# Catlas vs the Animal Writes Directory: UX, UI, design and layout at every breakpoint (28 Nov 2026)

**What was compared:** Catlas (communikitty.com/catlas, India hub `/catlas/in/india` and one listing `/catlas/animal-care-trust-chennai`) against our Directory (state list `/directory/goa/` and one listing `/directory/org/panjim-animal-welfare-society/`). Measured in the in-app browser with the viewport set to 375, 768, 1024, 1280 and 1440 px wide (computed styles and element boxes, read as a visitor). Structure and measurements only; no data copied (their terms forbid scraping without written permission). Related note: [`catlas-comparison-2026-11-28.md`](catlas-comparison-2026-11-28.md) (schema, scale: 52 India places vs our 7,565 listings).
**Limits:** one list page and one listing per site, logged-out view of Catlas; our listing is a verified AWBI one, not a thin one. Hover, focus, motion and dark mode were not measured. Where a value is inferred it says so.

## 1. Design language

| | Catlas | Animal Writes |
|---|---|---|
| Mood | Warm, friendly, "app-like": cream header, lavender-to-peach gradient hero card, soft round shapes | Plain, editorial, high-contrast: grey page, black pills, white chips; yellow accent only on the header "Contact us" button |
| Page background | White (`#fff`); header cream `rgb(251,247,240)` | Light grey `rgb(244,244,244)` |
| Text colour | Deep violet ink `rgb(42,31,74)`; meta grey-violet `rgb(107,100,120)` | Near-black `rgb(20,20,20)` headings, `rgb(30,30,30)` body, meta `rgb(95,95,95)` |
| Primary colour | Violet `rgb(85,51,148)` (Call button, chip text); tint `rgb(232,220,251)` (chips) | Black `rgb(20,20,20)` (Call button, "Search") |
| Headings | Fraunces (serif), weight 500 to 600 | Inter, weight 700 |
| Body font | System UI stack (`-apple-system`), 17 px | Inter, 15 to 16 px |
| Shapes | Hero card radius 28 px; pill buttons and pill chips; round 40 to 44 px avatars | Pill buttons (9999 px); chips 8 px radius, 1 px grey border; round initials mark; no card shadows |
| Imagery | Org logo or photo on every listing and as a map marker | Initials mark unless the org has a logo; no pictures on pages (owner rule) |
| Brand fit | Cat-rescue consumer brand | Civic, record-keeping brand; black, white and grey with yellow as a rare accent (owner, 10 Nov) |

## 2. List page (state or country hub) by breakpoint

| Width | Catlas | Animal Writes |
|---|---|---|
| 375 | Header 61 px; page is map and list stacked (the first row sits at y=735, below the map and filters); **bottom tab bar**; row 354 x 65 (40 to 44 px avatar, serif name 16 px, meta 13 px); H1 22 px | Header 75 px; filter card, then list; row 343 x 184 (name 20 px / 600, meta 13 px, type chip 154 x 28, 12 px text); H1 32 px; Call 1962 pill 118 x 44 |
| 768 | Header 57 px; still stacked; row 748 x 64; H1 22 px | Header 75 px; row 704 x 143; name 21 px; chip 164 x 28; H1 39.6 px; Call 1962 134 x 48 |
| 1024 | **Split view starts**: list panel 380 px wide at x=30 beside a full-height map; row 380 x 64; H1 22 px | List column fixed 720 px; row 720 x 144; chip 171 x 28; H1 44.6 px |
| 1280 | Same split view, panel stays 380 px; header 57 px | Row 720 x 145; name 22 px; chip 176 x 28; H1 48 px; Call 1962 134 x 52 |
| 1440 | Same: panel 380 px, map takes the rest | Same 720 px column; H1 48 px; extra width is empty margin |
| Scroll | No horizontal scroll at any width | No horizontal scroll at any width |

**Reading:** Catlas changes layout once (stacked below 1024, split view from 1024) and keeps type sizes fixed (22 px H1, 16 px names). Ours scales type fluidly (H1 32 to 48 px, name 20 to 22 px) but keeps one column at every width, so on desktop the page is a 720 px column with unused space and the map is a separate toggle. Their row is about 2.2 times shorter (64 against 144 px); at 1024 and up their panel shows about 7 places per 800 px screen against about 4 of ours.

## 3. Listing page by breakpoint

