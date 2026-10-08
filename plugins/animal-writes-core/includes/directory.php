<?php
/**
 * Directory: /directory/, /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/, featured first.
 *
 * Singles live at /directory/org/{slug}/.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory place URLs: /directory/{state}/, /directory/{state}/{district}/, /directory/{state}/{district}/{type}/.
 * There are no state × type pages: /directory/{state}/?type= is a filtered state list (canonical: the state page).
 * Older patterns (/directory/{district}/, /directory/{place}/{type}/) get one 301 to the new URL.
 */
function aw_directory_rewrites() {
	add_rewrite_tag( '%aw_dir%', '(.+?)' );
	add_rewrite_tag( '%aw_type_slug%', '([^/]+)' );
	add_rewrite_rule( '^directory/(?!org/|page/|feed/)(.+?)/?$', 'index.php?aw_dir=$matches[1]', 'top' );
}
add_action( 'init', 'aw_directory_rewrites', 20 );

/** A place (state or district) by slug, cached for the request. */
function aw_dir_place( $slug ) {
	static $cache = array();
	if ( ! array_key_exists( $slug, $cache ) ) {
		$t              = get_term_by( 'slug', $slug, 'aw_city' );
		$cache[ $slug ] = $t && ! is_wp_error( $t ) ? $t : null;
	}
	return $cache[ $slug ];
}

/** "Veterinary" is one filter for people; the listings keep their separate veterinary types. */
const AW_VET_TYPES = array( 'veterinary-hospital', 'veterinary-dispensary', 'veterinary-clinic', 'mobile-veterinary-unit', 'veterinary-office', 'bird-hospital' );

/** A listing-type slug from a URL or filter: a real type, or "veterinary". */
function aw_listing_type_ok( $slug ) {
	return 'veterinary' === $slug || (bool) get_term_by( 'slug', $slug, 'aw_listing_type' );
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
	$t = get_term_by( 'slug', $slug, 'aw_listing_type' );
	return $t ? $t->name : '';
}

