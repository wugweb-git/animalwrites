# InsightWeb → public WordPress.org: assessment (17 Nov 2026)

Read-only assessment of `plugins/insightweb` for the public WordPress.org plugin directory. Consolidates what is
known about InsightWeb from `docs/pending.md`, the `docs/*.md` plans (chiefly `docs/insightweb-plan.md`,
`docs/seo/search-insights-plan.md`) and the Notion "InsightWeb" product page into one place. No local WordPress is
available (owner rule), so anything that can only be confirmed at runtime is marked **(runtime)**. The rebrand/extraction
design is a separate doc: `docs/insightweb-org-rebrand.md`.

## Verdict

**No-go as it stands. Extractable into a listable plugin, but with real cost at one layer: Google credentials.**

InsightWeb is a Rank Math / Kits add-on, not a standalone plugin: it is hard-gated on **Kits Core** and **Rank Math**,
leans on the **Kits Core admin shell** for its whole UI, carries **Animal Writes** post types and a hardcoded site, and
reads **Search Console / GA4** through the internals of the **Site Kit by Google** plugin (not a public API). None of
that blocks a *separate* generic plugin — "Search Console + GA4 insights" is the clearest generic story in the suite —
but the one question that decides whether it is worth doing is how a public install would authenticate to Google
(see §6 and the rebrand doc). The in-place hygiene a live self-hosted plugin can safely take has been done (readme
External services, brand-neutral short description); the listing-blocking changes (one prefix, drop the site coupling,
`Requires Plugins`, remove `Update URI`) belong to the separate public codebase, not to the live plugin.

## 1. What InsightWeb is

Extracted from RankWeb on 11 Nov 2026 (`docs/insightweb-plan.md`), unchanged in behaviour, keeping every `aw_si_*`
name. It **measures** how the site does in search and **suggests**; it never edits SEO settings, keyphrases or content
(that is RankWeb's). ~18.8k lines of PHP plus one small React entry (`src/index.js` → `build/index.js`; source is
present).

- **Keyword records** — one row per keyword with roles, owning pages, Search Console figures by day, priority and a
  suggested action.
- **Sources** — Search Console (Discover, Google News, URL Inspection) and GA4 **through Site Kit**; PageSpeed,
  Microsoft Clarity, Bing Webmaster and Google Alerts, each only when its key/connection is set.
- **Views** — Overview, Keywords, Rising (rising terms, quick wins, conflicts), Pages, Clarity, Check list, Links
  (who links to us; Bing monthly), Server (crawler requests, 4xx/5xx, PHP errors; visitors by country), Settings.
- **Surfaces** — a Site Health test, a WordPress Dashboard box, and the `wp aw-si` WP-CLI commands.
- **Scheduled jobs** — the `aw_si_*` cron set (daily, rollup, GA4, PSI, Clarity, Bing, GSC types, inspect, alerts,
  crawl).
- **REST** — namespaces `insightweb/v1` and `aw/v1`.

## 2. Dependency map — CRITICAL vs NON-CRITICAL

**CRITICAL** — hard-gated in `includes/bootstrap.php`; without these InsightWeb shows one admin notice and does nothing:

