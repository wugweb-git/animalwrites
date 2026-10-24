#!/usr/bin/env python3
"""AWO List: the rows build_awo.py held back for a person, decided by the owner's rule (24 Oct 2026).

- Merge (fill an existing listing, as build_awo.py does for exact matches) when the phone matches a published listing's
  phone, or the name and district match exactly. The listing's own values stand: the phone fills only an empty one,
  types are added; verified or claimed listings are left alone.
- Add as new when nothing matches: the held listing and the AWO row both have phones and they differ, no other listing
  has the AWO phone, and the names share no distinctive word (generic words such as trust, society, animal, welfare,
  shri are left out; "Seva Charitable Trust" vs "Jivdaya Charitable Trust"). Names that share one ("Pet Force" and
  "Pets Force"), are spelled almost alike (85% or more), or have no distinctive word at all, are most likely the same organisation with another number: the owner decides.
- Everything else goes to the remainder for the owner: no phone on one side (nothing to tell them apart), a phone
  matching a different listing from the name match, a post rather than an organisation, a place not matched.
Usage: python3 awo_held.py <awo dir> <held.csv> data/listings.json   (dry run: data/out/awol-held.* and remainder CSV)
"""
import csv
import difflib
import json
import os
import re
import sys

from build_awo import SOURCE_DATE, SOURCE_NAME, phone_of
from places import Places, norm
from report import Report


GENERIC = set("the a an and of for to in at by shri shree sri trust society foundation charitable welfare animal animals "
               "pet pets care protection seva sewa sansthan samiti organisation organization association rescue shelter "
               "helpline home group india ngo".split()) - {"seva", "sewa", "pet", "pets"}


def words(name):
    """Distinctive words of a name: lower case, plural s dropped, generic words left out."""
    ws = {w.rstrip("s") for w in re.findall(r"[a-z]+", name.lower()) if len(w) > 2}
    return {w for w in ws if w not in {g.rstrip("s") for g in GENERIC}}


def digits(p):
    d = re.sub(r"\D", "", p or "")
    return d[-10:] if len(d) >= 10 else d


