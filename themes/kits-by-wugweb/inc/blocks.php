<?php
/**
 * Kits by Wugweb: pattern categories, block styles and the theme's block filters (menu badge, buttons, banner, pattern cache).
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pattern categories (Editor → Patterns): Kits: Components and Kits: Lists.
 */
function kits_pattern_categories() {
	register_block_pattern_category( 'kits-components', array( 'label' => __( 'Kits: Components', 'kits-by-wugweb' ) ) );
	register_block_pattern_category( 'kits-lists', array( 'label' => __( 'Kits: Lists', 'kits-by-wugweb' ) ) );
}
add_action( 'init', 'kits_pattern_categories' );

/**
 * Block styles (Appearance > Editor > Styles > Blocks lists them under each block). Their colours, borders, padding and
 * sizes are in theme.json (styles.blocks.*.variations), so they are edited there; kit.css adds height and hover.
 */
function kits_block_styles() {
	$styles = array(
		// Default (core "fill", shown as Primary) + these three. Size and content are extra CSS classes (docs/buttons.md).
		// Retired names still render: kits-primary (= Brand), kits-small (= small Primary), core outline (= Secondary).
		'core/button'          => array(
			'kits-secondary' => __( 'Secondary', 'kits-by-wugweb' ),
			'kits-ghost'     => __( 'Ghost', 'kits-by-wugweb' ),
			'kits-brand'     => __( 'Brand', 'kits-by-wugweb' ),
		),
		// Section and Head band: their padding, background and gap are settings (theme.json styles.blocks.core/group.variations;
		// Appearance > Editor > Styles > Blocks > Group), not CSS. docs/kits-control-map.md.
		'core/group'           => array(
			'kits-card'      => __( 'Card', 'kits-by-wugweb' ),
			'kits-surface'   => __( 'Surface', 'kits-by-wugweb' ),
			'kits-section'   => __( 'Section', 'kits-by-wugweb' ),
			'kits-head-band' => __( 'Head band', 'kits-by-wugweb' ),
		),
		'core/separator'       => array(
			'kits-hairline' => __( 'Hairline', 'kits-by-wugweb' ),
		),
		'core/paragraph'       => array(
			'kits-label' => __( 'Label', 'kits-by-wugweb' ),
			'kits-note'  => __( 'Note', 'kits-by-wugweb' ),
		),
		'core/list'            => array(
			'kits-sources' => __( 'Sources', 'kits-by-wugweb' ),
		),
		// A menu link with a "Beta" badge after its label (kits_nav_badge below; kit-primitives.css section 6).
		'core/navigation-link' => array(
			'kits-beta' => __( 'Beta', 'kits-by-wugweb' ),
		),
	);
	foreach ( $styles as $block => $list ) {
		foreach ( $list as $name => $label ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'kits_block_styles' );

/**
 * Groups saved before the Section and Head band styles existed carry the old classes (kit-section, kit-section-band).
 * They get the matching style before they render (WordPress prints a style's CSS for the blocks that have it), so they
 * follow the Styles settings without a content change. Re-saving such a block with the style picked makes this a no-op.
 */
add_filter(
	'render_block_data',
	function ( $block ) {
		if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		static $on = null;
		if ( null === $on ) {
			$on = 'off' !== ( function_exists( 'kits_option' ) ? kits_option( 'legacy_group_styles' ) : 'on' ); // Theme Options: Old section classes.
		}
		$class   = (string) ( $block['attrs']['className'] ?? '' );
		$classes = preg_split( '/\s+/', $class );
		if ( ! $on || false !== strpos( $class, 'is-style-' ) ) {
			return $block;
		}
		$style = in_array( 'kit-section-band', $classes, true ) ? 'is-style-kits-head-band' : ( in_array( 'kit-section', $classes, true ) ? 'is-style-kits-section' : '' );
		if ( '' !== $style ) {
			$block['attrs']['className'] = trim( $style . ' ' . $class );
		}
		return $block;
	},
	5 // before core's block style variation support (10), which prints the style's CSS.
);

/**
 * The same in the editor (assets/js/legacy-styles.js): older groups get the style when they open, so the editor
 * shows the Styles values on them and the next save keeps the style in the content.
 */
function kits_legacy_styles_editor() {
	if ( 'off' === ( function_exists( 'kits_option' ) ? kits_option( 'legacy_group_styles' ) : 'on' ) ) {
		return;
	}
	$f = get_template_directory() . '/assets/js/legacy-styles.js';
	wp_enqueue_script( 'kits-legacy-styles', get_template_directory_uri() . '/assets/js/legacy-styles.js', array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-data', 'wp-block-editor' ), (string) filemtime( $f ), true );
}
add_action( 'enqueue_block_editor_assets', 'kits_legacy_styles_editor' );

/**
 * Badge after a menu label: "Beta" (block style kits-beta). The badge sits inside the link, so the link's name reads
 * "{label}, Beta" (the comma is for screen readers only) and its target size is unchanged.
 *
 * @param string $text Badge text.
 * @return string
 */
function kits_nav_badge( $text ) {
	return '<span class="kit-badge kit-badge--beta"><span class="screen-reader-text">, </span>' . esc_html( $text ) . '</span>';
}

add_filter(
	'render_block_core/navigation-link',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'is-style-kits-beta' ) ) {
			return $html;
		}
		return preg_replace( '#(<span class="wp-block-navigation-item__label">.*?</span>)#s', '$1' . kits_nav_badge( __( 'Beta', 'kits-by-wugweb' ) ), $html, 1 );
	},
	10,
	2
);

