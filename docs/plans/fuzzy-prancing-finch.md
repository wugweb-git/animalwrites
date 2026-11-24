> Status as of 24 Nov 2026 (checked against git): Partly done. Many B-list copy items gated on the owner's yes; see docs/pending.md.

# Final plan: act on the 1 Nov 2026 search data

> **The full to-do list (T1–T45), priorities, reasons, links, decisions, experiments and audits is in `Claude outputs/full-report-and-todo-2026-11-01.md`.** This file is the summary; the report wins where they differ. The merged edition (v3) is folded in as report §11, including the items rejected because they were wrong or against your rules.

## Context
You asked for every action in the 1 Nov analysis, your Search Insights notes (Rising, P1, P2, Quick wins, Conflicts), the Core Web Vitals, Trends and AI measurements, and your corrected report. The site's own data (Search Insights: Search Console, GA4, Clarity and PageSpeed, to 31 Oct) is the source of truth.

The site is new on Google, not invisible. Since 21 Oct it gets ~10,000 impressions and ~250–300 clicks a day, at average position 6–7. 93% of the top pages' clicks come from the lab rules campaign, and that demand drops after 5 Nov. So the work is:
1. Fix what hurts the campaign now (speed).
2. Turn page-1 reference pages into clicks.
3. Build the second engine: the dog-bite and rabies, helplines, Directory, and Supreme Court pages.

## Session self-audit: what was missed, now in this plan
1. `docs/pending.md` not updated for today's finished items (rule: after each item) → step 0.
2. The Dates entry says "hearing (press-reported)"; the judgment only sets reports due → reworded (A7).
3. Duplicate injured-animal guides (`found-an-injured-animal-what-to-do-first` / `-what-to-do`) give conflicting water advice (from the Diwali outline) → B20.
4. Telangana state page outranks the national cruelty guide (first analysis) → B9.
5. Experiments from both analyses were never listed → section E.
6. Rising terms not placed: "telangana stray dogs" (+850%), "animal rescue team near me", "animal ngo near me" → B6, B19, News.
7. "In the news" spikes (cheetah KNG22 shot, elephant ×70 unverified) → source check in the audit, then an optional News explainer (your call).
8. The Directory has no 24-hour filter, so the Diwali page must not promise one → B1 note; the filter is in B21 (suggestion).
9. Supreme Court page: the letter-petition link needs context, since the 19 May judgment dismissed the recall pleas → B3.
10. The dog-bite page says "up to 15 minutes" where NCDC's tables say "for 15 minutes" → B2.
11. Rising tab page 2 and Quick wins rows 51–307 were never fully read → the data audit reads all of them (step 1).
12. Your other chat offered to mark the Rising noise itself → A6 runs only if it hasn't done so (I check the status first).
13. My worktree `aw-search-1101` must be removed when the work ends (my own, by its exact path) → the last step.
14. Weekly crawler check and Googlebot/Bingbot crawl stats in Search Console → yours; listed under "Yours".

## Step 0: housekeeping (now)
- `docs/pending.md`: record today's finished items by title. Wording is neutral; nothing internal goes into the public repo.
- Dates: reword the 17 Nov entry to "Supreme Court stray-dog case: High Court compliance reports due (19 May 2026 judgment)" via a small one-time update, same pattern as before.
- Check the Dates screen shows the 4 entries once.

## Step 1: finish the full audit (running now, read-only)
Three audits: the crawl (every sitemap URL and every URL Google shows: status, redirects, canonicals, titles, H1s, schema, robots, llms.txt), search data (CTR gaps, all 307 quick wins, conflicts by type, GA4, AI, Hindi queries, losers), and performance and health (TTFB and cache, Core Web Vitals, structured data, the 4 dead plugin sources). Results go to `Claude outputs/full-audit-2026-11-01.md`. Each finding becomes a row in A or B below, or a new one.

