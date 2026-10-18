#!/usr/bin/env python3
"""Organisations named on four web pages the owner sent (18 Oct 2026), mapped against existing listings. Read-only.

Pages (saved in data/sources/web/): dogsindia.com animal welfare organisations (old directory), adopt-a-pet.in partner
shelters (current, with phone), infoqueenbee.com list (2016 article), awbptrust.org "NGOs in India working for animal
welfare" (2020 article). Each organisation is looked for in the directory by name (anywhere in India, since most
pages give a city at best). Output: data/sources/web/web-lists-mapped.csv and a summary.
Usage: python3 map_web_lists.py data/listings.json
"""
import csv
import html
import json
import os
import re
import sys

from match import name_similarity, clean_title

HERE = os.path.dirname(os.path.abspath(__file__))
W = os.path.join(HERE, "data", "sources", "web")
STATES = ["Andhra Pradesh", "Assam", "Bihar", "Chandigarh", "Chhattisgarh", "Delhi", "Goa", "Gujarat", "Haryana",
          "Himachal Pradesh", "Jammu", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur",
          "Meghalaya", "Mizoram", "Nagaland", "Orissa", "Odisha", "Pondicherry", "Punjab", "Rajasthan", "Sikkim",
          "Tamil Nadu", "Tamilnadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttaranchal", "Uttarakhand",
          "West Bengal", "Andaman"]


def text(f, keep_lines=True):
    t = open(os.path.join(W, f), encoding="utf-8", errors="ignore").read()
    t = re.sub(r"(?is)<(script|style|nav|header|footer)[^>]*>.*?</\1>", " ", t)
    t = re.sub(r"(?i)<br\s*/?>|</(p|div|li|tr|h\d|td|b|strong|font)>", "\n", t)
    t = html.unescape(re.sub(r"<[^>]+>", " ", t))
    lines = [re.sub(r"\s+", " ", l).strip() for l in t.split("\n")]
    return [l for l in lines if l]


def dogsindia():
    out, state = [], ""
    lines = text("dogsindia.html")
    for i, l in enumerate(lines):
        if l in STATES or l.title() in STATES:
            state = l.title()
            continue
        if not state or len(l) > 80 or re.search(r"\d{3}|ph:|e-?mail|website|fax|time", l, re.I):
            continue
        nxt = lines[i + 1] if i + 1 < len(lines) else ""
        if re.search(r"\d", nxt) and not re.search(r"^(dr\.?|mr\.?|mrs\.?|ms\.?)\b", l, re.I):
            out.append({"source": "dogsindia.com", "name": l.strip(" ,"), "place": state, "detail": nxt[:120]})
    return out


def adoptapet():
    out, lines = [], text("adoptapet.html")
    for i, l in enumerate(lines):
        if re.match(r"^\+?91\d{10}$", lines[i + 2] if i + 2 < len(lines) else "") and "," in l:
            name, _, city = l.rpartition(",")
            out.append({"source": "adopt-a-pet.in", "name": name.strip(), "place": city.strip(), "detail": lines[i + 2]})
    return out


def awbptrust():
    t = open(os.path.join(W, "awbptrust.html"), encoding="utf-8", errors="ignore").read()
    out = []
    parts = re.split(r"<h[2-4][^>]*>(.*?)</h[2-4]>", t, flags=re.S)
    for k in range(1, len(parts) - 1, 2):
        name = html.unescape(re.sub(r"<[^>]+>", "", parts[k])).replace("\xa0", " ").strip()
        body = html.unescape(re.sub(r"<[^>]+>", " ", parts[k + 1]))
        loc = re.search(r"Location\s*[–-]\s*([^\n]{2,60}?)\s{2,}|Location\s*[–-]\s*(\S+(?: \S+)?)", body)
        if loc and name and "Board of India" not in name:
            out.append({"source": "awbptrust.org", "name": name, "place": (loc.group(1) or loc.group(2)).strip(), "detail": ""})
    return out


def infoqueenbee():
    x = " ".join(text("infoqueenbee.html"))
    names = re.findall(r"\d{1,2}\.\s*[‘']([^’']{4,90})[’']", x)
    return [{"source": "infoqueenbee.com", "name": n.strip(" ‘’'/"), "place": "", "detail": ""} for n in names
            if "Animal Welfare Board" not in n and "National Institute" not in n]


def main(listings_file):
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    rows = dogsindia() + adoptapet() + awbptrust() + infoqueenbee()
    out, counts = [], {}
    for r in rows:
        best, score = None, 0.0
        for l in L:
            s = name_similarity(r["name"], clean_title(l["title"]))
            if s > score:
                best, score = l, s
        place_ok = bool(best and r["place"] and r["place"].split()[0].lower()[:5] in (best["title"] + " " + json.dumps(best.get("districts"))).lower())
        kind = "listed" if score >= 0.9 else ("maybe" if score >= 0.75 else "new")
        counts[(r["source"], kind)] = counts.get((r["source"], kind), 0) + 1
        out.append(dict(r, match=kind, score=score, listing_id=best["id"] if best and kind != "new" else "",
                        listing_title=best["title"] if best and kind != "new" else ""))
    with open(os.path.join(W, "web-lists-mapped.csv"), "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=list(out[0]))
        w.writeheader()
        w.writerows(out)
    for (s, k), n in sorted(counts.items()):
        print(s, k, n)
    for x in out:
        if x["match"] != "listed":
            print(x["match"], "|", x["source"], "|", x["name"], "|", x["place"], "|", x["listing_title"], x["score"])


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
