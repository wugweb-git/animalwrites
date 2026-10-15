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

**Intent codes.**
- **I** informational
- **N** navigational
- **C** commercial investigation (choosing an organisation)
- **T** transactional or local (contacting, applying, finding near me)

## 1. Section hubs (7 hub Pages)

| Page | Primary keyword | Secondary keywords | Intent | Search title, page 1 (built by aw-seo-extras; a copy is kept in Rank Math) |
|---|---|---|---|---|
| /news/ | animal welfare news | animal welfare news india, stray dogs news, animal cruelty news | I | Animal Welfare News from India – Animal Writes |
| /library/ | animal laws in india | animal welfare court orders, animal welfare rules, supreme court stray dogs judgment | I | Animal Law, Court Orders & Rules in India – Animal Writes |
| /resources/ | animal welfare guides | how to report animal cruelty, injured animal help, feeding community dogs law | I | Animal Welfare Guides & Research in India – Animal Writes |
| /directory/ | animal welfare ngos in india | animal ngo near me, animal shelter, gaushala, government veterinary hospital | C/T | Animal Welfare NGOs in India: Directory of 5,000+ Groups |
| /opportunities/ | animal welfare volunteer | animal welfare jobs india, animal welfare internship, foster animals | T | Animal Welfare Volunteer, Internship & Jobs in India |
| /campaigns/ | animal welfare campaigns | letter petition supreme court, community animals campaign | I/N | (WordPress title of the Page) |
| /media/ | animal writes in the news | letter petition campaign coverage | N | Animal Writes in the News |

## 2. Directory (programmatic; titles built live, never stored)

| Page type | Primary keyword pattern | Secondary keyword patterns | Intent | Title rule (aw-seo-extras, 60 characters) |
|---|---|---|---|---|
| State (`/directory/{state}/`) | animal welfare ngos in {state} | animal shelters in {state}, gaushalas in {state}, animal rescue {state} | C/T | Animal Welfare NGOs & Shelters in {State} – Animal Writes, or the longest of its shorter forms that fits |
| District (`/directory/{state}/{district}/`) | animal ngo in {district} | animal rescue {district}, animal shelter {district}, vet in {district} | T (local) | Animal Welfare NGOs in {District}, {State}: Rescue, Shelters, Vets, or the longest shorter form that fits |
| Type in a place (`/directory/{state}/{district}/{type}/`) | {type} in {district} | {type} near me, {type} {state} | T (local) | {Type} in {District} – Animal Writes |
| Filtered list (`?state=&type=`) | {type} in {state} | (none) | T | {Type} in {State} – Animal Writes; canonical rules as set in Phase 1 |
| Listing (`/directory/org/{slug}/`) | {organisation name} | {organisation name} contact, {organisation name} {district} | N/T | {Name}, {District} – Animal Writes |

Type vocabulary that people search for, and the listing type behind each:
- gaushala → Gaushala (2,047)
- veterinary hospital or vet → Veterinary Hospital or Dispensary (928)
- 1962 → Mobile Veterinary Unit (28)
- ABC centre or dog sterilisation → ABC Centre (65)
- wildlife rescue → Wildlife Rescue (60)
- animal ngo → Animal Welfare Organisation (3,891)

The thin-page rules from Phase 1 still apply: thin district × type pages stay out of the index.

## 3. Topics (18 topic archives plus /topics/)

| Topic | Primary keyword | Secondary keywords | Intent |
|---|---|---|---|
| /topics/ | animal welfare topics | animal welfare issues in india | I |
| Community Dogs | stray dogs in india | community dogs, street dogs law | I |
| Animal Birth Control | animal birth control | abc programme, dog sterilisation | I |
| Courts and Judgments | animal welfare court judgments | supreme court stray dogs | I |
| Cruelty and Prosecution | animal cruelty cases | animal cruelty law india | I |
| Laws and Rules | animal protection rules india | pca act rules | I |
| Policy and Government | animal welfare policy india | animal husbandry department | I |
| Wildlife | wildlife rescue india | human wildlife conflict | I |
| Cattle and Gaushalas | gaushalas in india | cattle welfare | I/C |
| Campaigns and Civic Action | animal welfare civic action | animal rights campaigns | I |
| Pets, Breeding and Pet Shops | pet shops and breeders | pet shop rules | I |
| Veterinary Care | veterinary care india | animal hospital | I/T |
| Feeders and Caregivers | stray animal feeders | community dog caregivers | I |
| Performing and Captive Animals | captive animals india | performing animals rules | I |
| Animal Transport and Slaughter | animal transport rules | cattle transport law | I |
| Rabies and Public Health | rabies in india | dog bite rabies | I |
| Disaster and Emergency Rescue | animal disaster rescue | flood animal rescue | I |
| Adoption | animal adoption india | adopt indie dog | T |
| Birds | bird rescue india | injured bird help | I/T |

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

| Guide | Primary (unchanged) | Secondary added | Main question the first paragraph answers |
|---|---|---|---|
| How to file a police complaint (FIR) for animal cruelty | file a police complaint | fir for animal cruelty, animal cruelty complaint | Can I report cruelty to the police? Yes; serious cases must be registered as an FIR |
| Feeding community dogs: what the law says | feeding community dogs | is feeding stray dogs legal, feeding stray dogs law | Is feeding legal? Yes; the rules are about where and how |
| RWA or society stopping you from feeding dogs | society stopping feeding dogs | rwa stray dog feeding, feeding spot abc rules | What to do (steps follow) |
| Injured animal emergency: who to call first | injured animal emergency | 1962 helpline, injured dog help | Who to call: lead sentence added (from the guide's own steps) |
| Who to call for an animal emergency in India | animal emergency in india | animal rescue helpline, 1962 mobile veterinary unit | Which number: lead sentence added (from the guide's own table) |
| ABC Rules 2023 explained | abc rules 2023 | animal birth control rules 2023, community dogs relocation | What the rules are |
| Reporting illegal pet shops and breeders | illegal pet shops | pet shop rules 2018, dog breeding rules 2017 | Must they be registered? Yes |
| Animal welfare: frequently asked questions | animal welfare faq | can community dogs be removed, is feeding stray dogs legal | (each question answered directly) |
| Living alongside animals | living alongside animals | dog bite first aid, human animal conflict | Most conflicts can be prevented |
| Feeding community animals: good practice | feeding community animals | what to feed stray dogs, feeding stray cats | Why regular clean feeding matters |
| Adopting a dog or cat | adopting a dog | adopt a cat, indie dog adoption | What adoption involves (10 to 15 years) |
| Animal welfare laws in India, in plain words | animal welfare laws | pca act 1960, section 325 bns animals | A map of the laws |
| Helping an injured animal | helping an injured animal | injured dog first aid, injured animal rescue | Act calmly; steps follow |
| How to report animal cruelty | report animal cruelty | animal cruelty complaint india | Cruelty is an offence under the PCA Act and BNS |
| What is Animal Birth Control (ABC)? | animal birth control | abc programme, dog sterilisation india | ABC is the method India uses by law |
| Caring for community dogs in your area | caring for community dogs | community dog caregiver, stray dog vaccination | Caregivers make ABC work |

## 6. Key pages

| Page | Primary keyword | Intent | Note |
|---|---|---|---|
| Home | animal welfare in india (secondary: animal welfare news, animal welfare ngo directory; was "animal welfare home") | N/I | The owner's hero copy is not changed |
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
