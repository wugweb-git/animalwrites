# Lab rules campaign 2026: page record

Page: `/campaigns/lab-animals-rules-2026/` (campaign 13848; short link `/lab`).
Subject: the Draft Laboratory Animals Rules, 2026 (G.S.R. 864(E)), published in the Gazette on 6 Oct 2026; objections to `cpcsea-mef@gov.in` until 5 Nov 2026.
Library draft: 13844 (PDF 13845). Guides: 13854–13858, 13869. Campaign link term: 1077.
Build script: `tools/content/campaign2-page.php`. Styles and scripts: `plugins/animal-writes-core/assets/css/campaign-lab.css`, `assets/js/campaign-lab.js`; lifecycle, counters and shortcodes in `includes/campaign-tools.php`, `campaigns.php`, `campaign-letters.php`.

Every hash below is on `wordpress` (live) as of 3 Nov 2026. Sections are in page order.

## Hero
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

## What the draft does
- Icon grid of the changes, with rule cards that have their own anchors (`change-1` …). 8a4e8888, 17 Oct; 71eb987f, 17 Oct
- A bento: one crimson lead tile, supporting tiles, the last tile full width. e2dffa11, 1297afda, c28dc76a, 601df136, 2 Nov
- Three rows from 1200 (the lead and open markets, then three thirds, then the closing card); supporting cards keep their hover shadow. 8cf3b022, 054abcbb, 1afd535a, 2 Nov
- Guide links under the section, 44px targets in body type, with the open-book guide icon. 54045334, 2f259100, 3 Nov

## Pain scale
- Pain ladder of the four tiers (mild, moderate, severe, non-recovery). 8a4e8888, 17 Oct
- Bars restored. 04c97133, 19 Oct
- Tiers are H3 under the section's H2, same look. bca59647, 25 Oct
- One thin pain bar; it stays thin on desktop. fb40f4e5, 2 Nov; 0bb120a1, 3 Nov
- A pain meter: each tier's bar is four segments lit to its level and fills once on first view; on phones a rail down the left fills on scroll, with crimson nodes on Severe and Non-recovery; reduced motion or no JS shows it full. cdf06b2b, 3 Nov
- Rule chips are 44px targets on phones. 696bb4d9, 48313da0, 3 Nov
- Guide links white on the dark section. 54045334, 3 Nov

## Today vs the draft
- Comparison table; no tint on the draft column. feb7b369, 17 Oct; 50621a47, 19 Oct
- No sideways scrolling row. c53ac16e, 2 Nov
- On phones, one card per row, so the Draft 2026 column is never off the screen. 74cc0801, 3 Nov

## Send your objection (letter tool)
- Letter tool: pick grounds, then Gmail, Outlook, the email app or copy; confirmed-letter count. 482990d1, 16 Oct
- Stepped letter tool. 8a4e8888, 17 Oct
- Anonymous statistics per letter (city, grounds, route, device, date; no name, email or IP); an unticked "Email me updates" box. 1464c105, 17 Oct
- Every send records the sender: name, city and email required; a contact tagged with the campaign. fba35301, 18 Oct
- The record-keeping sentence and the "Is my data stored?" question removed. 299dc952, 18 Oct
- Letter tool on a white card. d0586909, 19 Oct
- Other ways to send removed (by post first, then the section). b380d669, 6a5c3290, 21 Oct
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
- Story (9:16) and post (4:5) pictures to share or save. 44bfb673, 21 Oct
- Short link `/lab`; Share and Copy link use it. 8f3b2067, 2 Nov
- One main "Share this page" button over the share bar. 188e57a4, 4db1e969, 2 Nov
- One Share block on every campaign: a band in the campaign colour, white main button, icon share bar, the ready-made pictures. 597d300d, 74df45af, 9026fd5d, 880d66f0, 816ada37, 5afec03e, 246151ad, 474a9356, 3088b8cb, 4e55a9f9, 2 Nov
- Hashtags for X: the X share link carries #StopAnimalTesting #WithdrawAnimalTestingRules #AnimalFreeScience; WhatsApp and email unchanged. 2fffe9c5, 2 Nov
- "It takes under a minute." beside the main button. 08f608f6, 2 Nov

