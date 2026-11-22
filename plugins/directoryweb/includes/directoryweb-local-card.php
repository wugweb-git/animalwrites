<?php
/**
 * Gutenberg Block: directoryweb/local-card
 * Displays a small-district helper card when a district has 0-2 listings,
 * inviting users to volunteer or submit an organisation.
 * Phase 2 block for DirectoryWeb (plan §1.6).
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/local-card block.
 */
function directoryweb_register_local_card_block() {
	register_block_type(
		'directoryweb/local-card',
		array(
			'attributes'      => array(
				'heading'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'description' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_callback' => 'directoryweb_local_card_render',
		)
	);
}
add_action( 'init', 'directoryweb_register_local_card_block' );

/**
 * Render the directoryweb/local-card block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function directoryweb_local_card_render( $attributes = array() ) {
	$heading     = ! empty( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
	$description = ! empty( $attributes['description'] ) ? sanitize_text_field( $attributes['description'] ) : '';

	// When attributes are explicitly given, render directly.
	if ( '' !== $heading ) {
		$actions = '<a class="kit-btn" href="' . esc_url( home_url( '/volunteer/' ) ) . '">' . esc_html__( 'Volunteer near you', 'directoryweb' ) . '</a>'
			. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">' . esc_html__( 'Add an organisation', 'directoryweb' ) . '</a>';
		return function_exists( 'aw_local_card' ) ? aw_local_card( $heading, $description, $actions ) : '<aside class="aw-local-card"><h2>' . esc_html( $heading ) . '</h2><p>' . esc_html( $description ) . '</p></aside>';
	}

	// Dynamic context: resolve current place on district pages.
	if ( ! function_exists( 'aw_dir_place' ) || ! function_exists( 'aw_place_count' ) ) {
		return '';
	}

	$place_var = (string) get_query_var( 'aw_place' );
	$d         = $place_var ? aw_dir_place( $place_var ) : null;
	if ( ! $d || 'district' !== $d->kind ) {
		return '';
	}

	$n = aw_place_count( $d );
	$state = function_exists( 'aw_place_state' ) ? aw_place_state( $d->state ) : null;
	if ( $n >= 3 || ! $state ) {
		return '';
	}

	/* translators: %s: district name. */
	$head = sprintf( __( 'Few listings in %s yet', 'directoryweb' ), $d->name );
	/* translators: %s: district name. */
	$line = sprintf( __( 'If there\'s no group near you, you can volunteer to help animals in %s, or tell us about one we\'ve missed.', 'directoryweb' ), $d->name );

	$actions = '<a class="kit-btn" href="' . esc_url( home_url( '/volunteer/' ) ) . '">' . esc_html__( 'Volunteer near you', 'directoryweb' ) . '</a>'
		. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">' . esc_html__( 'Add an organisation', 'directoryweb' ) . '</a>'
		. '<a class="kit-btn kit-btn--ghost" href="' . esc_url( aw_directory_url( $state->slug ) ) . '">' . esc_html( sprintf( __( 'See all in %s', 'directoryweb' ), $state->name ) ) . '</a>';

	return function_exists( 'aw_local_card' ) ? aw_local_card( $head, $line, $actions ) : '<aside class="aw-local-card"><h2>' . esc_html( $head ) . '</h2><p>' . esc_html( $line ) . '</p><div class="aw-local-card-actions">' . $actions . '</div></aside>';
}
