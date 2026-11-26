<?php
/**
 * Title: Directory list
 * Slug: animal-writes/hub-directory
 * Categories: animal-writes
 * Description: The list on the directory hub page (a Query Loop of the section's items in cards). Replaces the synced pattern "Directory list" that was kept only in the database (26 Nov 2026); the page now points at this file.
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:query {"queryId":106,"query":{"perPage":24,"pages":0,"offset":0,"postType":"aw_listing","order":"desc","orderBy":"date","inherit":false},"namespace":"animal-writes/hub","className":"aw-hub-list aw-hub-directory","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list aw-hub-directory"><!-- wp:animal-writes/result-head /-->

<!-- wp:post-template {"className":"aw-orgs","layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"aw-org","layout":{"type":"default"}} -->
<article class="wp-block-group aw-org"><!-- wp:animal-writes/org-mark /-->

<!-- wp:group {"className":"aw-org-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-org-body"><!-- wp:post-title {"level":2,"isLink":true,"className":"aw-org-name"} /-->

<!-- wp:paragraph {"className":"aw-org-meta kit-ico\u002d\u002ddirectory","metadata":{"bindings":{"content":{"source":"animal-writes/field","args":{"key":"area"}}}}} -->
<p class="aw-org-meta kit-ico--directory"></p>
<!-- /wp:paragraph -->

<!-- wp:animal-writes/badges /-->

<!-- wp:animal-writes/contact /--></div>
<!-- /wp:group -->

<!-- wp:animal-writes/org-actions /--></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"className":"kit-pager","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous {"label":"Previous"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":"Next"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:html -->
<div class="kit-empty"><p>No organisations match. Try another district, or clear the filters.</p><p><a class="kit-btn kit-btn--secondary" href="/directory/">Clear filters</a></p></div>
<!-- /wp:html -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
