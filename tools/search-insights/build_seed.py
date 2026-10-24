#!/usr/bin/env python3
"""Build the one-time Search Insights seed from the keyword map and the 21 Oct rankings baseline.

Search Insights (aw-seo-extras) seeds its keyword table once from this JSON; after that the site table is the source of
truth and keyword-map.md is regenerated from it (docs/seo/search-insights-plan.md, section 2). The site never parses
Markdown: this script does, once, here.

    python3 tools/search-insights/build_seed.py            # writes plugins/aw-seo-extras/data/search-insights-seed.json
    python3 tools/search-insights/build_seed.py --check    # exits 1 if the committed seed is out of date
"""

import argparse
import csv
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MAP = ROOT / "docs/seo/keyword-map.md"
RANKINGS = ROOT / "docs/seo/rankings-2026-10-21.csv"
OUT = ROOT / "plugins/aw-seo-extras/data/search-insights-seed.json"

# Map sections with keyword tables, and the page type each one seeds. Section 4 (item types) holds rules, not
# keywords, and section 7 (cannibalisation) holds notes; neither is seeded.
SECTIONS = {
    "1": "hub",
    "2": "directory",
    "3": "topic",
    "5": "guide",
    "6": "key-page",
    "9": "new-row",
}


# Cells that are not search phrases: "as set" (owner's wording pages), and quoted words in the gaps list that are
# guide names or context rather than searches.
NOT_KEYWORDS = {"as set"}
NOT_GAP_PHRASES = {"pdf", "who to call", "animal welfare"}


def cells(line):
    return [c.strip() for c in line.strip().strip("|").split("|")]


def strip_parens(s):
    """Drop explanatory '(…)' that follows a space; keeps '864(e)'."""
    return re.sub(r"\s+\([^()]*\)", "", s).strip()


def clean_kw(s):
    s = s.replace("**", "").strip().strip('"').strip()
    s = strip_parens(s)
    return re.sub(r"\s+", " ", s).strip().lower()


def split_list(s):
    s = s.replace("**", "")
    if s.strip() in ("", "–", "-", "(none)", "none"):
        return []
    out = []
    for part in re.split(r",|;| / ", s):
        k = clean_kw(part)
        if k and k not in ("–", "-", "none"):
            out.append(k)
    return out


def primaries(s):
    """Primary keyword cell: may hold '(secondary: …)' and several primaries split by ' / ' or ';'."""
    s = s.replace("**", "")
    extra = []
    m = re.search(r"\(secondary:\s*([^)]*)\)", s)
    if m:
        extra = split_list(m.group(1))
        s = s[: m.start()] + s[m.end():]
    s = strip_parens(s)  # Notes in brackets may hold '/' or ';' themselves.
    return [k for k in (clean_kw(p) for p in re.split(r";| / ", s)) if k and k not in NOT_KEYWORDS], extra


def priority(s):
    m = re.search(r"P([123])", s or "")
    return f"P{m.group(1)}" if m else ""


def trends(demand, kw, primary=True):
    """The Trends index of this keyword if the Demand cell gives one: '"kw" T 25', or for the primary a leading 'T 5'."""
    d = (demand or "").replace("**", "")
    m = re.match(r"\s*T\s+([\d,]+)", d)
    if m and primary:
        return int(m.group(1).replace(",", ""))
    m = re.search(r'"' + re.escape(kw) + r'"\s+T\s+([\d,]+)', d, re.I)
    return int(m.group(1).replace(",", "")) if m else None


def rank(s):
    """Live rank: '#5' → 5; 'not top 10' → '>10'; anything else → None (kept as text)."""
    s = (s or "").replace("**", "")
    m = re.match(r"\s*#(\d+)", s)
    if m:
        return int(m.group(1))
    if re.match(r"\s*not top 10", s):
        return ">10"
    return None


def page_ref(label):
    """Path and post ID found in a page label, e.g. '(#17307, /resources/dog-bite-rabies-vaccine/)'."""
    path = None
    m = re.search(r"(?:^|[\s(`,])(/(?:[a-z0-9\-{}]+/)*)(?=[\s)`,]|$)", label)
    if m and m.group(1) != "/":  # A lone '/' is a separator ("Claim / Report"); Home is set below.
        path = m.group(1)
    pid = None
    m = re.search(r"#(\d{2,6})", label)
    if m:
        pid = int(m.group(1))
    if label.strip().lower() == "home":
        path = "/"
    return path, pid


