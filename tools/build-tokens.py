#!/usr/bin/env python3
"""Tokens for Kits (master) and Animal Writes: one source, two outputs each.

tokens/tokens.json is the source. Each build writes
  - the CSS custom properties (--kit-*), and
  - the WordPress presets in theme.json (palette, font sizes, spacing, radius, shadows) for the tokens listed under "$wp",
with those --kit-* tokens pointing at the presets (var(--wp--preset--...)). So a value is set once, and a change made later
in Appearance > Editor > Styles reaches core blocks and Kits components alike.

  themes/kits-by-wugweb: tokens.json -> assets/css/kit-tokens.css (and its kit-tokens.min.css) + theme.json (the neutral
                         Kits defaults)
  plugins/animal-writes-core/theme (the Animal Writes theme files Animal Writes Core serves over Kits):
                         tokens.json -> assets/css/tokens.css (only tokens with no preset) + theme.json (only presets
                         whose values differ from Kits; a list it writes is complete, as it replaces the Kits one when
                         merged over it)

Usage: python3 tools/build-tokens.py
"""
import importlib.util
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("kits_tokens", ROOT / "themes/kits-by-wugweb/tools/build-tokens.py")
engine = importlib.util.module_from_spec(spec)
spec.loader.exec_module(engine)

KITS = ROOT / "themes/kits-by-wugweb"
AW = ROOT / "plugins/animal-writes-core/theme"  # the Animal Writes theme files (tools/ci/theme_copies.py)
prims = engine.primitives_from(KITS / "tokens/kits-v42.json")
kits_src = json.loads((KITS / "tokens/tokens.json").read_text())
n1, kits_presets = engine.build(KITS / "tokens/tokens.json", KITS / "assets/css/kit-tokens.css", KITS / "tokens/tokens.compiled.json", "kit", theme_json=KITS / "theme.json", prims=prims)
n2, _ = engine.build(AW / "tokens/tokens.json", AW / "assets/css/tokens.css", AW / "tokens/tokens.compiled.json", "kit", theme_json=AW / "theme.json", prims=prims, parent=kits_src, parent_presets=kits_presets)
css_min = importlib.util.module_from_spec(importlib.util.spec_from_file_location("css_min", ROOT / "tools/ci/css_min.py"))
css_min.__spec__.loader.exec_module(css_min)
min_out = KITS / "assets/css/kit-tokens.min.css"  # the file the site serves (tools/ci/css_min.py)
min_out.write_text(css_min.minify((KITS / "assets/css/kit-tokens.css").read_text(encoding="utf-8")), encoding="utf-8")
print(f"Kits: {n1} tokens; Animal Writes: {n2} tokens without a preset; presets written to both theme.json files")
