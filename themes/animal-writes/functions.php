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
		wp_enqueue_style( 'aw-tokens', "$uri/assets/css/tokens.css", array( 'global-styles' ), $v( 'assets/css/tokens.css' ) );
		wp_enqueue_style( 'aw-components', "$uri/assets/css/aw.css", array( 'aw-tokens' ), $v( 'assets/css/aw.css' ) );
		wp_enqueue_script( 'aw-interactions', "$uri/assets/js/aw.js", array(), $v( 'assets/js/aw.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	},
	20
);

// Reusable sections (patterns/*.php) appear under their own category in the block inserter.
add_action(
	'init',
	function () {
		register_block_pattern_category( 'animal-writes', array( 'label' => __( 'Animal Writes sections', 'animal-writes' ) ) );
	}
);
