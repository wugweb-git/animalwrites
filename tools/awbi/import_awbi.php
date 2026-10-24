<?php
/**
 * One-off: AWBI recognised organisations -> aw_listing drafts. Idempotent by AWBI code + name.
 * Usage: wp eval-file import_awbi.php <json> <offset> <limit>
 */
list( $file, $offset, $limit ) = array( $args[0], (int) $args[1], (int) $args[2] );
$rows = array_slice( json_decode( file_get_contents( $file ), true ), $offset, $limit );
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$made = $updated = 0;
foreach ( $rows as $r ) {
	$key  = $r['code'] . '|' . $r['name'];
	$have = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => 'aw_awbi_key', 'meta_value' => $key, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	$post = array(
		'post_type'    => 'aw_listing',
		'post_title'   => $r['name'],
		'post_content' => '<!-- wp:paragraph --><p>Recognised by the Animal Welfare Board of India (AWBI code ' . esc_html( $r['code'] ) . '). Source: AWBI list of recognised animal welfare organisations as on 31 May 2026.</p><!-- /wp:paragraph -->',
	);
	if ( ! $have && get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( array( 'key' => 'aw_awbi_code', 'value' => $r['code'] ), array( 'key' => 'aw_merged_into', 'compare' => 'EXISTS' ) ) ) ) ) {
		continue; // this AWBI code belonged to a listing merged into another one
	}
	if ( $have && get_post_meta( $have[0], 'aw_merged_into', true ) ) {
		continue; // merged into another listing (a draft with a 301): never updated or made again
	}
	if ( $have ) {
		$id = $have[0];
		$post['ID'] = $id;
		wp_update_post( $post ); // Keeps its current status.
		$updated++;
	} else {
		$post['post_status'] = 'draft';
		$id = wp_insert_post( $post );
		$made++;
	}
	if ( ! $id || is_wp_error( $id ) ) {
		continue;
	}
	// Place: LGD codes (rows carry state and district codes); unknown codes are refused, never added.
	if ( ! aw_set_item_places( $id, array( array( (int) $r['state'], (int) $r['district'] ) ) ) ) {
		WP_CLI::warning( 'Place not in LGD for ' . $r['name'] . ': ' . $r['state'] . '/' . $r['district'] );
	}
	wp_set_object_terms( $id, $r['types'], 'post_tag', true ); // types are Directory tags; append (services stay)
	update_post_meta( $id, 'aw_awbi_key', $key );
	update_post_meta( $id, 'aw_awbi_code', $r['code'] );
	update_post_meta( $id, 'aw_awbi_address', $r['address'] ); // Kept for checking claims; not shown publicly.
	update_post_meta( $id, 'aw_awbi_valid', $r['valid'] );
	update_post_meta( $id, 'aw_area', $r['area'] );
	if ( ! metadata_exists( 'post', $id, 'aw_verified' ) ) {
		update_post_meta( $id, 'aw_verified', false );
	}
}
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
echo "offset $offset: created $made, updated $updated\n";
