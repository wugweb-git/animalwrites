<?php
/**
 * Breadcrumbs (restructure Phase 5): one trail per page, shown above the page title. The trail is Rank Math's
 * (built in aw-seo-extras breadcrumbs.php, which also gives it to search engines as the one BreadcrumbList).
 *
 * Organisation:      Home › Directory › State › District › Organisation
 * District × type:   Home › Directory › State › District › Type
 * Other items:       Home › Section › Item
 * Section hub:       Home › Section
 * Category:          Home › Categories › Category
 * Page:              Home › (parent) › Page
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The trail for the current page: list of [name, url]; the last entry is the page itself. Empty when there is none.
 * Rank Math's breadcrumbs are the source (aw-seo-extras gives them the places and sections); this only reads them.
 */
function aw_breadcrumbs() {
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

/** The visible trail. */
function aw_breadcrumbs_html() {
	$trail = aw_breadcrumbs();
	if ( ! $trail ) {
		return '';
	}
	$last = count( $trail ) - 1;
	$html = '<nav class="kit-crumbs" aria-label="Breadcrumb"><ol>';
	foreach ( $trail as $i => $c ) {
		if ( $last > 3 && $i > 0 && $i < $last - 1 ) { // longer than Home + 2 levels: the middle folds into "…" (schema keeps the full trail)
			$html .= 1 === $i ? '<li class="kit-crumbs-more"><span aria-hidden="true">…</span><span class="screen-reader-text">' . esc_html( implode( ', ', array_map( fn( $x ) => wp_strip_all_tags( $x[0] ), array_slice( $trail, 1, $last - 2 ) ) ) ) . '</span></li>' : '';
			continue;
		}
		if ( 0 === $i && $i !== $last ) { // Home is an icon
			$html .= '<li class="kit-crumbs-home"><a href="' . esc_url( $c[1] ) . '" aria-label="Home"><span class="kit-ico kit-ico--home" aria-hidden="true"></span></a></li>';
			continue;
		}
		$html .= $i === $last
			? '<li><span aria-current="page">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</span></li>'
			: '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( wp_strip_all_tags( $c[0] ) ) . '</a></li>';
	}
	return $html . '</ol></nav>';
}

/** The trail, once per page ('' after the first call): the section band places it itself, inside the band. */
function aw_breadcrumbs_claim() {
	static $done = false;
	if ( $done || is_admin() || ! aw_breadcrumbs() ) {
		return '';
	}
	$done = true;
	return aw_breadcrumbs_html();
}

/** Put the trail once, just before the first heading area of the page (page title or news source row). */
function aw_breadcrumbs_prepend( $html ) {
	return aw_breadcrumbs_claim() . $html;
}
add_filter(
	'render_block_core/post-title',
	function ( $html, $block ) {
		return 1 === (int) ( $block['attrs']['level'] ?? 2 ) ? aw_breadcrumbs_prepend( $html ) : $html;
	},
	10,
	2
);
// On item pages the category chips sit above the title: the trail goes before them, so it is always first.
add_filter(
	'render_block_core/post-terms',
	function ( $html, $block, $instance ) {
		if ( ! is_singular() || isset( $instance->context['queryId'] ) ) { // the page's own chips, not those in lists
			return $html;
		}
		return aw_breadcrumbs_prepend( $html );
	},
	10,
	3
);
// Shortcodes in a template run before its blocks, so the trail is placed by blocks (category chips or the title) or by the
// section band itself, never by a shortcode such as the publisher row.
