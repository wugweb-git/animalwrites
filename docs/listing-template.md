# Directory profile template

`themes/animal-writes/templates/single-aw_listing.html` (post type `aw_listing`), owner 20 Oct 2026. It is built from the
item template's shared blocks (see `item-template.md`) and the listing groups in
`plugins/animal-writes-core/includes/listing-template.php`. Every block prints nothing when it has nothing. In the
Site Editor they are listed as "Listing: …" (category Widgets). Fields: SCF group "Organisation details"
(`acf-json/group_aw_listing.json`). No stored data or wording is changed by the page.

| # | Row | Block | What fills it |
|---|-----|-------|---------------|
| 1 | Breadcrumb | `animal-writes/breadcrumb` | Home › Directory › State › District (each a link); the BreadcrumbList is the same trail. |
| 2 | H1 | `core/post-title` | The organisation's name. No tick (the meta row says Verified). |
| 3 | Meta row | `animal-writes/item-meta` | Up to 3 type chips (primary first) · place chip · status: "Verified on {date}" (aw_verified, aw_verified_on), or "Needs re-check" (aw_needs_reverify), "Featured" while aw_featured and today ≤ aw_featured_until, "Blacklisted on NGO Darpan". No other date. |
| 4–5 | Logo and description | `animal-writes/listing-about` | The featured image (fixed square, left; 64px phone, 96px from 768) and the description (post content, else the excerpt) beside it. No logo: the text full width. |
| 6 | Registrations | `animal-writes/listing-registrations` | AWBI number (aw_awbi_code, "valid until" / "expired" from aw_awbi_valid; "not on the current AWBI list" from aw_awbi_dropped) · Registration ({aw_reg_type}) · 12A · 80G · FCRA · NGO Darpan ID. 12A, 80G, FCRA and Darpan have no valid-until field, so they show the number only. Expiry is worked out at render time from today in India. |
| 7 | Contact | `animal-writes/listing-contact` | Labelled buttons: Call {first number} · WhatsApp (first number marked WhatsApp) · Email · Website ↗ · Directions (aw_maps_url; greyed out without it). Then the other numbers, one per line: "{label}: {number}" (+ WhatsApp link). Contact data appears only here. |
| 8 | Location | `animal-writes/listing-location` | Address (aw_address, else aw_area with district, state, PIN) · District (place chip) · Opening hours (one line per day; split days on one line; "Closed"; days with no row not shown). |
| 9 | Services | `animal-writes/listing-services` | Services (service tags, else aw_services; "Stated by …" aw_services_source, aw_services_on) · Animals helped · Areas served · Languages. |
| 10 | Organisation | `animal-writes/listing-organisation` | Established · Incorporated (aw_reg_date, the registration date as NGO Darpan lists it) · Head of the organisation. |
| 11 | Ad 1 | `kits/ad-slot` "profile" | The "Directory profile" ad. |
| 12 | Online profiles | `animal-writes/listing-profiles` | Small icon links: Instagram, Facebook, X, YouTube, LinkedIn, Google Business Profile. |
| 13 | Support | `animal-writes/listing-support` | Donate · Volunteer · Adopt buttons; "UPI: {aw_upi}" with a copy button. |
| 14 | Photos | `animal-writes/listing-photos` | aw_gallery, the organisation's own photos only (up to 6, caption under each). Stock and CC images (CC0 worker's `aw_cc0_source` / Openverse, or Unsplash, rawpixel, Wikimedia, Pexels, Pixabay, Flickr URLs) are never shown (`aw_is_stock_image()`); `aw_listing_stock_galleries()` lists any. |
| 15 | Sources | `animal-writes/item-sources` | The listing's data sources with their marks and "as on" dates, linked · "Last updated: {date}" = the latest of aw_verified_on, aw_source_date and aw_detail_sources[].checked (never the post's own date) · Editorial policy · Report a mistake. |
| 16 | Share | `animal-writes/item-share` | The same share row as item pages ("Share", icon buttons). |
| 17 | Ad 2 | `kits/ad-slot` "article-end" | |
| 18 | Claim | `animal-writes/listing-help` | "Is this your organisation?" and Claim, only while the listing is not claimed (claimed = verified). |
| 19 | More organisations | `animal-writes/item-more` | "More organisations in {District}": up to 6, filled from the state (same type in the nearest districts first); cards with the logo whole in a 4:3 box, or the initials. |

`aw_rel_nofollow` (or a sponsored listing): every link to the organisation gets `rel="nofollow sponsored"`.

Not shown on the page (stored only): aw_g_category, aw_g_rating, aw_g_reviews, aw_ig_followers, aw_ig_posts, aw_last_post,
aw_detail_sources (except their dates in Last updated). Schema only: aw_lat/aw_lng, aw_place_id, aw_wikidata,
aw_ngobase_url. Removed from the page: the trust box, the old Details list and links row, the "About" heading, the
live Google and Activity boxes, the campaigns/opportunities links box (`[aw_org_links]`) and the "Organisations in"
news card.

## Schema

aw-seo-extras `aw_listing_schema()`: NGO (or VeterinaryCare, AnimalShelter, GovernmentOffice by type) with name, url,
logo, description, PostalAddress, geo, telephone (all numbers), email, sameAs (website, socials, Google profile,
Wikidata, ngobase), areaServed, foundingDate, founder or employee from aw_head, openingHoursSpecification,
identifier (AWBI code, registration, 12A, 80G, FCRA, Darpan ID), image (logo and own photos only). No aggregateRating.
