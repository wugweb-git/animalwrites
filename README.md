# Animal Writes — WordPress

Live at **https://animalwrites.in** — a platform for animal welfare in India: daily **news**, a **library** of government rules, advisories and judgments, a **directory** of NGOs, gaushalas and government vets by state and district, and **campaigns**.

Built on WordPress with the **Kits by Wugweb** block theme and a set of Kits plugins. The old `animal-writes` child theme is retired; Kits by Wugweb runs the whole site.

## Structure
- `themes/kits-by-wugweb/` — the Kits by Wugweb parent block theme (runs the whole site).
- `plugins/animal-writes-core/` — content types and fields; the site's theme files (`theme/`: brand palette, templates, parts, patterns, `aw.css`); campaigns and letters; forms and people (Fluent Forms, FluentCRM); card pictures; deploy purge, backups, site check and branded error pages.
- `plugins/directoryweb/` — **DirectoryWeb**: the Directory — listings with their places and types, a map and Near me, claims from organisations, Claimed and Verified marks, and "Find help near you".
- `plugins/aw-sourcing/` — **SourceWeb**: feeds, keywords, publishers, relay, AI keys, enrichment, the review queue and the ingest API for the sourcing workers.
- `plugins/aw-seo-extras/` — **RankWeb**: SEO (with Rank Math), the FAQ system and structured data.
- `plugins/insightweb/` — **InsightWeb**: Search Insights — Search Console, GA4, Clarity and PageSpeed, movers and keyword work.
- `plugins/planweb/` — **PlanWeb**: the publishing calendar and one site schedule (WordPress posts, pages, campaigns and newsletters in one month view and feed).
- `plugins/mailweb/` — **MailWeb**: the WordPress inbox for the Hostinger mailboxes; replies go from support@.
- `plugins/kits-core/` — **Kits Core**: reusable design-system parts and the shared admin shell (no site-specific code).

## Docs
- [docs/plugin-map.md](docs/plugin-map.md) — every plugin file and what it does.
- [docs/design-system.md](docs/design-system.md) — breakpoints (768 / 1200), tokens, components.
- [docs/rollback.md](docs/rollback.md) — release tags and rollback.
- [CLAUDE.md](CLAUDE.md) — how changes are built, reviewed and shipped.

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
`POST /wp-json/aw/v1/ingest` with the ingest user's Application Password (`aw-sourcing/includes/ingest.php`). Items arrive as Pending Review; the automatic review publishes routine News and Library items and holds the rest for review. Duplicates (same source URL) return `{"status":"duplicate"}`.

## Guardrails
- Sponsored items show "Sponsored by …" automatically.
- Imported listings are never auto-verified; a daily job flags listings last verified over 180 days ago.
- Site forms (Fluent Forms + FluentCRM) send no email; "Report a mistake" and "Remove my data" are reasons on the Contact form.
- Ads: Advanced Ads only, switched off on live.
