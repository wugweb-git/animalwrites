# DirectoryWeb: a generic directory engine, and the last Directory code leaving Animal Writes Core (scope, 18 Nov 2026)

**Goal.** Retire the `animal-writes-core` tenant plugin piece by piece. This document covers the Directory piece:
- what of the Directory is still in Core, what it depends on and what depends on it;
- how DirectoryWeb (`plugins/directoryweb`, text domain `directoryweb`) becomes a **generic directory engine** that
  any site configures: one generic preset, `directoryweb-default`, plus that site's own settings;
- how the move happens without touching data.

**Checked against** `origin/wordpress-dev` at `73bbc87c` (18 Nov 2026). Every `file:line` was read on that commit, or
on this branch where marked "(branch)". Paths:
- `core/` = `plugins/animal-writes-core/includes/`
- `dw/` = `plugins/directoryweb/includes/`
- `rw/` = `plugins/aw-seo-extras/includes/` (RankWeb)
- `sw/` = `plugins/aw-sourcing/includes/` (SourceWeb)

**Principles (owner rules).**
1. **Generic engine, one preset.** No animal-welfare knowledge in the engine. DirectoryWeb ships ONE generic preset,
   `directoryweb-default`. This site runs it with its configured data prefix and settings, which reproduce today exactly.
   **The data prefix stays `aw_` on this site; code and preset names never use `aw`.** Stored names (`aw_*` meta keys,
   the `aw_listing` post type slug, options) are data and do not change (§12).
2. **WordPress-native first.** Plugins (SCF, Rank Math, Fluent Forms, FluentCRM, SourceWeb) are layers on top, each
   optional, and DirectoryWeb runs without any of them.
3. **Admin and front-end never mix.** Every row below is labelled **[A]** (wp-admin: core UI only, i.e. WP_List_Table,
   Settings API, core notices, no custom CSS, no Kits design rules) or **[F]** (visitor-facing: Kits design rules,
   i.e. tokens, 767/768 and 1199/1200, 720px main column, no folds, Inter, date first in meta rows, titles never
   underlined). The block editor canvas is [F]; its sidebar panels are [A]. A visitor form is [F]; its field-mapping
   settings are [A].
4. **Move code, never data.** `aw_*` keys, `aw_listing`, terms, slugs, URLs and options stay.

**Where we start.** DirectoryWeb 1.0.0 (live since 17 Nov) already holds 28 files and 9,372 lines in `dw/`, the 8 SCF
listing groups, the LGD place files, the map, the admin and the REST routes. Core still loads it from the sibling folder
(`plugins/animal-writes-core/animal-writes-core.php:61-77`), and DirectoryWeb still needs Core
(`plugins/directoryweb/directoryweb.php:35-46`).

---

## 1. Inventory: what of the Directory is still in Core

### 1.1 Moves in this PR (stage 2)

| Side | Piece | Where (file:line) | Now in |
|---|---|---|---|
| — (data) | Directory meta fields (48 keys) | `core/meta.php:36-84` in `aw_meta_schema()` (`:17`) | `dw/listing-meta.php` `directoryweb_meta_schema()` (branch) |
| — | Their sanitising rules | `core/meta.php:140-182` (Directory parts of `:141`, `:142`, `:154-172`) | `directoryweb_sanitize_meta()` |
| A+F (REST) | Their registration | `core/meta.php:187-209` (`init`) | `directoryweb_register_meta()` |
| — | Phone and hours lists | `core/meta.php:213-411` (`AW_LIST_FIELDS` `:225`, `aw_listing_phones` `:254`, `aw_listing_hours` `:277`, `aw_set_listing_list` `:305`, `aw_listing_put_phone` `:343`, `aw_listing_first_phone` `:383`, `aw_hours_rows_spec` `:399`) | `directoryweb_*` of the same names |
| — | Never-publish phones | `core/meta.php:235-246` (`AW_NEVER_PUBLISH_PHONES`, 1 hash; `aw_phone_never_publish`; filter `aw_never_publish_phones`) | `DIRECTORYWEB_NEVER_PUBLISH_PHONES`, `directoryweb_phone_never_publish()` |
| F | Data sources of a listing | `core/data-sources.php` (whole file: `aw_data_sources` `:31`, `aw_listing_sources` `:58`, `aw_source_labels` `:117`, `aw_source_logo` `:155`) | `dw/data-sources.php`, unchanged |
| — (cron) | Re-verification job | `core/cron.php:46-83` | `directoryweb_flag_reverify()` on `aw_daily` |
| A | One-pager SCF gate | `dw/listing-profile.php:66` called Core's `aw_scf_active()` | `directoryweb_scf_active()` (Kits Core's check, else SCF's function), §6 |

### 1.2 Still in Core today, and the final home of each piece

**Owner rule:** `animal-writes-core` will be **deleted**. "Stays in Core" is never an end state, and no code stays in
it. Each piece below has exactly one final home from this list:

| Destination | Meaning |
|---|---|
| **DirectoryWeb** | directory code |
| **Kits Core** | generic code shared by the Kits plugins |
| **RankWeb** | SEO code (schema, FAQ, breadcrumbs, sitemaps) |
| **SourceWeb** | sourcing and enrichment code |
| **Kits theme** | templates, patterns, theme.json and global styles |
| **Saved site settings** | options and settings stored by the owning plugin (Settings API), SCF groups saved to the owning plugin's `acf-json` |
| **Native** | a WordPress core API or feature replaces the code: Media, `register_post_type`, `register_post_meta`, core `category`/`post_tag` and term meta, Block Bindings, plugin block templates, Settings API, WP_List_Table, wp-cron, REST, `wp_date`, `wp_upload_dir`, script/style loading |

Where Native replaces the code, the plugin named in brackets is the one that calls the core API.

| Side | Piece | Where today | Final home |
|---|---|---|---|
| A+F | Listing post type arguments | `core/content-types.php:30`, `:46-48`, `:76-87` (DirectoryWeb calls it at `dw/bootstrap.php:16-22`) | **Native** `register_post_type` (DirectoryWeb) with its own arguments from its config (stage 3; Q1). The builder for the other six types: **Native** `register_post_type` called by **Kits Core** (session D) |
| — | Attribution fields on listings | `core/meta.php:18-25` | **Native** `register_post_meta` (SourceWeb, which writes them); DirectoryWeb reads them |
| — | `aw_website` on Opportunities | `core/meta.php:37` (branch) | **Native** `register_post_meta` (Kits Core content model, session D); DirectoryWeb keeps the listing side |
| — | `AW_WEEKDAYS` | `core/meta.php` (branch) | **DirectoryWeb** `DIRECTORYWEB_WEEKDAYS` (exists); `faq-data.php` (→ RankWeb) and SourceWeb switch to it |
| A+F | Tags: types and services, icons | `core/tags.php:25-33` (`AW_TAG_ICONS`), `:41`, `:106-153`; `core/model.php:25` (`AW_SERVICES`) | **Native** core `post_tag`/`category` (and term meta, Q6) for the terms; roles and helpers in **DirectoryWeb** (§4, §1.4); slug lists, service labels and icons in **saved site settings** (DirectoryWeb) |
| — | Relations to listings | `core/model.php:36-39`, `:88-115`, `:277-284` | **Kits Core** content model (session D); DirectoryWeb reads through a `directoryweb_related_items` filter |
| F | Block bindings | `core/bindings.php:104-236` (source `animal-writes/field` `:170`) | **Native** Block Bindings API (`register_block_bindings_source`): **DirectoryWeb** registers `directoryweb/field`; a generic source for other types in **Kits Core** |
| F | Listing blocks registration | `core/hub-blocks.php:41`, `:50-63` | **Native** `block.json` / `register_block_type` (DirectoryWeb); callbacks are already in `dw/listing-template.php:116-566` |
| F+A | Forms engine | `core/forms-fluentforms.php`, `core/fluentcrm.php` | **Kits Core** generic Fluent Forms / FluentCRM bridges; **DirectoryWeb** adapters for claim and report (§5); the no-plugin form is **Native** (block + REST); form IDs and tags in **saved site settings** |
| A | SCF bridge | `core/scf.php:24-26`, `:63-90` (filter `aw_scf_groups` `:84`), `:196-200`; `core/edit-screens.php:236+` | **Kits Core** `kits_scf_*` (session D, PR #139); fields are **Native** `register_post_meta` (DirectoryWeb); the 8 groups are **saved site settings** in DirectoryWeb's `acf-json` (§6) |
| F | Templates | `plugins/animal-writes-core/theme/templates/single-aw_listing.html`, `archive.html`, `page-hub.html` via `core/theme-parts-templates.php:114-194` | **Native** plugin block templates (`register_block_template`, DirectoryWeb, stage 4); overrides and styling in the **Kits theme** (theme.json, global styles) |
| F | Search, Home, analytics context | `core/pages.php:196-259`, `core/home.php:203-212`, `core/kits-config.php:26-97` | **Kits Core** (search, Home, analytics context), calling `directoryweb_*` guarded |
| — | FAQ feeders, breadcrumbs | `core/faq-data.php`, `core/breadcrumbs.php` | **RankWeb** (session B) |
| — | Wrappers this PR leaves in Core (`aw_listing_phones` …) | `core/meta.php`, `core/cron.php` (branch) | **Deleted with Core.** Every caller switches to the `directoryweb_*` names first (stage 6) |
| — | Daily event `aw_daily` | `core/cron.php` | **Native** wp-cron: DirectoryWeb's own `directoryweb_daily` event (stage 6) |

### 1.3 Already in DirectoryWeb

| Side | Piece | Where |
|---|---|---|
| A | Claims (legacy `aw_claim` type), decide handler | `dw/claims.php`, `admin_post_aw_claim_decide` `:199` |
| A | Claim review (Directory → Claims), the review screen | `dw/claims-review.php` (field map `DW_CLAIM_L` `:26`, screen `:299`, `admin_post_aw_claim_ff_decide` `:627`) |
| A | DirectoryWeb Claims list in the Kits shell: **read-only** | `dw/directoryweb-rest.php`, `dw/directoryweb-ui.php:97-99` |
| F | Badges: Claimed = `aw_verified`; Verified = government documents | `dw/listing-template.php:405`, `:568`; `dw/gov-verified.php:57`, `:84` |
| A | SCF groups (8), the one-pager fallback | `plugins/directoryweb/acf-json/`, `directoryweb.php:96-115`; `dw/listing-profile.php:61-79` (branch) |
| F | Places, map, Near me, Find help, district helplines, place intros, "Featured in", org people | `dw/places.php`, `dw/directory-map.php`, `dw/find-help.php`, `dw/district-helplines.php`, `dw/place-intros.php`, `dw/featured.php`, `dw/org-people.php` |
| A | Dashboard, Listings, Settings (Kits shell), WP-CLI | `dw/directoryweb-ui.php`, `dw/directoryweb-rest.php:49-147`, `dw/directoryweb-listings.php:40-99` |

**Thin type pages** are RankWeb's: `rw/robots.php:9`, `:34` (`AW_THIN_DISTRICT_TYPE`, a RankWeb setting, default 1;
only empty pages get noindex).

