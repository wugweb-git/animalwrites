#!/usr/bin/env python3
"""District veterinary offices from state Animal Husbandry websites -> rows for import_listings.php.

Usage: python3 build_state_offices.py > offices.json   (fetches the pages live)

Only office details are kept: office name, district, office address, official phone (landline or the department's
CUG mobile series) and official email. Officers' names and personal mobiles are never stored.
States: UP, Maharashtra, Telangana, Karnataka, West Bengal (the ones that publish official numbers; Oct 2026).
"""
import datetime
import json
import re
import sys

import requests
from bs4 import BeautifulSoup

from places import Places

UA = {"User-Agent": "Mozilla/5.0 (compatible; AnimalWritesBot/1.0; +https://animalwrites.in)"}
TODAY = datetime.date.today().isoformat()
# District names as written on the state sites -> LGD names used by the directory.
DISTRICT_ALIAS = {
    "badaun": "Budaun", "g.b. nagar": "Gautam Buddha Nagar", "j.p. nagar": "Amroha", "raibarielly": "Rae Bareli",
    "cha. sambhajinagar": "Chhatrapati Sambhajinagar", "medchal": "Medchal Malkajgiri",
    "yadadri bhongir": "Yadadri Bhuvanagiri", "ramanagar": "Bengaluru South", "purba burdwan": "Purba Bardhaman",
}


def get(url):
    r = requests.get(url, headers=UA, timeout=60)
    r.raise_for_status()
    return BeautifulSoup(r.text, "html.parser")


def cells(tr):
    return [re.sub(r"\s+", " ", td.get_text(" ", strip=True)).strip() for td in tr.find_all("td")]


def landline(text):
    """First Indian landline (STD code + number) in the text, as published."""
    m = re.search(r"\b0\d{2,4}[-\s]?\d{6,8}\b", text or "")
    return m.group(0) if m else ""


def email(text):
    t = (text or "").replace("[at]", "@").replace("[dot]", ".").replace(" ", "")
    m = re.search(r"[\w.+-]+@[\w-]+(\.[\w-]+)+", t)
    return m.group(0).lower() if m else ""


def up():
    url = "https://animalhusb.upsdc.gov.in/Welcome/contactus"
    soup, out = get(url), []
    for t in soup.find_all("table"):
        head = [c.get_text(" ", strip=True) for c in t.find_all("tr")[0].find_all(["td", "th"])]
        if "District Name" not in head:
            continue
        for tr in t.find_all("tr")[1:]:
            c = cells(tr)
            if len(c) < 7 or not c[2]:
                continue
            district, office, cug, address, mail = c[2], landline(c[3]), re.sub(r"\D", "", c[4]), c[5], email(c[6])
            out.append(dict(state="Uttar Pradesh", district=district, name=f"Chief Veterinary Officer's Office, {district}",
                            phone=office or cug, address=address, email=mail,
                            extra=f"Official mobile (CUG): {cug}." if office and len(cug) == 10 else "",
                            source_name="UP Animal Husbandry Department", source_url=url))
    return out


def maharashtra():
    url = "https://dahd.maharashtra.gov.in/en/directory/"
    soup, out = get(url), []
    for t in soup.find_all("table"):
        for tr in t.find_all("tr")[1:]:
            c = cells(tr)
            if len(c) < 5 or not c[1].startswith("District Deputy Commissioner"):
                continue
            district = c[1].rsplit(",", 1)[-1].strip()
            out.append(dict(state="Maharashtra", district=district,
                            name=f"District Deputy Commissioner of Animal Husbandry, {district}",
                            phone=landline(c[2]) or (c[2] if re.fullmatch(r"0\d{9,11}", c[2] or "") else ""),
                            address=c[3], email=email(c[4]), extra="",
                            source_name="Maharashtra Animal Husbandry Department", source_url=url))
    return out


