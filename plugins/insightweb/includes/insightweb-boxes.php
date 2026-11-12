<?php
/**
 * The Overview boxes on the new Dashboard (12 Nov 2026): GET insightweb/v1/dashboard/box (aw_si_cap()) runs the box's
 * own function (aw_si_overview_boxes(), the same as the classic Overview) and hands its output over as plain parts
 * the shell draws: paragraphs, notes, lists, tables and notices. Text and links only: no markup goes out, and a link
 * is kept only when it is http(s) or a relative address.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'kits_shell_rest_can' ) ) {
			return;
		}
		register_rest_route(
			INSIGHTWEB_REST,
			'/dashboard/box',
			array(
				'methods'             => 'GET',
				'permission_callback' => kits_shell_rest_can( aw_si_cap() ),
				'callback'            => 'insightweb_rest_box',
				'args'                => array(
					'key' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}
);

/** The boxes the new Dashboard shows below its tiles (Sources is drawn there already): [ { key, title } ]. */
function insightweb_box_list(): array {
	$out = array();
	foreach ( aw_si_overview_boxes() as $key => $box ) {
		if ( 'sources' !== $key ) {
			$out[] = array(
				'key'   => $key,
				'title' => $box[0],
			);
		}
	}
	return $out;
}

/**
 * GET dashboard/box: one box's parts.
 *
 * @param WP_REST_Request $request Request.
 */
function insightweb_rest_box( WP_REST_Request $request ) {
	$key   = sanitize_key( (string) $request['key'] );
	$boxes = aw_si_overview_boxes();
	if ( ! isset( $boxes[ $key ] ) || ! is_callable( $boxes[ $key ][1] ) ) {
		return new WP_Error( 'insightweb_no_box', __( 'No such box.', 'aw-seo-extras' ), array( 'status' => 404 ) );
	}
	if ( get_option( 'aw_si_off' ) ) {
		return rest_ensure_response(
			array(
				'key'    => $key,
				'title'  => $boxes[ $key ][0],
				'blocks' => array(
					array(
						'type' => 'p',
						'text' => __( 'Search Insights is off: its data was deleted. Settings → Run the daily update starts it again.', 'aw-seo-extras' ),
					),
				),
			)
		);
	}
	ob_start();
	call_user_func( $boxes[ $key ][1] );
	$html = (string) ob_get_clean();
	return rest_ensure_response(
		array(
			'key'    => $key,
			'title'  => $boxes[ $key ][0],
			'blocks' => insightweb_html_blocks( $html ),
		)
	);
}

/**
 * A box's output as plain parts: { type: p | note | list | table | notice, … }.
 *
 * @param string $html The box's output.
 * @return array[]
 */
function insightweb_html_blocks( string $html ): array {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return '' === trim( $html ) ? array() : array(
			array(
				'type' => 'p',
				'text' => trim( wp_strip_all_tags( $html ) ),
			),
		);
	}
	$doc  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="iw-root">' . $html . '</div>', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $doc->getElementById( 'iw-root' );
	if ( ! $root ) {
		return array();
	}
	$out = array();
	insightweb_blocks_walk( $root, $out );
	return $out;
}

/**
 * Walk a node's children into parts. Forms, inputs and scripts are left out (the box's controls stay on the classic
 * Overview); a details element is walked as its content.
 *
 * With $forms (the tabs, insightweb-tabs.php), a form becomes a listtable or form part, a list's views a views part,
 * and a list table outside a form a listtable part.
 *
 * @param DOMNode $node  Node.
 * @param array   $out   Parts so far.
 * @param bool    $forms Keep forms and list tables as parts.
 */
