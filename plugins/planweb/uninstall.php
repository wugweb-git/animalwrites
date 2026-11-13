<?php
/**
 * Uninstall PlanWeb. By default it deletes nothing: settings and every calendar entry stay, so reinstalling finds
 * everything as it was.
 *
 * Only when Kits → Modules → PlanWeb → "Delete this plugin's data when it is deleted" is ticked
 * (planweb_delete_data_on_uninstall is true) does it delete PlanWeb's own data, exactly this:
 *
 * Options: planweb_on, planweb_setup, planweb_import_done, planweb_import_lock, planweb_import_result,
 *   planweb_import_tried, planweb_delete_data_on_uninstall.
 * Transients (they expire anyway): planweb_counts and those starting planweb_draft_.
 * Scheduled events (all, whatever their arguments): planweb_counts_build.
 * Tables: none of its own.
 *
 * Never deleted, even when ticked:
 * - The calendar entries (posts), their list values (terms), users, attachments and any post meta.
 * - The capabilities PlanWeb gave to roles (they are part of the users' roles; switching PlanWeb off removes them).
 * - Animal Writes Core's Kits → Dates list (aw_team_dates and aw_team_dates_links_note_1111), which PlanWeb only
 *   reads when it imports.
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/planweb-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored, then every row of
 * every table below (transients are caches and are not kept). If the backup cannot be written, nothing is deleted.
 *
 * Runs for the current site only.
 *
 * @package planweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( true !== (bool) get_option( 'planweb_delete_data_on_uninstall', false ) ) {
	return; // The default: keep everything.
}

$planweb_options = array( 'planweb_on', 'planweb_setup', 'planweb_import_done', 'planweb_import_lock', 'planweb_import_result', 'planweb_import_tried', 'planweb_feed_key', 'planweb_migrate_1114', 'planweb_migrate_1114_lock' );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! planweb_uninstall_backup( 'planweb', __DIR__ . '/planweb.php', array_merge( $planweb_options, array( 'planweb_delete_data_on_uninstall' ) ), array(), array() ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

foreach ( $planweb_options as $planweb_option ) {
	delete_option( $planweb_option );
}

delete_transient( 'planweb_counts' );
global $wpdb;
foreach ( array( '_transient_planweb_draft_', '_transient_timeout_planweb_draft_' ) as $planweb_start ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $planweb_start ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall: transients by prefix, once.
}

wp_unschedule_hook( 'planweb_counts_build' );

delete_option( 'planweb_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
