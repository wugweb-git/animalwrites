#!/usr/bin/env python3
"""Organisations from three older lists the owner sent (18 Oct 2026), read from Scribd, mapped against the directory.

- scribd-all-animal-ngos-india.txt: "All Animal NGOs India" (Child of Nature, 2006).
- scribd-vet-hospitals.txt: "Veterinary Hospitals of India" (radhee.com, about 2013): hospitals run by NGOs/trusts.
- scribd-people-for-animals.txt: People For Animals' "Other NGOs / Individuals" page (28 Aug 2019); individuals skipped.
A row counts as listed when an existing listing has an alike name (>= 0.9) in the same state; "check" when the name is
alike but the state differs or is unknown, or the name is only fairly alike; else new. Read-only.
Output: data/sources/web2/web2-mapped.csv
Usage: python3 map_web2.py data/listings.json
"""
import csv
import json
import os
import re
import sys

from difflib import SequenceMatcher

from match import clean_title, fold

# Words every animal NGO name shares: they never make two names the same organisation.
GENERIC = {"the", "of", "and", "for", "a", "an", "in", "at", "to", "india", "indian", "animal", "animals", "welfare", "care",
           "rescue", "people", "foundation", "society", "trust", "charitable", "centre", "center", "home", "shelter",
           "protection", "prevention", "cruelty", "organisation", "organization", "association", "group", "club", "sangh",
           "seva", "sewa", "samiti", "sanstha", "sansthan", "gau", "gou", "pashu", "jeev", "jiv", "regd", "registered",
           "ngo", "spca", "s", "p", "c", "lovers", "friends", "network", "mission", "kendra", "shri", "shree", "sri"}


def name_sim(a, b):
    """0..1 like match.name_similarity, but names must share a word that is not generic animal-NGO vocabulary."""
    ta = [t for t in fold(a).split() if t not in GENERIC and len(t) > 2]
    tb = [t for t in fold(b).split() if t not in GENERIC and len(t) > 2]
    full = SequenceMatcher(None, " ".join(fold(a).split()), " ".join(fold(b).split())).ratio()
    if not ta or not tb:
        return round(full if not ta and not tb else full * 0.8, 3)
    common = len(set(ta) & set(tb))
    if not common:
        return round(min(full, 0.7), 3)
    overlap = common / min(len(set(ta)), len(set(tb)))
    return round(max(full, overlap * (0.95 if common >= 2 or min(len(ta), len(tb)) == 1 else 0.75)), 3)
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
W = os.path.join(HERE, "data", "sources", "web2")
ORG = re.compile(r"\b(society|trust|foundation|sangh|sangham|samiti|sanstha|sansthan|spca|s\.p\.c\.a|pfa|people for animals|"
                 r"welfare|rescue|shelter|care|centre|center|hospital|clinic|association|mission|blue cross|gaushala|goshala|"
                 r"pinjrapole|panjrapole|panjarapole|jivdaya|jeevdaya|ashram|home|network|league|club|group|organi[sz]ation|"
                 r"ngo|fund|federation|council|board|kendra|sabha|mandal|parishad|animals?)\b", re.I)
SKIP = re.compile(r"^(hon\.|fields of activity|activities|phone|tel|fax|email|e-mail|website|webpage|contact|mob|ph\b|"
                  r"hospital:|relief centre|shelter:|\(page|next|this is|www\.|http)", re.I)


def blocks(path):
    out, cur = [], []
    for l in open(path, encoding="utf-8").read().splitlines():
        if not l.strip():
            if cur:
                out.append(cur)
            cur = []
        else:
            cur.append(l.strip())
    if cur:
        out.append(cur)
    return out


