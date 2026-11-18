# Calls from other plugins and tools into animal-writes-core

Generated from `wordpress-dev` at `302f431b` (18 Nov 2026) by matching every `aw_*(` call outside
`plugins/animal-writes-core` against the functions animal-writes-core defines. Some calls sit behind
`function_exists()` and degrade quietly; all of them must switch to their new home before animal-writes-core is
deleted (see `docs/content-blocks-feasibility.md`, section 22). The 7 SourceWeb `aw_i()` / `aw_date()` calls in
`enrichment-front.php` and the `tools/content/campaign2-page.php` ones are already switched on the content-blocks branch.

Forms, FluentCRM and newsletter (18 Nov 2026, Track G): the `aw_ff_*`, `aw_crm_*`, `aw_report_url()`, `aw_claim_url()`,
`aw_form_page_listing()`, `AW_FF_*` and `AW_CRM_TAG_TITLES` callers in DirectoryWeb, MailWeb, RankWeb and `tools/forms` now
call the new names (`kits_ff_*`, `kits_report_url()`, `directoryweb_*`, `mailweb_fcrm_*`). The old names stay as wrappers
in Kits Core (`forms/compat.php`), MailWeb (`fluentcrm-compat.php`) and DirectoryWeb (`claim-page.php`) for the rest of
Core and older code.

| Plugin / tool | Calls | Functions | Files |
|---|---|---|---|
| `plugins/directoryweb` | 188 | 57 | 19 |
| `plugins/aw-seo-extras` | 173 | 44 | 16 |
| `plugins/aw-sourcing` | 79 | 33 | 16 |
| `tools/ci` | 16 | 5 | 3 |
| `tools/directory` | 9 | 5 | 3 |
| `plugins/insightweb` | 7 | 7 | 2 |
| `tools/content` | 6 | 5 | 4 |
| `plugins/planweb` | 2 | 2 | 2 |
| `plugins/mailweb` | 1 | 1 | 1 |
| `tools/lgd` | 1 | 1 | 1 |

## `plugins/directoryweb`

