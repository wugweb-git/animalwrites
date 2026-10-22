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
- **Rank and Priority columns (added 21 Oct 2026).** The data is in `docs/seo/rankings-2026-10-21.csv`, and the demand is in `docs/seo/keyword-demand-2026-10-21.md`.
  - **Rank** is the position in real Google, India (`gl=in&hl=en&pws=0`), checked 22 Oct 2026 for the first 50 queries. Positions count organic results on page 1 only. "not top 10" means animalwrites.in was not on page 1.
  - Google showed a CAPTCHA on query 51, so the sweep stopped there. For the other queries, Rank gives the Search Console 28-day average position ("GSC …") or says "not checked". Re-run the same list monthly; the CSV has a date column.
  - **Priority:**
    - **P1:** high demand (T ≥ 20, or GSC ≥ 50 impressions in 28 days), and we either rank 4–20 or have a page that fits.
    - **P2:** high demand, but no page yet (a content gap).
    - **P3:** low demand, or already in the top 3.

**Intent codes.**
- **I** informational
- **N** navigational
- **C** commercial investigation (choosing an organisation)
- **T** transactional or local (contacting, applying, finding near me)

## 1. Section hubs (7 hub Pages)

| Page | Primary keyword | Secondary keywords | Intent | Search title, page 1 (built by aw-seo-extras; a copy is kept in Rank Math) | Demand (15 Oct 2026) | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|---|---|
| /news/ | animal welfare news | animal welfare news india, stray dogs news, animal cruelty news | I | Animal Welfare News from India, Summarised – Animal Writes | T 5, rising +1,500% ("animal news" T 136); AC Y; GSC hub 30 impr | #5 (with a filtered URL, /news/?publisher=…) | P3 |
| /library/ | animal laws in india | animal welfare court orders, animal welfare rules, supreme court stray dogs judgment | I | Animal Laws in India: Court Orders, Rules – Animal Writes | T 2 (AC Y, top "pdf"); "supreme court stray dogs" T 16; GSC hub 48 impr, pos 4.3; rules by name about 70 | not top 10 | P3 |
| /resources/ | animal welfare guides | how to report animal cruelty, injured animal help, feeding community dogs law | I | Animal Welfare Guides, FAQs and Research – Animal Writes | T 0, AC – (secondaries AC Y: "how to report animal cruelty in india" is top); GSC hub 20 impr | #2 (with /resources/?tag=guide) | P3 |
| /directory/ | animal welfare ngos in india | animal ngo near me, animal shelter, gaushala, government veterinary hospital | C/T | Animal Welfare NGOs in India by District – Animal Writes | AC Y; "animal ngo" T 28 ("near me" leads); "gaushala" T 383; GSC hub 17, place pages 2,540 impr, pos about 8 | #4 | P1 |
| /opportunities/ | animal welfare volunteer | animal welfare jobs india, animal welfare internship, foster animals | T | Animal Welfare Volunteer Roles and Jobs – Animal Writes | T 0, AC Y ("volunteering near me"); "ngo jobs" T 67; "animal welfare jobs india" AC Y; GSC hub 0 | not top 10 | P3 |
| /campaigns/ | stray dog petition | letter petition supreme court | I/N | Stray Dog Petition and Citizen Campaigns – Animal Writes | T 0, AC Y; GSC: "petition campaign" 113, "letter petition" 48, "stray dog petition india" 14 (all on Home); hub 18 | not top 10 (change.org, sci.gov.in, thehindu) | P3 |
| /media/ | letter petition campaign | animal writes in the news | N | Letter Petition Campaign: Press Coverage – Animal Writes | AC –, T 0; brand-only (GSC "nodogsnovote" 605 on Home) | #1 (with /campaigns/animal-writes-1-0/); /media/ now 301s to /press-coverage/ | P3 |

## 2. Directory (programmatic; titles built live, never stored)

