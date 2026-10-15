# Site health: Animal Writes, 15 October 2026

Phase 9 of the SEO programme. It covers Core Web Vitals and Lighthouse, ads and image layout shift, caching, mobile, HTTPS and www, robots.txt, the Rank Math 404 monitor and redirect chains. The fixes were deployed the same day: commits 254dd8a, 8c7ffab and 75f5c74, plus the server settings listed below.

## How it was measured

- **PageSpeed Insights API:** every call returned 429 "Quota exceeded … Queries per day", the same as earlier today. There is no field (CrUX) data from it this time.
- **Lighthouse 13.5.0 instead:** run locally with `npx` in headless Chrome, using simulated mobile and desktop throttling.
- **Pages tested:**
  - Home;
  - /news/;
  - a listing (/directory/org/a-dogs-tale/);
  - a guide (/resources/how-to-report-animal-cruelty/).
- **How to read the numbers:**
  - "Before" is one run.
  - "After" is the median of 5 runs, taken after the fixes and with the page cache warmed.
  - Lab results on this connection vary a lot between runs (performance 49–98 for the same page), so the medians matter more than any single run.

## Core Web Vitals (lab)

| Page | Device | Before: perf / LCP / CLS / TBT | After (median of 5): perf / LCP / CLS / TBT | SEO | Accessibility | Best practices (before → after) |
|---|---|---|---|---|---|---|
| Home | mobile | 78 / 2.5 s / 0 / 440 ms | 66 / 3.4 s / 0 / 443 ms | 100 | 100 | 73 → 77 |
| Home | desktop | 78 / 1.8 s / 0.009 / 10 ms | 91 / 1.3 s / 0.010 / 11 ms | 92 → 100 | 100 | 73 → 77 |
| /news/ | mobile | 53 / 5.9 s / 0.004 / 340 ms | 72 / 4.1 s / 0.004 / 314 ms | 100 | 100 | 73 → 77 |
| /news/ | desktop | 72 / 3.5 s / 0.001 / 10 ms | 79 / 2.3 s / 0 / 7 ms | 100 | 100 | 73 → 77 |
| Listing | mobile | 89 / 1.9 s / 0 / 330 ms | 72 / 3.5 s / 0 / 403 ms | 100 | 97 | 73 → 77 |
| Listing | desktop | 95 / 0.7 s / 0 / 10 ms | 98 / 0.7 s / 0 / 18 ms | 100 | 97 | 73 → 77 |
| Guide | mobile | 96 / 2.0 s / 0 / 170 ms | 62 / 6.8 s / 0 / 260 ms | 100 | 100 → 96 | 100 |
| Guide | desktop | 96 / 1.3 s / 0 / 10 ms | 93 / 1.2 s / 0 / 7 ms | 100 | 100 → 96 | 100 |

**What the numbers say:**
- **CLS is good everywhere** (0.010 or less), both before and after. Card pictures sit in fixed frames, the ad slots reserve their space (100 px on phones, 90 px from 768 px), and the Home hero has its width and height. Ads and images cause no measurable layout shift.
- **/news/ improved most.** On mobile, LCP went from 5.9 s to 4.1 s and performance from 53 to 72, because the first card picture now loads at once with high priority. On desktop, LCP went from 3.5 s to 2.3 s.
- **Home desktop SEO 92 → 100.** The 92 came from a robots.txt fetch that timed out in that one run. robots.txt returns 200 and is valid.
- **Best practices 73 → 77.** The console error "adsbygoogle.push() error: No slot size for availableWidth=0" is gone from every page (see "Ads" below). What still costs points is a third-party cookie from doubleclick.net, set by AdSense.
- **Mobile got worse on Home, the listing and the guide.**
  - Ads now actually load. The ad fix let AdSense size its units, and the pages now carry more third-party script and creative weight: the Home page grew from 1.6 MB to 2.9 MB on mobile.
  - Lab TBT on phones is driven by gtag/GTM, Clarity, AdSense and GSAP (kit.js).
  - **The guide's mobile LCP (6.8 s median) needs a follow-up.** Its main picture loads in under 0.5 s but renders late. This was not explained before wrap-up.
