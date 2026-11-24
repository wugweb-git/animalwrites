> Status as of 24 Nov 2026 (checked against git): Mostly done. aw_keep_modified, keyphrase owner guard, quick-win flag, monitoring-cadence.md are in wordpress-dev. docs/seo/cdn-hpanel-proposal.md missing.

# Technical to-dos, 1 Nov 2026 (tech session)

## Context
Three read-only audits on 1 Nov found site-wide technical problems: slow uncached pages and a 5-minute edge cache, keyphrase collisions, 281 posts re-stamped "modified 1 Nov", failing Search Insights sources, schema details, Dates wording. The coordinator later took the lab-rules campaign away from this session (no edits to its template, scripts or headers) and added T19, T31, T47, T52, two FAQ text bugs, a Dates entry and eight written proposals. Nothing here changes page copy.

Worktree branch is now `tech-1101` from `origin/wordpress-dev` (e559ed0d). The worktree had been cut from `main`, the old Figma repo. Every push goes straight to `wordpress-dev`, then `tools/aw-ship.sh`, one deploy at a time. Before each push, ask the "Campaign 2 master plan" session and the coordinator (`local_89a968ab…`) whether another deploy is running.

## Facts already checked (read-only, 1 Nov)
- **Cache:** every page sends `public, s-maxage=300` from `animal-writes-core/includes/cdn-cache.php` (option `aw_cdn_ttl`=300, clamped 60–900). The campaign's clean URL is cached the same way. `max-age=0` shows up only when there is a query string (utm/fbclid), because the code skips any query string. Edge HIT TTFB is about 0.55 s and DYNAMIC is 1.0–2.2 s, both from Frankfurt. WordPress cannot purge hcdn. The deploy purge clears LiteSpeed only.
- **/about/ CLS:** the footer folds are served `open` and `kit.js:612` closes them. A 375px load measured CLS 0, so the cause is still unconfirmed.
- **Re-stamp:** 66 Library items at 14:04 IST, 199 Resources at 14:13–14:41 and 16 pages at 14:31–14:42. Each save made a revision, and `_edit_last` is 1 (admin). That points to admin-REST bulk saves made in the browser, not a cron job. The window lines up with the FAQ Subtitle commits (baf76c05/3406d2b6, 14:05–14:10 IST).
- **IndexNow:** Rank Math's `instant-indexing` module is on, the key is set and the key file returns 200. T50 needs no change.
- **Library:** all 14 act, rule and notification items are already `Legislation` with `legislationIdentifier`. T44 is a check only.
- **District ItemList:** Mumbai shows `numberOfItems` 94 with 24 elements, so it already uses the real total. I'll recheck a state page and a type page.
- **Search Insights runs:**
  - GDELT: 429.
  - Autocomplete: ran 26 Oct and stored 0 rows (a baseline run). It is weekly and due 2 Nov, so it is not broken.
  - Trends interest: 429.
  - Trends trending: 0 rows, "kept 0".
  - Bing: 46 queries have no keyword record.
  - Live: never run.
- **Keyphrases:** 129 keywords have status conflict, with 444 owner rows (441 from focus). There are 311 duplicate primary-keyphrase groups covering 2,715 extra pages.
- **News images:** 1,412 published items. 1,331 have a source image and 968 of those are at least 1200px wide.
- **/helplines/:** Rank Math redirection id 41 points to `/#need-help-now`.

## Batch A: code, one push, one deploy
1. **Footer fold (site-wide):** in `themes/animal-writes/parts/footer.html`, keep `open`. Add a tiny inline script right after the three folds that closes them before first paint at 767px and below, the same way `kits-core/includes/primitives.php:117` does. Before shipping, re-measure /about/ at 360, 375 and 412px with layout-shift sources. If the shift comes from somewhere else, fix that instead and report it.
2. **CDN lifetime (site-wide, also reaches the campaign; say so in the report):** in `cdn-cache.php`:
   - raise the clamp to 60–3600;
   - add a per-type lifetime through the existing `aw_cdn_ttl` filter: listings, Directory places, Library and Resources get 3600, Home, News and hubs keep the option value;
   - add `stale-while-revalidate=600` only after a `?cdnprobe=1` test shows hcdn accepts it.

   The option value itself stays at 300 unless the owner agrees. The proposal goes into a new file, `docs/seo/cdn-hpanel-proposal.md`, with the exact hPanel settings: ignore utm_*/fbclid/gclid in the cache key, an India PoP if offered, and the purge-on-deploy note.
3. **Keyphrase guard (T11):** in `aw-seo-extras/includes/focus-keywords.php` `/seo-focus`, call a new helper `aw_seo_keyphrase_owner($kw, $id)` before writing. It checks:
   - (a) another published post whose primary `rank_math_focus_keyword` is the same phrase, compared with `aw_si_norm`;
   - (b) a `wp_aw_si_kw_pages` row with role owner on another object;
   - (c) an `aw_news` item asking for a phrase owned by a post, aw_legal, aw_campaign, page, listing or term.

   A refusal returns `skipped[] = {id, why: 'owned by <type> <id>'}`. Add a WP-CLI dry run, `wp aw-si keyphrase-guard --dry`, that replays the current conflict owners in post-date order and counts how many the rule would have refused. Run it read-only over SSH and report the number. Add a pure-PHP model and tests to `plugins/aw-sourcing/tests/run.php`'s pattern, if the rule can stay WordPress-free.
