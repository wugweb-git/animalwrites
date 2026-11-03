<?php
/**
 * Kits blocks, and the Styles settings that every matching part follows.
 *
 * - kits/hero (blocks/hero): the page hero. Its look is block supports, so Styles > Blocks > Hero holds its height
 *   (Dimensions > Minimum height), colours, padding, border and shadow.
 * - Two Styles values are also the theme's tokens, so heroes and sections that are not (yet) these blocks follow them too:
 *     Styles > Blocks > Hero > Minimum height             -> --kit-hero-h        (every hero: Home, campaigns, covers)
 *     Styles > Blocks > Group > Section > Padding top      -> --kit-space-section     (every section, band and campaign section)
 *     Styles > Blocks > Group > Section > Padding bottom   -> --kit-space-section-end (their bottom side)
 *   While a value is the theme default (the Section preset), nothing is printed and the tokens keep their 48 / 70 / 96
 *   steps at 768 and 1200 (--kit-space-section-base, kit-tokens.css). A value set in Styles is one value at every width.
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
 * Markup printed by PHP as a core Group block, so a Group style on it (Section, Head band) gets its CSS: WordPress prints
 * a block style's settings only for blocks that render with it. $html is the group's full markup, as the block saves it.
 *
 * @param string $html  The group's markup (outer tag included).
 * @param array  $attrs Group attributes: className with the style (is-style-kits-section), tagName, align, layout.
 * @return string
 */
function kits_group_html( $html, $attrs ) {
	return render_block(
		array(
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		)
	);
}

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
		'--kit-hero-h'            => array(
			'value'   => wp_get_global_styles( array( 'dimensions', 'minHeight' ), array( 'block_name' => 'kits/hero' ) ),
			'default' => array( 'var(--kit-hero-h)' ),
		),
		'--kit-space-section'     => array(
			'value'   => wp_get_global_styles( array( 'variations', 'kits-section', 'spacing', 'padding', 'top' ), array( 'block_name' => 'core/group' ) ),
			'default' => array( 'var(--wp--preset--spacing--section)', 'var(--kit-space-section-base)' ),
		),
		'--kit-space-section-end' => array(
			'value'   => wp_get_global_styles( array( 'variations', 'kits-section', 'spacing', 'padding', 'bottom' ), array( 'block_name' => 'core/group' ) ),
			'default' => array( 'var(--wp--preset--spacing--section)', 'var(--kit-space-section-base)' ),
		),
	);
	$out = '';
	foreach ( $map as $token => $row ) {
		$css = kits_style_value_css( $row['value'] );
		// A value that names one of these tokens could make them cyclic and invalid everywhere, so it is left to the block
		// that holds it. The Section preset points at --kit-space-section-base, so it is safe to use.
		$self = (bool) preg_match( '/(' . implode( '|', array_map( 'preg_quote', array_keys( $map ) ) ) . ')(?![\w-])/', $css );
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
