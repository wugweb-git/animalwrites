<?php
/**
 * Campaign 2.0 page (draft lab animal rules 2026), 16 Oct 2026. Creates the campaign as a DRAFT; re-running updates the
 * same item (by slug) and never publishes it. Copy: Claude outputs/campaign2/campaign2-copy.md (letter grounds checked
 * against G.S.R. 864(E), the 1998 Rules with the 2001 and 2006 amendments, and the PCA Act). No supporters section
 * (owner's rule). Run: wp eval-file campaign2-page.php
 */

$doc = get_page_by_path( 'draft-laboratory-animals-rules-2026', OBJECT, 'aw_legal' );
if ( ! $doc ) {
	WP_CLI::error( 'Library item draft-laboratory-animals-rules-2026 not found (run campaign2-library.php first)' );
}
$lib_draft = home_url( '/library/draft-laboratory-animals-rules-2026/' );
$lib_1998  = home_url( '/library/breeding-experiments-animals-rules-1998/' );
$lib_act   = get_permalink( 117 );

function c2p( $t, $cls = '' ) {
	$a = $cls ? ' {"className":"' . $cls . '"}' : '';
	$c = $cls ? ' class="' . $cls . '"' : '';
	return "<!-- wp:paragraph$a -->\n<p$c>$t</p>\n<!-- /wp:paragraph -->\n\n";
}
function c2h( $t, $level = 2, $anchor = '' ) {
	$attrs = array();
	if ( 2 !== $level ) {
		$attrs['level'] = $level;
	}
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	$a  = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	return "<!-- wp:heading$a -->\n<h$level class=\"wp-block-heading\"$id>$t</h$level>\n<!-- /wp:heading -->\n\n";
}
function c2sc( $sc ) {
	return "<!-- wp:shortcode -->\n$sc\n<!-- /wp:shortcode -->\n\n";
}
function c2list( $items, $ordered = false, $cls = '' ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	$tag   = $ordered ? 'ol' : 'ul';
	$attrs = array_filter( array( 'ordered' => $ordered ? true : null, 'className' => $cls ? $cls : null ) );
	$a     = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
	$c     = 'wp-block-list' . ( $cls ? ' ' . $cls : '' );
	return "<!-- wp:list$a -->\n<$tag class=\"$c\">$li</$tag>\n<!-- /wp:list -->\n\n";
}
/** A full-width section with an anchor; $cls adds the band style (aw-camp-hero, aw-camp-paper, aw-camp-dark). */
function c2sec( $anchor, $inner, $cls = '' ) {
	$classes = trim( 'aw-camp-section ' . $cls );
	$attrs   = array(
		'align'     => 'full',
		'className' => $classes,
		'style'     => array( 'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|60', 'bottom' => 'var:preset|spacing|60', 'left' => 'var:preset|spacing|40', 'right' => 'var:preset|spacing|40' ) ) ),
		'layout'    => array( 'type' => 'constrained', 'contentSize' => '900px' ),
	);
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<div$id class=\"wp-block-group alignfull $classes\" style=\"padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function c2grid( $cards ) {
	$inner = '';
	foreach ( $cards as $c ) {
		$inner .= "<!-- wp:group {\"className\":\"aw-card\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group aw-card\">$c</div>\n<!-- /wp:group -->\n\n";
	}
	return "<!-- wp:group {\"layout\":{\"type\":\"grid\",\"minimumColumnWidth\":\"16rem\"}} -->\n<div class=\"wp-block-group\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function c2buttons( $btns ) {
	$b = '';
	foreach ( $btns as $x ) {
		$b .= "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$x[1]}\">{$x[0]}</a></div>\n<!-- /wp:button -->\n";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">$b</div>\n<!-- /wp:buttons -->\n\n";
}
function c2table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function c2faq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function chip( $rule, $label, $allows = '' ) {
	return '[aw_rule_chip rule="' . $rule . '" label="' . $label . '"' . ( $allows ? ' allows="' . $allows . '"' : '' ) . ']';
}

