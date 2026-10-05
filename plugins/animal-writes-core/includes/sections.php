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
	$img = (string) get_post_meta( $p->ID, 'aw_source_image', true );
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
		if ( is_tax( 'aw_city' ) && 'aw_listing' === $type ) {
			$title = sprintf( 'Help in %s', single_term_title( '', false ) );
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
			$with  = array_filter( $pool, fn( $p ) => get_post_meta( $p->ID, 'aw_source_image', true ) || get_post_meta( $p->ID, 'aw_file_id', true ) || has_post_thumbnail( $p ) );
			$posts = array_slice( array_merge( $with, array_diff_key( $pool, $with ) ), 0, 3 );
			foreach ( $posts as $p ) {
				$tiles .= aw_section_tile( $p );
			}
		}
		$visual = $tiles ? '<div class="aw-section-visual" aria-hidden="true">' . $tiles . '</div>' : '';
		return '<header class="aw-section-band alignfull"><div class="aw-section-head alignwide"><div>'
			. '<h1 class="wp-block-query-title">' . esc_html( $title ) . '</h1>'
			. ( $desc ? '<p class="aw-section-desc">' . esc_html( $desc ) . '</p>' : '' )
			. ( $count ? '<p class="aw-section-count">' . esc_html( number_format_i18n( $count ) . ' ' . ( $noun[ $type ] ?? 'items' ) ) . '</p>' : '' )
			. '</div>' . $visual . '</div></header>';
	}
);
