<?php
/**
 * About page (page 58), owner-approved brief (17 Oct 2026): four sections (Who we are, What we do, Why we do it,
 * How you can help), built from theme blocks and styles. Takes out the first campaign's story blocks (A National
 * Movement of Compassion, the Campaign Timeline, the next-phase banner, "Historic achievement unlocked"), the
 * separate Corrections line and the volunteer banner (Volunteer is one of the actions now). Sets the Rank Math
 * title, description and focus keyword. The old content and meta go to ~/aw-about-backup-*.json first, and
 * WordPress keeps the old version as a revision.
 * Run: wp eval-file about-page.php            (dry run: wp eval-file about-page.php dry)
 */
$id  = 58;
$dry = in_array( 'dry', (array) ( $args ?? array() ), true );
$p   = get_post( $id );
if ( ! $p || 'about' !== $p->post_name ) {
	WP_CLI::error( 'Page 58 is not the About page; nothing changed' );
}

$content = <<<'HTML'
<!-- wp:group {"metadata":{"name":"Who we are"},"align":"wide","className":"aw-about-who","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aw-about-who" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label">Our story</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">We started with a cause. We learned what it takes to build for it.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our first campaign, <a href="/campaigns/india-writes-for-its-community-dogs/">India Writes for Its Community Dogs</a>, began with a simple belief: people should have a way to speak up when decisions affect animals.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We learned by doing. We had the conviction to start, but not everything needed to sustain the work beyond the campaign. That experience changed how we approached Animal Writes. We wanted to build something more useful for the long term: a place to find information, understand issues, discover organisations, and take informed action.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">From one day of collective action to a resource people can return to.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"id":19,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="/wp-content/uploads/2026/10/aw1-im3-1024x399.jpeg" alt="People seated with handwritten placards at a gathering for community dogs." class="wp-image-19" width="1024" height="399"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"What we do"},"align":"wide","className":"aw-about-what","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aw-about-what" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label">The platform</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Information, resources, and ways to take action for animals.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Animal welfare information can be spread across court decisions, government notices, news reports, organisations, and practical guides. Finding the right information is only the first step; understanding it and knowing where to go next can be just as difficult.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Animal Writes brings these pathways together in one place, helping people discover information, understand the issues affecting animals in India, and identify useful next steps.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Modules"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Directory</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Find organisations and services.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Discover animal-welfare organisations, gaushalas, veterinary services, and other relevant listings. Use available location filters to find options relevant to your area.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/directory/">Find an organisation</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">News</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Understand what’s happening.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Follow animal-welfare developments from across India, with summaries that help readers understand the news and trace it back to its source.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/news/">Read the latest news</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Emergency help</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Know where to start when help is needed.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Find practical information for situations such as an injured animal, a cruelty complaint, an emergency, or questions about community animals.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/resources/who-to-call-for-an-animal-emergency-in-india-112-1962-local-groups/">Find emergency help</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Library</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Go beyond the headline.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Explore court decisions, legislation, government notifications, and official documents that shape animal welfare. Find summaries alongside links to original sources.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/library/">Browse the Library</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Resources</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Practical guidance for real situations.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Explore guides, FAQs, research, and useful templates covering animal care, laws, reporting cruelty, emergency response, and civic participation.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/resources/">Explore resources</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-card","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Campaigns and civic action</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Turn concern into participation.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Discover campaigns, letter templates, and other opportunities to participate in animal-welfare advocacy where available.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button" href="/campaigns/">Explore campaigns</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"How we work"},"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">How we work</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We link every item to its original source, cover animal welfare in India only, and correct mistakes openly. Read our <a href="/editorial-policy/">editorial policy</a> and <a href="/corrections/">corrections</a> page.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Whether you’re looking for help, trying to understand an issue, or deciding how to contribute, start with what you need.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Why we do it"},"align":"wide","className":"aw-about-why","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aw-about-why" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label">Why this matters</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Caring is a starting point. Finding the right next step matters, too.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When someone wants to help an animal, understand a legal decision, report cruelty, or find an organisation, they need more than a headline or a search result.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>They need information they can follow up on. They need context. They need to know where a document came from, which resources may be relevant, and what options are available to them.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Too often, finding these pieces means navigating different websites, documents, organisations, and sources without a clear path between them.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Animal Writes exists to make that journey easier.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We bring together news, official information, practical resources, and organisation listings so people can move from a question to a better understanding of the issue—and, where appropriate, towards a useful action.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We are building this platform because animal welfare needs more than awareness alone. It also needs accessible information, informed participation, and practical ways for people to get involved.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Principles"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"grid","minimumColumnWidth":"16rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Make information easier to find</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Bring relevant information and resources into clearer pathways.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Make complex issues easier to understand</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Connect summaries with original sources and practical context.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Help people find their next step</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Connect readers with relevant guides, organisations, and opportunities to participate.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"How you can help"},"align":"wide","className":"aw-about-help","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide aw-about-help" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label">Take part</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">You don’t have to do everything. Start somewhere.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>There are different ways to help animals, depending on your time, experience, and circumstances. You can begin by finding an organisation, learning about an issue, sharing useful information, or participating in a campaign.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Actions"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"grid","minimumColumnWidth":"20rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Find an organisation</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Looking for help or hoping to support existing work? Explore organisations and services relevant to your location and needs.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/directory/">Find an organisation <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Learn before you act</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use emergency guides, research, the Library, and practical resources to understand an issue and identify appropriate next steps.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/resources/">Explore resources <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Take part in a campaign</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Explore active campaigns and available templates or participation opportunities.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/campaigns/">Explore current campaigns <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Volunteer</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Join volunteers across India who rescue, feed and speak up for animals.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/volunteer/">Register as a volunteer <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Claim your listing</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Run an organisation or service listed in the Directory? Claim its listing to keep its details up to date.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/claim/">Claim your listing <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-kits-note","layout":{"type":"flow"}} -->
<div class="wp-block-group is-style-kits-note"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Help improve the information</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you find an outdated listing, incorrect detail, or information that needs correction, contact Animal Writes with the relevant page and supporting information.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/contact/">Contact us <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Newsletter"},"className":"kit-dark aw-about-newsletter","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"margin":{"top":"var:preset|spacing|50"}}},"backgroundColor":"secondary","textColor":"background","layout":{"type":"constrained","contentSize":"560px","justifyContent":"left"}} -->
<div class="wp-block-group kit-dark aw-about-newsletter has-background-color has-secondary-background-color has-text-color has-background" style="margin-top:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"textColor":"background"} -->
<h3 class="wp-block-heading has-background-color has-text-color">Newsletter</h3>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[aw_newsletter]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"fontSize":"large","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
<p class="has-large-font-size" style="margin-top:var(--wp--preset--spacing--50)">Start with one useful step. It may be the information you find, the resource you share, or the action you take next.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
HTML;

