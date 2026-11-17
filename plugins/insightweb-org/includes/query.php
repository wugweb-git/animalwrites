<?php
/**
 * The admin data-access layer: everything the Keywords list table and the Overview charts read. Keeps the SQL in one
 * place (one rule per view, so a tab count and the list it opens always agree) and returns plain arrays the screens
 * render. Read-only except insightweb_kw_set_override(), the one change the admin makes — a priority override on
 * InsightWeb's own keyword record, never on the site.
 *
 * Generalised from the Animal Writes edition: the entity/movers views, the Directory "same subject" rule and the
 * sourcing branch are dropped (#6). One prefix: insightweb_ / INSIGHTWEB_.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The keyword roles, bit => label. A keyword may hold several (bitmask in the `roles` column).
 *
 * @return array<int, string>
 */
function insightweb_roles(): array {
	return array(
		INSIGHTWEB_ROLE_TRACKED  => __( 'Tracked', 'insightweb' ),
		INSIGHTWEB_ROLE_FOCUS    => __( 'Focus', 'insightweb' ),
		INSIGHTWEB_ROLE_BRAND    => __( 'Brand', 'insightweb' ),
		INSIGHTWEB_ROLE_SEARCHED => __( 'Searched', 'insightweb' ),
	);
}

/**
 * Search intents, key => label (stored in the `intent` column by the engine).
 *
 * @return array<string, string>
 */
function insightweb_intents(): array {
	return array(
		'informational' => __( 'Informational', 'insightweb' ),
		'navigational'  => __( 'Navigational', 'insightweb' ),
		'commercial'    => __( 'Commercial', 'insightweb' ),
		'transactional' => __( 'Transactional', 'insightweb' ),
	);
}

/**
 * The effective-priority SQL: the owner's override, else the calculated priority.
 *
 * @param string $alias Table alias with its dot (e.g. "k."), or ''.
 * @return string
 */
function insightweb_eff_sql( string $alias = '' ): string {
	return "IF({$alias}priority_override <> '', {$alias}priority_override, {$alias}priority)";
}

/**
 * The quick-win condition: the stored flag when the daily calculation has run, else the raw rule.
 *
 * @return string
 */
function insightweb_quick_sql(): string {
	return insightweb_kw_ready() ? 'quick_win = 1' : 'pos28 BETWEEN 8 AND 20 AND impr28 >= ' . (int) INSIGHTWEB_QUICK_MIN;
}

/**
 * The SQL condition of each keyword view, so every count and the list it opens use one rule. No `%` anywhere — these
 * pass through $wpdb->prepare() as fixed fragments.
 *
 * @param string $view all, p1, p2, p3, gap, conflict, quick or notranked; '' for the whole map.
 * @return string|array<string, string> The condition of $view (1=0 when unknown), or view => condition.
 */
function insightweb_view_sql( string $view = '' ) {
	$eff = insightweb_eff_sql();
	$map = array(
		'all'       => '1=1',
		'p1'        => "{$eff} = 'P1'",
		'p2'        => "{$eff} = 'P2'",
		'p3'        => "{$eff} = 'P3'",
		'gap'       => "status = 'gap'",
		'conflict'  => "status = 'conflict'",
		'quick'     => insightweb_quick_sql(),
		'notranked' => 'roles & ' . (int) INSIGHTWEB_ROLE_TRACKED . " AND impr28 = 0 AND LOCATE('{', label) = 0",
	);
	return '' === $view ? $map : ( $map[ $view ] ?? '1=0' );
}

/**
 * The labels of the views (same keys as insightweb_view_sql()).
 *
 * @return array<string, string>
 */
function insightweb_view_labels(): array {
	return array(
		'all'       => __( 'All', 'insightweb' ),
		'p1'        => 'P1',
		'p2'        => 'P2',
		'p3'        => 'P3',
		'gap'       => __( 'Gaps', 'insightweb' ),
		'conflict'  => __( 'Conflicts', 'insightweb' ),
		'quick'     => __( 'Quick wins', 'insightweb' ),
		'notranked' => __( 'Tracked, not ranking', 'insightweb' ),
	);
}

/**
 * The admin URL of an InsightWeb tab.
 *
 * @param string $tab Tab id, e.g. 'keywords'.
 * @return string
 */
