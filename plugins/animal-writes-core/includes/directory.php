<?php
/**
 * Directory: /directory/, /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/.
 *
 * Every one of these is the Directory Page (hubs.php) with the place and type as query vars (aw_place, aw_dir_type);
 * its list is the hub Query Loop. Singles live at /directory/org/{slug}/.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory place URLs: /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/.
 * There are no state × type pages: /directory/{state}/?tag= is a filtered state list (canonical: the state page).
 * Older patterns (/directory/{district}/, /directory/{place}/{type}/) get one 301 to the new URL.
 */
function aw_directory_rewrites() {
	add_rewrite_tag( '%aw_dir%', '(.+?)' );
	add_rewrite_tag( '%aw_type_slug%', '([^/]+)' );
	add_rewrite_rule( '^directory/(?!org/|page/|feed/)(.+?)/?$', 'index.php?aw_dir=$matches[1]', 'top' );
}
add_action( 'init', 'aw_directory_rewrites', 20 );

/** A place (state or district) by slug, from the LGD file (places.php). */
function aw_dir_place( $slug ) {
	return aw_place_by_slug( $slug );
}

/** "Veterinary" is one filter for people; the listings keep their separate veterinary types. Bird Hospital is its own
 * choice (owner, 18 Oct 2026). */
const AW_VET_TYPES = array( 'veterinary-hospital', 'veterinary-dispensary', 'veterinary-clinic', 'mobile-veterinary-unit', 'veterinary-office' );

/** A listing-type slug from a URL or filter: a real type (a Directory tag that is not a service), or "veterinary". */
function aw_listing_type_ok( $slug ) {
	return 'veterinary' === $slug || in_array( $slug, aw_listing_type_slugs(), true );
}

/** The ?tag= values on this request (tag=a,b or tag[]=a&tag[]=b). */
function aw_request_tags() {
	$raw = wp_unslash( $_GET['tag'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below, per tag
	return array_values( array_unique( array_filter( array_map( 'sanitize_title', is_array( $raw ) ? $raw : explode( ',', (string) $raw ) ) ) ) );
}

/** The listing type chosen by ?tag= (the first one that is a type, or "veterinary"), or ''. */
function aw_request_type() {
	foreach ( aw_request_tags() as $t ) {
		if ( aw_listing_type_ok( $t ) ) {
			return $t;
		}
	}
	return '';
}

/** The services chosen by ?tag=. */
function aw_request_services() {
	return array_values( array_intersect( aw_request_tags(), aw_service_slugs() ) );
}

/** What to query for a type slug: the type itself, or every veterinary type. */
function aw_listing_type_query( $slug ) {
	return 'veterinary' === $slug ? implode( ',', AW_VET_TYPES ) : $slug;
}

/** Display name of a type slug. */
function aw_listing_type_name( $slug ) {
	if ( 'veterinary' === $slug ) {
		return 'Veterinary';
	}
	$t = get_term_by( 'slug', $slug, 'post_tag' );
	return $t ? $t->name : '';
}

/** Name of the listing type the current directory page shows, if any. */
function aw_current_type_name() {
	$slug = (string) ( get_query_var( 'aw_type_slug' ) ? get_query_var( 'aw_type_slug' ) : get_query_var( 'aw_dir_type' ) );
	if ( '' === $slug ) {
		$slug = aw_request_type();
	}
	return false !== strpos( $slug, ',' ) ? 'Veterinary' : ( $slug ? aw_listing_type_name( $slug ) : '' );
}

/** Turn /directory/... into a listing query, or send older URL shapes to the new one. */
add_filter(
	'request',
	function ( $vars ) {
		if ( empty( $vars['aw_dir'] ) ) {
			return $vars;
		}
		$seg   = array_values( array_filter( explode( '/', strtolower( (string) $vars['aw_dir'] ) ) ) );
		$paged = 0;
		$n     = count( $seg );
		if ( $n >= 2 && 'page' === $seg[ $n - 2 ] && ctype_digit( $seg[ $n - 1 ] ) ) {
			$paged = (int) $seg[ $n - 1 ];
			$seg   = array_slice( $seg, 0, -2 );
		}
		$type_ok = 'aw_listing_type_ok';
		$go      = function ( $url ) use ( $paged ) {
			if ( $paged > 1 ) {
				$url = strtok( $url, '?' ) . 'page/' . $paged . '/' . ( strpos( $url, '?' ) ? '?' . substr( $url, strpos( $url, '?' ) + 1 ) : '' );
			}
			$keep = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'q', 'verified', 'gov', 'tag' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( add_query_arg( map_deep( $keep, 'rawurlencode' ), $url ), 301, 'Animal Writes' );
			exit;
		};
		$out     = array( 'pagename' => 'directory' ); // the Directory Page; its list reads the place and type (hubs.php)
		$a       = isset( $seg[0] ) ? aw_dir_place( $seg[0] ) : null;
		$b       = $seg[1] ?? '';
		$c       = $seg[2] ?? '';
		if ( 1 === count( $seg ) && $a ) {
			if ( $a->parent ) {
				$go( aw_directory_url( $a->slug ) ); // old /directory/{district}/
			}
			$out['aw_place'] = $a->slug;
			$type            = aw_request_type();
			if ( $type ) {
				$out['aw_dir_type'] = aw_listing_type_query( $type ); // filtered state list
			}
		} elseif ( 2 === count( $seg ) && $a ) {
			$bp = aw_dir_place( $b );
			if ( ! $a->parent && $bp && (int) $bp->parent === (int) $a->term_id ) {
				$out['aw_place'] = $bp->slug;
			} elseif ( $type_ok( $b ) ) {
				$go( aw_directory_url( $a->slug, $b ) ); // old /directory/{place}/{type}/
			} else {
				return array( 'error' => '404' );
			}
		} elseif ( 3 === count( $seg ) && $a && ! $a->parent ) {
			$bp = aw_dir_place( $b );
			if ( ! $bp || (int) $bp->parent !== (int) $a->term_id || ! $type_ok( $c ) ) {
				return array( 'error' => '404' );
			}
			$out['aw_place']     = $bp->slug;
			$out['aw_dir_type']  = aw_listing_type_query( $c );
			$out['aw_type_slug'] = $c;
		} else {
			return array( 'error' => '404' );
		}
		if ( $paged ) {
			$out['paged'] = $paged;
		}
		return $out;
	}
);

add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'aw_dir_type';
		return $v;
	}
);

