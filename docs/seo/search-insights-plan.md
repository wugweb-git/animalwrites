# Search Insights — plan (owner review)

24 October 2026. Approved (PR #1). Phase 1 is built and live (aw-seo-extras `includes/search-insights/`, PR #17), and the Sourcing keywords tab too (PR #32). The phase 1 leftovers (topic filter, listing names as one row, all 8 actions, gap page types, the 5,000-row split) were built on 25 Oct (PR #39), and phase 2 (GA4, PageSpeed, Clarity, searches on the site, Pages tab, redirects; PR #42) phase 4 (check list, bookmarklet live check, imports; PR #45) and the phase 5 snapshot on 25 Oct too. Phase 3 (workers job, Rising tab) is PR #44 and workers PR #7. Hindi and Hinglish stay out (owner, 25 Oct); YouTube, Reddit and Bing wait for the owner's keys and sign-in. It joins the seven Search Insights briefs of 24 Oct into one plan. Where the briefs differ, the latest brief wins, and section 13 lists each override.

Related files:
- `docs/seo/keyword-map.md` (Rank and Priority columns, 21 Oct)
- `docs/seo/keyword-demand-2026-10-21.md` (removed 25 Oct 2026; in git history)
- `docs/seo/rankings-2026-10-21.csv` (130 queries) (removed 25 Oct 2026; in git history)

## Fixed decisions (owner)

- **Free sources only.** No paid plans, APIs or budgets.
- **Listings are "entity" keywords.** They are shown as one aggregate plus the top 20 movers, not one row each in the main lists.
- **Hindi and Hinglish keywords are shown, never suggested to Sourcing.**
- **No Hindi or Hinglish work until phase 5** (owner, 24 Oct). Phase 1 tags `lang` only; grouping under English parents and Hindi autocomplete wait for phase 5.
- **No automatic changes.** No automatic edits to Rank Math keyphrases or to the Sourcing lists. Everything is a suggestion, and the owner decides.
- **No GA4 test events and no GA4 or GTM changes.** GA4 is read through Site Kit only.
- **Bing only after the owner signs in** to Bing Webmaster Tools.
- **No Google results scraping from the server or from GitHub runners.** The live Google check runs only in the owner's browser (bookmarklet).
- **Works without AW Sourcing.** If the plugin is off, the Sourcing view hides.

## 1. Purpose

Search Insights is one place to see four things:
- what people search;
- where Animal Writes ranks;
- how our pages and sourced items perform;
- what to do next.

It holds read-only data and suggested actions.

## 2. Rename: "Search demand" becomes "Search Insights"

**Where the old screen lived.** It was Tools → Search demand, from `plugins/aw-seo-extras/includes/search-demand.php` (removed 25 Oct 2026: its Search Console call is `aw_si_gsc_query()`, its gaps an Overview box, its address opens the Keywords tab):
- address `tools.php?page=aw-search-demand`, capability `aw_sd_cap()`;
- option `aw_search_demand`;
- cron `aw_search_demand_refresh`, daily at 07:00 IST.

**The rename.**
- The label "Search Insights" goes on the menu, the screen title and in the docs:
  - the `docs/component-map.md` row;
  - `docs/seo/content-strategy-2026-10-15.md`;
  - the header comment in `faq.php`.
- The new slug is `aw-search-insights`. The old address `tools.php?page=aw-search-demand` keeps working: a hidden page under the old slug redirects to the new one. The old Settings address already reaches Tools through `aw_admin_moved_pages` in `admin-ia.php`, which stays.
- Option `aw_search_demand` and cron hook `aw_search_demand_refresh` keep their names.
- The file can be renamed later. The function prefix `aw_sd_` stays, so nothing else breaks.

**The hard-coded keyword map goes.** `aw_sd_static()` is a hard-coded copy of the 15 Oct keyword map and is already out of date: it has no Rank or Priority. It is replaced:
- The new `keywords` table is seeded **once** from `docs/seo/keyword-map.md`, including the 21 Oct Rank and Priority columns, plus `rankings-2026-10-21.csv`.
- After the seed, **the site table is the source of truth.**
- `keyword-map.md` is regenerated from the table with each monthly snapshot (section 10).
- The site never parses repo Markdown at runtime.
- `aw_sd_static()` is removed after the seed.

## 3. Data model: one record per keyword

There are four tables, built with the existing dbDelta + version-option pattern (as in `plugins/aw-sourcing/includes/enrichment.php`, `aw_enrich_table()`).

### `wp_aw_si_keywords`
One row per normalised keyword.

| Column | Meaning |
|---|---|
| `id`, `norm`, `label` | `norm` is lower case, punctuation and extra spaces removed, plural/singular folded; `label` is the display form |
| `lang` | `en`, `hi` (Devanagari) or `hinglish` (Latin-script Hindi, e.g. "kutta kaatna", "gau shala") |
| `group_id` | the parent keyword: variants, Hindi and Hinglish forms are grouped under one English record (Hindi and Hinglish grouping in phase 5) |
| `roles` | any of: `sourcing`, `focus`, `tracked`, `brand` (below) |
| `topic`, `page_type` | from the keyword map / owning page |
| `priority`, `priority_override` | P1/P2/P3 computed (section 7); the owner can override |
| `status` | has page / gap / conflict |
| `entity` | 1 for listing names (Directory organisations) |
| `action` | the suggested action (section 7) |

**Roles.**
- **sourcing**: read from AW Sourcing (`aw_relevance` keyword lists and the `aw_sources` news keywords). Read only.
- **focus**: read from Rank Math `rank_math_focus_keyword` on posts, Library, Resources, Opportunities, campaigns, tags and categories. Listings are grouped as `entity`.
- **tracked**: the keyword map P1/P2 targets, plus the monthly check list (editable, section 5.4).
- **brand**: matches `AW_SD_BRAND` ("animal writes", "animalwrites", "india writes", …).

**Expected size.** About 3–4k rows. Listings are one aggregate entity record, plus a per-listing row only where GSC shows that name.

### `wp_aw_si_kw_pages`
Which page owns which keyword: `keyword_id`, object type and id, URL, `role` (owner / also ranks), `conflict`.

A conflict is either of:
- two pages with the same focus keyphrase;
- GSC sending the keyword to a page that is not its owner.

News items and term archives (tag, category) still own keywords, but they do not count towards "two pages". When only News items or archives own a keyword, GSC showing another News item or archive is no conflict either. Old addresses count as the page they redirect to: Rank Math exact and regex redirections and old slugs.

### `wp_aw_si_obs`
Every measurement, in long format: `keyword_id` or `page`, `source`, `metric`, `value`, `period` (day / week / month), `date`, `fetched_at`.

Every source writes here, so history and "change against last month" need no extra tables.

**Retention.**
- Daily rows are kept 90 days, then rolled up into monthly rows:
  - counts are summed, but **only rows for a single day** (`period` = day, one row per keyword or page per calendar date);
  - position is the mean weighted by impressions;
  - indexes and rolling-window figures (`period` = 28d, 3m, week) are never summed: the rollup keeps the last value of the month.
- Monthly rows are kept indefinitely.
- The rollup is a daily WP-cron job.

**Expected rows** (to confirm in the phase 1 dry run):

| Source | Rows a day |
|---|---|
| GSC date + query + page (one day) | about 2,500 |
| Clarity URL × device | about 300 |
| GA4 | about 300 |
| Workers / rising | about 400 |
| Site search | about 100 |
| **Total** | **about 3,600 a day, about 325k over 90 days** |

Monthly aggregates add about 10k rows a month, about 120k a year.

### `wp_aw_si_rising`
One row per rising term:
- `term`;
- `sources`: one or more of Trends RSS, related queries, autocomplete diff, new GSC query, News/GDELT spike, Wikipedia spike;
- `growth`, `first_seen`, `last_seen`;
- matching keyword or page (or none);
- suggested action;
- owner status: new / noted / dismissed.

### Option `aw_si_runs`
One entry per source: last good run, last error (time and message), next run and rows written. It feeds the "last updated" notices and the Site Health test.

### Uninstall and deactivation
Both keep the `wp_aw_si_*` tables and options. Dropping them is an explicit owner action:
- Tools → Search Insights → Settings → "Delete Search Insights data";
- a nonce, plus a typed confirmation.

There is no drop in `uninstall.php`.

## 4. What is attached to each keyword

| Data | Source | Notes |
|---|---|---|
| Owning page(s) and conflict flag | Rank Math + GSC | `kw_pages` |
| GSC position, impressions, clicks, CTR | Site Kit (Search Console) | 28 days, daily |
| Live Google position (monthly) | Bookmarklet | top 20, top 3 domains, People also ask |
| Demand | Trends index ("animal welfare" = 100), Bing volume, Keyword Planner import, autocomplete (en; hi in phase 5) | dated |
| Trending flags | Trends RSS, rising related queries, autocomplete diff, new GSC queries, News/GDELT spikes, Wikipedia spikes | `rising` |
| Sourcing performance | AW Sourcing (items fetched / published) → those items' GSC | **needs the AW Sourcing work, section 11** |
| On-site demand | GA4 site search (Site Kit), WordPress `?s=` and Directory searches | aggregated only (section 9) |
| Page quality | Clarity + PageSpeed of the owning page, plus Clarity notes | section 5.2 |

## 5. Sources

Every source follows the same failure rule:
- keep the last good data;
- write the error to `aw_si_runs`;
- show "last updated …" and the error in a notice on its tab and in Site Health;
- never fail silently;
- never retry in a tight loop: one attempt, then the next scheduled run (or the manual fallback).

### 5.1 Site Kit, from WP-cron as each module's owner (no new Google sign-in)

These use the call that already works in `aw_sd_sitekit()` / `aw_sd_query()`: switch user, then `Modules->get_module(…)->get_data(…)`. That is Site Kit's own module layer. It does not read tokens.

**Each module runs as its own owner.**
- Site Kit lets each module have its own owner. Today `aw_sd_sitekit()` reads only `googlesitekit_search-console_settings['ownerID']`.
- Phase 1 generalises it to `aw_si_sitekit( $slug )`. It reads `ownerID` from that module's own settings (`googlesitekit_search-console_settings`, `googlesitekit_analytics-4_settings`, `googlesitekit_pagespeed-insights_settings`) and switches to that user for that call only.
- If a module has no owner, or the owner can't be loaded, that source fails with a clear error under the failure rule. It never borrows another module's owner. Exception (owner, 25 Oct 2026): PageSpeed, which Site Kit never gives an owner, runs as the Search Console owner (else the GA4 owner).

**Search Console is stored by day.**
- The daily job asks for `date` + `query` and `date` + `query` + `page`. It covers the last 4 days, because Google finalises data 2–3 days late; rows already stored are replaced, not added.
- **One request per calendar day** per dimension set: 4 days × 2 sets = 8 calls a day. That keeps each call under the 5,000-row limit (about 2,500 rows a day).
- If a call still returns exactly 5,000 rows, the day is marked "truncated" in `aw_si_runs` and on the screen. Site Kit does not pass `startRow` (checked in its source, 25 Oct), so the job fetches that day's query + page rows again page by page (Site Kit's page filter), for the 50 pages with the most impressions. The warning stays when pages are left over or one page alone returns 5,000 rows, so totals are never silently short.
- Each row is one calendar day (`period` = day), so the monthly rollup can sum them safely.
- 28-day and 3-month figures on the screens are sums of those daily rows. The rolling-window totals Google returns, as today's `aw_sd_refresh()` stores them, are kept only as a check (`period` = 28d / 3m) and are never summed.

