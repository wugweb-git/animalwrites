<?php
/**
 * Search Console through Site Kit, stored by calendar day (plan §5.1).
 *
 * - One request per day and dimension set: date (site total), date + query, date + query + page. Each stays under the
 *   5,000-row limit; a day that still returns 5,000 rows is marked truncated in the run log and on the screen.
 * - The last 4 days are fetched again every morning (Google finalises data 2–3 days late) and replace what was stored.
 * - A backfill walks back to 90 days, 15 days per run, so 28-day comparisons work from the first week.
 * - Fetch first, then replace: a failed day keeps its stored rows.
 * - Over 5,000 rows: Site Kit does not pass Google's startRow, so query + page rows of such a day are fetched again
 *   page by page (its only filter), for up to 50 pages with the most impressions. The day stays marked truncated only
 *   while pages with impressions are left over, or a single page still returns 5,000 rows. The query-only list (q)
 *   cannot be split this way; its missing tail is the rarest searches, and their records still come from the split.
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
const AW_SI_GSC_SPLIT    = 50; // Pages fetched one by one on a day whose query + page rows hit the limit.

/**
 * One searchanalytics request for one day: aw_sd_query() of search-demand.php, for a single date.
 *
 * @param object $module Site Kit's Search Console module.
 * @param array  $dims   Dimensions, e.g. date + query.
 * @param string $day    Date (Y-m-d).
 * @param string $url    Only this page (full URL), or '' for the whole site.
 * @return array|WP_Error Rows as [keys, clicks, impressions, position].
 */
function aw_si_gsc_rows( $module, array $dims, string $day, string $url = '' ) {
	return aw_sd_query( $module, $dims, $day, $day, $url );
}

/**
 * Query + page rows of a day over the row limit, fetched again page by page for the pages with the most impressions.
 *
 * @param object $module Site Kit's Search Console module.
 * @param string $day    Date (Y-m-d).
 * @param array  $qp     The truncated query + page rows.
 * @param array  $pages  The day's page rows (date + page), for the page list and its order.
 * @param float  $until  Stop starting new page calls after this time (microtime), 0 for no limit.
 * @return array|WP_Error array{rows: array, pages: int, left: int, full: bool} rows replaces $qp; left counts pages with
 *                        impressions not fetched one by one; full is true when a single page also hit the limit.
 */
function aw_si_gsc_split_qp( $module, string $day, array $qp, array $pages, float $until = 0 ) {
	usort( $pages, fn( $a, $b ) => $b[2] <=> $a[2] );
	$pages = array_values( array_filter( $pages, fn( $r ) => $r[2] > 0 && '' !== (string) ( $r[0][1] ?? '' ) ) );
	$split = array();
	$rows  = array();
	$full  = false;
	foreach ( array_slice( $pages, 0, AW_SI_GSC_SPLIT ) as $p ) {
		if ( $until && microtime( true ) > $until ) {
			break; // Out of time: the pages left keep the first request's rows, and the day stays marked truncated.
		}
		$url = (string) $p[0][1];
		$got = aw_si_gsc_rows( $module, array( 'date', 'query' ), $day, $url );
		if ( is_wp_error( $got ) ) {
			return $got;
		}
		$full          = $full || count( $got ) >= AW_SI_GSC_LIMIT;
		$split[ $url ] = true;
		foreach ( $got as $r ) {
			$rows[] = array( array( $r[0][0] ?? $day, (string) ( $r[0][1] ?? '' ), $url ), $r[1], $r[2], $r[3] );
		}
	}
	foreach ( $qp as $r ) {
		if ( ! isset( $split[ (string) ( $r[0][2] ?? '' ) ] ) ) {
			$rows[] = $r; // Pages not fetched one by one keep the rows of the first request.
		}
	}
	return array(
		'rows'  => $rows,
		'pages' => count( $split ),
		'left'  => max( 0, count( $pages ) - count( $split ) ),
		'full'  => $full,
	);
}

