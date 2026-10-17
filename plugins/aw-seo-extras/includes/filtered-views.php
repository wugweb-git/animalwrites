<?php
/**
 * Filtered hub lists (owner approved, 15 Oct 2026): /news/?category_name=wildlife, /library/?tag=rule …
 *
 * - One filter with one value, ?category_name= or ?tag=, on a section hub (not a Directory place) is a page of its own
 *   when it holds 3 or more items: its own title and description, a self-canonical, a breadcrumb step and an entry in
 *   the "filtered" sitemap. With fewer items it is noindex (links followed).
 * - ?tag= on its own section hub lists the same items as the tag page (/tag/{slug}/, indexed from 3 items, its own
 *   sitemap entry), so such a view points its canonical at the tag page instead of competing with it.
 * - Several filters at once (?category_name=a&state=b) or several values (?tag=a,b) are noindex: the combinations
 *   would multiply without end. A parameter the hub does not filter by is noindex too.
 * - ?state= and ?publisher= (one value) follow the ?category_name= rule (16 Oct 2026): from 3 items their own title
 *   ("Animal Welfare News from Kerala", "Animal Welfare News from LiveLaw"), description, breadcrumb step,
 *   self-canonical and filtered-sitemap.xml entry; fewer, noindex. They had the hub's own title before, so each one
 *   duplicated it.
 * - A view kept out of the index has the section page (without filters) as its canonical URL (owner, 17 Oct 2026).
 *   Rank Math prints no canonical tag on a noindex page, so the value shows in its data (og:url), not as a tag.
 * - ?q= (a name or word search inside a list, the Directory's included) is a site search result: noindex, links
 *   followed (Google's guidance on internal search result pages).
 * - Any other single filter keeps the earlier rule (indexed, canonical with the filter).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fewest items for a filtered list to be indexed. */
const AW_FILTERED_MIN_ITEMS = 3;

/** Hubs whose ?category_name= views can be indexed (Directory listings have no categories as a filter). */
const AW_FILTERED_CATEGORY_HUBS = array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_campaign' );

/**
 * Title shapes of a filtered view per section (words from the hub titles), longest first; %s is the category name.
 * No colon or comma: the only separator in a title is the " – " before Animal Writes (owner, 15 Oct 2026).
 */
const AW_FILTERED_TITLE_WORDS = array(
	'aw_news'        => array( '%s News from India', '%s News' ),
	'aw_legal'       => array( '%s Laws and Court Orders in India', '%s Laws and Court Orders', '%s Library Documents in India', '%s Library Documents' ),
	'post'           => array( '%s Guides and Research', '%s Resources' ),
	'aw_opportunity' => array( '%s Volunteer Roles and Jobs', '%s Opportunities' ),
	'aw_campaign'    => array( '%s Campaigns', 'Campaigns on %s' ),
);

/** Title shapes of a ?state= or ?publisher= view, longest first; %s is the state or publisher name. */
const AW_FILTERED_PLACE_TITLE_WORDS = array(
	'state'     => array(
		'aw_news'        => array( 'Animal Welfare News from %s', '%s Animal Welfare News', '%s News' ),
		'aw_legal'       => array( 'Animal Laws and Court Orders in %s', 'Animal Laws in %s', '%s Animal Laws' ),
		'aw_opportunity' => array( 'Animal Welfare Volunteer Roles and Jobs in %s', 'Animal Welfare Opportunities in %s', '%s Opportunities' ),
	),
	'publisher' => array(
		'aw_news' => array( 'Animal Welfare News from %s', '%s Animal Welfare News', '%s News' ),
	),
);

/**
 * Name and item count of a ?state= or ?publisher= view on a hub, counted with the hub's own filter clauses (open
 * opportunities only; preview-only items left out). ['', 0] when the value names nothing.
 */
