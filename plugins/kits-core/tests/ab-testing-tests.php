<?php
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}
/**
 * Unit tests for Kits Core Native A/B Testing & Multivariate Engine.
 * Runs standalone without WordPress database.
 *
 * Usage: php plugins/kits-core/tests/ab-testing-tests.php
 *
 * @package kits-core
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable -- standalone test harness with WP stubs.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['t_actions']    = array();
$GLOBALS['t_shortcodes'] = array();
$GLOBALS['t_filters']    = array();

function add_action( $hook, $cb, $prio = 10 ) {
	$GLOBALS['t_actions'][ $hook ][] = array( $cb, $prio );
	return true;
}

function add_shortcode( $tag, $cb ) {
	$GLOBALS['t_shortcodes'][ $tag ] = $cb;
	return true;
}

function apply_filters( $tag, $value, ...$args ) {
	if ( isset( $GLOBALS['t_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['t_filters'][ $tag ] as $cb ) {
			$value = $cb( $value, ...$args );
		}
	}
	return $value;
}

function add_filter( $tag, $cb ) {
	$GLOBALS['t_filters'][ $tag ][] = $cb;
	return true;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function wp_unslash( $val ) {
	return is_string( $val ) ? stripslashes( $val ) : $val;
}

function is_ssl() {
	return true;
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

function do_shortcode( $content ) {
	return $content;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

require_once __DIR__ . '/../includes/ab-testing.php';

$count = 0;
function check( $cond, $msg ) {
	global $count;
	++$count;
	if ( ! $cond ) {
		echo "FAIL: $msg\n";
		exit( 1 );
	}
	echo "ok   $msg\n";
}

// Test 1: Function exists and default variant assignment.
check( function_exists( 'kits_ab_variant' ), 'kits_ab_variant function exists' );
$variant = kits_ab_variant( 'test_hero' );
check( in_array( $variant, array( 'A', 'B' ), true ), 'default variant is A or B' );

// Test 2: Query parameter QA override.
$_GET['kits_ab_qa_test'] = 'B';
check( 'B' === kits_ab_variant( 'qa_test', array( 'A', 'B' ) ), 'QA query param forces variant B' );
unset( $_GET['kits_ab_qa_test'] );

// Test 3: Cookie persistence.
$_COOKIE['kits_ab_cookie_test'] = 'C';
check( 'C' === kits_ab_variant( 'cookie_test', array( 'A', 'B', 'C' ) ), 'Cookie value preserves variant C' );
unset( $_COOKIE['kits_ab_cookie_test'] );

// Test 4: 100% Weighted allocation.
$weighted = kits_ab_variant( 'weight_test', array( 'A', 'B' ), array( 0, 100 ) );
check( 'B' === $weighted, '100% weight to B always selects variant B' );

// Test 5: kits_ab_is helper.
$_GET['kits_ab_is_test'] = 'A';
check( kits_ab_is( 'is_test', 'A' ), 'kits_ab_is returns true for active variant' );
check( ! kits_ab_is( 'is_test', 'B' ), 'kits_ab_is returns false for inactive variant' );
unset( $_GET['kits_ab_is_test'] );

// Test 6: Shortcode registration and conditional rendering.
check( isset( $GLOBALS['t_shortcodes']['kits_ab'] ), 'kits_ab shortcode registered' );
$cb = $GLOBALS['t_shortcodes']['kits_ab'];
$_GET['kits_ab_sc_test'] = 'A';
$rendered_a = $cb( array( 'id' => 'sc_test', 'variant' => 'A' ), 'Variant A Content' );
$rendered_b = $cb( array( 'id' => 'sc_test', 'variant' => 'B' ), 'Variant B Content' );
check( 'Variant A Content' === $rendered_a, 'Shortcode renders matching variant content' );
check( '' === $rendered_b, 'Shortcode suppresses non-matching variant content' );
unset( $_GET['kits_ab_sc_test'] );

// Test 7: wp_footer analytics hook registered.
check( isset( $GLOBALS['t_actions']['wp_footer'] ), 'wp_footer analytics hook registered' );

echo "\nAll {$count} A/B testing checks passed cleanly.\n";
