<?php
/**
 * Pages built from live data (SEO Phase 4, 15 Oct 2026): the Directory's state, district, district × type and filtered
 * lists (?tag=, ?state=), the 18 category pages and the tag pages.
 *
 * - Search titles and descriptions from the page's own counts and place names (titles.php asks these functions).
 * - One visible line of facts on page 1: under the result count in the Directory, above the lists on a category page. It
 *   says what is listed there, how many of each and where most of them are, so no two pages say the same thing.
 *
 * Every number and name comes from the published listings and items; nothing is written by hand. The owner's intro
 * lines are not changed, and the thin-page and noindex rules (robots.php) are unchanged.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Directory data: listings per state, district and India, by type ---------- */

/** Types named in a place's breakdown, in this order when counts tie ("veterinary" = every veterinary type, as the filter). */
const AW_SEO_BREAKDOWN = array( 'animal-welfare-organisation', 'gaushala', 'veterinary', 'abc-centre', 'wildlife-rescue' );

/**
 * Published listings counted per state code, district code and for all of India, by listing type (its slug), by
 * "veterinary" (any veterinary type, as the Directory's Veterinary filter) and in all ("*"). A listing with two types
 * counts under each. "{key}#ph" counts those with a phone or WhatsApp number, "{key}#gov" those from a government list
 * (or a government veterinary office or 1962 unit). "={key}" counts each listing once, by its most specific type
 * (aw_seo_main_type(): a gaushala, vet or ABC centre is not also counted as an NGO), so a breakdown adds up to the whole
 * (owner, 15 Oct 2026). Rebuilt when the Directory's stored counts are (places.php), else kept for 12 hours.
 *
 * @return array{at:int, s:array, d:array, in:array}
 */
function aw_seo_dir_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	$at   = (int) ( aw_place_counts()['at'] ?? 0 );
	$data = get_transient( 'aw_seo_dir_data' );
	if ( is_array( $data ) && ( $data['at'] ?? -1 ) === $at && 3 === ( $data['v'] ?? 0 ) ) {
		return $data;
	}
	global $wpdb;
	$ids  = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	$skip = $ids ? ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : ''; // preview-only items (integers)
	$rows = $wpdb->get_results( "SELECT p.ID id, MAX(CASE WHEN pm.meta_key = 'aw_state_code' THEN pm.meta_value END) st, MAX(CASE WHEN pm.meta_key = 'aw_district_code' THEN pm.meta_value END) dt, MAX(CASE WHEN pm.meta_key = 'aw_phones' AND CAST(pm.meta_value AS UNSIGNED) > 0 THEN 1 ELSE 0 END) ph, MAX(CASE WHEN pm.meta_key = 'aw_source_name' AND TRIM(pm.meta_value) <> '' AND pm.meta_value NOT LIKE '%google%' AND pm.meta_value <> 'AWBI ABC list' THEN 1 ELSE 0 END) gov FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN ('aw_state_code', 'aw_district_code', 'aw_phones', 'aw_source_name') WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'{$skip} GROUP BY p.ID", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$tax  = $wpdb->get_results( "SELECT r.object_id id, t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id AND tt.taxonomy = 'post_tag' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug IN ('" . implode( "','", array_map( 'esc_sql', aw_listing_type_slugs() ) ) . "') JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = 'aw_listing' AND p.post_status = 'publish'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$types = array();
	foreach ( $tax as $t ) {
		$types[ (int) $t['id'] ][] = $t['slug'];
	}
	$vet  = defined( 'AW_VET_TYPES' ) ? AW_VET_TYPES : array();
	$data = array( 'v' => 3, 'at' => $at, 's' => array(), 'd' => array(), 'in' => array() );
	foreach ( $rows as $r ) {
		$keys = $types[ (int) $r['id'] ] ?? array();
		$main = aw_seo_main_type( $keys );
		if ( array_intersect( $keys, $vet ) ) {
			$keys[] = 'veterinary';
		}
		$keys[] = '*';
		if ( '' !== $main ) {
			$keys[] = '=' . $main;
		}
		$gov    = $r['gov'] || array_intersect( $keys, array( 'veterinary-office', 'mobile-veterinary-unit' ) ); // from a government list
		foreach ( array_unique( $keys ) as $k0 ) {
			foreach ( array_filter( array( $k0, $r['ph'] ? $k0 . '#ph' : '', $gov ? $k0 . '#gov' : '' ) ) as $k ) {
			$data['in'][ $k ] = ( $data['in'][ $k ] ?? 0 ) + 1;
			if ( $r['st'] ) {
				$data['s'][ (int) $r['st'] ][ $k ] = ( $data['s'][ (int) $r['st'] ][ $k ] ?? 0 ) + 1;
			}
			if ( $r['dt'] ) {
				$data['d'][ (int) $r['dt'] ][ $k ] = ( $data['d'][ (int) $r['dt'] ][ $k ] ?? 0 ) + 1;
			}
			}
		}
	}
	set_transient( 'aw_seo_dir_data', $data, 12 * HOUR_IN_SECONDS );
	return $data;
}

/**
 * The one type a listing is counted under in a breakdown: its most specific. Any veterinary type ("veterinary"), then
 * ABC centre, gaushala, wildlife rescue, any other type, and the generic Animal Welfare Organisation ("NGOs") last.
 *
 * @param string[] $slugs The listing's type slugs.
 * @return string Type key, '' without a type.
 */
function aw_seo_main_type( array $slugs ) {
	$vet = defined( 'AW_VET_TYPES' ) ? AW_VET_TYPES : array();
	if ( array_intersect( $slugs, $vet ) ) {
		return 'veterinary';
	}
	foreach ( array( 'abc-centre', 'gaushala', 'wildlife-rescue' ) as $k ) {
		if ( in_array( $k, $slugs, true ) ) {
			return $k;
		}
	}
	$rest = array_values( array_diff( $slugs, array( 'animal-welfare-organisation' ) ) );
	if ( $rest ) {
		sort( $rest );
		return $rest[0];
	}
	return in_array( 'animal-welfare-organisation', $slugs, true ) ? 'animal-welfare-organisation' : '';
}

/**
 * Listings of a type ("*" for all) in a place (null for India).
 *
 * @param object|null $place State or district.
 * @param string      $key   Type slug, "veterinary" or "*".
 * @return int
 */
function aw_seo_dir_count( $place, $key = '*' ) {
	$d = aw_seo_dir_data();
	if ( ! $place ) {
		return (int) ( $d['in'][ $key ] ?? 0 );
	}
	return (int) ( $d[ 'state' === $place->kind ? 's' : 'd' ][ $place->code ][ $key ] ?? 0 );
}

/**
 * The places one level down (a state's districts; India's states) that have listings of a type, most first.
 *
 * @param object|null $place State, or null for India.
 * @param string      $key   Type slug, "veterinary" or "*".
 * @return array List of [place, count].
 */
function aw_seo_dir_children( $place, $key = '*' ) {
	$out = array();
	foreach ( $place ? aw_place_districts( $place->code ) : aw_place_states() as $p ) {
		$n = aw_seo_dir_count( $p, $key );
		if ( $n ) {
			$out[] = array( $p, $n );
		}
	}
	usort( $out, fn( $a, $b ) => $b[1] <=> $a[1] ?: strcmp( $a[0]->name, $b[0]->name ) );
	return $out;
}

/**
 * What is listed in a place, by type, most first: the types in AW_SEO_BREAKDOWN, then any other type with listings.
 * Each listing is counted once, under its most specific type (aw_seo_main_type()), so the parts add up to the whole.
 *
 * @param object $place State or district.
 * @return array List of [type key, count].
 */
function aw_seo_dir_breakdown( $place ) {
	$d    = aw_seo_dir_data();
	$have = (array) ( $d[ 'state' === $place->kind ? 's' : 'd' ][ $place->code ] ?? array() );
	$out  = array();
	foreach ( $have as $k => $n ) {
		if ( 0 === strpos( (string) $k, '=' ) && false === strpos( (string) $k, '#' ) ) {
			$out[] = array( substr( $k, 1 ), (int) $n ); // veterinary types are counted once, as "veterinary"
		}
	}
	$rank = array_flip( AW_SEO_BREAKDOWN );
	usort( $out, fn( $a, $b ) => $b[1] <=> $a[1] ?: ( $rank[ $a[0] ] ?? 99 ) <=> ( $rank[ $b[0] ] ?? 99 ) );
	return $out;
}

/**
 * Words for a type: singular or plural by the count, in full or short (for a description).
 *
 * @param string $key   Type slug or "veterinary".
 * @param int    $n     Count (1 for the singular).
 * @param bool   $short Short words for a description ("NGOs", "vets").
 * @return string
 */
function aw_seo_type_words( $key, $n = 2, $short = false ) {
	$words = array( // singular, plural, short plural
		'animal-welfare-organisation' => array( 'animal welfare organisation', 'animal welfare organisations', 'NGOs' ),
		'gaushala'                    => array( 'Gaushala', 'Gaushalas', 'Gaushalas' ), // always capitalised (owner, 15 Oct 2026)
		'veterinary'                  => array( 'vet', 'vets', 'vets' ), // every veterinary type, always "vets" (owner, 15 Oct 2026)
		'veterinary-hospital'         => array( 'veterinary hospital', 'veterinary hospitals', 'vet hospitals' ),
		'veterinary-dispensary'       => array( 'veterinary dispensary', 'veterinary dispensaries', 'vet dispensaries' ),
		'veterinary-clinic'           => array( 'veterinary clinic', 'veterinary clinics', 'vet clinics' ),
		'veterinary-office'           => array( 'government veterinary office', 'government veterinary offices', 'government vet offices' ),
		'mobile-veterinary-unit'      => array( 'mobile veterinary unit (1962)', 'mobile veterinary units (1962)', '1962 mobile vet units' ),
		'abc-centre'                  => array( 'ABC centre', 'ABC centres', 'ABC centres' ),
		'wildlife-rescue'             => array( 'wildlife rescue group', 'wildlife rescue groups', 'wildlife rescuers' ),
		'bird-hospital'               => array( 'bird hospital', 'bird hospitals', 'bird hospitals' ),
	);
	if ( isset( $words[ $key ] ) ) {
		return 1 === (int) $n ? $words[ $key ][0] : $words[ $key ][ $short ? 2 : 1 ];
	}
	$name = trim( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_listing_type_name( $key ) ) );
	$name = implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) || 0 === stripos( $w, 'gaushala' ) ? ucfirst( $w ) : mb_strtolower( $w ), explode( ' ', $name ) ) );
	return 1 === (int) $n ? $name : aw_seo_type_plural( $key );
}

