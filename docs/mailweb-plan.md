# MailWeb on the Kits plugin standard (plan)

Status: plan only, for the owner's yes (owner, 11 Nov 2026). No code until it is approved. Checked against `origin/wordpress-dev` at c37d3b7c on 11 Nov 2026; every file:line below is from that commit.

## Fixed for this plan (owner)

- **Data stays as it is.** Every option, the queue table, the webhook routes, hooks, filters, cron hooks, menu slugs and the `mailweb_` prefix keep their names. The one slug change is the owner's menu decision (§10): `mailweb` opens the Dashboard and the Inbox moves to `mailweb-inbox`, with redirects (§5). New REST routes go under MailWeb's own namespace, `mailweb/v1/…`, beside `mailweb/v1/mail-in` (as DirectoryWeb uses `directoryweb/v1`), never under `kits/v1`. MailWeb is 3.2.1 and is already the model for release files (`readme.txt`, `languages/`, `MAILWEB_VERSION`).
- **Mail rules.** support@ sends outgoing mail and replies; noreply@ sends bulk and automatic mail only; vedanshu@ is for partnerships only; the public address is contact@. Nothing is sent without the owner's yes. No test sends, so no proof in this plan sends an email. Forms send no email (`aw_ff_mail_off()`, `animal-writes-core/includes/forms-fluentforms.php:34`), and nothing here changes that.
- **No ship during a FluentCRM send.** `tools/aw-ship.sh` and the Deploy check already refuse (`tools/ci/mail_sending_check.py` reads `aw/v1/deploy-status`). Every phase ships on the owner's yes, one deploy at a time.
- **The admin is the shared Kits Core shell.** MailWeb only registers parts into it.

## Where the brief and the docs differ

- `docs/admin-ui-spec.md` (approved 11 Nov) covers the Kits menu only. Its §F leaves the plugin dashboards (phases 3–6) "specified separately". Its §C already moves **Check site: email** (`mailweb_pause`, `mailweb_last_error`) to **MailWeb → Dashboard**, and this plan takes that row.
- `docs/kits-plugin-ui.md` (the plugin shell) is on no branch yet. `git log --all -- docs/kits-plugin-ui.md` was empty on 11 Nov. This plan follows the brief's outline. The look comes from the Kits admin design system (Google Site Kit pattern), shipped in Kits Core. WordPress packages are plumbing only: `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`, `@wordpress/icons`. When the shell spec lands, it wins, and the names in §2–§4 are renamed to match.
- `admin-ui-spec.md` §A.6 builds Kits screens with `@wordpress/components` and DataViews. The later owner entry (Notion decision log, 11 Nov; see the DirectoryWeb note in the shared notes folder) replaces that look with the Site Kit pattern. Plugin screens follow the later entry.
- Shell order: InsightWeb pilot → RankWeb → SourceWeb → **MailWeb** → DirectoryWeb → PlanWeb. MailWeb's shell phases (§9, phases 2–4) wait for the shell to be live with InsightWeb and to have the parts in §7.

---

## 1. Inventory (nothing on today's screens goes missing)

### 1.1 Files

| File | What it holds | Lands |
|---|---|---|
| `mailweb.php` | Header (1–23), constants (28–32), `mailweb_5min` schedule (35–44), text domain (46–51), requires (53–66), deactivation clears `mailweb_flush` and `mailweb_crm_sync` (69–77) | Stays. Requires gain `includes/rest.php` and `includes/shell.php` |
| `includes/addresses.php` | `mailweb_addresses()`, the one address parser | Stays. Tested in CI |
| `includes/crypto.php` | AES-256-GCM for stored secrets (`mwenc1:` prefix, key from `wp_salt('auth')`) | Stays. Secrets never leave PHP |
| `includes/mail-in.php` | `mailweb_setting()` (env → wp-config → encrypted option, 26–36), `mailweb_box_ids()` (62–112), `mailweb_api()` (125–146), webhook permission (156–169) and handler (172–196), routes (198–227), Incoming mail screen (232–288) | Logic stays. Screen → Settings → Connected services (§4) |
| `includes/mailboxes.php` | `mailweb_all_boxes()`, send boxes, default From, aliases (19–64); screen (68–110); save `admin_post_mailweb_alias` (112–136) | Logic stays. Screen → Settings → Mailboxes and aliases |
| `includes/profiles.php` | Per-mailbox name, signature, picture (21–44); signature layout on site mail (53–157); screen (159–185); save `admin_post_mailweb_profile` (187–208) | Logic stays. Screen → Settings → Profiles |
| `includes/smtp.php` | `mailweb_smtp_settings()` (16–36), From and From name (39–40), `phpmailer_init` (42–63), screen (66–106), save and test `admin_post_mailweb_smtp_save` (108–143) | Logic stays. Screen → Settings → Connected services → Site sending |
| `includes/mail-cap.php` | Queue table (45–74), block-error rules (100–116), pause (119–124), retries (127–150), priority (199–217), `X-Mailweb-Priority` header (226–246), `pre_wp_mail` (248–285), `mailweb_flush()` (288–349), cron (352–360), FluentCRM pacing (364–366), screen (370–441), actions `admin_post_mailweb_cap` (443–470), pause notice (474–502) | Logic stays. Numbers → Dashboard. Limits → Settings → Sending |
| `includes/crm-sync.php` | FluentCRM feed settings (22–45), notes (51–175), lock (177–193), sync (199–231), cron (233–249), webhook run (251–260), reply notes (268–290), WP-CLI (292–303), contact-job flag (308–309), screen (313–338), save `admin_post_mailweb_crm` (340–359) | Logic stays. Screen → Settings → Connected services → FluentCRM feed |
| `includes/inbox.php` | `MAILWEB_CAP` and limits (28–32), forms source (46–54), `mailweb_call()` (66–87), folders (94–132), state (180–196), bulk (226–283), page (285–303), list (330–366), message (369–457), compose (460–545), form item (548–580), safe HTML (583–629), single actions `admin_post_mailweb_act` (645–721), send (723–841) | Logic moves behind REST (§3). Screens → Inbox working list |
| `includes/list-table.php` | `Mailweb_List_Table`: columns (30–40), bulk actions (42–60), items and the 500-row merge (70–163), row actions (187–212) | Replaced by the shell list. Kept for the no-JS fallback for two releases |
| `includes/overview.php` | Inbox cache and "last good answer" (23–71), unread (74–80), cache flush (83–88), widget (160–191), menu with badge (194–207), Dashboard widget (210–217), FluentCRM FluentSMTP card off (220) | Cache and menu stay. Widget stays a core Dashboard widget |
| `includes/home.php` | Plugins-screen links (18–30), Mail → Overview feature table (38–131) | Overview → Dashboard (§2). Links stay |
| `includes/settings-tab.php` | Mail → Settings with two tabs, `incoming` and `sending` (17–36) | → Settings (§4). Old `sub=` addresses redirect (§5) |
| `includes/kits-hub.php` | Kits → Plugins row (`kits_plugin_screens`, 31–34) and System Status row (`kits_status_rows`, 41–46) | Stays. The status row gains the sending status (§2.3) |
| `includes/admin-table.php`, `class-mailweb-simple-table.php` | Read-only core list table helper | Fallback only, then removed with the classic screens |
| `uninstall.php` | Deletes nothing | Stays |
| `languages/mailweb.pot` | Hand-edited template, 227 strings | Regenerated (§6) |
| `readme.txt`, `composer.json` | Release files, lint and make-pot scripts | Updated each release (§6) |

