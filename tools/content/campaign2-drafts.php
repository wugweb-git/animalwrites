<?php
/**
 * Campaign 2.0 News and Resources DRAFTS (16 Oct 2026). Never publishes; re-running updates the same drafts (by slug)
 * and skips any that are no longer drafts. Facts only from texts checked on 16 Oct 2026: G.S.R. 864(E) (draft rules),
 * the 1998 Rules with the 2001 and 2006 amendments, the PCA Act s.20 and the CPCSEA SOP (CPCSEA Compendium), all from
 * ccsea.gov.in. News N4 (Palamur) and Resources R4-R6 need outside facts that are not checked yet: not made here.
 * Run: wp eval-file campaign2-drafts.php
 */
function c2d_p( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function c2d_h( $t, $l = 2 ) {
	$a = 2 === $l ? '' : ' {"level":' . $l . '}';
	return "<!-- wp:heading$a -->\n<h$l class=\"wp-block-heading\">$t</h$l>\n<!-- /wp:heading -->\n\n";
}
function c2d_ul( $items, $ordered = false ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	$t = $ordered ? 'ol' : 'ul';
	$a = $ordered ? ' {"ordered":true}' : '';
	return "<!-- wp:list$a -->\n<$t class=\"wp-block-list\">$li</$t>\n<!-- /wp:list -->\n\n";
}
function c2d_table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function c2d_faq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return c2d_h( 'Questions and answers' ) . '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function c2d_a( $url, $t ) { return '<a href="' . esc_url( $url ) . '">' . $t . '</a>'; }

$camp  = home_url( '/campaigns/lab-animals-rules-2026/' );
$draft = home_url( '/library/draft-laboratory-animals-rules-2026/' );
$r1998 = home_url( '/library/breeding-experiments-animals-rules-1998/' );
$act   = get_permalink( 117 );
$mark  = c2d_p( '<strong>DRAFT for owner approval. Facts checked 16 Oct 2026 against G.S.R. 864(E), the 1998 Rules (with the 2001 and 2006 amendments) and the PCA Act, as published by CCSEA.</strong>' );
$cta   = c2d_p( 'Objections can be sent to CCSEA until 5 November 2026. ' . c2d_a( $camp . '#send', 'Write and send yours in two minutes' ) . '.' );
$src   = c2d_h( 'Sources' ) . c2d_ul(
	array(
		c2d_a( 'https://ccsea.gov.in/WriteReadData/LnPdf/DraftPreventionofCrueltytoAnimals(ControlandSupervisionofExperiment,BreedingandTradingofLaboratoryAnimals)Rules,2026.pdf', 'CCSEA: Draft Laboratory Animals Rules, 2026, G.S.R. 864(E)' ) . ' (Gazette of India, Extraordinary, No. 793, 6 October 2026)',
		c2d_a( 'https://ccsea.gov.in/WriteReadData/userfiles/file/Compendium%20of%20CPCSEA.pdf', 'CCSEA: Compendium of CPCSEA' ) . ' (1998 Rules, 2001 and 2006 amendments, PCA Act Chapter IV, standard operating procedures)',
	)
);

$docs = array();

/* N1 */
$docs[] = array(
	'type'     => 'aw_news',
	'slug'     => 'draft-lab-animal-rules-2026-rule-by-rule',
	'title'    => 'What India\'s draft lab animal rules say, rule by rule',
	'rm_title' => 'Draft Lab Animal Rules 2026, Rule by Rule – Animal Writes',
	'desc'     => 'The draft rules on animal experiments, G.S.R. 864(E), explained rule by rule: who approves experiments, where animals come from, strays, primates and gaps.',
	'focus'    => 'draft lab animal rules 2026,ccsea draft rules,g.s.r. 864(e)',
	'cats'     => array( 'laws-rules', 'policy-government' ),
	'content'  => $mark
		. c2d_p( 'On 6 October 2026 the Gazette of India published draft rules, G.S.R. 864(E), to replace the 1968 and 1998 rules on experiments on animals. The Committee for Control and Supervision of Experiments on Animals (CCSEA) invites objections and suggestions within thirty days. This is what the main rules say.' )
		. c2d_h( 'Rule 11: who approves an experiment' )
		. c2d_p( 'Rule 11(3) says experiments classed as mild or moderate, "including such experiments on all animals", go to the establishment\'s own Institutional Animals Ethics Committee (IAEC) for decision, and "shall not be referred to the Committee". Rule 11(2) sends only severe and non-recovery experiments on animals above rodents and rabbits to CCSEA, which must decide within thirty working days.' )
		. c2d_p( 'For mild and moderate experiments on animals above rabbits, Rule 11(4) makes the IAEC report to CCSEA at three stages: start, interim progress and completion. The draft does not say who approves severe experiments on rodents and rabbits.' )
		. c2d_h( 'Rule 21(2): where animals come from' )
		. c2d_p( 'Animals must come from CCSEA-registered breeders or traders (Rule 21(1)). If a kind of livestock or farm animal above rabbits, or fish or birds, is not available from them, Rule 21(2) allows buying from "non-registered establishments or open market" with the IAEC\'s prior approval, records and a quarantine set by CCSEA guidelines.' )
		. c2d_h( 'Rule 5: strays, wild animals and protected species' )
		. c2d_p( 'Stray and feral animals of domestic species "shall not be used", but "the competent authorities may grant exemptions" where there is an essential need and scientific justification (Rule 5(3)). The draft does not define "competent authority". Wild-caught animals need an exemption from the relevant department (Rule 5(2)). Species protected under the Wildlife (Protection) Amendment Act, 2022 can be used only for listed purposes, with justification and permission (Rule 5(1)).' )
		. c2d_h( 'Rule 6: primates' )
		. c2d_p( 'Primates can be used for serious human disease, basic research or preserving the species, where no other species will do (Rule 6(1)). Primates listed in the Wildlife (Protection) Amendment Act, 2022 "shall not be used" (Rule 6(2)). Rule 5(1)(c) says the protected-species rule "shall not apply to any species of non-human primates", which leaves the position unclear.' )
		. c2d_h( 'Rule 2(i): what counts as a lab' )
		. c2d_p( 'An "establishment" includes "a place that is not wholly enclosed or covered and mobile facilities".' )
		. c2d_h( 'Rule 7 and Annexure I: the pain scale' )
		. c2d_p( 'Each experiment is classed as non-recovery, mild, moderate or severe. Annexure I lists food withdrawal for under 24 hours in adult rats and short-term solitary caging of sociable rats and mice as mild, and 48-hour food withdrawal and stimuli the animal cannot escape as moderate. Rule 7(2) bars experiments with severe pain "likely to be long period" that "cannot be ameliorated".' )
		. c2d_h( 'Rules 4(6) and 11(11): teaching' )
		. c2d_p( 'Higher education and professional training are permitted purposes. An IAEC can approve repetitive or curriculum-based teaching for up to five years.' )
		. c2d_h( 'Rule 25: penalties' )
		. c2d_p( 'CCSEA can suspend or revoke an establishment\'s registration after a deficiency notice. The fine in Section 20 of the Prevention of Cruelty to Animals Act stays at up to ₹200; rules cannot change it.' )
		. $cta . c2d_p( c2d_a( $draft, 'Read the full draft in our Library' ) . '.' ) . $src,
);

/* N2 */
$docs[] = array(
	'type'     => 'aw_news',
	'slug'     => 'who-approves-animal-experiments-draft-rules',
	'title'    => 'Who approves an animal experiment? The draft rules\' answer',
	'rm_title' => 'Who Approves Animal Experiments in India? – Animal Writes',
	'desc'     => 'Today CCSEA approves experiments on large animals. Under the 2026 draft, labs\' own committees would approve all mild and moderate experiments on every species.',
	'focus'    => 'who approves animal experiments,iaec approval,ccsea approval',
	'cats'     => array( 'laws-rules', 'policy-government' ),
	'content'  => $mark
		. c2d_p( 'Every registered establishment needs permission before an experiment, from either CCSEA or its own Institutional Animals Ethics Committee (IAEC). Which of the two decides is the biggest change in the 2026 draft rules.' )
		. c2d_h( 'Today' )
		. c2d_p( 'The 1998 Rules say permission comes from the Committee or the IAEC "as the case may be" (Rule 8). CPCSEA\'s standard operating procedures set the split: the IAEC approves experiments on small animals, and "for carrying out the experiment on large animals, approval of CPCSEA is a must"; the IAEC may only recommend.' )
		. c2d_h( 'Under the draft' )
		. c2d_p( 'The split moves from the size of the animal to the expected pain. Mild and moderate experiments on all animals go to the IAEC and "shall not be referred to the Committee" (Rule 11(3)). CCSEA decides only severe and non-recovery experiments on animals above rodents and rabbits (Rule 11(2)). For mild and moderate experiments on animals above rabbits, the IAEC reports to CCSEA at three stages (Rule 11(4)).' )
		. c2d_table(
			array( 'Experiment', 'Today', 'Draft 2026' ),
			array(
				array( 'Mild or moderate, rats and mice', 'IAEC', 'IAEC' ),
				array( 'Mild or moderate, dogs, monkeys and other large animals', 'CCSEA (CPCSEA procedures)', 'IAEC, with reports to CCSEA' ),
				array( 'Severe or non-recovery, large animals', 'CCSEA', 'CCSEA' ),
				array( 'Severe, rodents and rabbits', 'IAEC', 'Not stated' ),
			)
		)
		. c2d_h( 'Who sits on an IAEC under the draft' )
		. c2d_p( 'Nine members (Rule 16): a biologist, two scientists from different disciplines, a veterinarian, the scientist in charge of the animal facility, and four CCSEA nominees: a main nominee, a link nominee, an outside scientist and a socially aware nominee. The chairperson and member secretary come from the establishment\'s own members. A meeting needs six members, including the main nominee and the socially aware nominee (Rule 17(2)).' )
		. $cta . $src,
);

/* N3 */
$docs[] = array(
	'type'     => 'aw_news',
	'slug'     => 'lab-animal-rules-today-vs-draft-2026',
	'title'    => 'Lab animal rules today vs the 2026 draft, side by side',
	'rm_title' => 'Lab Animal Rules Today vs the 2026 Draft – Animal Writes',
	'desc'     => 'How the 2026 draft rules on animal experiments compare with the 1998 Rules in force: approvals, sourcing of animals, anaesthesia, labs and penalties.',
	'focus'    => 'animal testing rules india 2026,1998 rules vs draft,lab animal rules comparison',
	'cats'     => array( 'laws-rules', 'policy-government' ),
	'content'  => $mark
		. c2d_p( 'The draft Laboratory Animals Rules, 2026 would replace the Breeding of and Experiments on Animals (Control and Supervision) Rules, 1998, as amended in 2001 and 2006. These are the main differences.' )
		. c2d_table(
			array( '', 'Today', 'Draft 2026' ),
			array(
				array( 'Who approves', 'IAEC for small animals; CCSEA for large animals (CPCSEA procedures)', 'IAEC for mild and moderate experiments on every species (Rule 11(3))' ),
				array( 'Where animals come from', 'Registered breeders; other legal sources only with written permission from a competent authority (2006 amendment, Rule 10(b))', 'Unregistered sources or the open market for some species, with the IAEC\'s approval (Rule 21(2))' ),
				array( 'Anaesthesia', 'Required for any procedure more severe than simple inoculation or superficial venesection (1998 Rules, Rule 9(d))', 'No such rule' ),
				array( 'Aftercare', 'Investigators responsible for aftercare and rehabilitation, costed into research (2006 amendment)', 'Left to CCSEA guidelines (Rule 15(1))' ),
				array( 'What counts as a lab', 'Animal house facility', 'Includes places not wholly enclosed, and mobile facilities (Rule 2(i))' ),
				array( 'How experiments are sorted', 'Size of the animal', 'Expected pain: non-recovery, mild, moderate, severe (Rule 7, Annexure I)' ),
				array( 'Penalty', 'Fine up to ₹200 (PCA Act, s.20); suspension or revocation of registration', 'Fine unchanged; suspension or revocation after a deficiency notice (Rule 25)' ),
			)
		)
		. c2d_p( c2d_a( $r1998, 'Read the 1998 Rules and their amendments' ) . ' · ' . c2d_a( $draft, 'Read the 2026 draft' ) . '.' )
		. $cta . $src,
);

/* R1 */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'how-to-object-to-a-draft-rule-india',
	'title'    => 'How to object to a draft rule in India',
	'rm_title' => 'How to Send an Objection to a Draft Rule – Animal Writes',
	'desc'     => 'How public objections to draft rules work in India: where drafts are published, the 30-day window, who to write to, what to include and what happens next.',
	'focus'    => 'how to send objection to draft rules,objection to draft notification,draft rules india',
	'cats'     => array( 'laws-rules', 'campaigns-civic-action' ),
	'content'  => $mark
		. c2d_p( 'Many rules made under an Act are first published as a draft, so that anyone can send objections and suggestions before they are final. The draft notification says how long the window is and where to write.' )
		. c2d_h( 'How to send an objection' )
		. c2d_ul(
			array(
				'<strong>Find the draft.</strong> Drafts appear in the Gazette of India (egazette.gov.in) and usually on the ministry or authority\'s website. Note its number, such as G.S.R. 864(E), and its date.',
				'<strong>Check the window.</strong> The notification states the period, often thirty days. Send yours well before the last day.',
				'<strong>Find the address.</strong> The notification names the officer, postal address and email for objections.',
				'<strong>Write it.</strong> Give the draft\'s name and number in the subject. List each objection with its rule number and what you want changed. Add your name and city, and ask for an acknowledgment.',
				'<strong>Send it.</strong> By email to the address given, or by post in time to arrive before the deadline. Keep a copy.',
			),
			true
		)
		. c2d_h( 'What makes an objection useful' )
		. c2d_p( 'Name the exact rule and say what should change. A line in your own words helps; identical copies are easier to set aside.' )
		. c2d_h( 'What happens next' )
		. c2d_p( 'The authority considers the objections and suggestions received in the window, then publishes final rules in the Gazette, changed or not.' )
		. c2d_h( 'Example: the draft lab animal rules, 2026' )
		. c2d_p( 'G.S.R. 864(E), published on 6 October 2026, invites objections to CCSEA at cpcsea-mef@gov.in, or Member Secretary (CCSEA), Office of CCSEA, Near State Bank of India, Delhi Milk Scheme Complex, Shadipur, Delhi – 110008. ' . c2d_a( $camp, 'Our campaign page' ) . ' builds the letter for you.' )
		. c2d_faq(
			array(
				array( 'Who can object to a draft rule?', 'Anyone, unless the notification says otherwise. Drafts invite objections and suggestions from the public.' ),
				array( 'Does an objection have to be in a set format?', 'Usually not. Give the draft\'s name and number, your objections with rule numbers, your name and city, and send it to the address in the notification within the time given.' ),
			)
		)
		. "<!-- wp:paragraph {\"className\":\"aw-guide-disclaimer\"} -->\n<p class=\"aw-guide-disclaimer\">This guide is general information, not legal advice.</p>\n<!-- /wp:paragraph -->\n\n",
);

