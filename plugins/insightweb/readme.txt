=== InsightWeb ===
Contributors: wugweb
Tags: search console, analytics, keywords, pagespeed, seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search Console, GA4, Clarity, PageSpeed and Bing insights: keyword records, rising terms, quick wins and page checks.

== Description ==

InsightWeb measures how a Kits site does in search. It reads data and makes suggestions; it never changes SEO settings, keyphrases or content. The SEO work itself (titles, schema, FAQ, canonicals, sitemaps) is RankWeb's.

= Search Insights =

* One record per keyword, with its roles, owning pages, Search Console figures by day, priority and a suggested action.
* Search Console through Site Kit, one call per day; Discover, Google News and URL Inspection.
* GA4 through Site Kit (read only), PageSpeed, Microsoft Clarity and Bing Webmaster, each only when its key or connection is set.
* Rising terms, quick wins, conflicts, the Links view, crawler access checks, server figures and a monthly check list.
* Tabs: Overview, Keywords, Rising, Pages, Clarity, Check list, Links, Server and Settings. Other plugins can add a tab (filter `aw_si_tabs`).
* A Site Health test, a WordPress Dashboard box and the `wp aw-si` commands.

= What it needs =

* Kits Core, Animal Writes Core (the keyword table) and Rank Math SEO. Without them InsightWeb shows one notice and does nothing else.
* Site Kit by Google for Search Console and GA4.

= Keys =

Clarity, Bing Webmaster, PageSpeed and Google Alerts keys are set under Animal Writes → Keys, or as `wp-config.php` constants, which win. Their values are never shown.

== External services ==

InsightWeb reads measurement data from a few services. It only reads; it never sends your content or personal data to them, and each one is used only when you have set its key or connection.

* **Google Search Console and Google Analytics 4** — InsightWeb does not connect to Google itself and stores no Google credentials. It reads Search Console and GA4 data that the separate *Site Kit by Google* plugin has already fetched, using the Google account you connect inside Site Kit. To authenticate, install Site Kit by Google and connect Search Console and Analytics there; InsightWeb then reads that data (your site's own clicks, impressions, positions, sessions and index status) read-only. Governed by Google's terms (https://policies.google.com/terms) and privacy policy (https://policies.google.com/privacy).
* **Google PageSpeed Insights API** (`https://www.googleapis.com/pagespeedonline/v5/runPagespeed`) — when PageSpeed is enabled, InsightWeb sends the URL of each page it checks to measure its mobile score. An optional free API key may be set to raise the rate limit. Google terms and privacy as above.
* **Microsoft Clarity** (`https://www.clarity.ms/`) — only when a Clarity API token is set. InsightWeb reads your own Clarity project's summary metrics. Microsoft Services Agreement (https://www.microsoft.com/servicesagreement) and privacy statement (https://privacy.microsoft.com/privacystatement).
* **Bing Webmaster Tools** (`https://ssl.bing.com/`) — only when a Bing Webmaster API key is set. InsightWeb sends your site URL to read its crawl statistics and keyword figures. Microsoft terms and privacy as above.
* **DB-IP IP-to-Country Lite** (`https://download.db-ip.com/`) — once a month InsightWeb downloads a free country-lookup file (CC BY 4.0) used to show the country of visitors locally. No data is sent to DB-IP. See https://db-ip.com/ and https://db-ip.com/db/lite.php.
* **Crawler-access checks** — InsightWeb requests your own site's pages and `robots.txt` from outside to confirm search and AI crawlers can reach them. No third-party data is involved.

== Installation ==

1. Upload the `insightweb` folder to `/wp-content/plugins/`.
2. Activate InsightWeb on the Plugins screen.
3. Open Plugins → InsightWeb.

== Frequently Asked Questions ==

= Does it change my SEO settings? =

No. InsightWeb only reads (Search Console, GA4, Rank Math) and suggests. RankWeb and Rank Math do the SEO work.

= What happens when it is switched off or deleted? =

Its scheduled jobs stop. Its tables and options stay. Only Settings → "Delete Search Insights data" (typed DELETE) or `wp aw-si delete-data --yes` removes them.

== Changelog ==

= 1.0.0 =
* Search Insights moved out of RankWeb into its own plugin, unchanged: the same screen and address, names, tables, options, scheduled jobs and routes.

== Upgrade Notice ==

= 1.0.0 =
Search Insights is now its own plugin. Activate InsightWeb; nothing else changes.
