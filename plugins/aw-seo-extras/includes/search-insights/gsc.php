<?php
/**
 * Search Console through Site Kit, stored by calendar day (plan §5.1).
 *
 * - One request per day and dimension set: date (site total), date + query, date + query + page. Each stays under the
 *   5,000-row limit; a day that still returns 5,000 rows is marked truncated in the run log and on the screen.
 * - The last 4 days are fetched again every morning (Google finalises data 2–3 days late) and replace what was stored.
 * - A backfill walks back to 90 days, 15 days per run, so 28-day comparisons work from the first week.
 * - Fetch first, then replace: a failed day keeps its stored rows.
 *
 * Rows in obs: src gsc, period day; metric total (kw 0), q (query, page ''), qp (query + page), p (page, kw 0: the
 * page's full figures, including the searches Google hides, which qp rows leave out).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_GSC_LIMIT    = 5000;
const AW_SI_GSC_REFETCH  = 4;
const AW_SI_GSC_BACKFILL = 90;
const AW_SI_GSC_PER_RUN  = 15;
const AW_SI_GSC_BUDGET   = 50; // Seconds per run: stop before a host time limit, so the run is always logged.

/**
 * One searchanalytics request for one day: aw_sd_query() of search-demand.php, for a single date.
 *
 * @param object $module Site Kit's Search Console module.
 * @param array  $dims   Dimensions, e.g. date + query.
 * @param string $day    Date (Y-m-d).
 * @return array|WP_Error Rows as [keys, clicks, impressions, position].
 */
function aw_si_gsc_rows( $module, array $dims, string $day ) {
	return aw_sd_query( $module, $dims, $day, $day );
}

/**
 * Fetch one day: site total, queries, queries by page.
 *
 * @param object $module Site Kit's Search Console module.
 * @param string $day    Date (Y-m-d).
 * @return array|WP_Error array{total: array, q: array, qp: array, truncated: string[]}
 */
function aw_si_gsc_fetch_day( $module, string $day ) {
	$out = array( 'truncated' => array() );
	foreach ( array(
		'total' => array( 'date' ),
		'q'     => array( 'date', 'query' ),
		'qp'    => array( 'date', 'query', 'page' ),
		'p'     => array( 'date', 'page' ),
	) as $key => $dims ) {
		$rows = aw_si_gsc_rows( $module, $dims, $day );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( count( $rows ) >= AW_SI_GSC_LIMIT ) {
			$out['truncated'][] = $key;
		}
		$out[ $key ] = $rows;
	}
	return $out;
}

/**
 * Replace the stored rows of one day with $data. Queries become keyword records (role Searched).
 *
 * @param string $day  Date (Y-m-d).
 * @param array  $data What aw_si_gsc_fetch_day() returned.
 * @return int Rows written.
 */
