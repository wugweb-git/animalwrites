#!/usr/bin/env python3
"""District Legal Services Authorities in the ten states whose SLSA sites give partial lists (owner, 21 Oct 2026):
Gujarat, Assam, Uttar Pradesh, Jammu & Kashmir, Himachal Pradesh, Meghalaya, Sikkim, Maharashtra, West Bengal, Delhi.
Office contacts only (same rules as build_dlsa.py): the office landline, the office email (DLSA / court / government
addresses), and an official mobile only where the state issues a numbered series to the offices (Assam, West Bengal).
Officers' names and their own mobiles are never stored; Uttar Pradesh lists only the secretaries' mobiles, so its DLSAs
get no phone. Taluk committees (TLSC/SDLSC) and High Court committees are left out.
Pages: data/sources/nalsa/slsa/ (fetched 18 Oct 2026).
Usage: python3 build_dlsa2.py data/listings.json > data/out/dlsa2.rows.json
"""
import json
import os
import re
import sys

from build_dlsa import emails, jmy, landline
from htmltables import tables
from places import Places

HERE = os.path.dirname(os.path.abspath(__file__))
S = os.path.join(HERE, "data", "sources", "nalsa", "slsa")
OFFICE = re.compile(r"(dlsa|legal|lsa|nyaya|court|\.nic\.in$|\.gov\.in$|gujarat\.gov|meghalaya\.gov|sikkim)", re.I)
GU = {"અમદાવાદ ગ્રામ્ય": "Ahmedabad", "અમરેલી": "Amreli", "આણંદ": "Anand", "અરવલ્લી": "Arvalli", "બનાસકાંઠા": "Banas Kantha",
      "ભરૂચ": "Bharuch", "ભાવનગર": "Bhavnagar", "બોટાદ": "Botad", "છોટા ઉદેપુર": "Chhotaudepur", "દાહોદ": "Dahod",
      "દેવભૂમિ દ્વારકા": "Devbhumi Dwarka", "ગાંધીનગર": "Gandhinagar", "ગીર સોમનાથ": "Gir Somnath", "જામનગર": "Jamnagar",
      "જુનાગઢ": "Junagadh", "કચ્છ": "Kachchh", "ખેડા": "Kheda", "મહેસાણા": "Mahesana", "મહીસાગર": "Mahisagar", "મોરબી": "Morbi",
      "નર્મદા": "Narmada", "નવસારી": "Navsari", "પંચમહાલ": "Panch Mahals", "પાટણ": "Patan", "પોરબંદર": "Porbandar",
      "રાજકોટ": "Rajkot", "સાબરકાંઠા": "Sabar Kantha", "સુરત": "Surat", "સુરેન્દ્રનગર": "Surendranagar", "તાપી": "Tapi",
      "વડોદરા": "Vadodara", "વલસાડ": "Valsad"}
ALIAS = {"Karimganji": "Sribhumi", "Amingaon": "Kamrup", "Kamrup": "Kamrup Metro", "South Salmara(Mancachar)": "South Salmara Mancachar",
         "Bhaderwah": "Doda", "Udampur": "Udhampur", "Badgam": "Budgam", "Kolkata": "Kolkata", "South 24 Parganas": "24 Paraganas South",
         "North 24 Parganas": "24 Paraganas North", "Burdwan": "Purba Bardhaman", "Ahmednagar": "Ahilyanagar", "Aurangabad": "Chhatrapati Sambhajinagar",
         "Buldhana": "Buldhana", "Chandrapur": "Chandrapur", "Kinnaur": "Kinnaur", "Central-II": "Central", "Badaun": "Budaun", "Shahdara": "East"}
# Two DLSAs in one LGD district keep their own names
OWN = {"Central-II": "Central-II (Rouse Avenue)", "Shahdara": "Shahdara"}


def full(phone):
    """An 11-digit landline (with its 0) or a 10-digit mobile; anything shorter is a misprint and is left out."""
    d = re.sub(r"\D", "", phone or "")
    return phone if (len(d) == 11 and d[0] == "0") or (len(d) == 10 and d[0] in "6789") else ""


def office_mail(cell):
    return next((e for e in emails(cell) if OFFICE.search(e)), "")


