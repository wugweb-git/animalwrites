<?php
/**
 * FIR by state, batch 1 (owner review and yes, 25 Oct 2026): 9 state pages (Assam, Bihar, Jharkhand, Kerala, Ladakh,
 * Mizoram, Nagaland, Puducherry, Tamil Nadu), the "Reporting cruelty in your state" section in the national FIR guide
 * (post 9027, inserted once before "Get support"), and a Library judgment: Madras High Court, Balaji v. State,
 * Crl.O.P. No. 9632 of 2026, 1 Sep 2026 (court copy as uploaded to its judgment portal on 11 Sep 2026).
 * Text from Claude outputs/fir-by-state/final/ (both sessions' research merged: MERGED-state-review-2026-10-25.md).
 * Replaces the 15-page script, which was never run. Re-running updates the same posts and adds nothing twice.
 *
 * Usage: wp eval-file fir-by-state-9-2026-10-25.php [dry]
 */
$dry  = ! empty( $args[0] ) && 'dry' === $args[0];
$data = json_decode( (string) file_get_contents( __DIR__ . '/fir-by-state-9-2026-10-25.json' ), true );
$cat  = get_term_by( 'slug', 'cruelty-prosecution', 'category' );
$cj   = get_term_by( 'slug', 'courts-judgments', 'category' );
$hub  = get_post( 9027 );
if ( ! is_array( $data ) || 9 !== count( $data['pages'] ?? array() ) || ! $cat || ! $cj || ! $hub || 'how-to-file-a-police-complaint-fir-for-animal-cruelty' !== $hub->post_name ) {
	WP_CLI::error( 'Data file, categories or the national guide (9027) not found; nothing written.' );
}

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

// 2. The 9 state pages.
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

// 3. The national guide: the state section before "Get support", once.
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
