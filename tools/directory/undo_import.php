<?php
/**
 * Undo one import run from its log (import_listings.php). Only when the owner asks.
 * Usage: wp eval-file undo_import.php <log.jsonl>
 *
 * Listings the run created go to Trash (restorable; nothing is deleted). On listings it extended, the types it added
 * are removed and the fields it filled are emptied again, unless someone changed them since.
 */
$lines = array_reverse( array_filter( array_map( 'trim', file( $args[0] ) ) ) );
$trash = $types = $fields = $kept = 0;
foreach ( $lines as $l ) {
	$e  = json_decode( $l, true );
	$id = (int) $e['post'];
	if ( 'post' === $e['field'] ) {
		if ( 'trash' !== get_post_status( $id ) && wp_trash_post( $id ) ) {
			$trash++;
		}
		continue;
	}
	if ( 'created' === $e['kind'] ) {
		continue; // goes with the listing
	}
	if ( 'types' === $e['field'] ) {
		wp_remove_object_terms( $id, (array) $e['new'], 'post_tag' );
		$types++;
	} elseif ( 'aw_data_sources' === $e['field'] ) {
		update_post_meta( $id, 'aw_data_sources', (array) $e['old'] );
		$fields++;
	} elseif ( 'aw_phones' === $e['field'] ) {
		$kept++; // phone lists are not taken back automatically (listed for a person to check)
		WP_CLI::log( "phone added to $id: " . $e['new'] );
	} elseif ( (string) get_post_meta( $id, $e['field'], true ) === (string) $e['new'] ) {
		delete_post_meta( $id, $e['field'] );
		$fields++;
	} else {
		$kept++; // changed since the import
	}
}
delete_transient( 'aw_seo_dir_data' );
echo "trashed $trash created listings, removed types on $types, emptied $fields fields, left $kept for a person to check\n";
