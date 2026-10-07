<?php
/**
 * Animal Writes settings for Kits Core (kits_* filters).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CONTENT_TYPES = array( 'aw_news', 'aw_legal', 'aw_research', 'aw_guide', 'aw_campaign', 'aw_resource', 'aw_opportunity', 'aw_listing' );

// Tags: Site Kit only (Analytics GT-TXZHFR39 -> G-4P5YMJ04RL, AdSense, Tag Manager GTM-TB3FCFQB). Kits Core prints
// no GTM of its own, so nothing loads twice. Do not put a GA4 tag inside GTM-TB3FCFQB while Site Kit Analytics is on.
add_filter( 'kits_gtm_id', fn() => '' );
add_filter( 'kits_analytics_hosts', fn() => 'animalwrites.in,www.animalwrites.in' );

add_filter( 'kits_share_post_types', fn() => AW_CONTENT_TYPES );
add_filter( 'kits_sourced_post_types', fn() => array( 'aw_news', 'aw_legal' ) ); // first one holds the Sourcing menu
add_filter( 'kits_ads_skip', fn( $skip ) => $skip || is_page( array( 'helplines', 'corrections' ) ) ); // no ads where people look for emergency help, or on corrections
add_filter( 'kits_ads_article_types', fn() => array( 'aw_news', 'aw_legal', 'aw_research' ) );

add_filter( 'kits_seo_article_types', fn() => array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_campaign' ) );
add_filter( 'kits_seo_twitter_site', fn() => '@AnimalWrites' );
add_filter( 'kits_seo_locale', fn() => 'en_IN' );
add_filter(
	'kits_seo_archive_descriptions',
	fn() => array(
		'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
		'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
		'aw_research'    => 'The laws, rules and research that protect animals in India, from official sources.',
		'aw_guide'       => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
		'aw_campaign'    => 'Citizen campaigns for animals in India, starting with Animal Writes 1.0.',
		'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
		'aw_resource'    => 'Letter templates and practical tools for animal welfare action in India.',
		'aw_listing'     => 'Find shelters, vets, ambulances and rescue groups by state and district across India.',
	)
);
// Share image fallback: the publisher's image, else the first page of the official PDF.
add_filter(
	'kits_seo_fallback_image',
	function ( $image, $id ) {
		$image = function_exists( 'aw_item_image' ) ? aw_item_image( $id ) : '';
		$fid   = (int) get_post_meta( $id, 'aw_file_id', true );
		return ( ! $image && $fid ) ? (string) wp_get_attachment_image_url( $fid, 'large' ) : $image;
	},
	10,
	2
);
