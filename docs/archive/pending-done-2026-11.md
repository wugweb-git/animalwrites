# Pending: finished work, archived 25 Nov 2026

Moved out of `docs/pending.md` unchanged. Each section was marked done or completed with its PR or commit.

### Kits WordPress Suite Monorepo & Multi-Tenant Brand Customizer (done 21 Nov 2026, branch `kits-suite` on wugweb-git/kits)
- **Standalone Monorepo & Multi-Tenant Suite (`https://github.com/wugweb-git/kits` branch `kits-suite`):**
  - Populated standalone repository on dedicated branch `kits-suite` with universal FSE block theme `themes/kits-by-wugweb` and 7 companion plugins: `kits-core`, `directoryweb`, `rankweb`, `mailweb`, `sourceweb`, `insightweb`, `planweb`.
  - Pre-existing files in the `Kits` repository preserved 100% untouched.
  - Authored comprehensive architectural and operational guide `KITS_WP_SUITE.md` in repository root.
- **Brand Customizer UI (`plugins/kits-core/includes/brand-customizer.php`, `brand-customizer-model.php`):**
  - Registered admin screen at `admin.php?page=kits-brand` and shortcut under `Appearance > Brand Tokens`.
  - Configured full brand token coverage: Brand Colors (`--kit-color-accent`, `--kit-color-accent-ink`, `--kit-color-bg`, `--kit-color-raised`, `--kit-color-ink`, `--kit-color-text`, `--kit-color-muted`, `--kit-color-line`), Typography (`--kit-font-display`, `--kit-font-body`), Corner Radii (`--kit-radius-control`, `--kit-radius-card`, `--kit-radius-media`, `--kit-radius-pill`), and Layout Boundaries (`--kit-width-content`, `--kit-width-wide`, `--kit-space-section-base`).
  - Added 4 built-in 1-Click Tenant Profiles: Default (Animal Writes / Neutral), Tenant 1 (Modern Tech & SaaS), Tenant 2 (Editorial & Publication), and Tenant 3 (Eco & Community Wellness).
  - Added Export / Import JSON functionality for zero-downtime replication across tenant WordPress instances.
  - Dual CSS bridge injection into frontend `<head>` and block editor canvas `.editor-styles-wrapper` with automated LiteSpeed cache purge on save.
  - Comprehensive unit test suite `plugins/kits-core/tests/brand-customizer-tests.php` passing all 22 tests cleanly without WordPress dependencies.
  - Detailed tenant onboarding guide written to `docs/multi-tenant-setup-guide.md`.

