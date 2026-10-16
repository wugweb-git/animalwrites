<?php
/**
 * ALTCHA check for every aw_form_open() form: a free proof-of-work CAPTCHA, no third-party service, no cookies.
 *
 * The widget (assets/altcha.js, ALTCHA 2.3.0, MIT) fetches a signed challenge from /wp-json/kits/v1/altcha,
 * the visitor's browser solves it, and the answer travels in the hidden field `altcha`. aw_form_guard() checks
 * it with kits_altcha_verify(). The signing key is generated on first use and kept in the option kits_altcha_key.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KITS_ALTCHA_MAX = 100000; // about half a second on a mid-range phone
const KITS_ALTCHA_TTL = 20 * MINUTE_IN_SECONDS;

function kits_altcha_key() {
	$key = get_option( 'kits_altcha_key' );
	if ( ! $key ) {
		$key = bin2hex( random_bytes( 32 ) );
		update_option( 'kits_altcha_key', $key, false );
	}
	return $key;
}

/** A new signed challenge. */
function kits_altcha_challenge() {
	$salt      = bin2hex( random_bytes( 12 ) ) . '?expires=' . ( time() + KITS_ALTCHA_TTL );
	$number    = random_int( 0, KITS_ALTCHA_MAX );
	$challenge = hash( 'sha256', $salt . $number );
	return array(
		'algorithm' => 'SHA-256',
		'challenge' => $challenge,
		'maxnumber' => KITS_ALTCHA_MAX,
		'salt'      => $salt,
		'signature' => hash_hmac( 'sha256', $challenge, kits_altcha_key() ),
	);
}

/** True when the posted payload is a solved, unexpired, unused challenge we signed. */
function kits_altcha_verify( $payload ) {
	$p = json_decode( (string) base64_decode( (string) $payload, true ), true );
	if ( ! is_array( $p ) || 'SHA-256' !== ( $p['algorithm'] ?? '' ) ) {
		return false;
	}
	foreach ( array( 'challenge', 'number', 'salt', 'signature' ) as $k ) {
		if ( ! isset( $p[ $k ] ) || ! is_scalar( $p[ $k ] ) ) {
			return false;
		}
	}
	$salt = (string) $p['salt'];
	if ( ! preg_match( '/[?&]expires=(\d+)/', $salt, $m ) || (int) $m[1] < time() ) {
		return false;
	}
	$challenge = (string) $p['challenge'];
	if ( ! hash_equals( hash( 'sha256', $salt . (int) $p['number'] ), $challenge )
		|| ! hash_equals( hash_hmac( 'sha256', $challenge, kits_altcha_key() ), (string) $p['signature'] ) ) {
		return false;
	}
	$used = 'kits_altcha_' . md5( $challenge ); // each solved challenge works once
	if ( get_transient( $used ) ) {
		return false;
	}
	set_transient( $used, 1, KITS_ALTCHA_TTL );
	return true;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'kits/v1',
			'/altcha',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$r = new WP_REST_Response( kits_altcha_challenge() );
					$r->header( 'Cache-Control', 'no-store' );
					$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
					return $r;
				},
			)
		);
	}
);

// kits-core can sit outside wp-content/plugins (and outside the web root), so WordPress serves the script itself.
add_action(
	'init',
	function () {
		if ( ! isset( $_GET['kits_altcha_js'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: public, max-age=31536000, immutable' );
		readfile( KITS_CORE_DIR . 'assets/altcha.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	0
);

/**
 * Widget markup for a form. The script (67 KB) loads only when someone starts using a form on the page, so pages
 * with a form in the footer stay light.
 */
function kits_altcha_widget( $mode = '' ) {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		$src  = add_query_arg( array( 'kits_altcha_js' => 1, 'ver' => '2.3.0' ), home_url( '/' ) );
		add_action(
			'wp_footer',
			function () use ( $src ) {
				// Sit just above the form's send button (unless placed already: data-placed); load the widget on first touch of any form that has one.
				echo "<script>(function(){function place(){document.querySelectorAll('.kit-captcha:not([data-placed])').forEach(function(c){var f=c.closest('form'),b=f&&f.querySelector('button[type=submit]');for(var n=b;n&&n.parentNode!==f;n=n.parentNode);if(n){f.insertBefore(c,n);}});}"
					. "var loaded=false;function load(){if(loaded)return;loaded=true;var s=document.createElement('script');s.src=" . wp_json_encode( $src ) . ";s.defer=true;document.head.appendChild(s);}"
					. "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',place);}else{place();}"
					. "['focusin','pointerdown'].forEach(function(ev){document.addEventListener(ev,function(e){var f=e.target.closest&&e.target.closest('form');if(f&&f.querySelector('.kit-captcha'))load();},{passive:true});});})();</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			},
			20
		);
	}
	// "invisible": no check box; the check runs when the form is sent (auto="onsubmit"), same protection.
	if ( 'invisible' === $mode ) {
		return '<div class="kit-captcha kit-captcha--invisible"><altcha-widget challengeurl="' . esc_url( rest_url( 'kits/v1/altcha' ) ) . '" auto="onsubmit" hidefooter hidelogo></altcha-widget></div>';
	}
	return '<div class="kit-captcha"><altcha-widget challengeurl="' . esc_url( rest_url( 'kits/v1/altcha' ) ) . '" hidefooter hidelogo></altcha-widget></div>';
}
