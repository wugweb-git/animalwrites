<?php
/**
 * Gutenberg Block: directoryweb/find-help
 * Displays the Directory search bar, place counts, and quick filter pills.
 * Phase 2 block for DirectoryWeb (plan §1.6).
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the directoryweb/find-help block.
 */
function directoryweb_register_find_help_block() {
	register_block_type(
		'directoryweb/find-help',
		array(
			'attributes'      => array(
				'compact' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'directoryweb_find_help_render',
		)
	);
}
add_action( 'init', 'directoryweb_register_find_help_block' );

/**
 * Render the directoryweb/find-help block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
function directoryweb_find_help_render( $attributes = array() ) {
	$compact = ! isset( $attributes['compact'] ) || ! empty( $attributes['compact'] );

	if ( function_exists( 'directoryweb_use_style' ) ) {
		directoryweb_use_style( 'aw-directory' );
		directoryweb_use_style( 'dw-listing' );
	}

	$total = function_exists( 'aw_listing_public_count' ) ? aw_listing_public_count() : 0;
	$dir   = function_exists( 'aw_hub_url' ) ? aw_hub_url( 'aw_listing' ) : home_url( '/directory/' );

	$opts   = '<option value="">' . esc_html__( 'All states and UTs', 'directoryweb' ) . '</option>';
	$counts = array();
	$states = function_exists( 'aw_place_states' ) ? (array) aw_place_states() : array();

	foreach ( $states as $st ) {
		$n = function_exists( 'aw_state_count' ) ? aw_state_count( $st ) : ( function_exists( 'aw_place_count' ) ? aw_place_count( $st ) : 0 );
		if ( ! $n ) {
			continue;
		}
		$counts[] = array( $st, $n );
		$opts    .= '<option value="' . esc_attr( $st->slug ?? '' ) . '">' . esc_html( $st->name ?? '' ) . '</option>';
	}
	$with = count( $counts );

	if ( $compact ) {
		$pills = '';
		$types = function_exists( 'aw_find_help_top_types' ) ? aw_find_help_top_types( 6 ) : array();
		foreach ( $types as $t ) {
			$pills .= '<li><a class="kit-filter-pill" href="' . esc_url( add_query_arg( 'tag', $t[0], $dir ) ) . '">' . esc_html( $t[1] ) . '</a></li>';
		}

		$q_input     = '<label class="screen-reader-text" for="aw-ed-q">' . esc_html__( 'Organisation name', 'directoryweb' ) . '</label><span class="kit-ico kit-ico--search" aria-hidden="true"></span><input id="aw-ed-q" class="aw-findhelp-q" type="search" name="q" placeholder="' . esc_attr__( 'Search by name', 'directoryweb' ) . '">';
		$state_input = '<label class="screen-reader-text" for="aw-ed-state">' . esc_html__( 'State or union territory', 'directoryweb' ) . '</label><select id="aw-ed-state" class="aw-findhelp-state" name="state">' . $opts . '</select>';

		if ( function_exists( 'aw_dir_search_controls' ) ) {
			$shared      = aw_dir_search_controls(
				array(
					'prefix'      => 'aw-ed',
					'cls'         => 'aw-findhelp-',
					'show_labels' => false,
					'q_label'     => __( 'Organisation name', 'directoryweb' ),
					'placeholder' => __( 'Search by name', 'directoryweb' ),
					'state_label' => __( 'State or union territory', 'directoryweb' ),
				)
			);
			$q_input     = $shared['q'] ?? $q_input;
			$state_input = $shared['state'] ?? $state_input;
		}

		/* translators: 1: total listings count, 2: states count. */
		$summary = sprintf( __( '%1$s vets, shelters and rescue groups in %2$s states.', 'directoryweb' ), '<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>', '<strong>' . (int) $with . '</strong>' );

		return '<div class="kit-sec-head"><div><h2 class="wp-block-heading" id="aw-find-h">' . esc_html__( 'Find help near you', 'directoryweb' ) . '</h2>'
			. '<p>' . $summary . '</p></div></div>'
			. '<div class="aw-findhelp-bar"><form class="aw-ed-search aw-findhelp-form" role="search" method="get" action="' . esc_url( $dir ) . '">'
			. '<input type="hidden" name="aw_filter" value="1">'
			. $q_input . $state_input
			. '<button type="submit" class="kit-btn kit-btn--ico-search aw-findhelp-go">' . esc_html__( 'Search', 'directoryweb' ) . '</button>'
			. '<a class="kit-btn kit-btn--ghost kit-btn--ico-map-pin aw-findhelp-near" href="' . esc_url( $dir ) . '#near-me">' . esc_html__( 'Use my location', 'directoryweb' ) . '</a>'
			. '</form><p class="aw-findhelp-status" role="status" aria-live="polite"></p></div>'
			. ( $pills ? '<div class="aw-findhelp-filters"><p class="aw-findhelp-label" id="aw-findhelp-filters-h">' . esc_html__( 'Quick filters', 'directoryweb' ) . '</p><ul class="aw-findhelp-types" aria-labelledby="aw-findhelp-filters-h">' . $pills . '</ul></div>' : '' )
			. '<div class="aw-findhelp-actions"><a class="kit-btn kit-btn--brand aw-findhelp-claim" href="' . esc_url( home_url( '/claim/' ) ) . '">' . esc_html__( 'Claim or update your listing', 'directoryweb' ) . '</a>'
			. '<a class="kit-btn kit-btn--secondary aw-findhelp-browse" href="' . esc_url( $dir ) . '#aw-browse-states-h">' . esc_html__( 'Browse all states', 'directoryweb' ) . '</a></div>';
	}

	return '<div class="aw-findhelp-full"><form class="aw-ed-search" role="search" method="get" action="' . esc_url( $dir ) . '">'
		. '<input type="hidden" name="aw_filter" value="1">'
		. '<label class="screen-reader-text" for="aw-ed-q">' . esc_html__( 'Organisation name', 'directoryweb' ) . '</label><input id="aw-ed-q" type="search" name="q" placeholder="' . esc_attr__( 'Search by name', 'directoryweb' ) . '">'
		. '<label class="screen-reader-text" for="aw-ed-state">' . esc_html__( 'State or union territory', 'directoryweb' ) . '</label><select id="aw-ed-state" name="state">' . $opts . '</select>'
		. '<button type="submit" class="kit-btn">' . esc_html__( 'Search', 'directoryweb' ) . '</button>'
		. '</form></div>';
}
