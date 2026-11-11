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
if python3 -c 'import json,sys; d=json.load(open("/tmp/rw.out")); sys.exit(0 if not d["counting"] and all(len(d["tiles"][str(r)])==8 for r in (7,30,90)) else 1)'; then echo "ok   dashboard: counts read, 8 tiles per range"; else echo "FAIL dashboard payload"; FAIL=1; fi

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
want 403 "POST /$PW/entries/$ID/trash subscriber" -X POST -b /tmp/cj-rw-sub -H "X-WP-Nonce: $NS" "$P/entries/$ID/trash"
want 200 "POST /$PW/entries/$ID/trash" -X POST "${AJ[@]}" "$P/entries/$ID/trash"
if [ "$(wp post get "$ID" --field=post_status)" = "trash" ]; then echo "ok   entry $ID in the Trash (not deleted)"; else echo "FAIL entry $ID not in the Trash"; FAIL=1; fi

# Settings: an unchanged Save of the switch and a second import change nothing (Dates itself is never written).
BEFORE=$(optmd5 planweb_on aw_team_dates)
want 200 "POST /$PW/settings/switch unchanged Save" -X POST "${AJ[@]}" -d '{"on":true}' "$P/settings/switch"
grep -q '"saved":false' /tmp/rw.out && echo "ok   unchanged Save reports nothing saved" || { echo "FAIL unchanged Save wrote"; FAIL=1; }
PW_N=$(wp post list --post_type=planweb_entry --post_status=any --format=count)
want 200 "POST /$PW/import again" -X POST "${AJ[@]}" "$P/import"
PW_M=$(wp post list --post_type=planweb_entry --post_status=any --format=count)
[ "$PW_N" = "$PW_M" ] && echo "ok   import again adds nothing ($PW_M entries)" || { echo "FAIL import again added entries: $PW_N -> $PW_M"; FAIL=1; }
same "PlanWeb Settings" "$BEFORE" "$(optmd5 planweb_on aw_team_dates)"
if wp eval 'exit( in_array( "planweb_on", kits_settings_transfer_options(), true ) ? 0 : 1 );'; then echo "ok   planweb_on is in the Kits export"; else echo "FAIL planweb_on missing from the Kits export"; FAIL=1; fi

wp plugin deactivate planweb --quiet
