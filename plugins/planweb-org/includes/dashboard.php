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
	$end   = wp_date( 'Y-m-d', time() + 6 * DAY_IN_SECONDS );
	// One schedule: PlanWeb's entries and the site's own (scheduled posts, missed ones, newsletters), by date and time.
	$site = array_filter( planweb_site_items( $today, $end ), fn( $i ) => 'published' !== $i['status'] && 'sent' !== $i['status'] );
	$next = array_merge( planweb_entries( $today, $end ), $site );
	usort( $next, fn( $a, $b ) => strcmp( $a['date'] . ' ' . ( '' === $a['time'] ? '99:99' : $a['time'] ), $b['date'] . ' ' . ( '' === $b['time'] ? '99:99' : $b['time'] ) ) );
	if ( ! $next ) {
		echo '<p>' . esc_html__( 'Nothing in the next 7 days.', 'planweb' ) . '</p>';
	} else {
		echo '<ul class="planweb-tiles">';
		foreach ( array_slice( $next, 0, 10 ) as $e ) {
			$t = strtotime( $e['date'] . ' 12:00:00' );
			echo '<li class="planweb-tile-row' . ( $e['date'] === $today ? ' planweb-tile-row--today' : '' ) . '"><span class="planweb-tile" aria-hidden="true"><span class="planweb-tile-mon">' . esc_html( wp_date( 'M', $t ) ) . '</span><span class="planweb-tile-day">' . esc_html( wp_date( 'j', $t ) ) . '</span></span>'
				. '<span class="planweb-tile-text"><span class="screen-reader-text">' . esc_html( planweb_date_label( $e['date'] ) ) . ': </span><strong>' . esc_html( ( '' !== $e['time'] ? $e['time'] . ' ' : '' ) . $e['title'] ) . '</strong>'
				. '<span class="planweb-tile-meta">' . ( isset( $e['source'] ) ? planweb_site_badges( $e ) : planweb_type_badge( $e['type'] ) . ' ' . planweb_status_badge( $e['status'] ) ) . ' · ' . esc_html( planweb_when( $e['date'] ) ) . '</span></span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the badges escape their parts.
		}
		echo '</ul>';
		if ( count( $next ) > 10 ) {
			/* translators: %d: number of further entries. */
			echo '<p>' . esc_html( sprintf( __( 'And %d more.', 'planweb' ), count( $next ) - 10 ) ) . '</p>';
		}
	}
	echo '<p><a href="' . esc_url( planweb_url() ) . '">' . esc_html__( 'Open the calendar', 'planweb' ) . '</a></p>';
}

/**
 * A site item's marks: its kind (Resource, Newsletter …) and its status, as the entry badges look.
 *
 * @param array $i planweb_site_items() row.
 */
function planweb_site_badges( array $i ): string {
	return '<span class="planweb-type planweb-type--site"><span class="planweb-mark" aria-hidden="true"></span>' . esc_html( $i['typeText'] ) . '</span> <span class="planweb-status planweb-status--' . esc_attr( $i['status'] ) . '">' . esc_html( $i['statusText'] ) . '</span>';
}