/* 1. Hero (open and closed versions; one shows). */
$hero = c2sec(
	'top',
	c2sc( '[aw_countdown date="2026-11-05" label="Objections close"]' )
	. c2h( 'Labs could soon approve their own animal experiments.', 1 )
	. c2p( 'India has published draft rules that move most approvals for animal experiments from the national committee to each lab\'s own committee. Read what the rules say, then send your objection in two minutes.', 'aw-camp-hero-summary' )
	. c2buttons( array( array( 'Send your objection', '#send' ) ) )
	. c2p( '<a href="#rules">Read what the rules say</a>' )
	. c2p( chip( '11-3', 'Rule 11(3)' ) . ' ' . chip( '21-2', 'Rule 21(2)' ) . ' ' . chip( '5-3', 'Rule 5(3)' ) . ' ' . chip( '2-i', 'Rule 2(i)' ) ),
	'aw-camp-hero aw-camp-open'
);
$hero_closed = c2sec(
	'',
	c2h( 'Objections to the draft lab animal rules have closed.', 1 )
	. c2p( 'The public could send objections to CCSEA until 5 November 2026. CCSEA now reviews them before publishing final rules in the Gazette. <a href="#next">What happens next</a>.' ),
	'aw-camp-hero aw-camp-closed'
);

/* 2. What's happening */
$what = c2sec(
	'what',
	c2h( 'What\'s happening' )
	. c2p( 'On 6 October 2026 the Gazette of India published draft rules (G.S.R. 864(E)) to replace the 1968 and 1998 rules on experiments on animals in India. The public has 30 days to send objections to the Committee for Control and Supervision of Experiments on Animals (CCSEA).' )
	. c2list( array( '<strong>30 September 2026:</strong> notification signed', '<strong>6 October 2026:</strong> published in the Gazette', '<strong>5 November 2026:</strong> last day for objections' ), true )
	. c2p( '<a href="' . $lib_draft . '">Read the full draft in our Library</a>' ),
	'aw-camp-paper'
);

/* 3. What the draft does */
$cards = array(
	c2h( 'Labs approve most experiments themselves', 3 ) . c2p( 'Mild and moderate experiments on any species, including dogs and monkeys, would be approved by the lab\'s own ethics committee and not sent to CCSEA. CCSEA only receives reports for animals larger than rabbits.' ) . c2p( chip( '11-3', 'Rule 11(3)', 'The lab committee alone approves mild and moderate experiments on every species.' ) . ' ' . chip( '11-4', 'Rule 11(4)', 'For animals larger than rabbits, the committee reports to CCSEA at three stages; CCSEA does not approve.' ) ),
	c2h( 'Some animals could come from open markets', 3 ) . c2p( 'If registered breeders can\'t supply them, livestock, farm animals, fish and birds could be bought from unregistered sources or the open market, with only the lab committee\'s approval.' ) . c2p( chip( '21-2', 'Rule 21(2)', 'Buying from unregistered sources or the open market with the lab committee\'s approval and a quarantine.' ) ),
	c2h( 'Strays: banned, with exemptions', 3 ) . c2p( 'Stray and feral animals are not to be used, but "competent authorities", never defined in the draft, can grant exemptions. In 2022 the regulator told labs stray dogs may be used in vaccine trials, and withdrew that in 2023.' ) . c2p( chip( '5-3', 'Rule 5(3)', 'Exemptions for studies on animal health, the environment or human or animal health, when only strays will do.' ) ),
	c2h( 'Wildlife and monkeys: allowed under conditions', 3 ) . c2p( 'Protected species and primates can be used for listed purposes with justification and permission. The draft is unclear on primates.' ) . c2p( chip( '5-1-c', 'Rule 5(1)(c)' ) . ' ' . chip( '6-2', 'Rule 6(2)' ) ),
	c2h( 'A lab doesn\'t need walls', 3 ) . c2p( 'Places that are not wholly enclosed, and mobile facilities, count as labs.' ) . c2p( chip( '2-i', 'Rule 2(i)' ) ),
	c2h( 'Training is a reason to use animals', 3 ) . c2p( 'Higher education and professional training are listed purposes, and teaching approvals can last five years.' ) . c2p( chip( '4-6', 'Rule 4(6)' ) . ' ' . chip( '11-11', 'Rule 11(11)' ) ),
);
$rules = c2sec( 'rules', c2h( 'What the draft does' ) . c2grid( $cards ) );

