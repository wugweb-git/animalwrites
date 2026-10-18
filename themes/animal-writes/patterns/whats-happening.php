<?php
/**
 * Title: What's happening
 * Slug: animal-writes/whats-happening
 * Categories: animal-writes
 * Description: The five newest news stories as a mosaic of News cards (one large card, two cards, two rows), titles only, with a link to all news.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"What's happening"},"align":"full","className":"kit-section kit-bg-canvas aw-ed-news","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull kit-section kit-bg-canvas aw-ed-news"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:heading -->
<h2 class="wp-block-heading">What’s happening</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/news/">All news</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":41,"query":{"perPage":5,"pages":0,"offset":0,"postType":"aw_news","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-news-list aw-news-mosaic is-brief","layout":{"type":"default"}} -->
<?php echo function_exists( 'aw_hub_item' ) ? str_replace( '"level":2', '"level":3', aw_hub_item( 'aw_news' ) ) : ''; // the News list's card, one level below this section's H2 ?>
<!-- /wp:post-template --></div>
<!-- /wp:query --></section>
<!-- /wp:group -->
