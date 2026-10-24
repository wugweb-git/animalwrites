# Analytics and ads (Animal Writes 2.0)

**Current setup: see [analytics/ga4-setup.md](analytics/ga4-setup.md).** In short (24 Oct 2026): GA4 (G-4P5YMJ04RL) counts only through Tag Manager GTM-TB3FCFQB (Site Kit loads the container; Site Kit's own GA4 snippet is off, `kits_ga4_via_gtm` = 1). Ads: Advanced Ads only, switched off on live; ads.txt is a static file in public_html (option `aw_ads_txt` deleted). Clarity: one copy, from Kits Core. The old 1.0 container GTM-W9D28GKV is not used.

**Rules (owner, 22 Oct 2026):** never send test events to live GA4 (verify GTM in Preview / Tag Assistant only); no GA4 or GTM changes without the owner's OK; never full-import the GTM exports in this repo (they are stale): add new items by hand.

The sections below are the 8 Oct container plan, kept as history.

## Container content (GTM-TB3FCFQB)

**Variables → Configure built-in:** Click URL, Click Classes, Click Text, Page URL.

**Triggers**

| Name | Type | Fires on |
|---|---|---|
| Contact click | Click – Just Links | Some link clicks: Click URL matches RegEx `^(tel:\|mailto:\|https://(wa\.me\|api\.whatsapp\.com)/)` |
| Share click | Click – All Elements | Some clicks: Click Classes contains `aw-share` |
| Form sent | Page View | Some page views: Page URL matches RegEx `[?&]aw_done=(volunteer\|joined\|report\|claim\|receipt)` |

**Tags (Custom HTML, one per trigger)**

```html
<!-- AW event: contact_click (trigger: Contact click) -->
<script>if (window.gtag) gtag('event', 'contact_click', { link_url: {{Click URL}} });</script>
```

```html
<!-- AW event: share (trigger: Share click) -->
<script>if (window.gtag) gtag('event', 'share', { method: {{Click Text}}, content_type: 'page', item_id: {{Page URL}} });</script>
```

```html
<!-- AW event: form_sent (trigger: Form sent) -->
<script>if (window.gtag) { var m = location.search.match(/aw_done=([a-z]+)/); gtag('event', 'form_sent', { form_name: m ? m[1] : '' }); }</script>
```

Then, in GA4 → Admin → Events, mark `form_sent` and `contact_click` as key events.

`form_name` values: volunteer, joined, report (listing error), claim (listing claim), receipt.
