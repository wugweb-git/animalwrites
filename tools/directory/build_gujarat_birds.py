#!/usr/bin/env python3
"""Gujarat Forest Department, Karuna Abhiyan -> permanent bird and wildlife treatment facilities, for import_listings.php.

forests.gujarat.gov.in/save-birds.htm links the campaign's centre map; its markers come from the public Google Sheet
"Karuna Abhiyan 2026" (tab "Center-list", gid 1775582006), saved in data/sources/gujarat-karuna/ (18 Oct 2026).
The sheet lists the 10–20 January campaign's control rooms, collection points and treatment centres, and does not say
which are open all year. Only facilities that are permanent by what they are (a Forest Department wildlife care or
rehabilitation centre, a named bird hospital, a veterinary hospital or dispensary run by an NGO) are taken; they are
picked by hand below and checked against the sheet's own text, so a changed sheet stops the build.
Contact people and their mobiles are not stored (personal numbers).
The Animal Husbandry Department's treatment centres are its veterinary dispensaries (309 rows, names in Gujarati); they
are counted in the report and not imported here.
Usage: python3 build_gujarat_birds.py data/listings.json
"""
import csv
import json
import os
import re
import sys

from match import Matcher
from places import Places
from report import Report

HERE = os.path.dirname(os.path.abspath(__file__))
SHEET = os.path.join(HERE, "data", "sources", "gujarat-karuna", "karuna-sheet-centerlist-gid1775582006.csv")
URL = "https://forests.gujarat.gov.in/save-birds.htm"
ON = "2026-01-10"  # Karuna Abhiyan 2026 sheet
TYPE, NAME = "કેન્દ્ર નું પ્રકાર", "નામ અને સરનામું"
# Sheet row -> (text the row must contain, English name, district, types, existing listing ID or 0, what it is)
PICKED = {
    1405: ("બર્ડ હોસ્પિટલ", "Beauty Without Brutality Bird Hospital, Pal", "Surat", ["bird-hospital"], 630,
           "the bird hospital of Beauty Without Brutality at Pal, Surat"),
    1411: ("નંદિની વેટેરીનરી હોસ્પિટલ", "Nandini Veterinary Hospital, Shri Surat Panjrapole", "Surat", ["veterinary-hospital", "bird-hospital"], 525,
           "the veterinary hospital at Shri Surat Panjrapole, Ghod Dod Road"),
    896: ("સુપાર્શ્વ જૈન", "Suparshva Jain Sanstha Veterinary Dispensary, Bhuj", "Kachchh", ["veterinary-dispensary"], 0,
          "a veterinary dispensary run by Suparshva Jain Sanstha in Bhuj"),
    132: ("વાઈલ્ડ લાઈફ કેર સેન્ટર", "Wildlife Care Centre, Bodakdev", "Ahmedabad", ["wildlife-rescue"], 0,
          "the Gujarat Forest Department's wildlife care centre at Bodakdev, Ahmedabad"),
    146: ("પુન:વસન કેન્દ્ર", "Wildlife Rehabilitation Centre, Bilasiya", "Ahmedabad", ["wildlife-rescue"], 0,
          "the Gujarat Forest Department's wildlife rehabilitation centre at Bilasiya"),
    810: ("એનીમલ કેર સેન્ટર", "Animal Care Centre, Amrapur", "Junagadh", ["wildlife-rescue"], 0,
          "the Gujarat Forest Department's animal care centre at Amrapur"),
}


def main(listings_file):
    rep = Report("gujforest", "Gujarat Forest Department, Karuna Abhiyan 2026 centre list", URL)
    rows = list(csv.DictReader(open(SHEET, encoding="utf-8")))
    treat = [r for r in rows if "સારવાર" in r[TYPE]]
    rep.found = len(treat)
    kinds = {"Animal Husbandry": "husbandary", "Forest": "Forest", "NGO / Private": "NGO"}
    for label, k in kinds.items():
        n = sum(1 for r in treat if k in r[TYPE])
        rep.notes.append(f"treatment centres ({label}): {n}")
    rep.notes.append("collection points and control rooms (campaign only): "
                     f"{sum(1 for r in rows if r[TYPE] and 'સારવાર' not in r[TYPE])}")
    places, matcher = Places(), Matcher(listings_file)
    gj = places.state("Gujarat")
    by_id = {l["id"]: l for l in json.load(open(listings_file, encoding="utf-8"))}
    picked = set()
    for idx, (must, name, district, types, lid, what) in PICKED.items():
        r = rows[idx]
        if must not in r[NAME]:
            raise SystemExit(f"sheet row {idx} changed: {r[NAME][:80]!r}")
        picked.add(idx)
        dist = places.district(gj, district)
        src = {"data_source": "gujforest", "source_name": "Gujarat Forest Department (Karuna Abhiyan 2026)",
               "source_url": URL, "source_date": ON}
        if lid:
            l = by_id[lid]
            rep.match(dict(src, post_id=lid, types=types),
                      {"sheet": re.sub(r"\s+", " ", r[NAME]), "listing": lid, "listing_title": l["title"], "adds": types})
            continue
        kind, hit, score = matcher.find(name, gj["code"], dist["code"])
        if kind != "new":
            rep.maybe({"name": name, "listing": hit["id"], "listing_title": hit["title"], "score": score})
            continue
        rep.new(dict(src, key="gujforest|" + re.sub(r"\W+", "-", name.lower()).strip("-"), name=name, types=types,
                     state=gj["code"], district=dist["code"], area=f"{dist['name']}, Gujarat", phone="",
                     address_private=re.sub(r"\s+", " ", r[NAME]),
                     content=(f"{name} is {what}. It is listed as a treatment centre for injured birds in the "
                              f"Gujarat Forest Department's Karuna Abhiyan (10–20 Jan 2026).")),
                {"sheet": re.sub(r"\s+", " ", r[NAME]), "name": name, "types": types})
    for i, r in enumerate(rows):
        if i in picked or "સારવાર" not in r[TYPE]:
            continue
        reason = ("Animal Husbandry veterinary dispensary (owner decides)" if "husbandary" in r[TYPE]
                  else "campaign treatment point, not shown to be permanent")
        rep.skip(reason, {"district": r["District"], "sheet": re.sub(r"\s+", " ", r[NAME])[:120]} if "husbandary" not in r[TYPE] else None)
    rep.write()


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
