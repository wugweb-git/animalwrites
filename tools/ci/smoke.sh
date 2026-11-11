#!/usr/bin/env bash
# Boot a throwaway WordPress with this repo's themes and plugins, add one item of each kind, then load one URL per
# page type. Fails on anything but 200, any PHP fatal or a "critical error" page. Used by .github/workflows/deploy-check.yml.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
WP=/tmp/wp
rm -rf "$WP" && mkdir -p "$WP" && cd "$WP"
wp core download --quiet
wp config create --dbname=wp --dbuser=root --dbpass=root --dbhost=127.0.0.1 --quiet --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/tmp/wp-debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
PHP
wp core install --url=http://127.0.0.1:8080 --title="Animal Writes" --admin_user=ci --admin_password=ci-only --admin_email=ci@example.invalid --skip-email --quiet
for p in "$REPO"/plugins/*/; do ln -s "$p" "wp-content/plugins/$(basename "$p")"; done
for t in "$REPO"/themes/*/; do ln -s "$t" "wp-content/themes/$(basename "$t")"; done
wp rewrite structure '/%postname%/' --quiet
wp theme activate animal-writes --quiet
wp plugin activate animal-writes-core --quiet
# Rank Math is the site's SEO system; aw-seo-extras adds what it cannot do (it does nothing without Rank Math).
wp plugin install seo-by-rank-math --version=1.0.280 --activate --quiet
wp plugin activate aw-seo-extras --quiet
# Mailweb: all site mail (inbox, webhook /wp-json/mailweb/v1/mail-in, SMTP sending, daily limit, signatures).
wp plugin activate mailweb --quiet
# One item of each kind.
wp post create --post_type=page --post_title="Contact us" --post_name=contact --post_status=publish --quiet
wp post create --post_type=page --post_title="Home" --post_name=home --post_status=publish --quiet
wp post create --post_type=page --post_title="Search" --post_name=search --post_status=publish --post_content="[aw_search]" --quiet
# /volunteer/ as on live (the one-time step that made it was removed, audit 16, 25 Oct 2026).
wp post create --post_type=page --post_title="Register as a volunteer" --post_name=volunteer --post_status=publish --post_content='<!-- wp:animal-writes/volunteer {"open":true} /-->' --quiet
wp term create category "Community Dogs" --slug=community-dogs --quiet || true
N=$(wp post create --post_type=aw_news --post_title="CI news item" --post_status=publish --porcelain)
wp post meta add "$N" aw_source_url "https://www.thehindu.com/ci-item/" --quiet
wp post meta add "$N" aw_source_name "The Hindu" --quiet
wp post term add "$N" category community-dogs --quiet
wp post term add "$N" post_tag ci-tag --quiet
wp post create --post_type=aw_legal --post_title="CI library item 2023" --post_status=publish --quiet
wp post create --post_type=post --post_title="CI guide" --post_status=publish --quiet
wp post create --post_type=aw_opportunity --post_title="CI opportunity" --post_status=publish --quiet
wp post create --post_type=aw_campaign --post_title="CI campaign" --post_status=publish --quiet
L=$(wp post create --post_type=aw_listing --post_title="CI listing Ernakulam" --post_status=publish --porcelain)
wp eval "aw_set_item_places( $L, array( array( 32, 555 ) ) );"
wp rewrite flush --quiet
php -S 127.0.0.1:8080 -t "$WP" >/tmp/php-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2
curl -s -o /dev/null http://127.0.0.1:8080/ || true # first request runs the one-time steps
single() { wp post list --post_type="$1" --posts_per_page=1 --field=url | sed 's#http://127.0.0.1:8080##'; }
URLS=(/ "/?rest_route=/aw/v1/mail-in" /news/ /library/ /resources/ /opportunities/ /campaigns/ /directory/ /press-coverage/ /contact/ /volunteer/ "/search/?q=dog"
  /directory/kerala/ /directory/kerala/ernakulam/ /category/community-dogs/ /tag/ci-tag/ "/news/?category_name=community-dogs"
  "$(single aw_news)" "$(single aw_legal)" "$(single post)" "$(single aw_opportunity)" "$(single aw_campaign)" "$(single aw_listing)")
FAIL=0
for u in "${URLS[@]}"; do
  code=$(curl -s -o /tmp/page.html -w '%{http_code}' "http://127.0.0.1:8080$u")
  if [ "$code" != "200" ] || grep -qiE 'critical error|Fatal error|Parse error' /tmp/page.html; then
    echo "FAIL $code $u"; FAIL=1
  else
    echo "ok   $code $u"
  fi
