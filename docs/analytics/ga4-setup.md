# GA4 and GTM setup (Animal Writes, 15 Oct 2026)

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
| `form_submit_success` | volunteer, newsletter, claim, contact, report sends (split by the `form` dimension) |
| `call_click` | a visitor rings an organisation |
| `whatsapp_click` | a visitor messages an organisation |
| `email_click` | a visitor writes to an organisation |
| `claim_step` | optional: only if claim funnel starts should count; otherwise leave as a normal event |

GA4 marks the whole event, not one `form` value. To count only volunteer/newsletter/claim/contact sends, create
events in Admin → Events → Create event (e.g. `volunteer_signup` when `event_name` = `form_submit_success` and
`form` = `volunteer`; likewise `newsletter_signup`, `claim_sent`, `contact_sent`) and mark those as key events.

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
| Item | `item` | pdf_download |

Metrics (scope Event, unit Standard) if wanted: `results` (search), `position` (card_click), `percent` (scroll_depth),
`fields` (form_error). GA4 allows 50 event-scoped dimensions; this uses 22.

Leave `number`, `pin`, `path`, `href`, `link_url`, `link_text`, `filters` unregistered (high cardinality; still
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
