<?php
/**
 * Restructure Phase 3: move 21 records between sections, with one-hop 301s from every old URL.
 *
 * Run once on the server: wp eval-file tools/restructure/phase3.php
 * Safe to run again (skips what is already done). Nothing is deleted: the merged duplicate (203) becomes private.
 */

if ( ! function_exists( 'aw_redirect_add' ) ) {
	WP_CLI::error( 'Deploy the redirects module first.' );
}

$report = array();

/** Change an item's section, keeping its slug; record the old URL. */
$move = function ( $id, $to ) use ( &$report ) {
	$p = get_post( $id );
	if ( ! $p ) {
		$report[] = "$id: missing";
		return false;
	}
	if ( $p->post_type === $to ) {
		$report[] = "$id: already $to";
		return true;
	}
	$clash = get_posts( array( 'post_type' => $to, 'name' => $p->post_name, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1 ) );
	if ( $clash ) {
		$report[] = "$id: STOPPED, slug {$p->post_name} already used in $to by {$clash[0]}";
		return false;
	}
	$old = get_permalink( $id );
	set_post_type( $id, $to );
	clean_post_cache( $id );
	aw_redirect_add( $old, $id );
	$report[] = "$id: {$p->post_type} -> $to, " . wp_parse_url( $old, PHP_URL_PATH ) . ' -> ' . wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
	return true;
};

$kind  = fn( $id, $slug ) => wp_set_object_terms( $id, $slug, 'aw_legal_kind', false );
$court = fn( $id, $slug ) => wp_set_object_terms( $id, $slug, 'aw_court', false );

// Legal Watch -> News. The court moves from "issuer" (not used on news) to the court field.
$to_news = array( 7507 => 'high-court', 7508 => 'high-court', 201 => 'high-court', 200 => 'supreme-court', 202 => 'high-court', 146 => '' );
foreach ( $to_news as $id => $c ) {
	if ( $move( $id, 'aw_news' ) ) {
		wp_set_object_terms( $id, array(), 'aw_source_type', false );
		if ( $c ) {
			$court( $id, $c );
		}
	}
}

// 146 and 7509: PIB links had lost their release ID.
update_post_meta( 146, 'aw_source_url', 'https://www.pib.gov.in/PressReleaseDetail.aspx?PRID=2318504&reg=3&lang=1' );
update_post_meta( 7509, 'aw_source_url', 'https://pib.gov.in/PressReleasePage.aspx?PRID=2319409' );
$report[] = '146, 7509: PIB source links fixed';

// 203 (Bar and Bench) merges into 202 (Live Law): one story, both sources credited.
if ( 'private' !== get_post_status( 203 ) ) {
	$old = get_permalink( 203 );
	update_post_meta( 202, 'aw_also_reported', array( array( 'name' => (string) get_post_meta( 203, 'aw_source_name', true ), 'url' => (string) get_post_meta( 203, 'aw_source_url', true ) ) ) );
	update_post_meta( 203, 'aw_merged_into', 202 );
	wp_update_post( array( 'ID' => 203, 'post_status' => 'private' ) );
	aw_redirect_add( $old, 202 );
	$report[] = '203: merged into 202 (kept as private), ' . wp_parse_url( $old, PHP_URL_PATH ) . ' -> ' . wp_parse_url( get_permalink( 202 ), PHP_URL_PATH );
}

// Legal Watch, kept: set their type.
foreach ( array( 142 => 'circular', 143 => 'circular', 144 => 'notification', 145 => 'notification' ) as $id => $k ) {
	$kind( $id, $k );
}
$report[] = '142, 143: Circular; 144, 145: Notification';

// Research -> Legal Watch.
$to_legal = array( 117 => 'act', 118 => 'rule', 119 => 'rule', 120 => 'rule', 121 => 'rule', 122 => 'rule', 123 => 'rule', 124 => 'rule', 176 => 'judgment' );
foreach ( $to_legal as $id => $k ) {
	if ( $move( $id, 'aw_legal' ) ) {
		$kind( $id, $k );
	}
}
foreach ( array( 117, 118 ) as $id ) {
	wp_set_object_terms( $id, 'awbi', 'aw_source_type', false ); // issued by AWBI, not the Gazette
}
$court( 176, 'supreme-court' );
$report[] = '117: Act; 118-124: Rule; 176: Judgment (Supreme Court); 117, 118 issuer AWBI';

// Research, kept.
wp_set_object_terms( 125, 'report', 'aw_source_type', false );

flush_rewrite_rules( false );
foreach ( $report as $line ) {
	WP_CLI::log( $line );
}
WP_CLI::success( count( (array) get_option( 'aw_redirects', array() ) ) . ' redirects stored.' );
