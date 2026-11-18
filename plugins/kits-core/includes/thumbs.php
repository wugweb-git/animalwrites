<?php
/**
 * Thumbnails never repeat (owner, 9 Oct 2026) — for content WE create. Ingested items (News, Library, research) show
 * the publisher's own image, linked (media.php); without a usable one, their own CC0 photo from Openverse (featured
 * image, used once site-wide, cc0-images.php); only failing that may they share their category photo (owner, 12 Oct 2026).
 * Featured images (_thumbnail_id) of every published item, CC0 photos included, are counted by kits_thumb_duplicates().
 *
 * Our own openly licensed category photos: each photo belongs to ONE item at a
 * time: the newest published item in that category that has no photo yet. Every other item shows its text placard.
 * Files are compared by content (md5), so the same picture uploaded twice still counts once. The assignment is stored
 * (option aw_thumb_map: post ID => attachment ID) and rebuilt when an item is published, changed or removed, and daily.
 * Library items with their own document page (aw_file_id / aw_doc_preview) keep it: it is unique to them.
 *
 * Check: GET /wp-json/aw/v1/thumb-dupes → { "duplicates": n } (the deploy gate fails when n > 0); Kits → Home → Thumbnails.
 * Moved from Animal Writes Core on 18 Nov 2026: option aw_thumb_map, meta aw_md5, transient aw_thumb_dupes_n, the
 * aw_thumb_rebuild event and the REST route are unchanged.
 *
 * @package kits-core
 *
 * SITE MODULE (Animal Writes only): not part of the generic Kits Core product. It stays in this plugin for now because card-images.php, tools/media-reuse.php and category-images.php call it and it hooks the Home sections at plugin load;
 * moving it to the theme's site layer (assets/site/) would break that load order. Listed in docs/awcore-retirement.md §7.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_THUMB_TYPES = array( 'aw_news', 'aw_legal', 'post' );

/** Content hash of an attachment's file (cached on the attachment). */
function kits_thumb_hash( $att ) {
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
function kits_thumb_rebuild() {
	$ids  = get_posts( array( 'post_type' => KITS_THUMB_TYPES, 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$used = array(); // hash => post ID
	// Pictures already used elsewhere as featured images (press, listings, pages) are taken first.
	global $wpdb;
	foreach ( $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish'" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$used[ kits_thumb_hash( (int) $r->meta_value ) ] = (int) $r->post_id;
	}
	$map = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'aw_file_id', true ) || get_post_meta( $id, 'aw_doc_preview', true ) || get_post_meta( $id, 'aw_cover_image_id', true ) || get_post_thumbnail_id( $id ) ) {
			continue; // its own document page, a cover the owner chose (meta.php), or its own featured image (e.g. a CC0 photo, cc0-images.php)
		}
		if ( function_exists( 'kits_is_ingested' ) && kits_is_ingested( $id ) && function_exists( 'sourceweb_publisher_image' ) && sourceweb_publisher_image( $id ) ) {
			continue; // shows the publisher's image
		}
		$cats = wp_get_object_terms( $id, 'category' );
		foreach ( is_wp_error( $cats ) ? array() : $cats as $t ) {
			$att = function_exists( 'kits_category_image_id' ) ? (int) kits_category_image_id( $t ) : 0;
			if ( $att && ! isset( $used[ kits_thumb_hash( $att ) ] ) ) {
				$used[ kits_thumb_hash( $att ) ] = $id;
				$map[ $id ]                    = $att;
				break;
			}
		}
	}
	update_option( 'aw_thumb_map', $map, false );
	return $map;
}

/** The stored photo for an item (0 = placard). */
function kits_thumb_for( $id ) {
	$map = get_option( 'aw_thumb_map' );
	if ( ! is_array( $map ) ) {
		$map = kits_thumb_rebuild();
	}
	return (int) ( $map[ (int) $id ] ?? 0 );
}

