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
| `aw_seo_keep_listings` | option (listings kept indexed with no contact) | `includes/robots.php:76` (read only; no screen today) | **S** Indexing group shows it read-only (count + list); editing stays where it is set today (WP-CLI / Directory) unless the owner asks |
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
