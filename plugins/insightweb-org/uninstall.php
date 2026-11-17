<?php
/**
 * Uninstall cleanup — runs only when the site owner deletes InsightWeb, and only if they opted in by turning on the
 * `insightweb_delete_data` option (off by default, so deleting the plugin keeps the data unless that was chosen).
 *
 * It removes ONLY InsightWeb's own data — its `{prefix}insightweb_*` tables, its two version options and the opt-in
 * flag, and its per-user screen-option meta. It never touches any other plugin's data: not Animal Writes Core, not
 * Kits Core, and never the shared `{prefix}aw_si_*` / `{prefix}aw_kw_*` tables or any `aw_*` option. The plugin is not
 * loaded during uninstall, so table and option names are spelled out here rather than read from the plugin.
 *
 * @package InsightWeb
 */

// Only ever run from WordPress's uninstall routine.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Opt-in only: do nothing unless the owner chose to delete the data.
if ( ! get_option( 'insightweb_delete_data' ) ) {
	return;
}

global $wpdb;

// InsightWeb's own tables (always the current blog's prefix + the insightweb_ namespace).
$insightweb_tables = array( 'keywords', 'kw_pages', 'obs', 'rising', 'links' );
foreach ( $insightweb_tables as $insightweb_name ) {
	$insightweb_table = $wpdb->prefix . 'insightweb_' . $insightweb_name;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time opt-in cleanup of the plugin's own tables, identifier bound via %i.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $insightweb_table ) );
}

// InsightWeb's own options.
foreach ( array( 'insightweb_db', 'insightweb_kw_db', 'insightweb_delete_data' ) as $insightweb_option ) {
	delete_option( $insightweb_option );
}

// InsightWeb's per-user screen-option meta (per-page counts).
foreach ( array( 'insightweb_keywords_per_page', 'insightweb_rising_per_page', 'insightweb_links_per_page' ) as $insightweb_meta ) {
	delete_metadata( 'user', 0, $insightweb_meta, '', true );
}
