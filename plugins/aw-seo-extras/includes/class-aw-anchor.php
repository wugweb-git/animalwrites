<?php
/**
 * The anchor type sorter and the anchor mix: pure PHP, no WordPress, so tests/AnchorTest.php runs it on sample anchors.
 * internal-links.php gives it each link's anchor and its target's keywords; link-audit.php counts the types per target.
 *
 * Types, tried in this order (the first that fits wins):
 *   image      the link holds an image and no text: the image's ALT text is the anchor
 *   naked      the anchor is an address (https://…, www.…, example.org/page)
 *   generic    one of an editable list (click here, read more, this page…), compared as normalised words
 *   exact      equals the target's main keyword (its first link keyword)
 *   branded    contains a brand name (a setting), as whole words
 *   long_tail  more words than the limit (a setting, 6)
 *   partial    contains a word of one of the target's keywords (3 letters or more)
 *   other      anything else
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-linker.php';

/** Anchor types. */
final class AW_Anchor {

	/** The types, in the order they are shown. */
	const TYPES = array( 'exact', 'partial', 'branded', 'generic', 'naked', 'image', 'long_tail', 'other' );

	/**
	 * The type of one anchor.
	 *
	 * @param string $anchor The anchor text (the ALT text for an image link).
	 * @param array  $ctx    keywords (the target's link keywords, normalised, the main one first), brand (brand names,
	 *                       normalised), generic (generic anchors, normalised), long_words (6), is_image (bool).
	 */
	public static function type( string $anchor, array $ctx = array() ): string {
		$ctx   += array(
			'keywords'   => array(),
			'brand'      => array(),
			'generic'    => array(),
			'long_words' => 6,
			'is_image'   => false,
		);
		$anchor = trim( $anchor );
		if ( $ctx['is_image'] ) {
			return 'image';
		}
		if ( preg_match( '~^(?:https?://|www\.)\S+$~i', $anchor ) || preg_match( '~^[a-z0-9][a-z0-9-]*(?:\.[a-z0-9-]+)+(?:/\S*)?$~i', $anchor ) ) {
			return 'naked';
		}
		$norm = AW_Linker::normalize( $anchor );
		if ( '' === $norm ) {
			return 'other';
		}
		if ( in_array( $norm, (array) $ctx['generic'], true ) ) {
			return 'generic';
		}
		$keywords = array_values( (array) $ctx['keywords'] );
		if ( $keywords && $norm === $keywords[0] ) {
			return 'exact';
		}
		$padded = ' ' . $norm . ' ';
		foreach ( (array) $ctx['brand'] as $brand ) {
			if ( '' !== $brand && false !== strpos( $padded, ' ' . $brand . ' ' ) ) {
				return 'branded';
			}
		}
		$words = explode( ' ', $norm );
		if ( count( $words ) > max( 1, (int) $ctx['long_words'] ) ) {
			return 'long_tail';
		}
		foreach ( $keywords as $kw ) {
			foreach ( explode( ' ', (string) $kw ) as $w ) {
				if ( mb_strlen( $w ) >= 3 && in_array( $w, $words, true ) ) {
					return 'partial';
				}
			}
		}
		return 'other';
	}

	/**
	 * A list of settings values (brand names, generic anchors) in matching form: normalised, no empties, no repeats.
	 *
	 * @param array $list Raw values.
	 */
	public static function normalize_list( array $list ): array {
		$out = array();
		foreach ( $list as $item ) {
			$n = AW_Linker::normalize( (string) $item );
			if ( '' !== $n ) {
				$out[ $n ] = $n;
			}
		}
		return array_values( $out );
	}

	/**
	 * One target's mix: the share of each type and the types over their limit.
	 *
	 * @param array $counts Type => links.
	 * @param array $opt    exact_max (percent, 20), type_max (percent, 60: any other type), min_links (5: no warning
	 *                      under this many links, as a share of two links says nothing).
	 * @return array total, pct (type => percent, one decimal), warn (list of types over their limit).
	 */
	public static function mix( array $counts, array $opt = array() ): array {
		$opt  += array(
			'exact_max' => 20,
			'type_max'  => 60,
			'min_links' => 5,
		);
		$total = 0;
		$pct   = array();
		foreach ( self::TYPES as $t ) {
			$total += max( 0, (int) ( $counts[ $t ] ?? 0 ) );
		}
		$warn = array();
		foreach ( self::TYPES as $t ) {
			$n         = max( 0, (int) ( $counts[ $t ] ?? 0 ) );
			$pct[ $t ] = $total ? round( 100 * $n / $total, 1 ) : 0.0;
			if ( $total >= (int) $opt['min_links'] && $pct[ $t ] > ( 'exact' === $t ? (float) $opt['exact_max'] : (float) $opt['type_max'] ) ) {
				$warn[] = $t;
			}
		}
		return array(
			'total' => $total,
			'pct'   => $pct,
			'warn'  => $warn,
		);
	}

	/**
	 * Whether one more link of a type would keep the target inside its limits (the auto-linker asks this before it
	 * links, and so rotates to another keyword).
	 *
	 * @param array  $counts Type => links the target has now.
	 * @param string $type   The type the new link would have.
	 * @param array  $opt    As mix().
	 */
	public static function room( array $counts, string $type, array $opt = array() ): bool {
		$counts[ $type ] = (int) ( $counts[ $type ] ?? 0 ) + 1;
		return ! in_array( $type, self::mix( $counts, $opt )['warn'], true );
	}
}