**Animal Writes Core's side** (`animal-writes-core/includes/mailweb-aw.php`) stays in Core. It holds the campaign strip under signatures (30–54), the Website forms source (`mailweb_forms_source`, 56–297), the "New form entries" notice (299–330), the old webhook `aw/v1/mail-in` (332–370) and the Check site email box (373–392). Also in Core: SMTP defaults noreply@ / "Animal Writes" and Reply-To support@ (`kits-config.php:131–132`), and the retry of FluentCRM replies on `mailweb_flush` (`fluentcrm.php:325`).

### 1.2 Screens today and where each item lands

| Screen (slug) | Item | Lands |
|---|---|---|
| **MailWeb → Inbox** (`mailweb` today, `mailweb-inbox` from phase 2; `MAILWEB_CAP` = `edit_others_posts`) | Mailbox picker (All, or one mailbox with its aliases) | Inbox → filter "Mailbox" |
| | Folder links with unread counts: Inbox, Sent, Archive, Spam, Trash, **Website forms** | Inbox → views |
| | Search (not in Website forms) | Inbox → search |
| | Columns, bulk and row actions | Inbox (§3) |
| | New message | Inbox → header primary action |
| | Message view: header table, sandboxed frame, pictures hidden with a "Show pictures" link, attachments, Reply / Reply all / Forward, Archive, Move to Inbox / Not spam, Spam, Mark unread, Move to folder, Move to Trash | Inbox → reading panel (§3.3) |
| | Compose: To, Cc/Bcc, Subject, editor with signature, "Added under your signature" preview (campaign strip), From (send boxes only, signature swaps), attachments (10 MB total) | Inbox → compose panel (§3.4) |
| | Website form item: fields, text, Reply, Mark unread, Move to Trash | Inbox → reading panel (forms) |
| | "Read in step with Hostinger webmail" link | Inbox → footer line |
| **MailWeb → Overview** (`mailweb-overview` today; the Dashboard at `mailweb` from phase 2; `MAILWEB_CAP`) | Feature table: Inbox, Mailboxes, Incoming mail (webhook), Profiles and signatures, Site sending (SMTP), Daily limit and queue, FluentCRM feed, Dashboard widget; "Needs attention" box; WP-CLI command | Dashboard: tiles and sections (§2); WP-CLI in Dashboard → Help line |
| **MailWeb → Settings → Incoming mail, mailboxes, profiles** (`mailweb-settings`, `manage_options`) | Mail API token; webhook URL and last delivery; webhook secret per mailbox; mailbox IDs (read-only) | Settings → Connected services: Hostinger Mail API, Incoming mail (webhook) |
| | Mailboxes table (send from it, default From) and aliases textarea | Settings → Mailboxes and aliases |
| | Profiles (display name, signature HTML, picture) | Settings → Profiles |
| | FluentCRM (notes on, tag, mailboxes) | Settings → Connected services: FluentCRM feed |
| **MailWeb → Settings → Site sending** | SMTP mailbox, password, sender name, server, port; status; Save; Save and send a test email | Settings → Connected services: Site sending (SMTP) |
| | Pause box with Resume | Dashboard → Sending status (and Settings → Sending) |
| | Queue figures (12 rows) | Dashboard tiles and the Queue / Sent and failed sections |
| | Emails per 24 hours, Kept back from campaigns; Save limit; Send queued emails now; Retry held emails | Settings → Sending |
| **WordPress Dashboard → Mail widget** | Per mailbox: unread, status, newest 3 | Stays a core widget, outside the shell |
| **Kits → Plugins / System Status** | MailWeb row, "Active, 3.2.1" | Stays (§2.3) |
| **Kits → Check site** (Core, `aw_site_check_after`) | "Email sending: running/PAUSED", last error, link to resume | **Dashboard → Sending status.** Core's box becomes one line with a link (admin-ui-spec §C) |
| **Plugins screen** | Inbox, Overview, Settings links | Three links in menu order: Dashboard, Inbox, Settings |
| **Admin notices** | Pause notice: always on Dashboard, Inbox, Settings and Check site; elsewhere once a day | Every admin screen, admins only, dismissible until the next pause (§4.4, owner 11 Nov) |

### 1.3 Options (none renamed)

| Option | Holds | Secret | Lands |
|---|---|---|---|
| `mailweb_api_token` | Mail API token, encrypted | yes | Connected services → Hostinger Mail API |
| `mailweb_secret_<key>` | Webhook secret per mailbox, encrypted | yes | Connected services → Incoming mail |
| `mailweb_box_ids`, `mailweb_box_addresses` | Mailbox IDs and addresses from the token | no (read-only) | Mailboxes and aliases (shown) |
| `mailweb_mailboxes`, `mailweb_default_from` | Send from it per mailbox; default From | no | Mailboxes and aliases |
| `mailweb_aliases` | alias → mailbox map | no | Mailboxes and aliases |
| `mailweb_profiles` | name, signature, picture per address | no | Profiles |
| `mailweb_smtp` | user, name, host, port, encrypted `pass` | `pass` only | Connected services → Site sending |
| `mailweb_cap` | `budget` (default 90), `reserve` (default 10) | no | Settings → Sending |
| `mailweb_crm` | on, tag, boxes | no | Connected services → FluentCRM feed |
| `mailweb_pause` | {at, error} while paused | no | Dashboard → Sending status |
| `mailweb_last_error` | {at, error}, addresses already removed | no | Dashboard → Sent and failed |
| `mailweb_last` | last webhook delivery {at, mailbox} | no | Dashboard → Mailboxes; Incoming mail row |
| `mailweb_stats` | sent_direct, queued, sent_from_queue, rejected, paused | no | Dashboard → Sent and failed ("all time" line) |
| `mailweb_send_log` | times of Inbox sends, last hour | no | Dashboard tile "Replies sent" |
| `mailweb_overview_last_<key>` | last good Inbox answer per mailbox | no | Dashboard (stale marker) |
| `mailweb_crm_done`, `mailweb_crm_lock` | noted Message-IDs (last 5,000); run lock | no | Internal; the count is shown on the FluentCRM row |
| `mailweb_queue_db` | schema version `2` | no | Internal |
| `aw_mail_in_old_seen` (Core) | mailboxes still calling the old webhook | no | Incoming mail row (Core adds it through a filter) |

