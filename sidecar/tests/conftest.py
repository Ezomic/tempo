from __future__ import annotations

import os
import sys
from pathlib import Path

import pytest

# main.py reads the secret and creates its token root at import time, so both
# have to be settled before it is imported.
SECRET = "test-secret-at-least-16-chars"
os.environ["TEMPO_SIDECAR_SECRET"] = SECRET
# No real sleeping in tests; the throttle is there to be kind to Garmin.
os.environ["TEMPO_SIDECAR_THROTTLE"] = "0"

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import main  # noqa: E402


@pytest.fixture
def tokens_root(tmp_path, monkeypatch):
    """Point the token store at a throwaway directory for the whole test."""
    root = tmp_path / "tokens"
    root.mkdir()
    monkeypatch.setattr(main, "TOKENS_ROOT", root)

    return root


@pytest.fixture
def client():
    from fastapi.testclient import TestClient

    with TestClient(main.app) as c:
        yield c


@pytest.fixture
def auth():
    return {"X-Tempo-Secret": SECRET}
