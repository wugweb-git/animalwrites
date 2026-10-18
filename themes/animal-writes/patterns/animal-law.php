<?php
/**
 * Title: From the Library
 * Slug: animal-writes/animal-law
 * Categories: animal-writes
 * Description: The three newest Library items as cards (the News card: picture, publisher, date, title), then four Kits tiles into the Library by type, two by two.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"From the Library"},"align":"full","className":"kit-section kit-surface-section aw-ed-law","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull kit-section kit-surface-section aw-ed-law"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">From the Library</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Court decisions, government notices and the rules behind them, summarised in plain words and linked to the original documents.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph -->
<p><a class="kit-ed-more" href="/library/">Browse the Library</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":43,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-news-list is-three is-brief","layout":{"type":"default"}} -->
<?php echo function_exists( 'aw_hub_item' ) ? str_replace( '"level":2', '"level":3', aw_hub_item( 'aw_news' ) ) : ''; // the News card for Library items too: picture (document page or publisher image), publisher, date, title ?>
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:group {"className":"kit-stack","style":{"spacing":{"margin":{"top":"var:preset|spacing|70"}}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"16rem"}} -->
<div class="wp-block-group kit-stack" style="margin-top:var(--wp--preset--spacing--70)"><!-- wp:group {"className":"kit-tile kit-ico--legal","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--legal"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=act">Acts and Rules</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The law in full, from the official text.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--research","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--research"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=judgment">Court orders and judgments</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>What the courts have ordered, summarised.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--file","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--file"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=circular">Circulars and notices</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>From the Animal Welfare Board and government departments.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--mail","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--mail"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=template">Letters and templates</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Letters you can send today.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
