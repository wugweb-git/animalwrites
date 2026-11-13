<?php
/**
 * DirectoryWeb's routes for its new screens (docs/directory-plugin-plan.md §2), under directoryweb/v1:
 *
 *   GET  dashboard                 manage_directoryweb the tiles, from the background counts (directoryweb-dashboard.php)
 *   POST dashboard/count           manage_directoryweb ask for a count now (one background event)
 *   GET  stats/<tile>              manage_directoryweb one tile (published, status, places, indexing, search, claims,
 *                                                      badges, enrichment), from the same stored counts
 *   GET  claims                    edit_aw_volunteers  the Claims list: views, search, filters, sort, pages
 *   GET  claims/<id>               edit_aw_volunteers  one claim, field by field, with the proposed changes
 *   GET  settings                  manage_options      the services and the groups, with today's values
 *   POST settings/claim-form       manage_options      which Fluent Forms form is the claim form (option aw_ff_forms)
 *   GET  listings                  edit_posts          the Listings tab, read only (directoryweb-listings.php)
 *
 * Every route uses kits_shell_rest_can(): 401 signed out, 403 without the wp_rest nonce in a browser, 403 without the
 * capability. The aw/v1 routes are not touched. No key is read or sent.
 *
 * The Claims routes only read: no route here decides, applies or undoes a claim, or changes a listing. Claims are
 * reviewed on Directory → Claims (claims-review.php).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_REST = 'directoryweb/v1';

/**
 * The permission check for a route (Kits Core's; refused when Kits Core is missing).
 *
 * @param string $cap Capability.
 * @return callable
 */
function directoryweb_rest_can( string $cap ): callable {
	if ( function_exists( 'kits_shell_rest_can' ) ) {
		return kits_shell_rest_can( $cap );
	}
	return fn() => new WP_Error( 'rest_forbidden', __( 'This needs Kits Core.', 'directoryweb' ), array( 'status' => 403 ) );
}

