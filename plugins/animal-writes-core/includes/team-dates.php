<?php
/**
 * Dates (owner, 21 Oct 2026): the team's own calendar inside WordPress. Animal Writes → Dates shows a month grid, the list of
 * dates with their notes, and a form to add or remove one; the Dashboard shows what is next. No plugin and no public page:
 * The Events Calendar was installed but inactive, and it would add public event pages and an /events/ archive to the
 * sitemap. The dates live in the option aw_team_dates: a list of {date Y-m-d, title, note}.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_DATES_CAP = 'edit_others_posts'; // editors and administrators

/** The dates that start the calendar (seeded once; docs/pending.md lists the same ones). */
function aw_team_dates_seed() {
	return array(
		array( 'date' => '2026-11-05', 'title' => 'Lab rules objections close', 'note' => 'Campaign 13848. The countdown on the home page and the campaign page ends today.' ),
		array( 'date' => '2026-11-07', 'title' => 'Decide the lab rules Post phase', 'note' => 'Owner decides what 13848 shows after closing. Until then no closed-state or Post content and no FAQ visibility changes on it.' ),
		array( 'date' => '2026-11-17', 'title' => 'Directory thin-page re-check and keyword positions', 'note' => 'Scheduled tasks at 10:00 (Directory indexing against the 20 Oct baseline) and 11:00 (8 tracked keywords).' ),
	);
}

/** All dates, oldest first. */
function aw_team_dates() {
	$dates = get_option( 'aw_team_dates', null );
	if ( ! is_array( $dates ) ) {
		$dates = aw_team_dates_seed();
		add_option( 'aw_team_dates', $dates, '', false );
	}
	$dates = array_values( array_filter( $dates, fn( $d ) => is_array( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['date'] ?? '' ) ) ) );
	usort( $dates, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
	return $dates;
}

/**
 * Monthly reminder (owner, 31 Oct 2026): the Search Console Links read on the 1st. Dates are one-off entries, so the
 * next 12 months are added once (flag aw_team_dates_links_read); each can be removed like any other date.
 */
function aw_team_dates_add_links_read() {
	if ( get_option( 'aw_team_dates_links_read' ) ) {
		return;
	}
	$title = 'Search Console Links read (Claude Code session)';
	$all   = aw_team_dates();
	$have  = array();
	foreach ( $all as $d ) {
		$have[ $d['date'] . '|' . $d['title'] ] = true;
	}
	$first = new DateTimeImmutable( 'first day of next month', wp_timezone() );
	for ( $i = 0; $i < 12; $i++ ) {
		$ymd = $first->modify( '+' . $i . ' months' )->format( 'Y-m-d' );
		if ( ! isset( $have[ $ymd . '|' . $title ] ) ) {
			$all[] = array( 'date' => $ymd, 'title' => $title, 'note' => 'Read Search Console → Links and add new linking pages with wp aw-si links add (they show in AW SEO Plus → Search Insights → Links).' );
		}
	}
	update_option( 'aw_team_dates', $all, false );
	update_option( 'aw_team_dates_links_read', time(), false );
}
add_action( 'admin_init', 'aw_team_dates_add_links_read' );

/**
 * The FAQ holdout ended early (owner, 31 Oct 2026: no holdout): its 1 Dec date leaves the calendar, once (flag
 * aw_team_dates_faq_holdout_off).
 */
function aw_team_dates_drop_faq_holdout() {
	if ( get_option( 'aw_team_dates_faq_holdout_off' ) ) {
		return;
	}
	$all  = aw_team_dates();
	$keep = array_values( array_filter( $all, fn( $d ) => 'FAQ holdout ends' !== ( $d['title'] ?? '' ) ) );
	if ( count( $keep ) !== count( $all ) ) {
		update_option( 'aw_team_dates', $keep, false );
	}
	update_option( 'aw_team_dates_faq_holdout_off', time(), false );
}
add_action( 'admin_init', 'aw_team_dates_drop_faq_holdout' );

