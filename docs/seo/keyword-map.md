# Keyword map — Animal Writes 2.0

15 October 2026 (SEO Phase 3). Written using the AEO/GEO, keyword research and SEO/AEO skills.

**How to use this map.**
- One primary keyword per page. Rank Math's focus-keyword field holds the primary keyword first, then the secondary keywords, separated by commas.
- Rank Math free scores up to 5 keywords. Secondary keywords are set only where they add something: the hubs, the topics and the 16 guides.
- No keyword tool with Indian volumes was available (the Ahrefs plan does not include Keywords Explorer). Intent and priority below come from:
  - reading the pages and their own copy;
  - the 10 Oct 2026 keyword audit (`docs/seo-2026-10-10/02-keyword.md`);
  - how the queries are phrased.
- Re-check against Search Console queries once there are 3 months of 2.0 data.
- **Demand column (added 15 Oct 2026).** The data and method are in `docs/seo/keyword-demand-2026-10-15.md`.
  - **T** is the Google Trends index, India, past 12 months, with "animal welfare" = 100. It shows relative interest, not search counts.
  - **AC** is Google autocomplete (India): **Y** exact phrase suggested, **ext** only longer forms suggested, **–** not suggested.
  - **GSC** is Search Console impressions for 17 Nov 2025 – 14 Oct 2026.
  - Still no absolute volumes: Keyword Planner or an Ahrefs plan would add them.

**Intent codes.**
- **I** informational
- **N** navigational
- **C** commercial investigation (choosing an organisation)
- **T** transactional or local (contacting, applying, finding near me)

## 1. Section hubs (7 hub Pages)

| Page | Primary keyword | Secondary keywords | Intent | Search title, page 1 (built by aw-seo-extras; a copy is kept in Rank Math) | Demand (15 Oct 2026) |
|---|---|---|---|---|---|
| /news/ | animal welfare news | animal welfare news india, stray dogs news, animal cruelty news | I | Animal Welfare News from India – Animal Writes | T 5, rising +1,500% ("animal news" T 136); AC Y; GSC hub 30 impr |
| /library/ | animal laws in india | animal welfare court orders, animal welfare rules, supreme court stray dogs judgment | I | Animal Law, Court Orders & Rules in India – Animal Writes | T 2 (AC Y, top "pdf"); "supreme court stray dogs" T 16; GSC hub 48 impr, pos 4.3; rules by name about 70 |
| /resources/ | animal welfare guides | how to report animal cruelty, injured animal help, feeding community dogs law | I | Animal Welfare Guides & Research in India – Animal Writes | T 0, AC – (secondaries AC Y: "how to report animal cruelty in india" is top); GSC hub 20 impr |
| /directory/ | animal welfare ngos in india | animal ngo near me, animal shelter, gaushala, government veterinary hospital | C/T | Animal Welfare NGOs in India: Directory of 5,000+ Groups | AC Y; "animal ngo" T 28 ("near me" leads); "gaushala" T 383; GSC hub 17, place pages 2,540 impr, pos about 8 |
| /opportunities/ | animal welfare volunteer | animal welfare jobs india, animal welfare internship, foster animals | T | Animal Welfare Volunteer, Internship & Jobs in India | T 0, AC Y ("volunteering near me"); "ngo jobs" T 67; "animal welfare jobs india" AC Y; GSC hub 0 |
| /campaigns/ | animal welfare campaigns | letter petition supreme court, community animals campaign | I/N | (WordPress title of the Page) | T 0, AC Y; GSC: "petition campaign" 113, "letter petition" 48, "stray dog petition india" 14 (all on Home); hub 18 |
| /media/ | animal writes in the news | letter petition campaign coverage | N | Animal Writes in the News | AC –, T 0; brand-only (GSC "nodogsnovote" 605 on Home) |

## 2. Directory (programmatic; titles built live, never stored)

