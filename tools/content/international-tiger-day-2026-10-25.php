<?php
/**
 * Publishes the owner-approved International Tiger Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: PIB 29 Jul 2026 (Global Tiger Day 2026, 3682 tigers, 58 reserves), PIB backgrounder Jul 2024 (St Petersburg 2010).
 *
 * Usage: wp eval-file international-tiger-day-2026-10-25.php [dry]
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
	'slug'     => 'international-tiger-day',
	'title'    => 'International Tiger Day: 29 July, and India\'s tigers',
	'rm_title' => 'International Tiger Day 29 July and India – Animal Writes',
	'desc'     => 'International Tiger Day is on 29 July. How it began in 2010, India\'s 3,682 tigers and 58 tiger reserves, and what happens on the day.',
	'focus'    => 'international tiger day,global tiger day,tiger day 2027',
	'content'  => g_p( 'International Tiger Day, also called Global Tiger Day, is marked every year on 29 July. In India the National Tiger Conservation Authority (NTCA) leads the day.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Date' ),
			array(
				array( '2026', 'Wednesday, 29 July' ),
				array( '2027', 'Thursday, 29 July' ),
			)
		)
		. g_h( 'How it began' )
		. g_p( 'The tiger range countries agreed on the day at the St Petersburg tiger summit on 29 July 2010, the first time national leaders met about the conservation of a single species.' )
		. g_h( 'India\'s tigers' )
		. g_ul(
			array(
				'<strong>3,682 tigers</strong> (All India Tiger Estimation 2022) and <strong>58 tiger reserves</strong>, as the Union Environment Minister said on Global Tiger Day 2026.',
				'The next All India Tiger Estimation is under way in 2026.',
			)
		)
		. g_h( 'What India did on the day in 2026' )
		. g_p( 'The NTCA held the national event in New Delhi. The Union Environment Minister launched an NTCA eco-tourism webpage linking the official websites of all tiger reserves for safari and stay bookings, and NTCA Awards were given for tiger conservation.' )
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Book safaris only through official tiger reserve websites</strong>, and follow park rules.',
				'<strong>Report poaching or wildlife trade</strong> to the forest department.',
			)
		)
		. g_faq(
			array(
				array( 'When is International Tiger Day?', 'Every year on 29 July.' ),
				array( 'How many tigers does India have?', '3,682, according to the All India Tiger Estimation 2022.' ),
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
				array( 'Global Tiger Day 2026 in New Delhi', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2290935', 'PIB, Ministry of Environment, Forest and Climate Change, 29 Jul 2026' ),
				array( 'International Tiger Day backgrounder', 'https://static.pib.gov.in/WriteReadData/specificdocs/documents/2024/jul/doc2024728356101.pdf', 'PIB, July 2024: St Petersburg, 29 July 2010' ),
				array( 'NTCA eco-tourism page', 'https://ntca.gov.in/eco-tourism/', 'National Tiger Conservation Authority' ),
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
