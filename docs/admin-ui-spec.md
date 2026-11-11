# Kits admin UI: spec

Status: draft for review, 11 Nov 2026. Spec only. Nothing here is built yet.

Scope: the Kits menu and the screens Animal Writes adds to it. The plugin dashboards (RankWeb, SourceWeb, MailWeb, Directory) are specified separately. This document only names where items moved out of Kits land in them.

---

## A. UI standard

All Kits screens follow one pattern, modelled on a theme-options app: one header, one section list, values edited where they are shown.

1. **App header** (every Kits screen): site name, Kits version, Kits Core version, a **Save** button (disabled until something changes), and a "Saved" or error message beside it. Save works on the whole screen, not one box at a time.
2. **Menu and tabs are the same list.** The six items in the left WordPress menu are the six tabs across the header. One source: filter `kits_admin_tabs` (kept), order filter `kits_menu_order` (kept).
3. **Section list** on the left of each screen, with collapsible groups (for example Theme Options → Header → Announcement bar). The URL keeps the section (`#header/announce`) so a link opens the right box.
4. **Values are editable where they are shown.** No read-only panels that say "change this elsewhere", except the Design system (read-only on purpose) and items WordPress owns (Font Library, Site Editor), which get a direct link.
5. **"Reset to theme"** on every field that has a theme default: a small link beside the field. It removes the saved value, so the theme default shows again.
6. **Built with WordPress packages**: `@wordpress/components`, `@wordpress/dataviews` (for lists and tables), `@wordpress/data`, `@wordpress/api-fetch`; built with `@wordpress/scripts`; **one script per screen**, loaded only on that screen.
7. **Storage stays native.** Settings are registered with `register_setting( …, [ 'show_in_rest' => [ 'schema' => … ] ] )` and saved through `/wp/v2/settings`. Design values live in Global Styles. Identity uses core options. Nothing gets a new storage format.
   - Core options: `blogname`, `blogdescription`, `site_logo`, `site_icon`.
   - Global Styles: palette, fonts, sizes, spacing, element styles.
   - `kits_theme_options`: Kits switches and choices.
   - `kits_page_defaults`: per content-type and site-wide layout.
   - `kits_option()` keeps its lookup order: post meta `_kits_{key}` → `kits_page_defaults` (context, then `_site`) → `kits_theme_options` → theme.json `custom.kits` → default → filter `kits_option`.
   - Export, import and the `kits/v1/settings` REST route keep working with the same three parts (global_styles, options, patterns).
8. **Notices**: only on a Kits screen's own pages, always dismissible, using core `Notice` from `@wordpress/components`. No Kits notice on other admin screens.
9. **Accessibility: WCAG 2.2 AA.** Every control has a visible label. Focus is visible. Keyboard order follows the screen. Section list and tabs are real lists with `aria-current`. Saved and error messages are announced (`aria-live="polite"`). Targets are at least 24 × 24 px. Contrast is checked on the admin colours.
10. **Without JavaScript** each screen shows a short message and a link to export settings, so a broken script never locks the owner out.

---

## B. Kits menu: six items

| # | Menu item | Slug | Capability | Replaces |
|---|---|---|---|---|
| 1 | Home | `kits` | `manage_options` | Welcome, Site: Animal Writes (status part), Check site (page check, backup, joined files) |
| 2 | Theme Options | `kits-options` | `edit_theme_options` | Theme Options, Fonts, Contact links, the contrast tab of Design system |
| 3 | Settings | `kits-settings` | `manage_options` | Core Settings, Preview |
| 4 | Plugins | `kits-plugins` | `manage_options` | Plugins |
| 5 | Tools | `kits-tools` | `edit_theme_options` | Import / Export |
| 6 | Help | `kits-help` | `manage_options` | Docs, Design system, Edit screens, Theme parts, Site: Animal Writes notes |

No "Animal Writes" tab and no separate tenant menu. Animal Writes adds rows and sections into these six through filters (see E3).

### B1. Home

Sections:

