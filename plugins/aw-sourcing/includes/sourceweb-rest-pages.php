<?php
/**
 * SourceWeb's routes for the screens rebuilt on the Kits shell after 12 Nov 2026, under sourceweb/v1. Each calls the
 * functions today's screens use, so both screens save the same way and log to the same change log. Administrators
 * only (manage_options):
 *
 *   GET  workers                 the Workers list (view, search, orderby, order, page), the Run now log
 *   GET  workers/<id>            one worker for its form ('new' for Add New Worker), with the keys it may use
 *   GET  workers/<id>/runs       the runs the workflow reported, newest first
 *   POST workers/save            save a worker (aw_worker_save())
 *   POST workers/delete          delete a Planned worker (aw_worker_delete())
 *   POST workers/run             Run now (aw_workers_run_now())
 *   GET  sources                 the Sources list (view, search, orderby, order, page)
 *   POST sources/decide          a list action on hosts (aw_sourcing_sources_apply())
 *   POST sources/short           short names for card chips (aw_sourcing_shorts_apply())
 *   GET  sources/one             one source for its form (host; '' for Add New Source), with its feeds
 *   POST sources/save            save the source form (aw_sourcing_source_save())
 *   GET  sources/feeds           every feed (type, state, search, page), or one site's (host)
 *   POST sources/feeds/decide    on, off, keyword check on/off, delete (aw_sources_feed_op())
 *   GET  keywords                the Keywords list (view, cat, search, orderby, order, page), the two switches, Press settings
 *   GET  keywords/one            one keyword for its form (id; 0 for Add New Keyword)
 *   POST keywords/save           add (aw_sourcing_kw_add()), edit or delete from sourcing (aw_sourcing_kw_apply())
 *   POST keywords/decide         a row or bulk action on ids (aw_sourcing_kw_bulk())
 *   GET  keywords/test           Test a headline (aw_relevance_verdict())
 *   POST keywords/settings       relevance filter, aggregator feeds, Press discovery (the Settings API forms' own sanitizers)
 *   GET  keywords/suggestions    searches from Search Insights no keyword covers (aw_sourcing_suggestions())
 *   POST keywords/suggestions/decide  include, exclude, search, dismiss, restore (aw_sourcing_suggest_apply())
 *   GET  rules/sections          Section rules: the sections and the tag rules
 *   GET  rules/section           one section's allowed categories and tags (type)
 *   POST rules/section/save      save them (aw_sourcing_section_save())
 *   GET  rules/tag               one tag rule for its form (tag; '' for Add New Rule), with the tags to choose from
 *   POST rules/tag/save          add or edit a tag rule (aw_rules_tag_save())
 *   POST rules/tag/delete        delete a tag rule (aw_rules_tag_delete())
 *   GET  rules/enrich            Directory enrichment: the trust order and the field rules
 *   POST rules/priority          save the trust order (aw_rules_priority_save())
 *   GET  rules/field             one field's rule for its form (field)
 *   POST rules/field/save        save it (aw_rules_field_save())
 *   GET  rules/summary           Summary rules, as the form shows them
 *   POST rules/summary/save      save them through the form's sanitizer (aw_summary_rules_sanitize())
 *
 * Keys never go out: a key is its id (a hash) and its last 4 characters.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$text   = array(
			'type'    => 'string',
			'default' => '',
		);
		$page   = array(
			'type'    => 'integer',
			'default' => 1,
		);
		$list   = array(
			'view'    => $text,
			'search'  => $text,
			'orderby' => $text,
			'order'   => $text,
			'page'    => $page,
		);
		$worker = array(
			'type'     => 'string',
			'required' => true,
		);
		$hosts  = array(
			'type'    => 'array',
			'items'   => array( 'type' => 'string' ),
			'default' => array(),
		);
		$flag   = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$routes = array(
			'/workers'                                => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_workers', $list ),
			'/workers/(?P<id>[a-z0-9_-]+)'            => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_worker', array() ),
			'/workers/(?P<id>[a-z0-9_-]+)/runs'       => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_worker_runs', array() ),
			'/workers/save'                           => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_worker_save',
				array(
					'edit'      => $worker,
					'id'        => $text,
					'name'      => $text,
					'what'      => $text,
					'schedule'  => $text,
					'late'      => $text,
					'group'     => $text,
					'group_new' => $text,
					'keys'      => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
					'keysShown' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			'/workers/delete'                         => array( 'POST', AW_SRC_CAP, 'sourceweb_rest_worker_delete', array( 'id' => $worker ) ),
			'/workers/run'                            => array( 'POST', AW_SRC_CAP, 'sourceweb_rest_worker_run', array( 'id' => $worker ) ),
			'/sources'                                => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_sources', $list ),
			'/sources/decide'                         => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_sources_decide',
				array(
					'action' => array(
						'type'     => 'string',
						'enum'     => array( 'allow', 'neutral', 'block', 'press_add', 'press_remove', 'relay_on', 'relay_off', 'delete' ),
						'required' => true,
					),
					'hosts'  => $hosts,
				),
			),
			'/sources/short'                          => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_sources_short',
				array(
					'short' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
			'/sources/one'                            => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_source', array( 'host' => $text ) ),
			'/sources/save'                           => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_source_save',
				array(
					'host'   => $text,
					'status' => $text,
					'name'   => $text,
					'short'  => $text,
					'press'  => $flag,
					'relay'  => $flag,
				),
			),
			'/sources/feeds'                          => array(
				'GET',
				AW_SRC_CAP,
				'sourceweb_rest_feeds',
				array(
					'host'   => $text,
					'type'   => $text,
					'state'  => $text,
					'search' => $text,
					'page'   => $page,
				),
			),
			'/sources/feeds/decide'                   => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_feeds_decide',
				array(
					'op'  => array(
						'type'     => 'string',
						'enum'     => array( 'on', 'off', 'filter_on', 'filter_off', 'delete' ),
						'required' => true,
					),
					'ids' => $hosts,
				),
			),
			'/keywords'                               => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_keywords', $list + array( 'cat' => $text ) ),
			'/keywords/one'                           => array(
				'GET',
				AW_SRC_CAP,
				'sourceweb_rest_keyword',
				array(
					'id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			),
			'/keywords/save'                          => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_keyword_save',
				array(
					'id'         => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'label'      => $text,
					'variants'   => $text,
					'lang'       => array(
						'type'    => 'string',
						'enum'    => array( 'en', 'hi', 'hinglish' ),
						'default' => 'en',
					),
					'roles'      => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
					'categories' => $hosts,
					'sections'   => $hosts,
					'search_on'  => $flag,
					'query'      => $text,
					'delete'     => $flag,
				),
			),
			'/keywords/decide'                        => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_keywords_decide',
				array(
					'op'  => array(
						'type'     => 'string',
						'pattern'  => '^(delete|(add|remove)_[0-9]+|cat_[a-z0-9_-]+)$',
						'required' => true,
					),
					'ids' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'integer' ),
						'default' => array(),
					),
				),
			),
			'/keywords/test'                          => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_keywords_test', array( 'text' => $text ) ),
			'/keywords/settings'                      => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_keywords_settings',
				array(
					'enabled'          => $flag,
					'gnews_allow_only' => $flag,
					'press'            => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
			'/keywords/suggestions'                   => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_suggestions', $list ),
			'/keywords/suggestions/decide'            => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_suggestions_decide',
				array(
					'op' => array(
						'type'     => 'string',
						'enum'     => array( 'include', 'exclude', 'search', 'dismiss', 'restore' ),
						'required' => true,
					),
					'qs' => $hosts,
				),
			),
			'/rules/sections'                         => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_sections', array() ),
			'/rules/section'                          => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_section', array( 'type' => $text ) ),
			'/rules/section/save'                     => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_rules_section_save',
				array(
					'type'       => $text,
					'categories' => $hosts,
					'tags'       => $hosts,
					'gone'       => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
			'/rules/tag'                              => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_tag', array( 'tag' => $text ) ),
			'/rules/tag/save'                         => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_rules_tag_save',
				array(
					'tag'         => $text,
					'original'    => $text,
					'any'         => $text,
					'hold'        => $text,
					'unless'      => $text,
					'hold_reason' => $text,
				),
			),
			'/rules/tag/delete'                       => array( 'POST', AW_SRC_CAP, 'sourceweb_rest_rules_tag_delete', array( 'tag' => $worker ) ),
			'/rules/enrich'                           => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_enrich', array() ),
			'/rules/priority'                         => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_rules_priority',
				array(
					'pos' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
			'/rules/field'                            => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_field', array( 'field' => $text ) ),
			'/rules/field/save'                       => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_rules_field_save',
				array(
					'field' => $worker,
					'rule'  => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
			'/rules/summary'                          => array( 'GET', AW_SRC_CAP, 'sourceweb_rest_rules_summary', array() ),
			'/rules/summary/save'                     => array(
				'POST',
				AW_SRC_CAP,
				'sourceweb_rest_rules_summary_save',
				array(
					'values' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			),
		);
		foreach ( $routes as $path => $r ) {
			register_rest_route(
				SOURCEWEB_REST,
				$path,
				array(
					'methods'             => $r[0],
					'permission_callback' => sourceweb_rest_can( $r[1] ),
					'callback'            => $r[2],
					'args'                => $r[3],
				)
			);
		}
	}
);

/* ---------- Workers ---------- */

/** Worker status => label. */
const SOURCEWEB_WORKER_STATUS = array(
	'active'  => 'Active',
	'late'    => 'Late',
	'failing' => 'Failing',
	'planned' => 'Planned',
);

/**
 * A reported run as plain text: result and its tone, the failed step, when, how long, the counts.
 *
 * @param array|null $l A run from aw_workers_last() or the runs option.
 * @return array|null { result, label, tone, when, took, counts }.
 */