## A. Technical: I do these and report (order = priority)
| # | Work | Evidence |
|---|---|---|
| A1 | **Lab campaign speed** (campaign live to 5 Nov): long tap tasks in the letter tool, countdown and share; defer JS; reserve space (CLS); preload and size the LCP image. Speed only, no Post-phase content. Also `/about/` CLS | INP 276 ms (fail), lab LCP 4.1 s, CLS 0.104; /about/ CLS 0.105 |
| A2 | **TTFB** on Home and Directory: LiteSpeed cache hits for logged-out visitors, TTL and purge frequency, CDN. Fix inside the existing cache setup | 1.1–1.3 s |
| A3 | **Conflicts (130)**. Keyphrases: AWBI → explainer; animal shelter/ambulance → Directory type pages; mobile veterinary unit → 1962 guide; world animal day → guide; animal writes → Home. A link from each losing page to the right one. "Animal birth control": two options for you. Long tail: the page Google shows keeps the phrase, the others drop it (dry run, then REST). Root cause: Sourcing skips a keyphrase that already has an owner (small code change) | Conflicts view |
| A4 | **Old URLs**: one-hop 301s for /knowledge/*, /legal-watch/* and the FIR URL; no chains or 302s; internal links updated to the new URLs | 1,677 impressions on old URLs |
| A5 | **Campaign 13848 keyphrases**: secondary "gsr 864e", "cpcsea-mef@gov.in" | your note |
| A6 | **Search Insights tidy-up**. Dismiss: "yes", the celebrity tails, the Rising noise. Mark as Noted: rabies, "section 11 punishment", gaushala by district, states in the news | your note |
| A7 | **Dates**: the 17 Nov wording fix; experiment checkpoints as each page goes live | — |
| A8 | **UTM link list** for Instagram, Facebook and X bio and story links (you post them) | 2,136 IG sessions, untagged |
| A9 | **Plugin data sources**: GDELT throttle, back-off and cache; Bing key/verification check; autocomplete fallback; Trends daily-trends RSS for IN. AI keys are not touched | 4 sources dead |
| A10 | **Schema**: BreadcrumbList check; VeterinaryCare/LocalBusiness on listings (stored phone, hours, geo); Article/Legislation-style markup on Library items; FAQ markup kept for AI | corrected report §6 |
| A11 | **Internal links**: spokes ↔ hubs; News first paragraph → explainer; CPCSEA, IAEC and testing-laws → the Library draft | corrected report §7 |

## B. Copy: drafted by subagents, pasted to you, published only on your yes
Each draft opens with a data box: Search Insights queries, impressions, clicks, position; Rising terms; Trends timing (Chrome); volume (Ubersuggest/OpenSEO free tiers); top 3 competitors in the results. Every fact is sourced.

| # | Page | Due |
|---|---|---|
| B1 | Diwali: firecrackers and animals (new). Helplines 112, 1962, forest 1926 checked; cracker hours only from an issued order (Odisha 7–9 pm, 125 dB confirmed); no 24-hour vet promise | live 5 Nov |
| B2 | Dog-bite guide upgrade: title ≤44 chars plus suffix; category table; NCDC 2019 schedules; TT; RIG and monoclonals; human helplines (15400, 108/112); "for 15 minutes"; rabies FAQ from Rising; ICMR-NIE figures. A doctor checks the tables (your call) | 4 Nov |
| B3 | Supreme Court guide (same URL): "compliance reports due 17 Nov" tracker; Kerala (6 Nov) and Goa rows; fix the wrong feeding link and the doubled FAQ heading; context for the letter-petition link | draft 5 Nov, live 10 Nov, update 17 Nov |
| B4 / B14 | CPCSEA and IAEC titles and descriptions | 3–4 Nov |
| B5 | 1962 page: title, one-line answer, state table (number, hours, WhatsApp, free?) | 5 Nov |
| B6 | Directory place/type title template "Animal helpline numbers in {district}: rescue, shelters and vets" plus a state version; 5 examples; code ships after your yes | 5 Nov |
| B7 | AWBI page retargeted: helpline (verify 0129-2555700), complaints, recognition, recruitment | 7 Nov |
| B8 | NGO Darpan: "search by name" and "Darpan ID search" sections | Nov |
| B9 | FIR cluster: national ↔ state links (Telangana outranks the national guide), Karnataka page, recent FIRs, "section 11 punishment", "stray dogs complaint" | Nov |
| B10 | FAQ page: new Q&As; your /tag/faq/ option (A–E); FAQ page SEO title | Nov |
| B11 | Feeding and RWA: upgrade the 2 existing guides, including "Is feeding stray dogs illegal?"; use both "stray" and "community dogs"; old campaign untouched | 20 Nov |
| B12 | ABC (two pages: merge or split, your call) and a PCA Act 1960 explainer plan | Dec |
| B16 | Government vet hospital {city} template, top 10 cities | 25 Nov |
| B17 | State gaushala hubs: Rajasthan, Gujarat and UP pilot | Dec |
| B18 | "Who regulates animal welfare in India" hub (AWBI, CPCSEA/CCSEA, IAEC) | Nov |
| B19 | District answer box (nearest 1962 unit, vet hospital, top 3 organisations); data from listings, wording yours | Nov |
| B20 | Injured-animal guides: merge or differentiate the two; one water rule; Diwali block on the injured-bird page | before 5 Nov |
| B21 | (Suggestion) A "24-hour" Directory filter, if listings store hours | your call |

Publishing after a yes: REST in the logged-in browser (content, Rank Math title and description, keyphrases, FAQ box, a recycled media image). Then a live check: 200, index, canonical, schema, sitemap, the 720px column on a phone.

## C. Your decisions needed (nothing starts on these without you)
- **"GSR 864(E) explained" page:** your note says it cannibalises the campaign. Alternative: "GSR 864E" in the existing titles.
- **"After 5 Nov" lab rules page and the final-call push:** your 7 Nov hold.
- **GA4 event on objection sends:** needs your specific OK; Preview only; may duplicate the existing send count.
- **Newsletter, reels, posts:** you send; I draft only.
- **/tag/faq/ option; animal birth control owner; ABC merge; doctor review of the dog-bite tables.**

