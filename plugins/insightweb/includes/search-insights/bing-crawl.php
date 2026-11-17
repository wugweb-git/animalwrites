<?php
/**
 * Bing Webmaster beyond searches and links (3 Nov 2026), with the same free key as bing.php:
 *
 * - Crawl, weekly on Monday 06:45 IST: GetCrawlStats (one row a day: pages crawled, crawl errors, pages in Bing's index,
 *   4xx and 5xx answers, blocked by robots.txt) into obs src bing, period day, metrics crawl-pages, crawl-errors,
 *   in-index, code-4xx, code-5xx, robots-blocked; GetCrawlIssues counted in the run log.
 * - Keyword stats, monthly on the 1st at 07:00 IST: GetKeywordStats (Bing's weekly searches across all sites, India,
 *   English) for the P1 and quick-win keywords, at most INSIGHTWEB_BING_KW a run, into obs src bing, period week, metric
 *   kw-volume (value = Impressions, txt = broad impressions), kw_id set. Only the last 12 weeks are kept.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_BING_CRAWL_CRON = 'insightweb_bing_crawl';
const INSIGHTWEB_BING_KW_CRON    = 'insightweb_bing_kw';
const INSIGHTWEB_BING_KW         = 60;
const INSIGHTWEB_BING_CRAWL_MAP  = array(
	'CrawledPages'       => 'crawl-pages',
	'CrawlErrors'        => 'crawl-errors',
	'InIndex'            => 'in-index',
	'Code4xx'            => 'code-4xx',
	'Code5xx'            => 'code-5xx',
	'BlockedByRobotsTxt' => 'robots-blocked',
);

add_filter(
	'insightweb_sources',
	function ( $s ) {
		if ( function_exists( 'insightweb_bing_on' ) && insightweb_bing_on() ) {
			$s['bing_crawl'] = array(
				'label' => __( 'Bing crawl (weekly)', 'insightweb' ),
				'every' => WEEK_IN_SECONDS,
			);
			$s['bing_kw']    = array(
				'label' => __( 'Bing keyword volumes (monthly)', 'insightweb' ),
				'every' => 31 * DAY_IN_SECONDS,
			);
		}
		return $s;
	}
);
add_filter( 'insightweb_cron_hooks', fn( $h ) => array_merge( (array) $h, array( INSIGHTWEB_BING_CRAWL_CRON, INSIGHTWEB_BING_KW_CRON ) ) );

add_action(
	'init',
	function () {
		if ( get_option( 'insightweb_off' ) ) {
			return;
		}
		$tz = wp_timezone();
		if ( ! wp_next_scheduled( INSIGHTWEB_BING_CRAWL_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'next monday 06:45', $tz ) )->getTimestamp(), 'weekly', INSIGHTWEB_BING_CRAWL_CRON );
		}
		if ( ! wp_next_scheduled( INSIGHTWEB_BING_KW_CRON ) ) {
			wp_schedule_single_event( ( new DateTimeImmutable( 'first day of next month 07:00', $tz ) )->getTimestamp(), INSIGHTWEB_BING_KW_CRON ); // Booked again after each run: WordPress has no monthly recurrence.
		}
	}
);

/**
 * The weekly crawl run.
 *
 * @param bool $dry Read, do not write.
 */
