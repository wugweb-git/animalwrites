<?php
/**
 * Publishes the three owner-approved guides of 15 Oct 2026 (approved after a final check):
 * 13797 World Animal Day, 13800 Animal Welfare Board of India, 13798 1962 helpline.
 *
 * The final check (15 Oct 2026) re-read every source; this file holds the checked text. The draft marker line is gone.
 * Usage: wp eval-file publish-guides-2026-10-15.php [dry]
 * Pass 1 publishes the three posts with today's date, so pass 2 can link them to each other by their real URLs.
 * Pass 2 writes the checked content, title, description, focus keywords, categories and the Guide tag.
 * Then it removes "1962 helpline" and "1962 mobile veterinary unit" from the two emergency guides' secondary
 * keywords and adds a link from each to the 1962 guide (content strategy, item 3).
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
function g_faq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return g_h( 'Questions and answers' ) . '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function g_a( $url, $text ) { return '<a href="' . esc_url( $url ) . '">' . $text . '</a>'; }
function g_sources( $list ) {
	$items = array();
	foreach ( $list as $s ) {
		$items[] = g_a( $s[1], $s[0] ) . ( isset( $s[2] ) ? ' (' . $s[2] . ')' : '' );
	}
	return g_h( 'Sources' ) . g_ul( $items );
}
$disc = "<!-- wp:paragraph {\"className\":\"aw-guide-disclaimer\"} -->\n<p class=\"aw-guide-disclaimer\">This guide is general information, not legal advice.</p>\n<!-- /wp:paragraph -->\n\n";
$L    = fn( $id ) => get_permalink( $id );
$out  = array();
$ids  = array( 13797, 13800, 13798 );

/* Pass 1: publish with today's date. */
$now = current_time( 'mysql' );
$gmt = current_time( 'mysql', true );
foreach ( $ids as $id ) {
	$p = get_post( $id );
	if ( ! $p || 'post' !== $p->post_type ) {
		$out[] = "MISSING $id";
		continue;
	}
	if ( 'publish' === $p->post_status ) {
		continue; // already published by an earlier run; keep its date
	}
	if ( ! $dry ) {
		$r = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish', 'post_date' => $now, 'post_date_gmt' => $gmt, 'edit_date' => true ), true );
		$out[] = is_wp_error( $r ) ? "ERR publish $id " . $r->get_error_message() : "published $id at $now";
	}
}
clean_post_cache( 13797 ); clean_post_cache( 13800 ); clean_post_cache( 13798 );

$guides = array();