/**
 * Core Button keeps one built-in style, its default, named Primary; core Outline is replaced by Kits Secondary.
 */
add_filter(
	'block_type_metadata',
	function ( $meta ) {
		if ( 'core/button' === ( $meta['name'] ?? '' ) ) {
			$meta['styles'] = array(
				array(
					'name'      => 'fill',
					'label'     => __( 'Primary', 'kits-by-wugweb' ),
					'isDefault' => true,
				),
			);
		}
		return $meta;
	}
);

/**
 * Link buttons: an absolute link shows the "leaves the site" arrow (kit-primitives.css section 10) unless it points at
 * this site's own host, which is only known here.
 */
function kits_button_own_host_css() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( '' === $host ) {
		return;
	}
	$host = preg_replace( '/^www\./', '', $host );
	$sel  = array();
	foreach ( array( $host, 'www.' . $host ) as $h ) {
		$sel[] = ':is(a.kit-btn, a.wp-block-button__link, a.wp-element-button)[href^="http"][href*="//' . esc_attr( $h ) . '/"]';
		$sel[] = ':is(a.kit-btn, a.wp-block-button__link, a.wp-element-button)[href^="http"][href$="//' . esc_attr( $h ) . '"]';
	}
	wp_register_style( 'kits-button-host', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_enqueue_style( 'kits-button-host' );
	wp_add_inline_style( 'kits-button-host', implode( ',', $sel ) . '{--kit-cta-arrow:var(--kit-ico-arrow-right);--_nudge:translateX(var(--kit-btn-nudge))}' );
}
add_action( 'enqueue_block_assets', 'kits_button_own_host_css' );

/**
 * Button block sidebar: Size, Content and Icon (assets/js/button-options.js), so size and content combine with any style.
 */
function kits_button_editor_options() {
	$f = get_template_directory() . '/assets/js/button-options.js';
	wp_enqueue_script( 'kits-button-options', get_template_directory_uri() . '/assets/js/button-options.js', array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ), (string) filemtime( $f ), true );
	wp_set_script_translations( 'kits-button-options', 'kits-by-wugweb', get_template_directory() . '/languages' ); // so the editor control's strings load from a locale's JSON.
}
add_action( 'enqueue_block_editor_assets', 'kits_button_editor_options' );

/**
 * Theme pattern files are cached by WordPress; refresh the cache when the pattern files of the active theme
 * (and its parent) change.
 */
add_action(
	'init',
	function () {
		$files = array_merge( (array) glob( get_template_directory() . '/patterns/*.php' ), (array) glob( get_stylesheet_directory() . '/patterns/*.php' ) );
		$sig   = md5( implode( ',', array_map( fn( $f ) => $f . ':' . filemtime( $f ), array_unique( $files ) ) ) );
		if ( kits_theme_store( 'kits_pattern_sig' ) !== $sig ) {
			wp_get_theme()->delete_pattern_cache();
			kits_theme_store_set( 'kits_pattern_sig', $sig );
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

// The six card states are pattern files (patterns/card-{state}.php, Kits: Lists), since 18 Nov 2026.
