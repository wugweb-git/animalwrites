# FAQ system

Owner-approved spec, 20 Oct 2026. Code: `plugins/aw-seo-extras/includes/faq.php`; schema: `plugins/aw-seo-extras/includes/schema.php`; accordion style and script: Kits `kit.css` / `kit.js` (`kit-faq`).

## Rules

- One FAQ per page, 3 to 6 questions. Fewer than 3: no FAQ section and no FAQPage schema.
- Each answer: first sentence answers and names the subject; at most 50 words; one link (the section or the source); dates j M Y with the calendar icon.
- The FAQPage node is parsed from the rendered accordion, so its text is the visible text word for word.
- No FAQ on News, Research, Opportunities, format tags, About, Contact, policies, Home, News and Resources pages.
- Resources guides (owner, 25 Oct 2026): their own questions in the guide's FAQ box (meta `aw_faq`, approved items only), shown by the `item-faq` block at the end of the content with our FAQPage schema. A Rank Math FAQ block on a guide (from the editor or a publish script) is converted on save: questions and answers word for word, the block and its heading removed, each conversion's removed markup and the FAQ as it was before kept in `_aw_faq_rm_block` (a list, newest last). One FAQ system for the whole site; Rank Math keeps titles, descriptions, sitemaps and redirects.

## Where it shows

| Page | Questions | Position |
|---|---|---|
| Directory listing | registry A (data) | after Support, before Sources (`single-aw_listing.html`) |
| Library document | registry B (data) | last in Content, before Sources (`single.html`) |
| Organisation type, service, category | approved topic items | after the list, before Share (`term-faq` block) |
| Directory, Volunteer, Claim, Opportunities | approved topic items | after the list (end of the page content), page 1 only, no filters |
| Resources guide | the guide's own approved items (FAQ box) | `item-faq` block, last in Content, before Sources (`single.html`) |

## Registry (data-driven)

`aw_faq_registry()`: question template, fields needed, answer builder, priority, search pattern. Nothing is stored per page. Candidates are kept when their fields have values, deduped by the fact they state (e.g. "Does X offer adoption?" from the Adoption service tag repeats the services answer and is dropped), sorted by priority, capped at 6.

- A listing's services answer "based on type" is shown but does not count toward the 3 (owner, 20 Oct 2026): a listing needs 3 answers from its own data.
- Library questions use the document's short name: the "Short name" field (`aw_short_name`, e.g. PCA Act 1960, ABC Rules 2023), else the primary focus keyphrase. The first answer gives the full official title once.
- The accordion is texturized before the schema is parsed from it (WordPress texturizes the page: " - " → " – ", "24x7" → "24×7"), so both hold the same characters.

Library official releases and circulars (owner, 25 Oct 2026): "When was the … released / issued?" is asked without a document number (they have none), naming who released it; official releases with no PDF also get "Where can I read the …?" (the issuer's page). Letter templates get hand-written topic questions instead (no data fields).

Library: "Who issued …" is not asked for Acts and Rules (the stored issuer is whose copy it is, not the law-maker). Four fields were added for Library documents (box "FAQ fields"): Short name (`aw_short_name`), Hindi version link (`aw_file_hi_url`), Issued under (`aw_legal_act`), Key sections (`aw_key_sections`, Acts and Rules only, one per line `number | one-line summary | link`). Section questions come only from that list.

Resources guides keep their own Rank Math FAQ block; AW SEO Plus → FAQ counts them in a row of their own (guides with and without one; research summaries are not counted, as Research gets no FAQ).

## Topic items

Meta `aw_faq` (term or page): JSON list of `{q, a, sources[{name,url}], keyword, status draft|approved, reviewed Y-m-d}`. Edited on the term screen, or in the "FAQ" box of a hub page or a Resources guide. Drafts are never shown. AW SEO Plus → FAQ (was AW SEO Plus → FAQ; the old address redirects) lists every draft with its sources; tick and Approve.

## Experiment

10% of eligible listings and Library documents (crc32 of salt + ID, stable) show no FAQ until the owner rolls out (AW SEO Plus → FAQ). Option `aw_faq_experiment` holds start, weeks (6), pct (10), salt, on. The page context pushed to GTM has `faq_group` = faq | holdout | none on listings and Library documents; GTM variable `DLV - faq_group` sends it on the GA4 Google tag (GTM version 8, 20 Oct 2026) and GA4 has the event-scoped custom dimension "Has FAQ" (`faq_group`).

Compare after 6 weeks: Search Console impressions, clicks and CTR for queries containing "contact number", "pdf", "in hindi", "address" on FAQ pages vs holdout pages (page lists: `wp aw-faq holdout`), and GA4 sessions referred by chatgpt.com, perplexity.ai and gemini.google.com by landing page.

## Commands

- `wp aw-faq report` — counts by type and group, questions per FAQ, drafts waiting.
- `wp aw-faq convert-guides [--dry-run]` — move every guide's Rank Math FAQ block into its own FAQ (also happens on each save); flags answers over 50 words, more than one link, and FAQs over 6 questions (only the first 6 show). `wp aw-faq undo-guide <id>` reverses the latest conversion: the block back and the guide's FAQ as it was before.
- `wp aw-faq check [--verbose]` — on every page: 3–6 questions or none, schema text = visible text, no duplicate question, ≤ 50 words, one link, holdout pages show none; reports pages whose focus keyphrase is in fewer than 2 questions.
- `wp aw-faq holdout` — CSV of eligible pages: url, type, group, questions.
- `wp aw-faq import <file.json>` — add topic drafts (`{"post_tag:slug"|"category:slug"|"page:slug"|"library:slug": [{q, a, sources, keyword}]}`; `library:` is for letter templates); existing questions are kept.
- `tools/ci/faq_live_check.py <url>…` — fetches live pages and compares the visible accordion with the FAQPage JSON-LD.
