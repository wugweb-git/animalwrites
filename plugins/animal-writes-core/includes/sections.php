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
	'aw_research'    => 'The laws, rules and judgments that protect animals, readable here and linked to the official source.',
	'aw_guide'       => 'Practical guides for the moments that matter: an injured animal, a cruelty case, a community dog.',
	'aw_campaign'    => 'Citizen campaigns for animals, starting with the letters of Animal Writes 1.0.',
	'aw_opportunity' => 'Volunteer, foster or intern with people doing the work.',
	'aw_resource'    => 'Letter templates and tools you can copy, sign and post today.',
	'aw_listing'     => 'Shelters, vets, ambulances and rescue groups, by state and district.',
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
		if ( 'aw_listing' === $type && ( get_query_var( 'aw_city' ) || get_query_var( 'aw_listing_type' ) ) ) {
			$city  = get_query_var( 'aw_city' ) ? get_term_by( 'slug', get_query_var( 'aw_city' ), 'aw_city' ) : null;
			$ltype = get_query_var( 'aw_listing_type' ) ? get_term_by( 'slug', get_query_var( 'aw_listing_type' ), 'aw_listing_type' ) : null;
			$title = $ltype ? ( $city ? $ltype->name . ' in ' . $city->name : $ltype->name ) : 'Help in ' . ( $city ? $city->name : '' );
		}
		$desc = is_tax() && term_description() ? wp_strip_all_tags( term_description() ) : ( AW_SECTION_DESC[ $type ] ?? '' );
		if ( is_tax( 'aw_city' ) ) {
			$desc = 'Shelters, vets, ambulances and rescue groups listed here. Unverified listings are marked; organisations can correct their details.';
		}
		$count = (int) $wp_query->found_posts;
		$noun  = array( 'aw_listing' => 'organisations', 'aw_resource' => 'resources', 'aw_guide' => 'guides', 'aw_opportunity' => 'opportunities', 'aw_campaign' => 'campaigns', 'aw_research' => 'documents', 'aw_legal' => 'updates', 'aw_news' => 'stories' );

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
		$visual = $tiles ? '<div class="aw-section-visual" aria-hidden="true">' . $tiles . '</div>' : '';
		if ( 'aw_listing' === $type && is_post_type_archive( 'aw_listing' ) && ! get_query_var( 'aw_city' ) && ! get_query_var( 'aw_listing_type' ) && empty( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// Directory home: the states with the most organisations, set as type. Real counts, no map.
			$items = '';
			foreach ( get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 9 ) ) as $st ) {
				$items .= '<li><a href="' . esc_url( aw_directory_url( $st->slug ) ) . '"><span class="aw-states-n">' . esc_html( number_format_i18n( $st->count ) ) . '</span> <span class="aw-states-name">' . esc_html( $st->name ) . '</span></a></li>';
			}
			$visual = $items ? '<nav class="aw-states" aria-label="Browse by state"><ul>' . $items . '</ul></nav>' : $visual;
		}
		return '<header class="aw-section-band alignfull"><div class="aw-section-head alignwide"><div>'
			. '<h1 class="wp-block-query-title">' . esc_html( $title ) . '</h1>'
			. ( $desc ? '<p class="aw-section-desc">' . esc_html( $desc ) . '</p>' : '' )
			. ( $count ? '<p class="aw-section-count">' . esc_html( number_format_i18n( $count ) . ' ' . ( 1 === $count ? rtrim( preg_replace( '/ies$/', 'y', $noun[ $type ] ?? 'items' ), 's' ) : ( $noun[ $type ] ?? 'items' ) ) ) . '</p>' : '' )
			. '</div>' . $visual . '</div></header>';
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
			$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => true, 'fields' => 'ids' ) );
			$stats  = array(
				array( (int) wp_count_posts( 'aw_listing' )->publish, 'organisations listed', '/directory/' ),
				array( is_array( $states ) ? count( $states ) : 0, 'states and UTs covered', '/directory/' ),
				array( (int) wp_count_posts( 'aw_legal' )->publish, 'court and government updates', '/legal-watch/' ),
				array( (int) wp_count_posts( 'aw_research' )->publish + (int) wp_count_posts( 'aw_guide' )->publish, 'laws, rules and guides', '/research/' ),
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
		$states = get_terms( array( 'taxonomy' => 'aw_city', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name' ) );
		$total  = (int) wp_count_posts( 'aw_listing' )->publish;
		$opts   = '<option value="">All states and UTs</option>';
		$items  = '';
		$with   = 0;
		foreach ( (array) $states as $st ) {
			$opts .= '<option value="' . esc_attr( $st->slug ) . '">' . esc_html( $st->name ) . '</option>';
			if ( $st->count ) {
				$with++;
			}
			$items .= '<li' . ( $st->count ? '' : ' class="is-empty"' ) . '><a href="' . esc_url( aw_directory_url( $st->slug ) ) . '"><span class="n">' . esc_html( number_format_i18n( $st->count ) ) . '</span> <span class="s">' . esc_html( $st->name ) . '</span></a></li>';
		}
		return '<div class="aw-ed-find-head"><h2>Find help near you</h2><p class="aw-ed-big"><strong>' . esc_html( number_format_i18n( $total ) ) . '</strong> organisations in <strong>' . (int) $with . '</strong> states and union territories</p></div>'
			. '<form class="aw-ed-search" role="search" method="get" action="' . esc_url( get_post_type_archive_link( 'aw_listing' ) ) . '">'
			. '<input type="hidden" name="aw_filter" value="1">'
			. '<label class="screen-reader-text" for="aw-ed-q">Organisation name</label><span class="aw-ico aw-ico--search" aria-hidden="true"></span><input id="aw-ed-q" type="search" name="q" placeholder="Search by name, for example Blue Cross">'
			. '<label class="screen-reader-text" for="aw-ed-state">State or union territory</label><select id="aw-ed-state" name="state">' . $opts . '</select>'
			. '<button type="submit" class="aw-btn">Search</button></form>'
			. '<nav class="aw-ed-states" aria-label="Browse by state"><ul>' . $items . '</ul></nav>';
	}
);
