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
		'layout'    => array( 'type' => 'constrained', 'contentSize' => '1120px' ),
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
function c2html( $h ) {
	return "<!-- wp:html -->\n$h\n<!-- /wp:html -->\n\n";
}
/** Theme icon (aw.css 4.7), same markup as aw_i(). */
function c2i( $n ) {
	return '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
}
function chip( $rule, $label, $allows = '' ) {
	return '[aw_rule_chip rule="' . $rule . '" label="' . $label . '"' . ( $allows ? ' allows="' . $allows . '"' : '' ) . ']';
}

/*
 * Layout (17 Oct 2026 redesign): campaign branding from the explainer artwork (brick red, ink, paper; heavy tight
 * headlines; stat strip with red top rules; red address box), built from core blocks and theme icons (aw_i names).
 * Each section uses its own layout: split hero with a poster panel, date strip, icon grid, pain ladder, table,
 * stepped letter tool, icon list, two-column FAQ, document links, step line, news grid, share band.
 */

/* 1. Hero (open and closed versions; one shows). */
$poster = '<div class="aw-camp-poster">'
	. '<p class="aw-camp-stamp">G.S.R. 864(E)</p>'
	. '[aw_countdown date="2026-11-05" label="Objections close"]'
	. '<p class="aw-camp-poster-chips"><span class="aw-camp-poster-label">Read the clauses</span> ' . chip( '11-3', 'Rule 11(3)' ) . ' ' . chip( '21-2', 'Rule 21(2)' ) . ' ' . chip( '5-3', 'Rule 5(3)' ) . ' ' . chip( '2-i', 'Rule 2(i)' ) . '</p>'
	. '</div>';
$hero = c2sec(
	'top',
	'<!-- wp:columns {"verticalAlignment":"center","className":"aw-camp-hero-cols"} -->' . "\n" . '<div class="wp-block-columns are-vertically-aligned-center aw-camp-hero-cols">'
	. '<!-- wp:column {"verticalAlignment":"center","width":"58%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%">'
	. c2h( 'Labs could soon approve their own animal experiments.', 1 )
	. c2p( 'Draft rules would move most approvals for animal experiments from the national regulator to each lab\'s own committee.', 'aw-camp-hero-summary' )
	. '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button -->' . "\n" . '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#send">Send your objection</a></div>' . "\n" . '<!-- /wp:button -->' . "\n"
	. '<!-- wp:button {"className":"is-style-outline aw-camp-ghost"} -->' . "\n" . '<div class="wp-block-button is-style-outline aw-camp-ghost"><a class="wp-block-button__link wp-element-button" href="#rules">What the rules say</a></div>' . "\n" . '<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons -->' . "\n\n"
	. '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
	. '<!-- wp:column {"verticalAlignment":"center","width":"42%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%">'
	. c2html( $poster )
	. '</div>' . "\n" . '<!-- /wp:column --></div>' . "\n" . '<!-- /wp:columns -->' . "\n\n",
	'aw-camp-hero aw-camp-open'
);
$hero_closed = c2sec( // copy for the owner to edit (17 Oct 2026 brief: [OWNER COPY]); facts only until then
	'',
	c2h( 'Objections to the draft lab animal rules have closed.', 1 )
	. c2p( 'The public could send objections to CCSEA until 5 November 2026. CCSEA now reviews them before publishing final rules in the Gazette.', 'aw-camp-hero-summary' )
	. c2sc( '[aw_campaign_count min="100"]' )
	. c2buttons( array( array( 'Read the draft rules', $lib_draft ) ) ),
	'aw-camp-hero aw-camp-closed'
);

/* 2. What's happening: the artwork's date strip. */
$what = c2sec(
	'what',
	c2h( 'What\'s happening' )
	. c2p( 'The Gazette of India has published draft rules (G.S.R. 864(E)) to replace the 1968 and 1998 rules on experiments on animals. The public has 30 days to send objections to the Committee for Control and Supervision of Experiments on Animals (CCSEA).', 'aw-camp-lede' )
	. c2html(
		'<ol class="aw-camp-dates">'
		. '<li><span class="aw-camp-dates-when">30 Sep 2026</span><span class="aw-camp-dates-what">Notification signed</span></li>'
		. '<li><span class="aw-camp-dates-when">6 Oct 2026</span><span class="aw-camp-dates-what">Published in the Gazette</span></li>'
		. '<li class="is-key"><span class="aw-camp-dates-when">5 Nov 2026</span><span class="aw-camp-dates-what">Last day for objections</span></li>'
		. '</ol>'
	)
	. c2p( '<a class="aw-camp-more" href="' . $lib_draft . '">Read the full draft in our Library ' . c2i( 'arrow-right' ) . '</a>' ),
	'aw-camp-paper'
);

