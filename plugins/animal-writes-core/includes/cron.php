<?php
/**
 * Daily housekeeping: expire featured listings, flag listings due for re-verification.
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

function aw_cron_unschedule() {
	wp_clear_scheduled_hook( 'aw_daily' );
}

/**
 * Run daily tasks.
 */
function aw_daily() {
	aw_backfill_featured_flag();

	$today = current_time( 'Y-m-d' );

	// Expire featured.
	$expired = get_posts(
		array(
			'post_type'      => 'aw_listing',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => 'aw_featured', 'value' => 1, 'type' => 'NUMERIC' ),
				array( 'key' => 'aw_featured_until', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ),
			),
		)
	);
	foreach ( $expired as $id ) {
		update_post_meta( $id, 'aw_featured', 0 );
	}

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
