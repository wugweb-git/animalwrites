<?php
/**
 * What search engines index (restructure Phase 4).
 *
 * - /location/{place}/ sends one 301 to the place's directory page.
 * - noindex (links still followed): listing-type, issuer and opportunity-type archives; district × type pages with
 *   fewer than 3 listings; search results. Closed opportunities and older news: see retention.php.
 * - Lists: the canonical URL is the list without its filters (?topic=, ?court=, ?type=, ?q= …).
 * - Sitemap: only canonical, indexable URLs.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_THIN_DISTRICT_TYPE = 3; // a district × type page needs this many listings to be indexed
const AW_NOINDEX_TAXONOMIES = array( 'aw_listing_type', 'aw_source_type', 'aw_opportunity_type', 'aw_legal_kind', 'aw_resource_type' );

/** District × type page with too few listings to be worth indexing. */
function aw_is_thin_directory_page() {
	if ( ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() || ! get_query_var( 'aw_dir_type' ) ) {
		return false;
	}
	$place = aw_current_place();
	global $wp_query;
	return $place && $place->parent && (int) $wp_query->found_posts < AW_THIN_DISTRICT_TYPE;
}

add_filter(
	'wp_robots',
	function ( $robots ) {
		$tax = array_filter( AW_NOINDEX_TAXONOMIES, fn( $t ) => is_tax( $t ) );
		// A directory place page is also a listing-type query; only the bare type archive (/directory-type/…) is excluded.
		$type_archive = $tax && ! get_query_var( 'aw_place' );
		if ( $type_archive || is_search() || aw_is_thin_directory_page() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	},
	20
);

/** Canonical URL of the current list page: its path (with /page/N/), without filters. */
function aw_list_canonical() {
	if ( is_singular() || is_search() || is_404() ) {
		return '';
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return home_url( user_trailingslashit( $path ) );
}

add_action(
	'wp_head',
	function () {
		$url = aw_list_canonical();
		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		}
	},
	1
);

/* ---------- Sitemap ---------- */

add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxes ) {
		foreach ( AW_NOINDEX_TAXONOMIES as $t ) {
			unset( $taxes[ $t ] );
		}
		return $taxes;
	},
	20
);
