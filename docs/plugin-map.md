# Plugin map

Each file in the plugins and what it does. animal-writes-core and Kits Core load from `animal-writes-core/animal-writes-core.php` (Kits Core is required from there); Wug Mail (`aw-mail`) and `aw-seo-extras` are separate plugins. Forms: see `how-to-change-a-form.md`.

## animal-writes-core

| File | What it does |
|---|---|
| `admin-ia.php` | WP Admin information architecture (Phase 11 J). Nothing is deleted: menus move, labels match the site. |
| `auto-review.php` | Automatic review of ingested news and Legal Watch items (owner's decision, 7 Oct 2026: routine items that pass the checks go live; only doubtful ones wait for a person). |
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
| `ingest.php` | Ingest endpoint for the sourcing workers. |
| `issuers.php` | Issuer: who issued a Library document ("Issued by Supreme Court"). A label on the item, not a list, filter or page (S1.1, 8 Oct 2026). Stored on the item as meta aw_issuer (a slug from the issuer names below). The former list (taxonomy aw_source_type, with courts merged in) was copied into the meta once and is no longer registered; a backup of its links is in uploads/aw-private/issuers-backup-2026-10-08.json. Old /source/{slug}/ URLs 301 to the Library (places.php). |
| `kits-config.php` | Animal Writes settings for Kits Core (kits_* filters). |
| `media.php` | Card images: the article's own preview image (linked, not copied) or a placard-style fallback. |
| `meta.php` | Post meta. Registered natively (no ACF) and exposed to the block editor + REST. |
| `model.php` | Content model, restructure Phase 2: legal sub-type, court, primary topic, topic → listing types for "Find help", and relations between items (each one shown on both sides in WP Admin). |
| `newsletter.php` | Newsletter: sign-up with email confirmation and the sign-up list (WP Admin > Audience > Newsletter, CSV export). Sending happens in an external mailing service, not in WordPress (S2.4). |
| `opportunities-site.php` | Opportunity sources the site fetches itself, once a day. Used where a source blocks the GitHub worker's servers (Wildlife SOS sits behind Cloudflare, which refuses GitHub Actions but answers this host). |
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
| `sourcing-lists.php` | Settings → Sourcing, the one place for what the workers fetch (S3, 8 Oct 2026). Kits Core gives the screen with Publishers (whose articles are accepted), Keywords (the one word list) and the Change log; this adds News feeds (RSS and Google News queries), Library sources (AWBI, PIB, Gazette, courts), Opportunity sources, Press-discovery queries and Topics (keyword → topic). |
| `systems.php` | Shared list helpers, the list filters and the trust panel. |
| `topic-images.php` | Category photos, set in WP Admin on each category screen (Topics, Listing types, Opportunity types): add or edit a category's name, slug, write-up (Description) and photo (pick or upload from the media library). Topic photos are also used on news and Legal Watch items in that topic. News and Legal Watch items show their topic's photo (see aw_item_image() in media.php). Stored as term meta `aw_topic_image` (attachment ID). The general photos for items without a topic stay in the option aw_topic_images['default']. |
| `volunteers-db.php` | Volunteers database: one table for 1.0 campaign volunteers and 2.0 sign-ups. |
| `volunteers.php` | Volunteer sign-up form (shared form kit): name, email, phone, PIN code → State and District (LGD), how you'd like to help, consent. Entries go to the volunteers table (Inbox → Volunteer sign-ups) with the page they came from. |

## aw-mail (Wug Mail)

Separate plugin since 18 Oct 2026; the mail code moved here from animal-writes-core with the same webhook URL, options, post type (`aw_message`) and meta keys. Sending: the SMTP mailbox (`smtp.php`, from Kits Core on 19 Oct 2026) and the daily cap and queue (`mail-cap.php`, from core on 18 Oct 2026) are here. Both show on Mail → Settings → Site sending. No mail client: replies are written in Hostinger webmail.

| File | What it does |
|---|---|
| `aw-mail.php` | Loader. Loads the moved files only when core no longer defines them, so the webhook answers throughout the switch. |
| `includes/mail-in.php` | Webhook `POST /wp-json/aw/v1/mail-in` (per-mailbox secrets; since 18 Oct 2026 it only refreshes counts, no copies), Settings → Incoming mail (secrets, API token, mailbox IDs), the one-time import and the 5-minute poll. Each email becomes an Inbox → Contact messages item and a FluentCRM note. API token: wp-config `AW_HOSTINGER_MAIL_TOKEN` first. |
| `includes/smtp.php` | The site's SMTP mailbox: server, port, mailbox and password (option kits_mail or wp-config KITS_MAIL_PASSWORD), sender name, default Reply-To (filter kits_mail_reply_to), test email. Moved from Kits Core 19 Oct 2026; names kept. |
| `includes/mail-cap.php` | Site-wide daily email cap and queue (moved from core 18 Oct 2026): budget per 24 hours, priorities, retries, pause on refusal, FluentCRM pacing. |
| `includes/mailboxes.php` | Every mailbox the Mail API token sees (vedanshu@, support@, noreply@) and the alias map (contact@ → vedanshu@; the API does not list aliases, so it is kept in Settings → Incoming mail). Only vedanshu@ and support@ are filed into the Inbox. |
| `includes/campaign-strip.php` | Countdown strip for the open campaign (days left on a deep tab, title and closing date in the campaign colour, white button) added under the signature of Mail replies and signed site emails; follows aw_campaign_open/close, none when no campaign is open. |
| `includes/profiles.php` | Per-mailbox display name, signature and picture (copied from Hostinger webmail; no API). Shown on the Mail page; site emails from that address use the name and end with the signature (not FluentCRM; not with `X-AW-No-Signature`). |
| `includes/inbox.php` | The Mail inbox (18 Oct 2026, owner: real inbox in WP): mailboxes and folders, list and search, read (sandboxed, remote pictures off), Reply / Reply all / Forward / New message via the Mail API (threads kept, copy in Sent, signature added), Archive / Move / Spam / Delete to Trash / unread, attachments; plus the Website forms folder (contact form messages). Admins and editors; 30 sends an hour; nothing logged but send times. |
| `includes/list-table.php` | The Mail list as a WordPress list table: folder links, mailbox picker (All mailboxes mixes them newest first, with a Mailbox column), checkboxes and bulk actions (read, unread, archive, spam, trash, back to Inbox), row actions, search, pagination. |
| `includes/settings-tab.php` | Mail → Settings: Incoming mail and site sending (SMTP, daily limit) in one place; the old Settings pages redirect there; Inbox → Contact messages redirects to Mail → Website forms. |
| `includes/overview.php` | Unread badge, cached counts and the Mail dashboard widget (the menu now opens the inbox); formerly the Mail menu (admins) with the unread badge across every mailbox, the last 10 messages per mailbox (no bodies) with "Open in webmail", and the Mail dashboard widget (last 5). Cached 5 minutes; refreshed when an email arrives. |
| `includes/mail-reply.php` | The former Inbox → Reply. Kept, not loaded (replies happen in webmail). |
| `uninstall.php` | Deletes nothing. |

## kits-core

| File | What it does |
|---|---|
| `ads.php` | Ad slots. Admins paste network code (e.g. AdSense) in Settings > Ads; empty slots print nothing. Slots: homepage (between sections), article (after the story), archive (after the 6th card). Also serves /ads.txt from the same settings page. |
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
| `sourcing.php` | Sourcing policy managed in WP Admin (News → Sourcing): publishers and relevance keywords. |
| `visits.php` | Honest visitor counter: one count per browser session, bots excluded, nothing personal stored. Counting runs in the browser (so cached pages still count). Shown in the footer. |

Removed on 8 Oct 2026 (dead): `testimonials.php` (1.0 testimonials, 0 items, exported), `launch.php` (one-time server setup and media restore, both finished), `media-pr.php` (retired settings screens). Their two shortcodes still in old Home content print nothing (`admin-ia.php`).