function aw_seo_filtered_place( $type, $key, $slug ) {
	$name = '';
	if ( 'state' === $key && function_exists( 'aw_place_by_slug' ) ) {
		$p    = aw_place_by_slug( $slug );
		$name = $p && empty( $p->parent ) ? (string) $p->name : '';
	} elseif ( 'publisher' === $key && function_exists( 'aw_filter_values' ) ) {
		$one  = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'aw_publisher', 'meta_value' => $slug ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$vals = $one ? aw_filter_values( $one, 'publisher' ) : array();
		$name = (string) ( $vals[ $slug ][0] ?? '' );
	}
	if ( '' === $name || ! function_exists( 'aw_filter_clauses' ) ) {
		return array( '', 0 );
	}
	list( $tq, $mq ) = aw_filter_clauses( $type, array( $key => array( $slug ) ) );
	$v = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'tax_query' => $tq, 'meta_query' => $mq ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( 'aw_opportunity' === $type && function_exists( 'aw_open_opportunities_meta_query' ) ) {
		$v['meta_query'][] = aw_open_opportunities_meta_query();
	}
	if ( function_exists( 'kits_preview_only_ids' ) && kits_preview_only_ids() ) {
		$v['post__not_in'] = kits_preview_only_ids();
	}
	return array( $name, (int) ( new WP_Query( $v ) )->found_posts );
}