Transients (cache only, unchanged): `mailweb_overview_<key>`, `mailweb_overview_down_<key>`, `mailweb_folders_<key>`, `mailweb_box_ids_wait`, `mailweb_flush_lock`, `mailweb_smtp_error`, `mailweb_pause_seen` (no longer read once the pause notice changes in phase 1; it expires by itself).

New, per user: user meta `mailweb_pause_dismissed`, holding the `at` time of the pause the user dismissed (§4.4). Nothing existing is renamed.

### 1.4 Table

`{prefix}mailweb_queue` (`mail-cap.php:53–68`, schema version 2): id, priority, status (queued, sending, sent, failed, held), mail (JSON, cleared after send), tries, created_at, sent_at, error, next_try, resend. Keys `sent (status, sent_at)` and `next (status, priority, id)`. Sent rows are kept 30 days without content (346). **No schema change in this plan.** Every Dashboard number in §2 can be read from these columns.

### 1.5 Routes, admin actions, cron, CLI, Site Health

| Kind | Name | File:line | Auth | Lands |
|---|---|---|---|---|
| REST | `POST mailweb/v1/mail-in` | `mail-in.php:201` | per-mailbox Bearer secret (`hash_equals`) | Stays |
| REST | `GET mailweb/v1/mail-in` | `mail-in.php:211` | `__return_true`: answers "ready" only, no data | Stays (checked, not a bug) |
| REST (Core) | `POST` / `GET aw/v1/mail-in` | `mailweb-aw.php:338`, `353` | same | Stays until `aw_mail_in_old_seen` stays empty |
| REST (Core) | `GET aw/v1/deploy-status` | `deploy-status.php` | yes/no only | Stays (deploy gate) |
| admin-post | `mailweb_act` (archive, trash, spam, inbox, move, read, unread, file, formread, formunread, formtrash, send) | `inbox.php:645` | `MAILWEB_CAP` + nonce `mailweb_act` | Same work behind `mailweb/v1/inbox/*`. Handler kept two releases for the no-JS fallback |
| list bulk | `load-toplevel_page_mailweb` | `inbox.php:226` | nonce `bulk-messages` + `MAILWEB_CAP` | → `inbox/bulk` |
| admin-post | `mailweb_cap` (save, resume, retry, flush) | `mail-cap.php:443` | `manage_options` + nonce | → `sending`, `sending/resume`, `queue/retry`, `queue/flush` |
| admin-post | `mailweb_smtp_save` (save, test) | `smtp.php:108` | `manage_options` + nonce | → `services/smtp`, `services/smtp/test` |
| admin-post | `mailweb_alias` | `mailboxes.php:112` | `manage_options` + nonce | → `/wp/v2/settings` (§4.3) |
| admin-post | `mailweb_profile` | `profiles.php:187` | `manage_options` + nonce | → `/wp/v2/settings` |
| admin-post | `mailweb_crm` | `crm-sync.php:340` | `manage_options` + nonce | → `services/fluentcrm` |
| form POST in render | Incoming mail save | `mail-in.php:233` | `manage_options` + nonce `mailweb_in` | → `services/mail-api`, `services/webhook/<key>` |
| Cron | `mailweb_flush`, every 5 min (`mailweb_5min`) | `mail-cap.php:352–360` | — | Stays (Core also hooks it, `fluentcrm.php:325`) |
| Cron | `mailweb_crm_sync`, every 5 min | `crm-sync.php:233–249` | — | Stays |
| WP-CLI | `wp mailweb crm-sync [--all]` | `crm-sync.php:292` | — | Stays; listed on Dashboard → Help |
| Site Health | **none today** | — | — | New test, phase 1 (§9): "Email sending" (paused = critical; held emails = recommended) |
| Filters and actions kept | `mailweb_forms_source`, `mailweb_after_signature`, `mailweb_head_html`, `mailweb_smtp_defaults`, `mailweb_smtp_reply_to`, `mailweb_crm_skip_email`, `mailweb_pause_notice_always`, `mailweb_settings_after`, `mailweb_smtp_screen_after`, `mailweb_webhook_noted`; FluentCRM `global_email_limit_per_second` → 1, `disable_email_processing`, `mailer_handler_chunk_size`, `fluentmail_suggest` | various | — | All kept. The two `*_after` screen actions keep firing on the no-JS fallback for two releases |

---

## 2. Dashboard (`mailweb`, `MAILWEB_CAP`)

The MailWeb menu opens the Dashboard (owner, 11 Nov). From phase 2 the top-level slug `mailweb` is the Dashboard and its first submenu item is "Dashboard". Until then it lives at `mailweb-overview`, which afterwards redirects to `mailweb` (§5).

The shell's spec row is: Dashboard (mailboxes, queue, sent and failed, sending status). The section chips use the same words, **Mailboxes · Queue · Sent and failed · Sending status**.

