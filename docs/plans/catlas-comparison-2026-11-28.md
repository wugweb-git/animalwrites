# Catlas (communikitty.com/catlas) compared with the Animal Writes Directory (28 Nov 2026)

Read 28 Nov 2026 as a visitor: the India hub page and one listing page, structure only. Nothing copied (their terms, section 5, forbid scraping or extracting data without written permission; no open licence or API).

**Scale (owner asked for a note): Catlas lists 52 places in India (about 10,176 worldwide, cat-focused). Our Directory holds 7,565 listings, all animal welfare. Catlas is small next to ours for India and does not vet organisations.** Several Indian entries overlap ours (Friendicoes SECA, People For Animals, Blue Cross of Hyderabad, Bombay SPCA, Animal Care Trust).

| | Catlas | Animal Writes |
|---|---|---|
| Listing schema | Organization, WebSite, LocalBusiness (name, url, image, logo, telephone, email, sameAs, address, areaServed, foundingDate on the org node), BreadcrumbList; hub pages carry an ItemList | Organization, WebSite, WebPage, BreadcrumbList, ImageObject, NGO + AnimalShelter (address, areaServed, telephone, email, sameAs, knowsAbout, hasOfferCatalog, keywords, identifier, subjectOf), FAQPage |
| Sections | Who they are, Get in touch, More rescues in the city, Is this your rescue? | Contact, Online profiles, Location, Registrations, Services, Questions (FAQ), Sources, Is this your organisation?, In the press, Volunteer, Get updates, More organisations nearby |
| Actions | Call, Website, Claim this page, Volunteer, Adoption Guide | Call, Email, Directions, Suggest an update, Report a mistake, Share, Claim this listing |
| Trust | Orgs maintain their own profile; no vetting stated | "Verified (awbi) on 31 May 2026", AWBI code, sources list |
| Hubs and filters | Type chips (rescues, shelters, TNR, cafés, vets...), use my location, map, browse by state or country; URLs like /catlas/rescues/in/india | District x type pages, state pages, map |
| Title pattern | "{Name}: Cat Rescue in {City}, {State}, India" | "{Name} in {State}" |

**What to take from it (ideas only, none started):**
- Their lead is a short, visible who-they-are line and a Call + Website pair above the fold. Ours already has Call, Email and Directions, but Website and Directions show "not yet available" on thin listings (lat/lng and website empty); enrichment is filling these.
- Type chips on the hub page (rescues, shelters, TNR, vets...) are a clean filter pattern; ours has district x type pages that could take the same chip row.
- The listing title carries the service and city ("Cat Rescue in Chennai"); ours names the state only.

## UX, UI, layout and how data is shown (added 28 Nov 2026, measured at 1024 px wide; ours on /directory/goa/, theirs on /catlas/in/india and one listing page)

**List and cards**
| | Catlas | Animal Writes |
|---|---|---|
| Layout | Map-first split view: a 380 px list panel beside a full-height map; all 52 India places in one scrolling list (about 3,300 px tall) | Filter card on top, then a one-column list of 720 px rows; List / Map toggle, "Near me", sort A-Z or "Most details first", "39 results for Goa" |
| Row | Compact: 380 x 64 px, no card border; 40 px round logo or a tinted initial, name in a serif (Fraunces, 16 px, semibold, truncated to one line), one grey 13 px line: "Rescue · Chennai, Tamil Nadu, India"; hover tint only | Roomier: 720 x 144 px; round mark (logo or initials), name (Inter, bold, wraps), place line with pin icon, type tag chip, arrow at the right edge; separators, no card shadow |
| Density | About 2.2 times more places per screen than ours | Roughly 3 per screen on a phone-height window |
| Phone / actions on the row | None; the row only opens the page (in a new tab) | None; the row only opens the page. The one call button is the page-level "Call 1962" |
| Map | Markers carry each org's logo, cluster counts, a legend (Rescue, Cat café, **Approximate location**) | Map is a second view behind a toggle |
| Search and filters | One search box ("city or rescue"), type chips in a scrolling row, "Use my location", Suggest button, share | Name or PIN search, State, District, Type, Service dropdowns, Claimed only, Verified only |
| Honesty about data | Marks approximate locations on the map | Shows "Verified (awbi) on {date}" and "Data not yet available · Suggest an update" |

**Listing page**
- **Catlas:** a header card (logo, name, one-line blurb, type chips, location, big Call and Website buttons), then Who they are (blurb plus specialty chips), Get in touch (email, phone, website, social icons in one card), More rescues in the city, Is this your rescue? Short; a field with no value is simply not shown.
- **Ours:** much longer: Contact, Online profiles, Location, Registrations, Services, Questions, Sources, Is this your organisation?, press, campaign, Volunteer, newsletter, nearby organisations. It shows empty fields on thin listings ("Website: Data not yet available", Directions greyed out), which is honest but makes a thin page look empty.

**Ideas (none started, owner decides):** (1) a compact row variant for district lists (64 to 80 px, one meta line, type as text instead of a chip) so more fit per screen; (2) hide empty fields on thin listings and keep one "Suggest an update" link, instead of a "Data not yet available" line per field; (3) mark approximate locations on the map; (4) a legend and type chips on the map view. Not a copy of their design: the structure and the way empty data is handled are the lessons.
