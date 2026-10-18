#!/usr/bin/env python3
"""AWBI Ambulance Grant Scheme release lists -> Animal Ambulance type on the organisations that received one.

Lists (AWBI, "Grant-in-aid released ... under the Ambulance Grant Scheme", saved in data/sources/awbi-ambulance/):
2015-16, 2018-19, 2019-20, 2020-21, 2022-23, 2025-26. A grant is given to
buy an animal ambulance, so the organisation runs one. Matched by AWBI code only; organisations not listed yet are
reported, not created (the list gives no contact details).
Usage: python3 build_ambulance.py data/listings.json
"""
import glob
import json
import os
import re
import sys

from report import Report

HERE = os.path.dirname(os.path.abspath(__file__))
CODE = re.compile(r"\b([A-Z]{2,3}\s?\d{1,4}/\s?\d{1,4})\b")
TYPES = {"abc-centre", "adoption-network", "animal-ambulance", "animal-shelter", "animal-welfare-organisation",
         "bird-hospital", "boarding-and-foster", "emergency-rescue", "feeder-network", "gaushala", "legal-aid",
         "mobile-veterinary-unit", "rescue-group", "sanctuary", "veterinary-clinic", "veterinary-dispensary",
         "veterinary-hospital", "veterinary-office", "wildlife-rescue"}


def main(listings_file):
    rep = Report("ambulance", "AWBI Ambulance Grant Scheme release lists", "https://awbi.gov.in/")
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    by_code = {l["awbi_code"].replace(" ", "").upper(): l for l in L if l.get("awbi_code")}
    seen = {}
    for f in sorted(glob.glob(os.path.join(HERE, "data", "sources", "awbi-ambulance", "amb-*.txt"))):
        year = re.search(r"amb-(\d{4}-\d{2})", f).group(1)
        lines = open(f, encoding="utf-8").read().splitlines()
        for i, line in enumerate(lines):
            for m in CODE.finditer(line):
                code = m.group(1).replace(" ", "").upper()
                yr = code.split("/")[1]
                if len(yr) < 4 and i + 1 < len(lines):  # 2018-19: the code's year wraps to the next line ("RJ597/20" + "09")
                    near = [t for t in re.finditer(r"\b\d{1,3}\b", lines[i + 1])
                            if len(yr + t.group()) == 4 and abs(t.start() - m.start()) <= 6]
                    if near:
                        code += min(near, key=lambda t: abs(t.start() - m.start())).group()
                seen.setdefault(code, []).append(year)
    for code, years in sorted(seen.items()):
        rep.found += 1
        l = by_code.get(code)
        sample = {"awbi_code": code, "grant_years": ", ".join(sorted(set(years)))}
        if not l:
            rep.skip("organisation not listed", sample)
            continue
        have = [t for t in l["types"] if t in TYPES]
        if "animal-ambulance" in have:
            rep.skip("already has the type")
        elif l.get("protected"):
            rep.skip("verified or claimed", dict(sample, listing=l["title"]))
        elif len(have) >= 3:
            rep.skip("already 3 types", dict(sample, listing=l["title"], types=have))
        else:
            rep.match({"post_id": l["id"], "types": ["animal-ambulance"]},
                      dict(sample, listing=l["id"], listing_title=l["title"], types=have, adds=["animal-ambulance"]))
    rep.write(n=60)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
