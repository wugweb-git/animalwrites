<?php
/**
 * Title: What's happening
 * Slug: animal-writes/whats-happening
 * Categories: animal-writes
 * Description: The four newest news stories as a mosaic of News cards: one large card on the left with its summary, three rows beside it (phones: the large card, then the rows, with their thumbnails), with a link to all news.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"What's happening"},"align":"full","className":"kit-section kit-bg-canvas aw-ed-news","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull kit-section kit-bg-canvas aw-ed-news"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">Latest news</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>What’s happening for animals across India, with every source linked.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-ghost kit-btn--sm"} -->
<div class="wp-block-button is-style-kits-ghost kit-btn--sm"><a class="wp-block-button__link wp-element-button" href="/news/">All news</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":41,"query":{"perPage":4,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-news-list aw-news-mosaic","layout":{"type":"default"}} -->
<?php echo function_exists( 'aw_hub_item' ) ? str_replace( '"level":2', '"level":3', aw_hub_item( 'aw_news' ) ) : ''; // the News list's card, one level below this section's H2 ?>
<!-- /wp:post-template --></div>
<!-- /wp:query --></section>
<!-- /wp:group -->
