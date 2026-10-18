#!/usr/bin/env python3
"""Rows from the second batch of lists (owner, 18 Oct 2026): weforanimals.com (last updated Aug 2015), BMC NGO Directory
2025, Petofy Delhi, Karuna Society's wildlife rescue page. Reads map_web4.py's CSV; the entries it could not match by name
were read by hand (SAME / NEW / skipped below).

- Listed organisations: older-source details fill empty fields only (email, website, phone); types and services from
  "Activities" are added. Emails at dead ISP domains (vsnl, sify, satyam...) are not used. Official district bodies
  (District SPCA imports) are not filled from these lists.
- New organisations: published, marked as from an older list (weforanimals) or as from the BMC directory / Petofy.
People (honorary officers, volunteers), general development NGOs, publishers, government offices and page text are skipped.
Usage: python3 build_web4.py data/listings.json > data/out/web4.rows.json
"""
import csv
import json
import os
import re
import sys

from build_activities import SERVICES, TYPES, pick
from map_web4 import contacts
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = {
    "weforanimals": ("weforanimals.com directory of animal welfare organisations (last updated Aug 2015)", "https://weforanimals.com/animal%20welfare%20organizations/animal%20welfare%20organisations.htm", "2015-08-21"),
    "BMC NGO Directory 2025": ("BMC (MCGM) NGO Directory 2025", "https://portal.mcgm.gov.in/irj/go/km/docs/documents/HomePage%20Data/NGO%20Directory%202025%20.pdf", "2025-01-01"),
    "Petofy Delhi": ("Petofy: Animal Welfare Organizations in Delhi", "https://petofy.com/delhi/pet-ngos", "2026-10-18"),
}
# Websites from these lists that still load (checked 18 Oct 2026); the other 21 are dead or now someone else's
LIVE = {"animalrescuekerala.org": "https://animalrescuekerala.org/", "love4cow.com": "http://www.love4cow.com/", "samrakshan.org": "http://samrakshan.org/"}
DEAD = re.compile(r"indiatimes|icenet|tatanagar|@rediff\.com|vsnl|sify\.com|satyam\.net|dataone|eth\.net|datainfosys|giasbm|giasdl|bol\.net|nde\.vsnl|ndb\.vsnl|1w1\.|parlis\.nic|nsysu", re.I)

# Entries matched by hand to a listing (entry name -> listing id)
SAME = {
    "Radha Govinda Gow Raksha Samithi": 282, "Visakha SPCA": 233, "People For Animals - Chandigarh": 1939, "People For Animals Chandigarh": 1939,
    "Animal India Trust": 1760, "Helping Organisation for People, Environment & Animal Trust (HOPE & Animal Trust)": 1055, "HOPE & Animal Trust": 1055,
    "Plant & Animals Welfare Society - PAWS Dombivli": 9234, "Plant & Animals Welfare Society -Mumbai (PAWS Mumbai)": 1298,
    "Animal Lovers Assocation Society - ALAS": 16463, "In Defence of Animals": 9158, "Manav Gou Sadan - A Shelter Home for Stray Cows": 1747,
    "Manav Gau Sadan Village Revala Khanpur": 1747, "Wildlife S.O.S.": 1764, "People for Animals - Haryana": 854, "Animal Rescue Kerala (ARK)": 1184,
    "Animal Welfare Association": 1238, "People For Animals - Kolkata": 3844, "The Bombay Humanitarian League (alias) Shree Mumbai Jivdaya Mandali": 1213,
    "Pasu Seva Kendra": 435, "Brooke Hospital for Animals India": 1769, "Una SPCA": 675, "Jammu & Kashmir SPCA": 1061,
    "Animal Rescue & Rehab Trust": 1075, "People For Animals - Calicut": 16369, "Kerala Animal Lovers' Assn.": 1171,
    "Kids for Tigers Mumbai - Sanctuary Asia": 9273, "Sanctuary Asia": 9273, "Sanctuary Magazine": 9273,
    "Stray Dog Lovers Assn/voice Of The Animals In Distress": 1203, "Bombay SPCA / Bai Sakarbai Dinshaw Petit Hospital for Animals": 1192,
    "People For Animals - Orissa": 1808, "Blue Cross of Pondicherry": 16453, "Help in Suffering Animal Shelter": 2009,
    "Animal Welfare & Protection Trust": 3067, "Madras/Chennai SPCA": 3023, "A gra SPCA": 3414, "Blue Cross of Pantnagar": 16454,
    "Alipurduar": 16363, "Shahanshah Jehangir, Pashu Pakshi Suraksha Sangh": 3836, "Nature's Beckon": 9220, "JBF(INDIA) Trust": 9543,
    "S.P.C.A Chandigarh": 1888, "Wildlife Trust of India": 9353, "Bhartiya Cattle Resource Development Foundation": 1733,
    "Dabar Hare Krishan Gaushala": 1748, "Kindness to Animals and Respect for Environment - KARE": 1731, "Samrakshan Trust": 1744,
    "TRAFFIC India - Regional Office": 9331, "Vegetarianism Society of Delhi": 1741, "Acharya Sushil Gau Sadan": 1742, "Goa SPCA": 666,
    "Panaji Animal Welfare Society": 667, "Kashyalye Tisk, Usgoa, Ponda, Goa , India": 665, "Kodaikanal SPCA": 3028, "Dehradun SPCA": 3305,
    "Animal Rescue And Shelter Foundation": 9059, "PETA India (People for the Ethical Treatment of Animals India)": 9240, "PETA India": 9240,
    "Friendicoes": 1726, "Society for Prevention of Cruelty to Animals": 1724, "Kazaalhelping": 9187,
}
# Keyed by (name, city) where the same name is several organisations
SAME_AT = {("People For Animals", "Calicut/Kozhikode"): 16369, ("People For Animals", "Balasore Unit-I"): 1844,
           ("People For Animals", "Morena"): 16647, ("People For Animals", "Raipur Unit-I"): 407, ("People For Animals", "Delhi"): 1752,
           ("People For Animals - Head Office", "Karol Bagh"): 9549, ("SPCA", "Imphal"): None}
