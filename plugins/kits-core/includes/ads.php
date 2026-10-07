<?php
/**
 * Ad slots. Admins paste network code (e.g. AdSense) in Settings > Ads; empty slots print nothing.
 * Slots: homepage (between sections), article (after the story), archive (after the 6th card).
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
	'head'    => 'Site-wide code in <head> (network script, auto ads)',
);

function aw_ad( $slot ) {
	$code = (string) get_option( 'aw_ad_' . $slot, '' );
	if ( '' === trim( $code ) || 'head' === $slot || apply_filters( 'kits_ads_skip', false, $slot ) ) {
		return '';
	}
	return '<aside class="aw-ad aw-ad-' . esc_attr( $slot ) . '" aria-label="Advertisement"><p class="aw-ad-label">Advertisement</p>' . $code . '</aside>';
}

/** The section-list ad, once per page (themes with their own list markup call this at the end of the list). */
function kits_list_ad() {
	static $done = false;
	if ( $done || is_admin() || ! ( is_archive() || is_home() ) ) {
		return '';
	}
	$ad = aw_ad( 'archive' );
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
		if ( $done || ! is_singular() || is_front_page() || ! in_the_loop() && ! is_main_query() ) {
			return $content;
		}
		$done = true;
		return $content . aw_ad( 'article' );
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
		if ( is_admin() || ! ( is_archive() || is_home() ) ) {
			return $content;
		}
		$parts = explode( '</li>', $content );
		if ( count( $parts ) > 7 && ( $ad = kits_list_ad() ) ) { // phpcs:ignore
			$parts[5] .= '</li><li class="aw-ad-item">' . $ad;
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
