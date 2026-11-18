<?php
/**
 * Pattern kits-core/hero (registered in includes/blocks.php): page hero on the Kits Hero block (kits/hero): label, H1,
 * one line and two actions. Its height, colours and padding are set in Styles > Blocks > Hero. It was the Kits theme's
 * pattern kits-by-wugweb/hero until 18 Nov 2026 (a theme pattern may not depend on a plugin's block).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:kits/hero {"align":"full","layout":{"type":"constrained"}} -->
<section class="wp-block-kits-hero alignfull"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label"><?php esc_html_e( 'Label', 'kits-core' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Heading', 'kits-core' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'Supporting text.', 'kits-core' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Primary action', 'kits-core' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button"><?php esc_html_e( 'Secondary action', 'kits-core' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:kits/hero -->