# New organisations: entry name (and city) -> (name to list, state, district, extra types)
NEW = {
    ("Peoples Action in Development (PAID)", "Cuddapah"): ("Peoples Action in Development (PAID)", "Andhra Pradesh", "Y.S.R. Kadapa", []),
    ("People For Animals - Lakhimpur district chapter", "North Lakhimpur"): ("People For Animals, North Lakhimpur", "Assam", "Lakhimpur", []),
    ("Ahimsa First", ""): ("Ahimsa First", "Maharashtra", "Mumbai", []),
    ("Nature & Animal Care Foundation(NACF)", ""): ("Nature & Animal Care Foundation (NACF)", "Maharashtra", "Mumbai", []),
    ("Terra Anima Trust, Animal Welfare in the Nilgiris", "Ooty"): ("Terra Anima Trust", "Tamil Nadu", "The Nilgiris", []),
    ("Maitri Leprosy Centre Animal Care Programme", ""): ("Maitri Animal Care Programme, Bodhgaya", "Bihar", "Gaya", ["veterinary-hospital"]),
    ("ASHA Foundation", "Ahmedabad"): ("ASHA Foundation", "Gujarat", "Ahmedabad", []),
    ("Saurashtra SPCA", "Rajkot"): ("Saurashtra SPCA", "Gujarat", "Rajkot", []),
    ("People For Animals", "Vadodara"): ("People For Animals, Vadodara", "Gujarat", "Vadodara", []),
    ("People For Animals", "Bhiwani"): ("People For Animals, Bhiwani", "Haryana", "Bhiwani", []),
    ("Donkey Sanctuary", "Gurgaon"): ("The Donkey Sanctuary India", "Haryana", "Gurugram", ["sanctuary"]),
    ("Universal Compassion Movement", "Mccleodganj"): ("Universal Compassion Movement", "Himachal Pradesh", "Kangra", []),
    ("Beauty Without Cruelty - Bangalore Centre", "Bangalore"): ("Beauty Without Cruelty, Bangalore Centre", "Karnataka", "Bengaluru Urban", []),
    ("Beauty Without Cruelty - Mumbai/Bombay Centre", ""): ("Beauty Without Cruelty, Mumbai Centre", "Maharashtra", "Mumbai", []),
    ("Care for the Wild India", "Bhopal"): ("Care for the Wild India", "Madhya Pradesh", "Bhopal", []),
    ("People For Animals", "Amravati"): ("People For Animals, Amravati", "Maharashtra", "Amravati", []),
    ("Ratanlal C. Bafna Foundation Trust", "Jalgaon"): ("Ratanlal C. Bafna Foundation Trust", "Maharashtra", "Jalgaon", ["gaushala"]),
    ("Andhra Pradesh Jeeva Raksha Sangham", "Guntur"): ("Andhra Pradesh Jeeva Raksha Sangham", "Andhra Pradesh", "Guntur", []),
    ("Karuna Foundation For Animal Welfare", ""): ("Karuna Foundation for Animal Welfare", "Maharashtra", "Mumbai", []),
    ("New Bombay Animal Lovers Association", ""): ("New Bombay Animal Lovers Association", "Maharashtra", "Thane", []),
    ("Shree Jivdaya Pariwas Charitable Trust", ""): ("Shree Jivdaya Parivar Charitable Trust, Mumbai", "Maharashtra", "Mumbai", []),
    ("The Maharashtra State Federation of Goushalas & Panjarapole, (alias) Maharashtra Rajya Goushals & Panjarapole Sangh", ""):
        ("Maharashtra State Federation of Goushalas & Panjarapoles", "Maharashtra", "Mumbai", []),
    ("People For Animals", "Mizoram"): ("People For Animals, Mizoram", "Mizoram", "Aizawl", []),
    ("Society for Jeeva Seva", "Bhubaneshwar"): ("Society for Jeeva Seva", "Odisha", "Khordha", []),
    ("Wildlife Society of Orissa", "Cuttack Unit-Iii"): ("Wildlife Society of Orissa", "Odisha", "Kataka", ["wildlife-rescue"]),
    ("People For Animals", "Bhatinda Unit-Ii"): ("People For Animals, Bathinda", "Punjab", "Bathinda", []),
    ("People For Animals, BOONDI UNIT-I", "Boondi Unit-I"): ("People For Animals, Bundi", "Rajasthan", "Bundi", []),
    ("People For Animals, CHITTORGARH", "Chittorgarh"): ("People For Animals, Chittorgarh", "Rajasthan", "Chittorgarh", []),
    ("Shri Ram Kumar Panna Lal Goshala Veterinary Hospital", "Jodhpur"): ("Shri Ram Kumar Panna Lal Goshala Veterinary Hospital", "Rajasthan", "Jodhpur", ["gaushala", "veterinary-hospital"]),
    ("National Ahinsa Foundation", "Jodhpur"): ("National Ahinsa Foundation", "Rajasthan", "Jodhpur", []),
    ("Voice For Voiceless", "Pali"): ("Voice For Voiceless, Pali", "Rajasthan", "Pali", []),
    ("Save Animals Organisation", "Hyderabad"): ("Save Animals Organisation", "Telangana", "Hyderabad", []),
    ("Green Cops", "Kovvur"): ("Green Cops", "Andhra Pradesh", "East Godavari", []),
    ("People For Animals - Lucknow", "Lucknow"): ("People For Animals, Lucknow", "Uttar Pradesh", "Lucknow", []),
    ("Indian Tiger Welfare Society", "Noida"): ("Indian Tiger Welfare Society", "Uttar Pradesh", "Gautam Buddha Nagar", []),
    ("Prithivi Kalyan Samiti", "Gopeshwar"): ("Prithivi Kalyan Samiti", "Uttarakhand", "Chamoli", []),
    ("Gosewa Sankalp", "Rishikesh"): ("Gosewa Sankalp", "Uttarakhand", "Dehradun", ["gaushala"]),
    ("People For Animals", "Haldwani"): ("People For Animals, Haldwani", "Uttarakhand", "Nainital", []),
    ("People For Animals", "Berhampore (West Bengal)"): ("People For Animals, Berhampore", "West Bengal", "Murshidabad", []),
    ("Howrah SPCA", ""): ("Howrah SPCA", "West Bengal", "Howrah", []),
    ("South Calcutta Animal Welfare Society", ""): ("South Calcutta Animal Welfare Society", "West Bengal", "Kolkata", []),
    ("Kakatiya SPCA", "Warrangal"): ("Kakatiya SPCA", "Telangana", "Hanumakonda", []),
    ("Blue Cross of Tata", "Jamshedpur"): ("Blue Cross of Tata, Jamshedpur", "Jharkhand", "East Singhbum", []),
    ("People For Animals", "Patna"): ("People For Animals, Patna", "Bihar", "Patna", []),
    ("Ambikapur", ""): ("People For Animals, Ambikapur", "Chhattisgarh", "Surguja", []),
    ("Animal Saviour", "Karol Bagh"): ("Animal Saviour", "Delhi", "Central", []),
    ("Ranthambhore Foundation", "Vasant Kunj"): ("Ranthambhore Foundation", "Delhi", "South West", []),
    ("Raksha Foundation", "Mumbai"): ("Raksha Foundation", "Maharashtra", "Mumbai", []),
    ("Sherry And Divya Foundation", "Mumbai"): ("Sherry And Divya Foundation", "Maharashtra", "Mumbai", []),
    ("Wildlife Conservation Trust", "Mumbai"): ("Wildlife Conservation Trust", "Maharashtra", "Mumbai", ["wildlife-rescue"]),
    ("Umeed for Animals Foundation", ""): ("Umeed for Animals Foundation", "Haryana", "Gurugram", []),
    ("Lets Save Pets", ""): ("Lets Save Pets", "Delhi", "East", []),
    ("Life For Strays", ""): ("Life For Strays", "Delhi", "North", []),
}
KARUNA = dict(post_id=291, types=["wildlife-rescue", "veterinary-hospital"], services=["wildlife-rescue", "rescue", "treatment", "sterilisation", "vaccination", "cattle-care"],
              founded="2000")


