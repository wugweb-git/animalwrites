# InsightWeb: Search Insights out of RankWeb (plan)

11 November 2026. Owner's decision of 11 Nov ("9. Decision log": "Search Insights leaves RankWeb; plugin names"). **Plan only: no code until the owner approves.** Built on `docs/seo/search-insights-plan.md`, the Search Insights entries in `docs/pending.md`, `docs/plugin-map.md` and the code on `wordpress-dev` at `b0512a4e` (live `wordpress` is `637e9e64`; the two commits between touch only the joined CSS files, nothing here).

## The split in one line

- **InsightWeb measures:** Search Console, GA4, Clarity, PageSpeed, Bing, keywords, Rising, quick wins, conflicts, links, server and crawler checks.
- **RankWeb acts:** title and description rules, schema, FAQ, canonicals, breadcrumbs, sitemap additions, noindex rules.
- **InsightWeb never writes SEO settings.** A suggested focus keyword goes to RankWeb through the existing `POST /wp-json/aw/v1/seo-focus`, and only when someone presses Apply on it.

## Fixed for this plan (owner)

- A Kits plugin that requires Kits Core. It also needs Animal Writes Core, as Search Insights does today: the keyword table `wp_aw_si_keywords` and its helpers (`aw_si_table()`, `aw_si_norm()`, `aw_kw_install()`) are Core's (owner's decision 3, 25 Oct).
- Data stays as it is: tables, options, cron hooks, REST routes and every `aw_si_*` name. The aw → kits rename is deferred (11 Nov); only new code is named `kits` from the start.
- No GA4 or GTM change. No new Search Console calls: the same calls as today, once per calendar day.
- AI keys are not touched. They are not on the Keys screen (they live in SourceWeb's `ai-keys.php`) and nothing here reads or moves them.

## Where the brief and the Notion log differ

The brief's UI standard says "built with `@wordpress/components` and `@wordpress/dataviews` … no home-made design system". A later 11 Nov entry in the Notion decision log replaces that (owner: "WordPress own packages was a good thought but now it sucks… I gave you 2 references: Google Site Kit or Uncode"):

- Kits plugins look like Google Site Kit.
- A Kits admin design system (tokens and components) ships in Kits Core, and every Kits screen uses it.
- WordPress packages are used only for plumbing: `@wordpress/element`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`.
- Own implementation: no code, CSS, icons, images or fonts copied from Site Kit.
- Clickable HTML prototypes of the key screens, beside the reference, come before any build.

A second 11 Nov owner decision makes that design system **one shared plugin shell in Kits Core** (app header, chips, tiles, "Select metrics", widgets, a DataView wrapper, Connected services rows with a DataForm, settings groups, setup flow, notices, empty, loading and error states), with **InsightWeb as its pilot**. Its spec is `docs/kits-plugin-ui.md`, beside `docs/admin-ui-spec.md`. Which WordPress packages sit inside the shell is the shell's decision, not this plan's.

This plan follows both entries: phase 2 (section 2.2) describes only what InsightWeb registers into the shell and the data it serves, and lists the shell parts the pilot needs (2.2.5). Neither `docs/kits-plugin-ui.md` nor `docs/admin-ui-spec.md` exists on any branch or in any worktree yet (checked with `git for-each-ref`, `git ls-tree` and a file search on 11 Nov). Phase 2 follows them once they are written, where they differ from this plan.

## 1. Inventory

Everything below is in `plugins/aw-seo-extras/` (RankWeb 1.9.3) unless a path says otherwise. "Moves" means it goes to InsightWeb with its name unchanged.

### 1.1 Files

| Files | What they do | Where |
|---|---|---|
| `includes/search-insights.php` | Loader: needs Animal Writes Core, then loads 37 files | Moves (becomes InsightWeb's loader) |
| `search-insights/schema.php`, `runs.php`, `sitekit.php`, `keywords.php`, `gsc.php`, `compute.php`, `rollup.php`, `cron.php`, `sources.php` | Tables, run log, Site Kit calls, keyword roles, Search Console by day, priorities, quick wins, conflicts, rollup, schedules, redirects counted as their target | Moves |
| `ga4.php`, `psi.php`, `clarity.php`, `bing.php`, `bing-crawl.php`, `gsc-types.php`, `alerts.php`, `sitesearch.php`, `visitors.php` | GA4, PageSpeed, Clarity, Bing Webmaster, Bing crawl and keyword volumes, Discover / Google News / URL Inspection, Google Alerts, searches on the site, visitor countries | Moves |
| `rising.php`, `links.php`, `links-seed.php`, `data/links-2026-11-02.php`, `pages.php`, `checklist.php`, `server.php`, `crawlcheck.php`, `selfcheck.php` | Rising, Links, Pages, Check list (bookmarklet live check), Server, crawler access, the server's own speed and AI checks | Moves |
| `imports.php`, `tool-imports.php`, `snapshot.php`, `searches.php`, `dryrun.php`, `rankmath.php`, `referrer-icons.php` | Imports and paste forms, Ubersuggest / vidIQ / OpenSEO seeds, the monthly snapshot CSV, content gaps, the dry run, Rank Math reads, referrer icons | Moves |
| `admin.php` and the 7 `class-aw-si-*-table.php` list tables | The tabbed screen | Moves (phase 1 as it is; replaced in phase 2) |
| `health.php` | Site Health test `aw_search_insights_fresh` | Moves |
| `dashboard.php` | The SEO box on the WordPress Dashboard, from stored rows (functions named `aw_seo_dash_*`) | Moves, names kept |
| `cli.php` | `wp aw-si …` | Moves, command name kept |
| `focus-keywords.php`, `keyphrase-model.php` | `aw/v1/seo-items`, `aw/v1/seo-focus` and the one-page-per-keyphrase rule | **Stays in RankWeb** (this is the hand-off route) |
| `analysis.php` | `aw/v1/seo-score-items`, `aw/v1/seo-scores`: Rank Math content analysis | Stays |
| `faq.php`, `faq-overview.php`, `titles.php`, `canonical.php`, `schema.php`, `rank-math-schema.php`, `breadcrumbs.php`, `sitemap.php`, `robots.php`, `filtered-views.php`, `programmatic.php`, `redirects.php`, `opengraph.php`, `llms.php`, `markdown.php`, `health.php` (endpoints watchdog), `helpers.php`, `overview.php`, `settings.php`, `kits-hub.php` | SEO work on the site | Stay |

RankWeb files that touch Search Insights today, and what happens to each:

| Where | What | Change |
|---|---|---|
| `aw-seo-extras.php` loader | `'search-insights'` in the file list | Removed (phase 1) |
| `aw-seo-extras.php` deactivation hook | Clears `aw_si_cron_hooks()` | Removed: RankWeb must never clear InsightWeb's jobs |
| `settings.php` modules list | Module `search-insights`: switch, 7 numbers (`AW_SI_HIGH_TRENDS`, `AW_SI_HIGH_IMPR`, `AW_SI_QUICK_MIN`, `AW_SI_MOVERS`, `AW_SI_PSI_FLAG`, `AW_SI_RM_LOW`, `AW_SI_KEEP_DAYS`), hidden tab | Removed from RankWeb; InsightWeb defines the same constants from the same stored values (1.3) |
| `settings.php` | Redirect of `aw-seo-settings&tab=search-insights` to Search Insights → Settings; `aw_seo_settings_embed()` | Redirect kept; the embed is no longer called |
| `overview.php` | Search Insights module row, link and health status | Becomes one line: "Search Insights is now InsightWeb" with its link |
| `focus-keywords.php` | Refuses a keyphrase whose Search Insights owner is another page (reads `wp_aw_si_kw_pages`) | Kept: RankWeb reads, guarded by `function_exists( 'aw_si_ready' )` |
| `kits-hub.php` | Kits → Plugins row "SEO rules, FAQ, schema and Search Insights." | Text loses "and Search Insights"; InsightWeb adds its own row |
| Plugin header | "SEO rules, FAQ, schema and Search Insights for Kits sites." | "SEO rules, FAQ and schema for Kits sites." |

Search Insights calls three RankWeb functions; each gets a guard or its own copy in InsightWeb:

| Function | Used by | Change |
|---|---|---|
| `aw_seo_api_allowed()` | REST routes in `rising.php`, `crawlcheck.php` | InsightWeb's own `aw_si_api_allowed()`, same rule (administrator or `aw_worker`) |
| `aw_seo_settings_embed()` | Settings tab numbers | InsightWeb's own form for its 7 numbers (1.3) |
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
- **Owner's choices:** `aw_si_checklist`, `aw_si_gdelt_on`, `aw_si_tool_seeds` ("Seeds for keyword tools", 25 searches saved 10 Nov), `aw_si_live_token`.
- **The 7 numbers:** stored today in RankWeb's option `aw_seo_settings`, under the key `search-insights` (only values that differ from the default). They stay there. InsightWeb defines the same `AW_SI_*` constants from `aw_seo_settings['search-insights']` with the same defaults, and its own Settings writes only that key. RankWeb's save starts from the whole stored array (`aw_seo_saved()`), so it never drops the key once the module is gone from its list.
- **The module switch:** `aw_seo_settings['search-insights']['on']`. If it is stored as off, InsightWeb loads only its Settings screen until the owner switches it on there. Its value on live is read once, read only, before phase 1 ships.
- **Screen options (user meta):** `aw_si_keywords_per_page`, `aw_si_pages_per_page`, `aw_si_rising_per_page`, `aw_si_links_per_page`, `aw_si_checklist_per_page`, `aw_si_clarity_per_page`. Kept in phase 1 (same screen id, 2.1). Phase 2's lists keep their own per-user choices.
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
| `aw_si_geo_refresh` | Single event, 2 min after it is needed | Country database download |
| `aw_si_srv_speed`, `aw_si_srv_ai` | Single events from the self-check | Server speed and AI-crawler checks |
| `aw_si_rising_spikes` | (removed job, kept only to clear it) | — |

The repeating jobs are booked on `init` only when missing (`wp_next_scheduled()`), and none is booked with arguments. So a second loader can never book a job twice; the risk during the switch is a job with **no handler** (2.3).

**Found on the way (no change proposed in phase 1):** `aw_si_cron_hooks()`, used by deactivation, lists only 8 hooks (`aw_si_daily`, `aw_si_rollup`, `aw_si_ga4`, `aw_si_psi`, `aw_si_psi_more`, `aw_si_clarity`, `aw_si_clarity_retry`, `aw_si_bing`). Deactivating leaves `aw_si_gsc_types`, `aw_si_inspect`, `aw_si_inspect_more`, `aw_si_alerts`, `aw_si_crawl`, `aw_si_bing_crawl`, `aw_si_bing_kw` booked, and they run (and call out) whenever the code is loaded again. Proposed for phase 2, on the owner's yes: InsightWeb's deactivation clears the full list.

### 1.5 REST routes, admin actions, CLI, Site Health

| Kind | Name | Who | Where |
|---|---|---|---|
| REST | `GET/POST aw/v1/search-insights` | Workers' Rising job (`aw_worker`) | Moves, unchanged |
| REST | `GET/POST aw/v1/search-insights/crawl` | Workers' outside crawler check | Moves, unchanged |
| REST | `GET aw/v1/seo-items`, `POST aw/v1/seo-focus` | Workers' focus-keyword job; InsightWeb's Apply (phase 2) | **Stays in RankWeb** |
| REST | `aw/v1/seo-score-items`, `aw/v1/seo-scores` | Workers | Stays |
| admin-post | `aw_si_run`, `aw_si_delete`, `aw_si_download`, `aw_si_import`, `aw_si_note`, `aw_si_rising_paste`, `aw_si_gdelt`, `aw_si_bing_kw`, `aw_si_tool_seeds` | Settings and tab forms (nonce + `aw_si_cap()`) | Moves |
| AJAX | `wp_ajax_aw_si_live_save` | The bookmarklet live check (nonce + token, origins from `aw_si_live_origins`) | Moves |
| CLI | `wp aw-si` (and the aliases `wp aw-si-rising`, `wp aw-si-crawl`) | Owner, links reminder | Moves |
| Site Health | `aw_search_insights_fresh`, `aw_search_insights_crawl` | — | Move, same ids |
| Dashboard widget | SEO box | — | Moves |

### 1.6 The Keys screen

Kits → Keys (`admin.php?page=aw-keys`, Animal Writes Core `includes/keys.php`) lists only what plugins add with the `aw_keys` filter. Today only Search Insights adds anything: `AW_CLARITY_TOKEN`, `AW_BING_WEBMASTER_KEY`, `AW_PSI_KEY` (optional) in `runs.php`, and `AW_GOOGLE_ALERTS_FEED_1`, `AW_GOOGLE_ALERTS_FEED_2` in `alerts.php`.

- **Phase 1:** nothing changes. InsightWeb keeps the two `aw_keys` filters, so Kits → Keys looks the same.
- **Phase 2:** the five keys show in InsightWeb → Settings → Connected services (Clarity, Bing, PageSpeed, Google Alerts). Values stay in `aw_keys` and are still read with `aw_key()`; a `wp-config.php` constant still wins; a value is never shown again, only "set" and where. Editing a key goes through a new REST route that checks `manage_options` and writes only those five names into `aw_keys`. Once the Settings screen is live, InsightWeb drops its `aw_keys` filters. Kits → Keys then has nothing to list, so Core hides the menu item when `aw_keys_list()` is empty and redirects `page=aw-keys` to InsightWeb → Settings (a small Core change, its own commit). This also settles the 11 Nov "final admin structure" line "Keys → RankWeb Settings", which the later 11 Nov entry moved to InsightWeb.
- AI keys: not on this screen, not touched.

### 1.7 SourceWeb's hooks (must keep working)

SourceWeb (`plugins/aw-sourcing`) reads Search Insights in five places. None needs a change in phase 1, since every name stays and every call is guarded with `function_exists()`.

| SourceWeb | Reads | Note |
|---|---|---|
| `search-insights-tab.php` | Filter `aw_si_tabs` (adds "Sourcing keywords"), action `aw_si_tab_sourcing` | InsightWeb applies both, unchanged |
| `class-aw-sourcing-si-keywords-table.php`, `source-sites.php` | `aw_si_page_metrics( $paths, $days )` (28 and 90 days) | Function moves with `gsc.php`, same signature |
| `search-insights-tab.php` | `aw_sourcing_si_available()` = `aw_si_ready()` + `aw_si_table()` | `aw_si_ready()` moves; `aw_si_table()` is Core's |
| `keyword-suggestions.php` | Filter `aw_search_demand_terms` | SourceWeb fills it from the keyword table; unchanged |
| `admin-menu.php`, the tables | `aw_si_url()`, `aw_si_cap()`, `aw_si_runs()` | `aw_si_url()` returns the new address after phase 2 |
| Search Insights reads SourceWeb | Filter `aw_sourcing_keyword_items`, constant `AW_SRC_CAP` for `aw_si_cap()` | Unchanged |

In phase 2 the tabbed screen goes. The "Sourcing keywords" list is SourceWeb's code and its rows are Google News searches, not keywords, so it belongs in SourceWeb's own working list (a view of SourceWeb → Keywords that keeps reading `aw_si_page_metrics()`). Until SourceWeb moves it, InsightWeb shows any `aw_si_tabs` addition as an extra section reached from the Dashboard, rendered by the same callback (owner question 3).

### 1.8 FAQ figures

FAQ coverage (`aw_faq_index()`, the hourly transient `aw_faq_index`, `aw_faq_off_types`, the RankWeb Overview's FAQ status and RankWeb → FAQ) is RankWeb's and stays. Search Insights reads none of it. The only FAQ link is a suggestion: `compute.php` suggests the action "FAQ answer" for a question with no page. That stays a suggestion in InsightWeb; writing the answer happens in the item's FAQ box (RankWeb).

### 1.9 Rank Math reads

All read only, all move with InsightWeb: focus keyphrases for the roles (`keywords.php`), redirections and old slugs (`sources.php`), SEO scores and the Analytics Search Console rows as a backup (`rankmath.php`). Today Search Insights loads only when Rank Math is active, because RankWeb does. InsightWeb loads without Rank Math: each read checks for Rank Math first and the source shows "Rank Math not active" in the run log.

### 1.10 Screens today (RankWeb → Search Insights, `admin.php?page=aw-search-insights`)

| Tab | Content | Phase 2 place |
|---|---|---|
| Overview | 17 boxes: Sources, Priorities, Quick wins, Rising this week, Movers, Brand, AI referrals, Top referrers, Campaigns by source, Not found (404s), Index status, Discover and Google News, Clarity, Searches on our site, Content gaps, Crawler access, Bing crawl | Dashboard tiles and sections (2.2) |
| Keywords | List table; views All, P1, P2, P3, Gaps, Conflicts, Quick wins, Tracked not ranking, Entities; 18 columns | The working list |
| Rising | Rising terms, paste form, Noted / Dismissed | Dashboard section (chip Rising) |
| Pages | Per page: Search Console, GA4, PageSpeed, Clarity, Rank Math score | Dashboard section (chip Pages) |
| Clarity | Page by device, site summary | Dashboard section (chip Speed) |
| Check list | Monthly tracked list with the bookmarklet live check | A Keywords view "Check list" |
| Sourcing keywords | SourceWeb's | SourceWeb (1.7) |
| Links | Who links to us, Google Alerts mentions | Dashboard section (chip Links) |
| Server | Crawler requests, 4xx/5xx, PHP errors, hPanel figures | Dashboard section (chip Speed, as "Speed and server") |
| Settings | Schedule, keys, seed, the 7 numbers, Run now, imports, snapshot, Delete data | Settings (2.2) |

## 2. Admin

### 2.1 Phase 1: same screen, new owner

The screen is registered by InsightWeb, still as a submenu of RankWeb's menu (`AW_SEO_MENU`) while RankWeb is active, so the address, the screen id (`aw-seo-plus_page_aw-search-insights`), saved screen options and box order all stay. Without RankWeb it becomes a top-level InsightWeb menu. The only visible change is a new row on the Plugins screen and on Kits → Plugins.

### 2.2 Phase 2: InsightWeb as the pilot of the Kits plugin shell

The admin is one shared shell in Kits Core (owner, 11 Nov), specified in `docs/kits-plugin-ui.md` (not on any branch yet on 11 Nov). The shell owns every component: app header, section chips, tiles, "Select metrics", widgets, the DataView wrapper, Connected services rows with their DataForm, settings groups, setup flow, notices and the empty, loading and error states. InsightWeb designs none of them. It is the first plugin to use the shell, so it is built together with the shell, and this section lists only what InsightWeb registers through the shell's PHP and JS API, plus the data it serves from its own REST routes.

**Screens.** Three menu items, shown as tabs in the app header: Dashboard (`insightweb`), Keywords (`insightweb-keywords`), Settings (`insightweb-settings`). Capability: `aw_si_cap()` for Dashboard and Keywords, `manage_options` for Settings. Header extras: date range (28 days by default; 7, 28, 90 days), Help (links to `docs/seo/search-insights-plan.md` topics on the Help tab), and a "Run now" menu (2.2.4).

**REST routes** (new code, so `kits/v1/insightweb/…`; each read-only route answers from stored rows only, never calls Google, and is cached in a transient that the daily run clears; the shell's refresh asks again with `refresh=1`):

| Route | Method | Serves |
|---|---|---|
| `kits/v1/insightweb/tiles` | GET `?days=28` | Every tile below |
| `kits/v1/insightweb/sections/<key>` | GET | One Dashboard section's widgets |
| `kits/v1/insightweb/keywords` | GET (search, filters, sort, page, per_page, view) | The Keywords list |
| `kits/v1/insightweb/keywords/bulk` | POST | Priority bulk actions |
| `kits/v1/insightweb/services` | GET | Each connected service's status and summary |
| `kits/v1/insightweb/services/<key>` | POST, DELETE | Save or clear that service's keys or switch (`manage_options`) |
| `kits/v1/insightweb/settings` | GET, POST | The settings groups |
| `kits/v1/insightweb/run/<source>` | POST | Run one source now (2.2.4) |
| `aw/v1/seo-focus` (RankWeb, existing) | POST | Apply focus keyword, `dry: true` first |

#### 2.2.1 Dashboard tiles

Every window is "28 days to the newest stored day" (Search Console runs 2–3 days behind), compared with the 28 days before; the change chip shows only when both windows are fully stored, as `aw_seo_dash_gsc()` does today. Source line: the source's name and the newest stored day, linking to its row in Settings → Connected services.

| Tile | Number | Sub-line | Change chip | From |
|---|---|---|---|---|
| Impressions | Sum of `impr` | "Search Console, to {day}" | % against the 28 days before | `wp_aw_si_obs` src `gsc`, metric `total`, period `day` (same query as `aw_seo_dash_gsc()`) |
| Clicks | Sum of `clicks` | Click rate (clicks ÷ impressions) | % against the 28 days before | Same rows |
| Average position | Impression-weighted mean of `pos` | "Lower is better" | Places up or down (text label "up 0.4", never colour alone) | Same rows. New query: the number must be checked once against Search Console's own site average |
| Quick wins | Count of keyword records matching `aw_si_quick_sql()` (position 8–20, at least `AW_SI_QUICK_MIN` impressions) | Their impressions in total | Count against 28 days before | `wp_aw_si_keywords`; history needs the daily counts below |
| Conflicts | Count with `status = 'conflict'` | Keywords with two owning pages or Google showing another page | Count against 28 days before | `wp_aw_si_keywords`; same note |

Counts of quick wins and conflicts are not kept by day today. The daily run (`aw_si_daily`, after the calculation) writes one row each into `wp_aw_si_obs` (src `si`, metric `quick_count` / `conflict_count`, period `day`): existing table and columns, no new call. Until 56 days are stored those two tiles show no change chip. The rollup keeps the last value per month for them (added through the existing `aw_si_last_value_metrics` filter). Clarity and Bing have no separate on/off option: each is on when its key is set (`aw_si_clarity_on()`, `aw_si_bing_on()`).

"Select metrics" (shell, saved per user) may also offer, from stored rows: Brand clicks, AI referrals (GA4), 404s still open, Index status (indexed share), PageSpeed mobile median, Clarity pages flagged, Rising terms new this week, External links. Default set: the five above.

#### 2.2.2 Dashboard section chips

Chips: **Keywords · Pages · Rising · Links · Speed**. Each opens its section of shell widgets (charts and short tables) fed by `sections/<key>`, with a link into the full list where there is one. Every Overview box of today lands in one section, so nothing is lost:

| Chip | Widgets (from today's boxes and tabs) |
|---|---|
| Keywords | Clicks per day (chart), Priorities (counts per P1–P4), Movers, Brand, Content gaps; links into the Keywords views |
| Pages | Pages (per page: Search Console, GA4, PageSpeed, Clarity, Rank Math score), Not found (404s), Index status, Discover and Google News, Searches on our site, Top referrers, AI referrals, Campaigns by source |
| Rising | Rising this week; the Rising list with Mark as noted / Dismiss / Back to new; the paste form |
| Links | The Links list (views by type, Unsorted, Hidden, Spam; its row and bulk actions), Google Alerts mentions |
| Speed | PageSpeed, Clarity (site summary, pages by device, notes), Server (crawler requests, 4xx/5xx, PHP errors, hPanel figures), Crawler access, Bing crawl |

The Sources line (each source's last good run and last error, from `aw_si_runs`) shows in the header's status area or as the first widget, as the shell provides.

#### 2.2.3 Keywords (the working list, shell DataView wrapper)

- **Columns** (today's 18, same order): Keyword, Priority, Status, Topic, Page, Impressions (28 days), Clicks, Position, Change, Bing impressions (90 days), Live check, Trends, Volume (imports), Ubersuggest, vidIQ (YouTube), OpenSEO, Suggested action. Shown by default: Keyword, Priority, Status, Page, Impressions, Clicks, Position, Change, Suggested action; the rest are in the columns menu.
- **Sortable:** Keyword, Priority, Topic, Impressions, Clicks, Position, Change, Trends (as today).
- **Views:** All, P1, P2, P3, Gaps, Conflicts, Quick wins, Tracked not ranking, Entities, Check list. Each is a saved view whose rule is `aw_si_view_sql()` (Check list: the records in `aw_si_checklist`). In All, the listing names stay one combined "Listing names" row plus the top movers, as today.
- **Filters:** Topic, Page type (Hub, Directory, Topic, Guide), Role (sourcing, focus, tracked, brand, searched), Language (en, hi, hinglish), plus search.
- **Bulk actions:** Set priority P1, P2, P3; Use the calculated priority. Check list view: Remove.
- **Row actions:** Open page; Apply focus keyword, shown only where the suggested action is one. It sends `POST aw/v1/seo-focus` with `dry: true` and shows RankWeb's answer (would write, or why it refuses: another page owns it, not in the SEO title, a News item against an evergreen owner). A second press sends it for real. InsightWeb never writes `rank_math_focus_keyword`.
- **Check list view:** the bookmarklet for the live check (a draggable link carrying the token from `aw_si_live_token`) sits above the list.

#### 2.2.4 Settings

**Connected services**, each a shell row (status → summary → Edit opens the DataForm with Save, Cancel and Disconnect). Status, in this order: "Not set up" (missing key or module), "Error" (last run failed, with its date and message from `aw_si_runs`), "Late" (last good run older than twice its schedule, the existing rule in `runs.php`), else "Connected, last run {date}". Keys never reach the browser: the form shows "set", "not set" or "set in wp-config.php" (then read-only: change it there).

| Service | Connect steps (Edit) | Status rule | Disconnect |
|---|---|---|---|
| Search Console | None in InsightWeb: link to Site Kit's Search Console settings | Site Kit active and its Search Console module connected with an owner; run log `gsc` | Hidden (Site Kit owns it) |
| GA4 | None in InsightWeb: link to Site Kit's Analytics settings. No GA4 or GTM change | Site Kit's Analytics module connected with an owner; run log `ga4` | Hidden |
| Clarity | Paste the Data Export token (`AW_CLARITY_TOKEN`). Help: "Clarity → Settings → Data Export → Generate new API token." | Token set (`aw_si_clarity_on()`); run log `clarity`; calls used today of 10 (`aw_si_clarity_calls`) | Clears the token in `aw_keys` |
| Bing Webmaster | Paste the API key (`AW_BING_WEBMASTER_KEY`). Help as on Keys today | Key set (`aw_si_bing_on()`); run logs `bing`, `bing_crawl`, `bing_kw` | Clears the key |
| PageSpeed | Optional key (`AW_PSI_KEY`); without one it runs as the Search Console owner, as today | Run log `psi`; "Connected" also with no key | Clears the key |
| Google Alerts | Up to two RSS feed addresses (`AW_GOOGLE_ALERTS_FEED_1`, `_2`), checked against the Google Alerts feed pattern | At least one valid feed; run log `alerts` | Clears the feeds |

Also shown as rows without a form (status only): Workers (Rising job and outside crawler check, from their last `aw/v1/search-insights` posts) and GDELT (on or off, `aw_si_gdelt_on`; its switch sits in the Sources group below).

**Admin settings groups** (shell groups, plain descriptions, no raw option tables):

| Group | Fields | Stored in |
|---|---|---|
| Thresholds | The 7 numbers: High demand Trends, High demand impressions, Quick win impressions, Listing names shown one by one, PageSpeed flag score, Rank Math low score, Daily figures kept (days); "Restore defaults" | `aw_seo_settings['search-insights']` (only values that differ from the default, as today) |
| Schedules | Read-only times per source (the table in 1.4) and Run now per source: daily update, rollup, GA4, PageSpeed, Bing Webmaster, Clarity ("uses 2 of its 10 daily calls") | Cron; run log |
| Sources | GDELT on/off | `aw_si_gdelt_on` |
| Keyword tools | Seeds for keyword tools (one per line) | `aw_si_tool_seeds` |
| Imports | File imports and paste forms (today's `aw_si_settings_rows`: Ubersuggest, vidIQ, OpenSEO and the other kinds in `aw_si_import_kinds`) | As today |
| Snapshot | Download the monthly snapshot CSV; the keyword map rebuild note | As today |
| Data | Keyword map seed status; Delete Search Insights data (typed DELETE) | As today |

#### 2.2.5 Shell parts the pilot needs that may not be in the shell yet

To check against `docs/kits-plugin-ui.md` once it exists:

1. **Tiles anchored on the source's own last day,** not on today: a per-tile "to {day}" and the date range applied as "N days to the newest stored day".
2. **A change chip that can be absent** (comparison window not fully stored) with its own text, and a count-type change ("+12") as well as a percentage and a position change where lower is better.
3. **A full list inside a Dashboard section** (Pages, Rising, Links are paged lists with row and bulk actions), since the plugin has only three menu items. Either the DataView wrapper works inside a section, or the shell allows a secondary list screen per section.
4. **Disconnect that a service can hide** (Search Console and GA4 belong to Site Kit), and a key field that is read-only when a `wp-config.php` constant wins.
5. **Long-running actions with progress:** Run now for PageSpeed and URL Inspection work in steps over minutes; the shell needs a started / running / done state read from the run log.
6. **A file upload field in DataForm** (imports) and a **file download action** (snapshot CSV).
7. **A danger-zone action with a typed confirmation** (Delete data).
8. **A custom slot above a list** for the bookmarklet link, and a **paste form** widget (Rising).
9. **Sections added by another plugin** (`aw_si_tabs`, today SourceWeb's Sourcing keywords), only if owner question 3 keeps it in InsightWeb.
10. **The WordPress Dashboard box** (`dashboard.php`) stays a core dashboard widget outside the shell, unless the shell offers one.

**Build notes (shell-independent):** data comes from InsightWeb's REST routes with `api-fetch` nonces; capabilities per route as above; JS strings in the `insightweb` text domain with `wp_set_script_translations()`; InsightWeb's own script per screen registers its tiles, columns, groups and services and is enqueued only there; performance budget per screen as the shell sets it (proposed for the pilot: under 150 KB gzip of InsightWeb's own JS per screen, first data within 1 s on a warm cache, at most 3 requests on first render, no remote call on page load); notices only on InsightWeb's own screens, always dismissible; WCAG 2.2 AA through the shell's components.

**What RankWeb is left with** (its own UI work, after the pilot): Dashboard (SEO health: items missing titles, descriptions or schema; FAQ coverage; noindex counts) · FAQ · Settings (rules and modules), on the same shell. In phase 1 RankWeb only loses the Search Insights items.

## 3. Moving it out

### 3.1 Plugin shape

- Folder `plugins/insightweb/`, main file `insightweb.php`, moved files under `includes/` with `git mv` so history follows them.
- Header: `Plugin Name: InsightWeb`, `Description: Search Console, GA4, Clarity, PageSpeed, Bing, keywords and rising terms for Kits sites.`, `Requires PHP: 8.0`, `Requires at least: 6.4`, `Text Domain: insightweb`, `Update URI: https://wugweb.com/plugins/insightweb/`, `Author: Wugweb`.
- **No `Requires Plugins: kits-core` header.** On live, Kits Core is loaded by Animal Writes Core (`animal-writes-core.php` requires it first), not always active as its own plugin, so WordPress would refuse to activate InsightWeb. The requirement is checked in code on `plugins_loaded`: without Kits Core or without Animal Writes Core, InsightWeb shows one notice on the Plugins screen and loads nothing else (owner question 1).
- Text domain: the moved strings change from `aw-seo-extras` to `insightweb` (mechanical; English-only site, so nothing visible). A `.pot` made with `wp i18n make-pot`.
- Kits Core hooks: a Kits → Plugins row and a System Status row through `kits_plugin_screens` and `kits_status_rows`, as RankWeb does.
- PHPCS (WordPress-Extra, the repo's `tools/ci/phpcs-security.xml` and `phpcs_delta.py`) on every moved and changed file; prefixes `aw_si`, `kits_insightweb`, `insightweb`.

### 3.2 Load order, so no job runs twice or is missed

The risk is a gap, not a double run: Hostinger pulls the commit, but a new plugin is inactive until someone activates it in wp-admin. In that gap WP-Cron would fire `aw_si_daily` and the others with no handler (a repeating job is booked again and does nothing that day; a single event such as `aw_si_psi_more`, `aw_si_inspect_more`, `aw_si_clarity_retry` or `aw_si_bing` is used up and lost).

1. **One commit ships both sides.** `insightweb/` gets the files; RankWeb's loader drops `search-insights` and gains a bridge:
   - `insightweb.php` defines `INSIGHTWEB_FILE` when WordPress loads it (before `plugins_loaded`).
   - InsightWeb's bootstrap starts with a guard: `if ( defined( 'INSIGHTWEB_LOADED' ) ) return; define( 'INSIGHTWEB_LOADED', true );`. Whoever calls it first wins; the code can never load twice (which would also be a fatal "function already declared").
   - RankWeb's `plugins_loaded` (priority 5): if `INSIGHTWEB_FILE` is not defined (InsightWeb inactive) and `WP_PLUGIN_DIR . '/insightweb/includes/bootstrap.php'` exists, RankWeb requires it. So Search Insights keeps running from the minute the commit lands, active or not.
   - InsightWeb calls its bootstrap on `plugins_loaded` priority 5 as well. Active plugins load in `active_plugins` order (`aw-seo-extras` before `insightweb`), but the guard makes the order irrelevant.
2. **Nothing reschedules.** The bootstrap books jobs only where `wp_next_scheduled()` finds none, exactly as today; no activation hook books anything. The bookings already in the cron array keep their times.
3. **RankWeb's deactivation no longer clears `aw_si_*` jobs**, in the same commit, so switching RankWeb off later never stops InsightWeb.
4. **Activate InsightWeb** in wp-admin (Plugins screen, by hand, not during the 06:30–08:10 IST run window and not during a FluentCRM send). Nothing changes on activation: the guard sees the code already loaded by the bridge on that request, and on the next request InsightWeb loads it itself.
5. **The bridge goes** in a small later commit, once the owner has seen InsightWeb active on live and one full daily run (06:30 to 08:10 IST) has logged OK in `aw_si_runs`.

Rollback: revert the phase 1 commit (the release tag from the Deploy check). RankWeb loads `includes/search-insights.php` again; tables, options and bookings were never touched.

### 3.3 Menus and old addresses

- **Phase 1:** the page slug `aw-search-insights` and the address `admin.php?page=aw-search-insights&tab=…` do not change, so no redirect is needed. RankWeb's redirect of `aw-seo-settings&tab=search-insights` stays. (The older `tools.php?page=aw-search-demand` and `aw_admin_moved_pages` redirects named in `docs/seo/search-insights-plan.md` are no longer in the code; nothing to carry.)
- **Phase 2:** InsightWeb gets its own top-level menu (Dashboard, Keywords, Settings; slugs `insightweb`, `insightweb-keywords`, `insightweb-settings`). RankWeb's menu loses Search Insights. Old addresses redirect with `wp_safe_redirect()` on `admin_init`, keeping the meaning of `tab` and `view`:
  - `page=aw-search-insights` (Overview) → Dashboard; `&tab=keywords&view=quick` → Keywords, Quick wins view; `&tab=pages|rising|links|clarity|server` → Dashboard with that section open; `&tab=checklist` → Keywords, Check list view; `&tab=settings` → Settings; `&tab=sourcing` → SourceWeb's view (or InsightWeb's extra section until it moves).
  - `aw_si_url()` returns the new addresses, so every link in SourceWeb, Animal Writes Core, the Dashboard widget and notices follows.
- The Deploy check's `settings_parts_check.py` (report mode) flags new `add_menu_page` calls; the new menu goes into its baseline with the phase 2 PR.

### 3.4 Proof before and after

`staging.animalwrites.in` is not a usable copy of live (database from 15 Oct, its branch deleted, per `docs/staging.md`), and the owner's rule is no throwaway local WordPress. So the proof is (owner question 2):

- **On the PR:** `php -l`, PHPCS, the Deploy check's smoke test (which boots WordPress with every folder under `plugins/`; it gets `wp plugin activate insightweb`) and a CI test that loads RankWeb and InsightWeb in both orders, and RankWeb alone with InsightWeb inactive, asserting the code loads once and every hook in 1.4 has a handler.
- **On live, read only, before the ship:** a record of the numbers every view shows: Overview boxes, the count of each Keywords view, Rising, Pages, Links, Server row counts, the 7 settings values, the `aw_si_runs` entries, the cron bookings (`wp cron event list`, read only) and `SELECT COUNT(*)` of each `wp_aw_si_*` table.
- **On live, after the ship:** the same record, the same numbers (allowing only the rows a scheduled run added in between, named by source and time).
- **A daily job run once:** the next scheduled 07:10 run, not a manual one (no extra Search Console call); its `aw_si_runs` entry OK and one day of new `gsc` rows.
- **The focus-keyword hand-off (phase 2):** Apply on one suggested keyword with `dry: true` (nothing written, the answer shown), then for real on one item the owner picks; checked in Rank Math's box and in `wp_postmeta`. In phase 1 there is no hand-off change to prove; `wp aw-si` keyphrase check is run once, read only.
- Live checks spaced out: one read before, one after deploy, one after the next daily run, one after activation, one after the bridge goes.

## 4. Phases

Each phase is complete and checked on live before the next starts. Each ships on the owner's yes, never during a FluentCRM send (the Deploy check's `mail_sending_check.py` already refuses one). Route: bigger technical work, so branch → PR into `wordpress-dev` → review gate (code-review, wp-plugin-audit, PHPCS on changed files, Deploy check green) → the owner merges → ship with `tools/aw-ship.sh`.