| Page type | Primary keyword pattern | Secondary keyword patterns | Intent | Title rule (aw-seo-extras, 60 characters) | Demand (15 Oct 2026) |
|---|---|---|---|---|---|
| State (`/directory/{state}/`) | animal welfare ngos in {state} | animal shelters in {state}, gaushalas in {state}, animal rescue {state} | C/T | Animal Welfare NGOs & Shelters in {State} – Animal Writes, or the longest of its shorter forms that fits | AC mostly – for states (Google suggests cities); GSC state pages about 1,400 impr, pos 8–10 |
| District (`/directory/{state}/{district}/`) | animal ngo in {district} | animal rescue {district}, animal shelter {district}, vet in {district} | T (local) | Animal Welfare NGOs in {District}, {State}: Rescue, Shelters, Vets, or the longest shorter form that fits | AC Y for big cities (Delhi, Noida, Dehradun, Pune, Bangalore); GSC strongest pattern is "animal helpline number {district}" / "animal rescue {district} contact number", about 240 impr, 0 clicks |
| Type in a place (`/directory/{state}/{district}/{type}/`) | {type} in {district} | {type} near me, {type} {state} | T (local) | {Type} in {District} – Animal Writes | AC Y ("gaushala in jaipur", "government veterinary hospital {city}"); GSC "jhajjar gaushala" 22 |
| Filtered list (`?state=&type=`) | {type} in {state} | (none) | T | {Type} in {State} – Animal Writes; canonical rules as set in Phase 1 | n/a (canonicalised) |
| Listing (`/directory/org/{slug}/`) | {organisation name} | {organisation name} contact, {organisation name} {district} | N/T | {Name}, {District} – Animal Writes | GSC 2,144 impr over 367 listings; "{name} contact number" and "{name} reviews" appear |

Type vocabulary that people search for, and the listing type behind each:
- gaushala → Gaushala (2,047)
- veterinary hospital or vet → Veterinary Hospital or Dispensary (928)
- 1962 → Mobile Veterinary Unit (28)
- ABC centre or dog sterilisation → ABC Centre (65)
- wildlife rescue → Wildlife Rescue (60)
- animal ngo → Animal Welfare Organisation (3,891)

The thin-page rules from Phase 1 still apply: thin district × type pages stay out of the index.

## 3. Topics (18 topic archives plus /topics/)

| Topic | Primary keyword | Secondary keywords | Intent | Demand (15 Oct 2026) |
|---|---|---|---|---|
| /topics/ | animal welfare topics | animal welfare issues in india | I | AC Y (school-essay intent); "issues in india" AC – |
| Community Dogs | stray dogs in india | community dogs, street dogs law | I | "stray dogs" T 171 (part anime), "street dogs" T 96, "community dogs" T 11; AC Y; GSC topic 3 |
| Animal Birth Control | animal birth control | abc programme, dog sterilisation | I | AC Y ("rules 2023" top); T about 0; GSC 41 impr pos 5 (guide) |
| Courts and Judgments | animal welfare court judgments | supreme court stray dogs | I | AC –; "supreme court stray dogs" T 16, AC Y |
| Cruelty and Prosecution | animal cruelty cases | animal cruelty law india | I | AC Y ("cases in india"); "animal cruelty" T 25; GSC cruelty queries 41 impr |
| Laws and Rules | animal protection rules india | pca act rules | I | AC – ("animal protection laws india" is suggested); "animal laws" T 14; GSC rules by name about 70 |
| Policy and Government | animal welfare policy india | animal husbandry department | I | AC –, T 0; "animal husbandry department" AC Y, T 304 (broad) |
| Wildlife | wildlife rescue india | human wildlife conflict | I | AC ext (mostly Indiana); T 0; "human wildlife conflict" AC Y, T 6 |
| Cattle and Gaushalas | gaushalas in india | cattle welfare | I/C | AC Y; "gaushala" T 383 |
| Campaigns and Civic Action | animal welfare civic action | animal rights campaigns | I | AC –; "animal rights campaigns" AC Y |
| Pets, Breeding and Pet Shops | pet shops and breeders | pet shop rules | I | AC –; "pet shop rules" AC Y; GSC "pet shop rules 2018" 17 |
| Veterinary Care | veterinary care india | animal hospital | I/T | AC –, "veterinary care" T 40; "veterinary hospital" T 315; "animal hospital" AC Y |
| Feeders and Caregivers | stray animal feeders | community dog caregivers | I | AC Y (exact only); T 0 |
| Performing and Captive Animals | captive animals india | performing animals rules | I | AC –; "performing animals rules" AC ext (1973/2001); GSC 4 |
| Animal Transport and Slaughter | animal transport rules | cattle transport law | I | AC Y ("1978"); GSC "transport of animals rules 1978" about 26; "cattle transport" T 2 |
| Rabies and Public Health | rabies in india | dog bite rabies | I | AC Y; "rabies" T 1,375 (health intent) |
| Disaster and Emergency Rescue | animal disaster rescue | flood animal rescue | I | AC Y (exact only); T 4 |
| Adoption | animal adoption india | adopt indie dog | T | AC Y (Indiana noise); "dog adoption" T 67, "adopt a dog" T 7 |
| Birds | bird rescue india | injured bird help | I/T | AC Y; "bird rescue" T 25; "injured bird helpline" AC Y |