### 1.4 DirectoryWeb's calls into Core, to switch before Core goes

Every DirectoryWeb call into a function defined only in `animal-writes-core` (found by listing Core's functions and
searching `plugins/directoryweb` for calls, tests excluded). Each must switch to a `kits_*` or `directoryweb_*` name
**before Core is deleted**, and each call is guarded (`function_exists`, or a DirectoryWeb fallback) so a missing Core
or Kits Core never fatals. Today DirectoryWeb stops itself when Core is missing (`directoryweb.php:35-46`, checks
`AW_CORE_VERSION` at `:38`), which is why the unguarded calls are safe now. That check goes last.

| Final home | Group | Core function (defined at) | DirectoryWeb call sites (`*` = not guarded at the call) | Guarded | New name |
|---|---|---|---|---|---|
| Kits Core | Item page parts (session D) | `aw_item_editor_preview` (`core/item-head.php:73`) | `listing-template.php:118`, `listing-template.php:141`, `listing-template.php:162`, `listing-template.php:214`, `listing-template.php:236` +8 | 13/13 | `kits_item_*` |
| Kits Core | Item page parts (session D) | `aw_item_here` (`core/item-head.php:67`) | `listing-template.php:39`, `find-help.php:358*` | 1/2 | `kits_item_*` |
| Kits Core | Item page parts (session D) | `aw_item_kind` (`core/item-head.php:83`) | `find-help.php:116*`, `find-help.php:242*`, `find-help.php:436*` | 0/3 | `kits_item_*` |
| Kits Core | Item page parts (session D) | `aw_item_more_card` (`core/item-head.php:497`) | `listing-template.php:558` | 1/1 | `kits_item_*` |
| Kits Core | Item page parts (session D) | `aw_item_date_ts` (`core/item-head.php:120`) | `listing-template.php:478` | 1/1 | `kits_item_*` |
| **Native** Media (Kits Core) | Item page parts (session D) | `aw_item_card_media` (`core/item-head.php:487`) | `listing-template.php:480` | 1/1 | `wp_get_attachment_image()` through `kits_item_card_media` |
| Kits Core | Icons, dates | `aw_i` (`core/systems.php:24`) | `listing-profile.php:259*`, `listing-profile.php:261*`, `listing-profile.php:288*`, `listing-profile.php:293*`, `listing-profile.php:301*` +17 | 0/22 | `kits_icon`, `kits_date` |
| Kits Core (on **Native** `wp_date`) | Icons, dates | `aw_date` (`core/systems.php:45`) | `listing-profile.php:297*`, `data-sources.php:134*`, `listing-template.php:94*`, `listing-template.php:95*`, `listing-template.php:147*` +7 | 0/12 | `kits_icon`, `kits_date` |
| **Native** style loading (DirectoryWeb) | Assets and theme files | `aw_use_component_style` (`core/theme-parts-assets.php:161`) | `listing-profile.php:311*`, `listing-profile.php:313*`, `local-cards.php:19*`, `listing-template.php:370*`, `listing-template.php:474*` +9 | 0/14 | `wp_enqueue_style` / block.json `style`, via `kits_use_component_style` where shared |
| **Native** (DirectoryWeb) | Assets and theme files | `aw_theme_file_uri` (`core/theme-parts.php:36`) | `data-sources.php:121*`, `listing-template.php:321*` | 0/2 | `plugins_url()` / `DIRECTORYWEB_URL`; theme files by `get_theme_file_uri()` |
| Kits Core | Phone link | `aw_tel_url` (`core/bindings.php:59`) | `listing-profile.php:357*`, `listing-template.php:169*`, `listing-template.php:171*`, `listing-template.php:192*` | 0/4 | `kits_tel_url` |
| **Native** WP_List_Table (Kits Core) | Admin table | `aw_core_simple_table` (`core/admin-table.php:21`) | `claims-review.php:457*`, `claims.php:165*`, `place-intros.php:236*` | 0/3 | `kits_simple_table` (exists, on WP_List_Table) |
| Kits Core (on **Native** `wp_upload_dir`) | Private upload folder | `aw_private_dir` (`core/campaigns.php:17`) | `claims-review.php:515*`, `claims.php:106*`, `claims.php:187*`, `claims.php:226*` | 0/4 | `kits_private_dir` |
| Kits Core | Section pages (hubs) | `aw_hub_url` (`core/hubs.php:35`) | `directory.php:398*`, `directory.php:639*`, `directory.php:650*` | 0/3 | `kits_hub_*` |
| Kits Core | Section pages (hubs) | `aw_hub_page` (`core/hubs.php:126`) | `district-helplines.php:108*`, `place-intros.php:129*` | 0/2 | `kits_hub_*` |
| Kits Core | Section pages (hubs) | `aw_hub_type` (`core/hubs.php:40`) | `directory.php:223` | 1/1 | `kits_hub_*` |
| Kits Core | Section pages (hubs) | `aw_hub_of_query` (`core/hubs.php:68`) | `district-helplines.php:181*` | 0/1 | `kits_hub_*` |
| Kits Core | Generic tag helpers | `aw_item_tags` (`core/tags.php:133`) | `district-helplines.php:82*` | 0/1 | `kits_item_tags`, `kits_set_item_tags` |
| Kits Core | Generic tag helpers | `aw_set_item_tags` (`core/tags.php:158`) | `claims-review.php:603*`, `claims.php:211*` | 0/2 | `kits_item_tags`, `kits_set_item_tags` |
| Kits Core | Block result head | `aw_block_result_head` (`core/hub-blocks.php:404`) | `directory-map.php:281*` | 0/1 | `kits_block_result_head` |
| **Native** Block Bindings (DirectoryWeb) | Bindings value | `aw_binding_value` (`core/bindings.php:74`) | `gov-verified.php:213` | 1/1 | `directoryweb/field` source; `kits_binding_value` for other types |
| Kits Core | SCF check | `aw_scf_active` (`core/scf.php:24`) | `listing-profile.php:68` | 1/1 | `kits_scf_active` (PR #139; gate already chains) |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_id` (`core/forms-fluentforms.php:176`) | `claims-review.php:66` | 1/1 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_form` (`core/forms-fluentforms.php:237`) | `claims.php:88*` | 0/1 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_ref` (`core/forms-fluentforms.php:552`) | `claims-review.php:247`, `directoryweb-dashboard.php:382*` | 1/2 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_ticked` (`core/forms-fluentforms.php:531`) | `claims-review.php:222` | 1/1 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_phone10` (`core/forms-fluentforms.php:85`) | `claims-review.php:149*` | 0/1 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_phone_store` (`core/forms-fluentforms.php:96`) | `claims-review.php:110*`, `claims-review.php:219*`, `claims-review.php:226*` | 0/3 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | Fluent Forms bridge (generic) | `aw_ff_social_user` (`core/forms-fluentforms.php:111`) | `claims-review.php:160*`, `claims-review.php:161*`, `claims-review.php:279*`, `claims-review.php:280*`, `claims-review.php:281*` +1 | 0/6 | `kits_ff_*`; claim/report mapping in DirectoryWeb's Fluent Forms adapter |
| Kits Core | FluentCRM bridge (generic) | `aw_crm_on` (`core/fluentcrm.php:98`) | `org-people.php:43`, `org-people.php:120` | 2/2 | `kits_crm_*`, reached through `directoryweb_*` CRM events (§5.3) |
| Kits Core | FluentCRM bridge (generic) | `aw_crm_tag_ids` (`core/fluentcrm.php:136`) | `org-people.php:140*` | 0/1 | `kits_crm_*`, reached through `directoryweb_*` CRM events (§5.3) |
| Kits Core | Content model: campaigns, Opportunities (session D) | `aw_campaign_organiser` (`core/model.php:147`) | `listing-template.php:526*` | 0/1 | Kits Core content model; DirectoryWeb reads related items through a `directoryweb_related_items` filter |
| Kits Core | Content model: campaigns, Opportunities (session D) | `aw_campaign_partner_ids` (`core/model.php:156`) | `listing-template.php:532*` | 0/1 | Kits Core content model; DirectoryWeb reads related items through a `directoryweb_related_items` filter |
| Kits Core | Content model: campaigns, Opportunities (session D) | `aw_open_opportunities_meta_query` (`core/retention.php:85`) | `listing-template.php:507` | 1/1 | Kits Core content model; DirectoryWeb reads related items through a `directoryweb_related_items` filter |
| DirectoryWeb | Type and service roles | `aw_listing_type_slugs` (`core/tags.php:112`) | `directoryweb-rest.php:443`, `directoryweb-rest.php:897`, `directory-map.php:43*`, `directory-map.php:128*`, `directory-map.php:294*` +7 | 3/12 | `directoryweb_type_slugs`, `directoryweb_entry_types`, `directoryweb_service_names`, `directoryweb_service_slugs`, `directoryweb_type_icon` (slug lists and icons = saved site settings) |
| DirectoryWeb | Type and service roles | `aw_listing_types` (`core/tags.php:148`) | `listing-template.php:286`, `district-helplines.php:78*`, `district-helplines.php:84*`, `directoryweb-listings.php:436` | 2/4 | `directoryweb_type_slugs`, `directoryweb_entry_types`, `directoryweb_service_names`, `directoryweb_service_slugs`, `directoryweb_type_icon` (slug lists and icons = saved site settings) |
| DirectoryWeb | Type and service roles | `aw_listing_service_names` (`core/tags.php:153`) | `listing-profile.php:271*`, `listing-template.php:238`, `directoryweb-listings.php:437` | 2/3 | `directoryweb_type_slugs`, `directoryweb_entry_types`, `directoryweb_service_names`, `directoryweb_service_slugs`, `directoryweb_type_icon` (slug lists and icons = saved site settings) |
| DirectoryWeb | Type and service roles | `aw_service_slugs` (`core/tags.php:107`) | `claims-review.php:603*`, `district-helplines.php:82*`, `directory.php:59*`, `directory.php:522*`, `claims.php:211*` | 0/5 | `directoryweb_type_slugs`, `directoryweb_entry_types`, `directoryweb_service_names`, `directoryweb_service_slugs`, `directoryweb_type_icon` (slug lists and icons = saved site settings) |
| DirectoryWeb | Type and service roles | `aw_type_icon` (`core/hub-blocks.php:360`) | `directory-map.php:295*` | 0/1 | `directoryweb_type_slugs`, `directoryweb_entry_types`, `directoryweb_service_names`, `directoryweb_service_slugs`, `directoryweb_type_icon` (slug lists and icons = saved site settings) |
| DirectoryWeb | Verified mark | `aw_verified_mark` (`core/hub-blocks.php:502`) | `listing-template.php:418` | 1/1 | `directoryweb_verified_mark` |
| DirectoryWeb | Form links of a listing | `aw_report_url` (`core/form-blocks.php:93`) | `listing-profile.php:284`, `listing-template.php:83` | 2/2 | `directoryweb_report_url`, `directoryweb_form_listing` |
| DirectoryWeb | Form links of a listing | `aw_form_page_listing` (`core/form-blocks.php:101`) | `claims-review.php:131` | 1/1 | `directoryweb_report_url`, `directoryweb_form_listing` |
| DirectoryWeb | Place distances (India module) | `aw_district_centroid` (`core/related.php:136`) | `directory-map.php:199*`, `directory-map.php:215*`, `geocode.php:155` | 1/3 | `directoryweb_place_centroid`, `directoryweb_place_km` |
| DirectoryWeb | Place distances (India module) | `aw_district_km` (`core/related.php:146`) | `find-help.php:299*` | 0/1 | `directoryweb_place_centroid`, `directoryweb_place_km` |
| **Native** (DirectoryWeb) | Post type, meta | `aw_content_type_args` (`core/content-types.php:41`) | `bootstrap.php:20` | 1/1 | `register_post_type` with DirectoryWeb's own arguments |
| **Native** (DirectoryWeb) | Post type, meta | `aw_register_meta` (`core/meta.php:136`) | `bootstrap.php:44` | 1/1 | `register_post_meta` by `directoryweb_register_meta` (this PR); drop the Core call |
| **Native** (DirectoryWeb) | Post type, meta | `aw_sanitize_meta` (`core/meta.php:104`) | `listing-profile.php:229*` | 0/1 | the `sanitize_callback` of `register_post_meta` (`directoryweb_sanitize_meta`, this PR); drop the Core call |
| SourceWeb | Source logo | `aw_press_site_logo` (`core/press.php:293`) | `data-sources.php:181` | 1/1 | `sourceweb_site_logo` |


Constants and hooks from Core:

| Final home | Name | DirectoryWeb uses at | New name |
|---|---|---|---|
| DirectoryWeb | `AW_CORE_VERSION` | `directoryweb.php:38` (the Core check) | removed with the check |
| Saved site settings (DirectoryWeb, Settings API) | `AW_REVERIFY_DAYS` (default 180) | `listing-meta.php:419`, `directoryweb-rest.php:935` | `directoryweb_reverify_days` option |
| Saved site settings (DirectoryWeb) | `AW_SERVICES` (`core/model.php:26`) | `claims-review.php:80`, `:202`, `:232`; `listing-template.php:289`; `claims.php:37` | service roles in config (§4) |
| DirectoryWeb Fluent Forms adapter | `AW_FF_OPTION`, `AW_FF_SOCIAL`, `AW_FF_DATES` | `directoryweb-ui.php:281`, `directoryweb-rest.php:1000`; `claims-review.php:160`, `:162`, `:279`, `:498-500` | `DIRECTORYWEB_FF_*` (option names unchanged) |
| **Native** wp-cron (DirectoryWeb) | hook `aw_daily` | `listing-meta.php:466` | `directoryweb_daily` |

### 1.5 Options DirectoryWeb reads that Core's uninstall would delete

`plugins/animal-writes-core/uninstall.php` deletes these when "Delete this plugin's data" is ticked
(`aw_delete_data_on_uninstall`, `:57`). DirectoryWeb reads them, so **ownership moves to DirectoryWeb**: the option
names stay the same (saved site settings), DirectoryWeb's own `uninstall.php` (and its backup) takes them over, and **Core's uninstall
list must drop them**. That change ships before or with Core's deletion, never after.

| Option or transient | Deleted by Core at | DirectoryWeb uses at |
|---|---|---|
| `aw_district_helplines` | `uninstall.php:69` | `district-helplines.php:36` |
| `aw_place_intros` | `:75` | `place-intros.php:22` |
| `aw_ff_forms` (form IDs) | `:74` | `directoryweb-ui.php:281`, `directoryweb-rest.php:1000` (shared with the other forms: owner = the Kits Core forms bridge, DirectoryWeb reads its two keys) |
| `aw_ff_send_email` | `:66` | `directoryweb-rest.php:937` (shared like `aw_ff_forms`) |
| `aw_press_logos` | `:79` | `data-sources.php:165` (owner = SourceWeb, with the source logo) |
| `aw_geo_last_request` | `:93` | `geocode.php:50-57`, `directoryweb-rest.php:767` |
| `aw_gov_verified_live` | `:94` | `gov-verified.php:118`, `:240`, `:250`; `directoryweb-rest.php:947` |
| `aw_place_counts` | `:108` | `places.php:279`, `:289`, `:307`; `directoryweb-dashboard.php:165` |
| `aw_places_rewrite` | `:109` | `places.php:369-371` |
| transient `aw_dir_points` | `:141` | `directory-map.php:35`, `:67`, `:71`; `gov-verified.php:101` |
| transients `aw_featured_names`, `_aliases`, `_links`, `_shared` | `:146-149` | `featured.php:37-306` |
| transient `aw_gov_verified_count` | `:151` | `directory.php:633`; `gov-verified.php:100-141` |
| transient `aw_verified_count` | `:155` | `directory.php:503-506`; `claims-review.php:651` |
| transient prefixes `aw_pin_`, `aw_place_error_`, `aw_orgq_rl_`, `aw_geo_` | `:160` | `directory-map.php:45`, `:198`; `places.php:467-474`; `places-admin.php:108-120`; `org-people.php:304`; `geocode.php:44` |
| cron hooks `aw_featured_rescan`, `aw_geocode_one`, `directoryweb_stats_build`; transient `directoryweb_stats` | `:166-176`, `:140` | `featured.php:322-335`; `geocode.php:204-205`; DirectoryWeb's own uninstall already clears the `directoryweb_*` two |

Not DirectoryWeb's: `aw_social_on` and `aw_social_show` are read by SourceWeb (`sw/kits-hub.php:33`,
`sw/sourceweb-rest.php:877-1071`), so SourceWeb takes those over in the same way.

