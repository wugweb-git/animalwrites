<?php
/**
 * Page parts (12 Nov 2026): a classic screen's output handed to a shell screen as plain parts it can draw, so a page
 * that is still a classic one can sit on the shell without its handlers changing. kits_shell_html_blocks() keeps text,
 * notes, headings, lists, tables and notices (forms and scripts dropped); kits_shell_html_parts() also keeps
 *   listtable: a WP_List_Table with its views, search, filters, columns, rows, row actions, bulk actions and pages;
 *   form:      any other form, with its address, method, hidden fields (its nonce), fields and buttons.
 * The shell's PageParts (src/shell/parts.js) draws them and sends every change as the same link or form request the
 * classic screen sends. Text and links only: no markup or script goes out, a link is kept only when it is http(s),
 * relative or a *.php address, and a password field is never passed on.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output as plain parts: { type: p | note | heading | list | table | notice, … }; forms and scripts are dropped.
 *
 * @param string $html Output.
 * @return array[]
 */
function kits_shell_html_blocks( string $html ): array {
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
	kits_shell_parts_blocks_walk( $root, $out );
	return $out;
}

/**
 * Walk a node's children into parts. Forms, inputs and scripts are left out; a details element is walked as its
 * content.
 *
 * With $forms (kits_shell_html_parts()), a form becomes a listtable or form part, a list's views a views part,
 * and a list table outside a form a listtable part.
 *
 * @param DOMNode $node  Node.
 * @param array   $out   Parts so far.
 * @param bool    $forms Keep forms and list tables as parts.
 */