| Data | Module call | Schedule (IST) | Limits |
|---|---|---|---|
| Search Console: date + query and date + query + page, last 4 days, stored per day (28 days and 3 months are sums of daily rows); position, impressions, clicks, CTR | `search-console` → `searchanalytics` | daily 07:00 (the existing cron) | Google quota through Site Kit; ≤ 5,000 rows a call, so one call per day and dimension set (8 a day); anonymised queries are not returned |
| GA4: `view_search_results` by `search_term` | `analytics-4` → `report` | daily 07:20 | Site Kit quota; read only |
| GA4: sessions by session source (chatgpt.com, perplexity.ai, gemini.google.com, copilot) × landing page | `analytics-4` → `report` | daily 07:20 | read only; no events sent |
| GA4: landing pages by organic sessions | `analytics-4` → `report` | daily 07:20 | read only |
| GA4: `404_view` events by `path` (custom dimension `customEvent:path`, else `pagePath`), last 28 days (1 Nov 2026) | `analytics-4` → `report` | daily 07:20 | read only; Overview box "Not found (404s, GA4, 28 days)", top 20 with redirect status |
| PageSpeed: mobile + desktop for the 16 key pages | `pagespeed-insights` → `pagespeed` | weekly, Sunday 06:00 | 32 calls a week. Fallback if the module is off: a free PSI API key in `wp-config.php` (`AW_PSI_KEY`, 25,000 calls a day) |

