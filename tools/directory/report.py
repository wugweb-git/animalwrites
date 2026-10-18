"""Dry-run report shared by the builders: counts per outcome and per type, skip reasons, samples, unsure matches.

Each builder writes data/out/<source>.rows.json (rows for import_listings.php) and data/out/<source>.report.json.
"""
import json
import os
import random
from collections import Counter

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data", "out")


class Report:
    def __init__(self, source, label, url=""):
        self.source, self.label, self.url = source, label, url
        self.found = 0
        self.outcome = Counter()      # match / new / unsure / skipped
        self.types_new = Counter()    # types on new listings
        self.types_added = Counter()  # types added to existing listings
        self.skipped = Counter()      # reason -> count
        self.samples = {"new": [], "match": [], "skipped": []}
        self.unsure = []
        self.notes = []
        self.rows = []

    def skip(self, reason, sample=None):
        self.outcome["skipped"] += 1
        self.skipped[reason] += 1
        if sample is not None:
            self.samples["skipped"].append(dict(sample, reason=reason))

    def new(self, row, sample=None):
        self.outcome["new"] += 1
        self.types_new.update(row["types"])
        self.rows.append(row)
        self.samples["new"].append(sample or row)

    def match(self, row, sample):
        self.outcome["match"] += 1
        self.types_added.update(row.get("types", []))
        self.rows.append(row)
        self.samples["match"].append(sample)

    def maybe(self, sample):
        self.outcome["unsure"] += 1
        self.unsure.append(sample)

    def write(self, n=20):
        os.makedirs(OUT, exist_ok=True)
        rnd = random.Random(18)
        samples = {k: (rnd.sample(v, n) if len(v) > n else v) for k, v in self.samples.items()}
        rep = {
            "source": self.source, "label": self.label, "url": self.url, "found": self.found,
            "outcome": dict(self.outcome), "types_new": dict(self.types_new), "types_added": dict(self.types_added),
            "skipped": dict(self.skipped), "notes": self.notes, "samples": samples, "unsure": self.unsure,
            "rows": len(self.rows),
        }
        json.dump(self.rows, open(os.path.join(OUT, self.source + ".rows.json"), "w"), ensure_ascii=False, indent=1)
        json.dump(rep, open(os.path.join(OUT, self.source + ".report.json"), "w"), ensure_ascii=False, indent=1)
        print(json.dumps({k: rep[k] for k in ("source", "found", "outcome", "types_new", "types_added", "skipped", "notes")}, ensure_ascii=False))
        return rep