/** Name of the listing type the current directory page shows, if any. */
function aw_current_type_name() {
	$slug = (string) ( get_query_var( 'aw_type_slug' ) ? get_query_var( 'aw_type_slug' ) : get_query_var( 'aw_listing_type' ) );
	if ( '' === $slug ) {
		$slug = sanitize_title( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
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
			$keep = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'q', 'verified' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $keep ), $url ), 301, 'Animal Writes' );
			exit;
		};
		$out = array( 'post_type' => 'aw_listing' );
		$a   = isset( $seg[0] ) ? aw_dir_place( $seg[0] ) : null;
		$b   = $seg[1] ?? '';
		$c   = $seg[2] ?? '';
		if ( 1 === count( $seg ) && $a ) {
			if ( $a->parent ) {
				$go( aw_directory_url( $a->slug ) ); // old /directory/{district}/
			}
			$out['aw_city'] = $a->slug;
			$type           = sanitize_title( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $type && $type_ok( $type ) ) {
				$out['aw_listing_type'] = aw_listing_type_query( $type ); // filtered state list
			}
		} elseif ( 2 === count( $seg ) && $a ) {
			$bp = aw_dir_place( $b );
			if ( ! $a->parent && $bp && (int) $bp->parent === (int) $a->term_id ) {
				$out['aw_city'] = $bp->slug;
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
			$out['aw_city']         = $bp->slug;
			$out['aw_listing_type'] = aw_listing_type_query( $c );
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

/**
 * Is this a directory listing query (archive, city or city × type)?
 *
 * @param WP_Query $q Query.
 * @return bool
 */
function aw_is_directory_query( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return false;
	}
	return $q->is_post_type_archive( 'aw_listing' )
		|| $q->is_tax( 'aw_listing_type' )
		|| ( $q->is_tax( 'aw_city' ) && 'aw_listing' === $q->get( 'post_type' ) );
}

/**
 * Featured first, then alphabetical. Needs-reverify listings stay visible (flag is for ops).
 *
 * @param WP_Query $q Query.
 */
function aw_directory_order( $q ) {
	if ( ! aw_is_directory_query( $q ) ) {
		return;
	}
	$q->set( 'post_type', 'aw_listing' );
	// Featured first, then verified, then A–Z. No other listing is ranked above the rest. Every listing carries aw_featured; aw_verified may be missing.
	$q->set(
		'meta_query',
		array(
			'feat' => array( 'key' => 'aw_featured', 'type' => 'NUMERIC' ),
			'ver'  => array(
				'relation' => 'OR',
				'ver_yes'  => array( 'key' => 'aw_verified', 'type' => 'NUMERIC', 'compare' => 'EXISTS' ),
				array( 'key' => 'aw_verified', 'compare' => 'NOT EXISTS' ),
			),
		)
	);
	$order = array( 'feat' => 'DESC', 'ver_yes' => 'DESC', 'title' => 'ASC' );
	if ( 'details' === ( $_GET['sort'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		// "Most details first": listings with more ways to reach them (phone, WhatsApp, email, website) first.
		$mq          = (array) $q->get( 'meta_query' );
		$mq['score'] = array( 'key' => 'aw_contact_score', 'type' => 'NUMERIC' );
		$q->set( 'meta_query', $mq );
		$order = array( 'feat' => 'DESC', 'score' => 'DESC', 'title' => 'ASC' );
	}
	$q->set( 'orderby', $order );
	$q->set( 'posts_per_page', 24 );
}
add_action( 'pre_get_posts', 'aw_directory_order' );

/**
 * Front-end check: directory archive, city or city × type page.
 *
 * @return bool
 */
function aw_is_directory_view() {
	return is_post_type_archive( 'aw_listing' )
		|| is_tax( 'aw_listing_type' )
		|| ( is_tax( 'aw_city' ) && 'aw_listing' === get_query_var( 'post_type' ) );
}

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
	$city = get_query_var( 'aw_city' ) ? get_term_by( 'slug', get_query_var( 'aw_city' ), 'aw_city' ) : null;
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

/**
 * Same title in the document <title> for SEO.
 *
 * @param array $parts Title parts.
 * @return array
 */
function aw_directory_document_title( $parts ) {
	if ( aw_is_directory_view() ) {
		$parts['title'] = wp_strip_all_tags( aw_directory_title( $parts['title'] ?? '' ) );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'aw_directory_document_title' );

/**
 * Directory URL helper for themes/menus.
 *
 * @param string $city_slug State or district slug.
 * @param string $type_slug Optional listing type slug (district pages only; a state gets ?type=).
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
		return $type ? add_query_arg( 'type', $type, $url ) : $url; // no state × type pages
	}
	$state = get_term( $place->parent, 'aw_city' );
	return home_url( 'directory/' . $state->slug . '/' . $place->slug . '/' . ( $type ? $type . '/' : '' ) );
}

/* ---------- Search and filters: [aw_directory_filter] ---------- */

/**
 * Turn the filter form (state, district, type) into the clean directory URL, keeping q and verified.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! aw_is_directory_view() || ! isset( $_GET['aw_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$g     = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$city  = sanitize_title( $g['district'] ?? '' ) ? sanitize_title( $g['district'] ) : sanitize_title( $g['state'] ?? '' );
		$type  = sanitize_title( $g['type'] ?? '' );
		$base  = $city ? aw_directory_url( $city, $type ) : ( $type ? ( 'veterinary' === $type ? add_query_arg( 'type', 'veterinary', get_post_type_archive_link( 'aw_listing' ) ) : get_term_link( $type, 'aw_listing_type' ) ) : get_post_type_archive_link( 'aw_listing' ) );
		$extra = array_filter(
			array(
				'q'        => sanitize_text_field( $g['q'] ?? '' ),
				'verified' => empty( $g['verified'] ) ? '' : '1',
			)
		);
		wp_safe_redirect( add_query_arg( $extra, is_wp_error( $base ) ? get_post_type_archive_link( 'aw_listing' ) : $base ) );
		exit;
	}
);

/**
 * Name search and verified-only on directory queries.
 *
 * @param WP_Query $q Query.
 */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! aw_is_directory_query( $q ) ) {
			return;
		}
		$gtype = sanitize_title( wp_unslash( $_GET['type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $gtype && ! $q->get( 'aw_listing_type' ) && aw_listing_type_ok( $gtype ) ) {
			$q->set( 'aw_listing_type', aw_listing_type_query( $gtype ) ); // a filtered list; canonical stays the unfiltered page
		}
		$text = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $text ) {
			$q->set( 's', $text );
			$q->set( 'search_columns', array( 'post_title' ) );
		}
		if ( ! empty( $_GET['verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$mq = (array) $q->get( 'meta_query' );
			$mq['only_verified'] = array( 'key' => 'aw_verified', 'value' => '1' );
			$q->set( 'meta_query', $mq );
		}
	},
	20
);

/** Number of verified listings (cached). "Verified only" appears once there is at least one. */
function aw_verified_count() {
	$n = get_transient( 'aw_verified_count' );
	if ( false === $n ) {
		$n = count( get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'aw_verified', 'meta_value' => '1' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
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
function aw_directory_filter() {
	$cur_city = get_query_var( 'aw_city' ) ? get_term_by( 'slug', get_query_var( 'aw_city' ), 'aw_city' ) : null;
	$cur_type = (string) get_query_var( 'aw_listing_type' );
	$state    = $cur_city ? ( $cur_city->parent ? get_term( $cur_city->parent, 'aw_city' ) : $cur_city ) : null;
	$district = $cur_city && $cur_city->parent ? $cur_city : null;
	$text     = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$verified = ! empty( $_GET['verified'] ); // phpcs:ignore WordPress.Security.NonceVerification

	$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'name' ) );
	$all    = get_transient( 'aw_dir_districts_n' );
	if ( false === $all ) {
		$all = array();
		foreach ( get_terms( array( 'taxonomy' => 'aw_city', 'hide_empty' => true, 'orderby' => 'name', 'childless' => true ) ) as $k ) {
			if ( $k->parent ) {
				$all[ $k->parent ][] = array( $k->slug, $k->name, (int) $k->count );
			}
		}
		set_transient( 'aw_dir_districts_n', $all, DAY_IN_SECONDS );
	}
	$s_opts = '<option value="">All states</option>';
	$d_opts = '<option value="" data-none="Choose a state first" data-all="All districts">' . ( $state ? 'All districts' : 'Choose a state first' ) . '</option>';
	foreach ( is_wp_error( $states ) ? array() : $states as $st ) {
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
	foreach ( get_terms( array( 'taxonomy' => 'aw_listing_type', 'hide_empty' => true, 'orderby' => 'name' ) ) as $t ) {
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
	return '<form class="aw-dir-search" role="search" method="get" action="' . esc_url( get_post_type_archive_link( 'aw_listing' ) ) . '">'
		. $field( 'aw-dir-q', 'Name', '<span class="aw-ico aw-ico--search kit-field-icon" aria-hidden="true"></span><input type="search" id="aw-dir-q" name="q" value="' . esc_attr( $text ) . '" placeholder="Blue Cross" autocomplete="off">', 'q' )
		. $field( 'aw-dir-state', 'State', '<select id="aw-dir-state" name="state">' . $s_opts . '</select>', 'state' )
		. $field( 'aw-dir-district', 'District', '<select id="aw-dir-district" name="district"' . ( $state ? '' : ' disabled' ) . '>' . $d_opts . '</select>', 'district' )
		. $field( 'aw-dir-type', 'Type', '<select id="aw-dir-type" name="type">' . $t_opts . '</select>', 'type' )
		. ( aw_verified_count() ? '<label class="kit-checkbox aw-dir-check"><input type="checkbox" name="verified" value="1"' . checked( $verified, true, false ) . '> Verified only</label>' : '' )
		. '<div class="aw-dir-submit"><input type="hidden" name="aw_filter" value="1"><button type="submit" class="kit-btn">Search</button></div>'
		. '</form>';
}
add_shortcode( 'aw_directory_filter', 'aw_directory_filter' );


/* ---------- Contact score (sort "Most details first") ---------- */

/** How many ways to reach a listing: phone, WhatsApp, email, website. */
function aw_contact_score( $id ) {
	$n = 0;
	foreach ( array( 'aw_phone', 'aw_whatsapp', 'aw_email', 'aw_website' ) as $k ) {
		$n += '' !== trim( (string) get_post_meta( $id, $k, true ) ) ? 1 : 0;
	}
	return $n;
}
/** Keep the score current when a contact field changes. */
function aw_contact_score_sync( $mid, $id, $key ) {
	if ( in_array( $key, array( 'aw_phone', 'aw_whatsapp', 'aw_email', 'aw_website' ), true ) && 'aw_listing' === get_post_type( $id ) ) {
		update_post_meta( $id, 'aw_contact_score', aw_contact_score( $id ) );
	}
}
add_action( 'updated_post_meta', 'aw_contact_score_sync', 10, 3 );
add_action( 'added_post_meta', 'aw_contact_score_sync', 10, 3 );
// Every listing needs the score so the sort can include it; fill it once.
add_action(
	'admin_init',
	function () {
		if ( '1' === get_option( 'aw_contact_score_done' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'aw_listing', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			update_post_meta( $id, 'aw_contact_score', aw_contact_score( $id ) );
		}
		update_option( 'aw_contact_score_done', '1', false );
	}
);

/**
 * [aw_browse_states] Every state/UT with at least one listing, A–Z, with its count (Directory hub and home).
 */
function aw_browse_states() {
	if ( aw_is_directory_view() && ( get_query_var( 'aw_city' ) || get_query_var( 'aw_listing_type' ) || ! empty( $_GET['q'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return ''; // the hub and the home page only
	}
	$terms = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
	$items = '';
	foreach ( is_wp_error( $terms ) ? array() : $terms as $st ) {
		$n = aw_state_count( $st );
		if ( $n ) {
			$items .= '<li><a href="' . esc_url( aw_directory_url( $st->slug ) ) . '"><span>' . esc_html( $st->name ) . '</span>' . kits_count( $n ) . '</a></li>';
		}
	}
	return $items ? '<nav class="aw-browse-states" aria-labelledby="aw-browse-states-h"><h2 id="aw-browse-states-h">Browse by state</h2><ul>' . $items . '</ul></nav>' : '';
}
add_shortcode( 'aw_browse_states', 'aw_browse_states' );

/** Listings in a state, its districts included (cached for an hour). */
function aw_state_count( $state ) {
	$all = get_transient( 'aw_state_counts' );
	if ( false === $all ) {
		$all = array();
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT tr.object_id id, tt.term_id, tt.parent FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'aw_city' JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'aw_listing' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $r ) {
			$all[ $r->parent ? (int) $r->parent : (int) $r->term_id ][ (int) $r->id ] = 1;
		}
		$all = array_map( 'count', $all );
		set_transient( 'aw_state_counts', $all, HOUR_IN_SECONDS );
	}
	return (int) ( $all[ $state->term_id ] ?? 0 );
}


/**
 * The Directory's intro line for the current view (also its meta and share description):
 * hub, "… in {State}." or "… in {District}, {State}." (owner-approved copy, 8 Oct 2026).
 */
function aw_directory_intro() {
	$city = get_query_var( 'aw_city' ) ? get_term_by( 'slug', (string) get_query_var( 'aw_city' ), 'aw_city' ) : null;
	if ( ! $city ) {
		return 'Animal welfare organisations, gaushalas and government vets across India.';
	}
	if ( $city->parent ) {
		$state = get_term( $city->parent, 'aw_city' );
		return sprintf( 'Animal welfare organisations, gaushalas and government vets in %1$s, %2$s.', $city->name, $state->name );
	}
	return sprintf( 'Animal welfare organisations, gaushalas and government vets in %s.', $city->name );
}
add_filter( 'kits_seo_description', fn( $d ) => ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() && ! get_query_var( 'aw_listing_type' ) && empty( $_GET['q'] ) ) ? aw_directory_intro() : $d ); // phpcs:ignore WordPress.Security.NonceVerification