| Phase | What | Size | Risk |
|---|---|---|---|
| **1. Move the code, no visible change** | `insightweb/` with the loader, the 37 files it loads (7 of them pull in the 7 list tables) and `data/`; bootstrap with guard; the bridge in RankWeb; RankWeb's loader, module list, deactivation, Overview row, Kits row and header edited (1.1); `aw_si_api_allowed()`, InsightWeb's own form for the 7 numbers, guard on `aw_seo_kp_replay()`; Rank Math checks per read; text domain; smoke test and load-order test; `docs/plugin-map.md` rows moved. About 14,000 lines moved, about 300 changed. | M (mostly moves) | Medium: load order and cron gaps (3.2); a missed RankWeb helper would show as a fatal in the smoke test, not on live |
| **1b. Activate, then remove the bridge** | Owner activates InsightWeb; after one clean daily run, the bridge goes | S | Low |
| **2a. Prototypes (with the shell)** | Clickable HTML prototypes of InsightWeb's Dashboard, Keywords and Settings, made from the shell's prototype beside the Site Kit reference; owner's yes. The 2.2.5 list checked against `docs/kits-plugin-ui.md` | S | Low |
| **2b. REST routes** | The `kits/v1/insightweb/…` routes (2.2), cached reads, the daily `quick_count` / `conflict_count` rows; can ship before the shell, with no screen change | M | Low: read only, except the services and settings writes, checked by the before/after record |
| **2c. Settings on the shell** | Connected services and settings groups registered; Core hides Kits → Keys when empty | M | Medium: keys and settings must round-trip with nothing lost |
| **2d. Dashboard on the shell** | Tiles, chips and sections registered | M | Low: read only |
| **2e. Keywords on the shell** | Columns, views, filters, bulk and row actions; Apply through `aw/v1/seo-focus` | M | Medium: the only write path (to RankWeb, through its own rules) |
| **2f. Menu and redirects** | Own top-level menu, the old tabbed screen retired, redirects (3.3), Sourcing keywords handed to SourceWeb, full deactivation list (1.4) | S | Low |

