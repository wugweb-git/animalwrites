<?php
/**
 * Publish items held in Sourcing → To review only because their summary is short or the source thin (owner, 15 Oct 2026:
 * "just publish them for now"; the rewrite comes later, from short-2026-10-15/rewrite-later.json).
 *
 *   AW_IDS=1,2,3 [AW_THIN_OK=2,3] [AW_APPLY=1] wp eval-file tools/summary-dates/publish-held.php
 *
 * AW_THIN_OK: items whose only other check is "thin format" (a video page with a thin text) that a person read and
 * found to be real Indian news: published too (the thin source is the summary's problem, not the item's).
 *
 * An item is published only when it is pending, its hold is about summary length or a thin source, and auto-review
 * finds no other reason (publisher, category, thin format, relevance, Library source, same story); otherwise it is left
 * and the reason printed. wp_publish_post() changes the status only: post_date and post_modified stay; an empty
 * post_date_gmt is filled from post_date. The search description is refreshed.
 *
 * @package animal-writes-core
 */

$ids   = array_filter( array_map( 'intval', explode( ',', (string) getenv( 'AW_IDS' ) ) ) );
$apply = '1' === getenv( 'AW_APPLY' );
$thin  = array_filter( array_map( 'intval', explode( ',', (string) getenv( 'AW_THIN_OK' ) ) ) );
$out   = array(
	'published' => array(),
	'left'      => array(),
);
foreach ( $ids as $id ) {
	$p    = get_post( $id );
	$hold = (string) get_post_meta( $id, 'aw_review', true );
	if ( ! $p || 'pending' !== $p->post_status ) {
		$out['left'][ $id ] = 'not pending (' . ( $p ? $p->post_status : 'missing' ) . '): ' . $hold;
		continue;
	}
	if ( ! preg_match( '/summary below|source gives too little|thin source|summary has \d+ sentences/i', $hold ) ) {
		$out['left'][ $id ] = 'held for another reason: ' . $hold;
		continue;
	}
	$why = in_array( $p->post_type, array( 'aw_news', 'aw_legal' ), true ) && function_exists( 'aw_review_reason' ) ? aw_review_reason( $id ) : '';
	if ( '' !== $why && ! preg_match( '/^summary below|^summary has \d+ sentences/', $why ) && ! ( in_array( $id, $thin, true ) && str_starts_with( $why, 'thin format' ) ) ) {
		$out['left'][ $id ] = 'other check: ' . $why;
		continue;
	}
	$out['published'][] = $id;
	if ( ! $apply ) {
		continue;
	}
	$before = array( $p->post_date, $p->post_modified );
	wp_publish_post( $id );
	update_post_meta( $id, 'aw_review', 'published with a short summary (owner, ' . wp_date( 'Y-m-d H:i' ) . '); rewrite later' );
	update_post_meta( $id, 'aw_summary_short', current_time( 'Y-m-d' ) );
	clean_post_cache( $id );
	if ( function_exists( 'aw_seo_fill_post' ) ) {
		aw_seo_fill_post( $id );
	}
	$after = get_post( $id );
	if ( array( $after->post_date, $after->post_modified ) !== $before ) {
		WP_CLI::warning( "#$id dates moved: " . implode( ' / ', $before ) . ' -> ' . $after->post_date . ' / ' . $after->post_modified );
	}
	do_action( 'litespeed_purge_post', $id );
}
WP_CLI::log( wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
WP_CLI::success( ( $apply ? 'Applied' : 'Dry run' ) . ': ' . count( $out['published'] ) . ' published, ' . count( $out['left'] ) . ' left' );
