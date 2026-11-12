<?php
/**
 * Search Insights' other tabs on the new screen (12 Nov 2026): Keywords, Pages, Check list, Rising, Clarity, Links,
 * Server, Sourcing keywords and any tab added later through the aw_si_tabs filter.
 *
 * On the tab's own address, after its load hook (which still handles every bulk and row action, then redirects back),
 * the tab draws its usual output into a buffer and insightweb_tab_parts() hands it to the new screen as plain parts:
 * text, notices and tables as on the Dashboard boxes, plus
 *   listtable: a WP_List_Table with its views, search, filters, columns, rows, row actions, bulk actions and pages;
 *   form:      any other form, with its fields and buttons.
 * The new screen draws them with the shell's parts and sends every change as the same request the current screen
 * sends (a link, or the form's own GET or POST with its own nonce), so nothing is saved in a new way. Text and links
 * only: no markup or script goes out, and a link is kept only when it is http(s), relative or a *.php address.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The tabs this file draws: every Search Insights tab but Overview (the Dashboard) and Settings. */
function insightweb_tab_keys(): array {
	return array_merge( array( 'keywords' ), array_keys( aw_si_extra_tabs() ) );
}

/** Each tab's label: key => label. */
function insightweb_tab_labels(): array {
	return array( 'keywords' => __( 'Keywords', 'aw-seo-extras' ) ) + aw_si_extra_tabs();
}

/**
 * The tab's output as parts: the notices above every tab, then the tab itself.
 *
 * @param string $tab A key of insightweb_tab_keys().
 * @return array[]
 */
function insightweb_tab_parts( string $tab ): array {
	if ( ! in_array( $tab, insightweb_tab_keys(), true ) ) {
		return array();
	}
	ob_start();
	aw_si_screen_notices();
	if ( 'keywords' === $tab ) {
		aw_si_keywords_tab();
	} else {
		do_action( 'aw_si_tab_' . $tab );
	}
	return insightweb_html_parts( (string) ob_get_clean() );
}

/**
 * Output with forms and list tables as plain parts (the Dashboard boxes' parts plus listtable and form).
 *
 * @param string $html Output.
 * @return array[]
 */
