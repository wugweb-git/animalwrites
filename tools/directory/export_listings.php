<?php
/**
 * Export existing listings for the import builders' de-dupe (tools/directory/match.py). Read-only.
 * Usage: wp eval-file export_listings.php > listings.json
 */
global $wpdb;
$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'aw_listing' AND post_status IN ('publish','draft','pending','private')" );
$out = array();
foreach ( $ids as $id ) {
	$id     = (int) $id;
	$places = function_exists( 'aw_item_places' ) ? aw_item_places( $id ) : array();
	$out[]  = array(
		'id'           => $id,
		'status'       => get_post_status( $id ),
		'title'        => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
		'types'        => wp_get_post_terms( $id, 'post_tag', array( 'fields' => 'slugs' ) ),
		'awbi_code'    => (string) get_post_meta( $id, 'aw_awbi_code', true ),
		'awbi_valid'   => (string) get_post_meta( $id, 'aw_awbi_valid', true ),
		'abc_valid'    => (string) get_post_meta( $id, 'aw_abc_valid', true ),
		'phones'       => function_exists( 'aw_listing_phones' ) ? wp_list_pluck( aw_listing_phones( $id ), 'number' ) : array(),
		'darpan_id'    => (string) get_post_meta( $id, 'aw_darpan_id', true ),
		'registration' => (string) get_post_meta( $id, 'aw_registration', true ),
		'import_key'   => (string) get_post_meta( $id, 'aw_import_key', true ),
		'sources'      => array_values( array_filter( (array) get_post_meta( $id, 'aw_data_sources', true ) ) ),
		'email'        => (string) get_post_meta( $id, 'aw_email', true ),
		'website'      => (string) get_post_meta( $id, 'aw_website', true ),
		'states'       => array_values( array_unique( array_map( 'intval', (array) get_post_meta( $id, 'aw_state_code', false ) ) ) ),
		'districts'    => array_values( array_unique( array_map( 'intval', (array) get_post_meta( $id, 'aw_district_code', false ) ) ) ),
		'merged_into'  => (int) get_post_meta( $id, 'aw_merged_into', true ),
		'protected'    => function_exists( 'aw_enrich_is_protected' ) ? (bool) aw_enrich_is_protected( $id ) : (bool) get_post_meta( $id, 'aw_verified', true ),
	);
}
echo wp_json_encode( $out );
