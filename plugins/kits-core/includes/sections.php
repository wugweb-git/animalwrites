<?php
/**
 * Every section labelled (shell spec): each full-width or wide group in the page becomes a <section> with a kebab-case
 * id (unique on the page) and an accessible name — aria-labelledby its first heading when that heading always renders
 * outside a list, otherwise aria-label. Headings inside lists (cards in a query loop) never carry ids.
 * A site can name sections without a visible heading with the kits_section_labels filter (class => [id, label]).
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A unique id on this page. */
function kits_section_id( $base ) {
	static $used = array();
	$id = sanitize_title( $base );
	$id = $id ? $id : 'section';
	$n  = $id;
	for ( $i = 2; isset( $used[ $n ] ); $i++ ) {
		$n = $id . '-' . $i;
	}
	$used[ $n ] = true;
	return $n;
}

add_filter(
	'render_block_core/group',
	function ( $html, $block ) {
		$align = $block['attrs']['align'] ?? '';
		if ( is_admin() || ! in_array( $align, array( 'full', 'wide' ), true ) || ! preg_match( '/^\s*<(div|header)\b([^>]*)>/', $html, $open ) ) {
			return $html;
		}
		if ( 'header' === $open[1] || false !== strpos( $open[2], 'kit-site-' ) || false !== strpos( $open[2], 'aria-label' ) ) {
			return $html; // page header bands keep <header>; site header and footer are landmarks already
		}
		$labels = (array) apply_filters( 'kits_section_labels', array() );
		$id     = '';
		$label  = '';
		foreach ( $labels as $class => $def ) {
			if ( preg_match( '/class="[^"]*\b' . preg_quote( $class, '/' ) . '\b/', $open[2] ) ) {
				list( $id, $label ) = $def;
				break;
			}
		}
		// The first h1/h2 that comes before any list in this section names it.
		$before = (string) strstr( $html, '<li', true );
		$before = '' === $before ? $html : $before;
		$attrs  = '';
		if ( preg_match( '/<(h[12])\b([^>]*)>(.*?)<\/\1>/s', $before, $h ) ) {
			$text = trim( wp_strip_all_tags( $h[3] ) );
			if ( preg_match( '/\bid="([^"]+)"/', $h[2], $hid ) ) {
				$hid = $hid[1];
			} else {
				$hid  = kits_section_id( ( $id ? $id : $text ) . '-title' );
				$html = preg_replace( '/<' . $h[1] . '\b' . preg_quote( $h[2], '/' ) . '>/', '<' . $h[1] . ' id="' . esc_attr( $hid ) . '"' . $h[2] . '>', $html, 1 );
			}
			$id    = $id ? $id : $text;
			$attrs = ' aria-labelledby="' . esc_attr( $hid ) . '"';
		} elseif ( $label ) {
			$attrs = ' aria-label="' . esc_attr( $label ) . '"';
		} else {
			return $html; // nothing to name it by: leave as a plain group
		}
		$sid  = false !== strpos( $open[2], ' id="' ) ? '' : ' id="' . esc_attr( kits_section_id( $id ) ) . '"';
		$html = preg_replace( '/^(\s*)<div\b/', '$1<section' . $sid . $attrs, $html, 1 );
		return (string) preg_replace( '/<\/div>(\s*)$/', '</section>$1', $html, 1 );
	},
	20,
	2
);
