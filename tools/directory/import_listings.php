<?php
/**
 * Directory import from public lists. Idempotent; never deletes. Usage: wp eval-file import_listings.php <json> <offset> <limit>
 *
 * Row kinds:
 * - new listing: key, name, types[], state, district, area, phone, address_private, content, source_name, source_url,
 *   source_date, abc_valid (optional). Matched by aw_import_key; created published and unverified.
 * - update of an existing listing: post_id, types[] (added, never removed), abc_centres[] (kept private).
 */
list( $file, $offset, $limit ) = array( $args[0], (int) $args[1], (int) $args[2] );
$rows = array_slice( json_decode( file_get_contents( $file ), true ), $offset, $limit );
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$made = $updated = $attached = 0;
foreach ( $rows as $r ) {
	if ( ! empty( $r['post_id'] ) ) {
		$id = (int) $r['post_id'];
		$id = (int) get_post_meta( $id, 'aw_merged_into', true ) ? (int) get_post_meta( $id, 'aw_merged_into', true ) : $id; // merged: extend the listing it went into
		if ( 'aw_listing' !== get_post_type( $id ) ) {
			continue;
		}
		wp_set_object_terms( $id, $r['types'], 'aw_listing_type', true );
		if ( ! empty( $r['abc_centres'] ) ) {
			update_post_meta( $id, 'aw_abc_centres', wp_json_encode( $r['abc_centres'] ) ); // private: centre addresses
			update_post_meta( $id, 'aw_abc_valid', max( array_column( $r['abc_centres'], 'valid' ) ) );
		}
		$attached++;
		continue;
	}
	$have = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => 'aw_import_key', 'meta_value' => $r['key'], 'fields' => 'ids', 'posts_per_page' => 1 ) );
	$post = array(
		'post_type'    => 'aw_listing',
		'post_title'   => $r['name'],
		'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $r['content'] ) . '</p><!-- /wp:paragraph -->',
	);
	if ( $have && get_post_meta( $have[0], 'aw_merged_into', true ) ) {
		continue; // merged into another listing (a draft with a 301): never updated or made again
	}
	if ( $have ) {
		$id         = $have[0];
		$post['ID'] = $id;
		wp_update_post( $post ); // keeps its status
		$updated++;
	} else {
		$post['post_status'] = 'publish';
		$id                  = wp_insert_post( $post );
		$made++;
	}
	if ( ! $id || is_wp_error( $id ) ) {
		continue;
	}
	// Place: LGD codes (rows carry state and district codes); unknown codes are refused, never added.
	if ( ! aw_set_item_places( $id, array( array( (int) $r['state'], (int) $r['district'] ) ) ) ) {
		WP_CLI::warning( 'Place not in LGD for ' . $r['name'] . ': ' . $r['state'] . '/' . $r['district'] );
	}
	wp_set_object_terms( $id, $r['types'], 'aw_listing_type', true );
	update_post_meta( $id, 'aw_import_key', $r['key'] );
	update_post_meta( $id, 'aw_area', $r['area'] );
	foreach ( array( 'source_name', 'source_url', 'source_date' ) as $k ) {
		update_post_meta( $id, 'aw_' . $k, $r[ $k ] );
	}
	if ( ! empty( $r['phone'] ) ) {
		aw_listing_put_phone( $id, $r['phone'] ); // into the Phone numbers list
	}
	if ( ! empty( $r['email'] ) && is_email( $r['email'] ) ) {
		update_post_meta( $id, 'aw_email', sanitize_email( $r['email'] ) ); // official office email only
	}
	if ( ! empty( $r['address_private'] ) ) {
		update_post_meta( $id, 'aw_import_address', $r['address_private'] ); // for checking claims; not shown publicly
	}
	if ( ! empty( $r['abc_valid'] ) ) {
		update_post_meta( $id, 'aw_abc_valid', $r['abc_valid'] );
	}
	if ( ! metadata_exists( 'post', $id, 'aw_verified' ) ) {
		update_post_meta( $id, 'aw_verified', false );
	}
	if ( ! metadata_exists( 'post', $id, 'aw_featured' ) ) {
		update_post_meta( $id, 'aw_featured', 0 );
	}
}
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
delete_transient( 'aw_sitemap_dir_pairs' );
echo "offset $offset: created $made, updated $updated, existing listings extended $attached\n";
