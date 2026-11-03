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
function kits_dash_bars( array $values, string $label, bool $table = false ): string {
	if ( ! $values ) {
		return '';
	}
	// Mark spec (dataviz, 3 Nov 2026): bars at most 24px wide with a 2px surface gap, a 4px rounded data-end and a
	// square baseline, one recessive baseline, and only the peak labelled (never a number on every bar).
	$n    = count( $values );
	$max  = max( 1, max( array_map( 'floatval', $values ) ) );
	$w    = 280;
	$h    = 72;
	$top  = 14; // room for the peak label
	$gap  = 2;
	$slot = ( $w - $gap * ( $n - 1 ) ) / $n;
	$bw   = min( 24, max( 2, $slot ) );
	$keys = array_keys( $values );
	$sum  = sprintf( '%1$s: from %2$s (%3$s) to %4$s (%5$s), highest %6$s.', $label, $keys[0], number_format_i18n( (float) reset( $values ) ), end( $keys ), number_format_i18n( (float) end( $values ) ), number_format_i18n( $max ) );
	$bars = '';
	$peak = '';
	$i    = 0;
	foreach ( $values as $k => $v ) {
		$v   = (float) $v;
		$bh  = $v > 0 ? max( 1, round( ( $v / $max ) * ( $h - $top ), 1 ) ) : 0;
		$x   = round( $i * ( $slot + $gap ) + ( $slot - $bw ) / 2, 1 );
		$y   = $h - $bh;
		$r   = min( 4, $bw / 2, $bh );
		$tip = '<title>' . esc_html( $k . ': ' . number_format_i18n( $v ) ) . '</title>';
		if ( $bh > 0 ) {
			// Rounded top corners, square at the baseline.
			$d     = sprintf( 'M%1$s %2$sV%3$sQ%1$s %4$s %5$s %4$sH%6$sQ%7$s %4$s %7$s %3$sV%2$sZ', $x, $h, $y + $r, $y, $x + $r, $x + $bw - $r, $x + $bw );
			$bars .= '<path d="' . esc_attr( $d ) . '">' . $tip . '</path>';
		}
		if ( '' === $peak && $v >= $max ) {
			$peak = '<text x="' . round( $x + $bw / 2, 1 ) . '" y="' . max( 10, $y - 3 ) . '" text-anchor="middle">' . esc_html( number_format_i18n( $v ) ) . '</text>';
		}
		++$i;
	}
	$base = '<line x1="0" y1="' . $h . '" x2="' . $w . '" y2="' . $h . '"></line>';
	$out  = '<figure class="aw-dash-chart"><svg viewBox="0 0 ' . $w . ' ' . ( $h + 1 ) . '" role="img" aria-label="' . esc_attr( $sum ) . '">' . $bars . $base . $peak . '</svg><figcaption class="description">' . esc_html( $label ) . '</figcaption></figure>';
	if ( $table ) { // the exact figures (a table view for every chart)
		$rows = '';
		foreach ( array_reverse( $values, true ) as $k => $v ) {
			$rows .= '<tr><td>' . esc_html( (string) $k ) . '</td><td>' . esc_html( number_format_i18n( (float) $v ) ) . '</td></tr>';
		}
		$out .= '<details><summary>' . esc_html( 'Figures' ) . '</summary><table class="widefat striped"><thead><tr><th scope="col">Day</th><th scope="col">' . esc_html( $label ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
	}
	return $out;
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
.aw-dash-chart svg{display:block;width:100%;height:auto;max-height:120px;fill:#2271b1;overflow:visible}
.aw-dash-chart line{stroke:#c3c4c7;stroke-width:1}
.aw-dash-chart text{fill:#50575e;font-size:10px}
.aw-dash-chart figcaption{margin-top:4px}
</style>
		<?php
	}
);
