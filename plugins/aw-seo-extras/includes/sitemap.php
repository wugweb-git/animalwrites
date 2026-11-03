<?php
/**
 * Rank Math sitemap (/sitemap_index.xml) additions:
 * - a "directory" sitemap (directory-sitemap.xml) of the Directory place pages (/directory/{state}/,
 *   /directory/{state}/{district}/) that have listings, and district × listing-type pages with listings. Places come
 *   from the LGD file (places.php); Rank Math knows nothing of these virtual pages.
 * - a "filtered" sitemap (filtered-sitemap.xml) of the indexable filtered hub lists (filtered-views.php), rebuilt
 *   once a day (event aw_seo_sitemap_daily), never on a request.
 * - both from one provider class (AW_Rank_Math_Sitemap); this module's switch turns both off.
 * - no post-type archive links (the sections are Pages, listed in the page sitemap).
 * - Rank Math's stored sitemaps are cleared after saves through the REST API and WP-CLI too.
 * Items kept out of the index by a rule of their state are left out of every sitemap by robots.php, whether this
 * module is on or not.
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
		if ( $d && ( $p[3] >= AW_THIN_DISTRICT_TYPE || aw_directory_keep_indexed( aw_directory_url( $d->slug, $p[1] ) ) ) ) {
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

/**
 * The indexable filtered hub lists (filtered-views.php) as the daily job below last stored them. Before its first run
 * they are built on the spot (and not stored).
 *
 * @return array List of ['loc' => URL].
 */
function aw_sitemap_filtered_urls() {
	if ( ! function_exists( 'aw_sitemap_filtered_build' ) ) {
		return array(); // the Filtered views module is off: no filtered lists
	}
	$urls = get_option( 'aw_sitemap_filtered' );
	return is_array( $urls ) ? $urls : aw_sitemap_filtered_build();
}

/** Daily: filtered-sitemap.xml is rebuilt (never on a request), and Rank Math's stored copy of it is cleared. */
function aw_sitemap_filtered_rebuild() {
	if ( ! function_exists( 'aw_sitemap_filtered_build' ) ) {
		return; // the Filtered views module is off
	}
	update_option( 'aw_sitemap_filtered', aw_sitemap_filtered_build(), false );
	if ( class_exists( '\RankMath\Sitemap\Cache_Watcher' ) ) {
		\RankMath\Sitemap\Cache_Watcher::invalidate( 'filtered' );
		add_action( 'shutdown', array( 'RankMath\Sitemap\Cache_Watcher', 'clear_queued' ) );
	}
}
add_action( 'aw_seo_sitemap_daily', 'aw_sitemap_filtered_rebuild' );
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( 'aw_seo_sitemap_daily' ) ) {
			wp_schedule_event( time() + 600, 'daily', 'aw_seo_sitemap_daily' );
		}
	}
);

/**
 * One page of a list of URLs as Rank Math's sitemap links.
 *
 * @param array $urls         List of ['loc' => URL, 'lastmod' => date (optional)].
 * @param int   $max_entries  Links per sitemap page.
 * @param int   $current_page Page, from 1.
 * @return array
 */
function aw_sitemap_page_links( array $urls, $max_entries, $current_page ) {
	$max_entries = max( 1, (int) $max_entries );
	$chunk       = array_slice( $urls, ( max( 1, (int) $current_page ) - 1 ) * $max_entries, $max_entries );
	return array_map( fn( $u ) => array_filter( array( 'loc' => $u['loc'], 'mod' => $u['lastmod'] ?? '' ) ), $chunk );
}

add_filter(
	'rank_math/sitemap/providers',
	function ( $providers ) {
		if ( ! interface_exists( '\RankMath\Sitemap\Providers\Provider' ) || ! class_exists( '\RankMath\Sitemap\Router' ) ) {
			return $providers;
		}
		if ( ! class_exists( 'AW_Rank_Math_Sitemap' ) ) {
			/** The site's own sitemaps: directory-sitemap.xml and filtered-sitemap.xml (each paged by Rank Math's entries per sitemap). */
			class AW_Rank_Math_Sitemap implements \RankMath\Sitemap\Providers\Provider {
				/** Sitemap type => function that lists its URLs. */
				const TYPES = array(
					'directory' => 'aw_sitemap_directory_urls',
					'filtered'  => 'aw_sitemap_filtered_urls',
				);

				public function handles_type( $type ) {
					return isset( self::TYPES[ $type ] );
				}

				public function get_index_links( $max_entries ) {
					$max_entries = max( 1, (int) $max_entries );
					$links       = array();
					foreach ( self::TYPES as $type => $source ) {
						$urls  = (array) call_user_func( $source );
						$pages = (int) ceil( count( $urls ) / $max_entries );
						for ( $i = 1; $i <= $pages; $i++ ) {
							$mods    = array_filter( wp_list_pluck( array_slice( $urls, ( $i - 1 ) * $max_entries, $max_entries ), 'lastmod' ) );
							$links[] = array_filter(
								array(
									'loc'     => \RankMath\Sitemap\Router::get_base_url( $type . '-sitemap' . ( $pages > 1 ? $i : '' ) . '.xml' ),
									'lastmod' => $mods ? max( $mods ) : '',
								)
							);
						}
					}
					return $links;
				}

				public function get_sitemap_links( $type, $max_entries, $current_page ) {
					return isset( self::TYPES[ $type ] ) ? aw_sitemap_page_links( (array) call_user_func( self::TYPES[ $type ] ), $max_entries, $current_page ) : array();
				}
			}
		}
		$providers[] = new AW_Rank_Math_Sitemap();
		return $providers;
	}
);

add_filter( 'rank_math/sitemap/post_type_archive_link', '__return_false' );

/*
 * Rank Math keeps each sitemap as a stored file and only clears it from wp-admin screens and cron (its Cache_Watcher is
 * started only there). A post saved through the REST API (the block editor, admin REST writes) or WP-CLI left its
 * type's sitemap as it was, so a new item stayed out until the next admin save (2 Nov 2026: Library item 23678). The
 * same clearing Rank Math does is queued here for those requests and runs at the end of the request.
 */
add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( is_admin() || wp_doing_cron() || ! class_exists( '\RankMath\Sitemap\Cache_Watcher' ) ) {
			return; // Rank Math's own watcher runs there.
		}
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || '' !== (string) $post->post_password ) {
			return; // As Rank Math: no clearing for revisions, auto-drafts or password-protected posts.
		}
		\RankMath\Sitemap\Cache_Watcher::invalidate_post( $post_id );
		add_action( 'shutdown', array( 'RankMath\Sitemap\Cache_Watcher', 'clear_queued' ) ); // the same callback Rank Math adds; added once
	},
	10,
	2
);
