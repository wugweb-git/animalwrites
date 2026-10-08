# Phase 11 report (8 Oct 2026)

Deploys: 3fcf886 (Phase 11), then fixes a79e85d, 8845575, ac9e33b, 4be0c87. Backups before: `~/backups/db-2026-10-08-pre-phase11.sql.gz`, `~/backups/code-2026-10-08-pre-phase11.tgz`.
Screenshots (not in git): `docs/phase11/screens/before|after/{page}-{375,640,1024,1200}.png`, `docs/phase11/kits-demo-1440.png`.

## 8. Carry-over (0.1–0.3)
- 0.1 IIT workshop (PIB release) Library → News: `/library/iit-organises-…-2026/` 301 → `/news/iit-organises-…-2026/` (200).
- 0.2 Rank Math deleted; `/wp-content/plugins/seo-by-rank-math/readme.txt` → 404.
- 0.3 News by status: publish 19, pending 2, draft 7, private 14 (merged/off-topic). "182" was "18" + "2" printed together.

## 1. B.1 inventory and missing tokens
See `b1-inventory.md` (old class → new primitive, files, URLs). Tokens added (none existed): `--kit-focus-width` 3px, `--kit-focus-offset` 2px, `--kit-size-check` 1.25rem, `--kit-color-warning` / `-soft`, `--kit-color-info` (= `--color-blue`) / `-soft`.

## 2. Primitives (kit-primitives.css + kits-core/includes/primitives.php)
| # | Primitive | Anatomy / variants | States | Tokens | A11y |
|---|---|---|---|---|---|
| 1 | kit-tag | span; neutral, accent, ink, success, warning, danger, info | static | radius-xs, fluid-label, color-* | not focusable |
| 2 | kit-chip | a; default, on-ink (footer). Topic links (post-terms) use it | default, hover (hover:hover), focus-visible, active, visited = default | radius-full, s-8 height + 44px hit area | focus ring |
| 3 | kit-filter-pill | a; optional count | default, hover, focus, selected (check mark + bold + aria-current), selected+hover, disabled (opacity-disabled + aria-disabled), loading | radius-full, size-target 44px | not colour-only |
| 4 | kit-filter-group / kit-filter-sheet | fieldset + legend + pills, "Show more" over 8 | phones: "Filters" sheet, focus kept inside, Esc closes, Reset / Apply | | hidden if ≤ 1 option, 0-result values hidden |
| 5 | kit-filter-active | removable chips (× = "Remove {value}") + "Clear all"; links, so the URL and Back work | | | |
| 6 | kit-badge | verified, unconfirmed, featured, sponsored, ad, closed, new | static | radius-xs | Advertisement is now a badge |
| 7 | kit-eyebrow | small label (fluid-label, weight-label) | | | `.is-style-kits-label` now looks the same, sentence case |
| 8 | kit-count | tabular figures | | | |
| 9 | kit-segment | 2–4 links/buttons | selected = aria-current | | Directory sort |
| 10 | kit-btn | primary, secondary, ghost, ink; sm | hover, focus, active, disabled, loading (spinner + label, aria-busy, no double send) | | aw-btn removed |
| 11 | kit-field | label above, (optional), optional icon, example placeholder, reserved message line (aria-describedby) | default, hover, focus, filled, invalid (peach + ⚠ + aria-invalid), valid (green, after a check), disabled, read-only | | |
| 12 | kit-checkbox | 20px, clickable label | invalid | size-check | consent unchecked |
| 13 | kit-form-status | role=status, aria-live polite | error / success | | |
| 14 | kit-banner | pattern "Banner" (cover): 16:5 / 4:3 phones; nothing until an image | first one fetchpriority=high | | |
| 15 | kit-breadcrumb | Home › … on every page type except home and 404; BreadcrumbList | | | |
Demo: private, noindex page `/kits-demo/` (administrators only), master palette and Animal Writes palette side by side.

## 3. D audit (375px: no page scrolls sideways; checked with ?ck= URLs)
| Page | Issue | Type | Status |
|---|---|---|---|
| Home | "Law that affects animals", "Learn", "All court and government updates", "The Acts and Rules, in full" | copy | OWNER |
| Home | Find help said 34 states, grid showed 36 (Chandigarh 0, Lakshadweep 0) | mechanics | fixed (34 and 34) |
| Directory state/district pages | "Shelters, vets, ambulances and rescue groups listed here…" (no shelters/ambulances/rescue groups listed) | copy | OWNER |
| About | mentions "Legal Watch" | copy | OWNER |
| Library | 5 unlabelled rows; Supreme Court in Issuer and Court; Court with one option | mechanics | fixed (labelled groups; courts only under Court; Court/State hidden while ≤ 1 option) |
| Library, Opportunities | one-line title links 23px tall | mechanics | fixed (24px) |
| Directory | top-9 tiles, false subtitle, filters off the baseline | mechanics + copy | fixed; subtitle OWNER to approve |
| Contact, Media & PR | no breadcrumb | mechanics | fixed |
| Library, Opportunities, News, Resources, Campaigns | filters labelled, counts respect other filters, URL keeps state | mechanics | fixed |
| Search (none) | existing empty text | copy | unchanged |

