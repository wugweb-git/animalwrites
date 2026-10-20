"""FAQ check on live pages (docs/faq.md): the FAQPage JSON-LD holds exactly the questions and answers the page shows.

    python3 tools/ci/faq_live_check.py https://animalwrites.in/directory/org/… [more URLs]
    python3 tools/ci/faq_live_check.py --file urls.txt

For each page: the accordion (kit-faq) and the FAQPage node are both there or both missing; 3 to 6 questions; no
duplicate question; each question and answer text equals the schema's (tags removed, spaces collapsed); at most one
FAQ section and one FAQPage. A cache-busting query string is added. Exit 1 when a page fails.
"""
import html
import json
import re
import sys
import urllib.request
from html.parser import HTMLParser


def norm(s):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", s or ""))).strip()


class FAQ(HTMLParser):
    """Collects the kit-faq questions (button text) and answers (text of div.kit-faq-a)."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.sections, self.items, self.mode, self.depth, self.buf = 0, [], None, 0, ""

    def handle_starttag(self, tag, attrs):
        cls = dict(attrs).get("class", "") or ""
        if tag == "section" and "kit-faq" in cls.split():
            self.sections += 1
        if self.mode:
            self.depth += tag not in ("br", "img", "input", "meta", "link")
            return
        if tag == "button" and "kit-faq-btn" in cls.split():
            self.mode, self.depth, self.buf = "q", 1, ""
        elif tag == "div" and "kit-faq-a" in cls.split():
            self.mode, self.depth, self.buf = "a", 1, ""

    def handle_endtag(self, tag):
        if not self.mode:
            return
        self.depth -= 1
        if self.depth == 0:
            if self.mode == "q":
                self.items.append([norm(self.buf), None])
            elif self.items:
                self.items[-1][1] = norm(self.buf)
            self.mode = None

    def handle_data(self, data):
        if self.mode:
            self.buf += data


def faqpages(page):
    out = []
    for m in re.finditer(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', page, re.S):
        try:
            data = json.loads(m.group(1))
        except ValueError:
            continue
        nodes = data.get("@graph", [data]) if isinstance(data, dict) else data
        out += [n for n in nodes if isinstance(n, dict) and "FAQPage" in ([n.get("@type")] if isinstance(n.get("@type"), str) else (n.get("@type") or []))]
    return out


def check(url):
    sep = "&" if "?" in url else "?"
    req = urllib.request.Request(url + sep + "faqcheck=1", headers={"User-Agent": "Mozilla/5.0 (AW FAQ check)"})
    page = urllib.request.urlopen(req, timeout=60).read().decode("utf-8", "replace")
    p = FAQ()
    p.feed(page)
    nodes = faqpages(page)
    errs = []
    if p.sections > 1 or len(nodes) > 1:
        errs.append(f"{p.sections} FAQ sections, {len(nodes)} FAQPage nodes")
    if bool(p.items) != bool(nodes):
        errs.append("FAQ shown without schema" if p.items else "FAQPage without a visible FAQ")
    if p.items and not 3 <= len(p.items) <= 6:
        errs.append(f"{len(p.items)} questions")
    if len({q.lower() for q, _ in p.items}) != len(p.items):
        errs.append("duplicate question")
    if nodes:
        schema = [(norm(q.get("name")), norm((q.get("acceptedAnswer") or {}).get("text"))) for q in nodes[0].get("mainEntity", [])]
        if [tuple(x) for x in p.items] != schema:
            for i, (v, s) in enumerate(zip(p.items, schema)):
                if tuple(v) != s:
                    errs.append(f"Q{i + 1} differs: page {v!r} / schema {s!r}")
                    break
            else:
                errs.append(f"{len(p.items)} questions on the page, {len(schema)} in the schema")
        for k in ("@id", "isPartOf", "about"):
            if not nodes[0].get(k):
                errs.append(f"FAQPage has no {k}")
    return len(p.items), errs


def main(argv):
    urls = []
    if argv[:1] == ["--file"]:
        urls = [u.strip() for u in open(argv[1]) if u.strip()]
    else:
        urls = argv
    bad = 0
    for u in urls:
        try:
            n, errs = check(u)
        except Exception as e:  # noqa: BLE001 - report and go on
            n, errs = 0, [f"fetch failed: {e}"]
        print(("FAIL " if errs else "ok   ") + f"{n} questions  {u}" + ("".join("\n     " + e for e in errs)))
        bad += bool(errs)
    print(f"{len(urls) - bad} of {len(urls)} pages pass")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
