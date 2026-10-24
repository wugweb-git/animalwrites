<?php
/**
 * Publishes the owner-approved World Rabies Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: GARC (day, 2026 theme), WHO campaign page (28 September, Louis Pasteur), WHO rabies fact
 * sheet (deaths, dogs, first aid, fatality), WHO India release of 25 Oct 2021 (NAPRE, 36%, notifiable), WOAH event page,
 * and the AWBI circular of 22 Sep 2026 in the Library.
 *
 * Usage: wp eval-file world-rabies-day-2026-10-25.php [dry]
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

$guide = $L( '/resources/dog-bite-rabies-vaccine/' );
$awbi  = $L( '/library/observance-of-world-rabies-day-28-september-2026/' );
$news1 = $L( '/news/states-local-bodies-advised-to-observe-world-rabies-day-on-28th-september/' );
$news2 = $L( '/news/world-rabies-day-celebrated-at-kcvas-with-awareness-drive-and-vaccination-camp/' );
$cat   = get_term_by( 'name', 'Rabies and Public Health', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Rabies and Public Health';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}

$d = array(
	'slug'     => 'world-rabies-day',
	'title'    => 'World Rabies Day: 28 September, and what India does on it',
	'rm_title' => 'World Rabies Day 2026 Date Theme and India – Animal Writes',
	'desc'     => 'World Rabies Day is on 28 September. The 2026 theme, why the date honours Louis Pasteur, and what India does to end dog-bite rabies deaths by 2030.',
	'focus'    => 'world rabies day,world rabies day 2026,world rabies day theme',
	'content'  => g_p( 'World Rabies Day is on 28 September every year. It raises awareness that rabies can be prevented, and pushes for the goal of no human deaths from dog-bite rabies by 2030.' )
		. g_h( 'This year' )
		. g_table( array( 'Year', 'Date', 'Theme' ), array( array( '2026', 'Monday, 28 September', 'Stronger Together' ), array( '2027', 'Tuesday, 28 September', 'Not announced yet' ) ) )
		. g_p( 'The Global Alliance for Rabies Control says the 2026 theme means that "rabies elimination is only possible when we work together".' )
		. g_h( 'Why 28 September' )
		. g_p( '28 September is the anniversary of the death of Louis Pasteur. Pasteur was the French scientist who developed the first rabies vaccine. The Global Alliance for Rabies Control coordinates the day, and it has been marked since 2007.' )
		. g_h( 'Why it matters' )
		. g_ul(
			array(
				'Rabies kills about 44,000 people a year worldwide, and this is likely an undercount (WHO).',
				'In up to 99% of human cases, a dog passed on the virus (WHO).',
				'Once symptoms appear, rabies is "virtually 100% fatal" (WHO). It can be prevented if the right care starts in time after a bite.',
				'India has about 36% of the world\'s human deaths from dog-bite rabies (WHO India, 2021).',
			)
		)
		. g_h( 'What India does on the day' )
		. g_ul(
			array(
				'<strong>The national plan:</strong> On World Rabies Day 2021, India launched the National Action Plan for Dog Mediated Rabies Elimination by 2030 (NAPRE). Its goal is zero human deaths from dog-bite rabies by 2030. The Health Ministry runs it with the Ministry of Fisheries, Animal Husbandry and Dairying.',
				'<strong>Notifiable disease:</strong> Rabies has been declared a notifiable disease. Health professionals must report cases to the authorities.',
				'<strong>AWBI:</strong> The Animal Welfare Board of India issues a circular each year asking for the day to be observed. In 2026 it asked local bodies, departments and animal welfare organisations to hold anti-rabies vaccination camps for community and pet dogs. See ' . g_a( $awbi, 'Observance of World Rabies Day – 28 Sep 2026' ) . ' in the Library.',
				'<strong>On the ground:</strong> veterinary colleges, schools and health departments hold awareness talks and dog vaccination camps. See the news below.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>After any dog or animal bite:</strong> wash the wound with soap and running water for at least 15 minutes, then go to a health centre the same day for the rabies vaccine. Step-by-step help: ' . g_a( $guide, 'What to do after a dog bite in India' ) . '.',
				'<strong>Vaccinate your dog</strong> every year, and ask your local body about vaccination for community dogs.',
				'<strong>Join or support a vaccination camp</strong> near you. Find animal welfare organisations and government veterinary hospitals in the ' . g_a( home_url( '/directory/' ), 'Directory' ) . '.',
				'<strong>Share correct information:</strong> no home remedies on a bite wound, and finish the full vaccine course.',
			)
		)
		. g_faq(
			array(
				array( 'When is World Rabies Day?', 'Every year on 28 September. The next one is on Tuesday, 28 September 2027.' ),
				array( 'What is the theme of World Rabies Day 2026?', '"Stronger Together", set by the Global Alliance for Rabies Control.' ),
				array( 'Why is World Rabies Day on 28 September?', 'It is the anniversary of the death of Louis Pasteur, who developed the first rabies vaccine.' ),
				array( 'What should I do right after a dog bite?', 'Wash the wound with soap and running water for at least 15 minutes, then get medical care the same day. Rabies vaccine after a bite prevents the disease.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guide: ' . g_a( $guide, 'What to do after a dog bite in India' ),
				'Library: ' . g_a( $awbi, 'Observance of World Rabies Day – 28 Sep 2026' ) . ' (AWBI)',
				'News: ' . g_a( $news1, 'States, local bodies advised to observe World Rabies Day on 28th September' ) . '; ' . g_a( $news2, 'World Rabies Day celebrated at KCVAS with awareness drive and vaccination camp' ),
				'Category: ' . g_a( (string) get_term_link( $cat ), 'Rabies and Public Health' ),
				'Directory: ' . g_a( home_url( '/directory/' ), 'government veterinary hospitals and animal welfare organisations' ),
			)
		)
		. g_sources(
			array(
				array( 'World Rabies Day', 'https://rabiesalliance.org/world-rabies-day', 'Global Alliance for Rabies Control: the day and the 2026 theme' ),
				array( 'World Rabies Day campaign', 'https://www.who.int/campaigns/world-rabies-day', 'WHO: 28 September and Louis Pasteur' ),
				array( 'Rabies fact sheet', 'https://www.who.int/news-room/fact-sheets/detail/rabies', 'WHO: deaths, dogs, first aid, fatality' ),
				array( 'India launches new National Action Plan for dog mediated rabies elimination by 2030', 'https://www.who.int/news/item/25-10-2021-adopt-one-health-stop-rabies-india-launches-new-national-action-plan-for-dog-mediated-rabies-elimination-by-2030', 'WHO India, 25 Oct 2021: NAPRE, 36%, notifiable disease' ),
				array( 'World Rabies Day 2026: Stronger Together', 'https://www.woah.org/en/event/world-rabies-day-2026-stronger-together/', 'WOAH' ),
			)
		)
		. "<!-- wp:paragraph {\"className\":\"aw-guide-disclaimer\"} -->\n<p class=\"aw-guide-disclaimer\">This guide is general information, not medical advice. After a bite, see a doctor.</p>\n<!-- /wp:paragraph -->\n\n",
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
