#!/usr/bin/env python3
"""Deploy check: section heights come from the theme (docs/design-system.md, "Section heights").

A section's height is its content plus the .kit-section padding (--kit-space-section); cards use --kit-space-* tokens.
This check fails on a NEW rule in our CSS (themes/, plugins/*/assets/, not vendor or .min files) that:

  pct     sets a vertical padding in percent (padding-top, padding-bottom, padding-block, or padding's first value),
          which makes a height follow the width;
  height  sets a fixed height or min-height of 200px, 12.5rem/em or 20vh (or svh/dvh/lvh) or more;
  imp     sets padding / padding-block / padding-top / padding-bottom with !important on a section-level selector
          (.kit-section, .alignfull, section, .aw-camp-section, .kit-section-band, .kit-hero), unless the value is the
          theme's own section padding (var(--kit-space-section) or var(--kit-section-y)).

Rules that existed on 2 Nov 2026 are listed in tools/ci/theme_rules_baseline.txt and still pass; remove a line there
when its rule is fixed. A new exception needs the owner's word and a line in the baseline with the reason.

    python3 tools/ci/theme_rules_check.py            # check
    python3 tools/ci/theme_rules_check.py --baseline # print today's findings in baseline form

The phone rule (every Home section fits one 375x812 screen; card rows scroll sideways) is measured on the page, not
here: see docs/design-system.md.
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASELINE = ROOT / "tools/ci/theme_rules_baseline.txt"
SKIP = re.compile(r"/vendor/|\.min\.|/node_modules/|/dropins/")
SECTIONISH = re.compile(r"\.kit-section\b(?!-)|\.alignfull\b|(^|[\s>+~,(])section\b|\.aw-camp-section\b|\.kit-section-band\b|\.kit-hero\b")
THEME_PAD = re.compile(r"^\s*var\(--kit-(space-section|section-y)\)(\s+var\(--kit-(space-section|section-y)\))?\s*!important\s*$")


def css_files():
    for base in (ROOT / "themes", ROOT / "plugins"):
        for f in base.rglob("*.css"):
            rel = str(f.relative_to(ROOT))
            if SKIP.search("/" + rel):
                continue
            if rel.startswith("plugins/") and "/assets/" not in rel:
                continue
            yield f


def rules(text):
    """(selector, declarations, line) for every style rule, at-rules opened (media, container, supports)."""
    text = re.sub(r"/\*.*?\*/", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)
    out, i, n = [], 0, len(text)
    stack = []
    start = 0
    while i < n:
        c = text[i]
        if c == "{":
            head = text[start:i].strip()
            line = text.count("\n", 0, start) + 1 + (len(text[start:i]) - len(text[start:i].lstrip("\n")) and 0)
            if head.startswith("@"):
                stack.append(None)
                start = i + 1
            else:
                j = text.find("}", i)
                if j < 0:
                    break
                body = text[i + 1:j]
                line = text.count("\n", 0, i) + 1 - head.count("\n")
                out.append((" ".join(head.split()), body, line))
                i = j
                start = j + 1
        elif c == "}":
            if stack:
                stack.pop()
            start = i + 1
        elif c == ";" and not stack and text[start:i].strip().startswith("@"):
            start = i + 1  # @import / @charset
        i += 1
    return out


def decls(body):
    for d in body.split(";"):
        if ":" in d:
            k, v = d.split(":", 1)
            yield k.strip().lower(), v.strip()


def big(v):
    for num, unit in re.findall(r"(?<![\w.-])(\d+(?:\.\d+)?)(px|rem|em|vh|svh|dvh|lvh)\b", v):
        x = float(num)
        if (unit == "px" and x >= 200) or (unit in ("rem", "em") and x >= 12.5) or (unit.endswith("vh") and x >= 20):
            return True
    return False


def findings():
    out = []
    for f in sorted(css_files()):
        rel = str(f.relative_to(ROOT))
        for sel, body, line in rules(f.read_text(errors="ignore")):
            for k, v in decls(body):
                kinds = []
                if k in ("padding-top", "padding-bottom", "padding-block", "padding-block-start", "padding-block-end"):
                    if "%" in v:
                        kinds.append("pct")
                elif k == "padding":
                    first = v.replace("!important", "").split()
                    if first and "%" in first[0] or (len(first) >= 3 and "%" in first[2]):
                        kinds.append("pct")
                if k in ("height", "min-height", "block-size", "min-block-size") and big(v):
                    kinds.append("height")
                if k in ("padding", "padding-block", "padding-top", "padding-bottom") and "!important" in v and SECTIONISH.search(sel) and not THEME_PAD.match(v):
                    kinds.append("imp")
                for kind in kinds:
                    out.append((rel, line, kind, sel, f"{k}: {v}"))
    return out


def key(rel, kind, sel, decl):
    return f"{rel} | {kind} | {sel} | {decl}"


def main():
    found = findings()
    if "--baseline" in sys.argv:
        for rel, line, kind, sel, decl in found:
            print(key(rel, kind, sel, decl))
        return
    allowed = set()
    if BASELINE.exists():
        for l in BASELINE.read_text().splitlines():
            l = l.split("  #", 1)[0].strip()
            if l and not l.startswith("#"):
                allowed.add(l)
    new = [x for x in found if key(x[0], x[2], x[3], x[4]) not in allowed]
    if new:
        print("New section-height rules break the theme (docs/design-system.md, Section heights):")
        for rel, line, kind, sel, decl in new:
            print(f"  {rel}:{line}: [{kind}] {sel} {{ {decl} }}")
        sys.exit(1)
    print(f"theme rules check passed ({len(found)} known exceptions in the baseline)")


if __name__ == "__main__":
    main()
