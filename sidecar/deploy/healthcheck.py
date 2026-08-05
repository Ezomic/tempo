"""Block until the sidecar answers /health, or fail.

Used as the unit's ExecStartPost so that a start which binds the port but
cannot actually serve is reported as a failed unit instead of an active one.
"""

from __future__ import annotations

import sys
import time
import urllib.error
import urllib.request

URL = "http://127.0.0.1:8790/health"
ATTEMPTS = 20
DELAY_SECONDS = 0.5


def main() -> int:
    last = ""

    for _ in range(ATTEMPTS):
        try:
            with urllib.request.urlopen(URL, timeout=2) as response:
                if response.status == 200:
                    return 0
                last = f"HTTP {response.status}"
        except (urllib.error.URLError, OSError) as exc:
            last = str(exc)

        time.sleep(DELAY_SECONDS)

    print(f"sidecar did not become healthy: {last}", file=sys.stderr)

    return 1


if __name__ == "__main__":
    raise SystemExit(main())
