<?php
/**
 * Sources: the registry of publisher hosts, each with a status (allow / neutral / block), and the gate that decides
 * whether an incoming item may enter. Reimplemented standalone under `sourceweb_`, storing to its own option.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Normalised host of a URL: lower case, no leading "www.". Pure; needs no WordPress.
 *
 * @param string $url URL.
 * @return string
 */
function sourceweb_norm_host( $url ) {
	$host = '';
	if ( function_exists( 'wp_parse_url' ) ) {
		$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
	} else {
		$parts = parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback only when WordPress is absent (tests)
		$host  = isset( $parts['host'] ) ? (string) $parts['host'] : '';
	}
	$host = strtolower( $host );
	return (string) preg_replace( '/^www\./', '', $host );
}

/**
 * The source registry (option `sourceweb_sources`): host => array{status,name,count,first,last}.
 *
 * @return array
 */
function sourceweb_sources() {
	$s = function_exists( 'get_option' ) ? get_option( 'sourceweb_sources' ) : false;
	return is_array( $s ) ? $s : array();
}

/**
 * Decide whether an item should be kept, with no side effects (pure; unit-testable). A `block` source is always
 * rejected; an exclude term always rejects; when the relevance filter is enabled, a non-`allow` source whose
 * text fails the verdict is rejected; otherwise it is kept.
 *
 * @param string     $url     Source URL.
 * @param string     $text    Title + summary.
 * @param array|null $lists   Keyword lists (defaults to the stored lists).
 * @param array|null $sources Source registry (defaults to the stored registry).
 * @return array{keep:bool,reason:string}
 */
function sourceweb_decide( $url, $text, $lists = null, $sources = null ) {
	$host    = sourceweb_norm_host( $url );
	$lists   = is_array( $lists ) ? array_merge( sourceweb_keyword_defaults(), $lists ) : sourceweb_keywords();
	$sources = null === $sources ? sourceweb_sources() : $sources;
	$status  = isset( $sources[ $host ]['status'] ) ? $sources[ $host ]['status'] : 'neutral';

	if ( 'block' === $status ) {
		return array(
			'keep'   => false,
			'reason' => 'blocked source: ' . $host,
		);
	}
	$hay = mb_strtolower( (string) $text );
	foreach ( (array) $lists['exclude'] as $term ) {
		if ( sourceweb_has_term( $hay, (string) $term ) ) {
			return array(
				'keep'   => false,
				'reason' => 'exclude term: ' . $term,
			);
		}
	}
	if ( ! empty( $lists['enabled'] ) && 'allow' !== $status ) {
		$verdict = sourceweb_relevance_verdict( $text, $lists );
		if ( ! $verdict[0] ) {
			return array(
				'keep'   => false,
				'reason' => $verdict[1],
			);
		}
	}
	return array(
		'keep'   => true,
		'reason' => 'allow' === $status ? 'allowed source' : 'passed',
	);
}

/**
 * Record a host (count, dates, name) and return the gate decision as a short code. Wraps sourceweb_decide().
 *
 * @param string $url  Source URL.
 * @param string $name Publisher name.
 * @param string $text Title + summary.
 * @return string '' to accept, or 'blocked' / 'filtered'.
 */
function sourceweb_gate( $url, $name, $text ) {
	$host = sourceweb_norm_host( $url );
	if ( '' === $host ) {
		return '';
	}
	$today         = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	$pubs          = sourceweb_sources();
	$rec           = $pubs[ $host ] ?? array(
		'status' => 'neutral',
		'name'   => '',
		'count'  => 0,
		'first'  => $today,
		'last'   => '',
	);
	$rec['name']   = $rec['name'] ? $rec['name'] : ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $name ) : trim( (string) $name ) );
	$rec['last']   = $today;
	$rec['count']  = (int) $rec['count'] + 1;
	$pubs[ $host ] = $rec;
	if ( function_exists( 'update_option' ) ) {
		update_option( 'sourceweb_sources', $pubs, false );
	}

	$decision = sourceweb_decide( $url, $text, null, $pubs );
	if ( $decision['keep'] ) {
		return '';
	}
	return 0 === strpos( $decision['reason'], 'blocked source' ) ? 'blocked' : 'filtered';
}
