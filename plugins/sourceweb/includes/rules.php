<?php
/**
 * Summary rules: deterministic transforms applied to a candidate item's summary before it is queued — strip
 * configured boilerplate phrases and trim to a maximum number of words at a sentence boundary. Pure PHP (no
 * WordPress, no network) apart from reading the option, so the transform is unit-testable.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * The summary-rule settings (option `sourceweb_summary`), merged over the defaults.
 *
 * @return array{max_words:int,strip:string[]}
 */
function sourceweb_summary_rules() {
	$r = function_exists( 'get_option' ) ? get_option( 'sourceweb_summary' ) : false;
	$r = is_array( $r ) ? $r : array();
	return array(
		'max_words' => isset( $r['max_words'] ) ? max( 0, (int) $r['max_words'] ) : 0,
		'strip'     => isset( $r['strip'] ) && is_array( $r['strip'] ) ? array_values( array_filter( array_map( 'strval', $r['strip'] ) ) ) : array(),
	);
}

/**
 * Apply the summary rules to a piece of text.
 *
 * @param string     $text  Raw summary text.
 * @param array|null $rules Optional rules (for testing); defaults to the stored rules.
 * @return string
 */
function sourceweb_apply_summary_rules( $text, $rules = null ) {
	$rules = is_array( $rules )
		? array(
			'max_words' => isset( $rules['max_words'] ) ? max( 0, (int) $rules['max_words'] ) : 0,
			'strip'     => isset( $rules['strip'] ) ? (array) $rules['strip'] : array(),
		)
		: sourceweb_summary_rules();

	$text = (string) $text;
	foreach ( (array) $rules['strip'] as $phrase ) {
		$phrase = trim( (string) $phrase );
		if ( '' !== $phrase ) {
			$text = str_ireplace( $phrase, ' ', $text );
		}
	}
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	if ( $rules['max_words'] > 0 ) {
		$text = sourceweb_trim_words( $text, $rules['max_words'] );
	}
	return $text;
}

/**
 * Trim text to at most $max words, preferring to end on the last complete sentence within the limit; otherwise
 * append an ellipsis.
 *
 * @param string $text Text (already whitespace-collapsed).
 * @param int    $max  Maximum words.
 * @return string
 */
function sourceweb_trim_words( $text, $max ) {
	$max   = (int) $max;
	$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( $max < 1 || count( $words ) <= $max ) {
		return trim( $text );
	}
	$kept = implode( ' ', array_slice( $words, 0, $max ) );
	if ( preg_match( '/^(.*[.!?])(?:\s|$)/u', $kept . ' ', $m ) ) {
		return trim( $m[1] );
	}
	return rtrim( $kept, ' ,;:' ) . '…';
}
