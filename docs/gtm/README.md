# GTM container import (Animal Writes)

File: `animalwrites-container.json` — for container GTM-TB3FCFQB.

Status (14 Oct 2026): published in GTM; Site Kit's own GA4 tag is off and `kits_ga4_via_gtm` = 1, so GA4 counts
only through GTM (no double counting). To update a published container with a newer copy of this file, import with
**Merge → Overwrite conflicting tags, triggers and variables** (renaming would duplicate every tag), then publish.
14 Oct 2026 update: filter_apply also carries `filter` and `value` (one event per filter in use); new variable DLV - filter.

First import:

1. GTM → Admin → Import container → choose the file → workspace "Default" → **Merge** → "Rename conflicting tags".
2. Preview on animalwrites.in, click around (search, a phone link, a PDF), check the events arrive in GA4 DebugView.
3. Submit / Publish the container.
4. Tell Claude Code it is published: it then switches off Site Kit's direct GA4 tag (option `kits_ga4_via_gtm` = 1),
   so GA4 is counted only through GTM. Until then the site sends events to GA4 through Site Kit's tag, and this
   container must stay unpublished (or GA4 would count twice).

Contents: one Google tag (G-4P5YMJ04RL) carrying the page context (page_type, section, category, state, district,
listing_type, issuer, publisher), and one GA4 event tag + trigger per event pushed by the site: form_submit_success,
form_error, search, pin_search, filter_apply, sort_change, outbound_click, call_click, whatsapp_click, email_click,
pdf_download, share_click, claim_step, card_click, 404_view, scroll_depth, menu_click, button_click.
