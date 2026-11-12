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

// ---- sourceweb_list(): views, search, sort, pages for the rebuilt tabs ----
$rows = array();
foreach ( array( array( 'a', 'x', 3 ), array( 'b', 'y', 1 ), array( 'c', 'x', 2 ), array( 'd', 'y', 2 ) ) as $r ) {
	$rows[] = array(
		'id'    => $r[0],
		'title' => 'Worker ' . strtoupper( $r[0] ),
		'group' => $r[1],
		'n'     => $r[2],
	);
}
$o = array(
	'views' => array(
		'x'    => array( 'X', fn( $r ) => 'x' === $r['group'] ),
		'none' => array( 'None', fn( $r ) => false ),
	),
	'text'  => fn( $r ) => $r['title'],
	'sort'  => array( 'n' => fn( $r ) => $r['n'] ),
	'per'   => 3,
);
$l = sourceweb_list( $rows, array(), $o );
aw_t( 4 === $l['total'] && 2 === $l['pages'] && 3 === count( $l['rows'] ) && 'a' === $l['rows'][0]['id'], 'list: all rows, paged, default order kept' );
aw_t( array( '', 'x' ) === array_column( $l['views'], 'value' ) && 4 === $l['views'][0]['count'] && 2 === $l['views'][1]['count'], 'list: All first, an empty view is left out' );
$l = sourceweb_list( $rows, array( 'view' => 'none' ), $o );
aw_t( 'none' === $l['view'] && 0 === $l['total'] && in_array( 'none', array_column( $l['views'], 'value' ), true ), 'list: the open view shows even when empty' );
$l = sourceweb_list( $rows, array( 'view' => 'nope' ), $o );
aw_t( '' === $l['view'] && 4 === $l['total'], 'list: an unknown view is All' );
$l = sourceweb_list( $rows, array( 'view' => 'x', 'search' => 'worker c' ), $o );
aw_t( 1 === $l['total'] && 'c' === $l['rows'][0]['id'], 'list: view and search together, any case' );
$l = sourceweb_list( $rows, array( 'orderby' => 'n' ), $o + array() );
aw_t( array( 'b', 'c', 'd' ) === array_column( $l['rows'], 'id' ), 'list: sort ascending, equal keys keep their order' );
$l = sourceweb_list( $rows, array( 'orderby' => 'n', 'order' => 'desc', 'page' => 2 ), $o );
aw_t( array( 'b' ) === array_column( $l['rows'], 'id' ) && 2 === $l['page'], 'list: sort descending, page 2' );
$l = sourceweb_list( $rows, array( 'page' => 99, 'orderby' => 'evil' ), $o );
aw_t( 2 === $l['page'] && 'd' === $l['rows'][0]['id'], 'list: a page past the end is the last; an unknown sort is ignored' );