function sourceweb_worker_run_text( $l ) {
	if ( ! $l ) {
		return null;
	}
	$map  = array(
		'success'   => array( 'success', 'Success' ),
		'running'   => array( 'info', 'Running' ),
		'failed'    => array( 'error', 'Failed' ),
		'cancelled' => array( 'error', 'Cancelled' ),
		'skipped'   => array( 'warn', 'Skipped' ),
	);
	$res  = (string) ( $l['result'] ?? '' );
	$m    = $map[ $res ] ?? $map['failed'];
	$step = '';
	if ( ! empty( $l['failed_step'] ) ) {
		$step = ': step "' . $l['failed_step'] . '"';
	} elseif ( 'skipped' === $res ) {
		$step = ': step "' . ( $l['main_step'] ?? 'main' ) . '" did not run';
	}
	$counts = (array) ( $l['counts'] ?? array() );
	return array(
		'result' => $res,
		'label'  => $m[1] . $step . ( ! empty( $l['dry_run'] ) ? ' (dry run)' : '' ),
		'tone'   => $m[0],
		'when'   => ! empty( $l['started'] ) ? wp_date( 'j M Y, g:i a', (int) $l['started'] ) : '',
		'took'   => ! empty( $l['ended'] ) && ! empty( $l['started'] ) && $l['ended'] >= $l['started'] ? aw_workers_duration( $l['ended'] - $l['started'] ) : '',
		'counts' => implode( ', ', array_map( fn( $k, $v ) => str_replace( '_', ' ', (string) $k ) . ': ' . $v, array_keys( $counts ), $counts ) ),
	);
}

/**
 * One row of the Workers list.
 *
 * @param array $r A row from aw_workers_status_rows().
 */
function sourceweb_worker_row( array $r ): array {
	$groups      = aw_worker_groups();
	list( $own, $all ) = $r['keys'];
	if ( ! $own ) {
		$keys = 'All keys';
	} else {
		/* translators: %d: number of keys. */
		$keys = sprintf( _n( '%d key attached', '%d keys attached', $own, 'aw-sourcing' ), $own ) . ( $all ? ' (+ ' . $all . ' for all workers)' : '' );
	}
	$note = '';
	if ( 'planned' === $r['status'] ) {
		$note = 'Not built yet';
	} elseif ( 'active' === $r['status'] && $r['planned'] ) {
		$note = 'Reported, not in the registry yet';
	}
	return array(
		'id'          => $r['id'],
		'title'       => $r['name'],
		'what'        => $r['what'],
		'group'       => $r['group'],
		'groupLabel'  => (string) ( $groups[ $r['group'] ][0] ?? $r['group'] ),
		'file'        => $r['file'],
		'fileUrl'     => $r['planned'] ? '' : 'https://github.com/wugweb-git/animal-writes-workers/blob/main/.github/workflows/' . rawurlencode( $r['file'] ),
		'planned'     => (bool) $r['planned'],
		'schedule'    => $r['planned'] ? ( '' !== $r['schedule'] ? 'Wanted: ' . $r['schedule'] : '' ) : aw_workers_schedule( $r['registry'] + array( 'file' => $r['file'] ) ),
		'late'        => $r['planned'] ? '' : aw_workers_late_words( $r ),
		'last'        => sourceweb_worker_run_text( $r['last'] ),
		'lastAt'      => (int) ( $r['last']['started'] ?? 0 ),
		'status'      => $r['status'],
		'statusLabel' => SOURCEWEB_WORKER_STATUS[ $r['status'] ],
		'statusNote'  => $note,
		'problem'     => in_array( $r['status'], array( 'late', 'failing' ), true ) ? $r['note'] : '',
		'keys'        => $keys,
		'canRun'      => aw_workers_can_run( $r ),
	);
}

/** The groups for the worker form: [ { value, label } ]. */
function sourceweb_worker_groups(): array {
	$out = array();
	foreach ( aw_worker_groups() as $slug => $g ) {
		$out[] = array(
			'value' => (string) $slug,
			'label' => (string) $g[0],
		);
	}
	return $out;
}

/**
 * GET workers: one page of the list, as on today's Workers tab (views by group, Late, Failing, Planned), and the Run
 * now log.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_workers( WP_REST_Request $request ) {
	$rows  = array_map( 'sourceweb_worker_row', aw_workers_status_rows() );
	// Late and failing first, then the registry's order, Planned last (as today's list).
	$first = array_values( array_filter( $rows, fn( $r ) => in_array( $r['status'], array( 'late', 'failing' ), true ) ) );
	$rest  = array_values( array_filter( $rows, fn( $r ) => ! in_array( $r['status'], array( 'late', 'failing' ), true ) ) );
	$views = array();
	foreach ( aw_worker_groups() as $slug => $g ) {
		$views[ 'group-' . $slug ] = array( $g[0], fn( $r ) => $r['group'] === $slug );
	}
	$views['late']    = array( 'Late', fn( $r ) => 'late' === $r['status'] );
	$views['failing'] = array( 'Failing', fn( $r ) => 'failing' === $r['status'] );
	$views['planned'] = array( 'Planned', fn( $r ) => $r['planned'] );
	$order            = array_flip( array_keys( SOURCEWEB_WORKER_STATUS ) );
	$out              = sourceweb_list(
		array_merge( $first, $rest ),
		$request->get_params(),
		array(
			'views' => $views,
			'text'  => fn( $r ) => $r['id'] . ' ' . $r['title'] . ' ' . $r['what'] . ' ' . $r['file'] . ' ' . $r['groupLabel'],
			'sort'  => array(
				'title'    => fn( $r ) => strtolower( $r['title'] ),
				'group'    => fn( $r ) => strtolower( $r['groupLabel'] . ' ' . $r['title'] ),
				'last_run' => fn( $r ) => $r['lastAt'],
				'status'   => fn( $r ) => $order[ $r['status'] ],
			),
			'per'   => 50,
		)
	);
	$log = array();
	foreach ( (array) get_option( 'aw_worker_dispatch_log', array() ) as $e ) {
		$w     = aw_worker( aw_worker_id( (string) ( $e['file'] ?? '' ) ) );
		$log[] = array(
			wp_date( 'j M Y, g:i a', (int) ( $e['at'] ?? 0 ) ),
			(string) ( $e['user'] ?? '' ),
			$w ? $w['name'] : (string) ( $e['file'] ?? '' ),
			! empty( $e['ok'] ) ? 'started' : (string) ( $e['msg'] ?? '' ),
		);
	}
	$out['log']    = $log;
	$out['runOff'] = ! defined( 'AW_GITHUB_DISPATCH_TOKEN' ) || '' === (string) AW_GITHUB_DISPATCH_TOKEN;
	return rest_ensure_response( $out );
}

/**
 * GET workers/<id>: one worker for its form, or a blank Planned worker for 'new'. The keys come as id, last 4
 * characters and provider only.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_worker( WP_REST_Request $request ) {
	$id  = sanitize_key( (string) $request['id'] );
	$new = 'new' === $id;
	$w   = $new ? null : aw_worker( $id );
	if ( ! $new && ! $w ) {
		return new WP_Error( 'sourceweb_no_worker', __( 'No such worker.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$keys = array();
	foreach ( aw_ai_rows() as $r ) {
		$ws     = aw_ai_row_workers( $r );
		$keys[] = array(
			'id'    => aw_ai_key_id( (string) $r['key'] ),
			'short' => aw_ai_key_short( $r ),
			'scope' => $ws ? sprintf( /* translators: %d: number of workers. */ _n( 'for %d worker', 'for %d workers', count( $ws ), 'aw-sourcing' ), count( $ws ) ) : __( 'all workers', 'aw-sourcing' ),
			'on'    => ! $ws || ( ! $new && in_array( $id, $ws, true ) ),
		);
	}
	$reg = $w && $w['registry'] ? $w['registry'] : null;
	return rest_ensure_response(
		array(
			'id'       => $new ? 'new' : $w['id'],
			'new'      => $new,
			'planned'  => $new || $w['planned'],
			'name'     => $w ? $w['name'] : '',
			'what'     => $w ? $w['what'] : '',
			'group'    => $w ? $w['group'] : 'one-off',
			'late'     => $w && null !== $w['late_after_h'] && '' !== $w['late_after_h'] ? (string) (int) $w['late_after_h'] : '',
			'schedule' => $w ? $w['schedule'] : '',
			'registry' => $reg ? array(
				'name'     => (string) $reg['name'],
				'late'     => null === $reg['late_after_h'] ? 'never' : $reg['late_after_h'] . ' h',
				'schedule' => aw_workers_schedule( $reg + array( 'file' => $w['file'] ) ),
				'file'     => $w['file'],
				'fileUrl'  => 'https://github.com/wugweb-git/animal-writes-workers/blob/main/.github/workflows/' . rawurlencode( $w['file'] ),
			) : null,
			'groups'   => sourceweb_worker_groups(),
			'keys'     => $keys,
			'addKey'   => aw_ai_keys_url( array( 'edit' => 'new' ) ),
		)
	);
}

