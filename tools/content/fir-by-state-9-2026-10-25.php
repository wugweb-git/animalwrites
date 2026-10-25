<?php
/**
 * FIR guide merge and state pages, batch 1 (owner's yes to the final text v2, 25 Oct 2026), in the owner's order:
 * a Library judgment (Madras High Court, Balaji v. State, Crl.O.P. No. 9632 of 2026, 1 Sep 2026; court copy as uploaded to
 * its judgment portal on 11 Sep 2026); post 9027 "How to file a police complaint (FIR) for animal cruelty" folded into
 * post 127 "How to report animal cruelty" (kept: more Search Console impressions with its old /knowledge/ address), with
 * the "Reporting cruelty in your state" section; 9027 redirected (301) to 127 and set to draft; 9 state pages (Assam,
 * Bihar, Jharkhand, Kerala, Ladakh, Mizoram, Nagaland, Puducherry, Tamil Nadu); links to 9027 repointed to 127.
 * Text from Claude outputs/fir-by-state/final/ (both sessions' research merged: MERGED-state-review-2026-10-25.md).
 * Replaces the 15-page script, which was never run. Re-running updates the same posts and adds nothing twice.
 *
 * Usage: wp eval-file fir-by-state-9-2026-10-25.php [dry]
 */
$dry  = ! empty( $args[0] ) && 'dry' === $args[0];
$data = json_decode( (string) file_get_contents( __DIR__ . '/fir-by-state-9-2026-10-25.json' ), true );
$cat  = get_term_by( 'slug', 'cruelty-prosecution', 'category' );
$cj   = get_term_by( 'slug', 'courts-judgments', 'category' );
$old  = get_post( 9027 );
$keep = get_post( 127 );
if ( ! is_array( $data ) || 9 !== count( $data['pages'] ?? array() ) || empty( $data['merged'] ) || ! $cat || ! $cj || ! $old || ! $keep || 'how-to-report-animal-cruelty' !== $keep->post_name || ! class_exists( '\\RankMath\\Redirections\\Redirection' ) ) {
	WP_CLI::error( 'Data file, categories, posts 127/9027 or Rank Math redirections not found; nothing written.' );
}
$old_path = 'resources/how-to-file-a-police-complaint-fir-for-animal-cruelty';
$new_url  = home_url( '/resources/how-to-report-animal-cruelty/' );

