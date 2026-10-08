"""Record what each hub shows: for every hub URL, every filter value on it, and page 2, the item links in <main> in
order, the H1, the count line and the canonical. Used before and after Part D (hub Pages).
    python3 crawl_hubs.py out.json"""
import json, re, sys, time, urllib.request, urllib.error, html
from concurrent.futures import ThreadPoolExecutor

BASE = "https://animalwrites.in"
HUBS = {"/news/": r"/news/[^/\"?#]+/", "/library/": r"/library/[^/\"?#]+/", "/resources/": r"/resources/[^/\"?#]+/",
        "/opportunities/": r"/opportunities/[^/\"?#]+/", "/campaigns/": r"/campaigns/[^/\"?#]+/", "/directory/": r"/directory/org/[^/\"?#]+/",
        "/media/": r"https?://(?!animalwrites\.in)[^\"]+"}
EXTRA = ["/directory/page/2/", "/directory/kerala/", "/directory/kerala/page/2/", "/directory/kerala/ernakulam/", "/directory/?type=gaushala",
         "/directory/kerala/?type=veterinary", "/directory/?q=blue", "/directory/?sort=details", "/directory/kerala/ernakulam/veterinary-dispensary/",
         "/directory/rajasthan/?q=gau", "/news/page/2/", "/library/page/2/", "/opportunities/page/2/", "/directory/uttar-pradesh/page/3/"]
ck = str(int(time.time()))


def get(path):
    url = BASE + path + ("&" if "?" in path else "?") + "ck=" + ck
    try:
        r = urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent": "AW-check"}), timeout=60)
        return r.status, r.read().decode("utf-8", "replace"), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, "", url


def main_html(b):
    m = re.search(r"<main.*?</main>", b, re.S)
    return m.group(0) if m else ""


def record(path, pat):
    code, b, final = get(path)
    m = main_html(b)
    items = []
    for h in re.findall(r'href="([^"]+)"', m):
        h = html.unescape(h).replace(BASE, "")
        if re.fullmatch(pat, h) and "/page/" not in h and h not in items:
            items.append(h)
    f = lambda p: html.unescape((re.search(p, b, re.S) or [None, ""])[1]).strip()
    h1 = re.sub(r"<[^>]+>", "", f(r"<h1[^>]*>(.*?)</h1>"))
    return {"status": code, "h1": h1, "count": f(r'class="aw-section-count">([^<]*)'), "status_line": f(r'<p role="status">([^<]*)'),
            "canonical": f(r'rel="canonical" href="([^"]*)'), "title": f(r"<title>([^<]*)"), "items": items,
            "filters": sorted(set(html.unescape(x).replace(BASE, "") for x in re.findall(r'class="kit-filter-pill[^"]*"[^>]*href="([^"]+)"|href="([^"]+)"[^>]*class="kit-filter-pill', b) for x in x if x))}


def main(out):
    jobs = dict(HUBS)
    res = {}
    with ThreadPoolExecutor(6) as ex:
        first = dict(zip(HUBS, ex.map(lambda p: record(p, HUBS[p]), HUBS)))
    res.update(first)
    for hub, r in first.items():
        for fl in r["filters"]:
            jobs[fl] = HUBS[hub]
    for e in EXTRA:
        hub = next(h for h in HUBS if e.startswith(h))
        jobs[e] = HUBS[hub]
    rest = [p for p in jobs if p not in res]
    with ThreadPoolExecutor(6) as ex:
        res.update(dict(zip(rest, ex.map(lambda p: record(p, jobs[p]), rest))))
    json.dump(res, open(out, "w"), indent=1, ensure_ascii=False)
    print(len(res), "urls;", sum(1 for r in res.values() if r["status"] != 200), "not 200")


main(sys.argv[1])
