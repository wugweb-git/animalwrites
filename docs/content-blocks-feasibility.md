# Content blocks under Kits Core: feasibility (18 Nov 2026)

Scope: the content parts of `plugins/animal-writes-core` (Animal Writes Core, "the tenant plugin"): the content types,
their render files, and the blocks and helpers they print with. The goal is to retire the tenant plugin step by step.
Generic rendering moves to Kits Core as blocks, patterns and helpers. What only Animal Writes needs stays as tenant
config. The CPT registration stays thin.

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
- **The site** (here: Animal Writes Core, and later only a small config) says *what* the content is: its post types,
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
| `post` | Resources (`/resources/`) | WordPress; relabelled by `aw_resources_post_args()` (`content-types.php`) | `group_aw_resource.json` | Stays tenant config (labels and slug are AW's words) |
| `page` | Pages | WordPress | — | No change |
| `aw_news` | News | `aw_register_content_types()` from `aw_post_types()` (`content-types.php:23`) | `group_aw_news.json` | Registration stays in the tenant |
| `aw_legal` | Library | same | `group_aw_legal*.json` | stays |
| `aw_campaign` | Campaign Hub | same | 7 `group_aw_campaign*.json` | stays |
| `aw_opportunity` | Opportunities | same | `group_aw_opportunity.json` | stays |
| `aw_listing` | Directory | DirectoryWeb (`directoryweb/includes/bootstrap.php:20`, with `aw_content_type_args()`) | DirectoryWeb | DirectoryWeb's |
| `aw_press` | Media & PR | `press.php:102` | in code | stays |
| `aw_social` | Social posts (under News; off by default) | `social-posts.php:127` | `group_aw_social.json` | stays |
| `aw_report`, `aw_volunteer`, `aw_message` | private records | `reports.php`, `volunteers.php`, `pages.php` | — | stays (not content) |
| Adoption | a **page** (`/adoption/`, 200 on live) with a Query Loop of Opportunities (`hubs.php:297`) | — | — | Not a type; no move needed |
| Taxonomy `aw_campaign_link` | campaign items | `campaign-links.php:26` | — | stays (SourceWeb and campaign pages query it) |

**Why the registration stays in the tenant (not Kits Core):** `aw_post_types()` is six rows of the site's own words
(names, slugs, supports). Moving it to Kits Core would put `aw_` slugs in the generic plugin, or need a
"register any type from config" layer, which is the CPT framework we do not want. When the tenant plugin is finally
reduced, these rows become a small site plugin or a `kits-config.php` filter answer, still `register_post_type()`.
CPT slugs, rewrite slugs and meta keys never change (section 6).

---

## 2. Render files: what moves, what stays

"Generic" = no `aw_` meta key, no AW words, no AW post type, or one that a filter can supply. Line counts as of today.

| File (tenant) | Lines | What it renders | Generic part | Verdict |
|---|---|---|---|---|
| `systems.php` (`aw_i()`, `aw_date()`) | 462 | The icon span and **the one date component** (calendar icon + `<time>`), used by about 100 calls in the tenant, DirectoryWeb and SourceWeb | **All of both functions** (they print only `kit-ico`/`kit-date` markup) | **Move now** → `kits_icon()`, `kits_date()` (Stage 2). The list filters (`AW_LIST_FILTERS`) stay (AW taxonomies) |
| `bindings.php` (source `animal-writes/field`) | 246 | Block Bindings: card and item fields bound to core blocks; empty bound blocks removed | The source, the key registry, "drop a block whose bound value is empty" | **Move now** → source `kits/post-field`, filter `kits_post_fields` (Stage 2). The keys and their AW rules stay in the tenant; the listing contact-button rules stay (Directory) |
| `hub-blocks.php` | 770 | 34 `animal-writes/*` server-rendered blocks (band, card parts, item rows, listing groups) and their loader | The loader (list → `register_block_type` + ServerSideRender preview) | Later (phase B). Block names `animal-writes/*` are saved in content and templates: they never rename; a Kits loader can register them under the same names. The listing blocks are DirectoryWeb's |
| `item-head.php` | 588 | The item page rows: meta row, sources, tags, share, "more" | Meta-row order (date first), card media | Later, through `kits/post-field` and Query Loop variations (blocks-spec 3.6, 3.18) |
| `sections.php` | 400 | `[aw_section_head]` (no live use) | — | Retire with the head band (blocks-spec 3.1) |
| `hubs.php` | 799 | Hub page Query Loops and the filters they read | Query-var plumbing (`query_loop_block_query_vars`) | Later with `kits/collection-filter` (blocks-spec 2.4) |
| `form-blocks.php` | 308 | Volunteer, claim, report and thank-you pages | — | Fluent Forms block (blocks-spec 3.7, 3.14); claim is DirectoryWeb's |
| `campaign-tools.php` | 1588 | Campaign state, letters, sticky bar, share section, **timelines** (`aw_camp_dates_timeline()`, `aw_steps_dateline()`) | Timelines already call `kits_datecard()` / `kits_milestone_state()` | The calendar is already Kits. The rest is campaign rules: stays tenant config until `kits/countdown`, `kits/sticky-cta`, `kits/letter` exist |
| `campaign-record.php` | 362 | Campaign metrics, impact, outcome | — | Tenant (data) until `kits/metric` (blocks-spec 2.3) |
| `social-posts.php` (`aw_social_card_html()`) | 1174 | **Social post card**, one sitewide component | The card markup from an array (no meta reads) | Phase B: needs the `aw-social-*` classes renamed in CSS (visible risk) and `AW_SOCIAL_PLATFORMS` / `aw_social_parse_url()` moved with it. Not in Stage 2 |
| `related.php` | 356 | `[aw_related]` groups | — | Query Loop variation `kits/related` (blocks-spec 3.3) |
| `press.php` | 459 | Media & PR cards, og: fetch | — | Tenant (data model) |
| `near-opps.php` | ~100 | Home "near you" REST swap | — | Tenant |
| `home.php`, `term-page.php` | 468, ~120 | Home and term pages | Story-band dates use `kits_datecard()` already | Patterns (blocks-spec 3.18) |
| `news-article.php`, `summary-display.php` | small | Key facts card, summary display | — | Tenant |
| `block-states.php` | 248 | AW block states on `/kits-demo/` | — | Tenant (it shows AW blocks) |

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
| Kits theme templates (`plugins/animal-writes-core/theme/`, the AW child copy) | `single.html` binds `animal-writes/field` | Templates use `animal-writes/*` blocks | None: `animal-writes/field` stays registered with the same keys and output |
| `kit.css` / `aw.css` (two identical copies of `aw.css`: theme and plugin; change both, `diff -q`) | `kit-date`, `kit-ico`, `aw-date` classes | — | None: markup byte-identical; no CSS change in Stage 2 |
| RankWeb (schema, FAQ, breadcrumbs: its own track) | `faq-data.php`, `breadcrumbs.php` stay put | RankWeb reads tenant helpers via `function_exists` | None; `aw_date()` keeps its name and output |
| DirectoryWeb (its own track) | Tenant loads it; `aw_content_type_args()` | 40 calls to `aw_i()` / `aw_date()` (`listing-template.php`, `listing-profile.php`, `gov-verified.php` and others); `aw_binding_value( 'blacklisted_label' )` (`gov-verified.php:213`) | None now. After this move DirectoryWeb can call `kits_icon()` / `kits_date()` and register its listing keys on `kits_post_fields`, which is what lets it stop depending on the tenant |
| SourceWeb (`aw-sourcing`) / Sourcing | `aw_campaign_link` taxonomy for campaign items | 6 calls to `aw_i()`/`aw_date()` (`enrichment-front.php`) | None; can switch to `kits_*` later |
| MailWeb | `mailweb-aw.php` (tenant config for mail) | — | None |
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
| F. Query Loop variations `kits/related`, `kits/item-more`, `kits/term-related` (blocks-spec 3.3, 3.18) | M | Medium (query cost; `aw_news` re-sort) | Owner review of blocks-spec first |
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
| 5 | F + G + H: blocks-spec phases 1–3 | Owner review of blocks-spec | yes, per page on the owner's yes |
| 6 | The tenant keeps only: CPT rows, meta keys, binding keys, campaign rules, words. It becomes a small site plugin | Owner | none |

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

1. **Blocks spec review.** The bigger content moves (F, G, H) wait on `docs/blocks-spec.md`. Is it approved as written?
2. **Social card class rename (E).** `aw-social-*` → `kit-social-*` in both `aw.css` copies and the card markup. OK to
   do as its own PR with a before/after check?
3. **Where the CPT rows end up (stage 6).** A small "Animal Writes site" plugin (registration + keys + words only), or
   answers inside `kits-config.php`? Either way no Kits Core CPT layer.
4. **Compat for the old names.** When the tenant goes: keep `aw_i()` / `aw_date()` / `animal-writes/field` alive in a
   Kits Core compat file (like `compat-sourcing.php`, the precedent of 17 Nov), or require every caller to switch first?
