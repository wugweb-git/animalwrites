<?php
/**
 * SourceWeb Dashboard counts: built by the hourly job sourceweb_dashboard_build, never while a screen loads. The job
 * keeps the newest counts in the transient sourceweb_dashboard (3 hours) and one row a day in the option
 * sourceweb_dashboard_log (120 days, not autoloaded), which the Dashboard's 7, 28 and 90-day chips compare against.
 * Everything it reads is stored already (options, the aw_enrich table, the review queue), so one run takes seconds.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SOURCEWEB_DASH          = 'sourceweb_dashboard';
const SOURCEWEB_DASH_LOG      = 'sourceweb_dashboard_log';
const SOURCEWEB_DASH_JOB      = 'sourceweb_dashboard_build';
const SOURCEWEB_DASH_LOG_DAYS = 120;

add_action( SOURCEWEB_DASH_JOB, 'sourceweb_dashboard_build' );

// Hourly, scheduled from an admin request (as the plugin's other jobs), so a front-end view never writes.
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( SOURCEWEB_DASH_JOB ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', SOURCEWEB_DASH_JOB );
		}
	}
);

/**
 * Ask for a count now (the Dashboard's first view, or Count now): one single event, never a count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function sourceweb_dashboard_request(): bool {
	if ( wp_next_scheduled( SOURCEWEB_DASH_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), SOURCEWEB_DASH_JOB, array( 'now' ) );
}

/**
 * The job: every count, then the day's row in the log.
 *
 * @param string $why 'now' when asked for from the Dashboard, '' on the hourly run.
 * @return array The counts.
 */
function sourceweb_dashboard_build( $why = '' ) {
	unset( $why );
	$c = array(
		'at'      => time(),
		'review'  => sourceweb_count_review(),
		'enrich'  => sourceweb_count_enrich(),
		'intake'  => sourceweb_count_intake(),
		'sources' => sourceweb_count_sources(),
		'workers' => sourceweb_count_workers(),
		'ai'      => function_exists( 'aw_sourcing_ai_counts' ) ? aw_sourcing_ai_counts() : array(),
		'google'  => array(
			'live'   => function_exists( 'aw_enrich_spent' ) ? aw_enrich_spent( 'live' ) : 0.0,
			'worker' => function_exists( 'aw_enrich_spent' ) ? aw_enrich_spent( 'worker' ) : 0.0,
			'cap'    => (int) get_option( 'aw_enrich_live_cap', '20' ),
			'key'    => '' !== (string) get_option( 'aw_enrich_google_key', '' ),
		),
		// Tiles another plugin fills (Animal Writes Core: Picture pipeline, Thumbnails): [ { key, label, value, sub, href, tone } ].
		'extra'   => array_values( array_filter( (array) apply_filters( 'aw_sourcing_dashboard_tiles', array() ), 'is_array' ) ),
	);
	set_transient( SOURCEWEB_DASH, $c, 3 * HOUR_IN_SECONDS );
	update_option( SOURCEWEB_DASH_LOG, sourceweb_log_add( (array) get_option( SOURCEWEB_DASH_LOG, array() ), wp_date( 'Y-m-d' ), sourceweb_dashboard_numbers( $c ), SOURCEWEB_DASH_LOG_DAYS ), false );
	return $c;
}

/** To review: items waiting, by type, and the oldest one's date. */
function sourceweb_count_review(): array {
	$out = array(
		'total'  => 0,
		'by'     => array(),
		'oldest' => 0,
	);
	if ( ! function_exists( 'aw_review_items' ) ) {
		return $out;
	}
	foreach ( aw_review_items() as $p ) {
		++$out['total'];
		$out['by'][ $p->post_type ] = ( $out['by'][ $p->post_type ] ?? 0 ) + 1;
		$t                          = (int) get_post_timestamp( $p );
		$out['oldest']              = $out['oldest'] ? min( $out['oldest'], $t ) : $t;
	}
	arsort( $out['by'] );
	return $out;
}

/** Listing details: proposals waiting, applied in the last 7 days (and how many of them automatically), listings checked. */
function sourceweb_count_enrich(): array {
	global $wpdb;
	$out = array(
		'pending'  => 0,
		'listings' => 0,
		'applied7' => 0,
		'auto7'    => 0,
		'checked'  => 0,
		'total'    => 0,
	);
	if ( function_exists( 'aw_enrich_progress' ) ) {
		$p              = aw_enrich_progress();
		$out['pending'] = (int) $p['pending'];
		$out['checked'] = (int) $p['checked'];
		$out['total']   = (int) $p['total'];
	}
	if ( ! get_option( 'aw_enrich_db' ) || ! function_exists( 'aw_enrich_table' ) ) {
		return $out;
	}
	$t               = aw_enrich_table();
	$since           = wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$out['listings'] = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT listing_id ) FROM {$t} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB -- hourly count in the background; the table name is the plugin's own
	$rows            = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$t} WHERE status IN ( 'approved', 'auto-applied' ) AND ( decided_at >= %s OR created >= %s ) GROUP BY status", $since, $since ) ); // phpcs:ignore WordPress.DB -- same
	foreach ( (array) $rows as $r ) {
		$out['applied7'] += (int) $r->n;
		if ( 'auto-applied' === $r->status ) {
			$out['auto7'] += (int) $r->n;
		}
	}
	return $out;
}

