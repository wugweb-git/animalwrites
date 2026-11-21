# WugWeb Modular Master Blueprint & Audit

> **Status:** Complete, deduplicated compilation of all 4 threads, reconciled into a unified monorepo and two-artifact runtime architecture. Nothing omitted.

---

## Module 1: Monorepo & Two-Artifact Runtime Architecture

### 1.1 Monorepo Directory Layout (`wugweb-monorepo`)
Keeping everything in one repository enables atomic updates across design tokens, theme variations, companion plugin hooks, and worker relays, while CI/CD packages the theme and plugin into strictly independent runtime artifacts:

```text
wugweb-monorepo/
├── packages/
│   ├── tokens/                  # Single source of truth: tokens.json, semantic slugs, theme.json v3 base
│   ├── foundry-ui/              # Versioned static Storybook & visual QA previews
│   └── worker-relay/            # Cloudflare Worker (validates per-tenant keys, sanitizes, opens PRs/Gists)
├── apps/
│   ├── wugweb-kits/             # 100% Declarative WordPress Block Theme
│   └── wugweb-core/             # PHP-First Companion Plugin (all logic, syncing, token locks, admin UI)
├── scripts/                     # Build/zip packagers, WP Playground blueprints, validation scripts
├── package.json                 # Workspace root (pnpm / npm workspaces / Turborepo)
└── .github/workflows/           # CI pipelines (Theme Check, Plugin Check, Playground PR preview, Storybook deploy)
```

### 1.2 The Foundry (`wugweb-design-system`)
* **Single Source of Truth:** Hosted on GitHub paired with Cloudflare R2/Pages and versioned static Storybook builds (`refs` / Storybook Composition or `storybook-addon-versions`, triggered via GitHub Actions on release tags like `v1.0.0`, `v1.1.0`) for zero-WordPress visual QA.
* **Master Assets:** Stores `tokens.json` (mapped to `theme.json` v3 schema) and approved block patterns (`.html`, `.json`, or `.php` files, or GitHub Gists).
* **Zero-Hosting QA & PR Previews:** Uses **WordPress Playground** (`playground.wordpress.net`) via WebAssembly (`blueprint.json`, URL parameters, two-way **Import from GitHub / Export to GitHub PR**, and `WordPress/action-wp-playground-pr-preview` on GitHub PRs) or local **`wp-env` / LocalWP** so there is zero need to pay for or maintain a separate live hosted WordPress staging site.

### 1.3 The Block Theme (`wugweb-kits` — 100% Declarative Visual Shell)
* **Files Included:** `style.css`, minimal `theme.json` (v3), `styles/*.json` (curated variations such as `dark.json`, `sharp.json`, `glassmorphic.json`), `parts/*.html` (headers/footers), `templates/*.html` (`index.html`, etc.), and `patterns/*.php` (only when bundling static patterns).
* **Compliance Rules:**
  * Zero `functions.php` business, sync, or UI logic.
  * Zero bundled `.zip` files.
  * Zero forced plugin installers (`Plugin_Upgrader` / TGMPA removed from theme).
  * Passes official **Theme Check** plugin and WordPress.org Theme Review Guidelines cleanly.
* **Excluded Tooling:** The **Create Block Theme** plugin was explicitly rejected by you and is excluded from the stack.

### 1.4 The Companion Plugin (`wugweb-core` — PHP-First System Orchestrator)
* **Verification & Scope:** Verified via **Plugin Check (PCP)**; not restricted by single-block Block Directory rules.
* **Responsibilities:** Houses 100% of PHP hooks, cloud manifest fetching/caching, runtime token injection/locking, pattern registration, two-way tenant capture, onboarding/admin UI, and custom interactive blocks (`register_block_type` for sliders, mega-menus, tabs, accordions, and theme toggles so tenant content never breaks if they switch themes).
* **Admin Placement:** Nested exclusively under **WordPress Admin → Appearance** via `add_theme_page()` (`appearance_page_wugweb-identity-tokens`) so there is zero top-level sidebar bloat, with direct deep-links into Site Editor views (`/wp-admin/site-editor.php?path=%2Fwp_global_styles`).

---

## Module 2: Design Tokens (`theme.json` v3), 4-Layer Hierarchy & Day/Night Mode

