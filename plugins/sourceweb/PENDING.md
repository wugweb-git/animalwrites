# SourceWeb: Pending & Roadmap

## Active Scope & Responsibilities
The content ingestion and syndication plugin owns:
- Feed ingestion (RSS, Atom, JSON feeds).
- External news scrapers and content reprint engine.
- Duplicate detection and content fingerprinting.
- Ingestion review queue and source rejection logic.

---

## Open Tasks & Enhancements

1. **Reprint Engine Controls**:
   - Canonical URL enforcement linking back to original sources.
   - Author attribution cards and syndication notices.
   - Automated keyword tagging based on incoming content analysis.

2. **Ingestion Queue & Source Rules**:
   - Rule engine for auto-discarding rejected or already applied rows (`sourcing-reject-applied-rows`).
   - Domain whitelisting and blacklisting.
   - Rate limiting external fetches with `wp_safe_remote_get`.
