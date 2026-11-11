=== PlanWeb ===
Contributors: wugweb
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The team's calendar inside WordPress. A Kits plugin.

== Description ==

PlanWeb keeps the team's dates and plans: content, social, email, campaign and reminder entries, each with a channel, an owner and a status (idea, draft, approved, due, sent). Nothing in it posts, schedules out or sends anything.

* PlanWeb → Calendar: a month grid, a list with filters, and a form to add or edit an entry.
* A Dashboard box with the next 7 days.
* Entries are a private post type in the REST API (`/wp/v2/planweb-entries`), readable only by people who work with them.
* Links may point only to this site or to social sites; titles and notes may hold no email address or phone number.
* Only administrators set Approved or Sent.

On a site with the earlier Dates list (option `aw_team_dates`), switching PlanWeb on copies every date once and leaves the list unchanged. `wp planweb verify` compares the two one for one.

== Changelog ==

= 0.1.0 =
* The store, the import from Dates, the Calendar screen and the Dashboard box.
