<?php
/**
 * Animal Writes settings for Kits Core (kits_* filters).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CONTENT_TYPES = array( 'aw_news', 'aw_legal', 'post', 'aw_campaign', 'aw_opportunity', 'aw_listing' );

// Tags: Site Kit only (Analytics GT-TXZHFR39 -> G-4P5YMJ04RL, AdSense, Tag Manager GTM-TB3FCFQB). Kits Core prints
// no GTM of its own, so nothing loads twice. Do not put a GA4 tag inside GTM-TB3FCFQB while Site Kit Analytics is on.
add_filter( 'kits_gtm_id', fn() => '' );
add_filter( 'kits_analytics_hosts', fn() => 'animalwrites.in,www.animalwrites.in' );

add_filter( 'kits_share_post_types', fn() => array() ); // sharing: the Scriptless Social Sharing plugin (owner, 8 Oct 2026)
add_filter( 'kits_sourced_post_types', fn() => array( 'aw_news', 'aw_legal' ) ); // first one holds the Sourcing menu
/*
 * Ads (owner, 9 Oct 2026). Articles: News, Library, Resources (in-content, end). Lists: News, Library, Resources (after
 * items 6 and 18), Directory state and district pages (after item 5). Profiles: above "Is this your organisation?".
 * None at all on forms, search, 404, crisis guides, sponsored Resources, government vets and MVUs, or Cruelty and
 * Prosecution items.
 */
const AW_CRISIS_GUIDES = array( 126, 127, 9027, 9028, 9029, 9030, 9031, 9032, 9033 ); // help-now guides + the crisis guides
add_filter(
	'kits_ads_skip',
	function ( $skip ) {
		if ( $skip || is_search() || is_page( array( 'helplines', 'corrections', 'contact', 'volunteer', 'remove-my-data', 'search', 'claim', 'report', 'thank-you' ) ) ) {
			return true;
		}
		if ( is_singular( 'aw_listing' ) && has_term( array( 'veterinary-office', 'mobile-veterinary-unit' ), 'aw_listing_type', get_queried_object_id() ) ) {
			return true;
		}
		if ( is_singular( 'post' ) && in_array( get_queried_object_id(), AW_CRISIS_GUIDES, true ) ) {
			return true;
		}
		if ( is_singular( 'post' ) && function_exists( 'aw_is_sponsored' ) && aw_is_sponsored( get_queried_object_id() ) ) {
			return true; // sponsored Resources carry no ads
		}
		if ( is_singular() && function_exists( 'aw_has_sponsored_block' ) && aw_has_sponsored_block( get_queried_object_id() ) ) {
			return true; // nor does a page with a sponsored block
		}
		return is_singular() && has_term( 'cruelty-prosecution', 'aw_topic', get_queried_object_id() );
	}
);
add_filter( 'kits_ads_article_types', fn() => array( 'aw_news', 'aw_legal', 'post' ) );
add_filter( 'kits_ads_end_after_content_types', fn() => array( 'post' ) ); // guides have no end bar: the end ad follows the text
/*
 * Animal Writes slots (owner, 12 Oct 2026), on top of the Kits Core ones (home-top/mid/bottom, article-incontent-1/2,
 * article-end, list-feed-1/2, sidebar, footer-above, head). Blocks: opportunity-end (opportunity template),
 * resources-hub (Resources page). Counted: directory-feed (state and district pages, after result 5),
 * directory-district-end (district pages, after the list), profile (Directory profiles), media-feed (Media & PR, after
 * item 6). "directory" is an older name: the fallback of directory-feed.
 */
