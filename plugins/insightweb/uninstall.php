<?php
/**
 * Uninstall InsightWeb. By default it deletes nothing: settings, keys, keyword tables and every report it has collected
 * stay, so reinstalling finds everything as it was.
 *
 * Only when "Delete this plugin's data when it is deleted" is ticked (InsightWeb → Settings → Numbers → Keeping data,
 * option insightweb_delete_data = 1) it deletes InsightWeb's own data, and exactly this:
 *
 * Custom tables:
 *   {prefix}aw_si_kw_pages, {prefix}aw_si_obs, {prefix}aw_si_rising, {prefix}aw_si_links, {prefix}aw_si_visits
 * Options:
 *   every option named aw_si_… (aw_si_db, aw_si_runs, aw_si_off, aw_si_gdelt_on, aw_si_tool_seeds, aw_si_checklist,
 *   aw_si_psi_queue, aw_si_inspect_queue, aw_si_ref_icons, aw_si_link_dr, aw_si_links_gsc_pages, aw_si_links_imports,
 *   aw_si_gsc_coverage, aw_si_bing_cursor, aw_si_crawl_inside, aw_si_crawl_outside, aw_si_live_token, aw_si_vis_db,
 *   the daily server counts aw_si_srv_…, and the other run markers), every option named insightweb_… (with
 *   insightweb_delete_data), and the key 'search-insights' (the seven numbers) inside RankWeb's aw_seo_settings —
 *   the rest of that option is RankWeb's and stays
 * Transients (and their timeouts):
 *   every transient named aw_si_… or insightweb_… (insightweb_dash, aw_si_redirects, aw_si_rm_scores, per-user import
 *   results, Bing keyword volumes, …)
 * Scheduled events:
 *   aw_si_daily, aw_si_rollup, aw_si_alerts, aw_si_ga4, aw_si_psi, aw_si_psi_more, aw_si_clarity, aw_si_clarity_retry,
 *   aw_si_bing, aw_si_bing_kw, aw_si_bing_crawl, aw_si_gsc_types, aw_si_inspect, aw_si_inspect_more, aw_si_crawl,
 *   aw_si_geo_refresh, aw_si_rising_spikes, aw_si_srv_ai, aw_si_srv_speed, insightweb_dash_build — every event of
 *   these hooks, whatever its arguments
 *
 * Never deleted: the keyword tables {prefix}aw_si_keywords and {prefix}aw_si_kw_map (Kits Core's, they hold the
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

foreach ( array( 'kw_pages', 'obs', 'rising', 'links', 'visits' ) as $insightweb_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'aw_si_' . $insightweb_table ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixed table names.
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
$insightweb_hooks = array( 'aw_si_daily', 'aw_si_rollup', 'aw_si_alerts', 'aw_si_ga4', 'aw_si_psi', 'aw_si_psi_more', 'aw_si_clarity', 'aw_si_clarity_retry', 'aw_si_bing', 'aw_si_bing_kw', 'aw_si_bing_crawl', 'aw_si_gsc_types', 'aw_si_inspect', 'aw_si_inspect_more', 'aw_si_crawl', 'aw_si_geo_refresh', 'aw_si_rising_spikes', 'aw_si_srv_ai', 'aw_si_srv_speed', 'insightweb_dash_build' );
foreach ( $insightweb_hooks as $insightweb_hook ) {
	wp_unschedule_hook( $insightweb_hook );
}
unset( $insightweb_hooks, $insightweb_hook, $insightweb_table );

// Options and transients by name: aw_si_… and insightweb_… are InsightWeb's only.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API deletes options by prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'aw_si_' ) . '%',
		$wpdb->esc_like( 'insightweb_' ) . '%',
		$wpdb->esc_like( '_transient_aw_si_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_aw_si_' ) . '%',
		$wpdb->esc_like( '_transient_insightweb_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_insightweb_' ) . '%'
	)
);
wp_cache_flush(); // The deleted options may still be in the object cache.
