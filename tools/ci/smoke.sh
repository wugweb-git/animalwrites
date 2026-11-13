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
# Kits by Wugweb alone, as on the live site: Animal Writes Core serves the Animal Writes theme parts (includes/theme-parts*.php).
wp theme activate kits-by-wugweb --quiet
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
# Several workers: a request to the site itself (cron, loopback) must not wait on the one being answered.
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t "$WP" >/tmp/php-server.log 2>&1 &
SERVER=$!
stop_server() { pkill -P "$SERVER" 2>/dev/null || true; kill "$SERVER" 2>/dev/null || true; } # the workers, then the server
trap 'stop_server' EXIT
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
for s in kits kits-options kits-settings kits-tools kits-help; do admok "Kits → $s" "admin.php?page=$s"; done
adm "admin.php?page=kits" >/dev/null # the five tabs are checked with the frame below
has "Home: edit the theme (Site Editor links)" /tmp/admin.html 'id="theme"'
has "Home: page check (Animal Writes)" /tmp/admin.html 'id="page-check"'
has "Home: email, thumbnails and pictures stay" /tmp/admin.html 'id="pictures"'
# Nothing WordPress or another plugin already shows (owner, 11 Nov 2026), and no row of section links.
for id in versions backup dates theme-switch setup; do grep -qF "id=\"$id\"" /tmp/admin.html && { echo "FAIL Home still has $id"; FAIL=1; } || echo "ok   Home: no $id"; done
grep -q 'class="subsubsub"' /tmp/admin.html && { echo "FAIL Home: section link row"; FAIL=1; } || echo "ok   Home: no section link row"
# Kits → Plugins is gone (owner, 11 Nov 2026): its old address opens WordPress's own Plugins screen.
to=$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "http://127.0.0.1:8080/wp-admin/admin.php?page=kits-plugins")
case "$to" in */wp-admin/plugins.php) echo "ok   kits-plugins goes to Plugins";; *) echo "FAIL kits-plugins goes to: $to"; FAIL=1;; esac
grep -q 'page=kits-plugins' /tmp/admin.html && { echo "FAIL Home still links to Kits → Plugins"; FAIL=1; } || echo "ok   no Kits → Plugins link"
adm "admin.php?page=kits-settings" >/dev/null
has "Settings: CDN cache times" /tmp/admin.html 'id="cdn"'
has "Settings: preview access" /tmp/admin.html 'id="kits-preview-ips"'
has "Settings: Analytics (read only)" /tmp/admin.html 'id="analytics"'
# Keys and modules are set in RankWeb → Settings and SourceWeb → Settings now (0b010493, 12 Nov 2026).
has "Settings: RankWeb Settings linked" /tmp/admin.html 'page=aw-seo-settings'
has "Settings: SourceWeb Settings linked" /tmp/admin.html 'page=aw-sourcing&#038;tab=settings'
adm "admin.php?page=kits-help" >/dev/null
has "Help: docs" /tmp/admin.html 'id="docs"'
has "Help: design system" /tmp/admin.html 'id="design-system"'
has "Help: edit screens" /tmp/admin.html 'id="developer/edit-screens"'
has "Help: theme parts" /tmp/admin.html 'id="developer/theme-parts"'
adm "admin.php?page=kits-help&tab=components" >/dev/null # Design system: the plugins here are links into the repo checkout
if grep -qF -e "$REPO/" -e "$WP/" /tmp/admin.html; then echo "FAIL Help → Design system prints a server path:"; grep -oF -e "$REPO/" -e "$WP/" /tmp/admin.html | sort | uniq -c | head -3; FAIL=1; else echo "ok   Help → Design system: no server paths"; fi
adm "admin.php?page=kits-options" >/dev/null
has "Theme Options: the app is the default" /tmp/admin.html 'kits-admin-theme-options-js'
for l in content-types socials share; do has "Theme Options: $l shows today's fields" /tmp/admin.html "data-kits-legacy=\"$l\""; done
has "Theme Options → Socials: Contact links" /tmp/admin.html 'name="aw_elsewhere[0][label]"'
for s in aw-keys aw-modules aw-dates; do admok "$s keeps its address" "admin.php?page=$s"; done
# Kits → Modules: the fields that were WP-CLI only (12 Nov 2026), one Settings API form.
adm "admin.php?page=aw-modules" >/dev/null
for f in aw_ff_send_email aw_nl_ff_form_full aw_sample_listing_ids aw_district_helplines aw_issuer_short; do has "Modules: field $f" /tmp/admin.html "name=\"$f\""; done
adm "admin.php?page=aw-sourcing&tab=settings" >/dev/null
has "SourceWeb Settings: backfill date field" /tmp/admin.html 'aw_backfill_floor'
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
aw-core admin.php?page=kits
aw-theme-parts admin.php?page=kits-help#developer/theme-parts
kits-system&tab=states admin.php?page=kits-help&tab=states#design-system
MAP
# Offline copies of the five Kits screens for the PR's screenshots (the Deploy check keeps them as an artifact).
if [ -n "${KITS_SNAPSHOT:-}" ] && command -v wget >/dev/null; then
  sed 's/^#HttpOnly_//' "$JAR" >/tmp/ci-cookies-wget.txt
  for s in kits kits-options kits-settings kits-tools kits-help; do
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
# Each product its own top-level menu, grouped in one Wugweb block (owner, 12 Nov 2026). As an administrator and as an
# editor, each screen's address answers as before: 200 where the role may open it, 403 where it may not. On each screen
# that opens, the product's own menu is the open one (and ITEM lit, unless -); nothing sits under Appearance or Plugins.
# PlanWeb is on for this part, off after.
echo "-- menus: the Wugweb block"
wp plugin activate planweb --quiet
wp user create ci-editor ci-editor@example.invalid --role=editor --user_pass=ci-editor-only --quiet
EJAR=/tmp/ci-cookies-editor.txt; rm -f "$EJAR"
curl -s -o /dev/null -c "$EJAR" http://127.0.0.1:8080/wp-login.php
curl -s -o /dev/null -b "$EJAR" -c "$EJAR" --data-urlencode log=ci-editor --data-urlencode pwd=ci-editor-only --data-urlencode testcookie=1 --data-urlencode redirect_to=http://127.0.0.1:8080/wp-admin/ http://127.0.0.1:8080/wp-login.php
as_role() { if [ "$1" = "editor" ]; then echo "$EJAR"; else echo "$JAR"; fi; }
menu_read() { python3 "$REPO/tools/ci/admin_menu_read.py" /tmp/admin.html "$1"; }
# ROLE PAGE CODE MENU ITEM: the page's address answers CODE; when 200, toplevel_page_MENU is open with ITEM lit.
while read -r role page want mid item; do
  [ -z "$role" ] && continue
  code=$(curl -s -b "$(as_role "$role")" -o /tmp/admin.html -w '%{http_code}' "http://127.0.0.1:8080/wp-admin/admin.php?page=$page")
  if [ "$want" != "200" ]; then
    if [ "$code" = "$want" ]; then echo "ok   $code $role $page (not theirs, as before)"; else echo "FAIL $code $role $page (want $want)"; FAIL=1; fi
    continue
  fi
  if [ "$code" != "200" ] || grep -qiE 'critical error|Fatal error|Parse error|Sorry, you are not allowed' /tmp/admin.html; then echo "FAIL $code $role $page"; FAIL=1; continue; fi
  m=$(menu_read "toplevel_page_$mid")
  if echo "$m" | grep -qx "open yes" && { [ "$item" = "-" ] || echo "$m" | grep -qx "current $item"; }; then echo "ok   200 $role $page: $mid open${item:+, $item lit}"; else echo "FAIL $role $page: want $mid open with $item lit, got: $(echo "$m" | head -2 | tr '\n' ' ')"; FAIL=1; fi
  for under in menu-appearance menu-plugins; do
    left=$(menu_read "$under" | sed -n 's/^items //p' | tr ' ' '\n' | grep -E '^(kits|aw-seo-plus|aw-search-insights|aw-sourcing-menu|mailweb|planweb|directoryweb)$' || true)
    if [ -n "$left" ]; then echo "FAIL $role $page: still under $under: $left"; FAIL=1; fi
  done
