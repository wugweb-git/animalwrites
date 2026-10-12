=== Kits by Wugweb ===
Contributors: wugweb
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Block theme built on the Kits by Wugweb design system.

== Description ==

Kits by Wugweb is a full-site-editing block theme. Design tokens (colour, type, spacing) come from the Kits design system and live in theme.json. Brands ship as child themes or style variations; site features belong in plugins.

= One source for design values =

tokens/tokens.json is the source. tools/build-tokens.py (and, for a child theme, the site's tools/build-tokens.py) writes the presets listed under "$wp" into theme.json (colour palette, font sizes, spacing, border radius, shadows) and makes the CSS variables point at them (--kit-color-ink: var(--wp--preset--color--secondary)). Core blocks are styled in theme.json (styles.elements, styles.blocks and the block style variations Primary, Secondary, Ghost, Small, Card, Surface, Hairline); kit.css only adds what theme.json cannot express (heights, hover, motion, layout). So a change in Appearance > Editor > Styles reaches core blocks and Kits components alike. Font families are written by hand in theme.json, as they carry font files. Do not edit kit-tokens.css or the generated presets by hand; edit tokens.json and rebuild.

== Changelog ==

= 2.0.0 =
* Rebuilt: valid theme.json from Kits tokens, real header/footer, full template set, cleaned patterns, Inter Tight bundled.

== Copyright ==

Kits by Wugweb, (C) Wugweb.
Kits by Wugweb is distributed under the terms of the GNU GPL v2 or later.

This theme bundles the following third-party resources:

Inter Tight Font
Copyright 2022 The Inter Project Authors (https://github.com/rsms/inter-tight)
License: SIL Open Font License, 1.1, https://openfontlicense.org
