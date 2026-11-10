# aw.css phase 2: plan (waiting for the owner's review)

10 Nov 2026. Plan only. Nothing here is built.

## What changed from the first draft

- **No `aw-` selector goes into a Kits file.** Each group that the first draft sent to `kit-primitives.css` is now marked (a) Kits component or (b) stays in Animal Writes CSS, with the reason. The first draft contradicted itself: it said no `aw-*` selector would go into Kits, then appended about 25 KB of them in steps 2 and 3. That is gone.
- **New CSS files live in `plugins/animal-writes-core/theme/assets/css/` only.** No child-theme copies, no child `functions.php` enqueue (the child theme is being retired). The theme-copies check changes to match (section "Checks").
- **CSS loads per component, as Uncode does per module.** Directory, listing, district, type icons, Home and the other domain groups load only where they are used. They load either by a template condition or because the component's class is in the page.
- **A bytes-per-page column** (section "CSS per page"): the goal is less CSS on each page, not the same CSS in more files.
- **Raw sizes stay as they are in this phase.** They are listed at the end as candidate tokens.
- **Two corrections to the measurement**, below.

Second revision (10 Nov, after a design-system review, a UI/UX review and a WordPress loading review):
- **Loading:** render hooks only record a handle; files are enqueued at `wp_enqueue_scripts` priority 20 in a fixed order after `aw-components`. Enqueueing straight from `render_block` would move the Kits bundle and aw.css ahead of the Fluent Forms and campaign CSS (section "Per-component loading").
- **(a)/(b) changes:**
  - `aw-card-time` becomes Kits (Kits Core prints it);
  - the newsletter art stays Animal Writes;
  - the stacked-card limits become tokens instead of Kits values;
  - rules 731–733 stay in aw.css;
  - the `aw-` selectors already in Kits get an owner step.
- **Transfer size:** a gzip column next to the raw bytes. On transfer the saving is −46% on average, not −62%.

## Measurement used, and its limits

- 10 Nov 2026 capture of the 23 page types at 1280, 768 and 375, logged out, analytics blocked. For each rule group it records the elements that change when the group is removed.
- Rule indexes are those of the measured file (wordpress-dev `b7ca41d1`, 1,281 rules). Phase 1 deletes 50 dead rules (they are left out of every figure here), merges repeats and strips comments, so the numbers shift. After phase 1 the capture is re-run and rules are matched by selector.
- **Correction 1: noise.** Three effects show up in single-group runs with no link to the group removed: the newsletter card's 232px side margins at 1280, the volunteer banner's grid on /campaigns/ at 768, and the Home help tiles' scroll-in reveal at 768. Without them, **151 groups (95,381 B) are load-bearing**, not 178 (101,668 B). 27 groups (6,287 B) changed something only at those spots. Home at 768 cannot reach TOTAL 0 while the reveal runs in the capture, so the gate needs reduced motion emulated first.
- **Correction 2: "matched" is too high for 94 rules (15,393 B).** The capture splits a selector on every comma, also inside `:is(…)`, and cuts `:focus-visible` to `-visible`. The broken selector throws, and a throwing selector is counted as matched, so those rules look matched on every page. The load-bearing results are not affected: they come from deleting rules for real. The per-page figures below use only the other rules to decide where a component is used. The capture script gets the fix before phase 2 is measured.

## Destinations

