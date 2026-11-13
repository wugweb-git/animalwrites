<?php
/**
 * Uninstall MailWeb. By default it deletes nothing: settings, profiles, the queue table and saved secrets stay, so
 * reinstalling finds everything as it was. Nothing in the mailboxes is ever touched.
 *
 * Only when "Delete this plugin's data when it is deleted" is ticked (MailWeb → Settings → Sending, option
 * mailweb_delete_data = 1) it deletes MailWeb's own data on this site, and exactly this:
 *
 * Custom tables:
 *   {prefix}mailweb_queue (emails waiting to be sent, held or failed)
 * Options:
 *   mailweb_cap, mailweb_mailboxes, mailweb_default_from, mailweb_aliases, mailweb_profiles, mailweb_crm,
 *   mailweb_smtp, mailweb_api_token, mailweb_secret_<mailbox> (one per mailbox), mailweb_box_addresses,
 *   mailweb_box_ids, mailweb_stats, mailweb_send_log, mailweb_dashboard_log, mailweb_last, mailweb_last_error,
 *   mailweb_crm_done, mailweb_queue_db, mailweb_pause, mailweb_overview_last_<mailbox>, mailweb_delete_data
 * Transients (and their timeouts):
 *   mailweb_dashboard, mailweb_box_ids_wait, mailweb_flush_lock, mailweb_pause_seen, mailweb_smtp_error,
 *   mailweb_folders_<mailbox>, mailweb_overview_<mailbox>, mailweb_overview_down_<mailbox>
 * Scheduled events:
 *   mailweb_flush, mailweb_crm_sync, mailweb_dashboard_build — every event of these hooks, whatever its arguments
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/mailweb-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored, then every row of
 * every table below (transients are caches and are not kept). If the backup cannot be written, nothing is deleted.
 *
 * Never deleted: anything in the mailboxes (Hostinger keeps the mail), FluentCRM contacts and notes, posts, users,
 * media (profile pictures stay in the media library), and any other plugin's options.
 *
 * @package mailweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'mailweb_delete_data', 0 ) ) {
	return; // The default: keep everything.
}

global $wpdb;

$mailweb_options = array( 'mailweb_cap', 'mailweb_mailboxes', 'mailweb_default_from', 'mailweb_aliases', 'mailweb_profiles', 'mailweb_crm', 'mailweb_smtp', 'mailweb_api_token', 'mailweb_box_addresses', 'mailweb_box_ids', 'mailweb_stats', 'mailweb_send_log', 'mailweb_dashboard_log', 'mailweb_last', 'mailweb_last_error', 'mailweb_crm_done', 'mailweb_queue_db', 'mailweb_pause', 'mailweb_delete_data' );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! mailweb_uninstall_backup( 'mailweb', __DIR__ . '/mailweb.php', $mailweb_options, array( 'mailweb_secret_', 'mailweb_overview_last_' ), array( $wpdb->prefix . 'mailweb_queue' ) ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mailweb_queue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table name.

foreach ( array( 'mailweb_flush', 'mailweb_crm_sync', 'mailweb_dashboard_build' ) as $mailweb_hook ) {
	wp_unschedule_hook( $mailweb_hook );
}
unset( $mailweb_hook );

$mailweb_secrets = array_map( fn( $k ) => 'mailweb_secret_' . sanitize_key( (string) $k ), array_keys( (array) get_option( 'mailweb_box_addresses', array() ) ) );
foreach ( array_merge( $mailweb_options, $mailweb_secrets ) as $mailweb_option ) {
	delete_option( $mailweb_option );
}
unset( $mailweb_secrets, $mailweb_option, $mailweb_options );

foreach ( array( 'mailweb_dashboard', 'mailweb_box_ids_wait', 'mailweb_flush_lock', 'mailweb_pause_seen', 'mailweb_smtp_error' ) as $mailweb_transient ) {
	delete_transient( $mailweb_transient );
}
unset( $mailweb_transient );

// The per-mailbox ones: webhook secrets of mailboxes no longer listed, "last seen" markers, folder and count caches.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API deletes options by prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'mailweb_secret_' ) . '%',
		$wpdb->esc_like( 'mailweb_overview_last_' ) . '%',
		$wpdb->esc_like( '_transient_mailweb_folders_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_mailweb_folders_' ) . '%',
		$wpdb->esc_like( '_transient_mailweb_overview_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_mailweb_overview_' ) . '%'
	)
);
wp_cache_flush(); // The deleted options may still be in the object cache.
