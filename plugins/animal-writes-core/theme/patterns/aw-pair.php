<?php
/**
 * Title: Campaign cards row
 * Slug: animal-writes/aw-pair
 * Categories: animal-writes
 * Description: One Home row with two campaign cards, each its own design: the Supreme Court stray dogs case (photograph overlay, pattern aw-sc) and AW_Festive for Diwali (pictures on top, pattern festive). The AW Content card (pattern aw-content) is kept but not on Home. The Diwali card shows until 20 Nov 2026 (owner, 13 Nov); then the Supreme Court card fills the row. Then the Animal Writes card (pattern aw-default-card: the default banner in medium; owner, 13 Nov 2026: the Lab campaign keeps the Home hero, the default banner goes to this row, the fourth Home block). With three cards the last fills its own row, and the Animal Writes card there splits into its words on ink (left column) and its photograph (right column), lined up with the cards above; with two, two columns. Stacked on phones, two equal columns of equal height from 768.
 * Inserter: yes
 * Keywords: campaign, cards, row, home
 * Viewport Width: 1280
 *
 * @package animal-writes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Campaign cards"},"align":"full","className":"is-style-kits-section kit-section aw-pair","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull is-style-kits-section kit-section aw-pair"><!-- wp:group {"className":"aw-pair-grid","layout":{"type":"default"}} -->
<div class="wp-block-group aw-pair-grid"><!-- wp:pattern {"slug":"animal-writes/aw-sc"} /-->

<?php if ( current_time( 'Y-m-d' ) <= '2026-11-20' ) : // the Diwali card stays on Home until 20 Nov 2026 (owner, 13 Nov) ?>
<!-- wp:pattern {"slug":"animal-writes/festive"} /-->
<?php endif; ?>
<!-- wp:pattern {"slug":"animal-writes/aw-default-card"} /--></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
