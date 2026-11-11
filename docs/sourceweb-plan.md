# SourceWeb on the Kits plugin standard (plan, 11 Nov 2026)

**Status:** plan only, waiting on the owner. No code until the owner approves it.
**Decision (owner, 11 Nov 2026):** SourceWeb (`plugins/aw-sourcing`) moves onto the shared Kits Core admin shell (Dashboard, one working list, Settings) and meets the Kits plugin standard (header, readme, translations, tests). The data stays as it is: options, the `aw_enrich` table, the `aw/v1` routes the workers use and every `aw_` name. The aw→kits rename is deferred. The workers repo is out of scope.
**Checked against:** `origin/wordpress-dev` at `c37d3b7c`. Every file:line below was read on that commit.
**Paths:** `sw/` is `plugins/aw-sourcing/includes/`, `core/` is `plugins/animal-writes-core/includes/`, `kc/` is `plugins/kits-core/includes/`.

## 0. Owner answers (11 Nov 2026)

All five settled by the owner on 11 Nov. The bracketed line under each is the answer.

1. **The admin look.** `docs/kits-plugin-ui.md` is on no branch yet (checked with `for-each-ref` + `ls-tree`, 11 Nov). The icons are: `docs/brand/plugin-icons/` is on `wordpress-dev` (`d5e2bcbb`), and SourceWeb's copy is in `plugins/aw-sourcing/assets/` (§5). `docs/admin-ui-spec.md` §A.6 still says "built with `@wordpress/components`", but the later owner entry of 11 Nov (Notion decision log) says: a Kits admin design system ships in Kits Core, Kits plugins look like Google Site Kit, and WordPress packages are plumbing only. This plan follows the later entry, as the DirectoryWeb and InsightWeb plans do. When `kits-plugin-ui.md` lands, §2 is checked against it. [**Yes:** the Site Kit look built into Kits Core.]
2. **Worker routes and `aw_worker`.** The brief says the worker routes keep the `aw_worker` capability. Today only two of them check it: `GET aw/v1/ai-keys` (`sw/ai-keys.php:526`) and `POST aw/v1/worker-run` (`sw/workers.php:235`). The others check `edit_posts` or `edit_others_posts`, most with an application password (§1.5), because the workers sign in as an Editor. **This plan changes no worker route's permission check.** Switching them to `aw_worker` would lock the workers out. [**Yes:** keep each check exactly as it is.]
3. **The Google key placeholder.** Directory enrichment shows the Google key's last four characters in the empty field ("Saved (…abcd)", `sw/enrichment-admin.php:430`). The standard says keys never reach the browser. [**Yes:** show "Set" or "Not set" only, from phase 2.]
4. **Social posts.** The module's code is Core's (`core/social-posts.php`, 1,093 lines: the `aw_social` type, its box, its intake and the block). Only its two switches move to SourceWeb → Settings → Social posts, with the same options `aw_social_on` and `aw_social_show`. The module stays off. [**Yes:** keep the code in Core; move only the switches.]
5. **The copy of live.** The same question as the DirectoryWeb and InsightWeb plans: staging runs a 15 Oct database. [**Refresh staging first** with `tools/staging/refresh.sh` (`docs/staging.md`); the proof runs on staging after the refresh.]

## 1. Inventory

**Where each item lands:** **D** Dashboard, **R** To review, **S** Settings (with its group), **keep** (no screen, unchanged), **Core** (stays in Animal Writes Core), **KC** (Kits Core).

### 1.1 Shape and loading

| Item | Now | After |
|---|---|---|
| Plugin header | Plain `/** */` block, `Plugin Name: SourceWeb`, `Version: 0.1.0`, `Update URI`, `@package aw-sourcing` (`aw-sourcing.php:2-16`). No `@wordpress-plugin`, `Requires at least`, `Requires PHP`, `Text Domain`, `Domain Path`, `License URI` | PHPDoc header as MailWeb's (`plugins/mailweb/mailweb.php:2-23`): `@wordpress-plugin`, `Requires at least: 6.4`, `Requires PHP: 8.0`, `Text Domain: aw-sourcing`, `Domain Path: /languages` (phase A) |
| Version constant | `AW_SOURCING_LOADED` = `'0.1.0'` (`:35`), also the load-once guard | New `AW_SOURCING_VERSION`; `AW_SOURCING_LOADED` stays as the guard (phase A) |
| Loading | Animal Writes Core loads it by `require_once`; it may also be active on its own; without Core it loads nothing and shows a notice (`:21-34`) | Unchanged |
| Depends on Kits Core | `AW_SRC_CAP`, `AW_PUB_OPTION`, `AW_REL_OPTION`, `aw_publishers()`, `aw_host()` live in `kc/sourcing.php:14-38` | Unchanged. The shell parts are guarded with `function_exists`, so SourceWeb still works if the shell is missing |
| readme, changelog, `.pot`, `uninstall.php` | None | `readme.txt`, changelog in it, `languages/aw-sourcing.pot` (phase A). No `uninstall.php`: uninstall must never drop `aw_enrich` or the options |
| Activation / deactivation hooks | None (`grep register_*_hook`: no result) | None added: the plugin is loaded by Core and is never deactivated on its own. Recorded, not changed |
| Menu shape | `sw/menu-model.php`: `AW_SRC_MENU` `aw-sourcing-menu`, `AW_SRC_PAGE` `aw-sourcing`, `AW_SRC_SCREEN` `sourcing_page_aw-sourcing` (kept by forcing the hook prefix, `sw/admin-menu.php:65`), report tabs, Settings last | Slugs kept (§2.0); `AW_SRC_SCREEN` kept so saved screen options still apply |
| Capabilities | `AW_REVIEW_CAP` = `edit_others_posts` (`sw/review-queue.php:15`), `AW_ENRICH_CAP` = `edit_others_posts` (`sw/enrichment-admin.php:18`), `AW_SRC_CAP` = `manage_options` (`kc/sourcing.php:26`), `manage_options` for AI models and Workers | Unchanged |