Topic titles are already stored per term in Rank Math ("{Topic} in India – Animal Writes" and similar). The Rank Math template for topics now follows the same pattern.

## 4. Item types (focus keyword per item)

| Type | Primary keyword source | Secondary | Intent | Rank Math schema (Titles & Meta) | Our graph node |
|---|---|---|---|---|---|
| News | a 2 to 4 word phrase from the headline (workers' focus-keyword job) | none | I (news) | Article → NewsArticle | NewsArticle (replaces Rank Math's) |
| Library | a 2 to 4 word phrase from the title (the job) | none | I | Article → Article | Legislation (Acts, Rules, Notifications, Circulars), NewsArticle (rulings), Article (templates) |
| Resources: research | a 2 to 4 word phrase from the title (the job) | none | I | Article → Article | Article (ScholarlyArticle as isBasedOn) |
| Resources: guides (16) | set by hand from the guide | 1 to 2 (see below) | I (how-to) | Article → Article | Article; FAQPage from the guide's Rank Math FAQ block |
| Opportunities | role title (the job) | none | T | None | JobPosting (none once closed) |
| Campaign | animal writes campaign | none | N/I | None | CreativeWork |
| Press | a phrase from the coverage title (the job) | none | N | None | (in the Media CollectionPage) |
| Listings | organisation name (on publish, aw-seo-extras) | none | N/T | None | NGO / VeterinaryCare / AnimalShelter / GovernmentOrganization |

Rules for the focus-keyword job, the same on the site (`aw_seo_clean_keyword`) and in the worker (`focus_keywords.why_refused`):
- 2 to 4 words, in English.
- At least one word from the title or from the item's own excerpt or intro.
- Never ends on a connector such as "for", "of" or "in".
- Never only generic words such as "save animals".
- The site name only for items about Animal Writes.
- A refused phrase is asked for once more, with the reason.

## 5. Resources guides (16)

