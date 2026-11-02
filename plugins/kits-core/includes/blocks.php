<?php
/**
 * Kits blocks, and the Styles settings that every matching part follows.
 *
 * - kits/hero (blocks/hero): the page hero. Its look is block supports, so Styles > Blocks > Hero holds its height
 *   (Dimensions > Minimum height), colours, padding, border and shadow.
 * - Two Styles values are also the theme's tokens, so heroes and sections that are not (yet) these blocks follow them too:
 *     Styles > Blocks > Hero > Minimum height             -> --kit-hero-h        (every hero: Home, campaigns, covers)
 *     Styles > Blocks > Group > Section > Padding (top)    -> --kit-space-section (every section, band and campaign section)
 *   While a value is the theme default (the token itself), nothing is printed and the token keeps its 48 / 70 / 96 steps
 *   at 768 and 1200 (kit-tokens.css). A value set in Styles is one value at every width.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_block_type_from_metadata( KITS_CORE_DIR . 'blocks/hero' );
	}
);

/**
 * A Styles value as CSS: preset references (var:preset|spacing|section) become CSS variables. Only plain CSS length
 * values pass (numbers, units, var(), min(), max(), clamp(), calc()); anything else returns ''.
 *
 * @param mixed $value Raw global styles value.
 * @return string
 */
function kits_style_value_css( $value ) {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return '';
	}
	$value = trim( $value );
	if ( 0 === strpos( $value, 'var:' ) ) {
		$value = 'var(--wp--' . str_replace( '|', '--', substr( $value, 4 ) ) . ')';
	}
	if ( false !== strpos( $value, '/*' ) || false !== strpos( $value, '*/' ) ) {
		return '';
	}
	return preg_match( '/^[a-z0-9\s.%(),+*\/-]+$/i', $value ) ? $value : '';
}

/**
 * The token overrides for the Styles values above that differ from the theme default.
 *
 * @return string CSS, or ''.
 */
function kits_style_tokens_css() {
	$map = array(
		'--kit-hero-h'        => array(
			'value'   => wp_get_global_styles( array( 'dimensions', 'minHeight' ), array( 'block_name' => 'kits/hero' ) ),
			'default' => array( 'var(--kit-hero-h)' ),
		),
		'--kit-space-section' => array(
			// The top padding: one token holds both sides, so set top and bottom together in Styles.
			'value'   => wp_get_global_styles( array( 'variations', 'kits-section', 'spacing', 'padding', 'top' ), array( 'block_name' => 'core/group' ) ),
			'default' => array( 'var(--wp--preset--spacing--section)', 'var(--kit-space-section)' ),
		),
	);
	$out = '';
	foreach ( $map as $token => $row ) {
		$css = kits_style_value_css( $row['value'] );
		// A value that refers to the token itself (or to the preset that is the token) would make the token cyclic and
		// invalid everywhere, so it is left to the block that holds it.
		$self = false !== strpos( $css, $token ) || array_filter( $row['default'], fn( $d ) => false !== strpos( $css, trim( $d, 'var()' ) ) );
		if ( '' !== $css && ! in_array( $css, $row['default'], true ) && ! $self ) {
			$out .= $token . ':' . $css . ';';
		}
	}
	return '' !== $out ? ':root{' . $out . '}' : '';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$css = kits_style_tokens_css();
		if ( '' === $css ) {
			return;
		}
		// Right after the theme's tokens (kit-tokens.css), so the Styles value wins at every width. A site whose own token
		// file sets these two tokens names that file's handle here.
		foreach ( array( apply_filters( 'kits_style_tokens_handle', 'kit-tokens' ), 'global-styles' ) as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				wp_add_inline_style( $handle, $css );
				return;
			}
		}
	},
	20
);
