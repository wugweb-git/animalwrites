<?php
/**
 * The feed into SourceWeb (plan §7b, Part 5) — InsightWeb's emit side only.
 *
 * After the daily recompute, keywords that score above the threshold and carry a sourcing-candidate recommendation
 * (and well-ranking focus keyphrases, which are proven terms) are offered to SourceWeb as candidates through the opt-in
 * action do_action( 'sourceweb_push_keywords', $terms, $context ). Candidacy only: SourceWeb, if it is listening, adds
 * the terms to its own approval queue and its Rules map role, category and On/Off — InsightWeb never inserts, never
 * writes to SourceWeb, and the action simply sits idle when SourceWeb is not installed. No shared table, no hard
 * dependency.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The score at or above which a keyword may be pushed to SourceWeb as a sourcing candidate. Set on the Settings tab
 * (the AW_SI_SOURCING_PUSH_MIN number); filterable.
 *
 * @return int
 */
function aw_si_sourcing_push_min(): int {
	$min = defined( 'AW_SI_SOURCING_PUSH_MIN' ) ? (int) AW_SI_SOURCING_PUSH_MIN : 70;
	/**
	 * Filter the minimum score to push a keyword to SourceWeb.
	 *
	 * @param int $min The threshold (0-100).
	 */
	return (int) apply_filters( 'aw_si_sourcing_push_min', $min );
}

/**
 * Build the $context for one candidate (pure, so the no-WordPress test can check it). A well-ranking focus keyphrase is
 * a proven term: it carries focus_keyphrase = true and its rank.
 *
 * @param array{score?: int, recommended?: string, impr?: int, clicks?: int, pos?: float|null, topic?: string, roles?: int} $r Candidate facts.
 * @return array<string, mixed>
 */
function aw_si_push_context( array $r ): array {
	$roles = (int) ( $r['roles'] ?? 0 );
	$focus = defined( 'AW_SI_ROLE_FOCUS' ) ? (int) AW_SI_ROLE_FOCUS : 2;
	$pos   = isset( $r['pos'] ) && null !== $r['pos'] ? (float) $r['pos'] : null;
	$type  = 'news'; // SourceWeb's default output; a sourcing:TYPE recommendation overrides it.
	foreach ( explode( ',', (string) ( $r['recommended'] ?? '' ) ) as $key ) {
		if ( 0 === strpos( $key, 'sourcing:' ) ) {
			$type = substr( $key, strlen( 'sourcing:' ) );
			break;
		}
	}
	return array(
		'source'           => 'insightweb',
		'score'            => (int) ( $r['score'] ?? 0 ),
		'recommended_type' => $type,
		'impressions'      => (int) ( $r['impr'] ?? 0 ),
		'clicks'           => (int) ( $r['clicks'] ?? 0 ),
		'position'         => $pos,
		'topic'            => (string) ( $r['topic'] ?? '' ),
		'focus_keyphrase'  => (bool) ( $roles & $focus ),
		'rank'             => $pos,
	);
}

/**
 * Offer the current sourcing candidates to SourceWeb. Reads the score store joined with the keyword table and fires one
 * opt-in action per candidate. Candidates: score ≥ the threshold AND a sourcing-candidate recommendation, or a
 * well-ranking focus keyphrase (positions 4–20). Returns how many were offered.
 *
 * @return int
 */
function aw_si_push_sourcing_candidates(): int {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return 0;
	}
	$min   = aw_si_sourcing_push_min();
	$focus = defined( 'AW_SI_ROLE_FOCUS' ) ? (int) AW_SI_ROLE_FOCUS : 2;
	$k     = aw_si_table( 'keywords' );
	$s     = aw_si_table( 'scores' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names via %i; fixed conditions with placeholders.
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT k.label, k.topic, k.roles, k.impr28, k.clicks28, k.pos28, k.live_pos, s.score, s.recommended FROM %i k JOIN %i s ON s.kw_id = k.id WHERE s.score >= %d AND ( s.recommended LIKE %s OR ( k.roles & %d AND k.pos28 BETWEEN 4 AND 20 ) )', $k, $s, $min, '%sourcing:%', $focus ) );
	$n    = 0;
	foreach ( (array) $rows as $r ) {
		$pos     = null !== $r->pos28 ? (float) $r->pos28 : ( null !== $r->live_pos ? (float) $r->live_pos : null );
		$context = aw_si_push_context(
			array(
				'score'       => (int) $r->score,
				'recommended' => (string) $r->recommended,
				'impr'        => (int) $r->impr28,
				'clicks'      => (int) $r->clicks28,
				'pos'         => $pos,
				'topic'       => (string) $r->topic,
				'roles'       => (int) $r->roles,
			)
		);
		/**
		 * Offer a keyword to SourceWeb as a sourcing candidate (opt-in; candidacy only — SourceWeb queues it for
		 * approval and never auto-inserts). Idle when no sourcing plugin is listening.
		 *
		 * @param string[]             $terms   The term (one keyword label).
		 * @param array<string, mixed> $context source, score, recommended_type, impressions, clicks, position, topic, focus_keyphrase, rank.
		 */
		do_action( 'sourceweb_push_keywords', array( (string) $r->label ), $context );
		++$n;
	}
	return $n;
}