## 4. OWNER strings
Home A1 wording (above). Directory subtitle "Animal welfare organisations, gaushalas and government vets across India." Contact: "We reply within a few working days." and the meta description. Newsletter and Contact messages as given in the prompt. Media & PR empty state "No coverage listed yet." (placeholder). Filter sheet "Show fewer" (collapse label, not in the prompt). Directory state/district description. About "Legal Watch".

## 5. Banners (G)
Settings → Banners removed (all slots were empty since 8 Oct; old values archived in `aw_banners_removed_2026_10_08`). Banner = Kits pattern "Banner" (Site Editor / page content). Topic hubs: optional "Banner image" (media ID) on each topic's edit screen; empty → no banner. Newsletter strip: Cover block in the footer template part (Site Editor → Footer), placeholder image #8839 marked "placeholder: replace". Pages and archives: nothing placed because nothing was set.

## 6. Media & PR fetch results (8 Oct)
All 7 items: page read, thumbnail downloaded and hosted (source URL kept), format Article. Dates found for TOI 27 Nov 2025, Deccan Herald 29 Nov, Outlook 29 Nov, DT Next 30 Nov; publishers from the sites' own names: The New Indian Express, DT Next, India TV News, India TV Hindi, The Times of India, Deccan Herald, Outlook India. No videos.

## 7. Admin (J)
Menu (checked with WP-CLI as an administrator; not screenshot, no login): Dashboard · Directory · News · Resources · Library (Kind, Issuers, Courts, Languages) · Opportunities · Campaigns · Pages (Media & PR) · Media · Classification (Topics, Locations) · Inbox (Listing claims, Messages, with unread counts) · Audience (Newsletter, Volunteers) · Appearance · Plugins · Users · Tools · Settings (… Mail, Ads, Sourcing, Contact links) · Site Kit. Posts and Comments hidden, comments off, "Hello world!" → draft. Court terms on news: 5, kept in meta `aw_court_legacy`. Testimonials: 0 items; export `wp-content/uploads/aw-private/testimonials-export-2026-10-08.json`.

## 9. Newsletter, contrast, rich results
Newsletter strip at 1024px: 386px → 265px (target 200px not met: the ALTCHA check box takes a row). Text on the band: white on ash at 80% over the photo ≈ 9:1 at worst. Rich Results Test: not run (Google's tool); JSON-LD types on /contact/: Organization, WebSite, ContactPage, BreadcrumbList.

## Follow-up (8 Oct, afternoon) — each checked live with a ?ck= URL
| # | Item | Result | Checked |
|---|---|---|---|
| 1 | Home copy | "From the Library", "Guides and research", "Browse the Library", "Read the Acts and Rules", "See all resources" live; old strings gone | /?ck= |
| 2 | Directory intro + meta/og | hub "…across India."; Kerala "…in Kerala."; Ernakulam "…in Ernakulam, Kerala." in intro, meta description and og:description | /directory/, /directory/kerala/, /directory/kerala/ernakulam/ |
| 3 | Contact | reply-time sentence removed; new meta description; Elsewhere: Instagram, Facebook, X (names + icons, new tab) | /contact/ |
| 4 | About | "Legal Watch" → "Library" linked to /library/ | /about/ |
| 5 | Filter groups | "Show all (13)" / "Show fewer" | /library/ |
| 6 | Media & PR empty state | "No coverage yet." + "Written about Animal Writes? Send us the link." → /contact/?about=general (shows only with no items) | code |
| 7 | Newsletter | heading removed; ALTCHA automatic on send (no box; check ran and returned a solution in the browser); height at 1024px 198px (was 386) | /contact/ |
| 8 | Testimonials | not in the REST API: /wp-json/wp/v2/aw_testimonial → 404; export (0 items) in aw-private | /wp-json/… |
| 9 | Media cards | shared story card (aw-story aw-story--card aw-story--external); no aw-press classes | /media/ |
| 10 | Press discovery | worker job built (workers 9725640): Google Programmable Search, rotates queries, drafts to "Inbox → Press to review"; off: GOOGLE_CSE_KEY / GOOGLE_CSE_CX not set | gh secret list |
| 11 | validator.schema.org | /contact/: BreadcrumbList + ContactPage, 0 errors 0 warnings; /media/: CollectionPage + BreadcrumbList, 0 errors 0 warnings | validator.schema.org |
| — | Section band | edge to edge, no gap above or at the sides; breadcrumb, heading and search/filters inside it on one left edge; breadcrumb first on item pages | /directory/, /library/, news item |

Not possible from here (needs an admin login): screenshots of WP Admin (menu, Press to review, Settings). /kits-demo/ is private (404 when logged out, by design); its render is in kits-demo-1440.png.
