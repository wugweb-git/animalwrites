<?php
/**
 * Monthly snapshot (plan §10): search-insights-YYYY-MM.csv in the columns of the 21 Oct rankings CSV (docs/seo/rankings-2026-10-21.csv, now in git history) plus
 * demand, priority, status and action, and keyword-map.md rebuilt from the keyword table. Both are downloads (Settings)
 * and `wp aw-si snapshot csv|map`; a session commits them to docs/seo/ each month. Nothing is committed from the site.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keywords in the snapshot: the check list, plus every tracked keyword with priority P1 or P2, listing names left out.
 *
 * @return object[] Keyword records, ordered by priority then label.
 */
function aw_si_snapshot_keywords(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k   = aw_si_table( 'keywords' );
	$ids = function_exists( 'aw_si_checklist' ) ? aw_si_checklist() : array();
	$in  = $ids ? ' OR id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '';
	$eff = "IF(priority_override <> '', priority_override, priority)";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name, constants and integers.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT *, {$eff} eff FROM {$k} WHERE entity = 0 AND label NOT LIKE %s AND ((roles & %d AND {$eff} IN ('P1', 'P2')){$in}) ORDER BY {$eff} = '', {$eff}, label", '%' . $wpdb->esc_like( '{' ) . '%', AW_SI_ROLE_TRACKED ) );
}

/**
 * The latest value of some obs rows per keyword.
 *
 * @param int[]    $ids     Keyword record IDs.
 * @param string   $src     Source.
 * @param string[] $metrics Metrics.
 * @return array<int, array<string, object>> kw_id => metric => row (value, txt, d).
 */
function aw_si_snapshot_latest( array $ids, string $src, array $metrics ): array {
	global $wpdb;
	if ( ! $ids ) {
		return array();
	}
	$o    = aw_si_table( 'obs' );
	$in   = implode( ',', array_map( 'intval', $ids ) );
	$list = implode( ',', array_map( fn( $m ) => "'" . esc_sql( $m ) . "'", $metrics ) );
	$out  = array();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- integers, escaped names and a table name.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT kw_id, metric, value, txt, d FROM {$o} WHERE src = %s AND metric IN ({$list}) AND kw_id IN ({$in}) ORDER BY d", $src ) ) as $r ) {
		$out[ (int) $r->kw_id ][ $r->metric ] = $r; // Ordered by date: the latest wins.
	}
	return $out;
}

/**
 * The snapshot rows, as arrays in CSV column order.
 *
 * @return array<int, array<string, string>>
 */
function aw_si_snapshot_rows(): array {
	$rows = aw_si_snapshot_keywords();
	$ids  = array_map( fn( $r ) => (int) $r->id, $rows );
	$live = aw_si_snapshot_latest( $ids, 'live', array( 'position', 'top3', 'paa', 'url' ) );
	$kp   = aw_si_snapshot_latest( $ids, 'kp', array( 'volume' ) );
	$ah   = aw_si_snapshot_latest( $ids, 'ahrefs', array( 'volume' ) );
	$out  = array();
	$date = wp_date( 'Y-m-d' );
	foreach ( $rows as $r ) {
		$l      = $live[ (int) $r->id ] ?? array();
		$p      = $l['position'] ?? null;
		$demand = array();
		if ( null !== $r->trends ) {
			$demand[] = 'T ' . (int) $r->trends;
		}
		$vol = $kp[ (int) $r->id ]['volume'] ?? ( $ah[ (int) $r->id ]['volume'] ?? null );
		if ( $vol ) {
			$demand[] = ( isset( $kp[ (int) $r->id ] ) ? 'KP ' : 'Ahrefs ' ) . ( '' !== $vol->txt ? $vol->txt : (string) (int) $vol->value );
		}
		if ( (int) $r->impr28 ) {
			$demand[] = 'GSC ' . (int) $r->impr28 . ' impr';
		}
		$group = ( (int) $r->roles & AW_SI_ROLE_BRAND ) ? 'brand' : ( '' !== $r->topic ? $r->topic : ( '' !== $r->page_type ? $r->page_type : 'demand' ) );
		$out[] = array(
			'date'                 => $date,
			'query'                => (string) $r->label,
			'group'                => (string) $group,
			'source'               => $p ? 'google-live (' . mysql2date( 'j M', $p->d ) . ')' : ( null !== $r->pos28 ? 'gsc-28d' : 'not checked' ),
			'position'             => $p ? ltrim( (string) $p->txt, '#' ) : ( null !== $r->pos28 ? (string) round( (float) $r->pos28, 1 ) : '' ),
			'url'                  => $p && isset( $l['url'] ) ? (string) $l['url']->txt : (string) $r->page,
			'top3'                 => isset( $l['top3'] ) ? str_replace( ', ', ' ; ', (string) $l['top3']->txt ) : '',
			'ai_overview'          => '',
			'ai_overview_names_aw' => '',
			'paa'                  => isset( $l['paa'] ) ? (string) $l['paa']->txt : '',
			'related'              => '',
			'gsc_impr_28d'         => (string) (int) $r->impr28,
			'gsc_clicks_28d'       => (string) (int) $r->clicks28,
			'gsc_pos_28d'          => null === $r->pos28 ? '' : (string) round( (float) $r->pos28, 1 ),
			'demand'               => implode( '; ', $demand ),
			'priority'             => (string) $r->eff,
			'status'               => (string) $r->status,
			'action'               => (string) ( aw_si_actions()[ $r->action ] ?? '' ),
		);
	}
	return $out;
}

