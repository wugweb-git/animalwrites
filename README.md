# Animal Writes 2.0 — WordPress

Local build. Not deployed anywhere.

- `themes/animal-writes/` — child theme of **Kits by Wugweb** (brand palette + pillar templates).
- `plugins/animal-writes-core/` — content types, directory, ingest endpoint, guardrails. Theme-independent.

Requires the parent theme `kits-by-wugweb` (in the Kits-WP folder).

## Install in WordPress Studio
Copy (or symlink) into the Studio site:
- `Kits-WP/kits-by-wugweb` → `wp-content/themes/kits-by-wugweb`
- `themes/animal-writes` → `wp-content/themes/animal-writes`
- `plugins/animal-writes-core` → `wp-content/plugins/animal-writes-core`

Then: activate the plugin, activate the **Animal Writes** theme, set Permalinks to "Post name" and save.

## URLs
| Pillar | URL |
|---|---|
| News | /news/ |
| Legal Watch | /legal-watch/ |
| Research Library | /research/ |
| Knowledge Centre | /knowledge/ |
| Campaign Hub | /campaigns/ |
| Opportunities | /opportunities/ |
| Resources | /resources/ |
| Directory | /directory/ · /directory/{city}/ · /directory/{city}/{type}/ · single: /directory/org/{slug}/ |

Directory order: Featured first, then A–Z.

## Ingest API (for the sourcing workers)
`POST /wp-json/aw/v1/ingest` with an Application Password (Users → Profile). User needs `edit_posts`.
Everything lands as **Pending Review**. Duplicates (same source URL) return `{"status":"duplicate"}`.

```json
{
  "type": "news | legal | research | listing",
  "title": "...",
  "summary": "2–3 lines in our own words",
  "source_url": "https://...",
  "source_name": "...",
  "source_date": "YYYY-MM-DD",
  "topics": ["..."],
  "source_type": "Supreme Court",
  "city": "delhi",
  "listing": { "listing_type": "animal-shelter", "phone": "", "whatsapp": "", "email": "", "website": "", "area": "", "services": "" }
}
```

## Guardrails
- Sponsored items show "Sponsored by …" automatically.
- Imported listings are never verified. Daily job expires Featured and flags listings verified more than 180 days ago (`aw_needs_reverify`).

## Not built yet
Listing claim/update form, sourcing workers, newsletter form, ad placements, Homeward mapping.
