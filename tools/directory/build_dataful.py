#!/usr/bin/env python3
"""NGO Darpan (NITI Aayog) data as published by Dataful (collection 637) -> new listings / additions, for import_listings.php.

The owner downloads the CSVs from https://dataful.in/collections/637/ into data/dataful/ (Dataful needs a login; the
datasets are not free). Columns (Dataful, 26 May 2026): as_on_date, unique_id, ngo_name, ngo_email,
state_of_registration, registered_district, registered_district_as_per_lgd, registered_district_lgd_code,
registered_subdistrict, type_of_ngo, registration_authority, registration_number, act_name, date_of_registration,
sectors, operational_district, operational_state. No phone, address or website.

Kept (owner, 18 Oct 2026): sectors include "Animal Welfare"; or sectors include "Animal Husbandry, Dairying &
Fisheries" and the name is about animals (animal / rescue / welfare / SPCA / PFA / jeev / pashu / karuna / shelter /
sanctuary / bird / pakshi / gau / gaushala); and at most 5 sectors. Dairy-, fisheries- and co-operative-only rows are
dropped.
Type from the name: shelter -> Animal Shelter, rescue -> Rescue Group, bird hospital / pakshi chikitsalaya -> Bird
Hospital, ambulance -> Animal Ambulance, gau / gaushala -> Gaushala, otherwise Animal Welfare Organisation.
The information is what NGOs disclosed on Darpan; the government does not verify it (said on the listing).

Usage: python3 build_dataful.py data/listings.json [data/dataful]
"""
import csv
import glob
import json
import os
import re
import sys
from datetime import datetime

from match import Matcher
from places import Places
from report import Report

ANIMAL_NAME = re.compile(r"\b(animals?|rescue|welfare|s\.?p\.?c\.?a|p\.?f\.?a|jee?v\w*|jiv\w*|pashu\w*|karuna|shelters?|sanctuary|"
                         r"birds?|pakshi|gau\w*|gou\w*|go\s?shala|gowshala|goshala)\b", re.I)
DAIRY = re.compile(r"\b(dairy|milk|dugdh|fisher\w*|fish|matsya|co-?operative|sahakari|poultry|goat\s?farm|piggery)\b", re.I)
RULES = [
    ("animal-shelter", re.compile(r"\bshelters?\b|\bsanctuary\b", re.I)),
    ("rescue-group", re.compile(r"\brescue|\bs\.?p\.?c\.?a\b", re.I)),
    ("bird-hospital", re.compile(r"\bbirds?\s+hospital|pakshi\s+chikitsalaya|charity\s+birds?", re.I)),
    ("animal-ambulance", re.compile(r"\bambulances?\b", re.I)),
    ("gaushala", re.compile(r"\bgau\w*|\bgou\w*|\bgo\s?shala|\bgowshala|\bgo\s?seva|\bgo\s?sewa|\bgovansh", re.I)),
]


def sectors_of(s):
    # Darpan joins sectors with commas, but "Animal Husbandry, Dairying & Fisheries" has a comma of its own.
    s = (s or "").replace("Animal Husbandry, Dairying & Fisheries", "Animal Husbandry|Dairying & Fisheries")
    return [x.strip().replace("|", ", ") for x in s.split(",") if x.strip()]


def ymd(d):
    for f in ("%d-%m-%Y", "%d/%m/%Y", "%Y-%m-%d"):
        try:
            return datetime.strptime(d.strip(), f).strftime("%Y-%m-%d")
        except (ValueError, AttributeError):
            pass
    return ""


def jmy(d):
    """The site's one date format (j M Y)."""
    return datetime.strptime(d, "%Y-%m-%d").strftime("%-d %b %Y")


def title_case(name):
    name = re.sub(r"\s+", " ", (name or "").strip())
    return name.title() if name.isupper() else name


