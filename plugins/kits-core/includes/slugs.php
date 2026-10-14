<?php
/**
 * Every published item has a slug: wp_publish_post() and direct status changes do not make one, and an item without
 * a slug is left out of lists, search and the sitemap.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Every published item has a slug ---------- */

// wp_publish_post() and direct status changes do not make a slug; an item published that way had an empty post_name.
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'publish' !== $new || '' !== (string) $post->post_name || ! in_array( $post->post_type, get_post_types( array( 'public' => true ) ), true ) || 'attachment' === $post->post_type ) {
			return;
		}
		$base = sanitize_title( $post->post_title );
		$base = '' !== $base ? $base : $post->post_type . '-' . $post->ID;
		if ( function_exists( 'aw_clean_news_slug' ) && function_exists( 'kits_sourced_types' ) && in_array( $post->post_type, kits_sourced_types(), true ) ) {
			$base = aw_clean_news_slug( $base, $post->ID );
		}
		$slug = wp_unique_post_slug( $base, $post->ID, 'publish', $post->post_type, (int) $post->post_parent );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $post->ID );
		$post->post_name = $slug;
	},
	1,
	3
);

// Guard: lists, search and the sitemap on the site never show an item without a slug.
add_filter(
	'posts_where',
	function ( $where, $q ) {
		if ( is_admin() || $q->is_singular() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return $where;
		}
		$status = (array) $q->get( 'post_status' );
		if ( $status && array( '' ) !== $status && ! in_array( 'publish', $status, true ) ) {
			return $where; // drafts and other non-public lists
		}
		global $wpdb;
		return $where . " AND {$wpdb->posts}.post_name <> ''";
	},
	10,
	2
);