function kits_shell_parts_blocks_walk( DOMNode $node, array &$out, bool $forms = false ): void {
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
				$list  = array_values( array_filter( kits_shell_parts_find( $c, 'table' ), fn( $t ) => kits_shell_parts_has_class( $t, 'wp-list-table' ) ) );
				$out[] = $list ? kits_shell_parts_listtable_block( $list[0], $c ) : kits_shell_parts_form_block( $c );
				continue;
			}
			if ( 'table' === $tag && false !== strpos( $class, ' wp-list-table ' ) ) {
				$flush();
				$out[] = kits_shell_parts_listtable_block( $c, null );
				continue;
			}
			if ( 'table' === $tag && false !== strpos( $class, ' form-table ' ) ) {
				$flush();
				kits_shell_parts_form_table( $c, $out ); // a settings page: each row is a heading, then its content
				continue;
			}
			if ( 'ul' === $tag && false !== strpos( $class, ' subsubsub ' ) ) {
				$flush();
				$out[] = kits_shell_parts_views_block( $c );
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
			$out[] = kits_shell_parts_table_block( $c );
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$items = array();
			foreach ( $c->childNodes as $li ) {
				if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
					$items[] = kits_shell_parts_cell( $li );
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
			) + kits_shell_parts_cell( $c );
		} elseif ( 'p' === $tag ) {
			$cell = kits_shell_parts_cell( $c );
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
			kits_shell_parts_blocks_walk( $c, $out, $forms ); // div, details, section …
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
function kits_shell_parts_cell( DOMNode $n ): array {
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
function kits_shell_parts_table_block( DOMElement $t ): array {
	$head = array();
	$rows = array();
	foreach ( $t->getElementsByTagName( 'tr' ) as $tr ) {
		$cells   = array();
		$in_head = 'thead' === strtolower( $tr->parentNode->nodeName );
		foreach ( $tr->childNodes as $td ) {
			if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
				$cells[] = kits_shell_parts_cell( $td );
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

/**
 * Output with forms and list tables as plain parts: kits_shell_html_blocks()'s parts plus listtable and form.
 *
 * @param string $html Output.
 * @return array[]
 */
function kits_shell_html_parts( string $html ): array {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return kits_shell_html_blocks( $html );
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
	kits_shell_parts_blocks_walk( $root, $out, true );
	// A list's views (printed just before its form) go with the list that follows them.
	$views = null;
	$final = array();
	foreach ( $out as $part ) {
		if ( 'views' === $part['type'] ) {
			$views = $part['views'];
			continue;
		}
		if ( 'listtable' === $part['type'] && null !== $views ) {
			$part['views'] = $views;
			$views         = null;
		}
		$final[] = $part;
	}
	return $final;
}

/**
 * Whether an element has a class.
 *
 * @param DOMNode $n     Node.
 * @param string  $cls Class.
 */
function kits_shell_parts_has_class( DOMNode $n, string $cls ): bool {
	return $n instanceof DOMElement && false !== strpos( ' ' . preg_replace( '/\s+/', ' ', $n->getAttribute( 'class' ) ) . ' ', ' ' . $cls . ' ' );
}

/**
 * Elements under a node, by tag, optionally with a class.
 *
 * @param DOMNode $n     Node.
 * @param string  $tag   Tag name.
 * @param string  $cls   Class ('' for any).
 * @return DOMElement[]
 */
function kits_shell_parts_find( DOMNode $n, string $tag, string $cls = '' ): array {
	$out = array();
	if ( ! $n instanceof DOMElement ) {
		return $out;
	}
	foreach ( $n->getElementsByTagName( $tag ) as $el ) {
		if ( '' === $cls || kits_shell_parts_has_class( $el, $cls ) ) {
			$out[] = $el;
		}
	}
	return $out;
}

/**
 * Text of a node without the parts that are not content (row actions, the phone row toggle, scripts).
 *
 * @param DOMNode $n Node.
 */
function kits_shell_parts_text( DOMNode $n ): string {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return $n->textContent;
	}
	if ( ! $n instanceof DOMElement ) {
		return '';
	}
	$tag = strtolower( $n->nodeName );
	if ( in_array( $tag, array( 'script', 'style', 'button', 'select', 'textarea', 'noscript' ), true ) || kits_shell_parts_has_class( $n, 'row-actions' ) ) {
		return '';
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= kits_shell_parts_text( $c );
	}
	return 'br' === $tag ? ' ' : $t;
}

/**
 * A safe link address, or ''.
 *
 * @param string $href Address.
 */
function kits_shell_parts_safe_href( string $href ): string {
	$href = trim( html_entity_decode( $href, ENT_QUOTES ) );
	if ( '' !== $href && ( preg_match( '#^https?://#i', $href ) || 0 === strpos( $href, '/' ) || preg_match( '#^[a-z0-9_.-]+\.php\b#i', $href ) ) ) {
		return esc_url_raw( $href );
	}
	return '';
}

/**
 * Squash white space.
 *
 * @param string $t Text.
 */
function kits_shell_parts_squash( string $t ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

/**
 * A settings table (table.form-table) outside a form: each row's heading as a heading part, then the row's content
 * (its forms among it) as parts.
 *
 * @param DOMElement $table The table.
 * @param array      $out   Parts so far.
 */
function kits_shell_parts_form_table( DOMElement $table, array &$out ): void {
	foreach ( kits_shell_parts_find( $table, 'tr' ) as $tr ) {
		foreach ( $tr->childNodes as $cell ) {
			if ( ! $cell instanceof DOMElement ) {
				continue;
			}
			if ( 'th' === strtolower( $cell->nodeName ) ) {
				$t = kits_shell_parts_squash( kits_shell_parts_text_no_sr( $cell ) );
				if ( '' !== $t ) {
					$out[] = array(
						'type' => 'heading',
						'text' => $t,
					);
				}
			} elseif ( 'td' === strtolower( $cell->nodeName ) ) {
				kits_shell_parts_blocks_walk( $cell, $out, true );
			}
		}
	}
}

/**
 * A WP_List_Table as one part.
 *
 * @param DOMElement      $table The table.wp-list-table.
 * @param DOMElement|null $form  The form around it, if any.
 */
function kits_shell_parts_listtable_block( DOMElement $table, ?DOMElement $form ): array {
	$scope = $form ? $form : $table->parentNode;
	$part  = array(
		'type'    => 'listtable',
		'method'  => $form && 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => $form ? kits_shell_parts_safe_href( $form->getAttribute( 'action' ) ) : '',
		'hidden'  => array(),
		'views'   => array(),
		'search'  => null,
		'filters' => array(),
		'bulk'    => array(),
		'cb'      => '',
		'columns' => array(),
		'rows'    => array(),
		'empty'   => '',
		'total'   => 0,
		'page'    => 1,
		'pages'   => 1,
	);
	$skip  = array( 'paged', 's', 'action', 'action2', 'filter_action' );
	if ( $form ) {
		foreach ( kits_shell_parts_find( $form, 'input' ) as $in ) {
			if ( 'hidden' === strtolower( $in->getAttribute( 'type' ) ) && '' !== $in->getAttribute( 'name' ) && ! in_array( $in->getAttribute( 'name' ), $skip, true ) ) {
				$part['hidden'][] = array( $in->getAttribute( 'name' ), $in->getAttribute( 'value' ) );
			}
		}
		foreach ( kits_shell_parts_find( $form, 'p', 'search-box' ) as $box ) {
			foreach ( kits_shell_parts_find( $box, 'input' ) as $in ) {
				if ( 'search' === strtolower( $in->getAttribute( 'type' ) ) ) {
					$label          = kits_shell_parts_find( $box, 'label' );
					$part['search'] = array(
						'name'  => $in->getAttribute( 'name' ),
						'value' => $in->getAttribute( 'value' ),
						'label' => $label ? kits_shell_parts_squash( $label[0]->textContent ) : '',
					);
				}
			}
		}
	}
	// The top bar: bulk actions, filters, the count and pages.
	foreach ( kits_shell_parts_find( $scope, 'div', 'tablenav' ) as $nav ) {
		if ( ! kits_shell_parts_has_class( $nav, 'top' ) ) {
			continue;
		}
		foreach ( kits_shell_parts_find( $nav, 'select' ) as $sel ) {
			$name = $sel->getAttribute( 'name' );
			$opts = array();
			$cur  = '';
			foreach ( kits_shell_parts_find( $sel, 'option' ) as $o ) {
				$opts[] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => kits_shell_parts_squash( $o->textContent ),
				);
				if ( $o->hasAttribute( 'selected' ) ) {
					$cur = $o->getAttribute( 'value' );
				}
			}
			if ( 'action' === $name ) {
				$part['bulk'] = array_values( array_filter( $opts, fn( $o ) => '-1' !== $o['value'] && '' !== $o['value'] ) );
			} elseif ( '' !== $name && 'action2' !== $name ) {
				$label = '';
				foreach ( kits_shell_parts_find( $nav, 'label' ) as $l ) {
					if ( $l->getAttribute( 'for' ) === $sel->getAttribute( 'id' ) ) {
						$label = kits_shell_parts_squash( $l->textContent );
					}
				}
				$part['filters'][] = array(
					'key'     => $name,
					'label'   => '' !== $label ? $label : $name,
					'options' => $opts,
					'value'   => $cur,
				);
			}
		}
		foreach ( kits_shell_parts_find( $nav, 'span', 'displaying-num' ) as $s ) {
			$part['total'] = (int) preg_replace( '/\D/', '', $s->textContent );
		}
		foreach ( kits_shell_parts_find( $nav, 'span', 'total-pages' ) as $s ) {
			$part['pages'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		foreach ( kits_shell_parts_find( $nav, 'input', 'current-page' ) as $s ) {
			$part['page'] = max( 1, (int) $s->getAttribute( 'value' ) );
		}
		foreach ( kits_shell_parts_find( $nav, 'span', 'current-page' ) as $s ) { // the pages with only one page of links
			$part['page'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		break;
	}
	// Columns from the head.
	$keys = array();
	foreach ( kits_shell_parts_find( $table, 'thead' ) as $head ) {
		foreach ( kits_shell_parts_find( $head, 'tr' ) as $tr ) {
			foreach ( $tr->childNodes as $th ) {
				if ( ! $th instanceof DOMElement || ! in_array( strtolower( $th->nodeName ), array( 'th', 'td' ), true ) ) {
					continue;
				}
				if ( kits_shell_parts_has_class( $th, 'check-column' ) ) {
					continue;
				}
				$key = $th->getAttribute( 'id' );
				if ( '' === $key && preg_match( '/\bcolumn-([a-z0-9_-]+)/i', $th->getAttribute( 'class' ), $m ) ) {
					$key = $m[1];
				}
				$label = '';
				foreach ( $th->childNodes as $c ) {
					if ( $c instanceof DOMElement && kits_shell_parts_has_class( $c, 'screen-reader-text' ) ) {
						continue;
					}
					$label .= $c instanceof DOMElement ? kits_shell_parts_text_no_sr( $c ) : $c->textContent;
				}
				$a      = kits_shell_parts_find( $th, 'a' );
				$sorted = kits_shell_parts_has_class( $th, 'sorted' ) ? ( kits_shell_parts_has_class( $th, 'asc' ) ? 'asc' : 'desc' ) : '';
				$col    = array(
					'key'     => sanitize_key( $key ),
					'label'   => kits_shell_parts_squash( $label ),
					'primary' => kits_shell_parts_has_class( $th, 'column-primary' ),
				);
				if ( $a && '' !== kits_shell_parts_safe_href( $a[0]->getAttribute( 'href' ) ) ) {
					$col['sortHref'] = kits_shell_parts_safe_href( $a[0]->getAttribute( 'href' ) );
					$col['sorted']   = $sorted;
				}
				$part['columns'][] = $col;
				$keys[]            = $col['key'];
			}
			break;
		}
		break;
	}
	if ( $part['columns'] && ! array_filter( array_column( $part['columns'], 'primary' ) ) ) {
		$part['columns'][0]['primary'] = true;
	}
	// Rows.
	$i = 0;
	foreach ( kits_shell_parts_find( $table, 'tbody' ) as $body ) {
		foreach ( $body->childNodes as $tr ) {
			if ( ! $tr instanceof DOMElement || 'tr' !== strtolower( $tr->nodeName ) ) {
				continue;
			}
			if ( kits_shell_parts_has_class( $tr, 'no-items' ) ) {
				$part['empty'] = kits_shell_parts_squash( kits_shell_parts_text( $tr ) );
				continue;
			}
			++$i;
			$row = array(
				'id'      => 'row-' . $i,
				'pick'    => '',
				'cells'   => array(),
				'actions' => array(),
			);
			$n   = 0;
			foreach ( $tr->childNodes as $td ) {
				if ( ! $td instanceof DOMElement || ! in_array( strtolower( $td->nodeName ), array( 'th', 'td' ), true ) ) {
					continue;
				}
				if ( kits_shell_parts_has_class( $td, 'check-column' ) ) {
					foreach ( kits_shell_parts_find( $td, 'input' ) as $cb ) {
						if ( 'checkbox' === strtolower( $cb->getAttribute( 'type' ) ) ) {
							$part['cb']  = $cb->getAttribute( 'name' );
							$row['pick'] = $cb->getAttribute( 'value' );
						}
					}
					continue;
				}
				$key = '';
				if ( preg_match( '/\bcolumn-([a-z0-9_-]+)/i', $td->getAttribute( 'class' ), $m ) ) {
					$key = sanitize_key( $m[1] );
				}
				if ( '' === $key || ! in_array( $key, $keys, true ) ) {
					$key = $keys[ $n ] ?? 'col' . $n;
				}
				++$n;
				$cell = array( 'text' => kits_shell_parts_squash( kits_shell_parts_text( $td ) ) );
				foreach ( kits_shell_parts_find( $td, 'a' ) as $a ) {
					if ( kits_shell_parts_in_row_actions( $a, $td ) ) {
						continue;
					}
					$href = kits_shell_parts_safe_href( $a->getAttribute( 'href' ) );
					if ( '' !== $href ) {
						$cell['href']     = $href;
						$cell['external'] = '_blank' === $a->getAttribute( 'target' );
					}
					break;
				}
				$row['cells'][ $key ] = $cell;
				foreach ( kits_shell_parts_find( $td, 'div', 'row-actions' ) as $ra ) {
					foreach ( kits_shell_parts_find( $ra, 'a' ) as $a ) {
						$href = kits_shell_parts_safe_href( $a->getAttribute( 'href' ) );
						$text = kits_shell_parts_squash( kits_shell_parts_text_no_sr( $a ) );
						if ( '' !== $href && '' !== $text ) {
							$row['actions'][] = array(
								'label'    => $text,
								'href'     => $href,
								'external' => '_blank' === $a->getAttribute( 'target' ),
							);
						}
					}
				}
			}
			$part['rows'][] = $row;
		}
		break;
	}
	if ( ! $part['total'] ) {
		$part['total'] = count( $part['rows'] );
	}
	return $part;
}

/**
 * Text without screen-reader-only words.
 *
 * @param DOMNode $n Node.
 */
function kits_shell_parts_text_no_sr( DOMNode $n ): string {
	if ( $n instanceof DOMElement && kits_shell_parts_has_class( $n, 'screen-reader-text' ) ) {
		return '';
	}
	if ( ! $n instanceof DOMElement ) {
		return $n->textContent;
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= kits_shell_parts_text_no_sr( $c );
	}
	return $t;
}

/**
 * Whether a link sits in a cell's row actions.
 *
 * @param DOMNode $a  Link.
 * @param DOMNode $td Cell.
 */
function kits_shell_parts_in_row_actions( DOMNode $a, DOMNode $td ): bool {
	for ( $p = $a->parentNode; $p && $p !== $td; $p = $p->parentNode ) {
		if ( kits_shell_parts_has_class( $p, 'row-actions' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * A list's views (ul.subsubsub) as { value, label, count, href, current }.
 *
 * @param DOMElement $ul The list.
 */
function kits_shell_parts_views_block( DOMElement $ul ): array {
	$views = array();
	foreach ( kits_shell_parts_find( $ul, 'a' ) as $i => $a ) {
		$count = '';
		foreach ( kits_shell_parts_find( $a, 'span', 'count' ) as $c ) {
			$count = preg_replace( '/[^\d,.]/', '', $c->textContent );
			$c->parentNode->removeChild( $c );
		}
		$href = kits_shell_parts_safe_href( $a->getAttribute( 'href' ) );
		if ( '' === $href ) {
			continue;
		}
		$views[] = array(
			'value'   => 'v' . $i,
			'label'   => kits_shell_parts_squash( $a->textContent ),
			'count'   => $count,
			'href'    => $href,
			'current' => kits_shell_parts_has_class( $a, 'current' ) || 'page' === $a->getAttribute( 'aria-current' ),
		);
	}
	return array(
		'type'  => 'views',
		'views' => $views,
	);
}

/**
 * Any other form: its own address, method, hidden fields (its nonce among them), fields and buttons, and its words.
 *
 * @param DOMElement $form The form.
 */
function kits_shell_parts_form_block( DOMElement $form ): array {
	$part   = array(
		'type'    => 'form',
		'method'  => 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => kits_shell_parts_safe_href( $form->getAttribute( 'action' ) ),
		'enctype' => 'multipart/form-data' === strtolower( $form->getAttribute( 'enctype' ) ) ? 'multipart/form-data' : '',
		'hidden'  => array(),
		'fields'  => array(),
		'buttons' => array(),
		'blocks'  => array(),
	);
	$radios = array();
	$xpath  = new DOMXPath( $form->ownerDocument );
	foreach ( $xpath->query( './/input|.//select|.//textarea|.//button', $form ) as $el ) {
		$tag  = strtolower( $el->nodeName );
		$type = 'input' === $tag ? strtolower( $el->getAttribute( 'type' ) ? $el->getAttribute( 'type' ) : 'text' ) : $tag;
		$name = $el->getAttribute( 'name' );
		if ( 'button' === $tag ) {
			$type = strtolower( $el->getAttribute( 'type' ) ? $el->getAttribute( 'type' ) : 'submit' );
			if ( 'submit' === $type ) {
				$part['buttons'][] = array(
					'name'    => $name,
					'value'   => $el->getAttribute( 'value' ),
					'label'   => kits_shell_parts_squash( $el->textContent ),
					'primary' => kits_shell_parts_has_class( $el, 'button-primary' ),
				);
			}
			continue;
		}
		if ( 'submit' === $type ) {
			$part['buttons'][] = array(
				'name'    => $name,
				'value'   => $el->getAttribute( 'value' ),
				'label'   => $el->getAttribute( 'value' ),
				'primary' => kits_shell_parts_has_class( $el, 'button-primary' ),
			);
			continue;
		}
		if ( '' === $name || in_array( $type, array( 'button', 'image', 'reset', 'password' ), true ) ) {
			continue;
		}
		if ( 'hidden' === $type ) {
			$part['hidden'][] = array( $name, $el->getAttribute( 'value' ) );
			continue;
		}
		$label = kits_shell_parts_field_label( $el, $xpath );
		$help  = '';
		foreach ( $xpath->query( 'ancestor::td[1]//p[contains(concat(" ", normalize-space(@class), " "), " description ")]', $el ) as $d ) {
			$help = kits_shell_parts_squash( $d->textContent );
		}
		if ( 'radio' === $type ) {
			if ( ! isset( $radios[ $name ] ) ) {
				$radios[ $name ]  = count( $part['fields'] );
				$part['fields'][] = array(
					'type'    => 'radio',
					'name'    => $name,
					'label'   => kits_shell_parts_group_label( $el, $xpath ),
					'help'    => $help,
					'options' => array(),
					'value'   => '',
				);
			}
			$f              = &$part['fields'][ $radios[ $name ] ];
			$f['options'][] = array(
				'value' => $el->getAttribute( 'value' ),
				'label' => $label,
			);
			$f['value']     = $el->hasAttribute( 'checked' ) ? $el->getAttribute( 'value' ) : $f['value'];
			unset( $f );
			continue;
		}
		$field = array(
			'type'     => $type,
			'name'     => $name,
			'label'    => $label,
			'help'     => $help,
			'disabled' => $el->hasAttribute( 'disabled' ), // shown, not sent (for example: only administrators can change it)
		);
		if ( 'select' === $type ) {
			$field['options']  = array();
			$field['value']    = null;
			$field['multiple'] = $el->hasAttribute( 'multiple' );
			foreach ( kits_shell_parts_find( $el, 'option' ) as $o ) {
				$field['options'][] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => kits_shell_parts_squash( $o->textContent ),
				);
				if ( $o->hasAttribute( 'selected' ) || null === $field['value'] ) {
					$field['value'] = $o->getAttribute( 'value' ); // the first option, unless one is selected
				}
			}
			$field['value'] = (string) $field['value'];
		} elseif ( 'textarea' === $type ) {
			$field['value'] = $el->textContent;
			$field['rows']  = max( 2, (int) $el->getAttribute( 'rows' ) );
		} elseif ( 'file' === $type ) {
			$field['accept'] = $el->getAttribute( 'accept' ); // a file is picked in the browser; nothing is filled in
		} elseif ( 'checkbox' === $type ) {
			$field['value']   = $el->hasAttribute( 'value' ) ? $el->getAttribute( 'value' ) : 'on';
			$field['checked'] = $el->hasAttribute( 'checked' );
		} else {
			$field['value'] = $el->getAttribute( 'value' );
			foreach ( array( 'placeholder', 'min', 'max', 'step' ) as $attr ) {
				if ( $el->hasAttribute( $attr ) ) {
					$field[ $attr ] = $el->getAttribute( $attr );
				}
			}
			$field['required'] = $el->hasAttribute( 'required' );
		}
		$part['fields'][] = $field;
	}
	// The form's words: everything but its fields and the form-table labels the fields carry.
	foreach ( iterator_to_array( $xpath->query( './/table[contains(concat(" ", normalize-space(@class), " "), " form-table ")]', $form ) ) as $ft ) {
		$ft->parentNode->removeChild( $ft );
	}
	foreach ( iterator_to_array( $xpath->query( './/label', $form ) ) as $l ) {
		$l->parentNode->removeChild( $l );
	}
	kits_shell_parts_blocks_walk( $form, $part['blocks'], false );
	return $part;
}

/**
 * A field's label: its own label, the row's heading, its aria-label, placeholder or name.
 *
 * @param DOMElement $el    Field.
 * @param DOMXPath   $xpath XPath.
 */
function kits_shell_parts_field_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::label[1]', $el ) as $l ) {
		$t = kits_shell_parts_squash( kits_shell_parts_text_no_sr( $l ) );
		if ( '' !== $t ) {
			return $t;
		}
	}
	$id = $el->getAttribute( 'id' );
	if ( '' !== $id ) {
		foreach ( $xpath->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' ) as $l ) {
			return kits_shell_parts_squash( $l->textContent );
		}
	}
	foreach ( $xpath->query( 'ancestor::tr[1]/th', $el ) as $th ) {
		return kits_shell_parts_squash( $th->textContent );
	}
	foreach ( array( 'aria-label', 'placeholder', 'name' ) as $attr ) {
		if ( '' !== $el->getAttribute( $attr ) ) {
			return $el->getAttribute( $attr );
		}
	}
	return '';
}

/**
 * A radio group's label: its fieldset legend or the row's heading.
 *
 * @param DOMElement $el    One radio.
 * @param DOMXPath   $xpath XPath.
 */
function kits_shell_parts_group_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::fieldset[1]/legend|ancestor::tr[1]/th', $el ) as $l ) {
		return kits_shell_parts_squash( $l->textContent );
	}
	return $el->getAttribute( 'name' );
}
