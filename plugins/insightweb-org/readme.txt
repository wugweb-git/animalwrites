=== InsightWeb ===
Contributors: wugweb
Tags: search console, analytics, google analytics, pagespeed, seo
Requires at least: 6.4
Requires PHP: 8.0
Requires Plugins: google-site-kit
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per-keyword insights from Search Console and GA4: roles, owning pages, priority and a next action, plus rising terms and quick wins.

== Description ==

InsightWeb turns the search data you already have into decisions. Instead of one more metrics dashboard, it builds a record for every keyword your site ranks for — the roles it plays, the pages that own it, a priority, and a suggested next action — so you know what to do, not just what happened.

It reads Search Console and Google Analytics 4 through Site Kit by Google (read-only; InsightWeb stores no Google credentials of its own and never changes your site or your SEO). On top of that it surfaces rising terms, quick wins (pages ranking just below the first page) and conflicts (two pages competing for one term), checks each page's PageSpeed, and confirms search and AI crawlers can reach your content. PageSpeed, Microsoft Clarity and Bing Webmaster add their figures when you set their keys.

A Site Health test, a WordPress dashboard widget and WP-CLI commands keep it out of your way. It reads and suggests; what you change is always your call.

This release (0.1.0) is an early skeleton: it activates next to Site Kit with an empty module set, and the insights modules land in later releases.

== Installation ==

1. Install and activate **Site Kit by Google**, and connect Search Console and Analytics in it.
2. Upload the `insightweb` folder to `/wp-content/plugins/`, or install from the plugin directory.
3. Activate InsightWeb and open the **InsightWeb** menu.

== Frequently Asked Questions ==

= Does it change my SEO settings or my content? =

No. InsightWeb only reads (through Site Kit) and suggests. It never edits settings, keyphrases or content.

= Does it need Site Kit? =

Yes, for Search Console and GA4 data: InsightWeb reads them through Site Kit by Google and keeps no Google credentials of its own. PageSpeed, Microsoft Clarity and Bing Webmaster work independently once you set their keys.

== External services ==

InsightWeb reads measurement data; it only reads, and each service is used only when its key or connection is set. Every outbound integration is declared in InsightWeb's external-services registry (a shared, suite-wide component), which generates this section and the in-admin "Data sources" list on the Overview. Today the registry declares the Google data flow below; this 0.1.0 skeleton makes no direct external call of its own, and PageSpeed, Clarity, Bing and DB-IP each register as their module lands. The services:

* **Google Search Console and Google Analytics 4** — accessed through the Site Kit by Google plugin, which holds the Google connection; InsightWeb stores no Google credentials. Google terms (https://policies.google.com/terms) and privacy policy (https://policies.google.com/privacy).
* **Google PageSpeed Insights** (`https://www.googleapis.com/`) — the URL of each checked page is sent to measure its score. Same Google terms and privacy.
* **Microsoft Clarity** (`https://www.clarity.ms/`) and **Bing Webmaster** (`https://ssl.bing.com/`) — only when their keys are set. Microsoft Services Agreement (https://www.microsoft.com/servicesagreement) and privacy statement (https://privacy.microsoft.com/privacystatement).
* **DB-IP IP-to-Country Lite** (`https://download.db-ip.com/`) — a monthly country-lookup file (CC BY 4.0); nothing is sent. See https://db-ip.com/.

== Changelog ==

= 0.1.0 =
* First skeleton of the public edition: standalone activation next to Site Kit, an empty module set, one `insightweb_` prefix, no self-hosted update header.
