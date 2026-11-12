<?php
/**
 * SourceWeb's new screens: the plain parts with no WordPress in them, so tests/run.php checks them. The To review list
 * (filter, search, sort, pages, views and filter choices), the Listing details fields for the field-by-field review,
 * the numbers the Dashboard keeps per day, and "save only what changed" for Settings.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** To review: rows per page. */
const SOURCEWEB_REVIEW_PER_PAGE = 50;

/** Listing details: listings per page. */
const SOURCEWEB_ENRICH_PER_PAGE = 20;

/** Listing details: the status views, in today's order (enrichment-admin.php). */
const SOURCEWEB_ENRICH_VIEWS = array(
	'pending'  => 'Waiting',
	'applied'  => 'Applied',
	'rejected' => 'Rejected',
	'failed'   => 'Failed',
	'undone'   => 'Undone',
	'all'      => 'All',
);

/**
 * Why an item waits, as today's To review column shows it.
 *
 * @param string $meta   The item's aw_review value.
 * @param string $status Its post status.
 */
function sourceweb_why_text( string $meta, string $status ): string {
	if ( '' !== $meta ) {
		return (string) preg_replace( '/^held:\s*/', '', $meta );
	}
	return 'pending' === $status ? 'waiting for a first review' : '';
}

/**
 * The short reason of a "why" text, for the filter: the words before the first colon, semicolon or bracket.
 *
 * @param string $why Why text.
 */
function sourceweb_why_key( string $why ): string {
	$k = trim( (string) preg_split( '/[:;(]/', $why, 2 )[0] );
	return mb_strtolower( mb_substr( $k, 0, 60 ) );
}

/**
 * One page of To review, from every waiting item (newest first, as aw_review_items() gives them).
 *
 * @param array $items  [ { id, title, type, typeLabel, date, host, why, excerpt, url } ].
 * @param array $q      { type, search, source, why, orderby: date|title|type, order: asc|desc, page }.
 * @param array $labels Type => label (AW_REVIEW_TYPES).
 * @param int   $per    Rows per page.
 * @return array { rows, total, page, pages, views, filters, count }.
 */
function sourceweb_review_list( array $items, array $q, array $labels, int $per = SOURCEWEB_REVIEW_PER_PAGE ): array {
	$type = (string) ( $q['type'] ?? '' );
	$type = isset( $labels[ $type ] ) ? $type : '';

	$by = array();
	foreach ( $items as $i ) {
		$by[ $i['type'] ] = ( $by[ $i['type'] ] ?? 0 ) + 1;
	}
	$views = array(
		array(
			'value' => '',
			'label' => 'All',
			'count' => count( $items ),
		),
	);
	foreach ( $labels as $t => $l ) {
		if ( ! empty( $by[ $t ] ) || $t === $type ) {
			$views[] = array(
				'value' => $t,
				'label' => $l,
				'count' => (int) ( $by[ $t ] ?? 0 ),
			);
		}
	}

	$view  = '' === $type ? $items : array_values( array_filter( $items, fn( $i ) => $i['type'] === $type ) );
	$count = count( $view ); // "Publish all N": the whole view, as today's handler publishes it.

	$hosts = array();
	$whys  = array();
	foreach ( $view as $i ) {
		if ( '' !== $i['host'] ) {
			$hosts[ $i['host'] ] = ( $hosts[ $i['host'] ] ?? 0 ) + 1;
		}
		$k = sourceweb_why_key( (string) $i['why'] );
		if ( '' !== $k ) {
			$whys[ $k ] = ( $whys[ $k ] ?? 0 ) + 1;
		}
	}
	arsort( $hosts );
	arsort( $whys );

	$source = (string) ( $q['source'] ?? '' );
	$why    = (string) ( $q['why'] ?? '' );
	$words  = mb_strtolower( trim( (string) ( $q['search'] ?? '' ) ) );
	$rows   = array_values(
		array_filter(
			$view,
			function ( $i ) use ( $source, $why, $words ) {
				if ( '' !== $source && $i['host'] !== $source ) {
					return false;
				}
				if ( '' !== $why && sourceweb_why_key( (string) $i['why'] ) !== $why ) {
					return false;
				}
				return '' === $words || false !== strpos( mb_strtolower( $i['title'] . ' ' . $i['excerpt'] . ' ' . $i['url'] ), $words );
			}
		)
	);

	$orderby = in_array( $q['orderby'] ?? '', array( 'title', 'type', 'date' ), true ) ? $q['orderby'] : 'date';
	$asc     = 'asc' === ( $q['order'] ?? ( 'date' === $orderby ? 'desc' : 'asc' ) );
	if ( 'date' !== $orderby || $asc ) {
		usort(
			$rows,
			function ( $a, $b ) use ( $orderby, $asc ) {
				if ( 'title' === $orderby ) {
					$c = strcasecmp( $a['title'], $b['title'] );
				} elseif ( 'type' === $orderby ) {
					$c = strcmp( $a['typeLabel'], $b['typeLabel'] );
				} else {
					$c = strcmp( $a['date'], $b['date'] );
				}
				return $asc ? $c : -$c;
			}
		);
	}

	$total = count( $rows );
	$pages = max( 1, (int) ceil( $total / max( 1, $per ) ) );
	$page  = min( $pages, max( 1, (int) ( $q['page'] ?? 1 ) ) );

	$opt = fn( array $counts, string $all ) => array_merge(
		array(
			array(
				'value' => '',
				'label' => $all,
			),
		),
		array_map(
			fn( $k ) => array(
				'value' => (string) $k,
				'label' => $k . ' (' . $counts[ $k ] . ')',
			),
			array_slice( array_keys( $counts ), 0, 30 )
		)
	);

	return array(
		'rows'    => array_slice( $rows, ( $page - 1 ) * $per, $per ),
		'total'   => $total,
		'page'    => $page,
		'pages'   => $pages,
		'count'   => $count,
		'type'    => $type,
		'views'   => $views,
		'filters' => array(
			'source' => $opt( $hosts, 'All sources' ),
			'why'    => $opt( $whys, 'Any reason' ),
		),
	);
}

