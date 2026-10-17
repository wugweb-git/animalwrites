<?php
/**
 * "India Writes for Its Community Dogs" (aw_campaign 8, the 29 Nov 2025 letter petition) as a Post-state campaign
 * page under the Campaign Content Standard v1.0 (Notion, approved 17 Oct 2026; Part C3 order). Owner's brief of
 * 17 Oct 2026: no new copy; every existing block is re-mapped into the standard's sections, and anything without a
 * section goes, unchanged, into an editor-only "Unused (review)" group. Where copy is needed, an editor-only
 * aw-owner-copy note says what goes there.
 *
 * Reads the page as it was before (backup file, so re-running gives the same result), checks every block it
 * reuses against its expected text, and stops on any mismatch.
 *
 * Run: wp eval-file aw1-post-page.php ~/backups/aw1-content-2026-10-17.html
 */

$src = $args[0] ?? '';
if ( ! $src || ! is_readable( $src ) ) {
	WP_CLI::error( 'Give the backup of post 8\'s content as the first argument' );
}
$post_id = 8;
if ( 'aw_campaign' !== get_post_type( $post_id ) ) {
	WP_CLI::error( 'Post 8 is not the campaign' );
}
$tag = get_term_by( 'slug', 'campaign-india-writes-community-dogs', 'post_tag' );
if ( ! $tag ) {
	WP_CLI::error( 'Tag campaign-india-writes-community-dogs not found (rename tag 1075 first)' );
}

