<?php
/**
 * Title: Opportunities list
 * Slug: animal-writes/hub-opportunities
 * Categories: animal-writes
 * Description: The list on the opportunities hub page (a Query Loop of the section's items in cards). Replaces the synced pattern "Opportunities list" that was kept only in the database (26 Nov 2026); the page now points at this file.
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:query {"queryId":104,"query":{"perPage":24,"pages":0,"offset":0,"postType":"aw_opportunity","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"aw-hub-list aw-hub-opportunities","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-hub-opportunities"><!-- wp:post-template {"className":"aw-opps","layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-md","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-md"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/read-meta /-->

<!-- wp:animal-writes/badges {"state":"block-md"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"kit-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":40,"className":"kit-card-sum"} /-->

<!-- wp:animal-writes/opp-actions /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"className":"kit-pager","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous {"label":"Previous"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":"Next"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:html -->
<div class="kit-empty"><p>No open opportunities of this kind right now.</p></div>
<!-- /wp:html -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
