> Status as of 24 Nov 2026 (checked against git): Partly done. One quick-win rule, one day-replace writer, one CLI root are in; 7-tab Settings and tool imports not confirmed.

# Plan (3 Nov): what is left. AW SEO Plus + Search Insights only. No new screens. Nothing in Sourcing.

## Context
The owner wants a strict line between Search Insights (aw-seo-extras), Sourcing (aw-sourcing) and Classification (animal-writes-core), AW SEO Plus settings that aren't one tick-box tab per module, labelled referrers, one-line "keyword" definitions, free research tools feeding the existing tabs (no email, no new screen), and every function audited. Only work not yet on wordpress-dev is listed (checked in the code 3 Nov 17:22 IST).

## How work ships (owner, 1 Nov: no PRs unless asked)
- One branch per part from origin/wordpress-dev. Before pushing: `php -l`, PHPCS, the code-review skill, the plugin audit checklist; fix findings on the branch.
- Push to wordpress-dev once per part, only when no other Deploy check is running; ship with `tools/aw-ship.sh` only on the owner's "deploy". Never push to `wordpress`.
- Building and checking go to subagents; I say which part goes to which before sending it.
- Copy (help text, definitions, box titles): pasted in chat first, built only after the owner's yes on that text.
- Before each part I re-check wordpress-dev and keep out of files another session is changing.

## Structure after the change
```
AW SEO Plus
├── Overview          every feature, where it is, live status (unchanged)
├── Search Insights   READS AND SUGGESTS; changes nothing on the site
│   ├── Overview      sources, priorities, quick wins, movers, Rising this week, top referrers,
│   │                 AI referrals, site searches, crawler access, Clarity, 404s, content gaps
│   ├── Keywords      what people search for (Search Console, Bing, Keyword Planner, Ubersuggest, OpenSEO)
│   ├── Rising        rising topics → page → action (Trends, Wikipedia, autocomplete, vidIQ)
│   ├── Pages         one row per page: search, GA4, Clarity, speed, Bing, index status
│   ├── Links         who links to us (+ Google Alerts mentions)
│   ├── Clarity       (unchanged)
│   ├── Server        what the server sees: bots, 4xx/5xx, PHP errors, visitors (unchanged)
│   ├── Check list    monthly live check + imports
│   ├── Sourcing keywords  (added by aw-sourcing; belongs under Sourcing, noted for that area)
│   └── Settings      Search Insights numbers (moved here), seeds list, run now, imports, delete data
├── Settings          CHANGES HOW PAGES APPEAR in Google, social and AI tools: 7 tabs
│   Modules · Titles and descriptions · Indexing · Schema and FAQ ·
│   Share images and Open Graph · Focus keywords and content analysis ·
│   AI agents (llms.txt + Markdown for agents)
└── FAQ               FAQ box content (unchanged)
```
- Keys (Animal Writes → Keys) hold every service key.
- **News spikes leave Search Insights** (owner): they count our own News volume, which is Sourcing's data. After this, Search Insights reads nothing ingestion brings in.
- The Sourcing keywords tab (added by aw-sourcing) belongs under Sourcing: noted in pending.md for that area, not moved by me.
- **Referrer** = a site that sends visitors (GA4); never called a "source".

**Where data comes from:** server APIs (Search Console, GA4, PageSpeed via Site Kit; Clarity; Bing; CrUX; URL Inspection; Rank Math) · scheduled Claude tasks with connectors (Ubersuggest, vidIQ, OpenSEO: no free server API) · workers (Trends, Wikipedia, autocomplete, outside crawler check) · pastes (Keyword Planner, Trends, government figures, Google Alerts feed URLs). Access is now by the `aw_worker` capability, so a separate sender for Search Insights only needs its own user with that capability (or a narrower `aw_si_sender` one). The owner creates the user; I never enter credentials.

## Order (each its own branch; push + ship per the rules above)
1. **Part 1 `seo-settings-tidy`**
   - Settings 18 → 7 tabs: UI grouping only, same option `aw_seo_settings` and constants (`settings.php`, `aw_seo_modules()`); Modules holds every on/off box.
   - Search Insights numbers move to Search Insights → Settings.
   - `aw_si_referrer_label()`: one named row per referrer (Instagram, not "ig" + "l.instagram.com") with our media favicon via `aw_favicon_url()` (kits-core attribution.php), else a dashicon (pattern in class-aw-sourcing-publishers-table.php:316). Used in Top referrers, the AI box, the Pages column.
   - One-line keyword definitions (text to the owner first): Keywords = what people search for; Focus keyword = the phrase one page targets in Rank Math; Sourcing keywords = what the workers fetch; categories/tags = labels. Same table in `docs/plugin-map.md`, plus the tree above.
   - The Dashboard SEO box (dashboard.php) uses the same referrer names if it shows referrers.
