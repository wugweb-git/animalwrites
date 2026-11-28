<?php
/**
 * Title: People and the law row
 * Slug: animal-writes/people-row
 * Categories: animal-writes
 * Description: Home row, fourth block from the top (owner, 27 Nov 2026; one designed block 28 Nov 2026): the newest profile as a photo tile on the left, the four law tiles (Acts and Rules, Court orders and judgments, Circulars and notices, Letters and templates) as a 2 by 2 on the right with no heading of their own. One column on phones (profile first), side by side from 1200. Prints nothing until a profile is published; until then the four tiles stay under The law, in plain words (patterns/animal-law.php).
 * Inserter: no
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'kits_profiles_exist' ) || ! kits_profiles_exist() ) {
	return;
}
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"People and the law"},"align":"full","className":"is-style-kits-section kit-section kit-ed-people","layout":{"type":"constrained","contentSize":"1200px"}} -->
<section class="wp-block-group alignfull is-style-kits-section kit-section kit-ed-people"><!-- wp:html -->
<?php echo kits_people_bento_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built and escaped in includes/profile.php ?>
<!-- /wp:html --></section>
<!-- /wp:group -->