### 1.2 Screens today and where each lands

Menu today: SourceWeb (`dashicons-rss`, position 26) → Overview · To review · Enrichment review · Sources · Keywords · Rules · AI models · Workers · Rejected · Relay log · Change log · Settings (12 items, as checked on live 25 Oct).

| Screen | Address | Code | Cap | Lands |
|---|---|---|---|---|
| Overview | `admin.php?page=aw-sourcing-menu` | `sw/admin-menu.php:64-66`, page `:274-397` (feature table by group, worker runs) | `edit_others_posts` | **D**: tiles §2.1; the feature table becomes the Dashboard section "What SourceWeb does"; the runs table the section "Worker runs" |
| To review | `admin.php?page=aw-review` | `sw/review-queue.php:104`, table `sw/class-aw-sourcing-review-table.php` (views by type, search, row actions Edit / Source / Publish / Reject, bulk Publish / Reject, "Publish all N", why each was held) | `edit_others_posts` | **R** §2.2 |
| Enrichment review | `admin.php?page=aw-enrich-review` | `sw/enrichment-admin.php:187`, table `sw/class-aw-sourcing-enrichment-table.php` (listing, field, current, proposed, source, confidence, status; views Waiting / Applied / Rejected / Failed / Undone / All `:314`; per-field Approve / Reject / Undo, bulk) | `edit_others_posts` | **R**, view "Listing details" §2.2 |
| Sources | `admin.php?page=aw-sourcing` | `sw/source-sites.php`, `sw/class-aw-sourcing-publishers-table.php` (views All / Allowed / Neutral / Blocked / Press / Feeds / Code / Relay `:30`), edit screen `&edit=new|<host>`, Feeds inside each source (`sw/sourcing-lists.php:160-175`) | `manage_options` | **S → Sources** |
| Keywords | `…&tab=keywords` | `sw/keywords-admin.php` (8 views: Include, Animal, Context, Exclude, Search, Press, Category words, Hindi; `sw/class-aw-sourcing-keywords-table.php:32-41`), suggestions `sw/keyword-suggestions.php:143`, `:180`, headline test, per-search stats `sw/keyword-stats.php` | `manage_options` | **S → Keywords**, plus the "Searches" view that takes in Search Insights → Sourcing keywords (§1.10) |
| Rules | `…&tab=rules` | `sw/rules-admin.php:35` (replaces Sections, Summary rules, Directory enrichment tabs once Rules are live); parts `&part=sections|summary|enrich` (`:27-28`, `:74`): section rules, summary rules, enrichment sources + Google key + cap, trust order, 42 field rules | `manage_options` | **S → Rules** (three sub-groups, same parts) |
| AI models | `…&tab=ai` | `sw/ai-keys.php:188`, screen `aw_ai_keys_screen`, table `sw/class-aw-sourcing-ai-keys-table.php` | `manage_options` | **S → AI models**, drawn by today's callback; `ai-keys.php` not edited (§2.3.3) |
| Workers | `…&tab=workers` | `sw/workers.php:378`, forms `sw/workers-admin.php` (save `:290`, delete `:436`, Run now `:476`), registry of 19 workflows `sw/workers-registry.php:19`, groups and AI keys per worker `sw/workers-meta.php` | `manage_options` | **S → Workers**; health on **D** |
| Rejected | `…&tab=rejected` | `sw/relevance.php:391` (log `aw_relevance_rejected`, max 500) | `manage_options` | **D**, section "Reports → Rejected" |
| Relay log | `…&tab=relay-log` | `sw/relay.php:228` (last 200 requests) | `manage_options` | **D**, "Reports → Relay log" |
| Change log | `…&tab=log` | drawn by `aw_sourcing_page()` (`sw/admin-menu.php:34`), option `kits_sourcing_log` | `manage_options` | **D**, "Reports → Change log" |
| Settings | `…&tab=settings` | `sw/admin-menu.php:153-170`: "Workers use these lists" switch (`aw_sources` `live`, `sw/sourcing-lists.php:121-135`) | `manage_options` | **S → Workers** (first row) |
| Press queries, Categories, Sections, Summary rules, Directory enrichment (hidden tabs) | `…&tab=press|categories|sections|summary|enrich` | `sw/sourcing-lists.php:98-108` (hidden from phase 4), `sw/rules-admin.php:39-43` | `manage_options` | Already redirected today (Keywords → Press, Posts → Categories, Rules parts). Redirects kept (§3.1) |
| Sourcing keywords | RankWeb → Search Insights, tab `sourcing` | `sw/search-insights-tab.php:21-50` (filters `aw_si_tabs`, `aw_si_tab_sourcing`) | `manage_options` | **S → Keywords**, view "Searches" (per `docs/insightweb-plan.md` §1.7). The tab stays until InsightWeb's phase 2 removes the tabbed screen |
| WordPress Dashboard box "Sourcing" | `wp-admin/` | `sw/dashboard.php:84-122` | `edit_others_posts` | **keep**, links point at the new Dashboard |
| Kits rows | Kits → Plugins and System Status | `sw/kits-hub.php` (`kits_plugin_screens`, `kits_status_rows`) | `edit_others_posts` | **keep**; Kits Home → Plugin status row (`docs/admin-ui-spec.md` D6) |
| Plugins screen links | Overview, Settings | `sw/menu-model.php:87-92`, `sw/admin-menu.php:139-150` | — | **keep**: Dashboard, Settings |

**Moving in from other screens** (`docs/admin-ui-spec.md` §C):