### UI & Plugin Code Reset to Last Claude Push (completed 21 Nov 2026, commit `4151ad25` / PR #170)
- **UI and plugin code reset to the last Claude push (`4151ad25` / PR #170):**
  - Reverted experimental skeleton loaders, zero-CLS layout states, and A/B testing engine from `plugins/kits-core` and `plugins/directoryweb`.
  - All plugins and theme files are 100% byte-for-byte identical to Claude's PR #170 release (`4151ad25`).
  - All documentation, research notes, and audit reports (`docs/master-roadmap-audit.md`, `docs/ui-spacing-audit.md`, `docs/kits-audit-architecture-deliverables.md`, rabies research notes) remain committed and tracked in Git.

### UI Layout Normalization & Card Spacing Overhaul (completed 23 Nov 2026)
- **Implemented sitewide card spacing & padding overhaul per owner directive:**
  - Card containers strictly enforced to zero padding (`padding: 0 !important; margin: 0 !important;`).
  - Independent content padding applied to all card bodies: desktop `12px 16px !important;`, mobile (`max-width: 767px`) `8px 10px !important;`.
  - Media & thumbnail images flush against card's left, top, and bottom edges (`align-self: stretch !important; margin: 0 !important; border-radius: 0 !important;`).
  - Meta row (`.kit-card-metarow`, `.kit-card-meta`, `.kit-card-chips`, `.kit-meta-row`) strictly enforced to never wrap into two rows (`flex-wrap: nowrap !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important;`).
- **Section Spacing & Vertical Rhythm Normalization (`docs/ui-spacing-audit.md`):**
  - Adjacent section dead space eliminated: `.is-style-kits-section` sharing canvas backgrounds now collapse top padding (`padding-top: 0 !important;`).
  - Cover story bands (`.wp-block-cover.kit-section.aw-ed-story`) given explicit symmetric vertical padding (`var(--kit-space-section)`), eliminating content clipping on mobile and tablet.
  - FAQ block nesting normalized: `.is-style-kits-section > .kit-faq` padding reset to `0` to prevent compounding wrapper margins.
  - Hub list (`.aw-system`) into `.aw-hub-faq` and `.kit-nl-band` transitions streamlined with `margin-top: var(--kit-space-6)`.
- **Cross-Navigation & Directory Related NGO Stacked Cards:**
  - Single page cross navigation (`aw-more` / `.aw-more-list` / `item-more`) converted to responsive stacked cards (`kit-card--stacked-md`).
  - Directory related NGO cross-navigation converted to stacked profile card layout (`.aw-more-card--org`, `.aw-card-profile`) with flush logo/initials on left, and single-line meta row, title, and summary on right.

### UI & Section Spacing Normalization Audit (completed 21 Nov 2026, docs `docs/ui-spacing-audit.md` and `docs/master-roadmap-audit.md`)
- **Full audit conducted across live production (`/`, `/directory/`, `/library/`, `/news/`, single listings, campaigns):**
  - Identified adjacent `.is-style-kits-section` blocks producing cumulative 192px dead space on desktop (96px on mobile) when sharing identical canvas backgrounds. Normalization rule specified: `.is-style-kits-section:not(.has-background):not(.kit-bg-kraft):not(.kit-surface-section) + .is-style-kits-section:not(.has-background):not(.kit-bg-kraft):not(.kit-surface-section) { padding-top: 0 !important; }`.
  - Identified missing vertical padding on `.wp-block-cover.kit-section.aw-ed-story` (Home story band), where inline styles declared horizontal padding only (`padding-left` and `padding-right`), leaving vertical content touching container boundaries. Normalization rule specified: `padding-top: var(--kit-space-section) !important; padding-bottom: var(--kit-space-section) !important;`.
  - Identified Hub list (`.aw-system`) into `.aw-hub-faq` and `.kit-nl-band` transitions producing irregular 136px vertical stacking gaps. Normalization rule specified: `margin-top: var(--kit-space-6)`.
  - Pruned 14 merged/stale worktrees and 64 merged local branches.

### AMP-Grade Skeletons, Zero-CLS Layouts & Native A/B Testing Engine (done 21 Nov 2026, PR #171, commit `6a3da1fd` live)
- **Built, merged and live (PR #171, commit `6a3da1fd`):**
  - **Shimmering Skeleton Layouts (`plugins/kits-core/src/shell/states.js`, `more.css`)**: Built `SkeletonTable`, `SkeletonRow`, `SkeletonCard`, and `SkeletonLine` with `@keyframes kits-shimmer` linear gradient animation. Integrated into `DataList` so initial loads and view filters display zero-CLS animated placeholder tables instead of abrupt UI pops.
  - **DirectoryWeb Shimmer Dashboards (`plugins/directoryweb/src/index.js`)**: Modernized Overview dashboard loading state to render 6 shimmering `SkeletonCard` tiles during count calculations.
  - **Native A/B & Multivariate Testing Engine (`plugins/kits-core/includes/ab-testing.php`)**: Built cache-friendly, zero-CLS split test framework with `kits_ab_variant()`, `kits_ab_is()`, `[kits_ab]` shortcode, 30-day cookie persistence, QA query string override (`?kits_ab_{exp}=B`), and automatic Google Tag Manager / GA4 (`kits_experiment_impression`) and Microsoft Clarity integration. Covered with 11 standalone unit tests (`plugins/kits-core/tests/ab-testing-tests.php`).
  - Gated deploy run `44899989371` passed green; Hostinger deployed `6a3da1fd` with LiteSpeed cache purge HTTP 200.

### World-Class Products Suite: Onboarding Wizard, Scripts Manager, Reprint Engine, RankWeb Editor Sidebar, Block Inspector Controls, and PCP Fixes (done 20 Nov 2026, PR #170, commit `4151ad25` live)
- **Built, merged and live (PR #170, commit `4151ad25`, tag `release-20261120-2231-4151ad25`):**
  - **Kits by Wugweb Onboarding & Setup Wizard (`plugins/kits-core/includes/onboarding-wizard.php`)**: Built 1-click guided onboarding (`admin.php?page=kits-onboarding`) covering site identity (custom logo via `wp.media`, title, tagline), style variations (`default`, `slate`, `forest`, `ink`) with visual palette swatches, typography pairing, and Site Editor launcher (benchmark: Astra, Kadence, Create Block Theme).
  - **Header & Footer Custom Scripts & Pixels Manager (`plugins/kits-core/includes/header-footer-scripts.php`)**: Built secure admin UI (`admin.php?page=kits-scripts`) and hooks for `<head>`, `wp_body_open`, and `wp_footer` injection (benchmark: WPCode / Insert Headers and Footers).
  - **Reprint & Canonical Syndication Engine (`plugins/rankweb/includes/reprint.php`)**: Meta box and automated canonical protection + frontend attribution badge for syndicated/republished articles (benchmark: Reprint).
  - **RankWeb Gutenberg Editor Sidebar (`plugins/rankweb/includes/editor-sidebar.php`, `assets/editor-sidebar.js`, `assets/editor-sidebar.css`)**: Live 0–100 SEO score pill in header, Google SERP snippet preview (desktop/mobile toggle), and real-time content analysis checklist (benchmark: Rank Math Pro / Yoast).
  - **DirectoryWeb & MailWeb Gutenberg Block Inspector Enhancements**: Added attributes and responsive container data attributes (`radius`, `zoom`, `showNearMe`, `height`) to `directoryweb/map`, and layout styling variants (`layout`, `heading`, `subheading`) to `mailweb/newsletter`.
  - **Plugin Check (PCP) Complete Clean-Up**: Added direct file access guards (`if ( ! defined( 'ABSPATH' ) ) exit;`) to all 15 `.asset.php` and test files across all 6 plugins, fixed `taxonomy-rename-tests.php` syntax, and fixed `mailweb_settings_url()` classic UI redirect.
  - Verified on live Hostinger production with LiteSpeed cache purge HTTP 200.

### Native Blocks, Schema Consumer & A11y Polish (done 20 Nov 2026, PR #169, commit `86c8ea2d` live)
- **Built, merged and live (PR #169, commit `86c8ea2d`, tag `release-20261120-1303-86c8ea2d`):**
  - Registered native Gutenberg block `directoryweb/map` and shortcode `[directoryweb_map]` calling `directoryweb_render_map_block()` in `plugins/directoryweb/includes/directory-map.php`.
  - Registered native Gutenberg block `mailweb/newsletter` and shortcode `[mailweb_newsletter]` calling `mailweb_nl_ff_form()` in `plugins/mailweb/includes/newsletter.php`.
  - Wired `kits_schema_map` into RankWeb via `rankweb_preset_schema_node()` in `plugins/rankweb/includes/schema.php`, outputting structured data schema.org nodes for preset post types into Rank Math's graph.
  - WCAG Accessibility Fix: Dropped invalid `role="link"` from unlinked Directions spans in `plugins/directoryweb/includes/listing-profile.php:261` and `listing-template.php:184`.
  - Added `autocomplete="off"` to listing admin textareas in `plugins/directoryweb/includes/listing-profile.php:108`.
  - Updated theme tags in `themes/kits-by-wugweb/style.css` to include official `block-theme` and `style-variations` tags per Theme Handbook review criteria.
  - Verified on live Hostinger production with LiteSpeed cache purge HTTP 200.

### Kits Help IA overhaul & Theme Block-Type patterns (done 20 Nov 2026, PR #168, commit `d88e0304` live)
- **Built and merged (PR #168, commit `d88e0304`):**
  - Added tabbed section switcher sub-navigation to `kits_sections_page()` and `kits_sections_back()`, visually separating the 6 stacked sections into clean tabs: `[ Docs ]`, `[ Design System ]`, `[ Developer ]` (with `[ Show all ]` fallback), completely eliminating the 20-meter vertical scroll overflow on `kits-help`.
  - Added client-side pagination (15 items per page with `« Previous`, `Page X of Y`, `Next »`) and real-time search/filter reset to the 172-item Design System registry table in `plugins/kits-core/includes/system-screen.php`.
  - Added 3-step Quick Start Action List to Kits Home (`admin-menu.php`, `kits_home_theme()`): Step 1 Logo & Header (`site-editor.php?postType=wp_template_part`), Step 2 Brand Colours & Typography (`site-editor.php?path=/wp_global_styles`), Step 3 Block Patterns (`site-editor.php?path=/patterns`).
  - Added official WordPress Theme Developer Handbook `Block Types:` declarations across 9 theme block patterns (`banner`, `hero-split`, `card-grid`, `cta`, `empty-state`, `faq`, `newsletter`, `section`, `three-big-actions`), enabling native 1-click transformation and suggestion in the block editor.
  - Added CSS styles for `.kits-section-nav`, `.kits-section-tab`, `.kits-reg-pagination`, and `.kits-quickstart-card` to `plugins/kits-core/src/shell/more.css` and `plugins/kits-core/build/shell/index.css`.

### Admin button piles: one "action list" part for the Kits shell (done 20 Nov 2026, PR #167, commit `bd14adb4` live)
- **Built and live (PR #167, commit `bd14adb4`):**
  - Added `.kits-action-list` and `.kits-action-row` in `plugins/kits-core/src/shell/more.css` and `includes/admin-kits-frame.php` (`kits_frame_action_list()`).
  - Added Site Kit / FluentCRM style `.kits-kpi-grid` and `.kits-kpi-card` with SVG sparklines and trend pills (`kits_frame_kpi_card()`, `kits_frame_kpi_grid()`).
  - Integrated into Kits Core Home (`admin-menu.php`) and InsightWeb Runs & Data (`admin.php`), replacing the 7-button pile with a structured Action List.
  - Upgraded Design System tabs to interactive `.kits-chip` pills and added KPI scorecard to Colour Contrast tab.
  - Resolved all issues from the 6 Plugin Check markdown reports: eliminated `eval()` in `admin-layer-tests.php`, deleted hidden `.distignore` in `planweb`, standardized direct file access checks across all test runners, and added `UnfinishedPrepare` suppression in `uninstall-backup.php`.
  - Verified on live Hostinger production with LiteSpeed cache purge HTTP 200.
