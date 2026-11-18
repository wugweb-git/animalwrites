#!/usr/bin/env python3
"""The Animal Writes look as a Kits style variation: themes/kits-by-wugweb/styles/animal-writes.json.

It copies the presets in plugins/animal-writes-core/theme/theme.json (colour palette, fonts), the one source. The
variation shows on the Kits theme card and in Appearance > Editor > Styles > Browse styles. Run it after a change to
that theme.json (tools/build-tokens.py writes it); CI fails when the two differ.

The variation's own "styles" (styles.css: the CSS only Animal Writes uses, from aw.css, retired 18 Nov 2026) are kept
as they are in the file: edit them there. The site serves them from its global styles, where they are saved.
The plugin's copy (plugins/animal-writes-core/theme/styles/animal-writes.json) is written the same.

Usage: python3 tools/aw-style-variation.py [--check]
"""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "plugins/animal-writes-core/theme/theme.json"
OUT = ROOT / "themes/kits-by-wugweb/styles/animal-writes.json"
COPY = ROOT / "plugins/animal-writes-core/theme/styles/animal-writes.json"

settings = json.loads(SRC.read_text()).get("settings", {})
variation = {
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "title": "Animal Writes",
    "settings": {k: settings[k] for k in ("color", "typography") if k in settings},
}
styles = json.loads(OUT.read_text()).get("styles") if OUT.exists() else None
if styles:
    variation["styles"] = styles
text = json.dumps(variation, indent="\t", ensure_ascii=False) + "\n"

if "--check" in sys.argv:
    if not OUT.exists() or OUT.read_text() != text or (COPY.exists() and COPY.read_text() != text):
        print(f"{OUT.relative_to(ROOT)} is out of date: run python3 tools/aw-style-variation.py")
        sys.exit(1)
    print("Animal Writes style variation matches plugins/animal-writes-core/theme/theme.json")
    sys.exit(0)

OUT.parent.mkdir(exist_ok=True)
OUT.write_text(text)
print(f"wrote {OUT.relative_to(ROOT)}")
if COPY.parent.is_dir():
    COPY.write_text(text)
    print(f"wrote {COPY.relative_to(ROOT)}")