/* 4. Pain scale */
$pain = c2sec(
	'pain-scale',
	c2h( 'The pain scale decides who says yes' )
	. c2p( 'Every experiment would be labelled by how much pain it is expected to cause. The label decides whether anyone outside the lab reviews it.' )
	. c2p( '<strong>Mild: approved by the lab\'s committee.</strong> Examples in the draft: keeping sociable rats or mice alone for a short time; withholding food from adult rats for under 24 hours.' )
	. c2p( '<strong>Moderate: approved by the lab\'s committee.</strong> Examples: withholding food from adult rats for 48 hours; stimuli the animal cannot escape or avoid; inducing tumours.' )
	. c2p( '<strong>Severe: CCSEA approval for animals larger than rodents and rabbits.</strong> Examples: toxicity tests where death is the endpoint; inescapable electric shock; long isolation of dogs and monkeys.', 'aw-camp-severe' )
	. c2p( '<strong>Non-recovery: CCSEA approval for animals larger than rodents and rabbits.</strong> Done fully under anaesthesia; the animal does not wake up.' )
	. c2p( 'The draft does not say who approves severe experiments on rats, mice or rabbits. ' . chip( '11-2', 'Rule 11(2)' ) . ' ' . chip( 'annexure-1', 'Annexure I' ) ),
	'aw-camp-dark'
);

/* 5. Today vs the draft */
$compare = c2sec(
	'compare',
	c2h( 'Today vs the draft' )
	. c2table(
		array( '', 'Today', 'Draft 2026' ),
		array(
			array( 'Who approves', 'Lab committee for small animals; CCSEA for large animals (CPCSEA procedures)', 'Lab committee for mild and moderate experiments on every species' ),
			array( 'Where animals come from', 'Registered breeders; other legal sources only with written permission from a competent authority (2006 amendment)', 'Unregistered sources or the open market for some species, with the lab committee\'s approval' ),
			array( 'Anaesthesia', 'Required for anything more severe than a simple injection (1998 Rules, Rule 9(d))', 'No such rule' ),
			array( 'What counts as a lab', 'Animal house facility', 'Includes unenclosed and mobile facilities' ),
			array( 'How it\'s judged', 'Size of the animal', 'Expected pain' ),
			array( 'Penalty in the Act', 'Up to ₹200', 'Up to ₹200 (Act unchanged); the draft adds suspension or revocation of registration' ),
		)
	)
	. c2p( '<a href="' . $lib_1998 . '">Read the 1998 Rules and their amendments</a> · <a href="' . $lib_act . '">Read the Prevention of Cruelty to Animals Act, 1960</a>' ),
	'aw-camp-paper'
);

/* 6. Send your objection */
$grounds = array(
	'<strong>Rule 11(3):</strong> Approval of mild and moderate experiments on every species should not rest with the establishment\'s own committee alone. Rule 11(4) only makes that committee report to CCSEA, and a report is not an approval. Experiments on animals larger than rodents and rabbits should keep needing CCSEA approval, as CPCSEA\'s procedures require today.',
	'<strong>Rule 21(2):</strong> Rule 21(2) would let establishments buy livestock, farm animals, fish and birds from unregistered sources or the open market with only their own committee\'s approval. Today, any source other than a registered breeder needs written permission from a competent legal authority (2006 amendment). Open-market sourcing should be removed, and any other source should keep needing outside permission.',
	'<strong>Rule 5(3):</strong> Stray and feral animals should be excluded without exception. "Competent authority" is not defined anywhere in the draft.',
	'<strong>Rules 5(1)(c) and 6:</strong> Rule 5(1)(c) is unclear and appears to conflict with Rule 6(2) on non-human primates. Primates protected under the Wildlife (Protection) Act must be excluded without exception.',
	'<strong>Rule 2(i):</strong> An "establishment" should not include places that are not wholly enclosed, or mobile facilities, given the risks to biosafety and animal welfare.',
	'<strong>Annexure I:</strong> Withholding food, short-term solitary caging of sociable animals, and stimuli the animal cannot escape should not be classed as "mild" or "moderate".',
	'<strong>Rule 11:</strong> The draft does not say who approves severe experiments on rodents and rabbits. This gap must be closed by requiring CCSEA approval.',
	'<strong>Rules 4(6) and 11(11):</strong> Animals should not be used for education and training where non-animal methods exist, and five-year teaching approvals should be removed.',
	'<strong>Rule 3:</strong> Replacement should be mandatory, not required only "wherever possible", with a public list of validated non-animal methods that establishments must check before applying.',
	'<strong>Penalties:</strong> CCSEA should ask the Ministry to amend Section 20 of the Act, which still sets the fine at up to ₹200.',
	'<strong>Rule 15(1), rehabilitation:</strong> The draft leaves rehabilitation and reuse to future CCSEA guidelines. The 2006 amendment\'s duty of aftercare and rehabilitation, with its cost built into research budgets, should be written into the rules.',
	'<strong>Anaesthesia:</strong> The draft drops Rule 9(d) of the 1998 Rules, which requires anaesthesia for any procedure more severe than a simple injection or superficial venesection. That duty should be restored.',
	'<strong>Reuse:</strong> The draft allows an animal to be reused (Rule 15(1), Annexure I). An animal that has been through a moderate or severe experiment should not be used again.',
	'<strong>Rule 5(3), strays in practice:</strong> In September 2022 CPCSEA told labs that stray dogs may be used for new vaccine trials and challenge studies, and withdrew that only "till further orders" in October 2023. Rule 5(3)\'s exemption would allow the same again, and should be deleted.',
);
$letter_sc = '[aw_letter_tool to="cpcsea-mef@gov.in" subject="Objection to the Draft Laboratory Animals Rules, 2026 (G.S.R. 864(E))" ticked="1,2,6"'
	. ' address="The Member Secretary, Committee for Control and Supervision of Experiments on Animals (CCSEA), Shadipur, Delhi – 110008"'
	. ' intro="I submit the following objections to the draft Prevention of Cruelty to Animals (Control and Supervision of Experiment, Breeding and Trading of Laboratory Animals) Rules, 2026, G.S.R. 864(E) dated 30 September 2026, published in the Gazette of India on 6 October 2026:"'
	. ' closing="I request that the draft be withdrawn and revised after wider consultation, and that CCSEA publish a clause-by-clause comparison with the 1998 Rules. Kindly acknowledge receipt of this objection."]';
