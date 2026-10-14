<?php
/**
 * Robots rules that depend on an item's state or a list's contents, which Rank Math's per-type and per-item settings
 * cannot express. Fixed rules live in Rank Math: per item (Advanced → Robots: the search, report and thank-you pages,
 * hidden items) and per type or taxonomy (Titles & Meta: listing-type, opportunity-type, Library-kind and service
 * archives; site search).
 *
 * - Closed opportunities (past their deadline or review date): noindex, links followed.
 * - Directory district × type pages with fewer listings than AW_THIN_DISTRICT_TYPE: noindex.
 * - Any wp_robots rule another plugin adds (e.g. a staging copy's noindex): Rank Math removes every wp_robots filter,
 *   so a copy of them is kept before it starts and asked here.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_THIN_DISTRICT_TYPE = 1; // owner, 10 Oct 2026: every district × type page with a listing is indexed

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
			return (int) $p[3] < AW_THIN_DISTRICT_TYPE;
		}
	}
	return true; // no listings of this type here
}

/** Whether the current view is an item or list kept out of search engines by a rule of its state. */
function aw_seo_noindex_rule() {
	if ( is_singular( 'aw_opportunity' ) && function_exists( 'aw_retention_state' ) && aw_retention_state( get_queried_object() ) ) {
		return true; // closed opportunity
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