function insightweb_url( string $tab = '' ): string {
	$args = array( 'page' => 'insightweb' );
	if ( '' !== $tab ) {
		$args['tab'] = $tab;
	}
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * The query args that filter the Keywords list, read from the request (read-only; sanitised).
 *
 * @return array<string, mixed>
 */
function insightweb_kw_filters(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on a GET list screen.
	$req = wp_unslash( $_REQUEST );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$get = static function ( $k ) use ( $req ) {
		return isset( $req[ $k ] ) ? sanitize_key( (string) $req[ $k ] ) : '';
	};
	return array(
		'view'    => $get( 'view' ) ? $get( 'view' ) : 'all',
		'role'    => (int) $get( 'role' ),
		'intent'  => $get( 'intent' ),
		'topic'   => isset( $req['topic'] ) ? sanitize_text_field( (string) $req['topic'] ) : '',
		'search'  => isset( $req['s'] ) ? trim( sanitize_text_field( (string) $req['s'] ) ) : '',
		'orderby' => $get( 'orderby' ) ? $get( 'orderby' ) : 'impr28',
		'order'   => 'asc' === $get( 'order' ) ? 'ASC' : 'DESC',
		'paged'   => max( 1, (int) $get( 'paged' ) ),
		'per'     => 50,
	);
}

/**
 * One page of keyword records for the given filters, plus the total row count.
 *
 * @param array<string, mixed> $f Filters from insightweb_kw_filters(); 'per' and 'paged' control the page.
 * @return array{items: object[], total: int}
 */
function insightweb_kw_list( array $f ): array {
	global $wpdb;
	if ( ! insightweb_kw_ready() ) {
		return array(
			'items' => array(),
			'total' => 0,
		);
	}
	$t     = insightweb_kw_table( 'keywords' );
	$where = array();
	$args  = array();

	$views   = insightweb_view_sql();
	$where[] = $views[ $f['view'] ] ?? '1=1';

	if ( $f['role'] && isset( insightweb_roles()[ $f['role'] ] ) ) {
		$where[] = 'roles & %d';
		$args[]  = $f['role'];
	}
	if ( '' !== $f['intent'] && isset( insightweb_intents()[ $f['intent'] ] ) ) {
		$where[] = 'intent = %s';
		$args[]  = $f['intent'];
	}
	if ( '' !== $f['topic'] ) {
		$where[] = 'topic = %s';
		$args[]  = $f['topic'];
	}
	if ( '' !== $f['search'] ) {
		$where[] = 'label LIKE %s';
		$args[]  = '%' . $wpdb->esc_like( $f['search'] ) . '%';
	}

	$cols    = array(
		'label'    => 'label',
		'priority' => insightweb_eff_sql(),
		'topic'    => "topic = '', topic",
		'impr28'   => 'impr28',
		'clicks28' => 'clicks28',
		'pos28'    => 'pos28 IS NULL, pos28',
		'change'   => '(pos_prev - pos28)',
		'trends'   => 'trends',
	);
	$orderby = isset( $cols[ $f['orderby'] ] ) ? $f['orderby'] : 'impr28';
	$order   = 'ASC' === $f['order'] ? 'ASC' : 'DESC';
	$by      = 'pos28' === $orderby ? 'pos28 IS NULL, pos28 ' . $order : $cols[ $orderby ] . ' ' . $order;

	$per   = max( 1, (int) $f['per'] );
	$paged = max( 1, (int) $f['paged'] );
	$sql   = implode( ' AND ', $where );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- conditions and order are fixed fragments with placeholders; values are bound.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql}", array_merge( array( $t ), $args ) ) );
	$query = "SELECT * FROM %i WHERE {$sql} ORDER BY {$by}, label ASC LIMIT %d OFFSET %d";
	$items = (array) $wpdb->get_results( $wpdb->prepare( $query, array_merge( array( $t ), $args, array( $per, ( $paged - 1 ) * $per ) ) ) );
	// phpcs:enable
	return array(
		'items' => $items,
		'total' => $total,
	);
}

/**
 * The row count of each view, for the view links above the list.
 *
 * @return array<string, int>
 */
function insightweb_view_counts(): array {
	global $wpdb;
	$out = array();
	if ( ! insightweb_kw_ready() ) {
		foreach ( array_keys( insightweb_view_sql() ) as $key ) {
			$out[ $key ] = 0;
		}
		return $out;
	}
	$t = insightweb_kw_table( 'keywords' );
	foreach ( insightweb_view_sql() as $key => $sql ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fixed conditions, no %.
		$out[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql}", $t ) );
	}
	return $out;
}

/**
 * Distinct non-empty topics, for the topic filter dropdown.
 *
 * @return string[]
 */
