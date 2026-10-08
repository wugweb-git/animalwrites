<?php
/**
 * Canvas landmarks (shell spec): every page has one <main id="main" tabindex="-1" aria-label="{page title}"> that the
 * skip link targets (WordPress's own block-theme skip link uses the main's id), a sticky <header> and
 * <footer id="site-footer" aria-label="Site footer">. Filter rows are toolbars, not sections.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The page's name for the main landmark. */
function kits_page_label() {
	if ( is_front_page() ) {
		return get_bloginfo( 'name' );
	}
	if ( is_search() || is_page( 'search' ) ) {
		return 'Search';
	}
	if ( is_singular() ) {
		return wp_strip_all_tags( (string) get_the_title( get_queried_object_id() ) );
	}
	return wp_strip_all_tags( (string) ( get_the_archive_title() ?: get_bloginfo( 'name' ) ) );
}

add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		static $done = false;
		if ( $done || 'main' !== ( $block['attrs']['tagName'] ?? '' ) ) {
			return $html;
		}
		$done = true;
		return (string) preg_replace( '/^(\s*<main\b)(?![^>]*\bid=)/', '$1 id="main" tabindex="-1" aria-label="' . esc_attr( kits_page_label() ) . '"', $html, 1 );
	},
	10,
	2
);

add_filter(
	'render_block_core/template-part',
	function ( $html, $block ) {
		if ( 'footer' !== ( $block['attrs']['slug'] ?? '' ) ) {
			return $html;
		}
		return (string) preg_replace( '/^(\s*<footer\b)(?![^>]*\bid=)/', '$1 id="site-footer" aria-label="Site footer"', $html, 1 );
	},
	10,
	2
);
