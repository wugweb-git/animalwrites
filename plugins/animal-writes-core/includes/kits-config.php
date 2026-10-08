<?php
/**
 * Animal Writes settings for Kits Core (kits_* filters).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CONTENT_TYPES = array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_campaign', 'aw_opportunity', 'aw_listing' );

// Tags: Site Kit only (Analytics GT-TXZHFR39 -> G-4P5YMJ04RL, AdSense, Tag Manager GTM-TB3FCFQB). Kits Core prints
// no GTM of its own, so nothing loads twice. Do not put a GA4 tag inside GTM-TB3FCFQB while Site Kit Analytics is on.
add_filter( 'kits_gtm_id', fn() => '' );
add_filter( 'kits_analytics_hosts', fn() => 'animalwrites.in,www.animalwrites.in' );

add_filter( 'kits_share_post_types', fn() => array() ); // sharing: the Scriptless Social Sharing plugin (owner, 8 Oct 2026)
add_filter( 'kits_sourced_post_types', fn() => array( 'aw_news', 'aw_legal' ) ); // first one holds the Sourcing menu
add_filter( 'kits_ads_skip', fn( $skip ) => $skip || is_page( array( 'helplines', 'corrections' ) ) ); // no ads where people look for emergency help, or on corrections
add_filter( 'kits_ads_article_types', fn() => array( 'aw_news', 'aw_legal' ) );

add_filter( 'kits_seo_article_types', fn() => array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_campaign' ) );
add_filter( 'kits_seo_twitter_site', fn() => '@AnimalWrites' );
add_filter( 'kits_seo_locale', fn() => 'en_IN' );
add_filter(
	'kits_seo_archive_descriptions',
	fn() => array(
		'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
		'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
		'aw_guide'       => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
		'aw_campaign'    => 'Citizen campaigns for animals in India, starting with Animal Writes 1.0.',
		'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
		'aw_listing'     => 'Animal welfare organisations, gaushalas and government vets across India.',
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

// Section names for sections without a visible heading (shell spec: every section labelled).
add_filter(
	'kits_section_labels',
	fn( $l ) => $l + array(
		'kit-ed-hero'  => array( 'hero', 'Introduction' ),
		'aw-ed-find'   => array( 'find-help', 'Find help near you' ),
		'aw-system'    => array( 'list', 'List' ),
		'aw-vol-banner' => array( 'volunteer-banner', 'Volunteer' ),
	)
);

// End bar on items: categories bottom left, share bottom right.
add_filter( 'kits_endbar_taxonomy', fn( $t, $type ) => 'aw_listing' === $type ? $t : 'aw_topic', 10, 2 );
add_filter( 'kits_endbar_share_types', fn() => array( 'aw_news', 'aw_legal', 'aw_guide', 'aw_opportunity', 'aw_campaign', 'aw_listing' ) );

