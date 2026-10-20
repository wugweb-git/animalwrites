<?php
/**
 * Local cards (owner copy, 9 Oct 2026):
 * - (The News item card "Organisations in {State}" was removed with the one item template, owner 20 Oct 2026: an item
 *   page ends with at most two "More" groups.)
 * - Directory district pages with fewer than 3 listings: "Few listings in {District} yet", with the state and
 *   "Add an organisation" (Contact, reason "List your organisation").
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One card. */
function aw_local_card( $head, $line, $actions ) {
	$id = 'aw-local-' . wp_unique_id();
	return '<aside class="aw-local-card" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="aw-local-card-head" id="' . esc_attr( $id ) . '">' . esc_html( $head ) . '</h2><p>' . esc_html( $line ) . '</p><div class="aw-local-card-actions">' . $actions . '</div></aside>';
}

// District page with fewer than 3 listings: the card after the list.
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		if ( 'aw_listing' !== ( $block['attrs']['query']['postType'] ?? '' ) || ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() || get_query_var( 'aw_dir_type' ) ) {
			return $html;
		}
		$d = aw_dir_place( (string) get_query_var( 'aw_place' ) );
		if ( ! $d || 'district' !== $d->kind || aw_place_count( $d ) >= 3 ) {
			return $html;
		}
		$state = aw_place_state( $d->state );
		if ( ! $state ) {
			return $html;
		}
		return $html . aw_local_card(
			sprintf( 'Few listings in %s yet', $d->name ),
			sprintf( 'Try the whole of %s, or tell us about a group we\'ve missed.', $state->name ),
			'<a class="kit-btn" href="' . esc_url( aw_directory_url( $state->slug ) ) . '">' . esc_html( sprintf( 'See all in %s', $state->name ) ) . '</a>'
			. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Add an organisation</a>'
		);
	},
	10,
	2
);
