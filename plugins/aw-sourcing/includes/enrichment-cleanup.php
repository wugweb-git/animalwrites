<?php
/**
 * Directory clean-up proposals (owner, 25 Oct 2026): the Directory worker's clean-up pass (animal-writes-workers,
 * aw_workers/directory/cleanup.py) sends two kinds of proposal through aw/v1/enrich/results, and they are reviewed in
 * Sourcing → Enrichment review like every other value.
 *
 * - place = {state, district} (LGD codes), from the listing's own PIN code (India Post) or address. It fills an
 *   empty place only: a listing with no state, or a state and no district, when the proposed district is in that
 *   state. Anything else (another state, a district already set, low confidence, claimed or verified) waits for
 *   review. Undo puts the old codes back.
 * - merge_into = the ID of the listing to keep (same name in the same district and a shared phone, email, website or
 *   address). Never applied by itself. Approving it merges: the kept listing's values stand; the other's fill its
 *   empty fields, and a second phone, email or website is kept beside the first (fill-empty + keep-both, each logged
 *   as an applied row with Undo); its types are added; links from other items (Featured in, organiser) move; the other
 *   listing goes to the trash with aw_merged_into and a 301 to the kept one (owner, 25 Oct: trash, not draft; WordPress empties the trash after 30 days). Undo
 *   reverses all of it.
 *
 * GET aw/v1/enrich/cleanup?page=N: the published listings (1,000 a page) with their place codes, their own place text
 * and contacts; page 1 also carries the LGD states, districts and spelling aliases the worker matches against.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- place ---------- */

/** A listing's place as a proposal is shaped: {state, district} (first codes), or '' when it has none. */
function aw_enrich_place_current( $lid ) {
	list( $states, $districts ) = aw_item_codes( $lid );
	return $states ? array( 'state' => (int) $states[0], 'district' => (int) ( $districts[0] ?? 0 ) ) : '';
}

/** A proposed place, checked against the LGD file: [state, district or 0], or null. */
function aw_enrich_place_value( $value ) {
	$v = is_array( $value ) ? $value : (array) json_decode( (string) $value, true );
	$s = function_exists( 'aw_place_state' ) ? aw_place_state( (int) ( $v['state'] ?? 0 ) ) : null;
	$d = ! empty( $v['district'] ) && function_exists( 'aw_place_district' ) ? aw_place_district( (int) $v['district'] ) : null;
	if ( ! $s || ( ! empty( $v['district'] ) && ( ! $d || (int) $d->state !== (int) $s->code ) ) ) {
		return null;
	}
	return array( (int) $s->code, $d ? (int) $d->code : 0 );
}

/** Whether the listing already has this place (or a more precise one in the same state when only a state is proposed). */
function aw_enrich_place_held( $lid, $value ) {
	$p = aw_enrich_place_value( $value );
	if ( ! $p ) {
		return false;
	}
	list( $states, $districts ) = aw_item_codes( $lid );
	return in_array( $p[0], $states, true ) && ( 0 === $p[1] || in_array( $p[1], $districts, true ) );
}

/** Whether a proposed place only fills what is empty: no district yet, and no state or the same state. */
function aw_enrich_place_fills( $lid, $value ) {
	$p = aw_enrich_place_value( $value );
	list( $states, $districts ) = aw_item_codes( $lid );
	return $p && ! $districts && count( $states ) <= 1 && ( ! $states || $states[0] === $p[0] );
}

/** Store a place (refused when the codes are not in the LGD file). */
function aw_enrich_place_write( $lid, $value ) {
	$p = aw_enrich_place_value( $value );
	return $p && function_exists( 'aw_set_item_places' ) && aw_set_item_places( $lid, array( $p ) );
}