done <<'PAGES'
administrator kits 200 kits kits
administrator kits-options 200 kits kits-options
administrator kits-settings 200 kits kits-settings
administrator kits-tools 200 kits kits-tools
administrator kits-help 200 kits kits-help
administrator aw-keys 200 kits -
administrator aw-modules 200 kits -
administrator aw-seo-plus 200 aw-seo-plus aw-seo-plus
administrator aw-faq 200 aw-seo-plus aw-faq
administrator aw-seo-settings 200 aw-seo-plus aw-seo-settings
administrator aw-search-insights 200 aw-search-insights -
administrator aw-sourcing-menu 200 aw-sourcing-menu -
administrator aw-review 200 aw-sourcing-menu -
administrator aw-enrich-review 200 aw-sourcing-menu -
administrator aw-sourcing 200 aw-sourcing-menu -
administrator aw-sourcing&tab=keywords 200 aw-sourcing-menu -
administrator mailweb 200 mailweb -
administrator mailweb-overview 200 mailweb -
administrator mailweb-settings 200 mailweb -
administrator planweb 200 planweb -
editor aw-seo-plus 200 aw-seo-plus aw-seo-plus
editor aw-faq 200 aw-seo-plus aw-faq
editor aw-sourcing-menu 200 aw-sourcing-menu -
editor aw-review 200 aw-sourcing-menu -
editor aw-enrich-review 200 aw-sourcing-menu -
editor mailweb 200 mailweb -
editor mailweb-overview 200 mailweb -
editor planweb 200 planweb -
editor aw-seo-settings 403
editor aw-search-insights 403
editor aw-sourcing 403
editor mailweb-settings 403
editor kits 403
editor kits-tools 403
PAGES
# The block, in order, after one separator: the menus each role sees (read on any screen).
curl -s -b "$JAR" -o /tmp/admin.html "http://127.0.0.1:8080/wp-admin/admin.php?page=aw-seo-plus"
got=$(menu_read menu-plugins | sed -n 's/^toplevel //p' | tr ' ' '\n' | grep -E '^toplevel_page_(kits|animal-writes|directoryweb|aw-sourcing-menu|aw-seo-plus|aw-search-insights|mailweb|planweb)$' | sed 's/^toplevel_page_//' | tr '\n' ' ' | sed 's/ $//')
if [ "$got" = "kits aw-sourcing-menu aw-seo-plus aw-search-insights mailweb planweb" ] || [ "$got" = "kits directoryweb aw-sourcing-menu aw-seo-plus aw-search-insights mailweb planweb" ]; then echo "ok   administrator: the Wugweb block in order ($got)"; else echo "FAIL administrator: Wugweb block: $got"; FAIL=1; fi
grep -q 'id="separator-wugweb"' /tmp/admin.html && echo "ok   a separator above the block" || { echo "FAIL no separator above the Wugweb block"; FAIL=1; }
curl -s -b "$EJAR" -o /tmp/admin.html "http://127.0.0.1:8080/wp-admin/admin.php?page=aw-seo-plus"
got=" $(menu_read menu-plugins | sed -n 's/^toplevel //p') "
for i in aw-seo-plus aw-sourcing-menu mailweb planweb; do case "$got" in *" toplevel_page_$i "*) echo "ok   editor: has $i" ;; *) echo "FAIL editor: no $i menu"; FAIL=1 ;; esac; done
for i in kits aw-search-insights; do case "$got" in *" toplevel_page_$i "*) echo "FAIL editor: sees $i"; FAIL=1 ;; *) echo "ok   editor: no $i (as before)" ;; esac; done
# A product's tabs reach its other screens; the tab shown is marked.
wp plugin deactivate planweb --quiet
# Plugin Check on each of our plugins that changed (PLUGIN_CHECK from the workflow: their folder names), so
# wordpress.org is not asked on every Deploy check. Warnings are printed; an error fails the run. Three codes are
# accepted for now and listed in docs/plugin-map.md: the Update URI header (kept on our plugins), the map libraries
# from a CDN, and DirectoryWeb's own text domain inside Animal Writes Core.
PC_ACCEPTED="plugin_updater_detected,PluginCheck.CodeAnalysis.Offloading.OffloadedContent,WordPress.WP.I18n.TextDomainMismatch"
if [ -z "${PLUGIN_CHECK// /}" ]; then
  echo "-- Plugin Check skipped (none of our plugins changed)"