1. **Versions and limits**: today's System Status rows (`kits_status_rows`, kept as the filter): theme version, Kits Core version, child theme (yes/no), WordPress, PHP, memory limit and WP memory limit (red below 256M), `max_input_vars`, time limit, upload limit. Red rows link to the fix.
2. **Site Health**: core Site Health status (good / should be improved / critical) with a link to Tools → Site Health.
3. **Page check**: last run time, the commit it ran on, pages checked, pages not 200 (each a link), a **Run now** button, and the note "runs again 2 minutes after each deploy". Moves from Check site.
4. **Backup**: last daily backup (time, size, file), kept for 14 days, next run (03:30 IST). Moves from Check site.
5. **Deployed commit**: the commit live now, when it was deployed, and when the cache was last purged.
6. **Joined files**: file count, kinds of page, last sweep, the cap. Moves from Check site.
7. **Plugin status**: one row per Wugweb plugin (Kits Core, SourceWeb, MailWeb, RankWeb, Directory): active or not, version, a link to its dashboard.
8. **Setup checklist**: today's "Start here" links turned into a list with ticks that work them out from the site (logo set, menus set, palette set, socials set, Search Console connected). No stored state.

### B2. Theme Options

Section list (groups are collapsible):

1. **Identity**: site title, tagline, logo, site icon.
2. **Header**: header part, sticky header, announcement bar, header menu.
3. **Footer**: footer part, phone bar (sticky bar), footer menu.
4. **Layout and spacing**: default sidebar, title, breadcrumbs; section spacing (48 / 70 / 96); breakpoints shown (767/768 and 1199/1200).
5. **Colours**: palette, colour sets, live contrast check.
6. **Typography**: fonts per role, sizes, link to the Font Library.
7. **Buttons**: button styles.
8. **Cards**: padding, gap, stacked card, side-by-side card, meta row.
9. **Content types**: single and archive for each public type, plus 404, Search, Authors.
10. **Socials**: the one list of profiles.
11. **Share bar**: networks.

Field-by-field table: section D1 below.

### B3. Settings

1. **Performance**: the eight speed switches, font preload, the joined-files cap.
2. **Compatibility**: old section classes.
3. **Preview access**: allowed IPs and devices.
4. **Features**: on/off switches for Kits features that other parts can turn off (one row per registered feature; see E3).

### B4. Plugins

The required/recommended list (`kits_plugins`), each with status and an install or activate button, plus "This site's plugins" (`kits_plugin_screens`), each with a link to its dashboard.

### B5. Tools

Export, check a file (dry run that lists what would change), import, restore defaults, put the copy back (undo the last import or restore).

### B6. Help

1. **Docs**: the kits-design and kits-features tables, plus the notes from Site: Animal Writes (WP-CLI commands, scheduled tasks, staging).
2. **Design system** (read-only): components, states, menus, tokens. The contrast tab moves to Theme Options → Colours.
3. **Developer**: Edit screens (mapping, schema, third-party) and Theme parts (until the child theme goes).

---

## C. Moves out of Kits and between screens

| Item now | Where it is now | Where it goes | Notes |
|---|---|---|---|
| Dates | Kits → Dates (`aw-dates`) | WordPress **Tools → Dates** | Same option `aw_team_dates`, same capability `edit_others_posts`, dashboard widget kept |
| Check site: page check | Kits → Check site | Kits **Home → Page check** | |
| Check site: backup | Kits → Check site | Kits **Home → Backup** | |
| Check site: joined files | Kits → Check site | Kits **Home → Joined files** | |
| Check site: CDN cache times | Kits → Check site | Kits **Settings → Performance** | `aw_cdn_ttl`, `aw_cdn_ttl_long` |
| Check site: email | Kits → Check site | **MailWeb → Dashboard** | `mailweb_pause`, `mailweb_last_error` |
| Check site: thumbnails | Kits → Check site | **SourceWeb → Dashboard** | `aw_thumb_map` |
| Check site: pictures (CC0) | Kits → Check site | **SourceWeb → Dashboard** | `aw_ov_quota`, `aw_cc0_log` |
| Check site: theme switch log | Kits → Check site | Kits **Home → Versions and limits** (last switch row, log behind a link) | capability `switch_themes` |
| Keys | Kits → Keys (`aw-keys`) | **RankWeb → Settings → Connected services** | `aw_keys`, `aw_key()` unchanged |
| Modules | Kits → Modules (`aw-modules`) | **SourceWeb → Settings** | `aw_social_on`, `aw_social_show` |
| Contact links | Kits → Contact links (`aw-elsewhere`) | Kits **Theme Options → Socials** | becomes the one socials source (D1.10) |
| Fonts | Kits → Fonts (`font-library.php`) | removed from menu | link inside Theme Options → Typography |
| Site: Animal Writes | Kits → Site: Animal Writes | removed | status rows to Home, notes to Help → Docs |
| Edit screens | Kits → Edit screens | Kits **Help → Developer** | |
| Theme parts | Kits → Theme parts | Kits **Help → Developer** | until the child theme goes |
| Design system: contrast | Kits → Design system → Contrast | Kits **Theme Options → Colours** | live, uses the palette being edited |
| Preview | Kits → Preview | Kits **Settings → Preview access** | |
| Core Settings | Kits → Core Settings | Kits **Settings** | |
| Import / Export | Kits → Import / Export | Kits **Tools** | |
| Welcome | Kits → Welcome | Kits **Home** | |