/** Filter parameters present on this request (sort is an order, not a filter): param => raw string. */
function aw_seo_filter_params() {
	$out = array();
	foreach ( array_merge( AW_FILTER_PARAMS, array( 'format' ) ) as $k ) {
		if ( 'sort' !== $k && isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out[ $k ] = is_string( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '[]'; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
	return $out;
}

/**
 * Items a hub list holds for one category or tag, with the hub's own rules (open opportunities only; preview-only
 * items left out). The same count on the page and in the sitemap.
 *
 * @param string $type Post type of the hub.
 * @param string $key  category_name|tag.
 * @param string $slug Term slug (for the Directory, a listing type such as "veterinary" too).
 * @return int
 */
function aw_seo_filtered_count( $type, $key, $slug ) {
	$v = array(
		'post_type'      => $type,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'tax_query'      => array( array( 'taxonomy' => 'tag' === $key ? 'post_tag' : 'category', 'field' => 'slug', 'terms' => 'aw_listing' === $type && function_exists( 'aw_listing_type_query' ) ? explode( ',', aw_listing_type_query( $slug ) ) : array( $slug ), 'include_children' => true ) ),
	);
	if ( 'aw_opportunity' === $type && function_exists( 'aw_open_opportunities_meta_query' ) ) {
		$v['meta_query'] = array( aw_open_opportunities_meta_query() ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( function_exists( 'kits_preview_only_ids' ) && kits_preview_only_ids() ) {
		$v['post__not_in'] = kits_preview_only_ids();
	}
	return (int) ( new WP_Query( $v ) )->found_posts;
}

/**
 * The filtered hub view of this request, or null when the request is not a filtered hub list.
 *
 * @return array|null [type, key, slug, name, n, index (bool), canonical (url or '')]
 */
function aw_seo_filtered_view() {
	static $view = false;
	if ( false !== $view ) {
		return $view;
	}
	$view = null;
	$type = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
	if ( ! $type || is_404() || ( function_exists( 'aw_current_place' ) && aw_current_place() ) || get_query_var( 'aw_dir_type' ) ) {
		return $view; // not a hub, or a Directory place (its own rules, programmatic.php)
	}
	$params = aw_seo_filter_params();
	if ( ! $params ) {
		return $view;
	}
	$view  = array( 'type' => $type, 'key' => '', 'slug' => '', 'name' => '', 'n' => 0, 'index' => false, 'canonical' => '' );
	$key   = (string) array_key_first( $params );
	$own   = 'aw_listing' === $type ? array( 'tag', 'q', 'verified' ) : array_column( AW_LIST_FILTERS[ $type ] ?? array(), 0 );
	$value = (string) $params[ $key ];
	if ( count( $params ) > 1 || ! in_array( $key, $own, true ) || preg_match( '/,|%2C|\[\]/i', $value ) ) {
		return $view; // several filters, several values, or not a filter of this hub: noindex
	}
	if ( 'q' === $key ) {
		return $view; // a search inside the list: noindex, follow
	}
	if ( isset( AW_FILTERED_PLACE_TITLE_WORDS[ $key ][ $type ] ) ) { // ?state=, ?publisher=: as a category view
		$slug = sanitize_title( $value );
		list( $name, $n ) = aw_seo_filtered_place( $type, $key, $slug );
		if ( '' === $name ) {
			return $view; // names nothing: noindex
		}
		$view = array_merge( $view, array( 'key' => $key, 'slug' => $slug, 'name' => aw_seo_plain( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ), 'n' => $n ) );
		if ( $n >= AW_FILTERED_MIN_ITEMS ) {
			$view['index']     = true;
			$view['canonical'] = add_query_arg( $key, rawurlencode( $slug ), aw_hub_canonical() );
		}
		return $view;
	}
	if ( ! in_array( $key, array( 'category_name', 'tag' ), true ) ) {
		$view['index'] = true; // any other single filter: as before (indexed, canonical with the filter)
		$view['key']   = $key;
		return $view;
	}
	$slug = sanitize_title( $value );
	$term = get_term_by( 'slug', $slug, 'tag' === $key ? 'post_tag' : 'category' );
	$name = $term ? $term->name : ( 'aw_listing' === $type && 'tag' === $key && function_exists( 'aw_listing_type_name' ) ? aw_listing_type_name( $slug ) : '' );
	if ( '' === $name ) {
		return $view; // no such category or tag
	}
	$view['key']  = $key;
	$view['slug'] = $slug;
	$view['name'] = aw_seo_plain( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) );
	$view['n']    = aw_seo_filtered_count( $type, $key, $slug );
	if ( $view['n'] < AW_FILTERED_MIN_ITEMS ) {
		return $view;
	}
	if ( 'tag' === $key && $term && (int) $term->count >= AW_FILTERED_MIN_ITEMS ) {
		$view['canonical'] = get_term_link( $term ); // the tag page lists the same items and is the indexed one
		$view['index']     = true;
		return $view;
	}
	$view['index']     = true;
	$view['canonical'] = add_query_arg( $key, rawurlencode( $slug ), aw_hub_canonical() );
	return $view;
}

/** Whether this request is a filtered hub list that is kept out of the index. */
function aw_seo_filtered_noindex() {
	$v = aw_seo_filtered_view();
	return null !== $v && ! $v['index'];
}

/** Whether this request is a filtered hub list with a category or tag of its own (title, description, breadcrumb). */
function aw_seo_filtered_named() {
	$v = aw_seo_filtered_view();
	return null !== $v && '' !== $v['name'];
}

/**
 * "Wildlife News from India – Animal Writes": the longest shape that fits 60 characters, skipping shapes that repeat a
 * word of the category name ("Laws and Rules Laws and Court Orders").
 */
function aw_seo_filtered_title() {
	$v = aw_seo_filtered_view();
	if ( ! aw_seo_filtered_named() ) {
		return '';
	}
	if ( 'tag' === $v['key'] && function_exists( 'aw_seo_term_title' ) && ( $term = get_term_by( 'slug', $v['slug'], 'post_tag' ) ) ) {
		return aw_seo_title_with_page( aw_seo_term_title( $term ) ); // the tag page's own title (its canonical, or the same list)
	}
	$name = str_replace( ' & ', ' and ', $v['name'] );
	if ( isset( AW_FILTERED_PLACE_TITLE_WORDS[ $v['key'] ][ $v['type'] ] ) ) { // a state or publisher
		$tries = array();
		foreach ( AW_FILTERED_PLACE_TITLE_WORDS[ $v['key'] ][ $v['type'] ] as $shape ) {
			$tries[] = aw_seo_branded( sprintf( $shape, $name ) );
		}
		foreach ( AW_FILTERED_PLACE_TITLE_WORDS[ $v['key'] ][ $v['type'] ] as $shape ) {
			$tries[] = sprintf( $shape, $name );
		}
		return aw_seo_title_with_page( aw_seo_fit( $tries, 60 - mb_strlen( aw_seo_page_suffix() ) ) );
	}
	$what = array();
	$words = fn( $t ) => array_diff( preg_split( '/\W+/u', mb_strtolower( $t ), -1, PREG_SPLIT_NO_EMPTY ), array( 'and', 'in', 'on', 'of', 'the', 'from', 'india' ) );
	$all   = AW_FILTERED_TITLE_WORDS[ $v['type'] ] ?? array( '%s' );
	foreach ( $all as $shape ) {
		if ( ! array_intersect( $words( $name ), $words( str_replace( '%s', '', $shape ) ) ) ) {
			$what[] = sprintf( $shape, $name );
		}
	}
	$what  = $what ? $what : array( sprintf( (string) end( $all ), $name ) );
	$room  = 60 - mb_strlen( aw_seo_page_suffix() );
	$tries = array();
	foreach ( $what as $w ) {
		$tries[] = aw_seo_branded( $w );
	}
	foreach ( $what as $w ) {
		$tries[] = $w;
	}
	return aw_seo_title_with_page( aw_seo_fit( $tries, $room ) );
}

/** "199 stories on wildlife. Animal-welfare news from across India, summarised with a link to every source." */
function aw_seo_filtered_description() {
	$v = aw_seo_filtered_view();
	if ( ! aw_seo_filtered_named() ) {
		return '';
	}
	if ( 'tag' === $v['key'] && function_exists( 'aw_seo_tag_description' ) && ( $term = get_term_by( 'slug', $v['slug'], 'post_tag' ) ) ) {
		return aw_seo_with_page( aw_seo_tag_description( $term ) ); // as the tag page
	}
	$count = function_exists( 'aw_hub_count_label' ) ? aw_hub_count_label( $v['type'] ) : '';
	$about = 'state' === $v['key'] ? ' in ' . $v['name'] : ( 'publisher' === $v['key'] ? ' from ' . $v['name'] : ' on ' . aw_seo_sentence_case( str_replace( ' & ', ' and ', $v['name'] ) ) );
	$lead = '' !== $count ? $count . $about . '. ' : '';
	$desc = $lead . ( AW_ARCHIVE_DESCRIPTIONS[ $v['type'] ] ?? '' );
	return aw_seo_with_page( aw_seo_cut( $desc, 155 - mb_strlen( aw_seo_page_suffix() ) ) );
}

// Robots: the filtered views that are not their own page.
add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		$search = function_exists( 'aw_hub_type' ) && aw_hub_type() && isset( $_GET['q'] ) && '' !== $_GET['q']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( aw_seo_filtered_noindex() || $search ) { // ?q= on a Directory place too
			$robots['index'] = 'noindex';
		}
		return $robots;
	},
	22
);

// Title and description of a named filtered view (they win over the hub Page's own, which belong to the plain list).
// The Directory's filtered views keep the titles built for them in titles.php and programmatic.php.
add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		$v    = aw_seo_filtered_view();
		$ours = $v && 'aw_listing' !== $v['type'] ? aw_seo_filtered_title() : '';
		return '' !== $ours ? $ours : $title;
	},
	25
);
add_filter(
	'rank_math/frontend/description',
	function ( $desc ) {
		$v    = aw_seo_filtered_view();
		$ours = $v && 'aw_listing' !== $v['type'] ? aw_seo_filtered_description() : '';
		return '' !== $ours ? $ours : $desc;
	},
	25
);

