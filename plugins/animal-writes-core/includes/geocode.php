<?php
/**
 * Listing coordinates for the Directory map and "Near me" (owner, 20 Oct 2026: OpenStreetMap's free geocoder,
 * Nominatim, one request a second, cached; PIN code or district centre as fallback, marked approximate).
 *
 * For each published listing without coordinates:
 * 1. Its address (aw_address, else the AWBI or import address, with the PIN code and "India") → Nominatim. A street or
 *    building match (place_rank 26+) within 60 km of the listing's district centre is "address"; a coarser match
 *    (locality) in the same range is approximate ("locality").
 * 2. Else the locality named in the address (its last part that is not a PIN, district or state, without "P.O.",
 *    "Tehsil" and the like) with the district and state → Nominatim, approximate (locality).
 * 3. Else its PIN code (the PIN field, or one in the address) → Nominatim postal code search, approximate (pin).
 * 4. Else its district centre (data/district-centroids.json), approximate (district).
 * Found points go to aw_geo_lat / aw_geo_lng with aw_geo_precision. aw_lat / aw_lng stay for the listing's own Google
 * Maps location (enrichment), which wins wherever it exists; geocoded points never show in the listing's schema.
 * Everything but "address" is labelled "approximate" on the map.
 * Every Nominatim answer (hit or miss) is cached for 90 days; requests are at least 1.1 seconds apart and carry the
 * site's name and contact address, as Nominatim's usage policy asks.
 *
 * Run: `wp aw geocode [--limit=<n>] [--redo-approx]` (the first run takes about 1.5 hours for 6,000 listings).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_GEO_UA       = 'AnimalWrites/1.0 (+https://animalwrites.in; contact@animalwrites.in)';
const AW_GEO_NEAR_KM  = 60; // a match farther than this from the listing's district centre is not trusted
const AW_GEO_CACHE    = 90 * DAY_IN_SECONDS;

/**
 * One Nominatim search (cached). Returns the first result [lat, lng, place_rank] or null.
 *
 * @param array $args Search parameters (q, or postalcode + country).
 * @return array|null
 */
function aw_geo_nominatim( $args ) {
	$args = array_merge( $args, array( 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'in', 'email' => 'contact@animalwrites.in' ) );
	$key  = 'aw_geo_' . md5( wp_json_encode( $args ) );
	$hit  = get_transient( $key );
	if ( false !== $hit ) {
		return 'none' === $hit ? null : $hit;
	}
	static $last = 0.0;
	$wait = 1.1 - ( microtime( true ) - $last );
	if ( $wait > 0 ) {
		usleep( (int) ( $wait * 1000000 ) );
	}
	$r    = wp_remote_get( 'https://nominatim.openstreetmap.org/search?' . http_build_query( $args ), array( 'timeout' => 20, 'user-agent' => AW_GEO_UA ) );
	$last = microtime( true );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null; // not cached: tried again on the next run
	}
	$j   = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	$out = is_array( $j ) && ! empty( $j[0]['lat'] ) ? array( (float) $j[0]['lat'], (float) $j[0]['lon'], (int) ( $j[0]['place_rank'] ?? 0 ) ) : null;
	set_transient( $key, $out ? $out : 'none', AW_GEO_CACHE );
	return $out;
}

/** Great-circle distance in km. */
function aw_geo_km( $lat1, $lng1, $lat2, $lng2 ) {
	$r = M_PI / 180;
	$h = sin( ( $lat2 - $lat1 ) * $r / 2 ) ** 2 + cos( $lat1 * $r ) * cos( $lat2 * $r ) * sin( ( $lng2 - $lng1 ) * $r / 2 ) ** 2;
	return 12742 * asin( min( 1, sqrt( $h ) ) );
}

/** A listing's address for geocoding ('' when it has none). */
function aw_geo_address( $id ) {
	foreach ( array( 'aw_address', 'aw_awbi_address', 'aw_import_address' ) as $k ) {
		$a = trim( preg_replace( '/\s+/', ' ', (string) get_post_meta( $id, $k, true ) ) );
		if ( strlen( $a ) >= 8 ) {
			$pin = (string) get_post_meta( $id, 'aw_pincode', true );
			if ( preg_match( '/^\d{6}$/', $pin ) && false === strpos( $a, $pin ) ) {
				$a .= ', ' . $pin;
			}
			return false === stripos( $a, 'india' ) ? $a . ', India' : $a;
		}
	}
	return '';
}

/** The listing's PIN code: the PIN field, else a 6-digit number in its address ('' when none). */
function aw_geo_pin( $id ) {
	$pin = (string) get_post_meta( $id, 'aw_pincode', true );
	if ( preg_match( '/^[1-9]\d{5}$/', $pin ) ) {
		return $pin;
	}
	return preg_match( '/\b([1-9]\d{2})\s?(\d{3})\b/', aw_geo_address( $id ), $m ) ? $m[1] . $m[2] : '';
}

/**
 * The locality named in an address: its last part that is not the PIN, district, state or country.
 *
 * @param string $addr     Address.
 * @param array  $skip     Names to leave out (district, state), lower case.
 * @return string
 */
function aw_geo_locality( $addr, $skip ) {
	$parts = array_reverse( array_map( 'trim', explode( ',', $addr ) ) );
	foreach ( $parts as $part ) {
		$x = preg_replace( '/\b[1-9]\d{2}\s?\d{3}\b/', '', $part );
		$x = preg_replace( '/\b(p\.?\s?o\.?|post|tehsil|teh\.?|tel\.?|tq\.?|taluk|taluka|mandal|block|dist\.?|district|via|near|opp\.?|india)\b\.?/i', '', $x );
		$x = trim( preg_replace( '/\s+/', ' ', $x ), " .-–" );
		if ( strlen( $x ) < 3 || preg_match( '/\d/', $x ) || in_array( strtolower( $x ), $skip, true ) ) {
			continue;
		}
		return $x;
	}
	return '';
}