---

## 2. Dependency map

### 2.1 What DirectoryWeb calls (each one optional except WordPress and, today, Core)

| Calls | Kind | What | Where | Side |
|---|---|---|---|---|
| WordPress | required | post types, meta, REST, blocks, cron, capabilities | everywhere | A+F |
| Animal Writes Core | required **today**, removed by stage 6 | `aw_content_type_args`, tags, `aw_normalise_phone`, `aw_tel_url`, `aw_core_simple_table`, `aw_use_component_style`, `aw_press_site_logo`, `aw_theme_file_uri`, `aw_date` | `dw/bootstrap.php:17-45`, `dw/data-sources.php:124-179`, `dw/listing-template.php` | A+F |
| Kits Core | optional (shell UI) | admin shell, `kits_simple_table`, `kits_preview_only_ids` | `dw/directoryweb-ui.php`, `core/kits-config.php:28` | A |
| **SCF / ACF** | **first-class, optional** | 8 listing field groups: the primary editor on animalwrites.in (§6) | `directoryweb.php:96-115` | A |
| RankWeb | optional | listing schema, Directory H1, helpline check, sitemap reachability | `rw/schema.php:54-230`, `rw/robots.php`, `dw/directoryweb-dashboard.php:202-206` | F (schema, robots) / A (dashboard) |
| SourceWeb | optional | enrichment log and provenance, enrichment icons, profile extras | `dw/claims-review.php:545-550`, `dw/gov-verified.php:34-37`, `dw/listing-profile.php:296`, `dw/directoryweb-dashboard.php:264-275` | A+F |
| Fluent Forms | optional adapter | claim #6, report #7 | `core/forms-fluentforms.php:58-63`, `dw/claims-review.php:124-172` | F (form) / A (review) |
| FluentCRM | optional adapter | claimant contact, tags, note | `core/fluentcrm.php:114-130`, `:239`; `dw/org-people.php` | A |
| MailWeb | optional | reply signatures, the mail queue | `core/fluentcrm.php:335-340`, `core/mailweb-aw.php` | A |
| Rank Math | optional, through RankWeb | sitemaps, breadcrumbs | `rw/sitemap.php:111-253`, `rw/robots.php:230-254` | F |
| InsightWeb | none | — | — | — |

