<?php
/**
 * Campaign 2.0 Library, second set (16 Oct 2026): seven documents as DRAFTS for the owner. Re-running updates the same
 * items (by slug) and never changes their status. Short documents carry their full English text, word for word from the
 * official PDF; the two Delhi High Court items carry a summary and the operative paragraphs word for word.
 * Sources checked 16 Oct 2026: ccsea.gov.in (What's New PDFs, letter of 6 Jan 2023), egazette.gov.in (G.S.R. 175(E)),
 * delhihighcourt.nic.in (order of 8 Jul 2025); the judgment of 16 Sep 2025 is not on the court's site as a file, so its
 * text is linked on Caseon.
 * Run: wp eval-file campaign2-library-2.php
 */
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$tag = function ( $slug, $name ) {
	$t = get_term_by( 'slug', $slug, 'post_tag' );
	return $t ? (int) $t->term_id : (int) wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) )['term_id'];
};
$t_campaign = $tag( 'campaign-lab-animals-2026', 'Lab animal rules 2026' );

/** A PDF from its official address, stored here once (by source URL). */
function c2l_pdf( $url, $post_id, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'meta_key' => 'aw_source_pdf', 'meta_value' => $url, 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "PDF not fetched: $url" );
		return 0;
	}
	$att = media_handle_sideload( array( 'name' => sanitize_file_name( $title ) . '.pdf', 'tmp_name' => $tmp ), $post_id, $title );
	if ( is_wp_error( $att ) ) {
		@unlink( $tmp ); // phpcs:ignore
		WP_CLI::warning( $att->get_error_message() );
		return 0;
	}
	update_post_meta( $att, 'aw_source_pdf', $url );
	return (int) $att;
}
function c2l_note( $t ) { return "<!-- wp:paragraph {\"className\":\"aw-doc-status\"} -->\n<p class=\"aw-doc-status\">$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function c2l_p( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function c2l_h( $t ) { return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n"; }
function c2l_ul( $items ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->\n\n";
}
function c2l_table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}