/**
 * The Directory list query: place, type, name search, verified only, and the order (verified first, then A–Z; or
 * "Most details first"). No featured listings (owner, 24 Oct 2026). Used by the hub Query Loop (hubs.php).
 *
 * @param array $v Base query vars.
 * @return array
 */
function aw_directory_query_vars( $v ) {
	$mq                  = (array) ( $v['meta_query'] ?? array() );
	$v['aw_digits_last'] = 1; // A–Z means letters first: names that start with a number (e.g. "1962 …") come after Z

	$order = array( 'title' => 'ASC' );
	// Verified first, once any listing is verified. Until then the step changes nothing and its OR join (aw_verified is
	// missing on most listings) made every Directory query about 300 ms slower.
	if ( aw_verified_count() ) {
		$mq['ver'] = array(
			'relation' => 'OR',
			'ver_yes'  => array( 'key' => 'aw_verified', 'type' => 'NUMERIC', 'compare' => 'EXISTS' ),
			array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ),
		);
		$order     = array( 'ver_yes' => 'DESC', 'title' => 'ASC' );
	}
	if ( 'details' === sanitize_key( wp_unslash( $_GET['sort'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$mq['score'] = array( 'key' => 'aw_contact_score', 'type' => 'NUMERIC' );
		$order       = array( 'score' => 'DESC', 'title' => 'ASC' );
	}
	$place = aw_current_place();
	if ( $place ) {
		$mq['aw_place'] = aw_place_meta_clause( $place );
	}
	if ( ! empty( $_GET['verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$mq['only_verified'] = array( 'key' => 'aw_verified', 'value' => '1' );
	}
	if ( ! empty( $_GET['gov'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Verified only (gov-verified.php): a read-only filter
		$mq['only_gov'] = array( 'key' => 'aw_gov_verified_on', 'compare' => 'EXISTS' );
	}
	$type = (string) get_query_var( 'aw_dir_type' );
	if ( '' === $type ) {
		$g    = aw_request_type();
		$type = $g ? aw_listing_type_query( $g ) : ''; // a filtered list (?tag=); canonical stays the unfiltered page
	}
	$tq = array();
	if ( '' !== $type ) {
		$tq[] = array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => explode( ',', $type ) );
	}
	foreach ( aw_request_services() as $service ) { // each chosen service
		$tq[] = array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => array( $service ) );
	}
	if ( $tq ) {
		$v['tax_query'] = $tq;
	}
	$text = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $text ) {
		$v['s']              = $text;
		$v['search_columns'] = array( 'post_title' );
	}
	$v['meta_query'] = $mq;
	$v['orderby']    = $order;
	return $v;
}

/**
 * Front-end check: the Directory Page (hub, place or place × type).
 *
 * @return bool
 */
function aw_is_directory_view() {
	return function_exists( 'aw_hub_type' ) && 'aw_listing' === aw_hub_type();
}

/**
 * H1 of the Directory view: "Directory", "Animal Helpline Numbers in Kerala" where the place's title says so (a listing
 * with a phone or an official number: aw_seo_place_has_helpline(); owner, 5 Nov 2026), else "Help in Kerala";
 * "Animal Shelter Contact Numbers in Gandhinagar" where its listings have a number (aw_seo_dir_h1(), owner, 5 Nov 2026),
 * else "Gaushala in Ernakulam" or the type.
 */
function aw_directory_h1() {
	$city  = aw_current_place();
	$tname = aw_current_type_name();
	if ( ! $city && ! $tname ) {
		return get_the_title( get_queried_object_id() );
	}
	if ( $tname ) {
		$numbers = $city && function_exists( 'aw_seo_dir_h1' ) ? aw_seo_dir_h1() : '';
		if ( '' !== $numbers ) {
			return $numbers;
		}
		return $city ? $tname . ' in ' . $city->name : $tname;
	}
	if ( function_exists( 'aw_seo_place_has_helpline' ) && aw_seo_place_has_helpline( $city ) ) {
		return 'Animal Helpline Numbers in ' . $city->name;
	}
	return 'Help in ' . $city->name;
}

// The Directory Page's H1 and intro follow the place and type.
add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		if ( 1 !== (int) ( $block['attrs']['level'] ?? 2 ) || ! aw_is_directory_view() || ( ! aw_current_place() && ! aw_current_type_name() ) ) {
			return $html;
		}
		return preg_replace( '#(<h1[^>]*>)(.*?)(</h1>)#s', '$1' . esc_html( aw_directory_h1() ) . '$3', $html, 1 );
	},
	5,
	2
);
add_filter(
	'render_block_core/paragraph',
	function ( $html ) {
		if ( ! aw_is_directory_view() || false === strpos( $html, 'kit-section-desc' ) || ! aw_current_place() ) {
			return $html;
		}
		$html = preg_replace( '#(<p[^>]*kit-section-desc[^>]*>)(.*?)(</p>)#s', '$1' . esc_html( aw_directory_intro() ) . '$3', $html, 1 );
		return function_exists( 'aw_place_intro_html' ) ? $html . aw_place_intro_html() : $html; // the place's own intro, when it has one (place-intros.php)
	}
);

// On a listing's own page its type links to that type in its district (/directory/{state}/{district}/{type}/); every
// other tag link is the tag's own page (/tag/{slug}/).
add_filter(
	'term_link',
	function ( $url, $term, $tax ) {
		if ( 'post_tag' !== $tax || ! is_singular( 'aw_listing' ) || ! in_array( $term->slug, aw_listing_type_slugs(), true ) || ! function_exists( 'aw_item_place' ) ) {
			return $url;
		}
		$place = aw_item_place( get_queried_object_id() );
		return ( $place && 'district' === $place->kind ) ? aw_directory_url( $place->slug, $term->slug ) : $url;
	},
	10,
	3
);

/**
 * Archive titles for city / city × type pages, e.g. "Animal shelters in Delhi".
 *
 * @param string $title Default title.
 * @return string
 */
function aw_directory_title( $title ) {
	if ( ! aw_is_directory_view() ) {
		return $title;
	}
	$city  = aw_current_place();
	$tname = aw_current_type_name();

	if ( $city && $tname ) {
		/* translators: 1: listing type, 2: city. */
		return sprintf( __( '%1$s in %2$s', 'animal-writes-core' ), $tname, $city->name );
	}
	if ( $city ) {
		/* translators: %s: city. */
		return sprintf( __( 'Animal welfare directory: %s', 'animal-writes-core' ), $city->name );
	}
	if ( $tname ) {
		return $tname;
	}
	return $title;
}
add_filter( 'get_the_archive_title', 'aw_directory_title', 20 );

// The document <title> of these views is set in aw-seo-extras (titles.php), from aw_directory_title().

/**
 * Directory URL helper for themes/menus.
 *
 * @param string $city_slug State or district slug.
 * @param string $type_slug Optional listing type slug (district pages only; a state gets ?tag=).
 * @return string
 */
function aw_directory_url( $city_slug, $type_slug = '' ) {
	$place = aw_dir_place( sanitize_title( $city_slug ) );
	$type  = sanitize_title( $type_slug );
	if ( ! $place ) {
		return home_url( 'directory/' );
	}
	if ( ! $place->parent ) {
		$url = home_url( 'directory/' . $place->slug . '/' );
		return $type ? add_query_arg( 'tag', $type, $url ) : $url; // no state × type pages
	}
	$state = aw_place_by_id( $place->parent );
	return home_url( 'directory/' . $state->slug . '/' . $place->slug . '/' . ( $type ? $type . '/' : '' ) );
}

/* ---------- Search and filters: [aw_directory_filter] ---------- */

/**
 * Turn the filter form (state, district, type) into the clean directory URL, keeping q and verified.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! aw_is_directory_view() ) {
			return;
		}
		$g = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		// One value each: ?state[]=kerala stopped the page (sanitize_title() on a list; audit, 12 Nov 2026). The first one counts.
		foreach ( array( 'state', 'district', 'q' ) as $k ) {
			if ( isset( $g[ $k ] ) && ! is_string( $g[ $k ] ) ) {
				$g[ $k ] = is_array( $g[ $k ] ) ? (string) current( array_filter( $g[ $k ], 'is_string' ) ?: array( '' ) ) : '';
			}
		}
		// A 6-digit PIN code in the search box opens that PIN's district (or state) list; when India Post does not know
		// the PIN, the district of the listings that store it (or the nearest PIN prefix) is used.
		$pin = preg_replace( '/\s/', '', (string) ( $g['q'] ?? '' ) );
		if ( preg_match( '/^[1-9]\d{5}$/', $pin ) ) {
			$r = aw_pin_lookup( $pin );
			if ( ! is_array( $r ) || empty( $r['district_slug'] ) ) {
				$near = aw_pin_nearest_district( $pin );
				if ( $near ) {
					$r = array( 'state_slug' => aw_place_parent_state( $near )->slug, 'district_slug' => $near->slug );
				}
			}
			if ( is_array( $r ) ) {
				$g['state']     = $r['state_slug'];
				$g['district']  = $r['district_slug'];
				$g['q']         = '';
				$g['aw_filter'] = 1;
			}
		} elseif ( '' === sanitize_title( $g['state'] ?? '' ) && '' === sanitize_title( $g['district'] ?? '' ) ) {
			// A name typed another way ("bluecross", "blue-cross") is searched as the name the listings carry.
			$alias = aw_search_name_alias( (string) ( $g['q'] ?? '' ) );
			if ( '' !== $alias ) {
				$g['q']         = $alias;
				$g['aw_filter'] = 1;
			}
			// A district, state or known city name in the search box ("Jhansi", "Kerala", "Kochi") opens that place's list.
			$place = aw_search_place( (string) ( $g['q'] ?? '' ) );
			if ( $place ) {
				$g['state']     = 'district' === $place->kind ? aw_place_parent_state( $place )->slug : $place->slug;
				$g['district']  = 'district' === $place->kind ? $place->slug : '';
				$g['q']         = '';
				$g['aw_filter'] = 1;
			}
		}
		if ( ! isset( $g['aw_filter'] ) ) {
			return;
		}
		$city = sanitize_title( $g['district'] ?? '' ) ? sanitize_title( $g['district'] ) : sanitize_title( $g['state'] ?? '' );
		$tags = array_values( array_filter( array_map( 'sanitize_title', (array) ( $g['tag'] ?? array() ) ) ) );
		$type = (string) current( array_filter( $tags, 'aw_listing_type_ok' ) ?: array( '' ) );
		$more = array_values( array_diff( $tags, array( $type ) ) ); // services
		$base = $city ? aw_directory_url( $city, $type ) : ( $type ? add_query_arg( 'tag', $type, aw_hub_url( 'aw_listing' ) ) : aw_hub_url( 'aw_listing' ) );
		if ( $more ) {
			$have = (string) wp_parse_url( $base, PHP_URL_QUERY );
			parse_str( $have, $hq );
			$base = add_query_arg( 'tag', implode( ',', array_filter( array_merge( array( (string) ( $hq['tag'] ?? '' ) ), $more ) ) ), $base );
		}
		$extra = array_filter(
			array(
				'q'        => sanitize_text_field( $g['q'] ?? '' ),
				'verified' => empty( $g['verified'] ) ? '' : '1',
				'gov'      => empty( $g['gov'] ) ? '' : '1',
			)
		);
		wp_safe_redirect( add_query_arg( $extra, $base ) );
		exit;
	}
);

/**
 * Organisation names people type as one word, with a hyphen or as initials, and the words the listings' names carry:
 * a search for the key is run as a search for the value (site searches, 1 Nov 2026). Keys are letters and digits only,
 * lower case; the value is searched as typed-in words (every word must be in the name).
 */
const AW_SEARCH_NAME_ALIASES = array(
	'bluecross' => 'blue cross', // initials such as "pfa" are left alone: listings carry them in their names ("PFA Faridabad")
);

/**
 * The search the listings' names answer for a term typed another way, '' when the term is searched as it is. The term
 * is compared without spaces, hyphens, dots or case ("Blue-Cross", "blue cross" and "bluecross" are one key); a term
 * that already matches its alias's words is left alone, so the redirect never loops.
 *
 * @param string $text The search box text.
 * @return string
 */
function aw_search_name_alias( $text ) {
	$key = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $text ) );
	if ( '' === $key || empty( AW_SEARCH_NAME_ALIASES[ $key ] ) ) {
		return '';
	}
	$to = AW_SEARCH_NAME_ALIASES[ $key ];
	return trim( preg_replace( '/\s+/', ' ', strtolower( (string) $text ) ) ) === $to ? '' : $to;
}

