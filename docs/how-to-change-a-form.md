# How to change a form

Every form uses one shared form kit: the field dictionary, the messages, the look and the behaviour are defined once. Change a field there and it changes in every form. A change to one form goes in that form's own file.

## The forms are Fluent Forms forms (owner, 21 Oct 2026)

Every site form is built in **Fluent Forms** (free) and reaches **FluentCRM** (free) through its own Fluent Forms → FluentCRM link. Labels, options, help text, the Contact form's fields per reason (conditional logic), tick boxes, hidden fields, the confirmation with the reference (`{submission.serial_number}`) and the links are Fluent Forms settings: change them in WP Admin → Fluent Forms. **Requests are Fluent Forms entries** (unread count, favourite = needs action, notes = what was done); **people are FluentCRM contacts** (statuses and organisation approval are tags).

| Form | ID | File | Reference | FluentCRM |
|---|---|---|---|---|
| Newsletter (footer, inline) | 3 | `forms/3-newsletter.json` | – | everyone → Newsletter, `newsletter`; then the thank-you page, which shows #9 for topics |
| Contact | 4 | `forms/4-contact.json` | AW-… | `nl_optin` ticked → Newsletter, `contact-form`, `newsletter` (and the WhatsApp fields); every sender a contact with `contact-form`, the WhatsApp fields and the message as a note (code). Report a mistake and Remove my data are plain links above the form, not reasons. Entries also show in Mail → Website forms |
| Volunteer sign-up | 5 | `forms/5-volunteer-sign-up.json` | AW-V-… | everyone → Newsletter, `volunteer`, volunteer fields; organisation ticked → `org-pending` |
| Claim this listing | 6 | `forms/6-claim-this-listing.json` | AW-C-… | ticked → Newsletter, `newsletter`, `claimant`; every claimant a contact with `claimant` (code) |
| Report a mistake | 7 | `forms/7-report-a-mistake.json` | AW-R-… | none |
| Remove my data | 8 | `forms/8-remove-my-data.json` | AW-D-… | none |
| Newsletter (full) | 9 | `forms/9-newsletter-full.json` | – | everyone → Newsletter, `newsletter`; each topic ticked → `topic-…` |

A tick box Fluent Forms saves as `1` when ticked and nothing when not (empty is 0). Each contact also gets "Newsletter box (last form)" 1/0 with the form and its consent wording.

**Versioned:** after changing a form in Fluent Forms, `wp eval-file tools/forms/forms.php export` and commit. To put the repo's version on a site (keeps the form's ID, entries and links; the form as it was goes to `~/backups/forms-before-…`): `… dry`, then `… import` (or `… import 4`). Links name tags and lists by slug in the files; import turns them into the site's IDs.

**Hidden fields on every form:** `v_page_id`, `v_page_title` (smartcodes); `v_referrer`, `v_utm_*`, `v_screen`, `v_lang`, `v_tz`, `v_secs`, `v_first_visit`, `v_visits`, and `v_ga`, `v_clarity` only with analytics consent: filled in the browser by `aw-analytics.js`, because pages are cached (LiteSpeed keeps one copy per address and drops `utm_*`, so a smartcode would save another visitor's values); `consent_version` (fixed). Fluent Forms itself saves IP, browser, device and page with every entry.

**Volunteer update link:** put `{{aw.update_link}}` in a FluentCRM email. Each contact gets their own signed link, `/update/?t=…`, valid 30 days, with no name or email in it (GA4 and Clarity record addresses). It opens the Volunteer form filled in from their contact; sending it updates the contact (FluentCRM matches by email). An edited or expired link is refused.

**Remove my data:** the form only collects the request. Tools → Erase Personal Data → add the email (WordPress sends its own confirmation) → once confirmed, Erase.

**Mail → Website forms** lists the Contact form's entries (read/unread and trash are the entry's own) with the older contact messages.

**Volunteers on a listing page** (`[aw_org_people]` in the listing template): contacts tagged `org-approved` whose organisation matches the listing and who ticked "Show my name".

**Analytics:** GTM (`docs/gtm`) listens for Fluent Forms' success event: GA4 `form_submit` (form, ref) and Clarity's `form_ref`, with analytics consent only.

