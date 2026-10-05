<?php
/**
 * Trust guardrails from the strategy:
 * - no ads on Legal Watch or emergency/rescue listings
 * - sponsored content is always labelled (see bindings: sponsored_label)
 *
 * Ad plugins/blocks should call aw_ads_allowed() or respect the body class "aw-no-ads".
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Listing types treated as emergency/rescue (no ads). Filterable. */
function aw_no_ad_listing_types() {
	return apply_filters( 'aw_no_ad_listing_types', array( 'animal-ambulance', 'emergency-rescue', 'veterinary-hospital' ) );
}

/**
 * Whether ads may render on the current request.
 *
 * @return bool
 */
function aw_ads_allowed() {
	if ( is_singular( 'aw_legal' ) || is_post_type_archive( 'aw_legal' ) ) {
		return false;
	}
	if ( is_tax( 'aw_listing_type', aw_no_ad_listing_types() ) ) {
		return false;
	}
	if ( is_singular( 'aw_listing' ) && has_term( aw_no_ad_listing_types(), 'aw_listing_type' ) ) {
		return false;
	}
	return (bool) apply_filters( 'aw_ads_allowed', true );
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( ! aw_ads_allowed() ) {
			$classes[] = 'aw-no-ads';
		}
		return $classes;
	}
);

/**
 * Remove any block carrying the "kits-ad-slot" class where ads are not allowed.
 */
add_filter(
	'render_block',
	function ( $content, $block ) {
		$class = $block['attrs']['className'] ?? '';
		if ( false !== strpos( $class, 'kits-ad-slot' ) && ! aw_ads_allowed() ) {
			return '';
		}
		return $content;
	},
	10,
	2
);
