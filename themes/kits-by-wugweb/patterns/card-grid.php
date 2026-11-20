<?php
/**
 * Title: Card grid — latest
 * Slug: kits-by-wugweb/card-grid
 * Categories: kits-lists
 * Block Types: core/query
 * Keywords: cards, grid, posts, list
 * Viewport Width: 1280
 */
?>
<!-- wp:query {"queryId":10,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"17rem"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-md","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-md"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta kit-meta-row"} /-->

<!-- wp:post-terms {"term":"category","className":"kit-card-chips kit-tags"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":30,"className":"kit-card-sum"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:pattern {"slug":"kits-by-wugweb/empty-state"} /-->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
