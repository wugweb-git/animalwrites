# GA4 and GTM setup (Animal Writes, 15 Oct 2026; Directory, money, campaigns and sign-ups added 17 Oct 2026)

## How events reach GA4 (once)

| Piece | State on the live site (checked 15 Oct 2026) |
|---|---|
| Site Kit Tag Manager | on: places GTM-TB3FCFQB |
| Site Kit Analytics snippet | off (`useSnippet` false): Site Kit does not place GA4 itself |
| Option `kits_ga4_via_gtm` | 1 |
| `page_context.gtag` | `false` on every page, so kit.js only pushes to the dataLayer and never calls `gtag()` |
| GA4 G-4P5YMJ04RL | reached only through the Google tag and GA4 event tags inside GTM-TB3FCFQB |
| Logged-in staff | no GTM, no Clarity (kits-core analytics.php) |

Guard in the container: every event trigger fires only when `DLV - gtag` is not `true`, and the Google tag is blocked
on Initialization when it is `true`. If Site Kit's Analytics snippet is ever switched back on and `kits_ga4_via_gtm` is
cleared, `gtag` becomes `true`, kit.js sends events to GA4 directly and the GTM tags stand down by themselves.
Keep it as it is: Site Kit Analytics snippet off, `kits_ga4_via_gtm` = 1.

## Import the container

File: `docs/analytics/gtm-container-animalwrites.json` (replaces `docs/gtm/animalwrites-container.json`; same tag,
trigger and variable names, plus `DLV - gtag` and the guard).

1. GTM → Admin → Import container → choose the file.
2. Workspace: Default (or a new one).
3. **Merge → Overwrite conflicting tags, triggers and variables.** Do not choose "Rename": the container already
   holds these tags, and renamed copies would fire alongside them and count every event twice.
4. Preview on animalwrites.in in a logged-out window; check events in GA4 → Admin → DebugView.
5. Submit → Publish.

## Key events (GA4 → Admin → Events → mark as key event)

| Event | Why |
|---|---|
| `form_submit_success` | every form sent (split by the `form` dimension); keep it marked until the four below have data, then unmark it |
| `volunteer_signup` | a volunteer registration (GTM tag: `form_submit_success` where `form` = `volunteer`) |
| `newsletter_signup` | a newsletter sign-up (`form` = `newsletter`) |
| `claim_sent` | a listing claim sent (`form` = `claim`; carries `listing_id`, `listing_name`) |
| `contact_sent` | a contact message sent (`form` = `contact`) |
| `call_click` | a visitor rings an organisation (or any number on the site; on a profile it carries `listing_id`) |
| `whatsapp_click` | a visitor opens a WhatsApp chat with a number (17 Oct: share links and group invites no longer count) |
| `email_click` | a visitor writes to an organisation |
| `claim_step` | optional: only if claim funnel starts should count; otherwise leave as a normal event |

GA4 marks a whole event, not one `form` value, and its "Create event" screen can no longer filter on a parameter, so
the container sends `volunteer_signup`, `newsletter_signup`, `claim_sent` and `contact_sent` itself (GA4 event tags on
the same dataLayer event, each filtered on `DLV - form`). They appear in GA4 after the container is published and the
first send arrives; then mark them as key events.

`generate_lead` (17 Oct): one event for a visitor contacting a listed organisation (`lead_source` = call, whatsapp,
email), fired together with `call_click` / `whatsapp_click` / `email_click` when the click is on a Directory profile.
Do **not** mark it while the three older events are marked: GA4's "Key events" total adds every marked event, so the
same tap would count twice. Migration when wanted: mark `generate_lead`, unmark `call_click`, `whatsapp_click`,
`email_click` the same day, and annotate the date (older reports keep the old names).

## Campaign 2.0 events (16 Oct 2026)

