#!/usr/bin/env python3
"""Front-end scripts → .min.js with terser (pinned version below): comments out, whitespace out, local names shortened.

The site serves the .min.js files; the sources stay the readable files in git. Edit a source, then run this and commit
both files. The Deploy check runs it with --check and fails when a .min.js is not what this writes from its source.

Only scripts listed in FILES are minified. Each must keep its code inside function scopes (no top-level names that
other scripts read), because terser shortens local names.

    python3 tools/ci/js_min.py           # write every .min.js
    python3 tools/ci/js_min.py --check   # exit 1 when a .min.js is missing or out of date
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
TERSER = "terser@5.31.6"
FILES = [
    ROOT / "plugins/animal-writes-core/assets/js/aw-analytics.js",
]


def minify(src):
    """The minified text of one source."""
    out = subprocess.run(
        ["npx", "--yes", TERSER, str(src), "--compress", "--mangle", "--comments", "false", "--ecma", "2017"],
        check=True,
        capture_output=True,
        text=True,
    )
    return out.stdout.rstrip("\n") + "\n"


def main():
    check = "--check" in sys.argv[1:]
    bad = 0
    for src in FILES:
        dst = src.with_name(src.stem + ".min.js")
        text = minify(src)
        if check:
            if not dst.exists() or dst.read_text() != text:
                print(f"FAIL {dst.relative_to(ROOT)} is not {src.name} minified: run python3 tools/ci/js_min.py and commit it")
                bad = 1
            else:
                print(f"ok   {dst.relative_to(ROOT)}")
        else:
            dst.write_text(text)
            print(f"wrote {dst.relative_to(ROOT)} ({len(text)} bytes)")
    return bad


if __name__ == "__main__":
    sys.exit(main())