elif wp plugin install plugin-check --activate --quiet 2>/dev/null; then
  for p in $PLUGIN_CHECK; do
    wp plugin activate "$p" --quiet 2>/dev/null || true
    echo "-- Plugin Check: $p"
    wp plugin check "$p" --exclude-directories=tests,node_modules,src --ignore-codes="$PC_ACCEPTED" --format=csv > "/tmp/pc-$p.csv" 2>&1 || true
    tail -60 "/tmp/pc-$p.csv"
    # The errors themselves, each under its file (the last 60 lines above can miss them).
    awk '/^FILE: /{f=$0} /,ERROR,/{print "ERROR ROW " f " | " $0}' "/tmp/pc-$p.csv"
    if grep -q ',ERROR,' "/tmp/pc-$p.csv"; then echo "FAIL Plugin Check errors in $p"; FAIL=1; fi
  done
  wp plugin deactivate plugin-check --quiet || true
else
  echo "-- Plugin Check could not be installed (skipped)"
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
# jsok LABEL HANDLE PAGE-FILE: the screen's script and the shell's, as printed on the page, answer 200 as JavaScript
# (a plugin loaded from outside wp-content/plugins once got an address that answered 404 as text/html).
jsok() {
  local h u c
  for h in "$2" kits-shell; do
    u=$(grep -oE "<script[^>]*id=['\"]$h-js['\"][^>]*>" "$3" | grep -oE "src=['\"][^'\"]+" | head -1 | cut -c6- | sed 's/&#038;/\&/g; s/&amp;/\&/g')
    if [ -z "$u" ]; then echo "FAIL $1: no $h script on the page"; FAIL=1; continue; fi
    c=$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$u")
    case "$c" in
      "200 "*javascript*) echo "ok   $1: $h script $c" ;;
      *) echo "FAIL $1: $h script $c ($u)"; FAIL=1 ;;
    esac
  done
}
wp user create rw-sub rw-sub@example.invalid --role=subscriber --user_pass=rw-sub-only --quiet
wp user create rw-ed rw-ed@example.invalid --role=editor --user_pass=rw-ed-only --quiet
NA=$(login ci ci-only); NS=$(login rw-sub rw-sub-only); NE=$(login rw-ed rw-ed-only)
[ -n "$NA" ] && [ -n "$NS" ] && [ -n "$NE" ] && echo "ok   signed in: administrator, subscriber, editor" || { echo "FAIL sign-in for the route checks"; FAIL=1; }
for p in "aw-seo-plus&ui=next" "aw-seo-settings&ui=next"; do
  want 200 "admin.php?page=$p (administrator)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$p"
  if grep -q 'id="rankweb-app"' /tmp/rw.out && grep -q "rankweb-admin-js" /tmp/rw.out && grep -q "kits-shell-js" /tmp/rw.out; then echo "ok   $p draws the new screen (shell + RankWeb script)"; else echo "FAIL $p: new screen not drawn"; FAIL=1; fi
  jsok "$p" rankweb-admin /tmp/rw.out