/* R2 */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'animal-experiment-severity-mild-moderate-severe',
	'title'    => 'Mild, moderate, severe: how the draft rules classify animal experiments',
	'rm_title' => 'Animal Experiment Severity: Mild to Severe – Animal Writes',
	'desc'     => 'The four severity categories in India\'s draft lab animal rules, with the draft\'s own examples, and why the category decides who approves an experiment.',
	'focus'    => 'animal experiment severity classification,mild moderate severe experiments,annexure i',
	'cats'     => array( 'laws-rules' ),
	'content'  => $mark
		. c2d_p( 'The 2026 draft rules sort every animal experiment by the pain, suffering, distress or lasting harm it is expected to cause (Rule 7, Annexure I). The category then decides who approves it.' )
		. c2d_h( 'The four categories' )
		. c2d_ul(
			array(
				'<strong>Non-recovery:</strong> done entirely under general anaesthesia from which the animal does not recover consciousness.',
				'<strong>Mild:</strong> short-term mild pain, suffering or distress, or no significant impairment of well-being.',
				'<strong>Moderate:</strong> short-term moderate, or long-lasting mild, pain, suffering or distress, or moderate impairment of well-being.',
				'<strong>Severe:</strong> severe pain, suffering or distress, long-lasting moderate pain, or severe impairment of well-being.',
			)
		)
		. c2d_h( 'Examples from the draft' )
		. c2d_table(
			array( 'Category', 'Examples in Annexure I' ),
			array(
				array( 'Mild', 'Withdrawal of food for under 24 hours in adult rats; short-term solitary caging of adult rats or mice of sociable strains' ),
				array( 'Moderate', 'Withdrawal of food for 48 hours in adult rats; escape and avoidance tests where the animal cannot escape the stimulus; inducing tumours that cause moderate pain' ),
				array( 'Severe', 'Toxicity testing where death is the endpoint; inescapable electric shock; complete isolation for long periods of social species such as dogs and primates' ),
			)
		)
		. c2d_h( 'Who approves each' )
		. c2d_p( 'Mild and moderate experiments on any species: the establishment\'s own ethics committee (Rule 11(3)). Severe and non-recovery experiments on animals above rodents and rabbits: CCSEA (Rule 11(2)). The draft does not say who approves severe experiments on rodents and rabbits.' )
		. c2d_p( 'Experiments with severe pain that is likely to last long and cannot be relieved are not allowed (Rule 7(2)).' )
		. $cta . $src,
);

