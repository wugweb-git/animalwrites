<?php
/**
 * Library records for "India Writes for Its Community Dogs" (17 Oct 2026, owner's brief): the three Supreme Court
 * documents of In Re: "City Hounded by Strays, Kids Pay Price", Suo Motu Writ Petition (Civil) No. 5 of 2025, that
 * were not yet in the Library. Each PDF is the court's own (api.sci.gov.in), stored here like every other judgment
 * (aw_mirror_gov_pdf). Summaries follow the Library summary rules, written from the PDF; the operative paragraphs
 * are quoted word for word. Re-running updates the same items (by slug). The two LiveLaw News items on the same
 * orders link to their record (aw_rel_legal).
 *
 * Run: wp eval-file aw1-library.php
 */

$tag  = get_term_by( 'slug', 'campaign-india-writes-community-dogs', 'post_tag' );
$note = fn( $t ) => "<!-- wp:paragraph {\"className\":\"aw-doc-status\"} -->\n<p class=\"aw-doc-status\">$t</p>\n<!-- /wp:paragraph -->\n\n";
$p    = fn( $t ) => "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n";
$h    = fn( $t ) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n";

$docs = array(
	array(
		'slug'    => 'supreme-court-order-of-11-august-2025-on-stray-dogs-2025-insc-977',
		'title'   => 'Supreme Court order of 11 August 2025 on stray dogs (2025 INSC 977)',
		'kind'    => 'order',
		'date'    => '2025-08-11',
		'number'  => '2025 INSC 977',
		'src'     => 'https://api.sci.gov.in/supremecourt/2025/41706/41706_2025_8_31_63158_Judgement_11-Aug-2025.pdf',
		'focus'   => 'supreme court stray dogs order 11 august 2025',
		'news'    => 0,
		'excerpt' => 'The Supreme Court of India, in an order of 11 August 2025, directed authorities in Delhi and the National Capital Region to pick up stray dogs from all localities and keep them in shelters. The bench told the Delhi government, its municipal bodies and the authorities of Noida, Ghaziabad, Gurugram and Faridabad to create shelters within eight weeks, starting with space for about 5,000 dogs. Dogs were to be sterilised, dewormed and vaccinated under the Animal Birth Control Rules, 2023, and not released back on the streets. The order, in Suo Motu Writ Petition (Civil) No. 5 of 2025, also asked for a dog-bite helpline within a week. A suo motu case is one the court starts on its own.',
		'quote'   => array(
			'(III) The Government of National Capital Territory of Delhi (NCT), Municipal Corporation of Delhi (MCD), New Delhi Municipal Council (NDMC) and the appropriate authorities of NOIDA, Ghaziabad, Gurugram &amp; Faridabad are directed to immediately create dog shelters/pounds and report to this Court the creation of such infrastructure all over the National Capital Region (NCR), within a period of eight weeks from today.',
			'(VII) Since this is a progressive exercise i.e. the dog shelters would have to be increased over a period of time, authorities concerned shall begin by creating dog shelter(s) for say 5,000 dogs in the next six-eight weeks.',
			'(X) We direct the Authorities, referred to above, to create helpline number within a period of one week from today so that all cases of dog bites are immediately reported. Action must be taken to round up/pick up/catch the dog(s) within four hours from the complaint being received. Any resistance offered by any individual or organizations in this regard will be viewed strictly and we may proceed for contempt against such individual or organizations coming in the way of this Court.',
			'(XI) The stray dogs shall be captured, sterilized, dewormed and immunized as required by Animal Birth Control Rules, 2023 and as noted above, shall not be released under any circumstances.',
		),
		'quote_h' => 'Directions (III), (VII), (X) and (XI)',
		'by'      => 'Order of Justices J.B. Pardiwala and R. Mahadevan, 11 August 2025, in In Re: "City Hounded by Strays, Kids Pay Price", Suo Motu Writ Petition (C) No. 5 of 2025. Summary, then four of its directions word for word from the court\'s PDF.',
	),
	array(
		'slug'    => 'supreme-court-order-of-7-november-2025-on-stray-dogs-2025-insc-1357',
		'title'   => 'Supreme Court order of 7 November 2025 on stray dogs in schools, hospitals and stations (2025 INSC 1357)',
		'kind'    => 'order',
		'date'    => '2025-11-07',
		'number'  => '2025 INSC 1357',
		'src'     => 'https://api.sci.gov.in/supremecourt/2025/41706/41706_2025_3_301_65687_FinalOrder_07-Nov-2025.pdf',
		'focus'   => 'supreme court order 7 november 2025 stray dogs',
		'news'    => 12246,
		'excerpt' => 'The Supreme Court of India, in an order of 7 November 2025, directed that stray dogs be removed from schools, colleges, hospitals, sports complexes, bus stands and railway stations across India. A bench of Justices Vikram Nath, Sandeep Mehta and N.V. Anjaria told States and Union Territories to identify these premises within two weeks and secure them with fencing and gates, preferably within eight weeks. Municipal bodies must shift dogs found there to shelters after sterilisation and vaccination under the Animal Birth Control Rules, 2023, and not release them back to the same place. The order also asked the Animal Welfare Board of India to issue Standard Operating Procedures within four weeks, and dealt with stray cattle on highways.',
		'quote'   => array(
			'E. It shall be the responsibility of the jurisdictional municipal body/authority to forthwith remove every stray dog found within the premises of an educational institution, hospital (public or private), sports complex, bus stand/depot (including Inter-State Bus Terminal) or railway station and to shift such animal/s to a designated shelter, after due sterilisation and vaccination, in accordance with the Animal Birth Control Rules, 2023. The stray dogs so picked up shall not be released back to the same location from which they were picked up. We have consciously directed the non-release of such stray dogs to the same location from which they were picked up, as permitting the same would frustrate the very effect of the directions issued to liberate such institutional areas from the presence of stray dogs.',
			'J. Animal Welfare Board of India shall, within four weeks, issue detailed Standard Operating Procedures (SOPs) for prevention of dog bites and management of stray dogs in institutional premises (public or private) including but not limited to Government and private educational institutions, hospitals (including district hospitals, primary health centres, and medical colleges), and sports complexes or stadia, to be uniformly adopted across all States and Union Territories.',
		),
		'quote_h' => 'Directions E and J of paragraph 25',
		'by'      => 'Order of Justices Vikram Nath, Sandeep Mehta and N.V. Anjaria, 7 November 2025, in In Re: "City Hounded by Strays, Kids Pay Price", Suo Motu Writ Petition (Civil) No. 5 of 2025. Summary, then two of its directions word for word from the court\'s PDF.',
	),
	array(
		'slug'    => 'supreme-court-judgment-of-19-may-2026-on-stray-dogs-2026-insc-506',
		'title'   => 'Supreme Court judgment of 19 May 2026 on stray dogs (2026 INSC 506)',
		'kind'    => 'judgment',
		'date'    => '2026-05-19',
		'number'  => '2026 INSC 506',
		'src'     => 'https://api.sci.gov.in/supremecourt/2025/41706/41706_2025_2_1501_71480_Judgement_19-May-2026.pdf',
		'focus'   => 'supreme court stray dogs judgment 19 may 2026',
		'news'    => 11238,
		'excerpt' => 'The Supreme Court of India, in a judgment of 19 May 2026, dismissed all applications to modify, recall or stay its order of 7 November 2025 on removing stray dogs from institutional premises. The bench of Justices Vikram Nath, Sandeep Mehta and N.V. Anjaria held that the Animal Birth Control Rules, 2023 do not require dogs to be released back into such premises, and dismissed the challenges to the Animal Welfare Board of India\'s Standard Operating Procedures. It allowed authorities, after assessment by qualified veterinary experts and within the law, to euthanise rabid, incurably ill or demonstrably dangerous dogs. Every High Court must register a suo motu case to monitor compliance.',
		'quote'   => array(
			'76. In view of the above, the submissions advanced on behalf of the applicants seeking modification, clarification, vacation, recall and/or stay of the directions contained in the order dated 7th November, 2025, particularly those pertaining to the removal of stray dogs from institutional areas and the prohibition on their re-release at the same locations, are found to be devoid of merit. Accordingly, all interlocutory applications seeking modification, clarification, vacation, recall and/or stay of the aforesaid directions stand dismissed.',
			'H. In areas where the population of stray dogs has assumed alarming proportions and where incidents of dog bites or aggressive attacks have become frequent and pose a continuing threat to public safety, the concerned authorities may, subject to due assessment by qualified veterinary experts and strictly in accordance with the provisions of the Prevention of Cruelty to Animals Act, 1960, the Animal Birth Control Rules, 2023 and other applicable statutory protocols, take such measures as may be legally permissible, including euthanasia in cases involving rabid, incurably ill or demonstrably dangerous/aggressive dogs, so as to effectively curb the threat posed to human life and safety.',
			'A. For the purpose of ongoing compliance and monitoring, all High Courts are directed to register a suo moto writ petition, in the name and style of: "In Re: Compliance with the directions issued by the Supreme Court in Suo Motu Writ Petition (Civil) No(s). 5 of 2025", as a continuing mandamus, for monitoring compliance with the directions issued by this Court vide orders dated 22nd August, 2025 and 7th November, 2025, in addition to the directions contained in the present order, and the same shall be placed before and, as far as practicable, taken up by a Division Bench of the concerned High Court.',
		),
		'quote_h' => 'Paragraph 76, and directions H and A',
		'by'      => 'Judgment of Justices Vikram Nath, Sandeep Mehta and N.V. Anjaria, 19 May 2026, in In Re: "City Hounded by Strays, Kids Pay Price", Suo Motu Writ Petition (Civil) No. 5 of 2025. Summary, then three passages word for word from the court\'s PDF.',
	),
);

