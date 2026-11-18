<?php
/**
 * Title: Card: stacked-sm
 * Slug: kits-by-wugweb/card-stacked-sm
 * Categories: kits-lists
 * Block Types: core/query
 * Description: A Query Loop of cards in the stacked-sm state (kit-card--stacked-sm, kit.css "Card states"): core date, category terms and title.
 */
?>
<!-- wp:query {"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--stacked-sm","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--stacked-sm"><!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta kit-meta-row"} /-->

<!-- wp:post-terms {"term":"category","className":"kit-card-chips kit-tags"} /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
