# PlanWeb: the team's calendar as its own Kits plugin (plan)

Owner, 11 Nov 2026: Dates moves into its own Kits plugin, **PlanWeb**, which holds the team's schedule and the content
and marketing calendar, and runs a scheduler for internal jobs and reminders. **This is step 1, a plan only. No code
until the owner says yes.**

Sources: the current code (`plugins/animal-writes-core/includes/team-dates.php` and `admin-home.php` on
`wordpress-dev` at `b0512a4e`; live is `637e9e64`, which `wordpress-dev` contains), `docs/pending.md` (Dates), and the
Notion page "Martech status, Dates spec and 11–24 Nov plan" (10 Nov, updated 11 Nov). The admin UI spec
(`docs/admin-ui-spec.md`) was not on any branch on 11 Nov, so this plan follows the Kits plugin standard excerpt in the
brief. If the spec lands first and differs, the spec wins.

---

## 0. What exists today (checked in the code, 11 Nov 2026)

- **Menu:** Kits → Dates (`admin.php?page=aw-dates`), not Tools → Dates. Since 10 Nov (642e7e76) it is one of the seven
  Animal Writes screens at the end of the Kits menu. Capability `edit_others_posts` (editors and administrators).
  `docs/pending.md` still says "Animal Writes → Dates"; that name is from before 10 Nov.
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

- **The library.** PlanWeb loads Action Scheduler through Composer (`woocommerce/action-scheduler`). Action Scheduler
  picks the newest copy loaded, so the one shipped by FluentCRM or Fluent Forms on live can win, and PlanWeb works if
  either is removed.
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
  - Body: date, title, Type, Status and a link to PlanWeb. Plain text plus a simple HTML part, using core's email
    header and footer only.
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

## 4. Admin (Kits plugin standard, Site Kit pattern)

### 4.1 Menu and screens

- **Menu.** PlanWeb has its own top-level menu with three items: **Dashboard**, **Calendar** (the working list) and
  **Settings**. The app header carries the PlanWeb name, the page title and a "New entry" button.
