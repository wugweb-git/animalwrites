<?php
/**
 * The daily recompute: read the 28-day Search Console figures from the obs table, the previous period, the page shown
 * most, the latest live check and the trends index, work each keyword through the decision engine (compute.php) and
 * copy the result onto the keyword record, so the lists sort and filter without aggregating on every view. Suggestions
 * only — nothing on the site is changed.
 *
 * Generalised from the Animal Writes edition: the redirect-resolution (`final_path`) and the News/tag/category "minor
 * path" handling are dropped (#6) — a path is used as-is and every owner counts. One prefix: insightweb_.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the columns to store for one keyword from its figures and owners (pure — the testable heart of the recompute).
 *
 * @param int                        $impr    Impressions, 28 days.
 * @param int                        $clicks  Clicks, 28 days.
 * @param float|null                 $pos28   Position, 28 days.
 * @param float|null                 $posprev Position, the previous 28 days.
 * @param float|null                 $live    Latest live-check position.
 * @param int|null                   $trends  Trends index.
 * @param string[]                   $owners  Owning page paths.
 * @param bool                       $has_page Some page owns or carries it.
 * @param array{0?: string, 1?: int} $top [path, impressions] of the page shown most.
 * @param array<string, mixed>       $ctx     label, quality, main, shown_minor, live_txt, live_date.
 * @return array<string, mixed> Columns for insightweb_kw_set_figures().
 */
function insightweb_compute_row( int $impr, int $clicks, ?float $pos28, ?float $posprev, ?float $live, ?int $trends, array $owners, bool $has_page, array $top, array $ctx ): array {
	$d    = insightweb_decide( $trends, $impr, $pos28, $live, $owners, $has_page, $top, $ctx );
	$page = $d['owners'] ? $d['owners'][0] : $d['shown'];
	return array(
		'priority'  => $d['priority'],
		'status'    => $d['status'],
		'action'    => $d['action'],
		'page'      => mb_substr( (string) $page, 0, 255 ),
		'impr28'    => $impr,
		'clicks28'  => $clicks,
		'pos28'     => $pos28,
		'pos_prev'  => $posprev,
		'live_pos'  => $live,
		'live_txt'  => mb_substr( (string) ( $ctx['live_txt'] ?? '' ), 0, 16 ),
		'live_date' => $ctx['live_date'] ?? null,
		'trends'    => $trends,
		'quick_win' => insightweb_quick_win( $d['pos'], $impr ) ? 1 : 0,
	);
}

/**
 * Whether the stored column differs enough from the new value to be worth writing (floats compared as numbers).
 *
 * @param mixed $old   Stored value.
 * @param mixed $fresh New value.
 * @return bool
 */
function insightweb_compute_changed( $old, $fresh ): bool {
	if ( null === $fresh ) {
		return null !== $old;
	}
	if ( null === $old ) {
		return true;
	}
	if ( is_numeric( $fresh ) && is_numeric( $old ) ) {
		return abs( (float) $old - (float) $fresh ) >= 0.05;
	}
	return (string) $old !== (string) $fresh;
}

/**
 * Recalculate every keyword record from the obs table. Returns counts by priority and status.
 *
 * @return array<string, int>
 */
