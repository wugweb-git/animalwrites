"""One name per component (design-system consolidation, step 4): every aw-* class that has a kit-* twin in the Kits
master becomes the kit-* name, in markup, scripts and stylesheets; selector lists that then repeat themselves are
merged, and a class written twice in one attribute is written once. AW-only components keep their aw-* names.

    python3 tools/consolidate/rename_classes.py names.txt     # names.txt: one aw-* base name per line

Database content (Pages, synced patterns) is changed by the matching PHP in the same folder.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
names = [n.strip() for n in open(sys.argv[1]) if n.strip().startswith("aw-")]
names.sort(key=len, reverse=True)  # longer names first
# A name as a whole class, or as the start of a modifier (aw-ico--mail, or 'aw-ico--' . $name in PHP).
pat = re.compile(r"(?<![a-zA-Z0-9_-])(" + "|".join(re.escape(n) for n in names) + r")(?=--|[^a-zA-Z0-9_-]|$)")


def rename(s):
    return pat.sub(lambda m: "kit-" + m.group(1)[3:], s)


def dedupe_classes(s):
    prev = None
    while prev != s:
        prev = s
        s = re.sub(r"(?<![a-zA-Z0-9_-])(kit-[a-z0-9-]+) \1(?![a-zA-Z0-9_-])", r"\1", s)
    return s


def split_top(prelude):
    out, depth, cur = [], 0, ""
    for ch in prelude:
        if ch in "([":
            depth += 1
        elif ch in ")]":
            depth -= 1
        if ch == "," and depth == 0:
            out.append(cur)
            cur = ""
        else:
            cur += ch
    out.append(cur)
    return out


def dedupe_selectors(css):
    res, i, last = [], 0, 0
    for m in re.finditer(r"[{}]", css):
        if m.group() == "{":
            start = max(css.rfind("}", 0, m.start()), css.rfind("{", 0, m.start()), css.rfind(";", 0, m.start()), css.rfind("*/", 0, m.start()) + 1) + 1
            pre = css[start:m.start()]
            body = pre.strip()
            if body and not body.startswith("@") and "," in body and "/*" not in body:
                keys = [re.sub(r"\s+", " ", it.strip()) for it in split_top(body)]
                if len(set(keys)) == len(keys):
                    continue  # nothing repeated: leave as written
                lead = pre[: len(pre) - len(pre.lstrip())]
                items, seen = [], set()
                for it in split_top(body):
                    key = re.sub(r"\s+", " ", it.strip())
                    if key and key not in seen:
                        seen.add(key)
                        items.append(it.strip())
                res.append(css[last:start] + lead + " ,".join(items) + " ")
                last = m.start()
    res.append(css[last:])
    return "".join(res)


changed = 0
for top in ("themes", "plugins"):
    for dp, _, fs in os.walk(os.path.join(ROOT, top)):
        if "node_modules" in dp or "/vendor" in dp:
            continue
        for f in fs:
            if not f.endswith((".php", ".html", ".js", ".css", ".json")):
                continue
            p = os.path.join(dp, f)
            s = open(p, encoding="utf-8").read()
            n = dedupe_classes(rename(s))
            if f.endswith(".css"):
                n = dedupe_selectors(n)
            if n != s:
                open(p, "w", encoding="utf-8").write(n)
                changed += 1
                print("changed", os.path.relpath(p, ROOT))
print(changed, "files")
