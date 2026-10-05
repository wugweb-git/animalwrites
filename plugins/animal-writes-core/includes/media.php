<?php
/**
 * Card images: the article's own preview image (linked, not copied) or a placard-style fallback.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placard fallback: the source name, set large on kraft.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_placard_html( $post_id ) {
	$label = get_post_meta( $post_id, 'aw_source_name', true );
	if ( ! $label ) {
		$label = get_post_meta( $post_id, 'aw_org_name', true );
	}
	if ( ! $label ) {
		$label = get_the_title( $post_id );
	}
	return '<div class="aw-placard" aria-hidden="true"><span>' . esc_html( $label ) . '</span></div>';
}

add_filter(
	'render_block_core/post-featured-image',
	function ( $content, $block, $instance ) {
		if ( '' !== trim( $content ) ) {
			return $content; // A real featured image exists.
		}
		$post_id = $instance->context['postId'] ?? get_the_ID();
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), array( 'aw_news', 'aw_legal', 'aw_research', 'aw_opportunity', 'aw_guide' ), true ) ) {
			return $content;
		}
		$link   = ! empty( $block['attrs']['isLink'] );
		$url    = (string) get_post_meta( $post_id, 'aw_source_image', true );
		$inner  = $url
			? '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add(\'aw-img-failed\')">'
			: aw_placard_html( $post_id );
		if ( $url ) {
			$inner .= aw_placard_html( $post_id ); // Shown only if the remote image fails to load.
		}
		$inner = $link ? '<a href="' . esc_url( get_permalink( $post_id ) ) . '" tabindex="-1">' . $inner . '</a>' : $inner;
		return '<figure class="wp-block-post-featured-image aw-card-media' . ( $url ? ' has-remote' : '' ) . '">' . $inner . '</figure>';
	},
	10,
	3
);
