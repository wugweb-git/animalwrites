#!/usr/bin/env python3
"""Deploy check (F4 accessibility gate): text contrast at least 4.5:1, non-text (icons, borders, rings) at least 3:1.

1. Every pair in a Kits registry's a11y.contrast list (text on background), resolved from the live token files.
2. Every campaign palette in campaign.css (.aw-camp and each .aw-camp--<name>): the pairs its rules put together.
A palette that fails any pair fails the deploy, so a new campaign colour set has to pass before it can ship.
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
# Same order as the site loads them: Kits primitives, Kits tokens, then the child theme's brand values.
TOKEN_FILES = [
    ROOT / "themes/kits-by-wugweb/assets/css/kits-v42.css",
    ROOT / "themes/kits-by-wugweb/assets/css/kit-tokens.css",
    ROOT / "themes/animal-writes/assets/css/tokens.css",
]
REGISTRIES = sorted((ROOT / "themes").glob("*/kits-registry.json"))
CAMPAIGN_CSS = ROOT / "plugins/animal-writes-core/assets/css/campaign.css"

TEXT, NON_TEXT = 4.5, 3.0
# Campaign pairs, from how campaign.css uses the palette: (foreground, background, minimum, where).
CAMPAIGN_PAIRS = [
    ("aw-camp-on-brand", "aw-camp-brand", TEXT, "hero, band, first card, step numbers: text on the brand colour"),
    ("kit-color-on-ink", "aw-camp-dark", TEXT, "dark sections, poster, table head: text on dark"),
    ("aw-camp-dark", "aw-camp-paper", TEXT, "closed band: text on paper"),
    ("aw-camp-brand", "aw-camp-paper", TEXT, "closed band kicker: brand text on paper"),
    ("aw-camp-brand", "kit-color-bg", TEXT, "key date: brand text on the page"),
    ("aw-camp-meta", "kit-color-bg", TEXT, "dates, steps, notes: meta text on the page"),
    ("aw-camp-meta", "aw-camp-paper", TEXT, "meta text on paper"),
    ("aw-camp-brand", "kit-color-bg", NON_TEXT, "icons, borders, rings on the page"),
    ("aw-camp-brand", "aw-camp-paper", NON_TEXT, "card icon on paper"),
    ("aw-camp-on-brand", "aw-camp-brand", NON_TEXT, "ghost button ring on the brand colour"),
]
# Not checked: the poster's brand rule and chip outline on dark. The rule is a separator and the chip has its own
# text, so WCAG 1.4.11 asks no 3:1 of them (the default palette has brand = dark there).


def strip(css):
    return re.sub(r"/\*.*?\*/", "", css, flags=re.S)


def decls(body):
    return {m.group(1): m.group(2).strip() for m in re.finditer(r"--([a-z0-9-]+)\s*:\s*([^;]+);", body)}


def root_vars():
    out = {}
    for f in TOKEN_FILES:
        for m in re.finditer(r":root\s*\{([^}]*)\}", strip(f.read_text())):
            out.update(decls(m.group(1)))
    return out


def resolve(name, vals):
    v = vals.get(name, "")
    for _ in range(10):
        m = re.search(r"var\(--([a-z0-9-]+)(?:\s*,\s*([^)]+))?\)", v)
        if not m:
            break
        v = v.replace(m.group(0), vals.get(m.group(1), m.group(2) or ""))
    return v.strip().lower()


def lum(hex_):
    if not re.fullmatch(r"#([0-9a-f]{3}|[0-9a-f]{6})", hex_ or ""):
        return None
    h = hex_[1:]
    if len(h) == 3:
        h = "".join(c * 2 for c in h)
    c = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    c = [x / 12.92 if x <= 0.03928 else ((x + 0.055) / 1.055) ** 2.4 for x in c]
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]


def ratio(a, b):
    la, lb = lum(a), lum(b)
    if la is None or lb is None:
        return None
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)


def main():
    vals = root_vars()
    fails, checked = [], 0

    def check(label, fg, bg, need, scope_vals):
        nonlocal checked
        a, b = resolve(fg, scope_vals), resolve(bg, scope_vals)
        r = ratio(a, b)
        checked += 1
        if r is None:
            fails.append(f"{label}: --{fg} ({a or 'unset'}) on --{bg} ({b or 'unset'}) is not a plain hex colour")
        elif r < need:
            fails.append(f"{label}: --{fg} {a} on --{bg} {b} is {r:.2f}:1, needs {need}:1")

    for reg in REGISTRIES:
        for it in json.loads(reg.read_text()).get("items", []):
            pair = (it.get("a11y") or {}).get("contrast")
            if pair:
                check(f"{reg.parent.name} / {it.get('name')}", pair[0].lstrip("-"), pair[1].lstrip("-"), TEXT, vals)

    css = strip(CAMPAIGN_CSS.read_text())
    base = {}
    for m in re.finditer(r"(^|\})\s*\.aw-camp\s*\{([^}]*)\}", css):
        base.update(decls(m.group(2)))
    palettes = {"default (.aw-camp)": {}}
    for m in re.finditer(r"(^|\})\s*\.aw-camp--([a-z0-9-]+)\s*\{([^}]*)\}", css):
        palettes[f".aw-camp--{m.group(2)}"] = decls(m.group(3))
    for name, own in palettes.items():
        scope = {**vals, **base, **own}
        for fg, bg, need, where in CAMPAIGN_PAIRS:
            check(f"campaign {name} ({where})", fg, bg, need, scope)

    if fails:
        print("Contrast below the minimum (text 4.5:1, non-text 3:1):")
        for f in fails:
            print("  " + f)
        sys.exit(1)
    print(f"contrast check passed: {checked} pairs, {len(palettes)} campaign palettes")


if __name__ == "__main__":
    main()