def main(awo_dir, held_csv, listings_file):
    rep = Report("awol-held", "AWO List, held rows decided (owner, 24 Oct 2026)", "")
    places = Places()
    orgs = {o["id"]: o for o in json.load(open(os.path.join(awo_dir, "awo-list-1.2.json"), encoding="utf-8"))["organisations"]}
    L = {l["id"]: l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into") and l.get("status") == "publish"}
    by_phone = {}
    for l in L.values():
        for p in l.get("phones") or []:
            by_phone.setdefault(digits(p), set()).add(l["id"])
    remainder = []
    decisions = []
    held = list(csv.DictReader(open(held_csv, encoding="utf-8")))
    for h in held:
        o = orgs[h["awo_id"]]
        phone = phone_of(o.get("phones"))
        types = ["animal-welfare-organisation"] + (["rescue-group"] if o.get("rescue_yn") == "yes" else []) + (["animal-shelter"] if o.get("animal_shelter_yn") == "yes" else [])
        st = places.state(o["state"], o.get("address", ""))
        dist = places.district(st, o["district"], address=o.get("address", "")) if st else None
        sample = {"id": h["awo_id"], "name": o["name"], "state": o["state"], "district": o["district"], "phone": phone}

        def park(why, lid=0):
            remainder.append(dict(h, decision="owner", reason=why, awo_phone=phone, listing_phones=" ".join((L.get(lid) or {}).get("phones") or [])))
            decisions.append({"awo_id": h["awo_id"], "awo_name": o["name"], "district": o["district"], "awo_phone": phone, "decision": "owner", "listing": lid or "", "listing_title": (L.get(lid) or {}).get("title", h["existing_listing_title"]), "reason": why + (" (listing phones: " + " ".join((L.get(lid) or {}).get("phones") or []) + ")" if (L.get(lid) or {}).get("phones") else "")})

        if "a post" in h["why_held"]:
            park("a post, not an organisation")
            continue
        phone_hits = by_phone.get(digits(phone), set()) if phone else set()
        lid = int(h["existing_listing_id"] or 0)
        exact = lid in L and norm(L[lid]["title"]) == norm(o["name"]) and dist and dist["code"] in (L[lid].get("districts") or [])
        target = None
        if phone_hits:
            if len(phone_hits) > 1 or (exact and lid not in phone_hits):
                park("phone matches another listing than the name", lid)
                continue
            target = next(iter(phone_hits))
            why = "phone match"
        elif exact:
            target, why = lid, "name and district match exactly"
        if target:
            l = L[target]
            if l.get("protected"):
                rep.skip("verified or claimed", dict(sample, listing=target))
                continue
            fields = {"post_id": target, "types": types, "data_source": "awol", "source_name": SOURCE_NAME, "source_url": "", "source_date": SOURCE_DATE}
            if phone and not l["phones"]:
                fields["phone"] = phone
            rep.match(fields, dict(sample, listing=target, listing_title=l["title"], why=why))
            decisions.append({"awo_id": h["awo_id"], "awo_name": o["name"], "district": o["district"], "awo_phone": phone, "decision": "merge", "listing": target, "listing_title": l["title"], "reason": why})
            continue
        l = L.get(lid) or {}
        if not phone or not l.get("phones"):
            park("no phone on one side: cannot tell them apart", lid)
            continue
        if not st:
            park("state not matched")
            continue
        a, b = words(o["name"]), words(l.get("title", ""))
        close = difflib.SequenceMatcher(None, o["name"].lower(), l.get("title", "").lower()).ratio() >= 0.85
        if (a & b) or close or not a or not b:
            park("similar name, phones differ: most likely the same organisation with another number", lid)
            continue
        # Both have phones, they differ, no listing has the AWO phone, and the names share no distinctive word.
        dcode, dname = (dist["code"], dist["name"]) if dist else (0, "")
        where = f"{dname}, {st['name']}" if dist else st["name"]
        name = re.sub(r"\s+", " ", o["name"]).strip()
        if norm(name) == norm(l.get("title", "")) or any(norm(x["title"]) == norm(name) for x in L.values()):
            name = f"{name}, {where.split(',')[0].strip()}"
        said = [s for k, s in (("rescue_yn", "rescues animals"), ("animal_shelter_yn", "runs an animal shelter"), ("treatment_yn", "treats animals")) if o.get(k) == "yes"]
        joined = said[0] if len(said) == 1 else ", ".join(said[:-1]) + " and " + said[-1] if said else ""
        told = f" It told the AWO List compilers that it {joined}." if said else ""
        rep.new({"key": "awol|" + h["awo_id"], "name": name, "types": types, "state": st["code"], "district": dcode, "area": where,
                 "phone": phone, "address_private": o.get("address", ""),
                 "content": f"{name} is an animal welfare organisation in {where}. It is listed in the AWO List compiled by Saumya Garg (December 2023 to January 2025).{told}",
                 "source_name": SOURCE_NAME, "source_url": "", "source_date": SOURCE_DATE, "data_source": "awol"},
                dict(sample, name=name, district=dname, why="phones differ"))
        decisions.append({"awo_id": h["awo_id"], "awo_name": o["name"], "district": o["district"], "awo_phone": phone, "decision": "add as new", "listing": "", "listing_title": "", "reason": "phones differ, names share nothing (held against " + l.get("title", "") + ")"})
    rep.write()
    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data", "out", "awol-held.remainder.csv")
    if remainder:
        with open(out, "w", encoding="utf-8", newline="") as f:
            w = csv.DictWriter(f, fieldnames=list(remainder[0].keys()))
            w.writeheader()
            w.writerows(remainder)
    with open(os.path.join(os.path.dirname(out), "awol-held.decisions.csv"), "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=list(decisions[0].keys()))
        w.writeheader()
        w.writerows(decisions)
    print(f"held {len(held)}: merge {sum(1 for r in rep.rows if 'post_id' in r)}, new {sum(1 for r in rep.rows if 'key' in r)}, owner {len(remainder)} ({out})")


if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2], sys.argv[3])
