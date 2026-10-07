<?php
/**
 * Directory: /directory/, /directory/{city}/, /directory/{city}/{type}/ with featured-first ordering.
 *
 * Singles live at /directory/org/{slug}/.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * City and city × type rewrite rules.
 */
function aw_directory_rewrites() {
	$skip = '(?!org/|page/|feed/)';
	add_rewrite_rule( '^directory/' . $skip . '([^/]+)/([^/]+)/page/([0-9]+)/?$', 'index.php?post_type=aw_listing&aw_city=$matches[1]&aw_listing_type=$matches[2]&paged=$matches[3]', 'top' );
	add_rewrite_rule( '^directory/' . $skip . '([^/]+)/([^/]+)/?$', 'index.php?post_type=aw_listing&aw_city=$matches[1]&aw_listing_type=$matches[2]', 'top' );
	add_rewrite_rule( '^directory/' . $skip . '([^/]+)/page/([0-9]+)/?$', 'index.php?post_type=aw_listing&aw_city=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( '^directory/' . $skip . '([^/]+)/?$', 'index.php?post_type=aw_listing&aw_city=$matches[1]', 'top' );
}
add_action( 'init', 'aw_directory_rewrites', 20 );

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
	$q->set(
		'orderby',
		array(
			'feat'    => 'DESC',
			'ver_yes' => 'DESC',
			'title'   => 'ASC',
		)
	);
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
	$type = get_query_var( 'aw_listing_type' ) ? get_term_by( 'slug', get_query_var( 'aw_listing_type' ), 'aw_listing_type' ) : null;

	if ( $city && $type ) {
		/* translators: 1: listing type, 2: city. */
		return sprintf( __( '%1$s in %2$s', 'animal-writes-core' ), $type->name, $city->name );
	}
	if ( $city ) {
		/* translators: %s: city. */
		return sprintf( __( 'Animal welfare directory: %s', 'animal-writes-core' ), $city->name );
	}
	if ( $type ) {
		return $type->name;
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
 * @param string $city_slug City slug.
 * @param string $type_slug Optional listing type slug.
 * @return string
 */
function aw_directory_url( $city_slug, $type_slug = '' ) {
	$path = 'directory/' . sanitize_title( $city_slug ) . '/';
	if ( $type_slug ) {
		$path .= sanitize_title( $type_slug ) . '/';
	}
	return home_url( $path );
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
		$base  = $city ? aw_directory_url( $city, $type ) : ( $type ? get_term_link( $type, 'aw_listing_type' ) : get_post_type_archive_link( 'aw_listing' ) );
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

/**
 * Search bar with state, district, type and verified filters. Works without JavaScript;
 * with it, the district list narrows to the chosen state.
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

	$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
	$all    = get_transient( 'aw_dir_districts' );
	if ( false === $all ) {
		$all = array();
		foreach ( get_terms( array( 'taxonomy' => 'aw_city', 'hide_empty' => false, 'orderby' => 'name', 'childless' => true, 'parent' => '' ) ) as $k ) {
			if ( $k->parent ) {
				$all[ $k->parent ][] = $k;
			}
		}
		set_transient( 'aw_dir_districts', $all, DAY_IN_SECONDS );
	}
	$s_opts = '<option value="">All states</option>';
	$d_opts = '<option value="">All districts</option>';
	foreach ( $states as $st ) {
		$s_opts .= '<option value="' . esc_attr( $st->slug ) . '"' . selected( $state && $state->term_id === $st->term_id, true, false ) . '>' . esc_html( $st->name ) . '</option>';
		$kids    = $all[ $st->term_id ] ?? array();
		if ( $kids ) {
			$d_opts .= '<optgroup label="' . esc_attr( $st->name ) . '" data-state="' . esc_attr( $st->slug ) . '">';
			foreach ( $kids as $k ) {
				$d_opts .= '<option value="' . esc_attr( $k->slug ) . '"' . selected( $district && $district->term_id === $k->term_id, true, false ) . '>' . esc_html( $k->name ) . '</option>';
			}
			$d_opts .= '</optgroup>';
		}
	}
	$t_opts = '<option value="">All types</option>';
	foreach ( get_terms( array( 'taxonomy' => 'aw_listing_type', 'hide_empty' => false, 'orderby' => 'name' ) ) as $t ) {
		$t_opts .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $cur_type, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
	}
	$active = $cur_city || $cur_type || $text || $verified;

	return '<form class="aw-dir-filter" role="search" method="get" action="' . esc_url( get_post_type_archive_link( 'aw_listing' ) ) . '">'
		. '<input type="hidden" name="aw_filter" value="1">'
		. '<p class="aw-dir-q"><label for="aw-dir-q">Search by name</label><input type="search" id="aw-dir-q" name="q" value="' . esc_attr( $text ) . '" placeholder="For example, Blue Cross" autocomplete="off"></p>'
		. '<p><label for="aw-dir-state">State</label><select id="aw-dir-state" name="state">' . $s_opts . '</select></p>'
		. '<p><label for="aw-dir-district">District</label><select id="aw-dir-district" name="district">' . $d_opts . '</select></p>'
		. '<p><label for="aw-dir-type">Type</label><select id="aw-dir-type" name="type">' . $t_opts . '</select></p>'
		. '<p class="aw-dir-check"><label class="aw-check"><input type="checkbox" name="verified" value="1"' . checked( $verified, true, false ) . '> Verified only</label></p>'
		. '<p class="aw-dir-actions"><button type="submit" class="wp-element-button">Search</button>'
		. ( $active ? ' <a class="aw-dir-clear" href="' . esc_url( get_post_type_archive_link( 'aw_listing' ) ) . '">Clear filters</a>' : '' ) . '</p>'
		. '</form>';
}
add_shortcode( 'aw_directory_filter', 'aw_directory_filter' );