"Not ranking" means a tracked keyword with no GSC impressions in 28 days.

### 5.2 Clarity (Data Export API), from WP-cron

**Token.** The owner creates a project API token in Clarity → Settings → Data Export. It is stored as `AW_CLARITY_TOKEN` in `wp-config.php`, never in the repo or the database.

**What is fetched.** Daily at 06:30. The API returns the last 1–3 days, so the job asks for the last 1 day:
- by URL: sessions, scroll depth, rage clicks, dead clicks, quick-backs, JS errors;
- by device: the same metrics.

**Limit.** 10 calls a day; the job uses at most 4.

**Flag.** A page with high rage or dead clicks, or quick-backs, that also gets search traffic. The thresholds are set after the first month of data.

**Clarity notes.** Recording summaries (Clarity Copilot insights) have no API. Instead, each page in the Pages view has a "Clarity notes" field: the owner pastes a short dated summary each month for flagged pages. It is stored in `obs` (source `clarity-note`).

**Never stored on the site:** recordings, session IDs or any session-level data. Only per-URL and per-device daily totals are kept.

### 5.3 Workers (new job `search-insights` in animal-writes-workers)

**Module and auth.**
- New module `aw_workers/search_insights.py` and workflow `.github/workflows/search-insights.yml`.
- Results are posted to a new route `POST /wp-json/aw/v1/search-insights`. It uses the existing worker sign-in: the `ingest` user's Application Password (`ingest.Client`), allowed by `aw_seo_api_allowed()`.
- The keyword, topic and species lists come from a `GET` on the same route.

