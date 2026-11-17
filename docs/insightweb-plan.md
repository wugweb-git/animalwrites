# InsightWeb: Search Insights out of RankWeb (plan)

11 November 2026. Owner's decision of 11 Nov ("9. Decision log": "Search Insights leaves RankWeb; plugin names"). **Plan only: no code until the owner approves.** Built on `docs/seo/search-insights-plan.md`, the Search Insights entries in `docs/pending.md`, `docs/plugin-map.md` and the code on `wordpress-dev` at `b0512a4e` (live `wordpress` is `637e9e64`; the two commits between touch only the joined CSS files, nothing here).

## The split in one line

- **InsightWeb measures:** Search Console, GA4, Clarity, PageSpeed, Bing, keywords, Rising, quick wins, conflicts, links, server and crawler checks.
- **RankWeb acts:** title and description rules, schema, FAQ, canonicals, breadcrumbs, sitemap additions, noindex rules.
- **InsightWeb never writes SEO settings.** Search Insights writes none today and keeps it that way. The focus-keyword routes `aw/v1/seo-items` and `aw/v1/seo-focus` stay in RankWeb.

## Fixed for this plan (owner)

- **InsightWeb is the extraction only (owner, 11 Nov).** The Search Insights module and its dependencies move out of RankWeb into their own plugin, with no redesign and no new features. This plan covers that step (phase 1) only.
- **The same screen, slug, names, keys, tables, options, cron jobs and routes.** The screen stays at `admin.php?page=aw-search-insights` with the same tabs. Every `aw_si_*` name, the `aw_si_tabs` filter and the `aw_si_page_metrics()` function stay as they are. The aw → kits rename is deferred (11 Nov).
- **A Kits plugin that requires Kits Core.** It also needs Animal Writes Core and Rank Math, as Search Insights does today: the keyword table `wp_aw_si_keywords` and its helpers (`aw_si_table()`, `aw_si_norm()`, `aw_kw_install()`) are Core's (owner's decision 3, 25 Oct), and today the module loads only when Rank Math is active.
- **No GA4 or GTM change.** No new Search Console calls: the same calls as today, once per calendar day.
- **AI keys are not touched.** They are not on the Keys screen (they live in SourceWeb's `ai-keys.php`) and nothing here reads or moves them.

## 1. Inventory

Everything below is in `plugins/aw-seo-extras/` (RankWeb 1.9.3) unless a path says otherwise. "Moves" means it goes to InsightWeb with its name and behaviour unchanged.

### 1.1 Files

| Files | What they do | Where |
|---|---|---|
| `includes/search-insights.php` | Loader: needs Animal Writes Core, then loads 37 files | Moves (becomes InsightWeb's loader) |
| `search-insights/schema.php`, `runs.php`, `sitekit.php`, `keywords.php`, `gsc.php`, `compute.php`, `rollup.php`, `cron.php`, `sources.php` | Tables, run log, Site Kit calls, keyword roles, Search Console by day, priorities, quick wins, conflicts, rollup, schedules, redirects counted as their target | Moves |
| `ga4.php`, `psi.php`, `clarity.php`, `bing.php`, `bing-crawl.php`, `gsc-types.php`, `alerts.php`, `sitesearch.php`, `visitors.php` | GA4, PageSpeed, Clarity, Bing Webmaster, Bing crawl and keyword volumes, Discover / Google News / URL Inspection, Google Alerts, searches on the site, visitor countries | Moves |
| `rising.php`, `links.php`, `links-seed.php`, `data/links-2026-11-02.php`, `pages.php`, `checklist.php`, `server.php`, `crawlcheck.php`, `selfcheck.php` | Rising, Links, Pages, Check list (bookmarklet live check), Server, crawler access, the server's own speed and AI checks | Moves |
| `imports.php`, `tool-imports.php`, `snapshot.php`, `searches.php`, `dryrun.php`, `rankmath.php`, `referrer-icons.php` | Imports and paste forms, Ubersuggest / vidIQ / OpenSEO seeds, the monthly snapshot CSV, content gaps, the dry run, Rank Math reads, referrer icons | Moves |
| `admin.php` and the 7 `class-aw-si-*-table.php` list tables | The tabbed screen | Moves as it is |
| `health.php` | Site Health test `aw_search_insights_fresh` | Moves |
| `dashboard.php` | The SEO box on the WordPress Dashboard, from stored rows (functions named `aw_seo_dash_*`) | Moves, names kept |
| `cli.php` | `wp aw-si …` | Moves, command name kept |
| `focus-keywords.php`, `keyphrase-model.php` | `aw/v1/seo-items`, `aw/v1/seo-focus` and the one-page-per-keyphrase rule | **Stays in RankWeb** |
| `analysis.php` | `aw/v1/seo-score-items`, `aw/v1/seo-scores`: Rank Math content analysis | Stays |
| `faq.php`, `faq-overview.php`, `titles.php`, `canonical.php`, `schema.php`, `rank-math-schema.php`, `breadcrumbs.php`, `sitemap.php`, `robots.php`, `filtered-views.php`, `programmatic.php`, `redirects.php`, `opengraph.php`, `llms.php`, `markdown.php`, `health.php` (endpoints watchdog), `helpers.php`, `overview.php`, `settings.php`, `kits-hub.php` | SEO work on the site | Stay |

RankWeb files that touch Search Insights today, and what happens to each:

| Where | What | Change |
|---|---|---|
| `aw-seo-extras.php` loader | `'search-insights'` in the file list | Replaced by the bridge (3.2) |
| `aw-seo-extras.php` deactivation hook | Clears `aw_si_cron_hooks()` | Removed: RankWeb must never clear InsightWeb's jobs |
| `settings.php` modules list | Module `search-insights`: switch, 7 numbers (`AW_SI_HIGH_TRENDS`, `AW_SI_HIGH_IMPR`, `AW_SI_QUICK_MIN`, `AW_SI_MOVERS`, `AW_SI_PSI_FLAG`, `AW_SI_RM_LOW`, `AW_SI_KEEP_DAYS`), hidden tab | Removed from RankWeb; InsightWeb defines the same constants from the same stored values (1.3) |
| `settings.php` | Redirect of `aw-seo-settings&tab=search-insights` to Search Insights → Settings; `aw_seo_settings_embed()` | Redirect kept; the embed is no longer called |
| `overview.php` | Search Insights module row, link and health status | Kept, pointing at the same screen; reads "Search Insights is now its own plugin, InsightWeb." (approved 11 Nov) |
| `focus-keywords.php` | Refuses a keyphrase whose Search Insights owner is another page (reads `wp_aw_si_kw_pages`) | Kept: RankWeb reads, guarded by `function_exists( 'aw_si_ready' )` |
| `kits-hub.php` | Kits → Plugins row "SEO rules, FAQ, schema and Search Insights." | "What Rank Math doesn't do or keeps for Premium." (approved 11 Nov); InsightWeb adds its own row |
| Plugin header | "SEO rules, FAQ, schema and Search Insights for Kits sites." | "Everything Rank Math doesn't do or keeps for Premium, for Kits sites." (approved 11 Nov) |

Search Insights calls three RankWeb functions; each gets a guard or its own copy in InsightWeb:

| Function | Used by | Change |
|---|---|---|
| `aw_seo_api_allowed()` | REST routes in `rising.php`, `crawlcheck.php` | InsightWeb's own `aw_si_api_allowed()`, same rule (administrator or `aw_worker`) |
| `aw_seo_settings_embed()` | Settings tab numbers | InsightWeb prints the same 7 fields in the same place, saving to the same stored key (1.3) |
| `aw_seo_kp_replay()` | `wp aw-si` check of the keyphrase rule (`cli.php`) | Called only when RankWeb is active; otherwise the command says RankWeb is needed |

### 1.2 Tables

All move; none is renamed, copied or emptied. Built with dbDelta and a version option, as today.

| Table | Owner | Version option |
|---|---|---|
| `wp_aw_si_keywords`, `wp_aw_si_kw_map` | **Animal Writes Core** (`keyword-table.php`); InsightWeb and SourceWeb each write their own roles | Core's |
| `wp_aw_si_kw_pages`, `wp_aw_si_obs`, `wp_aw_si_rising`, `wp_aw_si_links` | InsightWeb | `aw_si_db` = `2` |
| `wp_aw_si_visits` | InsightWeb | `aw_si_vis_db` = `1` |
| `wp_rank_math_analytics_gsc`, `wp_rank_math_redirections`, post meta `rank_math_focus_keyword`, `rank_math_seo_score` | Rank Math | **Read only** (backup Search Console rows, old addresses, focus keyphrases for the roles, SEO scores on Pages) |

Deactivating or deleting InsightWeb keeps every table and option, as today. Only Settings → "Delete Search Insights data" (nonce plus typed DELETE) or `wp aw-si delete-data --yes` drops them.

### 1.3 Options

All keep their names. InsightWeb reads and writes them as Search Insights does now.

- **Run log and state:** `aw_si_runs`, `aw_si_db`, `aw_si_vis_db`, `aw_si_installed`, `aw_si_seeded`, `aw_si_backfill`, `aw_si_off`, `aw_si_live_seed_run`, `aw_si_rising_since`, `aw_si_geo_updated`, `aw_si_crawl_inside`, `aw_si_crawl_outside`, `aw_si_crawl_mailed`, `aw_si_srv_checks`, `aw_si_clarity_calls`, `aw_si_clarity_retried`, `aw_si_bing_cursor`, `aw_si_links_seeded_20261102`, queues `aw_si_psi_queue`, `aw_si_inspect_queue`.
- **Owner's choices:** `aw_si_checklist`, `aw_si_gdelt_on`, `aw_si_tool_seeds` ("Seeds for keyword tools", 25 searches saved 10 Nov), `aw_si_live_token`. Clarity and Bing have no on/off option: each is on when its key is set (`aw_si_clarity_on()`, `aw_si_bing_on()`).
- **The 7 numbers:** stored in RankWeb's option `aw_seo_settings`, under the key `search-insights` (only values that differ from the default). They stay there. InsightWeb defines the same `AW_SI_*` constants from `aw_seo_settings['search-insights']` with the same defaults, and its Settings tab writes only that key. RankWeb's save starts from the whole stored array (`aw_seo_saved()`), so it never drops the key once the module is gone from its list.
- **The module switch:** `aw_seo_settings['search-insights']['on']`. After the move, InsightWeb being active is the switch. The stored value on live is read once, read only, before the ship: if it is off, the bridge (3.2) does not load the code and InsightWeb is left inactive, so nothing that is off today starts running.
- **Screen options (user meta):** `aw_si_keywords_per_page`, `aw_si_pages_per_page`, `aw_si_rising_per_page`, `aw_si_links_per_page`, `aw_si_checklist_per_page`, `aw_si_clarity_per_page`. Kept, with the screen id (2).
- **Keys:** stored in Core's option `aw_keys` and read with `aw_key()`; a `wp-config.php` constant of the same name wins. Not moved (1.6).

### 1.4 Scheduled jobs (WP-Cron)

All keep their hook names, times and handlers.

| Hook | When (IST) | What |
|---|---|---|
| `aw_si_clarity` (+ `aw_si_clarity_retry` 3 h later after a failure) | Daily 06:30 | Clarity: 2 of its 10 free calls |
| `aw_si_daily` | Daily 07:10 | Search Console (the one call a day per dimension set), roles, priorities, topics; also, at priorities 20–22, the server log day (`aw_si_srv_daily`), visitor countries (`aw_si_vis_daily`) and the self-check (`aw_si_chk_daily`) |
| `aw_si_gsc_types` | Daily 07:15 | Discover and Google News |
| `aw_si_ga4` | Daily 07:20 | GA4 through Site Kit (read only) |
| `aw_si_inspect` → `aw_si_inspect_more` | Daily 07:40, steps every 5 min | URL Inspection, up to 2,000 pages a day |
| `aw_si_alerts` | Daily 08:10 | Google Alerts feeds |
| `aw_si_rollup` | Daily 23:30 | Daily rows older than 90 days become monthly rows |
| `aw_si_psi` → `aw_si_psi_more` | Sundays 06:00, steps every 5 min | PageSpeed |
| `aw_si_bing_crawl` | Mondays 06:45 | Bing crawl statistics |
| `aw_si_crawl` | Mondays 08:45 | Crawler access check |
| `aw_si_bing` | Monthly, booked again after each run | Bing Webmaster links and searches |
| `aw_si_bing_kw` | 1st of the month 07:00, booked again after each run | Bing keyword volumes |
| `aw_si_geo_refresh` | One-off, 2 min after it is needed | Country database download |
| `aw_si_srv_speed`, `aw_si_srv_ai` | One-off, from the self-check | Server speed and AI-crawler checks |
| `aw_si_rising_spikes` | (removed job, kept only to clear it) | — |

That is 15 regular jobs plus 3 short one-off events (`aw_si_geo_refresh`, `aw_si_srv_speed`, `aw_si_srv_ai`). The repeating jobs are booked on `init` only when missing (`wp_next_scheduled()`), and none is booked with arguments (only the one-off `aw_si_srv_speed` carries one, `mobile` or `desktop`). So a second loader can never book a job twice; the risk during the switch is a job with **no handler** (3.2).

**Found on the way, corrected 11 Nov while building phase 1:** the earlier note here said deactivation clears only 8 of the 15 regular jobs. That was wrong. `aw_si_cron_hooks()` starts with 8, but the files that add the other jobs append them through the filter `aw_si_cron_hooks` (`gsc-types.php`, `alerts.php`, `crawlcheck.php`, `bing-crawl.php`, `visitors.php`, `selfcheck.php`, `rising.php`), so every regular job is cleared. The only gap: `aw_si_srv_speed` is booked with an argument, and `wp_clear_scheduled_hook()` without arguments leaves those two one-off events (they run once, minutes after the daily run). Recorded, not fixed in this step.

### 1.5 REST routes, admin actions, CLI, Site Health

| Kind | Name | Who | Where |
|---|---|---|---|
| REST | `GET/POST aw/v1/search-insights` | Workers' Rising job (`aw_worker`) | Moves, unchanged |
| REST | `GET/POST aw/v1/search-insights/crawl` | Workers' outside crawler check | Moves, unchanged |
| REST | `GET aw/v1/seo-items`, `POST aw/v1/seo-focus` | Workers' focus-keyword job | **Stays in RankWeb** |
| REST | `aw/v1/seo-score-items`, `aw/v1/seo-scores` | Workers | Stays |
| admin-post | `aw_si_run`, `aw_si_delete`, `aw_si_download`, `aw_si_import`, `aw_si_note`, `aw_si_rising_paste`, `aw_si_gdelt`, `aw_si_bing_kw`, `aw_si_tool_seeds` | Settings and tab forms (nonce + `aw_si_cap()`) | Moves |
| AJAX | `wp_ajax_aw_si_live_save` | The bookmarklet live check (nonce + token, origins from `aw_si_live_origins`) | Moves |
| CLI | `wp aw-si` (and the aliases `wp aw-si-rising`, `wp aw-si-crawl`) | Owner, links reminder | Moves |
| Site Health | `aw_search_insights_fresh`, `aw_search_insights_crawl` | — | Move, same ids |
| Dashboard widget | SEO box | — | Moves |

### 1.6 The Keys screen

Kits → Keys (`admin.php?page=aw-keys`, Animal Writes Core `includes/keys.php`) lists only what plugins add with the `aw_keys` filter. Today only Search Insights adds anything: `AW_CLARITY_TOKEN`, `AW_BING_WEBMASTER_KEY`, `AW_PSI_KEY` (optional) in `runs.php`, and `AW_GOOGLE_ALERTS_FEED_1`, `AW_GOOGLE_ALERTS_FEED_2` in `alerts.php`. Both filters move with their files, so Kits → Keys looks the same and the values stay where they are. AI keys are not on this screen and are not touched.

### 1.7 SourceWeb's hooks (must keep working)

SourceWeb (`plugins/aw-sourcing`) reads Search Insights in five places. None needs a change, since every name stays and every call is guarded with `function_exists()`.

| SourceWeb | Reads | Note |
|---|---|---|
| `search-insights-tab.php` | Filter `aw_si_tabs` (adds "Sourcing keywords"), action `aw_si_tab_sourcing` | InsightWeb applies both, unchanged |
| `class-aw-sourcing-si-keywords-table.php`, `source-sites.php` | `aw_si_page_metrics( $paths, $days )` (28 and 90 days) | Function moves with `gsc.php`, same signature |
| `search-insights-tab.php` | `aw_sourcing_si_available()` = `aw_si_ready()` + `aw_si_table()` | `aw_si_ready()` moves; `aw_si_table()` is Core's |
| `keyword-suggestions.php` | Filter `aw_search_demand_terms` | SourceWeb fills it from the keyword table; unchanged |
| `admin-menu.php`, the tables | `aw_si_url()`, `aw_si_cap()`, `aw_si_runs()` | Move; same addresses |

Search Insights reads SourceWeb too (filter `aw_sourcing_keyword_items`, constant `AW_SRC_CAP` for `aw_si_cap()`); unchanged.

### 1.8 FAQ figures

FAQ coverage (`aw_faq_index()`, the hourly transient `aw_faq_index`, `aw_faq_off_types`, the RankWeb Overview's FAQ status and RankWeb → FAQ) is RankWeb's and stays. Search Insights reads none of it. The only FAQ link is a suggestion: `compute.php` suggests the action "FAQ answer" for a question with no page, and that stays as it is.

### 1.9 Rank Math reads

All read only, all move with InsightWeb: focus keyphrases for the roles (`keywords.php`), redirections and old slugs (`sources.php`), SEO scores and the Analytics Search Console rows as a backup (`rankmath.php`). As today, the code loads only when Rank Math is active.

### 1.10 Screens (RankWeb → Search Insights, `admin.php?page=aw-search-insights`)

All tabs move as they are: Overview (17 boxes), Keywords (views All, P1, P2, P3, Gaps, Conflicts, Quick wins, Tracked not ranking, Entities; 18 columns), Rising, Pages, Clarity, Check list, Sourcing keywords (SourceWeb's, through `aw_si_tabs`), Links, Server, Settings.

## 2. Admin: the same screen, a new owner

InsightWeb registers the screen as a submenu of RankWeb's menu (`AW_SEO_MENU`), so the address, the slug `aw-search-insights`, the screen id (`aw-seo-plus_page_aw-search-insights`), saved screen options and box order all stay. RankWeb is never switched off (it is tied to the theme and Rank Math; owner, 11 Nov), so there is no menu of its own and no new admin UI. Nothing on the screen changes. Visible elsewhere: a new InsightWeb row on the Plugins screen and on Kits → Plugins, and the three RankWeb wording lines in 1.1.

## 3. Moving it out

### 3.1 Plugin shape

- Folder `plugins/insightweb/`, main file `insightweb.php`, moved files under `includes/` with `git mv` so history follows them.
- Header, as MailWeb's (the standard for every Kits plugin, owner, 11 Nov): a PHPDoc block with `@package`, `@author Wugweb`, `@copyright 2026 Wugweb`, `@license GPL-2.0-or-later` and the `@wordpress-plugin` marker, then `Plugin Name: InsightWeb`, `Description: Search Console, GA4, Clarity, PageSpeed, Bing, keywords and rising terms for Kits sites.`, `Version: 1.0.0`, `Requires at least: 6.4`, `Requires PHP: 8.0`, `Author: Wugweb`, `Author URI`, `License`, `License URI`, `Update URI: https://wugweb.com/plugins/insightweb/`, `Text Domain: aw-seo-extras`, `Domain Path: /languages`.
- `INSIGHTWEB_VERSION` = `1.0.0`, equal to the header (beside `INSIGHTWEB_FILE` and `INSIGHTWEB_DIR`).
- **Text domain stays `aw-seo-extras`** for the moved strings, like every other name; it changes with the deferred rename.
- **No `Requires Plugins: kits-core` header.** On live, Kits Core is loaded by Animal Writes Core (`animal-writes-core.php` requires it first), not always active as its own plugin, so WordPress would refuse to activate InsightWeb. The requirement is checked in code on `plugins_loaded`: without Kits Core, Animal Writes Core or Rank Math, InsightWeb shows one notice on the Plugins screen and loads nothing else (approved 11 Nov).
- Kits Core hooks: a Kits → Plugins row and a System Status row through `kits_plugin_screens` and `kits_status_rows`, as RankWeb does.
- PHPCS (WordPress-Extra, the repo's `tools/ci/phpcs-security.xml` and `phpcs_delta.py`) on every moved and changed file.
- **Release files, as MailWeb's:** `readme.txt` (`Requires at least: 6.4`, `Tested up to` the WordPress version live runs, `Requires PHP: 8.0`, `Stable tag: 1.0.0`, Description, Changelog `= 1.0.0 =` "Search Insights moved out of RankWeb, unchanged", Upgrade Notice); `composer.json` with `lint` and `make-pot` scripts; the translation template in `languages/`, made with `wp i18n make-pot . languages/<file>.pot --domain=aw-seo-extras --exclude=vendor`. The template is named after the text domain, `aw-seo-extras.pot`, while the domain stays `aw-seo-extras` (owner, 11 Nov); it becomes `insightweb.pot` with the deferred rename. Header, constant, Stable tag and template version move together on every release (wp-plugin-release).
- **No `uninstall.php`:** tables and options stay when InsightWeb is deleted, as today (1.2); only "Delete Search Insights data" drops them.
- **RankWeb goes to 2.0.0** in the same PR, as Search Insights leaves it (RankWeb's header, `readme.txt` and changelog; the RankWeb plan takes the rest from here).
- **Icon:** the InsightWeb icon (an eye with a yellow pupil) comes from `docs/brand/plugin-icons/` once it lands there (it is not in the repo yet): `assets/icon-256x256.png`, `assets/icon-128x128.png` and `assets/icon.svg`, used on the Kits → Plugins row. Phase 1 has no app header (the screen is unchanged); the header use waits for the later plan.

### 3.2 Load order, so no job runs twice or is missed

The risk is a gap, not a double run: Hostinger pulls the commit, but a new plugin is inactive until someone activates it in wp-admin. In that gap WP-Cron would fire `aw_si_daily` and the others with no handler (a repeating job is booked again and does nothing that day; a one-off event such as `aw_si_psi_more`, `aw_si_inspect_more`, `aw_si_clarity_retry` or `aw_si_bing` is used up and lost).

1. **One commit ships both sides.** `insightweb/` gets the files; RankWeb's loader drops `search-insights` and gains a bridge:
   - `insightweb.php` defines `INSIGHTWEB_FILE` when WordPress loads it (before `plugins_loaded`).
   - InsightWeb's bootstrap starts with a guard: `if ( defined( 'INSIGHTWEB_LOADED' ) ) return; define( 'INSIGHTWEB_LOADED', true );`. Whoever calls it first wins; the code can never load twice (which would also be a fatal "function already declared").
   - RankWeb's `plugins_loaded` (priority 5): if `INSIGHTWEB_FILE` is not defined (InsightWeb inactive), the stored module switch is not off, and `WP_PLUGIN_DIR . '/insightweb/includes/bootstrap.php'` exists, RankWeb requires it. So Search Insights keeps running from the minute the commit lands, active or not.
   - InsightWeb calls its bootstrap on `plugins_loaded` priority 5 as well. Active plugins load in `active_plugins` order (`aw-seo-extras` before `insightweb`), but the guard makes the order irrelevant.
2. **Nothing reschedules.** The bootstrap books jobs only where `wp_next_scheduled()` finds none, exactly as today; no activation hook books anything. The bookings already in the cron array keep their times.
3. **RankWeb's deactivation no longer clears `aw_si_*` jobs**, in the same commit, so switching RankWeb off later never stops InsightWeb. InsightWeb's deactivation clears the same list as today (1.4).
4. **Activate InsightWeb** in wp-admin (Plugins screen, by hand, not during the 06:30–08:10 IST run window and not during a FluentCRM send). Nothing changes on activation: the guard sees the code already loaded by the bridge on that request, and on the next request InsightWeb loads it itself.
5. **The bridge goes** in a small later commit, once the owner has seen InsightWeb active on live and one full daily run (06:30 to 08:10 IST) has logged OK in `aw_si_runs`.

Rollback: revert the commit (the release tag from the Deploy check). RankWeb loads `includes/search-insights.php` again; tables, options and bookings were never touched.

### 3.3 Menus and old addresses

The page slug `aw-search-insights` and every address `admin.php?page=aw-search-insights&tab=…` stay the same, so no redirect is needed. RankWeb's redirect of `aw-seo-settings&tab=search-insights` stays. (The older `tools.php?page=aw-search-demand` and `aw_admin_moved_pages` redirects named in `docs/seo/search-insights-plan.md` are no longer in the code; nothing to carry.)

### 3.4 Proof before and after

`staging.animalwrites.in` is not a usable copy of live (database from 15 Oct, its branch deleted, per `docs/staging.md`), and the owner's rule is no throwaway local WordPress. So staging is refreshed first (hPanel steps in `docs/staging.md`); phase 1 is proven on staging, plus CI and the read-only before/after records on live (approved 11 Nov). No merge or ship until staging is refreshed and the proof passes:

- **On the PR:** `php -l`, PHPCS, the Deploy check's smoke test (which boots WordPress with every folder under `plugins/`; it gets `wp plugin activate insightweb`) and the load-order test `tools/ci/insightweb_load_check.php`: RankWeb's bridge with InsightWeb inactive, then both active in either order (RankWeb is never off), asserting the code loads once, every hook in 1.4 has a handler, no job is booked twice, the routes and the seven constants are as before. The Deploy check has no PHPUnit suite and no Plugin Check today. **Phase 1 adds no Plugin Check step:** it would report on all 14,000 moved lines at once, which is a review of its own and not part of an extraction; it belongs with the later plan, for all Kits plugins together.
- **The numbers on each tab match before and after.** Read only on live, once before the ship and once after: the 17 Overview boxes, the count of each Keywords view, and the row counts of Rising, Pages, Clarity, Check list, Sourcing keywords, Links and Server; the 7 numbers on Settings; the `aw_si_runs` entries; `SELECT COUNT(*)` of each `wp_aw_si_*` table. Only rows a scheduled run added in between may differ, named by source and time.
- **No cron job is booked twice.** `wp cron event list` (read only) before and after: each hook in 1.4 appears once, at the same time as before. Checked again after activation and after the next daily run.
- **SourceWeb's five reads still work:** the Sourcing keywords tab shows the same rows and 28- and 90-day figures; Sourcing → Keywords → Suggestions shows the same count; the source-sites figures match; SourceWeb's links to Search Insights open the same tabs.
- **Kits → Keys looks the same:** the same five rows, each "set" or "not set" as before.
- **Search engines see nothing new:** RankWeb's file list changes, so read only, before and after: `robots.txt`, the sitemap index, and the head of five sample pages (Home, a News item, a listing, a Directory place page, a guide: title, description, canonical, robots meta, JSON-LD) match byte for byte. Search Insights prints nothing on public pages; this proves the move did not change RankWeb's output either.
- **Background runs, not only wp-admin:** the handlers load on `plugins_loaded` in every request (front end, WP-Cron, WP-CLI, REST), never behind `is_admin()`; the CI test runs in WP-CLI, where `is_admin()` is false.
- **A daily job run once:** the next scheduled 07:10 run, not a manual one (no extra Search Console call); its `aw_si_runs` entry OK and one day of new `gsc` rows.
- Live checks spaced out: one read before, one after deploy, one after activation, one after the next daily run, one after the bridge goes.

## 4. Phase 1 and its ship

Bigger technical work, so branch → PR into `wordpress-dev` → review gate (code-review, wp-plugin-audit, PHPCS on changed files, Deploy check green) → the owner merges → ship with `tools/aw-ship.sh`, on the owner's yes, never during a FluentCRM send (the Deploy check's `mail_sending_check.py` already refuses one).

| Step | What | Size | Risk |
|---|---|---|---|
| **1. Move the code** | `insightweb/` with the loader, the 37 files it loads (7 of them pull in the 7 list tables) and `data/`; bootstrap with guard; the bridge in RankWeb; RankWeb's loader, module list, deactivation, Overview row, Kits row and header edited (1.1); `aw_si_api_allowed()`, the 7 fields printed by InsightWeb, guard on `aw_seo_kp_replay()`; smoke test and load-order test; release files (3.1: PHPDoc header, version constant, `readme.txt`, `composer.json`, translation template; RankWeb 2.0.0); `docs/plugin-map.md` rows moved. About 14,000 lines moved, about 300 changed. | M (mostly moves) | Medium: load order and cron gaps (3.2); a missed RankWeb helper would show as a fatal in the smoke test, not on live |
| **1b. Activate, then remove the bridge** | Owner activates InsightWeb; after one clean daily run, the bridge goes | S | Low |

## WordPress.org edition (17 Nov 2026)

Phase 1 shipped and live (`b354050c`). This section folds in the former `docs/insightweb-org-assessment.md` and
`docs/insightweb-org-rebrand.md` (now deleted — `docs/insightweb-plan.md` is the single living doc). Read-only
assessment + extraction plan for listing `plugins/insightweb` on the public WordPress.org directory; no local
WordPress (owner rule), so runtime-only items are marked **(runtime)**. Side session; nothing shipped. InsightWeb
itself stays the self-hosted Animal Writes plugin (keeps its `Update URI` and in-code dependency checks); the public
plugin is a **separate codebase** that reuses the generic parts.

### Verdict / go-no-go

**No-go as it stands; a conditional GO to extract a separate listable plugin — with real cost at one layer: Google
credentials.** Of the suite InsightWeb has the clearest generic story ("Search Console + GA4 insights"), and the
analysis engine (keyword records, rising terms, quick wins, Discover/News/URL-Inspection, PageSpeed, Site Health,
WP-CLI) is genuinely generic and already works. The coupling (Kits Core, Rank Math requirement, Animal Writes post
types, the Kits admin shell, mixed prefixes) is all removable, none of it a rewrite. The one gating decision is how a
public install authenticates to Google (see **Credentials crux** below); until that is chosen it is a no-go on that
axis, not on the features.

### Decided plan (owner, 17 Nov 2026 — blockers 2, 3, 6, 7)

These are **locked** for the public/extracted edition. They apply **only to the public codebase**, never to the live
`plugins/insightweb` plugin.

- **#7 Credentials — LOCKED to model (A): companion to Site Kit.** The public plugin declares
  `Requires Plugins: google-site-kit`, reads Google (Search Console + GA4) **only through Site Kit**, and stores **no
  Google OAuth client or tokens of its own**. PageSpeed, Microsoft Clarity and Bing (own keys, no OAuth) are treated as
  standalone value that works with or without Site Kit. Move to model (B) own-OAuth **only** if Site Kit's
  publicly-supported surface proves insufficient — and **flag that to the coordinator before doing so**. **(runtime)**
  Today the Google reads use Site Kit's *internal* classes (`\Google\Site_Kit\Context`, `...\Core\Modules\Modules`,
  module `get_data()`, `googlesitekit_*_settings`); confirm a publicly-supported path per Site Kit version — the
  internal-API dependency is a per-version runtime risk to re-check.
- **#2 One prefix.** `insightweb_` (functions/options/hooks/meta/REST namespace/text domain), `InsightWeb_` classes,
  `INSIGHTWEB_` constants. Remove the mixed `aw_si_` / `aw_` / `aw_kw_` / `aw_seo_` / `kits_` in the public codebase.
  No `aw`-based name anywhere newly written.
- **#3 No `Update URI`.** Remove the `Update URI` header and the self-hosted-update hooks (`plugin-info.php` →
  `update_plugins_wugweb.com`, `plugins_api`) from the public codebase. The **live** plugin keeps them for self-hosted
  updates.
- **#6 Drop the Animal Writes coupling.** Remove the hardcoded `https://animalwrites.in/` (`links-seed.php:21-22`,
  the crawl user-agent + alert-email body in `crawlcheck.php:48,325`, the repo link in `server.php:217`), the shipped
  AW seed links (`search-insights/data/links-2026-11-02.php`), and the AW post types (`aw_listing` / `aw_news` /
  `aw_legal` / `aw_campaign` / `aw_opportunity`, `AW_SEO_ITEM_TYPES`) — the public plugin operates on admin-chosen
  public post types only.

**Build status (17 Nov 2026; see the progress table below for the current state):** when this was written the extracted
build did not exist, and the code for #2/#6 was planned as the I1–I3 port. Unlike MailWeb (already single-prefixed and standalone), a *loadable* InsightWeb extraction
also needs (a) its **own keyword table** to replace Kits Core's `aw_kw_*`, and (b) the bundled **`insightweb-shell`**
UI package, which the RankWeb session is still extracting. So the re-prefix (#2) and the woven-in decouple (#6) cannot
land green standalone until those land; #3 and #7 are header-level and land with the skeleton (I1). Sequence the build
I1 → I3 so each phase stays PHPCS/tests-green. **I1 done (`f0c8c935`):** `plugins/insightweb-org/` scaffolded —
standalone, activates next to Site Kit with an empty tab set, #3 (no Update URI) and #7 (`Requires Plugins:
google-site-kit`, model A) resolved, one `insightweb_` prefix; `php -l` + PHPCS (WordPress + security ruleset) + a
no-WP smoke test all green. **Own keyword table done (`0e87dc6c`):** `includes/keyword-table.php` — `insightweb_kw_*`
(install/ready/table/norm/schema + id_by_norm/get/create/set_figures), the search-insights subset of Kits Core's
`aw_kw_*` with the sourcing/news columns and the taxonomy map table dropped (#6 at the data layer); 17 green checks.
**Decision engine done (`ae211a59`):** `includes/compute.php` — the generalised "decide" logic (`insightweb_priority`,
`insightweb_is_question`/`is_news`, `insightweb_gap_action`, `insightweb_quick_win`, `insightweb_decide`,
`insightweb_actions`), the differentiator that turns metrics into a suggested action; the Directory/Library/News-item
branches dropped (#6), thresholds as overridable constants; 31 green checks. **Observation tables done (`23822062`):** `includes/schema.php` —
the plugin's own four tables (`kw_pages`, `obs`, `rising`, `links`) with pure per-table schema builders,
`insightweb_install` (admin_init, after the keyword table) and `insightweb_ready`; the AW links seed not carried (#6);
37 green checks. **Daily recompute done (`f3acd37a`):** `includes/recompute.php` —
`insightweb_compute` (reads `obs`, runs `insightweb_decide`, writes decisions onto the records via
`insightweb_kw_set_figures`), split into a pure, unit-tested `insightweb_compute_row` + float-tolerant
`insightweb_compute_changed`; redirect resolution and the News/tag/category "minor path" dropped (#6); 42 green checks
(the DB orchestrator is runtime-verified only). Next: the **Site-Kit GSC/GA4 read** (model A — the per-version
internal-API risk; keep it bounded behind a clear adapter), which fills `obs`; then `keywords`/`rising` list builders;
then the shell-backed tabs behind the shared `insightweb-shell` package, and the shared external-services registry once
its package lands.

### Safety audit (standalone .org edition, 17 Nov 2026) — SAFE

Verified the public edition is safe to activate/uninstall with Animal Writes Core, Kits Core and the other suite
plugins present or absent, and alongside the live plugin, with no fatal, no redeclare and no live-data corruption:

- **Own prefix on everything.** Tables `{prefix}insightweb_{keywords,kw_pages,obs,rising,links}`; options `insightweb_db`,
  `insightweb_kw_db`; per-user screen meta `insightweb_*_per_page`; REST namespace `insightweb/v1`; menu slug `insightweb`;
  classes `InsightWeb_*` (+ the shared, guarded `Wugweb_External_Services`); 76 `insightweb_*` functions; constants
  `INSIGHTWEB_*`. No `aw_` / `kits_` names, no Core/Kits shared names, no enqueued script/style handles, no CPT/taxonomy.
- **No reads/writes of Core's shared data.** Grep confirms zero references to `wp_aw_si_keywords`, `aw_kw_db`, `aw_si_*`,
  `aw_kw_*` or any `aw_`/`kits_` option or function. The keyword table has its OWN db-version option `insightweb_kw_db`
  (not `aw_kw_db`) and its own table `{prefix}insightweb_keywords` (never `{prefix}aw_si_keywords`).
- **Activation idempotent, Core-independent.** No `register_activation_hook`; tables are created lazily on `admin_init`
  (keyword table priority 1, observation tables priority 2) guarded by an autoloaded version option and built with
  `dbDelta` — cheap and safe to re-run. The plugin boots standalone (no `KITS_CORE_LOADED` gate); Site Kit is reached
  only through `class_exists( '\Google\Site_Kit\Plugin' )`, so its absence shows a prompt, never a fatal. No cron and
  no transients, so nothing is orphaned on deactivation.
- **No fatal if co-installed with the live plugin.** The live plugin and the public edition are one plugin identity
  (slug `insightweb`), so a site runs one or the other — but even side by side there is **zero** overlap of global
  `insightweb_*` functions (live 44, public 76, intersection empty) and classes are disjoint (`AW_SI_*` vs
  `InsightWeb_*`), so no redeclare; tables and options are disjoint (`aw_si_*`/`aw_kw_*` vs `insightweb_*`), so no data
  clash. The only shared symbols are the identity constants `INSIGHTWEB_VERSION/DIR/FILE` — expected for one identity,
  and non-fatal (a second `define()` warns and is ignored).
- **Uninstall removes only its own data, opt-in.** `uninstall.php` runs only under `WP_UNINSTALL_PLUGIN` and only when
  `insightweb_delete_data` is on (off by default), then drops only the `{prefix}insightweb_*` tables and deletes only
  the `insightweb_*` options and per-user meta — never Core's or any `aw_*` data. (The toggle UI lands with the Settings
  tab; until then nothing is deleted on uninstall, which is the safe default.) Current-blog scope (standard WP limit).

### Extracted build progress (public edition, as of 17 Nov 2026)

`plugins/insightweb-org/` exists and is a separate, standalone codebase — the live `plugins/insightweb` is untouched.
Each piece landed green (`php -l` + PHPCS WordPress + `tools/ci/phpcs-security.xml` + the no-WP smoke test, now 61
checks); `vendor/` and `composer.lock` are never committed. Branch `claude/insightweb-org-assess-dc97b9`, **not** on
`wordpress-dev` or `wordpress` — side session, nothing pushed or shipped.

| Piece | File | Commit | State |
|---|---|---|---|
| I1 skeleton (#3, #7, prefix, empty tab set) | `insightweb.php`, `includes/admin.php` | `f0c8c935` | done, green |
| Own keyword table | `includes/keyword-table.php` | `0e87dc6c` | done, green |
| Decision engine | `includes/compute.php` | `ae211a59` | done, green |
| Observation tables | `includes/schema.php` | `23822062` | done, green |
| Daily recompute | `includes/recompute.php` | `f3acd37a` | done, green (DB orchestrator runtime-verified only) |
| Admin data layer (list/views/overview reads, override) | `includes/query.php` | Admin UI commit | done, green |
| Keywords list table (WP_List_Table: views, filters, search, sort, bulk/row override, screen options) | `includes/class-insightweb-keywords-table.php` | Admin UI commit | done, green |
| Overview dashboard (scorecards; MiniChart trends + gap-action/priority/status; top queries) | `includes/overview.php` | Admin UI commit | done, green |
| Admin screen (tabbed: Overview + Keywords; Site Kit prompt; shell-embeddable render) | `includes/admin.php` | Admin UI commit | done, green |
| REST `insightweb/v1/overview` (feeds the shell chart component) | `includes/rest.php` | Admin UI commit | done, green |
| Rising list table (status views, search, sort, noted/dismissed/new row + bulk actions) | `includes/class-insightweb-rising-table.php` | Rising/Links commit | done, green |
| Links list table (read-only: type filter, search, sort, pagination) | `includes/class-insightweb-links-table.php` | Rising/Links commit | done, green |
| Shared external-services registry (suite-neutral, collision-guarded, version-gated) + InsightWeb's Site Kit disclosure + Overview "Data sources" card | `includes/class-wugweb-external-services.php`, `includes/services.php` | Registry commit | done, green — **proposed suite reference, pending coordinator adoption** |
| Settings tab (Settings API: decision thresholds + delete-data opt-in; thresholds drive the engine) | `includes/settings.php` | Settings commit | done, green |

**Admin screen inventory (public edition):** Overview (scorecards + MiniChart trends/breakdowns + top queries + Data sources), Keywords, Rising, Links (all core `WP_List_Table`), and Settings — five tabs, each returning HTML (classic screen today; a shell "part" once `insightweb-shell` lands). Pages / Clarity / Check list / Server / Sourcing keywords from the live edition are intentionally **not** in the public scope (no ported data source), not gaps.

**Admin UI phase (§7 charts + §8 tables), decisions applied:**
- **Substrate.** The live plugin renders the Kits React shell as *chrome* and embeds each tab's server HTML as a shell
  "part" (`insightweb_tab_parts` → `data.parts`), with a classic `<div class="wrap">` fallback when the shell/Kits Core
  is absent. The public edition mirrors this: every tab renderer **returns** HTML, so the same markup serves the classic
  screen today and the shell as a part once the bundled `insightweb-shell` package lands. The shell look is unchanged —
  the WP_List_Table and the Overview inherit the shell's CSS; no restyle, no new theme (owner constraint).
- **Charts = MiniChart contract (NOT Chart.js).** The suite standard is the shell's `MiniChart({label, points:[{value,
  label}], unit})`: inline-SVG bars normalised to max, **last bar inked for attention, the rest greyscale**, a
  `<figcaption>` (label + first→last range) and a hidden screen-reader data table (unit appended). `insightweb_minichart()`
  server-renders that exact contract, so it is identical before and after the shell app mounts and needs no chart
  library. Colours come from `--insightweb-ink` / `--insightweb-bar` (shell-overridable; greyscale default). Built for
  the clicks / impressions / CTR / position 28-day trends (bars over time) and the gap-action, priority and status
  breakdowns (one bar per category). Design pass: dataviz (read first — one axis each, direct labels, table-equivalent,
  no dual-axis), then frontend-design / ui-ux-pro-max / accessibility-review on the screens.
- **Overrides** are the only write, and only to InsightWeb's own keyword record (row + bulk, nonce + capability,
  post/redirect/get).

**Governing dev standard — the WordPress Plugin Developer Handbook** (developer.wordpress.org/plugins/): header
requirements, Best Practices, Security (sanitize input / escape output / nonces / capability checks), Hooks, the
Settings & Options API, Internationalization, Privacy and the directory guidelines. Enforced on the whole
`plugins/insightweb-org/` codebase with **wp-coding-standards** (PHPCS `WordPress,WordPress-Docs`), **wp-plugin-audit**
and **wp-org-submission**; the official **Plugin Check** runs on a clean WP at submission (no local WP here — owner
rule). As of the Admin UI commit all 11 PHP files pass `php -l`, PHPCS `WordPress,WordPress-Docs` and
`tools/ci/phpcs-security.xml` with zero findings, and the new Admin UI code is verified against each Handbook dimension:
input sanitised (`sanitize_key`/`sanitize_text_field` + `wp_unslash`), output escaped (`esc_*`, late-escape only where
built from escaped parts), nonces (`check_admin_referer` on both override paths) + capability checks (screen, handler,
REST `permission_callback`), i18n (`insightweb` text domain + translator comments on every placeholder string), hooks +
the screen-options API, and no new external calls (Privacy). This standard applies to everything still to build.

The Rising and Links tabs now have their own list tables (Rising: status views, search, sort, noted/dismissed/new row
and bulk actions; Links: read-only, type filter, search, sort). Both show empty states until a source fills the
`rising` / `links` tables.

**Not built yet:** the Site Kit GSC/GA4 read (model A — the internal-API/runtime-risk adapter that fills `obs` and the
`rising`/`links` source rows, the one piece that makes the lists and charts show real data); the bundled
`insightweb-shell` React package + mount (the server-rendered parts and the MiniChart contract are ready for it;
RankWeb's build recipe applies).

**Shared external-services registry (built here as the proposed suite reference).** `class-wugweb-external-services.php`
is suite-neutral, load-once and collision-guarded (class + `WUGWEB_EXTSVC_VERSION` guarded; later versions raise the
constant and every plugin ships the identical file). InsightWeb registers its one real integration — the Google
Search Console / GA4 data flow through Site Kit — in `services.php`; PageSpeed / Clarity / Bing / DB-IP register from
their modules as they land. `readme_section()` renders the readme "== External services ==" block and the Overview
"Data sources" card reads the same list. **This is the first implementation in the suite; the coordinator must bless it
and have SourceWeb / RankWeb bundle the identical copy** (the file was built to be adopted verbatim, not forked). **Shipping:** this is a side session — it does not push or ship; the branch + hashes
go to the coordinator, and only the live-plugin changes (the settings bug fix `b3cd3a0d`, the readme
`0d723960`/`b8e5857d`) are production-bound. The `plugins/insightweb-org/` build is a separate public codebase and must
**not** be deployed to the live site.

### Feature coverage — the owner's InsightWeb feature list (checked against live `plugins/insightweb`, 17 Nov 2026)

Features the owner assigned to InsightWeb (they all need Google data, so they belong here, not RankWeb). "Built" is
the state of the **live** plugin; the public edition carries the generic ones as the engine ports.

| # | Feature | Built (live)? | Note |
|---|---|---|---|
| 1 | GA4 integration | **Yes** | `ga4.php`, read-only via Site Kit (organic / AI / referral sessions, key events). |
| 8 | AI search traffic tracker (ChatGPT, Perplexity… from GA4) | **Yes** | `ga4.php` `aw_si_ai_sources` (chatgpt.com, chat.openai.com, perplexity.ai, gemini.google.com, copilot.microsoft.com) + `referrer-icons.php`; shown on Overview/Pages. |
| 9 | Google index status (URL Inspection API) | **Yes** | `gsc-types.php` — URL Inspection + index status (queue; the free API is partial, as noted). |
| 11, 12, 29 | Email reports, white-label reports, report frequency | **No** | The only `wp_mail` is the crawler-access alert (`crawlcheck.php`); no scheduled/white-label analytics report and no frequency setting. |
| 27, 28 | Fetch frequency; days of data kept | **Partial** | Retention = `AW_SI_KEEP_DAYS` (Settings; daily→monthly rollup) — yes. Fetch frequency is fixed by cron (daily/weekly), not a setting — no. |
| 31–34 | Top 5 winning / losing keywords and posts | **Partial** | Movers (`AW_SI_MOVERS`, pos_prev vs pos28) and Rising/drops exist; no packaged "top-5 winners/losers" card for keywords *and* posts. |
| 35, 36, 37 | Content SEO overview; ranking keywords per post; position history | **Partial** | Pages tab (`pages.php`): per-page overview + owned keywords + GA4/Clarity/PageSpeed/Rank-Math score — yes. Daily positions are stored (`obs`, rollup, `openseo-positions.php`); a per-item position-history view is thin — partial. |
| 52 | AdSense earnings history | **No** | Not read (no `adsense` anywhere). Site Kit surfaces AdSense; InsightWeb does not. |
| 53 | Search Console / GA data for one country | **No** | `gsc.php`/`ga4.php` don't filter by country. (`visitors.php` has a DB-IP country for the Server tab — different thing.) |
| 66, 69 | Post performance badges; SEO performance per post | **Partial** | Per-post Rank Math SEO score + PageSpeed + flags on the Pages tab — yes; a visual "badge" is thin — partial. |
| 68 | PageSpeed per post (needs a PSI key) | **Yes** | `psi.php` — per-page mobile score, flags under a threshold. |
| 77, 78, 79 | GA tracking settings (anonymize IP, self-hosted GA script, exclude logged-in users) | **No / out of scope** | InsightWeb *reads* via Site Kit; it does not inject or configure the GA snippet — that is Site Kit / GTM's job. |
| 15 | Google Trends (no public API → link out) | **Partial** | `rising.php` takes Trends via the worker/GDELT and shows a notice when Trends refuses; it is a rising source, not a pure link-out. |

Takeaway for the public edition: the **read + analyse** features (1, 8, 9, 68, and the Pages-tab per-post SEO) are
built and port cleanly under model A; the **reporting** features (11/12/29, white-label) and **AdSense** (52) and
**country filters** (53) are genuinely not built; the **GA tracking settings** (77–79) are out of scope (Site Kit owns
the snippet). None of this changes the go/no-go — it is a backlog map.

### External-services registry (shared component, owner 17 Nov 2026)

A single declared registry of every outbound integration, **shared** with SourceWeb (and the rest of the suite) — one
implementation in a shared package, both plugins consume it. **Not an InsightWeb-only copy.** **Converged with the
SourceWeb session (17 Nov 2026); the contract below is agreed.** Working reference / template:
`plugins/sourceweb/includes/external-services.php` + `external-services-admin.php` (SourceWeb commit `a23dadd3`).

- **Declaration.** `register( id, { hosts[], purpose, data_sent, terms_url, privacy_url, enabled } )` on a
  `register_services` action. `hosts[]` is one or more hosts; `id` is optional and derived from the first host. Code
  services are **enabled-by-default**; admin-added endpoints are **opt-in (disabled until enabled)**. The admin UI
  accepts a comma/space list for `hosts[]`, and legacy `data`/`terms`/`privacy` keys as aliases (migration convenience).
- **Gating.** `remote_request( url, args, method )` is the **sole gate** over `wp_safe_remote_*` — a `WP_Error` unless
  the URL's host is registered **and** enabled. `external_service_for_host( url|host )` matches membership in `hosts[]`,
  normalising scheme / path / `www` / case. So an integration cannot phone home undisclosed.
- **Generation (single source of truth).** `external_services_readme()` builds the readme `== External services ==`
  section **and** the admin disclosure screen is generated from the same registry — no hand-maintained second copy.
- **Shared package, neutral naming.** Lives alongside the shared core (not in either plugin), with its **own neutral
  package namespace** — never `aw_` / `insightweb_` / `sourceweb_` / `kits_` (a `Wugweb\ServiceRegistry`-style namespace
  or a short neutral prefix). Each plugin wraps it under its own prefix. The **RankWeb build session owns the shared
  package path/namespace** (coordinator routing); when it lands, each plugin `require`s it and deletes its own copy
  (same option shape, no data migration).
- **InsightWeb's registered surface:**
  - **Google (Search Console + GA4)** — disclosed as **"accessed through the Site Kit by Google plugin"**: under model
    (A) Site Kit makes the actual Google calls, so InsightWeb registers this as a *via-Site-Kit* entry (no direct
    Google host of its own), with Google Terms/Privacy URLs.
  - **PageSpeed Insights** — `www.googleapis.com` (`/pagespeedonline/v5/runPagespeed`); page URL sent; Google T/P.
  - **Microsoft Clarity** — `www.clarity.ms`; own token; Microsoft T/P.
  - **Bing Webmaster** — `ssl.bing.com`; site URL + own key; Microsoft T/P.
  - **DB-IP** — `download.db-ip.com`; monthly country-lite file download, nothing sent; DB-IP T/P.
  - **Crawl checks** — the site's own URLs/robots from outside; no third-party data.
  - **Admin-added** — any endpoint the admin registers, same five fields.
- **Status:** contract agreed; SourceWeb has the working reference. InsightWeb consumes the shared package once the
  RankWeb build session publishes its path/namespace — then InsightWeb registers the surface above through it
  (`register_services`) and its readme section is generated, not hand-written. Until then the I1 skeleton makes no
  outbound calls, so there is nothing to gate yet. (The hand-written `== External services ==` on the live
  `plugins/insightweb` readme stays as-is.)

### What ports vs what is dropped

**Ports (generic search-analytics features):** keyword records (roles, owning pages, GSC figures by day, priority,
suggested action); rising terms / quick wins / conflicts; PageSpeed, Clarity, Bing (own keys); URL Inspection /
Discover / Google News (via Site Kit); the Links view (drop the AW seed data); Site Health test, Dashboard box, WP-CLI
(rename the command namespace); the settings model (store-only-if-differs-from-default, correct after the 17 Nov fix);
the admin UI (the Kits shell, reused as a bundled package — see **Admin UI**); the no-WordPress runner `tests/run.php`.

**Dropped (Animal Writes / Kits specific):** the site post types `aw_listing` / `aw_news` / `aw_legal` /
`aw_campaign` / `aw_opportunity` and `AW_SEO_ITEM_TYPES` (the public plugin operates on admin-chosen public post types,
never hardcoded); hardcoded `animalwrites.in` (`links-seed.php`, `crawlcheck.php` UA + alert email, `server.php` repo
link) and the shipped seed data (`search-insights/data/links-2026-11-02.php`); the Kits Core **runtime** dependency, the
RankWeb bridge and the shared `aw_seo_settings` option, the `aw/v1` worker routes, the `aw_worker` capability, the Keys
page (→ own settings fields); the **hard Rank Math requirement** (make it optional); reassess the DB-IP visitors/country
and server-log tabs. Rule of thumb: a feature about search/analytics data ports; anything about Animal Writes content or
the Kits shell is dropped.

### Dependency map — CRITICAL vs NON-CRITICAL

"WP.org slug" is the directory slug for `Requires Plugins`, or "—" for a suite/site plugin not on the directory (must
be removed/replaced for the public plugin, never required).

**CRITICAL** — hard-gated in `includes/bootstrap.php`; without these InsightWeb shows one admin notice and does nothing:

| Dependency | WP.org slug | Exact guard (file:line) | Effect if absent | Public-codebase handling |
|---|---|---|---|---|
| **Kits Core** | — | `! defined( 'KITS_CORE_LOADED' )` — `bootstrap.php:30`; **and** `! function_exists( 'aw_kw_install' )` (the keyword table, now Kits Core's) — `bootstrap.php:52` | Bootstrap returns early → one `admin_notices` warning, nothing else loads. | **Design out**: ship own keyword table + helpers (one prefix); no Kits Core dependency to require. |
| **Rank Math SEO** | `seo-by-rank-math` | `defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' )` — `bootstrap.php:31` | Same early return + notice. | **Make optional** (SEO scores + GSC backup, not core); if kept, `Requires Plugins: seo-by-rank-math`. |

**NON-CRITICAL** — guarded with `function_exists` / `class_exists`; the plugin boots and the feature degrades. None blocks a listing:

| Dependency | WP.org slug | Exact guard (file:line) | Degrade behaviour |
|---|---|---|---|
| **Site Kit by Google** | `google-site-kit` | `class_exists( '\Google\Site_Kit\Context' )` (`sitekit.php:17`), `\Google\Site_Kit\Plugin` (`sitekit.php:20`, `insightweb-rest.php:533`), `\Google\Site_Kit\Core\Modules\Modules` (`sitekit.php:46`) | Each Google read returns `WP_Error` "Site Kit is not active"; GSC + GA4 unavailable, the rest runs. **Functionally core, technically optional** — see the credentials crux. |
| **Animal Writes Core** | — | none inside InsightWeb — only the **live loader** (InsightWeb boots itself on `plugins_loaded` priority 5 in `insightweb.php`) | Not a runtime gate; standalone activation works without it. The keyword table it once owned is now Kits Core's. |
| **Kits Core admin shell** | — (bundled) | `function_exists( 'kits_shell_html_parts' / 'kits_shell_app' / 'kits_shell_mount' / 'kits_shell_rest_can' )` (`insightweb-tabs.php:46,56`, `insightweb-ui.php:36,75,143`, `insightweb-rest.php:29`), `kits_admin_tabs` (`kits-hub.php:17`), `kits_dash_cards`/`kits_dash_bars` (`dashboard.php:141,175`), `kits_status_cell` (`kits-hub.php:46`) | Empty/fallback output; screens do not render. The public plugin **bundles** this shell as a shared GPL package (handle `insightweb-shell`, Kits specifics via `wp_localize_script`, no runtime Kits Core); never a `Requires Plugins`. |
| **SourceWeb** | — | `function_exists( 'aw_sourcing_kw_on' )` | Sourcing-keywords tab hidden; everything else runs. (Dropped.) |
| **Keys** (`aw_key()`) + per-source keys | — | `function_exists( 'aw_key' )` (`runs.php:46`, `admin.php:1076`); each source checks its own key — `bing.php:35`, `clarity.php:36`, `psi.php:158` | A source whose key is unset simply does not run; no error. Become the plugin's own settings fields. |

### Security posture (good)

REST routes all have a real `permission_callback` (`kits_shell_rest_can()` nonce+cap, or `aw_si_api_allowed` =
admin/`aw_worker`); no `__return_true`. SQL via `$wpdb->prepare()` with `%i` for identifiers (167 uses). 17
`check_admin_referer` + 1 `check_ajax_referer`; 35 `current_user_can`. Raw `file_get_contents` only on the local readme
and uploaded import files. PHPCS `WordPress` + `tools/ci/phpcs-security.xml` clean on the files changed this session.

### The credentials crux (the gating decision)

InsightWeb holds **no Google OAuth client and no tokens of its own**. For GSC/GA4 it calls Site Kit's **internals**
(`\Google\Site_Kit\Context`, `...\Core\Modules\Modules`, module `get_data()`, `googlesitekit_*_settings`) — a private,
unsupported surface that can break on any Site Kit release **(runtime to confirm per version)**. Two models:

- **(A) Companion to Site Kit** — `Requires Plugins: google-site-kit`; the user connects Google in Site Kit; the plugin
  reads it. No credential handling, smallest build, honest story — but needs a *supported* way in (today's internal
  calls are a listing + maintenance risk).
- **(B) Own Google OAuth** (the Netpeak model) — the user's own Google Cloud credentials, tokens stored encrypted; or a
  vendor-run OAuth proxy (an external service to disclose and operate). Independent, but a real OAuth layer to build and
  support.

Recommendation: start with **(A)** scoped to what Site Kit supports publicly; treat PageSpeed/Clarity/Bing (own keys,
no OAuth) as value that works with or without Site Kit; move to (B) only if the Site Kit surface proves insufficient.

### Admin UI decision

The listable edition does **not** rebuild a native UI. It **reuses the current Kits shell as-is, kept identical** —
same code, same look, different handle — extracted into a shared GPL UI package **bundled** into the plugin (no runtime
Kits Core), enqueued under the namespaced handle **`insightweb-shell`** (never the global `kits-shell`), with the
Kits-specific bits (branding, `kits/v1`, cross-plugin nav) injected as config via `wp_localize_script`. RankWeb's build
session is extracting the shared shell as the template (see `docs/rankweb-org-rebrand.md` §4). This supersedes the
"InsightWeb app on the Kits Core plugin shell" named in "Later, separate plan" below.

**Shell package contract (from the RankWeb build session, 17 Nov; structurally final):**

- It is a **source** package each plugin builds its *own* bundle from — **not** one runtime build shared across plugins
  (WP.org plugins can't share a runtime dep cleanly). RankWeb's is `@rankweb/shell` (`packages/rankweb-shell`), built
  with `@wordpress/scripts` to handle `rankweb-shell`, global `window.rankweb.shell`.
- **For InsightWeb:** copy the shell src, change the one global line at the end of `index.js` to
  `window.insightweb.shell`, and in `webpack.config.js` map the `@kits/shell` import to `requestToExternal`
  `['insightweb','shell']` / `requestToHandle` `insightweb-shell`. Everything else stays byte-identical — **including
  the `kits-` CSS class names (keep them)**. Distinct handle + global + PHP prefix per plugin = no collision when both
  are active; no re-namespacing of the shell internals is needed.
- **Config contract** (`wp_localize_script` into `window.<yourApp>`): `{ screen, name, version, icon, tabs:[{slug,
  label, url, current}], rest:'insightweb/v1', canSwitch, … }`. The shell's `AppHeader` reads name/version/icon/tabs;
  screens read rest+screen. Branding, cross-plugin nav and the REST namespace are all injected here — nothing
  hardcoded in the shell.
- **Tabs/screens:** pass the `insightweb_tabs` set as the `tabs` array (the shell renders the header); each screen body
  is InsightWeb's own React component built against the shell's exports (`import { AppShell, AppHeader, SettingsGroup }
  from '@kits/shell'`), routed by `app.screen`.
- **Open (owner):** the canonical **shared source location** for the shell (so the plugins don't keep divergent copies)
  is a suite-wide decision with the owner. Until then, build from the RankWeb copy as above. The staged shell-backed
  tabs wait on this.

### Listing blockers (wp.org checklist)

- **Slug** `insightweb` free (404 on wordpress.org/plugins/, verified 17 Nov); name has no third-party mark; main file =
  slug. Permanent once taken — re-check before submitting.
- **One 4+ char prefix** `insightweb_` (`InsightWeb_` classes, `INSIGHTWEB_` constants) — blocker today (mixed
  `aw_si_`/`aw_`/`aw_kw_`/`aw_seo_`/`kits_`); public codebase only, do not mass-rename the live plugin.
- **`Requires Plugins`** for any hard dep the public plugin keeps — public codebase only, **not** the live plugin
  (owner's 11 Nov in-code-check decision; adding it risks blocking live activation because Kits Core is not a standalone
  active plugin on live).
- **`Update URI`** must go in the public codebase (live plugin keeps it for self-hosted updates via `plugin-info.php`).
- **`== External services ==`** done in place in `readme.txt` (Google via Site Kit, PSI, Clarity, Bing, DB-IP, crawl
  checks, with Terms/Privacy URLs). Installation/FAQ/Changelog present; short description brand-neutral and ≤150.
- GPL-2.0-or-later, `src/` present, React built locally, no CDN/minified-only. **No trialware/nags/undisclosed
  tracking.** Assets: icon + banner exist; **screenshots are new work**. Contributors: owner to supply a real wp.org
  account.

### WordPress.org landscape

- **Competitors:** *Site Kit by Google* (the dependency; a public InsightWeb is a companion/analysis layer, not a
  replacement); *ExactMetrics* / *MonsterInsights* (GA dashboards); *SiteStats Analytics* (combines GA/GSC/Bing);
  *Netpeak Analytics Kit* (GSC+GA4 with the user's own OAuth — the model for option B).
- **Differentiator:** not another metrics dashboard — the **keyword-record model** (roles, owning pages, priority, a
  suggested action), rising terms / quick wins / conflicts, Discover/News/URL-Inspection coverage, crawler-access
  checks. Lead the listing with this.
- **Category:** SEO / analytics. **Tags (≤5):** `search console`, `analytics`, `google analytics`, `pagespeed`, `seo`.

### Listing copy (draft, for the public edition)

- **Short description (≤150):** "Per-keyword insights from Search Console and GA4: roles, owning pages, priority and a next action, plus rising terms and quick wins."
- **Description opening:** "InsightWeb reads your Search Console and GA4 data (through Site Kit) and turns it into one record per keyword — its roles, the pages that own it, a priority and a suggested next action. Rising terms, quick wins and conflicts sit alongside. It only reads and suggests; it never changes your SEO settings, keyphrases or content."
- **Long description:** "InsightWeb turns the search data you already have into decisions. Instead of one more metrics dashboard, it builds a record for every keyword your site ranks for — the roles it plays, the pages that own it, a priority, and a suggested next action — so you know what to do, not just what happened. It reads Search Console and Google Analytics 4 through Site Kit by Google (read-only; InsightWeb stores no Google credentials of its own and never changes your site or your SEO). On top of that it surfaces rising terms, quick wins (pages ranking just below the first page) and conflicts (two pages competing for one term), checks each page's PageSpeed, and confirms search and AI crawlers can reach your content. PageSpeed, Microsoft Clarity and Bing Webmaster add their figures when you set their keys. A Site Health test, a WordPress dashboard widget and WP-CLI commands keep it out of your way. It reads and suggests; what you change is always your call."

### Phase plan (each phase stops and reports)

- **I0 — decisions (owner):** name/slug, prefix, shared-core vs fork, **credentials model**, Rank Math optional-vs-required.
- **I1 — skeleton:** new folder, header (no `Update URI`, `Requires Plugins` per the credentials choice), one prefix,
  text domain, the tab framework + the bundled shell, empty generic tab set; activates on a clean WP; settings model +
  `tests/run.php` green.
- **I2 — Google data:** implement the chosen credentials model; GSC + GA4 read on a clean install; graceful
  "connect Google" state. **(The real cost; size depends on the credentials choice.)**
- **I3 — the engine:** keyword records, rising/quick-wins/conflicts, compute, Pages — wired to the generic post-type
  setting, each PHPCS-clean and tested.
- **I4 — independent sources:** PageSpeed, Clarity, Bing, Links (own keys, no OAuth); reassess visitors/server tabs.
- **I5 — readme, assets, screenshots:** re-branded; generate screenshots.
- **I6 — compliance + submit:** `wp-org-submission` + Plugin Check + PHPCS; clean zip (`.distignore`); slug check;
  submit; answer the reviewer; on approval set up SVN deploy.

### Runtime-only items (no local WordPress)

Compatibility of the Site Kit internal calls against the installed Site Kit version; cron registration, REST routes, DB
table creation on activation, Plugin Check on a clean WP next to Site Kit + Rank Math; the official readme validator
before submission.

### In-place work done this session (live plugin; safe)

- **The bridge (step 1b/5) is not removed:** `aw_seo_extras_insightweb_bridge()` is still in
  `plugins/aw-seo-extras/aw-seo-extras.php`, dormant because InsightWeb is active (returns as soon as `INSIGHTWEB_FILE`
  is defined). Removing it is a small RankWeb commit — **out of scope for InsightWeb-only work**, left to the RankWeb
  session; noted, not forced.
- **Settings default-value bug fixed:** a stored value equal to the default was kept, so it did not follow a later
  default change — fixed in `aw_si_settings_save()` (`b3cd3a0d`; test updated `eadd5e5c`).
- **readme hygiene:** `== External services ==` + brand-neutral differentiator-led description added; `Requires Plugins`
  / `Update URI` **not** touched on the live plugin (reasons above).

## Later, separate plan

Named only; none of this is part of phase 1, and each would need its own plan and the owner's yes: an InsightWeb app on the Kits Core plugin shell (Dashboard with tiles and chips, Keywords as the working list, Settings with connected services); the keys moving from Kits → Keys into that Settings; InsightWeb's own menu with redirects from the old tabs; an "Apply focus keyword" action through `aw/v1/seo-focus`; Sourcing keywords moving into SourceWeb; daily counts of quick wins and conflicts; the shell parts the pilot would ask Kits Core for; clearing the `aw_si_srv_speed` events on deactivation (1.4); the text domain and `aw_si_` rename. Any new route a later plan adds uses InsightWeb's own namespace, `insightweb/v1/…` (each Kits plugin has its own; `kits/v1` is Kits Core's); phase 1 keeps every `aw/v1/` route as it is.

## Owner questions (phase 1): approved 11 Nov

1. **Kits Core requirement: yes.** InsightWeb checks for Kits Core, Animal Writes Core and Rank Math in code, not with a `Requires Plugins:` header.
2. **Proof: refresh staging first** (`docs/staging.md`). Phase 1 is proven on staging, plus the read-only before/after records on live (3.4).
3. **RankWeb's text, exactly as written: yes.** Plugins screen description: "Everything Rank Math doesn't do or keeps for Premium, for Kits sites." Kits → Plugins row: "What Rank Math doesn't do or keeps for Premium." Overview row: "Search Insights is now its own plugin, InsightWeb." (same link). RankWeb's scope is everything Rank Math doesn't do or keeps for Premium; only Search Insights moves out.

The text domain stays `aw-seo-extras` (owner, 11 Nov).

---

## Keyword scoring & cross-purpose use (live InsightWeb — Parts 2/3/5/6, 17 Nov 2026)

Owner directive: give the one keyword table a numeric **Score**, a **Recommended use** that spans SEO and sourcing, a
**focus-keyphrase reconciliation**, a **canonical-owner** across the pages a keyword ranks for, and an **opt-in feed to
SourceWeb**. Suggestions only — InsightWeb never writes to Rank Math or SourceWeb. The scored results are stored in
**InsightWeb's own table** (keyed by `kw_id`, written by the daily recompute), never as a column on Kits Core's shared
`wp_aw_si_keywords`. The pure logic (tested, no WordPress) lives in `plugins/insightweb/includes/search-insights/score.php`.

### Score (0–100) — weights and formula

`score = round(0.35·Demand + 0.30·Opportunity + 0.20·Momentum + 0.15·Engagement)`, each part a 0–100 sub-score:

| Part | Weight | Signal | Rule |
|---|---|---|---|
| Demand | 35 | impressions(28d) + imported volume | log scale: `log10(1+total) / log10(1+5000) · 100`, capped at 100 |
| Opportunity | 30 | latest position | 0 unranked · 20 at pos ≤3 (won) · 70 at 4–5 · 100 at 5–20 (sweet spot) · 100→30 across 20–50 · 30→0 across 50–100 |
| Momentum | 20 | rising growth, else Trends | `growth/5 · 100` when there is a growth multiple, else `trends/100 · 100` |
| Engagement | 15 | CTR = clicks/impr | `ctr/0.10 · 100` (a 10% CTR = full) |

Weights and caps are constants at the top of `score.php` (`AW_SI_SCORE_W_*`, `AW_SI_SCORE_*_CAP`). The **score floor**
for "worth acting on" is `AW_SI_SCORE_FLOOR` (default 55; the Settings threshold overrides it).

### Recommended use — rules (one or more labels per keyword)

- **Focus keyphrase → page** — has an owning page and ranks in the winnable band (positions 4–20).
- **Write a new page** — score ≥ floor, no page, not ranking (positions >20 or none) — the Tracked-not-ranking set.
- **Secondary keyphrase** — has a page but already ranks top-3, or is a minor variant.
- **Sourcing candidate: news / library / campaign** — the keyword's topic maps to a sourcing content type in **SourceWeb's
  own category list** (read via a function, never hardcoded; empty when SourceWeb is absent), and it has impressions.
- **Exclude** — carries the Exclude role, or has no demand and a sub-floor score.

### Focus-keyphrase reconciliation (Part 3)

- **Set A** = keywords carrying the Focus-keyphrase role (set in Rank Math).
- **Set B** = keywords InsightWeb recommends as focus (`aw_si_is_focus_candidate`: score ≥ floor, position 4–20, has a page).
- Flags: **"Focus keyphrase, poor rank"** = in A, not B (candidate to drop — the not-ranking focus words); **"Should be a
  focus keyphrase"** = in B, not A (candidate to add). Both live beside Conflicts in one **Focus-keyphrase health** filter.

### Canonical owner (Part 6) — intent-first, then content-type ladder

Query **intent** from the phrase: `local` ("near me"/place) → `dated` (news words / a bare year) → `campaign` (donate/
volunteer/petition…) → else `evergreen`. Content **class** of a page from its post type / path: resource, library,
campaign, news, directory, other. The owner is the candidate with the best ladder rank for the intent, tie-broken by
position then CTR:

- **evergreen:** resource ≈ library (5) > campaign (4) > news (3) > directory (2) > other (1)
- **dated:** news (5) first · **local:** directory (5) first · **campaign:** campaign (5) first

Flags: **"Owned by News, should be Resource/Library"** (evergreen query where a news page ranks best but an evergreen
page should own the focus keyphrase; news keeps it as secondary); **"No canonical owner"** (≥2 candidate pages and none
holds a majority of impressions — a consolidation candidate).

### Feed into SourceWeb (Part 5 — InsightWeb emit side only)

Keywords scoring above the threshold **and** carrying a Sourcing-candidate recommendation are pushed as *candidates* via
`do_action('sourceweb_push_keywords', $terms, $context)` — one call per candidate, `$terms` an array of the one keyword
label. A well-ranking focus keyphrase is a proven term, flagged `focus_keyphrase:true` with its rank. Candidacy only —
the terms land in SourceWeb's **approval queue**, never auto-insert; no shared table and no hard dependency (the hook
sits idle if SourceWeb is absent). The SourceWeb receive side is a separate session.

**`$context` shape — LOCKED (field names are the contract; the live aw-sourcing receiver matches these exactly, held by
the coordinator):**

| Field | Type | Meaning |
|---|---|---|
| `source` | string | always `'insightweb'` |
| `score` | int 0–100 | the keyword score |
| `recommended_type` | string | `'news'` \| `'library'` \| `'campaign'` (from the `sourcing:TYPE` recommendation; `'news'` default) |
| `impressions` | int | Search Console impressions, 28 days |
| `clicks` | int | Search Console clicks, 28 days |
| `position` | float\|null | latest position (Search Console, else live check) |
| `topic` | string | the keyword's topic |
| `focus_keyphrase` | bool | carries the Rank Math Focus keyphrase role |
| `rank` | float\|null | the focus keyphrase's rank (= `position`) |

Emitted by `aw_si_push_context()` (`search-insights/sourcing-push.php`); the threshold is the option
`aw_si_sourcing_push_min` (default 70, filter `aw_si_sourcing_push_min`). InsightWeb reads the topic→type map from the
`aw_si_sourcing_types` filter that SourceWeb fills (empty ⇒ no sourcing recommendation).

**Build status (17 Nov):** Stage 1 (pure core `score.php`) and Stage 2 are **done, green on the branch** — the score store
`wp_aw_si_scores` (`AW_SI_DB_VERSION` 2→3) + the recompute writer (`aw_si_compute` → `aw_si_score_set`); the Keywords-tab
Score (sortable) + Recommended-use columns and the Focus-keyphrase-health filter; and the `sourceweb_push_keywords` emit +
threshold. Commits: `cef3d252`, `3fc5a1c3`, `2f5afb70`, `9297ed6a`, `8d889cdf`; 103 no-WP tests green. **Stage 3 (still to
build):** the multi-page candidate list + canonical-owner ladder (Part 6; the `owner_path`/`owner_type`/`owner_flag` store
columns are reserved) and a threshold Settings-UI field. Branch only; Part 4 (the Sourcing-keywords
tab) is SourceWeb's session.