### 2.1 Boundary Between Admin UI Tokens and Front-End Theme Tokens
* **WordPress Design System Tokens (`--wpds-*`):** Defined in `@wordpress/theme` (`design-tokens.css` and `ThemeProvider`, e.g., `--wpds-color-background-interactive-brand-strong`, `--wpds-dimension-padding-2xl`). Strictly for styling WordPress admin interfaces, editor chrome, and custom React plugin UIs—**never** for front-end block themes.
* **Front-End Block Theme Tokens (`theme.json` v3):**
  * **Editor-Facing Presets (`settings.*`):**
    * `settings.color.palette` $\rightarrow$ `--wp--preset--color--{slug}`
    * `settings.typography.fontSizes` $\rightarrow$ `--wp--preset--font-size--{slug}`
    * `settings.spacing.spacingSizes` $\rightarrow$ `--wp--preset--spacing--{slug}`
    * `settings.shadow.presets` $\rightarrow$ `--wp--preset--shadow--{slug}`
    * `settings.border` $\rightarrow$ per-block border width, style, radius, and color controls.
  * **Private / Custom Tokens (`settings.custom`):** Internal token registry for primitive ramps, radii, blurs, strokes, shadows, and line heights without cluttering editor UI panels $\rightarrow$ `--wp--custom--{path}` (e.g., `--wp--custom--blur--md`, `--wp--custom--stroke--default`, `--wp--custom--radius--md`).

### 2.2 Standardized Semantic Slugs & Rules
* Separate primitives in `settings.custom` from semantic roles in `settings.color.palette` and `styles`.
* Lock semantic palette slugs across the foundry, variations, and patterns: `base`, `surface`, `contrast`, `muted`, `primary`, `accent`, and `border` (never raw color names like `"white"` or `"black"`).
* Always reference `var(--wp--preset--...)` and `var(--wp--custom--...)` instead of hardcoded hex or pixel values, and never rename slugs across updates or style variations.
* Disable default WordPress bloat (`"defaultPalette": false`, `"defaultPresets": false` where needed, and `"custom": false` on color pickers when locking tenant palettes).

