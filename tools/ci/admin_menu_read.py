#!/usr/bin/env python3
"""Read the WordPress admin menu from a saved wp-admin page (tools/ci/smoke.sh).

    python3 tools/ci/admin_menu_read.py PAGE.html MENU_ID

MENU_ID is a top-level item's id, e.g. menu-plugins or menu-appearance. Prints three lines:
    open <yes|no>          the item is the lit one (WordPress's wp-has-current-submenu)
    current <slug>         the page= value of its lit sub-item ("-" when none)
    items <slug> ...       the page= values (or addresses) of its sub-items, in order
and a fourth, the ids of every top-level plugin menu on the page (li id="toplevel_page_…"):
    toplevel <id> ...
"""
import html
import re
import sys


def slug(href):
    href = html.unescape(href)
    m = re.search(r"admin\.php\?page=([^&'\"#]+(?:&tab=[^&'\"#]+)?)", href)
    return m.group(1) if m else href


def main():
    page = open(sys.argv[1], encoding="utf-8", errors="replace").read()
    menu_id = sys.argv[2]
    li = re.search(r'<li class="([^"]*)" id="' + re.escape(menu_id) + r'">(.*?)</ul>', page, re.S)
    if not li:
        print("open no\ncurrent -\nitems")
    else:
        sub = li.group(2).split("<ul class='wp-submenu wp-submenu-wrap'>", 1)
        links = re.findall(r"<a href='([^']*)'([^>]*)>", sub[1]) if len(sub) > 1 else []
        cur = [slug(h) for h, attrs in links if 'aria-current="page"' in attrs]
        print("open " + ("yes" if "wp-has-current-submenu" in li.group(1) else "no"))
        print("current " + (cur[0] if cur else "-"))
        print("items " + " ".join(slug(h) for h, _ in links))
    print("toplevel " + " ".join(re.findall(r'<li class="[^"]*" id="(toplevel_page_[^"]+)"', page)))


if __name__ == "__main__":
    main()
