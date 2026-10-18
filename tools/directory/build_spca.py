#!/usr/bin/env python3
"""District SPCAs (Societies for Prevention of Cruelty to Animals, PCA Act 1960 / SPCA Rules 2001), for import_listings.php.

Official lists only (saved in data/sources/spca/, 18 Oct 2026):
- AWBI, "SPCA in the States & UTs in India" (PDF, Sep 2022): state, district, notification. Districts marked
  "No G.O. received" have no notified SPCA and are skipped. No contact details.
- Haryana Animal Husbandry Department, list of SPCAs (Wayback copy of Oct 2023): name, registration no., year.
An SPCA already listed in the same district (title has SPCA or "Prevention of Cruelty") gets Rescue Group and, from the
Haryana list, its registration number; others become new listings (Animal Welfare Organisation + Rescue Group).
Usage: python3 build_spca.py data/listings.json
"""
import json
import os
import re
import sys

from htmltables import tables
from places import Places
from report import Report

HERE = os.path.dirname(os.path.abspath(__file__))
AWBI_TXT = os.path.join(HERE, "data", "sources", "spca", "awbi", "SPCA-in-States-UTs.txt")
AWBI_URL = "https://awbi.gov.in/view/index/society-for-prevention-of-cruelty-to-animals"
HR_HTML = os.path.join(HERE, "data", "sources", "spca", "haryana", "spcas.wayback-20231029.html")
HR_URL = "https://pashudhanharyana.gov.in/activities/animal-welfare/spcas"
SPCA = re.compile(r"\bs\.?\s?p\.?\s?c\.?\s?a\b|prevention of cruelty", re.I)
NONE = re.compile(r"no\s+g\.?\s?o|not\s+received|no\s+notification", re.I)
ALIAS = {"Ramanagara": "Bengaluru South", "Lakhimpur-Kheri": "Kheri", "Tehri": "Tehri Garhwal", "East Sikkim": "Gangtok",
         "North Sikkim": "Mangan", "South Sikkim": "Namchi", "West Sikkim": "Gyalshing", "West Champaran": "Pashchim Champaran",
         "Aurangabad": "Aurangabad", "Narsinghpur": "Narsimhapur", "North Middle Andaman": "North And Middle Andaman"}
TYPES = ["animal-welfare-organisation", "rescue-group"]


def awbi_rows(places):
    """(state, district, notification) from the PDF text (pdftotext -layout). Cells are split on runs of spaces; a
    row's notification may sit on the line before or after it (wrapped cells)."""
    out, lines = [], open(AWBI_TXT, encoding="utf-8").read().splitlines()
    cells = [[c for c in re.split(r"\s{2,}", l.strip()) if c and not re.fullmatch(r"\d{1,2}", c)] for l in lines]
    for i, cs in enumerate(cells):
        if len(cs) < 2:
            continue
        st = places.state(cs[0])
        if not st:
            continue
        dname = cs[1]
        notif = " ".join(cs[2:])
        if not notif:
            for j in (i + 1, i - 1):
                if 0 <= j < len(cells) and len(cells[j]) == 1 and not places.state(cells[j][0]):
                    notif = cells[j][0]
                    break
        out.append((st["name"], dname, notif))
    return out


def main(listings_file):
    rep = Report("spca", "District SPCAs: AWBI list (Sep 2022) and Haryana Animal Husbandry Department", AWBI_URL)
    places = Places()
    listings = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    spcas = {}
    for l in listings:
        if SPCA.search(l["title"]):
            for d in l.get("districts") or []:
                spcas.setdefault(d, []).append(l)
    hr_reg = {}
    for r in tables(HR_HTML)[0][1:]:
        m = re.search(r"S\.?P\.?C\.?A\.?\s*,\s*(.+)$", r[1])
        if m:
            hr_reg[m.group(1).strip().lower()] = (r[2].strip(), r[3].strip())
    seen = set()
    for state_name, dname, notif in awbi_rows(places):
        rep.found += 1
        sample = {"state": state_name, "district": dname, "notification": notif}
        if not notif or NONE.search(notif):
            rep.skip("no SPCA notified")
            continue
        st = places.state(state_name)
        dist = places.district(st, ALIAS.get(dname, dname)) if st else None
        if not dist:
            rep.skip("district not in LGD", sample)
            continue
        if dist["code"] in seen:
            continue
        seen.add(dist["code"])
        reg = hr_reg.get(dname.lower()) if st["name"] == "Haryana" else None
        src = {"source_name": "AWBI: SPCA in the States & UTs in India", "source_url": AWBI_URL, "source_date": "2022-09-01"}
        extra = {"registration": f"{reg[0]} ({reg[1]})"} if reg else {}
        # The district's own SPCA: "District SPCA", or SPCA with the district's name (not any NGO called SPCA).
        dn = [w for w in re.findall(r"[a-z]{4,}", (dname + " " + dist["name"]).lower())]
        have = [l for l in spcas.get(dist["code"], []) if re.search(r"district|zila|jila", l["title"], re.I)
                or any(w in l["title"].lower() for w in dn)]
        if have:
            l = have[0]
            add = [t for t in ["rescue-group"] if t not in l["types"]]
            if l.get("protected") or (not add and not extra):
                rep.skip("already listed with the type" if not l.get("protected") else "verified or claimed")
                continue
            rep.match(dict(extra, post_id=l["id"], types=add),
                      dict(sample, listing=l["id"], listing_title=l["title"], adds=add, **extra))
            continue
        name = f"District SPCA, {dist['name']}"
        rep.new(dict(src, **extra, key=f"spca|{st['slug']}|{dist['code']}", name=name, types=TYPES, state=st["code"],
                     district=dist["code"], area=f"{dist['name']}, {st['name']}", phone="", address_private="",
                     content=(f"The Society for Prevention of Cruelty to Animals (SPCA) for {dist['name']} district, set up "
                              f"under the Prevention of Cruelty to Animals Act, 1960 and the SPCA Rules, 2001 "
                              f"({notif.rstrip('.')}). Under the Rules the district SPCA is chaired by the "
                              f"District Magistrate or Collector. From the Animal Welfare Board of "
                              f"India's list of SPCAs in the states (Sep 2022).")),
                dict(sample, **extra))
    rep.write()


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
