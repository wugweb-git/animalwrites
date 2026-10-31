# How to change a form

Every site form is a Fluent Forms form; since 25 Oct 2026 there is no other (owner: the site's own forms and their handlers were removed). Change a form in WP Admin → Fluent Forms, then export it to `forms/` (below). The site's code only shows the forms, adds what Fluent Forms (free) cannot do, and gives them the site's look.

## The forms are Fluent Forms forms (owner, 21 Oct 2026)

Every site form is built in **Fluent Forms** (free) and reaches **FluentCRM** (free) through its own Fluent Forms → FluentCRM link. Labels, options, help text, the Contact form's fields per reason (conditional logic), tick boxes, hidden fields, the confirmation with the reference (`{submission.serial_number}`) and the links are Fluent Forms settings: change them in WP Admin → Fluent Forms. **Requests are Fluent Forms entries** (unread count, favourite = needs action, notes = what was done); **people are FluentCRM contacts** (statuses and organisation approval are tags).

| Form | ID | File | Reference | FluentCRM |
|---|---|---|---|---|
| Newsletter (footer, inline) | 3 | `forms/3-newsletter.json` | – | everyone → Newsletter, `newsletter`; then the thank-you page, which shows #9 for topics |
| Contact | 4 | `forms/4-contact.json` | AW-… | `nl_optin` ticked → Newsletter, `contact-form`, `newsletter` (and the WhatsApp fields); every sender a contact with `contact-form`, the WhatsApp fields and the message as a note (code). Report a mistake (`correction`) and Remove my data (`remove`) are reasons with the same standard fields as every reason (owner, 31 Oct 2026). For `remove` no FluentCRM step runs, whatever is ticked: the link's condition `about != remove` and the same check in the code; the entry is kept and the ticks recorded 1/0. Entries also show in Mail → Website forms |
| Volunteer sign-up | 5 | `forms/5-volunteer-sign-up.json` | AW-V-… | everyone → Newsletter, `volunteer`, volunteer fields; organisation ticked → `org-pending` |
| Claim this listing | 6 | `forms/6-claim-this-listing.json` | AW-C-… | ticked → Newsletter, `newsletter`, `claimant`; every claimant a contact with `claimant` (code) |
| Report a mistake (inactive since 31 Oct 2026: now a Contact reason) | 7 | `forms/7-report-a-mistake.json` | AW-R-… | none |
| Remove my data (inactive since 31 Oct 2026: now a Contact reason) | 8 | `forms/8-remove-my-data.json` | AW-D-… | none |
| Newsletter (full) | 9 | `forms/9-newsletter-full.json` | – | everyone → Newsletter, `newsletter`; each topic ticked → `topic-…` |

A tick box Fluent Forms saves as `1` when ticked and nothing when not (empty is 0). Each contact also gets "Newsletter box (last form)" 1/0 with the form and its consent wording.

**Versioned:** after changing a form in Fluent Forms, `wp eval-file tools/forms/forms.php export` and commit. To put the repo's version on a site (keeps the form's ID, entries and links; the form as it was goes to `~/backups/forms-before-…`): `… dry`, then `… import` (or `… import 4`). Links name tags and lists by slug in the files; import turns them into the site's IDs.

