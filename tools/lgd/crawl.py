"""Fetch every directory place page listed in a snapshot (not organisation pages) and record status, <title>,
count line and canonical, one tab-separated row per URL.  python3 crawl.py snapshot.json out.tsv"""
import json, re, sys, time, urllib.request, urllib.error
from concurrent.futures import ThreadPoolExecutor

urls = [u for u in json.load(open(sys.argv[1]))["urls"] if "/directory/org/" not in u]
ck = str(int(time.time()))

def get(u):
    try:
        r = urllib.request.urlopen(urllib.request.Request(u + "?ck=" + ck, headers={"User-Agent": "AW-check"}), timeout=60)
        code, b = r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        code, b = e.code, ""
    except Exception as e:
        code, b = "ERR " + type(e).__name__, ""
    f = lambda p: (re.search(p, b) or [None, ""])[1]
    return "\t".join([u, str(code), f(r"<title>([^<]*)"), f(r'<p role="status">([^<]*)'), f(r'rel="canonical" href="([^"]*)')])

with ThreadPoolExecutor(6) as ex:
    rows = sorted(ex.map(get, urls))
open(sys.argv[2], "w").write("\n".join(rows) + "\n")
