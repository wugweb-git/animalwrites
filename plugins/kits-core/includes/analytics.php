<?php
/**
 * Google Tag Manager. The container ID and production hosts come from the site (filters kits_gtm_id, kits_analytics_hosts).
 *
 * Only output on the production host, so staging traffic never reaches analytics.
 * Skipped when Site Kit is already inserting a GTM container (avoids double counting).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** GTM container ID for this site ('' = off). */
function aw_gtm_id() {
	return (string) apply_filters( 'kits_gtm_id', defined( 'KITS_GTM_ID' ) ? KITS_GTM_ID : '' );
}

/** Comma-separated production hosts on which analytics may run. */
function aw_analytics_hosts() {
	return (string) apply_filters( 'kits_analytics_hosts', defined( 'KITS_ANALYTICS_HOSTS' ) ? KITS_ANALYTICS_HOSTS : '' );
}

/**
 * Whether the GTM snippet should be printed on this request.
 *
 * @return bool
 */
function aw_gtm_enabled() {
	if ( ! aw_gtm_id() || is_admin() || wp_doing_ajax() ) {
		return false;
	}
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$hosts = array_map( 'trim', explode( ',', aw_analytics_hosts() ) );
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
		$id = esc_js( aw_gtm_id() );
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
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( aw_gtm_id() ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
	}
);
