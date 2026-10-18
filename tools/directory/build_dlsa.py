#!/usr/bin/env python3
"""District Legal Services Authorities (free legal aid in every district) -> Legal Aid listings, for import_listings.php.

NALSA publishes no national DLSA list (https://nalsa.gov.in/directory/ lists the 37 State authorities only). Each State
Legal Services Authority site (<state>.nalsa.gov.in) has its own DLSA table; these nine give every DLSA with an office
email or phone. The pages are saved in data/sources/nalsa/slsa/ (fetched 18 Oct 2026).

Stored: the DLSA's name, district, office address (shown, it is a public office), office email and office phone
(landline; the legal aid helpline where the state gives one; else the official mobile of the office). Officers' names
and personal emails are never stored.
Usage: python3 build_dlsa.py data/listings.json
"""
import json
import os
import re
import sys

from htmltables import tables
from match import Matcher
from places import Places
from report import Report

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "data", "sources", "nalsa", "slsa")
# state, saved file, page URL, as on, columns: district, address, email, landline, helpline, official mobile
STATES = [
    ("Andhra Pradesh", "andhrapradesh__1.html", "https://andhrapradesh.nalsa.gov.in/dlsas/", "2025-12-02", dict(d=1, a=2, e=3, l=4)),
    ("Arunachal Pradesh", "arunachalpradesh__2.html", "https://arunachalpradesh.nalsa.gov.in/secretary-of-dlsas/", "2026-06-25", dict(d=1, m=3)),
    ("Haryana", "haryana__1.html", "https://haryana.nalsa.gov.in/directory/", "2026-09-16", dict(d=1, l=3, e=4, a=6)),
    ("Karnataka", "karnataka__1.html", "https://karnataka.nalsa.gov.in/district-legal-services-authority/", "2026-07-30", dict(d=1, l=3, m=4, e=5)),
    ("Kerala", "kerala__2.html", "https://kerala.nalsa.gov.in/dlsa/", "2025-11-26", dict(d=0, e=2, m=3, l=4, a=5)),
    ("Nagaland", "nagaland__1.html", "https://nagaland.nalsa.gov.in/directory/", "2026-10-13", dict(d=1, m=3, e=4, a=5)),
    ("Rajasthan", "rajasthan__2.html", "https://rajasthan.nalsa.gov.in/secretary-of-dlsas/", "2026-10-06", dict(d=1, l=3, m=4, h=5, e=6)),
    ("Telangana", "telangana__1.html", "https://telangana.nalsa.gov.in/directory/", "2026-10-16", dict(d=1, m=3, l=4, e=5, a=6)),
    ("Uttarakhand", "uttarakhand__1.html", "https://uttarakhand.nalsa.gov.in/directory/", "2026-06-12", dict(d=1, m=3, l=4, e=5, a=6)),
]
# DLSA names that are not the LGD district name; the two Hyderabad authorities keep their own names.
ALIAS = {"East Godavari at Rajamahendravaram": "East Godavari", "Mewat": "Nuh", "Narnaul": "Mahendragarh",
         "Mangaluru (D.K)": "Dakshina Kannada", "Ramanagara": "Bengaluru South", "Karwar (U.K.)": "Uttara Kannada",
         "U. S. Nagar": "Udham Singh Nagar", "City Civil Court Legal Services Authority, Hyderabad": "Hyderabad",
         "MLSA, Hyderabad": "Hyderabad", "Jaipur District": "Jaipur", "Jaipur-Metro I": "Jaipur",
         "Jaipur-Metro II": "Jaipur", "Jodhpur-District": "Jodhpur", "Jodhpur-Metro": "Jodhpur"}
OWN_NAME = {"City Civil Court Legal Services Authority, Hyderabad": "City Civil Court Legal Services Authority, Hyderabad",
            "MLSA, Hyderabad": "Metropolitan Legal Services Authority, Hyderabad",
            "Jaipur-Metro I": "District Legal Services Authority, Jaipur Metro I",
            "Jaipur-Metro II": "District Legal Services Authority, Jaipur Metro II",
            "Jodhpur-Metro": "District Legal Services Authority, Jodhpur Metro"}
