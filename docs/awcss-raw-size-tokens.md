# Raw sizes in aw.css phase 2: token names

Names for the 102 literal sizes listed in [awcss-phase2-plan.md](awcss-phase2-plan.md) (section "Raw sizes"). Documentation only: it changes no CSS. Values are today's values; nothing changes visually. The names are applied in a later commit of the phase 2 build, after approval.

## Rules

1. **A token is named only when the size is used in 2 or more places** (two rules or components, not two properties of one rule), **or when a site would set its own value.** One-off sizes stay literal, or become a variable local to their component (`--_name`, set and used inside the one rule).
2. Two sizes with the same value but a different meaning do not share a token (a shared name would tie two unrelated parts together). They are listed under "Same value, kept apart".
3. Sizes are written in rem. A px value that stays literal is shown in its rem form (16px root). Hairlines (1px, 2px borders) and `vh`, `vw`, `%`, `ch`, `em` values stay as they are.
4. Names follow the existing patterns: `--kit-<group>-<name>` (generic, in `kit-tokens.css`) and `--aw-<component>-<part>` (brand, in aw.css `:root`); steps are `sm / md / lg`, a wider-screen value is the plain name plus `-lg`.

## Tokens to add (11)

| token | where | value | rows (plan idx) | used in |
|---|---|---|---|---|
| `--aw-field-ico-w` | aw.css `:root` | `1.25rem` | 121, 665, 666, 700 | icon slot in the Home search select and the Fluent Forms prefixed field and role cards |
| `--aw-ff-prefix-left` | aw.css `:root` | `calc(var(--s-3) + 1.25rem + var(--s-2))` | 665, 666 | Fluent Forms prefix position and the input padding that clears it |
| `--kit-news-media-max` | Kits `kit-tokens.css` | `40%` | 988, 1024, 1261 | Home Learn, News and Law mosaics |
| `--kit-news-media-w` | Kits `kit-tokens.css` | `10rem` | 1020, 1261 | Home News and Law mosaics ≥1200 |
| `--kit-radius-round` | Kits `kit-tokens.css` | `50%` | 1034, 1039, 1115 | directory filter toggle and its dot, status dot (circles) |
| `--kit-type-label-sm` | Kits `kit-tokens.css` | `.8125rem` | 1052, 1058 | directory search label, directory search check |
| `--kit-type-ui` | Kits `kit-tokens.css` | `.875rem` | 1054, 1056 | buttons, section description (equals `--kit-field-text-size`, which can alias it) |
| `--kit-card-stack-media-w` | Kits `kit-tokens.css` | `30%` | 1059, 1064 | wide stacked card picture (width, max-width, clamp) |
| `--aw-camp-art-h-sm` | aw.css `:root` | `50vw` | 1085, 1086 | campaign band art height and the padding under it (phones) |
| `--aw-camp-art-w` | aw.css `:root` | `45%` | 1091, 1092 | campaign band art width and the text column beside it (≥768) |
| `--aw-camp-art-w-lg` | aw.css `:root` | `50%` | 1094, 1095 | the same at ≥1200 |

Already tokens, no new name: rows 1057 (`--kit-type-sm`, `--kit-type-xs`, `--kit-meta-size` at 768) and 1062 (`--kit-card-min`, `--kit-card-media-max`, `--kit-thumb-min`, `--kit-thumb-max`, `--kit-body-min`: they move to kit-tokens.css; aw.css keeps its 18rem for `--kit-card-min`). Reuse of existing tokens: rows 265 and 266 use `--kit-space-8` (4rem) and `--kit-space-9` (6rem), identical values.

## Every row

`token` = uses a token above. `literal` = stays a literal. `local` = literal, or a `--_` variable inside its one rule.

