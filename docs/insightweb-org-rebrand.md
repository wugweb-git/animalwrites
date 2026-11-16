# InsightWeb → a public WordPress.org plugin: rebrand and generalise (scope, 17 Nov 2026)

Scope and design only. Nothing here is built. It plans extracting a **generic, reusable, WordPress.org-listable**
"search analytics" plugin from InsightWeb (`plugins/insightweb`). InsightWeb itself stays as the Animal Writes site
plugin, self-hosted; the public plugin is a **separate codebase** that reuses InsightWeb's generic parts. Companion
assessment: `docs/insightweb-org-assessment.md`. Written in the shape of `docs/rankweb-org-rebrand.md`.

## 1. Go / no-go

**Go — conditionally.** Of the suite, InsightWeb has the clearest generic story: "Search Console + GA4 insights for
WordPress" is a well-understood, popular category. The analysis engine (keyword records with roles and a suggested
action, rising terms, quick wins, Discover/News/URL-Inspection, PageSpeed, Site Health, WP-CLI) is genuinely generic and
already works. The coupling (Kits Core, Rank Math requirement, Animal Writes post types, the Kits admin shell, mixed
prefixes) is all **removable** and none of it is a rewrite.

**The one condition is the Google-credentials model (§5).** InsightWeb stores no Google OAuth of its own; it reads
Search Console and GA4 by calling into the **Site Kit by Google** plugin's *internals*. That is not a supported API and
would be fragile and, arguably, outside directory expectations for a listed plugin. So the project is a **go only once
the credentials model is chosen**; until then it is a no-go on that one axis, not on the features.

## 2. The split: what ports, what is dropped

**Ports as-is (generic search-analytics features):**

| Area | Coupling |
|---|---|
| Keyword records: roles, owning pages, GSC figures by day, priority, suggested action | none (operates on admin-chosen post types) |
| Rising terms, quick wins, conflicts | none |
| PageSpeed Insights (own/optional key) | none |
| Microsoft Clarity, Bing Webmaster (own keys) | none |
| URL Inspection, Discover, Google News coverage | via Site Kit (see §5) |
| Links view (who links to us) + Bing monthly | none (drop the Animal Writes seed data) |
| Site Health test, WordPress Dashboard box, `wp` CLI | light (rename the command namespace) |
| The settings model (seven numbers, store-only-if-differs-from-default) | none — already correct after the 17 Nov fix |
| Admin UI — the Kits shell, kept identical | reused as a bundled shared GPL package (no runtime Kits Core); see §4 |
| The no-WordPress PHPUnit-style runner (`tests/run.php`) | none |

**Dropped (Animal Writes / Kits specific — not in the public plugin):**

- The site post types `aw_listing` / `aw_news` / `aw_legal` / `aw_campaign` / `aw_opportunity` and `AW_SEO_ITEM_TYPES`;
  the public plugin operates only on **admin-chosen public post types** (a setting), never hardcoded ones.
- Hardcoded `animalwrites.in` (`links-seed.php`, `crawlcheck.php` UA + alert email, `server.php` repo link) and the
  shipped seed data (`search-insights/data/links-2026-11-02.php`).
