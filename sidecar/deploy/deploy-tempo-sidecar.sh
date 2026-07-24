#!/usr/bin/env bash
# Privileged sidecar deploy step. Installed once at /usr/local/bin/ (root-owned),
# invoked by the deploy workflow via a single NOPASSWD sudo entry. Do not let the
# deploy user write this file: it runs as root.
set -euo pipefail

SRC=/home/deploy/tempo/sidecar
DEST=/opt/tempo-sidecar

rsync -a --delete \
  --exclude tokens \
  --exclude .venv \
  --exclude __pycache__ \
  "$SRC"/ "$DEST"/

chown -R tempo-sidecar:tempo-sidecar "$DEST"/main.py "$DEST"/requirements.txt "$DEST"/deploy
sudo -u tempo-sidecar "$DEST"/.venv/bin/pip install -q -r "$DEST"/requirements.txt
systemctl restart tempo-sidecar
