<?php
/**
 * Keyword scoring and cross-purpose recommendations (plan §7b, 17 Nov 2026). The pure, testable heart of the Keywords
 * tab's Score and Recommended-use columns, the focus-keyphrase reconciliation (Part 3) and the canonical-owner ladder
 * (Part 6). Everything here is a suggestion — InsightWeb never writes to Rank Math or SourceWeb.
 *
 * No WordPress calls in this file: every function takes plain values and returns plain values, so the daily recompute
 * (compute.php) can store the results and the no-WordPress test runner can check them. The weights, caps and the owner
 * ladder live here as constants and are documented in docs/insightweb-plan.md.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Score weights (sum to 100). Demand leads, then Opportunity, then Momentum, then Engagement.
const AW_SI_SCORE_W_DEMAND      = 35;
const AW_SI_SCORE_W_OPPORTUNITY = 30;
const AW_SI_SCORE_W_MOMENTUM    = 20;
const AW_SI_SCORE_W_ENGAGEMENT  = 15;

// Caps that turn raw signals into 0–100 sub-scores.
const AW_SI_SCORE_DEMAND_CAP = 5000; // impressions(28d) + volume that reaches a full demand sub-score (log scale).
const AW_SI_SCORE_CTR_FULL   = 0.10; // a 10% click-through rate is a full engagement sub-score.
const AW_SI_SCORE_GROWTH_CAP = 5.0;  // a rising growth of ×5 is a full momentum sub-score.
const AW_SI_SCORE_TRENDS_CAP = 100;  // a Trends index of 100 is a full momentum sub-score (used when there is no growth).

/**
 * The demand sub-score (0–100): how much a keyword is searched, from 28-day impressions plus any imported volume. Log
 * scale, so a handful of searches still scores and the biggest terms do not dwarf the rest.
 *
 * @param int $impr   Search Console impressions, 28 days.
 * @param int $volume Imported monthly volume (Keyword Planner etc.), or 0.
 * @return int
 */
function aw_si_score_demand( int $impr, int $volume ): int {
	$total = max( 0, $impr ) + max( 0, $volume );
	if ( $total <= 0 ) {
		return 0;
	}
	$score = log10( 1 + $total ) / log10( 1 + AW_SI_SCORE_DEMAND_CAP ) * 100;
	return (int) max( 0, min( 100, round( $score ) ) );
}

/**
 * The opportunity sub-score (0–100): room to gain, peaked at positions 5–20. Zero when we do not rank at all, low in
 * the top 3 (already won) and far down (50+), a plateau across 5–20 where a push pays off.
 *
 * @param float|null $pos Latest position, or null when we do not rank.
 * @return int
 */
function aw_si_score_opportunity( ?float $pos ): int {
	if ( null === $pos || $pos <= 0 ) {
		return 0; // Not ranking: demand/momentum carry the keyword, not opportunity.
	}
	if ( $pos <= 3 ) {
		return 20; // Already in the top 3 — little room left.
	}
	if ( $pos <= 5 ) {
		return 70; // Rising into the sweet spot.
	}
	if ( $pos <= 20 ) {
		return 100; // The sweet spot: a winnable push.
	}
	if ( $pos <= 50 ) {
		return (int) round( 100 - ( $pos - 20 ) / 30 * 70 ); // 100 at 20 → 30 at 50.
	}
	return (int) max( 0, round( 30 - ( $pos - 50 ) / 50 * 30 ) ); // 30 at 50 → 0 at 100+.
}

/**
 * The engagement sub-score (0–100): click-through rate, clicks ÷ impressions, with a 10% rate reading as a full score.
 *
 * @param int $clicks Clicks, 28 days.
 * @param int $impr   Impressions, 28 days.
 * @return int
 */
function aw_si_score_engagement( int $clicks, int $impr ): int {
	if ( $impr <= 0 ) {
		return 0;
	}
	$ctr = max( 0, $clicks ) / $impr;
	return (int) max( 0, min( 100, round( $ctr / AW_SI_SCORE_CTR_FULL * 100 ) ) );
}

