> Status as of 24 Nov 2026 (checked against git): Partly done. Copy items gated on the owner's yes; see docs/pending.md.

# Content and strategy quick fixes (1 Nov 2026)

## Context
Search Console data shows page-1 pages with almost no clicks, and searches split across several of our pages. This session owns the copy and keyword-ownership fixes: CPCSEA, IAEC, AWBI, World Animal Day, NGO Darpan, Library "Download PDF" titles, the regulator hub outline, the FAQ page title, and the keyword conflicts the owner has already decided. Copy goes live only after the owner's yes on the exact text. Keyword moves are data edits.

## Findings from read-only checks (1 Nov)
- **Writing Rank Math fields:** they are not in `wp/v2` REST (post meta shows only aw_faq_subject, aw_faq_line, kits_last_reviewed and similar). Rank Math's own route `/rankmath/v1/updateMeta` exists and works with the wp-admin nonce (`wp.apiFetch.nonceMiddleware.nonce`; `wpApiSettings` is missing on the editor screen). It is used for `rank_math_title`, `rank_math_description` and `rank_math_focus_keyword` on posts and terms. Body text and H1 go through `wp/v2/posts/ID` (`content.raw`, `title`).
- **Title rule:** `aw-seo-extras/includes/titles.php` adds " – Animal Writes" and drops it when the result is over 60 characters. A ≤44-character base keeps the suffix. The World Animal Day guide uses `aw_seo_title_yearly` and moves the year automatically.
- **Baseline (Search Insights, GSC daily rows, 4–31 Oct):**

| Page | Impr | Clicks | Pos | Top queries (keyword table, 28 d) |
|---|---|---|---|---|
| CPCSEA (13868) | 4,188 | 13 | 6.6 | ccsea 1,892/11/6.1 · cpcsea full form 1,767/1/7.5 |
| IAEC (13859) | 750 | 1 | 7.2 | iaec full form 425/1/5.6 |
| AWBI (13800) | 1,091 | 3 | 6.6 | awbi 468/3/5.3 · AWBI (on notice 145) 452/0/7.4 |
| NGO Darpan (17308) | 267 | 3 | 8.2 | search by name 45/0/9.1 · darpan id search 39/0/9.9 |
| WAD guide (13797) | 270 | 0 | 7.3 | world animal day 54/0/11.9 |
| Draft lab rules (13844) | 1,201 | 80 | 4.1 | — |

  The brief's figures (5,356 / 1,277 / 1,708) cover a longer window. The table above is recorded as the E1/E3/E10 baseline in the shared notes.
- **AWBI facts at source:** awbi.gov.in gives one phone number, 0129-2555700, labelled "Contact Info" and "Board's Headquarters", Mon–Fri 9:00–5:30. The "SMS Sender ID" is AWBIND. Grievances and cruelty complaints go through the online Grievance form, which needs Sign In or Sign Up. The site shows **no separate grievance phone numbers** and **no recruitment page**. It only has a notice that every vacancy is advertised publicly and that offers come from the official email or registered post. Copy will say exactly that.
- **Library PDFs:** all four items have a PDF attachment: 117 → 178, 118 → 180, 13844 → 13845, 14266 → 14267. The SC title already ends "Official PDF". The attachment GUIDs for 178 and 180 point to the old staging host, so the served link needs checking before the title promises a download.
- **Conflict pages today:**
  - The two AWBI notices (145, 13505) have "animal welfare board of india" as focus keyword and no link to the guide.
  - Chandigarh News 11508 has "animal shelter" and no link to the Directory.
  - The 1962 release 11758 ("mobile veterinary units") and `/tag/mobile-veterinary-unit/` have no link to the 1962 guide.
  - `/tag/ambulance/` is already canonical to `/tag/animal-ambulance/`.
  - The WAD Library item already links to the guide.
  - About (58) holds "animal writes" and has no in-text link to Home.
