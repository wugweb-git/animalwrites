#!/usr/bin/env python3
"""Deploy check: the Animal Writes theme files that animal-writes-core carries (plugins/animal-writes-core/theme/, used
when Kits by Wugweb runs with no child theme) are the same as the child theme's (themes/animal-writes/).

While both exist, the child theme is the source: change a file there, then copy it to the plugin's theme/ folder. This
check fails when a copy differs from the child's file, or when a child template, part, pattern or registry has no copy.
The other checks read the child's file and skip its copy (is_copy), so a rule is not counted twice. Once the child
theme is gone, the plugin's files are read in its place (theme_file).

    python3 tools/ci/theme_copies.py        # exit 1 when a copy differs or is missing
"""
import filecmp
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
CHILD = ROOT / "themes/animal-writes"
COPY = ROOT / "plugins/animal-writes-core/theme"
# Each of these child files must have a copy (the plugin serves them; a missing one would be missing on the site).
REQUIRED = ("templates", "parts", "patterns", "kits-registry.json")


def is_copy(path):
    """True for a file under the plugin's theme/ folder whose child theme file exists (that file is read instead)."""
    p = pathlib.Path(path).resolve()
    try:
        rel = p.relative_to(COPY)
    except ValueError:
        return False
    return (CHILD / rel).is_file()


def theme_file(rel):
    """The child theme's file, or the plugin's copy when the child theme is gone."""
    child = CHILD / rel
    return child if child.exists() or not (COPY / rel).exists() else COPY / rel


def main():
    if not CHILD.is_dir():
        print("theme copies: no child theme, the plugin's theme/ folder is the source")
        return 0
    bad, n = [], 0
    for f in sorted(COPY.rglob("*")):
        if not f.is_file() or f.name == ".DS_Store":
            continue
        rel = f.relative_to(COPY)
        child = CHILD / rel
        if child.is_file():
            n += 1
            if not filecmp.cmp(f, child, shallow=False):
                bad.append(f"DIFFERS {rel} (copy themes/animal-writes/{rel} over it)")
    for top in REQUIRED:
        src = CHILD / top
        for f in sorted([src] if src.is_file() else src.rglob("*") if src.is_dir() else []):
            if f.is_file() and f.name != ".DS_Store" and not (COPY / f.relative_to(CHILD)).is_file():
                bad.append(f"MISSING {f.relative_to(CHILD)} (copy it to plugins/animal-writes-core/theme/)")
    if bad:
        print("Animal Writes theme copies out of step with the child theme:")
        print("\n".join("  " + b for b in bad))
        return 1
    print(f"theme copies check passed ({n} files the same as the child theme's)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
