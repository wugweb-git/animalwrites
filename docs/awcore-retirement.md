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

1. **The content types.** `aw_content_type_args()` (and `aw_post_types()`, `AW_HUBS`, the hub pages and their
   queries) are aw-core's. Without them there is no `aw_listing` post type, so the Directory is empty, and RankWeb's
   hub canonical, schema and sitemap parts fall back to Rank Math. They move with the content types
   (`docs/content-blocks-feasibility.md` §22).
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

## How it was checked

- An inventory script listed every call from the four plugins to a prefixed function defined only in another plugin,
  and every aw-core constant they use. Each hit was read by hand: switched, guarded, or already behind a check on the
  same function (or on a sibling from the same file).
- `php -l` on every file. The plugin test suites from the Deploy check passed locally, and PHPCS
  (`tools/ci/phpcs-security.xml`, new findings only) ran on the changed files.
- No local WordPress (repo rule). The Deploy check's smoke test boots WordPress with aw-core active.
