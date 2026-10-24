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
| `claims.php` | Claim or update a directory listing: [aw_claim_box] on each listing. |
| `content-types.php` | Post types and taxonomies. Each pillar keeps its own URL space for SEO. |
| `cron.php` | Daily housekeeping: expire featured listings, flag listings due for re-verification. |
| `data-sources.php` | Where an organisation's details come from: one small label per data source on every listing. |
| `directory.php` | Directory: /directory/, /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/, featured first. |
| `display.php` | Small front-end display tweaks. |
| `form-blocks.php` | Form blocks and pages (S6, S7, 8 Oct 2026). |
| `hub-blocks.php` | Small blocks for what core blocks cannot show (Phase 12 Part D, D4). Each is a registered, server-rendered block (no shortcodes), editable in the block editor (preview via ServerSideRender), used in the hub Pages and their list patterns. Inside a Query Loop they read the item from the block context (postId). |
| `hubs.php` | Hub Pages (Phase 12 Part D): each section hub is a Page that the owner edits (breadcrumb, H1, intro, banner, body) with its list as a synced pattern of core blocks (Query Loop + Post Template + pagination + empty state). |
| `indexing.php` | What search engines index (restructure Phase 4). |
| `issuers.php` | Issuer: who issued a Library document ("Issued by Supreme Court"). A label on the item, not a list, filter or page (S1.1, 8 Oct 2026). Stored on the item as meta aw_issuer (a slug from the issuer names below). The former list (taxonomy aw_source_type, with courts merged in) was copied into the meta once and is no longer registered; a backup of its links is in uploads/aw-private/issuers-backup-2026-10-08.json. Old /source/{slug}/ URLs 301 to the Library (places.php). |
| `kits-config.php` | Animal Writes settings for Kits Core (kits_* filters). |
| `media.php` | Card images: the article's own preview image (linked, not copied) or a placard-style fallback. |
| `meta.php` | Post meta. Registered natively (no ACF) and exposed to the block editor + REST. |
| `model.php` | Content model, restructure Phase 2: legal sub-type, court, primary topic, topic → listing types for "Find help", and relations between items (each one shown on both sides in WP Admin). |
| `newsletter.php` | Newsletter: sign-up with email confirmation and the sign-up list (WP Admin > Audience > Newsletter, CSV export). Sending happens in an external mailing service, not in WordPress (S2.4). |
| `pages.php` | Restructure Phase 6: the parts the new pages are built from. The pages themselves hold structure only; the owner writes their text (tools/restructure/phase6.php creates them). |
| `places-admin.php` | Place box on each item that can have a place (places.php): PIN code → State and District filled from India Post, matched to the LGD file; State → District dropdowns built from the file when there is no PIN or India Post is down. There is no place list in WP Admin and no way to type a place that is not in LGD. Codes that are not in the file are not saved (a notice says so) and the item keeps its previous place. |
| `places.php` | Places from the Local Government Directory (LGD), not from a WordPress list (8 Oct 2026). |
| `press.php` | Media & PR (Phase 11 I): coverage of Animal Writes as admin-editable items (aw_press), shown on /media/ as cards. |
| `redirects.php` | Permanent redirects for items that moved section or were merged (restructure Phase 3). |
| `reports.php` | "Report an error" on every directory listing: [aw_report_box]. |
| `retention.php` | Retention: nothing is ever deleted. Old items are archived instead, and stay reachable at their URL. |
| `schema.php` | Structured data (JSON-LD). |
| `sections.php` | Section header for archives: [aw_section_head]. Title, one-line description, live count and a three-image visual built from real content in that section. |
| `site-check.php` | "Check site" (admin bar, administrators): loads one page of every kind, as the deploy check does, and lists each with its status and time. A page fails on anything but 200, a PHP error or WordPress's "critical error" page. Results show on Tools → Check site. Nothing is changed. |
| `sitemap.php` | Sitemap (WordPress core /wp-sitemap.xml), tuned for Animal Writes: - no users list and no default blog posts or categories (unused); - a "directory" sitemap lists the place pages (/directory/{state}/, /directory/{state}/{district}/) that have listings,   and district × listing-type pages with at least 3 listings (indexing.php). Places come from the LGD file (places.php). Retention rules (news age, closed opportunities) live in retention.php. |
| `sources-config.php` | The sourcing lists (option aw_sources, seeded from data/sources-seed.json). Stays in core because the front end reads the section categories and tags from them; Settings → Sourcing edits them (aw-sourcing). |
| `summary-rules.php` | The summary rules (option aw_summary_rules over data/summary-rules-default.json), the helpers the site uses (auto-review limits, search description, entities stored per item as aw_entities) and the whole summary on an item's own page. The workers' endpoint and the Settings tab are in aw-sourcing. |
| `systems.php` | Shared list helpers, the list filters and the trust panel. |
| `topic-images.php` | Category photos, set in WP Admin on each category screen (Topics, Listing types, Opportunity types): add or edit a category's name, slug, write-up (Description) and photo (pick or upload from the media library). Topic photos are also used on news and Legal Watch items in that topic. News and Legal Watch items show their topic's photo (see aw_item_image() in media.php). Stored as term meta `aw_topic_image` (attachment ID). The general photos for items without a topic stay in the option aw_topic_images['default']. |
| `unverified.php` | Unverified values on a listing (meta aw_unverified) and the small mark the listing template shows after them. |
| `volunteers-db.php` | Volunteers database: one table for 1.0 campaign volunteers and 2.0 sign-ups. |
| `volunteers.php` | Volunteer sign-up form (shared form kit): name, email, phone, PIN code → State and District (LGD), how you'd like to help, consent. Entries go to FluentCRM only (contact tagged Volunteer, details and the page they came from in the Volunteer fields); since 24 Oct 2026 FluentCRM is the one volunteers table. |

