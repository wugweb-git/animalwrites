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

## Open decisions for the owner
1. **Display face:** keep self-hosted **Inter** as the marketplace default, or also bundle **Inter Tight** woff2 for the exact display look? (Inter Tight isn't in the repo; bundling needs the OFL files.)
2. **Theme name/slug for distribution:** keep "Kits by Wugweb", or a marketplace-specific name? (slug is permanent on wp.org.)
3. **Demo style variations:** which 1–2 neutral looks ship as the marketplace demo.
