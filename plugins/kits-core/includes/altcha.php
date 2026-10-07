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

/** Widget markup for a form; the script loads only on pages that have one. */
function kits_altcha_widget() {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		wp_enqueue_script( 'kits-altcha', plugins_url( 'assets/altcha.js', KITS_CORE_DIR . 'kits-core.php' ), array(), '2.3.0', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		// Sit just above the form's send button rather than at the top of the form.
		wp_add_inline_script( 'kits-altcha', "document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.aw-captcha').forEach(function(c){var f=c.closest('form'),b=f&&f.querySelector('button[type=submit]');for(var n=b;n&&n.parentNode!==f;n=n.parentNode);if(n){f.insertBefore(c,n);}});});", 'before' );
	}
	return '<div class="aw-captcha"><altcha-widget challengeurl="' . esc_url( rest_url( 'kits/v1/altcha' ) ) . '" auto="onfocus" hidefooter hidelogo></altcha-widget></div>';
}
