<?php
/**
 * Daily calculation per keyword (plan §7): 28-day Search Console figures (sums of daily rows), the previous 28 days'
 * position, the latest live check, the owning page, priority, status and a suggested action. Results are copied onto
 * the keyword record so the lists sort without aggregating obs. Suggestions only: nothing else on the site changes.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keyword map rule: high demand is Trends T ≥ 20, or ≥ 50 Search Console impressions in 28 days.
const AW_SI_HIGH_TRENDS = 20;
const AW_SI_HIGH_IMPR   = 50;
const AW_SI_QUICK_MIN   = 10; // Quick win: position 8–20 with ≥ 10 impressions.

/** Suggested actions (plan §7). Keys are stored; labels are shown. */
function aw_si_actions(): array {
	return array(
		'fix-conflict'   => __( 'Fix conflict', 'aw-seo-extras' ),
		'new-page'       => __( 'New page', 'aw-seo-extras' ),
		'guide-update'   => __( 'Update the page', 'aw-seo-extras' ),
		'add-secondary'  => __( 'Add as secondary keyphrase', 'aw-seo-extras' ),
		'faq-answer'     => __( 'FAQ answer', 'aw-seo-extras' ),
		'news-explainer' => __( 'News explainer', 'aw-seo-extras' ),
		'sourcing'       => __( 'Consider as Sourcing keyword', 'aw-seo-extras' ),
		'page-quality'   => __( 'Check page quality', 'aw-seo-extras' ),
	);
}

/**
 * The keyword map's priority rule, unchanged.
 *
 * @param int|null   $trends   Trends index (T), or null.
 * @param int        $impr     Search Console impressions, 28 days.
 * @param float|null $pos      Latest position (live check or Search Console), or null.
 * @param bool       $has_page A page owns or fits the keyword.
 */
function aw_si_priority( ?int $trends, int $impr, ?float $pos, bool $has_page ): string {
	if ( null !== $pos && $pos > 0 && $pos <= 3 ) {
		return 'P3'; // Already in the top 3.
	}
	$high = ( null !== $trends && $trends >= AW_SI_HIGH_TRENDS ) || $impr >= AW_SI_HIGH_IMPR;
	if ( ! $high ) {
		return 'P3';
	}
	// High demand: P1 when we rank 4–20 or a page fits, else P2 (a content gap).
	return $has_page || ( null !== $pos && $pos >= 4 && $pos <= 20 ) ? 'P1' : 'P2';
}

/**
 * Whether position and impressions make a quick win.
 *
 * @param float|null $pos  Position.
 * @param int        $impr Impressions, 28 days.
 */
function aw_si_quick_win( ?float $pos, int $impr ): bool {
	return null !== $pos && $pos >= 8 && $pos <= 20 && $impr >= AW_SI_QUICK_MIN;
}

/**
 * Priority, status and suggested action of one keyword (used by the daily calculation and by the dry run).
 *
 * @param int|null   $trends   Trends index.
 * @param int        $impr     Search Console impressions, 28 days.
 * @param float|null $pos28    Search Console position, 28 days.
 * @param float|null $live     Latest live-check position.
 * @param string[]   $owners   Paths of the pages that own the keyword.
 * @param bool       $has_page Some page owns or carries it.
 * @param array      $top      [path, impressions] of the page Google showed most.
 * @return array{priority: string, status: string, action: string, conflict: bool, pos: float|null, owners: string[], shown: string}
 */
function aw_si_decide( ?int $trends, int $impr, ?float $pos28, ?float $live, array $owners, bool $has_page, array $top ): array {
	// Position: the live check, or Search Console when it has figures newer than the check (plan §7).
	$pos      = null !== $pos28 ? $pos28 : $live;
	$shown    = (string) ( $top[0] ?? '' );
	$conflict = count( $owners ) > 1
		|| ( $owners && '' !== $shown && (int) ( $top[1] ?? 0 ) >= AW_SI_QUICK_MIN && ! in_array( $shown, $owners, true ) );
	$priority = aw_si_priority( $trends, $impr, $pos, $has_page );
	$status   = $conflict ? 'conflict' : ( $has_page ? 'has-page' : ( 'P2' === $priority ? 'gap' : '' ) );
	$action   = '';
	if ( $conflict ) {
		$action = 'fix-conflict';
	} elseif ( 'P2' === $priority ) {
		$action = 'new-page';
	} elseif ( aw_si_quick_win( $pos, $impr ) ) {
		$action = $has_page ? 'guide-update' : 'add-secondary';
	}
	return compact( 'priority', 'status', 'action', 'conflict', 'pos', 'owners', 'shown' );
}

/**
 * Recalculate every keyword record.
 *
 * @return array Counts by priority and status.
 */
