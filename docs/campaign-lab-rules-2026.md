# Lab rules campaign 2026: page record

Page: `/campaigns/lab-animals-rules-2026/` (campaign 13848; short link `/lab`).
Subject: the Draft Laboratory Animals Rules, 2026 (G.S.R. 864(E)), published in the Gazette on 6 Oct 2026; objections to `cpcsea-mef@gov.in` until 5 Nov 2026.
Library draft: 13844 (PDF 13845). Guides: 13854–13858, 13869. Campaign link term: 1077.
Build script: `tools/content/campaign2-page.php`. Styles and scripts: `plugins/animal-writes-core/assets/css/campaign-lab.css`, `assets/js/campaign-lab.js`; lifecycle, counters and shortcodes in `includes/campaign-tools.php`, `campaigns.php`, `campaign-letters.php`.

Every hash below is on `wordpress` (live) as of 3 Nov 2026. Sections are in page order.

## Timeline
- 30 Sep: G.S.R. 864(E) dated. 6 Oct: published in the Gazette; 30 days for objections.
- 16 Oct: Library draft, 1998 Rules and 10 guides; campaign tools and the page built.
- 17 Oct: page redesign (poster hero, date strip, pain ladder, stepped letter tool, news grid); press linked to the campaign; Campaign Content Standard order.
- 18–19 Oct: letters record the sender; palette A; band colours; share pictures.
- 21 Oct: owner's section order; Other ways to send removed; share box; objection count removed.
- 24–25 Oct: Find help near you band; pain-scale tiers as H3.
- 31 Oct: On social media only for Lab posts; own X and LinkedIn accounts with a pinned Lab post.
- 1 Nov: speed and font fixes; keyphrases; companion links on the guides; Library draft title and H1.
- 2 Nov: theme hero, three-step letter tool on the form kit, bento, share block, short link /lab, hashtags for X; the page kept as on 2 Nov morning.
- 3 Nov: pain meter, What people are saying, phone and tablet fixes; one newsletter to 115 volunteers.
- 4 Nov: pain scale as four cards and the newsletter band in crimson (live with 1d787b75); 45 campaign pictures added to the media library (24146–24190), none placed on the page.
- 5 Nov: the objection window closes (30 days from the Gazette).
- Close (correction): the page does not close on 5 or 6 Nov. Nothing closes it by date; it stays Active until its state is set to Post (owner, 2 Nov). See Post state below.

## Hero
- Page set to the Campaign Content Standard v1.0 (Active order). 6d4feee1, 17 Oct
- Section side padding from the site gutter (16/32/48), not a fixed 16px. 07d5e82e, 17 Oct
- No block gap between nested full-width sections; one section padding. 577f87bd, 19 Oct
- Rule chips quote the Library clause; open and closed blocks; countdown to the last day. 482990d1, 16 Oct
- Poster hero with date strip. 8a4e8888, 17 Oct
- Gazette pages as the motif: hero cover, clause crops, a strip of pages. a62de3d1, 17 Oct
- Palette A, Gazette crimson; white rules and icons on dark bands where crimson is under 3:1. d63f82d3, 17 Oct
- Hero fits 1020 / 1020 / 800; tighter column gap, shorter Gazette strip. 7dd98fb9, 196ed0a2, 19 Oct
- Artwork crops show the whole rat. 951469d7, 19 Oct
- Theme hero and H1. 69972b62, 2 Nov
- Every hero fixed at `--kit-hero-h` (min(720px, 100svh)) at every width. beb95d65, 2 Nov
- The fixed hero holds its content on phones. e4b15a6b, 2 Nov
- Clauses label on the chips' first row, so the hero fits on phones with the Gazette strip; rule chips keep a 44px hit area. 20105a6e, 2 Nov
- Hero minimum height, so only text zoomed to 200% can grow it (WCAG 1.4.4); Gazette strip off on short phones. 4ff02393, 2 Nov
- White focus ring on dark surfaces (hero, dark sections, lead tile). e78e13ec, 2 Nov
- The objection count is not shown in the hero (owner: the number was not real). 34f7eb41, 21 Oct

## What's happening
- The campaign date timeline: vertical on phones, three dates in one row when wide, only the key date in the campaign colour. 788d1293, 2 Nov
- The link sits under the timeline at the list's margin. cf019c96, 2 Nov
- Full rule name in What's happening; query loops keep post types with no public pages. ef6d424e, 17 Oct
- Campaign artwork beside What's happening and the Library section. 577f87bd, 19 Oct

## What the draft does
- Icon grid of the changes, with rule cards that have their own anchors (`change-1` …). 8a4e8888, 17 Oct; 71eb987f, 17 Oct
- A bento: one crimson lead tile, supporting tiles, the last tile full width. e2dffa11, 1297afda, c28dc76a, 601df136, 2 Nov
- Three rows from 1200 (the lead and open markets, then three thirds, then the closing card); supporting cards keep their hover shadow. 8cf3b022, 054abcbb, 1afd535a, 2 Nov
- Bento template for every campaign: three cards = the lead two thirds beside one, then the full row; five = lead and one, two halves, the full row. e0925958, 2 Nov
- Guide links under the section, 44px targets in body type, with the open-book guide icon. 54045334, 2f259100, 3 Nov

