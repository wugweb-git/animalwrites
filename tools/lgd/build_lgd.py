"""Build plugins/animal-writes-core/data/lgd.json, the read-only place reference, from an LGD export.

    python3 tools/lgd/build_lgd.py tools/lgd/source/states.DDMonYYYY.csv tools/lgd/source/districts.DDMonYYYY.csv

LGD export: lgdirectory.gov.in (daily copies: github.com/ramSeraph/opendata, release lgd-latest-extra1).
Each place keeps its code from LGD, and its id: the ID it had in the WordPress location list (volunteer records and
caches use it), or none for a place added later (the plugin then uses 900000 + state code or 1000000 + district code). Its display name and URL name (slug) are kept from the current lgd.json, so URLs
never change when the file is replaced; on the first build they come from the WordPress location list it replaced
(tools/lgd/source/wp-locations-2026-10-08.json). A new code gets a name from LGD and a new slug.
India Post spellings that differ from LGD are in "aliases" (from tools/awbi/match.py, plus India Post's own).
After building, run tools/lgd/check.php on the site: it lists items whose codes are no longer in the file.
"""
import csv, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "..", "..", "plugins", "animal-writes-core", "data", "lgd.json")
WP = os.path.join(HERE, "source", "wp-locations-2026-10-08.json")


def key(s):
    return re.sub(r"[^a-z]", "", s.lower().replace("&", "and"))


def slugify(s, used):
    base = re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", s.lower().replace("&", "and"))).strip("-")
    slug, i = base, 2
    while slug in used:
        slug, i = f"{base}-{i}", i + 1
    used.add(slug)
    return slug


def main(states_csv, districts_csv):
    states = list(csv.DictReader(open(states_csv, encoding="utf-8-sig")))
    districts = list(csv.DictReader(open(districts_csv, encoding="utf-8-sig")))
    prev = {}
    if os.path.exists(OUT):
        old = json.load(open(OUT, encoding="utf-8"))
        prev = {("s", s["code"]): s for s in old["states"]} | {("d", d["code"]): d for d in old["districts"]}
    else:  # first build: names and slugs from the WordPress location list, matched by state and name
        terms = json.load(open(WP, encoding="utf-8"))
        by_state = {key(t["name"]): t for t in terms if t["parent"] == 0}
        for s in states:
            t = by_state.get(key(s["State Name (In English)"]))
            if t:
                prev[("s", int(s["State Code"]))] = {"name": t["name"], "slug": t["slug"], "id": t["term_id"]}
                kids = {key(k["name"]): k for k in terms if k["parent"] == t["term_id"]}
                for d in districts:
                    if d["State Code"] == s["State Code"] and key(d["District Name(In English)"]) in kids:
                        k = kids[key(d["District Name(In English)"])]
                        prev[("d", int(d["District Code"]))] = {"name": k["name"], "slug": k["slug"], "id": k["term_id"]}
    used = {p["slug"] for p in prev.values()}
    out_s, out_d, new = [], [], []
    for s in sorted(states, key=lambda r: int(r["State Code"])):
        c = int(s["State Code"])
        p = prev.get(("s", c))
        if not p:
            new.append("state " + s["State Name (In English)"])
        out_s.append({"code": c, "id": p.get("id") if p else None, "name": p["name"] if p else s["State Name (In English)"], "slug": p["slug"] if p else slugify(s["State Name (In English)"], used), "lgd": s["State Name (In English)"], "ut": s.get("State or UT") == "U"})
    for d in sorted(districts, key=lambda r: int(r["District Code"])):
        c = int(d["District Code"])
        p = prev.get(("d", c))
        if not p:
            new.append("district " + d["District Name(In English)"])
        out_d.append({"code": c, "id": p.get("id") if p else None, "state": int(d["State Code"]), "name": p["name"] if p else d["District Name(In English)"], "slug": p["slug"] if p else slugify(d["District Name(In English)"], used), "lgd": d["District Name(In English)"]})
    tables = {}
    with open(os.path.join(HERE, "..", "awbi", "match.py"), encoding="utf-8") as f:
        for line in f:
            if line.startswith(("ALIAS_STATE=", "ALIAS_DIST=")):
                exec(line, {}, tables)
    aliases = {"state": dict(tables["ALIAS_STATE"], **{"orissa": "odisha", "nct of delhi": "delhi", "delhi ncr": "delhi", "andaman and nicobar": "andaman and nicobar islands", "dadra and nagar haveli": "the dadra and nagar haveli and daman and diu", "daman and diu": "the dadra and nagar haveli and daman and diu", "chandigarh ut": "chandigarh"}),
               "district": tables["ALIAS_DIST"]}
    m = re.search(r"(\d{2}[A-Za-z]{3}\d{4})", os.path.basename(districts_csv))
    data = {"source": "Local Government Directory (lgdirectory.gov.in)", "version": m.group(1) if m else "", "states": out_s, "districts": out_d, "aliases": aliases}
    json.dump(data, open(OUT, "w", encoding="utf-8"), ensure_ascii=False, separators=(",", ":"))
    print(f"{len(out_s)} states, {len(out_d)} districts, version {data['version']}; new codes: {len(new)}", *new[:20], sep="\n")


if __name__ == "__main__":
    main(*sys.argv[1:3])
