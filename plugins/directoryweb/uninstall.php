<?php
/**
 * Uninstall DirectoryWeb. By default it deletes nothing: listings, places, claims, settings and every option stay, so
 * reinstalling finds everything as it was.
 *
 * Only when the option directoryweb_delete_data_on_uninstall is true does it delete DirectoryWeb's own bookkeeping,
 * exactly this:
 *
 * Options: directoryweb_caps, directoryweb_rewrite_ver, directoryweb_delete_data_on_uninstall.
 * Transients (they expire anyway): directoryweb_stats, directoryweb_claim_rows.
 * Scheduled events (all, whatever their arguments): directoryweb_stats_build.
 * Tables: none of its own.
 *
 * Never deleted, even when ticked:
 * - Listings, claims, form entries, posts of any type, terms, users, attachments and any post, term or user meta.
 * - The Directory's data options that keep their Animal Writes names (aw_place_counts, aw_district_helplines,
 *   aw_place_intros, aw_gov_verified_live, aw_geo_last_request and the others): Animal Writes Core's uninstall owns them.
 * - The capability manage_directoryweb given to roles (it is part of the users' roles).
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/directoryweb-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored. If the backup
 * cannot be written, nothing is deleted.
 *
 * Runs for the current site only.
 *
 * @package directoryweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( true !== (bool) get_option( 'directoryweb_delete_data_on_uninstall', false ) ) {
	return; // The default: keep everything.
}

$directoryweb_options = array( 'directoryweb_caps', 'directoryweb_rewrite_ver' );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! directoryweb_uninstall_backup( 'directoryweb', __DIR__ . '/directoryweb.php', array_merge( $directoryweb_options, array( 'directoryweb_delete_data_on_uninstall' ) ), array(), array() ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

foreach ( $directoryweb_options as $directoryweb_option ) {
	delete_option( $directoryweb_option );
}

delete_transient( 'directoryweb_stats' );
delete_transient( 'directoryweb_claim_rows' );
wp_unschedule_hook( 'directoryweb_stats_build' );

delete_option( 'directoryweb_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