Phase 2 runs alongside the shell's own first phase in Kits Core (shell plus pilot first, then RankWeb, SourceWeb, MailWeb, DirectoryWeb, PlanWeb). 2c–2e wait for the shell parts they use; 2b does not.

## 5. Owner questions

1. **Kits Core requirement:** InsightWeb checks for Kits Core and Animal Writes Core in code instead of a `Requires Plugins:` header, because Kits Core is loaded by Animal Writes Core on live and is not always active on its own. OK?
2. **Proof "on a copy of live":** staging is stale and local test sites are off the table. OK to prove with the CI tests plus read-only before/after records on live (3.4), or should staging be refreshed first (hPanel steps in `docs/staging.md`)?
3. **Sourcing keywords:** move it into SourceWeb's own Keywords list in phase 2 (recommended), or keep it as an extra section inside InsightWeb?
4. **Admin UI:** phase 2 is the pilot of the shared Kits Core shell and registers into it (2.2), following the later 11 Nov log entries rather than the brief's `@wordpress/components` / `@wordpress/dataviews` line. Confirm.
5. **Deactivation list (1.4):** clear all 18 InsightWeb jobs in 1.4 on deactivation from phase 2f, instead of today's 8?
6. **Two new daily rows (2.2.1):** OK for the daily run to store the quick-win and conflict counts in `wp_aw_si_obs`, so those tiles can show a change? (No new call, no new table.)
