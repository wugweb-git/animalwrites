<?php
/**
 * PlanWeb's routes for its new screens (docs/calendar-plugin-plan.md §4), under planweb/v1:
 *
 *   GET  dashboard                 edit_planweb_entries  the tiles, from the stored counts (planweb-dashboard.php)
 *   POST dashboard/count           edit_planweb_entries  ask for a count now (one background event)
 *   GET  entries                   edit_planweb_entries  one page of the list: search, filters, sort, paging
 *   POST entries                   edit_planweb_entries  add an entry (planweb_save_entry(), as the Calendar's form)
 *   POST entries/<id>              edit_post             change an entry (the same)
 *   POST entries/<id>/trash        delete_post           move an entry to the Trash (as the Calendar's Trash link)
 *   GET  settings                  manage_options        the switch, the lists and the Dates import check
 *   POST settings/switch           manage_options        the PlanWeb group: the switch (planweb_on) and "Delete this
 *                                                        plugin's data" (delete_data), each written only when it
 *                                                        changes; reset: true restores the group's defaults
 *   POST import                    manage_options        run the Dates import again (planweb_import())
 *   GET  services                  manage_options        the services the plan names, with their state
 *
 * Every route checks the capability and, for a signed-in browser, the wp_rest nonce (kits_shell_rest_can()). The core
 * routes (/wp/v2/planweb-entries) stay as they are.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PLANWEB_REST = 'planweb/v1';

/**
 * The permission check: Kits Core's when it is there, else the same steps here.
 *
 * @param string $cap Capability.
 * @return callable
 */
function planweb_rest_can( string $cap ): callable {
	if ( function_exists( 'kits_shell_rest_can' ) ) {
		return kits_shell_rest_can( $cap );
	}
	return function ( WP_REST_Request $request ) use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'planweb' ), array( 'status' => 401 ) );
		}
		$app = function_exists( 'rest_get_authenticated_app_password' ) && null !== rest_get_authenticated_app_password();
		if ( ! $app && ! wp_verify_nonce( (string) ( $request->get_header( 'X-WP-Nonce' ) ?? '' ), 'wp_rest' ) ) {
			return new WP_Error( 'rest_cookie_invalid_nonce', __( 'The page has expired. Reload it and try again.', 'planweb' ), array( 'status' => 403 ) );
		}
		return current_user_can( $cap ) ? true : new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'planweb' ), array( 'status' => 403 ) );
	};
}

