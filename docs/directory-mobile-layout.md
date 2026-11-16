# Directory Mobile Layout Spec — Animal Writes

**Status:** proposal  
**Owner:** open  
**Related:** `plugins/animal-writes-core/includes/listing-template.php`, `listing-profile.php`, `content-types.php`, `themes/kits-by-wugweb/kits-registry.json`  
**Model:** Justdial mobile listing (card grid → detail page → claim/edit flow)

---

## 1. Design intent

Mobile-first directory listing that mirrors Justdial's proven flow:

1. **Search / filter** (city → category → filters)
2. **Card grid** (photo, name, rating, verified badge, location, years, phone + WhatsApp + CTA)
3. **Detail page** (tabs: About, Contact, Services, Reviews, Photos, Campaigns)
4. **Claim / edit** (organisation claims its listing to correct details)

Animal Writes already has the schema and functions (listing-template.php, listing-profile.php, content-types.php). This doc maps them to a mobile UI/UX and an edit/add flow.

---

## 2. Content flow (mobile)

### 2.1 Directory entry (city / category landing)

```
URL: /directory/kerala/  or  /directory/kerala/animal-shelter/

[Header: city name + category pill]
[Search bar: "Find an organisation…"]
[Filter chips: Verified · Has phone · Donate · Volunteer · Adopt]
[Sort: Nearest · Top rated · Newest]

Card grid (2-col on tablet, 1-col on phone):
  [Photo 4:3]  [Name]
                [4.2 ★ 86 Ratings] [Verified] [Trust]
                [Ghaziabad] · [17 Years in Business]
                [Banner] [Digital]            ← category pills
  [Call 08105851809] [WhatsApp] [Get Best Price]

[Load more]
```

Each card maps to Justdial's card pattern (image left, details right) using existing classes:

| Element | Existing class / function |
|---|---|
| Card wrapper | `kit-card kit-card--stacked-md` (`aw-lists.css`) |
| Photo | `kit-card-media` |
| Name | `kit-card-title` |
| Rating | `kit-meta-item` + `kit-count` |
| Verified badge | `kit-tag--source` / `aw-verified-mark` |
| Location | `kit-meta-item` + calendar icon |
| Years | `kit-meta-item` |
| Category pills | `kit-tag` |
| CTA row | `kit-btn`, `kit-btn--brand`, `aw-org-phones-wa` |

**Mobile rule:** one card per row, full width, no side-by-side cramming. Photo 4:3 at 280px wide on phones, 320px at 768.

### 2.2 Listing detail page (mobile)

```
URL: /directory/org/{slug}/

[Header: back arrow + share]
[Cover photo or logo]
[Name]
[4.2 ★ 86 Ratings] [Verified] [Jd Trust]
[Ghaziabad] · [17 Years in Business]
[Tabs: About · Contact · Services · Reviews · Photos · Campaigns]

About tab (default):
  [Logo]  Description text…
  Year established · Head of organisation · Registration · 12A · 80G · NGO Darpan
  Animals helped · Services · Languages

Contact tab:
  Call · WhatsApp · Email · Website · Directions (map)
  Other numbers (each: tel: link + WhatsApp link)

Services tab:
  Services list · Areas served · Languages

Reviews tab:
  Rating breakdown · reviewer comments

Photos tab:
  Organisation's own photos grid (never stock/CC)

Campaigns tab:
  Campaign cards where this org is Organiser / Partner / Supporter

[Footer CTA: Claim your listing]
```

**Existing blocks** (from `listing-template.php`, each renders only when it has data):

| Block | Content | Justdial analogue |
|---|---|---|
| `listing-about` | logo + description | Name + about text |
| `listing-registrations` | AWBI, 12A, 80G, FCRA, Darpan | Trust signals |
| `listing-contact` | Call, WhatsApp, Email, Website, Directions | Phone/WhatsApp/CTA row |
| `listing-location` | address, district, opening hours | Location + hours |
| `listing-services` | services, animals helped, areas served, languages | Category pills |
| `listing-organisation` | established, head | Years in business + head |
| `listing-profiles` | social icons | Social links |
| `listing-support` | Donate, Volunteer, Adopt, UPI copy | Support actions |
| `listing-photos` | org's own photos | Photo gallery |

**Critical-field rule** (already in code): phone, email, website, address, hours, services, registration, AWBI — when missing, show "Data not yet available" + link to suggest an update. Never hide.

### 2.3 Mobile breakpoints (existing)

Animal Writes uses only two breakpoints (enforced by `tools/ci/breakpoint_check.py`):

- **767 / 768** — phone → tablet
- **1199 / 1200** — tablet → desktop

No `matchMedia` outside these. All new mobile rules must use `max-width: 767px` / `min-width: 768px` only.


