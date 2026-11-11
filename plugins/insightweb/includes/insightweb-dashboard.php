<?php
/**
 * InsightWeb Dashboard counts: built by the job insightweb_dash_build, never while a screen loads. It sums the
 * Search Console rows Search Insights already stores (table aw_si_obs, src gsc, daily rows) for 7, 28 and 90 days and
 * the same length before, and keeps the result in the transient insightweb_dash. No remote call is made.
 *
 * The job runs twice a day, once more right after the daily Search Console import, and once when the Dashboard finds
 * nothing stored (a single event).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_DASH        = 'insightweb_dash';
const INSIGHTWEB_DASH_JOB    = 'insightweb_dash_build';
const INSIGHTWEB_DASH_RANGES = array( 7, 28, 90 );
const INSIGHTWEB_DASH_TOP    = 10;

add_action( INSIGHTWEB_DASH_JOB, 'insightweb_dash_build' );

// Twice a day, booked from an admin request; not while Search Insights is switched off by Delete data.
add_action(
	'admin_init',
	function () {
		if ( ! get_option( 'aw_si_off' ) && ! wp_next_scheduled( INSIGHTWEB_DASH_JOB ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'twicedaily', INSIGHTWEB_DASH_JOB );
		}
	}
);

// Right after the daily import (aw_si_daily runs at priority 10): count again in a separate request.
add_action( 'aw_si_daily', 'insightweb_dash_request', 20 );

// Delete data clears this job with the others.
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( INSIGHTWEB_DASH_JOB ) ) );

/**
 * Ask for a count now (the Dashboard's first view, Count now, or after the daily import): one single event, never a
 * count in this request.
 *
 * @return bool Whether a count is waiting to run.
 */
function insightweb_dash_request(): bool {
	if ( wp_next_scheduled( INSIGHTWEB_DASH_JOB, array( 'now' ) ) ) {
		return true;
	}
	return false !== wp_schedule_single_event( time(), INSIGHTWEB_DASH_JOB, array( 'now' ) );
}

/**
 * The job: every range from the stored rows.
 *
 * @param string $why 'now' when asked for, '' on the regular run.
 * @return array The counts.
 */
function insightweb_dash_build( $why = '' ) {
	unset( $why );
	$c = array(
		'at'     => time(),
		'end'    => '',
		'ranges' => array(),
	);
	if ( function_exists( 'aw_si_ready' ) && aw_si_ready() ) {
		$c = insightweb_dash_count( $c );
	}
	set_transient( INSIGHTWEB_DASH, $c, 2 * DAY_IN_SECONDS );
	return $c;
}

/**
 * Sum the stored Search Console rows. Anchored on the newest stored day, since Search Console runs a few days behind.
 *
 * @param array $c Counts so far (at).
 * @return array at, end (Y-m-d), ranges: days => [ days, stored, prevStored, clicks, impr, pos, prev*, pages, queries, chart, topPages, topQueries ].
 */
