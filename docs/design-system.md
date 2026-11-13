# Animal Writes design system

How the 2.0 site looks and behaves. No component hardcodes a colour, size, radius or duration.

**Current and history (24 Oct 2026).** The colour rule and the breakpoints below are current. The Product systems, Scales, States vocabulary and Components sections are the first version (5 to 7 Oct 2026: `--aw-*` tokens, `aw-*` classes, the old section names Legal Watch, Knowledge Centre, Research Library and Campaign Hub), kept as history; several values have changed since. Current components: [component-map.md](component-map.md) (generated), [buttons.md](buttons.md), [card-sizes.md](card-sizes.md), [item-template.md](item-template.md), [listing-template.md](listing-template.md) and the states sheet (/kits-demo/).

**Source of truth:** the Kits master's `themes/kits-by-wugweb/tokens/tokens.json` → `python3 tools/build-tokens.py` → `assets/css/kit-tokens.css` (`--kit-*`); Animal Writes overrides a few brand values in `themes/animal-writes/tokens/tokens.json` → `assets/css/tokens.css`. Components: Kits `kit.css` and `kit-primitives.css`, and Animal Writes `aw.css`.
Tokens follow the responsive token contract: every token is an `{sm, md, lg}` triple, `sm` is the mobile base, `md`/`lg` are `min-width` overrides at 768px and 1200px. `=` below means "same as sm".

To change the look, change a token and rebuild. Example: rounder cards = edit `radius-card`, run the build, deploy.

## Layers

| Layer | Where | Example |
|---|---|---|
| Primitive | `tokens.json` → `$primitives` | `marigold #ffa500`, `kraft #efe4cf` (never used by components directly) |
| Semantic | `tokens.json` → `tokens` | `color-accent → {color.marigold}`, `radius-card`, `motion-base` |
| Component | `aw.css` | `.aw-card` uses `radius-card`, `elevation-1→2`, `motion-base`, `motion-lift` |

WordPress presets (`theme.json` palette, font sizes, spacing) remain for the block editor; `settings.custom.radius` maps to the same `--aw-radius-*` variables so editor and front end agree.

## Colour rule
**Wugweb yellow `#ffbe1a`** is the only accent and is used for **fills only**: primary buttons, the most urgent action, badges, rules and highlights (always with ink text, 11:1). **Links and text accents are blue `#0062b8`** (6.1:1 on white, 5.5:1 on canvas, 4.9:1 on kraft); the focus ring is blue on light surfaces and yellow on dark ones. On dark surfaces links are white.

## Product systems
One generic archive template is replaced by four systems, each built for what people do with the content (`plugins/animal-writes-core/includes/systems.php`, styles in `aw.css` section 10):

| System | Pages | View |
|---|---|---|
| Help | Directory, Knowledge Centre, Resources | Scannable organisation rows (mark, area, verification state, type, Call, View); guides as tasks (photo, icon, updated date, reading time); resources by format and language |
| Intelligence | Legal Watch, News, Research Library | Case register (large date, issuer badge by court or body, topic, summary, source, document flag, issuer filters); news with a lead story, side stories and grid, topic filters; document library (kind, year, issuer, Read / PDF) |
| Action | Opportunities, Get involved | Opportunity cards with type, place, organisation, checked date, Apply and Details; type filters |
| Archive | Campaign Hub | The 1.0 documentary, other campaigns, an invitation to bring a campaign |
| Trust | Every news, legal, research, opportunity and listing page | The item's Sources block and meta row (item-head.php): issuer, source link, published and added dates; for listings, verification state and AWBI recognition. (The old `[aw_trust]` panel was in no page and was removed on 25 Oct 2026.) |

Directory order: verified, then A–Z. No featured listings (owner, 24 Oct 2026): no organisation is ranked above others for any other reason.

## Scales

### Colour
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-color-bg` | #ffffff | = | = |
| `--aw-color-surface` | #efe4cf | = | = |
| `--aw-color-surface-soft` | #faf6ee | = | = |
| `--aw-color-raised` | #ffffff | = | = |
| `--aw-color-ink` | #141414 | = | = |
| `--aw-color-text` | #1e1e1e | = | = |
| `--aw-color-muted` | #5f5f5f | = | = |
| `--aw-color-line` | #e4ddd0 | = | = |
| `--aw-color-accent` | #ffa500 | = | = |
| `--aw-color-accent-ink` | #9a5b00 | = | = |
| `--aw-color-on-accent` | #141414 | = | = |
| `--aw-color-on-ink` | #ffffff | = | = |
| `--aw-color-danger` | #c8102e | = | = |
| `--aw-color-danger-soft` | #fff2f4 | = | = |
| `--aw-color-success` | #137a3b | = | = |
| `--aw-color-success-soft` | #eef8f1 | = | = |
| `--aw-color-focus` | #ffa500 | = | = |
| `--aw-color-whatsapp` | #1f7a44 | = | = |

Contrast (WCAG 2.1 AA, checked): text `#1e1e1e` on white 16.7:1, on kraft 13.2:1; muted `#5f5f5f` on white 6.4:1, on kraft 5.1:1; accent-ink `#9a5b00` on white 5.4:1 but on kraft only 4.3:1, so it is used on white only (on kraft use ink). Marigold `#ffa500` is never used for text on light backgrounds; it is a fill (buttons with ink text, 9.3:1) and the focus ring.

### Type
One family: **Inter** (variable, self-hosted woff2, declared once in Kits by Wugweb's theme.json). Hierarchy comes from size, weight and tracking, never from a second typeface. Display sizes are meant to set in Inter Display (Inter's optical size for large text, `font-optical-sizing: auto`): that needs the Inter file with the opsz axis; the file shipped today has the weight axis only (13 Nov 2026).

