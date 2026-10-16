<?php
/**
 * Content strategy 15 Oct 2026, priority items 6 and 7 (P3): two DRAFTS for owner approval. Never publishes.
 * Facts only from the sources listed at the end of each draft, read on 16 Oct 2026.
 * Re-running updates the same drafts (by slug) while they are still drafts.
 * Usage: wp eval-file owner-drafts-p3-2026-10-16.php [dry]
 */
$dry = ! empty( $args[0] ) && 'dry' === $args[0];

function p3_p( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function p3_h( $t ) { return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n"; }
function p3_ul( $items ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->\n\n";
}
function p3_table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function p3_faq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return p3_h( 'Questions and answers' ) . '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function p3_a( $url, $text ) { return '<a href="' . esc_url( $url ) . '">' . $text . '</a>'; }
function p3_sources( $list ) {
	$items = array();
	foreach ( $list as $s ) {
		$items[] = p3_a( $s[1], $s[0] ) . ( isset( $s[2] ) ? ' (' . $s[2] . ')' : '' );
	}
	return p3_h( 'Sources' ) . p3_ul( $items );
}
$mark = p3_p( '<strong>DRAFT for owner approval: sources checked 16 Oct 2026</strong>' );
$disc = "<!-- wp:paragraph {\"className\":\"aw-guide-disclaimer\"} -->\n<p class=\"aw-guide-disclaimer\">This guide is general information, not legal advice.</p>\n<!-- /wp:paragraph -->\n\n";
$L    = fn( $id ) => get_permalink( $id );
$sc   = 'https://api.sci.gov.in/supremecourt/2025/41706/';

$drafts = array();

/* 6. Supreme Court stray dog orders */
$drafts[] = array(
	'slug'     => 'supreme-court-stray-dog-orders-explained',
	'title'    => 'Supreme Court stray dog orders, explained (2025 to 2026)',
	'cats'     => array( 'courts-judgments', 'community-dogs' ),
	'primary'  => 'courts-judgments',
	'rm_title' => 'Supreme Court Stray Dog Orders Explained – Animal Writes',
	'focus'    => 'supreme court stray dogs order,supreme court stray dogs judgement,city hounded by strays',
	'desc'     => 'The Supreme Court\'s stray dogs case began in July 2025. Its orders of August 2025, November 2025 and May 2026 set what councils must now do.',
	'content'  => $mark
		. p3_p( 'The Supreme Court\'s stray dogs orders all come from one case: Suo Motu Writ Petition (Civil) No. 5 of 2025, In Re: "City Hounded by Strays, Kids Pay Price". Four orders matter most. Together they decide where dogs that are picked up go back to, where they may be fed, and which places must be kept free of stray dogs.' )
		. p3_h( 'How the case began' )
		. p3_p( 'On 28 July 2025 a bench of two judges took up the case on its own, after a Times of India report from Delhi of the same name. On 11 August 2025 that bench ordered the authorities in Delhi, Noida, Ghaziabad, Gurugram and Faridabad to pick up stray dogs, build shelters, sterilise and vaccinate the dogs under the ' . p3_a( $L( 118 ), 'Animal Birth Control Rules, 2023' ) . ', and not release them back onto the streets.' )
		. p3_h( '22 August 2025: dogs go back to their area' )
		. p3_p( 'A bench of three judges, Justices Vikram Nath, Sandeep Mehta and N. V. Anjaria, changed the August 11 order. ' . p3_a( $L( 176 ), 'Read the judgment in the Library' ) . '.' )
		. p3_ul( array(
			'Dogs that are picked up are sterilised, dewormed, vaccinated and released back to the same area. The ban on release was kept in abeyance.',
			'Dogs with rabies, or suspected rabies, and dogs that show aggressive behaviour are sterilised and immunised but not released.',
			'Each municipal ward must have dedicated feeding spaces, with notice boards. Feeding stray dogs on the streets is not permitted.',
			'Each municipal authority must create a helpline to report violations.',
			'Anyone who wants to adopt a street dog can apply to the municipal body. The adopter must make sure the dog does not return to the streets.',
			'The case was widened to all states and union territories.',
		) )
		. p3_p( 'For what this means for people who feed dogs, read ' . p3_a( $L( 9028 ), 'feeding community dogs: what the law says' ) . ' and ' . p3_a( $L( 9029 ), 'what to do if your RWA or society stops you feeding dogs' ) . '.' )
		. p3_h( '7 November 2025: schools, hospitals, stations and highways' )
		. p3_p( 'The same three judges gave further directions. ' . p3_a( $L( 12246 ), 'Read our news summary of the order' ) . '.' )
		. p3_ul( array(
			'Educational institutions, hospitals, sports complexes, bus stands and depots, and railway stations must be fenced or otherwise secured against stray dogs, preferably within eight weeks. Each must name a nodal officer.',
			'The municipal body must remove stray dogs found inside these premises to a shelter, after sterilisation and vaccination. These dogs are not released back to the same place.',
			'Every government and private hospital must keep anti-rabies vaccine and immunoglobulin in stock at all times.',
			'Cattle and other stray animals must be removed from national and state highways and expressways and kept in shelters, gaushalas or cattle pounds, with food, water and veterinary care. Highways must show helpline numbers.',
			'The Animal Welfare Board of India had to issue Standard Operating Procedures for institutional premises within four weeks. It issued them on 27 November 2025.',
		) )
		. p3_h( '19 May 2026: the orders stand, and High Courts take over' )
		. p3_p( 'The three judges dismissed the applications that asked them to change or recall the November 2025 directions, and the challenges to the Board\'s Standard Operating Procedures. ' . p3_a( $L( 11238 ), 'Read our news summary of the judgment' ) . '.' )
		. p3_ul( array(
			'Every district must have at least one fully working Animal Birth Control centre.',
			'States and union territories must decide whether to extend the directions to other busy public places.',
			'Where dog bites have become frequent, authorities may, after assessment by qualified vets and only as the law allows, take measures including euthanasia of rabid, incurably ill or dangerous dogs.',
			'Officials who carry out the orders in good faith are protected from criminal cases, except where there is a prima facie case of bad faith or gross abuse of authority.',
			'Each High Court must register its own case to monitor compliance in its state. States and union territories were to file compliance affidavits before their High Courts by 7 August 2026.',
		) )
		. p3_p( 'The Supreme Court will next hear the case on 17 November 2026, to receive the High Courts\' reports.' )
		. p3_h( 'What councils were told to do' )
		. p3_p( 'The ' . p3_a( $L( 13800 ), 'Animal Welfare Board of India' ) . ' asked for strict compliance with these directions, its Standard Operating Procedures and the ABC Rules in a circular of 21 July 2026. ' . p3_a( $L( 9561 ), 'Read the circular in the Library' ) . '. For how sterilisation and release work, read ' . p3_a( $L( 9032 ), 'what the ABC Rules 2023 say about community dogs' ) . '.' )
		. p3_faq( array(
			array( 'What is the Supreme Court stray dogs case called?', 'Suo Motu Writ Petition (Civil) No. 5 of 2025, In Re: "City Hounded by Strays, Kids Pay Price".' ),
			array( 'Did the Supreme Court order all stray dogs into shelters?', 'The order of 11 August 2025 did, for Delhi and the NCR. On 22 August 2025 a bench of three judges changed it: dogs are sterilised, dewormed, vaccinated and released back to the same area, except dogs with rabies or aggressive behaviour.' ),
			array( 'Can stray dogs be fed on the street?', 'Under the order of 22 August 2025, each municipal ward must have dedicated feeding spaces, and feeding stray dogs on the streets is not permitted.' ),
			array( 'What did the November 2025 order say about schools and hospitals?', 'They must be secured against stray dogs, and the municipal body must remove stray dogs found inside them to a shelter after sterilisation and vaccination. These dogs are not released back to the same place.' ),
			array( 'Who monitors the orders now?', 'Since 19 May 2026, each High Court monitors compliance in its state through its own case.' ),
		) )
		. $disc
		. p3_sources( array(
			array( 'Supreme Court of India: order of 22 August 2025, 2025 INSC 1018 (quotes the 11 August 2025 directions)', $sc . '41706_2025_3_1501_63567_Judgement_22-Aug-2025.pdf', 'read 16 Oct 2026; also in our Library' ),
			array( 'Supreme Court of India: order of 7 November 2025, 2025 INSC 1357', $sc . '41706_2025_3_301_65687_FinalOrder_07-Nov-2025.pdf', 'read 16 Oct 2026' ),
			array( 'Supreme Court of India: judgment of 19 May 2026, 2026 INSC 506', $sc . '41706_2025_2_1501_71480_Judgement_19-May-2026.pdf', 'read 16 Oct 2026; paras 75, 76, 85, 108, 111 and 114' ),
			array( 'Animal Welfare Board of India: circular of 21 July 2026', $L( 9561 ), 'our Library' ),
		) ),
);

/* 7. Bird helplines by city */
$drafts[] = array(
	'slug'     => 'injured-bird-helpline-numbers-by-city',
	'title'    => 'Injured bird helplines by city: Gujarat, Delhi, Mumbai, Bengaluru',
	'cats'     => array( 'birds', 'wildlife' ),
	'primary'  => 'birds',
	'rm_title' => 'Injured Bird Helpline Numbers City by City – Animal Writes',
	'focus'    => 'injured bird helpline,bird rescue helpline,bird rescue',
	'desc'     => 'Who to call for an injured bird in Gujarat, Delhi, Mumbai and Bengaluru. Every number here comes from a government or rescue group\'s own website.',
	'content'  => $mark
		. p3_p( 'Found an injured bird? Put it in a dark, quiet box with air holes, do not feed it, and call one of the numbers below. Every number here was checked on the government or rescue group\'s own website on 16 October 2026. We list a city only where we could check it.' )
		. p3_h( 'Gujarat, including Ahmedabad' )
		. p3_p( 'The state\'s Karuna Abhiyan runs every January, around the kite festival, from 10 to 20 January in 2026. The numbers below were announced for it by the state government.' )
		. p3_table( array( 'Number', 'For' ), array(
			array( '<strong>8320002000</strong> (WhatsApp)', 'Forest Department, 24x7. Send "Hi" for a link to bird treatment centres in each district.' ),
			array( '<strong>1926</strong>', 'Forest Department, 24x7, for injured birds' ),
			array( '<strong>1962</strong>', 'Animal Husbandry Department, for saving animals and birds' ),
		) )
		. p3_h( 'Delhi' )
		. p3_table( array( 'Who', 'Number', 'Details' ), array(
			array( 'Wildlife Rescue, Wazirabad Village', '<strong>+91 98100 29698</strong> (call or WhatsApp)', 'Birds of prey. Monday to Saturday, 10:00 AM to 8:00 PM; Sunday, phone only for emergencies.' ),
			array( 'Department of Forests and Wildlife, Government of Delhi', '<strong>1800-11-8600</strong> (toll-free Green Helpline)', 'Wild animals that are hurt or orphaned' ),
		) )
		. p3_h( 'Mumbai and Thane' )
		. p3_table( array( 'Who', 'Number', 'Details' ), array(
			array( 'Resqink Association for Wildlife Welfare (RAWW)', '<strong>+91 76666 80202</strong>, <strong>+91 98697 80202</strong>', 'Volunteer group that rescues wild animals, birds and reptiles in and around Mumbai and Thane' ),
		) )
		. p3_h( 'Bengaluru' )
		. p3_table( array( 'Who', 'Number', 'Details' ), array(
			array( 'Avian and Reptile Rehabilitation Centre (ARRC), Horamavu', '<strong>+91 94496 42222</strong>', 'Birds, including birds caught in manja (kite string)' ),
			array( 'PfA Wildlife Hospital', '<strong>+91 99000 25370</strong>, <strong>+91 99803 39880</strong>', 'Urban wildlife rescue team, on call 365 days a year' ),
		) )
		. p3_h( 'Anywhere else' )
		. p3_p( 'For a wild bird, contact your state forest department or a wildlife rescue group: see ' . p3_a( $L( 9031 ), 'who to call for an animal emergency in India' ) . '. In states that run it, ' . p3_a( $L( 13798 ), 'the 1962 helpline' ) . ' sends a Mobile Veterinary Unit. You can also find rescue groups near you in the ' . p3_a( home_url( '/directory/' ), 'Directory' ) . ', and read ' . p3_a( $L( 126 ), 'how to stay safe and help an injured animal' ) . '.' )
		. p3_p( 'Know a bird helpline in your city that we should add? Tell us where it is published, so we can check it.' )
		. p3_faq( array(
			array( 'What should I do first with an injured bird?', 'Put it in a dark, quiet box with air holes, do not feed it, and call a rescue helpline.' ),
			array( 'What is the bird helpline number in Gujarat?', 'WhatsApp 8320002000 or call 1926 for the Forest Department, or 1962 for the Animal Husbandry Department.' ),
			array( 'Who rescues injured birds in Delhi?', 'Wildlife Rescue in Wazirabad Village takes birds of prey on +91 98100 29698. The Delhi Forest Department\'s toll-free Green Helpline is 1800-11-8600.' ),
			array( 'Who rescues injured birds in Bengaluru?', 'The Avian and Reptile Rehabilitation Centre on +91 94496 42222, or PfA Wildlife Hospital on +91 99000 25370.' ),
		) )
		. p3_sources( array(
			array( 'CMO Gujarat, 13 January 2026: Karuna Abhiyan 2026 (dates, 8320002000, 1926, 1962)', 'https://cmogujarat.gov.in/en/latest-news/karuna-abhiyan-2026-gujarat-cm-visit-wildlife-care-centre', 'read 16 Oct 2026' ),
			array( 'DD News, 14 January 2026: Gujarat CM reviews wildlife care centre as Karuna Abhiyan intensifies', 'https://ddnews.gov.in/en/gujarat-cm-reviews-wildlife-care-centre-as-karuna-abhiyan-intensifies/', 'read 16 Oct 2026' ),
			array( 'Wildlife Rescue, Delhi: Contact (number, hours, what to do with an injured bird)', 'https://www.raptorrescue.org/contact', 'read 16 Oct 2026' ),
			array( 'Department of Forests and Wildlife, Government of NCT of Delhi: Animal Rescue (Green Helpline)', 'https://wildliferescue.eforest.delhi.gov.in', 'read 16 Oct 2026' ),
			array( 'RAWW: home page, contact and rescue services', 'https://www.raww.in', 'read 16 Oct 2026' ),
			array( 'ARRC Wildlife Rescue, Bengaluru', 'https://wildarrc.org', 'read 16 Oct 2026' ),
			array( 'PfA Wildlife Hospital, Bengaluru', 'https://www.pfawildlifehospital.org', 'read 16 Oct 2026' ),
		) ),
);

$out = array();
foreach ( $drafts as $d ) {
	$t  = mb_strlen( $d['rm_title'] );
	$dl = mb_strlen( $d['desc'] );
	if ( $t < 55 || $t > 58 || $dl > 155 || preg_match( '/[:,&]/', str_replace( ' – Animal Writes', '', $d['rm_title'] ) ) ) {
		$out[] = 'LENGTH ' . $d['slug'] . " t=$t d=$dl";
		continue;
	}
	$live = get_posts( array( 'name' => $d['slug'], 'post_type' => 'post', 'post_status' => array( 'publish', 'pending', 'future' ), 'numberposts' => 1 ) );
	if ( $live ) {
		$out[] = 'SKIP not a draft: ' . $d['slug'];
		continue;
	}
	$existing = get_posts( array( 'name' => $d['slug'], 'post_type' => 'post', 'post_status' => array( 'draft' ), 'numberposts' => 1 ) );
	$arr      = array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_name'    => $d['slug'],
		'post_title'   => $d['title'],
		'post_excerpt' => $d['desc'],
		'post_content' => $d['content'],
	);
	if ( $existing ) {
		$arr['ID'] = $existing[0]->ID;
	}
	if ( $dry ) {
		$out[] = 'DRY ' . $d['slug'] . " t=$t d=$dl";
		continue;
	}
	$id = wp_insert_post( wp_slash( $arr ), true );
	if ( is_wp_error( $id ) ) {
		$out[] = 'ERR ' . $d['slug'] . ' ' . $id->get_error_message();
		continue;
	}
	wp_set_object_terms( $id, $d['cats'], 'category' );
	wp_set_object_terms( $id, array( 'guide' ), 'post_tag' );
	update_post_meta( $id, 'rank_math_title', wp_slash( $d['rm_title'] ) );
	update_post_meta( $id, 'rank_math_description', wp_slash( $d['desc'] ) );
	update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( $d['focus'] ) );
	$pc = get_term_by( 'slug', $d['primary'], 'category' );
	if ( $pc ) {
		update_post_meta( $id, 'rank_math_primary_category', $pc->term_id );
	}
	update_post_meta( $id, '_aw_owner_draft', '2026-10-16 content strategy P3; sources checked 16 Oct 2026' );
	$out[] = $id . ' | ' . get_post_status( $id ) . ' | ' . $d['title'] . ' | ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . " | t=$t d=$dl";
}
echo implode( "\n", $out ), "\n";