| Function | Calls | Files |
|---|---|---|
| `aw_i()` | 22 | `includes/find-help.php` (1), `includes/gov-verified.php` (2), `includes/listing-profile.php` (7), `includes/listing-template.php` (11), `includes/unverified.php` (1) |
| `aw_use_component_style()` | 14 | `includes/directory.php` (2), `includes/district-helplines.php` (2), `includes/find-help.php` (1), `includes/listing-profile.php` (2), `includes/listing-template.php` (5), `includes/local-cards.php` (1), `includes/org-people.php` (1) |
| `aw_item_editor_preview()` | 13 | `includes/listing-template.php` (13) |
| `aw_listing_type_slugs()` | 12 | `includes/directory-map.php` (3), `includes/directory.php` (3), `includes/directoryweb-dashboard.php` (1), `includes/directoryweb-listings.php` (2), `includes/directoryweb-rest.php` (2), `includes/places.php` (1) |
| `aw_date()` | 11 | `includes/district-helplines.php` (1), `includes/listing-profile.php` (1), `includes/listing-template.php` (9) |
| `aw_listing_phones()` | 10 | `includes/claims-review.php` (4), `includes/directory.php` (1), `includes/district-helplines.php` (1), `includes/listing-profile.php` (3), `includes/listing-template.php` (1) |
| `aw_ff_social_user()` | 7 | `includes/claims-review.php` (7) |
| `aw_listing_hours()` | 6 | `includes/claims-review.php` (1), `includes/directoryweb-listings.php` (1), `includes/listing-profile.php` (3), `includes/listing-template.php` (1) |
| `aw_normalise_phone()` | 5 | `includes/listing-profile.php` (1), `includes/listing-template.php` (4) |
| `aw_service_slugs()` | 5 | `includes/claims-review.php` (1), `includes/claims.php` (1), `includes/directory.php` (2), `includes/district-helplines.php` (1) |
| `aw_hub_url()` | 4 | `includes/directory.php` (4) |
| `aw_listing_types()` | 4 | `includes/directoryweb-listings.php` (1), `includes/district-helplines.php` (2), `includes/listing-template.php` (1) |
| `aw_private_dir()` | 4 | `includes/claims-review.php` (1), `includes/claims.php` (3) |
| `aw_tel_url()` | 4 | `includes/listing-profile.php` (1), `includes/listing-template.php` (3) |
| `aw_core_simple_table()` | 3 | `includes/claims-review.php` (1), `includes/claims.php` (1), `includes/place-intros.php` (1) |
| `aw_district_centroid()` | 3 | `includes/directory-map.php` (2), `includes/geocode.php` (1) |
| `aw_ff_phone_store()` | 3 | `includes/claims-review.php` (3) |
| `aw_item_kind()` | 3 | `includes/find-help.php` (3) |
| `aw_listing_first_phone()` | 3 | `includes/claims.php` (1), `includes/district-helplines.php` (1), `includes/listing-template.php` (1) |
| `aw_listing_service_names()` | 3 | `includes/directoryweb-listings.php` (1), `includes/listing-profile.php` (1), `includes/listing-template.php` (1) |
| `aw_set_listing_list()` | 3 | `includes/claims-review.php` (1), `includes/listing-profile.php` (2) |
| `aw_content_type_args()` | 2 | `includes/bootstrap.php` (2) |
| `aw_crm_on()` | 2 | `includes/org-people.php` (2) |
| `aw_data_sources()` | 2 | `includes/gov-verified.php` (2) |
| `aw_ff_ref()` | 2 | `includes/claims-review.php` (1), `includes/directoryweb-dashboard.php` (1) |
| `aw_hub_page()` | 2 | `includes/district-helplines.php` (1), `includes/place-intros.php` (1) |
| `aw_item_here()` | 2 | `includes/find-help.php` (1), `includes/listing-template.php` (1) |
| `aw_listing_put_phone()` | 2 | `includes/claims.php` (2) |
| `aw_listing_sources()` | 2 | `includes/gov-verified.php` (2) |
| `aw_report_url()` | 2 | `includes/listing-profile.php` (1), `includes/listing-template.php` (1) |
| `aw_set_item_tags()` | 2 | `includes/claims-review.php` (1), `includes/claims.php` (1) |
| `aw_binding_value()` | 1 | `includes/gov-verified.php` (1) |
| `aw_block_result_head()` | 1 | `includes/directory-map.php` (1) |
| `aw_campaign_organiser()` | 1 | `includes/listing-template.php` (1) |
| `aw_campaign_partner_ids()` | 1 | `includes/listing-template.php` (1) |
| `aw_crm_tag_ids()` | 1 | `includes/org-people.php` (1) |
| `aw_district_km()` | 1 | `includes/find-help.php` (1) |
| `aw_ff_form()` | 1 | `includes/claims.php` (1) |
| `aw_ff_id()` | 1 | `includes/claims-review.php` (1) |
| `aw_ff_phone10()` | 1 | `includes/claims-review.php` (1) |
| `aw_ff_ticked()` | 1 | `includes/claims-review.php` (1) |
| `aw_form_page_listing()` | 1 | `includes/claims-review.php` (1) |
| `aw_hub_of_query()` | 1 | `includes/district-helplines.php` (1) |
| `aw_hub_type()` | 1 | `includes/directory.php` (1) |
| `aw_item_card_media()` | 1 | `includes/listing-template.php` (1) |
| `aw_item_date_ts()` | 1 | `includes/listing-template.php` (1) |
| `aw_item_more_card()` | 1 | `includes/listing-template.php` (1) |
| `aw_item_tags()` | 1 | `includes/district-helplines.php` (1) |
| `aw_open_opportunities_meta_query()` | 1 | `includes/listing-template.php` (1) |
| `aw_register_meta()` | 1 | `includes/bootstrap.php` (1) |
| `aw_sanitize_meta()` | 1 | `includes/listing-profile.php` (1) |
| `aw_scf_active()` | 1 | `includes/listing-profile.php` (1) |
| `aw_source_labels()` | 1 | `includes/listing-template.php` (1) |
| `aw_source_logo()` | 1 | `includes/listing-template.php` (1) |
| `aw_theme_file_uri()` | 1 | `includes/listing-template.php` (1) |
| `aw_type_icon()` | 1 | `includes/directory-map.php` (1) |
| `aw_verified_mark()` | 1 | `includes/listing-template.php` (1) |

## `plugins/aw-seo-extras`