$send = c2sec(
	'send',
	c2h( 'Send your objection' )
	. c2p( 'Choose your grounds, add your name and city, and send it from your own email. Letters with a name, a city and a line in your own words count as individual responses.' )
	. c2list( $grounds, true, 'aw-letter-grounds' )
	. c2sc( $letter_sc )
	. c2sc( '[aw_campaign_count min="100"]' )
	. c2p( 'We store nothing from this form. Your letter goes from your own email account.', 'has-small-font-size' ),
	'aw-camp-paper aw-camp-open'
);

/* 7. Other ways to send */
$how = c2sec(
	'how',
	c2h( 'Other ways to send' )
	. c2p( '<strong>Copy and paste.</strong> Copy the letter and the address, and paste them into any email app.' )
	. c2p( '<strong>By post.</strong> Member Secretary (CCSEA), Office of CCSEA, Near State Bank of India, Delhi Milk Scheme Complex, Shadipur, Delhi – 110008. Post it by 30 October so it arrives in time.' )
	. c2p( '<strong>Write your own.</strong> Use your own words. Mention G.S.R. 864(E), the rule numbers, your name and city, and ask for an acknowledgment.' ),
	'aw-camp-open'
);

/* 8. Questions */
$faq = c2sec(
	'faq',
	c2h( 'Questions' )
	. c2faq(
		array(
			array( 'Are these rules already in force?', 'No. They take effect only after CCSEA considers objections and publishes final rules in the Gazette.' ),
			array( 'Who can send an objection?', 'Anyone. The notification invites objections and suggestions within thirty days.' ),
			array( 'Does one email matter?', 'Every objection received in the window must be considered before the rules are finalised. Individual letters with names and cities carry more weight than identical copies.' ),
			array( 'Does the draft ban any experiments?', 'It bars experiments that cause severe pain, suffering or distress that is likely to last long and cannot be relieved (Rule 7(2)). Other severe experiments are allowed with approval.' ),
			array( 'Can community dogs be used?', 'The draft says strays should not be used, but allows exemptions by "competent authorities", a term it does not define (Rule 5(3)). In September 2022 the regulator told labs stray dogs may be used for new vaccine trials; it withdrew that in October 2023 "till further orders".' ),
			array( 'Is Animal Writes against medical research?', 'We ask for the oversight that exists today to stay, for non-animal methods to come first, and for clear limits on where animals come from.' ),
			array( 'What happens after 5 November?', 'CCSEA reviews objections and may revise the draft before final notification. We will publish updates here.' ),
			array( 'Is my data stored?', 'The letter is sent from your own email. We store nothing unless you choose to join our updates.' ),
		)
	)
);

/* 10. Read the law */
$read = c2sec(
	'read',
	c2h( 'Read the law' )
	. c2list(
		array(
			'<a href="' . $lib_draft . '">Draft Laboratory Animals Rules, 2026 (G.S.R. 864(E)), full text</a>',
			'<a href="' . $lib_act . '">Prevention of Cruelty to Animals Act, 1960 (Chapter IV covers experiments)</a>',
			'<a href="' . $lib_1998 . '">Breeding of and Experiments on Animals Rules, 1998, with the 2001 and 2006 amendments</a>',
		)
	),
	'aw-camp-paper'
);

