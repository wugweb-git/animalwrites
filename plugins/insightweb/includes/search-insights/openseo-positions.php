<?php
/**
 * OpenSEO positions import (12 Nov 2026): Google positions read through OpenSEO's live results, sent as a CSV on
 * Search Insights → Settings → Imports.
 *
 * - The rows are stored as their own source (src openseo_serp), never as the browser checks (src live): OpenSEO's
 *   reads are not like-for-like with the bookmarklet's, so priority, the Check list and the Live check column keep
 *   reading only the browser checks. The Keywords tab shows the OpenSEO position in its own column.
 * - Columns: date, query (or keyword), position; optional url, top3, paa, source. When there is a source column, only
 *   rows whose source names OpenSEO are stored, so a file that also holds browser checks can be sent as it is.
 * - Only our keywords are stored; other searches are skipped. Position: a number is stored as the value, anything else
 *   (">9", "not found") as text. Stored as month rows with the day of the read (as the browser checks are), so the rollup leaves them; sending the same day again replaces it.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_OPENSEO_POS = 'openseo_serp';

add_filter(
	'aw_si_import_kinds',
	function ( $kinds ) {
		$kinds[ AW_SI_OPENSEO_POS ] = __( 'OpenSEO positions (CSV: date, query, position; optional: url, top3, paa, source). Kept apart from the browser checks.', 'aw-seo-extras' );
		return $kinds;
	}
);

add_filter(
	'aw_si_import_run',
	function ( $out, string $kind, string $raw ) {
		return AW_SI_OPENSEO_POS === $kind ? aw_si_openseo_pos_import( $raw ) : $out;
	},
	10,
	3
);

/**
 * Store one OpenSEO positions file.
 *
 * @param string $raw File or pasted text: a header row, then one row per search.
 * @return array{rows: int, skipped: int, error: string, other: int}
 */
function aw_si_openseo_pos_import( string $raw ): array {
	$out                 = array(
		'rows'    => 0,
		'skipped' => 0,
		'other'   => 0,
		'error'   => '',
	);
	list( $head, $rows ) = aw_si_parse_table( $raw, 'position' );
	$qcol                = $head ? (string) current( array_intersect( $head, array( 'query', 'keyword', 'search', 'term' ) ) ) : '';
	$pcol                = $head ? (string) current( array_intersect( $head, array( 'position', 'rank' ) ) ) : '';
	if ( '' === $qcol || '' === $pcol ) {
		$out['error'] = __( 'The rows need a header with a query column and a position column.', 'aw-seo-extras' );
		return $out;
	}
	$col   = fn( array $names ) => (string) current( array_intersect( $head, $names ) );
	$dcol  = $col( array( 'date', 'day' ) );
	$scol  = $col( array( 'source' ) );
	$extra = array(
		'url'  => $col( array( 'url' ) ),
		'top3' => $col( array( 'top3', 'top 3' ) ),
		'paa'  => $col( array( 'paa', 'people also ask' ) ),
	);
	$map   = aw_si_kw_map(); // Our keywords only: an OpenSEO search never becomes a keyword record by itself.
	$today = wp_date( 'Y-m-d' );
	foreach ( $rows as $r ) {
		if ( '' !== $scol && false === stripos( (string) $r[ $scol ], 'openseo' ) ) {
			++$out['other']; // A browser check or another source in the same file: not stored here.
			continue;
		}
		$id  = (int) ( $map[ aw_si_norm( sanitize_text_field( (string) $r[ $qcol ] ) ) ] ?? 0 );
		$pos = trim( sanitize_text_field( (string) $r[ $pcol ] ) );
		$day = $today;
		if ( '' !== $dcol && '' !== trim( (string) $r[ $dcol ] ) ) {
			$ts  = strtotime( trim( (string) $r[ $dcol ] ) );
			$day = $ts ? gmdate( 'Y-m-d', $ts ) : '';
		}
		if ( ! $id || '' === $pos || '' === $day ) {
			++$out['skipped'];
			continue;
		}
		$ok = aw_si_obs_put(
			$id,
			'',
			AW_SI_OPENSEO_POS,
			'position',
			'month',
			$day,
			array(
				'value' => is_numeric( $pos ) ? (float) $pos : null,
				'txt'   => mb_substr( $pos, 0, 16 ),
			)
		);
		foreach ( $extra as $metric => $c ) {
			$v = '' !== $c ? trim( sanitize_text_field( (string) $r[ $c ] ) ) : '';
			if ( '' !== $v ) {
				$ok = aw_si_obs_put( $id, '', AW_SI_OPENSEO_POS, $metric, 'month', $day, array( 'txt' => $v ) ) && $ok;
			}
		}
		$ok ? ++$out['rows'] : ++$out['skipped'];
	}
	return $out;
}

/**
 * The latest OpenSEO position of each keyword, for the Keywords tab.
 *
 * @param int[] $ids Keyword IDs.
 * @return array<int, object> id => row (value, txt, d).
 */
function aw_si_openseo_positions( array $ids ): array {
	global $wpdb;
	$ids = array_filter( array_map( 'intval', $ids ) );
	if ( ! $ids || ! aw_si_ready() ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$in   = implode( ',', $ids );
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, value, txt, d FROM {$o} WHERE src = %s AND metric = 'position' AND kw_id IN ({$in}) ORDER BY d", AW_SI_OPENSEO_POS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers and a table name.
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r->kw_id ] = $r; // Ordered by day: the latest wins.
	}
	return $out;
}
