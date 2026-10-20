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
				do_action( 'aw_cron_health_after' );
				echo '<p>' . ( $last && $now - $last < AW_CRON_STALE ? 'Running: last run ' . esc_html( aw_cron_span( $now - $last ) ) . ' ago.' : '<strong>No run for 30 minutes or more.</strong> The owner is emailed.' ) . '</p>';
			}
		);
	}
);

/*
 * Sourcing on time (owner, 20 Oct 2026): GitHub's own schedule runs late (6–8 of 8 runs a day, gaps up to 6.5 hours),
 * so Hostinger's server cron starts the workers through GitHub's API every 3 hours:
 *   cd /home/u544169507/domains/animalwrites.in/public_html && /usr/local/bin/wp eval 'aw_dispatch_workflow("sourcing.yml", array("dry_run" => "false"));'
 * A run started this way begins at once. GitHub's schedule stays as the backup; the workflow's concurrency group keeps
 * runs from overlapping. The token is a fine-grained GitHub token for wugweb-git/animal-writes-workers only, with
 * Actions read and write, kept in wp-config.php as AW_GITHUB_DISPATCH_TOKEN (never in a repository).
 */

/**
 * Start a workflow in the workers repository now.
 *
 * @param string $file   Workflow file, e.g. sourcing.yml.
 * @param array  $inputs workflow_dispatch inputs (strings).
 * @return true|WP_Error
 */
function aw_dispatch_workflow( $file, $inputs = array() ) {
	$token = defined( 'AW_GITHUB_DISPATCH_TOKEN' ) ? (string) AW_GITHUB_DISPATCH_TOKEN : '';
	$file  = sanitize_file_name( $file );
	if ( '' === $token ) {
		$res = new WP_Error( 'aw_dispatch_token', 'AW_GITHUB_DISPATCH_TOKEN is not set in wp-config.php' );
	} else {
		$resp = wp_remote_post(
			'https://api.github.com/repos/wugweb-git/animal-writes-workers/actions/workflows/' . rawurlencode( $file ) . '/dispatches',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'        => 'Bearer ' . $token,
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'animalwrites.in',
					'Content-Type'         => 'application/json',
				),
				'body'    => wp_json_encode( array( 'ref' => 'main', 'inputs' => (object) array_map( 'strval', $inputs ) ) ),
			)
		);
		$code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
		$res  = 204 === $code ? true : new WP_Error( 'aw_dispatch_failed', is_wp_error( $resp ) ? $resp->get_error_message() : 'GitHub answered HTTP ' . $code . ': ' . mb_substr( (string) wp_remote_retrieve_body( $resp ), 0, 200 ) );
	}
	$log          = (array) get_option( 'aw_dispatch_log', array() );
	$log[ $file ] = array( 'at' => time(), 'ok' => true === $res, 'msg' => true === $res ? 'started' : $res->get_error_message() );
	update_option( 'aw_dispatch_log', $log, false );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		echo esc_html( $file . ': ' . $log[ $file ]['msg'] ) . "\n";
	}
	return $res;
}

// The dashboard box also shows the last start of each workflow from the server.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_action(
			'aw_cron_health_after',
			function () {
				foreach ( (array) get_option( 'aw_dispatch_log', array() ) as $file => $r ) {
					echo '<p>' . esc_html( $file ) . ' started from the server: ' . esc_html( wp_date( 'j M Y, g:i a', (int) $r['at'] ) ) . ' — ' . ( $r['ok'] ? 'started' : '<strong>' . esc_html( $r['msg'] ) . '</strong>' ) . '</p>';
				}
			}
		);
	}
);
