<?php
/**
 * Sitemap (WordPress core /wp-sitemap.xml), tuned for Animal Writes:
 * - no users list and no categories (Resource types have no pages); Posts are Resources and are listed;
 * - a "directory" sitemap lists the place pages (/directory/{state}/, /directory/{state}/{district}/) that have listings;
 *   district × listing-type pages are left out (10 Oct 2026). Places come from the LGD file (places.php).
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

/** Directory place pages: states and districts with listings. */
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
	// District × type pages (/directory/{state}/{district}/{type}/) are not listed (SEO audit, 10 Oct 2026).
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

/* ---------- Rank Math sitemap (/sitemap_index.xml): the same URLs ---------- */

// The directory place pages and district × type pages, as Rank Math's "directory" sitemap (directory-sitemap.xml).
add_filter(
	'rank_math/sitemap/providers',
	function ( $providers ) {
		if ( ! interface_exists( '\RankMath\Sitemap\Providers\Provider' ) || ! class_exists( '\RankMath\Sitemap\Router' ) ) {
			return $providers;
		}
		if ( ! class_exists( 'AW_Rank_Math_Sitemap_Directory' ) ) {
			/** Directory place pages and district × type pages. */
			class AW_Rank_Math_Sitemap_Directory implements \RankMath\Sitemap\Providers\Provider {
				public function handles_type( $type ) {
					return 'directory' === $type;
				}
				public function get_index_links( $max_entries ) {
					$urls  = aw_sitemap_directory_urls();
					$pages = (int) ceil( count( $urls ) / max( 1, $max_entries ) );
					$links = array();
					for ( $i = 1; $i <= $pages; $i++ ) {
						$chunk = array_slice( $urls, ( $i - 1 ) * $max_entries, $max_entries );
						$mods  = array_filter( wp_list_pluck( $chunk, 'lastmod' ) );
						$links[] = array_filter(
							array(
								'loc'     => \RankMath\Sitemap\Router::get_base_url( 'directory-sitemap' . ( $pages > 1 ? $i : '' ) . '.xml' ),
								'lastmod' => $mods ? max( $mods ) : '',
							)
						);
					}
					return $links;
				}
				public function get_sitemap_links( $type, $max_entries, $current_page ) {
					$chunk = array_slice( aw_sitemap_directory_urls(), ( max( 1, (int) $current_page ) - 1 ) * $max_entries, $max_entries );
					return array_map( fn( $u ) => array_filter( array( 'loc' => $u['loc'], 'mod' => $u['lastmod'] ?? '' ) ), $chunk );
				}
			}
		}
		$providers[] = new AW_Rank_Math_Sitemap_Directory();
		return $providers;
	}
);

// Sections are Pages (the hubs, in the page sitemap); content types have no archive of their own to list.
add_filter( 'rank_math/sitemap/post_type_archive_link', '__return_false' );

// Not indexed, so not listed: the search, claim, report and thank-you pages, the kit demo, and closed opportunities.
add_filter(
	'kits_sitemap_skip',
	function ( $skip, $post ) {
		if ( 'page' === $post->post_type && in_array( $post->post_name, array( 'search', 'claim', 'report', 'thank-you', 'kits-demo' ), true ) ) {
			return true;
		}
		if ( 'aw_opportunity' === $post->post_type && function_exists( 'aw_retention_state' ) && aw_retention_state( $post ) ) {
			return true;
		}
		return $skip;
	},
	10,
	2
);
