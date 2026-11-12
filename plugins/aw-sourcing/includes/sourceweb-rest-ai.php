<?php
/**
 * SourceWeb's routes for AI models on the Kits shell (12 Nov 2026), under sourceweb/v1. Each calls the functions
 * today's AI models tab uses (ai-keys.php), so both screens save the same way and log to the same change log.
 * Administrators only (manage_options). The workers' own route (aw/v1/ai-keys) is not used here.
 *
 *   GET  ai               At a glance, the workers and the keys each can use, daily caps, keys by group
 *   GET  ai/keys          the keys list (view, search, orderby, order, page), with the workers to attach to
 *   GET  ai/key           one saved key for its form (id; 'new' for Add New Key)
 *   GET  ai/overview      Keys overview: every saved key and the usable keys per worker (read-only)
 *   POST ai/keys/add      add one key or several (aw_ai_keys_add())
 *   POST ai/key/save      a key's label and workers (aw_ai_key_edit_save())
 *   POST ai/keys/decide   up, down, recheck, remove, attach, detach (aw_ai_keys_list_op())
 *
 * A key never goes out: only its id (a hash) and its last 4 characters.
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
		$ids    = array(
			'type'    => 'array',
			'items'   => array( 'type' => 'string' ),
			'default' => array(),
		);
		$routes = array(
			'/ai'             => array( 'GET', 'sourceweb_rest_ai', array() ),
			'/ai/keys'        => array(
				'GET',
				'sourceweb_rest_ai_keys',
				array(
					'view'    => $text,
					'search'  => $text,
					'orderby' => $text,
					'order'   => $text,
					'page'    => array(
						'type'    => 'integer',
						'default' => 1,
					),
				),
			),
			'/ai/key'         => array( 'GET', 'sourceweb_rest_ai_key', array( 'id' => $text ) ),
			'/ai/overview'    => array( 'GET', 'sourceweb_rest_ai_overview', array() ),
			'/ai/keys/add'    => array(
				'POST',
				'sourceweb_rest_ai_keys_add',
				array(
					'keys'    => $text,
					'label'   => $text,
					'workers' => $ids,
				),
			),
			'/ai/key/save'    => array(
				'POST',
				'sourceweb_rest_ai_key_save',
				array(
					'id'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'label'   => $text,
					'workers' => $ids,
				),
			),
			'/ai/keys/decide' => array(
				'POST',
				'sourceweb_rest_ai_keys_decide',
				array(
					'op'     => array(
						'type'     => 'string',
						'enum'     => array( 'up', 'down', 'recheck', 'remove', 'attach', 'detach' ),
						'required' => true,
					),
					'ids'    => $ids,
					'worker' => $text,
				),
			),
		);
		foreach ( $routes as $route => $r ) {
			register_rest_route(
				SOURCEWEB_REST,
				$route,
				array(
					'methods'             => $r[0],
					'permission_callback' => sourceweb_rest_can( AW_SRC_CAP ),
					'callback'            => $r[1],
					'args'                => $r[2],
				)
			);
		}
	}
);

/** The workers to choose from, by group: [ { value, label, help } ]. */
function sourceweb_ai_worker_choices(): array {
	$groups = aw_worker_groups();
	$out    = array();
	foreach ( $groups as $slug => $g ) {
		foreach ( aw_workers_all() as $id => $w ) {
			if ( $w['group'] !== $slug ) {
				continue;
			}
			$out[] = array(
				'value' => (string) $id,
				'label' => $w['name'] . ' (' . $id . ')',
				'help'  => $g[0] . ( $w['planned'] ? ' · planned' : '' ),
			);
		}
	}
	return $out;
}

/**
 * A provider's name.
 *
 * @param string $p Provider slug.
 */
function sourceweb_ai_provider( string $p ): string {
	return '' !== $p ? (string) ( AW_AI_PROVIDERS[ $p ][0] ?? AW_AI_TOOLS[ $p ][0] ?? $p ) : '—';
}

/**
 * One row of the keys list, as plain text.
 *
 * @param array $r A row of aw_ai_key_records().
 */
