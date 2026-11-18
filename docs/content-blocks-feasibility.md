# Content blocks under Kits Core: feasibility (18 Nov 2026)

Scope: the content parts of `plugins/animal-writes-core` (Animal Writes Core, "the tenant plugin"): the content types,
their render files, and the blocks and helpers they print with. The tenant plugin is deleted at the end (owner, 18 Nov 2026): no
code stays in it. Generic code moves to Kits Core (blocks, patterns, helpers, presets) or to RankWeb, DirectoryWeb,
SourceWeb, MailWeb, PlanWeb or the Kits theme. What only Animal Writes needs becomes site data (options, preset
settings, global styles, SCF groups, page content). Section 22 gives every part its final home.

Out of scope here, each done on its own track: breadcrumbs and FAQ data (they go to RankWeb); the Directory
(`aw_listing`, claims, places, listing template; it goes to DirectoryWeb); Keys and the admin shell (Kits Core admin).
Where this doc touches those, it lists a dependency and stops.

Builds on `docs/blocks-spec.md` (11 Nov 2026, owner review pending). That spec lists the shortcodes and blocks one by
one and plans 7 new Kits blocks. This doc does not repeat it. It answers a different question: **which existing
tenant render code is generic, where it should live, and what is safe to move now without a visible change.**

Facts were checked on branch `claude/awcore-content-ed66ef` (from `73bbc87c`, = `origin/wordpress-dev`), and on live
read-only (curl, 18 Nov 2026).

---

## 0. The pattern: a companion plugin, like Uncode Core

How a theme vendor ships its content layer (Uncode + Uncode Core, and the installed Kits theme + Kits Core today):

- **The theme** has templates, parts, patterns, `theme.json` tokens and CSS. It registers no post types and no
  shortcodes (WordPress theme review, "Plugin territory": make.wordpress.org/themes/handbook/review/required/).
- **The companion plugin** (Uncode Core / Kits Core) holds the functions the theme's look relies on: blocks, block
  bindings, render helpers, shortcodes, the admin. It works with the theme but does not depend on one site's data.
- **The site** (here: Animal Writes Core today; at the end only site data: options, preset settings, global styles) says *what* the content is: its post types,
  meta keys, labels and words. It answers Kits Core's filters (`kits_*`); Kits Core never names an `aw_` key.

Kits Core already follows this for the pieces it holds: `kits/hero` (`blocks/hero/block.json`), primitives
(`kits_datecard()`, `kits_timeline()`, `kits_badge()`, `kits_filter_bar()`; `includes/primitives.php`), the share kit
(`kits_share_html()`, `kits_follow_html()`; `includes/share.php`), section labelling (`includes/sections.php`) and the
attribution row (`kits_source_row_html()`; `includes/attribution.php`). The tenant answers `kits_campaign_post_type`,
`kits_sourced_post_types` and others in `includes/kits-config.php`.

**What Kits Core must not become:** a custom post type framework. Post types are a few `register_post_type()` calls
with arguments; Pods, CPT UI and ACF/SCF already do "make a type in the admin". Kits Core gets render code, never a
type builder.

---

## 1. Content types: where each one is registered, and where it should stay

WordPress's own public types are only `post` and `page`. Everything else is a CPT.

| Type | Public name | Registered in | Fields | Verdict |
|---|---|---|---|---|
| `post` | Resources (`/resources/`) | WordPress; relabelled by `aw_resources_post_args()` (`content-types.php`) | `group_aw_resource.json` | Final: Kits Core preset `kits-resource`; labels and slug are preset settings (site data) |
| `page` | Pages | WordPress | — | No change |
| `aw_news` | News | `aw_register_content_types()` from `aw_post_types()` (`content-types.php:23`) | `group_aw_news.json` | Final: Kits Core preset `kits-news`, slug `aw_news` in its settings |
| `aw_legal` | Library | same | `group_aw_legal*.json` | Final: Kits Core preset `kits-library`, slug `aw_legal` |
| `aw_campaign` | Campaign Hub | same | 7 `group_aw_campaign*.json` | Final: Kits Core preset `kits-campaign`, slug `aw_campaign` |
| `aw_opportunity` | Opportunities | same | `group_aw_opportunity.json` | Final: Kits Core preset `kits-opportunity`, slug `aw_opportunity` |
| `aw_listing` | Directory | DirectoryWeb (`directoryweb/includes/bootstrap.php:20`, with `aw_content_type_args()`) | DirectoryWeb | DirectoryWeb's |
| `aw_press` | Media & PR | `press.php:102` | in code | Final: Kits Core preset `kits-press`, slug `aw_press` |
| `aw_social` | Social posts (under News; off by default) | `social-posts.php:127` | `group_aw_social.json` | Final: Kits Core (social card + preset `kits-social`), slug `aw_social` |
| `aw_report`, `aw_volunteer`, `aw_message` | private records | `reports.php`, `volunteers.php`, `pages.php` | — | Final: `aw_report` and `aw_volunteer` → Kits Core private-record presets (same slugs); `aw_message` → MailWeb (inbox) |
| Adoption | a **page** (`/adoption/`, 200 on live) with a Query Loop of Opportunities (`hubs.php:297`) | — | — | Not a type; no move needed |
| Taxonomy `aw_campaign_link` | campaign items | `campaign-links.php:26` | — | Final: Kits Core, registered by `kits-campaign` under the same slug (SourceWeb and campaign pages query it) |

**Where the registration ends up (animal-writes-core is deleted, owner 18 Nov 2026):** `aw_post_types()` is six rows of
the site's own words (names, slugs, supports). The code goes to Kits Core as presets (section 12); the words and the
`aw_*` slugs are this site's preset settings (an option), so no `aw_` name is written in Kits Core's code. Still
`register_post_type()`, no CPT builder. CPT slugs, rewrite slugs and meta keys never change (section 6). Every final
home is listed in section 22.

---

## 2. Render files: what moves, what stays

"Generic" = no `aw_` meta key, no AW words, no AW post type, or one that a filter can supply. Line counts as of today.

| File (tenant) | Lines | What it renders | Generic part | Verdict |
|---|---|---|---|---|
| `systems.php` (`aw_i()`, `aw_date()`) | 462 | The icon span and **the one date component** (calendar icon + `<time>`), used by about 100 calls in the tenant, DirectoryWeb and SourceWeb | **All of both functions** (they print only `kit-ico`/`kit-date` markup) | **Moved** → `kits_icon()`, `kits_date()` (Stage 2). The list filters (`AW_LIST_FILTERS`): Kits Core `kits_collection_filters`, values as preset settings |
| `bindings.php` (source `animal-writes/field`) | 246 | Block Bindings: card and item fields bound to core blocks; empty bound blocks removed | The source, the key registry, "drop a block whose bound value is empty" | **Moved** → source `kits/post-field`, filter `kits_post_fields` (Stage 2). Final: each preset declares its keys in Kits Core; the listing keys and contact-button rules → DirectoryWeb |
| `hub-blocks.php` | 770 | 34 `animal-writes/*` server-rendered blocks (band, card parts, item rows, listing groups) and their loader | The loader (list → `register_block_type` + ServerSideRender preview) | Final: Kits Core block loader, registering the saved `animal-writes/*` names unchanged (stored data); the listing blocks → DirectoryWeb |
| `item-head.php` | 588 | The item page rows: meta row, sources, tags, share, "more" | Meta-row order (date first), card media | Final: Kits Core (`kits/post-field`, Query Loop variations; blocks-spec 3.6, 3.18) + Kits theme patterns |
| `sections.php` | 400 | `[aw_section_head]` (no live use) | — | Final: none, deleted with animal-writes-core (no live use; blocks-spec 3.1) |
| `hubs.php` | 799 | Hub page Query Loops and the filters they read | Query-var plumbing (`query_loop_block_query_vars`) | Final: Kits Core `kits/collection-filter` (blocks-spec 2.4); hub pages are page content (site data) |
| `form-blocks.php` | 308 | Volunteer, claim, report and thank-you pages | — | Final: Fluent Forms blocks in page content (site data) through Kits Core forms; claim → DirectoryWeb |
| `campaign-tools.php` | 1588 | Campaign state, letters, sticky bar, share section, **timelines** (`aw_camp_dates_timeline()`, `aw_steps_dateline()`) | Timelines already call `kits_datecard()` / `kits_milestone_state()` | Final: Kits Core (`kits-campaign` preset, `kits/countdown`, `kits/sticky-cta`, `kits/letter`); each campaign's dates and state are its post meta (data) |
| `campaign-record.php` | 362 | Campaign metrics, impact, outcome | — | Final: Kits Core `kits/metric` (blocks-spec 2.3); figures stay post meta |
| `social-posts.php` (`aw_social_card_html()`) | 1174 | **Social post card**, one sitewide component | The card markup from an array (no meta reads) | Final: Kits Core, with the `kit-social-*` rename (owner yes, own PR) and the platform list / URL parser moved with it |
| `related.php` | 356 | `[aw_related]` groups | — | Final: Kits Core `kits/related` (blocks-spec 3.3) |
| `press.php` | 459 | Media & PR cards, og: fetch | — | Final: cards → Kits Core `kits-press`; og: fetch → SourceWeb; `aw_press_logos` → preset settings |
| `near-opps.php` | ~100 | Home "near you" REST swap | — | Final: Kits Core (Query Loop variation + REST route; the `aw/v1` path stays until its script switches) |
| `home.php`, `term-page.php` | 468, ~120 | Home and term pages | Story-band dates use `kits_datecard()` already | Final: Kits theme patterns (blocks-spec 3.18); Home and term pages are page content |
| `news-article.php`, `summary-display.php` | small | Key facts card, summary display | — | Final: Kits Core (`kits-news` binding keys and pattern) |
| `block-states.php` | 248 | AW block states on `/kits-demo/` | — | Final: Kits Core demo (`includes/demo.php`) |

