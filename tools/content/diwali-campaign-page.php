<?php
/**
 * Diwali 2026 campaign page (firecrackers and animals): an awareness campaign in the campaign layout, sections in the
 * Campaign Content Standard v1.0 order (hero, context, why-it-matters, how-to-help, faq, news, library, press,
 * partners, next-steps, updates). No letter tool, count or campaign document. Open until Diwali (8 Nov 2026); from
 * 9 Nov the page is in its Post state: the closed hero and the after-Diwali help lead, and night-time sections hide.
 *
 * Two ways to run it:
 *   wp eval-file diwali-campaign-page.php      creates or updates the campaign as a DRAFT (never publishes)
 *   php diwali-campaign-page.php               with AW_RENDER=1, prints the REST payload (JSON) instead; needs the
 *                                              small WordPress stand-ins from AW_RENDER_SHIM (images, terms)
 */

const DW_SLUG  = 'diwali-firecrackers-animals-2026';
const DW_TERM  = 'campaign-diwali-firecrackers-2026';
const DW_HERO  = 14272; // a person feeding two street dogs by the roadside (the featured image)
const DW_PHOTO = 23397; // hero photo, wide: a woman sets down water for a street dog on Diwali night
const DW_PHOTO_TALL = 23398; // the same scene, tall (phones)
const DW_ART   = 23348; // a dog resting calmly at home on Diwali evening
const DW_DIWALI = '2026-11-08';

if ( getenv( 'AW_RENDER' ) ) {
	require getenv( 'AW_RENDER_SHIM' );
}