def telangana():
    url = "https://vahd.telangana.gov.in/DVAHOs.aspx"
    soup, out = get(url), []
    t = soup.find("table")
    for tr in t.find_all("tr"):
        c = cells(tr)
        if len(c) < 7 or not c[0].isdigit():
            continue
        district, official = c[3], re.sub(r"\D", "", c[4])
        if district.isupper() and len(district) > 3:  # institutes such as TGVBRI, not district offices
            continue  # c[5] is the officer's personal number: not stored
        out.append(dict(state="Telangana", district=district,
                        name=f"District Veterinary and Animal Husbandry Office, {district}",
                        phone=official if len(official) == 10 else "", address="", email=email(c[6]), extra="",
                        source_name="Telangana Veterinary and Animal Husbandry Department", source_url=url))
    return out


def karnataka():
    url = "https://ahvs.karnataka.gov.in/9/district-officers/en"
    soup, out = get(url), []
    t = soup.find("table")
    for tr in t.find_all("tr")[1:]:
        c = cells(tr)
        if len(c) < 4 or "Deputy Director" not in c[1]:
            continue
        m = re.search(r"Deputy Director\s*,\s*(.+)$", c[1])  # the officer's name comes before; never kept
        if not m:
            continue
        district = re.sub(r"\s+", " ", m.group(1)).strip()
        district = re.sub(r"\((Rural|Urban)\)", r"\1", district)
        out.append(dict(state="Karnataka", district=district,
                        name=f"Deputy Director, Animal Husbandry and Veterinary Services, {district}",
                        phone=landline(c[2]), address="", email=email(c[3]), extra="",  # mobile after "/" is the officer's
                        source_name="Karnataka Animal Husbandry and Veterinary Services Department", source_url=url))
    return out


def west_bengal():
    url = "https://darahwb.org/offices.php"
    soup, out = get(url), []
    t = soup.find("table")
    for tr in t.find_all("tr")[1:]:
        c = cells(tr)
        if len(c) < 6 or not c[2]:
            continue
        num = re.sub(r"\D", "", c[5])
        official = num if re.fullmatch(r"76040\d{5}", num) else ""  # department CUG series only
        out.append(dict(state="West Bengal", district=c[2],
                        name=f"District Animal Resources Development Office, {c[2]}",
                        phone=official or landline(c[3]), address=(c[3] + (" " + c[4] if c[4] else "")).strip(),
                        email="", extra="",
                        source_name="West Bengal Animal Resources Development Department", source_url=url))
    return out


def main():
    places = Places()
    rows, report, seen = [], {}, set()
    for fn in (up, maharashtra, telangana, karnataka, west_bengal):
        try:
            offices = fn()
        except Exception as exc:  # one broken site must not stop the others
            report[fn.__name__] = f"failed: {exc}"
            continue
        kept = 0
        for o in offices:
            if not o["phone"]:
                continue  # an office listing without a number doesn't help anyone reach help
            plain = re.sub(r"\s*\(.*?\)", "", o["district"]).strip()  # "Purba Medinipur (East Medinipur)"
            o["district"] = DISTRICT_ALIAS.get(plain.lower(), plain)
            st, dist = places.match(o["state"], o["district"], o["address"])
            if not st:
                continue
            area = (dist["name"] if dist else o["district"].title()) + ", " + st["name"]
            note = "Government district veterinary office: the office that runs veterinary hospitals and dispensaries in the district."
            if o["address"]:
                note += f" Office: {o['address']}."
            if o["extra"]:
                note += " " + o["extra"]
            key = f"vetoffice|{st['slug']}|{(dist or {}).get('slug') or o['district'].lower()}"
            if key in seen:
                continue  # the same office listed twice (e.g. in two regional tables)
            seen.add(key)
            rows.append({
                "key": key,
                "name": o["name"], "types": ["veterinary-office"],
                "state": st["code"], "district": dist["code"] if dist else 0, "area": area,
                "phone": o["phone"], "email": o["email"], "address_private": "",
                "content": note, "source_name": o["source_name"], "source_url": o["source_url"], "source_date": TODAY,
            })
            kept += 1
        report[fn.__name__] = f"{len(offices)} offices, {kept} with an official number imported"
    json.dump(rows, sys.stdout, ensure_ascii=False)
    print(json.dumps(report, indent=1), file=sys.stderr)


if __name__ == "__main__":
    main()
