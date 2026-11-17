# Renaming the LIVE `aw-sourcing` plugin → SourceWeb — inventory, collision check, migration plan

**Status: REPORT ONLY. Parts 1–3 below. Nothing renamed, nothing migrated, branch only.**
Part 4 (the actual rename) is NOT in this change and must wait for (a) the `live-awsourcing`
feature work to land and (b) the owner's sign-off. Date: 17 Nov 2026. Branch `claude/zen-brattain-c7d9c8`.

Target: `plugins/aw-sourcing` on animalwrites.in. The `.org` standalone `plugins/sourceweb` is
**not in this repo/branch at all** (see Part 2) and is not touched.

---

## TL;DR — the one decision for the owner

The live plugin is **already half-renamed**. Its newer subsystems (caps, dashboard, model, REST,
UI) are on `sourceweb_*` / `sourceweb-*` and `SOURCEWEB_*` today; its older subsystems
(enrichment, AI keys, keywords, auto-review, ingest, workers) are still on `aw_*` and
`aw_sourcing_*`. Finishing the folder, header, text domain and the `aw_sourcing_*` family is
clean and low-risk. **But** the remaining `aw_*` generic namespace is not sourcing-private — ~30
functions and 6 DB keys in it are **live cross-plugin contracts** read by Core, DirectoryWeb,
InsightWeb, Kits Core and SEO-Extras. Renaming those is not a folder rename; it is a multi-plugin
API change.

**Recommended scope (safe, "zero behaviour change", this-plugin-only):** rename only what this
plugin exclusively owns — the folder, header, text domain, `AW_SOURCING_*` / `AW_SRC_*`
constants, the `aw_sourcing_*` identifier family, and the sourcing-private `aw_*` helpers that no
other plugin references. **Keep every cross-plugin contract as-is** (the `aw_si_*` keyword
contracts, the shared `aw_*` functions and DB keys listed in Part 1-E, the `aw/v1` worker REST
namespace). This is exactly the owned/shared split the plugin's own `uninstall.php` already draws.

A **full** `aw_* → sourceweb_*` sweep is possible but would edit 5 other plugins in lockstep and
migrate shared DB keys — a separate, coordinated piece of work, not this rename. Flagged, not assumed.

---

## PART 1 — INVENTORY (what the live plugin owns)

Scope scanned: `plugins/aw-sourcing` (69 PHP files). Counts are distinct identifiers.

### 1-A. Folder / header / text domain
| Thing | Current | Proposed |
|---|---|---|
| Folder | `plugins/aw-sourcing` | `plugins/sourceweb` |
| Main file | `aw-sourcing.php` | `sourceweb.php` |
| Header `Plugin Name` | `SourceWeb` | *(already correct)* |
| Header `Text Domain` + `Update URI` | `aw-sourcing` / `.../plugins/aw-sourcing/` | `sourceweb` / `.../plugins/sourceweb/` |
| Text-domain string in calls | `'aw-sourcing'` ×286 | `'sourceweb'` |
| POT file | `languages/aw-sourcing.pot` | `languages/sourceweb.pot` |
| Load guard constant | `AW_SOURCING_LOADED` (`aw-sourcing.php:42`) | `SOURCEWEB_LOADED` |
| Dir constant | `AW_SOURCING_DIR` (`aw-sourcing.php:43`) | `SOURCEWEB_DIR` |

> Note: Core includes this plugin by folder path. The include in `animal-writes-core` that points
> at `plugins/aw-sourcing/aw-sourcing.php` must move in the same commit as the folder/file rename
> (grep before Part 4: `grep -rn "aw-sourcing" plugins/animal-writes-core plugins/kits-core`).

