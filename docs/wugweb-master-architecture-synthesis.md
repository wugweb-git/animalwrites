# Wugweb & Kits 3.0: Master Architectural Synthesis

**Status:** Canonical Living Blueprint  
**Scope:** Design System, Block Theme, Companion Plugins, Token Foundry, Multi-Tenant Fleet, and Distribution Pipeline  
**Governing Rule:** One Repository $\neq$ One Product $\neq$ One Release  

---

## 1. Executive Thesis & The Core Architectural Axiom

The central insight unifying the entire Wugweb ecosystem is the strict separation of **Source**, **Presentation**, **Operational Logic**, and **Distribution**:

```
                       WUGWEB FOUNDRY
             Master Tokens, Schemas, Storybook, QA
                   (packages/design-tokens)
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
       KITS BLOCK THEME               KITS CORE PLUGIN
   Declarative FSE Presentation     Theme Hub & Operational Logic
    (themes/kits-by-wugweb)             (plugins/kits-core)
              │                               │
              └───────────────┬───────────────┘
                              ▼
                    WORDPRESS SITE EDITOR
             (Native Gutenberg Block Canvas)
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        TENANT 0 (AW)   TENANT 1 (IKAN)  TENANT 2 (MUSIC)
```

### The Three Independent Layers
1. **The Foundry (`packages/design-tokens`, `packages/design-system`)**: The upstream design system source of truth. Contains master `tokens.json`, semantic mappings, component contracts, and Storybook builds. Has zero WordPress runtime dependencies.
2. **The Theme (`themes/kits-by-wugweb`)**: A 100% declarative, zero-code block theme. Owns templates, template parts, block patterns, styles, and `theme.json`. **Strictly forbidden** from containing business logic, custom admin menus, cloud sync, or silent plugin installers.
3. **The Companion Plugin (`plugins/kits-core`)**: The administrative, operational, and integration layer. Owns the Theme Hub (`Appearance > Theme Hub`), onboarding wizard, licensing validation, tenant token management, cloud synchronization, and durable custom blocks that must survive a theme switch.

---

## 2. Product Boundaries & Packaging Architecture

### 2.1 The Eight Canonical Products

| Product Family | Full Product Name | Shorthand | Prefix / Namespace | WordPress.org Status | Distribution Artifact |
|---|---|---|---|---|---|
| **Theme** | **Kits by Wugweb** | Kits Theme (KT) | `kits_` | Standalone Block Theme | `dist/kits-by-wugweb.zip` |
| **Suite Core** | **Kits Core** | Core (KC) | `kits_core_` | Companion Management | `dist/kits-core.zip` |
| **Standalone** | **RankWeb** | Rank (RW) | `rankweb_` | GO (First priority) | `dist/rankweb.zip` |
| **Standalone** | **PlanWeb** | Plan (PW) | `planweb_` | GO (Standalone ready) | `dist/planweb.zip` |
| **Standalone** | **MailWeb** | Mail (MW) | `mailweb_` | GO (SDK & Deliverability ready) | `dist/mailweb.zip` |
| **Standalone** | **InsightWeb** | Insight (IW) | `insightweb_` | Viable (Site Kit model) | `dist/insightweb.zip` |
| **Standalone** | **SourceWeb** | Source (SW) | `sourceweb_` | Generic registry needed | `dist/sourceweb.zip` |
| **Standalone** | **DirectoryWeb** | Directory (DW) | `directoryweb_` | Multi-channel directory | `dist/directoryweb.zip` |

### 2.2 The Bundled-Code Model & Namespaced Shells
* **Zero Runtime Inter-Dependencies**: A public plugin edition must **never** require another Wugweb plugin at runtime simply because both live in the same monorepo.
* **Namespaced Admin Shells**: Reusable admin chrome is extracted as a headless package (`packages/admin-shell`) and bundled into each plugin under a strict namespaced handle:
  * `rankweb-shell`
  * `mailweb-shell`
  * `planweb-shell`, etc.
  * *Rule:* Never register a global `kits-shell` handle that could collide when multiple Wugweb plugins are installed on the same WordPress site.
