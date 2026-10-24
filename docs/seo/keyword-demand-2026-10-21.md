# Keyword demand — Animal Writes, 21 October 2026

**History (snapshot of 21–22 Oct 2026); current state: docs/pending.md and docs/plugin-map.md.**

Data pulled 22 Oct 2026, mostly 14:00–17:00 IST, for the 21 Oct refresh. It follows `docs/seo/keyword-demand-2026-10-15.md` and uses the same codes (T, AC, GSC). The results feed the Rank and Priority columns in `docs/seo/keyword-map.md`. The rankings baseline is `docs/seo/rankings-2026-10-21.csv`. Nothing on the site was changed.

## Sources

| Source | Result |
|---|---|
| Google results (built-in browser, `gl=in&hl=en&pws=0`) | **Partly worked.** 50 of 130 queries checked: brand, hubs, 10 city "animal ngo in …" pages, 5 state pages, and 18 topics. **Google showed a CAPTCHA on query 51** ("animal adoption india"). Per the brief, the sweep stopped there. A retry about 40 minutes later was still blocked. The browser was signed in to a Google account, so `pws=0` turns off personalisation, but this is not a fully logged-out view. |
| Search Console (Site Kit, `sc-domain:animalwrites.in`) | Worked. 28 days, 24 Sep – 21 Oct 2026: by date, query (1,809 rows), page (1,926), query + page (2,249) and device. Also 15–21 Oct queries, compared against 17 Nov 2025 – 14 Oct 2026. |
| Google Trends (built-in browser, India, past 12 months, through the Trends explore API) | Worked. 3 comparisons, all anchored on "animal welfare" = 100. Related queries for 8 terms, and top states for dog bite, rabies vaccine and gaushala. |
| Google autocomplete (`hl=en` and `hl=hi`, `gl=in`), Bing autosuggest (en-IN), YouTube suggest | Worked. About 315 requests: 30 seeds, 10 Hindi seeds, a–z expansions of 8 seeds, Bing for 30 seeds, and YouTube for 7. None were rate-limited. See section E. |
| Rank Math Analytics (REST `an/dashboard`, `an/keywordsOverview`) | Worked. See the cross-check in section D. |
| Semrush MCP | No API units on the account. Not used. |
| Ahrefs | Not retried. It returned "Insufficient plan" for every Keywords Explorer and GSC call on 15 Oct. |

## A. Search Console, last 28 days (24 Sep – 21 Oct 2026)

| Measure | 28 days | 15 Oct report (11 months) |
|---|---|---|
| Impressions | **45,194** | 46,447 |
| Clicks | **1,672** | 20,589 (17,404 from the Nov 2025 campaign) |
| Average position (by day) | 4.7 to 6.4 | – |
| Impressions 15–21 Oct (7 days) | 33,895 (3× the previous 21 days put together: 11,299) | – |
| Clicks 15–21 Oct | 1,471 | – |
| Device (query rows) | mobile 82%, desktop 17%, tablet 1% | – |

**Anonymised queries.** The named-query rows cover 11,656 of the 45,194 impressions (26%) and 294 of the 1,672 clicks. Most traffic comes from long-tail queries that Google hides.

**Brand vs non-brand** (named queries only):
- Brand ("animal writes", "animal writer", "animalwrites", "nodogsnovote", "india writes for…"): 121 impressions and 5 clicks, about 1% of named impressions.
- Non-brand: 11,535 impressions and 289 clicks.
- This is the reverse of November 2025, when brand queries were 83% of named impressions. Growth now comes from topics, not the name.

**What is driving it (query clusters):**

