# RankWeb: Rank Math Pro audit and build plan (14 Nov 2026)

Every feature Rank Math keeps for Pro (Business and Agency included), checked against what this site already has, and
what RankWeb will build. Sources: rankmath.com/offer (Free vs PRO table, read 14 Nov 2026), the module list of Rank Math
Free 1.0.282 on live (`includes/module/class-manager.php`: modules marked `probadge` are Pro only, `upgradeable` ones
have a Pro part), the knowledge base (rankmath.com/kb), and the code of RankWeb, InsightWeb, Animal Writes Core and Kits
Core on `wordpress-dev`.

Site facts used for the decisions (live, read only, 14 Nov 2026): WordPress 7.1.4 with the Abilities API; block theme
Kits by Wugweb; no WooCommerce, EDD, Elementor, Divi, bbPress or podcast plugin; 1,705 published News items, 6,872
listings, 212 Resources posts, 134 Library items, 24 pages; no published item with a password; Rank Math tables present:
404 log, analytics (GSC, inspections, objects), internal links and meta, redirections and their cache.

Rules that decide what can be built: no paid APIs or plans (free quotas only, no paid AI ever); RankWeb does only what
Rank Math Free does not; Search Insights stays in InsightWeb (split out 11 Nov 2026), so analytics features land there
and RankWeb's Overview shows and switches them.

## 1. Where every Pro feature stands

Status: **Have** = already on this site (where) · **Build** = RankWeb builds it (phase in section 2) · **InsightWeb** =
built in InsightWeb · **Free** = Rank Math Free already does it · **Not needed** = the site has nothing it applies to ·
**Can't** = needs a paid service.

### Analytics and tracking

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced Google Analytics 4 integration | GA4 numbers per post in WordPress | Have | InsightWeb `ga4.php` (Site Kit) |
| Keyword rank tracker (500 to 75,000 keywords) | Daily Google position per keyword | Have | InsightWeb keywords, GSC positions, OpenSEO import; no paid tracker |
| Position history for keywords and posts | Position over time | Have | InsightWeb observations table (`obs`), rollups |
| Track top 5 winning / losing keywords | Biggest movers | InsightWeb | Rising/drops exist; add a winners/losers card from `obs` (phase G) |
| Track top 5 winning / losing posts | Biggest page movers | InsightWeb | Same, by page (phase G) |
| Check ranking keywords for each post | Queries per post | Have | RankWeb content panel "Searches it shows for (28 days)" |
| Advanced content SEO overview | Per-post table of SEO score, traffic, keywords | Build | RankWeb Overview "Content" table from Rank Math score + InsightWeb data (phase G) |
| Single post performance badges | Badge in list screens (clicks, position) | Build | List-table column from InsightWeb data (phase G) |
| Track SEO performance of individual posts | Per-post traffic history | InsightWeb | Pages tab exists; add a per-item view (phase G) |
| Track PageSpeed for each post and page | PSI per URL | InsightWeb | `psi.php` covers key and campaign pages; add "check this page" on demand, free PSI key quota (phase G) |
| Track Google index status | URL Inspection per post | Build | GSC URL Inspection API (free, 2,000 a day per property), into Rank Math's `wp_rank_math_analytics_inspections` shape or our own table (phase G) |
| AI search traffic tracker | Visits from ChatGPT, Perplexity, Gemini … | Have | InsightWeb "AI referrals" (`referrer-icons.php`, GA4) |
| SEO performance email reports (7, 15 or 30 days) | Mailed digest | Build | One monthly digest to the admin from InsightWeb numbers, `wp_mail` from support@ (phase G) |
| White-labelled email reports, client management, support for client sites | Agency reselling | Not needed | One site |
| Google data fetch frequency (daily), days to keep Google data (no limit) | Storage | Have | InsightWeb daily runs; months kept for good |
| Import GSC and GA data from one country | Country filter | InsightWeb | Add a country setting to the GSC and GA4 calls (phase G) |
| Google Trends integration | Trend lines for keywords | Have | InsightWeb Rising (`rising.php`) |
| Google AdSense earning history | AdSense numbers | Not needed | Ads are off; no money items shown (owner, 12 Nov 2026) |
| MCP tools for agentic SEO | Lets AI agents read and change SEO data | Build | WordPress 7.1 Abilities API: RankWeb abilities (read SEO fields, link report, issues; write titles/descriptions/keywords with edit_post) exposed through the MCP adapter (phase H) |
| Free Rank Math AI trial, Content AI | Paid AI writing credits | Can't | Paid AI; the RankWeb content panel gives the site's own data instead |