def rows_for(state):
    """(DLSA place name as given, landline/phone, email, address) per DLSA."""
    T = lambda f, i: tables(os.path.join(S, f))[i]
    out = []
    if state == "Gujarat":
        for r in T("gujarat__contactlist.html", 0)[1:]:
            d = r[1].split("@")[0].strip()
            if d in GU:
                lp = r[4].strip().split(",")[0].replace("O79", "079")
                lp = ("0" + lp) if re.fullmatch(r"\d{10}", lp) else landline(re.sub(r"^(\d{3,5})\s", r"\1-", lp))  # 10 digits without the 0: keep whole
                out.append((GU[d], lp, office_mail(r[5]), ""))
    elif state == "Assam":
        for r in T("assam__1.html", 0)[1:]:
            out.append((r[1].replace("DLSA", "").strip(), r[3].strip(), "", ""))
    elif state == "Uttar Pradesh":
        for r in T("uttarpradesh__2.html", 0)[1:]:
            out.append((r[1].strip().title(), "", "", ""))  # the only numbers are the secretaries' own mobiles
    elif state == "Jammu and Kashmir":
        for r in T("jammukashmir__0.html", 0)[1:]:
            if r[1].startswith("DLSA"):
                out.append((r[1][5:].strip().title(), landline(r[3].split("/")[0]), "", ""))
    elif state == "Himachal Pradesh":
        for t in tables(os.path.join(S, "himachalpradesh__1.html"))[1:12]:
            for r in t[1:]:
                if r[1].strip().startswith("Secretary") and "dlsa" in r[2].lower():
                    d = re.search(r"District Legal Services Authority,\s*([A-Za-z]+)", r[4])
                    out.append((d.group(1) if d else "", landline(r[3]), office_mail(r[2]), r[4].strip()))
    elif state == "Meghalaya":
        for r in T("meghalaya__1.html", 2)[1:]:
            out.append((r[1].split(" District")[0].strip(), "", office_mail(r[4]), ""))
    elif state == "Sikkim":
        names = {"Gangtok": "Gangtok", "Mangan": "Mangan", "Pakyong": "Pakyong", "Namchi": "Namchi", "Gyalshing": "Gyalshing", "Soreng": "Soreng"}
        for r in T("sikkim__1.html", 1)[1:]:
            out.append((names.get(r[1].strip(), r[1].strip()), landline(r[3]), office_mail(r[4]), r[5].strip()))
    elif state == "Maharashtra":
        for r in T("maharashtra__1.html", 0)[1:]:
            if r[0].startswith("District Legal Services Authority"):
                out.append((r[0].split(",")[1].strip(), landline(r[4]), office_mail(r[2]), r[5].strip()))
    elif state == "West Bengal":
        for r in T("westbengal__1.html", 0)[1:]:
            if "DLSA" in r[1]:
                d = re.search(r"A D R Centre,\s*([A-Za-z0-9 ]+?),|^([A-Za-z]+),", r[3])
                place = (d.group(1) or d.group(2)).strip() if d else ""
                out.append((place, r[4].strip(), office_mail(r[2]), r[3].strip()))
    elif state == "Delhi":
        for r in T("delhi__1.html", 0)[1:]:
            m = re.match(r"Secretary,\s*([A-Za-z -]+?),?\s*DLSA", r[1].strip())
            if m:
                out.append((m.group(1).strip(), landline("011-" + r[3].strip()) if re.fullmatch(r"\d{8}", r[3].strip()) else "", office_mail(r[2]), ""))
    return out


SRC = {"Gujarat": ("https://gujarat.nalsa.gov.in/contact-list/", "2026-10-18"), "Assam": ("https://assam.nalsa.gov.in/", "2026-10-18"),
       "Uttar Pradesh": ("https://uttarpradesh.nalsa.gov.in/", "2026-10-18"), "Jammu and Kashmir": ("https://jammukashmir.nalsa.gov.in/", "2026-10-18"),
       "Himachal Pradesh": ("https://himachalpradesh.nalsa.gov.in/", "2026-10-18"), "Meghalaya": ("https://meghalaya.nalsa.gov.in/", "2026-10-18"),
       "Sikkim": ("https://sikkim.nalsa.gov.in/", "2026-10-18"), "Maharashtra": ("https://maharashtra.nalsa.gov.in/", "2026-10-18"),
       "West Bengal": ("https://westbengal.nalsa.gov.in/", "2026-10-18"), "Delhi": ("https://delhi.nalsa.gov.in/", "2026-10-18")}


def main(listings_file):
    places = Places()
    L = [l for l in json.load(open(listings_file, encoding="utf-8")) if not l.get("merged_into")]
    legal = {(d, l["id"]) for l in L if re.search(r"legal services", l["title"], re.I) for d in l.get("districts") or []}
    have_d = {d for d, _ in legal}
    rows, report, seen = [], {}, set()
    for state, (url, on) in SRC.items():
        st = places.state(state)
        rep = report.setdefault(state, {"found": 0, "new": 0, "already listed": 0, "district not in LGD": [], "with phone": 0, "with email": 0})
        for place, phone, mail, addr in rows_for(state):
            phone = full(phone)
            rep["found"] += 1
            dist = places.district(st, ALIAS.get(place, place), place)
            if not dist:
                rep["district not in LGD"].append(place)
                continue
            own = OWN.get(place) if state == "Delhi" else None
            if (dist["code"] in have_d or dist["code"] in seen) and not own:
                rep["already listed"] += 1
                continue
            seen.add(dist["code"])
            rep["new"] += 1
            rep["with phone"] += bool(phone)
            rep["with email"] += bool(mail)
            name = f"District Legal Services Authority, {own or dist['name']}"
            rows.append({"key": f"dlsa|{st['slug']}|{dist['code']}" + ("|" + re.sub(r"\W+", "-", own.lower()).strip("-") if own else ""), "name": name, "types": ["legal-aid"], "state": st["code"],
                         "district": dist["code"], "area": f"{dist['name']}, {st['name']}", "phone": phone, "email": mail,
                         "address": addr, "address_private": "", "data_source": "nalsa",
                         "source_name": f"{state} State Legal Services Authority", "source_url": url, "source_date": on,
                         "content": (f"The District Legal Services Authority ({own or dist['name']}) gives free legal aid and advice to people who "
                                     f"qualify under the Legal Services Authorities Act, 1987." + (f" Office: {addr}." if addr else "")
                                     + f" Contact details from the {state} State Legal Services Authority, as on {jmy(on)}. "
                                     f"NALSA's national legal aid helpline is 15100.")})
    json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
    print(json.dumps(report, ensure_ascii=False, indent=1), file=sys.stderr)


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
