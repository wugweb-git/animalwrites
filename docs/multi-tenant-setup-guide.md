# Multi-Tenant Setup & Brand Customization Guide (1 Default + 3 Tenants)

**Site Architecture:** Kits by Wugweb Universal Block Theme & Companion Plugins  
**Target Matrix:** 1 Default Instance (Animal Writes / Neutral Kits) + 3 Tenant WordPress Deployments  
**Repository:** [https://github.com/wugweb-git/kits](https://github.com/wugweb-git/kits) (Branch: `kits-suite`)  
**Status:** Live & Documented (November 2026)

---

## 1. Architectural Strategy: Single Codebase, Zero-Touch Multi-Tenancy

In multi-tenant WordPress configurations, branching the theme or creating heavy child themes produces significant maintenance overhead and divergence. Kits by Wugweb eliminates this by decoupling **Structure & Layout** from **Brand Tokens & Assets**:

```
+---------------------------------------------------------------------------------+
|                               Block Theme Architecture                          |
|  - HTML Block Templates (index.html, single.html, archive.html, etc.)           |
|  - HTML Block Parts (header.html, footer.html, sidebar.html)                    |
|  - Block Patterns (heroes, card grids, FAQs, search, CTAs)                      |
+---------------------------------------------------------------------------------+
                                      |
         +----------------------------+----------------------------+
         |                                                         |
         v                                                         v
+-----------------------------------+     +---------------------------------------+
|   WordPress Style Variations      |     |  Kits Brand Customizer (wp_options)   |
|   (Appearance > Editor > Styles)  |     |  (admin.php?page=kits-brand)          |
|   - Default (Neutral)             |     |  - 1-Click Tenant Presets             |
|   - Animal Writes                 |     |  - Custom CSS Variable Overrides      |
|   - Slate                         |     |  - Live Editor & Frontend Injection   |
|   - Forest                        |     |  - JSON Export / Import Portability   |
+-----------------------------------+     +---------------------------------------+
```

---

## 2. Supported Tenant Profiles (1 Default + 3 Tenants)

| Setting | Default (Animal Writes / Neutral) | Tenant 1: Modern Tech & SaaS | Tenant 2: Editorial & Publication | Tenant 3: Eco & Community Wellness |
| :--- | :--- | :--- | :--- | :--- |
| **Accent (`--kit-color-accent`)** | `#FFBE1A` (Gold) | `#2563EB` (Electric Blue) | `#C2410C` (Terracotta) | `#059669` (Forest Emerald) |
| **Accent Contrast (`--kit-color-accent-ink`)**| `#0062b8` | `#1D4ED8` | `#7C2D12` | `#065F46` |
| **Canvas Background (`--kit-color-bg`)** | `#F4F4F4` | `#F8FAFC` (Slate Canvas) | `#FDFBF7` (Cream Canvas) | `#F4FBF7` (Mint Canvas) |
| **Surface Raised (`--kit-color-raised`)** | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` |
| **Headings & Ink (`--kit-color-ink`)** | `#191919` | `#0F172A` | `#1C1917` | `#064E3B` |
| **Body Text (`--kit-color-text`)** | `#2E2E2E` | `#334155` | `#44403C` | `#1E3A2F` |
| **Display Font Stack** | System Sans / Inter | `'Inter', system-ui, sans-serif` | `'Georgia', serif` | `'Plus Jakarta Sans', sans-serif` |
| **Control Radius (`--kit-radius-control`)** | `8px` | `6px` | `2px` (Sharp) | `9999px` (Pill) |
| **Card Radius (`--kit-radius-card`)** | `12px` | `10px` | `4px` | `16px` (Soft Organic) |
| **Max Wide Grid Width** | `1200px` | `1280px` | `1160px` | `1200px` |
| **Section Spacing** | `48px` | `56px` | `64px` | `48px` |

---

## 3. How to Set Up a New Tenant Install (Minimal Setup)

To install Kits on a new WordPress site (e.g. Tenant 1, 2, or 3):

### Step 1: Install Theme
1. Clone or copy `themes/kits-by-wugweb` from the Kits monorepo into the tenant's `wp-content/themes/kits-by-wugweb`.
2. Activate the theme under **Appearance → Themes**.

### Step 2: Install Companion Plugins
From `plugins/` in the Kits monorepo, install and activate the required suite:
- `kits-core` (Mandatory: provides the Brand Customizer, Block Bindings engine, form security, and tokens bridge)
- `directoryweb` (Optional: if the tenant runs directories or organization profiles)
- `rankweb` (Optional: SEO, schema, and sitemaps)
- `mailweb` (Optional: newsletters and email subscriptions)
- `sourceweb` (Optional: content attribution and sourcing)
- `insightweb` (Optional: cookieless analytics and search telemetry)
- `planweb` (Optional: editorial scheduling)

### Step 3: Brand the Tenant in 1 Click
1. In wp-admin, navigate to **Appearance → Brand Tokens** (or **Kits → Brand Customizer**).
2. Click **Apply Preset** under the desired tenant profile (e.g., *Tenant 1: Modern Tech & SaaS*), or fine-tune individual colors and corner radii.
3. Click **Save Brand Tokens**.
4. The system:
   - Compiles the CSS custom properties `:root { ... }`.
   - Bridges values directly into WordPress Gutenberg block presets (`--wp--preset--color--primary`, etc.).
   - Injects the styles in `wp_head` for the public site and `.editor-styles-wrapper` for the Site Editor.
   - Automatically purges LiteSpeed / page cache.

---

## 4. Replicating Settings Across Sites (Export / Import)

Under the **Tenant Token Backup & Migration** card on the Brand Customizer screen:
1. Copy the JSON block from the source site (e.g., Default or Staging).
2. On the target tenant site, paste the JSON into the **Import Configuration** box.
3. Click **Import & Apply Configuration**.

---

## 5. Shortcodes vs. Native Block Replacements

When designing block templates for tenants, follow these mapped Gutenberg block conventions:

| Old / Monolithic Pattern | Native Gutenberg Replacement | Plugin Owner |
| :--- | :--- | :--- |
| `[aw_newsletter]` | `<!-- wp:mailweb/newsletter {"layout":"card"} /-->` | **MailWeb** |
| `[aw_find_help]` | `<!-- wp:directoryweb/search {"compact":true} /-->` | **DirectoryWeb** |
| `[aw_org_people]` | `<!-- wp:directoryweb/people /-->` | **DirectoryWeb** |
| `[aw_countdown]` | `<!-- wp:kits-core/countdown /-->` | **Kits Core** |
| `[aw_category_hub]` | `<!-- wp:kits-core/category-hub /-->` | **Kits Core** |
| `[aw_source_row]` | `<!-- wp:sourceweb/attribution /-->` | **SourceWeb** |
| Dynamic PHP Post Meta | Native Paragraph / Heading with Block Bindings `source:"kits/post-field"` | **Kits Core** |

*Note: Monolithic dynamic blocks (`animal-writes/*`) and shortcodes remain fully registered in `hub-blocks.php` and plugin controllers as backwards-compatible fallbacks, ensuring zero breakage for existing content.*
