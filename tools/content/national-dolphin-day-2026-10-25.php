<?php
/**
 * Publishes the owner-approved National Dolphin Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: PIB Mar 2022 (NBWL Standing Committee, 5 October), PIB 3 Mar 2025 (6,327 river dolphins), PIB 6 Oct 2025 (Project Dolphin Phase II).
 *
 * Usage: wp eval-file national-dolphin-day-2026-10-25.php [dry]
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

$ww = $L( '/resources/wildlife-week/' );
$days = $L( '/resources/animal-days-in-india/' );
$cat = get_term_by( 'name', 'Wildlife', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Wildlife';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'national-dolphin-day',
	'title'    => 'National Dolphin Day: 5 October, and India\'s river dolphins',
	'rm_title' => 'National Dolphin Day 5 October in India – Animal Writes',
	'desc'     => 'National Dolphin Day is on 5 October, set in 2022. India counted 6,327 river dolphins in its first survey; why they matter and how to help.',
	'focus'    => 'national dolphin day,gangetic dolphin,river dolphins india',
	'content'  => g_p( 'National Dolphin Day is marked in India every year on 5 October. The Standing Committee of the National Board for Wildlife chose the date in 2022, to raise awareness and bring people into dolphin conservation.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Date' ),
			array(
				array( '2026', 'Monday, 5 October' ),
				array( '2027', 'Tuesday, 5 October' ),
			)
		)
		. g_h( 'India\'s dolphins' )
		. g_ul(
			array(
				'<strong>6,327 river dolphins</strong> in India\'s first-ever river dolphin estimate, released by the Prime Minister on 3 March 2025. The survey covered 28 rivers in eight states; Uttar Pradesh had the most, followed by Bihar, West Bengal and Assam.',
				'The Gangetic river dolphin is India\'s national aquatic animal. Dolphins are a sign of a healthy river.',
				'<strong>Project Dolphin</strong> covers river and marine dolphins; its second phase was launched in Wildlife Week 2025.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Don\'t throw plastic or waste into rivers</strong>, and don\'t use fishing nets that trap dolphins.',
				'<strong>Report a stranded or dead dolphin</strong> to the forest department.',
			)
		)
		. g_faq(
			array(
				array( 'When is National Dolphin Day?', 'Every year on 5 October, since 2022.' ),
				array( 'How many river dolphins does India have?', '6,327, according to the first river dolphin estimate (2025).' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guides: ' . g_a( $ww, 'Wildlife Week' ) . '; ' . g_a( $days, 'Animal days in India' ),
				'Category: ' . g_a( $catl, 'Wildlife' ),
			)
		)
		. g_sources(
			array(
				array( 'Standing Committee of the National Board for Wild Life recommends to celebrate October 5 as National Dolphin Day', 'https://www.pib.gov.in/PressReleseDetailm.aspx?PRID=1809800', 'PIB, March 2022' ),
				array( 'PM chairs the 7th meeting of National Board for Wildlife in Gir', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2107778', 'PIB, 3 Mar 2025: 6,327 dolphins' ),
				array( 'Wildlife Week 2025 at Dehradun', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2175490', 'PIB, 6 Oct 2025: Project Dolphin Phase II' ),
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
