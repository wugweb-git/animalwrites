<?php
/**
 * Sponsored blocks (inserter group "Sponsored", owner 12 Oct 2026). Both are always labelled "Sponsored", their links
 * carry rel="sponsored noopener", and a page that holds either one carries no ads (Advanced Ads display conditions, tools/advanced-ads/setup.php).
 * - animal-writes/sponsored-item: a Resource with Sponsored ticked, as a card ("Sponsored by {name}" when named).
 * - animal-writes/sponsored-banner: picture, heading, text and link set by the editor.
 * The editor also gets the "System" pattern category (content repeated across pages: synced patterns).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The "Sponsored" label line: badge, and "Sponsored by {name}" when a name is given. */
function aw_sponsored_label( $name ) {
	$name = trim( (string) $name );
	return '<p class="aw-sponsored-label">' . kits_badge( 'Sponsored', 'sponsored' ) . ( '' !== $name ? ' <span>' . esc_html( 'Sponsored by ' . $name ) . '</span>' : '' ) . '</p>';
}

function aw_block_sponsored_item( $a ) {
	$id = (int) ( $a['postId'] ?? 0 );
	if ( ! $id || 'publish' !== get_post_status( $id ) || ! aw_is_sponsored( $id ) ) {
		return '';
	}
	$url   = get_permalink( $id );
	$thumb = get_the_post_thumbnail( $id, 'medium_large', array( 'alt' => '', 'loading' => 'lazy' ) );
	$text  = function_exists( 'aw_text' ) ? aw_text( $id, 26 ) : get_the_excerpt( $id );
	$sponsor = trim( (string) get_post_meta( $id, 'aw_sponsor_name', true ) );
	// data-sponsored-*: sponsored_view / sponsored_click (aw-analytics.js).
	return '<aside class="aw-sponsored-item" aria-label="Sponsored" data-sponsored-type="resource" data-sponsor="' . esc_attr( '' !== $sponsor ? $sponsor : 'sponsored' ) . '" data-sponsored-item="' . esc_attr( get_the_title( $id ) ) . '">' . aw_sponsored_label( $sponsor )
		. ( $thumb ? '<a class="aw-sponsored-item-media" href="' . esc_url( $url ) . '" rel="sponsored noopener" tabindex="-1" aria-hidden="true">' . $thumb . '</a>' : '' )
		. '<h3 class="aw-sponsored-item-title"><a href="' . esc_url( $url ) . '" rel="sponsored noopener">' . esc_html( get_the_title( $id ) ) . '</a></h3>'
		. ( $text ? '<p>' . esc_html( $text ) . '</p>' : '' ) . '</aside>';
}

function aw_block_sponsored_banner( $a ) {
	$url     = esc_url( (string) ( $a['url'] ?? '' ) );
	$heading = trim( (string) ( $a['heading'] ?? '' ) );
	$text    = trim( (string) ( $a['text'] ?? '' ) );
	$link    = trim( (string) ( $a['linkText'] ?? '' ) );
	$img     = (int) ( $a['mediaId'] ?? 0 ) ? wp_get_attachment_image( (int) $a['mediaId'], 'large', false, array( 'alt' => (string) ( $a['mediaAlt'] ?? '' ), 'loading' => 'lazy', 'class' => 'aw-sponsored-banner-img' ) ) : '';
	if ( '' === $heading && '' === $text && '' === $img ) {
		return '';
	}
	$h = '' !== $heading ? ( $url && '' === $link ? '<a href="' . $url . '" rel="sponsored noopener">' . esc_html( $heading ) . '</a>' : esc_html( $heading ) ) : '';
	$sponsor = trim( (string) ( $a['sponsor'] ?? '' ) );
	return '<aside class="aw-sponsored-banner" aria-label="Sponsored" data-sponsored-type="banner" data-sponsor="' . esc_attr( '' !== $sponsor ? $sponsor : 'sponsored' ) . '" data-sponsored-item="' . esc_attr( '' !== $heading ? $heading : $text ) . '">'
		. ( $img ? '<div class="aw-sponsored-banner-media">' . $img . '</div>' : '' )
		. '<div class="aw-sponsored-banner-body">' . aw_sponsored_label( (string) ( $a['sponsor'] ?? '' ) )
		. ( $h ? '<h2 class="aw-sponsored-banner-title">' . $h . '</h2>' : '' )
		. ( '' !== $text ? '<p>' . esc_html( $text ) . '</p>' : '' )
		. ( $url && '' !== $link ? '<p><a class="kit-btn" href="' . $url . '" rel="sponsored noopener">' . esc_html( $link ) . '</a></p>' : '' )
		. '</div></aside>';
}

add_action(
	'init',
	function () {
		wp_register_script( 'aw-sponsored-blocks', plugins_url( 'assets/js/sponsored-blocks.js', AW_CORE_DIR . 'animal-writes-core.php' ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), (string) filemtime( AW_CORE_DIR . 'assets/js/sponsored-blocks.js' ), true );
		$common = array(
			'api_version'           => 3,
			'category'              => 'kits-sponsored',
			'editor_script_handles' => array( 'aw-sponsored-blocks' ),
			'supports'              => array( 'html' => false ),
		);
		register_block_type(
			'animal-writes/sponsored-item',
			$common + array(
				'title'           => 'Sponsored Resource',
				'attributes'      => array( 'postId' => array( 'type' => 'integer', 'default' => 0 ) ),
				'render_callback' => 'aw_block_sponsored_item',
			)
		);
		$str = array( 'type' => 'string', 'default' => '' );
		register_block_type(
			'animal-writes/sponsored-banner',
			$common + array(
				'title'           => 'Sponsored banner',
				'attributes'      => array( 'mediaId' => array( 'type' => 'integer', 'default' => 0 ), 'mediaAlt' => $str, 'heading' => $str, 'text' => $str, 'url' => $str, 'linkText' => $str, 'sponsor' => $str ),
				'render_callback' => 'aw_block_sponsored_banner',
			)
		);
		register_block_pattern_category( 'system', array( 'label' => 'System', 'description' => 'Sections repeated across pages' ) );
	}
);

// The editor's list of sponsored Resources (only when the editor loads).
add_action(
	'enqueue_block_editor_assets',
	function () {
		$items = array();
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 200, 'meta_key' => 'aw_sponsored', 'meta_value' => '1', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$items[] = array( 'value' => $p->ID, 'label' => get_the_title( $p ) );
		}
		wp_add_inline_script( 'aw-sponsored-blocks', 'window.awSponsoredResources = ' . wp_json_encode( $items ) . ';', 'before' );
	}
);
