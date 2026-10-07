# Animal Writes 2.0: status

Last checked on the live site: **7 Oct 2026**. One file, kept current: newer numbers replace older ones.

**Site and stack**
- **Live:** https://animalwrites.in · **Admin:** /wp-admin · **1.0 archive:** old.animalwrites.in (cancelled; the 1.0 site will be deleted).
- **Stack:** WordPress 7.1.3 + PHP/MySQL on Hostinger.
  - **Themes:** Kits by Wugweb (master), Animal Writes (child).
  - **Plugins:** Animal Writes Core (also loads Kits Core), LiteSpeed Cache, Site Kit.
- **Workers:** GitHub Actions (`animal-writes-workers`, private).
- **Deploys:** branch `wordpress` deploys automatically to `public_html/aw_deploy`.

## 1. Decisions taken

| Area | Decision |
|---|---|
| Brand | The orange is replaced. The design system and CSS are done (see section 8). |
| Indexing | Open to all crawlers. |
| AWBI directory data | Published as unverified listings with the source shown. |
| 2.5 lakh letters claim | Left as is. |
| Government and court documents | Hosted on the site, with the original source linked on every document. Reasons: links break, our pages rank in search, and Copyright Act s.52(1)(q) allows reproducing Acts and judgments. |
| Theme | Master/child. Generic parts live in Kits (master) and Kits Core; the child holds the brand and Animal Writes templates; features live in plugins. |
| News attribution | Google News pattern: favicon + source on cards (plain text); the source is linked on the detail page. |
| Ingestion | Metadata + our 2–3 line summary only, never full articles. Government PDFs are the exception and are hosted. Items are reviewed by a person before publishing. |
| Databases | One MySQL database. Volunteers live in their own table (`aw_volunteers`). |
| Email list | Our own database (self-hosted), not Brevo. Sent through the site's own mailbox (`noreply@animalwrites.in`, WP Admin → Settings → Mail). |
| CAPTCHA | ALTCHA: self-hosted proof-of-work, no keys, on every form. |
| Featured listings | Free for now, through the claim form; 90 days, then off automatically. |
| Listing verification | The claim form plus the admin's Approve. |
| 1.0 receipts | Personal data and photos dropped; the total count (16,754) is kept. |
| Retention | **Archive, never delete** (section 5). |
| Tags | Site Kit is the only tag source. |
| FTP password | Closed (7 Oct). |

## 2. Pillars: plan (2.0 PDF) vs now

| Pillar | Live | Gaps |
|---|---|---|
| News & Updates | 18 | NGO announcements, rescue stories and research highlights have no source yet |
| Legal Watch | 11 | no actual order or judgment documents in the stream (Acts and Rules are in Research) |
| Research Library | 10 (PCA Act 1960; ABC Rules 2023; Pet Shop 2018; Dog Breeding 2017; Performing Animals 2001; Slaughter House 2001; Transport on Foot 2001; Transport of Animals 1978; SC judgment of 22 Aug 2025; WHO rabies fact sheet) | policy papers, reports, RTIs |
| Knowledge Centre | 9 guides (injured animal, reporting cruelty, ABC explained, community dogs, the laws in plain words, adoption, feeding, living alongside animals, FAQs) | more topics as needed |
| Campaign Hub | 1 (1.0 case study + campaign kit) | awareness, adoption, sterilisation drives, fundraisers |
| Opportunities | 18 (4 by hand; WWF-India and Wildlife SOS sourced automatically, AWBI notices watched) | grants and CSR calls (NGOBox, DevNetJobs after terms check) |
| Resources | 8 (1.0 letter templates; police complaint and RTI templates) | adoption agreements, toolkits, printables |
| Directory | 4,819 | see section 3 |

**Added beyond the PDF:**
- helplines (112, 1962, AWBI, state boards);
- an honest visitor counter;
- an accessibility pass;
- legal pages;
- the claim/update and "Report an error" forms.

**Monetisation:**
- **Built:** ad slots with AdSense units (section 6), and Featured logic (none sold).
- **Not built:** sponsored articles or campaigns, CSR packages, paid listings, affiliates.

## 3. Directory