### aw-directory.css (11)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 21 | `.aw-helplines` | grid-template-columns | `repeat(auto-fill, minmax(min(17rem, 100%), 1fr))` | literal (17rem stays (decision 2)) |
| 82 | `.aw-dir-search` (min-width: 1200px) | grid-template-columns | `minmax(9rem, 2fr) repeat(3, minmax(11.5rem, 1fr)) auto` | literal |
| 1034 | `.aw-dir-filter-toggle` | top | `50%` | literal (centring) |
| 1034 | `.aw-dir-filter-toggle` | border-radius | `50%` | token `--kit-radius-round` |
| 1039 | `.aw-dir-filter-toggle.has-filters::after` | top | `9px` | local `.5625rem` |
| 1039 | `.aw-dir-filter-toggle.has-filters::after` | right | `9px` | local `.5625rem` |
| 1039 | `.aw-dir-filter-toggle.has-filters::after` | width | `8px` | local `.5rem` |
| 1039 | `.aw-dir-filter-toggle.has-filters::after` | height | `8px` | local `.5rem` |
| 1039 | `.aw-dir-filter-toggle.has-filters::after` | border-radius | `50%` | token `--kit-radius-round` |
| 1052 | `.aw-dir-search .aw-dir-more-body .aw-dir-check` (min-width: 1200px) | font-size | `.8125rem` | token `--kit-type-label-sm` |
| 1058 | `.aw-dir-search .kit-field > label` (min-width: 768px) | font-size | `.8125rem` | token `--kit-type-label-sm` |

### aw-single.css (4)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 33 | `.aw-pdf .wp-block-file__embed` | height | `min(80vh, 900px)` | literal `min(80vh, 56.25rem)` |
| 39 | `.aw-pdf .wp-block-file__embed` (max-width: 767px) | height | `60vh` | literal |
| 306 | `.aw-more-card .aw-more-logo img` | max-width | `60%` | literal |
| 306 | `.aw-more-card .aw-more-logo img` | max-height | `70%` | literal |

### aw-home.css (14)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 121 | `.aw-ed-search select` | padding | `0 calc(var(--kit-space-3) + 1.25rem) 0 var(--kit-space-3)` | token `--aw-field-ico-w` (the 1.25rem inside the calc) |
| 988 | `.aw-ed-learn .aw-tasks.is-three .kit-card > .kit-card-media` @container aw-guides (max-width: 911px) | max-width | `40%` | token `--kit-news-media-max` |
| 1010 | `.aw-news-mosaic > li:first-child .kit-card-media` (min-width: 768px) | min-height | `8rem` | literal |
| 1020 | `.aw-ed-news .aw-news-mosaic .kit-card--stacked-md > .kit-car` (min-width: 1200px) | width | `10rem !important` | token `--kit-news-media-w` |
| 1024 | `:is(.aw-ed-news, .aw-ed-law) .aw-news-mosaic > li:first-chil` (max-width: 767px) | max-width | `40%` | token `--kit-news-media-max` |
| 1086 | `.aw-ed-hero--campaign .aw-camp-band-art` (max-width: 767px) | height | `50vw` | token `--aw-camp-art-h-sm` |
| 1092 | `.aw-ed-hero--campaign .aw-camp-band-art` (min-width: 768px) | inset | `50% 0 auto auto` | literal (centring) |
| 1092 | `.aw-ed-hero--campaign .aw-camp-band-art` (min-width: 768px) | width | `45%` | token `--aw-camp-art-w` (text column = 100% - art width - grid gap (same result as today)) |
| 1095 | `.aw-ed-hero--campaign .aw-camp-band-art` (min-width: 1200px) | width | `50%` | token `--aw-camp-art-w-lg` (text column = 100% - art width - grid gap (same result as today)) |
| 1213 | `.aw-bento--sos p:not(.aw-bento-eyebrow)` | max-width | `34ch` | literal |
| 1233 | `.aw-findhelp-form` @container (min-width: 56rem) | grid-template-columns | `minmax(0, 1fr) minmax(12rem, 16rem) auto auto` | literal |
| 1245 | `.aw-ed-law > .kit-stack > .kit-tile::after` (min-width: 1200px) | margin-top | `0.625rem` | literal |
| 1261 | `.aw-ed-law .aw-news-mosaic .kit-card--stacked-md > .kit-card` (min-width: 1200px) | width | `10rem !important` | token `--kit-news-media-w` |
| 1261 | `.aw-ed-law .aw-news-mosaic .kit-card--stacked-md > .kit-card` (min-width: 1200px) | max-width | `40%` | token `--kit-news-media-max` |

