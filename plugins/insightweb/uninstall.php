<?php
/**
 * Uninstall InsightWeb. By default it deletes nothing: settings, keys, keyword tables and every report it has collected
 * stay, so reinstalling finds everything as it was.
 *
 * Only when "Delete this plugin's data when it is deleted" is ticked (InsightWeb → Settings → Numbers → Keeping data,
 * option insightweb_delete_data = 1) it deletes InsightWeb's own data, and exactly this:
 *
 * Custom tables:
 *   {prefix}insightweb_kw_pages, {prefix}insightweb_obs, {prefix}insightweb_rising, {prefix}insightweb_links, {prefix}insightweb_visits
 * Options:
 *   every option named insightweb_… (insightweb_db, insightweb_runs, insightweb_off, insightweb_gdelt_on, insightweb_tool_seeds, insightweb_checklist,
 *   insightweb_psi_queue, insightweb_inspect_queue, insightweb_ref_icons, insightweb_link_dr, insightweb_links_gsc_pages, insightweb_links_imports,
 *   insightweb_gsc_coverage, insightweb_bing_cursor, insightweb_crawl_inside, insightweb_crawl_outside, insightweb_live_token, insightweb_vis_db,
 *   the daily server counts insightweb_srv_…, and the other run markers), every option named insightweb_… (with
 *   insightweb_delete_data), and the key 'search-insights' (the seven numbers) inside RankWeb's aw_seo_settings —
 *   the rest of that option is RankWeb's and stays
 * Transients (and their timeouts):
 *   every transient named insightweb_… or insightweb_… (insightweb_dash, insightweb_redirects, insightweb_rm_scores, per-user import
 *   results, Bing keyword volumes, …)
 * Scheduled events:
 *   insightweb_daily, insightweb_rollup, insightweb_alerts, insightweb_ga4, insightweb_psi, insightweb_psi_more, insightweb_clarity, insightweb_clarity_retry,
 *   insightweb_bing, insightweb_bing_kw, insightweb_bing_crawl, insightweb_gsc_types, insightweb_inspect, insightweb_inspect_more, insightweb_crawl,
 *   insightweb_geo_refresh, insightweb_rising_spikes, insightweb_srv_ai, insightweb_srv_speed, insightweb_dash_build — every event of
 *   these hooks, whatever its arguments
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/insightweb-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored, then every row of
 * every table below (transients are caches and are not kept).
 * RankWeb's aw_seo_settings is kept whole in the backup. If the backup cannot be written, nothing is deleted.
 *
 * Never deleted: the keyword tables {prefix}insightweb_keywords and {prefix}insightweb_kw_map (Kits Core's, they hold the
 * owner's Sourcing, Press and Tracked keywords), posts, users, Site Kit's and Rank Math's data, and any other plugin's
 * options.
 *
 * @package insightweb
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'insightweb_delete_data', 0 ) ) {
	return; // The default: keep everything.
}

global $wpdb;

$insightweb_tables = array_map( fn( $t ) => $wpdb->prefix . 'insightweb_' . $t, array( 'kw_pages', 'obs', 'rising', 'links', 'visits' ) );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! insightweb_uninstall_backup( 'insightweb', __DIR__ . '/insightweb.php', array( 'aw_seo_settings' ), array( 'insightweb_', 'insightweb_' ), $insightweb_tables ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

foreach ( $insightweb_tables as $insightweb_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $insightweb_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
}

// The seven numbers: InsightWeb's key in RankWeb's option.
$insightweb_seo = get_option( 'aw_seo_settings', null );
if ( is_array( $insightweb_seo ) && array_key_exists( 'search-insights', $insightweb_seo ) ) {
	unset( $insightweb_seo['search-insights'] );
	if ( $insightweb_seo ) {
		update_option( 'aw_seo_settings', $insightweb_seo, true );
	} else {
		delete_option( 'aw_seo_settings' );
	}
}
unset( $insightweb_seo );

// Scheduled events first: wp_clear_scheduled_hook() needs the arguments, so each event is taken off as it is listed.
$insightweb_hooks = array( 'insightweb_daily', 'insightweb_rollup', 'insightweb_alerts', 'insightweb_ga4', 'insightweb_psi', 'insightweb_psi_more', 'insightweb_clarity', 'insightweb_clarity_retry', 'insightweb_bing', 'insightweb_bing_kw', 'insightweb_bing_crawl', 'insightweb_gsc_types', 'insightweb_inspect', 'insightweb_inspect_more', 'insightweb_crawl', 'insightweb_geo_refresh', 'insightweb_rising_spikes', 'insightweb_srv_ai', 'insightweb_srv_speed', 'insightweb_dash_build' );
foreach ( $insightweb_hooks as $insightweb_hook ) {
	wp_unschedule_hook( $insightweb_hook );
}
unset( $insightweb_hooks, $insightweb_hook, $insightweb_table );

// Options and transients by name: insightweb_… and insightweb_… are InsightWeb's only.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API deletes options by prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'insightweb_' ) . '%',
		$wpdb->esc_like( 'insightweb_' ) . '%',
		$wpdb->esc_like( '_transient_aw_si_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aw_si_' ) . '%',
		$wpdb->esc_like( '_transient_insightweb_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_insightweb_' ) . '%'
	)
);
wp_cache_flush(); // The deleted options may still be in the object cache.