Display is a size style, not a heading level (as in Material's type scale): a hero's title is the page's `<h1>` in the Display style; the page then has no second `<h1>`. Below it come H2 and H3.

**One display weight:** `--kit-weight-display` (800) is the weight of every display size: hero titles (`.kit-hero-title`, `.kit-display`), campaign titles and the big numbers (countdowns, tallies, bento numbers, the error page's code). The Lab rules and Community dogs pages keep their own frozen copies.

**Headings h1–h6 read one set of tokens each**, the same in theme.json (`styles.elements.h1`…`h6`: size preset, then `var(--kit-hN-weight)`, `var(--kit-hN-leading)`, `var(--kit-hN-tracking)`), in kit.css/aw.css and in the blocks (the Heading block takes the element styles of its level). Change a level in `themes/kits-by-wugweb/tokens/tokens.json` and run `python3 tools/build-tokens.py`; sizes change in Styles > Typography (the presets).

| Style | Used for | Size | Weight | Line height | Tracking |
|---|---|---|---|---|---|
| Display | Hero titles: the page's `<h1>` set large (`.kit-hero-title`, `.kit-display`, `.aw-camp-band-title`) | `--kit-type-display`: 1.5 × H1 on a 375px screen to 2 × H1 at 1200px, fluid (`clamp(3rem, 1.6364rem + 5.8182vw, 6rem)`; owner, 13 Nov 2026) | `--kit-weight-display` 800 | `--kit-leading-display` 1.06 | `--kit-tracking-display` −0.035em |
| H1 | Page and archive titles | `--kit-h1-size` (preset 4-x-large) | `--kit-h1-weight` 700 | `--kit-h1-leading` 1.06 | `--kit-h1-tracking` −0.035em |
| H2 | Section titles | `--kit-h2-size` (3-x-large) | `--kit-h2-weight` 700 | `--kit-h2-leading` 1.2 | `--kit-h2-tracking` −0.02em |
| H3 | Card titles (`--kit-type-card-title`: 20px, 22px from 1200, one size per breakpoint; owner, 13 Nov 2026), sub-sections | `--kit-h3-size` (2-x-large) | `--kit-h3-weight` 600 | `--kit-h3-leading` 1.2 | `--kit-h3-tracking` −0.02em |
| H4 | Tile titles, small headings, form legends | `--kit-h4-size` (x-large) | `--kit-h4-weight` 600 | `--kit-h4-leading` 1.2 | `--kit-h4-tracking` −0.01em |
| H5 | Small headings in the main column | `--kit-h5-size` (x-large) | `--kit-h5-weight` 600 | `--kit-h5-leading` 1.35 | `--kit-h5-tracking` −0.01em |
| H6 | Smallest heading | `--kit-h6-size` (x-large) | `--kit-h6-weight` 600 | `--kit-h6-leading` 1.2 | `--kit-h6-tracking` −0.01em |
| Lead | Section descriptions, intros (`.aw-lead`, large paragraphs) | `--kit-type-lead` | 400, muted | 1.5 | — |
| Body | Paragraphs, lists | `--kit-type-base` | 400 | `--kit-leading-body` 1.6 | `--kit-tracking-body` −0.006em |
| Small | Excerpts, meta, footer links | `--kit-type-sm` | 400–500 | 1.55 | — |
| Caption | Dates, counts, chips, footer headings | `--kit-type-xs` | 500–600 | — | — |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-type-xs` | 0.8125rem | = | = |
| `--aw-type-sm` | 0.875rem | = | = |
| `--aw-type-base` | 1rem | 1rem | 1.0625rem |
| `--aw-type-lead` | 1.0625rem | 1.125rem | 1.25rem |
| `--aw-type-h4` | 1.125rem | 1.2rem | 1.2rem |
| `--aw-type-h3` | 1.25rem | 1.4rem | 1.5rem |
| `--aw-type-h2` | 1.75rem | 2.35rem | 2.9rem |
| `--aw-type-h1` | 2.25rem | 3.5rem | 4.5rem |
| `--aw-type-display` | 3rem | 4.5rem | 5.5rem |
| `--aw-type-stat` | 2.5rem | 3.25rem | 3.75rem |
| `--aw-type-placard` | 1.25rem | 1.5rem | 1.75rem |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-leading-body` | 1.6 | = | = |
| `--aw-leading-heading` | 1.12 | = | = |
| `--aw-leading-display` | 1.02 | = | = |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-weight-regular` | 400 | = | = |
| `--aw-weight-strong` | 600 | = | = |

Body measure: `--aw-measure` 68ch. Headings use `leading-heading`; display and stat numbers use `leading-display`.

### Space (4px base)
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-space-1` | 0.25rem | = | = |
| `--aw-space-2` | 0.5rem | = | = |
| `--aw-space-3` | 0.75rem | = | = |
| `--aw-space-4` | 1rem | = | = |
| `--aw-space-5` | 1.5rem | = | = |
| `--aw-space-6` | 2rem | = | = |
| `--aw-space-7` | 3rem | = | = |
| `--aw-space-8` | 4rem | = | = |
| `--aw-space-9` | 6rem | = | = |
| `--aw-space-section` | 3rem | 4.5rem | 6rem |
| `--aw-space-gutter` | 1rem | 1.5rem | 2rem |
| `--aw-space-grid` | 1rem | 1.5rem | 1.75rem |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-width-content` | 720px | = | = |
| `--aw-width-wide` | 1200px | = | = |

### Radius
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-radius-control` | 10px | = | = |
| `--aw-radius-card` | 14px | 16px | 16px |
| `--aw-radius-media` | 12px | 16px | 16px |
| `--aw-radius-hero` | 18px | 24px | 24px |
| `--aw-radius-pill` | 999px | = | = |

Hierarchy: controls (inputs, buttons) are tightest, cards and media softer, the hero softest; chips, share buttons and pills are fully round. Logos sit in a circle.

### Elevation
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-elevation-1` | 0 1px 2px rgba(20,20,20,.06), 0 1px 1px rgba(20,20,20,.04) | = | = |
| `--aw-elevation-2` | 0 6px 20px rgba(20,20,20,.10) | = | = |
| `--aw-elevation-3` | 0 16px 40px rgba(20,20,20,.16) | = | = |
| `--aw-elevation-offset` | 8px 8px 0 var(--aw-color-accent) | 14px 14px 0 var(--aw-color-accent) | 14px 14px 0 var(--aw-color-accent) |

`elevation-1` every resting card, tile and filter panel (with a 1px `color-line` border); `elevation-2` hover and the state finder; `elevation-3` overlays. `elevation-offset` is the marigold block shadow behind the hero photo.

### Motion
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-motion-fast` | 120ms | = | = |
| `--aw-motion-base` | 200ms | = | = |
| `--aw-motion-slow` | 320ms | = | = |
| `--aw-motion-ease` | cubic-bezier(.2,.7,.2,1) | = | = |
| `--aw-motion-lift` | -3px | = | = |

`prefers-reduced-motion: reduce` turns every transition and animation off (`aw.css` section 7).

### Layers, sizes, layout
| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-z-sticky` | 50 | = | = |
| `--aw-z-header` | 40 | = | = |
| `--aw-z-overlay` | 100 | = | = |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-size-target` | 44px | = | = |
| `--aw-size-control` | 48px | = | = |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-border-control` | 2px | = | = |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-card-columns` | 1 | 2 | 3 |
| `--aw-card-media-ratio` | 16/9 | = | = |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-placard-ratio` | 16/7 | 16/9 | 16/9 |

| Token | sm (0) | md (768) | lg (1200) |
|---|---|---|---|
| `--aw-carousel-item` | 82% | auto | auto |

### Breakpoints
Breakpoints (Kits tokens.json): mobile designed and tested at 375px · md 768px · lg 1200px. Fluid type scales from 375 to 1200. No other breakpoints.

A component that must change layout at another width uses a container query (or an auto-fill/minmax grid) inside the component, never a new global media query. QA widths: 375, then either side of each breakpoint: 767 / 768 / 769 and 1199 / 1200 / 1201; also check 1024 as an extra width.

`sm` 0, `md` 768, `lg` 1200 are declared once in `tokens.json` → `$breakpoints`. Width media queries (CSS, inline PHP styles) and `matchMedia` in JS use only `min-width: 768px` / `max-width: 767px` and `min-width: 1200px` / `max-width: 1199px`; the deploy check `tools/ci/breakpoint_check.py` fails on any other width.

## States vocabulary

Every interactive component implements the states that apply to it, with the same visual language:

| State | Treatment |
|---|---|
| Default | As specified per component |
| Hover | Cards lift `motion-lift` with `elevation-2` and a 1.03 image zoom; buttons darken; links underline thickens; inputs get a soft surface ring |
| Focus-visible | 3–4px marigold ring (`color-focus`), offset 2–4px; never removed |
| Active / pressed | Buttons move down 1px and scale 0.99, shadow removed |
| Disabled | 55% opacity, `not-allowed` cursor, no hover |
| Loading | `.is-loading`: label hidden, spinner (`aw-spin`), `aria-busy="true"`, second click ignored |
| Selected / current | Nav: `aria-current="page"` with permanent underline |
| Empty | Query blocks: h2 "Nothing here yet", one sentence, and search |
| Error | Field: danger border + soft danger fill + message below (`.aw-field-error`, linked by `aria-describedby`); form: `.aw-form-summary` (`role="alert"`, focused, links to each field) |
| Success | Field: success border (`.is-valid`); form: `.aw-form-done` (`role="status"`) |
| Image failed / missing | Placard: the item's topic set in display type on kraft (`aw_placard_html`) |

## Components

Each entry: anatomy, variants, tokens, behaviour, where it is used.

### Button
- **Anatomy:** label; optional spinner.
- **Variants:** primary (marigold fill, ink text); outline (`is-style-outline`, ink border); on dark (`.aw-dark`, inverted).
- **Tokens:** `size-control` min height, `radius-control`, `border-control`, `weight-strong`, `motion-fast`.
- **States:** hover, focus-visible, active, disabled, loading.
- **Used:** core Button block everywhere; `.aw-form button[type=submit]`; finder; print.

### Card (recycled for news, legal, research, guides, opportunities, resources, listings)
- **Anatomy:** media (16:9 image, document cover, logo or placard) → topic chips → h2/h3 title link → excerpt → date (source date for ingested items).
- **Variants:** `aw-cards-3` grid; `aw-cards-lead` (first item spans two columns on `lg`); `.aw-listing-card` (round logo, area, verified badge); one column on `sm`, two from `md`, three from `lg`; no carousel (owner, 2 Nov 2026: no sideways scrolling rows anywhere).
- **Tokens:** `radius-card`, `radius-media`, `elevation-1/2`, `motion-base`, `motion-lift`, `card-columns`, `card-media-ratio`, `space-grid`.
- **Behaviour:** the title is the only link announced; the image link duplicates it, so it is `tabindex="-1"` and `aria-hidden`. Government PDFs show their first page as the image (`object-position: top`).
- **Used:** every archive (`templates/archive.html`, `archive-aw_listing.html`), homepage queries, search.

### Media
- **Anatomy:** one of: remote publisher image (`aw_source_image`), WordPress featured image, PDF first page (`aw_file_id` preview), logo (contain, circle), placard fallback.
- **Resolution order:** remote image → PDF first page → featured image → placard. Same order for cards, section visuals and the share (og:image) image.
- **Code:** `includes/media.php` (`aw_placard_html`, featured-image filter), `includes/sections.php` (`aw_section_tile`); share images in aw-seo-extras `includes/opengraph.php` and `includes/helpers.php`.

### Section header
- **Anatomy:** h1 → one-line description → count ("12 documents") → three-tile visual (photos, covers or placards; real assets only).
- **Tokens:** `color-surface` band, `space-section`, `radius-media`, `type-h1`.
- **Used:** `[aw_section_head]` at the top of every archive and directory/state page.

### Form field (shared by volunteer, receipt and claim forms)
- **Anatomy:** label (+ "(optional)") → hint → control → error slot.
- **Code:** `aw_field()` in `plugins/kits-core/includes/forms.php` (the site's own forms are Fluent Forms); client behaviour in `aw.js` section 4.
- **Tokens:** `size-control`, `radius-control`, `border-control`, `color-danger(-soft)`, `color-success`, `color-focus`.
- **Behaviour:** validates when a field loses focus, then live while it is invalid; on submit, an error summary is focused with links to each field. The server applies the same rules and returns per-field errors with what the person typed (`?aw_err=` token, 10 minutes), so the form works the same without JavaScript.
- **Rules:** required, email, phone (8–15 digits, optional +), https link, max length, file type and size.
- **Spam guard:** nonce, honeypot, 3-second minimum, per-connection hourly limit (`aw_form_guard`).

### Claim this listing
- **Where:** the listing's "Is this your organisation?" card links to `/claim/?listing=<ID>`, which shows the Fluent Forms form "Claim this listing" (#6; About you → What should change → logo and proof → "Send for review"). Claims are Fluent Forms entries.
- **Older claims** (before Fluent Forms) are private `aw_claim` posts, administrators only: the review shows Now against Proposed; **Approve** applies the fields and logo and sets `aw_verified` and `aw_verified_on` (today); **Reject** closes it.
- **Code:** `includes/form-blocks.php` (`[aw_claim_page]`), `includes/claims.php`, `includes/forms-fluentforms.php`.

### Upload list (campaign receipts)
- Take photo (camera), choose files, paste screenshots; per-file label; remove; client-side shrink to 1600px JPEG; inline errors in place of pop-ups. Server re-encodes images (strips EXIF/GPS), keeps files in `uploads/aw-private` (web access denied).

### Chips
Topic and type terms as pills (`radius-pill`, `type-xs`, surface fill); links with hover and focus states.

### Navigation and chrome
Header (one banner landmark), announce bar, primary nav (underline grows on hover; current page stays underlined), footer (one contentinfo landmark), sticky help bar on `sm` (hides when scrolling down past 240px, returns on scroll up or focus).

### Share bar
WhatsApp, X, Copy link (pills). Inside `<main>` after the content. Copy link confirms with "Link copied" for 2 seconds.

### Reusable sections (patterns)
Theme patterns in `themes/animal-writes/patterns/`, listed under **Animal Writes sections** in the block inserter. The homepage references them, so one edit to a file updates every page using it:
`hero` (standard home hero; not shown on Home while campaign 13848 is published), `help-now` (Need help now: tap-to-call helplines and four tiles), `whats-happening` (newest news), `animal-law` (From the Library), `learn` (Guides), `find-help` (Directory search, Use my location and type chips), `aw1-story` (India Writes for Its Community Dogs, full story), `take-part` and `take-part-cards` (three photo cards; they replaced `take-action`, removed 13 Nov 2026), `volunteer-banner`, `guide-cta` (end of a campaign guide), `campaign` (campaign kit layout). Slugs are `animal-writes/<name>`; the Kits theme's own patterns are in `themes/kits-by-wugweb/patterns/`.

**One section head, one of each pattern (13 Nov 2026).** Every section head is the block group `kit-sec-head`: an H2 (core Heading), an optional line (Paragraph) and an optional ghost small "See all" button. The Home patterns, the Kits `lead-list` and `three-big-actions` patterns, the FAQ (`faq.php`) and Find help (`sections.php`) all print it; the old hand-built `kit-ed-head` header is left only in the frozen Community dogs page. The unused `take-action` pattern (replaced by `take-part-cards` on 2 Nov) is removed. Patterns kept apart on purpose, because they look different and are used: `animal-writes/hero` (the Home fallback, a Cover with a photo) and `kits-by-wugweb/hero` (the Kits hero block example); `take-part-cards` (photo cards, Home and sidebar) and `kits-by-wugweb/three-big-actions` (dark band, no photos). Slugs saved in content stay: `kits-by-wugweb/banner` (six hub pages), `animal-writes/volunteer-banner` and `animal-writes/help-now` (synced patterns).

**Site Editor (13 Nov 2026).** Every pattern has a category, search keywords and a preview width (`Viewport Width`: 1280 for sections, 600 for cards, 800 for the empty state, 400 for the sponsored label); internal ones are `Inserter: no`. Template parts are declared in the Kits `theme.json`: Header, Footer and Sidebar in their areas, and Above the footer (newsletter, the band every template prints before the footer). Custom templates: Page — Wide (Kits), Hub and Campaign page (Animal Writes). The editor loads the same token, component and primitive styles as the site (`add_editor_style`). Kits Theme Options (header, socials, contact links, share bar) stay in Kits Settings for now.

### Layout rules (owner, 2 Nov 2026)
These hold on every page. Section heights below gives the detail.

1. **Sizes only from tokens and docs.** Every size is a `--kit-*` token or a value written in this file, `docs/card-sizes.md`, `docs/component-map.md` or `themes/animal-writes/kits-registry.json`. No invented heights or card sizes.
2. **Padding.** Every card and block has at least 24px (`--kit-space-5`) on every side, at every width. List cards (`kit-card--*`) and the Card block style (`is-style-kits-card`) are the exception: 16px (`--kit-space-4`) by default and at least that, set in Theme Options → Cards (owner, 11 Nov 2026; docs/card-sizes.md).
   Stacked list cards (News, Library, Resources; owner, 13 Nov 2026): 340px to 600px wide (`--kit-card-stacked-min`, `--kit-card-stacked-max`), gap 16px (`--kit-space-4`), picture 30% at 3:4 (`--kit-card-stacked-media-width`, `--kit-card-stacked-media-ratio`), the words set the height, columns 1 below 1200 / 2 from 1200; the meta row on every card is one line; picture ratios 4:3 / 3:4 / 3:2 / 1:1 in Theme Options → Cards (docs/card-sizes.md).
3. **No height tricks.** No fixed heights, no vertical percentage padding, no `!important` over section padding, no `aspect-ratio` on a text box to make room. A picture's height comes from the picture's own shape.
4. **Hero.** Every hero (the theme Cover hero `.kit-hero`, the Home campaign hero and the campaign page heroes) has one fixed height, `--kit-hero-h` = `min(720px, 100svh)`, at every width, phones included (owner, 2 Nov 2026: "we need to keep fixed or within viewport sizes"). The hero is written as `min-height: var(--kit-hero-h)`: at normal text sizes its content is designed to fit, so it stays exactly that height, and only text zoomed to 200% can make it taller (WCAG 1.4.4). Its content fits inside, with the theme H1 size, the poster scaled to fit (on phones a ticket: the day count beside the stamp) and one button on phones. Text that cannot fit is listed for the owner, never cut off.
5. **Tablet and desktop height.** From 768 wide, every section and every hero is at most 720px tall, padding included, and at most one viewport height. 800px is the ceiling nothing may pass. Content that does not fit uses the patterns we have: folds (`<details>`, the first open), the tabs or steps pattern from the registry, or the first row plus "See all". Text is never cut off and never set below the theme sizes. A list page's results and an article's body are the page's reading, not a section.
6. **Phones first.** Owner: "mobile ux is first preference". Phones are designed first, then tablet and desktop. Only Home has a phone height limit: each Home section fits one 375×812 screen, except the Home campaign row (rule 7). On every page, long sections on phones use app-like patterns: folds for long text, a sticky bar for the main action (like the Lab phone bar) and tabs for steps. Targets are at least 44px, within thumb reach, and the page never scrolls sideways. Landscape phones: no special handling (owner, 2 Nov 2026). No orientation or short-height rules; portrait phones, tablet and desktop only.
7. **Home campaign cards.** 500px each (`--aw-pair-card-h`, a min-height). Stacked on phones, side by side from the theme breakpoint 768, so the row stays within rule 5 on tablet and desktop (owner, 2 Nov 2026). On phones the two stacked cards (about 1100px with the section padding) are the one exception to the one-screen rule in rule 6: the owner's later order to stack them wins (2 Nov 2026).
7a. **Photo cards in a stack (Take action).** `--kit-photo-card-h`: 250px on phones, 350px from 768 (owner, 2 Nov 2026). It is a min-height, so text is never cut off.
8. **One button per card.**
9. **Headings.** Only the theme H1, H2 and H3 sizes (`--kit-type-h1`, `--kit-type-h2`, `--kit-type-h3`). No page-level heading font-size overrides.

10. **Content block at most 600px.** Owner: "a content block must not exceed 600 - you cannot have cards or blocks which break the standard accessibility and WCAG". At every width, phones included, no card or content block is taller than 600px. That covers cards, tiles, photo cards, columns, fold bodies, table cards and form steps. A long block is split into cards, or into a fold with a short visible part.
11. **WCAG 2.2 AA for every pattern.**
    - A data table that keeps its own scroll (rule 12): the scroller can be scrolled by keyboard (`tabindex="0"`), focus is visible, it is named (`aria-labelledby` its heading, or `aria-label`), and nothing can be reached only by swiping.
    - Folds: a real `<details>`/`<summary>` (or a button with `aria-expanded`), with the content kept in the page.
    - Sticky bar: it never covers the focused element (`scroll-padding-bottom` at the bar's height), and it is small or can be dismissed.
    - Everywhere: targets at least 44×44px, text contrast at least 4.5:1, reflow at 320px with no sideways page scroll, text that resizes to 200%, and `prefers-reduced-motion` respected (no smooth scroll or animation when it is set).
12. **No sideways scrolling rows anywhere (owner, 2 Nov 2026).** Card lists, tiles, photo cards, figures, filters and chips stack, sit in a grid (the card-sizes.md states) or wrap; no carousel, no swipe row, no `overflow-x` row. Data tables wrap to fit the column; only a table that cannot fit with whole words keeps its own scroll inside its frame (`.wp-block-table`, which scrolls only when the table is wider), with the rule 11 table points. On phones a table of four or more columns keeps `--kit-table-min` (kit.css). Tables that keep a phone scroll (survey of all 40 live tables, 2 Nov 2026): the CCSEA amendment rules (fees), Animal days in India, Wildlife week (both), Dog bite and rabies vaccine (third table), Who regulates animal welfare (four columns), the 1962 helpline table (three columns, a long place name) and the Lab rules today vs draft guide (three columns, 3px over). All of them scroll at 360 only; none at 768 or 1280 (scan of 31 pages at 360, 768 and 1280, 2 Nov 2026). The campaign pages' tables wrap (the Lab compare table too). The Supreme Court orders and tracker tables are cards on phones.
    Lab rules campaign page: kept as it was on 2 Nov morning (owner); not covered by rule 4 or the 24px rule. Rule 12 applies there too (owner, 2 Nov 2026: "revert the page as it was - not side ways").

Checks: `tools/ci/theme_rules_check.py` in the Deploy check catches rules 3 and 9 and padding under 24px, or under 16px on a list card (2), in CSS. `tools/ci/section_heights_audit.js`, pasted into the browser console, measures rules 4 to 7, 10 and the measurable points of 11 on the page: sections OVER above 720 and FAIL above 800, blocks over 600, scrollers with no keyboard focus or name, targets under 44px and sideways page scroll.

### Error pages (404, 500, 503)
The 404 (theme template, `[aw_not_found]`) and the 500/503 drop-ins (`animal-writes-core/dropins/`) share one layout. Text column 40rem (`.kit-error-page > *`, as before 2 Nov 2026). Phones: code, H1, lead, then the photo (4:3, no frame), then search and section links. From 768px the photo fills the section behind the text at `--kit-hero-min`, and the page colour (`--kit-color-canvas`) fades in from the left over it: solid to the text column's right edge (it grows with the text size, so 200% text stays on the page colour) and clear 20% further, so the text never sits on the picture. The error-page search field has a `--kit-color-muted` edge (3:1 or more). The photo files (`assets/error/{404,500,503}-wide.webp` 16:9, `-phone.webp` 4:3) are optional: without them the pages keep their one text column. The 404 uses three puppies on a gravel road (media library 15533, Unsplash), right-aligned so the fade covers the plain left side. The 500/503 pages load before WordPress, so they copy the token values in.

### Section heights
A section's height is its content plus the theme's section padding: the Group style **Section** (`is-style-kits-section`) has `padding` top and bottom set to the preset `section`, `var(--kit-space-section-base)` (48px under 768, 70px to 1199, 96px from 1200). Parts not yet blocks read `--kit-space-section` (top) and `--kit-space-section-end` (bottom), set in Appearance → Editor → Styles → Blocks → Group → Section, not in CSS. Inside a section, cards and gaps use `--kit-space-*` tokens. Every hero is the one fixed shape: `--kit-hero-h`, `min(720px, 100svh)` (Layout rules 4), set in Styles → Blocks → Hero → Minimum height. A value changed in Styles is one value at every width, and every section and hero follows it (`kits-core/includes/blocks.php`); docs/kits-control-map.md.

- No fixed heights on sections or cards (no `height`/`min-height` in px, rem or vh); a picture's height comes from its shape (`aspect-ratio`).
- No percentage padding-top or padding-bottom (it ties a height to the width); keep room for a picture with the picture itself, as a block with its own `aspect-ratio`.
- No CSS padding, background or `!important` on a section or head band: those are Styles settings (`tools/ci/settings_parts_check.py`). No section inside a section: a card in a section is a card, with card padding.
- Desktop and tablet (owner, 2 Nov 2026): from 768 every section, hero included, is at most 720px tall (800 the absolute ceiling) and no card or block is over 600px at any width. On Home the hero is the theme hero (`--kit-hero-h`, theme H1 size); Need help now is four columns from a 56.5rem section (two columns from 768, one column on phones); Guides pictures are 16:9 (`--kit-placard-ratio`) in the row of three; the community dogs story is two columns from 768.
- Home on phones: no sideways rows (Layout rules 12): Library tiles, story figures and dates and Take action photo cards stack. Show more folds (`.aw-more`, a core Details folded by `details[data-kit-fold]`, 3 Nov 2026): Need help now shows the 112, injured-animal and 1962 tiles and folds the other four at or below 1199; the four Library type tiles are folded at every width; on phones the third Library item and Take part's second and third cards are folded too (the Library tiles' fold shows once the item fold is open, so one Show more shows at a time); the page never scrolls sideways (`scrollWidth` = viewport width). No rows wins over the one-screen rule (owner, 2 Nov 2026), so some sections run over one 375×812 screen (2 Nov 2026: Need help now 1611px, The law, in plain words 1705px, the community story 1008px). The campaign cards row stacks on phones, so there that one section is two cards tall (owner, 2 Nov 2026).
- Tablet and desktop: see Layout rules 5 (at most 720px, ceiling 800px, from 768 wide).
- Home campaign row (`aw-pair`, Supreme Court and AW_Festive cards, owner 2 Nov 2026): two photo cards, the text over the campaign photograph. Stacked on phones, side by side from 768 (the theme breakpoint). Each card is one height at every width, `--aw-pair-card-h` on `.aw-pair` (owner, 2 Nov 2026: "500 for all"; one button per card). It is a min-height, so text is never cut off, and it is the one fixed card height allowed.

`tools/ci/theme_rules_check.py` (Deploy check) fails on a new fixed height (200px / 12.5rem / 20vh or more), a vertical percentage padding or an `!important` padding on a section-level selector; rules that existed on 2 Nov 2026 are in `tools/ci/theme_rules_baseline.txt`. The phone-screen, desktop and 500px rules are measured on the page with `tools/ci/section_heights_audit.js` (paste it into the browser console): phones at 375×812, desktop at 1024×768, 1280×800 and 1440×900, plus 412, 768 and 1024 for the campaign row (section and card heights, `document.documentElement.scrollWidth`).

### Home layout
The home page's sections and their order live in the theme template `themes/animal-writes/templates/front-page.html`, not in the front page's content:

1. Hero: block `animal-writes/home-hero`. While campaign 13848 is published it shows the campaign hero, open or closed: no automatic switch when it closes, and nothing is added after it closes. The standard hero (pattern `hero`) is not shown on Home; the block falls back to it only if the campaign is not published.
2. Need help now? (`help-now`). Below the four-column layout the four link tiles have their icon square beside the title and line, 16px between tiles (3 Nov 2026).
3. Find help near you (`find-help`: `[aw_find_help compact="1"]`).
4. What's happening (`whats-happening`: Query Loop 41, three items as a mosaic: the lead with a 4:3 picture and summary, two rows beside it spread to end level; on phones the lead is a row like the other two).
5. Guides (`learn`: Query Loop 44, at most one state cruelty guide).
6. From the Library (`animal-law`: Query Loop 43, three items in the mosaic: the lead full width with the two rows side by side under it from 768 to 1199, the lead beside the two rows from 1200, on phones the lead is a row like the other two (3 Nov 2026); then the four type tiles, two by two from 768, four across from 1200, stacked on phones (no sideways row, 2 Nov 2026; was a sideways row from 25 Oct 2026): an Act, rule, order or judgment leads, official releases last).
7. Take part (`take-part`, owner, 2 Nov 2026; was Take action): three photo cards (pattern `take-part-cards`), Volunteer, Run an organisation? and Get updates, three equal cards in one row from 768, stacked on phones. The same cards end every campaign page and sit in the sidebar as a compact stack (height from the text, H3-size titles). The photo is clear (no overlay): the text sits on ink 78% that starts `--kit-space-7` above it; Get updates is the brand ink (no fitting photo). Height `--kit-photo-card-h`, the Brand button in the site yellow at `--kit-size-control`. Subscribe goes to the footer sign-up (`#newsletter`). On /volunteer/ the sidebar leaves out Volunteer; on an unclaimed listing it leaves out Run an organisation?.
8. India Writes for Its Community Dogs (`aw1-story`).
9. FAQ (block `animal-writes/item-faq`).

The front page (post 57) keeps its content, but it is not shown. Its FAQ box (meta `aw_faq`) still feeds the FAQ section; with too few questions the section prints nothing. Item picks for Query Loops 43 and 44 are in `animal-writes-core/includes/home.php`.

Every home section head is the Kits section head (`kit-sec-head`): an H2, one supporting line and a ghost button.

### Directory states panel
The directory home's header visual: the nine states with the most organisations, set as type (count in Tiro, name below), each linking to its state page. Real counts from the taxonomy; no map. Busiest state in ink, the rest raised; hover lifts, reduced motion respected.

### Section (`.aw-section` + background)
Every homepage band is a full-width section with `space-section` padding on one of three backgrounds: white, **canvas** (`color-canvas`, warm off-white) or **kraft** (`color-surface`), plus the dark campaign band. White cards on canvas or kraft give clear separation; adjacent sections never share a background.

### Section header (`.aw-sec-head`)
H2 + one-line lead on the left; one CTA on the right (`.aw-link-cta`: pill, ink outline, arrow icon that nudges on hover, fills ink on hover). Wraps under the title on small screens.

### Tile (`.aw-tile` in `.aw-tiles`)
Icon in a kraft square (turns marigold on hover) + H4 title + one-line description + arrow. Raised surface, border, `elevation-1`; hover lifts to `elevation-2`. Grid: 1 / 2 / 3 columns. Used for the section index; reusable for any set of links.

### Icons (`.kit-ico .kit-ico--name`, printed by `aw_i( 'name' )`)
One icon system: `kit-ico` in Kits' kit.css, each icon a CSS mask on one `<span>` that takes the text colour; `aw_i()` (animal-writes-core systems.php) prints it. Size with `--kit-ico-size` (default 1.25em) or the `--kit-icon-*` tokens, never a width in a component.
- **External links:** the arrow comes only from `.kit-link-ext` (kit.js marks every off-site text link and adds the screen-reader note; markup may set the class itself, as the Elsewhere links do). No hand-added arrow after a link. One exception: an off-site card title (press items) prints `kit-ico--arrow-up-right`, because a card title's link carries the whole-card layer and `.kit-link-ext` draws no arrow there.
- **Close and remove:** `kit-ico--x`, never a typed "×" (active filter chips). `kit-ico--x-brand` is the X (Twitter) logo.
- **Dates:** the `aw_date` calendar icon always stays.
- **Listing and Library types:** `kit-ico--type-{slug}` (Library `kit-ico--type-lib-{slug}`), printed by `aw_type_icon()` as `aw_i( 'type-' . $slug, 'kit-ico--type' )`. The masks live in aw-type-icons.css (a component file, loaded only where a type icon prints; each SVG once, types with the same picture share a rule). The modifier `.kit-ico.kit-ico--type` keeps the size they always had: `--kit-ico-size: 1em`, `.35em` before the label, `vertical-align: -0.14em`, the text colour. Checked 14 Nov 2026: of the contextual `.kit-ico` rules, only `.kit-tag .kit-ico` and `.kit-chip .kit-ico` (both 1em) reach a type icon in its real markup; none sets a colour, `display` or transform on one. The Directory map reads each type's `--i` for its markers (dir-map.js).

Lucide line icons (ISC licence) as CSS masks, so each is one `<span>` that takes the text colour: arrow-right, arrow-up-right, directory, legal, news, knowledge, research, opportunities, campaign, resources, helpline, mail, phone, search, instagram, facebook, x. Always `aria-hidden="true"` next to visible text or an `aria-label`.

### Footer
Newsletter band above it on every page (`.aw-nl-band`, `#newsletter`): spaced as every other section (section padding top and bottom, gutter at the sides; owner, 13 Nov 2026) with a card on the surface tint (line border, card radius, as wide as a section's content: --kit-width-wide, the 1120px column on campaign pages; padding 24 / 32) with the heading at the theme H2 at every width, one line and the sign-up form (stacked on phones; email, name and Subscribe in one row from 768). Then the dark footer: four columns from 1200 (Brand: wordmark, one line, email, small round social icons; Explore; Animal Writes; Legal & Trust), 2×2 from 768, stacked on phones with the three link lists as folds. Column labels are paragraphs, not headings; each list is a `<nav>` with its own aria-label. Helplines strip (tap-to-call, slightly lighter and bordered; first on phones). Bottom bar, smaller and dimmer (6:1 on the footer surface): copyright, Privacy, Terms, Disclaimer and **Powered by Wugweb**.

### Other components
PDF viewer (inline document + download + official source), helplines grid, data table, countdown and live counts, steps, ad placements (Advanced Ads block), details/FAQ (+ rotates to ×), pagination, search, print button. All use the same tokens and states.

## Theme Options and the Site Editor
Audit plan Phase 4.7 (14 Nov 2026). Kits Theme Options (`kits_theme_options`, Kits Core `includes/theme-options.php`, the app in `src/theme-options/`) keeps only what WordPress core cannot hold today. Nothing below moved: every stored value and the front end are as they were. The two screens now link to each other:
- **Theme Options → Site Editor:** Header opens the header part itself (`site-editor.php?postType=wp_template_part&postId={theme}//header&canvas=edit`), Footer the footer part, Layout and spacing Styles and Templates; Socials links the footer part (its profile icons), Share bar the Templates list, each Content types box its own template (`single-{type}`, `single`, `page`, `404`). `kits_site_editor_path()` (admin-screens.php) builds the address, and falls back to the list when the theme has no such template or part.
- **Site Editor → Theme Options:** while the header or footer part, or any template, is open, the document sidebar shows a "Kits Theme Options" panel (core `PluginDocumentSettingPanel`, no build step, `assets/js/site-editor-links.js`) linking to Theme Options → Header, Footer or Content types. Administrators only.

| Option | Where it lives now | Can core hold it today? | Plan |
|---|---|---|---|
| Announcement bar (`announce`, on/off) | Theme Options → Header; the bar itself is the `.kit-announce` Group in the header part, which the switch leaves out | Yes: removing or adding the Group in the header part does the same | Keep the switch (one click, no part edit); retire it only on the owner's word |
| Header stays at the top (`sticky_header`) | Theme Options → Header (body class `kits-header-static`) | Partly: core Group has Position → Sticky, but the header's sticky behaviour is the theme's CSS on the part | Keep |
| Phone bar (`sticky_bar`, `sticky_href`, `sticky_label`) | Theme Options → Footer; printed in `wp_footer` (site-parts.php) | No: core has no bar fixed to the bottom and no phones-only display | Keep |
| Share bar networks (`share_networks`, per post type) | Theme Options → Share bar; read by `aw_share_html()` | No: core has no share block (Social Icons links to profiles, it does not share a page) | Keep |
| Site-wide sidebar, title, breadcrumbs (`kits_page_defaults._site`) | Theme Options → Layout and spacing | Partly: a template can hold the sidebar part and the Post Title block; there is no core Breadcrumbs block in the versions Kits supports | Keep; the layout of each template stays in the Site Editor |
| Per type: sidebar, title, breadcrumbs (`kits_page_defaults[type]`) | Theme Options → Content types; Page Options per item (post meta) | Partly, as above, one template per type (`single-{type}`) | Keep |
| Card padding and gap | Global Styles (user origin, `settings.custom.kits.card`), edited in Theme Options → Cards | Stored in core already; the Site Editor's Styles panel has no field for custom values | Keep: nothing to move |
| Colours, fonts, type, buttons, widths, spacing | Styles (Site Editor) | Yes, already there | Linked from Theme Options |
| Header and footer content, logo, menus | Header and footer parts, Navigation (Site Editor) | Yes, already there | Linked from Theme Options |
| Socials (profile list) | Rank Math → Titles & Meta → Social Meta, shown read-only in Theme Options → Socials | No for the list (Rank Math's, read by schema); the footer's profile icons are hand-written HTML in the footer part, which the core Social Icons block could hold | Keep; Social Icons in the footer would change its markup, so only with the owner's word |
| Contact links (Animal Writes "Elsewhere", option `aw_elsewhere`) | Theme Options → Socials → Contact links (Animal Writes Core) | Partly: a list on the Contact page could be core blocks, but the list is printed by code with its own marks | Keep |
| Font preload (`font_preload`) | Kits → Settings | No: speed, not design | Keep |
| Speed switches (`css_bundle`, `css_parts_cap`, `defer_scripts`, `jquery_footer`, `no_emoji`, `form_css_lazy`, `inter_dedupe`, `lcp_fix`) | Kits → Settings | No: speed, not design | Keep |
| Old section classes (`legacy_group_styles`) | Kits → Settings | No: a content fix | Keep |

## Share pictures: one size

Owner, 13 Nov 2026: every shareable picture is one size, so it is made once and reused on every network.

- **Size: 1080 × 1080 px (1:1).** X, Facebook, Instagram, LinkedIn, Threads and WhatsApp all show a square uncropped in the feed. LinkedIn crops pictures taller than 4:5. In a story (9:16), Instagram and Facebook place the square on a background.
- **Safe band:** keep the words and the main subject inside the middle 1080 × 566 px (1.91:1). Then the same picture also works where a network crops to a link-preview shape.
- **On a campaign:** the "Share picture ID (1080×1080)" field (`aw_campaign_share_image`) stores the campaign's social picture for its social accounts. The page's own Share shares the page: its link with the campaign's hero picture, the link-preview image (owner, 13 Nov 2026). No ready-made pictures are offered on the page.
- Words on a share picture still need the owner's yes. Page pictures stay without text.

## One orchestrated moment
Only the homepage hero animates on entry (`aw-rise`, once per session). Everything else is still until someone interacts.

## Accessibility baseline
See `docs/accessibility-audit.md`. Targets at least 44px (`size-target`), visible focus everywhere, one h1 per page with ordered headings, one banner/main/contentinfo landmark, reduced motion honoured.
