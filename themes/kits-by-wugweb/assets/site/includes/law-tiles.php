<?php
/**
 * The four law tiles (Acts and Rules, Court orders and judgments, Circulars and notices, Letters and templates). Static
 * block markup with no Kits Core calls, so it loads whether or not Kits Core is active: the Animal law pattern keeps its
 * tiles when the profile code is not loaded (a Codex review point on PR 180).
 *
 * @package kits-by-wugweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The four law tiles (Acts and Rules, Court orders and judgments, Circulars and notices, Letters and templates): a 2 by 2
 * grid beside the profile on Home when a profile exists (owner, 27 Nov 2026), else the row under "The law, in plain words"
 * (patterns/animal-law.php), as before.
 *
 * @param string $class Extra class on the grid.
 * @param string $space Top margin preset ('' for none).
 * @return string Block markup.
 */
function kits_law_tiles_block( $class = '', $space = 'var:preset|spacing|50' ) {
	$tiles = <<<'TILES'
<!-- wp:group {"className":"kit-stack","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"16rem"}} -->
<div class="wp-block-group kit-stack" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:group {"className":"kit-tile kit-ico--legal","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--legal"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=act,rule">Acts and Rules</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The law in full, from the official text.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--research","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--research"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=judgment">Court orders and judgments</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>What the courts have ordered, summarised.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--file","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--file"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=circular">Circulars and notices</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>From the Animal Welfare Board and government departments.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"kit-tile kit-ico--mail","layout":{"type":"default"}} -->
<div class="wp-block-group kit-tile kit-ico--mail"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><a href="/library/?tag=template">Letters and templates</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Letters you can send today.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
TILES;
	if ( '' === $space ) {
		$tiles = str_replace( ',"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}', '', $tiles );
		$tiles = str_replace( ' style="margin-top:var(--wp--preset--spacing--50)"', '', $tiles );
	}
	return '' !== $class ? str_replace( '"className":"kit-stack"', '"className":"kit-stack ' . $class . '"', str_replace( 'class="wp-block-group kit-stack"', 'class="wp-block-group kit-stack ' . $class . '"', $tiles ) ) : $tiles;
}
