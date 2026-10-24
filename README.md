# Animal Writes 2.0 — WordPress

Live at https://animalwrites.in. How changes are made, reviewed and shipped: [CLAUDE.md](CLAUDE.md).

- `themes/kits-by-wugweb/` — the Kits by Wugweb parent theme (in this repo).
- `themes/animal-writes/` — the Animal Writes child theme (brand palette, templates).
- `plugins/animal-writes-core/` — content types, templates, directory, campaigns, forms glue, deploy purge.
- `plugins/aw-sourcing/` — feeds, keywords, publishers, relay, AI keys, To review, enrichment, the Workers tab, the ingest API.
- `plugins/aw-seo-extras/` — all SEO code (with Rank Math), the FAQ system, Search Insights.
- `plugins/kits-core/` — reusable design-system parts (no site-specific code).
- `plugins/mailweb/` — Mailweb 3.0: the WordPress inbox for the Hostinger mailboxes; replies go from support@.

## Docs
- [CLAUDE.md](CLAUDE.md) — working rules (branch + PR + review gate, shipping).
- [docs/pending.md](docs/pending.md) — open work (mirrored in Notion "8. Status & Pending").
- [docs/plugin-map.md](docs/plugin-map.md) — every plugin file and what it does.
- [docs/design-system.md](docs/design-system.md) — breakpoints (768 / 1200), tokens, components.
- [docs/rollback.md](docs/rollback.md) — release tags and rollback.

## URLs
| Section | URL |
|---|---|
| News | /news/ |
| Library | /library/ |
| Resources | /resources/ |
| Campaigns | /campaigns/ |
| Opportunities | /opportunities/ |
| Directory | /directory/ · /directory/{state}/ · /directory/{state}/{district}/ · /directory/{state}/{district}/{type}/ · single: /directory/org/{slug}/ |

## Ingest API (for the sourcing workers)
`POST /wp-json/aw/v1/ingest` with the ingest user's Application Password (aw-sourcing `includes/ingest.php`). Items arrive as Pending Review; the automatic review then publishes routine News and Library items and holds the rest for To review. Duplicates (same source URL) return `{"status":"duplicate"}`.

## Guardrails
- Sponsored items show "Sponsored by …" automatically.
- Imported listings are never verified. A daily job expires Featured and flags listings verified more than 180 days ago (`aw_needs_reverify`).
- Site forms (Fluent Forms + FluentCRM) send no email except WordPress's Remove-my-data confirmation.
- Ads: Advanced Ads only, switched off on live.
