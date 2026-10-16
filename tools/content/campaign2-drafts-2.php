<?php
/**
 * Campaign 2.0 DRAFTS, second set (16 Oct 2026): News N4 (Palamur timeline) and Resources R4-R6. Never publishes;
 * re-running updates the same drafts (by slug) and skips any that are no longer drafts. Same pattern as
 * campaign2-drafts.php. Facts checked on 16 Oct 2026 against: the CCSEA public notice of 25 Jun 2025, Delhi High Court
 * orders of 8 Jul 2025 and 16 Sep 2025 (W.P.(C) 9350/2025), the US FDA warning letter of 11 Dec 2025, CCSEA's website
 * and its 6 Jan 2023 letter, the CPCSEA Compendium (PCA Act Chapter IV, 1998 Rules, 2001 and 2006 amendments),
 * G.S.R. 175(E) of 9 Mar 2023, the Cosmetics Rules, 2020 and PIB's 28 Nov 2014 release; news dates against at least
 * two reports (Deccan Herald, Deccan Chronicle, ThePrint, PETA India).
 * Run: wp eval-file campaign2-drafts-2.php
 */
function c2e_p( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function c2e_h( $t, $l = 2 ) {
	$a = 2 === $l ? '' : ' {"level":' . $l . '}';
	return "<!-- wp:heading$a -->\n<h$l class=\"wp-block-heading\">$t</h$l>\n<!-- /wp:heading -->\n\n";
}
function c2e_ul( $items, $ordered = false ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	$t = $ordered ? 'ol' : 'ul';
	$a = $ordered ? ' {"ordered":true}' : '';
	return "<!-- wp:list$a -->\n<$t class=\"wp-block-list\">$li</$t>\n<!-- /wp:list -->\n\n";
}
function c2e_table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function c2e_faq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return c2e_h( 'Questions and answers' ) . '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function c2e_a( $url, $t ) { return '<a href="' . esc_url( $url ) . '">' . $t . '</a>'; }
function c2e_mark( $t ) { return c2e_p( '<strong>DRAFT for owner approval. Facts checked 16 Oct 2026 against ' . $t . '.</strong>' ); }
function c2e_src( $items ) { return c2e_h( 'Sources' ) . c2e_ul( $items ); }

$camp  = home_url( '/campaigns/lab-animals-rules-2026/' );
$draft = home_url( '/library/draft-laboratory-animals-rules-2026/' );
$r1998 = home_url( '/library/breeding-experiments-animals-rules-1998/' );
$act   = get_permalink( 117 );
$n1    = home_url( '/news/draft-lab-animal-rules-2026-rule-by-rule/' );
$n2    = home_url( '/news/who-approves-animal-experiments-draft-rules/' );
$n3    = home_url( '/news/lab-animal-rules-today-vs-draft-2026/' );
$n4    = home_url( '/news/palamur-beagles-case-timeline/' );
$r1    = home_url( '/resources/how-to-object-to-a-draft-rule-india/' );
$r2    = home_url( '/resources/animal-experiment-severity-mild-moderate-severe/' );
$r3    = home_url( '/resources/iaec-full-form-members-approvals/' );
$r4    = home_url( '/resources/cpcsea-full-form-ccsea/' );
$r5    = home_url( '/resources/alternatives-to-animal-testing-india/' );
$fda_n = get_permalink( 11800 );
$cta   = c2e_p( 'Objections to the draft lab animal rules can be sent to CCSEA until 5 November 2026. ' . c2e_a( $camp . '#send', 'Write and send yours in two minutes' ) . '.' );
$disc  = "<!-- wp:paragraph {\"className\":\"aw-guide-disclaimer\"} -->\n<p class=\"aw-guide-disclaimer\">This guide is general information, not legal advice.</p>\n<!-- /wp:paragraph -->\n\n";

/* Checked source links. */
$s_notice  = c2e_a( 'https://ccsea.gov.in/WriteReadData/LnPdf/PublicNotice_LetterofPeTAIndia.pdf', 'CCSEA: Public Notice on letters of PETA India, 25 June 2025' );
$s_hc1     = c2e_a( 'https://delhihighcourt.nic.in/app/showlogo/1752071151_bb3404df5d0b3635_587_93502025.pdf/2025', 'Delhi High Court: order of 8 July 2025, W.P.(C) 9350/2025' );
$s_hc2     = c2e_a( 'https://www.caseon.in/public/case/people-for-the-ethical-treatment-of-animals-peta-india-vs-the-committee-for-control-and-supervision-of-experiments-on-animals-ccsea', 'Delhi High Court: judgment of 16 September 2025, W.P.(C) 9350/2025 (text on Caseon)' );
$s_fda     = c2e_a( 'https://www.fda.gov/inspections-compliance-enforcement-and-criminal-investigations/warning-letters/palamur-biosciences-private-limited-708579-12112025', 'US FDA: warning letter to Palamur Biosciences, 11 December 2025' );
$s_dh      = c2e_a( 'https://www.deccanherald.com/india/telangana/telangana-police-file-fir-against-research-lab-for-alleged-animal-abuse-3592148', 'Deccan Herald: Telangana police file FIR against research lab, 18 June 2025' );
$s_peta1   = c2e_a( 'https://www.petaindia.com/blog/in-historic-first-fir-registered-against-animal-experimentation-laboratory-palamur-biosciences-following-peta-india-complaint/', 'PETA India: FIR registered against Palamur Biosciences, 18 June 2025' );
$s_print   = c2e_a( 'https://theprint.in/india/after-firs-inspections-fate-of-tortured-animals-at-palamur-lab-rests-on-new-panel-report/2674019/', 'ThePrint: After FIRs and inspections, fate of animals at Palamur lab rests on new panel report, 3 July 2025' );
$s_dc      = c2e_a( 'https://www.deccanchronicle.com/southern-states/telangana/telangana-hc-seeks-response-on-peta-plea-for-rehab-of-beagles-1974411', 'Deccan Chronicle: HC seeks response on PETA plea for rehab of beagles, 27 July 2026' );
$s_peta2   = c2e_a( 'https://www.petaindia.com/blog/release-the-73-beagles-delhi-high-court-directs-palamur-biosciences/', 'PETA India: High Court of Delhi seeks CCSEA response, 28 July 2026' );
$s_ccsea   = c2e_a( 'https://ccsea.gov.in/Content/57_1_Introduction.aspx', 'CCSEA: Introduction' );
$s_func    = c2e_a( 'https://ccsea.gov.in/Content/59_1_Functions.aspx', 'CCSEA: Functions' );
$s_rename  = c2e_a( 'https://ccsea.gov.in/WriteReadData/userfiles/file/CCSEA.pdf', 'CCSEA: letter on the change of name, 6 January 2023' );
$s_comp    = c2e_a( 'https://ccsea.gov.in/WriteReadData/userfiles/file/Compendium%20of%20CPCSEA.pdf', 'CCSEA: Compendium of CPCSEA' ) . ' (PCA Act Chapter IV, 1998 Rules, 2001 and 2006 amendments, standard operating procedures)';
$s_icmr    = c2e_a( 'https://www.icmr.gov.in/icmrobject/custom_data/1703674293_cpcsea.pdf', 'ICMR: letter on a CPCSEA member nominated by Gazette notification for three years, 6 December 2021' );
$s_gsr864  = c2e_a( 'https://ccsea.gov.in/WriteReadData/LnPdf/DraftPreventionofCrueltytoAnimals(ControlandSupervisionofExperiment,BreedingandTradingofLaboratoryAnimals)Rules,2026.pdf', 'CCSEA: Draft Laboratory Animals Rules, 2026, G.S.R. 864(E)' ) . ' (Gazette of India, Extraordinary, No. 793, 6 October 2026)';
$s_gsr175  = c2e_a( 'https://egazette.gov.in/WriteReadData/2023/244322.pdf', 'Gazette of India: New Drugs and Clinical Trials (Amendment) Rules, 2023, G.S.R. 175(E), 9 March 2023' );
$s_pib     = c2e_a( 'https://www.pib.gov.in/newsite/PrintRelease.aspx?relid=112066', 'PIB: Ban on Testing of Cosmetics on Animals, 28 November 2014' );
$s_cos2020 = c2e_a( 'https://www.goa.gov.in/wp-content/uploads/2021/01/Cosmetics-Rules-2020.pdf', 'Cosmetics Rules, 2020, G.S.R. 763(E), 15 December 2020' ) . ' (copy on the Goa Government portal)';

$docs = array();

/* N4 */
$docs[] = array(
	'type'     => 'aw_news',
	'slug'     => 'palamur-beagles-case-timeline',
	'title'    => 'The Palamur beagles case: a timeline',
	'rm_title' => 'Palamur Beagles Case: A Timeline of Events – Animal Writes',
	'desc'     => 'Palamur Biosciences from the June 2025 complaint to the Delhi High Court in July 2026: inspections, the FIR, CCSEA\'s notice and the US FDA letter.',
	'focus'    => 'palamur biosciences,palamur beagles,palamur biosciences peta',
	'cats'     => array( 'courts-judgments', 'laws-rules' ),
	'content'  => c2e_mark( 'the CCSEA notice, Delhi High Court orders, the US FDA letter and news reports' )
		. c2e_p( 'Palamur Biosciences Pvt Ltd runs a preclinical contract research laboratory and beagle breeding facility at Bhoothpur, Mahabubnagar, in Telangana, registered with the Committee for Control and Supervision of Experiments on Animals (CCSEA). Since June 2025 it has been the subject of inspections, a police case and two petitions in the Delhi High Court. Palamur has denied the allegations. This is what happened, in order.' )
		. c2e_table(
			array( 'Date', 'What happened' ),
			array(
				array( '20–27 Jan 2025', 'US FDA investigators inspect the facility\'s Good Laboratory Practice studies for medical devices (FDA letter).' ),
				array( '10 Jun 2025', 'PETA India reports abuse and neglect of animals at the facility to CCSEA and other authorities, based on whistleblower evidence.' ),
				array( '11–12 Jun 2025', 'A multidisciplinary committee authorised by CCSEA, with members from CCSEA, the Animal Welfare Board of India and others, inspects the facility.' ),
				array( '17 Jun 2025', 'The committee\'s report finds "entrenched structural, procedural, and ethical failures" in housing, veterinary care, euthanasia and records, and calls for removal and rehabilitation of animals and a review of Palamur\'s registration.' ),
				array( '16–18 Jun 2025', 'PETA India files a police complaint on 16 June. Boothpur police register an FIR under the BNSS, Sections 34, 269, 289, 337 and 429 of the IPC and the Wild Life (Protection) Act, reported on 18 June as the first against an animal experimentation lab in India.' ),
				array( '25 Jun 2025', 'CCSEA issues a public notice telling all registered establishments to "disregard" PETA India\'s letters about Palamur, saying they "hold no legal weight". The same day it sets up a second committee.' ),
				array( '28–29 Jun 2025', 'The second committee inspects the facility for a micro-audit. Its report is dated 10 July 2025.' ),
				array( '8 Jul 2025', 'On PETA India\'s petition, the Delhi High Court issues notice to CCSEA and Palamur, orders interim steps for the animals and bars Palamur from procuring or housing any new animals. CCSEA\'s counsel accepts that the 17 June report substantiates the allegations.' ),
				array( '17–24 Jul 2025', 'The court orders a third inspection with a Local Commissioner. It takes place without her. PETA India says its report of 24 July "virtually gives a clean chit"; the court later calls that inspection "mired in controversy".' ),
				array( '16 Sep 2025', 'The court orders a fresh inspection by a three-member team within three weeks. CCSEA must act on any deficiencies and Palamur must take "immediate rectificatory steps". The ban on new animals ends once the inspection is done, and the petition is closed.' ),
				array( '11 Dec 2025', 'The US FDA sends Palamur a warning letter citing "serious violations" of its Good Laboratory Practice regulations.' ),
				array( '27 Jul 2026', 'In a new PETA India petition, the Delhi High Court issues notice to CCSEA. The petition asks for the rehabilitation of 73 beagles and other animals, cancellation of Palamur\'s registration, and publication of animal-use data by labs.' ),
			)
		)
		. c2e_h( 'The 73 beagles' )
		. c2e_p( 'The 17 June 2025 inspection counted 73 dogs, 62 male and 11 female, that the facility said were "under rehabilitation", with no written records. PETA India told the court in July 2026 that the dogs are still at the laboratory and that Palamur turned down an offer from Freagles, which rehomes beagles retired from labs.' )
		. c2e_h( 'What Palamur says' )
		. c2e_p( 'Palamur denied the allegations and told ThePrint in July 2025 that it upholds "the highest standards" of ethical research. In September 2025 its counsel told the High Court that it follows its licences and the law, and would fix any shortcomings found.' )
		. c2e_h( 'Why it matters for the draft rules' )
		. c2e_p( 'The inspection committee asked for a detailed audit of Palamur\'s own Institutional Animals Ethics Committee (IAEC). Under the 2026 draft rules, a lab\'s own IAEC would approve all mild and moderate experiments on every species, including dogs (Rule 11(3)). ' . c2e_a( $n2, 'Who approves an animal experiment under the draft' ) . ' · ' . c2e_a( $fda_n, 'Our report on the FDA letter' ) . '.' )
		. $cta
		. c2e_src( array( $s_notice, $s_hc1, $s_hc2, $s_fda, $s_dh, $s_peta1, $s_print, $s_dc, $s_peta2 ) ),
);

/* R4 */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'cpcsea-full-form-ccsea',
	'title'    => 'CPCSEA full form, and why it\'s now CCSEA',
	'rm_title' => 'CPCSEA Full Form, and Why It\'s Now CCSEA – Animal Writes',
	'desc'     => 'CPCSEA, now CCSEA, regulates animal experiments in India. Its full form, the 2023 name change, the law behind it, its members and what it does.',
	'focus'    => 'cpcsea full form,ccsea full form,cpcsea',
	'cats'     => array( 'laws-rules', 'policy-government' ),
	'content'  => c2e_mark( 'CCSEA\'s website and letters and the PCA Act as published in the CPCSEA Compendium' )
		. c2e_p( 'CPCSEA stands for the <strong>Committee for the Purpose of Control and Supervision of Experiments on Animals</strong>. Since January 2023 it is called the <strong>Committee for Control and Supervision of Experiments on Animals (CCSEA)</strong>. It is the same body under the same law: only "for the Purpose of" was dropped.' )
		. c2e_h( 'The name change' )
		. c2e_p( 'A letter from the Committee\'s Member Secretary dated 6 January 2023 told all registered establishments and nominees that CPCSEA "would hereby be defined as" CCSEA. Older rules, forms and guidelines still say CPCSEA.' )
		. c2e_h( 'The law behind it' )
		. c2e_p( 'Chapter IV of the ' . c2e_a( $act, 'Prevention of Cruelty to Animals Act, 1960' ) . ' deals with experiments on animals:' )
		. c2e_ul(
			array(
				'<strong>Section 15:</strong> the Central Government, on the advice of the Animal Welfare Board of India, may set up a committee of officials and non-officials to control and supervise experiments on animals, and nominates its chairman.',
				'<strong>Section 17:</strong> the Committee must take all measures needed so that animals are not put to unnecessary pain or suffering before, during or after experiments, and may make rules for this.',
				'<strong>Section 18:</strong> it can authorise officers to inspect any place where experiments are done and to see records.',
				'<strong>Section 19:</strong> if its rules are broken, it can bar a person or institution from experimenting, for a period or indefinitely, after a hearing.',
				'<strong>Section 20:</strong> breaking such an order is punishable with a fine of up to ₹200.',
			)
		)
		. c2e_h( 'History' )
		. c2e_table(
			array( 'Year', 'Event' ),
			array(
				array( '1960', 'The PCA Act provides for the Committee (Section 15).' ),
				array( '1998', 'The Committee makes the Breeding of and Experiments on Animals (Control and Supervision) Rules, 1998 (S.O. 1074, 15 December 1998).' ),
				array( '2001', 'First amendment to the 1998 Rules (S.O. 134(E), 15 February 2001).' ),
				array( '2006', 'Second amendment (S.O. 1818(E), 23 October 2006): consider non-animal alternatives first, use the fewest animals, and make investigators responsible for aftercare and rehabilitation.' ),
				array( '2023', 'CPCSEA becomes CCSEA (letter of 6 January 2023).' ),
				array( '2026', 'Draft Laboratory Animals Rules, G.S.R. 864(E), published on 6 October 2026 to replace the 1998 Rules.' ),
			)
		)
		. c2e_p( c2e_a( $r1998, 'Read the 1998 Rules and their amendments' ) . ' · ' . c2e_a( $draft, 'Read the 2026 draft' ) . '.' )
		. c2e_h( 'Members' )
		. c2e_p( 'CCSEA is a statutory committee of the Department of Animal Husbandry and Dairying, Ministry of Fisheries, Animal Husbandry and Dairying. Its website lists 19 members. The Chairman is Dr Muthukumarasamy B., Joint Secretary (Animal Welfare), and the Member Secretary is Dr S. K. Dutta, Joint Commissioner, DAHD. Members are named by Gazette notification, typically for three years.' )
		. c2e_h( 'What CCSEA does' )
		. c2e_ul(
			array(
				'Registers establishments that breed animals for experiments or experiment on them, and renews registrations.',
				'Constitutes and revises each establishment\'s Institutional Animals Ethics Committee (' . c2e_a( $r3, 'IAEC' ) . ') and appoints its own nominees to it.',
				'Approves animal house facilities, and examines and approves research protocols on large animals.',
				'Inspects animal house facilities, and reviews nominees\' inspection reports and IAEC minutes.',
				'Writes and revises guidelines, and advises the Government on the welfare of animals used in experiments.',
				'Promotes the 3Rs (replacement, reduction and refinement) and keeps a database of non-animal methods.',
			)
		)
		. c2e_h( 'Under the 2026 draft' )
		. c2e_p( 'CCSEA would decide only severe and non-recovery experiments on animals above rodents and rabbits (Rule 11(2)). Mild and moderate experiments on every species would go to the lab\'s own IAEC (Rule 11(3)). ' . c2e_a( $n2, 'Who approves an animal experiment under the draft' ) . '.' )
		. c2e_faq(
			array(
				array( 'What is the full form of CPCSEA?', 'Committee for the Purpose of Control and Supervision of Experiments on Animals. Since January 2023 it is called the Committee for Control and Supervision of Experiments on Animals (CCSEA).' ),
				array( 'Under which law is CCSEA set up?', 'Section 15 of the Prevention of Cruelty to Animals Act, 1960.' ),
				array( 'Which ministry is CCSEA under?', 'The Department of Animal Husbandry and Dairying, Ministry of Fisheries, Animal Husbandry and Dairying.' ),
			)
		)
		. $cta
		. c2e_src( array( $s_ccsea, $s_func, $s_rename, $s_comp, $s_icmr, $s_gsr864 ) ),
);

