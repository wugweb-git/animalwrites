<?php
/**
 * Campaign kit: countdown, and the private upload folder used by listing claims.
 * (The proof-of-posting receipts and live counts were removed on 8 Oct 2026; they had no entries.)
 *
 * Shortcode:
 *   [aw_countdown date="2026-11-29" label="Posting day"]
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Private folder (blocked from the web), used for claim logos. */
function aw_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'aw-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	return $dir;
}

/* ---------- Countdown ---------- */
add_shortcode(
	'aw_countdown',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'date' => '', 'label' => '' ), $atts );
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['date'] ) ? $a['date'] : '';
		if ( ! $date ) {
			return '';
		}
		$ts   = strtotime( $date . ' 00:00:00 Asia/Kolkata' );
		$days = (int) ceil( ( $ts - time() ) / DAY_IN_SECONDS );
		$when = wp_date( AW_DATE_FORMAT, $ts, new DateTimeZone( 'Asia/Kolkata' ) );
		$lbl  = $a['label'] ? esc_html( $a['label'] ) . ': ' : '';
		if ( $days > 1 ) {
			$text = sprintf( '<span class="aw-cd-num">%d</span> <span class="aw-cd-unit">days to go</span>', $days );
		} elseif ( 1 === $days ) {
			$text = '<span class="aw-cd-num">1</span> <span class="aw-cd-unit">day to go</span>';
		} elseif ( 0 === $days ) {
			$text = '<span class="aw-cd-num">Today</span>';
		} else {
			$text = '<span class="aw-cd-unit">Took place on</span>';
		}
		return '<p class="aw-countdown" data-date="' . esc_attr( $date ) . '">' . $text . ' <span class="aw-cd-when">' . $lbl . esc_html( $when ) . '</span></p>';
	}
);