/* 3. What the draft does: icon grid, the main change leads. */
$card = function ( $icon, $title, $text, $chips ) {
	return c2p( c2i( $icon ), 'aw-camp-card-ico' ) . c2h( $title, 3 ) . c2p( $text ) . c2p( $chips, 'aw-camp-card-chips' );
};
$cards = array(
	$card( 'building', 'Labs approve most experiments themselves', 'Mild and moderate experiments on any species, including dogs and monkeys, would be approved by the lab\'s own ethics committee and not sent to CCSEA. CCSEA only receives reports for animals larger than rabbits.', chip( '11-3', 'Rule 11(3)', 'The lab committee alone approves mild and moderate experiments on every species.' ) . ' ' . chip( '11-4', 'Rule 11(4)', 'For animals larger than rabbits, the committee reports to CCSEA at three stages; CCSEA does not approve.' ) ),
	$card( 'world', 'Some animals could come from open markets', 'If registered breeders can\'t supply them, livestock, farm animals, fish and birds could be bought from unregistered sources or the open market, with only the lab committee\'s approval.', chip( '21-2', 'Rule 21(2)', 'Buying from unregistered sources or the open market with the lab committee\'s approval and a quarantine.' ) ),
	$card( 'alert', 'Strays: banned, with exemptions', 'Stray and feral animals are not to be used, but "competent authorities", never defined in the draft, can grant exemptions. In 2022 the regulator told labs stray dogs may be used in vaccine trials, and withdrew that in 2023.', chip( '5-3', 'Rule 5(3)', 'Exemptions for studies on animal health, the environment or human or animal health, when only strays will do.' ) ),
	$card( 'heart', 'Wildlife and monkeys: allowed under conditions', 'Protected species and primates can be used for listed purposes with justification and permission. The draft is unclear on primates.', chip( '5-1-c', 'Rule 5(1)(c)' ) . ' ' . chip( '6-2', 'Rule 6(2)' ) ),
	$card( 'home', 'A lab doesn\'t need walls', 'Places that are not wholly enclosed, and mobile facilities, count as labs.', chip( '2-i', 'Rule 2(i)' ) ),
	$card( 'knowledge', 'Training is a reason to use animals', 'Higher education and professional training are listed purposes, and teaching approvals can last five years.', chip( '4-6', 'Rule 4(6)' ) . ' ' . chip( '11-11', 'Rule 11(11)' ) ),
);
$grid = '';
foreach ( $cards as $c ) {
	$grid .= "<!-- wp:group {\"className\":\"aw-camp-card\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-camp-card\">$c</div>\n<!-- /wp:group -->\n\n";
}
$rules = c2sec( 'rules', c2h( 'What the draft does' ) . "<!-- wp:group {\"className\":\"aw-camp-grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-camp-grid\">$grid</div>\n<!-- /wp:group -->\n\n" );

/* 4. Pain scale: a four-step ladder; who approves sits on each step. */
$tier = function ( $lvl, $name, $who, $ext, $text ) {
	return '<li class="aw-camp-tier aw-camp-tier--' . $lvl . '"><span class="aw-camp-tier-bar" aria-hidden="true"></span>'
		. '<h3>' . $name . '</h3><p class="aw-camp-tier-who' . ( $ext ? ' is-ext' : '' ) . '">' . c2i( $ext ? 'legal' : 'building' ) . $who . '</p><p>' . $text . '</p></li>';
};
$pain = c2sec(
	'pain-scale',
	c2h( 'The pain scale decides who says yes' )
	. c2p( 'Every experiment would be labelled by how much pain it is expected to cause. The label decides whether anyone outside the lab reviews it.', 'aw-camp-lede' )
	. c2html(
		'<ol class="aw-camp-ladder">'
		. $tier( 1, 'Mild', 'Lab\'s own committee', false, 'Keeping sociable rats or mice alone for a short time; withholding food from adult rats for under 24 hours.' )
		. $tier( 2, 'Moderate', 'Lab\'s own committee', false, 'Withholding food from adult rats for 48 hours; stimuli the animal cannot escape or avoid; inducing tumours.' )
		. $tier( 3, 'Severe', 'CCSEA, for animals larger than rodents and rabbits', true, 'Toxicity tests where death is the endpoint; inescapable electric shock; long isolation of dogs and monkeys.' )
		. $tier( 4, 'Non-recovery', 'CCSEA, for animals larger than rodents and rabbits', true, 'Done fully under anaesthesia; the animal does not wake up.' )
		. '</ol>'
		. '<p class="aw-camp-gap">' . c2i( 'alert' ) . '<span>The draft does not say who approves severe experiments on rats, mice or rabbits. ' . chip( '11-2', 'Rule 11(2)' ) . ' ' . chip( 'annexure-1', 'Annexure I' ) . '</span></p>'
	),
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
	),
	'aw-camp-paper aw-camp-compare'
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
	. ' address="The Member Secretary, Committee for Control and Supervision of Experiments on Animals (CCSEA), Shadipur, Delhi 110008"'
	. ' intro="I submit the following objections to the draft Prevention of Cruelty to Animals (Control and Supervision of Experiment, Breeding and Trading of Laboratory Animals) Rules, 2026, G.S.R. 864(E) dated 30 September 2026, published in the Gazette of India on 6 October 2026:"'
	. ' closing="I request that the draft be withdrawn and revised after wider consultation, and that CCSEA publish a clause-by-clause comparison with the 1998 Rules. Kindly acknowledge receipt of this objection."]';
