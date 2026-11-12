# wp-admin IA inventory (12 Nov 2026)

Read on live in the built-in browser (menus, submenus, tabs, sections) and from the code. Each row has a proposal:
**keep**, **merge**, **move**, **rename** or **remove**. Nothing is removed or moved before the owner has seen this list.
The target layout is in the plan: content menus first, then one block of Wugweb products (each its own top-level menu,
the same with or without Kits Core), then third-party plugins as their vendors ship them.

## 1. Side menu and submenus (as on live)

| Menu › submenu | Owner (file) | Proposal |
|---|---|---|
| Dashboard › Home, Updates | core | keep |
| Dashboard › Simple History | Simple History | keep |
| Directory › Directory, Add Listing | Core (CPT `aw_listing`) | keep |
| Directory › Claims | Core `claims-review.php:296` | **done 12 Nov**: the item is Fluent Forms › Claims, after Entries (owner: "With form entries"); same screen, address and review; DirectoryWeb › Claims stays read-only |
| News › News, Add News item | Core (CPT `aw_news`) | keep |
| News › Linked campaigns | taxonomy `aw_campaign_link` | **done 12 Nov**: one entry under Classification |
| News › Social posts | Core `social-posts.php:146` (CPT `aw_social`) | keep under News (no Animal Writes menu: Animal Writes is the site layer and goes with the child theme (plan 4.3), so its screens stay in Kits (owner, 12 Nov)) |
| Resources › All Resources, Add New Resource | core `post` | keep |
| Resources › Linked campaigns | taxonomy | **done** (as above) |
| Library › Library, Add Library item, Linked campaigns | Core (CPT `aw_legal`) | keep; Linked campaigns **done** |
| Opportunities › …, Linked campaigns | Core (CPT `aw_opportunity`) | keep; Linked campaigns **done** |
| Campaigns › Campaigns, Add Campaign, Linked campaigns | Core (CPT `aw_campaign`) | keep; Linked campaigns **done** |
| Campaigns › Letter report | Core `campaign-letters.php:267` | keep (it belongs to Campaigns) |
| Press coverage › All coverage, Add coverage, Linked campaigns | Core (CPT `aw_press`; menu set twice: `press.php:108`, `admin-ia.php:95`) | **done 12 Nov**: menu defined once (`press.php`); Linked campaigns under Classification |
| Pages | core | keep |
| Media › Library, Add Media File | core | keep |
| Media › Media groups | Core (taxonomy `aw_media_group`) | keep |
| Media › Make WebP, Duplicate files, Reuse pictures | Core `media-webp.php`, `media-merge.php`, `media-reuse.php` | keep (core convention: media tools under Media) |
| Classification › Categories, Tags, Linked campaigns | Core `admin-ia.php:135` | keep (Linked campaigns added 12 Nov) |
| Appearance › Themes, Editor, Fonts | core | keep |
| Kits | Kits Core `admin-home.php:22` | **done 12 Nov**: its own top-level menu, first in the Wugweb block |
| Appearance › Theme Check | Theme Check | keep |
| Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb, PlanWeb | Kits Core `admin-fold.php` (`kits_wugweb_menu_slots()`), each plugin's own call without Kits Core | **done 12 Nov**: each its own top-level menu, one block after a separator, the same order without Kits Core (`admin-ia.php` menu order) |
| Tools › Plugin Check, Plugin Check Namer, Plugin Check SVN | Plugin Check | keep (development tool) |
| Settings › Performance, Plugin Check, Simple History | third party | keep |
| Site Kit, LiteSpeed Cache, FluentCRM, Fluent Forms, UpdraftPlus | third party | keep |
| Rank Math SEO › … › Black Friday Sale | Rank Math | **remove** the upsell link (`admin-tidy.php` list) |
| (empty last menu item) | unknown | **find and remove** |

## 2. Screens with no menu entry (hidden) and dead addresses

| Address | Today | Proposal |
|---|---|---|
| `admin.php?page=aw-keys` (Keys) | Kits › Keys | keep in Kits (no Animal Writes menu: Animal Writes is the site layer and goes with the child theme (plan 4.3), so its screens stay in Kits (owner, 12 Nov)) |
| `admin.php?page=aw-modules` (Modules) | Kits › Modules | keep in Kits (as above) |
| `admin.php?page=aw-dates` | redirects to PlanWeb classic | keep the redirect, to PlanWeb's shell screen |
| `aw-theme-parts`, `kits-docs` | redirect to Kits › Help | keep redirects |
| `kits-fonts`, `kits-design-system`, `aw-cron-health`, `aw-core` | **403 "not allowed"** | **redirect**: fonts → Appearance › Editor › Styles › Typography; design system → Kits › Help › Design system; cron health → Dashboard; aw-core → Kits (**done 12 Nov**) |

## 3. Tabs inside our screens

