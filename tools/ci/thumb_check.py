#!/usr/bin/env python3
"""Deploy check (owner, 9 Oct 2026): no picture is used by two published items on the live site.

Reads https://animalwrites.in/wp-json/aw/v1/thumb-dupes (count only). Fails when it reports duplicates. If the
site can't be reached or the endpoint isn't there yet, it warns and passes (the gate must not depend on the network).
"""
import json
import sys
import urllib.request

URL = "https://animalwrites.in/wp-json/aw/v1/thumb-dupes"
try:
    with urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "aw-deploy-check"}), timeout=20) as r:
        data = json.load(r)
except Exception as exc:  # noqa: BLE001
    print(f"thumbnail check skipped ({exc})")
    sys.exit(0)
n = int(data.get("duplicates", 0)) if isinstance(data, dict) else 0
if n:
    print(f"THUMBNAIL CHECK FAILED: {n} picture(s) are used by more than one published item (see Tools → Check site).")
    sys.exit(1)
print("thumbnail check passed")