/* 1. World Animal Day (13797) */
$guides[13797] = array(
	'title'    => 'World Animal Day: why 4 October, and how it began',
	'cats'     => array( 'campaigns-civic-action' ),
	'primary'  => 'campaigns-civic-action',
	'rm_title' => 'World Animal Day History and Why 4 October – Animal Writes',
	'focus'    => 'world animal day,world animal day 4 october,world animal day history',
	'desc'     => 'World Animal Day is marked every year on 4 October, the feast day of Francis of Assisi. It began in Berlin in 1925 with Heinrich Zimmermann.',
	'content'  => g_p( 'World Animal Day is marked every year on 4 October. The date is the feast day of Francis of Assisi, the patron saint of animals. The day exists to raise the status of animals and improve welfare standards around the world.' )
		. g_h( 'How it began' )
		. g_p( 'The idea came from Heinrich Zimmermann, a writer and publisher of the German magazine Mensch und Hund (Man and Dog). He organised the first World Animal Day on 24 March 1925 at the Sport Palace in Berlin, and more than 5,000 people attended.' )
		. g_h( 'Why 4 October' )
		. g_p( 'The first event was meant to be held on 4 October, but the venue was not free that day. The day was held on 4 October for the first time in 1929. In May 1931, an international animal protection congress in Florence, Italy, adopted Zimmermann\'s proposal to make 4 October World Animal Day everywhere.' )
		. g_h( 'Who coordinates it today' )
		. g_p( 'Since 2003, the UK animal welfare charity Naturewatch Foundation has coordinated the day. That year it launched the World Animal Day website, and in 2007 it started an Ambassador Programme. The day now has over 90 ambassadors in more than 70 countries. In 2025 it marked its 100th anniversary.' )
		. g_h( 'Recent themes' )
		. g_p( 'Each year has its own theme. Naturewatch Foundation announced the 2026 theme in March 2026.' )
		. g_table( array( 'Year', 'Theme' ), array( array( '2022', 'Shared Planet' ), array( '2023', 'Great or small, love them all' ), array( '2024', 'The world is their home too' ), array( '2025', 'Save Animals, Save the Planet' ), array( '2026', 'See the World Through Their Eyes' ) ) )
		. g_h( 'World Animal Day in India' )
		. g_p( 'The ' . g_a( $L( 13800 ), 'Animal Welfare Board of India' ) . ' issued a circular on 2 October 2026 on the observance of World Animal Day on 4 October 2026. ' . g_a( $L( 142 ), 'Read the circular in the Library' ) . '.' )
		. g_p( 'You can mark the day where you live: ' . g_a( home_url( '/directory/' ), 'find an animal welfare group near you' ) . ', learn ' . g_a( $L( 127 ), 'how to report animal cruelty' ) . ', or read ' . g_a( $L( 7601 ), 'our guide to adopting a dog or cat' ) . '.' )
		. g_faq( array(
			array( 'When is World Animal Day?', 'Every year on 4 October, the feast day of Francis of Assisi, the patron saint of animals.' ),
			array( 'Who started World Animal Day?', 'Heinrich Zimmermann, a writer and publisher of the German magazine Mensch und Hund (Man and Dog). He organised the first World Animal Day on 24 March 1925 at the Sport Palace in Berlin.' ),
			array( 'Why was the first World Animal Day not on 4 October?', 'The venue was not free on 4 October. The day was held on 4 October for the first time in 1929.' ),
			array( 'Who coordinates World Animal Day now?', 'Since 2003, the UK animal welfare charity Naturewatch Foundation has coordinated the day.' ),
			array( 'What is the World Animal Day 2026 theme?', 'See the World Through Their Eyes.' ),
		) )
		. g_sources( array(
			array( 'World Animal Day: About us', 'https://www.worldanimalday.org/about-us/', 'read 15 Oct 2026' ),
			array( 'World Animal Day: 100 years of World Animal Day', 'https://www.worldanimalday.org/100-years-of-world-animal-day-a-global-celebration/', 'read 15 Oct 2026; 2025 theme' ),
			array( 'Naturewatch Foundation: Announcing the World Animal Day 2026 theme (19 March 2026)', 'https://naturewatch.org/the-world-animal-day-2026-theme/', 'read 15 Oct 2026' ),
			array( 'Wikipedia: World Animal Day', 'https://en.wikipedia.org/wiki/World_Animal_Day', 'read 15 Oct 2026; Sport Palace, the 1929 and 1931 dates, themes 2022 to 2024' ),
			array( 'Animal Welfare Board of India: Observance of World Animal Day on 4 October 2026 (circular)', $L( 142 ), 'our Library' ),
		) ),
);

