# Retiring animal-writes-core: DirectoryWeb, SourceWeb, RankWeb and InsightWeb without it

Status 18 Nov 2026 (owner: "fix it now"). This change makes the four plugins safe to run with
`plugins/animal-writes-core` gone: none of them turns itself off for lack of aw-core, and no call into aw-core can
fatal. While aw-core is active, nothing on the site changes. The caller list this builds on is
`docs/aw-core-callers.md`; the plan for the content parts is `docs/content-blocks-feasibility.md` §22.

"Safe" here means *no fatal and no wrong output*. Some features still need aw-core to *work*; they are listed under
"Still blocks deleting aw-core" below.

## 1. Startup checks

Live (checked read-only, 18 Nov 2026): animal-writes-core, kits-core, directoryweb, aw-sourcing, insightweb,
aw-seo-extras, mailweb and planweb are all active in Plugins. So the new `Requires Plugins` headers are already met.

| Plugin | Before | Now |
|---|---|---|
| DirectoryWeb | off without `AW_CORE_VERSION` | needs Kits Core (`KITS_CORE_LOADED`); header `Requires Plugins: kits-core` |
| SourceWeb | off without `AW_CORE_DIR` | needs Kits Core and DirectoryWeb (`DIRECTORYWEB_LOADED`); header `Requires Plugins: kits-core, directoryweb` |
| InsightWeb | code check on Kits Core (bootstrap.php, plugins_loaded 5) | unchanged, no header (owner, 11 Nov) |
| RankWeb | needs Rank Math only | unchanged; it never needs aw-core, DirectoryWeb or Kits Core (every call is guarded) |

**Load order.** WordPress loads active plugins in name order: aw-seo-extras, aw-sourcing, directoryweb, insightweb,
kits-core. So without aw-core, SourceWeb and DirectoryWeb load *before* Kits Core. Each now loads an active Kits Core
(and SourceWeb an active DirectoryWeb) early, from `WP_PLUGIN_DIR`, the way aw-core does today. Kits Core and
DirectoryWeb stop a second load with their `*_LOADED` constants, and WordPress's later `include_once` of the same
file does nothing. A plugin that is not active is never loaded. With aw-core active, aw-core has already loaded
Kits Core and DirectoryWeb before these lines run, so they do nothing.

Before each early load the plugin's linked folder is registered (`wp_register_plugin_realpath()`, which WordPress
calls before it loads any active plugin), so on live, where the plugin folders are links into the deploy folder,
`plugin_dir_url()` still gives the `wp-content/plugins/…` address (aw-core does the same for its siblings).

Without the dependency, each shows one admin notice (as before) and loads nothing else.

