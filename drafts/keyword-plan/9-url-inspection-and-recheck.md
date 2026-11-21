# 9. URL Inspection re-run and the 28-day recheck (steps, nothing changed)

Drafted 18 Nov 2026 from the InsightWeb code on `origin/wordpress` (the live branch) and read-only reads of the live site over SSH. Nothing was run or changed.

## 1. What the plan asked, and what InsightWeb can do

The plan (Verification): "Re-run the URL Inspection that failed today before reading index status." PlanWeb entry **29216** "InsightWeb: re-run URL Inspection, then read index status" is dated 19 Nov 2026.

**There is no URL Inspection button in Settings → Runs and data.** The "Run now" row there has six buttons: Run the daily update, Run the N-day rollup, Run GA4, Start PageSpeed, Run Bing Webmaster (and Bing keyword volumes), Run Clarity. "Run the daily update" fetches Search Console, keyword roles and priorities; it does **not** start URL Inspection (`aw_si_daily()` never calls `aw_si_inspect_start()`).

URL Inspection starts only two ways:
1. **The daily job at 07:40 IST** (`aw_si_inspect` cron). It builds the day's queue (never-inspected pages first, then not-indexed pages, then the oldest checks), up to 2,000 pages, and works through it in 15-second steps every 5 minutes, but only while the site has visits (WP-Cron).
2. **WP-CLI:** `wp aw-si fetch inspect` (and `wp aw-si fetch inspect --dry` to list the next 20 pages without calling Google). This writes to the database, and SSH is read-only by the owner's decision of 25 Oct, so this route is the owner's only.

## 2. Where it stands today (read 18 Nov 2026, about 15:45 IST)

- Run log: "URL Inspection (index status): ok, 18 Nov 07:40". Yesterday's unfinished queue was closed and logged when today's started, so the "did not finish" state of 17 Nov is gone.
- Today's queue: started 18 Nov; 173 pages done, 98 of them indexed; 1,827 left; 1,188 published pages had never been inspected when it started. Next step scheduled 15:50 IST; tomorrow's queue at 07:40 IST on 19 Nov.
- Index state store: 2,100 page rows indexed (PASS) and 5,981 not indexed (one row per path ever inspected; not filtered to published pages here).
- The step 9 pages, all **indexed (PASS)**: World Animal Day (checked 7 Nov), International Tiger Day (4 Nov), National Dolphin Day (4 Nov), Kukur Tihar (11 Nov), Library 142 (18 Nov), News 24214 (5 Nov).

So the "re-run" is already happening on its own. Nothing needs a click today.

## 3. Steps for PlanWeb 29216 (19 Nov 2026)

