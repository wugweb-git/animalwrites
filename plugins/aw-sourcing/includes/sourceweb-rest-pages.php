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
			// The feed form stays on today's screen for now, at its own address.
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
