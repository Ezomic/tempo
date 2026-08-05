# Deploying the Tempo Garmin sidecar

Localhost-only FastAPI service that holds the Garmin session for Tempo. It must
never be reachable off-box: it is bound to `127.0.0.1` and gated by a shared
secret that only the Laravel app knows.

## One-time setup (as root on the droplet)

```bash
# Dedicated unprivileged user, no login, no home beyond the app dir.
useradd --system --home-dir /opt/tempo-sidecar --shell /usr/sbin/nologin tempo-sidecar

# App directory + code.
install -d -o tempo-sidecar -g tempo-sidecar -m 0750 /opt/tempo-sidecar
# copy main.py and requirements.txt into /opt/tempo-sidecar (the first time only;
# afterwards the deploy workflow keeps it in sync, see "Automated deploys" below)

# Python 3.12 venv owned by the service user.
sudo -u tempo-sidecar python3.12 -m venv /opt/tempo-sidecar/.venv
sudo -u tempo-sidecar /opt/tempo-sidecar/.venv/bin/pip install -r /opt/tempo-sidecar/requirements.txt

# Token store (the only writable path); 0700 so tokens are owner-only.
install -d -o tempo-sidecar -g tempo-sidecar -m 0700 /opt/tempo-sidecar/tokens

# Secret file: same value as GARMIN_SIDECAR_SECRET in the Laravel .env.
install -d -o root -g tempo-sidecar -m 0750 /etc/tempo-sidecar
umask 0077
printf 'TEMPO_SIDECAR_SECRET=%s\n' "$(openssl rand -hex 24)" > /etc/tempo-sidecar/env
chown root:tempo-sidecar /etc/tempo-sidecar/env
chmod 0640 /etc/tempo-sidecar/env
# -> copy this same secret into the Laravel app's GARMIN_SIDECAR_SECRET

# Install and start the unit.
cp /opt/tempo-sidecar/deploy/tempo-sidecar.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now tempo-sidecar
systemctl status tempo-sidecar
```

## Notes

- **No inbound firewall rule is needed for the port**: binding to `127.0.0.1`
  already keeps it off the network. Just make sure nothing proxies `8790`
  publicly. Verify with `ss -ltnp 'sport = :8790'` (should show `127.0.0.1`).
- **TLS** is Tempo's concern (terminated at the Herd/nginx proxy in front of the
  Laravel app), not the sidecar's: the sidecar only ever speaks plain HTTP over
  loopback.
- The service **fails closed**: it refuses to start if `TEMPO_SIDECAR_SECRET`
  is missing or shorter than 16 characters.
- Garmin tokens live under `/opt/tempo-sidecar/tokens/{connection_id}/` at
  `0700`/`0600`; `ProtectSystem=strict` + `ReadWritePaths` keep the rest of the
  filesystem read-only to the service.
- Updating the code: handled automatically, see below.

## Automated deploys

The sidecar now lives in the app repo under `sidecar/` and is deployed by the
same GitHub Actions workflow as the web app (`.github/workflows/deploy.yml`).
After the app deploys, the workflow runs `sudo /usr/local/bin/deploy-tempo-sidecar.sh`,
a root-owned wrapper that syncs `sidecar/` into `/opt/tempo-sidecar`, installs
pinned requirements into the venv as the service user, and restarts the unit.

One-time setup for the wrapper (as root). The script is version-controlled at
`sidecar/deploy/deploy-tempo-sidecar.sh`; install it root-owned so the deploy
user cannot modify what it runs as root:

```bash
install -o root -g root -m 0755 \
  /home/deploy/tempo/sidecar/deploy/deploy-tempo-sidecar.sh \
  /usr/local/bin/deploy-tempo-sidecar.sh

# Let the deploy user run only this script via sudo, without a password.
echo 'deploy ALL=(root) NOPASSWD: /usr/local/bin/deploy-tempo-sidecar.sh' \
  > /etc/sudoers.d/tempo-sidecar-deploy
chmod 0440 /etc/sudoers.d/tempo-sidecar-deploy
visudo -cf /etc/sudoers.d/tempo-sidecar-deploy
```

Pin `garminconnect` in `requirements.txt` to the exact version the droplet's
Python runs (currently `0.3.2` on Python 3.10) so dev and prod never drift.

## Health

`GET /health` is the one route that answers without the shared secret:

```bash
curl -s http://127.0.0.1:8790/health
# {"status":"ok","garminconnect":"0.3.2","token_store_writable":true}
```

Unauthenticated is safe here because the service is bound to loopback, and
because the response says only whether the process is up and can still write
its token store. It never names a connection or an athlete. Every other route
keeps the secret guard.

The unit uses it as an `ExecStartPost` gate (`deploy/healthcheck.py`), so a
start that binds the port but cannot serve shows up as a failed unit rather
than an active one. `systemctl status tempo-sidecar` is therefore meaningful
again.

To have the **status** app watch the sidecar, point a check at
`http://127.0.0.1:8790/health` from the droplet itself. It cannot be polled
from off-box, by design.
