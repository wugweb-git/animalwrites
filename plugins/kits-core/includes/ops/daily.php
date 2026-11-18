<?php
/**
 * The daily event aw_daily (moved from Animal Writes Core's cron.php, 19 Nov 2026). Other plugins hook their daily jobs
 * onto it (DirectoryWeb's re-verification, SourceWeb's opportunities, Kits Core's thumbnails), so the event keeps its
 * name. It is booked here on init when it is missing (Animal Writes Core booked it when it was activated); nothing
 * runs on it by itself.
 *
 * WP-Cron runs on traffic; on the live server a real cron hits wp-cron.php (cron-health.php shows who calls it).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'aw_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aw_daily' );
		}
	}
);