/* R5 */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'alternatives-to-animal-testing-india',
	'title'    => 'Alternatives to animal testing used in India',
	'rm_title' => 'Alternatives to Animal Testing in India – Animal Writes',
	'desc'     => 'The 3Rs in Indian law, the 2014 ban on testing cosmetics on animals, and the 2023 drug rules that name cell-based, organ-chip and computer methods.',
	'focus'    => 'alternatives to animal testing india,non-animal methods india,3rs animal testing',
	'cats'     => array( 'laws-rules' ),
	'content'  => c2e_mark( 'the PCA Act, the 2006 amendment, the Cosmetics Rules, 2020, G.S.R. 175(E) and PIB' )
		. c2e_p( 'Indian law allows experiments on animals but asks for other methods first where they work. Three sets of rules matter: the 3Rs in the rules on animal experiments, the ban on testing cosmetics on animals, and the 2023 change to the drug trial rules.' )
		. c2e_h( 'The 3Rs' )
		. c2e_ul(
			array(
				'<strong>Replacement:</strong> use methods that do not involve animals.',
				'<strong>Reduction:</strong> use the fewest animals that give a valid result.',
				'<strong>Refinement:</strong> change procedures and care to reduce pain and distress.',
			)
		)
		. c2e_p( 'Section 17(2) of the ' . c2e_a( $act, 'PCA Act' ) . ' already asks that experiments be avoided wherever possible, for example in medical colleges where books, models and films will do, and that experiments are not done merely to acquire manual skill. The 2006 amendment to the ' . c2e_a( $r1998, '1998 Rules' ) . ' added that "replacement alternatives not involving experiments on animals should be given due and full consideration", with sound justification if available alternatives are not used, and that the minimum number of animals be used. CCSEA lists promoting the 3Rs and keeping a database of non-animal methods among its functions. Rule 3(1) of the ' . c2e_a( $draft, '2026 draft' ) . ' asks for non-animal methods "wherever possible".' )
		. c2e_h( 'Cosmetics: banned since 2014' )
		. c2e_p( 'G.S.R. 346(E) of 21 May 2014 amended the Drugs and Cosmetics Rules, 1945 to prohibit testing cosmetics on animals in India, on the Drugs Technical Advisory Board\'s recommendation. The Bureau of Indian Standards had already approved non-animal tests for cosmetic safety in August 2013. Both bans are now in the Cosmetics Rules, 2020:' )
		. c2e_ul(
			array(
				'Rule 39(7): "No person shall use any animal for testing of cosmetics."',
				'Rule 18(4): no cosmetic tested on animals after 12 November 2014 may be imported.',
			)
		)
		. c2e_h( 'Drugs: the 2023 amendment' )
		. c2e_p( 'The ' . c2e_a( 'https://egazette.gov.in/WriteReadData/2023/244322.pdf', 'New Drugs and Clinical Trials (Amendment) Rules, 2023' ) . ' (G.S.R. 175(E), 9 March 2023, Gazette of India No. 136 of 13 March 2023) list the non-clinical methods that can be used to assess the safety and efficacy of a new drug:' )
		. c2e_ul(
			array(
				'Cell-based assays',
				'Organ chips and microphysiological systems',
				'Sophisticated computer modelling',
				'Other human biology-based test methods',
				'Animal studies',
			)
		)
		. c2e_p( 'Animal studies stay on the list. The change means the other methods can be offered in their place where they are suitable.' )
		. c2e_h( 'What these methods are' )
		. c2e_table(
			array( 'Method', 'What it is' ),
			array(
				array( 'Cell-based assays', 'Tests on human or animal cells grown in a dish, including 3D tissue models' ),
				array( 'Organ chips', 'Small devices lined with living human cells that copy how an organ such as a lung or liver works' ),
				array( 'Computer modelling', 'Software that predicts how a substance behaves in the body from existing data' ),
				array( 'Human biology-based methods', 'Other tests built on human biology, such as stem-cell derived tissues' ),
			)
		)
		. c2e_h( 'Under the 2026 draft' )
		. c2e_p( 'The draft repeats the call for non-animal methods "wherever possible" (Rule 3(1)). ' . c2e_a( $n1, 'What the draft says, rule by rule' ) . '.' )
		. c2e_faq(
			array(
				array( 'Is animal testing of cosmetics banned in India?', 'Yes. Testing cosmetics on animals has been banned since May 2014, and importing cosmetics tested on animals after 12 November 2014 is also banned. Both are in the Cosmetics Rules, 2020.' ),
				array( 'Can new drugs be tested without animals in India?', 'The New Drugs and Clinical Trials (Amendment) Rules, 2023 list cell-based assays, organ chips, computer modelling and other human biology-based methods alongside animal studies as non-clinical testing methods.' ),
				array( 'What are the 3Rs?', 'Replacement, reduction and refinement: use non-animal methods where possible, use fewer animals, and reduce pain and distress.' ),
			)
		)
		. $cta
		. c2e_src( array( $s_gsr175, $s_pib, $s_cos2020, $s_comp, $s_func, $s_gsr864 ) ),
);

