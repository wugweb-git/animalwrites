<?php
/**
 * Directory map and "Near me" (owner, 20 Oct 2026: built natively instead of Web Directory Free).
 *
 * - The list stays the default on every Directory view (all, state, district, district × type, ?tag= filters); a
 *   List / Map switch above it shows the same organisations on a map (MapLibre GL with OpenStreetMap data from
 *   OpenFreeMap: no key, no Google), clustered, one marker per listing with its type icon, a popup with name, type,
 *   district and a link. Points: the listing's own Google Maps location (aw_lat / aw_lng), else the geocoded point
 *   (geocode.php), shown as approximate unless it was a street match. Listings with no point are counted, not shown.
 * - Near me: the visitor's location (asked only when they press the button) or a PIN code (PIN → district through
 *   aw_pin_lookup; the district centre is the starting point) → listings within 5 / 10 / 25 / 50 km, nearest first.
 *   Only street, locality and PIN points are ranked (locality and PIN distances shown as "~N km"); listings placed
 *   only at their district's centre are never ranked: they follow as "In your district (location approximate)". The
 *   nearest district's Directory page is always offered.
 * - REST: GET /aw/v1/dir-points (filtered points for the map), GET /aw/v1/dir-near (nearest listings). Both read one
 *   cached list of every published listing's point (12 hours; cleared when a listing is saved or geocoded).
 * The map's text alternative is the list itself (the switch returns to it; the map says so).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_DIR_MAP_LIB = 'https://unpkg.com/maplibre-gl@4.7.1/dist/';
const AW_DIR_STYLE   = 'https://tiles.openfreemap.org/styles/liberty';

/**
 * Every published listing as a compact row, cached: [id, lat|null, lng|null, approx 0/1, primary type slug, name,
 * district name, path, state codes, district codes, tag slugs, claimed 0/1 (aw_verified), precision (own, address,
 * locality, pin, district), Verified 0/1 (government documents)].
 */
function aw_dir_points_all() {
	$all = get_transient( 'aw_dir_points' );
	if ( is_array( $all ) ) {
		return $all;
	}
	$all = array();
	$ids = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
	update_meta_cache( 'post', $ids );
	update_object_term_cache( $ids, 'aw_listing' );
	$type_slugs = aw_listing_type_slugs();
	foreach ( $ids as $id ) {
		$pt                 = function_exists( 'aw_geo_point' ) ? aw_geo_point( $id ) : null;
		list( $st, $dt )    = aw_item_codes( $id );
		$tags               = wp_list_pluck( (array) get_the_terms( $id, 'post_tag' ), 'slug' );
		$types              = array_values( array_intersect( $tags, $type_slugs ) );
		list( , $district ) = aw_item_place_names( $id );
		$all[]              = array(
			$id,
			$pt ? round( $pt[0], 5 ) : null,
			$pt ? round( $pt[1], 5 ) : null,
			$pt && ! in_array( $pt[2], array( 'own', 'address' ), true ) ? 1 : 0,
			$types ? $types[0] : '',
			html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			$district,
			wp_make_link_relative( get_permalink( $id ) ),
			$st,
			$dt,
			$tags,
			'1' === (string) get_post_meta( $id, 'aw_verified', true ) ? 1 : 0,
			$pt ? (string) $pt[2] : '',
			'' !== (string) get_post_meta( $id, 'aw_gov_verified_on', true ) ? 1 : 0,
		);
	}
	set_transient( 'aw_dir_points', $all, 12 * HOUR_IN_SECONDS );
	return $all;
}

add_action( 'save_post_aw_listing', fn() => delete_transient( 'aw_dir_points' ) );

/**
 * The rows matching a Directory view's filters.
 *
 * @param WP_REST_Request $r place (state or district slug), type (comma list), service (comma list), verified (claimed), gov (Verified), q.
 * @return array
 */
