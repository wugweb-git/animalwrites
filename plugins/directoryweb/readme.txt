=== DirectoryWeb ===
Contributors: wugweb
Tags: directory, listings, places, claims, map
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A directory of organisations in WordPress: listings with their places and types, a map and Near me, claims from the organisations themselves, and "Find help near you".

== Description ==

DirectoryWeb is the Directory of an Animal Writes site, as its own plugin.

= Listings, places and types =

* The listing post type, its fields (eight field groups), types and services.
* States and districts from a bundled list of local government places, with counts, PIN code lookup and old address redirects.
* Directory pages for the whole list, a place, and a place and type.
* A map with Near me, and an admin map beside the latitude and longitude fields.
* Coordinates from an address (one request a second) and phone, hours and registration helpers.

= Claims and records =

* Claims from organisations through a Fluent Forms form, reviewed field by field.
* Claimed and Verified marks (government records) and unverified values.
* The people of an organisation from FluentCRM.

= Find help =

* "Find help near you" on a page, and "Featured in": News, Library, Resources and Press items that mention an organisation.

= For the team =

* DirectoryWeb in the admin menu: Dashboard, Claims and Settings on the Kits admin shell, and Place intros.

= Works with =

* Kits Core (required: the admin shell, list tables, icons and dates).
* Animal Writes Core (while it is active it loads DirectoryWeb and supplies the site's content types, among them the listing type). RankWeb reads the Directory for titles, schema and the sitemap; SourceWeb adds listings and keeps the change log.

== Installation ==

1. Activate Kits Core first. While Animal Writes Core is active it loads DirectoryWeb from the plugins folder, so the Directory works before the plugin is activated on its own.
2. Activate DirectoryWeb under Plugins to own the Directory's rewrite rules and capability.

== Changelog ==

= 1.1.0 =
* The listing's data model moves in from Animal Writes Core: the 48 listing fields and their rules, the phone and opening-hours lists, the never-publish list, where a listing's details come from, and the daily re-check flags. Same keys and stored values; nothing changes on the site.

= 1.0.0 =
* The Directory moves out of Animal Writes Core into its own plugin. Same pages, data, field groups, blocks, shortcodes and routes; nothing changes on the site.

== Upgrade Notice ==

= 1.1.0 =
The listing fields are registered by DirectoryWeb now; no change on the site.

= 1.0.0 =
First release as its own plugin; no change on the site.
