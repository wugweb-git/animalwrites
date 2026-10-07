<?php
/**
 * Create content DRAFTS from JSON (type, slug, title, excerpt, topics[], content). Never publishes, never overwrites
 * a published post; an existing draft with the same slug is updated.
 * Usage: wp eval-file create_drafts.php drafts.json
 */
$rows = json_decode( file_get_contents( $args[0] ), true );
foreach ( $rows as $r ) {
	$have = get_posts( array( 'post_type' => $r['type'], 'name' => $r['slug'], 'post_status' => 'any', 'numberposts' => 1 ) );
	if ( $have && 'draft' !== $have[0]->post_status ) {
		echo "skipped (already {$have[0]->post_status}): {$r['slug']}\n";
		continue;
	}
	$post = array(
		'post_type'    => $r['type'],
		'post_name'    => $r['slug'],
		'post_title'   => $r['title'],
		'post_excerpt' => $r['excerpt'],
		'post_content' => $r['content'],
		'post_status'  => 'draft',
	);
	if ( $have ) {
		$post['ID'] = $have[0]->ID;
	}
	$id = $have ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
	if ( is_wp_error( $id ) ) {
		echo 'error: ' . $r['slug'] . ' ' . $id->get_error_message() . "\n";
		continue;
	}
	wp_set_object_terms( $id, $r['topics'], 'aw_topic' );
	echo ( $have ? 'updated draft ' : 'created draft ' ) . $id . ': ' . $r['title'] . "\n";
}