| Function | Calls | Files |
|---|---|---|
| `aw_seo_plain()` | 76 | `includes/breadcrumbs.php` (1), `includes/filtered-views.php` (2), `includes/focus-keywords.php` (5), `includes/helpers.php` (5), `includes/opengraph.php` (3), `includes/programmatic.php` (3), `includes/rank-math-schema.php` (1), `includes/schema.php` (45), `includes/sitemap.php` (1), `includes/titles.php` (9), `includes/video.php` (1) |
| `aw_hub_type()` | 9 | `includes/canonical.php` (2), `includes/filtered-views.php` (2), `includes/helpers.php` (1), `includes/schema.php` (1), `includes/titles.php` (3) |
| `aw_hub_url()` | 7 | `includes/breadcrumbs.php` (3), `includes/filtered-views.php` (2), `includes/programmatic.php` (2) |
| `aw_retention_state()` | 6 | `includes/robots.php` (3), `includes/schema.php` (2), `includes/sitemap.php` (1) |
| `aw_hub_page()` | 5 | `includes/canonical.php` (2), `includes/schema.php` (3) |
| `aw_text()` | 5 | `includes/schema.php` (5) |
| `aw_hub_count_label()` | 4 | `includes/filtered-views.php` (1), `includes/helpers.php` (1), `includes/llms.php` (1), `includes/programmatic.php` (1) |
| `aw_research_study_title()` | 4 | `includes/schema.php` (4) |
| `aw_hub_base_url()` | 3 | `includes/canonical.php` (1), `includes/schema.php` (2) |
| `aw_hub_total()` | 3 | `includes/programmatic.php` (1), `includes/schema.php` (1), `includes/titles.php` (1) |
| `aw_listing_types()` | 3 | `includes/helpers.php` (2), `includes/schema.php` (1) |
| `aw_service_slugs()` | 3 | `includes/programmatic.php` (3) |
| `aw_tag_section()` | 3 | `includes/breadcrumbs.php` (1), `includes/programmatic.php` (2) |
| `aw_camp_state()` | 2 | `includes/schema.php` (2) |
| `kits_category_image_id()` | 2 | `includes/helpers.php` (1), `includes/programmatic.php` (1) |
| `aw_form_page_listing()` | 2 | `includes/breadcrumbs.php` (2) |
| `aw_heading_dates()` | 2 | `includes/titles.php` (2) |
| `aw_item_issuer()` | 2 | `includes/schema.php` (2) |
| `aw_listing_service_names()` | 2 | `includes/helpers.php` (1), `includes/schema.php` (1) |
| `aw_listing_sources()` | 2 | `includes/schema.php` (2) |
| `aw_listing_type_slugs()` | 2 | `includes/programmatic.php` (2) |
| `aw_post_types()` | 2 | `includes/breadcrumbs.php` (2) |
| `aw_section_categories()` | 2 | `includes/filtered-views.php` (1), `includes/rankweb-dashboard.php` (1) |
| `aw_use_component_style()` | 2 | `includes/faq.php` (1), `includes/sitemap.php` (1) |
| `aw_campaign_links()` | 1 | `includes/schema.php` (1) |
| `aw_campaign_organiser()` | 1 | `includes/schema.php` (1) |
| `aw_category_help_types()` | 1 | `includes/programmatic.php` (1) |
| `aw_claim_url()` | 1 | `includes/breadcrumbs.php` (1) |
| `aw_filter_clauses()` | 1 | `includes/filtered-views.php` (1) |
| `aw_filter_values()` | 1 | `includes/filtered-views.php` (1) |
| `aw_hours_rows_spec()` | 1 | `includes/schema.php` (1) |
| `aw_hub_intro()` | 1 | `includes/helpers.php` (1) |
| `aw_hub_posts()` | 1 | `includes/schema.php` (1) |
| `aw_item_editor_preview()` | 1 | `includes/faq.php` (1) |
| `aw_law_index_html()` | 1 | `includes/sitemap.php` (1) |
| `aw_legal_kinds()` | 1 | `includes/schema.php` (1) |
| `aw_listing_hours()` | 1 | `includes/schema.php` (1) |
| `aw_listing_phones()` | 1 | `includes/schema.php` (1) |
| `aw_normalise_phone()` | 1 | `includes/schema.php` (1) |
| `aw_open_opportunities_meta_query()` | 1 | `includes/helpers.php` (1) |
| `aw_press_list()` | 1 | `includes/schema.php` (1) |
| `aw_primary_category()` | 1 | `includes/breadcrumbs.php` (1) |
| `sourceweb_publisher_image()` | 1 | `includes/helpers.php` (1) |
| `aw_term_section()` | 1 | `includes/programmatic.php` (1) |

## `plugins/aw-sourcing`