**Components already in Kits (no move needed):**

| Component | Where | Notes |
|---|---|---|
| Date card `kits_datecard()`, `kits_datecard_word()`, `kits_timeline()`, `kits_milestone_state()` | `kits-core/includes/primitives.php:181–280` | Owner, 13 Nov: one calendar for every timeline. Called from `campaign-tools.php:1283`, `home.php:440` |
| Stacked cards | `themes/kits-by-wugweb/assets/css/kit.css` (owner, 14 Nov: one stacked card for theme and product) | CSS only; the markup is core Query Loop / post blocks |
| Share kit `kits_share_html()`, `kits_follow_html()` | `kits-core/includes/share.php` | `aw_page_share_kit()` (`campaign-tools.php:1386`) is the tenant's call into it |
| Section bands (Section / Head band group styles) | `kits-core/includes/blocks.php` (`kits_group_html()`) | |
| Filter bar, sort, chips | `kits_filter_bar()` and friends, `primitives.php:293–409` | The hub toolbar already prints through these |

---

## 3. Scalability

**Query cost.** The moves in Stage 2 add no queries: `kits_date()` and `kits_icon()` are string builders; the binding
source reads post meta that the Query Loop has already primed (`update_post_meta_cache` is on for the loop). The later
moves must keep two rules:
- **`aw_news` is re-sorted by source date** on the front end (`hubs.php`, `pre_get_posts`). A Kits Query Loop
  variation must not assume `orderby=date`. Anything that needs "newest by ID" (imports, dedupe) uses `$wpdb` directly,
  not `WP_Query`, or it gets the re-sorted order.
- **Related and "more" groups** (`related.php`, `item-head.php`) run several small queries per item page. A Kits
  variation should keep `no_found_rows => true`, `fields => ids` where it can, and `update_post_term_cache` only when
  the card prints terms.

**Caching.** Live pages are full-page cached (LiteSpeed + CDN; `cdn-cache.php`, `deploy-purge.php` purges after every
deploy). Rendering in PHP is paid once per cache fill. Day counts and campaign state already set their own cache
lifetime (`aw_camp_cache_until()`). Kits blocks that show "days left" must recount in the browser (blocks-spec 2.2).
Object cache: `wp_cache_*` group `aw` (`campaign-tools.php:235`); Kits code uses group `kits`.

**Number of blocks.** Today: 51 custom blocks, 37 shortcodes (blocks-spec 1a/1b). The direction (blocks-spec) is 7 Kits
blocks + 1 DirectoryWeb block; everything else becomes core blocks + patterns + Query Loop variations + Block Bindings.
Stage 2 adds **zero** blocks: one binding source and two helpers. Fewer, generic blocks keep the inserter short.

**Editor UX.** A server-only binding source shows its label in the editor, not the value (as `animal-writes/field`
does today); `kits/post-field` registers the label "Kits post field". Showing real values in the editor needs a small
client-side source (proposal I). The
`animal-writes/*` blocks keep their ServerSideRender preview. Mobile-first: no change to front-end markup in Stage 2,
so no change at 375/768/1280.

---

## 4. Dependencies, both directions

| With | Tenant → it | It → tenant | Effect of the Stage 2 move |
|---|---|---|---|
| Kits theme templates (`plugins/animal-writes-core/theme/`, the AW child copy) | `single.html` binds `animal-writes/field` | Templates use `animal-writes/*` blocks | None: `animal-writes/field` stays registered with the same keys and output. Final: generic templates → Kits theme; AW-only templates/parts → site data (Site Editor) |
| `kit.css` / `aw.css` (two identical copies of `aw.css`: theme and plugin; change both, `diff -q`) | `kit-date`, `kit-ico`, `aw-date` classes | — | None: markup byte-identical; no CSS change in Stage 2 |
| RankWeb (schema, FAQ, breadcrumbs: its own track) | `faq-data.php`, `breadcrumbs.php` (final: RankWeb) | RankWeb reads tenant helpers via `function_exists` | None; `aw_date()` keeps its name and output |
| DirectoryWeb (its own track) | Tenant loads it; `aw_content_type_args()` | 40 calls to `aw_i()` / `aw_date()` (`listing-template.php`, `listing-profile.php`, `gov-verified.php` and others); `aw_binding_value( 'blacklisted_label' )` (`gov-verified.php:213`) | None now. After this move DirectoryWeb can call `kits_icon()` / `kits_date()` and register its listing keys on `kits_post_fields`, which is what lets it stop depending on the tenant |
| SourceWeb (`aw-sourcing`) / Sourcing | `aw_campaign_link` taxonomy for campaign items | 6 calls to `aw_i()`/`aw_date()` (`enrichment-front.php`) | Switched in this PR; the other SourceWeb calls are in section 22 (a) |
| MailWeb | `mailweb-aw.php` (final: MailWeb settings, site data) | — | None |
| InsightWeb | Loaded by the tenant | Reads analytics hooks (`data-act`, `kit-*` classes) | None: `data-act` on listing buttons is kept |
| SCF / ACF field groups (`acf-json/*.json`) | Field keys = the `aw_*` meta keys | — | None: no field or key changes |
| Fluent Forms | Forms printed by `aw_ff_form()` | — | None |
| `aw-analytics.js` | Listens to `data-act`, `.kit-share`, `.kit-crumbs` and more (blocks-spec 4, Risks) | — | `data-act` output unchanged (tenant still adds it) |

**Load order.** The tenant loads Kits Core first (`animal-writes-core.php:40`) when it is not active on its own, so
`kits_*` functions exist before any tenant file runs. Since 18 Nov the tenant already depends on Kits Core directly
(`aw_core_simple_table()` → `kits_simple_table()`); Stage 2 follows the same rule.

---

## 5. Working with another theme, and without the tenant

| Need | How | Status |
|---|---|---|
| A block outside this site | `block.json` (`apiVersion` 3), `textdomain` `kits-core`, render through supports and tokens | `kits/hero` today; the 7 blocks of blocks-spec follow it |
| Fields on core blocks with any theme | Block Bindings source `kits/post-field`: any theme's template or pattern can bind a Paragraph/Button/Image to a key | **Stage 2** |
| A site that has none of AW's keys | `kits_post_fields` is empty → the source returns '' → the bound block is removed, never an error | Stage 2 |
| Sizes and colours from the theme | `kit-*` classes read `--kit-*` / `--wp--preset--*` tokens (`kit-tokens.css`, `theme.json`); another theme maps its own tokens | Existing |
| Patterns | Theme patterns (`patterns/*.php`) + synced patterns in the database | Existing; the AW-specific ones stay in the child theme copy |
| Without the tenant plugin | Kits Core alone: `kits_icon()`, `kits_date()` (site date format), `kits/post-field` with no keys | Stage 2 makes these work; the content types themselves still need a small site config (section 1) |

---

## 6. DB safety

Code moves; data stays. In this PR and every later step:
- `aw_*` meta keys, `aw_*` options, CPT slugs (`aw_news`…), rewrite slugs, taxonomy `aw_campaign_link` and block names
  `animal-writes/*` stay unchanged. They are stored in the database and in saved content.
