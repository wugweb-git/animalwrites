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
		if ( ! is_front_page() || ( function_exists( 'aw_home_hero_is_campaign' ) && aw_home_hero_is_campaign() ) ) { // the campaign hero has its own artwork
			return;
		}
		$base = '/wp-content/uploads/2026/10/aw1-im5-';
		$set  = implode( ', ', array_map( fn( $s ) => esc_url( home_url( $base . $s[0] . '.jpeg' ) ) . ' ' . $s[1] . 'w', array( array( '768x433', 768 ), array( '1024x578', 1024 ), array( '1536x866', 1536 ), array( '2048x1155', 2048 ) ) ) );
		// With Optimization Detective on, it already preloads the hero for the widths it has measured (up to 480px and over
		// 600px), so this one covers only the widths in between instead of a third copy (owner, 20 Oct 2026).
		$media = function_exists( 'od_get_breakpoint_max_widths' ) ? ' media="screen and (480px &lt; width) and (width &lt;= 600px)"' : '';
		echo '<link rel="preload" as="image" href="' . esc_url( home_url( $base . '1536x866.jpeg' ) ) . '" imagesrcset="' . esc_attr( $set ) . '" imagesizes="100vw" fetchpriority="high"' . $media . '>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	1
);

/*
 * Inter comes from Kits by Wugweb (its theme.json declares the font once). The preload, the "Inter Fallback" face and
 * the one-copy-of-Inter filter come from Kits Core (includes/fonts.php).
 */

/**
 * Home page: WordPress's navigation and cover block stylesheets no longer block the first paint (owner, 24 Oct 2026).
 * The rules the home page uses (assets/vendor/wp-core/home-*.css, made by tools/campaign-home-css/extract.mjs) are printed
 * inline in each link's place, same position in the cascade. Only while WordPress is the version they were made from.
 */
const AW_HOME_CORE_CSS_WP = '7.1.3';
add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		$files = array( 'wp-block-navigation' => 'home-navigation.css', 'wp-block-cover' => 'home-cover.css' );
		if ( ! is_front_page() || is_admin() || ! isset( $files[ $handle ] ) || get_bloginfo( 'version' ) !== AW_HOME_CORE_CSS_WP ) {
			return $tag;
		}
		$file = get_theme_file_path( 'assets/vendor/wp-core/' . $files[ $handle ] );
		return is_readable( $file ) ? '<style id="' . esc_attr( $handle ) . '-home-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
	},
	10,
	2
);

/**
 * tokens.css is five custom properties (283 bytes): printed inline in its link's place on every page, so it is no
 * request of its own (owner, 24 Oct 2026). Same position in the cascade.
 */
add_filter(
	'style_loader_tag',
	function ( $tag, $handle ) {
		$file = get_theme_file_path( 'assets/css/tokens.css' );
		return 'aw-tokens' === $handle && ! is_admin() && is_readable( $file ) ? '<style id="aw-tokens-css">' . file_get_contents( $file ) . "</style>\n" : $tag; // phpcs:ignore WordPress.WP.AlternativeFunctions
	},
	10,
	2
);
