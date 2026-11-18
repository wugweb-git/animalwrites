<?php
/**
 * MailWeb's bundled admin shell: the shared parts its screens are built from.
 *
 * A self-contained, GPL admin-shell UI toolkit bundled with the plugin — one script and one stylesheet, both with the
 * namespaced handle `mailweb-shell` (built from packages/mailweb-shell into build/shell/), which sets
 * window.mailweb.shell. Screens render on it with no dependency on any other plugin. A screen is put in place with two
 * calls:
 *
 *     mailweb_shell_app( array( 'handle' => …, 'src' => …, 'asset' => …, 'object' => …, 'data' => … ) ); // enqueue
 *     mailweb_shell_mount( 'my-app', __( 'My plugin', 'mailweb' ) );
 *
 * mailweb_shell_rest_can() is the REST permission check the mailweb/v1 screen routes use; mailweb_shell_html_parts()
 * turns a server-rendered classic screen into the parts the Dashboard renders.
 *
 * @package mailweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/** The shell's version, from its build (changes whenever the built file changes). */
function mailweb_shell_asset(): array {
	$file = MAILWEB_DIR . 'build/shell/index.asset.php';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$a = include $file;
	return is_array( $a ) ? $a : array();
}

// Registered on every admin screen (cheap: nothing is printed until a screen enqueues it).
add_action(
	'admin_enqueue_scripts',
	function () {
		$a = mailweb_shell_asset();
		if ( ! $a ) {
			return;
		}
		$base = plugins_url( 'build/shell/', MAILWEB_FILE );
		wp_register_script( 'mailweb-shell', $base . 'index.js', $a['dependencies'] ?? array(), $a['version'] ?? false, true );
		wp_register_style( 'mailweb-shell', $base . 'index.css', wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-components', 'wp-theme' ) : array( 'wp-components' ), $a['version'] ?? false ); // after WordPress's component styles and design tokens (DataViews reads them): the Kits look on top
		wp_set_script_translations( 'mailweb-shell', 'mailweb' );
	},
	1
);

/**
 * Enqueue a plugin screen built on the shell. Call it from admin_enqueue_scripts, on the plugin's own screen only.
 *
 * @param array $args {
 *     The screen.
 *
 *     @type string $handle      The screen script's handle.
 *     @type string $src         Its URL.
 *     @type string $asset       Path of its .asset.php (dependencies and version from @wordpress/scripts).
 *     @type string $object      Global the data goes in (window.<object>).
 *     @type array  $data        The screen's data.
 *     @type string $text_domain Translations of the script.
 *     @type string $lang_path   Folder of the script's JSON translations (optional).
 * }
 * @return bool False when the shell, the screen script or a script they need is missing (the caller shows its current
 *              screen).
 */
function mailweb_shell_app( array $args ): bool {
	if ( ! wp_script_is( 'mailweb-shell', 'registered' ) || empty( $args['handle'] ) || empty( $args['src'] ) || empty( $args['asset'] ) || ! is_readable( $args['asset'] ) ) {
		return false;
	}
	$a     = include $args['asset'];
	$deps  = array_values( array_unique( array_merge( (array) ( $a['dependencies'] ?? array() ), array( 'mailweb-shell' ) ) ) );
	$shell = wp_scripts()->registered['mailweb-shell']->deps ?? array();
	// A script WordPress does not have (react-jsx-runtime is WordPress 6.6+) would leave the screen empty: show the current one.
	foreach ( array_merge( $deps, (array) $shell ) as $dep ) {
		if ( ! wp_script_is( $dep, 'registered' ) ) {
			return false;
		}
	}
	wp_enqueue_style( 'mailweb-shell' );
	wp_enqueue_script( $args['handle'], $args['src'], $deps, $a['version'] ?? false, true );
	if ( ! empty( $args['text_domain'] ) ) {
		wp_set_script_translations( $args['handle'], $args['text_domain'], $args['lang_path'] ?? '' );
	}
	if ( ! empty( $args['object'] ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $args['object'] ) ) {
		wp_add_inline_script( $args['handle'], 'window.' . $args['object'] . ' = ' . wp_json_encode( (array) ( $args['data'] ?? array() ) ) . ';', 'before' );
	}
	add_filter( 'admin_body_class', 'mailweb_shell_body_class' );
	mailweb_shell_screen_plain();
	return true;
}

/**
 * A shell screen has no Screen Options or Help tab: they belong to list tables and do nothing here.
 * Core's own switches: the screen_options_show_screen filter and WP_Screen::remove_help_tabs().
 */
function mailweb_shell_screen_plain(): void {
	add_filter( 'screen_options_show_screen', '__return_false', 99 );
	add_action(
		'admin_head',
		function () {
			$screen = get_current_screen();
			if ( $screen ) {
				$screen->remove_help_tabs();
			}
		},
		99
	);
}

/**
 * The body class that lets the shell run edge to edge.
 *
 * @param string $classes Admin body classes.
 */
function mailweb_shell_body_class( $classes ) {
	return trim( (string) $classes . ' kits-shell-screen' );
}

/**
 * The mount point of a shell screen, with a note when JavaScript does not run.
 *
 * @param string $id    Element id the script renders into.
 * @param string $title Screen title (the no-script heading).
 */
function mailweb_shell_mount( string $id, string $title ): void {
	// Notices from other plugins go after .wp-header-end, so they stay out of the app and its pinned header.
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><hr class="wp-header-end"><div id="' . esc_attr( $id ) . '">';
	echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'mailweb' ) . '</p></noscript>';
	echo '</div></div>';
}

