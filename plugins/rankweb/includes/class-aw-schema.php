<?php
/**
 * Custom JSON-LD: parsing, validation and small node builders. Pure PHP, no WordPress, so tests/SchemaTest.php runs it.
 * schema-custom.php stores an editor's JSON-LD and the speakable, about and mentions fields, and this class turns them
 * into nodes for Rank Math's graph; it also reads the JSON-LD out of a fetched page for "import from a URL".
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Custom JSON-LD helpers. */
final class AW_Schema {

	/** The most nodes kept from one source (a box or an import), so a bad paste cannot fill the graph. */
	const MAX_NODES = 25;

	/**
	 * Decode a block of JSON-LD an editor typed. Accepts a single object, a list of objects, or a { "@graph": [ … ] }
	 * wrapper. Returns the nodes (each an object with an @type) and an error message ('' when it is good; an empty
	 * string input is good and gives no nodes).
	 *
	 * @param string $raw The JSON-LD text.
	 * @return array{nodes: array<int,array>, error: string}
	 */
	public static function parse( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array(
				'nodes' => array(),
				'error' => '',
			);
		}
		$decoded = json_decode( $raw, true );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'nodes' => array(),
				'error' => json_last_error_msg(),
			);
		}
		return self::collect( $decoded );
	}

	/**
	 * The JSON-LD nodes in a decoded value: unwrap @graph, accept a list or a single object, drop each node's own
	 * @context (the graph carries one at the top). Every node must be an object with a non-empty @type.
	 *
	 * @param mixed $decoded A decoded JSON value.
	 * @return array{nodes: array<int,array>, error: string}
	 */
	private static function collect( $decoded ): array {
		if ( ! is_array( $decoded ) ) {
			return array(
				'nodes' => array(),
				'error' => 'The JSON-LD must be an object or a list of objects.',
			);
		}
		if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
			$list = $decoded['@graph'];
		} elseif ( isset( $decoded['@type'] ) ) {
			$list = array( $decoded );
		} elseif ( array() === $decoded || array_keys( $decoded ) === range( 0, count( $decoded ) - 1 ) ) {
			$list = $decoded; // a plain list of nodes
		} else {
			return array(
				'nodes' => array(),
				'error' => 'No @type and no @graph: this is not JSON-LD.',
			);
		}
		$nodes = array();
		foreach ( $list as $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) || ( ! is_string( $node['@type'] ) && ! is_array( $node['@type'] ) ) ) {
				return array(
					'nodes' => array(),
					'error' => 'Every node needs a @type.',
				);
			}
			unset( $node['@context'] );
			$nodes[] = $node;
			if ( count( $nodes ) >= self::MAX_NODES ) {
				break;
			}
		}
		return array(
			'nodes' => $nodes,
			'error' => '',
		);
	}

	/**
	 * The JSON-LD nodes inside a page's HTML (every <script type="application/ld+json"> block), for "import from a URL".
	 *
	 * @param string $html The page HTML.
	 * @return array<int,array> Nodes, each an object with an @type.
	 */
	public static function extract_from_html( string $html ): array {
		if ( '' === trim( $html ) || ! preg_match_all( '~<script\b[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $m ) ) {
			return array();
		}
		$nodes = array();
		foreach ( $m[1] as $block ) {
			$block = trim( html_entity_decode( $block, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$one   = self::parse( $block );
			foreach ( $one['nodes'] as $node ) {
				$nodes[] = $node;
				if ( count( $nodes ) >= self::MAX_NODES ) {
					return $nodes;
				}
			}
		}
		return $nodes;
	}

	/**
	 * Give each node a stable @id under the page, unless it already has one, so two custom nodes never clash and other
	 * nodes can point at them.
	 *
	 * @param array<int,array> $nodes Nodes from parse()/extract_from_html().
	 * @param string           $base  The page URL (the @id prefix).
	 * @return array<int,array>
	 */
	public static function with_ids( array $nodes, string $base ): array {
		$out = array();
		$n   = 0;
		foreach ( $nodes as $node ) {
			++$n;
			if ( empty( $node['@id'] ) ) {
				$node['@id'] = $base . '#aw-custom-' . $n;
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * A SpeakableSpecification from CSS selectors, or null when there are none.
	 *
	 * @param array $selectors CSS selectors.
	 */
	public static function speakable( array $selectors ): ?array {
		$css = array();
		foreach ( $selectors as $s ) {
			$s = trim( (string) $s );
			if ( '' !== $s ) {
				$css[] = $s;
			}
		}
		return $css ? array(
			'@type'       => 'SpeakableSpecification',
			'cssSelector' => $css,
		) : null;
	}

	/**
	 * Thing references for about / mentions: a URL becomes a sameAs reference, anything else a named Thing.
	 *
	 * @param array $values Names or URLs.
	 * @return array<int,array>
	 */
	public static function things( array $values ): array {
		$out = array();
		foreach ( $values as $v ) {
			$v = trim( (string) $v );
			if ( '' === $v ) {
				continue;
			}
			$out[] = preg_match( '~^https?://~i', $v )
				? array(
					'@type'  => 'Thing',
					'sameAs' => $v,
				)
				: array(
					'@type' => 'Thing',
					'name'  => $v,
				);
		}
		return $out;
	}

	/** A starter Dataset node (the "Dataset" template on the custom-schema box). */
	public static function dataset_template(): string {
		return (string) wp_json_encode(
			array(
				'@context'     => 'https://schema.org',
				'@type'        => 'Dataset',
				'name'         => '',
				'description'  => '',
				'license'      => '',
				'creator'      => array(
					'@type' => 'Organization',
					'name'  => '',
				),
				'distribution' => array(
					array(
						'@type'          => 'DataDownload',
						'encodingFormat' => 'text/csv',
						'contentUrl'     => '',
					),
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}
}
