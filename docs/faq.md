# FAQ system

Owner-approved spec, 20 Oct 2026; FAQ on every page, 31 Oct 2026. Code: `plugins/aw-seo-extras/includes/faq.php` (the feature, generic), `faq-overview.php` (AW SEO Plus → FAQ), `plugins/animal-writes-core/includes/faq-data.php` (the site's automatic questions and rules, through filters); schema: `plugins/aw-seo-extras/includes/schema.php`; accordion style and script: Kits `kit.css` / `kit.js` (`kit-faq`).

## Rules

- One FAQ per page, 3 to 6 questions (AW SEO Plus → Settings → FAQ: fewest, fewest on a post, most, longest answer). Fewer than 3: no FAQ section and no FAQPage schema. Posts (Resources guides): 2 to 6 (owner, 21 and 25 Oct 2026).
- Each answer: first sentence answers and names the subject; at most 50 words; one link (the section or the source); dates j M Y with the calendar icon.
- The FAQPage node is parsed from the rendered accordion, so its text is the visible text word for word. Its `isPartOf` / `about` point at a node the page's graph has (else the page's own node).
- One FAQ per page: a page with a Rank Math FAQ block in its content keeps that one and shows none from its box (today: campaign 13848, frozen until 7 Nov).
- Resources guides (owner, 25 Oct 2026): a Rank Math FAQ block on a guide (from the editor or a publish script) is converted on save into the guide's FAQ box: questions and answers word for word, the block and its heading removed, each conversion's removed markup and the FAQ as it was before kept in `_aw_faq_rm_block` (a list, newest last). One FAQ system for the whole site; Rank Math keeps titles, descriptions, sitemaps and redirects.

## The FAQ box (every page, owner 31 Oct 2026)

- Every public post type and every public taxonomy has the FAQ box on its edit screen: pages (forms pages such as Contact, Volunteer, Claim; policy pages such as Terms, Privacy, Disclaimer), posts, Library, campaigns, Directory listings, News, any future type; tags and categories. AW SEO Plus → FAQ → "FAQ box on": one checkbox per post type and taxonomy, ticked = has the box; all ticked by default, and a type registered later has the box until it is unticked (administrators save it; the unticked ones are stored in option `aw_faq_off_types`). No list of Animal Writes types in the plugin: the site's rules are in `faq-data.php` (filters `aw_faq_registry`, `aw_faq_context`, `aw_faq_show_here`).
- A page shows its FAQ only when its box has enough questions to show (the minimum).
- Automatic questions (Directory listings, Library documents: the registry below) are in the box filled in, marked **Auto**. Untouched, they follow the data. Edited, they are the page's own (**Edited**) and the data no longer changes them; "Back to automatic" undoes that. **Remove** hides one; **Order** moves rows. New data later adds its question at the end.
- Own questions (**Own**): written in the box or imported as drafts; shown once **Approved**.
- Each row says whether the page shows it and why not (draft, removed, repeats a question above, over the limit of 6), and the box says when the page shows no FAQ.
- Storage: meta `aw_faq` (post or term), JSON list in the box's order. Own rows `{q, a, sources[{name,url}], keyword, status draft|approved, reviewed Y-m-d}`; automatic rows `{auto: id, state: auto|edited|removed}` (+ `q`, `a` when edited). A box in its default order with nothing edited or removed stores no automatic rows.

## Where it shows