$docs = array(
	array(
		'slug'     => 'ccsea-public-notice-peta-india-letters-2025',
		'title'    => 'CCSEA Public Notice on letters of PETA India about Palamur Biosciences (25 June 2025)',
		'excerpt'  => 'CCSEA told all registered establishments to disregard PETA India\'s letters about Palamur Biosciences, saying they hold no legal weight.',
		'rm_title' => 'CCSEA Public Notice on PETA India Letters – Animal Writes',
		'focus'    => 'ccsea public notice peta india,palamur biosciences',
		'kind'     => 'circular',
		'issuer'   => 'ccsea',
		'cats'     => array( 'laws-rules', 'policy-government' ),
		'date'     => '2025-06-25',
		'number'   => 'No. V-11011(12)/2/2020-CPCSEA-DADF',
		'src'      => 'https://ccsea.gov.in/WriteReadData/LnPdf/PublicNotice_LetterofPeTAIndia.pdf',
		'src_name' => 'CCSEA',
		'pdf'      => true,
		'content'  => c2l_note( 'Full text of the public notice dated 25 June 2025, signed by Dr S. K. Dutta, Member Secretary (CCSEA), addressed to all CCSEA registered organisations.' )
			. c2l_p( '<strong>Subject: Letter of PeTA, India regarding violations of CCSEA regulations at the Animal House Facility of Palamur Biosciences Pvt. Ltd., Mahabubnagar, Telangana.</strong>' )
			. c2l_p( 'It has been brought to the notice of the CCSEA that PETA India is sending emails and letters to the registered establishment of CCSEA regarding alleged violation of the CCSEA guidelines and the Prevention of Cruelty to Animals Act 1960 by the M/s Palamur Biosciences, Mahabubnagar, Telangana.' )
			. c2l_p( 'Therefore, all establishments are advised to disregard any such communications from PETA India, as they hold no legal weight, and no measures should be taken based on their requests. The CCSEA remains committed to addressing violations within its jurisdiction and will ensure that appropriate actions are executed in compliance with the applicable laws and guidelines.' )
			. c2l_p( 'The CCSEA advises all establishments to disregard communications from unauthorized organizations and instead report such incidents directly to the authorities for verification. Establishments are encouraged to stay compliant with CCSEA guidelines and maintain transparency in their operations to avoid any unwarranted concerns or allegations.' ),
	),
	array(
		'slug'     => 'ccsea-vaccine-trials-dogs-circular-2022-withdrawn-2023',
		'title'    => 'CCSEA circular on vaccine trials using stray dogs (19 September 2022), withdrawn on 5 October 2023',
		'excerpt'  => 'A 2022 CPCSEA circular let stray dogs be used for new vaccine trials and challenge studies. CCSEA withdrew it "till further orders" in October 2023.',
		'rm_title' => 'CCSEA Vaccine Trials on Dogs Circular 2022 – Animal Writes',
		'focus'    => 'vaccine trials stray dogs ccsea,cpcsea circular dogs',
		'kind'     => 'circular',
		'issuer'   => 'ccsea',
		'cats'     => array( 'laws-rules', 'community-dogs' ),
		'date'     => '2023-10-05',
		'number'   => 'V-11011(13)/10/2023-CPCSEA-DADF',
		'src'      => 'https://ccsea.gov.in/WriteReadData/LnPdf/VaccinetrialsusingDogs(1).pdf',
		'src_name' => 'CCSEA',
		'pdf'      => true,
		'content'  => c2l_note( 'Full text of both letters, addressed to all registered establishments and nominees of CPCSEA and signed by Dr S. K. Dutta, Member Secretary. The PDF is a scan.' )
			. c2l_h( 'Withdrawal, 5 October 2023 (V-11011(13)/10/2023-CPCSEA-DADF)' )
			. c2l_p( '<strong>Subject: Vaccine trials using Dogs - regarding</strong>' )
			. c2l_p( 'This is to inform you that the circular of CCSEA dated 19.09.2022 regarding vaccine trials using stray dogs (enclosed), is hereby withdrawn till further orders.' )
			. c2l_h( 'Circular, 19 September 2022 (No. V-11011(13)/9/2022-CPCSEA-DADF)' )
			. c2l_p( '<strong>Subject: Vaccine trials using Dogs - regarding</strong>' )
			. c2l_p( 'The CPCSEA in its 101st meeting has recommended that stray dogs may be used for new vaccine trials and challenge studies of vaccines, however, they must be quarantined and rehabilitated as per the guidelines of CPCSEA. In cases of the batch testing of established vaccines except for challenge studies, dogs coming to the clinics can be used for safety and/or potency trials.' ),
	),
	array(
		'slug'     => 'administrative-ccsea-amendment-rules-2026-fees',
		'title'    => 'Administrative (Committee for Control and Supervision of Experiments on Animals) Amendment Rules, 2026',
		'excerpt'  => 'Sets CCSEA\'s fees for registering animal facilities, breeding, renewal, ethics committee revision and protocols on large animals. G.S.R. 552(E).',
		'rm_title' => 'CCSEA Registration Fees: 2026 Amendment – Animal Writes',
		'focus'    => 'ccsea registration fees,administrative ccsea amendment rules 2026',
		'kind'     => 'rule',
		'issuer'   => 'central-ministry',
		'cats'     => array( 'laws-rules', 'policy-government' ),
		'date'     => '2026-06-30',
		'number'   => 'G.S.R. 552(E)',
		'src'      => 'https://ccsea.gov.in/WriteReadData/LnPdf/AdministrativeCCSEAAmendmentRules2026.pdf',
		'src_name' => 'Gazette of India (via CCSEA)',
		'pdf'      => true,
		'content'  => c2l_note( 'English text of G.S.R. 552(E), Ministry of Fisheries, Animal Husbandry and Dairying (Department of Animal Husbandry and Dairying), dated 30 June 2026, published in the Gazette of India, Extraordinary, No. 494, 2 July 2026. The draft was S.O. 1496(E) of 12 March 2026, published on 23 March 2026.' )
			. c2l_p( '1. (1) These rules may be called the Administrative (Committee for Control and Supervision of Experiments on Animals) Amendment Rules, 2026. (2) They shall come into force on the date of their publication in the Official Gazette.' )
			. c2l_p( '2. In the Administrative (Committee for Control and Supervision of Experiments on Animals) Rules, 2023, in rule 23, after sub-rule (3), the following sub-rules shall be inserted, namely:-' )
			. c2l_p( '"(4) (a) The fee shall be charged as provided in the Table below:' )
			. c2l_table(
				array( 'Serial number', 'Purpose', 'Small animal (₹)', 'Large animal (₹)' ),
				array(
					array( '1', 'Registration', '12,000', '12,000' ),
					array( '2', 'Registration for breeding for in-house use (Bi)', '18,000', '30,000' ),
					array( '3', 'Registration for breeding for the purpose of trade (Bt)', '30,000', '30,000' ),
					array( '4', 'Renewal of registration for each purpose', '3,000', '3,000' ),
					array( '5', 'Amendment in registration', '12,000', '12,000' ),
					array( '6', 'Revision of Institutional Animal Ethics Committee', '1,200', '1,200' ),
					array( '7', 'Educational protocol on large animals where the Joint Director (Research) or Dean (Research) or equivalent competent authority would certify that the particular protocol under this category is a thesis or academic project.', '-', '1,200 (per protocol)' ),
					array( '8', 'Non-educational or Non-academic protocol on large animals.', '-', '6,000 (per protocol)' ),
				)
			)
			. c2l_p( 'b. The registration shall include but not limit to the purposes of - i. Research for education; ii. Research for commercial; iii. Research; iv. Non-Research Commercial that is to say, Production of Hyperimmune Plasma and Serum.' )
			. c2l_p( 'c. The establishments which are applying for registration with CCSEA, for different purposes are required to submit the fee as per their purposes.' )
			. c2l_p( 'd. The establishments already registered with CCSEA and applying for renewal of registration are required to submit the renewal fee, as provided in clause (a).' )
			. c2l_p( 'e. The amendment in registration shall include but not limit to – i. addition of small animal species (other than the one already registered) for same or different purpose; ii. modification of small animal facility within the same premises; iii. expansion of small animal facility, same or different premises or change of location; iv. modification of large animal facility which will include expansion (increase in number) of existing large animal facility in same or different premises or change of location; and v. addition of different large animal species for same or different purpose.' )
			. c2l_p( 'f. If the purpose is more than one, the fee shall be charged as per the respective purpose of registration, renewal and amendment.' )
			. c2l_p( 'g. The establishments which do not renew their Animal House Facilities before the date of expiry of the registration, shall be charged registration fee for the respective purpose, as specified in clause (a) in place of renewal fee.' )
			. c2l_p( 'h. The fees shall be revised after completion of every three years.' )
			. c2l_p( '(5) The fees and other charges collected by the Committee for Control and Supervision of Experiment on Animal shall be remitted to the Consolidated Fund of India through Bharatkosh".' )
			. c2l_p( '[F. No. V-11011(14)/2/2021-CPCSEA-DADF]' )
			. c2l_p( 'Note: The Administrative (Committee for Control and Supervision of Experiments on Animals) Rules, 2023 were published in the Gazette of India, Extraordinary, Part II, Section 3, Sub-section (ii), dated the 6th February, 2023 vide number G.S.R. 82(E), dated the 2nd February, 2023.' ),
	),
	array(
		'slug'     => 'cpcsea-renamed-ccsea-letter-2023',
		'title'    => 'CPCSEA to be called CCSEA: letter to registered establishments (6 January 2023)',
		'excerpt'  => 'CPCSEA\'s Member Secretary told registered labs the committee would now be called CCSEA: Committee for Control and Supervision of Experiments on Animals.',
		'rm_title' => 'CPCSEA Renamed CCSEA: Letter of 6 Jan 2023 – Animal Writes',
		'focus'    => 'cpcsea renamed ccsea,cpcsea to ccsea',
		'kind'     => 'circular',
		'issuer'   => 'ccsea',
		'cats'     => array( 'laws-rules', 'policy-government' ),
		'date'     => '2023-01-06',
		'number'   => 'V-11011(13)/13/2022-CPCSEA-DADF',
		'src'      => 'https://ccsea.gov.in/WriteReadData/userfiles/file/CCSEA.pdf',
		'src_name' => 'CCSEA',
		'pdf'      => true,
		'content'  => c2l_note( 'Full text of the letter dated 6 January 2023 to all the registered establishments and nominees, signed by Dr S. K. Dutta, Member Secretary (CPCSEA). The PDF is a scan.' )
			. c2l_p( 'It is to inform you that the \'Committee for the Purpose of Control and Supervision of Experiments on Animals\' (CPCSEA) would hereby be defined as \'Committee for Control and Supervision of Experiments on Animals\' (CCSEA).' ),
	),
	array(
		'slug'     => 'delhi-high-court-peta-india-v-ccsea-order-2025-07-08',
		'title'    => 'Delhi High Court order of 8 July 2025 in PETA India v. CCSEA (Palamur Biosciences), W.P.(C) 9350/2025',
		'excerpt'  => 'The Delhi High Court issued notice, directed CCSEA to take remedial steps for the animals at Palamur Biosciences and barred it from taking in new animals.',
		'rm_title' => 'Delhi HC Order in PETA v CCSEA, July 2025 – Animal Writes',
		'focus'    => 'palamur biosciences high court,peta india v ccsea',
		'kind'     => 'order',
		'issuer'   => 'high-court',
		'cats'     => array( 'courts-judgments', 'laws-rules' ),
		'date'     => '2025-07-08',
		'number'   => 'W.P.(C) 9350/2025',
		'src'      => 'https://delhihighcourt.nic.in/app/showlogo/1752071151_bb3404df5d0b3635_587_93502025.pdf/2025',
		'src_name' => 'Delhi High Court',
		'pdf'      => true,
		'content'  => c2l_note( 'Order of Justice Sachin Datta, 8 July 2025, in People for the Ethical Treatment of Animals PETA India v. The Committee for Control and Supervision of Experiments on Animals CCSEA & Anr. (Palamur Biosciences Pvt. Ltd. is respondent no. 2). Summary, then paragraphs 11 to 16 word for word. The full order, which quotes the inspection report of 17 June 2025, is in the PDF.' )
			. c2l_p( 'PETA India alleged that CCSEA had failed to act against Palamur Biosciences, a preclinical contract research organisation and commercial beagle breeder. The court noted CCSEA\'s inspection report of 17 June 2025, which recorded more dogs than approved and 73 dogs said to be under rehabilitation, and found that it "substantiates that there has been abject disregard of the relevant statutory requirements". It issued notice to both respondents.' )
			. c2l_h( 'Paragraphs 11 to 16' )
			. c2l_p( '11. Learned counsel for the respondent no.1 accedes that the inspection report dated 17.06.2025 substantiates the apprehensions/allegations of the petitioner. He, however, submits that subsequent show cause notices have been served on the respondent no.2 and further audits are being conducted before taking requisite action. for the purpose of taking action.' )
			. c2l_p( '12. From the state of affairs as noticed above and as also apparent from the perusal of the inspection report and the photographs filed by the petitioner as Annexure-P8, it is apparent that urgent interim directions are necessitated to ameliorate the conditions of animals in the concerned facility.' )
			. c2l_p( '13. Accordingly, the respondent no.1 is directed to take immediate steps to ensure that the necessary remedial steps are taken, and measures put in place for providing veterinary treatment, proper handling of animals and also to prevent proliferation of practices such as euthanasia, treatment without sedatives, etc. Adequate arrangement is also required to be placed for proper habitat of the animals.' )
			. c2l_p( '14. For the aforesaid purpose, let an inspection be conducted by the respondent no.1 along with representatives of the petitioner to identify the areas of concern. Let the same be done within one week. Let requisite steps be taken within a period of two weeks thereafter. Let a Status Report be filed within four weeks from today.' )
			. c2l_p( '15. Till the aforesaid exercise is conducted, the respondent no.2 is restrained from procuring/ housing any new animals at its facility.' )
			. c2l_p( '16. List on 04.08.2025.' ),
	),
	array(
		'slug'     => 'delhi-high-court-peta-india-v-ccsea-judgment-2025-09-16',
		'title'    => 'Delhi High Court judgment of 16 September 2025 in PETA India v. CCSEA (Palamur Biosciences), W.P.(C) 9350/2025',
		'excerpt'  => 'The court ordered a fresh inspection of Palamur Biosciences within three weeks, told CCSEA to act on any deficiencies, and closed the petition.',
		'rm_title' => 'PETA v CCSEA: Delhi HC Judgment, Sept 2025 – Animal Writes',
		'focus'    => 'palamur biosciences judgment,peta india v ccsea judgment',
		'kind'     => 'judgment',
		'issuer'   => 'high-court',
		'cats'     => array( 'courts-judgments', 'laws-rules' ),
		'date'     => '2025-09-16',
		'number'   => 'W.P.(C) 9350/2025',
		'src'      => 'https://www.caseon.in/public/case/people-for-the-ethical-treatment-of-animals-peta-india-vs-the-committee-for-control-and-supervision-of-experiments-on-animals-ccsea',
		'src_name' => 'Delhi High Court (text on Caseon)',
		'pdf'      => false,
		'content'  => c2l_note( 'Judgment of Justice Sachin Datta, pronounced on 16 September 2025. Summary, then paragraphs 20 to 24 word for word. The full text is linked on Caseon; the court\'s own copy is on the Delhi High Court website.' )
			. c2l_p( 'PETA India asked the court to have CCSEA revoke Palamur Biosciences\' licences and registrations and to rehabilitate the animals. Three inspections had been held: by a multidisciplinary committee on 11 and 12 June 2025 (report of 17 June), a micro-audit on 28 and 29 June (report of 10 July), and one ordered by the court on 17 July (report of 24 July). The court found the third "mired in controversy", as it was held without the Local Commissioner and PETA India alleged a conflict of interest, and ordered a fresh one.' )
			. c2l_h( 'Paragraphs 20 to 24' )
			. c2l_p( '20. In the circumstances, it is directed that a fresh inspection be undertaken at the facility of the respondent no.2. The modalities for conduct of the inspection shall be as under:' )
			. c2l_ul(
				array(
					'i. The inspection team shall comprise of three members viz.: (a) Dr. Arvind Ingle, Member of CCSEA; (b) Dr. M. Jerald Mahesh Kumar, Principal Scientist CCMB, Hyderabad (both being members of committee constituted by the respondent no.1 for conducting micro-audit on 28.06.2025 and 29.06.2025); and (c) Ms. Shradha Deshmukh, Local Commissioner (appointed by this Court in the present proceedings vide order dated 17.07.2025).',
					'ii. The aforesaid Local Commissioner shall be entitled to take the assistance of a veterinarian who shall accompany the Local Commissioner during the inspection. The Local Commissioner shall ensure that the veterinarian neither has affiliation with the petitioner nor has any conflict of interest with either of the parties. The cost/s for the same shall be borne by the petitioner.',
					'iii. The inspection shall be conducted as expeditiously as possible and in any event, within a period of three weeks from today. The Local Commissioner shall coordinate with the concerned parties and the other members of the Inspection team to fix the date and time for the aforesaid inspection.',
					'iv. For the aforesaid exercise, the Local Commissioner shall be paid a sum of Rs.2 Lakhs by the petitioner (in addition to expenses and the fee/s directed to be paid to the Local Commissioner vide order dated 17.07.2025). The charges for the other members of the Inspection Committee (if any) shall be borne by the respondent no.1 and / or the respondent no.2.',
				)
			)
			. c2l_p( '21. Upon conclusion of the aforesaid exercise, the inspection report (together with recommendations, if any) shall be provided to the petitioner and the respondents. Upon receipt of the inspection report, the respondent no.1 shall take cognizance of any deficiencies that may be revealed in the inspection report and shall take appropriate steps in accordance with law. The respondent no.2 shall also take immediate rectificatory steps as may be warranted in terms of the inspection report and/or directions issued by the respondent no.1. This Court acknowledges the fair stand of Mr. Vivek Kohli, learned senior counsel for the respondent no.2, that appropriate rectificatory steps (as may be required) shall be taken if any deficiencies are found during inspection.' )
			. c2l_p( '22. The interim order dated 08.07.2025, whereby, the respondent no.2 was restrained from procuring / housing any new animals at its facility, shall stand vacated upon the aforesaid inspection being conducted.' )
			. c2l_p( '23. Needless to say, the respondent no.1 shall continue to exercise its regulatory oversight in respect of the activities of the respondent no.2 to ensure that the said respondent is in compliance with all applicable rules and guidelines.' )
			. c2l_p( '24. The petition is disposed of in the above terms. Pending applications also stand disposed of.' ),
	),
	array(
		'slug'     => 'new-drugs-clinical-trials-amendment-rules-2023-non-animal-methods',
		'title'    => 'New Drugs and Clinical Trials (Amendment) Rules, 2023 (non-animal testing methods)',
		'excerpt'  => 'Lists cell-based assays, organ chips, computer modelling and other human biology-based methods, with animal studies, as non-clinical tests for new drugs.',
		'rm_title' => 'NDCT Amendment Rules 2023, G.S.R. 175(E) – Animal Writes',
		'focus'    => 'new drugs and clinical trials amendment rules 2023,non-animal methods drugs india',
		'kind'     => 'rule',
		'issuer'   => 'central-ministry',
		'cats'     => array( 'laws-rules', 'policy-government' ),
		'date'     => '2023-03-09',
		'number'   => 'G.S.R. 175(E)',
		'src'      => 'https://egazette.gov.in/WriteReadData/2023/244322.pdf',
		'src_name' => 'Gazette of India',
		'pdf'      => true,
		'content'  => c2l_note( 'English text of G.S.R. 175(E), Ministry of Health and Family Welfare (Department of Health and Family Welfare), dated 9 March 2023, published in the Gazette of India, Extraordinary, No. 136, 13 March 2023. The draft was G.S.R. 835(E) of 22 November 2022.' )
			. c2l_p( '1. (1) These rules may be called the New Drugs and Clinical Trials (Amendment) Rules, 2023. (2) They shall come into force on the date of their publication in the Official Gazette.' )
			. c2l_p( '2. In the New Drugs and Clinical Trials Rules, 2019, in the First Schedule, in paragraph 3, in sub paragraph (1), for clause (b), the following shall be substituted, relating to development methodology namely: —' )
			. c2l_p( '"(b) (I) The general requirements of non-clinical studies have been specified in the Second Schedule.' )
			. c2l_p( '(II) The non-clinical testing methods to assess the safety and efficacy of a new drug or investigational new drug include the following, namely: - (i) Cell-based assay; (ii) Organ chips and micro physiological systems; (iii) Sophisticated computer modeling; (iv) Other human biology-based test methods; (v) Animal Studies.".' )
			. c2l_p( 'Note: The New Drugs and Clinical Trial Rules, 2019 were published in the Gazette of India, Extraordinary, Part II, section 3, sub-section (i) vide Notification Number G.S.R. 227(E), dated the 19th March, 2019 and last amended vide notification number G.S.R. 778(E), dated the 14th October, 2022.' ),
	),
);

