#!/usr/bin/env python3
"""The organisations from four web pages the owner sent that the directory did not have (map_web_lists.py and a check
by hand, 18 Oct 2026) -> new listings, for import_listings.php. Owner: import all, including the old dogsindia.com list.

"EHF (Environment & Health Foundation)" and "Chaitanya Animal Paradise (CAP)" are one entry on dogsindia.com (the
shelter and the foundation that runs it): one listing. Old phone numbers (six or seven digits, before Indian numbers
changed) are not stored. Source: the page each organisation was found on (data source `web`).
Usage: python3 build_web_lists.py > data/out/web-lists.rows.json
"""
import json
import re
import sys

from places import Places

ON = "2026-10-18"
SRC = {
    "adopt": ("adopt-a-pet.in partner shelters", "https://www.adopt-a-pet.in/partner-shelters",
              "It is listed among the partner shelters and NGOs on adopt-a-pet.in."),
    "awbp": ("AWBP Trust: NGOs in India working for animal welfare", "https://awbptrust.org/awareness/ngos-in-india-working-for-animal-welfare-and-rights",
             "It is listed in AWBP Trust's list of NGOs in India working for animal welfare and rights (2020)."),
    "dogs": ("dogsindia.com: animal welfare organisations", "https://www.dogsindia.com/animal_welfare_org.htm",
             "It is listed in the dogsindia.com directory of animal welfare organisations, an old list; the details may be out of date."),
}
# name, state, district, source, types, phone, website, address (private), what it does (from the page, or '')
ROWS = [
    ("World For All Animal Care and Adoptions", "Maharashtra", "Mumbai Suburban", "adopt", [], "9004257179", "", "Andheri East, Mumbai 400052",
     "a Mumbai-based animal welfare organisation known for its stray animal adoption drives"),
    ("Pranimal Foundation", "Maharashtra", "Pune", "adopt", [], "8669695454", "", "B56, Purandar Society, Kashid Nagar, Pimple Gurav, Pimpri-Chinchwad 411061",
     "an animal welfare organisation in Pimpri-Chinchwad, Pune, established in 2020"),
    ("Animals Water Bowl Project (AWBP) Trust", "Telangana", "Hyderabad", "awbp", [], "", "https://awbptrust.org/", "",
     "a trust started in 2017 that gives out water bowls for street animals and runs awareness programmes, in Hyderabad and other cities"),
    ("Debasree Roy Foundation", "West Bengal", "Kolkata", "awbp", [], "", "http://www.debasreeroyfoundation.org/", "",
     "a Kolkata organisation working for stray animals since 2009"),
    ("Tails N Trees", "Maharashtra", "Pune", "awbp", [], "", "https://tailsntrees.org/", "", ""),
    ("Blue Cross Society, Madhubani", "Bihar", "Madhubani", "dogs", [], "", "", "Chitra Gupta Colony, Ward No. 12, Madhubani 847211", ""),
    ("SPCA Bhuj", "Gujarat", "Kachchh", "dogs", ["rescue-group"], "", "", "B/9, Nirmal Sinhniwadi, Bhuj", ""),
    ("Bangalore SPCA", "Karnataka", "Bengaluru Urban", "dogs", ["rescue-group"], "", "", "Kasturba Road, near Queen's Circle, Bangalore 560001", ""),
    ("M.P. Ghoshala Sangh", "Madhya Pradesh", "Bhopal", "dogs", ["gaushala"], "", "", "137, Malaviyanagar, Bhopal 462001", ""),
    ("Shree Vardhaman Jivadaya", "Maharashtra", "Mumbai", "dogs", [], "", "", "200, 3rd Khumbharwada, Somji Building, Mumbai 400004", ""),
    ("Blue Cross Society of Pondicherry", "Puducherry", "Puducherry", "dogs", [], "", "", "Rajiv Gandhi College of Veterinary & Animal Sciences, Kurumbapet, Puducherry 605009",
     "based at the Rajiv Gandhi College of Veterinary & Animal Sciences, Kurumbapet"),
    ("Blue Cross Society of Pantnagar", "Uttarakhand", "Udham Singh Nagar", "dogs", [], "", "", "G.B. Pant University of Agriculture and Technology, Pantnagar",
     "based at G.B. Pant University of Agriculture and Technology, Pantnagar"),
    ("Brij Mohan Ramkali Gou Samrakshan Kendra", "Madhya Pradesh", "Bhopal", "dogs", ["gaushala"], "", "", "40, Kaziapur, Bhopal 462001", ""),
    ("Chaitanya Animal Paradise (Environment & Health Foundation)", "Delhi", "South West", "dogs", [], "9999710209", "",
     "Plot no. 55, Pocket 8, Dwarka Sector 17 C, New Delhi 110075", "an animal shelter run by the Environment & Health Foundation (EHF) in Dwarka"),
]


def main():
    places, out = Places(), []
    for name, state, district, src, types, phone, site, addr, what in ROWS:
        st = places.state(state)
        dist = places.district(st, district)
        if not dist:
            raise SystemExit(f"district not in LGD: {district}, {state}")
        label, url, said = SRC[src]
        out.append({
            "key": "web|" + re.sub(r"\W+", "-", name.lower()).strip("-"), "name": name,
            "types": ["animal-welfare-organisation"] + types, "state": st["code"], "district": dist["code"],
            "area": f"{dist['name']}, {st['name']}", "phone": phone, "website": site, "address_private": addr,
            "data_source": "web", "source_name": label, "source_url": url, "source_date": ON,
            "content": (f"{name} is {what}. " if what else f"{name} is an animal welfare organisation in {dist['name']}, {st['name']}. ") + said,
        })
    json.dump(out, sys.stdout, ensure_ascii=False, indent=1)
    print(f"rows {len(out)}", file=sys.stderr)


if __name__ == "__main__":
    main()
