#!/usr/bin/env bash
# Refresh staging from live (owner, 11 Nov 2026: for the DirectoryWeb move's 0-difference checks). Run on the server:
#   bash ~/domains/animalwrites.in/public_html/aw_deploy/tools/staging/refresh.sh [git-ref]
# git-ref: what staging runs (default: origin/wordpress, the live code). For a before/after check, run once on the
# live code, record, then switch staging to the branch with: git -C "$STAGING_ROOT/aw_deploy" checkout --detach <ref>.
#
# The copy keeps live's personal data (owner, 11 Nov 2026), so staging stays private: the guard (staging-guard.php)
# goes in first and blocks email and every outside request, WordPress cron is off, MailWeb and UpdraftPlus are off,
# and the site is noindex and signed-in only. Pictures load from live's address (read-only); nothing is copied there.
set -euo pipefail
LIVE_ROOT="$HOME/domains/animalwrites.in/public_html"
STAGING_ROOT="${STAGING_ROOT:-$HOME/domains/staging.animalwrites.in/public_html}"
REF="${1:-origin/wordpress}"
LIVE_URL='https://animalwrites.in'
STAGING_URL='https://staging.animalwrites.in'
[ -f "$STAGING_ROOT/wp-config.php" ] || { echo "No staging wp-config.php at $STAGING_ROOT"; exit 1; }
[ -d "$STAGING_ROOT/aw_deploy/.git" ] || { echo "No staging checkout at $STAGING_ROOT/aw_deploy"; exit 1; }
W="wp --path=$STAGING_ROOT --skip-plugins --skip-themes"
cfg() { wp --path="$1" config get "$2" --skip-plugins --skip-themes; }

# 1. Guard first: no email, no outside requests, no cron, from the first request on the new database. AW_STAGING in
# wp-config.php keeps the guard on while the imported database still holds live's address (steps 3 to 4).
git -C "$STAGING_ROOT/aw_deploy" fetch -q origin
git -C "$STAGING_ROOT/aw_deploy" checkout -q --detach "$REF"
mkdir -p "$STAGING_ROOT/wp-content/mu-plugins"
cp "$STAGING_ROOT/aw_deploy/tools/staging/staging-guard.php" "$STAGING_ROOT/wp-content/mu-plugins/aw-staging-guard.php"
$W config set AW_STAGING true --raw --quiet
$W config set DISABLE_WP_CRON true --raw --quiet

# 2. Same plugins and themes as live: the repo's own as links into staging's checkout, third-party ones copied from
# live (not UpdraftPlus: it backs up to remote storage).
for p in animal-writes-core aw-seo-extras kits-core mailweb; do
  ln -sfn "../../aw_deploy/plugins/$p" "$STAGING_ROOT/wp-content/plugins/$p"
done
for t in animal-writes kits-by-wugweb; do
  ln -sfn "../../aw_deploy/themes/$t" "$STAGING_ROOT/wp-content/themes/$t"
done
for d in "$LIVE_ROOT"/wp-content/plugins/*/; do
  n=$(basename "$d")
  [ -L "$LIVE_ROOT/wp-content/plugins/$n" ] && continue
  [ "$n" = updraftplus ] && continue
  rsync -a --delete "$d" "$STAGING_ROOT/wp-content/plugins/$n/"
done
for d in "$LIVE_ROOT"/wp-content/themes/*/; do
  n=$(basename "$d")
  [ -L "$LIVE_ROOT/wp-content/themes/$n" ] && continue
  rsync -a --delete "$d" "$STAGING_ROOT/wp-content/themes/$n/"
done

# 3. Live database into staging, as it is (no rows removed).
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
LP=$(cfg "$LIVE_ROOT" table_prefix)
MYSQL_PWD="$(cfg "$LIVE_ROOT" DB_PASSWORD)" mysqldump --single-transaction --quick -u "$(cfg "$LIVE_ROOT" DB_USER)" -h "$(cfg "$LIVE_ROOT" DB_HOST)" "$(cfg "$LIVE_ROOT" DB_NAME)" > "$TMP/live.sql"
[ -s "$TMP/live.sql" ] || { echo "Empty dump; staging unchanged."; exit 1; }
MYSQL_PWD="$(cfg "$STAGING_ROOT" DB_PASSWORD)" mysql -u "$(cfg "$STAGING_ROOT" DB_USER)" -h "$(cfg "$STAGING_ROOT" DB_HOST)" "$(cfg "$STAGING_ROOT" DB_NAME)" < "$TMP/live.sql"
$W config set table_prefix "$LP" --quiet

# 4. Staging's address, pictures from live's; private; MailWeb (reads the live mailbox) and UpdraftPlus off.
$W search-replace "$LIVE_URL" "$STAGING_URL" --all-tables --skip-columns=guid --quiet
$W search-replace "$STAGING_URL/wp-content/uploads" "$LIVE_URL/wp-content/uploads" --all-tables --skip-columns=guid --quiet
$W option update blog_public 0 --quiet
$W plugin deactivate mailweb updraftplus --quiet || true
wp --path="$STAGING_ROOT" cache flush --quiet || true
wp --path="$STAGING_ROOT" litespeed-purge all --quiet 2>/dev/null || true

echo "Staging: $(git -C "$STAGING_ROOT/aw_deploy" log -1 --format='%h %s')"
echo "home=$($W option get home) blog_public=$($W option get blog_public) cron_off=$($W config get DISABLE_WP_CRON) guard_const=$($W config get AW_STAGING)"
echo "Active plugins: $(wp --path="$STAGING_ROOT" plugin list --status=active --field=name | tr '\n' ' ')"
