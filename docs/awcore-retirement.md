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
| `aw_dispatch_workflow` | SourceWeb Workers → Run now | `WP_Error` "needs the GitHub dispatch from Animal Writes Core" |
| `aw_source_download` | SourceWeb enrichment pictures | `WP_Error`, no picture |
| `aw_research_*` | SourceWeb ingest | already behind `function_exists( 'aw_research_save_citation' )` (same file) |
| `aw_hub_*`, `AW_HUBS`, `aw_post_types`, `aw_press_list`, `aw_item_date_ts`, `AW_LIST_FILTERS`, `AW_COURT_ISSUERS` | RankWeb canonical, breadcrumbs, titles, schema, sitemap, filtered views, FAQ data | Rank Math's own value is kept; no hub nodes, trail steps or extra sitemap URLs |
| DirectoryWeb functions (`aw_current_place`, `aw_directory_url`, `aw_place_*`, `aw_listing_place/sources`, `aw_lf`, ...) | RankWeb | guarded the same way (RankWeb does not require DirectoryWeb) |
| Kits Core `aw_si_table`, `kits_shell_app`, `kits_term_url`, `kits_date` | RankWeb | section skipped |
| `aw_error_count_*` | InsightWeb server report | already behind `function_exists( 'aw_error_count_resolve' )` (same aw-core file): status "not found" |
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
3. **Claims:** the Fluent Forms claim form and review (`forms-fluentforms.php`), the private upload folder
   (`aw_private_dir`, `campaigns.php`) and the FluentCRM tag (`fluentcrm.php`).
4. **Issuers** (`issuers.php`): the Library issuer label written by SourceWeb's ingest and read by auto-review.
5. **Cron health and Run now** (`cron-health.php`: `aw_dispatch_workflow`, `aw_cron_last`), **picture downloads**
   (`cc0-images.php`: `aw_source_download`), **research credits** (`research-citation.php`) and the **PHP error
   report** (`error-count.php`), read by SourceWeb and InsightWeb.
6. **Find help and item heads** (`item-head.php`: `aw_item_kind`, `aw_item_here`, `aw_item_date_ts`), campaign roles
   (`model.php`), press (`press.php`).
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

## How it was checked

- An inventory script listed every call from the four plugins to a prefixed function defined only in another plugin,
  and every aw-core constant they use. Each hit was read by hand: switched, guarded, or already behind a check on the
  same function (or on a sibling from the same file).
- `php -l` on every file. The plugin test suites from the Deploy check passed locally, and PHPCS
  (`tools/ci/phpcs-security.xml`, new findings only) ran on the changed files.
- No local WordPress (repo rule). The Deploy check's smoke test boots WordPress with aw-core active.
