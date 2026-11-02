#!/usr/bin/env python3
"""Deploy check (report mode): parts that moved to Appearance settings stay settings, and no new admin UI.

1. css   A NEW CSS rule (themes/, plugins/*/assets/) that sets a moved property on a part that is now a setting
         (docs/kits-control-map.md, "Pilot"). The part is the selector's last compound; :has()/:not() arguments are
         ignored. Moved so far:
           Section     .kit-section, .is-style-kits-section            padding, background, gap
           Head band   .kit-section-band(--*), .is-style-kits-head-band padding, background
           Hero        .kit-hero, .wp-block-kits-hero, .aw-ed-hero(--*), .aw-camp-hero    min-height, height
         These are set in Appearance > Editor > Styles > Blocks (Group > Section / Head band, Hero), so a CSS rule for
         them overrides the setting. Add a part here when it moves (same change that deletes its CSS).
2. admin A NEW admin page, menu, Customizer panel or admin stylesheet (owner, 3 Nov 2026: no new UI; settings use
         WordPress's own screens): add_menu_page / add_submenu_page / add_*_page, customize_register, a stylesheet
         enqueued or registered in a file that hooks admin_enqueue_scripts, a CSS file with "admin" in its path, or a
         <style> block in a file that adds an admin page.

What existed on 3 Nov 2026 is listed in tools/ci/settings_parts_baseline.txt and passes; remove a line when it goes.

    python3 tools/ci/settings_parts_check.py            # exit 1 on new findings (the workflow step reports only)
    python3 tools/ci/settings_parts_check.py --baseline # print today's findings in baseline form
"""
import pathlib
import re
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import theme_rules_check as t  # noqa: E402  (the shared CSS rule parser)

ROOT = t.ROOT
BASELINE = ROOT / "tools/ci/settings_parts_baseline.txt"

PARTS = [
    ("Section", re.compile(r"\.(kit-section|is-style-kits-section)(?![\w-])"), ("padding", "background", "gap", "row-gap")),
    ("Head band", re.compile(r"\.(kit-section-band(--[\w-]+)?|is-style-kits-head-band)(?![\w-])"), ("padding", "background")),
    ("Hero", re.compile(r"\.(kit-hero|wp-block-kits-hero|aw-ed-hero(--[\w-]+)?|aw-camp-hero)(?![\w-])"), ("min-height", "height")),
]

ADMIN_CALL = re.compile(r"\b(add_menu_page|add_submenu_page|add_(?:theme|options|management|dashboard|posts|pages|media|links|comments|plugins|users|object|utility)_page)\s*\(")
CUSTOMIZER = re.compile(r"['\"]customize_register['\"]")
STYLE_CALL = re.compile(r"\b(wp_enqueue_style|wp_register_style)\s*\(\s*([^,)]*)")


def prop_matches(prop, moved):
    return any(prop == m or prop.startswith(m + "-") for m in moved)


def css_findings():
    out = []
    for f in t.css_files():
        rel = str(f.relative_to(ROOT))
        for sel, body, _line, _at in t.rules(f.read_text(encoding="utf-8", errors="ignore")):
            for whole, last in t.subjects(sel):
                for part, rx, moved in PARTS:
                    if not rx.search(last):
                        continue
                    for k, v in t.decls(body):
                        if prop_matches(k, moved):
                            out.append(f"{rel} | css {part} | {whole} | {k}: {' '.join(v.split())}")
    return out


def php_files():
    for base in (ROOT / "themes", ROOT / "plugins"):
        for f in base.rglob("*.php"):
            if not t.SKIP.search("/" + str(f.relative_to(ROOT))) and "/tests/" not in str(f):
                yield f


def admin_findings():
    out = []
    for f in php_files():
        rel = str(f.relative_to(ROOT))
        s = f.read_text(encoding="utf-8", errors="ignore")
        admin_hook = "admin_enqueue_scripts" in s
        pages = False
        for m in ADMIN_CALL.finditer(s):
            pages = True
            line = s[m.start():s.find("\n", m.start())].strip()
            out.append(f"{rel} | admin page | {' '.join(line.split())[:160].rstrip()}")
        for m in CUSTOMIZER.finditer(s):
            out.append(f"{rel} | admin customizer | customize_register")
        if admin_hook:
            for m in STYLE_CALL.finditer(s):
                out.append(f"{rel} | admin style | {m.group(1)}( {' '.join(m.group(2).split())[:80]}")
        if pages and re.search(r"<style\b", s):
            out.append(f"{rel} | admin style | <style> block")
    for base in (ROOT / "themes", ROOT / "plugins"):
        for f in base.rglob("*.css"):
            rel = str(f.relative_to(ROOT))
            if "admin" in rel.lower() and not t.SKIP.search("/" + rel):
                out.append(f"{rel} | admin style | stylesheet file")
    return out


def main():
    found = sorted(set(css_findings() + admin_findings()))
    if "--baseline" in sys.argv:
        print("\n".join(found))
        return 0
    known = set()
    if BASELINE.exists():
        for line in BASELINE.read_text(encoding="utf-8").splitlines():
            if line.strip() and not line.startswith("#"):
                known.add(line.split("  #", 1)[0].rstrip())
    new = [x for x in found if x not in known]
    gone = sorted(known - set(found))
    if gone:
        print("fixed (remove from tools/ci/settings_parts_baseline.txt):")
        print("  " + "\n  ".join(gone))
    if new:
        print("NEW (a moved part got CSS again, or new admin UI; use the Styles setting or a core screen instead):")
        print("  " + "\n  ".join(new))
        return 1
    print(f"settings parts check passed ({len(known)} known in the baseline)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
