#!/usr/bin/env python3
"""Tokens for Kits (master) and Animal Writes (child): one source, two outputs per theme.

tokens/tokens.json is the source. Each build writes
  - the CSS custom properties (--kit-*), and
  - the WordPress presets in theme.json (palette, font sizes, spacing, radius, shadows) for the tokens listed under "$wp",
with those --kit-* tokens pointing at the presets (var(--wp--preset--...)). So a value is set once, and a change made later
in Appearance > Editor > Styles reaches core blocks and Kits components alike.

  themes/kits-by-wugweb: tokens.json -> assets/css/kit-tokens.css + theme.json (the neutral Kits defaults)
  themes/animal-writes:  tokens.json -> assets/css/tokens.css (only tokens with no preset) + theme.json (only presets
                         whose values differ from Kits; a list it writes is complete, as WordPress replaces the parent's)

When plugins/animal-writes-core/theme/ exists (the Animal Writes theme files Kits uses with no child theme), the child's
assets/css/tokens.css and theme.json are written there too, byte for byte, so tools/ci/theme_copies.py stays green.

Usage: python3 tools/build-tokens.py
"""
import importlib.util
import json
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location("kits_tokens", ROOT / "themes/kits-by-wugweb/tools/build-tokens.py")
engine = importlib.util.module_from_spec(spec)
spec.loader.exec_module(engine)

KITS = ROOT / "themes/kits-by-wugweb"
AW = ROOT / "themes/animal-writes"
AW_COPY = ROOT / "plugins/animal-writes-core/theme"  # the plugin's copies of the child theme files
prims = engine.primitives_from(KITS / "tokens/kits-v42.json")
kits_src = json.loads((KITS / "tokens/tokens.json").read_text())
n1, kits_presets = engine.build(KITS / "tokens/tokens.json", KITS / "assets/css/kit-tokens.css", KITS / "tokens/tokens.compiled.json", "kit", theme_json=KITS / "theme.json", prims=prims)
n2, _ = engine.build(AW / "tokens/tokens.json", AW / "assets/css/tokens.css", AW / "tokens/tokens.compiled.json", "kit", theme_json=AW / "theme.json", prims=prims, parent=kits_src, parent_presets=kits_presets)
print(f"Kits: {n1} tokens; Animal Writes: {n2} tokens without a preset; presets written to both theme.json files")
if AW_COPY.is_dir():
    for rel in ("assets/css/tokens.css", "theme.json"):
        (AW_COPY / rel).parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(AW / rel, AW_COPY / rel)
    print(f"Animal Writes tokens.css and theme.json copied to {AW_COPY.relative_to(ROOT)}/")
