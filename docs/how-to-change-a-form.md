# How to change a form

Every form uses one shared form kit: the field dictionary, the messages, the look and the behaviour are defined once. Change a field there and it changes in every form. A change to one form goes in that form's own file.

## The forms are Fluent Forms forms (owner, 21 Oct 2026)

Every site form is built in **Fluent Forms** (free) and connected to **FluentCRM** by its own Fluent Forms → FluentCRM link. Labels, options, help text, conditions (the Contact form's fields per reason), the Yes/No ticks (saved as 1/0), hidden fields, the thank-you redirect with the reference number and the FluentCRM link are all Fluent Forms settings. Change them in WP Admin → Fluent Forms; nothing about a form's fields is in code.

| Form | ID | File | Reference | FluentCRM link |
|---|---|---|---|---|
| Newsletter (footer) | 3 | `forms/3-newsletter.json` | – | everyone → Newsletter, tag newsletter |
| Contact | 4 | `forms/4-contact.json` | AW-… | `nl_optin` = 1 → Newsletter, tags contact-form, newsletter |
| Volunteer sign-up | 5 | `forms/5-volunteer-sign-up.json` | AW-V-… | everyone → Newsletter, tag volunteer; the new answers into Volunteer custom fields |
| Claim this listing | 6 | `forms/6-claim-this-listing.json` | AW-C-… | `nl_optin` = 1 → Newsletter, tag newsletter |
| Report a mistake | 7 | `forms/7-report-a-mistake.json` | AW-R-… | none (never) |
| Remove my data | 8 | `forms/8-remove-my-data.json` | AW-D-… | none (never) |

**Versioned:** after changing a form in Fluent Forms, export it into the repo and commit:
`wp eval-file tools/forms/forms.php export`. To put the repo's version on a site (keeps the form's ID, entries and link; the form as it was goes to `~/backups/forms-before-…`): `wp eval-file tools/forms/forms.php dry`, then `… import` (or `… import 4` for one form).

**Hidden fields on every form:** `v_page_id`, `v_page_title` (Fluent Forms smartcodes); `v_referrer`, `v_utm_*`, `v_screen`, `v_lang`, `v_tz`, `v_secs`, `v_first_visit`, `v_visits`, and `v_ga`, `v_clarity` only with analytics consent (filled in the visitor's browser by `aw-analytics.js`, because the pages are cached); `v_country`, `v_city` (found on the server from the IP, DB-IP Lite, no outside service); `consent_version` (fixed: each tick's wording version). Fluent Forms itself saves the IP, browser, device and page with every entry.

**What is still code** (`plugins/animal-writes-core/includes/forms-fluentforms.php`), because the plugins cannot do it: the spam check (ALTCHA), PIN code → State → District (LGD), the private upload (claim logo and proof), checks Fluent Forms cannot make (Indian mobile numbers, a real State and District, the claimed listing, an upload, a page on this site), the country and city lookup, and one small hook per form that also saves the entry where the rest of the site reads it (Inbox → Messages, the volunteers table, Listing claims, Reports, data requests). The campaign letter tool (`campaign-letters.php`, `campaign.js`) is not a Fluent Forms form and keeps its own code.

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
