#!/usr/bin/env python3
"""Deploy check (owner, 9 Oct 2026): the footer is as tall as its content.

Fails if any CSS rule whose selector targets the footer (.kit-footer*, .kit-site-footer, .kit-legal-links,
.kit-powered, #site-footer, footer) sets max-height, a viewport-based height (vh/svh/dvh/lvh) or overflow: hidden.
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
FILES = list((ROOT / "themes").glob("*/assets/css/*.css"))
SEL = re.compile(r"\.kit-footer|\.kit-site-footer|\.kit-legal-links|\.kit-powered|#site-footer|(^|[\s,>])footer\b")
BAD = [
    (re.compile(r"(^|;|\{)\s*max-height\s*:", re.I), "max-height"),
    (re.compile(r"(^|;|\{)\s*(min-)?height\s*:[^;]*\b\d*\.?\d+(s|d|l)?vh\b", re.I), "viewport-based height"),
    (re.compile(r"(^|;|\{)\s*overflow(-y)?\s*:\s*hidden", re.I), "overflow: hidden"),
]


def rules(css):
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    depth, start, out, sel_start = 0, 0, [], 0
    for m in re.finditer(r"[{}]", css):
        if m.group() == "{":
            sel = css[sel_start:m.start()].strip()
            start = m.end()
            depth += 1
            out.append([sel, start, None])
        else:
            depth -= 1
            for r in reversed(out):
                if r[2] is None:
                    r[2] = m.start()
                    break
            sel_start = m.end()
        if m.group() == "{":
            sel_start = m.end()
    return [(sel, css[a:b]) for sel, a, b in out if b is not None and "{" not in css[a:b]]


fails = []
for f in FILES:
    for sel, body in rules(f.read_text(encoding="utf-8")):
        if sel.startswith("@") or not SEL.search(sel):
            continue
        for rx, what in BAD:
            if rx.search(body):
                fails.append(f"{f.relative_to(ROOT)}: {sel[:90]} → {what}")
if fails:
    print("FOOTER CHECK FAILED (footer height must follow its content):")
    print("\n".join(fails))
    sys.exit(1)
print("footer check passed")