**Caching and dry runs.**
- Caches and "last seen" sets live in `.state/` (actions/cache), as in the sourcing job.
- `DRY_RUN` and a step summary table, as in the other jobs.

| Source | Schedule (IST) | Limits and pacing | Fallback |
|---|---|---|---|
| Google Trends "Trending now" RSS, geo=IN | daily 08:10 | 1 request; kept only if the term matches the keyword map, topics or species list | none needed |
| Wikipedia pageviews API, watchlist (Rabies, Stray dogs in India, Animal Welfare Board of India, Prevention of Cruelty to Animals Act, Gaushala, …) | daily 08:15 | user-agent header with a contact; ≤ 1 request a second | — |
| GDELT 2.0 DOC API, mention timeline (India sources) for the topic watchlist | daily 08:20 | ≥ 5 s between requests | skip the day, keep the last data |
| Google autocomplete (en, gl=in; hi in phase 5), seed list; the diff against the last snapshot = rising | weekly, Monday 08:30 | 2–4 s between requests, cached | the bookmarklet runs the same seeds |
| Trends rising related queries, seed topics (unofficial free access) | weekly, Monday 08:45 | one attempt, paced; GitHub IPs are often refused (429) | manual paste form |
| Trends interest, India, 12 months, anchored "animal welfare" = 100 | monthly, 1st, 09:00 | as above | manual paste form (owner pastes Trends averages) |
| YouTube Data API (free key `YOUTUBE_API_KEY`): top videos + suggest terms for seed topics | monthly, 2nd | 10,000 units a day; a monthly run uses about 1,000 | skip, keep last |
| Reddit API (free app): top questions mentioning seed topics in Indian subreddits | monthly, 2nd | free tier, 100 requests a minute; we use < 50 | skip, keep last |
| Bing Webmaster API: keyword volumes + related terms, site query stats | monthly, 3rd; **off until the owner signs in** | Bing quota | — |

