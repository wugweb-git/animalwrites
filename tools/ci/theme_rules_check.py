#!/usr/bin/env python3
"""Deploy check: section heights come from the theme (docs/design-system.md, "Section heights").

A section's height is its content plus the .kit-section padding (--kit-space-section); cards use --kit-space-* tokens.
This check fails on a NEW rule in our CSS (themes/, plugins/*/assets/, not vendor or .min files) that:

  pct     sets a vertical padding in percent (padding-top, padding-bottom, padding-block, or padding's first value),
          which makes a height follow the width;
  height  sets a fixed height or min-height of 200px, 12.5rem/em or 20vh (or svh/dvh/lvh) or more;
  imp     sets padding / padding-block / padding-top / padding-bottom with !important on a section-level selector
          (.kit-section, .alignfull, section, .aw-camp-section, .kit-section-band, .kit-hero), unless the value is the
          theme's own section padding (var(--kit-space-section) or var(--kit-section-y));
  pad     sets padding (or padding-top/right/bottom/left/block/inline) on a card or block (BLOCKS below, the selector's
          last part) to under 24px on any side: the card padding is var(--kit-space-5) on every side at every width
          (owner, 2 Nov 2026; docs/card-sizes.md). var(--kit-space-1..4), var(--s-1..4), the gutter/head/grid tokens,
          numbers under 24px (1rem = 16px) and 0 are under; var(--kit-space-5..9) and --kit-space-section are fine.
          A child reset to 0 inside a padded box (.kit-card-body in a padded stacked card) is listed in the baseline.
  type    sets font-size on an h1, h2 or h3 to anything but the theme's own sizes (var(--kit-type-*), inherit), so
          headings keep the theme H1/H2/H3 sizes (owner, 2 Nov 2026).

Rules that existed on 2 Nov 2026 are listed in tools/ci/theme_rules_baseline.txt and still pass; remove a line there
when its rule is fixed. A new exception needs the owner's word and a line in the baseline with the reason.

    python3 tools/ci/theme_rules_check.py            # check
    python3 tools/ci/theme_rules_check.py --baseline # print today's findings in baseline form

The page rules are measured on the page with tools/ci/section_heights_audit.js, not here (docs/design-system.md,
Layout rules): from 1024 wide every section and hero is at most 720px with its padding and one viewport height (OVER),
and never above 800px (FAIL); on phones only Home sections have a limit (one 375x812 screen). A fixed height that
could break the desktop rule already fails here as "height".
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
BASELINE = ROOT / "tools/ci/theme_rules_baseline.txt"
SKIP = re.compile(r"/vendor/|\.min\.|/node_modules/|/dropins/")
SECTIONISH = re.compile(r"\.kit-section\b(?!-)|\.alignfull\b|(^|[\s>+~,(])section\b|\.aw-camp-section\b|\.kit-section-band\b|\.kit-hero\b")
BLOCKS = re.compile(
    r"\.(kit-card|kit-card-body|kit-tile|kit-photo-card|is-style-kits-card|aw-more-card|aw-more-body|aw-story--card|aw-bento-tile"
    r"|aw-org|aw-org-facts|aw-vol-banner|aw-vol-banner-body|kit-share|aw-camp-share-kit|aw-content|aw-festive|kit-claim|aw-report"
    r"|aw-side-camp|aw-side-camp-body|aw-side-claim|aw-activity-box|aw-helpline|aw-emergency|aw-form-for|aw-claim-summary"
    r"|aw-sponsored-item|aw-about-act|kit-form-summary|kit-form-done|kit-form-status|kit-toc|aw-near"
    r"|aw-camp-card|aw-camp-way|aw-listing-help|aw-local-card|ff-message-success)(--[\w-]+)?(?![\w-])"
)
# Boxes drawn on a list's items or a box's parts (the wrapper has no box): matched on the whole selector.
BLOCK_PARTS = re.compile(
    r"(\.aw-camp-docs-q li|\.aw-categories-index a|\.aw-camp-partners a|\.kit-toc > summary|\.kit-toc ol"
    r"|:is\(\.aw-camp-timeline, \.aw-camp-tracker\) tr)$"
)
PAD_PROPS = ("padding", "padding-top", "padding-right", "padding-bottom", "padding-left", "padding-block", "padding-inline",
             "padding-block-start", "padding-block-end", "padding-inline-start", "padding-inline-end")
PAD_MIN = 24
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


def subjects(sel):
    """The last compound of each selector in a list (what the rule styles), without :has()/:not() arguments."""
    out, depth, cur = [], 0, ""
    for c in sel:
        if c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
        if c == "," and depth == 0:
            out.append(cur)
            cur = ""
        else:
            cur += c
    out.append(cur)
    res = []
    for s in out:
        s = s.strip()
        while True:
            m = re.search(r":(has|not)\(", s)
            if not m:
                break
            d, j = 1, m.end()
            while j < len(s) and d:
                d += {"(": 1, ")": -1}.get(s[j], 0)
                j += 1
            s = s[:m.start()] + s[j:]
        parts, depth, cur = [], 0, ""
        for c in s:
            if c == "(":
                depth += 1
            elif c == ")":
                depth -= 1
            if depth == 0 and c in " >+~":
                if cur:
                    parts.append(cur)
                cur = ""
            else:
                cur += c
        if cur:
            parts.append(cur)
        if parts and "::" not in parts[-1]:
            res.append((" ".join(s.split()), parts[-1]))
    return res


def split_values(v):
    out, depth, cur = [], 0, ""
    for c in v:
        if c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
        if c.isspace() and depth == 0:
            if cur:
                out.append(cur)
            cur = ""
        else:
            cur += c
    if cur:
        out.append(cur)
    return out


def px(tok):
    """A padding value in px at its smallest (phones), or None when it cannot be told (unknown variable, calc, percent)."""
    tok = tok.strip()
    m = re.fullmatch(r"var\(\s*--(kit-space|s)-(\d+)\s*(?:,.*)?\)", tok)
    if m:
        n = int(m.group(2))
        return 0 if n < (5 if m.group(1) == "kit-space" else 6) else PAD_MIN
    if re.fullmatch(r"var\(\s*--kit-space-(section|band-gap)\s*(?:,.*)?\)", tok):
        return PAD_MIN
    if re.fullmatch(r"var\(\s*--kit-space-(gutter|grid|head|head-meta|collection)\s*(?:,.*)?\)", tok):
        return 16
    m = re.fullmatch(r"clamp\(([^,]+),.*\)", tok)
    if m:
        return px(m.group(1))
    m = re.fullmatch(r"(\d*\.?\d+)(px|rem|em)?", tok)
    if m:
        x = float(m.group(1))
        return x * 16 if m.group(2) in ("rem", "em") else x
    return None


def thin(k, v):
    vals = split_values(v.replace("!important", ""))
    if not vals or len(vals) > 4:
        return False
    return any(p is not None and p < PAD_MIN for p in map(px, vals))


HEADING = re.compile(r"(^|[\s>+~,(])h[1-3](?![\w-])")
TYPE_OK = re.compile(r"^\s*(var\(--kit-type-[\w-]+(,[^()]*)?\)|inherit)\s*(!important)?\s*$")


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
                if k in PAD_PROPS and any(BLOCKS.search(last) or BLOCK_PARTS.search(whole) for whole, last in subjects(sel)) and thin(k, v):
                    kinds.append("pad")
                if k == "font-size" and any(HEADING.search(last) for _, last in subjects(sel)) and not TYPE_OK.match(v):
                    kinds.append("type")
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
        print("New section-height or card-padding rules break the theme (docs/design-system.md, Section heights;"
              " docs/card-sizes.md, 24px card padding):")
        for rel, line, kind, sel, decl in new:
            print(f"  {rel}:{line}: [{kind}] {sel} {{ {decl} }}")
        sys.exit(1)
    print(f"theme rules check passed ({len(found)} known exceptions in the baseline)")


if __name__ == "__main__":
    main()
