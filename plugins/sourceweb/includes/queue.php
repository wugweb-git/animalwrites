<?php
/**
 * Review queue: candidate items are held as a private `sourceweb_item` post type and reviewed before anything
 * reaches the site. An item is added only when the decision pipeline (sourceweb_decide via sourceweb_gate)
 * keeps it and it is not a near-duplicate of a recent item. Approving creates a draft in the first curated post
 * type (a human still publishes it); rejecting discards it.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Normalise a title for duplicate detection: lower case, a trailing " - Publisher" or leading "Section | "
 * removed, letters and digits only. Pure; needs no WordPress.
 *
 * @param string $title Title.
 * @return string
 */
function sourceweb_norm_title( $title ) {
	$t = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $title ) : strtolower( (string) $title );
	$t = preg_replace( '/\s+[-–—|]\s+[^-–—|]{2,40}$/u', '', $t );
	$t = preg_replace( '/^[^|]{2,30}\|\s+/u', '', $t );
	$t = preg_replace( '/[^\p{L}\p{N}\s]|_/u', ' ', $t );
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

/**
 * Register the private candidate-item post type.
 *
 * @return void
 */
function sourceweb_register_item_type() {
	register_post_type(
		'sourceweb_item',
		array(
			'label'               => __( 'Review queue', 'sourceweb' ),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
			'supports'            => array( 'title', 'editor' ),
			'capability_type'     => 'post',
		)
	);
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'init', 'sourceweb_register_item_type' );
}

/**
 * Is a title a near-duplicate of a recent queue item (any status)?
 *
 * @param string $title Candidate title.
 * @return bool
 */
function sourceweb_queue_is_duplicate( $title ) {
	$key = sourceweb_norm_title( $title );
	if ( '' === $key ) {
		return false;
	}
	$recent = get_posts(
		array(
			'post_type'        => 'sourceweb_item',
			'post_status'      => array( 'pending', 'draft', 'trash' ),
			'posts_per_page'   => 100,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	foreach ( $recent as $id ) {
		if ( sourceweb_is_same_story( get_post_field( 'post_title', $id ), $title ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Add a candidate item to the review queue if the decision pipeline keeps it and it is not a duplicate.
 *
 * @param array $item title, source_url, summary, source_name.
 * @return array{queued:bool,reason:string,id?:int}
 */
function sourceweb_queue_add( array $item ) {
	$title = isset( $item['title'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $item['title'] ) ) : '';
	if ( '' === $title ) {
		return array(
			'queued' => false,
			'reason' => 'no title',
		);
	}
	$url     = isset( $item['source_url'] ) ? esc_url_raw( (string) $item['source_url'] ) : '';
	$summary = isset( $item['summary'] ) ? sourceweb_apply_summary_rules( wp_strip_all_tags( (string) $item['summary'] ) ) : '';
	$name    = isset( $item['source_name'] ) ? (string) $item['source_name'] : '';

	$gate = sourceweb_gate( $url, $name, trim( $title . ' ' . $summary ) );
	if ( '' !== $gate ) {
		return array(
			'queued' => false,
			'reason' => $gate,
		);
	}
	if ( sourceweb_queue_is_duplicate( $title ) ) {
		return array(
			'queued' => false,
			'reason' => 'duplicate',
		);
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'sourceweb_item',
			'post_status'  => 'pending',
			'post_title'   => $title,
			'post_content' => wp_kses_post( $summary ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return array(
			'queued' => false,
			'reason' => 'insert failed',
		);
	}
	update_post_meta( $id, '_sourceweb_source_url', $url );
	update_post_meta( $id, '_sourceweb_source_name', sanitize_text_field( $name ) );
	return array(
		'queued' => true,
		'reason' => 'queued',
		'id'     => (int) $id,
	);
}

/**
 * Pending-item count.
 *
 * @return int
 */
function sourceweb_queue_pending_count() {
	$counts = wp_count_posts( 'sourceweb_item' );
	return isset( $counts->pending ) ? (int) $counts->pending : 0;
}

/**
 * Approve a queued item: create a draft in the first curated post type (a human still publishes it), then
 * discard the queue item. Returns the new draft id, or 0 on failure.
 *
 * @param int $id Queue item id.
 * @return int
 */
function sourceweb_queue_approve( $id ) {
	$id   = (int) $id;
	$item = get_post( $id );
	if ( ! $item || 'sourceweb_item' !== $item->post_type ) {
		return 0;
	}
	$types = sourceweb_post_types();
	$draft = wp_insert_post(
		array(
			'post_type'    => $types[0],
			'post_status'  => 'draft',
			'post_title'   => $item->post_title,
			'post_content' => $item->post_content,
		),
		true
	);
	if ( is_wp_error( $draft ) ) {
		return 0;
	}
	$url = (string) get_post_meta( $id, '_sourceweb_source_url', true );
	if ( '' !== $url ) {
		update_post_meta( $draft, '_sourceweb_source_url', $url );
	}
	wp_trash_post( $id );
	if ( function_exists( 'sourceweb_stats_bump' ) ) {
		sourceweb_stats_bump( array( 'approved' => 1 ) );
	}
	return (int) $draft;
}

/**
 * Reject a queued item (discard it).
 *
 * @param int $id Queue item id.
 * @return bool
 */
function sourceweb_queue_reject( $id ) {
	$id   = (int) $id;
	$item = get_post( $id );
	if ( ! $item || 'sourceweb_item' !== $item->post_type ) {
		return false;
	}
	$done = (bool) wp_trash_post( $id );
	if ( $done && function_exists( 'sourceweb_stats_bump' ) ) {
		sourceweb_stats_bump( array( 'rejected' => 1 ) );
	}
	return $done;
}
