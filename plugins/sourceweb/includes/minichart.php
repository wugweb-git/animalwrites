<?php
/**
 * MiniChart: the suite-standard tiny bar chart, rendered server-side as an accessible inline-SVG figure — no
 * JavaScript, no charting dependency, and only the admin's own greyscale inks (the shell theme is untouched).
 *
 * Bars are normalised to the largest value. The LAST bar is drawn in ink as the point of attention (the most
 * recent day, or the end of the funnel); the rest are grey. Each chart is a <figure> with a figcaption (the
 * label and the first→last range) and a visually hidden data table, so the figures are never colour-alone and
 * the numbers are available to a screen reader exactly as the bars show them.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render one MiniChart.
 *
 * @param array $args {
 *     Chart arguments.
 *
 *     @type string                                    $label  Chart label (the figcaption title and the table caption).
 *     @type array<int,array{value:float,label:string}> $points One bar each; the last is the attention bar.
 *     @type string                                    $unit   Optional unit, appended in the data table.
 * }
 * @return void
 */
function sourceweb_minichart( array $args ) {
	$label  = isset( $args['label'] ) ? (string) $args['label'] : '';
	$unit   = isset( $args['unit'] ) ? (string) $args['unit'] : '';
	$points = array();
	foreach ( isset( $args['points'] ) && is_array( $args['points'] ) ? $args['points'] : array() as $point ) {
		$points[] = array(
			'value' => isset( $point['value'] ) ? (float) $point['value'] : 0.0,
			'label' => isset( $point['label'] ) ? (string) $point['label'] : '',
		);
	}

	echo '<figure class="sourceweb-minichart" role="group" aria-label="' . esc_attr( $label ) . '">';

	if ( ! $points ) {
		echo '<figcaption class="sourceweb-minichart__cap">' . esc_html( $label ) . '</figcaption>';
		echo '<p class="sourceweb-status">' . esc_html__( 'No data yet.', 'sourceweb' ) . '</p>';
		echo '</figure>';
		return;
	}

	$count  = count( $points );
	$values = wp_list_pluck( $points, 'value' );
	$max    = max( 1.0, (float) max( $values ) );

	// SVG geometry (scales responsively via CSS width:100%).
	$width   = 320;
	$height  = 120;
	$top_pad = 10;
	$plot_h  = $height - $top_pad;
	$slot    = $width / $count;
	$bar_w   = $slot * 0.68;

	echo '<svg class="sourceweb-minichart__svg" viewBox="0 0 ' . (int) $width . ' ' . (int) $height . '" width="100%" height="' . (int) $height . '" aria-hidden="true" focusable="false">';
	foreach ( $points as $i => $point ) {
		$bar_h = ( $point['value'] / $max ) * $plot_h;
		$bar_h = $bar_h < 1 && $point['value'] > 0 ? 1.0 : $bar_h;
		$x     = ( $i * $slot ) + ( ( $slot - $bar_w ) / 2 );
		$y     = $height - $bar_h;
		$fill  = ( $i === $count - 1 ) ? '#1d2327' : '#c3c4c7';
		echo '<rect x="' . esc_attr( (string) round( $x, 2 ) ) . '" y="' . esc_attr( (string) round( $y, 2 ) ) . '" width="' . esc_attr( (string) round( $bar_w, 2 ) ) . '" height="' . esc_attr( (string) round( $bar_h, 2 ) ) . '" rx="2" fill="' . esc_attr( $fill ) . '"></rect>';
	}
	echo '</svg>';

	$first = $points[0];
	$last  = $points[ $count - 1 ];
	echo '<figcaption class="sourceweb-minichart__cap">';
	echo '<span class="sourceweb-minichart__label">' . esc_html( $label ) . '</span> ';
	echo '<span class="sourceweb-minichart__range">';
	echo esc_html(
		sprintf(
			/* translators: 1: first label, 2: first value, 3: last label, 4: last value. */
			__( '%1$s: %2$s → %3$s: %4$s', 'sourceweb' ),
			$first['label'],
			number_format_i18n( $first['value'] ),
			$last['label'],
			number_format_i18n( $last['value'] )
		)
	);
	echo '</span>';
	echo '</figcaption>';

	// The hidden data table (screen-reader and no-SVG fallback).
	echo '<table class="sourceweb-screen-reader-text">';
	echo '<caption>' . esc_html( $label ) . '</caption>';
	echo '<thead><tr><th scope="col">' . esc_html__( 'Point', 'sourceweb' ) . '</th><th scope="col">' . esc_html__( 'Value', 'sourceweb' ) . '</th></tr></thead><tbody>';
	foreach ( $points as $point ) {
		$value_text = '' !== $unit
			? sprintf( '%s %s', number_format_i18n( $point['value'] ), $unit )
			: number_format_i18n( $point['value'] );
		echo '<tr><th scope="row">' . esc_html( $point['label'] ) . '</th><td>' . esc_html( $value_text ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '</figure>';
}