done
want 200 "admin.php?page=aw-seo-plus (default)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=aw-seo-plus"
if grep -q 'id="rankweb-app"' /tmp/rw.out; then echo "ok   the new Dashboard is the default"; else echo "FAIL the new Dashboard is not the default"; FAIL=1; fi
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
for r in dashboard/count settings/titles settings/modules settings/faq-types settings/keep-listings settings/share-image; do
  want 401 "POST /rankweb/v1/$r signed out" -X POST -H 'Content-Type: application/json' -d '{"on":[],"ids":[],"id":0}' "$R/$r"
  want 403 "POST /rankweb/v1/$r subscriber" -X POST -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" -H 'Content-Type: application/json' -d '{"on":[],"ids":[],"id":0}' "$R/$r"
done
want 200 "POST /rankweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$R/dashboard/count"
# No setting lost: Save every group as the screen shows it, and its extras ("FAQ box on", listings kept indexed, the
# default share image) as they are; the options stay as they were.
snap() { wp eval 'foreach ( array( "aw_seo_settings", "aw_faq_off_types", "aw_seo_keep_listings", "aw_default_share_image" ) as $o ) { echo $o, "=", md5( serialize( get_option( $o, "(absent)" ) ) ), " "; }'; }
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
    if 'keepListings' in g:
        calls.append(('settings/keep-listings', {'ids': g['keepListings']['ids']}))
    if 'shareImage' in g:
        calls.append(('settings/share-image', {'id': g['shareImage']['id']}))
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
# Shell screens of the other Kits plugins (docs/kits-plugin-ui.md): each new screen 200 for an administrator and drawn
# (mount + script + shell), its current screen still the default, a subscriber refused (403), and every route 401 signed
# out, 401 with a cookie and no nonce, 403 for a subscriber, 200 for an administrator.
# screen PAGE MOUNT-ID SCRIPT-HANDLE: the &ui=next screen and its current screen.
screen() {
  want 200 "admin.php?page=$1&ui=next (administrator)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$1&ui=next"
  if grep -q "id=\"$2\"" /tmp/rw.out && grep -q "$3-js" /tmp/rw.out && grep -q "kits-shell-js" /tmp/rw.out; then echo "ok   $1&ui=next draws the new screen"; else echo "FAIL $1&ui=next: new screen not drawn"; FAIL=1; fi
  jsok "$1&ui=next" "$3" /tmp/rw.out
  want 200 "admin.php?page=$1 (default)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$1"
  if grep -q "id=\"$2\"" /tmp/rw.out; then echo "ok   $1: the new screen is the default"; else echo "FAIL $1: the new screen is not the default"; FAIL=1; fi
  want 403 "admin.php?page=$1&ui=next (subscriber)" -b /tmp/cj-rw-sub "$H/wp-admin/admin.php?page=$1&ui=next"
}
# route NAMESPACE/PATH: GET checks.
route() {
  want 401 "GET /$1 signed out" "$H/?rest_route=/$1"
  want 401 "GET /$1 cookie, no nonce" -b /tmp/cj-ci "$H/?rest_route=/$1"
  want 403 "GET /$1 subscriber" -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$H/?rest_route=/$1"
  want 200 "GET /$1 administrator" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/$1"
}
# post NAMESPACE/PATH JSON: a write refused signed out and for a subscriber.
post() {
  want 401 "POST /$1 signed out" -X POST -H 'Content-Type: application/json' -d "$2" "$H/?rest_route=/$1"
  want 403 "POST /$1 subscriber" -X POST -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" -H 'Content-Type: application/json' -d "$2" "$H/?rest_route=/$1"
}
# optmd5 NAME…: md5 of each option (or "(absent)").
optmd5() { local l; l=$(printf '"%s",' "$@"); wp eval "foreach ( array( ${l%,} ) as \$o ) { echo \$o, '=', md5( serialize( get_option( \$o, '(absent)' ) ) ), ' '; }"; }
# same LABEL BEFORE AFTER: no setting lost.
same() { if [ "$2" = "$3" ]; then echo "ok   $1: every option identical after an unchanged Save"; else echo "FAIL $1: options changed: $2 -> $3"; FAIL=1; fi; }