/**
 * The permission check of a shell screen's REST route:
 * 401 when signed out; 403 when a browser request has no valid wp_rest nonce (an Application Password request has
 * none); 403 without the capability.
 *
 * @param string $cap Capability the route needs.
 * @return callable A permission_callback.
 */
function mailweb_shell_rest_can( string $cap ): callable {
	return function ( $request ) use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'mailweb' ), array( 'status' => 401 ) );
		}
		$app = function_exists( 'rest_get_authenticated_app_password' ) && null !== rest_get_authenticated_app_password();
		if ( ! $app ) {
			$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?? $request->get_param( '_wpnonce' ) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'rest_cookie_invalid_nonce', __( 'The page has expired. Reload it and try again.', 'mailweb' ), array( 'status' => 403 ) );
			}
		}
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'mailweb' ), array( 'status' => 403 ) );
		}
		return true;
	};
}


/**
 * Output as plain parts: { type: p | note | heading | list | table | notice, … }; forms and scripts are dropped.
 *
 * @param string $html Output.
 * @return array[]
 */
function mailweb_shell_html_blocks( string $html ): array {
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
	mailweb_shell_parts_blocks_walk( $root, $out );
	return $out;
}

/**
 * Walk a node's children into parts. Forms, inputs and scripts are left out; a details element is walked as its
 * content.
 *
 * With $forms (mailweb_shell_html_parts()), a form becomes a listtable or form part, a list's views a views part,
 * and a list table outside a form a listtable part.
 *
 * @param DOMNode $node  Node.
 * @param array   $out   Parts so far.
 * @param bool    $forms Keep forms and list tables as parts.
 */
