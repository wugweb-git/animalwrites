<?php
/**
 * Microsoft Clarity Data Export API (plan §5.2), daily at 06:30 IST, only when the owner has put a project API token in
 * Animal Writes → Keys or wp-config.php as AW_CLARITY_TOKEN (never in the repo). Two calls a day for the last 24 hours:
 * by URL and device, then by device. The API allows 10 a day per project, so the job stops itself at 8. Only totals
 * per page and per device are stored: never recordings, session IDs or anything per session.
 *
 * Rows in obs, period day, d = the day before the run, kw_id 0:
 * - src clarity, per page: page = path (query strings and fragments dropped; URLs of one path combined), page_key =
 *   md5 of the path. Metrics sessions, users, bots, pps (pages per session), active and total (engagement seconds),
 *   scroll (average depth %), and the share of sessions (%) with dead clicks (dead), rage clicks (rage), quick-backs
 *   (quickback), excessive scrolling (xscroll), script errors (jserror) and error clicks (errclick); flag = 1 for a
 *   page over the thresholds below.
 * - src clarity-dev, per page and device: page = path, txt = device, page_key = md5 of "path|device". Metrics
 *   sessions, dead, rage, quickback, scroll.
 * - src clarity, per device for the whole site: page '', txt = device, page_key = md5 of "device:" and the device.
 *   The same metrics as a page, without flag.
 * Shares and averages are weighted by sessions wherever rows are combined. Clarity notes (the owner's dated
 * summaries): src clarity-note, metric note, txt = the note.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_CLARITY_CRON  = 'aw_si_clarity';
const AW_SI_CLARITY_API   = 'https://www.clarity.ms/export-data/api/v1/project-live-insights';
const AW_SI_CLARITY_MAX   = 8; // Calls a day this job allows itself (Clarity's limit is 10).
const AW_SI_CLARITY_RETRY = 'aw_si_clarity_retry'; // One later try on a day whose run failed (owner, 31 Oct 2026).
const AW_SI_CLARITY_KEEP  = 35; // Daily clarity and clarity-dev rows are kept at least this many days (rollup.php).

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
 * Where each stored metric comes from: Clarity block => metric => field in the block's rows.
 */
function aw_si_clarity_fields(): array {
	$share = 'sessionsWithMetricPercentage';
	return array(
		'traffic'          => array(
			'sessions' => 'totalSessionCount', // The dashboard's session count (bots excluded).
			'bots'     => 'totalBotSessionCount',
			'users'    => 'distinctUserCount',
			'pps'      => 'pagesPerSessionPercentage',
		),
		'engagementtime'   => array(
			'total'  => 'totalTime',
			'active' => 'activeTime',
		),
		'scrolldepth'      => array( 'scroll' => 'averageScrollDepth' ),
		'deadclickcount'   => array( 'dead' => $share ),
		'rageclickcount'   => array( 'rage' => $share ),
		'quickbackclick'   => array( 'quickback' => $share ),
		'excessivescroll'  => array( 'xscroll' => $share ),
		'scripterrorcount' => array( 'jserror' => $share ),
		'errorclickcount'  => array( 'errclick' => $share ),
	);
}

/** Metrics that are added up when rows are combined; all others are averages weighted by sessions. */
const AW_SI_CLARITY_COUNTS = array( 'sessions', 'users', 'bots' );

/**
 * A dimension's value in one response row, whatever its case (the API sends "Url" for dimension URL).
 *
 * @param array  $info One row of a block's information list.
 * @param string $name Dimension name: URL or Device.
 */
function aw_si_clarity_dim( array $info, string $name ): string {
	foreach ( $info as $k => $v ) {
		if ( 0 === strcasecmp( (string) $k, $name ) ) {
			return trim( (string) $v );
		}
	}
	return '';
}

/**
 * One API call.
 *
 * @param string[] $dimensions Up to three of URL, Device, …, in order.
 * @return array|WP_Error The decoded response: a list of {metricName, information[]}.
 */