* **`rankweb-core` Package**: The shared SEO engine containing 9 isolated PHP classes (Linker, Anchor, Silo, Link Audit, Video, Schema, Bulk, Redirects, Agent). Equipped with class collision guards (`if (!class_exists(...))`) allowing multiple bundled versions to coexist peacefully.

---

## 3. Design-Token Architecture & The Token Foundry

### 3.1 Two Token Categories in `theme.json`
Every token in Kits 3.0 belongs to one of two structural categories:

```
theme.json
├── settings.color / settings.typography / settings.spacing  --> Editor-Facing Presets (--wp--preset--*)
└── settings.custom                                         --> Custom System Tokens (--wp--custom--*)
```

1. **Editor-Facing Presets**: Exposed directly to end-users inside Gutenberg's native styling sidebars (`--wp--preset--color--primary`, `--wp--preset--font-size--large`, `--wp--preset--spacing--medium`).
2. **Custom Tokens**: Internal design variables defined under `settings.custom` for properties WordPress does not expose natively:
   * `--wp--custom--radius--md` (Border Radii)
   * `--wp--custom--stroke--default` (Border Widths)
   * `--wp--custom--blur--md` (Backdrop Filters)
   * `--wp--custom--width--content` (Layout Constraints)

### 3.2 The Four Coexisting Variable Tiers
To prevent collision between editor tools, theme presets, and component styles:
* **`--wpds-*`**: WordPress core Design System variables (controls Gutenberg editor chrome and wp-admin toolbars).
* **`--wp--preset--*`**: Native WordPress theme presets generated from `theme.json`.
* **`--wp--custom--*`**: Custom variables generated from `settings.custom`.
* **`--kit-*`**: The semantic bridge variables consumed by Kits patterns, templates, and plugins:
  ```css
  --kit-radius-card: var(--wp--custom--radius--md, 8px);
  --kit-color-accent: var(--wp--preset--color--accent, #f59e0b);
  ```

### 3.3 Stable Identifiers vs. Tenant Value Overrides
* Token identifiers (`blur.md`, `stroke.default`, `radius.md`, `shadow.card`) are **frozen and immutable across releases**.
* Tenants modify the **values**, never the keys. This guarantees that updating design tokens never breaks existing block templates, patterns, or child themes.

### 3.4 The "Reset to Tenant Baseline" Requirement
Native WordPress Site Editor behavior resets styles to the theme's disk defaults. In the Kits multi-tenant suite:
1. Tenant values are configured in Kits Core's Token Customizer UI and stored in `wp_options` (`kits_brand_tokens`).
2. Kits Core hooks the WordPress core filter **`wp_theme_json_data_theme`**.
3. It dynamically injects the tenant's token overrides into the theme's baseline data structure *before* user-level customizations are evaluated.
4. **Outcome**: When an administrator clicks "Reset to Default" inside native Site Editor > Styles, WordPress resets to the **tenant's configured brand baseline**, not the theme's original generic default!

---

## 4. The Two-Way Foundry Flywheel & Tenant Contribution

The Foundry operates as an active bidirectional innovation loop:

```
[Downstream Distribution]
Foundry (tokens.json) ──> Cloudflare R2 / ZIP ──> Kits Core ──> Theme ──> Live Tenant Site

[Upstream Contribution]
Tenant Site (Pattern) ──> Export (JSON/HTML) ──> Sanitization Gate ──> Playground QA ──> PR into Foundry
```

### 4.1 Storage Boundaries (Where Changes Live)
* **Page Content**: WordPress database (`wp_posts`).
* **Synced Patterns**: WordPress database (`wp_block` post type).
* **Theme Templates**: Filesystem or database overrides (`wp_template`).
* **Global Styles**: Database overrides (`wp_global_styles`).
* **Theme Source Code**: Git repository (`wugweb-git/kits`).
* *Crucial Realization:* GitHub never sees Block Editor edits automatically. Synchronization requires an explicit export pipeline.