/**
 * GET workers/<id>/runs: the runs the workflow reported, newest first.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_worker_runs( WP_REST_Request $request ) {
	$w = aw_worker( (string) $request['id'] );
	if ( ! $w ) {
		return new WP_Error( 'sourceweb_no_worker', __( 'No such worker.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$runs = (array) get_option( aw_workers_option( $w['file'] ), array() );
	krsort( $runs, SORT_NUMERIC );
	$ev   = array(
		'schedule'          => 'scheduled',
		'workflow_dispatch' => 'started by hand or from the site',
		'workflow_run'      => 'after another workflow',
		'push'              => 'push',
	);
	$rows = array();
	foreach ( $runs as $r ) {
		$t      = sourceweb_worker_run_text( $r );
		$sha    = (string) ( $r['sha'] ?? '' );
		$rows[] = array(
			'id'      => (string) ( $r['run_id'] ?? '' ),
			'attempt' => (int) ( $r['attempt'] ?? 1 ),
			'link'    => esc_url_raw( (string) ( $r['link'] ?? '' ) ),
			'when'    => $t['when'],
			'took'    => $t['took'],
			'label'   => $t['label'],
			'tone'    => $t['tone'],
			'event'   => (string) ( $ev[ $r['event'] ?? '' ] ?? ( $r['event'] ?? '' ) ),
			'counts'  => $t['counts'],
			'commit'  => '' !== $sha ? substr( $sha, 0, 7 ) : '',
			'commitUrl' => '' !== $sha ? 'https://github.com/wugweb-git/animal-writes-workers/commit/' . rawurlencode( $sha ) : '',
		);
	}
	return rest_ensure_response(
		array(
			'id'        => $w['id'],
			'name'      => $w['name'],
			'planned'   => (bool) $w['planned'],
			'keep'      => (int) AW_WORKERS_KEEP,
			'githubUrl' => $w['planned'] ? '' : 'https://github.com/wugweb-git/animal-writes-workers/actions/workflows/' . rawurlencode( $w['file'] ),
			'rows'      => $rows,
		)
	);
}

/**
 * POST workers/save.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_worker_save( WP_REST_Request $request ) {
	$res = aw_worker_save(
		array(
			'edit'      => (string) $request['edit'],
			'id'        => (string) $request['id'],
			'name'      => (string) $request['name'],
			'what'      => (string) $request['what'],
			'schedule'  => (string) $request['schedule'],
			'late'      => (string) $request['late'],
			'group'     => (string) $request['group'],
			'group_new' => (string) $request['group_new'],
			'keys'      => $request['keysShown'] ? array_map( 'strval', (array) $request['keys'] ) : null,
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'sourceweb_worker_' . $res->get_error_code(), $res->get_error_message(), array( 'status' => 'missing' === $res->get_error_code() ? 404 : 400 ) );
	}
	$msg = array(
		'added' => 'Planned worker "%s" added. It shows "Not built yet" until a workflow with its ID reports a run.',
		'saved' => 'Worker "%s" saved.',
		'same'  => 'Nothing changed for "%s".',
	);
	$out = sprintf( $msg[ $res['done'] ], $res['name'] );
	if ( $res['skip'] ) {
		/* translators: %d: number of keys. */
		$out .= ' ' . sprintf( _n( '%d key was left ticked: it is for this worker only, and a key needs at least one worker. Remove it on AI models instead.', '%d keys were left ticked: each is for this worker only, and a key needs at least one worker. Remove them on AI models instead.', $res['skip'], 'aw-sourcing' ), $res['skip'] );
	}
	return rest_ensure_response( $res + array( 'message' => $out ) );
}

/**
 * POST workers/delete: a Planned worker only.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_worker_delete( WP_REST_Request $request ) {
	$res = aw_worker_delete( (string) $request['id'] );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return rest_ensure_response(
		array(
			'message' => sprintf( 'Planned worker "%s" deleted.', $res['name'] ) . ( $res['freed'] ? ' ' . sprintf( 'Now for all workers: %s.', implode( ', ', $res['freed'] ) ) : '' ),
		)
	);
}

/**
 * POST workers/run: Run now, for a registry workflow that needs no input.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_worker_run( WP_REST_Request $request ) {
	$w = aw_worker( (string) $request['id'] );
	if ( ! $w || ! aw_workers_can_run( $w ) ) {
		return new WP_Error( 'sourceweb_no_run', __( 'Run now is not available for this worker.', 'aw-sourcing' ), array( 'status' => 400 ) );
	}
	$res = aw_workers_run_now( $w['file'] );
	if ( true !== $res ) {
		return new WP_Error( 'sourceweb_run_failed', $w['name'] . ': not started — ' . $res->get_error_message(), array( 'status' => 502 ) );
	}
	return rest_ensure_response( array( 'message' => $w['name'] . ': started on GitHub. Its start report appears here within a minute or two.' ) );
}

/* ---------- Sources ---------- */

/** Sources views => label (the list's status= views). */
function sourceweb_source_views(): array {
	$v = array(
		'allow'   => 'Allowed',
		'neutral' => 'Neutral',
		'block'   => 'Blocked',
		'press'   => 'Press sites',
		'feeds'   => 'With feeds',
		'code'    => 'Read by code',
		'relay'   => 'Relay',
	);
	if ( aw_sourcing_phase() >= 2 ) { // press sites went with Brave (phase 2)
		unset( $v['press'] );
	}
	return $v;
}

/**
 * One row of the Sources list, in plain text.
 *
 * @param array $r  A row from aw_sourcing_source_rows().
 * @param bool  $si Search Console figures are there.
 */
function sourceweb_source_row( array $r, bool $si ): array {
	$st   = array(
		'allow'   => 'Allowed',
		'neutral' => 'Neutral',
		'block'   => 'Blocked',
	);
	$not  = array(
		'relay' => 'Relay only',
		'feeds' => 'Feeds only',
	)[ $r['kind'] ] ?? '';
	$rec  = $r['rec'];
	$last = $r['relay_last'];
	$n    = 'number_format_i18n';
	$code = (string) $r['code'];
	return array(
		'id'         => $r['host'],
		'title'      => $r['host'],
		'name'       => $r['name'],
		'icon'       => aw_src_icon_url( $r['host'] ),
		'pub'        => (bool) $r['pub'],
		'kind'       => $r['kind'],
		'short'      => $r['short'],
		'status'     => $r['status'],
		'statusText' => $r['pub'] ? ( $st[ $r['status'] ] ?? 'Neutral' ) : ( '' !== $not ? $not . ' (not a publisher)' : 'Not a publisher' ),
		'press'      => (bool) $r['press'],
		'rating'     => $r['pub'] && ! empty( $rec['rating'] ) ? $rec['rating'] . ( ! empty( $rec['credibility'] ) ? ' · ' . $rec['credibility'] . ' credibility' : '' ) : '',
		'ratingUrl'  => $r['pub'] && ! empty( $rec['rating_url'] ) ? esc_url_raw( (string) $rec['rating_url'] ) : '',
		'dr'         => $r['dr'],
		'drAt'       => $r['dr_at'],
		'feeds'      => $r['feeds'],
		'feedsText'  => $r['feeds'] ? sprintf( /* translators: 1: feeds, 2: feeds that are on. */ _n( '%1$s feed (%2$s on)', '%1$s feeds (%2$s on)', $r['feeds'], 'aw-sourcing' ), $n( $r['feeds'] ), $n( $r['feeds_on'] ) ) . ( $r['code_n'] ? ', ' . $n( $r['code_n'] ) . ' read by code' : '' ) : '',
		'relay'      => (bool) $r['relay'],
		'relayOwn'   => (bool) $r['relay_own'],
		'relayLast'  => $last ? (string) $last['status'] . ' · ' . wp_date( 'j M Y, g:i a', (int) $last['at'] ) : '',
		'relayOk'    => $last ? is_int( $last['status'] ?? null ) && $last['status'] < 400 : null,
		'items'      => $n( $r['f30'] ) . ' / ' . $n( $r['pub_n'] ),
		'search'     => $si ? '28 days: ' . $n( $r['impr'] ) . ' impr. · ' . $n( $r['clicks'] ) . ' clicks; 3 months: ' . $n( $r['impr3'] ) . ' impr. · ' . $n( $r['clicks3'] ) . ' clicks' : '',
		'seoTitle'   => $r['title'],
		'seoDesc'    => $r['desc'],
		'seoAt'      => '' === $r['at'] ? '' : 'Checked ' . substr( $r['at'], 0, 10 ) . ' · ' . ( ctype_digit( $code ) ? 'HTTP ' . $code : $code ),
		'last'       => $r['last'],
		'canDelete'  => in_array( $r['kind'], array( 'publisher', 'relay' ), true ) && ! $r['feeds'] && ! $r['press'] && ! $r['count'] && ! $r['f30'] && ! $r['pub_n'],
		// For the sort: the raw numbers.
		'f30'        => $r['f30'],
		'pubN'       => $r['pub_n'],
		'impr'       => $r['impr'],
		'impr3'      => $r['impr3'],
		'feedsOn'    => $r['feeds_on'],
	);
}