| Page | Position |
|---|---|
| Directory listing | after Support, before Sources (`single-aw_listing.html`, `item-faq` block) |
| Library document, Resources guide, News, Opportunity | last in Content, before Sources (`single.html`, `item-faq` block) |
| Category and tag pages | after the list, before Share (`term-faq` block) |
| Directory, Volunteer, Claim, Opportunities (Settings: pages with a subject in the heading) | after the list (the end of the page's content), wide, page 1 only, no filters, never on a Directory place or type view |
| Every other page or campaign | after the page's content at the content width, page 1 only, no filters |

## Registry (automatic questions)

`aw_faq_registry()` (filter; the site's entries in `faq-data.php`): question template, fields needed, answer builder, priority, search pattern. Nothing is stored per page unless the box changes a question. Candidates are kept when their fields have values, deduped by the fact they state (e.g. "Does X offer adoption?" from the Adoption service tag repeats the services answer and is dropped), sorted by priority, capped at 6.

- A listing's services answer "based on type" is shown but does not count toward the 3 (owner, 20 Oct 2026): a listing needs 3 answers from its own data.
- Library questions use the document's short name: the "Short name" field (`aw_short_name`, e.g. PCA Act 1960, ABC Rules 2023), else the primary focus keyphrase. The first answer gives the full official title once.
- The accordion is texturized before the schema is parsed from it (WordPress texturizes the page: " - " → " – ", "24x7" → "24×7"), so both hold the same characters.

Library official releases and circulars (owner, 25 Oct 2026): "When was the … released / issued?" is asked without a document number (they have none), naming who released it; official releases with no PDF also get "Where can I read the …?" (the issuer's page). Letter templates get hand-written topic questions instead (no data fields).

Library: "Who issued …" is not asked for Acts and Rules (the stored issuer is whose copy it is, not the law-maker). Four fields were added for Library documents (box "FAQ fields"): Short name (`aw_short_name`), Hindi version link (`aw_file_hi_url`), Issued under (`aw_legal_act`), Key sections (`aw_key_sections`, Acts and Rules only, one per line `number | one-line summary | link`). Section questions come only from that list.

Resources guides: since 25 Oct 2026 every guide has an FAQ (their Rank Math FAQ blocks were converted). Guides get no automatic questions.

Resources research summaries (owner, 1 Nov 2026; until then Research had no FAQ): automatic questions from the study's own fields, naming it "the <focus keyphrase> study in <place>" (the place the title names, else the first district, else the first state): who carried it out (`aw_authors`, with `aw_study_title` when the answer stays short), where it was published (`aw_journal`, else `aw_source_name`, and `aw_source_date`), what it is about (the excerpt's first sentences, up to 35 words), and which law it discusses (`aw_entities` laws that are Library documents). Each links to the study (`aw_source_url`). None without a focus keyphrase or a source link.

Tags (owner, 1 Nov 2026): automatic questions from the items tagged (news, Library, Resources, Directory listings; Opportunities left out while paused): the latest news item, the latest Library document, the newest Resource, which organisations offer it (counts by state, link to the Directory filtered by the tag), who issues the Library documents (not Acts, Rules or templates), which topics (categories) come up most, which districts have the most listings, and which journals published the studies. None for a Directory listing type, for a tag with written questions of its own (it keeps only those), or for a tag with fewer than 4 items. Categories get none (theirs are written). A tag's item IDs are kept for an hour (transient `aw_faq_tag_<id>`), so a newly tagged item reaches its answers within the hour. A term's FAQ shows on page 1 of its list only.

## AW SEO Plus → FAQ

The overview only; approved questions are not listed here (they are in each page's box). One core list table, one row per item that shows an FAQ: name, type, questions shown, auto / edited / own; Edit FAQ (→ the item's box) · View; search, sort, filter by type. "Waiting for approval": items with draft questions (AI-proposed or imported), with the drafts listed; approve them in the item's box or tick and **Approve** (bulk). Counts by type (published, show an FAQ, questions but fewer than the minimum, drafts waiting) and Recount; the counts are cached for an hour.

## No holdout (owner, 31 Oct 2026)

The 20 Oct experiment (10% of listings and Library documents without an FAQ) is removed: option `aw_faq_experiment`, the holdout code and the `wp aw-faq holdout` command are gone; every eligible page shows its FAQ. The page context's `faq_group` is `faq` or `none` on every page with the box (GA4 dimension "Has FAQ" unchanged; docs/analytics/ga4-setup.md).

## Commands

- `wp aw-faq report` — counts by type: published, show an FAQ, fewer than the minimum, drafts waiting.
- `wp aw-faq convert-guides [--dry-run]` — move every guide's Rank Math FAQ block into its own FAQ (also happens on each save); flags answers over 50 words, more than one link, and FAQs over 6 questions (only the first 6 show). `wp aw-faq undo-guide <id>` reverses the latest conversion: the block back and the guide's FAQ as it was before.
- `wp aw-faq check [--verbose]` — on every page with an FAQ: the minimum to 6 questions, schema text = visible text, no duplicate question, ≤ 50 words, one link; reports pages whose focus keyphrase is in fewer than 2 questions.
- `wp aw-faq import <file.json>` — add draft questions (`{"<post type or taxonomy>:<slug>": [{q, a, sources, keyword}]}`, e.g. `post_tag:adoption`, `page:directory`, `aw_legal:<slug>` for letter templates); existing questions are kept.
- `tools/ci/faq_live_check.py <url>…` — fetches live pages and compares the visible accordion with the FAQPage JSON-LD.