**Hidden fields on every form:** `v_page_id`, `v_page_title` (smartcodes); `v_referrer`, `v_utm_*`, `v_screen`, `v_lang`, `v_tz`, `v_secs`, `v_first_visit`, `v_visits`, and `v_ga`, `v_clarity` only with analytics consent: filled in the browser by `aw-analytics.js`, because pages are cached (LiteSpeed keeps one copy per address and drops `utm_*`, so a smartcode would save another visitor's values); `consent_version` (fixed). Fluent Forms itself saves IP, browser, device and page with every entry.

**Volunteer update link:** put `{{aw.update_link}}` in a FluentCRM email. Each contact gets their own signed link, `/update/?t=…`, valid 30 days, with no name or email in it (GA4 and Clarity record addresses). It opens the Volunteer form filled in from their contact; sending it updates the contact (FluentCRM matches by email). An edited or expired link is refused.

**Report a mistake** (Contact, reason `correction`; owner, 31 Oct 2026): `/contact/?about=correction&item=<ID>[&field=picture|about|phone|email|address|hours]` (`aw_report_url()`, form-blocks.php) chooses the reason and fills the Message box with "Page: {title}", "Field: {field}" (only when given), the page's link and an empty line (`{aw.correction_message}`, reports.php; also from the older `?listing=`). No extra or hidden fields.

**Remove my data** (Contact, reason `remove`): `/contact/?about=remove`; Message empty; no FluentCRM step runs.

**Exports:** the current volunteer and subscriber lists are exported from **FluentCRM** (Contacts → filter by tag `volunteer` or list Newsletter → Export). The old tables `{prefix}aw_volunteers` and `{prefix}aw_subscribers` hold only rows from before the forms stopped writing them; they are kept as backups with no admin screen (read them with WP-CLI).

**The update link's token** stays private: GA4 gets `/update/` without `t` (GTM variables, `docs/gtm`), the page sends no referrer (`Referrer-Policy: no-referrer`), is never cached, and Microsoft Clarity masks the whole page.

**Mail → Website forms** lists the Contact form's entries (read/unread and trash are the entry's own) with the older contact messages.

**Volunteers on a listing page** (`[aw_org_people]` in the listing template): contacts tagged `org-approved` whose organisation matches the listing and who ticked "Show my name".

**Analytics:** GTM (`docs/gtm`) listens for Fluent Forms' success event: GA4 `form_submit` (form, ref) and Clarity's `form_ref`, with analytics consent only.

**Custom code** (`plugins/animal-writes-core/includes/forms-fluentforms.php` unless named): ALTCHA, PIN code → State → District (LGD), the private upload (claim logo and proof), the FluentCRM steps a link cannot do (notes, transactional contacts, the 1/0 box), the campaign letter tool (`campaign-letters.php`, `campaign.js`), the listing's Volunteers from FluentCRM (`org-people.php`) and the hidden-field script (`aw-analytics.js`).

**Which form shows where:** each page calls `aw_ff_form( <key> )` (`forms-fluentforms.php`) with the form's ID from `wp aw ff-form <key> <ID>` (keys: contact, volunteer, claim, report, remove_data; the newsletter forms use the options `aw_nl_ff_form` and `aw_nl_ff_form_full`). If Fluent Forms is off or an ID is missing, visitors see nothing in its place and administrators see a short note saying what to set. There is no fallback form.

## Where each form is shown

