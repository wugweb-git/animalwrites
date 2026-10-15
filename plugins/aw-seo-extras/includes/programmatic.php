<?php
/**
 * Pages built from live data (SEO Phase 4, 15 Oct 2026): the Directory's state, district, district × type and filtered
 * lists (?type=, ?state=), and the 18 topic pages.
 *
 * - Search titles and descriptions from the page's own counts and place names (titles.php asks these functions).
 * - One visible line of facts on page 1: under the result count in the Directory, above the lists on a topic page. It
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
 * counts under each. Rebuilt when the Directory's stored counts are (places.php), else kept for 12 hours.
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
	if ( is_array( $data ) && ( $data['at'] ?? -1 ) === $at ) {
		return $data;
	}
	global $wpdb;
	$ids  = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	$skip = $ids ? ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : ''; // preview-only items (integers)
	$rows = $wpdb->get_results( "SELECT p.ID id, MAX(CASE WHEN pm.meta_key = 'aw_state_code' THEN pm.meta_value END) st, MAX(CASE WHEN pm.meta_key = 'aw_district_code' THEN pm.meta_value END) dt FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN ('aw_state_code', 'aw_district_code') WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish'{$skip} GROUP BY p.ID", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$tax  = $wpdb->get_results( "SELECT r.object_id id, t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id AND tt.taxonomy = 'aw_listing_type' JOIN {$wpdb->terms} t ON t.term_id = tt.term_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_type = 'aw_listing' AND p.post_status = 'publish'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$types = array();
	foreach ( $tax as $t ) {
		$types[ (int) $t['id'] ][] = $t['slug'];
	}
	$vet  = defined( 'AW_VET_TYPES' ) ? AW_VET_TYPES : array();
	$data = array( 'at' => $at, 's' => array(), 'd' => array(), 'in' => array() );
	foreach ( $rows as $r ) {
		$keys = $types[ (int) $r['id'] ] ?? array();
		if ( array_intersect( $keys, $vet ) ) {
			$keys[] = 'veterinary';
		}
		$keys[] = '*';
		foreach ( array_unique( $keys ) as $k ) {
			$data['in'][ $k ] = ( $data['in'][ $k ] ?? 0 ) + 1;
			if ( $r['st'] ) {
				$data['s'][ (int) $r['st'] ][ $k ] = ( $data['s'][ (int) $r['st'] ][ $k ] ?? 0 ) + 1;
			}
			if ( $r['dt'] ) {
				$data['d'][ (int) $r['dt'] ][ $k ] = ( $data['d'][ (int) $r['dt'] ][ $k ] ?? 0 ) + 1;
			}
		}
	}
	set_transient( 'aw_seo_dir_data', $data, 12 * HOUR_IN_SECONDS );
	return $data;
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
 *
 * @param object $place State or district.
 * @return array List of [type key, count].
 */
