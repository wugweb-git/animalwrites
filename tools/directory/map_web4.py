#!/usr/bin/env python3
"""Audit of the lists sent on 18 Oct 2026 (second batch) against the Directory: weforanimals.com (animal welfare
organisations, ambulances, veterinary hospitals, adoption; an old list, H.S. Dhiman), the BMC (MCGM) NGO Directory 2025
(Mumbai; animal welfare entries only), Petofy's Delhi animal NGOs and Karuna Society's wildlife rescue page.
Pages saved in data/sources/web4/. Each entry -> listed (near-identical name, same state; district when known) or new.
People (Honorary Animal Welfare Officers, individual volunteers) are not organisations and are left out.
Usage: python3 map_web4.py data/listings.json > data/sources/web4/web4-mapped.csv
"""
import csv
import glob
import html
import json
import os
import re
import sys
from difflib import SequenceMatcher

from map_web2 import name_sim
from match import clean_title, fold
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
W = os.path.join(HERE, "data", "sources", "web4")
EMAIL = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
WEB = re.compile(r"(?:https?://|www\.)[^\s,;)<\"]+", re.I)
PHONE = re.compile(r"(?<!\d)(?:\+?91[\s-]?)?(?:0?([6-9]\d{4})[\s-]?(\d{5})|(0?\d{2,4})[\s-]{1,3}(\d{6,8}))(?!\d)")
PERSON = re.compile(r"(?i)honorary|hon\.? animal|^dr\.?\s|^mr\.?\s|^mrs\.?\s|^ms\.?\s|^miss\s")
STATES = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chandigarh", "Chhattisgarh", "Delhi", "Goa", "Gujarat",
          "Haryana", "Himachal Pradesh", "Jammu and Kashmir", "Jammu & Kashmir", "Jharkhand", "Karnataka", "Kerala", "Kerla",
          "Madhya Pradesh", "Madya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Orissa", "Odisha",
          "Pondicherry", "Puducherry", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Tamilnadu", "Tripura", "Uttar Pradesh",
          "Uttaranchal", "Uttarakhand", "West Bengal", "Chandhigarh"]
FIX_STATE = {"Kerla": "Kerala", "Madya Pradesh": "Madhya Pradesh", "Orissa": "Odisha", "Pondicherry": "Puducherry",
             "Tamilnadu": "Tamil Nadu", "Uttaranchal": "Uttarakhand", "Chandhigarh": "Chandigarh", "Jammu & Kashmir": "Jammu and Kashmir"}


def plain(s):
    s = re.sub(r"\s+", " ", s)  # the page's own line breaks are not breaks
    s = re.sub(r"(?i)<br\s*/?>", "\n", s)
    s = html.unescape(re.sub(r"<[^>]+>", " ", s)).replace("\xa0", " ")
    return "\n".join(re.sub(r"[ \t]+", " ", l).strip(" ,") for l in s.split("\n") if l.strip(" ,"))


def is_state(txt):
    t = txt.strip(" .:").lower()
    return next((FIX_STATE.get(x, x) for x in STATES if t == x.lower()), None)


def wfa_entries(path, kind):
    """weforanimals pages: paragraphs; a blank paragraph, a state or city heading, or a bold name ends an entry."""
    t = open(path, encoding="latin-1").read()
    t = re.sub(r"(?is)<(script|style|iframe).*?</\1>", "", t)
    out, state, city, cur = [], "", "", []

    def flush():
        if cur:
            out.append((state, city, "\n".join(cur)))
        cur.clear()

    for m in re.finditer(r"(?is)<a\s+name=\"?([^\">]+)\"?|<p\b[^>]*>(.*?)</p>", t):
        if m.group(1):
            st = is_state(html.unescape(m.group(1)).replace("%20", " "))
            if st:
                flush()
                state, city = st, ""
            continue
        body = m.group(2) or ""
        txt = plain(body)
        if not txt:
            flush()
            continue
        if is_state(txt):
            flush()
            state, city = is_state(txt), ""
            continue
        one = "\n" not in txt and len(txt) < 35 and not re.search(r"\d|@", txt)
        if one and (re.search(r"(?i)#993366", body) or (txt.isupper() and not re.search(r"(?i)spca|pfa|s\.p\.c\.a", txt))):
            flush()
            city = txt.title()  # a city heading
            continue
        if re.search(r"(?i)\(page\s*-|next\s*&gt;|next\s*>|pages\s*:", body):
            flush()
            continue
        bolds = " ".join(plain(b) for b in re.findall(r"(?is)<b\b[^>]*>(.*?)</b>", body)).strip()
        if bolds and bolds.replace("\n", " ") == txt.replace("\n", " ") and cur and not re.match(r"(?i)activities|phone|e-?mail|website", txt):
            flush()  # a new organisation's name in bold
        cur.append(txt)
    flush()
    rows = []
    for state, city, raw in out:
        lines = [l for l in raw.split("\n") if l.strip()]
        name = re.sub(r"\s+", " ", lines[0]).strip(" ,:-")
        if len(lines) < 2 or len(name) < 4 or PERSON.search(name) or not re.search(r"\d|@", raw):
            continue
        if re.match(r"(?i)(activities|phone|email|e-mail|website|fax|mobile|ph\b|hospital:|address)", name) or re.search(r"\d{3}|(?i)picture|gallery|quotes?\b|article|wallpaper|classified", name):
            continue
        rows.append(dict(src="weforanimals " + kind, state=state, city=city, name=name, text=raw))
    return rows


