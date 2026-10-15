<?php
/**
 * Same event, one story (owner, 15 Oct 2026).
 *
 * The first story of an event stays the one story. When another publisher reports the same event, the workers read
 * the new report, write the facts it adds (only facts from its own text, in the house style of our summaries) and send
 * them here. They are kept on the first story as `aw_merge_additions` (source URL, publisher, the added text, time,
 * model: the audit) and shown under its summary as "Also reported by X, which adds: …". With nothing new, only the
 * credit is added ("Also reported by" in the box above the summary, `aw_also_reported`).
 *
 * Nothing here touches the first story's post_date or post_modified: only post meta changes, so its date stays its
 * own and no "Updated" date can appear (owner: "no we dont do date updates on frontend").
 *
 * A duplicate already on the site (dup_id) is set to private with `aw_merged_into` and its URL sent to the first story.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SAME_STORY_TYPES = array( 'aw_news', 'aw_legal' );

/**
 * Credit a publisher on the first story, and keep the facts its report adds.
 *
 * @param int    $keep      The first story.
 * @param string $url       The new report's URL.
 * @param string $publisher The new report's publisher.
 * @param string $text      The facts it adds ('' = credit only).
 * @param string $model     The model that wrote them.
 * @return array{credited:bool,added:bool}
 */
function aw_same_story_fold( $keep, $url, $publisher, $text = '', $model = '' ) {
	$url  = esc_url_raw( $url );
	$out  = array(
		'credited' => false,
		'added'    => false,
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
	$text = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( $text ) ) );
	if ( '' !== $text ) {
		$adds = array_values( array_filter( (array) get_post_meta( $keep, 'aw_merge_additions', true ) ) );
		if ( ! in_array( $url, wp_list_pluck( $adds, 'url' ), true ) ) {
			$adds[] = array(
				'url'       => $url,
				'publisher' => $publisher,
				'text'      => $text,
				'at'        => current_time( 'mysql' ), // IST, the site's time zone
				'model'     => sanitize_text_field( $model ),
			);
			update_post_meta( $keep, 'aw_merge_additions', $adds );
			$out['added'] = true;
		}
	}
	if ( $out['credited'] || $out['added'] ) {
		clean_post_cache( $keep ); // meta only: post_date and post_modified stay as they are
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
			'status'     => $p->post_status,
			'title'      => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			'summary'    => wp_strip_all_tags( $p->post_excerpt ),
			'source_url' => (string) get_post_meta( $id, 'aw_source_url', true ),
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
	$res = aw_same_story_fold( $keep, (string) $req->get_param( 'source_url' ), (string) $req->get_param( 'publisher' ), (string) $req->get_param( 'added_text' ), (string) $req->get_param( 'model' ) );
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

/**
 * The added facts, under the summary: "Also reported by X, which adds: …", each with its link.
 *
 * @param int $id Post ID.
 * @return string
 */
function aw_same_story_html( $id ) {
	$adds = array_filter( (array) get_post_meta( $id, 'aw_merge_additions', true ), fn( $a ) => ! empty( $a['text'] ) && ! empty( $a['url'] ) );
	if ( ! $adds ) {
		return '';
	}
	$out = '<section class="aw-reported" aria-labelledby="aw-reported-h"><h2 id="aw-reported-h">Also reported</h2>';
	foreach ( $adds as $a ) {
		$name = '' !== (string) ( $a['publisher'] ?? '' ) ? $a['publisher'] : preg_replace( '/^www\./', '', (string) wp_parse_url( $a['url'], PHP_URL_HOST ) );
		$out .= '<p>Also reported by <a href="' . esc_url( $a['url'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $name ) . '</a>, which adds: ' . esc_html( $a['text'] ) . '</p>';
	}
	return $out . '</section>';
}

// After the summary on a News or Library item's own page.
add_filter(
	'render_block_core/post-excerpt',
	function ( $html, $block, $instance ) {
		$id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $id || ! is_singular( AW_SAME_STORY_TYPES ) || get_queried_object_id() !== $id ) {
			return $html;
		}
		return $html . aw_same_story_html( $id );
	},
	20,
	3
);
