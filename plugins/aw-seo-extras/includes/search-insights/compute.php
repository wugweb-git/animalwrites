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
// AW_SI_HIGH_TRENDS: AW SEO Plus → Settings (settings.php).
// AW_SI_HIGH_IMPR: AW SEO Plus → Settings (settings.php).
// AW_SI_QUICK_MIN: AW SEO Plus → Settings (settings.php).
// AW_SI_MOVERS: AW SEO Plus → Settings (settings.php).

/** Suggested actions (plan §7). Keys are stored; labels are shown. */
function aw_si_actions(): array {
	return array(
		'fix-conflict'   => __( 'Fix conflict', 'aw-seo-extras' ),
		'new-guide'      => __( 'New page: guide', 'aw-seo-extras' ),
		'new-district'   => __( 'New page: district or state page', 'aw-seo-extras' ),
		'new-page'       => __( 'New page', 'aw-seo-extras' ), // Before 25 Oct; the next daily calculation replaces it.
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
 * Whether a search reads as a question, so it fits an FAQ answer.
 *
 * @param string $label Search phrase.
 */
function aw_si_is_question( string $label ): bool {
	$label = trim( $label );
	if ( preg_match( '/^who\b.*\b(rabies|guidelines?|report|recommend\w*|vaccin\w*)\b/i', $label ) ) {
		return false; // WHO, the World Health Organization, not "who".
	}
	return false !== strpos( $label, '?' ) || (bool) preg_match( '/^(how|what|why|when|where|which|who|whom|whose|can|could|is|are|was|were|does|do|did|should|will|would)\b/i', $label );
}

/**
 * Whether a search is about news: an event, a case or a date, so it fits a news explainer.
 *
 * @param string $label Search phrase.
 */
function aw_si_is_news( string $label ): bool {
	if ( preg_match( '/\b(news|latest|today|yesterday|case|court|verdict|judgement|judgment|ruling|ban|banned|arrest|arrested|fir|attack|attacked|mauled|killed|viral|incident|protest)\b/i', $label ) ) {
		return true;
	}
	// A year alone is news, but not in the name of a law (the ABC Rules 2023, an Act or Bill of a year).
	return (bool) preg_match( '/\b20[0-9]{2}\b/', $label ) && ! preg_match( '/\b(act|acts|rules?|bill|amendment|law|laws|guidelines?|regulations?|order)\b/i', $label );
}

/**
 * Whether a search names a place (a district or state of India, a Directory pattern, or "near me"), so a gap fits a
 * Directory district or state page.
 *
 * @param string $label Search phrase.
 */
function aw_si_is_place( string $label ): bool {
	static $re = null;
	if ( preg_match( '/\{(district|city|state)\}|\bnear me\b/i', $label ) ) {
		return true;
	}
	if ( null === $re ) {
		$names = array();
		if ( function_exists( 'aw_lgd' ) ) {
			$lgd = aw_lgd();
			foreach ( array_merge( (array) $lgd['states'], (array) $lgd['districts'] ) as $p ) {
				$n = strtolower( (string) $p->name );
				if ( strlen( $n ) >= 4 ) { // Short names (Mau, Una …) match ordinary words too often.
					$names[] = preg_quote( $n, '/' );
				}
			}
		}
		$re = $names ? '/\b(' . implode( '|', array_unique( $names ) ) . ')\b/i' : '';
	}
	return '' !== $re && (bool) preg_match( $re, $label );
}

/**
 * Suggested page type for a content gap (plan §7): FAQ answer, news explainer, district or state page, or guide.
 *
 * @param string $label Search phrase.
 */
function aw_si_gap_action( string $label ): string {
	if ( aw_si_is_question( $label ) ) {
		return 'faq-answer';
	}
	if ( aw_si_is_news( $label ) ) {
		return 'news-explainer';
	}
	return aw_si_is_place( $label ) ? 'new-district' : 'new-guide';
}

/**
 * Pages flagged for quality in the last 28 days (plan §5.2: Clarity rage or dead clicks, quick-backs; PageSpeed).
 * Phase 2 writes these as obs rows src clarity or psi, metric flag, value 1. Filter aw_si_quality_pages adds more.
 *
 * @return array<string, true> path => true.
 */
function aw_si_quality_pages(): array {
	global $wpdb;
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-28 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$paths = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT page FROM {$o} WHERE src IN ('clarity', 'psi') AND metric = 'flag' AND value > 0 AND d >= %s AND page <> ''", $since ) );
	$paths = (array) apply_filters( 'aw_si_quality_pages', $paths );
	return array_fill_keys( array_map( 'strval', $paths ), true );
}

/**
 * Listing names (entity records) that moved most: shown one by one in the Keywords list, the rest as one row.
 *
 * @return int[] Keyword record IDs.
 */
function aw_si_entity_movers(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k = aw_si_table( 'keywords' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$k} WHERE entity = 1 AND pos28 IS NOT NULL AND pos_prev IS NOT NULL ORDER BY ABS(pos_prev - pos28) DESC, impr28 DESC, id LIMIT %d", AW_SI_MOVERS ) ) );
}

/**
 * SQL condition that keeps listing names out of a list or count, except the ones that moved most (plan §8).
 *
 * @param int[]|null $movers aw_si_entity_movers(), when the caller already has it.
 */
function aw_si_entity_sql( ?array $movers = null ): string {
	$movers = null === $movers ? aw_si_entity_movers() : $movers;
	return $movers ? '(entity = 0 OR id IN (' . implode( ',', array_map( 'intval', $movers ) ) . '))' : 'entity = 0';
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

/** SQL condition of a quick win: the stored flag (keyword table version 3), else the same rule on the stored figures. */
function aw_si_quick_sql(): string {
	return function_exists( 'aw_kw_ready' ) && aw_kw_ready() ? 'quick_win = 1' : 'pos28 BETWEEN 8 AND 20 AND impr28 >= ' . (int) AW_SI_QUICK_MIN;
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
 * @param array      $ctx      label (search phrase), lang, sourcing (already a Sourcing keyword), quality (paths
 *                             flagged for page quality, as path => true).
 * @return array{priority: string, status: string, action: string, conflict: bool, pos: float|null, owners: string[], shown: string}
 */
function aw_si_decide( ?int $trends, int $impr, ?float $pos28, ?float $live, array $owners, bool $has_page, array $top, array $ctx = array() ): array {
	// Position: the live check, or Search Console when it has figures newer than the check (plan §7).
	$pos      = null !== $pos28 ? $pos28 : $live;
	$shown    = (string) ( $top[0] ?? '' );
	$conflict = count( $owners ) > 1
		|| ( $owners && '' !== $shown && (int) ( $top[1] ?? 0 ) >= AW_SI_QUICK_MIN && ! in_array( $shown, $owners, true ) );
	$priority = aw_si_priority( $trends, $impr, $pos, $has_page );
	$status   = $conflict ? 'conflict' : ( $has_page ? 'has-page' : ( 'P2' === $priority ? 'gap' : '' ) );
	$label    = (string) ( $ctx['label'] ?? '' );
	$page     = $owners ? $owners[0] : $shown;
	$action   = '';
	// One action per keyword, the first that applies (plan §7).
	if ( $conflict ) {
		$action = 'fix-conflict';
	} elseif ( 'P2' === $priority ) {
		$action = aw_si_gap_action( $label ); // A content gap, with the page type that fits it.
	} elseif ( aw_si_quick_win( $pos, $impr ) ) {
		$action = $has_page ? 'guide-update' : 'add-secondary';
	} elseif ( $impr > 0 && '' !== $page && isset( $ctx['quality'][ $page ] ) ) {
		$action = 'page-quality'; // Searchers land on a page flagged by Clarity or PageSpeed.
	} elseif ( ! $has_page && $impr >= AW_SI_QUICK_MIN && aw_si_is_question( $label ) ) {
		$action = 'faq-answer';
	} elseif ( ! $has_page && $impr >= AW_SI_QUICK_MIN && aw_si_is_news( $label ) ) {
		$action = 'news-explainer';
	} elseif ( $impr >= AW_SI_QUICK_MIN && 'en' === ( $ctx['lang'] ?? 'en' ) && empty( $ctx['sourcing'] ) && 0 === strpos( $shown, '/news/' ) && aw_si_is_news( $label ) ) {
		$action = 'sourcing'; // Our news items already show for it: more of them would help. Never for Hindi or Hinglish.
	}
	return compact( 'priority', 'status', 'action', 'conflict', 'pos', 'owners', 'shown' );
}

/**
 * What aw_si_decide() needs to know about a keyword besides its figures.
 *
 * @param object|array $r       Keyword record (label, lang, roles).
 * @param array        $quality Paths flagged for page quality (aw_si_quality_pages()).
 */
function aw_si_ctx( $r, array $quality ): array {
	$r = (object) $r;
	return array(
		'label'    => (string) $r->label,
		'lang'     => (string) $r->lang,
		'sourcing' => (bool) ( (int) $r->roles & AW_SI_ROLE_SOURCING ),
		'quality'  => $quality,
	);
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
	$qw    = function_exists( 'aw_kw_ready' ) && aw_kw_ready(); // the quick_win column (keyword table version 3)
	$rows  = $wpdb->get_results( "SELECT id, label, lang, roles, priority, priority_override, status, action, page, impr28, clicks28, pos28, pos_prev, live_pos, live_txt, live_date, trends" . ( $qw ? ', quick_win' : '' ) . " FROM {$k}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a fixed column name, no input
	// phpcs:enable

	// An old address counts as the page it redirects to (plan §12, phase 2), so a redirected URL is no conflict.
	$by_page = array(); // kw_id => final path => impressions.
	foreach ( (array) $top as $t ) {
		$final                          = aw_si_final_path( (string) $t->page );
		$by_page[ $t->kw_id ][ $final ] = ( $by_page[ $t->kw_id ][ $final ] ?? 0 ) + (int) $t->i;
	}
	$top_page = array();
	foreach ( $by_page as $kw => $pages ) {
		arsort( $pages );
		$top_page[ $kw ] = array( (string) key( $pages ), (int) current( $pages ) );
	}
	$owners = array(); // kw_id => owning paths (focus primaries and keyword-map pages).
	$fits   = array(); // kw_id => true when some page owns or carries it.
	foreach ( (array) $links as $l ) {
		if ( in_array( $l->role, array( 'owner', 'secondary' ), true ) ) {
			$fits[ $l->kw_id ] = true;
		}
		if ( 'owner' === $l->role && 0 === strpos( $l->path, '/' ) && false === strpos( $l->path, '{' ) ) { // Real paths only: map labels and patterns are not compared.
			$owners[ $l->kw_id ][ aw_si_final_path( $l->path ) ] = true;
		}
	}

	$quality = aw_si_quality_pages();
	$count   = array();
	foreach ( (array) $rows as $r ) {
		$id       = (int) $r->id;
		$impr     = (int) ( $now28[ $id ]->i ?? 0 );
		$clicks   = (int) ( $now28[ $id ]->c ?? 0 );
		$pos28    = isset( $now28[ $id ] ) && null !== $now28[ $id ]->p ? round( (float) $now28[ $id ]->p, 1 ) : null;
		$posprev  = isset( $prev[ $id ] ) && null !== $prev[ $id ]->p ? round( (float) $prev[ $id ]->p, 1 ) : null;
		$lv       = $live[ $id ] ?? null;
		$trends   = isset( $trend[ $id ] ) ? (int) $trend[ $id ]->value : null;
		$d        = aw_si_decide( $trends, $impr, $pos28, $lv && null !== $lv->value ? (float) $lv->value : null, array_keys( $owners[ $id ] ?? array() ), ! empty( $fits[ $id ] ), $top_page[ $id ] ?? array( '', 0 ), aw_si_ctx( $r, $quality ) );
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
		if ( $qw ) {
			$set['quick_win'] = aw_si_quick_win( $d['pos'], $impr ) ? 1 : 0;
		}
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