## D. Yours (I can't do them)
- Search Console: request re-crawl or removal of the old URLs; check crawl stats for Googlebot and Bingbot; Bing site verification if the key check needs it.
- Posting the UTM links.

## E. Experiments (pre/post in Search Insights, 28 days; checkpoints in Dates)
1. CPCSEA title → CTR (0.47% on 5,339 impressions).
2. 1962 state table → position for "1962 helpline number" (10.2 → ≤7).
3. AWBI retarget → CTR (0.29%).
4. Dog-bite title with "injection schedule" and "category" → impressions and position.
5. Supreme Court hub indexed before 17 Nov → clicks 15–24 Nov.
6. Quick-answer and FAQ blocks → AI Overview and People Also Ask citations, checked weekly.
7. District answer box → internal searches and Directory clicks.
8. "Know your rights" link box on community-dog News → guide entrances.
9. Hindi FIR and dog-bite guides → Devanagari impressions (8 weeks; only if you want Hindi pages).

UTM-vs-bio and newsletter subject tests are yours to run.

## Order of work
- **1–2 Nov:** step 0, finish the audit, A1, A2, A4, A5, A6. Drafts started: B1, B2, B4, B14, B20.
- **3–5 Nov:** B5, B6, A3 (the conflicts with traffic plus the root cause), A9. B1 live after your yes. B3 drafted.
- **6–12 Nov:** A3 long tail, A10, A11, B7, B18; B3 live by 10 Nov.
- **13–19 Nov:** B3 update on 17 Nov, B9, B10, B19; experiment checkpoints.
- **20 Nov–1 Dec:** B11, B16, B8; then B12, B17; measure against the baseline.

## Links

**Search Insights (wp-admin)**
- Rising: https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=rising
- P1: https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=keywords&view=p1
- P2: https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=keywords&view=p2
- Quick wins: https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=keywords&view=quick
- Conflicts (A3): https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=keywords&view=conflict
- Pages: https://animalwrites.in/wp-admin/admin.php?page=aw-search-insights&tab=pages
- Dates (A7): https://animalwrites.in/wp-admin/admin.php?page=aw-dates

**Files (repo folder `Claude outputs/`)**
- Analysis: `search-analysis-2026-11-01.md`
- Today's results and the /tag/faq/ options: `search-actions-2026-11-01.md`
- Outlines: `outline-diwali-animals-2026-11-01.md` (B1), `outline-dog-bite-guide-2026-11-01.md` (B2), `outline-sc-stray-dogs-2026-11-01.md` (B3)
- Audit results (step 1): `full-audit-2026-11-01.md`

**Pages by plan item**

