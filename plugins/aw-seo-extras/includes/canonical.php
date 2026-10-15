<?php
/**
 * Canonical URLs Rank Math cannot work out (og:url follows the canonical):
 * - section hub Pages and Directory places: the Page path with /page/N/, without filters;
 * - filtered or sorted lists (?tag=, ?state=, ?category_name= …): the list with its filters in a fixed order, so each
 *   filtered list is its own indexed page (owner, 10 Oct 2026); the sort order is left out;
 * - /claim/?listing=ID: one indexed page per listing.
 * A canonical typed in Rank Math always wins. Items keep Rank Math's own canonical.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Query parameters that filter a list: such a URL is indexed with its own canonical (owner, 10 Oct 2026). */
const AW_FILTER_PARAMS = array( 'tag', 'state', 'district', 'q', 'category_name', 'verified', 'sort', 'publisher', 'court', 'issuer' );

/** Canonical URL of a hub page: its path without filters, with /page/N/. */
function aw_hub_canonical() {
	return strtok( aw_hub_base_url(), '?' ) . ( aw_hub_page() > 1 ? 'page/' . aw_hub_page() . '/' : '' );
}

/** Whether the current URL is a filtered or sorted list (?tag=, ?state=, ?q=, ?category_name= …). */
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

/** Canonical URL of the current list page: its path (with /page/N/) plus its filters, in a fixed order. */
function aw_list_canonical() {
	if ( is_search() || is_404() || ( is_singular() && ! aw_is_filtered_list() ) ) { // hub pages are Pages: filtered ones still count
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

/** The listing a /claim/?listing=ID page is for (0 elsewhere). */
function aw_claim_listing() {
	$id = is_page( 'claim' ) ? absint( $_GET['listing'] ?? 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	return ( $id && 'aw_listing' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) ? $id : 0;
}

/**
 * Canonical URL of the current view: hub, claim page or filtered list by the rules above; items their own URL;
 * other views their path.
 *
 * @return string
 */
function aw_seo_canonical() {
	$url = '';
	if ( function_exists( 'aw_hub_type' ) && aw_hub_type() && ! is_404() ) {
		$url = aw_hub_canonical();
	}
	if ( '' === $url && aw_claim_listing() ) {
		$url = add_query_arg( 'listing', aw_claim_listing(), get_permalink() );
	}
	if ( aw_is_filtered_list() ) {
		$url = aw_list_canonical();
	}
	if ( '' !== $url ) {
		return $url;
	}
	if ( is_singular() ) {
		$c = wp_get_canonical_url();
		return $c ? $c : (string) get_permalink();
	}
	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return home_url( user_trailingslashit( $path ) );
}

add_filter(
	'rank_math/frontend/canonical',
	function ( $url ) {
		if ( is_404() || '' !== aw_seo_typed( 'canonical_url' ) || ( ! aw_seo_is_list_view() && ! aw_claim_listing() ) ) {
			return $url; // typed in Rank Math, or an item: Rank Math's own
		}
		$ours = aw_seo_canonical();
		return '' !== $ours ? $ours : $url;
	},
	20
);
