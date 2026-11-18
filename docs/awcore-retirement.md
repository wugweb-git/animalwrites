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
5. **Cron health and Run now**, the **PHP error report** and **research credits**: moved on 19 Nov 2026 (section 5):
   Kits Core `includes/ops/` and SourceWeb `includes/research-citation.php`. **Picture downloads** (`cc0-images.php`:
   `aw_source_download`) moved on 18 Nov (SourceWeb `sourceweb_source_download`).
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

## 5. Ops, admin and keys leave aw-core (19 Nov 2026)

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
| `cdn-cache.php` | Kits Core `ops/cdn-cache.php`: `kits_cdn_*` | RankWeb faq.php (comment only), Kits Core volunteers.php (`kits_cdn_excluded_slugs`), site-screens, site-options | Options `aw_cdn_ttl`, `aw_cdn_ttl_long`, settings group `aw_cdn` unchanged. Never-cached ids (the crisis guides) and the longer-lived types come from `ops.php` filters `kits_cdn_excluded_ids`, `kits_cdn_long_lived_types`. |
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

### Not verified

No local WordPress (repo rule), so the moved screens and hooks were not run in a browser; `php -l`, every suite of the
Deploy check, a scan of the `require` targets, PHPCS on the changed files and the new tests are the evidence. After
the deploy, read-only on live: Kits → Home → Page check and Settings (CDN box), the Dashboard "Scheduled tasks" and
"Activity" boxes, a 404 page, `aw/v1/deploy-status`, and `wp insightweb keys status`.

## How it was checked

- An inventory script listed every call from the four plugins to a prefixed function defined only in another plugin,
  and every aw-core constant they use. Each hit was read by hand: switched, guarded, or already behind a check on the
  same function (or on a sibling from the same file).
- `php -l` on every file. The plugin test suites from the Deploy check passed locally, and PHPCS
  (`tools/ci/phpcs-security.xml`, new findings only) ran on the changed files.
- No local WordPress (repo rule). The Deploy check's smoke test boots WordPress with aw-core active.