function aw_dir_points_filter( WP_REST_Request $r ) {
	$place    = aw_place_by_slug( sanitize_title( (string) $r->get_param( 'place' ) ) );
	$types    = array_filter( array_map( 'sanitize_title', explode( ',', (string) $r->get_param( 'type' ) ) ) );
	$types    = in_array( 'veterinary', $types, true ) ? array_merge( array_diff( $types, array( 'veterinary' ) ), AW_VET_TYPES ) : $types;
	$services = array_filter( array_map( 'sanitize_title', explode( ',', (string) $r->get_param( 'service' ) ) ) );
	$verified = (bool) $r->get_param( 'verified' );
	$gov      = (bool) $r->get_param( 'gov' );
	$q        = strtolower( trim( sanitize_text_field( (string) $r->get_param( 'q' ) ) ) );
	$out      = array();
	foreach ( aw_dir_points_all() as $row ) {
		if ( $place && ! in_array( (int) $place->code, 'district' === $place->kind ? $row[9] : $row[8], true ) ) {
			continue;
		}
		if ( $types && ! array_intersect( $types, $row[10] ) ) {
			continue;
		}
		if ( $services && array_diff( $services, $row[10] ) ) {
			continue;
		}
		if ( ( $verified && ! $row[11] ) || ( $gov && empty( $row[13] ) ) || ( '' !== $q && false === strpos( strtolower( $row[5] ), $q ) ) ) {
			continue;
		}
		$out[] = $row;
	}
	return $out;
}

/** A row as the browser gets it. */
function aw_dir_point_out( $row, $km = null ) {
	$o = array(
		'id' => $row[0],
		'y'  => $row[1],
		'x'  => $row[2],
		'a'  => $row[3],
		't'  => $row[4],
		'n'  => $row[5],
		'd'  => $row[6],
		'u'  => $row[7],
		'p'  => (string) ( $row[12] ?? '' ),
	);
	if ( null !== $km ) {
		$o['km'] = round( $km, 1 );
	}
	return $o;
}

/** Type names by slug, for popups and the Near me list. */
function aw_dir_type_names() {
	$out = array();
	foreach ( aw_listing_type_slugs() as $slug ) {
		$t            = get_term_by( 'slug', $slug, 'post_tag' );
		$out[ $slug ] = $t ? $t->name : $slug;
	}
	return $out;
}

add_action(
	'rest_api_init',
	function () {
		$args = array(
			'place'    => array( 'type' => 'string' ),
			'type'     => array( 'type' => 'string' ),
			'service'  => array( 'type' => 'string' ),
			'verified' => array( 'type' => 'string' ),
			'gov'      => array( 'type' => 'string' ),
			'q'        => array( 'type' => 'string' ),
		);
		register_rest_route(
			'aw/v1',
			'/dir-points',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // public listings only
				'args'                => $args,
				'callback'            => function ( WP_REST_Request $r ) {
					$rows = aw_dir_points_filter( $r );
					$pts  = array_values( array_filter( $rows, fn( $x ) => null !== $x[1] ) );
					$res  = new WP_REST_Response(
						array(
							'total'  => count( $rows ),
							'approx' => count( array_filter( $pts, fn( $x ) => $x[3] ) ),
							'types'  => aw_dir_type_names(),
							'points' => array_map( 'aw_dir_point_out', $pts ),
						)
					);
					$res->header( 'Cache-Control', 'public, max-age=3600' );
					return $res;
				},
			)
		);
		register_rest_route(
			'aw/v1',
			'/dir-near',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => $args + array(
					'lat' => array( 'type' => 'number' ),
					'lng' => array( 'type' => 'number' ),
					'pin' => array( 'type' => 'string' ),
					'km'  => array( 'type' => 'integer' ),
				),
				'callback'            => 'aw_dir_near',
			)
		);
	}
);