/**
 * GET sources: one page of the Sources list, most items first, as today's list.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_sources( WP_REST_Request $request ) {
	$src  = aw_sourcing_source_rows();
	$rows = array();
	foreach ( $src['rows'] as $r ) {
		if ( '' !== (string) $r['host'] ) {
			$rows[] = sourceweb_source_row( $r, (bool) $src['si'] );
		}
	}
	// Most items first (fetched, then published), then the latest seen, then the host.
	usort( $rows, fn( $a, $b ) => ( $b['f30'] <=> $a['f30'] ) ?: ( $b['pubN'] <=> $a['pubN'] ) ?: strcmp( $b['last'], $a['last'] ) ?: strcmp( $a['id'], $b['id'] ) );
	$in    = array(
		'press' => fn( $r ) => $r['press'],
		'feeds' => fn( $r ) => $r['feeds'] > 0,
		'code'  => fn( $r ) => false !== strpos( $r['feedsText'], 'read by code' ),
		'relay' => fn( $r ) => $r['relay'],
	);
	$views = array();
	foreach ( sourceweb_source_views() as $k => $l ) {
		$views[ $k ] = array( $l, $in[ $k ] ?? fn( $r ) => $r['status'] === $k );
	}
	$out = sourceweb_list(
		$rows,
		$request->get_params(),
		array(
			'views' => $views,
			'text'  => fn( $r ) => $r['id'] . ' ' . $r['name'] . ' ' . $r['short'] . ' ' . $r['seoTitle'],
			'sort'  => array(
				'title'  => fn( $r ) => $r['id'],
				'status' => fn( $r ) => $r['statusText'] . ' ' . $r['id'],
				'dr'     => fn( $r ) => null === $r['dr'] ? -1 : $r['dr'],
				'feeds'  => fn( $r ) => array( $r['feeds'], $r['feedsOn'] ),
				'items'  => fn( $r ) => array( $r['f30'], $r['pubN'] ),
				'search' => fn( $r ) => array( $r['impr'], $r['impr3'] ),
				'last'   => fn( $r ) => $r['last'],
			),
			'per'   => 50,
		)
	);
	$out['feedCount'] = array_sum( array_column( $rows, 'feeds' ) );
	$out['phase']     = aw_sourcing_phase();
	$out['si']        = (bool) $src['si'];
	$out['stale']     = (bool) $src['stale'];
	return rest_ensure_response( $out );
}

/**
 * POST sources/decide: a row or bulk action, as today's list.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_sources_decide( WP_REST_Request $request ) {
	$action = (string) $request['action'];
	$phase  = aw_sourcing_phase();
	if ( ( in_array( $action, array( 'press_add', 'press_remove' ), true ) && $phase >= 2 ) || ( in_array( $action, array( 'relay_on', 'relay_off' ), true ) && $phase < 1 ) ) {
		return new WP_Error( 'sourceweb_not_now', __( 'That action is not available at this phase.', 'aw-sourcing' ), array( 'status' => 400 ) );
	}
	$hosts = array_values( array_filter( array_map( 'aw_sourcing_norm_host', array_map( 'sanitize_text_field', (array) $request['hosts'] ) ) ) );
	if ( ! $hosts ) {
		return rest_ensure_response(
			array(
				'changed' => false,
				'message' => __( 'Nothing changed.', 'aw-sourcing' ),
			)
		);
	}
	$pubs                 = aw_publishers(); // read just before the write: the ingest gate updates the same option
	$before               = $pubs;
	list( $pubs, $press ) = aw_sourcing_sources_apply( $pubs, $action, $hosts );
	if ( $pubs !== $before ) {
		update_option( AW_PUB_OPTION, $pubs, false );
	}
	$changed = $pubs !== $before || $press;
	return rest_ensure_response(
		array(
			'changed' => $changed,
			'message' => $changed ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/**
 * POST sources/short: short names, host => name.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_sources_short( WP_REST_Request $request ) {
	$in = array();
	foreach ( (array) $request['short'] as $h => $v ) {
		$h = aw_sourcing_norm_host( sanitize_text_field( (string) $h ) );
		if ( '' !== $h && is_scalar( $v ) ) {
			$in[ $h ] = sanitize_text_field( (string) $v );
		}
	}
	$pubs   = aw_publishers();
	$before = $pubs;
	$pubs   = aw_sourcing_shorts_apply( $pubs, $in );
	if ( $pubs !== $before ) {
		update_option( AW_PUB_OPTION, $pubs, false );
	}
	return rest_ensure_response(
		array(
			'changed' => $pubs !== $before,
			'message' => $pubs !== $before ? __( 'Short names saved.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/**
 * GET sources/one: a source for its form ('' for Add New Source).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_source( WP_REST_Request $request ) {
	$host  = aw_sourcing_norm_host( sanitize_text_field( (string) $request['host'] ) );
	$new   = '' === $host;
	$all   = aw_publishers();
	$rec   = $new ? null : ( $all[ $host ] ?? null );
	$press = ! $new && in_array( $host, aw_sourcing_press_hosts(), true );
	$feeds = $new ? array() : ( aw_sourcing_feeds_by_host()[ $host ] ?? array() );
	if ( ! $new && null === $rec && ! $press && ! $feeds ) {
		return new WP_Error( 'sourceweb_no_source', __( 'No such source.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$pub     = null !== $rec && empty( $rec['kind'] );
	$options = array(
		array(
			'value' => 'block',
			'label' => 'Blocked',
		),
		array(
			'value' => 'allow',
			'label' => 'Allowed',
		),
		array(
			'value' => 'neutral',
			'label' => 'Neutral',
		),
	);
	if ( ! $pub ) {
		$options[] = array(
			'value' => 'none',
			'label' => 'Not a publisher (press site, relay or feeds only)',
		);
	}
	$last = $new ? null : aw_src_relay_last( aw_src_relay_index( (array) get_option( 'aw_relay_log', array() ) ), $host );
	return rest_ensure_response(
		array(
			'host'       => $host,
			'new'        => $new,
			'name'       => (string) ( $rec['name'] ?? '' ),
			'short'      => (string) ( $rec['short'] ?? '' ),
			'status'     => $pub ? (string) ( $rec['status'] ?? 'neutral' ) : ( $new ? 'block' : 'none' ),
			'options'    => $options,
			'press'      => $press,
			'pressShown' => aw_sourcing_phase() < 2 || $press,
			'relayShown' => aw_sourcing_phase() >= 1,
			'relay'      => ! empty( $rec['relay'] ) || ( aw_sourcing_phase() < 1 && ! $new && aw_src_host_in( $host, aw_relay_hosts() ) ),
			'relayLast'  => $last ? (string) $last['status'] . ' · ' . wp_date( 'j M Y, g:i a', (int) $last['at'] ) : '',
			'feeds'      => count( $feeds ),
		)
	);
}

/**
 * POST sources/save: the source form.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_source_save( WP_REST_Request $request ) {
	list( $changed, $err ) = aw_sourcing_source_save(
		array(
			'host'        => (string) $request['host'],
			'status'      => (string) $request['status'],
			'name'        => (string) $request['name'],
			'short'       => (string) $request['short'],
			'press'       => (bool) $request['press'],
			'relay_shown' => true,
			'relay'       => (bool) $request['relay'],
		)
	);
	if ( '' !== $err ) {
		$msg = array(
			'host'    => 'Nothing saved: that is not a host (example.com).',
			'status'  => 'Nothing saved: choose Allowed, Neutral or Blocked.',
			'nothing' => 'Nothing saved: a source that is not a publisher must be searched for coverage, relayed or have feeds.',
		);
		return new WP_Error( 'sourceweb_source_' . $err, $msg[ $err ] ?? 'Nothing saved.', array( 'status' => 400 ) );
	}
	return rest_ensure_response(
		array(
			'changed' => $changed,
			'host'    => aw_sourcing_norm_host( sanitize_text_field( (string) $request['host'] ) ),
			'message' => $changed ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/**
 * GET sources/feeds: every feed and source read by code, or one site's.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_feeds( WP_REST_Request $request ) {
	$host = aw_sourcing_norm_host( sanitize_text_field( (string) $request['host'] ) );
	$rows = array();
	foreach ( (array) ( aw_sources_config()['sources'] ?? array() ) as $s ) {
		if ( ! is_array( $s ) || empty( $s['id'] ) || ( '' !== $host && aw_src_source_host( $s ) !== $host ) ) {
			continue;
		}
		$t      = (string) ( $s['type'] ?? '' );
		$rss    = 'rss' === ( $s['kind'] ?? '' );
		$q      = $rss ? aw_gnews_query( (string) $s['url'] ) : '';
		$h      = aw_src_source_host( $s );
		$args   = array(
			'feed' => $s['id'],
			'type' => $t,
		);
		$rows[] = array(
			'id'       => $t . '|' . $s['id'],
			'title'    => (string) ( $s['name'] ?? $s['id'] ),
			'type'     => $t,
			'typeText' => (string) ( AW_SOURCE_TYPES[ $t ][0] ?? $t ),
			'rss'      => $rss,
			'address'  => $q ? $q : (string) ( $s['url'] ?? '' ),
			'note'     => $q ? 'Google News search' : ( $rss ? '' : (string) ( $s['kind'] ?? '' ) . '; needs code to change' ),
			'host'     => $h,
			'byCode'   => 'code' === aw_src_read_by( $s ),
			'on'       => ! empty( $s['on'] ),
			'filter'   => $rss ? ( ! empty( $s['filter'] ) ? 'On' : 'Off' ) : '—',
			'filterOn' => ! empty( $s['filter'] ),
			// The feed form, at its own address (the new Sources screen draws it: sourceweb_feed_parts()).
			'editUrl'  => aw_sourcing_url( 'sources', '' !== $host ? $args + array( 'host' => rawurlencode( $host ) ) : $args ),
		);
	}
	$views = array();
	foreach ( AW_SOURCE_TYPES as $t => $l ) {
		$views[ $t ] = array( $l[0], fn( $r ) => $r['type'] === $t );
	}
	$views['on']  = array( 'On', fn( $r ) => $r['on'] );
	$views['off'] = array( 'Off', fn( $r ) => ! $r['on'] );
	$state        = sanitize_key( (string) $request['state'] );
	$out          = sourceweb_list(
		$rows,
		array(
			'view'   => '' !== $state ? $state : sanitize_key( (string) $request['type'] ),
			'search' => (string) $request['search'],
			'page'   => (int) $request['page'],
		),
		array(
			'views' => $views,
			'text'  => fn( $r ) => $r['title'] . ' ' . $r['address'] . ' ' . $r['host'] . ' ' . $r['id'],
			'per'   => '' !== $host ? 500 : 50,
		)
	);
	$out['addUrl'] = aw_sourcing_url(
		'sources',
		array_filter(
			array(
				'feed' => 'new',
				'host' => '' !== $host ? rawurlencode( $host ) : '',
			)
		)
	);
	return rest_ensure_response( $out );
}

/**
 * POST sources/feeds/decide.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_feeds_decide( WP_REST_Request $request ) {
	$ids     = array_map( 'sanitize_text_field', (array) $request['ids'] );
	$changed = $ids && aw_sources_feed_op( (string) $request['op'], $ids );
	return rest_ensure_response(
		array(
			'changed' => $changed,
			'message' => $changed ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/* ---------- Keywords ---------- */