def site(web):
    d = re.sub(r"^(https?://)?(www\.)?", "", web.lower()).split("/")[0]
    return LIVE.get(d, "")


def phone(p):
    m = re.match(r"^0?(\d{2,4})-(\d{6,8})$", p.strip())  # an STD code and a landline
    if m and len(m.group(1) + m.group(2)) == 10 and not (len(m.group(1)) == 4 and m.group(1)[0] in "6789" and p[0] != "0" and len(m.group(2)) == 6):
        return f"0{m.group(1)}-{m.group(2)}"
    d = re.sub(r"\D", "", p)
    return d if len(d) == 10 and d[0] in "6789" else ""


def src_of(source):
    return SRC["weforanimals" if source.startswith("weforanimals") else source]


def main(listings_file):
    places = Places()
    L = {l["id"]: l for l in json.load(open(listings_file, encoding="utf-8"))}
    R = list(csv.DictReader(open(os.path.join(HERE, "data", "sources", "web4", "web4-mapped.csv"), encoding="utf-8")))
    upd, new, report = {}, {}, {"filled": 0, "new": 0, "skipped": []}
    for r in R:
        name, city = r["name"], r["city"]
        lid = None
        where = city or r["state"]
        if (name, where) in SAME_AT:
            lid = SAME_AT[(name, where)]
        elif name in SAME:
            lid = SAME[name]
        elif r["match"] == "listed":
            lid = int(r["listing_id"])
        if lid:
            l = L.get(lid)
            if not l or l.get("protected") or (l.get("import_key") or "").startswith("spca|"):
                continue
            em, web, ph = contacts(r["text"].replace(" | ", "\n"))
            row = upd.setdefault(lid, {"post_id": lid, "types": [], "services": []})
            if em and not DEAD.search(em) and not l.get("email"):
                row.setdefault("email", em)
            if web and site(web) and not l.get("website"):
                row.setdefault("website", site(web))
            if ph and phone(ph) and not l.get("phones") and r["source"] != "Petofy Delhi":
                row.setdefault("phone", phone(ph))
            act = re.search(r"(?i)activities\s*:?\s*\|?\s*(.+)", r["text"])
            act = act.group(1) if act else ""
            row["types"] = sorted(set(row["types"]) | {t for t in pick(act, TYPES) if t not in l["types"]})
            row["services"] = sorted(set(row["services"]) | {s for s in pick(act, SERVICES) if s not in l["types"]})
            continue
        k = next((c for c in ((name, city), (name, r["state"]), (name, "")) if c in NEW), None)
        if not k:
            report["skipped"].append(f"{name} ({city or r['state']})")
            continue
        if k in new:
            continue
        lname, state, district, extra = NEW[k]
        st = places.state(state)
        dist = places.district(st, district)
        if not dist:
            raise SystemExit(f"district not in LGD: {district}, {state}")
        em, web, ph = contacts(r["text"].replace(" | ", "\n"))
        act = re.search(r"(?i)activities\s*:?\s*\|?\s*(.+)", r["text"])
        act = act.group(1) if act else ""
        label, url, on = src_of(r["source"])
        old = r["source"].startswith("weforanimals")
        types = ["animal-welfare-organisation"] + sorted(set(extra) | set(pick(act + " " + lname, TYPES)))
        new[k] = {"key": "web4|" + re.sub(r"\W+", "-", lname.lower()).strip("-"), "name": lname, "types": types,
                  "services": pick(act, SERVICES), "state": st["code"], "district": dist["code"], "area": f"{dist['name']}, {st['name']}",
                  "phone": phone(ph) if ph and r["source"] != "Petofy Delhi" else "", "email": em if em and not DEAD.search(em) else "",
                  "website": site(web) if web else "", "address": "",
                  "address_private": r["text"].split(" | ")[0] if r["source"] != "BMC NGO Directory 2025" else r["text"].split("\n")[0],
                  "data_source": "web", "source_name": label, "source_url": url, "source_date": on,
                  "content": (f"{lname} is an animal welfare organisation in {dist['name']}, {st['name']}. It is listed in {label}"
                              + (", an older list; the details may be out of date." if old else "."))}
    rows = [r for r in upd.values() if len(r) > 3 or r["types"] or r["services"]]
    rows.append(KARUNA)
    rows += list(new.values())
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    filled = [r for r in upd.values() if len(r) > 3]
    print(json.dumps({"listings updated": len(rows) - len(new), "with contacts filled": len(filled),
                      "with types/services added": sum(1 for r in upd.values() if r["types"] or r["services"]), "new": len(new),
                      "skipped": len(report["skipped"])}), file=sys.stderr)
    print("skipped: " + "; ".join(report["skipped"]), file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