function sourceweb_ai_key_row( array $r ): array {
	$p     = $r['provider'];
	$tone  = aw_ai_key_tone( $r['status'] );
	$names = array_map( fn( $id ) => aw_worker( $id ) ? aw_worker( $id )['name'] : $id . ' (no longer exists)', $r['workers'] );
	if ( 'paid' === $r['status'] ) {
		$status = 'Not used (paid)';
	} elseif ( 'failed' === $r['status'] ) {
		$status = 'Failed in the last run';
	} else {
		$status = (string) ( AW_AI_STATUS[ $r['status'] ] ?? $r['status'] );
	}
	$caps = array_map( fn( $c ) => AW_AI_CAP_ICONS[ $c ][1], AW_AI_CAPS[ $p ] ?? array() );
	return array(
		'id'            => $r['id'],
		'pos'           => $r['pos'],
		'last4'         => $r['last4'],
		'title'         => '…' . $r['last4'],
		'provider'      => $p,
		'providerLabel' => sourceweb_ai_provider( $p ),
		'model'         => '' !== $r['model'] ? $r['model'] : '—',
		'label'         => $r['label'],
		'usedBy'        => $r['workers'] ? implode( ', ', $names ) : 'All workers',
		'group'         => aw_ai_tier_label( $p ),
		'caps'          => $caps ? implode( ', ', $caps ) : '—',
		'capsNote'      => (string) ( AW_AI_INFO[ $p ][1] ?? '' ),
		'status'        => $r['status'],
		'statusLabel'   => $status,
		'tone'          => $tone,
		'recheck'       => $r['recheck'],
		'lastOk'        => $r['secret'] ? '—' : ( $r['last_ok'] ? wp_date( 'j M, H:i', $r['last_ok'] ) : 'Never' ),
		'lastOkAt'      => $r['last_ok'],
		'limits'        => '' !== $r['limits'] ? $r['limits'] : '—',
		'usage'         => $r['usage'],
		'checked'       => $r['checked'] ? wp_date( 'j M, H:i', $r['checked'] ) : '—',
		'checkedAt'     => $r['checked'],
		'secret'        => $r['secret'],
		'problem'       => ! $r['secret'] && 'bad' === $tone ? sprintf( 'Key …%s: %s. It is not used; safe to remove.', $r['last4'], AW_AI_STATUS[ $r['status'] ] ?? $r['status'] ) : '',
	);
}

/**
 * GET ai: At a glance, the workers and the keys each can use, the daily caps on free models, keys by group.
 */
function sourceweb_rest_ai() {
	$rows = aw_ai_rows();
	$d    = aw_ai_overview_data( $rows );
	$all  = aw_workers_all();
	$used = array_map( fn( $id ) => $all[ $id ]['name'] ?? $id, array_keys( $d['used'] ) );
	return rest_ensure_response(
		array(
			'glance'  => array(
				array( 'Keys', $d['keys'] . ' saved, ' . $d['working'] . ' working now' ),
				array( 'Supply', 'About ' . number_format_i18n( $d['daily'] ) . ' free requests a day with a daily cap' . ( $d['nocap'] ? ', plus ' . $d['nocap'] . ' keys with no daily cap' : '' ) ),
				array( 'Demand', number_format_i18n( $d['ok'] ) . ' answers the workers needed in the last 24 h' . ( $d['miss'] ? ' (+' . number_format_i18n( $d['miss'] ) . ' tries that hit a limit or failed)' : '' ) ),
				array( 'Demand against supply', $d['pct'] . '% of the daily supply with caps (' . number_format_i18n( $d['ok'] ) . ' of about ' . number_format_i18n( $d['daily'] ) . '). Supply uses the providers\' published free limits, so it is an estimate.' ),
				array( 'AI workers', count( $used ) . ' of ' . count( $all ) . ' workers' . ( $used ? ' used AI in the last 24 h: ' . implode( ', ', $used ) : ': none used AI in the last 24 h' ) ),
			),
			'pct'     => $d['pct'],
			'workers' => array_map(
				fn( $w ) => array(
					$w['name'] . ' (' . $w['id'] . ')' . ( $w['planned'] ? ' (planned)' : '' ),
					$w['can'] === $w['all'] ? $w['can'] . ' (all working keys)' : $w['can'] . ' of ' . $w['all'] . ' working keys',
					$w['today'] ? 'Yes' : '—',
				),
				$d['workers']
			),
			'caps'    => array_map(
				fn( $c ) => array( $c['provider'] . ' …' . $c['account'], (string) $c['limit'], $c['left'], (string) $c['share'], (string) $c['used'], $c['keys'] . ' (' . $c['count'] . ')', $c['at'] ),
				aw_ai_free_caps_rows()
			),
			'groups'  => array_map( fn( $g ) => array( $g['group'], $g['means'], $g['providers'] ), aw_ai_tier_groups( $rows ) ),
		)
	);
}

