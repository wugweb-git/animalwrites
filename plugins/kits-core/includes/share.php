<?php
/**
 * Share buttons: plain links (no third-party scripts, no tracking) plus copy-link.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Share bar HTML for a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_share_html( $post_id ) {
	$url   = get_permalink( $post_id );
	$title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES );
	$text  = $title . ' ' . $url;
	$links = array(
		'WhatsApp' => 'https://wa.me/?text=' . rawurlencode( $text ),
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
		'X'        => 'https://x.com/intent/post?text=' . rawurlencode( $title ) . '&url=' . rawurlencode( $url ),
	);
	$out = '<div class="aw-share" role="group" aria-label="' . esc_attr__( 'Share', 'animal-writes-core' ) . '"><span class="aw-share-label">' . esc_html__( 'Share', 'animal-writes-core' ) . '</span>';
	foreach ( $links as $name => $href ) {
		$out .= '<a class="aw-share-' . strtolower( $name ) . '" href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>';
	}
	$out .= '<button type="button" class="aw-share-copy" data-url="' . esc_attr( $url ) . '" data-done="' . esc_attr__( 'Link copied', 'animal-writes-core' ) . '">' . esc_html__( 'Copy link', 'animal-writes-core' ) . '</button></div>';
	return $out;
}

// At the end of <main> on single items, after the content and any call to action.
add_filter(
	'render_block_core/group',
	function ( $content, $block ) {
		static $done = false;
		$types = (array) apply_filters( 'kits_share_post_types', array( 'post' ) );
		if ( $done || 'main' !== ( $block['attrs']['tagName'] ?? '' ) || ! is_singular( $types ) ) {
			return $content;
		}
		$pos = strrpos( $content, '</main>' );
		if ( false === $pos ) {
			return $content;
		}
		$done = true;
		return substr( $content, 0, $pos ) . '<div class="aw-share-wrap">' . aw_share_html( get_queried_object_id() ) . '</div>' . substr( $content, $pos );
	},
	10,
	2
);