/**
 * The place a search term names: a district or state by its LGD or common name (places.php), or a city or
 * neighbourhood the place detector maps to a district (AW_PLACE_DETECT_CITIES, find-help.php). Null when the term is
 * not a place name, names more than one district, or is a word the detector skips ("south", "mon").
 *
 * @param string $text The search box text.
 * @return object|null
 */
function aw_search_place( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text || mb_strlen( $text ) < 3 || preg_match( '/\d/', $text ) ) {
		return null;
	}
	$key = aw_place_key( $text );
	if ( defined( 'AW_PLACE_DETECT_SKIP' ) && in_array( $key, AW_PLACE_DETECT_SKIP, true ) ) {
		return null;
	}
	$d = aw_place_district_by_name( $text );
	if ( ! $d && defined( 'AW_PLACE_DETECT_CITIES' ) && isset( AW_PLACE_DETECT_CITIES[ $key ] ) ) {
		$d = aw_place_district_by_name( AW_PLACE_DETECT_CITIES[ $key ] );
	}
	return $d ? $d : aw_place_state_by_name( $text );
}

/**
 * The district most of the published listings with a PIN code store: the exact PIN first, then the PINs that share
 * its first three digits (India Post's sorting district). Null when no listing stores such a PIN.
 *
 * @param string $pin 6-digit PIN code.
 * @return object|null
 */