### aw.css (14)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 122 | `.aw-ed-mosaic` | grid-auto-rows | `9rem` | literal |
| 126 | `.aw-ed-mosaic` (min-width: 1200px) | grid-auto-rows | `8.5rem` | literal |
| 781 | `.aw-ed-mosaic` (min-width: 768px) | grid-auto-rows | `10rem` | literal |
| 1075 | `.aw-camp-band.aw-ed-hero--campaign h1.aw-camp-band-title` | max-width | `18ch` | literal |
| 1085 | `.aw-camp-band.aw-ed-hero--campaign.has-art` (max-width: 767px) | padding-bottom | `calc(50vw + var(--kit-space-section-end))` | token `--aw-camp-art-h-sm` |
| 1091 | `.aw-camp-band.aw-ed-hero--campaign.has-art .aw-camp-band-tex` (min-width: 768px) | max-width | `calc(55% - var(--kit-space-grid))` | token `--aw-camp-art-w` (text column = 100% - art width - grid gap (same result as today)) |
| 1094 | `.aw-camp-band.aw-ed-hero--campaign.has-art .aw-camp-band-tex` (min-width: 1200px) | max-width | `calc(50% - var(--kit-space-grid))` | token `--aw-camp-art-w-lg` (text column = 100% - art width - grid gap (same result as today)) |
| 1115 | `.aw-sc-status::before` | width | `.5em` | literal |
| 1115 | `.aw-sc-status::before` | height | `.5em` | literal |
| 1115 | `.aw-sc-status::before` | margin-right | `.45em` | literal |
| 1115 | `.aw-sc-status::before` | border-radius | `50%` | token `--kit-radius-round` |
| 1116 | `.aw-sc h2 mark` | padding | `0 .2em` | literal |
| 1116 | `.aw-sc h2 mark` | margin | `0 -.2em` | literal |
| 1117 | `.aw-sc-line` | max-width | `40ch` | literal |

### aw-hubs.css (3)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 203 | `.aw-camp-next p` | max-width | `52ch` | literal |
| 380 | `.aw-hub` | grid-template-columns | `repeat(auto-fill, minmax(min(100%, 20rem), 1fr))` | literal |
| 880 | `.aw-hub-library .aw-cases` | grid-template-columns | `repeat(auto-fill, minmax(min(100%, 32rem), 1fr))` | literal |

### aw-listing.css (7)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 265 | `.aw-listing-about.has-logo` | grid-template-columns | `4rem minmax(0, 1fr)` | token `--kit-space-8 (exists)` (4rem exactly) |
| 266 | `.aw-listing-about.has-logo` (min-width: 768px) | grid-template-columns | `6rem minmax(0, 1fr)` | token `--kit-space-9 (exists)` (6rem exactly) |
| 275 | `.aw-facts-row` (min-width: 768px) | grid-template-columns | `12rem minmax(0, 1fr)` | literal |
| 417 | `.aw-datasrcs.is-full .kit-tag` | min-width | `3.5rem` | literal |
| 729 | `#claim > summary::before, #report > summary::before, #volunt` | width | `1.125em` | literal |
| 729 | `#claim > summary::before, #report > summary::before, #volunt` | height | `1.125em` | literal |
| 784 | `.aw-claim-find` | max-width | `36rem` | literal |

### aw-volunteer.css (3)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 319 | `.kit-sidebar .aw-vol-banner-photo img` | height | `140px` | literal `8.75rem` |
| 500 | `.aw-vol-banner-photo img` | height | `120px` | literal `7.5rem` |
| 505 | `.aw-vol-banner-photo img` (min-width: 768px) | min-height | `320px` | literal `20rem` |

### aw-meta.css (1)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 347 | `.aw-card-time` | min-height | `36px` | literal `2.25rem` (value for value, see Decisions) |

### aw-lists.css (1)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 382 | `.aw-hub-group ul` | padding-inline-start | `1.1em` | literal |

### aw-search.css (1)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 410 | `.aw-search-results mark` | padding | `0 .1em` | literal |

### aw-contact.css (1)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 423 | `.aw-contact-org, .aw-contact-report` | max-width | `70ch` | literal |