### 2.3 Reference `theme.json` v3 Token Schema

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {
    "custom": {
      "color": {
        "brandPrimary": "#1d4ed8"
      },
      "radius": {
        "md": "8px"
      }
    },
    "color": {
      "palette": [
        {
          "slug": "primary",
          "color": "var(--wp--custom--color--brand-primary)",
          "name": "Primary"
        }
      ]
    }
  },
  "styles": {
    "blocks": {
      "core/button": {
        "border": {
          "radius": "var(--wp--custom--radius--md)"
        }
      }
    }
  }
}
```

### 2.4 The 4-Layer `theme.json` Hierarchy & Tenant Baseline Injection
1. **Layer 1 — Default (`wp_theme_json_data_default`):** WordPress Core defaults.
2. **Layer 2 — Blocks (`wp_theme_json_data_blocks`):** Individual block defaults.
3. **Layer 3 — Theme (`wp_theme_json_data_theme`):** The theme's registered source of truth. **Anything injected here becomes the baseline when a user clicks "Reset styles" or "Reset to defaults" in the Site Editor.**
4. **Layer 4 — User (`wp_theme_json_data_user`):** Database overrides saved from the Site Editor UI (`wp_global_styles` CPT). Clicking "Reset styles" wipes Layer 4 and reverts to Layer 3 (`theme`).

Because WordPress Core lacks a native UI to edit raw `settings.custom` tokens (like `blur` or `stroke`) or redefine `settings.shadow.presets` CSS values in place (though WordPress 7.2+ introduced UI editing for Text Shadow presets under *Styles → Typography → Text shadow presets*), storing tenant token values in `wp_options` (`tenant_design_tokens` / `wugweb_tokens`) with locked slugs and injecting them via **`wp_theme_json_data_theme`** ensures:
* Variable names (`--wp--custom--blur--md`, `--wp--preset--shadow--card`) never break.
* Native Site Editor dropdowns and Style Book previews populate automatically.
* **"Reset to defaults"** restores the tenant's registered brand baseline rather than wiping it out.

```php
add_filter( 'wp_theme_json_data_theme', function( $theme_json ) {
    $tenant_tokens = get_option( 'tenant_design_tokens', [] );

    if ( empty( $tenant_tokens ) ) {
        return $theme_json;
    }

    $new_data = [
        'version'  => 3,
        'settings' => [
            // 1. Update custom registry tokens (stroke, blur, etc.)
            'custom' => [
                'blur'   => [
                    'md' => sanitize_text_field( $tenant_tokens['blur_md'] ?? '8px' ),
                ],
                'stroke' => [
                    'default' => sanitize_text_field( $tenant_tokens['stroke_default'] ?? '1px' ),
                ],
            ],
            // 2. Update registered shadow presets without changing slugs
            'shadow' => [
                'presets' => [
                    [
                        'name'   => 'Card Elevation',
                        'slug'   => 'card', // Locked slug keeps --wp--preset--shadow--card intact
                        'shadow' => sanitize_text_field( $tenant_tokens['shadow_card'] ?? '0 4px 12px rgba(0,0,0,0.1)' ),
                    ],
                ],
            ],
        ],
    ];

    return $theme_json->update_with( $new_data );
} );
```

### 2.5 Zero-Flash Day / Night Mode
* **Semantic Tokens + Style Variation:** Define `base`, `surface`, `contrast`, `muted`, `primary`, and `border` in `theme.json`, and ship `styles/dark.json` for sites wanting dark mode as their default.
* **CSS Custom Property Overrides (`assets/css/dark-mode.css`):**

```css
:root[data-theme="dark"],
.is-dark-theme {
  --wp--preset--color--base: #0b0d10;
  --wp--preset--color--surface: #15181e;
  --wp--preset--color--contrast: #f3f4f6;
  --wp--preset--color--muted: #9ca3af;
}
```

* **Zero-FOUC Toggle:** Pair a lightweight **Interactivity API** header toggle block/pattern with a tiny inline `wp_head` script that checks `localStorage` first and falls back to `window.matchMedia('(prefers-color-scheme: dark)')` before first paint so reloading in dark mode produces zero white flash.

---

## Module 3: WordPress Core APIs, Cloud Patterns & Admin UX

### 3.1 Cloud Pattern Delivery (`register_block_pattern` + Transients API)
* Fetch pattern manifests from GitHub/Cloudflare R2 via `wp_remote_get()` and cache with `set_transient( 'wugweb_cloud_manifest', ... )`.
* On `init`, loop through tenant-enabled pattern slugs (`get_option( 'wugweb_enabled_patterns' )`) and register them in memory via `register_block_pattern()` with clean categories (`register_block_pattern_category` such as *Heroes*, *Features*, *Social Proof*, *CTAs*)—zero database writes.
* Strip cluttered default patterns via `remove_theme_support( 'core-block-patterns' )`.

### 3.2 End-to-End Customer Journey & Appearance Dashboard (`add_theme_page`)
1. **Post-Purchase & Activation:**
   * Order Confirmation Page acts as an Action Hub: prominent `.zip` download button (with server headers forcing `.zip` download so Safari doesn't auto-unzip), one-click copy license key, 3-step micro-guide (`1. Download ZIP → 2. Upload to WP → 3. Paste Key`), plus an automated welcome email backup.
   * Upon single-plugin activation (skipping bulk activations), `wp_safe_redirect()` sends the user to the onboarding screen.
2. **4-Step Streamlined Onboarding Flow:**
   * **Step 1 — Brand & License Verification:** High-contrast branded screen validating the license key and unlocking cloud token/pattern sync.
   * **Step 2 — Style Variation & Starter Architecture:** Visual cards for `styles/*.json`, starter page checkboxes (`Home`, `About`, `Pricing`, `Contact`, `Blog`) generated via `wp_insert_post()` with clean `<!-- wp:pattern {"slug":"..."} /-->` markup (zero shortcode bloat), and an optional sample media toggle (ON for fresh installs, OFF for existing sites).
   * **Step 3 — Global Brand Tokens (Live Split-Screen Preview):** Brand Logo upload (`wp.media` setting `site_logo` option for `wp:site-logo`), Primary/Accent Colors, Font Pairing, and custom tokens (`blur`, `stroke`, `shadow`) saved to `tenant_design_tokens` / `wugweb_tokens` (`wp_theme_json_data_theme`), with a live `<iframe>` preview on the right.
   * **Step 4 — Unified Post-Setup Command Center (`Appearance → Site Identity & Tokens`):**
     * Consolidates **Site Identity** (`blogname`, `blogdescription`, `site_logo`, `site_icon` via `wp.media`), **Brand Design Tokens & Presets** (`wp-color-picker` + token sliders), **Selective Pattern/Block Checkboxes**, **Asset/Block Performance Toggles**, **Foundry Sync Controls** (Tenant ID, manual *Sync Now* cache-clear button, sync status), a **Reset / Re-run Wizard** button, and **1-Click Deep-Links** into Site Editor screens (`Header`, `Footer`, `Global Styles`).
     * Keep admin notices dismissible and confined to plugin screens.
3. **Optional Site Editor Lock:**
   * Hide `site-editor.php` for locked-down tenant environments via `remove_submenu_page( 'themes.php', 'site-editor.php' )` + `admin_init` redirect.

### 3.3 Conditional Assets & Modern Block APIs
* **Conditional CSS:** Load block/pattern stylesheets only when rendered on a page via `wp_enqueue_block_style()`.
* **Block Bindings API:** Connect dynamic data sources natively via `register_block_bindings_source()`.
* **Block Styles & Variations:** Register visual style pills in the block sidebar via `register_block_style()` (e.g., `Card`, `Glass`, `Bordered`, `Elevated` on `core/group`; `Primary Glow`, `Ghost`, `Pill Arrow` on `core/button`) so buyers never need to write CSS classes.
* **Interactivity API:** Power lightweight front-end interactions (toggles, accordions, tabs, sliders) without heavy React client bundles.

---

## Module 4: Two-Way Tenant Pattern Capture, Extraction & Governance

### 4.1 Database vs. Repository Reality
* Visual edits made in WordPress (pages, `wp_global_styles`, custom patterns saved as `wp_block` CPTs) live in each tenant's MySQL database—not in GitHub. GitHub only tracks repository files (`theme.json`, `/patterns/*.php`, `/templates/*.html`, `/parts/*.html`).

### 4.2 Automated Two-Way Capture (`save_post_wp_block` + Cloudflare Worker Relay)
* `wugweb-core` hooks into `save_post_wp_block`. When a tenant creates and saves a pattern inside WordPress, the plugin fires a background `wp_remote_post()` webhook carrying `$post->post_title` and `$post->post_content` (raw block HTML).
* **Security Relay:** The webhook POSTs to a lightweight **Cloudflare Worker** authenticated with a per-tenant API key. The Worker validates and sanitizes the payload and uses a server-side GitHub credential to open a draft Pull Request or Gist—ensuring no GitHub Personal Access Token ever ships inside tenant-installed code.

### 4.3 Manual & Multisite Pattern Extraction Methods
* **WordPress Multisite (Super Admin):** Query `wp_X_posts` (`post_type = 'wp_block'`) across subsites or scan via WP-CLI.
* **WP Admin JSON Export/Import:** Navigate to *Appearance → Editor → Patterns* (or `/wp-admin/edit.php?post_type=wp_block`) $\rightarrow$ `⋮` $\rightarrow$ **Export as JSON**, then **Import from JSON**.
* **Block Editor Copy-Paste:** Select the outer container block in the Block Editor $\rightarrow$ `⋮` $\rightarrow$ **Copy** (or open Code Editor via `Ctrl+Shift+Alt+M` / `Cmd+Shift+Alt+M` to copy raw `<!-- wp:... -->` markup) and paste into a plain-text `/patterns/*.php` file (with `Title:`, `Slug:`, `Categories:` PHP header) in your repo.
* **Modified Theme ZIP:** Diff `theme.json`, `/patterns/*.php`, `/templates/*.html`, and `/parts/*.html` in VS Code.

### 4.4 Pre-Promotion Clean-Up & Ownership Checklist
1. **Verify Permission / ToS Rights:** Custom layouts and copy built by tenants are Customer Data / Customer IP unless your Terms of Service / MSA explicitly grant a reuse license. Always obtain permission and generalize the layout before shipping publicly.
2. **Swap Media URLs:** Re-upload and replace any image URLs pointing to `://tenant-site.com...` with local theme/foundry assets (`get_theme_file_uri(...)`).
3. **Strip Tenant-Specific Content:** Replace business copy, logos, and custom CSS classes with generic placeholders and semantic `--wp--preset--*` / `--wp--custom--*` variables.

---

## Module 5: Background Automation, Cron Jobs, Hooks & Enterprise Scale

### 5.1 Cron vs. Event Hooks (Webhooks)

| Dimension | Cron (Scheduled Polling) | Event Hook / Webhook (Event-Driven Push) |
| --- | --- | --- |
| **Trigger** | Clock / calendar (`crond` + 5-field `crontab`: `minute hour day-of-month month day-of-week`, e.g., `0 6 * * *`). | Action / event (publish post, form submit, payment, product update, pattern save). |
| **Resource Profile** | Sleeps via OS hardware interrupts using minimal RAM/CPU while idle; spikes only during heavy runs or overlaps. | Dormant code on disk until triggered; high burst concurrency risk if unhandled. |
| **Latency** | Delayed until next scheduled tick (1-minute minimum standard cron granularity). | Real-time (milliseconds). |
| **Capabilities & Limits** | Executes scripts, DB queries, `curl`, file cleanups headless; cannot react to live events or run sub-minute natively; needs script logic for complex calendar rules (last Friday, holidays) or retries. | Deterministic `If-This-Then-That` + JSON payloads; requires source system to emit webhooks. |
| **WordPress Nuance** | **WP-Cron** checks due tasks on incoming HTTP traffic (no WP-Cron PHP execution when traffic is zero, though web/DB processes remain). | WordPress action hooks (`save_post_wp_block`, `publish_post`, etc.) fire synchronously unless offloaded to a queue/background request. |

### 5.2 Traditional Server Cron vs. GitHub Actions (`schedule:`)

| Feature | Traditional Server (VPS / Cloud) | GitHub Actions (Scheduled Workflows) |
| --- | --- | --- |
| **Execution Environment** | Always-on Linux/Windows server. | Temporary virtual machines spun up by GitHub. |
| **Cost** | 24/7 server hosting cost. | Free tier (up to 2,000 mins/month on private repos; unlimited on public repos). |
| **Timing Accuracy** | Exact to the second. | Best-effort; can experience 5–30+ minute delays during peak GitHub queue load. |
| **Best For** | Local DB backups, private server file cleanups, exact-second schedules. | Repo updates, external API calls, daily alerts, scheduled scrapes (0% load on your server). |

### 5.3 Crawling & Overlap Prevention Patterns
* **External Sites You Don't Control:** Must use **Cron polling** with `flock` lock files (to prevent slow crawls overlapping), randomized jitter (`sleep(random(1, 30))`) to avoid robotic signatures, and appropriate tooling (`curl` / Python `requests` / `BeautifulSoup` for static HTML vs. `Playwright` / `Puppeteer` for JS-rendered pages).
* **Your Own App or Webhook-Capable Platforms (Shopify, GitHub, Stripe, WordPress):** Use **Hook $\rightarrow$ Queue $\rightarrow$ Worker** (processing 1–3 items concurrently) so traffic bursts never spawn dozens of colliding processes.
* **Three Core Overlap Safeguards:**
  1. **Lock Files / Mutex Locks:** Temporary lock file (`task.lock`), DB flag, or Linux `flock`.
  2. **Message Queues:** Redis, RabbitMQ, or AWS SQS buffering events for sequential/bounded workers.
  3. **Debouncing:** Resetting a short timer on rapid bursts (e.g., rapid document/pattern saves) until activity settles.

### 5.4 The 7-Layer Scalable Automation Stack ("Scheduled Actions" to Enterprise)
1. **Event Hook:** Captures an event and writes a task row + target timestamp into a database queue table (enabling relative timers such as "48 hours after signup").
2. **Queue:** Buffers concurrent spikes safely without CPU/RAM exhaustion.
3. **Cron Runner:** Wakes periodically and processes due rows in controlled batches with locking and logging (e.g., WooCommerce Action Scheduler).
4. **Dedicated Worker Pools:** Decoupled worker processes/containers pulling from queues with bounded concurrency limits so slow tasks never block other queues.
5. **State Machines / Workflow Orchestration:** Tracks multi-step workflow states (`pending → running → completed / failed`) across distributed steps (e.g., Temporal, Airflow).
6. **Retries with Exponential Backoff & Dead-Letter Queues (DLQs):** Retries transient external failures with increasing delays and isolates poison/repeatedly failing messages in a DLQ.
7. **Event Streams / Pub-Sub:** Append-only event logs (e.g., Kafka, EventBridge) enabling multiple independent consumers to react reliably to domain events.

---

## Module 6: Consolidated Audit of Fake, Dubious, Contradictory & Incomplete Claims

| Item / Claim Across Threads | Audit Verdict | Actual Technical Reality & Resolution |
| --- | --- | --- |
| **Theme `Plugin_Upgrader` & React Wizard vs. Declarative Theme** *(Thread 1 early turns & Thread 2 `3:25–3:27 pm`)* | **CONTRADICTORY** | Putting `Plugin_Upgrader` 1-click installers and React setup UI inside a theme violates WordPress.org Theme Review Guidelines ("plugin territory") and breaks the two-artifact split. Keep `wugweb-kits` 100% declarative and house all logic/UI in `wugweb-core`. |
| **Create Block Theme Plugin** *(Thread 2 `3:27 pm` & `3:34–3:36 pm`)* | **USER-REJECTED / CONTRADICTORY** | Explicitly rejected by you in Thread 1. Excluded from the stack; write `/patterns/*.php` directly or register patterns in `wugweb-core`. |
| **Direct GitHub API `wp_remote_post` from `save_post_wp_block` on Tenant Sites** *(Thread 1 early turn)* | **SECURITY RISK / DUBIOUS** | Shipping a GitHub Personal Access Token inside tenant-installed plugin code exposes repository write access to every tenant admin. Route through a Cloudflare Worker relay using a per-tenant API key. |
| **`wp_theme_json_data_user` vs. `wp_theme_json_data_theme` for Tenant Baselines** *(Thread 3 `3:09 pm` vs. `3:10 pm`)* | **SELF-CORRECTED** | `wp_theme_json_data_user` (Layer 4) gets wiped when a user clicks "Reset to defaults" in Site Editor. Filtering **`wp_theme_json_data_theme`** (Layer 3) updates the registered baseline so "Reset to defaults" restores the tenant's custom token values. |
| **Sample JSON `"$schema": "https://wp.org"`** *(Thread 3 opening snippet)* | **INACCURATE URL** | WordPress `theme.json` schemas live under `https://schemas.wp.org/...` (e.g., `https://schemas.wp.org/trunk/theme.json`), not `https://wp.org`. |
| **"Copy-Paste Block Markup (Works even from the front end)"** *(Thread 2 `3:34 pm`)* | **MISLEADING FOR PUBLIC FRONT END** | The Code Editor shortcut (`Ctrl+Shift+Alt+M` / `Cmd+Shift+Alt+M`) is a Block Editor tool, not a public front-end visitor view; copying rendered DOM HTML from a live webpage does not yield raw `<!-- wp:... -->` block markup. |
| **Frost & BlockPress Ranked Top-5 by Active Directory Installs** *(Thread 1 early turn)* | **INACCURATE (CORRECTED)** | Frost and BlockPress are niche/developer favorites rather than top-5 by active WordPress.org installs alongside Twenty Twenty-Five, Twenty Twenty-Four, Extendable, Spectra One, and Ollie. |
| **"Gutenberg is separate / needs to be installed"** *(Thread 1 early confusion)* | **CLARIFIED** | The standalone Gutenberg plugin is a bi-weekly beta sandbox and should not be installed on production tenant sites; the Block Editor engine is built into WordPress Core. |
| **WP-Cron "uses literally 0% CPU and RAM" When Idle** *(Thread 4 `1:00 pm`)* | **SLIGHTLY OVERSTATED** | Zero WP-Cron PHP execution occurs without HTTP traffic, though the hosting server, PHP-FPM, database, and OS still consume baseline idle memory/processes. |
| **Truncated Sentences & Duplicate Pasted Blocks** *(Thread 2 `3:29 pm`, Thread 3 `3:11 pm`, Thread 4 `1:05 pm`)* | **INCOMPLETE / REDUNDANT** | Deduplicated across all 4 threads, with cut-off sections (`--wpds-*` vs. `theme.json`, FSE touchpoints/Day-Night mode, and Layers 4–7 enterprise queue/worker architecture) completed cleanly. |

---

## Module 7: Final Actionable Recommendations

1. **Enforce the Monorepo + Strict Two-Artifact Contract:** Keep `apps/wugweb-kits` 100% declarative so it passes **Theme Check**, and keep all PHP hooks (`wp_theme_json_data_theme`, `register_block_pattern`, `save_post_wp_block`), `add_theme_page()` UI, and custom blocks inside `apps/wugweb-core` (validated with **Plugin Check / PCP**).
2. **Lock Semantic Token Slugs & Inject via `wp_theme_json_data_theme`:** Standardize on `base`, `surface`, `contrast`, `muted`, `primary`, `accent`, and `border` plus locked `--wp--custom--*` tokens (`blur`, `stroke`, `radius`, `shadow`) so tenant customizations, Day/Night mode (`styles/dark.json` + `:root[data-theme="dark"]` + `wp_head` pre-paint check), and Site Editor "Reset to defaults" work seamlessly.
3. **Protect Webhooks, Previews & Async Workers:** Route tenant pattern-capture webhooks through `packages/worker-relay` (Cloudflare Worker), run PR previews via WordPress Playground (`WordPress/action-wp-playground-pr-preview`) + Storybook without maintaining a paid staging site, and guard async sync/crawl pipelines with `flock`/DB locks, queues, idempotency keys, and bounded worker concurrency.