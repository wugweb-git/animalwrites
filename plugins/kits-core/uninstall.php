<?php
/**
 * Uninstall Kits Core. By default it deletes nothing: theme options, form settings, saved keys, publisher records and
 * visit counts stay, so reinstalling finds everything as it was.
 *
 * Only when Kits → Settings → Uninstall → "Delete this plugin's data when it is deleted" is ticked
 * (kits_delete_data_on_uninstall is true) does it delete Kits Core's own data, exactly this:
 *
 * Options:
 *   kits_theme_options, kits_page_defaults, kits_settings_backup, kits_settings_backup_defaults, kits_altcha_key,
 *   kits_ga4_via_gtm, kits_preview_devices, kits_preview_ips, kits_preview_links, kits_spot_map,
 *   kits_delete_data_on_uninstall.
 * Transients (they expire anyway): kits_preview_link_*, kits_thanks_*, kits_transfer_result_*, kits_altcha_*, and the
 *   site transient kits_update_release.
 * Scheduled events: kits_spot_map_daily.
 * Tables: none of its own.
 *
 * Never deleted, even when ticked:
 * - Posts, pages, synced patterns, Styles (wp_global_styles), terms, users, attachments and any post meta (the
 *   _kits_* Page Options of each page stay).
 * - Shared data Kits Core keeps for the other plugins: the keyword tables ({prefix}aw_si_keywords and
 *   {prefix}aw_si_kw_map) and their version option aw_kw_db, the sourcing lists aw_sources, aw_publishers and
 *   aw_relevance, the aw_fetch_favicons event, and the aw_form_* / aw_rl_* form transients (shared with Animal Writes
 *   Core; they expire within the hour).
 * - Anything belonging to another plugin or to WordPress itself.
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

foreach ( array(
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
) as $kits_option ) {
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

delete_option( 'kits_delete_data_on_uninstall' ); // Last, so a run that stops half-way can be repeated.