/** Put a place snapshot back: {states: [...], districts: [...]}. */
function aw_enrich_place_restore( $lid, $snap ) {
	delete_post_meta( $lid, 'aw_state_code' );
	delete_post_meta( $lid, 'aw_district_code' );
	foreach ( array_map( 'intval', (array) ( $snap['states'] ?? array() ) ) as $c ) {
		add_post_meta( $lid, 'aw_state_code', $c );
	}
	foreach ( array_map( 'intval', (array) ( $snap['districts'] ?? array() ) ) as $c ) {
		add_post_meta( $lid, 'aw_district_code', $c );
	}
	if ( function_exists( 'aw_place_counts_reset' ) ) {
		aw_place_counts_reset();
	}
}

/** "District, State" for a place value or a snapshot. */
function aw_enrich_place_label( $v ) {
	$v = (array) $v;
	if ( isset( $v['states'] ) ) {
		$v = array( 'state' => (int) ( $v['states'][0] ?? 0 ), 'district' => (int) ( $v['districts'][0] ?? 0 ) );
	}
	$s = ! empty( $v['state'] ) && function_exists( 'aw_place_state' ) ? aw_place_state( (int) $v['state'] ) : null;
	$d = ! empty( $v['district'] ) && function_exists( 'aw_place_district' ) ? aw_place_district( (int) $v['district'] ) : null;
	return $s ? ( $d ? $d->name . ', ' : '' ) . $s->name : '';
}

/* ---------- merge ---------- */

/** Meta never copied by a merge (its own state, scores, flags and the lists handled field by field). */
function aw_enrich_merge_skip( $key ) {
	return '_' === $key[0] || 0 === strpos( $key, 'rank_math' ) || 0 === strpos( $key, 'aw_phones' ) || 0 === strpos( $key, 'aw_opening_hours' )
		|| 0 === strpos( $key, 'aw_enrich' ) || in_array( $key, array( 'aw_featured', 'aw_contact_score', 'aw_review', 'aw_import_batch', 'aw_link_checked', 'aw_merged_into', 'aw_state_code', 'aw_district_code', 'aw_verified', 'aw_verified_on', 'aw_unverified', 'aw_reported_closed', 'aw_email_alt', 'aw_website_alt' ), true );
}

/**
 * Merge listing $from into $into (an approved merge_into row $rid). Returns what to undo, or why it could not merge.
 *
 * @param int $from Listing merged away.
 * @param int $into Listing kept.
 * @param int $rid  The approved row.
 * @return array|string
 */
