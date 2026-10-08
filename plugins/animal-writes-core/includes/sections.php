<?php
/**
 * Section header for archives: [aw_section_head]. Title, one-line description, live count and a
 * three-image visual built from real content in that section.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SECTION_DESC = array(
	'aw_news'        => 'Animal-welfare news from across India, summarised, with a link to every source.',
	'aw_legal'       => 'Court orders, notifications and rules that affect animals, explained in plain words.',
	'aw_guide'       => 'Practical guides for the moments that matter: an injured animal, a cruelty case, a community dog.',
	'aw_campaign'    => 'Citizen campaigns for animals, starting with the letters of Animal Writes 1.0.',
	'aw_opportunity' => 'Volunteer, foster or intern with people doing the work.',
	'aw_listing'     => 'Animal welfare organisations, gaushalas and government vets across India.', // OWNER to approve (Phase 11 E1)
);

/**
 * One visual tile for a post: remote image, featured image, or a placard.
 *
 * @param WP_Post $p Post.
 * @return string
 */
function aw_section_tile( $p ) {
	$img = function_exists( 'aw_item_image' ) ? aw_item_image( $p->ID, 'medium_large' ) : '';
	if ( ! $img && ( $fid = (int) get_post_meta( $p->ID, 'aw_file_id', true ) ) ) {
		$img = (string) wp_get_attachment_image_url( $fid, 'large' );
	}
	if ( ! $img && has_post_thumbnail( $p ) ) {
		$img = (string) get_the_post_thumbnail_url( $p, 'medium_large' );
	}
	if ( $img ) {
		$fit = 'aw_listing' === $p->post_type ? ' style="object-fit:contain;background:#fff;padding:12px"' : '';
		return '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"' . $fit . '>';
	}
	return function_exists( 'aw_placard_html' ) ? aw_placard_html( $p->ID ) : '';
}