## Pain scale
- Pain ladder of the four tiers (mild, moderate, severe, non-recovery). 8a4e8888, 17 Oct
- Bars restored. 04c97133, 19 Oct
- Tiers are H3 under the section's H2, same look. bca59647, 25 Oct
- Objection tally on a brand band with an envelope mark (later removed with the count). 1296ae4c, a3818f1e, 21 Oct
- One thin pain bar; it stays thin on desktop. fb40f4e5, 2 Nov; 0bb120a1, 3 Nov
- A pain meter: each tier's bar is four segments lit to its level and fills once on first view; on phones a rail down the left fills on scroll, with crimson nodes on Severe and Non-recovery; reduced motion or no JS shows it full. cdf06b2b, 3 Nov
- Rule chips are 44px targets on phones. 696bb4d9, 48313da0, 3 Nov
- Guide links white on the dark section. 54045334, 3 Nov

## Today vs the draft
- Comparison table; no tint on the draft column. feb7b369, 17 Oct; 50621a47, 19 Oct
- No sideways scrolling row. c53ac16e, 2 Nov
- On phones, one card per row, so the Draft 2026 column is never off the screen. 74cc0801, 3 Nov

## Send your objection (letter tool)
- Letter tool: pick grounds, then send; confirmed-letter count. 482990d1, 16 Oct
- Routes now: Gmail (the app on phones), the email app, or Copy letter; no Outlook; Copy address in the note after copying (owner, 19 Oct).
- Stepped letter tool. 8a4e8888, 17 Oct
- Anonymous statistics per letter (city, grounds, route, device, date; no name, email or IP). 1464c105, 17 Oct
- Every send records the sender: name, city and email required; a contact tagged with the campaign; "Email me updates" and the WhatsApp box start ticked. fba35301, 18 Oct
- "Sign it" is Campaign form #10 (campaign, reasons, route, own line, both boxes, WhatsApp, an AW-L-n reference, visit fields); contacts tagged campaign-<slug> (owner, 21 Oct).
- The record-keeping sentence and the "Is my data stored?" question removed. 299dc952, 18 Oct
- Letter tool on a white card. d0586909, 19 Oct
- Other ways to send removed (by post first, then the section). b380d669, 6a5c3290, 21 Oct
- Before that: Other ways to send as tiles with badges and a postal address; postal date line without an extra icon. 1296ae4c, a3818f1e, 21 Oct
- Three steps. bd1fae14, 2 Nov
- A send stopped for a missing name, city or email shows the message under that field. 9c3c4c15, 2 Nov
- Letter fields at 16px (no iOS zoom), Next on the phone keyboard. 953210a2, 2 Nov
- Columns 1fr 2fr from 1200; recipient at the theme H3 size. fec9e9cb, da74eb76, 2 Nov
- On the theme's form kit; placeholders repeat the labels in plain words; field icon on the own-words box. f4ea78bb, 4337fb5e, 2 Nov
- Sideways rows named and keyboard-focusable. f2c66d94, 3e3f17b9, 2 Nov
- Unticked grounds open with one button. 0bb120a1, 3 Nov
- On phones the letter folds open with one button, "Show the whole letter" / "Show less", instead of scrolling inside a small box. 4cfcb0f0, 3 Nov

## Share ("Get three more letters in")
- Share box with a message and a link preview from the page's social data; on phones Share sends picture, message and link together. 0f677cce, 21 Oct
- Story (9:16) and post (4:5) pictures to share or save; share pictures are JPG, never AVIF (owner, 19 Oct). 44bfb673, 21 Oct
- The letter share box shows all the time. 1296ae4c, 21 Oct
- The story and post pictures are share templates, not shown on the page. f76e3506, 2 Nov
- Short link `/lab`; Share and Copy link use it. 8f3b2067, 2 Nov
- One main "Share this page" button over the share bar. 188e57a4, 4db1e969, 2 Nov
- One Share block on every campaign: a band in the campaign colour, white main button, icon share bar, the ready-made pictures. 597d300d, 74df45af, 9026fd5d, 880d66f0, 816ada37, 5afec03e, 246151ad, 474a9356, 3088b8cb, 4e55a9f9, 2 Nov
- Hashtags for X: the X share link carries #StopAnimalTesting #WithdrawAnimalTestingRules #AnimalFreeScience; WhatsApp and email unchanged. 2fffe9c5, 2 Nov
- "It takes under a minute." beside the main button. 08f608f6, 2 Nov
- Share works the same on all three campaigns: the shared picture file is named by its real type, one status message at a time, the bar's X link carries the share text and the hashtags, "Or share a ready-made picture" over the pictures, a white focus ring on the band. 246151ad series, 2 Nov; checked on Lab, Diwali and Supreme Court (native share with pictures, copy fallback, keyboard order, no console errors).
- A "Hashtags for X" field on the campaign editor (`aw_campaign_share_hashtags`). 2fffe9c5, 2 Nov