done
# AW Sourcing is loaded by Core: its private worker endpoint must exist and refuse a visitor (401, not 404).
code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080/?rest_route=/aw/v1/sourcing-policy")
if [ "$code" != "401" ]; then echo "FAIL $code /aw/v1/sourcing-policy (want 401)"; FAIL=1; else echo "ok   $code /aw/v1/sourcing-policy"; fi
# PlanWeb (loaded by Core, off until switched on): Dates is imported one for one, a second import adds nothing, the
# Dates option is unchanged, Kits → Dates leaves the menu, and the calendar's routes refuse a visitor.
DATES='[{"date":"2026-11-05","title":"Lab rules objections close","note":"Campaign 13848 & the home page."},{"date":"2026-11-17","title":"Rankings recheck","note":"Search Insights → Pages."},{"date":"2026-11-05","title":"Lab rules objections close","note":"Campaign 13848 & the home page."},{"date":"soon","title":"Not a date"},{"date":"2026-12-01","title":"Search Console Links read (Claude Code session)","note":"RankWeb → Search Insights → Links"}]'
wp option update aw_team_dates "$DATES" --format=json --quiet
wp option update aw_team_dates_links_note_1111 1 --quiet
BEFORE=$(wp option get aw_team_dates --format=json)
# Off first, as on live today: Kits → Dates is in the menu and the calendar's post type does not exist.
if [ "$(wp eval 'echo isset( aw_core_screens()["aw-dates"] ) ? "yes" : "no";')" = "yes" ]; then echo "ok   planweb off: Kits → Dates in the menu"; else echo "FAIL planweb off: Kits → Dates missing"; FAIL=1; fi
if [ "$(wp eval 'echo post_type_exists( "planweb_entry" ) ? "yes" : "no";')" = "no" ]; then echo "ok   planweb off: no entry type"; else echo "FAIL planweb off: entry type registered"; FAIL=1; fi
wp plugin activate planweb --quiet # Core already loads it; activating also switches it on, as ticking Kits → Modules does.
if [ "$(wp option get planweb_on)" = "1" ]; then echo "ok   planweb on after activation"; else echo "FAIL planweb not on after activation"; FAIL=1; fi
if out=$(wp planweb import 2>&1) && echo "$out" | grep -q "5 dates in Dates, 4 entries made, 0 already there, 1 not dates"; then echo "ok   planweb import: $out"; else echo "FAIL planweb import: $out"; FAIL=1; fi
if out=$(wp planweb verify 2>&1) && echo "$out" | grep -q "4 the same, 0 different, 0 missing, 0 extra"; then echo "ok   planweb verify"; else echo "FAIL planweb verify: $out"; FAIL=1; fi
if out=$(wp planweb import 2>&1) && echo "$out" | grep -q "0 entries made, 4 already there"; then echo "ok   planweb import again adds nothing"; else echo "FAIL planweb import again: $out"; FAIL=1; fi
if out=$(wp planweb verify 2>&1); then echo "ok   planweb verify again"; else echo "FAIL planweb verify again: $out"; FAIL=1; fi
if [ "$(wp option get aw_team_dates --format=json)" = "$BEFORE" ]; then echo "ok   aw_team_dates unchanged"; else echo "FAIL aw_team_dates changed by the import"; FAIL=1; fi
if [ "$(wp eval 'echo isset( aw_core_screens()["aw-dates"] ) ? "yes" : "no";')" = "no" ]; then echo "ok   Kits → Dates left the menu"; else echo "FAIL Kits → Dates still in the menu"; FAIL=1; fi
for r in /wp/v2/planweb-entries /wp/v2/planweb_type; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:8080/?rest_route=$r")
  if [ "$code" != "401" ]; then echo "FAIL $code $r (want 401)"; FAIL=1; else echo "ok   $code $r"; fi
