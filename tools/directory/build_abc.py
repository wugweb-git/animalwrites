#!/usr/bin/env python3
"""AWBI ABC project recognition list -> rows for import_listings.php.

Usage: python3 build_abc.py abc.json awbi_codes.json > abc_import.json
       python3 build_abc.py --report abc.json data/listings.json <list date Y-m-d>   (dry-run report in data/out/abc.*;
       codes come from the listings export; existing ABC listings are recognised by their import key)
  abc.json        output of parse_abc.py
  awbi_codes.json wp post meta list of aw_awbi_code -> post ID (see README) to attach centres to existing listings

A centre whose AWBI code matches an existing listing becomes an update of that listing (ABC type added, ABC centre
address kept private). Any other centre becomes its own listing. Rows without a state are skipped and reported.
"""
import json
import re
import sys

from places import Places

SOURCE = {
    "source_name": "AWBI ABC list",
    "source_url": "https://awbi.gov.in/view/index/Project-Recognition-for-ABC",
    "source_date": "2026-06-29",
}


# Cells the 04-08-2026 PDF splits so that no reader gets them right (by serial number).
FIXES_2026_08 = {17: {"state": "Jammu and Kashmir", "district": "Srinagar"},
                 103: {"state": "Tamil Nadu", "district": "Virudhunagar", "centre": "Manga Puram"},
                 66: {"org": "ABC Centre, Balussery"}, 110: {"org": "ABC Centre, Punthalathazham"},
                 108: {"district": "Wayanad"}, 4: {"district": "Kannur"}}


def jmy(d):
    """The site's one date format (j M Y)."""
    from datetime import datetime
    return datetime.strptime(d, "%Y-%m-%d").strftime("%-d %b %Y")


def main(abc_file, codes_file, report=None, on=None):
    rows = json.load(open(abc_file, encoding="utf-8"))
    if on:
        SOURCE["source_date"] = on
    if on == "2026-08-04":
        for r in rows:
            r.update(FIXES_2026_08.get(r["n"], {}))
    for r in rows:
        r["state"] = re.sub(r"(?<=[a-z]) (?=[a-z]\b)", "", r["state"])  # "Maharashtr a" (wrapped cell)
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
        note = (f"ABC centre: {r['centre']}. " if r["centre"] else "") + f"ABC project recognition valid until {jmy(r['valid'])} (AWBI list of {jmy(SOURCE['source_date'])})."
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
    if report:
        return report_out(out, rows, skipped, report)
    json.dump(out, sys.stdout, ensure_ascii=False)
    print(f"ABC rows {len(rows)} -> new listings {sum(1 for o in out if 'key' in o)}, "
          f"existing listings updated {len(by_code)}, skipped (no state) {skipped}", file=sys.stderr)


def _abc_match(o, listings):
    """The existing ABC listing for a row without a usable AWBI code: same state, and an alike name or ABC centre
    (centres of the 29-06-2026 import often have no district)."""
    from match import name_similarity
    best, score = None, 0.0
    centre = o.get("address_private", "")
    for l in listings:
        if l.get("merged_into") or o["state"] not in (l.get("states") or []) or "abc-centre" not in l.get("types", []):
            continue
        if o["district"] and [d for d in (l.get("districts") or []) if d] and o["district"] not in l["districts"]:
            continue  # both placed, in different districts
        key_centre = (l.get("import_key") or "").split("|")[2] if (l.get("import_key") or "").count("|") >= 3 else ""
        sc = max(name_similarity(o["name"], l["title"]), name_similarity(centre, key_centre) if key_centre else 0)
        if sc > score:
            best, score = l, sc
    return (best, score) if score >= 0.8 else (None, score)


def report_out(out, rows, skipped, listings):
    from report import Report
    rep = Report("abc", "AWBI ABC project recognition list (4 Aug 2026)", SOURCE["source_url"])
    rep.found = len(rows)
    by_id = {l["id"]: l for l in listings}
    for n in skipped:
        rep.skip("no state")
    for o in out:
        if "post_id" in o:
            l = by_id.get(o["post_id"], {})
            rep.match(o, {"listing": o["post_id"], "listing_title": l.get("title"), "centres": len(o["abc_centres"]),
                          "adds": [t for t in o["types"] if t not in l.get("types", [])]})
            continue
        l, sc = _abc_match(o, listings)
        if l:  # already listed: refresh its ABC validity, never its name or text
            row = {"post_id": l["id"], "types": ["abc-centre"], "abc_valid": o["abc_valid"], **SOURCE}
            rep.match(row, {"listing": l["id"], "listing_title": l["title"], "row": o["name"], "score": sc,
                            "abc_valid": f"{l.get('abc_valid')} -> {o['abc_valid']}", "adds": [t for t in row["types"] if t not in l["types"]]})
        else:
            rep.new(o, {"name": o["name"], "area": o["area"], "centre": o["address_private"], "valid": o["abc_valid"], "best_score": sc})
    rep.write()


if __name__ == "__main__":
    if sys.argv[1] == "--report":
        L = json.load(open(sys.argv[3], encoding="utf-8"))
        codes = {l["awbi_code"].strip().upper(): l["id"] for l in L if l.get("awbi_code") and not l.get("merged_into")}
        import tempfile
        tf = tempfile.NamedTemporaryFile("w", suffix=".json", delete=False)
        json.dump(codes, tf)
        tf.close()
        main(sys.argv[2], tf.name, report=L, on=sys.argv[4])
    else:
        main(*sys.argv[1:3])
