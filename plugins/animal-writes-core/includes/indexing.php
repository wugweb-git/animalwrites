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

const AW_THIN_DISTRICT_TYPE = 1; // owner, 10 Oct 2026: every district × type page with a listing is indexed
const AW_NOINDEX_TAXONOMIES = array( 'aw_listing_type', 'aw_opportunity_type', 'aw_legal_kind', 'category', 'aw_service' );

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

add_filter(
	'wp_robots',
	function ( $robots ) {
		$tax = array_filter( AW_NOINDEX_TAXONOMIES, fn( $t ) => is_tax( $t ) );
		// A directory place page is also a listing-type query; only the bare type archive (/directory-type/…) is excluded.
		$type_archive = $tax && ! get_query_var( 'aw_place' );
		if ( $type_archive || is_search() || aw_is_thin_directory_page() ) { // filtered lists are indexed (owner, 10 Oct 2026)
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	},
	20
);

/** Query parameters that filter a list: such a URL is indexed with its own canonical (owner, 10 Oct 2026). */
const AW_FILTER_PARAMS = array( 'type', 'state', 'district', 'q', 'topic', 'kind', 'service', 'verified', 'sort', 'publisher', 'court', 'issuer' );

/** Whether the current URL is a filtered or sorted list (?type=, ?state=, ?q=, ?topic=, ?kind= …). */
function aw_is_filtered_list() {
	$is_list = ( function_exists( 'aw_hub_type' ) && aw_hub_type() ) || ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() ) || is_page( 'directory' );
	if ( is_singular() && ! $is_list ) {
		return false;
	}
	foreach ( AW_FILTER_PARAMS as $k ) {
		if ( isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
	}
	return false;
}

/**
 * Canonical URL of the current list page: its path (with /page/N/) plus its filters, in a fixed order, so each
 * filtered list (e.g. /directory/?type=gaushala) is its own indexed page (owner, 10 Oct 2026). Sort order is left out.
 */
function aw_list_canonical() {
	if ( is_singular() || is_search() || is_404() ) {
		return '';
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$url  = home_url( user_trailingslashit( $path ) );
	$args = array();
	foreach ( AW_FILTER_PARAMS as $k ) {
		if ( 'sort' !== $k && isset( $_GET[ $k ] ) && '' !== $_GET[ $k ] && is_string( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
	return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $url ) : $url;
}

// The same canonical for Rank Math: a filtered list points to itself, not to the unfiltered list.
add_filter( 'kits_seo_canonical', fn( $url ) => aw_is_filtered_list() ? aw_list_canonical() : $url, 20 ); // after the hub canonical (kits-config.php)

add_action(
	'wp_head',
	function () {
		// Rank Math prints it when active (filtered lists included, through kits_seo_canonical below).
		$url = ( function_exists( 'kits_rank_math' ) && kits_rank_math() ) ? '' : aw_list_canonical();
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
