<?php
/**
 * Technical saves keep the "modified" date (1 Nov 2026). The date a page was last modified feeds its dateModified in
 * the schema, the sitemaps and the "Updated" line, so it moves only when a reader would see a change. A save that only
 * changes letter case, moves data between fields or repairs a value keeps the date the page had:
 *
 * - in PHP: aw_keep_modified( fn() => wp_update_post( … ) ) (or set $GLOBALS['aw_keep_modified'] around the save);
 * - over the REST API (a script in the logged-in browser, WP-CLI's REST calls): add ?aw_keep_modified=1 to the request, or
 *   the header X-AW-Keep-Modified: 1. The user still needs the right to edit the item; only the date stays.
 *
 * Only updates of an existing item are affected; a new item gets its date as usual. Revisions are still saved.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs $save with the modified date kept, and returns what it returns.
 *
 * @param callable $save The save (wp_update_post, wp_insert_post with an ID, …).
 * @return mixed
 */
function aw_keep_modified( callable $save ) {
	$was                         = ! empty( $GLOBALS['aw_keep_modified'] );
	$GLOBALS['aw_keep_modified'] = true;
	try {
		return $save();
	} finally {
		$GLOBALS['aw_keep_modified'] = $was;
	}
}

/** Whether the save in progress asked to keep the modified date. */
function aw_keep_modified_requested() {
	if ( ! empty( $GLOBALS['aw_keep_modified'] ) ) {
		return true;
	}
	if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$flag = isset( $_GET['aw_keep_modified'] ) ? sanitize_text_field( wp_unslash( $_GET['aw_keep_modified'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- the REST API checks the user's rights
	$head = isset( $_SERVER['HTTP_X_AW_KEEP_MODIFIED'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_AW_KEEP_MODIFIED'] ) ) : '';
	return '1' === $flag || '1' === $head;
}

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		$id = (int) ( $postarr['ID'] ?? 0 );
		if ( ! $id || 'revision' === ( $data['post_type'] ?? '' ) || ! aw_keep_modified_requested() ) {
			return $data;
		}
		$old = get_post( $id );
		if ( $old && '0000-00-00 00:00:00' !== $old->post_modified ) {
			$data['post_modified']     = $old->post_modified;
			$data['post_modified_gmt'] = $old->post_modified_gmt;
		}
		return $data;
	},
	99,
	2
);
