=== InsightWeb ===
Contributors: wugweb
Tags: search console, analytics, keywords, pagespeed, seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Search Console, GA4, Clarity, PageSpeed, Bing, keywords and rising terms for Kits sites.

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
