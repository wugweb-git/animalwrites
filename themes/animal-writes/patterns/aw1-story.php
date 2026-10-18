<?php
/**
 * Title: India writes for its community dogs
 * Slug: animal-writes/aw1-story
 * Categories: animal-writes
 * Description: The past handwritten-letter campaign in its campaign page's design (owner, 18 Oct 2026: "should resemble the page"): the campaign orange with white text, the story, the campaign's figures and timeline, a Kits Brand button, and the hero photo as a perforated stamp with the day's postmark. Core blocks only; styles in campaign-record.css (.aw-ed-story.aw-camp--community-dogs).
 * Inserter: yes
 *
 * @package animal-writes
 */

$aw1_photo = (string) wp_get_attachment_image_url( 15, 'large' );
$aw1_alt   = (string) get_post_meta( 15, '_wp_attachment_image_alt', true );
?>
<!-- wp:group {"metadata":{"name":"India writes for its community dogs"},"align":"full","className":"kit-section aw-ed-story aw-camp--community-dogs","style":{"spacing":{"padding":{"right":"var(--wp--style--root--padding-right)","left":"var(--wp--style--root--padding-left)"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kit-section aw-ed-story aw-camp--community-dogs" style="padding-right:var(--wp--style--root--padding-right);padding-left:var(--wp--style--root--padding-left)"><!-- wp:heading -->
<h2 class="wp-block-heading">India writes for its community dogs</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"aw-ed-story-lead"} -->
<p class="aw-ed-story-lead">In November 2025, people across India wrote by hand to the Supreme Court for the country’s community dogs, and on 29 November walked to their post offices to send the letters.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Figures"},"className":"aw-ed-story-figures","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-figures"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-num"} -->
<p class="aw-ed-story-num"><strong>2.5 lakh</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">letters delivered to the Supreme Court, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-num"} -->
<p class="aw-ed-story-num"><strong>10,000+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">citizens committed, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-num"} -->
<p class="aw-ed-story-num"><strong>100+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">cities, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-num"} -->
<p class="aw-ed-story-num"><strong>100+</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">volunteer coordinators, by the campaign’s count</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Timeline"},"className":"aw-ed-story-dates","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-story-dates"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-date"} -->
<p class="aw-ed-story-date"><strong><time datetime="2025-11-01">1 Nov 2025</time></strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Campaign launched</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-date"} -->
<p class="aw-ed-story-date"><strong><time datetime="2025-11-10">10 Nov 2025</time></strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">10,000 citizens committed across 50+ cities</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-key","layout":{"type":"default"}} -->
<div class="wp-block-group is-key"><!-- wp:paragraph {"className":"aw-ed-story-date"} -->
<p class="aw-ed-story-date"><strong><time datetime="2025-11-29">29 Nov 2025</time></strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">National day of action at post offices</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"className":"aw-ed-story-date"} -->
<p class="aw-ed-story-date"><strong><time datetime="2025-12-02">2 Dec 2025</time></strong></p>
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
<!-- /wp:buttons -->

<!-- wp:image {"id":15,"sizeSlug":"large","linkDestination":"none","className":"aw-ed-story-stamp"} -->
<figure class="wp-block-image size-large aw-ed-story-stamp"><img src="<?php echo esc_url( $aw1_photo ); ?>" alt="<?php echo esc_attr( $aw1_alt ); ?>" class="wp-image-15"/></figure>
<!-- /wp:image -->

<!-- wp:list {"className":"aw-ed-story-postmark"} -->
<ul class="wp-block-list aw-ed-story-postmark"><!-- wp:list-item -->
<li><time datetime="2025-11-29">29 Nov 2025</time></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>All GPOs and HPOs</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->