| Width | Catlas | Animal Writes |
|---|---|---|
| 375 | Hero card x=17, 341 x 475, radius 28, padding 34 x 21; H1 Fraunces 30 px (weight 500); **Call button full width, 299 x 52, violet pill**; Website as a text link; page height 3,570 px | No hero card; H1 Inter 32 px / 700 at x=16 (343 wide); Call pill 187 x 44, **grey tint** (`rgba(20,20,20,.06)`), not filled; 13 sections; page height 5,916 px |
| 768 | Hero 720 x 295 at x=24; H1 34.6 px; Call button moves right, 99 x 56; page 2,261 px | Single 704 px column at x=32; H1 39.6 px; Call 203 x 48; page 4,734 px |
| 1024 | Hero 976 x 271; H1 46 px; Call 99 x 56 at the right of the hero; page 1,840 px | Column 720 px; H1 44.6 px; Call 203 x 48; page 4,712 px |
| 1280 | (same pattern, width between 1024 and 1440) | Column 720 px at x=48; H1 48 px; Call 203 x 52; page 4,454 px |
| 1440 | Hero 1192 x 230 at x=124; H1 52 px; page 1,780 px | Column 720 px at x=120; H1 48 px; page 4,454 px |

**Reading:** their listing is a short, card-based page (4 headed sections: Who they are, Get in touch, More rescues, Is this your rescue?) whose hero holds logo, name, blurb, chips, location and a single clear primary action. Ours is a long single column (13 headed sections: Contact, Online profiles, Location, Registrations, Services, FAQ, Sources, claim, press, campaign, Volunteer, newsletter, nearby), 2 to 2.5 times taller at every width, with the call action as a quiet tinted pill. Ours carries far more verifiable information (AWBI code, "Verified on" date, sources, FAQ); theirs is faster to scan.

## 4. Mobile behaviour

- **Navigation:** Catlas shows a bottom tab bar on phones (Nearby, Catlas, Learn) and a 57 to 61 px cream header; ours has a 75 px header with a "Contact us" button and a menu icon, no tab bar.
- **Touch targets:** Catlas Call 52 to 56 px tall; ours 44 px on phones (the minimum), 48 to 52 px above. Their avatar 44 px.
- **Overflow:** neither site scrolls sideways at 375 px.
- **Both** keep the list rows as a single tappable target to the page; neither puts a phone number or button on the row.

## 5. What to take from it (ideas, none started; owner decides)

1. **Compact list row** (64 to 80 px, avatar, one name line, one meta line, type as text) for district and state lists, so a phone screen shows 6 to 8 places instead of 3.
2. **Split list-and-map view from 1024 px**: list panel about 380 px beside the map, with the map pins and the list in step. The map is currently a toggle.
3. **A filled primary Call button** (black pill, 48 px, full width on phones) at the top of a listing that has a phone, in place of the tinted pill.
4. **A short top card on listings** (name, one-line what-they-do, type, place, Call and Website) before the long detail sections; keep the detail below.
5. **Hide empty fields on thin listings**: one "Suggest an update" link instead of "Data not yet available" per field.
6. **Map honesty:** a legend with "Approximate location" for pins from a PIN code or district, not an address.
7. **Do not copy:** their palette (violet and cream) and serif headings; our black, white and grey with Inter is the owner's rule (10 Nov) and fits a civic record.

## 6. Addendum: communikitty.com/learn (the Learn side of the same product)

Read on 28 Nov 2026 for structure and topics only; no text copied (their terms forbid scraping). Catlas is the directory half of CommuniKitty. Learn is the content half, and the bottom tab bar on phones (Nearby, Catlas, Learn) puts the two side by side.

**What Learn holds**
- **Guides**, grouped by stage (Transition, Care, Feeding, Emergency): first days home, litter box set-up, weaning, found kittens, foster, meeting resident pets, litter-box problems.
- **Articles**, grouped by subject (Health, Welfare, Enrichment, Breeds, Science, culture). The Health set covers single conditions (FeLV, FIV, giardia, IBD and more).
- **Glossary**: short term pages tagged by subject, one source each, a "more in this subject" list.
- **Tools**: symptom checker (emergency screen first, then 43 conditions), kitten age by weight, body condition score (1 to 9), calorie calculator, name generator, age converter, breed finder.
- **Supplies** pages (product lists).

**How a guide page is built:** category tags, a one-line intro, a "short version" box, jump links, numbered steps with Do and Don't lists, red-flag rows ("vet now", "call the vet", "normal"), 4 FAQs, 7 sources named by institution, 8 related cards, a not-medical-advice footer. Articles add Key Takeaways, a Bottom Line box, numbered citations with DOIs and a comparison table.

**Their weak spots (our openings)**
- Review labels are inconsistent. Some guides say "vet review pending", the FeLV article says it was AI-drafted and not yet reviewed by a vet, while the footer claims review by vet technicians.
- The kitten age page contradicts itself on birth and week-1 weights.
- Written for the US and UK (supplier links, units, vet access). Nothing on India: Indian law, street cats, ABC for cats, municipal bodies, cost of care.

**What we have today:** no care guides at all. Searching our site for kitten, litter, foster, feeding, spay, vaccination and rabies returns News items, listings and one rabies campaign. The Library and Resources hold law and policy, not how-to care.