4. **Modified-date guard (T37):** add a flag filter on `wp_insert_post_data`. When `$GLOBALS['aw_keep_modified']` is set, or the REST request carries `aw_keep_modified=1` or the header `X-AW-Keep-Modified`, it keeps `post_modified` and `post_modified_gmt` from the stored row. Use it in the site's own scripted savers (`faq.php` conversions, `news-titles.php`, `same-story.php`, `enrichment-cleanup.php`, `summary-rewrite.php` where the edit is not a content change). Note it in `docs/` for the browser bulk scripts. To confirm the cause, diff the revision of item 9576 at 14:04 against the one before it, and record it in pending.md. The 281 dates are not touched.
5. **Search Insights sources (T16):**
   - **GDELT:** add a setting "News (GDELT)", off by default, on the Settings tab using the Settings API. While it is off the source leaves the sources list and Site Health, and a core notice says why. Google News RSS needs a workers change, which is out of scope, so it goes into pending.md as a suggestion.
   - **Autocomplete:** report the diagnosis (a baseline run, weekly, due 2 Nov). Also stop a 0-row baseline run from reading as fresh data on the Rising notice. The fallback needs the workers, so it also goes into pending.md.
   - **Bing:** add an admin-post action on Settings, "Add keyword records for Bing queries without one". It uses `aw_si_kw_ids($labels, AW_SI_ROLE_SEARCHED)` and shows a dry count first. I run it once in the built-in browser.
   - **Live:** the seed import in `imports.php:~305` calls `aw_si_run_ok('live', …)` and is marked "seed".
   - **Trends:** when a worker run returns 0 rows together with an error or a refusal, store `info.status = 'source failed'`. The Rising notice then shows "source failed" instead of "no demand".
6. **Dates (T18):** add `aw_team_dates_1101b()` in `team-dates.php`, with flag `aw_team_dates_1101b`. It retitles the 17 Nov entry to "Supreme Court stray-dog case: High Court compliance reports due (19 May 2026 judgment)" and adds two entries: "Cause-list check for 17 Nov" on 12 Nov, and "Search Insights thresholds review (Wikipedia 2×, GDELT 3×)" on 25 Nov.
7. **Schema (T29/T47):** in `aw-seo-extras/includes/schema.php`, inside the `rank_math/json_ld` filter:
   - on News, set the WebPage `datePublished` and `dateModified` to the NewsArticle's;
   - add an E.164 normaliser for `telephone` and ContactPoint numbers in `aw_listing_schema`: +91 with 10 digits for mobiles, and STD landlines without the leading 0; anything else is left as stored;
   - check BreadcrumbList on each type.
8. **FAQ text bugs:**
   - In `animal-writes-core/includes/faq-data.php` `aw_faq_study_parts`, a direction word alone ("North") must never become the place. When it does, fall back to the summary's place, which gives Sohagibarwa.
   - Topic capitals: when the keyphrase is all lower case and the title has the phrase in title case, keep "Indian" as a proper adjective. This is a demonym rule ("Indian").
   - Make the FAQ checks (`wp aw-faq check` and the FAQ admin column) report a title fallback as a warning on every page type, not only `AW_FAQ_SUBJECT_TYPES`.
9. **Docs:** a new `docs/seo/monitoring-cadence.md` (T52) with the weekly read (Search Insights Overview, Rising, Quick wins) and the monthly read (Links, already on Dates).

Checks before the push:
- `php -l` on changed files;
- the PHPCS delta;
- `registry_check.py`, footer, breakpoint, contrast and thumb checks;
- `php plugins/aw-sourcing/tests/run.php`.

Then push once, wait for the Deploy check, run `aw-ship.sh` and confirm `aw_deployed_head` matches the pushed commit.

## Batch B: live data through wp-admin in the built-in browser, after the deploy
- **T19:** edit Rank Math redirection 41 so `/helplines/` points to `/resources/who-to-call-for-an-animal-emergency-in-india-112-1962-local-groups/`, then curl the 301.
- **T13:** first read each row's status, then dismiss "yes" and the celebrity tails (nauheed cyrusi, ronak choudhary, gaurav taneja, romanchs law, anika puri, jagriti mishra biography, tina datta). In Rising, dismiss "bungou stray dogs" and any named gaushala with no listing, checking the Directory first. Mark Noted the rabies cluster, "animal cruelty act section 11 punishment", the gaushala-by-district rows and the state "in the news" rows. Skip anything already done.
- Run the Bing records action once and report the count.
- **T31:** dry-run the long tail of conflicts: the page Google shows keeps the phrase, the others drop it. Report the list. A real run waits for the guard to be live and needs a recompute. It writes keyphrases on many pages, so it waits for the owner's yes if more than about 20 pages change.