- The binding source name `animal-writes/field` stays registered for good while any template or saved block uses it.
- No migration, no `update_option`, no meta writes, no post changes. Nothing to back up for Stage 2.
- A later rename of a stored key is a separate, owner-approved migration with a backup and an undo, never part of a move.

---

## 7. What can be added or improved

| Proposal | Effort | Risk | Notes |
|---|---|---|---|
| A. `kits_icon()`, `kits_date()` in Kits Core; tenant wrappers | S | Low (byte-identical output) | **Stage 2** |
| B. Binding source `kits/post-field` + `kits_post_fields` registry + empty-block removal; tenant registers its keys; `animal-writes/field` kept as an alias | S–M | Low (same values, same escaping, same empty rule) | **Stage 2** |
| C. DirectoryWeb and SourceWeb call `kits_icon()` / `kits_date()`, and DirectoryWeb registers its listing keys (`phone`, `area`, `services`…) on `kits_post_fields` | S | Low | Their own tracks; this is what lets them drop the tenant |
| D. Kits loader for server-rendered blocks (same `animal-writes/*` names, generic ServerSideRender editor script) | M | Medium (editor script handle and `window.awHubBlocks` global move) | Phase B |
| E. Social post card → `kits_social_card_html()` with `kit-social-*` classes | M | Medium: CSS rename in both `aw.css` copies, visible if missed | Phase B; needs a before/after check at 375/768/1280 |
| F. Query Loop variations `kits/related`, `kits/item-more`, `kits/term-related` (blocks-spec 3.3, 3.18) | M | Medium (query cost; `aw_news` re-sort) | Blocks spec approved (18 Nov); own PR |
| G. Item meta row from bound core blocks (`kits/post-field`) in `single.html` | M | Medium (template change, many `render_block_*` filters) | After B; template edit = visible layout, owner's yes |
| H. Kits blocks of blocks-spec (`kits/share`, `kits/countdown`, `kits/sponsored`, then the rest) | L | Medium | Waiting on the owner's review of blocks-spec |
| I. Editor: register `kits/post-field` keys in JS too (labels in the Attributes panel) | S | Low | After B |

---

## 8. Staged plan

| Stage | Work | Who decides | Visible change |
|---|---|---|---|
| 1 | This doc | — | none |
| 2 (this PR) | A + B: icon/date helpers and the binding source move to Kits Core; tenant keeps thin wrappers and its keys | Review gate, then the owner merges | **none** (markup identical) |
| 3 | C: DirectoryWeb and SourceWeb switch to `kits_*` names | Their tracks | none |
| 4 | D + E: block loader and social card | Owner (CSS rename) | none intended; checked at 375/768/1280 |
| 5 | F + G + H: blocks-spec phases 1–3 (spec approved 18 Nov) | Owner, per page | yes, per page on the owner's yes |
| 6 | animal-writes-core is deleted: every part has a final home (section 22); words, slugs and settings are site data | Owner | none |

---

## 9. Risks

- **Fatal if Kits Core is missing.** The wrappers call `kits_*` directly, like `aw_core_simple_table()`. Kits Core ships
  in the same deploy and is loaded first; the Deploy check's smoke run loads both.
- **Double work on `render_block`.** The empty-block rule moves to Kits Core; the tenant's old filter is reduced to its
  listing-button part, so a block is never checked twice.
- **Escaping drift.** The source keeps today's escaping per key: URLs `esc_url`, the deadline `wp_kses_post`, the rest
  `esc_html`. Kits Core applies the type each key registers with.
- **Callers outside the tenant.** DirectoryWeb and SourceWeb call `aw_i()`, `aw_date()` and `aw_binding_value()`. The
  wrappers keep those names working until each plugin switches (C). Before the tenant plugin is switched off, every
  such call must be gone or a compat file must carry it (like `kits-core/includes/compat-sourcing.php`).
- **Plugin Check scope.** Touching the tenant pulls all of it into Plugin Check; pre-existing ERROR rows get inline
  `phpcs:ignore` only where the check fails.

---

## 10. Open questions for the owner

1. **Blocks spec review.** Answered 18 Nov: `docs/blocks-spec.md` is **approved**. The moves F, G, H can be built, each phase on its own PR; each content change on live still waits for its own yes.
2. **Social card class rename (E).** `aw-social-*` → `kit-social-*` in both `aw.css` copies and the card markup. OK to
   do as its own PR with a before/after check?
3. **Where the CPT rows end up (stage 6).** Answered 18 Nov: animal-writes-core is deleted, no site plugin. The rows
   become Kits Core presets; slugs and words are preset settings (section 22). No Kits Core CPT layer.
4. **Compat for the old names.** Answered 18 Nov: replace aw with kits, no compat file; every caller switches first
   (section 22 (a)).

---

# Part II — owner additions (18 Nov 2026)

Every row below is labelled **[admin]** (wp-admin screens, metaboxes, list tables, settings, the editor's own panels:
WordPress core UI only, no Kits front-end tokens or layout rules) or **[front-end]** (what visitors see, and the
block editor canvas that renders the same blocks: the Kits design rules apply). One side's rules are never applied
to the other.

## 11. Admin vs front-end: this PR and section 2

