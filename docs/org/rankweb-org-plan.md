# RankWeb on the Kits plugin standard (plan)

11 November 2026. Owner's request of 11 Nov: bring the three existing plugins onto the shared Kits Core admin shell, a plan for each first. **Plan only: no code until the owner approves.** Built on the code of `plugins/aw-seo-extras/` (RankWeb 1.9.3) on `wordpress-dev` at `c37d3b7c`, `docs/insightweb-plan.md`, `docs/directory-plugin-plan.md` §1.4, `docs/admin-ui-spec.md`, `docs/calendar-plugin-plan.md` and `docs/pending.md`. All paths below are inside `plugins/aw-seo-extras/` unless they say otherwise.

**What RankWeb is after the split.** Everything Rank Math doesn't do or keeps for Premium (owner, 11 Nov; §0). It acts on the site and measures nothing: title and description rules, schema, FAQ, canonicals, breadcrumbs, sitemap additions, noindex rules, redirects, Open Graph, llms.txt and Markdown for agents. Search Insights leaves for InsightWeb (`docs/insightweb-plan.md`, phase 1 on branch `claude/insightweb-phase1`). RankWeb keeps `aw/v1/seo-items`, `aw/v1/seo-focus`, `aw/v1/seo-score-items`, `aw/v1/seo-scores` and the one-page-per-keyphrase rule.