- The **Kits Core runtime dependency**, the **RankWeb bridge** and the shared `aw_seo_settings` option, the `aw/v1`
  worker routes, the `aw_worker` capability, and the Keys page (replaced by the plugin's own settings fields). The
  admin **UI is kept, not dropped** — the Kits shell is reused as a bundled package rather than rebuilt (see §4).
- The **hard Rank Math requirement** — make Rank Math an *optional* enhancement (it supplies SEO scores and a GSC
  backup), not a gate. The generic plugin should run with just WordPress (+ Site Kit for Google data).
- Visitors/country via the DB-IP download and the server-log tab if they prove too site-specific (reassess in R3).

Rule of thumb: a feature **about search/analytics data** ports; anything **about Animal Writes content or the Kits
shell** is dropped.

## 3. Decisions to lock (owner)

- **Name & slug.** Proposed: keep **InsightWeb**, slug **`insightweb`** (verified free on wordpress.org, 17 Nov; the
  slug is permanent — re-check immediately before submitting). "InsightWeb" is a standalone brand with no third-party
  trademark in it, so it is safer than any "… for Site Kit" / "… for Google" descriptive name (the directory questions
  "for X" names, and a Google mark in the title is risky). Alternative if the owner wants a more descriptive brand:
  a coined name emphasising the keyword-insight angle — owner's call.
- **One prefix, 4+ chars: `insightweb_`** (functions/options/hooks/meta/REST namespace/text domain), `InsightWeb_`
  classes, `INSIGHTWEB_` constants. This replaces the mixed `aw_si_` / `aw_` / `aw_kw_` / `aw_seo_` / `kits_`.
  **Owner's standing rule for this work: no `aw`-based prefix anywhere newly written** — `insightweb_` (the slug) or a
  shorter plugin-specific prefix only.
- **Shared core vs fork.** The generic engine (keyword model, rising, compute, the source adapters, the settings model,
  the test runner) could live in a small Composer package shared by InsightWeb (site) and the public plugin, as decided
  for RankWeb — *recommended* to avoid drift. Fork is simpler but drifts. A **second shared package holds the admin
  shell UI** (the bundled `insightweb-shell`, see §4), extracted by RankWeb's build session as the template.
- **Credentials model (§5).** The gating decision; lock before R2.
- **Distribution of InsightWeb itself.** Unchanged: stays the self-hosted Animal Writes plugin, keeps its `Update URI`
  and in-code dependency checks. The public plugin does not replace it.

## 4. Target architecture (generic plugin)

- New folder/slug/main-file identical (`insightweb/insightweb.php`), text domain = slug, one prefix.
- Header: **no `Update URI`** (that header is for self-hosted plugins); `Requires Plugins: google-site-kit` if it keeps
  the Site Kit dependency (§5A), and `seo-by-rank-math` only if Rank Math stays required (preferably it does not).
  `@wordpress-plugin` + `@package/@author/@copyright/@license` + `License URI` (the live header already has these).
- Admin UI: **reuse the current Kits shell as-is, kept identical** — same code, same look, different handle. It is
  extracted into a **shared GPL UI package bundled into the plugin** (no runtime dependency on Kits Core) and enqueued
  under a namespaced handle **`insightweb-shell`** (never the global `kits-shell`). The Kits-specific bits — branding,
  the `kits/v1` routes, cross-plugin navigation — are **injected as config via `wp_localize_script`**, not hardcoded,
  so the same shell serves a standalone install. This is a **dependency on the shared shell UI package** that RankWeb's
  build session is extracting as the template (detail: `docs/rankweb-org-rebrand.md` §4). The generic tab set is
  configured through it; the tabs themselves port. (Do not propose a rebuilt or native Settings-API UI.)
- Operates on admin-chosen public post types only.
- Reuse the generic engine (shared package or re-prefixed copy) and `tests/run.php` verbatim.
- Same security posture already in InsightWeb (nonces, caps, prepared `%i` SQL, escaping, real `permission_callback`s).

## 5. The credentials model (the gating decision)

InsightWeb reads Search Console and GA4 through Site Kit's internals (`\Google\Site_Kit\Context`, `Core\Modules\Modules`,
module `get_data()`, `googlesitekit_*_settings`). That is **not a public API**. For a public plugin, choose one:

- **(A) Companion to Site Kit.** `Requires Plugins: google-site-kit`; the user connects Google inside Site Kit; the
  plugin reads that data. *Pros:* no credential handling, smallest build, honest "insights on top of Site Kit" story.
  *Cons:* needs a **supported** way in — reading Site Kit internals as today is a maintenance and listing risk; if Site
  Kit exposes no suitable public surface, this option is limited to what Site Kit's own hooks/REST allow.
- **(B) Own Google OAuth** (the Netpeak Analytics Kit model). The user supplies their own Google Cloud OAuth
  credentials, tokens stored encrypted; GSC/GA4 read directly from Google's APIs. *Pros:* independent, no Site Kit
  dependency. *Cons:* a real OAuth layer to build and support, a Google Cloud setup step for the user, and — if a
  vendor-run OAuth proxy is offered to avoid that step — an external service to disclose, operate and keep GPL-clean.

