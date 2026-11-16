#!/usr/bin/env python3
"""Build distributable zips of the Kits by Wugweb theme and the Kits Core plugin.

Two tracks:
  * wporg      — directory-ready: no `Update URI` in the theme, dev files stripped.
  * commercial — adds an `Update URI` to the theme header for self-hosted updates.

The plugin already carries its own `Update URI`; the track only changes the theme header.
Dev-only files (phpcs config, build tooling, node_modules, VCS, OS cruft) are excluded; the
CSS/JS sources are kept alongside their .min copies so a reviewer can see the unminified code.

Usage:
  python3 tools/build-dist.py --target theme  --track wporg      [--out dist]
  python3 tools/build-dist.py --target theme  --track commercial
  python3 tools/build-dist.py --target plugin [--out dist]       # kits-core
  python3 tools/build-dist.py --target all    --track wporg

It does NOT ship: a build is only as submission-ready as the source. This packages whatever is
on disk, so run the review gates (Theme Check, PHPCS, render-compare) first.
"""

from __future__ import annotations

import argparse
import fnmatch
import pathlib
import re
import shutil
import sys
import zipfile

REPO = pathlib.Path(__file__).resolve().parent.parent

TARGETS = {
    "theme": {
        "src": REPO / "themes" / "kits-by-wugweb",
        "slug": "kits-by-wugweb",
        "update_uri": "https://wugweb.com/kits/",  # added only on the commercial track
    },
    "plugin": {
        "src": REPO / "plugins" / "kits-core",
        "slug": "kits-core",
        "update_uri": None,  # the plugin header already has its own
    },
}

# Glob patterns (matched against the path relative to the source root) kept out of every build.
EXCLUDE = [
    ".git", ".git/*", ".github", ".github/*",
    "node_modules", "node_modules/*",
    "vendor", "vendor/*",
    "tests", "tests/*",
    "src", "src/*",            # JS sources for the admin app; build/ carries the compiled output
    "*.map",
    "phpcs.xml.dist", ".phpcs.xml.dist", "phpcs.xml",
    "package.json", "package-lock.json",
    "*.DS_Store", ".DS_Store",
    "tokens/*",                # token sources; theme.json + kit-tokens.css are the shipped output
]


def excluded(rel: str) -> bool:
    return any(fnmatch.fnmatch(rel, pat) or fnmatch.fnmatch(rel, pat.rstrip("/*")) for pat in EXCLUDE)


def set_theme_update_uri(style_css: pathlib.Path, uri: str | None) -> None:
    """Add, or strip, the `Update URI:` header line in a theme style.css for the chosen track."""
    text = style_css.read_text(encoding="utf-8")
    text = re.sub(r"^\s*Update URI:.*\n", "", text, flags=re.MULTILINE)  # drop any existing line
    if uri:
        text = re.sub(r"^(\s*Version:.*\n)", r"\1Update URI: " + uri + "\n", text, count=1, flags=re.MULTILINE)
    style_css.write_text(text, encoding="utf-8")


def build(target: str, track: str, out_dir: pathlib.Path) -> pathlib.Path:
    spec = TARGETS[target]
    src: pathlib.Path = spec["src"]
    slug = spec["slug"]
    if not src.is_dir():
        sys.exit(f"source not found: {src}")

    stage = out_dir / slug
    if stage.exists():
        shutil.rmtree(stage)
    stage.mkdir(parents=True)

    count = 0
    for path in sorted(src.rglob("*")):
        rel = path.relative_to(src).as_posix()
        if excluded(rel):
            continue
        dest = stage / rel
        if path.is_dir():
            dest.mkdir(parents=True, exist_ok=True)
        else:
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, dest)
            count += 1

    if target == "theme":
        set_theme_update_uri(stage / "style.css", spec["update_uri"] if track == "commercial" else None)

    zip_name = f"{slug}-{track}.zip" if target == "theme" else f"{slug}.zip"
    zip_path = out_dir / zip_name
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(stage.rglob("*")):
            if path.is_file():
                zf.write(path, pathlib.Path(slug) / path.relative_to(stage))
    shutil.rmtree(stage)
    try:
        shown = zip_path.relative_to(REPO)
    except ValueError:
        shown = zip_path
    print(f"{target}/{track}: {count} files -> {shown}")
    return zip_path


def main() -> None:
    ap = argparse.ArgumentParser(description="Build theme/plugin distributables.")
    ap.add_argument("--target", choices=["theme", "plugin", "all"], default="all")
    ap.add_argument("--track", choices=["wporg", "commercial"], default="wporg")
    ap.add_argument("--out", default="dist")
    args = ap.parse_args()

    out_dir = (REPO / args.out).resolve()
    out_dir.mkdir(parents=True, exist_ok=True)

    targets = ["theme", "plugin"] if args.target == "all" else [args.target]
    for t in targets:
        build(t, args.track, out_dir)


if __name__ == "__main__":
    main()
