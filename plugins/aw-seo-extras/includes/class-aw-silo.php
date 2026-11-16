<?php
/**
 * Silo rules: pure PHP, no WordPress, so tests/SiloTest.php runs them on sample silos. silos.php feeds it the silos
 * and shows the result.
 *
 * A silo is one hub and its supporting items (an ordered list). Two modes:
 *   virtual  each supporting item links to the hub and to the other supporting items; the hub links to its supporting items.
 *   circle   each item links to the next one and back to the hub (the last one only back to the hub); the hub links to the first.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Silo rules. */
final class AW_Silo {

	/** The modes. */
	const MODES = array( 'virtual', 'circle' );

	/**
	 * The items a member of a silo links to, in order.
	 *
	 * @param string $mode    'virtual' or 'circle'.
	 * @param int    $hub     The hub's item ID.
	 * @param array  $items   The supporting items' IDs, in order.
	 * @param int    $current The item that links.
	 * @param int    $max     Most links (virtual mode: the siblings are the ones after this item first, so the links
	 *                        spread round the silo).
	 * @return int[]
	 */
	public static function links( string $mode, int $hub, array $items, int $current, int $max = 10 ): array {
		$items = array_values( array_unique( array_map( 'intval', $items ) ) );
		$max   = max( 1, $max );
		if ( 'circle' === $mode ) {
			if ( $current === $hub ) {
				return $items ? array( $items[0] ) : array();
			}
			$at = array_search( $current, $items, true );
			if ( false === $at ) {
				return array();
			}
			return isset( $items[ $at + 1 ] ) ? array( $items[ $at + 1 ], $hub ) : array( $hub );
		}
		if ( $current === $hub ) {
			return array_slice( $items, 0, $max );
		}
		$at = array_search( $current, $items, true );
		if ( false === $at ) {
			return array();
		}
		$rest = array_merge( array_slice( $items, $at + 1 ), array_slice( $items, 0, $at ) ); // the others, from the next one round
		return array_merge( array( $hub ), array_slice( $rest, 0, $max - 1 ) );
	}

	/**
	 * Whether an item may link to another item, by the silo rule: an item in one silo links to another silo's hub
	 * only, never to its supporting items. Items outside every silo are not restricted.
	 *
	 * @param array $map    Item ID => array( silo: ID, hub: bool ), for the items in a silo.
	 * @param int   $source Item that links.
	 * @param int   $target Item linked to.
	 */
	public static function may_link( array $map, int $source, int $target ): bool {
		if ( ! isset( $map[ $source ], $map[ $target ] ) || $map[ $source ]['silo'] === $map[ $target ]['silo'] ) {
			return true;
		}
		return (bool) $map[ $target ]['hub'];
	}

	/**
	 * How many folders deep an address is: its path segments after the site's own path (/library/guides/x/ is 3).
	 *
	 * @param string $url  Address.
	 * @param string $home Site address.
	 */
	public static function depth( string $url, string $home ): int {
		$path = (string) parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- runs without WordPress too.
		$base = rtrim( (string) parse_url( $home, PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- runs without WordPress too.
		if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}
		return count( array_filter( explode( '/', $path ), 'strlen' ) );
	}

	/**
	 * The checks on one silo's addresses.
	 *
	 * @param array $urls      Item ID => address, the hub and the supporting items.
	 * @param string $home     Site address.
	 * @param int   $min_items Fewest items (hub included): fewer is flagged.
	 * @param int   $max_depth Most folders deep: deeper is flagged.
	 * @return array few (int: the number of items when under the minimum, else 0), deep (item IDs too deep).
	 */
	public static function check( array $urls, string $home, int $min_items = 5, int $max_depth = 3 ): array {
		$deep = array();
		foreach ( $urls as $id => $url ) {
			if ( self::depth( (string) $url, $home ) > $max_depth ) {
				$deep[] = (int) $id;
			}
		}
		return array(
			'few'  => count( $urls ) < $min_items ? count( $urls ) : 0,
			'deep' => $deep,
		);
	}
}
