<?php
/**
 * Suggestions from Search Insights (owner, 24 Oct 2026): Sourcing → Keywords lists searches people make that
 * no sourcing word covers, so the owner can add them to the Include or Exclude list by hand. Nothing is added by itself.
 *
 * - The searches come from aw-seo-extras (filter aw_search_demand_terms). Without it there are no suggestions and
 *   sourcing works as before.
 * - Shown: at least 20 impressions in the last 28 days (owner, 24 Oct 2026; Rising is not used for suggestions). Left out: brand searches, searches not in English
 *   (they never become Google News searches; Search Insights shows them), and searches an Include or Exclude word
 *   already matches, as the keyword check matches a headline.
 * - Shown on the Keywords screen's Suggestions view (Kits shell; GET and POST sourceweb/v1/keywords/suggestions):
 *   Add to Include, Add to Exclude, Add as a search, Dismiss, Show again.
 * - Dismissed searches: option aw_sourcing_dismissed (not autoloaded).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SOURCING_DISMISSED = 'aw_sourcing_dismissed';

/** Lowercase, single-spaced. */
function aw_sourcing_suggest_norm( $s ) {
	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( (string) $s ) ) );
}

/** Dismissed searches (from phase 2: the records marked dismissed). */
function aw_sourcing_dismissed() {
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) {
		global $wpdb;
		$k = aw_si_table( 'keywords' );
		return array_map( 'aw_sourcing_suggest_norm', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT label FROM %i WHERE dismissed = 1', $k ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	return array_values( array_filter( (array) get_option( AW_SOURCING_DISMISSED, array() ), 'is_string' ) );
}

/** Every suggestion row carries these fields; the ones InsightWeb pushes default empty for Search Console demand rows. */
function aw_sourcing_suggest_extra_defaults() {
	return array(
		'score'            => 0,
		'recommended_type' => '',
		'topic'            => '',
		'focus_keyphrase'  => false,
		'rank'             => 0,
		'source'           => '',
		'pushed'           => false,
	);
}

/**
 * Searches with demand that no sourcing word covers, plus the keyword candidates InsightWeb pushes
 * (sourceweb_push_keywords). Candidacy only: nothing here is added to a list.
 *
 * @return array|null Rows (q, impr, clicks, pos, rising, dismissed, score, recommended_type, topic, focus_keyphrase,
 *                    rank, source, pushed), or null when neither Search Insights nor a push gives any data.
 */
function aw_sourcing_suggestions() {
	$terms  = apply_filters( 'aw_search_demand_terms', null );
	$pushed = function_exists( 'aw_sourcing_pushed_suggestions' ) ? aw_sourcing_pushed_suggestions() : array();
	if ( ! is_array( $terms ) && ! $pushed ) {
		return null;
	}
	$rel       = function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ? aw_sourcing_kw_lists() : aw_relevance();
	$words     = array_merge( (array) ( $rel['strong'] ?? array() ), (array) ( $rel['exclude'] ?? array() ), (array) ( $rel['search'] ?? array() ) ); // a Search keyword covers it too (phase 2)
	$dismissed = array_flip( aw_sourcing_dismissed() );
	$dis_norm  = array_flip( array_map( 'aw_si_norm', array_keys( $dismissed ) ) );
	$covered   = function ( $q ) use ( $words ) {
		foreach ( $words as $w ) {
			if ( aw_has_term( $q, $w ) ) {
				return true;
			}
		}
		return false;
	};
	$is_dismissed = fn( $q ) => isset( $dismissed[ $q ] ) || ( function_exists( 'aw_si_norm' ) && isset( $dis_norm[ aw_si_norm( $q ) ] ) );
	$out          = array();
	foreach ( (array) $terms as $t ) {
		$q = aw_sourcing_suggest_norm( $t['q'] ?? '' );
		if ( '' === $q || ! empty( $t['brand'] ) || ! preg_match( '/^[\x20-\x7e]+$/', $q ) ) {
			continue;
		}
		if ( (int) ( $t['impr'] ?? 0 ) < 20 ) { // owner, 24 Oct 2026: 20 impressions in 28 days; Rising does not count until Search Insights has 8 weeks of history
			continue;
		}
		if ( $covered( $q ) ) {
			continue;
		}
		$out[ $q ] = array(
			'q'         => $q,
			'impr'      => (int) ( $t['impr'] ?? 0 ),
			'clicks'    => (int) ( $t['clicks'] ?? 0 ),
			'pos'       => (float) ( $t['pos'] ?? 0 ),
			'rising'    => ! empty( $t['rising'] ),
			'dismissed' => $is_dismissed( $q ),
		) + aw_sourcing_suggest_extra_defaults();
	}
	// InsightWeb's pushed candidates: shown whatever their impressions (InsightWeb has already scored them), but still
	// only when no Include/Exclude/Search keyword already covers them. A candidate also seen in Search Console demand
	// keeps that row and gains the pushed fields (Score, recommended type, topic, proven).
	foreach ( $pushed as $p ) {
		$q = aw_sourcing_suggest_norm( $p['q'] );
		if ( '' === $q || ! preg_match( '/^[\x20-\x7e]+$/', $q ) || $covered( $q ) ) {
			continue;
		}
		$extra = array(
			'score'            => (int) $p['score'],
			'recommended_type' => (string) $p['recommended_type'],
			'topic'            => (string) $p['topic'],
			'focus_keyphrase'  => (bool) $p['focus_keyphrase'],
			'rank'             => (int) $p['rank'],
			'source'           => (string) $p['source'],
			'pushed'           => true,
		);
		if ( isset( $out[ $q ] ) ) {
			$out[ $q ] = array_merge( $out[ $q ], $extra );
			continue;
		}
		$out[ $q ] = array(
			'q'         => $q,
			'impr'      => (int) $p['impr'],
			'clicks'    => (int) $p['clicks'],
			'pos'       => (float) $p['pos'],
			'rising'    => false,
			'dismissed' => $is_dismissed( $q ),
		) + $extra;
	}
	return array_values( $out );
}

/** Do one action on some searches; returns how many changed. */
function aw_sourcing_suggest_apply( $op, array $qs ) {
	$qs = array_values( array_unique( array_filter( array_map( 'aw_sourcing_suggest_norm', $qs ) ) ) );
	if ( ! $qs ) {
		return 0;
	}
	if ( 'route' === $op && function_exists( 'aw_sourcing_push_route' ) ) { // approve a pushed candidate and auto-route it (Section, Role, Category)
		$n = 0;
		foreach ( $qs as $q ) {
			if ( true === aw_sourcing_push_route( $q, 'owner' ) ) {
				++$n;
			}
		}
		return $n;
	}
	if ( function_exists( 'aw_sourcing_kw_on' ) && aw_sourcing_kw_on() ) { // phase 2: roles and the dismissed flag on the record
		$n = 0;
		foreach ( $qs as $q ) {
			if ( in_array( $op, array( 'include', 'exclude', 'search' ), true ) ) {
				$bit = array( 'include' => AW_KW_ROLE_INCLUDE, 'exclude' => AW_KW_ROLE_EXCLUDE, 'search' => AW_KW_ROLE_SEARCH )[ $op ];
				$id  = aw_kw_id_by_norm( aw_si_norm( $q ) );
				$was = $id ? (int) aw_kw_get( $id )['roles'] : 0;
				$r   = aw_sourcing_kw_add( $q, array( 'roles' => $bit, 'sections' => AW_KW_ROLE_EXCLUDE === $bit ? array() : array( 'aw_news' ) ) );
				if ( ! is_wp_error( $r ) && ! ( $was & $bit ) ) {
					++$n;
					aw_src_log( 'keywords', $q . ': ' . ucfirst( $op ) . ' (suggested by Search Insights)', '', ucfirst( $op ) );
				}
				continue;
			}
			$id = aw_kw_id_by_norm( aw_si_norm( $q ) );
			$id = $id ? $id : aw_kw_create( $q );
			$on = 'dismiss' === $op ? 1 : 0;
			if ( $id && true === aw_kw_update( $id, array( 'dismissed' => $on ) ) ) {
				++$n;
				aw_src_log( 'keywords', $q . ': suggestion ' . ( $on ? 'dismissed' : 'shown again' ), (bool) ! $on, (bool) $on );
			}
		}
		return $n;
	}
	if ( 'include' === $op || 'exclude' === $op ) {
		$rel   = aw_relevance();
		$list  = 'include' === $op ? 'strong' : 'exclude';
		$have  = array_map( 'aw_sourcing_suggest_norm', (array) ( $rel[ $list ] ?? array() ) );
		$added = array_values( array_diff( $qs, $have ) );
		if ( $added ) {
			$rel[ $list ] = array_merge( (array) ( $rel[ $list ] ?? array() ), $added );
			update_option( AW_REL_OPTION, $rel, false );
			aw_src_log( 'keywords', ( 'include' === $op ? 'Include' : 'Exclude' ) . ': added ' . implode( ', ', $added ) . ' (suggested by Search Insights)' );
			delete_transient( 'aw_sourcing_kw_stats_30' );
		}
		return count( $added );
	}
	$d   = aw_sourcing_dismissed();
	$new = 'dismiss' === $op ? array_values( array_unique( array_merge( $d, $qs ) ) ) : array_values( array_diff( $d, $qs ) );
	update_option( AW_SOURCING_DISMISSED, $new, false );
	return abs( count( $new ) - count( $d ) );
}
