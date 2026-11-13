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
	return kits_share_html( (string) get_permalink( $post_id ), html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ), $nets, $camp, array( 'post_id' => (int) $post_id ) );
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
 * The one share payload of a page (owner, 13 Nov 2026: "page should share image and content and link and hashtag ...
 * standardise this function"): what every share sends, the server's share bar, the phone's share sheet (kit.js) and a
 * campaign's own script (window.awCampShare, campaign-tools.php) alike.
 *
 * Filter kits_share_args fills it in: 'text' (the share line: a campaign's share line, else the page's og:description;
 * the title when nothing fills it), 'hashtags' (#AnimalWrites first, then the campaign's own), 'image' (the page's
 * 1080x1080 share square on our own server, for the share sheet; never a third-party picture), 'url' (the address to
 * hand out in place of $url: a campaign's short link). 'post_id' names the post the share is for (0: the current view).
 *
 * @param string $url      Page address.
 * @param string $title    Plain-text title.
 * @param string $campaign utm_campaign of the shared address (campaign slug, post type or 'term').
 * @param array  $args     See kits_share_html().
 * @return array{url:string,title:string,text:string,hashtags:string[],hash:string,message:string,image:string,campaign:string,args:array}
 *               'url' untagged (kits_share_tagged_url() tags it per network); 'hash' " #AnimalWrites #Tag";
 *               'message' the line with its hashtags.
 */
function kits_share_payload( $url, $title, $campaign = '', $args = array() ) {
	$args = (array) apply_filters( 'kits_share_args', array_merge( array( 'text' => '', 'hashtags' => array(), 'image' => '', 'url' => '', 'post_id' => 0, 'follow' => array(), 'button' => '', 'order' => false ), (array) $args ), $url, $campaign );
	$text = '' !== trim( (string) $args['text'] ) ? trim( (string) $args['text'] ) : $title;
	$tags = array_values( array_unique( array_filter( array_map( fn( $t ) => preg_replace( '/[^A-Za-z0-9_]/', '', (string) $t ), (array) $args['hashtags'] ), 'strlen' ) ) );
	$hash = $tags ? ' ' . implode( ' ', array_map( fn( $t ) => '#' . $t, $tags ) ) : ''; // "#AnimalWrites #Tag" after the line
	return array(
		'url'      => '' !== (string) $args['url'] ? (string) $args['url'] : (string) $url,
		'title'    => (string) $title,
		'text'     => $text,
		'hashtags' => $tags,
		'hash'     => $hash,
		'message'  => $text . $hash,
		'image'    => (string) $args['image'],
		'campaign' => (string) $campaign,
		'args'     => $args,
	);
}

/**
 * The share bar for any address and title (a post, a category or tag page).
 *
 * @param string        $url      Page address.
 * @param string        $title    Plain-text title.
 * @param string[]|null $only     Network keys to show, in the usual order; null for every network. Copy link always shows.
 * @param string        $campaign utm_campaign of the shared address (campaign slug, post type or 'term').
 * @param array         $args     The share payload (kits_share_payload()): 'text', 'hashtags', 'image', 'url', 'post_id';
 *                                and 'follow' (our own accounts as [label, url, icon] rows: a "Follow us" row under the
 *                                bar; pages only, never post types (owner, 13 Nov 2026)), 'button' (a page Share
 *                                section's one button, e.g. "Share this page": always shown, the phone's share sheet
 *                                where there is one, else it copies the link; owner, 13 Nov 2026), 'order' (true: the
 *                                networks in the order of $only).
 * @return string
 */
function kits_share_html( $url, $title, $only = null, $campaign = '', $args = array() ) {
	$p     = kits_share_payload( $url, $title, $campaign, $args );
	$args  = $p['args'];
	$url   = $p['url'];
	$text  = $p['text'];
	$tags  = $p['hashtags'];
	$hash  = $p['hash'];
	$tag   = fn( $source ) => kits_share_tagged_url( $url, $source, $campaign );
	$site  = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$nets  = array( // key => [name, icon, link]
		'x'        => array( 'X', 'x-brand', 'https://x.com/intent/post?text=' . rawurlencode( $text ) . '&url=' . rawurlencode( $tag( 'x' ) ) . ( $tags ? '&hashtags=' . rawurlencode( implode( ',', $tags ) ) : '' ) ),
		'facebook' => array( 'Facebook', 'facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $tag( 'facebook' ) ) ), // Facebook takes the link only; its card comes from the page's link preview
		'linkedin' => array( 'LinkedIn', 'linkedin', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $tag( 'linkedin' ) ) ),
		'whatsapp' => array( 'WhatsApp', 'brand-whatsapp', 'https://wa.me/?text=' . rawurlencode( $text . ' ' . $tag( 'whatsapp' ) . $hash ) ),
		'email'    => array( 'Email', 'mail', 'mailto:?subject=' . rawurlencode( sprintf( 'From %s: %s', $site, $title ) ) . '&body=' . rawurlencode( $p['message'] . "\n\n" . $tag( 'email' ) ) ),
	);
	if ( null !== $only ) {
		$nets = array_intersect_key( $nets, array_flip( (array) $only ) );
		if ( $args['order'] ) {
			$nets = array_replace( array_intersect_key( array_flip( (array) $only ), $nets ), $nets );
		}
	}
	$out = '<div class="kit-share" role="group" aria-label="' . esc_attr__( 'Share', 'kits-core' ) . '"><span class="kit-share-label" aria-hidden="true">' . esc_html__( 'Share', 'kits-core' ) . '</span>';
	// The phone's own share sheet (Web Share): hidden until kit.js finds navigator.share. It hands over the payload: the
	// line with its hashtags, the link and the page's 1080x1080 share picture; each app keeps what it accepts.
	// A page Share section's one button ('button'): shown without waiting for kit.js; where the browser has no share sheet
	// it copies the link (data-fallback), so the one button always does something.
	$main  = '' !== (string) $args['button'];
	$share = $main ? (string) $args['button'] : __( 'Share', 'kits-core' );
	$out  .= '<button type="button" class="kit-share-btn kit-share-native' . ( $main ? ' kit-share-main" data-fallback="copy" data-done="' . esc_attr__( 'Link copied', 'kits-core' ) . '"' : '" hidden' ) . ' data-kit-share="native" data-url="' . esc_attr( $tag( 'native' ) ) . '" data-title="' . esc_attr( $title ) . '" data-text="' . esc_attr( $p['message'] ) . '"' . ( '' !== $p['image'] ? ' data-image="' . esc_url( $p['image'] ) . '"' : '' ) . ' aria-label="' . esc_attr( $share ) . '"><span class="kit-ico kit-ico--share" aria-hidden="true"></span><span class="kit-share-name">' . esc_html( $share ) . '</span></button>';
	foreach ( $nets as $key => list( $name, $icon, $href ) ) {
		/* translators: %s: network name. */
		$label = 'email' === $key ? __( 'Share by email', 'kits-core' ) : sprintf( __( 'Share on %s', 'kits-core' ), $name );
		$out  .= '<a class="kit-share-btn kit-share-' . $key . '" href="' . esc_url( $href ) . '"' . ( 'email' === $key ? '' : ' target="_blank" rel="noopener noreferrer"' ) . ' data-kit-share="' . $key . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="kit-ico kit-ico--' . $icon . '" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $name ) . '</span></a>';
	}
	$copy = __( 'Copy link', 'kits-core' );
	$out .= '<button type="button" class="kit-share-btn kit-share-copy" data-url="' . esc_attr( $tag( 'copy' ) ) . '" data-done="' . esc_attr__( 'Link copied', 'kits-core' ) . '" aria-label="' . esc_attr( $copy ) . '" title="' . esc_attr( $copy ) . '"><span class="kit-ico kit-ico--link" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $copy ) . '</span></button>';
	return $out . '<span class="screen-reader-text" role="status" aria-live="polite"></span></div>' . kits_follow_html( (array) $args['follow'] );
}

/**
 * "Follow us": the site's own social accounts as the share bar's buttons, one row under it (owner, 13 Nov 2026). Only
 * where a page asks for it (kits_share_html 'follow'); the share bar at the end of posts never carries it.
 *
 * @param array $rows [label, url, icon] rows.
 * @return string
 */
function kits_follow_html( $rows ) {
	$rows = array_filter( $rows, fn( $r ) => is_array( $r ) && ! empty( $r[0] ) && ! empty( $r[1] ) );
	if ( ! $rows ) {
		return '';
	}
	$out = '<div class="kit-share kit-follow" role="group" aria-label="' . esc_attr__( 'Follow us', 'kits-core' ) . '"><span class="kit-share-label" aria-hidden="true">' . esc_html__( 'Follow us', 'kits-core' ) . '</span>';
	foreach ( $rows as $r ) {
		list( $name, $href ) = $r;
		$icon = isset( $r[2] ) ? (string) $r[2] : 'link';
		/* translators: %s: network name. */
		$label = sprintf( __( 'Follow us on %s', 'kits-core' ), $name );
		$out  .= '<a class="kit-share-btn kit-follow-btn" href="' . esc_url( $href ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="kit-ico kit-ico--' . esc_attr( sanitize_html_class( $icon ) ) . '" aria-hidden="true"></span><span class="kit-share-name" aria-hidden="true">' . esc_html( $name ) . '</span></a>';
	}
	return $out . '</div>';
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