### Kits (`kit-` class) (9)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 621 | `.aw-nl-band p.aw-nl-band-line` | max-width | `46ch` | literal |
| 735 | `.aw-src-icon:not(.aw-src-icon--letter), .aw-datasrc-logo` | padding | `3px !important` | literal `.1875rem !important` |
| 755 | `.aw-src.aw-src` | min-height | `1.75rem` | literal |
| 1054 | `.kit-btn, .wp-block-button__link, .fluentform .ff-btn.kit-bt` | font-size | `.875rem` | token `--kit-type-ui` |
| 1056 | `.kit-section-desc` | font-size | `.875rem` | token `--kit-type-ui` |
| 1059 | `.kit-card--stacked-lg > .kit-card-media` | width | `30% !important` | token `--kit-card-stack-media-w` (the 30% inside the clamp too) |
| 1059 | `.kit-card--stacked-lg > .kit-card-media` | max-width | `30%` | token `--kit-card-stack-media-w` (the 30% inside the clamp too) |
| 1064 | `.kit-card--stacked-lg > .kit-card-media` | width | `clamp(var(--kit-thumb-min), 30%, var(--kit-thumb-max)) !important` | token `--kit-card-stack-media-w` (the 30% inside the clamp too) |
| 1067 | `.kit-card--stacked-lg` | gap | `clamp(var(--kit-space-3), 5vw, var(--kit-space-5)) !important` | literal |