/**
 * GET ai/keys: one page of the keys list (views Working, Waiting, Broken, GitHub secrets), in the workers' order unless
 * sorted. Move up / down only on the plain list (no view, search or sort), as today's list.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_ai_keys( WP_REST_Request $request ) {
	$rows = array_map( 'sourceweb_ai_key_row', aw_ai_key_records() );
	$out  = sourceweb_list(
		$rows,
		$request->get_params(),
		array(
			'views' => array(
				'ok'     => array( 'Working', fn( $r ) => ! $r['secret'] && 'ok' === $r['tone'] ),
				'wait'   => array( 'Waiting', fn( $r ) => ! $r['secret'] && 'wait' === $r['tone'] ),
				'bad'    => array( 'Broken', fn( $r ) => ! $r['secret'] && 'bad' === $r['tone'] ),
				'secret' => array( 'GitHub secrets', fn( $r ) => $r['secret'] ),
			),
			'text'  => fn( $r ) => $r['last4'] . ' ' . $r['provider'] . ' ' . $r['providerLabel'] . ' ' . $r['model'] . ' ' . $r['label'] . ' ' . $r['usedBy'],
			'sort'  => array(
				'provider' => fn( $r ) => strtolower( $r['providerLabel'] ),
				'label'    => fn( $r ) => strtolower( $r['label'] ),
				'status'   => fn( $r ) => $r['status'],
				'last_ok'  => fn( $r ) => $r['lastOkAt'],
				'checked'  => fn( $r ) => $r['checkedAt'],
			),
			'per'   => 100,
		)
	);
	$out['plain']   = '' === $out['view'] && '' === (string) $request['search'] && '' === (string) $request['orderby'];
	$out['workers'] = sourceweb_ai_worker_choices();
	return rest_ensure_response( $out );
}

/**
 * GET ai/key: a saved key for its form (label and workers; provider, model and status read-only), or a blank one for
 * 'new'. A key in the workers' GitHub secrets is changed in GitHub.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_ai_key( WP_REST_Request $request ) {
	$id      = sanitize_key( (string) $request['id'] );
	$choices = sourceweb_ai_worker_choices();
	if ( 'new' === $id || '' === $id ) {
		return rest_ensure_response(
			array(
				'new'     => true,
				'label'   => '',
				'workers' => array(),
				'choices' => $choices,
			)
		);
	}
	foreach ( aw_ai_rows() as $r ) {
		if ( aw_ai_key_id( $r['key'] ) !== $id ) {
			continue;
		}
		$p = (string) $r['provider'];
		return rest_ensure_response(
			array(
				'new'           => false,
				'id'            => $id,
				'last4'         => substr( $r['key'], -4 ),
				'providerLabel' => sourceweb_ai_provider( $p ),
				'group'         => aw_ai_tier_label( $p ),
				'model'         => '' !== (string) $r['model'] ? (string) $r['model'] : '—',
				'statusLabel'   => (string) ( AW_AI_STATUS[ $r['status'] ] ?? $r['status'] ),
				'tone'          => aw_ai_key_tone( (string) $r['status'] ),
				'checked'       => ! empty( $r['checked'] ) ? wp_date( 'j M Y, H:i', (int) $r['checked'] ) : 'Not checked yet',
				'label'         => (string) ( $r['label'] ?? '' ),
				'workers'       => aw_ai_row_workers( $r ),
				'choices'       => $choices,
			)
		);
	}
	$secret = isset( aw_ai_usage()[ $id ] );
	return new WP_Error(
		'sourceweb_no_key',
		$secret ? 'This key is in the workers\' GitHub secrets: it is changed in GitHub and cannot be attached to workers.' : 'No such key.',
		array( 'status' => 404 )
	);
}

/** GET ai/overview: Keys overview, read-only. Every saved key as "…last4 (provider, label)", and per worker what it gets. */
function sourceweb_rest_ai_overview() {
	$workers = aw_workers_all();
	$keys    = array();
	foreach ( aw_ai_rows() as $r ) {
		$ws     = aw_ai_row_workers( $r );
		$keys[] = array(
			aw_ai_key_short( $r ),
			(string) ( AW_AI_STATUS[ $r['status'] ] ?? $r['status'] ),
			$ws ? implode( ', ', array_map( fn( $id ) => isset( $workers[ $id ] ) ? $workers[ $id ]['name'] : $id . ' (no longer exists)', $ws ) ) : 'All workers',
		);
	}
	$per = array();
	foreach ( $workers as $id => $w ) {
		$served = aw_ai_served( $id );
		$per[]  = array(
			$w['name'] . ' (' . $id . ')' . ( $w['planned'] ? ' (planned)' : '' ),
			(string) count( $served['rows'] ),
			(string) count( $served['tools'] ),
		);
	}
	return rest_ensure_response(
		array(
			'keys'    => $keys,
			'workers' => $per,
		)
	);
}

