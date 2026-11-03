<?php
/**
 * Markdown for agents (owner, 3 Nov 2026): a request whose Accept header prefers text/markdown over text/html gets the
 * page as Markdown. Home and single front-end pages (items, pages, guides) only; everything else, and every browser
 * (browsers never ask for Markdown), gets the HTML as before.
 *
 * - The page is rendered exactly as for a browser; its main region (<main>) becomes Markdown: the title (H1), the
 *   page's summary (its meta description) as a quote, then headings, paragraphs, lists, quotes, tables, links and
 *   pictures (alt text), with full addresses. Navigation, the footer, forms, buttons, scripts, styles and hidden parts are left out.
 * - Headers: Content-Type text/markdown; charset=utf-8, Vary: Accept, Cache-Control no-store, private (and LiteSpeed
 *   no-cache) and a canonical Link to the HTML page, so no shared cache keeps the Markdown or hands it to a browser. The HTML keeps its own cache headers
 *   (cdn-cache.php); while the CDN holds a page's HTML, an agent asking for Markdown gets that HTML.
 * - Password-protected pages, previews and logged-in requests are never converted.
 * - Off with the "Markdown for agents" switch in AW SEO Plus → Settings.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument's own property names (childNodes, tagName, nodeValue).

/**
 * Whether an Accept header prefers text/markdown to text/html (q values compared; markdown must be named).
 *
 * @param string $accept Accept header.
 */
function aw_md_accept_prefers( string $accept ): bool {
	$q = array();
	foreach ( explode( ',', strtolower( $accept ) ) as $part ) {
		$bits = array_map( 'trim', explode( ';', $part ) );
		$type = (string) array_shift( $bits );
		$val  = 1.0;
		foreach ( $bits as $b ) {
			if ( 0 === strpos( $b, 'q=' ) ) {
				$val = (float) substr( $b, 2 );
			}
		}
		if ( '' !== $type ) {
			$q[ $type ] = max( $q[ $type ] ?? 0.0, $val );
		}
	}
	$md = max( $q['text/markdown'] ?? 0.0, $q['text/x-markdown'] ?? 0.0 );
	if ( $md <= 0 ) {
		return false;
	}
	$html = max( $q['text/html'] ?? 0.0, $q['application/xhtml+xml'] ?? 0.0, $q['text/*'] ?? 0.0, $q['*/*'] ?? 0.0 );
	return $md > $html || ( $md === $html && ! isset( $q['text/html'] ) );
}

/** Whether this request gets Markdown. */
function aw_md_wanted(): bool {
	if ( 'get' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || is_admin() || is_feed() || is_preview() || is_embed() || is_404() || is_user_logged_in() ) {
		return false;
	}
	if ( ! ( is_front_page() || is_singular() ) || ( is_singular() && post_password_required() ) ) {
		return false;
	}
	$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
	return '' !== $accept && aw_md_accept_prefers( $accept );
}

/**
 * A full address for a link or picture: relative ones are joined to the site address.
 *
 * @param string $url Address from the page.
 */
function aw_md_url( string $url ): string {
	$url = trim( $url );
	if ( '' === $url || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
		return esc_url_raw( $url );
	}
	$home = untrailingslashit( home_url() );
	if ( 0 === strpos( $url, '//' ) ) {
		return esc_url_raw( 'https:' . $url );
	}
	return esc_url_raw( $home . ( 0 === strpos( $url, '/' ) ? '' : '/' ) . $url );
}

/**
 * Text of a node with spaces collapsed.
 *
 * @param string $s Text.
 */
function aw_md_text( string $s ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * Inline Markdown of a node's children: text, links, emphasis, code, line breaks, pictures.
 *
 * @param DOMNode $node Node.
 */
function aw_md_inline( DOMNode $node ): string {
	$out = '';
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			$out .= preg_replace( '/\s+/u', ' ', $c->nodeValue );
			continue;
		}
		if ( ! $c instanceof DOMElement || aw_md_skip( $c ) ) {
			continue;
		}
		$tag = strtolower( $c->tagName );
		if ( '' !== $out && ! preg_match( '/\s$/u', $out ) && in_array( $tag, array( 'a', 'img', 'time', 'div', 'p', 'li', 'figure', 'figcaption' ), true ) ) {
			$out .= ' '; // links, pictures and boxes side by side are separate words.
		}
		if ( 'a' === $tag ) {
			$text = aw_md_text( aw_md_inline( $c ) );
			$href = trim( $c->getAttribute( 'href' ) );
			if ( '' === $text ) {
				$text = aw_md_text( $c->getAttribute( 'aria-label' ) );
			}
			if ( '' === $text ) {
				continue;
			}
			$out .= ( '' !== $href && 0 !== strpos( $href, '#' ) && 0 !== stripos( $href, 'javascript:' ) ) ? '[' . str_replace( array( '[', ']' ), array( '\[', '\]' ), $text ) . '](' . aw_md_url( $href ) . ')' : $text;
		} elseif ( in_array( $tag, array( 'strong', 'b' ), true ) ) {
			$t    = aw_md_text( aw_md_inline( $c ) );
			$out .= '' !== $t ? '**' . $t . '**' : '';
		} elseif ( in_array( $tag, array( 'em', 'i' ), true ) ) {
			$t    = aw_md_text( aw_md_inline( $c ) );
			$out .= '' !== $t ? '*' . $t . '*' : '';
		} elseif ( 'code' === $tag ) {
			$out .= '`' . aw_md_text( $c->textContent ) . '`';
		} elseif ( 'br' === $tag ) {
			$out .= "  \n";
		} elseif ( 'img' === $tag ) {
			$alt  = aw_md_text( $c->getAttribute( 'alt' ) );
			$src  = $c->getAttribute( 'src' );
			$out .= ( '' !== $alt && '' !== $src ) ? '![' . $alt . '](' . aw_md_url( $src ) . ')' : '';
		} else {
			$out .= aw_md_inline( $c );
		}
	}
	return $out;
}