echo "-- kits screens on the plugin look (the default; includes/admin-kits-frame.php)"
# Home, Settings, Tools and Help: 200 and drawn in the frame (shell stylesheet, header, five tabs) with the same
# sections as before, on &ui=next and the plain address; a subscriber is refused.
while read -r page section; do
  want 200 "admin.php?page=$page&ui=next (administrator)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$page&ui=next"
  if grep -q 'class="kits-shell-header"' /tmp/rw.out && grep -q "kits-shell-css" /tmp/rw.out && grep -q "id=\"$section\"" /tmp/rw.out; then echo "ok   $page&ui=next draws the frame with $section"; else echo "FAIL $page&ui=next: frame or $section missing"; FAIL=1; fi
  n=$(grep -o 'class="kits-shell-tab' /tmp/rw.out | wc -l | tr -d ' ')
  if [ "$n" = "5" ]; then echo "ok   $page&ui=next: five tabs"; else echo "FAIL $page&ui=next: $n tabs"; FAIL=1; fi
  if grep -qiE 'critical error|Fatal error|Parse error' /tmp/rw.out; then echo "FAIL $page&ui=next: PHP error"; FAIL=1; fi
  want 200 "admin.php?page=$page (default)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=$page"
  if grep -q 'class="kits-shell-header"' /tmp/rw.out; then echo "ok   $page: the new look is the default"; else echo "FAIL $page: the new look is not the default"; FAIL=1; fi
  want 403 "admin.php?page=$page&ui=next (subscriber)" -b /tmp/cj-rw-sub "$H/wp-admin/admin.php?page=$page&ui=next"
