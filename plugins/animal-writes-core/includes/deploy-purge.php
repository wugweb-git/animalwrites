<?php
/**
 * Purge the page cache after a deploy (owner, 24 Oct 2026).
 *
 * aw-ship.sh purges LiteSpeed after it deploys, but Hostinger's auto-deploy (a git pull into aw_deploy) does not, so a
 * code change could stay invisible behind cached pages for up to the cache lifetime (7 days): PR #16 did, on 24 Oct.
 * The first uncached request after the deployed commit changes (a cache miss, wp-admin, WP-Cron or the LiteSpeed
 * crawler) purges the whole cache once and remembers the commit (option aw_deployed_head).
 *
 * WP-CLI requests are skipped: a purge from the CLI does not reach the web server through this hook, and aw-ship.sh
 * purges by itself. A purge after aw-ship.sh's own is harmless.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The commit checked out in the deploy repository (the one this plugin is served from), '' if unknown. */
function aw_deployed_head() {
	$git  = dirname( (string) realpath( AW_CORE_DIR ), 2 ) . '/.git';
	$head = is_readable( $git . '/HEAD' ) ? trim( (string) file_get_contents( $git . '/HEAD' ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( 0 !== strpos( $head, 'ref: ' ) ) {
		return preg_match( '/^[0-9a-f]{40}$/', $head ) ? $head : ''; // detached: the commit itself
	}
	$ref = substr( $head, 5 );
	if ( is_readable( $git . '/' . $ref ) ) {
		return trim( (string) file_get_contents( $git . '/' . $ref ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$packed = is_readable( $git . '/packed-refs' ) ? (string) file_get_contents( $git . '/packed-refs' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return preg_match( '/^([0-9a-f]{40}) ' . preg_quote( $ref, '/' ) . '$/m', $packed, $m ) ? $m[1] : '';
}

add_action(
	'init',
	function () {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		$head = aw_deployed_head();
		if ( '' === $head || get_option( 'aw_deployed_head' ) === $head ) {
			return;
		}
		update_option( 'aw_deployed_head', $head, true );
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache's own hook; nothing happens if it is not active
	},
	99
);
