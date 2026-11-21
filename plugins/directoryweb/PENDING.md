# DirectoryWeb: Pending & Roadmap

## Active Scope & Responsibilities
The directory and faceted search plugin owns:
- Custom Post Type `aw_listing` (or configurable tenant listing post type).
- Faceted search, filtering, and geolocation queries.
- Directory detail and archive rendering.
- Shelter and veterinary clinic claim listing workflow.
- Schema.org structured data for local organizations and clinics.

---

## Open Tasks & Enhancements

1. **Complete Data Model Decoupling from Animal Writes Core**:
   - Merge remaining listing field definitions and taxonomies from `claude/awcore-directoryweb-7a7567` directly into `DirectoryWeb`.
   - Remove legacy bridge calls to `animal-writes-core`.

2. **Unified Search & Facet System**:
   - Harmonize homepage quick-search bar with `/directory/` faceted filter query engine.
   - Support URL state persistence for active filters (city, state, emergency care, verified status).

3. **Listing Claim Flow (`docs/cc-master-plan-19-nov.md`)**:
   - Frontend claim modal with verification document/email submission.
   - Admin review queue under `DirectoryWeb > Claims` with Approve/Reject actions.
   - Automated notification emails via `MailWeb`.
   - Verified badge assignment upon approval.

4. **Directory Performance & Caching**:
   - Cache expensive facet aggregation counts in transients.
   - Ensure zero slow MySQL queries on large directory datasets (7,000+ entries).

5. **Business Directory Plugin (WPBDP) Deep Consumption**:
   - *Status (22 Nov 2026):* Initial `directoryweb_post_type()` dynamic filter and metadata adapter (`includes/compat-bdp.php`) added.
   - *Open:* Generalize remaining hardcoded `'aw_listing'` references across `directory-map.php`, `listing-template.php`, `directoryweb-ui.php`, and `featured.php` to `directoryweb_post_type()`.
   - *Open:* Bridge BDP custom table `wpbdp_form_fields` and taxonomies (`wpbdp_category`, `wpbdp_tag`) so WPBDP installations can use native Gutenberg directory cards and Leaflet map blocks without database migration.
