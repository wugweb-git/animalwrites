# DirectoryWeb: the Directory as its own Kits plugin (plan, 11 Nov 2026)

**Status:** plan only, waiting on the owner. No code until the owner approves it.
**Decision (owner, 11 Nov 2026):** the Directory (listings, types, places, claims, badges, Find help, government records) leaves Animal Writes Core and becomes the Kits plugin **DirectoryWeb**. It requires Kits Core, keeps today's data keys (the aw→kits rename is deferred), and follows the Kits plugin UI standard (Dashboard, one working list, Settings).
**Checked against:** `origin/wordpress-dev` at `b0512a4e` (live `wordpress` is `637e9e64`). Every file:line below was read on that commit.

## 0. What this plan has to settle first (owner questions)

1. **The admin look (settled by the owner, 11 Nov).** The admin look follows the Kits admin design system, which ships in Kits Core with the shell and follows the Google Site Kit pattern for plugins. WordPress packages are plumbing only: `@wordpress/data`, `api-fetch`, `i18n`, `icons`. Clickable prototypes come before any build. The shell is specified in `docs/kits-plugin-ui.md`, next to `docs/admin-ui-spec.md`; neither file was on any branch on 11 Nov. **DirectoryWeb only registers parts into the shell.** It designs no header, tile, list or settings row; any part the design system lacks is listed in §2.4 as a request to Kits Core. This is about admin screens only: the front-end blocks stay WordPress-native (core blocks, Block Bindings, patterns, Query Loop variations; §1.6).
2. **Three things the brief takes as given do not exist yet.** Each one is a dependency or a small Kits task, not DirectoryWeb work:
   - **`Requires Plugins:`:** no plugin in this repo has the header, including RankWeb, MailWeb and SourceWeb. They check for Kits at runtime instead (`aw-sourcing.php:21-30`, `includes/kits-hub.php` in each). Animal Writes Core also loads Kits Core and SourceWeb by `require_once` (`animal-writes-core.php:39-41`, `:143-147`).
   - **`kits/collection-filter`:** there is no such block. Kits has `kits/hero` and `kits/dropdown`; the collection toolbar is only a component in `kits-registry.json:576`.
   - **`kits_component_styles`:** it is a filter of the **parent theme** (`themes/kits-by-wugweb/functions.php:349-358`), not of Kits Core.
3. **The copy of live.** The proof in §3.6 needs a copy of live. Staging (`docs/staging.md`) runs a deleted branch with a 15 Oct database. Owner to choose: (a) refresh staging with `tools/staging/copy-db.sh` for this job only, or (b) compare on live, signed out and read-only, before and after each ship. Option (b) means a rollback (`docs/rollback.md`) if anything differs. **Owner, 12 Nov 2026: (a), refresh staging.**
4. **`featured.php` stays.** Featured *listings* were dropped on 24 Oct 2026 (`docs/status.md:34`, `directory.php:158`), not 21 Oct. `featured.php` today is the live **"Featured in"** matcher: News, Library, Resources, Press and Campaign items that mention an organisation, shown on its profile and in its schema. It moves to DirectoryWeb. Its one dead function `aw_featured_html()` (`featured.php:415`, no callers) is deleted in phase 1 (owner, 12 Nov 2026).

## 1. Inventory

**Where each piece goes:**
- **DW:** moves into DirectoryWeb.
- **Core:** stays in Animal Writes Core.
- **SW:** stays in SourceWeb.
- **RW:** stays in RankWeb.
- **IW:** InsightWeb.
- **Kits:** Kits Core or the Kits theme.

**Paths:** `core/` is `plugins/animal-writes-core/includes/`, `sw/` is `plugins/aw-sourcing/includes/`, `rw/` is `plugins/aw-seo-extras/includes/`.

**Size:** the 17 Directory files in Core come to 5,515 lines of PHP. With them go 8 SCF groups, `lgd.json` (76 KB), `district-centroids.json` (18 KB), 2 stylesheets (27 KB), 2 scripts and 1 template.

### 1.1 Animal Writes Core files that move

| File | What it does (key lines) | Goes to |
|---|---|---|
| `core/directory.php` | Directory URLs (rewrite `:20`, `init` 20 `:25`; `request` `:86`; `template_redirect` `:344`). List query `aw_directory_query_vars` `:163`, H1/title/intro (`:232`, `:294`, `:641`), search aliases and PIN search (`:414-466`), counts (`:488`, `:495`), contact score (`:593`, synced on meta change `:609-610`), `[aw_directory_filter]` `:587`, digit-last sort `:654` | DW. Calls RankWeb's `aw_seo_dir_h1` / `aw_seo_place_has_helpline` (`:228-245`), which become filters (§1.4) |
| `core/directory-map.php` | Map and Near me: REST `aw/v1/dir-points` `:146`, `aw/v1/dir-near` `:169` (public GET), transient `aw_dir_points`, script/style `aw-dir-map` `:314-316` | DW |
| `core/listing-profile.php` | Fallback details box when SCF is off (`:62`, save `:105`), `org-details` block callback `:140` | DW. `org-details` sits in no template: delete in phase 2 |
| `core/listing-template.php` | The 13 profile block callbacks (`:116-546`), `claim-cta` `:566`, listing-help hide `:401`, H1 tick removal `:414` | DW |
| `core/org-people.php` | People of an organisation from FluentCRM: `[aw_org_people]` `:85`, edit box `:106`, public org search `wp_ajax(_nopriv)_aw_org_search` `:164-165` | DW |
| `core/places.php` | LGD places: reader `aw_lgd()` `:53` (`data/lgd.json` `:58`), place helpers `:85-518`, option `aw_place_counts`, `save_post`/`set_object_terms` count resets `:320-331`, PIN lookup `aw_pin` `:494`, old place redirects `:382`, WP-CLI `aw redirects-import` `:408` | DW. Also used by News, Library, Campaigns and Opportunities (`AW_PLACE_TYPES` `:28`): DirectoryWeb owns places, and the other types use them because Core requires DirectoryWeb |
| `core/places-admin.php` | Place box on the edit screen (`acf/render_field` `:73`, save `:84`, `wp_ajax_aw_pin_lookup` `:127`) | DW |
| `core/claims.php` | Legacy `aw_claim` type (`:42-60`, kept so pre–Fluent Forms claims stay readable), logo checks, `admin_post_aw_claim_decide` `:182` (fires `aw_claim_before_apply` `:191`) | DW |
| `core/claims-review.php` | Claim form #6: pre-fill `fluentform/rendering_field_data_*` `:124-172`, `AW_CLAIM_L` `:27`, Directory → Claims (`add_submenu_page` `:296`, cap `manage_directoryweb` since 13 Nov 2026), per-field approve `aw_claim_apply_field` `:494`, `admin_post_aw_claim_ff_decide` `:578` | DW |
| `core/find-help.php` | "Find help near you": place detection in text `:122-256` (`save_post` 30 `:266`), links on `item-more` `:354` and the campaign band `:370`, WP-CLI `aw-places` `:419`, `:507` | DW |
| `core/featured.php` | "Featured in" matcher (see §0.4): scan, cron `aw_featured_rescan` `:322`, meta `aw_rel_org*`, schema helpers `:442-455`, WP-CLI `:497` | DW. Delete dead `aw_featured_html` `:415` |
| `core/gov-verified.php` | Verified (government records): `aw_gov_verification` `:57`, sync on meta change `:112-128` (only while option `aw_gov_verified_live`), title mark `:167`, edit-screen box `:222`, WP-CLI `aw gov-verify` `:236` | DW |
| `core/unverified.php` | Unverified values (meta `aw_unverified`) and their mark | DW |
| `core/geocode.php` | Coordinates from Nominatim (one request a second, cron `aw_geocode_one` `:205`), `aw_listing_sources` filter `:255`, WP-CLI `aw geocode` `:279` | DW |
| `core/map-picker.php` | Admin map beside lat/lng (Leaflet 1.9.4 from `assets/vendor/`, `:20-37`) | DW |
| `core/local-cards.php` | "Few … listings" card on small districts (`render_block_core/query` `:25`) | DW |
| `core/district-helplines.php` | District helpline block (option `aw_district_helplines`, `render_block_core/query` prio 9 `:118`) | DW |

