<?php
/**
 * Bing Webmaster beyond searches and links (3 Nov 2026), with the same free key as bing.php:
 *
 * - Crawl, weekly on Monday 06:45 IST: GetCrawlStats (one row a day: pages crawled, crawl errors, pages in Bing's index,
 *   4xx and 5xx answers, blocked by robots.txt) into obs src bing, period day, metrics crawl-pages, crawl-errors,
 *   in-index, code-4xx, code-5xx, robots-blocked; GetCrawlIssues counted in the run log.
 * - Keyword stats, monthly on the 1st at 07:00 IST: GetKeywordStats (Bing's weekly searches across all sites, India,
 *   English) for the P1 and quick-win keywords, at most AW_SI_BING_KW a run, into obs src bing, period week, metric
 *   kw-volume (value = Impressions, txt = broad impressions), kw_id set. Only the last 12 weeks are kept.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_BING_CRAWL_CRON = 'aw_si_bing_crawl';
const AW_SI_BING_KW_CRON    = 'aw_si_bing_kw';
const AW_SI_BING_KW         = 60;
const AW_SI_BING_CRAWL_MAP  = array(
	'CrawledPages'       => 'crawl-pages',
	'CrawlErrors'        => 'crawl-errors',
	'InIndex'            => 'in-index',
	'Code4xx'            => 'code-4xx',
	'Code5xx'            => 'code-5xx',
	'BlockedByRobotsTxt' => 'robots-blocked',
);

add_filter(
	'aw_si_sources',
	function ( $s ) {
		if ( function_exists( 'aw_si_bing_on' ) && aw_si_bing_on() ) {
			$s['bing_crawl'] = array(
				'label' => __( 'Bing crawl (weekly)', 'aw-seo-extras' ),
				'every' => WEEK_IN_SECONDS,
			);
			$s['bing_kw']    = array(
				'label' => __( 'Bing keyword volumes (monthly)', 'aw-seo-extras' ),
				'every' => 31 * DAY_IN_SECONDS,
			);
		}
		return $s;
	}
);
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_BING_CRAWL_CRON, AW_SI_BING_KW_CRON ) ) );

add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) ) {
			return;
		}
		$tz = wp_timezone();
		if ( ! wp_next_scheduled( AW_SI_BING_CRAWL_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'next monday 06:45', $tz ) )->getTimestamp(), 'weekly', AW_SI_BING_CRAWL_CRON );
		}
		if ( ! wp_next_scheduled( AW_SI_BING_KW_CRON ) ) {
			wp_schedule_single_event( ( new DateTimeImmutable( 'first day of next month 07:00', $tz ) )->getTimestamp(), AW_SI_BING_KW_CRON ); // Booked again after each run: WordPress has no monthly recurrence.
		}
	}
);

/**
 * The weekly crawl run.
 *
 * @param bool $dry Read, do not write.
 */
function aw_si_bing_crawl_run( bool $dry = false ): array {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() || ! function_exists( 'aw_si_bing_on' ) || ! aw_si_bing_on() ) {
		return array( 'off' => true );
	}
	if ( ! $dry ) {
		aw_si_run_start( 'bing_crawl' );
	}
	$stats = aw_si_bing_call( 'GetCrawlStats' );
	if ( is_wp_error( $stats ) ) {
		if ( ! $dry ) {
			aw_si_run_fail( 'bing_crawl', $stats->get_error_message() );
		}
		return array( 'error' => $stats->get_error_message() );
	}
	$issues = aw_si_bing_call( 'GetCrawlIssues' );
	$rows   = 0;
	$up_to  = '';
	$keep   = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . ( aw_si_bing_days() ) . ' days' )->format( 'Y-m-d' );
	foreach ( (array) $stats as $r ) {
		$d = aw_si_bing_date( (string) ( $r['Date'] ?? '' ) );
		if ( '' === $d || $d < $keep ) {
			continue; // Days the rollup already folded into months are not stored again.
		}
		foreach ( AW_SI_BING_CRAWL_MAP as $field => $metric ) {
			if ( isset( $r[ $field ] ) && ! $dry ) {
				aw_si_obs_put( 0, '', 'bing', $metric, 'day', $d, array( 'value' => (float) $r[ $field ] ) );
			}
		}
		++$rows;
		$up_to = max( $up_to, $d );
	}
	$info = array(
		'rows'   => $rows,
		'issues' => is_wp_error( $issues ) ? null : count( (array) $issues ),
	);
	if ( '' !== $up_to ) {
		$info['up_to'] = $up_to;
	}
	if ( is_wp_error( $issues ) ) {
		$info['warnings'] = array( $issues->get_error_message() );
	}
	if ( ! $dry ) {
		aw_si_run_ok( 'bing_crawl', $info );
	}
	return $info;
}
add_action( AW_SI_BING_CRAWL_CRON, fn() => aw_si_bing_crawl_run() );

/**
 * The monthly keyword-volume run for the P1 and quick-win keywords (English only).
 *
 * @param bool $dry Read, do not write.
 */
