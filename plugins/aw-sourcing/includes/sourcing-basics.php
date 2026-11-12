<?php
/**
 * SourceWeb's own basics: the capability, the publisher and relevance option names, host and publisher helpers and
 * the sourced post types. Kits Core has the same definitions for its own screens; whichever loads first defines
 * them and the other skips, so SourceWeb works with or without Kits Core.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'AW_PUB_OPTION' ) || define( 'AW_PUB_OPTION', 'aw_publishers' );
defined( 'AW_REL_OPTION' ) || define( 'AW_REL_OPTION', 'aw_relevance' );
defined( 'AW_SRC_CAP' ) || define( 'AW_SRC_CAP', 'manage_options' );

if ( ! function_exists( 'kits_sourced_types' ) ) {
	/** Post types fed by sources (news-like). Sites set them with the kits_sourced_post_types filter. */
	function kits_sourced_types() {
		return (array) apply_filters( 'kits_sourced_post_types', array( 'post' ) );
	}
}

if ( ! function_exists( 'aw_host' ) ) {
	/**
	 * Normalised host of a URL (lowercase, no www.).
	 *
	 * @param string $url URL.
	 */
	function aw_host( $url ) {
		$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $h );
	}
}

if ( ! function_exists( 'aw_publishers' ) ) {
	/** Publisher records: host => [status, name, count, first, last]. */
	function aw_publishers() {
		$p = get_option( AW_PUB_OPTION );
		return is_array( $p ) ? $p : array();
	}
}