add_filter(
	'kits_ad_slots',
	function ( $slots ) {
		return array_merge(
			$slots,
			array(
				'directory-feed'         => array( 'Directory – in results', 'Directory state and district pages: a card after result 5', 'directory', 'auto' ),
				'directory-district-end' => array( 'Directory – end of district page', 'Directory district pages, after the list', '', 'auto' ),
				'profile'                => array( 'Directory profile', 'Directory profiles, above "Is this your organisation?"', 'article-end', 'auto' ),
				'opportunity-end'        => array( 'Opportunity – end', 'Opportunity pages, after the Apply button (block)', '', 'block' ),
				'resources-hub'          => array( 'Resources page', 'The Resources page, after the list (block)', '', 'block' ),
				'media-feed'             => array( 'Media & PR – in list', 'Media & PR: a card after item 6', '', 'auto' ),
				'directory'              => array( 'Directory lists (older slot)', 'Used by "Directory – in results" while its own box is empty', 'archive', 'legacy' ),
			)
		);
	}
);
// Counted list positions: News, Library, Resources (Kits default, after items 6 and 18); Directory state and district
// pages (after item 5); Media & PR (after item 6). No list ads elsewhere.
add_filter(
	'kits_ads_list_slots',
	function ( $slots ) {
		$hub = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
		if ( 'aw_listing' === $hub ) {
			return get_query_var( 'aw_place' ) ? array( 5 => 'directory-feed' ) : array();
		}
		if ( 'aw_press' === $hub ) {
			return array( 6 => 'media-feed' );
		}
		return in_array( $hub, array( 'aw_news', 'aw_legal', 'post' ), true ) ? $slots : array();
	}
);
// Directory profiles: the ad sits above "Is this your organisation?".
add_filter(
	'render_block_animal-writes/listing-help',
	fn( $html ) => is_singular( 'aw_listing' ) && function_exists( 'aw_ad' ) ? aw_ad( 'profile' ) . $html : $html
);
// Directory district pages: after the list (and after the "Few listings" card).
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		if ( 'aw_listing' !== ( $block['attrs']['query']['postType'] ?? '' ) || ! function_exists( 'aw_dir_place' ) || ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() ) {
			return $html;
		}
		$d = aw_dir_place( (string) get_query_var( 'aw_place' ) );
		return $d && 'district' === $d->kind ? $html . aw_ad( 'directory-district-end' ) : $html;
	},
	20,
	2
);
// The Wugweb sample listing: only the owner sees it (preview), everyone else gets a 404 and no trace in lists.
add_filter( 'kits_preview_only_ids', fn( $ids ) => array_merge( (array) $ids, array( 9003 ) ) );

add_filter( 'kits_seo_article_types', fn() => array( 'aw_news', 'aw_legal', 'post', 'aw_campaign' ) );
add_filter( 'kits_seo_twitter_site', fn() => '@AnimalWrites' );
add_filter( 'kits_seo_locale', fn() => 'en_IN' );
add_filter(
	'kits_seo_archive_descriptions',
	fn() => array(
		'aw_news'        => 'Animal-welfare news from across India, summarised with a link to every source.',
		'aw_legal'       => 'Court orders, notifications and rules that affect animals in India, explained in plain words.',
		'post'           => 'Practical guides: what to do for an injured animal, how to report cruelty, Animal Birth Control and more.',
		'aw_campaign'    => 'Citizen campaigns for animals in India, starting with Animal Writes 1.0.',
		'aw_opportunity' => 'Volunteer, foster, internship and job opportunities in animal welfare in India.',
		'aw_listing'     => 'Animal welfare organisations, gaushalas and government vets across India.',
	)
);
// Share image (og:image, article schema): the item's own stored image (topic photo, hosted PDF), never the publisher's.
add_filter(
	'kits_seo_image_id',
	fn( $att, $id ) => $att ? $att : ( function_exists( 'aw_item_image_id' ) ? aw_item_image_id( $id ) : 0 ),
	10,
	2
);
// Long headlines: the <title> is the headline alone, cut at a word (kits-core seo.php).
add_filter( 'kits_seo_short_title_types', fn() => array( 'aw_news', 'aw_legal' ) );
// og:url = canonical: list pages (hubs, directory places) use the hub canonical with /page/N/.
add_filter( 'kits_seo_canonical', fn( $url ) => ( function_exists( 'aw_hub_canonical' ) && aw_hub_type() && ! is_404() ) ? aw_hub_canonical() : $url );
// A listing with no description of its own: one from its facts (type, place, services).
add_filter(
	'kits_seo_description',
	function ( $desc ) {
		if ( '' !== trim( wp_strip_all_tags( (string) $desc ) ) || ! is_singular( 'aw_listing' ) ) {
			return $desc;
		}
		return aw_listing_fact_description( get_queried_object_id() );
	},
	5
);

