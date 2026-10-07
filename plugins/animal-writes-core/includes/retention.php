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
		$dl = (string) get_post_meta( $post->ID, 'aw_deadline', true );
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
			$q->set( 'meta_query', aw_open_opportunities_meta_query() );
		}
	},
	40
);

/** Open opportunities: no deadline set, or a deadline today or later. */
function aw_open_opportunities_meta_query() {
	return array(
		'relation' => 'OR',
		array( 'key' => 'aw_deadline', 'compare' => 'NOT EXISTS' ),
		array( 'key' => 'aw_deadline', 'value' => '', 'compare' => '=' ),
		array( 'key' => 'aw_deadline', 'value' => wp_date( 'Y-m-d' ), 'compare' => '>=', 'type' => 'DATE' ),
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