**Custom code** (`plugins/animal-writes-core/includes/forms-fluentforms.php` unless named): ALTCHA, PIN code → State → District (LGD), the private upload (claim logo and proof), the FluentCRM steps a link cannot do (notes, transactional contacts, the 1/0 box), the campaign letter tool (`campaign-letters.php`, `campaign.js`), the listing's Volunteers from FluentCRM (`org-people.php`) and the hidden-field script (`aw-analytics.js`).

The site's own forms below stay installed and show again if Fluent Forms is switched off (`wp aw ff-off <key>`).

## Shared parts (all forms)

| What | File | Where |
|---|---|---|
| A field's label, icon, example (placeholder), autocomplete, length limit, messages | `plugins/kits-core/includes/form-kit.php` | `kits_field_dict()` (line 20). Example values for this site can be set with the `kits_field_dict` filter. |
| Messages for errors that are not about one field ("Couldn't send…", "Too many tries…", ALTCHA, expired page) | `plugins/kits-core/includes/forms.php` | `aw_form_generic_error()` (line 57) and `aw_form_guard()` |
| Server-side checks (required, email, 10-digit phone, PIN code, web address, length) | `plugins/kits-core/includes/forms.php` | `aw_form_validate()` (line 298) |
| How fields look (height, border, focus ring, error and valid states, button) | `themes/kits-by-wugweb/assets/css/kit-primitives.css` | sections 11 (Form field) and 16 (Form standard) |
| Behaviour (checks on send and when leaving a field, focus the first problem, send without reloading, keep what was typed, character counter, fresh key on an old page, expandable and step forms) | `themes/kits-by-wugweb/assets/js/kit.js` | section 4 and "Expandable forms" |
| PIN code → State and District; District follows State | `themes/animal-writes/assets/js/aw.js` (page) and `plugins/animal-writes-core/includes/places.php` (`aw_pin_lookup()`, `aw_place_fields()`) | |

## Each form

| Form | Fields and layout | What happens on send | Where entries go |
|---|---|---|---|
| Contact (`/contact/`) | `plugins/animal-writes-core/includes/pages.php`, `aw_contact_form()` (line 92); reasons and message examples: `AW_CONTACT_ABOUT`, `AW_CONTACT_PLACEHOLDER` (lines 57, 67) | `aw_contact_submit()` (line 123) | Inbox → Contact messages |
| Volunteer (banner and `/volunteer/`) | `plugins/animal-writes-core/includes/volunteers.php`, `aw_volunteer_form()` (line 56); "How would you like to help?" options: `AW_VOL_ROLES` (line 13) | `aw_volunteer_submit()` (line 87) | Inbox → Volunteer sign-ups |
| Newsletter (footer) | `plugins/animal-writes-core/includes/newsletter.php`, `aw_newsletter_form()` (line 98) | `aw_newsletter_submit()` (line 122) | Newsletter → Subscribers |
| Claim this listing (every directory profile) | `plugins/animal-writes-core/includes/claims.php`, `aw_claim_form()` (line 66); roles `AW_CLAIM_ROLES` (line 17); fields a claim may change `AW_CLAIM_FIELDS` (line 26) | `aw_claim_submit()` (line 100) | Inbox → Listing claims |
| Report a mistake (every directory profile) | `plugins/animal-writes-core/includes/reports.php`, `aw_report_form()` (line 48); reasons `aw_report_reasons()` (line 16) | `aw_report_submit()` (line 64) | Inbox → Reports |

Where they appear: the volunteer banner is the theme pattern `themes/animal-writes/patterns/volunteer-banner.php` (block `animal-writes/volunteer`); the listing card is the block `animal-writes/listing-help` in `themes/animal-writes/templates/single-aw_listing.html`; the newsletter strip is in `themes/animal-writes/parts/footer.html`.

## Examples

- **Rename a label everywhere** (for example "Your name"): change `label` in `kits_field_dict()`.
- **Add a reason to Contact**: add a line to `AW_CONTACT_ABOUT` (and, if it needs its own message example, to `AW_CONTACT_PLACEHOLDER`).
- **Make a field required in one form**: in that form's function, pass `'required' => true` to `kits_dict_field()`, and add the field to the required list in its `…_submit()` function (the second argument of `kits_dict_rules()`).
- **Change a success message**: the `aw_form_success()` call at the end of that form's `…_submit()` function, and the matching text at the top of its form function (shown after a send without JavaScript).

Every change goes through GitHub: push to `wordpress-dev`; the deploy check runs, and only if it passes does the site update.
