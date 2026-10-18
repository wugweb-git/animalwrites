#!/usr/bin/env python3
"""Organisations on AWBI's list of recognised organisations as on 31 Mar 2023 that are on neither of its later lists
(31 Aug 2025, 31 May 2026) -> listings marked lapsed (owner, 18 Oct 2026): "on AWBI's list of 31 Mar 2023, not on its
current list". Gujarat ones are read from the Gujarat State Animal Welfare Board's copy of the AWBI list (15 Oct 2024),
which has clean columns. An organisation already listed (same name, same district or state) gets the code instead.

Lists (data/sources/awbi/): awbi-2023-03-31.txt (Scribd copy of "Reco List 31032023"), awbi-2025-08-31.txt,
gujarat-sawb.txt (pdftotext -layout of the Gujarat board's PDF, Wayback copy of 5 Oct 2025).
Usage: python3 build_awbi_old.py data/listings.json > data/out/awbi-old.rows.json
"""
import json
import os
import re
import sys
from difflib import SequenceMatcher

from map_web2 import name_sim
from match import clean_title, fold
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
A = os.path.join(HERE, "data", "sources", "awbi")
RX = r"(?<![A-Z])([A-Z]{2,3}\s?\d{3,4}/\s?\d{4})"
M2023 = ("AWBI list of recognised organisations (31 Mar 2023)", "https://www.scribd.com/document/654981613/Reco-List-31032023", "2023-03-31")
GUJ = ("AWBI list of recognised organisations, Gujarat (Gujarat State Animal Welfare Board, 15 Oct 2024)",
       "https://gsawb.gujarat.gov.in/writereaddata/Portal/images/pdf/List-of-AWBI-recognized%20Organizations.pdf", "2024-10-15")
STATES = {"CHATTISGARH": "Chhattisgarh", "UTTARAKHAND": "Uttarakhand", "UTTRANCHAL": "Uttarakhand", "MADHYA PRADESH": "Madhya Pradesh",
          "HIMACHAL PRADESH": "Himachal Pradesh", "HARYANA": "Haryana", "GUJARAT": "Gujarat", "DELHI": "Delhi", "NEW DELHI": "Delhi"}


# The list's spellings and towns -> LGD district names.
SPELL = {"Murena": "Morena", "Muraina": "Morena", "Narasinghpur": "Narsimhapur", "Raisan": "Raisen", "Siwani": "Seoni",
         "Jabua": "Jhabua", "Shahjapur": "Shajapur", "Chattarpur": "Chhatarpur", "Sahadol": "Shahdol", "Mandasaur": "Mandsaur",
         "Simla": "Shimla", "Punchmahal": "Panchmahal", "Sabarkanta": "Sabarkantha", "Burahanpur": "Burhanpur",
         "Udhamsingh Nagar": "Udham Singh Nagar", "Kanker": "Uttar Bastar Kanker",
         "Narsinghpur": "Narsimhapur", "Hoshangabad": "Narmadapuram", "Mehsana": "Mahesana", "Khargaon": "Khargone", "Mewat": "Nuh"}
TOWNS = {"Pantnagar": "Udham Singh Nagar", "Kashipur": "Udham Singh Nagar", "Palampur": "Kangra", "Vadnagar": "Mehsana",
         "Visnagar": "Mehsana", "Bhuj": "Kachchh", "Malia Hatina": "Junagadh", "Kapadwanj": "Kheda", "Himmatnagar": "Sabarkantha",
         "Gadarwara": "Narsimhapur", "Kareli": "Narsimhapur", "Itarsi": "Narmadapuram",
         "Gauri Kinara": "Bhind", "Kalol": "Gandhinagar", "Ujjain": "Ujjain", "Simrol": "Indore", "Badnagar": "Ujjain", "Sabalgarh": "Morena",
         "Ichawar": "Sehore", "Hanumana": "Rewa", "Teothar": "Rewa", "Teyothar": "Rewa", "Mohan Baraodiya": "Shajapur",
         "Garod": "Mandsaur", "Lakhanadaun": "Seoni", "Ambha": "Morena", "Porsa": "Morena", "Bheemgauda": "Haridwar",
         "Dhamdha": "Durg", "Durg": "Durg", "Solan": "Solan", "Mandi": "Mandi"}
