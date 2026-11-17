# Kits by Wugweb — theme marketplace readiness plan

**Goal:** ship **Kits by Wugweb** (theme) + **Kits Core** (companion plugin) to the **wordpress.org theme directory** *and* a **commercial marketplace** (ThemeForest), without changing how the live Animal Writes site looks or behaves.

**Branch:** `claude/theme-marketplace-prep` (worktree off `wordpress-dev`) → PR into `wordpress-dev` → owner merges. No direct push to `wordpress`.

**Guiding principle:** build to the strict wordpress.org bar (it covers most commercial rules); add a thin commercial build layer on top. Animal Writes is a *tenant*, so anything AW-specific moves to the tenant layer (Animal Writes Core / child `aw.css`), never into the generic theme.

---

## Status confirmed against `origin` (live = dev, 0 commits apart)

Already compliant — **not** in scope:
- No `Update URI`; `Requires PHP: 8.0`; fonts self-hosted (Inter, local variable `fontFace`, no Google/CDN call anywhere); uniform text domain; PHP lint clean; screenshot 1200×900; bundled-font licences in `readme.txt`.

(The earlier "Update URI / PHP 7.4 / Google Fonts" flags came from a **stale local `wordpress` branch**; `origin` is clean.)

---

## Phase 0 — Tooling & baseline (no theme changes)
- Install PHPCS + WPCS + Theme-Review sniffs locally via the `wp-coding-standards` skill (not currently installed here).
- Record a baseline: PHPCS report on the theme, `theme_rules_check.py`, `contrast_check.py`, `php -l`.
- Theme Check itself needs a WP install (we don't stand one up — `no-local-test-setup` rule); we run the equivalent WPCS **Theme Review** ruleset + the make.wordpress.org *Required* checklist by hand, and the owner runs Theme Check on staging as the final gate.

## Phase 1 — Decouple the theme from the Animal Writes tenant *(core refactor; live-parity critical)*
Move every AW-specific rule out of the generic component CSS into the tenant layer:
- `kit.css`: `body.single-aw_research` (l.162), `.type-aw_research`/`.single-aw_research` (l.282), `.aw-opp-actions` (l.1306), the heading `:has()` rule listing `.aw-hero-lead`/`.aw-camp-lede`/`.aw-findhelp-lede`/`.aw-letter-share-*`/`.aw-listing-desc` (l.240–241), `.aw-camp-card-fold`/`.aw-more` (l.605–608).
- Target: `aw.css` in `animal-writes-core/theme/assets/css/` **and** the child copy (keep both identical — `aw-css-two-copies` rule).
- Genericise comments in `kit.css`/`kit-primitives.css` ("Animal Writes promotion", footer label examples).
- **Move, don't delete** — these style live AW pages.
- Verify: render-compare 16 live page types at 375/768/1280 (old vs new CSS) → **0 diffs**; run `css_min.py`; `diff -q` the two `aw.css` copies.

## Phase 2 — Generic-theme hygiene
- `styles/animal-writes.json`: remove the client brand variation from the generic theme; move it to the tenant. Keep `styles/colours/ink.json` and add 1–2 neutral demo variations.
- `kits-registry.json`: genericise the footer default nav labels ("Animal Writes" → placeholder).
- `readme.txt`: refresh Tags/Description/"Tested up to"; add changelog entries.
- i18n: generate the `.pot` (`wp-i18n-workflow`); confirm every string is translatable.
- Confirm no client-specific images ship in the generic theme.

## Phase 3 — Theme Review compliance (wp.org *Required* list)
- Work the make.wordpress.org *Required* checklist (escaping/prefixing/sanitisation, no plugin-territory features in the theme, correct `add_theme_support`, credits/licences, no admin UI in the theme).
- PHPCS: `WordPress` + `WordPress-Docs` + Theme Review standard → fix violations on changed files.
- Accessibility pass (`design:accessibility-review` + existing `contrast_check.py`): contrast, keyboard, landmarks, skip link.

## Phase 4 — Kits Core plugin (it travels with the theme)
- `wp-plugin-audit` on Kits Core (security, prefixing, i18n).
- Confirm it runs **activated standalone**, not only via Animal Writes Core `require_once`.
- wp.org rule: the theme must work with the plugin **off** — document exactly what degrades (forms, share bar, admin shell) so plugin features are optional, not required.
- Plugin Directory guidelines pass (`wp-org-submission`) for Kits Core's own submission.

## Phase 5 — Two distribution builds
- Build script producing:
  - **wp.org build:** no `Update URI`, no bundled plugin, GPL-only assets, clean zip (`src/` in, `node_modules`/`.git`/`.min` sources handled per rules).
  - **commercial build:** `Update URI` re-added, Kits Core bundled (TGMPA/recommended), demo content + docs.
- Marketplace assets: screenshot (have 1200×900), banner/icon for each; demo for the commercial variant.
- `LICENSE`, documentation, changelog.

## Phase 6 — Submit
- wp.org: slug check, readme validator, Theme Check clean on staging, upload to the theme review queue.
- Commercial: package + docs + demo import.

---

## Gates (per CLAUDE.md "bigger technical work")
Each PR: `code-review` skill · `wp-plugin-audit` (when Kits Core changes) · PHPCS on changed files · Deploy check green · Codex comments answered. **Live parity proven before any merge.** Owner merges; ship via `tools/kits-ship.sh`. Update the Notion theme page + `docs/pending.md` as items land. Commit trailer `Co-Authored-By: Wugweb <admin@wugweb.com>`.

## Decisions (owner)
1. **Font: keep self-hosted Inter** (already bundled, local `fontFace`). Google's Inter files may be used as a *source* but must be bundled, never hotlinked. Both tracks chosen.
2. **Name/slug: keep "Kits by Wugweb"** (`kits-by-wugweb`).

## Still open
- Demo style variations: DONE — shipped `styles/slate.json` + `styles/forest.json` (neutral grey base, accent swapped). Owner can swap the hues if preferred.
- Base-accent default (`theme.json primary #FFBE1A`): still open — the generic theme still *defaults* to AW yellow (see Phase 2/3 progress for the safe way to neutralise it).

## Progress (branch `claude/theme-marketplace-prep`, local — not pushed)
Status against `origin`: live == dev, already clean on headers/fonts (no `Update URI`, PHP 8.0, Inter self-hosted, no Google/CDN call).

- **Phase 1 — decouple tenant from the generic theme: DONE.**
  - `7d4b9409` — 8 Animal-Writes CSS rules moved `kit.css` → tenant `aw.css`.
  - `6b3ecadd` — "Animal Writes" name scrubbed from the generic theme (comments, `kits-registry.json`, `readme.txt`); `styles/animal-writes.json` relocated to the plugin.
  - `4ec3199f` — safe AW JS decoupled: dead aliases dropped, hero-motion gate moved to `aw.js`, dead `reduce` var removed. `code-review` (medium): 0 correctness bugs.
  - Verified: `theme_rules_check`, `theme_copies`, `registry_check`, `css_min`, `node --check` all pass; **no "Animal Writes" literal left in the generic theme**.
- **Phase 0/3 — WPCS on the theme PHP: DONE.**
  - `c949a91f` — added `themes/kits-by-wugweb/phpcs.xml.dist` (WordPress-Extra + WordPress-Docs + i18n + PrefixAllGlobals); fixed all violations → **`vendor/bin/phpcs` clean, 0 errors / 0 warnings** (WPCS 3.4.1 / PHPCS 3.13.6, installed in the scratchpad, not the repo).
- **Phase 2 — i18n: DONE.**
  - `9c4b96ec` — generated `languages/kits-by-wugweb.pot` (117 strings: headers, `functions.php`, all 14 patterns, `theme.json`, the style variation, editor JS; one placeholder carries its `translators:` comment, no make-pot warnings). Added `Domain Path: /languages` and `wp_set_script_translations()` for the `kits-button-options` editor script. phpcs still 0/0; `php -l` clean.
- **Deferred to the Dec `aw_`-prefix rename (not theme-only safe):** the kits-core form-engine protocol names `X-AW-Fetch` / `_aw_nonce`, the shared `aw-form-done-title` hook, and the tenant analytics hooks in `kit.js` (harmless no-ops on a plugin-less site).
- **Known i18n gap (quality, not a wp.org blocker):** front-end `kit.js` has a few hardcoded English strings (Next/Back/Close search/Loading results/step labels); it is intentionally dependency-free, so translating them needs a wp-i18n dependency + `wp_set_script_translations`, or server-rendered data-attributes. Deferred.
- **Phase 4 — Kits Core audit: DONE (read-only; nothing fixed yet).** `wp-plugin-audit` (4 dimension agents) + PHPCS. **Verdict: no critical/high security issues, i18n clean, no functional bugs.** Security PHPCS (project CI sniffs) clean (0 errors). Findings, by severity:
  - 🟠 **Security hardening (none critical).** MEDIUM: the uninstall backup includes `kits_altcha_key` and the folder is protected by `.htaccess` only, so on nginx the key is URL-reachable (`uninstall.php:49`, `uninstall-backup.php:160`) — drop the key from the backup. LOW: admin-only DOM-redirect via `kits_spot_url` (`demo.php:274`); public nonce oracle `nopriv kits_form_nonce` (`form-kit.php:116`); theme-update package no integrity check, off by default (`theme-updates.php:50`); favicon host no allowlist (`attribution.php:154`); nonce-in-URL download (`settings-transfer.php:397`); ALTCHA replay not atomic (`altcha.php:62`); preview token in query string (`preview.php:327`).
  - 🟡 **Stale docs / header metadata (submission-relevant, low-risk):** "visit counter" still documented but removed from code (header Description, readme ×3, `uninstall.php`, `help-tab.php:48`); header menu text wrong ("Kits: Overview, Design system, Preview" vs real Home/Theme Options/Settings/Tools/Help); readme "deletes no data" needs the opt-in caveat; **no "External services" section** (GTM, Clarity, update URL) — wp.org wants it; "six items" docblocks say six (code has five); header Description 443 chars (>140, mirrored in `.pot`); no `License URI` line; readme `License` slug `GPLv2 or later` vs header SPDX; readme short-description ~190 (>150).
  - 🟡 **Legacy `aw_`/tenant coupling (= Dec prefix-rename scope, large):** ~54 functions + constants/hooks/options/transients/post-meta on `aw_`/`AW_` (keyword-table/keyword-model/forms/attribution/analytics/sourcing); `aw_campaign` post-type referenced in a "generic" plugin (`share.php`, `theme-options.php`, `site-parts.php`); nonce actions are bare form IDs.
  - ⚪ **WPCS style debt:** ~200 errors / 144 warnings across 35 files (202 auto-fixable) — pre-existing, CI only gates security.
  - **Checked, NOT bugs:** every admin-post/AJAX/REST handler has nonce + capability (verified); no `unserialize`/SQLi/LFI; safe redirects; no hardcoded secrets; uninstall is opt-in + backup-first; the `__return_true` preview REST returns only a boolean; `aw_kw_*` write helpers have no in-plugin caller.
- **Quick wins FIXED on this branch:** (1) security — ALTCHA key kept out of the uninstall backup (still deleted), demo `kits_spot_url` origin-guarded; (2) docs/metadata — Description ≤140 + License URI, readme License slug/short-desc/share-bar/uninstall caveat + **External services** section, stale "visit counter" removed from header/readme/help-tab, `.pot` regenerated. php -l + security PHPCS clean.
- **Still deferred (own effort / December rename):** the `aw_`/`AW_` prefix rename + `aw_campaign` decoupling + the ~200 WPCS `phpcbf` style findings (large, coordinated across plugins).
- **Deferred to the Dec `aw_`-prefix rename (not theme-only safe):** the kits-core form-engine protocol names `X-AW-Fetch` / `_aw_nonce`, the shared `aw-form-done-title` hook, the tenant analytics hooks in `kit.js`, and now the Kits Core `aw_`/`AW_` internals + `aw_campaign` coupling above.
- **Known i18n gap (quality, not a wp.org blocker):** front-end `kit.js` hardcoded strings (above).
- **Phase 5 — build tooling: DONE.** `tools/build-dist.py` packages the theme and Kits Core into zips: `--track wporg` (no theme `Update URI`) vs `--track commercial` (adds it), stripping dev files (phpcs config, token sources, node_modules, tests, src) and keeping the CSS/JS sources beside their `.min` copies + the `.pot`. Verified: wporg theme header has no `Update URI`, commercial does; dev files excluded; sources + POT present. Zips build to `dist/` (gitignored — artifacts). A build is only as ready as the source, so the gates below run first.
- **Demo style variations — DONE (17 Nov).** Added two whole-site variations to `themes/kits-by-wugweb/styles/`: `slate.json` (cool grey-blue accent `#C6D2E0` / ink `#334E68`) and `forest.json` (muted green `#A8D5B5` / ink `#1E6B43`). Each redefines the full 19-colour palette (a variation's palette *replaces*, it does not merge) with only `primary`/`primary-dark`/`focus` swapped, so the neutral grey base stays. Additive and zero live-risk: they only apply when a user picks them in Appearance → Styles; they change neither the theme default nor AW's applied look. Confirmed `--kit-color-accent: var(--wp--preset--color--primary, …)` (`kit-tokens.css:32`), so swapping the preset genuinely recolours the whole accent system (buttons, datecards, shadows, links). Contrast verified by hand (dark `#191919` on-accent text is fixed, not preset-driven): ash-on-accent, accent-on-ink links, and accent-ink links on the `#F4F4F4`/`#FFFFFF` bases all ≥ 5.6:1; the accent is never a thin boundary that needs 3:1 (the focus ring uses its own `focus` token). JSON parses clean.
- **Default accent neutralised at build time — DONE (17 Nov, `de6763e6`).** The generic theme still *defaulted* to the Animal Writes yellow (`theme.json` `primary` = `#FFBE1A`), a tenant colour. Editing the repo is live-risky — `--kit-color-accent` falls back to the `primary` preset, so a repo change would recolour AW's live accent unless AW's look is a fully self-contained DB global-styles post (not verifiable from here). Instead `tools/build-dist.py` now swaps `#FFBE1A` → a neutral `#C6D2E0` (grey-blue, = the Slate variation; dark `#191919` on-accent text 11.5:1) across `theme.json`'s primary preset and the CSS fallback literals, in the built zip only, on both tracks (same mechanism as the theme `Update URI`). Verified: the built zip has no `#FFBE1A`; the repo and live AW keep `#FFBE1A`. Owner changes `NEUTRAL_ACCENT` (one line) to pick a different shipped default — keep it light enough for dark on-accent text.
- **Turnkey staging steps (owner):** (1) render-compare the 16 live page types at 375/768/1280, branch CSS/JS vs current live → expect 0 diffs (the parity gate for shipping dev→live, not the marketplace zip); (2) activate the built theme zip on staging, run **Theme Check** → expect clean; (3) **done** — demo variations shipped (Slate/Forest) and the default accent is neutralised at build time; (4) run the wp.org **readme validator** on each `readme.txt` (Theme URI now resolves: `wugweb.com`); a real front-page screenshot (the current one is the kits logo).
- **Remaining:** owner uploads to the theme + plugin directories (logged-in; slug `kits-by-wugweb` free). The `aw_` rename is **in progress** on branch `claude/kits-core-rename` — Phase 1 done, the coordinated SI/Sourcing namespace rename underway (see the pending.md entry).

## Status — 17 Nov 2026 (on `wordpress-dev`, NOT shipped)
On `origin/wordpress-dev` @ `6b106a4e` (`ec5a81b0..6b106a4e`). Local gate green: `css_min` up-to-date, `theme_rules` / `theme_copies` / `registry` pass, `php -l` clean, theme WPCS 0/0, security PHPCS clean. The Deploy **check** runs on the dev tip. **Not shipped to live.** Done since the initial push: the build `src/` fix, the Slate/Forest demo variations, the build-time default-accent neutralisation, the readme `Requires PHP` fix, comment support + password-form styling, the Theme URI repoint, and the audits (wp.org Required checklist, block-theme conformance, Theme Unit Test coverage) — all clean. **Repo work is complete; only owner-side steps remain** (a real front-page screenshot, Theme Check on staging, the logged-in directory upload). The `aw_` rename is **in progress** on branch `claude/kits-core-rename`: Phase 1 done (form engine + dual-accept protocol + `aw_campaign` decouple + helpers, 6 suites green, not pushed), with the coordinated SI/Sourcing namespace rename underway in that session.
