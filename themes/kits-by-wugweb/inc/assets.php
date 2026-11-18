<?php
/**
 * Kits by Wugweb: editor styles, the served CSS file names and the front-end styles and script.
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor styles. A block theme gets editor-styles, responsive-embeds and post-thumbnails from core on its own.
 */
function kits_setup() {
	add_editor_style( 'assets/css/kits-v42.css' );
	add_editor_style( 'assets/css/kit-tokens.css' );
	add_editor_style( 'assets/css/kits.css' );
	add_editor_style( 'assets/css/kit.css' ); // components and primitives: the editor shows content as the site does.
	add_editor_style( 'assets/css/kit-primitives.css' );
	if ( is_readable( get_template_directory() . '/assets/site/site.css' ) ) {
		add_editor_style( 'assets/site/site.css' ); // the site's own CSS (kits_site_component_style).
	}
}
add_action( 'after_setup_theme', 'kits_setup' );

/*
 * Fonts: Inter, declared once in theme.json (settings.typography.fontFamilies, with its fontFace in
 * assets/fonts/inter/inter-latin.woff2). WordPress prints the @font-face on the site and in the editor, and the Font
 * Library and Styles manage it; nothing loads from another server.
 */

if ( ! function_exists( 'kits_css_file' ) ) {
	/**
	 * The file a Kits stylesheet is served from: its .min.css (tools/ci/css_min.py writes it from the source) when it is
	 * there, else the source. The block editor keeps loading the sources.
	 *
	 * @param string $name File name without .css, in assets/css (kit-tokens, kit, kit-primitives).
	 * @return string Path relative to the theme folder.
	 */
	function kits_css_file( $name ) {
		$min = 'assets/css/' . $name . '.min.css';
		return is_readable( get_template_directory() . '/' . $min ) ? $min : 'assets/css/' . $name . '.css';
	}
}

/**
 * Front-end styles that theme.json cannot express.
 */
function kits_enqueue_styles() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	// Kits v4.2 tokens (bare names: --neutral-*, --color-*, --s-*, --r-*, --opacity-*, --fluid-*). Loads before kit-tokens so a child theme can override.
	wp_enqueue_style( 'kits-v42', $uri . '/assets/css/kits-v42.css', array( 'global-styles' ), (string) filemtime( $dir . '/assets/css/kits-v42.css' ) );
	// Design tokens (--kit-*): neutral defaults; a child theme overrides them with its own token file.
	$css = kits_css_file( 'kit-tokens' );
	wp_enqueue_style( 'kits-tokens', $uri . '/' . $css, array( 'global-styles' ), (string) filemtime( $dir . '/' . $css ) );
	wp_enqueue_style(
		'kits',
		get_template_directory_uri() . '/assets/css/kits.css',
		array(),
		(string) filemtime( get_template_directory() . '/assets/css/kits.css' ) // a new version on every change, so browsers never keep an old copy.
	);
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_styles' );

/**
 * Kits components and interactions (token-driven; a child theme restyles them by overriding --kit-* tokens).
 */
function kits_enqueue_components() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$css = kits_css_file( 'kit' );
	wp_enqueue_style( 'kits-components', $uri . '/' . $css, array( 'kits-tokens', 'kits' ), (string) filemtime( $dir . '/' . $css ) );
	$css = kits_css_file( 'kit-primitives' );
	wp_enqueue_style( 'kits-primitives', $uri . '/' . $css, array( 'kits-components' ), (string) filemtime( $dir . '/' . $css ) );
	wp_enqueue_script(
		'kits-interactions',
		$uri . '/assets/js/kit.js',
		array(),
		(string) filemtime( $dir . '/assets/js/kit.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	); // scroll-in motion: the browser's own (element.animate), no GSAP (owner, 2 Nov 2026).
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_components', 15 );
