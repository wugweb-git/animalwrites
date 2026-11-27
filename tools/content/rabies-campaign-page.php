<?php
/**
 * Rabies awareness & prevention campaign page (India): a standalone campaign landing page in the
 * Campaign Content Standard v1.0 layout (hero, context, why-it-matters, how-to-help, faq, news,
 * library, press, people, partners, next-steps, updates). No petition, letter tool, or count.
 *
 * Tone: calm, urgent, factual public-health guidance grounded in NCDC 2019 National Guidelines,
 * WHO Rabies Fact Sheet, and the Animal Birth Control Rules, 2023.
 *
 * Two ways to run it:
 *   wp eval-file tools/content/rabies-campaign-page.php   creates/updates the draft & maps rabies content
 *   AW_RENDER=1 php tools/content/rabies-campaign-page.php  prints the REST payload (JSON)
 */

const RB_SLUG  = 'rabies-awareness-india';
const RB_TERM  = 'campaign-rabies-india';
const RB_PHOTO = 23334; // world-rabies-day-health-centre-vaccine-table-wide (Media 23334); 23370 duplicates library item 143 and fails the thumbnail check

if ( getenv( 'AW_RENDER' ) ) {
	require getenv( 'AW_RENDER_SHIM' );
}

function rbp( $t, $cls = '' ) {
	$a = $cls ? ' {"className":"' . $cls . '"}' : '';
	$c = $cls ? ' class="' . $cls . '"' : '';
	return "<!-- wp:paragraph$a -->\n<p$c>$t</p>\n<!-- /wp:paragraph -->\n\n";
}
function rbh( $t, $level = 2, $anchor = '' ) {
	$attrs = array();
	if ( 2 !== $level ) {
		$attrs['level'] = $level;
	}
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	$a  = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	return "<!-- wp:heading$a -->\n<h$level class=\"wp-block-heading\"$id>$t</h$level>\n<!-- /wp:heading -->\n\n";
}
function rbsc( $sc ) {
	return "<!-- wp:shortcode -->\n$sc\n<!-- /wp:shortcode -->\n\n";
}
function rbhtml( $h ) {
	return "<!-- wp:html -->\n$h\n<!-- /wp:html -->\n\n";
}
function rbi( $n ) {
	return '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
}
function rba( $text, $href ) {
	return '<a href="' . $href . '">' . $text . '</a>';
}
function rbmore( $text, $href ) {
	return rbp( '<a class="aw-camp-more" href="' . $href . '">' . $text . ' ' . rbi( 'arrow-right' ) . '</a>' );
}
function rbfold( $summary, $inner, $cls = 'aw-camp-card-fold' ) {
	return '<!-- wp:details {"className":"' . $cls . '"} -->' . "\n" . '<details class="wp-block-details ' . $cls . '"><summary>' . $summary . '</summary>' . $inner . '</details>' . "\n" . '<!-- /wp:details -->' . "\n\n";
}
function rbsec( $anchor, $inner, $cls = '' ) {
	$classes = trim( 'aw-camp-section ' . $cls );
	$attrs   = array(
		'align'     => 'full',
		'className' => $classes,
		'style'     => array( 'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|60', 'bottom' => 'var:preset|spacing|60' ) ) ),
		'layout'    => array( 'type' => 'constrained', 'contentSize' => '1120px' ),
	);
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<div$id class=\"wp-block-group alignfull $classes\" style=\"padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function rbslot( $slug, $inner, $cls = '' ) {
	$classes = trim( 'aw-camp-slot aw-camp-slot--' . $slug . ' ' . $cls );
	$attrs   = array( 'anchor' => $slug, 'align' => 'full', 'className' => $classes, 'layout' => array( 'type' => 'default' ) );
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<div id=\"$slug\" class=\"wp-block-group alignfull $classes\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function rbtable( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function rbfaq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function rbsplit( $text, $img_id, $pos = 'center' ) {
	$img = wp_get_attachment_image( $img_id, 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'style' => 'object-position:' . $pos ) );
	return '<!-- wp:columns {"verticalAlignment":"center","className":"aw-camp-split"} -->' . "\n" . '<div class="wp-block-columns are-vertically-aligned-center aw-camp-split">'
		. '<!-- wp:column {"verticalAlignment":"center","width":"58%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%">' . $text . '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
		. '<!-- wp:column {"verticalAlignment":"center","width":"42%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%">' . rbhtml( '<figure class="aw-camp-art">' . $img . '</figure>' ) . '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
		. '</div>' . "\n" . '<!-- /wp:columns -->' . "\n\n";
}
function rbq( $markup, $term_id ) {
	return str_replace( '%TQ%', '"taxQuery":{"' . KITS_CAMPAIGN_LINK . '":[' . (int) $term_id . ']}', $markup );
}
function rbrow( $query_id, $type, $per, $term_id, $cls = 'aw-camp-row-q' ) {
	$m = '<!-- wp:query {"queryId":' . $query_id . ',"query":{"perPage":' . $per . ',"pages":0,"offset":0,"postType":"' . $type . '","order":"desc","orderBy":"date","inherit":false,%TQ%},"className":"' . $cls . '"} -->
<div class="wp-block-query ' . $cls . '"><!-- wp:post-template -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-md","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-md"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->
<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/read-meta /-->
<!-- wp:animal-writes/badges {"state":"block-md"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->';
	return rbq( $m, $term_id ) . "\n";
}

if ( getenv( 'AW_RENDER' ) ) {
	$term_id = (int) getenv( 'AW_TERM_ID' );
} else {
	$term_obj = get_term_by( 'slug', RB_TERM, KITS_CAMPAIGN_LINK );
	if ( ! $term_obj ) {
		$inserted = wp_insert_term( 'Rabies Awareness India', KITS_CAMPAIGN_LINK, array( 'slug' => RB_TERM ) );
		if ( is_wp_error( $inserted ) ) {
			WP_CLI::error( $inserted->get_error_message() );
		}
		$term_id = (int) $inserted['term_id'];
	} else {
		$term_id = (int) $term_obj->term_id;
	}
}

/* 1. Hero: calm, urgent, public-health tone; no petition or letter tool. */
$cover_url = wp_get_attachment_image_url( RB_PHOTO, 'full' ) ?: '/wp-content/uploads/2026/11/world-rabies-day-health-centre-vaccine-table-2.webp';
$cover_alt = get_post_meta( RB_PHOTO, '_wp_attachment_image_alt', true ) ?: 'A blue vaccine cool box, a tray of cotton balls and papers on a wooden table by a window.';
$cover     = array(
	'url'                => $cover_url,
	'id'                 => RB_PHOTO,
	'alt'                => $cover_alt,
	'dimRatio'           => 55,
	'overlayColor'       => 'foreground',
	'isUserOverlayColor' => true,
	'focalPoint'         => array( 'x' => 0.7, 'y' => 0.7 ),
	'minHeight'          => 660,
	'contentPosition'    => 'center left',
	'isDark'             => true,
	'anchor'             => 'top',
	'className'          => 'kit-hero aw-camp-hero',
	'align'              => 'full',
	'style'              => array( 'spacing' => array( 'padding' => array( 'right' => '0', 'left' => '0' ) ) ),
	'layout'             => array( 'type' => 'constrained', 'contentSize' => '1120px' ),
);

$tips = '<!-- wp:list {"className":"aw-festive-tips"} -->' . "\n" . '<ul class="wp-block-list aw-festive-tips">';
foreach ( array(
	array( 'alert', 'Wash any bite or scratch with soap under running water for 15 minutes' ),
	array( 'calendar', 'Start the anti-rabies vaccine the same day — free at government hospitals' ),
	array( 'shield', 'Ask for RIG into the wound for every bleeding bite or deep scratch' ),
	array( 'paw', 'Vaccinate and sterilise community dogs under ABC Rules, 2023' ),
) as $t ) {
	$tips .= '<!-- wp:list-item -->' . "\n" . '<li><span class="aw-festive-ico">' . rbi( $t[0] ) . '</span>' . $t[1] . '</li>' . "\n" . '<!-- /wp:list-item -->' . "\n";
}
$tips .= '</ul>' . "\n" . '<!-- /wp:list -->' . "\n\n";

$hero = '<!-- wp:cover ' . wp_json_encode( $cover, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->' . "\n"
	. '<div id="top" class="wp-block-cover alignfull is-dark has-custom-content-position is-position-center-left kit-hero aw-camp-hero" style="padding-right:0;padding-left:0;min-height:660px">'
	. '<span aria-hidden="true" class="wp-block-cover__background has-foreground-background-color has-background-dim-55 has-background-dim"></span>'
	. '<img class="wp-block-cover__image-background wp-image-' . RB_PHOTO . '" alt="' . esc_attr( $cover['alt'] ) . '" src="' . esc_url( $cover['url'] ) . '" style="object-position:70% 70%" data-object-fit="cover" data-object-position="70% 70%"/>'
	. '<div class="wp-block-cover__inner-container">'
	. '<!-- wp:group {"style":{"spacing":{"padding":{"right":"0","left":"0"}}},"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->' . "\n" . '<div class="wp-block-group" style="padding-right:0;padding-left:0">'
	. rbsc( '[aw_camp_status]' )
	. rbh( 'Rabies is 100% preventable. Know what to do in the first 15 minutes.', 1 )
	. rbp( 'Once symptoms appear, rabies is nearly always fatal — yet prompt wound washing, same-day vaccination, and mass dog vaccination stop it completely.' )
	. $tips
	. '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons">'
	. '<!-- wp:button {"className":"is-style-kits-brand"} -->' . "\n" . '<div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="#first-aid">First aid &amp; vaccine guide</a></div>' . "\n" . '<!-- /wp:button -->' . "\n\n"
	. '<!-- wp:button {"className":"is-style-kits-secondary"} -->' . "\n" . '<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="#helplines">Helplines &amp; hospitals</a></div>' . "\n" . '<!-- /wp:button -->'
	. '</div>' . "\n" . '<!-- /wp:buttons -->'
	. '</div>' . "\n" . '<!-- /wp:group -->'
	. '</div></div>' . "\n" . '<!-- /wp:cover -->' . "\n\n";

/* 2. Context: public health reality + One Health approach in India. */
$context = rbsec(
	'context',
	rbsplit(
		rbh( 'Why this matters in India' )
		. rbp( 'Rabies is almost always fatal once clinical symptoms start, but it is completely preventable if you wash the wound immediately and start post-exposure prophylaxis (PEP) on time. In India, dogs account for around 95% of human rabies cases, and every animal bite or scratch that breaks the skin should be treated without waiting.' )
		. rbp( 'Stopping rabies also protects street dogs. Under the Animal Birth Control Rules, 2023, healthy community dogs are sterilised, dewormed, vaccinated against rabies, and released back to their territory — culling or relocating dogs does not control rabies and violates the law.' )
		. rbmore( 'Read the full guide: Rabies after a bite in India', '/resources/rabies-after-a-bite-india/' ),
		RB_PHOTO,
		'70% 70%'
	),
	'aw-camp-white'
);

/* 3. Why it matters: NCDC 2019 categories, schedules, and ABC Rules 2023. */
$lcard = function ( $anchor, $cls, $icon, $title, $body, $rest = '' ) {
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	$an = $anchor ? '"anchor":"' . $anchor . '",' : '';
	return "<!-- wp:group {{$an}\"className\":\"$cls\",\"layout\":{\"type\":\"default\"}} -->\n<div$id class=\"wp-block-group $cls\">" . rbp( rbi( $icon ), 'aw-camp-card-ico' ) . rbh( $title, 3 ) . $body . ( $rest ? rbfold( $title, $rest ) : '' ) . "</div>\n<!-- /wp:group -->\n\n";
};

$why = rbsec(
	'guidelines',
	rbh( 'Indian medical guidelines &amp; law' )
	. rbp( 'Based on the National Centre for Disease Control (NCDC) National Guidelines for Rabies Prophylaxis (2019), WHO guidance, and the ABC Rules, 2023.', 'aw-camp-lede' )
	. "<!-- wp:group {\"className\":\"aw-camp-grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-camp-grid\">"
	. $lcard(
		'categories',
		'aw-camp-card',
		'alert',
		'Exposure categories (I, II, III)',
		rbp( '<strong>Category I</strong> (licks on intact skin): wash with soap and water; no vaccine needed. <strong>Category II</strong> (minor scratches or abrasions without bleeding): wash 15 minutes + start anti-rabies vaccine. <strong>Category III</strong> (bleeding bites, deep scratches, saliva on broken skin or eyes/mouth, or any wild-animal bite): wash 15 minutes + vaccine + Rabies Immunoglobulin (RIG).' ),
		rbp( 'India is stricter than WHO on RIG: NCDC 2019 requires RIG for <strong>all</strong> Category III exposures, and also for Category II exposures in immunocompromised patients. RIG should be infiltrated into and around every wound as soon as possible (up to day 7 after the first vaccine dose).' )
	)
	. $lcard(
		'schedule',
		'aw-camp-card',
		'calendar',
		'Approved vaccine schedules',
		rbp( 'Government hospitals provide two NCDC-approved schedules free under the National Health Mission: <strong>Intradermal (Updated Thai Red Cross 2-2-2-0-2)</strong> — 2 skin injections per visit on <strong>Days 0, 3, 7, and 28</strong>; or <strong>Intramuscular (Essen 1-1-1-1-1)</strong> — 5 doses on <strong>Days 0, 3, 7, 14, and 28</strong>.' ),
		rbp( 'Day 0 is the date of the first vaccine dose — even if the bite happened days or weeks earlier, start immediately and finish every scheduled dose. Vaccine is given in the upper arm (deltoid), or anterolateral thigh in young children — never in the buttock.' )
	)
	. $lcard(
		'abc-law',
		'aw-camp-card',
		'paw',
		'Community dogs &amp; ABC Rules, 2023',
		rbp( 'Rule 33(b) of the Animal Birth Control Rules, 2023 requires municipalities to sterilise, deworm, vaccinate against rabies, and release healthy community dogs back to the exact area where they were picked up.' ),
		rbp( 'Dogs suspected of having rabies or showing severe clinical signs must be humanely isolated and never released back. Relocating or harming healthy community dogs breaks the law and disrupts vaccinated territories.' )
	)
	. "</div>\n<!-- /wp:group -->\n\n"
	. rbmore( 'Full dog-bite vaccine &amp; RIG schedule', '/resources/dog-bite-rabies-vaccine/' ),
	'aw-camp-grey'
);

/* 4. How to help / First aid & helplines. */
$first_aid = rbsec(
	'first-aid',
	rbh( 'What to do immediately after a bite or scratch' )
	. rbp( 'Every minute counts. Follow these steps in order — do not wait to see if the dog stays healthy.', 'aw-camp-lede' )
	. rbtable(
		array( 'Step', 'What to do (and avoid)' ),
		array(
			array( '1. Wash (15 min)', 'Flush gently under running tap water with soap for at least 15 minutes, then apply povidone-iodine or alcohol. Never apply chillies, turmeric, oil, lime, herbs, or soil.' ),
			array( '2. Leave open', 'Keep the wound open and uncovered — do not stitch or tightly bandage bite wounds immediately (unless life-threatening bleeding requires minimal loose sutures after RIG).' ),
			array( '3. Same-day PEP', 'Visit the nearest government hospital or anti-rabies clinic the same day for ARV (and RIG for Category III). Check tetanus status too. Never wait 10 days to observe the dog.' ),
			array( '4. Complete doses', 'Finish all 4 ID visits (Days 0, 3, 7, 28) or 5 IM doses (Days 0, 3, 7, 14, 28). Do not stop halfway even if the wound heals.' ),
		)
	)
	. rbh( 'Who to call', 3, 'helplines' )
	. rbp( '<strong>National Rabies Helpline:</strong> <strong>15400</strong> (16x7, select states) · <strong>Medical Emergency:</strong> <strong>108</strong> or <strong>112</strong> · <strong>Animal Rescue / Veterinary Helpline:</strong> <strong>1962</strong> or your municipal ABC centre. ' . rba( 'Find local animal helplines and NGOs in the Directory', '/directory/' ) . '.' ),
	'aw-camp-white'
);

/* 5. FAQ (Rank Math FAQ block — single FAQPage schema on the page). */
$faq = rbsec(
	'faq',
	rbh( 'Questions about rabies after a bite' )
	. rbfaq(
		array(
			array(
				'Does every dog bite cause rabies?',
				'No, most dogs do not have rabies. However, in India NCDC guidelines treat every mammal bite or scratch that breaks the skin as potentially rabid — you cannot tell by looking at the animal, so wash the wound and start vaccination the same day.',
			),
			array(
				'What if the dog is vaccinated or a pet?',
				'Start the anti-rabies vaccine anyway unless a doctor confirms a documented, up-to-date vaccination history and guides otherwise. NCDC warns that vaccination status cannot be presumed without proof.',
			),
			array(
				'What if it happened days, weeks, or months ago?',
				'Go to a clinic anyway. The incubation period is typically 2–3 months (ranging from 1 week to 1 year or more), and NCDC guidelines state that anyone presenting late must still receive wound care and full PEP.',
			),
			array(
				'Is the anti-rabies vaccine free in India?',
				'Yes. Both the anti-rabies vaccine (ARV) and rabies immunoglobulin (RIG) are provided free of cost at government hospitals and primary health centres under the National Health Mission Free Drug Initiative.',
			),
			array(
				'Do rat, mouse, or squirrel bites need the rabies vaccine?',
				'Bites from domestic house rats and mice do not ordinarily need PEP in India, though you should still wash the wound and see a doctor for tetanus/infection care. PEP is needed if the bite happened in a forest area or if the animal could have been a mongoose or wild rodent.',
			),
			array(
				'Can a cat scratch or lick cause rabies?',
				'Yes. Any scratch or abrasion from a cat or dog that breaks the skin (Category II or III), or saliva on broken skin, eyes, or mouth, requires immediate 15-minute washing and PEP.',
			),
		)
	),
	'aw-camp-grey'
);

/* 6. Linked News, Guides, Library, Press, Social, Partners, Next steps, Share. */
$news = rbsec(
	'latest',
	rbhtml( '<header class="kit-ed-head"><div><h2>Rabies guides &amp; news</h2></div><a class="kit-ed-more" href="/resources/">All guides ' . rbi( 'arrow-right' ) . '</a></header>' )
	. '<!-- wp:group {"className":"aw-camp-row","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group aw-camp-row">'
	. rbrow( 101, 'aw_news', 6, $term_id )
	. rbrow( 102, 'post', 8, $term_id )
	. '</div>' . "\n" . '<!-- /wp:group -->' . "\n\n",
	'aw-camp-white'
);

$library = rbsec(
	'read',
	rbh( 'Primary sources &amp; law' )
	. rbp( 'National Guidelines for Rabies Prophylaxis (NCDC/MoHFW), WHO Rabies Fact Sheet, and the ABC Rules, 2023 in our Library.', 'aw-camp-lede' )
	. rbq(
		'<!-- wp:query {"queryId":103,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false,%TQ%},"className":"aw-camp-docs-q kit-ico--file"} -->
<div class="wp-block-query aw-camp-docs-q kit-ico--file"><!-- wp:post-template -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--stacked-sm","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--stacked-sm"><!-- wp:post-featured-image {"isLink":false,"className":"kit-card-media"} /-->
<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/badges {"state":"stacked-sm"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->',
		$term_id
	),
	'aw-camp-grey'
);

$press = rbsec(
	'',
	rbhtml( '<header class="kit-ed-head"><div><h2>On social media</h2><p class="aw-camp-lede">Public health updates and community vaccination drives.</p></div><a class="kit-ed-more" href="/press-coverage/">See all ' . rbi( 'arrow-right' ) . '</a></header>' )
	. rbrow( 104, 'aw_press', 12, $term_id, 'aw-camp-row aw-camp-row-q' ),
	'aw-camp-white'
);

$next_steps = rbsec(
	'next',
	rbh( 'Four actions that make neighbourhoods rabies-free' )
	. rbhtml(
		'<ol class="aw-camp-steps">'
		. '<li><strong>First 15 minutes</strong><span>Wash any bite or scratch with soap and running water immediately</span></li>'
		. '<li><strong>Same-day PEP</strong><span>Get ARV (and RIG for Category III) at a government hospital</span></li>'
		. '<li><strong>Vaccinate community dogs</strong><span>Support your local ABC sterilisation &amp; anti-rabies drive</span></li>'
		. '<li><strong>Share verified facts</strong><span>Send this page to your RWA group, school, and neighbours</span></li>'
		. '</ol>'
	),
	'aw-camp-grey'
);

$share = rbsec(
	'share',
	rbh( 'Share this with your RWA and neighbours' )
	. rbp( 'Knowing the 15-minute wash rule and where to get free ARV/RIG saves lives — and keeps vaccinated community dogs safe from panic and relocation.', 'aw-camp-lede' )
	. rbhtml( '<div class="aw-camp-share-kit" data-aw-share></div>' )
	. rbp( rbi( 'mail' ) . 'Sign up with the newsletter form below for updates on public health guidance and community vaccination drives.', 'aw-camp-note' ),
	'aw-camp-tint'
);

$partners = rbsec( '', rbsc( '[aw_campaign_partners title="Partners"]' ), 'aw-camp-white' );

$content = '<!-- wp:group {"align":"full","className":"aw-camp aw-camp--rabies","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignfull aw-camp aw-camp--rabies">'
	. rbslot( 'hero', $hero )
	. rbslot( 'context', $context )
	. rbslot( 'why-it-matters', $why )
	. rbslot( 'how-to-help', $first_aid )
	. rbslot( 'faq', $faq )
	. rbslot( 'news', $news )
	. rbslot( 'library', $library )
	. rbslot( 'partners', $partners, 'aw-camp-if-filled' )
	. rbslot( 'press', $press, 'aw-camp-if-items' )
	. rbslot( 'people', rbsec( '', '<!-- wp:animal-writes/social-posts /-->', 'aw-camp-white' ) )
	. rbslot( 'next-steps', $next_steps )
	. rbslot( 'updates', $share )
	. rbsc( '[aw_sticky_cta href="#first-aid" label="First aid after a bite" days="0"]' )
	. "</div>\n<!-- /wp:group -->";

$title   = 'Rabies is 100% preventable: First aid, vaccine schedule & ABC';
$excerpt = 'What to do in the first 15 minutes after a dog or cat bite in India, NCDC 2019 vaccine & RIG schedules, free government clinic care, and how ABC vaccination stops rabies.';
$meta    = array(
	'aw_campaign_open'         => gmdate( 'Y-m-d', time() + 19800 ),
	'aw_campaign_share_text'   => 'Bitten or scratched? Wash with soap under running water for 15 minutes and start the anti-rabies vaccine the same day — free at government hospitals.',
	'aw_campaign_impact_state' => 'na',
	'aw_campaign_outcome'      => 'ongoing',
);
$seo     = array(
	'rank_math_title'         => 'Rabies Awareness & Bite First Aid in India – Animal Writes',
	'rank_math_description'   => 'Rabies is 100% preventable: 15-minute soap wash, free NCDC 2019 vaccine & RIG at government hospitals, helplines (15400, 108, 1962), and ABC vaccination.',
	'rank_math_focus_keyword' => 'rabies awareness india,dog bite rabies vaccine,rabies first aid,anti rabies injection schedule',
);

if ( getenv( 'AW_RENDER' ) ) {
	echo json_encode( // phpcs:ignore -- command-line output
		array(
			'post' => array(
				'slug'             => RB_SLUG,
				'status'           => 'draft',
				'title'            => $title,
				'excerpt'          => $excerpt,
				'content'          => $content,
				'template'         => 'campaign-page',
				'featured_media'   => RB_PHOTO,
				KITS_CAMPAIGN_LINK => array( $term_id ),
				'meta'             => $meta,
			),
			'seo'  => $seo,
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	return;
}

$existing = get_page_by_path( RB_SLUG, OBJECT, 'aw_campaign' );
$post     = array(
	'post_type'    => 'aw_campaign',
	'post_name'    => RB_SLUG,
	'post_title'   => $title,
	'post_excerpt' => $excerpt,
	'post_content' => $content,
	'post_status'  => $existing ? $existing->post_status : 'draft',
);
if ( $existing ) {
	$post['ID']            = $existing->ID;
	$post['post_date']     = $existing->post_date;
	$post['post_date_gmt'] = $existing->post_date_gmt;
	unset( $meta['aw_campaign_open'] );
}
$id = wp_insert_post( wp_slash( $post ), true );
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
wp_set_object_terms( $id, array( $term_id ), KITS_CAMPAIGN_LINK, true );
wp_set_object_terms( $id, array( 'campaigns-civic-action', 'rabies-public-health', 'community-dogs' ), 'category', false );
set_post_thumbnail( $id, RB_PHOTO );
update_term_meta( $term_id, 'aw_campaign_page', $id );
foreach ( array_merge( array( '_wp_page_template' => 'campaign-page' ), $meta, $seo ) as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
$primary_cat = get_term_by( 'slug', 'campaigns-civic-action', 'category' );
if ( $primary_cat ) {
	update_post_meta( $id, 'rank_math_primary_category', (string) $primary_cat->term_id );
}

// Map all existing rabies guides, news, library items, and press coverage into this campaign
$mapped_count = 0;
$rabies_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'aw_news', 'aw_legal', 'aw_press' ),
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		's'              => 'rabies',
	)
);
$tagged_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'aw_news', 'aw_legal', 'aw_press' ),
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'tax_query'      => array(
			'relation' => 'OR',
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => array( 'rabies-public-health' ),
			),
			array(
				'taxonomy' => 'post_tag',
				'field'    => 'slug',
				'terms'    => array( 'rabies', 'anti-rabies', 'dog-bite' ),
			),
		),
	)
);
$legal_posts = get_posts(
	array(
		'post_type'      => 'aw_legal',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'fields'         => 'ids',
		'post_name__in'  => array(
			'who-rabies-fact-sheet',
			'animal-birth-control-rules-2023',
			'prevention-of-cruelty-to-animals-act-1960',
		),
	)
);
$all_rabies_ids = array_unique( array_merge( $rabies_posts, $tagged_posts, $legal_posts ) );
foreach ( $all_rabies_ids as $pid ) {
	wp_set_object_terms( (int) $pid, array( $term_id ), KITS_CAMPAIGN_LINK, true );
	$mapped_count++;
}

// Register campaign-rabies-india in aw_sources['topic_tags'] so future news & aw_press auto-link
$aw_sources = get_option( 'aw_sources', array() );
if ( is_array( $aw_sources ) ) {
	if ( ! isset( $aw_sources['topic_tags'] ) || ! is_array( $aw_sources['topic_tags'] ) ) {
		$aw_sources['topic_tags'] = array();
	}
	if ( empty( $aw_sources['topic_tags'][ RB_TERM ] ) ) {
		$aw_sources['topic_tags'][ RB_TERM ] = array(
			'any' => array(
				'rabies',
				'anti-rabies',
				'dog bite',
				'rabies vaccine',
				'rabies immunoglobulin',
				'nrcp',
				'रेबीज',
			),
		);
		update_option( 'aw_sources', $aw_sources );
	}
}

WP_CLI::success(
	sprintf(
		'%s rabies campaign %d (%s), mapped %d related content items: %s',
		$existing ? 'updated' : 'created',
		$id,
		get_post_status( $id ),
		$mapped_count,
		get_preview_post_link( $id )
	)
);
