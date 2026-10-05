<?php
/**
 * Title: Campaign page (letters, countdown, live count, upload)
 * Slug: animal-writes/campaign
 * Categories: featured
 * Post Types: aw_campaign, page
 * Description: Full campaign layout. Replace the campaign slug "my-campaign" and the date in the shortcodes.
 */
?>
<!-- wp:group {"align":"full","className":"aw-camp-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"900px"}} -->
<div class="wp-block-group alignfull aw-camp-hero has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><!-- wp:shortcode -->
[aw_countdown date="2026-11-29" label="Posting day"]
<!-- /wp:shortcode -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Say in one or two sentences what this campaign asks for, and who it is addressed to.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#letters">Write your letter</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#count-my-letter">I posted mine</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"aw-camp-counts aw-dark","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"backgroundColor":"secondary","textColor":"background","layout":{"type":"constrained","contentSize":"900px"}} -->
<div class="wp-block-group alignfull aw-camp-counts aw-dark has-background-color has-secondary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:shortcode -->
<div class="aw-counts">[aw_count campaign="my-campaign" what="letters"] [aw_count campaign="my-campaign" what="people"] [aw_count campaign="my-campaign" what="cities"]</div>
<!-- /wp:shortcode -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Counted from photos people upload below. Updated every few minutes.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">How to take part</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true,"className":"aw-steps"} -->
<ol class="wp-block-list aw-steps"><!-- wp:list-item -->
<li><strong>Write your letter.</strong> Use a template below or write your own, and sign it.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Post it.</strong> Send it by Speed Post from your nearest post office and keep the receipt.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><strong>Count it.</strong> Upload a photo of the receipt so your letter is added to the total.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:heading {"anchor":"letters"} -->
<h2 class="wp-block-heading" id="letters">Letter templates</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":61,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_resource","order":"asc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template -->
<!-- wp:post-title {"level":3,"isLink":true} /-->

<!-- wp:post-excerpt {"excerptLength":20} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:heading {"anchor":"count-my-letter"} -->
<h2 class="wp-block-heading" id="count-my-letter">I posted my letter</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[aw_receipt_form campaign="my-campaign"]
<!-- /wp:shortcode -->

<!-- wp:heading {"anchor":"volunteer"} -->
<h2 class="wp-block-heading" id="volunteer">Lead it in your city</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[aw_volunteer_form]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Questions</h2>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Add a question</summary><!-- wp:paragraph -->
<p>Add the answer.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->