function insightweb_html_parts( string $html ): array {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return insightweb_html_blocks( $html );
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
	insightweb_blocks_walk( $root, $out, true );
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
function insightweb_has_class( DOMNode $n, string $cls ): bool {
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
function insightweb_find( DOMNode $n, string $tag, string $cls = '' ): array {
	$out = array();
	if ( ! $n instanceof DOMElement ) {
		return $out;
	}
	foreach ( $n->getElementsByTagName( $tag ) as $el ) {
		if ( '' === $cls || insightweb_has_class( $el, $cls ) ) {
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
function insightweb_text( DOMNode $n ): string {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return $n->textContent;
	}
	if ( ! $n instanceof DOMElement ) {
		return '';
	}
	$tag = strtolower( $n->nodeName );
	if ( in_array( $tag, array( 'script', 'style', 'button', 'select', 'textarea', 'noscript' ), true ) || insightweb_has_class( $n, 'row-actions' ) ) {
		return '';
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= insightweb_text( $c );
	}
	return 'br' === $tag ? ' ' : $t;
}

/**
 * A safe link address, or ''.
 *
 * @param string $href Address.
 */
function insightweb_safe_href( string $href ): string {
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
function insightweb_squash( string $t ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

/**
 * A WP_List_Table as one part.
 *
 * @param DOMElement      $table The table.wp-list-table.
 * @param DOMElement|null $form  The form around it, if any.
 */
function insightweb_listtable_block( DOMElement $table, ?DOMElement $form ): array {
	$scope = $form ? $form : $table->parentNode;
	$part  = array(
		'type'    => 'listtable',
		'method'  => $form && 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => $form ? insightweb_safe_href( $form->getAttribute( 'action' ) ) : '',
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
		foreach ( insightweb_find( $form, 'input' ) as $in ) {
			if ( 'hidden' === strtolower( $in->getAttribute( 'type' ) ) && '' !== $in->getAttribute( 'name' ) && ! in_array( $in->getAttribute( 'name' ), $skip, true ) ) {
				$part['hidden'][] = array( $in->getAttribute( 'name' ), $in->getAttribute( 'value' ) );
			}
		}
		foreach ( insightweb_find( $form, 'p', 'search-box' ) as $box ) {
			foreach ( insightweb_find( $box, 'input' ) as $in ) {
				if ( 'search' === strtolower( $in->getAttribute( 'type' ) ) ) {
					$label          = insightweb_find( $box, 'label' );
					$part['search'] = array(
						'name'  => $in->getAttribute( 'name' ),
						'value' => $in->getAttribute( 'value' ),
						'label' => $label ? insightweb_squash( $label[0]->textContent ) : '',
					);
				}
			}
		}
	}
	// The top bar: bulk actions, filters, the count and pages.
	foreach ( insightweb_find( $scope, 'div', 'tablenav' ) as $nav ) {
		if ( ! insightweb_has_class( $nav, 'top' ) ) {
			continue;
		}
		foreach ( insightweb_find( $nav, 'select' ) as $sel ) {
			$name = $sel->getAttribute( 'name' );
			$opts = array();
			$cur  = '';
			foreach ( insightweb_find( $sel, 'option' ) as $o ) {
				$opts[] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => insightweb_squash( $o->textContent ),
				);
				if ( $o->hasAttribute( 'selected' ) ) {
					$cur = $o->getAttribute( 'value' );
				}
			}
			if ( 'action' === $name ) {
				$part['bulk'] = array_values( array_filter( $opts, fn( $o ) => '-1' !== $o['value'] && '' !== $o['value'] ) );
			} elseif ( '' !== $name && 'action2' !== $name ) {
				$label = '';
				foreach ( insightweb_find( $nav, 'label' ) as $l ) {
					if ( $l->getAttribute( 'for' ) === $sel->getAttribute( 'id' ) ) {
						$label = insightweb_squash( $l->textContent );
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
		foreach ( insightweb_find( $nav, 'span', 'displaying-num' ) as $s ) {
			$part['total'] = (int) preg_replace( '/\D/', '', $s->textContent );
		}
		foreach ( insightweb_find( $nav, 'span', 'total-pages' ) as $s ) {
			$part['pages'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		foreach ( insightweb_find( $nav, 'input', 'current-page' ) as $s ) {
			$part['page'] = max( 1, (int) $s->getAttribute( 'value' ) );
		}
		foreach ( insightweb_find( $nav, 'span', 'current-page' ) as $s ) { // the pages with only one page of links
			$part['page'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		break;
	}
	// Columns from the head.
	$keys = array();
	foreach ( insightweb_find( $table, 'thead' ) as $head ) {
		foreach ( insightweb_find( $head, 'tr' ) as $tr ) {
			foreach ( $tr->childNodes as $th ) {
				if ( ! $th instanceof DOMElement || ! in_array( strtolower( $th->nodeName ), array( 'th', 'td' ), true ) ) {
					continue;
				}
				if ( insightweb_has_class( $th, 'check-column' ) ) {
					continue;
				}
				$key = $th->getAttribute( 'id' );
				if ( '' === $key && preg_match( '/\bcolumn-([a-z0-9_-]+)/i', $th->getAttribute( 'class' ), $m ) ) {
					$key = $m[1];
				}
				$label = '';
				foreach ( $th->childNodes as $c ) {
					if ( $c instanceof DOMElement && insightweb_has_class( $c, 'screen-reader-text' ) ) {
						continue;
					}
					$label .= $c instanceof DOMElement ? insightweb_text_no_sr( $c ) : $c->textContent;
				}
				$a      = insightweb_find( $th, 'a' );
				$sorted = insightweb_has_class( $th, 'sorted' ) ? ( insightweb_has_class( $th, 'asc' ) ? 'asc' : 'desc' ) : '';
				$col    = array(
					'key'     => sanitize_key( $key ),
					'label'   => insightweb_squash( $label ),
					'primary' => insightweb_has_class( $th, 'column-primary' ),
				);
				if ( $a && '' !== insightweb_safe_href( $a[0]->getAttribute( 'href' ) ) ) {
					$col['sortHref'] = insightweb_safe_href( $a[0]->getAttribute( 'href' ) );
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
	foreach ( insightweb_find( $table, 'tbody' ) as $body ) {
		foreach ( $body->childNodes as $tr ) {
			if ( ! $tr instanceof DOMElement || 'tr' !== strtolower( $tr->nodeName ) ) {
				continue;
			}
			if ( insightweb_has_class( $tr, 'no-items' ) ) {
				$part['empty'] = insightweb_squash( insightweb_text( $tr ) );
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
				if ( insightweb_has_class( $td, 'check-column' ) ) {
					foreach ( insightweb_find( $td, 'input' ) as $cb ) {
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
				$cell = array( 'text' => insightweb_squash( insightweb_text( $td ) ) );
				foreach ( insightweb_find( $td, 'a' ) as $a ) {
					if ( insightweb_in_row_actions( $a, $td ) ) {
						continue;
					}
					$href = insightweb_safe_href( $a->getAttribute( 'href' ) );
					if ( '' !== $href ) {
						$cell['href']     = $href;
						$cell['external'] = '_blank' === $a->getAttribute( 'target' );
					}
					break;
				}
				$row['cells'][ $key ] = $cell;
				foreach ( insightweb_find( $td, 'div', 'row-actions' ) as $ra ) {
					foreach ( insightweb_find( $ra, 'a' ) as $a ) {
						$href = insightweb_safe_href( $a->getAttribute( 'href' ) );
						$text = insightweb_squash( insightweb_text_no_sr( $a ) );
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
function insightweb_text_no_sr( DOMNode $n ): string {
	if ( $n instanceof DOMElement && insightweb_has_class( $n, 'screen-reader-text' ) ) {
		return '';
	}
	if ( ! $n instanceof DOMElement ) {
		return $n->textContent;
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= insightweb_text_no_sr( $c );
	}
	return $t;
}

/**
 * Whether a link sits in a cell's row actions.
 *
 * @param DOMNode $a  Link.
 * @param DOMNode $td Cell.
 */
function insightweb_in_row_actions( DOMNode $a, DOMNode $td ): bool {
	for ( $p = $a->parentNode; $p && $p !== $td; $p = $p->parentNode ) {
		if ( insightweb_has_class( $p, 'row-actions' ) ) {
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
function insightweb_views_block( DOMElement $ul ): array {
	$views = array();
	foreach ( insightweb_find( $ul, 'a' ) as $i => $a ) {
		$count = '';
		foreach ( insightweb_find( $a, 'span', 'count' ) as $c ) {
			$count = preg_replace( '/[^\d,.]/', '', $c->textContent );
			$c->parentNode->removeChild( $c );
		}
		$href = insightweb_safe_href( $a->getAttribute( 'href' ) );
		if ( '' === $href ) {
			continue;
		}
		$views[] = array(
			'value'   => 'v' . $i,
			'label'   => insightweb_squash( $a->textContent ),
			'count'   => $count,
			'href'    => $href,
			'current' => insightweb_has_class( $a, 'current' ) || 'page' === $a->getAttribute( 'aria-current' ),
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
function insightweb_form_block( DOMElement $form ): array {
	$part   = array(
		'type'    => 'form',
		'method'  => 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => insightweb_safe_href( $form->getAttribute( 'action' ) ),
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
					'label'   => insightweb_squash( $el->textContent ),
					'primary' => insightweb_has_class( $el, 'button-primary' ),
				);
			}
			continue;
		}
		if ( 'submit' === $type ) {
			$part['buttons'][] = array(
				'name'    => $name,
				'value'   => $el->getAttribute( 'value' ),
				'label'   => $el->getAttribute( 'value' ),
				'primary' => insightweb_has_class( $el, 'button-primary' ),
			);
			continue;
		}
		if ( '' === $name || in_array( $type, array( 'button', 'image', 'reset', 'file', 'password' ), true ) ) {
			continue;
		}
		if ( 'hidden' === $type ) {
			$part['hidden'][] = array( $name, $el->getAttribute( 'value' ) );
			continue;
		}
		$label = insightweb_field_label( $el, $xpath );
		$help  = '';
		foreach ( $xpath->query( 'ancestor::td[1]//p[contains(concat(" ", normalize-space(@class), " "), " description ")]', $el ) as $d ) {
			$help = insightweb_squash( $d->textContent );
		}
		if ( 'radio' === $type ) {
			if ( ! isset( $radios[ $name ] ) ) {
				$radios[ $name ]  = count( $part['fields'] );
				$part['fields'][] = array(
					'type'    => 'radio',
					'name'    => $name,
					'label'   => insightweb_group_label( $el, $xpath ),
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
			'type'  => $type,
			'name'  => $name,
			'label' => $label,
			'help'  => $help,
		);
		if ( 'select' === $type ) {
			$field['options']  = array();
			$field['value']    = null;
			$field['multiple'] = $el->hasAttribute( 'multiple' );
			foreach ( insightweb_find( $el, 'option' ) as $o ) {
				$field['options'][] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => insightweb_squash( $o->textContent ),
				);
				if ( $o->hasAttribute( 'selected' ) || null === $field['value'] ) {
					$field['value'] = $o->getAttribute( 'value' ); // the first option, unless one is selected
				}
			}
			$field['value'] = (string) $field['value'];
		} elseif ( 'textarea' === $type ) {
			$field['value'] = $el->textContent;
			$field['rows']  = max( 2, (int) $el->getAttribute( 'rows' ) );
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
	insightweb_blocks_walk( $form, $part['blocks'], false );
	return $part;
}

/**
 * A field's label: its own label, the row's heading, its aria-label, placeholder or name.
 *
 * @param DOMElement $el    Field.
 * @param DOMXPath   $xpath XPath.
 */
function insightweb_field_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::label[1]', $el ) as $l ) {
		$t = insightweb_squash( insightweb_text_no_sr( $l ) );
		if ( '' !== $t ) {
			return $t;
		}
	}
	$id = $el->getAttribute( 'id' );
	if ( '' !== $id ) {
		foreach ( $xpath->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' ) as $l ) {
			return insightweb_squash( $l->textContent );
		}
	}
	foreach ( $xpath->query( 'ancestor::tr[1]/th', $el ) as $th ) {
		return insightweb_squash( $th->textContent );
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
function insightweb_group_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::fieldset[1]/legend|ancestor::tr[1]/th', $el ) as $l ) {
		return insightweb_squash( $l->textContent );
	}
	return $el->getAttribute( 'name' );
}
