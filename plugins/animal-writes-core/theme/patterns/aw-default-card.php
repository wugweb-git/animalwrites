<?php
/**
 * Title: Animal Writes card
 * Slug: animal-writes/aw-default-card
 * Categories: animal-writes
 * Description: The Animal Writes default banner as a Home card (medium size; the large is the Hero pattern, the small the sidebar card, plus a 1080x1080 share picture): the theme photo card (core Cover, kit-photo-card) on the street dog at dusk (Media 23313) in brand greys (duotone), the hero's words as H2 and line, and one Kits button (one button per card). The photo is clear (no dim); the words sit on the Kits action-card ink band (--kit-action-scrim-mix) that fades out --kit-space-7 below them, and the whole card is the button's link (kit-action-card). A card in the Home campaign row (aw-pair), not a section.
 * Inserter: yes
 *
 * @package animal-writes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:cover {"url":"/wp-content/uploads/2026/11/supreme-court-steps-sleeping-street-dog.avif","id":23313,"alt":"","dimRatio":0,"overlayColor":"foreground","isUserOverlayColor":true,"focalPoint":{"x":0.62,"y":0.75},"contentPosition":"top left","isDark":true,"metadata":{"name":"Animal Writes card"},"className":"kit-photo-card kit-action-card aw-default-card","style":{"color":{"duotone":["#141414","#f2f2f2"]}},"layout":{"type":"default"}} -->
<div class="wp-block-cover is-dark has-custom-content-position is-position-top-left kit-photo-card kit-action-card aw-default-card"><span aria-hidden="true" class="wp-block-cover__background has-foreground-background-color has-background-dim-0 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-23313" alt="" src="/wp-content/uploads/2026/11/supreme-court-steps-sleeping-street-dog.avif" style="object-position:62% 75%" data-object-fit="cover" data-object-position="62% 75%"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"anchor":"aw-default-card-h"} -->
<h2 class="wp-block-heading" id="aw-default-card-h">Help for India’s animals, in one place.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Helplines, vets and rescue groups near you, and the law in plain words.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-kits-brand"} -->
<div class="wp-block-button is-style-kits-brand"><a class="wp-block-button__link wp-element-button" href="/help/">Find help</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->
