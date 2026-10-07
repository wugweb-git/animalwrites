#!/usr/bin/env python3
"""Animal Writes 1.0 archive helper. Reads the DECRYPTED archive JSON on stdin; never prints personal data.

  volunteers      -> JSON lines {name, email, city, role, created_at} on stdout (for `wp aw-volunteers import`)
  strip-receipts  -> the same archive JSON without receipt:* records on stdout
  count           -> counts per record type on stderr

Counts always go to stderr. Accepts a list of {key, value} rows, {"rows": [...]}, or a {key: value} object.
"""
import json
import re
import sys
from collections import Counter


def rows(data):
    if isinstance(data, dict) and isinstance(data.get("rows"), list):
        data = data["rows"]
    if isinstance(data, list):
        for r in data:
            if isinstance(r, dict) and "key" in r:
                yield r["key"], r.get("value"), r
    elif isinstance(data, dict):
        for k, v in data.items():
            yield k, v, None


def value(v):
    if isinstance(v, str):
        try:
            return json.loads(v)
        except ValueError:
            return {}
    return v if isinstance(v, dict) else {}


def kind(key):
    return str(key).split(":", 1)[0]


def main():
    cmd = sys.argv[1] if len(sys.argv) > 1 else "count"
    data = json.load(sys.stdin)
    counts = Counter(kind(k) for k, _, _ in rows(data))
    if cmd == "volunteers":
        n = 0
        for k, v, _ in rows(data):
            if kind(k) != "volunteer":
                continue
            v = value(v)
            m = re.match(r"^volunteer:(.+):[^:]*@[^:]*$", str(k))  # key = volunteer:{ISO timestamp}:{email}
            created = v.get("timestamp") or v.get("createdAt") or (m.group(1) if m else None)
            print(json.dumps({"name": v.get("name", ""), "email": v.get("email", ""), "city": v.get("city", ""),
                              "role": v.get("role", ""), "created_at": (created or "").replace("T", " ").replace("Z", "")[:19] or None},
                             ensure_ascii=False))
            n += 1
        print(f"volunteer records written: {n}", file=sys.stderr)
    elif cmd == "strip-receipts":
        if isinstance(data, dict) and isinstance(data.get("rows"), list):
            data["rows"] = [r for r in data["rows"] if kind(r.get("key", "")) != "receipt"]
        elif isinstance(data, list):
            data = [r for r in data if not (isinstance(r, dict) and kind(r.get("key", "")) == "receipt")]
        else:
            data = {k: v for k, v in data.items() if kind(k) != "receipt"}
        json.dump(data, sys.stdout, ensure_ascii=False)
        kept = Counter(kind(k) for k, _, _ in rows(data))
        print(f"before: {dict(counts)} | after: {dict(kept)}", file=sys.stderr)
    else:
        print(f"records: {dict(counts)}", file=sys.stderr)


if __name__ == "__main__":
    main()
