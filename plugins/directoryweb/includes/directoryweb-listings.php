<?php
/**
 * DirectoryWeb's Listings tab (12 Nov 2026): the Directory's listings as a read-only list on the Kits shell, with
 * views (status and badges), search, state and type filters, sort and pages; opening a listing shows its full
 * profile (read-only), with an Edit link to the listing's own edit screen. Nothing is changed here except the bulk
 * status/re-check actions: listings are edited on their edit screen, and claims stay on Directory → Claims.
 *
 *   GET  directoryweb/v1/listings        edit_posts   the list
 *   GET  directoryweb/v1/listings/<id>   edit_posts   one listing's full public profile (read-only; no phone or e-mail)
 *   POST directoryweb/v1/listings/bulk   edit_posts   publish / move to draft / re-check the ticked listings
 *
 * Bulk actions never delete or trash a listing (they move it to draft instead); each listing is changed only
 * if the current user can edit it (and, to publish, can publish). Claims stay read-only on their own screen.
 *
 * @package directoryweb
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
		register_rest_route(
			DIRECTORYWEB_REST,
			'/listings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => directoryweb_rest_can( DIRECTORYWEB_LISTINGS_CAP ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'callback'            => 'directoryweb_rest_listing_detail',
			)
		);
		register_rest_route(
			DIRECTORYWEB_REST,
			'/listings/bulk',
			array(
				'methods'             => 'POST',
				'permission_callback' => directoryweb_rest_can( DIRECTORYWEB_LISTINGS_CAP ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'enum'     => array_keys( directoryweb_listing_bulk_actions() ),
						'required' => true,
					),
					'ids'    => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
				'callback'            => 'directoryweb_rest_listings_bulk',
			)
		);
	}
);

/** The bulk actions the Listings screen offers: value => label. None deletes or trashes a listing. */
function directoryweb_listing_bulk_actions(): array {
	return array(
		'publish'       => __( 'Publish', 'directoryweb' ),
		'draft'         => __( 'Move to draft', 'directoryweb' ),
		'recheck'       => __( 'Mark for re-check', 'directoryweb' ),
		'clear-recheck' => __( 'Clear re-check', 'directoryweb' ),
	);
}

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
	if ( '' !== $type && in_array( $type, directoryweb_listing_type_slugs(), true ) ) {
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
	foreach ( directoryweb_listing_type_slugs() as $slug ) {
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
 * POST listings/bulk: apply one action to the ticked listings. Each listing is changed only if the current user can
 * edit it (publish also needs publish_posts); listings already in the target state, trashed, or not a directory
 * listing are skipped. Never deletes or trashes.
 *
 * @param WP_REST_Request $r Request.
 * @return WP_REST_Response|WP_Error
 */
function directoryweb_rest_listings_bulk( WP_REST_Request $r ) {
	$action  = (string) $r['action'];
	$ids     = array_values( array_unique( array_filter( array_map( 'absint', (array) $r['ids'] ) ) ) );
	$changed = 0;
	$skipped = 0;
	if ( ( 'publish' === $action ) && ! current_user_can( 'publish_posts' ) ) {
		return new WP_Error( 'directoryweb_cannot_publish', __( 'You are not allowed to publish listings.', 'directoryweb' ), array( 'status' => 403 ) );
	}
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || 'aw_listing' !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $id ) ) {
			++$skipped;
			continue;
		}
		$ok = false;
		if ( 'publish' === $action || 'draft' === $action ) {
			if ( $post->post_status === $action ) {
				++$skipped;
				continue;
			}
			$done = wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $action,
				),
				true
			);
			$ok = ! is_wp_error( $done );
		} elseif ( 'recheck' === $action ) {
			$ok = '1' === (string) get_post_meta( $id, 'aw_needs_reverify', true ) ? null : (bool) update_post_meta( $id, 'aw_needs_reverify', '1' );
		} elseif ( 'clear-recheck' === $action ) {
			$ok = '' === (string) get_post_meta( $id, 'aw_needs_reverify', true ) ? null : (bool) delete_post_meta( $id, 'aw_needs_reverify' );
		}
		if ( true === $ok ) {
			++$changed;
		} else {
			++$skipped;
		}
	}
	if ( $changed > 0 ) {
		delete_transient( DIRECTORYWEB_STATS ); // the Dashboard's counts (and badge totals) recount on the next view
	}
	$labels  = directoryweb_listing_bulk_actions();
	$message = sprintf(
		/* translators: 1: action label, 2: number changed, 3: number skipped. */
		__( '%1$s — %2$d changed, %3$d skipped.', 'directoryweb' ),
		$labels[ $action ] ?? $action,
		$changed,
		$skipped
	);
	return rest_ensure_response(
		array(
			'changed' => $changed,
			'skipped' => $skipped,
			'message' => $message,
		)
	);
}

/**
 * GET listings/<id>: one listing's full public profile for the read-only profile view (modelled on a CRM contact
 * profile). The same public fields the listing's own page shows, grouped, plus status, dates and a completeness
 * score — never a phone number, e-mail address or claim detail. Read only; nothing is written.
 *
 * @param WP_REST_Request $r Request.
 * @return WP_REST_Response|WP_Error
 */
