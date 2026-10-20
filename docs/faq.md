# FAQ system

Owner-approved spec, 20 Oct 2026. Code: `plugins/animal-writes-core/includes/faq.php`; schema: `plugins/aw-seo-extras/includes/schema.php`; accordion style and script: Kits `kit.css` / `kit.js` (`kit-faq`).

## Rules

- One FAQ per page, 3 to 6 questions. Fewer than 3: no FAQ section and no FAQPage schema.
- Each answer: first sentence answers and names the subject; at most 50 words; one link (the section or the source); dates j M Y with the calendar icon.
- The FAQPage node is parsed from the rendered accordion, so its text is the visible text word for word.
- No FAQ on News, Research, Opportunities, format tags, About, Contact, policies, Home, News and Resources pages. Guides keep their own Rank Math FAQ block.

## Where it shows

| Page | Questions | Position |
|---|---|---|
| Directory listing | registry A (data) | after Support, before Sources (`single-aw_listing.html`) |
| Library document | registry B (data) | last in Content, before Sources (`single.html`) |
| Organisation type, service, category | approved topic items | after the list, before Share (`term-faq` block) |
| Directory, Volunteer, Claim, Opportunities | approved topic items | after the list (end of the page content), page 1 only, no filters |

## Registry (data-driven)

`aw_faq_registry()`: question template, fields needed, answer builder, priority, search pattern. Nothing is stored per page. Candidates are kept when their fields have values, deduped by the fact they state (e.g. "Does X offer adoption?" from the Adoption service tag repeats the services answer and is dropped), sorted by priority, capped at 6.

Library: "Who issued …" is not asked for Acts and Rules (the stored issuer is whose copy it is, not the law-maker). Three fields were added for Library documents (box "FAQ fields"): Hindi version link (`aw_file_hi_url`), Issued under (`aw_legal_act`), Key sections (`aw_key_sections`, Acts and Rules only, one per line `number | one-line summary | link`). Section questions come only from that list.

## Topic items

Meta `aw_faq` (term or page): JSON list of `{q, a, sources[{name,url}], keyword, status draft|approved, reviewed Y-m-d}`. Edited on the term screen or the hub page's "FAQ" box. Drafts are never shown. Tools → FAQ lists every draft with its sources; tick and Approve.

## Experiment

10% of eligible listings and Library documents (crc32 of salt + ID, stable) show no FAQ until the owner rolls out (Tools → FAQ). Option `aw_faq_experiment` holds start, weeks (6), pct (10), salt, on. The page context pushed to GTM has `faq_group` = faq | holdout | none on listings and Library documents.

Compare after 6 weeks: Search Console impressions, clicks and CTR for queries containing "contact number", "pdf", "in hindi", "address" on FAQ pages vs holdout pages (page lists: `wp aw-faq holdout`), and GA4 sessions referred by chatgpt.com, perplexity.ai and gemini.google.com by landing page.

## Commands

- `wp aw-faq report` — counts by type and group, questions per FAQ, drafts waiting.
- `wp aw-faq check [--verbose]` — on every page: 3–6 questions or none, schema text = visible text, no duplicate question, ≤ 50 words, one link, holdout pages show none; reports pages whose focus keyphrase is in fewer than 2 questions.
- `wp aw-faq holdout` — CSV of eligible pages: url, type, group, questions.
- `wp aw-faq import <file.json>` — add topic drafts (`{"post_tag:slug"|"category:slug"|"page:slug": [{q, a, sources, keyword}]}`); existing questions are kept.
- `tools/ci/faq_live_check.py <url>…` — fetches live pages and compares the visible accordion with the FAQPage JSON-LD.