---

## 3. Edit / add flow (mobile)

### 3.1 Claim flow (existing)

Any visitor on an unclaimed listing sees:

> **Claim your listing** — "Run an organisation listed in our Directory? Claim it to correct its details and add your logo and photos."

Button → `/claim/` → `aw_block_claim_cta()` (`listing-profile.php`).

### 3.2 Edit flow (proposed)

Mobile edit is a new screen. Recommended flow:

```
/directory/org/{slug}/edit
  (requires login; capability: aw_edit_listing or owner-verified)

Step 1 — Identity
  Name · Category · Area/landmark · Google Maps link · Lat/Lng · Google place ID

Step 2 — Contact
  Email · Phone(s) (tel: + WhatsApp toggle) · Website

Step 3 — About
  Year established · Head · Registration (12A / 80G / FCRA / Darpan) · Animals helped · Services · Languages

Step 4 — Support
  Donate link · Volunteer link · UPI ID (copy button)

Step 5 — Photos
  Upload org's own photos (never stock/CC); cover + gallery

[Preview] [Save changes]
```

Each step maps to the field groups in `aw_listing_fields()` (`listing-profile.php`):

| Step | Fields (`aw_listing_fields()`) |
|---|---|
| Identity | `aw_area`, `aw_maps_url`, `aw_lat`, `aw_lng`, `aw_place_id`, category |
| Contact | `aw_email`, phones |
| About | `aw_founded`, `aw_head`, `aw_registration`, `aw_reg_12a`, `aw_reg_80g`, `aw_darpan_id`, `aw_animals`, `aw_services`, `aw_languages` |
| Support | `aw_donate_url`, `aw_volunteer_url` |
| Photos | featured image + `listing-photos` block |

**Mobile UX rules for edit:**
- One field per row, full width, 44px min touch target (`kit-size-target`)
- Phone field: `type="tel"` + auto `tel:` link render
- URL field: `type="url"` + shows as clickable link on front end
- Save shows "Saved" toast, not a page reload
- Unsaved changes: `beforeunload` warning

### 3.3 Add new listing (proposed)

Same 5-step edit flow, entry point from:

- Directory landing: "Add an organisation" button
- Claim flow: "This organisation isn't listed — add it"

Result: new `aw_listing` post, status `draft` until verified (per rule 5 of AGENTS.md: never publish without owner OK; unpublish to draft instead of deleting).

---

## 4. Component map → CSS classes

| UI element | Class | File |
|---|---|---|
| Directory card | `kit-card kit-card--stacked-md` | `aw-lists.css`, `kit.css` |
| Card media (photo) | `kit-card-media` | `kit.css` |
| Card title | `kit-card-title` | `kit.css` |
| Rating chip | `kit-chip`, `kit-count` | `aw-single.css`, `kit.css` |
| Verified badge | `kit-tag--source`, `aw-verified-mark` | `aw-lists.css`, `listing-profile.php` |
| Category pill | `kit-tag` | `kit.css` |
| CTA button | `kit-btn`, `kit-btn--brand` | `kit.css` |
| Phone link | `aw-org-phones`, `data-act="phone"` | `listing-profile.php` |
| WhatsApp link | `aw-org-phones-wa`, `data-act="whatsapp"` | `listing-profile.php` |
| Listing group | `aw-listing-group`, `aw-listing-{slug}` | `listing-template.php` |
| Definition row | `aw-facts-row` (`dt`/`dd`) | `listing-template.php` |
| Claim box | `aw-side-claim` | `listing-profile.php` |
| More cards (campaigns) | `aw-more-card`, `aw-more-list` | `aw-single.css` |

---

## 5. Schema reference (existing)

### Post type: `aw_listing`
- **URL:** `/directory/{slug}/` (archive), `/directory/org/{slug}/` (single)
- **Meta keys:** all `aw_*` keys from `aw_listing_fields()` + `aw_place_source`, `aw_verified`, `aw_sponsored`
- **Taxonomies:** WordPress `category` (shared), Tags (labels)

### Field groups (`aw_listing_fields()` in `listing-profile.php`)

```php
'Contact'   => aw_email, aw_area
'Online'    => aw_website, aw_maps_url, aw_lat, aw_lng, aw_place_id,
               aw_g_rating, aw_g_reviews, aw_g_category, aw_g_profile,
               aw_instagram, aw_facebook, aw_x, aw_youtube
'About'     => aw_founded, aw_head, aw_registration, aw_reg_12a,
               aw_reg_80g, aw_darpan_id, aw_animals, aw_services, aw_languages
'Links'     => aw_rel_nofollow
'Support'   => aw_donate_url, aw_volunteer_url
```

