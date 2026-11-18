<?php
/**
 * The decision engine: from a keyword's Search Console figures (28-day impressions, position, the previous period and
 * the latest live check) and its owning pages, work out a priority, a status and one suggested next action. Pure,
 * read-only logic — this is what turns metrics into decisions; nothing on the site is changed. The engine makes no
 * assumption about a site's post types or URL shapes.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Thresholds (overridable by the Settings screen in a later phase).
if ( ! defined( 'INSIGHTWEB_HIGH_TRENDS' ) ) {
	define( 'INSIGHTWEB_HIGH_TRENDS', 20 ); // High demand: a trends index at least this.
}
if ( ! defined( 'INSIGHTWEB_HIGH_IMPR' ) ) {
	define( 'INSIGHTWEB_HIGH_IMPR', 50 ); // High demand: Search Console impressions in 28 days at least this.
}
if ( ! defined( 'INSIGHTWEB_QUICK_MIN' ) ) {
	define( 'INSIGHTWEB_QUICK_MIN', 10 ); // Quick win: impressions at least this, at position 8–20.
}

/**
 * Suggested actions. Keys are stored on the record; labels are shown.
 *
 * @return array<string, string>
 */
function insightweb_actions(): array {
	return array(
		'fix-conflict'   => __( 'Fix conflict', 'insightweb' ),
		'new-guide'      => __( 'New page', 'insightweb' ),
		'guide-update'   => __( 'Update the page', 'insightweb' ),
		'add-secondary'  => __( 'Add as a secondary keyphrase', 'insightweb' ),
		'faq-answer'     => __( 'Answer as an FAQ', 'insightweb' ),
		'news-explainer' => __( 'Write a timely explainer', 'insightweb' ),
		'page-quality'   => __( 'Check page quality', 'insightweb' ),
	);
}

/**
 * The priority rule.
 *
 * @param int|null   $trends   Trends index (T), or null.
 * @param int        $impr     Search Console impressions, 28 days.
 * @param float|null $pos      Latest position (live check or Search Console), or null.
 * @param bool       $has_page A page owns or fits the keyword.
 * @return string P1, P2 or P3.
 */
function insightweb_priority( ?int $trends, int $impr, ?float $pos, bool $has_page ): string {
	if ( null !== $pos && $pos > 0 && $pos <= 3 ) {
		return 'P3'; // Already in the top 3.
	}
	$high = ( null !== $trends && $trends >= INSIGHTWEB_HIGH_TRENDS ) || $impr >= INSIGHTWEB_HIGH_IMPR;
	if ( ! $high ) {
		return 'P3';
	}
	// High demand: P1 when we rank below the top 3 but within 20, or a page fits; else P2 (a content gap).
	return $has_page || ( null !== $pos && $pos > 3 && $pos <= 20 ) ? 'P1' : 'P2';
}

/**
 * Whether a search reads as a question, so it fits an FAQ answer.
 *
 * @param string $label Search phrase.
 * @return bool
 */
function insightweb_is_question( string $label ): bool {
	$label = trim( $label );
	if ( preg_match( '/^who\b.*\b(rabies|guidelines?|report|recommend\w*|vaccin\w*)\b/i', $label ) ) {
		return false; // WHO, the organisation, not "who".
	}
	return false !== strpos( $label, '?' ) || (bool) preg_match( '/^(how|what|why|when|where|which|who|whom|whose|can|could|is|are|was|were|does|do|did|should|will|would)\b/i', $label );
}

/**
 * Whether a search is about news (an event, a case or a bare year), so it fits a timely explainer.
 *
 * @param string $label Search phrase.
 * @return bool
 */
function insightweb_is_news( string $label ): bool {
	if ( preg_match( '/\b(news|latest|today|yesterday|case|court|verdict|judgement|judgment|ruling|ban|banned|arrest|arrested|attack|attacked|killed|viral|incident|protest)\b/i', $label ) ) {
		return true;
	}
	// A year alone is news, but not in the name of a law (an Act, Bill or Rules of a year).
	return (bool) preg_match( '/\b20[0-9]{2}\b/', $label ) && ! preg_match( '/\b(act|acts|rules?|bill|amendment|law|laws|guidelines?|regulations?|order)\b/i', $label );
}

