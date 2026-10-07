<?php
/**
 * Title: What's happening
 * Slug: animal-writes/whats-happening
 * Categories: animal-writes
 * Description: Editorial news: one lead story with three secondary stories.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-ed-news","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-ed-news"><!-- wp:html -->
<header class="aw-ed-head"><div><h2>What’s happening</h2></div><a class="aw-ed-more" href="/news/">All news <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></header>
<!-- /wp:html -->
<!-- wp:group {"className":"aw-ed-news-grid","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-news-grid"><!-- wp:query {"queryId":41,"query":{"perPage":1,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-ed-lead"} -->
<div class="wp-block-query aw-ed-lead"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->
<!-- wp:shortcode -->
[aw_source_row]
<!-- /wp:shortcode -->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":36} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
<!-- wp:query {"queryId":42,"query":{"perPage":4,"pages":0,"offset":1,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-ed-list"} -->
<div class="wp-block-query aw-ed-list"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:shortcode -->
[aw_source_row]
<!-- /wp:shortcode -->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
