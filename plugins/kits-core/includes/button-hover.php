<?php
/**
 * Button hover colours chosen in Styles → Blocks → Button, printed as the theme's hover tokens.
 *
 * Buttons read --kit-btn-* tokens (blocks.php maps background, text, border, radius, weight and padding). The theme's
 * hover colours are tokens too (--kit-btn-<style>-bg-hover / -fg-hover); a hover colour set in Global Styles
 * (styles.elements.button.:hover, or a Button block style's variation) is printed as that token, after blocks.php's.
 * Buttons themselves are edited in Styles (owner, 11 Nov 2026); Theme Options no longer has a Buttons section.
 *
 * @package kits-core
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The hover tokens of each button style, by key: 'fill' is the default style, the rest are core/button style slugs.
 * Each is [ background token, text token ]. Brand has none: its hover is a darker shade of its own colour.
 *
 * @return array
 */
function kits_button_hover_tokens(): array {
	return array(
		'fill'           => array( '--kit-btn-primary-bg-hover', '--kit-btn-primary-fg-hover' ),
		'kits-secondary' => array( '--kit-btn-secondary-bg-hover', '--kit-btn-secondary-fg-hover' ),
		'kits-ghost'     => array( '--kit-btn-ghost-bg-hover', '--kit-btn-ghost-fg-hover' ),
	);
}

/**
 * The hover tokens for the hover colours chosen in Styles, e.g. ':root{--kit-btn-primary-bg-hover:…}'.
 *
 * @return string CSS, or ''.
 */
function kits_button_hover_css(): string {
	if ( ! function_exists( 'kits_style_value_css' ) ) {
		return '';
	}
	$out = '';
	foreach ( kits_button_hover_tokens() as $key => $tokens ) {
		foreach ( array( 'background', 'text' ) as $i => $prop ) {
			$path = 'fill' === $key
				? array( 'elements', 'button', ':hover', 'color', $prop )
				: array( 'variations', $key, 'elements', 'button', ':hover', 'color', $prop );
			$opts = 'fill' === $key ? array() : array( 'block_name' => 'core/button' );
			$css  = kits_style_value_css( wp_get_global_styles( $path, $opts ) );
			// A value naming a button token could make the tokens cyclic, so it is left out (as in blocks.php).
			if ( '' !== $css && ! preg_match( '/--kit-btn-/', $css ) ) {
				$out .= $tokens[ $i ] . ':' . $css . ';';
			}
		}
	}
	return '' !== $out ? ':root{' . $out . '}' : '';
}

// After blocks.php's tokens (priority 20), on the same stylesheet, so a chosen hover colour wins over the derived one.
add_action(
	'wp_enqueue_scripts',
	function () {
		$css = kits_button_hover_css();
		if ( '' === $css ) {
			return;
		}
		foreach ( array( apply_filters( 'kits_style_tokens_handle', 'kit-tokens' ), 'global-styles' ) as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				wp_add_inline_style( $handle, $css );
				return;
			}
		}
	},
	21
);
