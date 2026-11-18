#!/usr/bin/env python3
"""Build distributable zips of the Kits by Wugweb theme and the Kits Core plugin.

Two tracks:
  * wporg      — directory-ready: no `Update URI` in the theme, dev files stripped. Keeps the
                 admin-app src/, because wp.org requires the source of any compiled file in the zip.
  * commercial — adds an `Update URI` to the theme header for self-hosted updates; drops src/ (not
                 reviewed by wp.org, so the zip stays lean).

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
    "*.map",
    "phpcs.xml.dist", ".phpcs.xml.dist", "phpcs.xml",
    "package.json", "package-lock.json",
    "*.DS_Store", ".DS_Store",
    "tokens/*",                # token sources; theme.json + kit-tokens.css are the shipped output
    "tools", "tools/*",        # dev scripts (the theme's build-tokens.py); not part of the product
    "styles/animal-writes.json",  # the Animal Writes site's own style variation, a client brand: never in a Kits zip
    "assets/site", "assets/site/*",  # the site's own CSS (kits_site_component_style); never in a distributed zip
]

# The admin-app JS sources (src/, compiled into build/). wp.org requires the source of every
# compiled/minified file in the zip (or a public source repo linked in readme.txt), so src/ ships
# on the wporg track; only the self-hosted commercial track strips it to keep the zip lean.
SRC_EXCLUDE = ["src", "src/*"]

# The distributed theme must not default to the Animal Writes brand yellow (#FFBE1A) — that is a
# tenant colour, wrong for a generic marketplace theme. The repo and the live Animal Writes site
# keep #FFBE1A; only the built zip ships this neutral default. --kit-color-accent reads the `primary`
# preset, so rewriting theme.json's primary (plus the CSS fallback literals) neutralises the whole
# accent system. Owner: change this one value to pick a different shipped default; it must stay light
# enough for dark #191919 on-accent text (the demo variations slate.json/forest.json are checked).
AW_ACCENT = "#FFBE1A"
NEUTRAL_ACCENT = "#C6D2E0"  # grey-blue, same as the Slate variation; WCAG: ash #191919 on it = 11.5:1


def excluded(rel: str, patterns: list) -> bool:
    return any(fnmatch.fnmatch(rel, pat) or fnmatch.fnmatch(rel, pat.rstrip("/*")) for pat in patterns)


def neutralise_theme_accent(stage: pathlib.Path) -> int:
    """Swap the AW brand yellow for a neutral default across the STAGED theme (zip only, not the repo).

    Covers theme.json's `primary` preset and every `#FFBE1A` fallback literal in the shipped CSS, so a
    reviewer sees no tenant colour. Returns the number of files changed."""
    changed = 0
    for path in sorted(stage.rglob("*")):
        if path.suffix.lower() not in (".json", ".css"):
            continue
        text = path.read_text(encoding="utf-8")
        new = re.sub(re.escape(AW_ACCENT), NEUTRAL_ACCENT, text, flags=re.IGNORECASE)
        if new != text:
            path.write_text(new, encoding="utf-8")
            changed += 1
    return changed


def neutral_sidebar_part(stage: pathlib.Path) -> bool:
    """The STAGED theme's Sidebar part as an empty group (zip only, not the repo).

    The repo's part holds the site's dormant Advanced Ads slot (advads/gblock); a theme on wp.org may not depend on a
    plugin's block. Returns True when the part was changed."""
    part = stage / "parts" / "sidebar.html"
    if not part.is_file() or "wp:advads/" not in part.read_text(encoding="utf-8"):
        return False
    part.write_text('<!-- wp:group {"layout":{"type":"constrained"}} -->\n<div class="wp-block-group"></div>\n<!-- /wp:group -->\n', encoding="utf-8")
    return True


def neutral_registry(stage: pathlib.Path) -> bool:
    """The STAGED theme's kits-registry.json without the site's own names (zip only, not the repo).

    Drops the dormant Advertisement component (it names the Advanced Ads block), the `aw-` class names in the class
    lists, and the `aw_` function names inside the notes. Returns True when the file was changed."""
    import json

    reg = stage / "kits-registry.json"
    if not reg.is_file():
        return False
    before = reg.read_text(encoding="utf-8")
    note = re.compile(r"\s*\((?:aw_[a-z_]+|[a-z_]+\.php)\)|\s*\(aw_[a-z_]+\)")

    def walk(node):
        if isinstance(node, dict):
            return {k: walk(v) for k, v in node.items()}
        if isinstance(node, list):
            keep = []
            for item in node:
                if isinstance(item, dict) and str(item.get("block", "")).startswith("advads/"):
                    continue
                if isinstance(item, str) and item.startswith("aw-"):
                    continue
                keep.append(walk(item))
            return keep
        if isinstance(node, str):
            out = re.sub(r"\s*\((?:aw_[a-z_]+|\.aw-[^)]*)\)", "", node)
            return re.sub(r"ol\.aw-[a-z-]+\s*", "", out)
        return node

    data = walk(json.loads(before))
    after = json.dumps(data, indent=1, ensure_ascii=False) + "\n"
    if re.search(r"advads|\baw[-_]", after):
        sys.exit("kits-registry.json still names the site after neutralising: " + ", ".join(sorted(set(re.findall(r"[^\"]*(?:advads|\baw[-_])[^\"]*", after)))[:5]))
    reg.write_text(after, encoding="utf-8")
    return after != before


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

    # wp.org needs the source of the compiled build/; the commercial track is not reviewed, so it drops src/.
    patterns = EXCLUDE if track == "wporg" else EXCLUDE + SRC_EXCLUDE

    count = 0
    for path in sorted(src.rglob("*")):
        rel = path.relative_to(src).as_posix()
        if excluded(rel, patterns):
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
        n = neutralise_theme_accent(stage)
        print(f"  accent: {AW_ACCENT} -> {NEUTRAL_ACCENT} in {n} file(s) (repo keeps {AW_ACCENT})")
        if neutral_sidebar_part(stage):
            print("  parts/sidebar.html: the Advanced Ads block -> an empty group (repo keeps the dormant ad slot)")
        if neutral_registry(stage):
            print("  kits-registry.json: the site's own names (Advanced Ads component, aw- classes, aw_ functions) left out (repo keeps them)")

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
