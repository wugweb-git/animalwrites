<?php
/**
 * Receive keyword candidates pushed by Search Insights / InsightWeb (owner, 17 Nov 2026): the cross-plugin contract
 * do_action( 'sourceweb_push_keywords', $terms, $context ). InsightWeb scores a keyword and a recommended use and
 * EMITS it as candidacy; SourceWeb stores the candidate and surfaces it in the Keywords → Suggestions approval queue.
 *
 * - NOTHING is added to the keyword lists here. A pushed keyword only becomes a suggestion row; a person approves it
 *   (or an owner-set auto-approve rule, default off, routes it — keyword-routing.php).
 * - Storage: option aw_sourcing_pushed_keywords (not autoloaded), keyword norm => candidate. Freshness and a cap keep
 *   it small. The contract carries, per keyword: source, score, recommended_type (news|library|campaign), impressions,
 *   clicks, position, topic, focus_keyphrase (true when the site already ranks for it) and rank.
 * - $terms is a list; each item is a keyword string, or an array carrying its own fields. $context is the shared
 *   default for the batch (so InsightWeb may send one keyword with its context, or many that share a run's context).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SOURCING_PUSHED = 'aw_sourcing_pushed_keywords';

/** Candidates older than this are dropped on the next read/write (InsightWeb re-pushes current ones each run). */
const AW_SOURCING_PUSHED_TTL = 90 * DAY_IN_SECONDS;

/** At most this many candidates are kept (the highest-scoring, freshest). */
const AW_SOURCING_PUSHED_MAX = 1000;

/** The recommended uses InsightWeb may send, and the section each routes to (keyword-routing.php uses this map). */
const AW_SOURCING_PUSHED_TYPES = array( 'news', 'library', 'campaign' );

/**
 * One field of a pushed keyword, from the keyword's own row first, then the batch context.
 *
 * @param array  $term    The keyword row (array form) or array().
 * @param array  $context The batch context.
 * @param string $key     Field.
 * @param mixed  $default Default.
 * @return mixed
 */
function aw_sourcing_pushed_field( array $term, array $context, $key, $default = null ) {
	if ( array_key_exists( $key, $term ) ) {
		return $term[ $key ];
	}
	if ( array_key_exists( $key, $context ) ) {
		return $context[ $key ];
	}
	return $default;
}

/**
 * One pushed keyword, cleaned to the stored shape ('' q means skip it).
 *
 * @param mixed $term    A keyword string or an array carrying its fields.
 * @param array $context The batch context.
 * @return array{q:string, source:string, score:int, recommended_type:string, impressions:int, clicks:int, position:float, topic:string, focus_keyphrase:bool, rank:int, pushed_at:int}|null
 */
function aw_sourcing_pushed_clean( $term, array $context ) {
	$row = is_array( $term ) ? $term : array();
	$q   = is_string( $term ) ? $term : (string) ( $row['q'] ?? $row['term'] ?? $row['keyword'] ?? $row['label'] ?? '' );
	$q   = trim( (string) preg_replace( '/\s+/u', ' ', (string) $q ) );
	if ( '' === $q ) {
		return null;
	}
	$type = (string) aw_sourcing_pushed_field( $row, $context, 'recommended_type', '' );
	$type = in_array( $type, AW_SOURCING_PUSHED_TYPES, true ) ? $type : '';
	$rank = (int) aw_sourcing_pushed_field( $row, $context, 'rank', 0 );
	return array(
		'q'                => $q,
		'source'           => sanitize_key( (string) aw_sourcing_pushed_field( $row, $context, 'source', 'insightweb' ) ),
		'score'            => max( 0, (int) round( (float) aw_sourcing_pushed_field( $row, $context, 'score', 0 ) ) ),
		'recommended_type' => $type,
		'impressions'      => max( 0, (int) aw_sourcing_pushed_field( $row, $context, 'impressions', 0 ) ),
		'clicks'           => max( 0, (int) aw_sourcing_pushed_field( $row, $context, 'clicks', 0 ) ),
		'position'         => max( 0.0, (float) aw_sourcing_pushed_field( $row, $context, 'position', 0 ) ),
		'topic'            => sanitize_text_field( (string) aw_sourcing_pushed_field( $row, $context, 'topic', '' ) ),
		'focus_keyphrase'  => (bool) aw_sourcing_pushed_field( $row, $context, 'focus_keyphrase', false ),
		'rank'             => $rank > 0 ? $rank : 0,
		'pushed_at'        => time(),
	);
}