/**
 * The page type that fits a content gap: an FAQ answer, a timely explainer, or a new page.
 *
 * @param string $label Search phrase.
 * @return string An action key.
 */
function insightweb_gap_action( string $label ): string {
	if ( insightweb_is_question( $label ) ) {
		return 'faq-answer';
	}
	if ( insightweb_is_news( $label ) ) {
		return 'news-explainer';
	}
	return 'new-guide';
}

/**
 * Whether position and impressions make a quick win (just off the first page, with enough demand).
 *
 * @param float|null $pos  Position.
 * @param int        $impr Impressions, 28 days.
 * @return bool
 */
function insightweb_quick_win( ?float $pos, int $impr ): bool {
	return null !== $pos && $pos >= 8 && $pos <= 20 && $impr >= INSIGHTWEB_QUICK_MIN;
}

/**
 * Priority, status and one suggested action for a keyword.
 *
 * @param int|null                   $trends   Trends index.
 * @param int                        $impr     Search Console impressions, 28 days.
 * @param float|null                 $pos28    Search Console position, 28 days.
 * @param float|null                 $live     Latest live-check position.
 * @param string[]                   $owners   Paths of the pages that own the keyword.
 * @param bool                       $has_page Some page owns or carries it.
 * @param array{0?: string, 1?: int} $top  [path, impressions] of the page shown most.
 * @param array<string, mixed>       $ctx      label, quality (path => true), main (owner paths that are not minor pages;
 *                                              all owners when missing), shown_minor (the shown page is a minor page).
 * @return array{priority: string, status: string, action: string, conflict: bool, pos: float|null, owners: string[], shown: string}
 */
function insightweb_decide( ?int $trends, int $impr, ?float $pos28, ?float $live, array $owners, bool $has_page, array $top, array $ctx = array() ): array {
	$pos   = null !== $pos28 ? $pos28 : $live;
	$shown = (string) ( $top[0] ?? '' );
	$main  = (array) ( $ctx['main'] ?? $owners );
	// Two owning pages, or Google showing a different page than the owner with real impressions, is a conflict.
	$conflict = count( $main ) > 1
		|| ( $owners && '' !== $shown && (int) ( $top[1] ?? 0 ) >= INSIGHTWEB_QUICK_MIN && ! in_array( $shown, $owners, true ) && ( $main || empty( $ctx['shown_minor'] ) ) );
	$priority = insightweb_priority( $trends, $impr, $pos, $has_page );
	$status   = $conflict ? 'conflict' : ( $has_page ? 'has-page' : ( 'P2' === $priority ? 'gap' : '' ) );
	$label    = (string) ( $ctx['label'] ?? '' );
	$page     = $owners ? $owners[0] : $shown;
	$action   = '';
	// One action per keyword: the first that applies.
	if ( $conflict ) {
		$action = 'fix-conflict';
	} elseif ( 'P2' === $priority ) {
		$action = insightweb_gap_action( $label );
	} elseif ( insightweb_quick_win( $pos, $impr ) ) {
		$action = $has_page ? 'guide-update' : 'add-secondary';
	} elseif ( $impr > 0 && '' !== $page && isset( $ctx['quality'][ $page ] ) ) {
		$action = 'page-quality';
	} elseif ( ! $has_page && $impr >= INSIGHTWEB_QUICK_MIN && insightweb_is_question( $label ) ) {
		$action = 'faq-answer';
	} elseif ( ! $has_page && $impr >= INSIGHTWEB_QUICK_MIN && insightweb_is_news( $label ) ) {
		$action = 'news-explainer';
	}
	return compact( 'priority', 'status', 'action', 'conflict', 'pos', 'owners', 'shown' );
}