/** Keywords is on the keyword table (phase 2 and later); before that the routes refuse. */
function sourceweb_keywords_ready() {
	return aw_sourcing_kw_on() ? true : new WP_Error( 'aw_kw_not_ready', __( 'The keyword table is not ready yet.', 'aw-sourcing' ), array( 'status' => 409 ) );
}

/** Category slug => name. */
function sourceweb_keyword_categories(): array {
	$out = array();
	foreach ( get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	) as $t ) {
		$out[ $t->slug ] = $t->name;
	}
	return $out;
}

/** The two switches and the Press settings, as the forms show them. */
function sourceweb_keyword_settings(): array {
	$r = aw_relevance();
	$p = (array) ( aw_sources_config()['press'] ?? array() );
	return array(
		'enabled'          => ! empty( $r['enabled'] ),
		'gnews_allow_only' => ! empty( $r['gnews_allow_only'] ),
		'press'            => array(
			'enabled'    => ! empty( $p['enabled'] ),
			'every_days' => (int) ( $p['every_days'] ?? 7 ),
		),
	);
}

/**
 * A keyword record as a list row.
 *
 * @param array $r    Record from aw_sourcing_kw_records().
 * @param array $cats Category slug => name.
 */
function sourceweb_keyword_row( array $r, array $cats ): array {
	$search = $r['searches'] ? 'On: ' . implode( ', ', array_map( fn( $x ) => $x['feed'], $r['searches'] ) ) : ( ( $r['roles'] & ( AW_KW_ROLE_INCLUDE | AW_KW_ROLE_SEARCH ) ) && ! $r['search_on'] ? 'Off' : '—' );
	return array(
		'id'         => (int) $r['id'],
		'title'      => (string) $r['label'],
		'lang'       => (string) $r['lang'],
		'langLabel'  => 'en' === $r['lang'] ? '' : ( 'hi' === $r['lang'] ? 'Hindi' : 'Hinglish' ),
		'spellings'  => $r['variants'] ? implode( ', ', $r['variants'] ) : '—',
		'spell'      => implode( ' ', aw_kw_spellings( $r ) ),
		'roles'      => (int) ( $r['roles'] & AW_KW_WORKER_ROLES ),
		'rolesText'  => implode( ', ', aw_sourcing_kw_role_names( $r['roles'] ) ),
		'categories' => array_map(
			fn( $slug ) => array(
				'slug' => $slug,
				'name' => $cats[ $slug ] ?? $slug,
			),
			array_values( (array) $r['categories'] )
		),
		'search'     => $search,
		'items'      => $r['searches'] ? number_format_i18n( $r['items'][0] ) . ' fetched · ' . number_format_i18n( $r['items'][1] ) . ' published' : '—',
		'itemsN'     => (int) $r['items'][0],
		'impr'       => (int) $r['impr28'],
	);
}

/**
 * GET keywords: one page of the Keywords list (A to Z unless sorted), the role, category and section choices, the
 * switches and the Press settings.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keywords( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$cats = sourceweb_keyword_categories();
	$cat  = sanitize_title( (string) $request['cat'] );
	$cat  = isset( $cats[ $cat ] ) ? $cat : '';
	$recs = array_values( aw_sourcing_kw_records() );
	if ( '' !== $cat ) {
		$recs = array_values( array_filter( $recs, fn( $r ) => in_array( $cat, (array) $r['categories'], true ) ) );
	}
	usort( $recs, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
	$rows  = array_map( fn( $r ) => sourceweb_keyword_row( $r, $cats ) + array( '_r' => $r ), $recs );
	$views = array( '' => array( __( 'All', 'aw-sourcing' ), fn( $x ) => aw_sourcing_kw_in_view( $x['_r'], '' ) ) );
	foreach ( aw_sourcing_kw_views() as $k => $v ) {
		$views[ $k ] = array( $v[0], fn( $x ) => aw_sourcing_kw_in_view( $x['_r'], $k ) );
	}
	$out         = sourceweb_list(
		$rows,
		$request->get_params(),
		array(
			'views' => $views,
			'text'  => fn( $x ) => $x['spell'],
			'sort'  => array(
				'title' => fn( $x ) => strtolower( $x['title'] ),
				'items' => fn( $x ) => $x['itemsN'],
				'impr'  => fn( $x ) => $x['impr'],
			),
			'per'   => 100,
		)
	);
	$out['rows'] = array_map(
		function ( $x ) {
			unset( $x['_r'], $x['spell'] );
			return $x;
		},
		$out['rows']
	);
	$roles       = array();
	foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
		$roles[] = array(
			'value' => (int) $bit,
			'label' => $l,
			'help'  => aw_sourcing_kw_role_help()[ $bit ],
		);
	}
	$out['roles']      = $roles;
	$out['include']    = AW_KW_ROLE_INCLUDE;
	$out['exclude']    = AW_KW_ROLE_EXCLUDE;
	$out['categories'] = array_map(
		fn( $slug, $name ) => array(
			'value' => $slug,
			'label' => $name,
		),
		array_keys( $cats ),
		$cats
	);
	$out['cat']        = $cat;
	$out['settings']   = sourceweb_keyword_settings();
	return rest_ensure_response( $out );
}

/**
 * GET keywords/one: one keyword for its form, or a blank one (id 0).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keyword( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$choices = array(
		'roles'      => array(),
		'categories' => array(),
		'sections'   => array(),
	);
	foreach ( aw_sourcing_kw_roles() as $bit => $l ) {
		$choices['roles'][] = array(
			'value' => (int) $bit,
			'label' => $l,
			'help'  => aw_sourcing_kw_role_help()[ $bit ],
		);
	}
	foreach ( sourceweb_keyword_categories() as $slug => $name ) {
		$choices['categories'][] = array(
			'value' => $slug,
			'label' => $name,
		);
	}
	foreach ( aw_sourcing_kw_sections() as $t => $l ) {
		$choices['sections'][] = array(
			'value' => $t,
			'label' => $l,
		);
	}
	$id = absint( $request['id'] );
	if ( ! $id ) {
		return rest_ensure_response(
			array(
				'id'       => 0,
				'label'    => '',
				'variants' => '',
				'roles'    => array(),
				'cats'     => array(),
				'sections' => array(),
				'lang'     => 'en',
				'choices'  => $choices,
			)
		);
	}
	$r = aw_kw_get( $id );
	if ( ! $r ) {
		return new WP_Error( 'rest_not_found', __( 'No such keyword.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$other    = array_values( array_filter( array_map( fn( $b, $l ) => ( $r['roles'] & $b ) && ! isset( aw_sourcing_kw_roles()[ $b ] ) ? $l : '', array_keys( aw_si_roles() ), aw_si_roles() ) ) );
	$searches = aw_kw_searches( $r, array_column( array_filter( (array) aw_sources_config()['sources'], 'is_array' ), null, 'id' ) );
	return rest_ensure_response(
		array(
			'id'         => (int) $r['id'],
			'label'      => (string) $r['label'],
			'norm'       => (string) $r['norm'],
			'variants'   => implode( "\n", $r['variants'] ),
			'roles'      => array_values( array_filter( array_keys( aw_sourcing_kw_roles() ), fn( $b ) => (bool) ( $r['roles'] & $b ) ) ),
			'otherRoles' => $other ? implode( ', ', $other ) : __( 'none', 'aw-sourcing' ),
			'cats'       => array_values( (array) $r['categories'] ),
			'sections'   => array_values( (array) $r['sections'] ),
			'lang'       => (string) $r['lang'],
			'search_on'  => (bool) $r['search_on'],
			'searches'   => $searches ? implode( ', ', array_map( fn( $x ) => $x['feed'], $searches ) ) : __( 'none', 'aw-sourcing' ),
			'query'      => (string) $r['query'],
			'feedId'     => '' !== (string) $r['feed_id'] ? (string) $r['feed_id'] : __( '(made from the query)', 'aw-sourcing' ),
			'choices'    => $choices,
		)
	);
}

/**
 * POST keywords/save: add a keyword, save one, or delete it from sourcing (its Sourcing and Press roles go; Search
 * Insights keeps the record), as today's forms.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keyword_save( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$id   = absint( $request['id'] );
	$cats = sourceweb_keyword_categories();
	$set  = array(
		'roles'      => aw_sourcing_kw_bits( array_map( 'absint', (array) $request['roles'] ) ),
		'variants'   => sanitize_textarea_field( (string) $request['variants'] ),
		'categories' => array_values( array_intersect( array_map( 'sanitize_title', (array) $request['categories'] ), array_keys( $cats ) ) ),
		'sections'   => array_values( array_intersect( array_map( 'sanitize_key', (array) $request['sections'] ), array_keys( aw_sourcing_kw_sections() ) ) ),
	);
	$lang = (string) $request['lang'];
	if ( ! $id ) {
		if ( ! $set['roles'] ) {
			return new WP_Error( 'aw_kw_roles', __( 'Choose at least one role.', 'aw-sourcing' ), array( 'status' => 400 ) );
		}
		$label = sanitize_text_field( (string) $request['label'] );
		$r     = aw_sourcing_kw_add( $label, $set + array( 'lang' => $lang ) );
		if ( is_wp_error( $r ) ) {
			$there = aw_kw_id_by_norm( aw_si_norm( $label ) );
			return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 400, 'id' => (int) $there ) );
		}
		return rest_ensure_response(
			array(
				'done'    => 'added',
				'id'      => (int) $r,
				'message' => __( 'Keyword saved. The workers use it from their next run.', 'aw-sourcing' ),
			)
		);
	}
	if ( ! aw_kw_get( $id ) ) {
		return new WP_Error( 'rest_not_found', __( 'No such keyword.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$delete = (bool) $request['delete'];
	if ( $delete ) {
		$set = array( 'roles' => 0 );
	} else {
		$q                = trim( (string) $request['query'] );
		$q                = (string) preg_replace_callback( '#^(https?)://#i', fn( $m ) => strtolower( $m[1] ) . '://', $q );
		$set['query']     = preg_match( '#^https?://#i', $q ) ? esc_url_raw( $q ) : sanitize_text_field( $q ); // an address keeps its %XX
		$set['search_on'] = $request['search_on'] ? 1 : 0;
		$set['lang']      = $lang;
	}
	$r = aw_sourcing_kw_apply( $id, $set );
	if ( is_wp_error( $r ) ) {
		return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 400 ) );
	}
	$done = ! $r ? 'none' : ( $delete ? 'deleted' : 'saved' );
	$msg  = array(
		'none'    => __( 'Nothing changed.', 'aw-sourcing' ),
		'saved'   => __( 'Keyword updated. The workers use it from their next run.', 'aw-sourcing' ),
		'deleted' => __( 'Keyword deleted from sourcing (its Sourcing and Press roles were removed; Search Insights keeps the record).', 'aw-sourcing' ),
	);
	return rest_ensure_response(
		array(
			'done'    => $done,
			'id'      => $id,
			'message' => $msg[ $done ],
		)
	);
}

/**
 * POST keywords/decide: a row or bulk action, as today's list.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keywords_decide( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	list( $n, $errs ) = aw_sourcing_kw_bulk( (string) $request['op'], array_map( 'absint', (array) $request['ids'] ) );
	return rest_ensure_response(
		array(
			'changed' => $n,
			'errors'  => $errs,
			'message' => $n ? __( 'Keywords changed. The workers use them from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/**
 * GET keywords/test: whether a headline is kept, and why.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keywords_test( WP_REST_Request $request ) {
	$text = sanitize_text_field( (string) $request['text'] );
	if ( '' === $text ) {
		return new WP_Error( 'rest_invalid_param', __( 'Paste a headline.', 'aw-sourcing' ), array( 'status' => 400 ) );
	}
	$v = aw_relevance_verdict( $text );
	return rest_ensure_response(
		array(
			'kept' => (bool) $v[0],
			'why'  => (string) $v[1],
		)
	);
}

/**
 * POST keywords/settings: the two switches and the Press settings, through the Settings API forms' own sanitizers
 * (same log lines; a Save with no change writes nothing).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_keywords_settings( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	if ( ! function_exists( 'add_settings_error' ) ) {
		require_once ABSPATH . 'wp-admin/includes/template.php'; // The sanitizers add the "Saved" notice.
	}
	$before = sourceweb_keyword_settings();
	$rel    = aw_relevance_switches_sanitize(
		array(
			'_aw_tab'          => 'keywords',
			'enabled'          => (bool) $request['enabled'],
			'gnews_allow_only' => (bool) $request['gnews_allow_only'],
		)
	);
	if ( is_array( $rel ) && $rel !== get_option( AW_REL_OPTION ) ) {
		update_option( AW_REL_OPTION, $rel );
	}
	$press = (array) $request['press'];
	if ( $press ) {
		$c = aw_sources_sanitize(
			array(
				'_aw_tab'    => 'press',
				'enabled'    => ! empty( $press['enabled'] ),
				'every_days' => absint( $press['every_days'] ?? 0 ),
			)
		);
		if ( is_array( $c ) && $c !== get_option( 'aw_sources' ) ) {
			update_option( 'aw_sources', $c );
		}
	}
	$after = sourceweb_keyword_settings();
	return rest_ensure_response(
		array(
			'saved'    => $after !== $before,
			'message'  => $after !== $before ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
			'settings' => $after,
		)
	);
}

/**
 * GET keywords/suggestions: searches people made (20 impressions in 28 days) that no keyword covers, most
 * impressions first; the Dismissed view holds the ones set aside.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_suggestions( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$all = aw_sourcing_suggestions();
	if ( null === $all ) {
		return rest_ensure_response(
			array(
				'none'  => true,
				'rows'  => array(),
				'total' => 0,
				'page'  => 1,
				'pages' => 1,
				'view'  => '',
				'views' => array(),
			)
		);
	}
	$rows = array();
	foreach ( $all as $t ) {
		$rows[] = array(
			'id'        => $t['q'],
			'title'     => $t['q'],
			'impr'      => (int) $t['impr'],
			'clicks'    => (int) $t['clicks'],
			'pos'       => round( (float) $t['pos'], 1 ),
			'rising'    => (bool) $t['rising'],
			'dismissed' => (bool) $t['dismissed'],
		);
	}
	usort( $rows, fn( $a, $b ) => ( $b['impr'] <=> $a['impr'] ) ?: strcmp( $a['id'], $b['id'] ) );
	$out         = sourceweb_list(
		$rows,
		$request->get_params(),
		array(
			'views' => array(
				''          => array( __( 'Suggested', 'aw-sourcing' ), fn( $r ) => ! $r['dismissed'] ),
				'dismissed' => array( __( 'Dismissed', 'aw-sourcing' ), fn( $r ) => $r['dismissed'] ),
			),
			'text'  => fn( $r ) => $r['id'],
			'sort'  => array(
				'title'  => fn( $r ) => $r['id'],
				'impr'   => fn( $r ) => $r['impr'],
				'clicks' => fn( $r ) => $r['clicks'],
				'pos'    => fn( $r ) => $r['pos'],
			),
			'per'   => 50,
		)
	);
	$out['none'] = false;
	return rest_ensure_response( $out );
}

/**
 * POST keywords/suggestions/decide: add as Include, Exclude or Search, dismiss, or show again.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_suggestions_decide( WP_REST_Request $request ) {
	$ready = sourceweb_keywords_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$n = aw_sourcing_suggest_apply( (string) $request['op'], array_map( 'sanitize_text_field', (array) $request['qs'] ) );
	return rest_ensure_response(
		array(
			'changed' => $n,
			/* translators: %d: number of searches. */
			'message' => $n ? sprintf( _n( '%d search changed.', '%d searches changed.', $n, 'aw-sourcing' ), $n ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}

