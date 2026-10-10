<?php
/**
 * Sitemap (WordPress core /wp-sitemap.xml), tuned for Animal Writes:
 * - no users list and no categories (Resource types have no pages); Posts are Resources and are listed;
 * - a "directory" sitemap lists the place pages (/directory/{state}/, /directory/{state}/{district}/) that have listings,
 *   and district × listing-type pages with at least 3 listings (indexing.php). Places come from the LGD file (places.php).
 * Retention rules (news age, closed opportunities) live in retention.php.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );

// Posts are Resources (content-types.php): their sitemap is wp-sitemap-posts-post-N.xml; the old Resources sitemap
// address points there.
add_action(
	'template_redirect',
	function () {
		if ( 'posts' === get_query_var( 'sitemap' ) && 'aw_guide' === get_query_var( 'sitemap-subtype' ) ) {
			wp_safe_redirect( home_url( '/wp-sitemap-posts-post-' . max( 1, (int) get_query_var( 'paged' ) ) . '.xml' ), 301, 'Animal Writes' );
			exit;
		}
	},
	0
);

add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxes ) {
		unset( $taxes['category'] );
		return $taxes;
	}
);

/** District × listing-type pairs with enough listings to be indexed (no state × type pages exist). Stored counts, places.php. */
function aw_sitemap_directory_pairs() {
	$pairs = get_transient( 'aw_sitemap_dir_pairs' );
	if ( false !== $pairs ) {
		return $pairs;
	}
	$min   = defined( 'AW_THIN_DISTRICT_TYPE' ) ? AW_THIN_DISTRICT_TYPE : 3;
	$pairs = array();
	foreach ( aw_place_counts()['pairs'] as $p ) {
		$d = aw_place_district( $p[0] );
		if ( $d && $p[3] >= $min ) {
			$pairs[] = array( 'city' => $d->slug, 'type' => $p[1], 'modified' => $p[2], 'n' => $p[3] );
		}
	}
	usort( $pairs, fn( $x, $y ) => strcmp( $x['city'] . '/' . $x['type'], $y['city'] . '/' . $y['type'] ) );
	set_transient( 'aw_sitemap_dir_pairs', $pairs, 12 * HOUR_IN_SECONDS );
	return $pairs;
}

/** Directory place pages (states and districts with listings), then district × type pages. */
function aw_sitemap_directory_urls() {
	$urls = array();
	foreach ( aw_place_states() as $st ) {
		if ( aw_place_count( $st ) ) {
			$urls[] = array( 'loc' => aw_directory_url( $st->slug ) );
		}
		foreach ( aw_place_districts( $st->code ) as $d ) {
			if ( aw_place_count( $d ) ) {
				$urls[] = array( 'loc' => aw_directory_url( $d->slug ) );
			}
		}
	}
	foreach ( aw_sitemap_directory_pairs() as $r ) {
		$urls[] = array( 'loc' => aw_directory_url( $r['city'], $r['type'] ), 'lastmod' => gmdate( 'c', strtotime( $r['modified'] . ' UTC' ) ) );
	}
	return $urls;
}

add_action(
	'init',
	function () {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		/** Directory place pages and district × type pages. */
		class AW_Sitemap_Directory extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'directory';
				$this->object_type = 'directory';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				$per = wp_sitemaps_get_max_urls( $this->object_type );
				return array_slice( aw_sitemap_directory_urls(), ( $page_num - 1 ) * $per, $per );
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return (int) ceil( count( aw_sitemap_directory_urls() ) / wp_sitemaps_get_max_urls( $this->object_type ) );
			}
		}
		wp_register_sitemap_provider( 'directory', new AW_Sitemap_Directory() );
	}
);


// Pages that are not indexed stay out of the page sitemap: site search, and the claim, report and thank-you forms.
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$ids = array();
		foreach ( array( 'search', 'claim', 'report', 'thank-you' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p ) {
				$ids[] = $p->ID;
			}
		}
		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $ids );
		return $args;
	},
	10,
	2
);