| Page type | Primary keyword pattern | Secondary keyword patterns | Intent | Title rule (aw-seo-extras, 60 characters) | Demand (15 Oct 2026) | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|---|---|
| State (`/directory/{state}/`) | animal welfare ngos in {state} | animal shelters in {state}, gaushalas in {state}, animal rescue {state} | C/T | Animal Welfare NGOs & Shelters in {State} – Animal Writes, or the longest of its shorter forms that fits | AC mostly – for states (Google suggests cities); GSC state pages about 1,400 impr, pos 8–10 | #1 Kerala, Maharashtra, UP, Karnataka; #2 Rajasthan | P3 |
| District (`/directory/{state}/{district}/`) | animal ngo in {district} | animal rescue {district}, animal shelter {district}, vet in {district} | T (local) | Animal Welfare NGOs in {District}, {State}: Rescue, Shelters, Vets, or the longest shorter form that fits | AC Y for big cities (Delhi, Noida, Dehradun, Pune, Bangalore); GSC strongest pattern is "animal helpline number {district}" / "animal rescue {district} contact number", about 240 impr, 0 clicks | not top 10 in any of the 10 cities ("animal ngo in {city}": Justdial, Petofy and the NGOs' own sites lead) | P1 |
| Type in a place (`/directory/{state}/{district}/{type}/`) | {type} in {district} | {type} near me, {type} {state} | T (local) | {Type} in {District} – Animal Writes | AC Y ("gaushala in jaipur", "government veterinary hospital {city}"); GSC "jhajjar gaushala" 22 | "veterinary hospital in pune" and "government veterinary hospital delhi": not top 10; gaushala-in-city checks blocked | P1 |
| Filtered list (`?state=&type=`) | {type} in {state} | (none) | T | {Type} in {State} – Animal Writes; canonical rules as set in Phase 1 | n/a (canonicalised) | n/a (but /directory/?type=gaushala and ?type=veterinary-hospital collect about 50 impressions each) | – |
| Listing (`/directory/org/{slug}/`) | {organisation name} | {organisation name} contact, {organisation name} {district} | N/T | {Name}, {District} – Animal Writes | GSC 2,144 impr over 367 listings; "{name} contact number" and "{name} reviews" appear | GSC positions 8–12 for named quick wins (Blue Cross Pondicherry 10.1, SANS 11.3, Animotel 10.0, Daya 11.9) | P1 |

Type vocabulary that people search for, and the listing type behind each:
- gaushala → Gaushala (2,047)
- veterinary hospital or vet → Veterinary Hospital or Dispensary (928)
- 1962 → Mobile Veterinary Unit (28)
- ABC centre or dog sterilisation → ABC Centre (65)
- wildlife rescue → Wildlife Rescue (60)
- animal ngo → Animal Welfare Organisation (3,891)

The thin-page rules from Phase 1 still apply: thin district × type pages stay out of the index.

## 3. Topics (18 topic archives plus /topics/)

| Topic | Primary keyword | Secondary keywords | Intent | Demand (15 Oct 2026) | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|---|
| /categories/ | animal welfare categories | types of animal welfare | I | Google India autocomplete suggests "animal welfare categories" itself, and "types of animal welfare" (15 Oct 2026); replaces the earlier keyword with the banned word | not top 10 | P3 |
| Community Dogs | stray dogs in india | community dogs, street dogs law | I | "stray dogs" T 171 (part anime), "street dogs" T 96, "community dogs" T 11; AC Y; GSC topic 3 | not top 10 | P1 |
| Animal Birth Control | animal birth control | abc programme, dog sterilisation | I | AC Y ("rules 2023" top); T about 0; GSC 41 impr pos 5 (guide) | #4 (the guide) | P1 |
| Courts and Judgments | animal welfare court judgments | supreme court stray dogs | I | AC –; "supreme court stray dogs" T 16, AC Y | #2 | P3 |
| Cruelty and Prosecution | animal cruelty cases | animal cruelty law india | I | AC Y ("cases in india"); "animal cruelty" T 25; GSC cruelty queries 41 impr | not top 10 | P1 |
| Laws and Rules | animal protection rules india | pca act rules | I | AC – ("animal protection laws india" is suggested); "animal laws" T 14; GSC rules by name about 70 | not top 10 | P3 |
| Policy and Government | animal welfare policy india | animal husbandry department | I | AC –, T 0; "animal husbandry department" AC Y, T 304 (broad) | not top 10 | P3 |
| Wildlife | wildlife rescue india | human wildlife conflict | I | AC ext (mostly Indiana); T 0; "human wildlife conflict" AC Y, T 6 | not top 10 | P3 |
| Cattle and Gaushalas | gaushalas in india | cattle welfare | I/C | AC Y; "gaushala" T 383 | not top 10 | P1 |
| Campaigns and Civic Action | animal welfare civic action | animal rights campaigns | I | AC –; "animal rights campaigns" AC Y | #1 | P3 |
| Pets, Breeding and Pet Shops | pet shops and breeders | pet shop rules | I | AC –; "pet shop rules" AC Y; GSC "pet shop rules 2018" 17 | not top 10 | P3 |
| Veterinary Care | veterinary care india | animal hospital | I/T | AC –, "veterinary care" T 40; "veterinary hospital" T 315; "animal hospital" AC Y | not top 10 | P3 (demand sits in the Directory type; see section 9) |
| Feeders and Caregivers | stray animal feeders | community dog caregivers | I | AC Y (exact only); T 0 | not top 10 | P3 |
| Performing and Captive Animals | captive animals india | performing animals rules | I | AC –; "performing animals rules" AC ext (1973/2001); GSC 4 | #3 | P3 |
| Animal Transport and Slaughter | animal transport rules | cattle transport law | I | AC Y ("1978"); GSC "transport of animals rules 1978" about 26; "cattle transport" T 2 | not top 10 | P3 |
| Rabies and Public Health | rabies in india | dog bite rabies | I | AC Y; "rabies" T 1,375 (health intent) | not top 10 | P1 |
| Disaster and Emergency Rescue | animal disaster rescue | flood animal rescue | I | AC Y (exact only); T 4 | #3 | P3 |
| Adoption | animal adoption india | adopt indie dog | T | AC Y (Indiana noise); "dog adoption" T 67, "adopt a dog" T 7 | not checked (CAPTCHA) | P1 |
| Birds | bird rescue india | injured bird help | I/T | AC Y; "bird rescue" T 25; "injured bird helpline" AC Y | not checked (CAPTCHA) | P1 |

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

| Guide | Primary (unchanged) | Secondary added | Main question the first paragraph answers | Demand (15 Oct 2026) | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|---|
| How to file a police complaint (FIR) for animal cruelty | file a police complaint | fir for animal cruelty, animal cruelty complaint | Can I report cruelty to the police? Yes; serious cases must be registered as an FIR | AC Y (generic); "fir for animal cruelty" AC Y | GSC page 4.3 (213 impressions) | P1 |
| Feeding community dogs: what the law says | feeding community dogs | is feeding stray dogs legal, feeding stray dogs law | Is feeding legal? Yes; the rules are about where and how | AC Y (weak); "is feeding stray dogs illegal in india" AC Y; GSC 1 | not checked | P3 |
| RWA or society stopping you from feeding dogs | society stopping feeding dogs | rwa stray dog feeding, feeding spot abc rules | What to do (steps follow) | AC –; "feeding stray dogs in housing society" AC Y; GSC page 7 | not checked | P3 |
| Injured animal emergency: who to call first | injured animal emergency | 1962 helpline, injured dog help | Who to call: lead sentence added (from the guide's own steps) | AC Y; "injured animal helpline number" AC Y; GSC 121 (old URL) | GSC page 6.2 (244 impressions, old /knowledge/ URL) | P1 |
| Who to call for an animal emergency in India | animal emergency in india | animal rescue helpline, 1962 mobile veterinary unit | Which number: lead sentence added (from the guide's own table) | AC –; "animal helpline" T 59, "1962 helpline" AC Y | GSC page 3.5 (395 impressions) | P1 |
| ABC Rules 2023 explained | abc rules 2023 | animal birth control rules 2023, community dogs relocation | What the rules are | AC Y (+"pdf"); GSC "abc rules 2023 pdf" 6 | not checked; Library item GSC 9.3 (55) | P1 |
| Reporting illegal pet shops and breeders | illegal pet shops | pet shop rules 2018, dog breeding rules 2017 | Must they be registered? Yes | AC –; "pet shop rules 2018" AC Y, GSC 17 | not checked | P3 |
| Animal welfare: frequently asked questions | animal welfare faq | can community dogs be removed, is feeding stray dogs legal | (each question answered directly) | AC – | not checked | P3 |
| Living alongside animals | living alongside animals | dog bite first aid, human animal conflict | Most conflicts can be prevented | AC –; "dog bite first aid" AC Y | not checked | P3 |
| Feeding community animals: good practice | feeding community animals | what to feed stray dogs, feeding stray cats | Why regular clean feeding matters | AC –; "what to feed stray dogs (in india)" AC Y; GSC page 13 | not checked | P3 |
| Adopting a dog or cat | adopting a dog | adopt a cat, indie dog adoption | What adoption involves (10 to 15 years) | AC Y | not checked | P1 |
| Animal welfare laws in India, in plain words | animal welfare laws | pca act 1960, section 325 bns animals | A map of the laws | AC Y ("in india" top); "pca act 1960" AC Y | not checked | P3 |
| Helping an injured animal | helping an injured animal | injured dog first aid, injured animal rescue | Act calmly; steps follow | AC –; "injured animal rescue" AC Y | not checked | P3 |
| How to report animal cruelty | report animal cruelty | animal cruelty complaint india | Cruelty is an offence under the PCA Act and BNS | AC Y; GSC page 336 impr, pos about 5 | GSC page 4.8–5.5 (585 impressions, two URLs) | P1 |
| What is Animal Birth Control (ABC)? | animal birth control | abc programme, dog sterilisation india | ABC is the method India uses by law | AC Y; GSC page 323 impr, pos about 5 | GSC page 5.8–6.9 (615 impressions, two URLs) | P1 |
| Caring for community dogs in your area | caring for community dogs | community dog caregiver, stray dog vaccination | Caregivers make ABC work | AC –; "stray dog vaccination" AC Y; GSC page 6 | not checked | P3 |

## 6. Key pages

| Page | Primary keyword | Intent | Note | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|
| Home | animal welfare in india (no secondaries since 15 Oct 2026: "animal welfare news" belongs to /news/ and "animal welfare ngo directory" to /directory/; was "animal welfare home") | N/I | The owner's hero copy is not changed. Demand check 15 Oct 2026: kept. "animal welfare india" scores higher (T 14 against 4), but its related queries are the Animal Welfare Board of India, and Google treats the two phrasings as one. "animal welfare in india" is suggested by autocomplete and its results page is informational. "animal welfare ngo directory" has no autocomplete. | not top 10 | P3 |
| About | animal writes (secondary: citizen letter campaign) | N | The brand is only the title suffix, so the brand query is the keyword | #1 | P3 |
| Contact | contact us | N/T | The owner's wording | – | – |
| Volunteer | register as a volunteer | T | The page's own heading | – | – |
| Claim / Report | claim this listing / report a mistake | T | Claim, report and thank-you are noindex | – | – |
| Corrections, policies | as set | N | The owner's wording | – | – |

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

### Conflicts found 21 Oct 2026 (Rank Math focus keyword, first phrase, published items, listings left out)

Read from the live database with `wp db query` on 22 Oct. The rule stays: one primary keyword per page. Only conflicts on evergreen pages are listed. News items sharing a phrase ("stray dogs" ×16, "supreme court" ×13, "human elephant conflict" ×17, "human wildlife conflict" ×16) are normal for a news archive, and the topics own the broad terms.

| Primary keyword | Pages that share it | Which should own it | Fix (proposal) |
|---|---|---|---|
| world animal day | guide #13797 /resources/world-animal-day-4-october/; Library #142 (AWBI notice, 2026); 2 news items (#81, #7484) | the guide (evergreen) | Library #142 → "world animal day 2026 notice" (or "awbi world animal day circular"); news → "bollywood world animal day". Google ranks the Library notice today (1,548 impressions, position 8–8.4), not the guide; add a link from the notice to the guide. |
| animal birth control | guide /resources/animal-birth-control-abc-explained/; Library /library/animal-birth-control-rules-2023/ (aw_legal); 2 news items | the guide | Library item → "abc rules 2023" (it is the rules text), which also matches the "abc rules 2023 pdf" searches. The ABC Rules guide #9032 then needs "abc rules 2023 explained" so the two do not collide. |
| animal welfare board of india | guide #13800 /resources/animal-welfare-board-of-india-awbi-explained/; 2 Library notices | the guide | Library notices → their own subject ("awbi public notice misuse of name", "awbi illegal transport letter"). |
| letter petition campaign | the /media/ hub (now /press-coverage/); 5 press items | the hub or /campaigns/animal-writes-1-0/ (Google ranks the 1.0 campaign page #1) | Press items → their headline phrase. Decide whether the hub keeps "letter petition campaign" now that /media/ redirects to /press-coverage/. |
| community dogs | Library /library/supreme-court-judgment-of-22-august-2025-on-community-dogs-2025-insc-1018/; 1 research item; 1 press item | the Community Dogs topic (secondary) | Library → "supreme court community dogs judgment 2025". |
| dog bite / dog bites | guide #17307 ("dog bite"); 9 research items ("dog bites") | the guide | Research items → "dog bites {place}" (most already name a place; 9 use the bare phrase). |
| rabies vaccine | 9 research items | the dog-bite guide #17307 as a secondary | Research items → "rabies vaccine completion {place}" and similar. |
| world rabies day | Library notice (2026) and 2 news items | a future observance page (P2) | When the page exists, the Library notice gets "world rabies day 2026 notice". |

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


## 9. New rows (20–21 Oct 2026 work) and the 21 Oct priorities

Demand and figures: `docs/seo/keyword-demand-2026-10-21.md`. Rank: `docs/seo/rankings-2026-10-21.csv`. GSC is the 28 days to 21 Oct.

| Page | Primary keyword | Secondary keywords | Intent | Demand (21 Oct 2026) | Rank (21 Oct) | Priority |
|---|---|---|---|---|---|---|
| What to do after a dog bite in India (#17307, /resources/dog-bite-rabies-vaccine/) | dog bite | what to do after dog bite, rabies vaccine schedule, dog bite injection | I/T | "dog bite" T 430, "rabies vaccine" T 426; AC Y for schedule, injection price, category 1/2/3, "no blood" and food myths, with a full Hindi set; Bing confirms | page live 21 Oct: 62 impressions, position 6.4; "dog bite" page 1 has Wikipedia, MNT, WebMD, NIH, WHO and a hospital, not us | **P1** |
| NGO Darpan registration (#17308, /resources/ngo-darpan-registration/) | ngo darpan registration | ngo darpan, darpan id search, ngo darpan certificate | I/T | T 87; AC Y (online, fees, documents, certificate, Hinglish "kaise kare"); Bing confirms | page 70 impressions, position 5.4 ("darpan id search" forms) | **P1** |
| 1962 helpline (#13798, /resources/1962-helpline-mobile-veterinary-units/) | 1962 helpline number | mobile veterinary unit, animal ambulance number, 1962 helpline number {state} | T | AC Y (mp, up, rajasthan, gujarat, timing, "is free or not", "kya hai"); GSC about 200 impressions on 1962 queries | page 974 impressions, position 6.9; queries 8.8–11.6 | **P1** |
| CPCSEA full form (#13868) | cpcsea full form | ccsea full form, cpcsea | I | GSC "cpcsea full form" 206, "cpcsea-mef@gov.in" 228, "ccsea" 498 | page 1,041 impressions, position 6.2; query 8.5 | **P1** |
| AWBI explained (#13800) | animal welfare board of india | awbi, awbi circular | I/N | "awbi" 129 + "animal welfare board of india" 71 in GSC; AC Y (recruitment, chairman, email id, upsc) | page 408 impressions, position 6.6 | **P1** |
| Draft lab animal rules (campaign + Library + 7 resources) | g.s.r. 864(e) (Library item) | draft lab animal rules 2026, animal experimentation rules 2026 | I/N | GSC cluster 3,795 impressions, 212 clicks | #1 for "lab animal rules 2026"; "gsr 864 e" position 4.3 | **P1** until the campaign closes |
| World Animal Day (#13797, /resources/world-animal-day-4-october/) | world animal day | world animal day 2026, 4 october day | I | T 25 (peaks in October); GSC about 1,160 impressions on the cluster | the Library notice ranks instead (position 8–8.4); see the conflict table in section 7 | **P1** (seasonal: be ready by September 2027) |
| Injured bird helplines by city (#13843) | injured bird helpline | bird rescue helpline number {city} | T | AC Y (near me, number, ahmedabad, jaipur, mumbai, delhi, vadodara); "bird rescue" T 25 | page 338 impressions, position 7.2; "bird rescue helpline number mumbai" 10.0 | **P1** |
| Supreme Court stray dog orders explained (#13842) | supreme court stray dogs judgment | stray dogs supreme court order, next hearing | I | "supreme court stray dogs" 53 within "stray dogs" related queries (Trends 15 Oct: T 16); AC Y for judgement, order, next hearing, "today" | page 84 impressions, position 4.9; head query not checked (CAPTCHA) | P3 (T < 20); watch, it is live news |
| District pages, helpline pattern | animal helpline number {district} (secondary on district pages) | animal rescue {district} contact number, 1962 {state} | T | GSC: Bareilly 27, Gwalior 25, Jammu 30, Dehradun 19, Alappuzha 19, Azamgarh 18 (positions 7.7–13); AC Y for Ahmedabad, Rajkot, Delhi, Surat, Bhubaneswar, Bhavnagar, Jamnagar | live check of 10 big cities stopped by CAPTCHA | **P1** |
| Animal ambulance number {state} | animal ambulance number {state} | 1962 {state} | T | AC Y (delhi, up, odisha, assam, mp, jaipur, bihar) | GSC: Karnataka 10 (1 impression) | P2 (no state page answers it; the 1962 guide could, by state) |
| Gaushala type pages | gaushala in {district} / gaushalas in {state} | gaushala near me, gaushala {city} contact number | T (local) | T 382; Rajasthan, Haryana, Punjab, Delhi, Chandigarh lead; "gaushala near me" 100 in related queries | "gaushalas in india" not top 10; /directory-type/gaushala/ (170 impressions) now 301s to /tag/gaushala/ | **P1** |
| Veterinary type naming | veterinary hospital in {district}; government veterinary hospital {city} | vet hospital, veterinary clinic | T (local) | **"veterinary hospital" T 318 vs "vet hospital" 58 (raw 84.7 vs 15.5); "veterinary clinic" 119 vs "vet clinic" 91.** Keep "Veterinary hospital" as the type label and in titles. Related: "government veterinary hospital" 95 | "veterinary hospital in pune" and "government veterinary hospital delhi": not top 10 (Justdial, Practo, delhi.gov.in) | **P1** |
| Animal days in India + 10 observance pages (`Claude outputs/pending.md`) | {day} (e.g. world rabies day, world wildlife day, world sparrow day) | {day} {year}, {day} theme, kab manaya jata hai | I | World Animal Day T 25; World Rabies Day T 4; AC Y for "{day} 2026", theme, poster, "kab manaya jata hai"; "world animal days list 2026" AC Y | no pages yet (World Animal Day exists) | P2 (no page yet); World Rabies Day, Wildlife Week and Animal Welfare Fortnight first |
| Library: PCA Act 1960 | pca act 1960 | pca act 1960 pdf, section 11 punishment | I | AC Y (pdf, in hindi, bare act, section 11, punishment) | not checked (CAPTCHA) | P1 (page fits; add "PDF" and a section 11 penalty summary) |
| Library: ABC Rules 2023 | abc rules 2023 | abc rules 2023 pdf, rule 20 | I | AC Y (pdf english / hindi, gazette, rule 20, upsc) | Library item GSC 9.3 (55 impressions, /research/ URL) | P1 |
| Library: Pet Shop Rules 2018 | pet shop rules 2018 | pet shop rules 2018 pdf | I | GSC 24 impressions, position 6.8 | GSC 6.8 | P3 |
| Library: Dog Breeding and Marketing Rules 2017 | dog breeding rules 2017 | – | I | GSC 13, position 7.9 | GSC 7.9 | P3 |
| Library: Transport of Animals Rules 1978 | transport of animals rules 1978 | pdf | I | GSC 6, position 5.3 | GSC 5.3 | P3 |
| Library: AWBI circulars (hub filter) | awbi circular | awbi circulars and advisories, awbi stray dog circular | I | AC Y (circulars, 2015, pet dog, stray dog, guidelines) | not checked | P3 (low volume) |

**Organisation profiles at positions 8–12 (quick wins).** Blue Cross Pondicherry 10.1 (52 impressions), SANS Animal Welfare Trust 11.3 (49 + 29 "reviews"), Animotel Care Trust "reviews" 10.0 (45), Bezubaan Saathi 10.7 (36), Daya Animal Welfare 11.9 (23 + 23), Blue Cross Vizag 8.4 (28), People for Animals Goa 10.8 (19), Pyaar Foundation Chandrapur 9.2 (19), Saahas for Animals 7.7 (114, new). Combined they are well over 50 impressions: **P1** as a group.