## Added 1 Nov (coordinator: work from the Search Insights data and `Claude outputs/action-register-2026-11-01.md`)
- **Stored quick-win flag (Batch A, small):** the Quick wins view is only a position filter (`class-aw-si-keywords-table.php:127`, `pos28 8–20 and impr28 ≥ AW_SI_QUICK_MIN`), so 307 rows have no stored value.
  - Add a `quick_win tinyint(1)` column to the keywords table in `search-insights/schema.php` (dbDelta, through `aw_si_install`).
  - Set it in the daily calculation in `compute.php` from the existing `aw_si_quick_win()`.
  - Point the view at the column, so the register, the weekly read and the Overview count the same rows.
  - The first daily run fills it. Check that the count matches the view's 307.
- **B.Page-quality flags (36 keywords):** all 36 land on Home. Home's PageSpeed is fine: mobile 92, LCP 1.8 s, CLS 0.04, PSI flag 0. The flag comes from Clarity on 30 Oct: dead clicks about 10–12% on mobile and 20% on PC, quick-backs about 7–8%, rage 0.2–0.5%.
  - Probe Home read-only in the browser at 375 and 1200 for things that look tappable but aren't: images and titles without links, tiles with a pointer cursor, and the Need help now bento.
  - Home is being reworked by the Home redo sessions, so I won't edit Home. I'll send them the list of dead-click elements and the fix for each. A code fix ships only for a site-wide component outside Home's files.
  - Also report that one flagged day in 28 marks a page for a month, so the threshold may be too sensitive. That is a proposal only.
- **B.Dismiss (12):** the list for the T13 tidy-up is the register's twelve: anant ambani, anant ambani gaushala, anika puri, gaurav taneja dog, jagriti mishra biography, mau gaurav taneja, nauheed cyrusi children, nauheed cyrusi kids, romanches law, ronak choudhary, ronak choudhary age, tina datta. Add "yes" and "tina datta" if they are not already among them. Check each status first.
  - Rising: Directory check first for the named gaushalas and "bungou". Mark Noted rabies, section 11, gaushala-by-district and the state rows. This uses the Rising tab's own row actions in the built-in browser.
- **D.Conflicts (129) long tail:** after the guard is live, follow the register's "Recommended owner" column. An evergreen page (Resource, Library, Campaign, Directory) keeps the phrase, and News items and tags drop it. Where there is no evergreen owner, the page Google shows keeps it.
  - Dry run first: list per keyword the pages that would lose the phrase. T12 rows with traffic, such as AWBI, animal writes, ABC, shelter, ambulance, MVU and World Animal Day, are reported separately.
  - Rows touching the campaign or Home are left to their sessions.
  - Writes go through `_aw_focus_history` so they can be undone, with a recompute after. More than about 20 pages waits for the owner's yes.
- **Opportunities stay paused:** no worker or page changes there.

## Proposals only (owner's yes needed; nothing shipped)
- **T38 forms 16px:** `@media (max-width: 767px) { :root { --kit-field-text-size: 1rem; } }` in `themes/animal-writes/assets/css/tokens.css`. The exact CSS gets pasted in the report.
- **CDN option and hPanel:** raise `aw_cdn_ttl` above 300, and the hPanel settings from the proposal file.
- **Plugin audit items:** #2, #3, #5 (option b, no IP), #16, #17, #18, #20 and #25 (`aw_worker` capability). One paragraph each with its risk, in `docs/seo/` or in pending.md.
- **T47:** list the News items without a 1200px+ image and the robots `max-image-preview:large` state. Report only.

## Verification
- **Headers:** curl 5 runs each on `/`, `/directory/`, the campaign, `/directory/org/saahas-for-animals/` and `/directory/kerala/`, before and after the deploy. Record TTFB, `cache-control`, `x-hcdn-cache-status` and `age`. The before numbers are already recorded.
- **Footer:** load /about/ in the built-in browser at 375px with layout shifts logged. Check that the folds are closed on phones and open at 768 and 1200.
- **JSON-LD:** parse one page each of News, listing, district, Library rule, Resource and hub. Check the dates match, phones start with +91, `numberOfItems` is the real total, the type is Legislation and there is one BreadcrumbList.
- **Guard:** dry-run count over SSH (read-only). Then one POST to `/seo-focus` with a phrase another page owns, using `dry`, from the admin REST in the browser. It must return the refusal reason.
- **Dates and Search Insights:** check the Dates screen and the Search Insights Settings/Rising notices after the deploy.
- **Deploy:** the Deploy check is green and `wp option get aw_deployed_head` equals the pushed sha.
- **Wrap-up:** update `docs/pending.md` by item title as each item lands. The final report lists every change, every verification, and what is left for the owner: hPanel, the CDN option, GA4, the 281 dates, the bookmarklet, the forms OK, the proposals and the workers suggestions.
