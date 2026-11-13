=== PlanWeb ===
Contributors: wugweb
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The team's calendar inside WordPress. A Kits plugin.

== Description ==

PlanWeb keeps the team's dates and plans: content, social, email, campaign and reminder entries, each with one or more channels, an owner and a status (idea, draft, approved, due, sent, done). Nothing in it posts, schedules out or sends anything.

* PlanWeb → Calendar: a month grid, a list with filters, and a form to add or edit an entry.
* A Dashboard box with the next 7 days.
* Entries are a private post type in the REST API (`/wp/v2/planweb-entries`), readable only by people who work with them.
* Links may point only to this site or to social sites; titles and notes may hold no email address or phone number.
* Only administrators set Approved or Sent. Done closes an entry that was finished but never sent: it is not overdue and not counted as sent.
* A read-only calendar feed (iCalendar) for Google Calendar, Apple Calendar or Outlook, at `/wp-json/planweb/v1/calendar.ics?key=…`. The key is made in PlanWeb → Settings → Calendar feed; there are filters by type (`type=marketing`) and status (`status=open`). The feed holds date, time, title, type, channels, status and link, never the note.

On a site with the earlier Dates list (option `aw_team_dates`), switching PlanWeb on copies every date once and leaves the list unchanged. `wp planweb verify` compares the two one for one.

== Changelog ==

= 0.2.0 =
* The read-only calendar feed, with a key made in Settings and filters by type and status.
* An entry may have several channels; Threads and Bluesky are on the list.
* The Done status.
* Connected services: Metricool and Postiz rejected; Meta Business Suite and Buffer (free) listed as used outside PlanWeb.
* Opening the Dashboard writes nothing; the permission check without Kits Core matches Kits Core's.

= 0.1.0 =
* The store, the import from Dates, the Calendar screen and the Dashboard box.