/**
 * Parts left out: navigation, chrome, forms, controls, scripts, hidden and screen-reader-only parts.
 *
 * @param DOMElement $el Element.
 */
function aw_md_skip( DOMElement $el ): bool {
	$tag = strtolower( $el->tagName );
	if ( 'button' === $tag && $el->parentNode instanceof DOMElement && preg_match( '/^h[1-6]$/i', $el->parentNode->tagName ) ) {
		return false; // an accordion question (a button inside a heading) keeps its words.
	}
	if ( in_array( $tag, array( 'nav', 'footer', 'aside', 'form', 'button', 'input', 'select', 'textarea', 'label', 'script', 'style', 'noscript', 'template', 'svg', 'iframe', 'video', 'audio', 'canvas', 'dialog' ), true ) ) {
		return true;
	}
	if ( $el->hasAttribute( 'hidden' ) || 'true' === $el->getAttribute( 'aria-hidden' ) ) {
		return true;
	}
	return (bool) preg_match( '/(^|\s)(screen-reader-text|skip-link|wp-block-buttons|wp-block-search|kit-breadcrumbs|kit-crumbs|kit-share|aw-item-share|aw-sticky-cta)(\s|$)/', $el->getAttribute( 'class' ) );
}

/**
 * Block Markdown of a node's children.
 *
 * @param DOMNode $node  Node.
 * @param int     $depth List depth.
 */
function aw_md_blocks( DOMNode $node, int $depth = 0 ): string {
	$out    = '';
	$inline = '';
	$flush  = function () use ( &$out, &$inline ) {
		$t = aw_md_text( $inline );
		if ( '' !== $t ) {
			$out .= $t . "\n\n";
		}
		$inline = '';
	};
	foreach ( $node->childNodes as $c ) {
		if ( XML_TEXT_NODE === $c->nodeType ) {
			$inline .= $c->nodeValue;
			continue;
		}
		if ( ! $c instanceof DOMElement || aw_md_skip( $c ) ) {
			continue;
		}
		$tag = strtolower( $c->tagName );
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			$flush();
			$t = aw_md_text( aw_md_inline( $c ) );
			if ( '' !== $t ) {
				$out .= str_repeat( '#', (int) $m[1] ) . ' ' . $t . "\n\n";
			}
		} elseif ( 'p' === $tag ) {
			$flush();
			$t = trim( (string) preg_replace( '/[ \t]+/u', ' ', aw_md_inline( $c ) ) );
			if ( '' !== $t ) {
				$out .= $t . "\n\n";
			}
		} elseif ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
			$flush();
			$n = 0;
			foreach ( $c->childNodes as $li ) {
				if ( ! $li instanceof DOMElement || 'li' !== strtolower( $li->tagName ) || aw_md_skip( $li ) ) {
					continue;
				}
				++$n;
				$sub  = '';
				$text = '';
				foreach ( $li->childNodes as $part ) {
					if ( $part instanceof DOMElement && in_array( strtolower( $part->tagName ), array( 'ul', 'ol' ), true ) ) {
						$sub .= aw_md_blocks( $li->ownerDocument->createElement( 'div' )->appendChild( $part->cloneNode( true ) )->parentNode, $depth + 1 );
					} elseif ( $part instanceof DOMElement && in_array( strtolower( $part->tagName ), array( 'div', 'article', 'p', 'h2', 'h3', 'h4', 'figure' ), true ) ) {
						$text .= ' ' . aw_md_text( aw_md_inline( $part ) );
					} elseif ( XML_TEXT_NODE === $part->nodeType ) {
						$text .= $part->nodeValue;
					} elseif ( $part instanceof DOMElement && ! aw_md_skip( $part ) ) {
						$wrap = $li->ownerDocument->createElement( 'span' );
						$wrap->appendChild( $part->cloneNode( true ) );
						$text .= aw_md_inline( $wrap );
					}
				}
				$text = aw_md_text( $text );
				if ( '' !== $text ) {
					$out .= str_repeat( '  ', $depth ) . ( 'ol' === $tag ? $n . '. ' : '- ' ) . $text . "\n";
				}
				$out .= $sub;
			}
			$out .= 0 === $depth ? "\n" : '';
		} elseif ( 'blockquote' === $tag ) {
			$flush();
			$q = trim( aw_md_blocks( $c, $depth ) );
			if ( '' !== $q ) {
				$out .= '> ' . str_replace( "\n", "\n> ", $q ) . "\n\n";
			}
		} elseif ( 'table' === $tag ) {
			$flush();
			$rows = array();
			foreach ( $c->getElementsByTagName( 'tr' ) as $tr ) {
				$cells = array();
				foreach ( $tr->childNodes as $td ) {
					if ( $td instanceof DOMElement && in_array( strtolower( $td->tagName ), array( 'td', 'th' ), true ) ) {
						$cells[] = str_replace( '|', '\|', aw_md_text( aw_md_inline( $td ) ) );
					}
				}
				if ( $cells ) {
					$rows[] = $cells;
				}
			}
			if ( $rows ) {
				$cols = max( array_map( 'count', $rows ) );
				foreach ( $rows as $i => $r ) {
					$out .= '| ' . implode( ' | ', array_pad( $r, $cols, '' ) ) . " |\n";
					if ( 0 === $i ) {
						$out .= '|' . str_repeat( ' --- |', $cols ) . "\n";
					}
				}
				$out .= "\n";
			}
		} elseif ( in_array( $tag, array( 'a', 'strong', 'b', 'em', 'i', 'code', 'span', 'time', 'br', 'img', 'small', 'abbr', 'mark', 'sup', 'sub' ), true ) ) {
			$wrap = $c->ownerDocument->createElement( 'span' );
			$wrap->appendChild( $c->cloneNode( true ) );
			$inline .= aw_md_inline( $wrap ) . ' ';
		} elseif ( 'hr' === $tag ) {
			$flush();
			$out .= "---\n\n";
		} else {
			$flush();
			$out .= aw_md_blocks( $c, $depth ); // a container: div, section, article, figure.
		}
	}
	$flush();
	return $out;
}

