# SourceWeb's new screens (&ui=next; plugins/aw-sourcing/includes/sourceweb-ui.php) and the sourceweb/v1 routes
# (sourceweb-rest.php). Sourced by smoke.sh, which gives $H, $NA, $NS, the cookie jars and screen, route, post, optmd5
# and same. SourceWeb is loaded by Animal Writes Core (active), so nothing to activate.
screen aw-sourcing-menu sourceweb-app sourceweb-admin
screen aw-review sourceweb-app sourceweb-admin
screen aw-enrich-review sourceweb-app sourceweb-admin
screen "aw-sourcing&tab=settings" sourceweb-app sourceweb-admin
screen "aw-sourcing&tab=workers" sourceweb-app sourceweb-admin
screen "aw-sourcing&tab=ai" sourceweb-app sourceweb-admin
screen "aw-sourcing" sourceweb-app sourceweb-admin
# The feed form stays on today's screen at its own address until it is rebuilt.
want 200 "admin.php?page=aw-sourcing&feed=new (feed form)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-sourcing&feed=new"
if grep -q 'id="sourceweb-app"' /tmp/rw.out; then echo "FAIL feed form: drawn as the new screen"; FAIL=1; else echo "ok   feed form: today's screen"; fi
for r in dashboard review "review&type=aw_news" enrichment "enrichment&status=all" settings workers "workers&view=planned&search=x&orderby=status&order=desc" workers/new workers/sourcing workers/sourcing/runs sources "sources&view=block&orderby=items&order=desc" sources/one sources/feeds "sources/feeds&state=on" ai ai/keys "ai/key&id=new" ai/overview; do
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
post sourceweb/v1/workers/save '{"edit":"sourcing"}'
post sourceweb/v1/workers/delete '{"id":"sourcing"}'
post sourceweb/v1/workers/run '{"id":"sourcing"}'
post sourceweb/v1/sources/decide '{"action":"block","hosts":[]}'
post sourceweb/v1/sources/short '{"short":{}}'
post sourceweb/v1/sources/save '{"host":""}'
post sourceweb/v1/sources/feeds/decide '{"op":"on","ids":[]}'
post sourceweb/v1/ai/keys/add '{"keys":""}'
post sourceweb/v1/ai/key/save '{"id":"x"}'
post sourceweb/v1/ai/keys/decide '{"op":"recheck","ids":[]}'
# Workers: a registry worker can't be deleted; an unchanged Save of every worker writes nothing.
want 400 "POST /sourceweb/v1/workers/delete a registry worker" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"id":"sourcing"}' "$H/?rest_route=/sourceweb/v1/workers/delete"
W_OPTS="aw_workers_meta aw_workers_groups aw_ai_models kits_sourcing_log"
# shellcheck disable=SC2086
BEFORE=$(optmd5 $W_OPTS)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/workers" > /tmp/sw-workers.json
python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY2' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
def call(route, data=None):
    args = ['curl', '-s', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-w', '\n%{http_code}']
    if data is not None:
        args += ['-X', 'POST', '-H', 'Content-Type: application/json', '-d', json.dumps(data)]
    body, code = subprocess.run(args + [r + '/' + route], capture_output=True, text=True).stdout.rsplit('\n', 1)
    return code, json.loads(body)
lst = json.load(open('/tmp/sw-workers.json'))
bad = 0
ids = [w['id'] for w in lst['rows']]
print(('ok  ' if ids else 'FAIL') + ' workers list: %d workers, %d views' % (len(ids), len(lst['views'])))
bad += not ids
for wid in ids[:5]:
    code, w = call('workers/' + wid)
    data = {k: w[k] for k in ('name', 'what', 'group', 'late', 'schedule')}
    data.update({'edit': wid, 'keys': [k['id'] for k in w['keys'] if k['on']], 'keysShown': bool(w['keys'])})
    code, res = call('workers/save', data)
    ok = code == '200' and res.get('done') == 'same'
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save of worker ' + wid + ' (' + str(res.get('done')) + ')')
    bad += not ok
sys.exit(1 if bad else 0)
PY2
# shellcheck disable=SC2086
same "SourceWeb Workers" "$BEFORE" "$(optmd5 $W_OPTS)"
# AI models: no response carries a saved key; empty actions, an empty Add, an unknown key and an unchanged Save of
# the saved keys write nothing.
A_OPTS="aw_ai_models kits_sourcing_log"
wp eval 'echo wp_json_encode( array_values( wp_list_pluck( aw_ai_rows(), "key" ) ) );' > /tmp/sw-ai-keys.json
# shellcheck disable=SC2086
BEFORE=$(optmd5 $A_OPTS)
python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY2' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
def call(route, data=None):
    args = ['curl', '-s', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-w', '\n%{http_code}']
    if data is not None:
        args += ['-X', 'POST', '-H', 'Content-Type: application/json', '-d', json.dumps(data)]
    body, code = subprocess.run(args + [r + '/' + route], capture_output=True, text=True).stdout.rsplit('\n', 1)
    return code, body
secrets = [k for k in json.load(open('/tmp/sw-ai-keys.json')) if len(k) > 8]
bad = 0
bodies = {}
for route in ['ai', 'ai/keys', 'ai/keys&view=bad&search=x&orderby=checked&order=desc', 'ai/key&id=new', 'ai/overview']:
    code, body = call(route)
    bodies[route] = body
    leak = [k[-4:] for k in secrets if k in body]
    ok = code == '200' and not leak
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' GET ' + route + (' (key in the answer: ...' + ', ...'.join(leak) + ')' if leak else ''))
    bad += not ok
lst = json.loads(bodies['ai/keys'])
print('ok   ai keys list: %d keys, %d views, plain: %s' % (lst['total'], len(lst['views']), lst['plain']))
for route, data, want in [
    ('ai/keys/add', {'keys': '', 'label': 'x'}, '400'),
    ('ai/key/save', {'id': 'nosuchkey', 'label': 'x'}, '404'),
    ('ai/keys/decide', {'op': 'attach', 'ids': [], 'worker': ''}, '400'),
    ('ai/keys/decide', {'op': 'remove', 'ids': []}, '200'),
    ('ai/keys/decide', {'op': 'up', 'ids': ['nosuchkey']}, '200'),
]:
    code, body = call(route, data)
    ok = code == want and (want != '200' or json.loads(body).get('saved') is False)
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' POST ' + route + ' ' + json.dumps(data) + ' (want ' + want + ', nothing saved)')
    bad += not ok