### 4.2 Pre-Promotion Sanitization Checklist
Before any tenant pattern is merged into canonical Foundry patterns:
- [x] **Media Scrubbing**: Replace tenant images/video with neutral SVG placeholders or open Kits assets.
- [x] **Copy Neutralization**: Strip brand names, localized addresses, and private phone numbers.
- [x] **CSS Purification**: Purge ad-hoc `<style>` tags and inline `style="..."` attributes.
- [x] **Token Mapping**: Ensure all colors, paddings, and radii strictly map to semantic `--kit-*` tokens.
- [x] **Licensing & IP**: Confirm explicit user reuse consent and GPL compatibility.
- [x] **Visual QA**: Tested in disposable WordPress Playground runtime via Blueprint.

---

## 5. Customer Interaction Model & Day/Night Architecture

### 5.1 The Four Customer Touchpoints

```
1. Pages & Posts        --> Build individual content URLs (/about, /news) using Patterns.
2. Site Editor          --> Control structural layout (Headers, Footers, Templates).
3. Global Styles        --> Manage visual design tokens (Palette, Typography scale, Spacing).
4. Wugweb Theme Hub     --> Branded companion dashboard (Onboarding, Component Library, Licensing).
```

### 5.2 Day/Night Architecture (Runtime Switch vs. Style Variation)
A critical architectural separation:

| Layer | Implementation | Purpose | Persistence |
|---|---|---|---|
| **Editor Style Variation** | `styles/dark.json` | An editorial design choice for site owners who want a permanently dark site. | Theme configuration |
| **Visitor Runtime Toggle** | Header button + Interactivity API | An interactive switch enabling site visitors to toggle light/dark modes. | `localStorage` + `prefers-color-scheme` |

* **Zero-FOUC Micro-Script**: An inline, blocking `<script>` in `wp_head` evaluates `localStorage.getItem('kit_theme_mode')` and `window.matchMedia('(prefers-color-scheme: dark)')` to immediately inject `data-theme="dark"` into `<html>` before DOM painting, preventing white-flash.
* **Semantic Variable Shift**: `[data-theme="dark"]` swaps the values of `--kit-color-bg`, `--kit-color-ink`, `--kit-color-raised`, and `--kit-color-line` without requiring component-level CSS overrides.

---

## 6. Onboarding Journey & Activation Safety

### Phase 1 — Purchase & Delivery
* Store confirmation screen with license key, 1-click copy, download link, and 3-step setup guide.
* Welcome transactional email containing invoice, license key, and documentation links.

### Phase 2 — WordPress Activation
* **Zero Activation Hijacking**: The theme provides a clean, dismissible admin notice. Forcibly redirecting users upon theme activation is strictly banned for WordPress.org compliance.

### Phase 3 — Four-Step Tailored Setup Wizard (inside `kits-core`)
* **Step 1: Entitlement & Connection**: Enter license key, validate entitlement, connect to Foundry.
* **Step 2: Architecture & Preset**: Choose curated aesthetic preset, select starter pages and pattern bundle, optionally import placeholder media.
* **Step 3: Brand Customization**: Upload logo, configure primary/accent colors, select typography pairing with live preview.
* **Step 4: Theme Hub Launch**: Direct link to inspect components, configure headers/footers, or launch native Site Editor.

---

## 7. WordPress.org Readiness & Security Governance

### 7.1 WordPress.org Directory Compliance Norms
* **GPL License Only**: No trialware, no locked core features, no bundled hosted plugins.
* **No `Update URI`**: Removed from all public `.org` plugin and theme headers.
* **`Requires Plugins` Rule**: Restricted strictly to dependencies that are themselves hosted on WordPress.org.
* **External Services Disclosure**: Every outbound host must be registered via `register_external_service()` and fully disclosed in `== External services ==` in `readme.txt` with privacy links and purpose.
* **Centralized Network Relay**: `remote_request()` using `wp_safe_remote_*` is the sole authorized outbound route. Returns `WP_Error` if host is unregistered.
* **Unique Prefixes**: Minimum 4-character prefix per plugin. No legacy `aw_` or `aw-` in new code.

