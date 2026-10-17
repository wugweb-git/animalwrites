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

/**
 * Consent mode v2 regions (owner, 17 Oct 2026): the EEA (EU 27 plus Iceland, Liechtenstein, Norway), the UK and
 * Switzerland, as ISO 3166-1 alpha-2 codes. Filter kits_consent_regions to change the list.
 *
 * @return string[]
 */
function kits_consent_regions() {
	$eea = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO' );
	return array_values( array_unique( array_map( 'strtoupper', (array) apply_filters( 'kits_consent_regions', array_merge( $eea, array( 'GB', 'CH' ) ) ) ) ) );
}

/** IANA time zones of the consent regions (what a browser reports), for scripts that cannot ask Google for the region. */
function kits_consent_timezones() {
	$zones = array( 'Europe/Mariehamn', 'Arctic/Longyearbyen', 'Europe/Nicosia', 'Europe/Belfast' ); // aliases browsers may report
	foreach ( kits_consent_regions() as $cc ) {
		$zones = array_merge( $zones, (array) DateTimeZone::listIdentifiers( DateTimeZone::PER_COUNTRY, $cc ) );
	}
	return array_values( array_unique( $zones ) );
}

/**
 * Consent mode v2 (basic), before any Google tag (GTM, AdSense, page_context push): everything denied by default in the
 * consent regions until Google's CMP (AdSense Privacy & messaging) sends an update, granted everywhere else; ad data
 * redacted and URL passthrough on while denied. The CMP itself is Google's, loaded by AdSense; no banner here.
 */
add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}
		$region = wp_json_encode( kits_consent_regions() );
		echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500,region:{$region}});gtag('consent','default',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});gtag('set','ads_data_redaction',true);gtag('set','url_passthrough',true);</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	-100
);

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
		// Page speed: the container starts once the page has loaded, or at the visitor's first tap, key or scroll if that
		// comes first, so it never holds back the first paint. gtm.start is stamped now; everything pushed to the dataLayer
		// before the container starts (consent defaults, page_context, events) is kept and read by it.
		echo "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var done=false,ev=['pointerdown','keydown','scroll'];function go(){if(done)return;done=true;ev.forEach(function(e){w.removeEventListener(e,go,true);});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);}ev.forEach(function(e){w.addEventListener(e,go,{capture:true,passive:true});});if(d.readyState==='complete'){go();}else{w.addEventListener('load',go,{once:true});}})(window,document,'script','dataLayer','{$id}');</script>\n<!-- End Google Tag Manager -->\n";
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

// Microsoft Clarity: same rules as GTM (production host only, never for logged-in staff). Outside the consent region it
// loads at once; inside it (judged by the browser's time zone, kits_consent_timezones()) only once analytics storage is
// granted, read from Google's consent mode (gtag consent update) or the CMP's TCF signal (purpose 1), and then it is
// told so with clarity('consentv2'). Page speed: its script is fetched after the page has loaded, when the browser is
// idle; calls made before then (consentv2, events) queue in clarity.q and are read when it starts.
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
		$tz = wp_json_encode( kits_consent_timezones() );
		echo "<script>(function(w,d,id,zones){var on=false;function load(g){if(!on){on=true;(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};function go(){t=l.createElement(r);t.async=1;t.src=\"https://www.clarity.ms/tag/\"+i+\"?ref=bwt\";y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);}function idle(){(\"requestIdleCallback\" in c)?requestIdleCallback(go,{timeout:2000}):setTimeout(go,1000);}if(l.readyState===\"complete\"){idle();}else{c.addEventListener(\"load\",idle,{once:true});}})(w,d,\"clarity\",\"script\",id);}if(g){w.clarity(\"consentv2\",{ad_Storage:g.ad_storage===\"granted\"?\"granted\":\"denied\",analytics_Storage:\"granted\"});}}var tz=\"\";try{tz=Intl.DateTimeFormat().resolvedOptions().timeZone||\"\";}catch(e){}if(zones.indexOf(tz)<0){load();return;}function seen(a){if(a&&a[0]===\"consent\"&&a[1]===\"update\"&&a[2]&&a[2].analytics_storage===\"granted\"){load(a[2]);}}var dl=w.dataLayer=w.dataLayer||[];for(var i=0;i<dl.length;i++){seen(dl[i]);}var push=dl.push;dl.push=function(){for(var j=0;j<arguments.length;j++){seen(arguments[j]);}return push.apply(dl,arguments);};var n=0,t=setInterval(function(){if(typeof w.__tcfapi===\"function\"){clearInterval(t);w.__tcfapi(\"addEventListener\",2,function(tc,ok){if(ok&&tc&&(tc.eventStatus===\"tcloaded\"||tc.eventStatus===\"useractioncomplete\")&&tc.gdprApplies&&tc.purpose&&tc.purpose.consents&&tc.purpose.consents[1]){load({ad_storage:\"granted\"});}});}else if(++n>40){clearInterval(t);}},250);})(window,document,\"{$id}\",{$tz});</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
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