function aw_si_bing_kw_run( bool $dry = false ): array {
	if ( ! $dry ) {
		wp_clear_scheduled_hook( AW_SI_BING_KW_CRON ); // The init hook books next month's run.
	}
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() || ! function_exists( 'aw_si_bing_on' ) || ! aw_si_bing_on() ) {
		return array( 'off' => true );
	}
	global $wpdb;
	if ( ! $dry ) {
		aw_si_run_start( 'bing_kw' );
	}
	$k     = aw_si_table( 'keywords' );
	$o     = aw_si_table( 'obs' );
	$quick = aw_si_view_sql( 'quick' );
	$p1    = aw_si_view_sql( 'p1' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- table name and fixed conditions.
	$kws  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, label FROM {$k} WHERE ( {$p1} OR {$quick} ) AND COALESCE(lang, 'en') = 'en' AND label NOT LIKE %s ORDER BY impr28 DESC LIMIT %d", '%' . $wpdb->esc_like( '{' ) . '%', AW_SI_BING_KW ) );
	$rows = 0;
	$fail = array();
	$cut  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-84 days' )->format( 'Y-m-d' );
	foreach ( $kws as $kw ) {
		$r = aw_si_bing_call(
			'GetKeywordStats',
			array(
				'q'        => (string) $kw->label,
				'country'  => 'in',
				'language' => 'en-IN',
			)
		);
		if ( is_wp_error( $r ) ) {
			$fail[] = $kw->label . ': ' . $r->get_error_message();
			if ( count( $fail ) >= 3 ) {
				break; // Bing refuses: stop for this month.
			}
			continue;
		}
		foreach ( (array) $r as $w ) {
			$d = aw_si_bing_date( (string) ( $w['Date'] ?? '' ) );
			if ( '' === $d || $d < $cut ) {
				continue;
			}
			if ( ! $dry ) {
				aw_si_obs_put(
					(int) $kw->id,
					'',
					'bing',
					'kw-volume',
					'week',
					$d,
					array(
						'value' => (float) ( $w['Impressions'] ?? 0 ),
						'txt'   => (string) (int) ( $w['BroadImpressions'] ?? 0 ),
					)
				);
			}
			++$rows;
		}
	}
	if ( $dry ) {
		return array(
			'keywords' => count( $kws ),
			'rows'     => $rows,
			'warnings' => $fail,
		);
	}
	// Only the last 12 weeks of volumes are kept.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = 'bing' AND metric = 'kw-volume' AND period = 'week' AND d < %s", $cut ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	if ( ! $rows && $fail ) {
		aw_si_run_fail( 'bing_kw', (string) $fail[0] );
	} else {
		aw_si_run_ok(
			'bing_kw',
			array(
				'rows'     => $rows,
				'keywords' => count( $kws ),
				'warnings' => $fail,
			)
		);
	}
	return array( 'rows' => $rows );
}
add_action( AW_SI_BING_KW_CRON, fn() => aw_si_bing_kw_run() );

/** Overview box: Bing's crawl over the last 28 days and the last 12 weeks. */
function aw_si_box_bing_crawl(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$o    = aw_si_table( 'obs' );
	$from = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-84 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT YEARWEEK(d, 3) wk, MIN(d) since, MAX(d) last, SUM(IF(metric = 'crawl-pages', value, 0)) pages, SUM(IF(metric = 'crawl-errors', value, 0)) errors, SUM(IF(metric = 'code-4xx', value, 0)) c4, SUM(IF(metric = 'code-5xx', value, 0)) c5, SUM(IF(metric = 'robots-blocked', value, 0)) robots, MAX(IF(metric = 'in-index', value, 0)) idx FROM {$o} WHERE src = 'bing' AND period = 'day' AND metric IN ('crawl-pages', 'crawl-errors', 'code-4xx', 'code-5xx', 'robots-blocked', 'in-index') AND d >= %s GROUP BY wk ORDER BY wk DESC LIMIT 12", $from ) );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No Bing crawl figures yet (weekly, Monday 06:45 IST).', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$n = fn( $v ) => esc_html( number_format_i18n( (int) $v ) );
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Week from', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Pages crawled', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Crawl errors', 'aw-seo-extras' ) . '</th><th scope="col">4xx</th><th scope="col">5xx</th><th scope="col">' . esc_html__( 'Blocked by robots.txt', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'In Bing\'s index', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( wp_date( 'j M', strtotime( (string) $r->since ) ) ) . '</td><td>' . $n( $r->pages ) . '</td><td>' . $n( $r->errors ) . '</td><td>' . $n( $r->c4 ) . '</td><td>' . $n( $r->c5 ) . '</td><td>' . $n( $r->robots ) . '</td><td>' . $n( $r->idx ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $n.
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Bing Webmaster crawl statistics by week (Monday to Sunday); "In Bing\'s index" is the highest count of the week.', 'aw-seo-extras' ) . '</p>';
}
