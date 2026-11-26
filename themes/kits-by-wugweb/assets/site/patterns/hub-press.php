<?php
/**
 * Title: Press list
 * Slug: animal-writes/hub-press
 * Categories: animal-writes
 * Description: The list on the press hub page (a Query Loop of the section's items in cards). Replaces the synced pattern "Press list" that was kept only in the database (26 Nov 2026); the page now points at this file.
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:query {"queryId":107,"query":{"perPage":100,"pages":0,"offset":0,"postType":"aw_press","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"aw-hub-list aw-hub-media","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-hub-media"><!-- wp:heading {"className":"screen-reader-text"} -->
<h2 class="wp-block-heading screen-reader-text">Latest press coverage</h2>
<!-- /wp:heading -->

<!-- wp:post-template {"className":"aw-news-grid","layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card--block-md","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-md"><!-- wp:post-featured-image {"isLink":false,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:animal-writes/read-meta /-->

<!-- wp:animal-writes/badges {"state":"block-md"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"kit-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":40,"className":"kit-card-sum"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-no-results -->
<!-- wp:html -->
<div class="kit-empty"><p><strong>No coverage yet.</strong></p><p>Written about Animal Writes? <a href="/contact/?about=general">Send us the link.</a></p></div>
<!-- /wp:html -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