### 7.2 Security Safeguards
* **Zero Write Tokens in Client Code**: Never ship Foundry write tokens, GitHub PATs, or master keys inside plugins.
* **Graceful Remote Fallback**: If Cloudflare R2 or the Foundry relay is offline, the theme and companion plugins fall back immediately to local cached patterns and local theme assets without breaking.

---

## 8. Debunked Misconceptions & Clarified Realities

| Misconception / Rejected Proposal | Authoritative Architectural Standard |
|---|---|
| *"Put the custom React wizard & Theme Hub in the theme."* | **REJECTED**: Theme admin screens must not contain heavy React applications or custom business logic. Theme Hub lives exclusively in `kits-core`. |
| *"Themes can silently install companion plugins."* | **REJECTED**: Banned by WordPress.org guidelines. Themes may only suggest plugins via dismissible notices with explicit user approval. |
| *"Directly update `wp_global_styles` in DB to set styles."* | **REJECTED**: Bypasses WordPress core hierarchy. Use `/styles/*.json` for variations and `wp_theme_json_data_theme` for tenant baselines. |
| *"Filtering `wp_theme_json_data_user` locks down the editor."* | **REJECTED**: Does not stop database writes and risks overriding user choices destructively. Use `wp_theme_json_data_theme` instead. |
| *"FSE means everything can live in the theme."* | **REJECTED**: FSE provides block templates, not a licence to bundle business logic, API relays, or custom post types into the theme. |
| *"Pattern references create zero database bloat."* | **REJECTED**: Pages and synced patterns still create database rows in `wp_posts` and `wp_block`. |

---

## 9. Delivery Pipeline & Git Workflow

### 9.1 Review & Merge Sequence
$$\text{Worktree Branch} \longrightarrow \text{Pull Request} \longrightarrow \text{Review Gate} \longrightarrow \text{Owner Approval \& Merge} \longrightarrow \text{Ship Script}$$

* **Review Gate Checklist**:
  1. Code review via code-review skill.
  2. Plugin audit via `wp-plugin-audit` on changed plugins.
  3. PHPCS on changed files.
  4. Deploy check / smoke test green.
  5. Official Plugin Check green.
  6. Workers pytest green (where applicable).
  7. Commit attribution: `Co-Authored-By: Wugweb <admin@wugweb.com>`.

### 9.2 Independent Artifact Packaging
Each product is built and packaged independently:
* `dist/kits-by-wugweb.zip` (Theme)
* `dist/kits-core.zip` (Core Companion)
* `dist/rankweb.zip`, `dist/mailweb.zip`, `dist/directoryweb.zip`, etc.
* Monorepo **never** outputs a monolithic combined WordPress ZIP.

---

## 10. Multi-Tenant Fleet Deployment Map

```
                                  CANONICAL SOURCE REPO
                            https://github.com/wugweb-git/kits
                                    (Branch: kits-suite)
                                             │
      ┌──────────────────────────────┬───────┴──────────────────────┬──────────────────────────────┐
      ▼                              ▼                              ▼                              ▼
TENANT 0: ANIMAL WRITES        TENANT 1: IKAN HEALTH          TENANT 2: MUSICLINKD           HUB: WUGWEB.COM
• URL: animalwrites.in         • URL: ikanhealth.com          • URL: musiclinkd.com          • URL: wugweb.com
• Theme: kits-by-wugweb        • Theme: kits-by-wugweb        • Theme: kits-by-wugweb        • Role: Foundry & Token Engine
• Plugins: All 7 Companion     • Plugins: kits-core, rankweb  • Plugins: kits-core, mailweb  • SaaS Portal & Documentation
• Preset: Editorial / Nature   • Preset: Eco / Health Clean   • Preset: Modern Dark Tech     • Skills & Agent Registry
```
