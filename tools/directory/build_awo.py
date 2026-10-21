#!/usr/bin/env python3
"""AWO List (compiled by Saumya Garg, Dec 2023 to Jan 2025) -> new listings / fills, for import_listings.php.

Source: awo-list-1.2.json and awol-match.json (data/awo-list-sgarg/, captured 8 Oct 2026; the Drive folder is not public).
Owner approved publishing it on 21 Oct 2026. Rules: an exact match (score 95 and over) to an existing listing fills its
phone only when the listing has none, and adds the types the organisation stated (rescue, shelter); verified or claimed
listings are left alone. A likely match (80 to 94), or a same-name organisation in the same district, is held back for a
person. Anything else becomes a new listing (Animal Welfare Organisation, plus Rescue Group / Animal Shelter when the
organisation said so), published unverified. The address is kept private; the phone is the organisation's, as it told
the compilers. The list is older than the AWBI and Darpan imports, so it never replaces a value.
Usage: python3 build_awo.py <awo dir> data/listings.json
"""
import json
import os
import re
import sys

from places import Places, norm
from report import Report

SOURCE_NAME = "AWO List (S. Garg)"
SOURCE_DATE = "2025-01-03"


def phone_of(raw):
    for p in raw or []:
        d = re.sub(r"\D", "", p)
        d = d[2:] if len(d) == 12 and d.startswith("91") else d.lstrip("0") if len(d) == 11 else d
        if re.fullmatch(r"[6-9]\d{9}", d):
            return d
        if re.fullmatch(r"\d{6,12}", d):
            return d
    return ""


def main(awo_dir, listings_file):
    rep = Report("awol", "AWO List (compiled by Saumya Garg)", "")
    places = Places()
    orgs = {o["id"]: o for o in json.load(open(os.path.join(awo_dir, "awo-list-1.2.json"), encoding="utf-8"))["organisations"]}
    match = {m["id"]: m for m in json.load(open(os.path.join(awo_dir, "awol-match.json"), encoding="utf-8"))}
    L = {l["id"]: l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")}
    by_name = {}
    for l in L.values():
        by_name.setdefault(norm(l["title"]), []).append(l)
    seen = set()
    state_only = 0
    for oid, o in orgs.items():
        rep.found += 1
        m = match.get(oid) or {}
        phone = phone_of(o.get("phones"))
        types = ["animal-welfare-organisation"]
        said = []
        if o.get("rescue_yn") == "yes":
            types.append("rescue-group")
            said.append("rescues animals")
        if o.get("animal_shelter_yn") == "yes":
            types.append("animal-shelter")
            said.append("runs an animal shelter")
        if o.get("treatment_yn") == "yes":
            said.append("treats animals")
        sample = {"id": oid, "name": o["name"], "state": o["state"], "district": o["district"], "phone": phone}
        lid, score = m.get("listing_id"), m.get("score", 0)
        if lid and lid in L and score >= 95:
            l = L[lid]
            if l.get("protected"):
                rep.skip("verified or claimed", dict(sample, listing=l["title"]))
                continue
            fields = {"post_id": lid, "types": types[1:] and types, "data_source": "awol", "source_name": SOURCE_NAME,
                      "source_url": "", "source_date": SOURCE_DATE}
            if phone and not l["phones"]:
                fields["phone"] = phone
            rep.match(fields, dict(sample, listing=lid, listing_title=l["title"], adds=[k for k in ("phone",) if k in fields]))
            continue
        if lid and score >= 80:
            rep.maybe(dict(sample, listing=lid, listing_title=m.get("listing"), score=score))
            continue
        st = places.state(o["state"], o.get("address", ""))
        dist = places.district(st, o["district"], address=o.get("address", "")) if st else None
        if not st:
            rep.skip("state not matched to LGD", sample)
            continue
        if not dist:
            state_only += 1  # the list often gives the state as the district
        dcode, dname = (dist["code"], dist["name"]) if dist else (0, "")
        where = f"{dname}, {st['name']}" if dist else st["name"]
        twins = [l for l in by_name.get(norm(o["name"]), []) if (dist and dcode in (l.get("districts") or [])) or (not dist and st["code"] in (l.get("states") or []))]
        if twins:
            rep.maybe(dict(sample, listing=twins[0]["id"], listing_title=twins[0]["title"], score="same name"))
            continue
        if re.search(r"\b(honorary|officer)\b", o["name"], re.I):
            rep.maybe(dict(sample, listing=0, listing_title="", score="a post, not an organisation"))
            continue
        key = "awol|" + oid
        if key in seen or any(l.get("import_key") == key for l in L.values()):
            rep.skip("already imported", sample)
            continue
        seen.add(key)
        name = re.sub(r"\s+", " ", o["name"]).strip()
        joined = said[0] if len(said) == 1 else ", ".join(said[:-1]) + " and " + said[-1] if said else ""
        told = f" It told the AWO List compilers that it {joined}." if said else ""
        content = (f"{name} is an animal welfare organisation in {where}. It is listed in the AWO List "
                   f"compiled by Saumya Garg (December 2023 to January 2025).{told}")
        rep.new({"key": key, "name": name, "types": types, "state": st["code"], "district": dcode,
                 "area": where, "phone": phone, "address_private": o.get("address", ""),
                 "content": content, "source_name": SOURCE_NAME, "source_url": "", "source_date": SOURCE_DATE,
                 "data_source": "awol"}, dict(sample, name=name, district=dname))
    counts = {}
    for r in rep.rows:
        if "key" in r:
            counts[norm(r["name"])] = counts.get(norm(r["name"]), 0) + 1
    for r in rep.rows:
        if "key" in r and (counts[norm(r["name"])] > 1 or by_name.get(norm(r["name"]))):
            r["name"] = f"{r['name']}, {r['area'].split(',')[0].strip()}"  # same name elsewhere: the place tells them apart
            r["content"] = r["content"].replace(r["name"].rsplit(", ", 1)[0] + " is", r["name"] + " is", 1)
    rep.notes.append(f"{state_only} new listings carry the state only (district not stated or not matched)")
    rep.write()


if __name__ == "__main__":
    HERE = os.path.dirname(os.path.abspath(__file__))
    main(sys.argv[1], sys.argv[2] if len(sys.argv) > 2 else os.path.join(HERE, "data", "listings.json"))
