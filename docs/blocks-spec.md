# Blocks spec (11 Nov 2026; answers added 11 Nov)

The front-end plan for the owner's custom-blocks decision of 11 Nov 2026. It goes with the Kits admin rebuild of the same day (Home, Theme Options, Settings, Plugins, Tools, Help; Theme Options → Socials is the one source for social links).

This is a spec only. Nothing here is built. The owner reviews it first; each phase below is built on its own branch and pull request, and each content change on live waits for its own yes.

## The decision

Custom blocks only where WordPress has no native way: 7 new blocks in Kits Core, plus `kits/hero` (built, unchanged), plus one DirectoryWeb block, `directoryweb/people` (3.13).

| Block | Replaces | Does |
|---|---|---|
| `kits/letter` | `[aw_letter_tool]` | Builds a letter from the grounds a visitor ticks; sends it with Gmail, an email app, or Copy |
| `kits/countdown` | `[aw_countdown]` | Shows the days left |
| `kits/metric` | `[aw_campaign_count]`, `[aw_campaign_metric]`, `[aw_campaign_impact]`, `[aw_campaign_outcome]` | Shows a number from data, with a threshold |
| `kits/collection-filter` | `[aw_directory_filter]`, `[aw_find_help]`, the hub toolbar | Filters and sort for any list; DirectoryWeb adds its filters |
| `kits/share` | The share bar | Share links and Copy link |
| `kits/sticky-cta` | `[aw_sticky_cta]` | The action bar on phones |
| `kits/sponsored` | The 2 sponsored blocks | Sponsored slots |

Everything else becomes a native WordPress feature (section 3). PlanWeb, InsightWeb, SourceWeb and MailWeb have no front-end blocks.

**Rules that hold for every phase**
- Kits Core and the Kits theme get no Animal Writes names. Only new code gets `kits` names; the `aw` to `kits` rename of existing code stays deferred. Where a new Kits block needs site data (a campaign's close date, a campaign's metrics), it asks through a `kits_*` filter and Animal Writes Core answers.
- Old shortcodes and old blocks keep rendering until their content is moved. Each old block stays registered, hidden from the inserter (`supports.inserter: false`), so saved content never shows "This block is not supported".
- No build step, like `kits/hero`: plain `index.js` on the `wp` globals for the editor, and a plain ES module `view.js` with a hand-written `view.asset.php` (dependency `@wordpress/interactivity`) where a block needs behaviour.
- Sizes and colours come only from `docs/design-system.md`, `docs/card-sizes.md`, the component map and the registry tokens. No new values.
- Classes and `data-*` attributes that `aw-analytics.js` listens to are kept, or the listener is moved in the same change (list in section 4, Risks). No GA4 or GTM change without the owner's word.

---

## 1. Inventory

Found with `grep` for `add_shortcode`, `register_block_type`, `registerBlockType`, `block.json`, `render_callback` and `render_block` across `plugins/` and `themes/` on `claude/blocks-spec-1111-135dba` (from `637e9e64`).

"Live use" is a read-only database search on 11 Nov 2026 (published, draft, private and scheduled items; revisions left out) plus the theme files. "Theme files" means both copies: `themes/animal-writes/` and `plugins/animal-writes-core/theme/`. No template or template part is saved in the database on live, so the files are what live shows.

### 1a. Shortcodes (37)

