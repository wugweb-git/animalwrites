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
# handler whoever loads it: RankWeb's bridge while InsightWeb is inactive, InsightWeb in either plugin order, and
# InsightWeb alone. Switching RankWeb off leaves Search Insights' jobs booked.
echo "-- insightweb"
iw() { wp eval-file "$REPO/tools/ci/insightweb_load_check.php" "$1" --user=ci || FAIL=1; }
iw bridge
wp plugin activate insightweb --quiet
iw both
wp eval '$a = array_values( (array) get_option( "active_plugins" ) ); usort( $a, fn( $x, $y ) => str_starts_with( $y, "insightweb/" ) <=> str_starts_with( $x, "insightweb/" ) ); update_option( "active_plugins", $a ); echo "order: " . implode( ", ", $a ) . "\n";'
iw both
curl -s -o /tmp/page.html -w '%{http_code}\n' http://127.0.0.1:8080/ | grep -q '^200$' && echo "ok   200 / with InsightWeb active" || { echo "FAIL / with InsightWeb active"; FAIL=1; }
wp plugin deactivate aw-seo-extras --quiet
got=$(wp eval --skip-plugins --skip-themes 'echo wp_next_scheduled( "aw_si_daily" ) && wp_next_scheduled( "aw_si_ga4" ) ? "booked" : "cleared";' 2>/dev/null || echo error)
if [ "$got" = "booked" ]; then echo "ok   RankWeb switched off: Search Insights' jobs stay booked"; else echo "FAIL RankWeb switched off: Search Insights' jobs $got"; FAIL=1; fi
iw alone
wp plugin activate aw-seo-extras --quiet
if [ -f /tmp/wp-debug.log ] && tail -n +"$((LOG_START + 1))" /tmp/wp-debug.log | grep -E 'PHP (Fatal|Parse) error|PHP Warning'; then FAIL=1; fi
wp theme activate animal-writes --quiet
wp cache flush --quiet
exit $FAIL