# Names that run into the address in the 2023 list (read by hand from the list).
NAMES = {
    "CG005/1999": "Ujjwal Gorakshan Kendra Jai Maa Banjari Dham Trust", "CG018/2013": "Maa Bhagwati Gau Sewa Evam Sanrakshan Kendra",
    "GJ008/1987": "Hinsa Virodhak Sangh", "GJ058/1998": "Swapnabhoomi Gram Vikas Trust",
    "GJ165/2002": "Shri Mahavir Shubh-Sandesh Jivdaya Panjrapole Charitable Trust", "GJ180/2002": "Animal Help Foundation",
    "GJ191/2002": "Bhagyoday Yuva Vikas Mandal", "GJ205/2002": "Sadparivar Khadi Gramodyog Vikas Sansthan",
    "GJ223/2003": "People for Animals Valsad", "GJ256/2011": "Sadbhav Charitable Trust",
    "HP021/2004": "Shri Siddha Baba Balaknath Gau Vigyan Kendra", "MP075/1999": "Gramin Pragati Gau Seva Sadan",
    "MP111/1999": "Govardhan Gaushala", "MP133/1999": "Maa Chamunda Pratapi Gaushala Pratapdal Goraksha Samiti",
    "MP151/1999": "Shri Radhakrishna Go Manav Sewa Sanstha", "MP162/1999": "Madhya Pradesh Krishi Go Seva Sangh",
    "MP195/2000": "Jeev Daya Paryavaran Samrakshan Sanstha", "MP211/2000": "People For Animals Bhopal",
    "MP220/2001": "Shri Krishna Gaushala Harsiddhi Mandir", "MP267/2002": "Shri Saubhagya Surya Dharawar Dham Gaushala Trust",
    "MP284/2004": "Animal Cure and Care", "MP285/2004": "Shri Govind Goseva Samiti", "MP308/2005": "Suman Rani Shiksha Prasar Samiti",
    "MP326/2007": "Shri Karthikeye Jeev Jantu Kalyan Samiti", "MP343/2009": "Swami Ravindradas Gauraksha Sewa Samiti",
    "MP401/2015": "Divya Sarvangin Vikas Sewa Sansthan Samiti", "MP412/2019": "Shri Narayan Singh Bais Siksha Evam Samaj Sudhar Samiti",
    "UK008/2002": "Society for Community Involvement in Development", "GJ140/2002": "Shri Sarvodai Gaushala Gausadan Panjrapole",
    "GJ099/2000": "Shri Sunny Memorial Charitable Trust", "HP022/2005": "Madhav Gaushala and Go Vigyan Kendra",
    "MP014/1991": "Kalyan Seva Ashram Trust", "MP031/1999": "Laxmi Goseva Parishad", "MP192/2000": "Shri Krishna Gaushala Samiti",
    "MP202/2000": "Late Gendabai Smriti Goseva Sadan", "MP227/2001": "Gorakshan Sewa Sadan Samiti",
    "MP237/2002": "Shri Khandeshwari Gaushala Samiti", "MP245/2002": "Shimad Bhagwat Chintaman Ganesh Gaushala",
    "MP300/2004": "Avdhoot Surbhi Shala", "UK002/1994": "Pashu Palak Vikas Sansthan",
}
DISTRICTS = {"GJ165/2002": "Ahmedabad", "MP289/2004": "Ujjain"}  # district glued into the address