function aw_seo_dir_breakdown( $place ) {
	$d    = aw_seo_dir_data();
	$have = (array) ( $d[ 'state' === $place->kind ? 's' : 'd' ][ $place->code ] ?? array() );
	$vet  = defined( 'AW_VET_TYPES' ) ? AW_VET_TYPES : array();
	$out  = array();
	foreach ( $have as $k => $n ) {
		if ( '*' === $k || in_array( $k, $vet, true ) ) {
			continue; // veterinary types are counted once, as "veterinary"
		}
		$out[] = array( $k, (int) $n );
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
		'gaushala'                    => array( 'gaushala', 'gaushalas', 'gaushalas' ),
		'veterinary'                  => array( 'vet or veterinary hospital', 'vets and veterinary hospitals', 'vets' ),
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
	$name = implode( ' ', array_map( fn( $w ) => preg_match( '/^[A-Z]{2,}$/', $w ) ? $w : mb_strtolower( $w ), explode( ' ', $name ) ) );
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
		$type = sanitize_title( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$type = ( '' !== $type && aw_listing_type_ok( $type ) ) ? $type : '';
	}
	$other = false;
	foreach ( array( 'q', 'service', 'verified' ) as $k ) {
		$other = $other || ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
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

/* ---------- Directory: titles and descriptions ---------- */

/**
 * Search title of a district × type page or a filtered list by type (state or all India); '' for other views.
 * "Gaushalas in Pune, Maharashtra – Animal Writes", "Vets & Veterinary Hospitals in Karnataka – Animal Writes".
 */
function aw_seo_dir_title() {
	$v = aw_seo_dir_view();
	if ( ! $v || '' === $v['type'] || $v['other'] ) {
		return '';
	}
	$what = aw_seo_title_case( aw_seo_type_words( $v['type'], max( 1, $v['n'] ) ) );
	if ( 'district' === $v['kind'] ) {
		$full  = $v['place']->name . ', ' . $v['state']->name;
		$short = $v['place']->name;
	} else {
		$full  = 'state' === $v['kind'] ? $v['place']->name : 'India';
		$short = $full;
	}
	return aw_seo_fit(
		array_unique(
			array(
				aw_seo_branded( $what . ' in ' . $full ),
				$what . ' in ' . $full,
				aw_seo_branded( $what . ' in ' . $short ),
				$what . ' in ' . $short,
			)
		),
		60 - mb_strlen( aw_seo_page_suffix() )
	);
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
		$what = aw_seo_type_words( $v['type'], $n );
		$info = 1 === $n ? 'address, phone number and services' : 'addresses, phone numbers and services';
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
			$most = count( $kids ) > 1 ? ' Most are in ' . aw_seo_places_line( $kids ) . '.' : '';
			foreach ( array( $most, count( $kids ) > 2 ? ' Most are in ' . aw_seo_places_line( $kids, 2 ) . '.' : '', '' ) as $m ) {
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
		if ( mb_strlen( $c ) <= 155 - mb_strlen( aw_seo_page_suffix() ) ) {
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
			$out  = esc_html( sprintf( '%1$s %2$s listed in %3$s.', aw_seo_num( $n, true ), aw_seo_type_words( $type, $n ), $place->name ) );
			$m    = aw_seo_dir_count( $state, $type );
			$kids = aw_seo_dir_children( $state, $type );
			if ( count( $kids ) > 1 ) {
				$others = array_values( array_filter( $kids, fn( $r ) => $r[0]->code !== $place->code ) );
				$out   .= ' ' . esc_html( $state->name . ' has ' ) . aw_seo_link( aw_directory_url( $state->slug, $type ), number_format_i18n( $m ) . ' ' . aw_seo_type_words( $type, $m ) )
					. esc_html( ' in ' . number_format_i18n( count( $kids ) ) . ' districts; also in ' )
					. aw_seo_places_links( $others, fn( $p ) => aw_directory_url( $p->slug, $type ) ) . '.';
			} else {
				$out .= ' ' . esc_html( sprintf( 'It is the only district in %1$s with %2$s listed.', $state->name, aw_seo_type_words( $type, 2 ) ) );
			}
		} else {
			$kids = aw_seo_dir_children( $place, $type );
			$out  = 'state' === $v['kind']
				? esc_html( sprintf( '%1$s %2$s in %3$s, across %4$s %5$s.', aw_seo_num( $n, true ), aw_seo_type_words( $type, $n ), $place->name, number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' ) )
				: esc_html( sprintf( '%1$s %2$s across India, in %3$s states and union territories.', aw_seo_num( $n, true ), aw_seo_type_words( $type, $n ), number_format_i18n( count( $kids ) ) ) );
			if ( count( $kids ) > 1 ) {
				$out .= ' ' . esc_html( 'Most are in ' ) . aw_seo_places_links( $kids, fn( $p ) => aw_directory_url( $p->slug, $type ) ) . '.'; // districts × type, or states filtered by type
			}
		}
	} else {
		$parts = aw_seo_dir_breakdown( $place );
		$link  = fn( $p ) => aw_seo_link( aw_directory_url( $place->slug, $p[0] ), number_format_i18n( $p[1] ) . ' ' . aw_seo_type_words( $p[0], $p[1] ) );
		$inc   = $parts && ! ( 1 === count( $parts ) && $parts[0][1] === $n ) ? esc_html( ', including ' ) . aw_seo_join( array_map( $link, array_slice( $parts, 0, 4 ) ) ) : '';
		if ( 'state' === $v['kind'] ) {
			$kids = aw_seo_dir_children( $place );
			$out  = esc_html( sprintf( '%1$s has %2$s %3$s in %4$s %5$s', $place->name, number_format_i18n( $n ), 1 === $n ? 'listing' : 'listings', number_format_i18n( count( $kids ) ), 1 === count( $kids ) ? 'district' : 'districts' ) ) . $inc . '.';
			if ( count( $kids ) > 1 ) {
				$out .= ' ' . esc_html( 'Most are in ' ) . aw_seo_places_links( $kids, fn( $p ) => aw_directory_url( $p->slug ) ) . '.';
			}
		} else {
			$out = esc_html( sprintf( '%1$s has %2$s %3$s', $place->name, number_format_i18n( $n ), 1 === $n ? 'listing' : 'listings' ) ) . $inc . '.';
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

/* ---------- Topics ---------- */

/**
 * Published items per topic: news, Library documents, guides, research summaries, opportunities and campaigns
 * (cached for 12 hours).
 *
 * @return array term_id => [aw_news, aw_legal, guide, research, aw_opportunity, aw_campaign] counts.
 */
function aw_seo_topic_counts() {
	$out = get_transient( 'aw_seo_topic_counts' );
	if ( is_array( $out ) ) {
		return $out;
	}
	global $wpdb;
	$research = get_term_by( 'slug', 'research', 'category' );
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT tt.term_id term, p.post_type type, SUM(EXISTS(SELECT 1 FROM {$wpdb->term_relationships} rc WHERE rc.object_id = p.ID AND rc.term_taxonomy_id = %d)) research, COUNT(DISTINCT p.ID) n FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->term_relationships} r ON r.term_taxonomy_id = tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID = r.object_id AND p.post_status = 'publish' WHERE tt.taxonomy = 'aw_topic' GROUP BY tt.term_id, p.post_type", $research ? (int) $research->term_taxonomy_id : 0 ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
	set_transient( 'aw_seo_topic_counts', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}
add_action( 'save_post', fn() => delete_transient( 'aw_seo_topic_counts' ) );

/**
 * What a topic holds, as [text, url] parts: "285 news stories", "5 court orders and official documents", "8 guides",
 * "1 research summary", "2 opportunities", and the Directory listing types its "Find help" shows.
 *
 * @param WP_Term $term Topic.
 * @param bool    $help With the Directory types.
 * @return array
 */
function aw_seo_topic_parts( $term, $help = true ) {
	$c     = aw_seo_topic_counts()[ $term->term_id ] ?? array();
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
			$parts[] = array( number_format_i18n( $n ) . ' ' . $w[ 1 === $n ? 0 : 1 ], add_query_arg( 'topic', $term->slug, aw_hub_url( $w[2] ) ) );
		}
	}
	if ( $help && function_exists( 'aw_topic_help_types' ) ) {
		foreach ( aw_topic_help_types( $term->term_id ) as $tid ) {
			$t = get_term( $tid, 'aw_listing_type' );
			$n = ( $t && ! is_wp_error( $t ) ) ? aw_seo_dir_count( null, $t->slug ) : 0;
			if ( $n ) {
				$parts[] = array( number_format_i18n( $n ) . ' ' . aw_seo_type_words( $t->slug, $n ) . ' in the Directory', add_query_arg( 'type', $t->slug, aw_hub_url( 'aw_listing' ) ) );
			}
		}
	}
	return $parts;
}

/** Meta description of a topic page from what it holds; '' when it holds nothing. */
function aw_seo_topic_description( $term ) {
	$parts = aw_seo_topic_parts( $term );
	if ( ! $parts ) {
		return '';
	}
	$lead = aw_seo_plain( $term->name ) . ' in India on Animal Writes: ';
	for ( $max = count( $parts ); $max >= 1; $max-- ) {
		$line = $lead . aw_seo_join( array_column( array_slice( $parts, 0, $max ), 0 ) ) . '.';
		if ( mb_strlen( $line ) <= 155 - mb_strlen( aw_seo_page_suffix() ) ) {
			return $line;
		}
	}
	return aw_seo_cut( $line, 155 - mb_strlen( aw_seo_page_suffix() ) );
}

// A topic page: the line of facts above its lists, page 1.
add_filter(
	'do_shortcode_tag',
	function ( $html, $tag ) {
		if ( 'aw_topic_hub' !== $tag || ! is_tax( 'aw_topic' ) || aw_seo_page_number() > 1 ) {
			return $html;
		}
		$parts = aw_seo_topic_parts( get_queried_object() );
		if ( ! $parts ) {
			return $html;
		}
		$line = 'On this topic: ' . aw_seo_join( array_map( fn( $p ) => aw_seo_link( $p[1], $p[0] ), $parts ) ) . '.';
		return '<p class="kit-note">' . $line . '</p>' . $html;
	},
	10,
	2
);

/**
 * Keep each topic's stored Rank Math description equal to the one built here, so the Rank Math box shows what is
 * printed: only where the field is empty or still holds the automatic copy (_aw_seo_auto); a typed one is never
 * replaced. Daily, and from WP-CLI: `wp eval 'print_r( aw_seo_sync_topics() );'`.
 *
 * @return array slug => description.
 */
function aw_seo_sync_topics() {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => 'aw_topic', 'hide_empty' => true ) ) as $term ) {
		$value = aw_seo_topic_description( $term );
		$auto  = (array) json_decode( (string) get_term_meta( $term->term_id, '_aw_seo_auto', true ), true );
		$have  = trim( (string) get_term_meta( $term->term_id, 'rank_math_description', true ) );
		if ( '' !== $value && $have !== $value && ( '' === $have || $have === ( $auto['description'] ?? null ) ) ) {
			update_term_meta( $term->term_id, 'rank_math_description', wp_slash( $value ) );
			$auto['description'] = $value;
			update_term_meta( $term->term_id, '_aw_seo_auto', wp_slash( wp_json_encode( $auto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
		}
		$out[ $term->slug ] = trim( (string) get_term_meta( $term->term_id, 'rank_math_description', true ) );
	}
	return $out;
}
add_action( 'aw_seo_fill_daily', 'aw_seo_sync_topics', 30 );

/* ---------- Resources hub: guides and research summaries counted apart ---------- */

/** "16 guides and 143 research summaries" for the unfiltered Resources hub ('' when it does not apply). */
function aw_seo_resources_count_label() {
	if ( ( function_exists( 'aw_is_filtered_list' ) && aw_is_filtered_list() ) || ! function_exists( 'aw_hub_total' ) ) {
		return '';
	}
	$total    = (int) aw_hub_total( 'post' );
	$term     = get_term_by( 'slug', 'research', 'category' );
	$research = $term ? (int) $term->count : 0;
	$guides   = $total - $research;
	if ( ! $research || $guides < 1 ) {
		return '';
	}
	return sprintf( '%1$s %2$s and %3$s %4$s', number_format_i18n( $guides ), 1 === $guides ? 'guide' : 'guides', number_format_i18n( $research ), 1 === $research ? 'research summary' : 'research summaries' );
}
