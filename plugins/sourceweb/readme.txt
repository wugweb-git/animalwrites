=== SourceWeb ===
Contributors: wugweb
Tags: news, sources, feeds, curation, moderation
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Curate incoming news and content: rate source hosts, score items by keyword relevance, and review everything before it reaches your site.

== Description ==

SourceWeb is an editorial gate for the content that flows into your WordPress site. If you pull in news, feeds or submissions, SourceWeb lets you decide which sources you trust, score each item against your own keyword lists, and hold the rest for review — so what gets published is what you actually want.

It is built to stay out of your way: no required companion plugins, no account, no external service, and no calls home. SourceWeb acts only on the public post types you choose and keeps all of its settings under its own option keys.

**What it does**

* **Rate your sources.** Keep a registry of publisher hosts, each marked allow, neutral or block. Blocked hosts never get in; allowed hosts skip the keyword filter.
* **Score by relevance.** Maintain include ("strong"), subject, context and exclude keyword lists. An exclude term always rejects an item; an include term always keeps it; otherwise an item is kept only when it carries both a subject term and a context term.
* **Match the way editors think.** The keyword matcher treats Latin terms as whole words (an optional plural is allowed), a trailing "*" as a word-prefix match, and terms in other scripts (Hindi, Tamil, and the rest) as substrings — so "dog" matches "dogs" but not "dogma".
* **Choose what it touches.** You pick which public post types SourceWeb curates. Nothing is hardcoded to a particular content type.

**Who it is for**

Newsrooms, aggregators, niche publishers and any editorial team that ingests a lot and needs a fast, transparent way to keep the signal and drop the noise — without handing control to a black box.

This is a standalone plugin. It shares no code or data with any other plugin, and it is GPL-licensed.

== Installation ==

1. Upload the `sourceweb` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin.
3. Open the SourceWeb menu and, under Settings, choose the post types to curate and fill in the keyword lists.

== Frequently Asked Questions ==

= Does it require any other plugin? =
No. SourceWeb is self-contained and works on a stock WordPress install.

= Which content does it act on? =
Only the public post types you tick in Settings. By default that is the built-in Posts type.

= Does it contact any external service? =
No. This release makes no outbound network requests; it evaluates text against your keyword lists entirely on your own site. If a future version adds optional feed ingestion or AI summaries, the services involved will be disclosed here and will be contacted only when you enable them and provide your own credentials.

= Does uninstalling delete my data? =
Uninstalling removes only SourceWeb's own settings (keyword lists, source registry, chosen post types) and its capability. It never deletes posts, users, media or any other plugin's data.

== External services ==

This release does not contact any external service. No data leaves your site.

== Screenshots ==

1. The SourceWeb Settings screen: choosing post types and editing the keyword lists.

== Changelog ==

= 0.1.0 =
* First release: source registry, keyword relevance engine and the per-post-type curation gate, with a standalone Settings screen.
