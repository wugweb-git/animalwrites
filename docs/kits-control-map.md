# Kits control map

Every Kits part, and the WordPress control that will hold its look.

## 1. What this is

Today only the theme.json presets and the core block styles can be changed in **Appearance → Editor → Styles**. Everything else is fixed in CSS: `kit.css`, `kit-primitives.css` and `kit-tokens.css` in the Kits theme, `aw.css` in the Animal Writes child theme, and the campaign CSS in the Animal Writes plugin. So every change to a gap, a height or a colour is a code change.

This map takes each part in the two registries (`themes/kits-by-wugweb/kits-registry.json` and `themes/animal-writes/kits-registry.json`, 53 + 99 = 152 parts) and names the WordPress control that will hold it, the settings it gets, and the CSS it replaces.

Kits will later be one theme for many sites, and the Animal Writes child theme goes away. Parts marked **AW-specific** must then move out of Kits into the site plugin.

### Control order

No new screens, panels, menus, Customizer sections, React apps or admin CSS. Each part takes the **first** control that can hold it:

1. **Styles**: a core block style that already exists, or a token (theme.json preset or `settings.custom`). Edited in Site Editor → Styles.
2. **Variation**: a block style variation on a core block (`styles.blocks.<block>.variations.<slug>` in theme.json, plus `register_block_style`).
3. **Kits block**: a block with block supports in `block.json` (colour, spacing, typography, `dimensions.minHeight`, border, radius, shadow, background). For parts printed by PHP or that need their own wrapper. Existing `animal-writes/*` blocks get the same supports.
4. **Pattern / part**: a pattern, template or template part, edited in the Site Editor.
5. **Kits screen field**: a field on the existing Kits → Design system screen (`plugins/kits-core/includes/system-screen.php`), Settings API and core form-table markup only. Used only when nothing above can hold the part.
6. **No control**: behaviour, accessibility or a locked part. The reason is given.

### Uncode patterns we mirror

Uncode 2.8.11 (mapped from its documented features; the copy here has the theme only, not the option definitions) is a reference for patterns only. The "Uncode equivalent" column names the matching Uncode concept and the WordPress-core piece we use instead.

1. Per-site settings = Global Styles + a style variation + the existing Kits screen.
2. Settings become CSS variables in one place, never per-page CSS.
3. Palette slugs only; no hex values in blocks.
4. Page options fall back: page → post-type default → site default.
5. Each block's CSS sits in its block.json and loads only where the block is used.
6. One-off values go in block supports on the block.
7. Template parts and synced patterns hold the header, footer, 404 and CTA bands.
8. Global Styles and Kits settings can be exported and imported.

What we don't take: WPBakery shortcodes, a bundled options framework, any Uncode code/CSS/icons/images (ThemeForest licence).

### Legend

- **Slug**: the block, variation or pattern. Variation slugs are short kebab-case with the `kits-` prefix (like the existing `kits-card`) and no site names. `core/group` → `kits-section` means variation `kits-section` on core Group (class `is-style-kits-section`).
- **Settings**: what becomes editable: colour, spacing, gap, min-height, radius, border, shadow, type.
- **Replaces**: CSS rules whose subject is one of the part's classes, as `file:line ranges`. Files: `kit.css`, `kit-primitives.css`, `kit-tokens.css`, `kits.css`, `kits-v42.css` (Kits theme); `aw.css`, `tokens.css` (Animal Writes theme); `campaign*.css`, `dir-map.css` (Animal Writes plugin). The table shows the first four ranges per file; "Full line ranges" at the end lists every range. Each rule is counted once, under the part that owns the rightmost class in its selector.
- **Pilot (this change)**: the rows the pilot branch builds first.

## 2. Summary

| Control | Parts | CSS rules that move | `!important` in them |
|---|---|---|---|
| 1. Styles | 22 | 669 | 132 |
| 2. Variation | 21 | 320 | 73 |
| 3. Kits block | 65 | 810 | 80 |
| 4. Pattern / part | 28 | 1597 | 189 |
| 5. Kits screen field | 0 | 0 | 0 |
| 6. No control | 16 | 288 | 58 |
| **All parts** | **152** | **3684** | **532** |

- CSS scanned: 3761 rules with 555 `!important` across all the files above.
- Not tied to any part: 60 base rules (23 `!important`): `:root` tokens, `html`/`body`, global focus, the content column and full-width layout. They stay as Kits base CSS. Plus 17 `@keyframes` steps.
- 549 of the rules counted under parts are in `campaign-lab.css`, the Lab page's locked copy of the campaign CSS. They stay as they are until that page is unlocked.
- Rules under **No control** stay in CSS (288 rules); some can be deleted (parts not used or replaced).
- The pilot rows (Section band, Head band, the three heroes) cover 204 rules and 61 `!important`, the campaign hero included.
- Rule 5 (Kits screen field) has no part today: every part fits a Styles token, a variation, a block or a pattern. Values that Styles cannot show (such as `settings.custom` heights) are listed under "Fields Styles can't hold".

## Fields Styles can't hold

The brief listed four values that Styles might not hold: hero height, swipe-row card width, sticky bar and font preload. Each one is placed below. The rule is the same for all of them. If no one needs to edit a value, it stays a token in theme.json `settings.custom` (WordPress prints it as `--wp--custom--*`). If someone does need to edit it, it becomes a field on the existing **Kits → Design system** screen (`plugins/kits-core/includes/system-screen.php`), built with the Settings API and core `form-table` markup. No new screen, menu or panel is needed for any of them.

