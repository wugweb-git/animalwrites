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
| Email list | Our own database (self-hosted), not Brevo. Sending route later. |
| 1.0 receipts | Personal data and photos dropped; the total count (16,754) is kept. |
| Retention | **Archive, never delete** (section 5). |
| Tags | Site Kit is the only tag source. |
| Homeward, FTP password | Deferred. |

## 2. Pillars: plan (2.0 PDF) vs now

| Pillar | Live | Gaps |
|---|---|---|
| News & Updates | 18 | NGO announcements, rescue stories and research highlights have no source yet |
| Legal Watch | 11 | no actual order or judgment documents in the stream (Acts and Rules are in Research) |
| Research Library | 10 (PCA Act 1960; ABC Rules 2023; Pet Shop 2018; Dog Breeding 2017; Performing Animals 2001; Slaughter House 2001; Transport on Foot 2001; Transport of Animals 1978; SC judgment of 22 Aug 2025; WHO rabies fact sheet) | policy papers, reports, RTIs |
| Knowledge Centre | 4 guides (injured animal, reporting cruelty, ABC explained, community dogs) | a guide to the laws, adoption, feeding, coexistence, FAQs |
| Campaign Hub | 1 (1.0 case study + campaign kit) | awareness, adoption, sterilisation drives, fundraisers |
| Opportunities | 4 (entered by hand) | NGO jobs, internships, fellowships, grants, CSR; no automated sourcing |
| Resources | 6 (1.0 letter templates) | complaint and RTI templates, adoption agreements, toolkits, printables |
| Directory | 3,700 | see section 3 |

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
| 1 | Coverage: NGOs, shelters, rescue, vets, ambulances, wildlife, feeders | **3,700 listings**: AWBI organisations 3,630 (Gaushala 1,692), ABC centres 64 (29 new from the AWBI ABC list of 29 Jun 2026, 35 AWBI organisations marked), 1962 Mobile Veterinary Units 28 (one per state, DAHD 18 Mar 2025), 13 1.0 supporter NGOs. Shelters, vet hospitals, rescue, wildlife: 0 |
| 2 | Find by city, state, category, services | ✅ state/district pages, filters, search, homepage state finder. ❌ services field empty |
| 3 | City × type SEO pages | ✅ 823 place × type pages in the sitemap |
| 4 | Contact details | ⚠️ 28 with a phone number (1962). The AWBI and ABC lists contain no phone, email or website |
| 5 | Verification status + date | ✅ built; every listing shows "Not yet confirmed" plus its source and list date. 0 verified |
| 6 | Claim/update workflow | ✅ on every listing, admin-approved. 0 claims |
| 7 | Contact verification process | ❌ not defined (owner) |
| 8 | Periodic re-verification | ✅ daily job flags listings verified more than 180 days ago |
| 9 | Report an error | ✅ on every listing. 0 reports |
| 10 | Featured profiles | ✅ logic works. 0 featured, no pricing (owner) |
| 11 | Structured data | ✅ NGO or VeterinaryCare on listings, ItemList on directory pages |
| 12 | Logos and photos | ⚠️ 13 supporter logos only |
| 13 | Map / near me | ❌ no coordinates |
| 14 | Seeding from public sources | AWBI list, AWBI ABC list, DAHD 1962. **Not available:** Tamil Nadu vet list (needs a data.gov.in API key); other states (only on state sites, so a per-state import); NGO Darpan (captcha, unclear terms) |
| 15 | 1.0 supporters' locations | waiting for the next NGO data (they are ordinary NGOs, not partners) |
| 16 | Homeward mapping | deferred |

**How the imports work:** `tools/directory/README.md` (idempotent, never deletes, unverified, source shown). The sources are also listed in `animal-writes-workers/SOURCES.md`.

## 4. Sourcing map

| Module | What comes in | Source | How often | Who |
|---|---|---|---|---|
| News | title, source, date, link, image URL, our summary | Google News keyword feeds, The Hindu RSS | every 6 h | workers, then human review |
| Legal Watch | same, plus PDFs for government notices | AWBI pages, PIB (via Google News), LiveLaw, Bar & Bench, SC Observer | every 6 h | workers, then human review |
| Research | full PDFs | AWBI, SC, WHO | one-off | Claude Code |
| Directory | name, code, area, type, phone where published | AWBI lists, DAHD | one-off per list | Claude Code (`tools/directory`) |
| Directory updates | changes | the organisations | on request | claim form + admin |
| States/districts | 36 + 820 | LGD | one-off | Claude Code |
| Helplines, guides, campaigns, opportunities, resources | content | government sources, 1.0, by hand | one-off | Claude Code |

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
| Cache and images | LiteSpeed Cache + Hostinger CDN, WebP. News images are hotlinked from publishers (own images optional) |
| Background jobs | run on page visits, with the GitHub workflow as backup; 0 overdue |

## 7. Data

| Data | Where | Status |
|---|---|---|
| Live site | Hostinger MySQL | live |
| Volunteers | `aw_volunteers` (admin list, export, delete) | **88** 1.0 volunteers recovered (from the session log and campaign screenshots); the 2.0 form writes here |
| 1.0 Supabase export (18,222 rows: volunteers + receipts) | — | **lost.** Exported and checksum-verified on 5 Oct, then the project was deleted and every local copy was gone by 7 Oct. Two whole-disk scans found nothing; Supabase can't restore it |
| Consent | — | 1.0 volunteers signed up for the campaign, not 2.0 updates, so they must opt in before any email (DPDP Act) |

## 8. Theme and design system
- **Kits master:**
  - token engine with neutral defaults;
  - all generic components, states, motion and print rules;
  - header and footer parts, and placeholder patterns.
- **Kits Core plugin:** forms, share, SEO, ads, visit counter.
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

## 10. Notion (done by Cowork)
- **Animal Writes 2.0:** build status set to live; Stack and Technology set to WordPress; duplicate Strategic Review removed; guardrail and hosted-documents changes noted.
- **"Animal Writes 3.0"** renamed Homeward.
- **⏳ Pending page** created.

## 11. Pending

**Owner, open:**
1. Submit `wp-sitemap.xml` in Search Console.
2. A data.gov.in API key, only for the Tamil Nadu vet list.
3. Directory business: Featured pricing and a sales process; who verifies a listing, and how.
4. Anthropic console monthly limit; `noreply@animalwrites.in` mailbox.

**In progress (Claude Code, approved 7 Oct):**
- Phone numbers: per-state vet hospital lists from state Animal Husbandry sites, larger states first. Availability is reported before import.
- Thin sections: drafts for the Knowledge Centre (laws, adoption, feeding, coexistence, FAQs) and Resources (complaint, RTI templates), for owner approval; Opportunities sourcing.

**Deferred:**
- GTM container content
- WhatsApp sign-up post for 1.0 volunteers
- email list, newsletter and 1.0 opt-in email
- CAPTCHA
- DPDP consent checkboxes
- Looker Studio
- Homeward
- FTP/hPanel password

**Waiting, no action needed:** AdSense review, and ads.txt switching to "Authorized".

**Optional:**
- Kits step 4 (attribution and sourcing into Kits Core)
- own topic images instead of hotlinked publisher images