Recommendation: start with **(A)** scoped to whatever Site Kit supports publicly (smallest, safest first release), and
treat PageSpeed/Clarity/Bing (which already use the plugin's own keys, no OAuth) as the independent value that works
with or without Site Kit. Move to **(B)** only if the Site Kit surface proves insufficient and there is appetite to run
an OAuth layer.

## 6. WordPress.org compliance checklist (from wp-org-submission)

- [ ] Slug free and permanent (`insightweb` free 17 Nov); name rules met; main file = slug.
- [ ] One 4+ char prefix everywhere (`insightweb_`); no `aw_`/`aw_si_`/`kits_`.
- [ ] `readme.txt` passes the validator: Stable tag, Tested up to a real release, short description ≤150 (done),
      Installation, FAQ, Screenshots, Changelog, and **== External services ==** (done in place: Site Kit/Google,
      PageSpeed, Clarity, Bing, DB-IP, crawl checks, with Terms + Privacy URLs).
- [ ] Remove the `Update URI` header (public plugin only; keep it on the live plugin).
- [ ] `Requires Plugins` for any hard dependency the public plugin keeps (public plugin only — **not** the live plugin;
      the live plugin checks deps in code by the owner's 11 Nov decision and adding the header risks blocking activation
      because Kits Core is not a standalone active plugin on live).
- [ ] GPL-compatible; source in the zip; no CDN/remote asset loading; no minified-only code.
- [ ] No trialware, no persistent admin nags, no undisclosed tracking (InsightWeb has none).
- [ ] Assets in SVN `/assets/`: `icon-128x128.png`, `icon-256x256.png`, `icon.svg`, `banner-772x250.png`,
      `banner-1544x500.png`, `screenshot-1.png`… (icon + banner exist; screenshots are new work).
- [ ] WordPress.org Contributors username (owner to provide).
- [ ] Plugin Check clean on a clean WP install; PHPCS (security + WordPress-Extra) clean.

## 6a. Listing copy (draft, for R4/R5)

Lead the directory listing with the differentiator (the keyword-record model), not the source list. Facts preserved:
it **reads** Google data through Site Kit and never changes SEO settings/keyphrases/content.

- **Short description (≤150):** "Per-keyword insights from Search Console and GA4: roles, owning pages, priority and a next action, plus rising terms and quick wins."
- **Description opening:** "InsightWeb reads your Search Console and GA4 data (through Site Kit) and turns it into one record per keyword — its roles, the pages that own it, a priority and a suggested next action. Rising terms, quick wins and conflicts sit alongside. It only reads and suggests; it never changes your SEO settings, keyphrases or content."
- **Tags (≤5):** `search console`, `analytics`, `google analytics`, `pagespeed`, `seo`. **Category:** SEO / analytics.
- **Assets (deferred to R5):** `icon-128/256` + `icon.svg` + banner exist; **screenshots are new work** (need a live install with Site Kit connected — cannot be produced without local/staging WordPress). Spec: Overview, a keyword record with its suggested action, Rising/quick-wins, the Site Health test. Banner copy can reuse the differentiator line above.

## 7. Phased plan (each phase stops and reports)

- **I0 — decisions (owner).** Name/slug, prefix, shared-core vs fork, **credentials model (§5)**, Rank Math
  optional-vs-required. Nothing builds until these are set.
- **I1 — skeleton.** New plugin folder, header (no `Update URI`, `Requires Plugins` per §5), one prefix, text domain,
  the tab framework and a standalone Settings screen (Settings API + `WP_List_Table`) with an empty generic tab set.
  Activates on a clean WP. Carries the settings model and `tests/run.php` green.
- **I2 — Google data.** Implement the chosen credentials model (A or B). Search Console + GA4 read working on a clean
  install; graceful "connect Google" state when not configured.
- **I3 — the engine.** Port keyword records, rising/quick-wins/conflicts, compute, Pages — each wired to the generic
  post-type setting, each PHPCS-clean and tested.
- **I4 — independent sources.** PageSpeed, Clarity, Bing, Links — own keys, no OAuth; reassess visitors/server tabs.
- **I5 — readme, assets, screenshots.** Draft from InsightWeb's (External services already written), re-branded;
  generate screenshots.
- **I6 — compliance + submit.** `wp-org-submission` + Plugin Check + PHPCS; clean zip (`.distignore`); slug check;
  submit; answer the reviewer; on approval set up SVN deploy.

**Effort.** I1, I3, I4 are mostly mechanical (the generic code exists and is tested). **I2 is the real cost** and its
size depends entirely on the §5 choice. I5–I6 are gated by human review latency (days to weeks) and by the owner
providing the wporg account and screenshots.

## 8. Risks and non-goals

- **Site Kit internals (A)** can break on a Site Kit release; **own OAuth (B)** is a support and compliance burden.
  This single axis dominates the project — decide it first.
- **Review latency / slug burn:** a rejected slug is lost for months; get name + readme right before submitting.
- **Shared-core drift** if forked instead of packaged.
- **Non-goal:** this does not change InsightWeb; do not strip its site modules, its `Update URI`, or its in-code
  dependency checks. The public plugin is a new codebase.
- **Non-goal:** no paid-API features in the free public plugin; PageSpeed/Clarity/Bing use the user's own keys.