### Internal links

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Automated keyword linking | Links keywords to pages | Build (done on branch) | Internal links phase 1, `claude/rankweb-internal-links` |
| AI internal linking (AI Link Genius) | Suggests links with AI | Build | Same index plus the content panel's "Internal links to add"; no paid AI |
| Link report | Links in and out per post | Build | Internal links phase 2 |
| Broken link detector | Broken links in content | Build | Internal links phase 2 (internal); external links checked in batches with HEAD requests (phase B) |
| Orphan page detector / detect orphan pages | Pages nothing links to | Build | Internal links phase 2 (rendered pages, not post_content only) |
| Mark cloaked links as external | `/go/` affiliate links counted as external | Not needed | No affiliate links; becomes one setting in phase 2 if a prefix is ever used |

### Schema

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| The most advanced schema generator, 840+ types, custom schema builder | Any schema.org type in a form | Build | Per-item "Custom schema (JSON-LD)" box: paste JSON-LD, checked with `json_decode`, `@context` forced, merged into Rank Math's graph (phase D) |
| Add custom schema using JSON-LD/HTML | Raw JSON-LD | Build | Same box (phase D) |
| Add unlimited multiple schemas | Several per item | Build | Same box takes a list (phase D) |
| Automate schema implementation | Schema templates by post type or condition | Have | RankWeb `schema.php` builds the graph per type (listings, News, Library, campaigns, places) |
| Validate schema with Google | Link to the Rich Results Test | Build | A "Test in Google" link per item and in the list (phase D) |
| Import schema from any website | Copy JSON-LD from a URL | Build | Read a URL's JSON-LD into the custom box for editing (phase D) |
| Speakable schema | Parts for voice assistants | Build | `speakable` cssSelector on News and Resources (title, summary) (phase D) |
| Dataset schema | Data downloads | Build | Library items that are data files (phase D; check which items qualify first) |
| Fact check schema (ClaimReview) | Fact checks | Not needed | The site publishes no fact checks; never added to items that are not |
| Podcast schema, podcast module | Podcasts | Not needed | No podcast |
| Carousel schema | ItemList for list pages | Have | `ItemList` on hubs and places (`schema.php`) |
| Mentions and About schema | Entities a page is about | Build | `about` / `mentions` from the item's categories, tags and linked campaign (phase D) |
| Automatic video detection, video data fill, video schema | VideoObject from embeds | Build | YouTube embeds in content → VideoObject with oEmbed title and thumbnail (free) (phase C) |
| Advanced HowTo schema | HowTo steps | Not needed | Google no longer shows HowTo results; not added |
| 18 / 6 extra schema types | Prebuilt types | Free (most) | Rank Math Free's types plus RankWeb's |
| Automatic Q&A schema for bbPress | Forums | Not needed | No bbPress |
| Elementor / Divi accordion to FAQ schema | Page builders | Not needed | Block theme; RankWeb's FAQ module does this for blocks |

### Sitemaps and indexing

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Google News sitemap | `news-sitemap.xml`, last 2 days, `<news:news>` | Build | 1,705 News items (`aw_news`) (phase C) |
| Google Video sitemap | `video-sitemap.xml` | Build | Items with a video embed (phase C, after counting them) |
| Noindex password-protected pages | Robots noindex on protected items | Build | One rule in `robots.php`; none today, so a safeguard (phase C) |
| Automated SEO sitemaps | Sitemaps | Free | Rank Math Free, plus RankWeb's Directory sitemap |

### Redirections and 404

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced redirections module (categories, schedule) | Groups and scheduled redirects | Build | Read and write Rank Math's `wp_rank_math_redirections`; categories as a column (phase F) |
| Import redirections via CSV | Bulk add | Build | CSV upload, preview, then add through `aw_redirect_add()` (phase F) |
| Export 404 log | CSV of `wp_rank_math_404_logs` | Build | Download on the Tools tab (phase F) |
| Sync redirections to .htaccess | Server-level redirects | Build, off | The server is LiteSpeed (reads .htaccess); a block between `# BEGIN RankWeb` markers, plain path rules only, written only when switched on, with a backup of the old file (phase F) |

