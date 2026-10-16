# SEO plan validation: 15 Oct 2026

**Plan checked:** `~/.claude/plans/the-7-hub-pages-federated-shore.md` (10 phases, Ground rules, Verification).

**Method:** this was a read-only check. Each row below comes from a live check made on 15 Oct 2026, between about 14:30 and 15:30 IST. Commit messages were not taken as proof. The sources were:
- Rank Math options on the server, read with `wp eval-file`.
- SQL counts on the live database.
- Live HTML fetched with curl for 31 URLs, covering every page type.
- A crawl of internal links: 230 seed pages, 1,128 unique internal links.
- Event tests in a real browser (the built-in browser pane).
- Rank Math 1.0.280's own source on the server.

**Changes since the plan:**
- `aw_topic` is now core `category`.
- The label taxonomies are now `post_tag`.
- Resources are the core `post` type, so `aw_guide` has 0 items.
- Phase 7 was held on purpose, so it is marked Not started.

**Another session was writing to the site during this check.** For example, News focus keywords went from 992 to 1,006 between two of my queries. Counts are as of the moment each one was read.

## Summary at the first check (15 Oct)

| Status | Rows |
|---|---|
| Done | 17 |
| Partly | 18 |
| Not done | 4 |
| Not started | 9 (Phase 7: 3, Phase 8: 2, Phase 9: 1, Phase 10: 3) |
| Not checked | 2 (outside what this check could reach) |
| **Total** | **50** |

There is no evidence that Phases 8, 9 and 10 were ever run:
- no commits for them;
- no `~/backups/seo-phase7…10` dumps (phases 1 to 6 each have one);
- no reports in `docs/seo/`;
- no final report artifact.

## Status at close (16 Oct 2026, 07:30 IST)

Phase 10 re-checked every row live, using the same methods as the original check:
- a 28-page head snapshot compared with Phase 7's;
- `wp eval-file` and SQL counts on the server;
- curl of the 31 head URLs, with H1, alt and JSON-LD parsed;
- sitemap, llms.txt and robots.txt fetched;
- a 251-page sample crawl, with 1,328 URLs checked;
- the Rank Math 404 log.

Each row's evidence is in the "Status at close" column of the tables in section 1.

| Status at close | Rows |
|---|---|
| Done | 47 |
| Partly | 1 (6.1: the Rich Results Test was blocked by a CAPTCHA, so it is an owner action) |
| Not done | 0 |
| Owner decision | 1 (5.2: whether a separate citation-ready block is wanted) |
| Not checked | 1 (G3: design tokens, outside SEO) |
| **Total** | **50** |

### Field coverage per type (published, 16 Oct 07:20 IST)

| Type | Items | SEO title | Description | Focus keyword | Keyword in SEO title (as Rank Math reads it) | Score stored | Score average | Over 80 | 50 or less | Featured image |
|---|---|---|---|---|---|---|---|---|---|---|
| News | 1,024 | 1,024 | 1,024 | 1,024 | 1,016 (was 263/1,006) | 1,024 | 81.9 | 777 | 12 | 232 |
| Library | 84 (85 in sitemap at crawl time) | 84 | 84 | 84 | 84/85 (was 27) | 84 | 80.8 | 54 | 0 | 76 |
| Resources | 162 | 162 | 162 | 162 | 156 (was 37/159) | 162 | 77.5 | 89 | 6 | 159 |
| Listings | 5,139 | 5,139 | 5,139 | 5,139 | 5,137 | 5,139 | 79.1 | 544 | 0 | 14 |
| Opportunities | 18 | 18 | 18 | 18 | 13 | 18 | 63.1 | 8 | 5 | 4 |
| Campaigns | 1 | 1 | 1 | 1 | 1 | 1 | 77.0 | 0 | 0 | 1 |
| Pages | 22 | 22 | 22 | 22 | 21 (was 14) | 22 | 60.0 | 0 | 2 | 8 |
| Media & PR | 43 | 43 | 43 | 43 | — | not scored (no page of their own: they 301 to /media/) | — | — | — | 15 |
| Categories | 18 | 18 | 18 | 18 | — | — | — | — | — | — |
| Tags | 50 | 50 | 50 | 50 (was 0) | — | — | — | — | — | — |

Scores were before: none stored on any item (15 Oct). The scores come from Rank Math's own analyzer (its bundled `analyzer.js`, run headless on the values its Update SEO Scores tool builds, with the rendered body). The 12 red News items and the 5 opportunities with the keyword outside the title are the workers' repick list. The repick runs with `AW_SEO_REPICK=1` once the AI keys work again.

### The fix list (section 3) at close

| # | Item | At close |
|---|---|---|
| 1 | /claim/ noindex | **Reversed by the owner:** stays indexed and in the sitemap (live: index, in the page sitemap) |
| 2 | Sample listing links | Done: 0 links on /directory/ and /tag/abc-centre/ |
| 3 | Redirects | Done: topic/stray-animals → /category/community-dogs/. The two trashed stories have no kept copy, so they stay 404 |
| 4 | ad-slot.js URL | Done: no 404 hits since 15 Oct 15:03 |
| 5 | Nagaur facts line | Done |
| 6 | 2-hop redirects | `?author=` is Done (1 hop). `http://www.` is **owner (hPanel)** |
| 7 | Rendered body and scores | Done. 97 re-scored today, average 46.4 → 84.3 |
| 8 | Keyword inside SEO title | Done. The hyphen and apostrophe gap was closed today (53 News items) |
| 9 | Categories page keyword | Done |
| 10 | JobPosting fields | Done where the data exists. Rich Results Test: **owner** (CAPTCHA) |
| 11 | Dead News source URLs | Done. 1,024 checked: 0 dead, 127 blocked (403, not dead), 7 unknown; `aw_link_checked` stored |
| 12 | Alt text | Done: 0 featured images and 5 of 949 media images without alt |
| 13 | Tag keywords | Done: 50/50 |
| 14 | Rank Math templates and stale options | Done: `%aw_title%` / `%aw_description%` on 7 types; 79 stale keys removed |
| 15 | llms.txt counts and the AI-crawler decision | Done: live counts with the date; AI crawlers allowed (recorded in robots.txt) |
| 16 | G1 core title filter and `kits_sitemap_skip` | Done: moved to aw-seo-extras (titles unchanged on 9 URLs); `kits_sitemap_skip` removed |
| 17 | Phase 7 | Done (11f434c, 5341d83), plus today's after-check, `?q=`, `?state=` and `?publisher=` rules, and the Ashoknagar link |
| 18 | Phase 8 | Done |
| 19 | Phase 9 | Done; the host settings are with the owner |
| 20 | Events | Done (`docs/analytics/`) |
| 21 | Final pass and report | Done |

