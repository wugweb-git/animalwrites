# PlanWeb: the team's calendar as its own Kits plugin (plan)

Owner, 11 Nov 2026: Dates moves into its own Kits plugin, **PlanWeb**, which holds the team's schedule and the content
and marketing calendar, and runs a scheduler for internal jobs and reminders. **This is step 1, a plan only. No code
until the owner says yes.**

Sources: the current code (`plugins/animal-writes-core/includes/team-dates.php` and `admin-home.php` on
`wordpress-dev` at `b0512a4e`; live is `637e9e64`, which `wordpress-dev` contains), `docs/pending.md` (Dates), and the
Notion page "Martech status, Dates spec and 11–24 Nov plan" (10 Nov, updated 11 Nov). The admin UI is the shared Kits
Core shell (owner, 11 Nov), being specified as `docs/admin-ui-spec.md` and `docs/kits-plugin-ui.md`. Neither was on
any branch on 11 Nov, so section 4 follows the brief's outline of them. Where they differ, they win.

---

## 0. What exists today (checked in the code, 11 Nov 2026)

- **Menu:** Kits → Dates (`admin.php?page=aw-dates`), not Tools → Dates. Since 10 Nov (642e7e76) it is one of the seven
  Animal Writes screens at the end of the Kits menu. Capability `edit_others_posts` (editors and administrators).
  `docs/pending.md` said "Animal Writes → Dates" (from before 10 Nov); corrected to Kits → Dates on 11 Nov.
- **Storage:** one option, `aw_team_dates` (autoload off). It holds a list of `{date Y-m-d, title, note}`. Entries have
  no ID: delete uses the list index plus the date.
- **One-time writers** (each runs once on `admin_init` and sets a flag):
  - the seed: 5 Nov Lab, 17 Nov Directory re-check;
  - `aw_team_dates_links_read`: the monthly Search Console Links read on the 1st, added as 12 separate one-off
    entries (Nov 2026–Oct 2027);
  - `aw_team_dates_1101`, `aw_team_dates_1101b`: the search-analysis dates and the Supreme Court retitle;
  - `aw_team_dates_faq_holdout_off`: drops the FAQ holdout date;
  - `aw_team_dates_links_note_1111`: from the 11 Nov Kits menu audit (`e4bcf889`, `305b7e6a`). It rewrites "AW SEO
    Plus → Search Insights → Links" to "RankWeb → Search Insights → Links" in the notes of the monthly Links entries,
    working on the list as stored.
- **Screen:** a month grid, an "All dates" table with Remove links, and an Add form. The handlers are
  `admin_post_aw_dates_add` and `admin_post_aw_dates_delete`.
- **Dashboard widget** `aw_team_dates`: up to 6 dates from 3 days ago to 60 days ahead, shown as calendar tiles with an
  inline `<style>`.
- **Other readers:**
  - Kits → Site: Animal Writes (`admin-home.php:166`) shows "Next: …" from `aw_team_dates()`;
  - `aw_core_screens()` lists `aw-dates`.
- **Docs and CI that name it:**
  - `docs/plugin-map.md`, `docs/edit-screens-map.md`, `docs/component-map.md`, `docs/pending.md`;
  - `data/edit-screen-map.json`;
  - both `kits-registry.json` copies (the `aw-dates-*` tile classes);
  - `tools/ci/settings_parts_baseline.txt` (the admin page and its `<style>` block).
- **Related data PlanWeb will read, not copy:**
  - Social posts (`AW_SOCIAL_TYPE` in `social-posts.php`): registered only when `aw_social_on()` is true, and the
    module is off on live;
  - campaigns (`aw_campaign`, meta `aw_campaign_open` and `aw_campaign_close`);
  - FluentCRM campaigns;
  - future posts.
- **Background jobs today:** WP-Cron only (`aw_daily`, backups, Search Insights). No plugin of ours calls Action
  Scheduler yet.
- **Mail:** every `wp_mail` goes through MailWeb's daily limit and queue. Priority 1 is "notices", and campaign emails
  keep a reserve.
- **CI:** the Deploy check boots a throwaway WordPress on the GitHub runner (`tools/ci/smoke.sh`) with every repo
  plugin linked in. It also runs pure-PHP tests (for example `plugins/aw-sourcing/tests/run.php`). The deploy is a git
  pull with no build step, so built JS must be committed.

---

## 1. What it holds

### 1.1 Entries (PlanWeb's own data)