For YouTube and Reddit, only aggregated terms, video or post titles and counts are stored. No usernames, comment text, channel IDs or user IDs.

The schedule avoids the existing jobs:
- sourcing runs at :17 every 6 hours;
- focus keywords run at 04:00 IST;
- SEO scores run at 05:15 IST.

### 5.4 Manual (owner)

**Monthly check list.** Editable in Search Insights. It is built from:
- keyword map P1/P2;
- brand terms;
- the current quick wins;
- the 130 queries in `rankings-2026-10-21.csv` as the first list.

**Live Google check (bookmarklet).** Monthly.
- Search Insights → Rankings → "Run live check" shows the bookmarklet and a one-time token that expires after 2 hours.
- The owner opens google.com and clicks it.
- For each query on the list it:
  - loads `/search?q=…&gl=in&hl=en&num=20&pws=0`;
  - reads `#search a h3` → the position of animalwrites.in (top 20), the top 3 domains, and People also ask (`[data-q]`).
- It waits 8–15 s at random between queries.
- On "unusual traffic" it stops and saves its progress; the next click resumes.
- It posts results to `aw/v1/search-insights/live`. That route checks the token and allows CORS from `https://www.google.com` only.
- Nothing goes to GA4.

**Imports**, as CSV or paste:
- Keyword Planner volume ranges (free Ads account);
- Ahrefs Webmaster Tools: own organic keywords and backlinks (free, once animalwrites.in is verified);
- Trends averages, when the worker fails.

**Content data.** data.gov.in / IDSP rabies and dog-bite figures by state, stored with source and date for guides.

### 5.5 On the site

**WordPress search and Directory searches.**
- Covers `?s=` terms and Directory PIN / near-me / district searches.
- Counted per term per day (section 9).
- Written on the request; rolled into `obs` daily.

**AW Sourcing counts.** Read only from `plugins/aw-sourcing` (section 11):
- per keyword: items fetched and published (30 days), and the GSC impressions and clicks of those items;
- daily counts of entities and topics across fetched News: publishers (`aw_publishers`), states, species, laws (the `aw_entities` item meta written by summary rules);
- a spike flag at ≥ 3× the 14-day average.

## 6. Schedule at a glance (IST)

| When | Jobs |
|---|---|
| Daily | 06:30 Clarity · 07:00 Search Console · 07:20 GA4 · 08:10 Trends RSS · 08:15 Wikipedia · 08:20 GDELT · 23:30 rollup (90-day rule) + Sourcing counts |
| Weekly | Sun 06:00 PageSpeed · Mon 08:30 autocomplete diff · Mon 08:45 Trends related queries · Mon new GSC queries (impressions this week, none in the 4 weeks before) |
| Monthly | 1st Trends interest · 2nd YouTube, Reddit · 3rd Bing (after sign-in) · live check by the owner (any day, may be split over days) · snapshot (section 10) |
| On demand | "Refresh now" per source; imports; Clarity notes |

## 7. Priority and suggested actions

**Priority.** The rule from `keyword-map.md`, unchanged:
- **P1:** high demand (Trends T ≥ 20, or GSC ≥ 50 impressions in 28 days), and we rank below the top 3 but within 20 (above 3, up to 20, so 3.4 counts) or have a page that fits.
- **P2:** high demand, but no page yet (a content gap).
- **P3:** low demand, or already in the top 3.

Demand is the highest of Trends T and GSC impressions. Position is the latest live check, or GSC if newer. The owner can override any priority.

**Quick wins.** GSC positions 8–20 with ≥ 10 impressions.

**Content gaps.** High-demand keywords with no page, with a suggested page type: guide, district page or FAQ answer.

**Suggested action**, one per keyword or rising term:
- news explainer;
- FAQ answer;
- guide update;
- new page (guide / district page);
- fix conflict;
- add as secondary keyphrase;
- consider as Sourcing keyword (shown as a suggestion in Sourcing; never for Hindi or Hinglish);
- check page quality (Clarity / PageSpeed flag).

Nothing is applied automatically.

**Rising terms** show:
- source(s), growth and first seen;
- the matching page, or none;
- the suggested action.

## 8. Views and admin UI

Everything sits at Tools → Search Insights, with tabs (`nav-tab-wrapper`, `?tab=`).