| Cluster | Impressions | Clicks | Position | Main pages |
|---|---|---|---|---|
| Lab animal rules: "gsr 864 e", "g.s.r. 864(e)", "draft rules on animal experimentation 2026", "cpcsea…", "new animal testing laws" | 3,795 | 212 | 3.5–8.5 | /campaigns/lab-animals-rules-2026/ (5,101 impressions, 533 clicks, position 3.6), /library/draft-laboratory-animals-rules-2026/ (2,378 / 256), /resources/animal-testing-laws-india/, /resources/draft-lab-animal-rules-2026-rule-by-rule/, /resources/cpcsea-full-form-ccsea/ |
| World Animal Day: "world animal day 2026", "animal day 2026", "4 october 2026 day" | 1,163 | 3 | 7–12 | /legal-watch/… → /library/observance-of-world-animal-day-on-4th-october-2026/ (1,300 + 248). **Not** /resources/world-animal-day-4-october/ |
| Helplines: "1962 helpline number", "animal helpline number {city}", "animal rescue {city} contact number", "bird rescue helpline number mumbai" | 1,323 | 7 | 8–11 | /resources/1962-helpline-mobile-veterinary-units/ (974, position 6.9), district Directory pages, old /location/ URLs (301s) |
| AWBI: "awbi", "animal welfare board of india", "state animal welfare board west bengal" | 283 | 0 | 5.9–7.9 | /resources/animal-welfare-board-of-india-awbi-explained/ (408, position 6.6) |
| Gaushala: "jhajjar gaushala", "gau seva helpline number gwalior", named gaushalas | 337 | 0 | 8–11 | Gaushala profiles; /directory-type/gaushala/ (170 impressions, 301 → /tag/gaushala/) |
| Veterinary: "veterinary hospital chengannur", "mobile veterinary unit", "1962 veterinary ambulance" | 360 | 1 | 4–12 | profiles, /directory-type/mobile-veterinary-unit/ |
| Dog bite and rabies | 61 (no dog-bite query yet) | 0 | – | /resources/dog-bite-rabies-vaccine/ (#17307, live 21 Oct: 62 page impressions, position 6.4) |
| NGO Darpan | about 20 ("darpan id search") | 1 | 2–10 | /resources/ngo-darpan-registration/ (#17308: 70 page impressions, position 5.4) |

**Positions 8–20 with at least 10 impressions: 86 queries.** The largest are:

| Query | Impressions | Position | Lands on |
|---|---|---|---|
| world animal day 2026 | 468 | 8.8 | Library World Animal Day notice |
| cpcsea full form | 206 | 8.5 | /resources/cpcsea-full-form-ccsea/ |
| 4 october 2026 day / day on 4 october 2026 / 4th october 2026 day | 281 | 8–11 | Library World Animal Day notice |
| 1962 helpline number / 1962 animal helpline / 1962 helpline / timing / veterinary ambulance | 199 | 8.8–11.6 | 1962 guide |
| blue cross pondicherry | 52 | 10.1 | profile |
| world animal day | 52 | 12.3 | Library notice |
| sans animal welfare trust (+ reviews) | 78 | 11.1–11.3 | profile |
| animotel care trust reviews | 45 | 10.0 | profile |
| bezubaan saathi animal trust | 36 | 10.7 | profile |
| jhajjar gaushala | 31 | 9.0 | district × type page |
| animal rescue jammu contact number | 30 | 9.7 | /location/jammu/ (301) |
| blue cross vizag | 28 | 8.4 | profile |
| animal helpline number bareilly / gwalior | 52 | 10.7–10.9 | district pages |
| mobile veterinary unit | 27 | 11.6 | /directory-type/mobile-veterinary-unit/ |
| bird rescue helpline number mumbai | 26 | 10.0 | injured bird helplines guide |
| daya animal welfare (+ organization) | 46 | 11.9–12.6 | profile |
| animal cruelty complaint | 19 | 8.4 | report cruelty guide |
| animal rescue alappuzha / dehradun contact number | 38 | 8.3–13.1 | district pages |
| people for animals goa, pyaar foundation chandrapur | 19 each | 9.2–10.8 | profiles |

Some off-topic news queries are also at positions 8–20: "nauheed cyrusi children", "ronak choudhary age", "gaurav taneja dog", "romanchs law" and "priya kailad". They bring impressions, not readers, and need no action.

**New queries since 15 Oct.** 1,138 queries with 6,098 impressions appear in 15–21 Oct but not in the 17 Nov 2025 – 14 Oct 2026 rows. Site Kit returned only 959 rows for the long period, so some of these may be old long-tail. The real new clusters:
- G.S.R. 864(E) and lab animal rules: 1,325 + 262 + 105 + 50 impressions;
- CPCSEA/CCSEA and IAEC full forms;
- "awbi" (129) and "animal welfare board of india" (71);
- 1962 helpline (85 + 40 + 24 + 22 + 17);
- "saahas for animals" / "sahas pune" (150);
- "bird rescue helpline number mumbai" (26).

## B. Google Trends (India, 12 months to 22 Oct 2026, "animal welfare" = 100)

| Term | T (22 Oct) | T (15 Oct) | Note |
|---|---|---|---|
| dog bite | **430** | – | Steady all year (raw 27–37 each week). |
| rabies vaccine | **426** | – | A spike at New Year (Dec 28 week = 100 raw). |
| gaushala | **382** | 383 | Same as 15 Oct. |
| veterinary hospital | **318** | 315 | – |
| stray dogs | 175 | 171 | About half is the anime *Bungo Stray Dogs*. |
| veterinary clinic | 119 | – | – |
| vet clinic | 91 | – | – |
| ngo darpan | **87** | – | – |
| vet hospital | 58 | – | – |
| animal helpline number | 45 | "animal helpline" 59 | – |
| world animal day | 25 | – | A short spike every year in the first week of October. |
| world rabies day | 4 | – | – |

**Naming the veterinary type.** "veterinary hospital" scores 318 against "vet hospital" 58 (raw 84.7 vs 15.5, the 86 vs 16 seen on 20 Oct), so keep "Veterinary hospital" in titles and type labels. For clinics the two forms are close (119 vs 91), so "veterinary clinic" still leads.

**Related queries (top, then rising):**
- **dog bite:** rabies, dog bite injection, dog bite vaccine, rabies vaccine, dog bite treatment, injection for dog bite, dog bite symptoms. Rising: foods to avoid after dog bite +50%, category 2 dog bite +40%.
- **rabies vaccine:** vaccine for rabies, anti rabies vaccine, rabies vaccine dose, rabies vaccine schedule, rabies vaccine price, rabies vaccine india. Rising: abhayrab rabies vaccine news (breakout); the other rising terms are unrelated noise.
- **gaushala:** gaushala near me (100), gaushala road, gaushala delhi, gaushala management, gaushala in hindi, gaushala ludhiana. Rising: gaushala management system +70%, and named gaushalas (Sri Krishna, Shri Mataji, Gopeshwar Malihabad).
- **stray dogs:** supreme court stray dogs (53), stray dogs india, supreme court on stray dogs. Rising: telangana stray dogs +850%, welfare of stray dogs +110%.
- **ngo darpan:** ngo darpan portal, darpan registration, ngo registration, ngo darpan login, certificate, darpan id. Rising: ngo darpan list, npo darpan.
- **animal helpline number:** animal rescue helpline number, … near me, dog helpline number, … ahmedabad, government animal helpline number, … delhi, … rajkot, animal ambulance number. Rising: … bhopal +250%, … ahmedabad 24 hours +80%.
- **veterinary hospital:** government (100), near veterinary hospital, government veterinary hospital, veterinary hospital near me, govt veterinary hospital, veterinary hospital chennai. Rising: best veterinary hospital near me +90%.
- **world animal day:** world animal day 2026, when is world animal day, world animal day is celebrated on, world animal day date. Rising: world animal day 2026 +3,100%, world animal road accident awareness day +4,800%.

**Top states:**
- **dog bite:** Sikkim, Arunachal Pradesh, Manipur, Nagaland, Meghalaya, Puducherry.
- **rabies vaccine:** Arunachal Pradesh, Manipur, Nagaland, Tripura, Sikkim, Meghalaya.
- **gaushala:** Chandigarh, Rajasthan, Haryana, Punjab, Delhi.

Small states lead the dog-bite and rabies lists because Trends normalises by population. In absolute terms the big states still search most.

## C. AI and other surfaces (for context)

- **GA4, last 7 days:** chatgpt.com / ai-assistant brought 68 sessions (60 new users), and the "AI Assistant" channel had 74 sessions. ChatGPT is already sending readers.
- **Perplexity, 22 Oct (logged out):**
  - "draft lab animal rules 2026 india how to object": **cites animalwrites.in**.
  - "animal welfare directory india": cites awbi.gov.in, humaneworld.org and dahd.maharashtra.gov.in. **Not us.**
  - "1962 animal helpline number which states": cites bfil.co.in, support.guruspeak.in and indiatoday.in. **Not us.**
  - "how to report animal cruelty in india": the sources did not load, so this one is not recorded.

## D. Rank Math Analytics cross-check (30 days, data to 21 Oct)

| Measure | Rank Math | Search Console |
|---|---|---|
| Impressions | 2,222 (previous period 117) | 45,194 |
| Clicks | 24 | 1,672 |
| Average position | 8.98 | about 5–6 |
| Keywords | 560: 43 in the top 3, 83 in the top 10, 27 in the top 50, 2 in the top 100 | 1,809 named |

Rank Math (free) counts only the 270 posts it tracks, and its data starts on about 18 Sep. That leaves out the campaign page and most Directory and Library URLs, so it is **not a usable cross-check** for totals. Use Search Console.

## E. Search suggestions (Google en and hi, Bing en-IN, YouTube)

**Main findings:**
1. **Dog bite and rabies is the deepest cluster.** People ask about:
   - doses and timing: 0-3-7-28, "within how many hours", a missed dose, how long protection lasts;
   - severity categories 1/2/3, and "no blood / just a scratch";
   - a large food-myth cluster: "what not to eat after dog bite", and कुत्ते के काटने पर क्या नहीं खाना चाहिए ("what not to eat after a dog bite");
   - price, government hospitals and Jan Aushadhi;
   - vaccine brands: Abhayrab, Rabipur, Rabivax.

   The Hindi seed returns a complete FAQ set.
2. **"Near me" is the largest pattern, with 97 suggestions.** It comes with "government", "24 hours", "contact number", "for cow", "to feed cows", "for donation" and "to volunteer". These are Directory intents.
3. **Helplines are searched by city and state.** Gujarat leads (Ahmedabad, Rajkot, Surat, Bhavnagar, Jamnagar, Vadodara), then Odisha, MP, UP and Rajasthan. Common forms: "1962 helpline number mp / up / rajasthan / gujarat", "1962 … kya hai", "is free or not", "timing", and पशु हेल्पलाइन नंबर 24 घंटे चालू ("animal helpline number, open 24 hours").
4. **All 10 target cities appear in the suggestions.** Delhi leads with 26 and Kolkata is weakest with 3 (vets only). Suggestions use "bangalore", never "bengaluru". Strong cities outside the list: Gurgaon, Noida, Surat, Rajkot, Bhubaneswar, Indore and Bhopal, plus a long Kerala tail for veterinary hospitals.
5. **Law searches want the PDF and the penalty.** Examples: "abc rules 2023 pdf" (English, Hindi, gazette, rule 20), "pca act 1960 pdf / bare act / in hindi", and "section 11 … punishment". Some come from exam aspirants (UPSC angles on the ABC Rules and AWBI).
6. **Stray dogs and the Supreme Court is live news.** People search for the judgement, the order, the next hearing and "judgement today". आवारा कुत्ते ("stray dogs") is led by the SC ruling and by "आवारा कुत्ते पकड़ने का नंबर" (the number to call to catch strays).
7. **Hinglish is strong (51 suggestions).** Examples: "gaushala kaise khole" (how to open a gaushala), "kitna paisa milta hai" (how much money you get), "yojana" (government scheme), "ngo darpan registration kaise kare" (how to register), "id kaise banaye" (how to make the ID), "kab manaya jata hai" (when is it observed).
8. **YouTube.** "rabies patient" is the top suggestion for "rabies". There are Tamil, Telugu and Malayalam suggestions and rescue videos. Gaushala searches on YouTube are devotional.
9. **Bing agrees only in part.** It confirms NGO Darpan, dog bite injection, rabies vaccine and world rabies day (4–6 of 9 suggestions each). It matches nothing for "what to do after dog bite", "street dogs", "gaushala near me", "injured bird helpline" or "feeding stray dogs law", and returned nothing for "awbi circular".
10. **Caveat.** About 25 suggestions were for Dubai or the UAE, because the requests came from a non-Indian IP. The "how to file fir for animal cruelty" suggestions were all UAE and are not usable.

**Per seed (Google en-IN, top suggestions):**

| Seed | Top Google suggestions (en-IN) | Hindi or Hinglish additions | Bing confirms |
|---|---|---|---|
| dog bite | injection, treatment, category, injection price, vaccine, vaccine schedule, symptoms | डॉग बाइट इंजेक्शन ("dog bite injection") | yes (4/9) |
| what to do after dog bite | … to prevent rabies, no blood, injection, vaccine, child, with blood, home remedies | – | no |
| rabies vaccine | schedule, price, side effects, dose, near me, for dogs, name | ka asar kab tak rehta hai (how long its effect lasts), ki dose kitni hoti hai (how many doses), रेबीज वैक्सीन ("rabies vaccine") | yes |
| rabies vaccine schedule | for humans, calculator, india, price, in hindi, for child | govt of india | yes |
| dog bite injection | price, time, name, schedule, dose days (calculator), near me, cost | in government hospital near me | yes |
| gaushala | near me, in english, by munshi premchand, in hindi, road, management system, near me contact number, gurgaon | gaushala management | yes (3/9) |
| gaushala near me | contact number, to feed cows, open now, noida, for milk, gurgaon, for donation | rohini | no |
| veterinary hospital | near me, hebbal, near me government, kollam, mysore, delhi, patna, kannur | – | yes (1/9) |
| animal helpline number | ahmedabad, rajkot, delhi, near me, surat, bhubaneswar, bhavnagar, mp 24 hours, jamnagar | एनिमल हेल्पलाइन नंबर ("animal helpline number") | partly |
| animal ambulance number | delhi, in up, near me, odisha, assam, mp, jaipur, cuttack, vadodara | bihar | yes (1/9) |
| 1962 helpline | number, in hindi, timing, mp, in up, rajasthan, gujarat, kya hai (what is it), is free or not | 1962 हेल्पलाइन नंबर राजस्थान ("1962 helpline number Rajasthan") | partly |
| injured bird helpline | near me, number, ahmedabad, jaipur, mumbai, delhi, vadodara | – | no |
| ngo darpan registration | online, fees, documents, process, certificate (download), online niti aayog, benefits | kaise kare (how to do it), एनजीओ दर्पण रजिस्ट्रेशन फीस ("NGO Darpan registration fee") | yes |
| animal cruelty complaint | online, number, delhi, bangalore, hyderabad, india, form | पशु क्रूरता की शिकायत कहां करें ("where to file an animal cruelty complaint") | partly |
| animal cruelty act section 11 | punishment, punishment in hindi / marathi, 11 d punishment, punishment pdf | – | partly |
| feeding stray dogs law india | law in india, pdf, rules, can we feed, is it illegal | – | no |
| stray dogs supreme court | judgement, order, live law, next hearing, verdict, latest news, judgement today | आवारा कुत्ते सुप्रीम कोर्ट ("stray dogs Supreme Court") | yes (4/9) |
| abc rules 2023 | pdf, pdf english, free download, in hindi, india, gazette, upsc, supreme court | pdf hindi, rule 20 | yes (1/9) |
| pca act 1960 | section 11, pdf, in hindi, bare act, full form, section 28, punishment | – | yes (1/9) |
| awbi circular | circulars, circulars and advisories, 2015, pet dog circular, stray dog circular, rules, guidelines | 2024 | no result |
| world animal day | is celebrated on, 2026, celebration in school, kab manaya jata hai (when is it observed), drawing, 2026 theme, poster | world animal days list 2026 | yes (3/9) |
| world rabies day | 2026, 2026 theme, poster, drawing, celebrated on, logo, slogan | kab manaya jata hai (when is it observed) | yes |
| animal ngo near me | for cats, for dogs, contact number, for cow, open now, to volunteer, 24 hours | – | yes (2/9) |
| street dogs | complaint number near me, complaint, barking at night complaint, helpline number, ko kya khilana chahiye (what to feed them) | in india | no |
| animal welfare board of india | recruitment, headquarters, legal vacancy, v nagaraja, chennai, email id, upsc, chairman | – | yes |

**YouTube** (video demand):
- "dog bite": what to do, injection, treatment, in Tamil and Telugu.
- "rabies": rabies patient (top), rabies symptoms, rabies in Hindi.
- "gaushala": devotional and bhajan content.
- "animal rescue": rescue videos, "animal rescue India".
- "stray dogs": news and protest.

Video is a format for later (dog-bite first aid, how to call 1962). No page action now.

## F. Gaps and changes since 15 Oct

1. **World Animal Day.** A lasting guide exists (/resources/world-animal-day-4-october/, #13797, live 15 Oct), but Google ranks the **Library notification** instead (1,548 impressions, position 8–8.4). Four items share the primary keyword "world animal day". See the keyword map, section 7.
2. **Dog bite and rabies** (T 430 and 426) now has a page (#17307, 21 Oct) that is not yet ranking for the head terms. The suggestion clusters (schedule, category, food myths, price, Hindi) are the questions it should answer.
3. **NGO Darpan** (T 87) has a page (#17308, 21 Oct) at position 5.4 for "darpan id search" forms. The Hinglish how-to suggestions are the next questions to answer.
4. **Gaushala** (T 382) and **veterinary hospital** (T 318) are still the largest local terms. The state pages rank #1–2 for "animal welfare ngos in {state}". The city-level "animal ngo in {city}" pages are not in the top 10 for any of the 10 big cities, where Justdial, Petofy and the NGOs' own sites hold the page.
5. **1962** is now a real cluster (about 200 impressions at positions 8.8–11.6) with state-wise demand: MP, UP, Rajasthan, Gujarat.
6. **Lab animal rules** brought 3,795 named-query impressions at positions 3.5–8.5 in one week. This is campaign traffic. Keep it until the campaign closes, then point the Library item at the final notified rules.