add_action(
	'rest_api_init',
	function () {
		if ( ! planweb_on() ) {
			return;
		}
		$text   = fn( bool $required = false ) => array(
			'type'     => 'string',
			'required' => $required,
		);
		$fields = array(
			'date'    => $text( true ),
			'time'    => $text(),
			'title'   => $text( true ),
			'note'    => $text(),
			'type'    => $text( true ),
			'channel' => $text(),
			'owner'   => $text(),
			'status'  => $text(),
			'link'    => $text(),
		);
		$routes = array(
			'/dashboard'                 => array( 'GET', 'edit_planweb_entries', 'planweb_rest_dashboard', array() ),
			'/dashboard/count'           => array( 'POST', 'edit_planweb_entries', fn() => rest_ensure_response( array( 'waiting' => planweb_counts_request() ) ), array() ),
			'/entries'                   => array( 'GET', 'edit_planweb_entries', 'planweb_rest_entries', planweb_rest_list_params() ),
			'/entries/(?P<id>\d+)'       => array( 'POST', 'edit_planweb_entries', 'planweb_rest_save', $fields ),
			'/entries/(?P<id>\d+)/trash' => array( 'POST', 'edit_planweb_entries', 'planweb_rest_trash', array() ),
			'/settings'                  => array( 'GET', 'manage_options', fn() => rest_ensure_response( planweb_settings_payload() ), array() ),
			'/settings/switch'           => array(
				'POST',
				'manage_options',
				'planweb_rest_switch',
				array(
					'on'          => array( 'type' => 'boolean' ),
					'delete_data' => array( 'type' => 'boolean' ),
					'reset'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			'/import'                    => array( 'POST', 'manage_options', 'planweb_rest_import', array() ),
			'/services'                  => array( 'GET', 'manage_options', fn() => rest_ensure_response( array( 'services' => planweb_services() ) ), array() ),
		);
		foreach ( $routes as $route => $r ) {
			register_rest_route(
				PLANWEB_REST,
				$route,
				array(
					'methods'             => $r[0],
					'permission_callback' => planweb_rest_can( $r[1] ),
					'callback'            => $r[2],
					'args'                => $r[3],
				)
			);
		}
		// One month for the Dashboard's calendar grid.
		register_rest_route(
			PLANWEB_REST,
			'/month',
			array(
				'methods'             => 'GET',
				'permission_callback' => planweb_rest_can( 'edit_planweb_entries' ),
				'callback'            => 'planweb_rest_month',
				'args'                => array(
					'm' => array(
						'type'    => 'string',
						'pattern' => '^\\d{4}-(0[1-9]|1[0-2])$',
					),
				),
			)
		);
		// One entry, for its address (Entries &edit=ID opens its form).
		register_rest_route(
			PLANWEB_REST,
			'/entries/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => planweb_rest_can( 'edit_planweb_entries' ),
				'callback'            => 'planweb_rest_entry',
				'args'                => array(),
			)
		);
		// Adding: the same fields, no id.
		register_rest_route(
			PLANWEB_REST,
			'/entries',
			array(
				'methods'             => 'POST',
				'permission_callback' => planweb_rest_can( 'edit_planweb_entries' ),
				'callback'            => 'planweb_rest_save',
				'args'                => $fields,
			)
		);
	}
);

/** The list's query parameters. */
function planweb_rest_list_params(): array {
	$enum = fn( array $values ) => array(
		'type' => 'string',
		'enum' => array_merge( array( '' ), array_map( 'strval', $values ) ),
	);
	return array(
		'page'    => array(
			'type'    => 'integer',
			'minimum' => 1,
			'default' => 1,
		),
		'search'  => array(
			'type'    => 'string',
			'default' => '',
		),
		'type'    => $enum( array_keys( PLANWEB_TYPES ) ),
		'channel' => $enum( array_keys( PLANWEB_CHANNELS ) ),
		'owner'   => $enum( array_keys( PLANWEB_OWNERS ) ),
		'status'  => $enum( array_keys( PLANWEB_STATUSES ) ),
		'view'    => $enum( array( 'all', 'overdue', 'waiting' ) ),
		'when'    => $enum( array_keys( PLANWEB_WINDOWS ) ),
		'sort'    => $enum( array( 'date', 'title' ) ),
		'dir'     => $enum( array( 'asc', 'desc' ) ),
	);
}

/* ---------- Dashboard ---------- */

/** The Dashboard: the tiles for each range from the stored counts (formatting only, nothing is counted here). */
function planweb_rest_dashboard() {
	$c        = get_transient( PLANWEB_COUNTS );
	$counting = ! is_array( $c ) || empty( $c['at'] );
	$today    = wp_date( 'Y-m-d' );
	$stale    = ! $counting && $c['day'] !== $today;
	if ( $counting || $stale ) {
		planweb_counts_request(); // The first view, or a new day: count in the background.
	}
	$c   = $counting ? planweb_count_entries( array(), $today ) : $c;
	$url = fn( array $args ) => planweb_ui_url( 'entries', $args );
	$out = array(
		'counting' => $counting,
		'stale'    => $stale,
		'at'       => $counting ? 0 : (int) $c['at'],
		'atText'   => $counting ? '' : wp_date( 'j M Y, g:i a', (int) $c['at'] ),
		'ranges'   => PLANWEB_RANGES,
		'tiles'    => array(),
		'byType'   => array(),
		'byStatus' => array(),
	);
	foreach ( PLANWEB_RANGES as $r ) {
		$out['tiles'][ $r ]  = planweb_dashboard_tiles( $c, $r, $url, 'planweb_date_label' );
		$out['byType'][ $r ] = array();
		foreach ( PLANWEB_TYPES as $slug => $label ) {
			$out['byType'][ $r ][] = array(
				'slug'  => $slug,
				'label' => $label,
				'count' => (int) ( $c['ahead'][ $r ]['types'][ $slug ] ?? 0 ),
				'href'  => $url(
					array(
						'type' => $slug,
						'when' => 'next' . $r,
					)
				),
			);
		}
	}
	foreach ( PLANWEB_STATUSES as $slug => $label ) {
		$out['byStatus'][] = array(
			'slug'  => $slug,
			'label' => $label,
			'count' => (int) ( $c['statuses'][ $slug ] ?? 0 ),
			'href'  => $url(
				array(
					'status' => $slug,
					'when'   => 'all',
				)
			),
		);
	}
	return rest_ensure_response( $out );
}

/* ---------- Entries ---------- */

/**
 * One entry as the list shows it.
 *
 * @param array  $e     planweb_entry().
 * @param string $today Y-m-d.
 */
function planweb_rest_row( array $e, string $today ): array {
	return $e + array(
		'dateText'    => '' !== $e['date'] ? planweb_date_label( $e['date'] ) : '',
		'whenText'    => '' !== $e['date'] ? planweb_when( $e['date'] ) : '',
		'past'        => $e['date'] < $today,
		'overdue'     => planweb_is_overdue( $e, $today ),
		'typeText'    => PLANWEB_TYPES[ $e['type'] ] ?? '',
		'channelText' => PLANWEB_CHANNELS[ $e['channel'] ] ?? '',
		'ownerText'   => PLANWEB_OWNERS[ $e['owner'] ] ?? '',
		'statusText'  => PLANWEB_STATUSES[ $e['status'] ] ?? $e['status'],
		'linkHost'    => '' !== $e['link'] ? (string) wp_parse_url( $e['link'], PHP_URL_HOST ) : '',
		'canEdit'     => current_user_can( 'edit_post', $e['id'] ),
		'canTrash'    => current_user_can( 'delete_post', $e['id'] ),
		'oldUrl'      => planweb_url(
			array(
				'edit' => $e['id'],
				'm'    => substr( $e['date'], 0, 7 ),
			)
		) . '#planweb-form',
	);
}

/**
 * GET month?m=Y-m: the month's entries (not the Trash), as the Calendar's grid shows them, each with the address
 * that opens its form on Entries. No m: this month.
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_month( WP_REST_Request $request ) {
	$today = wp_date( 'Y-m-d' );
	$month = preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request['m'] ) ? (string) $request['m'] : substr( $today, 0, 7 );
	$last  = (int) wp_date( 't', strtotime( $month . '-01 12:00:00' ) );
	$rows  = array();
	foreach ( planweb_entries( $month . '-01', sprintf( '%s-%02d', $month, $last ) ) as $e ) {
		$rows[] = planweb_rest_row( $e, $today ) + array( 'editUrl' => planweb_ui_url( 'entries', array( 'edit' => $e['id'] ) ) );
	}
	return rest_ensure_response(
		array(
			'month'   => $month,
			'today'   => $today,
			'entries' => $rows,
		)
	);
}

/**
 * GET entries/<id>: one entry as the list shows it. Not one in the Trash, and only for someone who may edit it.
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_entry( WP_REST_Request $request ) {
	$id = (int) ( $request->get_url_params()['id'] ?? 0 );
	$e  = $id && 'publish' === get_post_status( $id ) ? planweb_entry( $id ) : null;
	if ( ! $e ) {
		return new WP_Error( 'rest_post_invalid_id', __( 'No such entry.', 'planweb' ), array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'planweb' ), array( 'status' => 403 ) );
	}
	return rest_ensure_response( array( 'entry' => planweb_rest_row( $e, wp_date( 'Y-m-d' ) ) ) );
}

/**
 * One page of the list. Search covers title and note; filters, sort and paging are done in the query.
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_entries( WP_REST_Request $request ) {
	$today = wp_date( 'Y-m-d' );
	$a     = planweb_list_args( $request->get_params(), $today );
	$mq    = array(
		'pw_date' => array(
			'key'  => 'planweb_date',
			'type' => 'DATE',
		),
	);
	foreach ( array(
		'from'   => '>=',
		'to'     => '<=',
		'before' => '<',
	) as $k => $compare ) {
		if ( '' !== $a[ $k ] ) {
			$mq[] = array(
				'key'     => 'planweb_date',
				'value'   => $a[ $k ],
				'compare' => $compare,
				'type'    => 'DATE',
			);
		}
	}
	if ( '' !== $a['status'] ) {
		// "Approved" also lists Due: an approved entry on its day.
		$status = array(
			'key'     => 'planweb_status',
			'value'   => 'approved' === $a['status'] ? array( 'approved', 'due' ) : array( $a['status'] ),
			'compare' => 'IN',
		);
		// An entry with no stored status is an idea (planweb_entry()).
		$mq[] = 'idea' === $a['status'] ? array(
			'relation' => 'OR',
			$status,
			array(
				'key'     => 'planweb_status',
				'compare' => 'NOT EXISTS',
			),
		) : $status;
	}
	if ( 'waiting' === $a['view'] ) {
		$mq[] = array(
			'key'   => 'planweb_status',
			'value' => 'draft',
		);
	}
	if ( 'overdue' === $a['view'] ) {
		$mq[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'planweb_status',
				'value'   => PLANWEB_OPEN_STATUSES,
				'compare' => 'IN',
			),
			array(
				'key'     => 'planweb_status',
				'compare' => 'NOT EXISTS',
			),
		);
	}
	$tax = array();
	foreach ( array(
		'type'    => 'planweb_type',
		'channel' => 'planweb_channel',
		'owner'   => 'planweb_owner',
	) as $k => $taxonomy ) {
		if ( '' !== $a[ $k ] ) {
			$tax[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $a[ $k ],
			);
		}
	}
	$order = strtoupper( $a['dir'] );
	$args  = array(
		'post_type'      => PLANWEB_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => PLANWEB_PER_PAGE,
		'paged'          => $a['page'],
		'meta_query'     => $mq, // phpcs:ignore WordPress.DB.SlowDBQuery -- a few hundred entries at most.
		'orderby'        => 'title' === $a['sort'] ? array(
			'title'   => $order,
			'pw_date' => 'ASC',
		) : array(
			'pw_date' => $order,
			'title'   => 'ASC',
		),
	);
	if ( $tax ) {
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery -- a few hundred entries at most.
	}
	if ( '' !== $a['search'] ) {
		$args['s'] = $a['search'];
	}
	$q     = new WP_Query( $args );
	$rows  = array_values( array_filter( array_map( 'planweb_entry', $q->posts ) ) );
	$c     = get_transient( PLANWEB_COUNTS );
	$views = array(
		array(
			'value' => 'all',
			'label' => __( 'All', 'planweb' ),
		),
		array(
			'value' => 'overdue',
			'label' => __( 'Overdue', 'planweb' ),
			'count' => is_array( $c ) ? (int) ( $c['overdue']['total'] ?? 0 ) : null,
		),
		array(
			'value' => 'waiting',
			'label' => __( 'Waiting for approval', 'planweb' ),
			'count' => is_array( $c ) ? (int) ( $c['waiting'] ?? 0 ) : null,
		),
	);
	return rest_ensure_response(
		array(
			'rows'  => array_map( fn( $e ) => planweb_rest_row( $e, $today ), $rows ),
			'total' => (int) $q->found_posts,
			'page'  => $a['page'],
			'pages' => max( 1, (int) $q->max_num_pages ),
			'args'  => array_diff_key( $a, array_flip( array( 'from', 'to', 'before' ) ) ),
			'views' => array_map( fn( $v ) => array_filter( $v, fn( $x ) => null !== $x ), $views ),
		)
	);
}

/**
 * Add or change an entry through planweb_save_entry(), with the Calendar form's checks.
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_save( WP_REST_Request $request ) {
	$id = (int) ( $request->get_url_params()['id'] ?? 0 );
	if ( $id && ( ! planweb_entry( $id ) || 'publish' !== get_post_status( $id ) ) ) {
		return new WP_Error( 'rest_post_invalid_id', __( 'No such entry.', 'planweb' ), array( 'status' => 404 ) );
	}
	if ( $id && ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'planweb' ), array( 'status' => 403 ) );
	}
	$stored = $id ? planweb_entry( $id ) : array();
	$params = $request->get_params();
	unset( $params['id'] );
	// A change keeps the stored value of every field the request leaves out.
	$f = planweb_clean_form( array_merge( $stored, $params ) );
	if ( '' === $f['status'] ) {
		$f['status'] = $id ? (string) $stored['status'] : 'idea';
	}
	$saved = planweb_save_entry( $f, $id );
	if ( is_wp_error( $saved ) ) {
		$data = (array) $saved->get_error_data();
		return new WP_Error( $saved->get_error_code(), $saved->get_error_message(), array( 'status' => (int) ( $data['status'] ?? 400 ) ) );
	}
	return rest_ensure_response(
		array(
			'entry'   => planweb_rest_row( planweb_entry( $saved ), wp_date( 'Y-m-d' ) ),
			'message' => __( 'Entry saved.', 'planweb' ),
		)
	);
}

/**
 * Move an entry to the Trash (never deleted for good).
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_trash( WP_REST_Request $request ) {
	$id = (int) ( $request->get_url_params()['id'] ?? 0 );
	if ( ! planweb_entry( $id ) || 'publish' !== get_post_status( $id ) ) {
		return new WP_Error( 'rest_post_invalid_id', __( 'No such entry.', 'planweb' ), array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'delete_post', $id ) ) {
		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'planweb' ), array( 'status' => 403 ) );
	}
	if ( ! EMPTY_TRASH_DAYS ) {
		return new WP_Error( 'rest_trash_not_supported', __( 'The Trash is turned off on this site, so the entry was kept.', 'planweb' ), array( 'status' => 501 ) );
	}
	if ( ! wp_trash_post( $id ) ) {
		return new WP_Error( 'rest_cannot_delete', __( 'The entry could not be moved to the Trash.', 'planweb' ), array( 'status' => 500 ) );
	}
	return rest_ensure_response(
		array(
			'id'      => $id,
			'message' => __( 'Entry moved to the Trash.', 'planweb' ),
		)
	);
}

/* ---------- Settings ---------- */

/** The import group: the stored result and the one-for-one check now (as the Calendar's import panel). */
function planweb_import_payload(): array {
	$r = (array) get_option( 'planweb_import_result', array() );
	$v = planweb_verify();
	return array(
		'ran'      => ! empty( $r ),
		'atText'   => ! empty( $r['time'] ) ? wp_date( 'j M Y, g:i a', (int) $r['time'] ) : '',
		'stored'   => (int) ( $r['stored'] ?? 0 ),
		'created'  => (int) ( $r['created'] ?? 0 ),
		'skipped'  => (int) ( $r['skipped'] ?? 0 ),
		'failed'   => array_values( (array) ( $r['failed'] ?? array() ) ),
		'personal' => array_map( 'intval', (array) ( $r['personal'] ?? array() ) ),
		'check'    => array(
			'same'    => (int) $v['same'],
			'differs' => (int) $v['differs'],
			'missing' => (int) $v['missing'],
			'extra'   => (int) $v['extra'],
		),
		'invalid'  => array_values( $v['invalid'] ),
		'odd'      => array_values(
			array_map(
				fn( $l ) => array(
					'state' => $l['state'],
					'dates' => $l['dates'] ? array_intersect_key( $l['dates'], array_flip( array( 'date', 'title', 'note' ) ) ) : null,
					'entry' => $l['entry'] ? array_intersect_key( $l['entry'], array_flip( array( 'date', 'title', 'note' ) ) ) : null,
				),
				array_filter( $v['lines'], fn( $l ) => 'same' !== $l['state'] )
			)
		),
	);
}

/** Every Settings group. */
function planweb_settings_payload(): array {
	$lists = array();
	foreach ( array(
		'planweb_type'    => array( __( 'Types', 'planweb' ), PLANWEB_TYPES ),
		'planweb_channel' => array( __( 'Channels', 'planweb' ), PLANWEB_CHANNELS ),
		'planweb_owner'   => array( __( 'Owners', 'planweb' ), PLANWEB_OWNERS ),
	) as $tax => $l ) {
		$used = array();
		foreach ( (array) get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
			)
		) as $t ) {
			if ( $t instanceof WP_Term ) {
				$used[ $t->slug ] = (int) $t->count; // Stored by WordPress; nothing is counted here.
			}
		}
		$values = array();
		foreach ( $l[1] as $slug => $label ) {
			$values[] = array(
				'slug'  => $slug,
				'label' => $label,
				'count' => $used[ $slug ] ?? 0,
			);
		}
		$lists[] = array(
			'key'    => $tax,
			'label'  => $l[0],
			'values' => $values,
		);
	}
	return array(
		'switch' => array(
			'on'         => planweb_on(),
			'deleteData' => planweb_delete_data_on(),
		),
		'lists'  => $lists,
		'import' => planweb_import_payload(),
	);
}