1. **Wait for the morning run.** After 08:00 IST on 19 Nov, open **InsightWeb → Dashboard**, card **"Daily jobs"**. Find the row **"URL Inspection (index status)"**: "Last good run" should read 19 Nov, 07:40. If the State shows a warning, read its reason line: it says how many pages were not reached and why (Google's quota, Search Console not reachable, or the time budget), and when every page will have been inspected once ("… pages never inspected yet; at N a day the queue reaches them all by about [date]").
2. **Check the schedule is still as expected:** InsightWeb → **Settings → Runs and data** → row **Schedule**: "… from 07:40 IST URL Inspection (up to 2,000 pages a day, not indexed and never checked first)". Nothing to change there.
3. **If the 19 Nov row says Google refused (HTTP 403 or 429)**: do nothing that day; the next 07:40 run starts again with the pages left. If it says **Search Console could not be reached**: check Site Kit's Search Console connection (Settings → Connected services in InsightWeb lists it), then wait for the next run.
4. **If you want it started by hand before 07:40:** only from a shell with write access: `wp aw-si fetch inspect --dry` (lists the next 20 pages), then `wp aw-si fetch inspect`. WP-Cron runs the rest every five minutes.
5. **Read index status** once "Last good run" is today: InsightWeb → Dashboard box **"Index status (Search Console totals, and the pages we inspected)"**. Note the indexed and not-indexed counts and the date, and copy them into the 28-day recheck note (section 5).
6. **Pages edited from the keyword plan:** the queue checks indexed pages last (oldest check first), so an edited page that is already indexed may not be re-inspected for weeks. For each page that ships, ask Google directly: Search Console → URL Inspection → "Request indexing" (by hand, as on 1 Nov; daily quota applies), and IndexNow. That is the recrawl signal; InsightWeb's queue is the record.
7. Mark PlanWeb 29216 done with the date and the two counts.

**Suggestion, not a task (code, PR route):** add a "Start URL Inspection" button to Settings → Runs and data → Run now, calling `aw_si_inspect_start()`, so step 4 does not need a shell. Bigger technical work under the owner's rules: branch, PR, review gate, owner merges.

## 4. The 28-day recheck: when

- PlanWeb **29199** "InsightWeb: 28-day recheck of the keyword plan changes" is dated **16 Dec 2026** (Wednesday), 28 days after the step 1 data fixes of 17 and 18 Nov. Keep it.
- Each later change gets its own read 28 days after it ships (the plan: "InsightWeb rechecks 28 days after each change"). Add one PlanWeb entry per shipped batch, dated ship date + 28.
- **Seasonal pages are different.** Their impressions rise before the day and fall after it, so a 28-day read in December compares an off-season month with the month of the day itself and will always look like a fall. For World Animal Day, Tiger Day, Dolphin Day and Kukur Tihar, read **position and the health flags only** on 16 Dec, and read **clicks and impressions** in the 28 days after each 2027 day (proposed entries: 26 Aug 2027 for Tiger Day; 2 Nov 2027 for World Animal Day and Dolphin Day; 25 Nov 2027 for Kukur Tihar), compared with the baselines in section 6.

## 5. The 28-day recheck: what to read (from the plan's Verification)

Run section 3 first (URL Inspection current), then in InsightWeb → **Keywords**:

1. **Every touched row:** clicks, average position, impressions (28 days), against the baseline written in that step's draft.
2. **The 4 health flags** on the same rows, each should clear or not appear: "No canonical owner" (`no-owner`), "Owned by News, should be Resource/Library", "Focus keyphrase, poor rank" (`poor-rank`), "Should be a focus keyphrase".
3. **Conflicts count:** was 14; target 0.
4. **Tracked, not ranking:** the count should drop once the new pages (cruelty law, feeding law, the rabies guide) are indexed.
5. **Index status** (section 3 step 5) for every page that shipped.
6. Record the read as a dated line in `docs/pending.md` (and Notion "8. Status & Pending" if the owner asks).

Rows by step (each step's draft holds its own baseline table):

| Step | Draft | Rows to read |
|---|---|---|
| 1 | data fixes (pending.md, 17–18 Nov) | the 6 exclude-list focus keyphrases; the 6 untracked junk terms (gone); "animal testing bill" now P1; the CPCSEA email variants on the CPCSEA page; "animal experimentation rules 2026" as the campaign's focus (Lab: owner's word); Rising queue (18 Noted, 6 dismissed, 7 New) |
| 3 | every `3-*.md` (CPCSEA, IAEC, AWBI, how-to-report, 1962, who-to-call, injured bird, NGO Darpan, the 4 rabies pages) | the keyphrases each draft lists, the click-rate rows (titles and descriptions changed), and the helpline "No canonical owner" rows |
| 4 | `4-animal-cruelty-law-india.md`, `4-feeding-stray-dogs-law-india.md`, `4-rabies-after-a-bite.md` | the new pages' phrases in "Tracked, not ranking" (should start ranking once indexed) |
| 5 | `5-library-draft-lab-rules.md` | the lab-rules owner rows (Lab: owner's word) |
| 6 | `6-kukur-tihar-owner.md`, `6-news-proposals.md` | the owner rows moved from News to Resource; "kukur tihar 2027" and "kukur tihar date 2026" owner rows |
| 7 | `7-directory-titles.md`, `7-listing-keyphrases.md`, `7-mumbai-gap.md` | "NGOs in city/state" rows, the listing keyphrases, "animal aid mumbai" (was position 33.3) |
| 8 | `8-lab-campaign-keyphrase.md` | "animal experimentation rules 2026" (Lab: owner's word) |
| 9 | the four `9-*.md` drafts | section 6 below |

## 6. Step 9 rows and baselines (InsightWeb, 28 days, read 18 Nov 2026)

| Keyword | Impr | Clicks | Pos | Ranking page | InsightWeb owner | Flag |
|---|---|---|---|---|---|---|
| world animal day 2026 | 361 | 0 | 8.9 | WAD guide | Library 142 | owner mismatch |
| world animal day 2026 theme | 83 | 0 | 6.7 | WAD guide | WAD guide | |
| world animal day theme 2026 | 74 | 0 | 6.7 | WAD guide | WAD guide | |
| world animal day theme | 36 | 0 | 8.2 | WAD guide | WAD guide | |
| when is animal day | 19 | 0 | 9.9 | WAD guide | WAD guide | poor-rank |
| animal day | 14 | 0 | 9.0 | WAD guide | animal-days-in-india | owner mismatch |
| when is world animal day 2026 | 11 | 0 | 9.1 | Library 142 | Library 142 | |
| tiger day in india | 164 | 0 | 8.0 | Tiger guide | Tiger guide | |
| national tiger day in india | 99 | 0 | 9.0 | Tiger guide | Tiger guide | poor-rank |
| national tiger day | 23 | 0 | 12.0 | Tiger guide | Tiger guide | |
| tigers day | 19 | 0 | 10.3 | Tiger guide | Tiger guide | poor-rank |
| international tigers day | 12 | 0 | 14.9 | Tiger guide | Tiger guide | poor-rank |
| dolphin day | 239 | 0 | 7.7 | Dolphin guide | Dolphin guide | |
| dolphin day in india | 172 | 0 | 7.3 | Dolphin guide | Dolphin guide | |
| national dolphin day | 138 | 0 | 7.9 | Dolphin guide | Dolphin guide | poor-rank |
| dolphin day 2026 | 27 | 0 | 7.0 | Dolphin guide | Dolphin guide | |
| kukur tihar 2026 | 15,286 | 3 | 6.1 | News 24214 | News 24214 | |
| when is kukur tihar 2026 | 531 | 0 | 1.1 | News 24214 | News 24214 | |
| kukur tihar | 345 | 0 | 8.4 | Kukur guide | Kukur guide | poor-rank; news-should-be-evergreen |
| kukur tihar date | 110 | 0 | 6.1 | Kukur guide | Kukur guide | |
| kukur tihar 2027 | 65 | 0 | 7.2 | Kukur guide | News 24214 | poor-rank; owner mismatch |
| kukur tihar date 2026 | 60 | 0 | 5.9 | Kukur guide | News 24214 | no-owner |
| kukur tihar festival | 57 | 0 | 8.2 | Kukur guide | Kukur guide | poor-rank |
| when is kukur tihar | 25 | 0 | 7.6 | Kukur guide | Kukur guide | |

What "better" means on 16 Dec 2026 (off-season): the owner rows point to the guide pages; positions hold or improve; `poor-rank` clears on the rows whose title or opening changed (tiger day in india, national tiger day in india, national dolphin day, kukur tihar, kukur tihar 2027). Impressions will fall on all of these until each 2027 day; that is the season, not the change.

What "better" means after each 2027 day (2027 reads in section 4): clicks above 0 on the head rows (tiger day in india, dolphin day, world animal day 2027, kukur tihar 2027) and positions at 5 or better, against this table.

## 7. Facts and sources

- All behaviour in sections 1 to 3 is from `plugins/insightweb/includes/search-insights/admin.php` (Run now row, Schedule row), `cron.php` (`aw_si_daily`), `gsc-types.php` (`aw_si_inspect_*`, cap 2,000, 15 s steps, 300 s gap) and `cli.php` (`wp aw-si fetch inspect`) on `origin/wordpress`.
- Live state from read-only `wp eval` and `wp aw-si status` on 18 Nov 2026.
- Baselines from `wp_aw_si_keywords` and `wp_aw_si_scores`, read 18 Nov 2026 (Search Console via Site Kit, 28 days).
- Not checked: the Dashboard screens themselves in a browser (labels taken from the code); Search Console's own index report.
