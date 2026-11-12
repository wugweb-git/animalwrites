=== Animal Writes Core ===
Contributors: wugweb
Tags: directory, campaigns, forms, content types, nonprofit
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Animal Writes site: content types and fields, the Directory with its map and places, campaigns and letters, forms and people, card pictures and the jobs that keep the site running.

== Description ==

Animal Writes Core holds everything that is particular to the Animal Writes site and keeps it independent of the theme.

* Content types and their fields: News, Library, Resources, Opportunities, Campaigns, Press coverage and Directory listings, each in its own address space.
* The Directory: states, districts and listing types, verified listings first, claims from organisations, and DirectoryWeb (Listings, Claims, Place intros) for the team.
* Campaigns and letters: countdowns, letter grounds and the letter report.
* Forms and people: Fluent Forms blocks for volunteering, claims and contact, and their FluentCRM records.
* Media: card pictures, media groups, filled-in names and alternative text for new uploads, duplicate files and picture reuse.
* Running the site: scheduled tasks, the site check, the cache purge after each deploy, CDN lifetimes and branded error pages.

Needs Kits Core for the shared admin screens. It loads SourceWeb from its own folder when SourceWeb is not active on its own.

== Uninstall ==

Deleting the plugin deletes no data: content types, listings, campaigns, letters, places, form records, media groups and every table and setting it keeps stay, so reinstalling finds everything as it was.

== Changelog ==

= 1.0.0 =
* Current release.
