<?php
/**
 * Title: From the Library
 * Slug: animal-writes/animal-law
 * Categories: animal-writes
 * Description: Three Library items as a mosaic of News cards (the newest Act, rule, order or judgment large on the left with its summary, two more beside it, an official release last; home.php; from 768 to 1199 the lead runs full width above the two), then four Kits tiles into the Library by type in a Show more fold (a plain details, folded at every width; four across from 1200, two by two from 768, one column on phones).
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"From the Library"},"align":"full","className":"is-style-kits-section kit-section kit-surface-section aw-ed-law","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull is-style-kits-section kit-section kit-surface-section aw-ed-law"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">The law, in plain words</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Acts, court orders and notices, each with the original document.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-ghost kit-btn--sm"} -->
<div class="wp-block-button is-style-kits-ghost kit-btn--sm"><a class="wp-block-button__link wp-element-button" href="/library/">Browse the Library</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":43,"query":{"perPage":3,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-news-list aw-news-mosaic","layout":{"type":"default"}} -->
<?php echo function_exists( 'aw_hub_item' ) ? str_replace( '"level":2', '"level":3', aw_hub_item( 'aw_news' ) ) : ''; // the News card for Library items too: picture (document page or publisher image), publisher, date, title ?>
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<!-- wp:html {"metadata":{"name":"Show more (opens the fold)"}} -->
<details class="wp-block-details aw-more"><summary><span class="aw-more-show">Show more</span><span class="aw-more-less">Show less</span></summary>
<!-- /wp:html -->

<!-- wp:group {"className":"kit-stack","layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"16rem"}} -->
<div class="wp-block-group kit-stack"><!-- wp:group {"className":"kit-tile kit-ico--legal","layout":{"type":"default"}} -->
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
<!-- /wp:group -->

<!-- wp:html {"metadata":{"name":"Show more (closes the fold)"}} -->
</details>
<!-- /wp:html --></section>
<!-- /wp:group -->
