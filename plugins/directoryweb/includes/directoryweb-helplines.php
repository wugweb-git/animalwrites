<?php
/**
 * Gutenberg Block: directoryweb/helplines
 * Displays national and district emergency helpline cards (112, 1962, official animal husbandry, local rescue).
 * Phase 2 block for DirectoryWeb (plan §1.6).
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/helplines block.
 */
function directoryweb_register_helplines_block() {
	register_block_type(
		'directoryweb/helplines',
		array(
			'attributes'      => array(
				'districtCode' => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'title'        => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_callback' => 'directoryweb_helplines_render',
		)
	);
}
add_action( 'init', 'directoryweb_register_helplines_block' );

/**
 * Render the directoryweb/helplines block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function directoryweb_helplines_render( $attributes = array() ) {
	$code = ! empty( $attributes['districtCode'] ) ? (int) $attributes['districtCode'] : 0;

	// Dynamic context: resolve current district place if no code provided.
	if ( ! $code && function_exists( 'aw_current_place' ) ) {
		$d = aw_current_place();
		if ( $d && 'district' === ( $d->kind ?? '' ) ) {
			$code = (int) $d->code;
		}
	}

	if ( ! $code ) {
		return '<!-- directoryweb/helplines: no district active -->';
	}

	$place = function_exists( 'aw_place_by_code' ) ? aw_place_by_code( $code, 'district' ) : null;
	if ( ! $place && function_exists( 'aw_current_place' ) ) {
		$place = aw_current_place();
	}

	if ( ! $place || ! function_exists( 'aw_district_helplines_html' ) ) {
		return '<!-- directoryweb/helplines: place not resolved -->';
	}

	return aw_district_helplines_html( $place );
}