/** GET /aw/v1/dir-near: the nearest listings to a point or a PIN code. */
function aw_dir_near( WP_REST_Request $r ) {
	$km     = in_array( (int) $r->get_param( 'km' ), array( 5, 10, 25, 50 ), true ) ? (int) $r->get_param( 'km' ) : 10;
	$pin    = preg_replace( '/\D/', '', (string) $r->get_param( 'pin' ) );
	$lat    = (float) $r->get_param( 'lat' );
	$lng    = (float) $r->get_param( 'lng' );
	$origin = '';
	if ( '' !== $pin ) {
		if ( ! preg_match( '/^[1-9]\d{5}$/', $pin ) ) {
			return new WP_Error( 'aw_pin', 'Enter a 6-digit PIN code.', array( 'status' => 400 ) );
		}
		$found = function_exists( 'aw_pin_lookup' ) ? aw_pin_lookup( $pin ) : 'unavailable';
		$cent  = is_array( $found ) && ! empty( $found['district'] ) ? aw_district_centroid( (int) $found['district'] ) : null;
		if ( ! $cent ) {
			return new WP_Error( 'aw_pin', 'That PIN code was not found.', array( 'status' => 404 ) );
		}
		list( $lat, $lng ) = $cent;
		$origin            = 'PIN ' . $pin . ' (' . $found['district_name'] . ' district)';
	} elseif ( $lat < 6 || $lat > 37.5 || $lng < 68 || $lng > 97.5 ) {
		return new WP_Error( 'aw_where', 'That location is outside India.', array( 'status' => 400 ) );
	}
	// The nearest district (by centre): the PIN's own district, else the closest centre to the point.
	$best = null;
	if ( '' !== $pin && ! empty( $found['district'] ) ) {
		$best = array( 0, aw_place_district( (int) $found['district'] ) );
	} else {
		foreach ( aw_place_states() as $s ) {
			foreach ( aw_place_districts( $s->code ) as $dist ) {
				$c = aw_district_centroid( $dist->code );
				if ( $c ) {
					$d = aw_geo_km( $lat, $lng, $c[0], $c[1] );
					if ( ! $best || $d < $best[0] ) {
						$best = array( $d, $dist );
					}
				}
			}
		}
	}
	$dcode = $best && $best[1] ? (int) $best[1]->code : 0;
	$near  = array();
	$inds  = array();
	foreach ( aw_dir_points_filter( $r ) as $row ) {
		if ( null === $row[1] ) {
			continue;
		}
		$prec = (string) ( $row[12] ?? ( $row[3] ? 'district' : 'own' ) );
		if ( 'district' === $prec ) {
			if ( $dcode && in_array( $dcode, $row[9], true ) ) {
				$inds[] = $row; // never ranked by distance
			}
			continue;
		}
		$d = aw_geo_km( $lat, $lng, $row[1], $row[2] );
		if ( $d <= $km ) {
			$near[] = array( $d, $row );
		}
	}
	usort( $near, fn( $a, $b ) => $a[0] <=> $b[0] );
	$district = $best && $best[1] ? array( 'name' => $best[1]->name, 'url' => wp_make_link_relative( aw_directory_url( $best[1]->slug ) ), 'count' => function_exists( 'aw_place_count' ) ? (int) aw_place_count( $best[1] ) : 0 ) : null;
	return array(
		'origin'   => $origin,
		'lat'      => round( $lat, 4 ),
		'lng'      => round( $lng, 4 ),
		'km'       => $km,
		'count'    => count( $near ),
		'types'    => aw_dir_type_names(),
		'results'  => array_map( fn( $x ) => aw_dir_point_out( $x[1], $x[0] ), array_slice( $near, 0, 50 ) ),
		'in_district' => array_map( 'aw_dir_point_out', array_slice( $inds, 0, 50 ) ),
		'in_district_count' => count( $inds ),
		'district' => $district,
	);
}

/* ---------- The switch, Near me and the map, above the Directory list ---------- */

