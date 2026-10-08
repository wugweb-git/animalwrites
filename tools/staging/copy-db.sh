#!/usr/bin/env bash
# Copy the live database to staging, without personal data. Run on the server:
#   bash ~/domains/animalwrites.in/public_html/aw_deploy/tools/staging/copy-db.sh
# Needs the staging site (STAGING_ROOT) to exist with its own wp-config.php (hPanel, docs/staging.md).
set -euo pipefail
LIVE_ROOT="$HOME/domains/animalwrites.in/public_html"
STAGING_ROOT="${STAGING_ROOT:-$HOME/domains/animalwrites.in/public_html/staging}"
[ -f "$STAGING_ROOT/wp-config.php" ] || { echo "No staging wp-config.php at $STAGING_ROOT"; exit 1; }
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
cfg() { wp --path="$1" config get "$2" --skip-plugins --skip-themes; }
LP=$(cfg "$LIVE_ROOT" table_prefix)
# 1. Dump live.
MYSQL_PWD="$(cfg "$LIVE_ROOT" DB_PASSWORD)" mysqldump -u "$(cfg "$LIVE_ROOT" DB_USER)" -h "$(cfg "$LIVE_ROOT" DB_HOST)" "$(cfg "$LIVE_ROOT" DB_NAME)" > "$TMP/live.sql"
# 2. Load into staging.
MYSQL_PWD="$(cfg "$STAGING_ROOT" DB_PASSWORD)" mysql -u "$(cfg "$STAGING_ROOT" DB_USER)" -h "$(cfg "$STAGING_ROOT" DB_HOST)" "$(cfg "$STAGING_ROOT" DB_NAME)" < "$TMP/live.sql"
# The copied tables carry the live table prefix: staging uses the same one.
wp --path="$STAGING_ROOT" --skip-plugins --skip-themes config set table_prefix "$LP" --quiet
# 3. Remove personal data on staging only: volunteers, subscribers, contact messages, claims, reports, visitors' comments.
W="wp --path=$STAGING_ROOT --skip-plugins --skip-themes"
$W db query "TRUNCATE ${LP}aw_volunteers; TRUNCATE ${LP}aw_subscribers;"
$W db query "DELETE pm FROM ${LP}postmeta pm JOIN ${LP}posts p ON p.ID = pm.post_id WHERE p.post_type IN ('aw_message','aw_claim','aw_report','aw_volunteer'); DELETE FROM ${LP}posts WHERE post_type IN ('aw_message','aw_claim','aw_report','aw_volunteer'); DELETE FROM ${LP}comments; DELETE FROM ${LP}commentmeta;"
# 4. Point it at the staging address.
$W search-replace 'https://animalwrites.in' 'https://staging.animalwrites.in' --all-tables --quiet
$W option update blog_public 0 --quiet
echo "Staging database copied without personal data."