$send = c2sec(
	'send',
	c2h( 'Send your objection' )
	. c2p( 'Choose your grounds, sign it, and send it from your own email. Letters with a name, a city and a line in your own words count as individual responses.', 'aw-camp-lede' )
	. c2list( $grounds, true, 'aw-letter-grounds' )
	. c2sc( $letter_sc )
	. c2sc( '[aw_campaign_count min="100"]' )
	. c2p( c2i( 'check' ) . 'We store nothing from this form. Your letter goes from your own email account.', 'aw-camp-note' ),
	'aw-camp-paper aw-camp-open'
);

/* 7. Other ways to send: icon list. */
$way = function ( $icon, $title, $text ) {
	return '<li>' . c2i( $icon ) . '<div><h3>' . $title . '</h3><p>' . $text . '</p></div></li>';
};
$how = c2sec(
	'how',
	c2h( 'Other ways to send' )
	. c2html(
		'<ul class="aw-camp-ways">'
		. $way( 'file', 'Copy and paste', 'Copy the letter and the address, and paste them into any email app.' )
		. $way( 'mail', 'By post', 'Member Secretary (CCSEA), Office of CCSEA, Near State Bank of India, Delhi Milk Scheme Complex, Shadipur, Delhi 110008. Post it by 30 October so it arrives in time.' )
		. $way( 'send', 'Write your own', 'Use your own words. Mention G.S.R. 864(E), the rule numbers, your name and city, and ask for an acknowledgment.' )
		. '</ul>'
	),
	'aw-camp-open'
);

/* 8. Questions: heading column and answers column. */
$faq = c2sec(
	'faq',
	'<!-- wp:columns {"className":"aw-camp-faq-cols"} -->' . "\n" . '<div class="wp-block-columns aw-camp-faq-cols">'
	. '<!-- wp:column {"width":"34%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:34%">'
	. c2h( 'Questions' )
	. c2p( 'Something else? <a href="/contact/">Ask us</a> and we\'ll add the answer here.' )
	. '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
	. '<!-- wp:column {"width":"66%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:66%">'
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
	. '</div>' . "\n" . '<!-- /wp:column --></div>' . "\n" . '<!-- /wp:columns -->' . "\n\n"
);

/* 10. Read the law: every Library document with the campaign tag (the draft, the Act, the 1998 Rules, circulars, orders). */
$tag = get_term_by( 'slug', 'campaign-lab-animals-2026', 'post_tag' );
$tq  = '"taxQuery":{"post_tag":[' . (int) $tag->term_id . ']}';
$read = c2sec(
	'read',
	c2h( 'Read the law and the record' )
	. c2p( 'The draft, the law it would replace, and the circulars and court orders behind this campaign, in full in our Library.', 'aw-camp-lede' )
	. '<!-- wp:query {"queryId":84,"query":{"perPage":12,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"aw-camp-docs-q kit-ico--file"} -->' . "\n"
	. '<div class="wp-block-query aw-camp-docs-q kit-ico--file"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n\n",
	'aw-camp-paper'
);

/* 11. What happens next: a real sequence, so numbered. */
$next_inner = c2h( 'What happens next' )
	. c2html(
		'<ol class="aw-camp-steps">'
		. '<li><strong>Objections close</strong><span>5 Nov 2026</span></li>'
		. '<li><strong>CCSEA reviews</strong><span>Every objection and suggestion is considered</span></li>'
		. '<li><strong>Final rules</strong><span>Published in the Gazette, revised or not</span></li>'
		. '<li><strong>We report back</strong><span>Here and in our newsletter</span></li>'
		. '</ol>'
	);
$next        = c2sec( 'next', $next_inner, 'aw-camp-open' );
$next_closed = c2sec( 'next', $next_inner, 'aw-camp-closed' ); // after closing it moves directly under the hero

