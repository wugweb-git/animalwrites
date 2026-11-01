<?php
/**
 * Home page. Its sections and their order live in the theme template (templates/front-page.html), each a pattern; the
 * front page's own content is not shown. Its FAQ box still holds the home page's questions (item-faq block, last).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The FAQ section prints nothing when the home page has too few questions for an FAQ.
add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		if ( false === strpos( (string) ( $block['attrs']['className'] ?? '' ), 'aw-ed-faq' ) || false !== strpos( $html, 'kit-faq' ) ) {
			return $html;
		}
		return '';
	},
	10,
	2
);