/** A date in the site's one format, j M Y. */
function aw_team_date_label( $ymd ) {
	return wp_date( 'j M Y', strtotime( $ymd . ' 12:00:00' ) );
}

/** "today", "tomorrow", "in 12 days" or "3 days ago". */
function aw_team_date_when( $ymd ) {
	$days = (int) round( ( strtotime( $ymd . ' 12:00:00' ) - strtotime( wp_date( 'Y-m-d' ) . ' 12:00:00' ) ) / DAY_IN_SECONDS );
	if ( 0 === $days ) {
		return 'today';
	}
	if ( 1 === $days ) {
		return 'tomorrow';
	}
	return $days > 0 ? 'in ' . $days . ' days' : abs( $days ) . ( -1 === $days ? ' day ago' : ' days ago' );
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( AW_CORE_MENU, 'Dates', 'Dates', AW_DATES_CAP, 'aw-dates', 'aw_team_dates_screen' ); // Animal Writes → Dates
	},
	10 // menu order: Overview (9) · Dates · Check site (11) · Contact links (12)
);

/** Animal Writes → Dates. */
function aw_team_dates_screen() {
	if ( ! current_user_can( AW_DATES_CAP ) ) {
		wp_die( 'Not allowed.', 403 );
	}
	$month = isset( $_GET['m'] ) && preg_match( '/^\d{4}-\d{2}$/', (string) $_GET['m'] ) ? (string) $_GET['m'] : wp_date( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification
	$first = strtotime( $month . '-01 12:00:00' );
	$prev  = wp_date( 'Y-m', strtotime( '-1 month', $first ) );
	$next  = wp_date( 'Y-m', strtotime( '+1 month', $first ) );
	$dates = aw_team_dates();
	$by    = array();
	foreach ( $dates as $d ) {
		$by[ $d['date'] ][] = $d;
	}
	$base  = admin_url( 'admin.php?page=aw-dates' );
	$lead  = (int) wp_date( 'N', $first ) - 1; // Monday first
	$days  = (int) wp_date( 't', $first );
	$today = wp_date( 'Y-m-d' );
	echo '<div class="wrap"><h1>Dates</h1>';
	echo '<p><a class="button" href="' . esc_url( add_query_arg( 'm', $prev, $base ) ) . '">‹ ' . esc_html( wp_date( 'F', strtotime( $prev . '-01 12:00:00' ) ) ) . '</a> <strong style="font-size:1.2em;margin:0 12px">' . esc_html( wp_date( 'F Y', $first ) ) . '</strong> <a class="button" href="' . esc_url( add_query_arg( 'm', $next, $base ) ) . '">' . esc_html( wp_date( 'F', strtotime( $next . '-01 12:00:00' ) ) ) . ' ›</a> <a class="button" href="' . esc_url( $base ) . '">Today</a></p>';
	echo '<table class="widefat fixed" style="table-layout:fixed;max-width:1100px"><thead><tr>';
	foreach ( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) as $h ) {
		echo '<th>' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody><tr>';
	for ( $i = 0; $i < $lead; $i++ ) {
		echo '<td style="background:#f6f7f7"></td>';
	}
	for ( $day = 1; $day <= $days; $day++ ) {
		$ymd = sprintf( '%s-%02d', $month, $day );
		if ( 0 === ( $lead + $day - 1 ) % 7 && $day > 1 ) {
			echo '</tr><tr>';
		}
		$style = 'vertical-align:top;height:84px;' . ( $ymd === $today ? 'background:#fff8e5;' : '' );
		echo '<td style="' . esc_attr( $style ) . '"><strong>' . (int) $day . '</strong>';
		foreach ( $by[ $ymd ] ?? array() as $d ) {
			echo '<div style="margin-top:4px;padding:2px 6px;background:#2271b1;color:#fff;border-radius:3px;font-size:12px;line-height:1.35">' . esc_html( $d['title'] ) . '</div>';
		}
		echo '</td>';
	}
	for ( $i = ( 7 - ( $lead + $days ) % 7 ) % 7; $i > 0; $i-- ) {
		echo '<td style="background:#f6f7f7"></td>';
	}
	echo '</tr></tbody></table>';

	echo '<h2 style="margin-top:28px">All dates</h2>';
	$cells = array();
	foreach ( $dates as $i => $d ) {
		$del     = wp_nonce_url( admin_url( 'admin-post.php?action=aw_dates_delete&i=' . (int) $i . '&d=' . rawurlencode( $d['date'] ) ), 'aw_dates_delete_' . $i );
		$cells[] = array(
			'date'   => esc_html( aw_team_date_label( $d['date'] ) ),
			'when'   => esc_html( aw_team_date_when( $d['date'] ) ),
			'what'   => '<strong>' . esc_html( $d['title'] ) . '</strong>' . ( '' !== $d['note'] ? '<br><span class="description">' . esc_html( $d['note'] ) . '</span>' : '' ),
			'remove' => '<a href="' . esc_url( $del ) . '" onclick="return confirm(\'Remove this date?\')">Remove</a>',
		);
	}
	aw_core_simple_table( 'dates', array( 'date' => 'Date', 'when' => 'When', 'what' => 'What', 'remove' => '<span class="screen-reader-text">Remove</span>' ), $cells, array( 'empty' => 'No dates yet.' ) );

	echo '<h2 style="margin-top:28px">Add a date</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_dates_add">' . wp_nonce_field( 'aw_dates_add', '_wpnonce', true, false );
	echo '<p><label>Date <input type="date" name="date" required></label> <label>What <input type="text" name="title" class="regular-text" required></label></p><p><label>Note<br><textarea name="note" rows="3" class="large-text" style="max-width:640px"></textarea></label></p><p><button class="button button-primary">Add</button></p></form></div>';
}

add_action(
	'admin_post_aw_dates_add',
	function () {
		if ( ! current_user_can( AW_DATES_CAP ) || ! check_admin_referer( 'aw_dates_add' ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$name = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && '' !== $name ) {
			$all   = aw_team_dates();
			$all[] = array( 'date' => $date, 'title' => $name, 'note' => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ) );
			update_option( 'aw_team_dates', $all, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=aw-dates&m=' . substr( $date, 0, 7 ) ) );
		exit;
	}
);

add_action(
	'admin_post_aw_dates_delete',
	function () {
		$i = (int) ( $_GET['i'] ?? -1 );
		if ( ! current_user_can( AW_DATES_CAP ) || ! check_admin_referer( 'aw_dates_delete_' . $i ) ) {
			wp_die( 'Not allowed.', 403 );
		}
		$all = aw_team_dates();
		if ( isset( $all[ $i ] ) && $all[ $i ]['date'] === sanitize_text_field( wp_unslash( $_GET['d'] ?? '' ) ) ) {
			unset( $all[ $i ] );
			update_option( 'aw_team_dates', array_values( $all ), false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=aw-dates' ) );
		exit;
	}
);

// Dashboard: what is next (dates of the last 3 days and the next 60, up to 6).
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( AW_DATES_CAP ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'aw_team_dates',
			'Dates',
			function () {
				$from = wp_date( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
				$to   = wp_date( 'Y-m-d', time() + 60 * DAY_IN_SECONDS );
				$next = array_slice( array_values( array_filter( aw_team_dates(), fn( $d ) => $d['date'] >= $from && $d['date'] <= $to ) ), 0, 6 );
				if ( ! $next ) {
					echo '<p>Nothing in the next 60 days.</p>';
				} else {
					echo '<ul style="margin:0">';
					foreach ( $next as $d ) {
						echo '<li style="margin:0 0 8px"><strong>' . esc_html( aw_team_date_label( $d['date'] ) ) . '</strong> · ' . esc_html( aw_team_date_when( $d['date'] ) ) . '<br>' . esc_html( $d['title'] ) . '</li>';
					}
					echo '</ul>';
				}
				echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=aw-dates' ) ) . '">Open the calendar</a></p>';
			}
		);
	}
);