| Item | Side |
|---|---|
| `kits_icon()`, `kits_date()` (primitives.php) | [front-end] |
| `kits/post-field` source, `kits_post_fields`, empty-block rule (kits-core bindings.php) | [front-end] (the source label in the editor's Attributes panel is [admin]) |
| SCF bridge `kits_scf_active()`, `kits_scf_json_folder()`, `kits_scf_groups()` (kits-core scf.php) | [admin] |
| aw-core `systems.php`, `bindings.php` wrappers | [front-end] |
| aw-core `scf.php` (field groups, value guard, SCF stays on) | [admin] |
| `hub-blocks.php`, `item-head.php`, `sections.php`, `hubs.php`, `related.php`, `home.php`, `term-page.php`, `news-article.php`, `summary-display.php`, `near-opps.php`, `social-posts.php` card, `campaign-record.php` output | [front-end] |
| `campaign-tools.php`: timelines, share section, sticky bar, letters | [front-end]; its campaign boxes (`aw_camp_*_box`) [admin] |
| `content-types.php`, `press.php` / `social-posts.php` registration and intake, `edit-screens.php`, `block-states.php` (states sheet, administrators only) | [admin] |
| `form-blocks.php` | [front-end] (the forms' entries in Fluent Forms: [admin]) |

## 12. Content-type presets

**The line.** A *preset* is a PHP config array that Kits Core turns into native calls. It is not a builder UI, not
a stored type definition, and has no admin screen to make types. A site enables presets in code (a filter), or
brings its own type from CPT UI, Pods or SCF and still uses every Kits block through bindings and Query Loop.

A preset declares, and Kits Core does only this with it:

| Part | Native call | Side |
|---|---|---|
| type | `register_post_type()` (labels, slug, supports, `show_in_rest`, `template` for the editor) | [admin] |
| fields | `register_post_meta()` with `show_in_rest`, `type`, `single`, `sanitize_callback`, `auth_callback` | [admin] |
| binding keys | entries on `kits_post_fields` (section B of Part I) for its fields | [front-end] |
| terms | which **core** `category` / `post_tag` roles it uses (labels, icons). No own taxonomy (section 18) | both |
| templates and patterns | `single-{type}.html` / `archive-{type}.html` block templates, patterns in a pattern category | [front-end] |
| schema | a mapping key the SEO layer reads (section 13) | [front-end] (output in `<head>`) |

**Naming (owner, 18 Nov 2026).** Data keeps its aw_ names on this site; code, presets, blocks and functions never
use aw. Presets are named `kits-<type>`. On this site, the enabled presets point at the existing type slugs
(`aw_news`…), which stay unchanged as stored data (section 6).

**The presets.** This site's enabled presets reproduce today exactly: same slug, rewrite, supports, meta keys and
terms. They only describe what `aw_post_types()` already registers, and are switched on only in a later stage,
after a diff shows the registered arguments are identical.

| Preset | Type slug (this site's stored data, or new) | Status | Fields (examples) | Notes |
|---|---|---|---|---|
| `kits-news` | `aw_news` | live | source url/name/date, key facts | re-sorted by source date (Part I, 3) |
| `kits-library` | `aw_legal` | live | document, issuer, law date | |
| `kits-press` | `aw_press` | live | publisher, format, url | |
| `kits-resource` | `post` (relabelled) | live | — | WordPress's own Posts |
| `kits-opportunity` | `aw_opportunity` | live | deadline, apply url, org | |
| `kits-adoption` | none: a page with a Query Loop of Opportunities | live | — | stays a page; the preset only gives the pattern |
| `kits-campaign` | `aw_campaign` | live | dates, document, orgs, outcome, share | 4 sizes stay one component (section 14) |
| `kits-portfolio` | `kits_portfolio` | new | client, year, role, link | |
| `kits-card` | `kits_card` | new | image, line, link | a reusable small card (tips, facts) |
| `kits-infographic` | `kits_infographic` | new | image, alt long description, source, data link | needs a text alternative (WCAG 1.1.1) |
| `kits-case-study` | `kits_case_study` | suggested | problem, approach, outcome, figures | |
| `kits-event` | `kits_event` | suggested | start, end, place, online url | dates through `kits_date()` / `kits_datecard()` |
| `kits-person` | `kits_person` | suggested | role, photo, profile links | no private data |
| `kits-testimonial` | `kits_testimonial` | suggested | quote, name, role, consent date | only with the person's consent |

New type slugs use the `kits_` prefix (owner naming rule). Effort: preset loader S, each new preset S–M (fields +
templates + pattern), this site's enabled presets M (must be byte-identical; checked by comparing `get_post_type_object()` output
before/after). Risk: low for new types, medium for switching this site's existing types onto presets (rewrite rules, permalinks) —
not before the owner's word.

## 13. Schema per type (contract only; RankWeb owns the output)

Kits Core emits **no JSON-LD**. A preset carries a `schema` key; RankWeb reads it through one filter and puts the
node into Rank Math's single graph (as it does today, `aw-seo-extras/includes/schema.php`), so there is never a
second JSON-LD block.

Contract (proposed, for the RankWeb track to accept or change):
- `kits_schema_map( array $map ): array` — post type => `array( 'type' => 'Article', 'fields' => array( schema
  property => binding key ) )`. RankWeb builds the node from the binding values (`kits_post_field_value()`), so
  schema text is the visible text.
- An entry may carry `requires` (properties without which no node is built) and `value_callback( $key, $post_id )`: when set, RankWeb reads values through it instead. Presets set it to `kits_preset_schema_value()`, the visible value except dates in ISO 8601 and pictures as their address (built in stage 4).
- `kits_schema_node( array $node, int $post_id, string $post_type )` — last-mile change by the site.
- Term and hub pages: `CollectionPage` + `ItemList`, as RankWeb does today.

| Preset | schema.org type | Today |
|---|---|---|
| News | `NewsArticle` | live (RankWeb `aw_article_nodes`) |
| Library | `Legislation` / `Article` / `DigitalDocument` by kind | live |
| Resources | `Article` (+ `FAQPage` from the visible FAQ) | live |
| Press | `CollectionPage` of items on /media/ | live |
| Opportunities | unchanged: the node RankWeb builds today (`aw_opportunity_schema`) | live; not re-mapped here |
| Campaigns | `Event` with a day and place, else `CreativeWork` | live (`aw_campaign_schema`) |
| Portfolio | `CreativeWork` | new |
| Card | `CreativeWork` | new |
| Infographic | `ImageObject` (with `caption`, `isBasedOn` the data source) | new |
| Case study | `Article` | suggested |
| Event | `Event` | suggested |
| Team / Person | `Person` (`worksFor` the site's Organization) | suggested |
| Testimonial | `Review` only with a real reviewed item, else no node | suggested |

Effort: contract S here; RankWeb's reader M (its track). Risk: duplicate nodes if a type also has a Rank Math
schema template — RankWeb's existing rule (its node replaces Rank Math's default) covers it.

## 14. Customisation: without code vs with code

| Without code | Where | Side |
|---|---|---|
| Labels, menu name | a preset's labels can be overridden in Kits → Settings (stored option, core Settings API) — planned; today code | [admin] |
| Slugs | Settings → Permalinks (core) for the base; a type's own slug stays code (it is stored in URLs) | [admin] |
| Which fields show on the edit screen | core Preferences → Panels; SCF field group settings where SCF is on | [admin] |
| Card variant, layout | block attributes / block styles in the editor (section 16) | [front-end] |
| Archive layout | Site Editor: the type's `archive-*.html` template | [front-end] |
| Patterns | Site Editor / synced patterns | [front-end] |
| Colours, spacing, type | Styles (theme.json tokens), style variations | [front-end] |

| With code (filters) | Hook |
|---|---|
| Enable / disable presets | `kits_presets_enabled` (the ids that are on; Kits → Settings → Content types saves them); `kits_presets` (the definitions) |
| Field keys and values | `kits_post_fields` (this PR) |
| Empty-field markup | `kits_post_field_empty` (this PR) |
| Schema | `kits_schema_map`, `kits_schema_node` (section 13) |
| SCF folders and groups | `kits_scf_json_folder()`, `kits_scf_groups` (this PR) |

**Stay single sitewide components (no per-site or per-page variants):** the campaign's 4 sizes (lg, md, sm,
share), the stacked card, the social post card, the date card. A site changes their tokens, never forks them.

## 15. Plugin integrations

| Plugin | Required? | Guard | Effort / risk | Side |
|---|---|---|---|---|
| **SCF / ACF** | **Optional, first-class.** The primary editor on animalwrites.in (13 groups in `animal-writes-core/acf-json`, 8 in DirectoryWeb). Content and bindings work without it: fields are `register_post_meta()`, bindings read native meta, never `get_field()` | `kits_scf_active()`; every `acf_*` call inside it | Bridge S (this PR), low | [admin] |
| CPT UI / Pods | Optional | none needed: a type they register is just a post type; it opts into bindings by adding keys on `kits_post_fields` | S, low | [admin] |
| Fluent Forms (and other form plugins) | Optional | one adapter per form plugin (the adapter approach of the DirectoryWeb track; not on this branch): class check, fall back to a mailto link | M per adapter, low | both |
| MailWeb / newsletter | Optional | `function_exists( 'mailweb_*' )`; the newsletter block prints nothing without it | S, low | both |
| SourceWeb (ingest) | Optional | writes posts and `aw_campaign_link`; Kits reads them like any post | none, low | [admin] |
| RankWeb | Optional | schema contract (section 13) and breadcrumbs through filters; Kits prints none itself | S here, M in RankWeb | [front-end] |
| InsightWeb | Optional | reads `data-*` hooks and `kit-*` classes; those are kept (Part I, 4) | none, low | [front-end] |
| WooCommerce | Not relevant to AW (no shop). If a Kits site has it: products use Woo's own blocks; Kits layouts (section 16) can show `product` through Query Loop | `class_exists( 'WooCommerce' )` | M, medium | [front-end] |
| Polylang / WPML | Optional | presets register strings with `__()` (labels translate); types and meta flagged translatable through their config files (`wpml-config.xml`), slugs translatable by them | M, medium (rewrite slugs per language) | both |
| Caching (LiteSpeed, CDN) | Assumed | output is per page and cacheable; day counts recount in the browser; deploy purge stays | none, low | [front-end] |

## 16. Display layouts (one set, any type, Directory included)

**What exists today (checked):**
- `--kit-carousel-item` token (82%, `auto` from 1200; `kit-tokens.css:175`, `:278`) and a registry row
  "Carousel dots" (`kit-carousel`, status *replaced*) — no carousel component in `kit.css`.
- Two campaign-scoped snapping rows: `.aw-camp .aw-camp-row` (`campaign-lab.css:526–528`) and the Lab rules rows
  (`campaign.css:406–410`): flex, `overflow-x: auto`, `scroll-snap-type: x mandatory`, cards `flex: 0 0
  var(--kit-card-min)`. No arrows, no autoplay.
- Grid, stacked card list, bento and the social card list are in `kit.css` / `aw.css` today.

**Plan: one Kits collection layer on core Query Loop.** The layouts are **block styles** on `core/post-template`
(registered in Kits Core, CSS in `kit.css`), plus a Query Loop variation per preset that picks the card pattern.
Any post type can use them, `aw_listing` included. No new query block.

| Layout | How | Notes |
|---|---|---|
| Grid | existing grid layout + `--kit-card-min` | |
| Stacked list | existing stacked card (one component) | |
| Carousel / rail | new `is-style-kits-rail` (below) | generalises the two campaign rows |
| Bento | `is-style-kits-bento`: three shades per bento, campaign light + campaign dark + white, plus pictures/texture (owner, 13 Nov) | |
| Featured + list | first item lg, the rest stacked (CSS `:first-child`) | |
| Masonry | CSS columns (no JS); reading order stays top-to-bottom per column, checked with a screen reader | low priority |
| Tabs | **allowed (owner, 18 Nov 2026)**, as a layout option where the owner picks it. Not a fold: every panel's content is in the page markup, real `tablist`/`tab`/`tabpanel` roles, arrow keys between tabs, no-JS shows all panels in order. Not built yet | |
| Accordions / "Show more" | **never** (owner, 13 Nov) | |

**Carousel / rail rules** [front-end]:
- CSS `scroll-snap-type: x mandatory`, `overscroll-behavior-x: contain`, native swipe on phones; cards
  `flex: 0 0 var(--kit-carousel-item)` below 768, `var(--kit-card-min)` from 768 (`docs/card-sizes.md` line 7:
  17rem, the Kits token in `kit-tokens.css:138`; owner, 18 Nov).
- Arrows: two real `<button>`s ("Previous", "Next" + the list's name), 44px (site size; WCAG 2.5.8 floor 24px),
  shown from 768; they scroll by one card with `scrollBy` and are disabled at the ends. Optional dots are buttons too.
- Minimal script on the Interactivity API (`kits/rail` store, `view.js` module), no jQuery, Swiper or Slick: the
  browser does the scrolling; the script only drives the buttons and the current-item state.
- `role="region"`, `aria-roledescription="carousel"`, `aria-label` from the section heading; each card is a list
  item (no `aria-hidden` on off-screen cards; they stay in the tab order, and focusing one scrolls it into view —
  focus is never left off-screen).
- **No autoplay** by default. If a site enables it: a visible Pause button (WCAG 2.2.2), stops on hover and focus,
  and never runs under `prefers-reduced-motion: reduce`.
- Breakpoints 767/768 and 1199/1200 only; Inter; titles never underline; date first in the meta row; one component
  in `kit.css`, never page-scoped.
- The campaign rows move onto it only with a before/after check at 375/768/1280 (they are visible).

**Cards** [front-end]: one card component with variants — content card, stacked, social, **listing**. Kits Core
owns the layout and styling (`kit.css`, tokens). The listing card's fields (name, type, area, Claimed/Verified
badges, contact buttons) come through bindings from DirectoryWeb's keys on `kits_post_fields`; DirectoryWeb decides
which fields a listing card shows (its pattern).

Effort / risk: block styles + rail CSS M, low–medium; rail script S; moving the campaign rows S but visible (owner's
yes); listing card variant M (with DirectoryWeb). **Not built in this PR:** no existing AW layout can be
generalised with zero visual change (the campaign rows are campaign-scoped and sized differently from a generic rail).

## 17. Share: one `kits/share` block

**Today:** the engine is Kits Core (`share.php`: `kits_share_html()`, `kits_share_payload()`, `kits_share_types()`,
`kits_share_tagged_url()`, `kits_post_share_html()`, `kits_follow_html()`). The blocks are the tenant's:
`animal-writes/share` (`aw_block_share`, `campaign-tools.php:1433`; Home pattern `home-share.php`),
`aw_share_section()` (`:1417`), `aw_page_share_kit()` / `aw_page_share_fill()` (`:1386`, `:1445`),
`animal-writes/item-share` (`item-head.php:405`; `single.html`, `single-aw_listing.html`),
`animal-writes/term-share` (`term-page.php:54`; `category.html`, `tag.html`), and the campaign share box
(`aw_camp_share_box` `:854` [admin], `aw_camp_share_meta` `:1568`). Share pictures (1080x1080) are RankWeb's
(`aw-seo-extras` helpers.php, opengraph.php).

**Plan:** one `kits/share` block (block.json, apiVersion 3) with a `context` attribute — `page` / `item` / `term`
— and **Follow us for page shares only** (owner). Share = the page only; the native share sheet on phones
(`navigator.share`), the links row elsewhere; one share kit. The site supplies its words, Follow accounts and the
campaign's share pictures through filters (`kits_share_context`, `kits_share_pictures`). The three
`animal-writes/*` names stay registered as aliases that render through `kits/share`, so templates and patterns
render unchanged; blocks-spec 2.5 has the attributes.

**Not built in this PR:** the three render paths depend on tenant functions (`aw_page_share_kit`,
`aw_follow_accounts`, campaign meta, `aw_use_component_style`). Moving them is more than a wrapper and its output
must be checked on Home, an item, a listing, a term page and a campaign at 375/768/1280. Effort M, risk medium
(visible on every page type).

## 18. Taxonomies: one tag and category system

**Owner rule: one tag/category system per project.** Presets register **no taxonomy of their own**. They use only
core `category` and `post_tag` and declare which terms or roles they use, with labels and icons. Term archives stay
the core `/category/` and `/tag/` block templates.

| Taxonomy | Today | Plan | Side |
|---|---|---|---|
| `category` (core) | on every content type (`content-types.php:84`; owner, 15 Oct) | unchanged; presets list it | both |
| `post_tag` (core) | on all types (`tags.php:41`); one pool with Resource/Opportunity/Library labels and Directory types/services; icons in `AW_TAG_ICONS` | unchanged; a preset declares its tag roles (e.g. "Resource type: guide, research") and icons through a filter | both |
| `aw_campaign_link` | item → campaign relation, no public page (`campaign-links.php:26`); News, Library, Resources, Press, Opportunities, Campaigns, social posts; campaign page scripts query it | **existing non-tag relation, owner to confirm**; unchanged | [admin] / data |
| `aw_media_group` | attachments, admin only (`media-groups.php:19`, `public` false) | **existing non-tag relation, owner to confirm**; generic in shape, could move to Kits Core under the same slug later | [admin] |

Front-end: term archives are `category.html` / `tag.html` (block templates); the hub filters run through Query Loop
+ `kits_filter_bar()`; term icons from the tag roles; term pages get `CollectionPage` from RankWeb (section 13);
term sharing through `kits/share` `context: term` (section 17).
Admin: core term screens; REST `show_in_rest` already on; core sitemaps list category and tag archives (Rank Math's
sitemap on live).
No term, slug or data change for this site's enabled presets.

## 19. WordPress-native integration

Native first; SCF, Rank Math and form adapters are layers on top.

| API | Used today | Custom today | Native target | Effort / risk | Side |
|---|---|---|---|---|---|
| `register_post_type` / `register_taxonomy` + `show_in_rest` | yes | arguments built in `aw_content_type_args()` | presets (section 12) on the same calls | M / medium | [admin] |
| `register_post_meta` + `show_in_rest` | yes (`meta.php`) | SCF value guard keeps formats | unchanged; presets declare meta | S / low | [admin] |
| block.json (apiVersion 3) | `kits/hero` | 34 `animal-writes/*` blocks registered in PHP | block.json per new Kits block; old names kept | M / low | [front-end] |
| Block Bindings API | `animal-writes/field` | — | `kits/post-field` (**this PR**) | done | [front-end] |
| Query Loop variations | no | `[aw_related]`, item "more", partners | `kits/related`, `kits/item-more`, `kits/partners` | M / medium | [front-end] |
| Block variations / styles | Group styles (Section, Head band) | page-scoped layout CSS | layouts as block styles (section 16) | M / low | [front-end] |
| Patterns + pattern categories | theme patterns, synced patterns | — | one "Kits" category per preset | S / low | [front-end] |
| Block templates / parts | yes (theme) | — | `single-{type}` / `archive-{type}` per preset | S / low | [front-end] |
| theme.json tokens, style variations | yes | `--kit-*` tokens in CSS files | tokens stay; Styles values map to them (`blocks.php`) | — | [front-end] |
| Interactivity API | dropdown (`kits/dropdown`) | `campaign.js`, `kit.js` handlers | rail, share, filter stores | M / low | [front-end] |
| Core blocks first | yes | card parts as custom blocks | core blocks + bindings (blocks-spec 3.6) | M / medium | [front-end] |
| Template hierarchy | yes | `[aw_category_hub]` | Query Loops in `category.html` | M / medium | [front-end] |
| Core REST | yes | `aw/v1/*` routes (near-opps, social intake) | keep only where core has no route | — | both |
| Capabilities | `manage_kits` (Kits) | some screens on `manage_options` | map per screen to core caps / `manage_kits` | S / low | [admin] |
| Embeds / oEmbed | core embeds | YouTube privacy player in the social card | `core/embed` with `youtube-nocookie` | S / low | [front-end] |
| Core sitemaps | Rank Math's sitemap on live | — | unchanged (Rank Math) | — | [front-end] |
| i18n | `__()`, `.pot` per plugin | — | `wp_set_script_translations()` for each new block script | S / low | both |
| WP-CLI | `wp aw …`, `wp kits …` | — | presets get `wp kits preset list` (read-only) | S / low | [admin] |
| Editor panels | ServerSideRender previews | — | core components (`InspectorControls`, `PanelBody`) only | S / low | [admin] |

## 21. Added to scope: more tenant files, and the sponsored blocks

| File | Lines | What it does | Side | Goes to | Effort / risk |
|---|---|---|---|---|---|
| `model.php` | 334 | Content model: Library sub-type, court, category → listing types for "Find help", two-sided relations between items | [admin] (relation boxes) + data | Final: Kits Core (preset relations, two-sided); Library sub-type and court → `kits-library` settings; category → listing types → DirectoryWeb; storage unchanged | M / medium (relations shown on both sides) |
| `reports.php` | 204 | "Report a mistake": `/report/?item=`, `[aw_report_box]`, private type `aw_report` | [front-end] form + [admin] records | Final: form → Fluent Forms block via Kits Core forms; `aw_report` records → Kits Core private-record preset | S / low |
| `near-opps.php` | 82 | Home "Opportunities near you": server draws the newest, `GET aw/v1/opps-near?state=` swaps in local rows | [front-end] | Final: Kits Core, a Query Loop variation with a "near the visitor" filter; the REST route stays custom (core has none) | M / medium (cached Home) |
| `team-dates.php` | 351 | The team's calendar, Kits → Dates (option `aw_team_dates`); PlanWeb takes over when switched on | [admin] | Final: PlanWeb, which takes ownership of option `aw_team_dates` (name unchanged) | — / low |
| `category-moves.php` | 78 | 301s from the old `/topic/{slug}/` addresses to `/category/{slug}/` | [front-end] (redirects) | Final: RankWeb redirects; the old-to-new list is RankWeb data | none / low |
| `law-links.php` | 204 | Acts and Rules: crawlable links to every Act and Rule (`render_block_core/query`) | [front-end] | RankWeb (internal-links track) | S / low |
| `issuers.php` | 106 | Issuer label on Library items (meta `aw_issuer`) | [front-end] label + [admin] field | Final: Kits Core `kits-library` (binding key `issuer`); the issuer list (`aw_issuer_short`) is preset settings | S / low |
| `research-citation.php` | 141 | Research credit line in Sources; the ScholarlyArticle it is based on in schema | [front-end] | Final: credit line → Kits Core `kits-library` binding key; schema → RankWeb (section 13) | S / low |
| `entity-links.php` | 112 | Names in a summary linked to their pages (`render_block_core/post-excerpt`, meta `aw_entities`) | [front-end] | RankWeb (internal linking), reading SourceWeb's `aw_entities` | M / medium (changes summary text links) |
| `retention.php` | 105 | Content rules: nothing deleted; Opportunities past their deadline closed (out of the list, noindex via RankWeb) | [front-end] lists + data rules | Final: Kits Core, a generic "closed after date" state on `kits-opportunity`; noindex → RankWeb | S / low |
| `systems.php` | 462 | Shared list helpers and the hub list filters (`AW_LIST_FILTERS`); `aw_i()` / `aw_date()` (now wrappers, this PR) | [front-end] | Final: Kits Core `kits_collection_filters` / `kits/collection-filter` (blocks-spec 2.4); this site's filter values are preset settings | L / medium (hub pages) |
| `category-images.php` | 155 | Category and tag photos on the term screens (term meta `aw_category_image`, attachment ID); default photos in option `aw_category_images['default']`; items fall back to their category's photo | [admin] term field + [front-end] item image | **Kits Core**, as a generic "term image" field on any taxonomy. The `aw_category_image` key and the default option stay unchanged; images stay in the Media Library | S–M / low (admin field is core UI; output unchanged) |

None of these moves in this PR (docs only). Every move keeps its stored keys and options.

**`sponsored-blocks.php` — KEEP (owner, 18 Nov 2026).** `animal-writes/sponsored-item` and
`animal-writes/sponsored-banner` (104 lines; [front-end] blocks, inserter group "Sponsored") are **dormant until the
monetisation plan; the hide-monetary rule applies** (no money items shown on the site until the owner says so).
Not moved, renamed or deleted here. Final: Kits Core, kept dormant; when the monetisation plan (including ads) starts, they are the base for
`kits/sponsored` (blocks-spec 2.7), and the Advanced Ads rule (a page with a sponsored block carries no ads) stays.

## 20. Updated staged plan and owner questions

| Stage | Work | Visible change |
|---|---|---|
| 2 (this PR) | icon/date, `kits/post-field`, **SCF bridge** (`kits_scf_active`, `kits_scf_json_folder`, `kits_scf_groups`); callers switched to the `kits_*` names | none |
| 3 | The files open PRs touch (faq-data.php, tags.php, DirectoryWeb listing files) switch to `kits_*`; then the `aw_i` / `aw_date` / `aw_scf_*` wrappers are deleted. DirectoryWeb uses `kits_scf_json_folder()` and puts its listing keys on `kits_post_fields` | none |
| 4 | Preset loader + all seven new presets, each switched on or off in Kits Core (off by default). Built: `plugins/kits-core/includes/presets.php` + `includes/presets/`, switches in Kits → Settings → Content types (option `kits_presets_enabled`), `wp kits preset list`, tests in `plugins/kits-core/tests/presets-tests.php`. Templates and patterns per preset follow with the layouts (stage 5) | new types only, when switched on |
| 5 | Layout block styles + rail; `kits/share` with aliases | yes, on the owner's yes |
| 6 | Schema contract read by RankWeb | `<head>` only |
| 7 | This site's existing types onto their `kits-*` presets (byte-identical registration) | none intended |

Owner questions (added):
5. Which new presets first: `kits-portfolio`, `kits-card`, `kits-infographic`; and are `kits-case-study`, `kits-event`, `kits-person`, `kits-testimonial` wanted?
6. Tabs: answered 18 Nov, **yes**, allowed (see section 16).
7. Card minimum width: answered 18 Nov, **17rem** (the Kits token). The aw-core override is removed in this PR.
8. `aw_campaign_link` and `aw_media_group`: answered 18 Nov, **rename to `kits_campaign_link` and `kits_media_group`**. They stay
   taxonomies (the right native tool for both); the rename is a data migration in its own PR (see the owner answers).
9. Masonry: wanted? (Reading order and screen readers make it the riskiest layout.)
10. Sponsored blocks: confirm they stay dormant until the monetisation plan (section 21).
11. Card minimum width: answered, 17rem (see 7).

Owner answers (18 Nov 2026):
- **Old names (question 4):** replace aw with kits, no compat file. Callers in this PR now use `kits_icon()`,
  `kits_date()`, `kits_scf_active()`, `kits_scf_groups()` and `kits/post-field`. The old functions stay only for the
  files open PRs touch (faq-data.php, tags.php, DirectoryWeb listing files) and are deleted once those switch (stage 3).
  The `animal-writes/field` source name stays registered while any saved post or synced pattern in the database still
  binds to it; a search of the live posts decides when it can go.
- **Presets (question 5):** all seven in one go (`kits-portfolio`, `kits-card`, `kits-infographic`, `kits-case-study`,
  `kits-event`, `kits-person`, `kits-testimonial`). Each preset has an on/off switch in Kits Core (admin, core Settings
  API), off by default, so the admin menu only shows the types a site uses.
- **Relations (question 8):** the owner asked why. `aw_campaign_link` ties an item (News, Library, guide, press) to a
  campaign: it has no public archive, campaign pages and SourceWeb query it, and it must not mix with topic tags.
  `aw_media_group` groups pictures in the Media Library for admin only. Both could become post meta or a tag instead;
  that stays the owner's call, and nothing changes until then.
- **Masonry (question 9):** wanted, as one layout option of the layout block (section 16), with DOM order = reading order.
- **Sponsored blocks (question 10):** yes, dormant until the monetisation plan.
- **Social card rename (question 2):** yes, `aw-social-*` → `kit-social-*`, in its own PR (both aw.css copies, markup).

---

## 22. Final homes: animal-writes-core is deleted (owner, 18 Nov 2026)

No code stays in animal-writes-core. Every row above that used to say "stays tenant" or "later" now names its final
home: Kits Core (generic code and presets), RankWeb, DirectoryWeb, SourceWeb, MailWeb, PlanWeb, the Kits theme, or
**site data**. Site data means options, preset settings, global styles, SCF field groups, Site Editor templates and
page content. Stored names never change. That covers meta keys, CPT and taxonomy slugs, option names, block names,
the binding source `animal-writes/field` and the SCF group and field keys (section 6). The plugin that becomes their
home owns them under the same names.

**Order.** Each item moves only after its home exists and its callers have switched, (a) to (d) below. The plugin is
switched off only when nothing outside it calls into it. Before it is deleted, the "Delete this plugin's data" box
must be unticked (d).

### Final home per part

Destinations, one per row: **Kits Core**, **RankWeb**, **DirectoryWeb**, **SourceWeb**, **MailWeb**, **PlanWeb**,
**Kits theme**, **Site settings** (options, preset settings, global styles, SCF groups, Site Editor templates, page
content) or **Native**, where a WordPress core API or feature does the job and no plugin code is kept for it. The
Native column names the core part each row relies on.

| Part (today in animal-writes-core) | Destination | Native (core API or feature used) |
|---|---|---|
| CPT rows `aw_news`, `aw_legal`, `aw_campaign`, `aw_opportunity`, `aw_press`, `aw_social` (`content-types.php`, `press.php`, `social-posts.php`) | Kits Core presets; slugs and labels in Site settings | `register_post_type()` |
| `post` relabelled as Resources | Kits Core `kits-resource`; labels in Site settings | core Posts, `register_post_type_args` |
| Private records `aw_report`, `aw_volunteer` | Kits Core private-record presets | `register_post_type()` (`public` false) |
| Private record `aw_message` | MailWeb | — |
| Meta keys (`meta.php`) | **Native**, declared by each preset | `register_post_meta()` with `show_in_rest`, `sanitize_callback`, `auth_callback` |
| Taxonomy `aw_campaign_link` → `kits_campaign_link` (owner, 18 Nov) | Kits Core (`kits-campaign`) | `register_taxonomy()` |
| Taxonomy `aw_media_group` → `kits_media_group` (owner, 18 Nov) | Kits Core | `register_taxonomy()` on attachments, Media Library |
| Hub list filters (`AW_LIST_FILTERS`, `systems.php`) | Kits Core `kits/collection-filter`; values in Site settings | core `category` / `post_tag`, `query_loop_block_query_vars` |
| Hub pages (`hubs.php`) | **Native** Query Loop in page content + Kits Core filter | core Query Loop block |
| `aw_i()` / `aw_date()` | Kits Core (`kits_icon`, `kits_date`), done here | `date_i18n()` / `get_option( 'date_format' )` |
| Binding source and keys (`bindings.php`) | Kits Core `kits/post-field` (done here); listing keys → DirectoryWeb | Block Bindings API |
| 34 `animal-writes/*` blocks (`hub-blocks.php`) | Kits Core loader, same names; listing blocks → DirectoryWeb | `register_block_type()`, ServerSideRender |
| Item head rows (`item-head.php`) | Kits Core + Kits theme patterns | core Post Date, Post Terms, Post Featured Image blocks |
| `[aw_section_head]` (`sections.php`) | **Native**, retired (no live use) | core Group and Heading blocks |
| Form pages (`form-blocks.php`), report form (`reports.php`) | Kits Core forms; claim → DirectoryWeb | — (Fluent Forms block in page content) |
| Campaign rules, letters, sticky bar (`campaign-tools.php`) | Kits Core `kits-campaign`, `kits/countdown`, `kits/sticky-cta`, `kits/letter` | post meta |
| Campaign figures (`campaign-record.php`) | Kits Core `kits/metric` | post meta |
| Social post card (`social-posts.php`) | Kits Core (with the `kit-social-*` rename) | — |
| Related groups (`related.php`) | Kits Core `kits/related` variation | core Query Loop block |
| Media & PR og: fetch (`press.php`) | SourceWeb | `wp_safe_remote_get()` |
| Home "near you" (`near-opps.php`) | Kits Core | REST API (`register_rest_route()`), Query Loop |
| Home and term pages (`home.php`, `term-page.php`) | Kits theme patterns + Site settings (page content) | block templates, patterns |
| Key facts, summary display (`news-article.php`, `summary-display.php`) | Kits Core `kits-news` | — |
| Block states demo (`block-states.php`) | Kits Core demo | — |
| Content model and relations (`model.php`) | Kits Core (preset relations); category → listing types → DirectoryWeb | post meta |
| Team dates (`team-dates.php`, option `aw_team_dates`) | PlanWeb | — |
| Old `/topic/` redirects (`category-moves.php`) | RankWeb | `wp_safe_redirect()` |
| Acts and Rules links (`law-links.php`), entity links (`entity-links.php`) | RankWeb | `render_block` filter |
| Issuer label (`issuers.php`) | Kits Core `kits-library`; issuer list in Site settings | post meta |
| Research credit line / schema (`research-citation.php`) | Kits Core `kits-library` / RankWeb | — |
| Closing expired Opportunities (`retention.php`) | Kits Core state on `kits-opportunity`; noindex → RankWeb | **wp-cron** (`wp_schedule_event()`) |
| Category and tag photos (`category-images.php`) | Kits Core term image | `register_term_meta()`, Media Library |
| Sponsored blocks (`sponsored-blocks.php`) | Kits Core, dormant | — |
| SCF field groups (13) and value guard (`scf.php`) | Site settings (groups, same keys); guard → Kits Core SCF bridge | — |
| Brand token values (`tokens.css`) | Site settings (style variation `styles/animal-writes.json`, active global styles) | Global Styles / `theme.json` |
| `aw.css` and component sheets | Kits theme `kit.css` (generic) or the style variation's CSS (AW only) | — |
| AW templates, parts, patterns, `theme.json` | Kits theme (generic) or Site settings (Site Editor templates, synced patterns) | block templates, `wp_template` / `wp_template_part`, `wp_block` |
| Settings screens for the options in (d) | the new owner's screen | Settings API |
| MailWeb config (`mailweb-aw.php`) | MailWeb, with its values in Site settings | — |
| FAQ data, breadcrumbs (`faq-data.php`, `breadcrumbs.php`) | RankWeb | — |

### (a) Callers in other plugins that must switch first

The full list, per function and file, is in `docs/aw-core-callers.md`: 482 calls to 150-odd animal-writes-core
functions, from `wordpress-dev` at `302f431b`.

| Plugin | Calls | Functions | Main ones | Switch to |
|---|---|---|---|---|
| DirectoryWeb | 188 | 57 | `aw_i` 22, `aw_use_component_style` 14, `aw_item_editor_preview` 13, `aw_listing_type_slugs` 12, `aw_date` 11, `aw_listing_phones` 10, `aw_ff_*` 16, `aw_binding_value` 1, `aw_scf_active` 1, the `aw_scf_groups` filter | `kits_icon` / `kits_date` / `kits_scf_*` / `kits_post_fields`. The listing helpers (`aw_listing_*`, phones, hours, services) become DirectoryWeb's own code; the form helpers (`aw_ff_*`) go to Kits Core forms |
| RankWeb (`aw-seo-extras`) | 173 | 44 | `aw_seo_plain` 76, `aw_hub_*` 30, `aw_retention_state` 6, `aw_text` 5, `aw_research_study_title` 4, `aw_category_image_id` 2 | RankWeb's own text helper; hub, retention, issuer and category-image readers come from Kits Core (presets, term image); listing readers come from DirectoryWeb |
| SourceWeb (`aw-sourcing`) | 79 | 33 | `aw_listing_phones` 8, `aw_post_types` 6, `aw_service_slugs` 6, `aw_sanitize_meta` 5, `aw_date` 4 + `aw_i` 3 (switched in this PR), tags/issuer/campaign-link setters, research and press ingest; `AW_CORE_DIR` in `aw-sourcing.php` and `relay.php` | Kits Core presets (type list, meta sanitising, tags, campaign links); DirectoryWeb (listing writes); ingest and research code moves into SourceWeb |
| InsightWeb | 7 | 7 | `aw_error_count_*` 6, `aw_key` 1 | InsightWeb owns the error count; `aw_key` follows the keys plan (each plugin keeps its own keys) |
| PlanWeb | 2 | 2 | `aw_core_reset_sections`, `aw_social_on` | Kits Core |
| MailWeb | 1 | 1 | `aw_crm_contact_for` | MailWeb |
| `tools/` (ci, content, directory, lgd) | 33 | — | `aw_core_screen_*` (ci), `aw_listing_*` (directory), `aw_hub_item`, `aw_press_ingest` | follow the functions they call |

The SourceWeb count is 7 `aw_i()` / `aw_date()` calls (all in `enrichment-front.php`, now switched), not about 46.
The rest of SourceWeb's 79 calls go to other helpers.

### (b) Site CSS and values that would change on live if animal-writes-core vanished

animal-writes-core enqueues `theme/assets/css/tokens.css` ("Brand values over the Kits defaults",
`theme-parts-assets.php:271`). It also enqueues about 20 component sheets (`aw.css`, `aw-home.css`, `aw-single.css`,
`aw-listing.css`, `aw-lists.css`, `aw-directory.css`, `aw-forms.css`, …) and the campaign sheets in `assets/css/`
(`campaign.css` 1,103 lines, `campaign-lab.css` 922, `campaign-record.css` 413, `aw-camp-*`, `aw-share.css`).
It also supplies the AW copy of the theme folder: `theme.json` (different from the Kits theme's), 11 templates (4
with the same name as the Kits theme's: `404`, `archive`, `page`, `single`), 4 parts, 19 patterns, 9 images. If the
plugin vanished, these 19 token overrides would fall back to the Kits defaults without any warning:

| Token | Live (aw-core) | Kits default |
|---|---|---|
| `--kit-card-min` | 18rem until this PR | 17rem. **Owner, 18 Nov: 17rem**; this PR drops the override (cards about 16px narrower in grids) |
| `--kit-card-min-width` | `min(100%, var(--kit-card-min))` | auto |
| `--kit-card-stacked-gap` | `clamp(space-3, 5vw, space-5)` | space-5 |
| `--kit-card-stacked-media` / `-max` / `-flex` / `-min` | `clamp(thumb-min, 30%, thumb-max)` / none / `0 1 auto` / thumb-min | 40% / 40% / none / auto |
| `--kit-card-stacked-body-flex` / `-min` | `1 1 0` / `--kit-body-min` (10rem) | `1 1 auto` / 0 |
| `--kit-card-block-media-max` | `--kit-card-media-max` (22.5rem) | none |
| `--kit-color-on-accent` | `#141414` | `var(--color-ash)` |
| `--kit-surface-page` | `var(--kit-color-canvas)` | `var(--neutral-1)` |
| `--kit-field-text-size` | 1rem (phones; .875rem from 768) | .875rem |
| `--kit-btn-font-size` | .875rem | `var(--fluid-body-md)` |
| `--kit-section-desc-size` | .875rem | `var(--kit-type-lead)` |
| `--kit-lead-title-leading` | 1.25 | 1.2 |
| `--kit-type-sm` / `--kit-type-xs` / `--kit-meta-size` (from 768) | fixed clamps / .8125rem | fluid tokens / 12px |

Four more tokens exist only in aw-core and would become undefined: `--kit-card-media-max`, `--kit-thumb-min`,
`--kit-thumb-max`, `--kit-body-min`.

**Where each goes:**
- **Brand token values** → the theme's style variation `themes/kits-by-wugweb/styles/animal-writes.json`
  (it already exists, and the aw-core copy is identical), as the variation's `styles.css`. The site keeps it as the
  active global styles (site data). Generic card tokens (`--kit-card-media-max`, thumb and body minimums) → the Kits
  theme's `tokens.json`, where `kit-tokens.css` is generated from.
- **`aw.css` and the component sheets** → whatever is generic goes into the Kits theme `kit.css` (stacked card, social
  card, meta row). What only AW uses goes into the style variation's CSS. Today the two `aw.css` copies stay
  identical.
- **Campaign sheets** → Kits Core, with the `kits-campaign` preset. Each campaign's colours and motif are its
  settings (post meta), not code.
- **Templates, parts, patterns, `theme.json`** → generic ones into the Kits theme. AW-only ones become site data
  (Site Editor templates and parts, synced patterns). Before the switch, diff the two `theme.json` files.

### (c) The 13 SCF field groups and the Library meta

| Group | Type | Final home |
|---|---|---|
| `group_aw_news` | `aw_news` | `kits-news` preset |
| `group_aw_legal`, `group_aw_legal_document` (**Library meta**: source url/name/date, file id/url, number, in force, email gate `aw_requires_email`; issuer `aw_issuer` comes from `issuers.php`) | `aw_legal` | `kits-library` preset |
| `group_aw_campaign`, `_dates`, `_document`, `_orgs`, `_outcome`, `_share`, `_social` (7) | `aw_campaign` | `kits-campaign` preset |
| `group_aw_opportunity` | `aw_opportunity` | `kits-opportunity` preset |
| `group_aw_resource` | `post` | `kits-resource` preset |
| `group_aw_social` | `aw_social` | Kits Core social preset |

**How they are stored.**
- Each preset registers its fields with `register_post_meta()` under the meta keys its settings name (here the
  existing `aw_*` keys), so content and `kits/post-field` work without SCF.
- This site's 13 group JSON files are **site data**: they keep their group and field keys, which saved posts point
  to through `_aw_*` reference meta.
- They are loaded from a site folder that Kits Core registers with `kits_scf_json_folder()`, outside
  animal-writes-core, so deleting the plugin cannot drop them. The repo keeps a copy.
- `meta.php`'s `register_post_meta()` calls (keys, types, sanitising) move into the presets. The SCF value guard
  (`scf.php`) goes to Kits Core's SCF bridge.

### (d) Options other plugins read that aw-core's `uninstall.php` would delete

`uninstall.php` deletes these only when `aw_delete_data_on_uninstall` is true ("Delete this plugin's data"). Ownership
moves to the reader; the option names stay the same.

| Option | Read by | New owner |
|---|---|---|
| `aw_team_dates` | PlanWeb | PlanWeb |
| `aw_keys` | InsightWeb | each plugin's own keys (keys plan); migrate, then retire |
| `aw_ff_forms` | DirectoryWeb, `tools/forms` | Kits Core forms |
| `aw_ff_send_email` | DirectoryWeb | Kits Core forms |
| `aw_nl_ff_form_full` | `tools/forms` | MailWeb (newsletter) |
| `aw_district_helplines`, `aw_place_intros`, `aw_gov_verified_live` | DirectoryWeb | DirectoryWeb |
| `aw_place_counts` | DirectoryWeb, RankWeb | DirectoryWeb |
| `aw_social_on`, `aw_social_show` | SourceWeb, PlanWeb | Kits Core (social preset settings) |
| `aw_category_images` (and term meta `aw_category_image`) | RankWeb through `kits_category_image_id()` | Kits Core term image |
| `aw_issuer_short`, `aw_press_logos`, `aw_internal_org`, `aw_elsewhere`, `aw_follow_reddit`, `aw_follow_discord`, `aw_sample_listing_ids`, `aw_cdn_ttl*` | aw-core only today | Kits Core preset or share settings (`aw_sample_listing_ids` → DirectoryWeb; `aw_cdn_ttl*` → Kits Core deploy purge) |

The same uninstall also deletes the DirectoryWeb transient `directoryweb_stats` and unschedules its
`directoryweb_stats_build` event. The two tables `{prefix}aw_form_raw` and `{prefix}aw_camp_letters` are kept today;
they are supporters' own records, always kept, and their ownership goes to Kits Core forms and `kits-campaign`.

Until every row above has moved, the box stays unticked. When animal-writes-core is deleted, its `uninstall.php` goes
with it, so no option is deleted.

**Taxonomy rename (owner, 18 Nov 2026): "rename aw to kits".** `aw_campaign_link` becomes `kits_campaign_link`, and
`aw_media_group` becomes `kits_media_group`. Both stay taxonomies. The post-meta and tag alternatives are dropped: a
taxonomy is what WordPress gives for "many items share one label". It brings counts, admin filters and a REST route,
and it is queried with `tax_query`. A tag would mix campaigns with topic tags and give them public tag pages; post meta
would need a slow meta query on every campaign page. This is a stored-name change (section 6), so it gets its own PR:
- **Built in PR #145.** The code registers only the new names. The first web or cron load after the deploy renames
  the `term_taxonomy` rows once (one UPDATE per taxonomy), after saving the renamed IDs in
  `kits_taxonomy_rename_backup`. Term IDs, slugs, term meta and assignments do not change.
- **Saved blocks.** Query Loops saved with `"taxQuery":{"aw_campaign_link":…}` are mapped to the new name by
  `kits_taxonomy_current()`, so post content is not rewritten.
- **WP-CLI.** `wp kits taxonomy-rename status | run [--dry-run] | undo`. `undo` goes together with rolling the code
  back.
- **Callers.** 42 mentions of `aw_campaign_link` in 14 files and 15 of `aw_media_group` in 4 files, all switched in
  the same PR. The media `rest_base` stays `media-groups`.
