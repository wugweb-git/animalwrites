<?php
/**
 * Retention: nothing is ever deleted. Old items are archived instead, and stay reachable at their URL.
 *
 * - News, 6+ months old: noindex (still listed).
 * - News, 12+ months old: archived — noindex, left out of the news list and the sitemap.
 * - Opportunities past their deadline: closed — noindex, left out of the opportunities list and the sitemap.
 * - Legal Watch, research, guides, resources, campaigns, directory: permanent, unchanged.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_NEWS_NOINDEX_AFTER = '6 months';
const AW_NEWS_ARCHIVE_AFTER = '12 months';

/**
 * Retention state of a post: '' (normal), 'noindex', 'archived' or 'closed'.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function aw_retention_state( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( 'aw_news' === $post->post_type ) {
		$t = (int) get_post_time( 'U', true, $post );
		if ( $t < strtotime( '-' . AW_NEWS_ARCHIVE_AFTER ) ) {
			return 'archived';
		}
		return $t < strtotime( '-' . AW_NEWS_NOINDEX_AFTER ) ? 'noindex' : '';
	}
	if ( 'aw_opportunity' === $post->post_type ) {
		$dl = aw_opportunity_closes( $post->ID );
		return ( $dl && $dl < wp_date( 'Y-m-d' ) ) ? 'closed' : '';
	}
	return '';
}

// Archived, closed and older news pages ask search engines not to index them; links are still followed.
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_singular( array( 'aw_news', 'aw_opportunity' ) ) && aw_retention_state( get_queried_object() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

// Lists leave out archived news and closed opportunities.
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( 'aw_news' ) ) {
			$q->set( 'date_query', array( array( 'after' => AW_NEWS_ARCHIVE_AFTER . ' ago', 'inclusive' => true ) ) );
		}
		if ( $q->is_post_type_archive( 'aw_opportunity' ) ) {
			$mq           = (array) $q->get( 'meta_query' ); // keep the list filters (Location is a meta filter)
			$mq['aw_open'] = aw_open_opportunities_meta_query();
			$q->set( 'meta_query', $mq );
		}
	},
	40
);

/** Days an opportunity without a deadline stays open before it needs renewing. */
const AW_OPP_REVIEW_DAYS = 30;

/** The date an opportunity closes: its deadline, else its review date (Y-m-d), or '' if neither is set. */
function aw_opportunity_closes( $id ) {
	$dl = (string) get_post_meta( $id, 'aw_deadline', true );
	return $dl ? $dl : (string) get_post_meta( $id, 'aw_review_until', true );
}

/** Give an opportunity with no deadline a review date 30 days after it was posted (renew by moving the date). */
function aw_opportunity_set_review( $id ) {
	if ( 'aw_opportunity' !== get_post_type( $id ) || get_post_meta( $id, 'aw_deadline', true ) || get_post_meta( $id, 'aw_review_until', true ) ) {
		return;
	}
	update_post_meta( $id, 'aw_review_until', wp_date( 'Y-m-d', (int) get_post_time( 'U', true, $id ) + AW_OPP_REVIEW_DAYS * DAY_IN_SECONDS ) );
}
add_action( 'save_post_aw_opportunity', 'aw_opportunity_set_review', 20 );

/** Open opportunities: the deadline is today or later; with no deadline, the review date is today or later. */
function aw_open_opportunities_meta_query() {
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

// The sitemap lists only indexable items.
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( 'aw_news' === $post_type ) {
			$args['date_query'] = array( array( 'after' => AW_NEWS_NOINDEX_AFTER . ' ago', 'inclusive' => true ) );
		}
		if ( 'aw_opportunity' === $post_type ) {
			$args['meta_query'] = aw_open_opportunities_meta_query();
		}
		return $args;
	},
	10,
	2
);