Every old slug redirects (E1).

---

## D. Field by field

Columns: **Field** · **Now** (screen and key) · **Goes to** · **Control** · **Default** · **Storage** · **Capability**.

Capability for every Theme Options field is `edit_theme_options` unless stated (today the screen needs `manage_options`; the change lets an editor with theme rights use it). Settings is `manage_options`.

### D1. Theme Options

#### D1.1 Identity

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Site title | Settings → General (core) | Identity | TextControl | — | `blogname` | `manage_options` |
| Tagline | Settings → General (core) | Identity | TextControl | — | `blogdescription` | `manage_options` |
| Logo | Site Editor (core) | Identity | MediaUpload + preview | none | `site_logo` | `edit_theme_options` |
| Site icon | Settings → General (core) | Identity | MediaUpload, square crop | none | `site_icon` | `manage_options` |

New screen for values that exist today. The core screens keep working; both write the same option.

#### D1.2 Header

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Header part | Theme Options → General → Header | Header | SelectControl (template parts in area `header`) | theme `header` | `kits_page_defaults._site.header` | `edit_theme_options` |
| Sticky header | not a setting today (theme CSS) | Header | ToggleControl | on (today's behaviour) | `kits_theme_options.sticky_header` (new key) | `edit_theme_options` |
| Announcement bar | Theme Options → General → Header (`announce`) | Header | ToggleControl | on | `kits_page_defaults._site.announce` | `edit_theme_options` |
| Header menu | Site Editor → Navigation (core) | Header | SelectControl of `wp_navigation` posts + "Edit" link | the menu in the header part | the header part's Navigation block `ref` | `edit_theme_options` |

#### D1.3 Footer

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Footer part | Theme Options → General → Footer | Footer | SelectControl (parts in area `footer`) | theme `footer` | `kits_page_defaults._site.footer` | `edit_theme_options` |
| Phone bar on/off | Theme Options → General → Footer (`sticky_bar`) | Footer | ToggleControl | off | `kits_page_defaults._site.sticky_bar` | `edit_theme_options` |
| Phone bar link | same (`sticky_href`) | Footer | TextControl, URL or `tel:` | empty | `kits_page_defaults._site.sticky_href` | `edit_theme_options` |
| Phone bar label | same (`sticky_label`) | Footer | TextControl | empty | `kits_page_defaults._site.sticky_label` | `edit_theme_options` |
| Footer menu | Site Editor → Navigation (core) | Footer | SelectControl of `wp_navigation` + "Edit" link | the menu in the footer part | the footer part's Navigation block `ref` | `edit_theme_options` |

#### D1.4 Layout and spacing

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Default sidebar | Theme Options → General → Layout | Layout | SelectControl (none / right / left) | theme default | `kits_page_defaults._site.sidebar` | `edit_theme_options` |
| Default title | same | Layout | SelectControl (show / hide) | show | `kits_page_defaults._site.title` | `edit_theme_options` |
| Default breadcrumbs | same | Layout | SelectControl (show / hide) | show | `kits_page_defaults._site.crumbs` | `edit_theme_options` |
| Section spacing small / medium / large | Theme Options → Visual → Spacing (read-only, theme.json) | Layout | three UnitControl fields | 48 / 70 / 96 px | Global Styles `settings.spacing.spacingSizes` (user origin) | `edit_theme_options` |
| Breakpoints | not shown | Layout | read-only text | 767/768, 1199/1200 | theme CSS (fixed) | — |
| Main column width | not shown | Layout | read-only text, link to Site Editor | theme.json `contentSize` / `wideSize` | Global Styles | — |

Breakpoints stay fixed in CSS. They are shown so the owner knows them; they are not editable (media queries cannot read custom properties).

#### D1.5 Colours

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Palette | Theme Options → Visual → Palette (read-only) | Colours | ColorPalette editor per slug (name fixed, colour editable) | theme.json palette | Global Styles `settings.color.palette` (user origin) | `edit_theme_options` |
| Colour sets | Theme Options → Visual → Colours (read-only) | Colours | one row per set: background, text, link, line | theme.json `custom.kits` sets | Global Styles `settings.custom.kits` (user origin) | `edit_theme_options` |
| Site colour set | **no UI today** (`kits_page_defaults._site.colours`) | Colours | SelectControl of sets | theme default | `kits_page_defaults._site.colours` | `edit_theme_options` |
| Contrast check | Design system → Contrast | Colours | live table: each text/background pair, ratio, AA pass/fail | — | computed, not stored | — |

#### D1.6 Typography

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Body font | Theme Options → Visual → Typography (read-only) | Typography | SelectControl of installed font families | theme.json | Global Styles `styles.typography.fontFamily` | `edit_theme_options` |
| Heading font | same | Typography | SelectControl | theme.json | Global Styles `styles.elements.heading.typography.fontFamily` | `edit_theme_options` |
| Button / label font | same | Typography | SelectControl | theme.json | Global Styles `styles.elements.button.typography.fontFamily` | `edit_theme_options` |
| Font sizes | same | Typography | one row per size slug: min, max (fluid) | theme.json sizes | Global Styles `settings.typography.fontSizes` (user origin) | `edit_theme_options` |
| Font Library | Kits → Fonts (menu item) | Typography | link button "Manage fonts" | — | core | `edit_theme_options` |

#### D1.7 Buttons

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Button styles (fill, outline, text) | Theme Options → Visual → Buttons (read-only) | Buttons | per style: background, text, border, radius, padding, hover colours | theme.json `elements.button` and block styles | Global Styles `styles.elements.button` and `styles.blocks.core/button.variations` | `edit_theme_options` |

#### D1.8 Cards

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Card padding | Theme Options → Visual → Cards (read-only, note names theme.json) | Cards | UnitControl / spacing-token select | 16px `--kit-space-4` | Global Styles `settings.custom.kits.card.padding` | `edit_theme_options` |
| Card gap | same | Cards | spacing-token select | 16px `--kit-space-4` | Global Styles `settings.custom.kits.card.gap` | `edit_theme_options` |
| Stacked large card | CSS only (`kit.css` 1261–1262, `!important`) | Cards | padding and gap selects | today's values | Global Styles `settings.custom.kits.card.stacked` | `edit_theme_options` |
| Side-by-side cards | CSS only | Cards | read-only rule: never stretch | — | theme CSS (`align-items:start`) | — |
| Meta row | CSS only | Cards | read-only rule: wraps, date first | — | theme CSS | — |

Code changes that come with this section (phase 1):

- Remove `!important` from `.kit-card--stacked-lg` and its media rule; raise specificity instead.
- Side-by-side cards: `align-items: start` so cards never stretch to the tallest.
- Meta row: `flex-wrap: wrap`.
- `aw.css` has two copies (theme and plugin): any card rule changed there is changed in both.

#### D1.9 Content types

One group per public post type (post, page, and each custom type), plus 404, Search and Authors. Keys are today's `kits_page_defaults` contexts.

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Single: template | Theme Options → Single → {type} | Content types → {type} → Single | SelectControl of templates | theme | `kits_page_defaults.single-{type}.template` | `edit_theme_options` |
| Single: sidebar | same | same | SelectControl | inherits `_site` | `kits_page_defaults.single-{type}.sidebar` | `edit_theme_options` |
| Single: title | same | same | SelectControl | inherits | `…single-{type}.title` | `edit_theme_options` |
| Single: breadcrumbs | same | same | SelectControl | inherits | `…single-{type}.crumbs` | `edit_theme_options` |
| Single: header part | same | same | SelectControl | inherits | `…single-{type}.header` | `edit_theme_options` |
| Single: footer part | same | same | SelectControl | inherits | `…single-{type}.footer` | `edit_theme_options` |
| Single: share networks | same | same | CheckboxControl list | Share bar default | `…single-{type}.share_networks` | `edit_theme_options` |
| Archive: same six fields | Theme Options → Archives → {type} | Content types → {type} → Archive | as above | inherits | `kits_page_defaults.archive-{type}.*` | `edit_theme_options` |
| 404 | Theme Options → Single → 404 | Content types → 404 | as above | inherits | `kits_page_defaults.single-404.*` | `edit_theme_options` |
| Search | Theme Options → Archives → Search | Content types → Search | as above | inherits | `kits_page_defaults.archive-search.*` | `edit_theme_options` |
| Authors | Theme Options → Archives → Author | Content types → Authors | as above | inherits | `kits_page_defaults.archive-author.*` | `edit_theme_options` |

Each field shows "inherits: {value}" when empty, and "Reset to theme" clears it.

#### D1.10 Socials (one source)

Today the same profiles live in four places that are not kept in step:

1. Contact links option `aw_elsewhere` (list of label + URL), shown by shortcode `[aw_elsewhere]` on Contact.
2. Footer links hard-coded in `themes/animal-writes/parts/footer.html:10` and `plugins/animal-writes-core/theme/parts/footer.html:10`.
3. Schema `sameAs` from `aw_publisher_profiles`, which reads Rank Math and falls back to a hard-coded list.
4. Rank Math social options, shown read-only in Theme Options → Socials.

After: one list.

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Profiles (network, URL, label) | `aw_elsewhere`, footer HTML, schema list, Rank Math | Socials | DataViews list: add, reorder, remove; network SelectControl (Instagram, Threads, X, Facebook, LinkedIn, YouTube, WhatsApp, other) | today's `aw_elsewhere` merged with the footer links | `kits_theme_options.socials` (new key: list of {network, url, label}) | `edit_theme_options` |

Readers after the change:

- Footer: a `kits/socials` block (or the core Social Icons block filled from the list) replaces the hard-coded links in both footer copies.
- Contact "Elsewhere": `[aw_elsewhere]` reads `kits_option('socials')`, falls back to `aw_elsewhere` until migrated.
- Schema: `aw_publisher_profiles` reads the list.
- Rank Math: on save, Kits writes the URLs into Rank Math's Social Meta (Facebook page URL, X username, additional profiles) so the two never drift. Rank Math stays the reader for its own output.
- Icons: `aw_social_icon` gets a Threads icon.

Migration (one time, on first load after update): copy `aw_elsewhere` into `socials`, add footer links that are missing, keep `aw_elsewhere` untouched until the owner confirms.

#### D1.11 Share bar

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Networks | Theme Options → Utility → Share | Share bar | CheckboxControl list, reorderable | today's list | `kits_theme_options.share_networks` | `edit_theme_options` |

#### D1.12 Also on Theme Options today

| Field | Now | Goes to | Notes |
|---|---|---|---|
| Font preload | Theme Options → Utility → Performance (`font_preload`) | Settings → Performance | RadioControl wide / all / off, default wide, `kits_theme_options.font_preload` |
| Extra | Theme Options → Utility → Extra | Help → Developer | read-only today; shown as a list of filters other parts use |

### D2. Settings

| Field | Now | Goes to | Control | Default | Storage | Cap |
|---|---|---|---|---|---|---|
| Joined component CSS | Core Settings → Speed (`css_bundle`) | Performance | ToggleControl | on | `kits_theme_options.css_bundle` | `manage_options` |
| Joined files cap | Core Settings (`css_parts_cap`) | Performance | SelectControl 25 / 50 / 100 / 200 | 50 | `kits_theme_options.css_parts_cap` | `manage_options` |
| Defer scripts | Core Settings (`defer_scripts`) | Performance | ToggleControl | on | `kits_theme_options.defer_scripts` | `manage_options` |
| jQuery in footer | Core Settings (`jquery_footer`) | Performance | ToggleControl | on | `kits_theme_options.jquery_footer` | `manage_options` |
| No emoji script | Core Settings (`no_emoji`) | Performance | ToggleControl | on | `kits_theme_options.no_emoji` | `manage_options` |
| Form CSS only with a form | Core Settings (`form_css_lazy`) | Performance | ToggleControl | on | `kits_theme_options.form_css_lazy` | `manage_options` |
| One copy of Inter | Core Settings (`inter_dedupe`) | Performance | ToggleControl | on | `kits_theme_options.inter_dedupe` | `manage_options` |
| LCP image fix | Core Settings (`lcp_fix`) | Performance | ToggleControl | on | `kits_theme_options.lcp_fix` | `manage_options` |
| Font preload | Theme Options → Utility → Performance | Performance | RadioControl | wide | `kits_theme_options.font_preload` | `manage_options` |
| CDN page cache time | Check site → CDN (`aw_cdn_ttl`) | Performance (row added by Animal Writes) | NumberControl, seconds | 0 | `aw_cdn_ttl` (group `aw_cdn`) | `manage_options` |
| CDN long cache time | Check site → CDN (`aw_cdn_ttl_long`) | Performance (row added by Animal Writes) | NumberControl, seconds | 3600 | `aw_cdn_ttl_long` | `manage_options` |
| GA4 through GTM | **no UI today** (`kits_ga4_via_gtm`) | Performance → read-only row | read-only text "on/off, set in code" | current value | unchanged | — |
| Old section classes | Core Settings → Older content (`legacy_group_styles`) | Compatibility | ToggleControl | on | `kits_theme_options.legacy_group_styles` | `manage_options` |
| Preview IPs | Preview (`kits_preview_ips`) | Preview access | TextareaControl, one IP per line, validated | empty | `kits_preview_ips` | `manage_options` (**adds the missing capability check**) |
| Preview devices | Preview (`kits_preview_devices`) | Preview access | DataViews list: name, last seen, remove | empty | `kits_preview_devices` | `manage_options` |
| Preview links | Preview (`kits_preview_links`) | Preview access | list: create link, copy, expiry, remove | empty | `kits_preview_links` | `manage_options` |
| Features | new | Features | ToggleControl per feature | on | `kits_theme_options.features.{slug}` | `manage_options` |

The Preview admin-post actions (register, remove, link, device) become REST routes under `kits/v1/preview/*` with the same nonce and capability.

### D3. Plugins

| Item | Now | Goes to | Control | Storage | Cap |
|---|---|---|---|---|---|
| Required / recommended list | Plugins (`kits_plugins`: kits-core required; rank-math, fluentform, site-kit, optimization-detective recommended) | Plugins | DataViews table: name, status, install / activate button | none (reads plugin state) | `install_plugins` / `activate_plugins` per button |
| This site's plugins | Plugins (`kits_plugin_screens`) | Plugins | table with dashboard links | none | `manage_options` |

### D4. Tools

| Item | Now | Goes to | Control | Storage | Cap |
|---|---|---|---|---|---|
| Export | Import / Export (REST `kits/v1/settings` GET) | Tools | parts checkboxes (global styles, options, patterns) + Download | — | `edit_theme_options` |
| Check a file | new | Tools | file input → list of what would change, nothing saved | — | `edit_theme_options` |
| Import | Import / Export (`kits_transfer_import`) | Tools | file input + parts checkboxes + Import | writes the three parts; saves a copy first in `kits_settings_backup` | `edit_theme_options` |
| Restore defaults | Import / Export (`kits_transfer_reset`) | Tools | button + confirm dialog | clears the three parts; copy first in `kits_settings_backup` | `edit_theme_options` |
| Put the copy back | Import / Export (`kits_transfer_undo`) | Tools | button, shows the copy's date | restores `kits_settings_backup` | `edit_theme_options` |

Gaps fixed here:

- Restore defaults today leaves the Preview options alone. That stays (Preview is access, not design) and the dialog says so.
- `kits_settings_backup_defaults` is never read. Remove it in phase 2 after one release.

### D5. Help

| Item | Now | Goes to | Notes |
|---|---|---|---|
| Docs tables (kits-design, kits-features) | Docs | Help → Docs | unchanged content |
| WP-CLI commands list | Site: Animal Writes | Help → Docs (section added by Animal Writes) | |
| Scheduled tasks, staging notes | Site: Animal Writes | Help → Docs (section added by Animal Writes) | statuses move to Home |
| Design system: components, states, menus, tokens | Design system | Help → Design system | read-only |
| Edit screens: Mapping, Schema, Third-party | Edit screens (`data/edit-screen-map.json`) | Help → Developer | the map's line references are stale and it has no Modules entry; refresh the map when this moves |
| Theme parts: functions, templates, parts, patterns | Theme parts | Help → Developer | until the child theme goes |

### D6. Home: status rows from Site: Animal Writes and Check site

| Row | Now | Goes to | Storage |
|---|---|---|---|
| Dates status | Site: Animal Writes | Home → Setup checklist (row added by Animal Writes, link to Tools → Dates) | `aw_team_dates` |
| Check site status | Site: Animal Writes | Home → Page check | `aw_site_check_last` |
| To review | Site: Animal Writes | Home → Plugin status (SourceWeb row) | unchanged |
| Scheduled tasks | Site: Animal Writes | Home → Versions and limits (cron health row) | WP cron |
| Daily backup | Site: Animal Writes | Home → Backup | `aw_backup_last`, cron `aw_daily_backup` |
| Deploys and cache | Site: Animal Writes | Home → Deployed commit | unchanged |
| Staging | Site: Animal Writes | Help → Docs | |
| Page check: run | Check site (admin-post `aw_site_check`) | Home → Page check → Run now | REST `kits/v1/site-check` (POST), capability `manage_options` |
| Page check: URL list | Check site | Home → Page check (list behind "Pages checked") | unchanged |
| Page check: admin-bar node | admin bar | kept, links to Home | |
| Joined files | Check site | Home → Joined files | `kits_parts_kinds`, `kits_parts_sweep` |
| Theme switch log | Check site (`aw_theme_switch_log`) | Home → Versions and limits | capability `switch_themes` to view |

Kits Core itself stays free of site names: the page check, backup and deploy rows are added by Animal Writes through `kits_status_rows` and a new `kits_home_sections` filter.

---

## E. Tech plan

### E1. Redirects

Every old admin slug redirects to its new place with a 302 on `admin_init`, kept for two releases:

| Old | New |
|---|---|
| `admin.php?page=kits` (Welcome) | `admin.php?page=kits` (Home) |
| `kits-core-settings` | `kits-settings` |
| `kits-options` | `kits-options` (same) |
| `kits-transfer` | `kits-tools` |
| `kits-docs` | `kits-help#docs` |
| `kits-system` | `kits-help#design-system` |
| `kits-preview` | `kits-settings#preview` |
| `animal-writes` (Overview and Site: Animal Writes) | `kits` |
| `aw-site-check` | `kits#page-check` |
| `aw-dates` | `tools.php?page=aw-dates` |
| `aw-keys` | RankWeb → Settings → Connected services |
| `aw-modules` | SourceWeb → Settings |
| `aw-elsewhere` | `kits-options#socials` |
| `aw-edit-screens` | `kits-help#developer/edit-screens` |
| `aw-theme-parts` | `kits-help#developer/theme-parts` |

### E2. No setting lost

- Option names do not change, except two new keys (`socials`, `sticky_header`) and the `features` map.
- Before phase 1 ships: export settings from live and keep the file. After: export again and compare; every key in the first file must be in the second with the same value.
- Moved screens keep their option names, so moving a screen never moves data.
- A checklist test lists every field in section D and fails if a registered setting is missing from the REST schema.

### E3. Extension points (so Kits Core stays generic)

- `kits_admin_tabs`, `kits_menu_order`: kept.
- `kits_status_rows`: kept.
- New `kits_home_sections`: add a Home section (id, title, render callback or REST source).
- New `kits_settings_fields`: add a row to a Settings section (id, section, control, setting name).
- New `kits_help_sections`: add a Help → Docs or Developer section.
- New `kits_features`: register a feature switch.
- Animal Writes uses these for page check, backup, deploy, CDN times, notes and Developer screens.

### E4. Capabilities and nonces

- Every REST route has a `permission_callback` with the capability in the tables above. No `__return_true`.
- Saves go through `/wp/v2/settings` (core nonce `wp_rest`).
- Kits-only actions (site check run, import, restore, undo, preview) are REST routes under `kits/v1/*`, each with its own capability check.
- The Preview screen's missing capability check is fixed in phase 2.
- Every option registered with a `sanitize_callback` and a JSON schema (type, enum, format: uri for URLs).

### E5. JavaScript translations

- Text domain `kits-core`, `wp_set_script_translations()` for each screen script.
- All strings through `__()` from `@wordpress/i18n`, with translator comments where a string has a placeholder.
- `wp i18n make-pot` and `make-json` in the build.

### E6. Performance budget

- One script per screen, loaded only on that screen.
- WordPress packages are loaded as dependencies (`wp-components`, `wp-data`, …), not bundled.
- Each screen script at most 60 KB minified (own code, before gzip).
- First paint of a screen under 1 second on the live admin; no extra admin-ajax calls on load.
- Nothing from these screens loads on the front end.

### E7. Tests and checks

- PHPCS (security ruleset) on every changed PHP file.
- `@wordpress/scripts` lint for JS and CSS.
- PHPUnit: settings registered with schema; `kits_option()` lookup order unchanged; redirects; capability checks on each REST route.
- The CI baseline `tools/ci/settings_parts_baseline.txt` lists every `add_submenu_page` line; it is updated in the same change that moves a screen.
- After each phase: open every Kits screen on live, save one field, reset it, and check the front end did not change (render compare on Home, a listing, a news item, Contact).
- Accessibility: keyboard pass and screen-reader names on each screen.

---

## F. Phases

| Phase | What | Size | Risk |
|---|---|---|---|
| 1 | **Theme Options**: build system, app header, section list, Identity, Header, Footer, Layout, Colours (with contrast), Typography, Buttons, Cards (with the CSS fixes), Content types, Socials (one source + migration), Share bar | Large: new JS build, about 12 sections, one data migration (socials), CSS changes on cards | Medium. Design values move from read-only to editable in Global Styles, so a wrong save changes the whole site; Reset to theme and Tools → Put the copy back cover it. Socials migration touches the footer and schema. |
| 2 | **Home, Settings, Plugins, Tools, Help** + the moves out of Kits (Dates, Keys, Modules, Check site parts) + redirects | Medium: five screens on the same build; most fields are switches; Home is mostly read-only | Low to medium. Settings are switches with known defaults. Moving Keys and Modules depends on the RankWeb and SourceWeb screens existing; until then they stay where they are and redirect later. |
| 3–6 | Plugin dashboards: RankWeb, SourceWeb, MailWeb, Directory | specified separately | specified separately |

Order inside phase 1: build system and app header first (no visible change), then one section at a time, each shipped on its own.

---

## Self-check: every current item has a place

| Current screen | Items | All placed |
|---|---|---|
| Welcome | Start here links, System Status rows | Home (B1) |
| Plugins | `kits_plugins`, `kits_plugin_screens` | Plugins (D3) |
| Import / Export | export, import, reset, undo, backup option | Tools (D4) |
| Fonts | Font Library link | Typography (D1.6) |
| Core Settings | 8 speed switches, cap, older content | Settings (D2) |
| Theme Options | General (header, layout, footer), Single, Archives, Visual (6), Utility (share, socials, performance, extra) | D1.1–D1.12 |
| Design system | components, states, menus, contrast, tokens | Help (D5), contrast to Colours (D1.5) |
| Docs | kits-design, kits-features | Help (D5) |
| Preview | IPs, devices, links | Settings (D2) |
| Site: Animal Writes / Overview | status groups, WP-CLI list | Home (D6), Help (D5) |
| Dates | `aw_team_dates`, add / delete, widget | Tools → Dates (C) |
| Check site | page check, backup, joined files, CDN, email, thumbnails, pictures, theme switch log | Home (D6), Settings (D2), MailWeb / SourceWeb (C) |
| Contact links | `aw_elsewhere` | Socials (D1.10) |
| Keys | `aw_keys` and the five keys | RankWeb (C) |
| Modules | `aw_social_on`, `aw_social_show` | SourceWeb (C) |
| Edit screens | Mapping, Schema, Third-party | Help → Developer (D5) |
| Theme parts | functions, templates, parts, patterns | Help → Developer (D5) |

Gaps found while listing, each handled above:

1. `kits_page_defaults._site.colours` has no UI → Colours (D1.5).
2. Preview save relies only on the menu's capability (no check in the save itself) → explicit check added (D2, E4).
3. Socials live in four places → one list (D1.10).
4. `kits_ga4_via_gtm` has no UI → read-only row (D2).
5. `kits_settings_backup_defaults` is never read → removed in phase 2 (D4).
6. Edit-screen map has stale line references and no Modules entry → refreshed when it moves (D5).
7. Moving screens changes `tools/ci/settings_parts_baseline.txt` → updated in the same change (E7).
8. `kits_theme_screens()` helper is unused → removed in phase 2.
9. No Threads icon in `aw_social_icon` → added with Socials (D1.10).

## Questions for the owner

1. **Sticky header**: today it is fixed in the theme. Add the switch (D1.2), or leave it out?
2. **Socials and Rank Math**: Kits writes the list into Rank Math on save (D1.10). Or should Rank Math stay as it is and only be read?
3. **Breakpoints**: shown read-only (D1.4). Agreed that they are not editable?
4. **Phase 2 and the plugin moves**: Keys and Modules move when the RankWeb and SourceWeb screens exist. Until then they stay in the Kits menu. Agreed?
