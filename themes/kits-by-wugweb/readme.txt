=== Kits by Wugweb ===
Contributors: wugweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multipurpose block theme by Wugweb; every site's look is set in Styles.

== Description ==

Kits by Wugweb is a full-site-editing block theme. Design tokens (colour, type, spacing) come from the Kits design system and live in theme.json. Each site's look is a style variation (styles/), picked in Appearance > Editor > Styles; site features belong in plugins.

= One source for design values =

tokens/tokens.json is the source. tools/build-tokens.py (and, for a child theme, the site's tools/build-tokens.py) writes the presets listed under "$wp" into theme.json (colour palette, font sizes, spacing, border radius, shadows) and makes the CSS variables point at them (--kit-color-ink: var(--wp--preset--color--secondary)). Core blocks are styled in theme.json (styles.elements, styles.blocks and the block style variations Primary, Secondary, Ghost, Small, Card, Surface, Hairline); kit.css only adds what theme.json cannot express (heights, hover, motion, layout). So a change in Appearance > Editor > Styles reaches core blocks and Kits components alike. Font families are written by hand in theme.json, as they carry font files. Do not edit kit-tokens.css or the generated presets by hand; edit tokens.json and rebuild.

== Changelog ==

= 2.2.0 =
* Section and Head band are Group styles; their padding, background and gap are set in Styles > Blocks > Group.
* Kits Hero block: height, colours, padding, border and shadow are block supports, set in Styles > Blocks > Hero.
* Styles values for the hero height and section padding also reach parts that are not blocks yet.
* Animal Writes style variation (styles/animal-writes.json), with the Inter font.
* Needs WordPress 6.6 or later (Group styles in theme.json).

= 2.0.0 =
* Rebuilt: valid theme.json from Kits tokens, real header/footer, full template set, cleaned patterns, Inter Tight bundled.

== Copyright ==

Kits by Wugweb, (C) Wugweb.
Kits by Wugweb is distributed under the terms of the GNU GPL v2 or later.

This theme bundles the following third-party resources:

Inter Tight Font
Copyright 2022 The Inter Project Authors (https://github.com/rsms/inter-tight)
License: SIL Open Font License, 1.1, https://openfontlicense.org

Inter Font (assets/fonts/inter, used by the Animal Writes style variation)
Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter)
License: SIL Open Font License, 1.1, https://openfontlicense.org

Pattern images (assets/images) and the theme screenshot
License: CC0, https://creativecommons.org/publicdomain/zero/1.0/
museum.webp - https://www.rawpixel.com/image/3297419/free-photo-image-interior-hallway-architecture
tourist-and-building.webp - https://www.rawpixel.com/image/5928004/photo-image-public-domain-hand-person
building-exterior.webp - https://www.rawpixel.com/image/430335/free-photo-image-architecture-building-roof
windows.webp - https://www.rawpixel.com/image/3286615/free-photo-image-texture-architecture-building
abstract-geometric-art.webp - https://www.rawpixel.com/image/3283970/free-photo-image-pattern-artwork-abstract
angular-roof.webp - https://www.rawpixel.com/image/3289063/free-photo-image-architectural-detail-architecture
art-gallery.webp - https://www.rawpixel.com/image/3286187/free-photo-image-person-stairs-architecture
green-staircase.webp - https://www.rawpixel.com/image/430558/free-photo-image-abstract-architecture-art-public-domain
hotel-facade.webp - https://www.rawpixel.com/image/3286725/free-photo-image-black-and-white-background-photos
icon-message.webp - License: Unicode License V3, https://www.unicode.org/license.txt
screenshot.jpg - Kits by Wugweb wordmark