def find_district(places, st, address, name):
    a = re.sub(r"(?<=[a-z.])(?=[A-Z])", " ", address)  # "Nagar Bhopal" from "NagarBhopal"
    for k, v in SPELL.items():
        a = re.sub(r"\b" + k + r"\b", v, a)
    m = re.search(r"Dis?tt?\.?\s*[-:]?\s*([A-Z][A-Za-z ]{2,25}?)(?=[\s,.-]*\d|\s*$|,)", a)
    cands = [m.group(1)] if m else []
    cands += [w.strip() for w in re.findall(r"([A-Z][A-Za-z]+(?: [A-Z][a-z]+)?)\s*-?\s*\d{3}\s?\d{3}", a)][::-1]  # the place before the PIN
    for c in cands:
        for c2 in (c, c.split()[-1]):
            d = places.district(st, SPELL.get(c2, c2))
            if d and difflib_ok(d["name"], SPELL.get(c2, c2)):
                return d
    last = re.findall(r"([A-Z][a-z]+)\W*$", a)
    if last:
        d = places.district(st, SPELL.get(last[0], last[0]))
        if d and difflib_ok(d["name"], SPELL.get(last[0], last[0])):
            return d
    for town, dname in TOWNS.items():
        if re.search(r"\b" + town + r"\b", a):
            return places.district(st, dname)
    return places.district(st, "", a + " " + name)


def difflib_ok(a, b):
    return SequenceMatcher(None, a.lower(), b.lower()).ratio() >= 0.75 or b.lower() in a.lower()


def norm(c):
    return re.sub(r"\s", "", c).upper()


ADDR = re.compile(r"(?:,(?=\S)|(?=O/o)|(?<=[a-z.)])(?=[A-Z][a-z/.])|(?<=[A-Za-z.)])(?=\d)|\s*\n\s*|\s(?=(?:C/o|C/O|c/o|PO\b|P\.O|At\.|At\s|Vill|Village|Dist|Distt|Near|Nr\.|Post\b|O/o|Gram\b|H\.?\s?No|Plot|Opp|Ward|Teh|Tehsil|Shop|Flat|Bunglow|Bungalow|Sector|Behind|B/h|\d)))")


def split_name(text):
    """Name and address of a 2023 row: the name ends where the address starts (a cell boundary, a number or an address word)."""
    t = text.strip()
    m = ADDR.search(t, 4)  # a name has at least a few letters
    name, addr = (t[:m.start()], t[m.end():]) if m else (t, "")
    return re.sub(r"\s+", " ", name).strip(" ,-"), re.sub(r"\s+", " ", addr).strip(" ,")


def rows_2023():
    lines = open(os.path.join(A, "awbi-2023-03-31.txt"), encoding="utf-8").read().splitlines()
    out = {}
    for i, l in enumerate(lines):
        m = re.fullmatch(r"\s*(?:\d+\s*)?([A-Z]{2,3}\s?\d{3,4}/\s?\d{4})\s*(.*)", l)
        if not m:
            continue
        code, rest = norm(m.group(1)), [m.group(2)] if m.group(2).strip() else []
        state = ""
        for nxt in lines[i + 1:i + 9]:
            s = nxt.strip().upper()
            if s in STATES or s.replace("&", "AND") in STATES:
                state = STATES.get(s, s.title())
                break
            if re.fullmatch(r"\d{1,4}", s) or re.search(RX, nxt):
                break
            rest.append(nxt)
        text = ""
        for r in rest:  # a line that ends with a space ends a cell
            text += (r if not text else (("\n" if text.endswith(" ") else " ") + r))
        name, addr = split_name(text)
        out[code] = {"code": code, "name": name, "address": addr, "state": state}
    return out