### aw-forms.css (24)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 661 | `.aw-form-ff .ff-el-group.kit-field .ff-el-input--content tex` | min-height | `calc(5 * 1.5em + 2 * var(--s-3))` | literal |
| 665 | `.aw-form-ff .kit-field-control.aw-ff-prefixed > .aw-ff-prefi` | left | `calc(var(--s-3) + 1.25rem + var(--s-2))` | token `--aw-ff-prefix-left` (whole calc named; 666 adds var(--aw-ff-prefix, 4ch)) |
| 665 | `.aw-form-ff .kit-field-control.aw-ff-prefixed > .aw-ff-prefi` | top | `50%` | literal (centring) |
| 666 | `.aw-form-ff .kit-field .kit-field-control.aw-ff-prefixed:has` | padding-left | `calc(var(--s-3) + 1.25rem + var(--s-2) + var(--aw-ff-prefix, 4ch))` | token `--aw-ff-prefix-left` (whole calc named; 666 adds var(--aw-ff-prefix, 4ch)) |
| 673 | `.aw-form-ff input[type=file]::file-selector-button` | min-height | `2rem` | literal |
| 691 | `.aw-form-ff .aw-vol-role.kit-checkbox` | min-height | `7.5rem` | literal |
| 692 | `.aw-form-ff .aw-ff-opt-ico` | width | `1.125rem` | literal |
| 692 | `.aw-form-ff .aw-ff-opt-ico` | height | `1.125rem` | literal |
| 693 | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` | width | `1.75rem` | literal |
| 693 | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` | height | `1.75rem` | literal |
| 694 | `.aw-form-ff .aw-vol-role.kit-checkbox input, .aw-form-ff .ki` | margin | `-1px` | literal (1px hairline stays px) |
| 695 | `.aw-form-ff .aw-vol-role.kit-checkbox::after` | width | `1.25rem` | literal |
| 695 | `.aw-form-ff .aw-vol-role.kit-checkbox::after` | height | `1.25rem` | literal |
| 698 | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` | top | `calc(var(--kit-space-3) + 0.1875rem)` | literal |
| 698 | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` | right | `calc(var(--kit-space-3) + 0.1875rem)` | literal |
| 698 | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` | width | `0.875rem` | literal |
| 698 | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` | height | `0.875rem` | literal |
| 700 | `.aw-form-ff .aw-vol-role.kit-checkbox` (max-width: 767px) | min-height | `3.5rem` | literal |
| 700 | `.aw-form-ff .aw-vol-role.kit-checkbox` (max-width: 767px) | padding | `var(--kit-space-2) calc(var(--kit-space-3) * 2 + 1.25rem) var(--kit-sp` | token `--aw-field-ico-w` (the 1.25rem inside the calc) |
| 701 | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` (max-width: 767px) | width | `1.5rem` | literal |
| 701 | `.aw-form-ff .aw-vol-role .aw-ff-opt-ico` (max-width: 767px) | height | `1.5rem` | literal |
| 702 | `.aw-form-ff .aw-vol-role.kit-checkbox::after` (max-width: 767px) | top | `50%` | literal (centring) |
| 703 | `.aw-form-ff .aw-vol-role.kit-checkbox:has(input:checked)::be` (max-width: 767px) | top | `50%` | literal (centring) |
| 716 | `.aw-form-ff .kit-chips .kit-checkbox:has(input:checked)` | padding-inline | `calc(var(--kit-space-4) - 2px + var(--kit-border-control))` | literal (the 2px is a border width, stays px) |

### aw-type-ico.css (1)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 838 | `.aw-type-ico` | margin-right | `.35em` | literal |

### tokens (8)

| idx | selector | property | value today | result |
|---|---|---|---|---|
| 1057 | `:root` (min-width: 768px) | --kit-type-sm | `clamp(.75rem, .7273rem + .0909vw, .8125rem)` | existing token |
| 1057 | `:root` (min-width: 768px) | --kit-type-xs | `clamp(.6875rem, .6591rem + .1136vw, .75rem)` | existing token |
| 1057 | `:root` (min-width: 768px) | --kit-meta-size | `.8125rem` | existing token |
| 1062 | `:root` | --kit-card-min | `18rem` | existing token |
| 1062 | `:root` | --kit-card-media-max | `22.5rem` | existing token |
| 1062 | `:root` | --kit-thumb-min | `4.5rem` | existing token |
| 1062 | `:root` | --kit-thumb-max | `15rem` | existing token |
| 1062 | `:root` | --kit-body-min | `10rem` | existing token |

## Count

- 102 rows in all: 27 use a new token or an existing-token reuse, 8 are already tokens, **67 keep a literal** (listed per file above under `literal` / `local`).
- Of the 67 literals: 5 are `50%` centring (rows 1034 top, 665 top, 702, 703, 1092 inset); 6 are one-off measures in `ch` (rows 203, 423, 621, 1075, 1117, 1213); the rest are single-place sizes. A measure token (`--kit-measure-xs` … `-xl`, next to `--kit-measure` 68ch) is the one group a site would plausibly set itself; none is added until a second place uses it.

## Same value, kept apart

Equal values with a different meaning keep their own literal (rule 2).

| value | rows | not shared with |
|---|---|---|
| `10rem` | 781 (mosaic row) | `--kit-news-media-w` (1020, 1261), `--kit-body-min` |
| `12rem` | 275 (facts label column), 1233 (find-help select min) | each other |
| `9rem` | 82 (directory search first column), 122 (mosaic row) | each other |
| `3.5rem` | 417 (data source tag), 700 (role card, phones) | each other, `--kit-thumb-sm` (56px, px) |
| `7.5rem` | 500 (banner photo, from 120px), 691 (role card) | each other |
| `1.75rem` | 693 (role icon), 755 (source line) | each other |
| `1.25rem` | 695 (role card ring) | `--aw-field-ico-w` |
| `.1875rem` | 698 (check inset), 735 (source icon padding, from 3px) | each other |
| `1.125rem` / `1.125em` | 692 / 729 | each other (rem vs em) |
| `1.5rem` | 701 | `--kit-size-check` (1.5rem, a checkbox) |
| `.875rem` | 698 (check size) | `--kit-type-ui` (a font size) |

## Decisions (10 Nov 2026)

1. Sizes are in rem: px values that stay literal are shown in rem form above.
2. Row 21: the helplines grid keeps 17rem (the Kits default for `--kit-card-min`, while aw.css sets 18rem). It stays a literal.
3. `--kit-measure-sm` and `--aw-sc-line` do not clash: no change.
4. `70ch` (row 423) stays apart from `--kit-measure` 68ch.
5. Row 347 (`.aw-card-time` min-height, 36px = 2.25rem) stays a literal, value for value. Not a target; 44 px applies to links and controls.
6. The campaign band text column is derived from the art width (100% − art width − grid gap): the result is the same as today.
7. Remaining notes (rem and px twins, 1062 `--kit-card-min` split) need no action.
