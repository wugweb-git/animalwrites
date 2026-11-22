<?php
/**
 * News article page: the key-facts card and the ad-slot hook (the read-more fold was dropped, owner 14 Nov 2026).
 *
 * Key facts: a short card above the summary with "What happened", "Why it matters" and "What is next", read from the
 * aw_news_happened, aw_news_matters and aw_news_next fields (meta.php). It prints only when at least one is filled and
 * only the filled lines show, so an item without them is unchanged. The News import does not fill them (no source gives
 * them); an editor or a later import step does.
 *
 * Ads: nothing is built. aw_news_ad_slot( '', 'after-p3' | 'after-sources' ) is the hook; a slot prints, with its
 * reserved height and an "Advertisement" label, only when a filter gives it content. Not above the headline, no fixed bottom banner,
 * no popup. The two positions are at least 600px apart.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The slot's wrapper, or '' when nothing is hooked in. $pos: 'after-p3' or 'after-sources'. */
function aw_news_ad_slot( string $pos ): string {
	/** Filters the ad markup for a News position; return '' for none. */
	$ad = (string) apply_filters( 'aw_news_ad_slot', '', $pos );
	if ( '' === $ad ) {
		return '';
	}
	$label = (string) aw_binding_value( 'sponsored_label', get_the_ID() ); // "Sponsored by X" when the item itself is sponsored
	$label = '' !== $label ? $label : __( 'Advertisement', 'animal-writes-core' );
	return '<aside class="aw-ad-slot aw-ad-slot--' . esc_attr( $pos ) . '" aria-label="' . esc_attr( $label ) . '"><p class="aw-ad-label">' . esc_html( $label ) . '</p><div class="aw-ad-box">' . $ad . '</div></aside>';
}

add_filter(
	'render_block_core/post-content',
	function ( $html ) {
		static $done = false;
		if ( $done || ! is_singular( 'aw_news' ) || get_the_ID() !== get_queried_object_id() ) {
			return $html;
		}
		$done  = true;
		$ends  = array();
		$off   = 0;
		while ( false !== ( $pos = stripos( $html, '</p>', $off ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			$ends[] = $pos + 4;
			$off    = $pos + 4;
		}
		if ( count( $ends ) < 3 ) {
			return $html;
		}
		$head = substr( $html, 0, $ends[2] );
		$rest = substr( $html, $ends[2] );
		$ad   = aw_news_ad_slot( 'after-p3' );
		return $head . $ad . $rest;
	}
);

add_filter(
	'render_block_animal-writes/item-sources',
	function ( $html ) {
		return is_singular( 'aw_news' ) && '' !== (string) $html ? $html . aw_news_ad_slot( 'after-sources' ) : $html;
	}
);

/** The key-facts card for a News item, or '' when none of the three fields is filled. */
function aw_news_key_facts( int $post_id ): string {
	$rows = array(
		__( 'What happened', 'animal-writes-core' ) => (string) get_post_meta( $post_id, 'aw_news_happened', true ),
		__( 'Why it matters', 'animal-writes-core' ) => (string) get_post_meta( $post_id, 'aw_news_matters', true ),
		__( 'What is next', 'animal-writes-core' )   => (string) get_post_meta( $post_id, 'aw_news_next', true ),
	);
	$html = '';
	foreach ( $rows as $label => $text ) {
		$text = trim( $text );
		if ( '' !== $text ) {
			$html .= '<li><strong>' . esc_html( $label ) . '</strong> ' . esc_html( $text ) . '</li>';
		}
	}
	return '' === $html ? '' : '<aside class="aw-keyfacts" aria-label="' . esc_attr__( 'Key facts', 'animal-writes-core' ) . '"><ul>' . $html . '</ul></aside>';
}

add_filter(
	'render_block_core/post-excerpt',
	function ( $html ) {
		return is_singular( 'aw_news' ) && get_the_ID() === get_queried_object_id() ? aw_news_key_facts( (int) get_the_ID() ) . $html : $html;
	}
);