| Dependency | Guard (file:line) | Effect if absent |
|---|---|---|
| **Kits Core** | `! defined( 'KITS_CORE_LOADED' )` — `bootstrap.php:30`; and `! function_exists( 'aw_kw_install' )` (the keyword table, now Kits Core's) — `bootstrap.php:52` | Bootstrap returns early; admin notice; nothing loads. |
| **Rank Math SEO** | `defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' )` — `bootstrap.php:31` | Same early return + notice. |

**NON-CRITICAL** — guarded with `function_exists` / `class_exists`; the plugin boots and the feature degrades:

| Dependency | Guard | Degradation |
|---|---|---|
| **Site Kit by Google** | `class_exists( '\Google\Site_Kit\Context' )` (`sitekit.php:17`), `\Google\Site_Kit\Plugin` (`sitekit.php:20`, `insightweb-rest.php:533`), `\Google\Site_Kit\Core\Modules\Modules` (`sitekit.php:46`) | Returns `WP_Error` "Site Kit is not active"; Search Console + GA4 unavailable. **Functionally core, technically optional.** |
| **Animal Writes Core** | none inside InsightWeb — it is only the **live loader** (InsightWeb boots itself on `plugins_loaded` priority 5 in `insightweb.php`) | Not a runtime gate; standalone activation works without it. The keyword table it once owned is now Kits Core's. |
| **Kits Core admin shell** | `function_exists( 'kits_shell_html_parts' / 'kits_shell_app' / 'kits_shell_mount' / 'kits_shell_rest_can' )` (`insightweb-tabs.php:46,56`, `insightweb-ui.php:36,75,143`, `insightweb-rest.php:29`), `kits_admin_tabs` (`kits-hub.php:17`), `kits_dash_cards`/`kits_dash_bars` (`dashboard.php:141,175`), `kits_status_cell` (`kits-hub.php:46`) | Empty/fallback output; the screens do not render. (Not a listing blocker: the public plugin reuses this shell as a bundled shared GPL package — handle `insightweb-shell`, Kits specifics injected via `wp_localize_script`, no runtime Kits Core — per `docs/insightweb-org-rebrand.md` §4.) |
| **SourceWeb** | `function_exists( 'aw_sourcing_kw_on' )` | Sourcing-keywords tab hidden. |
| **Keys** (`aw_key()`, Kits Core) + PSI/Clarity/Bing/Alerts keys | `function_exists( 'aw_key' )` (`runs.php:46`, `admin.php:1076`); each source checks its own key (`bing.php:35`, `clarity.php:36`, `psi.php:158`) | That source simply does not run. |

## 3. Security posture (good)

- **REST** — every route has a real `permission_callback`: `kits_shell_rest_can( $cap )` (nonce + capability, Kits Core)
  or `aw_si_api_allowed` (administrators or the `aw_worker` user). No `__return_true`.
- **SQL** — `$wpdb->prepare()` throughout, with `%i` for identifiers/table names (167 uses). No raw interpolation found.
- **CSRF / caps** — 17 `check_admin_referer` + 1 `check_ajax_referer`; 35 `current_user_can`.
- **HTTP** — `wp_remote_get` to the documented hosts; raw `file_get_contents` only on the local readme and uploaded
  import files (PHPCS-ignored, local paths). **(runtime)** the outside crawl checks fetch the site's own URLs.
- PHPCS `WordPress` + `tools/ci/phpcs-security.xml` are clean on the files changed in this session.

## 4. Coupling to Animal Writes / Kits (what a public plugin must shed)

- **Site post types** — `aw_listing`, `aw_news`, `aw_legal`, `aw_campaign`, `aw_opportunity` appear in `keywords.php`,
  `dryrun.php`, `crawlcheck.php:67-69`, `psi.php:42,53`, `gsc-types.php:585`, `cli.php`. Driven off `AW_SEO_ITEM_TYPES`
  (RankWeb's constant) where present.
- **Hardcoded site** — `https://animalwrites.in/` in `links-seed.php:21-22`, the crawl user-agent and alert email body
  in `crawlcheck.php:48,325`; shipped seed data `search-insights/data/links-2026-11-02.php` (real third-party URLs);
  the repo link in `server.php:217`.
- **RankWeb bridge** — RankWeb loads InsightWeb's bootstrap while InsightWeb is inactive; `aw_seo_settings` is shared
  with RankWeb (the seven numbers live under its `search-insights` key).
- **Mixed prefixes** — `aw_si_` (dominant), `AW_SI_`, `insightweb_`, `aw_kw_`, `aw_seo_`, `kits_`, and `aw_`
  option/hook names. A listed plugin needs **one** 4+ char prefix on everything (the rebrand doc proposes
  `insightweb_`; the owner's standing rule for this work is: **no `aw`-based prefix** in anything newly proposed).

## 5. Listing blockers vs the wp.org checklist

| Item | State | Note |
|---|---|---|
| Slug free & permanent | **OK** | `insightweb` returns 404 on wordpress.org/plugins/ (verified 17 Nov) — free. |
| Name rules / trademark | OK | "InsightWeb" is a standalone brand; no third-party mark in the name. |
| Main file = slug | OK | `insightweb.php`. |
| One 4+ char prefix | **Blocker** | mixed `aw_si_`/`aw_`/`kits_`/`insightweb_` — see §4. (Public codebase only; do not mass-rename the live plugin — that is the owner's separate December rename.) |
| `Requires Plugins` for hard deps | **Blocker (public only)** | Not set today, deliberately: owner decided 11 Nov that deps are checked in code because on live Kits Core is loaded by Animal Writes Core, not activated as a standalone plugin. Adding the header to the **live** plugin risks blocking activation, so it is **not** done in place; the public codebase sets `Requires Plugins: google-site-kit, seo-by-rank-math` (whichever it keeps). |
| `Update URI` header | **Blocker (public only)** | Live plugin uses it for self-hosted updates (`plugin-info.php` → `update_plugins_wugweb.com`, `plugins_api`). A wp.org plugin must not set it. Remove in the public codebase; keep on the live plugin. |
| `== External services ==` | **Done (in place)** | Added to `readme.txt` this session (Google via Site Kit, PSI, Clarity, Bing, DB-IP, crawl checks, with terms/privacy URLs). |
| Installation / FAQ / Changelog | OK | Present; short description now brand-neutral and ≤150. |
| GPL, source in zip, no CDN/minified-only | OK | GPL-2.0-or-later; `src/` present; React built locally; no remote asset loading found. |
| Trialware / nags / undisclosed tracking | **None** | No Pro gate; feature switches only. Visitors/country uses a local DB-IP file (disclosed). |
| Assets (icon/banner/screenshots) | Partial | `assets/` has icon + banner; screenshots are new work for submission. |

## 6. The crux — Google credentials for a public install

InsightWeb holds **no Google OAuth client and no tokens of its own**. For Search Console and GA4 it calls into the
**Site Kit by Google** plugin's internals — `\Google\Site_Kit\Context`, `...\Core\Modules\Modules`, the module object's
`get_data()`, and the `googlesitekit_*_settings` options. That is Site Kit's **private surface, not a public/supported
API**, so it can break on any Site Kit release **(runtime to confirm against a given Site Kit version)**. Two viable
models for a public plugin, judged in the rebrand doc:

- **(A) Depend on Site Kit, but stop using its internals.** Cleanest story ("companion to Site Kit"), smallest build,
  zero credential handling — but needs a *supported* way to read Site Kit's data; today's internal calls are a real
  listing and maintenance risk.
- **(B) Ship its own Google OAuth** (as Netpeak Analytics Kit does: the user's own Google Cloud OAuth credentials, or a
  vendor-run OAuth proxy — the latter is an external service to disclose and to operate). More work, more support, full
  independence.

This choice gates the whole project; everything else (prefix, UI, coupling) is mechanical by comparison.

## 7. WordPress.org landscape

- **Closest existing plugins (competitors):**
  - *Site Kit by Google* — the official Search Console + GA4 + PageSpeed + AdSense dashboard; InsightWeb's own
    dependency. A public InsightWeb is a **companion/analysis layer** on top of it, not a replacement.
  - *ExactMetrics* and *MonsterInsights* — GA-centric dashboards (eCommerce/form tracking); large install base.
  - *SiteStats Analytics* — combines GA, Search Console, Bing, WooCommerce in a drag-and-drop dashboard; fetches from
    Google APIs and caches locally.
  - *Netpeak Analytics Kit* — GSC + GA4 in wp-admin using the user's **own OAuth credentials**, tokens stored
    encrypted. The reference model for §6 option (B).
- **Differentiator** — not another metrics dashboard, but the **keyword-record model** (roles, owning pages, priority,
  a suggested action), **rising terms / quick wins / conflicts**, Discover/News/URL-Inspection coverage, and the
  crawler-access checks. That is the angle a listing should lead with.
- **Slug** — `insightweb` is **free** (verified). Permanent once taken; confirm again immediately before submitting.
- **Suggested directory category & tags** — category: SEO / analytics. Tags (≤5): `search console`, `analytics`,
  `google analytics`, `pagespeed`, `seo`.

## 8. Runtime-only items (no local WordPress)

- Compatibility of the Site Kit internal calls against the installed Site Kit version (§6).
- Cron registration, REST routes, DB table creation on activation, and Plugin Check on a clean WP install next to Site
  Kit + Rank Math.
- Live validation of the readme via the official readme validator before submission.

## 9. Status of this session's in-place work

- Fixed a real bug: the Settings save kept a stored value equal to the default, so it would not follow a later default
  change — `aw_si_settings_save()` now clears a stale default-equal key (commit `b3cd3a0d`; test updated `eadd5e5c`;
  `docs/pending.md` `d967d873`).
- readme.txt: `== External services ==` added, short description made brand-neutral (`0d723960`).
- No push, no ship (side session). Nothing above changes RankWeb or any other plugin.
