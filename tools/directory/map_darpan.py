#!/usr/bin/env python3
"""Compile the NGO Darpan "Animal Welfare" sector search batches and map them against existing listings. Read-only.

Batches come from the owner's CAPTCHA'd search on ngodarpan.gov.in (Search NPO, Sector = Animal Welfare, all states),
read page by page in the browser and saved as data/darpan/darpan-animal-welfare-batch-NNN.json. Rows are
[name, "registration no\\nDISTRICT (State)", address, sector ids] (batch 001) or [serial, name, ...] (later batches).
Output: data/darpan/darpan-mapped.csv and a summary on stdout.
Usage: python3 map_darpan.py data/listings.json
"""
import csv
import glob
import json
import os
import re
import sys

from match import Matcher
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
D = os.path.join(HERE, "data", "darpan")
ANIMAL = re.compile(r"\b(animals?|pashu\w*|jee?v\w*|jiv\w*|gau\w*|gou\w*|go\s?(seva|sewa|shala|raksha)\w*|gowshala|goshala|panjrapo\w*|"
                    r"pinjrapo\w*|birds?|pakshi|dogs?|cats?|s\.?p\.?c\.?a|p\.?f\.?a|karuna|wildlife|veterinary|vet|rescue|shelter|"
                    r"sanctuary|kamdhenu|nandi|cattle|cows?)\b", re.I)


def main(listings_file):
    places, matcher = Places(), Matcher(listings_file)
    rows, sectors = {}, None
    for f in sorted(glob.glob(os.path.join(D, "darpan-animal-welfare-batch-*.json"))):
        b = json.load(open(f, encoding="utf-8"))
        sectors = b["sectors"]
        for r in b["rows"]:
            if len(r) == 5:
                r = r[1:]
            name, regdist, address, secs = r
            reg, _, dist = regdist.partition("\n")
            m = re.match(r"(.*)\((.*)\)\s*$", dist.strip())
            dname, sname = (m.group(1).strip(), m.group(2).strip()) if m else (dist.strip(), "")
            rows[(name.strip().lower(), reg.strip())] = dict(name=re.sub(r"\s+", " ", name).strip(), registration=reg.strip(),
                                                             district=dname, state=sname, address=address,
                                                             sectors=[sectors[i] for i in secs])
    out, counts = [], {"total": 0, "match": 0, "unsure": 0, "new": 0, "no place": 0, "animal name": 0}
    for r in rows.values():
        counts["total"] += 1
        st = places.state(r["state"]) if r["state"] else None
        dist = places.district(st, r["district"].title()) if st else None
        animal = bool(ANIMAL.search(r["name"]))
        counts["animal name"] += animal
        if not st:
            kind, hit, score = "no place", None, 0
        else:
            kind, hit, score = matcher.find(r["name"].title() if r["name"].isupper() else r["name"], st["code"],
                                            dist["code"] if dist else 0, registration=r["registration"])
        counts[kind] += 1
        out.append({"name": r["name"], "registration": r["registration"], "district": r["district"], "state": r["state"],
                    "sectors_count": len(r["sectors"]), "only_animal_welfare": r["sectors"] == ["Animal Welfare"],
                    "animal_name": animal, "match": kind, "listing_id": hit["id"] if hit else "",
                    "listing_title": hit["title"] if hit else "", "score": score, "address": r["address"],
                    "sectors": ", ".join(r["sectors"])})
    out.sort(key=lambda x: (x["match"] != "match", not x["animal_name"], x["name"].lower()))
    with open(os.path.join(D, "darpan-mapped.csv"), "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=list(out[0]))
        w.writeheader()
        w.writerows(out)
    print(json.dumps(counts))
    for x in out:
        if x["match"] in ("match", "unsure"):
            print(x["match"], "|", x["name"], "|", x["district"], "->", x["listing_title"], x["score"])


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
