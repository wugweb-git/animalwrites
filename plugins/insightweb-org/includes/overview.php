<?php
/**
 * The Overview (Dashboard) tab: the headline figures and the charts that answer "where should I spend effort" —
 * the 28-day clicks / impressions / CTR / position trends and the gap-action and priority / status breakdowns (the
 * gap-action split is InsightWeb's differentiator), plus the top queries.
 *
 * Every chart is a MiniChart, the suite's shared chart contract (the shell exports it; this is the server render of
 * the same contract, so it is identical before and after the shell app mounts and needs no chart library): an
 * accessible <figure> of inline-SVG bars normalised to the largest value, the LAST bar inked for attention and the
 * rest greyscale, a <figcaption> of the label and the first-to-last range, and a visually hidden data table (with the
 * unit) so the figures never depend on sight of the bars. Colours come from the shell palette through CSS variables
 * (--insightweb-ink, --insightweb-bar); no new theme and no custom chrome. One prefix: insightweb_.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Overview as a block of escaped HTML (so it renders both on the classic screen and as a shell part).
 *
 * @return string
 */
function insightweb_overview_html(): string {
	$o = insightweb_overview();
	if ( ! $o['ready'] ) {
		return '<p>' . esc_html__( 'Setting up — InsightWeb has not built its tables yet.', 'insightweb' ) . '</p>';
	}

	$html  = insightweb_overview_scorecards( $o['totals'] );
	$html .= insightweb_overview_trends( $o['trend'] );
	$html .= insightweb_overview_breakdowns( $o );
	$html .= insightweb_overview_top( $o['top'] );
	return $html;
}

/**
 * The headline scorecards (single big numbers — dataviz: a bare value needs no chart).
 *
 * @param array<string, float|int|null> $t Totals from insightweb_overview().
 * @return string
 */