/**
 * The momentum sub-score (0–100): how fast demand is rising. The rising growth multiple when there is one, else the
 * Trends index.
 *
 * @param float|null $growth Rising growth multiple (×N), or null.
 * @param int|null   $trends Trends index, or null.
 * @return int
 */
function aw_si_score_momentum( ?float $growth, ?int $trends ): int {
	if ( null !== $growth && $growth > 0 ) {
		return (int) max( 0, min( 100, round( $growth / AW_SI_SCORE_GROWTH_CAP * 100 ) ) );
	}
	if ( null !== $trends && $trends > 0 ) {
		return (int) max( 0, min( 100, round( $trends / AW_SI_SCORE_TRENDS_CAP * 100 ) ) );
	}
	return 0;
}

/**
 * The keyword score (0–100) and its four parts, from signals already on the record. Suggestions only.
 *
 * @param array{impr?: int, volume?: int, clicks?: int, pos?: float|null, growth?: float|null, trends?: int|null} $s Signals.
 * @return array{score: int, demand: int, opportunity: int, engagement: int, momentum: int}
 */
function aw_si_score( array $s ): array {
	$demand      = aw_si_score_demand( (int) ( $s['impr'] ?? 0 ), (int) ( $s['volume'] ?? 0 ) );
	$opportunity = aw_si_score_opportunity( isset( $s['pos'] ) ? ( null === $s['pos'] ? null : (float) $s['pos'] ) : null );
	$engagement  = aw_si_score_engagement( (int) ( $s['clicks'] ?? 0 ), (int) ( $s['impr'] ?? 0 ) );
	$momentum    = aw_si_score_momentum(
		isset( $s['growth'] ) && null !== $s['growth'] ? (float) $s['growth'] : null,
		isset( $s['trends'] ) && null !== $s['trends'] ? (int) $s['trends'] : null
	);
	$score       = ( $demand * AW_SI_SCORE_W_DEMAND
		+ $opportunity * AW_SI_SCORE_W_OPPORTUNITY
		+ $momentum * AW_SI_SCORE_W_MOMENTUM
		+ $engagement * AW_SI_SCORE_W_ENGAGEMENT ) / 100;
	return array(
		'score'       => (int) max( 0, min( 100, round( $score ) ) ),
		'demand'      => $demand,
		'opportunity' => $opportunity,
		'engagement'  => $engagement,
		'momentum'    => $momentum,
	);
}

/**
 * The score at or above which a keyword is worth acting on (write a page, push to sourcing). The Settings threshold can
 * override it.
 *
 * @return int
 */
function aw_si_score_floor(): int {
	return defined( 'AW_SI_SCORE_FLOOR' ) ? (int) AW_SI_SCORE_FLOOR : 55;
}

/**
 * The recommended-use labels for a keyword — one or more, spanning SEO and sourcing. Keys are stored; labels are shown
 * by aw_si_use_labels(). Suggestions only.
 *
 * Rules:
 * - focus-page   — has an owning page and ranks in the winnable band (positions 4–20): make it that page's focus keyphrase.
 * - write-page   — real demand (score at/above the floor) but no page and not ranking: write a new page.
 * - secondary    — has demand and a page, but already ranks top-3 or the term is a minor variant: add as a secondary keyphrase.
 * - sourcing:TYPE — the keyword's topic maps to a sourcing content type (news / library / campaign) in SourceWeb's list.
 * - exclude      — carries the Exclude role, or is off-topic with no demand.
 *
 * @param array{score?: int, pos?: float|null, has_page?: bool, impr?: int, roles?: int, topic?: string, is_minor?: bool} $kw Keyword facts.
 * @param array<string, string>                                                                                           $sourcing_types topic (lower-case) => 'news'|'library'|'campaign', read from SourceWeb (empty when SourceWeb is absent).
 * @return string[] Recommended-use keys, in display order.
 */