### 1.2 Parts of shared Core files

These files stay in Core; only their Directory parts move.

| File | Directory part | Goes to |
|---|---|---|
| `core/content-types.php` | `aw_listing` in `aw_post_types()` `:30`, rewrite `directory/org` `:45-48`, registered in the shared loop on `init` 10 `:81` | DW registers `aw_listing` itself. Core's loop skips it (§3.1) |
| `core/tags.php` | Listing types and services are **WordPress tags** (`post_tag`), not taxonomies: `aw_listing_type_slugs` `:112`, `aw_listing_types` `:148`, `aw_listing_service_names` `:153`, `AW_SERVICE_TYPE` `:184`. The type list comes from option `aw_sources` → sections → `aw_listing` → tags (`sources-config.php:15`) | Listing-type helpers to DW. Tag registration and generic tag helpers stay in Core |
| `core/meta.php` | Listing keys in `aw_meta_schema()` `:32-79`; `AW_NEVER_PUBLISH_PHONES` `:222` + `aw_phone_never_publish()` `:227`; phones/hours helpers `:241-386` | Listing meta and phone/hours helpers to DW. The takedown hashes are site data, so they move to an option (`aw_never_publish_phones`, same key name as today's filter `:232`), seeded from the constant |
| `core/scf.php` | `AW_SCF_GROUPS` `:64` (8 listing groups), `acf/settings/load_json` `:66`, `save_json` per group `:74`, listing hints in `acf/validate_value` `:181` | DW adds its own `load_json` path and save paths for its groups |
| `core/bindings.php` | Source `animal-writes/field` `:170`. Listing keys: verified_label, blacklisted_label, area, services, phone, phone_url, whatsapp_url, email_url, website (`:95-138`). Empty-hide `:196`, profile actions note `:235` | The listing keys move to a DW source (§1.6). The shared source stays in Core |
| `core/hub-blocks.php` | Registers every `animal-writes/*` block in one loop (`:21-93`). Directory blocks: `listing-*` ×13 `:51-63`, `claim-cta` `:64`, `listing-help` `:41`, `org-details` `:42`, `org-mark` `:33`, `badges` `:34`, `contact` `:35` (dead, returns ''), `org-actions` `:36`, `result-head` `:37`, `browse-states` `:38`, `list-filters` `:28` (aw_listing branch). Title marks: Claimed `:497-517`, Blacklisted `:464`, Reported closed `:480` | DW registers its blocks under the same names in phase 1 (saved content keeps working) |
| `core/hubs.php` | The Directory list pattern: card `:432-449`, result head `:485`, Query Loop `:497` (queryId 106, namespace `animal-writes/hub`, an attribute only), browse states `:518` | DW (phase 2: a registered Query Loop variation) |
| `core/sections.php` | `[aw_find_help]` `:334-389`, `aw_find_help_top_types` `:296`; `aw_directory_filter` called at `:168`, `:172`, `:216` | `[aw_find_help]` to DW. The section heads stay |
| `core/form-blocks.php` | `listing-help` callback `:63`, `aw_report_url` `:92`, `aw_form_page_listing` `:100`, `[aw_claim_page]` `:170-177` (on the /claim/ page in the database), claim thank-you `:219-222` | Claim parts to DW. Volunteer and report stay |
| `core/forms-fluentforms.php` | Shared Fluent Forms rules (mail off `:34-55`, honeypot/ALTCHA `:605`, uploads `:638-662`, `aw_ff_upload` `:762`). Claim branches: validation `aw_ff_errors` `:559`, CRM on submit `:687-737` (tag `claimant`, `aw_crm_claim_note`) | Stays in Core. DW hooks its claim parts on through `directoryweb_claim_*` filters in phase 1; the claim form ID becomes a DW setting (today `aw_ff_forms['claim']`, `claims-review.php:65`) |
| `core/fluentcrm.php` | Tags `claimant`, `org-pending`, `org-approved` `:121-124`, `aw_crm_claim_note` `:237`, fields `vol_org_*` `:366-370` | Claim and people parts to DW (behind `class_exists`). Tag names become claim-rule settings |
| `core/mailweb-aw.php` | Claim entries in Mail → Website forms (`mailweb_forms_source` `:168`, key `AW-C-` `:51`) | Stays in Core (it is the site's MailWeb glue) |
| `core/item-head.php`, `breadcrumbs.php`, `related.php`, `data-sources.php` | The item head's listing branches (meta row Claimed/Verified `:178-191`, sources `:312`, More `:465`), "More organisations in…" `related.php:255`, `aw_listing_sources` filter `data-sources.php:107`, district centroids reader `related.php:136` | The shared item head stays in Core and calls DW helpers. Centroids and the listing branch of related move to DW |
| `core/cron.php` | Re-verify flag `:50-66` (`AW_REVERIFY_DAYS` 180, `animal-writes-core.php:30`) | DW (claim rules setting) |
| `core/model.php`, `campaign-tools.php` | `AW_SERVICES` `:26`; campaign ↔ organisation links (`aw_organisation`, `aw_campaign_partners`…). SCF group `group_aw_campaign_orgs` is the campaign edit screen's message box (`campaign-tools.php:769`) | `AW_SERVICES` to DW. Campaign links and `group_aw_campaign_orgs` **stay in Core** (they belong to campaigns) |
| `core/admin-ia.php`, `admin-home.php`, `edit-screens.php` | Menu position of `edit.php?post_type=aw_listing` `:197`; Overview rows `:187-197`; `aw_edit_screen_own_plugins()` `:813` and `data/edit-screen-map.json` | Menu position to DW. Overview rows become a Kits status row. Update the edit-screen map's owner plugin for every listing box |
| `core/theme-parts-assets.php` | `aw-directory` and `aw-listing` style entries `:139-150`, handed to `kits_component_styles` `:223`; `kits_parts_page_kind` → 'directory' `:217` | DW adds its own entries to the same filter (§3.4) |
| `animal-writes-core.php` | The activation hook calls `aw_directory_rewrites()` and flushes rewrites `:149-158` | DW gets its own activation (rewrites, flush) |

### 1.3 Data and theme files

| File | Notes | Goes to |
|---|---|---|
| `acf-json/group_aw_listing*.json` (8) | Contact, Address and place, Opening hours, Registrations, About and services, Online, Photos and support, Claimed and sources. All `post_type == aw_listing`. Message fields rendered by other code: `field_aw_listing_msg_place` (places-admin), `_msg_provenance` (SourceWeb `enrichment-admin.php:128`), `_msg_gov_state` (gov-verified) | DW `acf-json/`, **same group and field keys** |
| `acf-json/group_aw_campaign_orgs.json` | Campaign screen box, not a listing group | Stays in Core |
| `data/lgd.json` | Read only by `aw_lgd()` (`places.php:58`); built by `tools/lgd/build_lgd.py`, checked by `tools/lgd/check.php`, read by `tools/directory/places.py:36`, `tools/awbi/match.py:6` | DW `data/lgd.json` (same file name). Tool paths updated in the same commit |
| `data/district-centroids.json` | `related.php:136-139` | DW `data/` |
| `theme/templates/single-aw_listing.html` | The only copy (the child theme's twin was removed with the child theme folder, 14 Nov 2026) | DW registers it as a plugin template (§3.3) |
| `theme/patterns/find-help.php` | `[aw_find_help compact="1"]`; identical copy in the child theme; on the front page `front-page.html:8` | DW registers the pattern |
| `theme/assets/css/aw-directory.css`, `aw-listing.css` (+ `.min.css`) | Only in the plugin's theme folder | DW `assets/css/` |
| `assets/css/dir-map.css`, `assets/js/dir-map.js`, `assets/js/map-picker.js` | Map, Near me, admin map picker | DW `assets/` |
| `assets/js/hub-blocks.js` | Editor previews (ServerSideRender) for every hub block | Stays in Core. DW ships its own copy for its blocks |

### 1.4 RankWeb (stays in RankWeb, reads DirectoryWeb)

- **Place pages** (`rw/programmatic.php`): `aw_seo_dir_data()` `:35` (raw SQL on `aw_listing`), title, H1, description and facts (`:298-467`); `render_block_core/query` `:532`. Also titles (`rw/titles.php:294-307`, `:444-456`, `:510-525`), canonicals (`rw/canonical.php:15-60`), breadcrumbs (`rw/breadcrumbs.php:56-108`), filtered views (`rw/filtered-views.php`).
- **Sitemap** (`rw/sitemap.php`): `directory-sitemap.xml` from `aw_sitemap_directory_urls()` `:41`, cron `aw_seo_sitemap_daily`; listings with no contact are dropped (`rw/robots.php:226`).
- **Schema** (`rw/schema.php`): `aw_listing_schema()` `:53` (NGO / VeterinaryCare / AnimalShelter / GovernmentOffice), output `:1065`; place pages as CollectionPage + ItemList `:1222`. RankWeb keeps owning the graph. DirectoryWeb emits no JSON-LD.
- **No-contact noindex** (`rw/robots.php`): `aw_listing_unreachable()` `:74` means contact score 0 and no social profile. The exemption list is option `aw_seo_keep_listings`, the daily score fill is `:104`, and the rule is applied on `rank_math/frontend/robots` `:140`. The thin type-page threshold `AW_THIN_DISTRICT_TYPE` (default 1) and `AW_DIRECTORY_KEEP_INDEXED` are in `aw_seo_settings` (`rw/settings.php:155-156`).
- **What changes:**
  - RankWeb today calls about 40 Directory functions directly: `aw_listing_*`, `aw_place_*`, `aw_directory_*`, `aw_current_place`, `aw_contact_score` and others (`grep -rn "aw_listing_\|aw_place_\|aw_directory_\|aw_current_place" plugins/aw-seo-extras/includes/`). They keep their names: only new code gets new names. RankWeb's calls work unchanged because Core requires DirectoryWeb.
  - The reverse calls become filters, so DirectoryWeb names no other plugin. `directory.php:228-245` calls `aw_seo_dir_h1()` / `aw_seo_place_has_helpline()`; that becomes `directoryweb_place_h1` and `directoryweb_place_has_helpline`, which RankWeb answers. `places.php:435` calls `aw_rank_math_redirect()` inside WP-CLI; that becomes `directoryweb_redirect_add`.
  - The indexing rule stays RankWeb's. DirectoryWeb asks through `directoryweb_listing_indexable` (RankWeb answers with `aw_listing_unreachable`), so the Dashboard tile and RankWeb never disagree.

### 1.5 SourceWeb (the enrichment engine stays in SourceWeb)

- **What stays:** table `{prefix}aw_enrich` (`sw/enrichment.php:129`); REST `aw/v1/enrich/{config,queue,results,index,cleanup}` `:1037-1045` (cap `edit_others_posts`); the public `aw/v1/listing-live` (`sw/enrichment-front.php:121`); ingest `type=listing` (`sw/ingest.php:337-347`, `:449`); clean-up proposals (`sw/enrichment-cleanup.php`).
- **The Enrichment review screen stays in SourceWeb** (`admin.php?page=aw-enrich-review`, under the SourceWeb menu `aw-sourcing-menu`, `sw/enrichment-admin.php:187`). It already has per-field Approve, Reject and Undo, with bulk actions (`:345-365`). DirectoryWeb's Dashboard tile links to it and does not copy it.
- **What DirectoryWeb exposes for SourceWeb:**
  - **The field registry.** `directoryweb_fields()` returns the listing types and their fields from the SCF groups. Today SourceWeb globs `AW_CORE_DIR . 'acf-json/group_aw_listing*.json'` (`sw/rules.php:192`, `tests/rules-tests.php:14`) and sets `AW_ENRICH_LISTING_GROUP` `:24`. Both must switch in the **same commit** that moves the JSON, or SourceWeb's Rules tab loses the 42 Directory field rules.
  - **The helpers, unchanged:** `aw_listing_phones`, `aw_listing_hours`, `aw_listing_place`, `aw_listing_types`, `aw_set_listing_list`, `aw_listing_put_phone`, `aw_unverified_*`, `aw_phone_never_publish`, `aw_place_*`, `aw_lgd`, `aw_item_codes`, `aw_set_item_places`, `aw_places_from_text`, `aw_service_slugs`.
  - **The hooks, unchanged:** `aw_claim_before_apply` (`claims.php:191`, used at `sw/enrichment.php:996`) and the `aw_listing_sources` filter.
  - **The edit-screen message field** `field_aw_listing_msg_provenance`, same key.
- **DirectoryWeb uses SourceWeb's change log.** `claims-review.php:497-502`, `:612` call `aw_enrich_add_row`, `aw_enrich_snapshot`, `aw_enrich_install` and `aw_enrich_after_change`, behind `function_exists`. In phase 1 they become one action, `directoryweb_field_changed`, that SourceWeb records. Without SourceWeb a claim still applies, but has no Undo, and the Claims list says so.
- **Kept as is:** `aw_cdn_purge_post()` is called at `sw/enrichment.php:567` and defined nowhere (`docs/pending.md`). It is SourceWeb's and not part of this move.

### 1.6 Blocks, shortcodes, bindings and their phase 2 target

Phase 1 moves these with **the same names** (`animal-writes/*`, the same shortcodes), so saved content and templates keep working. Phase 2 rebuilds each one as WordPress-native as it can go. **New names:** block namespace `directoryweb/`, binding source `directoryweb/listing`.

| Today | Where used | Phase 2 target |
|---|---|---|
| `listing-contact` | profile template | **Core block + Block Binding.** Core Buttons and Paragraphs bound to phone, phone_url, whatsapp_url, email_url, website (the keys already exist in `bindings.php`). Empty-hide and the unverified mark stay as binding behaviour |
| `listing-about` | profile | **Core blocks.** Featured Image + Post Content / Excerpt. The template-intro check (`listing-template.php:108`) becomes a binding |
| `listing-registrations`, `listing-organisation`, `listing-location`, `listing-services`, `listing-profiles`, `listing-support`, `listing-activity` | profile | **One custom block, `directoryweb/fields`, with a `group` attribute.** WordPress has no "print the filled fields of this group, skip the empty ones" block. Rows come from the listing type's fields (Settings), so a new listing type needs no new block |
| `listing-photos` | profile | **Custom block (`directoryweb/photos`).** The core Gallery block cannot bind to a meta list. It drops stock images (`:349`) |
| `listing-press` ("Featured in"), `listing-opportunities`, `listing-campaigns` | profile | **Query Loop variations** (`directoryweb/featured-in`, `/opportunities`, `/campaigns`), with the query set through `query_loop_block_query_vars` from `aw_featured_items()` and the `aw_organisation` / partner meta |
| `listing-help`, `claim-cta` | profile (`claim-cta` sits in no template) | **Pattern + Block Binding.** Group, Paragraph and Button bound to the claim URL; hidden once claimed by a `claimed` binding |
| `[aw_org_people]` | profile `:51` | **Custom block (`directoryweb/people`).** It reads FluentCRM; nothing native does |
| `org-details` | no template | **Delete** |
| `contact` | dead (`hub-blocks.php:367` returns '') | **Delete** |
| Directory list: `org-mark`, `badges`, `org-actions`, card in `hubs.php:432-449` | Directory list | **Query Loop variation `directoryweb/listings`** with the query from `aw_directory_query_vars`. Card: Post Title, Featured Image (initials fallback through a binding), Paragraph bound to `area`, Buttons bound to contact. Type badges become a Paragraph bound to `type_labels` |
| Claimed / Verified / Blacklisted / Reported closed title marks (`render_block_core/post-title` at `hub-blocks.php:501` prio 25, `gov-verified.php:167` prio 26, `:464`, `:480`) | cards and titles | **Custom block `directoryweb/status`,** placed after the title in the card and the profile. It replaces four title filters, so the title stays plain |
| `result-head` (count, sort, map, Near me), `browse-states` | Directory list | **Custom blocks** (`directoryweb/result-head`, `/browse-places`). They use the map script and place counts |
| `[aw_directory_filter]`, `list-filters` (aw_listing branch) | Directory list | **`kits/collection-filter`**, a Kits Core dependency (§0.2). Until it ships, the shortcode stays |
| `[aw_find_help]` + pattern `animal-writes/find-help` | front page | **Pattern (`directoryweb/find-help`) holding a custom block `directoryweb/find-help`** (place counts and top types) |
| `[aw_claim_page]` | /claim/ page content (database) | **Pattern: the Fluent Forms block (form #6) + a custom block `directoryweb/listing-finder`** (the organisation finder `claims.php:117`). Pre-fill stays a Fluent Forms filter |
| Local cards, district helplines (filters on `core/query`) | district pages | **Custom blocks `directoryweb/local-card` and `/helplines`** placed in the district pattern, in place of filters that inject HTML into the Query Loop |

### 1.7 Badges, records, claim flow (facts the move must keep)

- **Claimed** means meta `aw_verified`, set only by claim approval (`claims-review.php:494-576`). Admin label "Claimed" (group `group_aw_listing_claimed`). The meta row shows "Claimed on {date}" or "Needs re-check" (`item-head.php:184-191`); listings flagged by the daily re-verify cron after 180 days show "Needs re-check".
- **Verified** means the registration details come from government documents: meta `aw_gov_verified_on`/`_by` (`gov-verified.php`). Sources `darpan`, `datagov`, `nalsa`, `gujforest`, `gov` (`:25`). Edit-screen box "From government records" (`:222`).
- **No completeness badge.**
- **A claim** is a Fluent Forms entry of form #6. Its decision is stored as submission meta `aw_claim_review` (`{by, at, status, fields[]}`, `:618`); its status is pending, approved or rejected.
- **Per-field approval:** only fields that differ from the listing get a tick (`aw_claim_changes` `:271`). Each applied field first logs a row with its old value in the enrichment table, which is what makes **Undo** possible (SourceWeb `aw_enrich_undo`, `sw/enrichment.php:818`).
- **Undo restores one field and never clears the Claimed mark** (owner, 11 Nov 2026). Taking a claim off a listing is a separate action, **Remove claim** (§2.2):
  - It clears `aw_verified`, `aw_verified_on` and `aw_needs_reverify`, and clears the `aw_verified_count` cache.
  - It ends the claimant's link to the listing's people: tag `org-approved` and `vol_org_listing` for this listing. The FluentCRM contact itself is kept.
  - It records `removed`, with who and when, in the claim's review (`aw_claim_review`, or `aw_c_status` on an old-form claim). The entry stays.
  - It leaves every applied field as it is; fields are put back one by one with Undo.
- **No email is sent** by any form, claim or approval (`aw_ff_mail_off`, option `aw_ff_send_email` = 0).
- **Legacy `aw_claim` posts** stay readable. One gap: `aw_org_people_from_claim()` is called at `claims.php:226-227` (guarded) and defined nowhere, so a legacy approval never adds the claimant as a person. The new form's approval (`claims-review.php:604-610`) links the claimant only when they ticked "Show my name".
  - **This is being fixed now**, in its own fix on `wordpress-dev`, outside this plan. Every approved claimant is linked to the listing privately (FluentCRM contact ↔ listing), and their name shows only if they opted in.
  - Past approvals get a backfill, with a dry run first; nothing is written until the owner has seen the count.
  - The move to DirectoryWeb keeps that fix: the function moves with `org-people.php`.

### 1.8 Fluent Forms, FluentCRM, workers' routes

- **Forms:**
  - #6 Claim this listing (`forms/6-claim-this-listing.json`).
  - #5 Volunteer, whose organisation picker uses `aw_org_search`.
  - #4 Contact, whose "List your organisation" reason carries the `org_*` fields.
  - #7 (inactive).
  - Only #6's hooks move. #4 and #5 stay with Core and call DW's `aw_org_search_public()`.
- **FluentCRM:** see §1.2. Every call stays behind `class_exists`.
- **Routes (`aw/v1`, unchanged addresses):**
  - DW: `dir-points` and `dir-near` (public GET), the admin-ajax `aw_pin` (public), `aw_pin_lookup` (admin) and `aw_org_search` (public, rate-limited).
  - SW, unchanged: `enrich/*`, `listing-live`, `ingest`, `exists`, `worker-run`.
  - The worker `directory-enrichment.yml` (`sw/workers-registry.php:70-78`) talks only to SourceWeb's routes.

### 1.9 Animal Writes text that must leave the plugin's code

In phase 1 these become settings or data. Each starts with today's value, supplied by Core through one filter, `directoryweb_site_config`, until phase 3's Settings store them:
- **Place sources:** the LGD file and India Post (`api.postalpincode.in`, `places.php:469`); the PIN pattern `^[1-9]\d{5}$`.
- **Geocoder:** Nominatim with `countrycodes=in`, and the User-Agent and contact `AW_GEO_UA` (`geocode.php:32`, `:43`, `:83`); the India bounding box (`directory-map.php:205`).
- **Map and assets:** the tile style URL (`directory-map.php:27`) becomes a setting (§2.3). The map library (`:26`) is **not** a setting: MapLibre 4.7.1 and Leaflet 1.9.4 are served from the plugin's `assets/vendor/` (npm package files, sha256 in each folder's README; 13 Nov 2026), so no CDN, integrity or crossorigin applies; they move with the plugin.
- **Registrations:** AWBI, 12A, 80G, FCRA, NGO Darpan, MHA, with issuer slugs and labels (`listing-template.php:113`, `:147-153`; `gov-verified.php:25-28`), and the government source list.
- **Helplines:** 112 and 1962 (`district-helplines.php:103-107`).
- **Copy:** "Animal Helpline Numbers in …" / "Help in …" (`directory.php:226-248`), "Animal welfare directory: %s" (`:307`), the intro (`:644-650`), the "Blue Cross" alias and placeholder (`:414`, `:575`), redirect-by "Animal Writes" (`:105`), "Claimed only" / "Verified only" (`:581-582`).
- **City and neighbourhood lists:** `AW_PLACE_DETECT_CITIES`, `AW_DISTRICT_COMMON_NAMES`, and the Hindi stop-words in `AW_FEATURED_COMMON`.
- **Fixed paths:** `/directory/`, `/claim/`, `/volunteer/`, `/contact/?about=listing`; claim role list `AW_CLAIM_ROLES`; the `+91` phone handling.
- **What is not text to move:** the data keys (`aw_*` meta, options, `aw/v1`, `aw_listing`, block names in saved content). They stay as they are (owner).

## 2. Admin: what DirectoryWeb registers into the Kits Core shell

**DirectoryWeb only registers parts into the shell.** The shell and its look are Kits Core's: the Kits admin design system (Google Site Kit pattern for plugins), with WordPress packages as plumbing only (`@wordpress/data`, `api-fetch`, `i18n`, `icons`). Its parts are the app header, Dashboard chips and tiles, "Select metrics", the working list (search, filters, sort, columns, bulk and row actions, pagination, saved views), Settings rows, the setup flow, notices and the empty/loading/error states. DirectoryWeb designs none of it. It registers screens, tiles, list columns, settings groups and services through the Kits Core PHP/JS API, and serves its data from its own REST routes. New routes are under its own namespace, `directoryweb/v1/…`; the existing `aw/v1` addresses do not change.

In the shell, DirectoryWeb is **fifth**: InsightWeb, RankWeb, SourceWeb, MailWeb, DirectoryWeb, PlanWeb. The six Kits plugin plans share this UI standard and the same review skills (plugin audit, release, testing, coding standards, database, i18n, build tools, structured data, GitHub flow).

**Menu:** DirectoryWeb → Dashboard · Claims · Settings. Listings stay in WordPress's own list (`edit.php?post_type=aw_listing`), linked from the header. The old address `edit.php?post_type=aw_listing&page=aw-claims` redirects to Claims.

### 2.1 Dashboard tiles

Each tile has a number, a sub-line, the source it names and a cached REST route with a refresh.

| Section chip | Tile | Number and sub-line | Source named | Route |
|---|---|---|---|---|
| Listings | Published listings | count, by type (top 5 + others) | DirectoryWeb (posts + listing-type tags) | `GET /directoryweb/v1/stats/listings` |
| Listings | By state | published / draft / pending / private | WordPress post status | same |
| Listings | Places covered | states and districts with a listing, of the LGD total | DirectoryWeb places (`aw_place_counts`) + LGD file version | `/stats/places` |
| Indexing | Indexable vs no contact | indexable, no-contact (noindex), exempt (`aw_seo_keep_listings`) | **RankWeb** rule through `directoryweb_listing_indexable` (shows "RankWeb not active" otherwise) | `/stats/indexing` |
| Indexing | Directory search clicks (28 days) | clicks, impressions on /directory/ paths | **InsightWeb** (Search Console), through its own filter or route; hidden until InsightWeb is active | `/stats/search` |
| Claims | Claims waiting | pending count, oldest age | Fluent Forms form #6 + `aw_claim_review` | `/stats/claims` |
| Claims | Claimed / Verified | claimed (`aw_verified`), verified (`aw_gov_verified_on`), needs re-check | DirectoryWeb meta | `/stats/badges` |
| Enrichment | Enrichment queue | waiting proposals, Google-pending listings, last run | **SourceWeb** (`aw_enrich`, `aw_enrich_google_pending`); links to SourceWeb → Enrichment review | `/stats/enrichment` (empty with "SourceWeb not active") |

### 2.2 Working list: Claims (the shell's working list, over REST)

- **Rows:** one Fluent Forms entry of form #6, plus legacy `aw_claim` posts marked "old form".
- **Columns:** date, listing (link), claimant name and role, contact (email/phone), fields changed (count), status, decided by/at.
- **Filters:** status (pending, approved, rejected), listing state/district, listing type, has logo/photos/proof, date range. Search: listing name, claimant.
- **Row view:** the per-field review as today (`aw_claim_changes`): listing value against claimed value, one tick per field, "Show my name" (people box). Approve applies the ticked fields; each one logs its old value through `directoryweb_field_changed`.
- **Undo:** per field, on approved fields (through SourceWeb's change log; off, with a note, when SourceWeb is inactive). Undo never clears the Claimed mark, even on the last field.
- **Remove claim:** a row action on an approved claim that takes the claim back.
  - It asks for confirmation before it runs, naming the listing and the claimant.
  - The claimant loses the edit rights the claim gave and is removed from the listing's people.
  - The listing itself stays, with its published status and its fields.
  - Who removed it and when is logged in the claim's review.
  - It does what §1.7 says, as `POST /directoryweb/v1/claims/{id}/remove`. The Claimed / Verified tile and the listing's meta row follow at once.
- **Bulk actions:** Reject; Approve all changed fields (asks first, lists the listings); Mark as seen.
- **REST:**
  - `GET /directoryweb/v1/claims`, `GET /claims/{id}`
  - `POST /claims/{id}/decide` (`fields[]`, `status`)
  - `POST /claims/{id}/undo` (`field`)
  - `POST /claims/{id}/remove`
  - Permission is `edit_aw_volunteers` (today's capability), and every write checks the REST nonce.

### 2.3 Settings

**Connected services, first, each a row with a status:**

| Service | Status shows | Connect / Edit |
|---|---|---|
| Place source | "LGD, {file date}, {n} states / {n} districts" | Choose the place file (data file in the plugin; a different country ships its own file) and its version |
| PIN code lookup | India Post on/off, last answer | On/off, rate limit |
| Geocoder | Nominatim, last request, cache days | Contact address for the User-Agent, country code, on/off |
| Map tiles | OpenFreeMap | Tile style URL only, picked from an allowed list of hosts (OpenFreeMap by default), never free text. The library URL, version and `integrity` hash are fixed in code |
| Fluent Forms | Claim form #{id} found / missing | Pick the claim form |
| FluentCRM | active / not, tags found | Claimant / pending / approved tag names |
| SourceWeb | active, queue size | Link to SourceWeb Settings (enrichment sources and keys stay there) |
| RankWeb | active, indexing rule summary | Link to RankWeb Settings (the rule and its threshold stay there) |
| InsightWeb | active / not | Link |

**Admin settings groups, after the services:**
- **Listing types:** the type list (today `aw_sources` → sections → `aw_listing` → tags) and each type's own fields. A type-only SCF box is shown by an SCF location rule on that type's tag, next to the 8 shared boxes.
- **Places:** place types that carry a place (`AW_PLACE_TYPES`), district common names, place detection lists.
- **Claim rules:** roles, re-verify days (180), "Show my name" default. Undo never un-claims; Remove claim does (§1.7).
- **Badges:** Claimed label, Verified sources (government source list), the never-publish phone list (hashes only; the number is never shown again).
- **Indexing:** read-only summary of RankWeb's rule with a link (it stays RankWeb's).
- **Find help:** the number of states/places shown (`AW_FINDHELP_MAX`), types offered, where the links appear (item More, campaign band).
- **Directory copy:** titles, intro, search aliases, helplines (112/1962 rows), redirect-by.

**What all settings follow:**
- Every setting goes through `register_setting( …, array( 'show_in_rest' => … ) )` and **keeps its current option name**. Today's constants (`AW_VET_TYPES`, `AW_SEARCH_NAME_ALIASES`, `AW_FINDHELP_MAX`, `AW_REVERIFY_DAYS`, `AW_CLAIM_ROLES`…) become defaults, so no saved value is lost.
- Keys never reach the browser.

**Technical (per the standard):**
- Capability and nonce per screen: `manage_directoryweb` for Claims (was `edit_aw_volunteers` until 13 Nov 2026), `manage_options` for Settings.
- JS translations through `wp_set_script_translations( …, 'directoryweb' )`.
- One script per screen, which only registers DirectoryWeb's parts with the shell. Its dependencies are the shell's script and the plumbing packages (`@wordpress/data`, `api-fetch`, `i18n`, `icons`), built through `@wordpress/scripts` (with `.asset.php`). It has no styles of its own.
- Performance budget per screen as the shell sets it.
- Tests (plugin testing): today's Deploy check has no PHPUnit and no WordPress test suite; it runs lint, PHPCS security, pure-PHP tests and `tools/ci/smoke.sh`. So the proofs go there, with no new CI setup:
  - **Pure-PHP tests** in `plugins/directoryweb/tests/` (one Deploy check step, as `animal-writes-core/tests/org-people-tests.php`): the claim apply, Undo and Remove claim decisions as pure functions (which fields change, that Undo never clears Claimed, what Remove claim clears); the tile-style host allow-list; the settings defaults from today's constants. The SourceWeb rules tests point at `directoryweb_fields()`.
  - **`smoke.sh`** (real WordPress and MySQL on the runner): `wp plugin activate directoryweb` after Kits Core and Animal Writes Core; every Directory page type loads (list, a listing, a place page, Find help, the sitemap); each `directoryweb/v1` write answers 401/403 signed out and as a subscriber; the post type, rewrites and template exist once.
  - **Plugin Check** (`wp plugin install plugin-check --activate`, `wp plugin check directoryweb`) runs in `smoke.sh` in report mode in phase 1 (red on a new finding, not stopping the deploy, as the Settings parts step), and becomes blocking once its first findings are fixed.

### 2.4 Requests to Kits Core: parts the design system may lack

Before DirectoryWeb's admin phase, these are asked of Kits Core. DirectoryWeb does not build them itself.
1. **A field-by-field review view** for one row of the working list: the current value beside the proposed value, a tick per field, apply the ticked fields, Undo per applied field. Claims need it; SourceWeb's Enrichment review would use the same part.
2. **A row status that says why an action is off**, for example "Undo needs SourceWeb", inside the list.
3. **A data-file service row:** a bundled file with its date and counts (the place file), with no account to connect.
4. **A write-only list setting:** values stored as hashes and never shown again, with add and remove (the never-publish phone list).
5. **A read-only setting that links to another Kits plugin's setting** (the indexing rule, which stays RankWeb's).
6. **A tile that hides itself, or says "{plugin} not active",** when its source plugin is missing (the RankWeb, InsightWeb and SourceWeb tiles).

## 3. Moving it out of Animal Writes Core

### 3.1 Registration order: never twice, never missing

How plugins load today:
- WordPress loads active plugins in the order of `active_plugins`, which is alphabetical by folder.
- `Requires Plugins` (WordPress 6.5+) blocks activation; it **does not change load order**.
- Core already loads Kits Core and SourceWeb with a guarded `require_once` (`animal-writes-core.php:39-41`, `:143-147`), and each guards itself with a constant (`KITS_CORE_LOADED`, `AW_SOURCING_LOADED`).

The plan:
1. **Folder and slug: `plugins/directoryweb/`, main file `directoryweb.php`, text domain `directoryweb`.**
   - It starts with `if ( defined( 'DIRECTORYWEB_LOADED' ) ) return; define( 'DIRECTORYWEB_LOADED', '1.0.0' );`.
   - **No `Requires Plugins: kits-core` in phase 1** (owner, 11 Nov). It checks for Kits Core at runtime (`KITS_CORE_LOADED`), as SourceWeb does (`aw-sourcing.php:21-30`). Core loads Kits Core itself, so the header could block activation. It is added only once `wp plugin list` (read-only) shows Kits Core active as its own plugin, in the small header ship of step 7.
   - **Header (plugin audit):** a `/** … @wordpress-plugin */` block with `@package directoryweb`, `Version`, `Requires at least: 6.7` (for `register_block_template`; Core says 6.5), `Requires PHP: 7.4` (as Core), `Text Domain: directoryweb`, `License: GPL-2.0-or-later`, `Domain Path: /languages`, `Update URI: https://wugweb.com/plugins/directoryweb/` (as the other five plugins); `Requires Plugins: kits-core` only later, as above. The version constant `DIRECTORYWEB_VERSION` equals the header `Version`.
   - **Release files (plugin release), as MailWeb has them** (the only plugin that does): `readme.txt` with `Stable tag` equal to the header `Version`, `Requires at least`, `Requires PHP`, a Changelog and an Upgrade Notice, starting at `1.0.0`; `languages/directoryweb.pot` made with `wp i18n make-pot . languages/directoryweb.pot --domain=directoryweb` (a `make-pot` script in `composer.json`, as `plugins/mailweb/composer.json:27`), never edited by hand. Each release bumps header, constant, `Stable tag`, changelog and `.pot` together.
   - **Icon** (from `docs/brand/plugin-icons/`: a map pin with a yellow dot): `assets/icon.svg`, `assets/icon-256x256.png`, `assets/icon-128x128.png` in the plugin. It is registered with the shell for the Kits → Plugins row and the shell's app header; DirectoryWeb draws neither.
   - **No custom tables** (database): the claim decision stays in submission meta, the change log in SourceWeb's `{prefix}aw_enrich`, so there is no `dbDelta` and no schema version. Its own new options are only `directoryweb_rewrite_ver` and the Settings values (existing option names, §2.3); new ones are saved with autoload off.
   - **Deactivation and uninstall never remove data** (never delete user data): deactivation only flushes rewrites; there is no `uninstall.php` that deletes listings, meta, options or claims.
   - **Audit points for the PR** (plugin audit): every `directoryweb/v1` write has a `permission_callback` with the capability (`manage_directoryweb` or `manage_options`), not only the REST nonce; every public `wp_ajax_nopriv_` route (Find help, PIN and organisation lookups) stays read-only and rate-limited as today; output escaped late; `$wpdb` only through `prepare()`; strings use the `directoryweb` text domain, with translator comments on placeholders.
2. **Core loads DirectoryWeb exactly like SourceWeb.** At the place where the Directory files are required today (`animal-writes-core.php:58-64`), it does `if ( ! defined( 'DIRECTORYWEB_LOADED' ) && file_exists( dirname( AW_CORE_DIR ) . '/directoryweb/directoryweb.php' ) ) require_once …`.
   - On the first deploy the post type, fields and routes are therefore there before anyone activates the plugin. Plugins on the server are links into the deploy checkout, and a new folder has no link until it is made (hPanel), so this matters.
   - Once DirectoryWeb is active on its own and loads after Core (`a` < `d`), its own load returns at the constant.
   - If it ever loads first, Core's `require_once` is a no-op.
   - Either way the code runs once.
3. **The move is one commit: files deleted from Core and added to DirectoryWeb together.** No file exists in both places in any commit, so no function is declared twice. Moved functions keep their `aw_` names; RankWeb and SourceWeb call them.
4. **`aw_listing` leaves Core's type loop.** `aw_post_types()` keeps its entry for the places that read the list. `aw_register_content_types()` skips `aw_listing` when `DIRECTORYWEB_LOADED` is defined, and DirectoryWeb registers it on `init` priority 10 with **identical arguments**: rewrite `directory/org`, `has_archive` 'directory', supports, menu icon, `show_in_rest`, category and tag. One function builds the arguments, so they cannot drift.
5. **Hook priorities stay as they are:**
   - rewrites on `init` 20;
   - tags on `init` 21;
   - patterns on `init` 9;
   - blocks on `init` 10;
   - bindings on `init`;
   - meta on `init`;
   - the title marks at 25, 26 and 30.
   Core's activation-hook calls to `aw_directory_rewrites()` move to DirectoryWeb's activation, with a one-time `flush_rewrite_rules()` behind an option flag (`directoryweb_rewrite_ver`). The first deploy flushes once, the same way `aw_places_rewrite` does (`places.php:366`).
6. **SCF:** DirectoryWeb adds `acf/settings/load_json` → its own `acf-json` and the save path for its 8 groups. Core removes the 8 from `AW_SCF_GROUPS`. Group and field keys are identical, so SCF sees the same groups once. In the same commit, SourceWeb's `rules.php:192` and `tests/rules-tests.php:14` switch to `directoryweb_fields()` / `DIRECTORYWEB_DIR`.
7. **Header order:**
   - **First ship:** Core does the bundled require.
   - **Owner:** adds the plugin link in hPanel and activates DirectoryWeb.
   - **Second, small ship:** Core's header gains `Requires Plugins: directoryweb`; DirectoryWeb's gains `Requires Plugins: kits-core` after the check below.

   Adding the header to an already active Core with DirectoryWeb inactive would only show a notice. Adding it after activation keeps the Plugins screen clean. Two checks come first:
   - **Kits Core activation:** DirectoryWeb's `Requires Plugins: kits-core` is added in this same small ship, and only if `wp plugin list` (read-only) then shows Kits Core active as its own plugin (it did on 11 Nov 2026). Otherwise the header waits and the runtime check (`KITS_CORE_LOADED`) alone holds; the runtime check stays either way.
   - **The real folder name** is checked on the server at the same time.

### 3.2 Animal Writes Core

- **Requires Plugins:** Core gains `Requires Plugins: directoryweb` (third step above).
- **What Core keeps:** the item head, hubs, Fluent Forms and FluentCRM glue, campaigns and `group_aw_campaign_orgs`. These call DirectoryWeb helpers.
- **What Core supplies:** today's Animal Writes values, through `directoryweb_site_config` (§1.9), from `core/kits-config.php`, where Core already configures Kits.
- **Edit-screen map:** `data/edit-screen-map.json` and `aw_edit_screen_own_plugins()` list DirectoryWeb as the owner of the 8 listing boxes, the Place box, People and the gov-records box.

### 3.3 Templates and patterns from the plugin

- **The template:** `single-aw_listing.html` is registered with `register_block_template( 'directoryweb//single-aw_listing', … )` (WordPress 6.7+).
  - Live runs `kits-by-wugweb` with the child theme **inactive** (11 Nov 2026). The page is served today from Core's `theme/templates/single-aw_listing.html`, through `theme-parts-templates.php`, which ranks its files above Kits' files and above plugin-registered templates of the same slug.
  - So Core's copy is deleted in the same commit as the plugin registers its own; otherwise Core's file keeps winning and the plugin's is never used. Kits has no `single-aw_listing.html`, so nothing else competes.
  - The child theme's copy is gone: the child theme folder was removed on 14 Nov 2026.
  - The 0-difference check runs on staging (refreshed from live) before and after this commit.
- **Before phase 1, check whether the live database holds an edited copy of this template.** An edited copy (a `wp_template` post) would win over both files.
- **The pattern:** `find-help` is registered by DirectoryWeb on `init` 9 under the **same slug** `animal-writes/find-help`, because `front-page.html:8` references it. Core and the child stop registering it in the same commit.

### 3.4 CSS through the component loader

- **What moves:** `aw-directory.css` and `aw-listing.css` (and their `.min.css`) move to `directoryweb/assets/css/`.
- **How they register:** DirectoryWeb adds them to the **theme's** `kits_component_styles` filter. The entries are the same as today (`theme-parts-assets.php:139-150`: the `when` conditions, the `core/post-template` → `aw-orgs` block flag, deps `aw-components`), with `src`/`file` pointing at the plugin. Core drops its two entries in the same commit.
- **The page kind:** `kits_parts_page_kind` → 'directory' moves with them, so the joined file per page kind stays the same.
- **Without the Kits theme:** the filter does not exist, and DirectoryWeb enqueues its two files with the same `when` conditions (`wp_enqueue_scripts`, priority 20). When Kits Core takes the loader over, only the filter's owner changes.
- **Map scripts:** `dir-map.js`, `dir-map.css` and `map-picker.js` keep their handles (`aw-dir-map`, `aw-map-picker`) and load only where they do today: the Directory result head, and the listing edit screen.

### 3.5 Database backup first

Before each ship of phase 1 and 2:
- `wp db export` to a **file** (never piped to gzip; that gives an empty file), size checked, kept off the web root.
- UpdraftPlus's own backup on the owner's side.
- Nothing in this move writes to the database, apart from the one-time rewrite flush and the new options seeded from constants. The backup is the way back if a template or option goes wrong.

### 3.6 Proof: 0 differences

**Where:** on the copy chosen in §0.3, before and after.

**Pages:**
- `/directory/`
- a state (`/directory/kerala/`)
- a district (`/directory/kerala/ernakulam/`)
- a district × type (`/directory/kerala/ernakulam/veterinary-hospital/`)
- a listing with every group filled, and a no-contact listing (noindex)
- `/claim/?listing={id}`
- the front page (Find help)
- one News item with "Find help near you"

**Widths:** 375, 768, 1280.

**What is compared:**
1. **The HTML**, signed out, nonces and timestamps masked: 0 differences.
2. **Screenshots** in the built-in browser, compared pixel by pixel: 0 differences.
3. **The head:** `robots`, canonical, title, JSON-LD (RankWeb's), `directory-sitemap.xml` and the listing sitemap: identical.
4. **The REST answers** of `dir-points` and `dir-near`, a PIN search and the `enrich/config` field list: identical.
5. **Admin:** the listing edit screen shows the same 8 boxes, Place, People, gov records and provenance; Claims list and one claim's review; SourceWeb → Rules shows the 42 Directory field rules; PHP error log empty.

**Plus:** lint, PHPCS on changed files, the DirectoryWeb pure-PHP tests, `smoke.sh` with DirectoryWeb active, Plugin Check (report), and the Deploy check green.

## 4. Phases

Each phase is complete before the next. Each ships on the owner's yes, through the Deploy check, never while a FluentCRM email is sending (the deploy gate `aw/v1/deploy-status` already waits). Live checks are spaced out (one ship, check, then the next).

| Phase | What | Size | Risk and how it is held |
|---|---|---|---|
| **1. Move the code, no visible change** | §3 in full: new plugin folder, the 17 files, the Directory parts of the shared files in §1.2, 8 SCF groups, 2 data files, template, pattern, CSS, scripts; filters in place of the calls into RankWeb and SourceWeb; site text into `directoryweb_site_config`; SourceWeb `rules.php` and test path; tool paths; `readme.txt`, `.pot` and icon; the tests step and `wp plugin activate directoryweb` in `smoke.sh`, with Plugin Check in report mode; dead `aw_featured_html` removed (owner's yes). No `Requires Plugins` header in the first ship (runtime check `KITS_CORE_LOADED`). Then the plugin link + activation (owner), then the `Requires Plugins` headers (small ship: `directoryweb` on Core; `kits-core` on DirectoryWeb only once `wp plugin list` shows Kits Core active on its own) | **Large**: about 5,500 lines moved, ~25 files touched; one PR (bigger technical work: branch → PR → code review, plugin audit, PHPCS, Deploy check, owner merges) | **High** if done loosely: post type, rewrites, template or SCF could go missing for a request. Held by the bundled require (§3.1), one commit with no duplicate file, identical keys and arguments, backup, and the §3.6 proof |
| **2. Blocks and patterns** | §1.6: core blocks + bindings (`directoryweb/listing` source), the `directoryweb/fields` block, Query Loop variations, the claim and Find help patterns, the status block in place of 4 title filters, local card and helplines as blocks; template rewritten with them; old `animal-writes/*` blocks kept registered (rendering the same) so saved content and the child theme's copy keep working; `org-details` and `contact` deleted. `kits/collection-filter` only when Kits Core ships it | **Medium–large**: one PR, possibly two (profile; list and places) | **Medium**: markup changes. Held by the same 0-difference proof (HTML may differ by class names only where the CSS is adjusted in the same change; screenshots must match) |
| **3. Admin UI** | §2, registered into the Kits Core shell (DirectoryWeb only registers parts): Dashboard tiles, the Claims working list, Settings, the §2.4 requests answered by Kits Core first, the `directoryweb/v1` routes, old Claims address redirect, settings seeded from constants with no value lost, tests | **Medium**, depends on the Kits Core shell | **Low–medium** (admin only). **Order:**
  1. InsightWeb is extracted first, with no redesign (`docs/insightweb-plan.md`).
  2. The Kits Core shell is piloted on InsightWeb only after that extraction passes its before/after check.
  3. Then RankWeb, SourceWeb, MailWeb, **then DirectoryWeb**, then PlanWeb. Phase 3 waits for its turn. Phases 1 and 2 do not depend on the shell |

## 5. Not in this plan (recorded, not done)

- The aw→kits rename of keys, block names and routes (deferred by the owner).
- `aw_org_people_from_claim()` missing (§1.7): being fixed now in its own fix on `wordpress-dev`, with a dry-run backfill of past approvals before anything is written; the move keeps it.
- `aw_cdn_purge_post()` missing (§1.5).
- `docs/listing-template.md` is out of date in two lines: `:13` still says "Verified on", where the code now prints "Claimed on" plus a separate government "Verified on"; `:22` still lists Donate and UPI, which were dropped 31 Oct. It is updated with phase 1.
