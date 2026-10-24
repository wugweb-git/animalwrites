<?php
/**
 * Same event, one story (owner, 15 Oct 2026).
 *
 * The first story of an event stays the one story. When another publisher reports the same event, the workers read
 * the new report and, when it has facts our story lacks, rewrite the first story's summary with them woven in (only
 * facts from the two reports; every number and name checked against them). Each fold is kept as an audit in
 * `aw_merge_additions` (source URL, publisher, the facts it added, time, model, the summary before); every earlier
 * summary is kept in `aw_summary_history` so it can be restored. The credit is the publisher's logo in the source row
 * (`aw_also_reported`). Nothing on the page says how the summary was written.
 *
 * The first story's dates never change: the summary is written straight to the row (post_excerpt only), so neither
 * post_date nor post_modified moves and no "Updated" date can appear (owner: "no we dont do date updates on frontend").
 *
 * A duplicate already on the site (dup_id) is set to private with `aw_merged_into` and its URL sent to the first story.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credit a publisher on the first story; with new facts, put in the rewritten summary (the old one is kept).
 *
 * @param int    $keep      The first story.
 * @param string $url       The new report's URL.
 * @param string $publisher The new report's publisher.
 * @param string $text      The facts it adds ('' = credit only).
 * @param string $model     The model that wrote them.
 * @param string $summary   The first story's summary with those facts woven in ('' = summary unchanged).
 * @return array{credited:bool,added:bool,rewritten:bool}
 */
