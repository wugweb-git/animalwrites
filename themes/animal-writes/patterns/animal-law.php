<?php
/**
 * Title: Animal law
 * Slug: animal-writes/animal-law
 * Categories: animal-writes
 * Description: Typographic section: latest court decisions and government notices, with links to the law itself.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"align":"full","className":"aw-ed-law","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull aw-ed-law"><!-- wp:html -->
<h2 class="aw-ed-law-title">Law that affects animals</h2>
<!-- /wp:html -->
<!-- wp:group {"className":"aw-ed-law-grid","layout":{"type":"default"}} -->
<div class="wp-block-group aw-ed-law-grid"><!-- wp:html -->
<div class="aw-ed-law-side"><p>Court decisions, government notices and the rules behind them, summarised in plain words and linked to the original documents.</p><ul class="aw-ed-links"><li><a href="/legal-watch/">All court and government updates <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></li><li><a href="/research/">The Acts and Rules, in full <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></li><li><a href="/knowledge/animal-birth-control-abc-explained/">Animal Birth Control, explained <span class="aw-ico aw-ico--arrow-right" aria-hidden="true"></span></a></li></ul></div>
<!-- /wp:html -->
<!-- wp:query {"queryId":43,"query":{"perPage":5,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-ed-rows"} -->
<div class="wp-block-query aw-ed-rows"><!-- wp:post-template -->
<!-- wp:post-date /-->
<!-- wp:post-terms {"term":"aw_topic"} /-->
<!-- wp:post-title {"level":3,"isLink":true} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
