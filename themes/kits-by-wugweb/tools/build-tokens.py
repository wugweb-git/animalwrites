#!/usr/bin/env python3
"""Kits responsive token engine: expand sparse sm/md/lg tokens, validate, emit CSS custom properties.

Contract: every token is {sm, md, lg}; sm is the base (min-width 0); md and lg inherit upward.
Primitives live under $primitives and are referenced as {group.key}. No device words in token names.

  python3 build-tokens.py --src tokens.json --css out.css [--json compiled.json] [--prefix kit] [--alias aw]
                         [--theme-json theme.json --primitives kits-v42.json]

--theme-json  also bind the tokens listed under "$wp" in tokens.json to WordPress presets: write the presets into
              theme.json (palette, font sizes, spacing, radius, shadows) and emit --kit-* (and the bound Kits v4.2
              primitives) as var(--wp--preset--...), so Appearance > Editor > Styles is the one place values change.

--prefix  the variable prefix written (default: kit) -> --kit-color-accent
--alias   also write --{alias}-name: var(--{prefix}-name) for every token, so older class names keep working
"""
import argparse
import json
import re
import sys
from pathlib import Path

BPS = ("sm", "md", "lg")


def expand(tok, name):
    if not isinstance(tok, dict) or "sm" not in tok:
        sys.exit(f"{name}: must be an object with an 'sm' value")
    bad = set(tok) - set(BPS)
    if bad:
        sys.exit(f"{name}: illegal breakpoint keys {bad} (only sm/md/lg)")
    out = {"sm": tok["sm"]}
    out["md"] = tok.get("md", out["sm"])
    out["lg"] = tok.get("lg", out["md"])
    return out


def resolve_refs(value, prims):
    def rep(m):
        group, key = m.group(1), m.group(2)
        try:
            return prims[group][key]
        except KeyError:
            sys.exit(f"unknown primitive {{{group}.{key}}}")
    return re.sub(r"\{(\w+)\.([\w-]+)\}", rep, str(value))


WP_KINDS = {  # $wp key -> (theme.json settings path, preset value key, css var kind)
    "palette": (("color", "palette"), "color", "color"),
    "fontSizes": (("typography", "fontSizes"), "size", "font-size"),
    "spacingSizes": (("spacing", "spacingSizes"), "size", "spacing"),
    "radiusSizes": (("border", "radiusSizes"), "size", "border-radius"),
    "shadows": (("shadow", "presets"), "shadow", "shadow"),
    "fontFamilies": (None, None, "font-family"),  # bound only: families stay hand-written in theme.json (font files)
}


def wp_kebab(slug):
    """The CSS name WordPress gives a preset slug (_wp_to_kebab_case): '2x-large' -> '2-x-large', 'h5' -> 'h-5'."""
    words = re.findall(r"[A-Z]?[a-z]+|[A-Z]+(?![a-z])|\d+", slug)
    return "-".join(w.lower() for w in words)


def literal(value, prims, depth=0):
    """Resolve var(--name) against the bare Kits v4.2 primitives until only a literal is left."""
    if depth > 8:
        sys.exit(f"cannot resolve {value}")
    out = re.sub(r"var\(--([\w-]+)\)", lambda m: prims[m.group(1)] if m.group(1) in prims else m.group(0), value)
    return out if out == value else literal(out, prims, depth + 1)


def wp_bindings(src, compiled, prims, parent=None):
    """-> (css lines for bound tokens, css lines for bound primitives, {kind: [preset]}) for this token file.
    A child token file binds through its parent's $wp map; only the presets whose values differ are its own."""
    wp = (parent or src).get("$wp") or {}
    tok_lines, prim_lines, presets = {}, {}, {}
    for kind, entries in wp.items():
        if kind.startswith("$"):
            continue
        path, key, cssk = WP_KINDS[kind]
        for e in entries:
            var = f"--wp--preset--{cssk}--{wp_kebab(e['slug'])}"
            values = set()
            for name in e.get("tokens", []):
                if name not in compiled:
                    continue
                t = compiled[name]
                if len({t["sm"], t["md"], t["lg"]}) > 1:
                    sys.exit(f"{name}: a token bound to a preset cannot change by breakpoint")
                values.add(t["sm"] if kind in ("shadows", "fontFamilies") else literal(t["sm"], prims))
                tok_lines[name] = f"var({var}, {values and next(iter(values))})" if kind != "fontFamilies" else f"var({var})"
            for name in e.get("primitives", []):
                values.add(prims[name])
                prim_lines[name] = f"var({var}, {prims[name]})"
            if e.get("value"):  # a preset whose value is a token itself (a size that changes by breakpoint, e.g. meta text)
                values.add(e["value"])
            if len(values) > 1:
                sys.exit(f"preset {e['slug']}: bound tokens disagree {values}")
            if path and values:
                presets.setdefault(kind, []).append({"slug": e["slug"], "name": e["name"], key: values.pop()})
    return tok_lines, prim_lines, presets