$meta = array(
	'rank_math_title'         => 'About Us: India’s Animal Welfare Resource – Animal Writes',
	'rank_math_description'   => 'Animal Writes brings together animal welfare news, court orders, laws, guides and a directory of organisations in India, so you can understand issues and act.',
	'rank_math_focus_keyword' => 'about animal writes, animal welfare india',
);

if ( $dry ) {
	WP_CLI::success( 'DRY RUN: ' . strlen( $content ) . ' bytes of content; ' . count( parse_blocks( $content ) ) . ' top-level blocks; nothing written' );
	return;
}

$backup = array( 'post_content' => $p->post_content, 'meta' => array() );
foreach ( array_keys( $meta ) as $k ) {
	$backup['meta'][ $k ] = get_post_meta( $id, $k, true );
}
$file = getenv( 'HOME' ) . '/aw-about-backup-' . gmdate( 'Ymd-His' ) . '.json';
if ( ! file_put_contents( $file, wp_json_encode( $backup ) ) ) {
	WP_CLI::error( 'Backup not written; nothing changed' );
}
$r = wp_update_post( array( 'ID' => $id, 'post_content' => $content ), true );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
foreach ( $meta as $k => $v ) {
	update_post_meta( $id, $k, $v );
}
WP_CLI::success( 'About page rebuilt; old content and meta in ' . $file );
