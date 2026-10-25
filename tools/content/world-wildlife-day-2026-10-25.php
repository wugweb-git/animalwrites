<?php
/**
 * Publishes the owner-approved World Wildlife Day guide (25 Oct 2026; draft in Claude outputs/observance-drafts/).
 * Observance-day pages: one evergreen Resources guide per day, slug without the year, updated every year.
 * Sources checked 25 Oct 2026: wildlifeday.org (UN/CITES, 2026 theme), UN News Dec 2013, PIB 3 Mar 2025 (NBWL at Gir).
 *
 * Usage: wp eval-file world-wildlife-day-2026-10-25.php [dry]
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
	'slug'     => 'world-wildlife-day',
	'title'    => 'World Wildlife Day: 3 March, and what India does on it',
	'rm_title' => 'World Wildlife Day 3 March Theme and India – Animal Writes',
	'desc'     => 'World Wildlife Day is on 3 March, the day CITES was signed in 1973. The 2026 theme, how the day began, and what India does on it.',
	'focus'    => 'world wildlife day,world wildlife day 2027,world wildlife day theme',
	'content'  => g_p( 'World Wildlife Day is a United Nations day, marked every year on 3 March. It celebrates wild animals and plants and what they give to people and the planet.' )
		. g_h( 'This year' )
		. g_table(
			array( 'Year', 'Date', 'Theme' ),
			array(
				array( '2026', 'Tuesday, 3 March', 'Medicinal and Aromatic Plants: Conserving Health, Heritage and Livelihoods' ),
				array( '2027', 'Wednesday, 3 March', 'Not announced yet' ),
			)
		)
		. g_h( 'Why 3 March' )
		. g_p( 'On 3 March 1973 the Convention on International Trade in Endangered Species of Wild Fauna and Flora (CITES) was signed in Washington. The UN General Assembly made 3 March World Wildlife Day in December 2013, and the CITES Secretariat runs it.' )
		. g_h( 'What India does on the day' )
		. g_ul(
			array(
				'<strong>2025, Gir:</strong> on 3 March 2025 the Prime Minister chaired the 7th meeting of the National Board for Wildlife at Gir National Park in Gujarat. He released India\'s first river dolphin estimate (6,327 dolphins in 28 rivers across eight states), and announced the 16th count of Asiatic lions, a new project for gharials, a National Great Indian Bustard Conservation Action Plan and a centre of excellence for human-wildlife conflict at Coimbatore.',
				'<strong>Zoos and forest departments</strong> hold talks, nature walks and competitions for students.',
			)
		)
		. g_h( 'What you can do' )
		. g_ul(
			array(
				'<strong>Don\'t buy wildlife products:</strong> skins, ivory, shells, live birds or turtles as pets. Trade in protected species is an offence under the Wildlife (Protection) Act, 1972.',
				'<strong>Report injured wild animals</strong> to the state forest department or a wildlife rescue group, and don\'t handle them yourself. Find one in the Directory: ' . g_a( $dir, 'Wildlife Rescue organisations' ) . '.',
				'<strong>Read and share</strong> wildlife news and laws from the ' . g_a( $catl, 'Wildlife category' ) . '.',
			)
		)
		. g_faq(
			array(
				array( 'When is World Wildlife Day?', 'Every year on 3 March. The next one is on Wednesday, 3 March 2027.' ),
				array( 'What was the theme of World Wildlife Day 2026?', '"Medicinal and Aromatic Plants: Conserving Health, Heritage and Livelihoods".' ),
				array( 'Why is World Wildlife Day on 3 March?', 'CITES, the convention on trade in endangered species, was signed on 3 March 1973.' ),
				array( 'Is World Wildlife Day the same as Wildlife Week?', 'No. World Wildlife Day is a UN day on 3 March. Wildlife Week is India\'s own week, 2 to 8 October.' ),
			)
		)
		. g_h( 'Related on Animal Writes' )
		. g_ul(
			array(
				'Guides: ' . g_a( $ww, 'Wildlife Week' ) . '; ' . g_a( $days, 'Animal days in India' ) . ' (the full list)',
				'Category: ' . g_a( $catl, 'Wildlife' ),
				'Directory: ' . g_a( $dir, 'Wildlife Rescue organisations' ),
			)
		)
		. g_sources(
			array(
				array( 'World Wildlife Day, official website', 'https://wildlifeday.org/en', 'UN / CITES: 3 March, the 2026 theme' ),
				array( 'UN General Assembly proclaims 3 March as World Wildlife Day', 'https://news.un.org/en/story/2013/12/458572-un-general-assembly-proclaims-3-march-world-wildlife-day', 'UN News, December 2013' ),
				array( 'PM chairs the 7th meeting of National Board for Wildlife on 3rd March in Gir', 'https://www.pib.gov.in/PressReleasePage.aspx?PRID=2107778', 'PIB, 3 Mar 2025' ),
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
