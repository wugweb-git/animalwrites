<?php
/**
 * Robots rules that depend on an item's state or a list's contents, which Rank Math's per-type and per-item settings
 * cannot express. Fixed rules live in Rank Math: per item (Advanced → Robots: the search, report, claim (owner, 24 Oct 2026) and thank-you pages,
 * hidden items) and per type or taxonomy (Titles & Meta: listing-type, opportunity-type, Library-kind and service
 * archives; site search).
 *
 * - Closed opportunities (past their deadline or review date): noindex, links followed.
 * - Directory district × type pages with no listings (AW_THIN_DISTRICT_TYPE = 1): noindex.
 * - Directory profiles with no contact at all, unless they have Search Console impressions: noindex.
 * - The Web Stories archive (/web-stories/) while no story is published: noindex.
 * - Every RSS/Atom feed (posts, News, Opportunities, Library, comments): X-Robots-Tag noindex, links followed (owner, 25 Oct 2026).
 * - robots.txt: a Content-Signal line (search=yes, ai-input=yes, ai-train=no; owner, 3 Nov 2026) under User-agent: *,
 *   added to the text Rank Math writes; every saved rule and the Sitemap line stay as they are.
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
// AW_THIN_DISTRICT_TYPE: AW SEO Plus → Settings (settings.php).

/**
 * Thin district × type pages that stay indexed: every one with Search Console clicks or impressions in the 90 days to
 * 20 Oct 2026 (owner). Paths under /directory/.
 */
// AW_DIRECTORY_KEEP_INDEXED: AW SEO Plus → Settings (settings.php).

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

/**
 * Owner, 24 Oct 2026: a Directory profile with no way to reach the organisation (no phone, WhatsApp, email, website or
 * social profile) is noindex (links followed) and out of the sitemap, so Google spends its crawl on profiles worth
 * indexing. It becomes indexable again by itself once a contact is added (enrichment, claim or edit). Profiles with
 * Search Console impressions stay indexed: their IDs are in the option aw_seo_keep_listings (set with WP-CLI from the
 * Search Console export of 24 Oct 2026, 901 profiles).
 */
function aw_listing_unreachable( $id ) {
	$id = (int) $id;
	if ( 'aw_listing' !== get_post_type( $id ) || in_array( $id, array_map( 'intval', (array) get_option( 'aw_seo_keep_listings', array() ) ), true ) ) {
		return false;
	}
	$score = get_post_meta( $id, 'aw_contact_score', true );
	if ( '' === $score && function_exists( 'aw_contact_score' ) ) {
		$score = aw_contact_score( $id ); // listings imported without a contact never got the stored score
		update_post_meta( $id, 'aw_contact_score', $score );
	}
	if ( (int) $score > 0 ) {
		return false;
	}
	foreach ( array( 'aw_facebook', 'aw_instagram', 'aw_x', 'aw_youtube', 'aw_linkedin' ) as $k ) {
		if ( '' !== trim( (string) get_post_meta( $id, $k, true ) ) ) {
			return false;
		}
	}
	return true;
}

/** Whether the current view is an item or list kept out of search engines by a rule of its state. */
function aw_seo_noindex_rule() {
	if ( is_singular( 'aw_opportunity' ) && function_exists( 'aw_retention_state' ) && aw_retention_state( get_queried_object() ) ) {
		return true; // closed opportunity
	}
	if ( is_post_type_archive( 'web-story' ) && ! (int) ( wp_count_posts( 'web-story' )->publish ?? 0 ) ) {
		return true; // the Web Stories archive while it has no published story (it is also out of the sitemap: Rank Math)
	}
	if ( is_singular( 'aw_listing' ) && aw_listing_unreachable( get_queried_object_id() ) ) {
		return true; // a profile with no contact (also out of the sitemap: sitemap.php)
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

/* A listing reported closed stays in search (owner, 21 Oct 2026): it only carries the "Reported closed" note on its page. */

// "?press=all" lists every item on the profile: the same page, kept out of the index.
add_filter( 'rank_math/frontend/robots', fn( $r ) => is_singular( 'aw_listing' ) && isset( $_GET['press'] ) ? array_merge( (array) $r, array( 'index' => 'noindex', 'follow' => 'follow' ) ) : $r ); // phpcs:ignore WordPress.Security.NonceVerification

// Feeds stay out of the index (owner, 25 Oct 2026): a header, as a feed has no <head>.
add_action( 'template_redirect', fn() => is_feed() && ! headers_sent() ? header( 'X-Robots-Tag: noindex, follow' ) : null, 0 );

/**
 * The robots.txt Content-Signal (owner, 3 Nov 2026): one line under the first User-agent: * group. The rest of the file is
 * Rank Math's saved text, unchanged. Runs after Rank Math (10) and before the staging copy's block-all (99).
 *
 * @param string $txt robots.txt text.
 */
function aw_seo_robots_content_signal( $txt ) {
	$txt    = (string) $txt;
	$signal = (string) apply_filters( 'aw_seo_content_signal', 'search=yes, ai-input=yes, ai-train=no' );
	if ( '' === $signal || preg_match( '/^\s*Content-Signal\s*:/mi', $txt ) ) {
		return $txt;
	}
	$line = 'Content-Signal: ' . $signal;
	if ( preg_match( '/^User-agent:\s*\*\s*$/mi', $txt, $m, PREG_OFFSET_CAPTURE ) ) {
		$at = $m[0][1] + strlen( $m[0][0] );
		return substr( $txt, 0, $at ) . "\n" . $line . substr( $txt, $at );
	}
	return "User-agent: *\n" . $line . "\n\n" . $txt;
}
add_filter( 'robots_txt', 'aw_seo_robots_content_signal', 50 );