### 2.2 What calls the Directory

| Caller | Calls | Where | Side |
|---|---|---|---|
| RankWeb schema | `aw_listing_phones`, `aw_listing_hours`, `aw_hours_rows_spec`, `aw_listing_sources` | `rw/schema.php:54-160` | F |
| RankWeb FAQ feeders (session B) | `aw_listing_phones`, `aw_listing_hours`, `AW_WEEKDAYS` | `core/faq-data.php:206-363` | F |
| SourceWeb enrichment and ingest | `aw_sanitize_meta`, `aw_set_listing_list`, `aw_listing_phones`, `aw_listing_hours`, `aw_phone_never_publish`, `aw_listing_put_phone` | `sw/enrichment.php`, `sw/enrichment-cleanup.php`, `sw/ingest.php:140-347` | — (REST/cron) |
| Core bindings, hub blocks, forms, item head | `aw_listing_first_phone`, `aw_listing_phones`, `aw_listing_sources_html` | `core/bindings.php:129-133`, `core/forms-fluentforms.php:568`, `core/item-head.php:313` | F |
| Import tools (WP-CLI eval) | `aw_set_listing_list`, `aw_listing_put_phone`, `aw_data_sources` | `tools/directory/*.php` | A |
| REST | `directoryweb/v1/*`, `aw/v1/dir-points`, `aw/v1/dir-near` | `dw/directoryweb-rest.php:49-147`, `dw/directoryweb-listings.php:40-99`, `dw/directory-map.php:146-169` | A (`directoryweb/v1`) / F (`dir-*`) |
| WP-CLI | `aw redirects-import`, `aw gov-verify`, `aw geocode`, `aw-places`, featured, org people, place intros | `dw/places.php:408`, `dw/gov-verified.php:236`, `dw/geocode.php:279`, `dw/find-help.php:419`, `:507`, `dw/featured.php:472`, `dw/org-people.php:249`, `dw/place-intros.php:337` | A |
| Theme templates | listing blocks, bindings | `theme/templates/single-aw_listing.html`, `theme/patterns/find-help.php` | F |

After the move every caller keeps working through the `aw_*` wrappers in Core (§12). The wrappers are temporary: Core will be deleted, so every caller switches to `directoryweb_*` first (§1.2, §1.4).

---

## 3. The engine and its presets

### 3.1 The core model (domain-agnostic)

An **entry** (one post of the directory post type) has:

| Part | Generic meaning | This site today (default preset, `aw_` data prefix) |
|---|---|---|
| Entry types | Which kind of entry (several allowed), from the preset | core tags in the "type" role (`aw_listing_type_slugs()`, `core/tags.php:111-113`) |
| Field groups | Named groups of fields; each field has a key, a data type, a rule and visibility (public / team only) | the 48 keys plus the SCF list fields, grouped like the 8 SCF groups |
| Taxonomy roles | Core `category` and `post_tag` only, with a role per term set (§4) | types and services (tags), sections (categories) |
| Location (optional) | Place codes (an area hierarchy from a data file), area text, point (lat/lng) | LGD state and district, `aw_area`, `aw_lat`/`aw_lng` |
| Contact (optional) | Lists of phones, emails, links, hours | `aw_phones`, `aw_email(_alt)`, `aw_website(_alt)`, socials, `aw_opening_hours` |
| Media | Logo (featured image), gallery | thumbnail, `aw_gallery` |
| Trust | Claim status, verification (with source and date), re-check interval | Claimed (`aw_verified`), Verified (government documents), AWBI validity, 180 days |
| Provenance | Source per value, with URL and date | `aw_data_sources`, `aw_source_*`, the enrichment log |
| Visibility rules | **Never publish field X for entry Y or value V** | the never-publish phone list (one rule of this kind) |

**Site config** (settings on top of the preset; fixed in code on this site until stage 5): the post type slug, field prefix, labels,
permalinks (`directory`, `directory/org`), place data file. The seam is already on the branch:
`directoryweb_post_type()` → `aw_listing` and `directoryweb_meta_prefix()` → `aw_` (`dw/listing-meta.php:19-31`).

