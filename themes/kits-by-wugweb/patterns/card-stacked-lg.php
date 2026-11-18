<?php
/**
 * Title: Card: stacked-lg
 * Slug: kits-by-wugweb/card-stacked-lg
 * Categories: kits-lists
 * Block Types: core/query
 * Description: A Query Loop of cards in the stacked-lg state (kit-card--stacked-lg, kit.css "Card states"): core date, category terms and title.
 */
?>
<!-- wp:query {"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--stacked-lg","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--stacked-lg"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta kit-meta-row"} /-->

<!-- wp:post-terms {"term":"category","className":"kit-card-chips kit-tags"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":30,"className":"kit-card-sum"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