1. **Animal Writes tokens** (`themes/animal-writes/tokens/tokens.json` → `tokens.css`, plugin copy kept as today): brand values.
2. **Kits** (`kit.css`, `kit-primitives.css`, `kit-tokens.css`), with **`kit-` selectors only**. A Kits rule that needs an Animal Writes value reads a token whose Kits default is today's value. Animal Writes sets its own value in `tokens.json`.
3. **Animal Writes component files** in `plugins/animal-writes-core/theme/assets/css/`, each loaded only where its component is used.
4. **aw.css** keeps what every page needs and has no Kits form (the newsletter's Fluent Forms skin, heading overrides) and the campaign rules, which stay put.

Load order: every component file depends on `aw-components` (aw.css) and is enqueued at `wp_enqueue_scripts` priority 20, so it prints after aw.css and before `aw-campaign-record` (priority 30, `campaign-record.php:270–288`), as today. Live order on Home today: Fluent Forms CSS → `kits-bundle` → `aw-components`. `aw-campaign` has no deps and is enqueued at priority 10 (`campaign-tools.php:641–659`).

Two order changes remain and are checked by the TOTAL 0 gate:
- A rule that moves into the middle of `kit.css` loses ties with later Kits rules of the same specificity. Each such tie is fixed by editing the Kits declaration in place. The ties are listed per step.
- Moved rules now print after everything that stays in aw.css. On Home the campaign rules that stay (1075–1117) sit between `aw-ed-hero` rules (1077–1095), so the `aw-ed-hero` / `aw-camp-band` ties are re-checked in commit 4.

## Per-component loading (the Uncode way)

- **Loader in Kits, files from Animal Writes.** Kits gets a small, generic registry: a filter `kits_component_styles` that maps a style handle to either a template condition (callback) or a component flag set by the PHP that prints the component. Animal Writes registers its files through that filter, in line with the 10 Nov decision that plugins join Kits through filters. Kits names none of them.
- **Two kinds of trigger:**
  - *Template condition*, checked at `wp_enqueue_scripts` 20:
    - `aw-home.css` on `is_front_page()`;
    - `aw-listing.css` on `is_singular( 'aw_listing' )` and the claim pages;
    - `aw-directory.css` on `is_tax( 'aw_place' )` and tag pages;
    - `aw-search.css` on `is_search()`;
    - `aw-volunteer.css` and `aw-contact.css` on `is_page()` with the page slug (page conditions, not template file names).
  - *Component flag* for components printed on many templates. The PHP that prints the component records the handle in a static set: `aw_type_ico` (`hub-blocks.php:348`, `directory-map.php:295`), the Fluent wrapper (`forms-fluentforms.php:250`). The plugin's own blocks (`hub-blocks.php:74`, `sponsored-blocks.php:68`, registered in PHP with no block.json) get `style_handles` for `aw-lists`, `aw-hubs`, `aw-meta` and `aw-single`. Scanning block HTML for a class in `render_block` is the fallback, only where no PHP prints the class.
- **Why recording, not enqueueing.** In a block theme, `template-canvas.php` renders the whole template (`get_the_block_template_html()`) before `wp_head`. So by `wp_enqueue_scripts` every flag is set and every file prints in the head. A file enqueued *during* render goes to the front of the queue and pulls its deps (`aw-components` → `aw-tokens` → `kits-bundle`) ahead of the Fluent Forms and campaign CSS, flipping their ties. Anything first printed in `wp_footer` (for example `aw_camp_dialogs`, `campaign-tools.php:219`) would fall to the footer, so those components use a template condition. This rests on the live head order; WP core is not in the repo, and the first commit's test confirms it.
- **No inlining.** The `path` argument is not used: the 20 KB `styles_inline_size_limit` budget is shared with core block CSS and is already used up on live (`wp-block-navigation-css` and `wp-block-cover-css` print as links). Inlined CSS is also not cached between pages.
- **Not `wp_enqueue_block_style( 'core/group' )`:** every page has groups, so it would load everywhere.
- **Editors** (post editor, Site Editor, classic editor) load every file, through the two hooks aw.css uses now (`block_editor_settings_all`, `mce_css` in `theme-parts-assets.php`).
- **Caching:** each file gets its own `filemtime` version. The Kits bundle hash changes only when a Kits file changes. Every deploy purges the cache already.

## CSS per page

Bytes are rule bytes (comments stripped), so the figures compare like with like.
- "aw.css today": the phase-1 file without the 50 dead rules, on every page.
- "after": tokens + the Kits additions + what is left in aw.css + the component files that page uses.
- The 39,020 B of rules matched on none of the 23 pages are counted on no page. Phase 0 either deletes them or files them in a component file that loads only where it is used.

| page | aw.css today | after phase 2 | saved | component files it loads |
|---|---|---|---|---|
| Home | 161,133 | 94,028 | 42% | aw-home.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Lab rules campaign | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Supreme Court campaign | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Diwali campaign | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Community dogs campaign | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Campaigns hub | 161,133 | 40,729 | 75% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-volunteer.css |
| Guide | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Cruelty guide | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Resources hub | 161,133 | 58,518 | 64% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-type-ico.css |
| News item | 161,133 | 50,982 | 68% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css |
| News hub | 161,133 | 38,669 | 76% | aw-hubs.css, aw-lists.css, aw-meta.css |
| Library item | 161,133 | 70,831 | 56% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Library hub | 161,133 | 58,518 | 64% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-type-ico.css |
| Directory listing | 161,133 | 75,357 | 53% | aw-hubs.css, aw-listing.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| State page | 161,133 | 36,267 | 77% | aw-directory.css, aw-hubs.css, aw-meta.css |
| District page | 161,133 | 36,267 | 77% | aw-directory.css, aw-hubs.css, aw-meta.css |
| Contact | 161,133 | 43,426 | 73% | aw-contact.css, aw-forms.css, aw-hubs.css, aw-meta.css, aw-volunteer.css |
| Volunteer | 161,133 | 68,517 | 57% | aw-directory.css, aw-forms.css, aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-volunteer.css |
| Claim | 161,133 | 34,947 | 78% | aw-hubs.css, aw-listing.css, aw-meta.css |
| Search results | 161,133 | 48,550 | 70% | aw-meta.css, aw-search.css, aw-type-ico.css |
| Tag page | 161,133 | 76,677 | 52% | aw-directory.css, aw-hubs.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |
| Category page | 161,133 | 72,325 | 55% | aw-hubs.css, aw-lists.css, aw-meta.css, aw-search.css, aw-single.css, aw-type-ico.css |
| 404 (renders Home) | 161,133 | 94,028 | 42% | aw-home.css, aw-lists.css, aw-meta.css, aw-single.css, aw-type-ico.css |

Average after phase 2: **61,897 B per page, against 161,133 B today (−62%).** The biggest savings are on Directory state/district pages (−77%), claim, news and campaigns hub (−75 to −78%). The smallest are on Home (−42%), which still loads its own 26 KB, and on pages that print type icons and many components (tag −52%, listing −53%).


### Transfer size (gzip)

The raw-byte table overstates the gain, because gzip shrinks one big file better than several small ones. Figures: each file rebuilt from its rules without comments, gzip level 6. Today the same rules as one file: 160,833 B raw, **25,952 B gzip**. For scale, live sends today's aw.css (with comments) as **43,216 B brotli** over HTTP/2, `cache-control: immutable`, one year (read-only request, 10 Nov). The figures below compare like with like (both minified).

| page | gzip today | gzip after | saved | AW stylesheet requests after (today: 1) |
|---|---|---|---|---|
| Home | 25,952 | 19,681 | 24% | 6 |
| Lab rules campaign | 25,952 | 15,778 | 39% | 6 |
| Supreme Court campaign | 25,952 | 15,778 | 39% | 6 |
| Diwali campaign | 25,952 | 15,778 | 39% | 6 |
| Community dogs campaign | 25,952 | 15,778 | 39% | 6 |
| Campaigns hub | 25,952 | 10,303 | 60% | 5 |
| Guide | 25,952 | 15,778 | 39% | 6 |
| Cruelty guide | 25,952 | 15,778 | 39% | 6 |
| Resources hub | 25,952 | 13,351 | 49% | 5 |
| News item | 25,952 | 12,060 | 54% | 5 |
| News hub | 25,952 | 9,633 | 63% | 4 |
| Library item | 25,952 | 15,778 | 39% | 6 |
| Library hub | 25,952 | 13,351 | 49% | 5 |
| Directory listing | 25,952 | 16,998 | 35% | 7 |
| State page | 25,952 | 9,420 | 64% | 4 |
| District page | 25,952 | 9,420 | 64% | 4 |
| Contact | 25,952 | 10,953 | 58% | 6 |
| Volunteer | 25,952 | 16,225 | 37% | 8 |
| Claim | 25,952 | 9,047 | 65% | 4 |
| Search results | 25,952 | 11,006 | 58% | 4 |
| Tag page | 25,952 | 17,371 | 33% | 7 |
| Category page | 25,952 | 16,284 | 37% | 7 |
| 404 (renders Home) | 25,952 | 19,681 | 24% | 6 |

Average after phase 2: **14,140 B gzip per page, against 25,952 B (−46%).** Requests: tokens and the Kits additions ride in files that load today, so the count is aw.css plus the component files, 4–8 per page instead of 1. On HTTP/2 these are parallel, and each file is cached on its own, so a second page loads only the files it has not seen.

Caveats:
- **State and district are low.** `aw_dir_panels_html()` (`directory-map.php:295`) prints type icons in the hidden Near-me panel, so with the component flag those pages also load `aw-type-ico.css` (+19,772 B raw, +3,718 B gzip). The capture did not see them because the panel is hidden.
- **A cheaper first win on transfer is shipping aw.css minified.** The comment-stripped rules gzip to about 26 KB, against about 43 KB brotli for today's file. That needs a minify step in the deploy, which is not in this plan (no build step today).

### Files after phase 2

| file | bytes | loads |
|---|---|---|
| AW tokens | 374 | every page (Animal Writes tokens.css) |
| Kits additions (`kit-` selectors) | 9,876 | every page (inside the Kits bundle; every Kits site) |
| aw.css | 15,259 | every page |
| aw-home.css | 26,411 | front page (template condition `is_front_page()`) |
| aw-type-ico.css | 19,849 | where an `aw-type-ico` element is printed (component flag in `aw_type_ico`, `hub-blocks.php:348`, and `directory-map.php:295`, so Directory pages too) |
| aw-single.css | 12,313 | single items: guide, news, library, listing (template condition `is_singular()`), and campaign pages that print the More / Sources / take-part blocks (component flag) |
| aw-forms.css | 9,629 | where a Fluent Forms form with `aw-form-ff` is printed (contact, volunteer, claim/report forms) |
| aw-lists.css | 8,248 | where `aw-news-list` / `aw-hub-list` / `aw-hub-group` / `aw-tasks` is printed |
| aw-directory.css | 5,846 | Directory templates: state, district and tag pages (template condition) and the Directory search block (component flag) |
| aw-listing.css | 4,526 | `single-aw_listing` and the claim pages (template condition) |
| aw-hubs.css | 3,214 | hub templates (`page-hub.html`, archives) and where `aw-toolbar` / `aw-hub-faq` is printed |
| aw-volunteer.css | 2,060 | `page-volunteer.html` and where `aw-vol-banner` is printed |
| aw-meta.css | 1,698 | where `aw-item-meta`, `aw-card-time` or a place chip (`taxonomy-aw_place`) is printed |
| aw-search.css | 1,494 | search results (`is_search()`) |
| aw-contact.css | 1,316 | the Contact page (page condition `is_page( 'contact' )`) |
| pending (phase 0) | 39,020 | not loaded on any of the 23 pages: phase 0 decides (delete, or join a component file) |

`aw-type-ico.css` (19.8 KB, 40 inline SVG masks) is the largest file that loads on many pages (15 of 23). A later step could cut it further with one SVG sprite or separate icon files. That is not in this plan.

## Steps, one commit each, each gated by TOTAL 0

Gate for every commit: before/after capture of the 23 pages at 1280, 768 and 375, compare = **TOTAL 0** (analytics blocked, reduced motion emulated). The re-run capture shows the moved group gone from aw.css. The post editor and Site Editor look the same. Theme rules, breakpoint and theme-copies checks are green. `component-map.md` is regenerated where a class or file list changes. The five campaign pages are in the 23 and must give 0 too.

| # | commit | what moves | to | bytes |
|---|---|---|---|---|
| 1 | Brand tokens and Kits token hooks | `:root` values, body background, button and section-description sizes, card limits | AW tokens.json; Kits rules read new tokens | ≈ 2,500 |
| 2 | Kits newsletter band (a) | band layout, card, heading line (art stays AW) | `kit-nl-band` variant of the Kits newsletter pattern + markup | ≈ 3,000 |
| 3 | Kits source line, date, card time (a) | `aw-src*`, `aw-date`, `aw-card-time`, list-title sizes | `kit-src`, `kit-date`, `kit-meta-item` time variant, tokens + markup | ≈ 4,400 |
| 4 | Component loader + `aw-home.css` | loader in Kits, AW registry, Home sections | new files | 26,411 |
| 5 | `aw-type-ico.css` | 41 type-icon rules | component flag | 19,849 |
| 6 | `aw-single.css` | More cards, Sources, item share, take-part, find-help line, PDF | template + component flag | 12,313 |
| 7 | `aw-forms.css` | Fluent Forms skin (not the newsletter) | component flag | 9,629 |
| 8 | `aw-lists.css` | news list, hub list, hub group, tasks | component flag | 8,248 |
| 9 | `aw-directory.css` | Directory search, filter, more, helplines, district | template condition | 5,846 |
| 10 | `aw-listing.css` | facts, listing head/help/actions, data sources, claim link | template condition | 4,526 |
| 11 | `aw-hubs.css` | toolbar, sort, hub FAQ, system wrapper, cases | template + component flag | 3,214 |
| 12 | `aw-volunteer.css`, `aw-contact.css`, `aw-search.css`, `aw-meta.css` | page-only and meta groups | template / component flag | 6,568 |
| — | campaign rules | nothing moves | stay in aw.css | see step "Campaign" |

Commit 4 carries the PHP (loader, registry, editor hooks) and is the bigger technical change. Commits 5–12 then only move CSS and add one registry line each. Commits 2 and 3 change markup and need the wp-plugin-audit and PHPCS gate on the touched PHP.

### 1. Brand tokens and Kits token hooks

| idx | rule | (a)/(b) | destination | Kits overlap and fix |
|---|---|---|---|---|
| 1062 | `:root` card limits (`--kit-card-min: 18rem`, media max, thumb min/max, body min) | token | AW tokens.json | kit-tokens.css:133 has 17rem; AW tokens load later, so the override holds (values: card-sizes.md) |
| 1053 | `:root` below 768: `--kit-field-text-size: 1rem` | token | AW tokens.json `{sm: 1rem, md: .875rem}` | Kits default .875rem (kit-tokens.css:178) |
| 1057 | `:root` ≥768: `--kit-type-sm`, `--kit-type-xs`, `--kit-meta-size: .8125rem` | token | AW tokens.json, **md and lg** | Kits sets 14px meta size at ≥1200 (kit-tokens.css:230). The aw rule wins at 1280 today, so lg must be .8125rem too, or 1280 changes |
| 116 | `body { background: var(--kit-color-canvas) }` | token | AW tokens.json `surface-page → {color.canvas}` | ties with kit.css:1019 `body { background: var(--kit-surface-page) }`; the token removes the tie |
| 1054 | button font size .875rem | (a) token | kit-primitives.css:266 reads `--kit-btn-font-size` (Kits default: today's value); AW sets .875rem | same selector list; `.kit-btn--sm` / `--icon` still win on specificity |
| 1056 | `.kit-section-desc` font size .875rem | (a) token | kit.css:251 reads `--kit-section-desc-size` (default `var(--kit-type-lead)`); AW sets .875rem | same selector |
| 1059, 1063–1068 | stacked/block card limits (`!important`) | (a) tokens only | kit.css §4.2 Card reads new tokens `--kit-card-stacked-media` and the narrow-screen gap, Kits defaults = today's Kits values (40%, kit.css:1209). AW sets its 30% / clamp (aw.css:1509, :1521, :1524) in tokens.json. Writing the AW values into Kits would change every Kits site | same selectors with `!important`; 1059 is overridden by 1064 (merged in phase 1). The 24px padding stays (card-sizes.md:13) |
| 783 | `.kit-footer p.kit-footer-head` | (a) | kit.css §5 footer, by line 770 | higher specificity than `p, li`: order-safe |
| 308 | `.kit-sidebar` grid | (a) | kit.css by `.kit-with-sidebar` | Kits' 1199px rule still wins on specificity |
| 451–452 | `.kit-pager > a` | (a) | kit.css:598–600 | no tie. Add a hover state: kit.css:1004 covers only `.prev`/`.next` |
| 487–489, 491 | `.kit-section-band` child margins, crumbs colour | (a) | kit.css §4.6 | 489 ties with `.kit-section-filters--below > .kit-section-count`: place it before that rule |
| 69, 353, 439 | post-template gap below 768, post-content margins, first band margin | (a) | kit.css §3 Layout, next to the rules they override | 69 ties with `.wp-block-post-template.is-layout-grid`: edit in place |
| 772–774, 1055 | h5 size, heading link colour/hover, field font size (`!important`) | (b) | stay in aw.css | Animal Writes' type choices on plain elements. As Kits tokens they would change Kits typography for every Kits site: a separate decision. The heading-link hover underline (aw.css:1053) gets a `:focus-visible` twin. 1055 is the iOS zoom fix; a later Kits field rule could read `--kit-field-text-size` |

Load-bearing on all 23 pages (768 and 1280 everywhere; 375 on contact, lab, library, news, volunteer). Risk: medium (every page); the traps are the 1200 meta size and five same-selector ties.

### 2. Newsletter band: (a) for the band, (b) for its form

| group | (a)/(b) | reason |
|---|---|---|
| `aw-nl-band` layout rules (603–612, 619–621, 644–646: band, card, text, icon slot, head, line) | **(a) `kit-nl-band`** | a newsletter band is a generic site part, and Kits already has a newsletter pattern (`patterns/newsletter.php`). The band is light (aw.css:818), so the dark-band form variant (kit-primitives.css:471) is not its precedent |
| `aw-nl-art` (613–618) and the art parts of the band rules | **(b)** stay in aw.css | the envelope SVG and its red `--nl-mark` (danger 82% mixed with ink, aw.css:821, 831–836) are brand illustration. Kits gives an empty art slot only |
| `aw-nl-band` rules that style the Fluent form inside it (622–643 with `.aw-newsletter-form`/`.ff-*`) and `aw-newsletter-form` (584–601) | **(b)** stay in aw.css | they style Fluent Forms' own markup (`.ff-el-*`, `.ff-btn-submit`). Kits must not depend on one forms plugin; the newsletter is on every page, so aw.css is the right place |

- Markup in the same commit: the band becomes a variant of the existing Kits `kits-by-wugweb/newsletter` pattern (no new pattern), with the `kit-nl-band*` classes, and `theme/parts/newsletter.html` (plugin) adopts them.
- The band heading is a `<p class="aw-nl-band-head">` today, so it is not in the heading outline. The variant uses an `h2` with the same look (heading hierarchy).
- Values still raw in the band (colour-mix 6–35%, strokes 1 / 1.5, `--kit-ico-size: 1.5rem` aw.css:837, `max-width: 46ch` :839, `calc(var(--kit-space-9) * 1.5)` :828) stay as they are and are listed under "Raw sizes". The `:has(> .aw-nl-band) + *` rule (:817) gets a `kit-nl-band` form.
- The part **keeps `aw-nl-band` as a second class**, because `campaign.js` (line 714) and `campaign-lab.js` (line 765) select it and both are frozen. The Animal Writes rules for the form inside keep using it.
- Tokens: Kits defaults are today's values (band padding `--kit-space-7`, card `--kit-space-5`/`--kit-space-6`, card width `--kit-width-content`), so Animal Writes sets nothing new. Padding stays ≥ 24px.
- Kits overlaps: the band's button rules beat `.kit-btn` on specificity (0,4,0 against 0,1,0). The two equal-specificity ties are with `.kit-btn--icon.kit-btn--sm` and the filter-sheet button, which the newsletter button never is.
- The 3 success-message rules (`ff-message-success`) stay. Fluent adds the class only after a submit, so check that state once by hand on a local copy, never on live.
- Load-bearing on all 23 pages at all widths. Risk: medium (every page, markup change).

### 3. Source line, date and list titles: (a) for three, (b) for the rest

| group | (a)/(b) | reason and markup |
|---|---|---|
| `aw-src`, `aw-src-icon`, `aw-src-name` (204–212, 349–352, 734–737, 754–764) | **(a) `kit-src`** | Kits Core already prints this markup (`plugins/kits-core/includes/attribution.php` lines 58–65), so the component is Kits'. Markup: attribution.php, `hub-blocks.php` and `also-reported.php` print `kit-src kit-src--link` etc. They keep `aw-src` as a second class while Animal Writes rules (for example 457, 464 in `aw-lists.css`) still use it. Rule 735 also lists `.aw-datasrc-logo` (listing data sources): that part stays in `aw-listing.css`. kit.css:1181 `.kit-card-chips .aw-src-icon` becomes `.kit-src-icon`. The base rule sets ink colour and strong weight (aw.css:297) and aw.css:1016 resets them with `!important`: the Kits base reads the meta tokens instead, and 1016 loses its `aw-src` and `aw-card-time` parts. The hover underline (aw.css:302) gets a `:focus-visible` twin; the 44px link target (:301) stays. The `--also` variant and `aw-src-also-link` stay Animal Writes (component-map.md:83) |
| `aw-date` (885, 891) | **(a) `kit-date`** | a no-wrap date with its icon, part of the Kits meta row. Markup in 8 files that print `aw-date` (campaign-tools.php, hub-blocks.php, team-dates.php, campaign-record.php, admin-home.php, systems.php, block-states.php, pages.php) plus the Kits card pattern. `aw-date` stays as a second class (campaign rules such as `.aw-social-card .aw-date` use it). two existing `aw-date` selectors in Kits (kit.css:1177 and kit-primitives.css:50) become `kit-date`. The icon gap reads `--kit-meta-icon-gap` / `--kit-meta-icon` (kit-tokens.css:123–124) instead of `margin-right: var(--kit-icon-gap)` (aw.css:1234) |
| `aw-story-title` 770: the Kits parts (`.kit-ed-lead .wp-block-post-title`, `.kit-ed-list …`, `main h4 …`) | **(a) tokens only** | Kits' list-title rules read `--kit-list-title-size`, `-weight` and `-leading`, with Kits defaults = today's Kits values. Animal Writes sets its values in tokens.json. No markup change |
| `aw-story-title` 143–145, 775 and `aw-org-name` | **(b)** stay in aw.css | Animal Writes list rows (`hubs.php`) |
| `aw-card-time` (347, 351) | **(a)** `kit-meta-item` time variant | Kits Core prints it (`plugins/kits-core/includes/attribution.php:120`), the same reason as `kit-src`. component-map.md:33 lists "time (clock)" in the Kits meta line, and `hub-blocks.php:244` already adds `kit-meta-item`. `min-height: 36px` (aw.css:457) has no doc line or token, so it stays in aw.css as is until it has a source. Alias `aw-card-time` kept |
| `aw-item-meta` container (220) | **(a) markup only** | it repeats `kit-meta-row` (aw.css:317 against kit.css:984): `item-head.php:197` adds `kit-meta-row` and the repeated declarations go |
| `aw-item-meta` children (deadline, verified on) | **(b)** `aw-meta.css` | Animal Writes item head (`item-head.php`) |
| `aw-type-ico` (838–878) | **(b)** `aw-type-ico.css` | one inline SVG per Directory type (gaushala, veterinary dispensary …): domain content |

- Kits overlap to watch: 770 against `.kit-ed-lead`, `.kit-ed-list` and `.kit-rows` titles, all `!important`. With tokens the rules stay where they are, so nothing flips. On `.kit-rows` titles (kit.css:1294) Kits wins on specificity today and still does.
- Risk: medium (markup in 12 PHP files plus the Kits card markup; the second classes keep old selectors working).

**`aw-` selectors already in Kits.** Kits holds about ten today. Each gets an owner step, so phase 2 ends with none:

| Kits line | selector | step |
|---|---|---|
| kit.css:1177, kit-primitives.css:50 | `aw-date` | 3 (`kit-date`) |
| kit.css:1181 | `aw-src-icon` | 3 (`kit-src-icon`) |
| kit.css:1165–1166 | `aw-story-logo` | 3 (to aw.css with `aw-story-title`) |
| kit.css:1182, :1190 | `aw-sponsored-label` | 8 (to `aw-lists.css`) |
| kit.css:1199 | `aw-opp-actions` | campaign rule: stays put; moved to aw.css beside the other campaign rules only with the owner's word |
| kit.css:1225–1227 | `aw-news-list` | 8 (to `aw-lists.css`) |
| kit.css:740 | comment naming `.aw-nl-band` | 2 (reworded) |

### 4–12. Animal Writes component files

Each table lists the load-bearing groups in that file.

#### aw-home.css (front page only)

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-bento-tile | 1186–1198, 1204–1210, 1214, 1220–1221 | 4,562 | home | all 3 | — |
| aw-ed-news | 1014–1028, 1169–1172 | 2,334 | home | all 3 | — |
| aw-ed-law | 1241–1261 | 2,042 | home | all 3 | — |
| aw-ed-search | 117–121 | 1,542 | home | all 3 | — |
| aw-ed-learn | 985–999 | 1,487 | home | all 3 | — |
| aw-bento | 1175–1185, 1211–1213, 1215, 1217–1218 | 1,435 | home | all 3 | — |
| aw-ed-story | 1262–1270 | 1,357 | home | home 768 | — |
| aw-ed-hero | 1077, 1080–1081, 1084, 1086–1087, 1090, 1092–1093, 1095 | 1,016 | home | all 3 | — |
| aw-news-mosaic | 1001–1002, 1004–1011, 1013 | 965 | home | home 768/1280 | — |
| aw-findhelp-form | 1225–1226, 1231–1233 | 811 | home | all 3 | — |
| aw-content | 1096–1097, 1100 | 785 | home | all 3 | — |
| aw-bento-cta | 1216, 1219 | 615 | home | all 3 | — |
| aw-bento-ico | 1200–1202 | 539 | home | all 3 | — |
| aw-content-actions | 1105–1106, 1108–1109 | 308 | home | all 3 | — |
| aw-pair-grid | 1128–1131 | 237 | home | all 3 | — |
| kit-sec-head | 1166–1168 | 203 | home | all 3 | — |
| aw-findhelp-filters | 1234, 1238 | 196 | home | all 3 | — |
| aw-findhelp-label | 1235, 1239 | 193 | home | all 3 | — |
| aw-ed-find | 1222–1223 | 168 | home | all 3 | — |
| aw-findhelp-actions | 1237, 1240 | 155 | home | all 3 | — |
| aw-findhelp-types | 1236 | 124 | home | all 3 | — |
| aw-content-bg | 1098 | 109 | home | all 3 | — |
| aw-ed-help | 1173–1174 | 96 | home | all 3 | — |
| aw-findhelp-bar | 1224 | 84 | home | all 3 | — |
| aw-bento-head | 1203 | 79 | home | all 3 | — |
| aw-pair | 1127 | 37 | home | all 3 | — |

Also in this file: 13 groups (4,932 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

- Kits overlaps: 166 ties on Kits classes (`.kit-card`, `.kit-sec-head`, `.kit-tile`, `.kit-stack`, `.kit-btn`). 31 of them carry aw `!important`, 40 carry Kits `!important`. They resolve as today because the file loads where aw.css loads.
- `aw-ed-mosaic` is load-bearing only on the Community dogs page, so it stays with the campaign rules.
- CI baseline lines to move with these rules: `.aw-content-body | padding-top: 40%`, `.home :is(.aw-ed-law > .kit-stack, …) | padding`, `.aw-ed-learn .aw-tasks.is-three .kit-card > .kit-card-body | padding: 0`.
- The first draft said about 6.6 KB of these rules also matched elements on every other page. That was correction 2 (the capture's comma bug), so they all go to `aw-home.css`.

#### aw-type-ico.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-type-ico | 838–878 | 19,849 | category, cruelty, diwali, dogs, guide, home, lab, libitem, library, listing, resources, sc, search, tag | all 3 | diwali, dogs, lab, sc |

#### aw-single.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-take-part | 1132–1159, 1162–1165 | 5,682 | cruelty, diwali, dogs, guide, home, lab, libitem, listing, newsitem, sc, volunteer | mixed (most at all 3) | diwali, dogs, lab, sc |
| aw-side-camp | 309–317 | 981 | cruelty, guide, libitem, listing, newsitem, volunteer | mixed (most at all 3) | — |
| aw-more-card | 251–255, 305–306 | 961 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-browse-states | 102–107 | 704 | cruelty, diwali, lab, sc | all 3 | diwali, lab, sc |
| aw-findhelp-line | 113–115 | 545 | guide, libitem, newsitem | all 3 | — |
| aw-more-list | 248–250, 339–340 | 422 | cruelty, guide, lab, libitem, listing, newsitem | all 3 | lab |
| aw-pdf | 32–33, 39 | 301 | libitem | all 3 | — |
| aw-guide-disclaimer | 892 | 216 | guide, sc | all 3 | sc |
| aw-more-line | 260 | 204 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-sources-refs | 240–242 | 189 | cruelty, guide | all 3 | — |
| aw-sources-credit | 233–234 | 188 | cruelty, guide, libitem, newsitem | all 3 | — |
| aw-sources-note | 237 | 187 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-more-title | 258–259 | 153 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-findhelp-group | 108, 110 | 151 | cruelty | all 3 | — |
| aw-sources-by | 235 | 140 | cruelty, guide | all 3 | — |
| aw-item-content | 229–230 | 135 | libitem | all 3 | — |
| aw-pdf-actions | 34 | 129 | libitem | all 3 | — |
| aw-findhelp-lede | 111 | 115 | cruelty, diwali, lab, sc | all 3 | diwali, lab, sc |
| aw-item-share | 244–245 | 114 | category, cruelty, guide, libitem, listing, newsitem, tag | all 3 | — |
| aw-sources-read | 238–239 | 112 | libitem, newsitem | all 3 | — |
| aw-sources | 231 | 108 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-sources-head | 232 | 91 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-more-head | 247 | 88 | category, cruelty, guide, libitem, listing, newsitem, tag | all 3 | — |
| aw-more | 246 | 84 | cruelty, dogs, guide, libitem, listing, newsitem | all 3 | dogs |
| aw-findhelp | 112 | 58 | cruelty, diwali, lab, sc | all 3 | diwali, lab, sc |
| aw-sources-updated | 304 | 55 | listing | all 3 | — |
| aw-sources-list | 303 | 52 | listing | all 3 | — |
| aw-more-meta | 257 | 49 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| aw-more-body | 256 | 46 | cruelty, guide, libitem, listing, newsitem | all 3 | — |

Also in this file: 1 groups (53 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

- `aw-take-part`, `aw-browse-states`, `aw-findhelp` and `aw-findhelp-lede` also act on the four campaign pages. Their component flag loads the file there; the gate on those pages is the check.
- `aw-side-camp` acts only at 1280 (the sidebar shows from 1200). Baseline lines to move: `.aw-item-share .kit-share | padding-top: 0` and the two `.aw-pdf …` heights.

#### aw-forms.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-form-ff | 647–652, 654–680, 686–710, 712–718 | 9,629 | contact, volunteer | all 3 | — |

Rule 653 (`.aw-form-ff, .aw-newsletter-form`) stays in aw.css, because the newsletter needs it on every page.

#### aw-lists.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-news-list | 453–455, 461–479, 882–884, 984, 1000, 1003, 1012, 1069 | 3,786 | home, news | all 3 | — |
| aw-hub-list | 440–447, 449, 456–460, 481–483, 485, 492–497 | 2,262 | campaigns, dogs, home, library, news, resources | all 3 | dogs |
| aw-hub-group | 381–383, 748–749, 769 | 798 | category, cruelty, diwali, dogs, guide, lab, libitem, listing, newsitem, resources, sc, tag, volunteer | volunteer 1280 | diwali, dogs, lab, sc |
| aw-tasks | 164–166, 983 | 304 | resources | all 3 | — |

Also in this file: 3 groups (1,098 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

#### aw-directory.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-dir-search | 71–73, 75–76, 79, 81–82, 725, 813, 1042–1047, 1050–1052, 1058 | 1,849 | district, state, tag | all 3 | — |
| aw-dir-filter | 1034–1039, 1048 | 901 | district, state, tag | all 3 | — |
| aw-helpline | 22–25, 27, 65 | 738 | district | all 3 | — |
| aw-dir-more | 530–537, 812, 1033, 1041 | 623 | district, state, tag | all 3 | — |
| aw-dir-q | 80, 83, 1040, 1049 | 327 | district, state, tag | district 375; state 375; tag 375 | — |
| aw-helpline-num | 26 | 300 | district | all 3 | — |
| aw-district-helplines | 29–31 | 193 | district | all 3 | — |
| aw-helplines | 21 | 179 | district | all 3 | — |
| aw-emergency | 16 | 120 | district | all 3 | — |
| aw-helpline-src | 28 | 82 | district | all 3 | — |

Also in this file: 4 groups (534 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

`aw-dir-q` acts only at 375. Baseline line to move: `.aw-org | padding: var(--kit-space-5) 0`.

#### aw-listing.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-listing-help | 508–511, 516–518, 583 | 764 | listing | all 3 | — |
| aw-facts | 273, 276–277, 281, 284, 288–292 | 757 | listing | all 3 | — |
| aw-item-place | 223–227 | 526 | listing | all 3 | — |
| aw-datasrcs | 413–415, 417, 738 | 506 | listing | all 3 | — |
| aw-claim-link | 726, 729 | 380 | listing | all 3 | — |
| aw-listing-about | 263, 265–266 | 293 | listing | all 3 | — |
| aw-facts-row | 274–275 | 164 | listing | all 3 | — |
| aw-more-initials | 307 | 120 | listing | all 3 | — |
| aw-listing-desc | 269–270 | 106 | listing | all 3 | — |
| aw-facts-note | 287 | 96 | listing | all 3 | — |
| aw-listing-head | 272 | 91 | listing | all 3 | — |
| aw-facts-suggest | 286 | 85 | listing | all 3 | — |
| aw-datasrc-mark | 419 | 83 | listing | all 3 | — |
| aw-listing-actions | 293 | 80 | listing | all 3 | — |
| aw-listing-group | 271 | 53 | listing | all 3 | — |
| aw-facts-na | 285 | 47 | listing | all 3 | — |
| aw-claim-find | 784 | 36 | claim | all 3 | — |

Also in this file: 3 groups (339 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

Claimed, verified and sponsored listings, claim results and the report form are phase 0 pages. They are not measured yet.

#### aw-hubs.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-toolbar | 85, 87–95, 98–99 | 1,005 | campaigns, category, library, news, resources | all 3 | — |
| aw-camp-next | 202–203 | 293 | campaigns | all 3 | — |
| aw-system | 74, 133, 450 | 265 | campaigns, library, news, resources, state | all 3 | — |
| aw-hub-news | 750–753 | 261 | category | all 3 | — |
| aw-hub-library | 880–881 | 243 | library | library 375/1280 | — |
| aw-cases | 134, 1029 | 202 | library | library 768/1280 | — |
| aw-hub | 380 | 174 | category | all 3 | — |
| aw-toolbar-count | 86, 97 | 156 | campaigns, category, library, news, resources | all 3 | — |
| aw-hub-faq | 336–337 | 117 | campaigns, claim, contact, cruelty, dogs, guide, libitem, library, news, resources, sc, volunteer | all 3 | dogs, sc |
| aw-term-end | 334–335 | 104 | category, tag | all 3 | — |
| aw-also | 167 | 81 | resources | all 3 | — |
| aw-term-chips | 338 | 63 | category, tag | all 3 | — |
| aw-dd-sort | 96 | 30 | campaigns, category, library, news, resources | mixed (most at all 3) | — |

Also in this file: 4 groups (220 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

#### aw-volunteer.css, aw-contact.css, aw-search.css, aw-meta.css

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-vol-banner | 318–321, 498–507 | 1,101 | campaigns, contact | all 3 | — |
| aw-volunteer-form | 682–684, 711, 728 | 562 | volunteer | all 3 | — |
| aw-vol-side | 322–323 | 215 | volunteer | volunteer 1280 | — |
| aw-vol-role | 794 | 125 | volunteer | all 3 | — |
| aw-vol-roles | 793 | 57 | volunteer | all 3 | — |

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-contact-row | 424–426, 430 | 323 | contact | all 3 | — |
| aw-elsewhere | 431–432 | 264 | contact | all 3 | — |
| aw-contact-pick | 427–429 | 261 | contact | all 3 | — |
| aw-contact | 421–422, 433 | 241 | contact | all 3 | — |
| aw-contact-form | 436 | 94 | contact | contact 768/1280 | — |
| aw-contact-org | 423, 434 | 93 | contact | all 3 | — |

Also in this file: 1 groups (40 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-search-results | 388–389, 393–394, 410 | 623 | category, search | all 3 | — |
| aw-search-group | 408–409 | 276 | search | all 3 | — |
| aw-search-title | 390–392 | 223 | search | all 3 | — |
| aw-search-form | 385–386 | 160 | search | all 3 | — |
| aw-search-count | 384, 387 | 131 | search | all 3 | — |
| aw-search-more | 411 | 81 | search | all 3 | — |

| group | rule indexes | bytes | load-bearing on | widths | campaign pages |
|---|---|---|---|---|---|
| aw-item-meta | 220–222, 228, 264 | 681 | cruelty, guide, libitem, listing, newsitem | all 3 | — |
| (element/root) | 731–733 | 384 | all 23 | mixed (most at all 3) | diwali, dogs, lab, sc. **Stays in aw.css**: it acts on all 23 pages, so a flag could miss a page |
| aw-card-time | 347, 351 | 210 | category | all 3 | — |

Also in this file: 1 groups (423 B) with no proven effect on the 23 pages (unmatched, or an effect only at the noisy spots).

Baseline lines to move: the volunteer banner photo heights (3 lines), `.aw-vol-page .aw-vol-banner-body | padding: 0`, `.aw-contact-row h3 | font-size`. The About groups (`aw-about-*`, 2.6 KB) showed only noise; /about/ is a phase 0 page.

### Campaign rules: stay in aw.css (owner's decision)

`campaign.css`, `campaign-lab.css` and `campaign-home.css` are frozen, so nothing moves. What stays in aw.css:
- campaign pages only: aw-camp-end, aw-festive-tips, aw-festive-ico, aw-opp-actions, aw-social-card, aw-camp-docs (1.5 KB);
- Community dogs: aw-ed-mosaic (1.3 KB);
- the campaign bands on Home: aw-camp-band, aw-sc*, aw-festive, aw-camp (≈ 5 KB);
- the countdown: aw-countdown, aw-cd-* (0.5 KB).

About 8.4 KB of aw.css is campaign CSS that every page loads. **For the owner:** loading those rules only on Home and the campaign pages would take 8.4 KB off the other 18 page types. But it means moving them out of aw.css, which needs the owner's word. Until then aw.css cannot be deleted in phase 3; it shrinks to 15,259 B.

## Checks

- **theme_copies.py.** New files under `plugins/animal-writes-core/theme/assets/css/` have no child copy. The check already passes a plugin-only file (it compares only files that exist on both sides). Two changes:
  - a list `PLUGIN_ONLY` of the new files, so the check **fails** if a child copy of one appears;
  - a docstring line saying the plugin is the source for them.
  The other checks (`theme_rules_check.py`, `breakpoint_check.py`) already read a plugin file that has no child twin, so the new files are checked. aw.css and tokens.css keep their two copies until the child theme is removed, as the check requires today.
- **theme_rules_baseline.txt.** 14 lines name `themes/animal-writes/assets/css/aw.css`. Each moves to its rule's new file in the same commit. The check says a new exception needs the owner's word; a moved line is the same exception, and the commit says so.
- **settings_parts_baseline.txt:49** (campaign hero min-height) stays: that rule stays in aw.css.
- **Child theme.** With no child enqueue, a site running the child theme would not get the new files. That is fine once the child is retired, but until then the site must run Kits with the plugin's theme parts (as it does now).
- **smoke.sh:91** keeps checking the `aw-components-css` link, because aw.css stays and is never inlined. Per-page asserts are added in the same script: `aw-home-css` present on `/` and absent on a state page, `aw-listing-css` on a listing.
- **component_map.py** needs no code change, only regeneration; its "Used in" lists name the new files.
- **States checked per (a) component** (design-system and UI/UX review): hover and a visible `:focus-visible` for every link or control, targets of 24px or more (WCAG 2.2) and 44px where design-system.md:289 / :353 ask for it, heading order, reduced motion. Colour-not-only and motion had no problems in these groups; `aw-take-part` already has a reduced-motion block (aw.css:1671).

### Later Kits candidates (not in this phase)

These Animal Writes groups are close to generic Kits parts. Each is a separate decision:
- `aw-toolbar` (with `-count` and `aw-dd-sort`) wraps `kit-filterbar` (aw.css:126–142), and the promotion plan calls the filter bar generic.
- `aw-take-part`: the "three big actions" pattern (kits-promotion-plan.md:69).
- `aw-more-card` repeats `kit-card`.
- `aw-item-share` only spaces `kit-share`.
- `aw-tasks`: `kit-task` already exists (kit.css:604).
- `aw-pdf` styles the core file block.

## Raw sizes: candidate tokens (unchanged in this phase)

These values in load-bearing groups are literal sizes with no line in design-system.md or card-sizes.md (card-sizes.md covers the card limits in rule 1062). They move unchanged so the gate stays at 0. Each is a candidate token for the owner to name later.

| idx | goes to | selector (as it is today) | media | property | value |
|---|---|---|---|---|---|
| 21 | aw-directory.css | `.aw-helplines` |  | grid-template-columns | `repeat(auto-fill, minmax(min(17rem, 100%), 1fr))` |
| 33 | aw-single.css | `.aw-pdf .wp-block-file__embed` |  | height | `min(80vh, 900px)` |
| 39 | aw-single.css | `.aw-pdf .wp-block-file__embed` | (max-width: 767px) | height | `60vh` |
| 82 | aw-directory.css | `.aw-dir-search` | (min-width: 1200px) | grid-template-columns | `minmax(9rem, 2fr) repeat(3, minmax(11.5rem, 1fr)) auto` |
| 121 | aw-home.css | `.aw-ed-search select` |  | padding | `0 calc(var(--kit-space-3) + 1.25rem) 0 var(--kit-space-3)` |
| 122 | aw.css | `.aw-ed-mosaic` |  | grid-auto-rows | `9rem` |
| 126 | aw.css | `.aw-ed-mosaic` | (min-width: 1200px) | grid-auto-rows | `8.5rem` |
| 203 | aw-hubs.css | `.aw-camp-next p` |  | max-width | `52ch` |
| 265 | aw-listing.css | `.aw-listing-about.has-logo` |  | grid-template-columns | `4rem minmax(0, 1fr)` |
| 266 | aw-listing.css | `.aw-listing-about.has-logo` | (min-width: 768px) | grid-template-columns | `6rem minmax(0, 1fr)` |
| 275 | aw-listing.css | `.aw-facts-row` | (min-width: 768px) | grid-template-columns | `12rem minmax(0, 1fr)` |
| 306 | aw-single.css | `.aw-more-card .aw-more-logo img` |  | max-width | `60%` |
| 306 | aw-single.css | `.aw-more-card .aw-more-logo img` |  | max-height | `70%` |
| 319 | aw-volunteer.css | `.kit-sidebar .aw-vol-banner-photo img` |  | height | `140px` |
| 347 | aw-meta.css | `.aw-card-time` |  | min-height | `36px` |
| 380 | aw-hubs.css | `.aw-hub` |  | grid-template-columns | `repeat(auto-fill, minmax(min(100%, 20rem), 1fr))` |
| 382 | aw-lists.css | `.aw-hub-group ul` |  | padding-inline-start | `1.1em` |
| 410 | aw-search.css | `.aw-search-results mark` |  | padding | `0 .1em` |
| 417 | aw-listing.css | `.aw-datasrcs.is-full .kit-tag` |  | min-width | `3.5rem` |
| 423 | aw-contact.css | `.aw-contact-org, .aw-contact-report` |  | max-width | `70ch` |
| 500 | aw-volunteer.css | `.aw-vol-banner-photo img` |  | height | `120px` |
| 505 | aw-volunteer.css | `.aw-vol-banner-photo img` | (min-width: 768px) | min-height | `320px` |
| 621 | Kits (`kit-` class) | `.aw-nl-band p.aw-nl-band-line` |  | max-width | `46ch` |
| 661 | aw-forms.css | `.aw-form-ff .ff-el-group.kit-field .ff-el-input--content tex` |  | min-height | `calc(5 * 1.5em + 2 * var(--s-3))` |
| 665 | aw-forms.css | `.aw-form-ff .kit-field-control.aw-ff-prefixed > .aw-ff-prefi` |  | left | `calc(var(--s-3) + 1.25rem + var(--s-2))` |
| 665 | aw-forms.css | `.aw-form-ff .kit-field-control.aw-ff-prefixed > .aw-ff-prefi` |  | top | `50%` |
| 666 | aw-forms.css | `.aw-form-ff .kit-field .kit-field-control.aw-ff-prefixed:has` |  | padding-left | `calc(var(--s-3) + 1.25rem + var(--s-2) + var(--aw-ff-prefix, 4ch))` |
| 673 | aw-forms.css | `.aw-form-ff input[type=file]::file-selector-button` |  | min-height | `2rem` |
| 691 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox` |  | min-height | `7.5rem` |
| 692 | aw-forms.css | `.aw-form-ff .aw-ff-opt-ico` |  | width | `1.125rem` |
| 692 | aw-forms.css | `.aw-form-ff .aw-ff-opt-ico` |  | height | `1.125rem` |
| 693 | aw-forms.css | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` |  | width | `1.75rem` |
| 693 | aw-forms.css | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` |  | height | `1.75rem` |
| 694 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox input, .aw-form-ff .ki` |  | margin | `-1px` |
| 695 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox::after` |  | width | `1.25rem` |
| 695 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox::after` |  | height | `1.25rem` |
| 698 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` |  | top | `calc(var(--kit-space-3) + 0.1875rem)` |
| 698 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` |  | right | `calc(var(--kit-space-3) + 0.1875rem)` |
| 698 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` |  | width | `0.875rem` |
| 698 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` |  | height | `0.875rem` |
| 700 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox` | (max-width: 767px) | min-height | `3.5rem` |
| 700 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox` | (max-width: 767px) | padding | `var(--kit-space-2) calc(var(--kit-space-3) * 2 + 1.25rem) var(--kit-sp` |
| 701 | aw-forms.css | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` | (max-width: 767px) | width | `1.5rem` |
| 701 | aw-forms.css | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` | (max-width: 767px) | height | `1.5rem` |
| 702 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox::after` | (max-width: 767px) | top | `50%` |
| 703 | aw-forms.css | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` | (max-width: 767px) | top | `50%` |
| 716 | aw-forms.css | `.aw-form-ff .kit-chips .kit-checkbox:has(input:checked)` |  | padding-inline | `calc(var(--kit-space-4) - 2px + var(--kit-border-control))` |
| 729 | aw-listing.css | `#claim > summary::before, #report > summary::before, #volunt` |  | width | `1.125em` |
| 729 | aw-listing.css | `#claim > summary::before, #report > summary::before, #volunt` |  | height | `1.125em` |
| 735 | Kits (`kit-` class) | `.aw-src-icon:not(.aw-src-icon--letter), .aw-datasrc-logo` |  | padding | `3px !important` |
| 755 | Kits (`kit-` class) | `.aw-src.aw-src` |  | min-height | `1.75rem` |
| 781 | aw.css | `.aw-ed-mosaic` | (min-width: 768px) | grid-auto-rows | `10rem` |
| 784 | aw-listing.css | `.aw-claim-find` |  | max-width | `36rem` |
| 838 | aw-type-ico.css | `.aw-type-ico` |  | margin-right | `.35em` |
| 880 | aw-hubs.css | `.aw-hub-library .aw-cases` |  | grid-template-columns | `repeat(auto-fill, minmax(min(100%, 32rem), 1fr))` |
| 988 | aw-home.css | `.aw-ed-learn .aw-tasks.is-three .kit-card > .kit-card-media` | @container aw-guides (max-width: 911px) | max-width | `40%` |
| 1010 | aw-home.css | `.aw-news-mosaic > li:first-child .kit-card-media` | (min-width: 768px) | min-height | `8rem` |
| 1020 | aw-home.css | `.aw-ed-news .aw-news-mosaic .kit-card--stacked-md > .kit-car` | (min-width: 1200px) | width | `10rem !important` |
| 1024 | aw-home.css | `:is(.aw-ed-news, .aw-ed-law) .aw-news-mosaic > li:first-chil` | (max-width: 767px) | max-width | `40%` |
| 1034 | aw-directory.css | `.aw-dir-filter-toggle` |  | top | `50%` |
| 1034 | aw-directory.css | `.aw-dir-filter-toggle` |  | border-radius | `50%` |
| 1039 | aw-directory.css | `.aw-dir-filter-toggle.has-filters::after` |  | top | `9px` |
| 1039 | aw-directory.css | `.aw-dir-filter-toggle.has-filters::after` |  | right | `9px` |
| 1039 | aw-directory.css | `.aw-dir-filter-toggle.has-filters::after` |  | width | `8px` |
| 1039 | aw-directory.css | `.aw-dir-filter-toggle.has-filters::after` |  | height | `8px` |
| 1039 | aw-directory.css | `.aw-dir-filter-toggle.has-filters::after` |  | border-radius | `50%` |
| 1052 | aw-directory.css | `.aw-dir-search .aw-dir-more-body .aw-dir-check` | (min-width: 1200px) | font-size | `.8125rem` |
| 1054 | Kits (`kit-` class) | `.kit-btn, .wp-block-button__link, .fluentform .ff-btn.kit-bt` |  | font-size | `.875rem` |
| 1056 | Kits (`kit-` class) | `.kit-section-desc` |  | font-size | `.875rem` |
| 1057 | tokens | `:root` | (min-width: 768px) | --kit-type-sm | `clamp(.75rem, .7273rem + .0909vw, .8125rem)` |
| 1057 | tokens | `:root` | (min-width: 768px) | --kit-type-xs | `clamp(.6875rem, .6591rem + .1136vw, .75rem)` |
| 1057 | tokens | `:root` | (min-width: 768px) | --kit-meta-size | `.8125rem` |
| 1058 | aw-directory.css | `.aw-dir-search .kit-field > label` | (min-width: 768px) | font-size | `.8125rem` |
| 1059 | Kits (`kit-` class) | `.kit-card--stacked-lg > .kit-card-media` |  | width | `30% !important` |
| 1059 | Kits (`kit-` class) | `.kit-card--stacked-lg > .kit-card-media` |  | max-width | `30%` |
| 1062 | tokens | `:root` |  | --kit-card-min | `18rem` |
| 1062 | tokens | `:root` |  | --kit-card-media-max | `22.5rem` |
| 1062 | tokens | `:root` |  | --kit-thumb-min | `4.5rem` |
| 1062 | tokens | `:root` |  | --kit-thumb-max | `15rem` |
| 1062 | tokens | `:root` |  | --kit-body-min | `10rem` |
| 1064 | Kits (`kit-` class) | `.kit-card--stacked-lg > .kit-card-media` |  | width | `clamp(var(--kit-thumb-min), 30%, var(--kit-thumb-max)) !important` |
| 1067 | Kits (`kit-` class) | `.kit-card--stacked-lg` |  | gap | `clamp(var(--kit-space-3), 5vw, var(--kit-space-5)) !important` |
| 1075 | aw.css | `.aw-camp-band.aw-ed-hero--campaign h1.aw-camp-band-title` |  | max-width | `18ch` |
| 1085 | aw.css | `.aw-camp-band.aw-ed-hero--campaign.has-art` | (max-width: 767px) | padding-bottom | `calc(50vw + var(--kit-space-section-end))` |
| 1086 | aw-home.css | `.aw-ed-hero--campaign .aw-camp-band-art` | (max-width: 767px) | height | `50vw` |
| 1091 | aw.css | `.aw-camp-band.aw-ed-hero--campaign.has-art .aw-camp-band-tex` | (min-width: 768px) | max-width | `calc(55% - var(--kit-space-grid))` |
| 1092 | aw-home.css | `.aw-ed-hero--campaign .aw-camp-band-art` | (min-width: 768px) | inset | `50% 0 auto auto` |
| 1092 | aw-home.css | `.aw-ed-hero--campaign .aw-camp-band-art` | (min-width: 768px) | width | `45%` |
| 1094 | aw.css | `.aw-camp-band.aw-ed-hero--campaign.has-art .aw-camp-band-tex` | (min-width: 1200px) | max-width | `calc(50% - var(--kit-space-grid))` |
| 1095 | aw-home.css | `.aw-ed-hero--campaign .aw-camp-band-art` | (min-width: 1200px) | width | `50%` |
| 1115 | aw.css | `.aw-sc-status::before` |  | width | `.5em` |
| 1115 | aw.css | `.aw-sc-status::before` |  | height | `.5em` |
| 1115 | aw.css | `.aw-sc-status::before` |  | margin-right | `.45em` |
| 1115 | aw.css | `.aw-sc-status::before` |  | border-radius | `50%` |
| 1116 | aw.css | `.aw-sc h2 mark` |  | padding | `0 .2em` |
| 1116 | aw.css | `.aw-sc h2 mark` |  | margin | `0 -.2em` |
| 1117 | aw.css | `.aw-sc-line` |  | max-width | `40ch` |
| 1213 | aw-home.css | `.aw-bento--sos p:not(.aw-bento-eyebrow)` |  | max-width | `34ch` |
| 1233 | aw-home.css | `.aw-findhelp-form` | @container (min-width: 56rem) | grid-template-columns | `minmax(0, 1fr) minmax(12rem, 16rem) auto auto` |
| 1245 | aw-home.css | `.aw-ed-law > .kit-stack > .kit-tile::after` | (min-width: 1200px) | margin-top | `0.625rem` |
| 1261 | aw-home.css | `.aw-ed-law .aw-news-mosaic .kit-card--stacked-md > .kit-card` | (min-width: 1200px) | width | `10rem !important` |
| 1261 | aw-home.css | `.aw-ed-law .aw-news-mosaic .kit-card--stacked-md > .kit-card` | (min-width: 1200px) | max-width | `40%` |

## Summary

1. Nothing with an `aw-` selector goes into Kits, and the ten already there get an owner step. Four groups become Kits components with Kits markup: the newsletter band `kit-nl-band` (art stays AW), the source line `kit-src`, the date `kit-date` and the card time (a `kit-meta-item` variant). List-title and button/section sizes become Kits tokens. Everything else stays in Animal Writes CSS.
2. Animal Writes CSS splits into aw.css (newsletter form, heading overrides, campaign rules: 15,259 B) and 12 component files in the plugin only. Each file is loaded by a template condition or a component flag through a small Kits loader filter, as Uncode does per module. Files are enqueued at `wp_enqueue_scripts` 20 after aw.css, never from inside render.
3. Average aw.css-derived CSS per page drops from 161 KB to about 62 KB raw (−62%). On transfer it drops from 26.0 KB to 14.1 KB gzip (−46%), with 4–8 cached stylesheet requests instead of 1. Biggest cuts: Directory, claim and news (−60 to −65% gzip); smallest: Home (−24%).
4. There are 12 commits, each gated by TOTAL 0 on 23 pages × 3 widths. The gate first needs reduced motion in the capture, and the capture needs its comma bug fixed (94 rules looked matched everywhere).
5. Still for the owner: whether the 8.4 KB of campaign rules may load only on campaign pages, and names for 102 raw sizes listed as candidate tokens.
