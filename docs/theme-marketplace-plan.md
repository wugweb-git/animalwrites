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
Each PR: `code-review` skill · `wp-plugin-audit` (when Kits Core changes) · PHPCS on changed files · Deploy check green · Codex comments answered. **Live parity proven before any merge.** Owner merges; ship via `tools/aw-ship.sh`. Update the Notion theme page + `docs/pending.md` as items land. Commit trailer `Co-Authored-By: Wugweb <admin@wugweb.com>`.

## Decisions (owner)
1. **Font: keep self-hosted Inter** (already bundled, local `fontFace`). Google's Inter files may be used as a *source* but must be bundled, never hotlinked. Both tracks chosen.
2. **Name/slug: keep "Kits by Wugweb"** (`kits-by-wugweb`).

## Still open
- Which 1–2 neutral style variations ship as the marketplace demo (Phase 2).

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
- **Remaining:** live render-compare parity (staging) · demo style variations (owner's look choice) · Phase 4 Kits Core `wp-plugin-audit` + PHPCS · Phase 5/6 two builds + submission · owner runs Theme Check on staging.