The container has GA4 event tags for the campaign tools (`plugins/animal-writes-core/assets/js/campaign.js`):
`campaign_view`, `letter_open`, `ground_toggle` (ground, on), `objection_sent` (route: gmail, outlook, mailto, copy),
`objection_confirmed`, `rule_card_open` (rule), `home_campaign_click` (link_text). Import the container again (Merge →
Overwrite) to add them.

- 17 Oct 2026: `newsletter_optin` (source: e.g. `campaign2`) fires when the newsletter form succeeds on a campaign page
  (after the server confirms). Share links on campaign pages fire the site's existing `share_click` (network: whatsapp,
  x, facebook, linkedin, copy_link); any link marked `data-kit-share="network"` counts as a share.
- Key events: mark `objection_sent`, `objection_confirmed` and `newsletter_optin`.
- Custom dimensions (scope Event): `route` (Letter route), `rule` (Rule chip), `ground` (Letter ground), `source` (Sign-up source).
- Funnel exploration: `campaign_view` → `letter_open` → `objection_sent` → `objection_confirmed`, broken down by
  `route` and device category.
- Search Console: page group for `/campaigns/lab-animals-rules-2026/` and the two Library items
  (`/library/draft-laboratory-animals-rules-2026/`, `/library/breeding-experiments-animals-rules-1998/`).

## Standard campaign events (17 Oct 2026, every campaign)