/**
 * The PlanWeb group: the switch, written only when it changes, through the same clean-up as Kits → Modules, and
 * "Delete this plugin's data when it is deleted". Each only when sent. reset: true is "Reset this section"
 * (planweb_settings_reset(): the switch is left as it is).
 *
 * @param WP_REST_Request $request Request.
 */
function planweb_rest_switch( WP_REST_Request $request ) {
	if ( $request['reset'] ) {
		$saved = planweb_settings_reset();
		return rest_ensure_response(
			array(
				'saved'      => $saved,
				'on'         => planweb_on(),
				'deleteData' => planweb_delete_data_on(),
				'message'    => __( 'Defaults restored.', 'planweb' ),
			)
		);
	}
	$new = null === $request['on'] ? null : planweb_switch_change( (bool) $request['on'], (string) get_option( 'planweb_on', '' ) );
	if ( null !== $new ) {
		update_option( 'planweb_on', $new, true );
	}
	$data = null !== $request['delete_data'] && planweb_delete_data_save( (bool) $request['delete_data'] );
	if ( null !== $new ) {
		$message = planweb_on() ? __( 'Saved. PlanWeb is on.', 'planweb' ) : __( 'Saved. PlanWeb is off: Kits → Dates is back, and the entries are kept.', 'planweb' );
	} else {
		$message = $data ? __( 'Saved.', 'planweb' ) : __( 'Nothing changed.', 'planweb' );
	}
	return rest_ensure_response(
		array(
			'saved'      => null !== $new || $data,
			'on'         => planweb_on(),
			'deleteData' => planweb_delete_data_on(),
			'message'    => $message,
		)
	);
}