def main(listings_file, folder):
    rep = Report("darpan", "NGO Darpan (NITI Aayog) via Dataful", "https://dataful.in/collections/637/")
    files = sorted(glob.glob(os.path.join(folder, "*.csv")))
    if not files:
        rep.notes.append("waiting for files: no CSV in " + folder)
        rep.write()
        return
    places, matcher = Places(), Matcher(listings_file)
    seen = set()
    for f in files:
        for r in csv.DictReader(open(f, encoding="utf-8-sig", newline="")):
            uid = (r.get("unique_id") or "").strip()
            if not uid or uid in seen:
                continue
            seen.add(uid)
            rep.found += 1
            name = title_case(r.get("ngo_name"))
            secs = sectors_of(r.get("sectors"))
            low = [s.lower() for s in secs]
            welfare = any("animal welfare" in s for s in low)
            husbandry = any("animal husbandry" in s for s in low)
            sample = {"darpan_id": uid, "name": name, "sectors": secs, "district": r.get("registered_district_as_per_lgd")}
            if not (welfare or (husbandry and ANIMAL_NAME.search(name))):
                if husbandry:
                    rep.skip("animal husbandry sector, name not about animals")
                continue  # not an animal NGO (not counted as skipped)
            if len(secs) > 5:
                rep.skip("more than 5 sectors", sample)
                continue
            if DAIRY.search(name) and not ANIMAL_NAME.search(DAIRY.sub("", name)):
                rep.skip("dairy / fisheries / co-operative", sample)
                continue
            dcode = int(re.sub(r"\D", "", r.get("registered_district_lgd_code") or "") or 0)
            st = places.state(r.get("state_of_registration") or "")
            dist = None
            if dcode and st:
                dist = next((x for x in places.districts[st["code"]].values() if x["code"] == dcode), None)
            if not st:
                rep.skip("state not in LGD", sample)
                continue
            types = [t for t, rx in RULES if rx.search(name)] or ["animal-welfare-organisation"]
            on = ymd(r.get("as_on_date")) or "2026-05-26"
            fields = {
                "darpan_id": uid,
                "registration": (r.get("registration_number") or "").strip(),
                "reg_type": (r.get("type_of_ngo") or "").strip(),
                "reg_authority": (r.get("registration_authority") or "").strip(),
                "reg_act": (r.get("act_name") or "").strip(),
                "reg_date": ymd(r.get("date_of_registration")),
                "sectors": ", ".join(secs),
                "email": (r.get("ngo_email") or "").strip().lower(),
                "data_source": "darpan",
                "source_name": "NGO Darpan (NITI Aayog) via Dataful",
                "source_url": "https://dataful.in/collections/637/",
                "source_date": on,
            }
            kind, hit, score = matcher.find(name, st["code"], dist["code"] if dist else 0, uid, fields["registration"])
            if kind == "match":
                rep.match(dict(fields, post_id=hit["id"], types=types),
                          dict(sample, listing=hit["id"], listing_title=hit["title"], score=score, adds=types))
            elif kind == "unsure":
                rep.maybe(dict(sample, listing=hit["id"], listing_title=hit["title"], score=score))
            else:
                place = dist["name"] if dist else st["name"]
                rep.new(dict(fields, key="darpan|" + uid, name=name, types=types, state=st["code"],
                             district=dist["code"] if dist else 0, area=(place + ", " + st["name"]) if dist else st["name"],
                             phone="", address_private="",
                             content=f"{name} is registered on NGO Darpan (NITI Aayog) with Darpan ID {uid}"
                                     f"{', in ' + place if dist else ''}. Sectors listed: {', '.join(secs)}. "
                                     f"Details are as the organisation disclosed them on NGO Darpan "
                                     f"(published by Dataful, as on {jmy(on)}); the government does not verify them."),
                        dict(sample, types=types))
    rep.write()


if __name__ == "__main__":
    here = os.path.dirname(os.path.abspath(__file__))
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(here, "data", "listings.json"),
         sys.argv[2] if len(sys.argv) > 2 else os.path.join(here, "data", "dataful"))
