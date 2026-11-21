# Law pages: SERP gap check before shipping (18 Nov 2026)

Skill: seo-skills:seo-content-brief, run as a gap check on approved copy. Nothing here rewrites the approved text. Every proposal is for the **next round**.

**Method and limits.** SE Ranking was not authorised, so the skill's fallbacks were used: WebSearch for the SERP, WebFetch for the top pages, and the statute or rule text where a fact needed checking. No paid tools and no Ahrefs.
- WebSearch runs from the US, not google.co.in, so the order below shows what is ranking, not the exact Indian positions.
- WebSearch shows no SERP features. **People Also Ask** is approximated from question-form searches and the FAQ blocks on the ranking pages. **AI Overview: unknown**, because it cannot be seen with these tools. Treat both as unverified until someone checks a logged-out google.co.in search.
- Firecrawl was not used, so the on-page benchmark (title length, og tags, JSON-LD) was skipped.
- Volume and KD: unknown (no data tool). Nothing is guessed.

Site rules applied to every proposal: FAQ answers of 50 words or fewer, at most 6 FAQs (a new one replaces one), headings written as claims, no HowTo or star markup, a source for every fact.

---

## Page 1: /resources/animal-cruelty-law-india/

**Primary:** animal cruelty law india. **Secondary:** pca act 1960, section 11 punishment, section 325 bns animals, animal cruelty.
**Our page:** 1,587 words, 13 H2s. B.json has **5 FAQs, not 6**, so one slot may be free. Check this before treating any new FAQ as a replacement.

**Template:** explainer. **Why:** the top 10 is mostly legal explainers and reference pages (Wikipedia, animallaw.info, law blogs); the queries are informational ("what is the punishment", "is it cognizable").

### Top 10 (WebSearch, "animal cruelty law india", plus the BNS 325 query)
| # | URL | Page type |
|---|---|---|
| 1 | https://blog.ipleaders.in/punishment-animal-cruelty-laws-animal-welfare-india/ | Law blog explainer (Sep 2021, IPC-era) |
| 2 | https://en.wikipedia.org/wiki/Prevention_of_Cruelty_to_Animals_Act,_1960 | Encyclopedia |
| 3 | https://en.wikipedia.org/wiki/Prevention_of_Cruelty_to_Animals_Act | Encyclopedia (disambiguation or overview) |
| 4 | https://en.wikipedia.org/wiki/Animal_welfare_and_rights_in_India | Encyclopedia |
| 5 | https://www.animallaw.info/article/overview-animal-laws-india | Academic overview |
| 6 | https://www.vosd.in/animal-cruelty-act-in-india/ | NGO explainer with FAQ (May 2026) |
| 7 | https://lawsikho.com/blog/bns-provisions-against-animal-cruelty-in-india/ | Law blog (paywalled, updated Jun 2026) |
| 8 | https://kannananimalwelfare.org/animal-rights-in-india-legal-laws/ | NGO explainer |
| 9 | https://www.animallaw.info/statute/cruelty-prevention-cruelty-animals-act-1960 | Statute text |
| 10 | https://devgan.in/bns/section/325/ | Section lookup (from the "section 325 bns" query) |

### People Also Ask (approximated, unverified)
- What is the punishment for animal cruelty in India? (we answer)
- Is animal cruelty a cognizable offence? (we answer)
- Is BNS 325 bailable? (**we do not answer**: devgan.in, aaptaxlaw.com)
- What is IPC 428 and 429 now? (we answer in one bullet)
- How do I file a complaint for animal cruelty? (we answer, and link out)
- Can a court take away an abused animal? (**we do not answer**: VOSD, iPleaders)

### Top 3: headings and length
- **iPleaders** (about 3,000–3,500 words): Introduction / PCA Act at a glance / Forms of cruelty and their punishment / How to file a complaint / Offences and penalties / Government restrictions / Animal welfare laws (PCA, WPA 1972, Draft AW Act 2011) / Way forward / Animal-friendly countries / Conclusion.
- **Wikipedia, PCA Act** (about 1,000–1,200 words): the Act chapter by chapter / Proposed amendments (2011, 2014, 2022 drafts; "under examination" on 3 Feb 2026) / Additional resources.
- **VOSD** (about 2,800–3,000 words): What is animal cruelty / PCA Act / Punishment / Role of IPC sections / Recent amendments / How to report / Rights of street and pet dogs / Cruelty vs negligence / FAQ.