add_action(
	'rest_api_init',
	function () {
		$dash   = directoryweb_rest_can( DIRECTORYWEB_CAP ); // directoryweb-caps.php
		$claims = directoryweb_rest_can( 'edit_aw_volunteers' ); // claim data: as on Directory → Claims
		$admin  = directoryweb_rest_can( 'manage_options' );
		register_rest_route(
			DIRECTORYWEB_REST,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'permission_callback' => $dash,
				'callback'            => fn() => rest_ensure_response( directoryweb_dashboard_payload() ),
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/dashboard/count',
			array(
				'methods'             => 'POST',
				'permission_callback' => $dash,
				'callback'            => fn() => rest_ensure_response( array( 'waiting' => directoryweb_stats_request() ) ),
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/stats/(?P<key>[a-z]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $dash,
				'callback'            => 'directoryweb_rest_stat',
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/claims',
			array(
				'methods'             => 'GET',
				'permission_callback' => $claims,
				'args'                => array(
					'status' => array(
						'type'    => 'string',
						'enum'    => array( 'pending', 'approved', 'rejected', 'all' ),
						'default' => 'pending',
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
					'state'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'type'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'has'    => array(
						'type'    => 'string',
						'enum'    => array( '', 'logo', 'photos', 'proof' ),
						'default' => '',
					),
					'days'   => array(
						'type'    => 'integer',
						'enum'    => array( 0, 7, 30, 90 ),
						'default' => 0,
					),
					'sort'   => array(
						'type'    => 'string',
						'enum'    => array( 'sent', 'listing' ),
						'default' => 'sent',
					),
					'dir'    => array(
						'type'    => 'string',
						'enum'    => array( 'asc', 'desc' ),
						'default' => 'desc',
					),
					'page'   => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
				'callback'            => 'directoryweb_rest_claims',
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/claims/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => $claims,
				'callback'            => 'directoryweb_rest_claim',
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/settings',
			array(
				'methods'             => 'GET',
				'permission_callback' => $admin,
				'callback'            => fn() => rest_ensure_response( directoryweb_settings_payload() ),
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/settings/claim-form',
			array(
				'methods'             => 'POST',
				'permission_callback' => $admin,
				'args'                => array(
					'form' => array(
						'type'     => 'integer',
						'minimum'  => 0,
						'required' => true,
					),
				),
				'callback'            => 'directoryweb_rest_save_claim_form',
			)
		);
	}
);

/* ---------- Dashboard ---------- */

/** The Dashboard: tiles from the stored counts (formatting only, nothing is counted here). */
function directoryweb_dashboard_payload(): array {
	$c        = get_transient( DIRECTORYWEB_STATS );
	$counting = ! is_array( $c );
	if ( $counting ) {
		directoryweb_stats_request(); // The first view after a deploy: count in the background.
		$c = array();
	}
	$num   = fn( $n ) => number_format_i18n( (int) $n );
	$l     = (array) ( $c['listings'] ?? array() );
	$st    = (array) ( $l['status'] ?? array() );
	$pub   = (int) ( $st['publish'] ?? 0 );
	$list  = add_query_arg( 'post_type', 'aw_listing', admin_url( 'edit.php' ) );
	$tiles = array();

	$top  = directoryweb_top_types( (array) ( $l['types'] ?? array() ) );
	$name = fn( $slug ) => function_exists( 'aw_listing_type_name' ) && '' !== aw_listing_type_name( (string) $slug ) ? aw_listing_type_name( (string) $slug ) : (string) $slug;
	$most = array();
	foreach ( $top['top'] as $slug => $n ) {
		$most[] = $name( $slug ) . ' ' . $num( $n );
	}
	if ( $top['others'] ) {
		/* translators: %s: number of listings. */
		$most[] = sprintf( __( 'other types %s', 'directoryweb' ), $num( $top['others'] ) );
	}
	$tiles[] = array(
		'key'     => 'published',
		'section' => 'listings',
		'label'   => __( 'Published listings', 'directoryweb' ),
		'value'   => $num( $pub ),
		'sub'     => $most ? implode( ' · ', $most ) : __( 'No listing type counted yet', 'directoryweb' ),
		'href'    => add_query_arg( 'post_status', 'publish', $list ),
		'detail'  => $most,
	);
	$off     = (int) ( $st['draft'] ?? 0 ) + (int) ( $st['pending'] ?? 0 ) + (int) ( $st['private'] ?? 0 );
	$tiles[] = array(
		'key'     => 'status',
		'section' => 'listings',
		'label'   => __( 'Not published', 'directoryweb' ),
		'value'   => $num( $off ),
		/* translators: 1: drafts, 2: waiting for review, 3: private. */
		'sub'     => sprintf( __( '%1$s drafts · %2$s waiting for review · %3$s private', 'directoryweb' ), $num( $st['draft'] ?? 0 ), $num( $st['pending'] ?? 0 ), $num( $st['private'] ?? 0 ) ),
		'href'    => add_query_arg( 'post_status', 'draft', $list ),
	);
	$p       = $c['places'] ?? null;
	$tiles[] = array(
		'key'     => 'places',
		'section' => 'listings',
		'label'   => __( 'Places covered', 'directoryweb' ),
		/* translators: 1: states with a listing, 2: all states. */
		'value'   => is_array( $p ) ? sprintf( __( '%1$s of %2$s states', 'directoryweb' ), $num( $p['states'] ), $num( $p['states_all'] ) ) : '',
		/* translators: 1: districts with a listing, 2: all districts, 3: place file version. */
		'sub'     => is_array( $p ) ? sprintf( __( '%1$s of %2$s districts · place file %3$s', 'directoryweb' ), $num( $p['districts'] ), $num( $p['districts_all'] ), $p['version'] ) : '',
		'href'    => current_user_can( DIRECTORYWEB_LISTINGS_CAP ) ? add_query_arg( 'view', 'publish', directoryweb_ui_url( 'listings' ) ) : '',
	);
	$x       = $c['indexing'] ?? null;
	$rank    = function_exists( 'rankweb_ui_url' ) && current_user_can( 'manage_options' ) ? rankweb_ui_url( 'settings' ) . '#indexing' : '';
	$tile    = array(
		'key'     => 'indexing',
		'section' => 'indexing',
		'label'   => __( 'Indexable listings', 'directoryweb' ),
		'href'    => $rank,
	);
	if ( ! $counting && null === $x ) {
		$tile['missing'] = 'RankWeb';
	} else {
		$x             = (array) $x;
		$tile['value'] = $num( max( 0, $pub - (int) ( $x['nocontact'] ?? 0 ) ) );
		/* translators: 1: listings with no contact, 2: listings kept indexed. */
		$tile['sub'] = sprintf( __( '%1$s with no contact (kept out of search) · %2$s kept indexed on purpose', 'directoryweb' ), $num( $x['nocontact'] ?? 0 ), $num( $x['kept'] ?? 0 ) );
	}
	$tiles[] = $tile;
	$si      = defined( 'INSIGHTWEB_LOADED' ) && function_exists( 'aw_si_url' );
	$tiles[] = array(
		'key'     => 'search',
		'section' => 'indexing',
		'label'   => __( 'Directory search clicks', 'directoryweb' ),
		'value'   => $si ? __( 'In InsightWeb', 'directoryweb' ) : '',
		'sub'     => $si ? __( 'Search Console clicks for /directory/ pages are in Search Insights', 'directoryweb' ) : '',
		'href'    => $si && function_exists( 'aw_si_cap' ) && current_user_can( aw_si_cap() ) ? aw_si_url() : '',
		'missing' => $si ? '' : 'InsightWeb',
		'hide'    => ! $si,
		'live'    => true,
	);
	$k       = $c['claims'] ?? null;
	$tile    = array(
		'key'     => 'claims',
		'section' => 'claims',
		'label'   => __( 'Claims waiting', 'directoryweb' ),
		'href'    => directoryweb_ui_url( 'claims' ),
	);
	if ( ! $counting && null === $k ) {
		$tile['missing'] = 'Fluent Forms';
	} else {
		$k             = (array) $k;
		$tile['value'] = $num( $k['pending'] ?? 0 );
		$age           = '' !== (string) ( $k['oldest'] ?? '' ) ? directoryweb_age_days( (string) $k['oldest'], (int) current_time( 'timestamp' ) ) : null; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- compared with the entries' site-time dates
		/* translators: %s: number of days. */
		$tile['sub']  = null !== $age ? sprintf( _n( 'The oldest has waited %s day', 'The oldest has waited %s days', $age, 'directoryweb' ), $num( $age ) ) : __( 'None waiting', 'directoryweb' );
		$tile['tone'] = ! empty( $k['pending'] ) ? 'warn' : '';
	}
	$tiles[] = $tile;
	$b       = (array) ( $c['badges'] ?? array() );
	$tiles[] = array(
		'key'     => 'badges',
		'section' => 'claims',
		'label'   => __( 'Claimed listings', 'directoryweb' ),
		'value'   => $num( $b['claimed'] ?? 0 ),
		/* translators: 1: Verified listings, 2: listings that need a re-check. */
		'sub'     => sprintf( __( '%1$s Verified from government records · %2$s need a re-check', 'directoryweb' ), $num( $b['verified'] ?? 0 ), $num( $b['recheck'] ?? 0 ) ),
		'href'    => current_user_can( DIRECTORYWEB_LISTINGS_CAP ) ? add_query_arg( 'view', 'claimed', directoryweb_ui_url( 'listings' ) ) : '',
		'tone'    => ! empty( $b['recheck'] ) ? 'warn' : '',
	);
	$e       = $c['enrichment'] ?? null;
	$tile    = array(
		'key'     => 'enrichment',
		'section' => 'enrichment',
		'label'   => __( 'Enrichment queue', 'directoryweb' ),
		'href'    => function_exists( 'aw_enrich_review_page' ) ? add_query_arg( 'page', 'aw-enrich-review', admin_url( 'admin.php' ) ) : '',
	);
	if ( ! $counting && null === $e ) {
		$tile['missing'] = 'SourceWeb';
	} else {
		$e             = (array) $e;
		$tile['value'] = $num( $e['pending'] ?? 0 );
		$last          = ! empty( $e['last'] ) ? mysql2date( 'j M Y', (string) $e['last'] ) : __( 'never', 'directoryweb' );
		/* translators: 1: listings waiting for Google, 2: date of the newest change. */
		$tile['sub'] = sprintf( __( '%1$s listings waiting for Google · newest change %2$s', 'directoryweb' ), $num( $e['google'] ?? 0 ), $last );
	}
	$tiles[] = $tile;

	return array(
		'counting' => $counting,
		'at'       => (int) ( $c['at'] ?? 0 ),
		'atText'   => ! empty( $c['at'] ) ? wp_date( 'j M Y, g:i a', (int) $c['at'] ) : '',
		'sections' => array(
			array(
				'value' => 'all',
				'label' => __( 'All', 'directoryweb' ),
			),
			array(
				'value' => 'listings',
				'label' => __( 'Listings', 'directoryweb' ),
			),
			array(
				'value' => 'indexing',
				'label' => __( 'Indexing', 'directoryweb' ),
			),
			array(
				'value' => 'claims',
				'label' => __( 'Claims', 'directoryweb' ),
			),
			array(
				'value' => 'enrichment',
				'label' => __( 'Enrichment', 'directoryweb' ),
			),
		),
		'tiles'    => $tiles,
	);
}

/**
 * GET stats/<key>: one Dashboard tile, from the same stored counts as the Dashboard (nothing is counted here).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function directoryweb_rest_stat( WP_REST_Request $request ) {
	$p = directoryweb_dashboard_payload();
	foreach ( $p['tiles'] as $tile ) {
		if ( (string) $request['key'] === $tile['key'] ) {
			return rest_ensure_response(
				array(
					'counting' => $p['counting'],
					'at'       => $p['at'],
					'atText'   => $p['atText'],
					'tile'     => $tile,
				)
			);
		}
	}
	return new WP_Error( 'directoryweb_no_tile', __( 'No such tile.', 'directoryweb' ), array( 'status' => 404 ) );
}

/* ---------- Claims ---------- */

/**
 * The Claims list. The light rows are filtered and paged here; only the rows of this page are compared with their
 * listing (the "Changes" count).
 *
 * @param WP_REST_Request $request Request.
 */
function directoryweb_rest_claims( WP_REST_Request $request ) {
	$light = directoryweb_claim_light_rows();
	$ids   = array_values( array_unique( array_filter( wp_list_pluck( $light, 'listing' ) ) ) );
	if ( $ids ) {
		_prime_post_caches( $ids, true, true );
	}
	$types = function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array();
	foreach ( $light as &$r ) {
		$lid                = 'aw_listing' === get_post_type( $r['listing'] ) ? (int) $r['listing'] : 0;
		$r['listing']       = $lid;
		$r['listing_title'] = $lid ? html_entity_decode( get_the_title( $lid ), ENT_QUOTES, 'UTF-8' ) : '';
		$r['state']         = $lid ? (int) get_post_meta( $lid, 'aw_state_code', true ) : 0;
		$terms              = $lid ? get_the_terms( $lid, 'post_tag' ) : array();
		$r['types']         = is_array( $terms ) ? array_values( array_intersect( wp_list_pluck( $terms, 'slug' ), $types ) ) : array();
	}
	unset( $r );
	$q      = directoryweb_claims_query(
		$light,
		array(
			'status' => (string) $request['status'],
			'search' => (string) $request['search'],
			'state'  => (int) $request['state'],
			'type'   => sanitize_title( (string) $request['type'] ),
			'has'    => (string) $request['has'],
			'days'   => (int) $request['days'],
			'sort'   => (string) $request['sort'],
			'dir'    => (string) $request['dir'],
			'page'   => (int) $request['page'],
			'per'    => 20,
			'now'    => (int) current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- the entries' dates are site time
		)
	);
	$states = array();
	if ( function_exists( 'aw_place_states' ) ) {
		foreach ( aw_place_states() as $s ) {
			$states[] = array(
				'value' => (string) $s->code,
				'label' => (string) $s->name,
			);
		}
	}
	$type_opts = array();
	foreach ( $types as $slug ) {
		$type_opts[] = array(
			'value' => (string) $slug,
			'label' => function_exists( 'aw_listing_type_name' ) && '' !== aw_listing_type_name( $slug ) ? aw_listing_type_name( $slug ) : (string) $slug,
		);
	}
	return rest_ensure_response(
		array(
			'rows'   => array_map( 'directoryweb_claim_row', $q['rows'] ),
			'total'  => $q['total'],
			'page'   => $q['page'],
			'pages'  => $q['pages'],
			'counts' => $q['counts'],
			'form'   => function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0,
			'states' => $states,
			'types'  => $type_opts,
		)
	);
}

/**
 * Status words.
 *
 * @param string $status pending, approved or rejected.
 */
function directoryweb_status_text( string $status ): string {
	return array(
		'pending'  => __( 'Waiting', 'directoryweb' ),
		'approved' => __( 'Approved', 'directoryweb' ),
		'rejected' => __( 'Rejected', 'directoryweb' ),
	)[ $status ] ?? $status;
}

/**
 * A list row as the screen shows it.
 *
 * @param array $r Light row with listing_title.
 */
function directoryweb_claim_row( array $r ): array {
	$changes = null;
	if ( $r['old'] ) {
		$changes = count( (array) get_post_meta( $r['entry'], 'aw_c_proposed', true ) ) + ( $r['has']['logo'] ? 1 : 0 );
	} elseif ( function_exists( 'aw_claim_entry' ) ) {
		$c = aw_claim_entry( $r['entry'] );
		if ( $c && $c['listing'] ) {
			$changes = count( aw_claim_changes( $c ) ) + ( '' !== $c['logo'] ? 1 : 0 ) + ( $c['photos'] ? 1 : 0 );
		}
	}
	$by = (int) ( $r['review']['by'] ?? 0 );
	$u  = $by ? get_userdata( $by ) : null;
	return array(
		'id'         => $r['id'],
		'entry'      => (int) $r['entry'],
		'ref'        => (string) ( $r['ref'] ?? '' ),
		'old'        => (bool) $r['old'],
		'title'      => '' !== $r['listing_title'] ? $r['listing_title'] : __( '(no listing)', 'directoryweb' ),
		'listingId'  => (int) $r['listing'],
		'listingUrl' => $r['listing'] ? (string) get_edit_post_link( $r['listing'], 'raw' ) : '',
		'viewUrl'    => $r['listing'] ? (string) get_permalink( $r['listing'] ) : '',
		'oldUrl'     => $r['old'] ? (string) get_edit_post_link( $r['entry'], 'raw' ) : '',
		'name'       => (string) $r['name'],
		'role'       => (string) $r['role'],
		'email'      => (string) $r['email'],
		'phone'      => (string) $r['phone'],
		'changes'    => $changes,
		'status'     => $r['status'],
		'statusText' => directoryweb_status_text( $r['status'] ),
		'sent'       => (string) $r['sent'],
		'sentText'   => mysql2date( 'j M Y', (string) $r['sent'] ),
		'decidedBy'  => $u ? (string) $u->display_name : '',
		'decidedAt'  => ! empty( $r['review']['at'] ) ? mysql2date( 'j M Y', (string) $r['review']['at'] ) : '',
	);
}

/**
 * Label of a claim field.
 *
 * @param string $key AW_CLAIM_L key, logo or photos.
 */
function directoryweb_field_label( string $key ): string {
	if ( 'logo' === $key ) {
		return __( 'Logo', 'directoryweb' );
	}
	if ( 'photos' === $key ) {
		return __( 'Photos (added before the current ones, 6 at most)', 'directoryweb' );
	}
	return defined( 'AW_CLAIM_L' ) && isset( AW_CLAIM_L[ $key ] ) ? (string) AW_CLAIM_L[ $key ][0] : $key;
}

/**
 * The newest change-log row a claim's applied field wrote (SourceWeb), or null.
 *
 * @param array  $c   aw_claim_entry().
 * @param string $key Claim field.
 * @return array|null [ 'id', 'status' ].
 */
function directoryweb_claim_log_row( array $c, string $key ): ?array {
	if ( ! function_exists( 'aw_enrich_table' ) || ! get_option( 'aw_enrich_db' ) || ! defined( 'AW_CLAIM_L' ) ) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one row of SourceWeb's change log
		$wpdb->prepare(
			'SELECT id, status FROM ' . aw_enrich_table() . ' WHERE listing_id = %d AND run = %s AND field = %s ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SourceWeb's table name
			(int) $c['listing'],
			'claim-' . (int) $c['id'],
			directoryweb_enrich_field( $key, AW_CLAIM_L )
		),
		ARRAY_A
	);
	return $row ? array(
		'id'     => (int) $row['id'],
		'status' => (string) $row['status'],
	) : null;
}

/**
 * One claim field by field.
 *
 * @param int $id Fluent Forms entry ID.
 * @return array|null
 */
function directoryweb_claim_detail( int $id ): ?array {
	if ( ! function_exists( 'aw_claim_entry' ) || ! aw_claim_ff_id() ) {
		return null;
	}
	$c = aw_claim_entry( $id );
	if ( ! $c ) {
		return null;
	}
	$status = directoryweb_claim_status( $c['review'] );
	$lid    = (int) $c['listing'];
	$file   = fn( $f ) => add_query_arg(
		array(
			'action' => 'aw_ff_file',
			'f'      => $f,
		),
		admin_url( 'admin-post.php' )
	);
	$fields = array();
	$undone = array();
	$notes  = array();
	if ( 'approved' === $status ) {
		foreach ( (array) ( $c['review']['fields'] ?? array() ) as $key ) {
			$key = (string) $key;
			$row = directoryweb_claim_log_row( $c, $key );
			if ( 'undone' === ( $row['status'] ?? '' ) ) {
				$undone[] = directoryweb_field_label( $key );
				continue;
			}
			$fields[] = array(
				'key'      => $key,
				'label'    => directoryweb_field_label( $key ),
				'current'  => in_array( $key, array( 'logo', 'photos' ), true ) ? '' : aw_claim_now( $lid, $key, false ),
				'proposed' => in_array( $key, array( 'logo', 'photos' ), true ) ? __( 'Uploaded with the claim', 'directoryweb' ) : (string) ( $c['propose'][ $key ] ?? '' ),
				'applied'  => true,
			);
		}
	} elseif ( $lid ) {
		foreach ( aw_claim_changes( $c ) as $key => $ch ) {
			if ( 'l_hours' === $key ) {
				/* translators: %s: the proposed opening hours. */
				$notes[] = sprintf( __( 'Opening hours proposed: %s. Enter them by hand in the listing’s Opening hours.', 'directoryweb' ), '' !== $ch[2] ? $ch[2] : __( '(none)', 'directoryweb' ) );
				continue;
			}
			if ( 'phones' === $key && directoryweb_phones_bad( (string) $ch[2], 'aw_claim_phone_ok' ) ) {
				$notes[] = __( 'A line of the phone numbers is not a phone number: approving on the Claims screen keeps the list as it is.', 'directoryweb' );
			}
			$fields[] = array(
				'key'      => (string) $key,
				'label'    => (string) $ch[0],
				'current'  => (string) $ch[1],
				'proposed' => '' !== (string) $ch[2] ? (string) $ch[2] : __( '(remove)', 'directoryweb' ),
				'applied'  => false,
			);
		}
		if ( '' !== $c['logo'] ) {
			$fields[] = array(
				'key'      => 'logo',
				'label'    => directoryweb_field_label( 'logo' ),
				'current'  => has_post_thumbnail( $lid ) ? __( 'Has a logo', 'directoryweb' ) : '',
				'proposed' => __( 'New logo uploaded', 'directoryweb' ),
				'applied'  => false,
			);
		}
		if ( $c['photos'] ) {
			$have     = count( array_filter( explode( ',', (string) get_post_meta( $lid, 'aw_gallery', true ) ) ) );
			$fields[] = array(
				'key'      => 'photos',
				'label'    => directoryweb_field_label( 'photos' ),
				/* translators: %s: number of photos. */
				'current'  => sprintf( __( '%s now', 'directoryweb' ), number_format_i18n( $have ) ),
				/* translators: %s: number of photos. */
				'proposed' => sprintf( __( '%s uploaded', 'directoryweb' ), number_format_i18n( count( $c['photos'] ) ) ),
				'applied'  => false,
			);
		}
	}
	$by = (int) ( $c['review']['by'] ?? 0 );
	$u  = $by ? get_userdata( $by ) : null;
	return array(
		'id'         => (int) $c['id'],
		'ref'        => (string) $c['ref'],
		'status'     => $status,
		'statusText' => directoryweb_status_text( $status ),
		'pending'    => 'pending' === $status && $lid > 0,
		'title'      => $lid ? html_entity_decode( get_the_title( $lid ), ENT_QUOTES, 'UTF-8' ) : __( '(no listing)', 'directoryweb' ),
		'listingUrl' => $lid ? (string) get_edit_post_link( $lid, 'raw' ) : '',
		'viewUrl'    => $lid ? (string) get_permalink( $lid ) : '',
		'oldScreen'  => add_query_arg(
			array(
				'post_type' => 'aw_listing',
				'page'      => 'aw-claims',
				'entry'     => (int) $c['id'],
			),
			admin_url( 'edit.php' )
		),
		'who'        => array(
			array( __( 'From', 'directoryweb' ), $c['name'] . ( '' !== $c['role'] ? ', ' . $c['role'] : '' ) ),
			array( __( 'Email', 'directoryweb' ), (string) $c['email'] ),
			array( __( 'Phone', 'directoryweb' ), (string) $c['phone'] ),
			array( __( 'Show name on listing', 'directoryweb' ), $c['show'] ? __( 'Yes', 'directoryweb' ) : __( 'No', 'directoryweb' ) ),
			array( __( 'Anything else', 'directoryweb' ), (string) $c['notes'] ),
			array( __( 'Sent', 'directoryweb' ), mysql2date( 'j M Y, H:i', (string) $c['sent'] ) ),
			array( __( 'Decided', 'directoryweb' ), $u ? $u->display_name . ', ' . mysql2date( 'j M Y', (string) ( $c['review']['at'] ?? '' ) ) : '' ),
		),
		'proofUrl'   => '' !== $c['proof'] ? esc_url_raw( $c['proof'] ) : '',
		'logoUrl'    => '' !== $c['logo'] ? $file( $c['logo'] ) : '',
		'logoNowUrl' => '' !== $c['logo'] && $lid && has_post_thumbnail( $lid ) ? (string) get_the_post_thumbnail_url( $lid, 'thumbnail' ) : '',
		'photoUrls'  => array_map( $file, (array) $c['photos'] ),
		'fields'     => $fields,
		'undone'     => $undone,
		'notes'      => $notes,
	);
}

/**
 * GET claims/<id>.
 *
 * @param WP_REST_Request $request Request.
 */
function directoryweb_rest_claim( WP_REST_Request $request ) {
	$d = directoryweb_claim_detail( (int) $request['id'] );
	if ( ! $d ) {
		return new WP_Error( 'rest_not_found', __( 'No such claim.', 'directoryweb' ), array( 'status' => 404 ) );
	}
	return rest_ensure_response( $d );
}

/* ---------- Settings ---------- */

/** Fluent Forms forms: id => title. */
function directoryweb_ff_forms(): array {
	if ( ! defined( 'FLUENTFORM' ) ) {
		return array();
	}
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}fluentform_forms ORDER BY id ASC", ARRAY_A ) as $f ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the settings screen's list of forms
		$out[ (int) $f['id'] ] = (string) $f['title'];
	}
	return $out;
}

/**
 * One read-only value.
 *
 * @param string $label Name.
 * @param string $value Value in words.
 * @param string $where Where it is set ('' = in code).
 */
function directoryweb_fact( string $label, string $value, string $where = '' ): array {
	return array(
		'label' => $label,
		'value' => $value,
		'where' => '' !== $where ? $where : __( 'Set in code', 'directoryweb' ),
	);
}

/** The connected services, then the groups, with today's values. */
function directoryweb_settings_payload(): array {
	$num   = fn( $n ) => number_format_i18n( (int) $n );
	$lgd   = function_exists( 'aw_lgd' ) ? aw_lgd() : array();
	$head  = directoryweb_lgd_head();
	$stats = get_transient( DIRECTORYWEB_STATS );
	$forms = directoryweb_ff_forms();
	$claim = function_exists( 'aw_claim_ff_id' ) ? aw_claim_ff_id() : 0;
	$geo   = (float) get_option( 'aw_geo_last_request', 0 );
	$crm   = function_exists( 'FluentCrmApi' );
	$tags  = array();
	if ( $crm ) {
		foreach ( array( 'org-pending', 'org-approved' ) as $slug ) {
			$found  = FluentCrmApi( 'tags' )->getInstance()->where( 'slug', $slug )->first();
			$tags[] = array( $slug, ! empty( $found ) );
		}
	}
	$sw   = defined( 'AW_SOURCING_DIR' );
	$rw   = function_exists( 'rankweb_ui_url' );
	$iw   = defined( 'INSIGHTWEB_LOADED' );
	$rule = $rw ? rankweb_ui_url( 'settings' ) . '#indexing' : '';

	$services = array(
		array(
			'slug'    => 'places',
			'name'    => __( 'Place source', 'directoryweb' ),
			/* translators: %s: file version. */
			'status'  => sprintf( __( 'LGD, %s', 'directoryweb' ), $head['version'] ),
			'tone'    => '',
			/* translators: 1: states, 2: districts, 3: source. */
			'summary' => sprintf( __( '%1$s states / %2$s districts, from %3$s. A data file in the plugin; another country ships its own file.', 'directoryweb' ), $num( count( (array) ( $lgd['states'] ?? array() ) ) ), $num( count( (array) ( $lgd['districts'] ?? array() ) ) ), $head['source'] ),
			'file'    => true,
		),
		array(
			'slug'    => 'pin',
			'name'    => __( 'PIN code lookup', 'directoryweb' ),
			'status'  => __( 'India Post, on', 'directoryweb' ),
			'tone'    => '',
			'summary' => __( 'Answers are kept for a week.', 'directoryweb' ),
			'facts'   => array(
				directoryweb_fact( __( 'Service', 'directoryweb' ), 'api.postalpincode.in' ),
				directoryweb_fact( __( 'PIN pattern', 'directoryweb' ), '^[1-9]\d{5}$' ),
			),
		),
		array(
			'slug'    => 'geocoder',
			'name'    => __( 'Geocoder', 'directoryweb' ),
			'status'  => __( 'Nominatim, on', 'directoryweb' ),
			'tone'    => '',
			/* translators: %s: date and time. */
			'summary' => $geo > 0 ? sprintf( __( 'Last request %s.', 'directoryweb' ), wp_date( 'j M Y, g:i a', (int) $geo ) ) : __( 'No request yet.', 'directoryweb' ),
			'facts'   => array(
				directoryweb_fact( __( 'Country', 'directoryweb' ), 'in' ),
				/* translators: %s: number of days. */
				directoryweb_fact( __( 'Answers kept', 'directoryweb' ), defined( 'AW_GEO_CACHE' ) ? sprintf( __( '%s days', 'directoryweb' ), $num( AW_GEO_CACHE / DAY_IN_SECONDS ) ) : '' ),
				directoryweb_fact( __( 'Contact sent with each request', 'directoryweb' ), defined( 'AW_GEO_UA' ) ? AW_GEO_UA : '' ),
			),
		),
		array(
			'slug'    => 'map',
			'name'    => __( 'Map tiles', 'directoryweb' ),
			'status'  => __( 'OpenFreeMap', 'directoryweb' ),
			'tone'    => '',
			'summary' => __( 'The tile style and the map library are fixed in code.', 'directoryweb' ),
			'facts'   => array(
				directoryweb_fact( __( 'Tile style', 'directoryweb' ), defined( 'AW_DIR_STYLE' ) ? AW_DIR_STYLE : '' ),
				directoryweb_fact( __( 'Map library', 'directoryweb' ), defined( 'AW_DIR_MAP_LIB' ) ? AW_DIR_MAP_LIB : '' ),
			),
		),
		array(
			'slug'      => 'fluentforms',
			'name'      => __( 'Fluent Forms', 'directoryweb' ),
			/* translators: %d: form ID. */
			'status'    => ! defined( 'FLUENTFORM' ) ? __( 'Not active', 'directoryweb' ) : ( $claim && isset( $forms[ $claim ] ) ? sprintf( __( 'Claim form #%d found', 'directoryweb' ), $claim ) : __( 'Claim form missing', 'directoryweb' ) ),
			'tone'      => defined( 'FLUENTFORM' ) && $claim && isset( $forms[ $claim ] ) ? '' : 'error',
			'summary'   => $claim && isset( $forms[ $claim ] ) ? $forms[ $claim ] : __( 'Claims cannot be sent until a claim form is chosen.', 'directoryweb' ),
			'claimForm' => array(
				'value'   => $claim,
				'options' => array_map(
					fn( $id, $title ) => array(
						'value' => (int) $id,
						/* translators: 1: form ID, 2: form title. */
						'label' => sprintf( __( '#%1$d %2$s', 'directoryweb' ), $id, $title ),
					),
					array_keys( $forms ),
					array_values( $forms )
				),
			),
		),
		array(
			'slug'    => 'fluentcrm',
			'name'    => __( 'FluentCRM', 'directoryweb' ),
			'status'  => $crm ? __( 'Active', 'directoryweb' ) : __( 'Not active', 'directoryweb' ),
			'tone'    => $crm ? '' : 'warn',
			'summary' => $crm ? __( 'An approved claimant is tagged and linked to the listing’s people.', 'directoryweb' ) : __( 'Approved claimants are not linked to the listing’s people until FluentCRM is active.', 'directoryweb' ),
			'facts'   => array_map(
				/* translators: %s: tag slug. */
				fn( $t ) => directoryweb_fact( sprintf( __( 'Tag %s', 'directoryweb' ), $t[0] ), $t[1] ? __( 'Found', 'directoryweb' ) : __( 'Not found', 'directoryweb' ) ),
				$tags
			),
		),
		array(
			'slug'    => 'sourceweb',
			'name'    => 'SourceWeb',
			'status'  => $sw ? __( 'Active', 'directoryweb' ) : __( 'Not active', 'directoryweb' ),
			'tone'    => $sw ? '' : 'warn',
			/* translators: %s: number of proposals. */
			'summary' => $sw ? ( is_array( $stats ) && is_array( $stats['enrichment'] ?? null ) ? sprintf( __( '%s proposals waiting. Enrichment sources and keys stay in SourceWeb.', 'directoryweb' ), $num( $stats['enrichment']['pending'] ) ) : __( 'Enrichment sources and keys stay in SourceWeb.', 'directoryweb' ) ) : __( 'Undo of a claimed field needs SourceWeb’s change log.', 'directoryweb' ),
			'link'    => $sw ? array(
				'label' => __( 'SourceWeb settings', 'directoryweb' ),
				'url'   => add_query_arg( 'page', defined( 'AW_SRC_PAGE' ) ? AW_SRC_PAGE : 'aw-sourcing', admin_url( 'admin.php' ) ),
			) : null,
		),
		array(
			'slug'    => 'rankweb',
			'name'    => 'RankWeb',
			'status'  => $rw ? __( 'Active', 'directoryweb' ) : __( 'Not active', 'directoryweb' ),
			'tone'    => $rw ? '' : 'warn',
			'summary' => __( 'A published listing with no way to reach it is kept out of search.', 'directoryweb' ),
			'link'    => $rw ? array(
				'label' => __( 'RankWeb settings', 'directoryweb' ),
				'url'   => $rule,
			) : null,
		),
		array(
			'slug'    => 'insightweb',
			'name'    => 'InsightWeb',
			'status'  => $iw ? __( 'Active', 'directoryweb' ) : __( 'Not active', 'directoryweb' ),
			'tone'    => '',
			'summary' => __( 'Search Console clicks for the Directory pages.', 'directoryweb' ),
			'link'    => $iw && function_exists( 'aw_si_url' ) ? array(
				'label' => __( 'Search Insights', 'directoryweb' ),
				'url'   => aw_si_url(),
			) : null,
		),
	);

	$types = array();
	foreach ( function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array() as $slug ) {
		$types[] = function_exists( 'aw_listing_type_name' ) && '' !== aw_listing_type_name( $slug ) ? aw_listing_type_name( $slug ) : $slug;
	}
	$roles  = defined( 'AW_CLAIM_ROLES' ) ? array_values( array_filter( AW_CLAIM_ROLES, fn( $k ) => '' !== $k, ARRAY_FILTER_USE_KEY ) ) : array();
	$where  = fn( string $cmd ) => sprintf(
		/* translators: %s: a WP-CLI command. */
		__( 'Set with %s', 'directoryweb' ),
		$cmd
	);
	$groups = array(
		array(
			'slug'        => 'types',
			'label'       => __( 'Listing types', 'directoryweb' ),
			'description' => __( 'The Directory’s tags that are not services. They come from the sourcing lists (SourceWeb → Sources).', 'directoryweb' ),
			'facts'       => array(
				directoryweb_fact( __( 'Types', 'directoryweb' ), implode( ', ', $types ), __( 'Set in the sourcing lists', 'directoryweb' ) ),
				directoryweb_fact( __( 'Veterinary as one filter', 'directoryweb' ), defined( 'AW_VET_TYPES' ) ? implode( ', ', AW_VET_TYPES ) : '' ),
			),
		),
		array(
			'slug'        => 'places',
			'label'       => __( 'Places', 'directoryweb' ),
			'description' => '',
			'facts'       => array(
				directoryweb_fact( __( 'Content types that carry a place', 'directoryweb' ), defined( 'AW_PLACE_TYPES' ) ? implode( ', ', AW_PLACE_TYPES ) : '' ),
				/* translators: %s: number. */
				directoryweb_fact( __( 'District common names', 'directoryweb' ), defined( 'AW_DISTRICT_COMMON_NAMES' ) ? sprintf( __( '%s names', 'directoryweb' ), $num( count( AW_DISTRICT_COMMON_NAMES ) ) ) : '' ),
				/* translators: %s: number. */
				directoryweb_fact( __( 'Cities found in text', 'directoryweb' ), defined( 'AW_PLACE_DETECT_CITIES' ) ? sprintf( __( '%s cities', 'directoryweb' ), $num( count( AW_PLACE_DETECT_CITIES ) ) ) : '' ),
			),
		),
		array(
			'slug'        => 'claims',
			'label'       => __( 'Claim rules', 'directoryweb' ),
			'description' => __( 'Undo puts one field back and never takes the Claimed mark off.', 'directoryweb' ),
			'facts'       => array(
				directoryweb_fact( __( 'Roles', 'directoryweb' ), implode( ', ', $roles ) ),
				/* translators: %s: number of days. */
				directoryweb_fact( __( 'Re-check a Claimed listing after', 'directoryweb' ), defined( 'AW_REVERIFY_DAYS' ) ? sprintf( __( '%s days', 'directoryweb' ), $num( AW_REVERIFY_DAYS ) ) : '' ),
				directoryweb_fact( __( '"Show my name" starts as', 'directoryweb' ), __( 'No', 'directoryweb' ), __( 'Set in the claim form', 'directoryweb' ) ),
				directoryweb_fact( __( 'Emails from forms and claims', 'directoryweb' ), '1' === (string) get_option( 'aw_ff_send_email', '0' ) ? __( 'On', 'directoryweb' ) : __( 'Off', 'directoryweb' ), $where( 'wp option update aw_ff_send_email' ) ),
			),
		),
		array(
			'slug'        => 'badges',
			'label'       => __( 'Badges', 'directoryweb' ),
			'description' => __( 'Claimed is set by an approved claim. Verified comes from government records.', 'directoryweb' ),
			'facts'       => array(
				directoryweb_fact( __( 'Claimed label', 'directoryweb' ), __( 'Claimed', 'directoryweb' ) ),
				directoryweb_fact( __( 'Verified sources', 'directoryweb' ), defined( 'AW_GOV_SOURCES' ) ? implode( ', ', AW_GOV_SOURCES ) : '' ),
				directoryweb_fact( __( 'Verified', 'directoryweb' ), get_option( 'aw_gov_verified_live' ) ? __( 'On', 'directoryweb' ) : __( 'Off', 'directoryweb' ), $where( 'wp aw gov-verify' ) ),
				/* translators: %s: number. */
				directoryweb_fact( __( 'Phone numbers never published', 'directoryweb' ), defined( 'AW_NEVER_PUBLISH_PHONES' ) ? sprintf( __( '%s kept as fingerprints only', 'directoryweb' ), $num( count( AW_NEVER_PUBLISH_PHONES ) ) ) : '' ),
			),
		),
		array(
			'slug'        => 'indexing',
			'label'       => __( 'Indexing', 'directoryweb' ),
			'description' => '',
			'linked'      => array(
				'label'  => __( 'Listings with no contact are kept out of search', 'directoryweb' ),
				'value'  => $rw ? __( 'On', 'directoryweb' ) : __( 'Not applied', 'directoryweb' ),
				'plugin' => 'RankWeb',
				'href'   => $rule,
			),
			'facts'       => array(),
		),
		array(
			'slug'        => 'findhelp',
			'label'       => __( 'Find help', 'directoryweb' ),
			'description' => '',
			'facts'       => array(
				directoryweb_fact( __( 'Places shown at most', 'directoryweb' ), defined( 'AW_FINDHELP_MAX' ) ? $num( AW_FINDHELP_MAX ) : '' ),
				directoryweb_fact( __( 'States found in one text at most', 'directoryweb' ), defined( 'AW_PLACE_DETECT_MAX_STATES' ) ? $num( AW_PLACE_DETECT_MAX_STATES ) : '' ),
			),
		),
		array(
			'slug'        => 'copy',
			'label'       => __( 'Directory copy', 'directoryweb' ),
			'description' => __( 'Titles, the intro and the helpline cards are written in the Directory’s code for now.', 'directoryweb' ),
			'facts'       => array(
				/* translators: %s: number. */
				directoryweb_fact( __( 'Search aliases', 'directoryweb' ), defined( 'AW_SEARCH_NAME_ALIASES' ) ? implode( ', ', array_map( fn( $k, $v ) => $k . ' → ' . $v, array_keys( AW_SEARCH_NAME_ALIASES ), AW_SEARCH_NAME_ALIASES ) ) : '' ),
			),
		),
	);
	return array(
		'services' => $services,
		'groups'   => $groups,
	);
}

/**
 * POST settings/claim-form: the claim form, saved in aw_ff_forms as `wp aw ff-form claim <ID>` does. Nothing is written
 * when the form is the same.
 *
 * @param WP_REST_Request $request Request.
 */
function directoryweb_rest_save_claim_form( WP_REST_Request $request ) {
	$id = (int) $request['form'];
	if ( $id && ! isset( directoryweb_ff_forms()[ $id ] ) ) {
		return new WP_Error( 'rest_invalid_param', __( 'No such form.', 'directoryweb' ), array( 'status' => 400 ) );
	}
	$opt   = defined( 'AW_FF_OPTION' ) ? AW_FF_OPTION : 'aw_ff_forms';
	$ids   = directoryweb_claim_form_change( (array) get_option( $opt, array() ), $id );
	$saved = null !== $ids;
	if ( $saved ) {
		update_option( $opt, $ids, false );
	}
	return rest_ensure_response(
		array(
			'saved'    => $saved,
			'message'  => $saved ? __( 'Saved.', 'directoryweb' ) : __( 'Nothing changed.', 'directoryweb' ),
			'settings' => directoryweb_settings_payload(),
		)
	);
}
