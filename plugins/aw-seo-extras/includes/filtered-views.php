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
 * - Other single filters (?state=, ?publisher= …) keep the earlier rule (indexed, canonical with the filter).
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
	if ( ! in_array( $key, array( 'category_name', 'tag' ), true ) ) {
		$view['index'] = true; // ?state=, ?publisher= …: as before (indexed, canonical with the filter)
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
	$about = ' on ' . aw_seo_sentence_case( str_replace( ' & ', ' and ', $v['name'] ) );
	$lead = '' !== $count ? $count . $about . '. ' : '';
	$desc = $lead . ( AW_ARCHIVE_DESCRIPTIONS[ $v['type'] ] ?? '' );
	return aw_seo_with_page( aw_seo_cut( $desc, 155 - mb_strlen( aw_seo_page_suffix() ) ) );
}

// Robots: the filtered views that are not their own page.
add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		if ( aw_seo_filtered_noindex() ) {
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
 * The indexable ?category_name= views of every hub (3 or more items each), cached for 12 hours. ?tag= views are not
 * listed: their tag pages are (Rank Math's tag sitemap).
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
	set_transient( 'aw_sitemap_filtered', $urls, 12 * HOUR_IN_SECONDS );
	return $urls;
}
add_action( 'transition_post_status', fn( $new, $old ) => $new !== $old && delete_transient( 'aw_sitemap_filtered' ), 10, 2 );

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
