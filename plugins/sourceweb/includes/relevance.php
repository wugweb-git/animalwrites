<?php
/**
 * Relevance: the include/animal/context/exclude keyword lists and the verdict for a piece of text. The verdict
 * rules: an exclude term rejects; an include ("strong") term keeps; otherwise an item is kept only when it
 * carries an animal term AND a context term.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Generic starter keyword lists. These are deliberately empty of any particular topic so the plugin ships
 * neutral; the administrator fills them in under SourceWeb → Keywords.
 *
 * @return array{enabled:bool,strong:array,animal:array,context:array,exclude:array}
 */
function sourceweb_keyword_defaults() {
	return array(
		'enabled' => false,
		'strong'  => array(),
		'animal'  => array(),
		'context' => array(),
		'exclude' => array(),
	);
}

/**
 * The current keyword lists (option `sourceweb_keywords`), merged over the defaults.
 *
 * @return array
 */
function sourceweb_keywords() {
	$r = function_exists( 'get_option' ) ? get_option( 'sourceweb_keywords' ) : false;
	return is_array( $r ) ? array_merge( sourceweb_keyword_defaults(), $r ) : sourceweb_keyword_defaults();
}

/**
 * Relevance verdict for a piece of text under the given (or current) keyword lists.
 *
 * @param string     $text  Title and/or summary.
 * @param array|null $lists Keyword lists; when null, the stored lists are used (so this is unit-testable with
 *                          explicit lists and needs no WordPress).
 * @return array{0:bool,1:string} keep?, reason.
 */
function sourceweb_relevance_verdict( $text, $lists = null ) {
	$r   = is_array( $lists ) ? array_merge( sourceweb_keyword_defaults(), $lists ) : sourceweb_keywords();
	$hay = mb_strtolower( (string) $text );

	foreach ( (array) $r['exclude'] as $t ) {
		if ( sourceweb_has_term( $hay, (string) $t ) ) {
			return array( false, 'exclude term: ' . $t );
		}
	}
	foreach ( (array) $r['strong'] as $t ) {
		if ( sourceweb_has_term( $hay, (string) $t ) ) {
			return array( true, 'strong term: ' . $t );
		}
	}
	$animal = null;
	foreach ( (array) $r['animal'] as $t ) {
		if ( sourceweb_has_term( $hay, (string) $t ) ) {
			$animal = $t;
			break;
		}
	}
	if ( null !== $animal ) {
		foreach ( (array) $r['context'] as $t ) {
			if ( sourceweb_has_term( $hay, (string) $t ) ) {
				return array( true, "include term: {$animal} + context term: {$t}" );
			}
		}
		return array( false, "include term ({$animal}) without a context term" );
	}
	return array( false, 'no include or strong term' );
}
