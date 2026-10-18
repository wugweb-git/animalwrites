<?php
/**
 * Title: Guides
 * Slug: animal-writes/learn
 * Categories: animal-writes
 * Description: The three newest guides from Resources as short Resources cards (icon, title, updated date and reading time), with a link to all guides.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Guides"},"align":"full","className":"kit-section kit-bg-canvas aw-ed-learn","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull kit-section kit-bg-canvas aw-ed-learn"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:heading -->
<h2 class="wp-block-heading">Guides</h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-ghost kit-btn--sm"} -->
<div class="wp-block-button is-style-kits-ghost kit-btn--sm"><a class="wp-block-button__link wp-element-button" href="/resources/?tag=guide">All guides</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":44,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"taxQuery":{"post_tag":[1042]}},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-tasks is-three","layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","className":"kit-card kit-task","layout":{"type":"default"}} -->
<article class="wp-block-group kit-card kit-task"><!-- wp:group {"className":"aw-task-body","layout":{"type":"default"}} -->
<div class="wp-block-group aw-task-body"><!-- wp:animal-writes/task-icon /-->

<!-- wp:post-title {"level":3,"isLink":true,"className":"aw-task-title"} /-->

<!-- wp:animal-writes/read-meta /--></div>
<!-- /wp:group --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></section>
<!-- /wp:group -->