| Guide | Primary (unchanged) | Secondary added | Main question the first paragraph answers | Demand (15 Oct 2026) |
|---|---|---|---|---|
| How to file a police complaint (FIR) for animal cruelty | file a police complaint | fir for animal cruelty, animal cruelty complaint | Can I report cruelty to the police? Yes; serious cases must be registered as an FIR | AC Y (generic); "fir for animal cruelty" AC Y |
| Feeding community dogs: what the law says | feeding community dogs | is feeding stray dogs legal, feeding stray dogs law | Is feeding legal? Yes; the rules are about where and how | AC Y (weak); "is feeding stray dogs illegal in india" AC Y; GSC 1 |
| RWA or society stopping you from feeding dogs | society stopping feeding dogs | rwa stray dog feeding, feeding spot abc rules | What to do (steps follow) | AC –; "feeding stray dogs in housing society" AC Y; GSC page 7 |
| Injured animal emergency: who to call first | injured animal emergency | 1962 helpline, injured dog help | Who to call: lead sentence added (from the guide's own steps) | AC Y; "injured animal helpline number" AC Y; GSC 121 (old URL) |
| Who to call for an animal emergency in India | animal emergency in india | animal rescue helpline, 1962 mobile veterinary unit | Which number: lead sentence added (from the guide's own table) | AC –; "animal helpline" T 59, "1962 helpline" AC Y |
| ABC Rules 2023 explained | abc rules 2023 | animal birth control rules 2023, community dogs relocation | What the rules are | AC Y (+"pdf"); GSC "abc rules 2023 pdf" 6 |
| Reporting illegal pet shops and breeders | illegal pet shops | pet shop rules 2018, dog breeding rules 2017 | Must they be registered? Yes | AC –; "pet shop rules 2018" AC Y, GSC 17 |
| Animal welfare: frequently asked questions | animal welfare faq | can community dogs be removed, is feeding stray dogs legal | (each question answered directly) | AC – |
| Living alongside animals | living alongside animals | dog bite first aid, human animal conflict | Most conflicts can be prevented | AC –; "dog bite first aid" AC Y |
| Feeding community animals: good practice | feeding community animals | what to feed stray dogs, feeding stray cats | Why regular clean feeding matters | AC –; "what to feed stray dogs (in india)" AC Y; GSC page 13 |
| Adopting a dog or cat | adopting a dog | adopt a cat, indie dog adoption | What adoption involves (10 to 15 years) | AC Y |
| Animal welfare laws in India, in plain words | animal welfare laws | pca act 1960, section 325 bns animals | A map of the laws | AC Y ("in india" top); "pca act 1960" AC Y |
| Helping an injured animal | helping an injured animal | injured dog first aid, injured animal rescue | Act calmly; steps follow | AC –; "injured animal rescue" AC Y |
| How to report animal cruelty | report animal cruelty | animal cruelty complaint india | Cruelty is an offence under the PCA Act and BNS | AC Y; GSC page 336 impr, pos about 5 |
| What is Animal Birth Control (ABC)? | animal birth control | abc programme, dog sterilisation india | ABC is the method India uses by law | AC Y; GSC page 323 impr, pos about 5 |
| Caring for community dogs in your area | caring for community dogs | community dog caregiver, stray dog vaccination | Caregivers make ABC work | AC –; "stray dog vaccination" AC Y; GSC page 6 |

## 6. Key pages

| Page | Primary keyword | Intent | Note |
|---|---|---|---|
| Home | animal welfare in india (secondary: animal welfare news, animal welfare ngo directory; was "animal welfare home") | N/I | The owner's hero copy is not changed. Demand check 15 Oct 2026: kept. "animal welfare india" scores higher (T 14 against 4), but its related queries are the Animal Welfare Board of India, and Google treats the two phrasings as one. "animal welfare in india" is suggested by autocomplete and its results page is informational. "animal welfare ngo directory" has no autocomplete. |
| About | about animal writes | N | The owner's wording |
| Contact | contact us | N/T | The owner's wording |
| Volunteer | register as volunteer | T | The owner's wording |
| Claim / Report | claim this listing / report a mistake | T | Report and thank-you are noindex |
| Corrections, policies | as set | N | The owner's wording |

## 7. Cannibalisation watch

- **"human elephant conflict" (13 items) and "human wildlife conflict" (13):**
  - mostly News and research, which is natural for a news archive;
  - the Wildlife topic owns the broad term;
  - items keep their specific phrases.
- **"animal welfare laws":**
  - the guide (7600) owns it;
  - the Library hub owns "animal laws in india";
  - the Laws and Rules topic owns "animal protection rules india".
- **"feeding community dogs":**
  - guide 9028 (law) owns it;
  - 7602 owns "feeding community animals";
  - 129 owns "caring for community dogs".
- **"animal birth control":**
  - guide 128 and the topic share it;
  - the topic page links to the guide;
  - the ABC Rules guide (9032) owns "abc rules 2023".

## 8. Demand gaps (15 Oct 2026)

Details and figures are in `docs/seo/keyword-demand-2026-10-15.md`. These are suggestions only; nothing on the site was changed.

1. **District helpline and rescue numbers.**
   - "animal helpline number {district}" and "animal rescue {district} contact number": about 240 impressions, positions 7–11, 0 clicks.
   - Consider them as secondaries on district Directory pages.
2. **Campaign petition searches.**
   - "petition campaign" 113, "letter petition" 48, "stray dog petition india" 14, "letter petition supreme court" 12.
   - They land on Home. "animal welfare campaigns" (the /campaigns/ primary) has no Trends demand.
   - Consider "stray dog petition" as the Campaigns primary.
3. **World Animal Day (about 1,060 impressions on a Library notification).** There is no lasting page for the day. It is seasonal, every October.
4. **1962 questions.**
   - People also ask: "Is 1962 free?", "Why is the animal ambulance number 1962?"
   - Mobile veterinary unit by state: 14 + 9 + 6 impressions.
   - Add them to the "Who to call" guide's FAQ.
5. **Rules searched with "pdf".** "pet shop rules 2018 pdf", "transport of animals rules 1978 pdf", "abc rules 2023 pdf", "pca act 1960 pdf". The Library items should link the official PDF and say "PDF".
6. **"street dogs".**
   - "street dogs" has T 96, against "community dogs" 11; "street dogs in india" is the top related query.
   - Consider it as a Community Dogs secondary.
7. **"gaushala": T 383, the largest term in the field.** Rajasthan, Haryana, Punjab and Delhi lead, and we hold 2,047 listings. Gaushala state and type-in-place pages are the biggest local opportunity.
8. **"government veterinary hospital {city}" and "veterinary hospital" (T 315).** These outrank "veterinary care india" (T 0, no autocomplete) for the Veterinary Care topic.
9. **Feeding legality phrased as "stray dogs".**
   - "is feeding stray dogs illegal in india", "awbi guidelines for feeding stray dogs", "feeding stray dogs in housing society", "supreme court stray dogs order/judgement".
   - Our feeding guides say "community dogs", and Search Console shows 1 impression.
10. **Animal Welfare Board of India.**
    - Signals: T 9, the top autocomplete for "animal welfare", a recruitment breakout, Wikipedia 664 views a month.
    - No page targets it.