OFFICE_MAIL = re.compile(r"(dlsa|legal|lsa|slsa|nyaya|\.nic\.in$|\.gov\.in$)", re.I)


def emails(cell):
    s = re.sub(r"\s*\[\s*\]?\s*at\s*\]\s*", "@", cell or "", flags=re.I)
    s = re.sub(r"\[\s*dot\s*\]", ".", s, flags=re.I)
    found = []
    for e in re.findall(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+", s):
        e = e.lower().rstrip(".")
        if e not in found:
            found.append(e)
    return found


def landline(cell):
    """The first landline (STD code + number) in a cell."""
    m = re.search(r"\b0\d{2,4}[\s-]?\d{5,8}\b", cell or "")
    return re.sub(r"\s", "-", m.group(0)) if m else ""


def mobile(cell):
    m = re.search(r"\b[6-9]\d{4}\s?\d{5}\b", cell or "")
    return m.group(0).replace(" ", "") if m else ""


def jmy(d):
    from datetime import datetime
    return datetime.strptime(d, "%Y-%m-%d").strftime("%-d %b %Y")


def main(listings_file):
    rep = Report("nalsa", "District Legal Services Authorities (State Legal Services Authority sites)",
                 "https://nalsa.gov.in/directory/")
    places, matcher = Places(), Matcher(listings_file)
    for state_name, fname, url, on, c in STATES:
        st = places.state(state_name)
        rows = tables(os.path.join(SRC, fname))[0]
        for r in rows[1:]:
            cell = lambda k: r[c[k]] if k in c and c[k] < len(r) else ""
            dname = re.sub(r"\s*District Legal Services Authority\s*", "", cell("d"), flags=re.I).strip()
            if not dname or len(r) < 3:
                continue
            rep.found += 1
            dist = places.district(st, ALIAS.get(dname, dname))
            sample = {"state": state_name, "dlsa": dname}
            if not dist:
                rep.skip("district not in LGD", sample)
                continue
            mails = [e for e in emails(cell("e")) if OFFICE_MAIL.search(e)]
            phone = landline(cell("l")) or mobile(cell("h")) or mobile(cell("m"))
            address = cell("a")
            name = OWN_NAME.get(dname, f"District Legal Services Authority, {dist['name']}")
            fields = {"email": mails[0] if mails else "", "data_source": "nalsa",
                      "source_name": f"{state_name} State Legal Services Authority", "source_url": url, "source_date": on}
            kind, hit, score = matcher.find(name, st["code"], dist["code"])
            if kind != "new" and "legal" not in hit["title"].lower():
                kind = "new"  # a place name alone is not the same organisation
            if kind == "match":
                rep.match(dict(fields, post_id=hit["id"], types=["legal-aid"], phone=phone),
                          dict(sample, listing=hit["id"], listing_title=hit["title"], score=score))
                continue
            if kind == "unsure":
                rep.maybe(dict(sample, listing=hit["id"], listing_title=hit["title"], score=score))
                continue
            rep.new(dict(fields, key=f"dlsa|{st['slug']}|{dist['code']}" + ("|" + re.sub(r"\W+", "-", dname.lower()) if dname in OWN_NAME else ""), name=name, types=["legal-aid"],
                         state=st["code"], district=dist["code"], area=f"{dist['name']}, {st['name']}", phone=phone, address=address,
                         address_private="",
                         content=(f"The {name.split(',')[0]} ({name.split(', ', 1)[1]}) gives free legal aid and "
                                  f"advice to people who qualify under the Legal Services Authorities Act, 1987." + (f" Office: {address}." if address else "") +
                                  f" Contact details from the {state_name} State Legal Services Authority, as on {jmy(on)}. "
                                  f"NALSA's national legal aid helpline is 15100.")),
                    dict(sample, phone=phone, email=fields["email"], address=address))
    rep.write()


if __name__ == "__main__":
    main(sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "data", "listings.json"))
