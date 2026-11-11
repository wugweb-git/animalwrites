<?php
/**
 * The WordPress Dashboard box (replaces Dates' box): the next 7 days, each with its Type and Status, as calendar tiles
 * (owner, 3 Nov 2026: "simple calendar block layout"). Server-rendered, no script; styles in assets/admin.css.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! planweb_on() || ! current_user_can( 'edit_planweb_entries' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'planweb_next', __( 'Next 7 days', 'planweb' ), 'planweb_dashboard_widget' );
	}
);

/** The box. */
function planweb_dashboard_widget() {
	$today = wp_date( 'Y-m-d' );
	$next  = planweb_entries( $today, wp_date( 'Y-m-d', time() + 6 * DAY_IN_SECONDS ) );
	if ( ! $next ) {
		echo '<p>' . esc_html__( 'Nothing in the next 7 days.', 'planweb' ) . '</p>';
	} else {
		echo '<ul class="planweb-tiles">';
		foreach ( array_slice( $next, 0, 10 ) as $e ) {
			$t = strtotime( $e['date'] . ' 12:00:00' );
			echo '<li class="planweb-tile-row' . ( $e['date'] === $today ? ' planweb-tile-row--today' : '' ) . '"><span class="planweb-tile" aria-hidden="true"><span class="planweb-tile-mon">' . esc_html( wp_date( 'M', $t ) ) . '</span><span class="planweb-tile-day">' . esc_html( wp_date( 'j', $t ) ) . '</span></span>'
				. '<span class="planweb-tile-text"><span class="screen-reader-text">' . esc_html( planweb_date_label( $e['date'] ) ) . ': </span><strong>' . esc_html( ( '' !== $e['time'] ? $e['time'] . ' ' : '' ) . $e['title'] ) . '</strong>'
				. '<span class="planweb-tile-meta">' . planweb_type_badge( $e['type'] ) . ' ' . planweb_status_badge( $e['status'] ) . ' · ' . esc_html( planweb_when( $e['date'] ) ) . '</span></span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the badges escape their parts.
		}
		echo '</ul>';
		if ( count( $next ) > 10 ) {
			/* translators: %d: number of further entries. */
			echo '<p>' . esc_html( sprintf( __( 'And %d more.', 'planweb' ), count( $next ) - 10 ) ) . '</p>';
		}
	}
	echo '<p><a href="' . esc_url( planweb_url() ) . '">' . esc_html__( 'Open the calendar', 'planweb' ) . '</a></p>';
}
