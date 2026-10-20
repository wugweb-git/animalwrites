<?php
/**
 * Breadcrumbs: Rank Math's trail is the one source. Rank Math does not know the site's places and hubs, so its items
 * are replaced here (rank_math/frontend/breadcrumb/items) by the trail below; the site's breadcrumb block
 * (animal-writes-core breadcrumbs.php) shows Rank Math's items, and Rank Math prints them as the page's one
 * BreadcrumbList.
 *
 * Organisation:      Home › Directory › State › District › Organisation
 * District × type:   Home › Directory › State › District › Type
 * News, Library:     Home › Section › primary category
 * Other items:       Home › Section › Item
 * Section hub:       Home › Section
 * Filtered hub:      Home › Section › Category or tag (one ?category_name= or ?tag=; filtered-views.php)
 * Category:          Home › Categories › Category
 * Tag:               Home › Section › Tag
 * Page:              Home › (parent) › Page
 *
 * An item's label is the "Breadcrumb Title" typed in Rank Math (Advanced tab), when there is one.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'aw_breadcrumb_label',
	function ( $label, $id ) {
		$b = trim( (string) get_post_meta( (int) $id, 'rank_math_breadcrumb_title', true ) );
		return '' !== $b ? $b : $label;
	},
	20,
	2
);

/**
 * The trail for the current page: list of [name, url]; the last entry is the page itself. Empty on the front page,
 * a 404 and anything with fewer than two steps.
 *
 * @return array
 */
function aw_seo_breadcrumb_trail() {
	static $trail = null;
	if ( null !== $trail ) {
		return $trail;
	}
	$trail = array();
	if ( is_404() || is_front_page() || ! function_exists( 'aw_post_types' ) ) {
		return $trail;
	}
	$dir   = array( 'Directory', home_url( '/directory/' ) );
	$place = function ( $term ) {
		$out = array();
		if ( $term && $term->parent && function_exists( 'aw_place_by_id' ) ) {
			$st    = aw_place_by_id( $term->parent );
			$out[] = array( $st->name, aw_directory_url( $st->slug ) );
		}
		if ( $term ) {
			$out[] = array( $term->name, aw_directory_url( $term->slug ) );
		}
		return $out;
	};

	if ( is_singular( 'aw_listing' ) ) {
		$id    = get_queried_object_id();
		$best  = function_exists( 'aw_item_place' ) ? aw_item_place( $id ) : null; // the district if there is one, else the state
		$trail = array_merge( array( $dir ), $place( $best ), array( array( apply_filters( 'aw_breadcrumb_label', get_the_title( $id ), $id ), get_permalink( $id ) ) ) );
	} elseif ( is_singular( array_keys( aw_post_types() ) ) ) {
		$id      = get_queried_object_id();
		$type    = get_post_type( $id );
		$obj     = get_post_type_object( $type );
		$trail[] = array( $obj->labels->name, aw_hub_url( $type ) );
		$cat     = in_array( $type, array( 'aw_news', 'aw_legal' ), true ) && function_exists( 'aw_primary_category' ) ? aw_primary_category( $id ) : null;
		if ( $cat ) { // News and Library (owner, 20 Oct 2026): Home › Section › primary category, the section's list of it when indexed
			$n       = function_exists( 'aw_seo_filtered_count' ) ? aw_seo_filtered_count( $type, 'category_name', $cat->slug ) : 0;
			$trail[] = array( $cat->name, $n >= AW_FILTERED_MIN_ITEMS ? add_query_arg( 'category_name', rawurlencode( $cat->slug ), aw_hub_url( $type ) ) : get_term_link( $cat ) );
		} else {
			$trail[] = array( apply_filters( 'aw_breadcrumb_label', get_the_title( $id ), $id ), get_permalink( $id ) );
		}
	} elseif ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() && aw_current_place() ) {
		$city  = aw_current_place();
		$trail = array_merge( array( $dir ), $place( $city ) );
		$tslug = (string) get_query_var( 'aw_type_slug' );
		if ( $tslug && $city && $city->parent ) { // district × type; a state's ?type= list is the state page
			$trail[] = array( aw_listing_type_name( $tslug ), aw_directory_url( $city->slug, $tslug ) );
		}
	} elseif ( aw_seo_is_category_page() ) {
		$all   = aw_seo_categories_url();
		$trail = array_merge( $all ? array( array( 'Categories', $all ) ) : array(), array( array( single_term_title( '', false ), get_term_link( get_queried_object() ) ) ) );
	} elseif ( is_tag() ) { // Home › the tag's section › Tag
		$term = get_queried_object();
		$sec  = function_exists( 'aw_tag_section' ) ? aw_tag_section( $term->slug ) : '';
		$obj  = $sec ? get_post_type_object( $sec ) : null;
		$trail = array_merge( $obj ? array( array( $obj->labels->name, aw_hub_url( $sec ) ) ) : array(), array( array( single_term_title( '', false ), get_term_link( $term ) ) ) );
	} elseif ( is_post_type_archive( array_keys( aw_post_types() ) ) ) {
		$obj   = get_queried_object();
		$trail = array( array( $obj->labels->name, get_post_type_archive_link( $obj->name ) ) );
	} elseif ( is_page() ) {
		$id = get_queried_object_id();
		foreach ( array_reverse( get_post_ancestors( $id ) ) as $a ) {
			$trail[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$trail[] = array( apply_filters( 'aw_breadcrumb_label', get_the_title( $id ), $id ), get_permalink( $id ) );
		$view    = function_exists( 'aw_seo_filtered_named' ) && aw_seo_filtered_named() ? aw_seo_filtered_view() : null;
		if ( $view ) { // a hub filtered by one category or tag: Home › Section › Category
			$trail[] = array( $view['name'], add_query_arg( $view['key'], rawurlencode( $view['slug'] ), aw_hub_canonical() ) );
		}
	}
	$trail = $trail ? array_merge( array( array( 'Home', home_url( '/' ) ) ), $trail ) : array();
	if ( count( $trail ) < 2 ) {
		$trail = array();
	}
	$trail = array_map( fn( $c ) => array( aw_seo_plain( $c[0] ), $c[1] ), $trail );
	return $trail;
}

// Rank Math's items are the trail above (its own would miss the Directory places and the section hubs).
add_filter(
	'rank_math/frontend/breadcrumb/items',
	function ( $crumbs ) {
		return is_admin() ? $crumbs : aw_seo_breadcrumb_trail();
	},
	20
);