### Content, images, bulk work

| Pro feature | What it does | Status | Where / dependency |
|---|---|---|---|
| Advanced bulk edit, advanced quick edit | SEO title, description, focus keyword, robots in list screens | Build | Quick Edit and Bulk Edit fields on every item type with Rank Math's box (phase E) |
| Complete import/export, import SEO data via CSV | Titles, descriptions, keywords by CSV | Build | Tools tab: export and import CSV by ID or URL, preview first (phase E) |
| Import/export focus keywords | Keywords by CSV | Build | Same CSV (phase E) |
| Find and replace image alt/title/caption | Bulk media text | Build | Tools tab, preview first, limits 100/100/160 as the media rules (phase E) |
| Automate image captions | Caption from the file or title | Have / Build | Core `media-autofill.php` fills alt and titles; captions added there (phase E) |
| Image SEO Pro (alt from title etc.) | Automatic alt/title | Have | Core `media-autofill.php`, `media.php` |
| Watermarked social media images | Play icon or logo on OG images | Not needed | Share pictures are made 1080x1080 with words (owner, 13 Nov 2026); a watermark would clash |
| Advanced post filtering | Filter lists by SEO score, schema, noindex | Build | Filters on item lists (phase E) |
| Local SEO Pro with multi-locations | Several LocalBusiness locations | Have | Directory listings carry Organization, PostalAddress, GeoCoordinates (`schema.php`) |
| Advanced local SEO blocks | Address, map, hours blocks | Have | Kits blocks and listing templates |
| WooCommerce SEO Pro, complete EDD SEO | Shops | Not needed | No shop |
| Complete Elementor / Divi integration, Elementor breadcrumbs widget | Page builders | Not needed | Block theme; RankWeb breadcrumbs |
| Custom setup wizard mode | Wizard | Build | RankWeb's own setup wizard (phase A) |

## 2. Build plan

Every phase works on its own, each module has its switch on RankWeb → Settings → Modules, and each phase stops for the
owner's check. Settings need manage_options, item fields edit_posts, nonces on every form, every input sanitised,
every output escaped, every string in the aw-seo-extras text domain.

- **A. Overview, switches, setup wizard, tools.** The Overview already lists every module (`overview.php`: what it does,
  where it is set up, its status); its switches are on Settings → Modules. The Overview gets one card per module with
  the on/off switch on the card, a link to its settings, its last job run and its numbers. Setup wizard on first activation and from the Overview (site
  type, linked post types, News sitemap, schema defaults, internal links, done screen), each step skippable, nothing
  saved until a step is confirmed. Tools tab with RankWeb's own: clear RankWeb caches, rebuild the link index, count the
  Dashboard now, re-create RankWeb tables, delete internal links data, rebuild the FAQ index, export and import RankWeb
  settings (JSON). Rank Math's own tools stay on Rank Math → Status & Tools.
- **B. Internal links phases 2 to 4.** Link audit (report, orphans, broken, redirects, nofollow, canonical elsewhere,
  click depth), anchor mix, silos (as planned in `docs/rankweb-internal-links.md`), plus the external broken-link check.
- **C. Sitemaps and video.** Google News sitemap, video detection and VideoObject, video sitemap, noindex on protected
  items.
- **D. Schema.** Custom JSON-LD box (several per item), import from a URL, Rich Results Test links, speakable, about and
  mentions, Dataset where it applies.
- **E. Bulk work.** Quick and Bulk Edit SEO fields, SEO data and focus keyword CSV export/import, media find and replace,
  automatic captions, list filters.
- **F. Redirections.** CSV import, 404 log export, categories, .htaccess sync (off by default).
- **G. InsightWeb additions.** Index status (URL Inspection API), winners and losers, per-item performance badge and
  view, content SEO overview, PageSpeed on demand, country filter, monthly email digest.
- **H. Agent tools.** RankWeb abilities through the WordPress Abilities API and MCP adapter, read first, then writes
  that need edit_post.

When all phases are done: version 2.1.0, changelog, readme.