### Owner actions

1. **hPanel and CDN:**
   - send `http://www.animalwrites.in` straight to `https://animalwrites.in/` (today it takes 2 hops);
   - turn on HTML edge caching (every page is `x-hcdn-cache-status: MISS`).
2. **Page caching and LiteSpeed purge scope:** take `create_term` and `edit_terms` out of "Purge all". Every new tag or category from the workers clears the whole cache.
3. **Security headers:** `X-Content-Type-Options`, `Referrer-Policy` and a basic `Permissions-Policy` (a host or .htaccess setting).
4. **Ads above the fold on phones:** decide whether the first ad slot stays above the first card on phones. It weighs on mobile LCP and TBT (site-health report).
5. **The 7 owner-typed guide titles:** they are kept as typed. 6 Resources items have a focus keyword that is not in their typed title (7602, 7603, 9028, 9029, 128, 129). Change either the title or the keyword.
6. **Hyphen decision:** still open from the title rules. Note that Rank Math reads a hyphenated word as one word, so a keyword must keep the title's hyphen ("animal-free").
7. **Gaushalas capitalisation** on the Directory band (the hub description says "gaushalas", other places say "Gaushalas"): pick one.
8. **ACF and Web Stories:** keep or deactivate them. Web Stories has 0 published stories, and `/web-stories/` is noindex while it stays empty.
9. **Remove the 10 broken AI keys** from the workers' secrets, so the keyword repick and summary runs use only working providers.
10. **GTM and GA4:** import `docs/gtm/animalwrites-container.json` into GTM-TB3FCFQB and publish it. In GA4 (G-4P5YMJ04RL), mark the key events and register the custom dimensions, following `docs/analytics/`.
11. **The 938-summary rewrite run** (`short-2026-10-15/rewrite-later.json`).
12. **The 3 items left in Inbox → To review.**
13. **Rich Results Test:** one URL per type (it shows a CAPTCHA to automated runs).
14. **News item 13737** is published with the title "peta-india": rename it or unpublish it.

## 1. Plan bullets

### Ground rules

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| G1 | Rank Math first; `aw-seo-extras` only for what Rank Math can't do; nothing SEO-related in themes, kits-core or animal-writes-core | Partly | The SEO output on the 31 sampled URLs comes from Rank Math plus aw-seo-extras. A grep of the core plugins found one leftover: `plugins/animal-writes-core/includes/directory.php:310` still adds a `document_title_parts` title filter for Directory views. kits-core still has `kits_sitemap_skip` (preview.php:186), which does nothing now. | Remove the Directory title filter from core, or move it to aw-seo-extras. Drop the unused `kits_sitemap_skip`. | **Done.** The Directory `document_title_parts` filter now lives in aw-seo-extras (`titles.php`, commit 4b0e189); it is still needed for name searches inside a place (`/directory/rajasthan/?q=cow`), which no Rank Math title rule names. Titles on 9 Directory URLs were identical before and after. `kits_sitemap_skip` is gone: 0 references; the sitemap checks `kits_preview_only_ids()` itself. |
| G2 | No invented facts | Partly | Generated text is built from counts, but one line is wrong. On `/directory/rajasthan/nagaur/gaushala/`, the facts line reads: "130 Gaushalas listed in Nagaur. Rajasthan has 873 Gaushalas in 31 districts; the most in Jodhpur (118)…". Nagaur itself has the most, so the sentence is false. Separately, the hub copy in llms.txt says "995 stories" while the live hub says 1,006. | Leave the current place out of the ranking, or name it first. Refresh the llms.txt hub copies. | **Done.** Nagaur now reads "the most in Nagaur (130) and Jodhpur (118)". The llms.txt hub lines carry live counts with the date: News "1,024 stories on 16 Oct 2026", the same as the hub. |
| G3 | Design tokens; `aw_date()`; 16:9 card frame | Not checked | Outside an SEO check. | — | Not checked (outside SEO). |
| G4 | mysqldump to `~/backups/` before bulk changes | Done | `~/backups/seo-phase1…phase6-*.sql.gz` dated 14–15 Oct, plus JSON copies of the options from before Phase 4 and before the web-story change. | No dumps for phases 7 to 10, because those phases never ran. | **Done.** `~/backups/seo-phase7-…`, `seo-phase8-9-…` and `seo-phase10-2026-10-15.sql.gz` (5.5 MB, "Dump completed"). There are also JSON copies of the Rank Math titles, sitemap and general options from before Phase 10, and a log of the keyword fixes (`kwfix-phase10-2026-10-16.json`). |
| G5 | No deleting; trash only | Not checked | The removed News items I found are in `trash` (IDs 13292 and 10789), not deleted. A full audit isn't possible from the database. | — | **Done** for this pass. Nothing was deleted. The only removals are 79 Rank Math option keys for types and taxonomies that no longer exist; their JSON copy is kept. |
| G6 | Deploy with `aw-ship.sh`, explicit git paths, Wugweb trailer | Done | All 7 SEO commits I checked carry `Co-Authored-By: Wugweb`. The deploy route can't be checked after the fact. | — | **Done.** Phase 10 commits 4b0e189, 74ed596 and 3c13737 (site) and 9bd9520 (workers) all carry the Wugweb trailer. aw-ship.sh reported "deployed 3c13737". |
| G7 | 28-page head snapshot after every phase | Partly | The only snapshot files I found are before/after pairs in a scratchpad `rmschema/` folder, which look like Phase 6. There are no snapshots in the repo or on the server for the other phases. | Keep each phase's snapshot diff, for example in `docs/seo/`. | **Done.** A 28-page head snapshot was taken on 16 Oct and compared with the Phase 7 after-snapshot. All 28 pages return 200, with 0 differences in title, description, robots, canonical, schema types or breadcrumbs. Both snapshots are in `docs/seo/snapshots/`. |

