# People / "warriors": starting with Vishakha Shukla (plan, 26 Nov 2026)

Owner direction (26 Nov 2026): a section on the home page and detailed profiles of people who make a difference, with evidence.
Focus on **Vishakha alone** first. Not on the Home page beyond one short block; each profile is a full page.
Nothing here is published. Copy goes live only with the owner's explicit yes. No address, phone or exact shelter location is ever shown.

## 1. Decisions taken (owner, 26 Nov 2026)

| # | Decision |
|---|---|
| 1 | Vishakha only for now. More people come later; **SourceWeb ingests more coverage** (owner will send links later). |
| 2 | Content is already on hand (English profile from public reporting); the English text goes through the normal copy route (drafted, pasted, owner's yes). |
| 3 | **Resources can hold the profile.** Resources are WordPress Posts at `/resources/{slug}/`; their types (Guide, How-to, FAQ, Research) are Tags (`content-types.php`, `tags.php`). A new tag **Profile** needs no new content type. |
| 4 | **Insights check still to do** (section 7). |

## 2. Why under Resources (answer to "are you sure I cannot")

Yes, it can go under Resources, and this is the cheaper route. I had proposed a new `/people/` hub; that is only needed if Resources cannot carry it.

| | Under Resources (recommended) | New `/people/` type |
|---|---|---|
| Code | One tag, one pattern, no new content type | New content type, hub, template, registry entries |
| SEO | Existing Article + Breadcrumb + sitemap + FAQ output | Must be built and checked |
| URL | `/resources/vishakha-shukla-lucknow-dog-shelter/` | `/people/vishakha-shukla/` |
| Hub | Resources list filtered by tag Profile | New hub page |
| Risk | Resources list mixes guides and profiles (filter by type solves it) | More to review and maintain |

Switch to a separate `/people/` hub later only if profiles outgrow Resources (a dozen or more, or they need their own menu entry).

## 3. Blocks (all one pattern set, registered in `kits-registry.json`)

1. **Profile page** under Resources: header (name, place, role, photo with permission), our short story, the **evidence table**, related campaign and Directory link, "Reported by" source links.
2. **Home block**: one short strip (a single person for now) linking to the profile. Query pattern on tag Profile, latest first.
3. **About block**: the same strip pattern, shorter, reused.
No folds anywhere (owner rule). Brand cards black/white/grey. Sizes only from the theme tokens. Mobile first.

## 4. Schema (decided)

- Profile page: **Article** (or NewsArticle) with the person under `about`, plus **BreadcrumbList** (Home, Resources, name).
- Do **not** use ProfilePage for her: Google limits it to people affiliated with the site (author, forum, "about me"). ProfilePage is only for people who write on Animal Writes.
- Organisation (Nawabi Tails Rescue Foundation): its Directory listing, if one exists. Check before linking.
- No promise of a rich result; check each page in the Rich Results Test before and after launch.

## 5. Evidence for Vishakha (what has been read)

| Claim | Source | Date | Read |
|---|---|---|---|
| Doctor in Lucknow; farmhouse away from the city; about 400 dogs in care; food and treatment | NDTV India, reported by Ranveer Singh, edited by Tilak Raj, "Dr Vishakha" | 22 Aug 2025 | Full text |
| Funded by her own income and some dog lovers | same | 22 Aug 2025 | Full text |
| Her two doubts on the Supreme Court order (feeding until spots are set; who decides "aggressive") | same | 22 Aug 2025 | Full text |
| Founder, Nawabi Tails Rescue Foundation; women feeders face harassment and assault; court told to file FIRs | Hindustan Times HT Insight, **written by Vishakha Shukla** (her own words) | 12 Jan 2026 | Full text (first 5,000 characters) |
| Lucknow dog lovers backed street dogs ("आवारा नहीं हमारा है") | News Nation, YouTube | about a year ago | Title and tags only |

**Confirmed by the owner (26 Nov 2026):** the NDTV "Dr Vishakha" and the HT author "Vishakha Shukla" are the same person. The profile may say so, citing both sources.
Label rule: "reported by" for outlets, "in her own words" for the HT piece. Every claim has at least one source. Each source gets an archive link so the evidence survives link rot.

## 6. All links shared, with status

| Link | What it is | Status |
|---|---|---|
| ndtv.in/uttar-pradesh-news/doctor-vishakha-built-a-farmhouse-for-stray-dogs-9137899 | NDTV, 22 Aug 2025, Dr Vishakha farmhouse | Read in full (browser) |
| amarujala.com/video/lucknow/...-2025-08-17 | Amar Ujala video, Lucknow protest, 17 Aug 2025 (from the address) | Not opened |
| youtu.be/rmycIa7OQ1s | News Nation, Lucknow dog lovers | Title and tags only |
| samarsaleel.com/...lucknow-unites-for-animal-rights-2-0.../505446 | "Lucknow Unites for Animal Rights 2.0", Moti Mahal Lawns | Headline only; page body did not load |
| timesofindia.indiatimes.com/city/lucknow/lucknowites-plead-supreme-court-to-hear-the-other-side | Times of India, Lucknow residents | Blocked in the browser; headline only |
| hindustantimes.com/ht-insight/governance/constitutional-safety-must-extend-to-women-feeders-101768210630304.html | HT op-ed by Vishakha Shukla, 12 Jan 2026 | Read (first 5,000 characters) |
| epaper.navbharattimes.com/imageview_32608_2724977_4_9_05-01-2026_4_i_1_sf.html | NBT e-paper, 5 Jan 2026 | Page image; text not read |
| instagram.com/p/DTFYW8ik2E2/ | Instagram post | Redirected to a "suspended" page; not read |
| youtu.be/1PW75ehRyws | YouTube video | Blank page; not read |
| share.google/xtynKOb7o4Ngq3whV | Financial Express, "Spare a thought for animals" | Title only |

Owner will check the unread ones later; SourceWeb will ingest the coverage.

From the web (Lab campaign, not Vishakha): Google News results for the draft Laboratory Animals Rules 2026 (Rising Kashmir, Education Times, New Indian Express, OC Academy, The Scientist, Humane World for Animals, Scroll, PETA India, Mongabay India, TheWire) and Google's structured-data pages (search gallery, Profile page, Article). Search Console figures are in `docs/pending.md`.

## 7. Insights check (done 26 Nov 2026, Search Console 27 Oct to 23 Nov unless stated)

1. **Her organisation is already in the Directory:** `/directory/org/nawabi-tails-rescue/`. The profile links to it (a "founded by" link on the listing is the reverse link).
2. **Resources already rank:** twelve pages hold positions 4 to 7. Best: Draft Lab animal rules rule-by-rule (155 clicks, position 4.2), Animal testing laws India (127 clicks, 5,949 impressions), How to object to a draft rule (127 clicks, position 4.0), 1962 helpline (106 clicks, 8,630 impressions), CPCSEA full form (10,451 impressions, 0.58% CTR). A Profile page inherits that section's standing; guides and how-tos carry the traffic, so a profile will not lead on traffic.
3. **Lucknow demand is small and we rank around 10:** "animal rescue lucknow" (116 impressions, position 10.3), "animal helpline number lucknow" (97, 9.1), "animal rescue lucknow contact number 24 hours" (108, 9.6). A Lucknow profile plus the Lucknow Directory lists can support these; the profile should link to the Lucknow list.
4. **Her name has no impressions yet** (no query for her in the Lucknow set). It would be a new keyword for us. Targets: "Vishakha Shukla Lucknow", "Nawabi Tails Rescue Foundation", "women feeders stray dogs".
5. **Still to do after launch:** URL Inspection and Rich Results Test on the profile; 28-day comparison; InsightWeb review of Resources and News.

## 8. SourceWeb ingestion (done 26 Nov 2026)

- **Keywords added** (Sourcing, English, role Include, Google News search on): Vishakha Shukla (14837), Nawabi Tails Rescue (10021), Nawabi Tails Rescue Foundation (14838), Lucknow women feeders (14839). The workers use them from their next run. Tag **Vishakha Shukla** (`vishakha-shukla`, tag 1308) created for her coverage.
- **Results arrive in** Sourcing, To review (Pending; nothing auto-publishes; Publish sends an item to News). The evidence table then links to those News items.
- **Decision (owner, 26 Nov 2026): let go of Hindi news.** The Hindi news links (NDTV India, Navbharat Times e-paper, Samar Salil, Amar Ujala) are not ingested as News. They stay cited as sources in the evidence table only where a claim needs them (NDTV is the main source for the farmhouse and the 400 dogs).
- **Hindi social media stays in.** Instagram, YouTube, X and Facebook items about her are added by hand as Media & PR items (Press coverage supports the formats Instagram post or reel, YouTube video, X post, Facebook post or video). Enter the link; the page's own details fill the empty fields. Tag `campaign-...` where it belongs to a campaign.
- English items are found by the keywords above; anything else is entered by hand through the ingest endpoint (`POST /wp-json/aw/v1/ingest`, Application Password created by the owner), tag `vishakha-shukla`.

## 9. Approval and safety

- No profile goes live without the person's agreement, or, if the owner decides on public reporting only, a written note of that decision here.
- Private link to the draft for the person: approve, change or remove. No login (the claim flow, B5 and B6, is on hold; profiles move onto it later).
- No home address, phone number or exact farmhouse location. Photo only with permission and credit.
- Claims stated as reported ("about 400 dogs, as NDTV reported").

## 10. Steps

| # | Step | Who | Gate |
|---|---|---|---|
| 1 | ~~Confirm NDTV "Dr Vishakha" and HT "Vishakha Shukla" are one person~~ (confirmed by owner); Directory listing found (`nawabi-tails-rescue`) | Claude | done |
| 2 | Insights check (section 7) | Claude | none |
| 3 | Add tag **Profile** to Resource types; build the profile pattern, Home block and About block; register them | Claude | own branch, code-review skill, PHPCS, Deploy check, owner's yes before shipping |
| 4 | Draft Vishakha's profile text (English) | Claude, with the content skills | owner's explicit yes |
| 5 | Evidence table with archive links | Claude | owner's yes |
| 6 | ~~Contact Vishakha~~ (she supplied the links); owner asks her for a photo and her approval of the draft | Owner | her approval |
| 7 | Publish as draft, check Rich Results Test and URL Inspection, then publish | Claude | owner's yes |
| 8 | SourceWeb ingests more coverage; add rows to the evidence table | Owner and SourceWeb | none |

## 11. Open questions (owner, 26 Nov 2026)

1. ~~Contact her first, or public reporting only?~~ **Settled:** Vishakha is in touch and gave the owner the links herself. The profile is made with her, so the draft still goes to her for approval before it goes live (section 9).
2. Photo: use a photo she supplies, with her permission and credit. None until she sends one.
3. Menu entry: none for now; the Resources list with the Profile tag is enough.