done <<'FRAME'
kits theme
kits-settings preview
kits-tools export
kits-help docs
FRAME
# A save comes back to the screen it was made on.
want 200 "admin.php?page=kits-settings (save address)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=kits-settings"
if grep -q 'name="_wp_http_referer" value="[^"]*page=kits-settings' /tmp/rw.out && ! grep -q 'name="_wp_http_referer" value="[^"]*ui=classic' /tmp/rw.out; then echo "ok   Settings forms come back to the new look"; else echo "FAIL Settings forms do not come back to the new look"; FAIL=1; fi
# Theme Options is drawn by its app on the shell; its tabs are the plain addresses (the new look is the default).

want 200 "admin.php?page=kits-options (administrator)" -b /tmp/cj-ci "$H/wp-admin/admin.php?page=kits-options"
if grep -q "kits-admin-theme-options-js" /tmp/rw.out && grep -q "kits-shell-js" /tmp/rw.out && grep -q 'id="kits-admin-app"' /tmp/rw.out; then echo "ok   Theme Options app on the shell"; else echo "FAIL Theme Options: app or shell missing"; FAIL=1; fi
if grep -q 'page=kits&amp;ui=next\|page=kits\\u0026ui=next\|page=kits&ui=next' /tmp/rw.out; then echo "FAIL Theme Options tabs carry &ui=next"; FAIL=1; else echo "ok   Theme Options tabs are the plain addresses"; fi
# Each Kits plugin's new screens: tools/ci/smoke-ui-<plugin>.sh, using screen, route, post, optmd5 and same above.
for f in "$REPO"/tools/ci/smoke-ui-*.sh; do
  [ -f "$f" ] || continue
  echo "-- $(basename "$f" .sh)"
  # shellcheck source=/dev/null
  . "$f"
  UI_RAN=$((${UI_RAN:-0} + 1))
done
if [ "${UI_RAN:-0}" -ge 5 ]; then echo "ok   $UI_RAN plugin screen files ran (tools/ci/smoke-ui-*.sh)"; else echo "FAIL only ${UI_RAN:-0} plugin screen files ran (tools/ci/smoke-ui-*.sh)"; FAIL=1; fi

wp user delete rw-sub rw-ed --yes --quiet
if [ -f /tmp/wp-debug.log ] && grep -E 'PHP (Fatal|Parse) error' /tmp/wp-debug.log; then FAIL=1; fi

# Second pass: the Animal Writes theme parts animal-writes-core serves over Kits by Wugweb (includes/theme-parts*.php):
# from a fresh cache, the same URLs answer 200, with one Animal Writes stylesheet, and the pages are the Animal Writes ones.
echo "-- Animal Writes theme parts (Kits by Wugweb with animal-writes-core)"
LOG_START=$( [ -f /tmp/wp-debug.log ] && wc -l </tmp/wp-debug.log || echo 0 )
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
    echo "FAIL $code $u"; FAIL=1
  else
    echo "ok   $code $u"
  fi
  # With the Joined stylesheet on (the default): exactly one Animal Writes stylesheet link on every page.
  case "$u" in *rest_route=*) continue ;; esac
  n=$(aw_links /tmp/page.html)
  if [ "$n" = "1" ]; then echo "ok   1 Animal Writes stylesheet $u"; else echo "FAIL $n Animal Writes stylesheets $u, want 1"; FAIL=1; fi
