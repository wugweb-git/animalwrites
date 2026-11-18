<?php
/**
 * Breadcrumbs, the visible trail: one trail per page, shown above the page title. The trail is Rank Math's (built in
 * breadcrumbs.php, which also gives it to search engines as the one BreadcrumbList); this file only reads and shows it.
 *
 * Loaded on every request, outside the Rank Math check and the Breadcrumbs module switch: with the module off the trail
 * is Rank Math's own; with Rank Math off there is no trail and nothing is shown. Animal Writes Core loads this file
 * too when RankWeb is not active (its breadcrumbs.php), so its section, hub and form bands keep their trail.
 *
 * Organisation:      Home › Directory › State › District
 * District × type:   Home › Directory › State › District › Type
 * Other items:       Home › Section › Item
 * Section hub:       Home › Section
 * Category:          Home › Categories › Category
 * Page:              Home › (parent) › Page
 *
 * The aw_breadcrumbs* functions are the earlier names, kept for the bands that call them.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'rankweb_breadcrumbs' ) || function_exists( 'aw_breadcrumbs' ) ) {
	return; // Loaded already (by RankWeb, or by Animal Writes Core from another path), or an older Core still shows its own trail: one trail per page.
}

/**
 * The trail for the current page: list of [name, url]; the last entry is the page itself. Empty when there is none.
 *
 * @return array
 */
function rankweb_breadcrumbs() {
	static $trail = null;
	if ( null !== $trail ) {
		return $trail;
	}
	$trail = array();
	if ( class_exists( '\\RankMath\\Frontend\\Breadcrumbs' ) && \RankMath\Frontend\Breadcrumbs::get() ) {
		foreach ( (array) \RankMath\Frontend\Breadcrumbs::get()->get_crumbs() as $c ) {
			if ( is_array( $c ) && isset( $c[0], $c[1] ) && '' !== (string) $c[1] ) {
				$trail[] = array( (string) $c[0], (string) $c[1] );
			}
		}
	}
	if ( count( $trail ) < 2 ) {
		$trail = array();
	}
	return $trail;
}

/**
 * The visible trail.
 *
 * @return string
 */
function rankweb_breadcrumbs_html() {
	$trail = rankweb_breadcrumbs();
	if ( ! $trail ) {
		return '';
	}
	$last = count( $trail ) - 1;
	$here = is_singular() ? (string) get_permalink( get_queried_object_id() ) : '';
	$self = ! $here || untrailingslashit( $trail[ $last ][1] ) === untrailingslashit( $here ); // News and Library end at the category, a link.
	$html = '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol>';
	foreach ( $trail as $i => $c ) {
		if ( $last > 3 && $i > 0 && $i < $last - 1 ) { // longer than Home + 2 levels: the middle folds into "…" (schema keeps the full trail).
			$html .= 1 === $i ? '<li class="kit-crumbs-more"><span aria-hidden="true">…</span><span class="screen-reader-text">' . esc_html( implode( ', ', array_map( fn( $x ) => wp_strip_all_tags( $x[0] ), array_slice( $trail, 1, $last - 2 ) ) ) ) . '</span></li>' : '';
			continue;
		}
		if ( 0 === $i && $i !== $last ) { // Home is an icon.
			$html .= '<li class="kit-crumbs-home"><a href="' . esc_url( $c[1] ) . '" aria-label="Home"><span class="kit-ico kit-ico--home" aria-hidden="true"></span></a></li>';
			continue;
		}
		$html .= $i === $last && $self
			? '<li><span aria-current="page">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</span></li>'
			: '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</a></li>';
	}
	return $html . '</ol></nav>';
}

/**
 * True when Page Options hide the trail on this page (the item, its kind of page or the site-wide default, from Kits →
 * Theme Options). Search engines still get the BreadcrumbList.
 *
 * @return bool
 */
function rankweb_breadcrumbs_hidden() {
	return function_exists( 'kits_page_options_apply' ) && function_exists( 'kits_option' ) && kits_page_options_apply() && 'hide' === kits_option( 'crumbs' );
}

/**
 * The trail, once per page ('' after the first call): a section band places it itself, inside the band.
 *
 * @return string
 */
function rankweb_breadcrumbs_claim() {
	static $done = false;
	if ( $done || is_admin() || rankweb_breadcrumbs_hidden() || ! rankweb_breadcrumbs() ) {
		return '';
	}
	$done = true;
	return rankweb_breadcrumbs_html();
}

/**
 * Put the trail once, just before the first heading area of the page (page title or news source row).
 *
 * @param string $html Block output.
 * @return string
 */
function rankweb_breadcrumbs_prepend( $html ) {
	return rankweb_breadcrumbs_claim() . $html;
}

/* ---------- The earlier names (Animal Writes Core's bands call aw_breadcrumbs_claim) ---------- */

if ( ! function_exists( 'aw_breadcrumbs' ) ) {
	/**
	 * Back-compat: rankweb_breadcrumbs().
	 *
	 * @return array
	 */
	function aw_breadcrumbs() {
		return rankweb_breadcrumbs();
	}
}
if ( ! function_exists( 'aw_breadcrumbs_html' ) ) {
	/**
	 * Back-compat: rankweb_breadcrumbs_html().
	 *
	 * @return string
	 */
	function aw_breadcrumbs_html() {
		return rankweb_breadcrumbs_html();
	}
}
if ( ! function_exists( 'aw_breadcrumbs_hidden' ) ) {
	/**
	 * Back-compat: rankweb_breadcrumbs_hidden().
	 *
	 * @return bool
	 */
	function aw_breadcrumbs_hidden() {
		return rankweb_breadcrumbs_hidden();
	}
}
if ( ! function_exists( 'aw_breadcrumbs_claim' ) ) {
	/**
	 * Back-compat: rankweb_breadcrumbs_claim().
	 *
	 * @return string
	 */
	function aw_breadcrumbs_claim() {
		return rankweb_breadcrumbs_claim();
	}
}
if ( ! function_exists( 'aw_breadcrumbs_prepend' ) ) {
	/**
	 * Back-compat: rankweb_breadcrumbs_prepend().
	 *
	 * @param string $html Block output.
	 * @return string
	 */
	function aw_breadcrumbs_prepend( $html ) {
		return rankweb_breadcrumbs_prepend( $html );
	}
}

add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		return 1 === (int) ( $block['attrs']['level'] ?? 2 ) ? rankweb_breadcrumbs_prepend( $html ) : $html;
	},
	10,
	2
);
// On item pages the category chips sit above the title: the trail goes before them, so it is always first.
add_filter(
	'render_block_core/post-terms',
	function ( $html, $block, $instance ) {
		if ( ! is_singular() || isset( $instance->context['queryId'] ) || (int) ( $instance->context['postId'] ?? 0 ) !== get_queried_object_id() ) { // the page's own chips, not those in lists (Related renders first, as a shortcode).
			return $html;
		}
		return rankweb_breadcrumbs_prepend( $html );
	},
	10,
	3
);
// Shortcodes in a template run before their blocks, so the trail is placed by blocks (category chips or the title) or by
// the section band itself, never by a shortcode such as the publisher row.
