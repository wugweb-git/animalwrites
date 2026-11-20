<?php
/**
 * Title: Three big actions
 * Slug: kits-by-wugweb/three-big-actions
 * Categories: kits-components
 * Block Types: core/group
 * Description: A dark band with a section head (kit-sec-head, as on every section) and three large, linked actions; the first is filled with the accent colour.
 * Keywords: actions, links, dark band
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"align":"full","className":"kit-ed-help","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull kit-ed-help"><!-- wp:group {"className":"kit-sec-head","layout":{"type":"default"}} -->
<div class="wp-block-group kit-sec-head"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"className":"kit-ed-help-title"} -->
<h2 class="wp-block-heading kit-ed-help-title"><?php esc_html_e( '[Section heading]', 'kits-by-wugweb' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="kit-ed-acts">
<a class="kit-ed-act" href="#"><span class="kit-ico kit-ico--alert" aria-hidden="true"></span><strong><?php esc_html_e( '[Action one]', 'kits-by-wugweb' ); ?></strong><span><?php esc_html_e( '[One line about action one]', 'kits-by-wugweb' ); ?></span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a>
<a class="kit-ed-act" href="#"><span class="kit-ico kit-ico--search" aria-hidden="true"></span><strong><?php esc_html_e( '[Action two]', 'kits-by-wugweb' ); ?></strong><span><?php esc_html_e( '[One line about action two]', 'kits-by-wugweb' ); ?></span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a>
<a class="kit-ed-act" href="#"><span class="kit-ico kit-ico--phone" aria-hidden="true"></span><strong><?php esc_html_e( '[Action three]', 'kits-by-wugweb' ); ?></strong><span><?php esc_html_e( '[One line about action three]', 'kits-by-wugweb' ); ?></span><span class="kit-ico kit-ico--arrow-right" aria-hidden="true"></span></a>
</div>
<!-- /wp:html --></div>
<!-- /wp:group -->