| Function | Calls | Files |
|---|---|---|
| `aw_listing_phones()` | 8 | `includes/enrichment-cleanup.php` (3), `includes/enrichment.php` (5) |
| `aw_post_types()` | 6 | `includes/relevance.php` (1), `includes/rules-model.php` (1), `includes/rules.php` (3), `tests/rules-tests.php` (1) |
| `aw_service_slugs()` | 6 | `includes/enrichment.php` (6) |
| `aw_sanitize_meta()` | 5 | `includes/enrichment.php` (1), `includes/ingest.php` (4) |
| `aw_date()` | 4 | `includes/enrichment-front.php` (4) |
| `aw_set_listing_list()` | 4 | `includes/enrichment.php` (4) |
| `aw_i()` | 3 | `includes/enrichment-front.php` (3) |
| `aw_item_tags()` | 3 | `includes/enrichment.php` (1), `includes/relevance.php` (2) |
| `aw_menu_count()` | 3 | `includes/admin-menu.php` (1), `includes/enrichment-admin.php` (1), `includes/review-queue.php` (1) |
| `aw_section_tags()` | 3 | `includes/ingest.php` (1), `includes/relevance.php` (2) |
| `aw_cron_last()` | 2 | `includes/relevance.php` (2) |
| `aw_cron_span()` | 2 | `includes/workers.php` (2) |
| `aw_dispatch_workflow()` | 2 | `includes/sourcing-lists.php` (1), `includes/workers.php` (1) |
| `aw_item_issuer()` | 2 | `includes/auto-review.php` (1), `includes/relevance.php` (1) |
| `aw_listing_hours()` | 2 | `includes/enrichment.php` (2) |
| `aw_research_study_title()` | 2 | `includes/ingest.php` (2) |
| `aw_section_categories()` | 2 | `includes/ingest.php` (1), `includes/relevance.php` (1) |
| `aw_set_issuer()` | 2 | `includes/ingest.php` (2) |
| `aw_set_item_tags()` | 2 | `includes/enrichment.php` (2) |
| `aw_tel_url()` | 2 | `includes/enrichment-front.php` (2) |
| `aw_whole_sentences()` | 2 | `includes/ingest.php` (1), `includes/summary-rewrite.php` (1) |
| `aw_backup_problem()` | 1 | `includes/workers.php` (1): switched to `kits_backup_problem()` (Kits Core, 18 Nov 2026) |
| `aw_keep_modified()` | 1 | `includes/news-titles.php` (1) |
| `aw_listing_put_phone()` | 1 | `includes/ingest.php` (1) |
| `aw_listing_types()` | 1 | `includes/enrichment.php` (1) |
| `aw_phone_never_publish()` | 1 | `includes/enrichment.php` (1) |
| `aw_press_ingest()` | 1 | `includes/ingest.php` (1) |
| `aw_research_doi()` | 1 | `includes/ingest.php` (1) |
| `aw_research_save_citation()` | 1 | `includes/ingest.php` (1) |
| `aw_research_save_study_title()` | 1 | `includes/ingest.php` (1) |
| `aw_set_campaign_links()` | 1 | `includes/ingest.php` (1) |
| `sourceweb_source_download()` | 1 | `includes/enrichment.php` (1) |

## `tools/ci`

| Function | Calls | Files |
|---|---|---|
| `aw_core_screen_cap()` | 7 | `settings_parts_baseline.txt` (7) |
| `aw_core_menu_parent()` | 3 | `settings_parts_baseline.txt` (3) |
| `aw_core_screens()` | 3 | `smoke.sh` (3) |
| `aw_menu_count()` | 2 | `settings_parts_baseline.txt` (2) |
| `aw_hub_blocks()` | 1 | `registry_check.py` (1) |

## `tools/directory`

| Function | Calls | Files |
|---|---|---|
| `aw_listing_phones()` | 4 | `export_listings.php` (1), `import_listings.php` (3) |
| `aw_set_listing_list()` | 2 | `import_listings.php` (1), `undo_import.php` (1) |
| `aw_listing_put_phone()` | 1 | `import_listings.php` (1) |
| `aw_listing_type_slugs()` | 1 | `import_listings.php` (1) |
| `aw_service_slugs()` | 1 | `import_listings.php` (1) |

## `plugins/insightweb`

| Function | Calls | Files |
|---|---|---|
| `aw_error_count_clean_message()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_error_count_each_line()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_error_count_php_candidates()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_error_count_resolve()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_error_count_tally()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_error_count_top()` | 1 | `includes/search-insights/server.php` (1) |
| `aw_key()` | 1 | `includes/search-insights/runs.php` (1) |

## `tools/content`

| Function | Calls | Files |
|---|---|---|
| `aw_hub_item()` | 2 | `aw1-post-page.php` (2) |
| `aw_date()` | 1 | `campaign2-page.php` (1) |
| `aw_i()` | 1 | `campaign2-page.php` (1) |
| `aw_press_ingest()` | 1 | `aw1-post-site.php` (1) |

## `plugins/planweb`

| Function | Calls | Files |
|---|---|---|
| `aw_core_reset_sections()` | 1 | `includes/switch.php` (1) |
| `aw_social_on()` | 1 | `includes/planweb-rest.php` (1) |

## `plugins/mailweb`

| Function | Calls | Files |
|---|---|---|
| `aw_crm_contact_for()` | 1 | `includes/crm-sync.php` (1); switched to MailWeb's own `mailweb_fcrm_contact_for()` (18 Nov 2026, forms move) |

## `tools/lgd`

| Function | Calls | Files |
|---|---|---|
| `aw_private_dir()` | 1 | `check.php` (1) |
