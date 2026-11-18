<?php
/**
 * Uninstall Kits Core. By default it deletes nothing: theme options, form settings, saved keys and publisher records
 * stay, so reinstalling finds everything as it was.
 *
 * Only when Kits → Settings → Uninstall → "Delete this plugin's data when it is deleted" is ticked
 * (kits_delete_data_on_uninstall is true) does it delete Kits Core's own data, exactly this:
 *
 * Options:
 *   kits_theme_options, kits_page_defaults, kits_settings_backup, kits_settings_backup_defaults, kits_altcha_key,
 *   kits_ga4_via_gtm, kits_preview_devices, kits_preview_ips, kits_preview_links, kits_spot_map, kits_presets_enabled,
 *   kits_presets_rewrite, kits_presets_consent,
 *   kits_daily_backup (the daily backup's switch) and aw_backup_last (its last run, backups.php), aw_error_pages (the error pages' state, error-pages.php),
 *   kits_cli_commands (the Help commands list, cli-commands.php), kits_delete_data_on_uninstall.
 * Transients (they expire anyway): kits_preview_link_*, kits_thanks_*, kits_transfer_result_*, kits_altcha_*, and the
 *   site transient kits_update_release, aw_error_pages_checked.
 * Scheduled events: kits_spot_map_daily, aw_daily_backup.
 * Tables: none of its own.
 *
 * Never deleted, even when ticked:
 * - Posts, pages, synced patterns, Styles (wp_global_styles), terms, users, attachments and any post meta (the
 *   _kits_* Page Options of each page stay).
 * - The aw_form_* / aw_rl_* form transients (shared with Animal Writes Core; they expire within the hour).
 * - The keyword tables ({prefix}aw_si_keywords, {prefix}aw_si_kw_map) and aw_kw_db, the sourcing lists aw_sources,
 *   aw_publishers and aw_relevance, and the aw_fetch_favicons event: InsightWeb's and SourceWeb's since 18 Nov 2026
 *   (Kits Core no longer installs, reads or books them).
 * - Files: the daily backups in ~/backups and the error pages (drop-ins) in wp-content.
 * - Anything belonging to another plugin or to WordPress itself.
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/kits-core-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below (except kits_altcha_key, a regenerated
 * secret that is deleted but never written to the backup file) with its value as stored, then every row of
 * every table below (transients are caches and are not kept). If the backup cannot be written, nothing is deleted.
 *
 * Runs for the current site only.
 *
 * @package kits-core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( true !== (bool) get_option( 'kits_delete_data_on_uninstall', false ) ) {
	return; // The default: keep everything.
}

$kits_options = array(
	'kits_theme_options',
	'kits_page_defaults',
	'kits_settings_backup',
	'kits_settings_backup_defaults',
	'kits_altcha_key',
	'kits_ga4_via_gtm',
	'kits_preview_devices',
	'kits_preview_ips',
	'kits_preview_links',
	'kits_spot_map',
	'kits_presets_enabled',
	'kits_daily_backup',
	'aw_backup_last',
	'aw_error_pages',
	'kits_presets_rewrite',
	'kits_presets_consent',
	'kits_cli_commands',
);

// The ALTCHA signing key is deleted but kept OUT of the backup file: it is a secret (regenerated on demand), and the
// backup lives under uploads/, which is URL-reachable on servers that ignore .htaccess (for example nginx).
$kits_backup_options = array_values( array_diff( $kits_options, array( 'kits_altcha_key' ) ) );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! kits_uninstall_backup( 'kits-core', __DIR__ . '/kits-core.php', array_merge( $kits_backup_options, array( 'kits_delete_data_on_uninstall' ) ), array(), array() ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

foreach ( $kits_options as $kits_option ) {
	delete_option( $kits_option );
}

global $wpdb;
foreach ( array( 'kits_preview_link_', 'kits_thanks_', 'kits_transfer_result_', 'kits_altcha_' ) as $kits_prefix ) {
	foreach ( array( '_transient_', '_transient_timeout_' ) as $kits_kind ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $kits_kind . $kits_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall: transients by prefix, once.
	}
}
delete_site_transient( 'kits_update_release' );

wp_unschedule_hook( 'kits_spot_map_daily' );
wp_unschedule_hook( 'aw_daily_backup' ); // the daily backup (backups.php); the backup files in ~/backups stay
delete_transient( 'aw_error_pages_checked' ); // error-pages.php; the drop-ins in wp-content stay

delete_option( 'kits_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
