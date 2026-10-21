<?php
/**
 * Robots rules that depend on an item's state or a list's contents, which Rank Math's per-type and per-item settings
 * cannot express. Fixed rules live in Rank Math: per item (Advanced → Robots: the search, report and thank-you pages,
 * hidden items) and per type or taxonomy (Titles & Meta: listing-type, opportunity-type, Library-kind and service
 * archives; site search).
 *
 * - Closed opportunities (past their deadline or review date): noindex, links followed.
 * - Directory district × type pages with no listings (AW_THIN_DISTRICT_TYPE = 1): noindex.
 * - The Web Stories archive (/web-stories/) while no story is published: noindex.
 * - Any wp_robots rule another plugin adds (e.g. a staging copy's noindex): Rank Math removes every wp_robots filter,
 *   so a copy of them is kept before it starts and asked here.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner, 21 Oct 2026: the 3-organisation rule of 20 Oct is withdrawn. A district × type page with at least 1 organisation is
 * indexed and in the sitemap; only a page with none (nothing to show) stays noindex (stored counts).
 */
const AW_THIN_DISTRICT_TYPE = 1;

/**
 * Thin district × type pages that stay indexed: every one with Search Console clicks or impressions in the 90 days to
 * 20 Oct 2026 (owner). Paths under /directory/.
 */
const AW_DIRECTORY_KEEP_INDEXED = array(
	'haryana/jhajjar/animal-welfare-organisation',
	'madhya-pradesh/sheopur/animal-welfare-organisation',
	'maharashtra/pune/animal-welfare-organisation',
	'maharashtra/pune/wildlife-rescue',
	'telangana/kumuram-bheem-asifabad/veterinary-office',
	'telangana/mancherial/veterinary-office',
	'telangana/medchal-malkajgiri/veterinary-office',
	'telangana/nizamabad/animal-welfare-organisation',
	'uttar-pradesh/chandauli/veterinary-office',
	'uttar-pradesh/gautam-buddha-nagar/animal-welfare-organisation',
	'uttar-pradesh/kushinagar/animal-welfare-organisation',
	'uttar-pradesh/mahrajganj/animal-welfare-organisation',
	'uttar-pradesh/siddharthnagar/gaushala',
	'uttarakhand/chamoli/animal-welfare-organisation',
	'west-bengal/purba-medinipur/veterinary-office',
);

/** Whether a district × type page (its URL) is on the keep-indexed list. */
function aw_directory_keep_indexed( $url ) {
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	return in_array( preg_replace( '#^directory/#', '', $path ), AW_DIRECTORY_KEEP_INDEXED, true );
}

/** District × type page with too few listings to be worth indexing. */
function aw_is_thin_directory_page() {
	if ( ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() || ! get_query_var( 'aw_dir_type' ) ) {
		return false;
	}
	$place = aw_current_place();
	if ( ! $place || ! $place->parent ) {
		return false;
	}
	// Stored counts (places.php), the same numbers the sitemap uses — the page query's found_posts is not reliable here.
	$type = (string) get_query_var( 'aw_dir_type' );
	foreach ( aw_place_counts()['pairs'] as $p ) {
		if ( (string) $p[0] === (string) $place->code && $p[1] === $type ) {
			return (int) $p[3] < AW_THIN_DISTRICT_TYPE && ! aw_directory_keep_indexed( aw_directory_url( $place->slug, $type ) );
		}
	}
	return true; // no listings of this type here
}

/** Whether the current view is an item or list kept out of search engines by a rule of its state. */
function aw_seo_noindex_rule() {
	if ( is_singular( 'aw_opportunity' ) && function_exists( 'aw_retention_state' ) && aw_retention_state( get_queried_object() ) ) {
		return true; // closed opportunity
	}
	if ( is_post_type_archive( 'web-story' ) && ! (int) ( wp_count_posts( 'web-story' )->publish ?? 0 ) ) {
		return true; // the Web Stories archive while it has no published story (it is also out of the sitemap: Rank Math)
	}
	return aw_is_thin_directory_page();
}

add_action(
	'wp',
	function () {
		global $wp_filter;
		if ( isset( $wp_filter['wp_robots'] ) && $wp_filter['wp_robots'] instanceof WP_Hook ) {
			$GLOBALS['aw_seo_wp_robots'] = clone $wp_filter['wp_robots'];
		}
	},
	1
);

add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		if ( aw_seo_noindex_rule() ) {
			$robots['index'] = 'noindex';
		}
		$hook = $GLOBALS['aw_seo_wp_robots'] ?? null;
		if ( $hook instanceof WP_Hook ) {
			$ours = (array) $hook->apply_filters( array(), array( array() ) );
			if ( ! empty( $ours['noindex'] ) ) {
				$robots['index'] = 'noindex';
			}
			if ( ! empty( $ours['nofollow'] ) ) {
				$robots['follow'] = 'nofollow';
			}
		}
		return $robots;
	},
	20
);

/* ---------- Directory listings reported closed (aw_reported_closed, set by enrichment): out of search ---------- */

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_singular( 'aw_listing' ) && get_post_meta( get_queried_object_id(), 'aw_reported_closed', true ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
);

// "?press=all" lists every item on the profile: the same page, kept out of the index.
add_filter( 'rank_math/frontend/robots', fn( $r ) => is_singular( 'aw_listing' ) && isset( $_GET['press'] ) ? array_merge( (array) $r, array( 'index' => 'noindex', 'follow' => 'follow' ) ) : $r ); // phpcs:ignore WordPress.Security.NonceVerification
