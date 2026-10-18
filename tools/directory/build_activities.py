#!/usr/bin/env python3
"""What organisations do, from the older lists' "Activities" (Veterinary Hospitals of India) and "Fields of activity"
(All Animal NGOs India, 2006) -> types and services on their listings (owner, 18 Oct 2026). Adds only: nothing is
removed, no limit on types. Listings: the organisations matched to these lists with a near-identical name in the same
state (map_web2.py, build_web2.py) and the ones added from them.
Usage: python3 build_activities.py data/listings.json > data/out/activities.rows.json
"""
import csv
import json
import os
import re
import sys
from difflib import SequenceMatcher

from build_web2 import MAP_SOURCE, NEW, block
from match import clean_title, fold

HERE = os.path.dirname(os.path.abspath(__file__))
TYPES = [
    ("animal-shelter", r"\bno-kill shelter\b|\banimal shelter\b|(?<!bird )\bshelter\b(?! for birds)|\bshelters\b|\brecovery home\b"),
    ("sanctuary", r"\bsanctuary\b"),
    ("animal-ambulance", r"\bambulance\b"),
    ("rescue-group", r"(?<!wildlife )\brescue\b"),
    ("wildlife-rescue", r"\bwildlife rescue\b|\bwildlife rehabilitation\b"),
    ("veterinary-hospital", r"\bveterinary hospital\b|\banimal hospital\b"),
    ("bird-hospital", r"\bbird hospital\b|\bbirds? treatment\b"),
]
SERVICES = [
    ("rescue", r"\brescue\b"),
    ("treatment", r"\bhospital\b|\btreatment\b|\bmedical\b|\bclinic\b|\bdispensary\b|\bfirst aid\b"),
    ("shelter", r"\bshelter\b|\brecovery home\b"),
    ("adoption", r"\badoption\b"),
    ("feeding", r"\bfeeding\b|\bfood\b|\bwater troughs?\b"),
    ("sterilisation", r"\bbirth control\b|\bsteril[iz]ation\b|\babc\b|\bspay\b|\bneuter"),
    ("vaccination", r"\bvaccination\b"),
    ("ambulance", r"\bambulance\b"),
    ("boarding-foster", r"\bfoster\b|\bboarding\b"),
    ("legal-help", r"\bcruelty cases\b|\blegal\b"),
    ("cattle-care", r"\bcows?\b|\bcattle\b|\bgaushala\b|\bgoshala\b|\bcow dispensary\b|\bcow sanctuary\b"),
]


def activities(src, key):
    t = block(src, key)
    m = re.search(r"(?:Activities|Fields of activity)\s*:\s*(.+)", t, re.I)
    if m:
        return m.group(1)
    if src == "con":  # the 2006 list sometimes gives the fields without the label, as a "; " list at the end
        tail = re.findall(r"((?:[A-Z][A-Za-z -]+;\s*){2,}[A-Z][A-Za-z -]+)", t)
        return tail[-1] if tail else ""
    return ""


def pick(text, rules):
    return [slug for slug, rx in rules if re.search(rx, text, re.I)]


def main(listings_file):
    L = {l["id"]: l for l in json.load(open(listings_file, encoding="utf-8"))}
    by_key = {l["import_key"]: l for l in L.values() if l.get("import_key")}
    targets = []  # (listing, source, key text)
    for src, key, name, *_ in NEW:
        k = "web2|" + re.sub(r"\W+", "-", name.lower()).strip("-")
        if k in by_key:
            targets.append((by_key[k], src, key))
    for r in csv.DictReader(open(os.path.join(HERE, "data", "sources", "web2", "web2-mapped.csv"), encoding="utf-8")):
        if r["match"] != "listed" or not r["listing_id"] or MAP_SOURCE[r["source"]] == "pfa":
            continue
        a, b = " ".join(fold(r["name"]).split()), " ".join(fold(clean_title(r["listing_title"])).split())
        if SequenceMatcher(None, a, b).ratio() >= 0.82:
            targets.append((L[int(r["listing_id"])], MAP_SOURCE[r["source"]], r["name"]))
    rows, seen = [], {}
    for l, src, key in targets:
        text = activities(src, key)
        if not text:
            continue
        types = [t for t in pick(text, TYPES) if t not in l["types"]]
        services = [s for s in pick(text, SERVICES) if s not in l["types"]]
        if l.get("protected") or not (types or services):
            continue
        row = seen.setdefault(l["id"], {"post_id": l["id"], "types": [], "services": []})
        row["types"] = sorted(set(row["types"]) | set(types))
        row["services"] = sorted(set(row["services"]) | set(services))
        print(f"{l['title'][:45]:45} | {', '.join(row['types'])} | {', '.join(row['services'])} || {text[:80]}", file=sys.stderr)
    json.dump(list(seen.values()), sys.stdout, ensure_ascii=False, indent=1)
    print(f"{len(seen)} listings", file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
