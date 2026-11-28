<?php
/**
 * Title: People (About)
 * Slug: animal-writes/people-about
 * Categories: animal-writes
 * Description: About us band: the newest profile as a card (up to three cards in a row once more profiles exist) and See all profiles. Prints nothing until a profile is published. Placed between What we do and Why we do it (tools/content/about-page.php).
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
<!-- wp:group {"metadata":{"name":"People"},"align":"full","className":"aw-about-band kit-about-people aw-about-band--raised","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull aw-about-band kit-about-people aw-about-band--raised"><!-- wp:group {"className":"aw-about-inner","layout":{"type":"default"}} -->
<div class="wp-block-group aw-about-inner"><?php echo kits_people_about_block( __( 'The people behind the work we cover', 'kits-by-wugweb' ), __( 'Real work, with the sources shown.', 'kits-by-wugweb' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup, text escaped inside (includes/profile.php) ?></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
