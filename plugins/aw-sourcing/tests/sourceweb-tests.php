<?php
/**
 * SourceWeb's new screens (sourceweb-model.php): the To review list shaper, the Listing details grouping, the day log
 * and "save only what changed". Plain PHP, required by tests/run.php after its helpers.
 *
 * @package aw-sourcing
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- a command-line test runner.

require dirname( __DIR__ ) . '/includes/sourceweb-model.php';

// ---- why it waits ----
aw_t( 'no keyword hit' === sourceweb_why_text( 'held: no keyword hit', 'draft' ), 'why: "held: " is dropped' );
aw_t( 'waiting for a first review' === sourceweb_why_text( '', 'pending' ), 'why: a pending item with no note' );
aw_t( '' === sourceweb_why_text( '', 'draft' ), 'why: a draft with no note says nothing' );
aw_t( 'publisher neutral' === sourceweb_why_key( 'Publisher neutral: thehindu.com (2 hits)' ), 'why key: the words before the colon, lower case' );

// ---- To review list ----
$labels = array(
	'aw_news'  => 'News',
	'aw_legal' => 'Library',
	'aw_press' => 'Press',
);
$items  = array();
foreach (
	array(
		array( 1, 'Dog rescue in Pune', 'aw_news', '2026-11-10 09:00:00', 'thehindu.com', 'no keyword hit' ),
		array( 2, 'Court order on feeding', 'aw_legal', '2026-11-09 09:00:00', 'livelaw.in', 'publisher neutral: livelaw.in' ),
		array( 3, 'Animal Writes in the news', 'aw_press', '2026-11-08 09:00:00', 'thehindu.com', 'waiting for a first review' ),
		array( 4, 'Another dog story', 'aw_news', '2026-11-07 09:00:00', '', 'no keyword hit' ),
	) as $r
) {
	$items[] = array(
		'id'        => $r[0],
		'title'     => $r[1],
		'type'      => $r[2],
		'typeLabel' => $labels[ $r[2] ],
		'date'      => $r[3],
		'host'      => $r[4],
		'why'       => $r[5],
		'excerpt'   => '',
		'url'       => $r[4] ? 'https://' . $r[4] . '/x' : '',
	);
}
$l = sourceweb_review_list( $items, array(), $labels );
aw_t( 4 === $l['total'] && 4 === $l['count'] && 1 === $l['pages'] && 1 === $l['rows'][0]['id'], 'review: all items, newest first' );
aw_t( 4 === $l['views'][0]['count'] && 'News' === $l['views'][1]['label'] && 2 === $l['views'][1]['count'] && 4 === count( $l['views'] ), 'review: views by type with counts' );
aw_t( 'thehindu.com' === $l['filters']['source'][1]['value'] && 'All sources' === $l['filters']['source'][0]['label'], 'review: source filter, most items first' );
$l = sourceweb_review_list( $items, array( 'type' => 'aw_news' ), $labels );
aw_t( 2 === $l['total'] && 2 === $l['count'] && 'aw_news' === $l['type'], 'review: one type' );
$l = sourceweb_review_list( $items, array( 'type' => 'nope' ), $labels );
aw_t( '' === $l['type'] && 4 === $l['total'], 'review: an unknown type is All' );
$l = sourceweb_review_list( $items, array( 'search' => 'DOG' ), $labels );
aw_t( 2 === $l['total'] && 4 === $l['count'], 'review: search is case-blind; Publish all still counts the view' );
$l = sourceweb_review_list( $items, array( 'source' => 'thehindu.com' ), $labels );
aw_t( array( 1, 3 ) === array_column( $l['rows'], 'id' ), 'review: filter by source' );
$l = sourceweb_review_list( $items, array( 'why' => 'no keyword hit' ), $labels );
aw_t( array( 1, 4 ) === array_column( $l['rows'], 'id' ), 'review: filter by reason' );
$l = sourceweb_review_list( $items, array( 'orderby' => 'title' ), $labels );
aw_t( array( 3, 4, 2, 1 ) === array_column( $l['rows'], 'id' ), 'review: sort by title, A to Z' );
$l = sourceweb_review_list(
	$items,
	array(
		'orderby' => 'date',
		'order'   => 'asc',
	),
	$labels
);
aw_t( 4 === $l['rows'][0]['id'], 'review: oldest first' );
$l = sourceweb_review_list( $items, array( 'page' => 2 ), $labels, 3 );
aw_t( 2 === $l['pages'] && 2 === $l['page'] && array( 4 ) === array_column( $l['rows'], 'id' ), 'review: page 2 of 2' );
$l = sourceweb_review_list( $items, array( 'page' => 9 ), $labels, 3 );
aw_t( 2 === $l['page'], 'review: a page past the end is the last page' );
$l = sourceweb_review_list( array(), array(), $labels );
aw_t( 0 === $l['total'] && 1 === $l['pages'] && array() === $l['rows'] && 1 === count( $l['views'] ), 'review: nothing waiting' );

// ---- Listing details ----
$row = fn( $id, $status ) => array(
	'id'         => $id,
	'field'      => 'phone',
	'label'      => 'Phone',
	'current'    => '',
	'proposed'   => '98100 00000',
	'status'     => $status,
	'confidence' => 'high',
	'source'     => 'Wikidata',
	'note'       => '',
	'created'    => '1 Nov 2026',
);
$f   = sourceweb_enrich_field( $row( 7, 'auto-applied' ) );
aw_t( $f['applied'] && ! $f['waiting'] && 7 === $f['key'] && '' === $f['undoOff'], 'enrich: auto-applied is applied, with Undo' );
$f = sourceweb_enrich_field( $row( 8, 'pending' ) );
aw_t( ! $f['applied'] && $f['waiting'], 'enrich: pending waits' );
$fields = array();
foreach ( array( array( 1, 'pending', 10 ), array( 2, 'approved', 10 ), array( 3, 'rejected', 10 ), array( 4, 'pending', 20 ), array( 5, 'pending', 99 ) ) as $x ) {
	$one            = sourceweb_enrich_field( $row( $x[0], $x[1] ) );
	$one['listing'] = $x[2];
	$fields[]       = $one;
}
$g = sourceweb_enrich_group( array( 20, 10 ), $fields );
aw_t( array( 20, 10 ) === array_keys( $g ), 'enrich: listings in list order, others left out' );
aw_t( 2 === count( $g[10]['fields'] ) && 1 === count( $g[10]['others'] ) && 1 === $g[10]['waiting'] && 1 === $g[10]['applied'], 'enrich: waiting and applied in the review, rejected apart' );
aw_t( ! isset( $g[10]['fields'][0]['listing'] ), 'enrich: no listing key in a field' );

// ---- Save only what changed ----
$now = array(
	'live'     => false,
	'live_cap' => 20,
	'name'     => 'x',
);
aw_t(
	array() === sourceweb_changed(
		$now,
		array(
			'live'     => false,
			'live_cap' => '20',
			'name'     => 'x',
		)
	),
	'changed: an unchanged Save changes nothing'
);
aw_t( array() === sourceweb_changed( $now, array( 'other' => true ) ), 'changed: unknown keys are left out' );
aw_t( array( 'live' => true ) === sourceweb_changed( $now, array( 'live' => 1 ) ), 'changed: a switch, typed as bool' );
aw_t(
	array( 'live_cap' => 30 ) === sourceweb_changed(
		$now,
		array(
			'live_cap' => '30',
			'live'     => '',
		)
	),
	'changed: a number, typed as int'
);
$on = array(
	'claim'  => true,
	'osm'    => true,
	'darpan' => false,
);
aw_t( null === sourceweb_enrich_sources_next( $on, $on ), 'sources: unchanged gives null (nothing written)' );
aw_t( null === sourceweb_enrich_sources_next( $on, array() ), 'sources: nothing sent gives null' );
$next = sourceweb_enrich_sources_next( $on, array( 'osm' => false ) );
aw_t(
	array(
		'claim'  => true,
		'osm'    => false,
		'darpan' => false,
	) === $next,
	'sources: one switched off, the others kept, same order'
);

// ---- Dashboard log ----
$n = sourceweb_dashboard_numbers(
	array(
		'review'  => array( 'total' => 5 ),
		'enrich'  => array(
			'pending'  => 9,
			'applied7' => 3,
		),
		'intake'  => array( 'rejected_today' => 2 ),
		'sources' => array( 'allow' => 40 ),
		'workers' => array(
			'total'    => 19,
			'problems' => array( 'a', 'b' ),
		),
	)
);
aw_t(
	array(
		'review'   => 5,
		'enrich'   => 9,
		'applied'  => 3,
		'rejected' => 2,
		'sources'  => 40,
		'workers'  => 17,
	) === $n,
	'log: the numbers a day keeps'
);
aw_t( ! isset( sourceweb_dashboard_numbers( array() )['workers'] ), 'log: no workers number without the workers' );
$log = array();
for ( $d = 1; $d <= 5; $d++ ) {
	$log = sourceweb_log_add( $log, sprintf( '2026-11-%02d', $d ), array( 'review' => $d ), 3 );
}
$log = sourceweb_log_add( $log, '2026-11-05', array( 'review' => 50 ), 3 );
aw_t( array( '2026-11-03', '2026-11-04', '2026-11-05' ) === array_keys( $log ) && 50 === $log['2026-11-05']['review'], 'log: one row a day, the last run wins, the oldest go' );

// ---- the routes: a capability on every one, none open ----
$rest = file_get_contents( dirname( __DIR__ ) . '/includes/sourceweb-rest.php' );
aw_t( false === strpos( $rest, '__return_true' ), 'routes: none is open to everyone' );
aw_t( 1 === substr_count( $rest, "'permission_callback' => sourceweb_rest_can( \$r[1] )" ), 'routes: every route registers through the one permission check' );
preg_match_all( "/^\t\t\t'\/[^']+'\s*=> array\(\s*'(GET|POST)',\s*(AW_REVIEW_CAP|AW_ENRICH_CAP|AW_SRC_CAP),/m", $rest, $m );
aw_t( 10 === count( $m[0] ), 'routes: 10 routes, each with a capability (' . count( $m[0] ) . ')' );
aw_t( false === strpos( $rest, "'key'     => (string) get_option( 'aw_enrich_google_key'" ) && false !== strpos( $rest, "'key'     => array( 'set' => \$key )" ), 'routes: the Google key goes out as set or not set only' );