| Item | URL |
|---|---|
| A1 | https://animalwrites.in/campaigns/lab-animals-rules-2026/ · https://animalwrites.in/about/ |
| A2 | https://animalwrites.in/ · https://animalwrites.in/directory/ |
| A3 | AWBI: https://animalwrites.in/resources/animal-welfare-board-of-india-awbi-explained/ (wrongly shown: https://animalwrites.in/library/public-notice-regarding-misuse-of-the-name-and-authority-of-the-animal-welfare-board-of-india/) · shelter: https://animalwrites.in/tag/animal-shelter/ (→ Directory type page) · ambulance: https://animalwrites.in/tag/animal-ambulance/ · 1962: https://animalwrites.in/resources/1962-helpline-mobile-veterinary-units/ · World Animal Day: https://animalwrites.in/resources/world-animal-day-4-october/ (+ https://animalwrites.in/library/observance-of-world-animal-day-on-4th-october-2026/) · ABC: https://animalwrites.in/library/animal-birth-control-rules-2023/ vs https://animalwrites.in/resources/animal-birth-control-abc-explained/ |
| A4 | https://animalwrites.in/knowledge/how-to-report-animal-cruelty/ · https://animalwrites.in/knowledge/animal-birth-control-abc-explained/ · https://animalwrites.in/legal-watch/observance-of-world-animal-day-on-4th-october-2026/ · https://animalwrites.in/resources/how-to-file-a-police-complaint-fir-for-animal-cruelty/ → https://animalwrites.in/resources/how-to-report-animal-cruelty/ |
| B1 | new: https://animalwrites.in/resources/diwali-firecrackers-and-animals/ (proposed) |
| B2 | https://animalwrites.in/resources/dog-bite-rabies-vaccine/ |
| B3 | https://animalwrites.in/resources/supreme-court-stray-dog-orders-explained/ · Library: https://animalwrites.in/library/supreme-court-judgment-of-19-may-2026-on-stray-dogs-2026-insc-506/ |
| B4 / B14 | https://animalwrites.in/resources/cpcsea-full-form-ccsea/ · https://animalwrites.in/resources/iaec-full-form-members-approvals/ |
| B5 | https://animalwrites.in/resources/1962-helpline-mobile-veterinary-units/ |
| B6 / B19 | e.g. https://animalwrites.in/directory/kerala/ · https://animalwrites.in/directory/maharashtra/mumbai/animal-shelter/ |
| B7 | https://animalwrites.in/resources/animal-welfare-board-of-india-awbi-explained/ |
| B8 | https://animalwrites.in/resources/ngo-darpan-registration/ |
| B9 | https://animalwrites.in/resources/how-to-report-animal-cruelty/ · https://animalwrites.in/resources/animal-cruelty-complaint-telangana/ |
| B10 | https://animalwrites.in/resources/animal-welfare-faqs/ · https://animalwrites.in/tag/faq/ |
| B11 | https://animalwrites.in/resources/rwa-or-society-stopping-you-from-feeding-dogs-what-to-do/ · https://animalwrites.in/resources/feeding-community-animals-good-practice/ |
| B12 | https://animalwrites.in/resources/animal-birth-control-abc-explained/ · https://animalwrites.in/resources/animal-birth-control-abc-rules-2023-explained/ · https://animalwrites.in/library/the-prevention-of-cruelty-to-animals-act-1960-as-amended-in-1982/ |
| B20 | https://animalwrites.in/resources/found-an-injured-animal-what-to-do-first/ · https://animalwrites.in/resources/found-an-injured-animal-what-to-do/ · https://animalwrites.in/resources/injured-bird-helpline-numbers-by-city/ |
| Sitemap | https://animalwrites.in/sitemap_index.xml |

## How I work
- Subagents for drafting and checks; I say who gets what before sending.
- No PRs. Small code goes to wordpress-dev via tools/aw-ship.sh, batched pushes, one deploy at a time.
- Live data edits go via wp-admin REST in the browser.
- Free tools only; OpenSEO cost estimate first (ask before >2,000 credits).
- `pending.md` updated after each item; Notion only when you ask.
- At the end: remove my worktree `/Users/abcom/Documents/GitHub/aw-search-1101`.

## Verification
- Code: `php -l`, PHPCS delta, the CI scripts, a green Deploy check, aw-ship, then `aw_deployed_head` matches the pushed commit.
- A1: PageSpeed mobile re-run (INP < 200 ms, CLS < 0.1, LCP < 2.5 s). A2: curl TTFB and cache headers before and after.
- A3: Conflicts count before and after the recompute. A4: curl chains show one hop.
- Each published page: status, robots, canonical, title length, JSON-LD, sitemap, a phone screenshot.
- 28-day Search Insights reads at each experiment checkpoint.
