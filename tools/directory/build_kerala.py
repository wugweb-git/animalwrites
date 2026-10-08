#!/usr/bin/env python3
"""Kerala government veterinary institutions (Animal Husbandry Department district pages) -> import rows.

Usage: python3 build_kerala.py > kerala.json   (fetches ahd.kerala.gov.in/en/{district}/ live)

Main institutions only (hospitals, polyclinics, dispensaries, district centres); sub-centres are left out.
Kept: institution name, district, address, official email; and the official mobile where the page publishes one
in an "official" column (Malappuram). No personal names are stored.
"""
import datetime
import json
import re
import sys

import requests
from bs4 import BeautifulSoup

from places import Places

UA = {"User-Agent": "Mozilla/5.0 (compatible; AnimalWritesBot/1.0; +https://animalwrites.in)"}
DISTRICTS = ["thiruvananthapuram", "kollam", "pathanamthitta", "alappuzha", "kottayam", "idukki", "ernakulam",
             "thrissur", "palakkad", "malappuram", "kozhikode", "wayanad", "kannur", "kasaragod"]
ABBR = [  # prefix -> (full name, listing type)
    (r"^D\.?V\.?C\b", "District Veterinary Centre", "veterinary-hospital"),
    (r"^V\.?P\b|polyclinic", "Veterinary Polyclinic", "veterinary-hospital"),
    (r"^V\.?H\b|hospital", "Veterinary Hospital", "veterinary-hospital"),
    (r"^V\.?D\b|dispensary", "Veterinary Dispensary", "veterinary-dispensary"),
]


def norm(t):
    return re.sub(r"\s+", " ", (t or "").replace("\xa0", " ")).strip(" ,")


def col(head, *words):
    for i, h in enumerate(head):
        if all(w in h.lower() for w in words):
            return i
    return None


def institution(raw):
    """'VD,Thavanur' -> ('Veterinary Dispensary, Thavanur', 'veterinary-dispensary')."""
    raw = norm(raw)
    for pat, full, typ in ABBR:
        if re.search(pat, raw, re.I):
            place = re.sub(pat, "", raw, flags=re.I)
            place = re.sub(r"^(veterinary\s*)?[,.\-:\s]+", "", place, flags=re.I).strip(" ,.-")
            place = re.sub(r"\b(the|senior|veterinary|surgeon|dispensary|hospital|polyclinic)\b", "", place, flags=re.I)
            place = re.sub(r"\bpin\b.*$|\d{6}|\bP\.?\s?O\b\.?", " ", place, flags=re.I)  # address bits: PIN, P.O.
            place = re.sub(r"[^A-Za-z ]+", " ", place)
            place = " ".join(place.split()[:3]).strip()
            return (f"{full}, {place.title()}" if place else full), typ
    return None, None


def district_rows(slug):
    url = f"https://ahd.kerala.gov.in/en/{slug}/"
    soup = BeautifulSoup(requests.get(url, headers=UA, timeout=60).text, "html.parser")
    table = max(soup.find_all("table"), key=lambda t: len(t.find_all("tr")), default=None)
    if not table:
        return url, []
    trs = table.find_all("tr")
    head = [norm(c.get_text(" ")).lower() for c in trs[0].find_all(["td", "th"])]
    i_name = col(head, "institut") if col(head, "institut") is not None else 0
    i_sub = col(head, "sub")
    i_addr = col(head, "address")
    i_mail = col(head, "mail")
    i_phone = next((i for i, h in enumerate(head) if "surgeon" in h and "official" in h), None)
    if i_addr is None or i_mail is None:
        return url, []
    out, last = [], ""
    for tr in trs[1:]:
        c = [norm(td.get_text(" ")) for td in tr.find_all("td")]
        if len(c) <= max(i_addr, i_mail):
            continue
        raw = c[i_name] if i_name < len(c) else ""
        if not raw or raw == last or (i_sub is not None and i_sub < len(c) and c[i_sub] and not raw):
            continue  # sub-centre rows repeat or leave the institution cell empty
        last = raw
        name, typ = institution(raw)
        if not name:
            continue
        mail = re.search(r"[\w.+-]+@[\w-]+(\.[\w-]+)+", c[i_mail])
        phone = ""
        if i_phone is not None and i_phone < len(c):
            m = re.search(r"\b[6-9]\d{9}\b", c[i_phone])
            phone = m.group(0) if m else ""
        out.append(dict(name=name, type=typ, address=c[i_addr], email=mail.group(0).lower() if mail else "", phone=phone))
    return url, out


def main():
    places = Places()
    today = datetime.date.today().isoformat()
    rows, seen, report = [], set(), {}
    for slug in DISTRICTS:
        try:
            url, insts = district_rows(slug)
        except Exception as exc:
            report[slug] = f"failed: {exc}"
            continue
        st, dist = places.match("Kerala", slug)
        kept = 0
        for o in insts:
            key = f"kerala-vet|{slug}|{o['name'].lower()}"
            if key in seen or not dist:
                continue
            seen.add(key)
            note = f"Government veterinary institution. Address: {o['address']}." if o["address"] else "Government veterinary institution."
            rows.append({
                "key": key, "name": o["name"], "types": [o["type"]],
                "state": st["code"], "district": dist["code"], "area": f"{dist['name']}, Kerala",
                "phone": o["phone"], "email": o["email"], "address_private": "",
                "content": note, "source_name": "Kerala Animal Husbandry Department", "source_url": url, "source_date": today,
            })
            kept += 1
        report[slug] = f"{len(insts)} institutions, {kept} kept"
    json.dump(rows, sys.stdout, ensure_ascii=False)
    print(json.dumps(report, indent=1), file=sys.stderr)


if __name__ == "__main__":
    main()
