<?php
/**
 * Title: Campaign cards row
 * Slug: animal-writes/aw-pair
 * Categories: animal-writes
 * Description: One Home row with two campaign cards, each its own design: the Supreme Court stray dogs case (photograph overlay, pattern aw-sc) and AW_Festive for Diwali (pictures on top, pattern festive). The AW Content card (pattern aw-content) is kept but not on Home. Then the lab rules campaign card (pattern aw-lab; owner, 13 Nov 2026: the default Animal Writes banner is the Home hero, the campaign moves to this row). The Diwali card shows until 20 Nov 2026 (owner, 13 Nov); with three cards the last one fills its own row. Stacked on phones, two equal columns of equal height from 768.
 * Inserter: yes
 *
 * @package animal-writes
 */
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Campaign cards"},"align":"full","className":"is-style-kits-section kit-section aw-pair","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull is-style-kits-section kit-section aw-pair"><!-- wp:group {"className":"aw-pair-grid","layout":{"type":"default"}} -->
<div class="wp-block-group aw-pair-grid"><!-- wp:pattern {"slug":"animal-writes/aw-sc"} /-->

<?php if ( current_time( 'Y-m-d' ) <= '2026-11-20' ) : // the Diwali card stays on Home until 20 Nov 2026 (owner, 13 Nov) ?>
<!-- wp:pattern {"slug":"animal-writes/festive"} /-->
<?php endif; ?>
<!-- wp:pattern {"slug":"animal-writes/aw-lab"} /--></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