for row in [x for x in lst['rows'] if not x['secret']][:3]:
    code, body = call('ai/key&id=' + row['id'])
    k = json.loads(body)
    leak = [s[-4:] for s in secrets if s in body]
    code, body = call('ai/key/save', {'id': row['id'], 'label': k['label'], 'workers': k['workers']})
    ok = code == '200' and json.loads(body).get('saved') is False and not leak
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save of key ...' + row['last4'])
    bad += not ok
sys.exit(1 if bad else 0)
PY2
# shellcheck disable=SC2086
same "SourceWeb AI models" "$BEFORE" "$(optmd5 $A_OPTS)"
# Sources: an unchanged Save of the first sources, empty list actions and empty short names write nothing.
S_OPTS="aw_publishers aw_sources kits_sourcing_log"
# shellcheck disable=SC2086
BEFORE=$(optmd5 $S_OPTS)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/sources" > /tmp/sw-sources.json
python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY2' || FAIL=1
import json, subprocess, sys, urllib.parse
r, nonce = sys.argv[1], sys.argv[2]
def call(route, data=None):
    args = ['curl', '-s', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-w', '\n%{http_code}']
    if data is not None:
        args += ['-X', 'POST', '-H', 'Content-Type: application/json', '-d', json.dumps(data)]
    body, code = subprocess.run(args + [r + '/' + route], capture_output=True, text=True).stdout.rsplit('\n', 1)
    return code, json.loads(body)
lst = json.load(open('/tmp/sw-sources.json'))
bad = 0
print('ok   sources list: %d sources, %d views' % (lst['total'], len(lst['views'])))
for route, data in (('sources/decide', {'action': 'block', 'hosts': []}), ('sources/short', {'short': {}}), ('sources/feeds/decide', {'op': 'off', 'ids': []})):
    code, res = call(route, data)
    ok = code == '200' and res.get('changed') is False
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' empty ' + route)
    bad += not ok
