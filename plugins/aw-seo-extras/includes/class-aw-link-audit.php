<?php
/**
 * The link audit's core: the items and the links between them in, the findings out. Pure PHP, no WordPress, so
 * tests/LinkAuditTest.php runs it on a small sample site. link-audit.php reads the site, feeds this class and stores
 * what it returns.
 *
 * What it works out for every item:
 * - links in (from other items, plus one when the item is linked from the home page or a menu) and links out;
 * - clicks from the home page: a breadth-first search that starts at the items the home page and the menus link to
 *   (one click) and follows the links between items;
 * - orphan (nothing links in), too deep (more clicks than the limit, or no way to reach it), too many links out;
 * - links out that are broken, go through a redirect, are nofollow, point to an item whose canonical points elsewhere,
 *   point to an address on the "do not link" list (thank-you and download pages), or leave a silo for another silo's
 *   supporting item.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-aw-silo.php';

/** Link audit. */
final class AW_Link_Audit {

	/** The findings, in the order they are listed. */
	const FLAGS = array( 'orphan', 'deep', 'too_many', 'broken', 'redirect', 'nofollow', 'canonical', 'avoided', 'silo' );

	/** Most addresses kept as the detail of one finding. */
	const DETAIL_MAX = 5;

	/**
	 * Audit a set of items.
	 *
	 * @param array $items Item ID => array( url: its address, canonical: the address its canonical points to, '' when
	 *                     it points to itself ).
	 * @param array $edges Links between pages: each array( source: item ID, target: item ID or 0 when the address is
	 *                     no item, url: the address as written, rel: its rel attribute, status: '' (not checked),
	 *                     'ok', 'broken' or 'redirect' ).
	 * @param array $roots Item IDs the home page and the menus link to (one click from the home page).
	 * @param array $opt   max_depth (3), max_links (100), avoid (address fragments, lower case), silo (item ID => array( silo,
	 *                     hub ) for the items in a silo: a link into another silo's supporting item is flagged).
	 * @return array Item ID => array( in, out, depth (int, or null when no click path reaches it), flags (list), detail
	 *               (flag => list of addresses) ).
	 */
	public static function analyze( array $items, array $edges, array $roots, array $opt = array() ): array {
		$opt  += array(
			'max_depth' => 3,
			'max_links' => 100,
			'avoid'     => array(),
			'silo'      => array(),
		);
		$avoid = array_values( array_filter( array_map( 'strtolower', array_map( 'strval', (array) $opt['avoid'] ) ), 'strlen' ) );

		$in      = array();
		$out     = array();
		$graph   = array();
		$res     = array();
		$detail  = array();
		$is_root = array_flip( array_map( 'intval', $roots ) );
		foreach ( array_keys( $items ) as $id ) {
			$id         = (int) $id;
			$in[ $id ]  = isset( $is_root[ $id ] ) ? 1 : 0;
			$out[ $id ] = 0;
			$res[ $id ] = array();
		}

		foreach ( $edges as $e ) {
			$src = (int) ( $e['source'] ?? 0 );
			$tgt = (int) ( $e['target'] ?? 0 );
			if ( ! isset( $items[ $src ] ) ) {
				continue;
			}
			++$out[ $src ];
			$url    = (string) ( $e['url'] ?? '' );
			$rel    = strtolower( (string) ( $e['rel'] ?? '' ) );
			$status = (string) ( $e['status'] ?? '' );
			if ( $tgt > 0 && $tgt !== $src && isset( $items[ $tgt ] ) ) {
				++$in[ $tgt ];
				$graph[ $src ][ $tgt ] = true;
			}
			if ( 'broken' === $status ) {
				self::note( $res, $detail, $src, 'broken', $url );
			}
			if ( 'redirect' === $status ) {
				self::note( $res, $detail, $src, 'redirect', $url );
			}
			if ( in_array( 'nofollow', (array) preg_split( '/\s+/', $rel ), true ) ) {
				self::note( $res, $detail, $src, 'nofollow', $url );
			}
			if ( $tgt > 0 && '' !== (string) ( $items[ $tgt ]['canonical'] ?? '' ) ) {
				self::note( $res, $detail, $src, 'canonical', $url );
			}
			if ( $tgt > 0 && $opt['silo'] && ! AW_Silo::may_link( (array) $opt['silo'], $src, $tgt ) ) {
				self::note( $res, $detail, $src, 'silo', $url );
			}
			$low = strtolower( $url );
			foreach ( $avoid as $bit ) {
				if ( false !== strpos( $low, $bit ) ) {
					self::note( $res, $detail, $src, 'avoided', $url );
					break;
				}
			}
		}

		// Clicks from the home page: breadth first, the first level being the items the home page and the menus link to.
		$depth = array();
		$queue = array();
		foreach ( array_keys( $is_root ) as $id ) {
			if ( isset( $items[ $id ] ) ) {
				$depth[ $id ] = 1;
				$queue[]      = $id;
			}
		}
		$at = 0;
		while ( isset( $queue[ $at ] ) ) { // the queue grows while it is read
			$id = $queue[ $at++ ];
			foreach ( array_keys( $graph[ $id ] ?? array() ) as $next ) {
				if ( ! isset( $depth[ $next ] ) ) {
					$depth[ $next ] = $depth[ $id ] + 1;
					$queue[]        = $next;
				}
			}
		}

		$final = array();
		foreach ( array_keys( $items ) as $id ) {
			$id     = (int) $id;
			$orphan = 0 === $in[ $id ];
			$d      = $depth[ $id ] ?? null;
			if ( $orphan ) {
				self::note( $res, $detail, $id, 'orphan', '' );
			} elseif ( null === $d || $d > (int) $opt['max_depth'] ) {
				self::note( $res, $detail, $id, 'deep', '' );
			}
			if ( $out[ $id ] > (int) $opt['max_links'] ) {
				self::note( $res, $detail, $id, 'too_many', '' );
			}
			$flags = array_values( array_intersect( self::FLAGS, array_keys( $res[ $id ] ) ) );
			$det   = array();
			foreach ( $flags as $f ) {
				if ( ! empty( $detail[ $id ][ $f ] ) ) {
					$det[ $f ] = $detail[ $id ][ $f ];
				}
			}
			$final[ $id ] = array(
				'in'     => $in[ $id ],
				'out'    => $out[ $id ],
				'depth'  => $d,
				'flags'  => $flags,
				'detail' => $det,
			);
		}
		return $final;
	}

