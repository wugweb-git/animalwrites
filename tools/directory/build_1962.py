#!/usr/bin/env python3
"""1962 Mobile Veterinary Units -> one listing per state that has units, for import_listings.php.

Source: DAHD reply to Lok Sabha unstarred question 2854 (18 March 2025), Annexure I (units commissioned per state).
Usage: pdftotext -layout LS2854.pdf mvu.txt && python3 build_1962.py mvu.txt > mvu_import.json
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


if __name__ == "__main__":
    main(sys.argv[1])
