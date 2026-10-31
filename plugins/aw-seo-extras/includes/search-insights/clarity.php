<?php
/**
 * Microsoft Clarity Data Export API (plan §5.2), daily at 06:30 IST, only when the owner has put a project API token in
 * Animal Writes → Keys or wp-config.php as AW_CLARITY_TOKEN (never in the repo). Two calls a day (by URL, by device) for the
 * last 24 hours; the API allows 10 a day per project, so the job stops itself at 8. Only per-URL and per-device totals
 * are stored: never recordings, session IDs or anything per session.
 *
 * Rows in obs: src clarity, period day (the day before the run). By URL: page = path; metrics sessions, scroll
 * (average scroll depth %), and the share of sessions (%) with rage clicks (rage), dead clicks (dead), quick-backs
 * (quickback) and JavaScript errors (jserror); flag = 1 for a page over the thresholds below. By device: page '',
 * txt = device, page_key = md5 of "device:" and the device. Clarity notes (the owner's dated summaries): src
 * clarity-note, metric note, txt = the note.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_CLARITY_CRON = 'aw_si_clarity';
const AW_SI_CLARITY_API  = 'https://www.clarity.ms/export-data/api/v1/project-live-insights';
const AW_SI_CLARITY_MAX  = 8; // Calls a day this job allows itself (Clarity's limit is 10).
const AW_SI_CLARITY_RETRY = 'aw_si_clarity_retry'; // One later try on a day whose run failed (owner, 31 Oct 2026).

/** Whether the Clarity token is set in wp-config.php. */
function aw_si_clarity_on(): bool {
	return '' !== aw_si_key( 'AW_CLARITY_TOKEN' );
}

/**
 * Page-quality thresholds (provisional until a month of data; plan §5.2). A page is flagged when it had at least
 * `sessions` sessions and any share is at or over its limit. Filter aw_si_clarity_thresholds changes them.
 */
function aw_si_clarity_thresholds(): array {
	return (array) apply_filters(
		'aw_si_clarity_thresholds',
		array(
			'sessions'  => 20,
			'rage'      => 5.0,
			'dead'      => 10.0,
			'quickback' => 10.0,
		)
	);
}

/**
 * One API call.
 *
 * @param string $dimension URL or Device.
 * @return array|WP_Error The decoded response: a list of {metricName, information[]}.
 */
function aw_si_clarity_call( string $dimension ) {
	$day   = wp_date( 'Y-m-d' );
	$calls = get_option( 'aw_si_clarity_calls', array() );
	$used  = (int) ( is_array( $calls ) && ( $calls['d'] ?? '' ) === $day ? $calls['n'] : 0 );
	if ( $used >= AW_SI_CLARITY_MAX ) {
		return new WP_Error( 'aw_si_clarity', __( 'Clarity: the calls allowed today are used up; the next run tries again.', 'aw-seo-extras' ) );
	}
	update_option(
		'aw_si_clarity_calls',
		array(
			'd' => $day,
			'n' => $used + 1,
		),
		false
	);
	$http = wp_remote_get(
		add_query_arg(
			array(
				'numOfDays'  => 1,
				'dimension1' => $dimension,
			),
			AW_SI_CLARITY_API
		),
		array(
			'timeout' => 60, // Clarity's export can take over 30 s; the 30 s limit failed every run 27–31 Oct 2026.
			'headers' => array(
				'Authorization' => 'Bearer ' . aw_si_key( 'AW_CLARITY_TOKEN' ),
				'Content-Type'  => 'application/json',
			),
		)
	);
	if ( is_wp_error( $http ) ) {
		return $http;
	}
	$code = (int) wp_remote_retrieve_response_code( $http );
	if ( 200 !== $code ) {
		$why = array(
			401 => __( 'the token is missing, wrong or expired', 'aw-seo-extras' ),
			403 => __( 'the token is not allowed to export data', 'aw-seo-extras' ),
			429 => __( 'the daily limit of 10 calls is used up', 'aw-seo-extras' ),
		);
		/* translators: 1: HTTP status, 2: reason. */
		return new WP_Error( 'aw_si_clarity', sprintf( __( 'Clarity answered %1$d: %2$s.', 'aw-seo-extras' ), $code, $why[ $code ] ?? __( 'unexpected answer', 'aw-seo-extras' ) ) );
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $http ), true );
	return is_array( $data ) ? $data : new WP_Error( 'aw_si_clarity', __( 'Clarity returned no data.', 'aw-seo-extras' ) );
}

