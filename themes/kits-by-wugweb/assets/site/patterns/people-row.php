<?php
/**
 * Title: People and the law row
 * Slug: animal-writes/people-row
 * Categories: animal-writes
 * Description: Home row, fourth block from the top (owner, 27 Nov 2026): two columns of equal height. Left: the newest profile (heading, one line, one card, See all profiles). Right: the four law tiles (Acts and Rules, Court orders and judgments, Circulars and notices, Letters and templates) as a 2 by 2 grid. One column on phones (profile first), two columns from 768. Prints nothing until a profile is published; until then the four tiles stay under The law, in plain words (patterns/animal-law.php).
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
<section class="wp-block-group alignfull is-style-kits-section kit-section kit-ed-people"><!-- wp:group {"className":"kit-people-row","layout":{"type":"default"}} -->
<div class="wp-block-group kit-people-row"><?php echo kits_people_profile_block( __( 'People who make a difference', 'kits-by-wugweb' ), __( 'Real work, with the sources shown.', 'kits-by-wugweb' ), 1, 45 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup, text escaped inside (includes/profile.php) ?>

<!-- wp:group {"className":"kit-people-law","layout":{"type":"default"}} -->
<div class="wp-block-group kit-people-law"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Look up the law', 'kits-by-wugweb' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Acts, court orders, notices and letters, each with the original document.', 'kits-by-wugweb' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<?php echo kits_law_tiles_block( 'kit-people-tiles', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static block markup (includes/profile.php) ?></div>
<!-- /wp:group --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