/* ---------- Rules ---------- */

/** Rules is one tab from the phase 3 migration; before it the routes refuse. */
function sourceweb_rules_ready() {
	return aw_rules_live() ? true : new WP_Error( 'aw_rules_not_ready', __( 'Rules is not set up yet (the phase 3 migration).', 'aw-sourcing' ), array( 'status' => 409 ) );
}

/**
 * Term names for slugs in the stored order; a slug with no term shows as "slug (no such …)".
 *
 * @param array  $slugs Slugs.
 * @param string $tax   category or post_tag.
 */
function sourceweb_rules_names( array $slugs, string $tax ): array {
	$out = array();
	foreach ( $slugs as $slug ) {
		$t     = get_term_by( 'slug', (string) $slug, $tax );
		$out[] = $t ? $t->name : urldecode( (string) $slug ) . ( 'category' === $tax ? ' (no such category)' : ' (no such tag)' );
	}
	return $out;
}

/**
 * GET rules/sections: every section with its allowed categories and tags, workers file and summary rule, and the tag
 * rules with the sections that may give each tag.
 */
function sourceweb_rest_rules_sections() {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$secs  = (array) ( aw_sources_config()['sections'] ?? array() );
	$sums  = aw_rules_summary_by_type();
	$what  = array(
		'news'        => __( 'News items', 'aw-sourcing' ),
		'legal'       => __( 'Library items', 'aw-sourcing' ),
		'research'    => __( 'Research', 'aw-sourcing' ),
		'opportunity' => __( 'Opportunities', 'aw-sourcing' ),
		'press'       => __( 'Press coverage', 'aw-sourcing' ),
	);
	$rows  = array();
	foreach ( aw_rules_sections() as $type => $def ) {
		$cats   = array_values( (array) ( $secs[ $type ]['categories'] ?? array() ) );
		$tags   = array_values( (array) ( $secs[ $type ]['tags'] ?? array() ) );
		$sum    = $sums[ $type ] ?? array();
		$rows[] = array(
			'id'         => $type,
			'title'      => $def['label'],
			'stored'     => array_key_exists( $type, $secs ),
			'categories' => $cats ? implode( ', ', sourceweb_rules_names( $cats, 'category' ) ) : __( 'Every category', 'aw-sourcing' ),
			'tags'       => $tags ? implode( ', ', sourceweb_rules_names( $tags, 'post_tag' ) ) : '—',
			'tagCount'   => count( $tags ),
			'worker'     => '' === $def['worker_type'] ? '—' : ( $what[ $def['worker_type'] ] ?? $def['worker_type'] ),
			'summary'    => $sum ? sprintf( '%d–%d words', (int) $sum['min_words'], (int) $sum['max_words'] ) . ( '' !== (string) ( $sum['inherit'] ?? '' ) ? ' (inherits News)' : '' ) : '—',
		);
	}
	$labs  = aw_rules_section_labels();
	$rules = array();
	foreach ( aw_rules_tag_rules() as $slug => $r ) {
		$slug = (string) $slug;
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		if ( ! $term && 0 === strpos( $slug, 'campaign-' ) && taxonomy_exists( 'aw_campaign_link' ) ) {
			$term = get_term_by( 'slug', $slug, 'aw_campaign_link' );
		}
		$in = array();
		foreach ( $secs as $t => $sec ) {
			if ( in_array( $slug, array_map( 'strval', (array) ( $sec['tags'] ?? array() ) ), true ) ) {
				$in[] = $labs[ $t ] ?? $t;
			}
		}
		$rules[] = array(
			'id'       => $slug,
			'title'    => $term ? $term->name : urldecode( $slug ) . ' (no such tag)',
			'slug'     => urldecode( $slug ),
			'any'      => implode( ', ', (array) ( $r['any'] ?? array() ) ),
			'hold'     => array_values( array_map( 'strval', (array) ( $r['hold'] ?? array() ) ) ),
			'unless'   => implode( ', ', (array) ( $r['unless'] ?? array() ) ),
			'reason'   => (string) ( $r['hold_reason'] ?? '' ),
			'sections' => $in ? implode( ', ', $in ) : __( 'No section allows this tag yet', 'aw-sourcing' ),
		);
	}
	return rest_ensure_response(
		array(
			'sections' => $rows,
			'tagRules' => $rules,
		)
	);
}

