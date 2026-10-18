#!/usr/bin/env python3
"""People For Animals unit pages -> PFA unit listings / contact details, for import_listings.php.

Source: peopleforanimalsindia.org/units.html and its 80 unit pages (saved in data/sources/pfa/, 18 Oct 2026): unit
("State - City"), the unit's contact phone and email as PFA publishes them. Only the unit's own contact is stored:
no names, and not the other members' mobiles. A unit already listed (title has People For Animals / PFA, same
district) gets the phone and email where its listing has none; other units become listings "People For Animals,
<City>" (Animal Welfare Organisation).
Usage: python3 build_pfa.py data/listings.json
"""
import csv
import json
import os
import re
import sys

from places import Places
from report import Report

HERE = os.path.dirname(os.path.abspath(__file__))
CSV = os.path.join(HERE, "data", "sources", "pfa", "pfa-units.csv")
PFA = re.compile(r"\bpeople\s*for\s*animals?\b|\bp\.?\s?f\.?\s?a\b", re.I)
CITY = {"Ahmednagar": "Ahilyanagar", "Gurgaon": "Gurugram", "Bangalore": "Bengaluru Urban", "Mysore": "Mysuru",
        "Allahabad": "Prayagraj", "Trivandrum": "Thiruvananthapuram", "Calicut": "Kozhikode", "Baroda": "Vadodara",
        "Bhubaneswar": "Khordha", "Noida": "Gautam Buddha Nagar", "Mohali": "Sas Nagar", "Cochin": "Ernakulam",
        "Rohilkhand / Bareilly": "Bareilly", "Badaun": "Budaun", "Ponda": "North Goa", "Vasco": "South Goa",
        "Gurgaon / Sadhana": "Gurugram", "Khamgaon": "Buldhana", "Kotabagh": "Nainital", "Pali Marwar": "Pali",
        "Panchgani": "Satara", "Raiganj": "Uttar Dinajpur", "Roorkee": "Haridwar", "Secunderabad": "Hyderabad",
        "Suratgarh": "Ganganagar", "Talcher": "Anugola", "Talcher II": "Anugola", "Shillong": "East Khasi Hills",
        "Bhiwadi": "Khairthal Tijara", "Trivanthampuram": "Thiruvananthapuram", "Tambaram": "Chengalpattu",
        "Thirssur": "Thrissur", "Hubli": "Dharwad", "Durg Bhilai": "Durg", "Kumbakonam": "Thanjavur",
        "Sivakasi": "Virudhunagar", "Siliguri": "Darjeeling", "Nagpur (Vidarbha)": "Nagpur"}
DISPLAY = {"Rohilkhand / Bareilly": "Bareilly", "Gurgaon / Sadhana": "Gurugram (Sadhana)", "Trivanthampuram": "Thiruvananthapuram",
           "Thirssur": "Thrissur", "Badaun": "Budaun"}
STATE = {"Rajsthan": "Rajasthan", "Hyderabad": "Telangana", "Uttrakhand": "Uttarakhand"}


def phone_of(s):
    s = re.sub(r"\(.*?\)", "", s or "")
    m = re.search(r"(?:\+?91[\s-]?)?0?([6-9]\d{4}[\s-]?\d{5})|\b(0\d{2,4}[\s-]?\d{6,8})\b", s)
    if not m:
        return ""
    return re.sub(r"[\s-]", "", m.group(1)) if m.group(1) else re.sub(r"\s", "-", m.group(2))


def main(listings_file):
    rep = Report("pfa", "People For Animals unit pages", "https://peopleforanimalsindia.org/units.html")
    places = Places()
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    for r in csv.DictReader(open(CSV, encoding="utf-8")):
        rep.found += 1
        state_name, _, city = r["unit"].partition(" - ")
        city = city.strip() or state_name.strip()
        st = places.state(STATE.get(state_name.strip(), state_name.strip()))
        dist = places.district(st, CITY.get(city, city)) if st else None
        sample = {"unit": r["unit"], "phone": phone_of(r["phone"]), "email": r["email"].strip().lower()}
        if not st:
            rep.skip("state not in LGD", sample)
            continue
        phone, email = sample["phone"], sample["email"]
        fields = {"phone": phone, "email": email if "@" in email else "", "data_source": "pfa", "source_name": "People For Animals (unit page)",
                  "source_url": r["detail_url"], "source_date": "2026-10-18"}
        town = re.sub(r"\s+(I{1,3}|\d)$", "", city.split("/")[-1].strip())
        hits = [l for l in L if PFA.search(l["title"]) and (
            (dist and dist["code"] in (l.get("districts") or []))
            or (st["code"] in (l.get("states") or []) and town.lower() in l["title"].lower()))]
        numbered = re.search(r"\b(I{1,3}|\d)\s*$", city) or len([x for x in hits]) > 1
        if hits and (numbered or re.search(r"\bunit\b", hits[0]["title"], re.I)):
            rep.maybe(dict(sample, listing=hits[0]["id"], listing_title=hits[0]["title"]))  # which unit is which: a person decides
            continue
        if hits:
            l = hits[0]
            if l.get("protected"):
                rep.skip("verified or claimed", dict(sample, listing=l["title"]))
                continue
            adds = [k for k in ("phone", "email") if fields[k] and not (l["phones"] if k == "phone" else l["email"])]
            if not adds:
                rep.skip("already has phone and email")
                continue
            rep.match(dict(fields, post_id=l["id"], types=[]), dict(sample, listing=l["id"], listing_title=l["title"], adds=adds))
            continue
        if not dist:
            rep.skip("city not matched to a district", sample)
            continue
        name = "People For Animals, " + DISPLAY.get(city, city)
        rep.new(dict(fields, key="pfa|" + re.sub(r"\W+", "-", r["unit"].lower()).strip("-"), name=name,
                     types=["animal-welfare-organisation"], state=st["code"], district=dist["code"],
                     area=f"{dist['name']}, {st['name']}", address_private="",
                     content=(f"The {DISPLAY.get(city, city)} unit of People For Animals (PFA), the national animal welfare organisation. "
                              f"Contact details from PFA's unit page (18 Oct 2026).")),
                dict(sample, name=name, district=dist["name"]))
    rep.write()


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
