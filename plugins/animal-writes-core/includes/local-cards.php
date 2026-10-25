<?php
/**
 * Local cards (owner copy, 9 Oct 2026):
 * - (The News item card "Organisations in {State}" was removed with the one item template, owner 20 Oct 2026: an item
 *   page ends with at most two "More" groups.)
 * - Directory district pages, and district × type pages, with 0–2 listings (owner, 25 Oct 2026): a card after the list
 *   with two ways on: "Volunteer near you" (/volunteer/) and "Add an organisation" (Contact, reason "List your
 *   organisation"), plus the whole state.
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

// District or district × type page with 0–2 listings: the card after the list.
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		if ( 'aw_listing' !== ( $block['attrs']['query']['postType'] ?? '' ) || ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() ) {
			return $html;
		}
		$d = aw_dir_place( (string) get_query_var( 'aw_place' ) );
		if ( ! $d || 'district' !== $d->kind ) {
			return $html;
		}
		$type = (string) get_query_var( 'aw_dir_type' );
		$term = '' !== $type ? get_term_by( 'slug', $type, 'post_tag' ) : null;
		if ( '' !== $type && ! $term ) {
			return $html;
		}
		$n = 0;
		if ( $term ) {
			foreach ( aw_place_counts()['pairs'] as $p ) { // stored counts, as the robots rule and the sitemap
				if ( (int) $p[0] === (int) $d->code && $p[1] === $type ) {
					$n = (int) $p[3];
				}
			}
		} else {
			$n = aw_place_count( $d );
		}
		$state = aw_place_state( $d->state );
		if ( $n >= 3 || ! $state ) {
			return $html;
		}
		$what = $term ? mb_strtolower( $term->name ) . ' listings' : 'listings'; // type names are singular ("Rescue Group")
		return $html . aw_local_card(
			sprintf( 'Few %s in %s yet', $what, $d->name ),
			sprintf( 'If there\'s no group near you, you can volunteer to help animals in %s, or tell us about one we\'ve missed.', $d->name ),
			'<a class="kit-btn" href="' . esc_url( home_url( '/volunteer/' ) ) . '">Volunteer near you</a>'
			. '<a class="kit-btn kit-btn--secondary" href="' . esc_url( home_url( '/contact/?about=listing' ) ) . '">Add an organisation</a>'
			. '<a class="kit-btn kit-btn--ghost" href="' . esc_url( aw_directory_url( $state->slug, $type ) ) . '">' . esc_html( sprintf( 'See all in %s', $state->name ) ) . '</a>'
		);
	},
	10,
	2
);