function insightweb_dash_count( array $c ): array {
	global $wpdb;
	$o   = aw_si_table( 'obs' );
	$end = (string) $wpdb->get_var( "SELECT MAX(d) FROM {$o} WHERE src = 'gsc' AND metric = 'total' AND period = 'day'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- background count; table name only.
	if ( '' === $end ) {
		return $c;
	}
	$c['end'] = $end;
	$e        = new DateTimeImmutable( $end, wp_timezone() );
	$longest  = max( INSIGHTWEB_DASH_RANGES );
	$first    = $e->modify( '-' . ( 2 * $longest - 1 ) . ' days' )->format( 'Y-m-d' );
	// One row per day: the site totals (clicks, impressions, position weighted by impressions).
	$days = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- background count; table name only.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) pw FROM {$o} WHERE src = 'gsc' AND metric = 'total' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY d ORDER BY d", $first, $end ) ) as $r ) {
		$days[ (string) $r->d ] = array( (int) $r->c, (int) $r->i, (float) $r->pw );
	}
	$k = aw_si_table( 'keywords' );
	foreach ( INSIGHTWEB_DASH_RANGES as $n ) {
		$from  = $e->modify( '-' . ( $n - 1 ) . ' days' )->format( 'Y-m-d' );
		$pfrom = $e->modify( '-' . ( 2 * $n - 1 ) . ' days' )->format( 'Y-m-d' );
		$pend  = $e->modify( '-' . $n . ' days' )->format( 'Y-m-d' );
		$now   = insightweb_dash_window( $days, $from, $end );
		$prev  = insightweb_dash_window( $days, $pfrom, $pend );
		$count = fn( string $metric, string $col, string $a, string $b ) => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- background count
			$wpdb->prepare( "SELECT COUNT(DISTINCT {$col}) FROM {$o} WHERE src = 'gsc' AND metric = %s AND period = 'day' AND d BETWEEN %s AND %s AND impr > 0", $metric, $a, $b ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table and column names from this file.
		);
		$row   = array(
			'days'       => $n,
			'from'       => $from,
			'stored'     => $now['stored'],
			'prevStored' => $prev['stored'],
			'clicks'     => $now['clicks'],
			'impr'       => $now['impr'],
			'pos'        => $now['pos'],
			'prevClicks' => $prev['clicks'],
			'prevImpr'   => $prev['impr'],
			'prevPos'    => $prev['pos'],
			'pages'      => $count( 'p', 'page_key', $from, $end ),
			'queries'    => $count( 'q', 'kw_id', $from, $end ),
			'prevPages'  => $prev['stored'] ? $count( 'p', 'page_key', $pfrom, $pend ) : 0,
			'prevQuery'  => $prev['stored'] ? $count( 'q', 'kw_id', $pfrom, $pend ) : 0,
			'chart'      => array(),
			'topPages'   => array(),
			'topQueries' => array(),
		);
		foreach ( $days as $d => $v ) {
			if ( $d >= $from ) {
				$row['chart'][ $d ] = $v[0];
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- background count; table name only.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM {$o} WHERE src = 'gsc' AND metric = 'p' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY page_key, page ORDER BY c DESC, i DESC LIMIT %d", $from, $end, INSIGHTWEB_DASH_TOP ) ) as $r ) {
			$row['topPages'][] = array( (string) $r->page, (int) $r->c, (int) $r->i, null === $r->p ? null : round( (float) $r->p, 1 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- background count; table names only.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT k.label, SUM(o.clicks) c, SUM(o.impr) i, SUM(o.pos * o.impr) / NULLIF(SUM(o.impr), 0) p FROM {$o} o JOIN {$k} k ON k.id = o.kw_id WHERE o.src = 'gsc' AND o.metric = 'q' AND o.period = 'day' AND o.d BETWEEN %s AND %s GROUP BY o.kw_id, k.label ORDER BY c DESC, i DESC LIMIT %d", $from, $end, INSIGHTWEB_DASH_TOP ) ) as $r ) {
			$row['topQueries'][] = array( (string) $r->label, (int) $r->c, (int) $r->i, null === $r->p ? null : round( (float) $r->p, 1 ) );
		}
		$c['ranges'][ $n ] = $row;
	}
	return $c;
}

/**
 * Totals of the stored days between two dates (inclusive).
 *
 * @param array  $days Y-m-d => [ clicks, impressions, position × impressions ].
 * @param string $from First day.
 * @param string $to   Last day.
 * @return array stored (days with rows), clicks, impr, pos (weighted by impressions; null without impressions).
 */
function insightweb_dash_window( array $days, string $from, string $to ): array {
	$out = array(
		'stored' => 0,
		'clicks' => 0,
		'impr'   => 0,
		'pos'    => null,
	);
	$pw  = 0.0;
	foreach ( $days as $d => $v ) {
		if ( $d < $from || $d > $to ) {
			continue;
		}
		++$out['stored'];
		$out['clicks'] += (int) $v[0];
		$out['impr']   += (int) $v[1];
		$pw            += (float) $v[2];
	}
	if ( $out['impr'] > 0 ) {
		$out['pos'] = round( $pw / $out['impr'], 1 );
	}
	return $out;
}
