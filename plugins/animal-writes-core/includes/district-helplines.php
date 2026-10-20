<?php
/**
 * District helplines (owner, 20 Oct 2026: "fill phone numbers first from official state lists, then build the helpline
 * blocks on the district pages"). On a Directory district page (page 1, no type filter) a block before the list:
 * "Animal helpline numbers in {District}", in the Helplines component (aw-helplines / aw-helpline cards).
 *
 * The cards, in order:
 *   1. 112, the national emergency number (always the first card, the red one).
 *   2. 1962, when the state's "1962 Mobile Veterinary Unit" listing exists (the card links to it).
 *   3. Official district numbers kept in the option aw_district_helplines: district code => list of
 *      {name, number, line, source, url, checked (Y-m-d)}, each from an official page (e.g. the District Animal
 *      Husbandry Offices on ahd.kerala.gov.in).
 *   4. Up to six listings in the district that have a phone number: ambulance and rescue services first, then
 *      government vets, then the rest; listings reported closed are left out. Each card links to its listing, where
 *      the number's source is shown.
 * Legal aid listings are left out. The block shows only when the district has at least one number of its own (3 or 4); 112 and 1962 alone are in the
 * footer already.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Services whose listings come first: the ones people call in an emergency. */
const AW_HELPLINE_FIRST = array( 'ambulance', 'emergency-rescue', 'rescue', 'wildlife-rescue', 'bird-treatment' );

/** Official numbers for a district (option aw_district_helplines). */
function aw_district_official_numbers( $code ) {
	$all = get_option( 'aw_district_helplines', array() );
	return is_array( $all ) && ! empty( $all[ (int) $code ] ) ? (array) $all[ (int) $code ] : array();
}

/** A tel: address for a number as printed. */
function aw_helpline_tel( $num ) {
	$n = preg_replace( '/[^\d+]/', '', (string) $num );
	return 'tel:' . $n;
}

/** Listings in a district with a phone, best first. */
function aw_district_helpline_listings( $code, $max = 6 ) {
	$ids = get_posts(
		array(
			'post_type'   => 'aw_listing',
			'post_status' => 'publish',
			'numberposts' => 200,
			'fields'      => 'ids',
			'meta_query'  => array(
				array( 'key' => 'aw_district_code', 'value' => (int) $code ),
				array( 'key' => 'aw_phones', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		)
	);
	$rows = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'aw_reported_closed', true ) ) {
			continue;
		}
		$num = function_exists( 'aw_listing_first_phone' ) ? aw_listing_first_phone( $id ) : '';
		if ( ! $num ) {
			$p   = aw_listing_phones( $id );
			$num = $p ? $p[0]['number'] : '';
		}
		if ( ! $num ) {
			continue;
		}
		$types = wp_list_pluck( aw_listing_types( $id ), 'slug' );
		if ( in_array( 'legal-aid', $types, true ) ) {
			continue; // legal services authorities help with cases, not with an animal in need
		}
		$svcs  = wp_list_pluck( aw_item_tags( $id, aw_service_slugs() ), 'slug' );
		$rank  = array_intersect( $svcs, AW_HELPLINE_FIRST ) ? 0 : ( array_intersect( $types, AW_VET_TYPES ) ? 1 : 2 );
		$type  = aw_listing_types( $id );
		$rows[] = array( 'id' => $id, 'num' => $num, 'rank' => $rank, 'type' => $type ? $type[0]->name : '' );
	}
	usort( $rows, fn( $a, $b ) => array( $a['rank'], get_the_title( $a['id'] ) ) <=> array( $b['rank'], get_the_title( $b['id'] ) ) );
	return array_slice( $rows, 0, $max );
}

/** One card. */
function aw_helpline_card( $head, $num, $line, $src, $href = '' ) {
	$h = $href ? '<a href="' . esc_url( $href ) . '">' . esc_html( $head ) . '</a>' : esc_html( $head );
	return '<div class="aw-helpline"><h3>' . $h . '</h3><a class="aw-helpline-num" href="' . esc_attr( aw_helpline_tel( $num ) ) . '">' . esc_html( $num ) . '</a>'
		. ( $line ? '<p>' . esc_html( $line ) . '</p>' : '' ) . ( $src ? '<p class="aw-helpline-src">' . $src . '</p>' : '' ) . '</div>';
}

/** The block for a district place, or ''. */
function aw_district_helplines_html( $d ) {
	$own = '';
	foreach ( aw_district_official_numbers( $d->code ) as $o ) {
		$src  = esc_html( (string) ( $o['source'] ?? '' ) );
		$src  = ! empty( $o['url'] ) ? '<a href="' . esc_url( $o['url'] ) . '" rel="nofollow noopener" target="_blank">' . $src . '</a>' : $src;
		$src .= ! empty( $o['checked'] ) ? ', checked ' . aw_date( $o['checked'] ) : '';
		$own .= aw_helpline_card( (string) $o['name'], (string) $o['number'], (string) ( $o['line'] ?? '' ), $src );
	}
	foreach ( aw_district_helpline_listings( $d->code ) as $r ) {
		$own .= aw_helpline_card( html_entity_decode( get_the_title( $r['id'] ) ), $r['num'], $r['type'], esc_html__( 'From the listing (source on its page)', 'animal-writes-core' ), get_permalink( $r['id'] ) );
	}
	if ( '' === $own ) {
		return '';
	}
	$cards = aw_helpline_card( __( 'Emergency', 'animal-writes-core' ), '112', __( 'Police, fire and ambulance, anywhere in India.', 'animal-writes-core' ), esc_html__( 'Ministry of Home Affairs', 'animal-writes-core' ) );
	$state = aw_place_state( $d->state );
	$mvu   = $state ? get_page_by_path( '1962-mobile-veterinary-unit-' . $state->slug, OBJECT, 'aw_listing' ) : null;
	if ( $mvu && 'publish' === $mvu->post_status ) {
		$cards .= aw_helpline_card( __( 'Mobile Veterinary Units', 'animal-writes-core' ), '1962', __( 'A vet comes to the animal.', 'animal-writes-core' ), esc_html( sprintf( '%s animal husbandry department', $state->name ) ), get_permalink( $mvu ) );
	}
	$id = 'aw-dh-' . wp_unique_id();
	/* translators: %s: district. */
	return '<section class="aw-district-helplines" aria-labelledby="' . esc_attr( $id ) . '"><h2 id="' . esc_attr( $id ) . '">' . esc_html( sprintf( __( 'Animal helpline numbers in %s', 'animal-writes-core' ), $d->name ) ) . '</h2>'
		. '<div class="aw-helplines">' . $cards . $own . '</div>'
		. '<p class="aw-emergency">' . esc_html__( 'An animal in danger right now: call 112 first.', 'animal-writes-core' ) . '</p></section>';
}

// Before the list on a district page (page 1, no type filter).
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		if ( 'aw_listing' !== aw_hub_of_query( $block['attrs']['queryId'] ?? 0 ) || ! aw_is_directory_view() || get_query_var( 'aw_dir_type' ) || aw_request_type() || aw_hub_page() > 1 ) {
			return $html;
		}
		$d = aw_current_place();
		if ( ! $d || 'district' !== $d->kind ) {
			return $html;
		}
		return aw_district_helplines_html( $d ) . $html;
	},
	9,
	2
);
