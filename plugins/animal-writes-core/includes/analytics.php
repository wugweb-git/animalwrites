<?php
/**
 * Google Tag Manager (same container as Animal Writes 1.0, so GA4 history continues).
 *
 * Only output on the production host, so staging traffic never reaches analytics.
 * Skipped when Site Kit is already inserting a GTM container (avoids double counting).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AW_GTM_ID' ) ) {
	define( 'AW_GTM_ID', 'GTM-W9D28GKV' );
}
if ( ! defined( 'AW_ANALYTICS_HOSTS' ) ) {
	define( 'AW_ANALYTICS_HOSTS', 'animalwrites.in,www.animalwrites.in' );
}

/**
 * Whether the GTM snippet should be printed on this request.
 *
 * @return bool
 */
function aw_gtm_enabled() {
	if ( ! AW_GTM_ID || is_admin() || wp_doing_ajax() ) {
		return false;
	}
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$hosts = array_map( 'trim', explode( ',', AW_ANALYTICS_HOSTS ) );
	if ( ! in_array( $host, $hosts, true ) ) {
		return false;
	}
	$sitekit = get_option( 'googlesitekit_tagmanager_settings' );
	if ( is_array( $sitekit ) && ! empty( $sitekit['containerID'] ) && ! empty( $sitekit['useSnippet'] ) ) {
		return false;
	}
	return true;
}

add_action(
	'wp_head',
	function () {
		if ( ! aw_gtm_enabled() ) {
			return;
		}
		$id = esc_js( AW_GTM_ID );
		echo "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>\n<!-- End Google Tag Manager -->\n";
	},
	1
);

add_action(
	'wp_body_open',
	function () {
		if ( ! aw_gtm_enabled() ) {
			return;
		}
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( AW_GTM_ID ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}
);
