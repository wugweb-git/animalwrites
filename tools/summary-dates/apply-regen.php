<?php
/**
 * Publication dates in summaries, second pass (owner, 15 Oct 2026): a summary left shorter than its search description
 * after a date or an empty sentence came out is written again from its source under the summary rules ("regen"), or,
 * when the source gives too little or can't be read, waits in Sourcing → To review with the reason ("review"). Never padded.
 *
 *   AW_PLAN=~/plan.json [AW_APPLY=1] wp eval-file tools/summary-dates/apply-regen.php
 *
 * Plan rows: {id, action: regen|review, before, after?, reason?}. A row whose summary changed since the check is left.
 * regen: the previous summary goes to aw_summary_history, the new one is written straight to the row (post_excerpt
 * only), so post_date and post_modified never change; the automatic search description follows.
 * review: the summary stays as it is; the item goes to pending (straight to the row, no date moves) with aw_review
 * "held: …", so it shows in Sourcing → To review.
 *
 * @package animal-writes-core
 */

global $wpdb;
$plan  = json_decode( (string) file_get_contents( (string) getenv( 'AW_PLAN' ) ), true );
$apply = '1' === getenv( 'AW_APPLY' );
if ( ! is_array( $plan ) ) {
	WP_CLI::error( 'No plan (AW_PLAN).' );
}
$now   = current_time( 'mysql' ); // IST, the site's time zone
$count = array(
	'regen'   => 0,
	'review'  => 0,
	'changed' => 0,
	'missing' => 0,
	'seo'     => 0,
);
foreach ( $plan as $row ) {
	$action = (string) ( $row['action'] ?? '' );
	if ( ! in_array( $action, array( 'regen', 'review' ), true ) ) {
		continue;
	}
	$id = (int) $row['id'];
	$p  = get_post( $id );
	if ( ! $p || 'publish' !== $p->post_status ) {
		++$count['missing'];
		continue;
	}
	if ( $p->post_excerpt !== $row['before'] ) {
		++$count['changed'];
		WP_CLI::log( "#$id changed since the check; left" );
		continue;
	}
	++$count[ $action ];
	if ( ! $apply ) {
		continue;
	}
	if ( 'regen' === $action ) {
		$hist   = array_values( array_filter( (array) get_post_meta( $id, 'aw_summary_history', true ) ) );
		$hist[] = array(
			'summary' => $p->post_excerpt,
			'at'      => $now,
			'reason'  => 'written again from the source: the summary was shorter than its search description after a date came out',
		);
		update_post_meta( $id, 'aw_summary_history', $hist );
		$wpdb->update( $wpdb->posts, array( 'post_excerpt' => (string) $row['after'] ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	} else {
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		update_post_meta( $id, 'aw_review', 'held: ' . sanitize_text_field( (string) ( $row['reason'] ?? 'summary below the section minimum' ) ) );
	}
	clean_post_cache( $id );
	if ( 'regen' === $action && function_exists( 'aw_seo_fill_post' ) && ! empty( aw_seo_fill_post( $id )['description'] ) ) {
		++$count['seo'];
	}
	do_action( 'litespeed_purge_post', $id );
}
WP_CLI::success( ( $apply ? 'Applied' : 'Dry run' ) . ': ' . wp_json_encode( $count ) );
