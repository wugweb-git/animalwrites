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
- Operates on admin-chosen public post types only (a setting), never on hardcoded types.
- Reuses the nine pure classes (shared package or fork) and the test suite verbatim.
- Same security posture already verified in RankWeb (nonces, caps, prepared SQL, escaping, `wp_safe_remote_*`).

### Admin UI: a bundled shared-shell package (decided 17 Nov 2026)

The suite's admin shell lives in Kits Core today: Kits Core registers the `kits-shell` script/style handle and every suite
plugin's admin app lists `kits-shell` as a dependency (externalised `@kits/shell` in each `webpack.config.js`;
`build/index.asset.php` carries the handle). A public plugin cannot depend on that — the handle only exists when Kits
Core is active, and `.org` forbids a shared runtime handed out by a separate, unpublished plugin.

**Solution (the Fluent model — how Fluent Forms / FluentCRM share one framework):** extract the shell into a single
**shared UI package** that is **bundled into each plugin**, never required at runtime. One codebase, several consumers,
each injecting its own config.

**Reuse the current shell as-is and keep it the same (owner, 17 Nov).** Do NOT redesign or rebuild the UI. Lift the
existing Kits shell code unchanged into the package; keep its look, components and behaviour identical, so the suite,
the theme and the public plugins all run the same shell. The only changes are the minimum needed to make it portable:

- Bundle it in each plugin; GPL (source shipped, not minified-only). Nothing is removed from the shell — the
  Kits-specific pieces (branding, the `kits/v1` endpoints, cross-plugin navigation) are turned into **config injected
  via `wp_localize_script`** rather than hardcoded, so the same code renders Kits chrome in the suite and the plugin's
  own chrome on `.org`. No visual or behavioural divergence.
- Each plugin bundles it under its **own namespaced handle** (`rankweb-shell`, `planweb-shell`, …), never a global
  `kits-shell` — otherwise two active suite plugins would collide on one handle and WordPress would keep whichever
  loaded first (version clash). Same code, different handle.
- So two shared packages back the public plugins: **`rankweb-core`** (the nine pure PHP classes) and the **shell UI
  package**. RankWeb's build session extracts the shell package from the Kits shell as the template; the other plugins
  reuse it.

**Sequencing (important):** build the shell package greenfield for the public plugins first and prove it there. Do **not**
refactor the live Kits Core / theme Theme-Options UI onto it in the same step. Migrating Kits Core (and therefore the
theme's Theme Options admin) to consume the same package is a deliberate **later follow-up**, tested on its own, so the
running site's admin is never disturbed mid-flight. End state: one shell package used by both the private suite/theme
(via Kits Core, Kits-branded) and the public plugins (bundled, de-branded).

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
