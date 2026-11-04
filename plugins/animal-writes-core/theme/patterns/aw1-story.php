<?php
/**
 * Title: India Writes for Its Community Dogs
 * Slug: animal-writes/aw1-story
 * Categories: animal-writes
 * Description: The past handwritten-letter campaign as a full-bleed photograph band (core Cover): the story, the campaign's figures (each with its Kits icon), its timeline as a rail, a Kits Brand button, and beside them four of the campaign's own photographs.
 * Inserter: yes
 *
 * @package animal-writes
 */

$aw1_photo = function ( $id, $cls ) {
	return '<!-- wp:image {"id":' . $id . ',"sizeSlug":"medium_large","linkDestination":"none","className":"' . $cls . '"} -->' . "\n"
		. '<figure class="wp-block-image size-medium_large ' . $cls . '"><img src="' . esc_url( (string) wp_get_attachment_image_url( $id, 'medium_large' ) ) . '" alt="' . esc_attr( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) . '" class="wp-image-' . $id . '"/></figure>' . "\n"
		. '<!-- /wp:image -->';
};
?>
<!-- wp:cover {"url":"/wp-content/uploads/2026/10/aw1-im6-1536x866.jpeg","id":22,"alt":"","dimRatio":100,"customGradient":"linear-gradient(90deg,rgba(20,20,20,0.94) 0%,rgba(20,20,20,0.82) 55%,rgba(20,20,20,0.55) 100%)","isUserOverlayColor":true,"focalPoint":{"x":0.7,"y":0.5},"minHeight":620,"isDark":true,"metadata":{"name":"India writes for its community dogs"},"align":"full","className":"kit-section aw-ed-story aw-camp--community-dogs","style":{"spacing":{"padding":{"right":"var(--wp--style--root--padding-right)","left":"var(--wp--style--root--padding-left)"}}},"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-cover alignfull is-dark kit-section aw-ed-story aw-camp--community-dogs" style="padding-right:var(--wp--style--root--padding-right);padding-left:var(--wp--style--root--padding-left);min-height:620px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim has-background-gradient" style="background:linear-gradient(90deg,rgba(20,20,20,0.94) 0%,rgba(20,20,20,0.82) 55%,rgba(20,20,20,0.55) 100%)"></span><img class="wp-block-cover__image-background wp-image-22" alt="" src="/wp-content/uploads/2026/10/aw1-im6-1536x866.jpeg" style="object-position:70% 50%" data-object-fit="cover" data-object-position="70% 50%"/><div class="wp-block-cover__inner-container"><!-- wp:group {"metadata":{"name":"Story and photographs"},"className":"aw-ed-story-cols","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-cols"><!-- wp:group {"metadata":{"name":"Story"},"className":"aw-ed-story-text","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-text"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">India Writes for Its Community Dogs</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">In November 2025, people across India wrote by hand to the Supreme Court for the country’s community dogs, and on 29 November walked to their post offices to send the letters.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Figures"},"className":"aw-ed-story-figures","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-figures"><!-- wp:group {"className":"kit-ico--mail","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group kit-ico--mail"><!-- wp:paragraph {"className":"aw-ed-story-num","fontSize":"2x-large"} -->
<p class="aw-ed-story-num has-2-x-large-font-size"><strong>2.5 lakh</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">letters delivered to the Supreme Court, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-ico--user","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group kit-ico--user"><!-- wp:paragraph {"className":"aw-ed-story-num","fontSize":"2x-large"} -->
<p class="aw-ed-story-num has-2-x-large-font-size"><strong>10,000+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">citizens committed, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-ico--map-pin","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group kit-ico--map-pin"><!-- wp:paragraph {"className":"aw-ed-story-num","fontSize":"2x-large"} -->
<p class="aw-ed-story-num has-2-x-large-font-size"><strong>100+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">cities, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-ico--megaphone","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group kit-ico--megaphone"><!-- wp:paragraph {"className":"aw-ed-story-num","fontSize":"2x-large"} -->
<p class="aw-ed-story-num has-2-x-large-font-size"><strong>100+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">volunteer coordinators, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Timeline"},"className":"aw-ed-story-dates","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-dates"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p><strong>1 Nov 2025</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Campaign launched</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p><strong>10 Nov 2025</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">10,000 citizens committed across 50+ cities</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-key","style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-key"><!-- wp:paragraph -->
<p><strong>29 Nov 2025</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">National day of action at post offices</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p><strong>2 Dec 2025</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Letters delivered to the Supreme Court</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:buttons {"className":"aw-ed-story-actions"} -->
<div class="wp-block-buttons aw-ed-story-actions"><!-- wp:button {"className":"is-style-kits-brand"} -->
<div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="/campaigns/india-writes-for-its-community-dogs/">Read the full story</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Photographs"},"className":"aw-ed-story-media","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-media"><!-- wp:group {"metadata":{"name":"Photographs"},"className":"aw-ed-story-photos","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-photos"><?php echo $aw1_photo( 23, 'aw-ed-story-photo' ) . "\n\n" . $aw1_photo( 24, 'aw-ed-story-photo' ) . "\n\n" . $aw1_photo( 25, 'aw-ed-story-photo' ) . "\n\n" . $aw1_photo( 26, 'aw-ed-story-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