/* ---------- Sitemap: filtered-sitemap.xml ---------- */

/**
 * The indexable ?category_name=, ?state= and ?publisher= views of every hub (3 or more items each), cached for 12
 * hours. ?tag= views are not listed: their tag pages are (Rank Math's tag sitemap).
 *
 * @return array List of ['loc' => URL].
 */
function aw_sitemap_filtered_urls() {
	$urls = get_transient( 'aw_sitemap_filtered' );
	if ( false !== $urls ) {
		return $urls;
	}
	$urls = array();
	foreach ( AW_FILTERED_CATEGORY_HUBS as $type ) {
		$cats = function_exists( 'aw_section_categories' ) ? aw_section_categories( $type ) : array();
		foreach ( (array) $cats as $slug ) {
			if ( aw_seo_filtered_count( $type, 'category_name', (string) $slug ) >= AW_FILTERED_MIN_ITEMS ) {
				$urls[] = array( 'loc' => add_query_arg( 'category_name', rawurlencode( (string) $slug ), aw_hub_url( $type ) ) );
			}
		}
	}
	foreach ( AW_FILTERED_PLACE_TITLE_WORDS as $key => $types ) { // ?state= and ?publisher= views from 3 items (17 Oct 2026)
		foreach ( array_keys( $types ) as $type ) {
			foreach ( aw_sitemap_filtered_values( $type, $key ) as $slug ) {
				if ( aw_seo_filtered_place( $type, $key, $slug )[1] >= AW_FILTERED_MIN_ITEMS ) {
					$urls[] = array( 'loc' => add_query_arg( $key, rawurlencode( $slug ), aw_hub_url( $type ) ) );
				}
			}
		}
	}
	set_transient( 'aw_sitemap_filtered', $urls, 12 * HOUR_IN_SECONDS );
	return $urls;
}
add_action( 'transition_post_status', fn( $new, $old ) => $new !== $old && delete_transient( 'aw_sitemap_filtered' ), 10, 2 );