/**
 * Receive pushed keyword candidates and store them (never added to the lists).
 *
 * @param mixed $terms   A list of keyword strings or rows.
 * @param mixed $context The batch context (array).
 * @return void
 */
function aw_sourcing_receive_pushed_keywords( $terms, $context = array() ) {
	if ( ! is_array( $terms ) ) {
		$terms = array( $terms );
	}
	$context = is_array( $context ) ? $context : array();
	$store   = aw_sourcing_pushed_raw();
	$changed = false;
	foreach ( $terms as $term ) {
		$row = aw_sourcing_pushed_clean( $term, $context );
		if ( null === $row ) {
			continue;
		}
		$norm              = function_exists( 'aw_si_norm' ) ? aw_si_norm( $row['q'] ) : mb_strtolower( $row['q'] );
		$store[ $norm ]    = $row;
		$changed           = true;
	}
	if ( $changed ) {
		aw_sourcing_pushed_save( $store );
	}
}
add_action( 'sourceweb_push_keywords', 'aw_sourcing_receive_pushed_keywords', 10, 2 );

/** The raw store (norm => candidate), old ones dropped. */
function aw_sourcing_pushed_raw() {
	$store = (array) get_option( AW_SOURCING_PUSHED, array() );
	$cut   = time() - AW_SOURCING_PUSHED_TTL;
	$out   = array();
	foreach ( $store as $norm => $row ) {
		if ( is_array( $row ) && '' !== (string) ( $row['q'] ?? '' ) && (int) ( $row['pushed_at'] ?? 0 ) >= $cut ) {
			$out[ (string) $norm ] = $row;
		}
	}
	return $out;
}

/**
 * Store candidates, keeping at most AW_SOURCING_PUSHED_MAX (the highest-scoring, then freshest).
 *
 * @param array $store norm => candidate.
 * @return void
 */
function aw_sourcing_pushed_save( array $store ) {
	if ( count( $store ) > AW_SOURCING_PUSHED_MAX ) {
		uasort( $store, fn( $a, $b ) => ( (int) $b['score'] <=> (int) $a['score'] ) ?: ( (int) $b['pushed_at'] <=> (int) $a['pushed_at'] ) );
		$store = array_slice( $store, 0, AW_SOURCING_PUSHED_MAX, true );
	}
	update_option( AW_SOURCING_PUSHED, $store, false );
}

/** Forget one pushed candidate by its normalised keyword (after it is approved or dismissed). */
function aw_sourcing_pushed_forget( $norm ) {
	$store = aw_sourcing_pushed_raw();
	if ( isset( $store[ (string) $norm ] ) ) {
		unset( $store[ (string) $norm ] );
		update_option( AW_SOURCING_PUSHED, $store, false );
	}
}

/**
 * Pushed candidates as suggestion rows, keyed by normalised keyword: the fields the Suggestions queue shows beside the
 * Search Console figures (score, recommended type, topic, and whether the site already ranks for it).
 *
 * @return array<string, array> norm => {q, impr, clicks, pos, score, recommended_type, topic, focus_keyphrase, rank, source, pushed}
 */
function aw_sourcing_pushed_suggestions() {
	$out = array();
	foreach ( aw_sourcing_pushed_raw() as $norm => $row ) {
		$out[ (string) $norm ] = array(
			'q'                => (string) $row['q'],
			'impr'             => (int) $row['impressions'],
			'clicks'           => (int) $row['clicks'],
			'pos'              => (float) $row['position'],
			'score'            => (int) $row['score'],
			'recommended_type' => (string) $row['recommended_type'],
			'topic'            => (string) $row['topic'],
			'focus_keyphrase'  => (bool) $row['focus_keyphrase'],
			'rank'             => (int) $row['rank'],
			'source'           => (string) $row['source'],
			'pushed'           => true,
		);
	}
	return $out;
}
