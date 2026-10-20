# PageSpeed audit: 16 key pages, mobile and desktop (20 October 2026)

Nothing has been changed. Every item below waits for the owner's decision.

## How it was measured

- **Field data:** PageSpeed Insights (pagespeed.web.dev), "real users", Chrome UX Report, last 28 days.
- **Lab data, PageSpeed Insights:**
  - mobile for home, /news/, one news item, /directory/ and /directory/kerala/;
  - desktop for the first four of those.
- **Lab data, Lighthouse 13.5.0 run locally:** all 16 pages, mobile and desktop. This is the same engine and the same Moto G Power / slow 4G simulation that PageSpeed uses.
  - PageSpeed's own lab runs stalled for the other pages, and its fetch of llms.txt had also timed out earlier.
  - Lab scores vary between runs by roughly ±10.

## Real users (field data)

**Core Web Vitals pass for the site, on mobile:**
- LCP 1.7 s;
- INP 107 ms;
- CLS 0;
- FCP 1.4 s;
- TTFB 0.9 s.

Search Console agrees: 259 mobile URLs are good and none are poor. Individual pages do not have enough traffic for page-level data, so these are origin-wide numbers. Desktop has no field data yet.

## Lab scores (Lighthouse, local)

| Page | Mobile | FCP | LCP | TBT | CLS | Desktop | LCP | TBT |
|---|---|---|---|---|---|---|---|---|
| Home | 82 | 1.8 s | 3.0 s | 378 ms | 0 | 96 | 1.1 s | 10 ms |
| /news/ | 70 | 2.1 s | 4.5 s | 286 ms | 0.004 | 88 | 1.8 s | 10 ms |
| News item | 61 | 2.0 s | 5.2 s | 491 ms | 0.001 | 96 | 1.0 s | 18 ms |
| /directory/ | 78 | 1.7 s | 2.4 s | 580 ms | 0 | 98 | 0.7 s | 14 ms |
| State (Kerala) | 81 | 1.8 s | 2.4 s | 452 ms | 0 | 99 | 0.7 s | 13 ms |
| District (Pune) | 82 | 1.7 s | 2.3 s | 436 ms | 0 | 100 | 0.6 s | 13 ms |
| Profile (Wildlife SOS) | **56** | 1.9 s | **7.0 s** | 593 ms | 0 | 100 | 0.6 s | 11 ms |
| /library/ | 83 | 1.6 s | 3.2 s | 344 ms | 0.007 | 98 | 0.7 s | 9 ms |
| Library item | 71 | 2.8 s | 4.6 s | 276 ms | 0.021 | 100 | 0.7 s | 11 ms |
| /resources/ | 85 | 1.8 s | 2.4 s | 353 ms | 0.007 | 95 | 1.1 s | 14 ms |
| Guide | 91 | 1.7 s | 2.4 s | 254 ms | 0 | 99 | 0.8 s | 12 ms |
| /opportunities/ | 85 | 1.8 s | 2.4 s | 351 ms | 0 | 99 | 0.7 s | 9 ms |
| Opportunity | 84 | 1.8 s | 3.1 s | 330 ms | 0 | 100 | 0.6 s | 10 ms |
| Campaign (lab rules) | **61** | 2.5 s | **5.8 s** | 458 ms | 0.075 | 99 | 0.8 s | 11 ms |
| /campaigns/ | 79 | 1.6 s | 3.6 s | 324 ms | 0.056 | 95 | 1.0 s | 14 ms |
| About | 82 | 1.8 s | 2.4 s | 414 ms | 0 | 98 | 0.8 s | 10 ms |

**PageSpeed Insights lab, for comparison:**

| Page | Mobile score | Mobile LCP | Mobile TBT | Desktop |
|---|---|---|---|---|
| Home | 89 | 2.9 s | | 97 |
| /news/ | 57 | 11.4 s | | 86 |
| News item | 66 | 6.9 s | | 95 |
| /directory/ | 59 | 6.6 s | 440 ms | 88 |
| Kerala | 72 | | 1,440 ms | |