/** The snapshot as CSV text. */
function aw_si_snapshot_csv(): string {
	$rows = aw_si_snapshot_rows();
	$cols = array( 'date', 'query', 'group', 'source', 'position', 'url', 'top3', 'ai_overview', 'ai_overview_names_aw', 'paa', 'related', 'gsc_impr_28d', 'gsc_clicks_28d', 'gsc_pos_28d', 'demand', 'priority', 'status', 'action' );
	$fh   = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
	fputcsv( $fh, $cols, ',', '"', '\\' );
	foreach ( $rows as $r ) {
		fputcsv( $fh, array_map( fn( $c ) => $r[ $c ], $cols ), ',', '"', '\\' );
	}
	rewind( $fh );
	$csv = (string) stream_get_contents( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.
	return $csv;
}

/**
 * A Markdown table cell: pipes escaped, line breaks removed.
 *
 * @param string $s Cell text.
 */
function aw_si_md_cell( string $s ): string {
	return str_replace( array( '|', "\r", "\n" ), array( '\|', ' ', ' ' ), trim( $s ) );
}

/** The keyword map (keyword-map.md) rebuilt from the keyword table: one table per section, keywords per page. */
function aw_si_keyword_map_md(): string {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return '';
	}
	$k   = aw_si_table( 'keywords' );
	$p   = aw_si_table( 'kw_pages' );
	$eff = "IF(k.priority_override <> '', k.priority_override, k.priority)";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table names and constants.
	$links = (array) $wpdb->get_results( $wpdb->prepare( "SELECT k.id, k.label, k.page_type, k.intent, k.trends, k.impr28, k.pos28, k.live_txt, k.status, k.action, {$eff} eff, l.path, l.obj_type, l.obj_id, l.role FROM {$p} l JOIN {$k} k ON k.id = l.kw_id WHERE l.src = 'seed' AND l.role IN ('owner', 'secondary') AND k.roles & %d ORDER BY l.role = 'secondary', k.label", AW_SI_ROLE_TRACKED ) );
	$pages = array(); // section => page => [primary rows, secondary labels].
	foreach ( $links as $l ) {
		$section = '' !== $l->page_type ? $l->page_type : 'other';
		$page    = 'post' === $l->obj_type && $l->obj_id ? aw_si_object_path( 'post', (int) $l->obj_id ) : (string) $l->path;
		$page    = '' !== $page ? $page : (string) $l->path;
		if ( 'owner' === $l->role ) {
			$pages[ $section ][ $page ]['primary'][] = $l;
		} else {
			$pages[ $section ][ $page ]['secondary'][] = $l->label;
		}
	}
	$titles = array(
		'hub'       => __( 'Section hubs', 'aw-seo-extras' ),
		'directory' => __( 'Directory', 'aw-seo-extras' ),
		'topic'     => __( 'Topics', 'aw-seo-extras' ),
		'guide'     => __( 'Resources guides', 'aw-seo-extras' ),
		'key-page'  => __( 'Key pages', 'aw-seo-extras' ),
		'new-row'   => __( 'Rows added since 20 Oct 2026', 'aw-seo-extras' ),
		'other'     => __( 'Other', 'aw-seo-extras' ),
	);
	$md     = "# Keyword map — Animal Writes 2.0\n\n";
	$md    .= '**Rebuilt by Search Insights on ' . wp_date( 'j F Y' ) . ".** The keyword table on the site is the source of truth (plan §2); this file is its monthly copy. The hand-written notes of the 15 Oct map (search titles, the method, cannibalisation notes) are in git history.\n\n";
	$md    .= "Columns: **Demand** = Trends T (\"animal welfare\" = 100) and Search Console impressions (28 days); **Rank** = the latest live Google check, or Search Console's 28-day position; **Priority** = the keyword-map rule (P1 high demand and we rank 4–20 or have a page; P2 high demand, no page; P3 the rest), or the owner's override.\n\n";
	$n      = 1;
	foreach ( $titles as $key => $title ) {
		if ( empty( $pages[ $key ] ) ) {
			continue;
		}
		$md .= '## ' . $n . '. ' . $title . "\n\n| Page | Primary keyword | Secondary keywords | Intent | Demand | Rank | Priority | Suggested action |\n|---|---|---|---|---|---|---|---|\n";
		++$n;
		foreach ( $pages[ $key ] as $page => $kw ) {
			foreach ( (array) ( $kw['primary'] ?? array() ) as $r ) {
				$demand = array();
				if ( null !== $r->trends ) {
					$demand[] = 'T ' . (int) $r->trends;
				}
				$demand[] = 'GSC ' . (int) $r->impr28 . ' impr';
				$rank     = '' !== $r->live_txt ? ( is_numeric( $r->live_txt ) ? '#' . $r->live_txt : $r->live_txt ) : ( null !== $r->pos28 ? 'GSC ' . round( (float) $r->pos28, 1 ) : 'not checked' );
				$md      .= '| ' . implode(
					' | ',
					array_map(
						'aw_si_md_cell',
						array(
							$page,
							$r->label,
							implode( ', ', array_unique( (array) ( $kw['secondary'] ?? array() ) ) ),
							$r->intent,
							implode( '; ', $demand ),
							$rank,
							(string) $r->eff,
							(string) ( aw_si_actions()[ $r->action ] ?? '' ),
						)
					)
				) . " |\n";
			}
		}
		$md .= "\n";
	}
	return $md;
}

