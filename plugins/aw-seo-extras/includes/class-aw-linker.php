<?php
/**
 * The auto-linker's core: HTML in, HTML with links out. Pure PHP (DOMDocument, mbstring, PCRE), no WordPress, so
 * tests/linker-test.php runs it on sample HTML without a site. internal-links.php feeds it the keyword index and the
 * item's settings.
 *
 * How it matches: every keyword is stored normalised (AW_Linker::normalize(): its words, lower case, one space between).
 * A text node is cut into words with their byte offsets; at each word the longest run of words that is a keyword wins
 * (longest first), and the words of a run may be separated by white space only. So matching is whole words only,
 * case-insensitive and Unicode-safe (letters, combining marks and digits of any script, as in Hindi).
 *
 * Never linked inside: h1-h6, a, code, pre, script, style, button, figcaption, and also textarea, svg, noscript,
 * template, select and option (their text is not running prose).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM's own property names.

/** HTML auto-linker. */
final class AW_Linker {

	/** Elements whose text is never linked. */
	const SKIP = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'code', 'pre', 'script', 'style', 'button', 'figcaption', 'textarea', 'svg', 'noscript', 'template', 'select', 'option' );

	/** One word: letters, combining marks and digits, joined by an apostrophe or hyphen inside the word. */
	const WORD = '/[\p{L}\p{M}\p{N}]+(?:[\'’\-][\p{L}\p{M}\p{N}]+)*/u';

	/** The class every link it adds carries (the audit tells auto links from hand-made ones by it). */
	const CLASS_NAME = 'aw-auto-link';

	/**
	 * A keyword or a run of text in its matching form: its words, lower case, one space between.
	 *
	 * @param string $text Text.
	 */
	public static function normalize( string $text ): string {
		if ( ! preg_match_all( self::WORD, $text, $m ) ) {
			return '';
		}
		return mb_strtolower( implode( ' ', $m[0] ), 'UTF-8' );
	}

	/**
	 * A link address in the form used to compare two addresses: no scheme, no www., no query or fragment, no trailing
	 * slash, lower case host. A relative address is read against $home.
	 *
	 * @param string $url  Address.
	 * @param string $home Site address, for relative addresses.
	 */
	public static function url_key( string $url, string $home = '' ): string {
		$url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $url || '#' === $url[0] ) {
			return '';
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$url = rtrim( $home, '/' ) . $url;
		}
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- runs without WordPress too.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		$host = preg_replace( '/^www\./', '', strtolower( (string) $parts['host'] ) );
		return $host . rtrim( (string) ( $parts['path'] ?? '' ), '/' );
	}

	/**
	 * Add links to an item's HTML.
	 *
	 * @param string   $html     The item's content HTML.
	 * @param array    $index    Keywords: normalised keyword => list of array( target_id, priority, keyword ), best
	 *                           target first (internal-links.php sorts it). Each keyword must already be normalised.
	 * @param callable $url_for  fn( int $target_id ): string, the target's address ('' = do not link to it).
	 * @param array    $opt      max_links (5), max_per_target (1), first_only (true), self_id (0), no_self (true),
	 *                           skip_linked (true), exclude (target ids never linked), home (site address).
	 * @return array html (unchanged, byte for byte, when no link was added or the parsed page would not match the input), links (each: target_id, url, anchor,
	 *               keyword, origin 'auto'), manual (each: url, anchor, rel: the links that were already there).
	 */
	public static function link( string $html, array $index, callable $url_for, array $opt = array() ): array {
		$opt += array(
			'max_links'      => 5,
			'max_per_target' => 1,
			'first_only'     => true,
			'self_id'        => 0,
			'no_self'        => true,
			'skip_linked'    => true,
			'exclude'        => array(),
			'home'           => '',
		);
		$out  = array(
			'html'   => $html,
			'links'  => array(),
			'manual' => array(),
		);
		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return $out;
		}

		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		// The encoding hint makes libxml read UTF-8; the wrapper keeps the item's own top-level nodes together.
		$ok = $doc->loadHTML( '<?xml encoding="UTF-8"><div id="aw-linker-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$root = $ok ? $doc->getElementById( 'aw-linker-root' ) : null;
		if ( ! $root ) {
			return $out;
		}

		// The links already there (hand-made): kept for the links table, and their targets are not linked again.
		$linked = array();
		foreach ( $root->getElementsByTagName( 'a' ) as $a ) {
			$href = (string) $a->getAttribute( 'href' );
			if ( '' === $href ) {
				continue;
			}
			$out['manual'][] = array(
				'url'    => $href,
				'anchor' => trim( preg_replace( '/\s+/u', ' ', (string) $a->textContent ) ),
				'rel'    => strtolower( trim( (string) $a->getAttribute( 'rel' ) ) ),
				'alt'    => self::image_alt( $a ),
			);
			$key             = self::url_key( $href, (string) $opt['home'] );
			if ( '' !== $key ) {
				$linked[ $key ] = true;
			}
		}

		$max_links = max( 0, (int) $opt['max_links'] );
		if ( ! $index || 0 === $max_links ) {
			return $out;
		}
		// The page is written back from the parsed tree only when that tree is the same page: libxml reads HTML 4, so
		// markup it does not understand (camelCase SVG attributes, a div inside a p) comes out changed. Such an item
		// is left as it is; the tags and the attribute names of the tree, untouched, must equal those of the input.
		$base = '';
		foreach ( $root->childNodes as $child ) {
			$base .= $doc->saveHTML( $child );
		}
		if ( self::signature( $html ) !== self::signature( $base ) ) {
			return $out;
		}
		$longest = 1;
		foreach ( $index as $kw => $unused ) {
			$longest = max( $longest, substr_count( (string) $kw, ' ' ) + 1 );
		}

		$exclude = array_flip( array_map( 'intval', (array) $opt['exclude'] ) );
		$per_t   = array(); // target id => links added
		$seen_kw = array(); // keyword => true once its first linkable appearance was considered
		$urls    = array(); // target id => address ('' = never)
		$added   = 0;
		$resolve = function ( int $tid ) use ( &$urls, $url_for, $opt, $linked ): string {
			if ( ! array_key_exists( $tid, $urls ) ) {
				$url = (string) $url_for( $tid );
				if ( '' !== $url && $opt['skip_linked'] && isset( $linked[ self::url_key( $url, (string) $opt['home'] ) ] ) ) {
					$url = ''; // the item already links there by hand
				}
				$urls[ $tid ] = $url;
			}
			return $urls[ $tid ];
		};
		$choose  = function ( string $kw ) use ( $index, $opt, $exclude, &$per_t, $resolve ) {
			foreach ( $index[ $kw ] as $cand ) {
				$tid = (int) $cand[0];
				if ( isset( $exclude[ $tid ] ) || ( $opt['no_self'] && $tid === (int) $opt['self_id'] ) ) {
					continue;
				}
				if ( ( $per_t[ $tid ] ?? 0 ) >= max( 1, (int) $opt['max_per_target'] ) ) {
					continue;
				}
				$url = $resolve( $tid );
				if ( '' !== $url ) {
					return array( $tid, $url );
				}
			}
			return null;
		};

		foreach ( self::text_nodes( $root ) as $node ) {
			if ( $added >= $max_links ) {
				break;
			}
			$text = (string) $node->nodeValue;
			if ( ! preg_match_all( self::WORD, $text, $m, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$words = $m[0];
			$lower = array_map( fn( $w ) => mb_strtolower( $w[0], 'UTF-8' ), $words );
			$cuts  = array(); // array( start byte, end byte, target id, url, keyword ), in text order
			$n     = count( $words );
			$room  = $max_links - $added; // links this node may still take
			for ( $i = 0; $i < $n && $room > 0; $i++ ) {
				$phrase = '';
				$hit    = null;
				// Build the runs from this word, then try them longest first.
				$runs = array();
				for ( $k = 0; $k < $longest && $i + $k < $n; $k++ ) {
					if ( $k > 0 ) {
						$gap_from = $words[ $i + $k - 1 ][1] + strlen( $words[ $i + $k - 1 ][0] );
						$gap      = substr( $text, $gap_from, $words[ $i + $k ][1] - $gap_from );
						if ( '' === $gap || preg_match( '/[^\s\x{00A0}]/u', $gap ) ) {
							break; // words of a keyword are separated by white space only
						}
					}
					$phrase    .= ( $k > 0 ? ' ' : '' ) . $lower[ $i + $k ];
					$runs[ $k ] = $phrase;
				}
				$last = count( $runs ) - 1;
				for ( $k = $last; $k >= 0; $k-- ) {
					$kw = $runs[ $k ];
					if ( ! isset( $index[ $kw ] ) ) {
						continue;
					}
					if ( $opt['first_only'] && isset( $seen_kw[ $kw ] ) ) {
						continue; // only its first appearance may be linked
					}
					$seen_kw[ $kw ] = true;
					$pick           = $choose( $kw );
					if ( $pick ) {
						$hit = array( $words[ $i ][1], $words[ $i + $k ][1] + strlen( $words[ $i + $k ][0] ), $pick[0], $pick[1], $kw );
						break;
					}
				}
				if ( $hit ) {
					$per_t[ $hit[2] ] = ( $per_t[ $hit[2] ] ?? 0 ) + 1;
					$cuts[]           = $hit;
					--$room;
					$i += $k; // continue after the linked words
				}
			}
			if ( ! $cuts ) {
				continue;
			}
			// Replace the text node by text, link, text, … .
			$frag = $doc->createDocumentFragment();
			$pos  = 0;
			foreach ( $cuts as $c ) {
				if ( $c[0] > $pos ) {
					$frag->appendChild( $doc->createTextNode( substr( $text, $pos, $c[0] - $pos ) ) );
				}
				$anchor = substr( $text, $c[0], $c[1] - $c[0] );
				$a      = $doc->createElement( 'a' );
				$a->setAttribute( 'href', $c[3] );
				$a->setAttribute( 'class', self::CLASS_NAME );
				$a->appendChild( $doc->createTextNode( $anchor ) );
				$frag->appendChild( $a );
				$out['links'][] = array(
					'target_id' => $c[2],
					'url'       => $c[3],
					'anchor'    => $anchor,
					'keyword'   => $c[4],
					'origin'    => 'auto',
				);
				$pos            = $c[1];
				++$added;
			}
			if ( $pos < strlen( $text ) ) {
				$frag->appendChild( $doc->createTextNode( substr( $text, $pos ) ) );
			}
			$node->parentNode->replaceChild( $frag, $node );
		}

		if ( $out['links'] ) {
			$html = '';
			foreach ( $root->childNodes as $child ) {
				$html .= $doc->saveHTML( $child );
			}
			$out['html'] = $html;
		}
		return $out;
	}

	/**
	 * The tags (lower case) and attribute names (as written) of some HTML, sorted, and its end tags in order: the same
	 * page parsed and written back has the same signature.
	 *
	 * @param string $html HTML.
	 */
	private static function signature( string $html ): string {
		$tags  = array();
		$attrs = array();
		if ( preg_match_all( '/<([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $t ) {
				$tags[] = strtolower( $t[1] );
				if ( preg_match_all( '/\s([^\s"\'<>\/=]+)(?=[\s=\/>]|$)/', $t[2], $am ) ) {
					array_push( $attrs, ...$am[1] );
				}
			}
		}
		sort( $tags );
		sort( $attrs );
		// The end tags in order: a div inside a p moves the </p>; an item that leaves end tags out is left alone too.
		$ends = preg_match_all( '/<\/([a-zA-Z][a-zA-Z0-9:-]*)\s*>/', $html, $em ) ? array_map( 'strtolower', $em[1] ) : array();
		return implode( ',', $tags ) . '|' . implode( ',', $attrs ) . '|' . implode( ',', $ends );
	}

	/**
	 * The text nodes that may be linked, in document order.
	 *
	 * @param DOMNode $root Root element.
	 * @return DOMText[]
	 */
	private static function text_nodes( DOMNode $root ): array {
		$out   = array();
		$stack = array( $root );
		while ( $stack ) {
			$node = array_pop( $stack );
			if ( $node instanceof DOMText ) {
				if ( '' !== trim( (string) $node->nodeValue ) ) {
					$out[] = $node;
				}
				continue;
			}
			if ( ! $node instanceof DOMElement || ( $node !== $root && in_array( strtolower( $node->nodeName ), self::SKIP, true ) ) ) {
				continue;
			}
			for ( $c = $node->lastChild; $c; $c = $c->previousSibling ) {
				$stack[] = $c; // last child first, so the first child is popped first
			}
		}
		return $out;
	}

	/**
	 * The ALT text of the first image inside a link ('' when it holds none).
	 *
	 * @param DOMElement $a Link.
	 */
	private static function image_alt( DOMElement $a ): string {
		$img = $a->getElementsByTagName( 'img' )->item( 0 );
		return $img instanceof DOMElement ? trim( (string) $img->getAttribute( 'alt' ) ) : '';
	}
}
