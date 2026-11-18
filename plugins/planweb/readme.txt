=== PlanWeb ===
Contributors: wugweb
Requires at least: 7.1
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

== Installation ==

1. Upload the `planweb` folder to `/wp-content/plugins/`, or install PlanWeb through the Plugins screen in WordPress.
2. Activate PlanWeb through the Plugins screen.
3. Open PlanWeb → Calendar to add entries, and PlanWeb → Settings → Calendar feed to make the read-only feed key.

On activation PlanWeb registers the private `planweb_entry` post type and grants the `edit_planweb_entries` capability set (plus `planweb_approve`, administrators only) to administrators and editors. Deactivating removes those capabilities and keeps every entry.

== Frequently Asked Questions ==

= Does PlanWeb post or send anything? =

No. PlanWeb only plans and holds entries. It never publishes posts, schedules anything out, or sends email.

= Does it need another plugin? =

The Calendar screen works on its own. The Dashboard, Entries and Settings screens are drawn with the Kits admin shell (Kits Core); without Kits Core those screens show a notice and the standalone Calendar screen remains available.

= How does the calendar feed work? =

PlanWeb serves a read-only iCalendar feed at `/wp-json/planweb/v1/calendar.ics?key=…`. Make the secret key in PlanWeb → Settings → Calendar feed, then subscribe in Google Calendar, Apple Calendar or Outlook. The feed holds date, time, title, type, channels, status and link only — never notes, names, emails or phone numbers. A missing or wrong key returns 404.

= What data does it store, and what happens on uninstall? =

Entries are a private post type, readable only by signed-in people who hold the capability. On uninstall nothing is deleted unless you tick "Delete this plugin's data when it is deleted"; when ticked, a compressed backup is written first and the delete is aborted if the backup fails.

== External Services ==

PlanWeb does not connect to, call, or send any data to an external service, and it makes no outbound HTTP requests.

The iCalendar feed it serves is read by your own calendar app (Google, Apple or Outlook) pulling from this site; the plugin itself never contacts those apps. Any social tools named elsewhere in PlanWeb (for example Meta Business Suite or Buffer) are used by the team outside the plugin — nothing is sent to them from PlanWeb.

== Changelog ==

= 0.2.0 =
* The read-only calendar feed, with a key made in Settings and filters by type and status.
* An entry may have several channels; Threads and Bluesky are on the list.
* The Done status.
* Connected services: Metricool and Postiz rejected; Meta Business Suite and Buffer (free) listed as used outside PlanWeb.
* Opening the Dashboard writes nothing; the permission check without Kits Core matches Kits Core's.

= 0.1.0 =
* The store, the import from Dates, the Calendar screen and the Dashboard box.
