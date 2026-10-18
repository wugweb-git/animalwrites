<?php
/**
 * Merge duplicate listings (owner, 18 Oct 2026): each "from" listing goes into the "into" listing (the newer one, with
 * the organisation's website). The kept listing's own values stand; the other's details fill only its empty fields
 * (AWBI code and dates, ABC centres...), types are added, links to the other listing (Featured in, organiser) are moved,
 * and the other listing becomes a draft with aw_merged_into and a 301 to the kept one. Nothing is deleted.
 * Usage: wp eval-file merge_listings.php <from:into,from:into,...> [<log.jsonl>]
 */
$pairs = array_map( fn( $p ) => array_map( 'intval', explode( ':', $p ) ), explode( ',', $args[0] ) );
$log   = fopen( $args[1] ?? 'merge.log.jsonl', 'a' );
$note  = fn( $id, $field, $old, $new ) => fwrite( $log, wp_json_encode( array( 'post' => $id, 'field' => $field, 'old' => $old, 'new' => $new, 'kind' => 'merged' ) ) . "\n" );
$skip  = array( 'aw_featured', 'aw_contact_score', 'aw_review', 'aw_import_batch', 'aw_link_checked', 'aw_merged_into', 'aw_state_code', 'aw_district_code' );
global $wpdb;
foreach ( $pairs as list( $from, $into ) ) {
	if ( 'aw_listing' !== get_post_type( $from ) || 'aw_listing' !== get_post_type( $into ) || $from === $into || get_post_meta( $from, 'aw_merged_into', true ) ) {
		WP_CLI::warning( "skipped $from -> $into" );
		continue;
	}
	$old_url = get_permalink( $from );
	foreach ( get_post_meta( $from ) as $key => $vals ) {
		if ( '_' === $key[0] || 0 === strpos( $key, 'rank_math' ) || in_array( $key, $skip, true ) || 0 === strpos( $key, 'aw_phones' ) || 0 === strpos( $key, 'aw_hours' ) ) {
			continue;
		}
		$v = maybe_unserialize( $vals[0] );
		if ( '' === $v || array() === $v || '' !== trim( (string) ( is_array( get_post_meta( $into, $key, true ) ) ? 'x' : get_post_meta( $into, $key, true ) ) ) ) {
			continue; // the kept listing's value stands
		}
		update_post_meta( $into, $key, $v );
		$note( $into, $key, '', $v );
	}
	if ( ! aw_listing_phones( $into ) && aw_listing_phones( $from ) ) {
		aw_set_listing_list( $into, 'aw_phones', aw_listing_phones( $from ) );
		$note( $into, 'aw_phones', array(), aw_listing_phones( $from ) );
	}
	$have = wp_get_post_terms( $into, 'post_tag', array( 'fields' => 'slugs' ) );
	$add  = array_values( array_diff( wp_get_post_terms( $from, 'post_tag', array( 'fields' => 'slugs' ) ), $have ) );
	if ( $add ) {
		wp_add_object_terms( $into, $add, 'post_tag' );
		$note( $into, 'types', array(), $add );
	}
	foreach ( array( 'aw_rel_org', 'aw_rel_org_auto', 'aw_rel_org_not', 'aw_organiser' ) as $rel ) { // links from other items
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $rel, (string) $from ) ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $mid ) {
			$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_id = %d", $mid ) ); // phpcs:ignore WordPress.DB
			if ( in_array( (string) $into, (array) get_post_meta( $pid, $rel, false ), true ) ) {
				delete_metadata_by_mid( 'post', $mid ); // already linked to the kept one
			} else {
				update_metadata_by_mid( 'post', $mid, (string) $into );
			}
			$note( $pid, $rel, $from, $into );
		}
	}
	wp_update_post( array( 'ID' => $from, 'post_status' => 'draft' ) );
	update_post_meta( $from, 'aw_merged_into', $into );
	$note( $from, 'post_status', 'publish', 'draft' );
	if ( function_exists( 'aw_redirect_add' ) ) {
		aw_redirect_add( $old_url, $into );
		$note( $from, 'redirect', $old_url, get_permalink( $into ) );
	}
	clean_post_cache( $into );
	WP_CLI::log( "merged $from into $into ($old_url -> " . get_permalink( $into ) . ')' );
}
delete_transient( 'aw_seo_dir_data' );
delete_transient( 'aw_featured_names' );
