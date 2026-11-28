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