/* News and guides: the newest campaign News item leads, the newest campaign guides (Resources) beside it; the home page's news grid. */
$updates = c2sec(
	'latest',
	c2html( '<header class="kit-ed-head"><div><h2>News and guides</h2></div><a class="kit-ed-more" href="/resources/">All guides ' . c2i( 'arrow-right' ) . '</a></header>' )
	. '<!-- wp:group {"className":"kit-ed-news-grid","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group kit-ed-news-grid">'
	. '<!-- wp:query {"queryId":82,"query":{"perPage":1,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"kit-ed-lead"} -->' . "\n"
	. '<div class="wp-block-query kit-ed-lead"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:animal-writes/publisher /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- wp:post-excerpt {"excerptLength":36} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n"
	. '<!-- wp:query {"queryId":83,"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"kit-ed-list"} -->' . "\n"
	. '<div class="wp-block-query kit-ed-list"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:animal-writes/publisher /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n"
	. '</div>' . "\n" . '<!-- /wp:group -->' . "\n\n"
);

/* In the press: Media & PR items with the campaign tag, in the Media & PR hub's cards; then the campaign's partners
   (none listed for now; the shortcode prints nothing until the owner names them). */
$press = c2sec(
	'press',
	c2html( '<header class="kit-ed-head"><div><h2>In the press</h2></div><a class="kit-ed-more" href="/media/?tag=campaign-lab-animals-2026">All coverage ' . c2i( 'arrow-right' ) . '</a></header>' )
	. '<!-- wp:query {"queryId":85,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_press","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"aw-hub-list aw-hub-media aw-camp-press","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-query aw-hub-list aw-hub-media aw-camp-press"><!-- wp:post-template {"className":"aw-news-grid","layout":{"type":"default"}} -->' . "\n"
	. '<!-- wp:group {"tagName":"article","className":"aw-story aw-story\\u002d\\u002dcard aw-story\\u002d\\u002dexternal","layout":{"type":"default"}} -->' . "\n"
	. '<article class="wp-block-group aw-story aw-story--card aw-story--external"><!-- wp:animal-writes/press-media /-->' . "\n\n"
	. '<!-- wp:group {"className":"aw-story-body","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group aw-story-body"><!-- wp:animal-writes/publisher /-->' . "\n\n"
	. '<!-- wp:post-title {"level":3,"isLink":true,"className":"aw-story-title"} /-->' . "\n\n" . '<!-- wp:animal-writes/press-meta /--></div>' . "\n" . '<!-- /wp:group --></article>' . "\n" . '<!-- /wp:group -->' . "\n"
	. '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n\n"
	. c2sc( '[aw_campaign_partners title="Partners"]' ),
	'aw-camp-paper'
);

/* 12. Stay in the loop: share row with the theme's brand icons. */
$share_url = rawurlencode( home_url( '/campaigns/lab-animals-rules-2026/' ) );
$share_txt = rawurlencode( 'Object to India\'s draft lab animal rules before 5 November:' );
$loop      = c2sec(
	'updates',
	c2h( 'Get updates on these rules' )
	. c2p( 'We\'ll email you when CCSEA responds and when the final rules are published. Sign up with the newsletter form at the end of this page.' ) // the footer has the form on every page
	. c2html(
		'<p class="aw-camp-share" data-source="campaign2"><span class="aw-camp-share-label">Share this page</span>'
		. '<a data-kit-share="whatsapp" href="https://wa.me/?text=' . $share_txt . '%20' . $share_url . '">' . c2i( 'brand-whatsapp' ) . 'WhatsApp</a>'
		. '<a data-kit-share="x" href="https://twitter.com/intent/tweet?text=' . $share_txt . '&amp;url=' . $share_url . '">' . c2i( 'x-brand' ) . 'X</a>'
		. '<a data-kit-share="facebook" href="https://www.facebook.com/sharer/sharer.php?u=' . $share_url . '">' . c2i( 'facebook' ) . 'Facebook</a>'
		. '<a data-kit-share="linkedin" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url . '">' . c2i( 'link' ) . 'LinkedIn</a>'
		. '<button type="button" class="kit-share-copy" data-kit-share="copy_link" data-url="' . esc_url( home_url( '/campaigns/lab-animals-rules-2026/' ) ) . '" data-done="Link copied">' . c2i( 'link' ) . 'Copy link</button>'
		. '</p>'
	),
	'aw-camp-dark'
);

$content = '<!-- wp:group {"align":"full","className":"aw-camp aw-camp--lab-rules","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignfull aw-camp aw-camp--lab-rules">'
	. $hero . $hero_closed . $next_closed . $what . $rules . $pain . $compare . $send . $how . $faq . $read . $next . $updates . $press . $loop
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
if ( $existing ) { // keep the publish date (wp_insert_post would set it to now)
	$post['ID']            = $existing->ID;
	$post['post_date']     = $existing->post_date;
	$post['post_date_gmt'] = $existing->post_date_gmt;
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