for row in lst['rows'][:5]:
    code, s = call('sources/one&host=' + urllib.parse.quote(row['id']))
    data = {k: s[k] for k in ('host', 'name', 'short', 'status', 'press', 'relay')}
    code, res = call('sources/save', data)
    ok = code == '200' and res.get('changed') is False
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save of source ' + row['id'])
    bad += not ok
sys.exit(1 if bad else 0)
PY2
# shellcheck disable=SC2086
same "SourceWeb Sources" "$BEFORE" "$(optmd5 $S_OPTS)"
# Keywords: today's word lists before the keyword table (phase 0 here), the new screen from phase 2.
want 200 "admin.php?page=aw-sourcing&tab=keywords before phase 2" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-sourcing&tab=keywords"
if grep -q 'id="sourceweb-app"' /tmp/rw.out; then echo "FAIL Keywords before phase 2 draws the new screen"; FAIL=1; else echo "ok   Keywords before phase 2 stays on today's screen"; fi
want 409 "GET /sourceweb/v1/keywords before phase 2" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/keywords"
post sourceweb/v1/keywords/save '{"id":0}'
post sourceweb/v1/keywords/decide '{"op":"delete","ids":[]}'
post sourceweb/v1/keywords/settings '{}'
post sourceweb/v1/keywords/suggestions/decide '{"op":"dismiss","qs":[]}'
PHASE_WAS=$(wp option get aw_sourcing_phase 2>/dev/null || echo absent)
if wp eval 'exit( aw_kw_ready() ? 0 : 1 );'; then
  wp option update aw_sourcing_phase 2 --quiet
  screen "aw-sourcing&tab=keywords" sourceweb-app sourceweb-admin
  for r in keywords "keywords&view=include&orderby=impr&order=desc" keywords/one "keywords/one&id=999999999" keywords/suggestions "keywords/test&text=stray+dogs+fed+in+Pune"; do
    case "$r" in
      keywords/one\&id=*) want 404 "GET /sourceweb/v1/$r administrator" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/$r" ;;
      *) route "sourceweb/v1/$r" ;;
    esac
  done
  K_OPTS="aw_relevance aw_sources kits_sourcing_log"
  # shellcheck disable=SC2086
  BEFORE=$(optmd5 $K_OPTS)
  curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/keywords" > /tmp/sw-keywords.json
  python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY2' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
