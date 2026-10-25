<?php
/**
 * Publishes the owner-approved Animal Welfare Fortnight guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year. Run after
 * animal-days-in-india-2026-10-25.php: it links that page and stops when it is missing.
 * Sources checked 25 Oct 2026: AWBI annual report 2024-25 (14 to 30 January), Deccan Herald 2010 (started 1964 by Rukmini
 * Devi Arundale), Andaman Sheekha (2026 launch), Ministry of Fisheries, Animal Husbandry and Dairying Rajya Sabha answer
 * (circulars for awareness programmes), our Library copy of AWBI's 11 Jan 2026 message.
 *
 * Usage: wp eval-file animal-welfare-fortnight-2026-10-25.php [dry]
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

$msg  = $L( '/library/message-to-celebrate-animal-welfare-fortnight-2026-and-jiv-jantu-kalyan-diwas-on-the-occasion-of-vasant-panchami-dated-11-01-2026/' );
$days = $L( '/resources/animal-days-in-india/' );
$bite = $L( '/resources/dog-bite-rabies-vaccine/' );
$dir  = home_url( '/directory/' );
$cat  = get_term_by( 'name', 'Campaigns and Civic Action', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Campaigns and Civic Action';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'animal-welfare-fortnight',
	'title'    => 'Animal Welfare Fortnight: 14 to 30 January, called by the Animal Welfare Board of India',
	'rm_title' => 'AWBI Animal Welfare Fortnight 2027 Dates – Animal Writes',
	'desc'     => 'Animal Welfare Fortnight runs every year from 14 to 30 January, called by the Animal Welfare Board of India. What it is and how to take part.',
	'focus'    => 'animal welfare fortnight,animal welfare fortnight 2027,pashu kalyan pakhwada',
	'content'  => g_p( 'Animal Welfare Fortnight is marked in India every year from 14 to 30 January. The Animal Welfare Board of India (AWBI) asks state governments, local bodies, animal welfare organisations and the public to use the fortnight to spread kindness to animals.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Dates', 'AWBI message' ),
			array(
				array( '2026', '14 to 30 January', 'Issued 11 Jan 2026, together with Jiv Jantu Kalyan Diwas on Vasant Panchami. See the Library: ' . g_a( $msg, 'Message to Celebrate Animal Welfare Fortnight 2026' ) ),
				array( '2027', '14 to 30 January', 'Not issued yet (AWBI usually issues it in early January)' ),
			)
		)
		. g_h( 'Who started it' )
		. g_p( 'The Animal Welfare Board of India is the statutory body set up in 1962 under the Prevention of Cruelty to Animals Act, 1960. Its founder chairperson, Rukmini Devi Arundale, started the yearly animal welfare fortnight in 1964.' )
		. g_h( 'What happens in the fortnight' )
		. g_ul(
			array(
				'<strong>AWBI\'s message:</strong> each year AWBI sends a circular to the states and union territories, recognised animal welfare organisations and its honorary animal welfare representatives, asking for awareness programmes during the fortnight.',
				'<strong>Suggested activities</strong> in past AWBI messages: animal birth control and anti-rabies drives, rescue and treatment of sick and injured animals, and campaigns against plastic that animals swallow.',
				'<strong>States and local bodies</strong> run their own events. In 2026, for example, the Andaman and Nicobar administration formally launched the fortnight.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Get your dog vaccinated</strong> against rabies, and ask your local body about sterilisation and vaccination for community dogs.',
				'<strong>Help an injured animal:</strong> call a local rescue group or a government veterinary hospital. Find one in the ' . g_a( $dir, 'Directory' ) . '.',
				'<strong>Keep plastic away from animals:</strong> cattle and street animals swallow plastic bags with food waste.',
				'<strong>Report cruelty:</strong> cruelty to animals is an offence under the Prevention of Cruelty to Animals Act, 1960.',
			)
		)
		. g_faq(
			array(
				array( 'When is Animal Welfare Fortnight?', 'Every year from 14 to 30 January. The next one is 14 to 30 January 2027.' ),
				array( 'Who organises Animal Welfare Fortnight?', 'The Animal Welfare Board of India, which asks states, local bodies and animal welfare organisations to hold events.' ),
				array( 'When did Animal Welfare Fortnight start?', 'In 1964, started by AWBI\'s founder chairperson, Rukmini Devi Arundale.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Library: ' . g_a( $msg, 'Message to Celebrate Animal Welfare Fortnight 2026 and Jiv Jantu Kalyan Diwas' ) . ' (AWBI, 11 Jan 2026)',
				'Guide: ' . g_a( $days, 'Animal days in India' ) . ' (the full list)',
				'Guide: ' . g_a( $bite, 'What to do after a dog bite in India' ),
				'Category: ' . g_a( $catl, 'Campaigns and Civic Action' ),
				'Directory: ' . g_a( $dir, 'animal welfare organisations, government veterinary hospitals' ),
			)
		)
		. g_sources(
			array(
				array( 'AWBI circulars', 'https://awbi.gov.in/circulars', 'Animal Welfare Board of India' ),
				array( 'AWBI annual report 2024-25', 'https://www.awbi.gov.in/uploads/regulations/178651150626English%20Annual%20Report%202024-25%20sent%20for%20printinting.pdf', 'the fortnight each year from 14 to 30 January' ),
				array( 'Animal Welfare Fortnight 2010 launched', 'https://www.deccanherald.com/amp/story/science%2Fanimal-welfare-fortnight-2010-launched-2467868', 'Deccan Herald, 2010: started in 1964 by Rukmini Devi Arundale' ),
				array( 'Animal Welfare Fortnight 2026 launched', 'https://andamansheekha.com/?p=150442', 'Andaman Sheekha, January 2026' ),
				array( 'Rajya Sabha answer on AWBI circulars', 'https://dahd.gov.in/sites/default/files/2025-04/RS3605.pdf', 'Ministry of Fisheries, Animal Husbandry and Dairying' ),
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
update_post_meta( $id, '_aw_owner_draft', 'Observance-day page; owner approved 25 Oct 2026; sources checked 25 Oct 2026 (AWBI)' );
clean_post_cache( $id );
WP_CLI::success( "$id | " . get_post_status( $id ) . ' | ' . get_permalink( $id ) . " | t=$t d=$dl" );
