<?php
/**
 * DirectoryWeb's Listings tab (12 Nov 2026): the Directory's listings as a read-only list on the Kits shell, with
 * views (status and badges), search, state and type filters, sort and pages; each row opens a reading panel with an
 * Edit link to the listing's own edit screen. Nothing is changed here: listings are edited on their edit screen, and
 * claims stay on Directory → Claims.
 *
 *   GET directoryweb/v1/listings   edit_posts   the list
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_LISTINGS_PAGE = 'directoryweb-listings';
const DIRECTORYWEB_LISTINGS_CAP  = 'edit_posts';

/** The list's views: value => [ label, post statuses, meta key and value or null ]. */
function directoryweb_listing_views(): array {
	return array(
		'all'      => array( __( 'All', 'directoryweb' ), array( 'publish', 'draft', 'pending', 'private' ), null ),
		'publish'  => array( __( 'Published', 'directoryweb' ), array( 'publish' ), null ),
		'draft'    => array( __( 'Not published', 'directoryweb' ), array( 'draft', 'pending', 'private' ), null ),
		'claimed'  => array( __( 'Claimed', 'directoryweb' ), array( 'publish' ), array( 'aw_verified', '1' ) ),
		'verified' => array( __( 'Verified', 'directoryweb' ), array( 'publish' ), array( 'aw_gov_verified_on', '' ) ),
		'recheck'  => array( __( 'Needs re-check', 'directoryweb' ), array( 'publish' ), array( 'aw_needs_reverify', '1' ) ),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			DIRECTORYWEB_REST,
			'/listings',
			array(
				'methods'             => 'GET',
				'permission_callback' => directoryweb_rest_can( DIRECTORYWEB_LISTINGS_CAP ),
				'args'                => array(
					'view'   => array(
						'type'    => 'string',
						'enum'    => array_keys( directoryweb_listing_views() ),
						'default' => 'all',
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
					'state'  => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'type'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'sort'   => array(
						'type'    => 'string',
						'enum'    => array( 'modified', 'title' ),
						'default' => 'modified',
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
				'callback'            => 'directoryweb_rest_listings',
			)
		);
	}
);

/**
 * GET listings.
 *
 * @param WP_REST_Request $r Request.
 * @return WP_REST_Response
 */
function directoryweb_rest_listings( WP_REST_Request $r ) {
	$views = directoryweb_listing_views();
	$view  = (string) $r['view'];
	$def   = $views[ $view ] ?? $views['all'];
	$q     = array(
		'post_type'              => 'aw_listing',
		'post_status'            => $def[1],
		'posts_per_page'         => 20,
		'paged'                  => (int) $r['page'],
		'orderby'                => 'title' === $r['sort'] ? 'title' : 'modified',
		'order'                  => 'asc' === $r['dir'] ? 'ASC' : 'DESC',
		'ignore_sticky_posts'    => true,
		'update_post_term_cache' => true,
		'suppress_filters'       => false,
	);
	$meta  = array();
	if ( $def[2] ) {
		$meta[] = '' === $def[2][1]
			? array(
				'key'     => $def[2][0],
				'value'   => '',
				'compare' => '!=',
			)
			: array(
				'key'   => $def[2][0],
				'value' => $def[2][1],
			);
	}
	if ( (int) $r['state'] ) {
		$meta[] = array(
			'key'   => 'aw_state_code',
			'value' => (string) (int) $r['state'],
		);
	}
	if ( $meta ) {
		$q['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a filtered admin list, 20 a page
	}
	$type = sanitize_title( (string) $r['type'] );
	if ( '' !== $type && in_array( $type, aw_listing_type_slugs(), true ) ) {
		$q['tag'] = $type;
	}
	$words = trim( sanitize_text_field( (string) $r['search'] ) );
	if ( '' !== $words ) {
		$q['s'] = $words;
	}
	$found = new WP_Query( $q );

	$rows = array_map( 'directoryweb_listing_row', $found->posts );

	$counts   = wp_count_posts( 'aw_listing' );
	$stats    = get_transient( DIRECTORYWEB_STATS );
	$badges   = is_array( $stats ) ? (array) ( $stats['badges'] ?? array() ) : array();
	$n        = array(
		'publish'  => (int) ( $counts->publish ?? 0 ),
		'draft'    => (int) ( $counts->draft ?? 0 ) + (int) ( $counts->pending ?? 0 ) + (int) ( $counts->private ?? 0 ),
		'claimed'  => $badges['claimed'] ?? null,
		'verified' => $badges['verified'] ?? null,
		'recheck'  => $badges['recheck'] ?? null,
	);
	$n['all'] = $n['publish'] + $n['draft'];
	$out      = array();
	foreach ( $views as $k => $v ) {
		$one = array(
			'value' => $k,
			'label' => $v[0],
		);
		if ( null !== $n[ $k ] ) { // Badge counts come from the Dashboard's last count; none before the first.
			$one['count'] = (int) $n[ $k ];
		}
		$out[] = $one;
	}

	$states = array(
		array(
			'value' => '0',
			'label' => __( 'Every state', 'directoryweb' ),
		),
	);
	foreach ( function_exists( 'aw_place_states' ) ? aw_place_states() : array() as $code => $s ) {
		$states[] = array(
			'value' => (string) $code,
			'label' => (string) $s->name,
		);
	}
	$types = array(
		array(
			'value' => '',
			'label' => __( 'Every type', 'directoryweb' ),
		),
	);
	foreach ( aw_listing_type_slugs() as $slug ) {
		$name    = function_exists( 'aw_listing_type_name' ) ? aw_listing_type_name( $slug ) : '';
		$types[] = array(
			'value' => $slug,
			'label' => '' !== $name ? $name : $slug,
		);
	}

	return rest_ensure_response(
		array(
			'rows'    => $rows,
			'total'   => (int) $found->found_posts,
			'page'    => (int) $r['page'],
			'pages'   => max( 1, (int) $found->max_num_pages ),
			'views'   => $out,
			'filters' => array(
				'state' => $states,
				'type'  => $types,
			),
			'newUrl'  => current_user_can( 'edit_posts' ) ? admin_url( 'post-new.php?post_type=aw_listing' ) : '',
		)
	);
}

/**
 * One row of the list, with what its reading panel shows. Only what the listing's public page shows, plus its
 * status and dates: no phone numbers, e-mail addresses or claim details.
 *
 * @param WP_Post $p Listing.
 * @return array
 */
function directoryweb_listing_row( WP_Post $p ): array {
	$id       = (int) $p->ID;
	$state    = function_exists( 'aw_place_state' ) ? aw_place_state( (int) get_post_meta( $id, 'aw_state_code', true ) ) : null;
	$district = function_exists( 'aw_place_district' ) ? aw_place_district( (int) get_post_meta( $id, 'aw_district_code', true ) ) : null;
	$statuses = get_post_statuses();
	$web      = (string) get_post_meta( $id, 'aw_website', true );
	$gov      = (string) get_post_meta( $id, 'aw_gov_verified_on', true );
	$thumb    = has_post_thumbnail( $id ) ? (string) get_the_post_thumbnail_url( $id, 'thumbnail' ) : '';
	return array(
		'id'       => $id,
		'title'    => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'status'   => (string) ( $statuses[ $p->post_status ] ?? $p->post_status ),
		'live'     => 'publish' === $p->post_status,
		'types'    => implode( ', ', wp_list_pluck( function_exists( 'aw_listing_types' ) ? aw_listing_types( $id ) : array(), 'name' ) ),
		'services' => implode( ', ', function_exists( 'aw_listing_service_names' ) ? aw_listing_service_names( $id ) : array() ),
		'place'    => implode( ', ', array_filter( array( $district->name ?? '', $state->name ?? '' ) ) ),
		'area'     => (string) get_post_meta( $id, 'aw_area', true ),
		'website'  => preg_match( '#^https?://#', $web ) ? esc_url_raw( $web ) : '',
		'claimed'  => '1' === (string) get_post_meta( $id, 'aw_verified', true ),
		'verified' => '' !== $gov ? $gov : '',
		'recheck'  => '1' === (string) get_post_meta( $id, 'aw_needs_reverify', true ),
		'modified' => (string) get_the_modified_date( 'j M Y', $p ),
		'created'  => (string) get_the_date( 'j M Y', $p ),
		'thumb'    => $thumb,
		'editUrl'  => current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '',
		'viewUrl'  => 'publish' === $p->post_status ? (string) get_permalink( $id ) : '',
	);
}

/** Listings page callback. */
function directoryweb_listings_page(): void {
	if ( ! current_user_can( DIRECTORYWEB_LISTINGS_CAP ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'directoryweb' ), 403 );
	}
	if ( directoryweb_ui_page( 'listings' ) ) {
		return;
	}
	directoryweb_plain_page( __( 'Directory listings', 'directoryweb' ), 'listings' );
}
