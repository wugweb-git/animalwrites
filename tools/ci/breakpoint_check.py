#!/usr/bin/env python3
"""Deploy check: the only breakpoints are md 768 and lg 1200 (Kits tokens.json $breakpoints).

Fails on any width in an @media query (CSS, inline styles in PHP/HTML) or a matchMedia() call (JS) other than
min-width 768/1200 or max-width 767/1199. A component that must change at another width uses a container query.
Third-party files (assets/vendor, *.min.*, altcha) are skipped, and so is the built Kits shell (kits-core/build/shell): it is
src/shell, which is checked here, plus WordPress's own DataViews package (its 600px phone layout), bundled in (13 Nov 2026).
"""
import pathlib
import re
import sys

from theme_copies import is_copy

ROOT = pathlib.Path(__file__).resolve().parents[2]
ALLOWED = {("min", "768"), ("max", "767"), ("min", "1200"), ("max", "1199")}
WIDTH = re.compile(r"\b(min|max)-width\s*:\s*([0-9.]+)(px|em|rem)")
QUERY = re.compile(r"@media[^{]*|matchMedia\(\s*['\"][^'\"]*")
SKIP = re.compile(r"/vendor/|\.min\.|altcha|/node_modules/|/kits-core/build/shell/")


def main():
    bad = []
    for base in ("themes", "plugins"):
        for f in (ROOT / base).rglob("*"):
            if f.suffix not in (".css", ".php", ".html", ".js") or SKIP.search(str(f)) or is_copy(f):
                continue
            for n, line in enumerate(f.read_text(errors="ignore").splitlines(), 1):
                for q in QUERY.findall(line):
                    for kind, num, unit in WIDTH.findall(q):
                        if unit != "px" or (kind, num) not in ALLOWED:
                            bad.append(f"{f.relative_to(ROOT)}:{n}: {kind}-width {num}{unit}")
    if bad:
        print("Width queries outside 767/768 and 1199/1200 (use a container query instead):")
        print("\n".join("  " + b for b in bad))
        sys.exit(1)
    print("breakpoint check passed")


if __name__ == "__main__":
    main()