def parse_map(text):
    rows = []
    section = None
    header = None
    for line in text.splitlines():
        m = re.match(r"^## (\d+)\.", line)
        if m:
            section = m.group(1)
            header = None
            continue
        if section not in SECTIONS or not line.startswith("|"):
            header = None if not line.startswith("|") else header
            continue
        c = cells(line)
        if header is None:
            header = [h.lower() for h in c]
            continue
        if set("".join(c)) <= set("-: "):
            continue
        rec = dict(zip(header, c))
        label = c[0]
        prim_cell = next((rec[h] for h in header if h.startswith("primary")), "")
        sec_cell = next((rec[h] for h in header if h.startswith("secondary")), "")
        demand = next((rec[h] for h in header if h.startswith("demand")), "")
        rank_cell = next((rec[h] for h in header if h.startswith("rank")), "")
        prio = priority(next((rec[h] for h in header if h.startswith("priority")), ""))
        intent = rec.get("intent", "")
        prims, extra = primaries(prim_cell)
        secs = split_list(sec_cell) + extra
        # A lone 'pdf' secondary means '<primary> pdf'.
        secs = [f"{prims[0]} pdf" if s == "pdf" and prims else s for s in secs]
        path, pid = page_ref(label)
        page = {"label": re.sub(r"\s+", " ", label.replace("`", "")).strip(), "path": path, "post_id": pid}
        for i, kw in enumerate(prims):
            rows.append({
                "keyword": kw, "kind": "primary", "section": section, "page_type": SECTIONS[section],
                "page": page, "intent": intent, "priority": prio, "trends": trends(demand, kw),
                "rank": rank(rank_cell), "rank_text": rank_cell.replace("**", ""), "demand_text": demand.replace("**", ""),
            })
        for kw in secs:
            rows.append({
                "keyword": kw, "kind": "secondary", "section": section, "page_type": SECTIONS[section],
                "page": page, "intent": intent, "priority": "", "trends": trends(demand, kw, primary=False),
                "rank": None, "rank_text": "", "demand_text": "",
            })
    return rows


def parse_gaps(text):
    """Quoted search phrases in section 8 (demand gaps): tracked, no page yet. Questions are left out."""
    m = re.search(r"^## 8\..*?(?=^## 9\.)", text, re.S | re.M)
    out = []
    if not m:
        return out
    title = ""
    seen = set()
    for line in m.group(0).splitlines():
        t = re.match(r"^\d+\.\s+\*\*(.+?)\*\*", line)
        if t:
            title = t.group(1).rstrip(".")
        for q in re.findall(r'"([^"]+)"', line):
            q = clean_kw(q)
            if q and not q.endswith("?") and len(q.split()) <= 8 and q not in NOT_GAP_PHRASES and q not in seen:
                seen.add(q)
                out.append({"keyword": q, "gap": title})
    return out


def parse_rankings(path):
    out = []
    with open(path, newline="", encoding="utf-8") as fh:
        for r in csv.DictReader(fh):
            pos = r["position"].strip()
            out.append({
                "keyword": clean_kw(r["query"]), "group": r["group"], "date": "2026-10-22",
                "position": int(pos) if pos.isdigit() else None, "position_text": pos,
                "url": r["url"], "top3": r["top3"], "paa": r["paa"],
            })
    return out


def build():
    text = MAP.read_text(encoding="utf-8")
    seed = {
        "version": 1,
        "source": "docs/seo/keyword-map.md + docs/seo/rankings-2026-10-21.csv (seeded once; the site table is the source of truth after)",
        "map": parse_map(text),
        "gaps": parse_gaps(text),
        "rankings": parse_rankings(RANKINGS),
    }
    return json.dumps(seed, ensure_ascii=False, indent=1, sort_keys=True) + "\n"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true")
    a = ap.parse_args()
    out = build()
    if a.check:
        same = OUT.exists() and OUT.read_text(encoding="utf-8") == out
        print("seed up to date" if same else "seed out of date: run tools/search-insights/build_seed.py")
        return 0 if same else 1
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(out, encoding="utf-8")
    d = json.loads(out)
    print(f"{OUT.relative_to(ROOT)}: {len(d['map'])} map keywords, {len(d['gaps'])} gap phrases, {len(d['rankings'])} ranked queries")
    return 0


if __name__ == "__main__":
    sys.exit(main())
