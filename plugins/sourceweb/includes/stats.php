<?php
/**
 * Stats: a small, self-contained metrics layer for the Overview dashboard. Counts are kept in one option
 * (`sourceweb_stats`) as per-day buckets, so the dashboard can show how the curation pipeline behaves over
 * time — items fetched, items queued, items dropped as near-duplicates or blocked by the gate, and items
 * approved or rejected — plus a daily snapshot of the review-queue size.
 *
 * The aggregation functions are pure (they take the stored structure as an argument and read no WordPress),
 * so they are unit-testable without a WordPress install. Only the two recording helpers touch options.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * The per-day counter keys the dashboard reports (queue_size is a snapshot, handled separately).
 *
 * @return string[]
 */
function sourceweb_stats_metrics() {
	return array( 'fetched', 'queued', 'skipped_dedupe', 'skipped_gate', 'skipped_other', 'approved', 'rejected' );
}

/**
 * An empty day bucket (every counter at zero; no queue_size snapshot yet).
 *
 * @return array<string,int>
 */
function sourceweb_stats_empty_day() {
	$day = array();
	foreach ( sourceweb_stats_metrics() as $metric ) {
		$day[ $metric ] = 0;
	}
	return $day;
}

/**
 * The list of calendar dates (Y-m-d, ascending) ending at $today and spanning $days days.
 *
 * @param int    $days  Number of days (>= 1).
 * @param string $today End date (Y-m-d).
 * @return string[]
 */
function sourceweb_stats_date_range( $days, $today ) {
	$days  = max( 1, (int) $days );
	$end   = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $today );
	$end   = $end ? $end : new DateTimeImmutable( 'today' );
	$dates = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$dates[] = $end->sub( new DateInterval( 'P' . $i . 'D' ) )->format( 'Y-m-d' );
	}
	return $dates;
}

/**
 * A daily time series for one metric over the window: one {date, value} pair per day, zero-filled, ascending.
 *
 * @param array  $stats  The stored stats structure (expects a 'days' map).
 * @param string $metric Counter key.
 * @param int    $days   Window length in days.
 * @param string $today  End date (Y-m-d).
 * @return array<int,array{date:string,value:int}>
 */
function sourceweb_stats_series( $stats, $metric, $days, $today ) {
	$buckets = isset( $stats['days'] ) && is_array( $stats['days'] ) ? $stats['days'] : array();
	$series  = array();
	foreach ( sourceweb_stats_date_range( $days, $today ) as $date ) {
		$value    = isset( $buckets[ $date ][ $metric ] ) ? (int) $buckets[ $date ][ $metric ] : 0;
		$series[] = array(
			'date'  => $date,
			'value' => $value,
		);
	}
	return $series;
}

/**
 * The summed total of each metric over the window.
 *
 * @param array  $stats The stored stats structure.
 * @param int    $days  Window length in days.
 * @param string $today End date (Y-m-d).
 * @return array<string,int>
 */
function sourceweb_stats_totals( $stats, $days, $today ) {
	$buckets = isset( $stats['days'] ) && is_array( $stats['days'] ) ? $stats['days'] : array();
	$dates   = sourceweb_stats_date_range( $days, $today );
	$totals  = array();
	foreach ( sourceweb_stats_metrics() as $metric ) {
		$sum = 0;
		foreach ( $dates as $date ) {
			$sum += isset( $buckets[ $date ][ $metric ] ) ? (int) $buckets[ $date ][ $metric ] : 0;
		}
		$totals[ $metric ] = $sum;
	}
	return $totals;
}

/**
 * The same-story dedupe rate over the window: the share of candidate items dropped as near-duplicates, out of
 * all items that cleared the gate (queued + dropped-as-duplicate). A float in 0..1; 0 when there is nothing to
 * measure.
 *
 * @param array  $stats The stored stats structure.
 * @param int    $days  Window length in days.
 * @param string $today End date (Y-m-d).
 * @return float
 */
function sourceweb_stats_dedupe_rate( $stats, $days, $today ) {
	$totals = sourceweb_stats_totals( $stats, $days, $today );
	$queued = (int) $totals['queued'];
	$dupes  = (int) $totals['skipped_dedupe'];
	$denom  = $queued + $dupes;
	if ( $denom <= 0 ) {
		return 0.0;
	}
	return round( $dupes / $denom, 4 );
}

/**
 * The total of a metric for the window versus the immediately preceding window of the same length.
 *
 * @param array  $stats  The stored stats structure.
 * @param string $metric Counter key.
 * @param int    $days   Window length in days.
 * @param string $today  End date (Y-m-d).
 * @return array{current:int,previous:int,change:int}
 */
function sourceweb_stats_period_delta( $stats, $metric, $days, $today ) {
	$days    = max( 1, (int) $days );
	$current = 0;
	foreach ( sourceweb_stats_series( $stats, $metric, $days, $today ) as $point ) {
		$current += (int) $point['value'];
	}
	// The window that ends the day before the current one begins.
	$end  = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $today );
	$end  = $end ? $end : new DateTimeImmutable( 'today' );
	$prev = $end->sub( new DateInterval( 'P' . $days . 'D' ) )->format( 'Y-m-d' );

	$previous = 0;
	foreach ( sourceweb_stats_series( $stats, $metric, $days, $prev ) as $point ) {
		$previous += (int) $point['value'];
	}
	return array(
		'current'  => $current,
		'previous' => $previous,
		'change'   => $current - $previous,
	);
}

