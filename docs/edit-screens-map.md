# Edit screens: boxes, fields and where they are used

Snapshot of live, 1 Nov 2026.

**Sources**

- Code: `wordpress-dev` at `3c5cf6b4`. All `file:line` refs below are on that commit. Short paths: `core/` = `plugins/animal-writes-core/includes/`, `seo/` = `plugins/aw-seo-extras/includes/`, `src/` = `plugins/aw-sourcing/includes/`, `kits/` = `plugins/kits-core/includes/`, `acf-json/` = `plugins/animal-writes-core/acf-json/`.
- Live runtime: the boxes each edit screen registers (`add_meta_boxes`, `add_meta_boxes_{type}`, `do_meta_boxes`), with and without admin code loaded. The callback file:line for our boxes matches the repo lines below, so the live site runs this code.
- Live database: meta keys per post type with row counts, term meta per taxonomy, Rank Math options, the admin account's saved box order.

**Active plugins (live):** animal-writes-core, aw-seo-extras, mailweb, seo-by-rank-math 1.0.280, secure-custom-fields 6.9.7, google-site-kit 1.190.0, litespeed-cache 7.9.2, link-whisper 0.9.8, fluent-crm 3.3.1, fluentform 6.2.16, simple-history, updraftplus, performance-lab + its modules (auto-sizes, dominant-color-images, embed-optimizer, image-prioritizer, optimization-detective, webp-uploads, speculation-rules, view-transitions, nocache-bfcache, performant-translations). **Not active:** Advanced Ads, Fluent SMTP, aw-sourcing as a separate plugin row (its files load; the provenance list in the listing box "Claimed and sources" comes from `plugins/aw-sourcing/`), kits-core as a row (its includes load). On live the plugins and themes run from `public_html/aw_deploy/`, not `wp-content`.

**Edit-screen UI by plugin:** Rank Math (post sidebar/metabox + term box + term description editor), SCF (19 field groups, plus a block bindings sidebar panel), LiteSpeed (side box, public types), Link Whisper (post, page; box and a target keyword sidebar panel), WordPress core boxes, and ours. The themes and Mailweb add no edit-screen box (Mailweb adds a Dashboard widget, §8). Site Kit, FluentCRM, Fluent Forms, UpdraftPlus, Simple History and the Performance Lab modules add **no** post or term edit-screen box (none registered at runtime; Simple History only logs `created_term`).

**How to read the order.** WordPress prints contexts side, normal, advanced; inside each, priority high → core → default → low; then the user's saved order (`meta-box-order_{type}`) overrides. Block-editor types (post, page, aw_legal, aw_campaign, aw_opportunity, aw_listing) replace every core box flagged `__back_compat_meta_box` with a sidebar panel; Custom Fields shows only when switched on in Preferences → Panels; all other boxes (ours, SCF, LiteSpeed, Link Whisper) show in the meta-box area (normal/advanced at the bottom, side in the Settings sidebar). Classic-editor types: aw_news, aw_press, aw_message, aw_claim, aw_report, attachment.

**Boxes by plugin**

General (all types, WordPress): Publish, Categories, Tags, Linked campaigns, Excerpt, Featured image, Author, Slug, Revisions, Attributes, Discussion, Custom Fields; on the Dashboard, At a Glance, Activity, Quick Draft, Site Health Status, WordPress Events and News.

The SCF groups ship in Animal Writes core (`acf-json/`); SCF only renders them.

| Plugin | Box | Purpose |
|---|---|---|
| Animal Writes core | Related | Links |
| Animal Writes core | Linked from | Links |
| Animal Writes core | Volunteers | People |
| Animal Writes core | Coverage | Details |
| Animal Writes core | Review (claims) | Review |
| Animal Writes core | Report | Report |
| Animal Writes core | Organisation details (fallback) | Details |
| Animal Writes core | Sponsored (fallback) | Details |
| Animal Writes core | Resource details (SCF, post) | Details |
| Animal Writes core | Source (SCF, News) | Sources |
| Animal Writes core | Source (SCF, Library) | Sources |
| Animal Writes core | Document (SCF, Library; includes Issued by) | Details |
| Animal Writes core | Event (SCF, Campaign) | Details |
| Animal Writes core | Dates and state, Document, Organisations, Letters and share (SCF, Campaign) | Details |
| Animal Writes core | Outcome (SCF, Campaign) | Campaign outcome |
| Animal Writes core | Opportunity details (SCF) | Details |
| Animal Writes core | Contact, Opening hours, Registrations, About and services, Online, Photos and support (SCF, listing) | Details |
| Animal Writes core | Address and place (SCF, listing; includes Place) | Place |
| Animal Writes core | Claimed and sources (SCF, listing; includes Where these details come from) | Sources |
| Animal Writes core | Photo (category, tag), Banner image (category) | Image |
| Animal Writes core | Find help: listing types (category) | Find help |
| Animal Writes core | Guides linking here (tag) | Links |
| AW SEO Plus | FAQ (post screens, category and tag screens) | FAQ |
| AW SEO Plus | Search Insights report boxes (§8) | SEO report |
| Kits Core | Image context, Focus keyphrase (media) | Image |
| Rank Math SEO | Rank Math SEO (posts, terms) | SEO |
| Rank Math SEO | Rank Math sidebar panel (block editor) | SEO |
| Rank Math SEO | Content AI (News) | SEO |
| Rank Math SEO | Description editor (category, tag) | SEO |
| Rank Math SEO | Link Suggestions | Links |
| Secure Custom Fields | SCF block bindings sidebar panel (block editor) | Block bindings |
| LiteSpeed Cache | LiteSpeed | Image / cache |
| Link Whisper | Link Whisper Suggested Links; target keyword sidebar panel | Links |

Dashboard widgets are listed in §8.

---

## 1. Boxes per post type, screen order

