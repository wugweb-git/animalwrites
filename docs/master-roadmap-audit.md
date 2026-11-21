# Animal Writes & Kits Companion Suite: Master Roadmap, Architecture & UI Audit

Compiled: 21 Nov 2026  
Status: Active Reference & Audit Log  
Author: Animal Writes WordPress Session

---

## 1. Executive Summary

This master document synthesizes all completed deliveries, user instructions, architecture plans, pending batches, research notes, and UI/UX design audits across the **Animal Writes** platform and the **Kits by Wugweb** companion suite.

All documentation, pending task lists, and research reports are tracked directly in Git to prevent local drift or data loss.

---

## 2. Completed Milestones & Live Deployments

### A. Theme Breakdown & Crash Resolution (Live)
* **Root Cause Diagnosis:** `amp-wp` (AMP for WP v1.8.3) conflicted with `amp` (Official WordPress AMP plugin) by redeclaring `function is_amp_endpoint()` globally in PHP without `function_exists()` guards, causing a fatal 500 error on bootstrap.
* **Resolution:** Safely deactivated `amp-wp` via WP-CLI without modifying user data. Block theme `kits-by-wugweb` was restored immediately with **HTTP 200 OK**.
* **DirectoryWeb UI Restoration:** Safely deactivated `business-directory-plugin` (WPBDP) which was intercepting core queries and zeroing out `wp_count_posts( 'aw_listing' )`. Verified all **7,738 listings** are intact (**6,872 published**, **866 pending/draft**, **3,677 verified**, **48 needs re-check**).

### B. Master Plan Item B4: Listing Merge & Darpan Integration (Live)
* **Post 43 ("Nawabi Tails Rescue Foundation"):** Updated with Darpan ID `UP/2026/1159491`, Kanpur Nagar registration notes, phone number `7905934763`, head `Dr. Vishakha Shukla`, area `Lucknow, Uttar Pradesh`, and founding date `2021`.
* **301 Redirection:** Configured permanent redirect from `^directory/org/nawabi-tails-rescue-lucknow/?$` to `https://animalwrites.in/directory/org/nawabi-tails-rescue/` (verified HTTP 301 -> 200 via curl).
* **Duplicate Cleanup:** Trashed redundant import listing 18308 and pruned from sitemaps and `aw_seo_keep_listings`.

### C. Release 1: World-Class Companion Suite (PR #170, Release Tag `release-20261120-2231-4151ad25`)
* **Kits Onboarding & Setup Wizard (`plugins/kits-core/includes/onboarding-wizard.php`):** Guided 1-click wizard (`admin.php?page=kits-onboarding`) covering site identity, visual style variations (`default`, `slate`, `forest`, `ink`), typography pairings, and Site Editor launcher (benchmark: Astra, Kadence).
* **Header & Footer Custom Scripts Manager (`plugins/kits-core/includes/header-footer-scripts.php`):** Secure code injection UI (`admin.php?page=kits-scripts`) for `<head>`, `wp_body_open`, and `wp_footer` (benchmark: WPCode).
* **Reprint & Canonical Syndication Engine (`plugins/rankweb/includes/reprint.php`):** Canonical URL protection meta box and frontend attribution badges.
* **RankWeb Gutenberg Editor Sidebar (`plugins/rankweb/includes/editor-sidebar.php`):** Real-time 0–100 SEO score pill, SERP preview toggle (desktop/mobile), and content checklist.
* **Block Inspector Enhancements:** Added radius, zoom, and near-me controls to `directoryweb/map` and styling variants to `mailweb/newsletter`.
* **Plugin Check (PCP) Hardening:** Added direct file access guards (`defined( 'ABSPATH' )`) across all test runners and asset scripts.

### D. Release 2: AMP-Grade Skeletons & Native A/B Testing Suite (PR #171, Commit `6a3da1fd`)
* **AMP-Grade Shimmering Skeletons (`plugins/kits-core/src/shell/states.js`, `more.css`):**
  * Built `SkeletonTable`, `SkeletonRow`, `SkeletonCard`, and `SkeletonLine` with `@keyframes kits-shimmer` linear gradient animation.
  * Integrated into `DataList` and `DirectoryWeb` Dashboard to provide zero-CLS placeholder tables and eliminate layout pop during data fetches.
* **Native A/B & Multivariate Experimentation Engine (`plugins/kits-core/includes/ab-testing.php`):**
  * Zero-CLS, zero database queries, 30-day cookie persistence.
  * Instant QA preview via query string: `?kits_ab_{experiment_id}=B`.
  * Shortcode: `[kits_ab id="hero_cta" variant="A"]...[/kits_ab]`.
  * Automated dataLayer push (`kits_experiment_impression`) for Google Analytics 4 and Microsoft Clarity integration.
  * 11/11 passing unit tests in `plugins/kits-core/tests/ab-testing-tests.php`.

