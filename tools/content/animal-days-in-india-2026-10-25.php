<?php
/**
 * Publishes the owner-approved "Animal days in India" list page (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * The list page of the observance-day guides: every animal day marked in India, month by month, linking each day that has
 * its own guide. Main category Campaigns and Civic Action (owner, 25 Oct 2026: no "Animal welfare" category). National
 * Dolphin Day added to the brief's 10 days (owner, 25 Oct 2026).
 * Sources checked 25 Oct 2026: UN News (World Wildlife Day, Dec 2013), worldsparrowday.org (first day 20 Mar 2010), World
 * Veterinary Association (2026 theme, 25 Apr 2026), PIB (International Tiger Day backgrounder, Jul 2024; World Elephant
 * Day backgrounder, 12 Aug 2026; NBWL Standing Committee on National Dolphin Day, 2022), AWBI circulars page.
 *
 * 25 Oct 2026, later: every day's name links its own guide once all eleven were published.
 *
 * Usage: wp eval-file animal-days-in-india-2026-10-25.php [dry]
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
$ww  = $L( '/resources/wildlife-week/' );
$wad = $L( '/resources/world-animal-day-4-october/' );
$awf = $L( '/resources/animal-welfare-fortnight/' );
$wwd = $L( '/resources/world-wildlife-day/' );
$spa = $L( '/resources/world-sparrow-day/' );
$vet = $L( '/resources/world-veterinary-day/' );
$zoo = $L( '/resources/world-zoonoses-day/' );
$tig = $L( '/resources/international-tiger-day/' );
$ele = $L( '/resources/world-elephant-day/' );
$dol = $L( '/resources/national-dolphin-day/' );
$dir = home_url( '/directory/' );
$cat = get_term_by( 'name', 'Campaigns and Civic Action', 'category' );
if ( ! $cat ) {
	$miss[] = 'category Campaigns and Civic Action';
}
if ( $miss ) {
	WP_CLI::error( 'Not found, nothing written: ' . implode( ', ', $miss ) );
}
$catl = (string) get_term_link( $cat );

$d = array(
	'slug'     => 'animal-days-in-india',
	'title'    => 'Animal days in India: the full list, month by month',
	'rm_title' => 'Animal Days in India Full List of Dates – Animal Writes',
	'desc'     => 'Every animal day marked in India, month by month: World Wildlife Day, Sparrow Day, Tiger Day, World Animal Day and more, with dates and who marks them.',
	'focus'    => 'animal days in india,important animal days,animal welfare days list',
	'content'  => g_p( 'These are the animal days marked in India each year, from the Animal Welfare Board of India\'s fortnight in January to Wildlife Week in October. Each entry gives the date, who marks the day, and what happens in India. Where we have a full guide, the name links to it.' )
		. g_h( 'The list' )
		. g_table(
			array( 'Month', 'Date', 'Day', 'Who marks it' ),
			array(
				array( 'January', '14 to 30 January', g_a( $awf, 'Animal Welfare Fortnight' ), 'Animal Welfare Board of India (AWBI)' ),
				array( 'March', '3 March', g_a( $wwd, 'World Wildlife Day' ), 'United Nations (CITES)' ),
				array( 'March', '20 March', g_a( $spa, 'World Sparrow Day' ), 'Nature Forever Society' ),
				array( 'April', 'Last Saturday of April (24 April 2027)', g_a( $vet, 'World Veterinary Day' ), 'World Veterinary Association' ),
				array( 'July', '6 July', g_a( $zoo, 'World Zoonoses Day' ), 'Marks the first rabies vaccine, given by Louis Pasteur on 6 July 1885' ),
				array( 'July', '29 July', g_a( $tig, 'International Tiger Day' ), 'Tiger range countries; in India, the National Tiger Conservation Authority' ),
				array( 'August', '12 August', g_a( $ele, 'World Elephant Day' ), 'World Elephant Day; in India, Project Elephant (Ministry of Environment)' ),
				array( 'September', '28 September', g_a( $rab, 'World Rabies Day' ), 'Global Alliance for Rabies Control; WHO' ),
				array( 'October', '2 to 8 October', g_a( $ww, 'Wildlife Week' ), 'Ministry of Environment, Forest and Climate Change' ),
				array( 'October', '4 October', g_a( $wad, 'World Animal Day' ), 'World Animal Day organisation' ),
				array( 'October', '5 October', g_a( $dol, 'National Dolphin Day' ), 'National Board for Wildlife (since 2022)' ),
			)
		)
		. g_h( 'The days, in short' )
		. g_p( '<strong>Animal Welfare Fortnight (14 to 30 January).</strong> The Animal Welfare Board of India asks local bodies, departments and animal welfare organisations to hold events for animals during the fortnight. Past circulars asked for animal birth control and anti-rabies drives, and for help to sick and injured animals. AWBI issues a circular for each year, and states hold their own events. Full guide: ' . g_a( $awf, 'Animal Welfare Fortnight' ) . '.' )
		. g_p( '<strong>World Wildlife Day (3 March).</strong> A United Nations day, on the date the CITES convention on trade in endangered species was signed in 1973. Full guide: ' . g_a( $wwd, 'World Wildlife Day' ) . '.' )
		. g_p( '<strong>World Sparrow Day (20 March).</strong> Started by the Nature Forever Society of India in 2010, to bring back the house sparrow. Full guide: ' . g_a( $spa, 'World Sparrow Day' ) . '.' )
		. g_p( '<strong>World Veterinary Day (last Saturday of April).</strong> The World Veterinary Association\'s day for vets. The 2026 theme was "Veterinarians: Guardians of Food and Health" (25 April 2026). Full guide: ' . g_a( $vet, 'World Veterinary Day' ) . '.' )
		. g_p( '<strong>World Zoonoses Day (6 July).</strong> For diseases that pass between animals and people, such as rabies. It marks the day in 1885 when Louis Pasteur first gave his rabies vaccine. Full guide: ' . g_a( $zoo, 'World Zoonoses Day' ) . '.' )
		. g_p( '<strong>International Tiger Day (29 July).</strong> Agreed by the tiger range countries at the St Petersburg summit in 2010. India often releases its tiger figures on this day. Full guide: ' . g_a( $tig, 'International Tiger Day' ) . '.' )
		. g_p( '<strong>World Elephant Day (12 August).</strong> Marked since 2012. In India, Project Elephant and state forest departments hold events. Full guide: ' . g_a( $ele, 'World Elephant Day' ) . '.' )
		. g_p( '<strong>World Rabies Day (28 September).</strong> The anniversary of Louis Pasteur\'s death. India\'s plan is zero human deaths from dog-bite rabies by 2030. Full guide: ' . g_a( $rab, 'World Rabies Day' ) . '.' )
		. g_p( '<strong>Wildlife Week (2 to 8 October).</strong> India\'s own week, starting on Gandhi Jayanti. Full guide: ' . g_a( $ww, 'Wildlife Week' ) . '.' )
		. g_p( '<strong>World Animal Day (4 October).</strong> The feast day of St Francis of Assisi, the patron saint of animals. Full guide: ' . g_a( $wad, 'World Animal Day' ) . '.' )
		. g_p( '<strong>National Dolphin Day (5 October).</strong> Set by the National Board for Wildlife in 2022, for the Gangetic river dolphin, India\'s national aquatic animal. Full guide: ' . g_a( $dol, 'National Dolphin Day' ) . '.' )
		. g_faq(
			array(
				array( 'Which animal days are marked in India?', 'The main ones are Animal Welfare Fortnight (14 to 30 January), World Wildlife Day (3 March), World Sparrow Day (20 March), World Veterinary Day (last Saturday of April), World Zoonoses Day (6 July), International Tiger Day (29 July), World Elephant Day (12 August), World Rabies Day (28 September), Wildlife Week (2 to 8 October), World Animal Day (4 October) and National Dolphin Day (5 October).' ),
				array( 'When is Animal Welfare Fortnight?', 'Every year from 14 to 30 January, called by the Animal Welfare Board of India.' ),
				array( 'When is World Animal Day?', 'Every year on 4 October.' ),
				array( 'Is World Wildlife Day the same as Wildlife Week?', 'No. World Wildlife Day is a United Nations day on 3 March. Wildlife Week is India\'s own week, 2 to 8 October.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guides: ' . g_a( $awf, 'Animal Welfare Fortnight' ) . ', ' . g_a( $wwd, 'World Wildlife Day' ) . ', ' . g_a( $spa, 'World Sparrow Day' ) . ', ' . g_a( $vet, 'World Veterinary Day' ) . ', ' . g_a( $zoo, 'World Zoonoses Day' ) . ', ' . g_a( $tig, 'International Tiger Day' ) . ', ' . g_a( $ele, 'World Elephant Day' ) . ', ' . g_a( $rab, 'World Rabies Day' ) . ', ' . g_a( $ww, 'Wildlife Week' ) . ', ' . g_a( $wad, 'World Animal Day' ) . ', ' . g_a( $dol, 'National Dolphin Day' ),
				'Category: ' . g_a( $catl, 'Campaigns and Civic Action' ),
				'Directory: ' . g_a( $dir, 'animal welfare organisations' ),
			)
		)
		. g_sources(
			array(
				array( 'AWBI circulars', 'https://awbi.gov.in/circulars', 'Animal Welfare Board of India: Animal Welfare Fortnight, 14 to 30 January' ),
				array( 'UN General Assembly proclaims 3 March as World Wildlife Day', 'https://news.un.org/en/story/2013/12/458572-un-general-assembly-proclaims-3-march-world-wildlife-day', 'UN News, December 2013' ),
				array( 'World Sparrow Day: history', 'https://www.worldsparrowday.org/about-us/history/index.html', 'Nature Forever Society' ),
				array( 'WVA theme for World Veterinary Day 2026', 'https://worldvet.org/news/wva-theme-for-world-veterinary-day-2026-veterinarians-guardians-of-food-and-health/', 'World Veterinary Association' ),
				array( 'International Tiger Day backgrounder', 'https://static.pib.gov.in/WriteReadData/specificdocs/documents/2024/jul/doc2024728356101.pdf', 'PIB, July 2024: St Petersburg, 29 July 2010' ),
				array( 'World Elephant Day backgrounder', 'https://static.pib.gov.in/WriteReadData/specificdocs/documents/2026/aug/doc2026812951201.pdf', 'PIB, 12 Aug 2026: Project Elephant' ),
				array( 'Standing Committee of the National Board for Wild Life recommends to celebrate October 5 as National Dolphin Day', 'https://www.pib.gov.in/PressReleseDetailm.aspx?PRID=1809800', 'PIB, 2022' ),
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
update_post_meta( $id, '_aw_owner_draft', 'Observance-day list page; owner approved 25 Oct 2026; sources checked 25 Oct 2026' );
clean_post_cache( $id );
WP_CLI::success( "$id | " . get_post_status( $id ) . ' | ' . get_permalink( $id ) . " | t=$t d=$dl" );