Legend: **src** = where the box comes from. **cond** = when it shows. SCF box id = `acf-{group key}` (seen in the admin account's `closedpostboxes_aw_legal`).

### post (Resources) — block editor

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish (sidebar panel) | submitdiv | core | side/core | back-compat → panel |
| 2 | Categories | categorydiv | core | side/core | panel |
| 3 | Tags | tagsdiv-post_tag | core | side/core | panel |
| 4 | Linked campaigns | tagsdiv-aw_campaign_link | core box for our taxonomy `aw_campaign_link` | side/core | panel |
| 5 | Resource attributes (template) | pageparentdiv | core | side/core | panel; shows because the theme has templates for posts |
| 6 | LiteSpeed | litespeed_meta_boxes | LiteSpeed `litespeed-cache/src/metabox.cls.php:76` | side/core | public types only |
| 8 | Featured image | postimagediv | core | side/low | panel |
| 9 | Excerpt | postexcerpt | core | normal/core | panel |
| 10 | Custom Fields | postcustom | core | normal/core | only if enabled in Preferences |
| 11 | Slug | slugdiv | core | normal/core | panel |
| 12 | Related | aw_related | ours, `core/model.php:124` (cb :144) | normal/default | type has a relation in `aw_relations()`, or is post |
| 13 | Resource details | acf-group_aw_resource | SCF `acf-json/group_aw_resource.json` | normal | location post_type == post |
| 14 | Linked from | aw_linked_from | ours, `core/model.php:128` (cb :173) | normal/low | type is a relation target (aw_rel_guide → post) |
| 15 | FAQ | aw-faq | ours, `seo/faq.php:797` (closure :800) | normal/low | `aw_faq_post_types()` (public types minus `aw_faq_off_types`, which is empty on live) |
| 16 | Link Whisper Suggested Links | wpil_link-articles | Link Whisper `core/Wpil/Base.php:764` | advanced/default | `manage_categories` cap; types from Link Whisper settings (post, page) |
| — | Rank Math SEO | rank_math_metabox (sidebar plugin in block editor) | Rank Math `includes/admin/metabox/class-metabox.php:161` | normal (back-compat → sidebar) | `titles.pt_post_add_meta_box` = on. Not captured at runtime (Rank Math's Screen loads only on post.php); found by code + options |
| — | Sponsored (fallback) | aw-sponsored | ours, `core/pages.php:325` | side/default | **not shown**: only when SCF is off |
| — | Rank Math (sidebar panel: SEO, Schema, Content AI) | panel-rank-math | Rank Math (JavaScript) | block editor sidebar | not a box; listed from the map |
| — | Link Whisper target keyword (sidebar panel) | panel-link-whisper | Link Whisper (JavaScript) | block editor sidebar | post, page; listed from the map |
| — | SCF block bindings (sidebar panel) | panel-scf-bindings | SCF (JavaScript) | block editor sidebar | listed from the map |

Not added: formatdiv (theme has no post-formats), commentsdiv/commentstatusdiv (no comments support), authordiv (no author support), revisionsdiv (shown by core only when the post has 2+ revisions).

### page — block editor

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish | submitdiv | core | side/core | panel |
| 2 | Page Attributes | pageparentdiv | core | side/core | panel |
| 3 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core | hidden by the admin account (`metaboxhidden_page`) |
| 4 | Featured image | postimagediv | core | side/low | panel |
| 5 | Custom Fields | postcustom | core | normal/core | if enabled |
| 6 | Slug | slugdiv | core | normal/core | panel |
| 7 | Author | authordiv | core | normal/core | panel |
| 8 | FAQ | aw-faq | ours `seo/faq.php:797` | normal/low | as post |
| 9 | Link Whisper Suggested Links | wpil_link-articles | Link Whisper | advanced | as post |
| — | Rank Math SEO | rank_math_metabox | Rank Math | sidebar | pt_page_add_meta_box on |
| — | Sidebar panels: Rank Math, Link Whisper target keyword, SCF block bindings | panel-rank-math, panel-link-whisper, panel-scf-bindings | JavaScript | block editor sidebar | as post |

No SCF group, no Related box. The admin account's saved order still lists a `kits_seo` normal box and a Rank Math content box (neither registered now).

### aw_news (News) — classic editor

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish | submitdiv | core | side/core | |
| 2 | Categories | categorydiv | core | side/core | |
| 3 | Tags | tagsdiv-post_tag | core | side/core | |
| 4 | Linked campaigns | tagsdiv-aw_campaign_link | core | side/core | |
| 5 | Post Attributes (template) | pageparentdiv | core | side/core | |
| 6 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core | |
| 7 | Content AI | rank_math_metabox_content_ai | Rank Math `modules/content-ai/class-admin.php:110` | side/high | classic editor only. Not captured (see above) |
| 8 | Link Suggestions | rank_math_metabox_link_suggestions | Rank Math `class-metabox.php:232` | side/default | classic editor only; `pt_aw_news_link_suggestions` = on; `link_builder` cap. Not captured (see above) |
| 9 | Featured image | postimagediv | core | side/low | |
| 10 | Rank Math SEO | rank_math_metabox | Rank Math | normal (Rank Math priority setting) | pt_aw_news_add_meta_box on; a real metabox here (classic) |
| 11 | Excerpt | postexcerpt | core | normal/core | |
| 12 | Custom Fields | postcustom | core | normal/core | supports custom-fields |
| 13 | Slug | slugdiv | core | normal/core | |
| 14 | Related | aw_related | ours `core/model.php:124` | normal/default | relations aw_rel_legal, aw_rel_org |
| 15 | Source | acf-group_aw_news | SCF `acf-json/group_aw_news.json` | normal | post_type == aw_news |
| 16 | FAQ | aw-faq | ours | normal/low | |

Places stored on News are kept and still read (`AW_PLACE_TYPES`, `core/places.php`); the Place fields are edited on Directory listings only.

### aw_legal (Library) — block editor

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish | submitdiv | core | side/core | panel |
| 2 | Categories / Tags / Linked campaigns | categorydiv, tagsdiv-post_tag, tagsdiv-aw_campaign_link | core | side/core | panels |
| 3 | Post Attributes | pageparentdiv | core | side/core | panel |
| 4 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core | |
| 5 | Revisions | revisionsdiv | core | normal/core | panel; 2+ revisions |
| 6 | Excerpt / Custom Fields / Slug | postexcerpt, postcustom, slugdiv | core | normal/core | panels / pref |
| 7 | Related | aw_related | ours | normal/default | relation aw_rel_org |
| 8 | Source | acf-group_aw_legal | SCF `acf-json/group_aw_legal.json` | normal | post_type == aw_legal |
| 9 | Document | acf-group_aw_legal_document | SCF `acf-json/group_aw_legal_document.json`; message fields drawn by `aw_legal_item_fields` (`core/faq-data.php:662`) and `aw_issuer_box` (`core/issuers.php:73`) | normal | post_type == aw_legal |
| 10 | Linked from | aw_linked_from | ours | normal/low | target of aw_rel_legal, aw_rel_resource |
| 11 | FAQ | aw-faq | ours | normal/low | |
| — | Rank Math SEO | rank_math_metabox | Rank Math | sidebar | |
| — | Sidebar panels: Rank Math, SCF block bindings | panel-rank-math, panel-scf-bindings | JavaScript | block editor sidebar | listed from the map |

Issued by is inside the Document box (Library items only); issuers stored on Resources are kept and still read. The admin account's saved order still names old box ids (`aw-faq-legal`, `aw-issuer`, `aw-place`); WordPress skips ids that are not registered.

### aw_campaign — block editor

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish / Categories / Tags / Linked campaigns / Post Attributes | core | core | side/core | panels |
| 2 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core | |
| 3 | Featured image | postimagediv | core | side/low | panel |
| 4 | Revisions / Excerpt / Custom Fields / Slug | core | core | normal/core | panels / pref |
| 5 | Related | aw_related | ours | normal/default | relations aw_rel_org, aw_organiser, aw_participant |
| 6 | Event | acf-group_aw_campaign | SCF `acf-json/group_aw_campaign.json` | normal | post_type == aw_campaign |
| 7 | Dates and state | acf-group_aw_campaign_dates | SCF; drawn by `aw_camp_dates_box` (`core/campaign-tools.php:628`) | normal | |
| 8 | Document | acf-group_aw_campaign_document | SCF; `aw_camp_document_box` (:647) | normal | |
| 9 | Organisations | acf-group_aw_campaign_orgs | SCF; `aw_camp_orgs_box` (:653) | normal | |
| 10 | Outcome | acf-group_aw_campaign_outcome | SCF; `aw_camp_outcome_box` (:661) + `aw_camp_record_box` (`core/campaign-record.php:215`) | normal | |
| 11 | Letters and share | acf-group_aw_campaign_share | SCF; `aw_camp_share_box` (:672) | normal | |
| 12 | FAQ | aw-faq | ours | normal/low | |
| — | Rank Math SEO | rank_math_metabox | Rank Math | sidebar | |

The five campaign groups (7–11) hold one message field each; the fields inside are drawn by `acf/render_field` and saved by our own `save_post_aw_campaign` (`core/campaign-tools.php:688`, `core/campaign-record.php:228`), which runs only when all five groups are active (`aw_camp_boxes_all_on()`). Not checked live yet.

### aw_opportunity — block editor

| # | Title | id | src | context / prio |
|---|---|---|---|---|
| 1 | Publish / Categories / Tags / Linked campaigns / Post Attributes | core | core | side/core (panels) |
| 2 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core |
| 3 | Excerpt / Custom Fields / Slug | core | core | normal/core |
| 4 | Related | aw_related | ours (relation aw_organisation, max 1) | normal/default |
| 5 | Opportunity details | acf-group_aw_opportunity | SCF `acf-json/group_aw_opportunity.json` | normal |
| 6 | FAQ | aw-faq | ours | normal/low |
| — | Rank Math SEO | rank_math_metabox | Rank Math | sidebar |
| — | Sidebar panels: Rank Math, SCF block bindings | panel-rank-math, panel-scf-bindings | JavaScript | block editor sidebar |

### aw_listing (Directory) — block editor (admin account: template-locked rendering mode)

| # | Title | id | src | context / prio | cond |
|---|---|---|---|---|---|
| 1 | Publish / Categories / Tags / Post Attributes | core | core | side/core | panels (no aw_campaign_link on listings) |
| 2 | LiteSpeed | litespeed_meta_boxes | LiteSpeed | side/core | |
| 3 | Featured image (= logo) | postimagediv | core | side/low | panel |
| 4 | Excerpt (= short bio) / Custom Fields / Slug | core | core | normal/core | |
| 5 | Contact | acf-group_aw_listing | SCF `acf-json/group_aw_listing.json` | normal | post_type == aw_listing |
| 6 | Address and place | acf-group_aw_listing_address | SCF `acf-json/group_aw_listing_address.json`; Place fields drawn by `aw_place_box` (`core/places-admin.php:20`) | normal | |
| 7 | Opening hours | acf-group_aw_listing_hours | SCF | normal | |
| 8 | Registrations | acf-group_aw_listing_registrations | SCF | normal | |
| 9 | About and services | acf-group_aw_listing_about | SCF | normal | |
| 10 | Online | acf-group_aw_listing_online | SCF | normal | |
| 11 | Photos and support | acf-group_aw_listing_photos | SCF | normal | |
| 12 | Claimed and sources | acf-group_aw_listing_claimed | SCF; ends with "Where these details come from", drawn by `aw_enrich_prov_box` (`src/enrichment-admin.php:138`) | normal | |
| 13 | Linked from | aw_linked_from | ours | normal/low | target of aw_rel_org, aw_organiser, aw_participant, aw_organisation |
| 14 | FAQ | aw-faq | ours | normal/low | |
| 15 | Volunteers | aw_org_people | ours `core/org-people.php:109` (cb :119) | normal/low | `current_user_can( 'edit_aw_volunteers' )` |
| — | Rank Math SEO | rank_math_metabox | Rank Math | sidebar | |
| — | Sidebar panels: Rank Math, SCF block bindings | panel-rank-math, panel-scf-bindings | JavaScript | block editor sidebar | listed from the map |
| — | Organisation details (fallback) | aw-listing-details | ours `core/listing-profile.php:68` | normal/high | **not shown**: only when SCF is off |

No Related box on listings (no relation starts at aw_listing).

### aw_press (Media & PR) — classic, not public

| # | Title | id | src | context / prio |
|---|---|---|---|---|
| 1 | Publish | submitdiv | core | side/core |
| 2 | Tags / Linked campaigns | tagsdiv-post_tag, tagsdiv-aw_campaign_link | core | side/core |
| 3 | Featured image (= thumbnail) | postimagediv | core | side/low |
| 4 | Coverage | aw_press_box | ours `core/press.php:135` (closure :138) | normal/high |
| 5 | Slug | slugdiv | core | normal/core (hidden by the admin account) |
| 6 | Related | aw_related | ours (relation aw_rel_org) | normal/default |

No FAQ, Rank Math, LiteSpeed (type not public). Supports `aw-section` (no box).

### aw_claim (Listing claims) — classic, not public, no posts on live

Boxes for an unsaved post: Publish (side/core), **Review** `aw_claim_review` ours `core/claims.php:118` (cb :127) normal/high, Slug (normal/core). Capability type aw_volunteer; `create_posts` = do_not_allow.

### aw_report (Reports) — classic, not public, no posts on live

Same check: Publish, **Report** `aw-report-sent` ours `core/reports.php:165` (closure :168) normal/high (read-only), Slug.

### aw_message (mailweb inbox) — classic, not public

Publish, Slug only (plus the editor), both in the General row. No plugin box.

### attachment (media edit screen)

Save `submitdiv`, Media groups `tagsdiv-aw_media_group` (our taxonomy), Attachment Attributes `pageparentdiv`, LiteSpeed, Slug, Author (hidden by the admin account). Field via `attachment_fields_to_edit` (not a box): **Image context** `kits_image_context`, `kits/image-context.php:13–30`.

### aw_volunteer

`show_ui` false: no edit screen.

---

## 2. Box contents: field → storage

Columns: **stored as**, type, sanitising on save, REST (`show_in_rest` via `register_post_meta`).
`aw_meta_schema()` = `core/meta.php:17–117`, registered by `aw_register_meta()` `core/meta.php:168–189` (single, `show_in_rest` true, `auth_callback` edit_posts, strings cleaned by `aw_sanitize_meta()` `core/meta.php:127–163`). REST yes = listed in that schema or another `register_post_meta`.

### 2.1 Our boxes

**Issued by** (inside the SCF "Document" box on aw_legal, message field `field_aw_legal_msg_issued_by`; `aw_issuer_box` `core/issuers.php:73`, save :84)

| field | stored as | type | sanitising | REST |
|---|---|---|---|---|
| Issuer select | post meta `aw_issuer` (slug) | string | `sanitize_title`, must exist in `aw_issuers()` (`aw_set_issuer` :59); empty → delete | no |
| "Not in the list? Add it" | option `aw_issuers_added` (slug ⇒ name) + `aw_issuer` | — | `sanitize_text_field`; live value: `ccsea` only | — |

Nonce `aw_issuer_save`; save_post prio 10. Issuers stored on other types are kept and still read.

**Place** (inside the SCF "Address and place" box on aw_listing, message field `field_aw_listing_msg_place`; `aw_place_box` `core/places-admin.php:20`, save :84; storage `aw_set_item_places()` `core/places.php`). Places stored on the other `AW_PLACE_TYPES` are kept and still read; auto-detect continues.

| field | stored as | type | sanitising | REST |
|---|---|---|---|---|
| PIN code | `aw_pincode` | 6 digits | `\D` stripped; must be 6 digits else nothing saved | no |
| State | `aw_state_code` (**multiple rows**, one per state) | int LGD code | `absint`; must be in LGD file | no |
| District | `aw_district_code` (multiple rows) | int LGD code | `absint`; district inside its state | no |
| "Also" ticks | more `aw_state_code`/`aw_district_code` rows | | | |
| Town or area | `aw_place_city` | text | `sanitize_text_field` | no |
| (on a real change) | `aw_place_source` = `editor`, deletes `aw_place_auto` | | | no |

Invalid input → transient `aw_place_error_{user}` notice, place kept. PIN lookup AJAX `wp_ajax_aw_pin_lookup`.

**Related** (`core/model.php:144–220`; relations `aw_relations()` `core/model.php:31–41`)

| row | stored as (multiple rows, one listing/post ID each) | on types | max |
|---|---|---|---|
| Legal Watch items in this story | `aw_rel_legal` → aw_legal | aw_news | ∞ |
| Organisations featured | `aw_rel_org` → aw_listing | aw_news, aw_legal, post, aw_press, aw_campaign | ∞ |
| Organiser | `aw_organiser` → aw_listing | aw_campaign | 1 |
| Participating organisations | `aw_participant` → aw_listing | aw_campaign | ∞ |
| Organisation | `aw_organisation` → aw_listing | aw_opportunity | 1 |
| Library items | `aw_rel_resource` → aw_legal | post | ∞ |
| Guides on this | `aw_rel_guide` → post | post | ∞ |
| Found in the text (ticks) | `aw_rel_org_auto` (found on save, `core/featured.php:253–300`); unticked → `aw_rel_org_not` (`core/featured.php:353–354`) | AW_FEATURED_TYPES (`core/featured.php:23`): aw_news, aw_legal, post, aw_press, aw_campaign | |
| Listing types (post only) | `aw_rel_listing_type` (post_tag term IDs, multiple rows) | post | |

Sanitising: `absint`, target post type checked, not self, cut to max. Search via REST `wp.apiFetch` (`core/model.php:224–245`). None REST-registered.

**Linked from** (`core/model.php:173–188`): read-only list of items whose `aw_rel_*` points here (`aw_rel_back`, 100 max). Stores nothing.

**FAQ** (`seo/faq.php:793–830`; editor `aw_faq_editor` :644; save `aw_faq_editor_save` → `aw_faq_items_save` :309–318)

| field | stored as | type | sanitising | REST |
|---|---|---|---|---|
| rows: q, a, sources, keyword, status, reviewed, auto id, order, removed | post meta `aw_faq` (JSON list) | JSON string | `aw_faq_clean_row()`; nonce `aw_faq_items_nonce`; empty → delete; clears transient `aw_faq_index` | no |

Automatic rows are generated from each type's own fields (`core/faq-data.php`), only edited/own rows are stored. Undo copy `_aw_faq_rm_block` on posts (Rank Math FAQ block conversion, `seo/faq.php:870ff`).

**Library item fields** (inside the SCF "Document" box on aw_legal, message field `field_aw_legal_msg_item`; `aw_legal_item_fields` `core/faq-data.php:662`, save on `save_post_aw_legal` :689)

| field | stored as | type | sanitising | REST |
|---|---|---|---|---|
| Short name | `aw_short_name` | string | `sanitize_text_field` | yes |
| Issued under (Act) | `aw_legal_act` | string | `sanitize_text_field` | yes |
| Key sections | `aw_key_sections` | multi-line "no \| summary \| link" | `sanitize_textarea_field` | yes |

Empty → delete. Not in any box but registered on aw_legal: `aw_file_hi_url` ("no longer edited", 15 rows), `aw_legal_dated` (`core/item-head.php:134`, 1 row) — editable only through Custom Fields / REST.

**Campaign fields** (inside the five SCF campaign groups, one message field each; `core/campaign-tools.php:590–686`, save on `save_post_aw_campaign` :688, only when all five groups are active)

| box | field | stored as | type | sanitising | REST |
|---|---|---|---|---|---|
| Dates and state | Now (state) | — read-only (`aw_camp_state`, `aw_camp_window`) | | | |
| Dates and state | Opens on | `aw_campaign_open` | Y-m-d | regex, else '' | yes |
| Dates and state | Closes on | `aw_campaign_close` | Y-m-d | regex, else '' | yes |
| Dates and state | Override the state | `aw_campaign_state_override` | pre/active/post | kept only with a reason | yes |
| Dates and state | Reason | `aw_campaign_override_note` | text | `sanitize_text_field` | yes |
| Document | Campaign document | `aw_campaign_doc` | Library post ID | `absint` | yes |
| Organisations | Partners | `aw_campaign_partners` | CSV listing IDs | `absint` list | yes |
| Organisations | Supporters | `aw_campaign_supporters` | CSV listing IDs | `absint` list | yes |
| Outcome | Outcome | `aw_campaign_outcome` | pending/achieved/partial/not/inconclusive | whitelist, default pending | yes |
| Outcome | As of | `aw_campaign_outcome_asof` | Y-m-d | regex | yes |
| Outcome | Real-world impact | `aw_campaign_impact_state` | verified/no-change/pending/inconclusive/na | whitelist `AW_CAMP_IMPACT` (`core/campaign-record.php:215`, save :228) | yes |
| Outcome | Impact evidence (item ID) | `aw_campaign_impact_evidence` | integer | `absint` | yes |
| Letters and share | Show the count from | `aw_campaign_metric_min` | int (default 100) | `absint` | yes |
| Letters and share | "As of" date after N days | `aw_campaign_metric_stale` | int ≥1 | `max(1, absint)` | yes |
| Letters and share | Share line | `aw_campaign_share_text` | text | `sanitize_text_field` | yes |
| Letters and share | Story image ID / Post image ID | `aw_campaign_share_story`, `aw_campaign_share_post` | attachment ID | `absint` | yes |
| Letters and share | Letters sent | — read-only `aw_campaign_sent` (written by the "Yes, I sent it" endpoint, with `aw_campaign_sent_at`) | int | | no |

Every field is written on every save (empty strings are stored, not deleted). `aw_campaign_metrics` (JSON metric list) has **no box**: Custom Fields / REST only.

**Where these details come from** (end of the SCF "Claimed and sources" box on aw_listing, message field `field_aw_listing_msg_provenance`; `aw_enrich_prov_box` `src/enrichment-admin.php:138`, save on `save_post_aw_listing` :160)

| field | stored as | type | sanitising | REST |
|---|---|---|---|---|
| Provenance list | read-only: enrichment table `aw_enrich_table()` rows (auto-applied/approved) | | | |
| Flags | read-only `aw_enrich_flags` | | | |
| Last checked | read-only `aw_enrich_checked` | | | |
| Identity unresolved | `aw_enrich_flags['identity']` = date ticked (`aw_enrich_flag`) | | nonce `aw_enrich_identity` | no |

Also prints a "Source: …" line under each matching SCF field (`acf/render_field`, `src/enrichment-admin.php`).

**Volunteers** (`core/org-people.php:119–136`): read-only list of FluentCRM contacts tagged org-approved / org-pending for this listing. Stores nothing.

**Coverage** (aw_press, `core/press.php:117–196`)

| field | stored as | sanitising |
|---|---|---|
| Link to the coverage | `aw_press_url` | `esc_url_raw` |
| Publisher | `aw_press_publisher` | `sanitize_text_field` |
| Publisher logo (media ID) | `aw_press_logo` | `absint`, 0 → delete |
| Date | `aw_press_date` | Y-m-d regex |
| Format | `aw_press_format` (select `AW_PRESS_FORMATS`) | text |
| Platform | `aw_press_platform` (select `AW_PRESS_PLATFORMS`, default web) | text |
| Account handle | `aw_press_account` | text |
| Thumbnail source URL | `aw_press_thumb_src` | `esc_url_raw` |
| Video embed URL | `aw_press_video` | `esc_url_raw` |
| Notes (not shown) | `aw_press_notes` | `sanitize_textarea_field` |
| Refresh preview (tick) | not stored; runs `aw_press_fetch()` which writes empty fields + `aw_press_fetch` status | |

Empty → delete. None REST-registered (type has no REST). `aw_press_group` (64 rows) and `aw_press_query` (46) are written by code, not the box.

**Review** (aw_claim, `core/claims.php:127–165`): read-only `aw_c_listing`, `aw_c_status`, `aw_c_proposed` (array), `aw_c_logo`, `aw_c_name`, `aw_c_role`, `aw_c_email`, `aw_c_phone`, `aw_c_feature`, `aw_c_notes`; Approve/Reject links → `admin_post_aw_claim_decide` (:183ff) write the listing (`aw_verified`, `aw_verified_on`, `aw_needs_reverify`, proposed fields).

**Report** (aw_report, `core/reports.php:168–186`): read-only `aw_r_type`, `aw_r_page`, `aw_r_reason`, `aw_r_details`, `aw_r_email`, `aw_r_status`, `aw_r_ref`. Status changes by list-row links (`aw_r_status`, :150–158).

### 2.2 SCF field groups (Local JSON in `acf-json/`, 19 groups, all active, position normal, style default, `show_in_rest` 0, `hide_on_screen` empty)

Saving goes through `aw_value_guard()` (`core/scf.php:62–113`, filter `acf/pre_update_value` prio 5): top-level fields write the existing key in its own format (true_false → '1' or delete; date_picker Ymd → Y-m-d; gallery → "1,2,3" **max 4**; file/post_object → ID), empty → delete, registered keys pass `aw_sanitize_meta`. No `_field` reference rows for top-level fields; repeaters are saved by SCF itself (rows `{key}_{n}_{sub}` plus `_`-prefixed reference rows). Validation messages `acf/validate_value` (`core/scf.php:139–155`). Read-only fields are shown and never saved.

**group_aw_resource "Resource details"** (post)

| tab | field (label) | key | type | REST |
|---|---|---|---|---|
| Review | Last reviewed | `kits_last_reviewed` | date_picker | yes |
| Source | Source URL / Source name / Source date | `aw_source_url`, `aw_source_name`, `aw_source_date` | url / text / date | **no for post** (schema `$cards` excludes post) |
| Sponsored | Sponsored | `aw_sponsored` | true_false | yes |
| Sponsored | Sponsor name (if Sponsored) | `aw_sponsor_name` | text, conditional | yes |

**group_aw_news "Source"** (aw_news): `aw_source_url` url, `aw_source_name` text (label "Publisher name"), `aw_source_date` date, `aw_publisher` text **read-only** ("Worked out from the source URL"; written by `aw_publisher_sync`, `core/systems.php:410–417`). REST: source keys yes, `aw_publisher` no.

**group_aw_legal "Source"** (aw_legal): `aw_source_url` url, `aw_source_name` text, `aw_source_date` date. REST yes.

**group_aw_legal_document "Document"** (aw_legal)

| field | key | type | REST |
|---|---|---|---|
| Document file | `aw_file_id` | file (ID) | yes |
| Document link | `aw_file_url` | url | yes |
| Official number | `aw_legal_number` | text | yes |
| In force | `aw_legal_force` | select InForce/PartiallyInForce/NotInForce | yes |
| (message) Short name, Issued under, Key sections | `aw_short_name`, `aw_legal_act`, `aw_key_sections` (§2.1) | drawn by `aw_legal_item_fields` | yes |
| (message) Issued by | `aw_issuer`, option `aw_issuers_added` (§2.1) | drawn by `aw_issuer_box` | no |
| Not used on the site: Ask for an email before download | `aw_requires_email` | true_false | yes |

**group_aw_opportunity "Opportunity details"** (aw_opportunity)

| tab | field | key | type | cond | REST |
|---|---|---|---|---|---|
| Organisation | Organisation | `aw_org_name` | text | | yes |
| Dates | Deadline | `aw_deadline` | date | | yes |
| Dates | Review date | `aw_review_until` | date | only when Deadline empty | yes |
| Dates | Event date | `aw_event_date` | date | | yes |
| Dates | Repeats | `aw_event_schedule` | text | | yes |
| Apply | Apply URL / Website | `aw_apply_url`, `aw_website` | url | | yes |
| Apply | Employment type | `aw_employment_type` | select (8 Google values) | | yes |
| Apply | Paid listing | `aw_paid_listing` | true_false | | yes |
| Source | Source URL / name / date | `aw_source_*` | | | yes |

**group_aw_campaign "Event"** (aw_campaign): `aw_event_date` date, `aw_event_place` text. REST yes.

**group_aw_campaign_dates "Dates and state", group_aw_campaign_document "Document", group_aw_campaign_orgs "Organisations", group_aw_campaign_outcome "Outcome", group_aw_campaign_share "Letters and share"** (aw_campaign): one message field each; the fields inside are ours (§2.1 Campaign fields), not SCF fields.

**The listing groups** (aw_listing, eight groups). REST: yes for every key in `aw_meta_schema` or the aw-sourcing list (`src/enrichment.php`, sanitiser `aw_enrich_sanitize`); **no** for `aw_phones`, `aw_opening_hours`, `aw_detail_sources`, `aw_aliases`.

| group (box) | fields (key, type) |
|---|---|
| group_aw_listing "Contact" | `aw_email` email; `aw_phones` repeater (number, label, whatsapp) |
| group_aw_listing_address "Address and place" | message: the Place fields (§2.1); `aw_area`, `aw_address`, `aw_maps_url` url, `aw_lat`, `aw_lng` (numeric check), `aw_place_id` |
| group_aw_listing_hours "Opening hours" | `aw_opening_hours` repeater (day select, open/close time H:i shown unless closed, closed) |
| group_aw_listing_registrations "Registrations" | `aw_registration`, `aw_reg_12a`, `aw_reg_80g`, `aw_darpan_id`, `aw_reg_type`, `aw_fcra`, `aw_awbi_code` **read-only**, `aw_awbi_valid` date, `aw_reg_date` date; under "Not used on the site": `aw_reg_authority`, `aw_reg_act`, `aw_sectors`, `aw_awbi_recognised` / `aw_awbi_renewed` dates |
| group_aw_listing_about "About and services" | `aw_aliases`, `aw_founded` (4-digit year), `aw_head`, `aw_animals`, `aw_areas_served`, `aw_services`, `aw_services_source` + `aw_services_on` (only when Services filled), `aw_languages` |
| group_aw_listing_online "Online" | `aw_website`, `aw_instagram`, `aw_facebook`, `aw_x`, `aw_youtube`, `aw_linkedin` (urls), `aw_wikidata`, `aw_ig_followers`, `aw_ig_posts`, `aw_last_post`, `aw_ngobase_url`, `aw_g_profile`, `aw_g_category`, `aw_g_rating` (numeric), `aw_g_reviews` (digits) |
| group_aw_listing_photos "Photos and support" | message "logo is the Featured image"; `aw_gallery` gallery (IDs, stored CSV); `aw_volunteer_url`, `aw_adopt_url` urls; `aw_rel_nofollow` true_false; under "Not used on the site": `aw_donate_url` url, `aw_upi` |
| group_aw_listing_claimed "Claimed and sources" | `aw_verified` true_false (label "Claimed"; unticked stores '' not delete); `aw_verified_on` date (label "Claimed on", only when ticked); `aw_needs_reverify` true_false; `aw_source_url`, `aw_source_name`, `aw_source_date`; `aw_detail_sources` repeater (detail select of 13 values, url, checked date); message: "Where these details come from" (§2.1) |

Registered on aw_listing but in **no** box/field: `aw_awbi_dropped`, `aw_awbi_list_on`, `aw_gov_verified_on`, `aw_gov_verified_by`, `aw_darpan_blacklisted_by`, `aw_darpan_blacklisted_on`, `aw_sponsored`, `aw_sponsor_name` (all set by code/imports).

### 2.3 Third-party boxes

- **Rank Math SEO** (sidebar in block editor, metabox on aw_news). Keys used on the site (live row counts): `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, `rank_math_seo_score`, `rank_math_robots`, `rank_math_primary_category`, `rank_math_facebook_image`/`_id`/`_title`/`_description`, `rank_math_twitter_use_facebook`, `rank_math_rich_snippet` (6 opportunities), `rank_math_schema_{Type}` (Article 205 post + 79 legal, NewsArticle 1024, NGO 5241, VeterinaryCare 958, GovernmentOffice 605, AnimalShelter 1, Legislation 14, DigitalDocument 6, JobPosting 13, Event 2+1, CreativeWork 1, page types), `rank_math_shortcode_schema_s-*`, `rank_math_analytic_object_id`, `rank_math_internal_links_processed`. Save: `RankMath\Admin\Metabox\Metabox::save_meta` on save_post; terms `save_term_meta` on edit_term. Our code writes the title/description/focus keyword when not typed (`_aw_seo_auto` bookkeeping, `seo/titles.php:141–166`, `seo/programmatic.php:661–684, 933–952`) and the schema rows (`seo/rank-math-schema.php`, `_aw_rm_schema_mid`). Rank Math's ACF module is on; `seo/analysis.php:254–272` removes codes/coordinates/counts from its content analysis. Rank Math settings: metabox on for post, page, aw_news, aw_legal, aw_campaign, aw_opportunity, aw_listing; off for attachment.
- **LiteSpeed** (`litespeed-cache/src/metabox.cls.php`): Disable Cache `litespeed_no_cache`, Disable Image Lazyload `litespeed_no_image_lazy`, Disable VPI `litespeed_no_vpi`, Viewport Images `litespeed_vpi_list`, `litespeed_vpi_list_mobile`. **0 rows** of any `litespeed_*` key on live.
- **Link Whisper Suggested Links** (post, page): suggestion UI; stores `wpil_sync_report3` (65 post, 20 page rows) and other `wpil_*` on processing. Rendering the box deletes `wpil_links` on that post (`Base.php:776`).
- **SCF**: the 19 groups above, and a block bindings sidebar panel in the block editor (bindings are saved in the block, not as meta).
- **JavaScript sidebar panels** (block editor): Rank Math (SEO, Schema, Content AI), Link Whisper target keyword (post, page), SCF block bindings. They are not boxes, so the live box read cannot see them; the map lists them (`panel-*`, runtime `edit-screen-only`).
- **Rank Math on terms**: the SEO box below the table (`{taxonomy}_edit_form` prio 11) and, on the edit form, a rich editor that replaces the core Description field (`category_description_editor`, `{taxonomy}_edit_form_fields` prio 1; it saves the core term description).

---

## 3. Where each stored key is used

R = reads, W = writes. "Display" = front-end block/output. Only box-edited (or box-shown) keys; for widely read keys the main places are given, not every line.

### 3.1 Shared item keys

| key | display | JSON-LD | FAQ | lists / filters / place pages | writers besides the box | analytics / admin |
|---|---|---|---|---|---|---|
| `aw_source_url` | "Read at {source}" binding `core/bindings.php:81–85`; item sources `core/item-head.php:318`; source attribution `kits/attribution.php:25,60` | Article `isBasedOn`/citation `seo/schema.php:426`; PDF `aw_schema_pdf` :366; court doc url :564 | `core/faq-data.php:333–443, 554` (download/read/study answers) | dedupe `src/same-story.php:42`; `core/cc0-images.php` image source; `core/media.php:277,303,463` source image | ingest `src/ingest.php:215` | review table `src/class-aw-sourcing-review-table.php:126`, source sites, keyword stats |
| `aw_source_name` | binding `source_meta`/`source_link_label` `core/bindings.php:83,87`; item head by-line `core/item-head.php:341,359`; card source `core/hub-blocks.php:603` | Article publisher name `seo/schema.php:427`, Periodical name :546 | study journal `core/faq-data.php:574, 758` | title cleanup `seo/titles.php:80` | ingest `src/ingest.php:241` | relevance, summary rewrite |
| `aw_source_date` | shown date: Post Date block filter `core/display.php:38–58`, meta row `core/item-head.php:124`; card ts `core/hub-blocks.php:253` | `datePublished` of the original `seo/schema.php:428`; Legislation `legislationDate` :481 | `core/faq-data.php:370,575` | News sort key `core/display.php:60–219` (filled from post date when missing, :155–158) | ingest `src/ingest.php:247` | review queue, gov-verified watch list `core/gov-verified.php:48,72` |
| `aw_issuer` | Library meta/issuer line `core/item-head.php:329`; card source `core/hub-blocks.php:604`; placard `core/media.php:308` | `GovernmentOrganization` (`aw_schema_issuer` `seo/schema.php:373–376`; court types :502) | issuer/read questions `core/faq-data.php:43–44, 342–411, 768, 1031` | — | `aw_set_issuer()` (imports) | dataLayer `issuer` `core/kits-config.php:67–68`; relevance/auto-review |
| `aw_state_code`, `aw_district_code` | place chips `core/item-head.php` (via `aw_item_places`), related-by-place `core/related.php:93,171` | job/opportunity `jobLocation`/`Place` (`aw_schema_place` `seo/schema.php:344`, :820–835) | listing location answers via `aw_item_place_names` | place pages & counts `core/places.php:296–353`, `seo/programmatic.php:48`, near opportunities `core/near-opps.php:27`, district helplines `core/district-helplines.php:50`, filters `core/systems.php:160,306`, filtered views `seo/filtered-views.php:347` | auto-detect `core/find-help.php:257, 444–447`; merges `src/enrichment-cleanup.php:72–78` | dataLayer `state`/`district` `core/kits-config.php:62–63`; enrichment admin filter |
| `aw_pincode` | listing location text `core/listing-template.php:217` | `PostalAddress.postalCode` listing `seo/schema.php:87`, jobs :820–824, :910–913 | location answer `core/faq-data.php:235` | — | geocoder input `core/geocode.php:79,91` | enrichment cleanup |
| `aw_place_city` | place text binding `core/bindings.php:111` | job `addressLocality` `seo/schema.php:768, 835` | — | featured matching `core/featured.php:92` | — | — |
| `aw_place_source`, `aw_place_auto` | — | — | — | — | detector `core/find-help.php:257–258, 422–495` | admin/CLI only (who set the place) |
| `aw_faq` | FAQ block (`seo/faq.php` rows → `animal-writes/item-faq`) | `FAQPage` (`seo/schema.php:1344, 1424`) | is the FAQ | FAQ overview counts `seo/faq-overview.php:60,76` | conversion undo `seo/faq.php:916–993` | — |
| `aw_rel_org` | "Featured in" on the listing `core/featured.php:372–395`; "News from" `core/related.php:264` | listing `subjectOf` (`aw_featured_subject_schema` `core/featured.php:461`) | — | — | merges `src/enrichment-cleanup.php:195` | 0 rows on live |
| `aw_rel_org_auto` / `aw_rel_org_not` | same as aw_rel_org (`core/featured.php:270–395`) | same | — | — | `aw_featured_scan` on save | 252 news, 8 campaign rows |
| `aw_rel_legal` | "From the Library" / "News about this" `core/related.php:240,244`; `core/item-head.php:431,435` | — | — | — | — | 2 rows |
| `aw_rel_resource` | "Guides on this" / "From the Library" `core/related.php:245,249`; `core/item-head.php:436,442` | — | — | — | — | 1 row |
| `aw_rel_guide` | "Guides on this" at the end of a research post `core/item-head.php:443` | — | — | — | — | 9 rows |
| `aw_rel_listing_type` | **none** | — | — | — | — | admin only: post_tag screen "Guides linking here" `core/model.php:291–303`; 0 rows |

### 3.2 aw_legal

| key | display | JSON-LD | FAQ | other |
|---|---|---|---|---|
| `aw_short_name` | — | — | the name questions use (`core/faq-data.php:154`) | — |
| `aw_legal_act` | — | — | "Issued under" answers `core/faq-data.php:396` | — |
| `aw_key_sections` | — | — | "What does section N say?" `core/faq-data.php:45, 419–450` | — |
| `aw_legal_dated` | "Dated {date}" in the meta row `core/item-head.php:152–156` | — | — | no box |
| `aw_file_id` | featured image / PDF cover (`core/media.php:24–173`, `core/hub-blocks.php:207,719`, `core/sections.php:30,104`) | `aw_schema_pdf` `seo/schema.php:363` (DigitalDocument/encoding) | PDF question `core/faq-data.php:40, 331` | written by `aw_mirror_gov_pdf` `core/media.php:418` |
| `aw_file_url` | binding `file_url` `core/bindings.php:158` | court document url `seo/schema.php:564` | — | 0 rows on live |
| `aw_legal_number` | — | `legislationIdentifier` `seo/schema.php:483` | date question `core/faq-data.php:42, 371` | ingest match `src/ingest.php:97, 382` |
| `aw_legal_force` | — | `legislationLegalForce` `seo/schema.php:484` | in-force questions `core/faq-data.php:41, 68, 348, 973` | ingest `src/ingest.php:390` |
| `aw_requires_email` | **none** | — | — | **never read**; 0 rows |
| `aw_file_hi_url` | **none** | — | — | **never read** (registered only, `core/faq-data.php:460`); 15 rows |

### 3.3 aw_campaign

| key | display / behaviour | JSON-LD | other |
|---|---|---|---|
| `aw_campaign_open` | Pre/Upcoming window `core/campaign-tools.php:54` | — | mail strip window `core/mail-campaign-strip.php:36` |
| `aw_campaign_close` | open/closed state, days left, sidebar card, share data `core/campaign-tools.php:48, 91, 488, 548, 579`; list column :667 | (via state) | schedules the close cron on change :144; mail strip :32–46; dataLayer `campaign_status` `core/kits-config.php:103–106` |
| `aw_campaign_doc` | rule chips quote this Library item `core/campaign-tools.php:247–256` | campaign `about` the Legislation node `seo/schema.php:595–596` | — |
| `aw_campaign_partners` | `[aw_campaign_partners]` cards `core/campaign-tools.php:418–427`; listing "Campaigns" (Partner) `core/listing-template.php:522–524` | — | — |
| `aw_campaign_supporters` | "Supported by"; listing "Campaigns" (Supporter) `core/listing-template.php:529–531` | — | — |
| `aw_campaign_outcome`, `_asof` | `[aw_campaign_outcome]` etc. `core/campaign-tools.php:98–100`, `core/campaign-record.php:127` | — | asof auto-set at close :148–149 |
| `aw_campaign_state_override`, `_override_note` | state override `core/campaign-tools.php:60–61` | — | — |
| `aw_campaign_metric_min`, `_metric_stale` | when the letter count shows / gets an as-of date `core/campaign-tools.php:170, 403, 410` | — | — |
| `aw_campaign_share_text`, `_share_story`, `_share_post` | Share button data `window.awCampShare` `core/campaign-tools.php:565, 579` | — | media merge keeps the IDs `core/media-merge.php:27` |
| `aw_campaign_sent` (+`_sent_at`) | count `[aw_campaign_count]` `core/campaign-tools.php:171, 386–410` | — | written by the "sent" endpoint |
| `aw_campaign_impact_state`, `_evidence` | Real-world impact module `core/campaign-record.php:116–120` | `aw_campaign_record_schema` `seo/schema.php:671ff` | — |
| `aw_campaign_metrics` | metric records `core/campaign-record.php:73` | record schema | no box |
| `aw_event_date` | — (no front-end display found) | Event `startDate` `seo/schema.php:585` | — |
| `aw_event_place` | — | Event `location` `seo/schema.php:586` | — |
| `aw_organiser` | listing "Campaigns" (Organiser) `core/listing-template.php:519` | Event `organizer` `seo/schema.php:582` | 0 rows |
| `aw_participant` | listing "Campaigns" (Partner) `core/listing-template.php:522–525` | — | 0 rows |

### 3.4 aw_opportunity

| key | display | JSON-LD | other |
|---|---|---|---|
| `aw_org_name` | binding `org_name` `core/bindings.php:141`; posted-by `core/hub-blocks.php:306`; card source :598; placard `core/media.php:125,147` | JobPosting `hiringOrganization` `seo/schema.php:773`; opportunity org :943 | ingest `src/ingest.php:298`, auto-link `src/ingest.php:559–565` |
| `aw_organisation` | posted-by link `core/hub-blocks.php:307`; listing "Opportunities" `core/listing-template.php:500` | org url / address `seo/schema.php:770, 941`, `aw_opportunity_org_address` :905 | ingest sets it `src/ingest.php:565` |
| `aw_deadline` | "Apply by" / Closed bindings `core/bindings.php:145–151` | `validThrough` `seo/schema.php:779, 957` | open/closed `core/retention.php:41–95`; ingest `src/ingest.php:303` |
| `aw_review_until` | (closes the listing) | — | `core/retention.php:42–100`, auto-set :50 |
| `aw_event_date`, `aw_event_schedule` | — | Event `startDate`, repeat `aw_opportunity_repeat` `seo/schema.php:884, 954` | — |
| `aw_apply_url` | Apply button binding `core/bindings.php:143` (empty once closed); actions `core/hub-blocks.php:300`; item sources `core/item-head.php:372` | — | ingest `src/ingest.php:300` |
| `aw_website` | — on opportunities | org `url` fallback `seo/schema.php:775–777, 945` | — |
| `aw_employment_type` | — | `employmentType` `seo/schema.php:795` | ingest `src/ingest.php:308` |
| `aw_paid_listing` | **none** | — | **never read**; 0 rows |

### 3.5 post (Resources)

| key | display | JSON-LD | other |
|---|---|---|---|
| `kits_last_reviewed` | — | Article `lastReviewed` `seo/schema.php:429` | 7 rows |
| `aw_sponsored` | "Sponsored" tag in the meta row `core/item-head.php:159`; binding `sponsored_label` `core/bindings.php:89`; nofollow on listing links `core/listing-template.php:34` | — | sponsored filter `core/systems.php:169, 234`; editor list `core/sponsored-blocks.php:94`; dataLayer `sponsor` `core/kits-config.php:109–110` |
| `aw_sponsor_name` | "Sponsored by X" `core/bindings.php:92`; sponsored item block `core/sponsored-blocks.php:30` | — | dataLayer `sponsor` |
| `aw_source_*` | research/source attribution (see 3.1) | — | — |

### 3.6 aw_listing (the eight SCF listing groups, with Place and provenance inside)

Live listing page = theme template `single-aw_listing.html` (blocks listing-about, -registrations, -contact, -location, -services, -organisation, -profiles, -support, -photos, -activity, -press, -opportunities, -campaigns, item-faq, item-sources). The older `animal-writes/org-details` block (`core/listing-profile.php:140ff`) is registered but in no template or published post (0 posts).

| key | display (`core/listing-template.php` unless noted) | JSON-LD (`aw_listing_schema`, `seo/schema.php:37–208`) | FAQ (`core/faq-data.php`) | other readers |
|---|---|---|---|---|
| `aw_email` | Contact :176–186 | `email` :102–103 | — | contact score `core/directory.php:482` |
| `aw_phones` (repeater) | Contact via `aw_listing_phones` `core/meta.php:237` | `telephone`, WhatsApp `contactPoint` :96–112 | phone answers :29 | district helplines, programmatic counts; claims/enrichment write via `aw_set_listing_list` `core/meta.php:289–312` |
| `aw_area` | Location :217 | `streetAddress` fallback :82 | :234–235 | jobs address `seo/schema.php:823, 912` |
| `aw_address` | Location :216 | `streetAddress` :82 | :30, 229 | geocoder `core/geocode.php:76` |
| `aw_maps_url` | Contact (directions) :182–183 | `hasMap` :118 | :242 | sources `core/data-sources.php:102` |
| `aw_lat`, `aw_lng` | map points (`core/geocode.php:126–147`) | `geo` :121–122 | — | geocoder |
| `aw_place_id` | Google block `src/enrichment-front.php:65,132` | — | — | duplicates check `src/enrichment.php:1351` |
| `aw_opening_hours` (repeater) | Contact hours via `aw_listing_hours` `core/meta.php:260` | `openingHoursSpecification` :137–140 | :34 | — |
| `aw_registration`, `aw_reg_12a`, `aw_reg_80g`, `aw_fcra`, `aw_darpan_id` | Registrations :145–153 | `identifier` PropertyValue :186–192 | registration answers :36, 305–306 | gov-verified official values `core/gov-verified.php:28,48` |
| `aw_reg_type` | Registrations label :145 | — | :310 | — |
| `aw_reg_date` | Organisation block :303 | — | — | — |
| `aw_reg_authority`, `aw_reg_act`, `aw_sectors` | **none** | — | — | admin only (descriptions say "admin only", `core/meta.php:71–74`); enrichment/claim field lists |
| `aw_awbi_code` | Registrations :143 | `identifier` "AWBI recognition code" :180–185 | AWBI answers :32, 268 | sources `core/data-sources.php:62–67`; meta description `seo/helpers.php:465`; read-only in SCF |
| `aw_awbi_valid` | "valid until / Expired" :147 | — | :272 | re-verify cron `core/cron.php:76` |
| `aw_awbi_recognised`, `aw_awbi_renewed` | **none** | — | — | edited in SCF, used only by claim/enrichment field lists (`core/claims-review.php:57–58, 456–457`, `src/enrichment.php:274–275`); 2732 / 124 rows |
| `aw_aliases` | — | — | — | name matching for "Featured in" `core/featured.php:138, 332` |
| `aw_founded` | Organisation :302 | `foundingDate` :130 | — | — |
| `aw_head` | Organisation :304 | `founder`/`employee` Person :157–166 | — | — |
| `aw_animals` | Services :251 | — | :37, 66, 318, 855 | — |
| `aw_areas_served` | Services :252 | `areaServed` Place :89 | — | — |
| `aw_services` (+ `_source`, `_on`) | Services :239–247 (stated-by note); binding `services` `core/bindings.php:122–127` | `knowsAbout` / `hasOfferCatalog` when no service tags :143–154 | :31, 250 | claims write source/date `core/claims.php:204–205`, enrichment `src/enrichment.php:427–428` |
| `aw_languages` | Services :253 | `knowsLanguage` :133 | — | — |
| `aw_website` | Contact :179–186 | `url`, `sameAs` :67, 114 | — | contact score; enrichment |
| `aw_instagram`, `aw_facebook`, `aw_x`, `aw_youtube`, `aw_linkedin` | Profiles :317; activity :462 (instagram) | `sameAs` :114 | — | robots/sitemap "unreachable listing" `seo/robots.php:80`, `seo/sitemap.php:113`; featured link keys |
| `aw_wikidata` | — | `sameAs` wikidata URL :114 | — | — |
| `aw_ngobase_url` | Sources `core/data-sources.php:99` | `sameAs` :114 | — | — |
| `aw_g_profile` | Profiles :317–321; activity :449 | `sameAs` :114 | — | sources |
| `aw_g_category` | — | `keywords` :178 | — | — |
| `aw_g_rating`, `aw_g_reviews` | Activity "On Google" :444–446 | not in schema (third-party rating, :123) | — | — |
| `aw_ig_followers`, `aw_ig_posts`, `aw_last_post` | Activity :451–457; enrichment extras `src/enrichment-front.php:71–79` | — | — | — |
| `aw_gallery` | Photos via `aw_listing_photo_ids` :356 | `image` :124–128 | — | enrichment writes `src/enrichment.php:347, 451`; claims `core/claims-review.php:527` |
| `aw_volunteer_url`, `aw_adopt_url` | Support buttons :337 | `JoinAction` (volunteer) :168–174 | volunteer/adoption answers :33, 35, 287, 297 | — |
| `aw_donate_url`, `aw_upi` | **none** — "No money anywhere (owner, 31 Oct 2026): … the stored values stay, unused" :336 | none (:167) | — | claim cleaning only `core/claims-review.php:438`; 221 / 18 rows |
| `aw_rel_nofollow` | rel nofollow sponsored on the listing's links :34 | — | — | — |
| `aw_verified`, `aw_verified_on` | "Claimed on {date}" badge binding `core/bindings.php:96–104`; meta row `core/item-head.php:184–185`; claim CTA :560; last updated :379 | — | — | directory filter `core/directory.php:173–187`, map `core/directory-map.php:62`, counts; written by claim approval `core/claims.php:223–224`, `core/claims-review.php:600–601`, ingest `src/ingest.php:326` |
| `aw_needs_reverify` | "Needs re-check" `core/bindings.php:99`, `core/item-head.php:186` | — | — | set by cron `core/cron.php:65, 81`; cleared by claims |
| `aw_detail_sources` (repeater) | "Last updated" / figures-on dates :383, 426 | — | — | — |
| `aw_source_url/_name/_date` | Sources block `core/data-sources.php:61–90`; last updated :379, 435 | — | — | gov-verified watch `core/gov-verified.php:48–72` |
| `aw_enrich_flags`, `aw_enrich_checked` | figures-on date :434 (checked) | — | — | enrichment queue/REST `src/enrichment.php:1074–1321` |

### 3.7 aw_press

| key | display | JSON-LD | other |
|---|---|---|---|
| `aw_press_url` | press card link `core/hub-blocks.php:432, 596`; permalink filter `core/campaign-tools.php:814` | `url` `seo/schema.php:1278` | dupes check, campaign links by keyword, relevance |
| `aw_press_publisher` | card source `core/hub-blocks.php:595` | `publisher` :1280 | admin column |
| `aw_press_logo` | card image fallback `core/hub-blocks.php:724` | image `aw_press_image_schema` :1256 | — |
| `aw_press_date` | card date `core/hub-blocks.php:516`; list sort `core/press.php:342`, hub query `core/hubs.php:217` | `datePublished` :1279 | — |
| `aw_press_format` | card chip `core/hub-blocks.php:657`; group sync `core/press.php:51` | — | — |
| `aw_press_platform`, `aw_press_account` | — | — | admin column `core/press.php:439–442`; sideload naming :94 |
| `aw_press_thumb_src` | — | — | fetch/ingest bookkeeping only |
| `aw_press_video` | **none** | — | **never read** (written by fetch :264); 2 rows |
| `aw_press_notes` | none (by design "not shown") | — | admin only |
| `aw_press_fetch` | — | — | box status line; triggers fetch when empty |

### 3.8 attachment

`kits_image_context` — Media list column `core/media-columns.php:214`; media auto-fill reads/writes `core/media-autofill.php:174–204`; removed from REST for viewers who cannot edit (`kits/image-context.php:55–63`). No front-end display.

---

## 4. Duplicates and boxes that add nothing

### 4.1 Same key edited in two boxes on one screen

None. Every key has one box per type: the Place, Issued by, Library item, provenance and campaign fields are drawn inside SCF groups and have no box of their own. Source keys are SCF-only per type; the listing logo is the Featured image with an SCF message pointing to it. Animal Writes → Edit screens checks this on every load ("Edited in two places").

### 4.2 Same subject, different keys (overlap, not a key duplicate)

- **aw_campaign partners:** "Partners" `aw_campaign_partners` (Organisations box, CSV) vs Related → "Participating organisations" `aw_participant` (meta rows). Both are read as role "Partner" on the listing's Campaigns block (`core/listing-template.php:515–525`). Also Related → "Organiser" `aw_organiser`. Live: partners stored on 1 campaign (empty value); `aw_participant`, `aw_organiser` 0 rows.
- **aw_opportunity organisation:** SCF "Organisation" `aw_org_name` (+ "Website" `aw_website`) vs Related → "Organisation" `aw_organisation` (listing link). Schema and display use both (`seo/schema.php:770–777, 941–945`).
- **aw_listing provenance:** "Where these details come from" (end of Claimed and sources) lists the same per-field sources the SCF fields show as a "Source:" line. Display only.
- **aw_listing "Claimed":** `aw_verified` / `aw_verified_on` are labelled Claimed / Claimed on and shown as "Claimed on {date}" (`core/bindings.php`); government verification is `aw_gov_verified_on/_by` (no box).
- **aw_listing gallery limit:** SCF save keeps 4 photos (`core/scf.php:92`); `aw_sanitize_meta` and the meta description say up to 6 (`core/meta.php:62, 136–138`).
- **Rank Math title/description** are typed in Rank Math and also filled by our code when not typed (`_aw_seo_auto`).

### 4.3 Boxes that add nothing (or nothing on that type)

- **LiteSpeed** on every public type and attachment: 0 `litespeed_*` rows on live.
- **Slug** on aw_claim, aw_report, aw_message, aw_press: types are not public (no URL); the admin account already hides it on aw_press.
- **Custom Fields** on block-editor types: off unless enabled; it is the only editor for `aw_campaign_metrics`, `aw_legal_dated`, `aw_file_hi_url`.
- **Linked from** on aw_legal/aw_listing/post: read-only; mostly "Nothing links here yet." (live: aw_rel_legal 2, aw_rel_resource 1, aw_rel_guide 9, aw_rel_org 0, aw_organisation 15 rows).
- **Related on aw_campaign:** its three hand rows (aw_rel_org, aw_organiser, aw_participant) have 0 rows on live; only the "Found in the text" ticks hold data (8).
- **Related → Listing types (post):** saved to `aw_rel_listing_type`, read only on the tag screen; 0 rows.
- **SCF read-only fields:** `aw_awbi_code` (listing), `aw_publisher` (news) — shown, never saved.
- **SCF fields never read on the front end or in schema:** `aw_requires_email` (Library), `aw_paid_listing` (Opportunity), `aw_donate_url`, `aw_upi`, `aw_sectors`, `aw_reg_authority`, `aw_reg_act`, `aw_awbi_recognised`, `aw_awbi_renewed` (Listing). Details in §6.
- **Coverage → Video embed URL** (`aw_press_video`): never read.
- Fallback boxes `aw-listing-details` and `aw-sponsored` are not registered while SCF is active (correct; listed for completeness).
- **Since 1 Nov 2026** LiteSpeed, Link Whisper Suggested Links, Rank Math Link Suggestions, Custom Fields, Slug and Linked from start collapsed on every post edit screen (`core/edit-screens.php`, `aw_edit_screen_collapsed_boxes()`; core `postbox_classes_{screen}_{box}` filter, adds `closed` only, and only while the user has no saved open/closed choice on that screen). Not removed.
- Stale ids in the admin account's saved order: `aw-faq-legal`, `aw-issuer`, `aw-place`, `kits_seo` (page), a Rank Math content box. None is registered now; WordPress skips them.

---

## 5. Term screens

Taxonomies with UI: category, post_tag (public), aw_campaign_link (`show_ui`, not public), aw_media_group (attachment, not public), link_category, wp_pattern_category. Our FAQ term box is hooked on `admin_init` (`seo/faq.php:851–860`) and Rank Math's term box and description editor when its Screen is loaded on term.php, so none of these appears in a command-line run; listed from the map. On a live term screen the map joins Rank Math's hooks by file, hook and priority (`term-rank-math`: `{taxonomy}_edit_form` 11; `term-rank-math-description`: `{taxonomy}_edit_form_fields` 1).

### category

| field (screen order) | stored as | type | sanitising / save | source | used |
|---|---|---|---|---|---|
| Name, Slug, Parent, Description | term fields | core | core | WordPress | everywhere |
| Description (Rank Math editor, edit) | core term description | HTML | Rank Math `category_description_editor` replaces the core field | Rank Math | category page description |
| Photo (add + edit) | term meta `aw_category_image` (attachment ID); fallback option `aw_category_images` kept in step | int | `absint`, must be an image; `manage_categories`; `created_/edited_category` (`core/category-images.php:47–85`) | ours | category page head and News/Library items in the category (`aw_category_image_id` :20–31; `core/media.php:118`; card fallback `core/hub-blocks.php:682`); 18 rows |
| Find help: listing types (add + edit) | term meta `aw_help_types` (array of post_tag IDs) | array | `absint`, `term_exists`; `manage_categories` (`core/model.php:248–286`) | ours | "Find help" on the category page (`aw_category_help_types` :289); 9 rows |
| Banner image (edit, prio 20) | term meta `aw_banner_image` | attachment ID | `absint`, image check (`core/category-images.php:122–141`) | ours | category banner `aw_category_banner_html` :144ff; 0 rows on live |
| FAQ (edit) | term meta `aw_faq` (JSON) | JSON | as post FAQ (`seo/faq.php:834–860`) | ours | FAQ block + FAQPage on the category page; 19 rows |
| Rank Math SEO (edit) | term meta `rank_math_title`, `_description`, `_focus_keyword`, `_robots`, `_breadcrumb_title`, `_facebook_image(_id)`, `_twitter_use_facebook`, `_seo_score` | | Rank Math `save_term_meta` (edit_term) | Rank Math (`tax_category_add_meta_box` on) | `rank_math_breadcrumb_title` read by our breadcrumbs `seo/breadcrumbs.php:30`; title/description filled by `aw_seo_sync_categories` `seo/programmatic.php:661–691` (`_aw_seo_auto` bookkeeping) |
| (save hook, no field) | `_aw_seo_auto` | JSON | `edited_/created_category` prio 30 | ours | auto-fill bookkeeping |

Category term meta with **no field** on the screen: `aw_category_card` (18, read by `aw_category_card_id` `core/media.php:94`), `aw_pinned` (1, read by `aw_search_mark` `core/pages.php:239`), `kits_seo_title`, `kits_seo_desc` (18 each, **no reader in code**), `_aw_seo_typed_before` (18, **no reader**).

### post_tag (labels, listing types, services)

| field | stored as | source | used |
|---|---|---|---|
| Name, Slug, Description | term fields | WordPress | |
| Description (Rank Math editor, edit) | core term description | Rank Math | tag page description |
| Photo (add + edit) | `aw_category_image` | ours `core/category-images.php:47–64` | tag page head; 0 tag rows on live |
| Guides linking here (edit, prio 20, listing-type tags only) | read-only (posts with `aw_rel_listing_type`) | ours `core/model.php:291–303` | — |
| FAQ (edit) | `aw_faq` | ours | tag FAQ; 19 rows |
| Rank Math SEO (edit) | `rank_math_title/description/focus_keyword/robots/canonical_url/breadcrumb_title/facebook_image/twitter_use_facebook/seo_score` | Rank Math (`tax_post_tag_add_meta_box` on) | `aw_seo_sync_tags` `seo/programmatic.php:933–960` writes title/description/robots |

### aw_campaign_link, aw_media_group, link_category, wp_pattern_category

Core fields only (name, slug, description). `aw_campaign_link` terms carry `aw_campaign_page` (2) and `aw_campaign_short` (2) with **no field** on the screen; read by campaign cards `core/hub-blocks.php:622–625` and `core/faq-data.php:1009`.

---

## 6. Saved but never read

Keys edited in (or registered for) an edit screen with no reader outside the box, its registration, and claim/enrichment field lists:

| key | type | edited in | live rows | note |
|---|---|---|---|---|
| `aw_requires_email` | aw_legal | SCF Document | 0 | only `core/meta.php:114` |
| `aw_paid_listing` | aw_opportunity | SCF Opportunity details | 0 | only `core/meta.php:86` |
| `aw_file_hi_url` | aw_legal | none (Custom Fields/REST) | 15 | registered `core/faq-data.php:460`, "no longer edited" |
| `aw_donate_url` | aw_listing | SCF | 221 | removed from display 31 Oct (`core/listing-template.php:336`) |
| `aw_upi` | aw_listing | SCF | 18 | same |
| `aw_sectors` | aw_listing | SCF | 20 | "admin only" |
| `aw_reg_authority`, `aw_reg_act` | aw_listing | SCF | 2 / 2 | "admin only" |
| `aw_awbi_recognised`, `aw_awbi_renewed` | aw_listing | SCF | 2732 / 124 | only claim/enrichment lists |
| `aw_darpan_blacklisted_by` | aw_listing | none | 1 | registered only; `_on` is read |
| `aw_press_video` | aw_press | Coverage box | 2 | written by fetch |
| `aw_rel_listing_type` | post | Related box | 0 | read only on the tag screen (admin) |
| `aw_press_notes`, `aw_press_platform`, `aw_press_account` | aw_press | Coverage box | 34 / 57 / 2 | admin only by design |
| term `kits_seo_title`, `kits_seo_desc`, `_aw_seo_typed_before` | category | none | 18 each | no code reference |
| SCF repeater reference rows `_aw_phones*` (1640+), `_aw_opening_hours*`, `_aw_detail_sources*`, and single `_aw_*` rows (e.g. `_aw_aliases` 161) | aw_listing | SCF | | SCF bookkeeping; our reads go to the value keys |
| former single fields `aw_phone` (326), `aw_whatsapp` (2), `aw_hours` (1), `aw_hours_spec` (1) | aw_listing | none | | "left in the database and nothing reads them" (`core/meta.php:195–200`) |
| page `kits_noindex`, `kits_og_*`, `kits_seo_*` | page | none (box `kits_seo` gone) | 1–3 | no reader in code |

---

## 7. Could not inspect / limits

- **Rank Math boxes** were not captured at runtime (Rank Math's metabox Screen only loads on post.php/term.php under `admin_init`). Their placement comes from Rank Math 1.0.280 code (`class-metabox.php:45–70, 152–245`) and the `rank-math-options-titles` settings.
- **FAQ term box** (hooked on `admin_init`) likewise from code only.
- **JavaScript sidebar panels** and **Dashboard widgets / report-page boxes** (§8) are listed from the map, not read live.
- **aw_campaign, aw_claim, aw_report** were not checked on a live edit screen after the 1 Nov 2026 changes.
- **aw_claim, aw_report:** no posts on live; checked with an unsaved post, so per-post conditions inside callbacks were not exercised.
- Box callbacks were not rendered (Link Whisper's deletes `wpil_links` when rendered), so content shown inside a box is from code.
- Third-party plugin internals (Rank Math, LiteSpeed, Link Whisper, SCF) were read only where they register or save boxes; all their meta keys are not exhaustively listed.
- "Never read" is by direct string search of `plugins/` and `themes/` on the branch (word-bounded key names, plus a check for built `'aw_' . $x` keys: only `src/ingest.php:321` builds keys, from its own field list). JS readers were included; the workers repo was not searched.
- Only the admin account has saved box-order meta.

---

## 8. Dashboard widgets and report pages

Not edit screens; listed on Animal Writes → Edit screens under "Dashboard (widgets)" and "Search Insights (report page)" from the map (`"screen": "admin"`), never read live and never counted as not mapped. None stores post meta.

**Dashboard widgets**

| id | title | plugin | purpose | file | stores |
|---|---|---|---|---|---|
| dashboard_right_now, dashboard_activity, dashboard_quick_press, dashboard_site_health, dashboard_primary | At a Glance, Activity, Quick Draft, Site Health Status, WordPress Events and News | WordPress (General row) | General | `wp-admin/includes/dashboard.php` | Quick Draft saves a draft post; the others nothing |
| mailweb_widget | Mail | Mailweb | Mail | `plugins/mailweb/includes/overview.php:158` | nothing |
| aw_visits | {site name} visits | Kits Core | Visits | `kits/visits.php:200` | nothing (reads options `aw_visits_total`, `aw_visits_since`) |
| aw_team_dates | Dates | Animal Writes core | Team dates | `core/team-dates.php:210` | nothing |
| aw_cl_widget | Campaign letters | Animal Writes core | Campaign letters | `core/campaign-letters.php:366` | nothing |
| aw_cron_health | Scheduled tasks | Animal Writes core | Scheduled tasks | `core/cron-health.php:105` | nothing |
| rank_math_dashboard_widget | Rank Math Overview | Rank Math SEO | SEO | `seo-by-rank-math/includes/admin/class-dashboard-widget.php:52` (Analytics module on) | not read live; confirm on the Dashboard |
| google_dashboard_widget | Site Kit Summary | Site Kit by Google | Analytics | — | not read live; confirm on the Dashboard (id not checked in the code) |
| litespeed_dashboard_widget | LiteSpeed | LiteSpeed Cache | Image / cache | — | not read live; confirm on the Dashboard (id not checked in the code) |
| advads_dashboard_widget | Advanced Ads | Advanced Ads | Ads | — | Advanced Ads is off on live; not read live (id not checked in the code) |

SCF adds no Dashboard widget (checked in SCF 6.9.6 code). There are no classic widgets (`register_widget`) in this repo.

**Search Insights report page** (AW SEO Plus, Overview tab; `seo/search-insights/admin.php:119–128`, normal context, shown with `do_meta_boxes`)

| id | title |
|---|---|
| aw-si-sources | Sources |
| aw-si-priorities | Priorities |
| aw-si-quick | Quick wins (position 8–20, at least 10 impressions) |
| aw-si-movers | Movers (28 days against the 28 before) |
| aw-si-brand | Brand |
| aw-si-ai | AI referrals (GA4, 28 days) |
| aw-si-referrers | Top referrers (GA4) |
| aw-si-site-search | Searches on our site (28 days, minimum) |
| aw-si-gaps | Content gaps (keyword map gaps and P2) |
| aw-si-crawl | Crawler access (weekly) |

Purpose "SEO report". They read Search Insights data and store no post meta.