function aw_enrich_merge( $from, $into, $rid ) {
	global $wpdb;
	if ( 'aw_listing' !== get_post_type( $from ) || 'aw_listing' !== get_post_type( $into ) || $from === $into ) {
		return 'not two listings';
	}
	if ( 'publish' !== get_post_status( $from ) || 'publish' !== get_post_status( $into ) ) {
		return 'both listings must be published';
	}
	if ( get_post_meta( $from, 'aw_merged_into', true ) || get_post_meta( $into, 'aw_merged_into', true ) ) {
		return 'already merged';
	}
	if ( aw_enrich_is_protected( $from ) ) {
		return 'the listing to merge away is claimed or verified';
	}
	$undo = array( 'into' => $into, 'old_url' => (string) get_permalink( $from ), 'rows' => array(), 'meta' => array(), 'tags' => array(), 'links' => array(), 'redirect' => 0, 'repointed' => array() );
	$src  = array( array( 'key' => 'listing', 'name' => sprintf( 'Merged listing #%d', $from ), 'url' => $undo['old_url'], 'fetched_at' => wp_date( 'c' ) ) );
	$log  = function ( $field, $value, $snap, $mode ) use ( $into, $src, $rid, &$undo ) {
		$row            = aw_enrich_add_row( $into, $field, $value, $snap, $src, 'high', 'approved', 'merge-' . $rid, 'both' === $mode ? aw_enrich_both_note( $field ) : 'From the merged listing (its field was empty here)' );
		$undo['rows'][] = $row;
		aw_enrich_supersede( $into, $field, (int) $row ); // a pending proposal for this field must not overwrite the merged value later
		if ( 'services' === ( aw_enrich_fields()[ $field ][1] ?? '' ) ) {
			$undo['services_touched'] = true;
		}
	};
	// Services also carry where they came from and when (aw_enrich_write sets both): kept so Undo puts them back.
	$undo['services_attr'] = array(
		'source' => metadata_exists( 'post', $into, 'aw_services_source' ) ? (string) get_post_meta( $into, 'aw_services_source', true ) : null,
		'on'     => metadata_exists( 'post', $into, 'aw_services_on' ) ? (string) get_post_meta( $into, 'aw_services_on', true ) : null,
	);

	// Phones: every number the kept listing does not have; a second number is kept beside the first, both unverified.
	foreach ( aw_listing_phones( $from ) as $row ) {
		if ( aw_enrich_has_value( $into, 'phone', $row['number'] ) ) {
			continue;
		}
		$mode = aw_listing_phones( $into ) ? 'both' : 'apply';
		$snap = aw_enrich_snapshot( $into, 'phone' );
		if ( aw_enrich_write( $into, $row['whatsapp'] ? 'whatsapp' : ( 'Emergency' === $row['label'] ? 'emergency' : 'phone' ), $row['number'], $mode ) ) {
			$log( 'phone', $row['number'], $snap, $mode );
		}
	}
	// The other fields: fill what is empty; email and website kept beside the first; list fields add.
	foreach ( aw_enrich_fields() as $field => $def ) {
		if ( in_array( $field, array( 'phone', 'whatsapp', 'emergency', 'logo', 'photos', 'place', 'merge_into', 'closed', 'place_id', 'maps_url' ), true ) ) {
			continue;
		}
		$value = aw_enrich_current( $from, $field );
		if ( ( is_array( $value ) && ! $value ) || ( ! is_array( $value ) && '' === trim( (string) $value ) ) ) {
			continue;
		}
		$value = aw_enrich_union( $into, $field, $value );
		if ( aw_enrich_has_value( $into, $field, $value ) ) {
			continue;
		}
		$mode = aw_enrich_is_empty( $into, $field ) ? 'apply' : ( in_array( $field, AW_ENRICH_LIST_FIELDS, true ) || ( isset( AW_ENRICH_ALT[ $field ] ) && '' === trim( (string) get_post_meta( $into, AW_ENRICH_ALT[ $field ], true ) ) ) ? 'both' : '' );
		if ( '' === $mode ) {
			continue; // the kept listing's value stands
		}
		$snap = aw_enrich_snapshot( $into, $field );
		if ( aw_enrich_write( $into, $field, $value, $mode ) ) {
			$log( $field, $value, $snap, $mode );
		}
	}
	// Other details the kept listing lacks (AWBI code and dates, import address, source...): copied, not replaced.
	$fields = array_filter( array_map( fn( $d ) => $d[1], aw_enrich_fields() ), fn( $k ) => 0 === strpos( $k, 'aw_' ) );
	foreach ( get_post_meta( $from ) as $key => $vals ) {
		if ( aw_enrich_merge_skip( $key ) || in_array( $key, $fields, true ) || ! metadata_exists( 'post', $from, $key ) ) {
			continue;
		}
		$v = maybe_unserialize( $vals[0] );
		if ( '' === $v || array() === $v || ( metadata_exists( 'post', $into, $key ) && '' !== get_post_meta( $into, $key, true ) ) ) {
			continue;
		}
		update_post_meta( $into, $key, $v );
		$undo['meta'][] = $key;
	}
	// Types and other tags.
	$add = array_values( array_diff( wp_get_post_terms( $from, 'post_tag', array( 'fields' => 'slugs' ) ), wp_get_post_terms( $into, 'post_tag', array( 'fields' => 'slugs' ) ) ) );
	if ( $add ) {
		wp_add_object_terms( $into, $add, 'post_tag' );
		$undo['tags'] = $add;
	}
	// Links from other items (Featured in, organiser).
	foreach ( array( 'aw_rel_org', 'aw_rel_org_auto', 'aw_rel_org_not', 'aw_organiser' ) as $rel ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $rel, (string) $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $m ) {
			$pid = (int) $m['post_id'];
			if ( in_array( (string) $into, array_map( 'strval', (array) get_post_meta( $pid, $rel, false ) ), true ) ) {
				delete_metadata_by_mid( 'post', (int) $m['meta_id'] ); // already linked to the kept one
				$undo['links'][] = array( 'post' => $pid, 'key' => $rel, 'deleted' => true );
			} else {
				update_metadata_by_mid( 'post', (int) $m['meta_id'], (string) $into );
				$undo['links'][] = array( 'post' => $pid, 'key' => $rel, 'mid' => (int) $m['meta_id'] );
			}
		}
	}
	// Redirects that pointed at the merged listing now point at the kept one; its own address gets a 301.
	$to    = (string) get_permalink( $into );
	$table = $wpdb->prefix . 'rank_math_redirections';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE url_to IN ( %s, %s )", $undo['old_url'], untrailingslashit( $undo['old_url'] ) ) ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			$wpdb->update( $table, array( 'url_to' => $to ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$undo['repointed'] = $ids;
	}
	wp_trash_post( $from ); // owner, 25 Oct 2026: merged duplicates go to the trash (WordPress empties it after 30 days; Undo works until then)
	update_post_meta( $from, 'aw_merged_into', $into );
	if ( function_exists( 'aw_rank_math_redirect' ) ) {
		$path             = trim( (string) wp_parse_url( $undo['old_url'], PHP_URL_PATH ), '/' );
		$undo['redirect'] = '' !== $path ? aw_rank_math_redirect( rawurldecode( $path ), $to ) : 0;
	}
	aw_enrich_after_change( $into );
	aw_enrich_after_change( $from );
	delete_transient( 'aw_seo_dir_data' );
	delete_transient( 'aw_featured_names' );
	if ( function_exists( 'aw_place_counts_reset' ) ) {
		aw_place_counts_reset();
	}
	return $undo;
}