/* 2. Animal Welfare Board of India (13800) */
$guides[13800] = array(
	'title'    => 'Animal Welfare Board of India (AWBI): what it does',
	'cats'     => array( 'policy-government', 'laws-rules' ),
	'primary'  => 'policy-government',
	'rm_title' => 'Animal Welfare Board of India Explained – Animal Writes',
	'focus'    => 'animal welfare board of india,awbi,awbi recognition',
	'desc'     => 'The Animal Welfare Board of India is a statutory body set up in 1962 under the PCA Act, 1960. It advises the government and recognises welfare groups.',
	'content'  => g_p( 'The Animal Welfare Board of India (AWBI) is a statutory advisory body on animal welfare. It was set up in 1962 under Section 4 of the ' . g_a( $L( 117 ), 'Prevention of Cruelty to Animals Act, 1960' ) . '. It comes under the Department of Animal Husbandry and Dairying in the Ministry of Fisheries, Animal Husbandry and Dairying.' )
		. g_p( 'Its mandate is to prevent unnecessary pain or suffering to animals, as the Act provides. Rukmini Devi Arundale helped set up the Board and was its first chair. Its headquarters moved from Chennai to Ballabhgarh, in Faridabad district of Haryana, in 2018.' )
		. g_h( 'What the Board does' )
		. g_ul( array(
			'Keeps the law on cruelty to animals under study and advises the government on changes to it, and on rules under the Act, especially for animals being transported, performing animals and animals kept in captivity.',
			'Advises on the design of vehicles, to lessen the burden on draught animals, and on slaughterhouses, so that unnecessary pain or suffering is avoided.',
			'Encourages shelters, rescue homes, sanctuaries and pinjrapoles, and gives financial and other help to animal welfare organisations and animal hospitals.',
			'Coordinates the work of animal welfare organisations.',
			'Promotes the humane treatment of animals through education, and advises the government on any animal welfare matter.',
		) )
		. g_p( 'For the laws the Board works under, read ' . g_a( $L( 7600 ), 'animal welfare laws in India, in plain words' ) . '.' )
		. g_h( 'Recognition of animal welfare organisations and gaushalas' )
		. g_p( 'The Board recognises animal welfare organisations (AWOs). An organisation applies with its registration certificate under the Societies Registration Act or a Trust Act, its audited accounts and annual reports for the last three years, two copies of its memorandum of association or bye-laws, and a report of its animal welfare work. Board members or authorised agencies then inspect it. If the inspection report is satisfactory, the organisation is considered for recognition.' )
		. g_p( 'Recognised organisations must renew their recognition every five years, only through the Board\'s online portal. A recognition more than five years old that has not been renewed is treated as invalid. Gaushalas with less than 10% milking animals do not pay the ₹5,000 renewal fee if they submit an undertaking in the prescribed format. On 28 September 2026 the Board gave ' . g_a( $L( 144 ), 'a final opportunity to renew' ) . '.' )
		. g_p( 'The Board publishes a list of recognised organisations, and a list of de-recognised, blacklisted or suspended ones, on its website. Find animal welfare organisations and gaushalas near you in the ' . g_a( home_url( '/directory/' ), 'Directory' ) . '.' )
		. g_h( 'Community dogs' )
		. g_p( 'The Board issued Standard Operating Procedures on community dogs on 27 November 2025. On 21 July 2026 it issued a circular asking for strict compliance with the Supreme Court\'s directions in the stray dogs case, those procedures and the ' . g_a( $L( 118 ), 'Animal Birth Control Rules, 2023' ) . '. ' . g_a( $L( 9561 ), 'Read the circular in the Library' ) . ', or read ' . g_a( $L( 9032 ), 'what the ABC Rules 2023 say about community dogs' ) . '.' )
		. g_h( 'Misuse of the Board\'s name' )
		. g_p( 'On 25 September 2026 the Board issued ' . g_a( $L( 145 ), 'a public notice warning that its name and authority were being misused' ) . '.' )
		. g_h( 'Contact' )
		. g_table( array( 'For', 'Details' ), array(
			array( 'Address', '42 Milestone, Delhi-Agra Highway, NH-2, Village Seekri, Ballabhgarh, Faridabad, Haryana 121004' ),
			array( 'Phone', '0129-2555700' ),
			array( 'Email', 'support@awbi.gov.in' ),
			array( 'Hours', 'Monday to Friday, 9:00 AM to 5:30 PM; closed on weekends and government holidays' ),
		) )
		. g_faq( array(
			array( 'What is the Animal Welfare Board of India?', 'A statutory advisory body on animal welfare, set up in 1962 under Section 4 of the Prevention of Cruelty to Animals Act, 1960. It comes under the Department of Animal Husbandry and Dairying.' ),
			array( 'Where is the Animal Welfare Board of India?', 'At 42 Milestone, Delhi-Agra Highway, NH-2, Village Seekri, Ballabhgarh, Faridabad, Haryana 121004. Its headquarters moved there from Chennai in 2018.' ),
			array( 'How does an organisation get AWBI recognition?', 'It applies with its registration certificate, audited accounts and annual reports for the last three years, its memorandum or bye-laws and a report of its animal welfare work. Board members or authorised agencies inspect it, and a satisfactory report leads to consideration for recognition.' ),
			array( 'How often must AWBI recognition be renewed?', 'Every five years, only through the Board\'s online portal. A recognition more than five years old that has not been renewed is treated as invalid.' ),
			array( 'What is the AWBI phone number?', '0129-2555700, Monday to Friday, 9:00 AM to 5:30 PM.' ),
		) )
		. $disc
		. g_sources( array(
			array( 'AWBI: Mandate and Functions', 'https://awbi.gov.in/view/index/Mandate-and-Functions', 'read 15 Oct 2026' ),
			array( 'AWBI: Recognition (with address, phone and hours)', 'https://awbi.gov.in/recognition', 'read 15 Oct 2026' ),
			array( 'Wikipedia: Animal Welfare Board of India', 'https://en.wikipedia.org/wiki/Animal_Welfare_Board_of_India', 'read 15 Oct 2026; 1962, Section 4, first chair, move to Ballabhgarh in 2018' ),
			array( 'The Prevention of Cruelty to Animals Act, 1960', $L( 117 ), 'our Library' ),
			array( 'AWBI: Renewal of Recognition, final opportunity (28 September 2026)', $L( 144 ), 'our Library' ),
			array( 'AWBI: Public notice on misuse of its name (25 September 2026)', $L( 145 ), 'our Library' ),
			array( 'AWBI: circular of 21 July 2026 on compliance with the Supreme Court\'s directions, the SOPs of 27 November 2025 and the ABC Rules, 2023', $L( 9561 ), 'our Library' ),
		) ),
);

