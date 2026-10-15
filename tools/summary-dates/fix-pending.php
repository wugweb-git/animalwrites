<?php
// Minimal date removal for an item held in To review (status stays pending); same history/no-date-move as apply.php.
global $wpdb;
$plan  = json_decode( (string) file_get_contents( (string) getenv( 'AW_PLAN' ) ), true );
$apply = '1' === getenv( 'AW_APPLY' );
foreach ( $plan as $row ) {
	$id = (int) $row['id']; $p = get_post( $id );
	if ( ! $p || 'pending' !== $p->post_status || $p->post_excerpt !== $row['before'] ) { WP_CLI::log( "#$id left (status or summary changed)" ); continue; }
	if ( ! $apply ) { WP_CLI::log( "#$id would be fixed" ); continue; }
	$hist   = array_values( array_filter( (array) get_post_meta( $id, 'aw_summary_history', true ) ) );
	$hist[] = array( 'summary' => $p->post_excerpt, 'at' => current_time( 'mysql' ), 'reason' => 'publication date given as the event date: ' . implode( '; ', (array) $row['removed'] ) );
	update_post_meta( $id, 'aw_summary_history', $hist );
	$wpdb->update( $wpdb->posts, array( 'post_excerpt' => (string) $row['after'] ), array( 'ID' => $id ) );
	clean_post_cache( $id );
	if ( function_exists( 'aw_seo_fill_post' ) ) { aw_seo_fill_post( $id ); }
	WP_CLI::log( "#$id fixed; status " . get_post_status( $id ) );
}