/** "{Listing type} in {District}, {State}. {Services}. Contact details, location and how to help." ('' without type or place). */
function aw_listing_fact_description( $id ) {
	$types = get_the_terms( $id, 'aw_listing_type' );
	$type  = ( $types && ! is_wp_error( $types ) ) ? $types[0]->name : '';
	list( $state, $district ) = function_exists( 'aw_item_place_names' ) ? aw_item_place_names( $id ) : array( '', '' );
	$where = implode( ', ', array_filter( array( $district, $state ) ) );
	if ( '' === $type && '' === $where ) {
		return '';
	}
	$lead = $type ? $type . ( $where ? ' in ' . $where : '' ) : 'In ' . $where;
	$svc  = wp_get_object_terms( $id, 'aw_service', array( 'fields' => 'names' ) );
	$svc  = ( $svc && ! is_wp_error( $svc ) ) ? implode( ', ', array_slice( $svc, 0, 4 ) ) : '';
	return $lead . '. ' . ( $svc ? ucfirst( $svc ) . '. ' : '' ) . 'Contact details, location and how to help.';
}

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
add_filter( 'kits_endbar_share_types', fn() => array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_campaign', 'aw_listing' ) );


/** Analytics page context (kits-core analytics.php): what this page is, for GTM and the events in kit.js. */
add_filter(
	'kits_page_context',
	function ( $c ) {
		$hub_types = array( 'aw_news' => 'news', 'aw_legal' => 'library', 'post' => 'resources', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_listing' => 'directory', 'aw_press' => 'media' );
		$hub       = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
		if ( is_page( 'search' ) ) {
			$c['page_type'] = 'search';
		} elseif ( $hub ) {
			$c['page_type'] = get_query_var( 'aw_place' ) ? 'directory_place' : 'hub';
			$c['section']   = $hub_types[ $hub ] ?? $hub;
		} elseif ( is_tax( 'aw_topic' ) ) {
			$c['page_type'] = 'category';
			$c['category']  = single_term_title( '', false );
		} elseif ( is_singular() ) {
			$id             = get_queried_object_id();
			$type           = get_post_type( $id );
			$c['page_type'] = $hub_types[ $type ] ?? $type;
			$c['section']   = $hub_types[ $type ] ?? '';
			$topics         = wp_get_object_terms( $id, 'aw_topic', array( 'fields' => 'names' ) );
			$c['category']  = $topics && ! is_wp_error( $topics ) ? $topics[0] : '';
			if ( function_exists( 'aw_item_place_names' ) ) {
				list( $c['state'], $c['district'] ) = aw_item_place_names( $id );
			}
			$lt                = wp_get_object_terms( $id, 'aw_listing_type', array( 'fields' => 'names' ) );
			$c['listing_type'] = $lt && ! is_wp_error( $lt ) ? $lt[0] : '';
			$iss               = function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
			$c['issuer']       = $iss ? $iss->name : '';
			$pub               = function_exists( 'aw_publisher_of' ) ? aw_publisher_of( $id ) : array( '' );
			$c['publisher']    = (string) ( $pub[0] ?? '' );
		}
		if ( get_query_var( 'aw_place' ) && function_exists( 'aw_dir_place' ) ) {
			$p = aw_dir_place( (string) get_query_var( 'aw_place' ) );
			if ( $p ) {
				$c['state']    = 'district' === $p->kind ? aw_place_state( $p->state )->name : $p->name;
				$c['district'] = 'district' === $p->kind ? $p->name : '';
			}
		}
		return $c;
	}
);

// Mail (owner, 9 Oct 2026): sent as "Animal Writes <noreply@animalwrites.in>", replies to contact@ (vedanshu@'s alias).
add_filter( 'kits_mail_defaults', fn( $d ) => array_merge( $d, array( 'user' => 'noreply@animalwrites.in', 'name' => 'Animal Writes' ) ) );
add_filter( 'kits_mail_reply_to', fn() => 'contact@animalwrites.in' );
