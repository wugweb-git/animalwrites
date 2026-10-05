<?php
/**
 * Title: Lend a hand
 * Slug: animal-writes/lend-a-hand
 * Categories: animal-writes
 * Description: Three newest opportunities with links to all opportunities and other ways to help.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-opps","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-opps has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Lend a hand</h2>
<!-- /wp:heading -->
<!-- wp:query {"queryId":33,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_opportunity","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":18} /-->
<!-- wp:post-date {"fontSize":"small"} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
<!-- wp:paragraph {"className":"aw-more"} -->
<p class="aw-more"><a href="/opportunities/">All opportunities</a> or <a href="/get-involved/">other ways to help</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
