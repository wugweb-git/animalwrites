#!/usr/bin/env python3
"""Avyarth Commons directory (Bhopal NGOs and shelters, 2026) and ngo4you.com "Top NGO for Animals in India"
(28 May 2025) -> new listings and details for listed organisations (owner, 18 Oct 2026). Pages saved in
data/sources/web3/.

Avyarth's directory is a work in progress: several of its phone numbers are placeholders ("+91-XXXXXXXXXX",
"98273XX XXX") or look made up, so no phone is taken from it except Avyarth's own. ngo4you gives each organisation's
founding year, phone, address, email and website; for organisations already listed they fill empty fields only
(import_listings.php), and personal or parliament emails are not used.
Usage: python3 build_web3.py > data/out/web3.rows.json
"""
import json
import re
import sys

from places import Places

AVY = ("Avyarth Commons: animal welfare directory (Bhopal)", "https://commons.avyarth.org/directory/ngos", "2026-10-18")
AVY_SH = ("Avyarth Commons: shelters and recovery homes (Bhopal)", "https://commons.avyarth.org/directory/shelters", "2026-10-18")
N4Y = ("ngo4you.com: Top NGO for Animals in India", "https://www.ngo4you.com/ngo-for-animals-in-india/", "2025-05-28")

# name, state, district, source, types, phone, email, website, address (shown for an organisation's own office), what
NEW = [
    ("Stray Aid Foundation", "Madhya Pradesh", "Bhopal", AVY, ["rescue-group"], "", "", "", "", "a Bhopal NGO for rescue, sterilisation and adoption, on Kolar Road"),
    ("Avyarth Care for Animals (Foster for Furballs)", "Madhya Pradesh", "Bhopal", AVY, [], "8319841759", "contact@avyarth.org", "https://avyarth.org/", "",
     "a Bhopal non-profit for animal welfare awareness, education and community support, which runs the Avyarth Commons knowledge base"),
    ("Paw People Welfare Foundation", "Madhya Pradesh", "Bhopal", AVY, ["rescue-group", "animal-shelter"], "", "", "", "",
     "a Bhopal NGO for rescue, rehabilitation and adoption with a recovery home at Neelbadh, near Kerwa Dam"),
    ("Animals With Humanity (AWH) Bhopal", "Madhya Pradesh", "Bhopal", AVY, ["rescue-group"], "", "", "", "", "a Bhopal NGO for rescue, rehabilitation and awareness"),
    ("Rescue Routes", "Madhya Pradesh", "Bhopal", AVY, ["emergency-rescue", "rescue-group"], "", "", "", "", "a Bhopal group for emergency rescue and first aid across the city"),
    ("Scooby's Shelter", "Madhya Pradesh", "Bhopal", AVY_SH, ["animal-shelter", "rescue-group"], "", "", "", "", "a shelter in Jahangirabad, Bhopal, for rescue and medical care"),
    ("India Animal Fund", "Delhi", "New Delhi", N4Y, [], "", "info@indiaanimalfund.org", "https://indiaanimalfund.org/", "2, Aurangzeb Lane, New Delhi 110011",
     "a funder founded in 2022 that partners with animal welfare NGOs across India"),
    ("Madras Animal Rescue Society (MARS)", "Tamil Nadu", "Chengalpattu", N4Y, ["rescue-group"], "", "marstrust.india@gmail.com", "https://madrasanimalrescuesociety.org/",
     "Patta 739, Krishnan Karanai village, Tiruporur", "a rescue and rehabilitation society founded in 2019, working in Chennai and Mahabalipuram"),
]
# listing ID: fields from ngo4you (filled only where empty)
ENRICH = {
    9549: dict(phone="011-20818191", address="4-T, DCM Building, 16 Barakhamba Road, New Delhi 110001", founded="1992"),
    3024: dict(phone="9677297978", email="velu@bluecrossofindia.org", address="No 1A, Eldams Road, Alwarpet, Chennai 600018", founded="1959"),
    1726: dict(phone="011-35712913", email="friendicoes@gmail.com", address="271 & 273 Defence Colony, Jungpura Side, New Delhi 110024", founded="1979"),
    9051: dict(phone="9829843726", email="info@animalaidunlimited.org", address="Badi Village, Liyo Ka Gurha, near Badi TB Hospital, Udaipur 313011", founded="2002"),
    9274: dict(address="Raja Garden, near Shivaji College, New Delhi 110027", founded="1980"),
    9152: dict(phone="9818048398", email="strayanimals12@gmail.com", website="https://houseofstrayanimals.in/", address="HSA Animal Dispensary, Sector 54, Noida 201301", founded="2007"),
    9301: dict(phone="9730509104", email="support@sasindia.org", founded="2018"),
}


def main():
    places, rows = Places(), []
    for name, state, district, src, types, phone, email, site, addr, what in NEW:
        st = places.state(state)
        dist = places.district(st, district)
        if not dist:
            raise SystemExit(f"district not in LGD: {district}, {state}")
        rows.append({"key": "web3|" + re.sub(r"\W+", "-", name.lower()).strip("-"), "name": name,
                     "types": (["animal-welfare-organisation"] + types)[:3], "state": st["code"], "district": dist["code"],
                     "area": f"{dist['name']}, {st['name']}", "phone": phone, "email": email, "website": site, "address": addr,
                     "address_private": "", "data_source": "web", "source_name": src[0], "source_url": src[1], "source_date": src[2],
                     "content": f"{name} is {what}. It is listed in {src[0]}."})
    for lid, f in ENRICH.items():
        rows.append(dict(f, post_id=lid, types=[]))
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    print(f"new {len(NEW)}, details for {len(ENRICH)}", file=sys.stderr)


if __name__ == "__main__":
    main()