def from_blocks(path, source, places):
    rows, state = [], ""
    for b in blocks(path):
        lines = list(b)
        if len(lines) == 1 and places.state(lines[0]) and not re.search(r"\d", lines[0]):
            state = places.state(lines[0])["name"]
            continue
        city = ""
        while lines and not re.search(r"[a-z]", lines[0]) and not ORG.search(lines[0]):
            city = lines.pop(0)  # city headings in capitals
        if not lines or SKIP.match(lines[0]) or re.search(r"\d{3}", lines[0]):
            continue
        name = lines[0].strip(" ,:")
        text = " ".join(b)
        st = None
        for m in re.finditer(r",\s*([A-Za-z &]+?),?\s*India", text):
            st = places.state(m.group(1).strip()) or st
        if not st and city:
            d = places.district_anywhere(city.title())
            st = places.by_code.get(d["state"]) if d else None
        if not st and re.search(r"delhi", text, re.I):
            st = places.state("Delhi")
        st_name = st["name"] if st else state
        if len(name) < 4 or not ORG.search(name):
            continue
        rows.append({"source": source, "name": name, "state": st_name, "detail": " ".join(b[1:3])[:140]})
    return rows


def pfa_others(path, places):
    rows = []
    for l in open(path, encoding="utf-8").read().splitlines():
        m = re.match(r"^\s*([A-Z0-9][A-Z0-9 &.'’()\-/]{3,80}),\s*([A-Z][A-Z .\-/()]{2,40}):\s*$", l)
        if not m:
            continue
        name, city = m.group(1).strip(), m.group(2).strip()
        d = places.district_anywhere(city.title())
        st = places.by_code.get(d["state"]) if d else None
        rows.append({"source": "PFA other NGOs (2019)", "name": name.title(), "state": st["name"] if st else "",
                     "detail": city.title(), "individual": not ORG.search(name)})
    return rows


def main(listings_file):
    places = Places()
    st_name = {s["code"]: s["name"] for s in places.states.values()}
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    rows = (from_blocks(os.path.join(W, "scribd-all-animal-ngos-india.txt"), "Child of Nature list (2006)", places)
            + from_blocks(os.path.join(W, "scribd-vet-hospitals.txt"), "Veterinary Hospitals of India (c. 2013)", places)
            + pfa_others(os.path.join(W, "scribd-people-for-animals.txt"), places))
    index = {}  # distinctive word -> listings whose title has it (only those can be the same organisation)
    for l in L:
        for t in {t for t in fold(clean_title(l["title"])).split() if t not in GENERIC and len(t) > 2}:
            index.setdefault(t, []).append(l)
    out, counts = [], {}
    for r in rows:
        if r.get("individual"):
            kind, best, score = "individual (skipped)", None, 0
        else:
            toks = {t for t in fold(r["name"]).split() if t not in GENERIC and len(t) > 2}
            pool = {l["id"]: l for t in toks for l in index.get(t, [])}.values() if toks else L
            cands = sorted(((name_sim(r["name"], clean_title(l["title"])), l) for l in pool), key=lambda x: -x[0])[:5]
            same = [(s, l) for s, l in cands if r["state"] and r["state"] in [st_name.get(c) for c in l["states"]]]
            if same and same[0][0] >= 0.9:
                kind, (score, best) = "listed", same[0]
            elif cands and cands[0][0] >= 0.75:
                kind, (score, best) = "check", (same[0] if same else cands[0])
            else:
                kind, score, best = "new", (cands[0][0] if cands else 0), None
        counts[(r["source"], kind)] = counts.get((r["source"], kind), 0) + 1
        out.append({"source": r["source"], "name": r["name"], "state": r["state"], "detail": r["detail"], "match": kind,
                    "score": round(score, 3), "listing_id": best["id"] if best else "",
                    "listing_title": best["title"] if best else "",
                    "listing_state": ", ".join(st_name.get(c, "") for c in best["states"]) if best else ""})
    with open(os.path.join(W, "web2-mapped.csv"), "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=list(out[0]))
        w.writeheader()
        w.writerows(out)
    for (s, k), n in sorted(counts.items()):
        print(f"{s:40} {k:22} {n}")


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
