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
 * - Buttons: every button on the site (the core Button block and the .kit-btn links PHP prints) is one system that reads
 *   --kit-btn-* tokens (kit-primitives.css), so the Button settings in Styles reach them all:
 *     Styles > Blocks > Button > Colour (background, text)       -> --kit-btn-primary-bg / -fg (and their hover)
 *     Styles > Blocks > Button > Border > Radius                 -> --kit-btn-radius
 *     Styles > Blocks > Button > Typography > Font weight        -> --kit-btn-weight
 *     Styles > Blocks > Button > Dimensions > Padding (left)     -> --kit-btn-pad (both sides)
 *     Styles > Blocks > Button > Secondary / Ghost / Brand style -> --kit-btn-secondary-* / -ghost-* / -brand-*
 *   The Button block's settings are the same as Styles > Elements > Button; either place can be used.
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
 * and colour values pass (numbers, units, #hex, colour names, var(), rgb(), color-mix(), min(), max(), clamp(), calc());
 * anything else returns ''.
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
	return preg_match( '/^[a-z0-9\s.%(),+*\/#-]+$/i', $value ) ? $value : '';
}

/**
 * The token overrides for the Styles values above that differ from the theme default.
 *
 * @return string CSS, or ''.
 */
function kits_style_tokens_css() {
	$btn  = function ( array $path ) {
		return wp_get_global_styles( array_merge( array( 'elements', 'button' ), $path ) );
	};
	$var  = function ( $slug, array $path ) {
		return wp_get_global_styles( array_merge( array( 'variations', $slug ), $path ), array( 'block_name' => 'core/button' ) );
	};
	$hover = function ( $css ) {
		return 'color-mix(in srgb, ' . $css . ' var(--kit-btn-hover-mix), var(--kit-color-ink))';
	};
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
		'--kit-btn-primary-bg'    => array(
			'value'   => $btn( array( 'color', 'background' ) ),
			'default' => array( 'var(--wp--preset--color--secondary)', 'var(--kit-btn-primary-bg)' ),
			'also'    => array( '--kit-btn-primary-bg-hover' => $hover ),
		),
		'--kit-btn-primary-fg'    => array(
			'value'   => $btn( array( 'color', 'text' ) ),
			'default' => array( 'var(--wp--preset--color--on-ink)', 'var(--kit-btn-primary-fg)' ),
			'also'    => array( '--kit-btn-primary-fg-hover' => null ),
		),
		'--kit-btn-radius'        => array(
			'value'   => $btn( array( 'border', 'radius' ) ),
			'default' => array( 'var(--wp--preset--border-radius--pill)', 'var(--kit-radius-pill)' ),
		),
		'--kit-btn-weight'        => array(
			'value'   => $btn( array( 'typography', 'fontWeight' ) ),
			'default' => array( '500', 'var(--kit-weight-medium)' ),
		),
		'--kit-btn-pad'           => array(
			'value'   => $btn( array( 'spacing', 'padding', 'left' ) ),
			'default' => array( 'var(--wp--preset--spacing--50)', 'var(--kit-btn-pad)' ),
		),
		'--kit-btn-secondary-bg'  => array(
			'value'   => $var( 'kits-secondary', array( 'color', 'background' ) ),
			'default' => array( 'var(--kit-btn-secondary-bg)' ),
		),
		'--kit-btn-secondary-fg'  => array(
			'value'   => $var( 'kits-secondary', array( 'color', 'text' ) ),
			'default' => array( 'var(--kit-btn-secondary-fg)' ),
		),
		'--kit-btn-secondary-line' => array(
			'value'   => $var( 'kits-secondary', array( 'border', 'color' ) ),
			'default' => array( 'var(--kit-btn-secondary-line)' ),
		),
		'--kit-btn-ghost-fg'      => array(
			'value'   => $var( 'kits-ghost', array( 'color', 'text' ) ),
			'default' => array( 'var(--kit-btn-ghost-fg)' ),
		),
		'--kit-btn-ghost-line'    => array(
			'value'   => $var( 'kits-ghost', array( 'border', 'color' ) ),
			'default' => array( 'var(--kit-btn-ghost-line)' ),
		),
		'--kit-btn-brand-bg'      => array(
			'value'   => $var( 'kits-brand', array( 'color', 'background' ) ),
			'default' => array( 'var(--kit-btn-brand-bg)' ),
		),
		'--kit-btn-brand-fg'      => array(
			'value'   => $var( 'kits-brand', array( 'color', 'text' ) ),
			'default' => array( 'var(--kit-btn-brand-fg)' ),
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
			// Tokens that follow this one (a button's hover colour): derived from the value, or the same value.
			foreach ( isset( $row['also'] ) ? $row['also'] : array() as $also => $derive ) {
				$out .= $also . ':' . ( $derive ? $derive( $css ) : $css ) . ';';
			}
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