### 1-B. Constants (plugin-defined)
| Current | File:line | Proposed | Notes |
|---|---|---|---|
| `AW_SOURCING_LOADED`, `AW_SOURCING_DIR` | aw-sourcing.php:42–43 | `SOURCEWEB_*` | |
| `AW_SRC_CAP` | sourcing-basics.php:16 | `SOURCEWEB_CAP_SRC` (or fold into `SOURCEWEB_CAP`) | value `manage_options`, code-only |
| `AW_SRC_BACKUP_BATCH` | uninstall-backup.php:30 | `SOURCEWEB_BACKUP_BATCH` | code-only |
| `AW_SRC_MENU` = `aw-sourcing-menu` | menu-model.php:19 | `SOURCEWEB_MENU` = `sourceweb-menu` | **menu slug — see 1-F / Part 3** |
| `AW_SRC_PAGE` = `aw-sourcing` | menu-model.php:22 | `SOURCEWEB_PAGE` = `sourceweb` | **menu slug** |
| `AW_SRC_SCREEN` = `sourcing_page_aw-sourcing` | menu-model.php:28 | `sourceweb_page_sourceweb` | **screen id — see Part 3** |
| `AW_SRC_REPORT_TABS`, `AW_SRC_SETTINGS_TAB` | menu-model.php:31,34 | `SOURCEWEB_*` | code-only (tab keys) |
| `AW_SRC_ICONS` = `aw_source_icons` | source-icons.php:21 | const renames; **value is a DB option key** | keep value or migrate (see 3) |
| `AW_SRC_RELAY_MAP`, `AW_SOURCING_LOG_CAP` | sources-model.php:23,29 | `SOURCEWEB_*` | code-only |
| `AW_SOURCING_SITE_META` = `aw_source_site_meta` | source-sites.php:28 | const renames; **value = post-meta key** | see 3 |
| `AW_SOURCING_SITE_META_CRON` = `aw_sourcing_site_meta` | source-sites.php:31 | const renames; **value = cron hook** | see 1-G / 3 |
| `AW_SOURCING_DISMISSED` = `aw_sourcing_dismissed` | keyword-suggestions.php:22 | const renames; **value = DB option** | see 3 |
| `AW_SOURCING_V1_ARCHIVE` = `aw_sourcing_v1_archive` | sourcing-migrate-retire.php:43 | const renames; **value = DB option** | see 3 |
| `AW_SOURCING_EXTRA_SEARCHES`, `AW_SOURCING_P2_COLUMNS`, `AW_SOURCING_BACKUP_OPTIONS*` | keyword-stats.php:22, migrate*.php | `SOURCEWEB_*` | code-only (migration tooling) |
| **Already renamed:** `SOURCEWEB_CAP`, `SOURCEWEB_CAP_VERSION`, `SOURCEWEB_REST`, `SOURCEWEB_REVIEW_PER_PAGE`, `SOURCEWEB_ENRICH_*`, `SOURCEWEB_DASH*`, `SOURCEWEB_UI_TABS`, `SOURCEWEB_WORKER_STATUS` | | — | keep |

### 1-C. CPT / taxonomies
**None owned.** `plugins/aw-sourcing` registers **no** `register_post_type` / `register_taxonomy`.
The content types (News, Library, Directory listings) and taxonomies live in Animal Writes Core
(plugin header says so explicitly). **No CPT-slug or taxonomy migration is needed** — this removes
the single largest risk the task brief anticipated.

### 1-D. Classes
| Current | File | Proposed | Notes |
|---|---|---|---|
| `AW_Sourcing_SI_Keywords_Table` | class-aw-sourcing-si-keywords-table.php:14 | `SourceWeb_SI_Keywords_Table` (+ file `class-sourceweb-si-keywords-table.php`) | WP_List_Table subclass; **renders the shared `aw_si_keywords` table but the class name itself is plugin-private** — safe to rename. Keep the `aw_si_*` data it reads. |

### 1-E. Functions — the split that matters
- **Total `aw_*` functions defined here: 466.** Families: `aw_enrich_*` (~130), `aw_ai_*` (~40),
  `aw_kw_*` (~25), `aw_ar_*` (auto-review, ~30), `aw_ingest*`, `aw_workers*`, `aw_enrich_place_*`,
  plus the `aw_sourcing_*` admin/page helpers.
- **Sourcing-private (safe to rename to `sourceweb_*`):** the large majority — any `aw_*`
  function not in the list below.
- **CROSS-PLUGIN CONTRACTS — defined here, CALLED by other plugins. DO NOT rename without
  updating every caller in the same change (recommend: KEEP as-is):**

| Function | Called from |
|---|---|
| `aw_host` | kits-core (attribution, sourcing), AW-core (hub-blocks, also-reported, systems) |
| `aw_publishers` | kits-core (uninstall, attribution, sourcing), AW-core (uninstall, hub-blocks, systems) |
| `aw_relevance`, `aw_relevance_store` | kits-core, insightweb (keywords, dryrun), AW-core |
| `aw_review_count`, `aw_review_count_flush` | AW-core (admin-home, admin-ia) |
| `aw_worker` (workers-meta.php:133) | AW-core (social-posts), insightweb (settings), aw-seo-extras (helpers) |
| `aw_workers_problems`, `aw_workers_watchdog` | AW-core (backups, cron) |
| `aw_ai_check_all`, `aw_ingest_watchdog` | AW-core (cron) |
| `aw_find_by_source`, `aw_find_by_title` | AW-core (press) |
| `aw_summary_description`, `aw_summary_section` | aw-seo-extras (titles, `function_exists`-guarded) |
| `aw_enrich_add_row/after_change/install/snapshot/undo` | **directoryweb** (claims-review, tests) |
| `aw_enrich_icon` | AW-core (data-sources) |
| `aw_enrich_last_applied/profile_extras/progress/provenance/review_page/table` | **directoryweb** (listing-template, listing-profile, dashboard, gov-verified, rest) |
| `aw_sourcing_kw_lists`, `aw_sourcing_kw_on`, `aw_sourcing_phase` | insightweb (dryrun, keywords) |
| `aw_t` | appears in kits-core/forms, AW-core/forms-fluentforms — verify (likely a shared test/helper) before any rename |

  > The `aw_enrich_*` surface is effectively a **shared enrichment API with DirectoryWeb**, not a
  > sourcing-internal one. Treat the whole `aw_enrich_*` family + the `wp_aw_enrich` table as a
  > contract.