// ---- the rebuilt tabs' routes: administrators only, through the one permission check, no key goes out ----
$pages = file_get_contents( dirname( __DIR__ ) . '/includes/sourceweb-rest-pages.php' );
aw_t( false === strpos( $pages, '__return_true' ), 'page routes: none is open to everyone' );
aw_t( 1 === substr_count( $pages, "'permission_callback' => sourceweb_rest_can( \$r[1] )" ), 'page routes: every route registers through the one permission check' );
preg_match_all( "/^\t\t\t'\/[^']+'\s*=> array\(\s*'(GET|POST)',\s*([A-Z_]+),/m", $pages, $m );
aw_t( count( $m[0] ) >= 6 && array( 'AW_SRC_CAP' ) === array_values( array_unique( $m[2] ) ), 'page routes: ' . count( $m[0] ) . ' routes, every one manage_options (AW_SRC_CAP)' );
aw_t( false === strpos( $pages, "\$r['key']," ) && false === strpos( $pages, "'key' =>" ) && false === strpos( $pages, 'aw/v1/ai-keys\'' ), 'page routes: no key field is sent, and the worker route is not reused' );
aw_t( false !== strpos( $pages, 'aw_worker_save(' ) && false !== strpos( $pages, 'aw_worker_delete(' ) && false !== strpos( $pages, 'aw_workers_run_now(' ), 'workers routes: the same save, delete and Run now as the classic screen' );
$wa = file_get_contents( dirname( __DIR__ ) . '/includes/workers-admin.php' );
aw_t( 1 === substr_count( $wa, "if ( \$meta !== \$was ) { // a Save with no change writes nothing\n\t\tupdate_option( 'aw_workers_meta', \$meta, false );" ) && false !== strpos( $wa, "'admin_post_aw_worker_save'" ) && false !== strpos( $wa, 'aw_worker_save(' ), 'workers: the classic form saves through aw_worker_save()' );
aw_t( false !== strpos( $pages, 'aw_sourcing_sources_apply(' ) && false !== strpos( $pages, 'aw_sourcing_shorts_apply(' ) && false !== strpos( $pages, 'aw_sourcing_source_save(' ) && false !== strpos( $pages, 'aw_sources_feed_op(' ), 'sources routes: the same list actions, short names, source save and feed actions as the classic screen' );
$pol = file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-policy.php' );
aw_t( false !== strpos( $pol, 'list( $pubs, $press ) = aw_sourcing_sources_apply( $pubs, $status, $hosts );' ) && false !== strpos( $pol, '$pubs   = aw_sourcing_shorts_apply( $pubs,' ), 'sources: the classic list saves through the shared functions' );
$ui = file_get_contents( dirname( __DIR__ ) . '/includes/sourceweb-ui.php' );
aw_t( false !== strpos( $ui, "const SOURCEWEB_UI_TABS = array( 'sources', 'keywords', 'rules', 'ai', 'workers', 'settings' );" ), 'ui: Sources, Keywords, Rules, AI models, Workers and Settings are drawn on the new screens' );
aw_t( false !== strpos( $ui, "if ( 'rules' === \$tab && ! ( function_exists( 'aw_rules_live' ) && aw_rules_live() ) ) {" ), 'ui: Rules before the phase 3 migration stays on today\'s screens' );
aw_t( 12 === substr_count( $pages, '$ready = sourceweb_rules_ready();' ), 'rules routes: every route refuses before the phase 3 migration (409)' );
aw_t( false !== strpos( $pages, 'aw_sourcing_section_save(' ) && false !== strpos( $pages, 'aw_rules_tag_save(' ) && false !== strpos( $pages, 'aw_rules_tag_delete(' ) && false !== strpos( $pages, 'aw_rules_priority_save(' ) && false !== strpos( $pages, 'aw_rules_field_save(' ) && false !== strpos( $pages, 'aw_summary_rules_sanitize(' ), 'rules routes: the same section, tag rule, trust order, field rule and summary saves as today\'s screen' );
$ra = file_get_contents( dirname( __DIR__ ) . '/includes/rules-admin.php' );
$st = file_get_contents( dirname( __DIR__ ) . '/includes/sourcing-terms-admin.php' );
aw_t( false !== strpos( $ra, 'list( $done, $for ) = aw_rules_tag_save( $tag, $original, $in );' ) && false !== strpos( $ra, 'list( $done, $for ) = aw_rules_tag_delete( $tag );' ) && false !== strpos( $ra, 'list( $done, $for ) = aw_rules_priority_save( $pos );' ) && false !== strpos( $ra, 'list( $done, $for ) = aw_rules_field_save( $field, $in );' ) && false !== strpos( $st, '$done = aw_sourcing_section_save(' ), 'rules: today\'s forms save through the shared functions' );
aw_t( false !== strpos( $ui, "if ( 'keywords' === \$tab && ! ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) ) {" ), 'ui: Keywords before the keyword table stays on today\'s screen' );
aw_t( false !== strpos( $pages, 'aw_sourcing_kw_add(' ) && false !== strpos( $pages, 'aw_sourcing_kw_apply(' ) && false !== strpos( $pages, 'aw_sourcing_kw_bulk(' ) && false !== strpos( $pages, 'aw_relevance_verdict(' ) && false !== strpos( $pages, 'aw_relevance_switches_sanitize(' ) && false !== strpos( $pages, 'aw_sources_sanitize(' ) && false !== strpos( $pages, 'aw_sourcing_suggestions()' ) && false !== strpos( $pages, 'aw_sourcing_suggest_apply(' ), 'keywords routes: the same add, edit, list actions, test, switches, Press settings and suggestions as today\'s screen' );
$kwa = file_get_contents( dirname( __DIR__ ) . '/includes/keywords-admin.php' );
$kwt = file_get_contents( dirname( __DIR__ ) . '/includes/class-aw-sourcing-keywords-table.php' );
aw_t( false !== strpos( $kwa, 'list( $n, $errs ) = aw_sourcing_kw_bulk( $op, $ids );' ) && false !== strpos( $kwt, '$this->all = aw_sourcing_kw_records();' ) && false !== strpos( $kwt, 'aw_sourcing_kw_in_view( $r, $v )' ), 'keywords: the classic list and its actions use the shared functions' );
aw_t( 7 === substr_count( $pages, '$ready = sourceweb_keywords_ready();' ), 'keywords routes: every save and list refuses before the keyword table (409)' );

