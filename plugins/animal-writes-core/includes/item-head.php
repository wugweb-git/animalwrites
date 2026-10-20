<?php
/**
 * News and Library item pages (owner, 20 Oct 2026): one order, each element once.
 *
 * Breadcrumb (Home › Section › primary category) › H1 › meta row › image › summary › source line › share › ad › related.
 *
 * - [aw_item_meta]   one row: publisher logo + name (linked to the original, with the "also reported by" logos) ·
 *                    the original's date (j M Y, calendar icon) · chips (Library type first, then the categories).
 *                    The publisher, the date and the categories appear nowhere else on the page.
 * - [aw_source_line] plain text, no card: "Read the original ↗", the summary note and the Editorial policy link.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Item types that use this order. */
const AW_ITEM_HEAD_TYPES = array( 'aw_news', 'aw_legal' );

/**
 * An item's primary category: the one chosen in Rank Math, else its first category.
 *
 * @param int $id Post ID.
 * @return WP_Term|null
 */
function aw_primary_category( $id ) {
	$pid = (int) get_post_meta( $id, 'rank_math_primary_category', true );
	$t   = $pid ? get_term( $pid, 'category' ) : null;
	if ( $t instanceof WP_Term && has_term( $t->term_id, 'category', $id ) ) {
		return $t;
	}
	return aw_first_term( $id, 'category' );
}

/** Whether this shortcode runs for the page's own item (not a card in a list). */
function aw_item_head_here() {
	return is_singular( AW_ITEM_HEAD_TYPES ) && get_the_ID() === get_queried_object_id();
}

add_shortcode(
	'aw_item_meta',
	function () {
		if ( ! aw_item_head_here() ) {
			return '';
		}
		$id    = get_queried_object_id();
		$parts = array();
		$src   = do_shortcode( '[aw_source_row link="1"]' ); // same-story.php adds the "also reported by" logos
		if ( '' !== $src ) {
			// Inline elements only: the shortcode block runs wpautop, which would open a stray <p> after a <p>, <div> or <ul>.
			$src = preg_replace( array( '#^<(?:p|div) class="#', '#</(?:p|div)>$#', '#<ul class="aw-src-also"#', '#</ul>#', '#<li>#', '#</li>#' ), array( '<span class="', '</span>', '<span role="list" class="aw-src-also"', '</span>', '<span role="listitem">', '</span>' ), trim( $src ) );
			$parts[] = $src;
		}
		$day     = (string) get_post_meta( $id, 'aw_source_date', true );
		$parts[] = aw_date( $day ? $day . ' 12:00:00' : (int) get_post_time( 'U', true, $id ) ); // the original's date, absolute
		$chips   = '';
		if ( 'aw_legal' === get_post_type( $id ) && function_exists( 'aw_legal_kinds' ) ) {
			foreach ( aw_legal_kinds( $id ) as $k ) {
				$chips .= '<a class="kit-tag" href="' . esc_url( get_term_link( $k ) ) . '" rel="tag">' . ( function_exists( 'aw_type_icon' ) ? aw_type_icon( 'lib-' . $k->slug ) : '' ) . esc_html( $k->name ) . '</a>';
			}
		}
		$cats = get_the_term_list( $id, 'category', '', '', '' );
		$cats = is_wp_error( $cats ) ? '' : (string) $cats;
		if ( '' !== $chips . $cats ) {
			$parts[] = '<span class="aw-item-meta-chips wp-block-post-terms taxonomy-category">' . $chips . $cats . '</span>';
		}
		$sep = '<span class="aw-item-meta-sep" aria-hidden="true">·</span>';
		return '<div class="aw-item-meta">' . implode( $sep, $parts ) . '</div>';
	}
);

add_shortcode(
	'aw_source_line',
	function () {
		if ( ! aw_item_head_here() ) {
			return '';
		}
		$id   = get_queried_object_id();
		$url  = (string) get_post_meta( $id, 'aw_source_url', true );
		$out  = '';
		if ( $url ) {
			$out .= '<a class="aw-source-line-read" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">Read the original <span aria-hidden="true">↗</span><span class="screen-reader-text"> (opens in a new tab)</span></a> ';
		}
		// Library: the body that issued the document, when it is not the publisher already named in the meta row.
		$iss = 'aw_legal' === get_post_type( $id ) && function_exists( 'aw_item_issuer' ) ? aw_item_issuer( $id ) : null;
		if ( $iss && function_exists( 'aw_publisher_of' ) && aw_publisher_of( $id )[0] !== $iss->name ) {
			$out .= esc_html( sprintf( 'Issued by %s.', $iss->name ) ) . ' ';
		}
		$out .= 'Summary written by Animal Writes. Read the original before relying on it. <a href="/editorial-policy/">Editorial policy</a>';
		return '<p class="aw-source-line">' . $out . '</p>';
	}
);

// Categories are in the meta row, so the end bar holds the share buttons only.
add_filter( 'kits_endbar_taxonomy', fn( $t, $type ) => in_array( $type, AW_ITEM_HEAD_TYPES, true ) ? '' : $t, 20, 2 );
// The end-of-article ad follows the share buttons.
add_filter( 'kits_ads_end_after_share_types', fn( $t ) => array_merge( (array) $t, AW_ITEM_HEAD_TYPES ) );
