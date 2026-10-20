# Item page template

One structure for every item page (owner, 20 Oct 2026): News (`aw_news`), Library (`aw_legal`, sourced and our own
documents), Resources (`post`: research and guides) and Opportunities (`aw_opportunity`). They all use
`themes/animal-writes/templates/single.html`; type differences come from the item's data, not from separate templates.
Directory listings (`aw_listing`, `single-aw_listing.html`) use rows 1–4 only; everything below row 4 is unchanged.
Campaigns keep their own template (`campaign-page`).

Code: `plugins/animal-writes-core/includes/item-head.php`. The blocks are registered with the other `animal-writes/*`
blocks (`hub-blocks.php`), so the Site Editor lists them (category Widgets, titles "Item: …") and they can be moved
like any block. In the editor each one shows a short placeholder saying what it prints.

## Rows

A row prints nothing when it has nothing.

| # | Row | Block | What fills it | Used on |
|---|-----|-------|---------------|---------|
| 1 | Breadcrumb | `animal-writes/breadcrumb` | Rank Math's trail (aw-seo-extras `breadcrumbs.php`): Home › Section › primary category (the Rank Math primary category, else the first). The last step is a link. The BreadcrumbList schema is the same trail. Listings: Home › Directory › State › District. | all items, listings |
| 2 | Title | `core/post-title` (H1) | The title. | all |
| 3 | Meta row | `animal-writes/item-meta` | In this order, only what applies: the date (the original's for sourced items) · "Updated {date}" when edited on a later day · "Sponsored by …" · type chips (Library type, Research, Guide, Job …) · place chips (Opportunities, listings) · deadline (Opportunities) · status (listings: Blacklisted, Verified + date, Needs re-check) · category chips. No publisher, source or author. Spacing is the CSS gap only. | all, listings |
| 4 | Image | `core/post-featured-image` | The lead picture. Listings: `animal-writes/org-mark`, the logo only (nothing when there is none). | all, listings |
| 5 | Content | group `aw-item-content`: `core/post-excerpt`, `core/post-content`, Apply button | Summary (News, Library, research; not guides or opportunities) · the text (Library document with a "Contents" list when it has 5+ headings, guide body, opportunity description) · Apply (opportunities, while open) · FAQ blocks moved last. **Ad 1** is placed here (see Ads). | all |
| 6 | Sources | `animal-writes/item-sources` | News: publisher chip, Read the original, summary note. Library: publisher chip, Read the original, "Issued by …" when different, summary note. Our own documents: "Created by Animal Writes". Research: "Study by {authors} in {journal}", Read the original (DOI), summary note. Guides: "By Animal Writes" and the guide's own Sources list (moved here from the text, unchanged). Opportunities: "Posted by {organisation}", the original when it is not the Apply link. Every variant: Editorial policy, Report a mistake (`/contact/?about=correction#write`). | all |
| 7 | Tags | `animal-writes/item-tags` | Tag chips that are not the type shown in row 3. No categories. | all |
| 8 | Share | `animal-writes/item-share` | `aw_share_html()` (kits-core `share.php`): X, Facebook, LinkedIn, WhatsApp, Email, Copy link. One row, 44px icon buttons, the same style for every network, aria-label + title; names only at 1200+ when the row still fits (kit.js). | all |
| 9 | Ad 2 | `kits/ad-slot` slot `article-end` | The "Article – end" ad. | all |
| 10 | More | `animal-writes/item-more` | At most two groups of three small cards (type and date, title, one line; no pictures, no category chips). News: More in {category}, From the Library. Library: More in {category}, News about this. Resources: More {type}, From the Library. Opportunities: More opportunities. Picked by `aw_related_pick()` (related.php); cached 12 hours. | all |

## Ads (main column)

- **Ad 1** (`article-incontent-1`): after about 150 words (summary included), at the first block boundary, before the
  next H2 where the text has headings. Never above the first paragraph, never directly under the picture, never
  inside a list, table, quote, FAQ or clause (only between top-level blocks, never after the FAQ starts). A shorter
  text gets it after the Content row (at the top of the Sources block).
- **Ad 2** (`article-end`): the Ad slot block after Share.
- No other ads in the main column (`kits_ads_placed_types`: the automatic in-text, end-bar and after-content positions
  are off for these types). The sidebar (1200+) is unchanged and is not shown below 1200.

## Chips

One component for every chip (kit-primitives.css 1–2): outlined, white fill, 1px border at 60% of the text colour,
text colour; hover overlay and full border, focus ring, selected (an active filter) a stronger overlay. Only the
leading icon differs (`aw-type-ico--*`, Library `lib-*`, place pin, favicon). Campaign pages may tint it through the
`--kit-chip-*` tokens (campaign.css). Status tags (success, warning, danger, closed) keep their colours.

## Schema

aw-seo-extras `schema.php`, merged into Rank Math's graph: News NewsArticle; Library Legislation / Article /
DigitalDocument (templates); research Article with `isBasedOn` ScholarlyArticle (authors, `isPartOf` Periodical,
DOI); guides Article (+ Rank Math FAQPage); opportunities JobPosting with `validThrough` (no salary is stored, so no
`baseSalary`). BreadcrumbList = the visible trail.

## Research authors

`aw_authors` and `aw_journal` on research posts (`research-citation.php`): sent by the research worker, else read from
doi.org (CSL-JSON) after ingest. `aw_research_citation_backfill()` fills the rest.
