> Status as of 24 Nov 2026 (checked against git): Audit only. Implementation status: see system-reminder-you-are-operating-functional-island.md.

# Edit screens: audit and proposal (1 Nov 2026)

Read-only. No code changed, nothing committed, no live writes. Intended path for this file was
`/private/tmp/claude-501/-Users-abcom-Documents-GitHub-Animal-Writes/31bf60ad-bcba-41d1-a5bb-1d4ab1e33a1c/scratchpad/edit-screens-audit.md`
(plan mode allowed only this plan file; copy it there when edits are allowed). Raw live counts: `scratchpad/meta-counts.tsv` (same folder).

**Sources.** `docs/edit-screens-map.md` + `plugins/animal-writes-core/data/edit-screen-map.json` in worktree
`/Users/abcom/Documents/GitHub/aw-edit-screens-1101` (HEAD `ec64ac5c`); SCF JSON `acf-json/group_aw_*.json`; code in that worktree
(line refs below are on the worktree, which differs from the doc's `3c5cf6b4` only in faq.php / faq-data.php / media-columns / image-context);
live DB, read-only `SELECT`s over SSH (published + draft + private, trash excluded, empty values excluded).
Short paths: `core/` = animal-writes-core/includes, `seo/` = aw-seo-extras/includes, `src/` = aw-sourcing/includes.

**Live item counts:** post 203 · page 23 · aw_news 1476 · aw_legal 98 · aw_campaign 2 · aw_opportunity 22 · aw_listing 6801 · aw_press 64 · attachment 4143.

**Two platform facts that shape the proposal**

1. *Screen Options defaults only reach users with no saved choice.* `get_hidden_meta_boxes()` applies `default_hidden_meta_boxes` only when
   `metaboxhidden_{type}` is not saved. The admin account has it saved (empty array) for post, page, aw_legal, aw_campaign, aw_opportunity,
   aw_listing, aw_press, attachment — not for aw_news. So defaults change nothing for the admin on those types unless the box is also
   collapsed, or the admin unticks it once in Screen Options / Preferences.
2. *Block editor ignores Screen Options hiding.* `do_meta_boxes()` adds `hide-if-js` only when `! $screen->is_block_editor()`; in the block
   editor a box is turned off per user in Preferences → Panels. Core-only lever that works in both editors: the
   `postbox_classes_{screen}_{box}` filter adding `closed` (collapsed by default; the user's `closedpostboxes_*` still wins).
   → Proposal: classic screens (aw_news, aw_press, attachment) = `default_hidden_meta_boxes`; block-editor screens = collapsed via
   `postbox_classes_*`. Verify on live (read-only look) after the Deploy check; no local test setup.

---

## 1. Audit per post type (screen order now)

Rows = items with a non-empty value on live. Verdicts: **keep** / **merge into X** / **move to sidebar** / **remove from this type** / **hide by default**.
Core block-editor panels (Publish, Categories, Tags, Linked campaigns, Attributes, Featured image, Excerpt, Slug, Revisions) are kept unless said.

### post (Resources) — block editor — now 16 (8 core + 8 others)

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1–5 | Publish, Categories, Tags, Linked campaigns, Attributes (template) | core | terms, template | tags 203; linked campaigns 21 | keep |
| 6 | LiteSpeed | LiteSpeed `metabox.cls.php:76` | `litespeed_*` | **0** | hide by default (collapse) |
| 7 | Issued by | ours `core/issuers.php:75–76` | `aw_issuer` | **1** post | **remove from this type** (owner: Issued by belongs with Library). Keep the row |
| 8 | Featured image | core | `_thumbnail_id` | — | keep |
| 9 | Excerpt | core | excerpt | 198 | keep |
| 10 | Custom Fields | core | — | pref off | keep (off) |
| 11 | Slug | core | post_name | — | keep |
| 12 | Related | ours `core/model.php:124` | `aw_rel_guide` 9, `aw_rel_resource` 1, `aw_rel_org_auto` 6, `aw_rel_org` 0, `aw_rel_listing_type` 0 | 16 posts | keep; **merge Linked from into it** |
| 13 | Resource details | SCF `group_aw_resource` | Review `kits_last_reviewed` 7; Source `aw_source_*` 146; Sponsored 0 | 146 | keep → rename "Details", Source tab first |
| 14 | Linked from | ours `core/model.php:128` | read-only (targets of `aw_rel_guide`) | 9 rows point at posts | **merge into Related** |
| 15 | FAQ | ours `seo/faq.php:803` | `aw_faq` | 58 | keep |
| 16 | Link Whisper Suggested Links | Link Whisper `Base.php:764` | `wpil_*` | `wpil_sync_report3` 65 | hide by default (collapse) |
| — | Rank Math SEO | Rank Math (sidebar plugin) | `rank_math_*` | every item | keep |

Not edited anywhere but stored on posts: `aw_journal` 138, `aw_authors` 143, `aw_study_title` 146 (written by `core/research-citation.php`), `aw_state_code` 14 (auto, no Place box on post).

### page — block editor — now 9 (5 core + 4)

| # | Box | Source | Live data | Verdict |
|---|---|---|---|---|
| 1–2 | Publish, Page Attributes | core | — | keep |
| 3 | LiteSpeed | LiteSpeed | 0 | hide by default |
| 4–7 | Featured image, Custom Fields (off), Slug, Author | core | — | keep |
| 8 | FAQ | ours | `aw_faq` 19 | keep |
| 9 | Link Whisper | Link Whisper | `wpil_sync_report3` 20 | hide by default |
| — | Rank Math | Rank Math | all | keep |

Stale for admin: saved order lists `kits_seo` (gone). Page meta `kits_seo_*`, `kits_og_*`, `kits_noindex` (1–2 rows) have no reader.

### aw_news (News) — classic editor — now 16 (9 core + 7)

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1–5 | Publish, Categories, Tags, Linked campaigns, Post Attributes | core | | tags 320; linked campaigns 35 | keep |
| 6 | LiteSpeed | LiteSpeed | | 0 | hide by default (`default_hidden_meta_boxes` works here: no saved choice) |
| 7 | Place | ours `core/places-admin.php:19` | state/district/PIN/town | state 936 posts; `aw_place_source` auto 1001, **editor 3** | **remove from this type** (owner). Auto-detect keeps writing; data kept |
| 8 | Link Suggestions | Rank Math | | — | hide by default |
| 9 | Featured image | core | | — | keep |
| 10 | Rank Math SEO | Rank Math (real metabox) | | all | keep |
| 11 | Excerpt | core | | 1475 | keep |
| 12 | Custom Fields | core | | — | hide by default |
| 13 | Slug | core | | — | already hidden by core default for CPTs |
| 14 | Related | ours | `aw_rel_legal` 2; `aw_rel_org_auto` 240 posts (252 rows); `aw_rel_org_not` 1 | 242 | keep |
| 15 | Source | SCF `group_aw_news` | `aw_source_*` 1476; `aw_publisher` (read-only) 1384 | 1476 | keep → rename "Details" (Source tab) |
| 16 | FAQ | ours | `aw_faq` | **0** stored (auto rows only) | keep (owner: one FAQ section) |

Note: `aw_issuer` is stored on **78 news** items with no box on news; it is read by schema/dataLayer/relevance via `aw_item_issuer()`. Keep as is.

### aw_legal (Library) — block editor — now 17 (8 core + 9)

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1–3 | Publish, Categories/Tags/Linked campaigns, Post Attributes | core | | tags 98; linked campaigns 21 | keep |
| 4 | LiteSpeed | LiteSpeed | | 0 | hide by default |
| 5 | Issued by | ours `core/issuers.php:76` (cb :81) | `aw_issuer` (+ option `aw_issuers_added`) | **92 / 98** | **merge into Library details → Source tab** (the group already has a message field `field_aw_legal_msg_issuer` saying "set in the Issued by box") |
| 6 | Place | ours | state/district | state 29 (all `auto`) | **remove from this type** |
| 7–8 | Revisions, Excerpt, Custom Fields, Slug | core | | excerpt 98 | keep |
| 9 | Related | ours | `aw_rel_org` 0, `aw_rel_org_auto` 4 | 4 | keep; merge Linked from in |
| 10 | Library details | SCF `group_aw_legal` | Source `aw_source_*` 92; Document `aw_file_id` 23, `aw_file_url` 0, `aw_legal_number` 46, `aw_legal_force` 14, `aw_requires_email` **0, never read** | 92 | keep → "Details"; **take in Issued by + Library item**; drop `aw_requires_email` field from screen |
| 11 | Linked from | ours | targets of `aw_rel_legal` 2, `aw_rel_resource` 1 | 3 | merge into Related |
| 12 | FAQ | ours | `aw_faq` | 6 | keep |
| 13 | Library item | ours `core/faq-data.php:657–692` | `aw_short_name` 14, `aw_legal_act` **0**, `aw_key_sections` 8 | 14 | **merge into Library details** (new tab "Library item") |
| — | Rank Math | Rank Math | | all | keep |

No field today for `aw_legal_dated` (1 row, read at `core/item-head.php:152–156`) — candidate field in the same tab. `aw_file_hi_url` (15) stays unedited by decision.

### aw_campaign — block editor — now 17 (9 core + 8). Campaigns 13848 and 8 frozen: box changes only, no saves.

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1 | Campaign details (side) | ours `core/campaign-tools.php:587` (cb :591) | open/close, doc, partners, supporters, outcome + as-of, state override + reason, metric min/stale, share text/images, sent (read-only) | open 2, close 2, doc 1, supporters 1, partners 1 (empty), outcome 1, share 1, sent 1 | **merge into the SCF Details group** (tabs) |
| 2 | Publish, Categories, Tags, Linked campaigns, Post Attributes | core | | linked campaigns 2 | keep |
| 3 | LiteSpeed | LiteSpeed | | 0 | hide by default |
| 4 | Place | ours | | **0** | **remove from this type** |
| 5 | Real-world impact | ours `core/campaign-record.php:217` (cb :220) | `aw_campaign_impact_state` 1, `_evidence` 1 | 1 | **merge into Details → Outcome tab** |
| 6–7 | Featured image, Revisions, Excerpt, Custom Fields, Slug | core | | excerpt 2 | keep (Custom Fields is the only editor of `aw_campaign_metrics` 1) |
| 8 | Related | ours | `aw_rel_org` 0, `aw_organiser` 0, `aw_participant` 0; `aw_rel_org_auto` 1 post (8 rows) | 1 | keep (Organisations live here after key unification) |
| 9 | Campaign details (SCF) | SCF `group_aw_campaign` | `aw_event_date` 1, `aw_event_place` 1, **`aw_campaign_close`, `aw_campaign_doc` duplicated** | 1 | keep as the one "Details"; **remove the two duplicate fields** (SCF saves last and overwrites the side box, see map §4.1) |
| 10 | FAQ | ours | `aw_faq` | 0 | keep |
| — | Rank Math | Rank Math | | 2 | keep |

### aw_opportunity — block editor — now 13 (7 core + 6). Opportunities paused: lowest priority.

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1 | Publish, Categories, Tags, Linked campaigns, Post Attributes | core | | tags 21; linked campaigns **0** | keep |
| 2 | LiteSpeed | LiteSpeed | | 0 | hide by default |
| 3 | Place | ours | | state 16 (auto) | **remove from this type** |
| 4 | Excerpt, Custom Fields, Slug | core | | excerpt 22 | keep |
| 5 | Related | ours | `aw_organisation` (max 1) | 15 | **merge into Details → Organisation tab** (next to `aw_org_name`), later |
| 6 | Opportunity details | SCF `group_aw_opportunity` | `aw_org_name` 22, `aw_deadline` 6, `aw_review_until` 20, `aw_event_schedule` 2, `aw_apply_url` 21, `aw_website` 4, `aw_employment_type` 13, `aw_paid_listing` **0 never read**, `aw_source_*` 17 | 22 | keep → "Details", Source tab first; drop `aw_paid_listing` from screen |
| 7 | FAQ | ours | | 0 | keep |
| — | Rank Math | Rank Math | `rank_math_rich_snippet` 6 | | keep |

Admin already hides `acf-group_aw_opportunity`, `aw_related`, `aw-faq` here (`metaboxhidden_aw_opportunity`). Stored, no box: `aw_closes` 22, `aw_org_logo` 11.

### aw_listing (Directory) — block editor — now 15 (7 core + 8)

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1 | Publish, Categories, Tags (listing types), Post Attributes | core | | tags 6799 | keep |
| 2 | LiteSpeed | LiteSpeed | | 0 | hide by default |
| 3 | Place | ours | `aw_state_code` 6785, `aw_district_code` 6546, `aw_pincode` 3795, `aw_place_city` 323 | 6785 | keep on this type only → **merge into Details → "Address and place" tab** |
| 4 | Where these details come from | ours `src/enrichment-admin.php:130` (cb :134) | read-only provenance; `aw_enrich_flags` 471; `aw_enrich_checked` 6800 | 6800 | **merge into Details → Sources tab** |
| 5 | Featured image (logo) | core | | — | keep |
| 6 | Excerpt (short bio), Custom Fields, Slug | core | | excerpt 280 | keep |
| 7 | Organisation details | SCF `group_aw_listing` (9 tabs) | see §2 | 6801 | keep → the one "Details" |
| 8 | Linked from | ours | `aw_organisation` 15 + featured-in rows | ≤15 listings | **move to sidebar** (side/low) |
| 9 | FAQ | ours | `aw_faq` | 0 stored | keep |
| 10 | Volunteers | ours `core/org-people.php:109` | read-only FluentCRM list (cap `edit_aw_volunteers`) | — | hide by default (collapse); or move to sidebar |
| — | Rank Math | Rank Math | | all | keep |

### aw_press (Media & PR) — classic, not public — now 7 (5 core + 2)

| # | Box | Source | Holds | Live data | Verdict |
|---|---|---|---|---|---|
| 1–3 | Publish, Tags, Linked campaigns, Featured image | core | | linked campaigns 57 | keep |
| 4 | Coverage | ours `core/press.php:135` | `aw_press_url` 64, `_publisher` 64, `_date` 63, `_format` 64, `_platform` 57, `_logo` 24, `_thumb_src` 61, `_notes` 18, `_account` 2, `_video` 2 (never read) | 64 | keep → title "Details", Source fields first |
| 5 | Slug | core | | — | hide by default (type has no URL; admin already hides it) |
| 6 | Related | ours | `aw_rel_org` 0, `aw_rel_org_auto` 2 | 2 | keep |

### attachment — classic — now 6 boxes + 2 fields

| Box | Live data | Verdict |
|---|---|---|
| Save | — | keep |
| Media groups (`aw_media_group`) | 12 terms, 4115 links | keep |
| Attachment Attributes | — | keep |
| LiteSpeed | 0 | hide by default |
| Slug | — | keep (attachment pages) |
| Author | — | already hidden by admin |
| Fields: Image context `kits_image_context` 3297, Focus keyphrase `kits_image_keyphrase` 3297 | | keep |

### Terms

| Screen | Fields now | Live data | Verdict |
|---|---|---|---|
| category | core, Photo `aw_category_image` 18, Find help types `aw_help_types` 9, Banner image `aw_banner_image` **0**, FAQ 19, Rank Math | | keep all; Banner 0 rows → candidate to hide (owner call) |
| post_tag | core, Photo (0 tag rows), Guides linking here (read-only), FAQ 19, Rank Math | | keep |
| aw_campaign_link | core only; `aw_campaign_page` 2 and `aw_campaign_short` 2 have **no field** | 2 terms, 136 links | add the two fields (small, later) |
| aw_media_group | core only | 12 terms | keep |

---

## 2. One "Details" schema

Rule: every content type gets **one** SCF group titled **"Details"** (box id stays `acf-group_aw_{type}`), tabs in the same order:
**Source → Place (listing only) → [module tabs] → Review / Sponsored (where they apply)**. Custom PHP UIs (Issued by, Place, provenance,
campaign tools) render *inside* a tab through SCF's `acf/render_field/key=…` on a `message` field — the pattern `src/enrichment-admin.php:112`
already uses — so field names, nonces and save handlers stay as they are. No new CSS/JS.

### 2.1 Shared core

| Field | Key | Modules | Today (box / tab) | Proposed (box / tab) |
|---|---|---|---|---|
| Source URL | `aw_source_url` | post, news, legal, opp, listing | SCF tab Source (news: untabbed "Source" group) | Details / Source |
| Source name | `aw_source_name` | same | same; **label "Publisher name" on news** | Details / Source, label "Source name" everywhere (copy change: owner's yes) |
| Source date | `aw_source_date` | same | same | Details / Source |
| Publisher key (read-only) | `aw_publisher` | news | Source group | Details / Source |
| Source (press) | `aw_press_url`, `aw_press_publisher`, `aw_press_date` | press | Coverage box | Details (Coverage box retitled), first three fields. **Same concept, other keys** |
| Issuer | `aw_issuer` | legal (92); also stored on news 78, post 1 | Issued by box (side) on legal + post | Details / Source (legal only) |
| Place | `aw_state_code`, `aw_district_code`, `aw_pincode`, `aw_place_city` (+ `aw_place_source`, `aw_place_auto`) | listing (edit); news/legal/opp/post auto only | Place box (side) on 5 types | Details / Address and place (listing only) |
| Short name | `aw_short_name` | legal | Library item box | Details / Library item. (Campaign short name lives as term meta `aw_campaign_short` — flag) |
| Last reviewed | `kits_last_reviewed` | post | SCF tab Review | Details / Review (post) |
| Sponsored, Sponsor name | `aw_sponsored`, `aw_sponsor_name` | post (0), listing (1, no field) | SCF tab Sponsored (post) | Details / Sponsored (post). `aw_paid_listing` (opp, 0, never read) → off screen |
| Website | `aw_website` | opp 4, listing 364 | SCF Apply / Website and social | same key, fine |
| Event date | `aw_event_date` | opp, campaign 1 | SCF | same key, fine |

### 2.2 Per-module sections

| Module | Tab | Fields (key) | Today | Proposed |
|---|---|---|---|---|
| **Library** | Document | `aw_file_id`, `aw_file_url`, `aw_legal_number`, `aw_legal_force` | SCF Document | same; drop `aw_requires_email` |
| | Library item | `aw_short_name`, `aw_legal_act`, `aw_key_sections` (+ new `aw_legal_dated`) | Library item box | Details / Library item as SCF text/text/textarea(/date) fields; remove box + save `core/faq-data.php:657–692` |
| **Listing** | Contact | `aw_email`, `aw_phones` | SCF | same |
| | Address and place | Place UI (rendered) + `aw_area`, `aw_address`, `aw_maps_url`, `aw_lat`, `aw_lng`, `aw_place_id` | Place box + SCF | one tab |
| | Opening hours, Registrations, About and services, Website and social, Logo/photos/support | as today | SCF | same |
| | Verification | `aw_verified`, `aw_verified_on`, `aw_needs_reverify` | SCF, labels "Verified" | same; labels "Claimed"/"Claimed on" to match the site (copy: owner's yes) |
| | Sources | `aw_source_*`, `aw_detail_sources` + provenance list and "Identity unresolved" tick (rendered) | SCF + side box | one tab |
| **Opportunity** | Organisation | `aw_org_name`, `aw_organisation` (link), `aw_website` | SCF + Related box | one tab (later; paused) |
| | Dates | `aw_deadline`, `aw_review_until`, `aw_event_date`, `aw_event_schedule` | SCF | same |
| | Apply | `aw_apply_url`, `aw_employment_type` | SCF | drop `aw_paid_listing` |
| **Campaign** | Event | `aw_event_date`, `aw_event_place` | SCF | same |
| | Dates and state | `aw_campaign_open`, `aw_campaign_close`, `aw_campaign_state_override`, `aw_campaign_override_note` | side box (+ duplicate close in SCF) | side-box UI rendered here; SCF duplicate removed |
| | Document | `aw_campaign_doc` | side box + SCF duplicate | side-box UI only |
| | Organisations | `aw_campaign_partners`, `aw_campaign_supporters` | side box | here for now (see 2.3) |
| | Outcome | `aw_campaign_outcome`, `_outcome_asof`, `aw_campaign_impact_state`, `_impact_evidence` | side box + Real-world impact box | one tab |
| | Letters and share | `aw_campaign_metric_min`, `_metric_stale`, `_share_text`, `_share_story`, `_share_post`, `aw_campaign_sent` (read-only) | side box | one tab |
| **Resource** | Source, Review, Sponsored | as today | SCF | same; no Issued by |
| **News** | Source | `aw_source_*`, `aw_publisher` | SCF "Source" | Details / Source |
| **Press** | (Source) + Coverage | `aw_press_*` | Coverage | box retitled Details; order url, publisher, date, then format, platform, account, logo, thumbnail, video, notes |

### 2.3 Same concept, different keys (propose one; no migration now)

| Concept | Keys today (live rows) | Proposed single key | Note |
|---|---|---|---|
| Organisation that runs it (link to listing) | `aw_organisation` (opp 15) · `aw_organiser` (campaign 0) | `aw_organisation` | both max 1; both are relations in `aw_relations()` (`core/model.php:31–41`) |
| Organisation name (text) | `aw_org_name` (opp 22) | keep as the text fallback of `aw_organisation` | schema/display read both (`seo/schema.php:770–777, 941–945`) |
| Partners | `aw_campaign_partners` (CSV, 1 campaign, empty) · `aw_participant` (rows, 0) | `aw_participant` (meta rows, relation UI, shows in Linked from) | both read as "Partner" at `core/listing-template.php:515–525` |
| Supporters | `aw_campaign_supporters` (CSV, 1 with data) | new relation `aw_supporter` (rows) | migrate the one value later (frozen campaign: owner call) |
| Featured organisations | `aw_rel_org` (0) + `aw_rel_org_auto`/`_not` | keep | |
| Source of the item | `aw_source_url/_name/_date` · `aw_press_url/_publisher/_date` | `aw_source_*` | press keys read in hub-blocks/schema/press.php |
| Publisher | `aw_source_name` (labelled "Publisher name" on news) · `aw_publisher` (derived) · `aw_press_publisher` | `aw_source_name` for the name; `aw_publisher` stays derived | |
| Closing date | `aw_deadline` (opp) · `aw_campaign_close` (campaign) · `aw_review_until` · `aw_closes` (computed) | keep separate for now; label both "Closes on" | different behaviours (retention vs campaign state) |
| Place as text | `aw_event_place` (campaign 1) · Place codes | keep; Place codes are LGD-only | |
| Short name | `aw_short_name` (legal 14) · term meta `aw_campaign_short` (2) | `aw_short_name` | |
| Paid/sponsored | `aw_sponsored` (post 0, listing 1) · `aw_paid_listing` (opp 0, never read) | `aw_sponsored` | standing "no featured listings": do not touch `aw_featured` |
| Logo | `_thumbnail_id` (listing) · `aw_press_logo` (24) · `aw_org_logo` (opp 11) | keep; note only | |
| "Verified" | `aw_verified` (= Claimed) · `aw_gov_verified_on/_by` (3657) | keep keys; fix labels | per Claimed vs Verified rule |
| Outcome | `aw_campaign_outcome` · `aw_campaign_impact_state` | keep both, one tab | different meaning (campaign result vs real-world change) |

---

## 3. Target layout (fewest boxes)

M = main column (normal), S = sidebar. Core panels unchanged. "(collapsed)" = registered, closed by default; "(hidden)" = Screen Options default.

| Type | Main column, in order | Sidebar (non-core) | Collapsed/hidden by default |
|---|---|---|---|
| post | Details (Source · Review · Sponsored) → Related (+ Linked from list) → FAQ | Rank Math | LiteSpeed, Link Whisper (collapsed) |
| page | FAQ | Rank Math | LiteSpeed, Link Whisper (collapsed) |
| aw_news | Rank Math → Details (Source) → Related → FAQ | — | LiteSpeed, RM Link Suggestions, Custom Fields (hidden) |
| aw_legal | Details (Source + Issued by · Document · Library item) → Related (+ Linked from) → FAQ | Rank Math | LiteSpeed (collapsed) |
| aw_campaign | Details (Event · Dates and state · Document · Organisations · Outcome · Letters and share) → Related → FAQ | Rank Math | LiteSpeed (collapsed) |
| aw_opportunity | Details (Source · Organisation · Dates · Apply) → Related (until moved) → FAQ | Rank Math | LiteSpeed (collapsed) |
| aw_listing | Details (Contact · Address and place [+Place] · Hours · Registrations · About · Web · Photos · Verification · Sources [+provenance]) → FAQ | Linked from (low), Rank Math | LiteSpeed, Volunteers (collapsed) |
| aw_press | Details (Coverage) → Related | — | Slug (hidden) |
| attachment | fields | Media groups, Attributes | LiteSpeed (hidden) |

**Box counts** (everything shown by default incl. core panels; in brackets: non-core boxes)

| Type | Now | Proposed visible | + collapsed/hidden |
|---|---|---|---|
| post | 16 [8] | 12 [4] | 2 |
| page | 9 [4] | 7 [2] | 2 |
| aw_news | 16 [7] | 12 [4] | 3 |
| aw_legal | 17 [9] | 12 [4] | 1 |
| aw_campaign | 17 [8] | 13 [4] | 1 |
| aw_opportunity | 13 [6] | 11 [4] → 10 [3] after Organisation move | 1 |
| aw_listing | 15 [8] | 12 [4] (+ Linked from in sidebar) | 2 |
| aw_press | 7 [2] | 6 [2] | 1 |
| attachment | 6 + fields | 4 + fields | 2 |

---

## 4. Change list (ship in this order; one PR per batch, commit per step)

All: core WordPress / SCF APIs only, no CSS/JS added, no stored key deleted or renamed. Verify by lint, PHPCS, Deploy check, read-only live look.

| # | Change | File(s) | Effort | Risk | Data impact |
|---|---|---|---|---|---|
| 1 | Remove the duplicate `aw_campaign_close` and `aw_campaign_doc` fields from the SCF group (fixes SCF overwriting the side box on save) | `acf-json/group_aw_campaign.json` | S | low | none (keys stay; only the side box writes them) |
| 2 | Hide/collapse third-party and empty boxes: `default_hidden_meta_boxes` (aw_news: litespeed_meta_boxes, rank_math_metabox_link_suggestions, postcustom; aw_press: slugdiv; attachment: litespeed_meta_boxes) + `postbox_classes_{type}_litespeed_meta_boxes` / `_wpil_link-articles` / `_aw_org_people` → `closed` on block-editor types | new section in `core/edit-screens.php` | S | low; check in the block editor that collapsed state applies (platform fact 2) | none. Admin's saved `metaboxhidden_*` wins (fact 1) — tell the owner, or untick once |
| 3 | Place box only on Directory listings: admin-only list (e.g. `AW_PLACE_EDIT_TYPES = array( 'aw_listing' )`) used at `core/places-admin.php:18` (register) and `:144` (JS); **keep `AW_PLACE_TYPES`** — it drives place counts/related (`core/places.php:323`, `core/related.php:94`) | `core/places-admin.php` | S | low | none; save is skipped when the nonce is absent (`:81`). 3 news places set by an editor stay; auto-detect continues |
| 4 | Issued by off Resources: `array( 'aw_legal', 'post' )` → `array( 'aw_legal' )` | `core/issuers.php:75` | S | low | none (1 post keeps `aw_issuer`) |
| 5 | Related + Linked from in one box: register `aw_related` when either applies; callback prints the Related editor, then the Linked from list (`aw_linked_from_box`); listing (only Linked from) → side/low | `core/model.php:120–131`, callbacks :144/:173 | M | low | none (Linked from stores nothing) |
| 6 | Issued by into Library details: render `aw_issuer_box()` in `acf/render_field/key=field_aw_legal_msg_issuer` (replace its message), stop registering `aw-issuer` on aw_legal; keep save handler + nonce `aw_issuer_save` | `core/issuers.php:72–79`, `acf-json/group_aw_legal.json` | S–M | low (form fields stay inside the post form) | none |
| 7 | Library item into Library details: new tab "Library item" with text `aw_short_name`, text `aw_legal_act`, textarea `aw_key_sections` (optionally date `aw_legal_dated`); remove the box + its save (`core/faq-data.php:657–692`); keep `register_post_meta` (:647–655); drop `aw_requires_email` field | `acf-json/group_aw_legal.json`, `core/faq-data.php` | M | low–med: confirm `aw_value_guard` (`core/scf.php`) keeps textarea line breaks and deletes on empty, as the box did | none |
| 8 | Listing: Place UI into "Address and place" tab, provenance into "Sources" tab (render through message fields); stop registering `aw-place` (via #3 list → empty for listing box, rendered in SCF instead) and `aw-enrich-prov` | `core/places-admin.php`, `src/enrichment-admin.php:128–134`, `acf-json/group_aw_listing.json` | M | med: Place JS uses element ids and must still find them inside a closed SCF tab; nonces `aw_place_nonce`, `aw_enrich_identity` must post | none |
| 9 | Campaign: one Details group — render `aw_camp_details_box()` and `aw_camp_record_box()` in SCF tabs; stop the two side boxes. Note the side save writes every field on any save (`core/campaign-tools.php`), unchanged | `core/campaign-tools.php:586–591`, `core/campaign-record.php:216–220`, `acf-json/group_aw_campaign.json` | M | med; campaigns 13848/8 frozen — do not save them to test | none |
| 10 | Uniform titles "Details" and tab order (Source first) for all six groups + Coverage box; news "Publisher name" → "Source name"; listing "Verified" → "Claimed" | `acf-json/*.json`, `core/press.php:135` | S | low — **admin copy: paste in chat, owner's yes first** | none |
| 11 | Off-screen fields with 0 rows and no reader: `aw_paid_listing` (opp), `aw_rel_listing_type` row in Related (post) | `acf-json/group_aw_opportunity.json`, `core/model.php` | S | low | none (keys stay registered) |
| 12 | Opportunity Organisation link into Details (paused type — only when owner wants) | `core/model.php`, `acf-json/group_aw_opportunity.json` | M | low | none |
| 13 | Later, needs its own plan + owner OK: key unification (§2.3: `aw_organiser`→`aw_organisation`, partners → `aw_participant`, supporters relation, press → `aw_source_*`); term fields for `aw_campaign_page`/`aw_campaign_short` | several | L | med–high (readers in templates/schema) | migration — not now |

After each step: update `data/edit-screen-map.json` + `docs/edit-screens-map.md` (box ids/types change), and pending.md by title.

## Open points for the owner
- Admin account has saved Screen Options on 8 screens → defaults won't show for that account; OK to untick once instead of a user-meta change?
- Collapsed (block editor) vs truly off: truly off needs Preferences → Panels per user (or a small core `wp.data` preferences default script — would be JS, so not proposed).
- Copy changes in #10 need an explicit yes on the exact labels.