done
# has NAME FILE PATTERN: an ok/FAIL line for a fixed-string match in a fetched page.
has() { if grep -qF -- "$3" "$2"; then echo "ok   $1"; else echo "FAIL $1"; FAIL=1; fi; }
curl -s -o /tmp/home.html http://127.0.0.1:8080/
curl -s -o /tmp/state.html http://127.0.0.1:8080/directory/kerala/
curl -s -o /tmp/listing.html "http://127.0.0.1:8080$(single aw_listing)"
# joined FILE: the joined component file a page links to (kits-parts-css), fetched to /tmp/joined-NAME.css.
joined() { href=$(grep -oE "<link[^>]*id=['\"]kits-parts-css['\"][^>]*>" "$1" | grep -oE "href=['\"][^'\"]+" | head -1 | cut -c7-); [ -n "$href" ] && curl -s -o "/tmp/joined-$2.css" "$href"; }
if joined /tmp/home.html home; then
  has "joined file on / starts with aw.css" /tmp/joined-home.css '/* aw-components */'
  # aw.css's wordmark url() is rewritten to the folder it came from: the plugin's copy, not a theme folder.
  has "joined aw.css is the plugin copy" /tmp/joined-home.css '/wp-content/plugins/animal-writes-core/theme/assets/'
else
  echo "FAIL no joined component file on /"; FAIL=1
fi
# Markers of the component files, once they exist: aw-home on the front page only.
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-home.css" ]; then
  has "joined file on / has aw-home" /tmp/joined-home.css '/* aw-home */'
  if joined /tmp/state.html state && grep -qF '/* aw-home */' /tmp/joined-state.css; then echo "FAIL joined file on a state page has aw-home"; FAIL=1; else echo "ok   joined file on a state page has no aw-home"; fi
fi
# Joined stylesheet off: the separate files load, each where its component is.
wp eval '$o = (array) get_option( "kits_theme_options", array() ); $o["css_bundle"] = "off"; update_option( "kits_theme_options", $o );'
curl -s -o /tmp/home.html http://127.0.0.1:8080/
curl -s -o /tmp/state.html http://127.0.0.1:8080/directory/kerala/
curl -s -o /tmp/listing.html "http://127.0.0.1:8080$(single aw_listing)"
# The component stylesheet comes from the plugin's copy (aw.min.css, or aw.css when the min file is missing), not a theme folder.
if grep -oE "<link[^>]*id=['\"]aw-components-css['\"][^>]*>" /tmp/home.html | grep -qE '/wp-content/plugins/animal-writes-core/theme/assets/css/aw(\.min)?\.css'; then
  echo "ok   aw-components-css from the plugin (joined off)"
else
  echo "FAIL aw-components-css from the plugin (joined off)"; FAIL=1
fi
if grep -qF 'kits-cache/parts/' /tmp/home.html; then echo "FAIL joined component file on / with the switch off"; FAIL=1; else echo "ok   no joined component file with the switch off"; fi
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-home.css" ]; then
  has "aw-home-css on / (joined off)" /tmp/home.html "id='aw-home-css'"
  if grep -qF "id='aw-home-css'" /tmp/state.html; then echo "FAIL aw-home-css on a state page (joined off)"; FAIL=1; else echo "ok   no aw-home-css on a state page (joined off)"; fi
fi
if [ -f "$REPO/plugins/animal-writes-core/theme/assets/css/aw-listing.css" ]; then
  has "aw-listing-css on a listing (joined off)" /tmp/listing.html "id='aw-listing-css'"