/**
 * The most recent review-queue-size snapshot at or before $today, or null when none was ever recorded.
 *
 * @param array  $stats The stored stats structure.
 * @param string $today End date (Y-m-d).
 * @return int|null
 */
function sourceweb_stats_queue_latest( $stats, $today ) {
	$buckets = isset( $stats['days'] ) && is_array( $stats['days'] ) ? $stats['days'] : array();
	if ( ! $buckets ) {
		return null;
	}
	$dates = array_keys( $buckets );
	sort( $dates );
	$latest = null;
	foreach ( $dates as $date ) {
		if ( $date <= $today && isset( $buckets[ $date ]['queue_size'] ) ) {
			$latest = (int) $buckets[ $date ]['queue_size'];
		}
	}
	return $latest;
}

/**
 * Drop day buckets older than $keep days before $today (keeps the option bounded).
 *
 * @param array  $stats The stored stats structure (modified by value and returned).
 * @param int    $keep  Days to keep.
 * @param string $today End date (Y-m-d).
 * @return array The trimmed structure.
 */
function sourceweb_stats_trim( $stats, $keep, $today ) {
	if ( ! isset( $stats['days'] ) || ! is_array( $stats['days'] ) ) {
		$stats['days'] = array();
		return $stats;
	}
	$floor = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $today );
	$floor = $floor ? $floor : new DateTimeImmutable( 'today' );
	$floor = $floor->sub( new DateInterval( 'P' . max( 1, (int) $keep ) . 'D' ) )->format( 'Y-m-d' );
	foreach ( array_keys( $stats['days'] ) as $date ) {
		if ( $date < $floor ) {
			unset( $stats['days'][ $date ] );
		}
	}
	return $stats;
}

/**
 * The stored stats structure (option `sourceweb_stats`), normalised to { days: { Y-m-d: {...} } }.
 *
 * @return array{days:array}
 */
function sourceweb_stats_get() {
	$stats = function_exists( 'get_option' ) ? get_option( 'sourceweb_stats' ) : array();
	$stats = is_array( $stats ) ? $stats : array();
	if ( ! isset( $stats['days'] ) || ! is_array( $stats['days'] ) ) {
		$stats['days'] = array();
	}
	return $stats;
}

/**
 * Add to today's counters. Accepts a map of metric => increment; unknown metrics are ignored. One option write.
 *
 * @param array<string,int> $increments metric => count.
 * @param string|null       $day        Day (Y-m-d); defaults to today (UTC).
 * @return void
 */
function sourceweb_stats_bump( array $increments, $day = null ) {
	if ( ! function_exists( 'update_option' ) ) {
		return;
	}
	$day     = $day ? (string) $day : gmdate( 'Y-m-d' );
	$metrics = sourceweb_stats_metrics();
	$stats   = sourceweb_stats_get();
	if ( ! isset( $stats['days'][ $day ] ) || ! is_array( $stats['days'][ $day ] ) ) {
		$stats['days'][ $day ] = sourceweb_stats_empty_day();
	}
	foreach ( $increments as $metric => $count ) {
		if ( in_array( $metric, $metrics, true ) ) {
			$stats['days'][ $day ][ $metric ] = ( isset( $stats['days'][ $day ][ $metric ] ) ? (int) $stats['days'][ $day ][ $metric ] : 0 ) + (int) $count;
		}
	}
	$stats = sourceweb_stats_trim( $stats, 120, $day );
	update_option( 'sourceweb_stats', $stats, false );
}

/**
 * Record today's review-queue size (a snapshot, overwritten each time it is taken).
 *
 * @param int         $size Pending-item count.
 * @param string|null $day  Day (Y-m-d); defaults to today (UTC).
 * @return void
 */
function sourceweb_stats_snapshot_queue( $size, $day = null ) {
	if ( ! function_exists( 'update_option' ) ) {
		return;
	}
	$day   = $day ? (string) $day : gmdate( 'Y-m-d' );
	$stats = sourceweb_stats_get();
	if ( ! isset( $stats['days'][ $day ] ) || ! is_array( $stats['days'][ $day ] ) ) {
		$stats['days'][ $day ] = sourceweb_stats_empty_day();
	}
	$stats['days'][ $day ]['queue_size'] = max( 0, (int) $size );
	$stats                               = sourceweb_stats_trim( $stats, 120, $day );
	update_option( 'sourceweb_stats', $stats, false );
}

/**
 * Map a `sourceweb_queue_add` skip reason to the stats counter it belongs to.
 *
 * @param string $reason The reason string returned by sourceweb_queue_add.
 * @return string One of skipped_dedupe, skipped_gate, skipped_other.
 */
function sourceweb_stats_skip_metric( $reason ) {
	if ( 'duplicate' === $reason ) {
		return 'skipped_dedupe';
	}
	if ( 'blocked' === $reason || 'filtered' === $reason ) {
		return 'skipped_gate';
	}
	return 'skipped_other';
}