### Phase 1: audit, noindex bug, events

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 1.1 | Full repo and live audit | Partly | Fixes landed (rows 1.2 to 1.5). No audit report exists in `docs/seo/`, `docs/` or the artifacts. | The audit write-up. | **Done.** This document, `site-health-2026-10-15.md`, `content-strategy-2026-10-15.md` and the final report together make the audit record. |
| 1.2 | Fix the global `noarchive` | Done | `robots_global = ["index"]`. Every indexable sample outputs `follow, index, max-snippet:-1, max-video-preview:-1, max-image-preview:large`, with no `noarchive`. | — | **Done.** `robots_global = ["index"]`. 26 of the 31 head URLs output `follow, index, max-snippet:-1…`, with no `noarchive`. |
| 1.3 | Re-check noindex rules: search, thin district×type pages, closed opportunities, claim/report/thank-you, 4 manual items | Partly | These are noindex as intended: search, report, thank-you, remove-my-data, the sample listing, closed opportunity 7616, an empty `/web-stories/`, and 34 thin tags. The thin district×type threshold is 1, so none are noindexed, which is the owner's 10 Oct decision. **`/claim/` is indexable**: it outputs `follow, index`, `rank_math_robots=["index"]`, yet it is excluded from the sitemap (`exclude_posts=12587`). | Set `/claim/` to noindex. | **Done (owner reversed one part).** `/claim/` stays indexed and is in the page sitemap, by the owner's decision. These are noindex, follow: search, report, thank-you, `/web-stories/` and closed opportunity 7616. `/remove-my-data/` now 301s to `/contact/?type=remove-data`. |
| 1.4 | Watchdog for sitemap_index.xml and llms.txt | Done | `aw_seo_endpoints_watchdog` is scheduled hourly (next run 15:33). Last run 14:35: no problems, no flush needed. The code in `aw-seo-extras/includes/health.php` flushes rewrite rules once, then emails and shows a dashboard notice. | — | **Done.** The watchdog is scheduled (next run 16 Oct 07:33). |
| 1.5 | Event audit, mapping to the Phase-10 list, GTM import file | Done | All 12 mapped events are in `kit.js`. `docs/gtm/animalwrites-container.json` has 19 tags, 18 triggers and 33 variables. The **live** GTM-TB3FCFQB container includes every event name (form_submit_success, filter_apply, card_click, 404_view, scroll_depth and the rest) and GA4 G-4P5YMJ04RL. A `page_context` push with page type, section, category, state and district fires on every page. | Browser test coverage: see V3. | **Done** (unchanged; events in `docs/analytics/`). |