def mcgm():
    """BMC NGO Directory 2025: the animal welfare entries (read by hand from the PDF table; contact persons left out)."""
    E = [("Animal Matter To Me", "Madhmarve Rd, Malad West, Mumbai 400061", "8381828384", "getinvoled@amtmindia.org", "2010"),
         ("Animal Rescue And Shelter Foundation", "3, Parth CHS, 8-A, Building R, MMRDA Colony, Poonam Nagar, Andheri East, Mumbai 400093", "9820277824", "", ""),
         ("Awaaz - Voice Of Stray Animals", "Colaba, Mumbai", "7900094345", "", "2016"),
         ("Bombay Natural History Society", "Hornbill House, Shahid Bhagat Singh Rd, opp. Lion Gate, Kala Ghoda, Fort, Mumbai 400001", "022-22821811", "info@bnhs.org", ""),
         ("Bombay Panjrapole", "Panjrapole Gaushala, Cawasji Patel Tank, Mumbai 400004", "022-22425493", "", ""),
         ("Conservation Action Trust", "5, Sahakar Bhavan, 1st Floor, LBS Road, Narayan Nagar, Ghatkopar West, Mumbai 400086", "022-25122422", "", ""),
         ("Earth Brigade Foundation", "305, Shiv Ashish Commercial Complex, 19th Rd, Chembur Gaothan, Chembur, Mumbai 400071", "9820228605", "", ""),
         ("Eco Echo", "Shiv Veer, Swami Samarth Temple Lane, Sector 5, Kandivali West, Mumbai 400067", "7977891228", "echofoundation.india@gmail.com", ""),
         ("PETA India (People for the Ethical Treatment of Animals India)", "Plot No 127/16, MHADA Colony, Andheri West, Mumbai 400061", "022-40727382", "info@petaindia.org", ""),
         ("Raksha Foundation", "Unit No. 29, Ground Floor, Laxmi Woollen Mill Estate, Shakti Mills Ln, off Dr. E. Moses Road, Mumbai 400011", "9082198551", "", ""),
         ("RAWW - Resqink Association For Wildlife Welfare", "107 B Wing, The Nest, Asha Nagar, Mulund West, Mumbai 400080", "7666680202", "raww.office@gmail.com", "2013"),
         ("Save Our Strays", "Khandelwal Apartment, 17th Rd, Khar West, Mumbai 400052", "9820141310", "saveourstraysmum@gmail.com", "2003"),
         ("Sherry And Divya Foundation", "Nav Jeevan Society, Siddharth Nagar, Kamathipura, Mumbai 400008", "1800 123 1137", "", "2018"),
         ("The Welfare Of Stray Dogs", "Jamnadas Prabhudas Chawl, Adarsh Nagar, Lower Parel, Mumbai 400013", "9819593335", "wsdindia@gmail.com", "1985"),
         ("Universal Animal Welfare Society", "Chikuwadi, Malad West, Mumbai 400095", "9673195559", "universalanimalwelfare@gmail.com", "2009"),
         ("Utkarsh Global Foundation", "Office No. 402-407, 4th Floor, Vasudev Chambers, near Sonapur Signal, Mulund West, Mumbai 400078", "9870923938", "info@utkarshglobalfoundation.org", ""),
         ("Wildlife Conservation Trust", "1103, 11th Floor, P.J. Tower, Bombay Stock Exchange Building, Dalal Street, Fort, Mumbai 400001", "022-49255555", "info@wctindia.org", "")]
    return [dict(src="BMC NGO Directory 2025", state="Maharashtra", city="Mumbai", name=n, text=f"{a}\nPhone: {p}\nEmail: {e}\nFounded: {f}")
            for n, a, p, e, f in E]