/* 3. 1962 helpline and Mobile Veterinary Units (13798) */
$guides[13798] = array(
	'title'    => '1962 helpline: Mobile Veterinary Units explained',
	'cats'     => array( 'veterinary-care', 'disaster-rescue' ),
	'primary'  => 'veterinary-care',
	'rm_title' => '1962 Helpline for Mobile Veterinary Units – Animal Writes',
	'focus'    => '1962 helpline,mobile veterinary unit,1962 animal ambulance',
	'desc'     => 'The 1962 helpline is a toll-free number for Mobile Veterinary Units, which bring a vet to sick or injured animals. Over 4,000 run in 29 states and UTs.',
	'content'  => g_p( '1962 is a toll-free number for animals that need a vet. Calls go to a call centre, which can send a Mobile Veterinary Unit (MVU): a vehicle fitted out for diagnosis, treatment and minor surgery.' )
		. g_h( 'How a call works' )
		. g_p( 'The call centre passes the call to a veterinary doctor, who decides whether to send an MVU based on how urgent the case is. The call centre confirms the service with the animal owner\'s ID and mobile number and shares the record with the state.' )
		. g_h( 'Who runs it' )
		. g_p( 'The Government of India funds MVUs for states and union territories under its Livestock Health and Disease Control programme, at one MVU for every one lakh livestock. The government describes the service as veterinary care at the farmer\'s doorstep, for farmers and dairy owners.' )
		. g_p( 'As of August 2026, 4,019 MVUs were running in 29 states and union territories. They had served 136 lakh farmers and treated 276 lakh animals.' )
		. g_h( 'Is 1962 free?' )
		. g_p( 'The call is toll-free. What a unit charges depends on the state: in Tamil Nadu, the vet and paravet on board give emergency care free of cost. Check your state\'s animal husbandry department for its terms.' )
		. g_h( 'Two states as examples' )
		. g_table( array( 'State', 'What its service says' ), array(
			array( 'Tamil Nadu', 'Launched in 2019. A 24-hour call centre on 1962 sends 32 animal ambulances, which run every day from 8:00 AM to 8:00 PM in rural and urban areas. Serious cases are stabilised and referred to the nearest veterinary institution.' ),
			array( 'Karnataka', '290 Mobile Veterinary Units were sanctioned in 2022, run through a public-private partnership. Each has a veterinary doctor, a livestock inspector and a driver-cum-attendant.' ),
		) )
		. g_h( 'If 1962 does not answer in your area' )
		. g_p( 'Not every state runs the service in the same way. See ' . g_a( $L( 9031 ), 'who to call for an animal emergency in India' ) . ' and ' . g_a( $L( 9030 ), 'who to call first for an injured animal' ) . ', or find a government vet or rescue group near you in the ' . g_a( home_url( '/directory/' ), 'Directory' ) . '. While you wait, follow ' . g_a( $L( 126 ), 'how to stay safe and help an injured animal' ) . '.' )
		. g_faq( array(
			array( 'What is the 1962 helpline?', 'A toll-free number for animals that need a vet. Calls go to a call centre, which can send a Mobile Veterinary Unit fitted out for diagnosis, treatment and minor surgery.' ),
			array( 'Is 1962 free?', 'The call is toll-free. What a unit charges depends on the state: in Tamil Nadu, the vet and paravet on board give emergency care free of cost.' ),
			array( 'Who decides whether a Mobile Veterinary Unit comes?', 'A veterinary doctor at the call centre, based on how urgent the case is.' ),
			array( 'How many Mobile Veterinary Units are there?', 'As of August 2026, 4,019 MVUs were running in 29 states and union territories.' ),
		) )
		. $disc
		. g_sources( array(
			array( 'PIB, 8 May 2022: Union Minister inaugurates Mobile Veterinary Units (Karnataka; how the call centre works; 1 MVU per lakh livestock)', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=1823690', 'read 15 Oct 2026' ),
			array( 'PIB Backgrounder, 22 Aug 2026: National Animal Disease Control Programme (4,019 MVUs in 29 States and UTs)', 'https://www.pib.gov.in/PressReleaseIframePage.aspx?PRID=2302242', 'read 15 Oct 2026' ),
			array( 'Tamil Nadu Animal Husbandry and Veterinary Services: Animal Mobile Medical Ambulance', 'https://ahd.tn.gov.in/AnimalAmbulance.html', 'read 15 Oct 2026' ),
		) ),
);

/* Pass 2: content and SEO fields. */
foreach ( $guides as $id => $d ) {
	$t = mb_strlen( $d['rm_title'] );
	$dl = mb_strlen( $d['desc'] );
	if ( $t < 55 || $t > 58 || $dl > 155 || preg_match( '/[:,&]/', str_replace( ' – Animal Writes', '', $d['rm_title'] ) ) || false !== stripos( $d['content'], 'DRAFT for owner' ) ) {
		$out[] = "CHECK FAILED $id t=$t d=$dl";
		continue;
	}
	if ( ! $dry ) {
		$r = wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $d['title'], 'post_excerpt' => $d['desc'], 'post_content' => $d['content'] ) ), true );
		if ( is_wp_error( $r ) ) {
			$out[] = "ERR $id " . $r->get_error_message();
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
		update_post_meta( $id, '_aw_owner_draft', '2026-10-15 Phase 8; owner approved; final source check 15 Oct 2026; published ' . $now );
		clean_post_cache( $id );
	}
	$out[] = "$id | " . get_post_status( $id ) . ' | ' . get_permalink( $id ) . " | t=$t d=$dl";
}