**Generalised privacy rule.** `directoryweb_never_publish( $field, $value, $entry_id )`: a list of rules, each one a
field (or `*`) with an entry ID (or `*`) and a value hash (or `*`). Today's phone list is the rule set
`{ field: phones, entry: *, value: <sha256> }`. It is checked on every write path (forms, enrichment, imports, admin
save) and on every read for display, REST and schema. On this site it is seeded from `DIRECTORYWEB_NEVER_PUBLISH_PHONES`.
Stage 3; where the rules are stored is open question Q5 (A).

### 3.2 The preset and site settings

DirectoryWeb ships **one** preset, `directoryweb-default`: a PHP array (later also JSON) `{ slug, labels, post_type,
prefix, permalinks, types, fields, groups, taxonomy_roles, places, schema_map, privacy, forms_map, crm_map }` with
generic defaults (`directoryweb_` prefix, `directoryweb_listing` type, as in the .org edition). A site overrides values
through a filter or the DirectoryWeb Settings screen (A). The engine reads only the resulting config; templates, schema,
forms and enrichment read fields through it.

**This site** runs `directoryweb-default` with its settings: data prefix `aw_`, post type `aw_listing`, today's keys,
terms, URLs, SCF groups and schema rules (`rw/schema.php:54-90`: NGO, VeterinaryCare, AnimalShelter, GovernmentOffice by
type tag). The settings are generated from what is in the code now, so nothing changes on live. Another site (for
example a music or a services directory) runs the same preset with its own settings.

### 3.2a Listing types and their schema change per site

Entry types, their fields and their schema.org mapping are **site settings, not engine code**. The same engine renders,
validates and marks up whatever types a site declares. The table shows how; the second and third rows are illustrations
only, not plans.

| Site | Entry types (core tags in the "type" role) | Type-specific fields (examples) | schema.org mapping |
|---|---|---|---|
| This site (today, `rw/schema.php:57-81`) | vet hospital/clinic/dispensary, shelter, gaushala, sanctuary, ABC centre, rescue group, helpline… | registrations (AWBI, Darpan, 12A/80G), animals served, areas served | `VeterinaryCare`, `AnimalShelter`, `GovernmentOffice`, else `NGO`; several types give a `@type` list |
| A music directory (illustration) | artist, band, venue, label, studio, teacher | genres, instruments, capacity, booking contact | `MusicGroup`, `Person`, `MusicVenue`, `Organization`, `LocalBusiness` |
| A services directory (illustration) | plumber, electrician, tutor, clinic | service area, licence number, price range | `LocalBusiness` subtypes (`Plumber`, `Electrician`, `ProfessionalService`, `MedicalClinic`) |

What changes per site, and how:
- **Types** [A]: add, rename or retire tags on the core Tags screen and list them in the site's `taxonomy_roles`. No code,
  no new taxonomy.
- **Fields** [A]: field groups in the site settings (and the matching SCF groups when SCF is used). New fields are
  registered natively with their rules; nothing renames stored keys.
