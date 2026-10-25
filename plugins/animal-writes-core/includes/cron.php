<?php
/**
 * Daily housekeeping: flag listings due for re-verification.
 *
 * WP-Cron runs on traffic; on the live server add a real cron hitting wp-cron.php.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aw_cron_schedule() {
	if ( ! wp_next_scheduled( 'aw_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_daily' );
	}
}

/**
 * On deactivation: every repeating event of Core and the code it loads (Kits Core, AW Sourcing), so none is left
 * scheduled without its callback (plugin audit 23). Each is scheduled again on init when the plugin is turned back on.
 * One-off events (a geocode, a rescan) are left: they run once and are gone.
 */
function aw_cron_unschedule() {
	$hooks = array(
		'aw_daily',
		'aw_daily_backup',     // backups.php
		'aw_cc0_run',          // cc0-images.php
		'aw_raw_prune',        // campaign-form.php
		'aw_fetch_favicons',   // kits-core attribution.php
		'kits_spot_map_daily', // kits-core system-screen.php
		'aw_ai_check_all',     // aw-sourcing ai-keys.php
		'aw_workers_watchdog', // aw-sourcing workers.php
		'aw_ingest_watchdog',  // aw-sourcing relevance.php
		'aw_sourcing_site_meta', // aw-sourcing source-sites.php (AW_SOURCING_SITE_META_CRON)
	);
	foreach ( $hooks as $hook ) {
		wp_unschedule_hook( $hook );
	}
}

/**
 * Run daily tasks.
 */
function aw_daily() {
	$today = current_time( 'Y-m-d' );

	// Flag stale verifications.
	$cutoff = gmdate( 'Y-m-d', strtotime( $today . ' -' . (int) AW_REVERIFY_DAYS . ' days' ) );
	$stale  = get_posts(
		array(
			'post_type'      => 'aw_listing',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => 'aw_verified', 'value' => '1' ),
				array( 'key' => 'aw_verified_on', 'value' => $cutoff, 'compare' => '<', 'type' => 'DATE' ),
			),
		)
	);
	foreach ( $stale as $id ) {
		update_post_meta( $id, 'aw_needs_reverify', true );
	}

	// AWBI recognition past its "valid until": the listing needs re-checking (badge and trust line say so).
	$lapsed = get_posts(
		array(
			'post_type'      => 'aw_listing',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array( 'key' => 'aw_awbi_valid', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ),
			),
		)
	);
	foreach ( $lapsed as $id ) {
		update_post_meta( $id, 'aw_needs_reverify', true );
	}
}

/** True when the listing's AWBI recognition has a "valid until" date that has passed. */
function aw_awbi_lapsed( $id ) {
	$v = (string) get_post_meta( $id, 'aw_awbi_valid', true );
	return '' !== $v && $v < current_time( 'Y-m-d' );
}
add_action( 'aw_daily', 'aw_daily' );

/**
 * LiteSpeed cache warmer: only between 02:00 and 05:00 India time (owner, 17 Oct 2026).
 * Outside that window its scheduled run does nothing; the full sitemap is still warmed each week.
 */
function aw_litespeed_crawler_night_only() {
	if ( ! wp_doing_cron() ) {
		return;
	}
	$hour = (int) ( new DateTime( 'now', new DateTimeZone( 'Asia/Kolkata' ) ) )->format( 'G' );
	if ( $hour < 2 || $hour >= 5 ) {
		remove_all_actions( 'litespeed_task_crawler' );
	}
}
add_action( 'init', 'aw_litespeed_crawler_night_only', PHP_INT_MAX );