## What people are saying
- Shows people's own social posts about the campaign; hidden while there are none. 9fc74211, 21 Oct; a26863de, f6e6b41c, 31 Oct
- "On social media" lists only social posts linked to Lab; hidden while empty (it is hidden). c9b93a3b, 2 Nov
- "What people are saying": approved social posts linked to the campaign, one card per person (6 people, once each). 2e978ca7, 0bb120a1, 3 Nov
- The 15 approved social posts are shown on the page (owner, 3 Nov: "Show them live").

## In the news
- Press items carry the campaign; a Campaign filter in Media & PR. 3b5dda7a, 17 Oct
- Press cards show the article photo (logo only when there is none). 5b1742f2, 17 Oct
- Guides show their own pictures (stamp tile removed). 62550b42, 17 Oct
- Up to 12 press items (all the campaign's coverage). f7a31895, 21 Oct
- Sideways rows with no summaries on campaign cards (21 Oct), later replaced by stacked cards. b9f33008, 21 Oct
- Lists read the campaign link (`aw_campaign_link`), Kits card markup. c3b780a5, 2b119bbb, 21 Oct
- All coverage links to `/press-coverage/?campaign=`. ed4936b9, 21 Oct
- Stacked cards, no sideways row. eea7118f, c53ac16e, 2 Nov
- 6 press items, then 8 guides; the guides stay here and are also linked under the sections they explain. cb905419, 0bb120a1, 3 Nov
- Phones and tablets: the meta row (date, place, source chip) spans the card on top, square picture beside the full title; nothing cut at 320, 375 or 768. 391b473e, 17bc55ba, 79a5d2e3, 3 Nov

## Find help near you
- A band of 12 states, from the place names in News, Library and guides. a8b772ab, 24 Oct
- Kept on the page. ecff7a47, 3 Nov

## Read the law and the record (Library)
- All Library documents and guides linked to the campaign, in query loops. feb7b369, 17 Oct
- Stacked cards with a picture and the card background. 35b84ace, 2 Nov
- Cards fit at 320 with the whole source name and title. d0d6dbf4, 3 Nov
- Titles in full at every width (9 of 12 were cut from 768). 5721f9d4, 3 Nov
- "Read the full draft in our Library" is a 44px target on phones. 953210a2, 6c17a820, 2 Nov; 87d91861, 3 Nov
- Library drafts imported: the draft rules, the 1998 Rules and the related documents. ed835c9a, 16 Oct
- Guides: rule by rule, who approves, today vs the draft, how to object, pain scale, IAEC, CPCSEA, Palamur timeline, alternatives, testing laws. b06d2ced, 14f61b61, 16 Oct
- Guide call to action box on the six lab guides and the Library draft. 1bf1b476, 16 Oct
- The NAMs paper added to the Library and linked to the campaign (owner's yes, 2 Nov; the two tinyurl emails skipped).
- NAMs card picture: media 24027, `nams-organ-on-a-chip-device-lab-bench.avif` (1200x800), title "Organ-on-a-chip device on a lab bench", alt "A small clear organ-on-a-chip device on a lab bench beside a pipette and a petri dish." Image and alt approved by the owner, 3 Nov. Set as the picture of Library item 23913; checked on the live page.

## What happens next
- A roadmap: step cards, a vertical timeline on phones, the next step highlighted. 228bf825, af2ec664, 2 Nov

## Newsletter
- Volunteer banner after the content; the support section is not printed until it has content. 76918ad8, 17 Oct
- No second newsletter form on the page (the site footer has one). 5e6b1fcb, 16 Oct
- The newsletter band above the footer on every page. ac1a32f1, 2 Nov
- Get updates as a tinted card in the campaign colour. 4b6046b3, 2 Nov
- The email footer strip names the campaign: "Labs could approve most animal tests themselves · closes 5 Nov"; the strip is the owner's HTML as written, only the days-left block changes. f8ed17ba, 18 Oct
- 3 Nov: one email to the 115 subscribed contacts tagged with the campaign; part of the send is resent on 4 Nov.

## Phone bar
- Sticky "Send your objection" bar after the hero, with days to go. 482990d1, 16 Oct; 9b5d34f4, 17 Oct
- Keeps its height free when scrolling to a focused control. 69972b62, e78e13ec, 2 Nov

## Build script (`tools/content/campaign2-page.php`)
- The page is rebuilt from the script; it carries the section order, keyphrases, summary, share line and search title and description. 58b78491, 3f42e475, 21 Oct; c9b93a3b, 08f608f6, 2 Nov
- Guide row shows 8. 4138f7cd, 1 Nov
- Guides stay in In the news; What people are saying after On social media. cb905419, 2e978ca7, 3 Nov
- Back to the 2 Nov morning version on the owner's word. 2ebacf8e, 2 Nov
- Phone fixes shipped together (Today vs the draft cards, Read the law cards at 320, rule chips, letter fold, guide links). fdc45928, 3 Nov
- Repo leftovers removed (one-off tools that had already run). 7d687986, 25 Oct

## SEO and schema
- Campaign schema about its Library law; rule cards as an ItemList with their own anchors. 482990d1, 16 Oct; 71eb987f, 17 Oct
- Keyphrases: the campaign owns "g.s.r. 864(e)", "gsr 864e" and "cpcsea-mef@gov.in"; the rule-by-rule guide and Library draft keep "draft lab animal rules 2026, ccsea draft rules". 1 Nov (pending.md)
- Library draft H1 is the full official name plus "(GSR 864E)"; SEO title ends "PDF"; a "G.S.R. 864(E) PDF" heading above the PDF view; main date 6 Oct 2026 with "Dated 30 Sep 2026" as a second line. 1 Nov (pending.md); owner, 24 Oct
- The campaign's publish date stays fixed; only dateModified moves when the build script runs.
- SEO titles keep the hyphen in cpcsea-mef@gov.in. 7b1a5425, 3 Nov
- New lede, summary and search title and description, on the owner's yes ("Yes, all of them", 2 Nov). 08f608f6

## Sourcing and admin
- Sourcing topic rules for `campaign-lab-animals-2026` and the campaign's press keywords (`aw_sourcing_kw`) set, 2 Nov.
- The Dashboard letters box: the top-grounds and top-cities tables are named Ground and City and print no repeated header row; the Dates seed has no 7 Nov Post-phase entry. 4fd6ebf8, 3 Nov
- Shared section templates (What's happening, the bento, Read the law, What happens next, stacked news, the Share band) are the same on Lab, Diwali and Supreme Court, phones first and then 1080 and up (owner, 2 Nov).
- Links shared with `#` written as `%23` redirect (`…/%23how-to-help` 301s to `#how-to-help`, checked 3 Nov). 5765dc0d, 2 Nov
- Search (28 days to 31 Oct): campaign page 16,102 impressions, 1,653 clicks, position 3.8; Library draft 5,861 / 505 / 3.5.

## Standard and schema check (3 Nov, live)
Checked against the Campaign Content Standard v1.0 (17 Oct).

| Item | Result | Note |
|---|---|---|
| Section anchors equal slugs | Pass | hero, context, why-it-matters, how-to-help, news, library, faq, next-steps |
| Section order | Owner's order | Questions after the news and the Library (owner, 21 Oct); the standard puts faq before news |
| impact, support, partners | Hidden | No count on the page (owner, 21 Oct; 1,012 confirmed on 3 Nov); no support content; no partners listed |
| press | Merged | Into In the news (owner) |
| What people are saying, Find help near you | Owner's sections | Not slugs in the standard; "What people are saying" is a slot, "Find help near you" is not |
| One H1, H2 sections, H3 modules | Pass | Rule cards and pain tiers are H3 |
| Dates never joined into one string (A3) | Pass | Hero: "2 days to go" and "Objections close: 5 Nov 2026" are separate elements, the date in `<time>`; phone bar shows days only |
| Meta rows, date first | Pass | All 20 card meta rows start with the date |
| Newsletter above the footer | Pass | After the volunteer band |
| Settings: open, close, count threshold, doc | Pass | Set by the build script |
| Settings: hashtags, story image, post image, short link | Pass | All render on the page (X share, downloads, /lab) |
| Post image size label in settings | Fixed | Said 1080×1080; the image is 1080×1350 |
| JSON-LD parses, one block | Pass | |
| CreativeWork | Pass | Dates, description, image, about (Library law, two topics), creator |
| FAQPage | Pass | 7 questions, all in the visible text |
| BreadcrumbList | Pass | Home › Campaigns › page |
| ItemList (6 rule changes), DefinedTermSet (pain scale) | Pass | Names visible; anchors #change-1 to 6 exist |
| Title, description | Pass | Description and og/twitter text say "by 5 Nov 2026" (wording after the 5th is the owner's) |
| Canonical, robots | Pass | Self canonical; index, follow |
| og:image, og:image:alt, twitter card | Pass | summary_large_image |
| Sitemap | Pass | In aw_campaign-sitemap.xml |
| hreflang | None | English only; none needed |

### Post state: readiness only (3 Nov)
The owner decides the Post phase on 7 Nov. Nothing for Post is built before then.

**What happens at 00:00 IST on 6 Nov (code read).** Nothing closes the page. There is no closing job, and only the owner's Post setting (override plus a note) ends Active. Until then:
- The hero, the letter tool, Share and "Send your objection" all stay. The sent counter keeps counting.
- Hero countdown: "Today" on 5 Nov. From 6 Nov it reads "Took place on Objections close: 5 Nov 2026", next to the live letter tool.
- Phone bar: says "Last day today" from 5 Nov until Post.
- Home band: keeps "objections close 5 Nov 2026", "Your objection counts until 5 November" and "Send your objection". Its countdown turns to "Took place on".
- The page cache stops at 00:00 IST on 6 Nov, so these show at once.

So from 6 Nov until the owner sets Post, the page and Home still ask people to send objections after the window has closed (standard D4 and E).

| Item | Exists | Missing | Owner decides |
|---|---|---|---|
| Outcome with an as-of date | Outcome field; shows "Outcome: Pending. As of 5 Nov 2026" in Post by default | | The outcome and date |
| Letter tool, Share, hero buttons, phone bar off | All hide in Post; the counter refuses | | When Post starts |
| Closed hero | Owner's copy (17 Oct) in the build script | | The copy, on 7 Nov |
| "Get updates on these rules" button | In the closed hero | Links to `#updates`, which the page does not have | Target (for example `#newsletter`), on 7 Nov |
| Count in the closed hero | `[aw_campaign_count]`, from 100 | Label has no "by the campaign's count"; the count is self-reported "Yes, I sent it" clicks (1,012 on 3 Nov), so it will show | Removed (owner, 10 Nov): not in the live page content, and taken out of the build script (3ffd9dd6) |
| Home band in Post | | Not tied to the state | Outcome-only band text, or the standard Home hero |
| Impact (Results, Real-world impact) | | No section | Numbers and words |
| how-to-help in Post (what the campaign did) | | Nothing replaces it | Words |
| FAQ | 7 questions | "Who can send an objection?" and "Does one email matter?" describe the open window | Which stay |
| What happens next | 4 steps, under the hero in Post | | Wording |
| Context, share text, meta, og | | "The public has 30 days…"; "by 5 Nov 2026" | New wording |
| Post order | Built: hero, next-steps, context, why-it-matters, people, news, library, faq | Standard: hero, why-it-matters, how-to-help, context, impact, next-steps, faq, partners, press, library, news, updates | Order |
| Share images, hero alt text | | | After 7 Nov |
| Dates entry "Lab Post phase" (7 Nov) | In Animal Writes → Dates | | Delete it (owner) |

## Speed and cache
- Inter from one file, preloaded on phones and tablets; the letter preview rebuilt once per frame; rule dialogs join the page on the chip's first tap. 33e61d9a, aee48e9c, 1 Nov
- Font fix: only the library weights the theme's Inter file covers are left out, so 800 headings have their own file again. 8edf5ee6, 1 Nov
- Lighthouse mobile on live: LCP 3.8 / 3.5 / 3.4 s, CLS 0 (was 0.108), time to first byte 1.1–1.4 s. LCP is still above 2.5 s; the phone LCP element is the hero H1 (Inter 800). bd51c4f9, 1 Nov
- CDN: `s-maxage=900`, 5 of 5 HIT; the cache is purged after every deploy.

## Results (to 3 Nov)
- Search Console, 28 days to 31 Oct: campaign page 16,102 impressions, 1,653 clicks, position 3.8; Library draft 5,861 / 505 / 3.5; rule by rule 3,355 / 185 / 5.0; testing-laws guide 4,556 / 166 / 4.1. The Lab pages drew most of the site's search clicks in that period.
- AI referrals: 96 landings on the Library draft, 72 on the campaign page.
- X, 27 Oct – 2 Nov: 264 Lab posts; #StopAnimalTesting 226, #WithdrawAnimalTestingRules 157, #AnimalFreeScience 133; posts with a picture averaged 171 views vs 25 for text only; 1 post linked animalwrites.in.
- Links: Hindustan Times, Free Press Journal and DT Next link to the site.
- Newsletter: one campaign, "Object to the draft lab animal rules 2026", to 115 volunteers on 3–4 Nov; all 115 sent (FluentCRM, checked 5 Nov). Opens: 23 contacts; clicks: 5 (see Off the page).
## Letters and data (letter log `wp_aw_camp_letters`, read 3 Nov 2026)
- 3,981 send taps from 3,593 sends since 17 Oct: Gmail 3,559, copy 375, email app 46.
- 3,169 taps came with the updates box ticked.
- Confirmed "Yes, I sent it": 671 (31 Oct), 730 (2 Nov), 1,012 (3 Nov).
- Daily taps: highest 744 on 24 Oct; about 120 a day 30 Oct–2 Nov (111, 122, 118, 154); 652 on 3 Nov, the day of the campaign email.
- Devices to 31 Oct: phone 2,770, desktop 254, tablet 27.

## Sent vs confirmed: where the letters drop off (12 Nov 2026)
- What the two numbers are. `objection_sent` fires when a person taps a send route: Send with Gmail, Email app or
  Copy letter. The letter then goes from the person's own mailbox to the ministry; the site sends no email for it.
  `objection_confirmed` fires when the person comes back to the page and taps "Yes, I sent it" (the counter on the page
  counts these). There is no confirmation email, so delivery, opens and bounces in MailWeb or FluentCRM say nothing
  about letters.
- 28 days to 11 Nov: GA4 10,365 sent and 2,313 confirmed (22%); the form's own count 9,996 and 2,236.
- Where it drops: between opening the mail app and coming back. 89% of taps are Send with Gmail and 90% of sends are on
  phones (letter log, 3 Nov): on a phone the Gmail app opens, the person sends, and often never returns to the browser
  tab, so they are counted as sent but not confirmed. The true number of letters sent lies between the two figures.
- Measuring it now: InsightWeb → Overview → Campaigns by source shows the confirm rate per source and per day
  (confirmed ÷ sent; per day from the next GA4 run).
- What this means for the plan of 12 Nov: a resend button, a confirmation email and a reminder do not apply to this
  flow (there is nothing to resend). What would help a future campaign is making the way back easy: a short "Did it
  go?" message the person sees on their return (the page already brings the box back on reload), and counting sends
  without waiting for the tap. Copy and any change to the Lab page are the owner's call; the Lab page is closed.
- Grounds 1, 2 and 6 are in about 8,000 letters each and the others in 2,300–2,600: most likely ticked by default, so
  the data does not show which argument people chose. Future campaign forms: no ground ticked by default, or record
  "ticked by the person" separately (owner's call; nothing changed on the Lab form).

## Media & PR and sourcing
- Press items published: the Hindustan Times explainer (13949) and four Supreme Court items (19497, 19500, 19503, 19506), with written titles for the last three. Pharmabiz (13959) is back in draft.
- Repeats folded into the first story ("also reported by"): CNBC TV18 into 12972; Arogya Legal, Urban Acres and DrugsControl into 13639. Telangana Today's copy (14236) is private, merged into 13639, with a 301.
- Media & PR items get the campaign link by keyword on save (Sourcing campaign rules). f6e6b41c, 31 Oct
- The campaign's rule phrases (2 Nov): the 10 earlier ones plus the committee's full name, animal experimentation, animal research, experiments on animals, laboratory animals rules, lab animal rules, animal testing rules, gsr 864 / gsr 864e / gsr864 / gsr 864 e / 864(e), institutional animal ethics committee, animal ethics committee, breeding and trading of laboratory animals, new approach methodologies, cpcsea-mef, animal testing bill, draft prevention of cruelty to animals 2026, #ccsea, #gsr864e, #labanimalrules.
- Press search run on 31 Oct: 0 new, 11 own page, 11 not relevant, 18 not classified (the free AI check was unavailable).

## X (read of 27 Oct–2 Nov, 264 posts, a sample)
- Main tag set: #StopAnimalTesting #WithdrawAnimalTestingRules #AnimalFreeScience (88 posts together). #WithdrawAnimalTestingRules is the campaign's own tag; #SayNoToAnimalExperimentationBill had 12 posts.
- Posts with a picture or video averaged 171 views and 9 reposts; text-only posts 25 and 1.
- Links people shared: a tinyurl objection form (35), In Defense of Animals (26), PETA India (29), a lung-on-a-chip study on nature.com (10), change.org (6). One post linked to this page.
- Most-tagged accounts: @Dept_of_AHD (26), @ICMRDELHI (8), @IndianExpress (6).
- Used on the page: the three tags on the X share link (2fffe9c5) and "What people are saying" (2e978ca7).
- Search: the tag phrases have almost no Google searches ("stop animal testing" 1 impression in 28 days); people search the rule words.

## X and Google (read of 10 Nov 2026, read only)
- X: others still share this page (one account's post with the link had 24 reposts; two more accounts linked it). Our own Lab post (31 Oct) had 28 views; the account has 1 follower. Tags in use: #WithdrawAnimalTestingRules, #SayNoToAnimalExperimentationBill, #StopAnimalTesting, #AnimalFreeScience. The Hindu ran an "In Focus" episode on the draft on 9 Nov (a Media & PR candidate; not added).
- Google (signed out, India): "draft laboratory animals rules 2026" shows the Library draft at 2 and this page at 7; "animal testing laws in india" shows this page at 3, the testing-laws guide (13870) not on page 1. New searches seen: "draft laboratory animals rules 2026 pdf (download)", "draft rules on animal experimentation 2026", "animal experimentation rules 2026", "gsr 864 e pdf". The first four are in Search Insights already (622 and 634 impressions for the two experimentation phrases); the PDF phrases went to the keyword tools' seeds.
- Nothing posted, liked or followed; no page change (the Lab page changes on the owner's word only).

## Off the page
- Newsletter (3–4 Nov), one campaign: "Object to the draft lab animal rules 2026", to the 115 subscribed contacts tagged with the campaign, from noreply@ with Reply-To contact@, started 18:51 IST on 3 Nov. Result: 115 of 115 sent by 19:00 IST on 4 Nov. Status: archived.
- How it went out: 71 on the first run; 44 went through PHP mail() during a deploy (33 failed at 18:53 with "Could not instantiate mail function", 11, contacts 22–31 and 68, were marked sent but never left), so those 44 got the same email again on 4 Nov. Cause: a deploy re-clones the plugin folder, so mailweb was missing for about 10 seconds and FluentCRM fell back to PHP mail(), which the host refuses.
- In FluentCRM the one send shows as three rows, because the free version cannot resend inside a campaign: 2 (the first run, 115 listed), 3 (the 33, tag 22) and 4 (the 11, tag 23). All three are archived; nothing is waiting.
- Fixes: the ship script refuses to deploy while a campaign is sending; AW Mail Guard (`tools/server/aw-mail-guard/`) holds FluentCRM batches while mailweb is missing, installed once outside the deploy folder. e855e321
- Own accounts: X @animals_writes and LinkedIn /company/animalwrites, each with a pinned Lab post (31 Oct).
- Short link `/lab` (was `/lab-rules`); `/lab-rul/` and about 85 Rank Math redirects, including lab-rules link typos (1 Nov). 8f3b2067
- Observance guides World Rabies Day (20209), Animal Welfare Fortnight (20305) and World Wildlife Day (20306) hold Related rows to the Library (1 Nov).
- Dates calendar (Animal Writes → Dates) seeded with 5 Nov (lab rules close) and 7 Nov (decide the Post phase). c8c51fcd, 21 Oct
- Media library (4 Nov): 45 campaign pictures, 24146–24190, descriptive names, 24146 and 24162 carry text. Placed (owner, 10 Nov: "yes"; checked on live):
  - Pain-scale cards, in the page content (no build-script rerun): Mild 24183, Moderate 24189, Severe 24164, Non-recovery 24169; shown at every width.
  - Guide pictures: rule-by-rule (13854) 24152, was 16337; who approves (13855) 24147, was 16338.
  - News 12972 ("Explained", Hindustan Times): 24157, was 12984; the publisher's photo is turned off (aw_source_image_off).
  - Library 13904 (Delhi High Court, PETA India v. CCSEA): 24163 in place of the cat photo.
  - Not placed: the other five guide pictures (24184, 24181, 24176, 24171, 24158 are 319–762px wide, below the 1200×900 covers); the section pictures (the sections have no picture slot; owner, 10 Nov: no slot, leave them); the Post pictures (the page is still Active); the share bases 24153 (1200×630), 24154 (1080×1350) and 24155 (1080×1920), big enough, for the share images; the spares.
- Social posts by page section (card, story, X, Instagram, LinkedIn, WhatsApp), each with #AnimalWrites first: drafts, not posted.

## Wait-period wording (owner's yes on all 15 lines, 13 Nov 2026)
- Live on 13848 (content, meta and Rank Math; revision 28038 is the version before):
  - search title "Draft Lab Animal Rules 2026 (GSR 864E): What Happens Now";
  - search description "Objections to G.S.R. 864(E), the "animal testing bill", closed on 5 Nov 2026 at cpcsea-mef@gov.in. The final rules are not out yet. See what the draft changes.";
  - a status line under What's happening ("Are the draft lab animal rules final? Not yet. Status on 13 Nov 2026: …"), linking to the newsletter;
  - "The public had 30 days, until 5 Nov 2026, to send objections …";
  - Share heading "Share what the draft would change" and its new lede;
  - share message "India's draft lab animal rules (GSR 864E), in plain words:" and hashtags AnimalWrites, LabAnimalRules, StopAnimalTesting;
  - the question "What happens after 5 November?" is now "Are the draft lab animal rules 2026 final?" with the new answer (FAQ block and FAQPage schema; the question id is kept);
  - What happens next, step 1: "Objections closed / 5 Nov 2026".
- Code (455f1678): hero date chip `[aw_countdown … past_label="Objections closed:"]` shows "Objections closed: 5 Nov 2026"; phone bar `[aw_sticky_cta … after_label="Closed 5 Nov · decision awaited"]` replaces "Last day today"; Home hero top line "objections closed 5 Nov 2026", summary "Objections closed on 5 Nov; the final rules are not out yet.", date line "Last day was 5 Nov 2026".
- Unchanged: the "Send your objection" buttons (the tool stays on), the H1, the timeline's "Last day for objections", card dates.
- Not covered by the 15 lines: the link-preview picture still says "Objections close 5 November 2026" (needs a new picture); the letter tool's share box heading "Get three more letters in" (campaign-lab.js); the build script and the home-band script still carry the old lines (do not rerun them over live); the email strip.

## Wait period across the site (owner's yes "Yes, all", 13 Nov 2026)
- Live: campaign summary (the "Current campaign" side card and the page's schema description) now says objections closed on 5 Nov 2026 and the final rules are not out yet; the guide cards on 13854–13858, 13869, 13870 and Library 13844 say the same, and their button is "See what happens next" to the campaign page; FAQ answers on 13854 and 13870 (FAQ box) and the 13870 body say "closed"; the Library draft says objections "could be sent" until 5 November 2026.
- Link preview: Media 28138 `lab-rules-2026-og-v3.jpg` (v2 with "Closed 5 Nov · decision awaited" and "See what happens next"), set as the Facebook and X image; v2 kept in Media.
- Code (branch lab-wait-blocks-1113): the guide-cta pattern; the Lab email strip after the close shows "5 NOV / CLOSED" and "See what happens next"; the letter tool's share box after the close asks people to follow Animal Writes on X, Instagram, Threads, LinkedIn and Facebook for the decision (owner, 13 Nov), in place of "Get three more letters in".
- Partner outreach: dropped (owner, 13 Nov: "no partners").

## Decided by the owner
- Palette A, Gazette crimson (17 Oct).
- Bands white, grey, black or brand only; every band title an H2 (19 Oct).
- No objection count in the hero or an "Objections sent" section (21 Oct). The public count shows only from 100 confirmed letters (`aw_campaign_metric_min`, default 100).
- Section order and the removal of Other ways to send (21 Oct).
- Pain-scale tiers as H3 (25 Oct).
- "On social media" shows only posts linked to Lab and hides while empty (31 Oct, 2 Nov).
- Page kept as on 2 Nov morning; no landscape-phone work (2 Nov).
- The letter tool on the theme's form kit (2 Nov).
- Hashtags for X only, the three above (2 Nov).
- 6 people once each; 6 news items then 8 guides; "Find help near you" kept (3 Nov).
- The pain meter (3 Nov).
- Nothing closes the campaign by date; it closes only when its state is set to Post (2 Nov).
- No reminder email to the senders who ticked the updates box (3 Nov).
- Keep the theme H1 size and the compare-table headers that went live on 5 Nov (5 Nov).
- The pictures plan: four pain-scale cards, two guide covers, News 12972 and Library 13904 placed; no picture slot for the sections (10 Nov).
- No letter counter on the page (10 Nov).
- Social posts archived for now (10 Nov).

## Still open
- Letters: the target was 5,000. On 3 Nov, 1,012 people had answered "Yes, I sent it" (`aw_campaign_sent`); the letter log holds 3,981 send taps from 3,593 sends (see Letters and data below).
- Outreach planned but not done: newsletters of 19 and 26 Oct, the X thread, LinkedIn document post, WhatsApp card, partner kit, countdown stories.
- PCA Act 1960 Chapter IV is not in the Library.
- Opening the page from a link ending in `#rules` or `#faq` stayed at the hero in the built-in browser (desktop and 375px), while the "What the rules say" button and a later `#rules` worked. Not yet checked on a real phone or another browser.
- "cpcsea-mef@gov.in" searches: 93 impressions, 3 clicks, position 6.8 (to 31 Oct); a search title or description that answers the address needs the owner's text.

### Owner's call (recorded only)
- Library draft: a campaign link on its first screen (the guide box sits about three phone screens down; moving it is a template change).
- UTM links for the X and LinkedIn bios; a per-minute cap on the public sent counter.
- AW Mail Guard: install the plugin zip once.
- FluentCRM: one list per source (later).
- X: which posts go on the page; repin the own post with /lab, the hashtags and a picture.
- Label for the copy-the-hashtags chips.
- Google title for the email-address search (copy).
- The owner removes the 7 Nov "Decide the lab rules Post phase" entry in Animal Writes → Dates (owner: "I'll remove it").

### Technical, open
- Live (checked 5 Nov, 519bc1f9, 59d846cb): the phone bar's days on one line at 320, the compare table no longer a scroll row, "Show the post" and "Read the full text" names, JSON-LD numbers.
- One source chip reads "Hindu"; the paper is "The Hindu" (copy).
- A leftover Diwali rule `.aw-camp-steps--six` in campaign-lab.css.
- Off-scale heading overrides and one hard-coded disc colour in campaign-lab.css.
- Accessibility leftovers to re-check: share and Library link names, a list item outside its list, table header scope, one date at 2.55:1, 12px chip text, enterkeyhint.
- Step-2 wording "Enter this" (copy).
- Wrong pictures on media 13904 (a cat) and 12972 (a dog).
- Share and Volunteer photos are shared by three campaigns. (The NAMs card picture was set on 3 Nov.)
- Internal-link and small search fixes (proposals).
- Press search run for the campaign after the next Sources sync.

### Record only (dates, close and the Post phase are the owner's; no reminders)
- The campaign does not close by date on 6 Nov; it closes only when its state is set to Post (owner, 2 Nov).
- Kits theme rebuild: campaign CSS work waits until the Lab page moves to its Post phase.
- The campaign closes only when its state is set to Post. Until then the phone bar shows "Last day today" after the last day, and the meta, share text and social images keep the 5 November wording.
- The closed-state copy exists in the build script and shows on Post; the Post phase (closed content, FAQ visibility, What happens next) waits for the owner. A post-deadline news piece is in the plan, not written.

## Rules for this page
- Lab page: dates and wording only on the owner's word; new visible words need the owner's yes on the exact text.
- Theme tokens only (`kit.css`, `docs/design-system.md`, `docs/card-sizes.md`, `docs/component-map.md`); reuse Kits components first, and say in a CSS comment why when a local layout is kept.
- The page stays as on 2 Nov morning (owner); design-system rule 4 and the 24px block padding rule do not apply here; the no-sideways-rows rule does.
- Breakpoints 767/768 and 1199/1200; 44px targets on phones; WCAG 2.2 AA.
- The campaign's publish date stays fixed; only dateModified moves.
- Home hero stays the Lab campaign until the owner changes it.
- Card meta rows start with the date.
- The campaign page is not held to the 720px main column (campaign landing page).
- Campaign items link through `aw_campaign_link` (term 1077), not tags.
- No test events on live GA4.
- No deploy while a newsletter campaign is sending.
