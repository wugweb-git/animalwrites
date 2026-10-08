#!/usr/bin/env python3
"""Animal Writes tokens: brand values for the Kits token engine.

Builds themes/animal-writes/assets/css/tokens.css (--kit-* overrides where Animal Writes differs from Kits, + --aw-* aliases for every Kits token) and
themes/kits-by-wugweb/assets/css/kit-tokens.css (neutral Kits defaults).
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
AW = ROOT / "themes/animal-writes"
n1 = engine.build(KITS / "tokens/tokens.json", KITS / "assets/css/kit-tokens.css", KITS / "tokens/tokens.compiled.json", "kit")
kit_names = list(json.loads((KITS / "tokens/tokens.json").read_text())["tokens"])
n2 = engine.build(AW / "tokens/tokens.json", AW / "assets/css/tokens.css", AW / "tokens/tokens.compiled.json", "kit", "aw", kit_names)
print(f"Kits defaults: {n1} tokens; Animal Writes: {n2} tokens (+ --aw-* aliases)")