function aw_same_story_fold( $keep, $url, $publisher, $text = '', $model = '', $summary = '' ) {
	$url  = esc_url_raw( $url );
	$out  = array(
		'credited' => false,
		'added'    => false,
		'rewritten' => false,
	);
	$own  = (string) get_post_meta( $keep, 'aw_source_url', true );
	if ( '' === $url || $url === $own ) {
		return $out;
	}
	$publisher = sanitize_text_field( $publisher );
	if ( '' === $publisher ) {
		$publisher = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}
	$also = array_values( array_filter( (array) get_post_meta( $keep, 'aw_also_reported', true ) ) );
	if ( ! in_array( $url, wp_list_pluck( $also, 'url' ), true ) ) {
		$also[] = array(
			'name' => $publisher,
			'url'  => $url,
		);
		update_post_meta( $keep, 'aw_also_reported', $also );
		$out['credited'] = true;
	}
	$text    = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( $text ) ) );
	$summary = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( $summary ) ) );
	if ( '' !== $text ) {
		global $wpdb;
		$before = (string) get_post_field( 'post_excerpt', $keep, 'raw' );
		$now    = current_time( 'mysql' ); // IST, the site's time zone
		if ( '' !== $summary && $summary !== $before ) {
			$hist   = array_values( array_filter( (array) get_post_meta( $keep, 'aw_summary_history', true ) ) );
			$hist[] = array(
				'summary' => $before,
				'at'      => $now,
				'url'     => $url,
			);
			update_post_meta( $keep, 'aw_summary_history', $hist );
			// Straight to the row: wp_update_post would move post_modified (the date never changes).
			$wpdb->update( $wpdb->posts, array( 'post_excerpt' => $summary ), array( 'ID' => (int) $keep ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$out['rewritten'] = true;
			if ( function_exists( 'aw_summary_store_entities' ) ) {
				aw_summary_store_entities( $keep ); // the names the new summary uses, for internal linking
			}
		}
		$adds  = array_values( array_filter( (array) get_post_meta( $keep, 'aw_merge_additions', true ) ) );
		$entry = array(
			'url'            => $url,
			'publisher'      => $publisher,
			'text'           => $text,
			'at'             => $now,
			'model'          => sanitize_text_field( $model ),
			'summary_before' => $out['rewritten'] ? $before : '',
		);
		$at = array_search( $url, wp_list_pluck( $adds, 'url' ), true );
		if ( false === $at ) {
			$adds[] = $entry;
		} else {
			$adds[ $at ] = $entry; // the same report folded again (a rewrite of an earlier fold)
		}
		update_post_meta( $keep, 'aw_merge_additions', $adds );
		$out['added'] = true;
	}
	if ( $out['credited'] || $out['added'] ) {
		clean_post_cache( $keep ); // post_date and post_modified stay as they are
		do_action( 'litespeed_purge_post', $keep );
	}
	return $out;
}

/**
 * A duplicate already on the site: private, merged into the first story, its URL sent there.
 *
 * @param int $dup  The duplicate.
 * @param int $keep The first story.
 */
function aw_same_story_retire( $dup, $keep ) {
	$path = (string) wp_parse_url( (string) get_permalink( $dup ), PHP_URL_PATH );
	if ( 'private' !== get_post_status( $dup ) ) {
		wp_update_post(
			array(
				'ID'          => $dup,
				'post_status' => 'private',
			)
		);
	}
	update_post_meta( $dup, 'aw_merged_into', $keep );
	update_post_meta( $dup, 'aw_review', 'merged into #' . $keep . ' (same event) ' . wp_date( 'Y-m-d H:i' ) );
	if ( function_exists( 'aw_redirect_add' ) && $path && '/' !== $path ) {
		aw_redirect_add( $path, $keep );
	}
}

/** What the workers need to compare a new report with the first story. */
function aw_same_story_get( WP_REST_Request $req ) {
	$id = (int) $req->get_param( 'id' );
	$p  = get_post( $id );
	if ( ! $p || ! in_array( $p->post_type, AW_SAME_STORY_TYPES, true ) || 'trash' === $p->post_status ) {
		return new WP_Error( 'aw_not_found', 'No such story', array( 'status' => 404 ) );
	}
	return new WP_REST_Response(
		array(
			'id'         => $id,
			'type'       => $p->post_type, // the summary rules of its section (summary-rules.php)
			'status'     => $p->post_status,
			'title'      => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			'summary'    => wp_strip_all_tags( $p->post_excerpt ),
			'source_url' => (string) get_post_meta( $id, 'aw_source_url', true ),
			// The report's publication date: a summary may not give it as the event date (owner, 15 Oct 2026).
			'source_date' => (string) get_post_meta( $id, 'aw_source_date', true ),
			'date'       => get_the_date( 'Y-m-d', $id ),
			'publisher'  => (string) get_post_meta( $id, 'aw_source_name', true ),
			'merged'     => (int) get_post_meta( $id, 'aw_merged_into', true ),
			'also'       => array_values( wp_list_pluck( array_filter( (array) get_post_meta( $id, 'aw_also_reported', true ) ), 'url' ) ),
			'additions'  => array_values( array_filter( (array) get_post_meta( $id, 'aw_merge_additions', true ) ) ),
		)
	);
}

/** Fold a report into the first story (and retire its copy on the site, when there is one). */
function aw_same_story_post( WP_REST_Request $req ) {
	$keep = (int) $req->get_param( 'keep_id' );
	$p    = get_post( $keep );
	if ( ! $p || ! in_array( $p->post_type, AW_SAME_STORY_TYPES, true ) || 'trash' === $p->post_status ) {
		return new WP_Error( 'aw_not_found', 'No such story', array( 'status' => 404 ) );
	}
	$dup = (int) $req->get_param( 'dup_id' );
	if ( $dup && ( $dup === $keep || ! in_array( get_post_type( $dup ), AW_SAME_STORY_TYPES, true ) ) ) {
		return new WP_Error( 'aw_bad_dup', 'Bad duplicate', array( 'status' => 400 ) );
	}
	$res = aw_same_story_fold( $keep, (string) $req->get_param( 'source_url' ), (string) $req->get_param( 'publisher' ), (string) $req->get_param( 'added_text' ), (string) $req->get_param( 'model' ), (string) $req->get_param( 'summary' ) );
	if ( $dup ) {
		aw_same_story_retire( $dup, $keep );
	}
	return new WP_REST_Response( $res + array( 'keep_id' => $keep ) );
}

add_action(
	'rest_api_init',
	function () {
		$can = fn() => current_user_can( 'edit_posts' );
		register_rest_route(
			'aw/v1',
			'/same-story',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $can,
					'callback'            => 'aw_same_story_get',
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $can,
					'callback'            => 'aw_same_story_post',
				),
			)
		);
	}
);