### Contradictions (checked against primary text)
**No top result contradicts a fact on our page using a primary source.** Where the ranking pages disagree with us, they are the ones that are wrong or out of date:
1. **"BNS 325 covers animals valued at Rs 50 or more"** (wording in the search summary; iPleaders and VOSD use IPC 428/429 value thresholds). BNS 325 has no value threshold, so our text is right: it says "any animal, any value" and cites BNS 2(2). Evidence: https://devgan.in/bns/section/325/ and https://aaptaxlaw.com/bns/325-bns-mischief-by-killing-or-maiming-animal-325-bharatiya-nyaya-sanhita-2023.html. iPleaders and VOSD still cite IPC 428/429 as current law. This is a strength for us; no change needed.
2. **"Section 11 lists 16 forms of cruelty"** (iPleaders, VOSD). The statute has 15 clauses, (a) to (o): https://www.animallaw.info/statute/cruelty-prevention-cruelty-animals-act-1960. Our "fifteen" stands.
3. **2022 draft penalties.** VOSD gives Rs 1,000–5,000 for a first offence; Wikipedia and the Free Press Journal give Rs 50,000–75,000 for gruesome cruelty. Ours matches Wikipedia for gruesome cruelty: https://en.wikipedia.org/wiki/Prevention_of_Cruelty_to_Animals_Act,_1960. The figure for ordinary cruelty under the draft was not confirmed from a primary copy; the PIB release returned 403. Our page states only the gruesome-cruelty figure, so there is no conflict.
4. Our cognizable list (11(1)(l), (n), (o) and section 12, under section 31) matches the statute and iPleaders.

### Gaps (all for the next round)
| # | Gap | Evidence | Proposed addition |
|---|---|---|---|
| 1 | **BNS 325 is bailable** and tried by a Magistrate of the First Class; we say only "cognizable" | https://devgan.in/bns/section/325/ ; https://aaptaxlaw.com/bns/325-bns-mischief-by-killing-or-maiming-animal-325-bharatiya-nyaya-sanhita-2023.html ; BNSS First Schedule (India Code, already in our Sources) | Add to the BNS 325 list: "Bailable, and tried by a Magistrate of the First Class (BNSS First Schedule)." Check the India Code First Schedule before shipping. |
| 2 | **Section 29: forfeiture and custody ban.** A court may forfeit the animal to the government and bar the offender from keeping animals, permanently or for a period | https://www.animallaw.info/statute/cruelty-prevention-cruelty-animals-act-1960 ; https://www.vosd.in/animal-cruelty-act-in-india/ ; iPleaders | New H2: "A court can take the animal away and ban the offender from keeping animals (section 29)". Two sentences, including the 29(2) condition (evidence that the animal is likely to face more cruelty). |
| 3 | **Section 28: religious killing exemption.** It is missing from our "five cases" list (that list covers section 11(3) only) | https://www.animallaw.info/statute/cruelty-prevention-cruelty-animals-act-1960 ; https://en.wikipedia.org/wiki/Prevention_of_Cruelty_to_Animals_Act,_1960 | One sentence under "Section 11 does not apply in five cases": "Separately, section 28 says killing an animal in the way a community's religion requires is not an offence under the Act." |
| 4 | **BNS 291: negligent conduct with an animal** (up to 6 months, a fine up to Rs 5,000, or both; replaced IPC 289). It is used in dog-bite cases | https://devgan.in/bns/section/291/ ; https://vakilsearch.com/bns/sections/291 | One bullet under "Wild animals have their own law" or a short H2: "Owners who let an animal endanger people face BNS section 291". Sources disagree on whether it is cognizable; state only the penalty until the BNSS schedule is checked. |
| 5 | **Reporting basics:** 112 in an emergency; photos or video with time and place; a complaint does not need an eyewitness | https://www.vosd.in/animal-cruelty-act-in-india/ (Reporting) | One bullet at the top of "How to act on a cruelty case": "Record it: photos or video with date, time and place." Send the rest to the how-to-report page. |

