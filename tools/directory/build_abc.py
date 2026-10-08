#!/usr/bin/env python3
"""AWBI ABC project recognition list -> rows for import_listings.php.

Usage: python3 build_abc.py abc.json awbi_codes.json > abc_import.json
  abc.json        output of parse_abc.py
  awbi_codes.json wp post meta list of aw_awbi_code -> post ID (see README) to attach centres to existing listings

A centre whose AWBI code matches an existing listing becomes an update of that listing (ABC type added, ABC centre
address kept private). Any other centre becomes its own listing. Rows without a state are skipped and reported.
"""
import json
import sys

from places import Places

SOURCE = {
    "source_name": "AWBI ABC list",
    "source_url": "https://awbi.gov.in/view/index/Project-Recognition-for-ABC",
    "source_date": "2026-06-29",
}


def main(abc_file, codes_file):
    rows = json.load(open(abc_file, encoding="utf-8"))
    places = Places()
    codes = json.load(open(codes_file, encoding="utf-8"))  # {"MH167/2016": 123, ...}
    out, skipped, by_code = [], [], {}
    for r in rows:
        st, dist = places.match(r["state"], r["district"], r["centre"])
        if not st:
            d = places.district_anywhere(r["district"]) or places.district_anywhere(r["state"])
            if d:
                dist, st = d, places.by_code[d["state"]]
        if not st:
            skipped.append(r["n"])
            continue
        area = (dist["name"] if dist else r["district"].title()) + ", " + st["name"]
        note = (f"ABC centre: {r['centre']}. " if r["centre"] else "") + f"ABC project recognition valid until {r['valid']}."
        code = r["awbi_code"]
        if code and code in codes:
            # One update per existing listing, even when it runs several centres.
            row = by_code.setdefault(code, {"post_id": codes[code], "types": ["abc-centre"], "abc_centres": [], **SOURCE})
            row["abc_centres"].append({"centre": r["centre"], "district": dist["name"] if dist else r["district"],
                                       "state": st["name"], "valid": r["valid"]})
            continue
        out.append({
            "key": f"abc|{r['org']}|{r['centre']}|{st['slug']}",
            "name": r["org"] if r["org"] else f"ABC centre, {r['centre']}",
            "types": ["abc-centre"],
            "state": st["code"], "district": dist["code"] if dist else 0, "area": area,
            "phone": "", "address_private": r["centre"],
            "content": note, "abc_valid": r["valid"], **SOURCE,
        })
    # Same organisation running several new centres: tell the listings apart by place.
    names = [o["name"] for o in out]
    for o in out:
        if names.count(o["name"]) > 1:
            o["name"] = f"{o['name']}, {o['area'].split(',')[0]}"
    out.extend(by_code.values())
    json.dump(out, sys.stdout, ensure_ascii=False)
    print(f"ABC rows {len(rows)} -> new listings {sum(1 for o in out if 'key' in o)}, "
          f"existing listings updated {len(by_code)}, skipped (no state) {skipped}", file=sys.stderr)


if __name__ == "__main__":
    main(*sys.argv[1:3])
