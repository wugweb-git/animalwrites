<?php
/**
 * Publication dates in summaries (owner, 15 Oct 2026): export published News, Library and Resources research items for
 * the check in animal-writes-workers (python -m aw_workers.summary_dates ITEMS.json PLAN.json).
 *
 *   wp eval-file tools/summary-dates/export.php > items.json
 *
 * @package animal-writes-core
 */

$out = array();
$q   = new WP_Query(
	array(
		'post_type'      => array( 'aw_news', 'aw_legal', 'post' ),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
foreach ( $q->posts as $id ) {
	$p = get_post( $id );
	if ( 'post' === $p->post_type && ! has_term( 'research', 'category', $id ) ) {
		continue; // Resources: research summaries only
	}
	$out[] = array(
		'id'          => $id,
		'type'        => $p->post_type,
		'post_date'   => $p->post_date,
		'excerpt'     => $p->post_excerpt,
		'content'     => $p->post_content,
		'source_date' => (string) get_post_meta( $id, 'aw_source_date', true ),
		'source_url'  => (string) get_post_meta( $id, 'aw_source_url', true ),
		'title'       => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
	);
}
echo wp_json_encode( $out );