The result: no Kits screen field is needed yet. Hero height turned out to fit in Styles (the Hero block's Minimum height). The other three are read-only tokens or have no value left to set.

| Value | Where it lives | Default | CSS variable | Read by |
|---|---|---|---|---|
| **Hero height** | **Styles → Blocks → Hero → Dimensions → Minimum height** (block support on `kits/hero`; theme.json `styles.blocks.kits/hero.dimensions.minHeight`) | `var(--kit-hero-h)` = `min(720px, 100svh)` at every width (kit-tokens.css) | `--kit-hero-h`. A value set in Styles is printed as `:root{--kit-hero-h:…}` right after kit-tokens.css (`kits-core/includes/blocks.php`) | `.wp-block-kits-hero` (its own min-height); `.wp-block-cover.kit-hero` (kit.css, the Diwali cover hero); `section.aw-camp-band.aw-ed-hero--campaign` (aw.css, Home campaign band); campaign heroes in campaign.css (Supreme Court, Lab rules) |
| **Section padding** (pilot, kept with the hero because it uses the same bridge) | **Styles → Blocks → Group → Section → Dimensions → Padding** (theme.json `styles.blocks.core/group.variations.kits-section`) | preset `section` = `var(--kit-space-section-base)`: 48 under 768, 70 from 768, 96 from 1200 | `--kit-space-section` (top) and `--kit-space-section-end` (bottom), both `var(--kit-space-section-base)` by default. Only a changed value is printed, and it is then one value at every width | every `is-style-kits-section` group directly; through the token: `.wp-block-cover.alignfull`, groups with inline padding in post content (kit.css), the About band and finder band (aw.css), campaign sections (campaign.css, campaign-home.css), the 500 and 503 pages (dropins, which copy the default) |
| **Hero minimum on wide error and campaign pages** | theme.json `settings.custom` (no one edits it) → move from kit-tokens.css in the next batch | 560 under 1200, 660 from 1200 | `--kit-hero-min` | `.kit-error-page` (kit.css), Supreme Court hero on wide screens (campaign-lab.css), dropins |
| **Swipe-row card width** | **No field.** Sideways rows were removed site-wide (owner, 2 Nov 2026). The token stays only because the Lab rules page reads it, and that page is locked. Delete the token when that page changes | `min(82%, 22rem)`, from 768 `var(--kit-card-min)` | `--kit-row-item` (kit.css), `--kit-carousel-item` (kit-tokens.css, read by nothing) | campaign.css (Lab rules grounds) |
| **Sticky bar** (phone help bar `.kit-sticky-help`, campaign action bar `.aw-sticky-cta`) | Whether a page has the bar is a Page Option (plan step 3: page, then post type, then site). The bar's size is not a setting: it is the button height plus `--kit-space-2` above and below | on for pages that print it; hidden from 768 | none today. kit.css:410 has a raw `64px` body padding that should become `calc(var(--kit-btn-height) + 2 * var(--kit-space-2) + env(safe-area-inset-bottom))`, as campaign.css already does | kit.css:395-410, 695; campaign.css; campaign-lab.css (locked) |
| **Font preload** | **Kits → Design system field** only if a second site needs it off. Today it is code in the Animal Writes child (`functions.php` wp_head, Inter, from 1200 only), so it moves to Kits Core with the child theme's removal. Proposed field: "Preload the base font on wide screens" (checkbox, Settings API option `kits_font_preload`, default on) | on, `(min-width: 1200px)` | none (a `<link rel="preload">`) | the theme's base font face (theme.json `fontFamilies.base`) |
| **Breakpoints** | **Read-only.** CSS media queries cannot read variables, so 767/768 and 1199/1200 are fixed (Layout rules; `tools/ci/breakpoint_check.py`). theme.json `settings.custom.breakpoint` `{ "md": "768px", "lg": "1200px" }` documents them for scripts, and the Design system screen can list them as plain rows | 768, 1200 | `--wp--custom--breakpoint--md`, `--wp--custom--breakpoint--lg` (for JS and docs only) | `breakpoint_check.py`; every `@media` in the theme CSS |

**How a Styles value reaches parts that are not blocks yet.** The Hero minimum height and the Section padding are also the theme's tokens. `kits-core/includes/blocks.php` reads the saved Styles value (`wp_get_global_styles`). If it differs from the token default, it prints one `:root` rule after kit-tokens.css. Parts already rebuilt as blocks read the setting directly. Parts still in CSS read the token and follow it too, until each one is rebuilt and its CSS deleted. A site whose own token file sets these tokens can name that file's handle with the `kits_style_tokens_handle` filter.

## 3. Parts by registry

### 3a. Kits parts

From `themes/kits-by-wugweb/kits-registry.json`: 53 parts.

| # | Part | Type | Control | Slug | Settings | Replaces (file:lines) | Uncode equivalent | Notes |
|---|---|---|---|---|---|---|---|---|
| 1 | Button | primitive | Styles | `core/button` (Primary, `kits-secondary`, `kits-ghost`, `kits-brand`) | colour, spacing, radius, border, type, min-height | `kit-primitives.css:112, 231-247, 266-276, 281-305 (+4 more)`; `kit.css:1067`; `kits.css:13-20`; `aw.css:118, 122, 807, 837-847 (+8 more)` — 72 rules, 2 `!important` | Theme Options > Buttons; core: Styles > Blocks > Button | Height and press/nudge stay tokens (`--kit-btn-height`); PHP-built `kit-btn` links must print core button markup or read the same values. Animal Writes CSS overrides it in 18 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 2 | Tag | primitive | Variation | `core/post-terms` → `tag` | colour, spacing, gap, radius, border, type | `kit-primitives.css:31-49, 51, 54`; `kit.css:963, 1163, 1173`; `aw.css:325, 519, 554-555, 1376 (+1 more)` — 19 rules, 7 `!important` | module palette (tags); core: `core/post-terms` variation | PHP-built tags (`kit-tag` spans in cards) need the same `is-style-tag` class. Animal Writes CSS overrides it in 6 rules. |
| 3 | Link chip | primitive | Variation | `core/post-terms` → `chip` | colour, spacing, gap, radius, border, type | `kit-primitives.css:52-53, 55-59, 436`; `campaign-lab.css:561`; `campaign.css:625` — 10 rules, 0 `!important` | module palette (badge/chip); core: `core/post-terms` variation | Link chips on term pages are PHP-built; markup change. Animal Writes CSS overrides it in 2 rules. campaign-lab.css holds 1 of its rules, the Lab page's locked copy: they stay. |
| 4 | Badge | primitive | Styles | `core/navigation-link` → `kits-beta` | colour, radius, type | `kit-primitives.css:181-200` — 8 rules, 0 `!important` | Menu item badge; core: `core/navigation-link` style | Style is registered but has no theme.json values yet; add them under `styles.blocks.core/navigation-link.variations.kits-beta`. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 5 | Eyebrow | primitive | Variation | `core/paragraph` → `eyebrow` | colour, spacing, type | `kit-primitives.css:203` — 1 rule, 0 `!important` | heading module sub-heading; core: `core/paragraph` variation |  |
| 6 | Count | primitive | Variation | `core/paragraph` → `count` | colour, type | `kit-primitives.css:87, 133, 151, 204` — 4 rules, 0 `!important` | — | Same variation as List count (#47). |
| 7 | Form field | primitive | Styles | tokens `custom.field.*` (height, radius, border) | radius, border, colour, type, min-height | `kit-primitives.css:377-410, 440-460, 473-474, 492-496`; `kit.css:24, 160, 176-178, 551`; `aw.css:113, 546, 868-869, 875-876 (+3 more)`; `campaign-lab.css:446, 573-574`; `campaign.css:650` — 60 rules, 6 `!important` | Theme Options > Forms (Contact Form styling); core: tokens | Fields are printed by the form code, not core blocks; values sit as tokens. Not shown in Styles: see "Fields Styles can't hold". Animal Writes CSS overrides it in 14 rules. campaign-lab.css holds 3 of its rules, the Lab page's locked copy: they stay. |
| 8 | Checkbox | primitive | Styles | tokens (colour `primary`, `border`) | colour | `kit-primitives.css:419-421, 461-463, 472`; `kit.css:185-186`; `aw.css:871-873, 908, 919, 939-943` — 19 rules, 2 `!important` | Theme Options > Forms; core: tokens | Box size is the 24px target; it stays fixed. Animal Writes CSS overrides it in 10 rules. |
| 9 | Form | component | Kits block | `kits/form` | colour, spacing, gap, radius, border, type | `kit-primitives.css:413-416, 466-470`; `kit.css:159, 161-175, 179, 184 (+2 more)` — 19 rules, 2 `!important` | Contact Form module; core: block with supports | Forms are PHP + kit.js; the block wraps the existing markup. |
| 10 | Form status and done message | primitive | Styles | tokens (`success`, `error`, `warning` presets) | colour | `kit-primitives.css:424-426`; `kit.css:189-198` — 6 rules, 0 `!important` | — | Text is set by kit.js; only colours are settings. |
| 11 | Filter pill and group | primitive | Kits block | `kits/filter-bar` (pill style) | colour, spacing, gap, radius, border, type | `kit-primitives.css:11-14, 62-86, 91-102, 110-111 (+4 more)`; `kit.css:223-224`; `aw.css:129, 137` — 26 rules, 0 `!important` | Isotope filter (posts module); core: block with supports | Same block as #12. Animal Writes CSS overrides it in 2 rules. |
| 12 | Filter bar (dropdowns, Sort, active chips) | component | Kits block | `kits/filter-bar` | colour, spacing, gap, radius, border, type, min-height | `kit-primitives.css:90, 103-109, 116, 118-127 (+5 more)`; `kit.css:231, 233, 1051-1054, 1057-1058`; `aw.css:128, 143` — 41 rules, 1 `!important` | Posts module filtering; core: block with supports | Dropdown open/close is JS (no control). Animal Writes CSS overrides it in 2 rules. |
| 13 | Segmented control | primitive | Kits block | `kits/filter-bar` (sort) | colour, radius, border, type | `kit-primitives.css:207-214`; `aw.css:144-146` — 5 rules, 0 `!important` | Posts module filtering; core: block with supports | Animal Writes CSS overrides it in 2 rules. |
| 14 | Breadcrumb | component | Kits block | `kits/breadcrumb` | colour, gap, type | `kit-primitives.css:433`; `kit.css:220, 651-658, 864-865, 938-939 (+1 more)`; `aw.css:663` — 15 rules, 1 `!important` | Page Options > Header (breadcrumbs); core: Kits block | Moves the `animal-writes/breadcrumb` block (AW #28) into Kits. Animal Writes CSS overrides it in 1 rules. |
| 15 | Pagination | component | Styles | `core/query-pagination` (new `styles.blocks` entry) | colour, spacing, gap, radius, border, type | `kit.css:543-548, 593-595, 992-1005, 1104-1108`; `aw.css:599-602` — 22 rules, 11 `!important` | Posts module pagination; core: Styles > Blocks > Pagination | `kit-pager` (PHP lists) should print core pagination classes. Animal Writes CSS overrides it in 4 rules. |
| 16 | Disclosure (form behind a button) | component | Variation | `core/details` → `disclosure` | colour, spacing, radius, border | `kit-primitives.css:336-341, 476-481`; `kit.css:1008-1009`; `aw.css:685, 697-699` — 13 rules, 0 `!important` | Accordion/Toggle module; core: `core/details` variation | Open/close is behaviour (no control). Animal Writes CSS overrides it in 4 rules. |
| 17 | Steps | component | Kits block | `kits/form` (steps) | colour, gap, type | `kit-primitives.css:483-487`; `kit.css:1223-1229`; `aw.css:884-885, 907, 937-938, 1091-1093`; `campaign.css:645-648, 664` — 25 rules, 0 `!important` | Steps/Process module; core: block with supports | Part of the form block. Animal Writes CSS overrides it in 13 rules. |
| 18 | Icon | primitive | No control | — | — | `kit-primitives.css:128-129, 161, 277, 306 (+2 more)`; `kit.css:275-331, 358-359, 362, 364-365 (+24 more)`; `aw.css:138, 175, 195, 275 (+22 more)`; `campaign-lab.css:37, 56, 115, 150 (+9 more)`; `campaign-record.css:221, 295`; `campaign.css:36, 54, 180, 450 (+6 more)` — 153 rules, 1 `!important` | — | Icons are an SVG mask set that takes the text colour; size follows the text. Editing them per site would break the icon grid. Animal Writes CSS overrides it in 51 rules. campaign-lab.css holds 13 of its rules, the Lab page's locked copy: they stay. |
| 19 | Link styles | primitive | Styles | `styles.elements.link` | colour, type | `kit.css:732` — 1 rule, 0 `!important` | Theme Options > Typography (links); core: `styles.elements.link` | Already in theme.json; the rule left in CSS is the underline offset. |
| 20 | Empty state | primitive | Pattern / part | pattern `kits-by-wugweb/empty-state` | colour, spacing, type | `kit.css:596-597` — 2 rules, 0 `!important` | Theme Options > 404 (Content Block); core: pattern |  |
| 21 | Text styles (display, lead, caption, note) | primitive | Variation | `core/paragraph` → `lead`, `caption`; `core/heading` → `display` | type, colour, spacing | `kit-primitives.css:263`; `kit.css:44-49, 147-148`; `aw.css:11, 367` — 8 rules, 2 `!important` | Theme Options > Typography (font stacks); core: variations | `kit-note` already = `kits-note`. Animal Writes CSS overrides it in 2 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 22 | Background helpers | primitive | Styles | colour presets (`has-*-background-color`) | colour | `kit.css:337-338, 378-379` — 4 rules, 0 `!important` | module palette (background colour); core: palette presets | Replace `kit-bg-*` with the palette classes. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 23 | Site header | component | Pattern / part | template part `header` + `core/navigation` styles | colour, spacing, gap, min-height, type | `kit.css:149-155, 374-377, 380-391, 413-427 (+12 more)`; `aw.css:100, 978` — 118 rules, 67 `!important` | Theme Options > Navbar / Page Options > Menu; core: template part | Menu overlay open/close and scroll lock are behaviour (no control). Animal Writes CSS overrides it in 2 rules. |
| 24 | Announcement bar | component | Variation | `core/group` → `announce` | colour, spacing, type | `kit.css:371-373`; `aw.css:538` — 4 rules, 0 `!important` | Content Block (top bar); core: `core/group` variation | Bar text is AW #23. Animal Writes CSS overrides it in 1 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 25 | Sticky action bar *(not used)* | component | No control | — | — | `kit.css:395-406, 409-410, 695` — 6 rules, 2 `!important` | — | Not used. Remove its CSS instead. |
| 26 | Site footer | component | Pattern / part | template part `footer` | colour, spacing, gap, type | `kit.css:392, 583-585, 741-758, 760-806 (+2 more)`; `aw.css:1058` — 67 rules, 12 `!important` | Theme Options > Footer (Content Block); core: template part | Animal Writes CSS overrides it in 1 rules. |
| 27 | Section band and hub head | component | Variation | `core/group` → `kits-section` | spacing (padding 48/70/96 via spacing preset `section` = `var(--kit-space-section-base)`), colour (background canvas), gap (blockGap) | `kit.css:341-343, 511-512`; `aw.css:1692-1704, 1769-1770` — 15 rules, 0 `!important` | Row/Section module padding; core: `core/group` variation | Pilot (this change). Animal Writes CSS overrides it in 10 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 28 | Cards | component | Styles | `core/group` → `kits-card` (existing) | colour, spacing, radius, border, shadow, min-height | `kit.css:52-59, 70-79, 102-111, 133-146 (+11 more)`; `aw.css:212-216, 234-235, 242, 255-256 (+2 more)` — 57 rules, 11 `!important` | Posts module (card style); core: `kits-card` style | Old card classes (`kit-story`, `kit-doc`, `kit-case`, `kit-res`, `kit-tile`, `kit-task`) fold into Card (#48). Animal Writes CSS overrides it in 13 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 29 | Home editorial sections | component | Kits block | `kits/hero` | min-height (default `var(--kit-hero-h)` = min(720px,100svh)), colour, spacing, type | `kit-primitives.css:251-259`; `kit.css:26, 602-605, 612-617, 619-620 (+4 more)`; `aw.css:21, 24-25, 1027-1029, 1042`; `campaign-record.css:40, 79-80, 278-280`; `campaign.css:94-98, 124` — 53 rules, 40 `!important` | Page Options > Header (slider/hero); core: Kits block | Pilot (this change). Covers `kit-hero`; the `kit-ed` bands go to `core/group` → `kits-section` after the pilot; `kit-ed-hero`/`kit-ed-help`/`kit-ed-story` selectors are Home (AW) parts living in Kits CSS. Animal Writes CSS overrides it in 13 rules. |
| 30 | Banner | component | Variation | `core/group` → `banner` | colour, spacing, radius, border | `kit-primitives.css:429-430` — 2 rules, 1 `!important` | Content Block (CTA band); core: `core/group` variation | Pattern `kits-by-wugweb/banner` keeps the content. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 31 | Share *(replaced)* | component | Kits block | `kits/share-bar` | colour, gap, radius, border, type | `kit.css:407-408, 444-455, 457-462`; `aw.css:104, 345`; `campaign-lab.css:853, 867, 872, 884-887 (+1 more)`; `campaign.css:262, 962, 967, 979-982 (+1 more)` — 32 rules, 5 `!important` | Share module; core: Kits block | Status "replaced": sharing comes from a plugin; the block wraps its output. Animal Writes CSS overrides it in 18 rules. campaign-lab.css holds 8 of its rules, the Lab page's locked copy: they stay. |
| 32 | Table | primitive | Styles | `core/table` (new `styles.blocks` entry; core `stripes` style) | colour, spacing, border, type | `kit.css:437-441, 479-492` — 15 rules, 0 `!important` | Theme Options > Typography (tables); core: Styles > Blocks > Table |  |
| 33 | Carousel dots *(replaced)* | primitive | No control | — | — | none (styled by the parts it contains) | — | Replaced; no CSS left. |
| 34 | Ad placement (Advanced Ads) | component | No control | — | — | `kit.css:470-475` — 4 rules, 4 `!important` | — | Advanced Ads prints the markup and is off on the live site; the CSS only hides unfilled ad slots (behaviour). |
| 35 | Claim panel | component | Kits block | `kits/claim` | colour, spacing, radius, border | `kit.css:180-183`; `aw.css:85-87` — 7 rules, 0 `!important` | — | Claim flow is Directory-specific; check before multi-tenant. Animal Writes CSS overrides it in 3 rules. |
| 36 | More link | primitive | Variation | `core/paragraph` → `more` | colour, type | `kit.css:433-434`; `aw.css:161` — 3 rules, 0 `!important` | Posts module "view all" link; core: `core/paragraph` variation | Animal Writes CSS overrides it in 1 rules. |
| 37 | Character counter | primitive | No control | — | — | `kit-primitives.css:488` — 1 rule, 0 `!important` | — | JS counter inside form fields; colour comes from the muted token. |
| 38 | Print button | primitive | Styles | `core/button` → `kits-ghost` (existing) | colour, border, radius | `aw.css:61-62` — 2 rules, 0 `!important` | — | Print action is behaviour. Animal Writes CSS overrides it in 2 rules. |
| 39 | States sheet | tool | No control | — | — | none (styled by the parts it contains) | — | Private states sheet (a tool, not a page part). |
| 40 | Layout grid | primitive | Styles | `settings.layout` (contentSize, wideSize) + `blockGap` | spacing, gap | `kit.css:336, 867`; `aw.css:1799-1801` — 4 rules, 2 `!important` | Theme Options > Layout (widths, gutters); core: `settings.layout` | 720px main column and 1200 sidebar breakpoint stay fixed in layout CSS. Animal Writes CSS overrides it in 2 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 41 | Scroll lock (menu open) | primitive | No control | — | — | `kit.css:931` — 1 rule, 0 `!important` | — | Behaviour: stops page scroll while the menu is open. |
| 42 | Static page (no scroll reveal) | primitive | No control | — | — | `aw.css:1511` — 1 rule, 0 `!important` | — | Behaviour: turns off scroll reveal on a page. Animal Writes CSS overrides it in 1 rules. |
| 43 | Block components (tile, promo, rows, link list, photo card) | component | Variation | `core/cover` → `photo-card`; `core/group` → `promo`; `core/list` → `links` | colour, spacing, gap, min-height, radius, shadow, type | `kit.css:1271-1294, 1310-1314`; `campaign-lab.css:557-558` — 27 rules, 16 `!important` | Media Gallery / Single Media module; core: variations | Animal Writes CSS overrides it in 2 rules. campaign-lab.css holds 2 of its rules, the Lab page's locked copy: they stay. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 44 | Meta line (date, author, reading time, place) | primitive | Kits block | `kits/meta-row` | colour, gap, type | `kit.css:977-979, 981-987, 1168-1169`; `aw.css:1810`; `campaign-home.css:30`; `campaign-lab.css:502`; `campaign.css:768` — 12 rules, 3 `!important` | Posts module meta options; core: Kits block | Date first in every row; PHP-built card rows call the same renderer. Animal Writes CSS overrides it in 4 rules. campaign-lab.css holds 1 of its rules, the Lab page's locked copy: they stay. |
| 45 | Surfaces and overlay | primitive | Styles | `core/group` → `kits-surface` (existing) | colour, spacing, radius | `kit.css:1013-1017` — 5 rules, 0 `!important` | module palette (surface); core: `kits-surface` style | Overlay tint stays a token. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 46 | Error and empty pages (404, no results) | component | Pattern / part | templates `404`, `search` | colour, spacing, type | `kit.css:554, 1063-1066, 1068-1073, 1075-1076 (+1 more)` — 22 rules, 4 `!important` | Theme Options > 404 / search pages; core: templates |  |
| 47 | List count | primitive | Variation | `core/paragraph` → `count` | colour, type | `kit.css:1061` — 1 rule, 0 `!important` | Posts module count; core: `core/paragraph` variation | Same variation as Count (#6). |
| 48 | Card | component | Styles | `core/group` → `kits-card` (existing) | colour, spacing, radius, border, shadow, min-height | `kit.css:60, 82-98, 100, 114-132 (+10 more)`; `aw.css:101, 535, 1368-1371, 1377-1381 (+12 more)`; `campaign-lab.css:543, 554-556, 636, 809 (+1 more)`; `campaign.css:327, 812, 925, 932-934` — 150 rules, 41 `!important` | Posts module (card style); core: `kits-card` style | Sizes stay the six card patterns (`card-block-*`, `card-stacked-*`); heights per size per docs/card-sizes.md. Animal Writes CSS overrides it in 65 rules. campaign-lab.css holds 9 of its rules, the Lab page's locked copy: they stay. |
| 49 | Preview only | utility | No control | — | — | `kit.css:467` — 1 rule, 1 `!important` | — | Editor-only utility (shows a block only in the preview). |
| 50 | Core block styles (Site Editor > Styles > Blocks) | primitive | Styles | the 9 core block styles (+ `kits-beta`) | colour, spacing, radius, border, type | `kit.css:65, 80-81, 99, 525-530`; `kits.css:5-11`; `aw.css:1240, 1249, 1276, 1304-1305 (+4 more)` — 26 rules, 15 `!important` | element dynamic CSS; core: Styles > Blocks | These are the existing control; the CSS left is height and hover. Animal Writes CSS overrides it in 17 rules. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 51 | Item page with sidebar | layout | Pattern / part | template `single` (sidebar part) | gap, spacing | `kit.css:215, 1243-1250`; `aw.css:416` — 9 rules, 0 `!important` | Page Options > Sidebar; core: template | Sidebar from 1200px, hidden below (fixed). Animal Writes CSS overrides it in 1 rules. |
| 52 | Contents list | component | Kits block | `kits/toc` | colour, spacing, type | `kit.css:1318-1323` — 6 rules, 0 `!important` | Index/TOC module; core: Kits block | List is built from the headings in PHP. |
| 53 | FAQ accordion | component | Variation | `core/details` → `faq` | colour, spacing, border, radius, type | `kit.css:495-510, 513-522, 533-540`; `aw.css:445` — 28 rules, 1 `!important` | Accordion module; core: `core/details` variation | FAQ data and schema stay in the plugin; only the look moves. Animal Writes CSS overrides it in 1 rules. |

### 3b. Animal Writes parts

From `themes/animal-writes/kits-registry.json`: 99 parts. All of them are AW-specific: for multi-tenant they move to the Animal Writes plugin, and their CSS with them.

| # | Part | Type | Control | Slug | Settings | Replaces (file:lines) | Uncode equivalent | Notes |
|---|---|---|---|---|---|---|---|---|
| 1 | News card | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border, min-height | `aw.css:209-211, 218-229, 457, 462 (+9 more)`; `campaign-lab.css:520-521`; `campaign-record.css:163-174, 177-180`; `campaign.css:786-787` — 50 rules, 17 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. News classes (`aw-story`, `aw-news`) become Card sizes. campaign-lab.css holds 2 of its rules, the Lab page's locked copy: they stay. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 2 | Library row | component | Variation | `core/group` → `row` | colour, spacing, gap, border, type | `aw.css:51-58, 204-206, 232-233, 237-242 (+5 more)` — 25 rules, 8 `!important` | Row/Section module padding; core: `core/group` variation | AW-specific. PHP rows; markup change. |
| 3 | Resource card | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border | `aw.css:245-247, 252-254, 257-263, 1224 (+3 more)` — 16 rules, 0 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 4 | Opportunity card | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border | `aw.css:65-68, 284-285, 1526-1528, 1591-1593`; `campaign-home.css:22-24, 37`; `campaign-lab.css:109, 113, 188, 243-245 (+3 more)`; `campaign.css:174, 178, 283-286, 289 (+5 more)` — 45 rules, 0 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. Countdown is behaviour (no control). campaign-lab.css holds 12 of its rules, the Lab page's locked copy: they stay. |
| 5 | Organisation card | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border, min-height | `aw.css:98-99, 130, 266-267, 270-274 (+19 more)`; `dir-map.css:2, 16-18, 22` — 72 rules, 3 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. Directory card. |
| 6 | Organisation page header | component | Variation | `core/group` → `kits-head-band` | colour, spacing, gap | `aw.css:702-708, 712-714, 729-731, 734-739 (+1 more)` — 18 rules, 4 `!important` | Page Options > Header; core: `core/group` variation | AW-specific. Listing page top; reuses the pilot variation. |
| 7 | Organisation details | component | Kits block | `animal-writes/org-details` + supports | colour, spacing, gap, radius, border | `aw.css:391-393, 490, 710, 740-758 (+4 more)` — 39 rules, 5 `!important` | per-module CSS; core: block supports | AW-specific. Block exists; add block supports. |
| 8 | Directory search | component | Kits block | `animal-writes/list-filters` + supports | colour, spacing, gap, radius, border, min-height | `aw.css:108-112, 114, 116-117, 119-122 (+10 more)`; `campaign.css:261` — 33 rules, 0 `!important` | Posts module filtering; core: block supports | AW-specific. Directory search bar. |
| 9 | Find help near you | component | Pattern / part | pattern `animal-writes/find-help` | colour, spacing | `aw.css:157-158, 160, 162-164` — 6 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. |
| 10 | Press item | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border | `kit.css:1174`; `aw.css:297-304, 333, 458-461, 463 (+6 more)` — 31 rules, 21 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. Kits CSS holds 1 of its rules: move them to the site plugin before multi-tenant. |
| 11 | Hub page frame | component | Variation | `core/group` → `kits-section` | colour, spacing, gap | `aw.css:29-30, 115, 201, 504-507 (+3 more)` — 14 rules, 3 `!important` | Row/Section module padding; core: `core/group` variation | AW-specific. Uses the pilot variation. Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 12 | Search results | component | Pattern / part | template `search` | spacing, type | `aw.css:509-518, 542-545` — 14 rules, 1 `!important` | Theme Options > Search page; core: template | AW-specific. |
| 13 | Helplines | component | Kits block | `animal-writes/helplines` (new) | colour, spacing, gap, radius, border | `aw.css:26, 35-48, 95` — 13 rules, 0 `!important` | per-module CSS; core: new block | AW-specific. PHP list today; new block. |
| 14 | Home sections (Animal Writes) | component | Variation | `core/group` → `kits-section` | colour, spacing, gap | `aw.css:8-10, 12-18, 31-32, 69-71 (+4 more)`; `campaign-record.css:186, 205` — 38 rules, 7 `!important` | Row/Section module padding; core: `core/group` variation | AW-specific. Home bands; hero part goes to `kits/hero` (pilot). Class is in theme patterns/templates: older saved groups get the style on the front end (render_block_data) and in the editor (assets/js/legacy-styles.js), which keeps it in the content on the next save. |
| 15 | Volunteer banner and form | component | Pattern / part | pattern `animal-writes/volunteer-banner` | colour, spacing, radius | `aw.css:522-527` — 6 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. |
| 16 | Forms (Animal Writes) | component | Kits block | `kits/form` | colour, spacing, gap, radius, border, type | `aw.css:72-74, 430-431, 467-471, 577 (+21 more)` — 140 rules, 2 `!important` | Contact Form module; core: block with supports | AW-specific. Form fields and copy stay site content. |
| 17 | Campaign page | component | Pattern / part | pattern `animal-writes/campaign` + template `campaign-page` | colour, spacing, gap, type | `aw.css:77-81, 292-294`; `campaign-home.css:6-7, 10`; `campaign-lab.css:5-32, 69-80, 120-130, 144-146 (+9 more)`; `campaign-record.css:23-37, 52-64, 90, 96-98 (+16 more)`; `campaign.css:4-31, 115-119, 129-140, 185-196 (+10 more)` — 171 rules, 24 `!important` | Content Block (landing page); core: pattern + template | AW-specific. Bands use `kits-section`, cards `kits-card`. campaign-lab.css holds 41 of its rules, the Lab page's locked copy: they stay. |
| 18 | Related and also-reported | component | Kits block | `kits/related` | colour, spacing, gap, type | `aw.css:249, 306-313, 571-572, 1217-1218` — 13 rules, 1 `!important` | Related posts (Posts module); core: Kits block | AW-specific. Shortcode today; markup change. |
| 19 | Item page rows | component | Pattern / part | template `single` | gap, spacing | `kit.css:863`; `aw.css:317, 327-330, 344, 347 (+10 more)` — 33 rules, 3 `!important` | Content Block (single post); core: template | AW-specific. Rows are the Item blocks (#73–77). Kits CSS holds 1 of its rules: move them to the site plugin before multi-tenant. |
| 20 | Related items (item pages) | component | Kits block | `kits/related` | colour, spacing, gap, type | `aw.css:497-499` — 3 rules, 0 `!important` | Related posts (Posts module); core: Kits block | AW-specific. `[aw_related]` shortcode today. |
| 21 | Category index | component | Kits block | `kits/term-index` (new) | colour, spacing, gap, radius, min-height | `aw.css:477-483` — 7 rules, 0 `!important` | Category list module; core: Kits block | AW-specific. PHP list today. |
| 22 | Data sources | component | Kits block | `animal-writes/data-sources` (new) | colour, spacing, gap, radius, border | `aw.css:549-553, 556-557, 759-760, 968 (+3 more)` — 54 rules, 2 `!important` | Kits block with supports | AW-specific. |
| 23 | Announcement text | component | Pattern / part | template part `header` (announcement text) | type | `aw.css:538` — 1 rule, 0 `!important` | Content Block (top bar); core: template part | AW-specific. Look is Kits #24. |
| 24 | Footer extras | component | Pattern / part | template part `footer` | type, colour | none (styled by the parts it contains) | Theme Options > Footer (Content Block); core: template part | AW-specific. |
| 25 | Buttons (Animal Writes) | component | Styles | `core/button` styles (existing) | colour, radius, border | none (styled by the parts it contains) | Theme Options > Buttons; core: Styles > Blocks > Button | AW-specific. No CSS left; retire the `aw-btn` alias. |
| 26 | Finder band | component | Variation | `core/group` → `kits-section` | colour, spacing | `aw.css:22-23` — 2 rules, 1 `!important` | Row/Section module padding; core: `core/group` variation | AW-specific. |
| 27 | Helpers | primitive | No control | — | — | `aw.css:661, 1227, 1232` — 3 rules, 0 `!important` | — | AW-specific. Small utilities (copy button, reduced motion, screen helpers); behaviour, not look. |
| 28 | Breadcrumb | block | Kits block | `kits/breadcrumb` | colour, gap, type | none (styled by the parts it contains) | Page Options > Header (breadcrumbs); core: Kits block | AW-specific. Block moves to Kits (Kits #14). |
| 29 | Hub count | block | No control | — | — | none (styled by the parts it contains) | — | AW-specific. Renders nothing (the count moved to the filters row). Retire the block. |
| 30 | Hub pictures | block | Kits block | `animal-writes/hub-visual` + supports | gap, radius, min-height | `kit.css:217, 241, 248-250, 252 (+2 more)` — 9 rules, 0 `!important` | Kits block with supports | AW-specific. Sits inside the head band. Kits CSS holds 9 of its rules: move them to the site plugin before multi-tenant. |
| 31 | List filters | block | Kits block | `kits/filter-bar` | colour, spacing, gap, radius, border | `kit.css:214, 221-222, 226, 236-238 (+1 more)`; `aw.css:662` — 9 rules, 0 `!important` | Posts module filtering; core: Kits block | AW-specific. Block prints the Kits filter bar (Kits #12) or the Directory search (#8). Kits CSS holds 8 of its rules; they become the Kits control. |
| 32 | Law date (one line) | block | Kits block | `animal-writes/law-date` + supports | colour, type | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 33 | Updated and reading time | block | Kits block | `kits/meta-row` | colour, gap, type | none (styled by the parts it contains) | Posts module meta options; core: Kits block | AW-specific. Generic: date, reading time. |
| 34 | Place | block | Kits block | `animal-writes/place` + supports | colour, type | `aw.css:963-965` — 3 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 35 | Opportunity buttons | block | Kits block | `animal-writes/opp-actions` + supports | gap | `kit.css:1191`; `aw.css:287, 646` — 3 rules, 1 `!important` | Kits block with supports | AW-specific. Kits CSS holds 1 of its rules: move them to the site plugin before multi-tenant. |
| 36 | Organisation logo | block | Kits block | `animal-writes/org-mark` + supports | radius, border, min-height | `aw.css:268-269, 281, 363, 1186` — 5 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 37 | Badges and labels | block | Kits block | `animal-writes/badges` + supports | gap | `aw.css:276, 652` — 2 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 38 | Contact icons | block | Kits block | `animal-writes/contact` + supports | colour, gap | `aw.css:147, 670` — 2 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 39 | Organisation buttons | block | Kits block | `animal-writes/org-actions` + supports | gap | `aw.css:278-280, 281` — 5 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 40 | Result count and sort | block | Kits block | `animal-writes/result-head` + supports | colour, gap, type | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 41 | Browse by state | block | Kits block | `animal-writes/browse-states` + supports | colour, gap, radius, type | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 42 | Posted by | block | Kits block | `animal-writes/posted-by` + supports | colour, type | `aw.css:334, 976` — 2 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 43 | Volunteer form | block | Kits block | `kits/form` | colour, spacing, gap, radius, border | none (styled by the parts it contains) | Contact Form module; core: Kits block | AW-specific. Volunteer form block. |
| 44 | Is this your organisation? | block | Kits block | `animal-writes/listing-help` + supports | colour, spacing, radius, border | `aw.css:691` — 1 rule, 0 `!important` | Kits block with supports | AW-specific. |
| 45 | Animal law | pattern | Pattern / part | pattern `animal-writes/animal-law` | colour, spacing, gap | none (styled by the parts it contains) | Content Block; core: pattern / part | AW-specific. |
| 46 | India Writes for Its Community Dogs | pattern | No control | — | — | `aw.css:1840-1848`; `campaign-record-home.css:5-37`; `campaign-record.css:9-20, 146-148, 151-153, 162 (+12 more)` — 118 rules, 50 `!important` | — | AW-specific. Frozen (Community dogs page): owner-locked. |
| 47 | Campaign page | pattern | Pattern / part | pattern `animal-writes/campaign` | colour, spacing, gap | none (styled by the parts it contains) | Content Block; core: pattern / part | AW-specific. |
| 48 | Find help | pattern | Pattern / part | pattern `animal-writes/find-help` | colour, spacing, gap, radius | `aw.css:174, 176-178, 1771-1772, 1774-1777 (+1 more)` — 18 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. |
| 49 | AW Content | pattern | Pattern / part | pattern `animal-writes/aw-content` | colour, spacing | `aw.css:1557-1564, 1566, 1568-1570, 1581-1582 (+1 more)` — 14 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. |
| 50 | Supreme Court campaign card | pattern | Pattern / part | pattern `animal-writes/aw-sc` | colour, spacing, radius, min-height | `aw.css:1579-1580, 1583-1590`; `campaign.css:241-251` — 18 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. Card inside uses `kits-card`. |
| 51 | Campaign cards row | pattern | Pattern / part | pattern `animal-writes/aw-pair` | gap, spacing | `aw.css:1601-1606` — 5 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. |
| 52 | Need help now | pattern | Pattern / part | pattern `animal-writes/help-now` | colour, spacing, gap, radius, min-height | `aw.css:1713-1736, 1739, 1741-1753, 1755-1761 (+1 more)` — 39 rules, 1 `!important` | Content Block; core: pattern / part | AW-specific. Tiles use `kits-card`. |
| 53 | Homepage hero | pattern | Kits block | `kits/hero` | min-height (default `var(--kit-hero-h)` = min(720px,100svh)), colour, spacing, type | none (styled by the parts it contains) | Page Options > Header (hero); core: Kits block | Pilot (this change). AW-specific. Pattern holds the `kit-hero` cover. |
| 54 | Learn | pattern | Pattern / part | pattern `animal-writes/learn` | colour, spacing, gap | none (styled by the parts it contains) | Content Block; core: pattern / part | AW-specific. |
| 55 | Take part | pattern | Pattern / part | pattern `animal-writes/take-part` | colour, spacing, gap | none (styled by the parts it contains) | Content Block; core: pattern / part | AW-specific. |
| 56 | Take part cards | pattern | Pattern / part | pattern `animal-writes/take-part-cards` | gap, min-height, radius | `aw.css:1617-1689` — 34 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. Photo cards use Kits #43 `photo-card`. |
| 57 | Take action *(replaced)* | pattern | No control | — | — | none (styled by the parts it contains) | — | AW-specific. Replaced by Take part (#55); retire. |
| 58 | AW_Festive | pattern | Pattern / part | pattern `animal-writes/festive` | colour, spacing, gap | `aw.css:1858-1862, 1864`; `campaign-lab.css:41-52, 67`; `campaign.css:39-50, 62, 89-90, 99 (+4 more)` — 18 rules, 0 `!important` | Content Block; core: pattern / part | AW-specific. campaign-lab.css holds 2 of its rules, the Lab page's locked copy: they stay. |
| 59 | Volunteer banner | pattern | Pattern / part | pattern `animal-writes/volunteer-banner` | colour, spacing, radius | `aw.css:426-429, 674-683, 1069-1072, 1078 (+1 more)` — 19 rules, 1 `!important` | Content Block; core: pattern / part | AW-specific. |
| 60 | What's happening | pattern | Pattern / part | pattern `animal-writes/whats-happening` | colour, spacing, gap | `aw.css:590-594, 596-598, 605-607, 611-612 (+13 more)` — 54 rules, 27 `!important` | Content Block; core: pattern / part | AW-specific. |
| 61 | Local card | component | Styles | `core/group` → `kits-card` | colour, spacing, radius, border | `aw.css:1011-1014` — 4 rules, 1 `!important` | Posts module (card style); core: `kits-card` style | AW-specific. |
| 62 | Sponsored Resource | block | Kits block | `animal-writes/sponsored-item` + supports | colour, spacing, radius, border | `kit.css:1175`; `aw.css:1123-1128, 1225` — 8 rules, 0 `!important` | Kits block with supports | AW-specific. Kits CSS holds 1 of its rules: move them to the site plugin before multi-tenant. |
| 63 | Sponsored banner | block | Kits block | `animal-writes/sponsored-banner` + supports | colour, spacing, radius, border, min-height | `aw.css:1129-1137` — 8 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 64 | Date with calendar icon | component | Kits block | `kits/meta-row` | colour, gap, type | `kit-primitives.css:50`; `aw.css:319, 491, 1230`; `campaign-lab.css:114, 226, 734, 862`; `campaign-record.css:82, 123`; `campaign.css:179, 404, 472, 849 (+1 more)` — 15 rules, 0 `!important` | Posts module meta options; core: Kits block | AW-specific. Date item with calendar icon. Kits CSS holds 1 of its rules; they become the Kits control. campaign-lab.css holds 4 of its rules, the Lab page's locked copy: they stay. |
| 65 | Campaign tools | component | Pattern / part | template `campaign-page` + campaign patterns | colour, spacing, gap, radius, border, type | `aw.css:291, 655, 1520-1522, 1535 (+1 more)`; `campaign-home.css:8-9, 11-14, 25-28, 32 (+3 more)`; `campaign-lab.css:34-36, 38-39, 54-55, 58-65 (+46 more)`; `campaign-record.css:65-76, 81, 89, 93-94 (+6 more)`; `campaign.css:33-35, 37, 52-53, 56-60 (+51 more)` — 918 rules, 44 `!important` | Content Block (landing page); core: template + patterns | AW-specific. Campaign landing pages are full-width (no 720px column). `aw-camp-hero` rules move with the pilot `kits/hero` (see the "Campaign hero" line under this table). Lab page is locked. campaign-lab.css holds 422 of its rules, the Lab page's locked copy: they stay. |
| 66 | Map picker (listing edit screen) | component | No control | — | — | none (styled by the parts it contains) | — | AW-specific. Admin screen (listing edit); admin UI stays core-only. |
| 67 | Head band (breadcrumb, H1, intro, count, filters) | block | Variation | `core/group` → `kits-head-band` | spacing, colour, gap | `kit.css:210-213, 216, 218, 227-228 (+7 more)`; `aw.css:589, 658-660, 1473` — 30 rules, 8 `!important` | Page Options > Header; core: `core/group` variation | Pilot (this change). AW-specific. PHP `aw_head_band()` (sections.php) prints the class `kit-section-band`; it must print `is-style-kits-head-band`. Kits CSS holds 25 of its rules; they become the Kits control. |
| 68 | Guide call to action (campaign) | pattern | Pattern / part | pattern `animal-writes/guide-cta` | colour, spacing, radius | none (styled by the parts it contains) | Content Block; core: pattern / part | AW-specific. |
| 69 | About page bands | component | Variation | `core/group` → `kits-section` | colour, spacing, gap | `aw.css:1236-1239, 1241-1248, 1251-1275, 1277-1297 (+6 more)` — 57 rules, 15 `!important` | Row/Section module padding; core: `core/group` variation | AW-specific. About page bands. |
| 70 | Adoption beta page | component | Pattern / part | page content (beta page) + `kits-section` | colour, spacing | `aw.css:1333-1338, 1340-1343, 1345` — 11 rules, 5 `!important` | Content Block; core: pattern / part | AW-specific. |
| 71 | Live Google block | component | Kits block | `animal-writes/glive` (new) | colour, spacing, radius | `aw.css:1348-1349` — 2 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 72 | Enrichment review (admin) | component | No control | — | — | none (styled by the parts it contains) | — | AW-specific. Admin screen (core list table); no front-end look. |
| 73 | Item: meta row | block | Kits block | `animal-writes/item-meta` + supports | colour, gap, type | `aw.css:318, 320, 322-324, 394` — 6 rules, 1 `!important` | Kits block with supports | AW-specific. Prints `kits/meta-row` markup. |
| 74 | Item: sources | block | Kits block | `animal-writes/item-sources` + supports | colour, spacing, gap, border, type | `aw.css:331-332, 335-341, 409-410` — 11 rules, 1 `!important` | Kits block with supports | AW-specific. |
| 75 | Item: tags | block | Variation | `core/post-terms` → `tag` | colour, gap, radius, type | `aw.css:343` — 1 rule, 0 `!important` | Posts module meta (tags); core: `core/post-terms` variation | AW-specific. Swap the AW block for core Post terms with the Kits `tag` variation. |
| 76 | Item: share | block | Kits block | `kits/share-bar` | colour, gap, radius, border | none (styled by the parts it contains) | Share module; core: Kits block | AW-specific. |
| 77 | Item: more | block | Kits block | `animal-writes/item-more` + supports | colour, spacing, gap, radius | `aw.css:159, 348-351, 442, 448-449` — 8 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 78 | What people are saying (social posts) *(built, off (owner's OK of design and label first))* | block | No control | — | — | none (styled by the parts it contains) | — | AW-specific. Built but off until its design and label are approved. |
| 79 | Listing: logo and description | block | Kits block | `animal-writes/listing-about` + supports | spacing, gap, radius, border | `aw.css:366, 368-373` — 7 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 80 | Listing: registrations | block | Kits block | `animal-writes/listing-registrations` + supports | colour, spacing, type | `aw.css:387` — 1 rule, 0 `!important` | Kits block with supports | AW-specific. |
| 81 | Listing: contact | block | Kits block | `animal-writes/listing-contact` + supports | gap, spacing | `aw.css:395-398` — 3 rules, 0 `!important` | Kits block with supports | AW-specific. |
| 82 | Listing: location | block | Kits block | `animal-writes/listing-location` + supports | colour, gap, type | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 83 | Listing: services | block | Kits block | `animal-writes/listing-services` + supports | colour, spacing, type | `aw.css:390` — 1 rule, 0 `!important` | Kits block with supports | AW-specific. |
| 84 | Listing: organisation | block | Kits block | `animal-writes/listing-organisation` + supports | colour, spacing, type | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 85 | Listing: online profiles | block | Kits block | `animal-writes/listing-profiles` + supports | gap, radius | `aw.css:383, 400-402, 404-405` — 6 rules, 1 `!important` | Kits block with supports | AW-specific. |
| 86 | Listing: support | block | Kits block | `animal-writes/listing-support` + supports | gap, spacing | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 87 | Listing: photos | block | Kits block | `animal-writes/listing-photos` + supports | gap, radius, min-height | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 88 | Current campaign | block | Kits block | `animal-writes/current-campaign` + supports | colour, spacing, radius, border | `aw.css:417-425` — 9 rules, 0 `!important` | Kits block with supports | AW-specific. Card look from `kits-card`. |
| 89 | Listing: activity | block | Kits block | `animal-writes/listing-activity` + supports | colour, spacing, gap, radius, border | `aw.css:433-441` — 9 rules, 1 `!important` | Kits block with supports | AW-specific. |
| 90 | Listing: in the press | block | Kits block | `animal-writes/listing-press` + supports | gap, spacing | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 91 | Listing: opportunities | block | Kits block | `animal-writes/listing-opportunities` + supports | gap, spacing | none (styled by the parts it contains) | Kits block with supports | AW-specific. |
| 92 | Listing: campaigns | block | Kits block | `animal-writes/listing-campaigns` + supports | gap, spacing | `aw.css:358` — 1 rule, 0 `!important` | Kits block with supports | AW-specific. |
| 93 | Claim your listing | block | Kits block | `animal-writes/claim-cta` + supports | colour, spacing, radius, border | `aw.css:452-455` — 4 rules, 0 `!important` | Kits block with supports | AW-specific. Shares the look of Kits #35 `kits/claim`. |
| 94 | Term page: FAQ | block | Kits block | `animal-writes/term-faq` + supports | colour, spacing, border | `aw.css:443-444` — 2 rules, 0 `!important` | Accordion module; core: block supports | AW-specific. Items use `core/details` → `faq` look (Kits #53). |
| 95 | Term page: share | block | Kits block | `kits/share-bar` | colour, gap, radius, border | none (styled by the parts it contains) | Share module; core: Kits block | AW-specific. |
| 96 | Term page: related | block | Kits block | `animal-writes/term-related` + supports | gap, spacing | `aw.css:447` — 1 rule, 0 `!important` | Kits block with supports | AW-specific. Chips use `chip` (Kits #3). |
| 97 | Item: FAQ | block | Kits block | `animal-writes/item-faq` + supports | colour, spacing, border | `aw.css:446` — 1 rule, 0 `!important` | Accordion module; core: block supports | AW-specific. Items use `core/details` → `faq` look (Kits #53). |
| 98 | Directory map and Near me | component | Kits block | `animal-writes/directory-map` (new) | min-height, radius, border | `aw.css:132`; `dir-map.css:3-15, 19-21` — 17 rules, 0 `!important` | Map module; core: new block (frame only) | AW-specific. Map and "Near me" are behaviour; only the frame is a setting. |
| 99 | Home: hero | block | Kits block | `kits/hero` | min-height (default `var(--kit-hero-h)` = min(720px,100svh)), colour, spacing, type | `aw.css:1523-1525, 1529-1532, 1536-1538, 1544-1551`; `campaign-home.css:15-21, 29, 31, 33-35 (+1 more)`; `campaign-lab.css:487-493, 501, 503, 506-508 (+1 more)`; `campaign.css:753-759, 767, 769, 772-774 (+1 more)` — 61 rules, 0 `!important` | Page Options > Header (hero); core: Kits block | Pilot (this change). AW-specific. Home hero `aw-ed-hero` (and its campaign state `aw-camp-band`). campaign-lab.css holds 15 of its rules, the Lab page's locked copy: they stay. |

**Campaign hero** (`aw-camp-hero`, part of Campaign tools #65): Kits block `kits/hero`, min-height (default `var(--kit-hero-h)`), colour, spacing, type. Pilot (this change). Replaces `campaign-lab.css:82-95, 100, 104, 176-181, 189, 233-235`; `campaign-record.css:88, 91-92, 95, 102-109, 127, 129-130 (+3 more)`; `campaign.css:142, 151-160, 165, 169, 255-256, 264-277 (+2 more)` — 45 rules, 13 `!important`.

## 4. Parts with no control

- **Kits #18 Icon**: Icons are an SVG mask set that takes the text colour; size follows the text. Editing them per site would break the icon grid. (153 rules.)
- **Kits #25 Sticky action bar**: Not used. Remove its CSS instead. (6 rules.)
- **Kits #33 Carousel dots**: Replaced; no CSS left. (0 rules.)
- **Kits #34 Ad placement (Advanced Ads)**: Advanced Ads prints the markup and is off on the live site; the CSS only hides unfilled ad slots (behaviour). (4 rules.)
- **Kits #37 Character counter**: JS counter inside form fields; colour comes from the muted token. (1 rule.)
- **Kits #39 States sheet**: Private states sheet (a tool, not a page part). (0 rules.)
- **Kits #41 Scroll lock (menu open)**: Behaviour: stops page scroll while the menu is open. (1 rule.)
- **Kits #42 Static page (no scroll reveal)**: Behaviour: turns off scroll reveal on a page. (1 rule.)
- **Kits #49 Preview only**: Editor-only utility (shows a block only in the preview). (1 rule.)
- **Animal Writes #27 Helpers**: Small utilities (copy button, reduced motion, screen helpers); behaviour, not look. (3 rules.)
- **Animal Writes #29 Hub count**: Renders nothing (the count moved to the filters row). Retire the block. (0 rules.)
- **Animal Writes #46 India Writes for Its Community Dogs**: Frozen (Community dogs page): owner-locked. (118 rules.)
- **Animal Writes #57 Take action**: Replaced by Take part (#55); retire. (0 rules.)
- **Animal Writes #66 Map picker (listing edit screen)**: Admin screen (listing edit); admin UI stays core-only. (0 rules.)
- **Animal Writes #72 Enrichment review (admin)**: Admin screen (core list table); no front-end look. (0 rules.)
- **Animal Writes #78 What people are saying (social posts)**: Built but off until its design and label are approved. (0 rules.)

## 5. Order of work

After the pilot, one batch per page group. Groups are ordered by the CSS they remove, largest first. Counts leave out the pilot rows, the parts with no control and the locked `campaign-lab.css` lines.

| Batch | Page group | Parts | Rules that move | `!important` | Biggest parts (rules) |
|---|---|---|---|---|---|
| 1 | Lists (cards, filters, head) | 38 | 700 | 127 | Kits #48 Card (141), AW #5 Organisation card (72), Kits #28 Cards (57), AW #1 News card (48), Kits #12 Filter bar (41) |
| 2 | Single pages and forms | 51 | 700 | 57 | AW #16 Forms (140), Kits #7 Form field (57), AW #69 About page bands (57), AW #22 Data sources (54), AW #7 Organisation details (39) |
| 3 | Campaigns | 4 | 626 | 52 | AW #65 Campaign tools (496), AW #17 Campaign page (130), AW #47 Campaign page (0), AW #68 Guide call to action (0) |
| 4 | Home | 17 | 294 | 53 | AW #60 What's happening (54), AW #52 Need help now (39), AW #14 Home sections (38), AW #56 Take part cards (34), Kits #43 Block components (25) |
| 5 | Header and footer | 6 | 198 | 79 | Kits #23 Site header (118), Kits #26 Site footer (67), Kits #4 Badge (8), Kits #24 Announcement bar (4), AW #23 Announcement text (1) |
| 6 | Shared on all pages | 15 | 167 | 26 | Kits #1 Button (72), Kits #50 Core block styles (26), Kits #46 Error and empty pages (22), Kits #32 Table (15), Kits #21 Text styles (8) |

Notes on the order:

- Campaigns come second: most of that CSS is campaign-only tools (letters, tallies, share packs) printed by PHP. Start with the shared pieces: bands on `kits-section`, cards on `kits-card`.
- In Lists, Card (`kits-card`) goes first: News, Resource, Opportunity, Organisation, Press and Local cards all reuse it.
- Header and footer are template parts already; most of their CSS is the mobile menu overlay, which stays as behaviour.
- "Shared on all pages" (Button, text styles, tokens, the core block styles) is small in CSS but touches every page; it can run alongside any batch.

## Method

- Rules were read from the current files on this branch. A rule belongs to the part whose class is the longest match for a class in the last part of its selector (registry classes first, then classes printed by the block or pattern's PHP).
- Rules with only core classes were mapped by block: navigation → Site header, post template and featured image → Card, table → Table, details and FAQ → FAQ accordion, pagination → Pagination, search → Form field.
- Where two parts list the same class, the newer part owns it: `kit-card` → Card (#48), `kit-photo-card` → Block components (#43), `aw-take-part` → Take part cards (AW #56), `aw-hub-list`/`aw-news-*` → What's happening (AW #60).
- `kit-primitives.css` line 2: the comment `--r-*/--radius-*` closes the opening comment early, so the browser drops the `.screen-reader-text` rule on line 7. `kit.css:669` still defines it, so nothing shows.

## Full line ranges

<details><summary>Every range per part</summary>

- Kits #1 Button: kit-primitives.css:112, 231-247, 266-276, 281-305, 309-315, 317-335, 342-354, 360-373; kit.css:1067; kits.css:13-20; aw.css:118, 122, 807, 837-847, 852, 1456, 1471, 1567, 1571, 1595, 1778, 1792
- Kits #2 Tag: kit-primitives.css:31-49, 51, 54; kit.css:963, 1163, 1173; aw.css:325, 519, 554-555, 1376, 1411
- Kits #3 Link chip: kit-primitives.css:52-53, 55-59, 436; campaign-lab.css:561; campaign.css:625
- Kits #4 Badge: kit-primitives.css:181-200
- Kits #5 Eyebrow: kit-primitives.css:203
- Kits #6 Count: kit-primitives.css:87, 133, 151, 204
- Kits #7 Form field: kit-primitives.css:377-410, 440-460, 473-474, 492-496; kit.css:24, 160, 176-178, 551; aw.css:113, 546, 868-869, 875-876, 882-883, 1476, 1773; campaign-lab.css:446, 573-574; campaign.css:650
- Kits #8 Checkbox: kit-primitives.css:419-421, 461-463, 472; kit.css:185-186; aw.css:871-873, 908, 919, 939-943
- Kits #9 Form: kit-primitives.css:413-416, 466-470; kit.css:159, 161-175, 179, 184, 187-188, 669
- Kits #10 Form status and done message: kit-primitives.css:424-426; kit.css:189-198
- Kits #11 Filter pill and group: kit-primitives.css:11-14, 62-86, 91-102, 110-111, 117, 156, 164, 178; kit.css:223-224; aw.css:129, 137
- Kits #12 Filter bar (dropdowns, Sort, active chips): kit-primitives.css:90, 103-109, 116, 118-127, 130-132, 134-150, 152-155, 157-160, 165-177; kit.css:231, 233, 1051-1054, 1057-1058; aw.css:128, 143
- Kits #13 Segmented control: kit-primitives.css:207-214; aw.css:144-146
- Kits #14 Breadcrumb: kit-primitives.css:433; kit.css:220, 651-658, 864-865, 938-939, 941; aw.css:663
- Kits #15 Pagination: kit.css:543-548, 593-595, 992-1005, 1104-1108; aw.css:599-602
- Kits #16 Disclosure (form behind a button): kit-primitives.css:336-341, 476-481; kit.css:1008-1009; aw.css:685, 697-699
- Kits #17 Steps: kit-primitives.css:483-487; kit.css:1223-1229; aw.css:884-885, 907, 937-938, 1091-1093; campaign.css:645-648, 664
- Kits #18 Icon: kit-primitives.css:128-129, 161, 277, 306, 316, 356; kit.css:275-331, 358-359, 362, 364-365, 367, 456, 606-607, 618, 621, 623, 698, 710, 723-729, 759, 818-819, 825, 940, 965, 980, 1046, 1055-1056, 1059, 1074, 1077, 1137-1139, 1164, 1170, 1187; aw.css:138, 175, 195, 275, 321, 365, 380, 403, 539, 671, 767, 774, 1113, 1117, 1119, 1214, 1298, 1339, 1445, 1485, 1565, 1737, 1740, 1754, 1762, 1863; campaign-lab.css:37, 56, 115, 150, 272, 287, 315, 330, 435, 606, 733, 846, 877; campaign-record.css:221, 295; campaign.css:36, 54, 180, 450, 471, 499, 526, 541, 714, 972
- Kits #19 Link styles: kit.css:732
- Kits #20 Empty state: kit.css:596-597
- Kits #21 Text styles (display, lead, caption, note): kit-primitives.css:263; kit.css:44-49, 147-148; aw.css:11, 367
- Kits #22 Background helpers: kit.css:337-338, 378-379
- Kits #23 Site header: kit.css:149-155, 374-377, 380-391, 413-427, 568-579, 661-665, 674-693, 699-709, 711-719, 807-808, 870-928, 933-934, 1020-1045, 1047, 1098-1101, 1120-1125; aw.css:100, 978
- Kits #24 Announcement bar: kit.css:371-373; aw.css:538
- Kits #25 Sticky action bar: kit.css:395-406, 409-410, 695
- Kits #26 Site footer: kit.css:392, 583-585, 741-758, 760-806, 809-817, 820-823; aw.css:1058
- Kits #27 Section band and hub head: kit.css:341-343, 511-512; aw.css:1692-1704, 1769-1770
- Kits #28 Cards: kit.css:52-59, 70-79, 102-111, 133-146, 251, 253, 269, 353-357, 360-361, 363, 366, 367, 599, 1257-1268, 1295; aw.css:212-216, 234-235, 242, 255-256, 608, 1802-1804
- Kits #29 Home editorial sections: kit-primitives.css:251-259; kit.css:26, 602-605, 612-617, 619-620, 622, 624-647, 1297-1304, 1315; aw.css:21, 24-25, 1027-1029, 1042; campaign-record.css:40, 79-80, 278-280; campaign.css:94-98, 124
- Kits #30 Banner: kit-primitives.css:429-430
- Kits #31 Share: kit.css:407-408, 444-455, 457-462; aw.css:104, 345; campaign-lab.css:853, 867, 872, 884-887, 890; campaign.css:262, 962, 967, 979-982, 985
- Kits #32 Table: kit.css:437-441, 479-492
- Kits #34 Ad placement (Advanced Ads): kit.css:470-475
- Kits #35 Claim panel: kit.css:180-183; aw.css:85-87
- Kits #36 More link: kit.css:433-434; aw.css:161
- Kits #37 Character counter: kit-primitives.css:488
- Kits #38 Print button: aw.css:61-62
- Kits #40 Layout grid: kit.css:336, 867; aw.css:1799-1801
- Kits #41 Scroll lock (menu open): kit.css:931
- Kits #42 Static page (no scroll reveal): aw.css:1511
- Kits #43 Block components (tile, promo, rows, link list, photo card): kit.css:1271-1294, 1310-1314; campaign-lab.css:557-558
- Kits #44 Meta line (date, author, reading time, place): kit.css:977-979, 981-987, 1168-1169; aw.css:1810; campaign-home.css:30; campaign-lab.css:502; campaign.css:768
- Kits #45 Surfaces and overlay: kit.css:1013-1017
- Kits #46 Error and empty pages (404, no results): kit.css:554, 1063-1066, 1068-1073, 1075-1076, 1082-1094
- Kits #47 List count: kit.css:1061
- Kits #48 Card: kit.css:60, 82-98, 100, 114-132, 201-204, 563, 587, 1132-1136, 1147-1162, 1165-1167, 1171-1172, 1178-1186, 1188-1190, 1193-1219; aw.css:101, 535, 1368-1371, 1377-1381, 1392, 1399-1402, 1406, 1412-1414, 1417-1431, 1436-1438, 1481, 1492-1498, 1811-1813, 1816, 1823-1826, 1830; campaign-lab.css:543, 554-556, 636, 809, 816-818; campaign.css:327, 812, 925, 932-934
- Kits #49 Preview only: kit.css:467
- Kits #50 Core block styles (Site Editor > Styles > Blocks): kit.css:65, 80-81, 99, 525-530; kits.css:5-11; aw.css:1240, 1249, 1276, 1304-1305, 1307-1312, 1314-1317, 1319, 1328-1330
- Kits #51 Item page with sidebar: kit.css:215, 1243-1250; aw.css:416
- Kits #52 Contents list: kit.css:1318-1323
- Kits #53 FAQ accordion: kit.css:495-510, 513-522, 533-540; aw.css:445
- AW #1 News card: aw.css:209-211, 218-229, 457, 462, 530-534, 535, 582, 613, 981, 983-987, 993, 1024-1026, 1035; campaign-lab.css:520-521; campaign-record.css:163-174, 177-180; campaign.css:786-787
- AW #2 Library row: aw.css:51-58, 204-206, 232-233, 237-242, 242, 474, 972-975, 1191-1192, 1435
- AW #3 Resource card: aw.css:245-247, 252-254, 257-263, 1224, 1357, 1366, 1373
- AW #4 Opportunity card: aw.css:65-68, 284-285, 1526-1528, 1591-1593; campaign-home.css:22-24, 37; campaign-lab.css:109, 113, 188, 243-245, 389-391, 494-496, 513; campaign.css:174, 178, 283-286, 289, 374, 421-423, 618-620, 760-762, 779
- AW #5 Organisation card: aw.css:98-99, 130, 266-267, 270-274, 281, 374-375, 406, 486-489, 492-493, 500-501, 561-570, 574-576, 595, 644, 649-651, 668-669, 717-726, 732, 1140, 1350, 1443-1444, 1446-1451, 1457-1464; dir-map.css:2, 16-18, 22
- AW #6 Organisation page header: aw.css:702-708, 712-714, 729-731, 734-739, 958-961
- AW #7 Organisation details: aw.css:391-393, 490, 710, 740-758, 763-766, 770-773, 775-782, 1484
- AW #8 Directory search: aw.css:108-112, 114, 116-117, 119-122, 126-127, 131, 135-136, 141-142, 150-155, 951, 1104-1107, 1453-1455, 1465, 1502; campaign.css:261
- AW #9 Find help near you: aw.css:157-158, 160, 162-164
- AW #10 Press item: kit.css:1174; aw.css:297-304, 333, 458-461, 463, 609, 620, 966-967, 970, 982, 996-1008
- AW #11 Hub page frame: aw.css:29-30, 115, 201, 504-507, 588, 989-992, 1021-1023
- AW #12 Search results: aw.css:509-518, 542-545
- AW #13 Helplines: aw.css:26, 35-48, 95
- AW #14 Home sections (Animal Writes): aw.css:8-10, 12-18, 31-32, 69-71, 82, 90-94, 181-191, 1048-1055; campaign-record.css:186, 205
- AW #15 Volunteer banner and form: aw.css:522-527
- AW #16 Forms (Animal Writes): aw.css:72-74, 430-431, 467-471, 577, 688-690, 693-696, 784-806, 808-836, 850-851, 855-867, 870, 874, 877-881, 886-903, 909-918, 920-936, 945-950, 952-955, 1060-1068, 1074-1075, 1082, 1087-1090, 1094-1100, 1116, 1118
- AW #17 Campaign page: aw.css:77-81, 292-294; campaign-home.css:6-7, 10; campaign-lab.css:5-32, 69-80, 120-130, 144-146, 159-173, 199, 203, 208, 483, 532-533, 559, 570, 861; campaign-record.css:23-37, 52-64, 90, 96-98, 113-122, 124, 131, 135, 141, 159, 187-195, 201, 215-219, 225-230, 234-239, 244, 251, 256-258, 266-277, 289-294; campaign.css:4-31, 115-119, 129-140, 185-196, 207-209, 226-240, 300, 304, 309, 356-357, 749, 798-799, 847-848, 956
- AW #18 Related and also-reported: aw.css:249, 306-313, 571-572, 1217-1218
- AW #19 Item page rows: kit.css:863; aw.css:317, 327-330, 344, 347, 352-357, 359-361, 376-379, 381-382, 384-386, 388-389, 407, 412-414, 450, 456
- AW #20 Related items (item pages): aw.css:497-499
- AW #21 Category index: aw.css:477-483
- AW #22 Data sources: aw.css:549-553, 556-557, 759-760, 968, 971, 1110-1112, 1142-1184
- AW #23 Announcement text: aw.css:538
- AW #26 Finder band: aw.css:22-23
- AW #27 Helpers: aw.css:661, 1227, 1232
- AW #30 Hub pictures: kit.css:217, 241, 248-250, 252, 255, 267-268
- AW #31 List filters: kit.css:214, 221-222, 226, 236-238, 842; aw.css:662
- AW #34 Place: aw.css:963-965
- AW #35 Opportunity buttons: kit.css:1191; aw.css:287, 646
- AW #36 Organisation logo: aw.css:268-269, 281, 363, 1186
- AW #37 Badges and labels: aw.css:276, 652
- AW #38 Contact icons: aw.css:147, 670
- AW #39 Organisation buttons: aw.css:278-280, 281
- AW #42 Posted by: aw.css:334, 976
- AW #44 Is this your organisation?: aw.css:691
- AW #46 India Writes for Its Community Dogs: aw.css:1840-1848; campaign-record-home.css:5-37; campaign-record.css:9-20, 146-148, 151-153, 162, 176, 184-185, 197-200, 202, 206-214, 233, 240-243, 246-250, 255, 260-262, 287-288, 297-347
- AW #48 Find help: aw.css:174, 176-178, 1771-1772, 1774-1777, 1780-1791
- AW #49 AW Content: aw.css:1557-1564, 1566, 1568-1570, 1581-1582, 1594
- AW #50 Supreme Court campaign card: aw.css:1579-1580, 1583-1590; campaign.css:241-251
- AW #51 Campaign cards row: aw.css:1601-1606
- AW #52 Need help now: aw.css:1713-1736, 1739, 1741-1753, 1755-1761, 1763
- AW #56 Take part cards: aw.css:1617-1689
- AW #58 AW_Festive: aw.css:1858-1862, 1864; campaign-lab.css:41-52, 67; campaign.css:39-50, 62, 89-90, 99, 106, 109-110, 127, 853-854
- AW #59 Volunteer banner: aw.css:426-429, 674-683, 1069-1072, 1078, 1083
- AW #60 What's happening: aw.css:590-594, 596-598, 605-607, 611-612, 614-618, 622-640, 664-665, 1198-1207, 1358-1365, 1390-1391, 1394-1398, 1405, 1416, 1499, 1815, 1819-1822, 1829
- AW #61 Local card: aw.css:1011-1014
- AW #62 Sponsored Resource: kit.css:1175; aw.css:1123-1128, 1225
- AW #63 Sponsored banner: aw.css:1129-1137
- AW #64 Date with calendar icon: kit-primitives.css:50; aw.css:319, 491, 1230; campaign-lab.css:114, 226, 734, 862; campaign-record.css:82, 123; campaign.css:179, 404, 472, 849, 957
- AW #65 Campaign tools: aw.css:291, 655, 1520-1522, 1535, 1543; campaign-home.css:8-9, 11-14, 25-28, 32, 36, 38, 42-44; campaign-lab.css:34-36, 38-39, 54-55, 58-65, 96-97, 102-103, 105-108, 110-112, 116-118, 132-141, 149, 152-156, 184-187, 190-198, 200-202, 204-205, 209-225, 227-232, 236-242, 246-271, 273-286, 288-314, 316-329, 331-386, 394-434, 436-440, 447-482, 486, 497-500, 505, 511, 514, 524-529, 536-542, 545-550, 560, 566-569, 584-605, 607-635, 643-732, 735-808, 813-815, 821-845, 847-850, 863-866, 868-871, 875-876, 878-882, 889, 893-904; campaign-record.css:65-76, 81, 89, 93-94, 128, 149, 157-158, 220, 222-223, 283-284; campaign.css:33-35, 37, 52-53, 56-60, 68-84, 103, 107, 144-146, 161-162, 167-168, 170-173, 175-177, 181-183, 198-204, 212-220, 278-282, 288, 291-299, 301-303, 305-306, 310-326, 329-352, 362-373, 378-403, 405-410, 414-420, 424-449, 451-470, 473-498, 500-525, 527-540, 542-615, 623-624, 627-644, 649, 653-663, 666-713, 715-748, 752, 763-766, 771, 777, 780, 790-795, 802-811, 814-846, 863-924, 929-931, 937-947, 958-961, 963-966, 970-971, 973-977, 984, 988-999
- AW #67 Head band (breadcrumb, H1, intro, count, filters): kit.css:210-213, 216, 218, 227-228, 232, 234-235, 244-247, 258-266, 835-841, 943-945, 1232; aw.css:589, 658-660, 1473
- AW #69 About page bands: aw.css:1236-1239, 1241-1248, 1251-1275, 1277-1297, 1302-1303, 1306, 1313, 1318, 1323-1326, 1344
- AW #70 Adoption beta page: aw.css:1333-1338, 1340-1343, 1345
- AW #71 Live Google block: aw.css:1348-1349
- AW #73 Item: meta row: aw.css:318, 320, 322-324, 394
- AW #74 Item: sources: aw.css:331-332, 335-341, 409-410
- AW #75 Item: tags: aw.css:343
- AW #77 Item: more: aw.css:159, 348-351, 442, 448-449
- AW #79 Listing: logo and description: aw.css:366, 368-373
- AW #80 Listing: registrations: aw.css:387
- AW #81 Listing: contact: aw.css:395-398
- AW #83 Listing: services: aw.css:390
- AW #85 Listing: online profiles: aw.css:383, 400-402, 404-405
- AW #88 Current campaign: aw.css:417-425
- AW #89 Listing: activity: aw.css:433-441
- AW #92 Listing: campaigns: aw.css:358
- AW #93 Claim your listing: aw.css:452-455
- AW #94 Term page: FAQ: aw.css:443-444
- AW #96 Term page: related: aw.css:447
- AW #97 Item: FAQ: aw.css:446
- AW #98 Directory map and Near me: aw.css:132; dir-map.css:3-15, 19-21
- AW #99 Home: hero: aw.css:1523-1525, 1529-1532, 1536-1538, 1544-1551; campaign-home.css:15-21, 29, 31, 33-35, 39-41; campaign-lab.css:487-493, 501, 503, 506-508, 515-517; campaign.css:753-759, 767, 769, 772-774, 781-783
- AW #65 campaign hero (`aw-camp-hero`): campaign-lab.css:82-95, 100, 104, 176-181, 189, 233-235; campaign-record.css:88, 91-92, 95, 102-109, 127, 129-130, 134, 138-140, 143; campaign.css:142, 151-160, 165, 169, 255-256, 264-277, 290, 411-413
- Base (no part): kit-primitives.css:7-8, 23-30; kit-tokens.css:2-233; kit.css:10-22, 27-43, 45-46, 48, 243, 264, 271, 561-562, 569-572, 671, 830, 851-862, 950-960, 1012, 1116-1117, 1236, 1302-1303, 1305-1309; kits-v42.css:2-74; kits.css:22-25; aw.css:170, 464, 1030-1033, 1472, 1475, 1491; tokens.css:2-8

</details>