/**
 * Fetch one day: site total, queries, queries by page.
 *
 * @param object $module Site Kit's Search Console module.
 * @param string $day    Date (Y-m-d).
 * @param float  $until  Time (microtime) after which no more per-page calls start; 0 for no limit.
 * @return array|WP_Error array{total: array, q: array, qp: array, truncated: string[]}
 */
function aw_si_gsc_fetch_day( $module, string $day, float $until = 0 ) {
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
	if ( in_array( 'qp', $out['truncated'], true ) ) {
		$split = aw_si_gsc_split_qp( $module, $day, $out['qp'], $out['p'], $until );
		if ( is_wp_error( $split ) ) {
			return $split;
		}
		$out['qp']    = $split['rows'];
		$out['split'] = $split['pages'];
		if ( ! $split['left'] && ! $split['full'] ) {
			$out['truncated'] = array_values( array_diff( $out['truncated'], array( 'qp' ) ) );
		}
	}
	return $out;
}

/**
 * Replace the stored rows of one day with $data. Queries become keyword records (role Searched).
 *
 * @param string $day  Date (Y-m-d).
 * @param array  $data What aw_si_gsc_fetch_day() returned.
 * The delete and the inserts run in one transaction: if any write fails, the day's earlier rows stay.
 *
 * @return int|WP_Error Rows written, or why the day was not replaced.
 */
function aw_si_gsc_store_day( string $day, array $data ) {
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
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ok = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = 'gsc' AND period = 'day' AND d = %s", $day ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	foreach ( array_chunk( array_values( $merged ), 300 ) as $chunk ) {
		$place = array();
		$args  = array();
		foreach ( $chunk as $v ) {
			$place[] = "(%d, %s, %s, 'gsc', %s, 'day', %s, %d, %d, %f, %s)";
			array_push( $args, $v[0], mb_substr( $v[1], 0, 255 ), '' === $v[1] ? '' : md5( $v[1] ), $v[2], $day, $v[3], $v[4], round( $v[5], 2 ), $now );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one placeholder group per row, built above.
		$ok = $ok && false !== $wpdb->query( $wpdb->prepare( "INSERT INTO {$o} (kw_id, page, page_key, src, metric, period, d, clicks, impr, pos, fetched) VALUES " . implode( ', ', $place ), $args ) );
		if ( ! $ok ) {
			break;
		}
	}
	if ( ! $ok ) {
		$error = $wpdb->last_error;
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		/* translators: 1: date, 2: database error. */
		return new WP_Error( 'aw_si_store', sprintf( __( 'Search Console rows for %1$s could not be saved, so the earlier rows were kept: %2$s', 'aw-seo-extras' ), $day, $error ) );
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
				$data = aw_si_gsc_fetch_day( $module, $day, $start + AW_SI_GSC_BUDGET );
				if ( is_wp_error( $data ) ) {
					/* translators: 1: date, 2: error message. */
					$rep['error'] = sprintf( __( 'Search Console could not be read for %1$s: %2$s', 'aw-seo-extras' ), $day, $data->get_error_message() );
					break;
				}
				if ( $data['truncated'] ) {
					$rep['truncated'][ $day ] = $data['truncated'];
				}
				if ( ! empty( $data['split'] ) ) {
					$rep['split'][ $day ] = $data['split'];
				}
				$n = $dry ? count( $data['q'] ) + count( $data['qp'] ) + count( $data['total'] ) : aw_si_gsc_store_day( $day, $data );
				if ( is_wp_error( $n ) ) {
					$rep['error'] = $n->get_error_message();
					break;
				}
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
		// Site Kit can't be used: read the same days from Rank Math Analytics when it has them (rankmath.php).
		$backup = aw_si_rankmath_gsc_run( $days, $dry, $report->get_error_message() );
		if ( null !== $backup ) {
			return $backup;
		}
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
					'split'     => $report['split'] ?? array(),
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
