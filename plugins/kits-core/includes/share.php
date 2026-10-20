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
 * Share bar HTML for a post (owner, 20 Oct 2026): one row, left-aligned with the text, the same button for every network
 * in this order: X, Facebook, LinkedIn, WhatsApp, Email, Copy link. Icon buttons (44px) with an aria-label and a title;
 * the names show only at lg when the whole row fits on one line (kit.js adds .is-labelled), never on two rows.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function aw_share_html( $post_id ) {
	$url   = get_permalink( $post_id );
	$title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES );
	$site  = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$nets  = array( // key => [name, icon, link]
		'x'        => array( 'X', 'x-brand', 'https://x.com/intent/post?text=' . rawurlencode( $title ) . '&url=' . rawurlencode( $url ) ),
		'facebook' => array( 'Facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ),
		'linkedin' => array( 'LinkedIn', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ) ),
		'whatsapp' => array( 'WhatsApp', 'brand-whatsapp', 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url ) ),
		'email'    => array( 'Email', 'mail', 'mailto:?subject=' . rawurlencode( sprintf( 'From %s: %s', $site, $title ) ) . '&body=' . rawurlencode( $url ) ),
	);
	$out = '<div class="kit-share" role="group" aria-label="' . esc_attr__( 'Share', 'animal-writes-core' ) . '"><span class="kit-share-label" aria-hidden="true">' . esc_html__( 'Share', 'animal-writes-core' ) . '</span>';
	foreach ( $nets as $key => list( $name, $icon, $href ) ) {
		/* translators: %s: network name. */
		$label = 'email' === $key ? __( 'Share by email', 'animal-writes-core' ) : sprintf( __( 'Share on %s', 'animal-writes-core' ), $name );
		$out  .= '<a class="kit-share-btn kit-share-' . $key . '" href="' . esc_url( $href ) . '"' . ( 'email' === $key ? '' : ' target="_blank" rel="noopener noreferrer"' ) . ' data-kit-share="' . $key . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="kit-ico kit-ico--' . $icon . '" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $name ) . '</span></a>';
	}
	$copy = __( 'Copy link', 'animal-writes-core' );
	$out .= '<button type="button" class="kit-share-btn kit-share-copy" data-url="' . esc_attr( $url ) . '" data-done="' . esc_attr__( 'Link copied', 'animal-writes-core' ) . '" aria-label="' . esc_attr( $copy ) . '" title="' . esc_attr( $copy ) . '"><span class="kit-ico kit-ico--link" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $copy ) . '</span></button>';
	return $out . '<span class="screen-reader-text" role="status" aria-live="polite"></span></div>';
}

// At the end of <main> on single items, after the content and any call to action.
add_filter(
	'render_block_core/group',
	function ( $content, $block ) {
		static $done = false;
		$types = (array) apply_filters( 'kits_share_post_types', array( 'post' ) );
		if ( $done || ! $types || 'main' !== ( $block['attrs']['tagName'] ?? '' ) || ! is_singular( $types ) ) { // no types: off (is_singular( [] ) would match every page)
			return $content;
		}
		$pos = strrpos( $content, '</main>' );
		if ( false === $pos ) {
			return $content;
		}
		$done = true;
		return substr( $content, 0, $pos ) . '<div class="kit-share-wrap">' . aw_share_html( get_queried_object_id() ) . '</div>' . substr( $content, $pos );
	},
	10,
	2
);
