<?php
/**
 * An item's own page (News, Library, research) shows its whole summary. The summary rules themselves are SourceWeb's
 * (aw-sourcing/includes/summary-rules.php); this is the site's display of them.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'render_block_data',
	function ( $block ) {
		// The post-excerpt block cuts at 55 words by default; on an item's own page (News, Library, research) the summary is shown whole.
		if ( 'core/post-excerpt' === ( $block['blockName'] ?? '' ) && ! isset( $block['attrs']['excerptLength'] ) && is_singular( array( 'aw_news', 'aw_legal', 'post' ) ) ) {
			$block['attrs']['excerptLength'] = 1000;
		}
		return $block;
	}
);
