<?php
/**
 * Daily housekeeping: the aw_daily event (its jobs hook onto it: DirectoryWeb's re-verification, thumbs.php) and the
 * LiteSpeed crawler window.
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
		'aw_cc0_run',          // aw-sourcing cc0-images.php
		'aw_raw_prune',        // campaign-form.php
		'aw_fetch_favicons',   // kits-core attribution.php
		'kits_spot_map_daily', // kits-core system-screen.php
		'aw_ai_check_all',     // aw-sourcing ai-keys.php
		'aw_workers_watchdog', // aw-sourcing workers.php
		'aw_ingest_watchdog',  // aw-sourcing relevance.php
		'aw_sourcing_site_meta', // aw-sourcing source-sites.php (AW_SOURCING_SITE_META_CRON)
	);
	foreach ( $hooks as $hook ) {
		wp_clear_scheduled_hook( $hook ); // the no-argument (repeating) events only: a queued one-off with arguments, such as the workers' end-of-run report (aw_workers_watchdog, 'report'), still runs
	}
}

/**
 * The daily re-verification flags are DirectoryWeb's now (directoryweb_flag_reverify, on this same aw_daily event).
 * The old name stays for anyone who calls it directly.
 */
if ( ! function_exists( 'aw_daily' ) ) {
	function aw_daily() {
		if ( function_exists( 'directoryweb_flag_reverify' ) ) {
			directoryweb_flag_reverify();
		}
	}
}

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