| Field | Stored as | Values | Required | Old Dates entries |
|---|---|---|---|---|
| Date | meta `planweb_date` (`Y-m-d`) | any date | yes | their date |
| Time (IST) | meta `planweb_time` (`H:i`, site time zone) | optional | no | empty |
| Title | post title | text, max 120 | yes | their title |
| Note | post content (plain text) | text | no | their note |
| Type | taxonomy `planweb_type` | content, social, email, campaign, reminder | yes | **reminder** |
| Channel | taxonomy `planweb_channel` | Site, X, LinkedIn, Instagram, Facebook, Newsletter, WhatsApp, Reddit, Other | no | empty |
| Owner | taxonomy `planweb_owner` | Owner, Main, WordPress, Martech, Team | no | empty |
| Status | meta `planweb_status` | idea, draft, approved, due, sent | yes, default **idea** | idea |
| Link | meta `planweb_link` | the site's own pages or a social URL | no | empty |
| Repeat | meta `planweb_repeat` | none, weekly, monthly (day of month), yearly, plus optional `planweb_repeat_until` | no | none |
| Flag | meta `planweb_flag` (set by the scheduler only) | none, overdue | — | computed |

Notes on the fields:

- **Due.** "due" is set only by the scheduler (approved → due on the day; section 3). People pick idea, draft,
  approved or sent.
- **Who may approve.** Only administrators may set **approved** and **sent** (capability `planweb_approve`). That is
  the owner's yes, kept in the entry's revision history. Editors can add and edit ideas and drafts.
- **No personal data.** No field takes an email address, a name or a phone number.
  - Owner is one of the five team labels above, never a person.
  - Title and note are refused on save if they contain an email address or a phone-number pattern. The check is
    written so legal references such as "SMWP (C) 5/2025" and "diary no. 41706/2025" pass.
  - Migrated entries skip this check. Any hit is listed for the owner instead.
- **Link.** Only these are accepted:
  - the site's own host (`home_url()`);
  - x.com, twitter.com, linkedin.com, instagram.com, facebook.com, threads.net, reddit.com, and whatsapp.com
    channel links.

  `wa.me` and `tel:` are refused because they carry phone numbers. The check runs in the REST schema, so the UI and
  the API refuse the same things.
- No GA4 or GTM change anywhere in this plugin.

### 1.2 Read-only items (read live, never copied)

| Feed | Read from | Shown as |
|---|---|---|
| Future posts | `WP_Query` with `post_status=future`, every public type (`get_post_types( array( 'public' => true ) )`) | "Scheduled post", Type content, with a link to its edit screen |
| FluentCRM scheduled campaigns | FluentCRM's own model (`FluentCrm\App\Models\Campaign`, status `scheduled`, `scheduled_at`), only when FluentCRM is active | "Newsletter send", Channel Newsletter; title and time only, no recipients |
| Social posts drafts | `AW_SOCIAL_TYPE`, status draft, pending or future, only when `aw_social_on()` | Type social; "Social posts is off" when the module is off |
| Key campaign dates | `aw_campaign` meta `aw_campaign_open` and `aw_campaign_close` | Type campaign: "opens" and "closes" |

How the feeds behave:

- **Read-only and marked.** Feed items carry a "from …" label and a lock icon, and cannot be edited in PlanWeb.
  Clicking one opens the item's own edit screen.
- **Short-lived cache.** Each feed is cached for 5 minutes (a transient per feed and month). The cache is cleared when
  a post, campaign or social post is saved.
- **Missing sources.** If a source is missing (FluentCRM off, Social posts off), the feed shows "not available" and
  says why. It never fails the screen.

---

## 2. Storage

### 2.1 Compared