function insightweb_overview_scorecards( array $t ): string {
	$cards = array(
		array( __( 'Keywords', 'insightweb' ), number_format_i18n( (int) $t['keywords'] ) ),
		array( __( 'Impressions (28 days)', 'insightweb' ), number_format_i18n( (int) $t['impr'] ) ),
		array( __( 'Clicks (28 days)', 'insightweb' ), number_format_i18n( (int) $t['clicks'] ) ),
		array( __( 'CTR', 'insightweb' ), null === $t['ctr'] ? '–' : number_format_i18n( (float) $t['ctr'], 1 ) . '%' ),
		array( __( 'Average position', 'insightweb' ), null === $t['pos'] ? '–' : number_format_i18n( (float) $t['pos'], 1 ) ),
		array( __( 'Quick wins', 'insightweb' ), number_format_i18n( (int) $t['quick'] ) ),
	);
	$out   = '<div class="insightweb-scorecards" style="display:flex;flex-wrap:wrap;gap:1em;margin:1em 0;">';
	foreach ( $cards as $c ) {
		$out .= '<div class="card" style="flex:1 1 150px;margin:0;padding:1em;">'
			. '<p class="description" style="margin:0 0 .25em;">' . esc_html( $c[0] ) . '</p>'
			. '<p style="font-size:1.6em;font-weight:600;margin:0;">' . esc_html( $c[1] ) . '</p>'
			. '</div>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * The 28-day trends: clicks, impressions, CTR and position, each a MiniChart of bars over time (one axis each — dataviz
 * forbids a shared axis across different measures, so they are four charts, not one).
 *
 * @param array<int, array<string, float|int|string>> $trend Daily rows from insightweb_overview() 'trend'.
 * @return string
 */
function insightweb_overview_trends( array $trend ): string {
	$out = '<h2>' . esc_html__( 'Last 28 days', 'insightweb' ) . '</h2>';
	if ( count( $trend ) < 2 ) {
		return $out . '<p>' . esc_html__( 'No daily figures yet — the trends appear once Search Console data has been read.', 'insightweb' ) . '</p>';
	}
	$series = array(
		array( __( 'Clicks', 'insightweb' ), 'clicks', '' ),
		array( __( 'Impressions', 'insightweb' ), 'impr', '' ),
		array( __( 'CTR', 'insightweb' ), 'ctr', '%' ),
		array( __( 'Average position', 'insightweb' ), 'pos', '' ),
	);
	$out   .= '<div style="display:flex;flex-wrap:wrap;gap:1em;">';
	foreach ( $series as $s ) {
		$points = array();
		foreach ( $trend as $r ) {
			$points[] = array(
				'value' => (float) $r[ $s[1] ],
				'label' => mysql2date( 'j M', (string) $r['d'] ),
			);
		}
		$out .= '<div style="flex:1 1 280px;">' . insightweb_minichart( $s[0], $points, $s[2] ) . '</div>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * The gap-action, priority and status breakdowns, each a MiniChart of one bar per category.
 *
 * @param array<string, mixed> $o Overview from insightweb_overview().
 * @return string
 */
function insightweb_overview_breakdowns( array $o ): string {
	$out  = '<h2>' . esc_html__( 'Where to spend effort', 'insightweb' ) . '</h2>';
	$out .= '<div style="display:flex;flex-wrap:wrap;gap:1em;">';

	// Suggested next actions (the differentiator) — biggest category last, so the attention bar is the top action.
	$labels  = insightweb_actions();
	$actions = $o['action'];
	asort( $actions );
	$points = array();
	foreach ( $actions as $key => $n ) {
		$points[] = array(
			'value' => (int) $n,
			'label' => $labels[ $key ] ?? $key,
		);
	}
	if ( $points ) {
		$out .= '<div style="flex:1 1 280px;">' . insightweb_minichart( __( 'Suggested next actions', 'insightweb' ), $points, __( 'keywords', 'insightweb' ) ) . '</div>';
	}

	// Priority split.
	$plabels = array(
		'P3' => __( 'P3 watch', 'insightweb' ),
		'P2' => __( 'P2 gap', 'insightweb' ),
		'P1' => __( 'P1 act now', 'insightweb' ),
	);
	$ppoints = array();
	foreach ( $plabels as $key => $label ) {
		$ppoints[] = array(
			'value' => (int) ( $o['priority'][ $key ] ?? 0 ),
			'label' => $label,
		);
	}
	$out .= '<div style="flex:1 1 280px;">' . insightweb_minichart( __( 'By priority', 'insightweb' ), $ppoints, __( 'keywords', 'insightweb' ) ) . '</div>';

	// Status split.
	$slabels = array(
		'none'     => __( 'Unranked', 'insightweb' ),
		'has-page' => __( 'Has a page', 'insightweb' ),
		'gap'      => __( 'Gap', 'insightweb' ),
		'conflict' => __( 'Conflict', 'insightweb' ),
	);
	$spoints = array();
	foreach ( $slabels as $key => $label ) {
		$spoints[] = array(
			'value' => (int) ( $o['status'][ $key ] ?? 0 ),
			'label' => $label,
		);
	}
	$out .= '<div style="flex:1 1 280px;">' . insightweb_minichart( __( 'By status', 'insightweb' ), $spoints, __( 'keywords', 'insightweb' ) ) . '</div>';

	$out .= '</div>';
	return $out;
}

/**
 * MiniChart: the suite's shared chart contract, server-rendered. An accessible <figure> of inline-SVG bars normalised
 * to the largest value — the LAST bar inked for attention (--insightweb-ink), the rest greyscale (--insightweb-bar) —
 * with a <figcaption> of the label and the first-to-last range and a visually hidden data table (values with the unit)
 * so the data is read by assistive technology, not seen in the bars.
 *
 * @param string                                             $label  The series label (names the chart).
 * @param array<int, array{value: float|int, label: string}> $points One bar each; the last is the attention bar.
 * @param string                                             $unit   Unit appended to each value in the hidden table ('' for none).
 * @return string
 */
function insightweb_minichart( string $label, array $points, string $unit = '' ): string {
	$points = array_values( $points );
	$n      = count( $points );
	if ( 0 === $n ) {
		return '';
	}
	$unit_txt = '' === $unit ? '' : ( '%' === $unit ? $unit : ' ' . $unit );

	$max = 0.0;
	foreach ( $points as $p ) {
		$max = max( $max, (float) $p['value'] );
	}
	$max = $max > 0 ? $max : 1.0;

	// Geometry: a fixed viewBox, bars with a 2px surface gap, 4px rounded data-ends anchored to the baseline.
	$h   = 80;
	$gap = 2;
	$bw  = 10;
	$w   = max( 1, $n * ( $bw + $gap ) - $gap );
	$svg = '<svg role="img" aria-hidden="true" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" preserveAspectRatio="none" style="width:100%;height:80px;display:block;">';
	foreach ( $points as $i => $p ) {
		$bh   = (float) $p['value'] / $max * ( $h - 2 );
		$bh   = $bh > 0 ? max( 1.0, $bh ) : 0.0;
		$x    = $i * ( $bw + $gap );
		$y    = $h - $bh;
		$fill = ( $i === $n - 1 ) ? 'var(--insightweb-ink,#1d2327)' : 'var(--insightweb-bar,#c3c4c7)';
		$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . (int) $bw . '" height="' . round( $bh, 1 ) . '" rx="2" fill="' . $fill . '" />';
	}
	$svg .= '</svg>';

	$first = $points[0]['label'];
	$last  = $points[ $n - 1 ]['label'];
	/* translators: 1: chart label, 2: first bar label, 3: last bar label. */
	$caption = sprintf( __( '%1$s — %2$s to %3$s', 'insightweb' ), $label, (string) $first, (string) $last );

	$table = '<table><caption>' . esc_html( $label ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Point', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Value', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $points as $p ) {
		$decimals = ( floor( (float) $p['value'] ) === (float) $p['value'] ) ? 0 : 2;
		$table   .= '<tr><th scope="row">' . esc_html( (string) $p['label'] ) . '</th><td>' . esc_html( number_format_i18n( (float) $p['value'], $decimals ) . $unit_txt ) . '</td></tr>';
	}
	$table .= '</tbody></table>';

	return '<figure class="insightweb-minichart" style="margin:0;">'
		. $svg
		. '<figcaption>' . esc_html( $caption ) . '</figcaption>'
		. '<div class="screen-reader-text">' . $table . '</div>'
		. '</figure>';
}

/**
 * The top queries table.
 *
 * @param object[] $rows Rows from insightweb_overview() 'top' (label, impr28, clicks28, pos28).
 * @return string
 */
function insightweb_overview_top( array $rows ): string {
	$out = '<h2>' . esc_html__( 'Top queries (28 days)', 'insightweb' ) . '</h2>';
	if ( ! $rows ) {
		return $out . '<p>' . esc_html__( 'No impressions yet.', 'insightweb' ) . '</p>';
	}
	$out .= '<table class="wp-list-table widefat striped"><thead><tr>'
		. '<th scope="col">' . esc_html__( 'Keyword', 'insightweb' ) . '</th>'
		. '<th scope="col" style="text-align:right;">' . esc_html__( 'Impressions', 'insightweb' ) . '</th>'
		. '<th scope="col" style="text-align:right;">' . esc_html__( 'Clicks', 'insightweb' ) . '</th>'
		. '<th scope="col" style="text-align:right;">' . esc_html__( 'Position', 'insightweb' ) . '</th>'
		. '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$out .= '<tr><td>' . esc_html( (string) $r->label ) . '</td>'
			. '<td style="text-align:right;">' . esc_html( number_format_i18n( (int) $r->impr28 ) ) . '</td>'
			. '<td style="text-align:right;">' . esc_html( number_format_i18n( (int) $r->clicks28 ) ) . '</td>'
			. '<td style="text-align:right;">' . esc_html( null === $r->pos28 ? '–' : number_format_i18n( (float) $r->pos28, 1 ) ) . '</td></tr>';
	}
	$out .= '</tbody></table>';
	return $out;
}
