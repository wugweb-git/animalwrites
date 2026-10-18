#!/usr/bin/env python3
"""Three older lists read from Scribd (map_web2.py) -> new listings for organisations not in the directory (picked by
hand, 18 Oct 2026) and contact details for organisations already listed.

Lists: "All Animal NGOs India" (Child of Nature, 2006), "Veterinary Hospitals of India" (radhee.com, c. 2013),
People For Animals' "Other NGOs / Individuals" page (28 Aug 2019).
Owner, 18 Oct 2026: enrich only organisations already present; the newer source wins. These lists are older than the
directory's data, so they only fill empty fields (import_listings.php never overwrites). Phone numbers from the 2006
list are not used (numbering changed since); contact persons' names are never stored.
Usage: python3 build_web2.py data/listings.json > data/out/web2.rows.json
"""
import csv
import json
import os
import re
import sys

from difflib import SequenceMatcher

from match import clean_title, fold
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
W = os.path.join(HERE, "data", "sources", "web2")
SRC = {
    "con": ("scribd-all-animal-ngos-india.txt", "All Animal NGOs India (Child of Nature, 2006)",
            "https://www.scribd.com/document/342053239/All-Animal-NGOs-India", "", False),
    "vet": ("scribd-vet-hospitals.txt", "Veterinary Hospitals of India (radhee.com)",
            "https://www.scribd.com/document/134936129/Veterinary-Hospitals-of-India", "", True),
    "pfa": ("scribd-people-for-animals.txt", "People For Animals: other NGOs (2019)",
            "https://www.scribd.com/document/425235130/People-for-Animals", "2019-08-28", True),
}
MAP_SOURCE = {"Child of Nature list (2006)": "con", "Veterinary Hospitals of India (c. 2013)": "vet", "PFA other NGOs (2019)": "pfa"}
# key text in the list, listing name, state, district, extra types, what it is (or '')
NEW = [
    ("con", "Sreenidhi Animal Welfare Society", "Sreenidhi Animal Welfare Society", "Andhra Pradesh", "Chittoor", [], ""),
    ("con", "Azam for Animals", "Azam for Animals", "Assam", "Kamrup Metro", [], ""),
    ("con", "Venu Menon Animal Allies Foundation", "Venu Menon Animal Allies Foundation", "Delhi", "New Delhi", [], ""),
    ("con", "Jamshedpur Animal Welfare Society - JAWS", "Jamshedpur Animal Welfare Society (JAWS)", "Jharkhand", "East Singhbum", [], ""),
    ("con", "Animal Lovers Assocation Society - ALAS", "Animal Lovers Association Society (ALAS)", "Maharashtra", "Mumbai Suburban", [], ""),
    ("con", "Animal Welfarer's Association", "Animal Welfarer's Association", "Maharashtra", "Thane", [], ""),
    ("con", "Viniyog Parivar Trust", "Viniyog Parivar Trust", "Maharashtra", "Mumbai Suburban", [], ""),
    ("con", "Animal Citizen", "Animal Citizen", "Tamil Nadu", "Chennai", [], ""),
    ("con", "Hill View Farm Animal Refuge", "Hill View Farm Animal Refuge", "Tamil Nadu", "The Nilgiris", ["animal-shelter"],
     "an animal refuge at Masinagudi in the Nilgiris"),
    ("vet", "Rayalaseema S.P.C.A.", "Rayalaseema SPCA", "Andhra Pradesh", "Kurnool", ["rescue-group"],
     "an SPCA at Erragudur, Kurnool district, running a veterinary hospital, a mobile dispensary and an animal ambulance"),
    ("vet", "Charity Birds Hospital", "Charity Birds Hospital (Jain Lal Mandir)", "Delhi", "Old Delhi", ["bird-hospital"],
     "the bird hospital at Shri Digambar Jain Lal Mandir, opposite Red Fort, Chandni Chowk, run by Prachin Shri Aggarwal Digambar Jain Panchayat"),
    ("vet", "Charitable Birds Hospital", "Charitable Birds Hospital, Navin Shahdara", "Delhi", "East", ["bird-hospital"],
     "a bird hospital run by Shri Vijayanand Sureshwar Jain Sewa Trust in Navin Shahdara"),
    ("vet", "SPCA, Udgir", "SPCA Udgir", "Maharashtra", "Latur", ["rescue-group"], ""),
    ("pfa", "ANIMAL RESCUE MACC, JAMMU", "Animal Rescue MACC", "Jammu and Kashmir", "Jammu", ["rescue-group"], ""),
    ("pfa", "ANIMAL RESCUE CORPS INDIA, DELHI", "Animal Rescue Corps India", "Delhi", "South", ["rescue-group"],
     "an animal rescue group with volunteers in Gurgaon, Noida and South Delhi"),
    ("pfa", "ANGAJA FOUNDATION, DELHI", "Angaja Foundation", "Delhi", "New Delhi", [], ""),
    ("pfa", "BHAGYANAND ANIMAL SOCIETY", "Bhagyanand Animal Society", "Andhra Pradesh", "Chittoor", [], "an animal welfare society in Punganur, Chittoor district"),
    ("pfa", "CHARITABLE WELFARE SOCIETY FOR HUMANKIND AND ANIMALS", "Charitable Welfare Society for Humankind and Animals", "Madhya Pradesh", "Bhopal", [], ""),
    ("pfa", "GOLAGHAT FOUNDATION", "Golaghat Foundation", "Assam", "Golaghat", [], ""),
    ("pfa", "INITIATIVE FOR SYMBIOSIS BETWEEN HUMANS", "Initiative for Symbiosis between Humans & Animals (ISHA)", "Maharashtra", "Pune", [], ""),
    ("pfa", "MARUTHI ANIMAL CHARITABLE TRUST", "Maruthi Animal Charitable Trust", "Karnataka", "Bengaluru Urban", [], ""),
    ("pfa", "RAKSHITA ANIMAL CARE SOCIETY", "Rakshita Animal Care Society", "Madhya Pradesh", "Bhopal", [], ""),
    ("pfa", "ROAR AGAINST ANIMAL CRUELTY", "Roar Against Animal Cruelty", "Madhya Pradesh", "Bhopal", [], ""),
    ("pfa", "ROVER GROUP, GUWAHATI", "Rover Group", "Assam", "Kamrup Metro", [], ""),
    ("pfa", "SAI JI ANIMAL CARE SOCIETY", "Sai Ji Animal Care Society", "Punjab", "Patiala", [], ""),
    ("pfa", "SONATA FOUNDATION, KOLKATA", "Sonata Foundation", "West Bengal", "Kolkata", [], ""),
    ("pfa", "TAMIL NADU ECOLOGICAL", "Tamil Nadu Ecological & Animal Protection Society (TEAPS)", "Tamil Nadu", "The Nilgiris", [], ""),
    ("pfa", "HANUMAN VATIKA, GURGAON", "Hanuman Vatika (monkey shelter)", "Haryana", "Gurugram", ["animal-shelter"],
     "a monkey shelter at Village Sultanpur, Gurgaon"),
]
EMAIL = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
WEB = re.compile(r"(?:https?://|www\.)[^\s,;)]+", re.I)
PHONE = re.compile(r"(?<!\d)(?:\+?91[\s-]?)?(?:0?([6-9]\d{4})[\s-]?(\d{5})|(0\d{2,4})[\s-]?(\d{6,8}))(?!\d)")