def write_theme_json(path, presets, parent_presets=None):
    """Write the bound presets into theme.json. A child writes a kind only when a value differs from its parent's."""
    tj = json.loads(Path(path).read_text())
    for kind, (spath, key, _) in WP_KINDS.items():
        if not spath:
            continue
        mine = presets.get(kind)
        if parent_presets is not None:  # child: complete list (WordPress replaces the parent's), or nothing (inherit)
            full = [dict(p) for p in parent_presets.get(kind, [])]
            for p in full:
                for m in mine or []:
                    if m["slug"] == p["slug"]:
                        p[key] = m[key]
            mine = full if full != parent_presets.get(kind, []) else None
        node = tj.setdefault("settings", {})
        for part in spath[:-1]:
            node = node.setdefault(part, {})
        if mine:
            node[spath[-1]] = mine
        else:
            node.pop(spath[-1], None)
            if not node:  # an empty group left behind
                parent_node = tj["settings"]
                for part in spath[:-2]:
                    parent_node = parent_node[part]
                parent_node.pop(spath[-2], None)
    Path(path).write_text(json.dumps(tj, indent="\t", ensure_ascii=False) + "\n")


def build(src_path, css_path, json_path=None, prefix="kit", alias=None, alias_names=(), theme_json=None, prims=None, parent=None, parent_presets=None):
    """theme_json/prims: bind the $wp presets (see wp_bindings). Returns the token count; with theme_json, also the presets."""
    src = json.loads(Path(src_path).read_text())
    bp = src["$breakpoints"]
    if bp.get("sm") != 0 or not (bp["sm"] < bp["md"] < bp["lg"]) or set(bp) != set(BPS):
        sys.exit("$breakpoints must be sm=0 < md < lg")
    for name in src["tokens"]:
        if re.search(r"(mobile|tablet|desktop|phone)", name):
            sys.exit(f"{name}: device words are not allowed in token names")
    compiled = {n: expand(t, n) for n, t in src["tokens"].items()}
    compiled = {n: {b: resolve_refs(v, src.get("$primitives", {})) for b, v in t.items()} for n, t in compiled.items()}
    assert {n: expand(t, n) for n, t in compiled.items()} == compiled, "expansion must be idempotent"
    if json_path:
        Path(json_path).write_text(json.dumps({"$breakpoints": bp, "tokens": compiled}, indent=2) + "\n")
    p = f"{prefix}-" if prefix else ""  # --prefix "" writes bare names: --neutral-0
    tok_bound, prim_bound, presets = ({}, {}, {})
    if theme_json:
        tok_bound, prim_bound, presets = wp_bindings(src, compiled, prims or {}, parent)
        if parent is not None:  # the parent's stylesheet already points these tokens at the presets
            prim_bound = {}
            compiled = {n: t for n, t in compiled.items() if n not in tok_bound}
    lines = [f"/* Generated by kits-by-wugweb/tools/build-tokens.py from {Path(src_path).name}. Do not edit. */", ":root {"]
    if prim_bound:
        lines.append("  /* Kits v4.2 primitives bound to theme.json presets (Styles > Typography / Layout) */")
        lines += [f"  --{n}: {v};" for n, v in prim_bound.items()]
    lines += [f"  --{p}{n}: {tok_bound[n] if n in tok_bound and parent is None else t['sm']};" for n, t in compiled.items()]
    if alias:  # aliases cover this file's tokens plus alias_names (e.g. the parent's), so a partial override file keeps every --alias-* name
        lines += [f"  --{alias}-{n}: var(--{p}{n});" for n in dict.fromkeys([*compiled, *alias_names])]
    lines.append("}")
    for b, prev in (("md", "sm"), ("lg", "md")):
        diff = [f"    --{p}{n}: {t[b]};" for n, t in compiled.items() if t[b] != t[prev] and n not in tok_bound]
        if diff:
            lines += [f"@media (min-width: {bp[b]}px) {{", "  :root {", *diff, "  }", "}"]
    Path(css_path).write_text("\n".join(lines) + "\n")
    if theme_json:
        write_theme_json(theme_json, presets, parent_presets)
        return len(compiled), presets
    return len(compiled)


def primitives_from(path):
    """Bare Kits v4.2 primitive values (kits-v42.json) as {name: value}, var() references resolved."""
    raw = {n: t["sm"] for n, t in json.loads(Path(path).read_text())["tokens"].items()}
    return {n: literal(v, raw) for n, v in raw.items()}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True)
    ap.add_argument("--css", required=True)
    ap.add_argument("--json")
    ap.add_argument("--prefix", default="kit")
    ap.add_argument("--alias")
    ap.add_argument("--theme-json")
    ap.add_argument("--primitives")
    a = ap.parse_args()
    prims = primitives_from(a.primitives) if a.primitives else None
    n = build(a.src, a.css, a.json, a.prefix, a.alias, theme_json=a.theme_json, prims=prims)
    n = n[0] if isinstance(n, tuple) else n
    print(f"{n} tokens -> {a.css}")


if __name__ == "__main__":
    main()
