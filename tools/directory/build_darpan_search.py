#!/usr/bin/env python3
"""NGO Darpan "Animal Welfare" sector search (map_darpan.py output) -> new listings for the NGOs with an animal name.

Owner, 18 Oct 2026: import the Darpan NGOs whose name is about animals and that are not in the directory yet. Fields:
name, registration number, registered district and state (LGD), sectors; the address is kept private. Darpan's search
list gives no phone, email or Darpan ID. Types from the name: gaushala/goshala/gau -> Gaushala, rescue -> Rescue Group,
shelter -> Animal Shelter, always Animal Welfare Organisation.
Usage: python3 build_darpan_search.py data/darpan/darpan-mapped.csv > data/out/darpan-search.rows.json
"""
import csv
import json
import re
import sys
from datetime import date

from places import Places

URL = "https://ngodarpan.gov.in/#/search-ngo"
ON = date.today().isoformat()
TYPES = [("gaushala", re.compile(r"\b(gau|gou|go)\s?(shala|sala|seva|sewa|raksha|sanrakshan\w*)\b|\b(gaushal\w*|goushal\w*|goshal\w*|gowshal\w*)\b", re.I)),
         ("rescue-group", re.compile(r"\brescue\b", re.I)),
         ("animal-shelter", re.compile(r"\bshelters?\b", re.I))]
DISTRICT = {"EAST": "East", "WEST": "West"}


def jmy(d):
    from datetime import datetime
    return datetime.strptime(d, "%Y-%m-%d").strftime("%-d %b %Y")


def main(csv_file):
    places = Places()
    out, skipped = [], []
    for r in csv.DictReader(open(csv_file, encoding="utf-8")):
        if r["animal_name"] != "True":
            continue
        st = places.state(r["state"])
        dist = places.district(st, DISTRICT.get(r["district"], r["district"].title())) if st else None
        if not (st and dist):
            skipped.append(r["name"])
            continue
        name = r["name"].title() if r["name"].isupper() else r["name"]
        name = re.sub(r"\s+", " ", name).strip()
        types = ["animal-welfare-organisation"] + [t for t, rx in TYPES if rx.search(name)]
        sectors = r["sectors"]
        out.append({
            "key": "darpan-search|" + re.sub(r"\W+", "-", (r["name"] + "|" + r["registration"]).lower()).strip("-"),
            "name": name, "types": types, "state": st["code"], "district": dist["code"],
            "area": f"{dist['name']}, {st['name']}", "phone": "", "address_private": r["address"],
            "registration": r["registration"], "sectors": sectors, "data_source": "darpan",
            "source_name": "NGO Darpan (NITI Aayog)", "source_url": URL, "source_date": ON,
            "content": (f"{name} is registered on NGO Darpan (NITI Aayog), with animal welfare among its sectors of work, "
                        f"in {dist['name']}, {st['name']}. Registration no. {r['registration']}. Details are as the "
                        f"organisation disclosed them on NGO Darpan, as on {jmy(ON)}; the government does not verify them."),
        })
    json.dump(out, sys.stdout, ensure_ascii=False, indent=1)
    print(f"rows {len(out)}; skipped (place) {skipped}", file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1])