## What people are saying
- Shows people's own social posts about the campaign; hidden while there are none. 9fc74211, 21 Oct; a26863de, f6e6b41c, 31 Oct
- "On social media" lists only social posts linked to Lab; hidden while empty (it is hidden). c9b93a3b, 2 Nov
- "What people are saying": approved social posts linked to the campaign, one card per person (6 people, once each). 2e978ca7, 0bb120a1, 3 Nov

## In the news
- Press items carry the campaign; a Campaign filter in Media & PR. 3b5dda7a, 17 Oct
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

## What happens next
- A roadmap: step cards, a vertical timeline on phones, the next step highlighted. 228bf825, af2ec664, 2 Nov

## Newsletter
- No second newsletter form on the page (the site footer has one). 5e6b1fcb, 16 Oct
- The newsletter band above the footer on every page. ac1a32f1, 2 Nov
- Get updates as a tinted card in the campaign colour. 4b6046b3, 2 Nov
- The email footer strip names the campaign: "Labs could approve most animal tests themselves · closes 5 Nov". f8ed17ba, 18 Oct
- 3 Nov: one email to the 115 subscribed contacts tagged with the campaign; part of the send is resent on 4 Nov.

## Phone bar
- Sticky "Send your objection" bar after the hero, with days to go. 482990d1, 16 Oct; 9b5d34f4, 17 Oct
- Keeps its height free when scrolling to a focused control. 69972b62, e78e13ec, 2 Nov

## Build script (`tools/content/campaign2-page.php`)
- The page is rebuilt from the script; it carries the section order, keyphrases, summary, share line and search title and description. 58b78491, 3f42e475, 21 Oct; c9b93a3b, 08f608f6, 2 Nov
- Guide row shows 8. 4138f7cd, 1 Nov
- Guides stay in In the news; What people are saying after On social media. cb905419, 2e978ca7, 3 Nov
- Back to the 2 Nov morning version on the owner's word. 2ebacf8e, 2 Nov

## SEO and schema
- Campaign schema about its Library law; rule cards as an ItemList with their own anchors. 482990d1, 16 Oct; 71eb987f, 17 Oct
- Keyphrases: the campaign owns "g.s.r. 864(e)", "gsr 864e" and "cpcsea-mef@gov.in"; the rule-by-rule guide and Library draft keep "draft lab animal rules 2026, ccsea draft rules". 1 Nov (pending.md)
- Library draft H1 is the full official name plus "(GSR 864E)". 1 Nov (pending.md)
- SEO titles keep the hyphen in cpcsea-mef@gov.in. 7b1a5425, 3 Nov
- Links shared with `#` written as `%23` redirect (`…/%23how-to-help` 301s to `#how-to-help`, checked 3 Nov). 5765dc0d, 2 Nov
- Search (28 days to 31 Oct): campaign page 16,102 impressions, 1,653 clicks, position 3.8; Library draft 5,861 / 505 / 3.5.

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

## Still open
- Letters: the target was 5,000. Confirmed letters are under 100 (the public count is not showing); the admin letters widget and GA4 hold the full numbers.
- Outreach planned but not done: newsletters of 19 and 26 Oct, the X thread, LinkedIn document post, WhatsApp card, partner kit, countdown stories.
- After 5 Nov, until the state is set to Post:
  - the phone bar says "Last day today" (days left stops at 0);
  - the meta, social description and share text say "by 5 Nov" / "before 5 November";
  - the social images and their alt text say "Objections close 5 November 2026".
- The closed-state copy exists in the build script and shows on Post; the post-deadline news piece ("Objections closed: what happens now") is not written.
- Close day: 6 Nov in the campaign plan, 7 Nov in the theme rebuild plan; the Dates list has no Post entry.
- PCA Act 1960 Chapter IV is not in the Library.

## Rules for this page
- Lab page: dates and wording only on the owner's word; new visible words need the owner's yes on the exact text.
- Theme tokens only (`kit.css`, `docs/design-system.md`, `docs/card-sizes.md`, `docs/component-map.md`); reuse Kits components first, and say in a CSS comment why when a local layout is kept.
- Breakpoints 767/768 and 1199/1200; every block at least 24px padding; 44px targets on phones; WCAG 2.2 AA.
- Card meta rows start with the date.
- The campaign page is not held to the 720px main column (campaign landing page).
- Campaign items link through `aw_campaign_link` (term 1077), not tags.
- No test events on live GA4.
- No deploy while a newsletter campaign is sending.
