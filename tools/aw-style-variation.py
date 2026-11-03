#!/usr/bin/env python3
"""The Animal Writes look as a Kits style variation: themes/kits-by-wugweb/styles/animal-writes.json.

It copies the presets in themes/animal-writes/theme.json (colour palette, fonts), so the child theme stays the one
source until Kits runs without it. The variation shows on the Kits theme card and in Appearance > Editor > Styles >
Browse styles. Run it after a change to themes/animal-writes/theme.json; CI fails when the two differ.

Usage: python3 tools/aw-style-variation.py [--check]
"""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "themes/animal-writes/theme.json"
OUT = ROOT / "themes/kits-by-wugweb/styles/animal-writes.json"

settings = json.loads(SRC.read_text()).get("settings", {})
variation = {
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "title": "Animal Writes",
    "settings": {k: settings[k] for k in ("color", "typography") if k in settings},
}
text = json.dumps(variation, indent="\t", ensure_ascii=False) + "\n"

if "--check" in sys.argv:
    if not OUT.exists() or OUT.read_text() != text:
        print(f"{OUT.relative_to(ROOT)} is out of date: run python3 tools/aw-style-variation.py")
        sys.exit(1)
    print("Animal Writes style variation matches themes/animal-writes/theme.json")
    sys.exit(0)

OUT.parent.mkdir(exist_ok=True)
OUT.write_text(text)
print(f"wrote {OUT.relative_to(ROOT)}")
