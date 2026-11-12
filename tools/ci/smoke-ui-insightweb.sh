# InsightWeb's new screens (sourced by smoke.sh, which gives H, NA, NS, the cookie jars and screen, route, post,
# optmd5 and same). Search Insights is loaded here whether or not InsightWeb is active (Animal Writes Core or
# RankWeb's bridge loads it), so nothing is activated.
# shellcheck shell=bash
screen aw-search-insights insightweb-app insightweb-admin
screen "aw-search-insights&tab=settings" insightweb-app insightweb-admin
# Keywords and the other tabs on the new screen, each from its own output (insightweb-tabs.php): the screen is the tab's,
# and its parts carry no markup or script.
for t in keywords pages checklist rising clarity links server; do
  screen "aw-search-insights&tab=$t" insightweb-app insightweb-admin
  want 200 "admin.php?page=aw-search-insights&tab=$t (parts)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-search-insights&tab=$t"
  if grep -q "\"screen\":\"$t\"" /tmp/rw.out && grep -o '"parts":\[.*' /tmp/rw.out | grep -q '"type":"'; then echo "ok   tab=$t: its own parts"; else echo "FAIL tab=$t: no parts for the tab"; FAIL=1; fi
  if grep -o '"parts":\[.*' /tmp/rw.out | head -c 400000 | grep -qiE '<(script|form|input|a )|javascript:'; then echo "FAIL tab=$t: markup in its parts"; FAIL=1; else echo "ok   tab=$t: parts are text and links only"; fi
done
for r in dashboard settings services "dashboard/box&key=priorities"; do
  route "insightweb/v1/$r"
done
# The Overview boxes on the Dashboard: every box answers 200 as plain parts (no markup); an unknown box is 404; the
# options stay the same.
want 404 "GET /insightweb/v1/dashboard/box unknown key" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/insightweb/v1/dashboard/box&key=nosuchbox"
BOXES_BEFORE=$(optmd5 aw_seo_settings aw_si_runs aw_si_off)
wp eval 'echo implode( " ", array_column( insightweb_box_list(), "key" ) );' > /tmp/iw-boxes.txt
python3 - "$H/?rest_route=/insightweb/v1/dashboard/box" "$NA" <<'PY' || FAIL=1
import json, re, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
keys = open('/tmp/iw-boxes.txt').read().split()
bad = 0 if len(keys) == 16 else 1
print(('ok  ' if not bad else 'FAIL') + ' %d Overview boxes on the Dashboard' % len(keys))
for k in keys:
    out = subprocess.run(['curl', '-s', '-w', '\n%{http_code}', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, r + '&key=' + k], capture_output=True, text=True).stdout
    body, code = out.rsplit('\n', 1)
    try:
        d = json.loads(body)
    except ValueError:
        d = {}
    ok = code == '200' and isinstance(d.get('blocks'), list) and not re.search(r'<[a-z/!]', json.dumps(d.get('blocks')))
    print(('ok  ' if ok else 'FAIL') + ' %s box %s: %d parts' % (code, k, len(d.get('blocks') or [])))
    bad += not ok
sys.exit(1 if bad else 0)
PY
same "InsightWeb boxes" "$BOXES_BEFORE" "$(optmd5 aw_seo_settings aw_si_runs aw_si_off)"
post insightweb/v1/dashboard/count '{}'
post insightweb/v1/settings/numbers '{"fields":{}}'
post insightweb/v1/settings/rising '{}'
want 200 "POST /insightweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/insightweb/v1/dashboard/count"
# No setting lost: Save each group exactly as the screen shows it; nothing is written and the options stay the same.
BEFORE=$(optmd5 aw_seo_settings aw_si_gdelt_on aw_si_tool_seeds)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/insightweb/v1/settings" > /tmp/iw-settings.json
python3 - "$H/?rest_route=/insightweb/v1" "$NA" <<'PY' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
groups = json.load(open('/tmp/iw-settings.json'))['groups']
bad = 0
for g in groups:
    body = {}
    fields = {f['const']: f['text'] for b in g['blocks'] for f in b['fields']}
    if fields:
        body['fields'] = fields
    if 'gdelt' in g:
        body['gdelt'] = g['gdelt']['on']
    if 'seeds' in g:
        body['seeds'] = g['seeds']['text']
    out = subprocess.run(['curl', '-s', '-w', '\n%{http_code}', '-X', 'POST', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-H', 'Content-Type: application/json', '-d', json.dumps(body), r + '/settings/' + g['slug']], capture_output=True, text=True).stdout
    text, code = out.rsplit('\n', 1)
    saved = json.loads(text).get('saved') if code == '200' else None
    ok = code == '200' and saved is False
    print(('ok  ' if ok else 'FAIL') + ' %s unchanged Save settings/%s (saved: %s)' % (code, g['slug'], saved))
    bad += not ok
slugs = [g['slug'] for g in groups]
print(('ok  ' if slugs == ['numbers', 'rising'] else 'FAIL') + ' settings groups: ' + ', '.join(slugs))
sys.exit(1 if bad or slugs != ['numbers', 'rising'] else 0)
PY
same "InsightWeb Settings" "$BEFORE" "$(optmd5 aw_seo_settings aw_si_gdelt_on aw_si_tool_seeds)"
if wp eval 'exit( count( array_intersect( array( "aw_seo_settings", "aw_si_gdelt_on", "aw_si_tool_seeds" ), kits_settings_transfer_options() ) ) === 3 ? 0 : 1 );'; then echo "ok   InsightWeb's settings are in the Kits export"; else echo "FAIL InsightWeb's settings missing from the Kits export"; FAIL=1; fi