---

## 3. Pending Roadmap Batches (Master Plan)

### Batch 1: Claim Flow Quality & Upload Polish (Items B1–B3)
* **B1. About Field Prefill Sanitization:**
  * Clean raw HTML/date tags (e.g. `<span class="kit-date">`, `<time>`) from `textarea[name=l_about]` on `/claim/?listing=ID`. Pre-fill from clean text; strip tags in `claims-review.php` before applying.
* **B2. Upload Limit & Clear Messaging:**
  * Raise upload limit in `kits_ff_upload()` and `claims.php` to 8 MB with client-side downscaling.
  * Replace generic error with exact diagnostics: file size limit vs file type (`PNG, JPG, WebP`).
* **B3. Multi-Step Validation Error Summary:**
  * In `forms.js`, when validation fails on a previous step, show a clickable error summary banner on the active step rather than silently jumping steps without context.

### Batch 2: 3-Step Claim Form (Item B5 - Owner Gated)
* **Global Form Specs:** 16px inputs, 44px tap targets, mobile-sticky Next/Back buttons, red asterisks for mandatory fields, no site-wide newsletter distraction on `/claim/`.
* **Step 1 (Who you are):** Name, Role, Email, Mobile (10 digits), WhatsApp tick, Show name toggle, Volunteer opt-in checkbox.
* **Step 2 (Proof):** Document upload (PDF/image, 8 MB max), NGO Darpan ID, Registration year, Notes.
* **Step 3 (Send):** Read-only submission summary, accuracy confirmation, privacy consent, optional newsletter opt-in, honeypot (`kits_hp`), "We reply in 4 to 5 days."

### Batch 3: Complete Your Profile Screen (Item B6 - Post-Approval)
* Dedicated profile completion portal opened via emailed token.
* Regrouped sections: Contact, Indian-standard Address, About, Photos/Logo, Social links, Official Registrations (12A, 80G, Darpan, FCRA, AWBI).
* Granular Public vs Hidden privacy switches for every sensitive field.

---

## 4. UI & Layout Audit: Section Padding, Spacing & Visual Rhythm

An exhaustive review of the live front-end templates (`themes/kits-by-wugweb/`) revealed key spacing rules and remediation requirements:

### Findings & Inconsistencies:
1. **Vertical Rhythm Gap between Adjacent Full-Width Sections:**
   * Full-width sections using `.is-style-kits-section` apply vertical padding via `theme.json` (`padding.top: var(--wp--preset--spacing--section)` = 48px mobile, 70px tablet, 96px desktop).
   * However, when two `.kit-section` containers with different background colors (e.g., `.kit-bg-kraft` followed by `.kit-bg-canvas` or `.has-secondary-background-color`) meet, margin collapse does not apply, producing uniform padding. When two sections of the *same* background color meet, the combined 192px (96px + 96px) gap appears as excessive dead space.
2. **Compact Section Bands (`.kit-section-band--compact`):**
   * Hub heads and archive titles require reduced vertical padding (`--kit-space-head`: 24px mobile / 40px desktop) so content above the fold is not pushed downward.
3. **Card Grid Spacing on Mobile (<768px):**
   * Grid gaps on phone screens must stay at a tight 16px (`--kit-space-4`) rather than stretching or overlapping.
4. **Header and Footer Transition:**
   * The site header (`.kit-site-header`) uses padding `var(--wp--preset--spacing--30)`. On pages with an immediate `.kit-section-band`, the top section padding should be normalized to eliminate double spacing.

### Recommended CSS Normalization Rule:
```css
/* Adjacent identical-background sections normalization */
.wp-site-blocks > .is-style-kits-section + .is-style-kits-section:not([class*="has-background"]) {
    padding-top: 0 !important;
}

/* Ensure consistent responsive section padding */
.is-style-kits-section {
    padding-block: clamp(2.5rem, 6vw, 5rem);
}
```

---

## 5. Local Repository & Documentation Inventory

The following documents and research materials are committed directly to Git:
* `docs/master-roadmap-audit.md`: This comprehensive master file.
* `docs/cc-master-plan-19-nov.md`: Full 19 Nov master plan (Batches B1–B6, C1–C3).
* `AGENTS.md`: Core working rules (Rule 1 merge base audit, Rule 3 deploy gate, Rule 4 worktree discipline, Rule 5 data preservation).
* `reports/Rabies helpline and animal law facts.md`: Deep research report on animal welfare helplines and legal statutes.
* `research_notes/Rabies helpline and animal law facts/`: 9 detailed research chapters (bird helplines, CPCSEA/IAEC/AWBI, cruelty laws, feeding laws, 1962 helpline, NGO Darpan, observance days, Rabies PEP, Rabies verification).
* `drafts/keyword-plan/`: Organic search keyword inspection and search intent review documentation.