- **In the Kits admin.** A row on Kits → Plugins (`kits_plugin_screens`) and a row in Welcome → System status
  (`kits_status_rows`, the last sweep's result).
- **Requires Kits Core.** The check is at run time (`function_exists( 'kits_admin_tabs' )`), because Kits Core can be
  loaded by another plugin rather than activated, so the `Requires Plugins` header could block activation. Without
  Kits Core, PlanWeb shows one notice on its own pages and does nothing else.

### 4.2 Dashboard

- **Section chips** jump down the page: Coming up · Needs action · Channels · Email.
- **Uniform tiles**: label, info icon, number, sub-line, change chip and source. **Select metrics** shows or hides
  tiles, per user (user meta).

| Tile | Number | Source named on the tile |
|---|---|---|
| Next 7 days | entries plus feed items dated today to +6 days | "PlanWeb entries, scheduled posts, FluentCRM, Social posts, campaigns" |
| Overdue | entries with flag overdue | "PlanWeb sweep, last run …" |
| Waiting for approval | entries with Status draft | "PlanWeb entries" |
| By channel this month | count per Channel (bar list) | "PlanWeb entries, this month" |
| Next FluentCRM send | date and time of the next scheduled campaign, or "none scheduled" | "FluentCRM campaigns (read-only)" |

The change chip compares with the same window last week or month. A tile with no data says so; it never shows 0 as if
it were a measurement.

### 4.3 Calendar (the working list)

- **Views.** Month, week and list, in one screen with a view switch. Each view has its own address (`&view=month`,
  `&m=2026-11`), so a link opens the same view.
- **List view.** A DataView (`@wordpress/dataviews`) over the entries plus the feeds:
  - filters: Type, Channel, Owner, Status, plus "show read-only items";
  - sort by date; search;
  - bulk actions: change Status (approved and sent only for `planweb_approve`), and move to Trash (it never deletes
    for good).
- **Month and week views.**
  - Each day lists its items. Each item has a **coloured mark for its Type** plus a **text label for its Status**.
  - Colour is never the only signal: each Type also has its own icon and an accessible name (WCAG 2.2 AA, 1.4.1).
  - The colours come from the WordPress admin palette (`@wordpress/components` theme variables), not the brand palette.
  - If the shipped `@wordpress/dataviews` has no calendar layout, the grid is a small component built from
    `@wordpress/components` (Grid, Card, Button, Popover) with no stylesheet of our own beyond layout.
- **Old dates stay visible.** Past days are shown greyed, never hidden. The list's default filter is "from 30 days
  ago" and can be cleared.
- **Editing.** "New entry" and clicking an entry open a side panel with the fields from 1.1, including the field
  checks. Saving goes to the REST API (the entity's `edit` and `save`); the revision list is in the panel.

### 4.4 Settings

1. **Connected services first**, as rows with a status, each opening to a summary with **Edit**:
   - FluentCRM (read-only feed: "connected" or "not active");
   - Social posts ("off" on live);
   - Metricool and Postiz: **"not connected"** until the owner chooses one (3.2).
2. **Then toggles and lists with plain descriptions:**
   - Types, Channels, Owners: add, rename, reorder. Removing a value that is in use is refused and says how many
     entries use it.
   - Reminders: lead days per Type; the email digest switch and its recipient (from the site's mailboxes); "count
     past reminders as overdue" (D3).
   - Import from Dates: the result panel from 2.3.
3. **Storage.** One option, `planweb_settings`, through `register_setting` with `show_in_rest` and a full schema.
   Taxonomy values are edited through the core terms endpoints. No raw tables of options.

### 4.5 WordPress Dashboard widget

- Shows the next 7 days of entries plus feed items, each with date, Type mark, title and Status label, and an "Open the
  calendar" link.
- Server-rendered (no JS), and replaces the Dates widget.
- Its styles move into the plugin's admin stylesheet. The inline `<style>` goes, which also clears the
  `settings_parts_baseline` "admin style" line.

### 4.6 Technical

- **Build.** `@wordpress/scripts` with one entry per screen: `dashboard`, `calendar`, `settings`. Imports come only from
  `@wordpress/*` (components, dataviews, data, core-data, api-fetch, i18n, element). No home-made design system.
- **Built files are committed**, because the deploy has no build step. The Deploy check gains a Node step that runs
  `npm ci && npm run build` and fails if `build/` differs, like `css_min.py --check`.
- **Security.**
  - Each screen checks its capability.
  - REST uses core's cookie nonce (`wp_rest`).
  - The import and the "Send to …" buttons use their own nonces and capability checks.
  - Every REST field has a schema with `sanitize_callback` and `validate_callback`.
- **Notices** appear only on PlanWeb's own pages, always dismissible.
- **i18n.** Text domain `planweb`; `wp_set_script_translations` per script; `.pot` from `wp i18n make-pot`; JSON from
  `make-json`.
- **Performance budget per screen:**
  - own JS no more than 60 KB gzipped (WordPress packages come from core);
  - at most 3 REST requests on first load;
  - server time under 300 ms on live for one month of entries;
  - the WordPress Dashboard widget adds no script and at most 2 queries (cached 5 minutes).
- **Uninstall** removes nothing. Entries and settings stay (no data is deleted without asking). Deactivation
  unschedules the `planweb` actions.
- **Code standard:** PHPCS (WordPress-Extra, prefix `planweb`, text domain `planweb`) on every PHP file; `wp-scripts
  lint-js` on JS.

---

## 5. Phases

Each phase is complete before the next. Each is a branch → PR into `wordpress-dev` → review gate (code-review,
wp-plugin-audit, PHPCS, Deploy check green, Codex comments answered) → the owner merges → it ships on the owner's yes.
**A phase never ships while a FluentCRM campaign is sending.** `aw-ship.sh` is run only after checking that FluentCRM
has no campaign in "working" and that MailWeb's queue holds no campaign emails.

| Phase | What | Size | Risk | Proof |
|---|---|---|---|---|
| **1. The store + migration** | Post type, taxonomies, meta, caps, REST schema and checks, import from Dates, `wp planweb verify`, the Animal Writes Core switch and redirect (2.4), docs and baselines. Calendar is a plain read-only list for this phase only. | M (about 2 days) | Medium: it touches live data. Mitigated: the option stays untouched, the import is safe to re-run, and without PlanWeb nothing changes. | **The migrated entries match one-for-one** (`wp planweb verify` table, read-only on live). Pure-PHP tests for keys, duplicates, link and personal-data checks. In the CI WordPress: seed `aw_team_dates`, activate, verify, re-run, verify again. PHPCS clean; Plugin Check (in CI) clean. |
| **2. Calendar views + dashboards** | Dashboard tiles, Calendar (month, week, list DataView), side-panel editing, Settings (lists), WordPress Dashboard widget, build step in CI. | L (about 4 days) | Low to medium: new screens only; data rules come from phase 1. | **Screenshots against the Site Kit pattern** (Dashboard, Calendar three views, Settings) at 1280 and 375, in the CI WordPress. Keyboard-only run and axe check (WCAG 2.2 AA). Budget measured. JS unit tests (`wp-scripts test-unit-js`) for filters and date grids. |
| **3. Scheduler + reminders** | Action Scheduler jobs (3), notices, digest email (off), job status rows. | M (about 2 days) | Medium: background jobs on live. Mitigated: idempotent, local data only, email off by default, MailWeb limit. | **Reminders fire on a copy only:** in the Deploy check's throwaway WordPress (not a local install), seed entries around a fixed date, run `wp action-scheduler run --group=planweb`, assert flags, approved → due, repeats and one queued digest, with `pre_wp_mail` catching the email so nothing is sent. Pure-PHP tests with a fixed clock for every rule. On live after shipping: read-only check of the `planweb` actions and job status; digest stays off. |
| **4. Read-only feeds** | Future posts, FluentCRM scheduled campaigns, Social posts drafts, campaign open and close dates in the views, tiles and widget. | S to M (about 1.5 days) | Low: reads only. FluentCRM is read through its model, never written. | CI WordPress with FluentCRM absent shows "not available". Read-only live check that a known future post and the campaign dates appear on the right days. PHPCS, Plugin Check. |
| **5. Connected services** | Only after the owner chooses one (Metricool, Postiz or none): connect row, "Send to …" per approved entry, for review only. | S to M | Medium: the only part that reaches outside the site. Every post needs its own approval. | Plan first, for the owner's yes. Then tests against the service's sandbox or a mocked HTTP layer (`pre_http_request`). No real post in testing. |

---

## 6. Decisions for the owner

1. **D1 – Go ahead with phase 1** on this plan, with storage (a), a non-public post type.
2. **D2 – The 12 monthly "Search Console Links read" entries:** keep them as 12 entries (default), or fold them into one
   monthly repeating entry once the import matches.
3. **D3 – Past reminders:** count them as overdue, or not (default: not). The Martech page listed 7 Nov "Decide the
   lab rules Post phase" as overdue, which suggests yes.
4. **D4 – Email digest:** stays off (default), or on to which site mailbox.
5. **D5 – Posting to social:** Metricool, Postiz or none. This is phase 5, and is listed only.
6. **D6 – When the `aw_team_dates` backup may be removed** (default: kept).

## 7. Differences found while planning

- The brief says "Tools → Dates". The screen is **Kits → Dates** (`aw-dates`) since 10 Nov; `docs/pending.md` still
  calls it "Animal Writes → Dates".
- The 11 Nov Martech update says the 25 Nov "Search Insights thresholds review" no longer shows in Dates, although
  `aw_team_dates_1101b` added it once. It may have been removed by hand. The import takes the list as stored on the
  day it runs; the verify table will show what is there.
- The Martech page lists four campaign dates missing from Dates (17 Nov Supreme Court reports, 30 Nov MP model city,
  3 Dec Jharkhand, 15 Dec stray-dog checkpoint). They are added only on the owner's yes, as normal entries after
  phase 1. They are not part of the import.
- The earlier rule "admin UI core-only (WP_List_Table, Settings API, no custom CSS)" is replaced for Kits plugins by
  the 11 Nov standard (`@wordpress/components`, DataViews, REST). PlanWeb follows the 11 Nov standard.
- "Reminders fire on a local copy only" is met with the Deploy check's throwaway WordPress on the GitHub runner. No
  local WordPress or MySQL is set up.
