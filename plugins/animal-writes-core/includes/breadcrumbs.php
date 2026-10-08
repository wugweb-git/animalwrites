<?php
/**
 * Breadcrumbs (restructure Phase 5): one trail per page, shown above the page title and given to search engines
 * as BreadcrumbList (schema.php).
 *
 * Organisation:      Home › Directory › State › District › Organisation
 * District × type:   Home › Directory › State › District › Type
 * Other items:       Home › Section › Item
 * Section hub:       Home › Section
 * Topic:             Home › Topics › Topic
 * Page:              Home › (parent) › Page
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The trail for the current page: list of [name, url]; the last entry is the page itself. Empty when there is none. */
function aw_breadcrumbs() {
	static $trail = null;
	if ( null !== $trail ) {
		return $trail;
	}
	$trail = array();
	$dir   = array( 'Directory', home_url( '/directory/' ) );
	$place = function ( $term ) {
		$out = array();
		if ( $term && $term->parent ) {
			$st    = get_term( $term->parent, 'aw_city' );
			$out[] = array( $st->name, aw_directory_url( $st->slug ) );
		}
		if ( $term ) {
			$out[] = array( $term->name, aw_directory_url( $term->slug ) );
		}
		return $out;
	};

	if ( is_singular( 'aw_listing' ) ) {
		$id    = get_queried_object_id();
		$terms = wp_get_object_terms( $id, 'aw_city' );
		$best  = null;
		foreach ( $terms as $t ) {
			if ( ! $best || ( $t->parent && ! $best->parent ) ) {
				$best = $t; // the district if there is one, else the state
			}
		}
		$trail = array_merge( array( $dir ), $place( $best ), array( array( get_the_title( $id ), get_permalink( $id ) ) ) );
	} elseif ( is_singular( array_keys( aw_post_types() ) ) ) {
		$id   = get_queried_object_id();
		$type = get_post_type( $id );
		$obj  = get_post_type_object( $type );
		$trail[] = array( $obj->labels->name, get_post_type_archive_link( $type ) );
		$trail[] = array( get_the_title( $id ), get_permalink( $id ) );
	} elseif ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() && get_query_var( 'aw_city' ) ) {
		$city  = get_term_by( 'slug', (string) get_query_var( 'aw_city' ), 'aw_city' );
		$trail = array_merge( array( $dir ), $place( $city ) );
		$tslug = (string) get_query_var( 'aw_type_slug' );
		if ( $tslug && $city && $city->parent ) { // district × type; a state's ?type= list is the state page
			$trail[] = array( aw_listing_type_name( $tslug ), aw_directory_url( $city->slug, $tslug ) );
		}
	} elseif ( is_tax( 'aw_topic' ) ) {
		$trail = array( array( 'Topics', home_url( '/topics/' ) ), array( single_term_title( '', false ), get_term_link( get_queried_object() ) ) );
	} elseif ( is_post_type_archive( array_keys( aw_post_types() ) ) ) {
		$obj   = get_queried_object();
		$trail = array( array( $obj->labels->name, get_post_type_archive_link( $obj->name ) ) );
	} elseif ( is_page() && ! is_front_page() ) {
		$id = get_queried_object_id();
		foreach ( array_reverse( get_post_ancestors( $id ) ) as $a ) {
			$trail[] = array( get_the_title( $a ), get_permalink( $a ) );
		}
		$trail[] = array( get_the_title( $id ), get_permalink( $id ) );
	}
	// Every trail starts at Home (Phase 11 B.15); the front page and 404 have none.
	$trail = $trail ? array_merge( array( array( 'Home', home_url( '/' ) ) ), $trail ) : array();
	if ( count( $trail ) < 2 ) {
		$trail = array();
	}
	return $trail;
}

/** The visible trail. */
function aw_breadcrumbs_html() {
	$trail = aw_breadcrumbs();
	if ( ! $trail ) {
		return '';
	}
	$last = count( $trail ) - 1;
	$html = '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol>';
	foreach ( $trail as $i => $c ) {
		$html .= $i === $last
			? '<li><span aria-current="page">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</span></li>'
			: '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</a></li>';
	}
	return $html . '</ol></nav>';
}

/** The trail, once per page ('' after the first call): the section band places it itself, inside the band. */
function aw_breadcrumbs_claim() {
	static $done = false;
	if ( $done || is_admin() || ! aw_breadcrumbs() ) {
		return '';
	}
	$done = true;
	return aw_breadcrumbs_html();
}

/** Put the trail once, just before the first heading area of the page (page title or news source row). */
function aw_breadcrumbs_prepend( $html ) {
	return aw_breadcrumbs_claim() . $html;
}
add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		return 1 === (int) ( $block['attrs']['level'] ?? 2 ) ? aw_breadcrumbs_prepend( $html ) : $html;
	},
	10,
	2
);
// On item pages the topic chips sit above the title: the trail goes before them, so it is always first.
add_filter(
	'render_block_core/post-terms',
	function ( $html, $block, $instance ) {
		if ( ! is_singular() || isset( $instance->context['queryId'] ) ) { // the page's own chips, not those in lists
			return $html;
		}
		return aw_breadcrumbs_prepend( $html );
	},
	10,
	3
);
add_filter(
	'do_shortcode_tag',
	function ( $html, $tag ) {
		return 'aw_source_row' === $tag ? aw_breadcrumbs_prepend( $html ) : $html; // section heads place it inside the band
	},
	10,
	2
);
