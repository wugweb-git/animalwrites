<?php
/**
 * Title: Resources list
 * Slug: animal-writes/hub-resources
 * Categories: animal-writes
 * Description: The list on the resources hub page (a Query Loop of the section's items in cards). Replaces the synced pattern "Resources list" that was kept only in the database (26 Nov 2026); the page now points at this file.
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:query {"queryId":102,"query":{"perPage":40,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"aw-hub-list aw-hub-resources","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-hub-resources"><!-- wp:post-template {"className":"aw-tasks","layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-md","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-md"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/read-meta /-->

<!-- wp:animal-writes/badges {"state":"block-md"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"kit-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":40,"className":"kit-card-sum"} /--></div>
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
<div class="kit-empty"><p>No guides match this filter yet.</p></div>
<!-- /wp:html -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