/**
 * POST ai/keys/add: one key or several. Only the last 4 characters of each come back.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_ai_keys_add( WP_REST_Request $request ) {
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- as the form's save
	$res = aw_ai_keys_add(
		sanitize_textarea_field( (string) $request['keys'] ),
		sanitize_text_field( (string) $request['label'] ),
		aw_workers_posted_ids( (array) $request['workers'] )
	);
	if ( ! $res['saved'] ) {
		return new WP_Error( 'sourceweb_no_key', $res['dupes'] ? 'Nothing added: that key is already saved.' : 'Nothing added: paste a key.', array( 'status' => 400 ) );
	}
	$n = count( $res['added'] );
	/* translators: %d: number of keys. */
	$msg = sprintf( _n( '%d key added.', '%d keys added.', $n, 'aw-sourcing' ), $n );
	if ( $res['dupes'] ) {
		/* translators: %d: number of keys. */
		$msg .= ' ' . sprintf( _n( '%d was already saved and was skipped.', '%d were already saved and were skipped.', $res['dupes'], 'aw-sourcing' ), $res['dupes'] );
	}
	return rest_ensure_response(
		array(
			'saved'   => true,
			'added'   => $res['added'],
			'dupes'   => $res['dupes'],
			'message' => $msg . ' New keys are being checked in the background: reload in a few minutes to see their status.',
		)
	);
}

/**
 * POST ai/key/save: a saved key's label and workers.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_ai_key_save( WP_REST_Request $request ) {
	$n = aw_ai_key_edit_save( sanitize_key( (string) $request['id'] ), sanitize_text_field( (string) $request['label'] ), aw_workers_posted_ids( (array) $request['workers'] ) );
	if ( null === $n ) {
		return new WP_Error( 'sourceweb_no_key', 'No such key.', array( 'status' => 404 ) );
	}
	return rest_ensure_response(
		array(
			'saved'   => (bool) $n,
			'message' => $n ? 'Key saved. The workers use the change from their next run.' : 'Nothing changed.',
		)
	);
}

/**
 * POST ai/keys/decide: Move up / down, Check again, Remove, Attach to / Detach from a worker, straight away.
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_ai_keys_decide( WP_REST_Request $request ) {
	$res = aw_ai_keys_list_op( (string) $request['op'], array_map( 'strval', (array) $request['ids'] ), sanitize_key( (string) $request['worker'] ) );
	if ( 'noworker' === $res['op'] ) {
		return new WP_Error( 'sourceweb_no_worker', 'Nothing changed: choose the worker to attach to or detach from.', array( 'status' => 400 ) );
	}
	$n   = $res['n'];
	$msg = array(
		/* translators: %d: number of keys. */
		'remove'  => _n( '%d key removed. The news workers stop using it from their next run; it is noted in the Change log.', '%d keys removed. The news workers stop using them from their next run; they are noted in the Change log.', $n, 'aw-sourcing' ),
		/* translators: %d: number of keys. */
		'recheck' => _n( '%d key is being checked again in the background: reload in a few minutes.', '%d keys are being checked again in the background: reload in a few minutes.', $n, 'aw-sourcing' ),
		'up'      => 'Moved up. The workers try the keys from the top down.',
		'down'    => 'Moved down. The workers try the keys from the top down.',
		/* translators: %d: number of keys. */
		'attach'  => _n( '%d key attached. A key that was for every worker is now for the workers it is attached to only.', '%d keys attached. A key that was for every worker is now for the workers it is attached to only.', $n, 'aw-sourcing' ),
		/* translators: %d: number of keys. */
		'detach'  => _n( '%d key detached. A key that was for every worker is now for every other worker (not for workers added later).', '%d keys detached. A key that was for every worker is now for every other worker (not for workers added later).', $n, 'aw-sourcing' ),
	);
	$out = $n ? sprintf( $msg[ $res['op'] ], $n ) : 'Nothing changed.';
	if ( $res['skip'] ) {
		/* translators: %d: number of keys. */
		$out .= ' ' . sprintf( _n( '%d key was not detached: it is for that worker only, and a key needs at least one worker. Remove the key instead.', '%d keys were not detached: each is for that worker only, and a key needs at least one worker. Remove them instead.', $res['skip'], 'aw-sourcing' ), $res['skip'] );
	}
	return rest_ensure_response(
		array(
			'saved'   => $n > 0,
			'tone'    => $res['skip'] ? 'warn' : ( $n ? 'success' : 'info' ),
			'message' => $out,
		)
	);
}
