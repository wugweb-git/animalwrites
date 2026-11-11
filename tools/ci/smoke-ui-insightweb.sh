# InsightWeb's new screens (sourced by smoke.sh, which gives H, NA, NS, the cookie jars and screen, route, post,
# optmd5 and same). Search Insights is loaded here whether or not InsightWeb is active (Animal Writes Core or
# RankWeb's bridge loads it), so nothing is activated.
# shellcheck shell=bash
screen aw-search-insights insightweb-app insightweb-admin
screen "aw-search-insights&tab=settings" insightweb-app insightweb-admin
for r in dashboard settings services; do
  route "insightweb/v1/$r"
done
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
