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
add_filter( 'kits_clarity_id', fn() => 'yy09ekj2dp' ); // Microsoft Clarity (owner, 15 Oct 2026)

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
		if ( is_singular( 'aw_listing' ) && has_term( array( 'veterinary-office', 'mobile-veterinary-unit' ), 'post_tag', get_queried_object_id() ) ) {
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
		if ( is_singular( 'aw_opportunity' ) && has_term( 'volunteer', 'post_tag', get_queried_object_id() ) ) {
			return true; // no ads on volunteer roles (owner, 15 Oct 2026)
		}
		return is_singular() && has_term( 'cruelty-prosecution', 'category', get_queried_object_id() );
	}
);
add_filter( 'kits_ads_article_types', fn() => array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_listing' ) ); // up to 3 ads a page: Ad 1, Ad 2 and the sidebar (item-head.php)
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
				'profile'                => array( 'Directory profile', 'Directory profiles: Ad 1, after the Organisation details (block)', 'article-end', 'block' ),
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
			// Directory (all of India, states, districts): after result 5, then after 11 and every 6 more as people scroll
			// (owner, 12 Oct 2026). Each ad has listings on both sides, so two ads never touch.
			$pos = array( 5 => 'directory-feed' );
			for ( $n = 11; $n <= 200; $n += 6 ) {
				$pos[ $n ] = 'directory-feed';
			}
			return $pos;
		}
		if ( 'aw_press' === $hub ) {
			return array( 6 => 'media-feed' );
		}
		return in_array( $hub, array( 'aw_news', 'aw_legal', 'post' ), true ) ? $slots : array();
	}
);
// Directory profiles: Ad 1 is the "profile" Ad slot block after the Organisation group, Ad 2 "article-end" after Share (single-aw_listing.html).
// Directory district pages: after the list (and after the "Few listings" card).
add_filter(
	'render_block_core/query',
	function ( $html, $block ) {
		if ( 'aw_listing' !== ( $block['attrs']['query']['postType'] ?? '' ) || ! function_exists( 'aw_dir_place' ) || ! function_exists( 'aw_is_directory_view' ) || ! aw_is_directory_view() ) {
			return $html;
		}
		$d = aw_dir_place( (string) get_query_var( 'aw_place' ) );
		if ( ! $d || 'district' !== $d->kind ) {
			return $html;
		}
		// No end ad when the list's last card is already an ad or the last in-list ad is close to the end (never two together).
		$tail = substr( $html, -4000 );
		$last_ad = strrpos( $tail, 'kit-ad-item' );
		$last_li = strrpos( $tail, 'wp-block-post ' );
		return ( false !== $last_ad && ( false === $last_li || $last_ad > $last_li ) ) ? $html : $html . aw_ad( 'directory-district-end' );
	},
	20,
	2
);
// The sample listings (the Wugweb sample, and test samples listed in the option aw_sample_listing_ids): only the owner
// sees them (preview), everyone else gets a 404 and no trace in lists, sitemaps, counts or related blocks.
add_filter( 'kits_preview_only_ids', fn( $ids ) => array_merge( (array) $ids, array( 9003 ), array_map( 'intval', (array) get_option( 'aw_sample_listing_ids', array() ) ) ) );

// Section names for sections without a visible heading (shell spec: every section labelled).
add_filter(
	'kits_section_labels',
	fn( $l ) => $l + array(
		'aw-system'    => array( 'list', 'List' ),
		'aw-vol-banner' => array( 'volunteer-banner', 'Volunteer' ),
	)
);

// End bar on items: categories bottom left, share bottom right.
add_filter( 'kits_endbar_taxonomy', fn( $t, $type ) => 'aw_listing' === $type ? '' : 'category', 10, 2 ); // listings: none (their tags sit under the name)
add_filter( 'kits_endbar_share_types', fn() => array( 'aw_news', 'aw_legal', 'post', 'aw_opportunity', 'aw_campaign', 'aw_listing' ) );


