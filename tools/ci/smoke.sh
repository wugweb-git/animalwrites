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
# One item of each kind.
wp post create --post_type=page --post_title="Contact us" --post_name=contact --post_status=publish --quiet
wp post create --post_type=page --post_title="Home" --post_name=home --post_status=publish --quiet
wp post create --post_type=page --post_title="Search" --post_name=search --post_status=publish --post_content="[aw_search]" --quiet
wp term create aw_topic "Community Dogs" --slug=community-dogs --quiet || true
N=$(wp post create --post_type=aw_news --post_title="CI news item" --post_status=publish --porcelain)
wp post meta add "$N" aw_source_url "https://www.thehindu.com/ci-item/" --quiet
wp post meta add "$N" aw_source_name "The Hindu" --quiet
wp post create --post_type=aw_legal --post_title="CI library item 2023" --post_status=publish --quiet
wp post create --post_type=post --post_title="CI guide" --post_status=publish --quiet
wp post create --post_type=aw_opportunity --post_title="CI opportunity" --post_status=publish --quiet
wp post create --post_type=aw_campaign --post_title="CI campaign" --post_status=publish --quiet
L=$(wp post create --post_type=aw_listing --post_title="CI listing Ernakulam" --post_status=publish --porcelain)
wp eval "aw_set_item_places( $L, array( array( 32, 555 ) ) ); update_post_meta( $L, 'aw_featured', 0 );"
wp rewrite flush --quiet
php -S 127.0.0.1:8080 -t "$WP" >/tmp/php-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2
curl -s -o /dev/null http://127.0.0.1:8080/ || true # first request runs the one-time steps
single() { wp post list --post_type="$1" --posts_per_page=1 --field=url | sed 's#http://127.0.0.1:8080##'; }
URLS=(/ /news/ /library/ /resources/ /opportunities/ /campaigns/ /directory/ /media/ /contact/ /volunteer/ "/search/?q=dog"
  /directory/kerala/ /directory/kerala/ernakulam/
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
if [ -f /tmp/wp-debug.log ] && grep -E 'PHP (Fatal|Parse) error' /tmp/wp-debug.log; then FAIL=1; fi
exit $FAIL
