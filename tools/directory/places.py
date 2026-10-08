"""State and district matching for directory imports, against the LGD file (plugins/animal-writes-core/data/lgd.json).

Places are dicts: code (LGD code), name, slug, state (the state's code; 0 for a state). Rows carry codes in
"state" and "district"; the importers store them with aw_set_item_places() (places.php), which refuses unknown codes.

Same rules as tools/awbi/match.py, whose alias tables are reused as the single source:
exact district name within the state, then a fuzzy match (cutoff 0.84) on the raw name, the name without
"district" and its first word, then a district name found in the address text.

    from places import Places
    p = Places()                      # the LGD file in the plugin
    state, district = p.match("Tamil Nadu", "Kanyakumari", address="...")   # term dicts or None
"""
import difflib
import json
import os
import re
from collections import defaultdict

_HERE = os.path.dirname(os.path.abspath(__file__))
_tables = {}
with open(os.path.join(_HERE, "..", "awbi", "match.py"), encoding="utf-8") as f:
    for line in f:
        if line.startswith(("ALIAS_STATE=", "ALIAS_DIST=")):
            exec(line, {}, _tables)  # two literal dicts only
ALIAS_STATE = dict(_tables["ALIAS_STATE"], **{"orissa": "odisha", "j&k": "jammu and kashmir", "nct of delhi": "delhi",
                                             "andaman and nicobar": "andaman and nicobar islands",
                                             "dadra and nagar haveli": "the dadra and nagar haveli and daman and diu"})
ALIAS_DIST = _tables["ALIAS_DIST"]


def norm(s):
    return re.sub(r"\s+", " ", re.sub(r"[^a-z ]", " ", (s or "").lower().replace("&", "and"))).strip()


LGD_FILE = os.path.join(_HERE, "..", "..", "plugins", "animal-writes-core", "data", "lgd.json")


class Places:
    def __init__(self, lgd_file=LGD_FILE):
        data = json.load(open(lgd_file, encoding="utf-8"))
        states = [dict(s, state=0) for s in data["states"]]
        self.states = {s["name"].lower(): s for s in states}
        self.states_norm = {norm(k): v for k, v in self.states.items()}
        self.by_code = {s["code"]: s for s in states}
        self.districts = defaultdict(dict)
        for d in data["districts"]:
            self.districts[d["state"]][norm(d["name"])] = d
            self.districts[d["state"]].setdefault(norm(d["lgd"]), d)

    def state(self, name, text=""):
        """State term from a name; if empty or unknown, the one state named in `text` (e.g. an address)."""
        key = (name or "").strip().lower()
        key = ALIAS_STATE.get(key, key)
        if key in self.states:
            return self.states[key]
        if norm(key) in self.states_norm:
            return self.states_norm[norm(key)]
        hay = norm(text + " " + (name or ""))
        hits = [t for n, t in self.states_norm.items() if re.search(r"\b" + re.escape(n) + r"\b", hay)]
        hits += [self.states_norm[norm(v)] for k, v in ALIAS_STATE.items() if re.search(r"\b" + re.escape(norm(k)) + r"\b", hay) and norm(v) in self.states_norm]
        hits = {t["code"]: t for t in hits}
        return next(iter(hits.values())) if len(hits) == 1 else None

    def district(self, state, name, address=""):
        if not state:
            return None
        names = self.districts[state["code"]]
        raw = norm(name)
        raw = ALIAS_DIST.get(raw, raw)
        if raw in names:
            return names[raw]
        for cand in (raw, raw.replace(" district", ""), raw.split(" ")[0] if raw else ""):
            if cand:
                m = difflib.get_close_matches(cand, list(names), n=1, cutoff=0.84)
                if m:
                    return names[m[0]]
        addr = norm(address)
        hits = [n for n in names if len(n) > 4 and re.search(r"\b" + re.escape(n) + r"\b", addr)]
        return names[hits[0]] if len(hits) == 1 else None

    def match(self, state_name, district_name, address=""):
        st = self.state(state_name, address + " " + (district_name or ""))
        return st, self.district(st, district_name, address)

    def district_anywhere(self, name):
        """District term by name across all states, when it is unique (for rows without a usable state)."""
        raw = ALIAS_DIST.get(norm(name), norm(name))
        hits = [d for st in self.districts.values() for n, d in st.items() if n == raw]
        return hits[0] if len(hits) == 1 else None