| | (a) Non-public post type + meta + taxonomies, `show_in_rest` | (b) Custom table (`wp-database`) |
|---|---|---|
| Native WordPress | Yes: core REST (`/wp/v2/planweb-entries`), caps, trash, revisions, WP-CLI `wp post` | Own REST controller, own caps, own CLI, own `dbDelta` schema and upgrades |
| Revisions (who changed what, owner's yes on record) | Yes: title and note; meta through `revisions_enabled` on registered meta (WP 6.4+) | Build it |
| DataViews | Works with core entity records (`@wordpress/core-data`) | Works, through own endpoints |
| Filters (Type, Channel, Owner, Status) | Taxonomies filter in core REST for free; Status through one added collection param | Own SQL |
| Size of data | Tens to a few hundred entries a year: no index problem | Built for millions of rows; not needed |
| Kept out of the public site | `public => false`, no rewrite, not in search or sitemaps (Rank Math lists only public types) | Never public |
| Effort and risk | Low | Higher: schema, migrations, uninstall, security of own SQL |
| Export and backup | In the normal database export | In the export too, but a new table to remember |

### 2.2 Recommendation: (a), a non-public post type

- **Post type.** `planweb_entry` with these settings:
  - `public => false`, `show_ui => false` (PlanWeb's own screens), `show_in_rest => true`,
    `rest_base => planweb-entries`;
  - `capability_type => array( 'planweb_entry', 'planweb_entries' )` with `map_meta_cap`;
  - `supports => title, editor, revisions, custom-fields`.
- **Why taxonomies for Type, Channel and Owner.** Settings can rename or add a value without touching entries. Core
  REST filters by term, and counts come free (the "by channel this month" tile).
- **Why meta for Status.** Status is a fixed list with scheduler rules. A custom `post_status` is poorly supported in
  REST and the editor, so it is not used.
- **Date and time** are registered meta with a REST schema (`format: date`, pattern `^\d{2}:\d{2}$`). The calendar
  asks for one month at a time, using a meta query on `planweb_date` in a REST collection param.
- **Capabilities** are granted on activation:
  - `edit_planweb_entries` to administrator and editor (the same people as `edit_others_posts` today);
  - `planweb_approve` to administrator only;
  - Settings needs `manage_options`.

### 2.3 Migration from Dates (phase 1)

1. **When it runs:**
   - once, on PlanWeb's first activation, or from the button **Settings → Import from Dates**;
   - only after `aw_team_dates_links_note_1111` is set, so the 11 Nov rename is already in the notes;
   - never over SSH, because SSH is read-only on live.
2. **What it reads:** `get_option( 'aw_team_dates' )` as stored, not through `aw_team_dates()`. That function filters
   and sorts, and it can seed the list.
3. **Each element becomes one entry:**
   - date, title and note are copied character for character;
   - Type reminder, Status idea, Repeat none;
   - `planweb_source = aw_team_dates`;
   - `planweb_source_key = sha1( date | title | note | n )`, where n counts identical copies, so two identical entries
     stay two.
4. **The 12 monthly Links reads stay 12 entries.** Folding them into one repeating entry is an owner decision (D2),
   made after the counts match.
5. **Re-running is safe.** A key that already exists is skipped.
6. **Elements that are not valid dates** (the same ones `aw_team_dates()` drops today) are listed on the result screen,
   never silently dropped.
7. **The proof** is a result panel and `wp planweb verify` (read-only, safe on live):
   - stored elements, entries created, skipped duplicates and invalid elements;
   - then a one-for-one table: date, title, note on both sides, with "same" or "differs" per row.
8. **`aw_team_dates` is kept as a backup**, unchanged, until the owner says otherwise:
   - PlanWeb never writes it;
   - Animal Writes Core stops writing it once PlanWeb is active (2.4);
   - the one-time flags stay as they are.

### 2.4 Changes in Animal Writes Core (phase 1, same PR)

- `team-dates.php` checks `defined( 'PLANWEB_VERSION' )`. When PlanWeb is active, the file:
  - registers no menu and no Dashboard widget;
  - runs no add, delete or one-time writer;
  - keeps `aw_team_dates()` as a read-only function for any old caller.
- `admin-home.php` "Next: …" reads `planweb_next()` when it exists, and falls back to `aw_team_dates()` otherwise.
  The Overview row says "Dates moved to PlanWeb" and links there.
- `aw_core_screens()` drops `aw-dates` while PlanWeb is active, so Dates leaves the Kits menu.
- **Redirect.** `admin.php?page=aw-dates` (with `m=YYYY-MM`) redirects to PlanWeb → Calendar for that month. This is a
  hidden page in PlanWeb, using `wp_safe_redirect` and the same capability.
- **Without PlanWeb**, everything works as today.
- **Docs and CI baselines updated in the same change:**
  - `plugin-map.md`, `edit-screens-map.md` and `edit-screen-map.json`;
  - `component-map.md` and both registry copies: the `aw-dates-*` tiles become PlanWeb's widget classes;
  - `settings_parts_baseline.txt` and `pending.md` (Dates section).

---

## 3. Scheduler: Action Scheduler jobs

- **The library: committed inside the plugin (owner, 11 Nov).** The deploy is a git pull with no install step, and no
  plugin in the repo commits a `vendor/` folder, so Composer cannot load it. PlanWeb commits the Action Scheduler library
  at a pinned version in `plugins/planweb/lib/action-scheduler/` and loads it with `require_once` from the main file.
  Action Scheduler registers every copy and runs the newest, so a newer copy from FluentCRM or Fluent Forms still wins,
  and PlanWeb keeps working on its own if both are removed. `lib/` is excluded from PHPCS, Plugin Check and
  `wp i18n make-pot`. (Not chosen: relying on FluentCRM's or Fluent Forms' copy and showing "Scheduler needs FluentCRM
  or Fluent Forms" when neither is active.)
- **Check before phase 3, read-only on live:** which copy and version is active (`ActionScheduler_Versions`), and that
  its queue runs. The site's cron health check (`aw_cron_last`) shows whether WP-Cron is firing.
- **Group and timing.** All jobs are in group `planweb`. Recurring times are in IST and stored as UTC timestamps
  (`wp_timezone()`).
- **Where the handlers live.** They are registered in an always-loaded file, never behind `is_admin()` or `admin_init`:
  the queue runner has no admin screen and no user.
- **Every job is idempotent.** Each writes `planweb_job_{name}` = time, result and counts, and changes only PlanWeb's
  own entries.
- **No job posts, publishes or sends anything outside the site.** The only email is the optional reminder digest to
  the site's own mailbox, off by default (3.1).

| Job | Hook | How often | What it does | If it fails |
|---|---|---|---|---|
| Overdue flags | `planweb_sweep` | Daily 07:30 IST, plus once after each entry save (async) | Sets `planweb_flag = overdue` when the date (and time, if set) has passed and Status is idea, draft, approved or due. Clears the flag when the date moves forward or Status becomes sent. Past reminders follow D3. | Action Scheduler marks it failed and logs the error. The next day's run redoes everything, because the sweep reads all open entries each time. After a failed run, the Dashboard tile and the Kits status row show "Last sweep failed: …"; after 26 h with no success, "late". |
| Approved → due | part of `planweb_sweep` | Same | On the entry's day (time-aware), an **approved** entry becomes **due**, which means "approved and today". The status says to act; nothing is sent. A revision records the change. | Same as above. An entry missed one day is caught the next day, because the rule is "date ≤ today". |
| Reminders before a date | `planweb_remind` | Daily 08:00 IST | Builds the "coming up" list. Lead time per Type is set in Settings; defaults: content, social, email and campaign 3 days, reminder 1 day. Stored for the notice and the Dashboard tile. If the digest email is on, queues **one** email for the day. | Failed: logged, shown on the Dashboard; the next run sends that day's list. Never more than one digest a day, keyed by date (`planweb_digest_sent = Y-m-d`). |
| Repeating entries | `planweb_repeat` | Daily 07:15 IST, plus after a save that changes Repeat | For each entry with Repeat set, makes sure the next 3 occurrences exist as their own entries (`planweb_parent`, key parent + date). Each occurrence can be edited or trashed alone. Stops at `planweb_repeat_until`. A trashed occurrence is not made again. | Same as the sweep: the next run fills any gap. A cap of 50 new entries per run stops a mistake (for example, weekly with no end) from flooding. |
| Import from Dates | `planweb_migrate` | Once (async on activation, or from the button) | Section 2.3. | The result panel shows the error, and the button stays. Re-running is safe. |

### 3.1 Who gets a reminder, and how

- **In WordPress (always on):**
  - a notice on PlanWeb's own pages only, dismissible per user per day, for example "3 items in the next 3 days,
    1 overdue";
  - the Dashboard tiles and the WordPress Dashboard widget, for people with `edit_planweb_entries`.
- **Email digest: off by default.** The owner turns it on in Settings. Turning it on is the owner's yes.
  - The recipient is chosen from the site's own mailboxes (MailWeb's list, for example contact@ or support@), never
    typed in. No personal address is stored.
  - It goes through `wp_mail`, so MailWeb's daily limit and queue apply (priority 1, "notices"). It can never use the
    campaign share.
  - Body: date, title, Type, Status and a link to PlanWeb, from two template files (`templates/emails/base.php`, the
    shared shell with inline CSS, and `templates/emails/digest.php`, which escapes every value). Sent with
    `Content-Type: text/html; charset=UTF-8`. The lead times in the copy come from the same Settings values the job
    uses, so the text cannot drift from what runs.
- **Owner labels.** Main, WordPress and Martech are working sessions, not mailboxes. Their items show up in the lists
  and the notice, filtered by Owner. No email is linked to an Owner label.

### 3.2 Posting to social (option only; the owner decides later)

Phase 5 lists these options; nothing is built before the owner chooses one:

- **Metricool:** connected, but no network is linked yet. Its API can create a post "for review".
- **Postiz:** no connector yet.

Rules for any option:

- An entry gets a "Send to …" button only when it is Type social, Status approved, and the service is connected.
- The button needs `planweb_approve`, and asks for confirmation every time.
- It creates **one** draft or "for review" post in the service. It never publishes directly.
- The scheduler never presses it.

---

## 4. Admin: what PlanWeb registers into the Kits Core shell

**The shared shell (owner, 11 Nov 2026).** The admin UI is one shell in Kits Core, and every Kits plugin uses it. It is
being specified as `docs/kits-plugin-ui.md`, next to `docs/admin-ui-spec.md`; neither was on any branch on 11 Nov. The
shell draws:

- the app header, with tabs for Dashboard, the working list and Settings;
- section chips, tiles and "Select metrics" (saved per user);
- the DataView wrapper for the working list;
- Settings, with Connected services rows that open a DataForm, and admin settings groups;
- a setup flow, notices, and the shared empty, loading and error states;
- the row on Kits → Plugins and the status row on Kits Home.

PlanWeb builds none of these. It registers its parts through Kits Core's PHP and JS API, and serves its data from its
own REST routes under `planweb/v1` (its own namespace; `kits/v1` is Kits Core's), besides the core `/wp/v2/planweb-entries` for the entries. If
`docs/kits-plugin-ui.md` names things differently, it wins and this section is renamed to match.

### 4.1 Screens

| Shell slot | PlanWeb registers | Capability |
|---|---|---|
| Dashboard | the tiles in 4.2 | `edit_planweb_entries` |
| Working list | **Calendar**: the list in 4.3 plus two widgets of its own (month, week) | `edit_planweb_entries` |
| Settings | the services and groups in 4.4 | `manage_options` |
| Status row on Kits Home | "Last sweep: …" from `planweb_job_sweep` (ok, failed, late) | — |
| Hidden page | `aw-dates`, which redirects to Calendar (2.4) | `edit_planweb_entries` |

Header extras:

- the shell's Save area is used only on Settings;
- the date range sets the window for the Dashboard and Calendar (default: this month);
- the primary action is "New entry", which opens the entry DataForm (4.3).

### 4.2 Dashboard tiles

Each tile is registered with a label, info text, a route, a sub-line, a change chip and a named source. Numbers are
cached for 5 minutes (the shell's refresh clears the cache), and a tile without data uses the shell's empty state.

| Tile | Route (`GET`) | Number and sub-line | Change chip | Source named |
|---|---|---|---|---|
| Next 7 days | `planweb/v1/tiles/next7` | count of entries plus feed items from today to +6 days; sub-line "3 social, 2 content…" | vs the same 7 days last week | "PlanWeb entries, scheduled posts, FluentCRM, Social posts, campaigns" (links to Calendar, week) |
| Overdue | `planweb/v1/tiles/overdue` | entries with flag overdue; sub-line the oldest date | vs yesterday | "PlanWeb sweep, last run …" (links to the list filtered by flag) |
| Waiting for approval | `planweb/v1/tiles/waiting` | entries with Status draft | vs last week | "PlanWeb entries" (links to the list filtered by Status draft) |
| By channel this month | `planweb/v1/tiles/channels` | a bar list, count per Channel | vs last month | "PlanWeb entries, this month" |
| Next FluentCRM send | `planweb/v1/tiles/fluentcrm` | date and time of the next scheduled campaign, or "none scheduled" | none | "FluentCRM campaigns (read-only)" (links to FluentCRM) |

Section chips: Coming up (Next 7 days, Overdue) · Needs action (Waiting for approval) · Channels · Email (Next FluentCRM
send).

### 4.3 Calendar (the working list)

**List (the shell's DataView wrapper).**

- **Data:** `planweb/v1/items`, which merges entries and the read-only feeds for the date range (paged and sorted on the
  server). Saved views come from the shell.
- **Columns:**
  - Date (with time);
  - Title, with a lock icon on feed items;
  - Type (mark plus label);
  - Channel, Owner, Status (label), Flag;
  - Link;
  - Source (PlanWeb, scheduled post, FluentCRM, Social posts, campaign).
- **Filters:** Type, Channel, Owner, Status, Flag, and Source (to hide read-only items). Default: from 30 days ago;
  old dates stay visible once cleared.
- **Sort:** date (default, ascending), title, status. Search covers title and note.
- **Row actions:** Edit (entries only), Open (feed items open their own edit screen), Approve (`planweb_approve`), Mark
  sent (`planweb_approve`), Move to Trash.
- **Bulk actions:** set Status (approved and sent only with `planweb_approve`), set Owner, Move to Trash. Feed items
  are never selectable. There is no delete for good.
- **Entry form:** a DataForm (shell) over the fields in 1.1, with the REST checks from 1.1. Revisions open from the
  form.

**Month and week views: PlanWeb's own widgets inside the shell.** The working list's view switch (List · Month · Week)
loads them, and each view has its own address (`&view=month&m=2026-11`).

- **Built from the Kits admin design system's parts** (shipped in Kits Core with the shell): its card, button,
  popover, tooltip, icon and the shared empty, loading and error states. WordPress packages are plumbing only
  (`@wordpress/data`, `api-fetch`, `i18n`, `icons`). If the design system has no day-grid part yet, PlanWeb asks for
  one in Kits Core rather than styling its own.
- **Styling:** layout only (the grid's columns, and greying past days). No colours of our own: the Type marks use the
  design system's tokens. Each Type also has its own icon and an accessible name, so colour is never the only signal
  (WCAG 2.2 AA, 1.4.1). Status is a text label.
- **Data:** the same `planweb/v1/items` route, by date range. Days are buttons in a grid with arrow-key moves and a
  "today" marker (`aria-current="date"`).
- If the shell's working-list part gains a calendar layout by then, PlanWeb uses it instead and drops its own widgets.

### 4.4 Settings

**Connected services (shell rows: status → summary → Edit opens a DataForm with Save, Cancel and Disconnect).**

| Service | Status shown | Summary | Connect steps |
|---|---|---|---|
| FluentCRM | "connected" or "not active" (read-only feed) | next scheduled send | none: read only when the plugin is active |
| Social posts | "on" or "off" (off on live) | drafts and scheduled count | none: follows the module switch |
| Metricool | **"not connected"** | — | Only after the owner chooses it (D5): (1) the owner links the networks in Metricool; (2) the owner adds the API key in Settings, stored like the site's other keys and never sent to the browser ("set" or "not set"); (3) "Check connection" reads the brand, and posts nothing. |
| Postiz | **"not connected"** | — | Same steps, if chosen instead. |

**Settings groups (shell admin groups, each saved through `register_setting` with `show_in_rest` and a schema; option
`planweb_settings`):**

1. **Lists:** Types, Channels, Owners. Add, rename and reorder through the core terms routes. Removing a value that is
   in use is refused and says how many entries use it.
2. **Reminders:**
   - lead days per Type;
   - the email digest switch (off) and its recipient, chosen from the site's mailboxes;
   - "count past reminders as overdue" (D3: on).
3. **Import from Dates:** the result panel and verify table (2.3), through the shell's setup flow. It can be stopped
   and resumed, and is safe to run again.

### 4.5 WordPress Dashboard widget

- Shows the next 7 days of entries plus feed items: date, Type mark, title, Status label, and an "Open the calendar"
  link.
- Server-rendered, with no JS. It replaces the Dates widget, and its few rules go in the plugin's admin stylesheet,
  so the inline `<style>` goes.
- It sits outside the shell (core's Dashboard). If the shell offers a widget API by then, PlanWeb uses it.

### 4.6 Technical

- **Build.** `@wordpress/scripts`, one entry that registers PlanWeb's parts into the shell (tiles, list config, the
  month and week widgets, settings groups). It imports Kits Core's shell and design-system API, plus `@wordpress/*`
  packages for plumbing.
- **Built files are committed**, because the deploy has no build step. The Deploy check gains a Node step that runs
  `npm ci && npm run build` and fails if `build/` differs.
- **Security.**
  - Every route has a `permission_callback` with the capabilities in 4.1, and full args schemas with sanitize and
    validate callbacks.
  - REST uses core's `wp_rest` nonce.
  - The import and any "Send to …" have their own capability checks.
- **i18n.** Text domain `planweb`; `wp_set_script_translations`; `.pot` and JSON with `wp i18n`.
- **Performance budget** (inside the shell's own):
  - PlanWeb's JS no more than 40 KB gzipped;
  - Calendar's first load no more than 2 PlanWeb requests;
  - each route under 300 ms on live for one month;
  - the WordPress Dashboard widget no script and at most 2 queries (cached 5 minutes).
- **Uninstall** removes nothing. Deactivation unschedules the `planweb` actions.
- **Code standard:** PHPCS (WordPress-Extra, prefix and text domain `planweb`; `lib/` and `build/` excluded),
  `wp-scripts lint-js`.
- **Release (as MailWeb).** A PHPDoc header with `@wordpress-plugin`, `Update URI` and `Domain Path`; the
  `PLANWEB_VERSION` constant always equal to the header `Version`; `readme.txt` with `Stable tag`, `Tested up to` and a
  changelog block per version; `languages/planweb.pot` regenerated with `wp i18n make-pot` on each version bump.
- **Plugin Check.** It runs in the Deploy check after the smoke test, as a report only (printed, never failing the
  run). No phase claims "Plugin Check passes"; making it a gate is a later decision.
- **Icon.** The PlanWeb icon (a calendar with a yellow dot) comes from `docs/brand/plugin-icons/`. It is used on the
  Kits → Plugins row and in the app header, and copied to `assets/icon-256x256.png`, `assets/icon-128x128.png` and
  `assets/icon.svg`.
- **UI standard.** The same as the other Kits plugins: the Kits admin design-system shell; PlanWeb only registers its
  parts, and comes last in the shell's order.
- **Skills for every phase (same set as the other Kits plugin plans):** wp-plugin-audit, wp-plugin-release,
  wp-plugin-testing, wp-coding-standards, wp-database, wp-background-processing, wp-email-templates, wp-i18n-workflow,
  wp-build-tools, wp-github-flow and the code-review skill, plus any other that fits the phase.

---

## 5. Phases

Each phase is complete before the next. Each is a branch → PR into `wordpress-dev` → review gate (code-review,
wp-plugin-audit, PHPCS, Deploy check green, Codex comments answered) → the owner merges → it ships on the owner's yes.
**A phase never ships while a FluentCRM campaign is sending.** `aw-ship.sh` is run only after checking that FluentCRM
has no campaign in "working" and that MailWeb's queue holds no campaign emails.

**Order relative to the shell.** The Kits Core shell ships first, with InsightWeb as the pilot, then RankWeb,
SourceWeb, MailWeb and DirectoryWeb; **PlanWeb is last.** Phase 2 below therefore waits for the shell to be shipped.

**What phase 1 does before the shell:**

- the store, the REST routes and the migration, with `wp planweb verify`;
- the Animal Writes Core switch and redirect;
- the scheduler can be built here too (phase 3 does not need the UI).

Until phase 2, the team works in a **plain server-rendered Calendar page**: a `WP_List_Table` over the entries, plus
the Add and Edit form and the WordPress Dashboard widget. Phase 2 replaces it and adds nothing new to the shell's
baseline. Dates' month grid stays as-is on that page, read from PlanWeb, so nothing the team uses today is lost in the
gap.

| Phase | What | Needs the shell? | Size | Risk | Proof |
|---|---|---|---|---|---|
| **1. The store + migration** | Post type, taxonomies, meta, caps, REST routes and checks, import from Dates, `wp planweb verify`, the Animal Writes Core switch and redirect (2.4), the interim Calendar page and Dashboard widget, docs and baselines. | No | M (about 2.5 days) | Medium: it touches live data. Mitigated: the option stays untouched, the import is safe to re-run, and without PlanWeb nothing changes. | **The migrated entries match one-for-one** (`wp planweb verify`, read-only on live). Pure-PHP tests for keys, duplicates, link and personal-data checks. In the CI WordPress: seed `aw_team_dates`, activate, verify, re-run, verify again (`smoke.sh` activates it with `wp plugin activate planweb`). PHPCS clean on changed files. Plugin Check runs in the Deploy check as a report, not a gate. |
| **2. Calendar views + dashboard in the shell** | Register tiles, the list config, month and week widgets, and settings groups into the shell; remove the interim page; add the build step to CI. | **Yes: ships after the shell and the five plugins before it** | M (about 3 days; the shell does the heavy parts) | Low to medium: new screens; data rules come from phase 1. | **Screenshots against the Site Kit pattern** (Dashboard, Calendar list, month and week, Settings) at 1280 and 375 in the CI WordPress. Keyboard-only run and axe (WCAG 2.2 AA). Budget measured. JS unit tests for the date grids. |
| **3. Scheduler + reminders** | Action Scheduler committed in `lib/` at a pinned version, its jobs (3), notices, digest email (off), job status row. | No (can follow phase 1 directly) | M (about 2 days) | Medium: background jobs on live. Mitigated: idempotent, local data only, email off by default, MailWeb limit. | **Reminders fire on a copy only:** in the Deploy check's throwaway WordPress (not a local install), seed entries around a fixed date, run `wp action-scheduler run --group=planweb`, assert flags, approved → due, repeats and one queued digest, with `pre_wp_mail` catching the email so nothing is sent. Pure-PHP tests with a fixed clock. In the same CI WordPress, with no other copy active, the committed library loads and runs the queue (`ActionScheduler_Versions` shows PlanWeb's copy). PHPCS excludes `lib/`. On live: read-only check of which copy wins and of the `planweb` actions; digest stays off. |
| **4. Read-only feeds** | Future posts, FluentCRM scheduled campaigns, Social posts drafts, campaign dates in `planweb/v1/items`, tiles and widget. | Routes no; showing them in the shell yes | S to M (about 1.5 days) | Low: reads only. | CI WordPress with FluentCRM absent shows "not available". Read-only live check that a known future post and the campaign dates appear on the right days. PHPCS clean; Plugin Check report read. |
| **5. Connected services** | Only after the owner chooses one: the shell's service row, connect steps (4.4), "Send to …" per approved entry, for review only. | Yes | S to M | Medium: the only part that reaches outside the site. Every post needs its own approval. | Plan first, for the owner's yes. Tests with a mocked HTTP layer (`pre_http_request`). No real post in testing. |

Before the shell ships, phases can run in the order 1 → 3 → 4 (routes only). Phases 2 and 5 wait for the shell.

---

## 6. Decisions (owner, 11 Nov 2026: "Yes, all as recommended")

1. **D1 – Phase 1 goes ahead before the shell:** storage (a), a non-public post type, and the interim Calendar page.
   Today's Dates screen keeps working until the shell is ready. Phase 3 follows.
2. **D2 – The 12 monthly "Search Console Links read" entries** are folded into one monthly repeating entry once the
   import matches one-for-one.
3. **D3 – Past reminders count as overdue**, so an open item such as 7 Nov "Decide the lab rules Post phase" shows
   instead of disappearing. The Reminders setting defaults to on.
4. **D4 – Email digest: off.** The PlanWeb Dashboard and the WordPress Dashboard widget are enough.
5. **D5 – Posting to social: none for now.** Metricool or Postiz is chosen later. Nothing ever posts without the
   owner's yes.
6. **D6 – The `aw_team_dates` backup** may be removed 30 days after `wp planweb verify` passes on live.
7. **The four campaign dates** missing from Dates are added as normal entries after phase 1: 17 Nov Supreme Court
   reports due; 30 Nov MP model city; 3 Dec Jharkhand; 15 Dec stray-dog checkpoint.

## 7. Differences found while planning

- The brief says "Tools → Dates". The screen is **Kits → Dates** (`aw-dates`) since 10 Nov; `docs/pending.md` called
  it "Animal Writes → Dates" until 11 Nov.
- The 11 Nov Martech update says the 25 Nov "Search Insights thresholds review" no longer shows in Dates, although
  `aw_team_dates_1101b` added it once. It may have been removed by hand. The import takes the list as stored on the
  day it runs; the verify table will show what is there.
- The Martech page lists four campaign dates missing from Dates (17 Nov Supreme Court reports, 30 Nov MP model city,
  3 Dec Jharkhand, 15 Dec stray-dog checkpoint). The owner said yes on 11 Nov (section 6): they are added as
  normal entries after phase 1, not as part of the import.
- The earlier rule "admin UI core-only (WP_List_Table, Settings API, no custom CSS)" is replaced for Kits plugins.
  The look follows the Kits admin design system (Google Site Kit pattern for plugins), shipped in Kits Core;
  WordPress packages are plumbing only (owner, 11 Nov). PlanWeb only registers parts into the shell.
- The admin is the Kits Core shell, so PlanWeb registers parts rather than building screens. Its phase 2 waits for
  the shell, which ships first with InsightWeb, then RankWeb, SourceWeb, MailWeb and DirectoryWeb; PlanWeb is last.
- "Reminders fire on a local copy only" is met with the Deploy check's throwaway WordPress on the GitHub runner. No
  local WordPress or MySQL is set up.