foreach ( $docs as $d ) {
	$existing = get_page_by_path( $d['slug'], OBJECT, 'aw_legal' );
	$post     = array(
		'post_type'    => 'aw_legal',
		'post_name'    => $d['slug'],
		'post_title'   => $d['title'],
		'post_excerpt' => $d['excerpt'],
		'post_content' => $d['content'],
		'post_status'  => $existing ? $existing->post_status : 'draft', // never publish or un-publish on re-run
	);
	if ( $existing ) {
		$post['ID'] = $existing->ID;
	}
	$id = wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $d['slug'] . ': ' . $id->get_error_message() );
		continue;
	}
	$kind = get_term_by( 'slug', $d['kind'], 'post_tag' );
	wp_set_object_terms( $id, array_filter( array( $kind ? (int) $kind->term_id : 0, $t_campaign ) ), 'post_tag', true );
	wp_set_object_terms( $id, $d['cats'], 'category', false );
	foreach ( array(
		'aw_source_date'             => $d['date'],
		'aw_doc_sort'                => $d['date'],
		'aw_issuer'                  => $d['issuer'],
		'aw_source_url'              => $d['src'],
		'aw_source_name'             => $d['src_name'],
		'aw_legal_number'            => $d['number'],
		'rank_math_title'            => $d['rm_title'],
		'rank_math_description'      => $d['excerpt'],
		'rank_math_focus_keyword'    => $d['focus'],
		'rank_math_primary_category' => (string) get_term_by( 'slug', $d['cats'][0], 'category' )->term_id,
		'_aw_owner_draft'            => '2026-10-16 Campaign 2.0 Library set 2; text from the official PDF',
	) as $k => $v ) {
		update_post_meta( $id, $k, wp_slash( $v ) );
	}
	if ( 'rule' === $d['kind'] ) {
		update_post_meta( $id, 'aw_legal_force', 'InForce' );
	}
	if ( $d['pdf'] && ! get_post_meta( $id, 'aw_file_id', true ) ) {
		$att = c2l_pdf( $d['src'], $id, $d['slug'] );
		if ( $att ) {
			update_post_meta( $id, 'aw_file_id', $att );
		}
	}
	if ( function_exists( 'aw_rm_schema_sync' ) ) {
		aw_rm_schema_sync( $id );
	}
	WP_CLI::log( sprintf( '%s %d %s | t=%d d=%d | %s', $existing ? 'updated' : 'created', $id, get_post_status( $id ), mb_strlen( $d['rm_title'] ), mb_strlen( $d['excerpt'] ), $d['slug'] ) );
}
