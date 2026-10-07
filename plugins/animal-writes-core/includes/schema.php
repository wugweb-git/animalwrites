<?php
/**
 * Structured data (JSON-LD).
 *
 * - Listing pages: Organization (NGO) with name, URL, logo, locality/region (no street address), contact details
 *   when present, the official website as sameAs, and the AWBI recognition code as an identifier.
 * - Directory pages (all, state, district, type): ItemList of the organisations on the page, in order.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print one JSON-LD block.
 *
 * @param array $data Schema.org data.
 */
function aw_jsonld( $data ) {
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * State and district names of a listing.
 *
 * @param int $id Post ID.
 * @return array{0: string, 1: string} state, district.
 */
function aw_listing_place( $id ) {
	$state    = '';
	$district = '';
	foreach ( (array) wp_get_object_terms( $id, 'aw_city' ) as $t ) {
		if ( $t->parent ) {
			$district = $t->name;
		} else {
			$state = $t->name;
		}
	}
	return array( $state, $district );
}

/**
 * Organization data for a listing.
 *
 * @param int $id Post ID.
 * @return array
 */
function aw_listing_schema( $id ) {
	list( $state, $district ) = aw_listing_place( $id );
	$org = array(
		'@type' => 'NGO',
		'@id'   => get_permalink( $id ) . '#organization',
		'name'  => get_the_title( $id ),
		'url'   => get_permalink( $id ),
	);
	$logo = get_the_post_thumbnail_url( $id, 'medium' );
	if ( $logo ) {
		$org['logo'] = $logo;
	}
	if ( $state || $district ) {
		$org['address'] = array_filter( array( '@type' => 'PostalAddress', 'addressLocality' => $district, 'addressRegion' => $state, 'addressCountry' => 'IN' ) );
	}
	$phone = (string) get_post_meta( $id, 'aw_phone', true );
	$email = (string) get_post_meta( $id, 'aw_email', true );
	$site  = (string) get_post_meta( $id, 'aw_website', true );
	if ( $phone ) {
		$org['telephone'] = $phone;
	}
	if ( $email ) {
		$org['email'] = $email;
	}
	if ( $site ) {
		$org['sameAs'] = array( $site );
	}
	$code = (string) get_post_meta( $id, 'aw_awbi_code', true );
	if ( $code ) {
		$org['identifier'] = array( '@type' => 'PropertyValue', 'propertyID' => 'AWBI recognition code', 'value' => $code );
	}
	return $org;
}

add_action(
	'wp_head',
	function () {
		if ( is_singular( 'aw_listing' ) ) {
			aw_jsonld( array( '@context' => 'https://schema.org' ) + aw_listing_schema( get_queried_object_id() ) );
			return;
		}
		if ( function_exists( 'aw_is_directory_view' ) && aw_is_directory_view() ) {
			global $wp_query;
			$items = array();
			$start = (int) max( 0, ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * (int) $wp_query->get( 'posts_per_page' ) );
			foreach ( $wp_query->posts as $i => $p ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $start + $i + 1, 'url' => get_permalink( $p ), 'name' => get_the_title( $p ) );
			}
			if ( $items ) {
				aw_jsonld(
					array(
						'@context'        => 'https://schema.org',
						'@type'           => 'ItemList',
						'name'            => wp_strip_all_tags( get_the_archive_title() ),
						'numberOfItems'   => (int) $wp_query->found_posts,
						'itemListElement' => $items,
					)
				);
			}
		}
	},
	30
);