/** Title case for a type in a title: "Gaushalas", "Vets and Veterinary Hospitals", "ABC Centres", "(1962)" kept. */
function aw_seo_title_case( $text ) {
	$small = array( 'and', 'or', 'of', 'in' );
	$words = explode( ' ', $text );
	foreach ( $words as $i => $w ) {
		if ( 0 === $i || ! in_array( $w, $small, true ) ) {
			$words[ $i ] = preg_match( '/^[a-z]/', $w ) ? ucfirst( $w ) : $w;
		}
	}
	return str_replace( ' and ', ' & ', implode( ' ', $words ) );
}

/** "a, b and c". */
function aw_seo_join( array $parts ) {
	$parts = array_values( array_filter( $parts, 'strlen' ) );
	$last  = array_pop( $parts );
	return $parts ? implode( ', ', $parts ) . ' and ' . $last : (string) $last;
}

/** A number for a sentence: "One" at the start for 1, else 1,234. */
function aw_seo_num( $n, $lead = false ) {
	return ( 1 === (int) $n && $lead ) ? 'One' : number_format_i18n( (int) $n );
}

/**
 * The Directory view the current request shows, or null outside the Directory:
 * kind (india|state|district), place, type key ('' for all types), n (listings in the list) and other (a name search,
 * service or verified filter is on: such lists keep the general rules).
 *
 * @return array|null
 */