function aw_si_recommended_use( array $kw, array $sourcing_types = array() ): array {
	$score    = (int) ( $kw['score'] ?? 0 );
	$pos      = isset( $kw['pos'] ) && null !== $kw['pos'] ? (float) $kw['pos'] : null;
	$has_page = ! empty( $kw['has_page'] );
	$impr     = (int) ( $kw['impr'] ?? 0 );
	$roles    = (int) ( $kw['roles'] ?? 0 );
	$exclude  = defined( 'AW_KW_ROLE_EXCLUDE' ) ? (int) AW_KW_ROLE_EXCLUDE : 128;
	$floor    = aw_si_score_floor();
	$out      = array();

	if ( $roles & $exclude ) {
		return array( 'exclude' ); // The owner marked it off-topic; nothing else applies.
	}

	$winnable = null !== $pos && $pos > 3 && $pos <= 20;
	if ( $has_page && $winnable ) {
		$out[] = 'focus-page';
	}
	if ( ! $has_page && $score >= $floor && ( null === $pos || $pos > 20 ) ) {
		$out[] = 'write-page';
	}
	if ( $has_page && ( ( null !== $pos && $pos <= 3 ) || ! empty( $kw['is_minor'] ) ) ) {
		$out[] = 'secondary';
	}

	$type = aw_si_sourcing_type( (string) ( $kw['topic'] ?? '' ), $sourcing_types );
	if ( '' !== $type && $impr > 0 ) {
		$out[] = 'sourcing:' . $type;
	}

	if ( ! $out && $score < $floor && 0 === $impr ) {
		$out[] = 'exclude';
	}
	return array_values( array_unique( $out ) );
}

/**
 * The sourcing content type for a topic, from SourceWeb's category→type list (never hardcoded). Empty when the topic is
 * not one SourceWeb sources or SourceWeb is absent.
 *
 * @param string                $topic          The keyword's topic (as stored).
 * @param array<string, string> $sourcing_types topic (lower-case) => type.
 * @return string '' | 'news' | 'library' | 'campaign'.
 */
function aw_si_sourcing_type( string $topic, array $sourcing_types ): string {
	$key = strtolower( trim( $topic ) );
	if ( '' === $key || ! isset( $sourcing_types[ $key ] ) ) {
		return '';
	}
	$type = (string) $sourcing_types[ $key ];
	return in_array( $type, array( 'news', 'library', 'campaign' ), true ) ? $type : '';
}

/**
 * Whether InsightWeb would recommend a keyword AS a focus keyphrase (set B of the reconciliation): a strong score, a
 * winnable position and an owning page.
 *
 * @param int        $score    Keyword score.
 * @param float|null $pos      Position.
 * @param bool       $has_page An owning page.
 * @return bool
 */
function aw_si_is_focus_candidate( int $score, ?float $pos, bool $has_page ): bool {
	return $has_page && null !== $pos && $pos > 3 && $pos <= 20 && $score >= aw_si_score_floor();
}

/**
 * Focus-keyphrase health (Part 3): compare the role Rank Math set (set A) with what InsightWeb recommends (set B).
 *
 * @param bool $in_a Carries the Focus keyphrase role (set in Rank Math).
 * @param bool $in_b Recommended as a focus keyphrase (aw_si_is_focus_candidate()).
 * @return string '' (agree) | 'poor-rank' (focus keyphrase on a weak word, candidate to drop) | 'should-be' (strong word not set).
 */
function aw_si_focus_health( bool $in_a, bool $in_b ): string {
	if ( $in_a && ! $in_b ) {
		return 'poor-rank';
	}
	if ( $in_b && ! $in_a ) {
		return 'should-be';
	}
	return '';
}

/**
 * The content class of a page, from its post type and path: resource, library, campaign, news, directory or other.
 * Used by the canonical-owner ladder.
 *
 * @param string $path      Page path (e.g. /resources/rabies/).
 * @param string $post_type Post type (aw_legal, aw_campaign, aw_news, aw_listing, page, post…).
 * @return string
 */