function insightweb_topics(): array {
	global $wpdb;
	if ( ! insightweb_kw_ready() ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT topic FROM %i WHERE topic <> '' ORDER BY topic", insightweb_kw_table( 'keywords' ) ) ) );
}

/**
 * Set or clear the owner's priority override on one keyword record (the one change the admin makes).
 *
 * @param int    $id  Keyword record id.
 * @param string $to  'P1', 'P2', 'P3' or '' to clear.
 * @return bool
 */
function insightweb_kw_set_override( int $id, string $to ): bool {
	$to = in_array( $to, array( 'P1', 'P2', 'P3' ), true ) ? $to : '';
	return insightweb_kw_set_figures( $id, array( 'priority_override' => $to ) );
}

/**
 * The Overview aggregates the dashboard charts and the scorecards read. All read-only.
 *
 * @return array{
 *     ready: bool, totals: array<string, float|int|null>, priority: array<string, int>,
 *     status: array<string, int>, action: array<string, int>, trend: array<int, array<string, float|int|string>>,
 *     top: object[]
 * }
 */
function insightweb_overview(): array {
	global $wpdb;
	$empty = array(
		'ready'    => false,
		'totals'   => array(
			'keywords' => 0,
			'impr'     => 0,
			'clicks'   => 0,
			'ctr'      => null,
			'pos'      => null,
			'quick'    => 0,
		),
		'priority' => array(),
		'status'   => array(),
		'action'   => array(),
		'trend'    => array(),
		'top'      => array(),
	);
	if ( ! insightweb_kw_ready() ) {
		return $empty;
	}
	$k = insightweb_kw_table( 'keywords' );

	// Totals (weighted average position over keywords that have one).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$agg    = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) kw, SUM(impr28) i, SUM(clicks28) c, SUM(pos28 * impr28) / NULLIF(SUM(IF(pos28 IS NULL, 0, impr28)), 0) p, SUM(quick_win) q FROM %i', $k ) );
	$impr   = (int) ( $agg->i ?? 0 );
	$clicks = (int) ( $agg->c ?? 0 );
	$totals = array(
		'keywords' => (int) ( $agg->kw ?? 0 ),
		'impr'     => $impr,
		'clicks'   => $clicks,
		'ctr'      => $impr > 0 ? round( $clicks / $impr * 100, 1 ) : null,
		'pos'      => null === ( $agg->p ?? null ) ? null : round( (float) $agg->p, 1 ),
		'quick'    => (int) ( $agg->q ?? 0 ),
	);

	// Effective-priority breakdown.
	$eff      = insightweb_eff_sql();
	$priority = array(
		'P1' => 0,
		'P2' => 0,
		'P3' => 0,
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- fixed expression.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT {$eff} p, COUNT(*) n FROM %i GROUP BY p", $k ) ) as $r ) {
		if ( isset( $priority[ $r->p ] ) ) {
			$priority[ $r->p ] = (int) $r->n;
		}
	}

	// Status breakdown (has-page / gap / conflict / none).
	$status = array(
		'has-page' => 0,
		'gap'      => 0,
		'conflict' => 0,
		'none'     => 0,
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) n FROM %i GROUP BY status', $k ) ) as $r ) {
		$key            = '' === (string) $r->status ? 'none' : (string) $r->status;
		$status[ $key ] = ( $status[ $key ] ?? 0 ) + (int) $r->n;
	}

	// Suggested-action breakdown (non-empty actions only).
	$action = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) n FROM %i WHERE action <> '' GROUP BY action ORDER BY n DESC", $k ) ) as $r ) {
		$action[ (string) $r->action ] = (int) $r->n;
	}

	// Top queries by 28-day impressions.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$top = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT label, impr28, clicks28, pos28 FROM %i WHERE impr28 > 0 ORDER BY impr28 DESC LIMIT 10', $k ) );

	return array(
		'ready'    => true,
		'totals'   => $totals,
		'priority' => $priority,
		'status'   => $status,
		'action'   => $action,
		'trend'    => insightweb_overview_trend(),
		'top'      => $top,
	);
}

/**
 * The 28-day daily trend from the observation table: clicks, impressions, CTR and weighted position per day. Empty
 * until a source (Site Kit by Google, model A) has filled obs.
 *
 * @return array<int, array<string, float|int|string>>
 */
function insightweb_overview_trend(): array {
	global $wpdb;
	if ( ! insightweb_ready() ) {
		return array();
	}
	$o   = insightweb_kw_table( 'obs' );
	$tz  = wp_timezone();
	$end = ( new DateTimeImmutable( 'today', $tz ) )->modify( '-1 day' );
	$s28 = $end->modify( '-27 days' )->format( 'Y-m-d' );
	$e28 = $end->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, SUM(clicks) c, SUM(impr) i, SUM(pos * impr) / NULLIF(SUM(impr), 0) p FROM %i WHERE src = 'gsc' AND metric = 'q' AND period = 'day' AND d BETWEEN %s AND %s GROUP BY d ORDER BY d", $o, $s28, $e28 ) );
	$out  = array();
	foreach ( $rows as $r ) {
		$i     = (int) $r->i;
		$c     = (int) $r->c;
		$out[] = array(
			'd'      => (string) $r->d,
			'clicks' => $c,
			'impr'   => $i,
			'ctr'    => $i > 0 ? round( $c / $i * 100, 2 ) : 0,
			'pos'    => null === $r->p ? 0 : round( (float) $r->p, 1 ),
		);
	}
	return $out;
}
