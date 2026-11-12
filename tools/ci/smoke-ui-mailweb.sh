# MailWeb's new screens (&ui=next): sourced by smoke.sh, which has activated MailWeb and defines $H, $NA, $NS, $NE,
# the cookie jars and the helpers screen, route, post, want, optmd5 and same.
# Nothing here sends, marks or moves mail: CI has no Mail API token and no SMTP password, the writes that could lead
# to a send (resume, retry, bulk) are only checked as refused, and the Saves send today's values unchanged.
# shellcheck shell=bash
MW_OPTS="mailweb_cap mailweb_mailboxes mailweb_default_from mailweb_aliases mailweb_profiles mailweb_crm mailweb_smtp mailweb_api_token mailweb_pause"
# shellcheck disable=SC2086
MW_BEFORE=$(optmd5 $MW_OPTS)
screen mailweb-overview mailweb-app mailweb-admin
screen mailweb mailweb-app mailweb-admin
screen mailweb-settings mailweb-app mailweb-admin
want 200 "admin.php?page=mailweb&ui=next (editor)" -b /tmp/cj-rw-ed "$H/wp-admin/admin.php?page=mailweb&ui=next"
for r in dashboard inbox settings services; do route "mailweb/v1/$r"; done
want 200 "GET /mailweb/v1/dashboard editor" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$H/?rest_route=/mailweb/v1/dashboard"
grep -q '"tiles"' /tmp/rw.out && echo "ok   dashboard answers with tiles" || { echo "FAIL dashboard has no tiles"; FAIL=1; }
want 200 "GET /mailweb/v1/inbox editor" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$H/?rest_route=/mailweb/v1/inbox&folder=inbox&page=1"
grep -q '"views"' /tmp/rw.out && echo "ok   inbox answers with views" || { echo "FAIL inbox has no views"; FAIL=1; }
# The reading pane: one message. CI has no Mail API token, so a message is never found (404); nothing is marked read.
want 401 "GET /mailweb/v1/inbox/message signed out" "$H/?rest_route=/mailweb/v1/inbox/message&box=support&uid=1"
want 401 "GET /mailweb/v1/inbox/message cookie, no nonce" -b /tmp/cj-ci "$H/?rest_route=/mailweb/v1/inbox/message&box=support&uid=1"
want 403 "GET /mailweb/v1/inbox/message subscriber" -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$H/?rest_route=/mailweb/v1/inbox/message&box=support&uid=1"
want 404 "GET /mailweb/v1/inbox/message editor, no such message" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$H/?rest_route=/mailweb/v1/inbox/message&box=support&uid=1"
want 404 "GET /mailweb/v1/inbox/message unknown mailbox" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/mailweb/v1/inbox/message&box=nobody&uid=1"
want 200 "admin.php?page=mailweb&ui=next (inbox stylesheet)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=mailweb&ui=next"
grep -q "mailweb-admin-css" /tmp/rw.out && echo "ok   the Inbox loads its stylesheet" || { echo "FAIL the Inbox has no stylesheet"; FAIL=1; }
# The Dashboard carries the Overview's feature table as page parts (Inbox, Site sending, FluentCRM feed rows).
want 200 "admin.php?page=mailweb-overview&ui=next (features)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=mailweb-overview&ui=next"
grep -q '"features":\[{"type":"listtable"' /tmp/rw.out && grep -q 'FluentCRM feed' /tmp/rw.out && echo "ok   the Dashboard has the feature table" || { echo "FAIL the Dashboard has no feature table"; FAIL=1; }
want 403 "GET /mailweb/v1/settings editor (administrators only)" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$H/?rest_route=/mailweb/v1/settings"
post mailweb/v1/dashboard/count '{}'
post mailweb/v1/sending/resume '{}'
post mailweb/v1/queue/retry '{}'
post mailweb/v1/inbox/bulk '{"action":"read","folder":"inbox","ids":["support|1"]}'
# Move to folder: refused signed out and for a subscriber; a folder role it cannot move from is 400; a message of
# no known mailbox moves nowhere (no call to the mail server).
post mailweb/v1/inbox/move '{"folder":"inbox","to":"INBOX.Archive","ids":["support|1"]}'
want 400 "POST /mailweb/v1/inbox/move from Website forms" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"folder":"forms","to":"INBOX.Archive","ids":["support|1"]}' "$H/?rest_route=/mailweb/v1/inbox/move"
want 400 "POST /mailweb/v1/inbox/move to a bad folder name" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"folder":"inbox","to":"../etc","ids":["support|1"]}' "$H/?rest_route=/mailweb/v1/inbox/move"
want 200 "POST /mailweb/v1/inbox/move unknown mailbox" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"folder":"inbox","to":"INBOX.Archive","ids":["nosuchbox|1"]}' "$H/?rest_route=/mailweb/v1/inbox/move"
grep -q '"ok":false' /tmp/rw.out && echo "ok   Move to folder: a message of no known mailbox is not moved" || { echo "FAIL Move to folder moved a message of no known mailbox"; FAIL=1; }
for g in sending mailboxes profiles; do post "mailweb/v1/settings/$g" '{}'; done
post mailweb/v1/services/mail-api '{"token":""}'
post mailweb/v1/services/webhook/support '{"secret":""}'
post mailweb/v1/services/smtp '{}'
post mailweb/v1/services/fluentcrm '{}'
want 403 "POST /mailweb/v1/sending/resume editor (administrators only)" -X POST -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$H/?rest_route=/mailweb/v1/sending/resume"
want 200 "POST /mailweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/mailweb/v1/dashboard/count"
# No setting lost: Save every group and service as the screen shows it; nothing may be written.
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/mailweb/v1/settings" > /tmp/mw-settings.json
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/mailweb/v1/services" > /tmp/mw-services.json
python3 - "$H/?rest_route=/mailweb/v1" "$NA" <<'PY' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
groups = {g['slug']: g for g in json.load(open('/tmp/mw-settings.json'))['groups']}
services = json.load(open('/tmp/mw-services.json'))['services']
calls = [
    ('settings/sending', groups['sending']['values']),
    ('settings/mailboxes', {'send': {b['key']: b['send'] for b in groups['mailboxes']['boxes']}, 'default_from': groups['mailboxes']['defaultFrom'], 'aliases': groups['mailboxes']['aliases']}),
    ('settings/profiles', {'profiles': {p['key']: {'name': p['name'], 'signature': p['signature'], 'picture': p['picture']} for p in groups['profiles']['profiles']}}),
    ('services/mail-api', {'token': ''}),
]
for s in services:
    if s['kind'] == 'smtp':
        calls.append((s['route'], dict(s['values'], **{'pass': ''})))
    elif s['kind'] == 'fluentcrm':
        calls.append((s['route'], s['values']))