| # | Planned | Now |
|---|---|---|
| 1 | Coverage: NGOs, shelters, rescue, vets, ambulances, wildlife, feeders | **4,819 listings**: AWBI organisations 3,630 (Gaushala 1,692); ABC centres 64; 1962 Mobile Veterinary Units 28; **government district veterinary offices 191** (UP 75, Maharashtra 34, Telangana 33, Karnataka 30, West Bengal 19); **Kerala government veterinary hospitals 191 and dispensaries 737**; 13 1.0 supporter NGOs. Shelters, rescue groups, wildlife rescue: 0 |
| 2 | Find by city, state, category, services | ✅ state/district pages, filters, search, homepage state finder. ❌ services field empty |
| 3 | City × type SEO pages | ✅ every place × type page with listings is in the sitemap (grows with each import) |
| 4 | Contact details | ⚠️ **324 with a phone** (official numbers only: 1962 units, district offices, Malappuram institutions), **1,092 with an official email**. The AWBI and ABC lists contain no contacts. Officers' names and personal mobiles are never stored |
| 5 | Verification status + date | ✅ built; every listing shows "Not yet confirmed" plus its source and list date. 0 verified |
| 6 | Claim/update workflow | ✅ on every listing, admin-approved. 0 claims |
| 7 | Contact verification process | ✅ claim form + admin Approve (owner's decision) |
| 8 | Periodic re-verification | ✅ daily job flags listings verified more than 180 days ago |
| 9 | Report an error | ✅ on every listing. 0 reports |
| 10 | Featured profiles | ✅ free for now: "Also feature this listing" box on the claim form, 90 days on approval. 0 featured |
| 11 | Structured data | ✅ NGO or VeterinaryCare on listings, ItemList on directory pages |
| 12 | Logos and photos | ⚠️ 13 supporter logos only |
| 13 | Map / near me | ❌ no coordinates |
| 14 | Seeding from public sources | AWBI list, AWBI ABC list, DAHD 1962, five states' district vet offices, Kerala institutions. **Not used:** Haryana (2,900 names, no phones, PDF too irregular to parse reliably); Punjab (2020, personal mobiles); Tamil Nadu (needs a data.gov.in API key); Bihar, MP, Rajasthan, Gujarat, AP (nothing usable published); NGO Darpan (captcha, unclear terms) |
| 15 | 1.0 supporters' locations | waiting for the next NGO data (they are ordinary NGOs, not partners) |

**How the imports work:** `tools/directory/README.md` (idempotent, never deletes, unverified, source shown). The sources are also listed in `animal-writes-workers/SOURCES.md`.

## 4. Sourcing map

| Module | What comes in | Source | How often | Who |
|---|---|---|---|---|
| News | title, source, date, link, image URL, our summary | Google News keyword feeds, The Hindu RSS | every 6 h | workers, then **automatic review** (below) |
| Legal Watch | same, plus PDFs for government notices | AWBI pages, PIB (via Google News), LiveLaw, Bar & Bench, SC Observer | every 6 h | workers, then **automatic review** |
| Opportunities | title, organisation, place, deadline, link (factual line, no AI wording) | WWF-India careers, AWBI notifications (workers); Wildlife SOS careers (fetched by the site daily) | every 6 h / daily | published directly |
| Research | full PDFs | AWBI, SC, WHO | one-off | Claude Code |
| Directory | name, code, area, type, phone where published | AWBI lists, DAHD | one-off per list | Claude Code (`tools/directory`) |
| Directory updates | changes | the organisations | on request | claim form + admin |
| States/districts | 36 + 820 | LGD | one-off | Claude Code |
| Helplines, guides, campaigns, opportunities, resources | content | government sources, 1.0, by hand | one-off | Claude Code |

**Automatic review (owner's decision, 7 Oct 2026):** a news or legal item goes live when it passes every check: relevance (owner's keyword lists), not a web story/video/gallery, not the same story as one published in the last 7 days, legal items from a legal or government source, a sane summary. Anything else waits in Pending with the reason in the "Review" column; the owner reviews only those.

**Sourcing controls:**
- **Where:** WP Admin → News → Sourcing.
- **Publisher allow and block lists.** Blocked: bollyspice.com, storyboard18.com.
- **Relevance keywords**, edited in the same screen.
- **The workers read this policy before every run.**
- **The "allowed publishers only" switch is off** by owner's choice: Apple News would otherwise be cut too.

## 5. Retention: archive, never delete

| Content | Rule |
|---|---|
| News | indexed for 6 months; after that noindex; after 12 months off the news list and sitemap, page still reachable |
| Opportunities | closed after the deadline: off the list and sitemap, page still reachable |
| Legal Watch, Research, Guides, Resources, Campaigns, Directory | permanent |

## 6. SEO, analytics, ads, performance

| Item | Status |
|---|---|
| Sitemap | `/wp-sitemap.xml`: content, topics, places pointing to directory pages, 823 directory place × type pages; no users or sample-post lists |
| Search Console | connected through Site Kit (`sc-domain:animalwrites.in`). **Owner: submit `wp-sitemap.xml`** |
| Structured data | WebSite + Organization (homepage); NewsArticle (news, Legal Watch); Legislation/Article (research); Article (guides); breadcrumbs; listing schema |
| hreflang | not applicable: English-only site with no Hindi page versions |
| Analytics | GA4 through Site Kit's Google tag (GT-TXZHFR39). Old GTM-W9D28GKV removed, which ended double counting |
| GTM | container GTM-TB3FCFQB created; content and attaching it are deferred |
| AdSense | code loaded by Site Kit; 3 slots (homepage, after article, section lists); none on /helplines/; empty slots take no space. Site in review; ads.txt correct (`/ads.txt` returns 200) |
| Cache and images | LiteSpeed Cache + Hostinger CDN, WebP. News and Legal Watch show our own topic photos (11 of 18 topics have one) |
| Background jobs | run on page visits, with the GitHub workflow as backup; 0 overdue |

## 7. Data

| Data | Where | Status |
|---|---|---|
| Live site | Hostinger MySQL | live |
| Volunteers | `aw_volunteers` (admin list, export, delete) | **88** 1.0 volunteers recovered (from the session log and campaign screenshots); the 2.0 form writes here |
| 1.0 Supabase export (18,222 rows: volunteers + receipts) | — | **lost.** Exported and checksum-verified on 5 Oct, then the project was deleted and every local copy was gone by 7 Oct. Two whole-disk scans found nothing; Supabase can't restore it |
| Consent | volunteer and newsletter forms | consent tick box on both (DPDP). 1.0 volunteers must opt in before any newsletter: the invitation in WP Admin → Newsletter carries a confirm link |
| Newsletter subscribers | `aw_subscribers` (WP Admin → Newsletter: counts, CSV export, send) | sign-up on Get involved with email confirmation; 0 so far |

## 8. Theme and design system
- **Kits master:**
  - token engine with neutral defaults;
  - all generic components, states, motion and print rules;
  - header and footer parts, and placeholder patterns.
- **Kits Core plugin:** forms, share, SEO, ads, visit counter, source attribution and sourcing controls (Kits step 4, 7 Oct; Animal Writes sets its news and legal types through `kits_sourced_post_types`).
- **Animal Writes child:** brand token values and domain styles only.
- **Docs:** `docs/design-system.md` (scales, states, component specs) and `docs/accessibility-audit.md`.
- **Accessibility:** 0 WCAG 2.1 AA automated failures (20 pages × desktop and phone, 7 Oct). Visual QA found no sideways scrolling, no broken images and no text overflow.

## 9. 1.0 → 2.0

| 1.0 | 2.0 |
|---|---|
| Story sections, impact stats, FAQ, press, gallery | 1.0 case study in the Campaign Hub |
| Letter templates | Resources (6) |
| Supporters | Directory (13) |
| Logo, colours, font | theme |
| Volunteers | 88 recovered (rest lost, see section 7) |
| Receipts | count only (16,754) |
| Visitor counter, countdown, confetti, admin pages, testimonials | dropped |
| Exposed 1.0 endpoints | function deleted |

## 10. Notion
- **🐾 Animal Writes 2.0:** build status and a **Pending** section (merged from the ⏳ Pending page, which now points there), updated 7 Oct.
- **Topic write-ups and photos** table: the owner fills it in; Claude copies it to WP Admin → Topics.

## 11. Pending

**Owner, open:**
1. Mail: the `noreply@animalwrites.in` password in WP Admin → Settings → Mail.
2. The 1.0 invitation text, sent from WP Admin → Newsletter.
3. Submit `wp-sitemap.xml` in Search Console.
4. Topic write-ups and photos (Notion table).
5. Optional: data.gov.in API key (Tamil Nadu); Anthropic console monthly limit.

**Waiting, no action needed:** AdSense review, and ads.txt switching to "Authorized".

**Later:** more directory states; GTM container content; a second photo search after the owner's pictures.
