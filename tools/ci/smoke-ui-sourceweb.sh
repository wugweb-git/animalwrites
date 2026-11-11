# SourceWeb's new screens (&ui=next; plugins/aw-sourcing/includes/sourceweb-ui.php) and the sourceweb/v1 routes
# (sourceweb-rest.php). Sourced by smoke.sh, which gives $H, $NA, $NS, the cookie jars and screen, route, post, optmd5
# and same. SourceWeb is loaded by Animal Writes Core (active), so nothing to activate.
screen aw-sourcing-menu sourceweb-app sourceweb-admin
screen aw-review sourceweb-app sourceweb-admin
screen aw-enrich-review sourceweb-app sourceweb-admin
screen "aw-sourcing&tab=settings" sourceweb-app sourceweb-admin
for r in dashboard review "review&type=aw_news" enrichment "enrichment&status=all" settings; do
  route "sourceweb/v1/$r"
done
post sourceweb/v1/dashboard/count '{}'
post sourceweb/v1/review/decide '{"action":"publish","ids":[]}'
post sourceweb/v1/review/publish-all '{"type":"","count":0}'
post sourceweb/v1/enrichment/decide '{"do":"approve","ids":[]}'
post sourceweb/v1/settings/workers '{"values":{}}'
post sourceweb/v1/settings/enrichment '{"values":{}}'
post sourceweb/v1/settings/social '{"values":{}}'
post sourceweb/v1/settings/google-key '{"key":""}'
want 200 "POST /sourceweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/dashboard/count"
# The Google key never goes out: Settings says only whether one is set.
wp option update aw_enrich_google_key ci-secret-key-abcd --quiet
want 200 "GET /sourceweb/v1/settings administrator (key set)" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/settings"
if grep -q 'ci-secret\|abcd' /tmp/rw.out; then echo "FAIL the Google key (or its tail) is in the Settings answer"; FAIL=1; else echo "ok   no key or key tail in the Settings answer"; fi
grep -q '"set":true' /tmp/rw.out && echo "ok   Settings says the key is set" || { echo "FAIL Settings does not say the key is set"; FAIL=1; }
# No setting lost: Save every group with the values the screen shows, and an empty key; nothing is written.
SW_OPTS="aw_sources aw_enrich_sources aw_enrich_live_cap aw_enrich_google_key aw_social_on aw_social_show kits_sourcing_log"
# shellcheck disable=SC2086
BEFORE=$(optmd5 $SW_OPTS)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/settings" > /tmp/sw-settings.json
python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
d = json.load(open('/tmp/sw-settings.json'))
calls = [('settings/' + g['slug'], {'values': {f['key']: f['value'] for f in g['fields']}}) for g in d['groups']]
calls.append(('settings/enrichment', {'values': {s['source']: s['on'] for s in d['services'] if 'source' in s}}))
calls.append(('settings/google-key', {'key': ''}))
bad = 0
for route, data in calls:
    out = subprocess.run(['curl', '-s', '-X', 'POST', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-H', 'Content-Type: application/json', '-d', json.dumps(data), '-w', '\n%{http_code}', r + '/' + route], capture_output=True, text=True).stdout
    body, code = out.rsplit('\n', 1)
    saved = json.loads(body).get('saved') if code == '200' else None
    ok = code == '200' and saved is False
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save ' + route + ' (saved: ' + str(saved) + ')')
    bad += not ok
groups = [g['slug'] for g in d['groups']]
print(('ok  ' if {'workers', 'enrichment'} <= set(groups) else 'FAIL') + ' settings groups: ' + ', '.join(groups))
sys.exit(1 if bad or not {'workers', 'enrichment'} <= set(groups) else 0)
PY
# shellcheck disable=SC2086
same "SourceWeb Settings" "$BEFORE" "$(optmd5 $SW_OPTS)"
wp option delete aw_enrich_google_key --quiet
if wp eval 'exit( count( array_intersect( array( "aw_sources", "aw_enrich_sources", "aw_enrich_live_cap", "aw_social_on", "aw_social_show" ), kits_settings_transfer_options() ) ) === 5 && ! in_array( "aw_enrich_google_key", kits_settings_transfer_options(), true ) ? 0 : 1 );'; then echo "ok   SourceWeb's Settings options are in the Kits export (the key is not)"; else echo "FAIL SourceWeb's options missing from the Kits export, or the key is in it"; FAIL=1; fi