### UI rules
- Core WordPress only, reusing existing layouts.
- No custom CSS. Any class that can't be avoided is registered in `kits-registry.json`.
- **Lists** (Keywords, Rising, Pages, Sourcing, Check list) are `WP_List_Table` subclasses, like `Mailweb_List_Table` in `plugins/mailweb/includes/list-table.php`. Each has:
  - sortable columns;
  - filters by views (`subsubsub`) and dropdowns;
  - row actions ("Noted", "Dismiss");
  - bulk actions, screen options, search box and pagination.
- **Overview** uses core dashboard-style postboxes (meta boxes). No custom cards.
- **Settings and imports** use the Settings API and `form-table`:
  - the Clarity token note;
  - paste forms;
  - CSV import, with uploads through core upload handling (`wp_handle_upload`);
  - the delete-data confirm.
- **Status** uses core notices (`notice notice-success|warning|error`) and dashicons. Each tab shows "last updated / error" for its sources.
- **Health.** A Site Health test, "Search Insights sources fresh" (`site_status_tests`). It warns when any source is more than twice its schedule late.
- **Charts.** None in phase 1. Later, only what admin already loads; no new chart library without the owner's OK.

### Tabs

| Tab | Shows |
|---|---|
| **Overview** | Source freshness; top movers (position change against last month); quick wins; top 10 rising; brand (brand-term positions, sitelinks from the live check, AI referrals from GA4, unlinked mentions list) |
| **Keywords** | The keyword record. Filters: priority, topic, page type, role, language, status. Entities appear as one aggregate row plus the top 20 movers |
| **Rising** | Rising terms with sources, growth, first seen, matching page, action, status |
| **Pages** | Owning pages, conflicts, GA4 site search and AI referrals, page quality (Clarity + PageSpeed), Clarity notes |
| **Sourcing** | Per sourcing keyword: items fetched/published (30 days) and their GSC. Flags keywords that bring items but no traffic, and demand terms not yet sourced. **Hidden without AW Sourcing**, and empty until section 11 lands |
| **Check list** | The editable monthly list, the bookmarklet and token, live-check progress |
| **Settings** | Imports, paste forms, keys status (constants set or not, never the value), delete data |

## 9. Privacy

**Site search and Directory searches.**
- Stored as **daily aggregate counts per term** only.
- No IP addresses, user agents, user IDs, cookies or raw request lines.
- Terms that look like personal data are dropped before counting: phone numbers, emails, long digit strings. Directory PIN codes are kept only as a 6-digit area code count, never linked to anything else.

**Clarity.** Per-URL and per-device daily totals only (section 5.2).

**YouTube and Reddit.** Aggregated terms, titles and counts only (section 5.3).

**Bookmarklet.** Sends only queries, positions, domains and People-also-ask questions. Nothing is sent to GA4.

## 10. Monthly snapshot

- The site builds `search-insights-YYYY-MM.csv`:
  - the columns of `rankings-2026-10-21.csv`;
  - plus demand, priority, status and action;
  - offered as a Download button.
- It also builds the regenerated `keyword-map.md` from the keywords table.
- **A session commits both** to `docs/seo/` each month, straight onto `wordpress-dev` (docs: no PR, owner 24 Oct 2026). Nothing commits automatically.

## 11. Dependencies

- **AW Sourcing work in progress.** The "sourcing performance" part waits for it to ship first:
  - `aw_feed` and `aw_ingested_at` on items;
  - per-page GSC;
  - Sourcing suggestions.

  Search Insights reads that data and adds no storage of its own for it.
- **Site Kit** must stay connected (Search Console, GA4 and PageSpeed modules). If the Site Kit owner disconnects, the Google sources show an error and keep their last data.
- **Rank Math** must be active. aw-seo-extras loads only with Rank Math.
- **Owner setup**, each only when its phase starts:
  - Clarity token;
  - PSI key (only if the Site Kit module is off);
  - YouTube API key;
  - Reddit app;
  - Bing Webmaster sign-in;
  - Ahrefs Webmaster Tools verification;
  - free Keyword Planner account.

## 12. Build phases and workflow

