<?php
/**
 * Taxonomy rename (owner, 18 Nov 2026: "rename aw to kits"): aw_campaign_link → kits_campaign_link and
 * aw_media_group → kits_media_group. Term IDs, slugs, names, term meta and every item's assignment stay as they are;
 * only the taxonomy name on each term_taxonomy row changes.
 *
 * - Data: the first web or cron load after the deploy renames the rows once (one UPDATE per taxonomy), after keeping
 *   the list of renamed term_taxonomy IDs in the option kits_taxonomy_rename_backup. The option kits_taxonomy_rename
 *   marks it done.
 * - Saved blocks: Query Loops saved with "taxQuery":{"aw_campaign_link":[…]} keep working; kits_taxonomy_current()
 *   maps the old name to the new one where the site reads taxQuery (systems.php, campaign-tools.php). Post content is
 *   not rewritten.
 * - WP-CLI: `wp kits taxonomy-rename status` (counts), `run [--dry-run]` (the same rename by hand) and `undo` (puts the
 *   backed-up rows back under the old names; only together with rolling the code back, since this code registers the
 *   new names).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Old taxonomy name => new name.
 *
 * @return array<string, string>
 */
function kits_taxonomy_renames() {
	return array(
		'aw_campaign_link' => 'kits_campaign_link',
		'aw_media_group'   => 'kits_media_group',
	);
}

/**
 * The name a taxonomy is registered under now (an old name gives its new one; any other name is returned as is).
 *
 * @param string $taxonomy Taxonomy name, old or new.
 * @return string
 */
function kits_taxonomy_current( $taxonomy ) {
	$map = kits_taxonomy_renames();
	return $map[ (string) $taxonomy ] ?? (string) $taxonomy;
}

/**
 * Count the term_taxonomy rows under each old and new name.
 *
 * @return array<string, int>
 */
function kits_taxonomy_rename_counts() {
	global $wpdb;
	$out = array();
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		foreach ( array( $old, $new ) as $name ) {
			$out[ $name ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	return $out;
}

/**
 * Rename the rows from the old names to the new ones. The renamed term_taxonomy IDs are added to the backup option
 * first, so `undo` can put exactly these rows back.
 *
 * @param bool $dry_run Count only, change nothing.
 * @return array<string, int> Rows renamed (or that would be) per old name.
 */
function kits_taxonomy_rename_run( $dry_run = false ) {
	global $wpdb;
	$done   = array();
	$terms  = array();
	$backup = (array) get_option( 'kits_taxonomy_rename_backup', array() );
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$rows         = (array) $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$done[ $old ] = count( $rows );
		if ( $dry_run || ! $rows ) {
			continue;
		}
		$ids            = array_map( 'intval', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
		$terms          = array_merge( $terms, array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) ) );
		$backup[ $old ] = array_values( array_unique( array_merge( array_map( 'intval', (array) ( $backup[ $old ] ?? array() ) ), $ids ) ) );
		update_option( 'kits_taxonomy_rename_backup', $backup, false ); // Saved before the change; never deleted here.
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $new ), array( 'taxonomy' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! $dry_run ) {
		kits_taxonomy_rename_flush( $terms );
		update_option( 'kits_taxonomy_rename', '1', true );
	}
	return $done;
}

/**
 * Put the backed-up rows back under their old names (only rows still under the new name are touched).
 *
 * @return array<string, int> Rows put back per old name.
 */
function kits_taxonomy_rename_undo() {
	global $wpdb;
	$done   = array();
	$terms  = array();
	$backup = (array) get_option( 'kits_taxonomy_rename_backup', array() );
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		$done[ $old ] = 0;
		$ids          = array_values( array_filter( array_map( 'intval', (array) ( $backup[ $old ] ?? array() ) ) ) );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in            = implode( ',', $chunk ); // Integers only (intval above).
			$terms         = array_merge( $terms, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_taxonomy_id IN ({$in})", $new ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$done[ $old ] += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s AND term_taxonomy_id IN ({$in})", $old, $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
	kits_taxonomy_rename_flush( $terms );
	update_option( 'kits_taxonomy_rename', 'undone', true ); // The first-load rename does not run again.
	return $done;
}

/**
 * Drop the cached terms so no request reads a term under its old taxonomy name.
 *
 * @param int[] $term_ids Term IDs that changed.
 */
function kits_taxonomy_rename_flush( array $term_ids ) {
	if ( $term_ids ) {
		clean_term_cache( array_values( array_unique( $term_ids ) ), '', false );
	}
	foreach ( kits_taxonomy_renames() as $old => $new ) {
		clean_taxonomy_cache( $old );
		clean_taxonomy_cache( $new );
	}
	wp_cache_set_terms_last_changed();
}

// The first web or cron load after the deploy renames the rows once, before the taxonomies are registered (priority 5).
add_action(
	'init',
	function () {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || false !== get_option( 'kits_taxonomy_rename', false ) ) {
			return; // Done already, or WP-CLI: there `status` and `run --dry-run` must see the data as it is.
		}
		if ( ! add_option( 'kits_taxonomy_rename_lock', time(), '', false ) ) { // Another request is running it.
			if ( time() - (int) get_option( 'kits_taxonomy_rename_lock', 0 ) < 300 ) {
				return;
			}
		}
		kits_taxonomy_rename_run();
		delete_option( 'kits_taxonomy_rename_lock' );
	},
	1
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'kits taxonomy-rename',
		/**
		 * Rename aw_campaign_link and aw_media_group to their kits_ names (term IDs and assignments unchanged).
		 *
		 * ## OPTIONS
		 *
		 * <action>
		 * : status, run or undo.
		 *
		 * [--dry-run]
		 * : With run: count the rows only.
		 *
		 * @param array $args  Positional arguments.
		 * @param array $assoc Flags.
		 */
		function ( $args, $assoc ) {
			$action = (string) ( $args[0] ?? 'status' );
			if ( 'run' === $action ) {
				$dry = ! empty( $assoc['dry-run'] );
				foreach ( kits_taxonomy_rename_run( $dry ) as $old => $n ) {
					WP_CLI::log( sprintf( '%s → %s: %d row(s)%s', $old, kits_taxonomy_current( $old ), $n, $dry ? ' (dry run)' : '' ) );
				}
			} elseif ( 'undo' === $action ) {
				foreach ( kits_taxonomy_rename_undo() as $old => $n ) {
					WP_CLI::log( sprintf( '%s ← %s: %d row(s) put back', $old, kits_taxonomy_current( $old ), $n ) );
				}
			} elseif ( 'status' !== $action ) {
				WP_CLI::error( 'Use status, run or undo.' );
			}
			foreach ( kits_taxonomy_rename_counts() as $name => $n ) {
				WP_CLI::log( sprintf( '%s: %d term(s)', $name, $n ) );
			}
			WP_CLI::log( 'Marker kits_taxonomy_rename: ' . (string) get_option( 'kits_taxonomy_rename', '(not run)' ) );
			WP_CLI::success( 'Done.' );
		}
	);
}
