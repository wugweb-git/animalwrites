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
	// Every listing carries aw_featured (see aw_backfill_featured_flag), so an inner join is safe.
	$q->set( 'meta_key', 'aw_featured' );
	$q->set(
		'orderby',
		array(
			'meta_value_num' => 'DESC',
			'title'          => 'ASC',
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