$blocks = array_values( array_filter( parse_blocks( (string) file_get_contents( $src ) ), fn( $b ) => ! empty( $b['blockName'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
$text   = fn( $b ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( serialize_block( $b ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ); // the stored words, not the rendered ones (dates are reformatted on render)

/* What each reused block must say (start of its text), by position in the old page. */
$expect = array(
	0  => 'Animal Writes',
	2  => '29 November 2025',
	13 => 'Why This Matters',
	15 => 'No Infrastructure',
	21 => 'Your Constitutional Right',
	25 => 'Use the uniform title',
	35 => 'Campaign Timeline',
	37 => 'November 1, 2025',
	45 => 'December 2, 2025',
	75 => 'Supported By',
	77 => 'ASHA Tails of Hope',
	79 => 'Moments From Previous Campaigns',
	80 => 'A glimpse',
	81 => '',
	84 => 'Media Coverage',
	86 => 'Death sentence to animals',
	88 => 'Our Impact',
	92 => 'Nationwide GPO Participation',
	93 => 'Thousands of citizens walked',
	102 => 'Stay Connected',
	111 => 'Follow / Share us on Instagram',
);
if ( 112 !== count( $blocks ) ) {
	WP_CLI::error( 'Expected 112 blocks in the old page, found ' . count( $blocks ) );
}
foreach ( $expect as $i => $start ) {
	if ( '' !== $start && 0 !== strpos( $text( $blocks[ $i ] ), $start ) ) {
		WP_CLI::error( "Block $i does not start with \"$start\": " . substr( $text( $blocks[ $i ] ), 0, 80 ) );
	}
}
$used = array();
$take = function ( $i ) use ( &$blocks, &$used ) {
	$used[ $i ] = true;
	return $blocks[ $i ];
};
$ser = fn( $b ) => serialize_block( $b ) . "\n\n";

/* Builders (same markup as tools/content/campaign2-page.php). */
function a1p( $t, $cls = '' ) {
	$a = $cls ? ' {"className":"' . $cls . '"}' : '';
	$c = $cls ? ' class="' . $cls . '"' : '';
	return "<!-- wp:paragraph$a -->\n<p$c>$t</p>\n<!-- /wp:paragraph -->\n\n";
}
function a1h( $t, $level = 2 ) {
	$a = 2 !== $level ? ' {"level":' . $level . '}' : '';
	return "<!-- wp:heading$a -->\n<h$level class=\"wp-block-heading\">$t</h$level>\n<!-- /wp:heading -->\n\n";
}
function a1sc( $sc ) {
	return "<!-- wp:shortcode -->\n$sc\n<!-- /wp:shortcode -->\n\n";
}
function a1html( $h ) {
	return "<!-- wp:html -->\n$h\n<!-- /wp:html -->\n\n";
}
function a1i( $n ) {
	return '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
}
function a1date( $day ) {
	return '<span class="aw-date">' . a1i( 'calendar' ) . '<time datetime="' . $day . '">' . wp_date( 'j M Y', strtotime( $day . ' 12:00:00 Asia/Kolkata' ) ) . '</time></span>';
}
function a1group( $inner, $cls ) {
	return '<!-- wp:group {"className":"' . $cls . '","layout":{"type":"default"}} -->' . "\n<div class=\"wp-block-group $cls\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function a1sec( $anchor, $inner, $cls = '' ) {
	$classes = trim( 'aw-camp-section ' . $cls );
	$attrs   = array(
		'align'     => 'full',
		'className' => $classes,
		'style'     => array( 'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|60', 'bottom' => 'var:preset|spacing|60', 'left' => 'var:preset|spacing|40', 'right' => 'var:preset|spacing|40' ) ) ),
		'layout'    => array( 'type' => 'constrained', 'contentSize' => '1120px' ),
		'anchor'    => $anchor,
	);
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<div id=\"$anchor\" class=\"wp-block-group alignfull $classes\" style=\"padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function a1owner( $note ) {
	return '<!-- wp:paragraph {"className":"aw-owner-copy"} -->' . "\n<p class=\"aw-owner-copy\">[OWNER COPY] $note</p>\n<!-- /wp:paragraph -->\n\n";
}
function a1card( $inner ) {
	return a1group( $inner, 'aw-card' );
}
function a1grid( $cards ) {
	$inner = '';
	foreach ( $cards as $c ) {
		$inner .= a1card( $c );
	}
	return "<!-- wp:group {\"layout\":{\"type\":\"grid\",\"minimumColumnWidth\":\"16rem\"}} -->\n<div class=\"wp-block-group\">$inner</div>\n<!-- /wp:group -->\n\n";
}
/** A heading block reused with new text (and level); its other attributes kept. */
function a1rehead( $b, $t, $level = 2 ) {
	$b['attrs']['level'] = $level;
	if ( 2 === $level ) {
		unset( $b['attrs']['level'] );
	}
	$h                  = "<h$level class=\"wp-block-heading\">$t</h$level>";
	$b['innerHTML']     = $h;
	$b['innerContent']  = array( $h );
	return $b;
}

$lib = function ( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'aw_legal' );
	return $p && 'publish' === $p->post_status ? $p : null;
};
$link = fn( $p, $label = '' ) => $p ? '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $label ? $label : get_the_title( $p ) ) . '</a>' : '';
$j22  = get_post( 176 );                                  // 22 Aug 2025 judgment, 2025 INSC 1018
$o11  = $lib( 'supreme-court-order-of-11-august-2025-on-stray-dogs-2025-insc-977' );
$o7   = $lib( 'supreme-court-order-of-7-november-2025-on-stray-dogs-2025-insc-1357' );
$j19  = $lib( 'supreme-court-judgment-of-19-may-2026-on-stray-dogs-2026-insc-506' );
$sop  = get_post( 9561 );

/* ---------- 1 hero ---------- */
$h1   = a1rehead( $take( 0 ), 'India Writes for Its Community Dogs', 1 );
$hero = a1sec(
	'hero',
	$ser( $h1 )
	. a1p( 'Nationwide letter petition to the Supreme Court against the 7 Nov 2025 removal order · 29 Nov 2025', 'aw-camp-lede' )
	. a1sc( '[aw_camp_status]' )
	. a1owner( 'Hero summary: 1–2 lines; what people did, when, why (7 Nov 2025 order); past tense; no outcome claim; no superlatives.' )
	. $ser( $take( 2 ) ) // 29 November 2025 · All GPOs and HPOs
	. a1sc( '[aw_campaign_metric key="letters"]' )
	. '<!-- wp:image {"id":15,"sizeSlug":"large","linkDestination":"none"} -->' . "\n" . '<figure class="wp-block-image size-large"><img src="' . esc_url( (string) wp_get_attachment_image_url( 15, 'large' ) ) . '" alt="' . esc_attr( (string) get_post_meta( 15, '_wp_attachment_image_alt', true ) ) . '" class="wp-image-15"/></figure>' . "\n<!-- /wp:image -->\n\n",
	'aw-camp-hero'
);

/* ---------- 2 campaign-goal ---------- */
$goal = a1sec(
	'campaign-goal',
	a1h( 'Campaign goal' )
	. a1p( '<strong>Goal:</strong> reconsideration of the 7 Nov 2025 removal order.' )
	. a1p( '<strong>Success criterion:</strong> the Court reconsiders the removal and relocation directions.' )
	. a1sc( '[aw_campaign_outcome]' ),
	'aw-camp-paper'
);

/* ---------- 3 why-it-matters ---------- */
$why = a1sec(
	'why-it-matters',
	$ser( a1rehead( $take( 13 ), 'Why it mattered' ) )
	. a1grid(
		array(
			$ser( $take( 15 ) ) . $ser( $take( 16 ) ),
			$ser( $take( 17 ) ) . $ser( $take( 18 ) ),
			$ser( $take( 19 ) ) . $ser( $take( 20 ) ),
			$ser( $take( 21 ) ) . $ser( $take( 22 ) ) . $ser( $take( 23 ) ) . $ser( $take( 24 ) ) . $ser( $take( 25 ) ),
		)
	)
);

/* ---------- 4 how-to-help (what the campaign did) ---------- */
$mosaic = '<div class="aw-ed-mosaic">'
	. '<img class="m1" src="/wp-content/uploads/2026/10/aw1-im6-1536x866.jpeg" alt="A large crowd seated with placards at the day of action." width="1536" height="866" loading="lazy">'
	. '<img class="m2" src="/wp-content/uploads/2026/10/aw1-im8-768x1024.jpeg" alt="A boy holds a hand-lettered placard with a drawing of a dog." width="768" height="1024" loading="lazy">'
	. '<img class="m3" src="/wp-content/uploads/2026/10/aw1-im10-461x1024.jpeg" alt="A dog sits behind a sign reading Sada Haq Aithe Rakh." width="461" height="1024" loading="lazy">'
	. '<img class="m4" src="/wp-content/uploads/2026/10/aw1-im11-768x1024.jpeg" alt="A man on a tricycle holds a handwritten sign." width="768" height="1024" loading="lazy">'
	. '<img class="m5" src="/wp-content/uploads/2026/10/aw1-im12-768x1024.jpeg" alt="A girl holds a hand-drawn poster." width="768" height="1024" loading="lazy">'
	. '</div>'; // the story block's mosaic (patterns/aw1-story.php), same images and descriptions
$moments = $take( 81 ); // the gallery keeps the five photos the mosaic does not show (im1–im5)
$keep    = array( 17, 18, 19, 20, 21 );
$moments['innerBlocks'] = array_values( array_filter( $moments['innerBlocks'], fn( $im ) => in_array( (int) ( $im['attrs']['id'] ?? 0 ), $keep, true ) ) );
$moments['innerContent'] = array_merge( array( $moments['innerContent'][0] ), array_fill( 0, count( $moments['innerBlocks'] ), null ), array( end( $moments['innerContent'] ) ) );
$templates = array_filter( array( get_post( 11 ), get_post( 9 ), get_post( 13 ), get_post( 14 ) ), fn( $p ) => $p && 'publish' === $p->post_status );
$tlist     = '';
foreach ( $templates as $p ) {
	$tlist .= "<!-- wp:list-item -->\n<li>" . $link( $p ) . "</li>\n<!-- /wp:list-item -->\n";
}
$how = a1sec(
	'how-to-help',
	a1h( 'What we did' )
	. $ser( $take( 92 ) ) . $ser( $take( 93 ) ) // Nationwide GPO Participation on November 29th
	. a1owner( 'Receipt uploads and city coordinators: one line each, facts only.' )
	. a1owner( 'LawChakra line: one sentence, "as reported by LawChakra"; Imphal, Vadodara, Chennai; receipts as proof; link to https://lawchakra.in/legal-updates/indian-supreme-court-letters-stray-dogs/' )
	. a1h( 'The letters', 3 )
	. "<!-- wp:list -->\n<ul class=\"wp-block-list\">$tlist</ul>\n<!-- /wp:list -->\n\n"
	. $ser( a1rehead( $take( 79 ), 'Moments', 3 ) )
	. $ser( $take( 80 ) )
	. a1html( $mosaic )
	. $ser( $moments ),
	'aw-camp-paper'
);

/* ---------- 5 context: the timeline (existing entries, and the case's dates from the owner's brief) ---------- */
$entries = array(
	array( '2025-07-28', 'Supreme Court takes suo motu cognizance', '' ),
	array( '2025-08-11', 'Relocation order', $link( $o11, 'Read the order (2025 INSC 977)' ) ),
	array( '2025-08-22', 'Order modified', $link( $j22, 'Read the judgment (2025 INSC 1018)' ) ),
);
$old = array( 37 => '2025-11-01', 39 => '2025-11-10', 41 => '2025-11-20', 43 => '2025-11-29', 45 => '2025-12-02' );
foreach ( $old as $i => $day ) {
	$t = preg_replace( '/^[A-Za-z]+ \d{1,2}, \d{4}\s*—\s*/u', '', $text( $take( $i ) ) ); // the heading's own words, without its date
	$entries[] = array( $day, $t, esc_html( str_replace( 'Historic achievement', 'Letters delivered to the Supreme Court', $text( $take( $i + 1 ) ) ) ) ); // owner, 17 Oct 2026
	if ( '2025-11-01' === $day ) {
		$entries[] = array( '2025-11-07', 'Removal order', $link( $o7, 'Read the order (2025 INSC 1357)' ) );
	}
}
$entries[] = array( '2026-05-19', 'Final judgment upholds the 7 Nov 2025 order', $link( $j19, 'Read the judgment (2026 INSC 506)' ) );
$li = '';
foreach ( $entries as $e ) {
	$li .= '<li' . ( '2025-11-29' === $e[0] ? ' class="is-key"' : '' ) . '><span class="aw-camp-dates-when">' . a1date( $e[0] ) . '</span><span class="aw-camp-dates-what"><strong>' . esc_html( $e[1] ) . '</strong>' . ( '' !== $e[2] ? ' ' . $e[2] : '' ) . '</span></li>';
}
$context = a1sec(
	'context',
	$ser( $take( 35 ) ) // Campaign Timeline
	. $ser( $take( 36 ) )
	. a1html( '<ol class="aw-camp-dates aw-camp-timeline">' . $li . '</ol>' )
);

/* ---------- 6 impact ---------- */
$press_reports = array(
	array( 'https://www.indiatv.in/india/national/over-100000-people-filed-letter-petition-in-supreme-court-appealing-not-to-remove-stray-dogs-from-streets-2025-11-29-1179585', 'Over 1,00,000 people filed letter petition in Supreme Court', 'India TV', '2025-11-29' ),
	array( 'https://www.dtnext.in/news/tamilnadu/over-15l-from-tn-write-to-sc-seeking-recall-of-dog-removal-order-855059', 'Over 1.5L from TN write to SC seeking recall of dog removal order', 'DT Next', '2025-11-30' ),
	array( 'https://lawchakra.in/legal-updates/indian-supreme-court-letters-stray-dogs/', 'Over 50,000 Indians Flood Supreme Court With Handwritten Letters Opposing Stray Dogs Removal Order', 'LawChakra', '2025-12-01' ),
);
$pr = '';
foreach ( $press_reports as $r ) {
	$pr .= "<!-- wp:list-item -->\n<li><a href=\"{$r[0]}\">" . esc_html( $r[1] ) . '</a> — ' . esc_html( $r[2] ) . ' · ' . a1date( $r[3] ) . "</li>\n<!-- /wp:list-item -->\n";
}
$impact = a1sec(
	'impact',
	$ser( a1rehead( $take( 88 ), 'Impact' ) )
	. a1h( 'Results', 3 )
	. a1group( a1sc( '[aw_campaign_metric key="letters"]' ) . a1sc( '[aw_campaign_metric key="committed"]' ) . a1sc( '[aw_campaign_metric key="cities"]' ) . a1sc( '[aw_campaign_metric key="coordinators"]' ), 'aw-camp-results' )
	. a1p( 'Press reports of the number of letters:' )
	. "<!-- wp:list -->\n<ul class=\"wp-block-list\">$pr</ul>\n<!-- /wp:list -->\n\n"
	. a1h( 'Real-world impact', 3 )
	. a1sc( '[aw_campaign_impact]' )
);

/* ---------- 7 next-steps ---------- */
$nlinks = '';
foreach ( array( $j19, $o7, $sop ) as $p ) {
	if ( $p && 'publish' === $p->post_status ) {
		$nlinks .= "<!-- wp:list-item -->\n<li>" . $link( $p ) . "</li>\n<!-- /wp:list-item -->\n";
	}
}
$next = a1sec(
	'next-steps',
	a1h( 'What happened next' )
	. a1owner( 'Neutral, dated: 19 May 2026 judgment (2026 INSC 506), 7 Nov order upheld, AWBI SOP upheld, euthanasia within safeguards, High Courts monitor compliance; link the Library records; no attribution to the campaign.' )
	. ( $nlinks ? "<!-- wp:list -->\n<ul class=\"wp-block-list\">$nlinks</ul>\n<!-- /wp:list -->\n\n" : '' ),
	'aw-camp-paper'
);

/* ---------- 9 partners: the logos, each to its Directory profile, alt text = the organisation's name ---------- */
$dir = array( 27 => 'asha-tails-of-hope', 28 => 'barkville-foundation', 29 => 'nawabi-tails-rescue', 30 => 'tabassum', 31 => 'the-canine-help', 32 => 'swapna', 33 => 'pawsome-foundation', 34 => 'the-pack-project', 35 => 'animal-rescue-kashmir-ark', 36 => 'jeev-basera', 37 => 'rainbow-animal-aid', 38 => 'project-nayna', 39 => 'healing-saathi' );
$logos = $take( 77 );
$logos['attrs']['className'] = 'aw-camp-partner-logos';
$logos['innerContent'][0]    = str_replace( 'class="wp-block-gallery ', 'class="wp-block-gallery aw-camp-partner-logos ', $logos['innerContent'][0] );
foreach ( $logos['innerBlocks'] as &$im ) {
	$aid  = (int) $im['attrs']['id'];
	$name = trim( wp_strip_all_tags( preg_replace( '#.*<figcaption[^>]*>(.*?)</figcaption>.*#s', '$1', $im['innerHTML'] ) ) );
	$href = home_url( '/directory/org/' . $dir[ $aid ] . '/' );
	update_post_meta( $aid, '_wp_attachment_image_alt', $name );
	$im['attrs']['linkDestination'] = 'custom';
	$im['attrs']['href']            = $href;
	$h                              = preg_replace( '/ alt="[^"]*"/', ' alt="' . esc_attr( $name ) . '"', $im['innerHTML'] );
	$h                              = preg_replace( '#(<img [^>]+/>)#', '<a href="' . esc_url( $href ) . '">$1</a>', $h, 1 );
	$im['innerHTML']                = $h;
	$im['innerContent']             = array( $h );
}
unset( $im );
$partners = a1sec( 'partners', $ser( $take( 75 ) ) . $ser( $logos ) );

/* ---------- 10 press: the existing list, with dates; LawChakra added ---------- */
$dates = array( 'indiatvnews.com' => '2025-11-29', 'outlookindia.com' => '2025-11-29', 'deccanherald.com' => '2025-11-29', 'timesofindia.indiatimes.com' => '2025-11-27', 'newindianexpress.com' => '2025-11-30', 'indiatv.in' => '2025-11-29', 'dtnext.in' => '2025-11-30' );
$plist = $take( 86 );
foreach ( $plist['innerBlocks'] as &$it ) {
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( preg_replace( '#.*href="([^"]+)".*#s', '$1', $it['innerHTML'] ), PHP_URL_HOST ) );
	if ( isset( $dates[ $host ] ) ) {
		$h                  = str_replace( '</li>', ' · ' . a1date( $dates[ $host ] ) . '</li>', $it['innerHTML'] );
		$it['innerHTML']    = $h;
		$it['innerContent'] = array( $h );
	}
}
unset( $it );
$lc                     = '<li><a href="https://lawchakra.in/legal-updates/indian-supreme-court-letters-stray-dogs/">Over 50,000 Indians Flood Supreme Court With Handwritten Letters Opposing Stray Dogs Removal Order</a> — LAWCHAKRA · ' . a1date( '2025-12-01' ) . '</li>';
$plist['innerBlocks'][] = array( 'blockName' => 'core/list-item', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $lc, 'innerContent' => array( $lc ) );
$plist['innerContent']  = array_merge( array( $plist['innerContent'][0] ), array_fill( 0, count( $plist['innerBlocks'] ), null ), array( end( $plist['innerContent'] ) ) );
$press = a1sec( 'press', $ser( $take( 84 ) ) . $ser( $plist ), 'aw-camp-paper' );

/* ---------- 11 library and 12 news: the campaign tag (pattern of the Campaign 2.0 page) ---------- */
$tq      = '"taxQuery":{"post_tag":[' . (int) $tag->term_id . ']}';
$library = a1sec(
	'library',
	a1h( 'Read the law and the record' )
	. '<!-- wp:query {"queryId":91,"query":{"perPage":20,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"aw-camp-docs-q kit-ico--file"} -->' . "\n"
	. '<div class="wp-block-query aw-camp-docs-q kit-ico--file"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n\n"
);
$news = a1sec(
	'news',
	a1html( '<header class="kit-ed-head"><div><h2>News and guides</h2></div><a class="kit-ed-more" href="/news/?tag=campaign-india-writes-community-dogs">All news ' . a1i( 'arrow-right' ) . '</a></header>' )
	. '<!-- wp:group {"className":"kit-ed-news-grid","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group kit-ed-news-grid">'
	. '<!-- wp:query {"queryId":92,"query":{"perPage":1,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"kit-ed-lead"} -->' . "\n"
	. '<div class="wp-block-query kit-ed-lead"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:animal-writes/publisher /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- wp:post-excerpt {"excerptLength":36} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n"
	. '<!-- wp:query {"queryId":93,"query":{"perPage":9,"pages":0,"offset":1,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"kit-ed-list"} -->' . "\n"
	. '<div class="wp-block-query kit-ed-list"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:animal-writes/publisher /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n"
	. '</div>' . "\n" . '<!-- /wp:group -->' . "\n\n"
	. a1h( 'Guides and research', 3 )
	. '<!-- wp:query {"queryId":94,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,' . $tq . '},"className":"kit-ed-list"} -->' . "\n"
	. '<div class="wp-block-query kit-ed-list"><!-- wp:post-template -->' . "\n" . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:animal-writes/publisher /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" . '<!-- /wp:post-template --></div>' . "\n" . '<!-- /wp:query -->' . "\n\n"
);

/* ---------- 13 support: lessons learned, hidden until the owner writes them ---------- */
$support = a1sec(
	'support',
	a1h( 'Lessons learned' )
	. a1owner( 'Lessons learned: the owner\'s first-hand reflections. This section stays hidden until this note is replaced.' ),
	'aw-camp-needs-copy'
);

/* ---------- 14 updates ---------- */
$updates = a1sec( 'updates', a1h( 'Stay connected' ) . $ser( $take( 102 ) ) . $ser( $take( 111 ) ), 'aw-camp-dark' );

/* ---------- Unused (review): every block without a section, unchanged and in its old order ---------- */
$unused = '';
foreach ( $blocks as $i => $b ) {
	if ( empty( $used[ $i ] ) ) {
		$unused .= $ser( $b );
	}
}
$unused_group = '<!-- wp:group {"className":"aw-unused-review","layout":{"type":"default"}} -->' . "\n<div class=\"wp-block-group aw-unused-review\">"
	. a1p( 'Unused (review): blocks of the page before 17 Oct 2026 that have no section under the Campaign Content Standard. Not shown on the site. The owner decides to rework or drop them.' )
	. $unused . "</div>\n<!-- /wp:group -->\n\n";

$content = '<!-- wp:group {"align":"full","className":"aw-camp aw-camp--community-dogs","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignfull aw-camp aw-camp--community-dogs">'
	. $hero . $goal . $why . $how . $context . $impact . $next . $partners . $press . $library . $news . $support . $updates
	. $unused_group
	. "</div>\n<!-- /wp:group -->";

$post = get_post( $post_id );
$id   = wp_insert_post(
	wp_slash(
		array(
			'ID'            => $post_id,
			'post_type'     => 'aw_campaign',
			'post_title'    => 'India Writes for Its Community Dogs',
			'post_name'     => $post->post_name, // the slug moves with its redirects (aw1-post-site.php)
			'post_excerpt'  => $post->post_excerpt,
			'post_content'  => $content,
			'post_status'   => $post->post_status,
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
		)
	),
	true
);
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
wp_set_object_terms( $id, array( (int) $tag->term_id ), 'post_tag', true );
$metrics = array(
	array( 'key' => 'letters', 'label' => 'letters delivered to the Supreme Court', 'value' => '2.5 lakh', 'attribution' => 'By the campaign\'s count', 'source_label' => 'campaign-reported', 'verified' => '2026-10-17', 'threshold' => 0, 'stale_days' => 0 ),
	array( 'key' => 'committed', 'label' => 'citizens committed', 'value' => '10,000+', 'attribution' => 'By the campaign\'s count', 'source_label' => 'campaign-reported', 'verified' => '2026-10-17', 'threshold' => 0, 'stale_days' => 0 ),
	array( 'key' => 'cities', 'label' => 'cities', 'value' => '100+', 'attribution' => 'By the campaign\'s count', 'source_label' => 'campaign-reported', 'verified' => '2026-10-17', 'threshold' => 0, 'stale_days' => 0 ),
	array( 'key' => 'coordinators', 'label' => 'volunteer coordinators', 'value' => '100+', 'attribution' => 'By the campaign\'s count', 'source_label' => 'campaign-reported', 'verified' => '2026-10-17', 'threshold' => 0, 'stale_days' => 0 ),
);
foreach ( array(
	'_wp_page_template'           => 'campaign-page',
	'aw_campaign_open'            => '2025-11-01',
	'aw_campaign_close'           => '2025-11-29',
	'aw_campaign_outcome'         => 'not', // Not achieved (campaign-tools.php keys)
	'aw_campaign_outcome_asof'    => '2026-10-17',
	'aw_campaign_impact_state'    => 'no-change', // owner, 17 Oct 2026: No change (verified); the 19 May 2026 judgment upheld the order
	'aw_campaign_impact_evidence' => $j19 ? (int) $j19->ID : 0,
	'aw_campaign_metrics'         => wp_json_encode( $metrics, JSON_UNESCAPED_UNICODE ),
) as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
WP_CLI::success( sprintf( 'campaign %d rebuilt: %d blocks reused, %d kept in Unused (review)', $id, count( $used ), count( $blocks ) - count( $used ) ) );