function aw_si_page_class( string $path, string $post_type ): string {
	$map = array(
		'aw_legal'    => 'library',
		'aw_campaign' => 'campaign',
		'aw_news'     => 'news',
		'aw_listing'  => 'directory',
	);
	if ( isset( $map[ $post_type ] ) ) {
		return $map[ $post_type ];
	}
	$path = strtolower( $path );
	if ( false !== strpos( $path, '/resources/' ) || false !== strpos( $path, '/resource/' ) ) {
		return 'resource';
	}
	if ( 0 === strpos( $path, '/news/' ) ) {
		return 'news';
	}
	if ( 0 === strpos( $path, '/directory/' ) ) {
		return 'directory';
	}
	if ( 0 === strpos( $path, '/library/' ) ) {
		return 'library';
	}
	if ( 0 === strpos( $path, '/campaign' ) ) {
		return 'campaign';
	}
	return 'other';
}

/**
 * The query intent of a keyword, intent-first for the canonical-owner ladder, from simple signals in the phrase
 * (year tokens and news words → dated; "near me" or a place → local; action words → campaign; else evergreen).
 *
 * @param string $label Search phrase.
 * @return string 'evergreen' | 'dated' | 'local' | 'campaign'.
 */
function aw_si_query_intent( string $label ): string {
	$l = ' ' . strtolower( trim( $label ) ) . ' ';
	if ( preg_match( '/\bnear me\b/', $l ) || preg_match( '/\b(in|near|around)\s+[a-z]+\s*(city|district)?\s*$/', trim( strtolower( $label ) ) ) ) {
		return 'local';
	}
	if ( aw_si_is_news( trim( $label ) ) ) {
		return 'dated';
	}
	if ( preg_match( '/\b(donate|volunteer|petition|sign|pledge|join|campaign|support the)\b/', $l ) ) {
		return 'campaign';
	}
	return 'evergreen';
}

/**
 * The content-class priority ladder for an intent: higher wins. Evergreen favours a resource or library page; dated a
 * news page; local the directory; campaign the campaign page.
 *
 * @param string $intent aw_si_query_intent().
 * @return array<string, int>
 */
function aw_si_owner_ladder( string $intent ): array {
	switch ( $intent ) {
		case 'dated':
			return array(
				'news'      => 5,
				'resource'  => 3,
				'library'   => 3,
				'campaign'  => 2,
				'directory' => 2,
				'other'     => 1,
			);
		case 'local':
			return array(
				'directory' => 5,
				'resource'  => 3,
				'library'   => 3,
				'campaign'  => 2,
				'news'      => 2,
				'other'     => 1,
			);
		case 'campaign':
			return array(
				'campaign'  => 5,
				'resource'  => 3,
				'library'   => 3,
				'news'      => 2,
				'directory' => 2,
				'other'     => 1,
			);
		case 'evergreen':
		default:
			return array(
				'resource'  => 5,
				'library'   => 5,
				'campaign'  => 4,
				'news'      => 3,
				'directory' => 2,
				'other'     => 1,
			);
	}
}

/**
 * The canonical owner among the pages a keyword ranks for, plus a flag. Intent-first, then the content-type ladder,
 * tie-broken by position then CTR. Suggestions only — a human sets the focus keyphrase on the chosen page.
 *
 * @param array<int, array{path: string, post_type?: string, pos?: float|null, impr?: int, clicks?: int}> $candidates One per ranking URL.
 * @param string                                                                                          $intent aw_si_query_intent().
 * @return array{owner: array<string, mixed>|null, flag: string} flag '' | 'news-should-be-evergreen' | 'no-owner'.
 */
