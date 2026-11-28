# InsightWeb keyword plan — for a new Claude Code session (28 Nov 2026)

Hand this whole file to a new Claude Code session working on the Animal Writes WordPress site. It is self-contained: read it top to bottom before doing anything. The paste-ready kickoff prompt is at the very end.

---

## 0. Context

**Site / stack.** animalwrites.in — WordPress, theme *Kits by Wugweb*, plugins: Kits Core, RankWeb, **InsightWeb**, SourceWeb, MailWeb, PlanWeb, DirectoryWeb. SEO fields are Rank Math. InsightWeb (Search Insights, `admin.php?page=aw-search-insights`) ingests Search Console + Bing and scores keywords; it is the source of truth for the buckets below. Resource pages live at `/resources/...`, legal docs at `/library/...`, news at `/news/...`, directory at `/directory/[state]/[city]/[type]/`.

**Why this plan.** An SEO read on 17 Nov produced an action plan that shipped 18–24 Nov. A re-validation on 27 Nov found the keyword data had **drifted**: new imports and new pages pushed the Conflicts bucket back up and grew Quick wins. This plan resolves the current conflicts, harvests the Quick wins, sets the P1/P2 approach, and pulls fresh suggestions from InsightWeb and the external SEO tools.

**Numbers as of 27 Nov 2026 (RE-VALIDATE before acting — they drift daily):**

| Bucket | Count |
|---|---|
| All | 14,902 |
| P1 | 318 |
| P2 | 2 |
| P3 | 14,582 |
| Gaps | 1 |
| Conflicts | 10 |
| Quick wins | 811 |
| Tracked, not ranking | 155 |
| Entities | 293 |

**FIRST STEP, ALWAYS:** open InsightWeb → Keywords, read the live bucket counts and re-pull the Conflicts and Quick wins lists. Work from what is live today, not from the tables in this file. The bucket filter uses a URL param: `&view=p1|p2|p3|conflict|quick|gap|tracked|entities` on `admin.php?page=aw-search-insights&tab=keywords`.

---

## 1. Rules (do not break)

- **Branch only. No deploy, no merge, no PlanWeb approval without the owner's explicit yes.** Report and stop at the gate.
- **Copy gate:** any user-visible wording (titles, descriptions, body, FAQ) is drafted, pasted for the owner's yes, then shipped. Never publish copy unasked.
- **Lab rules are owner-gated:** the Lab page and the lab-rules keyphrases change only on the owner's word. There is no "Post" phase.
- **House SEO rules (Animal Writes = client site):** rendered title ≤ 60 chars; description 140–160 (working target); one H1; headings are claims, not questions; a shown date matches its schema date. **Never** propose HowTo, review stars, llms.txt or a keyword-density target. **FAQPage only where the page visibly shows the Q&A** (Google dropped FAQ rich results 7 May 2026 — it earns no Google feature, but still helps AI answers).
- **Rank Math free = 5 keyphrases per post** (focus + 4 secondary). Pick the top 4 by impressions; do not paste every variant.
- **One owner page per phrase.** When two pages want one phrase, name both and let the owner pick.
- **Never delete.** Unpublish to draft. Never touch directory card sizes or layout.
- **Facts flagged `[verify]`** (legal sections, medical schedules) must be confirmed against a primary source before any copy ships.

Mechanism note: moving a focus keyphrase off a press/news item and onto a resource page is what RankWeb's **`seo-focus`** does (shipped in PR #165). Use it rather than hand-editing where possible.

---

## 2. Workstream A — Resolve all 10 conflicts

A conflict = a focus keyphrase on the wrong page, or a focus keyphrase that is also on the Exclude list. Fixing it is a Rank Math / InsightWeb **setting** change — no copy. Re-pull the live Conflicts list first; the 10 seen on 27 Nov were:

**Mechanical — move the evergreen keyphrase off the News/library item to the resource owner (do these, 6):**

| Keyphrase | Currently on | Proposed owner page |
|---|---|---|
| animal testing | /news/axleres-ai-wins-smart-bio-award-2026… | /resources/alternatives-to-animal-testing-india/ |
| abc rules | /news/dog-mauls-girl-angry-society-moves-hc-against-abc-rules/ | /resources/animal-birth-control-abc-explained/ |
| abc rules 2023 | /library/constitution-of-committees-…abc-rules-2023… | /resources/animal-birth-control-abc-explained/ |
| animal welfare board | /news/animal-welfare-board-seeks-urgent-probe… | /resources/animal-welfare-board-of-india-awbi-explained/ |
| rescue and rehabilitation | /library/advisory-on-rescue-and-rehabilitation-…floods… | the nearest rescue resource (confirm which; e.g. the emergency-rescue hub) |
| animal bite | /resources/animal-bite-cases-eastern-india/ (also on Exclude, 0 impr) | either clear it as that page's focus keyphrase, or take "animal bite" off the Exclude list — owner picks which; default: clear the focus keyphrase, leave Exclude as is |

