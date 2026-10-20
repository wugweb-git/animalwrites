<?php
/**
 * News and Library item pages (owner, 20 Oct 2026): one order, each element once.
 *
 * Breadcrumb (Home › Section › primary category) › H1 › meta row › image › summary › source line › tags › share › ad ›
 * related.
 *
 * - [aw_item_meta]   one row: the original's date (j M Y, calendar icon), category chips, the publisher chip (favicon +
 *                    name, linked to the original, then the "also reported by" logos). Spacing is the CSS gap only.
 *                    The publisher, the date and the categories appear nowhere else on the page.
 * - [aw_item_tags]   the other labels (Library types, tags) as chips, after the source line.
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

/**
 * A summary up to its last full sentence (owner, 20 Oct 2026): a tail cut off mid-sentence ("…of Kerala's Th") is
 * dropped, never cut mid-word. Only the first paragraph is checked (Library summaries carry a note on a line of their
 * own). Text without any full sentence is returned as it is.
 *
 * @param string $text Summary.
 * @return string
 */
function aw_whole_sentences( $text ) {
	$lines = explode( "\n", trim( (string) $text ) );
	$first = trim( $lines[0] );
	if ( '' !== $first && ! preg_match( '/[.!?…]["\'”’)\]]*$/u', $first ) && preg_match_all( '/[.!?…]["\'”’)\]]*(?=\s|$)/u', $first, $m, PREG_OFFSET_CAPTURE ) ) {
		$last     = end( $m[0] );
		$lines[0] = substr( $first, 0, $last[1] + strlen( $last[0] ) );
	}
	return implode( "\n", $lines );
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
		$id  = get_queried_object_id();
		$day = (string) get_post_meta( $id, 'aw_source_date', true );
		$row = aw_date( $day ? $day . ' 12:00:00' : (int) get_post_time( 'U', true, $id ) ); // the original's date, absolute
		// Each chip is its own item of the row, so the row wraps between chips only (owner, 20 Oct 2026).
		$cats = get_the_terms( $id, 'category' );
		foreach ( ( $cats && ! is_wp_error( $cats ) ) ? $cats : array() as $t ) {
			$row .= '<a class="kit-chip" href="' . esc_url( get_term_link( $t ) ) . '" rel="tag">' . esc_html( $t->name ) . '</a>';
		}
		$src = do_shortcode( '[aw_source_row link="1"]' ); // the publisher chip; same-story.php adds the "also reported by" logos
		if ( '' !== $src ) {
			// Inline elements only: the shortcode block runs wpautop, which would open a stray <p> after a <p>, <div> or <ul>.
			$src = preg_replace( array( '#^<(?:p|div) class="#', '#</(?:p|div)>$#', '#<ul class="aw-src-also"#', '#</ul>#', '#<li>#', '#</li>#' ), array( '<span class="', '</span>', '<span role="list" class="aw-src-also"', '</span>', '<span role="listitem">', '</span>' ), trim( $src ) );
			$row .= $src;
		}
		return '<div class="aw-item-meta">' . $row . '</div>';
	}
);

// Labels that are not categories (Library types, other tags), after the source line, as chips with their icons.
add_shortcode(
	'aw_item_tags',
	function () {
		if ( ! aw_item_head_here() ) {
			return '';
		}
		$tags = get_the_terms( get_queried_object_id(), 'post_tag' );
		if ( ! $tags || is_wp_error( $tags ) ) {
			return '';
		}
		$out = '';
		foreach ( $tags as $t ) {
			$out .= '<a class="kit-chip" href="' . esc_url( get_term_link( $t ) ) . '" rel="tag">' . ( function_exists( 'aw_tag_icon' ) ? aw_tag_icon( $t->slug ) : '' ) . esc_html( $t->name ) . '</a>';
		}
		return '<div class="kit-tags aw-item-tags">' . $out . '</div>';
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
