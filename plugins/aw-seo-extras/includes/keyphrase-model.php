<?php
/**
 * One page per keyphrase (1 Nov 2026): the rule the focus-keyword API (/aw/v1/seo-focus) applies before it writes a
 * keyphrase, without WordPress, so tests can run it (plugins/aw-sourcing/tests/run.php).
 *
 * A keyphrase another published page already has (its Rank Math focus keyphrase, or the owner of the keyword record in
 * Search Insights) is refused. The reason names that page, so the worker can log it. When several pages have it, the
 * reason names an evergreen page (a Resource, Library item, campaign, page, listing or term) before a News item.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Content types whose pages answer a question for good (a News item reports one day's story). */
const AW_SEO_KP_EVERGREEN = array( 'post', 'aw_legal', 'aw_campaign', 'page', 'aw_listing', 'term' );

/**
 * Whether a content type is evergreen.
 *
 * @param string $type Post type, or 'term'.
 */
function aw_seo_kp_evergreen( string $type ): bool {
	return in_array( $type, AW_SEO_KP_EVERGREEN, true );
}

/**
 * Why a page may not take a keyphrase, '' when it may.
 *
 * @param string $type   Post type of the page asking for the keyphrase.
 * @param array  $owners Other pages that have it: each [ 'type' => post type or 'term', 'id' => int ]. The page itself
 *                       is never in the list.
 * @return string
 */
function aw_seo_kp_refusal( string $type, array $owners ): string {
	$owners = array_values( array_filter( $owners, fn( $o ) => is_array( $o ) && ! empty( $o['id'] ) ) );
	if ( ! $owners ) {
		return '';
	}
	usort( $owners, fn( $a, $b ) => (int) aw_seo_kp_evergreen( (string) $b['type'] ) - (int) aw_seo_kp_evergreen( (string) $a['type'] ) );
	$o    = $owners[0];
	$name = (string) $o['type'] . ' ' . (int) $o['id'];
	if ( 'aw_news' === $type && aw_seo_kp_evergreen( (string) $o['type'] ) ) {
		return 'an evergreen page owns this keyphrase: ' . $name;
	}
	return 'another page owns this keyphrase: ' . $name;
}

/**
 * Replays the rule over the pages that share a keyword today, oldest first: the first page keeps it and every later one
 * would have been refused. The evergreen page goes first when there is one, so a News item never wins over a guide.
 *
 * @param array $pages Each [ 'type' => post type or 'term', 'id' => int, 'date' => sortable string ].
 * @return array{keep: array, refused: array} The page kept and the pages refused, each with their reason.
 */
function aw_seo_kp_replay( array $pages ): array {
	usort(
		$pages,
		function ( $a, $b ) {
			$e = (int) aw_seo_kp_evergreen( (string) $b['type'] ) - (int) aw_seo_kp_evergreen( (string) $a['type'] );
			return $e ? $e : strcmp( (string) $a['date'], (string) $b['date'] );
		}
	);
	$keep    = array_shift( $pages );
	$refused = array();
	foreach ( $pages as $p ) {
		$refused[] = $p + array( 'why' => aw_seo_kp_refusal( (string) $p['type'], array( $keep ) ) );
	}
	return array(
		'keep'    => $keep,
		'refused' => $refused,
	);
}
