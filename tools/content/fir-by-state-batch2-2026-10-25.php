<?php
/**
 * FIR by state, batch 2 (owner's yes to FINAL-batch2-4-for-approval.md, 25 Oct 2026): state pages for Maharashtra,
 * Telangana, Goa and Chandigarh; in post 127's "Reporting cruelty in your state" section the 4 join "States with their
 * own guide" and their rows leave the "Other states" table (which also drops the old Chandigarh "Sector 22-C").
 * Chhattisgarh and Uttarakhand stay as rows (owner). Goa is published without a link to the Goa Police site (its
 * Register Complaint page carried injected links on 25 Oct; owner's rule). Text from Claude outputs/fir-by-state/six-new/.
 * Re-running updates the same posts and changes 127 only where something is still missing.
 *
 * Usage: wp eval-file fir-by-state-batch2-2026-10-25.php [dry]
 */
$dry  = ! empty( $args[0] ) && 'dry' === $args[0];
$data = json_decode( (string) file_get_contents( __DIR__ . '/fir-by-state-batch2-2026-10-25.json' ), true );
$cat  = get_term_by( 'slug', 'cruelty-prosecution', 'category' );
$keep = get_post( 127 );
if ( ! is_array( $data ) || 4 !== count( $data['pages'] ?? array() ) || ! $cat || ! $keep || 'how-to-report-animal-cruelty' !== $keep->post_name || ! function_exists( 'aw_faq_items_save' ) ) {
	WP_CLI::error( 'Data file, category, post 127 or the FAQ functions not found; nothing written.' );
}

// 1. The 4 state pages.
foreach ( $data['pages'] as $d ) {
	$t  = mb_strlen( $d['rm'] );
	$dl = mb_strlen( $d['desc'] );
	if ( $t > 60 || $dl > 155 ) {
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
	update_post_meta( $id, '_aw_owner_draft', 'FIR by state page, batch 2; owner yes 25 Oct 2026; official sources checked 25 Oct 2026' );
	clean_post_cache( $id );
	WP_CLI::success( "$id | " . get_permalink( $id ) . " | t=$t d=$dl" );
}

// 2. Post 127: the 4 move from the table to "States with their own guide".
$c     = (string) $keep->post_content;
$names = array( 'Assam', 'Bihar', 'Chandigarh', 'Goa', 'Jharkhand', 'Kerala', 'Ladakh', 'Maharashtra', 'Mizoram', 'Nagaland', 'Puducherry', 'Tamil Nadu', 'Telangana' );
$links = array();
foreach ( $names as $n ) {
	$links[] = '<a href="https://animalwrites.in/resources/animal-cruelty-complaint-' . sanitize_title( $n ) . '/">' . $n . '</a>';
}
$para = '<p><strong>States with their own guide:</strong> ' . implode( ', ', $links ) . '.</p>';
$new  = preg_replace( '#<p><strong>States with their own guide:</strong>.*?</p>#s', $para, $c, -1, $np );
$new  = preg_replace( '#<tr><td>(Maharashtra|Telangana|Goa|Chandigarh)</td>.*?</tr>\n?#s', '', $new, -1, $nr );
if ( 1 !== $np ) {
	WP_CLI::error( "127: \"States with their own guide\" paragraph found $np times; 127 not changed." );
}
if ( $dry ) {
	WP_CLI::log( "would update 127 | list -> 13 states | rows removed $nr (expect 4, or 0 on a re-run) | Sector 22-C left: " . substr_count( $new, '22-C' ) );
	return;
}
if ( $new !== $c ) {
	update_post_meta( 127, '_aw_content_backup_2026_10_25_b2', wp_slash( $c ) );
	$r = wp_update_post( wp_slash( array( 'ID' => 127, 'post_content' => $new ) ), true );
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( '127: ' . $r->get_error_message() );
	}
	clean_post_cache( 127 );
}
WP_CLI::success( "127 updated | rows removed $nr | Sector 22-C left: " . substr_count( $new, '22-C' ) );
