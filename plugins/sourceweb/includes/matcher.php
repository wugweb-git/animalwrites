<?php
/**
 * Keyword matcher (pure PHP, no WordPress). A term matches text as follows: Latin terms match whole words with an
 * optional plural (s/es); a trailing "*" matches the start of a word; terms in other scripts match as substrings;
 * case and repeated spaces do not matter.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Does the haystack contain the term, using the prefix/plural/word rules above?
 *
 * @param string $hay  Text to search (any case).
 * @param string $term Keyword; a trailing "*" makes it a prefix match.
 * @return bool
 */
function sourceweb_match_term( $hay, $term ) {
	$t = trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $term ) ) );
	if ( '' === $t ) {
		return false;
	}
	$prefix = str_ends_with( $t, '*' );
	if ( $prefix ) {
		$t = rtrim( rtrim( $t, '*' ) );
		if ( '' === $t ) {
			return false;
		}
	}
	$hay = mb_strtolower( (string) $hay );
	if ( preg_match( '/[^\x00-\x7F]/', $t ) ) {
		return false !== mb_strpos( $hay, $t ); // Other scripts have no word boundaries.
	}
	$body = implode( '\s+', array_map( fn( $p ) => preg_quote( $p, '/' ), explode( ' ', $t ) ) );
	return (bool) preg_match( '/(?<![a-z0-9])' . $body . ( $prefix ? '' : '(?:s|es)?(?![a-z0-9])' ) . '/u', $hay );
}

/**
 * Convenience wrapper used by the relevance verdict: whole-word for short Latin terms, substring for other scripts,
 * and the shared matcher for prefix terms.
 *
 * @param string $hay  Lowercased text.
 * @param string $term Term.
 * @return bool
 */
function sourceweb_has_term( $hay, $term ) {
	$t = mb_strtolower( trim( (string) $term ) );
	if ( '' === $t ) {
		return false;
	}
	if ( str_ends_with( $t, '*' ) ) {
		return sourceweb_match_term( $hay, $t );
	}
	if ( preg_match( "/^[a-z0-9 \\-'’]+$/u", $t ) ) {
		return (bool) preg_match( '/(?<![a-z0-9])' . preg_quote( $t, '/' ) . '(?:s|es)?(?![a-z0-9])/u', mb_strtolower( (string) $hay ) );
	}
	return false !== mb_strpos( mb_strtolower( (string) $hay ), $t );
}