**Summary:** desktop is good everywhere. Mobile is held back by LCP on four page types (profile, news item, news list, campaign) and by about 300–600 ms of blocking JavaScript on every page.

## What needs doing (proposals, in order of effect)

### 1. Load animation hides the main content on mobile (theme, kit.js)

**What happens:** the motion script sets the first two sections to opacity 0 and fades them in after GSAP has loaded (deferred) and run. Until then the LCP text or picture is invisible.

**Measured render delay before LCP:**
- profile: 2.2 s (the LCP is the bio text);
- campaign: 1.3 s (the H1);
- the same pattern on news items and the home hero.

**Proposal:** keep the scroll-in motion for lower sections, but show the first two sections at once (no fade on load), or animate them with a small slide only (no opacity).

**Expected effect:** profile and campaign mobile LCP down by about 1–2 s; mobile scores up by roughly 10–20.

**Note:** this is a design (motion) change, so it needs the owner's go-ahead.

### 2. Render-blocking CSS and JavaScript on every page (about 0.7–1.3 s mobile)

Files that block the first paint:

| File | Where | Proposal |
|---|---|---|
| fluent-forms-public.css, fluentform-public-default.css | every page | load only where a Fluent Form is on the page, or after load |
| accessibility-checker srOnlyFormat.css and frontendFixes.bundle.js (sync) | every page | turn the plugin's front-end fixes off if the theme already covers them, or defer them |
| advanced-ads advanced.js and advertisement.js (ad-blocker check), sync in head | every page | defer, or drop the ad-blocker check |
| jQuery and jQuery Migrate (sync) | every page | defer, or load only where Fluent Forms needs them |
| campaign.css and campaign-record.css | home and /campaigns/ | inline the few rules the home band needs, or load them non-blocking |
| tokens.css, aw.css, kits-cache CSS (three files) | every page | merge into one file and inline the critical part |
| WordPress emoji script | every page | remove (the site does not use emoji images) |

### 3. Third-party JavaScript on mobile (main-thread time per page load)

- **The scripts:**
  - AdSense: about 220 ms, with 120 KiB unused;
  - the GA4 gtag script loaded by GTM: about 270–370 ms;
  - GTM: about 170–200 ms;
  - Clarity: about 170–190 ms.
- **Already done:** GTM starts after load or on the first tap.
- **Options for the owner:**
  - start Clarity only on a share of sessions, or after the first interaction;
  - lazy-load AdSense slots below the fold (the Advanced Ads lazy-load setting).

### 4. Pictures on news lists and news items

- **News cards use larger files than needed:** /news/ cards request 1024 px AVIFs for small card slots. The `sizes` value is "auto, (max-width: 1024px) 100vw, 1024px". Wasted per page: 360 KiB on mobile and 704 KiB on desktop. Proposal: give card pictures a `sizes` value that matches the card width, so the 300–768 px versions are used.
- **News item picture:** the featured picture is the LCP and has no fetchpriority="high" (Lighthouse flags it as not prioritised and lazy-loaded), so it starts loading 1.1 s late. Proposal: load it eagerly with high priority on item pages, as the home hero already is.
- **Home hero:** it is preloaded three times, by WordPress plus two Optimization Detective preloads. Proposal: keep one.

### 5. Smaller items

- **Cache lifetimes:** about 27–94 KiB of static files have short cache lifetimes. Proposal: a 1-year browser cache on theme and plugin assets that carry ?ver=.
- **Font display:** saves 250–470 ms. The Inter preload applies only at ≥1200 px, so mobile waits for the font. Proposal: preload it on mobile too, or use font-display: optional.
- **Layout shift on campaign pages:** CLS 0.056–0.075 on /campaigns/ and the campaign page, below the 0.1 limit. The cause is the countdown strip and phone bar arriving late. Proposal: reserve their height.
- **Large DOM on /library/:** about 1,100 elements. No action unless it grows.