/* 11. What happens next */
$next = c2sec(
	'next',
	c2h( 'What happens next' )
	. c2list( array( 'Objections close on 5 November 2026.', 'CCSEA reviews the objections and suggestions.', 'Final rules, revised or not, are published in the Gazette.', 'We follow every step here and in our newsletter.' ), true )
);

/* Latest updates: the three newest News items with the campaign tag. */
$tag     = get_term_by( 'slug', 'campaign-lab-animals-2026', 'post_tag' );
$updates = c2sec(
	'latest',
	c2h( 'Latest updates' )
	. '<!-- wp:query {"queryId":82,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false,"taxQuery":{"post_tag":[' . (int) $tag->term_id . ']}},"className":"kit-cards aw-cards-3"} -->'
	. "\n<div class=\"wp-block-query kit-cards aw-cards-3\"><!-- wp:post-template -->\n<!-- wp:post-title {\"level\":3,\"isLink\":true} /-->\n\n<!-- wp:post-date /-->\n<!-- /wp:post-template -->\n\n<!-- wp:query-no-results -->\n"
	. c2p( 'Updates will appear here.' ) . "<!-- /wp:query-no-results --></div>\n<!-- /wp:query -->\n\n"
);

/* 12. Stay in the loop */
$share_url = rawurlencode( home_url( '/campaigns/lab-animals-rules-2026/' ) );
$share_txt = rawurlencode( 'Object to India\'s draft lab animal rules before 5 November:' );
$loop      = c2sec(
	'updates',
	c2h( 'Get updates on these rules' )
	. c2p( 'We\'ll email you when CCSEA responds and when the final rules are published.' )
	. c2sc( '[aw_newsletter]' )
	. c2p( 'Share: <a href="https://wa.me/?text=' . $share_txt . '%20' . $share_url . '">WhatsApp</a> · <a href="https://twitter.com/intent/tweet?text=' . $share_txt . '&amp;url=' . $share_url . '">X</a> · <a href="https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url . '">LinkedIn</a>' )
	. c2p( 'Colours from the explainer artwork by @p.s___withlove, used with her permission.', 'has-small-font-size' ),
	'aw-camp-dark'
);

$content = '<!-- wp:group {"align":"full","className":"aw-camp aw-camp--lab-rules","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignfull aw-camp aw-camp--lab-rules">'
	. $hero . $hero_closed . $what . $rules . $pain . $compare . $send . $how . $faq . $read . $next . $updates . $loop
	. c2sc( '[aw_sticky_cta href="#send" label="Send your objection"]' )
	. "</div>\n<!-- /wp:group -->";

$existing = get_page_by_path( 'lab-animals-rules-2026', OBJECT, 'aw_campaign' );
$post     = array(
	'post_type'    => 'aw_campaign',
	'post_name'    => 'lab-animals-rules-2026',
	'post_title'   => 'Object to the draft lab animal rules 2026',
	'post_excerpt' => 'India\'s draft rules on animal experiments would let labs approve most experiments themselves. Read what the rules say and send your objection to CCSEA by 5 November 2026.',
	'post_content' => $content,
	'post_status'  => $existing ? $existing->post_status : 'draft',
);
if ( $existing ) {
	$post['ID'] = $existing->ID;
}
$id = wp_insert_post( wp_slash( $post ), true );
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
wp_set_object_terms( $id, array( (int) $tag->term_id ), 'post_tag', true );
wp_set_object_terms( $id, array( 'campaigns-civic-action', 'laws-rules' ), 'category', false );
foreach ( array(
	'_wp_page_template'          => 'campaign-page',
	'aw_campaign_close'          => '2026-11-05',
	'aw_campaign_doc'            => (int) $doc->ID,
	'rank_math_title'            => 'Object to the Draft Lab Animal Rules 2026 – Animal Writes',
	'rank_math_description'      => 'India\'s draft lab animal rules would let labs approve most animal experiments themselves. Read what they say and send your objection by 5 November.',
	'rank_math_focus_keyword'    => 'draft lab animal rules 2026,ccsea draft rules,objection to draft rules',
	'rank_math_primary_category' => (string) get_term_by( 'slug', 'campaigns-civic-action', 'category' )->term_id,
) as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
WP_CLI::success( sprintf( '%s campaign %d (%s): %s', $existing ? 'updated' : 'created', $id, get_post_status( $id ), get_preview_post_link( $id ) ) );
