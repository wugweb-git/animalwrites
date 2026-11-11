# shellcheck shell=bash
# DirectoryWeb's new screens (Animal Writes Core, includes/directoryweb-*.php), sourced by smoke.sh with $H, $NA, $NS,
# the cookie jars /tmp/cj-ci and /tmp/cj-rw-sub, and want, screen, route, post, optmd5 and same.
# Animal Writes Core is active already (smoke.sh). The Claims routes only read: claims are reviewed on Directory → Claims.

# Dashboard and Settings: pages with no menu entry.
screen directoryweb directoryweb-app directoryweb-admin
screen directoryweb-settings directoryweb-app directoryweb-admin

# Claims, on Directory → Claims's own address.
C="$H/wp-admin/edit.php?post_type=aw_listing&page=aw-claims"
want 200 "Directory → Claims &ui=next (administrator)" -b /tmp/cj-ci "$C&ui=next"
if grep -q 'id="directoryweb-app"' /tmp/rw.out && grep -q 'directoryweb-admin-js' /tmp/rw.out && grep -q 'kits-shell-js' /tmp/rw.out; then echo "ok   Claims &ui=next draws the new screen"; else echo "FAIL Claims &ui=next: new screen not drawn"; FAIL=1; fi
want 200 "Directory → Claims (current screen)" -b /tmp/cj-ci "$C"
if grep -q 'id="directoryweb-app"' /tmp/rw.out; then echo "FAIL Claims: the current screen is not the default any more"; FAIL=1; else echo "ok   Claims: current screen stays the default"; fi
want 403 "Directory → Claims &ui=next (subscriber)" -b /tmp/cj-rw-sub "$C&ui=next"

# Routes: every GET, and every write refused signed out and for a subscriber.
route directoryweb/v1/dashboard
route directoryweb/v1/claims
route "directoryweb/v1/claims&status=all&days=30&sort=listing&dir=asc"
route directoryweb/v1/settings
post directoryweb/v1/dashboard/count '{}'
post directoryweb/v1/settings/claim-form '{"form":0}'
# No route decides, applies or undoes a claim: those addresses are not there, even for an administrator.
want 404 "POST /directoryweb/v1/claims/1/decide administrator (no such route)" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"status":"rejected"}' "$H/?rest_route=/directoryweb/v1/claims/1/decide"
want 404 "POST /directoryweb/v1/claims/1/undo administrator (no such route)" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{"field":"l_email"}' "$H/?rest_route=/directoryweb/v1/claims/1/undo"
want 404 "POST /directoryweb/v1/claims/1 administrator (read only)" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d '{}' "$H/?rest_route=/directoryweb/v1/claims/1"
want 401 "POST /directoryweb/v1/settings/claim-form cookie, no nonce" -X POST -b /tmp/cj-ci -H 'Content-Type: application/json' -d '{"form":0}' "$H/?rest_route=/directoryweb/v1/settings/claim-form"
want 200 "POST /directoryweb/v1/dashboard/count administrator" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/directoryweb/v1/dashboard/count"
want 404 "GET /directoryweb/v1/claims/999999999 administrator (no such claim)" -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/directoryweb/v1/claims/999999999"

# No setting lost: Save the claim form as the screen shows it; aw_ff_forms stays byte for byte the same.
BEFORE=$(optmd5 aw_ff_forms)
curl -s -b /tmp/cj-ci -H "X-WP-Nonce: $NA" "$H/?rest_route=/directoryweb/v1/settings" > /tmp/dw-settings.json
DWF=$(python3 -c "import json; s=json.load(open('/tmp/dw-settings.json')); print(json.dumps({'form': next(v for v in s['services'] if v['slug']=='fluentforms')['claimForm']['value']}))" 2>/dev/null)
if [ -n "$DWF" ]; then
  want 200 "POST /directoryweb/v1/settings/claim-form unchanged Save (administrator)" -X POST -b /tmp/cj-ci -H "X-WP-Nonce: $NA" -H 'Content-Type: application/json' -d "$DWF" "$H/?rest_route=/directoryweb/v1/settings/claim-form"
  grep -q '"saved":false' /tmp/rw.out && echo "ok   unchanged claim form Save reports nothing saved" || { echo "FAIL unchanged claim form Save wrote"; FAIL=1; }
else
  echo "FAIL directoryweb/v1/settings: no Fluent Forms service row"; FAIL=1
fi
same "DirectoryWeb Settings" "$BEFORE" "$(optmd5 aw_ff_forms)"
python3 -c "import json,sys; s=json.load(open('/tmp/dw-settings.json')); sys.exit(0 if len(s['groups'])==7 and len(s['services'])==9 else 1)" && echo "ok   DirectoryWeb Settings: 9 services, 7 groups" || { echo "FAIL DirectoryWeb Settings: services or groups missing"; FAIL=1; }
if wp eval 'exit( in_array( "aw_ff_forms", kits_settings_transfer_options(), true ) ? 0 : 1 );'; then echo "ok   aw_ff_forms is in the Kits export"; else echo "FAIL aw_ff_forms missing from the Kits export"; FAIL=1; fi