/* R6 pillar */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'animal-testing-laws-india',
	'title'    => 'Animal testing laws in India: a complete guide',
	'rm_title' => 'Animal Testing Laws in India: Full Guide – Animal Writes',
	'desc'     => 'Every law on animal testing in India: the PCA Act, the 1998 Rules, CCSEA and IAECs, the cosmetics ban, the 2023 drug rules and the 2026 draft rules.',
	'focus'    => 'animal testing laws in india,animal experimentation laws india,animal testing rules india',
	'cats'     => array( 'laws-rules', 'policy-government' ),
	'content'  => c2e_mark( 'the PCA Act, the 1998 Rules and amendments, G.S.R. 864(E), the Cosmetics Rules, 2020 and G.S.R. 175(E)' )
		. c2e_p( 'Experiments on animals are legal in India, but only under rules. This guide sets out each law, who enforces it, what is banned, and what the 2026 draft rules would change.' )
		. c2e_h( '1. The Act: Prevention of Cruelty to Animals Act, 1960' )
		. c2e_p( 'Chapter IV of the ' . c2e_a( $act, 'PCA Act' ) . ' covers experiments. Section 14 says nothing in the Act makes experiments unlawful when they are done to advance knowledge useful for saving or prolonging life, relieving suffering or fighting disease in humans, animals or plants. Sections 15 to 19 set up the regulator and give it powers to make rules, inspect and prohibit. Section 17(2) lists what the rules must aim for, including anaesthesia for operations as far as possible, avoiding experiments where books, models or films will do, and proper care before and after.' )
		. c2e_p( 'Section 20 sets the penalty for breaking the regulator\'s orders: a fine of up to ₹200. Only Parliament can change it; rules cannot.' )
		. c2e_h( '2. The regulator: CCSEA' )
		. c2e_p( 'The Committee for Control and Supervision of Experiments on Animals (CCSEA), called CPCSEA until 2023, registers establishments, appoints nominees to their ethics committees, approves protocols on large animals and inspects facilities. ' . c2e_a( $r4, 'CPCSEA full form, and why it\'s now CCSEA' ) . '.' )
		. c2e_h( '3. The rules in force: 1998 Rules' )
		. c2e_p( 'The ' . c2e_a( $r1998, 'Breeding of and Experiments on Animals (Control and Supervision) Rules, 1998' ) . ', amended in 2001 and 2006, require:' )
		. c2e_ul(
			array(
				'registration with CCSEA of every establishment that breeds animals for experiments or experiments on them;',
				'an Institutional Animals Ethics Committee (' . c2e_a( $r3, 'IAEC' ) . ') in each establishment, with CCSEA nominees;',
				'animals from registered breeders, or other legal sources only with written permission from a competent authority (2006 amendment);',
				'anaesthesia for any procedure more severe than simple inoculation or superficial venesection (Rule 9(d));',
				'consideration of non-animal alternatives and the fewest animals possible, and aftercare and rehabilitation by investigators (2006 amendment).',
			)
		)
		. c2e_p( 'Under CCSEA\'s procedures, experiments on large animals need CCSEA\'s approval; the IAEC approves those on small animals.' )
		. c2e_h( '4. What is banned' )
		. c2e_ul(
			array(
				'<strong>Cosmetics:</strong> testing on animals is banned since May 2014, and so is importing cosmetics tested on animals after 12 November 2014 (Cosmetics Rules, 2020, Rules 39(7) and 18(4)).',
				'<strong>Severe, lasting pain:</strong> the 2026 draft would bar experiments with severe pain that is likely to last long and cannot be relieved (Rule 7(2)).',
			)
		)
		. c2e_h( '5. Non-animal methods for drugs' )
		. c2e_p( 'Since March 2023 the New Drugs and Clinical Trials Rules list cell-based assays, organ chips, computer modelling and other human biology-based methods alongside animal studies. ' . c2e_a( $r5, 'Alternatives to animal testing used in India' ) . '.' )
		. c2e_h( '6. Wild and protected animals' )
		. c2e_p( 'Under the 2026 draft, species protected under the Wild Life (Protection) Act can be used only for listed purposes, with justification and permission, and stray and feral animals of domestic species "shall not be used" except under exemptions from "competent authorities" that the draft does not define (Rules 5 and 6). ' . c2e_a( $n1, 'Strays, wild animals and primates in the draft' ) . '.' )
		. c2e_h( '7. The 2026 draft rules' )
		. c2e_p( 'G.S.R. 864(E), published on 6 October 2026, would replace the 1998 Rules. Its biggest change: a lab\'s own IAEC would approve all mild and moderate experiments on every species, and CCSEA would see only severe and non-recovery ones on animals above rodents and rabbits. Objections close on 5 November 2026.' )
		. c2e_ul(
			array(
				c2e_a( $draft, 'The draft rules in full' ) . ' (Library)',
				c2e_a( $n1, 'What the draft says, rule by rule' ),
				c2e_a( $n2, 'Who approves an animal experiment under the draft' ),
				c2e_a( $n3, 'Today\'s rules and the draft, side by side' ),
				c2e_a( $r2, 'Mild, moderate, severe: how experiments are classified' ),
				c2e_a( $r1, 'How to object to a draft rule' ),
				c2e_a( $camp, 'Our campaign: send an objection' ),
			)
		)
		. c2e_h( '8. The law in practice' )
		. c2e_p( 'The case of Palamur Biosciences in Telangana shows how these rules are enforced: an inspection report, a police FIR, CCSEA\'s public notice and two petitions in the Delhi High Court. ' . c2e_a( $n4, 'The Palamur beagles case: a timeline' ) . '.' )
		. c2e_faq(
			array(
				array( 'Is animal testing legal in India?', 'Yes. Section 14 of the Prevention of Cruelty to Animals Act, 1960 allows experiments for medical and scientific knowledge, subject to the rules made by CCSEA. Testing cosmetics on animals is banned.' ),
				array( 'Who regulates animal experiments in India?', 'The Committee for Control and Supervision of Experiments on Animals (CCSEA), formerly CPCSEA, together with each establishment\'s Institutional Animals Ethics Committee.' ),
				array( 'What is the penalty for breaking the rules on animal experiments?', 'A fine of up to ₹200 under Section 20 of the PCA Act. CCSEA can also bar an establishment from experimenting, or suspend or cancel its registration.' ),
				array( 'What are the 2026 draft rules?', 'Draft Laboratory Animals Rules, G.S.R. 864(E), published on 6 October 2026 to replace the 1998 Rules. Objections can be sent to CCSEA until 5 November 2026.' ),
			)
		)
		. $cta . $disc
		. c2e_src( array( $s_comp, $s_gsr864, $s_cos2020, $s_gsr175, $s_ccsea, $s_rename ) ),
);