/**
 * A listing's map point: [lat, lng, precision] (address, locality, pin, district, own) or null.
 *
 * @param int $id Listing ID.
 * @return array|null
 */
function aw_geo_point( $id ) {
	$lat = (string) get_post_meta( $id, 'aw_lat', true );
	$lng = (string) get_post_meta( $id, 'aw_lng', true );
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		return array( (float) $lat, (float) $lng, 'own' ); // the listing's Google Maps location
	}
	$lat = (string) get_post_meta( $id, 'aw_geo_lat', true );
	$lng = (string) get_post_meta( $id, 'aw_geo_lng', true );
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		return array( (float) $lat, (float) $lng, (string) get_post_meta( $id, 'aw_geo_precision', true ) );
	}
	return null;
}

/**
 * Find a listing's coordinates. Returns the precision stored, or '' when nothing was found.
 *
 * @param int  $id          Listing ID.
 * @param bool $redo_approx Look again for listings that only have an approximate point.
 * @return string
 */
function aw_geocode_listing( $id, $redo_approx = false ) {
	if ( is_numeric( get_post_meta( $id, 'aw_lat', true ) ) && is_numeric( get_post_meta( $id, 'aw_lng', true ) ) ) {
		return 'own'; // never changed
	}
	if ( ! $redo_approx && '' !== (string) get_post_meta( $id, 'aw_geo_precision', true ) ) {
		return (string) get_post_meta( $id, 'aw_geo_precision', true );
	}
	$codes = function_exists( 'aw_item_codes' ) ? aw_item_codes( $id ) : array( array(), array() );
	$dist  = (int) ( $codes[1][0] ?? 0 );
	$cent  = $dist && function_exists( 'aw_district_centroid' ) ? aw_district_centroid( $dist ) : null;
	$near  = fn( $p ) => ! $cent || aw_geo_km( $p[0], $p[1], $cent[0], $cent[1] ) <= AW_GEO_NEAR_KM;
	$save  = function ( $lat, $lng, $prec ) use ( $id ) {
		update_post_meta( $id, 'aw_geo_lat', number_format( $lat, 6, '.', '' ) );
		update_post_meta( $id, 'aw_geo_lng', number_format( $lng, 6, '.', '' ) );
		update_post_meta( $id, 'aw_geo_precision', $prec );
		update_post_meta( $id, 'aw_geo_at', current_time( 'mysql' ) );
		return $prec;
	};
	$addr = aw_geo_address( $id );
	if ( '' !== $addr ) {
		$p = aw_geo_nominatim( array( 'q' => $addr ) );
		if ( $p && $near( $p ) ) {
			return $save( $p[0], $p[1], $p[2] >= 26 ? 'address' : 'locality' );
		}
	}
	list( $sname, $dname ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$dname                 = trim( preg_replace( '/\s*\(.*?\)/', '', $dname ) );
	$loc                   = '' !== $addr ? aw_geo_locality( $addr, array_filter( array( strtolower( $dname ), strtolower( $sname ) ) ) ) : '';
	if ( '' !== $loc && '' !== $sname ) {
		$p = aw_geo_nominatim( array( 'q' => implode( ', ', array_filter( array( $loc, $dname, $sname, 'India' ) ) ) ) );
		if ( $p && $near( $p ) ) {
			return $save( $p[0], $p[1], 'locality' );
		}
	}
	$pin = aw_geo_pin( $id );
	if ( '' !== $pin ) {
		$p = aw_geo_nominatim( array( 'postalcode' => $pin, 'country' => 'India' ) );
		if ( $p && $near( $p ) ) {
			return $save( $p[0], $p[1], 'pin' );
		}
	}
	if ( $cent ) {
		return $save( (float) $cent[0], (float) $cent[1], 'district' );
	}
	return '';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Find coordinates for published listings that have none.
	 *
	 * [--limit=<n>]
	 * : At most this many listings.
	 *
	 * [--redo-approx]
	 * : Also look again for listings with only an approximate point.
	 */
	WP_CLI::add_command(
		'aw geocode',
		function ( $args, $assoc ) {
			$ids   = get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
			$limit = (int) ( $assoc['limit'] ?? 0 );
			$redo  = ! empty( $assoc['redo-approx'] );
			$n     = array();
			$done  = 0;
			foreach ( $ids as $id ) {
				if ( $limit && $done >= $limit ) {
					break;
				}
				$had = (string) get_post_meta( $id, 'aw_geo_precision', true );
				if ( ! $redo && ( '' !== $had || is_numeric( get_post_meta( $id, 'aw_lat', true ) ) ) ) {
					$n['already'] = ( $n['already'] ?? 0 ) + 1;
					continue;
				}
				$r       = aw_geocode_listing( $id, $redo );
				$r       = '' !== $r ? $r : 'none';
				$n[ $r ] = ( $n[ $r ] ?? 0 ) + 1;
				++$done;
				if ( 0 === $done % 100 ) {
					WP_CLI::log( gmdate( 'H:i:s' ) . ' ' . $done . ' ' . wp_json_encode( $n ) );
				}
			}
			delete_transient( 'aw_dir_points' );
			WP_CLI::success( wp_json_encode( $n ) );
		}
	);
}
