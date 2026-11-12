<?php
/**
 * SourceWeb's report routes on the Kits shell (12 Nov 2026), under sourceweb/v1: read-only lists of what the workers
 * and the screens logged. Each reads the rows today's tab reads. Administrators only (manage_options):
 *
 *   GET  rejected    items the relevance check refused (aw_relevance_rejected_rows())
 *   GET  relay-log   requests the workers made through the site, the last 200 (aw_relay_log_rows())
 *   GET  changes     the Change log: who changed what and when (aw_sourcing_change_rows())
 *
 * Each takes view, search, orderby, order and page.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$text = array(
			'type'    => 'string',
			'default' => '',
		);
		$list = array(
			'view'    => $text,
			'search'  => $text,
			'orderby' => $text,
			'order'   => $text,
			'page'    => array(
				'type'    => 'integer',
				'default' => 1,
			),
		);
		foreach ( array(
			'/rejected'  => 'sourceweb_rest_rejected',
			'/relay-log' => 'sourceweb_rest_relay_log',
			'/changes'   => 'sourceweb_rest_changes',
		) as $route => $callback ) {
			register_rest_route(
				SOURCEWEB_REST,
				$route,
				array(
					'methods'             => 'GET',
					'permission_callback' => sourceweb_rest_can( AW_SRC_CAP ),
					'callback'            => $callback,
					'args'                => $list,
				)
			);
		}
	}
);

/**
 * GET rejected: one page of the Rejected log (views by type, and From an allowed publisher).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_rejected( WP_REST_Request $request ) {
	$rows  = aw_relevance_rejected_rows();
	$views = array( 'allowed' => array( 'From an allowed publisher', fn( $r ) => '' !== $r['problem'] ) );
	foreach ( array_unique( array_filter( array_column( $rows, 'type' ) ) ) as $t ) {
		$views[ 'type-' . sanitize_key( $t ) ] = array( $t, fn( $r ) => $r['type'] === $t );
	}
	foreach ( $rows as $i => &$r ) {
		$r['id'] = 'r' . $i;
	}
	unset( $r );
	return rest_ensure_response(
		sourceweb_list(
			$rows,
			$request->get_params(),
			array(
				'views' => $views,
				'text'  => fn( $r ) => $r['title'] . ' ' . $r['host'] . ' ' . $r['found'] . ' ' . $r['reason'],
				'sort'  => array(
					'type'  => fn( $r ) => strtolower( $r['type'] ),
					'found' => fn( $r ) => strtolower( $r['found'] ),
					'at'    => fn( $r ) => $r['at'],
				),
				'per'   => 50,
			)
		)
	);
}

/**
 * GET relay-log: one page of the relay requests (views Failed, and by host).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_relay_log( WP_REST_Request $request ) {
	$rows  = aw_relay_log_rows();
	$views = array( 'failed' => array( 'Failed', fn( $r ) => ! $r['ok'] ) );
	foreach ( array_unique( array_filter( array_column( $rows, 'host' ) ) ) as $h ) {
		$views[ 'host-' . sanitize_key( $h ) ] = array( $h, fn( $r ) => $r['host'] === $h );
	}
	foreach ( $rows as $i => &$r ) {
		$r['id'] = 'q' . $i;
	}
	unset( $r );
	return rest_ensure_response(
		sourceweb_list(
			$rows,
			$request->get_params(),
			array(
				'views' => $views,
				'text'  => fn( $r ) => $r['host'] . ' ' . $r['path'] . ' ' . $r['status'] . ' ' . $r['note'],
				'sort'  => array(
					'at'     => fn( $r ) => $r['at'],
					'host'   => fn( $r ) => strtolower( $r['host'] ),
					'status' => fn( $r ) => $r['status'],
					'bytes'  => fn( $r ) => $r['bytes'],
					'ms'     => fn( $r ) => $r['ms'],
				),
				'per'   => 50,
			)
		)
	);
}

/**
 * GET changes: one page of the Change log (views by tab).
 *
 * @param WP_REST_Request $request Request.
 */
function sourceweb_rest_changes( WP_REST_Request $request ) {
	$rows  = aw_sourcing_change_rows();
	$views = array();
	foreach ( array_unique( array_filter( array_column( $rows, 'tab' ) ) ) as $t ) {
		$views[ 'tab-' . sanitize_title( $t ) ] = array( $t, fn( $r ) => $r['tab'] === $t );
	}
	foreach ( $rows as $i => &$r ) {
		$r['id'] = 'c' . $i;
	}
	unset( $r );
	return rest_ensure_response(
		sourceweb_list(
			$rows,
			$request->get_params(),
			array(
				'views' => $views,
				'text'  => fn( $r ) => $r['user'] . ' ' . $r['tab'] . ' ' . $r['what'] . ' ' . $r['change'],
				'sort'  => array(
					'at'   => fn( $r ) => $r['at'],
					'user' => fn( $r ) => strtolower( $r['user'] ),
					'tab'  => fn( $r ) => strtolower( $r['tab'] ),
				),
				'per'   => 50,
			)
		)
	);
}
