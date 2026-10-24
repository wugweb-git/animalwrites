<?php
/**
 * What the Searches tab (the Search demand screen) reads from the keyword table since 24 Oct 2026, in place of the
 * hard-coded 15 Oct keyword map (aw_sd_static(), removed): its targets ("Our target?") and its Gaps list.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Section name of a keyword-map page type, as the Searches tab shows it.
 *
 * @param string $page_type hub, directory, topic, guide, key-page or new-row.
 */
function aw_si_section_label( string $page_type ): string {
	$labels = array(
		'hub'       => __( 'Hub', 'aw-seo-extras' ),
		'directory' => __( 'Directory', 'aw-seo-extras' ),
		'topic'     => __( 'Category', 'aw-seo-extras' ),
		'guide'     => __( 'Guide', 'aw-seo-extras' ),
		'key-page'  => __( 'Page', 'aw-seo-extras' ),
		'new-row'   => __( 'Page', 'aw-seo-extras' ),
	);
	return $labels[ $page_type ] ?? __( 'Page', 'aw-seo-extras' );
}

/**
 * Tracked keywords with the pages that own or carry them, in the row shape aw_sd_targets() reads:
 * [section, page, phrase]. Posts and terms show their title; Directory patterns and paths show as written.
 *
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function aw_si_searches_targets(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k = aw_si_table( 'keywords' );
	$p = aw_si_table( 'kw_pages' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT k.label, k.page_type, p.obj_type, p.obj_id, p.path FROM {$k} k JOIN {$p} p ON p.kw_id = k.id AND p.src = 'seed' AND p.role IN ('owner', 'secondary') WHERE k.roles & %d ORDER BY p.role = 'secondary', k.id", AW_SI_ROLE_TRACKED ) );
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$page = (string) $r->path;
		if ( 'post' === $r->obj_type && $r->obj_id ) {
			$page = get_the_title( (int) $r->obj_id );
		} elseif ( 'term' === $r->obj_type && $r->obj_id ) {
			$term = get_term( (int) $r->obj_id );
			$page = $term && ! is_wp_error( $term ) ? $term->name : $page;
		}
		$out[] = array( aw_si_section_label( (string) $r->page_type ), wp_strip_all_tags( $page ), (string) $r->label );
	}
	return $out;
}

/**
 * Gaps for the Searches tab: the keyword map's demand gaps (seeded with their note) and every keyword the daily
 * calculation marks as a gap (high demand, no page: P2), as [title, detail] rows.
 *
 * @return array<int, array{0: string, 1: string}>
 */
function aw_si_searches_gaps(): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$k = aw_si_table( 'keywords' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows   = $wpdb->get_results( "SELECT label, note, status, impr28, pos28 FROM {$k} WHERE note LIKE 'Gap%' OR status = 'gap' ORDER BY impr28 DESC, label" );
	$groups = array();
	foreach ( (array) $rows as $r ) {
		$title = 0 === strpos( (string) $r->note, 'Gap' ) ? trim( (string) preg_replace( '/^Gap \([^)]*\):\s*/', '', (string) $r->note ) ) : __( 'High demand, no page yet (P2)', 'aw-seo-extras' );
		/* translators: 1: search phrase, 2: impressions in 28 days, 3: average position. */
		$groups[ $title ][] = null === $r->pos28 ? sprintf( __( '"%1$s": %2$s impressions (28 days)', 'aw-seo-extras' ), $r->label, number_format_i18n( (int) $r->impr28 ) ) : sprintf( __( '"%1$s": %2$s impressions (28 days), position %3$s', 'aw-seo-extras' ), $r->label, number_format_i18n( (int) $r->impr28 ), number_format_i18n( (float) $r->pos28, 1 ) );
	}
	$out = array();
	foreach ( $groups as $title => $lines ) {
		$out[] = array( (string) $title, implode( '; ', $lines ) . '.' );
	}
	return $out;
}
