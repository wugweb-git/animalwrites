<?php
/**
 * Scheduled-task (WP-Cron) health (owner, 20 Oct 2026). WordPress's own cron is off (DISABLE_WP_CRON); wp-cron.php is
 * called from outside by more than one trigger, and the first to arrive does the work (WordPress locks the run):
 * - hostinger: the server cron in hPanel, every 5 minutes (/usr/bin/php .../public_html/wp-cron.php, PHP from the shell)
 * - github: the workers repository's "WordPress cron" workflow, every 15 minutes, often late (adds ?aw_tick=)
 * - uptimerobot / cron-job.org: an outside monitor calling https://animalwrites.in/wp-cron.php
 * Each call is recorded per trigger (last 48 hours). No run for 30 minutes is an ingestion-watchdog problem
 * (relevance.php), checked by any request that reaches PHP, so the alert does not depend on cron or GitHub. A dashboard
 * box shows each trigger's last run, runs in 24 hours and longest gap.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_CRON_STALE = 30 * MINUTE_IN_SECONDS;
const AW_CRON_KEEP  = 2 * DAY_IN_SECONDS;

/** Which trigger called wp-cron.php. */
function aw_cron_trigger() {
	if ( 'cli' === PHP_SAPI ) {
		return 'hostinger';
	}
	if ( isset( $_GET['aw_tick'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return 'github';
	}
	$ua = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( false !== strpos( $ua, 'uptimerobot' ) ) {
		return 'uptimerobot';
	}
	if ( false !== strpos( $ua, 'cron-job.org' ) ) {
		return 'cron-job.org';
	}
	return 'other';
}

/** Trigger => list of Unix times (last 48 hours). */
function aw_cron_runs() {
	return (array) get_option( 'aw_cron_runs', array() );
}

/** Unix time of the newest call from any trigger, or 0. */
function aw_cron_last() {
	$last = 0;
	foreach ( aw_cron_runs() as $times ) {
		$last = max( $last, (int) max( array_merge( array( 0 ), (array) $times ) ) );
	}
	return $last;
}

add_action(
	'init',
	function () {
		if ( ! wp_doing_cron() ) {
			return;
		}
		$now  = time();
		$runs = aw_cron_runs();
		$key  = aw_cron_trigger();
		foreach ( $runs as $k => $times ) {
			$runs[ $k ] = array_values( array_filter( (array) $times, fn( $t ) => $now - (int) $t < AW_CRON_KEEP ) );
		}
		$runs[ $key ][] = $now;
		update_option( 'aw_cron_runs', $runs, true ); // autoloaded: every request's alert check reads it
	},
	0
);

/**
 * Runs in a window and the longest gap between them (from the window's start to the first run, and from the last run
 * to now, included).
 *
 * @param int[] $times Unix times.
 * @param int   $since Window start.
 * @param int   $now   Now.
 * @return array{runs:int,gap:int}
 */
function aw_cron_stats( $times, $since, $now ) {
	$t = array_values( array_filter( array_map( 'intval', (array) $times ), fn( $x ) => $x >= $since ) );
	sort( $t );
	$gap  = 0;
	$prev = $since;
	foreach ( $t as $x ) {
		$gap  = max( $gap, $x - $prev );
		$prev = $x;
	}
	return array( 'runs' => count( $t ), 'gap' => max( $gap, $now - $prev ) );
}

/** "2 h 05 min" / "12 min". */
function aw_cron_span( $s ) {
	$m = (int) round( $s / 60 );
	return $m >= 60 ? sprintf( '%d h %02d min', intdiv( $m, 60 ), $m % 60 ) : $m . ' min';
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'aw_cron_health',
			'Scheduled tasks',
			function () {
				$now   = time();
				$runs  = aw_cron_runs();
				$names = array( 'hostinger' => 'Hostinger server cron', 'github' => 'GitHub Actions', 'uptimerobot' => 'UptimeRobot', 'cron-job.org' => 'cron-job.org', 'other' => 'Other' );
				echo '<table class="widefat striped"><thead><tr><th>Trigger</th><th>Last run</th><th>Runs, 24 h</th><th>Longest gap, 24 h</th></tr></thead><tbody>';
				foreach ( array_unique( array_merge( array( 'hostinger', 'github', 'uptimerobot' ), array_keys( $runs ) ) ) as $k ) {
					$t    = (array) ( $runs[ $k ] ?? array() );
					$last = $t ? (int) max( $t ) : 0;
					$st   = aw_cron_stats( $t, $now - DAY_IN_SECONDS, $now );
					echo '<tr><td>' . esc_html( $names[ $k ] ?? $k ) . '</td><td>' . ( $last ? esc_html( wp_date( 'j M Y, g:i a', $last ) ) : 'never' ) . '</td><td>' . (int) $st['runs'] . '</td><td>' . ( $st['runs'] ? esc_html( aw_cron_span( $st['gap'] ) ) : '—' ) . '</td></tr>';
				}
				echo '</tbody></table>';
				$last = aw_cron_last();
				echo '<p>' . ( $last && $now - $last < AW_CRON_STALE ? 'Running: last run ' . esc_html( aw_cron_span( $now - $last ) ) . ' ago.' : '<strong>No run for 30 minutes or more.</strong> The owner is emailed.' ) . '</p>';
			}
		);
	}
);
