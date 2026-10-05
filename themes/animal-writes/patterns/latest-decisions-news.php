<?php
/**
 * Title: Latest decisions and news
 * Slug: animal-writes/latest-decisions-news
 * Categories: animal-writes
 * Description: Three newest Legal Watch items and six newest news stories, with links to both sections.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-latest","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-latest" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">What the courts and governments decided</h2>
<!-- /wp:heading -->
<!-- wp:query {"queryId":31,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":24} /-->
<!-- wp:post-date {"fontSize":"small"} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
<!-- wp:paragraph {"className":"aw-more"} -->
<p class="aw-more"><a href="/legal-watch/">All legal updates</a></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"className":"aw-gap-top"} -->
<h2 class="wp-block-heading aw-gap-top">From across India</h2>
<!-- /wp:heading -->
<!-- wp:query {"queryId":32,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-lead"} -->
<div class="wp-block-query aw-cards aw-cards-lead"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":24} /-->
<!-- wp:post-date {"fontSize":"small"} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
<!-- wp:paragraph {"className":"aw-more"} -->
<p class="aw-more"><a href="/news/">All news</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
