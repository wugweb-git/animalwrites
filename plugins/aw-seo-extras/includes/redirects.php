<?php
/**
 * Redirects. The one list is Rank Math → Redirections (owner, 10 Oct 2026): aw_redirect_add() adds to it, so forms,
 * merges and migrations keep writing to that one list. Author archives go to About by a Rank Math rule (^author).
 *
 * Rules Rank Math's list cannot hold (they rewrite the address rather than match it) stay here:
 * - /NEWS/ and /News/ → /news/ (one 301 to the lower-case path; the query string is kept).
 * - ?lang=, ?issuer= and ?court= on the Library → the same list without them.
 * - A missing address is a 404, never a 301 to whichever item WordPress guesses from the slug.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add one 301 to Rank Math's list: $pattern (a path, or a regex when $comparison is 'regex') to $to (a URL).
 * An exact path already in the list is left alone. Returns the redirection ID, or 0.
 *
 * @param string $pattern    Path without slashes at the ends, or a regex.
 * @param string $to         Destination URL (absolute, or a path on this site).
 * @param string $comparison exact | regex.
 * @return int
 */
function aw_rank_math_redirect( $pattern, $to, $comparison = 'exact' ) {
	if ( '' === $pattern || '' === $to || ! class_exists( '\RankMath\Redirections\Redirection' ) || ! \RankMath\Helper::is_module_active( 'redirections' ) ) {
		return 0;
	}
	if ( 'exact' === $comparison && class_exists( '\RankMath\Redirections\DB' ) ) {
		$have = \RankMath\Redirections\DB::match_redirections_source( $pattern );
		foreach ( (array) $have as $row ) {
			foreach ( (array) maybe_unserialize( $row['sources'] ?? '' ) as $src ) {
				if ( 'exact' === ( $src['comparison'] ?? '' ) && trim( (string) $src['pattern'], '/' ) === $pattern ) {
					return (int) $row['id'];
				}
			}
		}
	}
	$r = \RankMath\Redirections\Redirection::from(
		array(
			'sources'     => array( array( 'pattern' => $pattern, 'comparison' => $comparison ) ),
			'url_to'      => $to,
			'header_code' => 301,
			'status'      => 'active',
		)
	);
	return (int) $r->save();
}

/** Remember that $old_url now lives at post $target (an ID), or at a URL (a string such as '/volunteer/'). */
function aw_redirect_add( $old_url, $target ) {
	$path = trim( (string) wp_parse_url( $old_url, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$to = is_numeric( $target ) ? (string) get_permalink( (int) $target ) : home_url( (string) $target );
	if ( $to ) {
		aw_rank_math_redirect( rawurldecode( $path ), $to );
	}
}

// Upper-case paths go to the lower-case one. Files (a dot in the last part), the admin, REST and login are left alone.
add_action(
	'parse_request',
	function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$bare = preg_replace( '/%[0-9A-Fa-f]{2}/', '', $path ); // percent-encoded letters are not upper case
		if ( ! preg_match( '/[A-Z]/', $bare ) || preg_match( '#^/(wp-|xmlrpc)#i', $path ) || false !== strpos( basename( $path ), '.' ) ) {
			return;
		}
		$lower = preg_replace_callback( '/(%[0-9A-Fa-f]{2})|([^%]+)/', fn( $m ) => '' !== $m[1] ? $m[1] : strtolower( $m[2] ), $path );
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		wp_safe_redirect( home_url( $lower ) . ( '' !== $query ? '?' . $query : '' ), 301, 'Animal Writes' );
		exit;
	},
	0
);

// Language is gone from the Library (Phase 12 B4); Issuer and Court are labels now (8 Oct 2026).
add_action(
	'template_redirect',
	function () {
		if ( ( isset( $_GET['lang'] ) || isset( $_GET['issuer'] ) || isset( $_GET['court'] ) ) && is_post_type_archive( 'aw_legal' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( remove_query_arg( array( 'lang', 'issuer', 'court' ) ), 301 );
			exit;
		}
	},
	1
);

add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