Every campaign page (post type `aw_campaign`, so future ones too) sends the same events with the same parameters; no
per-campaign names. window.kitsPage carries `campaign_slug` (the URL slug, e.g. `lab-animals-rules-2026`),
`campaign_title` and `campaign_status` (open / closed, from the campaign's close date) on campaign pages.

| Event | When | Parameters |
|---|---|---|
| `campaign_view` | every campaign page view (aw-analytics.js; was only on pages with a letter tool) | campaign_slug, campaign_title, campaign_status |
| `campaign_action` | every action on a campaign page | action, campaign_slug, campaign_title, and where they apply link_text, domain, network, route, rule, ground, on, source |

`action` values: `letter_open`, `ground_toggle`, `objection_sent`, `objection_confirmed`, `rule_card_open`, `share`,
`newsletter_optin` (letter tool and campaign.js); `section_jump` (#links), `document_open` (Library or PDF),
`guide_open`, `news_open`, `coverage_open` (Media & PR), `outbound` (press coverage and other sites), `whatsapp_group`
(chat.whatsapp.com), `social_follow`, `volunteer_click`, `contact_click`, `call`, `email`, `campaign_link`,
`internal_link`, `faq_open` (an FAQ answer opened); `promo_click` from the home band (on the home page).

Why `campaign_slug` / `campaign_title` and not `campaign_id` / `campaign_name`: gtag and GA4 read `campaign_id`,
`campaign_name` (and campaign_source, campaign_medium …) as traffic-source fields; sent on an event they can rewrite the
session's source/campaign attribution.

Older campaign events keep firing, now also with campaign_slug and campaign_title (mapping: older event = `campaign_action`
with that `action`): `letter_open`, `ground_toggle`, `objection_sent`, `objection_confirmed`, `rule_card_open`,
`newsletter_optin`, `home_campaign_click` (= `promo_click`). Keep `objection_sent`, `objection_confirmed` and
`newsletter_optin` as key events (a key event cannot be one `action` value). When the owner retires the older names,
pause their GTM tags; the funnel then reads `campaign_view` → `campaign_action` (action = letter_open → objection_sent →
objection_confirmed).

## Directory events (17 Oct 2026)

| Event | When | Parameters |
|---|---|---|
| `view_item` | a profile is opened | items[0]: item_id (post ID), item_name, item_category (type), item_category2 (state), item_category3 (district); listing_id, listing_name, listing_type, state, district, contacts (how many of WhatsApp/Call/Email/Website it has), verified |
| `view_item_list` | a Directory page (all India, state, district, type, filtered or searched) on load; a "More organisations in …" list when it scrolls into view | item_list_id (directory_all / _state / _district, + _type, + _search; related_listings), item_list_name, items with index (position counted across pages), results |
| `select_item` | a listing card clicked in any list | item_list_id, item_list_name, items[0] with index |
| `listing_action` | any action on a profile | action (call, whatsapp, email, website, directions, social, donate, volunteer, claim, report, suggest_update, share, type_link, place_link, breadcrumb; a greyed-out button tapped: call_missing, whatsapp_missing, email_missing, website_missing, directions_missing), area (actions, details, help, head, share), network, listing_id, listing_name, listing_type, state, district |
| `generate_lead` | call, WhatsApp or email on a profile | lead_source, listing_id, listing_name, listing_type, state, district |
| `no_results` | a hub list came back empty (Directory filters or name search, other hubs' filters) | hub, term, filters, listing_type, state, district |
| `pin_search` | (unchanged) a 6-digit PIN typed in a search box; the Directory search on the homepage now also sends `filter_apply` | pin |

The page context now also has `listing_id` and `listing_name` on profiles and on the Claim, Report and thank-you pages
opened for a listing (`?listing=`), `listing_type` on Directory type pages and type filters, and `page_type` = `home` on
the homepage (it said `page`). `card_click` now counts `position` among cards only (ad cards were counted) and carries
`card_id`, `card_name`.

GA4 ecommerce item reports (Monetisation → E-commerce purchases / item lists) show Directory listings as "items":
views, list views, list clicks and the item list names. No revenue is involved; ignore the revenue columns.

## Money (17 Oct 2026)

| Event | When | Parameters |
|---|---|---|
| `ad_slot_status` | once per ad slot with code, per page view | ad_slot (e.g. directory-feed, profile, article-incontent-1), ad_status (filled, unfilled, not_loaded = AdSense script never ran, usually an ad blocker; no_response = no answer in 15 s; code = another network's code), position (nth slot on the page) |
| `ad_slot_view` | a filled slot at least half on screen for 1 second | ad_slot, ad_status, position |
| `sponsored_view` | a sponsored block half on screen for 1 second; a sponsored Resource or listing page opened | sponsor, sponsored_type (resource, banner, page, listing), item |
| `sponsored_click` | a link in a sponsored block | sponsor, sponsored_type, item, domain |
| `donate_click` | a donate link (a listing's Donate button, or any link to a donation page) | domain, link_text, listing_id, listing_name |
| `select_content` | a named law, organisation or place linked in a summary | content_type (entity_law, entity_organisation, entity_place), content_id (the page path) |

Clicks on AdSense ads cannot be counted by the site: the ad is a frame from Google's domain. Ad clicks, impressions and
revenue per page come from linking AdSense to GA4 (owner: GA4 → Admin → Product links → AdSense links → Link, choose the
AdSense account; needs admin on both). GA4 then adds `publisher_ad_impression` / `publisher_ad_click` with ad unit and
revenue. Give each slot its own AdSense ad unit (today "AW list" and "AW article" serve several slots) to see revenue
per slot. Auto ads placed by Google itself (outside the site's slots) are not in `ad_slot_status`.

Comparing `ad_slot_status` (filled) with `ad_slot_view` gives viewability per slot and page type; `not_loaded` share
gives the ad-blocker rate.

## Custom dimensions (GA4 → Admin → Custom definitions → Create, scope Event)

| Dimension name | Parameter | Sent with |
|---|---|---|
| Page type | `page_type` | every event |
| Section | `section` | every event (Google tag) |
| Category | `category` | every event (Google tag) |
| State | `state` | every event (Google tag) |
| District | `district` | every event (Google tag) |
| Listing type | `listing_type` | every event (Google tag) |
| Issuer | `issuer` | every event; pdf_download |
| Publisher | `publisher` | every event (Google tag) |
| Form | `form` | form_submit_success, form_error |
| Form error where | `where` | form_error |
| Network | `network` | share_click |
| Filter | `filter` | filter_apply |
| Filter value | `value` | filter_apply, sort_change |
| Hub | `hub` | filter_apply |
| Search term | `term` | search |
| Claim step | `step` | claim_step |
| List | `list` | card_click |
| Outbound domain | `domain` | outbound_click |
| Button label | `label` | button_click |
| Button area | `area` | button_click |
| Menu area | `menu_area` | menu_click |
| Item | `item` | pdf_download, sponsored_view, sponsored_click |
| Letter route | `route` | objection_sent, campaign_action |
| Rule chip | `rule` | rule_card_open, campaign_action |
| Letter ground | `ground` | ground_toggle, campaign_action |
| Sign-up source | `source` | newsletter_optin, campaign_action (add if not yet registered) |
| **Action** | `action` | listing_action, campaign_action (new 17 Oct) |
| **Listing ID** | `listing_id` | view_item, listing_action, generate_lead, call/whatsapp/email_click, claim_sent, form_submit_success, donate_click (new) |
| **Listing name** | `listing_name` | the same (new) |
| **Lead source** | `lead_source` | generate_lead (new) |
| **Campaign slug** | `campaign_slug` | campaign_view, campaign_action, older campaign events (new) |
| **Campaign title** | `campaign_title` | the same (new) |
| **Ad slot** | `ad_slot` | ad_slot_status, ad_slot_view (new) |
| **Ad status** | `ad_status` | ad_slot_status, ad_slot_view (new) |
| **Sponsor** | `sponsor` | sponsored_view, sponsored_click (new) |
| **Sponsored type** | `sponsored_type` | sponsored_view, sponsored_click (new) |

Metrics (scope Event, unit Standard) if wanted: `results` (search, view_item_list), `position` (card_click, ad slots),
`percent` (scroll_depth), `fields` (form_error), `contacts` (view_item). GA4 allows 50 event-scoped dimensions; with
the ten new ones (bold) and `source` this uses 36.

Item-scoped data (item_id, item_name, item_category 1–3, item list name, index) needs no custom dimensions: GA4's own
item dimensions show it.

Leave `number`, `pin`, `path`, `href`, `link_url`, `link_text`, `filters`, `card_id`, `card_name`, `campaign_status`,
`verified`, `content_id` unregistered (high cardinality; still
visible in DebugView and BigQuery).

## Enhanced measurement

GA4 → Admin → Data streams → web stream → Enhanced measurement: turn **off** Scrolls, Outbound clicks, File
downloads and Site search, since the site sends `scroll_depth`, `outbound_click`, `pdf_download` and `search`
itself. Keep Page views on.

## Microsoft Clarity

| What | Value |
|---|---|
| Project ID | `yy09ekj2dp` (`kits_clarity_id` in animal-writes-core kits-config.php) |
| Placed by | kits-core analytics.php, production host only, never for logged-in staff |
| Not in GTM | do not add a Clarity tag to the container (it would load twice) |
| Link to GA4 | Clarity → Settings → Setup → Google Analytics integration → connect G-4P5YMJ04RL |
| Masking | Clarity → Settings → Masking: keep "Balanced" (form fields masked) |

## Consent (state on 17 Oct 2026; nothing changed)

- No consent banner and no Consent Mode default: Site Kit's Consent Mode setting is off, the container sets no
  `consent default`, so GA4, GTM, Clarity and AdSense run for every visitor without asking.
- India (DPDP Act 2023, Rules notified Nov 2025): consent duties for personal data phase in through 2027; analytics
  cookies are not singled out, but notice and consent for personal data are. Owner decision whether to add a banner.
- EEA/UK/Switzerland visitors: Google requires Consent Mode v2 signals for measurement and ads personalisation there,
  and AdSense requires a Google-certified consent message (AdSense → Privacy & messaging → European regulations) to
  serve personalised ads in those countries. Turning on Site Kit's Consent Mode (regions already listed: EEA, UK, CH)
  sets "denied" defaults there only; it needs a banner that updates consent, or GA4 loses those visitors' data.