function aw_pin_nearest_district( $pin ) {
	global $wpdb;
	$pin = preg_replace( '/\D/', '', (string) $pin );
	if ( 6 !== strlen( $pin ) ) {
		return null;
	}
	foreach ( array( $pin, substr( $pin, 0, 3 ) . '___' ) as $like ) {
		$code = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT d.meta_value FROM {$wpdb->postmeta} pc JOIN {$wpdb->posts} p ON p.ID = pc.post_id AND p.post_type = 'aw_listing' AND p.post_status = 'publish' JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = 'aw_district_code' WHERE pc.meta_key = 'aw_pincode' AND pc.meta_value LIKE %s GROUP BY d.meta_value ORDER BY COUNT(*) DESC LIMIT 1",
				$like
			)
		);
		$d    = $code ? aw_place_district( (int) $code ) : null;
		if ( $d ) {
			return $d;
		}
	}
	return null;
}

/** Published listings shown to visitors (preview-only ones, such as the sample listing, left out). */
function aw_listing_public_count() {
	$hidden = function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array();
	$less   = $hidden ? count( get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'post__in' => $hidden, 'fields' => 'ids', 'numberposts' => -1 ) ) ) : 0;
	return max( 0, (int) wp_count_posts( 'aw_listing' )->publish - $less );
}

