# PlanWeb's new screens (&ui=next on PlanWeb → Calendar): Dashboard, Entries and Settings, and the planweb/v1 routes.
# Sourced by smoke.sh, which gives $H, $NA, $NS, $NE, the cookie jars and the helpers screen, route, post, want, optmd5
# and same. PlanWeb is switched off earlier in smoke.sh, so it is switched on here first and off again at the end.
wp plugin activate planweb --quiet
wp eval 'echo planweb_on() ? "" : "planweb not on\n";' # One request after switching on: capabilities and lists set up.
wp eval 'planweb_counts_build();' # The background count, run now (the screens only read it).
PW=planweb/v1
P="$H/?rest_route=/$PW"
AJ=(-b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json')

screen planweb planweb-app planweb-admin
screen 'planweb&tab=entries' planweb-app planweb-admin
screen 'planweb&tab=settings' planweb-app planweb-admin
want 200 "admin.php?page=planweb&ui=next&tab=entries (editor)" -b /tmp/cj-rw-ed "$H/wp-admin/admin.php?page=planweb&ui=next&tab=entries"
if grep -q 'id="planweb-app"' /tmp/rw.out; then echo "ok   an editor gets the new Entries screen"; else echo "FAIL editor: Entries screen not drawn"; FAIL=1; fi
want 200 "admin.php?page=planweb&ui=next&tab=settings (editor)" -b /tmp/cj-rw-ed "$H/wp-admin/admin.php?page=planweb&ui=next&tab=settings"
if grep -q 'id="planweb-app"' /tmp/rw.out; then echo "FAIL editor: Settings drawn without manage_options"; FAIL=1; else echo "ok   an editor gets the current screen, not Settings"; fi

for r in dashboard 'entries&when=all' settings services; do route "$PW/$r"; done
post "$PW/dashboard/count" '{}'
post "$PW/entries" '{"date":"2026-11-30","title":"MP model city","type":"campaign"}'
post "$PW/settings/switch" '{"on":true}'
post "$PW/import" '{}'
want 403 "GET /$PW/settings editor" -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" "$P/settings"

# The Dashboard reads the stored counts: not counting, eight tiles for each range (the import ran in smoke.sh).
want 200 "GET /$PW/dashboard (counts stored)" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/dashboard"
if python3 -c 'import json,sys; d=json.load(open("/tmp/rw.out")); sys.exit(0 if not d["counting"] and all(len(d["tiles"][str(r)])==9 for r in (7,30,90)) else 1)'; then echo "ok   dashboard: counts read, 9 tiles per range (site schedule included)"; else echo "FAIL dashboard payload"; FAIL=1; fi

# Entries: add, change, refuse a phone link and an editor's Approved, Trash; the list pages and filters on the server.
want 200 "POST /$PW/entries add (administrator)" -X POST "${AJ[@]}" -d '{"date":"2026-11-30","title":"MP model city","type":"campaign","channel":"x","status":"idea","link":"https://x.com/animal_writes_"}' "$P/entries"
ID=$(python3 -c 'import json; print(json.load(open("/tmp/rw.out"))["entry"]["id"])' 2>/dev/null)
if [ -n "$ID" ]; then echo "ok   entry $ID added through planweb_save_entry()"; else echo "FAIL no entry id"; FAIL=1; ID=0; fi
want 200 "POST /$PW/entries/$ID change" -X POST "${AJ[@]}" -d '{"date":"2026-12-01","title":"MP model city","type":"campaign","status":"draft"}' "$P/entries/$ID"
if [ "$(wp post meta get "$ID" planweb_date)" = "2026-12-01" ]; then echo "ok   entry $ID moved to 1 Dec"; else echo "FAIL entry $ID not changed"; FAIL=1; fi
want 400 "POST /$PW/entries wa.me link refused" -X POST "${AJ[@]}" -d '{"date":"2026-11-30","title":"Call","type":"social","link":"https://wa.me/919999999999"}' "$P/entries"
want 403 "POST /$PW/entries/$ID Approved by an editor" -X POST -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" -H 'Content-Type: application/json' -d '{"date":"2026-12-01","title":"MP model city","type":"campaign","status":"approved"}' "$P/entries/$ID"
want 200 "GET /$PW/entries search, type and dates" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/entries&search=model&type=campaign&when=all&sort=title&dir=desc"
if python3 -c 'import json,sys; d=json.load(open("/tmp/rw.out")); sys.exit(0 if d["total"]==1 and d["rows"][0]["title"]=="MP model city" else 1)'; then echo "ok   entries: search and filter on the server"; else echo "FAIL entries filter"; FAIL=1; fi
want 200 "GET /$PW/entries waiting view" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/entries&view=waiting"
if grep -q '"MP model city"' /tmp/rw.out; then echo "ok   entries: the draft is waiting for approval"; else echo "FAIL entries waiting view"; FAIL=1; fi
# One entry by its address (Entries &edit=ID): signed out 401, subscriber 403, the entry 200; after the Trash, 404.
want 401 "GET /$PW/entries/$ID signed out" "$P/entries/$ID"
want 403 "GET /$PW/entries/$ID subscriber" -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$P/entries/$ID"
want 200 "GET /$PW/entries/$ID" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/entries/$ID"
grep -q '"title":"MP model city"' /tmp/rw.out && echo "ok   one entry by its id" || { echo "FAIL one entry by its id"; FAIL=1; }
# The Dashboard's month grid: the entry (dated 2026-12-01 by now) is in December with its form's address.
want 401 "GET /$PW/month signed out" "$P/month&m=2026-12"
want 403 "GET /$PW/month subscriber" -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$P/month&m=2026-12"
want 400 "GET /$PW/month bad month" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/month&m=2026-13"
want 200 "GET /$PW/month" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/month&m=2026-12"
if python3 -c 'import json,sys; d=json.load(open("/tmp/rw.out")); sys.exit(0 if d["month"]=="2026-12" and any(e["title"]=="MP model city" and "edit=" in e["editUrl"] for e in d["entries"]) else 1)'; then echo "ok   month: the entry with its edit address"; else echo "FAIL month grid data"; FAIL=1; fi
want 403 "POST /$PW/entries/$ID/trash subscriber" -X POST -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$P/entries/$ID/trash"
want 200 "POST /$PW/entries/$ID/trash" -X POST "${AJ[@]}" "$P/entries/$ID/trash"
if [ "$(wp post get "$ID" --field=post_status)" = "trash" ]; then echo "ok   entry $ID in the Trash (not deleted)"; else echo "FAIL entry $ID not in the Trash"; FAIL=1; fi
want 404 "GET /$PW/entries/$ID in the Trash" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/entries/$ID"

# Settings: an unchanged Save of the switch and a second import change nothing (Dates itself is never written).
BEFORE=$(optmd5 planweb_on aw_team_dates)
want 200 "POST /$PW/settings/switch unchanged Save" -X POST "${AJ[@]}" -d '{"on":true}' "$P/settings/switch"
grep -q '"saved":false' /tmp/rw.out && echo "ok   unchanged Save reports nothing saved" || { echo "FAIL unchanged Save wrote"; FAIL=1; }
# Core's one-time admin steps may have added Dates rows since the first import: the route takes those in first, then
# a run on the same Dates adds nothing.
want 200 "POST /$PW/import (rows added since the first import)" -X POST "${AJ[@]}" "$P/import"
PW_N=$(wp post list --post_type=planweb_entry --post_status=any --format=count)
want 200 "POST /$PW/import again" -X POST "${AJ[@]}" "$P/import"
PW_M=$(wp post list --post_type=planweb_entry --post_status=any --format=count)
[ "$PW_N" = "$PW_M" ] && echo "ok   import again adds nothing ($PW_M entries)" || { echo "FAIL import again added entries: $PW_N -> $PW_M"; FAIL=1; }
same "PlanWeb Settings" "$BEFORE" "$(optmd5 planweb_on aw_team_dates)"
if wp eval 'exit( in_array( "planweb_on", kits_settings_transfer_options(), true ) ? 0 : 1 );'; then echo "ok   planweb_on is in the Kits export"; else echo "FAIL planweb_on missing from the Kits export"; FAIL=1; fi

# Several channels and Done (14 Nov 2026): an entry saved with two channels keeps both; an administrator closes it as Done.
want 200 "POST /$PW/entries with two channels" -X POST "${AJ[@]}" -d '{"date":"2026-11-30","title":"[X + LinkedIn] Two channels","type":"social","channels":["x","linkedin"],"status":"idea"}' "$P/entries"
if python3 -c 'import json,sys; e=json.load(open("/tmp/rw.out"))["entry"]; sys.exit(0 if e["channels"]==["x","linkedin"] and e["channelText"]=="X, LinkedIn" else 1)'; then echo "ok   entry with X and LinkedIn"; else echo "FAIL two channels not kept"; FAIL=1; fi
CH=$(python3 -c 'import json; print(json.load(open("/tmp/rw.out"))["entry"]["id"])' 2>/dev/null)
want 200 "POST /$PW/entries/$CH Done" -X POST "${AJ[@]}" -d '{"date":"2026-11-30","title":"[X + LinkedIn] Two channels","type":"social","status":"done","channels":["linkedin"]}' "$P/entries/$CH"
if [ "$(wp post meta get "$CH" planweb_status)" = "done" ] && [ "$(wp post term list "$CH" planweb_channel --field=slug)" = "linkedin" ]; then echo "ok   entry $CH Done, channels changed to LinkedIn only"; else echo "FAIL entry $CH status or channels"; FAIL=1; fi
if wp eval 'exit( planweb_is_overdue( array( "date" => "2020-01-01", "status" => "done" ), "2026-11-14" ) ? 1 : 0 );'; then echo "ok   a past Done entry is not overdue"; else echo "FAIL Done counted overdue"; FAIL=1; fi

# The calendar feed: no key, no feed; a key from Settings; the right key gives text/calendar without sign-in; a wrong
# key looks like no route; an unknown filter is refused; a new key retires the old address.
F="$H/?rest_route=/$PW/calendar.ics"
want 404 "GET calendar.ics, feed off" "$F&key=none"
post "$PW/settings/feed" '{"action":"new"}'
want 403 "POST /$PW/settings/feed editor" -X POST -b /tmp/cj-rw-ed -H "X-WP-Nonce: $NE" -H 'Content-Type: application/json' -d '{"action":"new"}' "$P/settings/feed"
want 200 "POST /$PW/settings/feed new key" -X POST "${AJ[@]}" -d '{"action":"new"}' "$P/settings/feed"
KEY=$(wp option get planweb_feed_key)
want 404 "GET calendar.ics wrong key" "$F&key=wrong$KEY"
want 404 "GET calendar.ics no key" "$F"
want 400 "GET calendar.ics unknown type" "$F&key=$KEY&type=spam"
CT=$(curl -s -o /tmp/rw.out -w '%{http_code} %{content_type}' "$F&key=$KEY&type=marketing")
if [ "$CT" = "200 text/calendar; charset=utf-8" ] && head -c 15 /tmp/rw.out | grep -q 'BEGIN:VCALENDAR' && grep -q 'END:VCALENDAR' /tmp/rw.out; then echo "ok   calendar.ics: $CT"; else echo "FAIL calendar.ics: $CT"; head -c 300 /tmp/rw.out; echo; FAIL=1; fi
if grep -q 'Two channels' /tmp/rw.out && ! grep -qi 'reminder' /tmp/rw.out; then echo "ok   calendar.ics marketing only: the social entry, no reminders"; else echo "FAIL calendar.ics marketing filter"; FAIL=1; fi
want 200 "GET calendar.ics status=open" "$F&key=$KEY&status=open"
if ! grep -q 'Two channels' /tmp/rw.out; then echo "ok   calendar.ics open: the Done entry left out"; else echo "FAIL calendar.ics status filter"; FAIL=1; fi
want 200 "POST /$PW/settings/feed another key" -X POST "${AJ[@]}" -d '{"action":"new"}' "$P/settings/feed"
want 404 "GET calendar.ics old key" "$F&key=$KEY"
want 200 "POST /$PW/settings/feed off" -X POST "${AJ[@]}" -d '{"action":"off"}' "$P/settings/feed"
[ -z "$(wp option get planweb_feed_key 2>/dev/null)" ] && echo "ok   feed off: no key" || { echo "FAIL feed key left"; FAIL=1; }

# The site's own schedule (one schedule, 14 Nov 2026): a scheduled post shows in the month grid, the Dashboard and the
# feed, read live; a future post past its time is "Missed schedule"; nothing is copied into PlanWeb.
SP=$(wp post create --post_type=post --post_status=future --post_date="$(date -d '+3 days' '+%Y-%m-%d') 10:00:00" --post_title='Scheduled resource' --porcelain)
SPM=$(date -d '+3 days' '+%Y-%m')
PW_E=$(wp post list --post_type=planweb_entry --post_status=any --format=count)
want 200 "GET /$PW/month with the site schedule" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/month&m=$SPM"
if python3 -c 'import json,sys; d=json.load(open("/tmp/rw.out")); sys.exit(0 if any(i["title"]=="Scheduled resource" and i["status"]=="scheduled" and i["source"]=="post" for i in d["site"]) else 1)'; then echo "ok   month: the scheduled post beside the entries"; else echo "FAIL month: scheduled post missing"; FAIL=1; fi
want 200 "GET /$PW/dashboard site schedule" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$P/dashboard"
grep -q '"Scheduled resource"' /tmp/rw.out && echo "ok   dashboard: site schedule lists the scheduled post" || { echo "FAIL dashboard site schedule"; FAIL=1; }
wp eval "global \$wpdb; \$wpdb->update( \$wpdb->posts, array( 'post_date' => '$(date -d '-2 days' '+%Y-%m-%d') 09:00:00', 'post_date_gmt' => '$(date -u -d '-2 days' '+%Y-%m-%d') 03:30:00' ), array( 'ID' => $SP ) ); clean_post_cache( $SP );"
MS=$(wp eval 'echo planweb_site_counts( wp_date( "Y-m-d" ) )["missed"];')
[ "${MS:-0}" -ge 1 ] && echo "ok   a future post past its time counts as missed ($MS)" || { echo "FAIL missed schedule not counted"; FAIL=1; }
[ "$(wp post list --post_type=planweb_entry --post_status=any --format=count)" = "$PW_E" ] && echo "ok   nothing copied into PlanWeb" || { echo "FAIL site schedule wrote entries"; FAIL=1; }
wp post delete "$SP" --force --quiet

# The one-time moves of 14 Nov: a bracket title gets every channel it names; a second run changes nothing.
MG=$(wp eval '$id = planweb_save_entry( array( "title" => "[Instagram + Facebook + Threads] Moves", "date" => "2026-11-29", "type" => "social", "channel" => "instagram", "status" => "idea" ) ); delete_option( PLANWEB_MIGRATE ); $r = planweb_migrate_1114(); $e = planweb_entry( $id ); $again = planweb_migrate_1114(); echo in_array( $id, $r["channels"], true ) && array( "instagram", "facebook", "threads" ) === $e["channels"] && array() === $again["channels"] ? "ok" : "bad";')
[ "$MG" = "ok" ] && echo "ok   bracket titles moved to every channel they name" || { echo "FAIL channel move: $MG"; FAIL=1; }

wp plugin deactivate planweb --quiet
