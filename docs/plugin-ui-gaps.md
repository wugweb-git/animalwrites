# Plugin screens: classic vs new (audit, 12 Nov 2026)

Code read at live `d9163bf7`. **Have** = on the new screen. **Classic only** = still works at its old address, but the new screens don't show it and nothing links to it. **Missing** = gone from the new screen.

How to add, by letter:
- **(a)** an existing shell part (DataList, SettingsGroup, Field, Switch, Tile, RefTable, SectionCard, FieldReview, LinkedSetting, MiniChart …);
- **(b)** a new REST route in the plugin's own namespace, with the capability shown;
- **(c)** a shell part not built yet (pending list): detail panel, media fields, pair-list editor, row view in a list. Built 12 Nov: styled select and date inputs, calendar grid, FieldReview "Reject ticked", secret field, confirm-with-count.

## Main reason things look missing

The new screens cover only a few pages of each plugin. The other pages still draw the classic screen at their old address, but the new header has no tab for them, and the plugin is folded into one Plugins menu item.

| Plugin | New screens | Classic-only pages |
|---|---|---|
| SourceWeb | Dashboard, To review, Enrichment review, Settings; since 12 Nov every Sourcing tab (see "Rebuilt on the shell") | The feed form (`&feed=<id>`) |
| InsightWeb | Dashboard, Settings; since 12 Nov every tab (see "Rebuilt on the shell") | — |
| RankWeb | Dashboard (with Modules), FAQ, Settings (since 12 Nov) | — |
| MailWeb | Inbox, Settings | Compose (stays classic: it sends email) |
| PlanWeb | Dashboard, Entries, Settings | the month grid |
| DirectoryWeb | Dashboard, Claims, Settings | Listings (WordPress's own list) |
| Kits | Home, Settings, Tools, Help, Theme Options | Keys, Modules, Dates (no new look) |

**Step 1, which needs no new part:** add header tabs for every classic-only page, each pointing at its classic address, until that page is rebuilt. That brings back every section at once. In SourceWeb that means `sourceweb_ui_caps()` and its labels in `sourceweb-ui.php`; InsightWeb and RankWeb take the same change.

## SourceWeb

| Page | Missing on new | How to add |
|---|---|---|
| Sources | whole list: views, search, columns, short names, bulk/row actions; add/edit source; feeds list and feed form | (a) DataList + SettingsGroup; (b) `GET sources`, `POST sources/decide`, `POST sources/save`, `GET/POST sources/feeds` (manage_options); (c) styled select (status, feed type) |
| Keywords | relevance + aggregator switches; press settings; list with roles/categories; add/edit; Test a headline; Suggestions | (a) DataList, SettingsGroup, Switch, Field; (b) `GET keywords`, `POST keywords/decide`, `POST keywords/save`, `GET keywords/test`, `GET/POST keywords/suggestions`, `POST settings/keywords` (manage_options); (c) date input, category checklist, confirm-with-count |
| Rules | section rules, tag rules, summary rules, trust order, field rules | (a) DataList, SettingsGroup, SettingsBlock, Field; (b) GET and save routes for each part (manage_options); (c) pair-list editor (entities), styled select, confirm (Reset) |
| AI models | keys list, at-a-glance, daily caps, groups, add/edit key, attach/detach | (a) TileGrid, RefTable, DataList; (b) `GET ai` (last 4 characters only, never the key) and write routes like the classic handlers (manage_options); (c) secret field. Never reuse the worker route `aw/v1/ai-keys`. |
| Workers | list with views, **Run now**, runs log, add/edit/delete planned worker | (a) DataList, RefTable, SettingsGroup; (b) `GET workers`, `POST workers/run`, `GET workers/<id>/runs`, save/delete (manage_options); (c) styled select, confirm |
| Rejected / Relay log / Change log | the tables (search, sort, paging) | (a) DataList; (b) three read routes (manage_options) |
| Dashboard | keyword status, last change, relay count, links to Keywords, Rules, Press and Categories | (a) Tile + RefTable fed by the existing `GET dashboard` |
| To review | "Add a social post from its link"; items per page | (a) SectionCard + Field + Button; (b) `POST review/social-add` (edit_others_posts); a `per` parameter on `GET review` |
| Enrichment review | bulk Undo; thumbnails, source and duplicate links; `listing=` filter (Reject ticked done 12 Nov) | (a) bulk actions + a small change to the decide route; (c) media fields; extend `GET enrichment` |

**Bug found:** `sourceweb-rest.php:598` uses `$words` before it is set (it is set only at :753). The view counts on Enrichment review ignore the search, and PHP logs a warning on each load. Fix: read the search into `$words` before line 598.

## InsightWeb

| Page | Missing on new | How to add |
|---|---|---|
| Dashboard | 16 of the 17 Overview boxes: Priorities, Quick wins, Rising, Movers, Brand, AI referrals, Top referrers, Campaigns by source, Not found, Index status, Discover/News, Clarity, Site search, Content gaps, Crawler access, Bing crawl | (a) SectionCard + DataList/RefTable/MiniChart per box; (b) `GET dashboard/box/<key>` (read) |
| Keywords, Rising, Pages, Clarity, Check list, Sourcing keywords, Links, Server | the whole tabs | (a) DataList each; (b) one read route per tab |
| Settings | schedule, seed date; Run now, snapshots, imports, Bing count/add, Delete data | (a) SettingsGroup + Field; (b) `POST insightweb/v1/run` as a background job, plus import routes (manage_options); (c) date input, confirm-with-count (Delete data) |

## RankWeb

| Page | Missing on new | How to add |
|---|---|---|
| Dashboard | Modules table with "where to set up" links, per-module Off state, Search Insights row | (a) DataList; add `modules[]` to `GET dashboard` |
| FAQ | the whole tab | (a) DataList; (b) `GET faq`, `POST faq/approve`, `POST faq/recount` |
| Settings | nothing missing | — |

## MailWeb

| Page | Missing on new | How to add |
|---|---|---|
| Inbox | media picker (Move to folder and row actions done 12 Nov) | (c) media fields |
| Overview | done 12 Nov: the feature table is on the Dashboard | — |
| **Broken links** | `mailweb_settings_url()` (`includes/mail-in.php:50`) has no `ui=classic`: Test email and Send queued now can't be reached, the paused notice has no Resume, and classic saves land on the new screen | PHP only: add `ui=classic` to that link and to the redirects that follow a save |
| Notices | `mailweb_done` notices are lost after a save | PHP: carry `done` to the screen and show a Notice (a) |

## PlanWeb

| Page | Missing on new | How to add |
|---|---|---|
| Calendar | done 12 Nov: the month grid on the Dashboard | — |
| Entries | done 12 Nov: `&edit=ID`, Type/Status badges, past dates greyed | — |
| Settings | done 12 Nov: the mismatch table has 3 columns | — |
| Links | System Status "Calendar" link points to a `#planweb-import` that doesn't exist; the Import check tile has no link | PHP: link to `planweb_ui_url('settings') . '#import'` |

## DirectoryWeb

| Page | Missing on new | How to add |
|---|---|---|
| Menu | Dashboard and Settings have **no menu entry** (`directoryweb-ui.php:93-94`, empty parent) | PHP: put them under `edit.php?post_type=aw_listing` |
| Claims list | done 12 Nov: Reference column, search by reference | — |
| One claim | link to Enrichment review for Undo (current logo done 12 Nov) | extend `GET claims/<id>` (read only) |
| Dashboard | Places covered and Claimed tiles have no link | (b) `GET listings` (edit_posts) + (a) DataList on a Listings tab, or leave them without a link |
| Settings | Types has no link to Tags | (a) LinkedSetting |

Claims stay read-only: approve, reject and apply stay on Directory → Claims. That is by design, not a gap.

## Kits

Home, Settings, Tools and Help show the same content in both looks; nothing was lost when the new look became the default. What was removed on 11 Nov was removed from both looks by the owner (Versions and limits, Backup, Theme switch log, Setup checklist).

| Item | Problem | How to add |
|---|---|---|
| Keys, Modules, Dates | no new look; not linked from Settings | PHP: add links to the "Connected services and modules" card; to draw them in the frame, add them to `kits_frame_screens()` |
| Settings card wording | says keys are in RankWeb → Settings, but RankWeb says keys stay in Kits → Keys | PHP copy fix |
| Theme Options → Cards | the read-only Card style values (corners, colours, token values) are gone | (a) RefTable fed by the existing `kits_admin_app_data` |
| Theme Options → Layout | prints an empty "Spacing" form | PHP: remove `layout-spacing` (`admin-app.php:72`, `:219`) |
| Theme Options → Extra | the links box isn't in the app | (a) `links` on the section |
| Help → Docs | "Backup" and "Log" links point to sections that no longer exist | PHP: fix the links |
| Keys on the new look | — | (c) secret field; (b) `aw/v1/keys` (manage_options) |

## Done (12 Nov 2026)

- MailWeb: links to Site sending open the classic screen (Test email, Send queued now, Resume); send, move and flag results show on the new Inbox.
- SourceWeb: tabs for Sources, Keywords, Rules, AI models and Workers (administrators), opening today's screens; Enrichment review view counts follow the search box.
- InsightWeb and RankWeb already had tabs for their classic-only pages.
- DirectoryWeb: in the Plugins menu (Plugins → DirectoryWeb), from another session the same day.
- PlanWeb: System Status "Calendar" link and the Import check tile open Settings → Import from Dates.
- Kits: the empty Spacing form is gone from Theme Options → Layout; Settings → Connected services and modules links Keys and Modules and says where keys are; Help → Docs "Daily backup" links UpdraftPlus.

## Rebuilt on the shell (12 Nov 2026)

- **SourceWeb Workers** (`admin.php?page=aw-sourcing&tab=workers`): list with the group, Late, Failing and Planned views, search and sort; Run now; the Run now log; a worker's runs (`&runs=<id>`); Add New Worker and edit (`&edit=<id|new>`) with group, new group, late limit, schedule wanted and the keys it may use (last 4 characters only); Delete for a Planned worker. Routes `sourceweb/v1/workers…` (manage_options) call `aw_worker_save()`, `aw_worker_delete()` and `aw_workers_run_now()`, which the classic screen uses too.
- **SourceWeb Sources** (`admin.php?page=aw-sourcing`): list with views, search, sort, short names (Save short names), bulk and row actions (status, relay, press before phase 2, Delete for a source with nothing attached); Add New Source and edit (`&edit=<host|new>`); a site's feeds and the Feeds view (`&view=feeds`) with on/off, keyword check and Delete. Routes `sourceweb/v1/sources…` call `aw_sourcing_sources_apply()`, `aw_sourcing_shorts_apply()`, `aw_sourcing_source_save()` and `aw_sources_feed_op()`. **Still classic:** the feed form (`&feed=<id>`), at its own address.
- **SourceWeb Keywords** (`&tab=keywords`, from phase 2; before it the word lists stay classic): list with the role, category and Hindi views, search, sort, row and bulk actions (roles, category, delete from sourcing); Add New Keyword and edit (`&edit=<id|new>`); the relevance and aggregator switches, Press discovery; Test a headline; Suggestions from Search Insights (`&kw=suggestions`). Routes `sourceweb/v1/keywords…` call `aw_sourcing_kw_add()`, `aw_sourcing_kw_apply()`, `aw_sourcing_kw_bulk()`, `aw_relevance_verdict()`, the Settings API sanitizers and `aw_sourcing_suggest_apply()`; they answer 409 before phase 2.
- **SourceWeb Rules** (`&tab=rules`, from phase 3): Section rules (a section's categories and tags, tag rules), Summary rules, Directory enrichment (the trust order, a rule per field). Routes `sourceweb/v1/rules…` call `aw_sourcing_section_save()`, `aw_rules_tag_save()`, `aw_rules_tag_delete()`, `aw_rules_priority_save()`, `aw_rules_field_save()` and `aw_summary_rules_sanitize()`; they answer 409 before phase 3.
- **SourceWeb AI models** (`&tab=ai`): At a glance, the workers and their keys, Daily caps, Keys by group; the keys list with the Working, Waiting, Broken and GitHub secrets views, search, sort, Move up / down (plain list only), Check again, Remove, Attach to / Detach from a worker; Add New Key (`&edit=new`), Edit key (`&edit=<key id>`), Keys overview (`&overview=1`). A key goes out only as its id and last 4 characters; the workers' route `aw/v1/ai-keys` is not used. Routes `sourceweb/v1/ai…` call `aw_ai_keys_add()`, `aw_ai_key_edit_save()`, `aw_ai_keys_list_op()` and `aw_ai_key_records()`.
- **SourceWeb Rejected, Relay log, Change log** (`&tab=rejected`, `&tab=relay-log`, `&tab=log`): read-only lists with views, search, sort and pages. Routes `sourceweb/v1/rejected`, `relay-log` and `changes` read `aw_relevance_rejected_rows()`, `aw_relay_log_rows()` and `aw_sourcing_change_rows()`.
- **InsightWeb Dashboard boxes** (`admin.php?page=aw-search-insights`): the 16 Overview boxes after Daily jobs (Priorities to Bing crawl), each read from `GET insightweb/v1/dashboard/box?key=` (`aw_si_cap()`), which runs the box's own function (`aw_si_overview_boxes()`, the same list the classic Overview draws) and hands it over as text, lists, tables and notices; no markup, form or script goes out.
- **Kits page parts** (`plugins/kits-core/includes/admin-shell-parts.php`, the shell's `PageParts` in `src/shell/parts.js`): a classic screen's own output, drawn on the shell. `kits_shell_html_parts()` reads it into text, notices, tables, its list table (views, search, filters, sort, pages, row and bulk actions) and its forms (fields, buttons, hidden fields and nonce); `PageParts` draws them with DataList and Field and sends every change as the same link or form request the classic screen sends, so the classic handlers keep saving. No markup or script goes out, links only when http(s), relative or `*.php`, and never a password field. Tests: `plugins/kits-core/tests/shell-parts-tests.php`.
- **InsightWeb Keywords and every other tab** (`&tab=keywords|pages|checklist|rising|clarity|links|server|sourcing`): each tab's own output after its load hook, through Kits page parts (`insightweb-tabs.php`). Bulk and row priority overrides, link types, Check list buttons, Rising and Pages forms go to the same handlers.
- **RankWeb FAQ** (`admin.php?page=aw-faq`): FAQ box on (disabled for non-administrators, as today), Pages with an FAQ (views, search, Approve) and Counts with Recount, through Kits page parts (`rankweb_faq_parts()`).
- **InsightWeb Settings → Runs and data** (`&tab=settings#more`): Schedule, Keys, Keyword map seed, Run now, the snapshot and import rows (CSV upload included) and Delete data (type DELETE), from the Settings tab's own rows through Kits page parts; the Numbers row stays in its own groups. The link to the classic Settings is gone.
- **RankWeb Dashboard → Modules**: Needs attention, the Modules table (where each is set up, Off and Partly off), On edit screens and Commands, from the Overview's own body (`rankweb_overview_parts()`).
- **MailWeb Inbox → Move to folder**: the open message has a Move to folder choice with its mailbox's other folders; `POST mailweb/v1/inbox/move` calls `mailweb_move_to()` (the same Mail API move as Archive). The link to the current screen for moving is gone.
- **MailWeb Dashboard → Features**: the classic Overview's feature table (where each feature is set up and its state), from `mailweb_home_body()` through Kits page parts.
- **MailWeb Inbox row actions**: Archive, Mark read or unread and Trash on each row (those the folder offers), through `POST mailweb/v1/inbox/bulk`.
- **PlanWeb Entries**: `&tab=entries&edit=ID` opens the entry's form (`GET planweb/v1/entries/<id>`, not from the Trash); Type and Status badges and greyed past dates as on the Calendar; the import mismatch table has Check, In Dates and In PlanWeb.
- **DirectoryWeb Claims**: a Reference column (AW-C-<serial>) and search by reference; a claim with a new logo shows the listing's current logo beside it. Still read only.
- **Kits shell parts** (`plugins/kits-core/src/shell/calendar.js`, `inputs.js`): CalendarGrid (one month, Monday first, previous/next/Today, today marked, past greyed, a list on phones); every select and date, time, url, email, search and password input styled like the text inputs, with `Select`; `SecretField` (only whether a secret is set and its last 4 characters); `ConfirmButton` (asks in place with the count, and a word to type when it cannot be undone); FieldReview `onReject` (Reject ticked).
- **PlanWeb Dashboard → Calendar**: the month grid from `GET planweb/v1/month?m=Y-m` (edit_planweb_entries), each entry with its Type and Status badges and opening its form on Entries; the month stays in the address (`&month=`).
- **SourceWeb Enrichment review → Reject ticked**: rejects only the ticked values (`do=reject` with ids).

## Suggested order

1. **Fixes:** MailWeb `ui=classic` link-outs and notices; the SourceWeb `$words` bug; DirectoryWeb menu entries; PlanWeb and Kits dead links; the Kits empty Spacing form. All PHP, all small.
2. **Tabs for classic-only pages** in SourceWeb, InsightWeb and RankWeb. Nothing is hidden after this.
3. **SourceWeb Workers (with Run now) and Sources**, then Keywords, Rules and AI models. Each is a DataList plus its routes.
4. **InsightWeb Dashboard boxes and tabs**; **RankWeb Modules and FAQ**.
5. **Shell parts** the rest needs: calendar grid (PlanWeb), secret field (AI models, Keys), "Reject ticked", styled select and date inputs, confirm-with-count.