// ---- AI models: its own routes, no key goes out, the same saves as today's tab ----
$air = file_get_contents( dirname( __DIR__ ) . '/includes/sourceweb-rest-ai.php' );
$aik = file_get_contents( dirname( __DIR__ ) . '/includes/ai-keys.php' );
$ait = file_get_contents( dirname( __DIR__ ) . '/includes/class-aw-sourcing-ai-keys-table.php' );
aw_t( false === strpos( $air, '__return_true' ) && 1 === substr_count( $air, "'permission_callback' => sourceweb_rest_can( AW_SRC_CAP )" ), 'ai routes: every route through the one permission check, manage_options' );
preg_match_all( "/^\t\t\t'\/ai[^']*'\s*=> array\(\s*'(GET|POST)'/m", $air, $m );
aw_t( 7 === count( $m[0] ), 'ai routes: 7 routes under sourceweb/v1 (' . count( $m[0] ) . ')' );
aw_t( false === strpos( $air, "'key' =>" ) && false === strpos( $air, "'aw/v1'" ) && false === strpos( $air, "'/ai-keys'" ), 'ai routes: no key field is sent, and the workers\' route aw/v1/ai-keys is not reused' );
aw_t( substr_count( $air, "\$r['key']" ) === substr_count( $air, "aw_ai_key_id( \$r['key'] )" ) + substr_count( $air, "substr( \$r['key'], -4 )" ), 'ai routes: a key is read only for its id and its last 4 characters' );
aw_t( false !== strpos( $air, 'aw_ai_keys_add(' ) && false !== strpos( $air, 'aw_ai_key_edit_save(' ) && false !== strpos( $air, 'aw_ai_keys_list_op(' ) && false !== strpos( $air, 'aw_ai_key_records()' ) && false !== strpos( $air, 'aw_ai_overview_data(' ), 'ai routes: the same add, edit, list actions, list and overview as today\'s tab' );
aw_t( false !== strpos( $aik, '$res = aw_ai_keys_add(' ) && false !== strpos( $aik, '$n = (int) aw_ai_key_edit_save(' ) && false !== strpos( $aik, '$res  = aw_ai_keys_list_op(' ) && false !== strpos( $ait, '$this->all = aw_ai_key_records();' ), 'ai: today\'s forms and list use the shared functions' );
aw_t( false !== strpos( $aik, "if ( \$rows !== \$orig ) {\n\t\tupdate_option( 'aw_ai_models', \$rows, false );" ) && false !== strpos( $aik, "if ( ! \$what ) {\n\t\t\treturn 0;" ), 'ai: a list action or edit with no change writes nothing' );