Minor (optional): an IPC-to-BNS line, "IPC 428/429 → BNS 325; IPC 289 → BNS 291", for searchers still using IPC numbers (iPleaders and VOSD rank with IPC terms). Chapter V (performing animals, registration under section 22) is covered by Wikipedia and VOSD; it is low priority.

**What we have that they lack:** the 3 Feb 2026 "under examination" status (only Wikipedia also has it); the Rajya Sabha private member's bill (5 Dec 2025); Madras HC, Balaji v. State (1 Sep 2026), on 11(1)(a) being non-cognizable; BNSS 173/175 procedure and the 14-day preliminary enquiry; the proviso in 11(2) (no prison without a fine option for owners); the 11(3) exceptions; SC 19 May 2026 on euthanasia; Nagaraja read alongside the 2023 Constitution Bench. **No ranking page explains section 325 and section 11 together.** That is our angle.

**FAQ proposal (next round):** if the sixth slot is free, add the following. If not, it should replace "What is section 325 of the BNS?", which the body already covers.
- Q: "Is animal cruelty bailable in India?" A: "Yes, for the main offences. Section 11 PCA Act offences carry a fine or at most three months, and BNS section 325 (killing, poisoning or maiming an animal) is bailable under the BNSS First Schedule. Bail is still for the magistrate to grant." (44 words; check the schedule first.)

---

## Page 2: 9029 /resources/rwa-or-society-stopping-you-from-feeding-dogs-what-to-do/

**Primary:** feeding stray dogs law india. **Secondary:** is feeding stray dogs legal, is feeding stray dogs illegal in india, rwa stray dog feeding, society stopping feeding dogs.
**Our page:** 2,389 words, 14 H2s, 6 FAQs shown (a 7th is stored but hidden).

**Template:** MIXED (explainer and how-to). **Why:** the top 10 splits between legal explainers (S.S. Rana, A.K. Legal, Kannan) and practical guides (dogwithblog, NoBrokerHood, Risorra). Our page already has both halves: what the law says, then step by step.

### Top 10 (WebSearch, "feeding stray dogs law india", plus the RWA and 2026 queries)
| # | URL | Page type |
|---|---|---|
| 1 | https://ssrana.in/articles/stray-dog-menace-law-cases-india/ | Law firm article (May 2023, pre-2025 orders) |
| 2 | https://dogwithblog.in/feeding-stray-dogs-india/ | Blog guide with FAQ (updated 31 Oct 2026) |
| 3 | https://www.nobrokerhood.com/blog/feeding-stray-dogs-in-housing-society/ | Proptech blog with FAQ (Oct 2026) |
| 4 | https://kannananimalwelfare.org/feeding-stray-dogs-illegal-in-india/ | NGO blog (May 2026) |
| 5 | https://aklegal.in/feeding-of-stray-dogs-and-position-of-pet-parents-in-cooperative-housing-society/ | Law firm article |
| 6 | https://www.vosd.in/animal-cruelty-act-in-india/ | NGO explainer |
| 7 | https://dogopo.com/dog-feeder-laws-and-government-guidelines-in-india/ | Blog |
| 8 | https://www.risorra.com/stray-dog-feeding-rules-india | Blog guide |
| 9 | https://www.pressreader.com/india/hindustan-times-st-mumbai-live/20221112/281947431830946 | News (2022) |
| 10 | https://www.tribuneindia.com/news/delhi/dog-feeders-gain-legal-shield-from-harassment | News (18 Sep 2025) |
News that ranks for the "2026 supreme court" variant: https://www.downtoearth.org.in/governance/supreme-court-stays-order-on-putting-free-ranging-dogs-in-shelters-but-feeding-dogs-in-public-spaces-still-illegal, plus LiveLaw, SCC Online and Republic.

### People Also Ask (approximated from ranking FAQs, unverified)
- Is it legal to feed stray dogs in India? (we answer)
- Can my RWA fine me or stop me feeding? (we answer)
- Where am I allowed to feed stray dogs? (we answer, with a table)
- What is the Supreme Court order on feeding? (we answer)
- What can I do if neighbours threaten me? (we answer partly, without naming the offence)
- Is there a punishment for feeding dogs on the street? (**not answered directly**: keralakaumudi.com headline "punishment for feeding dogs")
- Can tenants feed stray dogs? (**not answered**: dogwithblog)