	/**
	 * Count the findings and the items they are on.
	 *
	 * @param array $result analyze() result.
	 * @return array items (audited), flagged (items with at least one finding), by (flag => items).
	 */
	public static function summary( array $result ): array {
		$by      = array_fill_keys( self::FLAGS, 0 );
		$flagged = 0;
		foreach ( $result as $r ) {
			if ( $r['flags'] ) {
				++$flagged;
			}
			foreach ( $r['flags'] as $f ) {
				++$by[ $f ];
			}
		}
		return array(
			'items'   => count( $result ),
			'flagged' => $flagged,
			'by'      => $by,
		);
	}

	/**
	 * Record a finding on an item, with the address it is about (kept up to DETAIL_MAX).
	 *
	 * @param array  $res    Findings: item => flag => true.
	 * @param array  $detail Addresses: item => flag => list.
	 * @param int    $id     Item.
	 * @param string $flag   Finding.
	 * @param string $url    Address ('' when it is about the item).
	 */
	private static function note( array &$res, array &$detail, int $id, string $flag, string $url ): void {
		$res[ $id ][ $flag ] = true;
		if ( '' !== $url && count( $detail[ $id ][ $flag ] ?? array() ) < self::DETAIL_MAX && ! in_array( $url, $detail[ $id ][ $flag ] ?? array(), true ) ) {
			$detail[ $id ][ $flag ][] = $url;
		}
	}
}
