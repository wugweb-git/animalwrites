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
		register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes sections', 'animal-writes' ) ) );
	}
);
