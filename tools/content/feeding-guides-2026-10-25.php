<?php
/**
 * Rewrites the two owner-approved feeding guides (25 Oct 2026; drafts in Claude outputs/feeding-guides/), same posts and
 * URLs: 9028 "Feeding community dogs: what the law says" and 9029 "RWA or society stopping you from feeding dogs".
 * Sources (checked 25 Oct 2026; Claude outputs/feeding-guides/sources-2026-10-25.md): ABC Rules 2023 (AWBI copy), AWBI
 * guidelines and advisories 2015-2026, Delhi High Court in Dr Maya D. Chablani (24 Jun 2021; SC stay vacated 19 May
 * 2022), Supreme Court 22 Aug 2025 and 19 May 2026. Their FAQ goes into the guide's own FAQ (meta aw_faq, approved).
 *
 * Usage: wp eval-file feeding-guides-2026-10-25.php [dry]
 */
$dry = ! empty( $args[0] ) && 'dry' === $args[0];

function g_p( $t ) { return "<!-- wp:paragraph -->\n<p>$t</p>\n<!-- /wp:paragraph -->\n\n"; }
function g_h( $t ) { return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$t</h2>\n<!-- /wp:heading -->\n\n"; }
function g_ul( $items ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->\n\n";
}
function g_table( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function g_a( $url, $text ) { return '<a href="' . esc_url( $url ) . '">' . $text . '</a>'; }
function g_sources( $list ) {
	$items = array();
	foreach ( $list as $s ) {
		$items[] = g_a( $s[1], $s[0] ) . ( isset( $s[2] ) ? ' (' . $s[2] . ')' : '' );
	}
	return g_h( 'Sources' ) . g_ul( $items );
}
// An item on the site by its path; stops the run when it is missing, so no link is ever guessed.
$out  = array();
$miss = array();
$L    = function ( $path ) use ( &$miss ) {
	$id = url_to_postid( home_url( $path ) );
	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		$miss[] = $path;
		return home_url( $path );
	}
	return get_permalink( $id );
};

$abc  = $L( '/resources/animal-birth-control-abc-rules-2023-explained/' );
$sco  = $L( '/resources/supreme-court-stray-dog-orders-explained/' );
$fir  = $L( '/resources/how-to-file-a-police-complaint-fir-for-animal-cruelty/' );
$rul  = $L( '/library/animal-birth-control-rules-2023/' );
$j22  = $L( '/library/supreme-court-judgment-of-22-august-2025-on-community-dogs-2025-insc-1018/' );
$j19  = $L( '/library/supreme-court-judgment-of-19-may-2026-on-stray-dogs-2026-insc-506/' );
$law  = home_url( '/resources/feeding-community-dogs-what-the-law-says/' );
$rwa  = home_url( '/resources/rwa-or-society-stopping-you-from-feeding-dogs-what-to-do/' );
$dir  = home_url( '/directory/' );
$cat  = get_term_by( 'slug', 'feeders-caregivers', 'category' );
if ( ! $cat ) {
	$miss[] = 'category feeders-caregivers';
}
foreach ( array( 9028, 9029 ) as $pid ) {
	if ( 'post' !== get_post_type( $pid ) ) {
		$miss[] = "post $pid";
	}
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$S = array(
	'abc'   => array( 'Animal Birth Control Rules, 2023 (G.S.R. 193(E), 10 Mar 2023), AWBI copy', 'https://awbi.gov.in/uploads/regulations/167956418266ABC%20Rule%202023.pdf', 'also in our Library' ),
	'g2015' => array( 'AWBI guidelines on pet and street dogs, caregivers, RWAs and AOAs', 'https://awbi.gov.in/uploads/circular/163282565895pet_dog_circular_26_2_2015.pdf', '26 Feb 2015' ),
	'd2015' => array( 'AWBI letter to all DGPs on harassment of citizens showing compassion to animals', 'https://awbi.gov.in/uploads/circular/163299189893lr_25082015_dgps_harassment.pdf', '25 Aug 2015' ),
	'd2020' => array( 'AWBI letter to Chief Secretaries and DGPs on harassment of citizens', 'https://awbi.gov.in/uploads/circular/163281868291Harassment%20of%20citizens.pdf', '14 Aug 2020' ),
	'a2021' => array( 'AWBI advisory on feeding spots for stray dogs', 'https://awbi.gov.in/uploads/regulations/163310193039feeding%20spots%20for%20stray%20dogs.pdf', '3 Mar 2021' ),
	'b2021' => array( 'AWBI advisory on dog feeders', 'https://awbi.gov.in/uploads/regulations/163310215935Dog%20feeder.pdf', '28 Jun 2021' ),
	'g2025' => array( 'AWBI guidelines for local bodies, RWAs and AOAs', 'https://awbi.gov.in/uploads/regulations/175275423709Implementation%20of%20ABC.pdf', '17 Jul 2025' ),
	'hc'    => array( 'Delhi High Court, Dr Maya D. Chablani v. Radha Mittal, I.A. 4164/2021 in CS(OS) 277/2020 (AWBI copy)', 'https://awbi.gov.in/uploads/courtorder/167542998854Dr-Maya-D-Chablani-vs-Smt-Radha-Mittal-Ors-on-24-June-2021.pdf', '24 Jun 2021; a Supreme Court stay of 4 Mar 2022 was vacated on 19 May 2022' ),
	'sc22'  => array( 'Supreme Court, Suo Motu W.P.(C) 5 of 2025, judgment of 22 Aug 2025 (2025 INSC 1018), para 33', $j22, 'our Library' ),
	'sc19'  => array( 'Supreme Court, judgment of 19 May 2026 (2026 INSC 506), paras 74 and 109', $j19, 'our Library' ),
);
$posts = array(
	9028 => array(
		'title'    => 'Feeding community dogs: what the law says',
		'rm_title' => 'Is Feeding Stray Dogs Legal in India Rules – Animal Writes',
		'desc'     => 'Feeding stray and community dogs is not illegal in India. What the ABC Rules 2023, AWBI and the Supreme Court say about where and how you can feed them.',
		'focus'    => 'feeding community dogs,is feeding stray dogs legal,feeding stray dogs law india,awbi guidelines for feeding stray dogs',
		'content'  => g_p( 'Feeding stray or community dogs is not illegal in India. The law is about <strong>where</strong> and <strong>how</strong> you feed: at feeding spots agreed with your residents\' association or the local body, at set times, and without littering. Since August 2025 the Supreme Court has said dogs must not be fed on the streets, only at dedicated feeding spaces.' )
			. g_h( 'What the law says, source by source' )
			. g_table(
				array( 'Source', 'What it says' ),
				array(
					array( g_a( $rul, 'Animal Birth Control Rules, 2023' ) . ', Rule 20(1)', 'The residents\' welfare association (RWA), apartment owners\' association (AOA) or the local body\'s representative must arrange feeding of community animals in the area.' ),
					array( 'ABC Rules 2023, Rule 20(1)(i)–(ii)', 'Feeding spots are agreed by both sides, away from children\'s play areas, entry and exit points and staircases. Feeding times are set around when children and senior citizens move about.' ),
					array( 'ABC Rules 2023, Rule 20(1)(iii)–(iv)', 'Designated feeders keep the spot clean and follow the association\'s guidelines. They may volunteer for vaccination, catching and release.' ),
					array( 'AWBI guidelines, 26 Feb 2015', '"There is no law that prohibits the feeding of street animals."' ),
					array( 'AWBI advisories, 3 Mar 2021 and 28 Jun 2021', 'Authorities and communities should identify enough feeding spots in every town, village and RWA, and feeders at designated spots must not be harassed.' ),
					array( 'AWBI guidelines, 17 Jul 2025', 'RWAs and AOAs keep a register of community animals (numbers, sterilisation, rabies vaccination, who feeds them).' ),
					array( 'Delhi High Court, Dr Maya D. Chablani, 24 Jun 2021', 'Community dogs have a right to food and citizens have a right to feed them, without harming or harassing others (para 126). Feeding is done at designated areas (para 127).' ),
					array( g_a( $j22, 'Supreme Court, 22 Aug 2025' ), 'Every municipal ward must create dedicated feeding spaces with notice boards. "Under no condition shall the feeding of stray dogs on the streets be permitted." (para 33(d))' ),
					array( g_a( $j19, 'Supreme Court, 19 May 2026' ), 'No feeding of stray dogs inside institutional premises (schools, hospitals and similar), unless the group files an affidavit accepting liability (para 74). The 22 Aug 2025 directions must be followed strictly.' ),
				)
			)
			. g_h( 'Where you can feed' )
			. g_ul(
				array(
					'<strong>At a designated feeding spot:</strong> one agreed with your RWA or AOA under Rule 20, or one created by your municipal body under the Supreme Court\'s order of 22 Aug 2025.',
					'<strong>At your own private entrance, porch or driveway, or a place not shared with other residents:</strong> the Delhi High Court said this in 2021 (para 131). The Supreme Court\'s 2025 order is about streets and public places.',
					'<strong>Not on the street,</strong> and not inside schools, hospitals and similar premises, under the Supreme Court\'s orders.',
				)
			)
			. g_h( 'Feeding responsibly' )
			. g_ul(
				array(
					'Feed at the same spot and time every day, and clean up afterwards (Rule 20(1)(iii)).',
					'Help get the dogs you feed sterilised and vaccinated: sterilised dogs are released back where they were caught (ABC Rules 2023, Rule 11(19)). See ' . g_a( $abc, 'ABC Rules 2023, explained' ) . '.',
					'Keep a simple record of the dogs, with sterilisation and vaccination dates. Your RWA must keep a register too (AWBI, 17 Jul 2025).',
				)
			)
			. g_h( 'If someone stops you' )
			. g_p( 'Harassing feeders is not allowed (AWBI, 2015 and 2021), and the ABC Rules set out how disputes about feeding spots are settled. Step by step: ' . g_a( $rwa, 'RWA or society stopping you from feeding dogs: what to do' ) . '. For the court orders in full, see ' . g_a( $sco, 'Supreme Court stray dog orders, explained' ) . '.' )
			. g_p( '<em>This guide is general information, not legal advice.</em>' )
			. g_sources( array( $S['abc'], $S['g2015'], $S['a2021'], $S['b2021'], $S['g2025'], $S['hc'], $S['sc22'], $S['sc19'] ) ),
		'faq'      => array(
			array( 'Is feeding stray dogs illegal in India?', 'No. Feeding stray or community dogs is not illegal in India. The Animal Welfare Board of India says "there is no law that prohibits the feeding of street animals". The rules decide where and how you feed.' ),
			array( 'Can I feed stray dogs on the street?', 'No. Since the Supreme Court\'s order of 22 Aug 2025, stray dogs must not be fed on the streets. Municipal bodies must create dedicated feeding spaces in every ward.' ),
			array( 'Who decides the feeding spot in my society?', 'Your RWA or AOA, with the feeders, under Rule 20 of the ABC Rules 2023. The spot must be away from play areas, entrances, exits and staircases.' ),
			array( 'What are the AWBI guidelines for feeding stray dogs?', 'AWBI\'s guidelines say feeding is legal, feeders must not be harassed, spots should be agreed and kept clean, and RWAs must arrange feeding and keep a register of community animals.' ),
		),
	),
	9029 => array(
		'title'    => 'RWA or society stopping you from feeding dogs: what to do',
		'rm_title' => 'Society Stops You Feeding Dogs What to Do – Animal Writes',
		'desc'     => 'If your RWA or housing society stops you feeding stray dogs: what the ABC Rules 2023 require, how the feeding-spot committee works, and where to complain.',
		'focus'    => 'society stopping feeding dogs,rwa stray dog feeding,feeding stray dogs in housing society,feeding spot abc rules',
		'content'  => g_p( 'Your residents\' welfare association (RWA) or housing society cannot simply ban feeding. Under the Animal Birth Control Rules, 2023, the association must itself arrange feeding of community dogs at agreed spots. If you can\'t agree, the Rules set up a committee that decides the feeding spot, and you can appeal to your State Animal Welfare Board.' )
			. g_h( 'What your RWA must do, and what it can\'t do' )
			. g_table(
				array( 'The RWA must', 'Source' ),
				array(
					array( 'Arrange feeding of community animals in the area, with the residents who feed them', g_a( $rul, 'ABC Rules 2023' ) . ', Rule 20(1)' ),
					array( 'Agree feeding spots away from play areas, entry and exit points and staircases, and set feeding times', 'Rule 20(1)(i)–(ii)' ),
					array( 'Keep a register of community animals (numbers, sterilisation, vaccination, feeders)', 'AWBI guidelines, 17 Jul 2025' ),
					array( 'Make sure dogs have food and water when no caregiver is there', 'Delhi High Court, Chablani, 2021, paras 129, 147' ),
				)
			)
			. g_table(
				array( 'The RWA can\'t', 'Source' ),
				array(
					array( 'Harass or intimidate feeders', 'AWBI guidelines, 2015 (harassment "maybe tantamount to the very grave offence of criminal intimidation"); AWBI advisory, 3 Mar 2021' ),
					array( 'Beat or drive away street dogs', 'AWBI guidelines, 2015' ),
					array( 'Have sterilised dogs taken elsewhere: they are released where they were caught', 'ABC Rules 2023, Rule 11(19); ' . g_a( $j22, 'Supreme Court, 22 Aug 2025' ) ),
					array( 'Stop you feeding unless the feeding harms or harasses others', 'Delhi High Court, Chablani, 2021, para 132' ),
				)
			)
			. g_p( 'Your duties as a feeder: feed only at the agreed spot and time, keep it clean, and follow the association\'s guidelines (Rule 20(1)(iii)). Since 22 Aug 2025 the Supreme Court has said dogs must not be fed on the streets. More in ' . g_a( $law, 'Feeding community dogs: what the law says' ) . '.' )
			. g_h( 'Step by step' )
			. g_ul(
				array(
					'<strong>Ask in writing for a feeding spot.</strong> Write to the committee, cite Rule 20 of the ABC Rules 2023, and propose a spot and time away from play areas, entrances and staircases. Keep copies.',
					'<strong>If they refuse: the Animal Welfare Committee.</strong> Under Rule 20(2), a conflict between the RWA and feeders goes to an Animal Welfare Committee: the Chief Veterinary Officer, the local police, the SPCA or State Animal Welfare Board, a recognised animal welfare organisation, the local body\'s vet, the person complaining and the RWA. Its decision on the feeding point is final. Ask your district\'s Chief Veterinary Officer or your municipal veterinary department to convene it.',
					'<strong>Appeal to the State Board.</strong> If you disagree with the committee, appeal to your State Animal Welfare Board (Rule 20(3)). Its decision is final.',
					'<strong>Write to AWBI.</strong> The Animal Welfare Board of India has asked states and police to stop harassment of feeders (letters of 25 Aug 2015 and 14 Aug 2020).',
					'<strong>If you are threatened or attacked, or the dogs are harmed:</strong> file a police complaint. AWBI has asked police to register FIRs on priority in such cases (14 Aug 2020). See ' . g_a( $fir, 'How to file a police complaint (FIR) for animal cruelty' ) . '.',
				)
			)
			. g_h( 'Keep a record' )
			. g_p( 'Save letters, notices, messages and photos, with dates. The committee, the State Board and the police will ask for them.' )
			. g_h( 'Get support' )
			. g_p( 'Local animal welfare organisations often help with these disputes. Find one in the ' . g_a( $dir, 'Directory' ) . '.' )
			. g_p( '<em>This guide is general information, not legal advice.</em>' )
			. g_sources( array( $S['abc'], $S['g2015'], $S['d2015'], $S['d2020'], $S['a2021'], $S['g2025'], $S['hc'], $S['sc22'] ) ),
		'faq'      => array(
			array( 'Can my society stop me from feeding stray dogs?', 'Your society cannot simply ban feeding. Under Rule 20 of the ABC Rules 2023 it must arrange feeding at agreed spots. It can ask you to feed only at that spot and time and to keep it clean.' ),
			array( 'Who decides the feeding spot if we can\'t agree?', 'An Animal Welfare Committee under Rule 20(2) of the ABC Rules 2023, which includes the Chief Veterinary Officer, the police and the RWA. Its decision is final; you can appeal to the State Animal Welfare Board.' ),
			array( 'Can the RWA remove the dogs from our area?', 'No. Sterilised and vaccinated dogs are released back where they were caught, under the ABC Rules 2023 and the Supreme Court\'s order of 22 Aug 2025.' ),
			array( 'What if I am threatened for feeding dogs?', 'File a police complaint. The Animal Welfare Board of India has said harassing feeders may amount to criminal intimidation and asked police to register FIRs on priority.' ),
		),
	),
);

foreach ( $posts as $pid => $d ) {
	$t  = mb_strlen( $d['rm_title'] );
	$dl = mb_strlen( $d['desc'] );
	if ( $t < 55 || $t > 58 || $dl > 155 || preg_match( '/[:,&]/', str_replace( ' – Animal Writes', '', $d['rm_title'] ) ) ) {
		WP_CLI::error( "Title or description check failed on $pid: t=$t d=$dl" );
	}
	if ( $dry ) {
		WP_CLI::log( "would update $pid | t=$t d=$dl | " . strlen( $d['content'] ) . ' bytes | ' . count( $d['faq'] ) . ' FAQ' );
		continue;
	}
	$r = wp_update_post( wp_slash( array( 'ID' => $pid, 'post_title' => $d['title'], 'post_excerpt' => $d['desc'], 'post_content' => $d['content'] ) ), true );
	if ( is_wp_error( $r ) ) {
		WP_CLI::error( $r->get_error_message() );
	}
	update_post_meta( $pid, 'rank_math_title', wp_slash( $d['rm_title'] ) );
	update_post_meta( $pid, 'rank_math_description', wp_slash( $d['desc'] ) );
	update_post_meta( $pid, 'rank_math_focus_keyword', wp_slash( $d['focus'] ) );
	update_post_meta( $pid, 'rank_math_primary_category', (int) $cat->term_id );
	wp_set_post_categories( $pid, array( (int) $cat->term_id ), true ); // add, keep the others
	$rows = array();
	foreach ( $d['faq'] as $x ) {
		$rows[] = array( 'q' => $x[0], 'a' => $x[1], 'sources' => array(), 'keyword' => '', 'status' => 'approved', 'reviewed' => '2026-10-25' );
	}
	aw_faq_items_save( 'post', $pid, $rows );
	update_post_meta( $pid, '_aw_owner_draft', 'Feeding guide rewrite; owner approved 25 Oct 2026; sources checked 25 Oct 2026' );
	clean_post_cache( $pid );
	WP_CLI::success( "$pid | " . get_permalink( $pid ) . " | t=$t d=$dl" );
}
