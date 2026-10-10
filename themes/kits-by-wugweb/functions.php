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
	add_editor_style( 'assets/css/kit-tokens.css' );
	add_editor_style( 'assets/css/kits.css' );
	add_editor_style( 'assets/css/kit.css' ); // components and primitives: the editor shows content as the site does
	add_editor_style( 'assets/css/kit-primitives.css' );
	if ( kits_uses_inter_tight() ) {
		add_editor_style( kits_font_url() );
	}
}
add_action( 'after_setup_theme', 'kits_setup' );

/**
 * Inter Tight from Google Fonts (400–800). A child theme that sets its own base font does not load it.
 */
function kits_font_url() {
	return 'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&display=swap';
}

/** Whether the active base font family is Inter Tight. */
function kits_uses_inter_tight() {
	foreach ( (array) wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) ) as $family ) {
		if ( 'base' === ( $family['slug'] ?? '' ) ) {
			return false !== stripos( (string) ( $family['fontFamily'] ?? '' ), 'Inter Tight' );
		}
	}
	return true;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( kits_uses_inter_tight() ) {
			wp_enqueue_style( 'kits-font', kits_font_url(), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	},
	5
);
add_filter(
	'wp_resource_hints',
	function ( $urls, $type ) {
		if ( 'preconnect' === $type && kits_uses_inter_tight() ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
		}
		return $urls;
	},
	10,
	2
);

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
		(string) filemtime( get_template_directory() . '/assets/css/kits.css' ) // a new version on every change, so browsers never keep an old copy
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
	wp_enqueue_style( 'kit-primitives', $uri . '/assets/css/kit-primitives.css', array( 'kit-components' ), (string) filemtime( $dir . '/assets/css/kit-primitives.css' ) );
	wp_enqueue_script( 'gsap', $uri . '/assets/vendor/gsap.min.js', array(), '3.12.5', array( 'in_footer' => true, 'strategy' => 'defer' ) ); // load / scroll-in motion
	wp_enqueue_script( 'kit-interactions', $uri . '/assets/js/kit.js', array( 'gsap' ), (string) filemtime( $dir . '/assets/js/kit.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'kits_enqueue_components', 15 );

/**
 * Pattern categories (Editor → Patterns): Kits: Components and Kits: Lists.
 */
function kits_pattern_categories() {
	register_block_pattern_category( 'kits-components', array( 'label' => __( 'Kits: Components', 'kits-by-wugweb' ) ) );
	register_block_pattern_category( 'kits-lists', array( 'label' => __( 'Kits: Lists', 'kits-by-wugweb' ) ) );
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

/**
 * Theme pattern files are cached by WordPress; refresh the cache when the pattern files of the active theme
 * (and its parent) change.
 */
add_action(
	'init',
	function () {
		$files = array_merge( (array) glob( get_template_directory() . '/patterns/*.php' ), (array) glob( get_stylesheet_directory() . '/patterns/*.php' ) );
		$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . filemtime( $f ), array_unique( $files ) ) ) );
		if ( get_option( 'kits_pattern_sig' ) !== $sig ) {
			wp_get_theme()->delete_pattern_cache();
			update_option( 'kits_pattern_sig', $sig, false );
		}
	},
	1
);

/**
 * Banner pattern (kit-banner): shows nothing until it has an image; the first banner on a page loads first.
 */
add_filter(
	'render_block_core/cover',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'kit-banner' ) ) {
			return $html;
		}
		if ( empty( $block['attrs']['url'] ) || false === stripos( $html, '<img' ) ) {
			return '';
		}
		static $first = true;
		if ( $first ) {
			$first = false;
			$html  = preg_replace( '/<img(?![^>]*fetchpriority)/', '<img fetchpriority="high"', $html, 1 );
			$html  = str_replace( 'loading="lazy"', 'loading="eager"', $html );
		}
		return $html;
	},
	10,
	2
);

/**
 * One stylesheet instead of five: the Kits files (kits-v42, kit-tokens, kits, kit, kit-primitives) are joined, in the
 * same order, into uploads/kits-cache/kits-{hash}.css; the hash changes with any file, so a changed file makes a new
 * bundle. The five handles stay registered (child themes depend on them) but print nothing. If the bundle cannot be
 * written, the five files load as before. Turn off with add_filter( 'kits_bundle_css', '__return_false' ).
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$styles  = wp_styles();
		$handles = array( 'kits-v42', 'kit-tokens', 'kits', 'kit-components', 'kit-primitives' );
		if ( is_admin() || ! apply_filters( 'kits_bundle_css', true ) ) {
			return;
		}
		$dir   = get_template_directory();
		$uri   = get_template_directory_uri();
		$files = array();
		foreach ( $handles as $h ) {
			$src = isset( $styles->registered[ $h ] ) ? (string) $styles->registered[ $h ]->src : '';
			if ( ! wp_style_is( $h, 'enqueued' ) && ! wp_style_is( $h, 'registered' ) || 0 !== strpos( $src, $uri ) || ! empty( $styles->registered[ $h ]->extra['after'] ) || ! empty( $styles->registered[ $h ]->extra['before'] ) ) {
				return; // not ours, or carries inline CSS: leave the files as they are
			}
			$files[ $h ] = $dir . substr( strtok( $src, '?' ), strlen( $uri ) );
		}
		$sig  = substr( md5( implode( ',', array_map( fn( $f ) => $f . ':' . ( file_exists( $f ) ? filemtime( $f ) . ':' . filesize( $f ) : 0 ), $files ) ) ), 0, 12 );
		$up   = wp_upload_dir();
		$path = trailingslashit( $up['basedir'] ) . 'kits-cache/kits-' . $sig . '.css';
		if ( ! file_exists( $path ) ) {
			$css = '';
			foreach ( $files as $h => $f ) {
				$part = file_exists( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( '' === $part ) {
					return;
				}
				$css .= "/* {$h} */\n" . $part . "\n";
			}
			wp_mkdir_p( dirname( $path ) );
			$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
			if ( false === file_put_contents( $tmp, $css ) || ! rename( $tmp, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return;
			}
			foreach ( (array) glob( dirname( $path ) . '/kits-*.css' ) as $old ) {
				if ( $old !== $path && filemtime( $old ) < time() - DAY_IN_SECONDS ) {
					@unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
		}
		$first = $styles->registered[ $handles[0] ];
		wp_register_style( 'kits-bundle', trailingslashit( $up['baseurl'] ) . 'kits-cache/kits-' . $sig . '.css', $first->deps, null );
		foreach ( $handles as $h ) {
			$styles->registered[ $h ]->src  = false; // an alias: prints nothing, keeps its place for dependants
			$styles->registered[ $h ]->deps = array( 'kits-bundle' );
		}
	},
	1000
);