def petofy():
    t = open(os.path.join(W, "petofy.txt"), encoding="utf-8").read()
    t = t[t.find("Animal Welfare Organizations in Delhi"):t.find("Showing 20 of")]
    rows = []
    for blk in re.split(r"\n\s*Call\s*\n", t):
        lines = [l.strip() for l in blk.split("\n") if l.strip() and l.strip() not in ("View Profile", "Pet Ngos") and not l.startswith("animal shelter,")]
        lines = [l for l in lines if not l.startswith("Animal Welfare Organizations in Delhi") and not l.startswith("Dr. ")]
        if len(lines) >= 2:
            name = lines[0] if lines[0] != "Welfare Foundation" else "Kalyani Animal Welfare Foundation"
            st = "Haryana" if "Haryana" in lines[-1] else "Uttar Pradesh" if "Uttar Pradesh" in lines[-1] else "Delhi"
            rows.append(dict(src="Petofy Delhi", state=st, city="", name=name, text=lines[-1]))
    return rows


def contacts(text):
    text = re.sub(r"(?<=\d)\n(?=\d)", "", text)
    em = [e.rstrip(".").lower() for e in EMAIL.findall(text) if "weforanimals" not in e]
    web = [w.rstrip(".") for w in WEB.findall(text) if not re.search(r"weforanimals|facebook", w, re.I)]
    ph = ""
    for m in PHONE.finditer(text):
        ph = (m.group(1) + m.group(2)) if m.group(1) else f"{m.group(3)}-{m.group(4)}"
        break
    return em[0] if em else "", web[0] if web else "", ph


# Near names that are other organisations (read by hand, 18 Oct 2026)
NOT_SAME = {"People For Animals - Lucknow": "*", "Prithivi Kalyan Samiti": "*", "Life For Strays": "*", "People for Animals - Haryana": "*"}


def key(name, city=""):
    """Comparable form: PFA and SPCA spelled one way, district words dropped, words sorted; a bare "People For Animals" or
    "SPCA" takes its city."""
    k = fold(re.sub(r"\(.*?\)", " ", name)).replace("&", " and ")
    k = re.sub(r"\bs\s?p\s?c\s?a\b|society for (the )?pre?vention of cruelty (of|to) animals", " spca ", k)
    k = re.sub(r"\bpfa\b", " people for animals ", k)
    k = re.sub(r"\b(dist|district|unit|mandal|rural|chapter|head office|india|the|of|and)\b", " ", k)
    k = re.sub(r"[^a-z ]", " ", k)
    words = k.split()
    if city and set(words) <= {"people", "for", "animals", "spca", "animal", "shelter", "welfare", "association"}:
        words += fold(city).split()
    return " ".join(sorted(words))


def main(listings_file):
    places = Places()
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into") and l["status"] == "publish"]
    entries = []
    for f in sorted(glob.glob(os.path.join(W, "wfa", "*.html"))):
        kind = {"awo": "organisations", "amb": "ambulances", "vet": "veterinary hospitals", "adopt": "adoption"}[os.path.basename(f).split("-")[0]]
        entries += wfa_entries(f, kind)
    entries += mcgm() + petofy()
    w = csv.writer(sys.stdout)
    w.writerow(["source", "state", "city", "name", "match", "listing_id", "listing_title", "score", "email", "website", "phone", "text"])
    from map_web2 import GENERIC
    idx = {}
    for l in L:  # word -> listings, to compare only listings that share a distinctive word
        l["_key"] = key(clean_title(l["title"]))
        for wd in set(l["_key"].split()) - GENERIC | ({"spca"} & set(l["_key"].split())):
            idx.setdefault(wd, []).append(l)
    seen = set()
    for e in entries:
        k = (fold(e["name"]), e["state"])
        if k in seen:
            continue
        seen.add(k)
        st = places.state("", e["text"]) or (places.state(e["state"]) if e["state"] else None)  # the address's state first: page headings drift
        if st:
            e["state"] = st["name"]
        dist = places.district(st, e["city"], e["text"]) if st else None
        best, score = None, 0.0
        ek = key(e["name"], e["city"])
        cands = {l["id"]: l for wd in set(ek.split()) - GENERIC for l in idx.get(wd, [])}.values()
        for l in cands:
            if st and st["code"] not in (l.get("states") or []):
                continue
            s = SequenceMatcher(None, ek, l["_key"]).ratio()
            if dist and l.get("districts") and dist["code"] not in l["districts"] and ek != l["_key"]:
                s -= 0.15  # the same name is the same organisation; a near name in another district is not
            if s > score:
                best, score = l, s
        em, web, ph = contacts(e["text"])
        if best and e["name"] in NOT_SAME:
            score = 0.5
        match = "listed" if score >= 0.9 else "unsure" if score >= 0.75 else "new"
        w.writerow([e["src"], e["state"], e["city"], e["name"], match, best["id"] if best and match != "new" else "", clean_title(best["title"]) if best and match != "new" else "",
                    round(score, 2), em, web, ph, e["text"].replace("\n", " | ")[:400]])


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
