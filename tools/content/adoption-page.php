<?php
/**
 * Adoption (page 15573, /adoption/), owner's beta brief (18 Oct 2026): a landing page that says what the section will
 * be, sets expectations and invites people to get in touch. No listings, filters or counts. Built from the bands and
 * tiles the About page uses (aw.css: About page). Default page template (the head band shows the title "Adoption").
 * Sets the Rank Math title and description once, only when they are empty. The old content and template go to
 * ~/aw-adoption-backup-*.json first; WordPress keeps the old version as a revision. Publishes the page.
 * Run: wp eval-file adoption-page.php            (dry run: wp eval-file adoption-page.php dry)
 */
$id  = 15573;
$dry = in_array( 'dry', (array) ( $args ?? array() ), true );
$p   = get_post( $id );
if ( ! $p || 'adoption' !== $p->post_name || 'page' !== $p->post_type ) {
	WP_CLI::error( 'Page 15573 is not the Adoption page; nothing changed' );
}

$content = <<<'HTML'
<!-- wp:group {"metadata":{"name":"Intro"},"align":"full","className":"aw-about-band aw-about-who","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull aw-about-band aw-about-who"><!-- wp:group {"className":"aw-about-inner","layout":{"type":"default"}} -->
<div class="wp-block-group aw-about-inner"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"aw-adopt-beta"} -->
<p class="aw-adopt-beta"><span class="kit-badge kit-badge--new">Beta</span></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">A home. A second chance. A new beginning.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Help an animal find a loving home.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Animal adoption should begin with care, informed decisions, and a commitment that lasts a lifetime.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>We’re building an adoption space on Animal Writes to help people discover adoption opportunities and connect with animal welfare organisations.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center">{{IMG:15541|A dog stands in tall grass, looking ahead.}}</div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Coming soon"},"align":"full","className":"aw-about-band aw-adopt-soon-band aw-about-band--canvas","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull aw-about-band aw-adopt-soon-band aw-about-band--canvas"><!-- wp:group {"className":"aw-about-inner","layout":{"type":"default"}} -->
<div class="wp-block-group aw-about-inner"><!-- wp:group {"className":"aw-adopt-soon","layout":{"type":"default"}} -->
<div class="wp-block-group aw-adopt-soon"><!-- wp:paragraph {"className":"aw-adopt-soon-ico"} -->
<p class="aw-adopt-soon-ico"><span class="kit-ico kit-ico--clock" aria-hidden="true"></span></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Coming soon</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We’re working on a better way to explore adoption opportunities. This section is currently in beta development.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"level":3,"className":"aw-adopt-towards"} -->
<h3 class="wp-block-heading aw-adopt-towards">What we’re working towards</h3>
<!-- /wp:heading -->

<!-- wp:group {"metadata":{"name":"Plans"},"className":"aw-adopt-plans","layout":{"type":"default"}} -->
<div class="wp-block-group aw-adopt-plans"><!-- wp:group {"className":"aw-about-act","layout":{"type":"flow"}} -->
<div class="wp-block-group aw-about-act"><!-- wp:paragraph {"className":"aw-about-act-ico"} -->
<p class="aw-about-act-ico"><span class="kit-ico kit-ico--heart" aria-hidden="true"></span></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Discover animals looking for homes.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"aw-about-act","layout":{"type":"flow"}} -->
<div class="wp-block-group aw-about-act"><!-- wp:paragraph {"className":"aw-about-act-ico"} -->
<p class="aw-about-act-ico"><span class="kit-ico kit-ico--building" aria-hidden="true"></span></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Find adoption opportunities through animal welfare organisations.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"aw-about-act","layout":{"type":"flow"}} -->
<div class="wp-block-group aw-about-act"><!-- wp:paragraph {"className":"aw-about-act-ico"} -->
<p class="aw-about-act-ico"><span class="kit-ico kit-ico--knowledge" aria-hidden="true"></span></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Learn about responsible adoption and preparing for life with a companion animal.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Help us build this"},"align":"full","className":"aw-about-band kit-dark aw-about-band--ink","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull aw-about-band kit-dark aw-about-band--ink"><!-- wp:group {"className":"aw-about-inner","layout":{"type":"default"}} -->
<div class="wp-block-group aw-about-inner"><!-- wp:heading -->
<h2 class="wp-block-heading">Want to help us build this?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Are you an animal welfare organisation, shelter, rescuer, or someone interested in adopting?</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Help us shape what comes next.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-brand"} -->
<div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact Animal Writes</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"className":"aw-adopt-note"} -->
<p class="aw-adopt-note">Adoption listings are not yet available. Please check back soon.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
HTML;

// The photograph: {{IMG:id|alt}} becomes a 4:3 cover image block with the large copy; an empty media-library alt gets it too.
$content = preg_replace_callback(
	'/\{\{IMG:(\d+)\|([^}]*)\}\}/',
	function ( $m ) use ( $dry ) {
		$aid = (int) $m[1];
		$alt = $m[2];
		$src = wp_get_attachment_image_url( $aid, 'large' );
		if ( ! $src ) {
			WP_CLI::error( "Picture $aid not found; nothing changed" );
		}
		if ( ! $dry && '' === (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $aid, '_wp_attachment_image_alt', $alt );
		}
		$src = wp_make_link_relative( $src );
		return '<!-- wp:image {"id":' . $aid . ',"aspectRatio":"4/3","scale":"cover","sizeSlug":"large","linkDestination":"none","className":"aw-about-pic"} -->' . "\n"
			. '<figure class="wp-block-image size-large aw-about-pic"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $aid . '" style="aspect-ratio:4/3;object-fit:cover"/></figure>' . "\n"
			. '<!-- /wp:image -->';
	},
	$content
);

// Search title and description: set only when empty, so later edits in Rank Math are kept.
$seo = array(
	'rank_math_title'       => 'Animal Adoption in India Is Coming Soon – Animal Writes',
	'rank_math_description' => 'Adoption on Animal Writes is coming soon: find animals looking for homes, adoption through welfare organisations, and guides to responsible adoption in India.',
);

if ( $dry ) {
	WP_CLI::success( 'DRY RUN: ' . strlen( $content ) . ' bytes; ' . count( parse_blocks( $content ) ) . ' top-level blocks; status now ' . $p->post_status . '; nothing written' );
	return;
}

$backup = array( 'post_content' => $p->post_content, 'post_status' => $p->post_status, 'template' => get_post_meta( $id, '_wp_page_template', true ) );
$file   = getenv( 'HOME' ) . '/aw-adoption-backup-' . gmdate( 'Ymd-His' ) . '.json';
if ( ! file_put_contents( $file, wp_json_encode( $backup ) ) ) {
	WP_CLI::error( 'Backup not written; nothing changed' );
}
$r = wp_update_post( array( 'ID' => $id, 'post_content' => $content, 'post_status' => 'publish' ), true );
if ( is_wp_error( $r ) ) {
	WP_CLI::error( $r->get_error_message() );
}
update_post_meta( $id, '_wp_page_template', '' ); // default page template (page.html): head band with the title, then the content
foreach ( $seo as $k => $v ) {
	if ( '' === (string) get_post_meta( $id, $k, true ) ) {
		update_post_meta( $id, $k, $v );
	}
}
WP_CLI::success( 'Adoption page published; old content in ' . $file );
