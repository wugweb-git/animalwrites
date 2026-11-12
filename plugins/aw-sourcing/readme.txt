=== SourceWeb ===
Contributors: wugweb
Tags: news, sources, feeds, curation, ai
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

What the news workers fetch and how it arrives: sources, feeds, keywords, rules, the review queues and the worker endpoints.

== Description ==

SourceWeb decides what comes into the site and lets the team review it.

* Sources: every publisher host with its status (allowed, neutral or blocked), short name, feeds, relay and its own site icon, fetched once in the background and kept in the media library.
* Keywords: the include, animal, context and exclude lists that decide relevance.
* Rules: per-section rules for what is kept and how it is summarised.
* To review and Enrichment review: the queues the team approves or rejects.
* Reports and jobs: workers, rejected items, relay requests and the change log.
* AI models: which free models do which job, with daily caps.
* Worker endpoints for ingest, relay, sourcing policy, relevance, same-story grouping, summaries and enrichment.

Animal Writes Core loads it from this folder; it can also be activated on its own.

== Uninstall ==

Deleting the plugin deletes no data: sources, keywords, rules, review queues, logs, AI model settings and the source icons in the media library stay, so reinstalling finds everything as it was.

== Changelog ==

= 0.1.0 =
* Current release.
