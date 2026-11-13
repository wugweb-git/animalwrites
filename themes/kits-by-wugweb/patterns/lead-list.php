<?php
/**
 * Title: Lead + list
 * Slug: kits-by-wugweb/lead-list
 * Categories: kits-lists
 * Description: Section head (kit-sec-head: H2 and a ghost "See all" button, the same head as every Home section) with a link, one lead post (large image and headline) and the next four as a ruled list.
 * Keywords: news, posts, lead story, list
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"align":"full","className":"kit-ed-news","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull kit-ed-news"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">[Section heading]</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-ghost kit-btn--sm"} -->
<div class="wp-block-button is-style-kits-ghost kit-btn--sm"><a class="wp-block-button__link wp-element-button" href="#">[See all]</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-ed-news-grid","layout":{"type":"default"}} -->
<div class="wp-block-group kit-ed-news-grid"><!-- wp:query {"queryId":61,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"kit-ed-lead"} -->
<div class="wp-block-query kit-ed-lead"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":36} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:query {"queryId":62,"query":{"perPage":4,"pages":0,"offset":1,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"kit-ed-list"} -->
<div class="wp-block-query kit-ed-list"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
