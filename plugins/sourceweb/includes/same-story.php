<?php
/**
 * Same-story detection: decide whether two titles are the same story even when the wording differs a little,
 * using normalised-title equality or a token-overlap (Jaccard) similarity. Pure PHP — unit-testable — and used
 * to widen the review-queue duplicate check beyond exact matches.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Significant word tokens of a title: normalised (sourceweb_norm_title), split, with very short words and a
 * small stop-word list dropped. Returns a de-duplicated list.
 *
 * @param string $title Title.
 * @return string[]
 */
function sourceweb_title_tokens( $title ) {
	$stop   = array( 'the', 'and', 'for', 'with', 'from', 'that', 'this', 'are', 'was', 'has', 'its', 'into', 'over', 'after' );
	$tokens = preg_split( '/\s+/u', sourceweb_norm_title( $title ), -1, PREG_SPLIT_NO_EMPTY );
	$out    = array();
	foreach ( (array) $tokens as $word ) {
		if ( mb_strlen( $word ) >= 3 && ! in_array( $word, $stop, true ) ) {
			$out[ $word ] = true;
		}
	}
	return array_keys( $out );
}

/**
 * Jaccard similarity (0..1) of two titles' significant tokens.
 *
 * @param string $a Title A.
 * @param string $b Title B.
 * @return float
 */
function sourceweb_title_similarity( $a, $b ) {
	$ta = sourceweb_title_tokens( $a );
	$tb = sourceweb_title_tokens( $b );
	if ( empty( $ta ) || empty( $tb ) ) {
		return 0.0;
	}
	$intersect = count( array_intersect( $ta, $tb ) );
	$union     = count( array_unique( array_merge( $ta, $tb ) ) );
	return $union > 0 ? $intersect / $union : 0.0;
}

/**
 * Are two titles the same story? True when their normalised titles are equal, or their token similarity meets
 * the threshold.
 *
 * @param string $a         Title A.
 * @param string $b         Title B.
 * @param float  $threshold Similarity threshold (default 0.6).
 * @return bool
 */
function sourceweb_is_same_story( $a, $b, $threshold = 0.6 ) {
	if ( sourceweb_norm_title( $a ) === sourceweb_norm_title( $b ) ) {
		return true;
	}
	return sourceweb_title_similarity( $a, $b ) >= (float) $threshold;
}