function insightweb_bing_crawl_run( bool $dry = false ): array {
	if ( get_option( 'insightweb_off' ) || ! insightweb_ready() || ! function_exists( 'insightweb_bing_on' ) || ! insightweb_bing_on() ) {
		return array( 'off' => true );
	}
	if ( ! $dry ) {
		insightweb_run_start( 'bing_crawl' );
	}
	$stats = insightweb_bing_call( 'GetCrawlStats' );
	if ( is_wp_error( $stats ) ) {
		if ( ! $dry ) {
			insightweb_run_fail( 'bing_crawl', $stats->get_error_message() );
		}
		return array( 'error' => $stats->get_error_message() );
	}
	$issues = insightweb_bing_call( 'GetCrawlIssues' );
	$rows   = 0;
	$up_to  = '';
	$keep   = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . ( insightweb_bing_days() ) . ' days' )->format( 'Y-m-d' );
	foreach ( (array) $stats as $r ) {
		$d = insightweb_bing_date( (string) ( $r['Date'] ?? '' ) );
		if ( '' === $d || $d < $keep ) {
			continue; // Days the rollup already folded into months are not stored again.
		}
		foreach ( INSIGHTWEB_BING_CRAWL_MAP as $field => $metric ) {
			if ( isset( $r[ $field ] ) && ! $dry ) {
				insightweb_obs_put( 0, '', 'bing', $metric, 'day', $d, array( 'value' => (float) $r[ $field ] ) );
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
		insightweb_run_ok( 'bing_crawl', $info );
	}
	return $info;
}
add_action( INSIGHTWEB_BING_CRAWL_CRON, fn() => insightweb_bing_crawl_run() );

/**
 * The monthly keyword-volume run for the P1 and quick-win keywords (English only).
 *
 * @param bool $dry Read, do not write.
 */
function insightweb_bing_kw_run( bool $dry = false ): array {
	if ( ! $dry ) {
		wp_clear_scheduled_hook( INSIGHTWEB_BING_KW_CRON ); // The init hook books next month's run.
	}
	if ( get_option( 'insightweb_off' ) || ! insightweb_ready() || ! function_exists( 'insightweb_bing_on' ) || ! insightweb_bing_on() ) {
		return array( 'off' => true );
	}
	global $wpdb;
	if ( ! $dry ) {
		insightweb_run_start( 'bing_kw' );
	}
	$k     = insightweb_kw_table( 'keywords' );
	$o     = insightweb_kw_table( 'obs' );
	$quick = insightweb_view_sql( 'quick' );
	$p1    = insightweb_view_sql( 'p1' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $p1 and $quick are the fixed view conditions of insightweb_view_sql(); the table is %i.
	$kws  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, label FROM %i WHERE ( {$p1} OR {$quick} ) AND COALESCE(lang, 'en') = 'en' AND label NOT LIKE %s ORDER BY impr28 DESC LIMIT %d", $k, '%' . $wpdb->esc_like( '{' ) . '%', INSIGHTWEB_BING_KW ) );
	$rows = 0;
	$fail = array();
	$cut  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-84 days' )->format( 'Y-m-d' );
	foreach ( $kws as $kw ) {
		$r = insightweb_bing_call(
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
			$d = insightweb_bing_date( (string) ( $w['Date'] ?? '' ) );
			if ( '' === $d || $d < $cut ) {
				continue;
			}
			if ( ! $dry ) {
				insightweb_obs_put(
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
	$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE src = 'bing' AND metric = 'kw-volume' AND period = 'week' AND d < %s", $o, $cut ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $rows && $fail ) {
		insightweb_run_fail( 'bing_kw', (string) $fail[0] );
	} else {
		insightweb_run_ok(
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
add_action( INSIGHTWEB_BING_KW_CRON, fn() => insightweb_bing_kw_run() );

/** Overview box: Bing's crawl over the last 28 days and the last 12 weeks. */
function insightweb_box_bing_crawl(): void {
	global $wpdb;
	if ( ! insightweb_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$o    = insightweb_kw_table( 'obs' );
	$from = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-84 days' )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT YEARWEEK(d, 3) wk, MIN(d) since, MAX(d) last, SUM(IF(metric = 'crawl-pages', value, 0)) pages, SUM(IF(metric = 'crawl-errors', value, 0)) errors, SUM(IF(metric = 'code-4xx', value, 0)) c4, SUM(IF(metric = 'code-5xx', value, 0)) c5, SUM(IF(metric = 'robots-blocked', value, 0)) robots, MAX(IF(metric = 'in-index', value, 0)) idx FROM %i WHERE src = 'bing' AND period = 'day' AND metric IN ('crawl-pages', 'crawl-errors', 'code-4xx', 'code-5xx', 'robots-blocked', 'in-index') AND d >= %s GROUP BY wk ORDER BY wk DESC LIMIT 12", $o, $from ) );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No Bing crawl figures yet (weekly, Monday 06:45 IST).', 'insightweb' ) . '</p>';
		return;
	}
	$n = fn( $v ) => esc_html( number_format_i18n( (int) $v ) );
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Week from', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Pages crawled', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Crawl errors', 'insightweb' ) . '</th><th scope="col">4xx</th><th scope="col">5xx</th><th scope="col">' . esc_html__( 'Blocked by robots.txt', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'In Bing\'s index', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( wp_date( 'j M', strtotime( (string) $r->since ) ) ) . '</td><td>' . $n( $r->pages ) . '</td><td>' . $n( $r->errors ) . '</td><td>' . $n( $r->c4 ) . '</td><td>' . $n( $r->c5 ) . '</td><td>' . $n( $r->robots ) . '</td><td>' . $n( $r->idx ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $n.
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Bing Webmaster crawl statistics by week (Monday to Sunday); "In Bing\'s index" is the highest count of the week.', 'insightweb' ) . '</p>';
}