SourceWeb's relay CA bundle read `AW_CORE_DIR . 'data/certs/*.pem'`, which is fatal without aw-core. The three
certificates are now in `plugins/aw-sourcing/data/certs/`, as identical files, so the bundle and its cache name are
the same.

## 2. Calls into aw-core: what moved, what is guarded

Calls into Kits Core from DirectoryWeb and SourceWeb, and calls into DirectoryWeb from SourceWeb, are left unguarded:
the startup check and the header guarantee those plugins. RankWeb and InsightWeb guard every call into another
plugin, because they require none of them.

### Moved to their final home (new names, same results; aw-core's copies stay untouched until it is deleted)

| aw-core | New home | Used by |
|---|---|---|
| `aw_i()` | Kits Core `kits_icon()` (already there) | DirectoryWeb |
| `aw_date()` | Kits Core `kits_date( $when, null, 'aw-date' )` (already there) | DirectoryWeb, RankWeb (guarded) |
| `aw_core_simple_table()` | Kits Core `kits_simple_table()` (already there) | DirectoryWeb |
| `aw_use_component_style()` | the theme's `kits_use_component_style()` (always behind `function_exists`) | DirectoryWeb |
| `aw_listing_phones/hours/first_phone/put_phone`, `aw_set_listing_list`, `aw_hours_rows_spec`, `aw_phone_never_publish` | DirectoryWeb `directoryweb_*` (`listing-meta.php`, already there) | DirectoryWeb, SourceWeb, RankWeb |
| `aw_service_slugs`, `aw_listing_type_slugs`, `aw_listing_types`, `aw_listing_service_names`, `aw_item_tags` (with a list), `aw_set_item_tags`, `aw_normalise_phone`, `aw_tel_url`, `AW_SERVICES` | DirectoryWeb `directoryweb_*`, `DIRECTORYWEB_SERVICES` (new `includes/listing-terms.php`) | DirectoryWeb, SourceWeb, RankWeb |
| `aw_district_centroid`, `aw_district_km` | DirectoryWeb `directoryweb_district_centroid/_km` (`directory-map.php`, reading its own `data/district-centroids.json`) | DirectoryWeb |
| `aw_use_component_style()` (DirectoryWeb's 15 calls) | DirectoryWeb `directoryweb_use_style()`, one guarded call to the theme's `kits_use_component_style()` | DirectoryWeb |
| `aw_theme_file_uri( 'assets/img/google-maps.png' )` | Core's address while Core is active, else DirectoryWeb's own copy `assets/img/google-maps.png` | DirectoryWeb |
| `aw_hub_url( 'aw_listing' )` | DirectoryWeb `directoryweb_hub_url()` (Core's answer while Core is active, else `/directory/`) | DirectoryWeb |
| `aw_section_tags`, `aw_section_categories`, `aw_item_tags` (by section), `aw_cron_span`, `AW_WEEKDAYS`, `AW_LEGAL_KINDS`, `AW_SAME_STORY_TYPES`, `AW_CRON_STALE` | SourceWeb `sourceweb_*`, `SOURCEWEB_*` (new `includes/content-helpers.php`) | SourceWeb |
| `aw_sanitize_meta` (ingest) | SourceWeb `sourceweb_sanitize_meta()`: Core's rules while Core is active, else DirectoryWeb's for listing keys and the same date, address and employment-type rules | SourceWeb |
| `aw_post_types` (relevance) | SourceWeb `sourceweb_post_types()`: Core's list while Core is active, else the sourcing lists' sections | SourceWeb |
| `aw_seo_plain()` | RankWeb `rankweb_plain_text()` (new `includes/text.php`, always loaded) | RankWeb (75 calls) |
| `AW_WEEKDAYS` | RankWeb `RANKWEB_WEEKDAYS` | RankWeb FAQ data |

The copied lists (`DIRECTORYWEB_SERVICES`, `SOURCEWEB_LEGAL_KINDS`, the weekdays) were checked equal to aw-core's.
Keep them equal until aw-core is deleted.

### Guarded, with a safe fallback (the code stays in aw-core until its content or feature moves)

| aw-core function or constant | Caller | Without aw-core |
|---|---|---|
| `aw_ff_*`, `AW_FF_SOCIAL`, `AW_FF_DATES` (Fluent Forms claims) | DirectoryWeb claims, claims review, dashboard | claim form empty, no pre-fill, phones shown raw, social names skipped |
| `aw_private_dir` | DirectoryWeb claims | logo step skipped; nothing is ever written to a public folder |
| `aw_hub_of_query`, `aw_hub_page` | DirectoryWeb district helplines, place intros | helplines block not appended; page test inlined (`get_query_var( 'paged' )`) |
| `aw_item_kind`, `aw_item_here` | DirectoryWeb Find help | no guide detection, item block skipped |
| `aw_campaign_organiser`, `aw_campaign_partner_ids` | DirectoryWeb listing template | no campaign roles |
| `aw_crm_tag_ids` | DirectoryWeb org people | no `org-approved` CRM tag |
| `aw_content_type_args`, `aw_register_meta` | DirectoryWeb bootstrap | already guarded: **no listing post type** |
| `aw_item_issuer`, `aw_set_issuer` | SourceWeb auto-review, ingest | issuer not checked or set (noted in the ingest result) |
| `kits_dispatch_workflow` (was `aw_dispatch_workflow`; Kits Core since 19 Nov 2026) | SourceWeb Workers → Run now | `WP_Error` "needs the GitHub dispatch from Kits Core" |
| `aw_source_download` | SourceWeb enrichment pictures | `WP_Error`, no picture |
| `aw_research_*` | SourceWeb ingest | already behind `function_exists( 'aw_research_save_citation' )` (same file) |
| `aw_hub_*`, `AW_HUBS`, `aw_post_types`, `aw_press_list`, `aw_item_date_ts`, `AW_LIST_FILTERS`, `AW_COURT_ISSUERS` | RankWeb canonical, breadcrumbs, titles, schema, sitemap, filtered views, FAQ data | Rank Math's own value is kept; no hub nodes, trail steps or extra sitemap URLs |
| DirectoryWeb functions (`aw_current_place`, `aw_directory_url`, `aw_place_*`, `aw_listing_place/sources`, `aw_lf`, ...) | RankWeb | guarded the same way (RankWeb does not require DirectoryWeb) |
| Kits Core `aw_si_table`, `kits_shell_app`, `kits_term_url`, `kits_date` | RankWeb | section skipped |
| `kits_error_count_*` (was `aw_error_count_*`; Kits Core since 19 Nov 2026) | InsightWeb server report | behind `function_exists( 'kits_error_count_resolve' )`: status "not found" |
| SourceWeb `aw_sourcing_kw_lists` | InsightWeb dry run | already behind `aw_sourcing_kw_on` (same SourceWeb file) |

## 3. Still blocks deleting aw-core

These no longer crash without aw-core, but stop working:

1. **The content types.** Done 19 Nov 2026 (section 5): `aw_content_type_args()`, `aw_post_types()`, `AW_HUBS`, the hub
   pages and the rest of the content layer now load from the theme's site layer, not from aw-core. Open: renaming the
   `aw_` functions, and the generic parts to Kits Core.
2. **The sitewide date format.** Done 19 Nov 2026: the `pre_option_date_format` filter (Indian day-month-year,
   `j M Y`, "19 Nov 2026") moved from aw-core's `systems.php` to the theme's site layer
   (`themes/kits-by-wugweb/assets/site/site.php`), so it stays on this site and never ships in a distributed theme.
   The `AW_DATE_FORMAT` constant is still defined there and in aw-core, for aw-core's own callers.
3. **Claims:** done 19 Nov 2026 (section 5): the claim form and review and the FluentCRM tag had already moved
   (Kits Core `forms/`, MailWeb); the private upload folder is now Kits Core's `kits_private_dir()`.
4. **Issuers** (`issuers.php`): done 19 Nov 2026, now SourceWeb's `includes/issuers.php` (section 5).
5. **Cron health and Run now**, the **PHP error report** and **research credits**: done 19 Nov 2026 (section 6):
   Kits Core `includes/ops/` and SourceWeb `includes/research-citation.php`. **Picture downloads** (`cc0-images.php`:
   `aw_source_download`) moved on 18 Nov (SourceWeb `sourceweb_source_download`).
6. **Find help and item heads** (`item-head.php`), press (`press.php`): done 19 Nov 2026 (section 5). Campaign roles
   (`model.php`) are still aw-core's (content types session).
7. **Loading.** aw-core loads Kits Core, DirectoryWeb, SourceWeb and InsightWeb from the deploy folder. On live all of
   them are also active in Plugins, so deleting aw-core leaves them running. Check this again just before the delete.
8. Callers outside these four plugins (`tools/`, MailWeb, PlanWeb) are listed in `docs/aw-core-callers.md` and are
   not part of this change.

## 4. Sourcing and keyword code out of Kits Core (18 Nov 2026)

Owner, 18 Nov 2026: "The sourcing code still in Kits Core - fix it". Kits Core no longer holds sourcing or keyword
code. Same function, option, table, meta key and shortcode names; no data moved, no migration.

| Was in Kits Core | Now | Notes |
|---|---|---|
| `keyword-table.php`, `keyword-model.php` (`aw_si_table()`, `aw_kw_*`, `aw_si_norm()`…, role constants, dbDelta of `wp_aw_si_keywords` / `wp_aw_si_kw_map`, option `aw_kw_db`) | InsightWeb `includes/keyword-table.php`, `keyword-model.php`, loaded once by `includes/keywords.php` (`INSIGHTWEB_KEYWORDS_LOADED`) | Loaded by insightweb.php first, by the bootstrap before its Kits Core / Rank Math check, and by SourceWeb. Needs no other plugin. |
| `sources-config.php` (`kits_sources_config()`), `data/sources-seed.json` | SourceWeb `includes/sources-config.php` (`sourceweb_sources_config()`, alias `aw_sources_config()`), `data/sources-seed.json` | Option `aw_sources` unchanged. DirectoryWeb reads the listing tags from it, or from the option itself when SourceWeb is off. |
| `attribution.php` (`kits_source_row_html()` and the other `kits_*`, `[aw_source_row]`, the news-date filter, the slug filter, the favicon run) | SourceWeb `includes/attribution.php` (`sourceweb_*`; the `aw_*` aliases stay) | Dual read of `kits_source_*` / workers-written `aw_source_*` meta unchanged. |
| `sourcing.php` (`kits_sourced_types()`, `kits_host()`, `kits_publishers()`, `KITS_*` constants) | removed: SourceWeb's `sourcing-basics.php` already defines `kits_sourced_types()`, `aw_host()`, `aw_publishers()` and the `AW_*` constants | Nothing called the `kits_` names or constants outside these files. |
| `compat-sourcing.php` (the `aw_*` aliases) | removed: the aliases are at the end of SourceWeb's `attribution.php` and `sources-config.php` | `aw_host()` and `aw_publishers()` are SourceWeb's own. |
| `slugs.php` calling `kits_clean_news_slug()` | filter `kits_publish_slug_base`, which SourceWeb hooks | Same slug as before. |

**The favicon run.** `aw_fetch_favicons` keeps its name (booked on live). SourceWeb books it on `init` and takes it off
when it is deactivated. InsightWeb's referrer icons listen on it and now book it too, so they keep working without
SourceWeb (after SourceWeb's deactivation InsightWeb books it again on the next request). Kits Core's deactivation hook
and `uninstall.php` no longer touch it. Animal Writes Core's own deactivation list (`cron.php`) still names it; that
is harmless and goes with aw-core.

**SourceWeb now needs InsightWeb** (`Requires Plugins: kits-core, directoryweb, insightweb`), for the keyword table
(about 200 calls). It loads an active InsightWeb early, as it does Kits Core and DirectoryWeb. While Animal Writes Core
is active, which loads InsightWeb's Search Insights whether or not InsightWeb is active, SourceWeb loads the keyword
table from Core's sibling `insightweb` folder, so nothing changes in that set-up either. SourceWeb's host helpers load
before its check, so Animal Writes Core's `aw_host()` / `aw_publishers()` calls never meet a missing function. Live has
InsightWeb active (18 Nov 2026).

**What needs SourceWeb now.** `[aw_source_row]`, the source rows and publisher icons on news cards and item pages,
relative dates on news cards and publisher-free news slugs need SourceWeb loaded (live: active, and Animal Writes Core
loads it too). Animal Writes Core's calls to these are behind `function_exists`, except `[aw_source_row]` in
`item-head.php`, which prints as text if SourceWeb is not loaded.

## 5. The content layer leaves aw-core (19 Nov 2026, branch `awcore-content`)

25 aw-core files and the `acf-json/` folder (13 field groups) have a final home and are gone from aw-core. **Code was
moved as it was: every function, constant, hook, shortcode, block, post type, taxonomy, meta key and option keeps its
name** (a script found 205 functions and constants; callers were listed before anything moved, from `plugins/`,
`themes/` and `tools/`). No alias file is needed: nothing was renamed, so every caller, in aw-core's other files too,
still resolves. Renaming the `aw_` functions to `kits_` / `directoryweb_` / `sourceweb_` names is a separate step
(see "Not done" below).

### Home per file

| Was in `plugins/animal-writes-core/` | Now |
|---|---|
| `includes/taxonomy-rename.php` and its test | Kits Core `includes/taxonomy-rename.php`, `tests/taxonomy-rename-tests.php` (kits_ names already; loaded before `media-groups.php`; the marker option `kits_taxonomy_rename`, `kits_taxonomy_current()`, the Undo backup and WP-CLI are unchanged) |
| `includes/{content-types, category-moves, tags, meta, scf, summary-display, news-article, bindings, also-reported, display, sections, hubs, hub-blocks, term-page, faq-data, model, helpers-public, pages, block-states, related, law-links, near-opps, entity-links}.php` | the theme's site layer, `themes/kits-by-wugweb/assets/site/content/`, loaded by `assets/site/site.php` in aw-core's old order. Only this site uses them (hard-coded sections, hubs, types, copy), and the distributed theme leaves the folder out |
| `acf-json/` (13 groups) | `themes/kits-by-wugweb/assets/site/acf-json/`, given to SCF by `content/scf.php` through Kits Core's `kits_scf_json_folder( kits_site_path( 'acf-json' ) )`; group and field keys unchanged |
| `assets/js/hub-blocks.js` | `themes/kits-by-wugweb/assets/site/js/hub-blocks.js` (address from `kits_site_uri()`) |
| `includes/breadcrumbs.php` | deleted: it only loaded RankWeb's renderer when RankWeb was not active, on a `plugins_loaded` hook that cannot run from the theme. RankWeb is active on live |
| `tests/content-bindings-tests.php` | `plugins/kits-core/tests/` (reads the site-layer files; still loads `systems.php` from aw-core until that moves) |

The Deploy check names, `tools/ci/registry_check.py`, `tools/ci/settings_parts_baseline.txt`, `kits-registry.json` and
the DirectoryWeb `listing-meta-tests.php` follow the new paths.

### Callers outside the file, per file (before the move)

| File | Defined | Callers outside the file |
|---|---|---|
| `content-types.php` | 5 | `aw_content_type_args` (DirectoryWeb 3); `aw_post_types` (DirectoryWeb 1, RankWeb 3, SourceWeb 9); `aw_register_content_types` (aw-core (other sessions) 1) |
| `category-moves.php` | 2 | none outside the file (or its own set) |
| `tags.php` | 16 | `aw_item_tags` (aw-core (other sessions) 2); `aw_legal_kinds` (Kits Core 2, RankWeb 7, aw-core (other sessions) 4); `aw_listing_type_slugs` (tools/ 1); `aw_listing_types` (aw-core (other sessions) 4); `aw_section_categories` (RankWeb 4, SourceWeb 2, aw-core (other sessions) 1); `aw_section_tags` (SourceWeb 2, aw-core (other sessions) 1); `aw_service_slugs` (tools/ 2); `aw_tag_icon` (RankWeb 1, aw-core (other sessions) 4); `aw_tag_section` (RankWeb 6) |
| `meta.php` | 11 | `AW_WEEKDAYS` (DirectoryWeb 1, SourceWeb 1); `aw_hours_rows_spec` (DirectoryWeb 1); `aw_listing_first_phone` (DirectoryWeb 1); `aw_listing_hours` (DirectoryWeb 1, aw-core (other sessions) 1); `aw_listing_phones` (DirectoryWeb 1, Kits Core 2, aw-core (other sessions) 1, tools/ 5); `aw_listing_put_phone` (DirectoryWeb 2, tools/ 1); `aw_meta_schema` (DirectoryWeb 3); `aw_phone_never_publish` (DirectoryWeb 2); `aw_register_meta` (DirectoryWeb 3, aw-core (other sessions) 1); `aw_sanitize_meta` (DirectoryWeb 14, SourceWeb 4, aw-core (other sessions) 1); `aw_set_listing_list` (DirectoryWeb 4, tools/ 2) |
| `scf.php` | 8 | `AW_SCF_GROUPS` (aw-core (other sessions) 1); `aw_scf_active` (DirectoryWeb 2, aw-core (other sessions) 1); `aw_scf_group_keys` (aw-core (other sessions) 1) |
| `summary-display.php` | 0 | none outside the file (or its own set) |
| `news-article.php` | 2 | none outside the file (or its own set) |
| `bindings.php` | 7 | `aw_binding_value` (DirectoryWeb 2, aw-core (other sessions) 3); `aw_normalise_phone` (DirectoryWeb 2, RankWeb 1); `aw_tel_url` (SourceWeb 3) |
| `also-reported.php` | 2 | `AW_SAME_STORY_TYPES` (SourceWeb 1) |
| `display.php` | 4 | `aw_seo_plain` (Kits Core 2, RankWeb 1, SourceWeb 2) |
| `sections.php` | 10 | `aw_head_band` (DirectoryWeb 2); `aw_head_below` (aw-core (other sessions) 1) |
| `hubs.php` | 24 | `AW_HUBS` (RankWeb 14, aw-core (other sessions) 4); `aw_card_item` (theme 2); `aw_hub_base_url` (RankWeb 4); `aw_hub_intro` (RankWeb 2); `aw_hub_item` (theme 4, tools/ 2); `aw_hub_of_query` (DirectoryWeb 2); `aw_hub_page` (DirectoryWeb 2, RankWeb 6); `aw_hub_posts` (RankWeb 2); `aw_hub_total` (RankWeb 4); `aw_hub_type` (DirectoryWeb 2, Kits Core 2, RankWeb 18, aw-core (other sessions) 6); `aw_hub_url` (DirectoryWeb 2, RankWeb 10, aw-core (other sessions) 3) |
| `hub-blocks.php` | 33 | `aw_block_posted_by` (aw-core (other sessions) 2); `aw_block_result_head` (DirectoryWeb 1); `aw_hub_blocks` (RankWeb 1, aw-core (other sessions) 6, tools/ 3); `aw_hub_count_label` (RankWeb 8); `aw_legal_kind_tag` (aw-core (other sessions) 2); `aw_news_meta_row` (aw-core (other sessions) 2); `aw_short_name` (Kits Core 5, RankWeb 1, aw-core (other sessions) 4); `aw_type_icon` (DirectoryWeb 1, aw-core (other sessions) 1, theme 1); `aw_verified_mark` (DirectoryWeb 2) |
| `term-page.php` | 7 | `aw_term_section` (RankWeb 2) |
| `faq-data.php` | 1 | `aw_legal_item_fields` (aw-core (other sessions) 1) |
| `model.php` | 13 | `AW_LEGAL_KINDS` (SourceWeb 1); `AW_SERVICES` (DirectoryWeb 1, MailWeb 3, SourceWeb 2, tools/ 2); `aw_campaign_organiser` (DirectoryWeb 2, RankWeb 2, aw-core (other sessions) 1); `aw_campaign_partner_ids` (DirectoryWeb 2, aw-core (other sessions) 4); `aw_category_help_types` (RankWeb 2); `aw_linked_from_box` (aw-core (other sessions) 1); `aw_related_box` (aw-core (other sessions) 1); `aw_relations` (aw-core (other sessions) 1) |
| `helpers-public.php` | 5 | none outside the file (or its own set) |
| `pages.php` | 14 | `AW_CONTACT_ABOUT` (MailWeb 4); `AW_CONTACT_PLACEHOLDER` (Kits Core 1); `aw_elsewhere_screen` (Kits Core 2, aw-core (other sessions) 1); `aw_heading_dates` (RankWeb 3); `aw_is_sponsored` (aw-core (other sessions) 1); `aw_social_icon` (Kits Core 2); `aw_sponsored_box` (aw-core (other sessions) 1) |
| `block-states.php` | 1 | none outside the file (or its own set) |
| `related.php` | 14 | `aw_district_centroid` (DirectoryWeb 3); `aw_related_back` (aw-core (other sessions) 2); `aw_related_pick` (aw-core (other sessions) 8); `aw_related_set` (aw-core (other sessions) 3); `aw_related_state_fill` (aw-core (other sessions) 1) |
| `law-links.php` | 7 | `aw_law_guides_naming` (aw-core (other sessions) 2); `aw_law_index_html` (RankWeb 2); `aw_law_mentions` (aw-core (other sessions) 2); `aw_law_named_in` (aw-core (other sessions) 2) |
| `near-opps.php` | 3 | none outside the file (or its own set) |
| `entity-links.php` | 3 | none outside the file (or its own set) |
| `taxonomy-rename.php` | 12 | `KITS_CAMPAIGN_LINK` (Kits Core 9, RankWeb 2, SourceWeb 6, aw-core (other sessions) 21, tools/ 7); `KITS_MEDIA_GROUP` (Kits Core 4, RankWeb 2, SourceWeb 4, aw-core (other sessions) 2); `kits_taxonomy_current` (Kits Core 2, SourceWeb 2, aw-core (other sessions) 8); `kits_taxonomy_rename_counts` (aw-core (other sessions) 1); `kits_taxonomy_rename_keys` (aw-core (other sessions) 1); `kits_taxonomy_rename_run` (aw-core (other sessions) 1); `kits_taxonomy_rename_undo` (aw-core (other sessions) 1); `kits_taxonomy_renames` (aw-core (other sessions) 1) |
| `breadcrumbs.php` | 0 | none outside the file (or its own set) |

### What changed in the code (nothing else)

- `scf.php`: the folder is `kits_site_path( 'acf-json' )` (was `AW_CORE_DIR`); the guard that keeps SCF on while aw-core is
  on compares with the literal `animal-writes-core/animal-writes-core.php`.
- `hub-blocks.php`: script address and version from the site layer (was `AW_CORE_DIR`, `AW_CORE_VERSION`).
- `block-states.php`: the demo page's campaign sheets still come from aw-core's `assets/css/` (the campaigns session's
  files); the code returns if `AW_CORE_DIR` is not defined.
- `content-types.php`: after adding the `option_permalink_structure` filter it calls `$wp_rewrite->init()`, because WordPress builds
  `WP_Rewrite` before the theme loads and reads the permalink structure only once.
- `related.php`: the fallback path of DirectoryWeb's centroid file is `WP_PLUGIN_DIR`, not `__DIR__`.
- `animal-writes-core.php`: the 25 `require_once` lines are gone.

### Loading order, and what it means

The files load when the theme loads (after every plugin) instead of while aw-core loads. Hooks they register on `init` or
later behave the same; the only two hooks that would have been missed (`plugins_loaded` in `breadcrumbs.php`) were
retired as above. A scan of every PHP file in `plugins/` found no call to any of the moved names at file level (outside
a function) other than the `KITS_CAMPAIGN_LINK` / `KITS_MEDIA_GROUP` constants, which Kits Core now defines first. On
the same `init` priority, the content layer's callbacks now run after the plugins' (before: aw-core's came first).
**The site layer runs only while no child theme is active** (`kits_site_on()`); live has none.

### Not done, and why

- **Renaming the `aw_` functions** (205): the owner's rule for new names is "no aw prefix", but a rename of this size
  across `plugins/`, the theme patterns and `tools/` cannot be tested without WordPress here. Left as the next step, with
  one rename per file, callers switched in the same commit.
- **Generic engines to Kits Core** (hub queries, sections, display, bindings, related; `content-blocks-feasibility.md`
  §22): need Kits Core presets and `kits/*` blocks to exist first. Until then they sit in the site layer unchanged.
- **Directory-specific code to DirectoryWeb** (the listing wrappers in `meta.php`, listing types in `tags.php`,
  `aw_binding_value` listing keys, `aw_district_centroid`): DirectoryWeb already has `directoryweb_*` twins; the `aw_`
  wrappers stay here as the thin aliases the remaining callers use (`meta.php` lines 176-231 are those, written `if ( ! function_exists() )`).
- **`category-moves.php`, `law-links.php`, `entity-links.php`** are RankWeb's in the plan, but they hold this site's
  slugs and law names, so they sit in the site layer until RankWeb has settings for them.
- **Files nobody owns:** `plugins/animal-writes-core/assets/css/*` (campaign sheets, read by `block-states.php`),
  `assets/js/aw-analytics*.js`, `data/`, `languages/` and `uninstall.php` (which mentions `aw_law_mentions` as a string).
## 5. Campaign, press, social, Home, item heads, claims, issuers, team dates (19 Nov 2026)

Owner, 18 Nov 2026: nothing stays in aw-core. This part moves 13 files out of `plugins/animal-writes-core/includes/`.
**Every file moved whole and unchanged in behaviour**: function, constant, block, shortcode, post-type, taxonomy, meta,
option, cron, route and slug names are the same (`aw_campaign`, `aw_campaign_link`, `aw_press`, `aw_social`, `aw_issuer`,
`aw_team_dates` ...), so no data, URL or schedule changes and no caller needed a new name. The `aw_` function names
(`aw_camp_state()`, `aw_press_list()` ...) were **not** renamed in this change: about 400 call sites in six plugins, the
theme, `edit-screen-map.json` (callbacks by name) and the tools use them, and a rename with no WordPress to run is the
riskier half. Renaming them to `kits_` / plugin-slug names is a mechanical follow-up once aw-core is gone (no aliases
are needed for that, since nothing outside calls them through aw-core any more). Aliases left by this change: none.
Text domains in the moved files are now the new home's (`kits-core`, `kits-by-wugweb`, `aw-sourcing`, `planweb`).

### Home table

| aw-core file | Contents | Callers outside the file (audit) | New home |
|---|---|---|---|
| `campaigns.php` | `aw_private_dir()`; shortcode `[aw_countdown]` | `aw_private_dir`: Kits Core forms/fluentforms.php (4), DirectoryWeb claims (6, all behind `function_exists`), `tools/lgd/check.php` | `aw_private_dir()` is Kits Core `kits_private_dir()` (`includes/private-dir.php`); all callers switched. The countdown shortcode is the theme's site layer (`assets/site/includes/campaigns.php`) |
| `campaign-tools.php` | campaign state/window helpers (`aw_camp_*`), the campaign boxes and share section, side card, timeline, follow accounts, asset enqueues | Kits Core (`aw_camp_id`, `aw_camp_is_open` in the campaign form, `aw_follow_accounts` in site options), RankWeb schema (`aw_camp_state`, guarded), aw-core `hub-blocks.php`, `kits-config.php`, theme templates (`aw_camp_dates_timeline`, `aw_steps_dateline`, `aw_home_hero_is_campaign`), `edit-screen-map.json` (box callbacks) | theme site layer `assets/site/includes/campaign-tools.php` (this site's campaign design and copy) |
| `campaign-record.php` | the record fold, metrics, `[aw_campaign_metric]`, motif and marks styles | `aw_camp_record_box` (Kits Core admin layer, edit-screen map) | theme site layer `assets/site/includes/campaign-record.php` |
| `home.php` | Home hero, Lab/SC/content blocks, guide and library picks | none outside the file (hooked on `aw_hub_blocks` and `query_loop_block_query_vars`) | theme site layer `assets/site/includes/home.php` |
| `item-head.php` | the item page's meta row, sources, tags, share, more-groups, card media; `aw_primary_category` | DirectoryWeb listing template and Find help (all behind `function_exists`), RankWeb (guarded; `aw_item_date_ts` in `faq-data.php` is not, front end only), aw-core `hub-blocks.php`, `law-links.php` | theme site layer `assets/site/includes/item-head.php`. `aw_whole_sentences()` (a text rule SourceWeb's ingest and rewrite use) left it for SourceWeb: `sourceweb_whole_sentences()` in `content-helpers.php`; both callers switched, no alias |
| `campaign-links.php` | taxonomy `aw_campaign_link` behind `KITS_CAMPAIGN_LINK`, `aw_campaign_links()`, `aw_set_campaign_links()`, news lines | RankWeb schema/faq-data (guarded), SourceWeb ingest, aw-core `hub-blocks.php`, `related.php` | Kits Core `includes/campaign-links.php` |
| `campaign-short.php` | short links (/lab, /diwali) 301 to the campaign page; `aw_campaign_short` meta rules | aw-core `meta.php` (`aw_camp_short_clean`) | theme site layer `assets/site/includes/campaign-short.php` (moved from Kits Core, 19 Nov 2026) |
| `campaign-letters.php` | the letter tool's statistics table, report page, dashboard widget, CSV | Kits Core campaign form (`aw_cl_*`) | Kits Core `includes/campaign-letters.php`, labelled a site module (§7): the Campaign form handler calls `aw_cl_*` straight |
| `press.php` | CPT `aw_press`, coverage fetch/sideload, `aw_press_list()`, `aw_press_ingest()` | SourceWeb ingest, RankWeb schema, DirectoryWeb (`aw_press_site_logo`), aw-core `hub-blocks.php`, `systems.php`, `tools/content` | theme site layer `assets/site/includes/press.php` (moved from Kits Core, 19 Nov 2026) |
| `social-posts.php` | CPT `aw_social`, module switch `aw_social_on`/`aw_social_show`, REST intake, cards, block | PlanWeb, SourceWeb settings (`aw_social_on`), aw-core `uninstall.php`, `tools/` | theme site layer `assets/site/includes/social-posts.php` (moved from Kits Core, 19 Nov 2026) |
| `sponsored-blocks.php` (+ `assets/js/sponsored-blocks.js`) | `animal-writes/sponsored-item` and `-banner` blocks | aw-core `block-states.php` (render callbacks by name) | theme site layer `assets/site/includes/sponsored-blocks.php` (moved from Kits Core, 19 Nov 2026); the editor script `assets/js/sponsored-blocks.js` stays in Kits Core and is found by `plugins_url()` |
| `issuers.php` | issuer names, `aw_item_issuer()`, `aw_set_issuer()`, `aw_issuer_short()`, editor box | SourceWeb ingest and auto-review (guarded), RankWeb schema, Kits Core cards, aw-core `faq-data.php`, `hub-blocks.php` | SourceWeb `includes/issuers.php` |
| `team-dates.php` | the team calendar fallback: option `aw_team_dates`, Kits → Dates | PlanWeb (11), Kits Core (`aw_team_dates_moved`, `aw_team_date_label`), aw-core `admin-config.php`, `uninstall.php`, `tools/` | PlanWeb `includes/team-dates.php` |
| `assets/css/` campaign files, `assets/js/campaign*.js` | `campaign.css`, `campaign-lab.css`, `aw-camp-*.css`, `aw-share.css`, `campaign-record*.css`, `campaign.js`, `campaign-lab.js` | the moved enqueue code; aw-core `block-states.php` (Kits demo page, switched); `tools/ci/contrast_check.py` (path switched) | theme site layer `assets/site/campaign/css`, `.../js` (not `css/aw*.css`, so no `.min` pairs); CI baselines re-keyed to the new paths |

Claims: the Fluent Forms claim form and review (`forms-fluentforms.php`) and the FluentCRM tag (`fluentcrm.php`) are no
longer in aw-core (Kits Core `forms/`, MailWeb `fluentcrm-compat.php`, DirectoryWeb `claim-page.php`). Only the private
folder was left. It went to Kits Core, not DirectoryWeb, because Kits Core's claim form already writes the logo there and
DirectoryWeb requires Kits Core, not the other way round. Folder name (`uploads/aw-private`) unchanged.

**Load order.** The theme's site layer loads after every plugin, so the moved Home/campaign/item-head hooks register
after aw-core's own at the same priority; none of them depends on an aw-core hook running first (checked: the `init`
ones register post meta, the rest are render, enqueue and filter hooks). Kits Core loads before aw-core, so
`KITS_CAMPAIGN_LINK_TYPES`, `AW_PRESS_GROUPS` and the rest are defined earlier than before. The theme's site layer is
needed for the campaign pages, Home and the item pages (it is this site's design; a distributed theme leaves it out, and
the plugins that call into it are already behind `function_exists`).

**Not moved, not mine.** Nothing unowned found in `includes/`.

## 6. Ops, admin and keys leave aw-core (19 Nov 2026)

Owner, 18 Nov 2026: nothing "stays in aw-core". This change gives the ops, admin and keys files of
`plugins/animal-writes-core` their final homes and deletes them there. Behaviour is the same: same screens, options,
cron events, hooks, REST routes, shortcodes and WP-CLI commands. Names changed to `kits_` (generic) or the plugin
slug; every stored name stays. Branch `awcore-ops`.

### Where each file went

| aw-core file | Final home | Who called it (outside the file) | Notes |
|---|---|---|---|
| `cron-health.php` | Kits Core `includes/ops/cron-health.php`: `kits_cron_*`, `kits_dispatch_workflow()`, `KITS_CRON_STALE/KEEP` | SourceWeb (`aw_dispatch_workflow` in workers.php, workers-tick.php, sourcing-lists.php; `aw_cron_last` in relevance.php), Kits Core site-screens, `tools/ci/smoke.sh` (option name), uninstall | Options `aw_cron_runs`, `aw_dispatch_log`, dashboard box `aw_cron_health`, action `aw_cron_health_after`, `?aw_tick=` unchanged. The workers repository is no longer hard-coded: filter `kits_dispatch_repo` (site layer `ops.php` gives `wugweb-git/animal-writes-workers`). Token constant `AW_GITHUB_DISPATCH_TOKEN` still read (`KITS_GITHUB_DISPATCH_TOKEN` also accepted). |
| `cron.php` | `aw_daily` booked by Kits Core `ops/daily.php` (on init, when missing); LiteSpeed night-only crawler to the theme site layer `ops.php` (`kits_site_litespeed_crawler_night_only`) | `aw_daily` event: DirectoryWeb listing-meta.php, SourceWeb opportunities-site.php, Kits Core thumbs.php | The activation and deactivation hooks (`aw_cron_schedule()`, `aw_cron_unschedule()`) are gone with the file; Kits Core's own deactivation unschedules its own events. The `aw_daily()` stub had no caller. |
| `error-count.php` | Kits Core `ops/error-count.php`: `kits_error_count_*`; test `kits-core/tests/error-count-tests.php` | InsightWeb server report (`aw_error_count_*` now `kits_error_count_*`, still behind `function_exists`), Deploy check | Event `aw_error_count_daily`, option `aw_error_count`, filter `aw_error_count_php_paths`, `wp aw error-count` unchanged. Mail subject uses the site name. Not redesigned (owner: 404 and error count are out of scope for the ops work in #147, but they still block deleting aw-core). |
| `not-found.php` | Kits Core `ops/not-found.php`: `kits_not_found_*`, `[kits_not_found]` and `[aw_not_found]`; the photos to `kits-core/assets/site/error/` (site-only folder) | the theme's 404 template (shortcode) | The site's sections, 410 types, lead texts, section markup, links and list styles come through `kits_not_found_*` filters in the theme site layer `ops.php`; output is the same. Not redesigned. |
| `site-check.php` | Kits Core `ops/site-check.php`: `kits_site_check_*` | Kits Core site-screens (Page check, Joined files, summary), `deploy-purge.php` | Option `aw_site_check_last`, event `aw_site_check_deploy`, admin-post `aw_site_check`, action `aw_site_check_after` (MailWeb, backups) unchanged. The page list is the filter `kits_site_check_urls`; the site's list is in `ops.php`. The standalone "Check site" screen (only used without Kits) is gone. |
| `cdn-cache.php` | Kits Core `ops/cdn-cache.php`: `kits_cdn_*` | RankWeb faq.php (comment only), Kits Core volunteers.php (`aw_cdn_excluded_slugs`), site-screens, site-options | Options `aw_cdn_ttl`, `aw_cdn_ttl_long`, settings group `aw_cdn` and the filter `aw_cdn_excluded_slugs` unchanged (`kits_cdn_excluded_slugs` runs after it). Never-cached ids (the crisis guides) and the longer-lived types come from `ops.php` filters `kits_cdn_excluded_ids`, `kits_cdn_long_lived_types`. |
| `deploy-purge.php` | Kits Core `ops/deploy-purge.php`: `kits_deployed_head()`, `kits_deploy_purge_if_new()` | Kits Core site-screens (option), `tools/kits-ship.sh` and the Deploy check (the `?aw_deploy_nudge=` request) | Purges LiteSpeed once per new commit, as before. Tested without WordPress (`site-ops-tests.php`): new commit purges once, same commit does not, unreadable head does nothing. |
| `deploy-status.php` | Kits Core `ops/deploy-status.php`: `kits_mail_sending()`, route `aw/v1/deploy-status` | `tools/ci/mail_sending_check.py`, `tools/kits-ship.sh` | Route and answer unchanged. |
| `keep-modified.php` | Kits Core `ops/keep-modified.php`: `kits_keep_modified()` | RankWeb faq.php (sets `$GLOBALS['aw_keep_modified']`, unchanged), SourceWeb news-titles.php | `?aw_keep_modified=1` and `X-AW-Keep-Modified` unchanged. |
| `retention.php` | Kits Core `ops/retention.php`: `kits_retention_state()`, `kits_open_opportunities_meta_query()`, `kits_opportunity_*` | RankWeb robots, sitemap, schema, helpers; DirectoryWeb listing-template; Core bindings, hubs, related, item-head | The post type `aw_opportunity` and the meta keys `aw_deadline`, `aw_review_until`, `aw_closes` are data and keep their names. It is content-type logic: it moves again with the Opportunities content type (session D). |
| `research-citation.php` | SourceWeb `includes/research-citation.php`: `sourceweb_research_*` | SourceWeb ingest (writes), RankWeb schema (reads the study title), Core item-head.php | Meta `aw_study_title`, `aw_authors`, `aw_journal`, event `aw_research_citation` unchanged. SourceWeb reads and writes it; RankWeb's schema calls the new name behind `function_exists`. |
| `keys.php` | Deleted. Each plugin keeps its own keys (owner rule, 18 Nov: no shared vault). | InsightWeb (`aw_key()` through the `kits_service_key` filter), Kits Core site-screens (Keys screen) | Section 5, "The keys vault". |
| `plugin-info.php`, `help-tab.php` | Deleted (the plugin's own Plugins-screen details and Help tab; they go with the plugin) | none | |
| `dashboard-activity.php` | Kits Core `dashboard-activity.php`: `kits_activity_*`, off unless the filter `kits_dashboard_activity` is true (site layer `ops.php` turns it on) | none | Widget id `aw_activity` unchanged. |
| `admin-config.php`, `kits-config.php` | Theme site layer `themes/kits-by-wugweb/assets/site/admin.php` and `config.php` (loaded by `site.php`) | none (filters only) | Plain filters for Kits Core; same values. The edit-screen map `data/edit-screen-map.json` and `js/aw-analytics(.min).js` moved there too (`tools/ci/js_min.py` follows). The Overview's "Plugins screen" link on aw-core's row went with the plugin. |
| `admin-table.php` | Deleted; `aw_core_simple_table()` is an alias of Kits Core `kits_simple_table()` | Core team-dates, pages, campaign-letters | |
| `theme-parts-screen.php`, `theme-parts.php` | Screen to the theme site layer `theme-parts-screen.php` (`kits_site_theme_parts_*`). `theme-parts.php` deleted; its two helpers are aliases. | Kits Core site-screens; `aw_theme_file_uri` (Core home.php, DirectoryWeb data-sources and listing-template, all guarded); `aw_use_component_style` (many Core files) | |
| `staging.php` | Theme site layer `staging.php`: `kits_site_is_staging()` | Kits Core site-screens | |
| `media-compat.php` | Deleted (no caller left in the repository) | none | |
| `uninstall-setting.php`, `uninstall.php` | Setting box deleted. The ops options, transient and events went into Kits Core's `uninstall.php`. aw-core's `uninstall.php` is trimmed and now inert (nothing can tick its setting). | | Delete it with the plugin. |
| `systems.php` (rest) | **Not moved.** The date filter was already gone. What is left is the hub and list engine (`AW_LIST_FILTERS`, `AW_LIST_SORTS`, `aw_filter_*`, `aw_active_*`, `aw_publisher_sync`, the two Query Loop fixes) plus three tiny helpers (`aw_i`, `aw_date`, `aw_src_date`, `aw_first_term`, `aw_text`). It belongs with the hubs (`hubs.php`, `hub-blocks.php`), so it moves with them in the content session. | RankWeb filtered-views, titles, schema, sitemap; Core hubs, hub-blocks, pages | Left for the coordinator. |

### Aliases left (each goes once nothing calls it)

Kits Core `includes/ops/compat.php`: `aw_dispatch_workflow`, `aw_cron_last`, `aw_cron_span`, `aw_keep_modified`,
`aw_retention_state`, `aw_open_opportunities_meta_query`, `aw_core_simple_table`, `aw_use_component_style`,
`aw_theme_file_uri`. SourceWeb `includes/research-citation.php`: `aw_research_doi`, `aw_research_study_title`,
`aw_research_authors_text`. Remaining Core callers: `aw_core_simple_table` (team-dates, pages, campaign-letters),
`aw_open_opportunities_meta_query` (hubs, related, item-head), `aw_retention_state` (bindings),
`aw_research_*` (item-head), `aw_theme_file_uri` (home), `aw_use_component_style` (many). The server's
cron line `wp eval 'aw_dispatch_workflow(...)'` (in `cron-health.php`'s comment) still works.

### The keys vault (Kits → Keys, option `aw_keys`)

Only InsightWeb used it (`docs/keys-per-plugin.md`): `AW_CLARITY_TOKEN`, `AW_BING_WEBMASTER_KEY` (set on live in
`aw_keys`), `AW_PSI_KEY`, `AW_GOOGLE_ALERTS_FEED_1`, `AW_GOOGLE_ALERTS_FEED_2` (not set). No other plugin has a key
there. AI keys are out of scope and untouched.

1. **Copy (built, runs by itself).** InsightWeb's `insightweb_keys_copy()` copies each of those five keys from
   `aw_keys` into its own option `insightweb_keys` when it is empty there and no wp-config.php constant sets it.
   It runs once on the first administrator request after deploy (flag option `insightweb_keys_copied`; delete it to run
   again) and as `wp insightweb keys copy [--dry-run]`; `wp insightweb keys status` shows where each key is set.
   Names and words only; no value is printed, logged or returned. It never changes or deletes `aw_keys`, so it is
   reversible (delete the entry on InsightWeb → Settings → Keys). Idempotent: a second run copies nothing.
   Tested in `plugins/insightweb/tests/run.php` (section 9).
2. **Keep reading the old option until the copy is checked.** `insightweb_key()` still falls back to `aw_keys`, so
   nothing stops if the copy has not run yet.
3. **Delete the vault (done in this change).** `keys.php`, the Kits → Keys screen and `admin_post_aw_keys` are gone,
   with the InsightWeb `aw_keys` and `kits_service_key` filters. `admin.php?page=aw-keys` redirects to InsightWeb →
   Settings → Keys. The option **`aw_keys` itself stays in the database** until the owner says so, after
   `wp insightweb keys status` shows both live keys as `set yes (insightweb)` and the next Clarity run (daily 06:30 IST)
   and Bing run (2nd of the month, 07:40 IST) are OK. Then InsightWeb drops its `aw_keys` fallback, and the option is
   deleted after a database export to a file (size checked).

### Depends on the site layer

The site's values (Check site pages, 404 sections and 410 types, the workers repository, never-cached ids, staging's
noindex, login wall and no-mail rules, the Activity box, analytics and admin config) now come from
`themes/kits-by-wugweb/assets/site/`, which loads only when `kits_site_on()` is true (Kits theme active, no child theme).
Live has that today. Anywhere it is off (a child theme, `wp --skip-themes`), those values are absent: a staging copy
would not be noindex or mail-blocked, and Run now says no repository is set. `tools/staging/*.sh` use `--skip-themes`
only for config and table-prefix commands, not for anything that sends.

### Not verified

No local WordPress (repo rule), so the moved screens and hooks were not run in a browser; `php -l`, every suite of the
Deploy check, a scan of the `require` targets, PHPCS on the changed files and the new tests are the evidence. After
the deploy, read-only on live: Kits → Home → Page check and Settings (CDN box), the Dashboard "Scheduled tasks" and
"Activity" boxes, a 404 page, `aw/v1/deploy-status`, and `wp insightweb keys status`.

## 7. Site modules in Kits Core: moved or labelled (19 Nov 2026, branch `claude/awcore-theme-db086f`)

The audit of 19 Nov found Animal Writes-only modules in the generic Kits Core. Names, options, meta keys, post types,
hooks, shortcodes, REST routes and WP-CLI names are unchanged; no aliases added.

**Moved to the theme's site layer** (`themes/kits-by-wugweb/assets/site/includes/`, loaded first by `assets/site/site.php`
inside the `KITS_CORE_LOADED` guard; the distributed theme and zips leave the folder out). Every caller outside the files
was already behind `function_exists` (SourceWeb, PlanWeb, RankWeb schema, DirectoryWeb, `tools/content`) or sits in the
site layer itself: `campaign-short.php`, `press.php`, `social-posts.php`, `sponsored-blocks.php`, `library-documents.php`.
One code change: the Modules menu in `social-posts.php` was added on `plugins_loaded`, which has already fired when the
theme loads, so it now hooks `after_setup_theme` (priority 20, still before `admin_menu`).

**Labelled as site modules** (header says so; they stay in Kits Core because code in Kits Core calls them straight at plugin
load, so moving them would break the load order or the Campaign/volunteer/report forms):

| File | Why it stays |
|---|---|
| `includes/campaign-letters.php` | the Campaign form handler (`forms/campaign-form.php`) calls `aw_cl_*` and `AW_CL_*` unguarded |
| `includes/thumbs.php` | `card-images.php`, `tools/media-reuse.php` and `category-images.php` call it; hooks the Home sections |
| `includes/category-images.php` | `card-images.php` and `thumbs.php` read it |
| `includes/forms/volunteers.php` | form pages, Fluent Forms field code and `forms/compat.php` call it |
| `includes/forms/reports.php` | form pages and `forms/compat.php` call it |

Moving them needs the Campaign, volunteer and report forms (and the card-picture code) to move with them, or each call
guarded; left for one change of its own.

**Kept on purpose.**
- `includes/taxonomy-rename.php`: the one-off rename has **not run** (`docs/pending.md`: "Rename not clicked, owner's word
  needed"), and its test is in the Deploy check. It goes after the owner's yes and a live check.
- The `aw_report` registration in `forms/reports.php`: the list columns, status action, edit box, the edit-screen map and
  the form pages still read those entries.

**Cleaned.** `animal-writes-core` is no longer named in `includes/system-screen.php` (class-usage scan), `admin-layer/edit-screens.php`
(plugin list), the 500/503 drop-ins or the test headers. `admin-layer/compat.php` is deleted: every caller (SourceWeb's menu
count, PlanWeb's Dates screen, the site layer's Contact links, Modules and campaign files, `tools/ci/smoke.sh`) now uses the
`kits_site_*`, `kits_modules_*`, `kits_menu_count`, `kits_edit_fields_metrics_box` and `KITS_FOLLOW_EXTRA` names. The filter
`aw_core_reset_sections` (a hook name) is still read.

## How it was checked

- An inventory script listed every call from the four plugins to a prefixed function defined only in another plugin,
  and every aw-core constant they use. Each hit was read by hand: switched, guarded, or already behind a check on the
  same function (or on a sibling from the same file).
- `php -l` on every file. The plugin test suites from the Deploy check passed locally, and PHPCS
  (`tools/ci/phpcs-security.xml`, new findings only) ran on the changed files.
- No local WordPress (repo rule). The Deploy check's smoke test boots WordPress with aw-core active.
