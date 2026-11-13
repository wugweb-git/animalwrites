<?php
/**
 * Title: Home share
 * Slug: animal-writes/home-share
 * Categories: animal-writes
 * Description: Share Animal Writes (owner, 13 Nov 2026: one share section everywhere): the heading and line on the Home hero picture under an ink wash, the Share card (one "Share this page" button, and one icon row on computers) and Follow us. The share kit is filled in on the page (campaign-tools.php, aw_page_share_fill). Home only.
 * Inserter: no
 *
 * @package animal-writes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"tagName":"section","metadata":{"name":"Share"},"align":"full","anchor":"share","className":"kit-section aw-home-share","layout":{"type":"default"}} -->
<section id="share" class="wp-block-group alignfull kit-section aw-home-share" aria-labelledby="aw-home-share-t"><!-- wp:heading {"className":"aw-home-share-title"} -->
<h2 class="wp-block-heading aw-home-share-title" id="aw-home-share-t">Share Animal Writes</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Send it to someone who may need a vet, a rescue group or the law for an animal near them.</p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="aw-camp-share-kit" data-aw-share></div>
<!-- /wp:html --></section>
<!-- /wp:group -->
