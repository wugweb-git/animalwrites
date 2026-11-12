=== Kits Core ===
Contributors: wugweb
Tags: forms, spam protection, share, analytics, admin
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The features every Kits site shares: the form engine with its spam guard, the share bar, Google Tag Manager with consent mode, a visit counter, source attribution and the Kits admin screens.

== Description ==

Kits Core is the shared layer of Kits by Wugweb sites. The site configures it through `kits_*` filters.

* Form engine: one field dictionary, server-side checks, per-field errors that work without JavaScript, and ALTCHA, a proof-of-work spam guard with no third-party service and no cookies.
* Share bar: plain links and copy-link, with no third-party scripts or tracking.
* Google Tag Manager and Microsoft Clarity with consent mode v2, denied by default where the law asks for it.
* A visit counter, preview-only items, news attribution and the publisher records used by SourceWeb.
* The Kits admin shell: the screens of every Kits plugin built on WordPress components and DataViews, with lists, settings, empty and error states that look and work the same everywhere.
* The Kits menu: Home, Theme Options, Settings, Tools and Help, with the design system and the registry of every component.

== Uninstall ==

Deleting the plugin deletes no data: theme options, form settings, saved keys, publisher records, keyword tables and visit counts stay, so reinstalling finds everything as it was.

== Changelog ==

= 1.0.0 =
* Current release.