/**
 * The page as Markdown, from its rendered HTML.
 *
 * @param string $html Whole page.
 * @return string Markdown, or '' when the page has no main region.
 */
function aw_md_from_html( string $html ): string {
	if ( ! class_exists( 'DOMDocument' ) || '' === trim( $html ) ) {
		return '';
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	$main = $doc->getElementsByTagName( 'main' )->item( 0 );
	if ( ! $main ) {
		return '';
	}
	$xp      = new DOMXPath( $doc );
	$desc    = $xp->query( '//meta[@name="description"]/@content' )->item( 0 );
	$summary = $desc ? aw_md_text( (string) $desc->nodeValue ) : '';
	$body    = aw_md_blocks( $main );
	$body    = (string) preg_replace( "/\n{3,}/", "\n\n", $body );
	$h1      = preg_match( '/^# .*\n\n/', $body, $m ) ? $m[0] : '';
	if ( '' === $h1 ) {
		$title = $doc->getElementsByTagName( 'title' )->item( 0 );
		$title = $title ? aw_md_text( (string) $title->textContent ) : '';
		$h1    = '' !== $title ? '# ' . $title . "\n\n" : ''; // the page opens with something else (Home): its title first
		$body  = $h1 . $body;
	}
	if ( '' !== $summary ) {
		$body = $h1 . '> ' . $summary . "\n\n" . substr( $body, strlen( $h1 ) );
	}
	return trim( $body ) . "\n";
}

add_action(
	'template_redirect',
	function () {
		if ( ! aw_md_wanted() ) {
			return;
		}
		ob_start(
			function ( $html ) {
				$md = aw_md_from_html( (string) $html );
				if ( '' === $md || headers_sent() ) {
					return $html; // no main region: the HTML as it was.
				}
				header( 'Content-Type: text/markdown; charset=utf-8', true );
				header( 'Cache-Control: no-store, private', true );
				header( 'X-LiteSpeed-Cache-Control: no-cache', true );
				header( 'Vary: Accept', false );
				header_remove( 'X-AW-CDN' );
				header_remove( 'Link' ); // the HTML's preloads mean nothing to a Markdown reader.
				if ( preg_match( '/<link rel="canonical" href="([^"]+)"/', (string) $html, $m ) ) {
					header( 'Link: <' . esc_url_raw( html_entity_decode( $m[1] ) ) . '>; rel="canonical"', false ); // the HTML page is the one to index.
				}
				return $md;
			}
		);
	},
	100000 // after cdn-cache.php (99999), whose header is replaced here.
);
