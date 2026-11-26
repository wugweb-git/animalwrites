# Animal Writes: Tenant Operations, Content, Campaigns & Data Pending

> **Context**: This document tracks the active roadmap and pending tasks that are **strictly site-specific to Animal Writes** (`animalwrites.in`), cleanly decoupled from the upstream **Kits Suite** theme and companion plugins.

---

## 1. Campaigns & Advocacy Pages

### A. Diwali Pet Safety & Animal Welfare (`/diwali/`)
- **Status**: Live campaign page active; periodic seasonal review.
- **Pending Tasks**:
  - Verify emergency clinic hotline widgets in top metro cities (Delhi NCR, Mumbai, Bengaluru, Pune, Kolkata).
  - Update downloadable community awareness posters (PDF/SVG).
  - Confirm noise-safety guidelines match current Animal Welfare Board of India (AWBI) advisories.

### B. Supreme Court Animal Law & Community Dogs PIL (`/sc/`)
- **Status**: Landmark orders tracked; case hearing summaries active.
- **Pending Tasks**:
  - Update case tracker for upcoming Supreme Court hearings on Animal Birth Control (ABC) Rules 2023.
  - Cross-link related judgments from the Library (`/library/`) to directory listings in affected municipal corporations.
  - Maintain FAQ schema nodes for legal rights of community animal caretakers.

### C. Laboratory Animals Rules 2026 (`/lab/`)
- **Status**: Draft rules analysis published in Library; consultation closed 5 Nov 2026 (`tools/content/lab-animals-campaign-page.php`, renamed from `campaign2-page.php`).
- **Pending Tasks**:
  - Update `rank_math_title` / `rank_math_description` to post-consultation wording and ship pending Share/Date-card edits.
  - Expand alternative non-animal testing models directory.

### D. Community Dogs Rights & ABC Rules (`/community-dogs/`)
- **Status**: RWA guidelines and feeding rights live.
- **Pending Tasks**:
  - Standardized legal notices template for feeders facing RWA harassment.
  - Municipal corporation complaint escalation numbers.

### E. Rabies Awareness & Prevention (`/campaigns/rabies-awareness-india/`)
- **Status**: Standalone campaign builder added 26 Nov 2026 (`tools/content/rabies-campaign-page.php`, no petition/letter tool).
- **Pending Tasks**:
  - Run `wp eval-file tools/content/rabies-campaign-page.php`, review draft, and publish in `wp-admin`.
  - Update `tenant/assets/site/patterns/rabies-card.php` on Home to link to `/campaigns/rabies-awareness-india/` once published.

---

## 2. Directory Data, Verification & Claim Flow (7,738 Listings)

### A. Listing Claim Flow (B5 / B6 implementation from `docs/cc-master-plan-19-nov.md`)
- **Status**: ⏸️ **Placed on HOLD pending upstream Kits platform maturation** (owner decision, 24 Nov 2026). B4, C1, C2, C3 completed live.
- **Held Tasks**:
  - B5 (3-step claim form & Indian address/initials schema tolerance): Held until upstream Kits Core form engine and theme components mature.
  - B6 (Post-approval profile completion portal): Held until B5 is live and measured.
  - Duplicate clean-up (Option C): Comprehensive live DB audit completed 24 Nov 2026 (see `docs/directory-duplicates-audit.md`). 58 government listings protected; 20 top NGO duplicate clusters mapped with canonical IDs and draft/redirect targets. Ready for owner approval.

### B. India Local Government Directory (LGD) Normalization
- **Status**: State and district taxonomy mapped via `tools/lgd/`.
- **Pending Tasks**:
  - Verify district reorganization in newly partitioned districts (e.g. Madhya Pradesh, Chhattisgarh).
  - Geocoding verification for rural animal health centers.

---

## 3. Content, SEO & Help-Now Guides

### A. Help-Now Rescue Action & Regulatory Guides
- **Status**: ✅ **12 core evergreen guides confirmed live (24 Nov 2026)**. Validated directly on `https://animalwrites.in` (all return HTTP 200) and in git:
  1. **1962 Veterinary Helpline & MVUs**: `/resources/1962-helpline-mobile-veterinary-units/` (200 OK)
  2. **Emergency Who to Call**: `/resources/who-to-call-for-an-animal-emergency-in-india-112-1962-local-groups/` (200 OK)
  3. **Cruelty Law (PCA §11 & BNS §325)**: `/resources/animal-cruelty-law-india/` (200 OK)
  4. **How to Report Animal Cruelty**: `/resources/how-to-report-animal-cruelty/` (200 OK)
  5. **Community Dogs & Feeding (ABC Rule 20)**: `/resources/caring-for-community-dogs-in-your-area/` (200 OK)
  6. **Injured Animal Protocol**: `/resources/found-an-injured-animal-what-to-do/` (200 OK)
  7. **Avian / Bird Helplines by City**: `/resources/injured-bird-helpline-numbers-by-city/` (200 OK)
  8. **Animal Birth Control Explained**: `/resources/animal-birth-control-abc-explained/` (200 OK)
  9. **AWBI Role & Contact**: `/resources/animal-welfare-board-of-india-awbi-explained/` (200 OK)
  10. **Rabies PEP & Dog Bite Vaccine Schedule**: `/resources/dog-bite-rabies-vaccine/` (200 OK)
  11. **NGO Darpan Registration & Search**: `/resources/ngo-darpan-registration/` (200 OK)
  12. **World Animal Day**: `/resources/world-animal-day-4-october/` (200 OK)
- **Reference Notes**: Preserved in `research_notes/` (`helpline_1962.md`, `cruelty_law.md`, `feeding_law.md`, `rabies_pep.md`, etc.) as background research and audit trail.
- **Pending Tasks**:
  - Emergency protocols: Canine Distemper isolation, Parvovirus care, snakebite rescue, abandoned newborn pup feeding.
  - State FIR filing guides: Step-by-step FIR procedures under PCA Act 1960 and BNS provisions across all 28 states.

### B. Search Insights & Keyword Action Plan (17 Nov 2026 Plan, Steps 0–9)
- **Status**: Steps 0–4 active; keyword tracking monitored via `InsightWeb`.
- **Pending Tasks**:
  - Step 5–7: Internal link cross-pollination between high-ranking news articles and relevant city directory archives.
  - Step 8–9: Expand long-tail queries for "free veterinary hospital near me" and "24x7 animal ambulance [city]".

---

## 4. Martech Automation & External Workers

### A. Workers Registry (`docs/workers.json`)
- **Status**: Verified in CI (matches `animal-writes-workers`).
- **Pending Tasks**:
  - Monitor Cloudflare Worker background jobs for scrapers and automated syndication.
  - Refresh social broadcasting hooks for `@animal_writes_` (Twitter/X, Instagram, LinkedIn).

### B. Discover & News Feed Ingestion
- **Status**: Google Discover and News sitemaps active via `RankWeb`.
- **Pending Tasks**:
  - Expand RSS sourcing feeds in `SourceWeb` for animal court orders and municipal notices.
  - Maintain automated UTM parameters on outbound newsletter links.

---

## 5. Hostinger VPS Production Operations

- **Deployment Script**: Maintain safety gates in `tools/kits-ship.sh`.
- **CI Guardrails**: 46 checks in `tools/ci/` (WCAG AAA contrast, zero duplicate photos, FluentCRM send guard).
- **Cache Invalidation**: LiteSpeed cache purge nudge loop after Hostinger git pull.
