# GTM container import (Animal Writes)

**Do not full-import this export (owner, 22 Oct 2026): it is stale. Add new tags, triggers and variables by hand; verify in Preview only.** The status and import steps below are history (14 to 21 Oct 2026). Current setup: `docs/analytics/ga4-setup.md`.

File: `animalwrites-container.json` — for container GTM-TB3FCFQB.

Status (14 Oct 2026): published in GTM; Site Kit's own GA4 tag is off and `kits_ga4_via_gtm` = 1, so GA4 counts
only through GTM (no double counting). To update a published container with a newer copy of this file, import with
**Merge → Overwrite conflicting tags, triggers and variables** (renaming would duplicate every tag), then publish.
14 Oct 2026 update: filter_apply also carries `filter` and `value` (one event per filter in use); new variable DLV - filter.

First import:

1. GTM → Admin → Import container → choose the file → workspace "Default" → **Merge** → "Overwrite conflicting tags, triggers and variables" (never Rename).
2. Preview on animalwrites.in, click around (search, a phone link, a PDF), check the events arrive in GA4 DebugView.
3. Submit / Publish the container.
4. Tell Claude Code it is published: it then switches off Site Kit's direct GA4 tag (option `kits_ga4_via_gtm` = 1),
   so GA4 is counted only through GTM. Until then the site sends events to GA4 through Site Kit's tag, and this
   container must stay unpublished (or GA4 would count twice).

Contents: one Google tag (G-4P5YMJ04RL) carrying the page context (page_type, section, category, state, district,
listing_type, issuer, publisher), and one GA4 event tag + trigger per event pushed by the site: form_submit_success,
form_error, search, pin_search, filter_apply, sort_change, outbound_click, call_click, whatsapp_click, email_click,
pdf_download, share_click, claim_step, card_click, 404_view, scroll_depth, menu_click, button_click.

21 Oct 2026 (owner): forms. Tag "Fluent Forms - form_submit and Clarity form_ref (listener)" (Custom HTML, DOM Ready)
listens for Fluent Forms' own success event and pushes `form_submit` with `form` (the form's name on the page) and
`form_ref` (its reference, AW-…, from the confirmation), and sets Clarity's `form_ref` tag. Tag "GA4 event - form_submit"
sends it to GA4 with `form` and `ref`. Both need analytics consent (analytics_storage). No email, name or phone.
Import with Merge → Overwrite, then publish.

21 Oct 2026 (owner): the Google tag sets `page_location` and `page_referrer` from two variables ("JS - page_location
(no update token)", "JS - page_referrer (no update token)") that remove the `t` parameter on /update/, so the volunteer
update link's personal token never reaches GA4 (every event takes these from the Google tag).

31 Oct 2026 (version 11, made by hand in the GTM UI, not by import): the listener also pushes `contact_reason` (the Contact form's `about`); `contact_sent` skips `correction` and `remove`; new `report_sent` for `correction`; the listener's GA4 event is now `aw_form_submit` (GA4's own Form interactions also sends `form_submit`); the newsletter trigger matches `^(newsletter|form-3)$`; the `ad_slot_status` / `ad_slot_view` tags, triggers and variables are removed. Neither export in the repo has these changes.
