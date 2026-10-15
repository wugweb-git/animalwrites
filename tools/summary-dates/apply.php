<?php
/**
 * Publication dates in summaries (owner, 15 Oct 2026): apply the plan made by aw_workers.summary_dates.
 *
 *   AW_PLAN=~/plan.json [AW_APPLY=1] wp eval-file tools/summary-dates/apply.php
 *
 * Without AW_APPLY=1 nothing is written (dry run). For each "fix" whose summary is still exactly the one checked, the
 * previous summary goes to aw_summary_history, then the new one is written straight to the row (post_excerpt only), so
 * post_date and post_modified never change. The automatic search description follows the summary (aw_seo_fill_post
 * only replaces a description that is still the automatic one; a typed one stays).
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
	'fixed'   => 0,
	'changed' => 0,
	'missing' => 0,
	'seo'     => 0,
);
foreach ( $plan as $row ) {
	if ( 'fix' !== ( $row['action'] ?? '' ) ) {
		continue;
	}
	$id = (int) $row['id'];
	$p  = get_post( $id );
	if ( ! $p || 'publish' !== $p->post_status ) {
		++$count['missing'];
		continue;
	}
	if ( $p->post_excerpt !== $row['before'] ) {
		++$count['changed']; // changed since the check: left alone
		WP_CLI::log( "#$id changed since the check; left" );
		continue;
	}
	if ( ! $apply ) {
		++$count['fixed'];
		continue;
	}
	$hist   = array_values( array_filter( (array) get_post_meta( $id, 'aw_summary_history', true ) ) );
	$hist[] = array(
		'summary' => $p->post_excerpt,
		'at'      => $now,
		'reason'  => 'publication date given as the event date: ' . implode( '; ', (array) ( $row['removed'] ?? array() ) ),
	);
	update_post_meta( $id, 'aw_summary_history', $hist );
	// Straight to the row: wp_update_post would move post_modified (the date never changes).
	$wpdb->update( $wpdb->posts, array( 'post_excerpt' => (string) $row['after'] ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $id );
	if ( function_exists( 'aw_seo_fill_post' ) && ! empty( aw_seo_fill_post( $id )['description'] ) ) {
		++$count['seo'];
	}
	do_action( 'litespeed_purge_post', $id );
	++$count['fixed'];
}
WP_CLI::success( ( $apply ? 'Applied' : 'Dry run' ) . ': ' . wp_json_encode( $count ) );
