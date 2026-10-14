<?php
/**
 * Rank Math sitemap (/sitemap_index.xml) additions:
 * - a "directory" sitemap (directory-sitemap.xml) of the Directory place pages (/directory/{state}/,
 *   /directory/{state}/{district}/) that have listings, and district × listing-type pages with listings. Places come
 *   from the LGD file (places.php); Rank Math knows nothing of these virtual pages.
 * - no post-type archive links (the sections are Pages, listed in the page sitemap).
 * - items that are not indexed by a rule of their state stay out: closed opportunities, items without a slug, and what
 *   kits_sitemap_skip adds (preview-only items). Fixed exclusions are Rank Math settings (noindex items, Sitemap →
 *   Exclude posts: the claim and search pages).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** District × listing-type pairs with enough listings to be indexed (no state × type pages exist). Stored counts, places.php. */
function aw_sitemap_directory_pairs() {
	$pairs = get_transient( 'aw_sitemap_dir_pairs' );
	if ( false !== $pairs ) {
		return $pairs;
	}
	$pairs = array();
	foreach ( aw_place_counts()['pairs'] as $p ) {
		$d = aw_place_district( $p[0] );
		if ( $d && $p[3] >= AW_THIN_DISTRICT_TYPE ) {
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
						$chunk   = array_slice( $urls, ( $i - 1 ) * $max_entries, $max_entries );
						$mods    = array_filter( wp_list_pluck( $chunk, 'lastmod' ) );
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

add_filter( 'rank_math/sitemap/post_type_archive_link', '__return_false' );

add_filter(
	'rank_math/sitemap/entry',
	function ( $url, $type, $obj ) {
		$obj = ( 'post' === $type && is_object( $obj ) && ! empty( $obj->ID ) ) ? get_post( (int) $obj->ID ) : null; // Rank Math passes a database row
		if ( ! $obj instanceof WP_Post ) {
			return $url;
		}
		$closed = 'aw_opportunity' === $obj->post_type && function_exists( 'aw_retention_state' ) && aw_retention_state( $obj );
		if ( '' === (string) $obj->post_name || $closed || apply_filters( 'kits_sitemap_skip', false, $obj ) ) {
			return false;
		}
		return $url;
	},
	10,
	3
);