**Clear and do NOT target — no new page (2):** Ruling 28 Nov (wugweb-seo, from InsightWeb demand). The concept head terms carry negligible demand — `animal rights` ≈ 11 impr / pos ~15, `animal welfare` ≈ 36 impr / pos ~38, both only parked on News posts — and the real "animal right*" traffic is **brand confusion** for "Animal Writes" (`animal rights.in` 363 impr / 192 clicks / pos 2.1; `animalright in website` 385 impr / 90 clicks), already won by the homepage. The concept SERPs are owned by Wikipedia/PETA; not winnable, and a page would cannibalise the laws/welfare explainer + library.

| Keyphrase | Currently on | Action |
|---|---|---|
| animal rights | /news/hundreds-march-for-animal-rights-in-city… | clear the focus keyphrase; do not target; do not build |
| animal welfare | /news/ashokas-complex-relationship-with-animal-welfare/ | clear the focus keyphrase; at most add as a *secondary* keyphrase on the existing animal-welfare-laws / AWBI explainer — never primary, never a new page |

Two distinctions this ruling depends on:
- `animal welfare board` (in the mechanical table above) is a **named entity with real intent** → a genuine keyphrase→page move to `/resources/animal-welfare-board-of-india-awbi-explained/`. Do **not** lump it in with the vague head term `animal welfare`.
- The **brand-confusion cluster** (`animal rights.in`, `animalright in website`, and `animal writes` misspellings — ~750+ impressions, high clicks, already pos 1–2) is a **wugweb-strategy / brand** task: make the brand / About / home page clearly own "Animal Writes" and disambiguate it from "animal rights". It is not an SEO content page and is out of scope for this plan — flag it to the owner.

**Owner-gated — do NOT touch without the owner's word (2):**

| Keyphrase | Currently on |
|---|---|
| draft laboratory animals rules 2026 | /library/draft-laboratory-animals-rules-2026/ |
| lab animal rules | /resources/lab-animal-rules-today-vs-draft-2026/ |

Also clear **item 21230** (admin action — still holding "lab animal rules" per the 24 Nov note), then recheck Conflicts.

**So:** all 8 non-lab conflicts are do-now (the 6 keyphrase→page moves in the table above + clearing `animal rights` and `animal welfare` off their News posts); only the **2 lab items** need the owner's word. No conflict needs a new page.

**Target:** Conflicts down to the 2 owner-gated lab rows. Expect the count to keep re-growing as new News/library items import — treat conflict-clearing as a recurring sweep, not a one-off.

---

## 3. Workstream B — Harvest the 811 Quick wins

Quick wins are position ~8–11 with real impressions and near-zero clicks — the highest-ROI bucket. Work clusters, not rows.

1. **Re-pull the live Quick wins** (`&view=quick`), and group by (a) owner page and (b) suggested action ("Update the page" vs "Add as secondary keyphrase"). Record the top ~15 collecting pages by impressions.
2. **Update-the-page cluster** (has a page, poor rank): tighten title (≤60), description (140–160) and the answer-first opening on the top collectors. The **1962 / helpline / animal-ambulance** hub is by far the biggest — e.g. `animal ambulance number` ≈ 1,465 impressions at pos ~8.2, `1962 helpline number timing` ≈ 239 @ 7.1, `1962 helpline number` ≈ 158 @ 9.2. Do this cluster first. Copy gate applies.
3. **Add-as-secondary cluster** (city/local variants ranking via directory state/city pages): attach the exact phrase as a secondary keyphrase on the matching existing directory page. **No new pages.** Cap 5 keyphrases per page, top 4 by impressions.
4. Re-check each touched page against the house SEO rules (§1) before it ships.

---

## 4. Workstream C — P1 (318) and P2 (2)

