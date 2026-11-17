<?php
/**
 * Sourcing basics shared by Kits sites: the sourced post types, the publisher records and their option keys. The
 * Sourcing policy itself (gate, keywords, publishers screen, worker endpoint) is in the aw-sourcing plugin.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kits_sourced_types' ) ) {
	/** Post types fed by sources (news-like). Sites set them with the kits_sourced_post_types filter. */
	function kits_sourced_types() {
		return (array) apply_filters( 'kits_sourced_post_types', array( 'post' ) );
	}
}

/** The post type whose admin menu holds the Sourcing screen (the first sourced type). */
function kits_sourcing_menu_type() {
	$t = kits_sourced_types();
	return (string) reset( $t );
}

// Kits Core's own names for the sourcing option keys and capability. The option VALUES ('aw_publishers',
// 'aw_relevance') are stored data: they keep their names until the owner-gated migration, so the constants hold the
// old values for now.
defined( 'KITS_PUB_OPTION' ) || define( 'KITS_PUB_OPTION', 'aw_publishers' );
defined( 'KITS_REL_OPTION' ) || define( 'KITS_REL_OPTION', 'aw_relevance' );
defined( 'KITS_SRC_CAP' ) || define( 'KITS_SRC_CAP', 'manage_options' );
// Back-compat: SourceWeb (aw-sourcing) still defines and uses the AW_ names; keep them defined too (whichever loads first wins).
defined( 'AW_PUB_OPTION' ) || define( 'AW_PUB_OPTION', 'aw_publishers' );
defined( 'AW_REL_OPTION' ) || define( 'AW_REL_OPTION', 'aw_relevance' );
defined( 'AW_SRC_CAP' ) || define( 'AW_SRC_CAP', 'manage_options' );

if ( ! function_exists( 'kits_host' ) ) {
	/**
	 * Normalised host of a URL (lowercase, no www.).
	 *
	 * @param string $url URL.
	 */
	function kits_host( $url ) {
		$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $h );
	}
}

if ( ! function_exists( 'kits_publishers' ) ) {
	/** Publisher records: host => [status, name, count, first, last]. */
	function kits_publishers() {
		$p = get_option( KITS_PUB_OPTION );
		return is_array( $p ) ? $p : array();
	}
}
