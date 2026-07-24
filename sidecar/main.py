from __future__ import annotations

import io
import os
import secrets
import time
import zipfile
from contextlib import asynccontextmanager
from datetime import date
from pathlib import Path
from typing import Any, Callable

from fastapi import Depends, FastAPI, Header, HTTPException, Query, Response
from garminconnect import Garmin
from pydantic import BaseModel

TOKENS_ROOT = Path(__file__).parent / "tokens"
TOKENS_ROOT.mkdir(exist_ok=True)
os.chmod(TOKENS_ROOT, 0o700)

SECRET = os.environ.get("TEMPO_SIDECAR_SECRET", "")
THROTTLE_SECONDS = float(os.environ.get("TEMPO_SIDECAR_THROTTLE", "0.6"))

# Abandoned MFA logins must not pile up in memory.
PENDING_TTL_SECONDS = 300
PENDING_MAX = 50


@asynccontextmanager
async def lifespan(_: FastAPI):
    # Fail closed: never run without a strong shared secret, since that secret is
    # the only thing gating access to Garmin credentials on a shared host.
    if len(SECRET) < 16:
        raise RuntimeError("TEMPO_SIDECAR_SECRET must be set to at least 16 characters.")
    yield


app = FastAPI(title="Tempo Garmin sidecar", lifespan=lifespan)

# Pending MFA logins: the garth client state cannot round-trip cleanly through
# Laravel, so the sidecar holds it in memory keyed by a short-lived login_token.
_pending: dict[str, dict[str, Any]] = {}


def require_secret(x_tempo_secret: str = Header(default="")) -> None:
    if not SECRET or not secrets.compare_digest(x_tempo_secret, SECRET):
        raise HTTPException(status_code=401, detail="Bad or missing secret")


def token_dir(connection_id: str) -> Path:
    safe = "".join(c for c in connection_id if c.isalnum() or c in "-_")
    if not safe:
        raise HTTPException(status_code=400, detail="Invalid connection_id")
    path = TOKENS_ROOT / safe
    path.mkdir(parents=True, exist_ok=True)
    os.chmod(path, 0o700)
    return path


def secure_token_files(path: Path) -> None:
    for entry in path.iterdir():
        if entry.is_file():
            os.chmod(entry, 0o600)


def prune_pending() -> None:
    now = time.monotonic()
    for key in [k for k, v in _pending.items() if now - v["created"] > PENDING_TTL_SECONDS]:
        _pending.pop(key, None)
    while len(_pending) > PENDING_MAX:
        oldest = min(_pending, key=lambda k: _pending[k]["created"])
        _pending.pop(oldest, None)


def has_tokens(connection_id: str) -> bool:
    return any(token_dir(connection_id).iterdir())


def load_client(connection_id: str) -> Garmin:
    if not has_tokens(connection_id):
        raise HTTPException(status_code=409, detail="Not connected")
    client = Garmin()
    client.login(str(token_dir(connection_id)))
    return client


def dump_tokens(client: Garmin, path: Path) -> None:
    # The garth client is exposed as `.garth` in newer garminconnect and `.client`
    # in 0.3.x. Support both so the same file runs in dev and on the droplet.
    garth = getattr(client, "garth", None) or getattr(client, "client", None)
    if garth is None:
        raise RuntimeError("Unable to locate garth client for token dump")
    garth.dump(str(path))
    secure_token_files(path)


def safe_name(client: Garmin) -> str | None:
    try:
        return client.get_full_name()
    except Exception:
        return None


def guarded(fn: Callable[[], Any]) -> Any:
    try:
        return fn()
    except Exception:
        return None


class LoginBody(BaseModel):
    connection_id: str
    email: str
    password: str


class MfaBody(BaseModel):
    connection_id: str
    login_token: str
    code: str


@app.post("/login")
def login(body: LoginBody, _: None = Depends(require_secret)) -> dict[str, Any]:
    client = Garmin(email=body.email, password=body.password, return_on_mfa=True)
    result1, result2 = client.login()

    if result1 == "needs_mfa":
        prune_pending()
        login_token = secrets.token_urlsafe(24)
        _pending[login_token] = {"client": client, "state": result2, "created": time.monotonic()}
        return {"status": "mfa_required", "login_token": login_token}

    path = token_dir(body.connection_id)
    dump_tokens(client, path)
    return {"status": "ok", "display_name": safe_name(client)}


@app.post("/login/mfa")
def login_mfa(body: MfaBody, _: None = Depends(require_secret)) -> dict[str, Any]:
    prune_pending()
    pending = _pending.pop(body.login_token, None)
    if pending is None:
        raise HTTPException(status_code=410, detail="Login token expired")

    client: Garmin = pending["client"]
    client.resume_login(pending["state"], body.code)
    path = token_dir(body.connection_id)
    dump_tokens(client, path)
    return {"status": "ok", "display_name": safe_name(client)}


@app.get("/status")
def status(connection_id: str = Query(...), _: None = Depends(require_secret)) -> dict[str, Any]:
    if not has_tokens(connection_id):
        return {"connected": False, "display_name": None}
    try:
        client = load_client(connection_id)
        return {"connected": True, "display_name": safe_name(client)}
    except Exception:
        return {"connected": False, "display_name": None}


@app.get("/activities")
def activities(
    connection_id: str = Query(...),
    start: date = Query(...),
    end: date = Query(...),
    _: None = Depends(require_secret),
) -> list[dict[str, Any]]:
    client = load_client(connection_id)
    return client.get_activities_by_date(start.isoformat(), end.isoformat())


@app.get("/activities/{activity_id}/fit")
def activity_fit(
    activity_id: str,
    connection_id: str = Query(...),
    _: None = Depends(require_secret),
) -> Response:
    client = load_client(connection_id)
    archive = client.download_activity(
        activity_id, dl_fmt=Garmin.ActivityDownloadFormat.ORIGINAL
    )
    return Response(content=extract_fit(archive), media_type="application/octet-stream")


def extract_fit(archive: bytes) -> bytes:
    # download_activity(ORIGINAL) returns a ZIP that contains the single .fit.
    with zipfile.ZipFile(io.BytesIO(archive)) as zf:
        names = [n for n in zf.namelist() if n.lower().endswith(".fit")]
        if not names:
            raise HTTPException(status_code=422, detail="No .fit in downloaded archive")
        return zf.read(names[0])


@app.get("/wellness")
def wellness(
    connection_id: str = Query(...),
    day: date = Query(..., alias="date"),
    _: None = Depends(require_secret),
) -> dict[str, Any]:
    client = load_client(connection_id)
    iso = day.isoformat()

    sleep = guarded(lambda: client.get_sleep_data(iso))
    time.sleep(THROTTLE_SECONDS)
    hrv = guarded(lambda: client.get_hrv_data(iso))
    time.sleep(THROTTLE_SECONDS)
    body_battery = guarded(lambda: client.get_body_battery(iso, iso))
    time.sleep(THROTTLE_SECONDS)
    resting_hr = guarded(lambda: client.get_rhr_day(iso))
    time.sleep(THROTTLE_SECONDS)
    stress = guarded(lambda: client.get_stress_data(iso))

    return {
        "date": iso,
        "sleep": sleep,
        "hrv": hrv,
        "body_battery": body_battery,
        "resting_hr": resting_hr,
        "stress": stress,
    }