### 1-F. Admin menu slugs / pages (`includes/admin-menu.php`, review-queue.php, enrichment-admin.php)
| Item | Current | Proposed | Impact |
|---|---|---|---|
| Top menu slug | `aw-sourcing-menu` (`AW_SRC_MENU`) | `sourceweb-menu` | bookmarks to `admin.php?page=aw-sourcing-menu` break |
| Overview page slug | `aw-sourcing` (`AW_SRC_PAGE`) | `sourceweb` | bookmark breakage |
| Submenu `aw-review` + cb `aw_review_page` | — | `sourceweb-review` | bookmark breakage |
| Submenu `aw-enrich-review` + cb `aw_enrich_review_page` | — | keep? (enrich = shared w/ directoryweb) | **decide with 1-E** |
| Overview callback `aw_sourcing_overview_page`, `aw_sourcing_page` | — | `sourceweb_*` | code-only |
| Tab-slug helper `aw_src_tab_menu_slug($tab)` | — | `sourceweb_tab_menu_slug` | code-only |

### 1-G. AJAX / admin_post actions
`admin_post_aw_ai_*`, `admin_post_aw_block_publisher`, `admin_post_aw_publish_all`,
`admin_post_aw_rules_*`, `admin_post_aw_sourcing*`, `admin_post_aw_sourcing_keywords`,
`admin_post_aw_sourcing_kw`, `admin_post_aw_worker`, `admin_post_sourceweb_reset_section` (already
renamed). → rename action strings to `sourceweb_*`. **Code-only** (each is self-contained: the
hook registration and the `<form action>`/`admin_post.php?action=` are both in this plugin). Low
risk; no persisted state.

### 1-H. Cron hooks (`wp_schedule_event` / `wp_next_scheduled`)
| Hook | File | Owned? | Proposed |
|---|---|---|---|
| `aw_sourcing_site_meta` | source-sites.php:187 | private | `sourceweb_site_meta` **(scheduled in DB — see 3)** |
| `aw_sources_sync_dispatch` | sourcing-lists.php:122 | private | `sourceweb_sources_sync_dispatch` **(scheduled — 3)** |
| `aw_ai_check_all` / `_now` / `aw_ai_check_new` | ai-keys.php | private, but `aw_ai_check_all` also scheduled by AW-core cron.php → **verify caller** | rename both sides or keep |
| `aw_ingest_watchdog` | relevance.php:536 | **also referenced by AW-core/cron.php** | keep or rename both sides |
| `aw_workers_watchdog` | workers.php:232 | **also referenced by AW-core/cron.php** | keep or rename both sides |
| `aw_research_citation` | ingest.php:399 | **Core's event** (uninstall.php:35 says so) | **KEEP** |
| `sourceweb_dashboard_build` (`SOURCEWEB_DASH_JOB`) | sourceweb-dashboard.php | already renamed | keep |

### 1-I. Capabilities
`SOURCEWEB_CAP` = `manage_sourceweb` (sourceweb-caps.php) — **already renamed**. Legacy
`AW_SRC_CAP` = `manage_options` is a fallback constant, not a custom cap. `AW_REVIEW_CAP`,
`AW_ENRICH_CAP` come from Core/other includes (not defined here) — leave.

### 1-J. REST namespaces / routes
| Namespace | Count | Decision |
|---|---|---|
| `sourceweb/v1` (`SOURCEWEB_REST`) | 6 routes (`/rules/autoresolve*`, `/enrichment/*`) | already renamed — keep. **Namespace shared with the `.org` fork — see Part 2-A for co-install constraints** |
| `aw/v1` | 5 routes | **worker endpoints — SHARED, KEEP** (external workers post to these; renaming breaks the fetch pipeline) |