bad = 0
for route, data in calls:
    out = subprocess.run(['curl', '-s', '-w', '\n%{http_code}', '-X', 'POST', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-H', 'Content-Type: application/json', '-d', json.dumps(data), r + '/' + route], capture_output=True, text=True).stdout
    body, code = out.rsplit('\n', 1)
    try:
        saved = json.loads(body).get('saved')
    except ValueError:
        saved = None
    ok = code == '200' and saved is False
    print(('ok  ' if ok else 'FAIL') + ' ' + code + ' unchanged Save ' + route + ' (saved: ' + str(saved) + ')')
    bad += not ok
print(('ok  ' if len(groups) == 3 else 'FAIL') + ' %d settings groups, %d services' % (len(groups), len(services)))
sys.exit(1 if bad or len(groups) != 3 else 0)
PY
# shellcheck disable=SC2086
same "MailWeb Settings" "$MW_BEFORE" "$(optmd5 $MW_OPTS)"
if wp eval 'exit( count( array_intersect( array( "mailweb_cap", "mailweb_mailboxes", "mailweb_default_from", "mailweb_aliases", "mailweb_profiles", "mailweb_crm" ), kits_settings_transfer_options() ) ) === 6 && ! in_array( "mailweb_smtp", kits_settings_transfer_options(), true ) ? 0 : 1 );'; then echo "ok   MailWeb's settings are in the Kits export, the SMTP password is not"; else echo "FAIL MailWeb's Kits export"; FAIL=1; fi
# Keys and passwords never reach the screen.
if grep -q '"pass":"\(set\|unset\|config\)"' /tmp/mw-services.json && ! grep -qi 'mwenc1' /tmp/mw-services.json /tmp/mw-settings.json; then echo "ok   services show only whether a password is set"; else echo "FAIL services answer has a secret in it"; FAIL=1; fi
