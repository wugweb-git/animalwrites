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
	'home'      => 'Homepage, between two sections (one per page)',
	'incontent' => 'Inside an article, after paragraph 3 (only articles with 6+ paragraphs)',
	'article'   => 'End of an article, before its categories and share buttons',
	'archive'   => 'Section lists (News, Library, Resources): a card after item 6, and after item 18 on long pages',
	'directory' => 'Directory state and district pages, after the 5th organisation (empty: the section-list ad)',
	'profile'   => 'Directory profiles, above "Is this your organisation?" (empty: the end-of-article ad)',
	'head'      => 'Site-wide code in <head> (network script, auto ads)',
);

function aw_ad( $slot ) {
	static $shown = 0;
	$code = (string) get_option( 'aw_ad_' . $slot, '' );
	if ( '' === trim( $code ) || 'head' === $slot || kits_ads_excluded( $slot ) || $shown >= kits_ads_max() ) {
		return '';
	}
	++$shown;
	// One line inside a block element, so wpautop in list shortcodes adds no <p>/<br> around the network code.
	$code = (string) preg_replace( '/\s*[\r\n]+\s*/', ' ', $code );
	return '<aside class="kit-ad aw-ad-' . esc_attr( $slot ) . '" aria-label="Advertisement"><p class="kit-ad-label"><span class="kit-badge kit-badge--ad">Ad</span></p><div class="kit-ad-unit aw-ad-unit">' . $code . '</div></aside>';
}

/** No ads on this page at all (also no head code and no auto ads): forms, 404, search, crisis pages… (kits_ads_skip). */
function kits_ads_excluded( $slot = 'head' ) {
	return is_admin() || is_404() || (bool) apply_filters( 'kits_ads_skip', false, $slot );
}

/** Most ads one page may carry: articles 3, lists 2, everything else (home, profiles) 1. */
function kits_ads_max() {
	$max = is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ? 3 : ( kits_ads_is_list() ? 2 : 1 );
	return (int) apply_filters( 'kits_ads_max', $max );
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
		if ( '' !== trim( $code ) && ! kits_ads_excluded() ) {
			echo $code . "\n"; // phpcs:ignore -- admin-entered ad network code.
		}
	},
	5
);

// Inside an article: after paragraph 3, only when the article has at least 6 paragraphs.
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) || substr_count( $content, '</p>' ) < 6 ) {
			return $content;
		}
		$done = true;
		$ad   = aw_ad( 'incontent' );
		if ( '' === $ad ) {
			return $content;
		}
		$pos = 0;
		for ( $i = 0; $i < 3; $i++ ) {
			$pos = strpos( $content, '</p>', $pos ) + 4;
		}
		return substr( $content, 0, $pos ) . $ad . substr( $content, $pos );
	},
	30
);

// End of an article: before its categories and share buttons (kits_endbar), or after the text where there is no end bar.
add_filter( 'kits_endbar_before', fn( $html ) => $html . ( is_singular( (array) apply_filters( 'kits_ads_article_types', array( 'post' ) ) ) ? aw_ad( 'article' ) : '' ) );
add_filter(
	'render_block_core/post-content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_singular( (array) apply_filters( 'kits_ads_end_after_content_types', array() ) ) ) {
			return $content;
		}
		$done = true;
		return $content . aw_ad( 'article' );
	},
	40
);

// Lists: a card after item 6, and after item 18 when the page has at least 20 items (Directory places: after item 5).
add_filter(
	'render_block_core/post-template',
	function ( $content ) {
		if ( is_admin() || ! kits_ads_is_list() ) {
			return $content;
		}
		$slot  = (string) apply_filters( 'kits_ads_list_slot', 'archive' );
		$at    = (array) apply_filters( 'kits_ads_list_positions', array( 6, 18 ), $slot );
		$parts = explode( '</li>', $content );
		$items = count( $parts ) - 1;
		foreach ( array_reverse( $at ) as $n ) {
			if ( $items <= $n || ( $n > 6 && $items < 20 ) ) {
				continue;
			}
			$ad = aw_ad( $slot );
			if ( '' === $ad && 'archive' !== $slot ) {
				$ad = aw_ad( 'archive' );
			}
			if ( $ad ) {
				$parts[ $n - 1 ] .= '</li><li class="kit-ad-item">' . $ad;
			}
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
add_filter( 'googlesitekit_adsense_tag_blocked', fn( $b ) => is_admin() || '' === kits_adsense_client() ? $b : true ); // our loader below replaces it
add_action(
	'wp_head',
	function () {
		$c = kits_adsense_client();
		if ( '' === $c || kits_ads_excluded() ) {
			return;
		}
		$src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode( $c ) . '&host=ca-host-pub-2644536267352236';
		echo '<meta name="google-adsense-account" content="' . esc_attr( $c ) . '">' . "\n";
		echo '<script>(function(){function go(){var s=document.createElement("script");s.async=true;s.crossOrigin="anonymous";s.src=' . wp_json_encode( $src ) . ';document.head.appendChild(s);}function later(){("requestIdleCallback" in window)?requestIdleCallback(go,{timeout:3000}):setTimeout(go,1500);}if(document.readyState==="complete"){later();}else{window.addEventListener("load",later,{once:true});}})();</script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources
	},
	5
);
