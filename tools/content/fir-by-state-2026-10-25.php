<?php
/**
 * FIR by state (owner brief 25 Oct 2026; drafts approved by the owner 25 Oct 2026): 15 state pages and a "Reporting
 * cruelty in your state" section in the national guide (post 9027), inserted before its "Get support" heading once.
 * Page text, SEO fields and FAQs come from fir-by-state-2026-10-25.json, built from the approved drafts in
 * Claude outputs/fir-by-state/ (research files there: official sources only). FAQs go into each page's own FAQ
 * (meta aw_faq, approved). Re-running updates the same posts and does not add the section twice.
 *
 * Usage: wp eval-file fir-by-state-2026-10-25.php [dry]
 */
$dry  = ! empty( $args[0] ) && 'dry' === $args[0];
$data = json_decode( (string) file_get_contents( __DIR__ . '/fir-by-state-2026-10-25.json' ), true );
if ( ! is_array( $data ) || empty( $data['pages'] ) ) {
	WP_CLI::error( 'fir-by-state-2026-10-25.json missing or unreadable.' );
}
$cat = get_term_by( 'slug', 'cruelty-prosecution', 'category' );
$hub = get_post( 9027 );
if ( ! $cat || ! $hub || 'how-to-file-a-police-complaint-fir-for-animal-cruelty' !== $hub->post_name ) {
	WP_CLI::error( 'Category cruelty-prosecution or the national guide (9027) not found; nothing written.' );
}
foreach ( $data['pages'] as $d ) {
	$t  = mb_strlen( $d['rm'] );
	$dl = mb_strlen( $d['desc'] );
	if ( $t < 55 || $t > 58 || $dl > 155 || preg_match( '/[:,&]/', str_replace( ' – Animal Writes', '', $d['rm'] ) ) ) {
		WP_CLI::error( "Title or description check failed on {$d['slug']}: t=$t d=$dl" );
	}
	$ex = get_posts( array( 'name' => $d['slug'], 'post_type' => 'post', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'numberposts' => 1, 'fields' => 'ids' ) );
	$id = $ex ? (int) $ex[0] : 0;
	if ( $dry ) {
		WP_CLI::log( ( $id ? "would update $id" : 'would create' ) . " {$d['slug']} | t=$t d=$dl | " . strlen( $d['content'] ) . ' bytes | ' . count( $d['faq'] ) . ' FAQ' );
		continue;
	}
	$post = array( 'post_type' => 'post', 'post_name' => $d['slug'], 'post_title' => $d['title'], 'post_excerpt' => $d['desc'], 'post_content' => $d['content'], 'post_status' => 'publish' );
	if ( $id ) {
		$post['ID'] = $id;
		$r          = wp_update_post( wp_slash( $post ), true );
	} else {
		$r = wp_insert_post( wp_slash( $post ), true );
	}
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( $d['slug'] . ': ' . $r->get_error_message() );
	}
	$id = (int) $r;
	wp_set_object_terms( $id, array( (int) $cat->term_id ), 'category' );
	wp_set_object_terms( $id, array( 'guide' ), 'post_tag' );
	update_post_meta( $id, 'rank_math_title', wp_slash( $d['rm'] ) );
	update_post_meta( $id, 'rank_math_description', wp_slash( $d['desc'] ) );
	update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( $d['focus'] ) );
	update_post_meta( $id, 'rank_math_primary_category', (int) $cat->term_id );
	$rows = array();
	foreach ( $d['faq'] as $x ) {
		$rows[] = array( 'q' => $x[0], 'a' => $x[1], 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => '2026-10-25' );
	}
	aw_faq_items_save( 'post', $id, $rows );
	update_post_meta( $id, '_aw_owner_draft', 'FIR by state page; owner approved 25 Oct 2026; official sources checked 25 Oct 2026' );
	clean_post_cache( $id );
	WP_CLI::success( "$id | " . get_permalink( $id ) . " | t=$t d=$dl" );
}
// The national guide: insert the state section before "Get support", once.
$content = (string) $hub->post_content;
if ( false !== strpos( $content, 'Reporting cruelty in your state' ) ) {
	WP_CLI::log( 'National guide already has the state section; left as it is.' );
	return;
}
$at = strpos( $content, 'Get support' );
$at = false === $at ? false : strrpos( substr( $content, 0, $at ), '<!-- wp:heading' );
if ( false === $at ) {
	WP_CLI::error( 'No "Get support" heading in the national guide; section not inserted.' );
}
if ( $dry ) {
	WP_CLI::log( 'would insert the state section (' . strlen( $data['hub'] ) . ' bytes) into 9027 before "Get support"' );
	return;
}
$r = wp_update_post( wp_slash( array( 'ID' => 9027, 'post_content' => substr( $content, 0, $at ) . $data['hub'] . substr( $content, $at ) ) ), true );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
clean_post_cache( 9027 );
WP_CLI::success( 'State section added to ' . get_permalink( 9027 ) );
