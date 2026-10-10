<?php
/**
 * Permanent redirects for items that moved section or were merged (restructure Phase 3).
 *
 * The one list is Rank Math → Redirections (owner, 10 Oct 2026). aw_redirect_add() adds to it, so forms, merges and
 * migrations keep writing to that one list. The old option aw_redirects (old path => post ID or URL) was imported into
 * it on 10 Oct 2026 and is kept unchanged as a record; it is only read when Rank Math is not active (a fresh install).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Whether Rank Math's Redirections module holds the redirects. */
function aw_redirects_in_rank_math() {
	return function_exists( 'kits_rank_math_module' ) && kits_rank_math_module( 'redirections' ) && class_exists( '\RankMath\Redirections\Redirection' );
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
	if ( ! aw_redirects_in_rank_math() || '' === $pattern || '' === $to ) {
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
	if ( aw_redirects_in_rank_math() ) {
		$to = is_numeric( $target ) ? (string) get_permalink( (int) $target ) : home_url( (string) $target );
		if ( $to ) {
			aw_rank_math_redirect( rawurldecode( $path ), $to );
		}
		return;
	}
	$map          = (array) get_option( 'aw_redirects', array() );
	$map[ $path ] = is_numeric( $target ) ? (int) $target : (string) $target;
	update_option( 'aw_redirects', $map, false );
}

/** Whole sections that moved (7 Oct 2026): old prefix => new prefix. Items keep their slug. In Rank Math as regex rules. */
const AW_MOVED_SECTIONS = array( 'knowledge' => 'resources', 'legal-watch' => 'library', 'research' => 'resources' );

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() || aw_redirects_in_rank_math() ) {
			return; // Rank Math answers these (its list has the items and the moved sections)
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
		// Issuer and Court are labels now (8 Oct 2026): ?issuer= and ?court= links go to the list without them.
		if ( ( isset( $_GET['lang'] ) || isset( $_GET['issuer'] ) || isset( $_GET['court'] ) ) && is_post_type_archive( 'aw_legal' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( remove_query_arg( array( 'lang', 'issuer', 'court' ) ), 301 );
			exit;
		}
	},
	1
);

// Author archives (/author/{name}/, ?author=N) are not used: one 301 to the About page (owner, 10 Oct 2026).
// Tag and date archives are left alone; categories are Resource types, with no pages (content-types.php).
add_action(
	'template_redirect',
	function () {
		$path = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_author() || 0 === strpos( $path . '/', 'author/' ) || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/about/' ), 301, 'Animal Writes' );
			exit;
		}
	},
	0
);