function aw_si_gsc_store_day( string $day, array $data ): int {
	global $wpdb;
	$o      = aw_si_table( 'obs' );
	$labels = array();
	foreach ( array( 'q', 'qp' ) as $key ) {
		foreach ( $data[ $key ] as $r ) {
			$labels[] = (string) ( $r[0][1] ?? '' );
		}
	}
	$ids  = aw_si_kw_ids( array_unique( $labels ), AW_SI_ROLE_SEARCHED );
	$now  = aw_si_now();
	$vals = array();
	foreach ( $data['total'] as $r ) {
		$vals[] = array( 0, '', 'total', $r[1], $r[2], $r[3] );
	}
	foreach ( (array) ( $data['p'] ?? array() ) as $r ) {
		$page = aw_si_path( (string) ( $r[0][1] ?? '' ) );
		if ( '' !== $page ) {
			$vals[] = array( 0, $page, 'p', $r[1], $r[2], $r[3] );
		}
	}
	foreach ( array( 'q', 'qp' ) as $key ) {
		foreach ( $data[ $key ] as $r ) {
			$id = $ids[ aw_si_norm( (string) ( $r[0][1] ?? '' ) ) ] ?? 0;
			if ( ! $id ) {
				continue;
			}
			$page   = 'qp' === $key ? aw_si_path( (string) ( $r[0][2] ?? '' ) ) : '';
			$vals[] = array( $id, $page, $key, $r[1], $r[2], $r[3] );
		}
	}
	// Two queries that normalise to the same record on the same day and page are added together.
	$merged = array();
	foreach ( $vals as $v ) {
		$key = $v[0] . '|' . $v[1] . '|' . $v[2];
		if ( ! isset( $merged[ $key ] ) ) {
			$merged[ $key ] = $v;
			continue;
		}
		$m              = $merged[ $key ];
		$impr           = $m[4] + $v[4];
		$merged[ $key ] = array( $m[0], $m[1], $m[2], $m[3] + $v[3], $impr, $impr ? ( $m[5] * $m[4] + $v[5] * $v[4] ) / $impr : 0 );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = 'gsc' AND period = 'day' AND d = %s", $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array_chunk( array_values( $merged ), 300 ) as $chunk ) {
		$place = array();
		$args  = array();
		foreach ( $chunk as $v ) {
			$place[] = "(%d, %s, %s, 'gsc', %s, 'day', %s, %d, %d, %f, %s)";
			array_push( $args, $v[0], mb_substr( $v[1], 0, 255 ), '' === $v[1] ? '' : md5( $v[1] ), $v[2], $day, $v[3], $v[4], round( $v[5], 2 ), $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one placeholder group per row, built above.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$o} (kw_id, page, page_key, src, metric, period, d, clicks, impr, pos, fetched) VALUES " . implode( ', ', $place ), $args ) );
	}
	return count( $merged );
}

/**
 * Days to fetch this run: the last 4 days, then up to 15 older days still missing within 90 days.
 *
 * @param DateTimeImmutable|null $today   Today (site time zone); for tests.
 * @param int                    $per_run Older days per run.
 */
function aw_si_gsc_days( ?DateTimeImmutable $today = null, int $per_run = AW_SI_GSC_PER_RUN ): array {
	$today = $today ?? new DateTimeImmutable( 'today', wp_timezone() );
	$days  = array();
	for ( $i = 1; $i <= AW_SI_GSC_REFETCH; $i++ ) {
		$days[] = $today->modify( "-{$i} days" )->format( 'Y-m-d' );
	}
	$next  = (string) get_option( 'aw_si_backfill', '' ); // The next older day to backfill.
	$floor = $today->modify( '-' . AW_SI_GSC_BACKFILL . ' days' )->format( 'Y-m-d' );
	$from  = $next && $next < end( $days ) ? $next : $today->modify( '-' . ( AW_SI_GSC_REFETCH + 1 ) . ' days' )->format( 'Y-m-d' );
	$d     = new DateTimeImmutable( $from, wp_timezone() );
	for ( $i = 0; $i < $per_run; $i++ ) {
		$day = $d->format( 'Y-m-d' );
		if ( $day < $floor ) {
			break;
		}
		$days[] = $day;
		$d      = $d->modify( '-1 day' );
	}
	return $days;
}

/**
 * The daily Search Console run. Never throws; failures go to the run log and the stored data stays.
 *
 * @param array $args days (override the day list), dry (fetch only: nothing stored).
 * @return array Report: days, rows, truncated, error, per-day row counts.
 */
function aw_si_gsc_run( array $args = array() ): array {
	@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$days   = $args['days'] ?? aw_si_gsc_days();
	$dry    = ! empty( $args['dry'] );
	$report = aw_si_with_module(
		'search-console',
		function ( $module ) use ( $days, $dry ) {
			$rep   = array(
				'days'      => array(),
				'rows'      => 0,
				'truncated' => array(),
				'error'     => '',
			);
			$start = microtime( true );
			foreach ( $days as $i => $day ) {
				if ( $i >= AW_SI_GSC_REFETCH && microtime( true ) - $start > AW_SI_GSC_BUDGET ) {
					$rep['stopped'] = $day; // The backfill goes on from here next run.
					break;
				}
				$data = aw_si_gsc_fetch_day( $module, $day );
				if ( is_wp_error( $data ) ) {
					/* translators: 1: date, 2: error message. */
					$rep['error'] = sprintf( __( 'Search Console could not be read for %1$s: %2$s', 'aw-seo-extras' ), $day, $data->get_error_message() );
					break;
				}
				if ( $data['truncated'] ) {
					$rep['truncated'][ $day ] = $data['truncated'];
				}
				$n                   = $dry ? count( $data['q'] ) + count( $data['qp'] ) + count( $data['total'] ) : aw_si_gsc_store_day( $day, $data );
				$rep['days'][ $day ] = array(
					'q'     => count( $data['q'] ),
					'qp'    => count( $data['qp'] ),
					'p'     => count( $data['p'] ),
					'impr'  => (int) array_sum( array_column( $data['total'], 2 ) ),
					'named' => (int) array_sum( array_column( $data['q'], 2 ) ),
				);
				$rep['rows']        += $n;
				if ( ! $dry && $i >= AW_SI_GSC_REFETCH ) {
					// Save the backfill position after each older day, so a run cut short still moves on.
					update_option( 'aw_si_backfill', ( new DateTimeImmutable( $day, wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' ), false );
				}
				if ( $dry ) {
					$rep['sample'][ $day ] = array_slice( $data['q'], 0, 5 );
				}
			}
			return $rep;
		}
	);
	if ( is_wp_error( $report ) ) {
		if ( ! $dry ) {
			aw_si_run_fail( 'gsc', $report->get_error_message() );
		}
		return array( 'error' => $report->get_error_message() );
	}
	if ( ! $dry ) {
		$done = array_keys( $report['days'] );
		if ( $report['error'] ) {
			aw_si_run_fail( 'gsc', $report['error'] );
		} else {
			aw_si_run_ok(
				'gsc',
				array(
					'days'      => count( $done ),
					'rows'      => $report['rows'],
					'truncated' => $report['truncated'],
					'up_to'     => $done ? max( $done ) : '',
				)
			);
		}
	}
	return $report;
}

/**
 * Search Console figures per page path over the last $days days (ending yesterday), summed from the daily page rows
 * (metric p, so searches Google hides are included). For other plugins, e.g. AW Sourcing's per-keyword view.
 *
 * @param string[] $paths Site paths such as /news/x/; empty for every page.
 * @param int      $days  Window length.
 * @return array<string, array{clicks: int, impr: int, pos: float|null}> path => figures.
 */
function aw_si_page_metrics( array $paths = array(), int $days = 28 ): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$end  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' );
	$args = array( $end->modify( '-' . ( max( 1, $days ) - 1 ) . ' days' )->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
	$in   = '';
	if ( $paths ) {
		$keys = array_map( 'md5', array_values( array_unique( array_map( 'strval', $paths ) ) ) );
		$in   = ' AND page_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')';
		$args = array_merge( $args, $keys );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per path.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM {$o} WHERE src = 'gsc' AND metric = 'p' AND period = 'day' AND d BETWEEN %s AND %s{$in} GROUP BY page", $args ) );
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[ (string) $r->page ] = array(
			'clicks' => (int) $r->c,
			'impr'   => (int) $r->i,
			'pos'    => null === $r->p ? null : round( (float) $r->p, 1 ),
		);
	}
	return $out;
}