add_action(
	'admin_post_aw_si_download',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_download' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$what = isset( $_GET['what'] ) ? sanitize_key( wp_unslash( $_GET['what'] ) ) : '';
		if ( 'map' === $what ) {
			$body = aw_si_keyword_map_md();
			$name = 'keyword-map.md';
			$type = 'text/markdown';
		} else {
			$body = aw_si_snapshot_csv();
			$name = 'search-insights-' . wp_date( 'Y-m' ) . '.csv';
			$type = 'text/csv';
		}
		nocache_headers();
		header( 'Content-Type: ' . $type . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a download, not HTML.
		exit;
	}
);

/** Settings tab row: the two downloads. */
function aw_si_snapshot_row(): void {
	$url = fn( $what ) => wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'aw_si_download',
				'what'   => $what,
			),
			admin_url( 'admin-post.php' )
		),
		'aw_si_download'
	);
	echo '<tr><th scope="row">' . esc_html__( 'Monthly snapshot', 'aw-seo-extras' ) . '</th><td><p>';
	/* translators: %s: month such as 2026-10. */
	echo '<a class="button" href="' . esc_url( $url( 'csv' ) ) . '">' . esc_html( sprintf( __( 'Download search-insights-%s.csv', 'aw-seo-extras' ), wp_date( 'Y-m' ) ) ) . '</a> ';
	echo '<a class="button" href="' . esc_url( $url( 'map' ) ) . '">' . esc_html__( 'Download keyword-map.md', 'aw-seo-extras' ) . '</a></p>';
	echo '<p class="description">' . esc_html__( 'The check list and every tracked P1/P2 keyword, in the columns of the 21 Oct rankings CSV plus demand, priority, status and action; and the keyword map rebuilt from the table. Once a month a session commits both to docs/seo/ (also: wp aw-si snapshot csv|map).', 'aw-seo-extras' ) . '</p></td></tr>';
}
add_action( 'aw_si_settings_rows', 'aw_si_snapshot_row', 5 );