/**
 * One Listing details row for the field-by-field review.
 *
 * @param array $r { id, field, label, current, proposed, status, confidence, source, note, created }: plain text.
 * @return array { key, label, current, proposed, applied, undoOff, waiting, status, confidence, source, note, created }.
 */
function sourceweb_enrich_field( array $r ): array {
	$status  = (string) $r['status'];
	$applied = in_array( $status, array( 'auto-applied', 'approved' ), true );
	return array(
		'key'        => (int) $r['id'],
		'field'      => (string) $r['field'],
		'label'      => (string) $r['label'],
		'current'    => (string) $r['current'],
		'proposed'   => (string) $r['proposed'],
		'applied'    => $applied,
		'waiting'    => 'pending' === $status,
		'undoOff'    => '',
		'status'     => $status,
		'confidence' => (string) $r['confidence'],
		'source'     => (string) $r['source'],
		'note'       => (string) $r['note'],
		'created'    => (string) $r['created'],
	);
}

/**
 * Rows of the proposals table grouped per listing, in the order the listings come.
 *
 * @param int[] $order    Listing IDs in list order.
 * @param array $fields   Fields from sourceweb_enrich_field(), each with 'listing'.
 * @return array listing ID => { fields: [ waiting and applied ], others: [ rejected, failed, undone, superseded ], waiting, applied }.
 */
function sourceweb_enrich_group( array $order, array $fields ): array {
	$out = array();
	foreach ( $order as $lid ) {
		$out[ (int) $lid ] = array(
			'fields'  => array(),
			'others'  => array(),
			'waiting' => 0,
			'applied' => 0,
		);
	}
	foreach ( $fields as $f ) {
		$lid = (int) $f['listing'];
		if ( ! isset( $out[ $lid ] ) ) {
			continue;
		}
		unset( $f['listing'] );
		if ( $f['waiting'] || $f['applied'] ) {
			$out[ $lid ]['fields'][] = $f;
			$out[ $lid ]['waiting'] += $f['waiting'] ? 1 : 0;
			$out[ $lid ]['applied'] += $f['applied'] ? 1 : 0;
		} else {
			$out[ $lid ]['others'][] = $f;
		}
	}
	return $out;
}

/**
 * The values a Save sent that differ from the stored ones (unknown keys and equal values are left out).
 *
 * @param array $now  Key => value now (bool, int or string).
 * @param array $sent Key => value sent.
 * @return array Key => new value, typed as the value now.
 */
