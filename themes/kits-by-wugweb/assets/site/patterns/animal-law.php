<?php
/**
 * Title: From the Library
 * Slug: animal-writes/animal-law
 * Categories: animal-writes
 * Description: Three Library items as a mosaic of News cards (the newest Act, rule, order or judgment large on the left with its summary, two more beside it, an official release last; home.php; from 768 to 1199 the lead runs full width above the two), then four Kits tiles into the Library by type (four across from 1200, two by two below, a sideways row on phones).
 * Inserter: yes
 * Keywords: law, library, acts, rules
 * Viewport Width: 1280
 *
 * @package animal-writes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

<!-- wp:query {"queryId":43,"query":{"perPage":4,"pages":0,"offset":0,"postType":"aw_legal","order":"desc","orderBy":"date","inherit":false},"className":"aw-hub-list","layout":{"type":"default"}} -->
<div class="wp-block-query aw-hub-list"><!-- wp:post-template {"className":"aw-news-list aw-news-mosaic","layout":{"type":"default"}} -->
<?php echo function_exists( 'aw_hub_item' ) ? str_replace( '"level":2', '"level":3', aw_hub_item( 'aw_news' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the News card for Library items too: picture (document page or publisher image), publisher, date, title: block markup from the plugin's own template, not user text ?>
<!-- /wp:post-template --></div>
<!-- /wp:query -->

<?php echo function_exists( 'kits_law_tiles_block' ) && ( ! function_exists( 'kits_profiles_exist' ) || ! kits_profiles_exist() ) ? kits_law_tiles_block() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static block markup (includes/profile.php); with a profile published the four tiles sit beside it in patterns/people-row.php ?>
</section>
<!-- /wp:group -->
