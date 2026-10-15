# Keyword demand — Animal Writes, 15 October 2026

Data pulled 15 Oct 2026, 09:00–10:00 IST. It puts real demand figures behind `docs/seo/keyword-map.md` (the map now has a Demand column that uses the codes below). Nothing on the site was changed.

## Sources

| Source | Result |
|---|---|
| Search Console (through the site's Site Kit connection, property `sc-domain:animalwrites.in`) | Worked. Data runs from 17 Nov 2025 to 14 Oct 2026. Pulled by query (892 rows), page (903), query + page (965) and date (332). |
| Google Trends (built-in browser, India, past 12 months) | Worked. About 25 comparisons, all anchored on "animal welfare" so the figures can be compared. Also pulled related queries and state interest for 5 terms. |
| Google autocomplete (suggestqueries, hl=en, gl=in) | Worked. 48 seed phrases, plus a presence check for 130 map keywords. |
| Bing autosuggest (en-IN) | Worked for the same 48 seeds. Bing is more US-leaning, so it was used only to confirm Google. |
| Google results pages (built-in browser) | Worked for 5 phrases ("People also ask", related searches and who ranks). No CAPTCHA. |
| Wikipedia pageviews (English, Oct 2025 – Sep 2026) | Worked. Used as a rough proxy for topic interest. |
| Ahrefs (Keywords Explorer, subscription info, management projects, gsc-keywords) | Every call returned "Insufficient plan". Each was tried once. |

## How to read the codes

- **T** is the Google Trends index, India, past 12 months, with "animal welfare" = 100.
  - T is relative interest, not a search count.
  - T 0 means too little data for Trends to show.
- **AC** is Google autocomplete (India).
  - **Y**: the exact phrase is suggested.
  - **ext**: only longer forms are suggested.
  - **–**: not suggested.
- **GSC** gives Search Console impressions and clicks for 17 Nov 2025 – 14 Oct 2026, for the matching queries or page.
  - Search Console hides anonymised queries. Query rows cover 26,711 of the 46,447 total impressions.

## Search Console in brief

- **Totals.** 46,447 impressions and 20,589 clicks. Of these, 23,198 impressions and 17,404 clicks came in November 2025 (the 1.0 campaign). October 2026 so far has 9,863 impressions and 170 clicks.
- **Brand.** Brand queries ("animal writes", "animalwrites.in", "animalrights.in" and their variants) make up 22,108 of the 26,711 named-query impressions and 16,880 of the 16,958 clicks.
- **Non-brand.** About 4,600 impressions and 78 clicks over 11 months. Almost all of it is at positions 5–12.
- **Non-brand impressions by surface:**
  - Home: 2,101 (campaign words such as "nodogsnovote", "petition campaign" and "letter petition");
  - the World Animal Day Library item: about 1,060;
  - Directory place pages: 2,540 page impressions across 135 pages, position about 8;
  - organisation listings: 2,144 across 367 listings;
  - guides: ABC 323, report cruelty 336, injured animal 121.
- **Home phrase.** "animal welfare in india", "animal welfare india" and "animal welfare" got no or nearly no impressions. The site does not yet appear for its head terms.
- **Strongest non-brand pattern: local help numbers.**
  - "animal helpline number {city}": Gwalior, Bareilly, Azamgarh, Hardoi, Unnao, Bilaspur. 104 impressions across 25 variants.
  - "animal rescue {city} contact number": Gwalior, Shimla, Jammu, Alappuzha, Ludhiana, Bareilly, Haridwar. About 136 impressions.
  - They land on district Directory pages or old /location/ URLs (now 301s) at positions 7–11, with no clicks.
- **Rules by name.** "pet shop rules 2018", "transport of animals rules 1978 (pdf)", "dog breeding and marketing rules 2017" and "abc rules 2023 pdf": about 70 impressions at positions 4–9.

## A. Home main phrase: keep "animal welfare in india"

Trends (India, 12 months, same chart):

| Phrase | Index |
|---|---|
| animal welfare | 51 |
| animal rights | 17 |
| animal welfare india | 14 |
| animal welfare in india | 4 |
| animal rights india | 3 |

Over 5 years:

| Phrase | Index |
|---|---|
| animal welfare india | 11 |
| stray dogs in india | 4 |
| animal welfare in india | 3 |
| animal rights in india | 1 |
| animal laws in india | 1 |

- **Why the bigger number for "animal welfare india" is misleading.** Its related queries are almost all "animal welfare board (of india)" (100/94), which people use to find the government board. Its higher index is largely that board's traffic, not people looking for a resource.
- **Autocomplete.** "animal welfare in india" is the first suggestion for itself. People who add "in" want information (essay, pdf, laws, list of organisations).
- **Who ranks.** The page 1 results for "animal welfare in india" are AWBI, Wikipedia, animallaw.info, an explainer, STRAW and World Animal Protection: an informational overview, which is what our Home is.
  - People also ask: "What are the main animal welfare laws in India?" and "Who is the current Minister of animal welfare in India?"
  - Related searches: "list of animal welfare organisations in India" and "animal laws in India pdf".
- **Same phrase to Google.** Google treats "animal welfare in india" and "animal welfare india" as the same search. A title and description that contain "animal welfare in India" match both.
- **Alternatives considered:**
  - "animal rights india": lower (3) and a different stance;
  - "stray dogs in india": topical, already owned by the Community Dogs topic;
  - "animal ngo": index 28 with "near me" intent, already owned by the Directory.
- **Verdict.** Keep "animal welfare in india". No change.

## B. The three live intros (report only, nothing edited)

**Media:** "Press coverage of Animal Writes and its citizen letter campaign for community animals across India."
- **Searches that come to us.** People search the campaign as a petition: "petition campaign" (113 impressions), "letter petition" (48), "stray dog petition india" (14), "letter petition supreme court" (12, position 4.3), "dog petition" (31). Hashtag searches also come: "nodogsnovote" 605, "#saveindianstreetdogs" 54.
- **Phrases with no search.** "Citizen letter campaign" and "press coverage" show no searches. "Community animals" has T 8, against "animal rights" 17 and "letter petition" 5.
- **Suggestion (optional).** Change "citizen letter campaign" to "citizen letter petition campaign", so the words people actually search appear in the intro.

**Opportunities:** "Volunteer, foster, intern or find a job with animal welfare organisations across India."
- **Volunteer.** "volunteer" is by far the biggest word (T 700, generic). Starting with "Volunteer" is right.
- **Jobs.** "ngo jobs" has T 67. Autocomplete suggests "animal welfare jobs in india".
- **Low demand.** "foster" (T 2), "internship" (T 0) and "animal welfare volunteer" (T 0, though autocomplete shows "animal welfare volunteering near me") have little demand in India.
- **Organisation vs NGO.** Searchers say "NGO" far more than "organisation" ("animal ngo" T 28; "animal welfare organisation" and "organization" both T 0).
- **Suggestion (optional).** "...find a job with animal welfare NGOs across India", or keep "organisations" and leave "NGO" to the title. The order of the verbs is fine.

**About first line:** "Animal Writes is a public resource for animal welfare in India: news, court orders and rules explained, and a directory of help near you."
- **Fits.** It contains the Home phrase word for word. "help near you" matches the very large "near me" intent ("animal ngo near me" is the top related query for "animal ngo").
- **"Laws" vs "rules".** People search "laws": "animal laws" has T 14 against "pca act" 2. Autocomplete suggests "animal laws in india", "animal welfare laws in india" and "animal cruelty laws in india".
- **Suggestion (optional).** "court orders, laws and rules explained". This is minor. The line is fine as it is.

## C. Gaps

**Search Console shows impressions but no page targets the query.**

1. **"animal helpline number {district}" and "animal rescue {district} contact number" (about 240 impressions across about a dozen districts, positions 7–11, 0 clicks).**
   - Trends: "animal helpline" is 59 and "animal rescue" 100, against "animal welfare" 100. Gujarat and Odisha lead.
   - District Directory pages target "animal ngo in {district}" and never say "helpline number" or "contact number".
   - Add both as district secondaries (map updated) for Phase 4 to consider.
2. **Campaign petition searches land on Home, not /campaigns/.**
   - The searches: "petition campaign" 113, "letter petition" 48, "stray dog petition india" 14, "letter petition supreme court" 12 (position 4.3), "public petition campaign india" 19.
   - The Campaigns hub's primary "animal welfare campaigns" has T about 0.
   - Suggested primary for /campaigns/: "stray dog petition"; secondary "letter petition supreme court".
3. **World Animal Day (about 1,060 impressions).**
   - The searches: "world animal day 2026", "animal day 2026", "4 october 2026 day", "animal welfare day 2026", "national animal day 2026" and others.
   - They land on a Library notification at positions 5–12. There is no lasting page for the day.
   - This is seasonal (one spike each October). A short "World Animal Day (4 October)" explainer could catch it every year.
4. **1962 helpline questions.**
   - Search Console: "1962 veterinary assam" 9, "1962 veterinary ambulance" 5, "mobile veterinary unit uttar pradesh" 14, "mobile veterinary unit in west bengal" 6.
   - People also ask: "Is 1962 free?", "Why is the animal ambulance number 1962?"
   - Only the guides mention 1962. Add these two questions to the "Who to call" guide's FAQ.
5. **Rules searched by name with "pdf".** "pet shop rules 2018 pdf", "transport of animals rules 1978 pdf", "abc rules 2023 pdf", "pca act 1960 pdf" and "dog breeding and marketing rules 2017 pdf". Autocomplete shows "pdf" on almost every rule. The Library items exist, so make sure each links the official PDF and carries "PDF" in its description.

**Phrasings with high interest that we don't target.**

6. **"street dogs"** has T 96, against "community dogs" 11.
   - "stray dogs" has T 171, but part of that is the anime "Bungo Stray Dogs".
   - Related: "street dogs in india", "street dogs complaint (number)", "food for street dogs", "street dogs supreme court".
   - We use "community dogs" almost everywhere. Add "street dogs in india" to the Community Dogs topic (map updated).
7. **"gaushala": T 383, the largest single term in our field.**
   - Rajasthan, Chandigarh, Haryana, Punjab and Delhi lead.
   - Autocomplete: "gaushala near me", "gaushala {city}", "gaushala near me contact number".
   - We hold 2,047 gaushala listings. Gaushala type-in-place and state pages are the biggest local opportunity.
8. **"veterinary hospital" (T 315) and "vet near me" (T 92).**
   - Autocomplete: "government veterinary hospital {city}" and "veterinary hospital near me government".
   - "veterinary care india", the Veterinary Care topic's primary, has no autocomplete and T 0. Suggested primary: "government veterinary hospital".
9. **Supreme Court stray dogs order and feeding legality.**
   - "supreme court stray dogs" has T 16 and full autocomplete: "order", "judgement", "verdict", "pdf".
   - Autocomplete also shows "is feeding stray dogs illegal in india", "awbi guidelines for feeding stray dogs", "feeding stray dogs in housing society" and "supreme court judgement on feeding stray dogs".
   - Our feeding guides use "community dogs" phrasing ("feeding community dogs" is barely suggested), and Search Console shows 1 impression for any feeding query.
   - Suggested secondaries: "is feeding stray dogs illegal" and "awbi guidelines for feeding stray dogs".
10. **Animal Welfare Board of India.**
   - Signals: T 9, the top autocomplete for "animal welfare", Wikipedia 664 views a month, and a "Breakout" in AWBI recruitment searches.
   - No page of ours targets AWBI. A listing or explainer, and AWBI vacancies in Opportunities, would meet that demand.

Smaller notes:
- **News.** "animal welfare news" is low (T 5) but up 1,500% in the year. "animal news" is T 136. Keep the News primary and add "animal news india".
- **Adoption.** "dog adoption" (T 67) beats "adopt a dog" (T 7) and "animal adoption" (T 20).
- **Cruelty.** "animal cruelty complaint online" and "animal cruelty complaint {city}" are suggested.

## Other topic-interest signals (Wikipedia, English, monthly average Oct 2025 – Sep 2026)

| Article | Views a month |
|---|---|
| Indian pariah dog | 13,390 |
| Cattle slaughter in India | 8,626 |
| Free-ranging dog | 6,729 |
| Animal rights | 6,597 |
| Animal welfare | 4,246 |
| Animal Welfare Board of India | 664 |
| Animal welfare and rights in India | 405 |
| PCA Act 1960 | 374 |
| Gaushala | 37 |

"Indian pariah dog" is the article for indie dogs. The strong interest in it supports using "indie dog" in adoption copy.

## Paid or sign-in sources the owner could set up (most useful first)

1. **Google Ads Keyword Planner.** Needs a free Google Ads account with no spend. It gives monthly volume ranges for India and for each state, which is the one thing missing here.
2. **Search Console, used directly.** Add the owner's account to the property, or keep using Site Kit. Watch the 2.0 data (from October 2026) for 3 months, then re-run this check.
3. **Bing Webmaster Tools, Keyword Research.** Free with a sign-in. It gives real Bing volumes for India; import the property from Search Console.
4. **An Ahrefs plan with Keywords Explorer (Lite or above).** It gives Indian volumes, keyword difficulty and competitor gaps, and the MCP connection is already in place.
5. **Google Trends.** Already free. Re-run it each October for World Animal Day and when the Supreme Court hears the stray dogs case.
