<?php
/**
 * Ad slots. Admins paste network code (e.g. AdSense) in Settings > Ads; empty slots print nothing.
 * Slots: homepage (between sections), article (after the story), archive (lists, after the 6th card), directory (directory
 * lists; falls back to archive), profile (directory entries; falls back to article), results (search, after the 6th result).
 * A theme marks its own list pages with the kits_ads_is_list filter (for example lists built as Pages).
 * Also serves /ads.txt from the same settings page.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_AD_SLOTS = array(
	'home'    => 'Homepage, between sections',
	'article' => 'After a story, guide or page',
	'archive' => 'Section lists, after the 6th item (or at the end of the list)',
	'directory' => 'Directory lists, after the 6th organisation (empty: the section-list ad)',
	'profile' => 'Directory profiles, after the details (empty: the after-a-story ad)',
	'results' => 'Search results, after the 6th result',
	'head'    => 'Site-wide code in <head> (network script, auto ads)',
);

function aw_ad( $slot ) {
	$code = (string) get_option( 'aw_ad_' . $slot, '' );
	if ( '' === trim( $code ) || 'head' === $slot || apply_filters( 'kits_ads_skip', false, $slot ) ) {
		return '';
	}
	// One line inside a block element, so wpautop in list shortcodes adds no <p>/<br> around the network code.
	$code = (string) preg_replace( '/\s*[\r\n]+\s*/', ' ', $code );
	return '<aside class="kit-ad aw-ad-' . esc_attr( $slot ) . '" aria-label="Advertisement"><p class="kit-ad-label"><span class="kit-badge kit-badge--ad">Advertisement</span></p><div class="kit-ad-unit aw-ad-unit">' . $code . '</div></aside>';
}

/** Is this a list page (archives, the blog, or a theme's own list Pages)? */
function kits_ads_is_list() {
	return (bool) apply_filters( 'kits_ads_is_list', is_archive() || is_home() );
}

/** The section-list ad, once per page (themes with their own list markup call this at the end of the list). */
function kits_list_ad() {
	static $done = false;
	if ( $done || is_admin() || ! kits_ads_is_list() ) {
		return '';
	}
	$slot = (string) apply_filters( 'kits_ads_list_slot', 'archive' );
	$ad   = aw_ad( $slot );
	if ( '' === $ad && 'archive' !== $slot ) {
		$ad = aw_ad( 'archive' );
	}
	$done = (bool) $ad;
	return $ad;
}

add_shortcode( 'aw_ad', fn( $a ) => aw_ad( sanitize_key( $a['slot'] ?? 'home' ) ) );

add_action(
	'wp_head',
	function () {
		$code = (string) get_option( 'aw_ad_head', '' );
		if ( '' !== trim( $code ) && ! is_admin() ) {
			echo $code . "\n"; // phpcs:ignore -- admin-entered ad network code.
		}
	},
	5
);

// After the main story on single items and pages (not the homepage).
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular() || is_front_page() || kits_ads_is_list() || ! in_the_loop() && ! is_main_query() ) {
			return $content;
		}
		$done = true;
		$slot = (string) apply_filters( 'kits_ads_article_slot', 'article' );
		$ad   = aw_ad( $slot );
		return $content . ( '' === $ad && 'article' !== $slot ? aw_ad( 'article' ) : $ad );
	}
);
add_filter(
	'render_block_core/post-excerpt',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ) {
			return $content;
		}
		$done = true;
		return $content . aw_ad( 'article' );
	},
	20
);

// Section lists: after the 6th card.
add_filter(
	'render_block_core/post-template',
	function ( $content ) {
		if ( is_admin() || ! kits_ads_is_list() ) {
			return $content;
		}
		$parts = explode( '</li>', $content );
		if ( count( $parts ) > 7 && ( $ad = kits_list_ad() ) ) { // phpcs:ignore
			$parts[5] .= '</li><li class="kit-ad-item">' . $ad;
		}
		return implode( '</li>', $parts );
	}
);

// /ads.txt
add_action(
	'init',
	function () {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore
		if ( '/ads.txt' === $path ) {
			$txt = (string) get_option( 'aw_ads_txt', '' );
			if ( '' !== trim( $txt ) ) {
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo $txt; // phpcs:ignore
				exit;
			}
		}
	}
);

// Settings > Ads (administrators only).
add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Ads',
			'Ads',
			'manage_options',
			'aw-ads',
			function () {
				if ( isset( $_POST['aw_ads_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aw_ads_nonce'] ) ), 'aw_ads' ) && current_user_can( 'unfiltered_html' ) ) {
					foreach ( array_keys( AW_AD_SLOTS ) as $s ) {
						update_option( 'aw_ad_' . $s, wp_unslash( $_POST[ 'aw_ad_' . $s ] ?? '' ) ); // phpcs:ignore -- raw ad code by admins.
					}
					update_option( 'aw_ads_txt', sanitize_textarea_field( wp_unslash( $_POST['aw_ads_txt'] ?? '' ) ) );
					echo '<div class="updated"><p>Saved.</p></div>';
				}
				echo '<div class="wrap"><h1>Ads</h1><p>Paste the code from your ad network. Empty slots show nothing. Every ad is labelled "Advertisement".</p><form method="post">';
				wp_nonce_field( 'aw_ads', 'aw_ads_nonce' );
				foreach ( AW_AD_SLOTS as $s => $label ) {
					echo '<h2>' . esc_html( $label ) . '</h2><textarea name="aw_ad_' . esc_attr( $s ) . '" rows="5" class="large-text code">' . esc_textarea( (string) get_option( 'aw_ad_' . $s, '' ) ) . '</textarea>';
				}
				echo '<h2>ads.txt</h2><p>Served at <code>/ads.txt</code>.</p><textarea name="aw_ads_txt" rows="4" class="large-text code">' . esc_textarea( (string) get_option( 'aw_ads_txt', '' ) ) . '</textarea>';
				submit_button();
				echo '</form></div>';
			}
		);
	}
);

/**
 * Page speed: AdSense (Site Kit) starts after the page has loaded, not during it, so its script and ad frames no longer
 * hold back the page. The account stays verified through Google's meta tag in the head. Auto ads work as before.
 */
function kits_adsense_client() {
	$s = (array) get_option( 'googlesitekit_adsense_settings', array() );
	return ! empty( $s['useSnippet'] ) && preg_match( '/^ca-pub-\d+$/', (string) ( $s['clientID'] ?? '' ) ) ? (string) $s['clientID'] : '';
}
add_filter( 'googlesitekit_adsense_tag_blocked', fn( $b ) => is_admin() || '' === kits_adsense_client() ? $b : true );
add_action(
	'wp_head',
	function () {
		$c = kits_adsense_client();
		if ( '' === $c || is_admin() ) {
			return;
		}
		$src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode( $c ) . '&host=ca-host-pub-2644536267352236';
		echo '<meta name="google-adsense-account" content="' . esc_attr( $c ) . '">' . "\n";
		echo '<script>(function(){function go(){var s=document.createElement("script");s.async=true;s.crossOrigin="anonymous";s.src=' . wp_json_encode( $src ) . ';document.head.appendChild(s);}function later(){("requestIdleCallback" in window)?requestIdleCallback(go,{timeout:3000}):setTimeout(go,1500);}if(document.readyState==="complete"){later();}else{window.addEventListener("load",later,{once:true});}})();</script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
	},
	5
);