/**
 * The terms of a taxonomy as choices (slug, name), and the ticked ones of a list.
 *
 * @param string $tax    category or post_tag.
 * @param array  $ticked Stored slugs.
 */
function sourceweb_rules_term_choices( string $tax, array $ticked ): array {
	$out = array();
	foreach ( get_terms(
		array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	) as $t ) {
		$out[] = array(
			'value' => $t->slug,
			'label' => $t->name,
		);
	}
	$gone = array();
	foreach ( $ticked as $slug ) {
		if ( ! get_term_by( 'slug', (string) $slug, $tax ) ) {
			$gone[] = (string) $slug;
		}
	}
	return array( $out, $gone );
}

/**
 * GET rules/section: one section's allowed categories and tags as checklists; a stored slug with no term is kept
 * (ticked) until unticked.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_section( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$type  = sanitize_key( (string) $request['type'] );
	$types = aw_rules_section_labels();
	if ( ! isset( $types[ $type ] ) ) {
		return new WP_Error( 'rest_not_found', __( 'No such section.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$sec                 = (array) ( aw_sources_config()['sections'][ $type ] ?? array() );
	$cat                 = array_values( array_map( 'strval', (array) ( $sec['categories'] ?? array() ) ) );
	$tag                 = array_values( array_map( 'strval', (array) ( $sec['tags'] ?? array() ) ) );
	list( $cats, $cgone ) = sourceweb_rules_term_choices( 'category', $cat );
	list( $tags, $tgone ) = sourceweb_rules_term_choices( 'post_tag', $tag );
	return rest_ensure_response(
		array(
			'type'       => $type,
			'label'      => $types[ $type ],
			'categories' => array_values( array_diff( $cat, $cgone ) ),
			'tags'       => array_values( array_diff( $tag, $tgone ) ),
			'gone'       => array(
				'categories' => $cgone,
				'tags'       => $tgone,
			),
			'choices'    => array(
				'categories' => $cats,
				'tags'       => $tags,
			),
		)
	);
}

/**
 * POST rules/section/save: the ticked slugs as term IDs, then today's save.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_section_save( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$type = sanitize_key( (string) $request['type'] );
	if ( ! isset( aw_rules_sections()[ $type ] ) ) {
		return new WP_Error( 'rest_not_found', __( 'No such section.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$ids  = function ( array $slugs, string $tax ) {
		$out = array();
		foreach ( array_map( 'sanitize_title', $slugs ) as $slug ) {
			$t = get_term_by( 'slug', $slug, $tax );
			if ( $t ) {
				$out[] = (int) $t->term_id;
			}
		}
		return $out;
	};
	$gone = (array) $request['gone'];
	$done = aw_sourcing_section_save(
		$type,
		array(
			'categories' => $ids( (array) $request['categories'], 'category' ),
			'tags'       => $ids( (array) $request['tags'], 'post_tag' ),
		),
		array(
			'categories' => array_map( 'sanitize_title', array_map( 'strval', (array) ( $gone['categories'] ?? array() ) ) ),
			'tags'       => array_map( 'sanitize_title', array_map( 'strval', (array) ( $gone['tags'] ?? array() ) ) ),
		)
	);
	$label = aw_rules_section_labels()[ $type ];
	return rest_ensure_response(
		array(
			'done'    => $done,
			/* translators: %s: section. */
			'message' => 'saved' === $done ? sprintf( __( 'Saved the %s section. The workers use this from their next run.', 'aw-sourcing' ), $label ) : sprintf( __( 'Nothing changed for %s.', 'aw-sourcing' ), $label ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- the comment above covers both
		)
	);
}

/**
 * GET rules/tag: one tag rule for its form ('' for Add New Rule), with the tags to choose from.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_tag( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$slug  = sanitize_title( (string) $request['tag'] );
	$rules = aw_rules_tag_rules();
	if ( '' !== $slug && ! isset( $rules[ $slug ] ) ) {
		return new WP_Error( 'rest_not_found', __( 'No such tag rule.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$r     = '' === $slug ? array() : (array) $rules[ $slug ];
	$lines = fn( $k ) => implode( "\n", array_map( 'strval', (array) ( $r[ $k ] ?? array() ) ) );
	list( $tags ) = sourceweb_rules_term_choices( 'post_tag', array() );
	if ( '' !== $slug && ! in_array( $slug, array_column( $tags, 'value' ), true ) ) {
		$tags[] = array(
			'value' => $slug,
			'label' => urldecode( $slug ) . ' (no such tag)',
		);
	}
	return rest_ensure_response(
		array(
			'tag'         => $slug,
			'any'         => $lines( 'any' ),
			'hold'        => $lines( 'hold' ),
			'unless'      => $lines( 'unless' ),
			'hold_reason' => (string) ( $r['hold_reason'] ?? '' ),
			'choices'     => $tags,
		)
	);
}

/** Notice key => [tone, words] for a Rules save (as today's notices). */
function sourceweb_rules_message( string $done, string $for ): array {
	$msg = array(
		/* translators: %s: tag. */
		'tag_added'   => array( 'success', __( 'Tag rule %s added. The workers use it from their next run.', 'aw-sourcing' ) ),
		/* translators: %s: tag. */
		'tag_saved'   => array( 'success', __( 'Tag rule %s saved. The workers use it from their next run.', 'aw-sourcing' ) ),
		/* translators: %s: tag. */
		'tag_deleted' => array( 'success', __( 'Tag rule %s deleted.', 'aw-sourcing' ) ),
		/* translators: %s: tag. */
		'tag_exists'  => array( 'error', __( 'A tag rule for %s exists already: edit it instead.', 'aw-sourcing' ) ),
		'tag_empty'   => array( 'error', __( 'Choose a tag and give at least one phrase.', 'aw-sourcing' ) ),
		/* translators: %s: the patterns. */
		'tag_badre'   => array( 'error', __( 'Not saved: a hold pattern is not a valid regular expression (%s).', 'aw-sourcing' ) ),
		/* translators: %s: field. */
		'field_saved' => array( 'success', __( 'Field rule %s saved. The enrichment worker uses it from its next run.', 'aw-sourcing' ) ),
		/* translators: %s: the order. */
		'order_saved' => array( 'success', __( 'Trust order saved: %s.', 'aw-sourcing' ) ),
		/* translators: %s: what it is about. */
		'none'        => array( 'info', __( 'Nothing changed for %s.', 'aw-sourcing' ) ),
		/* translators: %s: field. */
		'nosuch'      => array( 'error', __( 'No such field: %s.', 'aw-sourcing' ) ),
	);
	$m = $msg[ $done ] ?? array( 'info', '%s' );
	return array( $m[0], sprintf( $m[1], $for ) );
}

/**
 * A Rules save's answer: 200 with the notice, or 400 for a refusal.
 *
 * @param array $res [notice key, what it is about].
 */
function sourceweb_rules_answer( array $res ) {
	list( $tone, $text ) = sourceweb_rules_message( $res[0], (string) $res[1] );
	if ( 'error' === $tone ) {
		return new WP_Error( 'aw_rules_' . $res[0], $text, array( 'status' => 400 ) );
	}
	return rest_ensure_response(
		array(
			'done'    => $res[0],
			'tone'    => $tone,
			'message' => $text,
		)
	);
}

/**
 * POST rules/tag/save: add or edit a tag rule.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_tag_save( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$in = array();
	foreach ( array( 'any', 'unless', 'hold_reason' ) as $k ) {
		$in[ $k ] = sanitize_textarea_field( (string) $request[ $k ] );
	}
	$in['hold'] = wp_check_invalid_utf8( (string) $request['hold'] ); // regular expressions: checked to compile (aw_rules_tag_rule_clean), as today's form
	return sourceweb_rules_answer( aw_rules_tag_save( sanitize_title( (string) $request['tag'] ), sanitize_title( (string) $request['original'] ), $in ) );
}

/**
 * POST rules/tag/delete: delete a tag rule.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_tag_delete( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$tag = sanitize_title( (string) $request['tag'] );
	$res = aw_rules_tag_delete( $tag );
	return sourceweb_rules_answer( 'none' === $res[0] ? array( 'none', $tag ) : $res );
}

/** GET rules/enrich: the trust order and every field rule. */
function sourceweb_rest_rules_enrich() {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$order = aw_enrich_field_rules()['priority'];
	$names = array_map( fn( $s ) => $s[0], aw_enrich_sources() );
	$prio  = array();
	foreach ( $names as $k => $label ) {
		$pos    = array_search( $k, $order, true );
		$prio[] = array(
			'key'   => $k,
			'label' => $label,
			'pos'   => false === $pos ? '' : (string) ( $pos + 1 ),
		);
	}
	$fields = array();
	foreach ( aw_rules_field_rows() as $k => $row ) {
		$r        = $row['rule'];
		$why      = (string) ( $row['why'] ?? '' );
		$fields[] = array(
			'id'       => $k,
			'title'    => $row['label'],
			'note'     => $row['set'] || isset( aw_enrich_fields()[ $k ] ) ? '' : __( 'Added in the listing field group: off until its rule is set.', 'aw-sourcing' ),
			'group'    => $row['group'],
			'store'    => $row['store'] . ( $row['acf'] ? '' : ' (kept by code)' ),
			'on'       => '' !== $why ? 'Off: ' . $why : ( $r['on'] ? 'On' : 'Off' ),
			'sources'  => $r['sources'] ? implode( ', ', array_map( fn( $x ) => $names[ $x ] ?? $x, $r['sources'] ) ) : 'Any',
			'multi'    => $r['multi'] ? sprintf( 'Up to %d', (int) $r['multi'] ) : 'One',
			'official' => '' !== $r['official'] ? ( $names[ $r['official'] ] ?? $r['official'] ) : '—',
			'ai'       => $r['ai'] ? 'Yes' : '—',
			'invite'   => $r['invite'] ? 'Yes' : '—',
			'apply'    => ucfirst( (string) $r['apply'] ),
		);
	}
	return rest_ensure_response(
		array(
			'priority' => $prio,
			'fields'   => $fields,
		)
	);
}