/** Number of verified listings (cached). "Verified only" appears once there is at least one. */
function aw_verified_count() {
	$n = get_transient( 'aw_verified_count' );
	if ( false === $n ) {
		$n = count( get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_verified', 'meta_value' => '1', 'post__not_in' => function_exists( 'kits_preview_only_ids' ) ? kits_preview_only_ids() : array() ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		set_transient( 'aw_verified_count', $n, HOUR_IN_SECONDS );
	}
	return (int) $n;
}

/**
 * Directory search bar (Phase 11 E2): Name · State · District · Type · Search, one baseline, 44px fields.
 * District is off until a state is chosen and then lists only that state's districts, with counts; Type lists only
 * types that have listings, with counts. Works without JavaScript (aw.js narrows the districts).
 *
 * @return string
 */
/** Services choice: only services that at least 3 listings offer (the rest would lead to near-empty lists). */
function aw_directory_service_field( $field ) {
	$cur  = (string) current( aw_request_services() ?: array( '' ) );
	$opts = '';
	foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'slug' => aw_service_slugs(), 'hide_empty' => true ) ) as $t ) {
		if ( (int) $t->count >= 3 ) {
			$opts .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $cur, $t->slug, false ) . '>' . esc_html( $t->name . ' (' . number_format_i18n( $t->count ) . ')' ) . '</option>';
		}
	}
	return $opts ? $field( 'aw-dir-service', 'Service', '<select id="aw-dir-service" name="tag[]"><option value="">All services</option>' . $opts . '</select>', 'service' ) : '';
}

