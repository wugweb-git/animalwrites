# DirectoryWeb: Pending & Roadmap

Status as of 22 Nov 2026 (checked on `wordpress-dev` at `7e98b691`).

## Active Scope & Responsibilities
- Custom Post Type `aw_listing` (or configurable tenant listing post type).
- Faceted search, filtering, and geolocation queries.
- Directory detail and archive rendering.
- Shelter and veterinary clinic claim listing workflow.
- Schema.org structured data for local organizations and clinics.

---

## Open Tasks & Enhancements

1. **Complete Data Model Decoupling from Animal Writes Core**:
   - ~~Merge remaining listing field definitions and taxonomies from `claude/awcore-directoryweb-7a7567` directly into `DirectoryWeb`.~~ ✅ Done: `listing-meta.php`, `listing-terms.php`, `data-sources.php` load from the plugin; `DIRECTORYWEB_SCF_GROUPS` owns the eight field groups.
   - ~~Remove legacy bridge calls to `animal-writes-core`.~~ ✅ Done: `aw_scf_groups` filter kept as the one documented seam.

2. **Unified Search & Facet System**:
   - ~~Harmonize homepage quick-search bar with `/directory/` faceted filter query engine.~~ ✅ Done (`find-help.php`, `directory.php`).
   - ~~Support URL state persistence for active filters (city, state, emergency care, verified status).~~ ✅ Done (`directory.php`).
   - `kits/collection-filter` block replaces `[aw_directory_filter]` once Kits Core ships it (§0.2 of the plan). The shortcode stays until then.

3. **Listing Claim Flow (`docs/cc-master-plan-19-nov.md`)**:
   - ~~Frontend claim modal with verification document/email submission.~~ ✅ Done (`claim-page.php`, `claims.php`).
   - ~~Admin review queue under `DirectoryWeb > Claims` with Approve/Reject actions.~~ ✅ Done (`claims-review.php`); the new shell screen stays read-only by owner decision (11 Nov 2026).
   - ~~Automated notification emails via `MailWeb`.~~ ✅ Done (MailWeb bridge).
   - ~~Verified badge assignment upon approval.~~ ✅ Done (`gov-verified.php`, `claim-page.php`).

4. **Directory Performance & Caching**:
   - ✅ Cache expensive facet aggregation counts in transients (`directoryweb_facet_districts`, `directoryweb_facet_types`, `directoryweb_facet_services`) with automatic invalidation on `aw_place_counts_reset` and listing updates.
   - ✅ Ensure zero slow MySQL queries on large directory datasets (7,700+ entries).

5. **Phase 2 blocks (plan §1.6)** — ✅ built and loaded 22 Nov 2026:
   - ✅ `directoryweb/fields` (`includes/directoryweb-fields.php`)
   - ✅ `directoryweb/status` (`includes/directoryweb-status.php`)
   - ✅ `directoryweb/people` (`includes/directoryweb-people.php`)
   - ✅ `directoryweb/featured-in` (`includes/directoryweb-featured-in.php`; parse error fixed, `post_type` corrected to `aw_listing`)
   - ✅ `directoryweb/local-card` (`includes/directoryweb-local-card.php`)
   - ✅ `directoryweb/helplines` (`includes/directoryweb-helplines.php`)
   - Open: the Claim and Find help patterns, and swapping the template over to them.

6. **Phase 3 admin (Kits Core shell)** — ✅ built on `wordpress-dev`:
   - ✅ Dashboard, Claims (read-only), Settings screens (`directoryweb-ui.php`, `directoryweb-dashboard.php`)
   - ✅ `directoryweb/v1` routes: `dashboard`, `dashboard/count`, `stats/<tile>`, `claims`, `claims/<id>`, `settings`, `settings/claim-form`, `listings` (`directoryweb-rest.php`)
   - ✅ `manage_directoryweb` capability with grant + fallback (`directoryweb-caps.php`)
   - Open: the §2.4 requests to Kits Core first; the old Claims address 301 redirect.

7. **Deploy**: parent PR to `wordpress-dev` merges through the Deploy check; ship with `tools/kits-ship.sh`.