- **Schema** [F]: `schema_map` maps each type tag to a schema.org type, and each field to a property. RankWeb (or the
  engine's own output when RankWeb is off) reads the map instead of today's hardcoded list. This site's map is generated
  from `rw/schema.php` so its markup stays identical.
- **Rules that need code** (today: a government vet from a government list becomes `GovernmentOffice`, and legal aid
  from NALSA is not an `NGO`, `rw/schema.php:69-79`) stay as small site rules on a filter (`directoryweb_schema_types`).

### 3.3 Integrations read the preset

| Integration | Reads from the config (preset + site settings) | Side |
|---|---|---|
| RankWeb schema | `schema_map` (type role term → schema.org type), field → property map | F |
| Theme rendering | field groups and labels through Block Bindings (`directoryweb/field`) | F |
| Forms adapters | `forms_map` (form field → preset field) | F (form) / A (mapping) |
| CRM adapters | `crm_map` (event → tags/lists) | A |
| Enrichment (SourceWeb) | field keys, list fields, the alternates (`_alt`) | — |

---

## 4. Taxonomies: tags and categories (one system per project)

**Rule (owner):** presets register **no taxonomies**. Every preset uses only core `category` and `post_tag`. A preset
says which core terms play which **role**. Places stay data (the LGD file plus meta), not a taxonomy.

**Today (live):**
- `category` on listings is attached by DirectoryWeb (`dw/bootstrap.php:21`); Core skips `aw_listing` while
  DirectoryWeb is loaded (`core/content-types.php:84-88`).
- `post_tag` is on all types (`core/tags.php:41`). One shared pool holds the listing org types (`abc-centre`,
  `animal-shelter`, `veterinary-clinic`…), the services (`rescue`, `sterilisation`, `vaccination`…; fixed list
  `core/model.php:25`, slugs `core/tags.php:106-109`) and Resource, Opportunity and Library labels. Icons are in
  `AW_TAG_ICONS` (`core/tags.php:25-33`).
- Places: `dw/places.php` (LGD file, `aw_place_counts`), not terms.
- Thin type pages: `rw/robots.php`.
- Term pages and term share are session D's (`core/term-page.php`).

**Plan (stage 3):**
- **Roles.** The preset declares `taxonomy_roles = { type: [slugs], service: [slugs], section: category }`. This site's
  settings list today's slugs (from `AW_TAG_ICONS` / `aw_service_slugs()`), so there is no term, slug or URL change.
  Grouping by term meta (`register_term_meta( 'post_tag', 'directoryweb_role', … )`, native, `show_in_rest`) would let a
  new site add terms without code. Whether to add it is open question Q6 (A).
- **Icons** move to the config (this site: `AW_TAG_ICONS` as is).
- **Admin [A]:** core term screens (Tags, Categories) and the core tag/category boxes on the edit screen. A role column
  or filter on the Tags screen uses core list-table hooks only. No custom term UI.
- **Front-end [F]:**
  - Archives keep today's URLs: `/tag/{slug}/`, `/category/{slug}/`, and the Directory type pages
    `/directory/{state}/{district}/{type}/` (`dw/directory.php`, rewrite rules).
  - Chips and the filter bar (`[aw_directory_filter]`, `dw/directory.php:655`) read roles from the preset.
  - Query Loop filtering uses core `taxQuery` on `post_tag`.
- **REST:** core terms are already `show_in_rest`; entries expose `tags`/`categories`.
- **Schema:** the preset's `schema_map` maps type terms to schema.org subtypes (today hardcoded in `rw/schema.php:57-81`).
- **Sitemaps:** RankWeb's (`rw/sitemap.php`) keeps the Directory pairs and the thin-page rule; the core sitemaps
  provider when RankWeb is off (§8).
- **Writes:** forms, CRM and enrichment write terms only through the engine (`directoryweb_set_terms( $id, $role, $slugs )`),
  which checks each slug against the role's list. Today the claim review and imports write tags directly.

---

## 5. Forms, CRM and enrichment: provider-agnostic contracts

### 5.1 Forms contract (DirectoryWeb)

**Intents:** `claim` (claim a listing), `report` (report or correct a listing), `submit` (submit a new listing).

**Normalised payload** (one shape for every provider):
```
{ intent, listing_id (0 for submit), fields: { <preset field key>: value, … }, lists: { phones: [...], hours: [...] },
  terms: { type: [...], service: [...] }, submitter: { name, email, phone, role, show_name },
  consent: { newsletter: 0|1, wording, data_use: 0|1 }, files: { logo, proof, photos }, source: { form, provider, url, at } }
```

**One entry point:** `directoryweb_receive( array $payload ): int|WP_Error`. It:
1. validates the intent, the listing and the required fields;
2. sanitises every field with `directoryweb_sanitize_meta` and the privacy rules;
3. creates the claim or report record;
4. fires the CRM events (§5.3).

It **never writes to the listing**: approval stays on Directory → Claims (`dw/claims-review.php:299`) [A], and the
DirectoryWeb Claims screen stays **read-only** [A].

**Today's flow (Fluent Forms, must keep working unchanged):**

| Step | Where | Side |
|---|---|---|
| Form registry: `contact`, `volunteer`, `claim`, `report` (IDs in option `aw_ff_forms`; live claim #6, report #7) | `core/forms-fluentforms.php:57-63` | A (config) |
| Extra posted fields, uploads (`logo_token`, `proof_token`, `photo_tokens`), +91 phones | `core/forms-fluentforms.php:64-70`, `:793-794` (`wp_ajax(_nopriv)_aw_ff_upload`) | F |
| Claim socials → `aw_instagram`, `aw_facebook`, `aw_x`, `aw_youtube`, `aw_linkedin` | `core/forms-fluentforms.php:73-79` | — |
| Claim pre-fill from the listing (public fields only) | `dw/claims-review.php:124-172` | F |
| Field map form → listing → enrichment field (`l_email` → `aw_email`, `phones`, `l_area` → `aw_area`, `l_address` → `aw_address`, `l_maps` → `aw_maps_url`, `l_hours`, `l_about`, `l_aliases`, `l_founded`, `l_head`, `l_animals`, `l_areas` → `aw_areas_served`, `l_services`, `l_languages`…) | `dw/claims-review.php:26-40` (`DW_CLAIM_L`) | — |
| Values normalised on submit; org picker (published listing only) | `core/forms-fluentforms.php:140-144`, `:525-528` | — |
| References `AW-C-` / `AW-R-`, phone de-dup, contact on submit | `core/forms-fluentforms.php:553-575`, `:690` (`fluentform/submission_inserted`) | — |
| Review and per-field apply (logged in SourceWeb's change log with Undo) | `dw/claims-review.php:536-560`, `:627` | A |

**Adapters** (stage 3+; each a thin optional file `dw/forms/<provider>.php`, loaded only when that plugin is active):

| Adapter | Hook it listens to | Status |
|---|---|---|
| Fluent Forms | `fluentform/submission_inserted`, `fluentform/rendering_field_data_*` | **current**: wrap today's flow, behaviour identical |
| Gravity Forms | `gform_after_submission`, `gform_pre_render` (pre-fill) | design |
| WPForms | `wpforms_process_complete`, `wpforms_field_properties` | design |
| Contact Form 7 | `wpcf7_before_send_mail` (+ `wpcf7_posted_data`) | design |
| Formidable | `frm_after_create_entry` | design |
| Native block form | a DirectoryWeb block (`directoryweb/form`, block.json) posting to `directoryweb/v1/receive` (REST, nonce plus honeypot/ALTCHA) | design: also the **no-plugin fallback** and the shortcode |

The visitor form is [F] (Kits form rules). The **field-mapping settings** are [A]: one Settings API section per active
adapter (form field → preset field, select boxes), plus a filter `directoryweb_forms_map` for code. **This PR writes no
adapter code**; today's Fluent Forms code is untouched here. Its final home is the Kits Core forms bridge plus DirectoryWeb's claim/report adapter (§1.2).

### 5.2 Enrichment write contract (SourceWeb stays the engine)

**Today:** all enrichment lives in SourceWeb. None is in Core.

| Side | What | Where |
|---|---|---|
| — | Rules, change log table, apply/queue/both modes, never-publish check | `sw/enrichment.php:8-30` (rules), `:376` `aw_enrich_write`, `:636-680` (protected = Claimed or an approved claim; empty → apply; existing → keep both, unverified; claimed/verified/low confidence → queue), `:682` `aw_enrich_never_publish`, `:698` `aw_enrich_add_row` |
| — (REST) | Worker API `aw/v1/enrich/config|queue|results|index|cleanup` | `sw/enrichment.php:1042-1046` |
| A | Review screen, provenance box, sources and icons | `sw/enrichment-admin.php:22-282` |
| F | Profile extras (live Google block, news mentions) | `sw/enrichment-front.php:62-219` |
| — | Places, merge/unmerge | `sw/enrichment-cleanup.php:31-340` |
| — | DirectoryWeb's uses of it (guarded) | `dw/claims-review.php:545-550`, `:655-656`; `dw/claims.php:207` (`aw_claim_before_apply`); `dw/gov-verified.php:34-37`; `dw/listing-profile.php:296`; `dw/directoryweb-dashboard.php:264-275`; `dw/directoryweb-rest.php:281-286`, `:576-586` |
| — | Core | none: no enrichment code is in Core, so nothing moves from it |

**Contract (stage 3, documented here, not built on this PR):**
```
directoryweb_propose( int $entry_id, array $values, array $source ): array
  $values: { <preset field>: value | list }, $source: { name, url, date (Y-m-d), confidence: high|medium|low, run }
  returns per field: applied | kept_both | queued | blocked (privacy) | skipped (identity unresolved)
```
It applies the **conflict rules** (owner): claimed content first (a Claimed or Verified entry is never changed; its
values queue for review); then the organisation's own website over other sources; an empty field is filled when
confidence is high; when unsure, both values are kept with an "unverified" mark (`_alt` keys, list union). Privacy rules
always win. It fires `directoryweb_value_proposed` and `directoryweb_value_applied` so SourceWeb (or any source plugin)
keeps its own change log. SourceWeb calls it guarded (`function_exists( 'directoryweb_propose' )`); DirectoryWeb works
with SourceWeb absent (the change log, review queue and Undo are then simply not there).

Today's decision code (`aw_enrich_mode_decide`) stays in SourceWeb until the contract exists; then SourceWeb's
`aw_enrich_write` calls the contract for the final write and keeps the queue and the log.

### 5.3 CRM events

DirectoryWeb fires, with a stable payload `{ event, entry_id, entry_url, record_id, intent, submitter, consent, fields_changed, by, at }`:

`directoryweb_claim_submitted`, `directoryweb_claim_approved`, `directoryweb_claim_rejected`,
`directoryweb_report_submitted`, `directoryweb_listing_published`, `directoryweb_listing_updated`.

| Adapter | Listens and does | Status |
|---|---|---|
| FluentCRM | **current** (`core/fluentcrm.php`): the claimant becomes a transactional contact with tag `claimant` (`:121`); `org-pending` / `org-approved` (`:123-124`, the owner sets `org-approved` by hand); claim note on the contact (`:239`); one list "Newsletter" (`AW_CRM_LIST` `:31`); the newsletter box stored as 1/0 with its form and wording (`nl_box_last`, `nl_box_form`, `nl_box_consent` `:389-393`, `aw_crm_nl_box` `:496`). **No form unsubscribes anyone**, and a contact is never marked "can't email" | wrap today's code in stage 3, identical |
| Mailchimp | tags and merge fields on the audience | design |
| Brevo | contact attributes and lists | design |
| HubSpot | contact plus a timeline note | design |
| MailWeb | team notices (new claim, report) with the locked signatures | design |

Admin [A]: one Settings API section per adapter (event → tags/lists), plus filter `directoryweb_crm_map`.

---

## 6. SCF: a first-class integration, never a requirement [A]

- **Fields are DirectoryWeb's.** They are registered natively (`register_post_meta` with DirectoryWeb's own
  sanitising, `dw/listing-meta.php`, branch). SCF never owns a field.
- **The 8 groups are the primary editor on animalwrites.in:** `group_aw_listing`, `_address`, `_hours`,
  `_registrations`, `_about`, `_online`, `_photos`, `_claimed` (`DIRECTORYWEB_SCF_GROUPS`, `directoryweb.php:99`).
  - Loaded from and saved to `plugins/directoryweb/acf-json` (`acf/settings/load_json` `:101-107`,
    `acf/settings/save_json/key=` `:108-110`).
  - Fed to Core's registry through `aw_scf_groups` (`:115`, applied in `core/scf.php:84`).
  - The edit-screen group ordering is in `core/edit-screens.php:236+`.
- **The one-pager** (`dw/listing-profile.php`, the native metabox) is DirectoryWeb's own admin UI. It shows when SCF is
  off. On this site it stays the fallback, not forced visible (owner, 17 Nov).
- **The gap that was there:** the gate called Core's `aw_scf_active()` (`core/scf.php:24-26`) under `function_exists`.
  With Core gone it would fail open, and both the one-pager and the SCF boxes would show. **Fixed on this branch:**
  `directoryweb_scf_active()` (`dw/listing-profile.php`) is a fallback chain: `kits_scf_active()` when Kits Core has it
  (session D, PR #139), else Core's `aw_scf_active()`, else `function_exists( 'acf_add_local_field_group' )`. It gives
  the same answer as today on live whichever PR merges first. The name matches the .org edition
  (`directoryweb-org/includes/listing-profile.php:66`).
- **Next (session D, Kits Core track):** the generic SCF bridge moves to Kits Core as `kits_scf_*` (PR #139 adds
  `kits_scf_active()`, `kits_scf_json_folder( $dir, $keys )` and `kits_scf_groups()`): the active check, the group
  registry filter, the JSON load/save paths and the edit-screen group ordering. Core's `aw_scf_*` go when Core is deleted.
- **Follow-up step, after #139 is merged (not on this PR):** DirectoryWeb's acf-json registration
  (`directoryweb.php:96-115`: `load_json`, `save_json/key=`, `aw_scf_groups`) switches to
  `kits_scf_json_folder( DIRECTORYWEB_DIR . 'acf-json', DIRECTORYWEB_SCF_GROUPS )`, guarded by `function_exists`, with
  today's filters kept as the fallback. Then the gate's `aw_scf_active()` step can go when Core goes.
- **Nothing in stage 2 assumes SCF is active.** The only `acf_*` touch in DirectoryWeb is the filter registration
  (harmless without SCF) and the gate above.
- **.org edition:** ships the acf-json groups, supports SCF fully, works with SCF absent.

---

## 7. Front-end: listing UI on the shared Kits layout and card system [F]

Today's inventory:

| What | Where |
|---|---|
| Profile template | `plugins/animal-writes-core/theme/templates/single-aw_listing.html` (20 `animal-writes/*` blocks: `listing-about`, `-contact`, `-location`, `-services`, `-organisation`, `-registrations`, `-profiles`, `-support`, `-photos`, `-activity`, `-press`, `-opportunities`, `-campaigns`, `-help`, `item-meta`, `item-sources`, `item-share`, `item-faq`, `item-more`, `breadcrumb`) |
| Block registration | `core/hub-blocks.php:41`, `:50-63` (session D) |
| Block callbacks | `dw/listing-template.php:116-566` (contact `:165-223`, sources `:395`, Claimed mark `:405`, `:568`) |
| Directory, state, district, type pages | `theme/templates/archive.html`, `page-hub.html`; query and headings `dw/directory.php` |
| Filter bar | `[aw_directory_filter]` `dw/directory.php:655`; `themes/kits-by-wugweb/assets/js/kit.js` |
| Small-district card | `dw/local-cards.php:25-44` |
| Map | `dw/directory-map.php:313-314`, `plugins/directoryweb/assets/css/dir-map.css` (22 lines), `assets/js/dir-map.js` |
| CSS | `theme/assets/css/aw-listing.css` (150 lines, profile), `aw-directory.css` (131, Directory pages), `aw-lists.css` (145, list rows and cards) |
| Patterns | `theme/patterns/find-help.php`, `help-now.php` |

**Plan (stages 4-5).** Every listing list (Directory pages, Home find-help, related, "Featured in", campaign partners)
uses the shared **Kits layout system** (carousel, grid, bento, stacked, masonry, featured + list) and a **listing card
variant** in `kit.css`, both planned by session D in Kits Core. DirectoryWeb supplies the fields through Block Bindings
(`directoryweb/field`) from its preset: name, type, area, Claimed/Verified badges, contact buttons, map link. Long
term there is no DirectoryWeb-only card CSS: `aw-lists.css` and `aw-directory.css` fold into the Kits component, and
`aw-listing.css` keeps only profile-specific layout until that, too, is a Kits pattern.

Rules kept:
- 720px main column on listing pages;
- breakpoints 767/768 and 1199/1200 only;
- no folds or "+" expanders;
- date first in meta rows;
- titles never underline.

The profile becomes a plugin block template (`register_block_template`, WP 6.7+) so another theme renders it.

**This PR changes no markup or CSS.**

---

## 8. WP-native integration

| Side | API | What DirectoryWeb uses today | Custom today | Native target | Effort / risk |
|---|---|---|---|---|---|
| A+F | `register_post_type` / `register_taxonomy` | type via Core's builder; core `category`/`post_tag` | builder in Core | own `register_post_type` from the preset; core taxonomies only | S / low (stage 3) |
| A+F | `register_post_meta` + `show_in_rest` + REST schema | **native, this PR** (48 keys) | list fields (phones/hours) stored in SCF repeater format, not in the REST schema | add the list fields as `object`/`array` meta with a JSON schema (read view), keeping the stored rows | M / medium |
| A | Core REST before custom | `directoryweb/v1/*` for the shell; `aw/v1/dir-*` | custom listing list and profile routes | core `wp/v2/aw_listing` with `_fields` and registered meta; keep custom only for counts and bulk | M / medium |
| F | block.json, Block Bindings, Query Loop variations, block templates and patterns, theme.json | bindings source `animal-writes/field` (Core); PHP-registered blocks | blocks without block.json; templates in Core's theme layer | block.json per listing block, `directoryweb/field` bindings source, a Query Loop variation "Directory listings", plugin block templates | L / medium (stage 4) |
| F | Interactivity API | map, Near me, filter: plain JS (`dir-map.js`, `kit.js`) | custom JS | Interactivity API store for filter and Near me | M / medium |
| F | Template hierarchy | `single-aw_listing`, archive via Core's theme layer | theme-parts filters | plugin templates as fallbacks under the theme's own | S / low |
| A | Capabilities / `map_meta_cap` | `manage_directoryweb` (`dw/directoryweb-caps.php`), core caps on the type | type uses `post` caps | own `capability_type` with `map_meta_cap` (needs a role grant on activation) | M / medium |
| A | Revisions for meta | type supports revisions; meta not revisioned | — | `revisions_enabled` on chosen meta (WP 6.4+) for contact fields | S / low |
| A | WP_List_Table, Settings API, core notices | Claims review is WP_List_Table; shell screens (Kits) for dashboard/listings/settings | the Kits shell | stays; adapter mapping settings use the Settings API | — |
| — | WP-Cron | `aw_daily` (Core's event), geocode one-offs, featured rescan | — | own `directoryweb_daily` event when Core goes (stage 6); no Action Scheduler (not present) | S / low |
| F | Core sitemaps / robots hooks | RankWeb (Rank Math) | — | `wp_sitemaps_add_provider` and `wp_robots` when RankWeb is off | S / low |
| A | Privacy exporter / eraser | none for claim/report submitters | — | `wp_privacy_personal_data_exporters` / `_erasers` for claim and report records by email | M / low |
| A+F | i18n | text domain `directoryweb`, `.pot` | some admin strings and meta descriptions untranslated | translate meta descriptions and notices | S / low |
| A | Multisite | not tested | options per site | per-site options and caps; network activation grants caps per site | S / low |
| A | WP-CLI | 8 commands under `aw` / `aw-places` | `aw` namespace | `wp directoryweb …` aliases, old names kept | S / low |
| A | Abilities API (WP 6.9+) | none | — | read abilities (find entries, get entry) and gated write (propose value) for agent surfaces | M / low |

---

## 9. A fresh site with another theme and no Core

| Need | Today | Plan |
|---|---|---|
| Post type | DirectoryWeb registers it with Core's builder | Stage 3: own arguments from the preset |
| Meta | **This PR**: DirectoryWeb registers its keys | prefix from the preset (stage 5) |
| Phones, hours, never-publish | **This PR**: DirectoryWeb's own | generalised privacy rules (stage 3) |
| Data sources | **This PR**: DirectoryWeb's own file | — |
| SCF gate | **This PR**: self-contained | — |
| Tags, places, bindings, templates | Core / DirectoryWeb mix | stages 3-4 |
| Settings [A] | DirectoryWeb → Settings (`dw/directoryweb-ui.php:99`) | preset picker, adapter mapping sections |
| Activation | `directoryweb_activate` (`dw/bootstrap.php:36-49`): type, rewrites, meta (now DirectoryWeb's too), capability, one flush | + preset defaults |
| Uninstall | `plugins/directoryweb/uninstall.php` + backup | unchanged: deleting the plugin never deletes entries |

**.org edition** (`origin/org-editions`, `plugins/directoryweb-org/`) already uses `directoryweb_sanitize_meta`,
`directoryweb_listing_phones/hours`, `directoryweb_set_listing_list`, `directoryweb_listing_put_phone`,
`directoryweb_listing_first_phone`, `directoryweb_phone_never_publish`, `DIRECTORYWEB_WEEKDAYS`,
`DIRECTORYWEB_LIST_FIELDS`, `DIRECTORYWEB_NEVER_PUBLISH_PHONES`, the `directoryweb_never_publish_phones` filter
(`includes/core-lite.php:12-38`, `:116-261`, `:388`), `directoryweb_register_meta` (`includes/standalone.php:56`) and
`directoryweb_scf_active` (`includes/listing-profile.php:66`). This PR uses **the same names and signatures**, so the
editions converge on the engine. The .org edition is `directoryweb-default` with its own defaults (prefix `directoryweb_`).
Nothing changes on the `org-editions` branch.

---

## 10. Use cases across sites

"Another site" means any other directory on the same engine, for example a music or a services directory. Its details
are not guessed here.

| Need | This site today | Another site (e.g. a music or a services directory) | Generic model covers it? |
|---|---|---|---|
| Several types per entry | org types (NGO, vet, shelter, helpline…) | its own types | yes (type role over core tags) |
| Services / offerings | services list | its own offerings | yes (service role) |
| Place hierarchy from a data file | LGD state/district (India) | a city/country list, or none | yes if the site ships its place file; **not** for free-form or global places without a data file |
| Entry without address/point | helplines, feeders | e.g. online-only entries | yes (location optional) |
| Contact lists | phones (+WhatsApp), emails, links, hours | same kinds | yes |
| Claim by the entry's owner | yes, Fluent Forms | any form provider | yes (forms contract + adapters) |
| Official verification | Verified (AWBI, Darpan, 12A/80G) | a different register, or none | yes as "verification sources" in config; the register checks themselves are site modules |
| Source per value | yes | yes | yes |
| Privacy takedowns | phone list | any field | yes (generalised rule) |
| Relations | campaigns, Opportunities, News "Featured in" | its own content | **partly**: the engine offers a generic "related entries" field; site-specific relations stay in the site's own plugin |
| Events / dates on entries | no | possibly | **does not fit yet**: needs an event model or a link to an events plugin |
| A person as an entry | individual feeders (Q2) | possibly | yes if `Person` is a schema type in the config |
| National entries with no place | 1962 and similar (Q3) | possibly | yes with a "national" place value |

Does **not** fit the generic model as it stands: AWBI/Darpan-specific checks (`dw/gov-verified.php`), LGD/India Post PIN
lookup (`dw/places.php:494`), district helplines (`dw/district-helplines.php`), "Find help near you" place detection in
text (`dw/find-help.php`). These become optional India modules (places, PIN lookup, government registers), switched on
in this site's settings, never part of the preset.

---

## 11. Admin and front-end, file by file (DirectoryWeb)

| Side | Files |
|---|---|
| A | `claims.php`, `claims-review.php`, `directoryweb-ui.php`, `directoryweb-rest.php`, `directoryweb-listings.php`, `directoryweb-dashboard.php`, `directoryweb-caps.php`, `directoryweb-help-tab.php`, `directoryweb-shape.php`, `listing-profile.php` (one-pager), `places-admin.php`, `map-picker.php`, `place-intros.php` (screen), `plugin-info.php`, `uninstall-backup.php`, `acf-json/*` |
| F | `directory.php`, `directory-map.php`, `listing-template.php`, `local-cards.php`, `find-help.php`, `district-helplines.php`, `featured.php` (output), `org-people.php` (shortcode), `data-sources.php` (labels), `unverified.php` (marks) |
| — (data, cron, REST, CLI) | `listing-meta.php`, `places.php`, `geocode.php`, `gov-verified.php`, `bootstrap.php` |

This PR's changes by side:
- [—] `listing-meta.php`, `data-sources.php` (moved), Core `meta.php`/`cron.php`;
- [A] `listing-profile.php` (the gate), `directoryweb-rest.php` (Settings fact), `claims-review.php`, `claims.php` (renamed calls);
- [F] `listing-template.php`, `directory.php`, `district-helplines.php` (renamed calls only, same output).

---

## 12. DB safety

- Code moves, data does not. No migration, no key renamed, no new stored option.
- **The data prefix stays `aw_` on this site; code and preset names never use `aw`.** The preset is `directoryweb-default`;
  `aw_` appears only as this site's configured data prefix and in the stored names below.
- `aw_*` meta keys, `aw_listing`, SCF field keys (`field_aw_listing_*`), terms, slugs, URLs, options and the `aw_daily`
  hook stay.
- Registration is identical per key (type, description, single, REST, auth, sanitising). `aw_meta_schema()` still returns
  every key (`core/scf.php:196-200` reads it); `aw_register_meta()` skips the pairs DirectoryWeb registers.
- Proof (branch): a one-off parity run against `origin/wordpress-dev`'s `meta.php` gave the same 114 `register_post_meta`
  calls, identical sanitising for every key × 13 values, and identical stored rows for the phone/hours lists;
  `plugins/directoryweb/tests/listing-meta-tests.php` (25 checks) runs in the Deploy check.
- Wrappers keep every old name (`aw_sanitize_meta`, `aw_listing_phones`, `aw_listing_hours`, `aw_set_listing_list`,
  `aw_listing_put_phone`, `aw_listing_first_phone`, `aw_hours_rows_spec`, `aw_phone_never_publish`, `aw_daily`), each
  guarded. Without DirectoryWeb, reads return empty and writes do nothing but call `_doing_it_wrong`.
- The hash list moves to `DIRECTORYWEB_NEVER_PUBLISH_PHONES` (same hash); the old filter still runs inside the new one.

## 13. Staged plan

1. **Scope** (this document). ✔
2. **This PR: the safe engine split.** The listing data model in `dw/listing-meta.php`, `data-sources.php` moved, the
   re-verification job, the SCF gate fallback chain, the config seam. Follow-up once PR #139 is merged: acf-json
   registration through `kits_scf_json_folder()` (§6). This site's settings are implicit: today's values, in code.
3. **Preset and contracts.**
   - the `directoryweb-default` preset array, and this site's settings generated from today's code;
   - own post type arguments (with session D);
   - taxonomy roles over core tags/categories;
   - generalised privacy rules;
   - `directoryweb_receive` with the Fluent Forms adapter wrapping today's flow;
   - CRM events with the FluentCRM adapter wrapping today's code;
   - `directoryweb_propose` with SourceWeb calling it.
   All with no behaviour change on live.
4. **Front-end without Core's theme layer [F].** block.json for the listing blocks, the `directoryweb/field` bindings
   source, plugin block templates, a Query Loop variation, and the Kits layout plus listing card (with session D).
   Render-compare live pages before and after.
5. **Other sites [A+F].** Site settings in the Settings screen, the prefix/slug filters, the default preset for the .org
   edition, the other form/CRM adapters, the privacy exporter/eraser, the Abilities API.
6. **Core deleted.** Before deletion:
   - every DirectoryWeb call in §1.4 is switched and guarded;
   - SourceWeb, RankWeb, the import tools and the theme call `directoryweb_*`;
   - DirectoryWeb has its own `directoryweb_daily` event;
   - the §1.5 options move to DirectoryWeb's uninstall and leave Core's;
   - the Core check in `directoryweb.php:35-46` is removed.
   Then `animal-writes-core` is deleted, with no code left in it.

## 14. Risks

| Side | Risk | Guard |
|---|---|---|
| — | A key registered twice or not at all | `aw_register_meta` skips exactly DirectoryWeb's (key, type) pairs; parity test |
| — | Sanitising changes for a key | rules copied unchanged; parity run over every key |
| — | Load order (Core includes `meta.php` before DirectoryWeb) | wrappers resolve at call time; nothing calls them at include time |
| — | DirectoryWeb missing | reads empty, writes `_doing_it_wrong` (until Core is deleted) |
| — | Core deleted before a §1.4 call is switched | every call guarded; the Core check in `directoryweb.php:35-46` is removed only after the last switch |
| A | "Delete this plugin's data" on Core removes Directory options | §1.5: Core's uninstall list drops them first; DirectoryWeb's uninstall and backup own them |
| A | One-pager and SCF boxes both show after Core goes | gate is self-contained now (§6) |
| A | Takedown hash added in the old place | `meta.php` says where the list lives; docs and pending updated |
| A+F | Plugin Check pulls all of Core in | inline `phpcs:ignore` only where the Deploy check reports pre-existing ERROR rows |
| — | Sessions B/D edit Core at the same time | this PR touches only `meta.php`, `cron.php`, `data-sources.php` (removed), one line of `animal-writes-core.php`, `data/edit-screen-map.json` |
| F | Stage 4 changes markup | render-compare on live pages, one template at a time |
| A+F | Site settings drift from live | this site's settings are generated from today's code and checked by a parity test like this PR's |

## 15. Open questions for the owner

| # | Side | Question |
|---|---|---|
| Q1 | A+F | May DirectoryWeb own the listing type's arguments (stage 3), so `content-types.php` (session D) no longer lists `aw_listing`? |
| Q2 | F | Individual feeders: a person entry (no organisation, no address), or out of scope? |
| Q3 | F | National helplines (1962 and similar) with no state/district: entries with a "national" place, or only the helplines page? |
| Q4 | A | Is a DirectoryWeb running without Core a goal on this site, or only for the .org edition and other sites? |
| Q5 | A | Privacy rules (takedown hashes): in code as now, or in an owner-only Settings field? |
| Q6 | A | Term roles: keep slug lists in the site settings, or add a `directoryweb_role` term meta (native, `register_term_meta`) so a new site groups its own tags? |
| Q7 | F | The "Submit a new listing" intent: a public form on this site, or only claim and report? |
