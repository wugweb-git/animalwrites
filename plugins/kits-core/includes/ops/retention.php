<?php
/**
 * Retention: nothing is ever deleted. Old items are archived instead, and stay reachable at their URL.
 *
 * - News: permanent, listed and indexed at any age (owner's decision: old news stays in search).
 * - Opportunities past their deadline: closed — left out of the opportunities list; noindex and out of the sitemap
 *   (aw-seo-extras).
 * - Library, guides, resources, campaigns, directory: permanent, unchanged.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retention state of a post: '' (normal) or 'closed' (an opportunity past its date).
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function kits_retention_state( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( 'aw_opportunity' === $post->post_type ) {
		$dl = kits_opportunity_closes( $post->ID );
		return ( $dl && $dl < wp_date( 'Y-m-d' ) ) ? 'closed' : '';
	}
	return '';
}

// Lists leave out closed opportunities.
/** Days an opportunity without a deadline stays open before it needs renewing. */
const KITS_OPP_REVIEW_DAYS = 30;

/** The date an opportunity closes: its deadline, else its review date (Y-m-d), or '' if neither is set. */
function kits_opportunity_closes( $id ) {
	$dl = (string) get_post_meta( $id, 'aw_deadline', true );
	return $dl ? $dl : (string) get_post_meta( $id, 'aw_review_until', true );
}

/** Give an opportunity with no deadline a review date 30 days after it was posted (renew by moving the date). */
function kits_opportunity_set_review( $id ) {
	if ( 'aw_opportunity' !== get_post_type( $id ) || get_post_meta( $id, 'aw_deadline', true ) || get_post_meta( $id, 'aw_review_until', true ) ) {
		return;
	}
	update_post_meta( $id, 'aw_review_until', wp_date( 'Y-m-d', (int) get_post_time( 'U', true, $id ) + KITS_OPP_REVIEW_DAYS * DAY_IN_SECONDS ) );
}
add_action( 'save_post_aw_opportunity', 'kits_opportunity_set_review', 20 );

/** Keep aw_closes (the closing date, or 9999-12-31 with none) for the "Closing soon" order. */
function kits_opportunity_sync_closes( $id ) {
	if ( 'aw_opportunity' === get_post_type( $id ) ) {
		$d = kits_opportunity_closes( $id );
		update_post_meta( $id, 'aw_closes', $d ? $d : '9999-12-31' );
	}
}
add_action( 'save_post_aw_opportunity', 'kits_opportunity_sync_closes', 30 );
add_action(
	'updated_post_meta',
	function ( $mid, $id, $key ) {
		if ( in_array( $key, array( 'aw_deadline', 'aw_review_until' ), true ) ) {
			kits_opportunity_sync_closes( $id );
		}
	},
	10,
	3
);
add_action(
	'added_post_meta',
	function ( $mid, $id, $key ) {
		if ( in_array( $key, array( 'aw_deadline', 'aw_review_until' ), true ) ) {
			kits_opportunity_sync_closes( $id );
		}
	},
	10,
	3
);
// The one-time fill of every opportunity's closing date ran and was removed (audit item 16, flag confirmed on live 25 Oct 2026; see git history).

/** Open opportunities: the deadline is today or later; with no deadline, the review date is today or later. */
function kits_open_opportunities_meta_query() {
	$today = wp_date( 'Y-m-d' );
	return array(
		'relation' => 'OR',
		array( 'key' => 'aw_deadline', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
		array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => 'aw_deadline', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'aw_deadline', 'value' => '', 'compare' => '=' ),
			),
			array(
				'relation' => 'OR',
				array( 'key' => 'aw_review_until', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'aw_review_until', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
			),
		),
	);
}