| Item | Now | Code (stays in Core) | Lands |
|---|---|---|---|
| Thumbnails | Animal Writes → Check site, "Thumbnails" | `core/thumbs.php:135-141`: pictures used by more than one item, `aw_thumb_duplicates()`, option `aw_thumb_map`; public `GET aw/v1/thumb-dupes` (`:112-133`) for the deploy gate | **D** tile "Thumbnails", through a filter Core fills (§2.1) |
| Pictures (CC0) | Check site, "Pictures on News, Library and research items" | `core/cc0-images.php:953-981`: 8 counts (`aw_cc0_counts()`), Openverse requests today of 300 (`AW_CC0_DAILY_REQUESTS`, option `aw_ov_quota`), next `aw_cc0_run`, last 15 lines of `aw_cc0_log` | **D** tile "Picture pipeline" and its section, through the same filter |
| Modules (Social posts) | Kits → Modules (`aw-modules`) | `core/social-posts.php:66-79` (settings group `aw_modules`), `core/admin-home.php:39` | **S → Social posts** (two switches, same options) |

The Check site rows are drawn today by the `aw_site_check_after` action. They keep working there until Kits Home exists; then Check site redirects (`docs/admin-ui-spec.md` §E1) and these two rows are on SourceWeb's Dashboard only.

### 1.3 Options

Every option SourceWeb reads or writes (from `get_option` / `update_option` / `register_setting` across `sw/`). **No option changes name, shape or owner.**

| Option | What | Owner | Lands |
|---|---|---|---|
| `aw_sources` | sourcing lists, `live` switch | Core (`sources-config.php`) | S → Sources, Workers |
| `aw_publishers` (`AW_PUB_OPTION`) | publishers: allow / neutral / block, names | Kits Core | S → Sources |
| `aw_relevance` (`AW_REL_OPTION`) | relevance switches (`register_setting` `sw/sourcing-policy.php:862`) | Kits Core | S → Keywords |
| `aw_source_site_meta` | per-site meta (favicon, name) | SW | keep (hourly job) |
| `aw_summary_rules` | summary rules (`sw/summary-rules-admin.php:105`) | Core (`summary-rules.php:23`) | S → Rules → Summary |
| `aw_enrich_field_rules` | 42 field rules + trust order (`sw/rules.php:21`) | SW | S → Rules → Directory enrichment |
| `aw_enrich_sources` | enrichment source switches (11 sources, `sw/enrichment.php:45-58`) | SW | S → services + Rules → Directory enrichment |
| `aw_enrich_google_key` | Google Places key (`show_in_rest` false) | SW | S → services: "Set / Not set" only |
| `aw_enrich_live_cap` | monthly cap for the live Google block | SW | S → services (Google Maps row) |
| `aw_enrich_ledger` | spend per month (last 13) | SW | D (Enrichment tile) |
| `aw_enrich_hosts` | hosts the enrichment worker may read | SW | S → Rules → Directory enrichment |
| `aw_enrich_db` | `aw_enrich` schema version | SW | keep |
| `aw_ai_keys`, `aw_ai_models`, `aw_ai_usage`, `aw_ai_free_caps`, `aw_ai_provider_icons` | AI models and keys | SW (`ai-keys.php`) | S → AI models. **Never written by this work** |
| `aw_workers_meta`, `aw_workers_groups` | worker names, groups, AI keys per worker | SW | S → Workers |
| `aw_worker_dispatch_log` | Run now log | SW | S → Workers |
| `aw_workers_tick`, `aw_workers_tick_lock` | `wp aw workers-tick` state (hPanel cron every 15 min) | SW | keep |
| `aw_workers_watchdog_at`, `aw_ingest_watchdog_at`, `aw_ingest_watchdog_last` | watchdog times | SW | D (Workers, Intake tiles) |
| `aw_ingest_heartbeat` | last report from the workers (`POST aw/v1/ingest-heartbeat`) | SW | D (Intake tile) |
| `aw_relevance_rejected` (`AW_RELEVANCE_LOG`) | refused items (max 500) | SW | D → Reports |
| `aw_relevance_alert_pending`, `aw_relevance_alert_notice` | relevance alert | SW | keep (notice shown on SourceWeb screens only) |
| `aw_relay_log` | relay requests (last 200) | SW | D → Reports |
| `kits_sourcing_log` | change log | SW | D → Reports |
| `aw_sourcing_dismissed` | dismissed keyword suggestions | SW | S → Keywords |
| `aw_sourcing_phase`, `aw_sourcing_migrating`, `aw_sourcing_phase{1..4}_record`, `aw_backup_latest_{1..4}` | restructure phase switch, records, CLI backups | SW | keep (CLI only) |
| `aw_backfill_floor`, `aw_site_opportunities_last` | backfill floor; Opportunities fetch (paused by the owner) | SW | keep |
| Settings groups | `aw_sourcing_enrich`, `aw_sourcing_summary`, `aw_sourcing_sources`, `aw_sourcing_switches`; seed markers `aw_sourcing_seed_s35`, `aw_sourcing_seed_hc` | SW | Each group keeps its options; the screen they sit on changes |
| From elsewhere | `aw_thumb_map`, `aw_ov_quota`, `aw_cc0_log` (Core); `aw_social_on`, `aw_social_show` (Core) | Core | D; S → Social posts |

Transients (kept, no screen): `aw_review_count`, `aw_sourcing_host_stats`, `aw_sourcing_kw_stats_30`, `aw_summary_below`, `aw_worker_form_*`, `aw_ingest_alert_mailed`, `aw_workers_alert_mailed`, `aw_relevance_alert_sent`, `aw_featured_names`, `aw_seo_dir_data`.

### 1.4 Tables

