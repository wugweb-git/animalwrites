<?php
/**
 * Animal Writes child theme: load design tokens, component styles and interactions.
 *
 * @package animal-writes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$dir = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();
		$v   = fn( $f ) => (string) filemtime( "$dir/$f" );
		wp_enqueue_style( 'aw-tokens', "$uri/assets/css/tokens.css", array( 'kit-tokens' ), $v( 'assets/css/tokens.css' ) ); // brand values over the Kits defaults
		wp_enqueue_style( 'aw-components', "$uri/assets/css/aw.css", array( 'aw-tokens', 'kit-components' ), $v( 'assets/css/aw.css' ) );
		wp_enqueue_script( 'aw-interactions', "$uri/assets/js/aw.js", array( 'kit-interactions' ), $v( 'assets/js/aw.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	},
	20
);

// The editor shows content with the site's own tokens and components (after the Kits styles).
add_action(
	'after_setup_theme',
	function () {
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/aw.css' ) );
	},
	11
);

// Reusable sections (patterns/*.php) appear under their own category in the block inserter.
add_action(
	'init',
	function () {
		register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes', 'animal-writes' ) ) );
	}
);

// Home page: the hero photograph (patterns/hero.php) is the largest thing on screen; the browser starts on it at once.
add_action(
	'wp_head',
	function () {
		if ( ! is_front_page() ) {
			return;
		}
		$base = '/wp-content/uploads/2026/10/aw1-im5-';
		$set  = implode( ', ', array_map( fn( $s ) => esc_url( home_url( $base . $s[0] . '.jpeg' ) ) . ' ' . $s[1] . 'w', array( array( '768x433', 768 ), array( '1024x578', 1024 ), array( '1536x866', 1536 ), array( '2048x1155', 2048 ) ) ) );
		echo '<link rel="preload" as="image" href="' . esc_url( home_url( $base . '1536x866.jpeg' ) ) . '" imagesrcset="' . esc_attr( $set ) . '" imagesizes="100vw" fetchpriority="high">' . "\n";
	},
	1
);

/*
 * Inter without the jump (Core Web Vitals, layout shift). Until Inter arrives, text shows in "Inter Fallback": the
 * device's Arial resized to Inter's width and line height (measured from inter-latin.woff2: 107.48% wide, ascent 90.14%,
 * descent 22.44%), only for the characters Inter has. A line no longer rewraps when Inter swaps in (the Opportunities
 * intro moved the list 29px). Wide screens (1200px and up) also preload Inter so it is there for the first paint;
 * phones do not, so it never competes with their main picture.
 */
add_action(
	'wp_head',
	function () {
		$font = get_theme_file_uri( 'assets/fonts/inter/inter-latin.woff2' );
		echo '<link rel="preload" href="' . esc_url( $font ) . '" as="font" type="font/woff2" crossorigin media="(min-width: 1200px)">' . "\n";
		echo '<style id="aw-inter-fallback">@font-face{font-family:"Inter Fallback";src:local("Arial"),local("ArialMT");size-adjust:107.48%;ascent-override:90.14%;descent-override:22.44%;line-gap-override:0%;unicode-range:U+0000,U+0020-007E,U+00A0-00AC,U+00AE-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+2002,U+2009,U+200B,U+2013-2014,U+2018-201A,U+201C-201E,U+2022,U+2026,U+2032-2033,U+2039-203A,U+2044,U+20AC,U+2122,U+2191,U+2193,U+2212,U+FEFF}</style>' . "\n";
	},
	2
);

/*
 * Inter is served once. The theme's own variable file (assets/fonts/inter/inter-latin.woff2, weights 400 to 700,
 * font-display: swap) covers every weight the site uses. The Font Library copy of Inter (wp-content/uploads/fonts) also
 * declares 400 to 700 as separate static files without swap, and as the later rules they won: three extra 118 KB files
 * per page, with text hidden while they load. Those overlapping faces are left out; the library's other weights and its
 * italics stay. Phones do not preload the file: on a slow phone a preload would compete with the page's main picture (wide screens do, above).
 */
add_action(
	'init',
	function () {
		if ( ! class_exists( 'WP_Font_Face_Resolver' ) || false === has_action( 'wp_head', 'wp_print_font_faces' ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_print_font_faces', 50 );
		add_action(
			'wp_head',
			function () {
				$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
				foreach ( $fonts as $family => $faces ) {
					$fonts[ $family ] = array_values(
						array_filter(
							$faces,
							function ( $f ) {
								$src    = implode( ' ', (array) ( $f['src'] ?? array() ) );
								$weight = (int) ( $f['font-weight'] ?? 0 );
								return ! ( 'inter' === strtolower( trim( (string) ( $f['font-family'] ?? '' ), '"\' ' ) ) && false !== strpos( $src, '/uploads/fonts/' ) && 'normal' === ( $f['font-style'] ?? 'normal' ) && $weight >= 400 && $weight <= 700 );
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
