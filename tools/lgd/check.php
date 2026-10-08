<?php
/**
 * After replacing data/lgd.json: list items whose state or district code is no longer in the file (districts get split
 * or renamed), with a suggested new district by name. Read-only: nothing is changed.
 *   wp eval-file tools/lgd/check.php
 */
global $wpdb;
$rows = $wpdb->get_results( "SELECT pm.post_id id, pm.meta_key k, pm.meta_value v, p.post_title t, p.post_type pt FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status <> 'trash' WHERE pm.meta_key IN ('aw_state_code','aw_district_code')" );
$old  = array();
$bak  = aw_private_dir() . '/locations-backup-2026-10-08.json';
foreach ( (array) ( json_decode( (string) @file_get_contents( $bak ), true )['terms'] ?? array() ) as $t ) {
	$old[ (int) $t['term_id'] ] = $t['name'];
}
$n = 0;
foreach ( $rows as $r ) {
	$ok = 'aw_state_code' === $r->k ? aw_place_state( (int) $r->v ) : aw_place_district( (int) $r->v );
	if ( $ok ) {
		continue;
	}
	$n++;
	$sc   = aw_item_codes( (int) $r->id )[0];
	$st   = $sc ? aw_place_state( $sc[0] ) : null;
	echo $r->pt . ' #' . $r->id . ' ' . $r->t . ': ' . $r->k . ' ' . $r->v . " is not in LGD " . aw_lgd()['version'] . ( $st ? ' (state ' . $st->name . ')' : '' ) . "\n";
}
foreach ( (array) get_option( 'aw_places_unmatched', array() ) as $u ) {
	echo 'Not moved from the old location list: item #' . $u[0] . ' (' . $u[1] . ")\n";
}
global $wpdb;
$state_only = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'aw_state_code') AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'aw_district_code')" );
$none       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = 'aw_state_code')" );
echo "Codes not in the file: $n. Listings with a state but no district: $state_only. Listings with no place: $none.\n";
