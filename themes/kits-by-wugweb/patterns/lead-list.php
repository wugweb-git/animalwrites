<?php
/**
 * Title: Lead + list
 * Slug: kits-by-wugweb/lead-list
 * Categories: kits
 * Description: Section head with a link, one lead post (large image and headline) and the next four as a ruled list.
 */
?>
<!-- wp:group {"align":"full","className":"kit-ed-news","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull kit-ed-news"><!-- wp:html -->
<header class="kit-ed-head"><div><h2>[Section heading]</h2></div><a class="kit-ed-more" href="#">[See all] <span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a></header>
<!-- /wp:html -->

<!-- wp:group {"className":"kit-ed-news-grid","layout":{"type":"default"}} -->
<div class="wp-block-group kit-ed-news-grid"><!-- wp:query {"queryId":61,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"className":"kit-ed-lead"} -->
<div class="wp-block-query kit-ed-lead"><!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->
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
