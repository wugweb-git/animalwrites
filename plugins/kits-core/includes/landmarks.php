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
		$p    = new WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag() && 'MAIN' === $p->get_tag() && null === $p->get_attribute( 'id' ) ) {
			$p->set_attribute( 'id', 'main' );
			$p->set_attribute( 'tabindex', '-1' );
			$p->set_attribute( 'aria-label', kits_page_label() );
		}
		return $p->get_updated_html();
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
		$p = new WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag() && 'FOOTER' === $p->get_tag() && null === $p->get_attribute( 'id' ) ) {
			$p->set_attribute( 'id', 'site-footer' );
			$p->set_attribute( 'aria-label', 'Site footer' );
		}
		return $p->get_updated_html();
	},
	10,
	2
);

/*
 * A "Sidebar" template-part area (item pages, owner 16 Oct 2026): the Site Editor lists the sidebar part under its own
 * area, so blocks can be added to it without code. It starts with the 'sidebar' ad slot only.
 */
add_filter(
	'default_wp_template_part_areas',
	function ( $areas ) {
		$areas[] = array(
			'area'        => 'sidebar',
			'label'       => __( 'Sidebar', 'kits-core' ),
			'description' => __( 'The column beside an item page\'s text (below it on phones and tablets).', 'kits-core' ),
			'icon'        => 'sidebar',
			'area_tag'    => 'aside',
		);
		return $areas;
	}
);

