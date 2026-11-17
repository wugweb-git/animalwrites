<?php
/**
 * One-time, idempotent migration of InsightWeb's live data from the legacy aw_si_ names to insightweb_ (17 Nov 2026).
 *
 * The code now uses insightweb_ names; this moves the stored data to match. Runs on 'init' before the installers,
 * guarded by the insightweb_migrated flag so it does nothing once done. Every step is guarded and re-runnable, writes
 * no new data and drops nothing: tables are RENAMEd only when the old exists and the new does not, options are copied
 * (keeping their autoload) then the old deleted, and the old cron events are cleared (cron.php reschedules the new
 * insightweb_ hooks). Kits Core's two keyword tables + aw_kw_db migrate separately in insightweb_kw_migrate().
 *
 * The OWNER deploys this (after a DB backup to file); it is never run here.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function insightweb_migrate_names(): void {
	if ( get_option( 'insightweb_migrated' ) ) {
		return;
	}
	global $wpdb;

	// 1) InsightWeb's own tables: {prefix}aw_si_<n> -> {prefix}insightweb_<n>.
	foreach ( array( 'kw_pages', 'obs', 'rising', 'links', 'visits' ) as $n ) {
		$old = $wpdb->prefix . 'aw_si_' . $n;
		$new = $wpdb->prefix . 'insightweb_' . $n;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) === $old // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) ) !== $new ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- names built from $wpdb->prefix and a fixed list, no user input
		}
	}

	// 2) Options: every aw_si_* -> insightweb_* (keep the stored value and its autoload flag).
	$rows = $wpdb->get_results( "SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name LIKE 'aw\\_si\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed LIKE pattern, no user input
	foreach ( (array) $rows as $row ) {
		$old = $row->option_name;
		$new = 'insightweb_' . substr( $old, 6 ); // strip the "aw_si_" prefix
		if ( add_option( $new, get_option( $old ), '', $row->autoload ) ) {
			delete_option( $old );
		}
	}

	// 3) Cron: clear the legacy aw_si_ events; cron.php schedules the new insightweb_ hooks going forward.
	$cron = _get_cron_array();
	if ( is_array( $cron ) ) {
		$hooks = array();
		foreach ( $cron as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( is_string( $hook ) && 0 === strpos( $hook, 'aw_si_' ) ) {
					$hooks[ $hook ] = 1;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	update_option( 'insightweb_migrated', 1, true );
}
add_action( 'init', 'insightweb_migrate_names', 1 );