function insightweb_compute(): array {
	global $wpdb;
	if ( ! insightweb_ready() || ! insightweb_kw_ready() ) {
		return array();
	}
	$k   = insightweb_kw_table( 'keywords' );
	$o   = insightweb_kw_table( 'obs' );
	$p   = insightweb_kw_table( 'kw_pages' );
	$tz  = wp_timezone();
	$end = ( new DateTimeImmutable( 'today', $tz ) )->modify( '-1 day' );
	$s28 = $end->modify( '-27 days' )->format( 'Y-m-d' );
	$s56 = $end->modify( '-55 days' )->format( 'Y-m-d' );
	$e56 = $end->modify( '-28 days' )->format( 'Y-m-d' );
	$e28 = $end->format( 'Y-m-d' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names via %i; fixed conditions.
	$now28 = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM %i WHERE src = 'gsc' AND metric = 'q' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id", $o, $s28, $e28 ), OBJECT_K );
	$prev  = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM %i WHERE src = 'gsc' AND metric = 'q' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id", $o, $s56, $e56 ), OBJECT_K );
	$top   = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, page, SUM(impr) i FROM %i WHERE src = 'gsc' AND metric = 'qp' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id, page ORDER BY kw_id, i DESC", $o, $s28, $e28 ) );
	$live  = $wpdb->get_results( $wpdb->prepare( "SELECT o.kw_id, o.value, o.txt, o.d FROM %i o JOIN (SELECT kw_id, MAX(d) d FROM %i WHERE src = 'live' AND metric = 'position' GROUP BY kw_id) l ON l.kw_id = o.kw_id AND l.d = o.d WHERE o.src = 'live' AND o.metric = 'position'", $o, $o ), OBJECT_K );
	$trend = $wpdb->get_results( $wpdb->prepare( "SELECT o.kw_id, o.value FROM %i o JOIN (SELECT kw_id, MAX(d) d FROM %i WHERE src = 'trends' AND metric = 'index' GROUP BY kw_id) l ON l.kw_id = o.kw_id AND l.d = o.d WHERE o.src = 'trends' AND o.metric = 'index'", $o, $o ), OBJECT_K );
	$links = $wpdb->get_results( $wpdb->prepare( 'SELECT kw_id, obj_type, path, role FROM %i', $p ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, label, priority, priority_override, status, action, page, impr28, clicks28, pos28, pos_prev, live_pos, live_txt, live_date, trends, quick_win FROM %i', $k ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$top_page = array(); // kw_id => [path, impressions] shown most.
	foreach ( (array) $top as $t ) {
		if ( ! isset( $top_page[ $t->kw_id ] ) ) {
			$top_page[ $t->kw_id ] = array( (string) $t->page, (int) $t->i );
		}
	}
	$owners = array(); // kw_id => owning paths.
	$fits   = array(); // kw_id => a page owns or carries it.
	foreach ( (array) $links as $l ) {
		if ( in_array( $l->role, array( 'owner', 'secondary' ), true ) ) {
			$fits[ $l->kw_id ] = true;
		}
		if ( 'owner' === $l->role && 0 === strpos( (string) $l->path, '/' ) && false === strpos( (string) $l->path, '{' ) ) {
			$owners[ $l->kw_id ][ (string) $l->path ] = true;
		}
	}

	$count = array();
	foreach ( (array) $rows as $r ) {
		$id      = (int) $r->id;
		$impr    = (int) ( $now28[ $id ]->i ?? 0 );
		$clicks  = (int) ( $now28[ $id ]->c ?? 0 );
		$pos28   = isset( $now28[ $id ] ) && null !== $now28[ $id ]->p ? round( (float) $now28[ $id ]->p, 1 ) : null;
		$posprev = isset( $prev[ $id ] ) && null !== $prev[ $id ]->p ? round( (float) $prev[ $id ]->p, 1 ) : null;
		$lv      = $live[ $id ] ?? null;
		$trends  = isset( $trend[ $id ] ) ? (int) $trend[ $id ]->value : null;
		$tp      = $top_page[ $id ] ?? array( '', 0 );
		$own     = array_keys( $owners[ $id ] ?? array() );
		$ctx     = array(
			'label'     => (string) $r->label,
			'main'      => $own,
			'live_txt'  => $lv ? (string) $lv->txt : '',
			'live_date' => $lv ? $lv->d : null,
		);
		$set     = insightweb_compute_row( $impr, $clicks, $pos28, $posprev, $lv && null !== $lv->value ? (float) $lv->value : null, $trends, $own, ! empty( $fits[ $id ] ), $tp, $ctx );
		$changed = false;
		foreach ( $set as $col => $val ) {
			if ( insightweb_compute_changed( $r->$col ?? null, $val ) ) {
				$changed = true;
				break;
			}
		}
		if ( $changed ) {
			insightweb_kw_set_figures( $id, $set );
		}
		$eff           = $r->priority_override ? $r->priority_override : $set['priority'];
		$count[ $eff ] = ( $count[ $eff ] ?? 0 ) + 1;
		$count[ $set['status'] ? $set['status'] : 'none' ] = ( $count[ $set['status'] ? $set['status'] : 'none' ] ?? 0 ) + 1;
	}
	return $count;
}
