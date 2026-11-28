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