| Shortcode | File and line | Attributes | Live use | Row that covers it |
|---|---|---|---|---|
| `[kits_demo]` | `kits-core/includes/demo.php:221` | none | Page 8928 `kits-demo` (private) | `[kits_demo]`: stays the admin demo page |
| `[aw_source_row]` | `kits-core/includes/attribution.php:68` | `link` (`0`/`1`) | None in content | Source row → core post blocks with Block Bindings |
| `[aw_rule_chip]` | `animal-writes-core/includes/campaign-tools.php:259` | `rule`, `label`, `allows`, `doc` | Campaign 13848 (Lab) | `[aw_rule_chip]` → a text format |
| `[aw_letter_tool]` | `campaign-tools.php:292` | `to`, `subject`, `ticked` (comma list), `salute` (`Sir/Madam,`), `intro`, `closing`, `sign` (`Yours faithfully,`), `address`, `source` | Campaign 13848 (Lab) | `kits/letter` |
| `[aw_campaign_count]` | `campaign-tools.php:488` | `min` (campaign meta, else 100), `label` (`objections sent`) | None | `kits/metric` |
| `[aw_campaign_partners]` | `campaign-tools.php:512` | `title` (`Partners`) | Campaigns 13848, 23202 (Diwali), 23225 (Supreme Court) | Query Loop variation |
| `[aw_camp_status]` | `campaign-tools.php:535` | none | Campaigns 8, 13848, 23202, 23225 | Campaign state: stays on live as it is |
| `[aw_sticky_cta]` | `campaign-tools.php:556` | `href` (`#send`), `label` (`Send your objection`), `days` (`1`), `pre_href` (`#updates`), `pre_label` | Campaigns 13848, 23202, 23225 | `kits/sticky-cta` |
| `[aw_campaign_band]` | `campaign-tools.php:574` | `campaign`, `title`, `text`, `button`, `link`, `theme`, `label`, `art`, `art_phone` | Page 57 (Home) | Pattern, edited by hand (3.18) |
| `[aw_countdown]` | `animal-writes-core/includes/campaigns.php:29` | `date` (YYYY-MM-DD; else the campaign's close date), `label` | Campaigns 13848, 23225; theme files `patterns/campaign.php` | `kits/countdown` |
| `[aw_report_box]` | `animal-writes-core/includes/reports.php:100` | none | None | Report form → Fluent Forms block |
| `[aw_section_head]` | `animal-writes-core/includes/sections.php:190` | none (archive head) | None (saved content only) | Section heads → a pattern |
| `[aw_find_help]` | `sections.php:334` | `compact` (bool) | Page 57 (Home); theme files `patterns/find-help.php` | `kits/collection-filter` |
| `[aw_campaign_metric]` | `animal-writes-core/includes/campaign-record.php:83` | `key` | Campaign 8 (Community dogs) | `kits/metric` |
| `[aw_campaign_impact]` | `campaign-record.php:109` | none | Campaign 8 | `kits/metric` |
| `[aw_campaign_outcome]` | `campaign-record.php:127` | none | Campaign 8 | `kits/metric` |
| `[aw_org_people]` | `animal-writes-core/includes/org-people.php:85` | none | Theme files `templates/single-aw_listing.html` | `org-people` → Query Loop variation (DirectoryWeb) |
| `[aw_directory_filter]` | `animal-writes-core/includes/directory.php:587` | none (reads `q`, `state`, `district`, `tag[]`, `verified`, `gov` from the address) | None in content; printed by the List filters block | `kits/collection-filter` |
| `[aw_not_found]` | `animal-writes-core/includes/not-found.php:104` | none | Theme files `templates/404.html` | Template or page content |
| `[aw_related]` | `animal-writes-core/includes/related.php:343` | none | None found | Query Loop variation |
| `[aw_claim_page]` | `animal-writes-core/includes/form-blocks.php:170` | none (reads the listing from the address) | Page 12587 `claim` | Claims → Fluent Forms block with a pre-fill hook, plus a pattern |
| `[aw_report_page]` | `form-blocks.php:178` | none (reads `item`, `reason`) | None in content | Report form → Fluent Forms block |
| `[aw_thank_you]` | `form-blocks.php:209` | none (reads `form`) | Page 12589 `thank-you` | Template or page content |
| `[aw_volunteer_update]` | `animal-writes-core/includes/volunteers.php:97` | none (reads token `t`) | Page 19270 `update` | Volunteer-update form → Fluent Forms block |
| `[aw_newsletter]` | `animal-writes-core/includes/newsletter-fluentforms.php:42` | none | Synced pattern 12752 `newsletter-box`; theme files `parts/newsletter.html` | Newsletter form → Fluent Forms block |
| `[aw_copy_needed]` | `animal-writes-core/includes/pages.php:24` | `what` | None | Removed (3.18) |
| `[aw_categories_index]` | `pages.php:37` | none | Page 8759 `categories` | Categories index → core Categories / Terms blocks |
| `[aw_contact_form]` | `pages.php:98` | none | Page 61 `contact` | Contact form → Fluent Forms block |
| `[aw_categories_list]` | `pages.php:141` | `style` (`links`) | None | Categories list → core Categories / Terms blocks |
| `[aw_search]` | `pages.php:183` | none (reads `q`) | Page 8761 `search` | `[aw_search]` → core Search block |
| `[aw_category_hub]` | `pages.php:229` | none (reads the sort) | Theme files `templates/category.html` | Categories hub → core Terms blocks or a Query Loop variation |
| `[aw_elsewhere]` | `pages.php:377` | none | Page 61 `contact` | Core Social Icons block, reading Theme Options → Socials |
| `[aw_testimonials]` | `animal-writes-core/includes/admin-ia.php:63` | none (prints nothing) | None | Removed: no live use (3.18) |
| `[aw_banner]` | `admin-ia.php:63` | none (prints nothing) | None | Kits Banner pattern (3.18) |

### 1b. Custom blocks (51)

All are dynamic (a PHP render callback), except `kits/hero`, which saves its markup. The `animal-writes/*` blocks share one loader, `animal-writes-core/includes/hub-blocks.php:74` (the list at lines 23–64, render callbacks `aw_block_*`), editor script `assets/js/hub-blocks.js` (ServerSideRender preview). Five more files add to that list through the `aw_hub_blocks` filter.

| Block | Defined at | Attributes | Live use | Row that covers it |
|---|---|---|---|---|
| `kits/hero` | `kits-core/blocks/hero/block.json`, `kits-core/includes/blocks.php:32` | `align` (`full`) + block supports | Kits theme pattern `hero.php` | `kits/hero`: no change |
| `animal-writes/sponsored-item` | `animal-writes-core/includes/sponsored-blocks.php:73` | `postId` | None | `kits/sponsored` |
| `animal-writes/sponsored-banner` | `sponsored-blocks.php:82` | `mediaId`, `mediaAlt`, `heading`, `text`, `url`, `linkText`, `sponsor` | None | `kits/sponsored` |
| `animal-writes/breadcrumb` | `hub-blocks.php:24` | none | Hub pages 8811, 8935, 8937, 8939, 8941, 8943, 8945; theme files `single.html`, `single-aw_listing.html` | Rank Math Breadcrumbs block (3.18) |
| `animal-writes/section-head` | `hub-blocks.php:25` | `postType` | Theme files `archive`, `category`, `page`, `page-volunteer`, `tag` | Section heads → a pattern (see note 1) |
| `animal-writes/hub-count` | `hub-blocks.php:26` | `postType` | Hub pages 8935, 8937, 8939, 8941, 8943 (prints nothing since 20 Oct) | Hub toolbar → `kits/collection-filter` |
| `animal-writes/hub-visual` | `hub-blocks.php:27` | `postType` | Hub pages 8811, 8935, 8937, 8939, 8941, 8943, 8945 | Kits Banner pattern, or core Cover / Image (3.18) |
| `animal-writes/list-filters` | `hub-blocks.php:28` | `postType` | Hub pages 8811, 8935, 8937, 8939, 8941, 8943, 8945 | Hub toolbar → `kits/collection-filter` |
| `animal-writes/law-date` | `hub-blocks.php:29` | none | None in content | Item meta → core post blocks with Block Bindings |
| `animal-writes/read-meta` | `hub-blocks.php:30` | none | Synced list patterns 8934, 8936, 8938, 8940, 8946; Home 57; campaigns 8, 13848, 23202, 23225 | Item meta → core post blocks with Block Bindings |
| `animal-writes/place` | `hub-blocks.php:31` | none | None in content | Attributes → core post blocks with Block Bindings |
| `animal-writes/opp-actions` | `hub-blocks.php:32` | none | Synced pattern 8940 `opportunities-list` | Core blocks with Block Bindings in the card pattern (3.18) |
| `animal-writes/org-mark` | `hub-blocks.php:33` | none | Synced pattern 8944 `directory-list` | Core blocks with Block Bindings in the card pattern, DirectoryWeb (3.18) |
| `animal-writes/badges` | `hub-blocks.php:34` | `state` (`block-md`) | Synced list patterns 8934, 8936, 8938, 8940, 8944, 8946; Home 57; campaigns 8, 13848, 23202, 23225 | Badges → patterns and bindings (DirectoryWeb) (see note 2) |
| `animal-writes/contact` | `hub-blocks.php:35` | none | Synced pattern 8944 | Listing sections → patterns with Block Bindings (DirectoryWeb) |
| `animal-writes/org-actions` | `hub-blocks.php:36` | none | Synced pattern 8944 | Core blocks with Block Bindings in the card pattern, DirectoryWeb (3.18) |
| `animal-writes/result-head` | `hub-blocks.php:37` | none | Synced pattern 8944 | Hub toolbar → `kits/collection-filter` |
| `animal-writes/browse-states` | `hub-blocks.php:38` | none | Hub page 8945 (Directory) | Core Categories / Terms block on the places taxonomy (3.18) |
| `animal-writes/posted-by` | `hub-blocks.php:39` | none | None in content | Core blocks with Block Bindings in the card pattern (3.18) |
| `animal-writes/volunteer` | `hub-blocks.php:40` | `open` (bool) | Page 8965 `volunteer` | Fluent Forms block, form #5 (3.18) |
| `animal-writes/listing-help` | `hub-blocks.php:41` | none | Theme files `single-aw_listing.html` | Pattern, DirectoryWeb (3.18) |
| `animal-writes/org-details` | `hub-blocks.php:42` | none | None found | Core blocks with Block Bindings in the card pattern, DirectoryWeb (3.18) |
| `animal-writes/item-meta` | `hub-blocks.php:44` | none | Theme files `single.html`, `single-aw_listing.html` | Item meta → core post blocks with Block Bindings |
| `animal-writes/item-sources` | `hub-blocks.php:45` | none | Theme files `single.html`, `single-aw_listing.html` | Source row → core post blocks with Block Bindings |
| `animal-writes/item-tags` | `hub-blocks.php:46` | none | Theme files `single.html` | Tags → core post blocks |
| `animal-writes/item-share` | `hub-blocks.php:47` | none | Theme files `single.html`, `single-aw_listing.html` | `kits/share` |
| `animal-writes/item-more` | `hub-blocks.php:48` | none | Theme files `single.html`, `single-aw_listing.html` | Query Loop variation (3.18) |
| `animal-writes/current-campaign` | `hub-blocks.php:49` | none | Theme files `parts/sidebar.html` | Pattern, edited by hand (3.18) |
| `animal-writes/listing-about` | `hub-blocks.php:51` | none | Theme files `single-aw_listing.html` | Listing sections → patterns with Block Bindings |
| `animal-writes/listing-registrations` | `hub-blocks.php:52` | none | same | Listing sections |
| `animal-writes/listing-contact` | `hub-blocks.php:53` | none | same | Listing sections |
| `animal-writes/listing-location` | `hub-blocks.php:54` | none | same | Listing sections |
| `animal-writes/listing-services` | `hub-blocks.php:55` | none | same | Listing sections |
| `animal-writes/listing-organisation` | `hub-blocks.php:56` | none | same | Listing sections |
| `animal-writes/listing-profiles` | `hub-blocks.php:57` | none | same | Listing sections |
| `animal-writes/listing-support` | `hub-blocks.php:58` | none | same | Listing sections |
| `animal-writes/listing-photos` | `hub-blocks.php:59` | none | same | Listing sections |
| `animal-writes/listing-activity` | `hub-blocks.php:60` | none | same | Listing activity → Query Loop variation |
| `animal-writes/listing-press` | `hub-blocks.php:61` | none | same | Press → Query Loop variation |
| `animal-writes/listing-opportunities` | `hub-blocks.php:62` | none | same | Opportunities → Query Loop variation |
| `animal-writes/listing-campaigns` | `hub-blocks.php:63` | none | same | Query Loop variation, DirectoryWeb (3.18) |
| `animal-writes/claim-cta` | `hub-blocks.php:64` | none | Theme files `single-aw_listing.html` | Claims → Fluent Forms block plus a pattern |
| `animal-writes/item-faq` | `aw-seo-extras/includes/faq.php:769` (filter) | none | Theme files `front-page.html`, `single.html`, `single-aw_listing.html` | FAQ → core Details blocks; RankWeb adds the schema |
| `animal-writes/home-hero` | `animal-writes-core/includes/home.php:132` (filter) | none | Theme files `front-page.html` | Pattern, edited by hand (3.18) |
| `animal-writes/home-content` | `home.php:167` (filter) | none | Theme files `patterns/aw-content.php` | Pattern, edited by hand (3.18) |
| `animal-writes/home-sc` | `home.php:214` (filter) | none | Theme files `patterns/aw-sc.php` | Pattern, edited by hand (3.18) |
| `animal-writes/term-faq` | `animal-writes-core/includes/term-page.php:29` (filter) | none | Theme files `category.html`, `tag.html` | FAQ → core Details blocks; RankWeb adds the schema |
| `animal-writes/term-share` | `term-page.php:30` (filter) | none | Theme files `category.html`, `tag.html` | `kits/share` |
| `animal-writes/term-related` | `term-page.php:31` (filter) | none | Theme files `category.html`, `tag.html` | Query Loop variation (3.18) |
| `animal-writes/social-posts` | `animal-writes-core/includes/social-posts.php:926` (filter) | none | Campaign 13848 | Query Loop variation, SourceWeb (3.18) |

Notes on the rows (facts only; the decision stays with the owner):
1. `animal-writes/section-head` is the whole head band: breadcrumb, H1, intro, count and filters. The section-head pattern covers the heading part; the filters go to `kits/collection-filter`; the breadcrumb goes to Rank Math's Breadcrumbs block (3.18).
2. `animal-writes/badges` prints chips on every card (News, Resources, Library, Opportunities, Press and campaign cards), not only on Directory cards. The Badges row names DirectoryWeb.

Rows marked (3.18) were Unmapped in the first draft and have an answer since 11 Nov (section 3.18).

### 1c. Other front-end output that the rows touch

| What | Where | Row |
|---|---|---|
| Share bar added at the end of `<main>` on single items | `kits-core/includes/share.php:94` (`render_block_core/group`); HTML from `kits_share_html()` at `share.php:69` | `kits/share` |
| Hub toolbar: Filters, Sort, chips, count | `aw_list_filters()` `animal-writes-core/includes/systems.php:319`, filter list `AW_LIST_FILTERS` `systems.php:94`, `aw_block_result_head()` `hub-blocks.php:403`, `aw_head_below()` | `kits/collection-filter` |
| Hub list queries read the filters | `animal-writes-core/includes/hubs.php:317`, `:596`, `:607`, `:616`, `:642` | `kits/collection-filter` |
| Letter grounds: an ordered list with class `aw-letter-grounds` in the page, read by `campaign-lab.js:165` | Campaign 13848 content | `kits/letter` |
| Campaign share section (Share button plus share pictures, built by `campaign.js:248` from the page's `#share` section, classes `aw-share-block`, `aw-share-cta`, `aw-share-pics`) | Campaigns 13848, 23202, 23225 | `kits/share` with the campaign's share pictures (2.5) |
| Supreme Court tracker (table `aw-camp-tracker`, section `#tracker-sec`) | Campaign 23225 and Resource 13842 | Supreme Court tracker → a pattern |
| Block Bindings source `animal-writes/field` | `animal-writes-core/includes/bindings.php:170` | Item meta, attributes, listing sections (existing source) |
| FAQ box added after the post content | `aw-seo-extras/includes/faq.php:777` (`render_block_core/post-content`) | FAQ |

The other `render_block_*` filters (about 90, in `kits-core/includes/{sections,landmarks,attribution,page-options,site-parts}.php`, the Kits theme `functions.php` and 27 files of Animal Writes Core) restyle core blocks: post titles, dates, terms, excerpts, featured images, queries, groups. They are not shortcodes or blocks and no row names them. They stay; section 4 lists the ones a move has to keep working.

### 1d. Unmapped

None left. The first draft listed 23 items; each has an answer in section 3.18 (11 Nov 2026).

---

## 2. The 7 custom blocks

Each block lives in `plugins/kits-core/blocks/<name>/` with `block.json`, `index.js` (editor), `render.php`, and `view.js` + `view.asset.php` where it needs behaviour. `kits-core/includes/blocks.php` registers them next to `kits/hero`. Each block gets a row in the component map's Blocks table and in the registry (`kits-registry.json`, all three copies kept the same) with its states.

Common to all seven:
- `"apiVersion": 3`, `"textdomain": "kits-core"`, category `kits` (a new block category "Kits"; `kits/sponsored` keeps `kits-sponsored`).
- `supports`: `html: false`, `anchor: true`, spacing margin top and bottom; no colour pickers unless named below (colours come from tokens).
- Strings in the render are translatable with `__()`; attributes that hold text default to today's words, so moved content looks the same.
- WCAG 2.2 AA throughout: targets at least 24 by 24 px (the site uses 44 for buttons and icon buttons), visible focus (the theme's focus ring), no meaning carried by colour alone, text contrast 4.5:1, nothing that moves on its own, `prefers-reduced-motion` respected.

### 2.1 `kits/letter`

**Replaces** `[aw_letter_tool]` (`campaign-tools.php:292`) and the letter code in `campaign-lab.js` and `campaign.js`.

**Attributes** (`block.json`)

| Attribute | Type | Default | From the shortcode |
|---|---|---|---|
| `to` | string (email) | `""` | `to` |
| `subject` | string | `""` | `subject` |
| `ticked` | array of integers | `[]` | `ticked` (comma list → array) |
| `salute` | string | `"Sir/Madam,"` | `salute` |
| `intro` | string | `""` | `intro` |
| `closing` | string | `""` | `closing` |
| `sign` | string | `"Yours faithfully,"` | `sign` |
| `address` | string | `""` | `address` |
| `source` | string | `""` | `source` (sign-up source for analytics; empty: the site's default) |
| `layout` | string `steps` / `page` | `steps` | Today chosen in code (`aw_camp_is_lab_page()`): the Lab page shows one page |
| `thanksHead` | string | today's words | Hard-coded today ("Thank you. Your objection is on its way to CCSEA.") |
| `thanksText` | string | today's words | Hard-coded today |

The grounds move inside the block: its inner blocks are one core List, each item one ground (`allowedBlocks: ["core/list"]`, `templateLock: "insert"`). This replaces the separate `aw-letter-grounds` list the script reads from the page today.

**Site data** comes through one filter, so Kits Core knows no campaign: `kits_letter_context( array $context, int $post_id )` returns `open` (bool; closed hides the block), the REST addresses for "Yes, I sent it", the anonymous letter count and the sign-up, the Fluent Forms form ID, the consent and opt-in lines, and the default `source`. Animal Writes Core answers it from today's `aw_camp_*` functions. With no answer the block still works as a plain letter tool (Gmail, email app, Copy; no count, no sign-up).

**Render**: dynamic (`render.php`). The form markup is today's, in Kits classes (`kit-field`, `kit-steps`, `kit-btn`, `kit-form-status`, `kit-form-done`) plus new `kit-letter-*` classes for the letter's own parts.

**Interactivity API** store `kits/letter`:
- state: the ticked grounds, the picked count ("3 of 14 chosen"), the letter text built from salute, intro, grounds, own line, closing, sign, name and city; the step (steps layout).
- actions: tick, next and back, send by route (`gmail` opens Gmail's compose address in a new tab, `mailto` opens the email app, `copy` copies the letter), confirm (yes or not yet), sign-up.
- Without JavaScript: all grounds show with their text, and the block shows the letter's address, subject and a plain email link with the default grounds; nothing is hidden behind a script.

**Lab rule kept**: every ground always shows. No "Show all" button, no fold, at any width (owner, 10 Nov).

**Editor controls** (sidebar): To, Subject, Salute, Intro, Closing, Sign-off, Address, Analytics source, Layout (Steps / One page), Thank-you heading and line. The grounds are edited in place as the inner list; a "Ticked at start" checkbox list in the sidebar sets `ticked`.

**Accessibility**: the grounds are a `fieldset` with a `legend`; every field has a `label` and an error line tied by `aria-describedby`; the picked count and the "Copied" message are polite live regions; a missing name, city or email shows one `role="alert"` line and moves focus to the first empty field; on Next and Back focus moves to the new step's legend; after "Yes, I sent it" focus moves to the thank-you (`tabindex="-1"`); "Send with Gmail" says it opens in a new tab (visible hint or `aria-describedby`); the letter preview is a focusable region with a name. ALTCHA stays invisible and needs no puzzle (3.3.8 Accessible Authentication).

**CSS reused**: Steps (`kit-steps`, `kit-step`), Form (`kit-form`, `kit-captcha`), Form status and done message, Buttons (`kit-btn`, `kit-btn--secondary`), Link chip for ground chips. The look now in `aw-letter` rules (`campaign.css`, `campaign-lab.css`) moves to the block's `style.css` under `kit-letter-*`; the `aw-letter` rules stay until the shortcode goes.

**Migration**
- A block transform `from` type `shortcode`, tag `aw_letter_tool`, maps every attribute (`ticked` split into numbers). Opening a page with the shortcode inside a Shortcode block offers "Transform to Letter".
- The only page is campaign 13848 (Lab), which is closed, so the tool prints nothing there today. Its content (shortcode plus the grounds list) is moved only on the owner's word; until then `[aw_letter_tool]` keeps rendering.
- A new campaign that needs a letter uses the block from the start.

### 2.2 `kits/countdown`

**Replaces** `[aw_countdown]` (`campaigns.php:29`).

**Attributes**

| Attribute | Type | Default | From the shortcode |
|---|---|---|---|
| `date` | string (YYYY-MM-DD) | `""` | `date` |
| `label` | string | `""` | `label` |
| `useContextDate` | boolean | `true` | Today: no `date` means the campaign's close date |
| `pastText` | string | `"Took place on"` | Hard-coded today |

With no `date`, the block asks `kits_countdown_date( string $date, int $post_id )`; Animal Writes Core answers with the campaign's `aw_campaign_close`, the one source the sticky bar and Home band also read. Days are counted in the site's time zone (`wp_timezone()`; Asia/Kolkata on live) instead of the hard-coded zone.

**Render**: dynamic; the same text as today ("6 days to go", "1 day to go", "Today", "Took place on", then the date). **Interactivity API** (small): on load the store recounts the days from `data-date`, so a page served from cache the next day still shows the right number. No ticking clock.

**Editor controls**: Date (date picker), Label, Use the page's own date (toggle), Past text.

**Accessibility**: the number and its unit are one sentence ("6 days to go, 17 Nov 2026"); the date is a `<time datetime>`; no live region (it changes once a day); the number is not shown by colour or size alone.

**CSS reused**: none in Kits yet. The look of `aw-countdown` / `aw-cd-*` (component map, Opportunity card row) becomes a new Kits component "Countdown" (`kit-countdown`, `kit-cd-num`, `kit-cd-unit`, `kit-cd-when`) in `kit.css`, added to the registry with its states (days left, one day, today, past). Sizes from the design-system type scale only.

**Migration**: transform from shortcode `aw_countdown`. Uses: campaign 13848 (Lab, closed), campaign 23225 (Supreme Court, Active) and the theme pattern `patterns/campaign.php` (both copies, a file change). Page content moves on the owner's word per page; the shortcode keeps rendering until then. The Home Supreme Court card counts days in `animal-writes/home-sc` and is not touched.

### 2.3 `kits/metric`

**Replaces** `[aw_campaign_count]` (`campaign-tools.php:488`), `[aw_campaign_metric]` (`campaign-record.php:83`), `[aw_campaign_impact]` (`:109`), `[aw_campaign_outcome]` (`:127`).

**Attributes**

| Attribute | Type | Default | From the shortcodes |
|---|---|---|---|
| `source` | string | `""` | Which data: `count`, `metric`, `impact`, `outcome` (the names Animal Writes registers) |
| `key` | string | `""` | `[aw_campaign_metric key]` |
| `threshold` | number | `0` (0: the data's own threshold) | `[aw_campaign_count min]`, metric `threshold` |
| `label` | string | `""` (empty: the data's own label) | `[aw_campaign_count label]` |
| `staleDays` | integer | `0` (0: the data's own) | metric `stale_days`, count `aw_campaign_metric_stale` |
| `showAsOf` | boolean | `true` | "As of" line |
| `display` | string `number` / `line` | `number` | `number` for count and metric; `line` for impact and outcome (plain text, no pill) |

**Data**: Kits Core knows no campaign. Sources register through `kits_metric_sources( array $sources )`: key => label and a callback that gets the attributes and post ID and returns `value`, `label`, `note` (attribution, source link), `as_of` (date), `stale` (bool) and `hidden` (bool, for unverified or below threshold). Animal Writes Core registers the four sources from today's code, rules unchanged: unverified metrics hide, below-threshold numbers hide, "Not applicable" impact hides, outcome shows only once closed.

**Render**: dynamic, no script. Optional later: share a store with `kits/letter` so the count goes up after "Yes, I sent it" without a reload.

**Editor controls**: Source (select from the registered sources), Key (for sources that take one), Threshold, Label, Stale after (days), Show "As of", Display (Number / Line). The editor preview says why a metric is hidden ("Hidden: not verified", "Hidden: below 100").

**Accessibility**: value and label are read as one phrase ("1,240 objections sent"); the "As of" date is a `<time>`; stale is said in words ("as of 3 Nov"), not only by a dimmer colour; a source link has a name that says where it goes.

**CSS reused**: none in Kits. The look of `aw-camp-metric` and `aw-camp-count` becomes a new Kits component "Metric" (`kit-metric`, `kit-metric-value`, `kit-metric-label`, `kit-metric-note`, state `is-stale`) added to the registry; the line display reuses the plain `aw-camp-status` look as `kit-metric--line`.

**Migration**: transforms from the four shortcodes. `[aw_campaign_count]` has no live use. `[aw_campaign_metric]`, `[aw_campaign_impact]` and `[aw_campaign_outcome]` are used only on campaign 8 (Community dogs), which the owner froze (25 Oct): that page is not edited and the shortcodes keep rendering there.

### 2.4 `kits/collection-filter`

**Replaces** `[aw_directory_filter]` (`directory.php:587`), `[aw_find_help]` (`sections.php:334`) and the hub toolbar (`animal-writes/list-filters`, `animal-writes/result-head`, `animal-writes/hub-count`, `aw_list_filters()` in `systems.php:319`).

**Attributes**

| Attribute | Type | Default | From today |
|---|---|---|---|
| `postType` | string | `""` | `list-filters` `postType` |
| `layout` | string `toolbar` / `search` / `compact` | `toolbar` | `toolbar`: hub toolbar; `search`: the Directory search form; `compact`: `[aw_find_help compact="1"]` (Home) |
| `filters` | array of strings | `[]` (empty: every filter registered for the type) | `AW_LIST_FILTERS[type]`, the Directory fields |
| `showCount` | boolean | `true` | hub count ("23 stories") |
| `showSort` | boolean | `true` | Sort in the toolbar |
| `sorts` | array of strings | `[]` (empty: the type's sorts) | today's sorts (`new`, `old`, `az`, `za`, "Most details first") |
| `action` | string | `""` (empty: this page) | `[aw_find_help]` and the Directory search post to the Directory hub |
| `quickFilters` | integer | `0` | `[aw_find_help compact]` shows the 6 biggest types |

**Filters are registered, not built in**: `kits_collection_filters( array $filters, string $post_type )` returns key => label, control (`search`, `select`, `multi`, `checkbox`), query parameter, options callback (with counts), and how it changes the query. Animal Writes Core registers the hub filters it has today; DirectoryWeb registers name or PIN, state, district, type, service, Claimed only, Government verified and Use my location. Parameter names stay as today (`q`, `state`, `district`, `tag[]`, `verified`, `gov`, `sort`, `aw_filter`), so saved links, analytics and search results keep working.

**Query**: the block works with a core Query Loop on the same page. The Query Loop reads the filters through `query_loop_block_query_vars` (today `hubs.php` does it through `pre_render_block` and `render_block_data`); the count comes from the same query.

**Render**: dynamic. Phase one is a `GET` form that works with no JavaScript (Apply button), as today. **Interactivity API** store `kits/collection-filter`: dropdowns and the phone sheet, chips that remove one filter, district options that follow the state, Use my location. Optional later: the Interactivity Router to swap results without a full reload.

**Editor controls**: Post type, Layout, Filters (checkbox list of the registered ones, in order), Show count, Show sort, Sorts, Send to (page picker), Quick filters (number). Preview shows the real filters with today's counts.

**Accessibility**: the form is `role="search"` with a name; every control has a visible label (screen-reader-only only where the design already hides it); checkboxes are grouped in a `fieldset`; the result count is a polite live region; each chip is a button named "Remove filter: Dogs"; the phone sheet is a dialog: focus moves in, Escape closes, focus returns to the Filters button; dropdowns follow the menu-button pattern; nothing is lost at 400% zoom (1.4.10 Reflow).

**CSS reused**: Filter bar (`kit-filterbar`, `kit-filters`, `kit-dd`, `kit-removable`), Collection toolbar (`kit-toolbar`, `kit-toolbar-count`, `kit-dd--sheet-only`), Filter pill and group (`kit-filter`), Section band (`kit-section-search`), Form fields (`kit-field`). The Directory's own look (`aw-dir-search`, `aw-toolbar`, `aw-sort-seg`, `aw-browse-states`) moves with DirectoryWeb. Fold below 768 as today (`data-kit-fold="767"`).

**Migration**
- Block transforms from `animal-writes/list-filters` (keeps `postType`), `animal-writes/result-head`, `animal-writes/hub-count`, and from shortcodes `aw_directory_filter` and `aw_find_help` (`compact` → `layout: compact`).
- Content to move (one-time update, each on the owner's yes): the 7 hub pages (8811, 8935, 8937, 8939, 8941, 8943, 8945), the synced Directory list pattern 8944, Home 57, and the theme pattern `patterns/find-help.php` (file change, both copies).
- The old blocks and shortcodes keep rendering until then.

### 2.5 `kits/share`

**Replaces** the share bar: the bar added at the end of `<main>` (`kits-core/includes/share.php:94`), `animal-writes/item-share` (`item-head.php:405`) and `animal-writes/term-share` (`term-page.php:54`).

**Attributes**

| Attribute | Type | Default | From today |
|---|---|---|---|
| `networks` | array of strings | `[]` (empty: the networks chosen in Theme Options for this post type) | `share_networks` in Theme Options |
| `target` | string `post` / `term` / `page` | `post` | `item-share` (post), `term-share` (category or tag page) |
| `label` | string | `"Share"` | `kit-share-label` |
| `inline` | boolean | `false` | `kit-share-inline` (in the text column, no rule above) |
| `pictures` | boolean | `false` | The campaign share section: a Share button and the page's share pictures (`campaign.js:248`, `aw-share-block`, `aw-share-cta`, `aw-share-pics`) |

**Render**: dynamic, through the existing `kits_share_html()`: X, Facebook, LinkedIn, WhatsApp, Email, Copy link, plain links, no third-party scripts. **Interactivity API** store `kits/share`: Copy link (writes the address, says "Link copied" in the status region) and the fit check that shows network names at 1200 and up only when the row fits on one line (today in `kit.js`).

**Share pictures** (`pictures` on): the block also shows a Share button (the browser's own share sheet where there is one, else Copy link) and the share pictures, in a closed core Details fold as today. The pictures come from `kits_share_pictures( array $pictures, int $post_id )` (each: image, alt text, file name); Animal Writes Core answers with the campaign's share pictures. No new block.

**Editor controls**: Networks (checkboxes in today's order; "Use Theme Options" by default), Target, Label, Inline, Share pictures.

**Accessibility**: the row is `role="group"` named "Share"; each icon link is named "Share on X", "Share by email"; Copy link is a `button`; a status region says "Link copied"; 44 px targets; links that open a new tab say so in their title; icons are `aria-hidden`.

**CSS reused**: Share (`kit-share`, `kit-share-inline`, `kit-sh`). The `data-kit-share` attribute stays, and with pictures on, the `aw-camp-share` hook too (the analytics listens to both).

**Migration**: no page content holds these. The item and term templates (`single.html`, `single-aw_listing.html`, `category.html`, `tag.html`, both copies) swap the old blocks for `kits/share` in a file change. The automatic bar in `share.php` then stops when the page already has a `kits/share` block (`has_block()`), so a page never shows two bars. Block transforms from `animal-writes/item-share` and `animal-writes/term-share` for any saved copy. The campaign share section on campaigns 13848, 23202 and 23225 moves to `kits/share` with pictures on, per page on the owner's word.

### 2.6 `kits/sticky-cta`

**Replaces** `[aw_sticky_cta]` (`campaign-tools.php:556`).

**Attributes**

| Attribute | Type | Default | From the shortcode |
|---|---|---|---|
| `href` | string | `"#send"` | `href` |
| `label` | string | `"Send your objection"` | `label` |
| `showDays` | boolean | `true` | `days` (`0` → false) |
| `preHref` | string | `"#updates"` | `pre_href` |
| `preLabel` | string | `""` | `pre_label` |

**State** comes through `kits_sticky_cta_state( array $state, int $post_id )`: `phase` (`pre`, `open`, `closed`) and `days_left`. Animal Writes Core answers from the campaign: Pre shows the `preLabel` bar when set (no day count), Open shows the action and the days, Closed shows nothing, as today.

**Render**: dynamic; printed at the end of the page. **Interactivity API**: the bar hides while its target section is on screen (an `IntersectionObserver`, as `campaign.js` does today) and shows only below 768.

**Editor controls**: Button address, Button text, Show days left, Before-opening address and text.

**Accessibility**: a `<nav>` or `<aside>` with a name ("Campaign action"); one link, 44 px high; the bar never covers the focused element (2.4.11 Focus Not Obscured): the page gets `scroll-padding-bottom` equal to the bar's height while it shows; the days line is text, not only a number; it slides only when reduced motion is off.

**CSS reused**: Sticky action bar (`kit-sticky-help`), Buttons. When a page has both the Kits helpline bar (`site-parts.php`) and this bar, only one shows (this one on campaign pages); the rule goes in the block, not in page CSS.

**Migration**: transform from shortcode `aw_sticky_cta`. Uses: campaigns 13848 (Lab, closed), 23202 (Diwali) and 23225 (Supreme Court, Active). Each page moves on the owner's word; the shortcode keeps rendering until then.

### 2.7 `kits/sponsored`

**Replaces** `animal-writes/sponsored-item` and `animal-writes/sponsored-banner` (`sponsored-blocks.php:73`, `:82`).

**Attributes** (one block, two variations: "Sponsored item" and "Sponsored banner")

| Attribute | Type | Default | From today |
|---|---|---|---|
| `kind` | string `item` / `banner` | `item` | which old block |
| `postId` | integer | `0` | sponsored-item `postId` |
| `mediaId` | integer | `0` | banner `mediaId` |
| `mediaAlt` | string | `""` | banner `mediaAlt` |
| `heading` | string | `""` | banner `heading` |
| `text` | string | `""` | banner `text` |
| `url` | string | `""` | banner `url` |
| `linkText` | string | `""` | banner `linkText` |
| `sponsor` | string | `""` | banner `sponsor` |

Which posts count as sponsored comes from `kits_is_sponsored( bool $yes, int $post_id )` (Animal Writes Core answers with today's `aw_sponsored` meta), and the editor's list of sponsored posts from `kits_sponsored_posts`. Kits Core holds no meta key name.

**Render**: dynamic, no script. Prints nothing when the post is not sponsored or not published, as today. Every link carries `rel="sponsored noopener"`; the `data-sponsored-*` attributes stay (analytics). A page with the block carries no ads (Advanced Ads display condition, today's rule; Advanced Ads is off on live).

**Editor controls**: item: a post picker limited to sponsored posts; banner: image, alt text, heading, text, link, link text, sponsor name.

**Accessibility**: "Sponsored" is visible text (the badge), and "Sponsored by {name}" when named; the image's alt text is set in the editor or empty when the heading already says it; the heading is the link when there is no button text, so there is one link, not two.

**CSS reused**: Badge (`kit-badge`, sponsored state), Banner (`kit-banner`), Card (`kit-card--block-md`), the Kits theme pattern `sponsored-label.php`.

**Migration**: no live use. Block transforms from both old blocks for any future copy; the old blocks stay registered, hidden from the inserter.

---

## 3. Native replacements

One entry each. "Owner" is who builds and keeps it.

### 3.1 Section heads → a pattern (Kits theme)
- **What**: `kits/section-head`, a pattern: Group (Section style) with a Heading (H2), one Paragraph and an optional "See all" link.
- **Replaces**: `[aw_section_head]` (no live use) and the heading part of `animal-writes/section-head`.
- **Migration**: nothing in content. The templates keep `animal-writes/section-head` until the breadcrumb (Rank Math, 3.18) and the filters (`kits/collection-filter`) have their own homes; then the templates take the pattern plus those.

### 3.2 Categories list, index, hub → core Categories / Terms blocks, or a Query Loop variation (Kits theme)
- **List** (`[aw_categories_list]`, footer links style): core Categories block (list, no counts) or core Terms Query if WordPress 7.1 has it. No live use.
- **Index** (`[aw_categories_index]`, page 8759): core Terms Query showing each category's name, description and count; if that block is missing in 7.1, core Categories plus a Kits theme style.
- **Hub** (`[aw_category_hub]`, `templates/category.html`): four Query Loops (Resources, News, Library, Campaigns, six each), a Kits theme variation `kits/term-group` that reads the current category and the toolbar's sort through `query_loop_block_query_vars`.
- **Migration**: page 8759 content (owner's yes); `category.html` file change (both copies).

### 3.3 `[aw_related]`, `[aw_campaign_partners]` → Query Loop variations (Kits Core)
- **What**: `kits/related` and `kits/partners`, variations of `core/query` (`namespace` set, `isActive: ['namespace']`). On the server `query_loop_block_query_vars` fills `post__in` from `kits_related_ids( array, int $post_id )` and `kits_partner_ids( array, int $post_id )`; Animal Writes Core answers with `aw_related_groups()` and `aw_campaign_partner_ids()`. Inner blocks: the Kits card. An empty list prints nothing, heading included (as today).
- **Migration**: `[aw_related]` has no live use. `[aw_campaign_partners]` is on campaigns 13848, 23202 and 23225: moved per page on the owner's word.

### 3.4 `[aw_search]` → core Search block (Kits theme): not now
- **Answer (11 Nov)**: `/search/?q=` stays as it is. The search term reaches GA4 through Tag Manager, so moving to core search (`?s=`) is a GA4 and GTM change, which needs the owner's OK. Revisit with InsightWeb.
- **Later**: core Search block on the search page; results by the Kits theme `search.html` template (Query Loop, one loop per post type), with a redirect from the old address.
- **Migration**: none now. Page 8761 keeps `[aw_search]`.

### 3.5 `[aw_elsewhere]` → core Social Icons block, reading Theme Options → Socials (Kits theme)
- **What**: a core Social Icons block whose links come from Theme Options → Socials. Preferred: a Block Bindings source `kits/socials` bound to each Social Icon's `url` (needs WordPress 7.1 to allow bindings on `core/social-link`; checked before the build). Fallback: a `render_block_core/social-links` filter that fills the links from Theme Options. Empty entries print nothing.
- **Migration**: page 61 (Contact) content, on the owner's yes. Footer and schema `sameAs` already read the same accounts and are not touched.

### 3.6 Source row, item meta, tags, title, category, attributes → core post blocks with Block Bindings (Kits theme)
- **What**: core Post Title, Post Terms (category, tags, place), Post Date; Paragraph, Image and Button bound to a source `kits/post-field` (Kits theme) with keys registered by `kits_post_fields` (Animal Writes answers: source name, source address, source logo, place, deadline, law date, read time). The existing `animal-writes/field` source stays for content that already uses it.
- **Replaces**: `[aw_source_row]`, `animal-writes/item-meta`, `item-sources`, `item-tags`, `read-meta`, `law-date`, `place`.
- **Migration**: templates `single.html`, `single-aw_listing.html` (file change); `read-meta` in the five synced list patterns (8934, 8936, 8938, 8940, 8946), Home 57 and campaigns 13848, 23202, 23225 (content, owner's yes; campaign 8 stays frozen). The `render_block_core/post-date`, `post-terms` and `post-title` filters in `display.php`, `tags.php`, `breadcrumbs.php` and `pages.php` keep shaping these blocks.

### 3.7 Contact, newsletter, report and volunteer-update forms → Fluent Forms block (no owner named)
All four already are Fluent Forms forms: the shortcodes only wrap them through `aw_ff_form()`. Entries stay in Fluent Forms (owner, 21 and 31 Oct: one Contact form, #4). The move is a swap for the Fluent Forms block.
- **Newsletter** (`[aw_newsletter]`): synced pattern 12752 and `parts/newsletter.html` (both copies).
- **Contact** (`[aw_contact_form]`, `pages.php:98`, form #4): page 61.
- **Report** (`[aw_report_box]`, `[aw_report_page]`, `reports.php:94`): the form gets the item and reason from the address; a pre-fill hook keeps that.
- **Volunteer update** (`[aw_volunteer_update]`, page 19270): a pre-fill hook reads the link's token and fills the form from the FluentCRM contact, and shows today's message when the link is invalid.
- Wording on the forms does not change in this move; any wording change needs the owner's yes.

### 3.8 `[aw_not_found]`, `[aw_thank_you]` → template or page content (Kits theme / content)
- **404** (`templates/404.html`, both copies): only what core blocks do: heading, line, core Search block, and a pattern of popular links (a Query Loop). The extras `[aw_not_found]` prints today (removed-item notice, section guess, suggested search from the address) go, with no custom code (answer, 11 Nov).
- **Thank-you** (page 12589): a normal page. Fluent Forms redirects to it; the newsletter form on it is the Fluent Forms block. `[aw_thank_you]`'s per-form heading goes. Page content change on the owner's yes; the page address stays, so analytics that counts it is not touched.

### 3.9 Supreme Court tracker → a pattern (content)
- **What**: the tracker table (`aw-camp-tracker`, `#tracker-sec`) becomes a synced pattern "Supreme Court tracker", so campaign 23225 and Resource 13842 show one copy, edited once.
- **Migration**: content, on the owner's yes (the campaign is Active).

### 3.10 `[aw_rule_chip]` → a text format (Kits Core)
- **What**: `kits/note`, a rich-text format (`registerFormatType`, applied like bold or link) on a `span` with `data-kit-ref` (rule or annexure), `data-kit-doc` (document ID) and `data-kit-allows`. On the front end Kits Core turns each span into a `button` and a native `dialog` (built with the HTML API in a `render_block` filter); the clause text comes from `kits_note_text( string $text, string $ref, int $doc )`, answered by Animal Writes Core with today's `aw_camp_clause()`. No text found: the label shows as a plain tag, as today.
- **Accessibility**: `aria-haspopup="dialog"`, the dialog is labelled by its heading, Escape and Close shut it and focus returns to the chip.
- **Migration**: only campaign 13848 (Lab, closed); on the owner's word. The shortcode keeps rendering.

### 3.11 FAQ → core Details blocks; RankWeb adds the FAQ schema (RankWeb)
- **What**: questions are core Details blocks inside a Group with the Kits FAQ style; RankWeb builds the `FAQPage` schema from those Details blocks.
- **Today**: RankWeb stores each page's FAQ (approved and automatic questions) and prints it through `animal-writes/item-faq`, `animal-writes/term-faq` and a `render_block_core/post-content` filter (`faq.php:777`).
- **Migration**, two steps: first RankWeb prints its stored FAQ as core Details markup (one look, same schema); then hand-written FAQs move into page content as Details blocks. Automatic FAQs stay generated by RankWeb, with its FAQ schema (answer, 11 Nov). RankWeb's own FAQ and schema stay (no Rank Math FAQ blocks).

### 3.12 Listing sections → patterns with Block Bindings (DirectoryWeb)
- **What**: one pattern per section (about, registrations, contact, location, services, organisation, online profiles, support, photos), bound to a DirectoryWeb source `kits/listing`.
- **List fields** (services, registrations and the like): the DirectoryWeb binding source returns the list as text (answer, 11 Nov), so WordPress's own Bindings API does it with no block.
- **Migration**: `templates/single-aw_listing.html` (file change).

### 3.13 `org-people`, listing activity, press and opportunities → Query Loop variations (DirectoryWeb)
- **What**: `kits/listing-activity`, `kits/listing-press`, `kits/listing-opportunities`, variations of `core/query` that select items linked to the current listing.
- **`[aw_org_people]`** lists FluentCRM contacts (volunteers who chose to show their name), not posts, so a Query Loop cannot reach them. It becomes the one DirectoryWeb block, `directoryweb/people` (answer, 11 Nov): dynamic, no script, no attributes; it prints the listing's shown volunteers (a claimant with their role) and nothing when there are none, and keeps the schema `member` output (`aw_org_people_schema()`). Classes as today's `aw-org-people` look, moved to DirectoryWeb; heading an H2 with the list as a `ul`.
- **Migration**: `templates/single-aw_listing.html` (file change).

### 3.14 Claims → Fluent Forms block with a pre-fill hook, plus a pattern (DirectoryWeb)
- **Claim page** (`[aw_claim_page]`, page 12587): with a listing in the address, the Fluent Forms claim form with the listing pre-filled; without one, today's "find your organisation" search, which `kits/collection-filter` (search layout, sent to the claim page) can show.
- **Claim box** (`animal-writes/claim-cta`): a pattern (heading, line, button) whose link is bound to the listing's claim address.
- **Migration**: page 12587 content and the listing template, on the owner's yes.

### 3.15 Badges, "Featured in", government records → patterns and bindings (DirectoryWeb)
- **What**: Claimed and Verified badges, "Featured in" and government records as patterns of core blocks bound to `kits/listing`.
- **Note**: `animal-writes/badges` also prints chips on non-Directory cards (note 2 in section 1b).

### 3.16 Campaign state → nothing in Kits (stays on live as it is)
`[aw_camp_status]` and the campaign classes stay exactly as they are on campaigns 8, 13848, 23202 and 23225. No block, no change.

### 3.17 `[kits_demo]` → stays the admin demo page (Kits Core)
No change. The demo page gets a section for each new block's states.

### 3.18 The items that were Unmapped (answers of 11 Nov 2026)

| Item | Becomes | Owner | Migration |
|---|---|---|---|
| `[aw_campaign_band]` (Home 57), `animal-writes/home-hero`, `home-content`, `home-sc` | Patterns, edited by hand when a campaign changes; the countdown inside is `kits/countdown` | Content (Kits theme for the patterns) | Home 57 content and `front-page.html`, `patterns/aw-content.php`, `patterns/aw-sc.php` (both copies), on the owner's yes |
| `animal-writes/current-campaign` | Pattern, edited by hand | Content | `parts/sidebar.html` (both copies) |
| `[aw_banner]`, `animal-writes/hub-visual` | The Kits Banner pattern (8 Oct decision), or core Cover / Image | Kits theme | `hub-visual` on the 7 hub pages; `[aw_banner]` has no live use |
| `[aw_copy_needed]` | Removed; core block notes do that job | — | No live use; the shortcode goes in the phase that touches `pages.php` |
| `[aw_testimonials]` | Removed (no page uses it; checked live 11 Nov) | — | No live use; the empty placeholder in `admin-ia.php:63` goes |
| `animal-writes/breadcrumb` | Rank Math's Breadcrumbs block; Rank Math stays the master for breadcrumbs | Kits theme (templates) | 7 hub pages and the item templates; see Risks (trail markup, `.kit-crumbs`) |
| `item-more`, `term-related` | Query Loop variations (`kits/item-more`, `kits/term-related`) | Kits Core, with Animal Writes answering which items | Item and term templates (file change) |
| `listing-campaigns` | Query Loop variation | DirectoryWeb | Listing template |
| `social-posts` | Query Loop variation | SourceWeb | Campaign 13848, on the owner's word |
| Card parts: `opp-actions`, `posted-by` | Core blocks with Block Bindings in the card pattern (Apply link, author) | Kits theme | Synced pattern 8940 |
| Card parts: `org-mark`, `org-actions`, `org-details` | Core blocks with Block Bindings in the card pattern (logo, actions, details) | DirectoryWeb | Synced pattern 8944 |
| `browse-states` | Core Categories / Terms block on the places taxonomy | Kits theme (DirectoryWeb for the places taxonomy) | Directory hub page 8945 |
| `volunteer` (sign-up form) | Fluent Forms block, form #5 | — | Volunteer page 8965 |
| `listing-help` | Pattern | DirectoryWeb | Listing template |
| Campaign share section | `kits/share` with share pictures on (2.5); no new block | Kits Core | Campaigns 13848, 23202, 23225 |

---

## 4. Phases, sizes and risks

Sizes: S about a day, M two to three days, L a week or more. Each phase is its own branch and pull request into `wordpress-dev` and follows the review route for bigger technical work (CLAUDE.md, rule 3). Deploys go one at a time, never during a FluentCRM send. Each live content change is its own step after the code is live, on the owner's yes.

| Phase | Work | Size | Live content touched |
|---|---|---|---|
| 0 | This spec and its answers (below); owner review | — | none |
| 1 | `kits/share`, `kits/countdown`, `kits/sponsored`: blocks, transforms, registry and component-map rows, demo states; share in the item and term templates | M | none (template files only) |
| 2 | `kits/sticky-cta`, `kits/metric`, `kits/note` format; Animal Writes Core answers their filters | M | none until the owner picks pages |
| 3 | Query Loop variations `kits/related`, `kits/partners`; binding source `kits/post-field`; item templates on core post blocks | M | five synced list patterns; Home; three campaigns (each on a yes) |
| 4 | `kits/collection-filter` with the hub filters; hub pages and Home | L | 7 hub pages, pattern 8944, Home 57 |
| 5 | `kits/letter` (when a campaign needs a letter) | L | a new campaign; Lab 13848 only on the owner's word |
| 6 | Native items owned by the Kits theme and content: categories, socials, 404, thank-you, Supreme Court tracker, Banner, breadcrumbs, Home and sidebar campaign patterns, card parts | M | pages 8759, 61, 12589, 23225, 13842, 57, the 7 hub pages, patterns 8940 |
| 7 | Forms as the Fluent Forms block (newsletter first, then contact, volunteer sign-up, volunteer update, report) | S | pattern 12752, pages 61, 8965, 19270 |
| 8 | RankWeb FAQ as Details | M | none at first |
| 9 | DirectoryWeb: listing sections, listing Query Loops, `directoryweb/people`, claims, badges, Directory card parts, its filters for `kits/collection-filter` | L | listing template, page 12587, pattern 8944 |

Old shortcodes and blocks are removed only after a later check finds no use left on live, and only on the owner's word.

### Risks
- **DirectoryWeb does not exist yet.** The Directory code lives in Animal Writes Core. Phase 9 waits for that plugin, or its parts go in Animal Writes Core first under `kits` names and move later.
- **Frozen and closed pages.** Campaign 8 (Community dogs) is frozen and the Lab (13848) is closed; their shortcodes must keep rendering for good, so the old code cannot be deleted while those pages hold it.
- **Active campaign.** Supreme Court (23225) is Active; any edit there shows at once to visitors. Edits only on the owner's yes, never during a send.
- **Analytics hooks.** `aw-analytics.js` listens to `data-kit-share`, `.kit-share`, `.aw-camp-share`, `data-campaign-slug`, `data-sponsored-*`, `.aw-toolbar-count`, `.kit-filter-sheet`, `.aw-dir-more`, `.aw-related`, `.kit-crumbs` and others. New blocks keep these or the listener moves in the same pull request; GA4 and GTM are not changed.
- **Cached pages.** Days left and counts can go stale in the page cache; countdown and sticky bar recount in the browser; the deploy purge stays as it is.
- **Breadcrumbs.** Rank Math's trail has its own markup and may differ from today's (`kit-crumbs`, which the analytics listens to, and the trail's order on item pages). The move checks the trail page by page and moves the listener in the same change; the `render_block_core/post-title` and `post-terms` filters in `breadcrumbs.php` that place today's trail go with it.
- **Patterns edited by hand.** Home and sidebar campaign patterns no longer follow the campaign's state on their own (open, closed, Pre); each campaign change is a hand edit.
- **Lists as text.** A list field returned as text loses its list markup; the binding source returns it in one fixed format so it reads the same on every listing.
- **Block Bindings on Social Icons** may not be allowed in WordPress 7.1; the fallback filter is in 3.5.
- **Two theme copies.** Templates and patterns live in `themes/animal-writes/` and `plugins/animal-writes-core/theme/`; every file change goes to both, and `aw.css` stays the same in both.
- **Many render filters** shape core blocks (about 90). A template that swaps an old block for core blocks must be checked against them (titles, dates, terms, featured images).

### Answers of 11 Nov 2026 (were the open questions)
1. Unmapped items: each gets a row (3.18).
2. Search: not now; `/search/?q=` stays (3.4).
3. 404: core blocks only, a Search block and a pattern of popular links (3.8).
4. Thank-you: a normal page; Fluent Forms redirects to it (3.8).
5. Contact and report: already Fluent Forms with entries there (owner, 21 and 31 Oct) (3.7).
6. List fields: a DirectoryWeb binding source that returns text; `[aw_org_people]`: the block `directoryweb/people` (3.12, 3.13).
7. Automatic FAQs: kept, generated by RankWeb with the schema (3.11).

That makes 7 Kits blocks plus 1 DirectoryWeb block. Everything else is core blocks, patterns, Query Loop, Block Bindings, Rank Math's breadcrumbs or Fluent Forms.

### Stays untouched
- Campaign state (`[aw_camp_status]`, campaign classes) on live, as it is.
- Campaign 8 (Community dogs) content.
- `kits/hero`.
- SourceWeb, MailWeb, PlanWeb and InsightWeb: no front-end blocks.
- The `render_block_*` filters that restyle core blocks.
- Advanced Ads (off), GA4 and GTM.
- The `aw` to `kits` rename of existing code (deferred).
- Site search at `/search/?q=` (3.4).
