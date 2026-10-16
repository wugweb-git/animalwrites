<?php
/**
 * Campaign 2.0 (16 Oct 2026): N1-N4 were written by Animal Writes, so they are Resources, not News (News is sourced by
 * the workers and uses the news layout). Moves them to posts with the guide tag, adds 301s from their /news/ URLs and
 * points the links in all ten Campaign 2.0 pieces at /resources/. Safe to re-run.
 * Run: wp eval-file campaign2-news-to-resources.php
 */
$moved = array( 13854, 13855, 13856, 13867 );
$all   = array( 13854, 13855, 13856, 13857, 13858, 13859, 13867, 13868, 13869, 13870 );
$from  = array();
$to    = array();
foreach ( $moved as $id ) {
	$p = get_post( $id );
	if ( ! $p ) {
		continue;
	}
	$from[] = '/news/' . $p->post_name . '/';
	$to[]   = '/resources/' . $p->post_name . '/';
	if ( 'aw_news' === $p->post_type ) {
		set_post_type( $id, 'post' );
		foreach ( array( 'aw_source_name', 'aw_source_url', 'aw_source_date' ) as $k ) {
			delete_post_meta( $id, $k );
		}
		wp_set_object_terms( $id, 'guide', 'post_tag', true );
		clean_post_cache( $id );
		aw_redirect_add( '/news/' . $p->post_name . '/', $id );
	}
	WP_CLI::log( sprintf( '%d | %s | %s', $id, get_post_type( $id ), get_permalink( $id ) ) );
}
foreach ( $all as $id ) {
	$c = get_post_field( 'post_content', $id );
	$n = str_replace( $from, $to, $c );
	if ( $n !== $c ) {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $n ) ) );
		WP_CLI::log( "$id links updated" );
	}
}
