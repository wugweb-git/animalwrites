<?php
/**
 * Title: Card: block-sm
 * Slug: kits-by-wugweb/card-block-sm
 * Categories: kits-lists
 * Block Types: core/query
 * Description: A Query Loop of cards in the block-sm state (kit-card--block-sm, kit.css "Card states"): core date, category terms and title.
 */
?>
<!-- wp:query {"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-sm","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-sm"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta kit-meta-row"} /-->

<!-- wp:post-terms {"term":"category","className":"kit-card-chips kit-tags"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
