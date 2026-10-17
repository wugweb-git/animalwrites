<?php
/**
 * Lightweight visit counter endpoint (17 Oct 2026, server load): POST here counts one visit with WordPress loaded in
 * SHORTINIT mode (database and options only; no plugins, theme or REST), several times cheaper than the REST route.
 * The counting itself is Kits Core's kits_visit_count() (bots, prefetch/prerender and floods are not counted).
 *
 * Kits Core is bundled next to this plugin without a public address, so this file is the public door to it
 * (animal-writes-core.php points the footer script here through the kits_visit_endpoint filter).
 *
 * @package animal-writes-core
 */

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	http_response_code( 405 );
	header( 'Allow: POST' );
	exit;
}

define( 'SHORTINIT', true );

$aw_root = __DIR__; // the real path (the plugin folder may be a link): climb to the WordPress root
while ( ! file_exists( $aw_root . '/wp-load.php' ) && dirname( $aw_root ) !== $aw_root ) {
	$aw_root = dirname( $aw_root );
}
if ( ! file_exists( $aw_root . '/wp-load.php' ) || ! file_exists( dirname( __DIR__ ) . '/kits-core/includes/visits.php' ) ) {
	http_response_code( 503 );
	exit;
}
require $aw_root . '/wp-load.php';
require dirname( __DIR__ ) . '/kits-core/includes/visits.php';

$aw_counted = kits_visit_count(
	(string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	(string) ( $_SERVER['HTTP_SEC_PURPOSE'] ?? '' ) . ' ' . (string) ( $_SERVER['HTTP_PURPOSE'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	(string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
);

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, private' );
header( 'X-LiteSpeed-Cache-Control: no-cache' );
header( 'X-Robots-Tag: noindex' );
echo $aw_counted ? '{"counted":true}' : '{"counted":false}';