function aw_seo_dir_view() {
	static $v = false;
	if ( false !== $v ) {
		return $v;
	}
	$v = null;
	if ( ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() || is_404() ) {
		return $v;
	}
	$place = aw_current_place();
	$type  = (string) get_query_var( 'aw_type_slug' );
	if ( '' === $type ) {
		$type = function_exists( 'aw_request_type' ) ? aw_request_type() : ''; // ?tag= (a listing type)
	}
	$other = false;
	foreach ( array( 'q', 'verified' ) as $k ) {
		$other = $other || ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	$other = $other || ( function_exists( 'aw_request_services' ) && aw_request_services() ); // a service chosen too
	$v = array(
		'kind'  => $place ? $place->kind : 'india',
		'place' => $place,
		'state' => $place ? aw_place_parent_state( $place ) : null,
		'type'  => $type,
		'n'     => (int) aw_hub_total( 'aw_listing' ),
		'other' => $other,
	);
	return $v;
}

/**
 * Whether a district's page may speak of "contact numbers": at least two of its listings, and at least a quarter of them,
 * have a phone or WhatsApp number.
 */
function aw_seo_has_numbers( $place ) {
	$ph = aw_seo_dir_count( $place, '*#ph' );
	return $ph >= 2 && $ph * 4 >= aw_seo_dir_count( $place );
}

/** Listings in the current Directory view with a phone or WhatsApp number (its place and type). */
function aw_seo_dir_view_phones( $v ) {
	return aw_seo_dir_count( $v['place'], ( '' !== $v['type'] ? $v['type'] : '*' ) . '#ph' );
}

/**
 * Words for the current view's type: "government veterinary hospitals" when every veterinary listing in the place comes
 * from a government list, else as aw_seo_type_words().
 */
function aw_seo_view_type_words( $v, $n, $short = false ) {
	$words = aw_seo_type_words( $v['type'], $n, $short );
	$vet   = array( 'veterinary', 'veterinary-hospital', 'veterinary-dispensary', 'veterinary-clinic' );
	if ( in_array( $v['type'], $vet, true ) && aw_seo_dir_count( $v['place'], $v['type'] ) && aw_seo_dir_count( $v['place'], $v['type'] . '#gov' ) === aw_seo_dir_count( $v['place'], $v['type'] ) ) {
		$words = 'government ' . $words;
	}
	return $words;
}

/** "address, phone number and services" as far as the listings in view have them. */
function aw_seo_view_info( $n, $phones ) {
	if ( $phones >= $n ) {
		return 1 === $n ? 'address, phone number and services' : 'addresses, phone numbers and services';
	}
	if ( ! $phones ) {
		return 1 === $n ? 'address and services' : 'addresses and services';
	}
	return sprintf( 'addresses and services, %s with a phone number', number_format_i18n( $phones ) );
}

/* ---------- Directory: titles and descriptions ---------- */

/**
 * Search title of a district × type page or a filtered list by type (state or all India); '' for other views.
 * "Gaushalas in Pune, Maharashtra – Animal Writes", "Vets in Karnataka – Animal Writes".
 */
function aw_seo_dir_title() {
	$v = aw_seo_dir_view();
	if ( ! $v || '' === $v['type'] || $v['other'] ) {
		return '';
	}
	$what = aw_seo_title_case( aw_seo_view_type_words( $v, max( 1, $v['n'] ) ) );
	$ph   = aw_seo_dir_view_phones( $v );
	$num  = $ph ? ( 1 === $ph ? ' Contact Number' : ' Contact Numbers' ) : ''; // no separator but the " – " before the site name (owner, 15 Oct 2026)
	if ( 'district' === $v['kind'] ) {
		$full  = $v['place']->name . ' ' . $v['state']->name;
		$short = $v['place']->name;
	} else {
		$full  = 'state' === $v['kind'] ? $v['place']->name : 'India';
		$short = $full;
	}
	return aw_seo_fit(
		array_unique(
			array(
				$num ? $what . $num . ' in ' . $full : aw_seo_branded( $what . ' in ' . $full ),
				aw_seo_branded( $what . ' in ' . $full ),
				$what . ' in ' . $full,
				aw_seo_branded( $what . ' in ' . $short ),
				$what . ' in ' . $short,
			)
		),
		60 - mb_strlen( aw_seo_page_suffix() )
	);
}

/**
 * The places worth naming as "most are in": up to $max with at least two listings and more than the next place down
 * (no list of ties such as "A (1), B (1) and C (1)").
 *
 * @param array $list [place, count], most first.
 * @param int   $max  At most this many.
 * @return array
 */
function aw_seo_leaders( array $list, $max = 3 ) {
	$out = array();
	foreach ( array_slice( $list, 0, $max ) as $i => $r ) {
		if ( $r[1] < 2 || ( isset( $list[ $max ] ) && $r[1] <= $list[ $max ][1] ) ) {
			break;
		}
		$out[] = $r;
	}
	return count( $out ) === count( $list ) ? array() : $out; // naming every place is not "most"
}

/** "A (12), B (9) and C (4)" for the first $max places. */
function aw_seo_places_line( array $list, $max = 3 ) {
	return aw_seo_join( array_map( fn( $r ) => $r[0]->name . ' (' . number_format_i18n( $r[1] ) . ')', array_slice( $list, 0, $max ) ) );
}

/**
 * Meta description of a Directory place, district × type page or filtered list, from its counts; '' where the general
 * rules apply (the Directory hub, name searches, service or verified filters).
 */
function aw_seo_dir_description() {
	$v = aw_seo_dir_view();
	if ( ! $v || $v['other'] || ( 'india' === $v['kind'] && '' === $v['type'] ) || ! $v['n'] ) {
		return '';
	}
	$n     = $v['n'];
	$place = $v['place'];
	$state = $v['state'];
	$tail  = array( ' From the Animal Writes Directory of animal welfare organisations in India.', ' From the Animal Writes Directory.', '' );
	$cands = array();
	if ( '' !== $v['type'] ) {
		$what = aw_seo_view_type_words( $v, $n );
		$info = aw_seo_view_info( $n, aw_seo_dir_view_phones( $v ) );
		if ( 'district' === $v['kind'] ) {
			$lead = sprintf( '%1$s %2$s in %3$s, %4$s: %5$s.', aw_seo_num( $n, true ), $what, $place->name, $state->name, $info );
			$m    = aw_seo_dir_count( $state, $v['type'] );
			$k    = count( aw_seo_dir_children( $state, $v['type'] ) );
			$ctx  = $m > $n && $k > 1 ? sprintf( ' %1$s has %2$s in %3$s districts.', $state->name, number_format_i18n( $m ), number_format_i18n( $k ) ) : '';
			foreach ( array( $ctx, '' ) as $c ) {
				foreach ( $tail as $t ) {
					$cands[] = $lead . $c . $t;
				}
			}
		} else {
			$kids = aw_seo_dir_children( $place, $v['type'] );
			$area = 'state' === $v['kind']
				? sprintf( '%1$s %2$s in %3$s, across %4$s %5$s.', aw_seo_num( $n, true ), $what, $place->name, number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' )
				: sprintf( '%1$s %2$s across India, in %3$s states and union territories.', aw_seo_num( $n, true ), $what, number_format_i18n( count( $kids ) ) );
			$lead = aw_seo_leaders( $kids );
			$most = $lead ? ' Most are in ' . aw_seo_places_line( $lead ) . '.' : '';
			foreach ( array_unique( array( $most, count( $lead ) > 2 ? ' Most are in ' . aw_seo_places_line( $lead, 2 ) . '.' : '', '' ) ) as $m ) {
				foreach ( array( ' ' . ucfirst( $info ) . ' of each.', '' ) as $t ) {
					$cands[] = $area . $m . $t;
				}
			}
		}
	} else {
		$parts = aw_seo_dir_breakdown( $place );
		$where = 'district' === $v['kind'] ? $place->name . ', ' . $state->name : $place->name;
		if ( 1 === $n ) {
			$one     = $parts ? aw_seo_type_words( $parts[0][0], 1 ) : 'animal welfare organisation';
			$cands[] = sprintf( 'One %1$s listed in %2$s, with its phone, address and services, in the Animal Writes Directory of India.', $one, $where );
			$cands[] = sprintf( 'One %1$s listed in %2$s, with its phone, address and services.', $one, $where );
		} elseif ( 'district' === $v['kind'] && aw_seo_has_numbers( $place ) ) {
			// Searched as "animal helpline number {district}" and "animal rescue {district} contact number" (SC, 15 Oct 2026).
			$ph   = aw_seo_dir_view_phones( $v );
			$lead = sprintf( 'Animal helpline and rescue contact numbers in %1$s: %2$s animal welfare organisations, %3$s', $where, number_format_i18n( $n ), $ph >= $n ? 'all with a phone number' : number_format_i18n( $ph ) . ' with a phone number' );
			for ( $max = min( 3, count( $parts ) ); $max >= 0; $max-- ) {
				$list    = array_map( fn( $p ) => number_format_i18n( $p[1] ) . ' ' . aw_seo_type_words( $p[0], $p[1], true ), array_slice( $parts, 0, $max ) );
				$cands[] = $lead . ( $list ? ', including ' . aw_seo_join( $list ) : '' ) . '.';
			}
			$cands[] = sprintf( 'Animal helpline and rescue contact numbers in %1$s: %2$s animal welfare organisations.', $where, number_format_i18n( $n ) );
		} else {
			$kids = 'state' === $v['kind'] ? aw_seo_dir_children( $place ) : array();
			$lead = sprintf( '%1$s animal welfare organisations in %2$s', number_format_i18n( $n ), $where ) . ( $kids ? sprintf( ', across %1$s %2$s', number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' ) : '' );
			for ( $max = min( 3, count( $parts ) ); $max >= 0; $max-- ) {
				$list = array_map( fn( $p ) => number_format_i18n( $p[1] ) . ' ' . aw_seo_type_words( $p[0], $p[1], true ), array_slice( $parts, 0, $max ) );
				$inc  = $list ? ', including ' . aw_seo_join( $list ) . '.' : '.';
				$cands[] = $lead . $inc . ' Phone, address and services of each.';
				$cands[] = $lead . $inc;
			}
		}
	}
	foreach ( $cands as $c ) {
		if ( mb_strlen( $c ) <= AW_SEO_DESC_MAX - mb_strlen( aw_seo_page_suffix() ) ) {
			return $c;
		}
	}
	return (string) end( $cands );
}

/* ---------- Directory: the visible line of facts ---------- */

/** A link for the facts line. */
function aw_seo_link( $url, $text ) {
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
}

/** "A (12), B (9) and C (4)" with each place linked. */
function aw_seo_places_links( array $list, callable $url, $max = 3 ) {
	return aw_seo_join( array_map( fn( $r ) => aw_seo_link( $url( $r[0] ), $r[0]->name ) . ' (' . esc_html( number_format_i18n( $r[1] ) ) . ')', array_slice( $list, 0, $max ) ) );
}

/** " All have a phone number." / " 30 have a phone number." ('' without one). */
function aw_seo_phone_note( $n, $phones ) {
	if ( ! $phones ) {
		return '';
	}
	if ( 1 === $n ) {
		return esc_html( ' It has a phone number.' );
	}
	return esc_html( $phones >= $n ? ' All have a phone number.' : sprintf( 1 === $phones ? ' %s of them has a phone number.' : ' %s of them have a phone number.', number_format_i18n( $phones ) ) );
}

/** The facts line of the current Directory view (HTML), '' where there is nothing to add. */
function aw_seo_dir_facts() {
	$v = aw_seo_dir_view();
	if ( ! $v || $v['other'] || ! $v['n'] || aw_seo_page_number() > 1 || ( 'india' === $v['kind'] && '' === $v['type'] ) ) {
		return '';
	}
	$n     = $v['n'];
	$place = $v['place'];
	$state = $v['state'];
	$type  = $v['type'];
	$out   = '';
	if ( '' !== $type ) {
		if ( 'district' === $v['kind'] ) {
			$out  = esc_html( sprintf( '%1$s %2$s listed in %3$s.', aw_seo_num( $n, true ), aw_seo_view_type_words( $v, $n ), $place->name ) ) . aw_seo_phone_note( $n, aw_seo_dir_view_phones( $v ) );
			$m    = aw_seo_dir_count( $state, $type );
			$kids = aw_seo_dir_children( $state, $type );
			if ( count( $kids ) > 1 ) {
				// The leaders among all the state's districts, this one included (Nagaur, 130, was left out under Jodhpur, 118);
				// this district is named without a link to itself.
				$lead   = aw_seo_leaders( $kids );
				$named  = aw_seo_join(
					array_map(
						fn( $r ) => ( $r[0]->code === $place->code ? esc_html( $r[0]->name ) : aw_seo_link( aw_directory_url( $r[0]->slug, $type ), $r[0]->name ) ) . ' (' . esc_html( number_format_i18n( $r[1] ) ) . ')',
						$lead
					)
				);
				$out   .= ' ' . esc_html( $state->name . ' has ' ) . aw_seo_link( aw_directory_url( $state->slug, $type ), number_format_i18n( $m ) . ' ' . aw_seo_type_words( $type, $m ) )
					. esc_html( ' in ' . number_format_i18n( count( $kids ) ) . ' districts' )
					. ( $lead ? esc_html( '; the most in ' ) . $named : '' ) . '.';
			} else {
				$out .= ' ' . esc_html( sprintf( 'It is the only district in %1$s with %2$s listed.', $state->name, aw_seo_type_words( $type, 2 ) ) );
			}
		} else {
			$kids = aw_seo_dir_children( $place, $type );
			$out  = 'state' === $v['kind']
				? esc_html( sprintf( '%1$s %2$s in %3$s, across %4$s %5$s.', aw_seo_num( $n, true ), aw_seo_view_type_words( $v, $n ), $place->name, number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' ) )
				: esc_html( sprintf( '%1$s %2$s across India, in %3$s states and union territories.', aw_seo_num( $n, true ), aw_seo_view_type_words( $v, $n ), number_format_i18n( count( $kids ) ) ) );
			if ( aw_seo_leaders( $kids ) ) {
				$out .= ' ' . esc_html( 'Most are in ' ) . aw_seo_places_links( aw_seo_leaders( $kids ), fn( $p ) => aw_directory_url( $p->slug, $type ) ) . '.'; // districts × type, or states filtered by type
			}
		}
	} else {
		$parts = aw_seo_dir_breakdown( $place );
		$link  = fn( $p ) => aw_seo_link( aw_directory_url( $place->slug, $p[0] ), number_format_i18n( $p[1] ) . ' ' . aw_seo_type_words( $p[0], $p[1] ) );
		$inc   = $parts && ! ( 1 === count( $parts ) && $parts[0][1] === $n ) ? esc_html( ', including ' ) . aw_seo_join( array_map( $link, $parts ) ) : ''; // every type, so the parts add up to the count (a 5th type was dropped before)
		if ( $parts && 1 === count( $parts ) && $parts[0][1] === $n ) {
			$inc = esc_html( 1 === $n ? ': ' : ', all ' ) . $link( $parts[0] ); // one type only: still linked, so its district × type page is not an orphan (Ashoknagar, 16 Oct 2026)
		}
		if ( 'state' === $v['kind'] ) {
			$kids = aw_seo_dir_children( $place );
			$out  = esc_html( sprintf( '%1$s has %2$s %3$s in %4$s %5$s', $place->name, number_format_i18n( $n ), 1 === $n ? 'listing' : 'listings', number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' ) ) . $inc . '.';
			if ( aw_seo_leaders( $kids ) ) {
				$out .= ' ' . esc_html( 'Most are in ' ) . aw_seo_places_links( aw_seo_leaders( $kids ), fn( $p ) => aw_directory_url( $p->slug ) ) . '.';
			}
		} else {
			$out = esc_html( sprintf( '%1$s has %2$s %3$s', $place->name, number_format_i18n( $n ), 1 === $n ? 'listing' : 'listings' ) ) . $inc . '.' . aw_seo_phone_note( $n, aw_seo_dir_view_phones( $v ) );
			$m   = aw_seo_dir_count( $state );
			if ( $m > $n ) {
				$out .= ' ' . aw_seo_link( aw_directory_url( $state->slug ), $state->name ) . esc_html( ' has ' . number_format_i18n( $m ) . ' in all.' );
			}
		}
	}
	return '' !== $out ? '<p class="kit-note">' . $out . '</p>' : '';
}

// Under the result count and sort.
add_filter(
	'render_block_animal-writes/result-head',
	function ( $html ) {
		return '' === trim( (string) $html ) ? $html : $html . aw_seo_dir_facts();
	}
);

/* ---------- Categories ---------- */

/**
 * Published items per category: news, Library documents, guides, research summaries, opportunities and campaigns
 * (cached for 12 hours).
 *
 * @return array term_id => [aw_news, aw_legal, guide, research, aw_opportunity, aw_campaign] counts.
 */
function aw_seo_category_counts() {
	$out = get_transient( 'aw_seo_category_counts' );
	if ( is_array( $out ) ) {
		return $out;
	}
	global $wpdb;
	$research = get_term_by( 'slug', 'research', 'post_tag' );
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_id term, p.post_type type, SUM(EXISTS(SELECT 1 FROM {$wpdb->term_relationships} rc WHERE rc.object_id = p.ID AND rc.term_taxonomy_id = %d)) research, COUNT(DISTINCT p.ID) n FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_status = 'publish' WHERE tt.taxonomy = %s GROUP BY tt.term_id, p.post_type", $research ? (int) $research->term_taxonomy_id : 0, aw_seo_category_tax() ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out      = array();
	foreach ( $rows as $r ) {
		$t = (int) $r['term'];
		if ( 'post' === $r['type'] ) {
			$out[ $t ]['research'] = (int) $r['research'];
			$out[ $t ]['guide']    = (int) $r['n'] - (int) $r['research'];
		} else {
			$out[ $t ][ $r['type'] ] = (int) $r['n'];
		}
	}
	set_transient( 'aw_seo_category_counts', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}
add_action( 'save_post', fn() => delete_transient( 'aw_seo_category_counts' ) );

/**
 * What a category holds, as [text, url] parts: "285 news stories", "5 court orders and official documents", "8 guides",
 * "1 research summary", "2 opportunities", and the Directory listing types its "Find help" shows.
 *
 * @param WP_Term $term Category.
 * @param bool    $help With the Directory types.
 * @return array
 */
function aw_seo_category_parts( $term, $help = true ) {
	$c     = aw_seo_category_counts()[ $term->term_id ] ?? array();
	$words = array(
		'aw_news'        => array( 'news story', 'news stories', 'aw_news' ),
		'aw_legal'       => array( 'court order or official document', 'court orders and official documents', 'aw_legal' ),
		'guide'          => array( 'guide', 'guides', 'post' ),
		'research'       => array( 'research summary', 'research summaries', 'post' ),
		'aw_opportunity' => array( 'opportunity', 'opportunities', 'aw_opportunity' ),
		'aw_campaign'    => array( 'campaign', 'campaigns', 'aw_campaign' ),
	);
	$parts = array();
	foreach ( $words as $k => $w ) {
		$n = (int) ( $c[ $k ] ?? 0 );
		if ( $n ) {
			$parts[] = array( number_format_i18n( $n ) . ' ' . $w[ 1 === $n ? 0 : 1 ], add_query_arg( 'category_name', $term->slug, aw_hub_url( $w[2] ) ) );
		}
	}
	if ( $help && function_exists( 'aw_category_help_types' ) ) {
		foreach ( aw_category_help_types( $term->term_id ) as $tid ) {
			$t = get_term( $tid, 'post_tag' );
			$n = ( $t && ! is_wp_error( $t ) ) ? aw_seo_dir_count( null, $t->slug ) : 0;
			if ( $n ) {
				$parts[] = array( number_format_i18n( $n ) . ' ' . aw_seo_type_words( $t->slug, $n ), add_query_arg( 'tag', $t->slug, aw_hub_url( 'aw_listing' ) ), 'dir' );
			}
		}
	}
	return $parts;
}

/**
 * A category's parts as text: [what it holds, its Directory types].
 *
 * @param array $parts aw_seo_category_parts().
 * @param bool  $links As links.
 * @return array
 */
function aw_seo_category_split( array $parts, $links = false ) {
	$out = array( array(), array() );
	foreach ( $parts as $p ) {
		$out[ empty( $p[2] ) ? 0 : 1 ][] = $links ? aw_seo_link( $p[1], $p[0] ) : $p[0];
	}
	return $out;
}

/** Meta description of a category page from what it holds; '' when it holds nothing. */
function aw_seo_category_description( $term ) {
	$parts = aw_seo_category_parts( $term );
	if ( ! $parts ) {
		return '';
	}
	list( $items, $dir ) = aw_seo_category_split( $parts );
	$lead  = aw_seo_plain( $term->name ) . ' in India on Animal Writes: ';
	$room  = AW_SEO_DESC_MAX - mb_strlen( aw_seo_page_suffix() );
	$cands = array();
	for ( $d = count( $dir ); $d >= 0; $d-- ) {
		$tail = $d ? ' In the Directory: ' . aw_seo_join( array_slice( $dir, 0, $d ) ) . '.' : '';
		$cands[] = ( $items ? $lead . aw_seo_join( $items ) . '.' : rtrim( $lead, ': ' ) . '.' ) . $tail;
	}
	for ( $max = count( $items ) - 1; $max >= 1; $max-- ) {
		$cands[] = $lead . aw_seo_join( array_slice( $items, 0, $max ) ) . '.';
	}
	foreach ( $cands as $c ) {
		if ( mb_strlen( $c ) <= $room ) {
			return $c;
		}
	}
	return aw_seo_cut( (string) end( $cands ), $room );
}

// A category page: the line of facts above its lists, page 1.
add_filter(
	'do_shortcode_tag',
	function ( $html, $tag ) {
		if ( 'aw_category_hub' !== $tag || ! aw_seo_is_category_page() || aw_seo_page_number() > 1 ) {
			return $html;
		}
		$parts = aw_seo_category_parts( get_queried_object() );
		if ( ! $parts ) {
			return $html;
		}
		list( $items, $dir ) = aw_seo_category_split( $parts, true );
		$line = trim( ( $items ? 'In this category: ' . aw_seo_join( $items ) . '.' : '' ) . ( $dir ? ' In the Directory: ' . aw_seo_join( $dir ) . '.' : '' ) );
		return '<p class="kit-note">' . $line . '</p>' . $html;
	},
	10,
	2
);

/**
 * Keep each category's stored Rank Math fields equal to the ones built here, so the Rank Math box shows what is printed:
 * the description, and the share image (Facebook image = the category photo, Twitter uses the Facebook one). Only
 * where the field is empty or still holds the automatic copy (_aw_seo_auto); a typed one is never replaced. Daily, when
 * a category is saved, and from WP-CLI: `wp eval 'print_r( aw_seo_sync_categories() );'`.
 *
 * @return array slug => description.
 */
function aw_seo_sync_categories() {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => aw_seo_category_tax(), 'hide_empty' => false ) ) as $term ) {
		$auto  = (array) json_decode( (string) get_term_meta( $term->term_id, '_aw_seo_auto', true ), true );
		$att   = function_exists( 'aw_category_image_id' ) ? (int) aw_category_image_id( $term ) : 0;
		$url   = $att ? (string) wp_get_attachment_url( $att ) : '';
		$want  = array(
			'title'                => aw_seo_term_title( $term ),
			'description'          => $term->count ? aw_seo_category_description( $term ) : '',
			'facebook_image_id'    => $url ? (string) $att : '',
			'facebook_image'       => $url,
			'twitter_use_facebook' => $url ? 'on' : '',
		);
		$dirty = false;
		foreach ( $want as $k => $value ) {
			$have = trim( (string) get_term_meta( $term->term_id, 'rank_math_' . $k, true ) );
			if ( '' !== $value && $have !== $value && ( '' === $have || $have === ( $auto[ $k ] ?? null ) ) ) {
				update_term_meta( $term->term_id, 'rank_math_' . $k, wp_slash( $value ) );
				$auto[ $k ] = $value;
				$dirty      = true;
			} elseif ( '' !== $value && $have === $value && ! isset( $auto[ $k ] ) && ! in_array( $k, array( 'description', 'title' ), true ) ) {
				$auto[ $k ] = $value; // already equal: mark it automatic so a later photo change follows
				$dirty      = true;
			}
		}
		if ( $dirty ) {
			update_term_meta( $term->term_id, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		}
		$out[ $term->slug ] = trim( (string) get_term_meta( $term->term_id, 'rank_math_description', true ) );
	}
	return $out;
}
add_action( 'edited_category', fn() => aw_seo_sync_categories(), 30 ); // after the photo is saved (category-images.php)
add_action( 'created_category', fn() => aw_seo_sync_categories(), 30 );
add_action( 'aw_seo_fill_daily', 'aw_seo_sync_categories', 30 );

/* ---------- Category and tag titles (owner, 15 Oct 2026) ---------- */

/**
 * Search titles of the categories and tags, written to fit 55–58 characters with " – Animal Writes" (keyword first,
 * plain text, the site name only as the suffix). A term not listed gets aw_seo_term_title_rule().
 */
const AW_SEO_TERM_TITLES = array(
	'category:adoption' => 'Animal Adoption News and Guides for India',
	'category:animal-birth-control' => 'ABC Animal Birth Control Rules and News',
	'category:transport-slaughter' => 'Animal Transport and Slaughter in India',
	'category:birds' => 'Bird Rescue and Welfare News from India',
	'category:campaigns-civic-action' => 'Animal Welfare Campaigns and Civic Action',
	'category:cattle-gaushalas' => 'Cattle Welfare and Gaushala News in India',
	'category:community-dogs' => 'Laws and News on Stray and Community Dogs',
	'category:courts-judgments' => 'Animal Welfare Court Cases and Judgments',
	'category:cruelty-prosecution' => 'Animal Cruelty Cases and Prosecution News',
	'category:disaster-rescue' => 'Animal Rescue in Disasters and Emergencies',
	'category:feeders-caregivers' => 'Rights of People Feeding Street Animals',
	'category:laws-rules' => 'Animal Protection Laws and Rules in India',
	'category:performing-captive-animals' => 'Performing and Captive Animals in India',
	'category:pets-breeding' => 'Pet Shop and Dog Breeding Rules in India',
	'category:policy-government' => 'Animal Welfare Policy and Government News',
	'category:rabies-public-health' => 'Rabies Control and Public Health in India',
	'category:veterinary-care' => 'Veterinary Care and Animal Health in India',
	'category:wildlife' => 'Wildlife Protection and Rescue in India',
	'post_tag:abc-centre' => 'ABC Centres for Dog Sterilisation in India',
	'post_tag:animal-welfare-organisation' => 'Animal Welfare NGOs in India by District',
	'post_tag:gaushala' => 'Gaushalas in India by State and District',
	'post_tag:veterinary-office' => 'Government Veterinary Offices by District',
	'post_tag:mobile-veterinary-unit' => '1962 Mobile Veterinary Units across India',
	'post_tag:veterinary-dispensary' => 'Government Vet Dispensaries by District',
	'post_tag:veterinary-hospital' => 'Veterinary Hospitals in India by District',
	'post_tag:wildlife-rescue' => 'Wildlife Rescue Groups in Indian States',
	'post_tag:veterinary-clinic' => 'Veterinary Clinics in India by District',
	'post_tag:bird-hospital' => 'Bird Hospitals across India by District',
	'post_tag:animal-shelter' => 'Animal Shelters across India by District',
	'post_tag:animal-ambulance' => 'Animal Ambulances across India by District',
	'post_tag:adoption-network' => 'Pet Adoption Networks in India by District',
	'post_tag:boarding-and-foster' => 'Pet Boarding and Foster Homes across India',
	'post_tag:emergency-rescue' => 'Emergency Animal Rescue Groups in India',
	'post_tag:feeder-network' => 'Street Animal Feeder Networks across India',
	'post_tag:legal-aid' => 'Animal Welfare Legal Aid Groups in India',
	'post_tag:rescue-group' => 'Animal Rescue Groups in India by District',
	'post_tag:sanctuary' => 'Animal Sanctuaries across India by State',
	'post_tag:adoption' => 'NGOs Offering Animal Adoption across India',
	'post_tag:ambulance' => 'NGOs Running Animal Ambulances in India',
	'post_tag:boarding-foster' => 'NGOs Offering Pet Boarding and Fostering',
	'post_tag:cattle-care' => 'NGOs Offering Cattle Care in Indian States',
	'post_tag:feeding' => 'NGOs Feeding Street Animals across India',
	'post_tag:legal-help' => 'NGOs Offering Animal Legal Help in India',
	'post_tag:rescue' => 'NGOs Offering Animal Rescue across India',
	'post_tag:shelter' => 'NGOs Offering Animal Shelter across India',
	'post_tag:sterilisation' => 'NGOs Offering ABC Sterilisation in India',
	'post_tag:treatment' => 'NGOs Treating Injured Animals across India',
	'post_tag:vaccination' => 'NGOs Offering Animal Vaccination in India',
	'post_tag:act' => 'Animal Welfare Acts of India Summarised',
	'post_tag:advisory' => 'Animal Welfare Advisories Issued in India',
	'post_tag:circular' => 'Animal Welfare Circulars Issued in India',
	'post_tag:judgment' => 'Animal Welfare Court Judgments in India',
	'post_tag:notification' => 'Government Notifications on Animal Welfare',
	'post_tag:official-release' => 'Official Press Releases on Animal Welfare',
	'post_tag:order' => 'Court Orders on Animal Welfare in India',
	'post_tag:rule' => 'Animal Welfare Rules of India Summarised',
	'post_tag:template' => 'Letter Templates for Animal Welfare Cases',
	'post_tag:event' => 'Animal Welfare Event Listings for India',
	'post_tag:fellowship' => 'Animal Welfare Fellowship Opportunities',
	'post_tag:foster' => 'Animal Fostering Opportunities in India',
	'post_tag:grant' => 'Grants for Animal Welfare Work in India',
	'post_tag:internship' => 'Animal Welfare Internship Opportunities',
	'post_tag:job' => 'Jobs in Animal Welfare NGOs across India',
	'post_tag:volunteer' => 'Animal Welfare Volunteer Roles in India',
	'post_tag:faq' => 'Animal Welfare FAQs and Answers on the Law',
	'post_tag:guide' => 'Practical Animal Welfare Guides for India',
	'post_tag:how-to' => 'Animal Welfare How to Guides for Citizens',
	'post_tag:research' => 'Animal Welfare Research Studies from India',
);

/** A category's or tag's search title, with " – Animal Writes". */
function aw_seo_term_title( $term ) {
	$key = $term->taxonomy . ':' . $term->slug;
	if ( isset( AW_SEO_TERM_TITLES[ $key ] ) ) {
		return aw_seo_branded( AW_SEO_TERM_TITLES[ $key ] );
	}
	return aw_seo_term_title_rule( $term );
}

/**
 * The title of a category or tag that has no written one: the longest of a few shapes within 58 characters
 * (55–58 when one fits), else the shortest.
 */
function aw_seo_term_title_rule( $term ) {
	// Only tails that say what the page really lists, and no separator but the " – " before the brand (owner, 15 Oct
	// 2026: no filler such as ": News" added to reach a length, no colons or commas, no promise of phone numbers). New terms should still get a written title in AW_SEO_TERM_TITLES.
	$what  = 'post_tag' === $term->taxonomy ? aw_seo_tag_title( $term ) : aw_seo_plain( $term->name ) . ' in India';
	$types = array();
	foreach ( array( 'aw_listing', 'aw_legal', 'aw_news', 'post', 'aw_opportunity' ) as $pt ) {
		$q = new WP_Query( array( 'post_type' => $pt, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ) ) ) );
		if ( $q->posts ) {
			$types[] = $pt;
		}
	}
	$tries = array();
	$name  = 'post_tag' === $term->taxonomy ? aw_seo_tag_title( $term ) : aw_seo_plain( $term->name );
	if ( in_array( 'aw_listing', $types, true ) ) {
		$tries = array( $what . ' by State and District', $what . ' by District', str_replace( ' in India', ' across India', $what ) );
	} elseif ( array( 'aw_legal' ) === $types ) {
		$tries = array( $what . ' Summarised' );
	} elseif ( in_array( 'aw_news', $types, true ) ) {
		$tries = in_array( 'aw_legal', $types, true ) ? array( $name . ' News and Laws in India', $name . ' News and Laws' ) : array( $name . ' News from India', $name . ' News' );
	}
	$tries[] = $what;
	foreach ( $tries as $t ) {
		$n = mb_strlen( aw_seo_branded( $t ) );
		if ( $n >= 55 && $n <= 58 ) {
			return aw_seo_branded( $t );
		}
	}
	foreach ( $tries as $t ) {
		if ( mb_strlen( aw_seo_branded( $t ) ) <= 58 ) {
			return aw_seo_branded( $t );
		}
	}
	return $what; // longer than the range: the name alone, never cut
}

/* ---------- Resources hub: guides and research summaries counted apart ---------- */

/** "15 guides, 1 FAQ page and 143 research summaries" for the unfiltered Resources hub ('' when it does not apply). */
function aw_seo_resources_count_label() {
	if ( ( function_exists( 'aw_is_filtered_list' ) && aw_is_filtered_list() ) || ! function_exists( 'aw_hub_count_label' ) ) {
		return '';
	}
	return aw_hub_count_label( 'post' ); // the same live split the hub shows above its pagination
}

/* ---------- Tags: Library, Resource, Opportunity and Listing types, and Services ---------- */

/** A tag's plural for a title: "Gaushalas", "Judgments", "Official Releases", "Jobs", "Mobile Veterinary Units (1962)". */
function aw_seo_tag_plural( $term ) {
	$fixed = array(
		'research'           => 'Research Summaries',
		'faq'                => 'FAQs',
		'how-to'             => 'How-to Guides',
		'advisory'           => 'Advisories',
		'volunteer'          => 'Volunteer Opportunities',
		'foster'             => 'Foster Opportunities',
		'animal-welfare-organisation' => 'Animal Welfare Organisations',
		'mobile-veterinary-unit'      => 'Mobile Veterinary Units (1962)',
		'veterinary-office'           => 'Government Veterinary Offices',
	);
	if ( isset( $fixed[ $term->slug ] ) ) {
		return $fixed[ $term->slug ];
	}
	if ( function_exists( 'aw_service_slugs' ) && in_array( $term->slug, aw_service_slugs(), true ) ) {
		return 'Animal Welfare Organisations Offering ' . aw_seo_title_case( mb_strtolower( preg_replace( '/\s*\(.*?\)\s*/', ' ', $term->name ) ) );
	}
	if ( function_exists( 'aw_listing_type_slugs' ) && in_array( $term->slug, aw_listing_type_slugs(), true ) ) {
		return aw_seo_title_case( aw_seo_type_plural( $term->slug ) );
	}
	$words = explode( ' ', trim( $term->name ) );
	$last  = (string) array_pop( $words );
	$last  = preg_match( '/[^aeiou]y$/', $last ) ? substr( $last, 0, -1 ) . 'ies' : ( preg_match( '/(s|x|ch|sh)$/', $last ) ? $last . 'es' : $last . 's' );
	return aw_seo_title_case( trim( implode( ' ', $words ) . ' ' . $last ) );
}

/** A tag page's title without the site name: "{Tag plural} in India". */
function aw_seo_tag_title( $term ) {
	return aw_seo_tag_plural( $term ) . ' in India';
}

/** Words for a sentence: lower case, except acronyms (ABC, FAQs), "Gaushala" and brackets such as "(1962)". */
function aw_seo_sentence_case( $text ) {
	return implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}s?$|^\(|^Gaushala/u', $w ) ? $w : mb_strtolower( $w ), explode( ' ', trim( $text ) ) ) );
}

/** A tag page's description from what it holds (live counts): "2,047 Gaushalas in India in the Animal Writes Directory …". */
function aw_seo_tag_description( $term ) {
	$n    = (int) $term->count;
	$sec  = function_exists( 'aw_tag_section' ) ? aw_tag_section( $term->slug ) : '';
	$svc  = function_exists( 'aw_service_slugs' ) && in_array( $term->slug, aw_service_slugs(), true );
	if ( 1 === $n ) {
		$what = $svc ? 'animal welfare organisation offering ' . aw_seo_sentence_case( preg_replace( '/\s*\(.*?\)\s*/', ' ', $term->name ) ) : aw_seo_sentence_case( $term->name );
	} else {
		$what = aw_seo_sentence_case( aw_seo_tag_plural( $term ) );
	}
	$num  = 0 === $n ? '' : ( 1 === $n ? 'One ' : number_format_i18n( $n ) . ' ' );
	switch ( $sec ) {
		case 'aw_listing':
			$text = sprintf( '%1$s%2$s in India in the Animal Writes Directory, by state and district, with address, phone number and services.', $num, $what );
			break;
		case 'aw_legal':
			$text = sprintf( '%1$s%2$s on animals in India, summarised in plain words with a link to the official document, in the Animal Writes Library.', $num, $what );
			break;
		case 'aw_opportunity':
			$text = sprintf( '%1$sanimal welfare %2$s in India with organisations across the country: the role, location and how to apply.', $num, $what );
			break;
		case 'post':
			$text = sprintf( '%1$s%2$s on animal welfare in India from Animal Writes, in plain words.', $num, $what );
			break;
		default:
			$text = sprintf( '%1$s%2$s on Animal Writes, animal welfare in India.', $num, $what );
	}
	return aw_seo_cut( ucfirst( $text ), AW_SEO_DESC_MAX );
}

/**
 * A tag's Rank Math focus keyword: the primary search phrase first, then secondaries, comma-separated (as the
 * categories are stored; docs/seo/keyword-map.md). Searched wording where it is known (keyword-demand-2026-10-15.md:
 * "gaushala", "veterinary hospital", "1962", "ngo jobs"), else built from the tag's name and section.
 *
 * @param WP_Term $term Tag.
 * @return string
 */
function aw_seo_tag_keyword( $term ) {
	$known = array(
		'abc-centre'                  => 'abc centre,dog sterilisation centre,animal birth control centre',
		'act'                         => 'animal welfare acts india,pca act',
		'adoption'                    => 'dog adoption,pet adoption ngo',
		'adoption-network'            => 'dog adoption,adopt a dog',
		'advisory'                    => 'animal welfare advisories,awbi advisory',
		'ambulance'                   => 'animal ambulance,animal ambulance number',
		'animal-ambulance'            => 'animal ambulance,animal ambulance number',
		'animal-shelter'              => 'animal shelter,animal shelter near me',
		'animal-welfare-organisation' => 'animal welfare ngos in india,animal ngo,animal ngo near me',
		'bird-hospital'               => 'bird hospital,injured bird helpline',
		'boarding-foster'             => 'pet boarding,dog foster home',
		'boarding-and-foster'         => 'pet boarding,dog foster home',
		'cattle-care'                 => 'cattle care,gaushala',
		'circular'                    => 'animal welfare circulars,awbi circular',
		'emergency-rescue'            => 'animal rescue,animal rescue contact number',
		'event'                       => 'animal welfare events india',
		'faq'                         => 'animal welfare faqs',
		'feeder-network'              => 'stray dog feeders,stray animal feeders',
		'feeding'                     => 'feeding stray dogs,stray dog feeding',
		'fellowship'                  => 'animal welfare fellowship',
		'foster'                      => 'foster animals,dog foster',
		'gaushala'                    => 'gaushala,gaushalas in india,gaushala near me',
		'grant'                       => 'animal welfare grants',
		'guide'                       => 'animal welfare guides',
		'how-to'                      => 'animal welfare how-to guides',
		'internship'                  => 'animal welfare internship',
		'job'                         => 'animal welfare jobs india,ngo jobs',
		'judgment'                    => 'animal welfare court judgments,supreme court stray dogs',
		'legal-aid'                   => 'animal legal aid',
		'legal-help'                  => 'animal legal help',
		'mobile-veterinary-unit'      => '1962 mobile veterinary unit,1962 animal ambulance,mobile veterinary unit',
		'notification'                => 'animal welfare notifications,awbi notification',
		'official-release'            => 'animal welfare official releases',
		'order'                       => 'animal welfare orders',
		'rescue'                      => 'animal rescue,animal rescue near me',
		'rescue-group'                => 'animal rescue group',
		'research'                    => 'animal welfare research india,stray dog research',
		'rule'                        => 'animal welfare rules india,animal protection rules',
		'sanctuary'                   => 'animal sanctuary india',
		'shelter'                     => 'animal shelter,animal shelter near me',
		'sterilisation'               => 'dog sterilisation,abc programme',
		'template'                    => 'animal complaint letter template',
		'treatment'                   => 'stray animal treatment,animal treatment ngo',
		'vaccination'                 => 'dog vaccination,anti rabies vaccination',
		'veterinary-clinic'           => 'veterinary clinic,vet near me',
		'veterinary-dispensary'       => 'veterinary dispensary,government veterinary dispensary',
		'veterinary-hospital'         => 'veterinary hospital,government veterinary hospital',
		'veterinary-office'           => 'government veterinary office,animal husbandry office',
		'volunteer'                   => 'animal welfare volunteer,animal volunteering near me',
		'wildlife-rescue'             => 'wildlife rescue india,wildlife rescue',
	);
	if ( isset( $known[ $term->slug ] ) ) {
		return $known[ $term->slug ];
	}
	$name   = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^\p{L}\p{N} -]+/u', ' ', mb_strtolower( preg_replace( '/\s*\(.*?\)\s*/', ' ', $term->name ) ) ) ) );
	$plural = trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^\p{L}\p{N} -]+/u', ' ', mb_strtolower( preg_replace( '/\s*\(.*?\)\s*/', ' ', aw_seo_tag_plural( $term ) ) ) ) ) );
	$sec    = function_exists( 'aw_tag_section' ) ? aw_tag_section( $term->slug ) : '';
	if ( function_exists( 'aw_service_slugs' ) && in_array( $term->slug, aw_service_slugs(), true ) ) {
		return 0 === strpos( $name, 'animal' ) ? $name : 'animal ' . $name; // "animal rescue"
	}
	if ( 'aw_listing' === $sec ) {
		return $plural . ' in india'; // "dog trainers in india"
	}
	if ( in_array( $sec, array( 'aw_legal', 'aw_opportunity', 'post' ), true ) ) {
		return 0 === strpos( $plural, 'animal' ) ? $plural : 'animal welfare ' . $plural; // "animal welfare circulars"
	}
	return $name;
}

// A tag page is indexed once it holds 3 or more items.
add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		if ( is_tag() && (int) get_queried_object()->count < 3 ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	},
	25
);

/**
 * Keep each tag's Rank Math title, description, focus keyword and robots equal to the ones built here (marked automatic
 * in _aw_seo_auto), so the Rank Math box shows what is printed and the sitemap leaves out tags with fewer than 3 items.
 * A typed title, description or keyword is never replaced. Daily, when a tag is added or saved, and from WP-CLI:
 * `wp eval 'print_r( aw_seo_sync_tags() );'`.
 *
 * @return array slug => [title, description, robots].
 */
function aw_seo_sync_tags() {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false ) ) as $term ) {
		$auto  = (array) json_decode( (string) get_term_meta( $term->term_id, '_aw_seo_auto', true ), true );
		$want  = array(
			'title'         => aw_seo_term_title( $term ), // plain text, 55–58 characters
			'description'   => aw_seo_tag_description( $term ),
			'focus_keyword' => aw_seo_tag_keyword( $term ),
		);
		foreach ( $want as $k => $value ) {
			$have = trim( (string) get_term_meta( $term->term_id, 'rank_math_' . $k, true ) );
			if ( $have !== $value && ( '' === $have || $have === ( $auto[ $k ] ?? null ) ) ) {
				update_term_meta( $term->term_id, 'rank_math_' . $k, wp_slash( $value ) );
				$auto[ $k ] = $value;
			}
		}
		$robots = (int) $term->count >= 3 ? array( 'index' ) : array( 'noindex' );
		$have   = (array) get_term_meta( $term->term_id, 'rank_math_robots', true );
		if ( $have !== $robots && ( ! array_filter( $have ) || $have === ( $auto['robots'] ?? null ) ) ) {
			update_term_meta( $term->term_id, 'rank_math_robots', $robots );
			$auto['robots'] = $robots;
		}
		update_term_meta( $term->term_id, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		$out[ $term->slug ] = array( get_term_meta( $term->term_id, 'rank_math_title', true ), get_term_meta( $term->term_id, 'rank_math_description', true ), implode( ',', (array) get_term_meta( $term->term_id, 'rank_math_robots', true ) ) );
	}
	return $out;
}
add_action( 'aw_seo_fill_daily', 'aw_seo_sync_tags', 31 );
add_action( 'created_post_tag', fn() => aw_seo_sync_tags(), 30 );
add_action( 'edited_post_tag', fn() => aw_seo_sync_tags(), 30 );
