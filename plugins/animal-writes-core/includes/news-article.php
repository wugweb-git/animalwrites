<?php
/**
 * News article page: the read-more fold and the ad-slot hook.
 *
 * Fold: a News body with more than 5 paragraphs shows paragraphs 1 to 3, then a 64px faded peek of the rest and a
 * "Read full summary" button (aria-expanded, aria-controls; aw.js opens it). The full text is in the page, so search and
 * a visitor without JavaScript get all of it (the <noscript> rule shows it and hides the button).
 *
 * Ads: nothing is built. aw_news_ad_slot( '', 'after-p3' | 'after-sources' ) is the hook; a slot prints, with its
 * reserved height and an "Advertisement" label, only when a filter gives it content. Not above the headline, no fixed bottom banner,
 * no popup. The two positions are at least 600px apart once the fold is open; with it shut a plugin should print one only.
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
		if ( count( $ends ) <= 5 ) {
			return $head . $ad . $rest;
		}
		$rest = preg_replace( '/<p(?=[\s>])/i', '<p tabindex="-1"', $rest, 1 ); // the first new paragraph takes focus when the fold opens
		return $head . $ad
			. '<div class="aw-fold" data-aw-fold><div class="aw-fold-body" id="aw-fold-body">' . $rest . '</div>'
			. '<button type="button" class="aw-fold-btn" aria-expanded="false" aria-controls="aw-fold-body" data-open="' . esc_attr__( 'Show less', 'animal-writes-core' ) . '" data-shut="' . esc_attr__( 'Read full summary', 'animal-writes-core' ) . '">' . esc_html__( 'Read full summary', 'animal-writes-core' ) . '</button>'
			. '<noscript><style>.aw-fold-body{max-height:none!important;-webkit-mask-image:none!important;mask-image:none!important}.aw-fold-btn{display:none!important}</style></noscript></div>';
	}
);

add_filter(
	'render_block_animal-writes/item-sources',
	function ( $html ) {
		return is_singular( 'aw_news' ) && '' !== (string) $html ? $html . aw_news_ad_slot( 'after-sources' ) : $html;
	}
);