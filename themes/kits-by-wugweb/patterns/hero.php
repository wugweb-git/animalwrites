<?php
/**
 * Title: Hero
 * Slug: kits-by-wugweb/hero
 * Categories: kits-components
 * Description: Page hero (Kits Hero block): label, H1, one line and two actions. Its height, colours and padding are set in Styles > Blocks > Hero.
 * Keywords: hero, intro, title, buttons
 * Viewport Width: 1280
 */
?>
<!-- wp:kits/hero {"align":"full","layout":{"type":"constrained"}} -->
<section class="wp-block-kits-hero alignfull"><!-- wp:paragraph {"className":"is-style-kits-label"} -->
<p class="is-style-kits-label">Label</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Heading</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size">Supporting text.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Primary action</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-kits-secondary"} -->
<div class="wp-block-button is-style-kits-secondary"><a class="wp-block-button__link wp-element-button">Secondary action</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:kits/hero -->
