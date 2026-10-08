<?php
/**
 * Permanent redirects for items that moved section or were merged (restructure Phase 3).
 *
 * Option aw_redirects maps an old path (no slashes at the ends) to the post that now holds it. A request that would
 * 404 at an old path gets one 301 to that post's current URL, so a move never costs a redirect chain.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Remember that $old_url now lives at post $target (an ID), or at a URL (a string such as '/#volunteer'). */
function aw_redirect_add( $old_url, $target ) {
	$path = trim( (string) wp_parse_url( $old_url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$map          = (array) get_option( 'aw_redirects', array() );
	$map[ $path ] = is_numeric( $target ) ? (int) $target : (string) $target;
	update_option( 'aw_redirects', $map, false );
}

/** Whole sections that moved (7 Oct 2026): old prefix => new prefix. Items keep their slug. */
const AW_MOVED_SECTIONS = array( 'knowledge' => 'resources', 'legal-watch' => 'library', 'research' => 'resources' );

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$map  = (array) get_option( 'aw_redirects', array() );
		$to   = $map[ $path ] ?? $map[ rawurldecode( $path ) ] ?? null;
		if ( is_int( $to ) && 'publish' === get_post_status( $to ) ) {
			wp_safe_redirect( get_permalink( $to ), 301, 'Animal Writes' );
			exit;
		}
		if ( is_string( $to ) && '' !== $to ) {
			wp_safe_redirect( home_url( $to ), 301, 'Animal Writes' );
			exit;
		}
		// A moved section: same path under its new name (one hop; the item URLs above are checked first).
		$first = strtok( $path, '/' );
		if ( isset( AW_MOVED_SECTIONS[ $first ] ) ) {
			$rest = substr( $path, strlen( $first ) );
			$dest = 'research' === $first && '' === trim( $rest, '/' ) ? '/resources/' : '/' . AW_MOVED_SECTIONS[ $first ] . $rest . ( '' === $rest ? '' : '' ) . '/';
			wp_safe_redirect( home_url( preg_replace( '#/+#', '/', $dest ) ), 301, 'Animal Writes' );
			exit;
		}
	},
	0 // before WordPress guesses a redirect from the slug
);


// Language is gone from the Library (Phase 12 B4): old ?lang= filter links go to the same list without it.
add_action(
	'template_redirect',
	function () {
		if ( isset( $_GET['lang'] ) && is_post_type_archive( 'aw_legal' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( remove_query_arg( 'lang' ), 301 );
			exit;
		}
	},
	1
);