function aw_si_canonical_owner( array $candidates, string $intent ): array {
	$candidates = array_values( $candidates );
	if ( ! $candidates ) {
		return array(
			'owner' => null,
			'flag'  => '',
		);
	}

	$ladder = aw_si_owner_ladder( $intent );
	$rank   = static function ( array $c ) use ( $ladder ) {
		return (int) ( $ladder[ aw_si_page_class( (string) ( $c['path'] ?? '' ), (string) ( $c['post_type'] ?? '' ) ) ] ?? 1 );
	};
	$ctr    = static function ( array $c ) {
		$i = (int) ( $c['impr'] ?? 0 );
		return $i > 0 ? (int) ( $c['clicks'] ?? 0 ) / $i : 0.0;
	};

	// Owner = best ladder rank; tie-break by better (lower) position, then higher CTR.
	$owner = $candidates[0];
	foreach ( $candidates as $c ) {
		$cr = $rank( $c );
		$or = $rank( $owner );
		if ( $cr > $or ) {
			$owner = $c;
			continue;
		}
		if ( $cr === $or ) {
			$cp = isset( $c['pos'] ) && null !== $c['pos'] ? (float) $c['pos'] : 999.0;
			$op = isset( $owner['pos'] ) && null !== $owner['pos'] ? (float) $owner['pos'] : 999.0;
			if ( $cp < $op || ( $cp === $op && $ctr( $c ) > $ctr( $owner ) ) ) {
				$owner = $c;
			}
		}
	}

	// Flags. "No owner": several candidates and no page holds a majority of the impressions.
	$flag  = '';
	$total = 0;
	$best  = 0;
	foreach ( $candidates as $c ) {
		$i      = max( 0, (int) ( $c['impr'] ?? 0 ) );
		$total += $i;
		$best   = max( $best, $i );
	}
	if ( count( $candidates ) >= 2 && ( 0 === $total || $best / $total < 0.6 ) ) {
		$flag = 'no-owner';
	}

	// "Owned by News, should be Resource/Library": evergreen query where a news page currently ranks best but an
	// evergreen page should own it.
	if ( 'evergreen' === $intent ) {
		$best_ranked = $candidates[0];
		foreach ( $candidates as $c ) {
			$cp = isset( $c['pos'] ) && null !== $c['pos'] ? (float) $c['pos'] : 999.0;
			$bp = isset( $best_ranked['pos'] ) && null !== $best_ranked['pos'] ? (float) $best_ranked['pos'] : 999.0;
			if ( $cp < $bp ) {
				$best_ranked = $c;
			}
		}
		$owner_class = aw_si_page_class( (string) ( $owner['path'] ?? '' ), (string) ( $owner['post_type'] ?? '' ) );
		$top_class   = aw_si_page_class( (string) ( $best_ranked['path'] ?? '' ), (string) ( $best_ranked['post_type'] ?? '' ) );
		if ( 'news' === $top_class && in_array( $owner_class, array( 'resource', 'library' ), true ) ) {
			$flag = 'news-should-be-evergreen';
		}
	}

	return array(
		'owner' => $owner,
		'flag'  => $flag,
	);
}

/**
 * The topic → sourcing content-type map. InsightWeb never hardcodes SourceWeb's categories: SourceWeb (or any sourcing
 * plugin) fills this through the `aw_si_sourcing_types` filter, so the map is empty and no sourcing recommendation
 * fires when SourceWeb is absent. Keys are lower-cased topics; values are 'news' | 'library' | 'campaign'.
 *
 * @return array<string, string>
 */
function aw_si_sourcing_types(): array {
	/**
	 * Filter the topic → sourcing content-type map (SourceWeb supplies it from its own category list).
	 *
	 * @param array<string, string> $map topic => 'news' | 'library' | 'campaign'.
	 */
	$map = array();
	foreach ( (array) apply_filters( 'aw_si_sourcing_types', array() ) as $topic => $type ) {
		$t = strtolower( trim( (string) $topic ) );
		if ( '' !== $t && in_array( $type, array( 'news', 'library', 'campaign' ), true ) ) {
			$map[ $t ] = (string) $type;
		}
	}
	return $map;
}

