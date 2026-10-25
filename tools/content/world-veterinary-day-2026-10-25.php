<?php
/**
 * Publishes the owner-approved World Veterinary Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: World Veterinary Association (2026 theme, 25 Apr 2026), ICAR-IVRI, GADVASU.
 *
 * Usage: wp eval-file world-veterinary-day-2026-10-25.php [dry]
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

$days = $L( '/resources/animal-days-in-india/' );
$dir = home_url( '/directory/' );
$cat = get_term_by( 'name', 'Veterinary Care', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Veterinary Care';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'world-veterinary-day',
	'title'    => 'World Veterinary Day: the last Saturday of April',
	'rm_title' => 'World Veterinary Day 2027 Date and Theme – Animal Writes',
	'desc'     => 'World Veterinary Day is on the last Saturday of April, 24 April in 2027. The 2026 theme, who marks it, and how India celebrates its vets.',
	'focus'    => 'world veterinary day,world veterinary day 2027,world veterinary day theme',
	'content'  => g_p( 'World Veterinary Day is marked every year on the last Saturday of April. The World Veterinary Association uses it to recognise the work of vets for animal health, public health and food safety.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Date', 'Theme' ),
			array(
				array( '2026', 'Saturday, 25 April', 'Veterinarians: Guardians of Food and Health' ),
				array( '2027', 'Saturday, 24 April', 'Not announced yet' ),
			)
		)
		. g_h( 'What India does on the day' )
		. g_p( 'Veterinary universities and colleges hold seminars, student competitions, and free anti-rabies vaccination and deworming camps. In 2026, for example, ICAR-IVRI Mukteswar held a three-day programme (25 to 27 April) and Guru Angad Dev Veterinary and Animal Sciences University, Ludhiana, ran a vaccination and deworming camp.' )
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Take your animal for its yearly check-up</strong> and rabies vaccine.',
				'<strong>Find a government veterinary hospital</strong> near you in the ' . g_a( $dir, 'Directory' ) . '.',
			)
		)
		. g_faq(
			array(
				array( 'When is World Veterinary Day?', 'On the last Saturday of April every year. In 2027 that is 24 April.' ),
				array( 'What was the theme of World Veterinary Day 2026?', '"Veterinarians: Guardians of Food and Health".' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guide: ' . g_a( $days, 'Animal days in India' ) . ' (the full list)',
				'Category: ' . g_a( $catl, 'Veterinary Care' ),
				'Directory: ' . g_a( $dir, 'government veterinary hospitals' ),
			)
		)
		. g_sources(
			array(
				array( 'WVA theme for World Veterinary Day 2026', 'https://worldvet.org/news/wva-theme-for-world-veterinary-day-2026-veterinarians-guardians-of-food-and-health/', 'World Veterinary Association' ),
				array( 'Celebration of World Veterinary Day', 'https://www.ivri.nic.in/News/Celebration%20of%20World%20Veterinary%20Day.pdf', 'ICAR-IVRI' ),
				array( 'GADVASU news', 'https://www.gadvasu.in/news/13203', 'Ludhiana, 25 Apr 2026' ),
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