/**
 * The search field and the State choice that the Home "Find help near you" block and the Directory search share (one
 * markup, one look: kit-field at --kit-size-control, the search icon, the same state list with listings only).
 *
 * @param array $args {
 *     @type string $prefix      Id prefix ('aw-ed' on Home, 'aw-dir' on the Directory).
 *     @type string $cls         Class prefix for the field wrappers ('aw-findhelp-' or 'aw-dir-').
 *     @type bool   $show_labels Visible labels (Directory) or screen-reader labels (Home).
 *     @type string $q_label     The search field's label.
 *     @type string $placeholder The search field's placeholder.
 *     @type string $state_label The State choice's label.
 *     @type string $q           The current search text.
 *     @type string $state       The current state's slug.
 * }
 * @return array{q: string, state: string} The two fields' HTML.
 */
function aw_dir_search_controls( $args ) {
	$a     = wp_parse_args(
		$args,
		array(
			'prefix'      => 'aw-dir',
			'cls'         => 'aw-dir-',
			'show_labels' => true,
			'q_label'     => 'Name or PIN code',
			'placeholder' => 'Search by name or PIN code',
			'state_label' => 'State',
			'q'           => '',
			'state'       => '',
		)
	);
	$label = fn( $id, $text ) => '<label' . ( $a['show_labels'] ? '' : ' class="screen-reader-text"' ) . ' for="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</label>';
	$field = fn( $cls, $id, $text, $control ) => '<div class="kit-field ' . esc_attr( $a['cls'] . $cls ) . '">' . $label( $id, $text ) . '<div class="kit-field-control">' . $control . '</div></div>';
	$opts  = '<option value="">All states and UTs</option>';
	foreach ( (array) aw_place_states() as $st ) {
		if ( aw_place_count( $st ) ) { // states and UTs with no listings are left out, so the list and the counts match
			$opts .= '<option value="' . esc_attr( $st->slug ) . '"' . selected( $a['state'], $st->slug, false ) . '>' . esc_html( $st->name ) . '</option>';
		}
	}
	$q_id = $a['prefix'] . '-q';
	$s_id = $a['prefix'] . '-state';
	return array(
		'q'     => $field( 'q', $q_id, $a['q_label'], '<span class="kit-ico kit-ico--search kit-field-icon" aria-hidden="true"></span><input type="search" id="' . esc_attr( $q_id ) . '" name="q" value="' . esc_attr( $a['q'] ) . '" placeholder="' . esc_attr( $a['placeholder'] ) . '" autocomplete="off">' ),
		'state' => $field( 'state', $s_id, $a['state_label'], '<select id="' . esc_attr( $s_id ) . '" name="state">' . $opts . '</select>' ),
	);
}