/** Run the Dates import again (adds only dates not imported yet; Dates itself is never written). */
function planweb_rest_import() {
	planweb_import();
	planweb_counts_request();
	return rest_ensure_response(
		array(
			'import'  => planweb_import_payload(),
			'message' => __( 'Import run.', 'planweb' ),
		)
	);
}

/* ---------- Connected services ---------- */

/** The services the plan names (4.4). None is connected; nothing is posted or sent from PlanWeb. */
function planweb_services(): array {
	$fluent = defined( 'FLUENTCRM' ) || class_exists( '\FluentCrm\App\Models\Campaign' );
	$social = function_exists( 'aw_social_on' ) ? aw_social_on() : null;
	$later  = __( 'PlanWeb does not read it yet: the read-only feed comes in a later step.', 'planweb' );
	return array(
		array(
			'slug'    => 'fluentcrm',
			'name'    => 'FluentCRM',
			'status'  => $fluent ? __( 'Active', 'planweb' ) : __( 'Not active', 'planweb' ),
			'tone'    => $fluent ? '' : 'warn',
			'summary' => $fluent ? $later : __( 'Scheduled newsletter sends show here once FluentCRM is active.', 'planweb' ),
		),
		array(
			'slug'    => 'social-posts',
			'name'    => __( 'Social posts', 'planweb' ),
			'status'  => null === $social ? __( 'Not available', 'planweb' ) : ( $social ? __( 'On', 'planweb' ) : __( 'Off', 'planweb' ) ),
			'tone'    => $social ? '' : 'warn',
			'summary' => $social ? $later : __( 'Social posts is off, so there are no social drafts to show.', 'planweb' ),
		),
		array(
			'slug'    => 'metricool',
			'name'    => 'Metricool',
			'status'  => __( 'Not connected', 'planweb' ),
			'tone'    => 'warn',
			'summary' => __( 'Not chosen yet. Nothing is posted from PlanWeb.', 'planweb' ),
		),
		array(
			'slug'    => 'postiz',
			'name'    => 'Postiz',
			'status'  => __( 'Not connected', 'planweb' ),
			'tone'    => 'warn',
			'summary' => __( 'Not chosen yet. Nothing is posted from PlanWeb.', 'planweb' ),
		),
	);
}
