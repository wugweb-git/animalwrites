<?php
/**
 * Title: Campaigns list
 * Slug: animal-writes/hub-campaigns
 * Categories: animal-writes
 * Description: The list on the campaigns hub page (a Query Loop of the section's items in cards). Replaces the synced pattern "Campaigns list" that was kept only in the database (26 Nov 2026); the page now points at this file.
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:query {"queryId":105,"query":{"perPage":20,"pages":0,"offset":0,"postType":"aw_campaign","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"aw-hub-list aw-hub-campaigns aw-camp-more aw-hide-empty","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-hub-campaigns aw-camp-more aw-hide-empty"><!-- wp:heading -->
<h2 class="wp-block-heading">More campaigns for animals</h2>
<!-- /wp:heading -->

<!-- wp:post-template {"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"18rem"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-card\u002d\u002dblock-lg","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-card--block-lg"><!-- wp:post-featured-image {"isLink":true,"className":"kit-card-media"} /-->

<!-- wp:group {"className":"kit-card-body","layout":{"type":"default"}} -->
<div class="wp-block-group kit-card-body"><!-- wp:post-date {"className":"kit-card-meta"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"kit-card-title"} /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

</div>
<!-- /wp:query -->
