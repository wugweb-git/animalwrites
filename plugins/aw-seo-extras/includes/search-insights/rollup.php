<?php
/**
 * Retention (plan §3): daily rows older than 90 days (setting AW_SI_KEEP_DAYS) become one monthly row per keyword,
 * page, source and metric, then the daily rows are deleted. Clarity's daily rows (clarity, clarity-dev) are kept at
 * least AW_SI_CLARITY_KEEP (35) days even when the setting is lower. Monthly rows are kept indefinitely.
 *
 * Only period = day rows are rolled up, so sums never add overlapping windows: clicks and impressions are summed,
 * position is the mean weighted by impressions, and value is summed for counts or kept as the month's last value for
 * indexes (aw_si_metric_kind). A month rolled up over several runs is merged into the same monthly row.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// AW_SI_KEEP_DAYS: AW SEO Plus → Settings (settings.php).

/**
 * How a metric's value rolls up: sum (counts) or last (indexes, positions, text).
 *
 * @param string $src    Source.
 * @param string $metric Metric.
 */
function aw_si_metric_kind( string $src, string $metric ): string {
	$last = (array) apply_filters( 'aw_si_last_value_metrics', array( 'trends:index', 'live:position', 'psi:score', 'clarity:scroll' ) );
	return in_array( $src . ':' . $metric, $last, true ) ? 'last' : 'sum';
}

/**
 * Roll up one batch of old daily rows. Runs daily; a large backlog is worked through over several days.
 *
 * @param bool $dry   Count only.
 * @param int  $limit Daily rows per run.
 * @return array Counts: daily rows rolled up, monthly rows written; or error when nothing was changed.
 */
function aw_si_rollup( bool $dry = false, int $limit = 50000 ): array {
	global $wpdb;
	$o      = aw_si_table( 'obs' );
	$today  = new DateTimeImmutable( 'today', wp_timezone() );
	$cutoff = $today->modify( '-' . AW_SI_KEEP_DAYS . ' days' )->format( 'Y-m-d' );
	// Clarity's daily rows stay at least AW_SI_CLARITY_KEEP days (its 28-day views), whatever the setting says.
	$c_keep = max( (int) AW_SI_KEEP_DAYS, defined( 'AW_SI_CLARITY_KEEP' ) ? (int) AW_SI_CLARITY_KEEP : 35 );
	$c_cut  = $today->modify( '-' . $c_keep . ' days' )->format( 'Y-m-d' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, kw_id, page, page_key, src, metric, d, clicks, impr, pos, value, txt FROM {$o} WHERE period = 'day' AND d < %s AND ( src NOT IN ('clarity', 'clarity-dev') OR d < %s ) ORDER BY d LIMIT %d", $cutoff, $c_cut, $limit ) );
	if ( $dry || ! $rows ) {
		return array(
			'daily'   => count( (array) $rows ),
			'monthly' => 0,
			'cutoff'  => $cutoff,
		);
	}
	$groups = array();
	foreach ( $rows as $r ) {
		$month = substr( $r->d, 0, 7 ) . '-01';
		$key   = implode( '|', array( $r->src, $r->metric, $month, $r->kw_id, $r->page_key ) );
		$g     = $groups[ $key ] ?? array(
			'kw_id'    => (int) $r->kw_id,
			'page'     => $r->page,
			'page_key' => $r->page_key,
			'src'      => $r->src,
			'metric'   => $r->metric,
			'd'        => $month,
			'clicks'   => null,
			'impr'     => null,
			'posw'     => 0.0,
			'value'    => null,
			'last_d'   => '',
			'txt'      => '',
		);
		if ( null !== $r->impr ) {
			$g['clicks'] = (int) $g['clicks'] + (int) $r->clicks;
			$g['impr']   = (int) $g['impr'] + (int) $r->impr;
			$g['posw']  += (float) $r->pos * (int) $r->impr;
		}
		if ( null !== $r->value ) {
			if ( 'last' === aw_si_metric_kind( $r->src, $r->metric ) ) {
				if ( $r->d >= $g['last_d'] ) {
					$g['value']  = (float) $r->value;
					$g['last_d'] = $r->d;
					$g['txt']    = $r->txt;
				}
			} else {
				$g['value'] = (float) $g['value'] + (float) $r->value;
				if ( '' === $g['txt'] ) {
					$g['txt'] = (string) $r->txt; // The group's source or term: rows keyed by it keep it in the month.
				}
			}
		}
		$groups[ $key ] = $g;
	}
	// The monthly writes and the delete of the daily rows succeed or fail together: daily rows are only removed once
	// every monthly row they feed is stored.
	$written = 0;
	$ok      = false !== $wpdb->query( 'START TRANSACTION' );
	foreach ( $groups as $g ) {
		if ( ! $ok ) {
			break;
		}
		// Merge with a monthly row written by an earlier run for the same month.
		$old    = $wpdb->get_row( $wpdb->prepare( "SELECT clicks, impr, pos, value FROM {$o} WHERE src = %s AND metric = %s AND period = 'month' AND d = %s AND kw_id = %d AND page_key = %s", $g['src'], $g['metric'], $g['d'], $g['kw_id'], $g['page_key'] ) );
		$impr   = $g['impr'];
		$clicks = $g['clicks'];
		$posw   = $g['posw'];
		$value  = $g['value'];
		if ( $old ) {
			if ( null !== $old->impr ) {
				$impr   = (int) $impr + (int) $old->impr;
				$clicks = (int) $clicks + (int) $old->clicks;
				$posw  += (float) $old->pos * (int) $old->impr;
			}
			if ( null !== $old->value && 'sum' === aw_si_metric_kind( $g['src'], $g['metric'] ) ) {
				$value = (float) $value + (float) $old->value;
			} elseif ( null === $value ) {
				$value = null === $old->value ? null : (float) $old->value;
			}
		}
		$ok = aw_si_obs_put(
			$g['kw_id'],
			$g['page'],
			$g['src'],
			$g['metric'],
			'month',
			$g['d'],
			array(
				'clicks' => $clicks,
				'impr'   => $impr,
				'pos'    => $impr ? round( $posw / $impr, 2 ) : null,
				'value'    => $value,
				'txt'      => $g['txt'],
				'page_key' => $g['page_key'],
			)
		);
		++$written;
	}
	foreach ( array_chunk( array_map( 'intval', wp_list_pluck( $rows, 'id' ) ), 1000 ) as $chunk ) {
		$ok = $ok && false !== $wpdb->query( "DELETE FROM {$o} WHERE id IN (" . implode( ',', $chunk ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers.
	}
	if ( ! $ok ) {
		$error = $wpdb->last_error;
		$wpdb->query( 'ROLLBACK' );
		/* translators: %s: database error. */
		return array( 'error' => sprintf( __( 'The rollup could not save the monthly rows, so the daily rows were kept: %s', 'aw-seo-extras' ), $error ) );
	}
	$wpdb->query( 'COMMIT' );
	// phpcs:enable
	return array(
		'daily'   => count( $rows ),
		'monthly' => $written,
		'cutoff'  => $cutoff,
	);
}