/**
 * Totals per key (path or device) from one response: sessions, scroll and the per-session shares, weighted by sessions
 * when several URLs share a path (query strings).
 *
 * @param array  $resp      Decoded response.
 * @param string $dimension URL or Device.
 * @return array<string, array<string, float>> key => metric => value.
 */
function aw_si_clarity_totals( array $resp, string $dimension ): array {
	$field = array(
		'ScrollDepth'      => array( 'scroll', 'averageScrollDepth' ),
		'RageClickCount'   => array( 'rage', 'sessionsWithMetricPercentage' ),
		'DeadClickCount'   => array( 'dead', 'sessionsWithMetricPercentage' ),
		'QuickbackClick'   => array( 'quickback', 'sessionsWithMetricPercentage' ),
		'ScriptErrorCount' => array( 'jserror', 'sessionsWithMetricPercentage' ),
	);
	$keyof = function ( array $info ) use ( $dimension ): string {
		$v = (string) ( $info[ $dimension ] ?? '' );
		return 'URL' === $dimension ? aw_si_path( $v ) : mb_substr( $v, 0, 60 );
	};
	$sess  = array();
	foreach ( $resp as $block ) {
		if ( 'Traffic' !== ( $block['metricName'] ?? '' ) ) {
			continue;
		}
		foreach ( (array) ( $block['information'] ?? array() ) as $info ) {
			$k = $keyof( (array) $info );
			if ( '' !== $k ) {
				// totalSessionCount is the dashboard's session count (bots excluded); totalBotSessionCount is reported beside it.
				$sess[ $k ] = ( $sess[ $k ] ?? 0 ) + (float) ( $info['totalSessionCount'] ?? 0 );
			}
		}
	}
	$out = array();
	foreach ( $sess as $k => $n ) {
		$out[ $k ]['sessions'] = $n;
	}
	$sum = array(); // key => metric => [weighted sum, weight].
	foreach ( $resp as $block ) {
		$f = $field[ $block['metricName'] ?? '' ] ?? null;
		if ( ! $f ) {
			continue;
		}
		foreach ( (array) ( $block['information'] ?? array() ) as $info ) {
			$k = $keyof( (array) $info );
			if ( '' === $k || ! isset( $info[ $f[1] ] ) || ! is_numeric( $info[ $f[1] ] ) ) {
				continue;
			}
			$w                     = max( 1.0, (float) ( $info['sessionsCount'] ?? ( $sess[ $k ] ?? 1 ) ) );
			$sum[ $k ][ $f[0] ][0] = ( $sum[ $k ][ $f[0] ][0] ?? 0 ) + (float) $info[ $f[1] ] * $w;
			$sum[ $k ][ $f[0] ][1] = ( $sum[ $k ][ $f[0] ][1] ?? 0 ) + $w;
		}
	}
	foreach ( $sum as $k => $metrics ) {
		foreach ( $metrics as $m => $s ) {
			$out[ $k ][ $m ] = round( $s[0] / $s[1], 2 );
		}
	}
	return $out;
}

/**
 * Whether a page's figures cross the page-quality thresholds.
 *
 * @param array $m Metric => value for one page.
 */
function aw_si_clarity_flagged( array $m ): bool {
	$t = aw_si_clarity_thresholds();
	if ( (float) ( $m['sessions'] ?? 0 ) < (float) $t['sessions'] ) {
		return false;
	}
	foreach ( array( 'rage', 'dead', 'quickback' ) as $k ) {
		if ( isset( $m[ $k ] ) && (float) $m[ $k ] >= (float) $t[ $k ] ) {
			return true;
		}
	}
	return false;
}

/**
 * The daily Clarity run.
 *
 * @param array $args dry (fetch only).
 * @return array Report: pages, devices, flagged, rows, error.
 */