/**
 * POST rules/priority: save the trust order.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_priority( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$pos = array();
	foreach ( (array) $request['pos'] as $k => $v ) {
		$pos[ sanitize_key( (string) $k ) ] = '' === trim( (string) $v ) ? '' : absint( $v );
	}
	return sourceweb_rules_answer( aw_rules_priority_save( $pos ) );
}

/**
 * GET rules/field: one field's rule for its form, with the sources and the Apply choices it may have.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_field( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$field = sanitize_key( (string) $request['field'] );
	$rows  = aw_rules_field_rows();
	if ( ! isset( $rows[ $field ] ) ) {
		return new WP_Error( 'rest_not_found', __( 'No such field.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	$row     = $rows[ $field ];
	$sources = array();
	foreach ( aw_enrich_sources() as $k => $s ) {
		$sources[] = array(
			'value' => $k,
			'label' => $s[0],
		);
	}
	$apply = array();
	foreach ( AW_ENRICH_APPLY as $k => $label ) {
		if ( ( 'both' === $k && ! in_array( $field, AW_ENRICH_TWO_FIELDS, true ) ) || ( 'review' !== $k && in_array( $field, AW_ENRICH_REVIEW_FIELDS, true ) ) ) {
			continue; // two values only where a field can hold them; closed and merge_into always to a person
		}
		$apply[] = array(
			'value' => $k,
			'label' => $label,
		);
	}
	return rest_ensure_response(
		array(
			'field'   => $field,
			'label'   => $row['label'],
			'group'   => $row['group'],
			'store'   => $row['store'],
			'why'     => (string) ( $row['why'] ?? '' ),
			'rule'    => array(
				'on'       => (bool) $row['rule']['on'],
				'sources'  => array_values( (array) $row['rule']['sources'] ),
				'multi'    => (int) $row['rule']['multi'],
				'official' => (string) $row['rule']['official'],
				'ai'       => (bool) $row['rule']['ai'],
				'invite'   => (bool) $row['rule']['invite'],
				'social'   => (bool) $row['rule']['social'],
				'apply'    => (string) $row['rule']['apply'],
			),
			'choices' => array(
				'sources' => $sources,
				'apply'   => $apply,
			),
		)
	);
}

/**
 * POST rules/field/save: save one field's rule.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_field_save( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$raw = (array) $request['rule'];
	$in  = array(
		'on'       => ! empty( $raw['on'] ),
		'ai'       => ! empty( $raw['ai'] ),
		'invite'   => ! empty( $raw['invite'] ),
		'social'   => ! empty( $raw['social'] ),
		'multi'    => absint( $raw['multi'] ?? 0 ),
		'official' => sanitize_key( (string) ( $raw['official'] ?? '' ) ),
		'sources'  => array_map( 'sanitize_key', array_map( 'strval', (array) ( $raw['sources'] ?? array() ) ) ),
		'apply'    => sanitize_key( (string) ( $raw['apply'] ?? '' ) ),
	);
	$res = aw_rules_field_save( sanitize_key( (string) $request['field'] ), $in );
	if ( 'nosuch' === $res[0] ) {
		return new WP_Error( 'rest_not_found', __( 'No such field.', 'aw-sourcing' ), array( 'status' => 404 ) );
	}
	return sourceweb_rules_answer( $res );
}

/** The Summary rules form's fields that are numbers, lists (one per line) and texts: key => label. */
function sourceweb_summary_fields(): array {
	return array(
		'numbers' => array(
			'lead_max_words'        => __( 'Lead sentence, about this many words or fewer', 'aw-sourcing' ),
			'max_quotes'            => __( 'Direct quotes per summary, at most', 'aw-sourcing' ),
			'max_quote_words'       => __( 'Words in a quote, at most', 'aw-sourcing' ),
			'max_copied_run'        => __( 'Words copied in a row outside a quote, at most', 'aw-sourcing' ),
			'description_max_chars' => __( 'Search description, characters', 'aw-sourcing' ),
		),
		'lists'   => array(
			'general'        => array( __( 'Rules', 'aw-sourcing' ), __( 'One rule per line. The facts-only, date, length, plain-text, no-AI-marker, English-only and searched-words rules are here.', 'aw-sourcing' ) ),
			'stock_phrases'  => array( __( 'Banned stock and praise wording', 'aw-sourcing' ), __( 'One per line. A summary using any of them is written again.', 'aw-sourcing' ) ),
			'ai_markers'     => array( __( 'AI markers', 'aw-sourcing' ), __( 'One per line. A summary containing any of them is written again.', 'aw-sourcing' ) ),
			'searched_terms' => array( __( 'Searched words', 'aw-sourcing' ), __( 'One group per line, words separated by |. When the source names one, the summary keeps one of the group.', 'aw-sourcing' ) ),
			'entities'       => array( __( 'Linkable entities: names', 'aw-sourcing' ), __( "One entity per line: the site's name first, then other spellings, separated by |. A summary using another spelling is written again.", 'aw-sourcing' ) ),
		),
		'texts'   => array(
			'description'   => __( 'Search description and summary', 'aw-sourcing' ),
			'card'          => __( 'Card excerpt', 'aw-sourcing' ),
			'merge'         => __( 'Same-story merges', 'aw-sourcing' ),
			'fallback'      => __( 'Short fallback', 'aw-sourcing' ),
			'entities_note' => __( 'Linkable entities: how summaries name things', 'aw-sourcing' ),
		),
	);
}

/** GET rules/summary: the rules as the form shows them, one row per section. */
function sourceweb_rest_rules_summary() {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	$r     = aw_summary_rules();
	$below = aw_summary_below_counts();
	$secs  = array();
	foreach ( aw_rules_summary_by_type() as $pt => $sec ) {
		$own    = isset( $sec['alias'] );
		$b      = $own ? ( $below[ $sec['alias'] ] ?? array( 0, 0 ) ) : null;
		$secs[] = array(
			'type'        => $pt,
			'label'       => $sec['label'],
			'own'         => $own,
			'inherit'     => ! $own && '' !== (string) ( $sec['inherit'] ?? '' ),
			'min_words'   => (int) $sec['min_words'],
			'max_words'   => (int) $sec['max_words'],
			'structure'   => (string) $sec['structure'],
			'attribution' => (string) $sec['attribution'],
			'below'       => $b ? sprintf( '%d of %d', (int) $b[0], (int) $b[1] ) : '—',
		);
	}
	$f      = sourceweb_summary_fields();
	$values = array();
	foreach ( array_keys( $f['numbers'] ) as $k ) {
		$values[ $k ] = (int) $r[ $k ];
	}
	foreach ( array_keys( $f['lists'] ) as $k ) {
		$values[ $k ] = implode( "\n", (array) $r[ $k ] );
	}
	foreach ( array_keys( $f['texts'] ) as $k ) {
		$values[ $k ] = (string) $r[ $k ];
	}
	return rest_ensure_response(
		array(
			'sections' => $secs,
			'values'   => $values,
			'fields'   => $f,
		)
	);
}

/**
 * POST rules/summary/save: the form's values through the form's own sanitizer (same log lines; a Save with no change
 * writes nothing; reset goes back to the defaults).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rules_summary_save( WP_REST_Request $request ) {
	$ready = sourceweb_rules_ready();
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	if ( ! function_exists( 'add_settings_error' ) ) {
		require_once ABSPATH . 'wp-admin/includes/template.php'; // The sanitizer adds the "Saved" notice.
	}
	$v  = (array) $request['values'];
	$in = array( '_aw_tab' => 'summary' );
	if ( ! empty( $v['_reset'] ) ) {
		$in['_reset'] = 1;
	}
	$f = sourceweb_summary_fields();
	foreach ( array_merge( array_keys( $f['numbers'] ), array_keys( $f['lists'] ), array_keys( $f['texts'] ) ) as $k ) {
		if ( isset( $v[ $k ] ) && is_scalar( $v[ $k ] ) ) {
			$in[ $k ] = (string) $v[ $k ];
		}
	}
	$in['sections'] = array();
	foreach ( (array) ( $v['sections'] ?? array() ) as $pt => $sec ) {
		if ( ! is_array( $sec ) ) {
			continue;
		}
		$one = array( 'shown' => 1 );
		foreach ( array( 'min_words', 'max_words', 'structure', 'attribution' ) as $k ) {
			if ( isset( $sec[ $k ] ) && is_scalar( $sec[ $k ] ) ) {
				$one[ $k ] = (string) $sec[ $k ];
			}
		}
		if ( ! empty( $sec['inherit'] ) ) {
			$one['inherit'] = 1;
		}
		$in['sections'][ sanitize_key( (string) $pt ) ] = $one;
	}
	$before = get_option( AW_SUMMARY_RULES_OPTION );
	$new    = aw_summary_rules_sanitize( $in );
	$saved  = $new !== $before;
	if ( $saved ) {
		update_option( AW_SUMMARY_RULES_OPTION, $new, false );
	}
	return rest_ensure_response(
		array(
			'saved'   => $saved,
			'message' => $saved ? __( 'Saved. The workers use this from their next run.', 'aw-sourcing' ) : __( 'Nothing changed.', 'aw-sourcing' ),
		)
	);
}