### Functions (`listing-template.php` + `listing-profile.php`)

| Function | Returns | Used by |
|---|---|---|
| `aw_lf($id, $key)` | trimmed meta value | all listing blocks |
| `aw_listing_dl($rows, $class)` | `<dl>` HTML | every group block |
| `aw_listing_group($slug, $head, $body)` | `<section>` | every group block |
| `aw_listing_parts($parts)` | CSS-separated values | location, phones |
| `aw_listing_rel($id)` | rel string | external links |
| `aw_org_phones_html($phones, $lid)` | `<ul>` of tel:/wa.me links | `listing-contact` |
| `aw_org_hours_html($rows)` | `<table>` hours | `listing-location` |
| `aw_listing_location_text($area, $place, $pincode)` | deduped address string | `listing-location` |
| `aw_block_claim_cta()` | claim box HTML | detail page sidebar |
| `aw_block_listing_campaigns($a, $id)` | campaign cards | detail page Campaigns tab |

---

## 6. Mobile-specific CSS to add

Place in `aw-lists.css` (or a new `aw-directory-mobile.css` loaded after `aw-lists`):

```css
/* Directory card: mobile-first, one per row */
.aw-news-list > li .kit-card--stacked-md {
  display: flex;
  flex-direction: column;
  height: auto;
}
@media (max-width: 767px) {
  .aw-news-list > li .kit-card--stacked-md > .kit-card-media {
    width: 100%;
    height: auto;
    aspect-ratio: 4 / 3;
  }
  .aw-news-list > li .kit-card--stacked-md > .kit-card-body {
    padding: var(--kit-space-5);
  }
  /* CTA row: full-width buttons */
  .aw-org-phones, .aw-org-phones-wa {
    width: 100%;
    justify-content: center;
  }
}

/* Edit form: mobile */
.aw-dir-edit fieldset {
  border: 0;
  padding: 0;
  margin: 0 0 var(--kit-space-6);
}
.aw-dir-edit label {
  display: block;
  font-size: var(--kit-type-sm);
  color: var(--kit-color-muted);
  margin: 0 0 var(--kit-space-1);
}
.aw-dir-edit input, .aw-dir-edit select, .aw-dir-edit textarea {
  width: 100%;
  min-height: var(--kit-size-target); /* 44px */
  padding: 0 var(--kit-space-3);
  border: var(--kit-border-width) solid var(--kit-color-line);
  border-radius: var(--kit-radius-control);
  font: inherit;
}
.aw-dir-edit .aw-dir-step {
  display: none;
}
.aw-dir-edit .aw-dir-step.is-active {
  display: block;
}
.aw-dir-edit .aw-dir-nav {
  display: flex;
  gap: var(--kit-space-2);
  margin-top: var(--kit-space-8);
}
.aw-dir-edit .aw-dir-nav .kit-btn {
  flex: 1;
}
```

---

## 7. Files to touch (implementation order)

| File | Change |
|---|---|
| `plugins/animal-writes-core/includes/listing-template.php` | no change (blocks already exist) |
| `plugins/animal-writes-core/includes/listing-profile.php` | no change (fields already exist) |
| `plugins/animal-writes-core/theme/assets/css/aw-lists.css` | add mobile card + CTA rules above |
| `plugins/animal-writes-core/theme/assets/css/aw-lists.min.css` | regenerate with `tools/ci/css_min.py` |
| `themes/kits-by-wugweb/kits-registry.json` | no new classes (all existing) |
| **New** `plugins/animal-writes-core/includes/directory-edit.php` | edit/add screen handler (Step 1–5) |
| **New** template part `single-aw_listing-edit.html` | edit form markup |

---

## 8. Acceptance checks

Run before shipping:

```bash
# PHP lint
php -l plugins/animal-writes-core/includes/directory-edit.php

# Theme rules (no new fixed heights / % padding / !important on sections)
python3 tools/ci/theme_rules_check.py

# Registry (every aw-/kit- class registered)
python3 tools/ci/registry_check.py

# CSS min in sync
python3 tools/ci/css_min.py --check

# Breakpoints (only 767/768, 1199/1200)
python3 tools/ci/breakpoint_check.py
```

---

## 9. Next decision needed

**What should the new document cover first?** Pick one:

1. **Mobile card + detail layout** (the spec above, ready to implement)
2. **Edit / add flow spec** (Step 1–5 forms, validation, claim-to-edit conversion)
3. **Justdial pricing & trust signals** (adding price-per-service, call-pickup-rate, "Top Search" badges to AW listings)

Let me know which, and I'll write the implementation-ready spec or create the document in your Docs system.

---

*Co-Authored-By: Wugweb <admin@wugweb.com>*