/**
 * Candidate ?state= or ?publisher= values of a hub: the states and publishers its published items carry at least
 * AW_FILTERED_MIN_ITEMS times (the exact count, with the hub's own rules, comes from aw_seo_filtered_place()).
 *
 * @return string[] URL values (state slugs, publisher keys).
 */
function aw_sitemap_filtered_values( $type, $key ) {
	global $wpdb;
	$meta = 'state' === $key ? 'aw_state_code' : 'aw_publisher';
	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' GROUP BY m.meta_value HAVING COUNT( DISTINCT p.ID ) >= %d", $meta, $type, AW_FILTERED_MIN_ITEMS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( 'publisher' === $key ) {
		return array_values( array_filter( array_map( 'sanitize_title', $rows ) ) );
	}
	$out = array();
	foreach ( $rows as $code ) {
		$s = function_exists( 'aw_place_state' ) ? aw_place_state( $code ) : null;
		if ( $s ) {
			$out[] = (string) $s->slug;
		}
	}
	return array_values( array_unique( $out ) );
}

add_filter(
	'rank_math/sitemap/providers',
	function ( $providers ) {
		if ( ! interface_exists( '\RankMath\Sitemap\Providers\Provider' ) || ! class_exists( '\RankMath\Sitemap\Router' ) ) {
			return $providers;
		}
		if ( ! class_exists( 'AW_Rank_Math_Sitemap_Filtered' ) ) {
			/** Filtered hub lists with a category of their own. */
			class AW_Rank_Math_Sitemap_Filtered implements \RankMath\Sitemap\Providers\Provider {
				public function handles_type( $type ) {
					return 'filtered' === $type;
				}
				public function get_index_links( $max_entries ) {
					return aw_sitemap_filtered_urls() ? array( array( 'loc' => \RankMath\Sitemap\Router::get_base_url( 'filtered-sitemap.xml' ) ) ) : array();
				}
				public function get_sitemap_links( $type, $max_entries, $current_page ) {
					return array_slice( aw_sitemap_filtered_urls(), ( max( 1, (int) $current_page ) - 1 ) * $max_entries, $max_entries );
				}
			}
		}
		$providers[] = new AW_Rank_Math_Sitemap_Filtered();
		return $providers;
	}
);