function aw_si_clarity_run( array $args = array() ): array {
	$dry = ! empty( $args['dry'] );
	if ( ! aw_si_clarity_on() ) {
		return array( 'error' => __( 'No Clarity token: add it under Animal Writes → Keys.', 'aw-seo-extras' ) );
	}
	if ( ! $dry ) {
		aw_si_run_start( 'clarity' );
	}
	$day  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );
	$rows = array();
	$rep  = array(
		'pages'   => 0,
		'devices' => 0,
		'flagged' => 0,
		'rows'    => 0,
		'error'   => '',
	);
	foreach ( array( 'URL', 'Device' ) as $dim ) {
		$resp = aw_si_clarity_call( $dim );
		if ( is_wp_error( $resp ) ) {
			$rep['error'] = $resp->get_error_message();
			break;
		}
		foreach ( aw_si_clarity_totals( $resp, $dim ) as $key => $metrics ) {
			$page = 'URL' === $dim ? $key : '';
			$pk   = 'URL' === $dim ? md5( $key ) : md5( 'device:' . $key );
			$txt  = 'URL' === $dim ? '' : $key;
			foreach ( $metrics as $m => $v ) {
				$rows[] = array( 0, $page, $pk, $m, $v, $txt );
			}
			if ( 'URL' === $dim ) {
				++$rep['pages'];
				if ( aw_si_clarity_flagged( $metrics ) ) {
					$rows[] = array( 0, $page, $pk, 'flag', 1.0, '' );
					++$rep['flagged'];
				}
			} else {
				++$rep['devices'];
			}
		}
	}
	if ( '' === $rep['error'] ) {
		$n = $dry ? count( $rows ) : aw_si_obs_replace_day( 'clarity', $day, $rows );
		if ( is_wp_error( $n ) ) {
			$rep['error'] = $n->get_error_message();
		} else {
			$rep['rows'] = $n;
		}
	}
	if ( $dry ) {
		$rep['sample'] = array_slice( $rows, 0, 8 );
		return $rep;
	}
	if ( '' !== $rep['error'] ) {
		aw_si_run_fail( 'clarity', $rep['error'] );
		// One more try 3 hours later, once a day (calls stay well inside Clarity's 10 a day).
		$today = wp_date( 'Y-m-d' );
		if ( get_option( 'aw_si_clarity_retried' ) !== $today && ! wp_next_scheduled( AW_SI_CLARITY_RETRY ) ) {
			update_option( 'aw_si_clarity_retried', $today, false );
			wp_schedule_single_event( time() + 3 * HOUR_IN_SECONDS, AW_SI_CLARITY_RETRY );
		}
	} else {
		aw_si_run_ok( 'clarity', $rep + array( 'up_to' => $day ) );
	}
	return $rep;
}
add_action( AW_SI_CLARITY_CRON, fn() => get_option( 'aw_si_off' ) || ! aw_si_clarity_on() ? null : aw_si_clarity_run() );
add_action( AW_SI_CLARITY_RETRY, fn() => get_option( 'aw_si_off' ) || ! aw_si_clarity_on() ? null : aw_si_clarity_run() );

/**
 * Save a Clarity note for a page (the owner's short dated summary of the recordings; plan §5.2).
 *
 * @param string $path Site path.
 * @param string $note Note text (up to 255 characters).
 */
function aw_si_clarity_note_save( string $path, string $note ): bool {
	return aw_si_obs_put( 0, $path, 'clarity-note', 'note', 'day', wp_date( 'Y-m-d' ), array( 'txt' => $note ) );
}

add_action(
	'admin_post_aw_si_note',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_note' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$path = isset( $_POST['path'] ) ? aw_si_path( home_url( sanitize_text_field( wp_unslash( $_POST['path'] ) ) ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$ok   = '' !== $path && '' !== trim( $note ) && aw_si_clarity_note_save( $path, mb_substr( trim( $note ), 0, 255 ) );
		wp_safe_redirect( add_query_arg( $ok ? 'noted' : 'not_noted', 1, aw_si_url( 'pages' ) ) );
		exit;
	}
);
