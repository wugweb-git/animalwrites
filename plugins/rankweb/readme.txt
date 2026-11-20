=== RankWeb ===
Contributors: wugweb
Tags: seo, rank math, faq, schema, breadcrumbs
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO extras that run alongside Rank Math (free): FAQs, canonicals, breadcrumbs, internal links, schema, video and bulk tools.

== Description ==

RankWeb works next to Rank Math (free) and does nothing unless Rank Math is active. It is part of the Wugweb "Kits" suite: it reads Rank Math's own fields and tables and adds what Rank Math keeps for its Premium plan. Where a feature belongs to another suite plugin, RankWeb hands it over — search analytics and Google data live in InsightWeb, not here.

* FAQ system: an FAQ box on every public post type and taxonomy, questions filled in automatically and kept once edited, an accordion and FAQPage structured data.
* Canonical addresses Rank Math cannot work out: hub pages, directory places and filtered or sorted lists.
* One breadcrumb trail per page, given to search engines as one BreadcrumbList.
* Rank Math's content analysis run on the page visitors see, with stored scores.
* Focus keywords with a one-page-per-keyphrase guard.
* The RankWeb content panel in the block editor: keyphrases, the searches a page showed for, internal links to add, and the item's link keywords (the only thing it saves, with its own button).
* Internal links: an auto-linker that links items to each other from their link keywords (off until a post type is chosen in Settings → Internal links), a link audit (orphans, click depth, broken links, redirects, nofollow, canonical elsewhere), an anchor-type mix with warnings, and silos (hub and supporting items, virtual or circle).
* Video SEO: YouTube and Vimeo detection from content, a VideoObject in the schema, and a video sitemap.
* Custom schema: a per-item JSON-LD box, Speakable, about and mentions, import from a URL, and a Rich Results Test link.
* Bulk work: SEO columns and filters on the list screens, Quick Edit and Bulk Edit, and a CSV export and import of the SEO fields. Image find-and-replace across alt, title and caption, and fill missing captions.
* Redirect tools: CSV export and import of Rank Math's redirect list, a 404-log export, and an Apache .htaccess block.
* Agent tools: RankWeb's work as WordPress abilities (the Abilities API) for AI agents through the MCP adapter; reads by default, writes opt-in.
* A password-protected item is kept out of search.
* llms.txt, Open Graph details, share images and a watchdog for the sitemap.
* Settings for every rule, grouped by module, with their defaults. Every switchable module has an on/off switch on the dashboard.

== Installation ==

1. Install and activate Rank Math SEO (free) first. RankWeb requires it and will not activate without it.
2. Upload the plugin to wp-content/plugins, or install the zip from Plugins → Add New.
3. Activate RankWeb. Its settings are under the RankWeb menu; each module has an on/off switch on the RankWeb dashboard.

== Frequently Asked Questions ==

= Does RankWeb replace Rank Math? =

No. It runs alongside Rank Math (free) and only adds what Rank Math does not do or keeps for its Premium plan. Rank Math stays installed and active.

= Does it work without Rank Math? =

No. RankWeb depends on Rank Math and does nothing while Rank Math is inactive.

= Does it send my data anywhere? =

Only as described under "External services" below, and only for the features that use them. There is no tracking or phone-home.

== External services ==

RankWeb contacts these third-party services, only when the matching feature is used:

* YouTube and Vimeo oEmbed — when the Video module scans an item that contains a YouTube or Vimeo video, the video's public URL is sent to that provider's oEmbed endpoint (https://www.youtube.com/oembed, https://vimeo.com/api/oembed.json) to read the title, thumbnail and (Vimeo) duration and date. YouTube: https://policies.google.com/terms , https://policies.google.com/privacy . Vimeo: https://vimeo.com/terms , https://vimeo.com/privacy .
* Google Rich Results Test — the Custom schema box shows a link to https://search.google.com/test/rich-results for the current item. It is a link the editor clicks; RankWeb makes no automatic request. https://policies.google.com/terms , https://policies.google.com/privacy .
* Import schema from a URL — when an editor uses "Import from a URL" in the Custom schema box, RankWeb fetches that editor-supplied URL to read its JSON-LD. No request is made unless the editor enters a URL and clicks Import.

RankWeb's link audit and content analysis fetch URLs on your own site only.

== Uninstall ==

Deleting the plugin deletes no data: settings, FAQ records, focus keywords and the scores it has stored stay, so reinstalling finds everything as it was.

== Changelog ==

= 2.1.0 =
* Internal links: link audit (orphans, click depth, broken links, redirects, nofollow, canonical elsewhere, too many links), anchor-type mix with per-target warnings and keyword rotation, and silos (virtual and circle modes).
* Video SEO: YouTube and Vimeo detection, VideoObject schema from oEmbed, and a video sitemap.
* Custom schema: a per-item JSON-LD box (checked on save), Speakable, about and mentions, import JSON-LD from a URL, and a Rich Results Test link.
* Bulk work: a SEO column, list filters, Quick Edit and Bulk Edit, and a SEO CSV export and import; image find-and-replace and fill missing captions.
* Redirect tools: redirect CSV export and import, a 404-log export, and an Apache .htaccess block.
* Agent tools: RankWeb abilities on the WordPress Abilities API for the MCP adapter; reads by default, writes opt-in.
* A password-protected item is set to noindex.
* Module on/off switches on the dashboard.

= 2.0.0 =
* FAQ, canonicals, breadcrumbs, content analysis, focus keywords, the content panel, and the internal-links auto-linker.