function dwp( $t, $cls = '' ) {
	$a = $cls ? ' {"className":"' . $cls . '"}' : '';
	$c = $cls ? ' class="' . $cls . '"' : '';
	return "<!-- wp:paragraph$a -->\n<p$c>$t</p>\n<!-- /wp:paragraph -->\n\n";
}
function dwh( $t, $level = 2, $anchor = '' ) {
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
function dwsc( $sc ) {
	return "<!-- wp:shortcode -->\n$sc\n<!-- /wp:shortcode -->\n\n";
}
function dwlist( $items ) {
	$li = '';
	foreach ( $items as $i ) {
		$li .= "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n";
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->\n\n";
}
function dwhtml( $h ) {
	return "<!-- wp:html -->\n$h\n<!-- /wp:html -->\n\n";
}
function dwi( $n ) {
	return '<span class="kit-ico kit-ico--' . $n . '" aria-hidden="true"></span>';
}
function dwa( $text, $href ) {
	return '<a href="' . $href . '">' . $text . '</a>';
}
function dwmore( $text, $href ) {
	return dwp( '<a class="aw-camp-more" href="' . $href . '">' . $text . ' ' . dwi( 'arrow-right' ) . '</a>' );
}
/** A card's fold (core Details, closed): the card's own heading is the summary, so no new words; the rest of the card
    stays in the page (a link to something inside opens it, kit.js). Keeps a card at most 600px tall (Layout rules). */
function dwfold( $summary, $inner ) {
	return '<!-- wp:details {"className":"aw-camp-card-fold"} -->' . "\n" . '<details class="wp-block-details aw-camp-card-fold"><summary>' . $summary . '</summary>' . $inner . '</details>' . "\n" . '<!-- /wp:details -->' . "\n\n";
}
/** A full-width band; $cls adds its style (aw-camp-hero, -white, -grey, -dark, -tint) and state (aw-camp-open, -closed). */
function dwsec( $anchor, $inner, $cls = '' ) {
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
/** A section of the Campaign Content Standard: the slug is its anchor id. */
function dwslot( $slug, $inner, $cls = '' ) {
	$classes = trim( 'aw-camp-slot aw-camp-slot--' . $slug . ' ' . $cls );
	$attrs   = array( 'anchor' => $slug, 'align' => 'full', 'className' => $classes, 'layout' => array( 'type' => 'default' ) );
	return '<!-- wp:group ' . wp_json_encode( $attrs ) . " -->\n<div id=\"$slug\" class=\"wp-block-group alignfull $classes\">$inner</div>\n<!-- /wp:group -->\n\n";
}
function dwbuttons( $btns ) {
	$b = '';
	foreach ( $btns as $i => $x ) { // $x[2]: 'ghost' (on the hero's colour) or 'outline' (on a white or grey band)
		$cls = $i > 0 && ! empty( $x[2] ) ? ( 'ghost' === $x[2] ? 'is-style-outline aw-camp-ghost' : 'is-style-outline' ) : '';
		$b  .= $cls
			? "<!-- wp:button {\"className\":\"$cls\"} -->\n<div class=\"wp-block-button $cls\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$x[1]}\">{$x[0]}</a></div>\n<!-- /wp:button -->\n"
			: "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$x[1]}\">{$x[0]}</a></div>\n<!-- /wp:button -->\n";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">$b</div>\n<!-- /wp:buttons -->\n\n";
}
function dwtable( $head, $rows ) {
	$h = '<tr><th>' . implode( '</th><th>', $head ) . '</th></tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><thead>$h</thead><tbody>$b</tbody></table></figure>\n<!-- /wp:table -->\n\n";
}
function dwfaq( $qa ) {
	$qs   = array();
	$html = '';
	foreach ( $qa as $x ) {
		$qs[]  = array( 'id' => 'faq-' . substr( md5( $x[0] ), 0, 10 ), 'title' => $x[0], 'content' => $x[1], 'visible' => true );
		$html .= '<div class="rank-math-faq-item"><h3 class="rank-math-question">' . $x[0] . '</h3><div class="rank-math-answer">' . $x[1] . '</div></div>';
	}
	return '<!-- wp:rank-math/faq-block ' . wp_json_encode( array( 'questions' => $qs ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-rank-math-faq-block">' . $html . "</div><!-- /wp:rank-math/faq-block -->\n\n";
}
function dwsplit( $text, $img_id, $pos = 'center' ) {
	$img = wp_get_attachment_image( $img_id, 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'style' => 'object-position:' . $pos ) );
	return '<!-- wp:columns {"verticalAlignment":"center","className":"aw-camp-split"} -->' . "\n" . '<div class="wp-block-columns are-vertically-aligned-center aw-camp-split">'
		. '<!-- wp:column {"verticalAlignment":"center","width":"58%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58%">' . $text . '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
		. '<!-- wp:column {"verticalAlignment":"center","width":"42%"} -->' . "\n" . '<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:42%">' . dwhtml( '<figure class="aw-camp-art">' . $img . '</figure>' ) . '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
		. '</div>' . "\n" . '<!-- /wp:columns -->' . "\n\n";
}
function dwq( $markup, $term_id ) {
	return str_replace( '%TQ%', '"taxQuery":{"aw_campaign_link":[' . (int) $term_id . ']}', $markup );
}
/** A query row of cards (the lab campaign's card markup). */
function dwrow( $query_id, $type, $per, $term_id, $cls = 'aw-camp-row-q' ) {
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
	return dwq( $m, $term_id ) . "\n";
}

$term_id = getenv( 'AW_RENDER' ) ? (int) getenv( 'AW_TERM_ID' ) : (int) ( get_term_by( 'slug', DW_TERM, 'aw_campaign_link' )->term_id ?? 0 );
if ( ! $term_id ) {
	$msg = 'Campaign link ' . DW_TERM . ' not found; create it first';
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::error( $msg );
	}
	fwrite( STDERR, $msg . "\n" );
	exit( 1 );
}

/* hero: open (until 8 Nov) and closed (from 9 Nov); one shows. The open hero is the theme's page hero: a core Cover
   (kit-hero: its height and padding come from the theme) with the night photo, the text in a 560px column on the left,
   the four tips as the theme's icon tip list and two Kits buttons. No countdown. */
$cover = array(
	'url'             => wp_get_attachment_image_url( DW_PHOTO, 'full' ),
	'id'              => DW_PHOTO,
	'alt'             => get_post_meta( DW_PHOTO, '_wp_attachment_image_alt', true ),
	'dimRatio'        => 50,
	'overlayColor'    => 'foreground',
	'isUserOverlayColor' => true,
	'focalPoint'      => array( 'x' => 0.7, 'y' => 0.6 ),
	'minHeight'       => 660,
	'contentPosition' => 'center left',
	'isDark'          => true,
	'anchor'          => 'top',
	'className'       => 'kit-hero aw-camp-hero aw-camp-open',
	'align'           => 'full',
	'style'           => array( 'spacing' => array( 'padding' => array( 'right' => '0', 'left' => '0' ) ) ),
	'layout'          => array( 'type' => 'constrained', 'contentSize' => '1120px' ),
);
$tips = '<!-- wp:list {"className":"aw-festive-tips"} -->' . "\n" . '<ul class="wp-block-list aw-festive-tips">';
foreach ( array( array( 'home', 'Keep pets indoors' ), array( 'paw', 'Keep water out for the dogs outside' ), array( 'name', 'Use an ID tag' ), array( 'phone', 'Know who to call' ) ) as $t ) {
	$tips .= '<!-- wp:list-item -->' . "\n" . '<li><span class="aw-festive-ico">' . dwi( $t[0] ) . '</span>' . $t[1] . '</li>' . "\n" . '<!-- /wp:list-item -->' . "\n";
}
$tips .= '</ul>' . "\n" . '<!-- /wp:list -->' . "\n\n";
$hero = '<!-- wp:cover ' . wp_json_encode( $cover, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->' . "\n"
	. '<div id="top" class="wp-block-cover alignfull is-dark has-custom-content-position is-position-center-left kit-hero aw-camp-hero aw-camp-open" style="padding-right:0;padding-left:0;min-height:660px">'
	. '<span aria-hidden="true" class="wp-block-cover__background has-foreground-background-color has-background-dim-50 has-background-dim"></span>'
	. '<img class="wp-block-cover__image-background wp-image-' . DW_PHOTO . '" alt="' . esc_attr( $cover['alt'] ) . '" src="' . esc_url( $cover['url'] ) . '" style="object-position:70% 60%" data-object-fit="cover" data-object-position="70% 60%"/>'
	. '<div class="wp-block-cover__inner-container">'
	. '<!-- wp:group {"style":{"spacing":{"padding":{"right":"0","left":"0"}}},"layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->' . "\n" . '<div class="wp-block-group" style="padding-right:0;padding-left:0">'
	. dwsc( '[aw_camp_status]' )
	. dwh( 'Diwali is loud. Keep pets and street dogs safe.', 1 )
	. dwp( 'Firecrackers frighten and hurt pets, street animals and birds.' )
	. $tips
	. '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons">'
	. '<!-- wp:button {"className":"is-style-kits-brand"} -->' . "\n" . '<div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="#tonight">What to do tonight</a></div>' . "\n" . '<!-- /wp:button -->' . "\n\n"
	. '<!-- wp:button {"className":"is-style-kits-secondary"} -->' . "\n" . '<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="#help">Who to call</a></div>' . "\n" . '<!-- /wp:button -->'
	. '</div>' . "\n" . '<!-- /wp:buttons -->'
	. '</div>' . "\n" . '<!-- /wp:group -->'
	. '</div></div>' . "\n" . '<!-- /wp:cover -->' . "\n\n";
$hero_closed = dwsec(
	'',
	dwsc( '[aw_camp_status]' )
	. dwh( 'Diwali has passed. Burnt, lost and frightened animals still need you.', 1 )
	. dwp( 'The days after Diwali bring burnt paws, stunned birds and pets that bolted. Here is who to call and what to do first.', 'aw-camp-hero-summary' )
	. dwp( 'Our aim was no animal near you hurt, lost or left without help this Diwali.' )
	. dwbuttons( array( array( 'Who to call', '#help' ), array( 'Lost or injured animal', '#after', 'ghost' ) ) ),
	'aw-camp-hero aw-camp-closed'
);

/* context: what is happening, with the three dates. */
$what = dwsec(
	'what',
	dwsplit(
		dwh( 'What\'s happening' )
		. dwp( 'Diwali falls on Sunday 8 Nov 2026, with Govardhan Puja on 9 Nov 2026 and Bhai Dooj on 10 Nov 2026. For most animals it is the loudest week of the year. Dogs, cats, cattle and birds hear bangs more sharply than we do, and they cannot know the noise will stop. Frightened animals bolt into traffic, hide under cars, stop eating, or get burnt by crackers thrown near them. This page is what to do before, on the night and after.' )
		. dwhtml(
			'<ol class="aw-camp-dates">'
			. '<li class="is-key"><span class="aw-camp-dates-when">8 Nov 2026</span><span class="aw-camp-dates-what">Diwali (Lakshmi Puja)</span></li>'
			. '<li><span class="aw-camp-dates-when">9 Nov 2026</span><span class="aw-camp-dates-what">Govardhan Puja</span></li>'
			. '<li><span class="aw-camp-dates-when">10 Nov 2026</span><span class="aw-camp-dates-what">Bhai Dooj</span></li>'
			. '</ol>'
		)
		. dwmore( 'In the news: Diwali with pets, a Hindustan Times guide', '/news/diwali-with-pets-a-guide-to-keep-your-furry-friends-safe-and-comfortable-during-the-festival-of-lights/' ),
		DW_ART
	),
	'aw-camp-white'
);

/* why-it-matters: the law as three campaign cards (the firecracker rules card expires, so it shows only while open). */
$lcard = function ( $anchor, $cls, $icon, $title, $body, $rest = '' ) { // $rest: the paragraphs after the first, in the card's fold
	$id = $anchor ? ' id="' . $anchor . '"' : '';
	$an = $anchor ? '"anchor":"' . $anchor . '",' : '';
	return "<!-- wp:group {{$an}\"className\":\"$cls\",\"layout\":{\"type\":\"default\"}} -->\n<div$id class=\"wp-block-group $cls\">" . dwp( dwi( $icon ), 'aw-camp-card-ico' ) . dwh( $title, 3 ) . $body . ( $rest ? dwfold( $title, $rest ) : '' ) . "</div>\n<!-- /wp:group -->\n\n";
};
$law = dwsec(
	'law',
	dwh( 'What the law says' )
	. dwp( 'Hurting an animal with firecrackers is a crime. This is general information, not legal advice.', 'aw-camp-lede' )
	. "<!-- wp:group {\"className\":\"aw-camp-grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-camp-grid\">"
	. $lcard( '', 'aw-camp-card', 'legal', 'Cruelty', dwp( 'Treating an animal so as to cause it unnecessary pain or suffering is an offence under section 11(1)(a) of the Prevention of Cruelty to Animals Act, 1960: a fine of ₹10 to ₹50 for a first offence, and for a repeat within three years ₹25 to ₹100, up to three months in jail, or both.' ), dwp( 'Killing, poisoning or maiming an animal is also an offence under section 325 of the Bharatiya Nyaya Sanhita, 2023: up to five years in jail, a fine, or both.' ) )
	. $lcard( '', 'aw-camp-card', 'paw', 'Owls and wildlife', dwp( 'Owls are protected under the Wild Life (Protection) Act, 1972. The barn owl, the Indian eagle owl and nine more owls are in Schedule I, its highest protection. Catching or selling an owl is an offence.' ) )
	. $lcard( 'rules', 'aw-camp-card aw-camp-open', 'alert', 'Firecracker rules', dwp( 'Supreme Court orders since 2018 allow only less-polluting "green" crackers, sold through licensed traders, and limit bursting on Diwali to two hours, which the states fix. Noise crackers are banned between 10 pm and 6 am, and no cracker may be louder than 125 dB at four metres.' ), dwp( 'Delhi-NCR has its own orders. The Supreme Court heard the 2026 matter on 30 Sep 2026 and 29 Oct 2026 and said it would pass a balanced order; when we checked on 1 Nov 2026, it had not been published. In Odisha, the State Pollution Control Board\'s guidelines restrict bursting to 7 pm to 9 pm.' ) . dwp( 'States and district magistrates issue their own timings and bans, so check your state\'s or district\'s order before you buy or burst.' ) )
	. "</div>\n<!-- /wp:group -->\n\n"
	. dwp( 'Film it, note the time and place, and report it to the police or on 112.' )
	. dwmore( 'How to report animal cruelty', '/resources/how-to-report-animal-cruelty/' ),
	'aw-camp-grey'
);

/* how-to-help: five action cards, the night checklist, who to call. */
$card = function ( $icon, $title, $text, $more = '', $rest = '' ) { // $rest: the paragraphs after the first, in the card's fold
	return dwp( dwi( $icon ), 'aw-camp-card-ico' ) . dwh( $title, 3 ) . $text . ( $rest ? dwfold( $title, $rest ) : '' ) . $more;
};
$cards = array(
	array( 'before', $card( 'home', 'Before Diwali: pets at home', dwp( 'Make a safe room: an inner room with the windows and curtains shut, bedding, water, familiar toys and the TV or music on. Let the animal hide; don\'t pull it out.' ), '', dwp( 'Put an up-to-date phone number on the collar and check that the microchip is registered with your number. Keep recent photos on your phone. Walk and feed dogs earlier in the evening, before the bursting starts, and on a leash only.' ) . dwp( 'Keep cats indoors. Move bird cages and small pets away from windows and cover the cage partly. If your animal is old, unwell or very anxious, ask your vet before the festival (advice from ' . dwa( 'GADVASU', 'https://www.gadvasu.in/news/3170' ) . ', the Punjab vet university). Save your vet\'s number and Diwali hours now. Never give human medicines.' ) ) ),
	array( 'street', $card( 'paw', 'Community dogs, cats and cows', dwp( 'With your RWA or building\'s permission, leave a gate, parking or stairwell corner open for the dogs that live around you, with water bowls away from where crackers are burst. Ask neighbours not to burst crackers near feeding spots, under parked cars, or near gaushalas, cattle sheds and shelters. Cattle panic in traffic; keep crackers away from them.' ), dwmore( 'Caring for community dogs in your area', '/resources/caring-for-community-dogs-in-your-area/' ), dwp( 'The next morning, check under cars and in drains: frightened animals hide and get stuck.' ) . dwp( '<strong>Message for your RWA group:</strong> "Diwali is this Sunday. Please don\'t burst crackers near the dogs\' corner, under parked cars or near animals. If you see an animal that\'s hurt, call a vet or a rescue group (numbers on animalwrites.in)."' ) ) ),
	array( 'birds', $card( 'heart', 'Birds and owls', dwp( 'Noise and smoke disturb roosting birds, and stunned or injured birds turn up the next morning. Put a bird in a dark, quiet box with air holes, do not feed it, and call a bird helpline number near you.' ), dwmore( 'Injured bird helpline numbers by city', '/resources/injured-bird-helpline-numbers-by-city/' ) . dwmore( 'PTR on high alert against owl poaching ahead of Diwali', '/news/ptr-on-high-alert-against-owl-poaching-ahead-of-diwali/' ), dwp( 'Owls are trapped and sold around Diwali for superstitious rituals. Report a caged or offered owl to the state forest department or the Wildlife Crime Control Bureau. Don\'t release sky lanterns near trees, wetlands or sheds.' ) ) ),
	array( 'night', $card( 'moon', 'On Diwali night', dwp( 'Pets inside before dusk, and all doors and gates shut: pets bolt when guests come and go. Stay with an anxious pet and don\'t scold it. Keep crackers, sparklers and hot ash away from animals.' ), '', dwp( 'No festive sweets: chocolate, grapes and raisins are poisonous to dogs (' . dwa( 'ASPCA Animal Poison Control', 'https://www.aspca.org/pet-care/aspca-poison-control/people-foods-avoid-feeding-your-pets' ) . '); ask a vet if an animal has eaten something. If you burst crackers, use certified green crackers only, in the hours your district allows, and away from animals.' ) ) ),
	array( 'after', $card( 'injured', 'After Diwali: burnt, lost or injured animals', dwp( 'Burnt fur, limping, pawing at the eyes or mouth, shaking or hiding are signs an animal needs a vet. No ointments or home remedies: get it to a vet or a rescue group. A frightened animal may bite, so approach slowly and keep your hands away from its mouth. Move it only if it is in danger where it lies.' ), dwmore( 'Helping an injured animal: moving it safely', '/resources/found-an-injured-animal-what-to-do/' ), dwp( 'If your pet is missing, search nearby hiding places at dawn, tell the guards and your RWA group, post photos on local groups, call the vets and shelters near you, and update your microchip registry. Clear unburnt crackers and wrappers from streets and feeding spots: animals chew them.' ) ) ),
);
$grid = '';
foreach ( $cards as $c ) { // each card has its own anchor, so the hero and FAQ can link to it; before and night cards hide after Diwali
	$cls   = 'aw-camp-card' . ( in_array( $c[0], array( 'before', 'night' ), true ) ? ' aw-camp-open' : '' );
	$grid .= "<!-- wp:group {\"anchor\":\"{$c[0]}\",\"className\":\"$cls\",\"layout\":{\"type\":\"default\"}} -->\n<div id=\"{$c[0]}\" class=\"wp-block-group $cls\">{$c[1]}</div>\n<!-- /wp:group -->\n\n";
}
$todo    = dwsec( 'what-to-do', dwh( 'What to do, and when' ) . "<!-- wp:group {\"className\":\"aw-camp-grid\",\"layout\":{\"type\":\"default\"}} -->\n<div class=\"wp-block-group aw-camp-grid\">$grid</div>\n<!-- /wp:group -->\n\n", 'aw-camp-white' );
$tonight = dwsec(
	'tonight',
	dwh( 'Tonight, in order' )
	. dwhtml(
		'<ol class="aw-camp-steps aw-camp-steps--six">'
		. '<li><strong>Pets inside before dusk</strong><span>Doors, gates and windows shut</span></li>'
		. '<li><strong>Safe room on</strong><span>Curtains drawn, water down, TV or music on</span></li>'
		. '<li><strong>Collar and tag on</strong><span>Your number, readable</span></li>'
		. '<li><strong>Water out for the dogs outside</strong><span>Away from the bursting</span></li>'
		. '<li><strong>Crackers away from animals</strong><span>Sparklers and ash too, and no sweets</span></li>'
		. '<li><strong>An animal is hurt?</strong><span>Call the numbers below</span></li>'
		. '</ol>'
	),
	'aw-camp-dark aw-camp-open'
);
$help = dwsec(
	'help',
	dwh( 'Who to call' )
	. dwtable(
		array( 'Number', 'For' ),
		array(
			array( '<strong>112</strong>', 'Any emergency where people are in danger, including fire and traffic accidents involving animals' ),
			array( '<strong>1962</strong>', 'Mobile Veterinary Unit for sick and injured animals, in the states that run it; hours vary by state' ),
			array( 'Vets, rescue groups and animal ambulances', 'Find them by state and district in our Directory (buttons below)' ),
			array( 'Birds', dwa( 'Injured bird helpline numbers by city', '/resources/injured-bird-helpline-numbers-by-city/' ) . ' (Gujarat, Delhi, Mumbai and Thane, Bengaluru)' ),
			array( 'Owls and wildlife', 'Your state forest department (1926 in states that run a forest helpline) or the Wildlife Crime Control Bureau, ' . dwa( 'wccb.gov.in', 'https://wccb.gov.in/' ) ),
			array( 'Cruelty', 'The police or 112; Animal Welfare Board of India 0129-2555700 for complaints and guidance' ),
		)
	)
	. dwbuttons(
		array(
			array( 'Vets near you', '/directory/?tag=veterinary' ),
			array( 'Animal ambulances', '/directory/?tag=animal-ambulance', 'outline' ),
			array( 'Rescue groups', '/directory/?tag=rescue-group', 'outline' ),
			array( 'Gaushalas', '/directory/?tag=gaushala', 'outline' ),
		)
	)
	. dwp( dwi( 'helpline' ) . '1962 is a state service for animals, not a national number, and in several states it answers only in the day. Call a vet or rescue group in the Directory if it does not answer.', 'aw-camp-note' )
	. '<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->' . "\n" . '<div class="wp-block-group">'
	. dwmore( 'What 1962 is and where it runs', '/resources/1962-helpline-mobile-veterinary-units/' )
	. dwmore( 'Who to call for an animal emergency in India', '/resources/who-to-call-for-an-animal-emergency-in-india-112-1962-local-groups/' )
	. '</div>' . "\n" . '<!-- /wp:group -->' . "\n\n",
	'aw-camp-grey'
);

/* faq: six questions (FAQ rules: 3 to 6, answer first, under 50 words, one link). */
$faq = dwsec(
	'',
	'<!-- wp:columns {"className":"aw-camp-faq-cols"} -->' . "\n" . '<div class="wp-block-columns aw-camp-faq-cols">'
	. '<!-- wp:column {"width":"34%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:34%">'
	. dwh( 'Questions' )
	. dwp( 'Something else? ' . dwa( 'Ask us', '/contact/' ) . ' and we\'ll add the answer here.' )
	. '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n"
	. '<!-- wp:column {"width":"66%"} -->' . "\n" . '<div class="wp-block-column" style="flex-basis:66%">'
	. dwfaq(
		array(
			array( 'How do I calm dogs during Diwali firecrackers?', 'Keep your dog in an inner room with the curtains drawn and the TV or music on, and stay with it. Let it hide, and don\'t scold it or force it outside. Walk and feed it before dark. If it is very anxious, ask your vet before the festival.' ),
			array( 'Can I give my dog medicine for Diwali firecracker fear?', 'Only medicine a vet prescribes for your dog. Human painkillers such as ibuprofen and paracetamol can poison dogs (' . dwa( 'ASPCA Animal Poison Control', 'https://www.aspca.org/about-us/press-releases/human-otc-medications-lead-aspcas-annual-list-top-toxins-pets' ) . '). Ask your vet first, and tell them about any heart or breathing problems.' ),
			array( 'How can I help street dogs during Diwali?', 'Put out water and leave a quiet corner away from the bursting, with your RWA\'s permission. Ask neighbours not to burst crackers near the dogs or under parked cars. Check under cars and in drains the next morning, and call a ' . dwa( 'rescue group', '/directory/?tag=rescue-group' ) . ' if a dog is hurt.' ),
			array( 'What should I do if a bird is hurt by Diwali firecrackers?', 'Put the bird in a dark, quiet box with air holes, don\'t feed it, and call a ' . dwa( 'bird helpline', '/resources/injured-bird-helpline-numbers-by-city/' ) . ' or your state forest department. Keep cats and crows away from it while you wait.' ),
			array( 'Is bursting firecrackers on animals a crime in India?', 'Yes. Causing an animal unnecessary pain is an offence under section 11 of the Prevention of Cruelty to Animals Act, 1960, and maiming or killing one is an offence under section 325 of the Bharatiya Nyaya Sanhita, 2023 (up to five years in jail). ' . dwa( 'Report it to the police', '/resources/how-to-report-animal-cruelty/' ) . '.' ),
			array( 'My pet ran away on Diwali night. What should I do?', 'Search the hiding places near your home at dawn, when the noise has stopped. Tell the guards and your RWA group, post photos on local groups, call the ' . dwa( 'vets and shelters near you', '/directory/' ) . ', and update your microchip registry. Leave its bedding and water outside your door.' ),
		)
	)
	. '</div>' . "\n" . '<!-- /wp:column --></div>' . "\n" . '<!-- /wp:columns -->' . "\n\n",
	'aw-camp-white'
);

/* news: the newest linked News items, then the linked guides. */
$news = dwsec(
	'latest',
	dwhtml( '<header class="kit-ed-head"><div><h2>In the news and guides</h2></div><a class="kit-ed-more" href="/resources/">All guides ' . dwi( 'arrow-right' ) . '</a></header>' )
	. '<!-- wp:group {"className":"aw-camp-row","layout":{"type":"default"}} -->' . "\n" . '<div class="wp-block-group aw-camp-row">'
	. dwrow( 91, 'aw_news', 6, $term_id )
	. dwrow( 92, 'post', 8, $term_id )
	. '</div>' . "\n" . '<!-- /wp:group -->' . "\n\n",
	'aw-camp-grey'
);

/* library: the Acts linked to the campaign. */
$library = dwsec(
	'read',
	dwh( 'Read the law' )
	. dwp( 'The Prevention of Cruelty to Animals Act, 1960, in full in our Library.', 'aw-camp-lede' )
	. dwq(
		'<!-- wp:query {"queryId":93,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false,%TQ%},"className":"aw-camp-docs-q kit-ico--file"} -->
<div class="wp-block-query aw-camp-docs-q kit-ico--file"><!-- wp:post-template -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--stacked-sm","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--stacked-sm"><!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/badges {"state":"stacked-sm"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->',
		$term_id
	) . "\n\n"
	. dwh( 'Sources', 3 )
	. dwlist( array_map( fn( $x ) => dwa( $x[0], $x[1] ) . ' (' . $x[2] . ')', array( array( 'Prevention of Cruelty to Animals Act, 1960', '/library/the-prevention-of-cruelty-to-animals-act-1960-as-amended-in-1982/', 'our Library' ), array( 'Bharatiya Nyaya Sanhita, 2023, section 325', 'https://egazette.gov.in/WriteReadData/2023/250883.pdf', 'Gazette of India' ), array( 'Wild Life (Protection) Amendment Act, 2022', 'https://prsindia.org/files/bills_acts/bills_parliament/2021/The%20Wild%20Life%20(Protection)%20Amendment%20Act,%202022.pdf', 'PRS India' ), array( 'Supreme Court, Arjun Gopal v Union of India, 23 Oct 2018', 'https://www.theweek.in/news/india/2018/10/23/supreme-court-refuses-complete-ban-on-sale-of-firecrackers-.amp.html', 'The Week' ), array( 'States may fix two-hour slots, 31 Oct 2018', 'https://www.livelaw.in/amp/sc-modifies-time-slot-for-bursting-crackers-states-can-fix-timings-but-cannot-exceed-two-hours-a-day', 'LiveLaw' ), array( 'In re Noise Pollution, 2005', 'https://leap.unep.org/en/countries/in/national-case-law/re-noise-pollution-restricting-use-loudspeakers', 'UNEP LEAP' ), array( 'Supreme Court hearing, 29 Oct 2026', 'https://www.etvbharat.com/en/bharat/virat-kohli-like-voices-needed-to-deter-firecracker-use-compliance-failures-make-court-orders-a-mockery-sc-enn26102905740', 'ETV Bharat' ), array( 'Odisha Diwali guidelines, 31 Oct 2026', 'https://odishabytes.com/dos-and-donts-issued-for-diwali-celebration-in-odisha-check-guidelines-violations-to-invite-penal-action/', 'Odisha Bytes' ), array( 'People foods to avoid feeding your pets', 'https://www.aspca.org/pet-care/aspca-poison-control/people-foods-avoid-feeding-your-pets', 'ASPCA' ), array( 'Human medicines and pets', 'https://www.aspca.org/about-us/press-releases/human-otc-medications-lead-aspcas-annual-list-top-toxins-pets', 'ASPCA' ), array( 'Diwali advice for pets', 'https://www.gadvasu.in/news/3170', 'GADVASU' ) ) ) )
	. dwp( 'Read on 1 Nov 2026.', 'aw-camp-note' ),
	'aw-camp-white'
);

/* press: social posts linked by the campaign's keywords; hidden while there are none. */
$press = dwsec(
	'',
	dwhtml( '<header class="kit-ed-head"><div><h2>On social media</h2><p class="aw-camp-lede">What people are posting about Diwali and animals.</p></div><a class="kit-ed-more" href="/press-coverage/">See all ' . dwi( 'arrow-right' ) . '</a></header>' )
	. dwrow( 94, 'aw_press', 12, $term_id, 'aw-camp-row aw-camp-row-q' ),
	'aw-camp-grey'
);

/* next-steps: open and closed versions. */
$next_open = dwsec(
	'next',
	dwh( 'What happens next' )
	. dwhtml(
		'<ol class="aw-camp-steps">'
		. '<li><strong>Delhi-NCR order</strong><span>The Supreme Court said it would pass a balanced order</span></li>'
		. '<li><strong>8 Nov 2026</strong><span>Diwali night</span></li>'
		. '<li><strong>9 and 10 Nov 2026</strong><span>Check under cars and in drains; stunned birds and burnt animals turn up</span></li>'
		. '<li><strong>After Diwali</strong><span>We update this page</span></li>'
		. '</ol>'
	),
	'aw-camp-grey'
);
$next_closed = dwsec(
	'next-closed',
	dwh( 'What to do now' )
	. dwhtml(
		'<ol class="aw-camp-steps">'
		. '<li><strong>Check hiding places</strong><span>Under cars, in drains and stairwells, at dawn</span></li>'
		. '<li><strong>Burnt or injured animal</strong><span>Call a vet or rescue group near you</span></li>'
		. '<li><strong>Lost pet</strong><span>Tell your RWA group, call shelters, update the microchip registry</span></li>'
		. '<li><strong>Cracker litter</strong><span>Clear it from streets and feeding spots</span></li>'
		. '</ol>'
	),
	'aw-camp-grey'
);

/* updates: sharing and the newsletter line. */
$share = dwsec(
	'share',
	dwh( 'Send this to three people before Sunday' )
	. dwp( 'Your RWA group, the person who feeds the dogs on your street, a friend with a pet. One forward is one animal that gets a quiet room, a bowl of water or a vet in time.', 'aw-camp-lede' )
	. dwhtml( '<div class="aw-camp-share-kit" data-aw-share></div>' )
	. dwp( dwi( 'mail' ) . 'Sign up with the newsletter form at the end of this page and we\'ll tell you when the court orders on crackers change.', 'aw-camp-note' ),
	'aw-camp-tint aw-camp-open'
);

$content = '<!-- wp:group {"align":"full","className":"aw-camp aw-camp--diwali","layout":{"type":"default"}} -->' . "\n"
	. '<div class="wp-block-group alignfull aw-camp aw-camp--diwali">'
	. dwslot( 'hero', $hero . $hero_closed )
	. dwslot( 'next-steps', $next_closed, 'aw-camp-closed' )
	. dwslot( 'context', $what )
	. dwslot( 'why-it-matters', $law )
	. dwslot( 'how-to-help', $todo . $tonight . $help )
	. dwslot( 'faq', $faq )
	. dwslot( 'news', $news )
	. dwslot( 'library', $library )
	. dwslot( 'press', $press, 'aw-camp-if-items' )
	. dwslot( 'partners', dwsec( '', dwsc( '[aw_campaign_partners title="Partners"]' ), 'aw-camp-white' ), 'aw-camp-if-filled' )
	. dwslot( 'next-steps', $next_open, 'aw-camp-open' )
	. dwslot( 'updates', $share, 'aw-camp-open' )
	. dwsc( '[aw_sticky_cta href="#tonight" label="What to do tonight" days="0"]' )
	. "</div>\n<!-- /wp:group -->";

$title   = 'Keep animals safe this Diwali';
$excerpt = 'Firecrackers frighten and hurt pets, street animals and birds. Before 8 Nov 2026: a safe room for your pet, a quiet corner and water for the dogs outside, and the numbers to call if an animal is hurt.';
$meta    = array(
	'aw_campaign_open'          => gmdate( 'Y-m-d', time() + 19800 ), // the day it is built (IST); the window opens on publishing
	'aw_campaign_close'         => DW_DIWALI, // last day: Diwali; Post from 00:00 IST on 9 Nov
	'aw_campaign_share_text'    => 'Before 8 Nov: a safe room and ID tag for your pet, water and a quiet corner for the street dogs, and the numbers to call if an animal is hurt.',
	'aw_campaign_impact_state'  => 'na',
	'aw_campaign_outcome'       => 'pending',
);
$seo = array(
	'rank_math_title'         => 'Pets and Dogs During Diwali: Safety Guide – Animal Writes',
	'rank_math_description'   => 'Keep pets and dogs during Diwali safe from crackers: a quiet room at home, water for street dogs, help for hurt birds, who to call and what the law says.',
	'rank_math_focus_keyword' => 'dogs during Diwali,diwali dogs,diwali pets',
);

if ( getenv( 'AW_RENDER' ) ) { // the REST payload: the post, then Rank Math's meta (its own route)
	echo json_encode( // phpcs:ignore -- command-line output
		array(
			'post' => array(
				'slug'           => DW_SLUG,
				'status'         => 'draft',
				'title'          => $title,
				'excerpt'        => $excerpt,
				'content'        => $content,
				'template'       => 'campaign-page',
				'featured_media' => DW_HERO,
				'aw_campaign_link' => array( $term_id ),
				'meta'           => $meta,
			),
			'seo'  => $seo,
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	return;
}

$existing = get_page_by_path( DW_SLUG, OBJECT, 'aw_campaign' );
$post     = array(
	'post_type'    => 'aw_campaign',
	'post_name'    => DW_SLUG,
	'post_title'   => $title,
	'post_excerpt' => $excerpt,
	'post_content' => $content,
	'post_status'  => $existing ? $existing->post_status : 'draft',
);
if ( $existing ) { // keep the publish date
	$post['ID']            = $existing->ID;
	$post['post_date']     = $existing->post_date;
	$post['post_date_gmt'] = $existing->post_date_gmt;
	unset( $meta['aw_campaign_open'] ); // keep the opening day already stored
}
$id = wp_insert_post( wp_slash( $post ), true );
if ( is_wp_error( $id ) ) {
	WP_CLI::error( $id->get_error_message() );
}
wp_set_object_terms( $id, array( $term_id ), 'aw_campaign_link', true );
wp_set_object_terms( $id, array( 'campaigns-civic-action', 'pets-breeding', 'community-dogs' ), 'category', false );
set_post_thumbnail( $id, DW_HERO );
update_term_meta( $term_id, 'aw_campaign_page', $id );
foreach ( array_merge( array( '_wp_page_template' => 'campaign-page' ), $meta, $seo ) as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
update_post_meta( $id, 'rank_math_primary_category', (string) get_term_by( 'slug', 'campaigns-civic-action', 'category' )->term_id );
WP_CLI::success( sprintf( '%s campaign %d (%s): %s', $existing ? 'updated' : 'created', $id, get_post_status( $id ), get_preview_post_link( $id ) ) );