function mailweb_shell_parts_blocks_walk( DOMNode $node, array &$out, bool $forms = false ): void {
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
				$list  = array_values( array_filter( mailweb_shell_parts_find( $c, 'table' ), fn( $t ) => mailweb_shell_parts_has_class( $t, 'wp-list-table' ) ) );
				$out[] = $list ? mailweb_shell_parts_listtable_block( $list[0], $c ) : mailweb_shell_parts_form_block( $c );
				continue;
			}
			if ( 'table' === $tag && false !== strpos( $class, ' wp-list-table ' ) ) {
				$flush();
				$out[] = mailweb_shell_parts_listtable_block( $c, null );
				continue;
			}
			if ( 'table' === $tag && false !== strpos( $class, ' form-table ' ) ) {
				$flush();
				mailweb_shell_parts_form_table( $c, $out ); // a settings page: each row is a heading, then its content
				continue;
			}
			if ( 'ul' === $tag && false !== strpos( $class, ' subsubsub ' ) ) {
				$flush();
				$out[] = mailweb_shell_parts_views_block( $c );
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
			$out[] = mailweb_shell_parts_table_block( $c );
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$items = array();
			foreach ( $c->childNodes as $li ) {
				if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
					$items[] = mailweb_shell_parts_cell( $li );
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
			) + mailweb_shell_parts_cell( $c );
		} elseif ( 'p' === $tag ) {
			$cell = mailweb_shell_parts_cell( $c );
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
			mailweb_shell_parts_blocks_walk( $c, $out, $forms ); // div, details, section …
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
function mailweb_shell_parts_cell( DOMNode $n ): array {
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
function mailweb_shell_parts_table_block( DOMElement $t ): array {
	$head = array();
	$rows = array();
	foreach ( $t->getElementsByTagName( 'tr' ) as $tr ) {
		$cells   = array();
		$in_head = 'thead' === strtolower( $tr->parentNode->nodeName );
		foreach ( $tr->childNodes as $td ) {
			if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
				$cells[] = mailweb_shell_parts_cell( $td );
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
 * Output with forms and list tables as plain parts: mailweb_shell_html_blocks()'s parts plus listtable and form.
 *
 * @param string $html Output.
 * @return array[]
 */
function mailweb_shell_html_parts( string $html ): array {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return mailweb_shell_html_blocks( $html );
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
	mailweb_shell_parts_blocks_walk( $root, $out, true );
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
function mailweb_shell_parts_has_class( DOMNode $n, string $cls ): bool {
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
function mailweb_shell_parts_find( DOMNode $n, string $tag, string $cls = '' ): array {
	$out = array();
	if ( ! $n instanceof DOMElement ) {
		return $out;
	}
	foreach ( $n->getElementsByTagName( $tag ) as $el ) {
		if ( '' === $cls || mailweb_shell_parts_has_class( $el, $cls ) ) {
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
function mailweb_shell_parts_text( DOMNode $n ): string {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return $n->textContent;
	}
	if ( ! $n instanceof DOMElement ) {
		return '';
	}
	$tag = strtolower( $n->nodeName );
	if ( in_array( $tag, array( 'script', 'style', 'button', 'select', 'textarea', 'noscript' ), true ) || mailweb_shell_parts_has_class( $n, 'row-actions' ) ) {
		return '';
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= mailweb_shell_parts_text( $c );
	}
	return 'br' === $tag ? ' ' : $t;
}

/**
 * A safe link address, or ''.
 *
 * @param string $href Address.
 */
function mailweb_shell_parts_safe_href( string $href ): string {
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
function mailweb_shell_parts_squash( string $t ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', $t ) );
}

/**
 * A settings table (table.form-table) outside a form: each row's heading as a heading part, then the row's content
 * (its forms among it) as parts.
 *
 * @param DOMElement $table The table.
 * @param array      $out   Parts so far.
 */
function mailweb_shell_parts_form_table( DOMElement $table, array &$out ): void {
	foreach ( mailweb_shell_parts_find( $table, 'tr' ) as $tr ) {
		foreach ( $tr->childNodes as $cell ) {
			if ( ! $cell instanceof DOMElement ) {
				continue;
			}
			if ( 'th' === strtolower( $cell->nodeName ) ) {
				$t = mailweb_shell_parts_squash( mailweb_shell_parts_text_no_sr( $cell ) );
				if ( '' !== $t ) {
					$out[] = array(
						'type' => 'heading',
						'text' => $t,
					);
				}
			} elseif ( 'td' === strtolower( $cell->nodeName ) ) {
				mailweb_shell_parts_blocks_walk( $cell, $out, true );
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
function mailweb_shell_parts_listtable_block( DOMElement $table, ?DOMElement $form ): array {
	$scope = $form ? $form : $table->parentNode;
	$part  = array(
		'type'    => 'listtable',
		'method'  => $form && 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => $form ? mailweb_shell_parts_safe_href( $form->getAttribute( 'action' ) ) : '',
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
		foreach ( mailweb_shell_parts_find( $form, 'input' ) as $in ) {
			if ( 'hidden' === strtolower( $in->getAttribute( 'type' ) ) && '' !== $in->getAttribute( 'name' ) && ! in_array( $in->getAttribute( 'name' ), $skip, true ) ) {
				$part['hidden'][] = array( $in->getAttribute( 'name' ), $in->getAttribute( 'value' ) );
			}
		}
		foreach ( mailweb_shell_parts_find( $form, 'p', 'search-box' ) as $box ) {
			foreach ( mailweb_shell_parts_find( $box, 'input' ) as $in ) {
				if ( 'search' === strtolower( $in->getAttribute( 'type' ) ) ) {
					$label          = mailweb_shell_parts_find( $box, 'label' );
					$part['search'] = array(
						'name'  => $in->getAttribute( 'name' ),
						'value' => $in->getAttribute( 'value' ),
						'label' => $label ? mailweb_shell_parts_squash( $label[0]->textContent ) : '',
					);
				}
			}
		}
	}
	// The top bar: bulk actions, filters, the count and pages.
	foreach ( mailweb_shell_parts_find( $scope, 'div', 'tablenav' ) as $nav ) {
		if ( ! mailweb_shell_parts_has_class( $nav, 'top' ) ) {
			continue;
		}
		foreach ( mailweb_shell_parts_find( $nav, 'select' ) as $sel ) {
			$name = $sel->getAttribute( 'name' );
			$opts = array();
			$cur  = '';
			foreach ( mailweb_shell_parts_find( $sel, 'option' ) as $o ) {
				$opts[] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => mailweb_shell_parts_squash( $o->textContent ),
				);
				if ( $o->hasAttribute( 'selected' ) ) {
					$cur = $o->getAttribute( 'value' );
				}
			}
			if ( 'action' === $name ) {
				$part['bulk'] = array_values( array_filter( $opts, fn( $o ) => '-1' !== $o['value'] && '' !== $o['value'] ) );
			} elseif ( '' !== $name && 'action2' !== $name ) {
				$label = '';
				foreach ( mailweb_shell_parts_find( $nav, 'label' ) as $l ) {
					if ( $l->getAttribute( 'for' ) === $sel->getAttribute( 'id' ) ) {
						$label = mailweb_shell_parts_squash( $l->textContent );
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
		foreach ( mailweb_shell_parts_find( $nav, 'span', 'displaying-num' ) as $s ) {
			$part['total'] = (int) preg_replace( '/\D/', '', $s->textContent );
		}
		foreach ( mailweb_shell_parts_find( $nav, 'span', 'total-pages' ) as $s ) {
			$part['pages'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		foreach ( mailweb_shell_parts_find( $nav, 'input', 'current-page' ) as $s ) {
			$part['page'] = max( 1, (int) $s->getAttribute( 'value' ) );
		}
		foreach ( mailweb_shell_parts_find( $nav, 'span', 'current-page' ) as $s ) { // the pages with only one page of links
			$part['page'] = max( 1, (int) preg_replace( '/\D/', '', $s->textContent ) );
		}
		break;
	}
	// Columns from the head.
	$keys = array();
	foreach ( mailweb_shell_parts_find( $table, 'thead' ) as $head ) {
		foreach ( mailweb_shell_parts_find( $head, 'tr' ) as $tr ) {
			foreach ( $tr->childNodes as $th ) {
				if ( ! $th instanceof DOMElement || ! in_array( strtolower( $th->nodeName ), array( 'th', 'td' ), true ) ) {
					continue;
				}
				if ( mailweb_shell_parts_has_class( $th, 'check-column' ) ) {
					continue;
				}
				$key = $th->getAttribute( 'id' );
				if ( '' === $key && preg_match( '/\bcolumn-([a-z0-9_-]+)/i', $th->getAttribute( 'class' ), $m ) ) {
					$key = $m[1];
				}
				$label = '';
				foreach ( $th->childNodes as $c ) {
					if ( $c instanceof DOMElement && mailweb_shell_parts_has_class( $c, 'screen-reader-text' ) ) {
						continue;
					}
					$label .= $c instanceof DOMElement ? mailweb_shell_parts_text_no_sr( $c ) : $c->textContent;
				}
				$a      = mailweb_shell_parts_find( $th, 'a' );
				$sorted = mailweb_shell_parts_has_class( $th, 'sorted' ) ? ( mailweb_shell_parts_has_class( $th, 'asc' ) ? 'asc' : 'desc' ) : '';
				$col    = array(
					'key'     => sanitize_key( $key ),
					'label'   => mailweb_shell_parts_squash( $label ),
					'primary' => mailweb_shell_parts_has_class( $th, 'column-primary' ),
				);
				if ( $a && '' !== mailweb_shell_parts_safe_href( $a[0]->getAttribute( 'href' ) ) ) {
					$col['sortHref'] = mailweb_shell_parts_safe_href( $a[0]->getAttribute( 'href' ) );
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
	foreach ( mailweb_shell_parts_find( $table, 'tbody' ) as $body ) {
		foreach ( $body->childNodes as $tr ) {
			if ( ! $tr instanceof DOMElement || 'tr' !== strtolower( $tr->nodeName ) ) {
				continue;
			}
			if ( mailweb_shell_parts_has_class( $tr, 'no-items' ) ) {
				$part['empty'] = mailweb_shell_parts_squash( mailweb_shell_parts_text( $tr ) );
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
				if ( mailweb_shell_parts_has_class( $td, 'check-column' ) ) {
					foreach ( mailweb_shell_parts_find( $td, 'input' ) as $cb ) {
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
				$cell = array( 'text' => mailweb_shell_parts_squash( mailweb_shell_parts_text( $td ) ) );
				foreach ( mailweb_shell_parts_find( $td, 'a' ) as $a ) {
					if ( mailweb_shell_parts_in_row_actions( $a, $td ) ) {
						continue;
					}
					$href = mailweb_shell_parts_safe_href( $a->getAttribute( 'href' ) );
					if ( '' !== $href ) {
						$cell['href']     = $href;
						$cell['external'] = '_blank' === $a->getAttribute( 'target' );
					}
					break;
				}
				foreach ( mailweb_shell_parts_find( $td, 'div', 'row-actions' ) as $ra ) {
					// Words in the row's links line that are not a link ("; each item's FAQ box") stay as a note.
					$words = mailweb_shell_parts_text_no_sr( $ra );
					foreach ( mailweb_shell_parts_find( $ra, 'a' ) as $a ) {
						$words = str_replace( $a->textContent, ' ', $words );
					}
					$words = trim( mailweb_shell_parts_squash( $words ), " \t|;,·" );
					if ( '' !== $words ) {
						$cell['note'] = $words;
					}
				}
				$row['cells'][ $key ] = $cell;
				foreach ( mailweb_shell_parts_find( $td, 'div', 'row-actions' ) as $ra ) {
					foreach ( mailweb_shell_parts_find( $ra, 'a' ) as $a ) {
						$href = mailweb_shell_parts_safe_href( $a->getAttribute( 'href' ) );
						$text = mailweb_shell_parts_squash( mailweb_shell_parts_text_no_sr( $a ) );
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
function mailweb_shell_parts_text_no_sr( DOMNode $n ): string {
	if ( $n instanceof DOMElement && mailweb_shell_parts_has_class( $n, 'screen-reader-text' ) ) {
		return '';
	}
	if ( ! $n instanceof DOMElement ) {
		return $n->textContent;
	}
	$t = '';
	foreach ( $n->childNodes as $c ) {
		$t .= mailweb_shell_parts_text_no_sr( $c );
	}
	return $t;
}

/**
 * Whether a link sits in a cell's row actions.
 *
 * @param DOMNode $a  Link.
 * @param DOMNode $td Cell.
 */
function mailweb_shell_parts_in_row_actions( DOMNode $a, DOMNode $td ): bool {
	for ( $p = $a->parentNode; $p && $p !== $td; $p = $p->parentNode ) {
		if ( mailweb_shell_parts_has_class( $p, 'row-actions' ) ) {
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
function mailweb_shell_parts_views_block( DOMElement $ul ): array {
	$views = array();
	foreach ( mailweb_shell_parts_find( $ul, 'a' ) as $i => $a ) {
		$count = '';
		foreach ( mailweb_shell_parts_find( $a, 'span', 'count' ) as $c ) {
			$count = preg_replace( '/[^\d,.]/', '', $c->textContent );
			$c->parentNode->removeChild( $c );
		}
		$href = mailweb_shell_parts_safe_href( $a->getAttribute( 'href' ) );
		if ( '' === $href ) {
			continue;
		}
		$views[] = array(
			'value'   => 'v' . $i,
			'label'   => mailweb_shell_parts_squash( $a->textContent ),
			'count'   => $count,
			'href'    => $href,
			'current' => mailweb_shell_parts_has_class( $a, 'current' ) || 'page' === $a->getAttribute( 'aria-current' ),
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
function mailweb_shell_parts_form_block( DOMElement $form ): array {
	$part   = array(
		'type'    => 'form',
		'method'  => 'post' === strtolower( $form->getAttribute( 'method' ) ) ? 'post' : 'get',
		'action'  => mailweb_shell_parts_safe_href( $form->getAttribute( 'action' ) ),
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
					'label'   => mailweb_shell_parts_squash( $el->textContent ),
					'primary' => mailweb_shell_parts_has_class( $el, 'button-primary' ),
				);
			}
			continue;
		}
		if ( 'submit' === $type ) {
			$part['buttons'][] = array(
				'name'    => $name,
				'value'   => $el->getAttribute( 'value' ),
				'label'   => $el->getAttribute( 'value' ),
				'primary' => mailweb_shell_parts_has_class( $el, 'button-primary' ),
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
		$label = mailweb_shell_parts_field_label( $el, $xpath );
		$help  = '';
		foreach ( $xpath->query( 'ancestor::td[1]//p[contains(concat(" ", normalize-space(@class), " "), " description ")]', $el ) as $d ) {
			$help = mailweb_shell_parts_squash( $d->textContent );
		}
		if ( 'radio' === $type ) {
			if ( ! isset( $radios[ $name ] ) ) {
				$radios[ $name ]  = count( $part['fields'] );
				$part['fields'][] = array(
					'type'    => 'radio',
					'name'    => $name,
					'label'   => mailweb_shell_parts_group_label( $el, $xpath ),
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
			foreach ( mailweb_shell_parts_find( $el, 'option' ) as $o ) {
				$field['options'][] = array(
					'value' => $o->getAttribute( 'value' ),
					'label' => mailweb_shell_parts_squash( $o->textContent ),
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
	mailweb_shell_parts_blocks_walk( $form, $part['blocks'], false );
	return $part;
}

/**
 * A field's label: its own label, the row's heading, its aria-label, placeholder or name.
 *
 * @param DOMElement $el    Field.
 * @param DOMXPath   $xpath XPath.
 */
function mailweb_shell_parts_field_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::label[1]', $el ) as $l ) {
		$t = mailweb_shell_parts_squash( mailweb_shell_parts_text_no_sr( $l ) );
		if ( '' !== $t ) {
			return $t;
		}
	}
	$id = $el->getAttribute( 'id' );
	if ( '' !== $id ) {
		foreach ( $xpath->query( '//label[@for="' . str_replace( '"', '', $id ) . '"]' ) as $l ) {
			return mailweb_shell_parts_squash( $l->textContent );
		}
	}
	foreach ( $xpath->query( 'ancestor::tr[1]/th', $el ) as $th ) {
		return mailweb_shell_parts_squash( $th->textContent );
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
function mailweb_shell_parts_group_label( DOMElement $el, DOMXPath $xpath ): string {
	foreach ( $xpath->query( 'ancestor::fieldset[1]/legend|ancestor::tr[1]/th', $el ) as $l ) {
		return mailweb_shell_parts_squash( $l->textContent );
	}
	return $el->getAttribute( 'name' );
}
