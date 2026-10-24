<?php
/**
 * Purge the page cache after a deploy (owner, 24 Oct 2026).
 *
 * aw-ship.sh purges LiteSpeed after it deploys, but Hostinger's auto-deploy (a git pull into aw_deploy) does not, so a
 * code change could stay invisible behind cached pages for up to the cache lifetime (7 days): PR #16 did, on 24 Oct.
 * The first uncached request after the deployed commit changes purges the whole cache once and remembers the commit
 * (option aw_deployed_head). Cached pages never reach PHP, so that request is made on purpose: after each deploy the
 * deploy-check workflow requests the home page with a one-off ?aw_deploy_nudge= query (a cache miss, a full page render
 * whose response carries LiteSpeed's purge header) every 30 s for 5 minutes. Those responses are never cached. Any other
 * uncached request (a cache miss, wp-admin) also counts.
 *
 * Only a web request may store the deployed commit, because only its response can carry LiteSpeed's purge header.
 * Skipped: any command-line PHP (WP-CLI, and Hostinger's cron running `php wp-cron.php`; 'cli' === PHP_SAPI), and a
 * request whose headers are already sent. Storing the commit there would mark it done without purging anything.
 * aw-ship.sh is no exception: its `wp litespeed-purge all` is LiteSpeed's own WP-CLI purge, not this hook, and it runs
 * only when someone ships with that script. Merged PRs deployed by the deploy check rely on this hook and the nudge. A
 * second purge after aw-ship.sh's is harmless.
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
		if ( 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI ) || headers_sent() ) {
			return; // no response to carry the purge header: leave the commit for a web request
		}
		if ( isset( $_GET['aw_deploy_nudge'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			do_action( 'litespeed_control_set_nocache', 'deploy nudge' ); // one-off URLs: keep them out of the cache
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