## aw-sourcing (AW Sourcing)

Moved out of animal-writes-core and Kits Core on 24 Oct 2026 with no change in behaviour: same option keys, REST routes (`aw/v1`), cron hooks, admin pages, actions and filters. Core loads it from this folder (guard `AW_SOURCING_LOADED`); it can also be active on its own. Without Core it loads nothing and shows a notice. Its data files stay in core (`data/certs`, `data/sources-seed.json`, `data/summary-rules-default.json`).

| File | What it does |
|---|---|
| `aw-sourcing.php` | Loader only (no functions): the include order sets the Settings → Sourcing tab order. |
| `includes/sourcing-policy.php` | Settings → Sourcing (`options-general.php?page=aw-sourcing`): Publishers (allow / neutral / block, short names), Keywords (Include, Animal, Context, Exclude in option aw_relevance), Change log; the gate (aw_sourcing_gate), aw_has_term, GET sourcing-policy for the workers, the Publisher column and "Block host" row action. From Kits Core. |
| `includes/sourcing-lists.php` | Tabs News feeds, Library sources, Opportunity sources, Press, Categories, Sections and the "Workers use these lists" switch (option aw_sources). |
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
| `includes/review-queue.php` | Inbox → To review. |
| `includes/ai-keys.php` | Settings → AI models: keys, models, usage, twice-daily check (aw_ai_check_all). |
| `includes/relay.php` | GET relay: the fetch proxy for hosts that refuse the workers, and the Relay tab. |
| `includes/workers.php` | Settings → Sourcing → Workers (24 Oct 2026): the 17 workflows of the workers repository (AW_WORKERS, a copy of `docs/workers.json` there; change both together), POST worker-run (start and end reports), problems (late = no report for twice the interval; failed, cancelled, skipped, stuck), the hourly watchdog email (aw_workers_watchdog, once per new set), the notice, the rows in the dashboard box Scheduled tasks, the Site Health test and Run now (aw_dispatch_workflow, logged in aw_worker_dispatch_log). Also shows the AI models tiles. |

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
| `seo-fields.php` | Editable SEO fields, no SEO plugin needed. |
| `seo.php` | Meta description, Open Graph and Twitter card tags. Skipped automatically if a dedicated SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO) is active. |
| `share.php` | Share buttons: plain links (no third-party scripts, no tracking) plus copy-link. |
| `sourcing.php` | Sourcing basics: the sourced post types, aw_host(), aw_publishers() and the option constants (AW_PUB_OPTION, AW_REL_OPTION, AW_SRC_CAP). The policy and its screen moved to aw-sourcing on 24 Oct 2026. |
| `visits.php` | Honest visitor counter: one count per browser session, bots excluded, nothing personal stored. Counting runs in the browser (so cached pages still count). Shown in the footer. |

Removed on 8 Oct 2026 (dead): `testimonials.php` (1.0 testimonials, 0 items, exported), `launch.php` (one-time server setup and media restore, both finished), `media-pr.php` (retired settings screens). Their two shortcodes still in old Home content print nothing (`admin-ia.php`).