- **P1 — defend and re-route, do not chase rank.** These already rank and pull impressions. Work = the ownership fixes in A, CTR polish (title / description / first line) on the pos 3–7 pages, and leaving the winners alone. No new pages.
- **P2 — effectively empty (2 rows):**
  - `draft prevention of cruelty to animals 2026` — needs its News explainer refreshed + a focus keyphrase (lab-adjacent → owner's word).
  - `animal aid mumbai` — the one Gap (pos ~32); strengthen the Mumbai directory/shelter page.
  - Then decide with the owner whether to keep P2 as a tier at all (it has never held more than 3).

---

## 5. Workstream D — Pull more suggestions (read-only research, run FIRST)

Before committing to more content, mine the surfaces not yet used and bring back a short "extra suggestions" list:

- **InsightWeb tabs:** Rising (fresh trending terms to catch early), Pages (per-page aggregation), Links (internal-link gaps), Clarity (behaviour), Server (crawl/index health), Checklist (the monthly live-check queue — already surfaces big quick wins).
- **External tools (read-only; mind credits):** OpenSEO (search opportunities, site audit, GA4/Search Console), OpenRush (site audit, competitor and keyword gaps), Ahrefs (site-audit issues, content gaps). Note which flag something InsightWeb does not.

Deliver the findings to the owner as a short list; do not act on them without a yes.

---

## 6. Owner decisions still open (carried from the Status page)

- Approve the 32 PlanWeb entries (created 18 Nov, none approved).
- Move "animal cruelty laws" from News 9718 → the guide 29392.
- "world animal day 2026" keyphrases on 13797; the 9029 Rank Math title; the 13797 excerpt (Berlin 1925); the Dolphin Day image caption ("April 14").
- Extend the Uttar Pradesh directory description (127 → 140+ chars).
- Clinician sign-off on the rabies dose schedule (days 0/3/7/28 + RIG) before relying on it.
- The 2 lab-rules conflicts; clear item 21230. (`animal rights` / `animal welfare` are NOT owner decisions — they are cleared and not targeted per §2.)
- Brand-confusion cluster (`animal rights.in` etc.): a brand/strategy task, not an SEO page — decide separately.
- Whether to keep the P2 tier; when the Hinglish keyword phase turns on.

---

## 7. Verification

- After the conflict sweep: re-read the InsightWeb Conflicts count; it should fall to the owner-gated rows only.
- After any page change: run the **wugweb-seo audit** (`check-page.mjs`, `score-page.mjs`) on the rendered page, or read it back live — confirm title ≤60, description 140–160, one H1, correct schema, visible FAQ if FAQPage is present.
- 28 days after shipping: re-read InsightWeb for the touched rows — clicks, position, and the health flags ("No canonical owner", "Owned by News", "poor rank", "Should be a focus keyphrase").
- Update `docs/pending.md` and the Notion "8. Status & Pending" log with what shipped and what stayed gated.

---

## 8. Recommended sequence

1. Re-validate live buckets (§0) + Workstream D research pull.
2. Workstream A — the 6 mechanical conflict moves + the animal-bite fix + clearing animal rights/animal welfare (8 total), via `seo-focus`.
3. Workstream B — the helpline/ambulance quick-win cluster (biggest prize), then the directory secondary-keyphrase cluster.
4. Workstream C — P1 CTR polish; clear P2.
5. Hold for the owner: the 2 lab conflicts and all §6 decisions.

---

## 9. Paste-ready kickoff prompt for the new Claude Code session

```
Read "insightweb-keyword-plan-2026-11-28.md" in full before doing anything. It is the plan.

Do this on the Animal Writes WordPress site (animalwrites.in), branch only — no deploy, no merge, no PlanWeb approval without my explicit yes.

1. Re-validate: open InsightWeb → Keywords, read the live bucket counts, and re-pull the Conflicts and Quick wins lists. Work from what is live today, not the numbers in the file. Report the current counts back to me.

2. Workstream D (read-only) first: pull extra suggestions from InsightWeb's Rising/Pages/Links/Clarity/Server/Checklist tabs and from OpenSEO/OpenRush/Ahrefs. Give me a short list. Act on none of it yet.

3. Workstream A: make the mechanical conflict fixes with seo-focus — move each evergreen keyphrase off its News/library item to the resource owner in the file's table (incl. "animal welfare board" → /resources/animal-welfare-board-of-india-awbi-explained/), and fix the "animal bite" Exclude collision. Also clear "animal rights" and "animal welfare" off their News posts — do NOT target them and do NOT build a page (ruling in §2). Leave only the 2 lab-rules conflicts for me. Then re-read the Conflicts count and report. Flag the brand-confusion cluster (animal rights.in etc.) to me as a separate brand task, don't action it.

4. Workstream B: re-pull the Quick wins grouped by page and action type; draft the title/description/opening changes for the 1962/helpline/ambulance cluster and paste them for my yes before shipping; attach city variants as secondary keyphrases on the matching directory pages (cap 5 per page, top 4 by impressions).

5. Workstream C: P1 CTR polish (title/description/first line on pos 3–7 pages, drafts for my yes); clear the 2 P2 rows (the Mumbai gap page; the cruelty-2026 explainer is lab-adjacent, so hold for my word).

Rules: title ≤60, description 140–160, one H1, headings are claims, FAQPage only where the Q&A is visible, no HowTo/review-stars/llms.txt/keyword-density, never delete (unpublish to draft), don't touch directory layout. Confirm [verify] legal/medical facts against a primary source before shipping copy.

When done with each workstream, report: branch and commit, what changed per item, the new Conflicts count, and anything you couldn't do or that's waiting on me. Update docs/pending.md and the Notion "8. Status & Pending" log.
```

## Not in this request
- Shipping, merging, or approving PlanWeb entries — all owner-gated.
- Lab-rules page or keyphrase changes — owner's word only.
- Any copy going live without the paste-and-yes gate.