/**
 * Undo a merge: the merged listing is published again at its old address, the 301 is switched off, redirects point
 * back, links return, and the values it gave the kept listing are taken back.
 *
 * @param int   $from The listing that was merged away.
 * @param array $u    What aw_enrich_merge() returned.
 */
function aw_enrich_unmerge( $from, $u ) {
	global $wpdb;
	$into = (int) ( $u['into'] ?? 0 );
	foreach ( array_reverse( (array) ( $u['rows'] ?? array() ) ) as $row ) {
		aw_enrich_undo( (int) $row );
	}
	foreach ( (array) ( $u['meta'] ?? array() ) as $key ) {
		delete_post_meta( $into, $key );
	}
	if ( ! empty( $u['services_touched'] ) && isset( $u['services_attr'] ) && is_array( $u['services_attr'] ) ) { // the services' source and date as they were before the merge
		foreach ( array( 'source' => 'aw_services_source', 'on' => 'aw_services_on' ) as $k => $meta ) {
			null === ( $u['services_attr'][ $k ] ?? null ) ? delete_post_meta( $into, $meta ) : update_post_meta( $into, $meta, (string) $u['services_attr'][ $k ] );
		}
	}
	if ( ! empty( $u['tags'] ) ) {
		wp_remove_object_terms( $into, (array) $u['tags'], 'post_tag' );
	}
	foreach ( (array) ( $u['links'] ?? array() ) as $l ) {
		if ( ! empty( $l['deleted'] ) ) {
			add_post_meta( (int) $l['post'], (string) $l['key'], (string) $from );
		} elseif ( ! empty( $l['mid'] ) ) {
			update_metadata_by_mid( 'post', (int) $l['mid'], (string) $from );
		}
	}
	if ( 'trash' === get_post_status( $from ) ) {
		wp_untrash_post( $from );
	}
	wp_update_post( array( 'ID' => $from, 'post_status' => 'publish' ) );
	delete_post_meta( $from, 'aw_merged_into' );
	$table = $wpdb->prefix . 'rank_math_redirections';
	if ( ! empty( $u['redirect'] ) ) {
		$wpdb->update( $table, array( 'status' => 'inactive' ), array( 'id' => (int) $u['redirect'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( (array) ( $u['repointed'] ?? array() ) as $id ) {
		$wpdb->update( $table, array( 'url_to' => (string) get_permalink( $from ) ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	aw_enrich_after_change( $into );
	aw_enrich_after_change( $from );
	delete_transient( 'aw_seo_dir_data' );
	if ( function_exists( 'aw_place_counts_reset' ) ) {
		aw_place_counts_reset();
	}
}

/* ---------- REST: what the clean-up pass reads ---------- */

/**
 * GET enrich/cleanup?page=1: published listings with their place codes, own place text and contacts (1,000 a page).
 *
 * @param WP_REST_Request $req Request.
 * @return array
 */
function aw_enrich_rest_cleanup( WP_REST_Request $req ) {
	global $wpdb;
	$page  = max( 1, (int) ( $req['page'] ?? 1 ) );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids   = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => 1000, 'offset' => ( $page - 1 ) * 1000, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
	update_meta_cache( 'post', $ids );
	$m     = fn( $id, $k ) => (string) get_post_meta( $id, $k, true );
	$items = array();
	foreach ( $ids as $id ) {
		list( $states, $districts ) = aw_item_codes( $id );
		$items[] = array(
			'id'             => (int) $id,
			'name'           => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			'url'            => get_permalink( $id ),
			'states'         => $states,
			'districts'      => $districts,
			'pincode'        => $m( $id, 'aw_pincode' ),
			'area'           => $m( $id, 'aw_area' ),
			'city'           => $m( $id, 'aw_place_city' ),
			'address'        => $m( $id, 'aw_address' ),
			'import_address' => $m( $id, 'aw_import_address' ),
			'awbi_address'   => $m( $id, 'aw_awbi_address' ),
			'phones'         => wp_list_pluck( aw_listing_phones( $id ), 'number' ),
			'email'          => $m( $id, 'aw_email' ),
			'email_alt'      => $m( $id, 'aw_email_alt' ),
			'website'        => $m( $id, 'aw_website' ),
			'website_alt'    => $m( $id, 'aw_website_alt' ),
			'facebook'       => $m( $id, 'aw_facebook' ),
			'instagram'      => $m( $id, 'aw_instagram' ),
			'awbi_code'      => $m( $id, 'aw_awbi_code' ),
			'darpan_id'      => $m( $id, 'aw_darpan_id' ),
			'source_name'    => $m( $id, 'aw_source_name' ),
			'types'          => wp_get_post_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) ),
			'has_excerpt'    => '' !== trim( (string) get_post_field( 'post_excerpt', $id ) ),
			'protected'      => aw_enrich_is_protected( $id ),
		);
	}
	$out = array( 'items' => $items, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / 1000 ) ), 'total' => $total );
	if ( 1 === $page && function_exists( 'aw_lgd' ) ) {
		$lgd          = aw_lgd();
		$out['places'] = array(
			'states'    => array_values( array_map( fn( $s ) => array( 'code' => $s->code, 'name' => $s->name ), $lgd['states'] ) ),
			'districts' => array_values( array_map( fn( $d ) => array( 'code' => $d->code, 'state' => $d->state, 'name' => $d->name ), $lgd['districts'] ) ),
			'aliases'   => $lgd['aliases'],
		);
	}
	return $out;
}
