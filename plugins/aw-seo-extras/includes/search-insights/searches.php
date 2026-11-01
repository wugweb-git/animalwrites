<?php
/**
 * Content gaps for the Overview box (the old Search demand screen's Gaps list, removed with that screen on 25 Oct 2026).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content gaps: the keyword map's demand gaps (seeded with their note) and every keyword the daily
 * calculation marks as a gap (high demand, no page: P2), as [title, detail] rows.
 *
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_searches_gaps(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k = aw_si_table( 'keywords' );
	// A keyword-map gap note stays on the record; the gap is shown only while no page carries the search and none of
	// our pages ranks in the top 20 for it.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows   = $wpdb->get_results( "SELECT label, note, status, action, impr28, pos28 FROM {$k} WHERE status = 'gap' OR ( note LIKE 'Gap%' AND COALESCE(status, '') NOT IN ('has-page', 'conflict') AND NOT ( COALESCE(page, '') <> '' AND pos28 IS NOT NULL AND pos28 <= 20 ) ) ORDER BY impr28 DESC, label" );
	$groups = array();
	foreach ( (array) $rows as $r ) {
		$title = 0 === strpos( (string) $r->note, 'Gap' ) ? trim( (string) preg_replace( '/^Gap \([^)]*\):\s*/', '', (string) $r->note ) ) : __( 'High demand, no page yet (P2)', 'aw-seo-extras' );
		/* translators: 1: search phrase, 2: impressions in 28 days, 3: average position. */
		$line = null === $r->pos28 ? sprintf( __( '"%1$s": %2$s impressions (28 days)', 'aw-seo-extras' ), $r->label, number_format_i18n( (int) $r->impr28 ) ) : sprintf( __( '"%1$s": %2$s impressions (28 days), position %3$s', 'aw-seo-extras' ), $r->label, number_format_i18n( (int) $r->impr28 ), number_format_i18n( (float) $r->pos28, 1 ) );
		if ( 'gap' === $r->status && isset( aw_si_actions()[ $r->action ] ) ) {
			/* translators: 1: gap line, 2: suggested page type such as "New page: guide". */
			$line = sprintf( __( '%1$s (suggested: %2$s)', 'aw-seo-extras' ), $line, aw_si_actions()[ $r->action ] );
		}
		$groups[ $title ][] = $line;
	}
	$out = array();
	foreach ( $groups as $title => $lines ) {
		$out[] = array( (string) $title, implode( '; ', $lines ) . '.' );
	}
	return $out;
}
