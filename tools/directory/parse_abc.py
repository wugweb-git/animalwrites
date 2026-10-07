#!/usr/bin/env python3
"""Parse AWBI's "List of AWOs/Municipal Corporations Granted ABC Project Recognition" PDF.

Usage:
  pdftotext -bbox abc.pdf abc.html
  python3 parse_abc.py abc.html > abc.json

Reads each word's position: the table columns sit at fixed x positions on every page. A record starts at a serial
number in the first column and runs until the next one. Each row: n, org, awbi_code, centre, district, state,
recognised (Y-m-d), valid (Y-m-d).
"""
import html
import json
import re
import sys

# Column left edges in PDF points (same on all 7 pages of the 29-06-2026 list).
COLS = [("sl", 0), ("org", 45), ("centre", 170), ("district", 284), ("state", 362), ("recognised", 440), ("valid", 515)]
DATE = re.compile(r"(\d\d)[.-](\d\d)[.-](\d{4})")
WORD = re.compile(r'<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="[\d.]+" yMax="[\d.]+">([^<]*)</word>')
TOP, BOTTOM = 20, 800  # skip anything outside the table body


def col_of(x):
    name = COLS[0][0]
    for n, left in COLS:
        if x >= left - 2:
            name = n
    return name


def parse(src):
    rows = []
    for page in src.split("<page ")[1:]:
        raw = sorted((float(y), float(x), html.unescape(t)) for x, y, t in WORD.findall(page))
        # Words of one printed line differ by a fraction of a point in y: cluster them, then read left to right.
        line_y, words = None, []
        for y, x, t in raw:
            if line_y is None or y - line_y > 3:
                line_y = y
            words.append((line_y, x, t))
        words.sort(key=lambda w: (w[0], w[1]))
        cur = None
        for y, x, t in words:
            if y < TOP or y > BOTTOM:
                continue
            c = col_of(x)
            if c == "sl" and re.fullmatch(r"\d{1,3}\.?", t):
                cur = {k: [] for k, _ in COLS}
                cur["n"] = int(t.rstrip("."))
                rows.append(cur)
                continue
            if cur is not None and c != "sl":
                cur[c].append(t)
    out = []
    for r in rows:
        j = {k: re.sub(r"\s+", " ", " ".join(r[k])).strip(" ,") for k, _ in COLS if k != "sl"}
        if not (DATE.search(j["recognised"]) and DATE.search(j["valid"])):
            continue  # header rows or broken lines
        code = re.search(r"\(\s*([A-Z]{2,3}\s?\d{1,4}\s?/\s?\d{4})\s*\)", j["org"])
        org = re.sub(r"\(\s*(Offline|Online|[A-Z]{2,3}\s?\d{1,4}\s?/\s?\d{4})\s*\)", "", j["org"]).strip(" ,")
        d1, d2 = DATE.search(j["recognised"]).groups(), DATE.search(j["valid"]).groups()
        out.append({
            "n": r["n"], "org": re.sub(r"\s+", " ", org),
            "awbi_code": re.sub(r"\s", "", code.group(1)) if code else "",
            "centre": j["centre"], "district": j["district"], "state": j["state"],
            "recognised": f"{d1[2]}-{d1[1]}-{d1[0]}", "valid": f"{d2[2]}-{d2[1]}-{d2[0]}",
        })
    return out


if __name__ == "__main__":
    rows = parse(open(sys.argv[1], encoding="utf-8").read())
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    print(f"rows: {len(rows)}; with AWBI code: {sum(1 for r in rows if r['awbi_code'])}; "
          f"no state: {sum(1 for r in rows if not r['state'])}", file=sys.stderr)
