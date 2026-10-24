# Plugin map

Each file in the plugins and what it does. animal-writes-core, Kits Core and AW Sourcing load from `animal-writes-core/animal-writes-core.php` (Kits Core is required first, AW Sourcing last; either may also be active on its own and is then not loaded twice); Mailweb (`mailweb`) and `aw-seo-extras` are separate plugins. Forms: see `how-to-change-a-form.md`.

## animal-writes-core

| File | What it does |
|---|---|
| `admin-ia.php` | WP Admin information architecture (Phase 11 J). Nothing is deleted: menus move, labels match the site. |
| `also-reported.php` | "Also reported by": publisher logos in an item's source row (meta aw_also_reported, written by the same-story fold in aw-sourcing). |
| `bindings.php` | Block Bindings source "animal-writes/field" so templates can show meta with core blocks. |
| `breadcrumbs.php` | Breadcrumbs (restructure Phase 5): one trail per page, shown above the page title and given to search engines as BreadcrumbList (schema.php). |
| `campaigns.php` | Campaign kit: countdown, and the private upload folder used by listing claims. (The proof-of-posting receipts and live counts were removed on 8 Oct 2026; they had no entries.) |
| `cdn-cache.php` | Hostinger CDN (hcdn) lifetime for anonymous public pages: `Cache-Control: public, s-maxage=N` only on anonymous 200 GETs of public pages, never on forms, nonce or personal pages. Option aw_cdn_ttl (0 = off, 60 to 900 s). |
| `claims.php` | Claim or update a directory listing: [aw_claim_box] on each listing. |
| `content-types.php` | Post types and taxonomies. Each pillar keeps its own URL space for SEO. |
| `cron.php` | Daily housekeeping: expire featured listings, flag listings due for re-verification. |
| `data-sources.php` | Where an organisation's details come from: one small label per data source on every listing. |
| `deploy-purge.php` | Purges LiteSpeed once after each deploy: the first uncached web request that sees a new deployed commit purges and stores it (option aw_deployed_head); the Deploy check requests the home page with ?aw_deploy_nudge= for 5 minutes after each deploy so that request happens. Skips command-line PHP and requests whose headers are already sent. |
| `directory.php` | Directory: /directory/, /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/, featured first. |
| `display.php` | Small front-end display tweaks. |
| `form-blocks.php` | Form blocks and pages (S6, S7, 8 Oct 2026). |
| `hub-blocks.php` | Small blocks for what core blocks cannot show (Phase 12 Part D, D4). Each is a registered, server-rendered block (no shortcodes), editable in the block editor (preview via ServerSideRender), used in the hub Pages and their list patterns. Inside a Query Loop they read the item from the block context (postId). |
| `hubs.php` | Hub Pages (Phase 12 Part D): each section hub is a Page that the owner edits (breadcrumb, H1, intro, banner, body) with its list as a synced pattern of core blocks (Query Loop + Post Template + pagination + empty state). |
| `issuers.php` | Issuer: who issued a Library document ("Issued by Supreme Court"). A label on the item, not a list, filter or page (S1.1, 8 Oct 2026). Stored on the item as meta aw_issuer (a slug from the issuer names below). The former list (taxonomy aw_source_type, with courts merged in) was copied into the meta once and is no longer registered; a backup of its links is in uploads/aw-private/issuers-backup-2026-10-08.json. Old /source/{slug}/ URLs 301 to the Library (places.php). |
| `kits-config.php` | Animal Writes settings for Kits Core (kits_* filters). |
| `media.php` | Card images: the article's own preview image (linked, not copied) or a placard-style fallback. |
| `meta.php` | Post meta. Registered natively (no ACF) and exposed to the block editor + REST. |
| `model.php` | Content model, restructure Phase 2: legal sub-type, court, primary topic, topic → listing types for "Find help", and relations between items (each one shown on both sides in WP Admin). |
| `newsletter.php` | Newsletter: the old sign-up list (sign-ups now go through Fluent Forms, `newsletter-fluentforms.php`, into the FluentCRM list "Newsletter"); sign-up with email confirmation and the sign-up list (WP Admin > Audience > Newsletter, CSV export). Sending happens in an external mailing service, not in WordPress (S2.4). |
| `pages.php` | Restructure Phase 6: the parts the new pages are built from. The pages themselves hold structure only; the owner writes their text (tools/restructure/phase6.php creates them). |
| `places-admin.php` | Place box on each item that can have a place (places.php): PIN code → State and District filled from India Post, matched to the LGD file; State → District dropdowns built from the file when there is no PIN or India Post is down. There is no place list in WP Admin and no way to type a place that is not in LGD. Codes that are not in the file are not saved (a notice says so) and the item keeps its previous place. |
| `places.php` | Places from the Local Government Directory (LGD), not from a WordPress list (8 Oct 2026). |
| `press.php` | Media & PR (Phase 11 I): coverage of Animal Writes as admin-editable items (aw_press), shown on /media/ as cards. |
| `reports.php` | "Report an error" on every directory listing: [aw_report_box]. |
| `retention.php` | Retention: nothing is ever deleted. Old items are archived instead, and stay reachable at their URL. |
| `sections.php` | Section header for archives: [aw_section_head]. Title, one-line description, live count and a three-image visual built from real content in that section. |
| `site-check.php` | "Check site" (admin bar, administrators): loads one page of every kind, as the deploy check does, and lists each with its status and time. A page fails on anything but 200, a PHP error or WordPress's "critical error" page. Results show on Tools → Check site. Nothing is changed. |
| `sources-config.php` | The sourcing lists (option aw_sources, seeded from data/sources-seed.json). Stays in core because the front end reads the section categories and tags from them; Settings → Sourcing edits them (aw-sourcing). |
| `summary-rules.php` | The summary rules (option aw_summary_rules over data/summary-rules-default.json), the helpers the site uses (auto-review limits, search description, entities stored per item as aw_entities) and the whole summary on an item's own page. The workers' endpoint and the Settings tab are in aw-sourcing. |
| `systems.php` | Shared list helpers, the list filters and the trust panel. |
| `category-images.php` | Category photos, set in WP Admin on each category screen (Topics, Listing types, Opportunity types): add or edit a category's name, slug, write-up (Description) and photo (pick or upload from the media library). Topic photos are also used on news and Legal Watch items in that topic. News and Legal Watch items show their topic's photo (see aw_item_image() in media.php). Stored as term meta `aw_topic_image` (attachment ID). The general photos for items without a topic stay in the option aw_topic_images['default']. |
| `unverified.php` | Unverified values on a listing (meta aw_unverified) and the small mark the listing template shows after them. |
| `volunteers-db.php` | Volunteers database: one table for 1.0 campaign volunteers and 2.0 sign-ups. |
| `volunteers.php` | Volunteer sign-up form (shared form kit): name, email, phone, PIN code → State and District (LGD), how you'd like to help, consent. Entries go to FluentCRM only (contact tagged Volunteer, details and the page they came from in the Volunteer fields); since 24 Oct 2026 FluentCRM is the one volunteers table. |
| `backups.php` | Daily backup (S0): the database and the deployed code, once a day, into ~/backups on the server (outside the web root), kept 14 days. |
| `block-states.php` | Animal Writes blocks in their states, on the states sheet (/kits-demo/, administrators only): each block rendered from a real item that is in that state, so the sheet shows what visitors see. |
| `campaign-form.php` | Campaign form (owner, 21 Oct 2026): "Sign it" in the campaign letter tool is the Fluent Forms form "Campaign form" (forms/10-campaign-form.json; its ID in the option aw_ff_campaign_form). |
| `campaign-letters.php` | Campaign letters: anonymous statistics and the opt-in at send (owner, 17 Oct 2026; Privacy Policy section 7, "Campaign actions and sign-ups"). |
| `campaign-links.php` | Campaign link (owner, 20 Oct 2026: a campaign "shouldn't be a tag"). |
| `campaign-record.php` | Campaign record, the parts campaign-tools.php does not hold (Campaign Content Standard v1.0, Notion, approved 17 Oct 2026): metric records for a campaign's own figures, the Real-world impact module, and the editor-only b |
| `campaign-tools.php` | Campaign tools for an objection or letter campaign (first used by Campaign 2.0, the draft lab animal rules 2026). |
| `category-moves.php` | Categories moved to WordPress's own category (owner, 15 Oct 2026). |
| `cc0-images.php` | Ingested items (News, Library, research) whose publisher image is missing or won't load get a free CC0 / public domain photo from Openverse that matches their category (owner, 12 Oct 2026). |
| `cron-health.php` | Scheduled-task (WP-Cron) health (owner, 20 Oct 2026). |
| `directory-map.php` | Directory map and "Near me" (owner, 20 Oct 2026: built natively instead of Web Directory Free). |
| `district-helplines.php` | District helplines (owner, 20 Oct 2026: "fill phone numbers first from official state lists, then build the helpline blocks on the district pages"). |
| `entity-links.php` | Names linked in a summary (SEO Phase 7, internal linking). |
| `featured.php` | "Featured in" (owner, 18 Oct 2026): the news, Library items, resources, press coverage and campaigns an organisation appears in, linked from its Directory page by title, and both ways in the schema (the organisation's su |
| `find-help.php` | "Find help near you" (owner-approved plan, 24 Oct 2026; revised by the owner the same day): links from the pages Google crawls every day (News, Library, guides and the lab rules campaign) into the Directory's state and d |
| `fluentcrm.php` | FluentCRM (owner, 16 Oct 2026): one list, "Newsletter"; volunteers are newsletter subscribers. |
| `forms-fluentforms.php` | The site's forms in Fluent Forms (free) and FluentCRM (free), built with their own features (owner, 21 Oct 2026). |
| `geocode.php` | Listing coordinates for the Directory map and "Near me" (owner, 20 Oct 2026: OpenStreetMap's free geocoder, Nominatim, one request a second, cached; PIN code or district centre as fallback, marked approximate). |
| `item-head.php` | The item page (owner, 20 Oct 2026): one structure for News, Library (sourced and our own documents), Resources (research and guides) and Opportunities, from one template (single.html); Directory listings use rows 1–4. |
| `listing-profile.php` | Organisation profile (directory listings): the details box in wp-admin and the block that shows them. |
| `listing-template.php` | The Directory profile (owner, 20 Oct 2026): single-aw_listing.html, built from the item template's shared blocks (breadcrumb, meta row, Sources, Share, ad slots, More) and these groups, each printed only when it has data |
| `local-cards.php` | Local cards (owner copy, 9 Oct 2026): - (The News item card "Organisations in {State}" was removed with the one item template, owner 20 Oct 2026: an item page ends with at most two "More" groups.) - Directory district pa |
| `mailweb-aw.php` | Animal Writes' side of Mailweb (owner, 20 Oct 2026: Mailweb is a product; what only Animal Writes uses lives here). |
| `map-picker.php` | Map picker on the listing edit screen (wp-admin only): an OpenStreetMap map beside the Latitude and Longitude fields. |
| `near-opps.php` | Home "Opportunities near you" (owner, 16 Oct 2026): the list shows opportunities in the visitor's state. |
| `newsletter-optin.php` | Newsletter growth, consent only (owner, 9 Oct 2026; DPDP: consent per purpose). |
| `org-people.php` | Volunteers of an organisation, from FluentCRM (owner, 21 Oct 2026; was the site's volunteers table). |
| `privacy-requests.php` | DPDP "Remove my data": [aw_remove_data] on /remove-my-data/ (owner, 21 Oct 2026: collect only). |
| `related.php` | Related items (SEO Phase 7, internal linking): [aw_related] at the end of an item page. |
| `research-citation.php` | Research papers' credit (owner, 20 Oct 2026): "Study: <study title> by <authors> in <journal>" in the item's Sources block, and the ScholarlyArticle it is based on in its schema. |
| `scf.php` | Secure Custom Fields (SCF): the editing screens for the meta that meta.php registers. |
| `sponsored-blocks.php` | Sponsored blocks (inserter group "Sponsored", owner 12 Oct 2026). |
| `staging.php` | Staging site (staging.animalwrites.in): the same code and a copy of the database without personal data. |
| `tags.php` | Labels are WordPress's own Tags (owner, 15 Oct 2026): Library types, Resource types, Opportunity types, Listing types and Services are tags (post_tag) on every section, at /tag/{slug}/, filtered on the hubs by ?tag=. |
| `team-dates.php` | Dates (owner, 21 Oct 2026): the team's own calendar inside WordPress. |
| `term-page.php` | Category and tag pages (owner, 20 Oct 2026): one archive layout for every term. |
| `thumbs.php` | Thumbnails never repeat (owner, 9 Oct 2026) — for content WE create. |

## aw-sourcing (AW Sourcing)

Moved out of animal-writes-core and Kits Core on 24 Oct 2026 with no change in behaviour: same option keys, REST routes (`aw/v1`), cron hooks, admin pages, actions and filters. Core loads it from this folder (guard `AW_SOURCING_LOADED`); it can also be active on its own. Without Core it loads nothing and shows a notice. Its data files stay in core (`data/certs`, `data/sources-seed.json`, `data/summary-rules-default.json`).

| File | What it does |
|---|---|
| `aw-sourcing.php` | Loader only (no functions): the include order sets the Settings → Sourcing tab order. |
| `includes/sourcing-policy.php` | Settings → Sourcing (`options-general.php?page=aw-sourcing`): Publishers (allow / neutral / block, short names), Keywords (Include, Animal, Context, Exclude in option aw_relevance), Change log; the gate (aw_sourcing_gate), aw_has_term, GET sourcing-policy for the workers, the Publisher column and "Block host" row action. From Kits Core. |
| `includes/class-aw-sourcing-publishers-table.php` | Settings → Sourcing → Publishers as a core list table: views by status, search, sortable columns, pagination (screen option, 50 a page), row actions Allow / Neutral / Block, bulk status, short names edited in place. Posts to the same save handler (aw_sourcing, tab publishers), which checks the screen's aw_sourcing nonce; the table prints none of its own. |
| `includes/sourcing-lists.php` | Tabs News feeds, Library sources, Opportunity sources, Press, Categories, Sections and the "Workers use these lists" switch (option aw_sources). |
| `includes/class-aw-sourcing-sources-table.php` | News feeds, Library sources and Opportunity sources as a core list table (views On / Off, search, sortable, row and bulk actions: turn on/off, keyword check on/off); each source is edited or added on its own form-table screen (&edit=<id> or &edit=new). |
| `includes/summary-rules-admin.php` | GET summary-rules for the workers and the Summary rules tab. |
| `includes/relevance.php` | AI relevance verdicts, the Rejected tab (aw_relevance_rejected), the ingest heartbeat and hourly watchdog (aw_ingest_watchdog). |
| `includes/ingest.php` | POST ingest: the workers' items arrive here (News, Library, research, opportunities, listings, press drafts). |
| `includes/opportunities-site.php` | Opportunity sources the site fetches itself (not scheduled at present). |
| `includes/auto-review.php` | Automatic review of ingested News and Library items: routine ones go live, doubtful ones wait in Inbox → To review. |
| `includes/same-story.php` | Same event, one story: GET/POST same-story for the workers' folds and merges. |
| `includes/summary-rewrite.php` | The workers' summary rewrite pass (summary-rewrite endpoint). |
| `includes/news-titles.php` | `wp aw news-titles`: sentence-case news titles. |
| `includes/enrichment.php` | Directory enrichment: the queue and results endpoints, table {prefix}aw_enrich, apply rules, spend. |
| `includes/enrichment-admin.php` | Directory → Enrichment review and the Enrichment tab. |
| `includes/enrichment-front.php` | The live Google block on listing pages (listing-live endpoint). |
| `includes/review-queue.php`, `includes/class-aw-sourcing-review-table.php` | Inbox → To review as a core list table: views by type, search, sortable, pagination (screen option, 50), row actions Edit / Source / Publish / Reject, bulk Publish / Reject and "Publish all N". |
| `includes/keyword-suggestions.php`, `includes/class-aw-sourcing-suggestions-table.php` | Settings → Sourcing → Keywords → Suggestions from Search Insights: searches with at least 20 impressions in 28 days that no Include or Exclude word covers (not brand, English only), as a core list table with Add to Include, Add to Exclude and Dismiss (option aw_sourcing_dismissed). Needs aw-seo-extras for the searches; shows nothing without it. |
| `includes/ai-keys.php` | Settings → AI models: keys, models, usage, twice-daily check (aw_ai_check_all). |
| `includes/class-aw-sourcing-ai-keys-table.php` | Settings → AI models → Keys as a core list table: saved keys in the workers' order, then the GitHub-secret keys; views Working / Waiting / Broken / GitHub secrets, search, row actions Move up / Move down / Remove and bulk Check again / Remove (admin_post_aw_ai_keys_list), the plugins-list problem row under a broken key. |
| `includes/site-health.php` | Tools → Site Health → "Sourcing ran on time": the ingest heartbeat as a Site Health test, same rules as the watchdog (critical: no run for 8 hours; recommended: no News for 12 hours, none ever 12 hours after the workers' first report, or half the sources failing). |
| `includes/relay.php` | GET relay: the fetch proxy for hosts that refuse the workers, and the Relay tab. |
| `includes/keyword-stats.php` | Which search brought each item in (meta aw_feed, aw_ingested_at, saved at ingest since 24 Oct 2026) and per keyword the items fetched and published in 30 days, News and Library apart (older items: by the word in title or summary, estimated). Offered to aw-seo-extras through the filter aw_sourcing_keyword_items. |
| `includes/search-insights-tab.php`, `includes/class-aw-sourcing-si-keywords-table.php` | Tools → Search Insights → Sourcing keywords (filter aw_si_tabs): per Google News search, items fetched and published in 30 days (News, Library) with their pages' 28-day clicks and impressions from aw_si_page_metrics(); and the searches for the Keywords tab's suggestions (filter aw_search_demand_terms) from Search Insights' keywords table, English only. Nothing shows without aw-seo-extras. |
| `includes/workers.php` | Settings → Sourcing → Workers (24 Oct 2026): the workflows of the workers repository (AW_WORKERS in `includes/workers-registry.php`, an exact copy of `docs/workers.json` there; the Deploy check fails when they differ, `tools/ci/workers_registry_check.php`; when both change, merge the workers PR first), POST worker-run (start and end reports), problems (late = no report within the workflow's own limit (`late_after_h`: Sourcing, Sources sync, Summaries 12 h; Focus keywords, SEO scores, Site backup, Directory enrichment 36 h; Research 9 days; Keepalive 35 days; WordPress cron, Tests and by-hand runs never; 18 workflows); failed, cancelled, skipped, stuck), the hourly watchdog email (aw_workers_watchdog, once per new set), the notice, the rows in the dashboard box Scheduled tasks, the Site Health test and Run now (aw_dispatch_workflow, logged in aw_worker_dispatch_log). Also shows the AI models tiles. |

## mailweb (Mailweb)

A standalone product since 20 Oct 2026 (was `aw-mail`): nothing Animal Writes-only inside; Animal Writes' parts are in `animal-writes-core/includes/mailweb-aw.php`. Prefix `mailweb_`, text domain `mailweb`, webhook `POST /wp-json/mailweb/v1/mail-in` (the old `/wp-json/aw/v1/mail-in` still answers, from core).

| File | What it does |
|---|---|
| `mailweb.php` | Loader, plugin header, text domain, 5-minute schedule; deactivation clears its cron jobs. |
| `includes/crypto.php` | Secrets saved on the settings screen (API token, webhook secrets, SMTP password) are encrypted (AES-256-GCM, key from the site's salts). wp-config / environment values are never stored. |
| `includes/mail-in.php` | Settings (`mailweb_setting`: environment, then wp-config `MAILWEB_*`, then the encrypted option), Mail API helpers, mailbox IDs from the token, the webhook (per-mailbox secrets; refreshes counts, fires `mailweb_webhook_noted`), Mail → Settings → Incoming mail. |
| `includes/mailboxes.php` | Mailboxes from the token only; per mailbox "send from it" and the default From (options `mailweb_mailboxes`, `mailweb_default_from`); the alias map. |
| `includes/inbox.php` | The Mail inbox: mailboxes and folders, list and search, read (sandboxed), Reply / Reply all / Forward / New via the Mail API, Archive / Move / Spam / Trash / unread, attachments (10 MB in total, uploads checked before reading). Website forms folder through the `mailweb_forms_source` filter. |
| `includes/list-table.php` | The Mail list as a WordPress list table (`Mailweb_List_Table`). |
| `includes/profiles.php` | Per-mailbox display name, signature, picture; site emails laid out with the signature; `mailweb_after_signature` filter for a block under it; header `X-Mailweb-No-Signature: 1` skips it. |
| `includes/smtp.php` | The site's SMTP mailbox (option `mailweb_smtp`, password encrypted or `MAILWEB_SMTP_PASSWORD`), filters `mailweb_smtp_defaults`, `mailweb_smtp_reply_to`, test email. |
| `includes/mail-cap.php` | Daily limit and queue (table `{prefix}mailweb_queue`): priorities (`X-Mailweb-Priority`), retries, pause on refusal, FluentCRM pacing. |
| `includes/crm-sync.php` | FluentCRM feed (option `mailweb_crm`: on, tag, mailboxes): incoming email noted on the sender's contact (made "transactional"), replies noted; every 5 minutes and on webhook; `wp mailweb crm-sync --all`. |
| `includes/settings-tab.php` | Mail → Settings tabs: Incoming mail (token, secrets, mailboxes, aliases, profiles, FluentCRM) and Site sending (SMTP, daily limit). |
| `includes/overview.php` | Unread badge, cached counts, the Mail dashboard widget; hides FluentCRM's FluentSMTP card. |
| `languages/`, `composer.json` | Translation template (`mailweb.pot`), coding-standard and make-pot scripts. |
| `uninstall.php` | Deletes nothing. |

Animal Writes' side (`animal-writes-core/includes/mailweb-aw.php`, with `mail-campaign-strip.php`): `MAILWEB_API_TOKEN` from wp-config `AW_HOSTINGER_MAIL_TOKEN`; the campaign countdown strip under signatures; Website forms = Contact form messages (`aw_message`); Inbox → Contact messages redirects there; the old webhook address; the Check site line; the one-time move from aw-mail (options copied, secrets encrypted, table renamed, old options kept) with Animal Writes' choices (send from support@ only, contact@ → vedanshu@, FluentCRM for vedanshu@ and support@, tag "Emailed us").

## kits-core

| File | What it does |
|---|---|
| `altcha.php` | ALTCHA check for every aw_form_open() form: a free proof-of-work CAPTCHA, no third-party service, no cookies. |
| `analytics.php` | Google Tag Manager. The container ID and production hosts come from the site (filters kits_gtm_id, kits_analytics_hosts). |
| `attribution.php` | News attribution, Google News pattern. |
| `demo.php` | [kits_demo]: every Kits primitive in every state, on the master palette and on the site's own palette side by side (Phase 11 B.3). Used on the private, noindex page /kits-demo/ (administrators only). |
| `form-kit.php` | Form kit (S5): the field dictionary (one definition per field, used by every form), clean-up helpers, the source tag on every entry, a fresh form key for cached pages, and two patterns: the expandable form (S5.6) and the multi-step form (S5.7). Field markup, the spam guard and messages live in forms.php; behaviour in kit.js. |
| `forms.php` | Shared form component (S5): the field dictionary, field markup, spam guard, server-side per-field errors, the expandable and multi-step patterns, and one set of messages for every form. |
| `primitives.php` | Kits primitives (Phase 11 B.2): markup helpers so every page uses the same component for the same job. Styles: themes/kits-by-wugweb/assets/css/kit-primitives.css. Behaviour (sheet, show more): kit.js. |
| `share.php` | Share buttons: plain links (no third-party scripts, no tracking) plus copy-link. |
| `sourcing.php` | Sourcing basics: the sourced post types, aw_host(), aw_publishers() and the option constants (AW_PUB_OPTION, AW_REL_OPTION, AW_SRC_CAP). The policy and its screen moved to aw-sourcing on 24 Oct 2026. |
| `visits.php` | Honest visitor counter: one count per browser session, bots excluded, nothing personal stored. Counting runs in the browser (so cached pages still count). Shown in the footer. |
| `image-context.php` | imageContext: an editor-facing note on the copy an image supports (Media Library). |
| `landmarks.php` | Canvas landmarks (shell spec): every page has one <main id="main" tabindex="-1" aria-label="{page title}"> that the skip link targets (WordPress's own block-theme skip link uses the main's id), a sticky <header> and <foo |
| `performance.php` | Page speed on the front end (Core Web Vitals): - jQuery (and jQuery Migrate) print at the end of the page instead of in the head, so they no longer hold back the first paint. |
| `preview.php` | Preview: things only the site owner should see before they go public (sample items). |
| `slugs.php` | Every published item has a slug: wp_publish_post() and direct status changes do not make one, and an item without a slug is left out of lists, search and the sitemap. |
| `system-screen.php` | Appearance → Kits system: a read-only index of the design system, generated from kits-registry.json in the Kits master theme and in the site's child theme. |

Removed on 8 Oct 2026 (dead): `testimonials.php` (1.0 testimonials, 0 items, exported), `launch.php` (one-time server setup and media restore, both finished), `media-pr.php` (retired settings screens). Their two shortcodes still in old Home content print nothing (`admin-ia.php`).

## aw-seo-extras: Search Insights (phase 1, 24 Oct 2026; phase 1 leftovers 25 Oct)

Plan: `docs/seo/search-insights-plan.md`. Tools → Search Insights (`tools.php?page=aw-search-insights`). Read-only data and suggestions: nothing here writes to Rank Math keyphrases or the Sourcing lists. Tables `wp_aw_si_keywords`, `wp_aw_si_kw_pages`, `wp_aw_si_obs`, `wp_aw_si_rising` (kept on deactivate and delete; only Settings → Delete data or `wp aw-si delete-data --yes` drops them, and that also switches Search Insights off until the owner runs the daily update by hand).

| File | What it does |
|---|---|
| `includes/search-insights.php` | Loads the files below. |
| `includes/search-insights/schema.php` | The four tables (dbDelta, version option `aw_si_db`) and the owner-only drop. |
| `includes/search-insights/runs.php` | Run log per source (`aw_si_runs`): started, last good run, last error, rows; "late" = more than twice its schedule (counted from install for a source not run yet); a run started over an hour ago with no result "did not finish". |
| `includes/search-insights/sitekit.php` | Runs a Site Kit module as that module's own owner (`ownerID` in its settings), never another module's. |
| `includes/search-insights/keywords.php` | Normalising (plurals, dots, punctuation), language (en / hi / hinglish), roles, the one-time seed from `data/search-insights-seed.json`, the daily role sync from Rank Math (items, terms; listings only mark entities) and AW Sourcing (Include list), and `aw_si_sync_topics()`: each keyword's topic is a site category (from a linked category page, else the owning post's primary category, else that of the post Google shows most). |
| `includes/search-insights/gsc.php` | Search Console by calendar day: one call per day and dimension set (total, query, query + page, page; through `aw_sd_query()`); `aw_si_page_metrics( $paths, $days )` gives clicks, impressions and position per page path from the page rows (hidden searches included), the last 4 days again each morning, a backfill to 90 days of up to 15 days per run within a 50-second budget (its position saved after each day), a day whose query + page rows hit 5,000 is fetched again page by page for its 50 busiest pages (Site Kit passes no `startRow`; `aw_sd_query()` takes a page URL), and stays flagged "truncated" only if pages are left over or one page alone hits 5,000. |
| `includes/search-insights/compute.php` | 28-day figures from daily rows, previous 28 days, latest live check, owning page, conflicts, priority (keyword-map rule), status and one suggested action: fix conflict; a gap's page type (FAQ answer for a question, news explainer, district or state page for a place, else guide); update the page or add as secondary (quick wins); check page quality (page flagged by Clarity or PageSpeed, phase 2: obs `flag` rows or filter `aw_si_quality_pages`); FAQ answer or news explainer for searches with no page; consider as Sourcing keyword (news searches our /news/ items show for; English only). `aw_si_entity_movers()`: the 20 listing names that moved most. |
| `includes/search-insights/rollup.php` | Daily rows older than 90 days become monthly rows (sums, impression-weighted position, last value for indexes). |
| `includes/search-insights/cron.php` | `aw_si_daily` 07:10 IST and `aw_si_rollup` 23:30 IST. |
| `includes/search-insights/health.php` | Site Health test "Search Insights sources fresh". |
| `includes/search-insights/admin.php` | The screen: Overview (postboxes), Keywords, Searches (`aw_sd_screen( true )`; the old `aw-search-demand` address 301s to this tab), tabs from other plugins (filter `aw_si_tabs`, actions `aw_si_load_tab_{key}` and `aw_si_tab_{key}`), Settings (run now, delete data). |
| `includes/search-insights/class-aw-si-keywords-table.php` | Keywords list table: views, filters (role, topic, page type, language), search, sorting, priority overrides. Listing names stay out of every view but Entities: the All view shows them as one combined row plus the 20 that moved most. |
| `includes/search-insights/searches.php` | What the Searches tab (the Search demand screen) reads from the keyword table: its targets ("Our target?") and Gaps. The hard-coded 15 Oct map (`aw_sd_static()`) is gone. |
| `includes/search-insights/dryrun.php` | `aw_si_dry_report()`: builds the keyword table in memory (seed, Rank Math, Sourcing, Search Console for one day and 28 days) with the daily rule (`aw_si_decide()`), and reports rows per table and source and what each tab would show. Writes nothing. |
| `includes/search-insights/cli.php` | `wp aw-si status | dry-run | run | rollup | delete-data`. `dry-run` writes nothing. |
| `data/search-insights-seed.json` | The seed, built once by `tools/search-insights/build_seed.py` from `docs/seo/keyword-map.md` and `docs/seo/rankings-2026-10-21.csv`. |

## aw-seo-extras (all SEO code, with Rank Math; FAQ system; Search Insights)

| File | What it does |
|---|---|
| `analysis.php` | Rank Math's content analysis on what visitors see. |
| `canonical.php` | Canonical URLs Rank Math cannot work out (og:url follows the canonical): - section hub Pages and Directory places: the Page path with /page/N/, without filters; - filtered or sorted lists (?tag=, ?state=, ?category_name= |
| `faq.php` | FAQ system (owner-approved spec, 20 Oct 2026). |
| `filtered-views.php` | Filtered hub lists (owner approved, 15 Oct 2026): /news/?category_name=wildlife, /library/?tag=rule … - One filter with one value, ?category_name= or ?tag=, on a section hub (not a Directory place) is a page of its own w |
| `focus-keywords.php` | Focus keywords (Rank Math's rank_math_focus_keyword). |
| `health.php` | Sitemap and llms.txt watchdog (14 Oct 2026: both answered 404 until rewrite rules were flushed). |
| `helpers.php` | Shared helpers: text cutting, page numbers, a page's own intro, the site's naming rules, and share images. |
| `llms.php` | llms.txt (SEO Phase 5, AI visibility, 15 Oct 2026). |
| `opengraph.php` | Open Graph / Twitter details Rank Math's settings cannot express. |
| `programmatic.php` | Pages built from live data (SEO Phase 4, 15 Oct 2026): the Directory's state, district, district × type and filtered lists (?tag=, ?state=), the 18 category pages and the tag pages. |
| `rank-math-schema.php` | Rank Math's own schema entries for Pages, Library items, Resources, campaigns, News, Opportunities and Directory listings, so its screens show the schema the page really has. |
| `redirects.php` | Redirects. |
| `robots.php` | Robots rules that depend on an item's state or a list's contents, which Rank Math's per-type and per-item settings cannot express. |
| `schema.php` | The site's own JSON-LD nodes, put into Rank Math's one graph (Rank Math's schema templates cannot build these from the site's fields): - Listings: NGO / VeterinaryCare / AnimalShelter / GovernmentOffice (by listing type, |
| `search-demand.php` | Tools → Search demand (15 Oct 2026): what people search and how Animal Writes shows up for it. |
| `sitemap.php` | Rank Math sitemap (/sitemap_index.xml) additions: - a "directory" sitemap (directory-sitemap.xml) of the Directory place pages (/directory/{state}/, /directory/{state}/{district}/) that have listings, and district × list |
| `titles.php` | Search titles and meta descriptions. |