- **No national Directory type page exists.** `/directory/animal-shelter/` returns 404. `/directory/?type=animal-shelter` 301s to `?tag=animal-shelter`, which is canonical to `/tag/animal-shelter/`. So the tag page *is* the Directory's type view, and the owner's "Directory type page" target needs clarifying (question below).
- **ABC merge:** `/resources/animal-birth-control-abc-explained/` (128, about 900 words, old 301 from /knowledge/) and `/resources/animal-birth-control-abc-rules-2023-explained/` (9032, about 900 words).

## Owner answers (1 Nov)
- **Copy:** I draft each page's text from that page's own sourced facts. The owner approves each exact text before it goes live.
- **Animal shelter and animal ambulance:** the tag pages own both. Only the Chandigarh News item (11508) drops "animal shelter" and links to `/tag/animal-shelter/`. `/tag/animal-ambulance/` keeps "animal ambulance". `/tag/ambulance/` drops it from its focus keyword, and its canonical already points there.

## Steps (one page at a time; nothing goes live without its yes)
1. **Baseline note:** write the table above to the shared notes folder as `baseline-E1-E3-E10-2026-11-01.md`.
2. **CPCSEA (T4), IAEC (T5), AWBI (T14):** paste the SEO title (base ≤44), description, H1, a 40–60 word short-answer box and the link block (IAEC page plus Library draft). Text is drafted per the answer below. After the yes: updateMeta, then content and title via wp/v2. Old values go in the notes so they can be restored.
3. **Conflicts (T12, data edits already decided):**
   - Set the focus keyword through updateMeta and record before and after values in `_aw_focus_history`-style notes.
   - AWBI: drop it from 145 and 13505. The guide keeps "animal welfare board of india" first.
   - Mobile veterinary unit: drop it from 11758 and from the tag term 1028. Add it to 13798, where it is already present.
   - "Animal writes": drop it from About (58). Home keeps it.
   - "Animal shelter" and "animal ambulance": per the answer below.
   - World Animal Day: the guide keeps it, and the Library item 142 keeps its circular keyword.
   - **Links:** each losing page gets one link to the owner page. For Library and News items, the owner page goes in the hand-set related field `aw_rel_resource` / `aw_rel_legal` (related.php puts hand-set links first). That needs no new copy. Terms use the existing button map `AW_TAG_MAIN_PAGES` in `animal-writes-core/includes/sections.php`, which is a one-line code change on wordpress-dev plus a ship. About → Home: the logo link is enough, so no copy is added.
   - **ABC:** the owner decided the Library item owns the law query. Propose the 301 (keep 128, the older URL with its /knowledge/ history, and 301 9032 to it) and paste for yes. The merged text is the owner's call.
4. **World Animal Day (T32):** paste the guide's title and description for 2027 and the Library item's one-line pointer.
5. **NGO Darpan (T28):** read the portal's NPO Directory in the built-in browser first, because it renders with JavaScript and curl sees nothing. Then paste two sections, "Search an NGO by name" and "Search by Darpan ID", plus a title tweak.
6. **Library titles (T39):** confirm each PDF link opens. Then paste "… PDF" titles for 13844, 117 and 118. The SC title is already done.
7. **Hub outline (T25):** sections and links only, for the owner's decision.
8. **FAQ page (T23, option B decided):** paste the SEO title with "animal welfare FAQ" for 7604. Option C is checked on 17 Nov.
9. **Coordinator additions** (heads audit sections, T43 boxes, T41, T30/T49): only after items 1–8. Pasted in batches. The five Animal-issues boxes go to that session.

## After each yes: checks
- 200, index, self-canonical.
- `<title>` ≤60 with the suffix.
- JSON-LD parses.
- The URL is in its sitemap (`post-sitemap.xml`, `aw_legal-sitemap.xml`, `post_tag-sitemap.xml`).
- LiteSpeed purge shown by a fresh fetch.
- `docs/pending.md` updated by item title (detached worktree on `origin/wordpress-dev`, commit, push, no PR).

## Not done here
- Celebrity tails are never used.
- The dismiss list belongs to Tech.
- No campaign 13848 edits.
- No social posting or link-asking.
