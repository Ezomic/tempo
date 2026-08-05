"""The liveness probe is the one route that answers without a secret, so what
it does and does not say matters."""

from __future__ import annotations

import main


def test_answers_without_the_secret(client):
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_reports_the_garminconnect_version_in_use(client):
    body = client.get("/health").json()

    # The pinned version is the reason the droplet can stay on Python 3.10, so
    # knowing which one is actually loaded is the point of exposing it.
    assert body["garminconnect"].count(".") == 2


def test_reports_whether_the_token_store_can_be_written(client, tokens_root):
    assert client.get("/health").json()["token_store_writable"] is True


def test_notices_a_token_store_it_cannot_write(client, tokens_root, monkeypatch):
    monkeypatch.setattr(main, "TOKENS_ROOT", tokens_root / "gone")

    assert client.get("/health").json()["token_store_writable"] is False


def test_leaks_nothing_about_who_is_connected(client, tokens_root, auth):
    path = main.token_dir("31337")
    (path / "oauth1_token.json").write_text('{"oauth_token": "super-secret"}')

    body = client.get("/health").text

    assert "31337" not in body
    assert "secret" not in body.lower()
    assert set(client.get("/health").json()) == {
        "status",
        "garminconnect",
        "token_store_writable",
    }
