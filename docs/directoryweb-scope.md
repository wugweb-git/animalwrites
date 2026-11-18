# DirectoryWeb: the last Directory code leaves Animal Writes Core (scope, 18 Nov 2026)

**Goal.** Retire the `animal-writes-core` tenant plugin piece by piece. This document covers the Directory piece: what
of the Directory is still in Core, what it depends on and what depends on it, and how it moves into DirectoryWeb
(`plugins/directoryweb`, Plugin Name "DirectoryWeb", 1.0.0, text domain `directoryweb`) without touching data.

**Checked against** `origin/wordpress-dev` at `73bbc87c` (18 Nov 2026). Every `file:line` below was read on that commit.
Paths: `core/` = `plugins/animal-writes-core/includes/`, `dw/` = `plugins/directoryweb/includes/`,
`rw/` = `plugins/aw-seo-extras/includes/` (RankWeb), `sw/` = `plugins/aw-sourcing/includes/` (SourceWeb).

**Where we start.** Most of the Directory already moved (DirectoryWeb 1.0.0, live since 17 Nov): 28 files, 9,372 lines in
`dw/`, the 8 SCF listing groups in `plugins/directoryweb/acf-json/`, `lgd.json` and `district-centroids.json`, the map
assets, the admin (Dashboard, Listings, Claims, Settings, Place intros) and the REST routes. Core still loads
DirectoryWeb from the sibling folder (`plugins/animal-writes-core/animal-writes-core.php:61-77`), and DirectoryWeb still
needs Core (`plugins/directoryweb/directoryweb.php:35-46`).

The owner's direction: the Directory **is no longer kept as a content type of Core**. It is its own product with many
uses, so its data model belongs to DirectoryWeb.

---

## 1. Inventory: what of the Directory is still in Core

### 1.1 Moves in this PR (stage 2)

| Piece | Where (file:line) | What it is |
|---|---|---|
| Directory meta fields (48 keys) | `core/meta.php:36-84` in `aw_meta_schema()` (`:17`) | `aw_verified`, `aw_verified_on`, `aw_awbi_recognised/renewed/valid/code/dropped/list_on`, `aw_needs_reverify`, `aw_gov_verified_on/by`, `aw_email`, `aw_website`, `aw_area`, `aw_services(_source/_on)`, `aw_maps_url`, `aw_lat`, `aw_lng`, `aw_place_id`, `aw_g_rating/reviews/category/profile`, `aw_instagram`, `aw_facebook`, `aw_ngobase_url`, `aw_x`, `aw_youtube`, `aw_gallery`, `aw_founded`, `aw_head`, `aw_registration`, `aw_reg_12a`, `aw_reg_80g`, `aw_darpan_id`, `aw_darpan_blacklisted_by/on`, `aw_reg_authority/act/date`, `aw_sectors`, `aw_animals`, `aw_languages`, `aw_donate_url`, `aw_volunteer_url`, `aw_rel_nofollow` |
| Their sanitising rules | `core/meta.php:140-182` (`aw_sanitize_meta`), the Directory parts of the URL list `:141`, date list `:142`, gallery `:154`, lat/lng/rating `:157`, reviews `:160`, founded `:166`, nofollow `:169`, email `:172` | |
| Their registration | `core/meta.php:187-209` (`aw_register_meta`, `init`) | `register_post_meta` on `aw_listing`, REST on, `edit_posts` auth |
| Phone and hours lists | `core/meta.php:213-411`: `AW_LIST_FIELDS` `:225`, `aw_listing_phones` `:254`, `aw_listing_hours` `:277`, `aw_set_listing_list` `:305`, `aw_listing_put_phone` `:343`, `aw_listing_first_phone` `:383`, `aw_hours_rows_spec` `:399` | SCF repeater format (`aw_phones`, `aw_opening_hours`, rows `{key}_{n}_{sub}`, field-key reference rows) read and written without SCF |
| Never-publish phones | `core/meta.php:235-246`: `AW_NEVER_PUBLISH_PHONES` (1 SHA-256 hash, takedown of 25 Oct 2026), `aw_phone_never_publish`, filter `aw_never_publish_phones` | Checked on every phone write and on display |
| Data sources of a listing | `core/data-sources.php` (whole file, 186 lines): `aw_data_sources` `:31`, `AW_IMPORT_SOURCES` `:50`, `aw_listing_sources` `:58` (filter `aw_listing_sources` `:107`), `aw_source_labels` `:117`, `AW_SOURCE_HOSTS` `:144`, `aw_source_logo` `:155`, `aw_source_logos_fetch` `:171` | The small source labels on a profile (AWBI, Darpan, Google Maps, the organisation…) |
| Re-verification job | `core/cron.php:46-83` (`aw_daily`) | Daily: Claimed listings past `AW_REVERIFY_DAYS` (180, `animal-writes-core.php:31-32`) and AWBI recognition past "valid until" get `aw_needs_reverify` |

