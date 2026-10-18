#!/usr/bin/env python3
"""1962 Mobile Veterinary Units -> one listing per state that has units, for import_listings.php.

Source: DAHD reply to Lok Sabha unstarred question 2854 (18 March 2025), Annexure I (units commissioned per state).
Usage: pdftotext -layout LS2854.pdf mvu.txt && python3 build_1962.py mvu.txt > mvu_import.json
Check against DAHD's reply to Lok Sabha question 2879 (same day; Annexure II, MVUs sanctioned and operational per state):
       python3 build_1962.py --ls2879 LS2879.txt data/listings.json   (dry-run report; new rows only for states with
       operational units and no listing yet)
Both PDFs left dahd.gov.in in 2026; copies: Wayback Machine, 22 Dec 2025 (data/sources/dahd/).
Per-unit or per-district phone numbers are not published; units are dispatched by each state's 1962 call centre.
"""
import json
import re
import sys

from places import Places

SOURCE = {
    "source_name": "DAHD (Lok Sabha question 2854)",
    "source_url": "https://dahd.gov.in/sites/default/files/2025-04/LS2854.pdf",
    "source_date": "2025-03-18",
}


def main(txt_file):
    text = open(txt_file, encoding="utf-8").read()
    annex = text.split("Annexure –I")[-1].split("Annexure II")[0]
    places = Places()
    out, unknown = [], []
    for m in re.finditer(r"^\s*\d{1,2}\s+([A-Za-z&][A-Za-z& ]+?)\s{2,}(\d+)\s*$", annex, re.M):
        name, units = m.group(1).strip(), int(m.group(2))
        st = places.state(name)
        if not st or units < 1:
            unknown.append(name)
            continue
        out.append({
            "key": f"mvu1962|{st['slug']}",
            "name": f"1962 Mobile Veterinary Unit, {st['name']}",
            "types": ["mobile-veterinary-unit"],
            "state": st["code"], "district": 0, "area": st["name"],
            "phone": "1962", "address_private": "",
            "content": f"{units} mobile veterinary units commissioned in {st['name']}, dispatched through the state's "
                       f"1962 call centre (DAHD reply to Lok Sabha unstarred question 2854, 18 March 2025).",
            **SOURCE,
        })
    json.dump(out, sys.stdout, ensure_ascii=False)
    print(f"states with units: {len(out)}; not matched: {unknown}", file=sys.stderr)


def ls2879(txt_file, listings_file):
    from report import Report
    rep = Report("dahd-1962", "DAHD reply to Lok Sabha question 2879 (18 Mar 2025), Annexure II",
                 "https://web.archive.org/web/20251222000000*/https://www.dahd.gov.in/sites/default/files/2025-04/LS2879.pdf")
    text = open(txt_file, encoding="utf-8").read()
    annex = text.split("Annexure-II")[-1]
    have = {l["import_key"] for l in json.load(open(listings_file, encoding="utf-8")) if l.get("import_key")}
    places = Places()
    for m in re.finditer(r"^\s*\d{1,2}\s+([A-Za-z&][A-Za-z& ]+?)\**\s{2,}(\d+)\s+(\d+)\s*$", annex, re.M):
        name, sanctioned, running = m.group(1).strip(), int(m.group(2)), int(m.group(3))
        rep.found += 1
        st = places.state(name)
        if not st:
            rep.skip("state not in LGD", {"state": name})
        elif running < 1:
            rep.skip("no units operational", {"state": name, "sanctioned": sanctioned})
        elif f"mvu1962|{st['slug']}" in have:
            rep.skip("already listed")
        else:
            rep.new({"key": f"mvu1962|{st['slug']}", "name": f"1962 Mobile Veterinary Unit, {st['name']}",
                     "types": ["mobile-veterinary-unit"], "state": st["code"], "district": 0, "area": st["name"],
                     "phone": "1962", "address_private": "", "data_source": "",
                     "content": f"{running} mobile veterinary units operational in {st['name']}, dispatched through the "
                                f"state's 1962 call centre (DAHD reply to Lok Sabha unstarred question 2879, 18 March 2025).",
                     "source_name": "DAHD (Lok Sabha question 2879)", "source_url": rep.url, "source_date": "2025-03-18"})
    rep.write()


if __name__ == "__main__":
    if sys.argv[1] == "--ls2879":
        ls2879(sys.argv[2], sys.argv[3])
    else:
        main(sys.argv[1])