### Top 3: headings and length
- **S.S. Rana** (about 1,000–1,100 words): Threats caused by stray dogs / Laws pertaining to stray dogs / Judicial trend. Cites the ABC Rules 2001, Chablani and Nagpur. Nothing after 2023.
- **dogwithblog** (about 3,000–3,300 words): Quick answer / Why feeding is legal / What changed in 2025 / What the SC decided in May 2026 / What your RWA can and cannot do / How to feed without becoming a liability / What to feed / If your RWA harasses you / Tenants have the same right / A note on your city / Myth vs reality / FAQ.
- **NoBrokerHood** (about 1,800–2,000 words): What societies can and cannot do / Safety rules and best practice / Legal protection for feeders / Sterilisation and vaccination / Harmony / SC judgment / FAQ.

### Contradictions (these matter most)
**Our lead ("feeding on the street is not allowed anywhere in India") is contradicted by several ranking pages, but the primary sources support us.** No change to our text is proposed; the risk is to readers, who will see the conflict in the SERP.
1. **dogwithblog FAQ** says feeding is allowed "on public streets away from heavy footfall" (https://dogwithblog.in/feeding-stray-dogs-india/). This contradicts SC 22 Aug 2025, para 33(d): https://api.sci.gov.in/supremecourt/2025/41706/41706_2025_3_1501_63567_Order_22-Aug-2025.pdf. Down To Earth's headline agrees with us: "feeding dogs in public spaces still illegal".
2. **Kannan** says there is "no national law prohibiting feeding" and calls feeding "allowed with conditions" (https://kannananimalwelfare.org/feeding-stray-dogs-illegal-in-india/). **VOSD** and **NoBrokerHood FAQ 7** say feeding is "not illegal" or "legal" without saying where. All three are contradicted by para 33(d) and by the 19 May 2026 judgment, para 109.
3. **Some reports say the street-feeding ban covers Delhi only** (search results, e.g. thesouthfirst.com). We say all of India, citing paras 34–36. **Before shipping, check paras 34–36** in the 22 Aug 2025 PDF, because this is the claim readers will dispute.
4. **NoBrokerHood says the Animal Welfare Committee must decide within 15 days.** Rule 20 has no time limit; checked in the AWBI copy of the ABC Rules 2023: https://awbi.gov.in/uploads/regulations/167956418266ABC%20Rule%202023.pdf. The 15 days comes from the Bombay HC order in RNA Royal Park, which we already cite correctly. No change.
5. **"Feeding is a constitutionally protected right under Article 51A(g)"** (VOSD, dogwithblog, NoBrokerHood). Article 51A(g) is a fundamental duty, not a right that can be enforced against an RWA. We do not make this claim. See gap 4.
6. **Feeder liability.** The January 2026 news headlines ("feeders will pay compensation", e.g. https://www.newsx.com/india/stray-dogs-menace-scs-strong-warning-to-states-and-feeders-for-every-dog-bite-youll-pay-compensation-146241) may read as conflicting with our H2 "No court has made a street feeder pay for a dog bite". They were oral remarks; Moneylife reports the bench said on 20 Jan 2026 it was "not sarcastic" (https://moneylife.in/article/we-were-not-sarcastic-about-making-dogfeeders-liable-for-stray-dog-attacks-supreme-court/79430.html). No order making a feeder pay was found. Our page already says it was an oral remark. Optional: add the 20 Jan 2026 follow-up so the bullet is not read as dismissive.
7. **17 Nov 2026 hearing.** Our ThePrint source confirms the order was reserved "on the issue of compliance". It does not mention feeding, which matches "no new direction on feeding has been issued yet". OK.

### Gaps (all for the next round)
| # | Gap | Evidence | Proposed addition |
|---|---|---|---|
| 1 | **Rule 20(2): the committee may name a Colony Care Taker** designated by the Board to feed in the area. We mention CACT applications but not this power, which is the link to them | ABC Rules 2023, rule 20(2), closing para (AWBI copy, URL above) | Add to the "Disputes" bullet: "The committee may also name a Colony Animal Caretaker designated by the Board to feed the animals there (rule 20(2))." Cross-reference the CACT step. |
| 2 | **Name the offence for threats:** BNS 351, criminal intimidation (formerly IPC 503/506), and BNS 325 if a dog is harmed. Our step 6 says "file a police complaint" without a section | https://dogwithblog.in/feeding-stray-dogs-india/ (FAQ 7); VOSD (still cites IPC 503/506) | Step 6: "Threats are criminal intimidation under BNS section 351; harming the dogs falls under BNS section 325 and PCA Act section 11." Cite the BNS on India Code. |
| 3 | **Delhi government guidelines (Sep 2025):** harassing, threatening or obstructing a feeder who feeds responsibly is punishable; RWAs pick spots; disputes go to the rule 20 committee | https://www.tribuneindia.com/news/delhi/dog-feeders-gain-legal-shield-from-harassment | A "State rules" line under "The rules may change after each hearing" or a short H2: "Some states add their own feeding rules". **Get the Delhi UD department circular itself before shipping**; the report gives no penalty. dogwithblog's West Bengal rules (feeding windows, caregiver ID cards) need a primary source first. |
| 4 | **The "constitutional right to feed" myth** that 3 of the top 6 repeat | VOSD, dogwithblog, NoBrokerHood (URLs above); Constitution, Art. 51A(g) | One sentence in "Older rulings no longer set the rule": "Article 51A(g) is a citizen's duty of compassion for living creatures, not a right to feed in any place." |
| 5 | **Fines: we rest on what rule 20 leaves out.** Ranking pages cite an AWBI letter of 16 Jun 2022 barring societies from fining feeders | A search snippet citing "AWBI letter dtd 16.6.2022" (source page not confirmed; the search could not find the letter) | Look for the letter on awbi.gov.in (circulars). If it exists, cite it on the "No power to ban or fine" bullet. If not, keep the current wording. Do not cite it unseen. |

Also noted (lower priority): **tenants**. Rule 20(1) covers "the person residing in that area or premises", which includes tenants. dogwithblog has an H2 on this. One line in the RWA H2 would do: "This includes tenants (rule 20(1): 'the person residing in that area')."

**What we have that they lack:** paragraph-cited SC orders (22 Aug 2025, 7 Nov 2025, 19 May 2026); the 7 Nov 2025 "not a feeding ban" correction; the campus affidavit (para 74); the High Court monitoring cases; Bombay HC, Ayyappa Swami (Dec 2025), which goes against feeders and which no competitor reports; RNA Royal Park; Kerala HC panchayat liability (28 Jul 2026); Chablani and Nagpur marked as overtaken; the 17 Nov 2026 status. **No ranking page cites rule 20 by sub-rule, or any SC paragraph.**

**FAQ proposal (next round):** replace "What is a feeding spot under the ABC Rules?" (the body and the table already cover it) with:
- Q: "Can I be fined or arrested for feeding dogs on the street?" A: "The Supreme Court's order of 22 Aug 2025 says street feeders "shall be liable to be proceeded against under the relevant legal framework", but it names no law and sets no fine. Your municipal body or High Court may add local rules." (43 words; source para 33(d).)

---

## Sources used in this check
- Ranking pages: every URL in the two top-10 tables above.
- Primary texts: ABC Rules 2023 (AWBI PDF, rule 20 read in English and Hindi); PCA Act 1960 (animallaw.info statute text: sections 11, 28, 29, 31); BNS 325 and 291 (devgan.in, aaptaxlaw.com); SC 17 Nov 2026 (ThePrint, PTI); Wikipedia, PCA Act (2022 draft, 3 Feb 2026 status).
- Not reachable: PIB release 1881462 on the 2022 draft (HTTP 403); LawSikho body (paywalled).

## Keyword ownership check against the InsightWeb plan (18 Nov 2026)
- Page 1, gap 5 (reporting basics, 112): **do not add.** 127 owns "report animal cruelty" and "fir for animal cruelty"; the who-to-call page owns 112. Page 1 already links to 127 under "How to act on a cruelty case".
- Page 1, gap 4 (BNS 291): **one line only**, on negligent conduct with an animal; no "dog bite" wording. Link to dog-bite-rabies-vaccine, which owns that cluster.
- All other proposals match the approved keyphrases (Decision 3 defaults). No new pages or phrases; "animal cruelty laws" stays on News 9718.
- Page 1 has 5 FAQs, so "Is animal cruelty bailable in India?" takes the free sixth slot.
