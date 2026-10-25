<?php
/**
 * Publishes the owner-approved World Zoonoses Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: PIB, Ministry of Health and Family Welfare, 6 Jul 2026 (national consultation, learning resource package).
 *
 * Usage: wp eval-file world-zoonoses-day-2026-10-25.php [dry]
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

$rab = $L( '/resources/world-rabies-day/' );
$bite = $L( '/resources/dog-bite-rabies-vaccine/' );
$days = $L( '/resources/animal-days-in-india/' );
$cat = get_term_by( 'name', 'Rabies and Public Health', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Rabies and Public Health';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'world-zoonoses-day',
	'title'    => 'World Zoonoses Day: 6 July, and diseases that pass between animals and people',
	'rm_title' => 'World Zoonoses Day 6 July and One Health – Animal Writes',
	'desc'     => 'World Zoonoses Day is on 6 July, the day Louis Pasteur first gave his rabies vaccine. What zoonoses are and what India did on the day in 2026.',
	'focus'    => 'world zoonoses day,world zoonoses day 2027,zoonotic diseases india',
	'content'  => g_p( 'World Zoonoses Day is marked every year on 6 July. Zoonoses are diseases that pass between animals and people, such as rabies. The day marks 6 July 1885, when Louis Pasteur first gave his rabies vaccine to a person.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Date' ),
			array(
				array( '2026', 'Monday, 6 July' ),
				array( '2027', 'Tuesday, 6 July' ),
			)
		)
		. g_h( 'What India did on the day in 2026' )
		. g_ul(
			array(
				'<strong>A national plan:</strong> the Union Health Ministry held a national consultation in New Delhi on the draft National Action Plan for Prevention and Control of Zoonoses, built on the One Health approach (human, animal and environmental health together). The plan will also guide states and union territories in making their own plans.',
				'<strong>Training material:</strong> the ministry launched a Learning Resource Package on 10 priority zoonotic diseases (anthrax, brucellosis, Kyasanur Forest Disease, Crimean-Congo haemorrhagic fever, Nipah, mpox, rabies, leptospirosis, scrub typhus and Zika) and a set of e-learning modules.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Vaccinate your dog against rabies</strong> every year.',
				'<strong>After an animal bite or scratch</strong>, wash it with soap and running water for 15 minutes and see a doctor the same day. Step-by-step help: ' . g_a( $bite, 'What to do after a dog bite in India' ) . '.',
				'<strong>Wash your hands</strong> after handling animals, their food or their waste.',
			)
		)
		. g_faq(
			array(
				array( 'When is World Zoonoses Day?', 'Every year on 6 July.' ),
				array( 'Why is World Zoonoses Day on 6 July?', 'On 6 July 1885 Louis Pasteur first gave his rabies vaccine to a person.' ),
				array( 'What is a zoonotic disease?', 'A disease that passes between animals and people, such as rabies, anthrax or leptospirosis.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guides: ' . g_a( $rab, 'World Rabies Day' ) . '; ' . g_a( $bite, 'What to do after a dog bite in India' ) . '; ' . g_a( $days, 'Animal days in India' ),
				'Category: ' . g_a( $catl, 'Rabies and Public Health' ),
			)
		)
		. g_sources(
			array(
				array( 'National Multistakeholder Consultation on the National Action Plan for Prevention and Control of Zoonoses', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2281860', 'PIB, Ministry of Health and Family Welfare, 6 Jul 2026' ),
				array( 'Increasing public awareness on World Zoonoses Day', 'https://www.pib.gov.in/PressReleseDetailm.aspx?PRID=2031381', 'PIB' ),
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
update_post_meta( $id, '_aw_owner_draft', 'Observance-day page; owner approved 25 Oct 2026; sources checked 25 Oct 2026' );
clean_post_cache( $id );
WP_CLI::success( "$id | " . get_post_status( $id ) . ' | ' . get_permalink( $id ) . " | t=$t d=$dl" );
