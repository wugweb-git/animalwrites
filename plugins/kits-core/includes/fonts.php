<?php
/**
 * Inter on a Kits site (Kits by Wugweb as the active theme, no animal-writes child, which carries its own copy of
 * these two hooks):
 *
 * - Inter without the jump (Core Web Vitals, layout shift). Until Inter arrives, text shows in "Inter Fallback": the
 *   device's Arial resized to Inter's width and line height (measured from inter-latin.woff2: 107.48% wide, ascent
 *   90.14%, descent 22.44%), only for the characters Inter has, so a line does not rewrap when Inter swaps in. Wide
 *   screens (1200px and up) also preload the body font so it is there for the first paint; phones do not, so it never
 *   competes with their main picture. Theme Options → Font preload: "all" preloads it on every screen, "off" not at
 *   all; the file is the body font's first file in Styles (else the theme's assets/fonts/inter/inter-latin.woff2).
 * - Inter is served once (Theme Options → One copy of Inter). The theme's variable file (weights 100 to 900,
 *   font-display: swap) covers every upright weight; the Font Library copy of Inter also declares each weight as a
 *   static file without swap, and as the later rules they won (extra files per page, text hidden while they load).
 *   The upright library faces inside the range the theme's file is declared for, as resolved (Global Styles can
 *   narrow it), are left out, so no weight is ever left without a file. The library's italics stay.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Added at setup_theme (after every plugin, before the theme's functions.php), the place a child theme's hooks have, so
 * they keep the order the child theme gave them among other hooks of the same priority.
 */
add_action(
	'setup_theme',
	function () {
		if ( 'kits-by-wugweb' !== get_template() || 'animal-writes' === get_stylesheet() ) { // the child theme adds both itself
			return;
		}

		add_action(
			'wp_head',
			function () {
				$mode = function_exists( 'kits_option' ) ? kits_option( 'font_preload' ) : 'wide';
				$font = function_exists( 'kits_body_font_file' ) ? kits_body_font_file() : '';
				$font = '' !== $font ? $font : get_theme_file_uri( 'assets/fonts/inter/inter-latin.woff2' ); // Kits ships this file
				if ( 'off' !== $mode ) {
					echo '<link rel="preload" href="' . esc_url( $font ) . '" as="font" type="font/woff2" crossorigin' . ( 'all' === $mode ? '' : ' media="(min-width: 1200px)"' ) . '>' . "\n";
				}
				// The id is the one the animal-writes child theme prints, so the page is the same with or without it.
				echo '<style id="aw-inter-fallback">@font-face{font-family:"Inter Fallback";src:local("Arial"),local("ArialMT");size-adjust:107.48%;ascent-override:90.14%;descent-override:22.44%;line-gap-override:0%;unicode-range:U+0000,U+0020-007E,U+00A0-00AC,U+00AE-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+2002,U+2009,U+200B,U+2013-2014,U+2018-201A,U+201C-201E,U+2022,U+2026,U+2032-2033,U+2039-203A,U+2044,U+20AC,U+2122,U+2191,U+2193,U+2212,U+FEFF}</style>' . "\n";
			},
			2
		);

		add_action(
			'init',
			function () {
				if ( 'off' === ( function_exists( 'kits_option' ) ? kits_option( 'inter_dedupe' ) : 'on' ) ) { // Theme Options: One copy of Inter.
					return;
				}
				if ( ! class_exists( 'WP_Font_Face_Resolver' ) || false === has_action( 'wp_head', 'wp_print_font_faces' ) ) {
					return;
				}
				remove_action( 'wp_head', 'wp_print_font_faces', 50 );
				add_action(
					'wp_head',
					function () {
						$fonts  = WP_Font_Face_Resolver::get_fonts_from_theme_json();
						$is_lib = fn( $f ) => false !== strpos( implode( ' ', (array) ( $f['src'] ?? array() ) ), '/uploads/fonts/' );
						$inter  = fn( $f ) => 'inter' === strtolower( trim( (string) ( $f['font-family'] ?? '' ), '"\' ' ) ) && 'normal' === ( $f['font-style'] ?? 'normal' );
						// The weights the theme's own file is declared for, as resolved: only those library faces are left out.
						$lo = 1000;
						$hi = 0;
						foreach ( $fonts as $faces ) {
							foreach ( $faces as $f ) {
								if ( $inter( $f ) && ! $is_lib( $f ) ) {
									$w  = array_map( 'intval', preg_split( '/\s+/', trim( (string) ( $f['font-weight'] ?? '400' ) ) ) );
									$lo = min( $lo, min( $w ) );
									$hi = max( $hi, max( $w ) );
								}
							}
						}
						foreach ( $fonts as $family => $faces ) {
							$fonts[ $family ] = array_values(
								array_filter(
									$faces,
									function ( $f ) use ( $is_lib, $inter, $lo, $hi ) {
										$weight = (int) ( $f['font-weight'] ?? 0 );
										return ! ( $inter( $f ) && $is_lib( $f ) && $weight >= $lo && $weight <= $hi );
									}
								)
							);
						}
						wp_print_font_faces( array_filter( $fonts ) );
					},
					50
				);
			}
		);
	}
);
