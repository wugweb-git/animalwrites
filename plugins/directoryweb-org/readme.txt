=== DirectoryWeb ===
Contributors: wugweb
Tags: directory, listings, map, claims, near me
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a directory of organisations with a free map, Near me, and listings the organisations can claim, then you review. No API key.

== Description ==

DirectoryWeb turns a WordPress site into a directory people can trust. You add listings, or let the organisations claim theirs, and visitors find them by list, by place, or on a map.

* Listings with types, services, hours, contact details and photos.
* Pages for the whole directory, for a place, and for a place and type, with counts.
* A map with a Near me button. It asks for the visitor's location only when they tap it, and uses free OpenStreetMap-based tiles: no API key and no map bill.
* Claims: an organisation sends a claim, you see each changed field and approve or reject it.
* A Claimed mark (the organisation confirmed the details).
* A Dashboard, a Claims list and Settings in one admin app.

The claim feature uses the free [Fluent Forms](https://wordpress.org/plugins/fluentform/) plugin: install and activate Fluent Forms, build your claim form, and choose it in DirectoryWeb → Settings. Everything else — the listings, directory pages, map and Near me — works without Fluent Forms; until a claim form is set the Claims screen simply shows how to set one up.

This first release is being separated from the site it was built on. Some parts are still being made general; see the changelog.

== External services ==

DirectoryWeb can contact these third-party services. Address lookup and PIN lookup are off until you switch them on in Settings. The list below is generated from the plugin's registry.

* nominatim (nominatim.openstreetmap.org), your server: turns a listing address into map coordinates. Sent: the address, PIN or district text, one request a second. Terms: https://operations.osmfoundation.org/policies/nominatim/ Privacy: https://osmfoundation.org/wiki/Privacy_Policy
* postalpincode (api.postalpincode.in), your server, India only: looks up the district and state of a PIN code. Sent: the PIN code. Terms and privacy: https://www.postalpincode.in/
* openfreemap (tiles.openfreemap.org), visitor browser: map tiles and style. Sent: the visitor's IP address and the area viewed, when a map is shown. Terms: https://openfreemap.org/tos/ Privacy: https://openfreemap.org/privacy/
* osm-tiles (tile.openstreetmap.org), editor browser: map tiles in the listing editor. Sent: the editor's IP address and the area viewed. Terms: https://operations.osmfoundation.org/policies/tiles/

The Near me button uses the visitor's browser location; the position is not sent to your server or any service unless the visitor is searching by PIN.

== Third-party libraries ==

* Leaflet 1.9.4, BSD-2-Clause (assets/vendor/leaflet-1.9.4).
* MapLibre GL JS 4.7.1, BSD-3-Clause (assets/vendor/maplibre-gl-4.7.1).
* The admin screens are built from src/ and packages/directoryweb-shell/src with @wordpress/scripts (npm run build); the compiled files are in build/.

== Installation ==

1. Upload the plugin and activate it.
2. Open DirectoryWeb in the admin menu and add your first listing.

== Changelog ==

= 1.0.0 =
* First standalone release: no other plugin is needed.