/** Analytics page context (kits-core analytics.php): what this page is, for GTM and the events in kit.js. */
add_filter(
	'kits_page_context',
	function ( $c ) {
		$hub_types = array( 'aw_news' => 'news', 'aw_legal' => 'library', 'post' => 'resources', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_listing' => 'directory', 'aw_press' => 'press-coverage' );
		$hub       = function_exists( 'aw_hub_type' ) ? aw_hub_type() : '';
		if ( is_page( 'search' ) ) {
			$c['page_type'] = 'search';
		} elseif ( $hub ) {
			$c['page_type'] = get_query_var( 'aw_place' ) ? 'directory_place' : 'hub';
			$c['section']   = $hub_types[ $hub ] ?? $hub;
		} elseif ( is_category() ) {
			$c['page_type'] = 'category';
			$c['category']  = single_term_title( '', false );
		} elseif ( is_singular() ) {
			$id             = get_queried_object_id();
			$type           = get_post_type( $id );
			$c['page_type'] = $hub_types[ $type ] ?? $type;
			$c['section']   = $hub_types[ $type ] ?? ( 'page' === $type ? (string) get_post_field( 'post_name', $id ) : '' ); // Pages: their slug (claim, contact, volunteer…)
			$cats           = wp_get_object_terms( $id, 'category', array( 'fields' => 'names' ) );
			$c['category']  = $cats && ! is_wp_error( $cats ) ? $cats[0] : '';
			if ( function_exists( 'aw_item_place_names' ) ) {
				list( $c['state'], $c['district'] ) = aw_item_place_names( $id );
			}
			$lt                = 'aw_listing' === $type ? aw_listing_types( $id ) : array();
			$c['listing_type'] = $lt ? $lt[0]->name : '';
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
		if ( is_front_page() ) {
			$c['page_type'] = 'home'; // the homepage is also a Page; keep it apart (kits-core default)
		}
		// Analytics audit, 17 Oct 2026. Directory type pages and ?tag= filters: the type shown.
		if ( 'aw_listing' === ( function_exists( 'aw_hub_type' ) ? aw_hub_type() : '' ) && function_exists( 'aw_current_type_name' ) ) {
			$c['listing_type'] = (string) aw_current_type_name();
		}
		// The listing a page is about: its profile, or the Claim, Report and thank-you pages opened for it (?listing=).
		$listing = is_singular( 'aw_listing' ) ? get_queried_object() : ( is_page( array( 'claim', 'report', 'thank-you' ) ) && function_exists( 'aw_form_page_listing' ) ? aw_form_page_listing() : null );
		if ( $listing instanceof WP_Post ) {
			$c['listing_id']   = (string) $listing->ID;
			$c['listing_name'] = html_entity_decode( get_the_title( $listing ), ENT_QUOTES, 'UTF-8' );
			if ( ! is_singular( 'aw_listing' ) ) {
				$lt                = aw_listing_types( $listing->ID );
				$c['listing_type'] = $lt ? $lt[0]->name : '';
				if ( function_exists( 'aw_item_place_names' ) ) {
					list( $c['state'], $c['district'] ) = aw_item_place_names( $listing->ID );
				}
			}
		}
		// Campaign pages: the same two names on every campaign (campaign_slug, campaign_title), for the standard
		// campaign_view / campaign_action events (aw-analytics.js, campaign.js).
		if ( is_singular( 'aw_campaign' ) ) {
			$id                   = get_queried_object_id();
			$close                = function_exists( 'aw_camp_close_ts' ) ? (int) aw_camp_close_ts( $id ) : 0;
			$c['campaign_slug']   = (string) get_post_field( 'post_name', $id );
			$c['campaign_title']  = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
			$c['campaign_status'] = $close ? ( $close > time() ? 'open' : 'closed' ) : ''; // by the close date; no date: ''
		}
		// A sponsored Resource or listing: the sponsor's name (sponsored_view).
		if ( is_singular() && get_post_meta( get_queried_object_id(), 'aw_sponsored', true ) ) {
			$sp           = trim( (string) get_post_meta( get_queried_object_id(), 'aw_sponsor_name', true ) );
			$c['sponsor'] = '' !== $sp ? $sp : 'sponsored';
		}
		return $c;
	}
);

// Analytics events for the Directory, campaigns, sponsored blocks, donate links and named-entity links (owner's audit,
// 17 Oct 2026). Every page: the script is small and decides from window.kitsPage what applies.
add_action(
	'wp_enqueue_scripts',
	function () {
		$f = 'assets/js/aw-analytics.js';
		wp_enqueue_script( 'aw-analytics', plugins_url( $f, AW_CORE_DIR . 'animal-writes-core.php' ), array(), (string) filemtime( AW_CORE_DIR . $f ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

// Mail (owner, 9 Oct 2026): sent as "Animal Writes <noreply@animalwrites.in>", replies to contact@ (vedanshu@'s alias).
add_filter( 'mailweb_smtp_defaults', fn( $d ) => array_merge( $d, array( 'user' => 'noreply@animalwrites.in', 'name' => 'Animal Writes' ) ) );
add_filter( 'mailweb_smtp_reply_to', fn() => 'contact@animalwrites.in' );

// The Directory carries as many in-list ads as its length allows (one every 6 results); other pages keep the Kits caps.
add_filter( 'kits_ads_max', fn( $max ) => ( function_exists( 'aw_hub_type' ) && 'aw_listing' === aw_hub_type() ) ? 40 : $max );

// Visits are posted to the plugin's lightweight endpoint (visit.php: WordPress in SHORTINIT mode), not the REST route.
add_filter( 'kits_visit_endpoint', fn() => plugins_url( 'visit.php', AW_CORE_DIR . 'animal-writes-core.php' ) );