- **Accessibility, still open:**
  - the guide's related-card tag (`.kit-tag--info`) fails colour contrast;
  - the listing's `dl.aw-org-facts` fails the definition-list check.
  - Both are small template fixes.

## Fixes deployed

| Fix | Where | Proof (live) |
|---|---|---|
| The first picture in a list page (a hub, category or tag; not Home, not an item's own page) loads eagerly with `fetchpriority="high"`. Every later one stays lazy. | `plugins/animal-writes-core/includes/media.php` | /news/: 1 `fetchpriority="high"` picture, 1 `loading="eager"`; the guide keeps a single high-priority picture (its own lead) |
| Publisher pictures get `width`/`height` when the size was measured at ingest (`aw_source_image_w/h`, 60 items). The others sit in fixed card frames: CLS 0–0.01. | same | CLS table above |
| Inter is served once: the theme's variable file (400–700, `font-display: swap`). The Font Library's overlapping 400–700 files (3 × 118 KB, no swap) no longer load; its italics and other weights stay. No preload: on slow phones the preload competed with the main picture. | `themes/animal-writes/functions.php` | 0 Font Library 400–700 faces in the head of Home, /news/ and the guide; the Lighthouse "font-display" finding (an estimated 3,000 ms saving) is gone |
| AdSense units get the slot's width. In the flex box they had shrunk to 0, so AdSense could not size any ad ("No slot size for availableWidth=0" on every page). | `themes/kits-by-wugweb/assets/css/kit.css` (generic, reusable) | The console error is gone from all 8 runs |
| Hub Pages 301 to their trailing-slash address in one hop (/news → /news/, /library → /library/, /news/page/2 → /news/page/2/). Their own pagination is still not "corrected". Before, /news and /library answered 200 as duplicates. | `plugins/animal-writes-core/includes/hubs.php` | /news 301 → /news/; /news/page/2/ 200 |
| Rank Math Image SEO no longer writes the page title into empty alts and titles ("Library", "Media & PR 1"). | `plugins/aw-seo-extras/includes/helpers.php` (filter, no settings change) | /media/: alts are the headline or the publisher's name; no "Media & PR" alt or title |
| LiteSpeed "Serve Stale" turned on (`purge-stale` 0 → 1). After a purge, visitors get the cached page while it is rebuilt, instead of waiting for PHP. | LiteSpeed option | `wp litespeed-option get purge-stale` = 1 |
| Rank Math redirects from the 404 monitor: /directory/org/ → /directory/, /apple-touch-icon.png and /apple-touch-icon-precomposed.png → the site icon (180 px). | Rank Math Redirections | each returns 301 |

## Caching

- **HTML:** LiteSpeed page cache is on, with a public TTL of 1 day and 7 days for the front page. A warm request is a `x-litespeed-cache: hit`, with about 0.4 s TTFB from Mumbai.
  - Home and /news/ often show `miss`.
  - LiteSpeed purges the whole site on `create_term` and `edit_terms` (`purge-hook_all`). The ingest workers create categories and tags, so each new term clears every cached page.
  - Serve Stale (above) softens this. Taking `create_term` and `edit_terms` out of "purge all" is an owner decision (see below).
- **Static files:** CSS, JS, fonts and uploads are sent with `cache-control: public, max-age=31536000, immutable`. Lighthouse's "cache" finding is only about Google's AdSense script (14 days, not ours).
- **CDN (hcdn):** HTML is never cached at the edge (`x-hcdn-cache-status: MISS` on every request), so every page view goes to the origin.

## Mobile, HTTPS and robots.txt

- **Mobile:** the viewport meta is `width=device-width, initial-scale=1`. Lighthouse SEO is 100 on all four pages in both modes.
- **HTTPS:**
  - HSTS is `max-age=31536000; includeSubDomains`, and `content-security-policy: upgrade-insecure-requests` is set.
  - There is no `http://` asset on Home.
  - `X-Content-Type-Options` and `Referrer-Policy` are not sent (a server setting; low SEO impact).
- **robots.txt:** returns 200. It disallows /wp-admin/, allows admin-ajax.php and points to the sitemap index.
- **Sitemap and llms.txt:** /sitemap_index.xml 200, /llms.txt 200. The seven hubs all return 200.

## Redirect chains

| From | Hops | Result |
|---|---|---|
| http://animalwrites.in/ | 1 | https://animalwrites.in/ |
| https://www.animalwrites.in/ (and any https://www. path) | 1 | non-www |
| http://www.animalwrites.in/ (and paths) | **2** | http://www → https://www (Hostinger edge, `server: hcdn`) → https://animalwrites.in |
| https://animalwrites.in/?author=1, /author/admin/ | 1 | /about/ |
| http://www.animalwrites.in/?author=1 | **3** | the edge hop above, then www → non-www, then author → /about/ |
| /location/delhi/ | 1 | /directory/delhi/ |
| /topic/community-dogs/ | 1 | /category/community-dogs/ |
| /news, /library, /resources | 1 (was 0, a duplicate 200) | trailing slash |

The `?author=N` chain is one hop on https. Both remaining extra hops come from the Hostinger edge sending http://www to https://www before WordPress or .htaccess sees the request. Only hPanel can change that.

## Rank Math 404 monitor

The monitor module is on. It holds 225 logged URLs and 514 hits, the last on 15 Oct 2026 at 22:35. There are 528 active Rank Math redirects.

| URL | Hits | Verdict |
|---|---|---|
| .well-known/traffic-advice | 88 | Chrome's private prefetch proxy check; a 404 is correct |
| no-such-page-kits, no-such-page, this-page-does-not-exist-xyz, probe | 51 | our own test requests; correct |
| directory/org/wugweb-sample-listing | 20 | the preview-only sample listing; a 404 for visitors is intended |
| wp-content/plugins/home/…/kits-core/assets/js/ad-slot.js | 16 | the block editor's ad-slot script URL built from a symlinked plugin path. The code now prints it inline when kits-core is outside wp-content/plugins. The last hit was 15:03, before that fix. |
| news/… (about 15 headlines, 3–9 hits each) | about 60 | News items taken off as not relevant (actors, temples, politics). A 404 is correct: no redirect to an unrelated page. |
| sitemap_index.xml | 7 | logged on 14 Oct, during the rewrite flush; 200 now |
| directory/org, apple-touch-icon(-precomposed).png | 12 | **redirected today** (above) |
| topic/stray-animals | 4 | no such category; left as a 404 |

## For the owner (hPanel or a decision)

1. **http://www in one hop (hPanel → Domains / SSL / CDN).** The Hostinger edge first sends http://www to https://www, then WordPress sends that to non-www: 2 hops, and 3 with ?author=N. In hPanel, either make "Force HTTPS" redirect straight to the main domain, or turn on a "www → non-www" redirect at the edge.
2. **CDN HTML caching (hPanel → CDN).** The Hostinger CDN never caches HTML (always MISS), so every view waits about 0.4 s for the Mumbai origin, and longer after a purge. Turning on edge caching for HTML, with LiteSpeed purges passed to the CDN, would cut TTFB for repeat views.
3. **LiteSpeed "Purge All" hooks.** `create_term` and `edit_terms` purge the whole site whenever a category or tag is created or edited, which the ingest does. Removing those two from Purge All Hooks (LiteSpeed → Cache → Purge) would keep pages cached. Rank Math and the sitemap still update on publish.
4. **Rank Math General → Images:** "Add missing ALT attributes" and "Add missing TITLE attributes" are still on in the settings. My attempt to turn them off was blocked by auto mode. Their output is now filtered out in code, so turning them off in the settings is tidy-up only.
5. **Security headers (hPanel or .htaccess):** add `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`.
6. **Ads and phones:** now that ads fill, phone pages carry about 1–1.4 MB more of third-party script and creative. If mobile LCP matters more than ad fill above the fold, consider no ad above the first content on phones.

## Not finished (wrap-up)

- **The guide's mobile LCP (6.8 s median, was 2.0 s in the single before-run).** The main picture loads quickly but renders late. Check whether the newly filled ads, or the related-items block deployed by another session today, delay it.
- **Accessibility:** `.kit-tag--info` contrast on related cards, and `dl.aw-org-facts` structure on listings.
- **PageSpeed Insights (field data):** re-run when the API quota resets, for real-user CrUX numbers.
