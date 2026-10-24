<?php
/**
 * Rank Math sitemap (/sitemap_index.xml) additions:
 * - a "directory" sitemap (directory-sitemap.xml) of the Directory place pages (/directory/{state}/,
 *   /directory/{state}/{district}/) that have listings, and district × listing-type pages with listings. Places come
 *   from the LGD file (places.php); Rank Math knows nothing of these virtual pages.
 * - no post-type archive links (the sections are Pages, listed in the page sitemap).
 * - items that are not indexed by a rule of their state stay out: closed opportunities, items merged into another
 *   story, items without a slug, and preview-only items (kits_preview_only_ids(), e.g. the sample listing). Private, draft and noindex items
 *   and the empty Web Stories type are left out by Rank Math itself. Fixed exclusions are Rank Math settings (noindex items, Sitemap →
 *   Exclude posts: the search page). Claim forms (/claim/, page 12587) are noindex, follow and out of every sitemap (owner, 24 Oct 2026).
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

/**
 * Published profiles with no contact (aw_listing_unreachable() in robots.php), as an ID-keyed set: one query per
 * sitemap request instead of a meta lookup per listing. Listings with no stored score count as candidates and are
 * checked one by one (that also stores their score).
 */
function aw_sitemap_unreachable_listings() {
	static $set = null;
	if ( null !== $set ) {
		return $set;
	}
	global $wpdb;
	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT p.ID FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = 'aw_contact_score'
		WHERE p.post_type = 'aw_listing' AND p.post_status = 'publish' AND ( s.meta_value IS NULL OR s.meta_value = '' OR s.meta_value = '0' )
		AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key IN ( 'aw_facebook', 'aw_instagram', 'aw_x', 'aw_youtube', 'aw_linkedin' ) AND TRIM( m.meta_value ) <> '' )"
	);
	$set = array();
	_prime_post_caches( array_map( 'intval', $ids ), false, true );
	foreach ( $ids as $id ) {
		if ( aw_listing_unreachable( (int) $id ) ) {
			$set[ (int) $id ] = true;
		}
	}
	return $set;
}

add_filter(
	'rank_math/sitemap/entry',
	function ( $url, $type, $obj ) {
		$obj = ( 'post' === $type && is_object( $obj ) && ! empty( $obj->ID ) ) ? get_post( (int) $obj->ID ) : null; // Rank Math passes a database row
		if ( ! $obj instanceof WP_Post ) {
			return $url;
		}
		$closed = 'aw_opportunity' === $obj->post_type && function_exists( 'aw_retention_state' ) && aw_retention_state( $obj );
		$merged = (int) get_post_meta( $obj->ID, 'aw_merged_into', true ) > 0; // a same-story duplicate (normally private already)
		$unreachable = 'aw_listing' === $obj->post_type && isset( aw_sitemap_unreachable_listings()[ (int) $obj->ID ] ); // noindex (robots.php)
		if ( '' === (string) $obj->post_name || $closed || $merged || $unreachable || ( function_exists( 'kits_preview_only_ids' ) && in_array( (int) $obj->ID, kits_preview_only_ids(), true ) ) ) {
			return false;
		}
		return $url;
	},
	10,
	3
);