/** Rebuild soon after any change to a carded item (one run per burst of saves). */
function kits_thumb_schedule() {
	if ( ! wp_next_scheduled( 'aw_thumb_rebuild' ) ) {
		wp_schedule_single_event( time() + 60, 'aw_thumb_rebuild' );
	}
}
add_action( 'aw_thumb_rebuild', 'kits_thumb_rebuild' );
add_action( 'wp_scheduled_delete', 'kits_thumb_rebuild' ); // daily, with WordPress's own daily clean-up (was the site's aw_daily event)
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( in_array( $post->post_type, KITS_THUMB_TYPES, true ) && ( 'publish' === $new || 'publish' === $old ) ) {
			kits_thumb_schedule();
		}
	},
	10,
	3
);
add_action( 'set_object_terms', fn( $id, $terms, $tt, $tax ) => 'category' === $tax && in_array( get_post_type( $id ), KITS_THUMB_TYPES, true ) ? kits_thumb_schedule() : null, 10, 4 );

/**
 * A hand-picked cover (meta.php: aw_cover_image_id) means the item has its own picture, so it leaves the shared
 * category-photo pool at once: rebuild the map and drop the cached count now, without waiting for WP-Cron (which a
 * quiet site may not run for a while). Fires on add, change and clear of the meta.
 *
 * @param int|array $mid     Meta ID(s) (unused).
 * @param int       $post_id Post ID (unused; the rebuild scans every item).
 * @param string    $key     Meta key.
 * @return void
 */
function kits_thumb_cover_changed( $mid, $post_id, $key ) {
	if ( 'aw_cover_image_id' !== $key ) {
		return;
	}
	kits_thumb_rebuild();
	delete_transient( 'aw_thumb_dupes_n' );
}
add_action( 'added_post_meta', 'kits_thumb_cover_changed', 10, 3 );
add_action( 'updated_post_meta', 'kits_thumb_cover_changed', 10, 3 );
add_action( 'deleted_post_meta', 'kits_thumb_cover_changed', 10, 3 );

/**
 * Number of pictures used by more than one published item (cards and featured images). An item with a hand-picked
 * cover (meta.php: aw_cover_image_id) shows that cover, not its map photo or featured image, so its fallbacks are
 * left out of the count: two document items whose page-1 scans happen to match do not clash once one carries a cover.
 */
function kits_thumb_duplicates() {
	$seen = array();
	foreach ( (array) get_option( 'aw_thumb_map', array() ) as $id => $att ) {
		if ( 'publish' === get_post_status( $id ) && ! get_post_meta( (int) $id, 'aw_cover_image_id', true ) ) {
			$seen[ kits_thumb_hash( $att ) ][] = (int) $id;
		}
	}
	global $wpdb;
	foreach ( $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} c WHERE c.post_id = p.ID AND c.meta_key = 'aw_cover_image_id' AND c.meta_value <> '')" ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seen[ kits_thumb_hash( (int) $r->meta_value ) ][] = (int) $r->post_id;
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
						$n = count( kits_thumb_duplicates() );
						set_transient( 'aw_thumb_dupes_n', $n, 5 * MINUTE_IN_SECONDS );
					}
					return new WP_REST_Response( array( 'duplicates' => (int) $n ), 200 );
				},
			)
		);
	}
);

/** Kits → Home → Thumbnails: pictures used by more than one item (moved off Animal Writes Core's Check site, 18 Nov 2026). */
function kits_thumbs_site_check() {
	$d = kits_thumb_duplicates();
	// A Kits → Home card: its title is the card's, so no heading here.
	echo '<p>' . ( $d ? esc_html( count( $d ) . ' picture(s) used by more than one item: ' . implode( '; ', array_map( fn( $ids ) => '#' . implode( ', #', $ids ), $d ) ) ) : 'No picture is used twice.' ) . '</p>';
}
add_filter(
	'kits_home_sections',
	function ( $sections ) {
		$sections               = (array) $sections;
		$sections['thumbnails'] = array(
			'title'  => 'Thumbnails',
			'render' => 'kits_thumbs_site_check',
			'cap'    => 'manage_options',
			'order'  => 71,
		);
		return $sections;
	}
);