/* R3 */
$docs[] = array(
	'type'     => 'post',
	'slug'     => 'iaec-full-form-members-approvals',
	'title'    => 'IAEC: who approves animal experiments in India',
	'rm_title' => 'IAEC Full Form, Members and What It Does – Animal Writes',
	'desc'     => 'IAEC stands for Institutional Animals Ethics Committee. Who sits on one, what it approves today and under the 2026 draft, and how its meetings work.',
	'focus'    => 'iaec full form,institutional animal ethics committee,iaec members',
	'cats'     => array( 'laws-rules' ),
	'content'  => $mark
		. c2d_p( 'IAEC stands for Institutional Animals Ethics Committee. Every establishment registered with CCSEA to use animals in experiments has one. It reviews and approves experiments, and CCSEA places its own nominees on it.' )
		. c2d_h( 'Members' )
		. c2d_p( '<strong>Today (1998 Rules, Rule 13):</strong> a biological scientist, two scientists from different biological disciplines, a veterinarian, the scientist in charge of the animal facility, a scientist from outside the institute and a non-scientific socially aware member, with a specialist co-opted for projects using hazardous agents.' )
		. c2d_p( '<strong>Under the 2026 draft (Rule 16):</strong> nine members, valid for three years. Five are from the establishment (a biologist, two scientists, a veterinarian and the scientist in charge of the facility) and four are CCSEA nominees: a main nominee, a link nominee, an outside scientist and a socially aware nominee. The chairperson and member secretary are chosen from the establishment\'s own members.' )
		. c2d_h( 'Meetings' )
		. c2d_p( 'Under the draft, an IAEC meets at least twice a year, once in each half. A meeting needs six members, including the main nominee and the socially aware nominee; decisions without that quorum are invalid. Minutes go to CCSEA within fifteen days (Rule 17).' )
		. c2d_h( 'What it approves' )
		. c2d_p( 'Today, the IAEC approves experiments on small animals; experiments on large animals need CCSEA\'s approval, on the IAEC\'s recommendation (CPCSEA procedures). Under the draft, the IAEC alone approves all mild and moderate experiments on every species (Rule 11(3)), and can approve teaching use for up to five years (Rule 11(11)).' )
		. c2d_faq(
			array(
				array( 'What is the full form of IAEC?', 'Institutional Animals Ethics Committee.' ),
				array( 'How many members does an IAEC have?', 'Under the 2026 draft rules, nine: five from the establishment and four nominated by CCSEA.' ),
			)
		)
		. $cta . $src,
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
	update_post_meta( $id, '_aw_owner_draft', '2026-10-16 Campaign 2.0; facts checked against CCSEA texts' );
	WP_CLI::log( sprintf( '%d | %s | %s | t=%d d=%d', $id, $d['type'], $d['title'], mb_strlen( $d['rm_title'] ), mb_strlen( $d['desc'] ) ) );
}
