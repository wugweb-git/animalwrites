<?php
/**
 * Uninstall RankWeb. By default it deletes nothing: settings, FAQ records, focus keywords and the scores it has stored
 * stay, so reinstalling finds everything as it was.
 *
 * Only when "Delete this plugin's data when it is deleted" is ticked (RankWeb → Settings → Modules, option
 * aw_seo_delete_data = 1) it deletes RankWeb's own data, and exactly this:
 *
 * Options:
 *   aw_seo_settings          (but when it holds InsightWeb's numbers, key 'search-insights', that key stays)
 *   aw_faq_off_types, aw_seo_keep_listings, aw_default_share_image, aw_seo_delete_data,
 *   aw_seo_endpoints_last, aw_seo_cache_gen, aw_seo_press_kw_1103, aw_seed_redirects_version, aw_sitemap_filtered,
 *   aw_html_sitemap_page (the option only; the page itself stays), aw_faq_experiment,
 *   rankweb_health_log, rankweb_health_schema, aw_link_db, aw_link_index_version, aw_link_page_version, aw_link_index_built
 * Transients (and their timeouts):
 *   rankweb_health, aw_faq_counts, aw_faq_index, aw_html_sitemap, aw_seed_redirects_wait, aw_sitemap_dir_pairs,
 *   and every transient named aw_seo_… (aw_seo_dir_data, aw_seo_category_counts, aw_seo_endpoints_mailed,
 *   aw_seo_advice_focus_index_v2, aw_seo_advice_median2_<type>, aw_seo_rendered_<id>, aw_seo_c_<hash>,
 *   aw_seo_categories_<type>, aw_seo_link_index, aw_seo_link_<id>)
 * Scheduled events:
 *   aw_seo_fill_daily, aw_seo_endpoints_watchdog, aw_seo_sitemap_daily, aw_faq_index_build, rankweb_health_build and
 *   aw_link_index_build (both with and without their 'now' argument)
 * Custom tables: {prefix}aw_link_keywords and {prefix}aw_links (Internal links), dropped. Rank Math's tables are Rank Math's.
 *
 * Before deleting, writes a backup to wp-content/uploads/wugweb-backups/aw-seo-extras-uninstall-<YYYYmmdd-His>.jsonl.gz
 * (includes/uninstall-backup.php): a header line, then every option below with its value as stored, then every row of
 * every table below (transients are caches and are not kept).
 * aw_seo_settings is kept whole in the backup. If the backup cannot be written, nothing is deleted.
 *
 * Never deleted: posts, pages, terms, users, post or term meta (FAQ records, focus keywords, scores), Rank Math's data,
 * and any other plugin's options. The link keywords (post meta _aw_link_keywords) stay with the other post meta.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'aw_seo_delete_data', 0 ) ) {
	return; // The default: keep everything.
}

$aw_seo_options = array( 'aw_faq_off_types', 'aw_seo_keep_listings', 'aw_default_share_image', 'aw_seo_endpoints_last', 'aw_seo_cache_gen', 'aw_seo_press_kw_1103', 'aw_seed_redirects_version', 'aw_sitemap_filtered', 'aw_html_sitemap_page', 'aw_faq_experiment', 'rankweb_health_log', 'rankweb_health_schema', 'aw_link_db', 'aw_link_index_version', 'aw_link_page_version', 'aw_link_index_built', 'aw_seo_delete_data' );

require_once __DIR__ . '/includes/uninstall-backup.php';
if ( ! aw_seo_uninstall_backup( 'aw-seo-extras', __DIR__ . '/aw-seo-extras.php', array_merge( array( 'aw_seo_settings' ), $aw_seo_options ), array(), array( $GLOBALS['wpdb']->prefix . 'aw_link_keywords', $GLOBALS['wpdb']->prefix . 'aw_links' ) ) ) {
	return; // No backup, no delete (the reason is in the error log).
}

$aw_seo_settings = get_option( 'aw_seo_settings', array() );
if ( is_array( $aw_seo_settings ) && ! empty( $aw_seo_settings['search-insights'] ) ) {
	update_option( 'aw_seo_settings', array( 'search-insights' => $aw_seo_settings['search-insights'] ), true ); // InsightWeb's numbers stay
} else {
	delete_option( 'aw_seo_settings' );
}
unset( $aw_seo_settings );

foreach ( $aw_seo_options as $aw_seo_option ) {
	delete_option( $aw_seo_option );
}

foreach ( array( 'rankweb_health', 'aw_faq_counts', 'aw_faq_index', 'aw_html_sitemap', 'aw_seed_redirects_wait', 'aw_sitemap_dir_pairs' ) as $aw_seo_transient ) {
	delete_transient( $aw_seo_transient );
}
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no API deletes transients by prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_aw_seo_' ) . '%', $wpdb->esc_like( '_transient_timeout_aw_seo_' ) . '%' ) );

foreach ( array( 'aw_seo_fill_daily', 'aw_seo_endpoints_watchdog', 'aw_seo_sitemap_daily', 'aw_faq_index_build', 'rankweb_health_build', 'aw_link_index_build' ) as $aw_seo_hook ) {
	wp_clear_scheduled_hook( $aw_seo_hook );
}
wp_clear_scheduled_hook( 'rankweb_health_build', array( 'now' ) );
wp_clear_scheduled_hook( 'aw_link_index_build', array( 'now' ) );

// Internal links' tables (written to the backup above).
foreach ( array( 'aw_link_keywords', 'aw_links' ) as $aw_seo_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $aw_seo_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- RankWeb's own tables, on uninstall.
}
