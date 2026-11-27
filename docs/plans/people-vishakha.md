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

## 12. Mobile UX (v2, 27 Nov 2026; skills: persona-builder, ux-discover, web-review, frontend-design, landing-page, draft-content)
The page is judged as a phone page first (owner rule: mobile PWA/native first). Evidence base: a live render of a Resource page at 375px (no horizontal scroll; body 15px/24px; buttons 44px tall; chips only 28px tall; page height 8,099px, about 10 screens) plus Search Console (Lucknow queries around position 10). Everything below marked "assumption" needs the owner's check.

### 12.1 Personas (behaviour first; max 3)
| | Primary: "Shared-link checker" | Secondary: "Lucknow helper-seeker" | Tertiary: "Feeder who wants the law" |
|---|---|---|---|
| Who and context | Opens a WhatsApp or Instagram link to Vishakha's profile, often inside the app's browser, one hand, on mobile data; may be a journalist, partner or supporter checking who she is | In Lucknow with a stray or injured animal, or looking for a rescue group; arrives from search ("animal rescue lucknow") | A feeder or RWA member worried about the Supreme Court orders; arrives from the explainers |
| Wants | Know who she is and trust it within 10 seconds; see the proof; share it | A listing or number now | Her position, the law, what to do |
| Fears | Hype, no sources, a slow page | Wasted time, wrong or old contact | Being told off, legal jargon |
| Best path on the page | Header, summary, evidence, share | Action row, Help in Lucknow | Her words, The law linked |
| Evidence | Assumption (her name has no search impressions yet, so first visits come from shares) | Search Console: small Lucknow demand, position about 10 | Existing Resource traffic (guides carry it) |
Priority: the checker is primary for the page; the helper-seeker is primary for the goal (listing and Lucknow help).

### 12.2 Phone journey (shared link, 375px)
1. Lands: sees name, role, city and one-line summary without scrolling past the first screen. 2. Wants proof: the evidence is the first thing after the story, as cards, not a table. 3. Taps a source: opens in a new tab with the outlet and date visible before the tap; archive copy beside it. 4. Acts: "Know more about {organisation}" and "Find help in Lucknow" are reachable without hunting. 5. Shares: native share sheet (owner rule, 13 Nov). Pain points to design out: horizontal tables, long page, tiny chips, hidden proof, any need to log in.

