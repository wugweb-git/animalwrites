> Status as of 24 Nov 2026 (checked against git): Partly done. help-now-keywords-2026-11-01.csv exists; no content-strategy file found; briefs not confirmed.

# Plan: keyword research and content strategy for the six "help now" Resources

## Context

The owner asked that the help-now content go through two skills first: keyword research (rampstack-seo:seo-keyword) and content strategy (searchfit-seo:content-strategy). Today's drafting started with the dog-bite guide (item 1, pasted, waiting on a yes and a doctor) and was moving to the 1962 page (item 2). Each page should now be built from a checked keyword cluster and a place in one strategy, not from the 1 Nov report alone.

Conflict to settle first: the owner's rule of 1 Nov (memory "no-copywriting") says to answer "I can't write this" instead of drafting copy. The brief from the coordinating session asked for full drafts. The dog-bite draft already in chat was written before that rule was re-read. The question below decides whether the deliverable per page is a **brief** (structure, query per section, verified facts and sources; the owner writes the words) or a **draft** (as now).

Nothing here touches the live site. All output is files in `Claude outputs/` plus chat.

## Data already in hand (no new tools needed)

- Search Insights, 28 days to 1 Nov, read today: Keywords tab (search per topic), Rising tab, Pages tab. 1962 cluster already pulled (e.g. "1962 helpline number" 443 impressions at 10.2, 0 clicks; "timing" 44; "is free or not" 39; "assam" 34; "not working" 11).
- `Claude outputs/action-register-2026-11-01.md` (932 rows: keyword → page, priority, action).
- `Claude outputs/full-report-and-todo-2026-11-01.md` (T3, T7, T8, T20, T22, T24; competitors §11.4: dogwithblog, kannananimalwelfare, earthlingstrust, vosd).
- Existing drafts to reuse, not redo: `outline-dog-bite-guide-2026-11-01.md`, `fir-state-pages-review-2026-10-25.md` + `fir-guide-state-facts-2026-10-25.md` + repo `tools/content/fir-by-state-2026-10-25.json` (commit 721ef7c), `feeding-rwa-merged-draft-2026-10-25.md` (keep the RWA URL, 301 the law guide to it; never re-run `tools/content/feeding-guides-2026-10-25.php`).
- `docs/seo/keyword-map.md` (15 Oct primary/secondary map) to update, not replace.
- No paid keyword volumes (Ahrefs has no API on this plan; no CSV exports). Volumes stay as GSC impressions, Trends index and the two owner-supplied figures (18,100 and 6,600/month).

## Steps

1. **Keyword sheet (Stage 1–3 of seo-keyword).** One CSV `Claude outputs/help-now-keywords-2026-11-01.csv`: keyword, impressions, clicks, position, Trends, intent (I/N/T), SERP note, cluster, role (primary/secondary), page. Pull every Search Insights row for the six clusters by searching: 1962, helpline, emergency, injured, bird, dog bite, injection, category, rabies, cruelty, FIR, complaint, section 11, feeding, RWA, stray. Plus the Rising rows. Read-only in the built-in browser.
2. **SERP check, read-only.** For each cluster's primary query, note what ranks on page 1 (state govt pages, image packs, competitor answer-first H1s) from a normal search; stop at any CAPTCHA. Gives the intent and the "what wins" note.
3. **Clusters and scoring (Stage 4).** Six clusters, one page each (no new pages, rule 8): dog bite/rabies; 1962; injured animal + bird (merge or split decided in T3); who-to-call (hub); cruelty/FIR + state pages; feeding/RWA. Score Opportunity + Fit − Difficulty; flag cannibalisation (e.g. "1962 mobile veterinary unit" on /tag/, rabies questions on study summaries, "animal helpline number {city}" on Directory pages).
4. **Content strategy (content-strategy skill).** One file `Claude outputs/help-now-content-strategy-2026-11-01.md`:
   - pillar = who-to-call guide (help-now hub, per T49); spokes = 1962, injured animal/bird, dog bite, cruelty/FIR (+ state pages), feeding/RWA; Directory city pages as the local layer;
   - per page: primary and secondary keyphrases, title pattern, the query each H2/FAQ answers, short-answer box target (40–60 words), internal links in and out, sources needed, what to verify;
   - calendar against the dated items (Diwali 8 Nov, dog-bite doctor review, SC 17 Nov) and the one-change-per-28-days rule for experiments E2 and E4;
   - success metrics = the KPIs already in the master plan (1962 position ≤7, low-CTR pages ≥1.5–2%).
5. **Per-page deliverable** (owner's answer: **briefs, plus tables**), in the order of the original task (2 → 6), each pasted for the owner, then STOP for a yes. Each brief has:
   - a **data box**: every query for the page from Search Insights Rising, Quick wins, P1, P2, P3 and Pages (and the matching action-register sections A, B.P1, B.Add-as-secondary, B.Guide updates, B.FAQ answers, B.Gaps, C, D), with impressions and position, and where on the page each is answered (heading, FAQ, short answer, secondary keyphrase);
   - section order and the query each heading/FAQ serves; no prose: the owner writes the words;
   - factual tables filled from official sources only (1962 states, dog-bite categories and schedules, helplines), each cell sourced or marked "not verified".
   - Opportunities stay untouched; "AWBI recruitment 2026" goes to the AWBI guide note, not Opportunities.
   - The dog-bite draft already pasted is reduced to its brief and tables; its prose is withdrawn unless the owner says to keep it. The 1962 state table only with numbers from official state pages (Karnataka, Kerala, Tamil Nadu, HP, Assam, UP, MP found so far; hours differ: e.g. Karnataka call centre 24×7, vans 9–5; TN vans 8–8; HP 9–5 working days), every other state left blank and marked "not verified".
6. **Dog-bite (item 1) re-check** against the new cluster sheet; send only the differences, if any.
7. **Hand-over notes** to the shared notes folder; `docs/pending.md` and `docs/seo/keyword-map.md` changes only after the owner's yes on a page (both are repo docs, committed onto wordpress-dev).

## Verification

- CSV row counts match the Search Insights searches; spot-check 5 rows against the Keywords tab.
- Every number in a brief/draft carries a source URL read today, or sits in an "unverified" list.
- No live write, no Rank Math change, no publish until the owner's yes on that page's text.