add_shortcode(
	'aw_section_head',
	function () {
		global $wp_query;
		$type  = (string) get_query_var( 'post_type' );
		$type  = $type ? $type : ( is_tax() ? (string) ( get_taxonomy( get_queried_object()->taxonomy )->object_type[0] ?? '' ) : '' );
		$title = is_tax() ? single_term_title( '', false ) : post_type_archive_title( '', false );
		if ( 'aw_listing' === $type && ( get_query_var( 'aw_place' ) || get_query_var( 'aw_listing_type' ) ) ) {
			$city  = aw_current_place();
			$tname = function_exists( 'aw_current_type_name' ) ? aw_current_type_name() : '';
			$title = $tname ? ( $city ? $tname . ' in ' . $city->name : $tname ) : 'Help in ' . ( $city ? $city->name : '' );
		}
		$desc = is_tax() && term_description() ? wp_strip_all_tags( term_description() ) : ( AW_SECTION_DESC[ $type ] ?? '' );
		if ( 'aw_listing' === $type && function_exists( 'aw_directory_intro' ) ) {
			$desc = aw_directory_intro();
		}
		$count = (int) $wp_query->found_posts;
		$noun  = array( 'aw_listing' => 'organisations', 'aw_guide' => 'guides', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_legal' => 'documents', 'aw_news' => 'stories' );

		// Visual: campaign photos for campaigns; otherwise the first three items in this view.
		$tiles = '';
		if ( 'aw_campaign' === $type ) {
			foreach ( array( 'aw1-im8-768x1024.jpeg', 'aw1-im10-461x1024.jpeg', 'aw1-im12-768x1024.jpeg' ) as $f ) {
				$tiles .= '<img src="/wp-content/uploads/2026/10/' . esc_attr( $f ) . '" alt="" loading="lazy">';
			}
		} else {
			// Prefer items with a real image; fall back to placards only to fill the three tiles.
			$pool  = get_posts( array( 'post_type' => $type ? $type : 'any', 'post_status' => 'publish', 'numberposts' => 12, 'tax_query' => is_tax() ? array( array( 'taxonomy' => get_queried_object()->taxonomy, 'terms' => get_queried_object_id() ) ) : array() ) );
			// Three tiles, each a different picture: one item per distinct image, then placards to fill.
			$seen  = array();
			$with  = array();
			foreach ( $pool as $p ) {
				$key = aw_item_image( $p->ID ) ?: ( get_post_meta( $p->ID, 'aw_file_id', true ) ? 'doc' . $p->ID : ( has_post_thumbnail( $p ) ? get_the_post_thumbnail_url( $p ) : '' ) );
				if ( $key && ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = true;
					$with[]       = $p;
				}
			}
			$rest  = array_filter( $pool, fn( $p ) => ! in_array( $p, $with, true ) && ! aw_item_image( $p->ID ) );
			$posts = array_slice( array_merge( $with, $rest ), 0, 3 );
			foreach ( $posts as $p ) {
				$tiles .= aw_section_tile( $p );
			}
		}
		// A category page leads with its own photo (set on the category screen in WP Admin).
		$cat_img = ( is_tax() && function_exists( 'aw_topic_image_id' ) ) ? aw_topic_image_id( get_queried_object() ) : 0;
		if ( $cat_img ) {
			$tiles = '<img src="' . esc_url( (string) wp_get_attachment_image_url( $cat_img, 'medium_large' ) ) . '" alt="" loading="lazy">' . implode( '', array_slice( preg_split( '/(?=<img|<div class="aw-placard)/', $tiles, -1, PREG_SPLIT_NO_EMPTY ), 0, 2 ) );
		}
		$visual = $tiles ? '<div class="aw-section-visual" aria-hidden="true">' . $tiles . '</div>' : '';
		if ( 'aw_listing' === $type ) {
			// Directory: the search bar is the main element and the count sits with the results (Phase 11 E1, E3).
			$visual = '';
			$count  = 0;
		}
		// The band holds the breadcrumb, the heading and the page's search or filters (Directory search bar; list filters).
		$crumbs  = function_exists( 'aw_breadcrumbs_claim' ) ? aw_breadcrumbs_claim() : '';
		$filters = '';
		if ( 'aw_listing' === $type && function_exists( 'aw_directory_filter' ) ) {
			$filters = aw_directory_filter();
		} elseif ( is_post_type_archive() && function_exists( 'aw_list_filters' ) ) {
			$filters = aw_list_filters( $type );
		}
		return '<header class="aw-section-band alignfull' . ( 'aw_listing' === $type ? ' aw-section-band--compact' : '' ) . '"><div class="aw-section-inner">' . $crumbs . '<div class="aw-section-head"><div>'
			. '<h1 class="wp-block-query-title">' . esc_html( $title ) . '</h1>'
			. ( $desc ? '<p class="aw-section-desc">' . esc_html( $desc ) . '</p>' : '' )
			. ( $count ? '<p class="aw-section-count">' . esc_html( number_format_i18n( $count ) . ' ' . ( 1 === $count ? rtrim( preg_replace( '/ies$/', 'y', $noun[ $type ] ?? 'items' ), 's' ) : ( $noun[ $type ] ?? 'items' ) ) ) . '</p>' : '' )
			. '</div>' . $visual . '</div>' . ( $filters ? '<div class="aw-section-filters">' . $filters . '</div>' : '' ) . '</div></header>';
	}
);

/**
 * [aw_stats] Live counts for the homepage hero: organisations, states and UTs covered, legal updates, guides and documents.
 * Cached for an hour.
 */
add_shortcode(
	'aw_stats',
	function () {
		$stats = get_transient( 'aw_stats' );
		if ( false === $stats ) {
			$states = array_filter( aw_place_states(), 'aw_place_count' );
			$stats  = array(
				array( (int) wp_count_posts( 'aw_listing' )->publish, 'organisations listed', '/directory/' ),
				array( count( $states ), 'states and UTs covered', '/directory/' ),
				array( (int) wp_count_posts( 'aw_legal' )->publish, 'official documents and templates', '/library/' ),
				array( (int) wp_count_posts( 'aw_guide' )->publish, 'guides and resources', '/resources/' ),
			);
			set_transient( 'aw_stats', $stats, HOUR_IN_SECONDS );
		}
		$out = '<ul class="aw-stats" aria-label="Animal Writes in numbers">';
		foreach ( $stats as $s ) {
			$out .= '<li><a href="' . esc_url( $s[2] ) . '"><strong>' . esc_html( number_format_i18n( $s[0] ) ) . '</strong> <span>' . esc_html( $s[1] ) . '</span></a></li>';
		}
		return $out . '</ul>';
	}
);

/**
 * [aw_find_help] Directory discovery for the homepage: total, a search box with a state picker,
 * and every state and union territory as a typographic index with its organisation count.
 */
add_shortcode(
	'aw_find_help',
	function () {
		$states = aw_place_states();
		$total  = (int) wp_count_posts( 'aw_listing' )->publish;
		$opts   = '<option value="">All states and UTs</option>';
		$with   = 0;
		foreach ( (array) $states as $st ) {
			if ( ! aw_state_count( $st ) ) {
				continue; // states and UTs with no listings are left out, so the list and the count match (Phase 11 A2)
			}
			$with++;
			$opts .= '<option value="' . esc_attr( $st->slug ) . '">' . esc_html( $st->name ) . '</option>';
		}
		return '<div class="aw-ed-find-head"><h2>Find help near you</h2><p class="aw-ed-big"><strong>' . esc_html( number_format_i18n( $total ) ) . '</strong> organisations in <strong>' . (int) $with . '</strong> states and union territories</p></div>'
			. '<form class="aw-ed-search" role="search" method="get" action="' . esc_url( get_post_type_archive_link( 'aw_listing' ) ) . '">'
			. '<input type="hidden" name="aw_filter" value="1">'
			. '<label class="screen-reader-text" for="aw-ed-q">Organisation name</label><span class="aw-ico aw-ico--search" aria-hidden="true"></span><input id="aw-ed-q" type="search" name="q" placeholder="Search by name, for example Blue Cross">'
			. '<label class="screen-reader-text" for="aw-ed-state">State or union territory</label><select id="aw-ed-state" name="state">' . $opts . '</select>'
			. '<button type="submit" class="kit-btn">Search</button></form>'
			. aw_browse_states();
	}
);
