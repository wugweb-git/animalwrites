<?php
/**
 * Thumbnails never repeat (owner, 9 Oct 2026) — for content WE create. Ingested items (News, Library, research) show
 * the publisher's own image, linked (media.php); without a usable one, their own CC0 photo from Openverse (featured
 * image, used once site-wide, cc0-images.php); only failing that may they share their category photo (owner, 12 Oct 2026).
 * Featured images (_thumbnail_id) of every published item, CC0 photos included, are counted by aw_thumb_duplicates().
 *
 * Our own openly licensed category photos: each photo belongs to ONE item at a
 * time: the newest published item in that category that has no photo yet. Every other item shows its text placard.
 * Files are compared by content (md5), so the same picture uploaded twice still counts once. The assignment is stored
 * (option aw_thumb_map: post ID => attachment ID) and rebuilt when an item is published, changed or removed, and daily.
 * Library items with their own document page (aw_file_id / aw_doc_preview) keep it: it is unique to them.
 *
 * Check: GET /wp-json/aw/v1/thumb-dupes → { "duplicates": n } (the deploy gate fails when n > 0); Tools → Check site.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_THUMB_TYPES = array( 'aw_news', 'aw_legal', 'post' );

/** Content hash of an attachment's file (cached on the attachment). */
function aw_thumb_hash( $att ) {
	$att = (int) $att;
	$h   = (string) get_post_meta( $att, 'aw_md5', true );
	if ( '' === $h ) {
		$file = get_attached_file( $att );
		$h    = $file && is_readable( $file ) ? (string) md5_file( $file ) : 'id:' . $att;
		update_post_meta( $att, 'aw_md5', $h );
	}
	return $h;
}

/** Rebuild the map: newest items first; each takes the first category photo nobody holds yet. */
function aw_thumb_rebuild() {
	$ids  = get_posts( array( 'post_type' => AW_THUMB_TYPES, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$used = array(); // hash => post ID
	// Pictures already used elsewhere as featured images (press, listings, pages) are taken first.
	global $wpdb;
	foreach ( $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish'" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$used[ aw_thumb_hash( (int) $r->meta_value ) ] = (int) $r->post_id;
	}
	$map = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'aw_file_id', true ) || get_post_meta( $id, 'aw_doc_preview', true ) || get_post_thumbnail_id( $id ) ) {
			continue; // its own document page, or its own featured image (e.g. a CC0 photo, cc0-images.php)
		}
		if ( function_exists( 'aw_is_ingested' ) && aw_is_ingested( $id ) && function_exists( 'aw_publisher_image' ) && aw_publisher_image( $id ) ) {
			continue; // shows the publisher's image
		}
		$cats = wp_get_object_terms( $id, 'category' );
		foreach ( is_wp_error( $cats ) ? array() : $cats as $t ) {
			$att = function_exists( 'aw_category_image_id' ) ? (int) aw_category_image_id( $t ) : 0;
			if ( $att && ! isset( $used[ aw_thumb_hash( $att ) ] ) ) {
				$used[ aw_thumb_hash( $att ) ] = $id;
				$map[ $id ]                    = $att;
				break;
			}
		}
	}
	update_option( 'aw_thumb_map', $map, false );
	return $map;
}

/** The stored photo for an item (0 = placard). */
function aw_thumb_for( $id ) {
	$map = get_option( 'aw_thumb_map' );
	if ( ! is_array( $map ) ) {
		$map = aw_thumb_rebuild();
	}
	return (int) ( $map[ (int) $id ] ?? 0 );
}

/** Rebuild soon after any change to a carded item (one run per burst of saves). */
function aw_thumb_schedule() {
	if ( ! wp_next_scheduled( 'aw_thumb_rebuild' ) ) {
		wp_schedule_single_event( time() + 60, 'aw_thumb_rebuild' );
	}
}
add_action( 'aw_thumb_rebuild', 'aw_thumb_rebuild' );
add_action( 'aw_daily', 'aw_thumb_rebuild' );
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( in_array( $post->post_type, AW_THUMB_TYPES, true ) && ( 'publish' === $new || 'publish' === $old ) ) {
			aw_thumb_schedule();
		}
	},
	10,
	3
);
add_action( 'set_object_terms', fn( $id, $terms, $tt, $tax ) => 'category' === $tax && in_array( get_post_type( $id ), AW_THUMB_TYPES, true ) ? aw_thumb_schedule() : null, 10, 4 );

/** Number of pictures used by more than one published item (cards and featured images). */
function aw_thumb_duplicates() {
	$seen = array();
	foreach ( (array) get_option( 'aw_thumb_map', array() ) as $id => $att ) {
		if ( 'publish' === get_post_status( $id ) ) {
			$seen[ aw_thumb_hash( $att ) ][] = (int) $id;
		}
	}
	global $wpdb;
	foreach ( $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish'" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seen[ aw_thumb_hash( (int) $r->meta_value ) ][] = (int) $r->post_id;
	}
	return array_filter( array_map( 'array_unique', $seen ), fn( $ids ) => count( $ids ) > 1 );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/thumb-dupes',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // the deploy gate reads it without logging in (tools/ci/thumb_check.py)
				'callback'            => function () {
					// The full scan takes about a second, so the public count is kept 5 minutes (plugin audit 6).
					$n = get_transient( 'aw_thumb_dupes_n' );
					if ( false === $n ) {
						$n = count( aw_thumb_duplicates() );
						set_transient( 'aw_thumb_dupes_n', $n, 5 * MINUTE_IN_SECONDS );
					}
					return new WP_REST_Response( array( 'duplicates' => (int) $n ), 200 );
				},
			)
		);
	}
);

add_action(
	'aw_site_check_after',
	function () {
		$d = aw_thumb_duplicates();
		echo '<h2>Thumbnails</h2><p>' . ( $d ? esc_html( count( $d ) . ' picture(s) used by more than one item: ' . implode( '; ', array_map( fn( $ids ) => '#' . implode( ', #', $ids ), $d ) ) ) : 'No picture is used twice.' ) . '</p>';
	}
);
