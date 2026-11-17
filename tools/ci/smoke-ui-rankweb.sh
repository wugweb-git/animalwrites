# RankWeb's FAQ on the new screen (sourced by smoke.sh, which gives H, the cookie jars and screen): drawn from its own
# output through Kits Core's page parts (includes/admin-shell-parts.php), with no markup or script in the parts.
# Dashboard and Settings are checked in smoke.sh ("rankweb new screens").
# shellcheck shell=bash
screen aw-faq rankweb-app rankweb-admin
want 200 "admin.php?page=aw-faq (parts)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-faq"
if grep -q '"screen":"faq"' /tmp/rw.out && grep -o '"parts":\[.*' /tmp/rw.out | grep -q '"type":"listtable"' && grep -o '"parts":\[.*' /tmp/rw.out | grep -q '"type":"form"'; then echo "ok   aw-faq: its list and its FAQ box on form as parts"; else echo "FAIL aw-faq: no list or form in the parts"; FAIL=1; fi
if grep -o '"parts":\[.*' /tmp/rw.out | head -c 400000 | grep -qiE '<(script|form|input|a )|javascript:'; then echo "FAIL aw-faq: markup in its parts"; FAIL=1; else echo "ok   aw-faq: parts are text and links only"; fi
want 200 "admin.php?page=aw-faq (editor)" -b /tmp/cj-rw-ed "$H/wp-admin/admin.php?page=aw-faq"
if grep -q 'id="rankweb-app"' /tmp/rw.out; then echo "ok   aw-faq: the new screen for an editor"; else echo "FAIL aw-faq: no new screen for an editor"; FAIL=1; fi
# The Modules page: one table from GET rankweb/v1/modules (each module: switch, where it is set up, status), so the
# Overview's own HTML table is no longer sent as parts.
want 200 "admin.php?page=aw-seo-modules (Modules)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-seo-modules"
want 200 "GET /rankweb/v1/modules administrator" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/rankweb/v1/modules"
if grep -q '"key":"internal-links"' /tmp/rw.out && grep -q '"key":"analysis"' /tmp/rw.out && grep -q '"where":\[' /tmp/rw.out && grep -q '"status":{' /tmp/rw.out; then echo "ok   Modules page: every module with where and status"; else echo "FAIL Modules page: modules route lacks a module, where or status"; FAIL=1; fi
