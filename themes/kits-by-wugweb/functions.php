<?php
/**
 * Kits by Wugweb — theme setup.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme supports. Block themes get most supports automatically; these are the extras.
 */
function kits_setup() {
	add_theme_support( 'editor-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'post-thumbnails' );
	add_editor_style( 'assets/css/kits-v42.css' );
	add_editor_style( 'assets/css/kits.css' );
}
add_action( 'after_setup_theme', 'kits_setup' );

/**
 * Front-end styles that theme.json cannot express.
 */
function kits_enqueue_styles() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	// Kits v4.2 tokens (bare names: --neutral-*, --color-*, --s-*, --r-*, --opacity-*, --fluid-*). Loads before kit-tokens so a child theme can override.
	wp_enqueue_style( 'kits-v42', $uri . '/assets/css/kits-v42.css', array( 'global-styles' ), (string) filemtime( $dir . '/assets/css/kits-v42.css' ) );
	// Design tokens (--kit-*): neutral defaults; a child theme overrides them with its own token file.
	wp_enqueue_style( 'kit-tokens', $uri . '/assets/css/kit-tokens.css', array( 'global-styles' ), (string) filemtime( $dir . '/assets/css/kit-tokens.css' ) );
	wp_enqueue_style(
		'kits',
		get_template_directory_uri() . '/assets/css/kits.css',
		array(),
		wp_get_theme( 'kits-by-wugweb' )->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_styles' );

/**
 * Kits components and interactions (token-driven; a child theme restyles them by overriding --kit-* tokens).
 */
function kits_enqueue_components() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	wp_enqueue_style( 'kit-components', $uri . '/assets/css/kit.css', array( 'kit-tokens', 'kits' ), (string) filemtime( $dir . '/assets/css/kit.css' ) );
	wp_enqueue_script( 'kit-interactions', $uri . '/assets/js/kit.js', array(), (string) filemtime( $dir . '/assets/js/kit.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_components', 15 );

/**
 * Pattern category for all Kits patterns.
 */
function kits_pattern_categories() {
	register_block_pattern_category(
		'kits',
		array( 'label' => __( 'Kits', 'kits-by-wugweb' ) )
	);
}
add_action( 'init', 'kits_pattern_categories' );

/**
 * Block styles.
 */
function kits_block_styles() {
	register_block_style(
		'core/group',
		array(
			'name'  => 'kits-card',
			'label' => __( 'Card', 'kits-by-wugweb' ),
		)
	);
	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'kits-label',
			'label' => __( 'Label', 'kits-by-wugweb' ),
		)
	);
}
add_action( 'init', 'kits_block_styles' );
