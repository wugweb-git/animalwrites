# How to change a form

Every site form is a Fluent Forms form; since 25 Oct 2026 there is no other (owner: the site's own forms and their handlers were removed). Change a form in WP Admin → Fluent Forms, then export it to `forms/` (below). The site's code only shows the forms, adds what Fluent Forms (free) cannot do, and gives them the site's look.

## The forms are Fluent Forms forms (owner, 21 Oct 2026)

Every site form is built in **Fluent Forms** (free) and reaches **FluentCRM** (free) through its own Fluent Forms → FluentCRM link. Labels, options, help text, the Contact form's fields per reason (conditional logic), tick boxes, hidden fields, the confirmation with the reference (`{submission.serial_number}`) and the links are Fluent Forms settings: change them in WP Admin → Fluent Forms. **Requests are Fluent Forms entries** (unread count, favourite = needs action, notes = what was done); **people are FluentCRM contacts** (statuses and organisation approval are tags).

| Form | ID | File | Reference | FluentCRM |
|---|---|---|---|---|
| Newsletter (footer, inline) | 3 | `forms/3-newsletter.json` | – | everyone → Newsletter, `newsletter`; then the thank-you page, which shows #9 for topics |
| Contact | 4 | `forms/4-contact.json` | AW-… | `nl_optin` ticked → Newsletter, `contact-form`, `newsletter` (and the WhatsApp fields); every sender a contact with `contact-form`, the WhatsApp fields and the message as a note (code). Report a mistake (`correction`) and Remove my data (`remove`) are reasons with the same standard fields as every reason (owner, 31 Oct 2026). For `remove` no FluentCRM step runs, whatever is ticked: the link's condition `about != remove` and the same check in the code; the entry is kept and the ticks recorded 1/0. Entries also show in Mail → Website forms |
| Volunteer sign-up | 5 | `forms/5-volunteer-sign-up.json` | AW-V-… | everyone → Newsletter, `volunteer`, volunteer fields; organisation ticked → `org-pending` |
| Claim this listing | 6 | `forms/6-claim-this-listing.json` | AW-C-… | ticked → Newsletter, `newsletter`, `claimant`; every claimant a transactional contact with `claimant` (code; a FluentCRM link would subscribe them, so the unticked case stays in code); approved with "Show my name" = Yes → `org-approved`, `vol_org_listing`, `vol_org_show`, `vol_org_role` (claims-review.php) |
| Report a mistake (inactive since 31 Oct 2026: now a Contact reason) | 7 | `forms/7-report-a-mistake.json` | AW-R-… | none |
| Remove my data (inactive since 31 Oct 2026: now a Contact reason) | 8 | `forms/8-remove-my-data.json` | AW-D-… | none |
| Newsletter (full) | 9 | `forms/9-newsletter-full.json` | – | everyone → Newsletter, `newsletter`; each topic ticked → `topic-…` |

**Email me updates (owner, 31 Oct 2026):** on Contact, Volunteer sign-up and Claim, ticked → the form's FluentCRM link (Newsletter, subscribed); unticked → the same contact on the Newsletter list with the same fields and tags (not `newsletter`), transactional, so never emailed (`aw_crm_unticked()`, fluentcrm.php); a contact already subscribed keeps that status. Remove my data never reaches FluentCRM; the Campaign form is unchanged.

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

**Claim form #6 (owner, 31 Oct 2026):** four steps (About you · Your listing · Show you work there · Send). The steps and the groups that fold open are Fluent Forms Section Breaks with the class `kit-step-break` or `kit-fold-break`; kit.js turns them into steps (Back/Next, each step's required fields checked before Next); without JavaScript it is one long form. Every `l_*` field is filled from the listing (`claims-review.php`, public fields only; `{Listing}` in a label names it). Photos (`photo_tokens`, up to 5), logo and proof use the private upload. The last step's summary lists only changed fields (aw.js). Claims are reviewed in Directory → Claims: now → proposed for changed fields, approved per field, each written with the source "Claimed by organisation" and logged in the enrichment change log (Undo there); approval marks the listing claimed. After this form changes, `wp aw crm-setup` adds any new FluentCRM field (`vol_org_role`).

**Volunteer form #5 and /volunteer/ (owner, 31 Oct 2026):** order: ways to help (icon tiles, `AW_VOL_ROLE_ICONS`), PIN / State / District (one row from 768), full name, email, phone, WhatsApp ("Same as my phone" `wa_same` copies the phone, in aw.js and on the server), "OK to contact me on WhatsApp" only when a WhatsApp number is filled, when / how far / skills as chips (`kit-chips`, `kit-chips--segment`), languages, organisation, "I'm 18 or older." (`adult`, required), consent, newsletter. Tiles and chips are real checkboxes and radios (input hidden visually); each group is named by its label (`role="group"`, `aria-labelledby`). The page (`page-volunteer.html`): head band with the page's intro (meta `aw_page_intro`), the form beside the sidebar (from 768 under it, from 1200 beside it), the page's picture first in the sidebar and no "Help animals near you" card there. Organisation from the Directory: typing three letters in "Organisation name" lists up to 8 published listings ("name · district, state"; `aw_org_search_public()`, org-people.php, a public read-only admin-ajax search like the PIN lookup); picking one saves the listing ID in the hidden `org_listing` (FluentCRM `vol_org_listing`, so an `org-approved` volunteer with "Show my name" appears on that listing's people box) and the listing's own name in `org_name`; "My organisation isn't listed" keeps the typed name with no ID. The server keeps an ID only for a published listing. The update link pre-fills both. Field borders and help text use the Kits tokens `--kit-field-border`, `--kit-field-hint`, `--kit-field-hint-size` (WCAG AA).

**Messages (31 Oct 2026; wording changes only on the owner's yes):**

| Form | Where the success shows | Success message |
|---|---|---|
| Newsletter #3 | /thank-you/?form=newsletter: H1 "You're subscribed", description "Thank you for signing up for Animal Writes updates." (head band; no notice box), then "Choose your topics (optional)" (#9), then Home / Back | – |
| Contact #4 | Same page | Message sent. We'll reply by email. Your reference: AW-{n} |
| Volunteer sign-up #5 | Same page | Thank you for signing up. We will contact you when there is work in your area. You can ask us to delete your details at any time. Your reference: AW-V-{n} |
| Claim this listing #6 | Same page | Request sent. We'll check it and email you, usually within 4 to 5 days. Nothing on your listing changes until we've checked it. Your reference: AW-C-{n} |
| Newsletter (full) #9 | Same page (the thank-you page) | Thank you. Your topics are saved. |
| Campaign form #10 | The letter tool | Thank you. Your reference: {ref} |

Any ?form= that lands on /thank-you/ (`aw_thanks_forms()`): its heading is the H1 and its line the head description (`aw_thanks_head()`).

| Failure | Message |
|---|---|
| Required field empty | The field's own message from the form (e.g. "Enter your name", "Choose a state", "Choose your role") |
| Wrong format | Email: Fluent Forms' email rule message; phone / WhatsApp: "Enter a 10-digit number, like 9876543210."; claim username: "Enter the username only, like animalwrites."; claim date: "Enter a date like 8 Oct 2026."; year: "Enter a year, like 2010."; web address: the form's url rule message; upload: "Use a PNG, JPG or WebP under 2 MB." |
| Spam check | "Couldn't verify you're not a bot. Try again." |
| Too many sends | "Too many tries. Wait a minute, then try again." |
| The send itself fails (no connection, server error) | "Your message didn't send. Check your connection and try again." (aw.js; wording waiting for the owner's yes) |

**Which form shows where:** each page calls `aw_ff_form( <key> )` (`forms-fluentforms.php`) with the form's ID from `wp aw ff-form <key> <ID>` (keys: contact, volunteer, claim, report, remove_data; the newsletter forms use the options `aw_nl_ff_form` and `aw_nl_ff_form_full`). If Fluent Forms is off or an ID is missing, visitors see nothing in its place and administrators see a short note saying what to set. There is no fallback form.

## Where each form is shown

| Form | Shortcode or block (page) | Code |
|---|---|---|
| Newsletter (#3) | `[aw_newsletter]` (footer reusable block, campaign pages) | `newsletter-fluentforms.php`, `aw_newsletter_ff_form()` |
| Newsletter (full, #9) | `[aw_thank_you]` after a footer sign-up (`/thank-you/?form=newsletter`) | `form-blocks.php` |
| Contact (#4) | `[aw_contact_form]` (`/contact/`); `?about=listing` and so on choose the reason | `pages.php`, `aw_contact_form()`; reasons and message examples `AW_CONTACT_ABOUT`, `AW_CONTACT_PLACEHOLDER` (the placeholder follows the reason, `aw.js`) |
| Volunteer sign-up (#5) | block `animal-writes/volunteer` (the Volunteer banner pattern, `/volunteer/`); `[aw_volunteer_update]` on `/update/` | `volunteers.php`, `form-blocks.php` |
| Claim this listing (#6) | `[aw_claim_page]` (`/claim/?listing=<ID>`, with the listing's own head band; without a listing, the organisation finder) | `form-blocks.php`, `claims.php`, `claims-review.php` |
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
