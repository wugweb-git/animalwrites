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

/** Post types fed by sources (news-like). Sites set them with the kits_sourced_post_types filter. */
function kits_sourced_types() {
	return (array) apply_filters( 'kits_sourced_post_types', array( 'post' ) );
}

/** The post type whose admin menu holds the Sourcing screen (the first sourced type). */
function kits_sourcing_menu_type() {
	$t = kits_sourced_types();
	return (string) reset( $t );
}

const AW_PUB_OPTION = 'aw_publishers';
const AW_REL_OPTION = 'aw_relevance';
const AW_SRC_CAP    = 'manage_options';

/** Normalised host of a URL (lowercase, no www.). */
function aw_host( $url ) {
	$h = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $h );
}

/** Publisher records: host => [status, name, count, first, last]. */
function aw_publishers() {
	$p = get_option( AW_PUB_OPTION );
	return is_array( $p ) ? $p : array();
}