$tag = get_term_by( 'slug', 'campaign-lab-animals-2026', 'post_tag' );
foreach ( $docs as $d ) {
	$live = get_posts( array( 'name' => $d['slug'], 'post_type' => $d['type'], 'post_status' => array( 'publish', 'pending', 'future', 'private' ), 'numberposts' => 1 ) );
	if ( $live ) {
		WP_CLI::log( 'SKIP not a draft: ' . $d['slug'] );
		continue;
	}
	$existing = get_posts( array( 'name' => $d['slug'], 'post_type' => $d['type'], 'post_status' => 'draft', 'numberposts' => 1 ) );
	$arr      = array( 'post_type' => $d['type'], 'post_status' => 'draft', 'post_name' => $d['slug'], 'post_title' => $d['title'], 'post_excerpt' => $d['desc'], 'post_content' => $d['content'] );
	if ( $existing ) {
		$arr['ID'] = $existing[0]->ID;
	}
	$id = wp_insert_post( wp_slash( $arr ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $d['slug'] . ': ' . $id->get_error_message() );
		continue;
	}
	wp_set_object_terms( $id, $d['cats'], 'category' );
	wp_set_object_terms( $id, array_filter( array( (int) $tag->term_id, 'post' === $d['type'] ? 'guide' : null ) ), 'post_tag' );
	foreach ( array( 'rank_math_title' => $d['rm_title'], 'rank_math_description' => $d['desc'], 'rank_math_focus_keyword' => $d['focus'] ) as $k => $v ) {
		update_post_meta( $id, $k, wp_slash( $v ) );
	}
	update_post_meta( $id, 'rank_math_primary_category', get_term_by( 'slug', $d['cats'][0], 'category' )->term_id );
	if ( 'aw_news' === $d['type'] ) {
		update_post_meta( $id, 'aw_source_name', 'Animal Writes' );
		update_post_meta( $id, 'aw_source_url', $camp );
		update_post_meta( $id, 'aw_source_date', '2026-10-16' );
	}
	update_post_meta( $id, '_aw_owner_draft', '2026-10-16 Campaign 2.0 set 2; facts checked against primary sources and news reports' );
	WP_CLI::log( sprintf( '%d | %s | %s | t=%d d=%d', $id, $d['type'], $d['title'], mb_strlen( $d['rm_title'] ), mb_strlen( $d['desc'] ) ) );
}
