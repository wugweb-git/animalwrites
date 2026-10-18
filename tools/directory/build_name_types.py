#!/usr/bin/env python3
"""Types for existing listings from their NAME only (owner, 18 Oct 2026; no website crawling).

    shelter / home / ashram (for animals)           -> Animal Shelter
    rescue                                          -> Rescue Group
    SPCA                                            -> Rescue Group (added; Animal Welfare Organisation stays)
    bird hospital / pakshi chikitsalaya / charity birds -> Bird Hospital
    ambulance                                       -> Animal Ambulance

Whole words only ("Homoeopathy" is not "home"). Types are added, never removed; no limit on types (owner, 18 Oct 2026); verified or claimed listings are left alone.
Usage: python3 build_name_types.py data/listings.json
"""
import re
import sys

from report import Report

ANIMAL = r"\b(animals?|gau|gou|go|cows?|pashu|jeev|jiv|jeeva|jeevdaya|jivdaya|dogs?|cats?|birds?|pakshi|kamdhenu|gaushala|goshala|gowshala|gaushalla|gosewa|goseva|gauseva|govansh|gorakshan|praniseva|jantu|surabhi|nandi)\b"
RULES = [
    ("animal-shelter", re.compile(r"\b(shelters?|homes?)\b", re.I)),
    ("animal-shelter", re.compile(r"\b(ashrams?|aashrams?)\b", re.I), re.compile(ANIMAL, re.I)),  # "ashram for animals" only
    ("rescue-group", re.compile(r"\b(rescues?|rescuers?|s\.?\s?p\.?\s?c\.?\s?a\.?)\b", re.I)),
    ("bird-hospital", re.compile(r"\b(birds?\s+hospital|pakshi\s+chikitsalaya|charity\s+birds?)\b", re.I)),
    ("animal-ambulance", re.compile(r"\bambulances?\b", re.I)),
]
TYPES = {"abc-centre", "adoption-network", "animal-ambulance", "animal-shelter", "animal-welfare-organisation",
         "bird-hospital", "boarding-and-foster", "emergency-rescue", "feeder-network", "gaushala", "legal-aid",
         "mobile-veterinary-unit", "rescue-group", "sanctuary", "veterinary-clinic", "veterinary-dispensary",
         "veterinary-hospital", "veterinary-office", "wildlife-rescue"}


def main(listings_file):
    import json
    rep = Report("name-types", "Existing listings: types from the name")
    for l in json.load(open(listings_file, encoding="utf-8")):
        if l.get("merged_into") or l["status"] != "publish":
            continue
        rep.found += 1
        title = l["title"]
        want = []
        for t, rx, *need in RULES:
            if rx.search(title) and all(n.search(title) for n in need) and t not in want:
                want.append(t)
        have = [t for t in l["types"] if t in TYPES]
        add = [t for t in want if t not in have]
        if not want:
            continue  # nothing in the name
        if not add:
            rep.skip("already has the type")
            continue
        if l.get("protected"):
            rep.skip("verified or claimed", {"id": l["id"], "title": title, "would_add": add})
            continue
        rep.match({"post_id": l["id"], "types": add}, {"id": l["id"], "title": title, "types": have, "adds": add})
    rep.write(n=40)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else "data/listings.json")