### 12.3 Mobile section order and rules (changes section 3 order for phones; desktop keeps one column too)
1. Breadcrumb (one line, truncates, not wrapped).
2. Header: photo or initials tile first (fixed aspect ratio, no layout shift), then H1, then role and city, then one meta row (date first) with at most two chips (Profile, city).
3. Summary (2 to 3 sentences, 40 to 50 words).
4. **Action row** straight after the summary: two full-width buttons, 44px tall (existing button size): "Know more about {organisation}", "Find help in Lucknow". This puts the primary goal on the first screen or the second, with no sticky bar (no new behaviour invented).
5. At a glance: a key/value list (label above value, one column; no table).
6. What she does; 7. In her words.
8. **Evidence as cards**, not a table: each card = claim line, then source and date, a label chip (Reported by / In her own words), then two links "Read" and "Archive copy". Cards stack full width; claim text 15/24 like body. This also serves as the Sources list (the separate Sources section is dropped to avoid saying everything twice; the page gets shorter).
9. In the news (hidden until there is coverage); 10. The law, linked (links, not cards); 11. Part of a campaign (one card); 12. Help in Lucknow (repeat of the action row's second button plus the list link); 13. FAQ (open, 3 to 4 items); 14. Approval and corrections (one short block); 15. Share, tags, related.
- Target page height: 5 to 6 screens (about 4,500px), against 8,099px for the Supreme Court guide.
- Tap targets: every link, chip and button at least 44px by 44px including padding; the site's chips are 28px, so on this page chips are limited to two and given the 44px target (documented exception to fix in CSS, not a new size).
- Type from tokens only: body 15/24 on phones; H1 from the heading token; lines stay under 75 characters (720px column, 16px gutters).
- No fold, no accordion, no horizontal scroll at 320 to 767px; breakpoints 767/768 and 1199/1200 only.
- Links: descriptive text ("Read the NDTV India report, 22 Aug 2025"), external links open in a new tab with "(opens in a new tab)" for screen readers; archive link text says "Archive copy".
- Keyboard and screen reader: headings in order (H1, then H2 per section, H3 per evidence card); the evidence list is an ordered list; visible focus ring from the theme tokens; whole-card links on Home have one link per card, arrows `aria-hidden`.
- Motion: none beyond the theme's existing hover and press states; reduced motion respected.
- Speed: one photo only, `loading` set correctly (the header photo is above the fold so it is not lazy), width and height set, thumb_check passes; no new scripts.
- Dark mode: the site has no dark theme in the theme files (only a colour named primary-dark), so nothing to check; keep the page free of hard-coded colours so one is possible later.
- In-app browsers (WhatsApp, Instagram): share preview image is the logo or Home image until her photo is approved (owner rule: no pictures on policy pages does not apply; share image = brand until a permitted photo exists).

### 12.4 Home on phones (correction to section 5)
The Home row stacks: **profile card first, then the 2 by 2 grid block**. `docs/card-sizes.md` sets the card minimum width at 17rem (272px, `--kit-card-min`), so a true 2-column grid does not fit at 375px. Correction: **below 768px the 2 by 2 block becomes one column of four compact stacked cards** (icon tile left, title and one-line sum, arrow right: the existing `kit-card--stacked-*` state), and returns to 2 by 2 from 768px. About us on phones: one profile card; the row of cards becomes a single column with a "See all profiles" button.

### 12.5 Visual direction (frontend-design, inside the existing system)
No new aesthetic: black, white and grey cards, Inter, the reference card (icon tile, arrow, title, one line). The one memorable element is the **evidence card**: a quiet "receipt" with the source, date and archive link, so the proof is the design. No gradients, no decorative numbering, no stock photo, no all-caps eyebrow labels. Initials tile in a grey icon-tile style until a photo is approved.

### 12.6 Copy for phones (draft, owner's yes needed)
- H1 (SEO) stays option A; the card title on Home and About is just "Vishakha Shukla", role line "Doctor and founder, Nawabi Tails Rescue Foundation", sum "Cares for about 400 dogs in Lucknow, as NDTV reported".
- Buttons say what happens: "Know more about {organisation}", "Find help in Lucknow", "Read the NDTV India report", "Archive copy", "Share this profile".
- Evidence claims are one sentence each, past tense, with the outlet named ("NDTV India reported ...").
- Empty states: "In the news" is hidden until it has items; no "coming soon".

### 12.7 Success measures (no new tracking without the owner's OK)
Read from what we already have: Search Console for her name, Nawabi Tails Rescue and Lucknow queries; existing GA4 events for outbound link clicks and share taps; Resources page depth. Targets to judge after 28 days: evidence and listing links are tapped; the page is not abandoned before the evidence; share taps occur. No GA4 test events on the live property (standing rule).

### 12.8 Mobile review checklist for the build (web-review)
Phone 375 and 320, tablet 768, desktop 1440; no horizontal scroll; tap targets 44px; contrast 4.5:1 (`contrast_check`); headings in order; alt text on the photo (or none for the initials tile); focus visible; keyboard-only pass through the action row, evidence links and share; layout shift (photo aspect ratio reserved); meta title, description and share image present; no dead links; no address, phone or location anywhere.

## 13. Owner answers, 27 Nov 2026 (settle section 11 / 12)
| Question | Answer | What it changes |
|---|---|---|
| Directory address | No address anywhere on the profile page. City and state only (Lucknow, Uttar Pradesh) | The profile shows city and state only. **The Directory listing is not changed** (owner, 27 Nov 2026: not asked for) |
| Her photo | "Use from the sourced web?" | **No.** Photos in NDTV, HT and other outlets belong to them and to the photographers; copying one needs their licence. Allowed sources: a photo she sends (permission and credit), or one on her organisation's own site and socials that she says we may use. Until then the initials tile. The owner can ask her for one while she is in touch |
| Copy yes | The owner will review the finished page by link | The page is built as a **draft** (not public) and the link is sent for the yes; Home and About stay hidden until publish |
| Home row position | 4th from the top | Counting the hero as the first block: Hero, Need help now, Find help, **this row**, then the campaign cards row. (If the owner meant after the campaign row, the pattern line moves one place) |
| 2 by 2 grid use case | The **current four small blocks** (Acts and Rules, Court orders and judgments, Circulars and notices, Letters and templates, from "The law, in plain words", `patterns/animal-law.php`) move into the grid | The right column of the new row = those four tiles (same links to `/library/?tag=...`). They are removed from the "The law, in plain words" section, which keeps its heading, its three Library cards and "Browse the Library". Below 768px: one column; from 768px: 2 by 2 |

## 14. History and sources found, 27 Nov 2026 (her organisation's own site, the web, our SourceWeb)
Read, not yet in the evidence table (rows need the owner's yes). Her own organisation's pages are labelled "in the organisation's own words".
| When | What | Source | Label |
|---|---|---|---|
| 2020 | Began rescuing during the COVID-19 lockdown from a small apartment in Lucknow | nawabitailsrescue.org/about-me/ | Organisation's own words |
| 2021 | Nawabi Tails Rescue Foundation registered (non-profit company, directors Vishaka Shukla and Vaibhav Shukla) | thecompanycheck.com registry listing | Registry aggregator, not primary |
| 9 Apr 2022 | Adoption camp at One Awadh Centre, Gomti Nagar | nawabitailsrescue.org/events/ | Organisation's own words |
| 2022 | About 1,306 animals rescued | Knocksense feature | Reported by |
| 9 Apr 2023 | Hindustan Times, cruelty against stray dogs on the rise, say rescuers (listed on the organisation's events page) | HT, read the piece first | Reported by (unread) |
| 22 Dec 2023 | "When you adopt, you save 3 lives": Knocksense feature on her and the foster home | knocksense.com | Reported by |
| 15 and 20 Jul 2024 | Moving to a bigger shelter outside Lucknow (capacity 700 to 800 stated), opening first week of Aug 2024; adds cattle and livestock care | nawabitailsrescue.org/events/ | Organisation's own words |
| 20 Jul 2024 | News18 Hindi story on her | listed on the events page, read first | Reported by (unread) |
| about 2025 | Long-term partner of Humane World for Animals India in Lucknow; sterilisation work grew into a city-wide programme with the Lucknow Municipal Corporation | humaneworld.org blog "This city leads the way in helping street dogs" | Reported by (the partner) |
| 17 Aug 2025 | Lucknow protest on the Supreme Court order | Amar Ujala video, Samar Salil | Reported by (unread) |
| 22 Aug 2025 | NDTV India, "Dr Vishakha" | read | Reported by |
| 5 and 8 Jan 2026 | NBT e-paper; TOI "Lucknow joins nationwide protest" (already on our India Writes for Its Community Dogs campaign) | NBT, TOI | Reported by |
| 12 Jan 2026 | Her HT op-ed on women feeders | read | In her own words |

**Differences to handle, not hide:** her profession is "dental surgeon" on her organisation's site, "surgeon" in Knocksense and "doctor" in NDTV, so the page says doctor and names the source for anything finer. Her site gives both 5,000 and 7,000+ animals rescued, and 350+ in care now (maximum 450); NDTV said about 400. Every figure is shown with its source and date, never merged. The registry lists a Kanpur registered address, which we do not use.
**Photos on her site:** her media library holds a team photo (masked faces, cannot tell which person she is), shelter and dog pictures; none is a clear portrait of her. A portrait still needs her or her site's own choice.
**SourceWeb (published and pending, searched 27 Nov):** no News item names her or Nawabi Tails yet; the only mention in our system is the supporter list on India Writes for Its Community Dogs. Her four keywords (added 26 Nov) have not produced items. Older coverage (2022 to 2024) will not appear through Google News keywords, so those links are added by hand through the ingest route with the tag `vishakha-shukla`.
**Her own channels (from the listing):** Instagram @nawabitailsrescue, Facebook /nawabitailsrescue, YouTube @nawabitailsrescue. The Instagram post and one YouTube video the owner shared did not open earlier (suspended page, blank page).

## 15. Her organisation's social channels, read 27 Nov 2026 (built-in browser)
These are the organisation's own pages (Facebook page "Nawabi Tails Rescue", Lucknow; YouTube channel). They are the organisation's words, not independent reports; reaction counts are as read on 27 Nov and change. Captions only: the videos themselves were not watched. No phone number, address or exact location from the pages is used. Instagram would not open in the pane (it redirects to a "suspended" page for every address, so it is blocked for this browser, not read).
**Facebook page, reels and posts (the feed reads back to 7 Jan 2026 in this pass; 2025 not reached yet):**
| Date | Reel / post (caption) | Link | Subject |
|---|---|---|---|
| 7 Jan 2026 | "I played the Supreme Court hearing on my laptop today. My dog sat next to me and watched." | facebook.com/reel/1432322521623862 | Stray dogs case |
| 13 Jan 2026 | "During the stray dogs hearing, the SC bench remarked, 'Keep them in your house', placing liability on feeders for bites and incidents. But street dogs are not pets." | facebook.com/reel/1239305421404389 | Feeders and the Supreme Court |
| 13 Jan 2026 | Thanks the Delhi Chief Minister for a decision, comparing it with the Supreme Court | facebook.com/reel/1887252915485970 | Stray dogs policy |
| 17 Jan 2026 | "Beware Honourable Supreme Court. We are sending reinforcements" | facebook.com/reel/1242988404373251 | Campaign |
| 9 Feb 2026 | "Honourable Supreme Court, don't close your eyes to the other side." (555 reactions, 9 comments, 80 shares when read) | facebook.com/reel/1605248667294056 | Supreme Court case |
| 24 Feb 2026 | "Feeding our community dogs isn't kindness alone. It's prevention." | facebook.com/reel/3383298101837674 | Feeding and safety |
| 12 Oct 2026 | "Lucknow Flood Response: Animal Rescue and Feeding" | facebook.com/reel/1090971913549280 | Flood rescue, Lucknow |
| 19 Oct 2026 | "Last Date: 5 Nov. Act Now. Save Them. Stop it from becoming a law." (matches our Laboratory Animals Rules campaign, objection deadline 5 Nov) | facebook.com/reel/30240516858881686 | Lab rules campaign |
| 22 Oct 2026 | "Sterilisation = low population = fewer dog bites = better life for all" | facebook.com/reel/1157765563602773 | Sterilisation |
The page posts almost daily (rescue stories, adoption, medicines, a fostering series). Donations are out of scope for the profile (owner, 27 Nov 2026: skip).
**YouTube (@nawabitailsrescue, search 27 Nov):** two channel videos about five years old ("We don't just foster, we care", "Lost Labrador finds his way back home", about 2021); a third-party video "Pet Adoption Camp by One Awadh Centre Mall and Nawabi Tails for Street Dogs and Cats" (National Watch, about 2022, matches the 9 Apr 2022 camp); "Episode 2: Vishakha Shukla, Lucknow's Most Favourite, with Picker At Pace" (Shubhi Garg, about 2019, 278 views; same person not confirmed). A different YouTube creator called Vishakha Shukla posts self-improvement videos; not her, not used.
**Still to read:** Instagram (needs the owner to open it, or the links sent), the Facebook feed before Jan 2026 (the 2025 stray-dogs campaign), her LinkedIn, the 2023 HT and 2024 News18 pieces, the Knocksense and Humane World pieces in full.

**Name (owner, 27 Nov 2026):** her title is Dr. The page shows "Dr. Vishakha Shukla"; the field `kits_profile_prefix` holds "Dr." (page data: honorificPrefix), the name field holds "Vishakha Shukla".