/* The two emergency guides: drop the 1962 secondaries and link to the 1962 guide. */
$drop = array( '1962 helpline', '1962 mobile veterinary unit' );
$link = '<!-- wp:paragraph -->' . "\n" . '<p>More on the 1962 service: ' . g_a( $L( 13798 ), '1962 helpline and Mobile Veterinary Units explained' ) . '.</p>' . "\n" . '<!-- /wp:paragraph -->';
foreach ( array( 9030, 9031 ) as $id ) {
	$kw  = array_map( 'trim', explode( ',', (string) get_post_meta( $id, 'rank_math_focus_keyword', true ) ) );
	$new = array_values( array_filter( $kw, fn( $k ) => '' !== $k && ! in_array( strtolower( $k ), $drop, true ) ) );
	$c   = get_post_field( 'post_content', $id );
	$has = false !== strpos( $c, '1962-helpline-mobile-veterinary-units' );
	if ( ! $has ) {
		// Before the Questions and answers heading.
		$pos = strpos( $c, '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">Questions and answers</h2>' );
		$c   = false === $pos ? $c . "\n\n" . $link : substr( $c, 0, $pos ) . $link . "\n\n" . substr( $c, $pos );
	}
	if ( ! $dry ) {
		update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( implode( ',', $new ) ) );
		if ( ! $has ) {
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $c ) ) );
		}
		clean_post_cache( $id );
	}
	$out[] = "$id keywords: " . implode( ',', $kw ) . ' -> ' . implode( ',', $new ) . ( $has ? ' (link already there)' : ' (link added)' );
}
echo implode( "\n", $out ), "\n";
