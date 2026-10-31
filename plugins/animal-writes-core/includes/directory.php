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
	$raw = wp_unslash( $_GET['tag'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
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
			wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $keep ), $url ), 301, 'Animal Writes' );
			exit;
		};
		$out = array( 'pagename' => 'directory' ); // the Directory Page; its list reads the place and type (hubs.php)
		$a   = isset( $seg[0] ) ? aw_dir_place( $seg[0] ) : null;
		$b   = $seg[1] ?? '';
		$c   = $seg[2] ?? '';
		if ( 1 === count( $seg ) && $a ) {
			if ( $a->parent ) {
				$go( aw_directory_url( $a->slug ) ); // old /directory/{district}/
			}
			$out['aw_place'] = $a->slug;
			$type           = aw_request_type();
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
			$out['aw_place']        = $bp->slug;
			$out['aw_dir_type']     = aw_listing_type_query( $c );
			$out['aw_type_slug']    = $c;
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
	$mq = (array) ( $v['meta_query'] ?? array() );
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
		$order = array( 'ver_yes' => 'DESC', 'title' => 'ASC' );
	}
	if ( 'details' === ( $_GET['sort'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
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

/** H1 of the Directory view: "Directory", "Help in Kerala", "Gaushala in Ernakulam" or the type. */
function aw_directory_h1() {
	$city  = aw_current_place();
	$tname = aw_current_type_name();
	if ( ! $city && ! $tname ) {
		return get_the_title( get_queried_object_id() );
	}
	return $tname ? ( $city ? $tname . ' in ' . $city->name : $tname ) : 'Help in ' . $city->name;
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
		return preg_replace( '#(<p[^>]*kit-section-desc[^>]*>)(.*?)(</p>)#s', '$1' . esc_html( aw_directory_intro() ) . '$3', $html, 1 );
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
		// A 6-digit PIN code in the search box opens that PIN's district (or state) list.
		$pin = preg_replace( '/\s/', '', (string) ( $g['q'] ?? '' ) );
		if ( preg_match( '/^[1-9]\d{5}$/', $pin ) ) {
			$r = aw_pin_lookup( $pin );
			if ( is_array( $r ) ) {
				$g['state']    = $r['state_slug'];
				$g['district'] = $r['district_slug'];
				$g['q']        = '';
				$g['aw_filter'] = 1;
			}
		}
		if ( ! isset( $g['aw_filter'] ) ) {
			return;
		}
		$city  = sanitize_title( $g['district'] ?? '' ) ? sanitize_title( $g['district'] ) : sanitize_title( $g['state'] ?? '' );
		$tags  = array_values( array_filter( array_map( 'sanitize_title', (array) ( $g['tag'] ?? array() ) ) ) );
		$type  = (string) current( array_filter( $tags, 'aw_listing_type_ok' ) ?: array( '' ) );
		$more  = array_values( array_diff( $tags, array( $type ) ) ); // services
		$base  = $city ? aw_directory_url( $city, $type ) : ( $type ? add_query_arg( 'tag', $type, aw_hub_url( 'aw_listing' ) ) : aw_hub_url( 'aw_listing' ) );
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
	$s_opts = '<option value="">All states</option>';
	$d_opts = '<option value="" data-none="Choose a state first" data-all="All districts">' . ( $state ? 'All districts' : 'Choose a state first' ) . '</option>';
	foreach ( $states as $st ) {
		$s_opts .= '<option value="' . esc_attr( $st->slug ) . '"' . selected( $state && $state->term_id === $st->term_id, true, false ) . '>' . esc_html( $st->name ) . '</option>';
		$kids    = $all[ $st->term_id ] ?? array();
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
		$key            = in_array( $t->slug, AW_VET_TYPES, true ) ? 'veterinary' : $t->slug;
		$opts[ $key ][0] = 'veterinary' === $key ? 'Veterinary' : $t->name;
		$opts[ $key ][1] = ( $opts[ $key ][1] ?? 0 ) + (int) $t->count;
	}
	uasort( $opts, fn( $a, $b ) => strcmp( $a[0], $b[0] ) );
	$cur_type = (string) ( get_query_var( 'aw_type_slug' ) ? get_query_var( 'aw_type_slug' ) : ( false !== strpos( $cur_type, ',' ) ? 'veterinary' : $cur_type ) );
	$t_opts   = '<option value="">All types</option>';
	foreach ( $opts as $slug => $o ) {
		$t_opts .= '<option value="' . esc_attr( $slug ) . '"' . selected( $cur_type, $slug, false ) . '>' . esc_html( $o[0] . ' (' . number_format_i18n( $o[1] ) . ')' ) . '</option>';
	}
	$field = fn( $id, $label, $control, $cls = '' ) => '<div class="kit-field aw-dir-' . $cls . '"><label for="' . $id . '">' . $label . '</label><div class="kit-field-control">' . $control . '</div></div>';
	return '<form class="aw-dir-search kit-section-search" role="search" method="get" action="' . esc_url( aw_hub_url( 'aw_listing' ) ) . '">'
		. $field( 'aw-dir-q', 'Name or PIN code', '<span class="kit-ico kit-ico--search kit-field-icon" aria-hidden="true"></span><input type="search" id="aw-dir-q" name="q" value="' . esc_attr( $text ) . '" placeholder="Blue Cross" autocomplete="off"><button type="button" class="aw-dir-filter-toggle' . ( $state || $cur_type || $verified || $gov ? ' has-filters' : '' ) . '" aria-label="Filters" aria-controls="aw-dir-more" aria-expanded="' . ( $state || $cur_type || $verified || $gov ? 'true' : 'false' ) . '"><span class="kit-ico kit-ico--sliders" aria-hidden="true"></span></button>', 'q' )
		. '<details class="aw-dir-more" id="aw-dir-more" data-kit-fold="767"' . ( $state || $cur_type || $verified || $gov ? ' data-kit-keep-open' : '' ) . ' open><summary class="screen-reader-text">Filters</summary><div class="aw-dir-more-body">' // phones: one field and "Filters"
		. $field( 'aw-dir-state', 'State', '<select id="aw-dir-state" name="state">' . $s_opts . '</select>', 'state' )
		. $field( 'aw-dir-district', 'District', '<select id="aw-dir-district" name="district"' . ( $state ? '' : ' disabled' ) . '>' . $d_opts . '</select>', 'district' )
		. $field( 'aw-dir-type', 'Type', '<select id="aw-dir-type" name="tag[]">' . $t_opts . '</select>', 'type' )
		. aw_directory_service_field( $field )
		. ( aw_verified_count() ? '<label class="kit-checkbox aw-dir-check"><input type="checkbox" name="verified" value="1"' . checked( $verified, true, false ) . '> Claimed only</label>' : '' )
		. ( function_exists( 'aw_gov_verified_count' ) && aw_gov_verified_count() ? '<label class="kit-checkbox aw-dir-check"><input type="checkbox" name="gov" value="1"' . checked( $gov, true, false ) . '> Verified only</label>' : '' )
		. '</div></details>' . ( function_exists( 'kits_fold_now' ) ? kits_fold_now( 'fold' ) : '' ) // folded before the first paint on phones (no layout shift)
		. '<div class="aw-dir-submit"><input type="hidden" name="aw_filter" value="1"><button type="submit" class="kit-btn kit-btn--brand">Search</button></div>'
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
	$items = '';
	foreach ( aw_place_states() as $st ) {
		$n = aw_state_count( $st );
		if ( $n ) {
			$items .= '<li><a href="' . esc_url( aw_directory_url( $st->slug ) ) . '"><span>' . esc_html( $st->name ) . '</span>' . kits_count( $n ) . '</a></li>';
		}
	}
	return $items ? '<nav class="aw-browse-states" aria-labelledby="aw-browse-states-h"><h2 id="aw-browse-states-h">Browse by state</h2><ul>' . $items . '</ul></nav>' : '';
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
