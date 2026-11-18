<?php
/**
 * Feed parsing: turn a fetched RSS 2.0 or Atom document into a normalised list of items
 * ({ title, url, summary }). Pure PHP (SimpleXML, a core extension) — no WordPress, no network — so it is
 * unit-testable against fixture XML.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Parse a feed document into items.
 *
 * @param string $xml   The raw feed body.
 * @param int    $limit Maximum items to return (default 50).
 * @return array<int,array{title:string,url:string,summary:string}>
 */
function sourceweb_parse_feed( $xml, $limit = 50 ) {
	$xml = trim( (string) $xml );
	if ( '' === $xml ) {
		return array();
	}

	$previous = libxml_use_internal_errors( true );
	$sx       = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( false === $sx ) {
		return array();
	}

	$items = array();

	if ( isset( $sx->channel->item ) ) {
		// RSS 2.0 / RDF-style <item> under <channel>.
		foreach ( $sx->channel->item as $node ) {
			$items[] = array(
				'title'   => sourceweb_parse_text( $node->title ),
				'url'     => sourceweb_parse_text( $node->link ),
				'summary' => sourceweb_parse_text( isset( $node->description ) ? $node->description : '' ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
	} elseif ( isset( $sx->entry ) ) {
		// Atom <entry> under <feed>.
		foreach ( $sx->entry as $node ) {
			$summary = isset( $node->summary ) ? $node->summary : ( isset( $node->content ) ? $node->content : '' );
			$items[] = array(
				'title'   => sourceweb_parse_text( $node->title ),
				'url'     => sourceweb_parse_atom_link( $node ),
				'summary' => sourceweb_parse_text( $summary ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
	}

	return $items;
}

/**
 * The text of a SimpleXML node, trimmed (empty string when the node is absent).
 *
 * @param mixed $node A SimpleXMLElement or null-ish.
 * @return string
 */
function sourceweb_parse_text( $node ) {
	return trim( (string) $node );
}

/**
 * The best link URL for an Atom entry: the rel="alternate" link, else the first link with an href, else the
 * text of <link>.
 *
 * @param SimpleXMLElement $entry Atom entry.
 * @return string
 */
function sourceweb_parse_atom_link( $entry ) {
	if ( ! isset( $entry->link ) ) {
		return '';
	}
	$fallback = '';
	foreach ( $entry->link as $link ) {
		$href = isset( $link['href'] ) ? trim( (string) $link['href'] ) : '';
		if ( '' === $href ) {
			continue;
		}
		$rel = isset( $link['rel'] ) ? (string) $link['rel'] : 'alternate';
		if ( 'alternate' === $rel ) {
			return $href;
		}
		if ( '' === $fallback ) {
			$fallback = $href;
		}
	}
	return '' !== $fallback ? $fallback : trim( (string) $entry->link );
}
