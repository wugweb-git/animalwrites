<?php
/**
 * Uninstall SourceWeb: remove the plugin's own options, its queued review items, and its capability. It never
 * touches another plugin's data, nor the drafts it created in your own post types (those are now yours).
 *
 * @package sourceweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'sourceweb_post_types', 'sourceweb_keywords', 'sourceweb_sources', 'sourceweb_feeds', 'sourceweb_services', 'sourceweb_summary', 'sourceweb_stats', 'sourceweb_feed_runs' ) as $sourceweb_option ) {
	delete_option( $sourceweb_option );
}

// Delete the plugin's own review-queue items (the sourceweb_item post type), in bounded batches.
do {
	$sourceweb_items = get_posts(
		array(
			'post_type'        => 'sourceweb_item',
			'post_status'      => 'any',
			'posts_per_page'   => 100,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	foreach ( $sourceweb_items as $sourceweb_item_id ) {
		wp_delete_post( $sourceweb_item_id, true );
	}
} while ( ! empty( $sourceweb_items ) );

$sourceweb_admin_role = get_role( 'administrator' );
if ( $sourceweb_admin_role && $sourceweb_admin_role->has_cap( 'manage_sourceweb' ) ) {
	$sourceweb_admin_role->remove_cap( 'manage_sourceweb' );
}