function directoryweb_rest_listing_detail( WP_REST_Request $r ) {
	$id = absint( $r['id'] );
	$p  = get_post( $id );
	if ( ! $p || 'aw_listing' !== $p->post_type || 'trash' === $p->post_status ) {
		return new WP_Error( 'directoryweb_no_listing', __( 'No such listing.', 'directoryweb' ), array( 'status' => 404 ) );
	}
	$meta = fn( string $k ) => trim( (string) get_post_meta( $id, $k, true ) );
	$link = static function ( string $label, string $url ) {
		$url = esc_url_raw( $url );
		return preg_match( '#^https?://#', (string) $url ) ? array(
			'label' => $label,
			'url'   => $url,
		) : null;
	};
	$socials = array_values(
		array_filter(
			array(
				$link( __( 'Website', 'directoryweb' ), $meta( 'aw_website' ) ),
				$link( __( 'Google Maps', 'directoryweb' ), $meta( 'aw_maps_url' ) ),
				$link( __( 'Google Business Profile', 'directoryweb' ), $meta( 'aw_g_profile' ) ),
				$link( __( 'Instagram', 'directoryweb' ), $meta( 'aw_instagram' ) ),
				$link( __( 'Facebook', 'directoryweb' ), $meta( 'aw_facebook' ) ),
				$link( __( 'X (Twitter)', 'directoryweb' ), $meta( 'aw_x' ) ),
				$link( __( 'YouTube', 'directoryweb' ), $meta( 'aw_youtube' ) ),
				$link( __( 'LinkedIn', 'directoryweb' ), $meta( 'aw_linkedin' ) ),
			)
		)
	);
	$named = fn( array $pairs ) => array_values(
		array_filter(
			array_map(
				fn( $pair ) => '' !== $pair[1] ? array(
					'label' => $pair[0],
					'value' => $pair[1],
				) : null,
				$pairs
			)
		)
	);
	$registration = $named(
		array(
			array( __( 'Registration', 'directoryweb' ), $meta( 'aw_registration' ) ),
			array( __( '12A', 'directoryweb' ), $meta( 'aw_reg_12a' ) ),
			array( __( '80G', 'directoryweb' ), $meta( 'aw_reg_80g' ) ),
			array( __( 'NGO Darpan ID', 'directoryweb' ), $meta( 'aw_darpan_id' ) ),
		)
	);
	$about = $named(
		array(
			array( __( 'Year established', 'directoryweb' ), $meta( 'aw_founded' ) ),
			array( __( 'Head of the organisation', 'directoryweb' ), $meta( 'aw_head' ) ),
			array( __( 'Animals helped', 'directoryweb' ), $meta( 'aw_animals' ) ),
			array( __( 'Languages', 'directoryweb' ), $meta( 'aw_languages' ) ),
		)
	);
	$google = $named(
		array(
			array( __( 'Google rating', 'directoryweb' ), $meta( 'aw_g_rating' ) ),
			array( __( 'Google reviews', 'directoryweb' ), $meta( 'aw_g_reviews' ) ),
			array( __( 'Google category', 'directoryweb' ), $meta( 'aw_g_category' ) ),
		)
	);
	$hours  = array();
	foreach ( function_exists( 'directoryweb_listing_hours' ) ? directoryweb_listing_hours( $id ) : array() as $h ) {
		$hours[] = array(
			'day'  => (string) ( $h['day'] ?? '' ),
			'time' => ! empty( $h['closed'] ) ? __( 'Closed', 'directoryweb' ) : trim( (string) ( $h['open'] ?? '' ) . '–' . (string) ( $h['close'] ?? '' ), '–' ),
		);
	}
	$photos = array();
	foreach ( array_filter( array_map( 'absint', explode( ',', $meta( 'aw_gallery' ) ) ) ) as $att ) {
		$url = wp_get_attachment_image_url( $att, 'medium' );
		if ( $url ) {
			$photos[] = $url;
		}
	}
	$detail                 = directoryweb_listing_row( $p );
	$detail['excerpt']      = html_entity_decode( wp_strip_all_tags( (string) $p->post_excerpt ), ENT_QUOTES, 'UTF-8' );
	$detail['socials']      = $socials;
	$detail['about']        = $about;
	$detail['registration'] = $registration;
	$detail['google']       = $google;
	$detail['hours']        = $hours;
	$detail['photos']       = $photos;
	$detail['completeness'] = function_exists( 'directoryweb_listing_completeness' ) ? directoryweb_listing_completeness( $id ) : null;
	return rest_ensure_response( $detail );
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
		'types'    => implode( ', ', wp_list_pluck( directoryweb_listing_types( $id ), 'name' ) ),
		'services' => implode( ', ', directoryweb_listing_service_names( $id ) ),
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
	directoryweb_missing_page( __( 'Directory listings', 'directoryweb' ) );
}