def call(route, data=None):
    args = ['curl', '-s', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-w', '\n%{http_code}']
    if data is not None:
        args += ['-X', 'POST', '-H', 'Content-Type: application/json', '-d', json.dumps(data)]
    body, code = subprocess.run(args + [r + '/' + route], capture_output=True, text=True).stdout.rsplit('\n', 1)
    return code, json.loads(body)
lst = json.load(open('/tmp/sw-keywords.json'))
bad = 0
print('ok   keywords list: %d keywords, %d views, %d roles' % (lst['total'], len(lst['views']), len(lst['roles'])))
st = lst['settings']
for route, data, key in (('keywords/settings', dict(st), 'saved'), ('keywords/decide', {'op': 'delete', 'ids': []}, 'changed'), ('keywords/suggestions/decide', {'op': 'dismiss', 'qs': []}, 'changed')):
    code, res = call(route, data)
    ok = code == '200' and not res.get(key)
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged ' + route)
    bad += not ok
for row in lst['rows'][:5]:
    code, k = call('keywords/one&id=%d' % row['id'])
    data = {'id': k['id'], 'variants': k['variants'], 'lang': k['lang'], 'roles': k['roles'], 'categories': k['cats'], 'sections': k['sections'], 'search_on': k['search_on'], 'query': k['query']}
    code, res = call('keywords/save', data)
    ok = code == '200' and res.get('done') == 'none'
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save of keyword ' + row['title'])
    bad += not ok
code, res = call('keywords/save', {'id': 0, 'label': 'ci dog', 'roles': []})
ok = code == '400'
print(('ok  ' if ok else 'FAIL') + ' ' + code + ' Add New Keyword with no role is refused')
bad += not ok
sys.exit(1 if bad else 0)
PY2
  # shellcheck disable=SC2086
  same "SourceWeb Keywords" "$BEFORE" "$(optmd5 $K_OPTS)"
  if [ "$PHASE_WAS" = absent ]; then wp option delete aw_sourcing_phase --quiet; else wp option update aw_sourcing_phase "$PHASE_WAS" --quiet; fi
else
  echo "ok   (the keyword table is not set up here: the phase 2 Keywords checks are skipped)"
fi
# Rules: today's Sections, Summary rules and Directory enrichment tabs before the phase 3 migration, Rules from it.
want 409 "GET /sourceweb/v1/rules/sections before phase 3" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/sourceweb/v1/rules/sections"
post sourceweb/v1/rules/section/save '{"type":"aw_news"}'
post sourceweb/v1/rules/tag/save '{"tag":""}'
post sourceweb/v1/rules/tag/delete '{"tag":"x"}'
post sourceweb/v1/rules/priority '{"pos":{}}'
post sourceweb/v1/rules/field/save '{"field":"phone","rule":{}}'
post sourceweb/v1/rules/summary/save '{"values":{}}'
PHASE_WAS=$(wp option get aw_sourcing_phase 2>/dev/null || echo absent)
wp option update aw_sourcing_phase 3 --quiet
screen "aw-sourcing&tab=rules" sourceweb-app sourceweb-admin
for r in rules/sections "rules/section&type=aw_news" rules/tag rules/enrich rules/summary; do
  route "sourceweb/v1/$r"
done
R_OPTS="aw_sources aw_summary_rules aw_enrich_field_rules kits_sourcing_log"
# shellcheck disable=SC2086
BEFORE=$(optmd5 $R_OPTS)
python3 - "$H/?rest_route=/sourceweb/v1" "$NA" <<'PY2' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
def call(route, data=None):
    args = ['curl', '-s', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-w', '\n%{http_code}']
    if data is not None:
        args += ['-X', 'POST', '-H', 'Content-Type: application/json', '-d', json.dumps(data)]
    body, code = subprocess.run(args + [r + '/' + route], capture_output=True, text=True).stdout.rsplit('\n', 1)
    return code, json.loads(body)
bad = 0
def check(ok, label):
    global bad
    print(('ok  ' if ok else 'FAIL') + ' ' + label)
    bad += not ok
code, secs = call('rules/sections')
check(code == '200' and secs['sections'], 'rules: %d sections, %d tag rules' % (len(secs['sections']), len(secs['tagRules'])))
for row in secs['sections'][:4]:
    code, s = call('rules/section&type=' + row['id'])
    code, res = call('rules/section/save', {'type': row['id'], 'categories': s['categories'], 'tags': s['tags'], 'gone': s['gone']})
    check(code == '200' and res.get('done') == 'none', code + ' unchanged Save of section ' + row['id'])
for row in secs['tagRules'][:4]:
    code, t = call('rules/tag&tag=' + row['id'])
    code, res = call('rules/tag/save', {k: t[k] for k in ('tag', 'any', 'hold', 'unless', 'hold_reason')} | {'original': t['tag']})
    check(code == '200' and res.get('done') == 'none', code + ' unchanged Save of tag rule ' + row['id'])
code, res = call('rules/tag/save', {'tag': '', 'any': ''})
check(code == '400', code + ' a tag rule with no tag is refused')
code, en = call('rules/enrich')
code, res = call('rules/priority', {'pos': {p['key']: p['pos'] for p in en['priority']}})
check(code == '200' and res.get('done') == 'none', code + ' unchanged Save of the trust order')
for row in en['fields'][:4]:
    code, f = call('rules/field&field=' + row['id'])
    check(code == '200' and f.get('field') == row['id'] and f['choices']['apply'], code + ' field rule form ' + row['id'])
code, res = call('rules/field/save', {'field': 'no-such-field', 'rule': {}})
check(code == '404', code + ' a field rule for no such field is refused')
code, sm = call('rules/summary')
vals = dict(sm['values'])
vals['sections'] = {x['type']: {'min_words': x['min_words'], 'max_words': x['max_words'], 'structure': x['structure'], 'attribution': x['attribution'], 'inherit': x['inherit']} for x in sm['sections']}
code, res = call('rules/summary/save', {'values': vals})
check(code == '200' and res.get('saved') is False, code + ' unchanged Save of the summary rules')
sys.exit(1 if bad else 0)
PY2
if [ "$PHASE_WAS" = absent ]; then wp option delete aw_sourcing_phase --quiet; else wp option update aw_sourcing_phase "$PHASE_WAS" --quiet; fi
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