function insightweb_blocks_walk( DOMNode $node, array &$out, bool $forms = false ): void {
	$text  = '';
	$flush = function () use ( &$text, &$out ) {
		$t = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' !== $t ) {
			$out[] = array(
				'type' => 'p',
				'text' => $t,
			);
		}
		$text = '';
	};
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			$text .= $c->textContent;
			continue;
		}
		if ( XML_ELEMENT_NODE !== $c->nodeType ) {
			continue;
		}
		$tag   = strtolower( $c->nodeName );
		$class = ' ' . preg_replace( '/\s+/', ' ', $c->getAttribute( 'class' ) ) . ' ';
		if ( $forms ) {
			if ( 'form' === $tag ) {
				$flush();
				$list  = array_values( array_filter( insightweb_find( $c, 'table' ), fn( $t ) => insightweb_has_class( $t, 'wp-list-table' ) ) );
				$out[] = $list ? insightweb_listtable_block( $list[0], $c ) : insightweb_form_block( $c );
				continue;
			}
			if ( 'table' === $tag && false !== strpos( $class, ' wp-list-table ' ) ) {
				$flush();
				$out[] = insightweb_listtable_block( $c, null );
				continue;
			}
			if ( 'ul' === $tag && false !== strpos( $class, ' subsubsub ' ) ) {
				$flush();
				$out[] = insightweb_views_block( $c );
				continue;
			}
			if ( ( 'div' === $tag && false !== strpos( $class, ' tablenav ' ) ) || ( 'p' === $tag && false !== strpos( $class, ' search-box ' ) ) ) {
				continue; // read with the list table
			}
		}
		if ( in_array( $tag, array( 'script', 'style', 'form', 'input', 'button', 'select', 'textarea', 'noscript' ), true ) ) {
			continue;
		}
		if ( in_array( $tag, array( 'a', 'span', 'strong', 'em', 'b', 'i', 'code', 'abbr', 'small', 'br' ), true ) ) {
			$text .= 'br' === $tag ? ' ' : $c->textContent;
			continue;
		}
		$flush();
		if ( 'table' === $tag ) {
			$out[] = insightweb_table_block( $c );
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$items = array();
			foreach ( $c->childNodes as $li ) {
				if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
					$items[] = insightweb_cell( $li );
				}
			}
			$out[] = array(
				'type'  => 'list',
				'items' => $items,
			);
		} elseif ( false !== strpos( $class, ' notice ' ) ) {
			$tone = 'info';
			foreach ( array( 'error', 'warning', 'success' ) as $t ) {
				if ( false !== strpos( $class, ' notice-' . $t . ' ' ) ) {
					$tone = 'warning' === $t ? 'warn' : $t;
				}
			}
			$out[] = array(
				'type' => 'notice',
				'tone' => $tone,
			) + insightweb_cell( $c );
		} elseif ( 'p' === $tag ) {
			$cell = insightweb_cell( $c );
			if ( '' !== $cell['text'] ) {
				$out[] = array( 'type' => false !== strpos( $class, ' description ' ) ? 'note' : 'p' ) + $cell;
			}
		} elseif ( in_array( $tag, array( 'h2', 'h3', 'h4', 'h5', 'summary' ), true ) ) {
			$t = trim( preg_replace( '/\s+/u', ' ', $c->textContent ) );
			if ( '' !== $t ) {
				$out[] = array(
					'type' => 'heading',
					'text' => $t,
				);
			}
		} else {
			insightweb_blocks_walk( $c, $out, $forms ); // div, details, section …
		}
	}
	$flush();
}

/**
 * A cell's text and its first link (http(s) or relative only).
 *
 * @param DOMNode $n Node.
 * @return array { text, href? }
 */
function insightweb_cell( DOMNode $n ): array {
	$out = array( 'text' => trim( preg_replace( '/\s+/u', ' ', $n->textContent ) ) );
	if ( $n instanceof DOMElement ) {
		$a = 'a' === strtolower( $n->nodeName ) ? $n : $n->getElementsByTagName( 'a' )->item( 0 );
		if ( $a instanceof DOMElement ) {
			$href = trim( $a->getAttribute( 'href' ) );
			if ( '' !== $href && ( preg_match( '#^https?://#i', $href ) || 0 === strpos( $href, '/' ) || preg_match( '#^[a-z0-9_.-]+\.php\b#i', $href ) ) ) {
				$out['href']     = esc_url_raw( $href );
				$out['external'] = '_blank' === $a->getAttribute( 'target' );
			}
		}
	}
	return $out;
}

/**
 * A table as { type: table, head: string[], rows: cell[][] }. A row whose first cell is a th (scope="row") keeps it as
 * its first cell.
 *
 * @param DOMElement $t Table.
 */
function insightweb_table_block( DOMElement $t ): array {
	$head = array();
	$rows = array();
	foreach ( $t->getElementsByTagName( 'tr' ) as $tr ) {
		$cells   = array();
		$in_head = 'thead' === strtolower( $tr->parentNode->nodeName );
		foreach ( $tr->childNodes as $td ) {
			if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
				$cells[] = insightweb_cell( $td );
			}
		}
		if ( ! $cells ) {
			continue;
		}
		if ( $in_head ) {
			$head = array_map( fn( $c ) => $c['text'], $cells );
		} else {
			$rows[] = $cells;
		}
	}
	return array(
		'type' => 'table',
		'head' => $head,
		'rows' => $rows,
	);
}