2. **Part 5: AW SEO Plus fixes** (no dead code found)
   - Sitemap exclusion of noindex items moves into robots.php (always on); `filtered-sitemap.xml` under the sitemap switch.
   - Thin-page help text matches the code (only empty pages noindex): text to the owner first.
   - Reset `aw_seo_og_swap` per image; schema images through the no-AVIF helper.
   - Health check unscheduled when off; `directory-sitemap.xml` added to its checks.
   - opengraph.php header comment fixed; trash-pause uses Rank Math's own call.
   - Schema: Event with no place → CreativeWork (campaigns and country-only opportunities); drop SearchAction and HowTo. **Not touched:** JobPosting, the FAQ.
   - One helper each for the four duplicated bits; the two sitemap provider classes merged.
   - Speed: hub schema and filtered counts cached; filtered sitemap rebuilt daily; `aw_seo_settings` autoloaded; no writes during a page view.
   - Rank Math overlaps (ALT/Title switch, author archives) listed only; changed on the owner's yes.
3. **Part 4: Search Insights fixes**
   - One quick-win rule `aw_si_view_sql()` (built on the stored quick-win flag); one query → page match with redirects; Rising uses `aw_si_decide()`; one day-replace writer; Pages tab calls `aw_si_page_metrics()`; dry run calls real helpers; crawler access once in Site Health; one site-search row.
   - Remove News spikes from Rising (cron, "In the news" label, signal); the 13 Noted "in the news" rows stay as history.
   - Show stored-but-hidden data: PageSpeed LCP/CLS and Bing per page on Pages; Bing impressions on Keywords; "Rising this week" box; 12-week crawler trend; empty snapshot columns dropped.
   - Daily-figures setting used in all 5 places; test-day exclusion for AI referrals too; Trends imports replace.
   - Delete data clears check list, token, caches; safe focus-link rewrite; checked writes logged; seed "file missing" and compute logged in the run log; truncation notice fixed; entity flag cleared on unpublish.
   - One CLI root `wp aw-si …` (old names as aliases); prune Bing week rows and old Trends rows; links and Server-tab counts kept for good (owner).
   - Separate Search Insights sender: a capability check on the import route only (the owner creates the user).
4. **Part 2: imports for Ubersuggest, vidIQ, OpenSEO** into the existing Keywords/Rising tabs (`imports.php`, `compute.php`, `rising.php`), own columns, never overwriting Search Console / Keyword Planner / Bing figures; seeds list on Search Insights → Settings. **No email.**
5. **Part 3: remaining free sources**
   - Search Console Discover + Google News (`searchType`), daily.
   - URL Inspection ≤ 2,000 a day, not-indexed and never-checked first; index status on Pages, "not indexed by reason" on Overview.
   - CrUX monthly, site + the PageSpeed pages (key pages and campaign pages); free key pasted on the Keys page by the owner. First I check what the Server tab's daily speed check already reads, and skip CrUX if it covers it.
   - Bing crawl stats/errors weekly, keyword stats monthly.
   - Google Alerts RSS (owner pastes 2 feed URLs on Keys) → Links as "mention".
   - Later, on the owner's word: AdSense, YouTube Data API, Reddit, Bluesky.
6. **The routine** (scheduled Claude tasks, after Part 2 is live; check each connector's auth and the vidIQ balance first)

| When | Tool | What | Limit |
|---|---|---|---|
| Daily 07:30 IST | Ubersuggest | 3 keyword reports, India, seeds rotating | 3 a day |
| Monday 08:00 IST | vidIQ | outlier search, India | ≤ 10 in 28 days |
| 1st of month | OpenSEO | 12-month trend, 2 themes | ≤ ~110 of 500 credits |
| 1st of month | Ubersuggest | overview refresh, top 25 | 10 a month |

A refused call stops that tool for the day. Each run is logged in the run log.

## Verification (no local WP; read-only on live)
- Per part: `php -l` + PHPCS on changed files, code-review skill, plugin audit checklist, Deploy check green.
- After ship (read-only): Settings shows 7 tabs and every saved value unchanged (compare `aw_seo_settings` before/after); Search Insights → Settings shows the moved numbers; Top referrers / AI box / Pages show names and icons; Rising has no "In the news" rows added; Schema.org validator 0 errors on a campaign, an opportunity, a guide; sitemap still excludes noindex items with the sitemap module off.
- docs/pending.md updated by title after each part.