// 1. Library: the Madras High Court judgment, court copy hosted.
$lib_slug = 'madras-high-court-1-september-2026-pca-section-11-1-a-non-cognizable';
$lib      = get_posts( array( 'name' => $lib_slug, 'post_type' => 'aw_legal', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
$lib_id   = $lib ? (int) $lib[0] : 0;
$summary  = 'On 1 Sep 2026 the Madras High Court (Justice G.K. Ilanthiraiyan) quashed a case under section 11(1)(a) of the Prevention of Cruelty to Animals Act against a man accused of throwing a stone at a neighbour\'s dog that had bitten him. The court held that the offence is non-cognizable under section 31 of the Act, and that for a first offence the punishment is a fine of Rs 10 to Rs 50. The police should therefore have taken the magistrate\'s permission under section 174(2) of the Bharatiya Nagarik Suraksha Sanhita before registering the FIR. The court also noted that no evidence showed the dog was injured and that the complaint was a counterblast to the petitioner\'s own complaint.';
if ( $dry ) {
	WP_CLI::log( ( $lib_id ? "would update Library $lib_id" : 'would create Library item' ) . " $lib_slug" );
} else {
	$lp = array(
		'post_type'    => 'aw_legal',
		'post_name'    => $lib_slug,
		'post_title'   => 'Madras High Court, 1 Sep 2026: PCA section 11(1)(a) is non-cognizable (Balaji v. State, Crl.O.P. No. 9632 of 2026)',
		'post_excerpt' => $summary,
		'post_content' => "<!-- wp:paragraph {\"className\":\"aw-doc-status\"} -->\n<p class=\"aw-doc-status\">Order of Justice G.K. Ilanthiraiyan, Madras High Court, 1 September 2026. Summary, then the court's own copy (as uploaded to the court's judgment portal on 11 September 2026).</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $summary ) . "</p>\n<!-- /wp:paragraph -->\n",
		'post_status'  => 'publish',
		'post_date'    => '2026-09-01 12:00:00',
	);
	if ( $lib_id ) {
		$lp['ID'] = $lib_id;
		unset( $lp['post_date'] );
		$r = wp_update_post( wp_slash( $lp ), true );
	} else {
		$r = wp_insert_post( wp_slash( $lp ), true );
	}
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( 'Library: ' . $r->get_error_message() );
	}
	$lib_id = (int) $r;
	foreach ( array( 'aw_source_date' => '2026-09-01', 'aw_doc_sort' => '2026-09-01', 'aw_issuer' => 'high-court', 'aw_source_name' => 'Madras High Court', 'aw_source_url' => 'https://www.mhc.tn.gov.in/judis', 'aw_legal_number' => 'Crl.O.P. No. 9632 of 2026', 'aw_short_name' => 'Madras High Court order in Balaji v. State', 'aw_state_code' => '33', 'rank_math_title' => 'Madras High Court 2026 PCA 11(1)(a) Ruling – Animal Writes', 'rank_math_description' => 'On 1 Sep 2026 the Madras High Court held that cruelty under PCA section 11(1)(a) is non-cognizable: police need a magistrate\'s permission before an FIR.', 'rank_math_focus_keyword' => 'pca section 11(1)(a) non cognizable,madras high court balaji v state' ) as $k => $v ) {
		update_post_meta( $lib_id, $k, wp_slash( $v ) );
	}
	wp_set_object_terms( $lib_id, array( (int) $cj->term_id, (int) $cat->term_id ), 'category' );
	wp_set_object_terms( $lib_id, array( 'judgment' ), 'post_tag' );
	update_post_meta( $lib_id, 'rank_math_primary_category', (int) $cj->term_id );
	if ( ! (int) get_post_meta( $lib_id, 'aw_file_id', true ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( 'https://images.assettype.com/barandbench-hindi/2026-09-14/8ej9dcdm/Balaji_Vs_State.pdf', 120 );
		if ( is_wp_error( $tmp ) ) {
			WP_CLI::warning( 'PDF not downloaded: ' . $tmp->get_error_message() . ' (Library item kept without the file)' );
		} else {
			$att = media_handle_sideload( array( 'name' => 'madras-hc-balaji-v-state-crl-op-9632-2026.pdf', 'tmp_name' => $tmp ), $lib_id, 'Madras High Court, Balaji v. State, Crl.O.P. No. 9632 of 2026 (1 Sep 2026)' );
			if ( is_wp_error( $att ) ) {
				@unlink( $tmp );
				WP_CLI::warning( 'PDF not attached: ' . $att->get_error_message() );
			} else {
				update_post_meta( $lib_id, 'aw_file_id', (int) $att );
			}
		}
	}
	clean_post_cache( $lib_id );
	WP_CLI::success( "Library $lib_id | " . get_permalink( $lib_id ) . ' | file ' . (int) get_post_meta( $lib_id, 'aw_file_id', true ) );
}

// 2. Merge 9027 into 127: the merged text, title, SEO fields and FAQ on 127; 9027 redirected (301) and set to draft.
$m  = $data['merged'];
$mt = mb_strlen( $m['rm'] );
if ( $mt > 60 || mb_strlen( $m['desc'] ) > 155 ) {
	WP_CLI::error( "Merged guide title or description too long: t=$mt" );
}
if ( $dry ) {
	WP_CLI::log( 'would merge 9027 into 127 | ' . strlen( $m['content'] ) . ' bytes | ' . count( $m['faq'] ) . " FAQ | t=$mt | 301 /$old_path/ -> $new_url" );
} else {
	update_post_meta( 127, '_aw_content_backup_2026_10_25', wp_slash( $keep->post_content ) );
	update_post_meta( 127, '_aw_faq_backup_2026_10_25', wp_slash( (string) get_post_meta( 127, 'aw_faq', true ) ) );
	$r = wp_update_post( wp_slash( array( 'ID' => 127, 'post_title' => $m['title'], 'post_excerpt' => $m['desc'], 'post_content' => $m['content'] ) ), true );
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( 'Merge: ' . $r->get_error_message() );
	}
	update_post_meta( 127, 'rank_math_title', wp_slash( $m['rm'] ) );
	update_post_meta( 127, 'rank_math_description', wp_slash( $m['desc'] ) );
	update_post_meta( 127, 'rank_math_focus_keyword', wp_slash( $m['focus'] ) );
	$rows = array();
	foreach ( $m['faq'] as $x ) {
		$rows[] = array( 'q' => $x[0], 'a' => $x[1], 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => '2026-10-25' );
	}
	aw_faq_items_save( 'post', 127, $rows );
	clean_post_cache( 127 );
	global $wpdb;
	$have = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}rank_math_redirections WHERE sources LIKE %s", '%' . $wpdb->esc_like( $old_path ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $have ) {
		$rd = \RankMath\Redirections\Redirection::from(
			array(
				'sources'     => array( array( 'pattern' => $old_path, 'comparison' => 'exact' ) ),
				'url_to'      => $new_url,
				'header_code' => 301,
			)
		);
		$rd->save();
	}
	wp_update_post( array( 'ID' => 9027, 'post_status' => 'draft' ) );
	clean_post_cache( 9027 );
	WP_CLI::success( "Merged into 127 | " . get_permalink( 127 ) . " | 9027 now draft, 301 /$old_path/ -> $new_url" );
}

// 3. The 9 state pages.
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
	update_post_meta( $id, '_aw_owner_draft', 'FIR by state page; owner review and yes 25 Oct 2026; official sources checked 25 Oct 2026' );
	clean_post_cache( $id );
	WP_CLI::success( "$id | " . get_permalink( $id ) . " | t=$t d=$dl" );
}

// 4. Repoint links to 9027 (post content and FAQ items) to 127.
if ( $dry ) {
	WP_CLI::log( 'would repoint links to /' . $old_path . '/ in published posts and FAQ items' );
	return;
}
global $wpdb;
$n   = 0;
$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s", '%' . $wpdb->esc_like( $old_path ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $ids as $pid ) {
	$c = (string) get_post_field( 'post_content', $pid );
	$c = str_replace( array( home_url( '/' . $old_path . '/' ), '/' . $old_path . '/' ), array( $new_url, '/resources/how-to-report-animal-cruelty/' ), $c );
	wp_update_post( wp_slash( array( 'ID' => (int) $pid, 'post_content' => $c ) ) );
	clean_post_cache( (int) $pid );
	++$n;
}
foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'aw_faq' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $old_path ) . '%' ) ) as $pid ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$v = (string) get_post_meta( (int) $pid, 'aw_faq', true );
	update_post_meta( (int) $pid, 'aw_faq', wp_slash( str_replace( array( home_url( '/' . $old_path . '/' ), '\\/' . str_replace( '/', '\\/', $old_path ) . '\\/' ), array( $new_url, '\\/resources\\/how-to-report-animal-cruelty\\/' ), $v ) ) );
	++$n;
}
WP_CLI::success( "Links to 9027 repointed in $n posts or FAQ sets." );