function aw_directory_filter( $type = '' ) {
	$cur_city = aw_current_place();
	$cur_type = '' !== $type ? (string) $type : (string) get_query_var( 'aw_dir_type' ); // $type: an organisation type's tag page (sections.php)
	if ( '' === $cur_type ) {
		$cur_type = aw_request_type();
	}
	$state    = $cur_city ? aw_place_parent_state( $cur_city ) : null;
	$district = $cur_city && $cur_city->parent ? $cur_city : null;
	$text     = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$verified = ! empty( $_GET['verified'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$gov      = ! empty( $_GET['gov'] ); // phpcs:ignore WordPress.Security.NonceVerification

	// States and districts with listings, with their counts (stored counts, places.php).
	$states = array_filter( aw_place_states(), 'aw_place_count' );
	$all    = array();
	foreach ( $states as $st ) {
		foreach ( aw_place_districts( $st->code ) as $k ) {
			$n = aw_place_count( $k );
			if ( $n ) {
				$all[ $st->term_id ][] = array( $k->slug, $k->name, $n );
			}
		}
	}
	$d_opts = '<option value="" data-none="Choose a state first" data-all="All districts">' . ( $state ? 'All districts' : 'Choose a state first' ) . '</option>';
	foreach ( $states as $st ) {
		$kids = $all[ $st->term_id ] ?? array();
		if ( $kids ) {
			$d_opts .= '<optgroup label="' . esc_attr( $st->name ) . '" data-state="' . esc_attr( $st->slug ) . '"' . ( $state && $state->term_id === $st->term_id ? '' : ' hidden disabled' ) . '>';
			foreach ( $kids as $k ) {
				$d_opts .= '<option value="' . esc_attr( $k[0] ) . '"' . selected( $district && $district->slug === $k[0], true, false ) . '>' . esc_html( $k[1] . ' (' . number_format_i18n( $k[2] ) . ')' ) . '</option>';
			}
			$d_opts .= '</optgroup>';
		}
	}
	// Types that have listings, with counts; the veterinary types are one "Veterinary" choice.
	$opts = array();
	foreach ( get_terms( array( 'taxonomy' => 'post_tag', 'slug' => aw_listing_type_slugs(), 'hide_empty' => true, 'orderby' => 'name' ) ) as $t ) {
		$key             = in_array( $t->slug, AW_VET_TYPES, true ) ? 'veterinary' : $t->slug;
		$opts[ $key ][0] = 'veterinary' === $key ? 'Veterinary' : $t->name;
		$opts[ $key ][1] = ( $opts[ $key ][1] ?? 0 ) + (int) $t->count;
	}
	uasort( $opts, fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
	$cur_type = (string) ( get_query_var( 'aw_type_slug' ) ? get_query_var( 'aw_type_slug' ) : ( false !== strpos( $cur_type, ',' ) ? 'veterinary' : $cur_type ) );
	$t_opts   = '<option value="">All types</option>';
	foreach ( $opts as $slug => $o ) {
		$t_opts .= '<option value="' . esc_attr( $slug ) . '"' . selected( $cur_type, $slug, false ) . '>' . esc_html( $o[0] . ' (' . number_format_i18n( $o[1] ) . ')' ) . '</option>';
	}
	$field    = fn( $id, $label, $control, $cls = '' ) => '<div class="kit-field aw-dir-' . $cls . '"><label for="' . $id . '">' . $label . '</label><div class="kit-field-control">' . $control . '</div></div>';
	$shared   = aw_dir_search_controls(
		array(
			'q'     => $text,
			'state' => $state ? $state->slug : '',
		)
	);
	$service  = aw_directory_service_field( $field );
	$n_active = count( array_filter( array( $district ? $district : $state, $cur_type, $service && aw_request_services(), $verified, $gov ) ) );
	$checks   = ( aw_verified_count() ? '<label class="kit-checkbox aw-dir-check"><input type="checkbox" name="verified" value="1"' . checked( $verified, true, false ) . '> Claimed only</label>' : '' )
		. ( function_exists( 'aw_gov_verified_count' ) && aw_gov_verified_count() ? '<label class="kit-checkbox aw-dir-check"><input type="checkbox" name="gov" value="1"' . checked( $gov, true, false ) . '> Verified only</label>' : '' );
	aw_use_component_style( 'aw-directory' ); // the search form (tag pages, the List filters block, the shortcode)
	// Row 1: search and Search. Then the filters: inline rows from 768, a "Filters" bottom sheet on phones (the Kits
	// sheet, kit.js), applied only with "Apply filters". On phones Filters and Near me share one row under the search
	// (owner's Directory mobile spec, 14 Nov 2026); Near me there opens the toolbar's Near me (aw.js), and shows only
	// where that toolbar is on the page. Without JavaScript the filters stay inline at every width.
	return '<form class="aw-dir-search kit-section-search" role="search" method="get" action="' . esc_url( aw_hub_url( 'aw_listing' ) ) . '">'
		. '<div class="aw-dir-row">' . $shared['q']
		. '<div class="aw-dir-submit"><button type="submit" class="kit-btn kit-btn--ico-search">Search</button></div></div>'
		. '<div class="aw-dir-actions"><details class="kit-filter-sheet aw-dir-sheet" open><summary class="aw-dir-sheet-open"><span class="kit-ico kit-ico--sliders" aria-hidden="true"></span>Filters' . ( $n_active ? ' (' . (int) $n_active . ')' : '' ) . '</summary>'
		. '<div class="kit-filters aw-dir-filters" role="group" aria-labelledby="aw-dir-sheet-h">'
		. '<div class="aw-dir-sheet-head"><p class="aw-dir-sheet-title" id="aw-dir-sheet-h">Filters</p><button type="button" class="aw-dir-sheet-x" data-aw-sheet-close aria-label="Close filters"><span class="kit-ico kit-ico--x" aria-hidden="true"></span></button></div>'
		. '<div class="aw-dir-selects">' . $shared['state']
		. $field( 'aw-dir-district', 'District', '<select id="aw-dir-district" name="district"' . ( $state ? '' : ' disabled' ) . '>' . $d_opts . '</select>', 'district' )
		. $field( 'aw-dir-type', 'Type', '<select id="aw-dir-type" name="tag[]">' . $t_opts . '</select>', 'type' )
		. $service . '</div>'
		. ( $checks ? '<fieldset class="aw-dir-checks"><legend class="screen-reader-text">Show only</legend>' . $checks . '</fieldset>' : '' )
		. '<div class="kit-filter-sheet-actions"><a class="kit-btn kit-btn--secondary kit-btn--label" href="' . esc_url( aw_hub_url( 'aw_listing' ) ) . '">Reset</a><button type="submit" class="kit-btn">Apply filters</button></div>'
		. '</div></details><button type="button" class="kit-btn kit-btn--secondary kit-btn--ico-map-pin aw-near-proxy" aria-controls="aw-near" hidden>Near me</button></div>' . ( function_exists( 'kits_fold_now' ) ? kits_fold_now( 'sheet' ) : '' ) // a sheet before the first paint on phones (no layout shift)
		. '<input type="hidden" name="aw_filter" value="1">'
		. '</form>';
}
add_shortcode( 'aw_directory_filter', 'aw_directory_filter' );


/* ---------- Contact score (sort "Most details first") ---------- */

/** How many ways to reach a listing: phone, WhatsApp, email, website. */
function aw_contact_score( $id ) {
	$n      = 0;
	$phones = function_exists( 'aw_listing_phones' ) ? aw_listing_phones( $id ) : array(); // the Phone numbers list
	$n     += $phones ? 1 : 0;
	$n     += in_array( true, wp_list_pluck( $phones, 'whatsapp' ), true ) ? 1 : 0;
	foreach ( array( 'aw_email', 'aw_website' ) as $k ) {
		$n += '' !== trim( (string) get_post_meta( $id, $k, true ) ) ? 1 : 0;
	}
	return $n;
}
/** Keep the score current when a contact field changes. */
function aw_contact_score_sync( $mid, $id, $key ) {
	if ( ( in_array( $key, array( 'aw_email', 'aw_website', 'aw_phones' ), true ) || 0 === strpos( (string) $key, 'aw_phones_' ) ) && 'aw_listing' === get_post_type( $id ) ) {
		update_post_meta( $id, 'aw_contact_score', aw_contact_score( $id ) );
	}
}
add_action( 'updated_post_meta', 'aw_contact_score_sync', 10, 3 );
add_action( 'added_post_meta', 'aw_contact_score_sync', 10, 3 );
// The one-time fill of every listing's contact score ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

/**
 * [aw_browse_states] Every state/UT with at least one listing, A–Z, with its count (Directory hub and home).
 */
function aw_browse_states() {
	if ( aw_is_directory_view() && ( get_query_var( 'aw_place' ) || get_query_var( 'aw_dir_type' ) || ! empty( $_GET['tag'] ) || ! empty( $_GET['q'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return ''; // the hub and the home page only
	}
	aw_use_component_style( 'aw-single' ); // aw-browse-states
	$items = '';
	foreach ( aw_place_states() as $st ) {
		$n = aw_state_count( $st );
		if ( $n ) {
			$items .= '<li><a href="' . esc_url( aw_directory_url( $st->slug ) ) . '"><span>' . esc_html( $st->name ) . '</span>' . kits_count( $n ) . '</a></li>';
		}
	}
	return $items ? '<nav class="aw-browse-states aw-browse-states--all" aria-labelledby="aw-browse-states-h"><h2 id="aw-browse-states-h">Browse by state</h2><ul>' . $items . '</ul></nav>' : '';
}

/** Listings in a state, its districts included (stored counts, places.php). */
function aw_state_count( $state ) {
	return aw_place_count( $state );
}


/**
 * The Directory's intro line for the current view (also its meta and share description):
 * hub, "… in {State}." or "… in {District}, {State}." (owner-approved copy, 8 Oct 2026).
 */
function aw_directory_intro() {
	$city = aw_current_place();
	if ( ! $city ) {
		return 'Animal welfare organisations, Gaushalas and government vets across India.';
	}
	if ( $city->parent ) {
		$state = aw_place_parent_state( $city );
		return sprintf( 'Animal welfare organisations, Gaushalas and government vets in %1$s, %2$s.', $city->name, $state->name );
	}
	return sprintf( 'Animal welfare organisations, Gaushalas and government vets in %s.', $city->name );
}

// A–Z with names that start with a digit last (owner, 12 Oct 2026: the 1962 units were filling the top of the Directory).
add_filter(
	'posts_orderby',
	function ( $orderby, $q ) {
		global $wpdb;
		if ( ! $q->get( 'aw_digits_last' ) || false === strpos( $orderby, "{$wpdb->posts}.post_title" ) ) {
			return $orderby;
		}
		return preg_replace( '/(' . preg_quote( "{$wpdb->posts}.post_title", '/' ) . ' (ASC|DESC))/', "({$wpdb->posts}.post_title REGEXP '^[0-9]') ASC, $1", $orderby, 1 );
	},
	10,
	2
);
