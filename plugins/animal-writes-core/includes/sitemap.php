<?php
/**
 * Sitemap (WordPress core /wp-sitemap.xml), tuned for Animal Writes:
 * - no users list and no default blog posts or categories (unused);
 * - places point at the directory pages (/directory/{place}/), not the generic /location/ archive;
 * - a "directory" sitemap lists district × listing-type pages with at least 3 listings (indexing.php).
 * Retention rules (news age, closed opportunities) live in retention.php.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );

add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['post'] );
		return $types;
	}
);

add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxes ) {
		unset( $taxes['category'] );
		return $taxes;
	}
);

// Places: the directory page, listed only when the place has listings.
add_filter(
	'wp_sitemaps_taxonomies_query_args',
	function ( $args, $taxonomy ) {
		if ( 'aw_city' === $taxonomy ) {
			$args['object_ids'] = null;
			$args['hide_empty'] = true;
		}
		return $args;
	},
	10,
	2
);
add_filter(
	'wp_sitemaps_taxonomies_entry',
	function ( $entry, $term, $taxonomy ) {
		if ( 'aw_city' === $taxonomy && function_exists( 'aw_directory_url' ) ) {
			$entry['loc'] = aw_directory_url( get_term( $term )->slug );
		}
		return $entry;
	},
	10,
	3
);

/** District × listing-type pairs with enough listings to be indexed (no state × type pages exist). */
function aw_sitemap_directory_pairs() {
	$pairs = get_transient( 'aw_sitemap_dir_pairs' );
	if ( false !== $pairs ) {
		return $pairs;
	}
	global $wpdb;
	$pairs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT c.slug AS city, t.slug AS type, MAX(p.post_modified_gmt) AS modified, COUNT(DISTINCT p.ID) AS n
		 FROM {$wpdb->posts} p
		 JOIN {$wpdb->term_relationships} rc ON rc.object_id = p.ID
		 JOIN {$wpdb->term_taxonomy} tc ON tc.term_taxonomy_id = rc.term_taxonomy_id AND tc.taxonomy = 'aw_city'
		 JOIN {$wpdb->terms} c ON c.term_id = tc.term_id
		 JOIN {$wpdb->term_relationships} rt ON rt.object_id = p.ID
		 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = rt.term_taxonomy_id AND tt.taxonomy = 'aw_listing_type'
		 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND tc.parent <> 0
		 GROUP BY c.slug, t.slug HAVING n >= %d ORDER BY c.slug, t.slug", defined( 'AW_THIN_DISTRICT_TYPE' ) ? AW_THIN_DISTRICT_TYPE : 3 ), // phpcs:ignore
		ARRAY_A
	);
	set_transient( 'aw_sitemap_dir_pairs', $pairs, 12 * HOUR_IN_SECONDS );
	return $pairs;
}

add_action(
	'init',
	function () {
		if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		/** Directory place × type pages. */
		class AW_Sitemap_Directory extends WP_Sitemaps_Provider {
			public function __construct() {
				$this->name        = 'directory';
				$this->object_type = 'directory';
			}
			public function get_url_list( $page_num, $object_subtype = '' ) {
				$per  = wp_sitemaps_get_max_urls( $this->object_type );
				$rows = array_slice( aw_sitemap_directory_pairs(), ( $page_num - 1 ) * $per, $per );
				return array_map(
					fn( $r ) => array(
						'loc'     => aw_directory_url( $r['city'], $r['type'] ),
						'lastmod' => gmdate( 'c', strtotime( $r['modified'] . ' UTC' ) ),
					),
					$rows
				);
			}
			public function get_max_num_pages( $object_subtype = '' ) {
				return (int) ceil( count( aw_sitemap_directory_pairs() ) / wp_sitemaps_get_max_urls( $this->object_type ) );
			}
		}
		wp_register_sitemap_provider( 'directory', new AW_Sitemap_Directory() );
	}
);

// Refresh the pair list when listings change.
add_action( 'save_post_aw_listing', fn() => delete_transient( 'aw_sitemap_dir_pairs' ) );
