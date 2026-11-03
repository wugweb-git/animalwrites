#!/usr/bin/env python3
"""Deploy gate (3 Nov 2026): no deploy while a FluentCRM campaign is sending.

Each deploy replaces the deployed folder, so mailweb is missing for a few seconds; a campaign batch that starts then
fails (33 emails on 3 Nov). Reads https://animalwrites.in/wp-json/aw/v1/deploy-status (yes or no only).

Exit 0: not sending, or the endpoint is not on the live site yet (first deploy of it; warns).
Exit 1: sending, or no clear answer from the site after 3 tries (fails closed). Override in an emergency: run the
Deploy check from GitHub Actions with "skip_mail_check" ticked.
Used by .github/workflows/deploy-check.yml and tools/aw-ship.sh.
"""
import json
import sys
import time
import urllib.error
import urllib.request

URL = "https://animalwrites.in/wp-json/aw/v1/deploy-status"
last = ""
for attempt in range(3):
    if attempt:
        time.sleep(10)
    try:
        req = urllib.request.Request(f"{URL}?t={int(time.time())}", headers={"User-Agent": "aw-deploy-check", "Cache-Control": "no-cache"})
        with urllib.request.urlopen(req, timeout=20) as r:
            data = json.load(r)
    except urllib.error.HTTPError as exc:
        if exc.code == 404:
            print("mail check: /aw/v1/deploy-status is not on the live site yet; passing (first deploy of it)")
            sys.exit(0)
        last = f"HTTP {exc.code}"
        continue
    except Exception as exc:  # noqa: BLE001
        last = str(exc)
        continue
    if isinstance(data, dict) and isinstance(data.get("sending"), bool):
        if data["sending"]:
            print("REFUSING to deploy: a FluentCRM campaign is sending. Pause it (FluentCRM, the campaign, Pause), "
                  "deploy, then Resume; or deploy when it has finished.")
            sys.exit(1)
        print("mail check passed: no campaign is sending")
        sys.exit(0)
    last = f"unexpected answer: {str(data)[:120]}"
print(f"REFUSING to deploy: could not tell whether a campaign is sending ({last}).")
sys.exit(1)