done
if [ "$(wp eval 'echo planweb_check_fields( array( "link" => "https://wa.me/919999999999" ) ) ? "refused" : "allowed";')" = "refused" ]; then echo "ok   planweb refuses a wa.me link"; else echo "FAIL planweb allowed a wa.me link"; FAIL=1; fi
# Off again at the end: deactivating switches PlanWeb off, Kits → Dates comes back, the capabilities come off the
# roles, and the entries are kept.
wp plugin deactivate planweb --quiet
if [ "$(wp option get planweb_on 2>/dev/null)" = "" ]; then echo "ok   planweb off after deactivation"; else echo "FAIL planweb still on after deactivation"; FAIL=1; fi
if [ "$(wp eval 'echo isset( aw_core_screens()["aw-dates"] ) ? "yes" : "no";')" = "yes" ]; then echo "ok   planweb off: Kits → Dates back"; else echo "FAIL planweb off: Kits → Dates not back"; FAIL=1; fi
if [ "$(wp eval 'echo get_role( "administrator" )->has_cap( "edit_planweb_entries" ) ? "yes" : "no";')" = "no" ]; then echo "ok   planweb off: capabilities removed"; else echo "FAIL planweb off: capabilities left on"; FAIL=1; fi
if [ "$(wp eval 'global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = \"planweb_entry\"" );')" = "4" ]; then echo "ok   planweb off: entries kept"; else echo "FAIL planweb off: entries not kept"; FAIL=1; fi
# Kits menu (docs/admin-ui-spec.md §B, §E1; owner, 11 Nov 2026): as an administrator, the menu is exactly the six items,
# each loads (200), Keys, Modules and Dates still open at their own address, every old address answers 302 to its new
# place, and loading them all changes no setting (run logs and cache counters that page loads and scheduled tasks
# write are left out: aw_cron_runs, aw_publishers, aw_seo_cache_gen, aw_error_pages).
echo "-- kits menu (administrator)"
has() { if grep -qF -- "$3" "$2"; then echo "ok   $1"; else echo "FAIL $1"; FAIL=1; fi; }
opts() { wp eval 'global $wpdb; foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name NOT LIKE \"%transient%\" AND option_name NOT IN (\"cron\",\"recently_activated\",\"aw_cron_runs\",\"aw_publishers\",\"aw_seo_cache_gen\",\"aw_error_pages\") ORDER BY option_name" ) as $r ) { echo $r->option_name, " ", md5( $r->option_value ), "\n"; }'; }
JAR=/tmp/ci-cookies.txt; rm -f "$JAR"
curl -s -o /dev/null -c "$JAR" http://127.0.0.1:8080/wp-login.php
curl -s -o /dev/null -b "$JAR" -c "$JAR" --data-urlencode log=ci --data-urlencode pwd=ci-only --data-urlencode testcookie=1 --data-urlencode redirect_to=http://127.0.0.1:8080/wp-admin/ http://127.0.0.1:8080/wp-login.php
adm() { curl -s -b "$JAR" -o /tmp/admin.html -w '%{http_code}' "http://127.0.0.1:8080/wp-admin/$1"; }
adm "" >/dev/null # the first admin request runs the site's hourly checks (e.g. aw_error_pages); settings are read after it
opts >/tmp/opts-before.txt
admok() { # NAME PATH: 200, no PHP error, not refused
  code=$(adm "$2")
  if [ "$code" = "200" ] && ! grep -qiE 'critical error|Fatal error|Parse error|Sorry, you are not allowed' /tmp/admin.html; then echo "ok   $code $1"; else echo "FAIL $code $1"; FAIL=1; fi
}
for s in kits kits-options kits-settings kits-plugins kits-tools kits-help; do admok "Kits → $s" "admin.php?page=$s"; done
adm "admin.php?page=kits" >/dev/null
got=$(python3 - /tmp/admin.html <<'PY'
import re, sys
h = open(sys.argv[1], encoding='utf-8', errors='replace').read()
m = re.search(r'id="toplevel_page_kits".*?<ul class=.wp-submenu wp-submenu-wrap.>(.*?)</ul>', h, re.S)
print(' '.join(re.findall(r"href=.(?:admin\.php\?page=)?([^'\"]+)", m.group(1))) if m else 'no-menu')
PY
)
if [ "$got" = "kits kits-options kits-settings kits-plugins kits-tools kits-help" ]; then echo "ok   Kits menu is the six items"; else echo "FAIL Kits menu: $got"; FAIL=1; fi
n=$(grep -o '<a class="nav-tab' /tmp/admin.html | wc -l | tr -d ' ')
if [ "$n" = "6" ]; then echo "ok   six Kits tabs"; else echo "FAIL $n Kits tabs, want 6"; FAIL=1; fi
has "Home: page check (Animal Writes)" /tmp/admin.html 'id="page-check"'
has "Home: backup" /tmp/admin.html 'id="backup"'
has "Home: email, thumbnails and pictures stay" /tmp/admin.html 'id="pictures"'
has "Home: Dates linked" /tmp/admin.html 'page=aw-dates'
adm "admin.php?page=kits-plugins" >/dev/null
# InsightWeb is not loaded on this test site, so only PlanWeb's and MailWeb's rows are checked.
for n in PlanWeb MailWeb; do has "Plugins: $n row with its icon" /tmp/admin.html "data:image/svg+xml;base64,"; grep -q ">$n</a>" /tmp/admin.html && echo "ok   Plugins: $n row" || { echo "FAIL Plugins: no $n row"; FAIL=1; }; done
adm "admin.php?page=kits-settings" >/dev/null
has "Settings: CDN cache times" /tmp/admin.html 'id="cdn"'
has "Settings: preview access" /tmp/admin.html 'id="kits-preview-ips"'
has "Settings: Keys linked" /tmp/admin.html 'page=aw-keys'
has "Settings: Modules linked" /tmp/admin.html 'page=aw-modules'
adm "admin.php?page=kits-help" >/dev/null
has "Help: docs" /tmp/admin.html 'id="docs"'
has "Help: design system" /tmp/admin.html 'id="design-system"'
has "Help: edit screens" /tmp/admin.html 'id="developer/edit-screens"'
has "Help: theme parts" /tmp/admin.html 'id="developer/theme-parts"'
adm "admin.php?page=kits-help&tab=components" >/dev/null # Design system: the plugins here are links into the repo checkout
if grep -qF -e "$REPO/" -e "$WP/" /tmp/admin.html; then echo "FAIL Help → Design system prints a server path:"; grep -oF -e "$REPO/" -e "$WP/" /tmp/admin.html | sort | uniq -c | head -3; FAIL=1; else echo "ok   Help → Design system: no server paths"; fi
adm "admin.php?page=kits-options" >/dev/null
has "Theme Options: the app is the default" /tmp/admin.html 'kits-admin-theme-options-js'
for l in content-types socials share layout-spacing; do has "Theme Options: $l shows today's fields" /tmp/admin.html "data-kits-legacy=\"$l\""; done
has "Theme Options → Socials: Contact links" /tmp/admin.html 'name="aw_elsewhere[0][label]"'
admok "Theme Options, older screen (&ui=classic)" "admin.php?page=kits-options&ui=classic"
for s in aw-keys aw-modules aw-dates; do admok "$s keeps its address" "admin.php?page=$s"; done
# Old address => where it goes (a 302, the other query values kept).
while read -r old want; do
  out=$(curl -s -b "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}' "http://127.0.0.1:8080/wp-admin/admin.php?page=$old")
  if [ "$out" = "302 http://127.0.0.1:8080/wp-admin/$want" ]; then echo "ok   302 $old → $want"; else echo "FAIL $old: $out (want 302 $want)"; FAIL=1; fi
done <<'MAP'
kits-core-settings admin.php?page=kits-settings
kits-transfer admin.php?page=kits-tools
kits-docs admin.php?page=kits-help#docs
kits-system admin.php?page=kits-help#design-system
kits-preview admin.php?page=kits-settings#preview
animal-writes admin.php?page=kits
aw-site-check admin.php?page=kits#page-check
aw-elsewhere admin.php?page=kits-options#socials
aw-edit-screens admin.php?page=kits-help#developer/edit-screens
aw-theme-parts admin.php?page=kits-help#developer/theme-parts
kits-system&tab=states admin.php?page=kits-help&tab=states#design-system
MAP
# Offline copies of the menu's six screens for the PR's screenshots (the Deploy check keeps them as an artifact).
if [ -n "${KITS_SNAPSHOT:-}" ] && command -v wget >/dev/null; then
  sed 's/^#HttpOnly_//' "$JAR" >/tmp/ci-cookies-wget.txt
  for s in kits kits-options kits-settings kits-plugins kits-tools kits-help; do
    wget -q -E -k -K -p -nH --restrict-file-names=windows --load-cookies /tmp/ci-cookies-wget.txt -P "$KITS_SNAPSHOT" "http://127.0.0.1:8080/wp-admin/admin.php?page=$s" || true
  done
  echo "ok   Kits screens saved to $KITS_SNAPSHOT"
fi
opts >/tmp/opts-after.txt
# Every setting there before is there after with the same value (docs/admin-ui-spec.md §E2); options a plugin adds on
# its first run are listed, not a failure.
gone=$(comm -23 <(sort /tmp/opts-before.txt) <(sort /tmp/opts-after.txt))
added=$(comm -13 <(cut -d' ' -f1 /tmp/opts-before.txt | sort) <(cut -d' ' -f1 /tmp/opts-after.txt | sort) | tr '\n' ' ')
if [ -z "$gone" ]; then echo "ok   every setting kept with its value ($(wc -l </tmp/opts-before.txt | tr -d ' ') options)${added:+; new: $added}"; else echo "FAIL settings changed or gone:"; echo "$gone" | head -20; FAIL=1; fi
# Plugin Check on PlanWeb (report only, printed, never fails the run): only when plugins/planweb changed (PLUGIN_CHECK=1
# from the workflow), so wordpress.org is not asked on every Deploy check.
if [ "${PLUGIN_CHECK:-0}" != "1" ]; then
  echo "-- Plugin Check skipped (plugins/planweb unchanged)"
elif wp plugin install plugin-check --activate --quiet 2>/dev/null; then
  echo "-- Plugin Check: planweb (report)"; wp plugin check planweb --exclude-directories=tests --format=table 2>&1 | tail -40 || true
  wp plugin deactivate plugin-check --quiet || true
else
  echo "-- Plugin Check could not be installed (report skipped)"
fi
# RankWeb's new screens (trial, &ui=next; includes/rankweb-ui.php) and the rankweb/v1 routes (includes/rankweb-rest.php):
# both screens 200 for an administrator, the current screens still the default, every route 401 signed out, 401 with a
# cookie but no nonce, 403 for a subscriber, and an unchanged Save of every group writes nothing.
echo "-- rankweb new screens"
H=http://127.0.0.1:8080
login() { rm -f "/tmp/cj-$1"; curl -s -o /dev/null -c "/tmp/cj-$1" "$H/wp-login.php"; curl -s -o /dev/null -b "/tmp/cj-$1" -c "/tmp/cj-$1" --data-urlencode "log=$1" --data-urlencode "pwd=$2" -d "testcookie=1" -d "redirect_to=$H/wp-admin/" "$H/wp-login.php"; curl -s -b "/tmp/cj-$1" "$H/wp-admin/admin-ajax.php?action=rest-nonce"; }
want() { # want CODE LABEL curl-args…
  local w=$1 l=$2; shift 2
  local c; c=$(curl -s -o /tmp/rw.out -w '%{http_code}' "$@")
  if [ "$c" = "$w" ]; then echo "ok   $c $l"; else echo "FAIL $c $l (want $w)"; head -c 300 /tmp/rw.out; echo; FAIL=1; fi
}
wp user create rw-sub rw-sub@example.invalid --role=subscriber --user_pass=rw-sub-only --quiet
wp user create rw-ed rw-ed@example.invalid --role=editor --user_pass=rw-ed-only --quiet
NA=$(login ci ci-only); NS=$(login rw-sub rw-sub-only); NE=$(login rw-ed rw-ed-only)
[ -n "$NA" ] && [ -n "$NS" ] && [ -n "$NE" ] && echo "ok   signed in: administrator, subscriber, editor" || { echo "FAIL sign-in for the route checks"; FAIL=1; }
for p in "aw-seo-plus&ui=next" "aw-seo-settings&ui=next"; do
  want 200 "admin.php?page=$p (administrator)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$p"
  if grep -q 'id="rankweb-app"' /tmp/rw.out && grep -q "rankweb-admin-js" /tmp/rw.out && grep -q "kits-shell-js" /tmp/rw.out; then echo "ok   $p draws the new screen (shell + RankWeb script)"; else echo "FAIL $p: new screen not drawn"; FAIL=1; fi
done
want 200 "admin.php?page=aw-seo-plus (current Overview)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-seo-plus"
if grep -q 'id="rankweb-app"' /tmp/rw.out; then echo "FAIL the Overview is not the default any more"; FAIL=1; else echo "ok   current Overview stays the default"; fi
want 200 "admin.php?page=aw-seo-settings (current Settings)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-seo-settings"
if grep -q 'nav-tab-wrapper' /tmp/rw.out && ! grep -q 'id="rankweb-app"' /tmp/rw.out; then echo "ok   current Settings stays the default"; else echo "FAIL the current Settings is not the default"; FAIL=1; fi
want 200 "admin.php?page=aw-seo-plus&ui=next (editor)" -b /tmp/cj-rw-ed "$H/wp-admin/admin.php?page=aw-seo-plus&ui=next"
R="$H/?rest_route=/rankweb/v1"
for r in dashboard settings services; do
  want 401 "GET /rankweb/v1/$r signed out" "$R/$r"
  want 401 "GET /rankweb/v1/$r cookie, no nonce" -b /tmp/cj-ci "$R/$r"
  want 403 "GET /rankweb/v1/$r subscriber" -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$R/$r"
  want 200 "GET /rankweb/v1/$r administrator" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$R/$r"
done
want 200 "GET /rankweb/v1/dashboard editor" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$R/dashboard"
want 403 "GET /rankweb/v1/settings editor (administrators only)" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$R/settings"
for r in dashboard/count settings/titles settings/modules settings/faq-types; do
  want 401 "POST /rankweb/v1/$r signed out" -X POST -H 'Content-Type: application/json' -d '{"on":[]}' "$R/$r"
  want 403 "POST /rankweb/v1/$r subscriber" -X POST -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" -H 'Content-Type: application/json' -d '{"on":[]}' "$R/$r"
done
want 200 "POST /rankweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$R/dashboard/count"
# No setting lost: Save every group as the screen shows it, and "FAQ box on" as it is; both options stay as they were.
snap() { wp eval 'foreach ( array( "aw_seo_settings", "aw_faq_off_types", "aw_seo_keep_listings" ) as $o ) { echo $o, "=", md5( serialize( get_option( $o, "(absent)" ) ) ), " "; }'; }
BEFORE=$(snap)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$R/settings" > /tmp/rw-settings.json
python3 - "$R" "$NA" <<'PY' || FAIL=1
import json, subprocess, sys
r, nonce = sys.argv[1], sys.argv[2]
groups = json.load(open('/tmp/rw-settings.json'))['groups']
bad = 0
for g in groups:
    if g['slug'] == 'modules':
        body = {'on': {b['key']: b['on'] for b in g['blocks'] if b['switch']}}
    else:
        body = {'fields': {f['const']: f['text'] for b in g['blocks'] for f in b['fields']}}
    calls = [('settings/' + g['slug'], body)]
    if 'faqTypes' in g:
        calls.append(('settings/faq-types', {'on': [x['slug'] for x in g['faqTypes'] if x['on']]}))
    for route, data in calls:
        out = subprocess.run(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', '-X', 'POST', '-b', '/tmp/cj-ci', '-H', 'X-WP-Nonce: ' + nonce, '-H', 'Content-Type: application/json', '-d', json.dumps(data), r + '/' + route], capture_output=True, text=True).stdout
        print(('ok  ' if out == '200' else 'FAIL') + ' ' + out + ' unchanged Save ' + route)
        bad += out != '200'
print(('ok  ' if len(groups) == 7 else 'FAIL') + ' %d settings groups' % len(groups))
sys.exit(1 if bad or len(groups) != 7 else 0)
PY
AFTER=$(snap)
if [ "$BEFORE" = "$AFTER" ]; then echo "ok   no setting changed by unchanged Saves: $AFTER"; else echo "FAIL settings changed: $BEFORE -> $AFTER"; FAIL=1; fi
if wp eval 'exit( count( array_intersect( array( "aw_seo_settings", "aw_faq_off_types", "aw_seo_keep_listings" ), kits_settings_transfer_options() ) ) === 3 ? 0 : 1 );'; then echo "ok   RankWeb's three options are in the Kits export"; else echo "FAIL RankWeb's options missing from the Kits export"; FAIL=1; fi
wp user delete rw-sub rw-ed --yes --quiet
if [ -f /tmp/wp-debug.log ] && grep -E 'PHP (Fatal|Parse) error' /tmp/wp-debug.log; then FAIL=1; fi
# The child's aw.css has no component rules (it is the plugin's copy), so the component files load from the plugin.
curl -s -o /tmp/home-child.html http://127.0.0.1:8080/
href=$(grep -oE "<link[^>]*id=['\"]kits-parts-css['\"][^>]*>" /tmp/home-child.html | grep -oE "href=['\"][^'\"]+" | head -1 | cut -c7-)
if grep -qF "id='aw-home-css'" /tmp/home-child.html || { [ -n "$href" ] && curl -s "$href" | grep -qF '/* aw-home */'; }; then
  echo "ok   aw-home on / (child)"
else
  echo "FAIL no aw-home on / (child)"; FAIL=1
fi

# Second pass: Kits by Wugweb with no child. animal-writes-core then serves the child's styles, scripts, templates,
# parts and patterns (includes/theme-parts*.php); the same URLs must answer 200 and the page must be the child's.
echo "-- kits-by-wugweb, no child theme"
LOG_START=$( [ -f /tmp/wp-debug.log ] && wc -l </tmp/wp-debug.log || echo 0 )
# A saved Styles post for the child (one custom colour), so the switch must copy it to Kits (includes/theme-switch.php).
wp eval '$old = get_posts( array( "post_type" => "wp_global_styles", "name" => "wp-global-styles-animal-writes", "posts_per_page" => 1, "fields" => "ids" ) ); $id = wp_insert_post( array( "ID" => $old ? $old[0] : 0, "post_type" => "wp_global_styles", "post_status" => "publish", "post_title" => "Custom Styles", "post_name" => "wp-global-styles-animal-writes", "post_content" => wp_slash( "{\"settings\":{\"color\":{\"palette\":{\"custom\":[{\"slug\":\"ci-switch\",\"color\":\"#123456\",\"name\":\"CI\"}]}}},\"isGlobalStylesUserThemeJSON\":true,\"version\":3}" ) ) ); wp_set_object_terms( $id, "animal-writes", "wp_theme" );'
wp theme activate kits-by-wugweb --quiet
wp cache flush --quiet
wp transient delete --all --quiet || true
wp rewrite flush --quiet
curl -s -o /dev/null http://127.0.0.1:8080/ || true
# Animal Writes component stylesheets: aw.css and theme/assets/css/aw-*.css as separate links, or the joined file
# uploads/kits-cache/parts/*.css (Kits kits_component_styles). aw_links FILE prints how many links a page has.
aw_links() { grep -oE "<link[^>]*rel=['\"]stylesheet['\"][^>]*>" "$1" | grep -cE "/animal-writes-core/theme/assets/css/|/kits-cache/parts/" || true; }
for u in "${URLS[@]}"; do
  code=$(curl -s -o /tmp/page.html -w '%{http_code}' "http://127.0.0.1:8080$u")
  if [ "$code" != "200" ] || grep -qiE 'critical error|Fatal error|Parse error' /tmp/page.html; then
    echo "FAIL $code $u (no child)"; FAIL=1
  else
    echo "ok   $code $u (no child)"
  fi
  # With the Joined stylesheet on (the default): exactly one Animal Writes stylesheet link on every page.
  case "$u" in *rest_route=*) continue ;; esac
  n=$(aw_links /tmp/page.html)
  if [ "$n" = "1" ]; then echo "ok   1 Animal Writes stylesheet $u (no child)"; else echo "FAIL $n Animal Writes stylesheets $u, want 1 (no child)"; FAIL=1; fi
done
# has NAME FILE PATTERN: an ok/FAIL line for a fixed-string match in a fetched page.
has() { if grep -qF -- "$3" "$2"; then echo "ok   $1"; else echo "FAIL $1"; FAIL=1; fi; }
curl -s -o /tmp/home.html http://127.0.0.1:8080/
curl -s -o /tmp/state.html http://127.0.0.1:8080/directory/kerala/
curl -s -o /tmp/listing.html "http://127.0.0.1:8080$(single aw_listing)"
# joined FILE: the joined component file a page links to (kits-parts-css), fetched to /tmp/joined-NAME.css.
joined() { href=$(grep -oE "<link[^>]*id=['\"]kits-parts-css['\"][^>]*>" "$1" | grep -oE "href=['\"][^'\"]+" | head -1 | cut -c7-); [ -n "$href" ] && curl -s -o "/tmp/joined-$2.css" "$href"; }
if joined /tmp/home.html home; then
  has "joined file on / starts with aw.css (no child)" /tmp/joined-home.css '/* aw-components */'
  # aw.css's wordmark url() is rewritten to the folder it came from: the plugin's copy, not a theme folder.
  has "joined aw.css is the plugin copy (no child)" /tmp/joined-home.css '/wp-content/plugins/animal-writes-core/theme/assets/'
else
  echo "FAIL no joined component file on / (no child)"; FAIL=1
fi
# Markers of the component files, once they exist: aw-home on the front page only.
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-home.css" ]; then
  has "joined file on / has aw-home (no child)" /tmp/joined-home.css '/* aw-home */'
  if joined /tmp/state.html state && grep -qF '/* aw-home */' /tmp/joined-state.css; then echo "FAIL joined file on a state page has aw-home (no child)"; FAIL=1; else echo "ok   joined file on a state page has no aw-home (no child)"; fi
fi
# Joined stylesheet off: the separate files load, each where its component is.
wp eval '$o = (array) get_option( "kits_theme_options", array() ); $o["css_bundle"] = "off"; update_option( "kits_theme_options", $o );'
curl -s -o /tmp/home.html http://127.0.0.1:8080/
curl -s -o /tmp/state.html http://127.0.0.1:8080/directory/kerala/
curl -s -o /tmp/listing.html "http://127.0.0.1:8080$(single aw_listing)"
# The component stylesheet comes from the plugin's copy (aw.min.css, or aw.css when the min file is missing), not a theme folder.
if grep -oE "<link[^>]*id=['\"]aw-components-css['\"][^>]*>" /tmp/home.html | grep -qE '/wp-content/plugins/animal-writes-core/theme/assets/css/aw(\.min)?\.css'; then
  echo "ok   aw-components-css from the plugin (no child, joined off)"
else
  echo "FAIL aw-components-css from the plugin (no child, joined off)"; FAIL=1
fi
if grep -qF 'kits-cache/parts/' /tmp/home.html; then echo "FAIL joined component file on / with the switch off (no child)"; FAIL=1; else echo "ok   no joined component file with the switch off (no child)"; fi
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-home.css" ]; then
  has "aw-home-css on / (no child, joined off)" /tmp/home.html "id='aw-home-css'"
  if grep -qF "id='aw-home-css'" /tmp/state.html; then echo "FAIL aw-home-css on a state page (no child, joined off)"; FAIL=1; else echo "ok   no aw-home-css on a state page (no child, joined off)"; fi
fi
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-listing.css" ]; then
  has "aw-listing-css on a listing (no child, joined off)" /tmp/listing.html "id='aw-listing-css'"
fi
# Back on: the options cleaner keeps a saved value for a missing key, so "on" is set (and dropped as the default).
wp eval '$o = (array) get_option( "kits_theme_options", array() ); $o["css_bundle"] = "on"; update_option( "kits_theme_options", $o );'
curl -s -o /tmp/home.html http://127.0.0.1:8080/
# Markers from the child's front-page template and header part, in no Kits template or part; written as the class
# attribute so a stylesheet rule cannot match them.
has "front-page template is the Animal Writes one (no child)" /tmp/home.html 'class="wp-block-group kit-static' # the front page's main group (only our front-page template uses kit-static); the Take part band went back to its pattern and the FAQ section is empty without FAQ items (5 Nov 2026)
has "header part is the Animal Writes one (no child)" /tmp/home.html 'class="kit-header-utility" data-menu="secondary"'
want=$(ls "$REPO"/themes/animal-writes/patterns/*.php | wc -l | tr -d ' ')
got=$(wp eval 'echo count(array_filter(WP_Block_Patterns_Registry::get_instance()->get_all_registered(), fn($p)=>str_starts_with($p["name"],"animal-writes/")));' 2>/dev/null || echo error)
if [ "$got" = "$want" ]; then echo "ok   $got animal-writes/ patterns registered (no child)"; else echo "FAIL $got animal-writes/ patterns registered, want $want (no child)"; FAIL=1; fi
# The child's saved Styles were copied to Kits on the switch, and Kits uses them.
got=$(wp eval '$p = get_posts( array( "post_type" => "wp_global_styles", "name" => "wp-global-styles-kits-by-wugweb", "posts_per_page" => 1, "tax_query" => array( array( "taxonomy" => "wp_theme", "field" => "name", "terms" => "kits-by-wugweb" ) ) ) ); $c = wp_list_pluck( (array) ( wp_get_global_settings( array( "color", "palette" ) )["custom"] ?? array() ), "color" ); echo ( $p && false !== strpos( $p[0]->post_content, "#123456" ) ? "post" : "nopost" ) . "," . ( in_array( "#123456", $c, true ) ? "used" : "unused" );' 2>/dev/null || echo error)
if [ "$got" = "post,used" ]; then echo "ok   child's saved Styles copied to Kits and used (no child)"; else echo "FAIL child's saved Styles copied to Kits and used: $got (no child)"; FAIL=1; fi
# InsightWeb (Search Insights, moved out of RankWeb; docs/insightweb-plan.md 3.2): it loads once and every job has a
# handler whoever loads it: RankWeb's bridge while InsightWeb is inactive, and InsightWeb in either plugin order
# (RankWeb is never switched off: it is tied to the theme and Rank Math).
echo "-- insightweb"
iw() { wp eval-file "$REPO/tools/ci/insightweb_load_check.php" "$1" --user=ci || FAIL=1; }
iw bridge
wp plugin activate insightweb --quiet
iw both
wp eval '$a = array_values( (array) get_option( "active_plugins" ) ); usort( $a, fn( $x, $y ) => str_starts_with( $y, "insightweb/" ) <=> str_starts_with( $x, "insightweb/" ) ); update_option( "active_plugins", $a ); echo "order: " . implode( ", ", $a ) . "\n";'
iw both
curl -s -o /tmp/page.html -w '%{http_code}\n' http://127.0.0.1:8080/ | grep -q '^200$' && echo "ok   200 / with InsightWeb active" || { echo "FAIL / with InsightWeb active"; FAIL=1; }
# Never off while the theme runs (owner, 11 Nov 2026): deactivated, and with the old RankWeb switch saved as off, it
# still loads (Core or RankWeb's bridge) and every job keeps a handler.
wp plugin deactivate insightweb --quiet
wp eval '$s = (array) get_option( "aw_seo_settings", array() ); $s["search-insights"]["on"] = 0; update_option( "aw_seo_settings", $s );'
iw bridge
wp eval '$s = (array) get_option( "aw_seo_settings", array() ); unset( $s["search-insights"]["on"] ); update_option( "aw_seo_settings", $s );'
if [ -f /tmp/wp-debug.log ] && tail -n +"$((LOG_START + 1))" /tmp/wp-debug.log | grep -E 'PHP (Fatal|Parse) error|PHP Warning'; then FAIL=1; fi
wp theme activate animal-writes --quiet
wp cache flush --quiet
exit $FAIL
