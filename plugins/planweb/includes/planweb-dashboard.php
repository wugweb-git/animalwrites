<?php
/**
 * The counts PlanWeb's Dashboard shows, built by the hourly job planweb_counts_build, never while a screen loads. The
 * job reads every entry once, works the counts out (planweb_count_entries(), ui-model.php) and keeps them in the
 * transient planweb_counts. Saving or trashing an entry, or the first view of a new day, asks for one count at once.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLANWEB_COUNTS     = 'planweb_counts';
const PLANWEB_COUNTS_JOB = 'planweb_counts_build';

add_action( PLANWEB_COUNTS_JOB, 'planweb_counts_build' );

// Hourly while PlanWeb is on, scheduled from an admin request so a page view never writes; gone when it is off.
add_action(
	'admin_init',
	function () {
		$next = wp_next_scheduled( PLANWEB_COUNTS_JOB );
		if ( planweb_on() && ! $next ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', PLANWEB_COUNTS_JOB );
		} elseif ( ! planweb_on() && $next ) {
			wp_clear_scheduled_hook( PLANWEB_COUNTS_JOB );
		}
	}
);

/**
 * An entry changed: count again in the background.
 *
 * @param int $post_id Post.
 */
function planweb_counts_changed( $post_id ) {
	if ( PLANWEB_TYPE === get_post_type( $post_id ) ) {
		planweb_counts_request();
	}
}
add_action( 'save_post_' . PLANWEB_TYPE, 'planweb_counts_changed' );
add_action( 'trashed_post', 'planweb_counts_changed' );
add_action( 'untrashed_post', 'planweb_counts_changed' );

/**
 * Ask for a count now: one single event, never a count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function planweb_counts_request(): bool {
	if ( wp_next_scheduled( PLANWEB_COUNTS_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), PLANWEB_COUNTS_JOB, array( 'now' ) );
}

/**
 * The job: every entry, counted.
 *
 * @param string $why 'now' when asked for, '' on the hourly run.
 * @return array The counts.
 */
function planweb_counts_build( $why = '' ) {
	unset( $why );
	if ( ! planweb_on() || ! post_type_exists( PLANWEB_TYPE ) ) {
		return array();
	}
	$check = get_option( 'planweb_import_done' ) || get_option( 'planweb_import_result' ) ? planweb_verify() : array();
	$c     = planweb_count_entries( planweb_entries( '', '', array( 'posts_per_page' => -1 ) ), wp_date( 'Y-m-d' ), $check );

	$c['site'] = planweb_site_counts( wp_date( 'Y-m-d' ) ); // The site's own schedule (site-schedule.php).

	$c['at'] = time();
	set_transient( PLANWEB_COUNTS, $c, 3 * HOUR_IN_SECONDS );
	return $c;
}