**Routes.** Each answers from stored rows, options or the 5-minute Mail API cache. None sends mail, and none calls the API more than the Inbox cache already does. `refresh=1` (the shell's refresh) clears the 5-minute caches first.

| Route | Method | Capability |
|---|---|---|
| `mailweb/v1/tiles` | GET | `MAILWEB_CAP` |
| `mailweb/v1/sections/<mailboxes\|queue\|sent\|status>` | GET | `MAILWEB_CAP` |

### 2.1 Tiles

| Tile | Number | Sub-line | Change chip | Source named |
|---|---|---|---|---|
| Unread mail | Sum of each mailbox's Inbox `unreadCount` (`mailweb_overview_unread()`) | Per mailbox, for example "support@ 3 · vedanshu@ 0". "Shown from 4:12 pm" when the API is down (`stale`) | none (live count) | "Hostinger Mail API, cached 5 minutes, refreshed when mail arrives" |
| Website forms | `mailweb_forms_unread()` | Per form when the source gives counts (new optional `counts` key, §7). Otherwise "unread entries" | none | The source's label ("Website forms: Fluent Forms and Contact messages", from Core) |
| Sent in 24 hours | `mailweb_sent_24h()` "of" `budget` | "Room: notices N · campaigns M" (`mailweb_room(1)`, `mailweb_room(3)`) | vs the 24 hours before (sent rows, `sent_at`) | "Site sending queue (`mailweb_queue`)" |
| Waiting | `mailweb_queued()` | "Replies a · notices b · confirmations c · campaigns d; oldest since …" | none | same |
| Failed and held | count with status failed, plus count held | Last error with its time (`mailweb_last_error`) | vs yesterday (rows created) | same |
| Sending | "Running" or "Paused" (`mailweb_paused()`) | Paused: the refusal and its time. Running: "through noreply@ (smtp.hostinger.com:465)", or "server default: no password set" | none | "MailWeb sending status" |
| Replies sent | count in `mailweb_send_log` | "of 30 an hour, from the Inbox (Mail API)" | none | "Inbox sends (times only)" |

Default set: all seven. "Select metrics" may also offer **Mailboxes** (count and aliases, from `mailweb_box_addresses` and `mailweb_aliases`) and **Last mail in** (`mailweb_last`).

**Two counts that must stay apart.** The Inbox sends through the Mail API from the chosen mailbox (support@). Site mail goes through SMTP as noreply@ (Core's `mailweb_smtp_defaults`). Hostinger's 100-a-day limit is per mailbox, so the queue budget counts noreply@ only, and Inbox replies have their own 30-an-hour cap (`MAILWEB_SEND_HOUR`). The Dashboard shows both and never adds them together.

### 2.2 Sections

| Chip | Widgets | Actions |
|---|---|---|
| Mailboxes | One row per mailbox: picture, address, aliases, unread, status (OK / Empty / Not reachable / Shown from …), newest 5 (sender, date, subject; no bodies), "Open in Inbox". Last webhook delivery. | Open in Inbox; Open webmail |
| Queue | Waiting by priority, 0 replies, 1 notices, 2 confirmations, 3 campaigns (`mail-cap.php:376`); oldest waiting; the campaign share left today | **Send queued emails now (within the limit)** (`manage_options`). It sends real mail, so it is never pressed as a proof |
| Sent and failed | Sent today; sent per day, last 30 days (chart, from sent rows); failed (will retry, with next try); held after 4 tries (time, priority, tries, error only: no addresses, no subjects, as the log rule says); the all-time line from `mailweb_stats` | **Retry held emails** (`manage_options`) |
| Sending status | Running/paused, SMTP mailbox and server, password set / not set / set in wp-config; the priority rule in one line ("replies first, then notices, confirmations, campaigns; campaigns keep `reserve` back"); FluentCRM pacing (1 a second; held while the campaign share is used up) | **Resume sending** (`manage_options`, only while paused, with a confirm). This is the Check site email box, moved |

Dashboard → Help line: `wp mailweb crm-sync --all`, the webhook URL, and the link "Read in step with Hostinger webmail".

### 2.3 Rows outside the screen

- **Kits → Plugins** (`kits_plugin_screens`): points at the Dashboard, so `kits-hub.php:32` changes from `mailweb-overview` to `mailweb` in phase 2.
- **Kits Home → Plugin status** (`kits_status_rows`): "Active, 3.2.x · sending running" or "**sending paused since …**" (red, links to Dashboard → Sending status).
- **Kits → Check site** (Core): the box becomes one line, "Email sending: running/paused, see MailWeb → Dashboard". Core's `aw_site_check_after` hook calls a new `mailweb_status_line()`, so Core reads no MailWeb option itself.
- **WordPress Dashboard widget**: unchanged, server-rendered, no script.

---

## 3. Inbox (the working list, `mailweb-inbox`, `MAILWEB_CAP`)

The Inbox is the second menu item, after the Dashboard (owner, 11 Nov). It moves from `mailweb` to `mailweb-inbox` in phase 2. The header tabs read Dashboard · Inbox · Settings, and the menu badge (unread mail plus unread forms) stays on the top-level MailWeb item, with the same count on the Inbox tab.

Links that point at `page=mailweb` today and must follow the Inbox in phase 2:

- every `page=mailweb` address that carries Inbox arguments is redirected to `mailweb-inbox` with them (§5);
- Core's "New form entries" notice, `page=mailweb&folder=forms` (`mailweb-aw.php:325`), and Core's admin home link (`admin-home.php:206`) point at `mailweb-inbox` directly;
- the WordPress Dashboard widget's "Open in Inbox" links, the Plugins-screen action links (`home.php:18–30`) and `mailweb_settings_url()` callers;
- the screen id `toplevel_page_mailweb` (`list-table.php:27`) and the bulk hook `load-toplevel_page_mailweb` (`inbox.php:226`) become `mailweb_page_mailweb-inbox` and `load-mailweb_page_mailweb-inbox`;
- `tools/ci/settings_parts_baseline.txt` in the same change.

### 3.1 Routes (`MAILWEB_CAP`, nonce `wp_rest`)

| Route | Method | Does |
|---|---|---|
| `mailweb/v1/inbox` | GET `box, folder, search, page, per_page` | List: mailbox, or "all" merged newest-first (500-row cap per mailbox, as today, `list-table.php:101`); Website forms through `mailweb_forms_source` |
| `mailweb/v1/inbox/folders` | GET `box` | Folders and unread counts (`mailweb_folders()`, 1-minute cache) |
| `mailweb/v1/inbox/message/<box>/<uid>` | GET `folder, images` | Meta and safe HTML (`mailweb_safe_html()`, CSP inside); marks it read, as today (`inbox.php:381`) |
| `mailweb/v1/inbox/message/<box>/<uid>/attachment/<id>` | GET | File download (`application/octet-stream`, `nosniff`, `sanitize_file_name`) |
| `mailweb/v1/inbox/form/<id>` | GET | Form item; marks it read through the source |
| `mailweb/v1/inbox/bulk` | POST `{action, ids:["box\|uid" or "form\|id"]}` | `mailweb_bulk()`: read, unread, archive, inbox, spam, trash |
| `mailweb/v1/inbox/move` | POST `{box, folder, uid, to}` | Move to any folder |
| `mailweb/v1/inbox/send` | POST multipart | `mailweb_send_from_form()` logic: From limited to send boxes, 30 an hour, 10 MB checked before any file is read, thread link, Answered / Forwarded flags, FluentCRM "Reply sent" note, form `replied` |

All the logic already exists. The routes call the same functions, which return values instead of redirecting. Folder names are checked against `^INBOX(\.[\w .-]+)?$`, as today.

### 3.2 List

- **Columns:** From (To in Sent), Subject (About in Website forms), Mailbox (only in All), Date. Unread rows are bold, with a text label "(unread)", never colour alone. A paperclip shows when there are attachments.
- **Views:** Inbox, Sent, Archive, Spam, Trash, Website forms, each with its unread count.
- **Filters:** Mailbox (All, or each mailbox with its aliases); search (mail folders only; the Mail API search).
- **Bulk actions,** per view as today (`list-table.php:42–60`): Mark read, Mark unread, Archive (not in Archive or Trash), Move to Inbox / Not spam (not in Inbox), Spam (not in Spam or Sent), Move to Trash (not in Trash). Website forms: Mark read, Mark unread, Move to Trash.
- **Row actions:** Open, Archive, Mark read / unread, Trash. Delete is never offered: Trash only, as today.
- **Empty and error states:** "No messages", "Nothing matches your search", and "The mailbox could not be read just now" (the shell's states).
- **Primary action:** New message.

### 3.3 Reading

- The reading panel opens beside the list or over it, as the shell provides. It shows a header table (From, To, Cc, Date), the message in a **sandboxed iframe** (`sandbox="allow-popups allow-popups-to-escape-sandbox"`, `srcdoc`, CSP, no scripts), and "Pictures from the internet are hidden" with Show pictures.
- Attachments are download links. The actions are Reply, Reply all, Forward, Archive, Move to Inbox / Not spam, Spam, Mark unread, Move to folder and Move to Trash.
- A form item shows its fields and text, with Reply, Mark unread and Move to Trash.
- The iframe stays MailWeb's own markup inside a shell slot (request 3 in §7). The shell never renders mail HTML itself.

### 3.4 Replying

- **From** lists only mailboxes with "Send from it" (`mailweb_send_boxes()`). The default is `mailweb_default_from()`. On live, only support@ sends, as set by the 20 Oct move (`mailweb-aw.php:13`), so every reply goes from support@ unless the owner ticks another mailbox.
- A line under From reads "Replies go from support@ (Settings → Mailboxes and aliases)". If a message came in to another mailbox, the existing note stays: a reply from another mailbox carries no thread link (`inbox.php:532`).
- "Send from it" stays off for vedanshu@ (owner, 11 Nov), so it never appears under From. This plan changes no data and adds no way round it.
- The signature swaps with From, as today. The campaign strip preview (`mailweb_after_signature`, context `inbox`) shows under the editor.
- **Editor:** a rich-text field with paste-safe HTML. The server still runs `wp_kses_post` on save (request 4).
- **Attachments:** a file field, 10 MB in total (request 5).
- **Send** asks no extra question, because it is the user's own typed reply. Nobody sends from a proof (§9).

---

## 4. Settings (`mailweb-settings`, `manage_options`)

### 4.1 Connected services (shell rows: status → summary → Edit opens the edit form with Save, Cancel, Disconnect)

Secrets never reach the browser. Each field shows **"set"**, **"not set"** or **"set in wp-config.php"**. The last one is read-only: change it there (`mailweb_setting_fixed()`). Saving an empty secret field keeps the stored value, as today.

| Service | Fields in Edit | Status rule | Disconnect | Route |
|---|---|---|---|---|
| Hostinger Mail API | Token (secret) | "Not set up" with no token. "Error" when `/api/v1/me` fails, or when `mailweb_box_ids_wait` is set and no IDs are known. Otherwise "Connected: N mailboxes". Mailbox IDs are shown read-only | Clears `mailweb_api_token` only. Mailbox list and settings stay | `POST/DELETE mailweb/v1/services/mail-api` |
| Incoming mail (webhook), one row per mailbox | Webhook secret (secret); the webhook URL with a copy button and the hPanel path | "Not set up" with no secret. "Late" when no delivery in 7 days while the token works. Otherwise "Last mail in …" (`mailweb_last`). Core adds "old address last called …" from `aw_mail_in_old_seen` | Clears that mailbox's secret | `POST/DELETE …/services/webhook/<key>` |
| Site sending (SMTP) | Mailbox address, sender name, server, port, password (secret) | "Sending through noreply@…" or "Not set up, using the server default". Paused shows as "Error" with the refusal | Clears the password only. The site then sends the server's default way; the form says so before it saves | `POST/DELETE …/services/smtp` |
| FluentCRM feed | Notes on/off, tag, mailboxes | "Not active" without FluentCRM; "On: N mailboxes"; "Off" | Hidden (it is a switch) | `POST …/services/fluentcrm` |

The **test email** (`smtp.php:125–139`) stays an owner-only action in the SMTP edit form, `POST …/services/smtp/test`, labelled "Sends one email to the admin address". No session presses it, and no proof uses it.

### 4.2 Sending (settings group)

- **Emails per 24 hours** (`mailweb_cap.budget`, 1–10,000, default 90). Help: "your mail plan's limit less a margin (Hostinger Business Email: 100, so 90)".
- **Kept back from campaigns** (`mailweb_cap.reserve`, 0–99 and below the budget, default 10). Help: "campaigns use at most the limit minus this, so notices and confirmations always have room". On live today, campaigns get 80 (pending, 3–4 Nov), so the live values must be read before phase 1, and the proof checks they are unchanged.
- Shown read-only beside them, the priorities: 0 replies typed in FluentCRM, 1 notices (everything else), 2 sign-up confirmations (Fluent Forms), 3 FluentCRM campaigns. An email may set its own with `X-Mailweb-Priority: n`.
- **PlanWeb's digest** (`docs/calendar-plugin-plan.md` §3.1) goes through `wp_mail` with no header, so it is priority 1, a notice. It always has the full budget less what is sent (`mailweb_room(1)`), never the campaign share, and the reserve keeps campaigns from using up its room. Nothing in MailWeb changes for it. PlanWeb picks its recipient from MailWeb's mailbox list, so MailWeb adds `GET mailweb/v1/mailboxes` (addresses only, `edit_others_posts`) for that picker. PlanWeb stores the key, never a typed address.
- Actions here as on the Dashboard: Resume sending, Send queued emails now, Retry held emails.
- Route: `POST mailweb/v1/sending` (budget and reserve, same clamps as `mail-cap.php:39–42` and `466`); `POST …/sending/resume`, `POST …/queue/flush`, `POST …/queue/retry`.

### 4.3 Mailboxes and aliases · Profiles (settings groups)

Non-secret options are registered with `register_setting( 'mailweb', …, [ 'show_in_rest' => [ 'schema' => … ], 'sanitize_callback' => … ] )` and saved through `/wp/v2/settings` (admin-ui-spec §A.7). These are `mailweb_mailboxes`, `mailweb_default_from`, `mailweb_aliases` and `mailweb_profiles`. The sanitize callbacks are today's code, moved:

- `is_email` on both sides of an alias;
- `sanitize_key` for mailbox keys;
- `wp_kses_post` for signatures;
- `absint` for the picture's attachment ID.

**Never** registered with `show_in_rest`: `mailweb_smtp` (holds the password), `mailweb_api_token`, `mailweb_secret_*`.

- **Mailboxes and aliases:** a list of mailboxes from the token, with "Send from it" and "Default From", plus the line "Replies go from …". Aliases become a list of alias = mailbox pairs (add, remove), saved as today's map. The help keeps "The Mail API does not list aliases: keep this in step with hPanel".
- **Profiles:** per address, display name, signature (HTML, with preview) and picture (media picker).

### 4.4 Notices

- On MailWeb's own screens: shell notices, dismissible.
- **The sending-paused notice** (owner, 11 Nov) stays on every admin screen, outside the shell rule, because a pause stops all site mail and "no email is used to tell anyone". Today it shows once a day, and always on the WordPress Dashboard, MailWeb and Check site (`mail-cap.php:474–502`, transient `mailweb_pause_seen`). From phase 1:
  - it shows only to admins (`manage_options`), on every admin screen, while `mailweb_paused()` is true;
  - it has a dismiss button. Dismissing stores the pause's `mailweb_pause['at']` in the user's meta `mailweb_pause_dismissed`, through `POST mailweb/v1/notice/pause/dismiss` (`manage_options`, nonce `wp_rest`);
  - it stays hidden for that user until the next pause: a new pause has a new `at`, so the notice comes back for everyone. Resuming does not clear the meta;
  - the once-a-day rule and `mailweb_pause_seen` are no longer used. The filter `mailweb_pause_notice_always` is kept: when it returns true, the notice shows even after a dismiss;
  - the notice text and its link to Dashboard → Sending status are unchanged, apart from the new link address in phase 2.
- Core's "New form entries" notice is Core's and is not changed here.

---

## 5. Migration: old addresses and settings

| Old | New | How |
|---|---|---|
| `admin.php?page=mailweb` with any of `box`, `folder`, `uid`, `item`, `compose`, `s`, `paged`, `message`, `action` | `admin.php?page=mailweb-inbox` with the same arguments | 302 on `load-toplevel_page_mailweb` (phase 2). Bulk-action GETs keep working through the fallback |
| `admin.php?page=mailweb` with no Inbox arguments | the Dashboard | none needed: the slug is the Dashboard from phase 2 |
| `admin.php?page=mailweb-overview` | `admin.php?page=mailweb` | 302, kept as a hidden page (phase 2) |
| `mailweb-settings&sub=incoming` | `mailweb-settings#services` | 302 on `load-mailweb_page_mailweb-settings` when `sub` is present |
| `mailweb-settings&sub=sending` | `mailweb-settings#sending` | same |
| old anchors `#cap`, `#addresses`, `#profiles`, `#fluentcrm` | `#sending`, `#mailboxes`, `#profiles`, `#services/fluentcrm` | The browser keeps the anchor through the redirect; the shell maps the four old ones |
| `mailweb_settings_url( $sub, $args, $anchor )` | same signature, new addresses | One helper, so Core's links (`mailweb-aw.php:384`) and the pause notice follow it |

**No setting lost:**

1. Before phase 1, read on live (read-only SSH) every `mailweb_*` option name and a hash of its value. Secrets are recorded only as set or not set, and never printed. Also read the queue counts by status.
2. After each phase, read again. Every name must be present with the same hash, except counters that move by themselves (`mailweb_stats`, `mailweb_last`, `mailweb_send_log`, `mailweb_overview_last_*`, `mailweb_crm_done`).
3. The no-JS fallback keeps the classic screens and their `admin_post_*` handlers for two releases, behind "Open the classic screen" in the shell's no-JavaScript message (admin-ui-spec §A.10). Then the classic screens, `list-table.php` and `class-mailweb-simple-table.php` are removed in a release of their own, with the owner's yes.
4. `tools/ci/settings_parts_baseline.txt` (rows 51–54) is updated in the same change that moves a screen, including the Inbox's move to `mailweb-inbox`.

---

## 6. Audit, release and testing checks, as the plan for each phase

Checked now with the `wp-plugin-audit` method (verified by reading each line). These are fixed in the phases named.

| # | Finding | File:line | Fix | Phase |
|---|---|---|---|---|
| A1 | Header is in PHPDoc style with `@wordpress-plugin`, `@package`, `@author`, `@copyright`, `@license` ✓, but `Description` is 228 characters (limit 140) and still says "Own menu: Mail (Inbox, Overview, Settings)" | `mailweb.php:12` | Shorten, for example "Hostinger Mail in WordPress: inbox, website form messages, SMTP sending with a daily limit and queue, and a FluentCRM feed." (123 characters). Add `Requires Plugins: kits-core` only when the shell becomes required (phase 2) | 1 |
| A2 | `readme.txt` has no `== Upgrade Notice ==` | `readme.txt` | Add, one line per release | 1 |
| A3 | Changelog 3.2.1 says "a MailWeb tab on the Kits screens"; since 10 Nov it is a row on Kits → Plugins (`kits-hub.php:20`) | `readme.txt:78` | Correct in the next changelog entry; history is not rewritten | 1 |
| A4 | `mailweb.pot` was edited by hand. Its `Report-Msgid-Bugs-To` points at wordpress.org/support (MailWeb is not on WordPress.org). Dated 31 Oct, 227 strings | `languages/mailweb.pot:5–12` | `wp i18n make-pot . languages/mailweb.pot --domain=mailweb --exclude=vendor,node_modules,build --headers='{"Report-Msgid-Bugs-To":"https://wugweb.com/plugins/mailweb/"}'` (the `composer make-pot` script gains the header). From phase 2 also `wp i18n make-json`, with `wp_set_script_translations( …, 'mailweb' )` | every release |
| A5 | `MAILWEB_CAP` is defined in `inbox.php:28` but used by `overview.php`, `home.php` and `kits-hub.php` (works because of the require order) | `inbox.php:28` | Move it to `mailweb.php` with the other constants | 1 |
| A6 | Incoming mail saves during page output, with no redirect after the POST | `mail-in.php:233` | Becomes `POST …/services/mail-api` and `…/webhook/<key>` | 3 |
| A7 | State-changing GET links (nonced `admin-post.php?action=mailweb_act&do=trash…`) | `inbox.php:204` | REST POST in the shell; the GET handler only in the fallback | 2 |
| A8 | Inline `<style>` and `<script>` in the Inbox, compose and profiles screens | `inbox.php:290`, `543`; `profiles.php:182` | Gone with the shell screens (the look comes from the design system) | 2–3 |
| A9 | `composer lint` runs the full `WordPress` standard; the Deploy check runs only the security ruleset, on new findings | `composer.json:25`; `deploy-check.yml:72` | Changed files must pass `phpcs --standard=WordPress-Extra` plus the security ruleset before each PR (review gate); the CI delta stays as it is | every phase |

**Checked, not bugs (leave them):**

- `GET mailweb/v1/mail-in` and `GET aw/v1/mail-in` use `__return_true` and answer only "ready".
- The webhook POST has no capability check, because it is protected by per-mailbox secrets with `hash_equals`.
- Direct `$wpdb` calls on the plugin's own queue table, with `phpcs:ignore` notes.
- The `retry` UPDATE has no `prepare()` because it takes no input.
- Uploads: `is_uploaded_file`, size checked before reading, type from `wp_check_filetype()` on the name (not `$_FILES['type']`), never written to disk.
- Downloads: `sanitize_file_name` plus `nosniff`.
- `mailweb_api()` and `mailweb_call()` only call the fixed Hostinger host, so there is no SSRF.
- Errors are logged with addresses replaced (`mailweb_clean_error()`).
- Every `admin_post_*` handler checks both the capability and the nonce (`inbox.php:648`, `mail-cap.php:446`, `smtp.php:111`, `mailboxes.php:115`, `profiles.php:190`, `crm-sync.php:343`); the bulk handler checks `bulk-messages` and `MAILWEB_CAP`.

**Capability and nonce on every new route.** Each `register_rest_route` has a `permission_callback`. It uses `MAILWEB_CAP` for Dashboard and Inbox routes and `manage_options` for Settings, sending and queue actions. No route uses `__return_true`. Each has full `args` schemas with sanitize and validate callbacks. The nonce is core's `wp_rest` through `api-fetch`. The Inbox download link carries `_wpnonce` for the GET.

**Tests and CI.** Today the Deploy check runs `tools/ci/mailweb_addresses_test.php`, PHP lint, the PHPCS security delta and `smoke.sh` (which activates MailWeb). There is no PHPUnit and no Plugin Check. The plan follows the repo's way, pure-PHP tests plus `smoke.sh`, with no local WordPress or MySQL (owner rule):

1. **`plugins/mailweb/tests/run.php`** (pure PHP, small stubs for `get_option` and `update_option`), run by the Deploy check like the SourceWeb tests. It covers:
   - `mailweb_is_block_error()`, with every message in its docblock and the recipient cases;
   - `mailweb_clean_error()` (addresses removed, 250 characters);
   - the `X-Mailweb-Priority` header parse;
   - `mailweb_cap_settings()` clamps and `mailweb_room()` for priorities 0–3, including that 1 never takes the campaign share;
   - `mailweb_crypt` round trip and the `mwenc1:` prefix;
   - the alias line parser;
   - the settings sanitize callbacks.

   `mailweb_addresses_test.php` moves in beside them.
2. **`smoke.sh` additions** (phase 1). They run in CI's throwaway WordPress only:
   - every `mailweb/v1/*` route is registered and none uses `__return_true`;
   - a logged-out request gets 401, a subscriber 403, an editor 200 on Dashboard and Inbox routes and 403 on Settings routes (`rest_do_request` in `wp eval`);
   - `GET /wp/v2/settings` as admin contains no `mailweb_smtp`, `mailweb_api_token` or `mailweb_secret_*`;
   - the queue table exists at schema 2;
   - `mailweb_flush` and `mailweb_crm_sync` are scheduled.

   No mail is sent: there is no SMTP password in CI, and `pre_wp_mail` is short-circuited in the smoke config.
3. **Plugin Check** in the Deploy check, report mode first (`continue-on-error`, like the settings-parts step): `wp plugin install plugin-check --activate` then `wp plugin check mailweb`, with WordPress.org-only checks left out (`plugin_updater`, `trademarks`), since MailWeb ships by Update URI. It becomes a hard step once the report is clean.
4. **JS** (phase 2): `wp-scripts lint-js`. The built files are committed, and a Deploy check Node step runs `npm ci && npm run build` and fails if `build/` differs (as PlanWeb §4.6).
5. PHPUnit is not added. If the owner later wants it, `WP_UnitTestCase` runs in CI's MySQL service, which already exists.

**Release (every phase, `wp-plugin-release`):**

1. Header `Version`, `MAILWEB_VERSION` and `Stable tag` move in step.
2. Changelog and Upgrade Notice entries.
3. `Tested up to` is checked against the released WordPress at that date.
4. `.pot` (and JSON) regenerated.
5. `MAILWEB_DB_VERSION` stays `2` (no schema change).

Versions: 3.3.0 for phase 1, 4.0.0 for phase 2 (new admin), then minor versions.

---

## 7. Requests to Kits Core (parts MailWeb needs from the shell)

MailWeb builds and styles none of these. Each is checked against `docs/kits-plugin-ui.md` once it exists.

1. **A detail panel next to the working list** (open a row without leaving the list; back keeps the list's page, filters and selection).
2. **Views with counts** (Inbox 3, Spam, Website forms 2), and **actions that change per view** (bulk and row).
3. **A plugin-owned content slot inside the detail panel** for a sandboxed `<iframe srcdoc>`, sized to the panel. The shell never renders mail HTML.
4. **A rich-text field** in the edit form (bold, italic, links, lists, paste cleaned), with an HTML value. Used for compose and signatures.
5. **A file field** with a total-size limit, in the compose form; **a download action** on a row or panel.
6. **A list server-paged by the plugin** (the Mail API pages; no total in the "All" mix beyond the 500 cap), with an "about" count.
7. **A secret field** that shows set / not set / "set in wp-config.php" (read-only). Disconnect a service can hide (FluentCRM).
8. **A confirm step** on single actions (Resume sending, Send queued now, Retry held, test email).
9. **A list editor** for pairs (aliases), and **a media picker** (profile picture).
10. **A chart widget** (sent per day, 30 days), with a text table of the same numbers.
11. **A top-level menu badge** that the plugin sets (unread mail plus unread forms), and the shell header showing the same count on the Inbox tab.
12. **The forms-source counts:** MailWeb's own change, not Kits Core's. `mailweb_forms_source` gains an optional `counts` callable (`[label => n]`), which Core can fill from `aw_mail_ff_unread_counts()`. It is backward compatible.

---

## 8. Icon

The source is `docs/brand/plugin-icons/`: `mailweb.svg`, plus PNGs at 128, 256 and 1024 pixels. The owner's set lands there separately; this plan does not draw or change it. When it lands, the files are copied into `plugins/mailweb/assets/` under the same names, and the source stays in `docs/brand/`. Where each is used:

1. **`mailweb.svg`** in the shell's app header, beside "MailWeb", and in the Kits → Plugins and Kits Home plugin status rows (`kits_plugin_screens` and `kits_status_rows` gain an icon field, if the shell spec adds one).
2. **`mailweb.svg`** as the WordPress menu icon: `add_menu_page` takes it as a `data:image/svg+xml;base64,…` URI in place of `dashicons-email` (`overview.php:202`). If the owner's SVG is not single colour, WordPress cannot tint it, so `dashicons-email` stays until a single-colour menu version is in the set. This follows the brand rule (black, white, grey; yellow as a rare accent).
3. **PNG 128 and 256** on the update screen (`icons` → `1x` and `2x` in the Update URI response), when the Wugweb update server serves them.
4. **PNG 1024** stays in `docs/brand/` as the master for listings and social cards; it is not shipped in the plugin zip.

The icon lands in phase 4, or in any earlier phase once the set is on `wordpress-dev`.

---

## 9. Phases

Each phase is its own branch and PR into `wordpress-dev` (bigger technical work, CLAUDE.md rule 3). The review gate runs before the owner merges:

- the code-review skill;
- wp-plugin-audit;
- PHPCS on changed files;
- the Deploy check green;
- Codex comments answered.

The owner merges, then the phase ships with `tools/aw-ship.sh` on the owner's yes, never while a FluentCRM campaign is sending (the gate refuses). **Before any MailWeb deploy:** the AW Mail Guard zip should be installed (Owner, pending since 3 Nov). Without it, the few seconds while MailWeb's folder is replaced send through PHP `mail()`.

| Phase | What | Size | Risk | Proof (read-only on live; no email sent) |
|---|---|---|---|---|
| 1. Foundations (3.3.0) | Header and readme (A1–A3), `MAILWEB_CAP` move (A5), regenerated `.pot` (A4), the Site Health test "Email sending", `mailweb_status_line()` for Core's Check site box and the Kits Home row, `GET mailweb/v1/mailboxes` (PlanWeb's picker), the `counts` key on the forms source, the sending-paused notice for admins with dismiss until the next pause (§4.4, `POST mailweb/v1/notice/pause/dismiss`), `tests/run.php`, smoke and Plugin Check additions. **No screen changes** | Small: ~7 files, no new screen | Low. Mail path untouched: in `mail-cap.php` only the notice (474–502) changes; the queue, `pre_wp_mail`, flush and `smtp.php` are not edited | Deploy check green with the new tests. On live: Plugins screen shows 3.3.0; Site Health lists "Email sending: running"; Check site shows the one line; the option snapshot is unchanged (§5); the PHP log has no new lines; the queue counts by status move only by normal traffic |
| 2. Dashboard and Inbox on the shell (4.0.0) | `includes/rest.php` (Dashboard and Inbox routes), `includes/shell.php` (registers tiles, sections, list, panel), one JS entry; the menu change (`mailweb` = Dashboard, Inbox second at `mailweb-inbox`, redirects and link updates in §3 and §5); classic screens kept as the no-JS fallback; A7, A8 for the Inbox | Large: new build, ~8 routes, a working list with a reading panel and compose | Medium. The Inbox is daily work, so the fallback screen stays one click away, and actions reuse today's functions. The send path is the same `mailweb_send_from_form()` logic | CI: route permission tests (401/403/200). On live, as an editor and as admin: Dashboard tiles match the classic numbers on the same minute (unread, sent 24 h, waiting, failed/held, paused); Inbox lists the same first page as the classic screen for All and for support@; the MailWeb menu opens the Dashboard and Inbox is second; an old `page=mailweb&folder=forms` link and `page=mailweb-overview` land on the right screens; opening a message marks it read (as today); Archive and Mark unread on one message the owner names, then undo; **no Send pressed**; the owner sends the first real reply when ready |
| 3. Settings on the shell (4.1.0) | Connected services (Mail API, webhooks, SMTP, FluentCRM), Sending, Mailboxes and aliases, Profiles; `register_setting` for the four non-secret options; `sub=` redirects; A6 | Medium: four services, three groups, one redirect rule | Medium. Settings hold the token and passwords: a wrong save can stop incoming mail or sending. Empty secret fields keep the stored value, and Disconnect asks first | CI: `/wp/v2/settings` exposes no secret. On live: every service row shows the same state as the classic screen (set / not set / wp-config); open each Edit and Cancel; old `sub=incoming` and `sub=sending` addresses land on the right section; the option snapshot is unchanged after opening and cancelling each form; **no Save on a secret, no test email** |
| 4. Clean-up (4.2.0, after two releases) | Remove the classic screens, `list-table.php`, the simple-table helper and the fallback `admin_post_*` paths; update `settings_parts_baseline.txt`; the icon if landed | Small | Low. Only after the owner confirms the shell screens are in daily use | Smoke loads every MailWeb screen; old addresses still land; the option snapshot is unchanged |

---

## 10. Decisions (owner, 11 Nov 2026)

1. **Sending-paused notice** (§4.4): it stays on every admin screen. It shows only to admins and can be dismissed until the next pause. Phase 1.
2. **Menu** (§2, §3, §5): the MailWeb menu opens the Dashboard. Inbox is the second item. Phase 2.
3. **vedanshu@** (§3.4): "Send from it" stays off, so every reply goes from support@.
