#!/usr/bin/env python3
"""Parse AWBI's ABC project recognition list (04-08-2026 layout) with pdfplumber's table reader.

The 04-08-2026 PDF wraps every cell over several lines and its text layer does not keep cells in line, so the word
positions (parse_abc.py) mix up neighbouring rows. pdfplumber reads the ruled table: a record is the row with a serial
number plus the rows under it until the next serial; dates broken over cells are joined again.
Usage: python3 parse_abc_tables.py abc.pdf > abc.json   (needs pdfplumber; output as parse_abc.py)
"""
import json
import re
import sys

import pdfplumber

DATE = re.compile(r"(\d\d)[.-](\d\d)[.-](\d{4})")
CODE = re.compile(r"\(\s*([A-Z&]{2,4}\s?\d{1,4}\s?/\s?[\d\s]{2,5}(?:-\s?\d{2})?)\s*\)")
STATES = ["Andaman and Nicobar Islands", "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chandigarh",
          "Chhattisgarh", "Delhi", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jammu and Kashmir", "Jharkhand",
          "Karnataka", "Kerala", "Ladakh", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram",
          "Nagaland", "Odisha", "Puducherry", "Pondicherry", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana",
          "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal"]


def squash(s):
    return re.sub(r"\s+", "", s.lower())


def main(pdf_file):
    recs, cur = [], None
    with pdfplumber.open(pdf_file) as pdf:
        for page in pdf.pages:
            for table in page.extract_tables():
                for row in table:
                    cells = [re.sub(r"\s+", " ", c or "").strip() for c in row]
                    if len(cells) < 9:
                        continue  # stray one-cell rows (a page's repeated name column)
                    if re.fullmatch(r"\d{1,3}\.?", cells[0]):
                        cur = {"n": int(cells[0].rstrip(".")), "org1": cells[1], "org2": [cells[2]], "centre": [cells[4]],
                               "rest": [" ".join(cells[5:])]}
                        recs.append(cur)
                    elif cur is not None and not cells[0]:
                        cur["org2"].append(cells[2])
                        cur["centre"].append(cells[4])
                        cur["rest"].append(" ".join(cells[5:]))
    out = []
    for r in recs:
        org = r["org1"] or " ".join(x for x in r["org2"] if x)
        code = CODE.search(org)
        org = re.sub(r"\(\s*(Offline|Online)\s*\)", "", CODE.sub("", org)).strip(" ,")
        rest = " ".join(x for x in r["rest"] if x)
        dates = DATE.findall(re.sub(r"(?<=[\d.-])\s+(?=[\d.-])", "", rest))
        flat = squash(rest)
        state = next((s for s in STATES if squash(s) in flat), "")
        district = rest
        if state:  # the district is the text before the state (letters may be split over cells)
            i = flat.find(squash(state))
            n, k = 0, 0
            while k < len(rest) and n < i:
                if not rest[k].isspace():
                    n += 1
                k += 1
            district = rest[:k]
        district = DATE.sub("", district).strip(" ,")
        out.append({
            "n": r["n"], "org": re.sub(r"\s+", " ", org), "awbi_code": re.sub(r"\s", "", code.group(1)) if code else "",
            "centre": " ".join(x for x in r["centre"] if x).strip(" ,"), "district": district,
            "state": "Puducherry" if state == "Pondicherry" else state,
            "recognised": "-".join(reversed(dates[0])) if dates else "", "valid": "-".join(reversed(dates[1])) if len(dates) > 1 else "",
        })
    json.dump(out, sys.stdout, ensure_ascii=False, indent=1)
    print(f"rows: {len(out)}; with AWBI code: {sum(1 for r in out if r['awbi_code'])}; no state: "
          f"{sum(1 for r in out if not r['state'])}; no dates: {sum(1 for r in out if not r['valid'])}", file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1])
