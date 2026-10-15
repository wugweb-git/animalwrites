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
 * Inter is served once. The theme's own variable file (assets/fonts/inter/inter-latin.woff2, weights 400 to 700,
 * font-display: swap) covers every weight the site uses. The Font Library copy of Inter (wp-content/uploads/fonts) also
 * declares 400 to 700 as separate static files without swap, and as the later rules they won: three extra 118 KB files
 * per page, with text hidden while they load. Those overlapping faces are left out; the library's other weights and its
 * italics stay. The theme's file is preloaded.
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

add_action(
	'wp_head',
	function () {
		echo '<link rel="preload" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/fonts/inter/inter-latin.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	},
	2
);
