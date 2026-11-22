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
- **Status**: Draft rules analysis published in Library.
- **Pending Tasks**:
  - Update campaign timeline based on public comments submission to CPCSEA / Ministry of Fisheries, Animal Husbandry and Dairying.
  - Expand alternative non-animal testing models directory.

### D. Community Dogs Rights & ABC Rules (`/community-dogs/`)
- **Status**: RWA guidelines and feeding rights live.
- **Pending Tasks**:
  - Standardized legal notices template for feeders facing RWA harassment.
  - Municipal corporation complaint escalation numbers.

---

## 2. Directory Data, Verification & Claim Flow (7,738 Listings)

### A. Listing Claim Flow (B5 / B6 implementation from `docs/cc-master-plan-19-nov.md`)
- **Status**: B4, C1, C3 completed live (Nawabi Tails Darpan ID merged, post 18308 trashed, 301 redirect active, mail-in webhook alias secured, crawler tel: paths redirected to guides).
- **Pending Tasks**:
  - B5 (3-step claim modal): Owner confirmation to launch frontend modal on single listing profiles.
  - B6: Verification badge display and shelter administrator claim notification dispatch via `MailWeb`.
  - Duplicate clean-up: Review logged queue of 33 duplicate website pairs and 61 duplicate email groups (preserve data; merge or mark aliases).

### B. India Local Government Directory (LGD) Normalization
- **Status**: State and district taxonomy mapped via `tools/lgd/`.
- **Pending Tasks**:
  - Verify district reorganization in newly partitioned districts (e.g. Madhya Pradesh, Chhattisgarh).
  - Geocoding verification for rural animal health centers.

---

## 3. Content, SEO & Help-Now Guides

### A. Help-Now Rescue Action Guides
- **Status**: Initial guides live on `/library/`.
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
