<?php
/**
 * Dashboard widget parts (owner, 3 Nov 2026: Dashboard widgets may use stat cards and small charts; every other admin
 * screen stays core-only). Plain HTML and inline SVG, no chart library and no script. WordPress admin colours, text
 * that says up or down (never colour alone), and the exact figures stay in a table under the chart.
 *
 * kits_dash_cards( $cards ): a row of stat cards. Each card: label, value (already formatted), and optionally
 * delta (number, the change), delta_text (what it is compared with, e.g. "on the 7 days before").
 * kits_dash_bars( $values, $label ): a small bar chart; $values is label => number, oldest first.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stat cards.
 *
 * @param array $cards Cards: label, value, delta (optional), delta_text (optional).
 * @return string HTML.
 */
function kits_dash_cards( array $cards ): string {
	$out = '<ul class="aw-dash-cards">';
	foreach ( $cards as $c ) {
		$out .= '<li class="aw-dash-card"><span class="aw-dash-label">' . esc_html( (string) ( $c['label'] ?? '' ) ) . '</span>';
		$out .= '<span class="aw-dash-value">' . esc_html( (string) ( $c['value'] ?? '' ) ) . '</span>';
		if ( isset( $c['delta'] ) && is_numeric( $c['delta'] ) ) {
			$d    = (float) $c['delta'];
			$dir  = $d > 0 ? 'up' : ( $d < 0 ? 'down' : 'flat' );
			$mark = 'up' === $dir ? '▲' : ( 'down' === $dir ? '▼' : '=' );
			$word = 'up' === $dir ? 'Up' : ( 'down' === $dir ? 'Down' : 'Same' );
			$num  = 'flat' === $dir ? '' : ' ' . number_format_i18n( abs( $d ) );
			$out .= '<span class="aw-dash-delta aw-dash-delta--' . $dir . '"><span aria-hidden="true">' . $mark . '</span> ' . esc_html( $word . $num . ( empty( $c['delta_text'] ) ? '' : ' ' . $c['delta_text'] ) ) . '</span>';
		}
		$out .= '</li>';
	}
	return $out . '</ul>';
}

/**
 * A small bar chart (inline SVG). Screen readers get one sentence; the figures themselves are in the table the widget
 * prints under it.
 *
 * @param array  $values Label => number, oldest first.
 * @param string $label  What the bars are, e.g. "Visits per day, last 14 days".
 * @return string HTML.
 */
function kits_dash_bars( array $values, string $label ): string {
	if ( ! $values ) {
		return '';
	}
	$n    = count( $values );
	$max  = max( 1, max( array_map( 'floatval', $values ) ) );
	$w    = 280;
	$h    = 64;
	$gap  = 2;
	$bw   = max( 2, ( $w - $gap * ( $n - 1 ) ) / $n );
	$keys = array_keys( $values );
	$sum  = sprintf( '%1$s: from %2$s (%3$s) to %4$s (%5$s), highest %6$s.', $label, $keys[0], number_format_i18n( (float) reset( $values ) ), end( $keys ), number_format_i18n( (float) end( $values ) ), number_format_i18n( $max ) );
	$bars = '';
	$i    = 0;
	foreach ( $values as $k => $v ) {
		$bh    = max( 1, round( ( (float) $v / $max ) * ( $h - 4 ), 1 ) );
		$x     = round( $i * ( $bw + $gap ), 1 );
		$bars .= '<rect x="' . $x . '" y="' . ( $h - $bh ) . '" width="' . round( $bw, 1 ) . '" height="' . $bh . '"><title>' . esc_html( $k . ': ' . number_format_i18n( (float) $v ) ) . '</title></rect>';
		++$i;
	}
	return '<figure class="aw-dash-chart"><svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" role="img" aria-label="' . esc_attr( $sum ) . '" preserveAspectRatio="none">' . $bars . '</svg><figcaption class="description">' . esc_html( $label ) . '</figcaption></figure>';
}

// The styles, once, on the Dashboard only.
add_action(
	'admin_head-index.php',
	function () {
		?>
<style id="aw-dash-cards">
.aw-dash-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,9em),1fr));gap:8px;margin:0 0 12px;padding:0;list-style:none}
.aw-dash-card{min-width:0;margin:0;padding:12px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;display:flex;flex-direction:column;gap:2px}
.aw-dash-label{color:#50575e;font-size:13px;overflow-wrap:anywhere}
.aw-dash-value{color:#1d2327;font-size:24px;font-weight:600;line-height:1.3;overflow-wrap:anywhere}
.aw-dash-delta{font-size:13px}
.aw-dash-delta--up{color:#007017}
.aw-dash-delta--down{color:#b32d2e}
.aw-dash-delta--flat{color:#50575e}
.aw-dash-chart{margin:0 0 12px}
.aw-dash-chart svg{display:block;fill:#2271b1}
.aw-dash-chart figcaption{margin-top:4px}
</style>
		<?php
	}
);