/** Today's intake: the workers' last report, the Site Health test and items refused today. */
function sourceweb_count_intake(): array {
	$hb    = (array) get_option( 'aw_ingest_heartbeat', array() );
	$test  = function_exists( 'aw_sourcing_health_test' ) ? aw_sourcing_health_test() : array();
	$today = current_time( 'Y-m-d' );
	$rej   = 0;
	foreach ( (array) get_option( defined( 'AW_RELEVANCE_LOG' ) ? AW_RELEVANCE_LOG : 'aw_relevance_rejected', array() ) as $e ) {
		if ( is_array( $e ) && 0 === strpos( (string) ( $e['at'] ?? '' ), $today ) ) {
			++$rej;
		}
	}
	return array(
		'ok'             => 'good' === ( $test['status'] ?? '' ),
		'status'         => (string) ( $test['status'] ?? '' ),
		'label'          => (string) ( $test['label'] ?? '' ),
		'problem'        => 'good' === ( $test['status'] ?? 'good' ) ? '' : trim( wp_strip_all_tags( (string) ( $test['description'] ?? '' ) ) ),
		'last_run'       => (int) ( $hb['last_run'] ?? 0 ),
		'new'            => (int) ( $hb['new'] ?? 0 ),
		'published'      => (int) ( $hb['published'] ?? 0 ),
		'sources'        => (int) ( $hb['sources'] ?? 0 ),
		'failed'         => (int) ( $hb['sources_failed'] ?? 0 ),
		'rejected_today' => $rej,
		'rejected_all'   => count( (array) get_option( defined( 'AW_RELEVANCE_LOG' ) ? AW_RELEVANCE_LOG : 'aw_relevance_rejected', array() ) ),
	);
}

/** Sources: publishers by status (not the Directory's own source hosts) and the lists' feeds. */
function sourceweb_count_sources(): array {
	$pubs = function_exists( 'aw_publishers' ) ? aw_publishers() : array();
	$by   = array_count_values( array_map( fn( $p ) => (string) ( $p['status'] ?? 'neutral' ), array_filter( $pubs, fn( $p ) => is_array( $p ) && empty( $p['kind'] ) ) ) );
	$cfg  = get_option( 'aw_sources' ); // Read as stored: aw_sources_config() would seed it.
	return array(
		'allow'   => (int) ( $by['allow'] ?? 0 ),
		'neutral' => (int) ( $by['neutral'] ?? 0 ),
		'block'   => (int) ( $by['block'] ?? 0 ),
		'feeds'   => is_array( $cfg ) ? count( (array) ( $cfg['sources'] ?? array() ) ) : 0,
		'live'    => is_array( $cfg ) && ! empty( $cfg['live'] ),
	);
}

/** Workers: every workflow's last report and next run, and the problems the hourly watchdog sees. */
function sourceweb_count_workers(): array {
	if ( ! function_exists( 'aw_workers_runs' ) || ! defined( 'AW_WORKERS' ) ) {
		return array();
	}
	$now  = time();
	$runs = array();
	$good = 0;
	foreach ( aw_workers_runs() as $file => $list ) {
		$w = function_exists( 'aw_worker_def' ) ? aw_worker_def( $file, AW_WORKERS[ $file ] ) : AW_WORKERS[ $file ];
		krsort( $list, SORT_NUMERIC );
		$last   = $list ? reset( $list ) : null;
		$seen   = $last ? max( (int) ( $last['ended'] ?? 0 ), (int) ( $last['started'] ?? 0 ) ) : 0;
		$next   = ! empty( $w['crons'] ) && function_exists( 'aw_workers_next' ) ? aw_workers_next( (array) $w['crons'], $now ) : 0;
		$result = $last ? (string) ( $last['result'] ?? 'failed' ) : '';
		$good  += 'success' === $result ? 1 : 0;
		$runs[] = array(
			'file'   => (string) $file,
			'name'   => (string) $w['name'],
			'last'   => $seen,
			'result' => $result,
			'next'   => $next,
			'after'  => (string) ( $w['after'] ?? '' ),
		);
	}
	return array(
		'total'    => count( $runs ),
		'good'     => $good,
		'problems' => function_exists( 'aw_workers_problems' ) ? array_values( array_map( 'strval', (array) aw_workers_problems() ) ) : array(),
		'runs'     => $runs,
		'token'    => defined( 'AW_GITHUB_DISPATCH_TOKEN' ) && '' !== (string) AW_GITHUB_DISPATCH_TOKEN,
	);
}