| Form | Shortcode or block (page) | Code |
|---|---|---|
| Newsletter (#3) | `[aw_newsletter]` (footer reusable block, campaign pages) | `newsletter-fluentforms.php`, `aw_newsletter_ff_form()` |
| Newsletter (full, #9) | `[aw_thank_you]` after a footer sign-up (`/thank-you/?form=newsletter`) | `form-blocks.php` |
| Contact (#4) | `[aw_contact_form]` (`/contact/`); `?about=listing` and so on choose the reason | `pages.php`, `aw_contact_form()`; reasons and message examples `AW_CONTACT_ABOUT`, `AW_CONTACT_PLACEHOLDER` (the placeholder follows the reason, `aw.js`) |
| Volunteer sign-up (#5) | block `animal-writes/volunteer` (the Volunteer banner pattern, `/volunteer/`); `[aw_volunteer_update]` on `/update/` | `volunteers.php`, `form-blocks.php` |
| Claim this listing (#6) | `[aw_claim_page]` (`/claim/?listing=<ID>`; without a listing, the organisation finder) | `form-blocks.php`, `claims.php` |
| Report a mistake, Remove my data | Contact (#4), `?about=correction`, `?about=remove`. `/report/` and `/remove-my-data/` are Rank Math 301s to them (the query string is kept, so `?item=` and `?listing=` still name the item); the pages are in the trash and forms #7 and #8 inactive (entries kept; export 31 Oct in `Claude outputs/`). | `pages.php`, `reports.php`, `form-blocks.php` |

Where they appear: the volunteer banner is the theme pattern `themes/animal-writes/patterns/volunteer-banner.php` (block `animal-writes/volunteer`); the listing card is the block `animal-writes/listing-help` in `themes/animal-writes/templates/single-aw_listing.html`; the newsletter strip is in the footer (reusable block 12752, `[aw_newsletter]`).

## The site's look and the custom fields

| What | File |
|---|---|
| How fields look (height, border, focus ring, error and valid states, button) | `themes/kits-by-wugweb/assets/css/kit-primitives.css` (sections 11 Form field and 16 Form standard); Fluent Forms fields get the site's classes, icons and browser rules in `forms-fluentforms.php` |
| Behaviour (checks on send and when leaving a field, focus the first problem) | `themes/kits-by-wugweb/assets/js/kit.js` |
| PIN code → State and District; District follows State | the custom field "PIN code, State and District" (`aw_ff_render_places()` in `forms-fluentforms.php`), `aw.js` and `places.php` (`aw_pin_lookup()`) |
| Spam check (ALTCHA), timing and honeypot | the custom field "Spam check (ALTCHA)" (`aw_ff_render_check()`); checked in the `fluentform/validation_errors` filter, with the per-form limit (`AW_FF_FORMS`) |
| Claim logo and proof | the custom field "Private upload" (`aw_ff_render_logo()`, `aw_ff_upload()`; checks in `claims.php`) |

## Examples

- **Rename a label, add an option or a reason, make a field required, change a success message:** in WP Admin → Fluent Forms (the form's fields and its Confirmation), then export it to `forms/` and commit. For a new Contact reason that needs a message example, also add it to `AW_CONTACT_PLACEHOLDER` and `AW_CONTACT_ABOUT` (`pages.php`).
- **Show a form on another page:** put its shortcode in the page, or call `aw_ff_form( <key> )` in code.
- **A form was rebuilt and has a new ID:** `wp aw ff-form <key> <new ID>`.

Every change goes through GitHub (see CLAUDE.md). A new form or function, or a restructured one: its own branch and worktree → a pull request into `wordpress-dev` → the review gate and the Deploy check → the owner merges → `aw-ship.sh` ships it. A small fix: commit straight onto `wordpress-dev`; the Deploy check still gates `wordpress`, then `aw-ship.sh`.

## Campaign form (#10) and the raw copy (21 Oct 2026)

- "Sign it" in the campaign letter tool is the Fluent Forms form **Campaign form** (`forms/10-campaign-form.json`, option `aw_ff_campaign_form`). The letter tool keeps its own screen; when a letter is sent, `campaign.js` submits the signer to it. Entries: Fluent Forms → Campaign form. Its FluentCRM link: Newsletter list, tags newsletter and volunteer, fields vol_city, vol_page, vol_source, vol_whatsapp, campaign_ref, campaign_last, campaign_grounds, campaign_route, campaign_date, campaign_line. After the link (`campaign-form.php`): tag campaign-<slug>, the boxes as 1/0, the statistics row.
- The labels on the page come from the letter tool (`campaign-tools.php`), not from the form: change both.
- Unticked boxes (newsletter, WhatsApp, organisation, show my name, consent) are saved as 0 in every entry and contact.
- **Raw copy:** every form send reaches `wp_aw_form_raw` whole, before any check. Status: saved (entry ID), received (refused or failed), duplicate (a retry). 180 days. To get it: `wp aw raw-export --since=2026-10-21 > raw.csv` (add `--status=received` for sends that did not become entries).