**What we could add** (ideas only; nothing drafted or started; copy goes through the owner's yes, fact-bearing health pages stay draft until a vet or the owner signs off):
1. **India-specific stray-cat guides**: found kittens, what to do now; feeding strays on a budget; an injured stray, first steps; trap-neuter-return and sterilisation for cats; living with community cats (housing society rights).
2. **Glossary** of terms Indian readers meet: ABC, TNR, ARV, AWBI, the PCA Act, Darpan. They have terms only; we can tie each term to a law page and a directory type.
3. **Tools** (each needs vet sign-off): a body-condition guide and a "when to call a vet or helpline" checklist built on our helpline blocks.
4. **Existing pages**: add a visible FAQ, a sources list and a "when to call a vet" box to the rabies page; link guides to Directory pages (rescue, vet, sterilisation) the way they link Learn to Catlas.
5. **Structure to borrow, not wording**: short-version box first, red-flag rows, named institutional sources, related cards. This matches our answer-first and claim-heading rules and our no-folds rule (FAQs stay open).
6. **Label review honestly** on every health page ("reviewed by ... on ..." or "not yet reviewed"), which they do not do consistently.

**Do not copy:** their text, their product links, their app promo blocks.

## 7. Addendum: trustedvets.in (vet clinics plus a Shelters, Rescues & NGOs section)

Read 28 Nov 2026: /search/, /vets/delhi/, /about/, a clinic page, /rescue-orgs/ and one rescue record, in the browser at 375 and 1280 px. Who runs it is not stated anywhere I read. Facts only; nothing copied.

### 7.1 What it is
An Indian vet-clinic finder (about 1,614 clinics in 10 cities: Delhi 366, Mumbai 298, Bangalore 247, Pune 205, Jaipur 191, Gurugram 101, Ghaziabad 82, Noida 51, Faridabad 45, Greater Noida 28) with a side section of 55 shelters, rescues and NGOs in Delhi NCR, Mumbai, Bangalore and Jaipur. The About page says "Delhi NCR, Mumbai, Bangalore and 2 more cities", which does not match its own 10-city list. Header nav: Find a vet, Emergency, Rescue & NGOs, Shop, Blog, Our Story, Saved. Phones get a five-tab bottom bar (Find a vet, Emergency, Rescue, Shop, Saved).

### 7.2 /rescue-orgs/ audit
| Area | What it does |
|---|---|
| Structure | One H1 ("Animal Shelters, Rescues & NGOs"), a two-line intro that says it is not a vet directory and links to Find a vet, one row of filter chips, then a single list of 55 cards. No H2 sections, no map, no search box, no sort, no city or locality filter on this page (a city selector sits in the header). |
| Grouping | By type chips only: All, Shelter, Rescue, NGO, Adoption Center. Cards are ordered by Google rating, then city blocks run together (Jaipur, Delhi, Mumbai, Bangalore interleaved by rating, not grouped). |
| Card | Name, locality and city, star rating with review count, type chips, an open-now line ("Open now · Closes 5:00 PM", "Closed today"), then three buttons: Call, Directions, Website (Call is the only filled/tinted one). A card is 848 px wide on desktop and about 309 px on a phone. |
| Layout | Cream page (rgb 250,248,245), Inter 16 px, H1 24 px at every width, single column. 375 px: cards fill the width, page about 14,100 px for 55 cards. 1280 px: one 848 px column, page about 13,400 px, so no gain from the wider screen. Buttons are 30 px tall (below the 44 px touch minimum). |
| Mobile | Bottom tab bar; sticky 61 px header; city picker in the header. |
| Record page (/rescue-orgs/<slug>/) | H1, locality, type chips, Call / Directions / Website, weekly opening hours, address, a Google reviews block with excerpts and a View on Google link. Only 3 headings (name, Location, Google Reviews). No about text, no services, no registration, no sources, no FAQ, no related list. |
| Schema | List page: Organization, WebSite with SearchAction, BreadcrumbList and an **ItemList of the 55 orgs**. Record page: **NGO** with address, **geo**, telephone, sameAs and **aggregateRating** (ratings come from Google reviews), plus BreadcrumbList. No FAQPage, no opening hours in schema. |
| Data source | Everything on the record looks like Google Places data (rating, hours, geo, phone, address, review excerpts) plus the org's own website or Instagram link as sameAs. Several have no phone (14 of 55 here) or only an Instagram link as website. All 55 carry the single type NGO in schema whatever the chip says. |
| Features | Type filter, open-now status, call/directions/website, Save, "Is this your clinic?" claim link, suggest-an-edit link. No donate, volunteer, adopt or services info, no verification badge, no registration number. |

### 7.3 Data check against our Directory
Method: took the 55 names, phones, addresses and coordinates from their record pages and searched our listings (REST) by name. Name matching only, so a different spelling can hide a match; phone and address were not compared one by one.
- **Already in our Directory (about 25 of 55):** e.g. Pashu Anuhar, Go Nirvana Foundation, Raksha Jaipur, Chardikala Animal Shelter, Save A Stray (Noida), Wings and Tails Foundation, Friendicoes SECA, SNEHA Foundation, Animal Matter To Me, Helping Hands Animal Welfare Foundation, Sherry and Diya Foundation, YODA, Apna Home Animal Shelter, Freedom Farm, Rroaming Paws, Raksha Animal Welfare Center, Sarvoham Animal Foundation, Mylos Rescue, Karuna Animal Welfare Association of Karnataka, Second Chance Sanctuary, ALAI Rescues, CUPA Trauma, Sanjay Gandhi Animal Care Centre.
- **No name match found (about 30 of 55), to check one by one:** Youth Jeevseva Charitable Foundation (Jaipur), Gargi Animal Care Society, PAWS Adoption Hostel, Eco Rescuers Foundation, Animal Rain Bashera, PAWS (Vasant Kunj), Priti Love Rescue Foundation, Red Paws Rescue, Animal Care India (Badarpur), SPCA Delhi, Save Life Save Animals, HOPE Foundation (Mulund), Animal Shelter and Hospital (Mandvi), Gully Stray Care, Paws and Purpose, SARC Animal Rescue Shelter, Charlie's Animal Rescue Centre (CARE), Dogs Our Best Friends (Maruthy), Friend For Animal Trust, The Welfare of Stray Dogs, IDA India (Deonar), OM Animal Welfare Foundation (Bird Rescue), Voice of Animals Trust, Help for Animals, Animal Rescue and Care NGO, Animal Rescue & Shelter Foundation and a few more. These are candidates, not facts: each needs a real check (own website, Darpan, AWBI) before anything is added, and Google-sourced values are the weakest tier in our trust order (claim, own site, social, Darpan, OSM, Wikidata, AWBI, listing, India Post, Google).
- **Coordinates:** on the matched listings our `aw_lat`/`aw_lng` fields were empty in every case I sampled, while their records all carry geo. Worth a look before any map idea.
- **What they have that we lack:** Google rating and review count, opening hours and "open now" on every card, geo in schema. **What we have that they lack:** registration data (AWBI, Darpan, 80G), "Verified on" dates, services, sources, FAQ, claim flow, press and campaign links, 7,565 listings against their 55.
- Nothing was imported or changed; this is read-only.

### 7.4 Our pages for comparison (live, 28 Nov)
| | trustedvets /rescue-orgs/ | Animal Writes |
|---|---|---|
| List page | 1 H1, 1 chip row, 55 flat cards | /directory/: H1, Organisations, Browse by state, FAQ; /directory/delhi/: H1, Organisations, Districts, newsletter. State and district pages are grouped by place. |
| Record headings | 3 | 18 on a full listing (Contact, Online profiles, Location, Registrations, Services, Organisation, Support, Photos, FAQ, Sources, claim, press, campaign, Volunteer, newsletter, nearby) |
| Schema (list) | ItemList of the 55 | None on /directory/ or /directory/delhi/ beyond WebPage and BreadcrumbList (no ItemList) |
| Schema (record) | NGO + geo + aggregateRating + sameAs + tel | NGO, VeterinaryCare, AnimalShelter + tel + sameAs, plus FAQPage; no geo, no hours, no rating |
| Text and layout | cream page, 848 px single column, 24 px H1, 30 px buttons | black, white and grey, 720 px column, fluid H1 32 to 48 px, 44 px call pill |

### 7.5 Ideas, none started (owner decides)
1. **ItemList schema** on state and district list pages (theirs has it; ours does not).
2. **Geo in listing schema** once coordinates are filled (needs the lat/lng gap looked at first).
3. **Opening hours and an open-now line** on the list row, only where a verified source gives hours; do not copy Google's.
4. **Type chips** (Shelter, Rescue, NGO, Adoption) on district lists as a quick filter. We already hold the services and type data.
5. **Bigger touch targets**: theirs are 30 px; ours are 44 px or more, so keep ours.
6. **Do not copy** their reliance on Google ratings as the lead signal, or their single-type NGO schema; ours is richer and sourced.
7. **Candidates to research** (the unmatched list in 7.3), only through the normal enrichment route with verified sources.

## 8. Combined suggestions (Catlas, Learn, trustedvets) and what can be ingested

### 8.1 Suggestions, ranked (ideas only; nothing started; owner decides)
**Directory UX and layout**
1. Compact list row (64 to 80 px) with Call, WhatsApp and Directions on the row (sections 5.1 and 7.5).
2. Type chips (Shelter, Rescue, NGO, Adoption) as a quick filter on district lists, from the type and services data we already hold.
3. Filled primary Call button and a short top card on listings; hide empty fields on thin listings behind one "Suggest an update" link.
4. Split list-and-map view from 1024 px, with an "approximate location" legend for pins from a PIN or district.
5. Keep our 44 px touch targets and our black, white and grey look; do not copy violet/cream (Catlas) or cream/green (trustedvets), or Google-rating-led cards.

**Schema and data**
6. `ItemList` schema on state and district list pages (trustedvets has it; we do not).
7. Fill latitude and longitude on listings (sampled matches were empty), then add `geo` to listing schema.
8. Opening hours and an open-now line only where a verified source gives hours; show "Last verified" on every listing.
9. Claim, update and takedown link on every listing (we have a claim flow; make it one visible link).

**Content (Learn side)**
10. India-specific stray-cat and kitten guides, glossary (ABC, TNR, ARV, AWBI, PCA Act), "when to call a vet or helpline" boxes, honest review labels on every health page (section 6). Draft only; the copy gate and a vet or owner sign-off apply.

**Process**
11. Candidate organisations go through the normal enrichment route with verified sources only (8.2).

### 8.2 Is there data that can be ingested?
Short answer: **names as leads, yes; their field values, no.**

| Source | What it has | Can we ingest it? |
|---|---|---|
| Catlas (52 entries) | Rescue names, places, descriptions | Use names and places only as leads. Their terms forbid scraping (read earlier), so do not copy records or text. Value is small next to our 7,565 listings. |
| trustedvets.in `/rescue-orgs/` (55 orgs) | Name, address, phone, geo, hours, Google rating and review excerpts, sameAs link | Their terms and privacy pages say nothing on copying or reuse, and robots.txt allows `/rescue-orgs/` and `/clinic/`. But the values look like Google Places data (rating, hours, geo, reviews), which we must not store beyond our own licensed use, and Google is the weakest tier in our trust order. The only licence they name is OpenStreetMap (ODbL). So: **do not bulk-import their records.** |
| Their 25-odd names that we already hold | Same orgs | Nothing to add by ingestion; our records are richer. |
| Their roughly 30 names with no match in our Directory (section 7.3) | Name and city | **Yes, as a lead list.** Run each through our own enrichment: own website, Darpan, AWBI, social, claim. Create a listing only when a verified source confirms the organisation exists, then store our own sourced values with the source URL and date. Ratings and review text are never copied. |
| Their ratings, review excerpts, opening hours | Google-derived | No. |
| Their 1,614 vet clinics (private clinics, Google-listed) | Name, city, phone, geo, hours, rating | **Correction (owner, 28 Nov):** the Directory does hold vets. It has five veterinary types (`veterinary-hospital` 236, `veterinary-dispensary` 798, `veterinary-office` 197, `mobile-veterinary-unit` 29, plus `veterinary-clinic` 0) and 9 bird hospitals: 1,260 veterinary-type listings, almost all government institutions from the animal husbandry departments. The `veterinary-clinic` type exists but is empty, so private clinics like theirs have an empty slot to land in. A name match of their 1,614 against our 7,564 listings gave 98 exact and 15 near hits, nearly all false (generic names such as Happy Paws or Vet House); the two sets barely overlap because ours is government, theirs private. Same rule applies: leads only, verified sources (own site, Darpan not applicable, social, claim), no Google values or review text. |

**How, if the owner says yes:** (a) save the unmatched names and cities as a lead file in `docs/plans/`; (b) check each against our Directory by name, phone and place (a second pass, since spelling can hide a match); (c) push confirmed new ones through the SourceWeb directory enrichment with the own-site, Darpan and AWBI sources first, parked unless verified; (d) IndexNow after publishing. Free quotas only; no Google spend.

## 9. Schema import: their structured data, ours, and the target (documentation only; no code, no PR)

Read from their live pages on 28 Nov 2026 (JSON-LD). Structure only; example values are shortened.

### 9.1 Their schema by page type
| Page | Types | Properties carried |
|---|---|---|
| Every page | `Organization` and `WebSite` | name, alternateName, url, logo, description; `WebSite` has a `SearchAction` (`/search?q={search_term_string}`). |
| `/vets/<city>/` and `/vets/<city>/<locality>/` | `BreadcrumbList`, `ItemList` | Breadcrumb 2 to 3 levels (TrustedVets, city, locality). `ItemList` is a flat list of `ListItem` (position, url, name), about 30 per page. |
| `/emergency/<city>/` | `BreadcrumbList`, `ItemList` | Same pattern, breadcrumb TrustedVets, Emergency care, city. |
| `/clinic/<slug>/` | `VeterinaryCare`, `BreadcrumbList` | name, url, `PostalAddress` (street, locality, region, postalCode, country IN), `GeoCoordinates`, telephone, image, a one-sentence generated description (what it offers, pets treated, "open 24 hours"), `aggregateRating` (ratingValue, reviewCount, from Google), `openingHoursSpecification`. |
| `/rescue-orgs/` | `BreadcrumbList`, `ItemList` (55 orgs) | As the city lists. |
| `/rescue-orgs/<slug>/` | `NGO`, `BreadcrumbList` | name, url, `PostalAddress`, `GeoCoordinates`, telephone, `sameAs` (the org's website or Instagram), `aggregateRating`. |
| Not used | | `FAQPage`, `Review`, `areaServed`, `identifier`/registration, `foundingDate`, `knowsAbout`, `hasOfferCatalog`, `makesOffer`, `donate`. |

### 9.2 Ours today (same pages)
| Page | Types we output | Missing against theirs |
|---|---|---|
| `/directory/` and state or district pages | `WebPage`, `BreadcrumbList`, `Organization`, `WebSite`, `ImageObject` (+ `FAQPage` on the hub) | **`ItemList`** of the organisations shown. |
| `/directory/org/<slug>/` | `NGO`, `VeterinaryCare`, `AnimalShelter` (one node, `tel`, `sameAs`), `FAQPage`, `WebPage`, `BreadcrumbList` | **`PostalAddress`, `GeoCoordinates`, `openingHoursSpecification`, `aggregateRating`, `image`, description sentence**; and one multi-type node rather than the type that fits the listing. |

What we carry that they do not: `FAQPage`, three organisation types, and (in the page, not yet in schema) registration numbers, services, verified dates and sources.

### 9.3 Target schema for our pages (to adopt; each item needs an owner decision to build)
1. **List pages (state, district, type, vets):** add `ItemList` with `ListItem` position, url, name for the organisations shown, in the order shown, capped at the page size. Keep the existing `BreadcrumbList`.
2. **Listing page, address:** add `address` as `PostalAddress` (streetAddress, addressLocality, addressRegion, postalCode, addressCountry `IN`) from `aw_address` and the place fields. We hold addresses on most listings; this is the cheapest gain.
3. **Listing page, geo:** add `GeoCoordinates` only when `aw_lat` and `aw_lng` are filled (they were empty on every sampled listing); do not add approximate pins.
4. **Listing page, type:** emit the one schema type that fits the Directory type: veterinary types give `VeterinaryCare`; shelter gives `AnimalShelter`; rescue, NGO and trust give `NGO`. Drop the habit of stacking three types on every listing.
5. **Listing page, identity and trust (ours, not theirs):** add `identifier` for AWBI and Darpan ids, `sameAs` for the verified social profiles, `foundingDate` where held, `areaServed` from the district or state, `knowsAbout` or a services list from the service tags, and `dateModified` from the "Verified on" date. These are what separate us from a Google-rating directory.
6. **Opening hours:** add `openingHoursSpecification` only for listings where a verified source gives hours (own site or claimed). Never copy hours from Google.
7. **Ratings:** do not add `aggregateRating`. Theirs is Google data; ours would have to be our own, and we have no reviews. Skip.
8. **Site-wide:** keep `Organization`, `WebSite` with the same `SearchAction` pattern (our site search URL), and `BreadcrumbList` everywhere.
9. **FAQPage:** keep, visible FAQ only (the existing rule).
10. **Vets (new `veterinary-clinic` type, empty today):** if private clinics are ever added, use `VeterinaryCare` with address, geo, telephone, `openingHoursSpecification` and `medicalSpecialty` or `knowsAbout` from services and pet types; no Google rating.

### 9.4 Checks when it is built (not started)
Validate with the Rich Results Test and the Schema Markup Validator; run the Directory page types through Rank Math's schema so RankWeb and Rank Math do not output two graphs; keep Search Console free of new structured-data warnings. Code change would go on its own branch under the owner's two-route rule; the owner said no PR for this step, so this section is the record only.

## 10. Their structure (information architecture), saved for reference

Read 28 Nov 2026 from the sitemap (2,120 URLs) and the live pages.

| Level | URL pattern | What it is |
|---|---|---|
| Home | `/` | Finder; city picker; links to the five main areas. |
| Vets | `/search/` (all 1,614, filters), `/vets/<city>/` (10 cities, about 30 top-rated cards, then every clinic by locality), `/vets/<city>/<locality>/` (about 666 localities), `/clinic/<slug>/` (1,614 records) | Geography first (city, then locality), a flat clinic record at the bottom. |
| Emergency | `/emergency/`, `/emergency/<city>/` | A filtered cut of the same clinics: lists emergency care or open 24 hours; city counts (Jaipur 94, Delhi 82, Pune 48, Bangalore 43, Gurugram 40, Noida 23, Ghaziabad 21, Mumbai 20, Faridabad 7, Greater Noida 5). |
| Rescue and NGOs | `/rescue-orgs/`, `/rescue-orgs/<slug>/` (55) | One flat list, type chips, no city pages. |
| Shop | `/shop/`, `/shop/<category>/` (6), `/shop/<product>/` (76), `/shop/guides/<guide>/` | Categories: Food 15, Treats 14, Health & Wellness 15, Grooming & Monsoon 9, Toys 12, Beds & Comfort 6. |
| Blog | `/blog/` (18 posts) | City how-to-find-a-vet posts, emergency coverage by city, fees, government care, seasonal safety, buying guides, methodology. |
| Trust pages | `/about/`, `/our-story/`, `/contact/` (claim, update, takedown), `/privacy/`, `/terms/`, `/saved/` | |
| Grouping logic | City, then locality; type chips; services; pet type; open status | Same record shown through several cuts (city, locality, emergency, search) rather than separate content. |
| Navigation | Desktop header: Find a vet, Emergency, Rescue & NGOs, Shop, Blog, Our Story, Saved, city picker. Phone: five-tab bottom bar (Find a vet, Emergency, Rescue, Shop, Saved). Footer: browse by city with counts, three short disclaimer lines, Privacy, Terms. |
| Search page filters | Pet type icon chips (Dog, Cat, Bird, Other) under the search box, "Use my location", distance (1 to 10 km), open now or 24 hours, services (10), clinic type (5), area (about 666), home visits; sort: Recommended, Nearest, Open now, Most relevant, Highest rated; "1614 vets found". |
| Owner note | The row of animal icons (Dog, Cat, Bird, Other) at the top of the search page is the part the owner likes. |
| Schema | See sections 7.2 and 9 (ItemList on every list, VeterinaryCare and NGO on records, Product on shop items, FAQPage on the shop, BlogPosting on posts). |

## 11. How they mention disclaimers (quietly), saved for reuse

The pattern: short, plain, one sentence, placed where the doubt arises, in a small grey line, never a banner, modal, checkbox or long legal block. Each says what may be wrong and what to do about it.

| Where | What it says (paraphrased) |
|---|---|
| Clinic record, under the hours | Details may change; confirm with the clinic before visiting. Next to it: "Last verified <month year>". |
| Reviews block | These reviews are from Google, not independently verified by us. |
| Rescue record and list | Details can change; confirm with the organisation before visiting or donating. This directory is informational only and is not affiliated with any organisation listed. |
| City page | Opening hours come from public listings and go out of date; call before travelling. Services are listed only where the clinic's own information states them, so a clinic showing none may still offer more. |
| Emergency page | Call before travelling; hours come from public listings and can be out of date. |
| Footer, every page | Clinic details can change, so confirm with the clinic; some listings include OpenStreetMap data under the ODbL. |
| Shop product | Last verified <date>; prices and availability change, confirm on the retailer's page; not veterinary advice, consult a vet. And on the buy button: opens the retailer in a new tab; we may earn a commission, never at extra cost to you. |
| Blog data post | A caveat sentence inside the text (the fee data is from verified sources in the directory, not every clinic; treat it as a benchmark, not a quote). |
| Blog byline | "Written by a human, with AI assistance." |
| About and Terms | Discovery tool, not medical advice; not a booking platform; "as is". |

Why it works: the warning sits beside the value it qualifies (hours, fees, reviews, price), so it reads as honesty rather than legal cover, and a "Last verified" date shows the information is maintained. Adopting this for us (idea, owner decides, copy goes through the owner's yes): a one-line "confirm before you go" note under contact and hours, "Verified on <date>" beside it, a source-labelled line on any third-party figure, and one footer line. We already have the verified date and the sources; the quiet sentences are what is missing.

## 12. Shop audit (/shop/) and notes for our own shop later

**What it is.** "Vetted Picks": 76 products in 6 categories, no checkout. Every product links out to a retailer (Amazon India affiliate tag in the URL). Stated claims: independently evaluated, no paid placement, methodology published, "we may earn a commission but never at extra cost to you".

**Structure.**
| Part | What it does |
|---|---|
| Shop home | H1 "Vetted Picks", one line of promise, two trust chips ("Independently evaluated", "No paid placement" + Methodology link), category tiles with pick counts, filters (pet: all, dogs, cats; category; show: all, top picks only, budget picks only; sort: recommended, price low to high, price high to low), then picks grouped by category with "See all 15". 9-question FAQ. |
| Category page | A plain-language standard for the category (e.g. food judged on WSAVA manufacturer-trust guidance, not on "natural" or "premium"), the same filters, sub-groups (Cat Food, Dry Food, Premium Dry Food), the product list. |
| Product card | Role tag (Top Pick, Runner-Up, Budget Pick), brand, name, a one-line "who it suits", "Best for" line, price with pack size, "Full breakdown". |
| Product page | Role tag, price, buy button, **Best for, Skip if, Why we picked it, What we like, What we don't like, a standards line, Alternatives worth considering**, then "Last verified <date>", price caveat, not-veterinary-advice line. Same six elements on every product, by design, so products compare fairly. |
| Methodology (blog) | Real, in stock and fairly priced: checks a working Add to Cart on the live page, rejects implausible grey-market prices, re-checks periodically, records last-verified. Removes products that fail. |
| Schema | `ItemList` of all 76 on the shop home, `Product` (name, brand, url, image, `offers` with price in INR) on each product, `FAQPage` on the shop. No `Review` or `aggregateRating` on products. |
| Voice | Plain and sceptical ("words like natural and premium have no regulated meaning in India"), every pick has a "skip if". |

**Audit.**
- **Strong:** one fixed template; honest "skip if" and "what we don't like"; role tags; published method; price and date on each pick; trust chips under the H1; category pages that teach the buyer first; FAQ answers real questions (flea and tick, grain-free, dental chews, joint supplements, treats per day).
- **Weak:** affiliate revenue sits behind "independent" (they disclose it, but only on the product page); it is all Amazon; prices drift (they keep a date); the shop home prints "Showing 71 of 76" (a count mismatch); the product schema has no `Review`; brands are mostly big foreign names, little for stray-animal care, rescues or Indian low-cost basics.
- **For Animal Writes (a future shop; none started):**
  1. Do not copy their picks or text; build our own from vets and rescuers, for our audience (street and rescue dogs and cats, first aid, feeding, rabies-safe handling, travel crates for transport).
  2. Reuse the template shape: Best for, Skip if, Why we picked it, What we like and don't, Alternatives, Last verified, price caveat, not-advice line.
  3. State the commission or any sponsorship on the shop home and on every buy button, and keep "no paid placement" true.
  4. Tie each product to a guide or listing (a first-aid kit to the rabies page, carriers to rescue transport, humane traps to the sterilisation guide), so the shop supports the Directory instead of standing alone.
  5. Add `Product` and `ItemList` schema, no ratings unless we collect our own.
  6. Hold money items back per the owner's standing rule (12 Nov: no money items on the site until told) and the brand rules (black, white and grey, Inter).

## 13. Their blog (18 posts): what it can give us

**Format.** Short title, date, "Written by a human, with AI assistance" byline, plain intro, claim-style headings, specific numbers, a "where to actually start" close. Posts are built on their own directory data (fee data, city counts, emergency coverage) and one public investigation, so each is hard to copy.

| Their post type | Examples | Idea for us (ideas only; copy gate and fact checks apply) |
|---|---|---|
| City "how to find a trustworthy vet" | Delhi NCR, Mumbai, Bangalore | "Where to get animal help in <city>": helplines, government veterinary care, rescue and sterilisation contacts, built from our Directory and the district helpline pages we already have. |
| Emergency coverage by area | Delhi NCR, Mumbai, Bangalore | "24x7 animal care in <state or city>" from our veterinary and helpline data, with a visible "call first, hours change" line. |
| Government care explainer | Free and low-cost government veterinary care in Delhi | Our strongest fit: we hold 1,260 veterinary-type listings, mostly government. Write the state-by-state "what government animal care really offers" using our own data and named sources. |
| Fee data | What a vet visit costs in Delhi NCR | Only if we hold real fee figures; otherwise skip. |
| Rabies and seasonal | World Rabies Day, Diwali safety, monsoon safety | Rabies is already a campaign page; add festival and monsoon safety posts in the same plain style (Diwali is already a campaign). |
| Definitions | Vet vs animal hospital vs pet clinic | "Shelter vs rescue vs sanctuary vs NGO vs gaushala vs panjrapole": what each is, what it can legally do, and how to pick one; ties to our listing types. |
| Method and trust | How we evaluate Vetted Picks | A public "How we verify listings" page: sources, the trust order, "Verified on" dates, what "claimed" and "verified" mean. We hold all of this; a page makes it visible. |
| Buying guides | Food, toys, health, beds | Only if a shop is opened (section 12). |

**Worth borrowing:** posts anchored in our own data, plain caveat sentences inside the text, a byline that states how it was written, and one "where to start" action at the end.

## 14. Owner note, 28 Nov: merge the veterinary types and filter by animal

The owner suggests merging Veterinary care into one type and using animal-type filters. Recorded as a direction, not built.
- **Today:** five veterinary types (hospital 236, dispensary 798, office 197, mobile unit 29, clinic 0) plus Bird Hospital (9); the Directory already presents them as one "Veterinary" filter (`AW_VET_TYPES`), while the listings keep the separate types.
- **Direction:** one public type, **Veterinary care**, with a kind field behind it (hospital, dispensary, clinic, mobile unit, office, 24 hours, home visit) shown as a chip, and **animal-type filters** (Dog, Cat, Bird, Livestock, Wildlife, Other) as icon chips like trustedvets, on every list page and the search. Bird Hospital folds in under Bird.
- **Why it fits:** it matches what a person asks ("a vet for my cat"), removes four near-empty or confusing type pages, makes room for private clinics, and uses one schema type (`VeterinaryCare`).
- **What it needs:** an `animals` field on listings (the enrichment schema already has `animals` and `services`, so the data path exists), a redirect plan for the old type URLs, and the icon set (outline icons, black on white, to match the brand). Not started.
