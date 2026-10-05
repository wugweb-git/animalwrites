<?php
/**
 * Title: Lend a hand
 * Slug: animal-writes/lend-a-hand
 * Categories: animal-writes
 * Description: The three newest opportunities, with links to all opportunities and other ways to help.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-section aw-opps aw-bg-kraft","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-section aw-opps aw-bg-kraft"><!-- wp:html -->
<div class="aw-sec-head"><div><h2 class="wp-block-heading">Lend a hand</h2><p>Volunteer, foster or intern with organisations doing the work.</p></div><a class="aw-link-cta" href="/opportunities/">All opportunities <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></div>
<!-- /wp:html -->
<!-- wp:query {"queryId":33,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_opportunity","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-terms {"term":"aw_opportunity_type"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":18} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
<!-- wp:paragraph {"className":"aw-more"} -->
<p class="aw-more">Not ready to commit? <a href="/get-involved/">See other ways to help</a>.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