### 1.2 Stays in Core for now (shared with other content, or another session's file)

| Piece | Where | Why it stays |
|---|---|---|
| Listing post type arguments | `core/content-types.php:30` (labels, `/directory/` archive), `:46-48` (singles under `directory/org`), `:76-87` (skip when DirectoryWeb registers) | One builder (`aw_content_type_args`) for all seven types; DirectoryWeb already registers `aw_listing` with it (`dw/bootstrap.php:16-22`). Moving the arguments is stage 3 (§8) |
| Attribution fields on listings | `core/meta.php:18-25` (`aw_source_url/name/date` on News, Library, listings, Opportunities) | Shared by four types; SourceWeb writes them |
| `aw_website` on Opportunities | `core/meta.php:49` | Shared key: Core keeps it for `aw_opportunity`, DirectoryWeb registers it for `aw_listing` |
| Weekdays list | `core/meta.php:222` `AW_WEEKDAYS` | Also read by `core/faq-data.php` (session B's file) and `sw/enrichment.php`; kept as is |
| Listing types and services | `core/tags.php:27-29`, `:106-153` (`aw_service_slugs`, `aw_listing_type_slugs`, `aw_listing_types`, `aw_listing_service_names`), `core/model.php:25` (fixed services list) | Built on the shared tag system of every section (`aw_section_tags`) |
| Relations to listings | `core/model.php:36-39` (`aw_rel_org`, `aw_organiser`, `aw_participant`, `aw_organisation`), internal "Animal Writes" listing `:88-115`, category → listing types `:277-284` | The content model of campaigns, Opportunities and News pointing at listings |
| Block bindings | `core/bindings.php:104-133`, `:207-236` (source `animal-writes/field` `:170`) | One bindings source for every type; the listing keys inside it are stage 3 |
| Forms engine | `core/forms-fluentforms.php:61` (claim form #6 key), `:140-144` (org picker), `:553-575` (references `AW-C-`, phone de-dup), `core/fluentcrm.php:121-124`, `:239` (claimant tags and note) | Shared by contact, volunteer, report and campaign forms |
| Report a mistake | `core/reports.php:37`, `:110` | Every item type, listings included |
| Search, Home, site check | `core/pages.php:196-259`, `core/home.php:203-212`, `core/site-check.php:29-38`, `core/kits-config.php:26-97` | Site-level pages that read the Directory through `function_exists` guards |
| SCF bridge | `core/scf.php:63-127`, `:196-200` | Generic; DirectoryWeb adds its groups through the `aw_scf_groups` filter (`directoryweb.php:115`) |
| FAQ feeders, breadcrumbs | `core/faq-data.php`, `core/breadcrumbs.php` | Session B (RankWeb) |
| Content CPTs and blocks | `core/content-types.php`, `core/hub-blocks.php`, `core/sections.php` | Session D |
| Templates | `plugins/animal-writes-core/theme/templates/single-aw_listing.html`, `archive.html`, `page-hub.html`, served by `core/theme-parts-templates.php:114-194` | Core's theme layer serves every template; see §4 |

### 1.3 Already in DirectoryWeb (for the record)

Claims (`dw/claims.php`, legacy `aw_claim` type, `admin_post_aw_claim_decide` `:199`), claim review (`dw/claims-review.php`:
field map `AW_CLAIM_L` `:26`, Directory → Claims screen `:299`, `admin_post_aw_claim_ff_decide` `:627`), badges
(Claimed = `aw_verified` key, `dw/listing-template.php:405`, `:568`; Verified = government documents,
`dw/gov-verified.php:57` `aw_gov_verification`, sync `:84`, WP-CLI `:236`), the 8 SCF groups
(`plugins/directoryweb/acf-json/group_aw_listing*.json`, loaded and saved by `directoryweb.php:101-115`), the one-pager
editor (`dw/listing-profile.php`, shown only when SCF is off), places, map, Near me, Find help, district helplines,
place intros, "Featured in", org people, geocoding, the admin UI and the REST routes (§2.2).

**Thin type pages** are RankWeb's: `rw/robots.php:9`, `:34` (`AW_THIN_DISTRICT_TYPE`, set in RankWeb → Settings,
default 1; only empty pages are noindexed); RankWeb's dashboard reads DirectoryWeb's counts (`rw/rankweb-dashboard.php:242-245`).

---

## 2. Dependency map

### 2.1 What the Directory calls

| Calls into | What | Where |
|---|---|---|
| Core | `aw_content_type_args`, `aw_register_meta` (activation), `aw_post_types`, tags (`aw_listing_types`, `aw_service_slugs`), `aw_item_tags`, `aw_normalise_phone`, `aw_tel_url`, `aw_core_simple_table`, `aw_scf_active`, `aw_use_component_style`, `aw_press_site_logo` | `dw/bootstrap.php:17-45`, `dw/listing-template.php`, `dw/listing-profile.php`, `core/data-sources.php:179` |
| Kits Core | Admin shell (`kits-shell` script handle), `kits_simple_table`, `kits_preview_only_ids` (sample listings), analytics context | `dw/directoryweb-ui.php`, `core/kits-config.php:28`, `:47-97` |
| RankWeb | Directory H1 and helpline checks (`aw_seo_dir_h1`, `aw_seo_place_has_helpline`), `aw_sitemap_unreachable_listings`, keep-indexed list `aw_seo_keep_listings` | `dw/directory.php`, `dw/directoryweb-dashboard.php:202-206`, `rw/robots.php` |
| SourceWeb | Enrichment sources and icons (`aw_enrich_icon`, `aw_listing_sources` filter), the enrichment table | `core/data-sources.php:156`, `sw/enrichment-front.php:24`, `dw/directoryweb-rest.php` |
| MailWeb | Reply signatures; claim mails go through the Core forms engine | `core/mailweb-aw.php` |
| FluentCRM | Tags `claimant`, `org-approved`; people of an organisation | `core/fluentcrm.php:121-124`, `dw/org-people.php`, `dw/directoryweb-rest.php:768-772` |
| Fluent Forms | Claim #6, Report #7 | `core/forms-fluentforms.php:61`, `dw/claims-review.php:124-172` |
| SCF / ACF | The 8 listing groups | `directoryweb.php:98-115`, `core/scf.php` |
| Rank Math | Sitemaps, breadcrumbs, schema (through RankWeb) | `rw/sitemap.php:111-253`, `rw/robots.php:230-254`, `rw/schema.php` |
| InsightWeb | Nothing direct | — |

### 2.2 What calls the Directory

| Caller | Calls | Where |
|---|---|---|
| RankWeb schema (LocalBusiness, hours, phones, sources) | `aw_listing_phones`, `aw_listing_hours`, `aw_hours_rows_spec`, `aw_listing_sources`, `aw_normalise_phone` | `rw/schema.php:69-154` |
| RankWeb FAQ feeders (session B) | `aw_listing_phones`, `aw_listing_hours`, `AW_WEEKDAYS` | `core/faq-data.php:206-363` |
| SourceWeb enrichment and ingest | `aw_sanitize_meta`, `aw_set_listing_list`, `aw_listing_phones`, `aw_listing_hours`, `aw_phone_never_publish`, `aw_listing_put_phone`, `AW_WEEKDAYS` | `sw/enrichment.php`, `sw/enrichment-cleanup.php`, `sw/ingest.php` |
| Core bindings and hub blocks | `aw_listing_first_phone` | `core/bindings.php:129-133`, `core/hub-blocks.php` |
| Core forms | `aw_listing_phones` (claim de-dup), `aw_org_listing_ok` | `core/forms-fluentforms.php:143`, `:568` |
| Core item head | `aw_listing_sources_html` (guarded) | `core/item-head.php:313` |
| Core Home, search, site check, analytics | `aw_listing_public_count`, `aw_form_page_listing`, `aw_current_type_name`, `aw_item_place_names` | `core/home.php:203`, `core/kits-config.php:85-97`, `core/pages.php:196-259` |
| Import tools | `aw_set_listing_list`, `aw_listing_put_phone`, `aw_data_sources`, `aw_listing_phones` | `tools/directory/import_listings.php`, `undo_import.php`, `export_listings.php` |
| REST | `directoryweb/v1/*` (overview, listings, listing profile, bulk, claims, settings), `aw/v1/dir-points`, `aw/v1/dir-near`, org people | `dw/directoryweb-rest.php:49-147`, `dw/directoryweb-listings.php:40-99`, `dw/directory-map.php:146-169`, `dw/org-people.php:229` |
| WP-CLI | `aw redirects-import`, `aw gov-verify`, `aw geocode`, `aw-places`, featured, org people, place intros | `dw/places.php:408`, `dw/gov-verified.php:236`, `dw/geocode.php:279`, `dw/find-help.php:419`, `:507`, `dw/featured.php:472`, `dw/org-people.php:249`, `dw/place-intros.php:337` |
| Theme | Templates and patterns name the Directory blocks and the bindings source; `kit.js`/`kit.css` style the Directory filter | `plugins/animal-writes-core/theme/templates/single-aw_listing.html`, `theme/patterns/find-help.php`, `themes/kits-by-wugweb/assets/js/kit.js` |
| Campaign pages | Partners, supporters, organiser (listing IDs) | `core/meta.php:100-101`, `core/model.php:146-160` |

After the move every caller in this table keeps working: the `aw_*` names stay as wrappers in Core (§6).

---

## 3. Forms

- **Claim this listing (Fluent Forms #6).** Pre-filled from the listing (`dw/claims-review.php:124-172`). Field map
  `AW_CLAIM_L` (`dw/claims-review.php:26-40`): `l_email` → `aw_email`, `phones` → the phone list, `l_area` → `aw_area`,
  `l_address` → `aw_address`, `l_maps` → `aw_maps_url`, `l_hours` → the hours list, `l_about`, `l_aliases` →
  `aw_aliases`, `l_founded` → `aw_founded`, `l_head` → `aw_head`, `l_animals` → `aw_animals`, `l_areas` →
  `aw_areas_served`, `l_services`, `l_languages` → `aw_languages`, and the socials (`core/forms-fluentforms.php:72`).
  Values are saved as typed (+91 phones, Y-m-d dates; `core/forms-fluentforms.php:140-144`); references `AW-C-`
  (`:553`). Nothing is published until approved.
- **Review.** Directory → Claims (`dw/claims-review.php:299`) stays the review screen: per-field approve
  (`aw_claim_apply_field`), `admin_post_aw_claim_ff_decide` (`:627`). The DirectoryWeb Claims list in the shell is
  **read-only**: no approve, reject or apply there, ever.
- **Report a mistake (#7)**: `core/reports.php` (all item types).
- **FluentCRM.** A claimant becomes a transactional contact with tag `claimant`; "Show my name" + approval gives
  `org-approved` (owner sets it by hand); the claim is a note on the contact (`core/fluentcrm.php:121-124`, `:239`).
- **Mail.** Claim notifications go through Core's Fluent Forms mail settings (`aw_ff_send_email` option) and
  MailWeb's signatures (support@ for replies).
- **This PR:** the claim apply path writes phones through `aw_listing_put_phone` (`dw/claims.php`), which now runs
  DirectoryWeb's own function; behaviour and the never-publish check are unchanged.

## 4. Theme

- **Today.** Templates come from Core's theme layer (`plugins/animal-writes-core/theme/templates/`, served by
  `core/theme-parts-templates.php:114-194`): `single-aw_listing.html` (profile), `archive.html` and `page-hub.html`
  (Directory, state, district and type pages). The profile is built from DirectoryWeb's block callbacks
  (`dw/listing-template.php:116-566`) and the bindings source `animal-writes/field` (`core/bindings.php:170`). Patterns:
  `theme/patterns/find-help.php`, `help-now.php`. Cards and the filter use Kits theme components (`kit.css`, `kit.js`).
- **One-pager editor** (`dw/listing-profile.php`): a fallback only, shown when SCF is off. It stays the fallback; it is
  not forced visible.
- **Another theme.** A site without the Kits theme still gets: the `aw_listing` single and archive through the normal
  template hierarchy (`single-aw_listing`, `archive-aw_listing`); all listing meta in REST (`show_in_rest`, now
  registered by DirectoryWeb); and block bindings so any block theme can bind core blocks to listing fields. Missing
  today, and stage 4 (§8): DirectoryWeb shipping its own block templates (`register_block_template`, WP 6.7+) and its
  own bindings source, so the profile does not depend on Core's theme layer.
- **Layout rules kept.** 720px main column on listing pages, breakpoints 767/768 and 1199/1200 only, no folds or "+"
  expanders. This PR changes no markup or CSS.

## 5. A fresh site with another theme and no Core

What DirectoryWeb needs to run alone, and where it stands:

| Need | Today | Plan |
|---|---|---|
| Post type | Registered by DirectoryWeb with Core's builder | Stage 3: DirectoryWeb's own arguments; slug from `directoryweb_post_type()` (added in this PR, fixed to `aw_listing` here) |
| Meta | **This PR**: DirectoryWeb registers its 48 keys | Key names from `directoryweb_meta_prefix()` (added in this PR, fixed to `aw_` here) |
| Phones, hours, never-publish | **This PR**: DirectoryWeb's own | — |
| Data sources | **This PR**: DirectoryWeb's own file | — |
| Tags, places, bindings, templates | Core / DirectoryWeb mix | Stages 3–4 |
| Settings | DirectoryWeb → Settings (`dw/directoryweb-ui.php:99`) | Add the type slug and prefix as read-only facts first |
| Activation | `directoryweb_activate` (`dw/bootstrap.php:36-48`): type, rewrites, meta, capability, one flush | Now also registers DirectoryWeb's meta |
| Uninstall | `plugins/directoryweb/uninstall.php` + backup (`dw/uninstall-backup.php`) | Unchanged: deleting the plugin never deletes listings |

**Config, not renames.** `directoryweb_post_type()` and `directoryweb_meta_prefix()` are the seam: this site answers
`aw_listing` and `aw_`, a new site would answer `directoryweb_listing` and `directoryweb_` (the .org edition's names).
They take no filter yet: the other files still spell `aw_` and switching half would break listings. The filter comes
when every file reads them (stage 5).

**.org edition** (`origin/org-editions`, `plugins/directoryweb-org/`). It already has `directoryweb_sanitize_meta`,
`directoryweb_listing_phones`, `directoryweb_listing_hours`, `directoryweb_set_listing_list`,
`directoryweb_listing_put_phone`, `directoryweb_listing_first_phone`, `directoryweb_phone_never_publish`,
`DIRECTORYWEB_WEEKDAYS`, `DIRECTORYWEB_LIST_FIELDS`, `DIRECTORYWEB_NEVER_PUBLISH_PHONES` and the
`directoryweb_never_publish_phones` filter (`includes/core-lite.php:12-38`, `:116-261`, `:388`), and
`directoryweb_register_meta` (`includes/standalone.php:56`). This PR uses **the same names and signatures** on live, so
the two editions converge; the only difference left is the data prefix (`aw_` here, `directoryweb_` there). No change
to the `org-editions` branch.

## 6. DB safety

- Code moves, data does not. No migration, no `update_option`/`update_post_meta` added, no key renamed.
- `aw_*` meta keys, `aw_listing`, the SCF field keys (`field_aw_listing_*`), options and the `aw_daily` cron hook stay.
- Registration is the same per key: same type, description, `single`, REST, auth, sanitising. Only the function that
  calls `register_post_meta` changes. `aw_meta_schema()` still returns every key (SCF validation reads it,
  `core/scf.php:196-200`), and `aw_register_meta()` skips the pairs DirectoryWeb registers, so nothing is registered twice.
- Wrappers keep every old name: `aw_sanitize_meta`, `aw_listing_phones`, `aw_listing_hours`, `aw_set_listing_list`,
  `aw_listing_put_phone`, `aw_listing_first_phone`, `aw_hours_rows_spec`, `aw_phone_never_publish`, `aw_daily`; each
  guarded with `function_exists`. Without DirectoryWeb they read as empty and write nothing (the Directory is off then
  anyway, and Core already shows a notice).
- The hash list moves to `DIRECTORYWEB_NEVER_PUBLISH_PHONES` in `dw/listing-meta.php`, with the same hash. Its one
  reader of the old constant (`dw/directoryweb-rest.php:949`, the Settings fact) reads the new one. The old filter
  `aw_never_publish_phones` still runs, inside the new `directoryweb_never_publish_phones`.

## 7. Use cases the model must carry

Organisations (NGOs, trusts, societies), vets and clinics, shelters and sanctuaries, feeders (people and groups),
helplines (district, state and national, incl. 1962), adoption partners, government offices (AWBI, animal husbandry,
municipal ABC centres), ambulances, boarding and foster, wildlife rescue, legal help. What the model needs:

- **Type and services** as tags (many per listing); helplines without an address; people without a public address.
- **Place** at state/district (LGD codes), optional area, optional exact point (lat/lng) — feeders and helplines
  often have no point.
- **Contacts** as lists (several phones with labels and WhatsApp), with the never-publish check on every path.
- **Trust marks**: Claimed (approved claim, `aw_verified`), Verified (government documents), AWBI recognition with
  validity, Darpan blacklist, re-verification after 180 days.
- **Sources per value** (organisation, government list, Google Maps, web page, enrichment) with dates.
- **Opening hours** by day, incl. closed days and 24-hour helplines.
- **Relations**: campaigns (organiser, partners, supporters), Opportunities, News "Featured in", people (FluentCRM).
- Not yet modelled: a person (individual feeder) as distinct from an organisation; national helplines with no place.
  Both are questions for the owner (§10).

## 8. Staged move plan

1. **Scope** (this document). ✔
2. **This PR: the listing data model.** `dw/listing-meta.php`: schema, sanitising, registration, phones, hours,
   never-publish list, the two config seams. `dw/data-sources.php` moved from Core as is. Re-verification job moved
   (still on `aw_daily`). Core keeps wrappers.
3. **Post type and tags.** DirectoryWeb's own post type arguments (same labels, rewrite, archive, supports), the
   listing-type and service lists out of `core/tags.php` and `core/model.php:25`. Needs the content-types owner
   (session D) because `aw_content_type_args` is shared.
4. **Front end without Core's theme layer.** DirectoryWeb registers `single-aw_listing` and the Directory archive as
   plugin block templates, its own bindings source (`directoryweb/field`) next to `animal-writes/field`, and its
   patterns. Render-compare before and after on live pages.
5. **Prefix switch for new sites.** Every DirectoryWeb file reads `directoryweb_post_type()` / `directoryweb_meta_prefix()`;
   then the two functions get their filters. This site keeps `aw_`.
6. **Drop Core's wrappers** once SourceWeb, RankWeb, the import tools and the theme call `directoryweb_*` directly.

## 9. Risks

| Risk | Guard |
|---|---|
| A key registered twice, or not at all | `aw_register_meta` skips exactly the (key, type) pairs in `directoryweb_meta_schema()`; `aw_website` stays Core's for Opportunities |
| Sanitising changes for a key | The rules are copied unchanged; Core's lists keep only non-Directory keys |
| Load order: Core includes `meta.php` before DirectoryWeb | Core's wrappers are functions resolved at call time; no Core file calls them at include time |
| DirectoryWeb missing | Wrappers return empty / do nothing; Core's existing notice says DirectoryWeb is missing |
| Never-publish list edited in the old place | `meta.php` now says where the list lives; memory and docs updated |
| Plugin Check pulls all of Core in | Pre-existing ERROR rows get inline `phpcs:ignore` only where the Deploy check reports them |
| Session B/D edit Core at the same time | This PR touches `meta.php`, `cron.php`, `data-sources.php` (deleted) and `animal-writes-core.php` (one line); not `faq-data.php`, `breadcrumbs.php` or the CPT files |

## 10. Open questions for the owner

1. **Post type arguments (stage 3):** may DirectoryWeb own the listing type's arguments, so `content-types.php` no
   longer lists `aw_listing`? This touches session D's file.
2. **Individual feeders:** a person listing (no organisation, no address) — a new listing type tag, or out of scope?
3. **National helplines** (1962 and similar) with no state/district: listings with a "national" place, or keep them
   on the helplines page only?
4. **DirectoryWeb without Core:** is a standalone DirectoryWeb on this site a goal, or only for the .org edition?
   (It decides whether stage 4 ships plugin templates or keeps Core's theme layer.)
5. **Where takedown hashes are added from now on:** `dw/listing-meta.php` (this PR), or a DirectoryWeb → Settings
   field (owner-only, hashes only)?
