"""Kits registry check (deploy gate): every kit-* / aw-* class used in markup or stylesheets, and every animal-writes/*
block, must have an entry in a kits-registry.json (Kits master or the site's child theme). A registered class or
block that is used nowhere is reported as a warning only.

    python3 tools/ci/registry_check.py        # exit 1 when something is missing
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
REGS = ["themes/kits-by-wugweb/kits-registry.json", "themes/animal-writes/kits-registry.json"]

prefixes, blocks = [], set()
for r in REGS:
    for it in json.load(open(os.path.join(ROOT, r)))["items"]:
        prefixes += it.get("classes", [])
        if it.get("block"):
            blocks.add(it["block"])

used = {}
for top in ("themes", "plugins"):
    for dp, _, fs in os.walk(os.path.join(ROOT, top)):
        for f in fs:
            ext = f.rsplit(".", 1)[-1]
            if ext not in ("php", "html", "css", "js"):
                continue
            p = os.path.join(dp, f)
            s = open(p, encoding="utf-8", errors="ignore").read()
            found = set()
            if ext == "css":
                found |= set(re.findall(r"\.((?:aw|kit)-[a-z0-9-]+)", s))
            else:
                for m in re.finditer(r"class(?:Name)?\s*[=:]\s*\\?[\"']([^\"']*)", s):
                    found |= {c for c in m.group(1).split() if re.fullmatch(r"(aw|kit)-[a-z0-9-]+", c)}
                for m in re.finditer(r'"className":"([^"]*)"', s):
                    found |= {c for c in m.group(1).split() if re.fullmatch(r"(aw|kit)-[a-z0-9-]+", c)}
            for c in found:
                used.setdefault(c.split("--")[0].rstrip("-"), set()).add(os.path.relpath(p, ROOT))


def covered(c):
    return any(c == p or c.startswith(p + "-") for p in prefixes)


missing = sorted(c for c in used if not covered(c))
# Registered blocks: the names in aw_hub_blocks() (animal-writes-core) and the theme's patterns.
hb = open(os.path.join(ROOT, "plugins/animal-writes-core/includes/hub-blocks.php")).read()
fn = hb[hb.index("function aw_hub_blocks"):hb.index("add_action", hb.index("function aw_hub_blocks"))]
site_blocks = {"animal-writes/" + n for n in re.findall(r"^\s*'([a-z-]+)'\s*=>\s*array\(", fn, re.M)}
site_blocks |= {"animal-writes/" + f[:-4] for f in os.listdir(os.path.join(ROOT, "themes/animal-writes/patterns")) if f.endswith(".php")}
missing_blocks = sorted(site_blocks - blocks)
unused = sorted(p for p in prefixes if not any(c == p or c.startswith(p + "-") for c in used))

print(f"registry: {len(prefixes)} class prefixes, {len(blocks)} blocks and patterns; site uses {len(used)} classes, {len(site_blocks)} blocks and patterns")
if unused:
    print("warning, registered but unused:", ", ".join(unused))
if missing or missing_blocks:
    for c in missing:
        print("MISSING class", c, "used in", ", ".join(sorted(used[c])[:3]))
    for b in missing_blocks:
        print("MISSING block", b)
    sys.exit(1)
print("registry check passed")
