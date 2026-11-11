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

## Later, separate plan

Named only; none of this is part of phase 1, and each would need its own plan and the owner's yes: an InsightWeb app on the Kits Core plugin shell (Dashboard with tiles and chips, Keywords as the working list, Settings with connected services); the keys moving from Kits → Keys into that Settings; InsightWeb's own menu with redirects from the old tabs; an "Apply focus keyword" action through `aw/v1/seo-focus`; Sourcing keywords moving into SourceWeb; daily counts of quick wins and conflicts; the shell parts the pilot would ask Kits Core for; clearing the `aw_si_srv_speed` events on deactivation (1.4); the text domain and `aw_si_` rename. Any new route a later plan adds uses InsightWeb's own namespace, `insightweb/v1/…` (each Kits plugin has its own; `kits/v1` is Kits Core's); phase 1 keeps every `aw/v1/` route as it is.

## Owner questions (phase 1): approved 11 Nov

1. **Kits Core requirement: yes.** InsightWeb checks for Kits Core, Animal Writes Core and Rank Math in code, not with a `Requires Plugins:` header.
2. **Proof: refresh staging first** (`docs/staging.md`). Phase 1 is proven on staging, plus the read-only before/after records on live (3.4).
3. **RankWeb's text, exactly as written: yes.** Plugins screen description: "Everything Rank Math doesn't do or keeps for Premium, for Kits sites." Kits → Plugins row: "What Rank Math doesn't do or keeps for Premium." Overview row: "Search Insights is now its own plugin, InsightWeb." (same link). RankWeb's scope is everything Rank Math doesn't do or keeps for Premium; only Search Insights moves out.

The text domain stays `aw-seo-extras` (owner, 11 Nov).
