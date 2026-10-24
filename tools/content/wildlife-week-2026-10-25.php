<?php
/**
 * Publishes the owner-approved Wildlife Week guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026 (PIB, Ministry of Environment, Forest and Climate Change): Wildlife Week 2025 at Dehradun
 * (6 Oct 2025, theme, projects), Namo Van at Manesar (2025, "every year from October 2 to 8"), National Zoological Park
 * 69th Wildlife Week (2 Oct 2023, theme, Gandhi Jayanti and ahimsa). The brief's 1-7 October was corrected to 2-8.
 *
 * Usage: wp eval-file wildlife-week-2026-10-25.php [dry]
 * Creates the post on the first run; a later run updates the same post (found by its slug) and keeps its date.
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

$n1  = $L( '/news/odisha-nwcso-celebrates-72nd-wildlife-week-at-nandankanan/' );
$n2  = $L( '/news/manipur-launches-clouded-leopard-vulture-conservation-projects-on-72nd-wildlife-week/' );
$n3  = $L( '/news/tiger-translocation-sanctuary-expansion-plans-mark-wildlife-week-culmination-in-kota/' );
$n4  = $L( '/news/wildlife-week-at-vizag-zoo-workshops-competitions-and-conservation-activities/' );
$dir = home_url( '/directory/?tag=wildlife-rescue' );
$cat = get_term_by( 'name', 'Wildlife', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Wildlife';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'wildlife-week',
	'title'    => 'Wildlife Week in India: 2 to 8 October, and why it starts on Gandhi Jayanti',
	'rm_title' => 'Wildlife Week India 2026 Dates and Theme – Animal Writes',
	'desc'     => 'Wildlife Week is marked in India every year from 2 to 8 October, starting on Gandhi Jayanti. What it is, recent themes, and how to take part.',
	'focus'    => 'wildlife week,wildlife week 2026,national wildlife week india',
	'content'  => g_p( 'Wildlife Week is marked in India every year from 2 to 8 October. The Ministry of Environment, Forest and Climate Change, state forest departments, zoos and conservation groups use the week to raise awareness about wildlife conservation.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Dates', 'Week', 'Theme' ),
			array(
				array( '2026', '2 to 8 October', '72nd', 'No national theme published by the Ministry as of 25 Oct 2026 (some events used "Connecting Habitats, Thriving Wildlife")' ),
				array( '2027', '2 to 8 October', '73rd', 'Not announced yet' ),
			)
		)
		. g_h( 'Why 2 October' )
		. g_p( 'The week starts on the birth anniversary of Mahatma Gandhi. In its 2023 release, the Ministry says the week is held "to coincide with the birth anniversary of Mahatma Gandhi and to follow his principles of Ahinsa and Compassion for all living beings".' )
		. g_h( 'Recent themes' )
		. g_table(
			array( 'Year', 'Week', 'Theme', 'Source' ),
			array(
				array( '2025', '71st', 'Human-Wildlife coexistence', 'PIB, 6 Oct 2025' ),
				array( '2023', '69th', 'Partnerships for wildlife conservation', 'PIB, 2 Oct 2023' ),
			)
		)
		. g_h( 'What India does in the week' )
		. g_ul(
			array(
				'<strong>The national event:</strong> in 2025 the Union Environment Minister led the main event at the FRI campus in Dehradun on 6 October, with the Wildlife Institute of India, ICFRE, IGNFA and FRI. Five national projects were launched there, including Project Dolphin (Phase II), Project Sloth Bear, Project Gharial and a Centre of Excellence for Human–Wildlife Conflict Management, and a Tigers Outside Tiger Reserve project.',
				'<strong>Zoos and forest departments:</strong> zoos such as the National Zoological Park in Delhi hold talks, street plays, competitions and pledges for students. State forest departments run awareness drives and nature walks.',
				'<strong>In 2026:</strong> see the news below, from Odisha (Nandankanan), Manipur, Rajasthan (Kota), Andhra Pradesh (Vizag zoo) and others.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Visit a zoo, sanctuary or nature walk</strong> during the week; most run events for students and families.',
				'<strong>Report injured wild animals</strong> to the state forest department or a wildlife rescue group, and don\'t try to handle them yourself. Find one in the Directory: ' . g_a( $dir, 'Wildlife Rescue organisations' ) . '.',
				'<strong>Don\'t buy wildlife products</strong> (skins, ivory, shells, birds as pets): trade in protected species is an offence under the Wildlife (Protection) Act, 1972.',
				'<strong>Read and share</strong> wildlife news and laws from the ' . g_a( $catl, 'Wildlife category' ) . '.',
			)
		)
		. g_faq(
			array(
				array( 'When is Wildlife Week in India?', 'Every year from 2 to 8 October. The next one is 2 to 8 October 2027.' ),
				array( 'Why does Wildlife Week start on 2 October?', 'It starts on Gandhi Jayanti, to follow Mahatma Gandhi\'s principles of ahimsa and compassion for all living beings (Ministry of Environment, Forest and Climate Change).' ),
				array( 'What was the theme of Wildlife Week 2025?', '"Human-Wildlife coexistence".' ),
				array( 'Is Wildlife Week the same as World Wildlife Day?', 'No. World Wildlife Day is a UN day on 3 March. Wildlife Week is India\'s own week, 2 to 8 October.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'News (2026): ' . g_a( $n1, 'Odisha: NWCSO celebrates 72nd Wildlife Week at Nandankanan' ) . '; ' . g_a( $n2, 'Manipur launches clouded leopard, vulture conservation projects on 72nd Wildlife Week' ) . '; ' . g_a( $n3, 'Tiger translocation, sanctuary expansion plans mark Wildlife Week culmination in Kota' ) . '; ' . g_a( $n4, 'Wildlife week at Vizag zoo' ),
				'Category: ' . g_a( $catl, 'Wildlife' ),
				'Directory: ' . g_a( $dir, 'Wildlife Rescue organisations' ),
			)
		)
		. g_sources(
			array(
				array( 'Wildlife Week 2025 celebrations at Dehradun, themed "Human-Wildlife coexistence"', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2175490', 'PIB, Ministry of Environment, Forest and Climate Change, 6 Oct 2025' ),
				array( 'Namo Van at Manesar, to mark Wildlife Week 2025', 'https://www.pib.gov.in/PressReleseDetailm.aspx?PRID=2174052', 'PIB, 2025: observed every year from October 2 to 8' ),
				array( 'National Zoological Park begins 69th Wildlife Week, "Partnerships for wildlife conservation"', 'https://www.pib.gov.in/PressReleaseIframePage.aspx?PRID=1963487', 'PIB, 2 Oct 2023: Gandhi Jayanti, ahimsa' ),
			)
		),
);

$t  = mb_strlen( $d['rm_title'] );
$dl = mb_strlen( $d['desc'] );
if ( $t < 55 || $t > 58 || $dl > 155 || preg_match( '/[:,&]/', str_replace( ' – Animal Writes', '', $d['rm_title'] ) ) ) {
	WP_CLI::error( "Title or description check failed: t=$t d=$dl" );
}

$existing = get_posts(
	array(
		'name'        => $d['slug'],
		'post_type'   => 'post',
		'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
		'numberposts' => 1,
		'fields'      => 'ids',
	)
);
$id       = $existing ? (int) $existing[0] : 0;
if ( $dry ) {
	WP_CLI::log( ( $id ? "would update $id" : 'would create' ) . " | t=$t d=$dl | " . strlen( $d['content'] ) . ' bytes' );
	return;
}
$post = array(
	'post_type'    => 'post',
	'post_name'    => $d['slug'],
	'post_title'   => $d['title'],
	'post_excerpt' => $d['desc'],
	'post_content' => $d['content'],
	'post_status'  => 'publish',
);
if ( $id ) {
	$post['ID'] = $id;
	$r          = wp_update_post( wp_slash( $post ), true );
} else {
	$r = wp_insert_post( wp_slash( $post ), true );
}
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
$id = (int) $r;
wp_set_object_terms( $id, array( (int) $cat->term_id ), 'category' );
wp_set_object_terms( $id, array( 'guide' ), 'post_tag' );
update_post_meta( $id, 'rank_math_title', wp_slash( $d['rm_title'] ) );
update_post_meta( $id, 'rank_math_description', wp_slash( $d['desc'] ) );
update_post_meta( $id, 'rank_math_focus_keyword', wp_slash( $d['focus'] ) );
update_post_meta( $id, 'rank_math_primary_category', (int) $cat->term_id );
update_post_meta( $id, '_aw_owner_draft', 'Observance-day page; owner approved 25 Oct 2026; sources checked 25 Oct 2026 (PIB)' );
clean_post_cache( $id );
WP_CLI::success( "$id | " . get_post_status( $id ) . ' | ' . get_permalink( $id ) . " | t=$t d=$dl" );