### 1-K. Nonce actions
`aw_sourcing`, `aw_block_*`, `aw_publish_all`, `aw_enrich_identity`, `sourceweb_reset_section_*`
(already renamed). → rename `aw_*` nonce actions to `sourceweb_*`. **Code-only**, per-request, no
persisted state; must change the matching `wp_create_nonce`/`check_admin_referer` pair together.
Note `aw_enrich_identity` pairs with the enrich (directoryweb-shared) surface — keep if enrich is kept.

### 1-L. CSS / JS handles
No `wp_enqueue_script/style('aw-sourcing…')` handles found in PHP. The block build
(`build/index.js`, `src/`) registers via `block.json` — grep `src/` + `build/` and `assets/`
before Part 4; none surfaced as `aw_sourcing`/`aw-sourcing` handles in the PHP scan.

---

## PART 2 — COLLISION CHECK

Method: `grep -roE "\bsourceweb[_-][a-z0-9_-]+"` across **all plugins + themes except
`plugins/aw-sourcing`**.

**Result: ZERO hits.** No `sourceweb_*` / `sourceweb-*` identifier exists anywhere in the live
codebase outside this plugin. Every proposed `sourceweb_*` name is free — no redeclare risk on
co-active plugins.

- `plugins/sourceweb` (the `.org` fork) is **not present in this repo/branch** — confirmed by
  `ls plugins/` (animal-writes-core, aw-seo-extras, aw-sourcing, directoryweb, insightweb,
  kits-core, mailweb, planweb). So the "both define `sourceweb_*`" scenario cannot occur here, and
  there is no in-repo clash between the live plugin and the fork.
- The only existing `sourceweb_*` names are the partial migration **inside** aw-sourcing itself
  (not a collision — those are the already-done half).

### 2-A. `sourceweb/v1` REST namespace is SHARED with the `.org` fork (cross-repo, co-install)
Flagged by the standalone-SourceWeb session. The namespace `sourceweb/v1` is used by **both**
plugins, partitioned by path, so they coexist even if co-installed:
- **Live `aw-sourcing` registers:** `sourceweb/v1/rules/autoresolve`, `/rules/autoresolve/delete`,
  `/rules/autoresolve/move`, `/rules/autoresolve/reset`, `/enrichment/edit`, `/enrichment/delete`
  (autoresolve.php). Permission callback: **`sourceweb_rest_can`** (defined sourceweb-rest.php:35,
  used across sourceweb-rest-ai/logs/pages).
- **`.org` fork registers:** `sourceweb/v1/stats` (not in this repo). Its permission callback was
  renamed to **`sourceweb_stats_rest_can`** (fork fix 26ba900d) specifically to avoid a
  function-name redeclare with the live side's `sourceweb_rest_can`.

Constraints for Part 4 (no clash in this repo today — the fork isn't here — but to stay
co-install-safe):
1. **Do NOT add a `sourceweb/v1/stats` route on the live side** — the fork owns that path.
2. **Do NOT rename any live function to `sourceweb_stats_rest_can`** — the fork owns that name.
3. Live's existing `sourceweb_rest_can` is fine to keep (the fork already renamed away from it);
   do not rename any *other* live callback *into* `sourceweb_rest_can` either.
4. Leave the `aw/v1` worker routes and the `aw_si_tabs` / `aw_si_page_metrics` / `aw_si_table` /
   `wp_aw_si_keywords` contracts untouched (already in brief).

---

## PART 3 — MIGRATION PLAN (DB-backed names only)

Code identifiers (functions, constants, classes, nonces, AJAX actions, enqueue handles) need no
data migration — they change at deploy. Only names **persisted in the database** need a one-time,
idempotent, reversible migration so existing live data survives the rename. Below is every
DB-backed name, taken from the authoritative owned-data manifest in `uninstall.php` (which also
lists what is **shared and must never be renamed**).

### 3-A. Options to migrate (rename key, copy value, delete old) — ONLY the `aw_sourcing_*` ones
These are owned, `aw_sourcing_`-prefixed, and (except where noted) private:

| Old option | New option | Cross-plugin reader? |
|---|---|---|
| `aw_sourcing_dismissed` | `sourceweb_dismissed` | no |
| `aw_sourcing_migrating` | `sourceweb_migrating` | **yes — insightweb/keywords.php** → update reader too, or keep |
| `aw_sourcing_phase` | `sourceweb_phase` | **yes — insightweb/keywords.php** → update reader too, or keep |
| `aw_sourcing_phase1_record`…`phase4_record` | `sourceweb_phase1_record`… | no |
| `aw_sourcing_seed_hc`, `aw_sourcing_seed_s35` | `sourceweb_seed_hc`, `…_s35` | no |
| `aw_sourcing_v1_archive` | `sourceweb_v1_archive` | no (retired v1 data) |

