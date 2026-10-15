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
	if ( ! aw_gtm_id() || is_admin() || wp_doing_ajax() || aw_analytics_skip_user() ) {
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

/** Microsoft Clarity project ID for this site ('' = off). */
function aw_clarity_id() {
	return (string) apply_filters( 'kits_clarity_id', '' );
}

// Microsoft Clarity: same rules as GTM (production host only, never for logged-in staff).
add_action(
	'wp_head',
	function () {
		if ( ! aw_clarity_id() || is_admin() || wp_doing_ajax() || aw_analytics_skip_user() ) {
			return;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! in_array( $host, array_map( 'trim', explode( ',', aw_analytics_hosts() ) ), true ) ) {
			return;
		}
		$id = esc_js( aw_clarity_id() );
		echo "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src=\"https://www.clarity.ms/tag/\"+i+\"?ref=bwt\";y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,\"clarity\",\"script\",\"{$id}\");</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	2
);

/**
 * Page context for analytics (owner, 9 Oct 2026): window.kitsPage and a dataLayer "page_context" push before any tag,
 * so GTM tags and the events in kit.js know the page type, section, category, place, listing type, issuer and
 * publisher. A site fills the values with the kits_page_context filter. gtag = true while GA4 is placed directly by
 * Site Kit (events then also go to gtag); false once GA4 runs only inside GTM.
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}
		$ga  = (array) get_option( 'googlesitekit_analytics-4_settings', array() );
		$ctx = array(
			'page_type'    => is_front_page() ? 'home' : ( is_404() ? '404' : ( is_singular() ? get_post_type() : 'list' ) ),
			'section'      => '',
			'category'     => '',
			'state'        => '',
			'district'     => '',
			'listing_type' => '',
			'issuer'       => '',
			'publisher'    => '',
		);
		$ctx         = array_map( 'strval', (array) apply_filters( 'kits_page_context', $ctx ) );
		$ctx['gtag'] = ! empty( $ga['useSnippet'] ) && ! get_option( 'kits_ga4_via_gtm' );
		echo '<script>window.kitsPage=' . wp_json_encode( $ctx ) . ';(window.dataLayer=window.dataLayer||[]).push(Object.assign({event:"page_context"},window.kitsPage));</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	0
);

/**
 * Logged-in staff (anyone who can edit content) are never counted (owner, 14 Oct 2026): no GTM container for them,
 * whether Site Kit places it or this file does. Visitors are unaffected.
 */
function aw_analytics_skip_user() {
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}
add_filter( 'googlesitekit_tagmanager_tag_blocked', fn( $blocked ) => $blocked || aw_analytics_skip_user() );
add_filter( 'googlesitekit_analytics-4_tag_blocked', fn( $blocked ) => $blocked || aw_analytics_skip_user() );