| Screen | Tabs today | Proposal |
|---|---|---|
| Kits | Home, Theme Options, Settings, Tools, Help | Home: **added 12 Nov** Edit the theme (Site Editor links) above the site's checks; the site's checks stay on Home (no Animal Writes menu: Animal Writes is the site layer and goes with the child theme (plan 4.3), so its screens stay in Kits (owner, 12 Nov)). Theme Options: **map** to the Site Editor where core can hold it (plan phase 4.7). |
| Kits › Settings | Performance, CDN cache times, Shared cache, Compatibility, Preview access/IPs/devices, Connected services and modules | keep; group as sections with one line each |
| Kits › Help | Design system (tokens, states, components 160, menus, contrast), edit-screens map (mapping, schema, third-party boxes), docs | **rename** the developer parts to Help › Developer; keep design system |
| RankWeb | Dashboard, FAQ, Settings | add **Tools** (re-score, rebuild FAQ index) and **Help**; Settings tabs stay |
| DirectoryWeb | Dashboard, Claims, Listings, Settings, Place intros | **merge** Place intros into Listings › Places; Claims stays read-only (owner) |
| PlanWeb | Dashboard, Entries, Settings | keep; add Help |
| MailWeb | Dashboard, Inbox, Settings | keep; add Help |
| InsightWeb | Dashboard, Keywords, Rising, Pages, Clarity, Check list, Sourcing keywords, Links, Server, Settings | 10 tabs: **group** as Dashboard › Search (Keywords, Rising, Pages) › Site (Links, Server, Clarity, Check list) › Settings; Sourcing keywords shows only with SourceWeb |
| SourceWeb | Dashboard, To review, Enrichment review, Sources, Keywords, Rules, AI models, Workers, Rejected, Relay log, Change log, Settings | 12 tabs: **group** as Dashboard › Review (To review, Enrichment) › Setup (Sources, Keywords, Rules, AI models, Workers) › Logs (Rejected, Relay, Change) › Settings |

## 4. Every screen twice (shell and classic)

Each of MailWeb, RankWeb, InsightWeb, SourceWeb, DirectoryWeb and PlanWeb has the Kits shell screen and a classic PHP
screen (`&ui=classic`), switched by six copies of `*_ui_wanted` / `*_ui_ready`. Proposal (owner, 12 Nov: keep the shell,
on `@wordpress/components`): **merge** to one screen per product once the shell screen covers the classic one.

## 5. Duplicate functions across plugins

| Function | Where | Proposal |
|---|---|---|
| `kits_term_url` | Kits Core `primitives.php:11`, RankWeb `helpers.php:13` | **done 12 Nov**: RankWeb has `aw_seo_term_url()` |
| `*_simple_table` + class | Kits Core, Core, RankWeb, MailWeb, SourceWeb | keep one per plugin (independence), same pattern |
| `*_rest_can` | PlanWeb, RankWeb, SourceWeb, DirectoryWeb | keep per plugin |
| `aw_seo_api_allowed` / `aw_si_api_allowed` | RankWeb, InsightWeb | keep per plugin |
| `mailweb_crm_*` / `aw_crm_*` | MailWeb, Core | **merge**: Core calls MailWeb's when present |
| `kits_link`, `aw_core_link`, `aw_theme_parts_link`, `kits_options_link` | Kits Core, Core | **merge** into one per plugin |

## 6. Options with no screen (only WP-CLI can change them)

`aw_default_share_image`, `aw_seo_keep_listings`, `aw_district_helplines`, `aw_issuer_short`, `aw_ff_send_email`,
`aw_nl_ff_form_full`, `aw_sample_listing_ids`, `aw_backfill_floor`, `kits_ga4_via_gtm` (show read-only). Term meta
`aw_category_card`, `aw_pinned`, `aw_short_name`, `aw_campaign_short`; post meta `aw_campaign_metrics`, `aw_legal_dated`.
Proposal: **add** each to its owner's Settings section or edit box.

**Done 12 Nov:** `aw_ff_send_email`, `aw_nl_ff_form_full`, `aw_sample_listing_ids` on Kits › Modules › Forms and sample listings (`site-options.php`). Also done 12 Nov: RankWeb › Settings › Indexing (listings kept indexed, new IDs must be listings) and › Share images (default share image, media picker); SourceWeb › Settings › Backfill (oldest date); Kits › Modules › Directory and Library lists (district helplines, one line per number with source and date; issuer short names); Kits › Settings › Analytics shows `kits_ga4_via_gtm` read only. Next: the term and post meta.

## 7. Plugin list vs docs

Create Block Theme is active (docs: inactive). Link Whisper Free is inactive (docs and edit-screens map still list its
panel). Accessibility Checker inactive. `docs/plugin-map.md` "Every plugin on live" is from 24 Oct: **refresh**.