function aw_si_clarity_call( array $dimensions ) {
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
	$query = array( 'numOfDays' => 1 );
	foreach ( array_values( array_slice( $dimensions, 0, 3 ) ) as $i => $dim ) {
		$query[ 'dimension' . ( $i + 1 ) ] = $dim;
	}
	$http = wp_remote_get(
		add_query_arg( $query, AW_SI_CLARITY_API ),
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
 * Totals per group from one response. Each response row is one combination of the call's dimensions; $keyof names
 * the group it adds to ('' skips it). Counts are added up; averages and shares are weighted by the row's sessions.
 *
 * @param array    $resp   Decoded response.
 * @param string[] $dims   The call's dimensions, in order (they identify a row across blocks).
 * @param callable $keyof  Row => group key.
 * @return array<string, array<string, float>> group => metric => value (every group has sessions).
 */
function aw_si_clarity_totals( array $resp, array $dims, callable $keyof ): array {
	$fields = aw_si_clarity_fields();
	$rowid  = function ( array $info ) use ( $dims ): string {
		return implode( "\n", array_map( fn( $d ) => aw_si_clarity_dim( $info, $d ), $dims ) );
	};
	// Sessions of each response row, the weight of its other figures.
	$weight = array();
	foreach ( $resp as $block ) {
		if ( 'traffic' !== strtolower( (string) ( $block['metricName'] ?? '' ) ) ) {
			continue;
		}
		foreach ( (array) ( $block['information'] ?? array() ) as $info ) {
			$id            = $rowid( (array) $info );
			$weight[ $id ] = ( $weight[ $id ] ?? 0 ) + (float) ( $info['totalSessionCount'] ?? 0 );
		}
	}
	$sum = array(); // group => metric => [weighted sum, weight, plain sum, rows].
	foreach ( $resp as $block ) {
		$map = $fields[ strtolower( (string) ( $block['metricName'] ?? '' ) ) ] ?? null;
		if ( ! $map ) {
			continue;
		}
		foreach ( (array) ( $block['information'] ?? array() ) as $info ) {
			$info = (array) $info;
			$k    = (string) $keyof( $info );
			if ( '' === $k ) {
				continue;
			}
			$w = (float) ( $weight[ $rowid( $info ) ] ?? ( $info['sessionsCount'] ?? 0 ) );
			foreach ( $map as $m => $field ) {
				if ( ! isset( $info[ $field ] ) || ! is_numeric( $info[ $field ] ) ) {
					continue;
				}
				$v               = (float) $info[ $field ];
				$s               = $sum[ $k ][ $m ] ?? array( 0.0, 0.0, 0.0, 0 );
				$sum[ $k ][ $m ] = array( $s[0] + $v * $w, $s[1] + $w, $s[2] + $v, $s[3] + 1 );
			}
		}
	}
	$out = array();
	foreach ( $sum as $k => $metrics ) {
		$out[ $k ]['sessions'] = 0.0;
		foreach ( $metrics as $m => $s ) {
			if ( in_array( $m, AW_SI_CLARITY_COUNTS, true ) ) {
				$out[ $k ][ $m ] = $s[2];
			} else {
				// Rows without sessions (bots only) get a plain mean.
				$out[ $k ][ $m ] = round( $s[1] > 0 ? $s[0] / $s[1] : $s[2] / max( 1, $s[3] ), 2 );
			}
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
	$day    = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' )->format( 'Y-m-d' );
	$rep    = array(
		'pages'   => 0,
		'devices' => 0,
		'flagged' => 0,
		'rows'    => 0,
		'error'   => '',
	);
	$rows   = array(
		'clarity'     => array(),
		'clarity-dev' => array(),
	);
	$device = fn( array $info ): string => mb_substr( aw_si_clarity_dim( $info, 'Device' ), 0, 60 );
	$path   = function ( array $info ): string {
		$url = aw_si_clarity_dim( $info, 'URL' );
		return '' === $url ? '' : aw_si_path( $url );
	};

	// Call 1: by URL and device, for the page rows and the page-and-device rows.
	$resp = aw_si_clarity_call( array( 'URL', 'Device' ) );
	if ( is_wp_error( $resp ) ) {
		$rep['error'] = $resp->get_error_message();
	} else {
		$dims = array( 'URL', 'Device' );
		foreach ( aw_si_clarity_totals( $resp, $dims, $path ) as $page => $metrics ) {
			$pk = md5( $page );
			foreach ( $metrics as $m => $v ) {
				$rows['clarity'][] = array( 0, $page, $pk, $m, $v, '' );
			}
			++$rep['pages'];
			if ( aw_si_clarity_flagged( $metrics ) ) {
				$rows['clarity'][] = array( 0, $page, $pk, 'flag', 1.0, '' );
				++$rep['flagged'];
			}
		}
		$pair = function ( array $info ) use ( $path, $device ): string {
			$p = $path( $info );
			$d = $device( $info );
			return '' === $p || '' === $d ? '' : $p . "\n" . $d;
		};
		$keep = array( 'sessions', 'dead', 'rage', 'quickback', 'scroll' );
		foreach ( aw_si_clarity_totals( $resp, $dims, $pair ) as $key => $metrics ) {
			list( $page, $dev ) = explode( "\n", $key, 2 );
			$pk                 = md5( $page . '|' . $dev );
			foreach ( array_intersect_key( $metrics, array_flip( $keep ) ) as $m => $v ) {
				$rows['clarity-dev'][] = array( 0, $page, $pk, $m, $v, $dev );
			}
		}
	}

	// Call 2: by device, for the site's totals per device.
	if ( '' === $rep['error'] ) {
		$resp = aw_si_clarity_call( array( 'Device' ) );
		if ( is_wp_error( $resp ) ) {
			$rep['error'] = $resp->get_error_message();
		} else {
			foreach ( aw_si_clarity_totals( $resp, array( 'Device' ), $device ) as $dev => $metrics ) {
				$pk = md5( 'device:' . $dev );
				foreach ( $metrics as $m => $v ) {
					$rows['clarity'][] = array( 0, '', $pk, $m, $v, $dev );
				}
				++$rep['devices'];
			}
		}
	}

	// Both calls answered: replace the day's rows of each source.
	if ( '' === $rep['error'] ) {
		foreach ( $rows as $src => $list ) {
			$n = $dry ? count( $list ) : aw_si_obs_replace_day( $src, $day, $list );
			if ( is_wp_error( $n ) ) {
				$rep['error'] = $n->get_error_message();
				break;
			}
			$rep['rows'] += $n;
		}
	}
	if ( $dry ) {
		$rep['sample'] = array_merge( array_slice( $rows['clarity'], 0, 8 ), array_slice( $rows['clarity-dev'], 0, 5 ) );
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

// Monthly rows keep the last value of averages and shares (a sum of them would mean nothing); counts are summed.
add_filter(
	'aw_si_last_value_metrics',
	function ( $last ) {
		foreach ( array( 'pps', 'active', 'total', 'xscroll', 'errclick' ) as $m ) {
			$last[] = 'clarity:' . $m;
		}
		foreach ( array( 'scroll', 'rage', 'dead', 'quickback' ) as $m ) {
			$last[] = 'clarity-dev:' . $m;
		}
		return $last;
	}
);

/** First day (Y-m-d) of a window of the last $days days, which ends yesterday. */
function aw_si_clarity_since( int $days ): string {
	return ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . max( 1, $days ) . ' days' )->format( 'Y-m-d' );
}

/** Days a Clarity page must be flagged on to count (filter aw_si_quality_min_days, as in aw_si_quality_pages()). */
function aw_si_clarity_min_days(): int {
	return max( 1, (int) apply_filters( 'aw_si_quality_min_days', 3 ) );
}

/**
 * Site figures from Clarity over the last $days days (the per-device rows of the whole site).
 *
 * @param int $days Window in days, ending yesterday.
 * @return array days, last, sessions, users, bots (ints), pps, active, scroll, dead, rage, quickback, xscroll,
 *               jserror, errclick (floats weighted by sessions), flagged (pages flagged on enough days), devices
 *               (device => sessions).
 */
function aw_si_clarity_summary( int $days = 28 ): array {
	global $wpdb;
	$o     = aw_si_table( 'obs' );
	$since = aw_si_clarity_since( $days );
	$avg   = array( 'pps', 'active', 'scroll', 'dead', 'rage', 'quickback', 'xscroll', 'jserror', 'errclick' );
	$out   = array(
		'days'     => 0,
		'last'     => '',
		'sessions' => 0,
		'users'    => 0,
		'bots'     => 0,
	);
	foreach ( $avg as $m ) {
		$out[ $m ] = 0.0;
	}
	$out['flagged'] = 0;
	$out['devices'] = array();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT d, page_key, txt, metric, value FROM {$o} WHERE src = 'clarity' AND period = 'day' AND kw_id = 0 AND page = '' AND d >= %s", $since ) );
	$out['flagged'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT page FROM {$o} WHERE src = 'clarity' AND metric = 'flag' AND period = 'day' AND value > 0 AND d >= %s AND page <> '' GROUP BY page HAVING COUNT(DISTINCT d) >= %d) f", $since, aw_si_clarity_min_days() ) );
	// phpcs:enable
	// One device on one day => metric => value.
	$cells = array();
	foreach ( $rows as $r ) {
		$id                         = $r->d . '|' . $r->page_key;
		$cells[ $id ]['_d']         = (string) $r->d;
		$cells[ $id ]['_dev']       = (string) $r->txt;
		$cells[ $id ][ $r->metric ] = (float) $r->value;
	}
	$sum  = array(); // metric => [weighted sum, weight].
	$seen = array();
	foreach ( $cells as $c ) {
		if ( ! isset( $c['sessions'] ) ) {
			continue;
		}
		$seen[ $c['_d'] ] = true;
		$n                = $c['sessions'];
		foreach ( array( 'sessions', 'users', 'bots' ) as $m ) {
			$out[ $m ] += (int) round( $c[ $m ] ?? 0 );
		}
		$dev                    = $c['_dev'];
		$out['devices'][ $dev ] = ( $out['devices'][ $dev ] ?? 0 ) + (int) round( $n );
		foreach ( $avg as $m ) {
			if ( isset( $c[ $m ] ) ) {
				$sum[ $m ][0] = ( $sum[ $m ][0] ?? 0 ) + $c[ $m ] * $n;
				$sum[ $m ][1] = ( $sum[ $m ][1] ?? 0 ) + $n;
			}
		}
	}
	foreach ( $sum as $m => $s ) {
		$out[ $m ] = $s[1] > 0 ? round( $s[0] / $s[1], 2 ) : 0.0;
	}
	if ( $seen ) {
		$out['days'] = count( $seen );
		$out['last'] = max( array_keys( $seen ) );
	}
	arsort( $out['devices'] );
	return $out;
}

/**
 * Clarity figures per page over the last $days days.
 *
 * @param int $days Window in days, ending yesterday.
 * @return array[] Rows sorted by sessions (most first): page, sessions (int), pps, active, scroll, dead, rage,
 *                 quickback, xscroll, jserror (floats weighted by sessions), flag_days (int), flagged (bool),
 *                 devices (device => sessions).
 */
function aw_si_clarity_pages( int $days = 28 ): array {
	global $wpdb;
	$o     = aw_si_table( 'obs' );
	$since = aw_si_clarity_since( $days );
	$avg   = array( 'pps', 'active', 'scroll', 'dead', 'rage', 'quickback', 'xscroll', 'jserror' );
	// Each metric row is weighted by its own day's sessions (same day and page_key; the unique key finds it).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.page, m.metric, SUM(m.value) v, SUM(m.value * COALESCE(s.value, 0)) wv, SUM(COALESCE(s.value, 0)) w, COUNT(*) n, SUM(m.value > 0) pos
			FROM {$o} m LEFT JOIN {$o} s ON s.src = m.src AND s.metric = 'sessions' AND s.period = m.period AND s.d = m.d AND s.kw_id = m.kw_id AND s.page_key = m.page_key
			WHERE m.src = 'clarity' AND m.period = 'day' AND m.kw_id = 0 AND m.page <> '' AND m.d >= %s
			GROUP BY m.page, m.metric",
			$since
		)
	);
	$devs = (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, txt, SUM(value) v FROM {$o} WHERE src = 'clarity-dev' AND metric = 'sessions' AND period = 'day' AND kw_id = 0 AND d >= %s GROUP BY page, txt", $since ) );
	// phpcs:enable
	$min = aw_si_clarity_min_days();
	$out = array();
	foreach ( $rows as $r ) {
		$p = (string) $r->page;
		if ( ! isset( $out[ $p ] ) ) {
			$out[ $p ] = array(
				'page'     => $p,
				'sessions' => 0,
			) + array_fill_keys( $avg, 0.0 ) + array(
				'flag_days' => 0,
				'flagged'   => false,
				'devices'   => array(),
			);
		}
		if ( 'sessions' === $r->metric ) {
			$out[ $p ]['sessions'] = (int) round( (float) $r->v );
		} elseif ( 'flag' === $r->metric ) {
			$out[ $p ]['flag_days'] = (int) $r->pos; // One flag row per page a day.
			$out[ $p ]['flagged']   = $out[ $p ]['flag_days'] >= $min;
		} elseif ( in_array( $r->metric, $avg, true ) ) {
			$w                       = (float) $r->w;
			$out[ $p ][ $r->metric ] = round( $w > 0 ? (float) $r->wv / $w : (float) $r->v / max( 1, (int) $r->n ), 2 );
		}
	}
	foreach ( $devs as $r ) {
		if ( isset( $out[ (string) $r->page ] ) ) {
			$out[ (string) $r->page ]['devices'][ (string) $r->txt ] = (int) round( (float) $r->v );
		}
	}
	foreach ( $out as $p => $row ) {
		arsort( $out[ $p ]['devices'] );
	}
	$out = array_values( $out );
	usort( $out, fn( $a, $b ) => $a['sessions'] === $b['sessions'] ? strcmp( $a['page'], $b['page'] ) : $b['sessions'] <=> $a['sessions'] );
	return $out;
}

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