function aw_si_compute(): array {
	global $wpdb;
	$k   = aw_si_table( 'keywords' );
	$o   = aw_si_table( 'obs' );
	$p   = aw_si_table( 'kw_pages' );
	$tz  = wp_timezone();
	$end = ( new DateTimeImmutable( 'today', $tz ) )->modify( '-1 day' );
	$s28 = $end->modify( '-27 days' )->format( 'Y-m-d' );
	$s56 = $end->modify( '-55 days' )->format( 'Y-m-d' );
	$e56 = $end->modify( '-28 days' )->format( 'Y-m-d' );
	$e28 = $end->format( 'Y-m-d' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	$now28 = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM {$o} WHERE src = 'gsc' AND metric = 'q' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id", $s28, $e28 ), OBJECT_K );
	$prev  = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM {$o} WHERE src = 'gsc' AND metric = 'q' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id", $s56, $e56 ), OBJECT_K );
	// The page Google showed most for each query over 28 days.
	$top   = $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, page, SUM(impr) i FROM {$o} WHERE src = 'gsc' AND metric = 'qp' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY kw_id, page ORDER BY kw_id, i DESC", $s28, $e28 ) );
	$live  = $wpdb->get_results( "SELECT o.kw_id, o.value, o.txt, o.d FROM {$o} o JOIN (SELECT kw_id, MAX(d) d FROM {$o} WHERE src = 'live' AND metric = 'position' GROUP BY kw_id) l ON l.kw_id = o.kw_id AND l.d = o.d WHERE o.src = 'live' AND o.metric = 'position'", OBJECT_K );
	$trend = $wpdb->get_results( "SELECT o.kw_id, o.value FROM {$o} o JOIN (SELECT kw_id, MAX(d) d FROM {$o} WHERE src = 'trends' AND metric = 'index' GROUP BY kw_id) l ON l.kw_id = o.kw_id AND l.d = o.d WHERE o.src = 'trends' AND o.metric = 'index'", OBJECT_K );
	$links = $wpdb->get_results( "SELECT kw_id, path, role, src FROM {$p}" );
	$rows  = $wpdb->get_results( "SELECT id, roles, priority, priority_override, status, action, page, impr28, clicks28, pos28, pos_prev, live_pos, live_txt, live_date, trends FROM {$k}" );
	// phpcs:enable

	$top_page = array();
	foreach ( (array) $top as $t ) {
		if ( ! isset( $top_page[ $t->kw_id ] ) ) {
			$top_page[ $t->kw_id ] = array( (string) $t->page, (int) $t->i );
		}
	}
	$owners = array(); // kw_id => owning paths (focus primaries and keyword-map pages).
	$fits   = array(); // kw_id => true when some page owns or carries it.
	foreach ( (array) $links as $l ) {
		if ( in_array( $l->role, array( 'owner', 'secondary' ), true ) ) {
			$fits[ $l->kw_id ] = true;
		}
		if ( 'owner' === $l->role && 0 === strpos( $l->path, '/' ) && false === strpos( $l->path, '{' ) ) { // Real paths only: map labels and patterns are not compared.
			$owners[ $l->kw_id ][ $l->path ] = true;
		}
	}

	$count = array();
	foreach ( (array) $rows as $r ) {
		$id       = (int) $r->id;
		$impr     = (int) ( $now28[ $id ]->i ?? 0 );
		$clicks   = (int) ( $now28[ $id ]->c ?? 0 );
		$pos28    = isset( $now28[ $id ] ) && null !== $now28[ $id ]->p ? round( (float) $now28[ $id ]->p, 1 ) : null;
		$posprev  = isset( $prev[ $id ] ) && null !== $prev[ $id ]->p ? round( (float) $prev[ $id ]->p, 1 ) : null;
		$lv       = $live[ $id ] ?? null;
		$trends   = isset( $trend[ $id ] ) ? (int) $trend[ $id ]->value : null;
		$d        = aw_si_decide( $trends, $impr, $pos28, $lv && null !== $lv->value ? (float) $lv->value : null, array_keys( $owners[ $id ] ?? array() ), ! empty( $fits[ $id ] ), $top_page[ $id ] ?? array( '', 0 ) );
		$priority = $d['priority'];
		$status   = $d['status'];
		$action   = $d['action'];
		$own      = $d['owners'];
		$shown    = $d['shown'];
		$page     = $own ? $own[0] : $shown;
		$set      = array(
			'priority'  => $priority,
			'status'    => $status,
			'action'    => $action,
			'page'      => mb_substr( $page, 0, 255 ),
			'impr28'    => $impr,
			'clicks28'  => $clicks,
			'pos28'     => $pos28,
			'pos_prev'  => $posprev,
			'live_pos'  => $lv && null !== $lv->value ? (float) $lv->value : null,
			'live_txt'  => $lv ? mb_substr( (string) $lv->txt, 0, 16 ) : '',
			'live_date' => $lv ? $lv->d : null,
			'trends'    => $trends,
		);
		$changed  = false;
		foreach ( $set as $col => $val ) {
			$old = $r->$col;
			if ( null === $val ? null !== $old : ( null === $old || (string) $old !== (string) $val ) ) {
				// Floats come back from MySQL as strings such as "6.4": compare as numbers.
				if ( null !== $val && null !== $old && is_numeric( $val ) && is_numeric( $old ) && abs( (float) $old - (float) $val ) < 0.05 ) {
					continue;
				}
				$changed = true;
				break;
			}
		}
		if ( $changed ) {
			$set['updated'] = aw_si_now();
			$wpdb->update( $k, $set, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$eff                                 = $r->priority_override ? $r->priority_override : $priority;
		$count[ $eff ]                       = ( $count[ $eff ] ?? 0 ) + 1;
		$count[ $status ? $status : 'none' ] = ( $count[ $status ? $status : 'none' ] ?? 0 ) + 1;
	}
	return $count;
}
