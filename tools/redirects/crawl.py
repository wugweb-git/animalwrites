#!/usr/bin/env python3
"""Redirect crawl (audit item 18, owner 25 Oct 2026): requests every old address after a deploy and reports any 404.

Input: the CSV from `wp aw redirects-list` (path, target, code, comparison), as a file or on stdin. Only exact paths are
requested (regex rules are skipped and counted). For each old path it checks, without following redirects:
  1. the old path answers 301/302/307/308 (a 404 or 200 there is reported);
  2. its Location is the expected target (scheme, host and a trailing slash are ignored);
  3. the target itself, followed to its end, answers 200 (a 404 is reported);
  4. which layer answered: the X-Redirect-By header ("Rank Math" for Rank Math → Redirections, "Animal Writes" for the
     site's PHP redirects, "WordPress" for core). Counted per layer; with --expect-by "Rank Math" any other is reported.
Read only: GET requests, nothing is sent to the site. Note Rank Math counts a hit on each redirect it serves.

  ssh ... 'cd ~/domains/animalwrites.in/public_html && wp aw redirects-list' > /tmp/redirects.csv
  python3 tools/redirects/crawl.py /tmp/redirects.csv [--base https://animalwrites.in] [--delay 0.2] [--expect-by "Rank Math"]
  --only location/,source/   request only paths starting with these (comma list)

Exit code 1 when anything is reported.
"""
import argparse
import csv
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

UA = "aw-redirect-crawl"


class NoFollow(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):  # noqa: D401 - keep the 3xx response
        return None


def fetch(url, follow):
    opener = urllib.request.build_opener() if follow else urllib.request.build_opener(NoFollow)
    req = urllib.request.Request(url, headers={"User-Agent": UA}, method="GET")
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, r.headers.get("Location", ""), r.headers.get("X-Redirect-By", "")
    except urllib.error.HTTPError as e:
        h = e.headers or {}
        return e.code, h.get("Location", ""), h.get("X-Redirect-By", "")
    except Exception as e:  # noqa: BLE001
        return 0, str(e), ""


def norm(url, base):
    u = urllib.parse.urlsplit(urllib.parse.urljoin(base + "/", url))
    path = urllib.parse.unquote(u.path).rstrip("/") or "/"
    return path.lower() + ("?" + u.query if u.query else "")


SITE_HOSTS = ("animalwrites.in", "www.animalwrites.in")


def rebase(url, base):
    """A target on the site is requested on --base (staging or a local copy); other sites as they are."""
    u = urllib.parse.urlsplit(url)
    if u.hostname in SITE_HOSTS:
        b = urllib.parse.urlsplit(base)
        return urllib.parse.urlunsplit((b.scheme, b.netloc, u.path, u.query, ""))
    return url


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("csv", nargs="?", help="CSV from wp aw redirects-list (default: stdin)")
    ap.add_argument("--base", default="https://animalwrites.in")
    ap.add_argument("--delay", type=float, default=0.2, help="seconds between requests")
    ap.add_argument("--expect-by", default="", help='report any redirect not sent by this layer, e.g. "Rank Math"')
    ap.add_argument("--only", default="", help="comma list of path prefixes to request, e.g. location/,source/")
    a = ap.parse_args()
    base = a.base.rstrip("/")
    rows = list(csv.DictReader(open(a.csv, newline="", encoding="utf-8") if a.csv else sys.stdin))
    checked = skipped = 0
    problems = []
    targets = {}
    layers = {}
    only = tuple("/" + p.strip().lstrip("/") for p in a.only.split(",") if p.strip())
    for row in rows:
        if row.get("comparison") != "exact":
            skipped += 1
            continue
        path, want = row["path"], row["target"]
        if only and not path.startswith(only):
            continue
        checked += 1
        code, loc, by = fetch(base + urllib.parse.quote(path, safe="/%"), follow=False)
        if code in (301, 302, 307, 308):
            layers[by or "(no header)"] = layers.get(by or "(no header)", 0) + 1
            if a.expect_by and by != a.expect_by:
                problems.append(f"LAYER {path}  answered by {by or '(no header)'}, expected {a.expect_by}")
        if code not in (301, 302, 307, 308):
            problems.append(f"{code or 'ERR'}  {path}  (expected a redirect to {want}{'; ' + loc if not code else ''})")
        elif norm(loc, base) != norm(want, base):
            problems.append(f"WRONG {path}  goes to {loc}, list says {want}")
        t = rebase(urllib.parse.urljoin(base + "/", want), base)
        if t not in targets:
            targets[t] = fetch(t, follow=True)
            time.sleep(a.delay)
        tcode = targets[t][0]
        if tcode != 200:
            problems.append(f"{tcode or 'ERR'}  target {t}  (from {path})")
        time.sleep(a.delay)
    print(f"{checked} old paths checked, {len(targets)} targets, {skipped} non-exact rules skipped, {len(problems)} problems")
    print("answered by: " + (", ".join(f"{k} {v}" for k, v in sorted(layers.items())) or "none"))
    for p in problems:
        print("  " + p)
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    main()
