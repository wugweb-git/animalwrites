"""De-dupe for directory imports: is a row from a list already a listing?

Existing listings come from `wp eval-file export_listings.php > data/listings.json`.
Order: Darpan ID, then registration number (same state), then name similarity in the same LGD district
(name_similarity is the enrichment worker's, animal-writes-workers aw_workers/directory/model.py).

    m = Matcher("data/listings.json")
    kind, listing, score = m.find(name, state_code, district_code, darpan_id="", registration="")
    # kind: "match" (same organisation), "unsure" (a person decides; not written), "new"
"""
import json
import re
import unicodedata
from collections import defaultdict
from difflib import SequenceMatcher

MATCH = 0.9   # same district and at least this alike: the same organisation
UNSURE = 0.75  # from here to MATCH: listed for a person in the dry run, not written

_STOP = {"the", "of", "and", "for", "a", "an", "&", "in", "at", "society", "trust", "foundation", "charitable", "sangh",
         "samiti", "sanstha", "organisation", "organization", "association", "india", "indian", "regd", "registered",
         "pvt", "ltd", "private", "limited", "ngo"}


def fold(s):
    s = unicodedata.normalize("NFKD", s or "").encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9 ]+", " ", s)


def core_tokens(name):
    return [t for t in fold(name).split() if t not in _STOP and len(t) > 1]


def name_similarity(a, b):
    """0..1: how alike two organisation names are (common words like Trust or Society do not count)."""
    ta, tb = core_tokens(a), core_tokens(b)
    if not ta or not tb:
        return 0.0
    sa, sb = set(ta), set(tb)
    common = len(sa & sb)
    overlap = common / min(len(sa), len(sb))
    ratio = SequenceMatcher(None, " ".join(ta), " ".join(tb)).ratio()
    weight = 0.95 if common >= 2 or min(len(sa), len(sb)) == 1 else 0.75  # one shared word of several is weak
    return round(max(ratio, overlap * weight), 3)


def norm_reg(s):
    return re.sub(r"[^a-z0-9]", "", (s or "").lower())


def clean_title(t):
    return re.sub(r"\s*\([A-Z]{2}\d{3}/\d{2,4}\)\s*$", "", t or "")  # AWBI code in the title


class Matcher:
    def __init__(self, listings_file):
        rows = json.load(open(listings_file, encoding="utf-8"))
        by_id = {r["id"]: r for r in rows}
        self.rows = []
        for r in rows:
            if r.get("merged_into"):
                continue  # matched through the listing it went into
            self.rows.append(r)
        self.by_darpan = {norm_reg(r["darpan_id"]): r for r in self.rows if r.get("darpan_id")}
        self.by_reg = defaultdict(list)
        for r in self.rows:
            if r.get("registration"):
                self.by_reg[norm_reg(r["registration"])].append(r)
        self.by_district = defaultdict(list)
        self.by_state = defaultdict(list)
        for r in self.rows:
            for d in r.get("districts") or []:
                if d:
                    self.by_district[d].append(r)
            for s in r.get("states") or []:
                if s:
                    self.by_state[s].append(r)
        self.by_id = by_id

    def find(self, name, state, district, darpan_id="", registration=""):
        if darpan_id and norm_reg(darpan_id) in self.by_darpan:
            return "match", self.by_darpan[norm_reg(darpan_id)], 1.0
        if registration and len(norm_reg(registration)) >= 4:
            for r in self.by_reg.get(norm_reg(registration), []):
                if not state or state in (r.get("states") or []):
                    if name_similarity(name, clean_title(r["title"])) >= UNSURE:
                        return "match", r, 1.0
        best, score = None, 0.0
        pool = self.by_district.get(district, []) if district else []
        for r in pool:
            s = name_similarity(name, clean_title(r["title"]))
            if s > score:
                best, score = r, s
        if best and score >= MATCH:
            return "match", best, score
        if best and score >= UNSURE:
            return "unsure", best, score
        # Listings with no district in the state: at most "unsure".
        for r in self.by_state.get(state, []) if state else []:
            if [d for d in (r.get("districts") or []) if d]:
                continue
            s = name_similarity(name, clean_title(r["title"]))
            if s >= MATCH:
                return "unsure", r, s
        return "new", None, score