> `sourceweb_dashboard_log` is already on the new name — nothing to migrate.

### 3-B. Transients to migrate (or just let expire — they are caches)
| Old | New | Note |
|---|---|---|
| `aw_sourcing_host_stats` | `sourceweb_host_stats` | cache; safe to drop, repopulates |
| `aw_sourcing_kw_stats_*` (incl. `aw_sourcing_kw_stats_30`) | `sourceweb_kw_stats_*` | cache; **`aw_sourcing_kw_stats_30` named in brief** — still just a cache, no migration needed, let it rebuild |

**Recommendation:** don't migrate transients at all; change the code to write the new keys and let
the old ones expire. No data loss (they are derived caches).

### 3-C. Cron hooks to migrate (unschedule old hook, schedule new) — private ones only
`aw_sourcing_site_meta` → `sourceweb_site_meta`; `aw_sources_sync_dispatch` →
`sourceweb_sources_sync_dispatch`. On the activation/upgrade routine: if the old hook is
scheduled, `wp_clear_scheduled_hook(old)` and schedule new with the same recurrence. The
`aw_ingest_watchdog` / `aw_workers_watchdog` / `aw_ai_check_all` hooks are **cross-referenced by
AW-core** — keep them unless Core is updated in lockstep. **Keep `aw_research_citation` (Core's).**

### 3-D. Post-meta key
`aw_source_site_meta` (`AW_SOURCING_SITE_META`, source-sites.php) — written as post meta. If
renamed, migrate with a single `UPDATE {prefix}postmeta SET meta_key='sourceweb_source_site_meta'
WHERE meta_key='aw_source_site_meta'`. **Low value, higher risk — recommend KEEP** (it carries no
`sourcing` token a user sees). All other post-meta keys (`aw_source_url`, `aw_relevance`,
`aw_summary_*`, `aw_enrich_*`, etc.) are **shared with Core/DirectoryWeb/theme templates — KEEP.**

### 3-E. Custom table
`{prefix}aw_enrich` — the enrichment proposals table. **Shared with DirectoryWeb** (reads
`aw_enrich_db` + the `aw_enrich_*` API). **Recommend KEEP** the table name and the `aw_enrich_*`
functions; renaming is a DirectoryWeb change, not a SourceWeb rename. The keyword tables
`{prefix}aw_si_keywords`, `{prefix}aw_si_kw_map` and `aw_kw_db` are **explicitly shared (uninstall
lines 33–34) — KEEP.**

### 3-F. DB keys that must **NOT** be renamed (shared reads — confirmed by cross-ref)
`aw_sources` (kits-core, AW-core, directoryweb), `aw_relevance` (kits-core, insightweb),
`aw_publishers` (kits-core, AW-core), `aw_enrich_db` (directoryweb), `aw_ai_models`, `aw_ai_keys`
(owner-only), `aw_social_on`, `aw_social_show` (Core's), `kits_sourcing_log`, and all `aw_si_*`
keyword contracts.

### 3-G. Permalink / query / bookmark impact
- **No CPT slug change** (Part 1-C) ⇒ **no permalink or rewrite impact, no flush needed.**
- **Menu-slug change** (`aw-sourcing-menu` → `sourceweb-menu`, `aw-sourcing` → `sourceweb`) breaks
  existing admin bookmarks (`wp-admin/admin.php?page=aw-sourcing*`) and changes the screen id
  `sourcing_page_aw-sourcing` → `sourceweb_page_sourceweb` (affects per-screen "Screen Options",
  help tabs, and any saved `manage_{screen}_columns`/user-meta hidden-columns). Mitigation:
  optionally register a tiny redirect from the old top-slug to the new one for one release; accept
  that saved column prefs reset (cosmetic).

---

## What this change (this branch) contains
Only this report (`docs/sourceweb-live-rename-plan.md`). **No code renamed.** Awaiting: the
`live-awsourcing` feature work to land, then owner sign-off on **scope** (recommended: owned-only,
keep all cross-plugin contracts) before Part 4 is sequenced by the coordinator.

---

### Skills / connectors
- **Skills used:** none invoked — this was a read-only grep/cross-reference inventory; `wp-plugin-audit`
  (naming/prefix dimension) and `wp-coding-standards` are the relevant skills for Part 4 when it runs.
- **Connectors used:** none (local git worktree only).
- **Missing / blocked:** none needed for an inventory. No local WP (per owner rule); verification was
  code-path + grep across the repo.
