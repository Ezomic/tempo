"""The shared secret is the only thing standing between a process on the box
and stored Garmin credentials, so its behaviour is pinned here."""

from __future__ import annotations

import io
import time
import zipfile

import pytest
from fastapi import HTTPException

import main


def make_zip(names: dict[str, bytes]) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w") as zf:
        for name, content in names.items():
            zf.writestr(name, content)

    return buffer.getvalue()


class TestSecretGuard:
    def test_rejects_a_missing_header(self, client):
        assert client.get("/status", params={"connection_id": "1"}).status_code == 401

    def test_rejects_a_wrong_header(self, client):
        response = client.get(
            "/status",
            params={"connection_id": "1"},
            headers={"X-Tempo-Secret": "nope"},
        )

        assert response.status_code == 401

    def test_rejects_a_correct_prefix(self, client, auth):
        response = client.get(
            "/status",
            params={"connection_id": "1"},
            headers={"X-Tempo-Secret": auth["X-Tempo-Secret"][:10]},
        )

        assert response.status_code == 401

    def test_accepts_the_real_secret(self, client, auth, tokens_root):
        response = client.get("/status", params={"connection_id": "1"}, headers=auth)

        assert response.status_code == 200
        assert response.json() == {"connected": False, "display_name": None}


class TestTokenDir:
    def test_keeps_a_sane_id_inside_the_token_root(self, tokens_root):
        path = main.token_dir("42")

        assert path.parent == tokens_root
        assert path.name == "42"

    def test_strips_traversal_out_of_an_id(self, tokens_root):
        path = main.token_dir("../../etc/passwd")

        # Everything but the allowed characters is dropped, so the result can
        # only ever be a direct child of the token root.
        assert path.parent == tokens_root
        assert ".." not in path.name

    def test_rejects_an_id_that_sanitises_to_nothing(self, tokens_root):
        with pytest.raises(HTTPException) as raised:
            main.token_dir("///")

        assert raised.value.status_code == 400

    def test_creates_the_directory_owner_only(self, tokens_root):
        path = main.token_dir("7")

        assert path.is_dir()
        assert (path.stat().st_mode & 0o777) == 0o700


class TestDeleteConnection:
    def test_removes_the_stored_session(self, client, auth, tokens_root):
        path = main.token_dir("5")
        (path / "oauth1_token.json").write_text("{}")
        (path / "oauth2_token.json").write_text("{}")

        response = client.delete("/connections/5", headers=auth)

        assert response.status_code == 200
        assert response.json()["removed"] is True
        assert not path.exists()

    def test_is_harmless_when_there_was_nothing_stored(self, client, auth, tokens_root):
        response = client.delete("/connections/404", headers=auth)

        assert response.status_code == 200
        assert response.json()["removed"] is False

    def test_needs_the_secret(self, client, tokens_root):
        path = main.token_dir("5")
        (path / "oauth1_token.json").write_text("{}")

        assert client.delete("/connections/5").status_code == 401
        assert path.exists()


class TestExtractFit:
    def test_pulls_the_fit_out_of_the_archive(self):
        archive = make_zip({"12345_ACTIVITY.fit": b"FIT-BYTES"})

        assert main.extract_fit(archive) == b"FIT-BYTES"

    def test_matches_the_extension_case_insensitively(self):
        archive = make_zip({"ACTIVITY.FIT": b"FIT-BYTES"})

        assert main.extract_fit(archive) == b"FIT-BYTES"

    def test_ignores_other_files_in_the_archive(self):
        archive = make_zip({"readme.txt": b"nope", "a.fit": b"FIT-BYTES"})

        assert main.extract_fit(archive) == b"FIT-BYTES"

    def test_rejects_an_archive_with_no_fit(self):
        with pytest.raises(HTTPException) as raised:
            main.extract_fit(make_zip({"readme.txt": b"nope"}))

        assert raised.value.status_code == 422


class TestPendingLogins:
    def setup_method(self):
        main._pending.clear()

    def teardown_method(self):
        main._pending.clear()

    def test_drops_logins_past_the_ttl(self):
        main._pending["old"] = {
            "created": time.monotonic() - main.PENDING_TTL_SECONDS - 1
        }
        main._pending["fresh"] = {"created": time.monotonic()}

        main.prune_pending()

        assert "old" not in main._pending
        assert "fresh" in main._pending

    def test_caps_the_store_by_evicting_the_oldest(self):
        for i in range(main.PENDING_MAX + 5):
            # Distinct, recent timestamps so the TTL sweep leaves them alone.
            main._pending[f"t{i}"] = {"created": time.monotonic() + i}

        main.prune_pending()

        assert len(main._pending) == main.PENDING_MAX
        # The oldest go first, so the most recent survive.
        assert "t0" not in main._pending
        assert f"t{main.PENDING_MAX + 4}" in main._pending


class TestGarminErrorMapping:
    """Laravel turns these codes into specific messages, so the mapping is part
    of the contract rather than an implementation detail."""

    def test_rate_limiting_maps_to_429(self):
        from garminconnect import GarminConnectTooManyRequestsError

        assert main.garmin_http_error(GarminConnectTooManyRequestsError("x")).status_code == 429

    def test_bad_credentials_map_to_401(self):
        from garminconnect import GarminConnectAuthenticationError

        assert main.garmin_http_error(GarminConnectAuthenticationError("x")).status_code == 401

    def test_garmin_being_down_maps_to_502(self):
        from garminconnect import GarminConnectConnectionError

        assert main.garmin_http_error(GarminConnectConnectionError("x")).status_code == 502

    def test_anything_else_maps_to_500(self):
        assert main.garmin_http_error(ValueError("boom")).status_code == 500