fi
# Back on: the options cleaner keeps a saved value for a missing key, so "on" is set (and dropped as the default).
wp eval '$o = (array) get_option( "kits_theme_options", array() ); $o["css_bundle"] = "on"; update_option( "kits_theme_options", $o );'
curl -s -o /tmp/home.html http://127.0.0.1:8080/
# Markers from the Animal Writes front-page template and header part, in no Kits template or part; written as the class
# attribute so a stylesheet rule cannot match them.
has "front-page template is the Animal Writes one" /tmp/home.html 'class="wp-block-group kit-static' # the front page's main group (only our front-page template uses kit-static); the Take part band went back to its pattern and the FAQ section is empty without FAQ items (5 Nov 2026)
has "header part is the Animal Writes one" /tmp/home.html 'class="kit-header-utility" data-menu="secondary"'
want=$(ls "$REPO"/plugins/animal-writes-core/theme/patterns/*.php | wc -l | tr -d ' ')
got=$(wp eval 'echo count(array_filter(WP_Block_Patterns_Registry::get_instance()->get_all_registered(), fn($p)=>str_starts_with($p["name"],"animal-writes/")));' 2>/dev/null || echo error)
if [ "$got" = "$want" ]; then echo "ok   $got animal-writes/ patterns registered"; else echo "FAIL $got animal-writes/ patterns registered, want $want"; FAIL=1; fi
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
if [ -f /tmp/wp-debug.log ] && tail -n +"$((LOG_START + 1))" /tmp/wp-debug.log | grep -E 'PHP (Fatal|Parse) error|PHP Warning|PHP Deprecated:.*/(plugins|themes)/(animal-writes|kits|aw-|mailweb|insightweb|planweb)'; then FAIL=1; fi
wp cache flush --quiet
# Menu pages for the PR's before and after screenshots (pull requests only; the Deploy check keeps them with the Kits
# screens): as an administrator and as an editor, the same screens on this commit (menu-after) and on the base commit
# (menu-before), saved as offline pages. The base is served from its own checkout by a fresh PHP server (a running one
# would keep the old symlink targets in its path cache), and everything is put back afterwards. PlanWeb is on.
if [ -n "${KITS_SNAPSHOT:-}" ] && command -v wget >/dev/null && [ -n "${BASE:-}" ] && git -C "$REPO" cat-file -e "$BASE" 2>/dev/null; then
  wp plugin activate planweb --quiet
  sed 's/^#HttpOnly_//' "$JAR" >/tmp/ci-cookies-wget.txt
  sed 's/^#HttpOnly_//' "$EJAR" >/tmp/ci-cookies-editor-wget.txt
  snap() { # STATE: menu-before or menu-after
    for s in kits aw-seo-plus mailweb; do
      wget -q -E -k -K -p -nH --restrict-file-names=windows --load-cookies /tmp/ci-cookies-wget.txt -P "$KITS_SNAPSHOT/$1/administrator" "http://127.0.0.1:8080/wp-admin/admin.php?page=$s" || true
    done
    for s in aw-seo-plus mailweb; do
      wget -q -E -k -K -p -nH --restrict-file-names=windows --load-cookies /tmp/ci-cookies-editor-wget.txt -P "$KITS_SNAPSHOT/$1/editor" "http://127.0.0.1:8080/wp-admin/admin.php?page=$s" || true
    done
  }
  relink() { # FOLDER: point the test site's plugins and themes at that checkout
    for p in "$1"/plugins/*/; do ln -sfn "$p" "wp-content/plugins/$(basename "$p")"; done
    for t in "$1"/themes/*/; do ln -sfn "$t" "wp-content/themes/$(basename "$t")"; done
  }
  serve() { stop_server; sleep 1; PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t "$WP" >>/tmp/php-server.log 2>&1 & SERVER=$!; sleep 2; }
  snap menu-after
  if git -C "$REPO" worktree add --quiet --detach /tmp/base-tree "$BASE"; then
    relink /tmp/base-tree; serve
    snap menu-before
    relink "$REPO"; serve
    git -C "$REPO" worktree remove --force /tmp/base-tree || true
    echo "ok   menu pages before ($BASE) and after saved to $KITS_SNAPSHOT"
  else
    echo "-- menu pages before: the base commit could not be checked out (after only)"
  fi
  wp plugin deactivate planweb --quiet
fi
exit $FAIL