/**
 * The stored score row for one keyword: the score and its parts, the recommended-use keys (CSV) and the focus-keyphrase
 * health. Pure — the daily recompute passes the keyword's signals and this returns the columns to store, and the no-WP
 * test checks it. The canonical-owner columns are written by a later pass; they are not computed here.
 *
 * @param array{impr?: int, volume?: int, clicks?: int, pos?: float|null, growth?: float|null, trends?: int|null, has_page?: bool, roles?: int, topic?: string, is_minor?: bool} $kw Keyword signals and facts.
 * @param array<string, string>                                                                                                                                                  $sourcing_types aw_si_sourcing_types().
 * @return array{score: int, s_demand: int, s_opportunity: int, s_engagement: int, s_momentum: int, recommended: string, focus_health: string}
 */
function aw_si_score_row( array $kw, array $sourcing_types = array() ): array {
	$pos      = isset( $kw['pos'] ) && null !== $kw['pos'] ? (float) $kw['pos'] : null;
	$s        = aw_si_score(
		array(
			'impr'   => (int) ( $kw['impr'] ?? 0 ),
			'volume' => (int) ( $kw['volume'] ?? 0 ),
			'clicks' => (int) ( $kw['clicks'] ?? 0 ),
			'pos'    => $pos,
			'growth' => isset( $kw['growth'] ) && null !== $kw['growth'] ? (float) $kw['growth'] : null,
			'trends' => isset( $kw['trends'] ) && null !== $kw['trends'] ? (int) $kw['trends'] : null,
		)
	);
	$has_page = ! empty( $kw['has_page'] );
	$roles    = (int) ( $kw['roles'] ?? 0 );
	$rec      = aw_si_recommended_use(
		array(
			'score'    => $s['score'],
			'pos'      => $pos,
			'has_page' => $has_page,
			'impr'     => (int) ( $kw['impr'] ?? 0 ),
			'roles'    => $roles,
			'topic'    => (string) ( $kw['topic'] ?? '' ),
			'is_minor' => ! empty( $kw['is_minor'] ),
		),
		$sourcing_types
	);
	$focus    = defined( 'AW_SI_ROLE_FOCUS' ) ? (int) AW_SI_ROLE_FOCUS : 2;
	$in_a     = (bool) ( $roles & $focus );
	$in_b     = aw_si_is_focus_candidate( $s['score'], $pos, $has_page );
	return array(
		'score'         => $s['score'],
		's_demand'      => $s['demand'],
		's_opportunity' => $s['opportunity'],
		's_engagement'  => $s['engagement'],
		's_momentum'    => $s['momentum'],
		'recommended'   => implode( ',', $rec ),
		'focus_health'  => aw_si_focus_health( $in_a, $in_b ),
	);
}

/**
 * Labels for the recommended-use keys (shown in the Keywords tab).
 *
 * @return array<string, string>
 */
function aw_si_use_labels(): array {
	return array(
		'focus-page'        => __( 'Focus keyphrase → page', 'insightweb' ),
		'write-page'        => __( 'Write a new page', 'insightweb' ),
		'secondary'         => __( 'Secondary keyphrase', 'insightweb' ),
		'sourcing:news'     => __( 'Sourcing candidate: news', 'insightweb' ),
		'sourcing:library'  => __( 'Sourcing candidate: library', 'insightweb' ),
		'sourcing:campaign' => __( 'Sourcing candidate: campaign', 'insightweb' ),
		'exclude'           => __( 'Exclude', 'insightweb' ),
	);
}

/**
 * Labels for the focus-health and canonical-owner flags.
 *
 * @return array<string, string>
 */
function aw_si_flag_labels(): array {
	return array(
		'poor-rank'                => __( 'Focus keyphrase, poor rank', 'insightweb' ),
		'should-be'                => __( 'Should be a focus keyphrase', 'insightweb' ),
		'news-should-be-evergreen' => __( 'Owned by News, should be Resource/Library', 'insightweb' ),
		'no-owner'                 => __( 'No canonical owner', 'insightweb' ),
	);
}
