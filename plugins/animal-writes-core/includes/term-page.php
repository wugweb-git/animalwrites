<?php
/**
 * Category and tag pages (owner, 20 Oct 2026): one archive layout for every term. Head band (breadcrumb, H1 with the
 * icon, intro = the term's description, filters) → list (List – card 1 after item 6) → FAQ → Share → List – card 2 →
 * related terms. The last four are blocks in the tag and category templates (Site Editor):
 * animal-writes/term-faq, animal-writes/term-share, the List – card 2 ad, animal-writes/term-related.
 *
 * FAQ: the topic questions approved for the term (faq.php), rendered by aw_faq_html(); the FAQPage schema is parsed from
 * the same HTML. Only organisation types, services and categories have one.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Taxonomies with term pages. */
function aw_term_page_taxonomies() {
	return array( 'post_tag', 'category' );
}

/** The current category or tag page's term, or null. */
function aw_term_page_term() {
	$t = get_queried_object();
	return ( is_tag() || is_category() ) && $t instanceof WP_Term ? $t : null;
}

/* ---------- Blocks ---------- */

add_filter(
	'aw_hub_blocks',
	function ( $blocks ) {
		$blocks['term-faq']     = array( 'Term page: FAQ', 'aw_block_term_faq', array() );
		$blocks['term-share']   = array( 'Term page: share', 'aw_block_term_share', array() );
		$blocks['term-related'] = array( 'Term page: related', 'aw_block_term_related', array() );
		return $blocks;
	}
);

/** The editor's stand-in for a term block (no term in the Site Editor). */
function aw_term_block_preview( $text ) {
	return defined( 'REST_REQUEST' ) && REST_REQUEST ? '<p class="kit-note">' . esc_html( $text ) . '</p>' : '';
}

/** A section with an H2 at the item pages' section-heading size. */
function aw_term_section( $slug, $head, $body ) {
	$hid = 'aw-term-' . $slug . '-h';
	return '<section class="aw-more-group aw-term-' . esc_attr( $slug ) . '" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="aw-more-head has-x-large-font-size" id="' . esc_attr( $hid ) . '">' . esc_html( $head ) . '</h2>' . $body . '</section>';
}

/** FAQ: the term's approved topic questions (faq.php; none on later pages, none with fewer than 3). */
function aw_block_term_faq() {
	return aw_term_page_term() ? ( function_exists( 'aw_faq_html' ) ? aw_faq_html() : '' ) : aw_term_block_preview( 'FAQ: the questions approved for the category or tag (3 to 6).' );
}

/** Share: the page's address and its name. */
function aw_block_term_share() {
	$term = aw_term_page_term();
	if ( ! $term ) {
		return aw_term_block_preview( 'Share: X, Facebook, LinkedIn, WhatsApp, Email, Copy link' );
	}
	if ( ! function_exists( 'kits_share_html' ) ) {
		return '';
	}
	$title = function_exists( 'aw_seo_plain' ) && function_exists( 'aw_seo_term_title' ) ? preg_replace( '/ – Animal Writes$/u', '', aw_seo_plain( aw_seo_term_title( $term ) ) ) : html_entity_decode( $term->name, ENT_QUOTES );
	return '<div class="aw-item-share">' . kits_share_html( (string) kits_term_url( $term ), $title ) . '</div>';
}

/**
 * The terms a term sits with, and their group's heading: organisation types, services, Library types, a section's other
 * tags, or the other categories.
 *
 * @param WP_Term $term Term.
 * @return array [ heading, slugs ('' = every category) ]
 */
function aw_term_siblings( $term ) {
	if ( 'category' === $term->taxonomy ) {
		return array( __( 'Other categories', 'animal-writes-core' ), '' );
	}
	$types = function_exists( 'aw_listing_type_slugs' ) ? aw_listing_type_slugs() : array();
	if ( in_array( $term->slug, $types, true ) ) {
		return array( __( 'Other organisation types', 'animal-writes-core' ), $types );
	}
	$services = function_exists( 'aw_service_slugs' ) ? aw_service_slugs() : array();
	if ( in_array( $term->slug, $services, true ) ) {
		return array( __( 'Other services', 'animal-writes-core' ), $services );
	}
	if ( defined( 'AW_LEGAL_KINDS' ) && isset( AW_LEGAL_KINDS[ $term->slug ] ) ) {
		return array( __( 'Other document types', 'animal-writes-core' ), array_keys( AW_LEGAL_KINDS ) );
	}
	$sec = function_exists( 'aw_tag_section' ) ? aw_tag_section( $term->slug ) : '';
	return array( __( 'Related tags', 'animal-writes-core' ), $sec ? array_values( array_diff( aw_section_tags( $sec ), $types, $services ) ) : array() );
}

/** Related: the term's group, as chips (most items first; empty terms left out). */
function aw_block_term_related() {
	$term = aw_term_page_term();
	if ( ! $term ) {
		return aw_term_block_preview( 'Related: the other organisation types, services or categories.' );
	}
	list( $head, $slugs ) = aw_term_siblings( $term );
	if ( is_array( $slugs ) && ! $slugs ) {
		return '';
	}
	$args = array( 'taxonomy' => $term->taxonomy, 'hide_empty' => true, 'exclude' => array( $term->term_id ), 'orderby' => 'count', 'order' => 'DESC', 'number' => 12 );
	if ( is_array( $slugs ) ) {
		$args['slug'] = $slugs;
	} else {
		$args['exclude'][] = (int) get_option( 'default_category' );
	}
	$terms = get_terms( $args );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$out = '<ul class="kit-tags aw-term-chips">';
	foreach ( $terms as $t ) {
		$ico  = 'post_tag' === $t->taxonomy && function_exists( 'aw_tag_icon' ) ? aw_tag_icon( $t->slug ) : '';
		$out .= '<li><a class="kit-chip" href="' . esc_url( kits_term_url( $t ) ) . '">' . $ico . esc_html( html_entity_decode( $t->name, ENT_QUOTES ) ) . '</a></li>';
	}
	return aw_term_section( 'related', $head, $out . '</ul>' );
}