HEADER = re.compile(r"^\s*[A-Z0-9][A-Z0-9 &.'’()\-/]{3,80},[A-Z0-9 ,.&'’()\-/]{2,60}:\s*$")


def block(src, key):
    """The organisation's own entry: from its heading line to the next organisation's heading."""
    lines = open(os.path.join(W, SRC[src][0]), encoding="utf-8").read().splitlines()
    for i, l in enumerate(lines):
        head = l.strip()
        if src == "pfa":
            if not (HEADER.match(l) and head.upper().startswith(key.upper())):
                continue
        elif not head.lower().startswith(key.lower()):
            continue
        out = [l]
        for m in lines[i + 1:i + 12]:
            if src == "pfa" and HEADER.match(m):
                break
            if src != "pfa" and not m.strip() and len(out) > 2:
                break
            out.append(m)
        return " ".join(out)
    return ""


def contacts(src, text, keep_phone):
    text = re.sub(r"(?<=\d)\n(?=\d)", "", text)
    emails = [e.rstrip(".").lower() for e in EMAIL.findall(text) if not re.search(r"vsnl|satyam\.net|sify\.com|bol\.net", e, re.I)]
    webs = [w.rstrip(".") for w in WEB.findall(text) if not re.search(r"facebook|instagram|scribd|childofnature|radhee|peopleforanimalsindia", w, re.I)]
    phone = ""
    if keep_phone:
        for m in PHONE.finditer(text):
            phone = (m.group(1) + m.group(2)) if m.group(1) else f"{m.group(3)}-{m.group(4)}"
            break
    site = webs[0] if webs else ""
    if site and not site.startswith("http"):
        site = "http://" + site
    return {"email": emails[0] if emails else "", "website": site, "phone": phone}


def main(listings_file):
    places = Places()
    rows = []
    for src, key, name, state, district, types, what in NEW:
        st = places.state(state)
        dist = places.district(st, district)
        if not dist:
            raise SystemExit(f"district not in LGD: {district}, {state}")
        _, label, url, on, keep = SRC[src]
        c = contacts(src, block(src, key), keep)
        rows.append(dict(c, key="web2|" + re.sub(r"\W+", "-", name.lower()).strip("-"), name=name,
                         types=["animal-welfare-organisation"] + types, state=st["code"], district=dist["code"],
                         area=f"{dist['name']}, {st['name']}", address_private="", data_source="web", source_name=label,
                         source_url=url, source_date=on,
                         content=(f"{name} is {what}. " if what else f"{name} is an animal welfare organisation in {dist['name']}, {st['name']}. ")
                         + f"It is listed in {label}, an older list; the details may be out of date."))
    # Already listed: contact details where the listing has none (import_listings.php fills empty fields only).
    seen = set()
    for r in csv.DictReader(open(os.path.join(W, "web2-mapped.csv"), encoding="utf-8")):
        if r["match"] != "listed" or not r["listing_id"] or r["listing_id"] in seen:
            continue
        a, b = " ".join(fold(r["name"]).split()), " ".join(fold(clean_title(r["listing_title"])).split())
        if SequenceMatcher(None, a, b).ratio() < 0.82:
            continue  # writing details needs a near-identical name, not a shared word
        src = MAP_SOURCE[r["source"]]
        key = r["name"].upper() if src == "pfa" else r["name"]
        c = contacts(src, block(src, key), SRC[src][4])
        if not any(c.values()):
            continue
        seen.add(r["listing_id"])
        rows.append(dict(c, post_id=int(r["listing_id"]), types=[]))
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    print(f"new {sum(1 for r in rows if 'key' in r)}, updates {sum(1 for r in rows if 'post_id' in r)}", file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