| Table | Code | Lands |
|---|---|---|
| `{prefix}aw_enrich` (id, listing_id, field, value, old_value, sources, confidence, status, note, run, created, decided_at, decided_by) | `sw/enrichment.php:129-150`, version `aw_enrich_db` | keep; R → Listing details reads it |
| `{prefix}aw_si_keywords`, `{prefix}aw_si_kw_map` (the keyword table) | Core `keyword-table.php` | keep (Core's); S → Keywords reads and writes it as today |

### 1.5 REST routes (all kept, same namespace, path and check)

| Route | Code | Permission today | Who calls it |
|---|---|---|---|
| `POST aw/v1/ingest` | `sw/ingest.php:449` | `edit_posts` | workers |
| `POST aw/v1/exists` | `sw/ingest.php:557` | `edit_others_posts` | workers |
| `GET aw/v1/relay` | `sw/relay.php:193` | `edit_posts` + application password | workers |
| `POST aw/v1/worker-run` | `sw/workers.php:235` | application password + (`aw_worker` or `manage_options`) | workers |
| `GET aw/v1/ai-keys` | `sw/ai-keys.php:526` | `manage_options` or `aw_worker` | workers (**AI keys: untouched**) |
| `POST aw/v1/ai-usage` | `sw/ai-keys.php:583` | `edit_posts` + application password | workers |
| `GET aw/v1/sourcing-policy` | `sw/sourcing-policy.php:272` | `edit_posts` | workers |
| `GET aw/v1/same-story` (2 routes) | `sw/same-story.php:175-187` | `edit_posts` | workers |
| `GET aw/v1/relevance-config`, `relevance-items`; `POST relevance-verdicts`, `relevance-alert`, `relevance-rejected` | `sw/relevance.php:203-297` | `edit_posts` | workers |
| `POST aw/v1/ingest-heartbeat` | `sw/relevance.php:551` | `edit_posts` | workers |
| `GET aw/v1/summary-rules` | `sw/summary-rules-admin.php:17` | `edit_posts` | workers |
| `GET aw/v1/summary-rewrite` | `sw/summary-rewrite.php:366` | `edit_posts` | workers |
| `GET aw/v1/enrich/config`, `queue`, `index`, `cleanup`; `POST enrich/results` | `sw/enrichment.php:1041-1045` | `edit_others_posts` | enrichment worker |
| `GET aw/v1/listing-live` | `sw/enrichment-front.php:121` | public (`__return_true`), read-only | listing pages (live Google block; held by the monthly cap and the source switch, `:115`) |

New routes for the shell go under `sourceweb/v1/…` (§2.5). None of the routes above moves.

### 1.6 Admin actions (`admin-post.php`)

All 20 check a capability and a nonce today (read at each handler):

| Action | Code | Cap | Nonce |
|---|---|---|---|
| `aw_review`, `aw_publish_all` | `sw/review-queue.php:150`, `:246` | `edit_others_posts` | `aw_review`, `aw_publish_all` |
| `aw_enrich_decide` | `sw/enrichment-admin.php:194` | `edit_others_posts` | `aw_enrich_decide` |
| `aw_sourcing`, `aw_sourcing_keywords`, `aw_block_publisher` | `sw/sourcing-policy.php:332`, `:441`, `:525` | `manage_options` | `aw_sourcing`, `aw_sourcing_keywords_add` / `bulk-keywords`, `aw_block_*` |
| `aw_sourcing_kw` | `sw/keywords-admin.php:342` | `manage_options` | `aw_sourcing_kw_add` / `_edit_{id}` / `bulk-keywords` |
| `aw_sourcing_suggest` | `sw/keyword-suggestions.php:143` | `manage_options` | `bulk-suggestions` |
| `aw_sourcing_category_words`, `aw_sourcing_section` | `sw/sourcing-terms-admin.php:129`, `:268` | `manage_options` | per term / section |
| `aw_rules_tag_save`, `aw_rules_tag_delete`, `aw_rules_priority`, `aw_rules_field` | `sw/rules-admin.php:311`, `:372`, `:416`, `:546` | `manage_options` | per action |
| `aw_ai_keys`, `aw_ai_key_edit`, `aw_ai_keys_list` | `sw/ai-keys.php:647`, `:711`, `:753` | `manage_options` | `aw_ai_keys`, `aw_ai_key_edit_*`, `bulk-keys` (**untouched**) |
| `aw_worker_save`, `aw_worker_delete`, `aw_worker_run` | `sw/workers-admin.php:290`, `:436`, `:476` | `manage_options` | per action |

Settings API forms (Sources lists, Summary rules, Directory enrichment, switches) post to `options.php` with `settings_fields()`.

In phase C each action gets a `sourceweb/v1/…` route with the same capability and the REST nonce. The `admin-post` handlers stay for one release, so an open old tab still saves.

### 1.7 Scheduled jobs

| Hook | Schedule | Code | After |
|---|---|---|---|
| `aw_ai_check_all` | twice daily | `sw/ai-keys.php:511-518` | keep |
| `aw_ai_check_all_now`, `aw_ai_check_new` | single, on save | `sw/ai-keys.php:699-700`, `:836-837` | keep |
| `aw_ingest_watchdog` | hourly | `sw/relevance.php:525`, `:542-543` | keep; D shows last run |
| `aw_sourcing_site_meta` | hourly | `sw/source-sites.php:30`, `:186-191` | keep |
| `aw_workers_watchdog` | hourly (+ single `report`) | `sw/workers.php:212`, `:226-227`, `:306-308` | keep; D shows last run |
| `aw_sources_sync_dispatch` | single, 2 min after a Sources save | `sw/sourcing-lists.php:153-154` | keep |
| `aw_research_citation` | single, 60 s after a research item arrives | `sw/ingest.php:389` | keep |
| `wp aw workers-tick` | hPanel cron, every 15 min | `sw/workers-tick.php:139` | keep |
| From Core: `aw_cc0_run` (every 15 min), `aw_daily` → `aw_thumb_rebuild` | | `core/cc0-images.php:47-52`, `core/thumbs.php:84` | keep (Core); D shows next run |

No hook name changes, so no event is lost on deploy.

### 1.8 WP-CLI

| Command | Code |
|---|---|
| `wp aw news-titles` | `sw/news-titles.php:56` |
| `wp aw-sourcing import-dr` | `sw/source-sites.php:303` |
| `wp aw workers-tick` | `sw/workers-tick.php:139` |
| `wp aw-sourcing backup|migrate|verify|rollback --phase=1..4 [--dry-run]` | `sw/sourcing-migrate.php:141`, `:207`, `:296`, `:387`; phases in `sourcing-migrate-phase2.php`, `-rules.php`, `-retire.php` |

All kept. Listed on Kits → Help → Docs (WP-CLI section, `docs/admin-ui-spec.md` D5).

### 1.9 Site Health

`aw_sourcing_on_time` ("Sourcing ran on time"; critical when no run, recommended with problems; `sw/site-health.php:61-66`). Kept; the Dashboard's Intake tile reads the same test (`aw_sourcing_health_test()`).

### 1.10 Hooks other code relies on (all kept)

- **Filters SourceWeb applies:** `kits_sourcing_tabs` (every tab registers through it), `kits_sourcing_page_top`. In phase C they still work: each `kits_sourcing_tabs` entry not mapped in §2.3 is shown as an extra Settings group by its own callback, so nothing registered there goes missing.
- **Search Insights / InsightWeb:** `aw_si_tabs` + `aw_si_tab_sourcing` (`sw/search-insights-tab.php:21-24`), `aw_si_page_metrics()` (`sw/class-aw-sourcing-si-keywords-table.php`, `sw/source-sites.php`), `aw_search_demand_terms`, `aw_sourcing_keyword_items`, `aw_si_url()` (`docs/insightweb-plan.md` §1.7).
- **DirectoryWeb:** the field registry (`sw/rules.php:24`, `:181-199` glob of `group_aw_listing*.json`; `directoryweb_fields()` replaces it in the same commit as DirectoryWeb's move), `aw_claim_before_apply`, the change log helpers `aw_enrich_add_row` / `aw_enrich_snapshot` / `aw_enrich_install` / `aw_enrich_after_change` (to become the action `directoryweb_field_changed` that SourceWeb records), the `aw_listing_sources` filter (`docs/directory-plugin-plan.md` §1.5).
- **Kits:** `kits_plugin_screens`, `kits_status_rows` (`sw/kits-hub.php`).

### 1.11 Found while listing (recorded; fixed only where a phase says so)

1. **Strings are mostly not translatable.** 25 translation calls in about 25,000 lines; the one text domain used is `aw-sourcing` (once). Screens, notices and table headers are plain strings. Phase C writes the new screen's strings through `@wordpress/i18n`; the PHP screens that stay (AI models, legacy groups) are wrapped in phase A only where they are touched.
2. **Google key tail in the page** (§0.3).
3. **`docs/directory-plugin-plan.md` §1.5 is out of date on one line:** it says `aw_cdn_purge_post()` is called at `sw/enrichment.php:567` and defined nowhere. The call was dropped in `f1da7343` (`docs/pending.md`, CDN lifetime field); `grep` finds no `aw_cdn_purge_post` in `plugins/`. Left for the DirectoryWeb plan to correct.
4. **The brief's "worker routes keep `aw_worker`"** describes 2 of the 22 routes the workers call (§0.2).
5. **No PHPUnit, no Plugin Check, no `phpcs.xml.dist` in the plugin.** CI runs `php plugins/aw-sourcing/tests/run.php` (`.github/workflows/deploy-check.yml:88-89`, plain PHP, no WordPress: menu model, Sources model, keyword matcher, `tests/rules-tests.php`, `tests/retire-tests.php`), PHPCS security sniffs on changed files (`:57-77`, `tools/ci/phpcs-security.xml`), `tools/ci/workers_registry_check.php` and `tools/ci/smoke.sh` (`:110`).

## 2. Admin: what SourceWeb registers into the Kits Core shell

**SourceWeb only registers parts into the shell.** The shell and its look are Kits Core's (the Kits admin design system, Google Site Kit pattern). WordPress packages are plumbing only: `@wordpress/data`, `api-fetch`, `i18n`, `icons`, `element`. Any part the design system lacks is a request to Kits Core (§2.4); SourceWeb does not build its own.

### 2.0 Menu and addresses

SourceWeb → **Dashboard · To review · Settings**. The slugs stay, so most old links land in the right place:

| Item | Slug | Cap |
|---|---|---|
| Dashboard | `aw-sourcing-menu` (today's Overview) | `edit_others_posts` |
| To review | `aw-review` (count bubble kept, on the menu and the item) | `edit_others_posts` |
| Settings | `aw-sourcing` (today's Sources page; `&tab=` becomes a group, `#sources`, `#keywords` …) | `manage_options` |

The menu keeps its place (after Classification, before Mail, `sw/admin-menu.php:122-137`) and the forced hook prefix `sourcing` (`:65`).

### 2.1 Dashboard tiles

Each tile: a number, a sub-line, the source it names, and a cached REST route with a refresh. Tiles from another plugin hide themselves, or say "{plugin} not active", when that plugin is missing.

| Tile | Number and sub-line | Source named | Route (`GET /sourceweb/v1/…`) |
|---|---|---|---|
| To review | items waiting (`aw_review_count()`); by type (News, Library, Opportunity, Press, Research, Social) and the oldest one's age | WordPress (pending and held drafts, `sw/review-queue.php:40-45`) | `stats/review` |
| Listing details | proposals waiting in `aw_enrich`; applied in the last 7 days, auto-applied share | SourceWeb enrichment (`aw_enrich`) | `stats/enrichment` |
| Workers | healthy of 19; late or failed workflows by name, next due | SourceWeb workers (run reports from `POST aw/v1/worker-run`, `aw_workers_problems()`) | `stats/workers` |
| Today's intake | items that arrived today (published / held) and refused today; last report time, "Sourcing ran on time" or the problem | workers' reports (`aw_ingest_heartbeat`, `aw_relevance_rejected`, Site Health test §1.9) | `stats/intake` |
| Picture pipeline | items with their own picture of the published total; CC0 assigned, still placard, no match after the tries; Openverse requests today of 300 and next run | Animal Writes Core (`aw_cc0_counts()`, `aw_ov_quota`, `aw_cc0_run`), through the filter `aw_sourcing_dashboard_tiles`; hidden without it | `stats/pictures` |
| Thumbnails | pictures used by more than one item (0 is green) | Animal Writes Core (`aw_thumb_duplicates()`, same filter) | `stats/thumbnails` |
| AI models | free models that work of those listed; refused today | SourceWeb AI models: status column only (`aw_sourcing_overview_ai()`, `sw/dashboard.php:18`). No key, no key fragment | `stats/ai` |
| Google Maps spend | this month's live and check spend against the monthly cap | SourceWeb (`aw_enrich_ledger`, `aw_enrich_live_cap`) | `stats/enrichment` |

**Dashboard sections, under the tiles** (each a full paged list where it has rows):
1. **Worker runs:** today's Overview table (worker, last report, result, next due) with a link to Settings → Workers.
2. **Picture pipeline:** the 8 counts and the last 15 log lines (from Core, as on Check site today).
3. **Reports → Rejected, Relay log, Change log:** the three report tabs, read-only, with their search (today's GET forms).
4. **What SourceWeb does:** today's Overview feature table (group, what it does, where it lives, status), collapsed.

### 2.2 To review (the working list)

**Rows:** each item that came in automatically and waits for a person (`AW_REVIEW_TYPES`, `sw/review-queue.php:18-25`: News, Library, Opportunity, Press, Social post while that module is on, research papers only).
**Columns:** date (first, `meta-row` rule), title, type, source (publisher with its icon), why it was held, keyword hits.
**Views:** by type as today, plus **Listing details** (below).
**Filters:** type, source, why held, date range. **Search:** title, source.
**Row actions:** Edit, Source, Publish, Reject (as today). **Bulk:** Publish, Reject; "Publish all N" with a confirm that names N.

**Enrichment review becomes the view "Listing details", not its own list.** Reasons: both lists are "waiting for a person", both use `edit_others_posts`, and the menu then has one count. Inside the view:
- One row per listing with proposals; opening it shows the shared **field-by-field review part** (DirectoryWeb's request, `docs/directory-plugin-plan.md` §2.4.1): current value beside proposed value, source and confidence per field, a tick per field, Approve ticked / Reject, **Undo** per applied field.
- Status filter: Waiting, Applied, Rejected, Failed, Undone, All (today's views, `sw/enrichment-admin.php:314`).
- Bulk: Approve ticked, Reject, as today.
- Auto-applied high-confidence values for empty fields keep showing as Applied, with Undo.

**REST** (`sourceweb/v1/…`, every write checks the REST nonce):
- `GET review`, `POST review/{id}/decide` (`publish|reject`), `POST review/publish-all` (`count` must match) — `edit_others_posts`.
- `GET enrichment`, `GET enrichment/{listing}`, `POST enrichment/{listing}/decide` (`fields[]`, `status`), `POST enrichment/{id}/undo` — `edit_others_posts`.
- Each calls the same functions the `admin-post` handlers call today, so the result is the same.

### 2.3 Settings

#### 2.3.1 Connected services (first, each a status row)

| Service | Status shows | Set here |
|---|---|---|
| Workers (GitHub Actions) | "Dispatch token set / not set" (`AW_GITHUB_DISPATCH_TOKEN` in wp-config, `core/cron-health.php:188`), last dispatch, 19 workflows, late count | Nothing (wp-config only); link to Workers group |
| Workers' sign-in | the workers' user and its application password: exists / missing (never the password) | Link to the user's profile |
| Google Maps (Places) | key **set / not set**; this month's spend against the cap; live block on or off | Key (write-only field: a new value replaces, empty keeps), monthly cap, on/off. Free monthly quota only (`AW_GLIVE_COST` 0.025 per live call, `sw/enrichment-front.php:19`) |
| Wikidata | on / off | on / off |
| OpenStreetMap | off (owner decides, `docs/pending.md` Sourcing) | on / off |
| NGO Darpan, AWBI, India Post, Instagram / Facebook, the organisation's website, claims | on / off each | on / off (the 11 rows of `aw_enrich_sources()`) |
| Openverse (pictures) | requests today of 300, paused or not | read-only (Core's) |
| Relay | hosts allowed (5 by default, `sw/relay.php:22`), rate 30 per host per 10 min, last request | link to Sources → Relay view |
| InsightWeb / Search Insights | active, Search Console figures available (`aw_sourcing_si_available()`) | link |
| DirectoryWeb | active, field registry found (`directoryweb_fields()` or the SCF files) | link |
| AI providers | free models listed, working | link to AI models |

Keys and tokens never reach the browser: the REST answer carries `set: true|false`, never a value or a tail.

#### 2.3.2 Groups (after the services)

1. **Sources:** today's Sources list and edit screen with all 8 views, Feeds inside each source, press sites, relay switch.
2. **Keywords:** the keyword table's 8 views, suggestions, headline test, and the view **Searches** (Search Insights → Sourcing keywords today: items per Google News search over 30 days with their pages' Search Console figures from `aw_si_page_metrics()`).
3. **Rules:** Section rules, Summary rules, Directory enrichment (sources, hosts, trust order of 8, the 42 field rules).
4. **AI models:** §2.3.3.
5. **Social posts:** "Social posts type" (`aw_social_on`) and "Show on pages" (`aw_social_show`), off on live; the note that the block needs the owner's OK of its design and label first (`core/social-posts.php:10`).
6. **Workers:** "Workers use these lists" switch (today's Settings tab), the 19 workflows with schedule, group, AI keys attached (names only), last run, Run now, add / edit / delete.

#### 2.3.3 AI models (without touching AI keys)

The group is drawn by today's callback `aw_ai_keys_screen` (`sw/ai-keys.php:188`) inside the shell, with its own forms and nonces. `ai-keys.php` and `class-aw-sourcing-ai-keys-table.php` are **not edited** in any phase of this plan. The Dashboard's AI tile reads only the status of the rows. Any later change to that screen is the owner's.

#### 2.3.4 How settings are saved

- Each option keeps its name and shape (§1.3). Options SourceWeb owns get `register_setting( …, array( 'show_in_rest' => array( 'schema' => … ) ) )` with their current sanitize callback; secret ones (`aw_enrich_google_key`) stay `show_in_rest` false and save through `POST sourceweb/v1/settings/google-key`.
- Options other plugins own (`aw_sources`, `aw_summary_rules`, `aw_publishers`, `aw_relevance`, `aw_social_*`) are saved through their owner's sanitize callback, as today.
- Every change still writes the Change log (`kits_sourcing_log`).

### 2.4 Requests to Kits Core (parts the design system may lack)

1. **Field-by-field review** (DirectoryWeb's request 1): shared with Listing details.
2. **Views with their own columns** inside one working list (To review items vs Listing details).
3. **A legacy section slot:** a Settings group drawn by a PHP callback with its own forms (AI models, any `kits_sourcing_tabs` entry not yet moved).
4. **Full paged read-only lists inside a Dashboard section** with a search (Rejected, Relay log, Change log; InsightWeb's request 3).
5. **A tile filled by another plugin through a filter** (Picture pipeline and Thumbnails from Core), and a tile that hides itself when its source is missing (DirectoryWeb's request 6).
6. **A write-only secret field** that shows "Set / Not set" and never sends the value back.
7. **A confirm dialog that names a count** ("Publish all 14").
8. **A count bubble on a menu item and on the working list's view.**

### 2.5 New REST routes (summary)

Under `sourceweb/v1/`: `stats/{review,enrichment,workers,intake,pictures,thumbnails,ai}` (GET, `edit_others_posts`; workers and AI tiles `manage_options`); `review…`, `enrichment…` (§2.2); `settings/{group}` (GET/POST, `manage_options`); `settings/google-key` (POST only); `workers/{file}/run` (POST, `manage_options`, same as `aw_worker_run`); `reports/{rejected,relay-log,change-log}` (GET, `manage_options`). Every route has a `permission_callback` with its capability; no `__return_true`. Every write checks the `wp_rest` nonce.

## 3. Migration

### 3.1 Old addresses

Each redirects with a 302 on `admin_init`, kept for two releases, query string carried over (as `aw_sourcing_moved_redirect()` does today, `sw/admin-menu.php:175-195`):

| Old | New |
|---|---|
| `admin.php?page=aw-sourcing-menu` (Overview) | same slug: Dashboard |
| `admin.php?page=aw-review` | same: To review |
| `admin.php?page=aw-enrich-review[&status=…]` | `admin.php?page=aw-review&view=listing-details[&status=…]` |
| `admin.php?page=aw-sourcing` (Sources) | `admin.php?page=aw-sourcing#sources` |
| `…&tab=keywords|rules|ai|workers` (+ `&part=`, `&edit=`) | `admin.php?page=aw-sourcing#keywords|rules/{part}|ai|workers` (edit screens keep their query) |
| `…&tab=rejected|relay-log|log` | `admin.php?page=aw-sourcing-menu#reports/{tab}` |
| `…&tab=settings` | `admin.php?page=aw-sourcing#workers` |
| `…&tab=press|categories|sections|summary|enrich` | as today (Keywords → Press, Posts → Categories, Rules parts) |
| `options-general.php?page=aw-sourcing`, `edit.php?…&page=aw-enrich-review` | as today, then on to the new place |
| Kits → Modules (`aw-modules`) | `admin.php?page=aw-sourcing#social-posts` (`docs/admin-ui-spec.md` E1) |
| Check site thumbnails and pictures | `admin.php?page=aw-sourcing-menu#pictures` (when Check site goes) |
| Search Insights → Sourcing keywords | `admin.php?page=aw-sourcing#keywords/searches` (when InsightWeb's phase 2 drops the tab) |

`tools/ci/settings_parts_baseline.txt` is updated in the same change that moves a screen.

### 3.2 No setting lost

- No option, table, route, cron hook, CLI command or capability changes name (§1.3–1.9).
- Before each ship: list every option in §1.3 from live (read-only, `wp option get … --format=json` over SSH) and the `aw_enrich` row count and status counts; after: the same list must match byte for byte.
- Saved screen options (per page, hidden columns) are user meta keyed on `sourcing_page_aw-sourcing`; the new screens read them as their defaults.
- A test lists every Settings field in §2.3 and fails if an option of §1.3 has no place.
- The `admin-post` handlers stay for one release after phase C; they go in phase D with the owner's yes.

## 4. The Kits plugin standard, per phase

| Check | Plan |
|---|---|
| **Header and version** | Phase A: MailWeb-style PHPDoc header (§1.1), `Version` = `AW_SOURCING_VERSION` = `readme.txt` `Stable tag`, one value bumped together each release (`wp-plugin-release`). `aw_enrich_db` stays independent |
| **readme.txt, changelog, `.pot`** | Phase A: `readme.txt` like `plugins/mailweb/readme.txt` (description, the worker routes and their permission, the external services of §2.3.1 with what is sent, changelog from 0.1.0). `languages/aw-sourcing.pot` by `wp i18n make-pot` (`--exclude=tests`), regenerated each release; `load_plugin_textdomain` on `init` |
| **Capability and nonce on every route** | Already true for the 20 admin actions and 23 `aw/v1` routes (§1.5–1.6). Worker routes keep their checks unchanged (§0.2). New `sourceweb/v1` routes: capability per route + `wp_rest` nonce (§2.5). Audited with `wp-plugin-audit` each phase |
| **Escaping and SQL** | PHPCS security sniffs on every changed file (today's Deploy check). Phase A adds `plugins/aw-sourcing/phpcs.xml.dist` (WordPress-Extra, text domain `aw-sourcing`, prefixes `aw_`, `AW_`, `kits_sourcing`), report mode first so old files do not fail the build |
| **Tests** | Keep `tests/run.php` (pure PHP, no WordPress) in the Deploy check and **extend it** rather than add a WordPress test suite (no local WP/MySQL, owner rule): the redirect map (old address → new), the Settings field-to-option checklist (§3.2), the tile payloads built from fixtures (no key in any payload), the permission map (every `sourceweb/v1` route has a capability, none `__return_true`; every `aw/v1` route keeps today's check). `tools/ci/smoke.sh` gains the new screens' addresses (200 for a signed-in check, 302 for each old address). PHPUnit and Plugin Check: **not added** — they need a WordPress install in CI; listed as a later option for the owner |
| **JS build** | Phase C only: one script per screen (Dashboard, To review, Settings) built with `@wordpress/scripts` and its `.asset.php`; it only registers SourceWeb's parts with the shell; no CSS of its own; `wp_set_script_translations( …, 'aw-sourcing' )`; size within the shell's budget |
| **Background jobs** | No job added or renamed (§1.7). The Dashboard reads job state, it never runs a job on load |

## 5. Icon

The SourceWeb icon (a funnel to a yellow dot) is on `wordpress-dev` (`d5e2bcbb`): the source set is `docs/brand/plugin-icons/sourceweb.svg`, `sourceweb-256.png` and `sourceweb-128.png`, and the plugin's own copy, named the way WordPress expects, is `plugins/aw-sourcing/assets/icon.svg`, `icon-256x256.png` and `icon-128x128.png`. The plan uses the plugin's copy in three places:
- **Menu icon:** `assets/icon.svg` as an SVG data URI in the menu's grey, in place of `dashicons-rss` (`sw/admin-menu.php:64`). Phase A.
- **App header:** the shell shows it on SourceWeb's screens (`docs/brand/plugin-icons/README.md`). Phase C.
- **Kits → Plugins and Kits Home → Plugin status:** the row SourceWeb adds through `kits_plugin_screens` (`sw/kits-hub.php`). Phase A.

`readme.txt` (phase A) needs no icon line; the files in `assets/` are already the WordPress names.

## 6. Phases

Each phase ships on the owner's yes, through the Deploy check, one at a time, never while a FluentCRM email is sending (the deploy gate waits). Bigger phases go each on its own branch and PR into `wordpress-dev`, with the review gate before merge.

| Phase | What | Size | Risk | Proof |
|---|---|---|---|---|
| **A. Standard housekeeping** (only the icon changes) | The icon on the menu and the Kits → Plugins row (§5), header, `AW_SOURCING_VERSION`, `readme.txt` + changelog, `languages/aw-sourcing.pot`, `phpcs.xml.dist` (report mode), `tests/run.php` checks for the permission map | Small: 4 new files, header lines | Low: no behaviour change | The menu and Kits → Plugins show the SourceWeb icon; Plugins screen shows the same name and version; every SourceWeb screen loads; one workers run lands (ingest + heartbeat); PHP log has no new line; Deploy check green |
| **B. Small fixes found here** | Google key shows "Set / Not set" (§0.3); Social posts switches drawn on SourceWeb → Settings (today's screens), Kits → Modules redirects | Small | Low | Saving Directory enrichment with an empty key keeps the key; page source has no key tail; `aw_social_on` / `aw_social_show` unchanged after a save; old Modules address lands on the new group |
| **C. The shell** (waits for Kits Core's shell, piloted on InsightWeb, then RankWeb; SourceWeb is third) | §2 in full: Dashboard tiles and sections, To review with Listing details, Settings with services and groups, the `sourceweb/v1` routes, redirects (§3.1), §2.4 parts answered by Kits Core first, `admin-post` handlers kept | Large: 3 screens, about 20 routes, 1 build | Medium: admin only, nothing on the front end; held by same slugs, kept handlers and the option compare (§3.2) | §3.2 option and `aw_enrich` compare equal; every old address lands on the right group; To review count equal before and after; one Publish, one Reject, one Approve and one Undo on Listing details give the same rows as the old screens; a workers run lands; no key or tail in any REST answer (checked in the browser's network list) |
| **D. Clean-up** (one release after C) | Remove the old tab screens' menu items and the `admin-post` handlers that have a REST route; Check site's thumbnail and picture rows go when Kits Home exists | Small | Low | Smoke list green; old addresses still redirect |

Not part of any phase: DirectoryWeb's switch to `directoryweb_fields()` and `directoryweb_field_changed` lands in DirectoryWeb's phase 1 commit (`docs/directory-plugin-plan.md` §1.5); InsightWeb's tab removal in its phase 2.

## 7. Not in this plan (recorded, not done)

- The aw→kits rename of options, routes, hooks and the folder (deferred by the owner).
- AI keys and `ai-keys.php`: never touched (owner only).
- The workers repo, including the GDELT replacement suggestion (`docs/pending.md`).
- Moving `core/social-posts.php`, `core/cc0-images.php` or `core/thumbs.php` into SourceWeb (§0.4; they stay Core's).
- PHPUnit and Plugin Check in CI (§4).
- Correcting `docs/directory-plugin-plan.md` §1.5 on `aw_cdn_purge_post()` (§1.11.3).