def rows_gujarat():
    out = {}
    lines = open(os.path.join(A, "gujarat-sawb.txt"), encoding="utf-8").read().splitlines()
    for i, l in enumerate(lines):
        m = re.match(r"^\s*\d+\s+(GJ\d{3}/\d{4})\s+(.*)$", l)
        if not m:
            continue
        cells = [c for c in re.split(r"\s{2,}", m.group(2).strip()) if c]
        prev = [c for c in re.split(r"\s{2,}", lines[i - 1].strip()) if c] if i else []
        nxt = [c for c in re.split(r"\s{2,}", lines[i + 1].strip()) if c] if i + 1 < len(lines) else []
        name_extra = " ".join(c for c in prev[:1] if not re.match(r"^\d", c) and "GJ" not in c and len(prev) == 1)
        if len(cells) >= 3:
            name, addr = cells[0], " ".join(cells[1:-1])
        elif len(cells) == 2:
            name, addr = (name_extra, cells[0]) if name_extra else ("", cells[0])
        else:
            name, addr = name_extra, ""
        if len(nxt) == 1 and not re.match(r"^\d", nxt[0]) and "GJ" not in nxt[0] and name:
            name = f"{name} {nxt[0]}" if not name_extra else name
        out[m.group(1)] = {"code": m.group(1), "name": re.sub(r"\s+", " ", name).strip(" ,"), "address": addr.strip(" ,"), "state": "Gujarat"}
    return out


def main(listings_file):
    places = Places()
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    have = {l["awbi_code"].strip().upper() for l in L if l.get("awbi_code")}
    later = set(map(norm, re.findall(RX, open(os.path.join(A, "awbi-2025-08-31.txt"), encoding="utf-8").read())))
    r23, guj = rows_2023(), rows_gujarat()
    rows, report = [], {"new": 0, "code added": 0, "no name": 0}
    for code, r in sorted(r23.items()):
        if code in have or code in later:
            continue
        src = M2023
        if code in guj and guj[code]["name"]:
            r, src = guj[code], GUJ
        if not r["name"]:
            report["no name"] += 1
            print("no name", code, file=sys.stderr)
            continue
        st = places.state(r["state"]) if r["state"] else None
        if not st:
            st = places.state(code[:2] == "UK" and "Uttarakhand" or "")
        if code in NAMES:  # the rest of the run-on name is address
            rest = r["name"][len(NAMES[code]):] if r["name"].lower().startswith(NAMES[code][:12].lower()) else ""
            r = dict(r, name=NAMES[code], address=(rest.strip(" ,") + ", " + r["address"]).strip(" ,"))
        dist = (places.district(st, DISTRICTS[code]) if code in DISTRICTS else find_district(places, st, r["address"], r["name"])) if st else None
        name = r["name"].title() if r["name"].isupper() else r["name"]
        # Already listed (same name in the same district, or state when the district is unknown): add the code.
        hit = None
        for l in (L if dist else []):  # only with a known district: names like "Gopal Gaushala" repeat across a state
            if st and st["code"] not in (l.get("states") or []):
                continue
            if dist["code"] not in (l.get("districts") or []):
                continue
            a, b = " ".join(fold(name).split()), " ".join(fold(clean_title(l["title"])).split())
            if name_sim(name, clean_title(l["title"])) >= 0.9 and SequenceMatcher(None, a, b).ratio() >= 0.8:
                hit = l
                break
        fields = {"awbi_code": code, "awbi_list_on": src[2], "awbi_dropped": "2026-05-31", "awbi_address": r["address"]}
        if hit:
            rows.append(dict(fields, post_id=hit["id"], types=[]))
            report["code added"] += 1
            print(f"code {code} -> {hit['title']}", file=sys.stderr)
            continue
        if not st:
            report["no name"] += 1
            continue
        types = ["animal-welfare-organisation"] + (["gaushala"] if re.search(r"gau|gou|go\s?shala|goshala|gowshala|panjrapol|pinjrapol|gorakshan", name, re.I) else [])
        place = f"{dist['name']}, {st['name']}" if dist else st["name"]
        rows.append(dict(fields, key="awbi|" + code, name=name, types=types, state=st["code"], district=dist["code"] if dist else 0,
                         area=place, phone="", address_private=r["address"], data_source="", source_name=src[0], source_url=src[1],
                         source_date=src[2],
                         content=(f"{name} ({place}) was recognised by the Animal Welfare Board of India (code {code}) on its list "
                                  f"of {'15 Oct 2024' if src is GUJ else '31 Mar 2023'}. It is not on AWBI's current list (31 May 2026), "
                                  f"so its recognition may have lapsed.")))
        report["new"] += 1
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    print(json.dumps(report), file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
