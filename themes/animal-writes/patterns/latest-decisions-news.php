<?php
/**
 * Title: Latest decisions and news
 * Slug: animal-writes/latest-decisions-news
 * Categories: animal-writes
 * Description: The three newest Legal Watch items and the newest news stories, each with a link to the full section.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-section aw-latest aw-bg-canvas","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-section aw-latest aw-bg-canvas"><!-- wp:html -->
<div class="aw-sec-head"><div><h2 class="wp-block-heading">What the courts and governments decided</h2><p>Court orders, rules and notices that affect animals, explained in plain words.</p></div><a class="aw-link-cta" href="/legal-watch/">All legal updates <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></div>
<!-- /wp:html -->
<!-- wp:query {"queryId":31,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
<!-- wp:post-terms {"term":"aw_topic"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":22} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
<!-- wp:spacer {"height":"var:preset|spacing|70"} -->
<div style="height:var(--wp--preset--spacing--70)" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
<!-- wp:html -->
<div class="aw-sec-head"><div><h2 class="wp-block-heading">From across India</h2><p>Rescues, campaigns and policy news, linked to the original reports.</p></div><a class="aw-link-cta" href="/news/">All news <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></div>
<!-- /wp:html -->
<!-- wp:query {"queryId":32,"query":{"perPage":6,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-cards aw-cards-3"} -->
<div class="wp-block-query aw-cards aw-cards-3"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
<!-- wp:post-terms {"term":"aw_topic"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- wp:post-excerpt {"excerptLength":22} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>New items appear here after review.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