/** The current Directory view's filters, for the browser. */
function aw_dir_view_filters() {
	$place = aw_current_place();
	$type  = (string) get_query_var( 'aw_dir_type' );
	if ( '' === $type ) {
		$type = aw_request_type();
	}
	return array_filter(
		array(
			'place'    => $place ? $place->slug : '',
			'type'     => $type,
			'service'  => implode( ',', aw_request_services() ),
			'verified' => empty( $_GET['verified'] ) ? '' : '1', // phpcs:ignore WordPress.Security.NonceVerification
			'gov'      => empty( $_GET['gov'] ) ? '' : '1', // phpcs:ignore WordPress.Security.NonceVerification
			'q'        => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
		)
	);
}

/** The view controls in the Directory toolbar (aw_block_result_head()): List / Map and Near me. */
function aw_dir_tools_html() {
	wp_enqueue_script( 'aw-dir-map' );
	wp_enqueue_style( 'aw-dir-map' );
	return '<div class="aw-dir-tools" data-filters="' . esc_attr( wp_json_encode( (object) aw_dir_view_filters() ) ) . '">'
		. '<div class="kit-segment" role="group" aria-label="View"><button type="button" data-view="list" aria-pressed="true">List</button><button type="button" data-view="map" aria-pressed="false">Map</button></div>'
		. '<button type="button" class="kit-btn kit-btn--sm kit-btn--ghost kit-btn--ico-map-pin aw-near-open" aria-expanded="false" aria-controls="aw-near">Near me</button>'
		. '</div>';
}

/** The panels the toolbar opens, under it: Near me and the map (hidden until asked for). */
function aw_dir_panels_html() {
	$icons = '';
	foreach ( aw_listing_type_slugs() as $slug ) {
		$icons .= '<span class="aw-type-ico aw-type-ico--' . esc_attr( $slug ) . '" data-type="' . esc_attr( $slug ) . '"></span>';
	}
	return '<section id="aw-near" class="aw-near" hidden aria-label="Near me">'
		. '<form class="aw-near-form"><button type="button" class="kit-btn aw-near-locate">Use my location</button>'
		. '<span class="aw-near-or">or</span>'
		. '<label class="screen-reader-text" for="aw-near-pin">PIN code</label><input id="aw-near-pin" class="aw-near-pin" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" placeholder="PIN code" autocomplete="postal-code">'
		. '<label class="screen-reader-text" for="aw-near-km">Distance</label><select id="aw-near-km" class="aw-near-km"><option value="5">Within 5 km</option><option value="10" selected>Within 10 km</option><option value="25">Within 25 km</option><option value="50">Within 50 km</option></select>'
		. '<button type="submit" class="kit-btn kit-btn--brand">Find</button></form>'
		. '<p class="aw-near-note">Your location is used only to sort this list, in your browser and on this request; it is not stored.</p>'
		. '<div class="aw-near-results" role="status" aria-live="polite"></div></section>'
		. '<section class="aw-dir-map" hidden aria-label="Map"><p class="aw-dir-map-note"></p><div class="aw-dir-map-canvas" aria-hidden="true"></div></section>'
		. '<div class="aw-dir-icons" hidden>' . $icons . '</div>';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		$base = plugin_dir_url( __DIR__ ) . 'assets/';
		wp_register_script( 'aw-dir-map', $base . 'js/dir-map.js', array(), AW_CORE_VERSION . '-' . (string) filemtime( dirname( __DIR__ ) . '/assets/js/dir-map.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_style( 'aw-dir-map', $base . 'css/dir-map.css', array(), AW_CORE_VERSION . '-' . (string) filemtime( dirname( __DIR__ ) . '/assets/css/dir-map.css' ) );
		wp_localize_script(
			'aw-dir-map',
			'awDirMap',
			array(
				'points' => rest_url( 'aw/v1/dir-points' ),
				'near'   => rest_url( 'aw/v1/dir-near' ),
				'lib'    => AW_DIR_MAP_LIB,
				'style'  => AW_DIR_STYLE,
			)
		);
	}
);
