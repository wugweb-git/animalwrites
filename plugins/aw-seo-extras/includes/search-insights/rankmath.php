<?php
/**
 * What Search Insights reads from Rank Math (read only; nothing is written to Rank Math):
 * - the SEO score of each published item (post meta rank_math_seo_score), for the Pages tab;
 * - Rank Math Analytics' Search Console rows (table rank_math_analytics_gsc, one row per day, query and page), as a
 *   backup for the daily Search Console run when Site Kit can't be used. Rank Math keeps only positions up to its
 *   position filter and rounds positions, and leaves out pages with a query string, so Site Kit stays the first choice.
 * Focus keyphrases and redirections were already read (keywords.php, sources.php).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_RM_LOW = 50; // A Rank Math SEO score under this is "low" on the Pages tab.

/**
 * Rank Math SEO score per site path, for published items that have one. Cached for 12 hours.
 *
 * @return array<string, int> path => score (0–100).
 */
function aw_si_rankmath_scores(): array {
	$cached = get_transient( 'aw_si_rm_scores' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read only, cached below.
	$rows = (array) $wpdb->get_results( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_status = 'publish' WHERE m.meta_key = 'rank_math_seo_score' AND m.meta_value <> ''" );
	_prime_post_caches( array_map( 'intval', wp_list_pluck( $rows, 'post_id' ) ), false, false );
	$out = array();
	foreach ( $rows as $r ) {
		$path = aw_si_object_path( 'post', (int) $r->post_id );
		if ( '' !== $path && is_numeric( $r->meta_value ) ) {
			$out[ $path ] = max( 0, min( 100, (int) $r->meta_value ) );
		}
	}
	set_transient( 'aw_si_rm_scores', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/** Whether Rank Math Analytics has its Search Console table. */
function aw_si_rankmath_gsc_table(): string {
	global $wpdb;
	$t = $wpdb->prefix . 'rank_math_analytics_gsc';
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ? $t : ''; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * One day of Search Console from Rank Math Analytics, in the shape aw_si_gsc_fetch_day() returns.
 *
 * @param string $day Date (Y-m-d).
 * @return array|WP_Error array{total: array, q: array, qp: array, p: array, truncated: array}
 */
function aw_si_rankmath_gsc_day( string $day ) {
	global $wpdb;
	$t = aw_si_rankmath_gsc_table();
	if ( '' === $t ) {
		return new WP_Error( 'aw_si_rm', __( 'Rank Math Analytics is not connected (no rank_math_analytics_gsc table).', 'aw-seo-extras' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT query, page, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) / NULLIF(SUM(impressions), 0) p FROM {$t} WHERE created >= %s AND created < %s GROUP BY query, page", $day . ' 00:00:00', gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) ) . ' 00:00:00' ) );
	if ( ! $rows ) {
		/* translators: %s: date. */
		return new WP_Error( 'aw_si_rm', sprintf( __( 'Rank Math Analytics has no Search Console rows for %s.', 'aw-seo-extras' ), $day ) );
	}
	$out = array(
		'total'     => array(),
		'q'         => array(),
		'qp'        => array(),
		'p'         => array(),
		'truncated' => array(),
	);
	$agg = array(
		'q' => array(),
		'p' => array(),
	);
	$sum = array( 0, 0, 0.0 );
	foreach ( $rows as $r ) {
		$c           = (int) $r->c;
		$i           = (int) $r->i;
		$pos         = (float) $r->p;
		$url         = home_url( (string) $r->page );
		$out['qp'][] = array( array( $day, (string) $r->query, $url ), $c, $i, $pos );
		foreach ( array(
			'q' => (string) $r->query,
			'p' => $url,
		) as $kind => $key ) {
			$v                    = $agg[ $kind ][ $key ] ?? array( 0, 0, 0.0 );
			$agg[ $kind ][ $key ] = array( $v[0] + $c, $v[1] + $i, $v[2] + $pos * $i );
		}
		$sum = array( $sum[0] + $c, $sum[1] + $i, $sum[2] + $pos * $i );
	}
	foreach ( $agg as $kind => $bag ) {
		foreach ( $bag as $key => $v ) {
			$out[ $kind ][] = array( array( $day, $key ), $v[0], $v[1], $v[1] ? $v[2] / $v[1] : 0 );
		}
	}
	// The site total is the sum of the query rows: searches Google hides are not in Rank Math's table.
	$out['total'][] = array( array( $day ), $sum[0], $sum[1], $sum[1] ? $sum[2] / $sum[1] : 0 );
	return $out;
}

/**
 * The daily Search Console run from Rank Math Analytics, used when Site Kit can't be used. Logged as a good run with
 * the Site Kit problem and "via Rank Math" in its detail, so the screen says where the figures came from.
 *
 * @param string[] $days    Days to read.
 * @param bool     $dry     Read only.
 * @param string   $sitekit Why Site Kit could not be used.
 * @return array|null The run report, or null when Rank Math has none of the days (the caller then logs the failure).
 */
function aw_si_rankmath_gsc_run( array $days, bool $dry, string $sitekit ): ?array {
	if ( '' === aw_si_rankmath_gsc_table() ) {
		return null;
	}
	$rep = array(
		'days'    => array(),
		'rows'    => 0,
		'error'   => '',
		'via'     => 'rank-math',
		'sitekit' => $sitekit,
	);
	foreach ( $days as $day ) {
		$data = aw_si_rankmath_gsc_day( $day );
		if ( is_wp_error( $data ) ) {
			continue; // Rank Math may not have every day; the others are still stored.
		}
		$n = $dry ? count( $data['qp'] ) : aw_si_gsc_store_day( $day, $data );
		if ( is_wp_error( $n ) ) {
			$rep['error'] = $n->get_error_message();
			break;
		}
		$rep['days'][ $day ] = array(
			'q'    => count( $data['q'] ),
			'qp'   => count( $data['qp'] ),
			'p'    => count( $data['p'] ),
			'impr' => (int) array_sum( array_column( $data['total'], 2 ) ),
		);
		$rep['rows']        += $n;
	}
	if ( ! $rep['days'] && '' === $rep['error'] ) {
		return null;
	}
	if ( ! $dry ) {
		if ( '' !== $rep['error'] ) {
			aw_si_run_fail( 'gsc', $rep['error'] );
		} else {
			aw_si_run_ok(
				'gsc',
				array(
					'days'  => count( $rep['days'] ),
					'rows'  => $rep['rows'],
					'up_to' => max( array_keys( $rep['days'] ) ),
					/* translators: %s: why Site Kit could not be used. */
					'via'   => sprintf( __( 'Rank Math Analytics (Site Kit: %s)', 'aw-seo-extras' ), $sitekit ),
				)
			);
		}
	}
	return $rep;
}