| Phase | Builds | Dry run |
|---|---|---|
| 1 | Rename and old-slug redirect; tables; one-time seed from keyword-map.md + rankings CSV; Keywords tab; Search Console via the existing Site Kit path; Overview; Site Health test; retention rollup | today's data; row counts; what each tab shows |
| 2 | Site Kit GA4 + PageSpeed; Clarity + Clarity notes; WordPress and Directory site search; Pages tab; redirected URLs counted as their target. (The phase 1 leftovers were built separately, ahead of this phase.) | 1 day of each source |
| 3 | Workers `search-insights` job (Trends RSS, Wikipedia, GDELT, autocomplete, Trends related/interest) + REST route; Rising tab | last 7 days; top 10 rising terms |
| 4 | Check list, bookmarklet live check, imports and paste forms | 5 keywords |
| 5 | Sourcing tab (shipped 24 Oct, PR #32); Hindi and Hinglish (grouping under English parents, Hindi autocomplete); YouTube, Reddit, Bing (after sign-in); monthly snapshot + keyword-map regeneration | 1 month |

**Workflow.**
- This plan was approved by the owner (PR #1); phases follow below.
- Each phase gets its own branch: WordPress parts from `origin/wordpress-dev`, workers parts from `origin/main` in animal-writes-workers.
- Each phase gets its own PR: what changed, the dry-run results, screenshots.
- No direct pushes and no auto-merge. Merge only after the owner approves.
- Shipping is a separate, manual `aw-ship.sh` step after the merge.

## 13. Risks and overrides

**Risks.**
- **Trends from GitHub runners** is unofficial and often refused (429). The manual paste form is the real fallback.
- **The bookmarklet POST may be blocked.** Google's content security policy on google.com may block the bookmarklet's POST to our site. If so, in phase 4 the bookmarklet either:
  - opens a Search Insights window and hands over the results by `postMessage`; or
  - copies the results as JSON for the owner to paste.

  Fetched result pages may also differ from what the browser renders; the dry run checks this on 5 keywords.
- **Clarity** returns only the last 1–3 days. A missed day is lost, and the screen shows the gap.
- **GSC hides most queries.** It shows about 26% of impressions as named queries (21 Oct), so keyword-level GSC totals undercount.

**Overrides between the briefs** (the latest wins):
- GSC and GA4 come **through Site Kit** (brief 6, unified brief), not a workers service account (brief 3) or the GA4 Data API from workers (brief 5).
- The brief asks for `rest_do_request` as the Site Kit user. This plan keeps the module call `aw_sd_sitekit()` already uses. It is the same Site Kit layer, and it is proven on live.
- The keyword map is seeded once; the site table is the source of truth from then on (owner review).

## 14. Brief → section

| Brief (24 Oct) | Where it is covered |
|---|---|
| 1. Rename + six sections (Keywords, Demand, Rankings, Gaps, Sourcing, Brand) | 2; 8 (Keywords, Overview/brand, Rising for demand, Sourcing); 7 (gaps) |
| 2. Research functions: check list, bookmarklet, autocomplete, Trends, GSC, snapshot | 5.4, 5.3, 5.1, 10 |
| 3. Free data only: scheduled GSC, live check, autocomplete, Trends, PSI | 5.1 (GSC via Site Kit, override in 13), 5.4, 5.3, 5.1 PSI |
| 4. Trending terms: Trends RSS, related, autocomplete diff, new GSC queries, Sourcing spikes, Wikipedia | 5.3, 5.5, 6 (weekly new GSC queries), 3 (`rising`) |
| 5. More free sources: Bing, GA4, site logs, GDELT, YouTube, Reddit, Keyword Planner, Ahrefs WMT, data.gov.in | 5.3, 5.1 (GA4 via Site Kit), 5.5, 5.4, 9 |
| 6. Site Kit + Clarity auto-fetch | 5.1, 5.2 |
| 7. Unified keyword record; plan first | 3, 4, 7, 8; this document |
| Owner review rounds (24 Oct) | Fixed decisions; 2 (seed once); 3 (retention, Hinglish, uninstall); 5 (limits, failure rule); 5.2 (Clarity notes); 9; 10 (session commits); 11; 12 (workflow); 8 (admin UI) |