function sourceweb_changed( array $now, array $sent ): array {
	$out = array();
	foreach ( $sent as $k => $v ) {
		if ( ! array_key_exists( $k, $now ) ) {
			continue;
		}
		$cur = $now[ $k ];
		if ( is_bool( $cur ) ) {
			$v = (bool) $v;
		} elseif ( is_int( $cur ) ) {
			$v = (int) $v;
		} else {
			$v = (string) $v;
		}
		if ( $v !== $cur ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * The enrichment sources' switches after a Save, or null when nothing changed (nothing is written then).
 *
 * @param array $on   Source => on now (aw_enrich_sources_on()).
 * @param array $sent Source => on, as sent (others keep theirs).
 * @return array|null Source => on, every source, in the order of $on.
 */
function sourceweb_enrich_sources_next( array $on, array $sent ): ?array {
	$changed = sourceweb_changed( array_map( 'boolval', $on ), $sent );
	if ( ! $changed ) {
		return null;
	}
	return array_merge( array_map( 'boolval', $on ), $changed );
}

/**
 * The Dashboard's numbers for one day of the log (the time-range chips compare against them).
 *
 * @param array $c Counts from sourceweb_dashboard_build().
 * @return array Metric => number.
 */
function sourceweb_dashboard_numbers( array $c ): array {
	$n = array(
		'review'   => (int) ( $c['review']['total'] ?? 0 ),
		'enrich'   => (int) ( $c['enrich']['pending'] ?? 0 ),
		'applied'  => (int) ( $c['enrich']['applied7'] ?? 0 ),
		'rejected' => (int) ( $c['intake']['rejected_today'] ?? 0 ),
		'sources'  => (int) ( $c['sources']['allow'] ?? 0 ),
	);
	if ( isset( $c['workers']['total'] ) ) {
		$n['workers'] = (int) ( $c['workers']['total'] ?? 0 ) - count( (array) ( $c['workers']['problems'] ?? array() ) );
	}
	return $n;
}

/**
 * Keep one row a day (the last run of the day wins), at most $days days.
 *
 * @param array  $log  Date => numbers.
 * @param string $date Today (Y-m-d).
 * @param array  $row  Numbers.
 * @param int    $days Days kept.
 */
function sourceweb_log_add( array $log, string $date, array $row, int $days ): array {
	$log[ $date ] = $row;
	ksort( $log );
	return array_slice( $log, -$days, null, true );
}

/**
 * One page of a list for the rebuilt screens (Workers, Sources, Keywords, the logs): the view, the search, the sort and
 * the page, done on the server as DataList expects. Rows are already shaped for the screen.
 *
 * @param array $rows All rows, in their default order.
 * @param array $q    { view, search, orderby, order, page }.
 * @param array $o    { views: [ key => [ label, fn( row ): bool ] ] ('' is All, always shown), text: fn( row ): string
 *                    (what the search looks in), sort: [ key => fn( row ) ], per: rows per page (default 20) }.
 * @return array { rows, total, page, pages, view, views: [ { value, label, count } ] }.
 */
function sourceweb_list( array $rows, array $q, array $o ): array {
	$views = (array) ( $o['views'] ?? array() );
	if ( ! isset( $views[''] ) ) {
		$views = array( '' => array( 'All', fn( $r ) => true ) ) + $views;
	}
	$view = (string) ( $q['view'] ?? '' );
	$view = isset( $views[ $view ] ) ? $view : '';
	$out  = array();
	foreach ( $views as $k => $v ) {
		$n = count( array_filter( $rows, $v[1] ) );
		if ( $n || '' === $k || $k === $view ) {
			$out[] = array(
				'value' => (string) $k,
				'label' => (string) $v[0],
				'count' => $n,
			);
		}
	}
	$rows  = array_values( array_filter( $rows, $views[ $view ][1] ) );
	$words = mb_strtolower( trim( (string) ( $q['search'] ?? '' ) ) );
	if ( '' !== $words && isset( $o['text'] ) ) {
		$text = $o['text'];
		$rows = array_values( array_filter( $rows, fn( $r ) => false !== mb_strpos( mb_strtolower( (string) $text( $r ) ), $words ) ) );
	}
	$sort = (array) ( $o['sort'] ?? array() );
	$by   = (string) ( $q['orderby'] ?? '' );
	if ( isset( $sort[ $by ] ) ) {
		$key  = $sort[ $by ];
		$sign = 'desc' === ( $q['order'] ?? 'asc' ) ? -1 : 1;
		// Stable: equal keys keep the default order.
		$i = 0;
		foreach ( $rows as &$r ) {
			$r = array( $key( $r ), $i++, $r );
		}
		unset( $r );
		usort( $rows, fn( $a, $b ) => ( $sign * ( $a[0] <=> $b[0] ) ) ?: ( $a[1] <=> $b[1] ) );
		$rows = array_column( $rows, 2 );
	}
	$per   = max( 1, (int) ( $o['per'] ?? 20 ) );
	$total = count( $rows );
	$pages = max( 1, (int) ceil( $total / $per ) );
	$page  = min( $pages, max( 1, (int) ( $q['page'] ?? 1 ) ) );
	return array(
		'rows'  => array_slice( $rows, ( $page - 1 ) * $per, $per ),
		'total' => $total,
		'page'  => $page,
		'pages' => $pages,
		'view'  => $view,
		'views' => $out,
	);
}