### Phase 2: broken links and on-page

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 2.1 | Crawl the whole site, including the Directory | Partly | No crawl output was kept. My sample crawl (230 seeds: News, Library, Resources, 40 listings, 50 Directory place pages, all categories and tags, opportunities, hubs) checked 1,128 internal links: 1,127 return 200 and **1 returns 404**. | A full crawl record. | **Done (sample).** The after-check covered 251 pages: the 9 hubs, 40 random items per type, all 16 opportunities, the campaign, 30 Directory places, 18 categories, 16 tags and the 4 earlier orphans. All 251 return 200. They hold 1,209 unique internal links; 1,328 URLs were checked in all. |
| 2.2 | Repair broken internal links | Partly | The one broken link goes to `/directory/org/wugweb-sample-listing/`, which returns 404. It is linked from **/directory/** and **/tag/abc-centre/**. The post (ID 9003) is published and noindexed, but its page returns 404. | Remove it from the lists. Ask the owner before trashing or unpublishing it. | **Done.** `wugweb-sample-listing` has 0 links on `/directory/` and on `/tag/abc-centre/`. It still gets 404 hits from outside (21 in the monitor). |
| 2.3 | Rank Math redirects for dead URLs in the 404 monitor | Partly | 265 active redirects, 148 of them created since 14 Oct, and no chains among them. These are still 404 today:<br>- `news/pramod-muthalik-…` (7 hits, last at 09:20)<br>- `news/vegan-foods-…-2027` (hit at 12:12); both are trashed duplicates<br>- `topic/stray-animals`, which **redirects to `/category/stray-animals/`, itself a 404**<br>- `directory/org/wugweb-sample-listing` (12 hits)<br>- `wp-content/plugins/home/u544169507/…/kits-core/assets/js/ad-slot.js` (15 hits, last at 14:45): a broken editor script URL in `kits-core/includes/ads.php:176` (`plugins_url` with a symlinked path) | Redirect the trashed duplicates to the copy that was kept. Point `topic/stray-animals` at a category that exists. Fix the `ad-slot.js` URL. | **Done, with one difference.** There are 530 active redirects. `topic/stray-animals` now goes to `/category/community-dogs/` (200). The broken `ad-slot.js` editor URL has had no hits since 15 Oct 15:03. The two "trashed duplicates" have no kept copy: every copy of both stories is in Trash, including 10775. They stay 404, which is correct for removed content. A redirect to /news/ would be a soft 404. |
| 2.4 | Mark dead outbound links: listings `aw_link_dead`, News source URLs | Partly | 15 listings have `aw_link_dead`. **News has no dead-source marking.** There is no meta key for it; the only `aw_source_*` checks are on images. | Check and mark News source URLs. | **Done.** All 1,024 News source URLs were checked politely: one request per host at a time, 3 s apart, cached, with backoff on 429/503. Results: 890 return 200, **0 are dead** (404, 410 or no such host), 127 are blocked by bot protection (403; PETA India 62, NDTV 24 …) and 7 are unknown (5 SSL errors, 1 timeout, 1 308). Each item now has `aw_link_checked` ("{date} {verdict} {code}"). `aw_link_dead` is set only for dead links, so none were set. A 403 is never counted as dead. |
| 2.5 | On-page fixes per type: H1, heading order, alt, title and description length | Partly | Exactly 1 H1 on all 28 pages checked, with no skipped heading levels. Title length (rendered Rank Math title) and description length are in the table below. **Alt text:** 361 of 941 images in the media library have none, and 23 of 502 featured images on published items have none. Card pictures on category pages use `alt=""` (decorative, title link next to them), which is acceptable. | Alt text for the 23 featured images and the media library. Decide on the 249 long listing titles. | **Done.** Featured images without alt: **0** (was 23). Media library images without alt: **5 of 949** (was 361 of 941). 1 H1 on all 31 head URLs. The long listing titles are kept on purpose (names are never cut). |

**Title and description lengths per type:**

| Type | Titles over 60 | Titles under 25 | Descriptions over 160 | Descriptions under 70 | Duplicate titles |
|---|---|---|---|---|---|
| News | 0 | 0 | 0 | 1 | 0 |
| Library | 0 | 0 | 0 | 6 | 1 |
| Resources | 0 | 0 | 0 | 0 | 0 |
| Listings | **249** (names are never cut, by design) | 5 | 0 | 58 | **55** |
| Opportunities | **6** | 0 | 0 | 1 | 0 |
| Pages | 0 | 2 | 0 | 1 | 0 |

### Phase 3: AEO/GEO, keywords, focus keyword job

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 3.1 | Focus keyword filled per type | Partly | Published items with a focus keyword: News 1,006/1,006, Library 84/84, Resources 159/159, Listings 5,139/5,139, Opportunities 18/18, Campaign 1/1, Pages 22/22, Categories 18/18. **Tags: 0/50** have one (they have a Rank Math title and description, 50/50). | Tag focus keywords. | **Done.** Tags have focus keywords on 50/50 (was 0/50). Categories 18/18. Every published item type is at 100%. |
| 3.2 | Secondary keywords where useful | Done | The 7 hubs, Home, About and Media carry 2 to 5 comma-separated keywords. | — | **Done** (unchanged). |
| 3.3 | Search intent and schema/article type per type | Done | Rank Math defaults by type: News `article`/`NewsArticle`, Library `Article`, Resources `article`, Opportunities `jobposting`, Campaigns `event`, Listings `off` (aw-seo-extras outputs NGO, VeterinaryCare and so on). The live JSON-LD matches these. | — | **Done** (unchanged). |
| 3.4 | Rank Math FAQ block on guides where the answers exist | Done | 16 of 16 non-research Resources guides contain `rank-math/faq-block`. The guides checked output `WebPage+FAQPage` with Question and Answer. No Page has an FAQ block; the plan said "key pages", so that is optional. | — | **Done** (unchanged). |
| 3.5 | Finish the 43 missing focus keywords; tighten `focus_keywords.py` | Done | 0 missing. Workers commits `ddaa79b` and `cb23c77` added: 2 to 4 words, no connector at the end, not only generic words, at least one word from the title or text. **Weak point:** sharing a single word is enough to pass, so the keyword is inside the SEO title for only 263 of 1,006 News items (26%), 27/84 Library and 37/159 Resources. That costs 36 of Rank Math's 100 points (see section 2). | Require the whole phrase to appear in the SEO title. | **Done.** The keyword now stands in the SEO title the way Rank Math reads it: News **1,016/1,024** (was 263/1,006), Library 84/85 (was 27/84), Resources 156/162 (was 37/159), Pages 21/22 (was 14/22), Opportunities 13/18 (was 13/18). 53 News keywords had lost a hyphen or apostrophe ("animal free" against "Animal-free"). They were rewritten to the title's own words, and the workers and site checks now keep these characters (workers 9bd9520, site 74ed596). |
| 3.6 | Improve Rank Math title and description templates per type and taxonomy | Partly | Taxonomy templates were improved, for example category `%term% in India %sep% %sitename%` and the opportunity-type template. **The post-type templates are still Rank Math's defaults** (`%title% %sep% %sitename%` / `%excerpt%`) for every type. Every item stores its own values, so this is hidden, but a new item before the daily fill gets the default. There are also leftover options for the removed types (`pt_aw_guide_*`, `tax_aw_listing_type_sitemap=1`). | Templates per post type, and remove the stale options. | **Done.** All 7 item types use `%aw_title%` / `%aw_description%`, two Rank Math variables registered in aw-seo-extras. They return the live title and description rules, so the previews show real text, not a bare variable. This was checked with Rank Math's own `replace_vars` on one item per type. 79 stale option keys were removed (65 titles, 14 sitemap: `pt_aw_guide_*`, `tax_aw_listing_type_sitemap` and the city, listing-type, source-type, opportunity-type, Library-kind, service and resource-type taxonomies); 0 are left. |
| 3.7 | Store the 7 hub titles and descriptions in Rank Math | Done | News, Library, Resources, Directory, Opportunities, Campaigns and Media each have `rank_math_title`, `rank_math_description` and `_aw_seo_auto`. The live titles match the stored ones. | — | **Done** (unchanged). |

### Phase 4: programmatic SEO, local, organisation

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 4.1 | Directory state, district and type pages and category pages: intro, title and description from live data; thin-page rules stay | Partly | Titles, descriptions and a facts line come from live counts, for example "Rajasthan has 1,035 listings in 32 districts…" and "In this category: 202 news stories…". The intro sentence above the facts line is the same template on every district. The Nagaur ranking error is in G2. | Fix the facts-line ranking. | **Done.** The facts line names the current place in its ranking (see G2). A district whose listings are all one type now links that type's page ("Ashoknagar has 1 listing: 1 animal welfare organisation"). |
| 4.2 | Rank Math Local SEO and Knowledge Graph | Done | Type `company`, name "Animal Writes", logo (ID 162), email, 3 `sameAs` links (Facebook, Instagram, x.com), About page 58, Contact page 61. No phone and no address; no address policy is written down. | Record the address policy. | **Done**, as before. No address policy is written down. |
| 4.3 | One publisher Organization; Rank Math carries the same values | Done | The same `#organization` node on Home and News: alternateName, publishingPrinciples, correctionsPolicy, knowsAbout (18 categories), the same `sameAs`. | — | **Done** (unchanged). |

### Phase 5: AI visibility

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 5.1 | llms.txt contents; robots for AI crawlers | Partly | llms.txt returns 200 with 282 lines in these sections: Pages, Resources, News (51), Library (51), Campaigns, Opportunities, Categories, Tags, Directory by state. The hub counts are stale (995 against 1,006). **robots.txt has no AI-crawler section**: all crawlers are allowed by default, and no decision is recorded. | Refresh the counts. Write down the AI-crawler decision. | **Done.** llms.txt returns 200. Each section hub line ends with its live count and date, and the page cache keeps llms.txt for 1 hour at most. **AI crawlers: allowed (owner, 15 Oct 2026).** robots.txt now says so in a comment, with no separate rules, and still points to the sitemap. GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot and OAI-SearchBot each get 200 on /news/. |
| 5.2 | Citation-ready summaries on hubs and key pages | Partly | Hub and page summaries are in the JSON-LD graph and llms.txt (commit 3222a6b). Hubs have their intro text. I did not see a separate citation-ready block. | Confirm with the owner whether more is wanted. | **Owner decision** (unchanged): whether a separate citation-ready block is wanted. |
| 5.3 | Entity consistency | Done | The same name, alternate names and `sameAs` on every page sampled; `twitter_author_names` is AnimalWrites; one Organization node. | — | **Done** (unchanged). |

### Phase 6: schema

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 6.1 | Validate every type with Rich Results / schema.org | Partly | All 31 URLs have one JSON-LD block that parses. My check of required and recommended fields found these gaps:<br>- **JobPosting has no `validThrough`** (both opportunities sampled), and one has no `employmentType`.<br>- The hiring Organization has no `logo`.<br>- The research `isBasedOn` ScholarlyArticle has no `name`/`headline`.<br>There is no record that the Google Rich Results test was run, and I could not run it from here. | Run the Rich Results test on one URL per type. Fill `validThrough` and `employmentType` **only where the data exists**. | **Partly. Owner action:** the Rich Results Test could not be run because of a CAPTCHA. JobPosting has `validThrough` and `employmentType` only where the data exists; 2 of 18 opportunities have a stored deadline. All 31 head URLs have 1 JSON-LD block that parses. |
| 6.2 | Fix and fill schema per type | Done | Live types found:<br>- News: NewsArticle with the source as a CreativeWork.<br>- Rules: Legislation with a MediaObject.<br>- Library documents: Article with DigitalDocument.<br>- Research: Article with `isBasedOn` ScholarlyArticle.<br>- Guides: Article with FAQPage.<br>- Listings: NGO, or VeterinaryCare+GovernmentOffice.<br>- Opportunities: JobPosting, with none on closed ones.<br>- Campaign: Event.<br>- Hubs, places and categories: CollectionPage with ItemList.<br>- BreadcrumbList everywhere except Home. | No AnimalShelter seen in the sample. HowTo appears only on numbered guides, by design. | **Done.** The same types as before, live on the 31 URLs. For example, the opportunity is JobPosting and the listing is NGO. |
| 6.3 | Rank Math schema per post type; robots, canonical and OG defaults | Done | Default rich-snippet type per type (Rank Math free has no schema templates). Primary taxonomy `category` for News, Library, Resources, Campaigns, Opportunities and Listings. Breadcrumbs on. Default OG image ID 174. Canonical present on every indexable page; filtered and noindex pages have none. | — | **Done** (unchanged). |
| 6.4 | Sitemap module | Done | The index lists 17 sitemaps:<br>- Resources 159, Pages 17, News 1,006, Library 84, Campaign 1.<br>- Opportunities 16 (the 2 closed ones left out), Listings 5,138 (the noindexed sample left out).<br>- Categories 18, Tags 16 (the 34 noindexed thin tags left out, which is consistent).<br>- Directory places 1,561.<br>Images are included. `/sitemap.xml` and `/wp-sitemap.xml` 301 to the index. | `/claim/` mismatch: see 1.3. | **Done.** The index lists 11 sitemap groups: Resources 162, Pages 18 (`/claim/` included), News 1,024, Library 85, Campaign 1, Opportunities 16, Listings 5,138, Categories 18, Tags 16, Filtered views 35, Directory places 1,561. |

### Phase 7: internal linking (held until the taxonomy migration; now unblocked)

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 7.1 | Related items: News, Library, Resources and Directory by category and place | Not started | Held on purpose. Rank Math's link counter shows almost no links inside content: 65 rows in `rank_math_internal_links`, because News and Library `post_content` is empty. | All of it. | **Done** (Phase 7, 11f434c). Related blocks show on 176 of the 251 sampled pages (every sampled item type). There are entity links in the summary on 30 pages. All **390** related and entity link targets return 200. |
| 7.2 | Breadcrumbs everywhere; orphan check | Not started | Breadcrumbs do show on every sampled page except Home (done in Phase 6). No orphan check has been made. | Orphan check. | **Done.** Breadcrumbs are on every sampled page except Home. The 4 earlier orphans are now linked:<br>- `/tag/rule/` from 3 Library items in the sample;<br>- `/tag/notification/` from 1 item;<br>- `/tag/template/` from 2 items;<br>- Ashoknagar's `animal-welfare-organisation` page from its district page (fixed in this pass; it was orphaned because a district with one type printed no type link). |
| 7.3 | Canonicals and breadcrumbs on filtered lists | Not started | — | All of it. | **Done.**<br>- `?category_name=` views: own title, description, breadcrumb and canonical from 3 items (35 in filtered-sitemap.xml).<br>- `?tag=` views point their canonical at `/tag/{slug}/`. This is the Phase 7 choice, kept; for example, `/library/?tag=rule` → `/tag/rule/`.<br>- **`?q=` is now noindex, follow on every hub and Directory place**, as Google advises for internal search results.<br>- **`?state=` and `?publisher=`** follow the category rule: own title, for example "Animal Welfare News from Live Law" or "Animal Welfare Opportunities in Maharashtra", with description and self-canonical from 3 items, and noindex below 3. Before, each one repeated the hub's title. News `?state=` holds 0 items, because News has no place data, so it is noindex. |

### Phase 8: content and strategy

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 8.1 | Fill missing SEO copy: meta, intros, alt text, FAQ answers | Not started | No Phase 8 commits or backups. The open gaps are listed in 2.5 and 3.1. | All of it. | **Done** (Phase 8 report, `content-strategy-2026-10-15.md`). The 938 short summaries are an owner rewrite run. |
| 8.2 | Content-strategy map, delivered as a report | Not started | None in `docs/seo/` or the artifacts. `keyword-map.md` and `keyword-demand-2026-10-15.md` exist but are not a gap map per category. | The report. | **Done** (`content-strategy-2026-10-15.md`). |

### Phase 9: site health

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 9.1 | Core Web Vitals, CLS, caching, mobile, HTTPS/www, robots.txt, 404 monitor, redirect chains | Not started | No Phase 9 work. What is true today:<br>- **`?author=1` still takes 2 hops** (to `/author/admin/`, then `/about/`); the plan named this one explicitly.<br>- `http://www.` takes 2 hops.<br>- `/news` without the trailing slash answers 200 (canonical points to `/news/`).<br>- LiteSpeed cache hits, HSTS is on, robots.txt returns 200.<br>- No horizontal scroll at 375px, and CLS was 0 in the browser pane on 4 pages.<br>- **The PageSpeed API refused every request (daily quota exceeded), and Lighthouse is not installed, so there are no CWV or Lighthouse scores.**<br>- 404 monitor gaps: see 2.3. | All of it. | **Done** (`site-health-2026-10-15.md`). `?author=1` → `/about/` in 1 hop; `/news` → `/news/` in 1 hop. **Owner (hPanel):** `http://www.` still takes 2 hops; HTML edge caching, LiteSpeed purge scope and security headers are also host settings. |

### Phase 10: final champion pass

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| 10.1 | Check everything: sitemap entries Rank Math lacks, IndexNow, the save handler, and that removed pieces stay removed | Not started | Spot check done here:<br>- IndexNow module is on and has a key.<br>- No FAQPage, takeaways or summary output in our code.<br>- Only `kits_sitemap_skip` is left from the kits_* SEO filters.<br>- The breadcrumb filter now lives in aw-seo-extras, as intended.<br>- **The core Directory title filter is still there** (G1).<br>- The core term-screen fields are not SEO ("Find help" types and photos). | The full pass. | **Done** (this pass): sitemap, IndexNow (module on), robots, llms, templates and removed pieces all checked live. |
| 10.2 | Fix leftovers | Not started | — | — | **Done**: items 11, 14, 15 and 16 and the Phase 7 leftovers. |
| 10.3 | Final report with before and after | Not started | No SEO report artifact exists. The latest artifact is from 8 Oct. | The report. | **Done**: `docs/seo/final-report-2026-10-15.html`. |

### Verification section

| # | Bullet | Status | Evidence | What's missing | Status at close (16 Oct 2026, 07:30 IST) |
|---|---|---|---|---|---|
| V1 | Per phase: snapshot diff; sitemap, llms.txt and robots.txt return 200; Rich Results test per type | Partly | The 3 endpoints return 200 now. Snapshots exist for Phase 6 only. No Rich Results record. | — | **Done**, apart from the Rich Results Test (owner). The 28-page snapshot shows 0 changes; sitemap_index.xml, llms.txt and robots.txt return 200. |
| V2a | Broken-link crawl shows 0 internal 404s | Not done | The sample crawl found 1 internal 404 (2.2). | — | **Done.** 1 internal link returned 404 once: `/directory/maharashtra/pune/`, during the check. It returned 200 on 6 rechecks with HEAD and GET and is not in the 404 log, so the failure was transient. The other 1,328 checked URLs returned 200. |
| V2b | Lighthouse SEO and performance on Home, News, a listing and a guide at 375 and 1200 | Not done | No earlier record. The PageSpeed API quota was exhausted today. | Run it with an API key or local Lighthouse. | **Done** (Lighthouse 13.5 in `site-health-2026-10-15.md`; the PageSpeed API quota was used up). |
| V3 | Every mapped event seen firing in a real browser | Partly | Seen today in the browser pane:<br>- `page_context` (with page type, section, state and district)<br>- `search`, `outbound_click`, `email_click`, `card_click`, `menu_click`<br>- `404_view`, and `scroll_depth` at 25, 50, 75 and 90<br>**Not seen:**<br>- `form_submit_success`, `form_error`, `filter_apply`, `sort_change`<br>- `call_click`, `whatsapp_click`, `pdf_download`, `share_click`, `claim_step`<br>These need a form submit or specific pages, which this read-only check avoided. | Test those events. | **Done** (`docs/analytics/`). |
| V4a | Field counts per type | Done | See 3.1. Other counts:<br>- Rank Math title and description: 100% on every published post type.<br>- OG image meta: 1 Page only, so every other page falls back to the default image or the featured image.<br>- Featured images: News 233/1,006, Library 76/84, Resources 159/159, Listings 14/5,139.<br>- Categories: title, description and keyword 18/18. Tags: title and description 50/50, keyword 0/50. | — | **Done.** See the counts table in "Status at close" below. |
| V4b | Rank Math scores on 10 items, before and after | Not done | **`rank_math_seo_score` is empty for every published item of every type** (0 rows). There is no before/after. | See section 2. | **Done.** Every published item with its own page has a stored score: News 1,024, Library 84, Resources 162, Listings 5,139, Opportunities 18, Campaign 1, Pages 22. The 43 Media & PR items 301 to /media/ and have no page, so they are not scored. The 97 News items re-scored today (the 24 with blank titles, the 26 with pipe labels and the 53 keyword fixes) went from an average of 46.4 (67 red) to **84.3** (0 red). |
| V5 | Final report artifact for the owner | Not done | None exists. | — | **Done**: `docs/seo/final-report-2026-10-15.html`. |

## 2. Rank Math SEO score

### How Rank Math (free, 1.0.280) works out the score

- **It is calculated only in the browser.** The analyzer is `assets/admin/js/analyzer.js`. It builds a "Paper" from the SEO title, the SEO description, the focus keyword, the URL and the content, then runs the tests below.
- **The score** is the points earned divided by the points possible across the tests that apply, × 100. Above 80 is "great" (green), 51 to 80 is "good", and 50 or below is "bad" (red).
- **Where it is stored.** The editor saves the score into `rank_math_seo_score` when the post is saved. Nothing on the server calculates it:
  - `rest/class-admin.php::update_seo_score` only stores numbers the browser sends.
  - The `rank-math/get-seo-scores` ability only reads the stored value.
  - The `rank-math/analyze-post-content` ability returns raw headings, images and links, not a score.
- **Bulk option:** Rank Math → Status & Tools → Database Tools → **Update SEO Scores**. It needs a **logged-in admin browser**:
  - The PHP side sends batches of `{title, description, keyword, content: wpautop(post_content), url, thumbnail alt}`.
  - The browser runs the same analyzer on each batch.
  - It POSTs the numbers to `/rankmath/v1/updateSeoScore`, using the REST nonce.
  - It only covers items that have a focus keyword, which is all of ours.
- **There is no server-only or wp-cli route.** Running `analyzer.js` headless under Node would mean rebuilding Rank Math. Not recommended.

**Tests and their maximum points.** "Applies" says which of our types each test fits:

| Test | Max | Applies |
|---|---|---|
| Keyword in SEO title | **36** | all |
| Keyword density about 1% | 6 | all with text |
| Content has images or video | 6 | all with text |
| Keyword in URL | 5 | all |
| Has an internal link | 5 | all with text |
| Content length (0 under 600 words; 2 at 600+; up to 8 at 2,500+) | 8 | all with text |
| URL at most 75 characters | 4 | all |
| Has an external link | 4 | all with text |
| Keyword in first 10% of the content | 3 | all with text |
| Keyword in content | 3 | all with text |
| Keyword in a subheading | 3 | all with text |
| Title starts with the keyword (first half) | 3 | all |
| No paragraph over 120 words | 3 | all with text |
| Keyword in SEO description | 2 | all |
| Keyword in image alt | 2 | all |
| Not all links external | 2 | all with text |
| Table of contents | 2 | all with text |
| Number in title, sentiment, power words | 1 each | all |

The static Home page skips a few tests.

### What the owner sees today in Pages → SEO column

- **For the 18 indexable pages:** a grey **"N/A"**, then "Keyword: …" showing the first focus keyword. `class-post-columns.php` prints N/A whenever `rank_math_seo_score` is missing, and it is missing on all 22 pages.
- **For search, remove-my-data, report and thank-you:** "N/A" and **"No Index"**.
- **Inside the editor:** the Rank Math sidebar calculates a live score from the block markup in `post_content`. For hubs and form pages that markup is shortcodes and pattern references, so the analyzer sees 0 to 22 words, no links and no images. My estimates:
  - Directory about 47 (red).
  - Categories about 7 (red), because its keyword "animal welfare topics" is not in its title "Animal Welfare Categories in India".
  - About about 52.
- Saving any page in the editor stores that red number, and the list column then turns red.

### Why our types score badly: where the text actually lives

| Type | Where its text is | What Rank Math sees |
|---|---|---|
| News | Summary in `post_excerpt` (average 198 characters); `post_content` is **empty on all 1,006** | 0 words |
| Library | 78 of 84 have under 200 characters of content; the summary is in the excerpt | 0 words |
| Resources (research) | 143 summaries, mostly in the excerpt | 0 words |
| Resources (guides) | The guide text is in the content | 250 to 300 words |
| Listings | Average 179 characters; the details shown on the page come from meta (`aw_area`, `aw_awbi_*`) and the template | 3 to 25 words |
| Opportunities | 18 to 70 words | 18 to 70 words |
| Pages (hubs and forms) | Lists rendered by blocks and shortcodes | 9 to 22 words of real text |

### Failing tests: 3 real items per type, Rank Math's rules applied to stored data

| Type | Items | Estimated score | Tests failed |
|---|---|---|---|
| News | 13748, 13752, 13707 | 3–9 | Every content test. Keyword in title failed on all 3 when checked; the keywords were filled during the check, and 74% of News keywords are not in the title. URL over 75 on 2 of 3. |
| Library | 8881, 142, 9636 | 8–58 | Every content test (0 words). Keyword in title failed on 8881: its cut title "…Control and Supervision" drops "laboratory animals rules". URL over 75 on 3 of 3. |
| Resources guide | 127, 9031 (12545 is research) | 61–70 | Length under 600; keyword not in a subheading; keyword not in the description (127); no internal or external link (9031); no image. |
| Listing | 3152, 8449, 1583 | 50–54 | Every content test; length; no image. URL over 75 on 2 of 3. Title, URL and description tests pass. |
| Opportunity | 7621, 169, 7626 | 9–57 | 169: keyword "volunteer orientation hyderabad" is not in the title ("…at Blue Cross of Hyderabad"). Density too high (11–14%) on 30-word bodies. Length, links, image. |
| Page | 8945, 8759, 58 | 7–52 | Content tests, keyword in description (all 3), keyword in URL. 8759: keyword not in title. |
| Category | community-dogs, wildlife, courts-judgments | about 7 | The keyword is not in the title on all 3 (for example "stray dogs in india" against "Community Dogs in India"). The term description is about 30 words. |

The estimates come from a re-implementation of the weights above. Sentiment, power word, number and table of contents are counted as failed. Treat the numbers as rough; the list of failed tests is exact.

How widely the keyword placement tests fail, across all published items:

| Type | Keyword in title | Keyword in description | Keyword in slug | URL over 75 characters |
|---|---|---|---|---|
| News | 263/1,006 | 131 | 472 | **960** |
| Library | 27/84 | 22 | 51 | 74 |
| Resources | 37/159 | 31 | 102 | 145 |
| Listings | 5,137/5,139 | 4,564 | 5,139 | 1,183 |
| Opportunities | 13/18 | 12 | 13 | 8 |
| Pages | 14/22 | 4 | 4 | 0 |

### Feeding rendered content (`rank_math_content`): is it the right fix?

**Yes, for News, Library, research, listings, opportunities and the hub and form Pages.** Their visible text is real, but it lives in the excerpt, in meta or in templates. Rank Math supports exactly this:
- the JS filter `wp.hooks.addFilter('rank_math_content', …)` in the editor, used the same way by its ACF and custom-fields integrations;
- the PHP filter `rank_math/recalculate_score/data` for the bulk tool, the same way its WooCommerce module uses it.

Without this, every content test scores the empty `post_content` and every score is meaningless.

How to build it, in aw-seo-extras (allowed, because Rank Math can't see rendered content):

1. **One function returns the item's main body as HTML, and only that.** For News, the summary, source link and categories; for listings, the details block; for hubs, page 1 of the list. It must not include the header, navigation or footer, otherwise every page gets the same links and words and the scores become dishonest. Hubs can reuse the page-1 copy that `aw-seo-extras` already fetches.
2. **Editor:** a small script on the edit screens appends that HTML through `rank_math_content`, then calls `rankMathEditor.refresh('content')`.
3. **Bulk:** `add_filter('rank_math/recalculate_score/data', fn($v,$id) => […$v, 'content' => aw_seo_rendered_body($id)], 10, 2)`.
4. Run **Update SEO Scores** in a logged-in browser. This needs the owner's login; it can't be done over SSH.

The Rank Math setting "Custom fields to analyze" (`pt_*_analyze_fields`) covers meta-stored text in the editor only. It does not cover the excerpt, and the bulk tool ignores it, so the filter is still needed.

### Fixes we can make without inventing content (a)

1. **Keyword in the SEO title: 36 of 100 points.** Change `focus_keywords.py` so the phrase must appear word-for-word in the SEO title, and preferably early, which also wins the 3-point "title starts with keyword" test. Re-run it for News, Library and Resources, with a backup first.
2. **Keyword in the description: 2 points.** Where the description, which is drawn from the item's text, can naturally contain the phrase, prefer that phrase.
3. **Feed the rendered body** (above). The summary text, the source link (external), the category and related links (internal, Phase 7) and the picture (image test) then count. These are things the page already has.
4. **Alt text:** 23 featured images without alt (take the alt from their own title), and 361 media-library images. News pictures are publisher links and can't carry our alt.
5. **Slugs:** keep published URLs. New ingests get shorter slugs (75 characters or less, keyword included).
6. **Categories and the Categories page:** their keywords don't match their own titles. **Proposed Categories page keyword: "animal welfare categories"**:
   - Google autocomplete (India) suggests it as a query, returning "animal welfare categories" and "animal welfare topics".
   - It is word-for-word in the live title "Animal Welfare Categories in India" and in the description "Explore animal welfare categories across India…", so the title, title-start and description tests pass.
   - "animal welfare issues" also autocompletes, but mostly with other countries ("…in australia", "…in canada"), and "animal welfare issues in india" does not.
   - Suggested secondary keyword: "types of animal welfare" (autocompletes).
   - Row 61 of `docs/seo/keyword-map.md` also says "animal welfare topics" and needs the same change.
   - For category terms, the owner chooses between the search term ("stray dogs in india") and the site's own word ("community dogs"). One of the title and the keyword has to give way.

### Tests that don't fit the content type (b)

| Test | Doesn't fit | What to do |
|---|---|---|
| 600+ words; density about 1%; keyword in subheading; table of contents | News and Library summaries (about 50 words), listings, opportunities, form pages, hubs | Don't pad the content. Remove these tests for those post types with the PHP filter `rank_math/researches/tests` (it gets `$tests`, `$type`; check `get_post_type()`). This changes the editor score. **Unverified:** whether the bulk tool's test list (`rankMath.assessor.researchesTests` on the Status page) also passes through this filter. Test on 1 item. |
| Number, sentiment and power words in the title (3 points) | News titles are the publisher's headline; listing titles are names | Accept, or remove with the same filter. |
| URL at most 75 characters | 960 existing News URLs, 1,183 listings | Leave published URLs alone (renaming them means redirects and lost links). Fix it at ingest for new items only. |
| No paragraph over 120 words | Fine as it is | — |
| Keyword in URL | Hubs (`/directory/`, `/news/`) | Accept; hub URLs are fixed. |

Even after (a) and (b), a 50-word News summary will rarely reach "great". Use the score as a checklist, not a target.

## 3. Prioritised fix list

### P0: wrong or broken now (small changes)

1. Set `/claim/` (12587) to noindex. It is out of the sitemap but indexable.
2. Remove `wugweb-sample-listing` (9003) from the lists on `/directory/` and `/tag/abc-centre/`. It is linked but returns 404. Ask the owner before unpublishing it.
3. Redirects:
   - Point the 2 trashed duplicates (13292, 10789) at the copies that were kept.
   - Point `topic/stray-animals` at a category that exists. Right now it redirects into a 404.
4. Fix the `ad-slot.js` editor script URL in `kits-core/includes/ads.php:176`. It 404s on every editor load.
5. Fix the facts line on Directory type pages. "The most in Jodhpur (118)" is printed on the Nagaur page, which has 130.
6. Redirects that take 2 hops:
   - `?author=N` should go to `/about/` in one hop.
   - `http://www.` should go straight to `https://animalwrites.in/`. This is a host or CDN setting.

### P1: Rank Math scores, and gaps in Phases 1 to 6

7. Build the rendered-body feed (`rank_math_content` plus `rank_math/recalculate_score/data`). Remove the tests that don't fit each type. Then run **Update SEO Scores** in a logged-in browser. Record 10 items before and after; this is V4b.
8. `focus_keywords.py`: the phrase must be inside the SEO title. Re-run for News, Library and Resources, with a backup first.
9. Change the Categories page keyword to "animal welfare categories" (plus "types of animal welfare"). The owner decides the keyword versus title wording for category terms.
10. JobPosting: add `validThrough` and `employmentType` where the opportunity has a stored deadline or type. Never guess them. Run the Rich Results test on one URL per type.
11. Check and mark dead News source URLs.
12. Alt text for the 23 featured images, then the media library.
13. Focus keywords for tags (0/50).
14. Rank Math templates per post type instead of the defaults. Remove the stale `pt_aw_guide_*` and `tax_aw_listing_type_sitemap` options.
15. Refresh the llms.txt hub counts. Record the AI-crawler decision for robots.txt.
16. Ground rule G1: move or remove the `document_title_parts` Directory filter in animal-writes-core, and the unused `kits_sitemap_skip`.

### P2: phases not yet run

17. Phase 7 internal linking (now unblocked): related items, orphan check, canonicals on filtered lists.
18. Phase 8: missing copy, plus the content-strategy gap report.
19. Phase 9: Lighthouse and CWV at 375 and 1200 (with a PageSpeed API key or local Lighthouse), caching, the 404 monitor, redirect chains.
20. Test the events not yet seen firing: form_submit_success, form_error, filter_apply, sort_change, call_click, whatsapp_click, pdf_download, share_click, claim_step.
21. Phase 10: final pass, then the report artifact for the owner, with before and after.
