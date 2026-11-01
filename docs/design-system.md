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
One family: **Inter** (variable 400–700, self-hosted woff2). Hierarchy comes from size, weight and tracking, never from a second typeface.

| Style | Used for | Size token | Weight | Line height | Tracking |
|---|---|---|---|---|---|
| Display | Homepage hero (`.aw-hero-title`, `.aw-display`) | `type-display` | 700 | 1.06 | −0.035em |
| H1 | Page and archive titles | `type-h1` | 700 | 1.06 | −0.035em |
| H2 | Section titles (`.aw-sec-head h2`) | `type-h2` | 700 | 1.2 | −0.02em |
| H3 | Card titles, sub-sections | `type-h3` | 600 | 1.3 | −0.015em |
| H4 | Tile titles, small headings, form legends | `type-h4` | 600 | 1.2 | −0.01em |
| Lead | Section descriptions, intros (`.aw-lead`, large paragraphs) | `type-lead` | 400, muted | 1.5 | — |
| Body | Paragraphs, lists | `type-base` | 400 | 1.6 | −0.006em |
| Small | Excerpts, meta, footer links | `type-sm` | 400–500 | 1.55 | — |
| Caption | Dates, counts, chips, footer headings | `type-xs` | 500–600 | — | — |

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
| Selected / current | Nav: `aria-current="page"` with permanent underline; carousel dot: `aria-current="true"` filled |
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
- **Variants:** `aw-cards-3` grid; `aw-cards-lead` (first item spans two columns on `lg`); `.aw-listing-card` (round logo, area, verified badge); carousel on `sm` (`carousel-item` width, scroll-snap, dots).
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
Header (one banner landmark), announce bar, primary nav (underline grows on hover; current page stays underlined), footer (one contentinfo landmark) with honest visit counter, sticky help bar on `sm` (hides when scrolling down past 240px, returns on scroll up or focus).

### Share bar
WhatsApp, X, Copy link (pills). Inside `<main>` after the content. Copy link confirms with "Link copied" for 2 seconds.

### Reusable sections (patterns)
Theme patterns in `themes/animal-writes/patterns/`, listed under **Animal Writes sections** in the block inserter. The homepage references them, so one edit to a file updates every page using it:
`hero` (standard home hero; not shown on Home while campaign 13848 is published), `help-now` (Need help now: tap-to-call helplines and four tiles), `whats-happening` (newest news), `animal-law` (From the Library), `learn` (Guides), `find-help` (Directory search, Use my location and type chips), `aw1-story` (India Writes for Its Community Dogs, full story), `aw1-story-home` (the same story as one compact band, for Home), `take-action` (volunteer and claim your listing, as two cards), `volunteer-banner`, `guide-cta` (end of a campaign guide), `campaign` (campaign kit layout). Slugs are `animal-writes/<name>`; the Kits theme's own patterns are in `themes/kits-by-wugweb/patterns/`.

### Home layout
The home page's sections and their order live in the theme template `themes/animal-writes/templates/front-page.html`, not in the front page's content:

1. Hero: block `animal-writes/home-hero`. While campaign 13848 is published it shows the campaign hero, open or closed: no automatic switch when it closes, and nothing is added after it closes (owner, 1 Nov 2026). The standard hero (pattern `hero`) is not shown on Home; the block falls back to it only if the campaign is not published.
2. Need help now? (`help-now`).
3. Find help near you (`find-help`: `[aw_find_help compact="1"]`).
4. What's happening (`whats-happening`: Query Loop 41, four items as a mosaic).
5. Guides (`learn`: Query Loop 44, at most one state cruelty guide).
6. From the Library (`animal-law`: Query Loop 43, the law first).
7. Take action (`take-action`).
8. India Writes for Its Community Dogs (`aw1-story-home`).
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

### Icons (`.aw-ico .aw-ico--name`)
Lucide line icons (ISC licence) as CSS masks, so each is one `<span>` that takes the text colour: arrow-right, arrow-up-right, directory, legal, news, knowledge, research, opportunities, campaign, resources, helpline, mail, phone, search, instagram, facebook, x. Always `aria-hidden="true"` next to visible text or an `aria-label`.

### Footer
Brand column (wordmark, one line, email with icon, social icon buttons), Explore (two columns), Animal Writes links; bottom bar with copyright, visit count, Terms, Privacy, Disclaimer and **Powered by Wugweb** (pill, links to wugweb.com).

### Other components
PDF viewer (inline document + download + official source), helplines grid, data table, countdown and live counts, steps, ad placements (Advanced Ads block), details/FAQ (+ rotates to ×), pagination, search, print button. All use the same tokens and states.

## One orchestrated moment
Only the homepage hero animates on entry (`aw-rise`, once per session). Everything else is still until someone interacts.

## Accessibility baseline
See `docs/accessibility-audit.md`. Targets at least 44px (`size-target`), visible focus everywhere, one h1 per page with ordered headings, one banner/main/contentinfo landmark, reduced motion honoured.
