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
	$saved = kits_share_saved();
	$type  = (string) get_post_type( $post_id );
	$nets  = isset( $saved[ $type ] ) ? kits_share_list( $saved[ $type ] ) : null; // Networks chosen in Theme Options; else every network.
	if ( array() === $nets ) {
		return ''; // No networks for this kind of item: no bar.
	}
	$camp = 'aw_campaign' === $type ? (string) get_post_field( 'post_name', $post_id ) : $type;
	return kits_share_html( (string) get_permalink( $post_id ), html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ), $nets, $camp );
}

/**
 * The address a share hands out, tagged so the visit it brings back is counted under its network instead of Direct
 * (12 Nov 2026: Direct was 40% of sessions, mostly untagged WhatsApp shares). Only the shared address carries the
 * tags; the page's canonical stays the clean address (Rank Math) and LiteSpeed drops utm_* from its cache key.
 *
 * @param string $url      Page address.
 * @param string $source   Network key (x, facebook, linkedin, whatsapp, telegram, email, copy).
 * @param string $campaign Campaign slug, or the post type / 'term' for other pages.
 * @return string
 */
function kits_share_tagged_url( $url, $source, $campaign = '' ) {
	$args = array(
		'utm_source' => $source,
		'utm_medium' => 'social_share',
	);
	if ( '' !== $campaign ) {
		$args['utm_campaign'] = sanitize_title( $campaign );
	}
	return add_query_arg( array_map( 'rawurlencode', $args ), $url );
}

/**
 * The share networks chosen in Theme Options, type => comma list or 'none'. Only types set there.
 *
 * @return array<string,string>
 */
function kits_share_saved() {
	$saved = function_exists( 'kits_option' ) ? kits_option( 'share_networks' ) : array();
	return is_array( $saved ) ? array_map( 'strval', $saved ) : array();
}

/**
 * A comma list of networks ('x,whatsapp', or 'none') as an array of keys.
 *
 * @param string $csv Comma list.
 * @return string[]
 */
function kits_share_list( $csv ) {
	return 'none' === $csv ? array() : array_values( array_filter( array_map( 'trim', explode( ',', $csv ) ) ) );
}

/**
 * Post types that get the share bar at the end of the item: the kits_share_post_types filter, less any type whose
 * networks are all turned off in Theme Options.
 *
 * @return string[]
 */
function kits_share_types() {
	$saved = kits_share_saved();
	return array_values( array_filter( (array) apply_filters( 'kits_share_post_types', array( 'post' ) ), fn( $t ) => ! isset( $saved[ $t ] ) || kits_share_list( $saved[ $t ] ) ) );
}

/**
 * The share bar for any address and title (a post, a category or tag page).
 *
 * @param string        $url   Page address.
 * @param string        $title Plain-text title.
 * @param string[]|null $only     Network keys to show, in the usual order; null for every network. Copy link always shows.
 * @param string        $campaign utm_campaign of the shared address (campaign slug, post type or 'term').
 * @return string
 */
function kits_share_html( $url, $title, $only = null, $campaign = '' ) {
	$tag   = fn( $source ) => kits_share_tagged_url( $url, $source, $campaign );
	$site  = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$nets  = array( // key => [name, icon, link]
		'x'        => array( 'X', 'x-brand', 'https://x.com/intent/post?text=' . rawurlencode( $title ) . '&url=' . rawurlencode( $tag( 'x' ) ) ),
		'facebook' => array( 'Facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $tag( 'facebook' ) ) ),
		'linkedin' => array( 'LinkedIn', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $tag( 'linkedin' ) ) ),
		'whatsapp' => array( 'WhatsApp', 'brand-whatsapp', 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $tag( 'whatsapp' ) ) ),
		'email'    => array( 'Email', 'mail', 'mailto:?subject=' . rawurlencode( sprintf( 'From %s: %s', $site, $title ) ) . '&body=' . rawurlencode( $tag( 'email' ) ) ),
	);
	if ( null !== $only ) {
		$nets = array_intersect_key( $nets, array_flip( (array) $only ) );
	}
	$out = '<div class="kit-share" role="group" aria-label="' . esc_attr__( 'Share', 'kits-core' ) . '"><span class="kit-share-label" aria-hidden="true">' . esc_html__( 'Share', 'kits-core' ) . '</span>';
	foreach ( $nets as $key => list( $name, $icon, $href ) ) {
		/* translators: %s: network name. */
		$label = 'email' === $key ? __( 'Share by email', 'kits-core' ) : sprintf( __( 'Share on %s', 'kits-core' ), $name );
		$out  .= '<a class="kit-share-btn kit-share-' . $key . '" href="' . esc_url( $href ) . '"' . ( 'email' === $key ? '' : ' target="_blank" rel="noopener noreferrer"' ) . ' data-kit-share="' . $key . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="kit-ico kit-ico--' . $icon . '" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $name ) . '</span></a>';
	}
	$copy = __( 'Copy link', 'kits-core' );
	$out .= '<button type="button" class="kit-share-btn kit-share-copy" data-url="' . esc_attr( $tag( 'copy' ) ) . '" data-done="' . esc_attr__( 'Link copied', 'kits-core' ) . '" aria-label="' . esc_attr( $copy ) . '" title="' . esc_attr( $copy ) . '"><span class="kit-ico kit-ico--link" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $copy ) . '</span></button>';
	return $out . '<span class="screen-reader-text" role="status" aria-live="polite"></span></div>';
}

// At the end of <main> on single items, after the content and any call to action.
add_filter(
	'render_block_core/group',
	function ( $content, $block ) {
		static $done = false;
		$types = kits_share_types();
		if ( $done || ! $types || 'main' !== ( $block['attrs']['tagName'] ?? '' ) || ! is_singular( $types ) ) { // No types: off (is_singular( [] ) would match every page).
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
