# Analytics and ads (Animal Writes 2.0)

Site Kit is the only tag source. Kits Core prints no tags of its own (`kits_gtm_id` returns '').

| What | ID | Loaded by |
|---|---|---|
| Google tag → GA4 | GT-TXZHFR39 → G-4P5YMJ04RL | Site Kit Analytics |
| AdSense | ca-pub-5562717269522486 | Site Kit AdSense (`/ads.txt` from option `aw_ads_txt`) |
| Tag Manager | GTM-TB3FCFQB | Site Kit Tag Manager |

The old 1.0 container GTM-W9D28GKV is not used.

**Rule:** no GA4 configuration or Google tag inside GTM-TB3FCFQB, or page views count twice.
The container only adds events, through the `gtag()` that Site Kit already loaded.

GA4 enhanced measurement already records page views, scrolls, outbound clicks, PDF downloads and site search.

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