$out = array();
foreach ( $docs as $d ) {
	$why = function_exists( 'aw_summary_problem' ) ? aw_summary_problem( $d['excerpt'], 'library', true ) : '';
	if ( '' !== $why ) {
		WP_CLI::error( $d['slug'] . ': ' . $why );
	}
	$content = $note( $d['by'] ) . $p( esc_html( $d['excerpt'] ) ) . $h( $d['quote_h'] );
	foreach ( $d['quote'] as $q ) {
		$content .= $p( $q );
	}
	$existing = get_page_by_path( $d['slug'], OBJECT, 'aw_legal' );
	$post     = array(
		'post_type'    => 'aw_legal',
		'post_name'    => $d['slug'],
		'post_title'   => $d['title'],
		'post_excerpt' => $d['excerpt'],
		'post_content' => $content,
		'post_status'  => 'publish', // routine official records (owner: publish what passes; only flagged items wait)
		'post_date'    => $d['date'] . ' 12:00:00', // the document's own date, like the other court records
	);
	if ( $existing ) {
		$post['ID']            = $existing->ID;
		$post['post_date']     = $existing->post_date;
		$post['post_date_gmt'] = $existing->post_date_gmt;
	}
	$id = wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $d['slug'] . ': ' . $id->get_error_message() );
	}
	$kind = get_term_by( 'slug', $d['kind'], 'post_tag' );
	wp_set_object_terms( $id, array_filter( array( $kind ? (int) $kind->term_id : 0, $tag ? (int) $tag->term_id : 0 ) ), 'post_tag', true );
	wp_set_object_terms( $id, array( 'courts-judgments', 'community-dogs' ), 'category', false );
	foreach ( array(
		'aw_source_date'             => $d['date'],
		'aw_doc_sort'                => $d['date'],
		'aw_issuer'                  => 'supreme-court',
		'aw_source_url'              => $d['src'],
		'aw_source_name'             => 'Supreme Court of India',
		'aw_legal_number'            => $d['number'],
		'rank_math_focus_keyword'    => $d['focus'],
		'rank_math_primary_category' => (string) get_term_by( 'slug', 'courts-judgments', 'category' )->term_id,
		'aw_review'                  => 'published by hand 2026-10-17 (campaign India Writes for Its Community Dogs); text from the court\'s PDF',
	) as $k => $v ) {
		update_post_meta( $id, $k, wp_slash( $v ) );
	}
	if ( function_exists( 'aw_mirror_gov_pdf' ) && ! get_post_meta( $id, 'aw_file_id', true ) ) {
		aw_mirror_gov_pdf( $id, $d['src'] ); // the court's own PDF, stored here (media.php)
	}
	if ( function_exists( 'aw_summary_store_entities' ) ) {
		aw_summary_store_entities( $id );
	}
	if ( function_exists( 'aw_seo_fill_post' ) ) {
		aw_seo_fill_post( $id ); // title and description in Rank Math's fields, written as automatic
	}
	if ( function_exists( 'aw_rm_schema_sync' ) ) {
		aw_rm_schema_sync( $id );
	}
	if ( $d['news'] && 'aw_news' === get_post_type( $d['news'] ) && ! in_array( $id, array_map( 'intval', (array) get_post_meta( $d['news'], 'aw_rel_legal' ) ), true ) ) {
		add_post_meta( $d['news'], 'aw_rel_legal', $id ); // the LiveLaw story on this order links to its record
		delete_transient( 'aw_related_' . (int) $d['news'] );
	}
	do_action( 'litespeed_purge_post', $id );
	WP_CLI::log( sprintf( '%s %d %s | pdf=%s | %d words | %s', $existing ? 'updated' : 'created', $id, get_post_status( $id ), get_post_meta( $id, 'aw_file_id', true ) ?: 'none', str_word_count( $d['excerpt'] ), get_permalink( $id ) ) );
}
