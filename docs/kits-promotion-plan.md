# Promotion plan: Animal Writes child → Kits master (`kits-by-wugweb`)

Status: **proposal, nothing moved.** Approve or edit the "Move" column; each row is moved separately and tested on animalwrites.in before the next.

## Principle

Kits gets the **system** (token pipeline, components, states, behaviours, form engine) with **neutral defaults**. Animal Writes keeps its **brand** (token values, logo, copy, imagery) and **domain** (content types, directory, sourcing, campaigns). After the move the child theme is mostly a token file, brand assets, templates and patterns that are about animals.

## Inventory

### Tokens and build

| Item | Where now | Generic? | Move |
|---|---|---|---|
| Token contract and build script (`sm/md/lg` triples, validation, CSS emit) | `tools/build-tokens.py` | Generic | **Yes**: Kits ships the pipeline |
| Token *names* (color-, type-, space-, radius-, elevation-, motion-, z-, size-, tracking-, leading-, weight-) | `tokens/tokens.json` | Generic | **Yes**: Kits ships the schema plus neutral default values |
| Token *values* (Wugweb yellow #ffbe1a, link blue #0062b8, canvas #f6f3ee, kraft, Inter, radii) | `tokens/tokens.json` | Brand | No: stays in the child as overrides |
| `theme.json` palette/font mapping to tokens | child `theme.json` | Generic mapping, brand values | **Split**: mapping to Kits, values stay |
| Inter (self-hosted woff2) | child `assets/fonts/inter` | Generic font, brand choice | Optional: Kits default family |

### Components (`aw.css` section 4) and behaviours (`aw.js`)

| Component | Generic? | Move | Notes |
|---|---|---|---|
| 4.1 Button (primary, outline, ink, ghost; hover, active, focus, disabled, loading) | Generic | **Yes** | rename `aw-btn` → `kit-btn` (alias kept) |
| 4.2 Card (raised surface, flush media, chips, title, excerpt, date, arrow, whole-card link) | Generic | **Yes** | |
| 4.3 Media (remote image, fallback placard, logo, document cover) | Mostly generic | **Yes** (placard label is the only AW-specific part) | |
| 4.4 Field (label, hint, control, error, valid/invalid, summary) | Generic | **Yes** | |
| 4.5 Chip | Generic | **Yes** | |
| 4.7 Icons (CSS-mask set) | Generic mechanism; set is partly domain (injured, legal) | **Split**: mechanism + UI icons to Kits; domain icons stay | |
| 4.8 Section backgrounds, 4.9 Section header, 4.10 Tile | Generic | **Yes** | |
| 5 Chrome: header nav with dropdowns, sticky help bar, footer grid, skip link | Generic | **Yes** (footer credit "Powered by Wugweb" becomes a Kits default) | |
| 6 Page components: pager, empty state, chips nav, trust panel, filter bar, carousel dots | Generic | **Yes** | |
| 6 Domain components: case register, document library, directory rows, opportunity card, states panel, helplines, countdown, receipts | Domain | No | stay in the child / plugin |
| 7 Motion (tokens, one hero entrance, reduced-motion) | Generic | **Yes** | |
| 8 Print | Generic | **Yes** | |
| 9 Editorial homepage (`aw-ed-*`) | Layout system generic; content AW | **Split**: layout classes to Kits (`kit-ed-*`), patterns stay | |
| `aw.js`: hero once-per-session, sticky bar, carousel dots, copy-link, form validation, district filter | Generic except district filter | **Yes** except the directory filter | |

### PHP (plugin `animal-writes-core`)

| Module | Generic? | Move |
|---|---|---|
| `forms.php`: field component, spam guard, server errors via token | Generic | **Yes**: into a small "Kits Core" plugin (themes should not hold form handling) |
| `schema.php` helpers (`aw_jsonld`) | Generic helper; NGO/ItemList specifics are domain | **Split** |
| `share.php`, `seo.php`, `analytics.php` (GTM by host), `ads.php` | Generic | **Yes**: to Kits Core |
| `visits.php` honest counter | Generic | **Yes**: to Kits Core |
| `launch.php` (.htaccess writer, one-time media/slug jobs) | Ops for this site | No (the .htaccess writer could become a Kits Core utility later) |
| `attribution.php` source row, favicons, relative time | Generic for any aggregator | **Optional**: Kits Core "attribution" |
| `sourcing.php` publisher/keyword policy | Generic for any aggregator | **Optional**: Kits Core "sourcing" |
| content types, directory, claims, reports, volunteers DB, campaigns, sections, systems, ingest, bindings | Domain | No |

### Patterns and templates

| Item | Move |
|---|---|
| Kits already has: hero, hero-split, card-grid, cta, empty-state, faq, newsletter, section, topic-hub, ad-slot, sponsored-label | keep; restyle them on the promoted components |
| AW patterns: hero (photo), help-now, whats-happening, animal-law, find-help, learn, take-action, aw1-story, campaign | **No**, they are content. Two have generic shapes worth adding to Kits as neutral patterns: **"three big actions"** (help-now) and **"lead + list"** (whats-happening) |
| Templates `archive-aw_*`, `single-aw_*` | No (domain) |
| Parts header/footer | Generic structure **yes** (grouped nav + CTA, 3-column footer + legal bar + credit); brand assets stay |

## Order (if approved)

1. Token pipeline + schema into Kits with neutral values; AW keeps its values as overrides. Visual diff on animalwrites.in must be zero.
2. Components 4.1–4.10, chrome, motion, print, generic `aw.js` into Kits (`kit-*` classes, `aw-*` aliases kept so content does not break).
3. Kits Core plugin: forms, share/SEO/analytics/ads, visits.
4. Optional: attribution + sourcing into Kits Core.
5. Remove the now-duplicated rules from the child; re-run axe at 1280/375 (target: 0 violations, as today).

## Risks

- Class renames break saved content: mitigated by keeping `aw-*` aliases until a later clean-up.
- Kits sites without the plugin: components must degrade without PHP helpers.
- Cache: version bump of both themes so LiteSpeed and browsers pick up the split CSS.