**Rules this plan keeps**
- Data stays as it is: options, post and term meta, transients, routes, hooks, cron hooks, menu slugs and every `aw_` name. The rename is deferred. New code gets kits names (`rankweb_` functions, `RANKWEB_` constants). New REST routes go under `rankweb/v1/…`.
- No GA4 or GTM change. Rank Math stays the base: RankWeb is "advanced Rank Math", with no Pro and no Rank Math blocks.
- The admin is the shared Kits Core shell. RankWeb only registers parts into it (tiles and routes, the FAQ list's columns, filters and actions, services, settings groups). A part the design system lacks is a request to Kits Core (§4.4), never RankWeb CSS.
- Order: Kits Core shell plus the InsightWeb pilot first, **then RankWeb**, then SourceWeb, MailWeb, DirectoryWeb, PlanWeb.
- AI keys are not touched. RankWeb reads none.

**Which admin spec this follows.** `docs/admin-ui-spec.md` (approved 11 Nov) covers the Kits menu only and says the plugin dashboards are specified separately. `docs/kits-plugin-ui.md` is on no branch today (checked with `git for-each-ref` + `ls-tree`). So this plan follows the brief's outline: the look comes from the Kits admin design system in Kits Core (the Google Site Kit pattern, per the later 11 Nov Notion entry); WordPress packages are plumbing only (`@wordpress/data`, `api-fetch`, `i18n`, `icons`). Note: `admin-ui-spec.md` A6 still names `@wordpress/components` and `dataviews` as the look; that line predates the Notion entry. When `kits-plugin-ui.md` lands, §4 is re-checked against it.

---

## 0. Scope and owner answers (11 Nov 2026)

**Scope (owner, 11 Nov).** RankWeb covers everything Rank Math doesn't do or keeps for Premium. Only Search Insights leaves (to InsightWeb); everything else in the plugin stays in RankWeb. The admin text, exactly as written:

| Where | Text |
|---|---|
| Plugins screen description (header `Description:`) | "Everything Rank Math doesn't do or keeps for Premium, for Kits sites." |
| Kits → Plugins row (`includes/kits-hub.php:33`) | "What Rank Math doesn't do or keeps for Premium." |
| Overview row for Search Insights (replaces today's row, `includes/overview.php:143-157`) | "Search Insights is now its own plugin, InsightWeb." |
| Overview intro (today "What the plugin adds to Rank Math (free) for animalwrites.in…", `includes/overview.php:260`) | Same scope, approved 11 Nov: "Everything Rank Math doesn't do or keeps for Premium, for this site. Rank Math holds everything else; where a module's settings live in Rank Math, the link goes there." |

The four lines ship in the commit that removes Search Insights (phase 2, 2.0.0). The Overview row stays until the Dashboard replaces the Overview (phase 4a), then the same line sits in Dashboard → Where things are.

**Owner answers**

1. **Keys stay in Kits → Keys.** Moving them belongs to InsightWeb's later plan; RankWeb doesn't take them. RankWeb's Connected services shows Rank Math only (§4.3). (`admin-ui-spec.md` C and E1 still say "RankWeb → Settings → Connected services"; that is for InsightWeb's plan to settle.)
2. **Site Health test: yes.** One test that reports the hourly sitemap and llms.txt check: it reads the stored result (`aw_seo_endpoints_last`, `includes/health.php:68`) and makes no request of its own. Good when both answered at the last check; Critical with the problem sentence and the check time when one did not; Recommended "Not checked yet" before the first run or when the check module is off. Phase 3.
3. **Export: yes.** RankWeb's three settings options (`aw_seo_settings`, `aw_faq_off_types`, `aw_seo_keep_listings`) go into Kits → Tools → Export through Kits Core's existing filter `kits_settings_transfer_options` (`plugins/kits-core/includes/settings-transfer.php:36`). Phase 1; it is also the before/after proof for every later phase.

**Icon.** The source is `docs/brand/plugin-icons/rankweb.svg`, with PNGs at 128 and 256 (§7).

---

## 1. Inventory: everything RankWeb has today and where it lands

"SI" marks what leaves with Search Insights (InsightWeb phase 1). Lands: **D** = Dashboard tile or section, **F** = FAQ working list, **S** = a Settings group, **Same** = stays as it is (front end, edit screens, CLI, cron), **Gone** = removed only because its job moves into the shell (old screen behind a redirect).

### 1.1 Plugin file and loader (`aw-seo-extras.php`)

| Item | Where | Lands |
|---|---|---|
| Header: plain `/** … */` block, no `@wordpress-plugin`, `Version: 1.9.3`, `Text Domain: aw-seo-extras`, `Update URI`, Description "SEO rules, FAQ, schema and Search Insights for Kits sites." | `:2-16` | Phase 1 makes it a PHPDoc header with `@wordpress-plugin`, `@package`, `@author`, `@copyright`, `@license`, `License URI`; Description becomes "Everything Rank Math doesn't do or keeps for Premium, for Kits sites." in the SI commit (2.0.0, §0) |
| No version constant; Kits status row reads the header with `get_file_data()` | `includes/kits-hub.php:43` | New `RANKWEB_VERSION`, equal to the header; status row reads it |
| `AW_SEO_EXTRAS_DIR` | `:21` | Same |
| `aw_seo_extras_rank_math()`: Rank Math active check; without it a notice and nothing loads | `:26-44` | Same; also feeds the Rank Math service row (§4.3) |
| Loader: `settings.php` first, then 20 files minus switched-off modules (`aw_seo_files_off()`) | `:45-51` | Same; `'search-insights'` leaves the list (SI) |
| Switched-off module's cron cleared (`health`, `sitemap`), admin and cron requests only | `:52-74` | Same |
| Deactivation: clears `aw_si_cron_hooks()` (SI) and `aw_seo_fill_daily`, `aw_seo_endpoints_watchdog`, `aw_seo_sitemap_daily` | `:79-90` | SI part removed in the SI commit (RankWeb must never clear InsightWeb's jobs); the three SEO jobs stay |
| No `readme.txt`, no `languages/`, no `composer.json`, no `uninstall.php`, no tests | — | Phase 1 adds `readme.txt`, `languages/aw-seo-extras.pot`, `composer.json` (lint, make-pot), `tests/run.php`. No `uninstall.php`: deleting the plugin keeps its data (owner rule 5) |

### 1.2 Admin screens

| Screen | Slug, file | Capability | What is on it | Lands |
|---|---|---|---|---|
| RankWeb menu | `aw-seo-plus` (`AW_SEO_MENU`, `includes/overview.php:19`), `add_menu_page` `:25`, position 27.5, `dashicons-search`; screen-id prefix kept as `aw-seo-plus` `:27` | `manage_categories` | — | Same slug and menu; icon from §7 |
| Menu order fallback (after MailWeb or SourceWeb, else before the first separator) | `includes/overview.php:37-58` | — | — | Same |
| Plugins screen links: Overview, FAQ, Search Insights | `includes/overview.php:62-76` | — | — | Overview → Dashboard; FAQ stays; Search Insights link leaves (SI) |
| **Overview** | `aw-seo-plus`, `includes/overview.php:29`, page `:118-342` | `manage_categories` | 11 module rows (module, what it does, where to set it up incl. Rank Math links, status, Settings link, Off / Partly off); "Needs attention" notice for warning rows; "On edit screens" (3 rows); "Commands (WP-CLI)" (5 rows) | Module rows → **S** Modules group (description, where, on/off) and **D** status tiles; "Needs attention" → **D** attention strip; "On edit screens" and "Commands" → **D** section "Where things are" (read-only); Rank Math links → **D** "Where things are" and each Settings group's help. Search Insights row becomes "Search Insights is now its own plugin, InsightWeb." and the intro takes the new scope (§0, SI commit); `wp aw-si` command row leaves (SI) |
| **FAQ** | `aw-faq`, `includes/faq-overview.php:116`, page `:226-261` | `manage_categories` (screen); `manage_options` (types form) | "FAQ box on" form (post types and taxonomies, `aw_faq_off_types`); list "Pages with an FAQ" (core list table, `includes/class-aw-faq-list-table.php`): views All / Waiting for approval, search, type filter, sort by name, type, questions; row actions Edit FAQ, View; bulk Approve (Waiting view); "Counts" table by type (published, show an FAQ, below the minimum, drafts waiting); "Counted …" + Recount | List → **F** (§3); "FAQ box on" → **S** FAQ group; Counts → **D** FAQ coverage tile + **F** "Counts" view; Recount → **F** list action |
| **Settings** | `aw-seo-settings`, `includes/settings.php:496` (priority 200, last), page `:527-549` | `manage_options` | Tabs (`aw_seo_setting_tabs()` `:413`): Modules, Titles and descriptions, Indexing, Schema and FAQ, Share images and Open Graph, Focus keywords and content analysis, AI agents, hidden Search Insights tab; per field: Save, Restore defaults, "Changed from the default" | → **S** (§4), one group per tab; hidden Search Insights tab leaves (SI) |
| Search Insights | `aw-search-insights`, `includes/search-insights/admin.php` | `aw_si_cap()` | — | SI |
| Old link `aw-seo-settings&tab=search-insights` → Search Insights → Settings | `includes/settings.php:486-489` | — | — | Kept, as InsightWeb §1.10 says |
| Settings autoload fix-up | `includes/settings.php:482-484` | — | — | Same |
| Admin notice: "RankWeb does nothing until Rank Math SEO is active." | `aw-seo-extras.php:35-41` | `activate_plugins` | — | Same (all admin screens, it is a blocker); also **D** Rank Math tile |
| Admin notice: "Sitemap or llms.txt is not answering." | `includes/health.php:92-104` | `manage_options` | — | Same (all admin screens: it means search engines lose the sitemap); also **D** tile |
| Kits → Plugins row, Kits → Welcome status row | `includes/kits-hub.php:15-56` (`kits_plugin_screens`, `kits_status_rows`) | `manage_categories` | Name, link to Overview, description, "Active, 1.9.3" or "Does nothing until Rank Math…" | Same filters; description becomes "What Rank Math doesn't do or keeps for Premium." in the SI commit (§0); icon added (§7) |
| CI baseline lines | `tools/ci/settings_parts_baseline.txt:29-33` | — | — | Updated in the phase that changes each screen; line 33 leaves (SI) |

### 1.3 Edit-screen boxes (stay on the edit screens)

| Box | Where | Lands |
|---|---|---|
| FAQ box on every public post type with the box on | `add_meta_box` `includes/faq.php:1002-1025`, save `:1026` | Same |
| FAQ box on term screens | `includes/faq.php:1129-1141` (`aw_faq_term_box`, `aw_faq_term_save`) | Same |
| Post meta `aw_faq`, `aw_faq_subject`, `aw_faq_line` registered | `includes/faq.php:256-300` | Same |
| Library item box (Short name, Issued under, Key sections) | Animal Writes Core `includes/faq-data.php` | Not RankWeb; listed on **D** "Where things are" as today |

### 1.4 Settings: modules and fields (option `aw_seo_settings`, `includes/settings.php:21-323`)

Each field is a former constant, defined at load from the option or its default (`:354-362`); only values that differ from the default are stored (`:676`). The option is autoloaded (`:684`). Every field lands in the Settings group of its tab (§4.1), with the same constant, type, default and help text.

| Module (key) | Switch | Fields (type) | Settings group |
|---|---|---|---|
| General (`general`) `:27` | always on | `AW_SEO_DESC_MAX` (int), `AW_HUB_TITLES` (map), `AW_HUB_DESCRIPTIONS` (map), `AW_ARCHIVE_DESCRIPTIONS` (map); `AW_SEO_LISTING_SHARE_IMAGE` (int), `AW_SEO_SOURCE_IMAGE_TYPES` (list) shown on Share | Titles; Share |
| Titles and descriptions (`titles`) `:60` | always on | `AW_SEO_ITEM_TYPES` (list), `AW_SEO_BREAKDOWN` (list), `AW_SEO_TERM_TITLES` (map, 68 lines) | Titles |
| Canonicals (`canonical`) `:140` | always on | `AW_FILTER_PARAMS` (list) | Indexing |
| Robots rules (`robots`) `:149` | always on | `AW_THIN_DISTRICT_TYPE` (int, default 1), `AW_DIRECTORY_KEEP_INDEXED` (list, 15 paths) | Indexing |
| Filtered lists (`filtered`) `:175` | always on | `AW_FILTERED_MIN_ITEMS` (int), `AW_FILTERED_CATEGORY_HUBS` (list), `AW_FILTERED_TITLE_WORDS` (maplist), `AW_FILTERED_STATE_NOINDEX` (list) | Indexing |
| Schema (`schema`) `:193` | always on | `AW_SITE_ALT_NAMES` (list) | Schema and FAQ |
| FAQ (`faq`) `:202` | switch | `AW_FAQ_MIN`, `AW_FAQ_MIN_GUIDE`, `AW_FAQ_MAX`, `AW_FAQ_WORDS` (int), `AW_FAQ_HUBS` (map) | Schema and FAQ |
| Search Insights (`search-insights`) `:220` | switch | 7 `AW_SI_*` ints | **SI**: InsightWeb keeps reading `aw_seo_settings['search-insights']`; RankWeb's save keeps the key (it starts from the whole stored array, `aw_seo_saved()` `:657`) |
| Rank Math schema entries (`rank-math-schema`) `:235` | switch | `AW_RM_SCHEMA_TYPES` (list) | Schema and FAQ |
| Share images and Open Graph (`opengraph`) `:244` | switch | `AW_SEO_ARTICLE_TYPES` (list) | Share |
| Directory sitemap (`sitemap`) `:253` | switch | — | Indexing |
| Redirects (`redirects`) `:261` | switch | — | Indexing |
| Breadcrumbs (`breadcrumbs`) `:269` | switch | — | Titles |
| Focus keywords (`focus-keywords`) `:277` | switch | `AW_SEO_GENERIC_WORDS` (list) | Focus keywords and analysis |
| Sitemap and llms.txt check (`health`) `:286` | switch | — | Indexing |
| llms.txt (`llms`) `:294` | switch | `AW_LLMS_PAGE_ORDER` (list), `AW_LLMS_GUIDE_TYPES` (list) | AI agents |
| Markdown for agents (`markdown`) `:304` | switch | `AW_MD_VARY_HTML` (int 1/0) | AI agents |
| Content analysis (`analysis`) `:313` | switch | `AW_SEO_RENDERED_TYPES` (list), `AW_SEO_SUMMARY_SKIP_TESTS` (list) | Focus keywords and analysis |

Save path today: `admin_post_aw_seo_settings` `:648-688` (`manage_options` + `check_admin_referer( 'aw_seo_settings_{tab}' )`), text ↔ value in `aw_seo_field_text()` / `aw_seo_field_value()` `:367-404`. The new REST save calls the same two functions and the same "store only what differs" rule, so nothing about storage changes.

### 1.5 Other stored data

| Name | Kind | Where | Lands |
|---|---|---|---|
| `aw_faq_off_types` | option, not autoloaded | `includes/faq.php:72-83`, saved `includes/faq-overview.php:198-213` (`manage_options`, nonce `aw_faq_types`) | **S** FAQ group, same option |
| `aw_seo_keep_listings` | option (listings kept indexed with no contact) | `includes/robots.php:76` (edited on Settings → Indexing since 12 Nov 2026) | **S** Indexing group shows it read-only (count + list); editing stays where it is set today (WP-CLI / Directory) unless the owner asks |
| `aw_seo_endpoints_last`, transient `aw_seo_endpoints_mailed` | option, transient | `includes/health.php:68-79` | **D** Sitemap and llms.txt tile |
| `aw_sitemap_filtered` | option | `includes/sitemap.php:69-78` | Same |
| `aw_seo_cache_gen` | option | `includes/helpers.php:306-325` | Same |
| `aw_seo_press_kw_1103` | option (one-off done flag) | `includes/focus-keywords.php:316-324` | Same |
| `aw_default_share_image` | option, read | `includes/helpers.php:750` | Same |
| `aw_faq_index` | transient, 1 hour | `includes/faq-overview.php:29-90` | **D** FAQ tile and **F** list read it |
| `aw_seo_dir_data`, `aw_seo_category_counts`, `aw_sitemap_dir_pairs`, `aw_seo_rendered_*`, `aw_faq_counts` (deleted) | transients | `includes/programmatic.php:41`, `:549`, `includes/sitemap.php:24`, `includes/analysis.php:100`, `includes/faq-overview.php:92` | Same |
| `aw_faq_experiment` | option, deleted on each count | `includes/faq-overview.php:91` | Same (removal of this line is a later clean-up, not this plan) |
| Post meta written: `_aw_seo_auto`, `_aw_focus_history`, `_aw_redirects_paused`, `_aw_faq_rm_block`, `aw_faq*`, Rank Math's `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_robots`, `rank_math_rich_snippet`, `rank_math_schema_*`, `rank_math_seo_score` | meta | titles, programmatic, focus-keywords, redirects, faq, rank-math-schema, analysis | Same |

### 1.6 REST routes

| Route | Method | Permission | Where | Lands |
|---|---|---|---|---|
| `aw/v1/seo-items` (type, page, per_page ≤ 200, missing) | GET | `aw_seo_api_allowed()`: `manage_options` or `aw_worker` (`includes/helpers.php:905`) | `includes/focus-keywords.php:173` | Same (workers' focus-keyword job; InsightWeb's Apply) |
| `aw/v1/seo-focus` (dry first) | POST | same | `includes/focus-keywords.php:224` | Same |
| `aw/v1/seo-score-items` | GET | same | `includes/analysis.php:219` | Same |
| `aw/v1/seo-scores` | POST | same | `includes/analysis.php:232` | Same |
| New `rankweb/v1/…` | — | — | — | §2–§4 |

Cookie requests to these routes already need the `wp_rest` nonce (WordPress core); the workers use an Application Password. Nothing changes here.

### 1.7 Cron jobs

| Hook | Schedule | Where | Lands |
|---|---|---|---|
| `aw_seo_fill_daily` (titles, descriptions, contact scores) | daily | `includes/titles.php:196-204`, `:440`, `:626`; `includes/programmatic.php:709`, `:974`; `includes/robots.php:113` | Same |
| `aw_seo_endpoints_watchdog` | hourly | `includes/health.php:82-90` | Same; **D** tile |
| `aw_seo_sitemap_daily` | daily | `includes/sitemap.php:84-90` | Same |
| `aw_faq_index_build` | single, when the FAQ count has run out | `includes/overview.php:112-113`, handler `includes/faq-overview.php:20` | Same; the Dashboard asks for it the same way |
| New `rankweb_health_build` | hourly | — | Phase 3 (§2): builds the tile counts in the background; added to the deactivation list |
| `aw_si_*` (15 regular jobs) | — | `includes/search-insights/cron.php` | SI |

### 1.8 WP-CLI commands

| Command | Where | Lands |
|---|---|---|
| `wp aw-faq` (report, check, import, convert-guides, undo-guide) | `includes/faq.php:1231` | Same; listed on **D** "Where things are" |
| `wp aw-seo rank-math-schema` (--dry-run, --type) | `includes/rank-math-schema.php:370` | Same; listed |
| `wp aw-seo share-jpegs` | `includes/helpers.php:732` | Same; listed |
| `wp aw redirects-list` | `includes/redirects.php:70` | Same; listed |
| `wp aw-si`, `wp aw-si-rising` | `includes/search-insights/cli.php` | SI |

### 1.9 Site Health tests

None in RankWeb today. `aw_si_health_test()` is SI. **New (owner, 11 Nov):** one test that reports the hourly sitemap and llms.txt check, phase 3 (§0 answer 2).

### 1.10 Filters RankWeb offers, and front-end hooks

Own filters (all kept, same names): `aw_breadcrumb_label` (`includes/breadcrumbs.php:71`), `aw_faq_context` (`includes/faq.php:383`), `aw_faq_head_parts` (`:251`), `aw_faq_heading` (`:239`), `aw_faq_min` (`:61`), `aw_faq_post_types` (`:93`), `aw_faq_registry` (`:398`), `aw_faq_show_here` (`:335`), `aw_faq_taxonomies` (`:99`), `aw_seo_content_signal` (`includes/robots.php:177`). Animal Writes Core answers `aw_faq_registry`, `aw_faq_context` and `aw_faq_show_here` from `includes/faq-data.php`.

Front-end hooks, by file (all **Same**; this plan changes no output):

| File | Hooks (line) |
|---|---|
| `titles.php` | `wp_after_insert_post` 183, `rank_math/vars/register_extra_replacements` 258, `document_title_parts` 456, `pre_get_document_title` 459/461, `rank_math/frontend/title` 473/597/615, `rank_math/frontend/description` 566/584, `rank_math/opengraph/facebook/og_title` 616, `rank_math/opengraph/twitter/twitter_title` 617, `aw_heading_dates` 642 |
| `programmatic.php` (Directory place pages) | `render_block_core/query` 533, `save_post` 569, `do_shortcode_tag` 650, `edited_/created_category` 707-708, `created_/edited_post_tag` 975-976 |
| `canonical.php` | `rank_math/frontend/canonical` 93 |
| `filtered-views.php` | `rank_math/frontend/robots` 267, `/title` 281, `/description` 290 |
| `robots.php` | `wp` 130, `rank_math/frontend/robots` 141/164, `template_redirect` (feeds noindex) 167, `robots_txt` 188, `rank_math/sitemap/entry` 224 |
| `schema.php` | `rank_math/json_ld` 1349/1459, `rank_math/head` 1544/1546 |
| `rank-math-schema.php` | `wp_after_insert_post` 286, `rank_math/json_ld` 299 |
| `opengraph.php` | 8 `rank_math/opengraph/*` hooks 32-85 |
| `sitemap.php` | `rank_math/sitemap/providers` 109, `rank_math/sitemap/post_type_archive_link` 156, `save_post` 165 |
| `redirects.php` | `parse_request` (upper case → lower case, 301) 105, `template_redirect` 126 (Library lang/issuer/court), 138 (`?author=` → About), 150 (Home `/page/N/` → Home), `do_redirect_guess_404_permalink` 160, `wp_trash_post` 167, `untrashed_post` 183 |
| `breadcrumbs.php` | `rank_math/frontend/breadcrumb/items` 130 |
| `llms.php` | `rank_math/llms_txt/*` 28-127 |
| `markdown.php` | `template_redirect` 317 (text/markdown on `Accept`) |
| `analysis.php` | `save_post` 97, `rank_math/researches/tests` 109, `rank_math/recalculate_score/data` 122, `rank_math/admin/editor_scripts` 136, `pre_get_posts` 199, `rank_math/acf/config` 260 |
| `faq.php` | `aw_hub_blocks` 767, `render_block_core/post-content` 777, `kits_page_context` 809 |
| `helpers.php` | `shutdown` 319, `aw_seo_cache_flush` 330, `image_editor_output_format` 680, `wp_generate_attachment_metadata` 721, `wp` 884 |

### 1.11 Functions other code calls (names kept)

- Animal Writes Core: `aw_seo_dir_h1()` (`includes/programmatic.php:332`) and `aw_seo_place_has_helpline()` (`includes/titles.php:362`) from `includes/directory.php:239-245`; `aw_rank_math_redirect()` (`includes/redirects.php:40`) from `includes/places.php:411-435`; `aw_faq_items()` from `includes/faq-data.php:1163`; FAQ edit-screen map `includes/edit-screens.php:652`.
- SourceWeb: `aw_rank_math_redirect()` (`includes/enrichment-cleanup.php:220`); `aw_seo_kp_refusal()` in `tests/run.php:425-429`.
- Search Insights / InsightWeb: `aw_seo_kp_replay()`, `aw_seo_api_allowed()`, `aw_seo_settings_embed()` (`includes/settings.php:695`), `AW_SEO_MENU`; guarded per InsightWeb §1.4.
- DirectoryWeb (its phase 1): the calls from `directory.php` become filters `directoryweb_place_h1`, `directoryweb_place_has_helpline`, `directoryweb_redirect_add`, plus `directoryweb_listing_indexable`. RankWeb answers them with `aw_seo_dir_h1()`, `aw_seo_place_has_helpline()`, `aw_rank_math_redirect()` and `! aw_listing_unreachable()` (`includes/robots.php:74`). That code ships with DirectoryWeb's phase 1, not here; this plan's Dashboard reads the same `aw_listing_unreachable()` so the two never disagree.

---

## 2. Dashboard tiles

All counts are built hourly by the new cron job `rankweb_health_build` into the transient `rankweb_health` (never on a page view; the first view after a deploy shows "Counting…" and asks for a single event, as the Overview's FAQ status does today, `includes/overview.php:104-116`). Read with `GET rankweb/v1/dashboard` (`manage_categories`). "Items" means published posts of `AW_SEO_ITEM_TYPES`. Every number is a link.

| # | Tile | Number | Sub-line | Link | Source |
|---|---|---|---|---|---|
| 1 | Search titles | Items with no title: no `rank_math_title` and no rule title (`aw_seo_item_title()`, `includes/titles.php:65`) | "of {n} items" and the type with most | that type's list in wp-admin | new `rankweb_count_missing_titles()`, prepared `$wpdb` per type |
| 2 | Descriptions | Items with no description: no `rank_math_description` and no rule description (`aw_seo_item_description()`, `:106`; `_aw_seo_auto` marks the daily fill) | "{n} filled by the daily job" | same | new `rankweb_count_missing_descriptions()` |
| 3 | Schema | Items of `AW_RM_SCHEMA_TYPES` whose Rank Math entry does not name the real type (`aw_rm_schema_sync( $id, true )`, `includes/rank-math-schema.php:206`) | "`wp aw-seo rank-math-schema --dry-run` would write {n}" | Settings → Schema and FAQ | new `rankweb_count_schema_gaps()` |
| 4 | Focus keywords | Items with no `rank_math_focus_keyword` (the same query as `seo-items?missing=1`, `includes/focus-keywords.php:206`) | "Filled by the workers' job" | — | same query, counted |
| 5 | FAQ coverage | Pages that show an FAQ (`aw_faq_index()['counts'][*]['faq']`) | "{n} draft questions waiting" | FAQ list, Waiting view | `aw_faq_index` transient |
| 6 | Noindex | Items and list pages noindex by RankWeb's rules: listings with no contact (`aw_sitemap_unreachable_listings()`, `includes/robots.php:197`), thin Directory pages (`aw_sitemap_directory_pairs()` below `AW_THIN_DISTRICT_TYPE`), filtered lists below `AW_FILTERED_MIN_ITEMS` | "{n} kept indexed on purpose" (`AW_DIRECTORY_KEEP_INDEXED` + `aw_seo_keep_listings`) | Settings → Indexing | new `rankweb_count_noindex()` over the existing functions |
| 7 | Sitemap and llms.txt | Answering / not answering (`aw_seo_endpoints_last`) | "Checked {time}" or the problem sentence | `/sitemap_index.xml`, `/llms.txt` | `includes/health.php` |
| 8 | Rank Math | Active {version} / not active | "{n} RankWeb modules off" | Settings → Modules | `aw_seo_extras_rank_math()`, `aw_seo_module_on()` |

Above the tiles, an attention strip (shell part) lists every tile in a warning state, as "Needs attention" does today. Below them, the read-only section **Where things are**: the edit-screen boxes (today's 3 rows), the WP-CLI commands (today's rows minus `wp aw-si`), and the Rank Math screens RankWeb leaves to Rank Math (Titles & Meta, Sitemap, General → Breadcrumbs and llms.txt, Redirections).

Tiles 4 and 6 are counts of RankWeb's own rules, not measurements; search figures stay in InsightWeb.

Tiles 1 and 2: the rules give nearly every item a title and a description (`aw_seo_item_title()` and `aw_seo_item_description()` return `''` only for hub Pages, which take theirs from `AW_HUB_TITLES` / `AW_HUB_DESCRIPTIONS`, so hub Pages are counted against those maps instead). Both tiles are expected near 0; they are there to catch a rule that stops working. Tile 3 counts the items for which `aw_rm_schema_sync( $id, true )` reports it would write.

---

## 3. FAQ working list

Shell list over REST. Same data as today's list table (`aw_faq_index()` rows).

- **Route:** `GET rankweb/v1/faq` with `view` (all | waiting), `type`, `search`, `orderby` (name | type | shown), `order`, `page`, `per_page`. Permission `manage_categories`. Rows carry `can_edit` from `aw_faq_can_edit()` (`includes/faq-overview.php:109`), so the list never offers an action the user cannot do.
- **Columns, All view:** Name (link to the edit screen at `#aw-faq`), Type, Questions shown, Auto / edited / own. **Waiting view:** Name, Type, Draft questions (first 10 shown, as `draft_q` today).
- **Views:** All (count), Waiting for approval (count), and **Counts** (the per-type table: Published, Show an FAQ, Questions but fewer than the minimum, Draft questions waiting; with "Counted {time}").
- **Filters:** type (post types and taxonomies present in the rows), search by name.
- **Row actions:** Edit FAQ (`edit_post` / `edit_term` per row), View.
- **Bulk action:** Approve (Waiting view): `POST rankweb/v1/faq/approve` with `items: ["post:12","term:7"]`. Permission `manage_categories`; each item checked again with `aw_faq_can_edit()`; the same loop as `includes/faq-overview.php:124-147` (own, not-approved questions get `approved` and today's date); answer lists done and skipped per item; recount after.
- **List action:** Recount: `POST rankweb/v1/faq/recount` (`manage_categories`), calls `aw_faq_index( true )`.
- Writing and editing questions stays in each item's FAQ box (§1.3).

---

## 4. Settings, services and the shell parts

### 4.1 Settings groups

One shell Settings screen, a section list on the left (the shell's pattern), one group per today's tab, each module a block with its description and its fields:

| Group | Modules | Extra |
|---|---|---|
| Modules | every module: label, description, On switch or "Always on: other modules use it." | "Takes effect on the next page load." after save, as today |
| Titles and descriptions | General, Titles, Breadcrumbs | — |
| Indexing | Canonicals, Robots rules, Filtered lists, Directory sitemap, Redirects, Sitemap and llms.txt check | `aw_seo_keep_listings`, read-only |
| Schema and FAQ | Schema, FAQ, Rank Math schema entries | **FAQ box on**: post types and taxonomies (`aw_faq_off_types`, `manage_options`; editors see it read-only as today) |
| Share images and Open Graph | Open Graph, plus General's two share fields | — |
| Focus keywords and analysis | Focus keywords, Content analysis | — |
| AI agents | llms.txt, Markdown | — |

Field controls follow the four types: int → number (min 0), list → one per line ("/" for the home page), map → "key \| value" per line, maplist → "key \| value \| value …". Each field keeps "Changed from the default" and gains the shell's Reset to default; each group keeps Restore defaults. A module switched off shows "Off: switch it on under Modules."

Routes (all `manage_options`, cookie nonce `wp_rest`):
- `GET rankweb/v1/settings`: every module, field, default, saved value and the text form of each value (`aw_seo_field_text()`).
- `POST rankweb/v1/settings/{group}`: posted text per constant → `aw_seo_field_value()`; stores only values that differ from the default; starts from the whole stored array so other keys (including `search-insights`) are never dropped; `update_option( 'aw_seo_settings', array_filter( $all ), true )` exactly as `includes/settings.php:684`. `reset: true` clears the group's fields.
- `POST rankweb/v1/settings/modules`: `{ on: { key: bool } }` for switchable modules only.
- `POST rankweb/v1/settings/faq-types`: ticked slugs; stores only the unticked, as `includes/faq-overview.php:205-207`, and deletes `aw_faq_index`.

The save logic moves out of `admin_post_aw_seo_settings` into one function both paths call (phase 3), so the old form and the new screen cannot drift while both exist.

### 4.2 Old addresses

The three slugs stay the shell's pages, so the menu, screen ids (`aw-seo-plus_page_…`), bookmarks and the CI baseline lines keep working:

| Old | New |
|---|---|
| `admin.php?page=aw-seo-plus` (Overview) | same slug, Dashboard |
| `admin.php?page=aw-faq` / `&view=waiting` | same slug, FAQ list / Waiting view |
| `admin.php?page=aw-seo-settings&tab={tab}` | `admin.php?page=aw-seo-settings#{tab}` (302 on `admin_init`, `wp_safe_redirect`; old module keys in `tab` mapped with `aw_seo_module_tab()` as today) |
| `admin.php?page=aw-seo-settings&tab=search-insights` | InsightWeb's Settings (unchanged) |
| `admin-post.php?action=aw_seo_settings`, `aw_faq_types`, FAQ bulk approve and recount on `load-` | kept for one release after the switch, then removed |

The redirects stay for two releases, as `admin-ui-spec.md` E1.

### 4.3 Connected services

One read-only shell service row: **Rank Math SEO**. Status: "Active, {version}" (`RANK_MATH_VERSION`) or "Not active: RankWeb does nothing until it is" with a link to Plugins. Summary: the Rank Math features RankWeb relies on and whether each is on (Sitemap, Redirections, llms.txt, Breadcrumbs, read from Rank Math's modules option, read only). No key, no Disconnect. Keys stay in Kits → Keys (owner, 11 Nov; §0 answer 1).

### 4.4 Requests to Kits Core (parts the design system may lack)

RankWeb registers only; if the shell lacks any of these, Kits Core builds it once for every plugin:

1. A tile with a "Counting…" state and a tile whose number is a link with a sub-line.
2. An attention strip above the tiles fed by the tiles' warning state.
3. A read-only reference section (tables) on a Dashboard.
4. A list with views, one of which is a different table (Counts), and per-row capability flags that hide row actions.
5. A bulk action whose answer is per item (done / skipped with a reason).
6. Settings field types: multi-line list, "key | value" map and "key | value | value" map list, each with "Changed from the default"; a group-level Restore defaults.
7. A modules group: switches plus "always on" read-only rows.
8. A checkbox group that one capability can edit and another can only read.
9. A read-only service row (no key, no Disconnect).
10. A plugin icon in the Kits → Plugins row and in the app header (§7).

---

## 5. Migration

- **Nothing to move.** No option, meta, transient, route, hook or cron name changes. The new screens read and write the same option through the same functions.
- **Before and after, every phase:** read-only SSH `wp option get aw_seo_settings --format=json`, the same for `aw_faq_off_types` and `aw_seo_keep_listings`, before the deploy and after; the files must be byte-identical unless the phase was meant to change a value (none is).
- **Save without a change is a no-op:** in each Settings group, Save with nothing changed; the option must stay byte-identical (proves the diff-only rule and that the `search-insights` key survives).
- **Export and import (owner, 11 Nov):** RankWeb's three options are in `kits_settings_transfer_options` from phase 1, so Kits → Tools → Export before and after each phase gives the same RankWeb part, and Check a file on the old export shows nothing would change.
- **Old addresses:** §4.2; each one opened once after the switch.
- **Workers and InsightWeb:** `aw/v1/*` routes untouched; one read-only `GET aw/v1/seo-items?type=aw_news&per_page=1` after each deploy, same shape.
- **Rollback:** each phase is one release tag (Deploy check); `rollback.yml` puts the last one back. No data step to undo.

---

## 6. Audit, release and testing checks, per phase

| Check | Today | Plan |
|---|---|---|
| PHPDoc header with `@wordpress-plugin` | Plain block, no marker | Phase 1, MailWeb's form (`plugins/mailweb/mailweb.php:2-20`) |
| Version constant = header | No constant | Phase 1: `RANKWEB_VERSION`; header, constant, `readme.txt` Stable tag, `.pot` Project-Id-Version always equal. 1.9.4 in phase 1; **2.0.0 in the commit that removes Search Insights** (InsightWeb phase 1); 2.1.0 REST layer; 2.2.0 shell screens |
| `readme.txt` with Stable tag and changelog | None | Phase 1, MailWeb's layout (`plugins/mailweb/readme.txt`): Requires, Tested up to, Requires PHP 8.0, Stable tag, Description (what RankWeb adds to Rank Math), Changelog from 1.9.3, Upgrade Notice |
| `languages/aw-seo-extras.pot` | None | Phase 1: `wp i18n make-pot . languages/aw-seo-extras.pot --domain=aw-seo-extras --exclude=includes/search-insights,tests` (composer script `make-pot`, as MailWeb). Text domain stays `aw-seo-extras` (it is the header's). Shell scripts: `wp_set_script_translations()` per script, same domain, `make-json` in the build. Many admin strings are plain literals today (`includes/overview.php`, `includes/settings.php`): they become translatable as each screen moves to the shell, not in a separate sweep |
| Capability + nonce on every route | `aw/v1/*`: `aw_seo_api_allowed()`; admin-post forms: `current_user_can` + `check_admin_referer`; FAQ bulk: `check_admin_referer( 'bulk-aw-faq-items' )` + per-item `aw_faq_can_edit()` | Every `rankweb/v1/*` route has a `permission_callback` with the capability in §2–§4, never `__return_true`; cookie calls carry `wp_rest` through `api-fetch`; writes re-check per item; args have a schema (type, enum, minimum) |
| PHPCS | Security ruleset, new findings only, changed files (`tools/ci/phpcs_delta.py`) | Same, on every changed file; plus `composer lint` (WordPress standard) run on the branch and reported, not gating |
| PHPUnit / Plugin Check | Not in the Deploy check | **Prove with pure-PHP tests plus `tools/ci/smoke.sh`** (no local WordPress, owner rule): `plugins/aw-seo-extras/tests/run.php` (no WordPress, like `plugins/aw-sourcing/tests/run.php`) for `aw_seo_field_text()` ↔ `aw_seo_field_value()` round trip of every default, the diff-only save keeping foreign keys, the tab and old-link mapping, `aw_seo_kp_refusal()`; a Deploy check step runs it. `smoke.sh` (already boots WordPress with Rank Math 1.0.280 and RankWeb) gains: every `rankweb/v1/*` route answers 401 without a login and 200 for `ci`; the three screens load without PHP notices; old `&tab=` addresses redirect. **Plugin Check:** a report-only step in `smoke.sh` (`wp plugin install plugin-check` + `wp plugin check aw-seo-extras --format=json`) in phase 1, gating from phase 4 once its first report is clean or each item is answered |
| Code review, wp-plugin-audit | — | Every phase: the code-review skill and wp-plugin-audit (dimensions A–D) on the branch; Codex comments answered |
| JS build | — | Phase 4: `@wordpress/scripts`, one entry per screen (`dashboard`, `faq`, `settings`), WordPress packages as externals, `.asset.php` for deps and version, `build/` committed (Hostinger pulls the repo, no build on the server), `wp-scripts lint-js` in the Deploy check, each script within the shell's budget (`admin-ui-spec.md` E6: 60 KB own code) |
| Front-end output | — | Every phase: render compare (§8 proof A) |

Already sound and kept (checked, not bugs): the settings save (`manage_options` + nonce per tab), the FAQ types save (`manage_options` + nonce), the FAQ bulk approve (nonce + per-item capability), the `aw/v1/*` routes (capability or the workers' capability), `phpcs:ignore` notes on navigation-only `$_GET` reads.

---

## 7. Icon

The source is `docs/brand/plugin-icons/rankweb.svg` (bars with a yellow dot), with PNGs at 128 and 256. RankWeb already carries its copies in `plugins/aw-seo-extras/assets/`: `icon.svg`, `icon-256x256.png` and `icon-128x128.png`. They are used in:

1. **Kits → Plugins row:** `kits_plugin_screens` gains an icon (Kits Core request 10; today the row is `[name, address, capability, what it is]`, `includes/kits-hub.php:33`).
2. **App header** of the three RankWeb screens (shell header slot).
3. **WordPress menu:** a one-colour version (WordPress tints menu icons; brand rule black, white, grey, yellow only as the rare accent), replacing `dashicons-search` at `includes/overview.php:25`. Until then, `dashicons-search` stays.

The PNGs are for places outside wp-admin (listings); the plugin's screens use the SVG.

---

## 8. Phases

Each phase is its own branch from `origin/wordpress-dev` with the review gate (§6), ships on the owner's yes with `tools/aw-ship.sh` (the Deploy check refuses while a FluentCRM campaign is sending), one deploy at a time.

| Phase | What | Size | Risk | Proof |
|---|---|---|---|---|
| **1. Release hygiene, no visible change** | PHPDoc header, `RANKWEB_VERSION`, `readme.txt`, `.pot`, `composer.json`, `tests/run.php` + its Deploy check step, Plugin Check report step, Kits status row reads the constant, RankWeb's three options in the Kits export. 1.9.4 | S | Low: no output change | A, B; Plugin Check report attached |
| **2. Search Insights leaves** | Done by InsightWeb phase 1 (its PR #117). RankWeb's side checked here: loader list, deactivation hook, module and hidden tab, Overview row and intro, Plugins link, Kits row text, header Description (the four lines in §0), baseline line 33; version **2.0.0** and changelog in that commit | (in InsightWeb's) | Medium, InsightWeb's (load order) | A, B; RankWeb screens show no Search Insights item; `wp aw-si` still runs from InsightWeb |
| **3. REST layer, no visible change** | `rankweb/v1/dashboard`, `faq`, `faq/approve`, `faq/recount`, `settings…`, `services`; one save function shared with the old form; `rankweb_health_build` + `rankweb_health`; the count functions; the Site Health test for the sitemap and llms.txt check; smoke tests for 401/200. 2.1.0 | M | Low: new routes only, old screens unchanged; the shared save function is the one risk, covered by tests and proof B | A, B, C |
| **4a. Dashboard on the shell** | Tiles, attention strip, Where things are; Overview's old page behind `&ui=classic` for one release (Kits Theme Options used `&ui=next` for its trial, commit `a9875535`: the same trial-then-switch order). Waits for the shell and the InsightWeb pilot | M | Low: read only | A, D (every Overview row has a home), counts checked against `wp eval` read-only on live |
| **4b. FAQ list on the shell** | List, views, filters, bulk Approve, Recount; old list behind `&ui=classic` | M | Medium: the only bulk write | A, D; one Approve on one item the owner picks, checked in its FAQ box |
| **4c. Settings on the shell** | Seven groups, FAQ box on, Rank Math service row, old `&tab=` redirects; old form behind `&ui=classic`. 2.2.0 | M | Medium: writes the option every request reads (the constants) | A, B (save-without-change byte-identical in every group), C |
| **5. Old screens out** | After one release: remove the classic pages and the admin-post handlers, update `tools/ci/settings_parts_baseline.txt` lines 29-32, keep the redirects two releases | S | Low | A, D; old addresses each opened once |

**Proofs**
- **A. Front end unchanged:** a fixed URL set fetched read-only from live before and after the deploy (Home, a News item, a guide, a Library document, a listing, a Directory state, a district, a district × type page above and below the threshold, a filtered list, a category, a tag, each hub page, `/sitemap_index.xml`, `/directory-sitemap.xml`, `/llms.txt`, `/robots.txt`, one page with `Accept: text/markdown`, `/Directory/` (upper case), `/?author=1`, `/page/2/`); compared on status and `Location`, `<title>`, meta description, robots, canonical, `og:*`, breadcrumb, and the JSON-LD graph (`@type`s and values, normalised). Zero differences. curl runs no JavaScript, so no GA4 hit. A small read-only script, `tools/seo_render_compare.py`, ships with phase 1.
- **B. No setting lost:** §5 option files byte-identical; export compare.
- **C. Routes:** every new route 401 without a login, 403 for an editor where it is `manage_options`, 200 for an administrator (smoke); `aw/v1/seo-items` same shape on live.
- **D. Nothing missing:** the inventory in §1 ticked against the new screens, row by row, before the old screen goes.

---

## 9. Not in this plan

- Renaming `aw_` names, the folder or the text domain (deferred).
- Any change to titles, descriptions, schema, FAQ, sitemap, noindex, redirect or llms.txt output.
- The DirectoryWeb filters' code (DirectoryWeb phase 1); InsightWeb's screens; the Keys screen (InsightWeb phase 2).
- Removing `aw_faq_experiment` clean-up lines and other dead code found while listing (recorded for a later small change).

---

## 10. Admin UI phase — public RankWeb (17 Nov 2026)

Scope: the public WordPress.org plugin (`/Users/abcom/Documents/GitHub/rankweb`, branch `main`, local only — nothing pushed or shipped). Owner's brief, relayed by the coordinator: an overview with charts on a dashboard (§7), and better list/detail screens (§8). **Shell theme unchanged** — same components, colours, typography and chrome; "better UI/UX" means better layout, tables, states and affordances *inside* the existing Kits shell, never a redesign of it.

**Governing standard (owner, 17 Nov):** the WordPress Plugin Developer Handbook (`developer.wordpress.org/plugins/`) — header requirements, Best Practices, Security (sanitize input / escape output / nonces / capability checks), Hooks, the Settings & Options API, Internationalization, Privacy, and the directory guidelines — enforced on everything built and still to build with `wp-org-submission` + `wp-plugin-audit` + `wp-coding-standards`, and the official Plugin Check run before submission. The dashboard (10a) was checked against it: REST route behind `rankweb_rest_can( RANKWEB_CAP )` (login + `wp_rest` nonce + capability, never `__return_true`), the one request input `sanitize_key( wp_unslash() )`, every query `$wpdb->prepare()` with `%i`/`%s` placeholders, all strings in the `rankweb` text domain, no external requests; PHPCS (the security + i18n ruleset) clean on the changed files.

### 10a. Dashboard — done (commit `458c302`)

The Dashboard is now the top-level RankWeb screen (`admin.php?page=rankweb-dashboard`); Settings moved to its own submenu slug `rankweb-settings` (`RANKWEB_SETTINGS`), and the feature screens still hang off `RANKWEB_MENU`. Drawn entirely with the bundled shell:

- **Tiles** (`Tile`/`TileGrid`) for the headline numbers, each with a **period-over-period change** (28- or 90-day chip, `ChipRow`): orphan items, broken links, average click-depth, custom-schema / video-schema / focus-keyword / meta-description coverage, and the exact-match anchor share. Warning tone on the ones over a guide.
- **MiniChart** (the shell's own greyscale bar figure, last bar = latest) for the trends (orphans, average click-depth) and the two small distributions (items by click-depth, anchor-type mix). No chart library.
- A "Needs attention" `Notice`, and a read-only **Where things are** `SectionCard`/`RefTable`.
- New route `GET rankweb/v1/dashboard` (`RANKWEB_CAP`, cookie nonce via `rankweb_rest_can()`) reads what the modules already measure — the link audit (`rankweb_link_audit_summary()` + a click-depth histogram and average over the audit table), the site-wide anchor-type totals, and coverage counts of `_rankweb_jsonld`, `_rankweb_videos`, `rank_math_focus_keyword` and `rank_math_description` over the admin-chosen post types (`rankweb_schema_custom_types()`, `rankweb_video_types()`, `rankweb_bulk_types()`). Read-only: the daily log that feeds the trends grows from a daily cron `rankweb_dashboard_snapshot` and the tail of each audit, never on a page view. The snapshot hook is added to the deactivation cleanup.

**Charts component decision.** A reusable brand-palette **Chart.js** component library was built first (`packages/rankweb-charts`, `@rankweb/charts` → `window.rankweb.charts`, mirroring the shell package: `TrendChart`, `CoverageChart`, `MixChart`, `BarChart`, each an accessible `<figure>` with a "View as table" fallback; palette black/white/grey + a rare yellow accent). The owner then **standardised the suite's overview-chart primitive on the shell's own `MiniChart`**, so `rankweb-charts` is **parked**: it stays on the branch (source only — `build/` and `node_modules/` git-ignored), is **not** loaded by the plugin and **not** shipped, and is kept only for a possible later "richer charts" revisit. The dashboard uses `MiniChart`.

Why greyscale is right for these charts (dataviz method): every figure here is single-series or a two-part part-of-whole, so identity rests on the axis labels and the SR table, never on hue — which is exactly the brand (black/white/grey, yellow a rare accent). The palette validator confirms the pairs that must separate do (`#27272a`/`#eab308`/`#e4e4e7`: CVD ΔE 20.1, normal-vision 20.7 — PASS); the lightness-band / chroma-floor FAILs are the achromatic brand by design (no categorical hue ramp to separate), and the sub-3:1 contrast WARN is relieved by the mandatory direct labels + table views.

### 10b. Admin screens (§8)

Upgrade the feature list screens (currently the server-side `class-rankweb-simple-table`) to the shell's React **`DataList`** (sortable, filterable, searchable, views, bulk, pagination, row actions — WordPress DataViews under the hood; see `packages/rankweb-shell/src/list.js` and `parts.js`), with clean detail/edit and the shell's empty/loading/error states, all drawn with shell components (no theme of its own). Verification stays: `php -l`, PHPCS (project ruleset), `rankweb-core` PHPUnit-lite, `tests/run.php`, `wp-scripts lint-js`, webpack build — no local WordPress.

**Internal links — done (commit `a3c9f27`).** The Audit and Anchor-mix tabs are now shell `DataList` screens (`src/links.js`, `build/links.js`): findings as view pills with live counts; server-side sort added to `GET rankweb/v1/links` (new `orderby`/`order`, columns whitelisted); pagination; per-row Edit/View; DataList's own empty/loading/error states; "Run the audit now" posts to `links/audit` and the list polls `links/summary` while it runs. The screen mounts for the Audit and Anchor-mix tabs (`rankweb_links_ready()`); the **Silos** tab links to its classic form screen (silo create/edit/delete stay on `admin-post` for now — they are write forms, not a list), and a no-JavaScript browser falls back to the classic tables.

**Images, SEO CSV, Redirect tools — done (commit `b52637b`).** These three are tool screens (dry-run/apply operations and CSV/​.htaccess downloads and uploads), not lists, so they became clean shell screens rather than data tables:

- **Images** (`rankweb-images`, `src/media.js`): find-and-replace and fill-captions as shell forms; the dry-run/applied report (a before/after table) shown inline.
- **SEO CSV** (`rankweb-seo-csv`, `src/csv.js`): Export and Import as shell forms; the import report (changes + skipped) inline.
- **Redirect tools** (`rankweb-redirects`, `src/redirects.js`): the current redirects as a read-only shell `DataList` (new read-only `GET rankweb/v1/redirects`, paginated), plus Export / Import / 404-log export / .htaccess download+write as shell forms; report inline.

Each screen's forms post to the **existing, audited admin-post handlers** (no new file-handling REST, so the upload/download/apply security is unchanged); those handlers already carry the dry-run report back through their per-user transient, which the enqueue hands to the screen (deleting it only when the shell loads) and the shell renders. A browser without JavaScript falls back to the classic forms. All shell components, no theme of its own. The shell's accessible `DataList` (semantic table, sortable headers, labelled controls, icon buttons with accessible names, keyboard pagination) and the shell form controls carry the accessibility; `frontend-design` / `ui-ux-pro-max` / `accessibility-review` principles were applied through them rather than by restyling.

**Silos on the shell — done (commit `516e82c`).** The Internal links → Silos tab is now a shell screen too: the silos are a `DataList` (`GET rankweb/v1/links/silos`) with Edit/Delete row actions and an Add/Edit form; create/edit/delete post to the existing audited `admin-post` handlers (`rankweb_silo_save` / `rankweb_silo_delete`), their result riding the same `?saved`/`?deleted` flag and error transient. The Internal links bundle now mounts for all three tabs; a no-JS browser still falls back to the classic tables and forms. **No classic-only admin screen remains.** §8 is complete: all feature screens (Internal links with its three tabs, Images, SEO CSV, Redirect tools) are on the shell.

### 10c. Ready to submit — handoff to the coordinator (17 Nov 2026)

The owner asked to "ship it"; a side session does not push or ship, so the artifacts are prepared and handed over, and the coordinator / owner runs the outward steps. Current state: everything built and verified statically (no local WordPress), all on branch `main` of the standalone repo (`/Users/abcom/Documents/GitHub/rankweb`), local only — no remote, nothing pushed.

- **Distributable zip:** `dist/rankweb.zip` (regenerated 17 Nov with the full Admin UI build; `tools/build-zip.sh`). Contains the runtime files, the committed `build/` (app screens + bundled shell), the readable `src/` and `packages/rankweb-shell/src/` (WP.org "no minified-only code"), and `vendor/wugweb/rankweb-core/` readable classes. The parked `packages/rankweb-charts` is now excluded from the zip (`.distignore`).
- **POT:** `languages/rankweb.pot`.
- **Static verification green:** `php -l`, PHPCS (project ruleset), `rankweb-core` PHPUnit-lite 125/125, `tests/run.php` 45/45, `wp-scripts lint-js`, webpack (6 entries). Safety audit done (own prefix everywhere, idempotent activation/uninstall, no Core writes; `RankWeb_Simple_Table` redeclare-guarded).

**Owner / coordinator steps to actually submit (Phase 1, need a live WP and the owner's accounts — not doable from a side session):**
1. Run the **official Plugin Check** in a live WordPress with Rank Math active; fix or note any findings.
2. Capture **screenshot PNGs** from the live admin for SVN `/assets/` (Dashboard, Internal links data table, Settings, a tool screen).
3. Create a **GitHub remote** and push branch `main` (the repo is local-only today).
4. Submit the zip at `wordpress.org/plugins/developers/add` from the owner's account; answer the reviewer; on approval, SVN deploy (see §6 / the `wp-org-submission` skill).

### 10d. Accessibility scope (owner decision, 17 Nov 2026)

**SEO-adjacent accessibility stays under RankWeb; a full WCAG suite would be its own plugin.** RankWeb already touches the overlap — the Images module does alt-text find-and-replace and caption fill, and the internal-link audit judges anchor/link-text quality. So the content-accessibility signals that are also SEO signals (missing alt text, heading structure, link-text quality) belong here and can be extended under RankWeb. Deeper, non-SEO accessibility tooling (contrast, ARIA, keyboard, full-page WCAG scans) is **not** folded into RankWeb; if wanted, it is scoped as a separate plugin (the way Search Insights became InsightWeb).

**First SEO-adjacent accessibility feature — built (commit `9333371`):** an **"Images missing alt text"** tile on the Dashboard — a count of published image attachments with no `_wp_attachment_image_alt` (new `rankweb_dashboard_alt()`, two prepared COUNT queries), with the period-over-period change from the daily log, a warning tone and an attention line when any are missing, and a link to RankWeb → Images (shown only when the media module is on). Reuses the Images module's data model.

### 10e. Admin-UI refinements (owner, 17 Nov 2026; commit `59494c0`) — within the shell, no theme restyle

1. **Full-width data tables.** A scoped plugin stylesheet (`assets/admin.css`, handle `rankweb-admin`, depends on `rankweb-shell`, enqueued on RankWeb's shell screens) makes the DataViews table fill the shell content area (max 1280px) instead of shrinking to its columns. Layout only (table `width:100%` + the dashboard chart grid using the shell's `--ks-s4` spacing); it changes no colour, type or chrome, and the bundled shell package is untouched (so other plugins' copies don't diverge).
2. **Grouped dashboard blocks.** The KPIs are regrouped from one flat tile grid into themed `SectionCard`s each with its own `TileGrid` and a real `<h2>`: "Internal-link health" (orphans, broken, click-depth, exact-match anchors + the depth / anchor MiniCharts), "Internal links over time" (the trend MiniCharts), "SEO & accessibility coverage" (schema, video, focus keyword, meta description, images-missing-alt), then "Where things are". Gives a proper h1→h2 outline and consistent shell spacing; greyscale brand kept.

Any further code change means `dist/rankweb.zip` must be regenerated with `bash tools/build-zip.sh` before submission — the Admin UI build changed since the last zip.

---

## Appendix A — Internal links plan

_Folded in 17 Nov 2026 from the former `docs/rankweb-internal-links.md`, so RankWeb has one living plan doc._

# RankWeb: Internal links

RankWeb adds what Rank Math does not do for internal links. Rank Math's link counter (`wp_rank_math_internal_links`,
`wp_rank_math_internal_meta`) stays Rank Math's: it reads `post_content` only, on save, and keeps no anchor text, rel
or click depth. RankWeb reads it, never writes it.

Four phases, each usable on its own:

1. **Auto-linker** (done on branch `claude/rankweb-internal-links`, 14 Nov 2026).
2. **Link audit** (built 16 Nov 2026 on branch `claude/rankweb-link-audit`): links in and out, click depth, orphans, broken links, redirects, nofollow, canonicals elsewhere.
3. **Anchor mix** (built 17 Nov 2026 on branch `claude/rankweb-anchor-mix`, on top of the audit branch): anchor types per target, warnings, keyword rotation.
4. **Silos** (built 17 Nov 2026 on branch `claude/rankweb-silos`, on top of the anchor-mix branch): hub and supporting items, virtual and circle modes.

## Phase 1: auto-linker

| Part | Where |
|---|---|
| Link keywords per item | post meta `_aw_link_keywords` (list). RankWeb content panel (block editor) and the "Link keywords" box (classic editor). Until saved, the item's Rank Math focus keywords stand in. |
| Keyword index | table `{prefix}aw_link_keywords` (keyword, keyword_norm, target_id, priority). One item's rows on save; the whole index by the daily job `aw_link_index_build` and after a Settings save that changes the linked types or the items left out. |
| Links added | `the_content` (priority 25), singular views of a linked type, the item of the main query only. `AW_Linker` (DOMDocument, no regex on HTML). |
| Cache | transient `aw_seo_link_<id>`: the item's modified time, the pages' version (`aw_link_page_version`), the settings and the input HTML; the linked HTML is kept only when a link was added. One item's keyword change raises only the index version (`aw_link_index_version`), so other pages learn a new keyword at the daily rebuild or after a Settings save; an address change (slug, parent, status) or a deleted target raises the pages' version too. The index is read once into `aw_seo_link_index` (the best five targets per keyword, no keyword text); no query per keyword. |
| Links table | `{prefix}aw_links` (source_id, target_id, url, anchor, anchor_type, origin auto/manual, rel, updated), written when an item's linked output is built (one writer per item, by a named database lock; nothing is written when the rows are unchanged) and removed when the item is no longer published or is deleted. |
| Settings | RankWeb → Settings → Internal links. |
| Tests | `tests/LinkerTest.php` (PHPUnit; `php tests/phpunit-lite.php` runs it without PHPUnit, as the Deploy check does). |

Rules: whole words only, case-insensitive, any script (letters, combining marks, digits); the longest keyword first;
never inside h1–h6, a, code, pre, script, style, button, figcaption (nor textarea, svg, noscript, template, select,
option). A keyword needs 3 characters, and one word from "Words that alone make no keyword" never links alone.
An item the HTML parser would change (camelCase SVG attributes, a div inside a p, missing end tags) is left as it is: the page is written back from the parsed tree only when its tags, attribute names and end tags equal the input's. Added links carry the class `aw-auto-link`. With no linked post types (the default) no link is added.

### Settings (defaults)

| Setting | Default |
|---|---|
| Post types linked | none (nothing is linked) |
| Most links added to one item | 5 |
| Most links to the same page in one item | 1 |
| Link only the first time a keyword appears | 1 (on) |
| Never link an item to itself | 1 (on) |
| Skip a page the item already links to by hand | 1 (on) |
| Items left out (never linked from or to) | none |

## Phase 2: link audit

Scheduled job `aw_link_audit` (daily, or "Run the audit now" on RankWeb → Internal links). It works in slices of about 20
seconds and carries on in a one-off event (`more`) until done, so no browser tab must stay open. WP-Cron only: Action
Scheduler is not used. Three steps:

1. **Collect.** Each published item of the linked post types is rendered (`the_content`), linked as a visitor would see it
   (same index and settings as the auto-linker, `aw_link_run()`) and its links are recorded in `{prefix}aw_links`.
2. **Check.** Addresses on this site that no item owns are asked for once (HEAD, no redirect followed, answer kept six
   days): 404 and 410 are broken, 3xx is a redirect. An error or a refusal is never counted as broken.
3. **Analyse.** `AW_Link_Audit` (pure PHP, `class-aw-link-audit.php`) works out each item's links in and out, clicks from the
   home page (breadth first from the items the home page and the menus link to), and the findings. The result is one row
   per item in `{prefix}aw_link_audit`.

Findings: orphan (nothing links in; a link from the home page or a menu counts), too many clicks from the home page (or no
way to reach it), too many links in the item, broken link, link through a redirect (also a link written with an old
address of an item), internal nofollow, link to an item whose canonical points elsewhere, link to an address on the
do-not-link list.

The first click is the front page (when it is a page), menus, and block navigation and template parts saved in the
database. Template files that only live in the theme are not read. Rank Math's link counter is not used.

Settings (RankWeb → Settings → Internal links): most clicks from the home page (3), most links in one item (100),
addresses not to link to (thank-you, thanks, download). Screen: RankWeb → Internal links (core list table, filter by
finding). Dashboard tile "Internal links" (orphans, items with a finding). REST: `GET rankweb/v1/links`
(flag, page, per_page), `GET links/summary`, `POST links/audit` (administrators). Table `{prefix}aw_link_audit` has its
own version option `aw_link_audit_db`; uninstall removes it, with the audit's options.

## Phase 3: anchor mix

Every link in `{prefix}aw_links` gets an `anchor_type`, written with the row. The sorter is `AW_Anchor`
(`class-aw-anchor.php`, pure PHP). First match wins:

| Type | Rule |
|---|---|
| image | the link holds an image and no text (the ALT text is the anchor) |
| naked | the anchor is an address (`https://…`, `www.…`, `example.org/page`) |
| generic | one of the editable list (click here, read more, this page…), compared as normalised words |
| exact | equals the target's **main** keyword (its first link keyword) |
| branded | contains a brand name (setting), as whole words |
| long_tail | more words than the limit (setting, 6) |
| partial | contains a word of 3 letters or more of any of the target's keywords (so another keyword of the target is partial, not exact) |
| other | anything else |

For each target the audit counts the types (links to items only) into the transient `aw_seo_anchor_mix`. RankWeb → Internal
links → Anchor mix lists targets with their shares and warns when exact match is over 20% or any other type is over 60%.
A target with fewer than 5 links in gets no warning (a share of two links says nothing); the minimum is a setting. The
guides disagree on the right exact share (1 to 5%, 20%, 60%), so all three limits are settings.

Rotation: before it links a keyword the auto-linker asks `aw_anchor_ok()` (one transient read, no query per keyword). A
keyword that would put its target over a limit is skipped, so the target is linked by another of its keywords, or not
at all in that item. The snapshot is the last audit's, so it changes once a day; a page already cached keeps its links
until it is built again (the thresholds and the brand, generic and long-tail settings are part of the cache key).

Settings (Internal links): most exact match (20), most of any other type (60), fewest links before a warning (5), brand
names (Animal Writes), generic anchors, words in a long-tail anchor (6). REST: `GET rankweb/v1/links/anchors` (warn,
page, per_page). The Dashboard tile "Internal links" shows the items over a limit as a detail line. Existing rows get
their type at the next audit.

## Phase 4: silos

A silo has a name, one hub item, its supporting items in order, and a mode. **Storage: two custom tables, not a private
taxonomy.** `{prefix}aw_silos` (name, hub, mode; the hub is unique) and `{prefix}aw_silo_items` (the item ID is the primary
key, so the database itself keeps an item in one silo only; `position` is the circle's order). A taxonomy would show in the
editor, the REST API and the sitemaps, allow one item in several silos, and keep no order. Own version option
`aw_silo_db`; uninstall removes the tables (written to the backup first).

| Mode | Links |
|---|---|
| virtual | a supporting item links to the hub and to the other supporting items (the ones after it first, so links spread round the silo, at most "Silos: most links an item shows", 10); the hub links to its supporting items |
| circle | each item links to the next one and back to the hub; the last one only to the hub; the hub links to the first |

- **On the page:** the end of the content of a silo member gets a `<nav class="aw-silo-links">` list (`the_content`, after
  the auto-linker; singular main view only). No CSS is added: it is a plain list under a heading.
- **Between silos:** an item in one silo links to another silo's hub only. The auto-linker skips such a target and tries
  the next candidate of the keyword, and tries the item's own silo's targets first. Items in no silo are not restricted.
- **Audit:** the silo links count as links (they are not saved in the content), so they give in-links, depth and
  orphan results. A new finding "Link into another silo's supporting item" flags any link (the auto-linker's or a hand-made
  one) from an item in one silo to another silo's supporting item. This is wider than the plan's wording (supporting item
  to supporting item): a hub is held to the same rule as the auto-linker.
- **Checks on the Silos tab:** fewer than 5 items (hub counted), and addresses deeper than 3 folders
  (`/library/guides/page/` is 3). Both limits are settings.
- **Screen:** RankWeb → Internal links → Silos: a list, and a form (name, hub item ID, mode, supporting item IDs one per
  line). The form needs an administrator; it refuses an item already in another silo, naming it, and an item that is not
  published with its own page. Deleting a silo removes its settings only. Silo links are not in the anchor mix (their
  anchor is the item's title and they are not in `aw_links`). REST: `GET rankweb/v1/links/silos`.

---

## Appendix B — Rank Math Pro audit and build plan

_Folded in 17 Nov 2026 from the former `docs/rankweb-pro-audit.md`._

# RankWeb: Rank Math Pro audit and build plan (14 Nov 2026)

Every feature Rank Math keeps for Pro (Business and Agency included), checked against what this site already has, and
what RankWeb will build. Sources: rankmath.com/offer (Free vs PRO table, read 14 Nov 2026), the module list of Rank Math
Free 1.0.282 on live (`includes/module/class-manager.php`: modules marked `probadge` are Pro only, `upgradeable` ones
have a Pro part), the knowledge base (rankmath.com/kb), and the code of RankWeb, InsightWeb, Animal Writes Core and Kits
Core on `wordpress-dev`.

Site facts used for the decisions (live, read only, 14 Nov 2026): WordPress 7.1.4 with the Abilities API; block theme
Kits by Wugweb; no WooCommerce, EDD, Elementor, Divi, bbPress or podcast plugin; 1,705 published News items, 6,872
listings, 212 Resources posts, 134 Library items, 24 pages; no published item with a password; Rank Math tables present:
404 log, analytics (GSC, inspections, objects), internal links and meta, redirections and their cache.

Rules that decide what can be built: no paid APIs or plans (free quotas only, no paid AI ever); RankWeb does only what
Rank Math Free does not; Search Insights stays in InsightWeb (split out 11 Nov 2026), so analytics features land there
and RankWeb's Overview shows and switches them.

## 1. Where every Pro feature stands

Status: **Have** = already on this site (where) · **Build** = RankWeb builds it (phase in section 2) · **InsightWeb** =
built in InsightWeb · **Free** = Rank Math Free already does it · **Not needed** = the site has nothing it applies to ·
**Can't** = needs a paid service.

### Analytics and tracking

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced Google Analytics 4 integration | GA4 numbers per post in WordPress | Have | InsightWeb `ga4.php` (Site Kit) |
| Keyword rank tracker (500 to 75,000 keywords) | Daily Google position per keyword | Have | InsightWeb keywords, GSC positions, OpenSEO import; no paid tracker |
| Position history for keywords and posts | Position over time | Have | InsightWeb observations table (`obs`), rollups |
| Track top 5 winning / losing keywords | Biggest movers | InsightWeb | Rising/drops exist; add a winners/losers card from `obs` (phase G) |
| Track top 5 winning / losing posts | Biggest page movers | InsightWeb | Same, by page (phase G) |
| Check ranking keywords for each post | Queries per post | Have | RankWeb content panel "Searches it shows for (28 days)" |
| Advanced content SEO overview | Per-post table of SEO score, traffic, keywords | Build | RankWeb Overview "Content" table from Rank Math score + InsightWeb data (phase G) |
| Single post performance badges | Badge in list screens (clicks, position) | Build | List-table column from InsightWeb data (phase G) |
| Track SEO performance of individual posts | Per-post traffic history | InsightWeb | Pages tab exists; add a per-item view (phase G) |
| Track PageSpeed for each post and page | PSI per URL | InsightWeb | `psi.php` covers key and campaign pages; add "check this page" on demand, free PSI key quota (phase G) |
| Track Google index status | URL Inspection per post | Build | GSC URL Inspection API (free, 2,000 a day per property), into Rank Math's `wp_rank_math_analytics_inspections` shape or our own table (phase G) |
| AI search traffic tracker | Visits from ChatGPT, Perplexity, Gemini … | Have | InsightWeb "AI referrals" (`referrer-icons.php`, GA4) |
| SEO performance email reports (7, 15 or 30 days) | Mailed digest | Build | One monthly digest to the admin from InsightWeb numbers, `wp_mail` from support@ (phase G) |
| White-labelled email reports, client management, support for client sites | Agency reselling | Not needed | One site |
| Google data fetch frequency (daily), days to keep Google data (no limit) | Storage | Have | InsightWeb daily runs; months kept for good |
| Import GSC and GA data from one country | Country filter | InsightWeb | Add a country setting to the GSC and GA4 calls (phase G) |
| Google Trends integration | Trend lines for keywords | Have | InsightWeb Rising (`rising.php`) |
| Google AdSense earning history | AdSense numbers | Not needed | Ads are off; no money items shown (owner, 12 Nov 2026) |
| MCP tools for agentic SEO | Lets AI agents read and change SEO data | Build | WordPress 7.1 Abilities API: RankWeb abilities (read SEO fields, link report, issues; write titles/descriptions/keywords with edit_post) exposed through the MCP adapter (phase H) |
| Free Rank Math AI trial, Content AI | Paid AI writing credits | Can't | Paid AI; the RankWeb content panel gives the site's own data instead |

### Internal links

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Automated keyword linking | Links keywords to pages | Build (done on branch) | Internal links phase 1, `claude/rankweb-internal-links` |
| AI internal linking (AI Link Genius) | Suggests links with AI | Build | Same index plus the content panel's "Internal links to add"; no paid AI |
| Link report | Links in and out per post | Build | Internal links phase 2 |
| Broken link detector | Broken links in content | Build | Internal links phase 2 (internal); external links checked in batches with HEAD requests (phase B) |
| Orphan page detector / detect orphan pages | Pages nothing links to | Build | Internal links phase 2 (rendered pages, not post_content only) |
| Mark cloaked links as external | `/go/` affiliate links counted as external | Not needed | No affiliate links; becomes one setting in phase 2 if a prefix is ever used |

### Schema

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| The most advanced schema generator, 840+ types, custom schema builder | Any schema.org type in a form | Build | Per-item "Custom schema (JSON-LD)" box: paste JSON-LD, checked with `json_decode`, `@context` forced, merged into Rank Math's graph (phase D) |
| Add custom schema using JSON-LD/HTML | Raw JSON-LD | Build | Same box (phase D) |
| Add unlimited multiple schemas | Several per item | Build | Same box takes a list (phase D) |
| Automate schema implementation | Schema templates by post type or condition | Have | RankWeb `schema.php` builds the graph per type (listings, News, Library, campaigns, places) |
| Validate schema with Google | Link to the Rich Results Test | Build | A "Test in Google" link per item and in the list (phase D) |
| Import schema from any website | Copy JSON-LD from a URL | Build | Read a URL's JSON-LD into the custom box for editing (phase D) |
| Speakable schema | Parts for voice assistants | Build | `speakable` cssSelector on News and Resources (title, summary) (phase D) |
| Dataset schema | Data downloads | Build | Library items that are data files (phase D; check which items qualify first) |
| Fact check schema (ClaimReview) | Fact checks | Not needed | The site publishes no fact checks; never added to items that are not |
| Podcast schema, podcast module | Podcasts | Not needed | No podcast |
| Carousel schema | ItemList for list pages | Have | `ItemList` on hubs and places (`schema.php`) |
| Mentions and About schema | Entities a page is about | Build | `about` / `mentions` from the item's categories, tags and linked campaign (phase D) |
| Automatic video detection, video data fill, video schema | VideoObject from embeds | Build | YouTube embeds in content → VideoObject with oEmbed title and thumbnail (free) (phase C) |
| Advanced HowTo schema | HowTo steps | Not needed | Google no longer shows HowTo results; not added |
| 18 / 6 extra schema types | Prebuilt types | Free (most) | Rank Math Free's types plus RankWeb's |
| Automatic Q&A schema for bbPress | Forums | Not needed | No bbPress |
| Elementor / Divi accordion to FAQ schema | Page builders | Not needed | Block theme; RankWeb's FAQ module does this for blocks |

### Sitemaps and indexing

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Google News sitemap | `news-sitemap.xml`, last 2 days, `<news:news>` | Build | 1,705 News items (`aw_news`) (phase C) |
| Google Video sitemap | `video-sitemap.xml` | Build | Items with a video embed (phase C, after counting them) |
| Noindex password-protected pages | Robots noindex on protected items | Build | One rule in `robots.php`; none today, so a safeguard (phase C) |
| Automated SEO sitemaps | Sitemaps | Free | Rank Math Free, plus RankWeb's Directory sitemap |

### Redirections and 404

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced redirections module (categories, schedule) | Groups and scheduled redirects | Build | Read and write Rank Math's `wp_rank_math_redirections`; categories as a column (phase F) |
| Import redirections via CSV | Bulk add | Build | CSV upload, preview, then add through `aw_redirect_add()` (phase F) |
| Export 404 log | CSV of `wp_rank_math_404_logs` | Build | Download on the Tools tab (phase F) |
| Sync redirections to .htaccess | Server-level redirects | Build, off | The server is LiteSpeed (reads .htaccess); a block between `# BEGIN RankWeb Redirects` markers, plain path rules only, written only when switched on, with a backup of the old file (phase F) |

### Content, images, bulk work

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced bulk edit, advanced quick edit | SEO title, description, focus keyword, robots in list screens | Build | Quick Edit and Bulk Edit fields on every item type with Rank Math's box (phase E) |
| Complete import/export, import SEO data via CSV | Titles, descriptions, keywords by CSV | Build | Tools tab: export and import CSV by ID or URL, preview first (phase E) |
| Import/export focus keywords | Keywords by CSV | Build | Same CSV (phase E) |
| Find and replace image alt/title/caption | Bulk media text | Build | Tools tab, preview first, limits 100/100/160 as the media rules (phase E) |
| Automate image captions | Caption from the file or title | Have / Build | Core `media-autofill.php` fills alt and titles; captions added there (phase E) |
| Image SEO Pro (alt from title etc.) | Automatic alt/title | Have | Core `media-autofill.php`, `media.php` |
| Watermarked social media images | Play icon or logo on OG images | Not needed | Share pictures are made 1080x1080 with words (owner, 13 Nov 2026); a watermark would clash |
| Advanced post filtering | Filter lists by SEO score, schema, noindex | Build | Filters on item lists (phase E) |
| Local SEO Pro with multi-locations | Several LocalBusiness locations | Have | Directory listings carry Organization, PostalAddress, GeoCoordinates (`schema.php`) |
| Advanced local SEO blocks | Address, map, hours blocks | Have | Kits blocks and listing templates |
| WooCommerce SEO Pro, complete EDD SEO | Shops | Not needed | No shop |
| Complete Elementor / Divi integration, Elementor breadcrumbs widget | Page builders | Not needed | Block theme; RankWeb breadcrumbs |
| Custom setup wizard mode | Wizard | Build | RankWeb's own setup wizard (phase A) |

## 2. Build plan

Every phase works on its own, each module has its switch on RankWeb → Settings → Modules, and each phase stops for the
owner's check. Settings need manage_options, item fields edit_posts, nonces on every form, every input sanitised,
every output escaped, every string in the aw-seo-extras text domain.

- **A. Overview, switches, setup wizard, tools.** The Overview already lists every module (`overview.php`: what it does,
  where it is set up, its status); its switches are on Settings → Modules. The Overview gets one card per module with
  the on/off switch on the card, a link to its settings, its last job run and its numbers. Setup wizard on first activation and from the Overview (site
  type, linked post types, News sitemap, schema defaults, internal links, done screen), each step skippable, nothing
  saved until a step is confirmed. Tools tab with RankWeb's own: clear RankWeb caches, rebuild the link index, count the
  Dashboard now, re-create RankWeb tables, delete internal links data, rebuild the FAQ index, export and import RankWeb
  settings (JSON). Rank Math's own tools stay on Rank Math → Status & Tools.
- **B. Internal links phases 2 to 4.** Link audit (report, orphans, broken, redirects, nofollow, canonical elsewhere,
  click depth), anchor mix, silos (as planned in `docs/rankweb-internal-links.md`), plus the external broken-link check.
  **Built 16–17 Nov 2026** on branches `claude/rankweb-link-audit`, `claude/rankweb-anchor-mix`, `claude/rankweb-silos`
  (stacked, in that order), not live yet.
- **C. Sitemaps and video.** Google News sitemap, video detection and VideoObject, video sitemap, noindex on protected
  items. **Built 17 Nov 2026** on branch `claude/rankweb-video-noindex` (stacked on B), not live yet. The News sitemap
  was already live (12 Nov). New: module "Video" (YouTube and Vimeo detection from content, oEmbed for title, thumbnail
  and — Vimeo — duration and date; no paid API, so a YouTube video has no duration or date), a VideoObject in Rank
  Math's graph, a `video-sitemap.xml` served and listed like the News one, and a "Keep password-protected items out of
  search" robots setting (on by default). Tests: `AW_Video` detection, node and duration. Daily backfill job
  `aw_video_sync`.
- **D. Schema.** Custom JSON-LD box (several per item), import from a URL, Rich Results Test links, speakable, about and
  mentions, Dataset where it applies. **Built 17 Nov 2026** on branch `claude/rankweb-schema-custom` (stacked on C), not
  live yet. Module "Custom schema": a JSON-LD box (one object, a list, or a @graph; checked on save by `AW_Schema` and
  given a stable @id), Speakable CSS selectors, About and Mentions (names or URLs as Thing references), import JSON-LD
  from a URL (the page's application/ld+json read into the box), a Dataset starter template, and a Rich Results Test
  link. The nodes join Rank Math's graph; Speakable, about and mentions go on the WebPage node. Tests: `AW_Schema` parse,
  extract, @id, speakable and things. No scheduled job.
- **E. Bulk work.** Quick and Bulk Edit SEO fields, SEO data and focus keyword CSV export/import, media find and replace,
  automatic captions, list filters. **Built 17 Nov 2026** on branch `claude/rankweb-bulk` (stacked on D), not live yet.
  Two modules: "Bulk edit" (a SEO column, filters for missing title/description/focus/noindex/orphan, Quick Edit for
  title/description/focus, Bulk Edit for focus keyword, link keyword and the index rule, and a RankWeb → SEO CSV screen
  with export and a dry-run import) and "Images" (find and replace across alt/title/caption with a dry run, and fill
  missing captions from a pattern). A new scalar "text" settings type was added for the caption pattern. Pure `AW_Bulk`
  (CSV read/write, find-and-replace) with tests. Note: Quick Edit and Bulk Edit use the standard inline hooks and need a
  wp-admin check (not run on WordPress here).
- **F. Redirections.** CSV import, 404 log export, categories, .htaccess sync (off by default). **Built 17 Nov 2026**
  on branch `claude/rankweb-redirects` (stacked on E), not live yet. Module "Redirect tools": export and import the
  redirects as CSV (source, target, code 301/302/307/308/410/451, comparison; import has a dry run and writes through
  `aw_rank_math_redirect()` into Rank Math's one list), export Rank Math's 404 log as CSV, and an Apache/LiteSpeed
  .htaccess block (download always; a live write only when the setting is on, a backup first, via
  `insert_with_markers()`). `aw_rank_math_redirect()` now takes a status code. Pure `AW_Redirects` (CSV, validation,
  .htaccess lines) with tests. **Categories were not built**: Rank Math's list has no category field and the owner's
  rule keeps every redirect in that one list, so a parallel category map keyed by Rank Math id would be fragile — left
  for the owner to decide. The live .htaccess write needs a wp-admin check (not run on WordPress here).
- **G. InsightWeb additions.** Index status (URL Inspection API), winners and losers, per-item performance badge and
  view, content SEO overview, PageSpeed on demand, country filter, monthly email digest.
- **H. Agent tools.** RankWeb abilities through the WordPress Abilities API and MCP adapter, read first, then writes
  that need edit_post. **Built 17 Nov 2026** on branch `claude/rankweb-agent` (stacked on F), not live yet. Module
  "Agent tools": registers RankWeb abilities on `wp_abilities_api_init` (WordPress 6.9+; guarded by function_exists, so
  nothing breaks without it). Reads (get-seo, audit-summary, list-orphans) register when the module is on; the writes
  (set-seo, add-redirect, set-schema) only when "Allow writes" is on, and each checks its capability (edit_post or
  manage_options). The MCP adapter plugin, where installed, exposes the public abilities. Pure `AW_Agent` (manifest,
  name validation, input tidy) with tests. Needs the Abilities API and a live check (not run on WordPress here).

When all phases are done: version 2.1.0, changelog, readme.

---

## Appendix C — Public WordPress.org rebrand and generalise (scope)

_Folded in 17 Nov 2026 from the former `docs/rankweb-org-rebrand.md`. The public plugin built from this scope lives in its own repo at `/Users/abcom/Documents/GitHub/rankweb` (branch `main`, local); current build state is in §10 above._

# RankWeb → a public WordPress.org plugin: rebrand and generalise (scope, 17 Nov 2026)

Scope only. Nothing here is built. It plans extracting a **generic, reusable, WordPress.org-listable** plugin from
RankWeb (`plugins/aw-seo-extras`). RankWeb itself stays as the Animal Writes site plugin, self-hosted; the public
plugin is a **separate codebase** that reuses RankWeb's generic parts.

## 1. Why this is feasible

RankWeb's 2.1.0 work is already generic. Every pure class and every 2.1.0 module has **zero** Animal Writes coupling
(checked 17 Nov: `grep` for `aw_listing|aw_campaign|aw_news|aw_legal|Animal Writes|Directory|Kits|InsightWeb` returns 0
across all of them; `video.php` has one, a comment about the News sitemap). The coupling lives in the **old 2.0.0 site
modules**, which the public plugin simply will not include.

So the public plugin is "RankWeb minus the site-specific modules", not a rewrite. The hard part is the surrounding
work: a new name and one prefix, a standalone Settings UI (RankWeb borrows the Kits admin shell), generic defaults, and
WordPress.org compliance.

## 2. The split: what ports, what is dropped

**Ports as-is (generic Rank Math add-on features):**

| Area | Files | Coupling |
|---|---|---|
| Internal links: auto-linker, audit, anchor mix, silos | `class-aw-linker`, `class-aw-anchor`, `class-aw-silo`, `class-aw-link-audit`, `internal-links`, `link-audit`, `link-anchors`, `silos` | none |
| Video SEO (VideoObject + video sitemap) | `class-aw-video`, `video` | none (drop the News-sitemap aside) |
| Custom JSON-LD, Speakable, about/mentions, import-from-URL | `class-aw-schema`, `schema-custom` | none |
| Bulk edit + SEO CSV | `class-aw-bulk`, `bulk-edit`, `bulk-csv` | none |
| Image find-and-replace + captions | `media-seo` | none |
| Redirect tools (CSV, 404 export, .htaccess) | `class-aw-redirects`, `redirect-tools` | none (reads Rank Math's tables, which is the point) |
| Agent abilities (Abilities API) | `class-aw-agent`, `agent-tools` | none |
| Module on/off framework + Settings API | the module registry and `aw_seo_setting_*` in `settings.php` | light (strip the site tabs/modules) |
| Password-protected noindex | the rule in `robots.php` | that one rule only |
| The 9 PHPUnit test classes | `tests/*Test.php`, `phpunit-lite`, `run.php` | none |

**Dropped (Animal Writes / Kits specific — not in the public plugin):**

- Directory sitemap, News (Google News) sitemap, filtered-list views, Directory place/type logic, `aw_listing` /
  `aw_campaign` / `aw_news` / `aw_legal` content types.
- The listing (NGO/shelter/government) schema, campaign/Event/pain-scale schema, hub/archive titles and descriptions,
  the hardcoded "Animal Writes" publisher — i.e. most of `schema.php`, `titles.php`, `programmatic.php`,
  `filtered-views.php`, `sitemap.php`, `llms.php`, `canonical.php` hub rules.
- The Kits admin shell, the Kits Core dependency, the InsightWeb bridge, the `aw/v1` worker routes, the Keys page, the
  `aw_worker` capability.
- Content analysis and focus-keyword rules tuned to the site's post types (keep only if generalised).

Rule of thumb: a generic feature **about Rank Math's own data** ports; anything **about Animal Writes content** is
dropped. The public plugin operates only on the public post types the admin chooses, never on built-in site types.

## 3. Decisions (locked by the owner, 17 Nov 2026)

- **Name: RankWeb.** Kept as-is for the public plugin (owner's decision; not to be re-raised). Slug `rankweb`, text
  domain `rankweb`. Verify `https://wordpress.org/plugins/rankweb/` is free before submitting; the slug is permanent.
- **One prefix: `rankweb_`** (functions/options/hooks/meta), `RankWeb_` classes, `RANKWEB_` constants. Replaces the
  mixed `aw_` / `rankweb_` / `kits_` — `aw_` especially must be gone everywhere.
- **Shared-core: a package.** The nine pure classes live in one small Composer package that both RankWeb (site) and the
  public plugin require. No fork, no drift.
- **v1 feature set: full.** Port all seven generic areas (internal links, video, schema, bulk, media, redirects,
  agent), since we already have a functional full-featured plugin. Not a focused first release.

The detail below is kept for reference.

## 3a. Decision detail (reference)

1. **Name and slug.** Must not start with a generic word, and a third-party trademark may appear only at the end after
   "for"/"with". "RankWeb" is too close to "Rank Math" — do not reuse it. Options (owner picks):
   - A brand name, e.g. slug `linkjuicer-seo`, `silo-seo`, `schema-extras` (whatever the feature emphasis is).
   - Descriptive with the trademark at the end: display "SEO Extras for Rank Math", slug `seo-extras-for-rank-math`
     (allowed, but the directory sometimes still questions "for X" names — a standalone brand is safer).
   The slug is permanent; check `https://wordpress.org/plugins/<slug>/` returns 404 first.
2. **One prefix, 4+ characters.** RankWeb mixes `aw_`, `rankweb_`, `kits_`. The public plugin needs a single prefix on
   every function, class, constant, option, hook, meta key, REST namespace and text domain — e.g. `sfx_` is too short;
   use something like `seoextras_` / the brand (≥4 chars). Decide it with the name.
3. **Shared core vs fork.** The pure classes are identical in both plugins. Either:
   - **Fork** (simplest): copy the files, re-prefix, maintain twice. Drifts over time.
   - **Shared package** (cleaner): extract the pure classes into a small Composer package both plugins require; RankWeb
     and the public plugin each wrap them. More tooling, no drift. *Recommended* given there are nine tested classes.
4. **Feature set for v1.** All of §2 "ports", or a smaller first release (e.g. internal links only — a focused
   "internal linking for Rank Math" plugin is an easier review and a clearer directory story than a grab-bag).
5. **Distribution of RankWeb itself.** Unchanged: RankWeb stays the self-hosted Animal Writes plugin. The public plugin
   does not replace it.

## 4. Target architecture (generic plugin)

- New folder/slug/main-file all identical (`<slug>/<slug>.php`), text domain = slug, one prefix.
- `Requires Plugins: seo-by-rank-math` (the whole premise: a free Rank Math add-on). No Kits Core, no InsightWeb.
- Standalone admin: its own top-level menu and Settings screen built on the Settings API + `WP_List_Table` (RankWeb's
  Kits-shell React UI does not come along). The module on/off framework ports; the tabs are rebuilt for the generic set.
- Operates on admin-chosen public post types only (a setting), never on hardcoded types.
- Reuses the nine pure classes (shared package or fork) and the test suite verbatim.
- Same security posture already verified in RankWeb (nonces, caps, prepared SQL, escaping, `wp_safe_remote_*`).

## 5. WordPress.org compliance checklist (from the wp-org-submission skill)

- [ ] Slug free and permanent; name rules met; main file = slug.
- [ ] One 4+ char prefix everywhere; no `aw_`/`rankweb_`/`kits_`.
- [ ] `readme.txt` passes the validator: Stable tag, Tested up to a real release, short description ≤150, Installation,
      FAQ, Screenshots, Changelog, and **External services** (YouTube/Vimeo oEmbed, the Rich Results Test link,
      import-from-URL) with Terms + Privacy URLs. (RankWeb's readme already drafts these — port and re-brand.)
- [ ] Remove the `Update URI` header (that header is for self-hosted plugins; a .org plugin must not set it).
- [ ] GPL-compatible; source in the zip; no CDN/remote asset loading; no minified-only code.
- [ ] No trialware, no persistent admin nags, no undisclosed tracking. (RankWeb has none — the agent "writes" switch and
      the .htaccess switch are feature toggles, not Pro gates.)
- [ ] Assets in SVN `/assets/`: `icon-128x128.png`, `icon-256x256.png`, `icon.svg`, `banner-772x250.png`,
      `banner-1544x500.png`, `screenshot-1.png`… (RankWeb has icon + banner already; screenshots are new work).
- [ ] WordPress.org Contributors username (owner to provide).
- [ ] Plugin Check clean on a clean WP install; PHPCS (security + WordPress-Extra) clean.

## 6. Phased plan (each phase stops and reports)

- **R0 — decisions (owner).** Name/slug, prefix, shared-core vs fork, v1 feature set. Nothing builds until these are set.
- **R1 — skeleton.** New plugin folder, header (no Update URI, `Requires Plugins`), one prefix, text domain, the module
  framework and a standalone Settings screen with an empty generic tab set. Activates on a clean WP next to Rank Math.
- **R2 — port the pure core.** Bring in the nine classes (shared package or re-prefixed copy) and their tests; green.
- **R3 — port the generic modules.** Internal links (or the chosen v1 set), then video, schema, bulk, media, redirects,
  agent — each wired to the generic post-type setting, each phase tested and PHPCS-clean.
- **R4 — readme, assets, External services, screenshots.** Draft from RankWeb's, re-branded; generate screenshots.
- **R5 — compliance pass.** Run `wp-org-submission` + Plugin Check + PHPCS; fix. Build the clean zip (`.distignore`).
- **R6 — submit.** Slug check, submit at the add form, answer the reviewer; on approval, set up SVN deploy.

**Effort:** R1–R3 are mostly mechanical given the generic code already exists and is tested — on the order of a few
focused sessions. R4–R6 are gated by human review latency (days to weeks) and by the owner providing the wporg account
and screenshots. The real cost is the one-time **re-prefix + standalone Settings UI**, not the features.

## 7. Risks and non-goals

- **Review latency / slug burn:** a rejected slug is lost for three months; get the name and readme right before
  submitting.
- **"for Rank Math" naming** can still draw questions; a standalone brand is safer.
- **Shared-core drift** if we fork instead of packaging.
- **Non-goal:** this does not change RankWeb. Do not strip RankWeb's site modules; the public plugin is a new codebase.
- **Non-goal:** no Google-data features (those are InsightWeb) and no paid-API features in the free public plugin.

## 8. Recommendation

The owner chose the **full feature set** for v1 (17 Nov) — all seven areas, as a **shared-core** package — since the
plugin is already functional and complete. A focused internal-linking-only first release was the alternative, not taken.
Sequence the build so a reviewer can still follow it: port the shared core first, then one module area per phase (R3),
each tested and PHPCS-clean, even though all ship in v1.
