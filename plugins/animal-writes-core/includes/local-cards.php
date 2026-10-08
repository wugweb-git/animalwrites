<?php
/**
 * Local cards (owner copy, 9 Oct 2026):
 * - [aw_news_local] on a News item that has a place: "Organisations in {State}", linked to that state's Directory page.
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

add_shortcode(
	'aw_news_local',
	function () {
		$id = get_queried_object_id();
		if ( ! is_singular( 'aw_news' ) || ! function_exists( 'aw_item_place' ) ) {
			return '';
		}
		$p     = aw_item_place( $id );
		$state = $p && 'district' === $p->kind ? aw_place_state( $p->state ) : $p;
		if ( ! $state ) {
			return '';
		}
		return aw_local_card(
			sprintf( 'Organisations in %s', $state->name ),
			sprintf( 'Rescue groups, shelters and government vets you can contact in %s.', $state->name ),
			'<a class="kit-btn kit-btn--secondary" href="' . esc_url( aw_directory_url( $state->slug ) ) . '">' . esc_html( sprintf( 'See all in %s', $state->name ) ) . '</a>'
		);
	}
);

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
