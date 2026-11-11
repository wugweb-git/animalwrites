<?php
/**
 * Search Console beyond web search (3 Nov 2026), through Site Kit's own Google client (no new key):
 *
 * - Discover and Google News, daily at 07:15 IST: clicks and impressions per page for the last 3 days Search Console
 *   has (each day replaced). Rows in obs: src gsc-types (not gsc: the daily Search Console run
 *   replaces every gsc row of the days it reads), metric discover or gnews, period day, page = path.
 * - URL Inspection, daily from 07:40 IST: up to AW_SI_INSPECT_CAP pages a day (Google allows 2,000 a day per site),
 *   never-checked pages first, then pages not indexed, then the oldest checks. A few calls per cron step, the next
 *   step five minutes later. A refused call (HTTP 403 or 429) stops it for the day. Rows in obs: src inspect, metric
 *   index, period state, d 2000-01-01 (one row per page, replaced at each check), value 1 indexed / 0 not, txt = Google's
 *   coverage state (the reason), fetched = when checked.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_GSC_TYPES_CRON = 'aw_si_gsc_types';
const AW_SI_INSPECT_CRON   = 'aw_si_inspect';
const AW_SI_INSPECT_MORE   = 'aw_si_inspect_more';
const AW_SI_INSPECT_QUEUE  = 'aw_si_inspect_queue';
const AW_SI_INSPECT_CAP    = 2000; // Google's daily URL Inspection limit per site. Filter aw_si_inspect_cap lowers it.
const AW_SI_INSPECT_BUDGET = 25;   // Seconds per cron step.
const AW_SI_INSPECT_D      = '2000-01-01'; // The one fixed date of the index-state rows.

add_filter(
	'aw_si_sources',
	function ( $s ) {
		$s['gsc_types'] = array(
			'label' => __( 'Search Console: Discover and Google News', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		);
		$s['inspect']   = array(
			'label' => __( 'URL Inspection (index status)', 'aw-seo-extras' ),
			'every' => DAY_IN_SECONDS,
		);
		return $s;
	}
);
add_filter( 'aw_si_cron_hooks', fn( $h ) => array_merge( (array) $h, array( AW_SI_GSC_TYPES_CRON, AW_SI_INSPECT_CRON, AW_SI_INSPECT_MORE ) ) );

add_action(
	'init',
	function () {
		if ( get_option( 'aw_si_off' ) ) {
			return;
		}
		$tz = wp_timezone();
		if ( ! wp_next_scheduled( AW_SI_GSC_TYPES_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 07:15', $tz ) )->getTimestamp(), 'daily', AW_SI_GSC_TYPES_CRON );
		}
		if ( ! wp_next_scheduled( AW_SI_INSPECT_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 07:40', $tz ) )->getTimestamp(), 'daily', AW_SI_INSPECT_CRON );
		}
	}
);

/**
 * Site Kit's Search Console service and the property, for calls Site Kit's own data points do not offer.
 *
 * @param object $module Site Kit's Search Console module.
 * @return array{0: object, 1: string}|WP_Error [service, property].
 */
function aw_si_gsc_service( $module ) {
	$cls = '\Google\Site_Kit_Dependencies\Google\Service\SearchConsole';
	if ( ! class_exists( $cls ) || ! method_exists( $module, 'get_client' ) ) {
		return new WP_Error( 'aw_si_gsc', __( 'This Site Kit version has no Search Console client to call.', 'aw-seo-extras' ) );
	}
	$settings = (array) get_option( 'googlesitekit_search-console_settings', array() );
	$property = (string) ( $settings['propertyID'] ?? '' );
	if ( '' === $property ) {
		return new WP_Error( 'aw_si_gsc', __( 'Search Console is not connected in Site Kit.', 'aw-seo-extras' ) );
	}
	return array( new $cls( $module->get_client() ), $property );
}

/**
 * HTTP status of a Google API exception, or 0.
 *
 * @param Throwable $e The exception.
 */
function aw_si_gsc_http_code( Throwable $e ): int {
	$code = (int) $e->getCode();
	return $code >= 100 && $code < 600 ? $code : 0;
}

/**
 * Discover and Google News: clicks and impressions per page for the last 3 days Search Console has, each day replaced.
 *
 * @param bool $dry Read, do not write.
 * @return array What was read.
 */
function aw_si_gsc_types_run( bool $dry = false ): array {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return array( 'off' => true );
	}
	if ( ! $dry ) {
		aw_si_run_start( 'gsc_types' );
	}
	$tz   = wp_timezone();
	$days = array();
	for ( $i = 4; $i >= 2; $i-- ) { // Search Console's figures arrive about two days late.
		$days[] = ( new DateTimeImmutable( 'today', $tz ) )->modify( "-{$i} days" )->format( 'Y-m-d' );
	}
	$out = aw_si_with_module(
		'search-console',
		function ( $module ) use ( $days, $dry ) {
			$svc = aw_si_gsc_service( $module );
			if ( is_wp_error( $svc ) ) {
				return $svc;
			}
			list( $service, $property ) = $svc;
			$req_cls = '\Google\Site_Kit_Dependencies\Google\Service\SearchConsole\SearchAnalyticsQueryRequest';
			$rep     = array(
				'rows'  => 0,
				'up_to' => '',
			);
			foreach ( array(
				'discover'   => 'discover',
				'googleNews' => 'gnews',
			) as $type => $metric ) {
				foreach ( $days as $day ) {
					$req = new $req_cls();
					$req->setStartDate( $day );
					$req->setEndDate( $day );
					$req->setDimensions( array( 'page' ) );
					$req->setType( $type );
					$req->setRowLimit( 1000 );
					try {
						$resp = $service->searchanalytics->query( $property, $req );
					} catch ( Throwable $e ) {
						/* translators: 1: Discover or Google News, 2: the problem. */
						return new WP_Error( 'aw_si_gsc', sprintf( __( '%1$s could not be read: %2$s', 'aw-seo-extras' ), $type, wp_strip_all_tags( $e->getMessage() ) ) );
					}
					$rows = array();
					foreach ( (array) $resp->getRows() as $row ) {
						$path = aw_si_path( (string) ( $row->getKeys()[0] ?? '' ) );
						if ( '' !== $path ) {
							$rows[ $path ] = array( (int) ( $rows[ $path ][0] ?? 0 ) + (int) $row->getClicks(), (int) ( $rows[ $path ][1] ?? 0 ) + (int) $row->getImpressions() ); // two addresses of one page add up
						}
					}
					$rep[ $metric ][ $day ] = count( $rows );
					$rep['rows']           += count( $rows );
					if ( $rows ) {
						$rep['up_to'] = max( $rep['up_to'], $day );
					}
					$n = $dry ? 0 : aw_si_gsc_types_store( $metric, $day, $rows );
					if ( is_wp_error( $n ) ) {
						return $n;
					}
				}
			}
			return $rep;
		}
	);
	if ( $dry ) {
		return is_wp_error( $out ) ? array( 'error' => $out->get_error_message() ) : $out;
	}
	if ( is_wp_error( $out ) ) {
		aw_si_run_fail( 'gsc_types', $out->get_error_message() );
		return array( 'error' => $out->get_error_message() );
	}
	if ( '' === $out['up_to'] ) {
		unset( $out['up_to'] ); // No Discover or News figures in those days: the run still counts as done.
	}
	aw_si_run_ok( 'gsc_types', $out );
	return $out;
}
add_action( AW_SI_GSC_TYPES_CRON, fn() => aw_si_gsc_types_run() );

/**
 * One day of one search type: that day's rows are replaced.
 *
 * @param string $metric discover or gnews.
 * @param string $day    Y-m-d.
 * @param array  $rows   path => [clicks, impressions].
 * @return int|WP_Error Rows written.
 */
function aw_si_gsc_types_store( string $metric, string $day, array $rows ) {
	$list = array();
	foreach ( $rows as $path => $r ) {
		$list[] = array( 0, (string) $path, '' === (string) $path ? '' : md5( (string) $path ), $metric, (int) $r[0], (int) $r[1], null );
	}
	return aw_si_obs_replace_day( 'gsc-types', $day, $list, 'day', array( $metric ), 'gsc' );
}

/**
 * Discover and Google News per page over the last $days days: path => [discover clicks, impressions, news clicks,
 * impressions], most impressions first.
 *
 * @param int $days  Days.
 * @param int $limit Pages.
 */
function aw_si_gsc_types_pages( int $days = 28, int $limit = 10 ): array {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		return array();
	}
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( "-{$days} days" )->format( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(IF(metric = 'discover', clicks, 0)) dc, SUM(IF(metric = 'discover', impr, 0)) di, SUM(IF(metric = 'gnews', clicks, 0)) nc, SUM(IF(metric = 'gnews', impr, 0)) ni FROM {$o} WHERE src = 'gsc-types' AND metric IN ('discover', 'gnews') AND period = 'day' AND d >= %s GROUP BY page ORDER BY (di + ni) DESC LIMIT %d", $since, $limit ) );
}

/** Overview box: Discover and Google News. */
function aw_si_box_gsc_types(): void {
	$rows = aw_si_gsc_types_pages();
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No Discover or Google News figures yet (Search Console, daily at 07:15 IST). Google shows these only once a page has appeared there.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$n = fn( $v ) => esc_html( number_format_i18n( (int) $v ) );
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Page', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Discover impressions', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Discover clicks', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Google News impressions', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Google News clicks', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><a href="' . esc_url( home_url( $r->page ) ) . '" target="_blank" rel="noopener">' . esc_html( $r->page ) . '</a></td><td>' . $n( $r->di ) . '</td><td>' . $n( $r->dc ) . '</td><td>' . $n( $r->ni ) . '</td><td>' . $n( $r->nc ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $n.
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Search Console, 28 days. Google News here means the Google News app and news.google.com, not the News tab of Google Search.', 'aw-seo-extras' ) . '</p>';
}

/** The daily cap of URL Inspection calls. */
function aw_si_inspect_cap(): int {
	return max( 0, min( AW_SI_INSPECT_CAP, (int) apply_filters( 'aw_si_inspect_cap', AW_SI_INSPECT_CAP ) ) );
}

/**
 * Today's pages to inspect: never checked first, then not indexed (oldest check first), then the oldest checks.
 *
 * @param int $cap How many.
 * @return string[] Paths.
 */
function aw_si_inspect_todo( int $cap ): array {
	global $wpdb;
	$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
	$ids   = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
	$paths = array( '/' => true );
	foreach ( $ids as $id ) {
		$p = aw_si_object_path( 'post', (int) $id );
		if ( '' !== $p ) {
			$paths[ $p ] = true;
		}
	}
	$o = aw_si_table( 'obs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$seen  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, value, fetched FROM {$o} WHERE src = 'inspect' AND metric = 'index' AND period = 'state' AND d = %s ORDER BY value ASC, fetched ASC", AW_SI_INSPECT_D ) );
	$known = array();
	foreach ( $seen as $r ) {
		$known[ $r->page ] = $r;
	}
	$never = array_keys( array_diff_key( $paths, $known ) );
	$again = array();
	foreach ( $seen as $r ) {
		if ( isset( $paths[ $r->page ] ) ) {
			$again[] = $r->page; // Already ordered: not indexed first, then the oldest checks.
		}
	}
	return array_slice( array_merge( $never, $again ), 0, $cap );
}

/** Start today's URL Inspection queue. */
function aw_si_inspect_start(): void {
	if ( get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return;
	}
	$q = get_option( AW_SI_INSPECT_QUEUE );
	if ( is_array( $q ) && ( $q['d'] ?? '' ) === wp_date( 'Y-m-d' ) ) {
		aw_si_inspect_step(); // Today's queue already started.
		return;
	}
	if ( is_array( $q ) && ! empty( $q['todo'] ) && ! empty( $q['done'] ) && '' === ( $q['refused'] ?? '' ) ) {
		// Yesterday's queue did not get through every page: log what it did, so the run is not left open for good.
		// The pages left are still first in line (never checked, then not indexed).
		aw_si_run_ok(
			'inspect',
			array(
				'rows'     => (int) $q['done'],
				'up_to'    => (string) $q['d'],
				'indexed'  => (int) $q['indexed'],
				/* translators: %d: pages. */
				'warnings' => array( sprintf( __( '%d pages were not reached that day.', 'aw-seo-extras' ), count( $q['todo'] ) ) ),
			)
		);
	}
	update_option(
		AW_SI_INSPECT_QUEUE,
		array(
			'd'       => wp_date( 'Y-m-d' ),
			'todo'    => aw_si_inspect_todo( aw_si_inspect_cap() ),
			'done'    => 0,
			'indexed' => 0,
			'errors'  => array(),
			'refused' => '',
		),
		false
	);
	aw_si_run_start( 'inspect' );
	aw_si_inspect_step();
}
add_action( AW_SI_INSPECT_CRON, 'aw_si_inspect_start' );

/**
 * Work through today's queue for up to AW_SI_INSPECT_BUDGET seconds; book the next step while pages are left.
 *
 * @return array The queue.
 */
function aw_si_inspect_step(): array {
	$q = get_option( AW_SI_INSPECT_QUEUE );
	if ( ! is_array( $q ) || empty( $q['todo'] ) || '' !== ( $q['refused'] ?? '' ) || get_option( 'aw_si_off' ) || ! aw_si_ready() ) {
		return is_array( $q ) ? $q : array();
	}
	if ( ! wp_next_scheduled( AW_SI_INSPECT_MORE ) ) {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, AW_SI_INSPECT_MORE ); // Booked first, as in psi.php.
	}
	$res = aw_si_with_module(
		'search-console',
		function ( $module ) use ( &$q ) {
			$svc = aw_si_gsc_service( $module );
			if ( is_wp_error( $svc ) ) {
				return $svc;
			}
			list( $service, $property ) = $svc;
			$req_cls = '\Google\Site_Kit_Dependencies\Google\Service\SearchConsole\InspectUrlIndexRequest';
			$start   = microtime( true );
			while ( $q['todo'] && microtime( true ) - $start < AW_SI_INSPECT_BUDGET ) {
				$path = (string) $q['todo'][0];
				$req  = new $req_cls();
				$req->setInspectionUrl( home_url( $path ) );
				$req->setSiteUrl( $property );
				try {
					$resp = $service->urlInspection_index->inspect( $req ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Google's client property.
				} catch ( Throwable $e ) {
					$code = aw_si_gsc_http_code( $e );
					if ( in_array( $code, array( 403, 429 ), true ) ) {
						/* translators: %d: HTTP status. */
						$q['refused'] = sprintf( __( 'Google refused URL Inspection (HTTP %d), so it stops for today.', 'aw-seo-extras' ), $code );
						break;
					}
					array_shift( $q['todo'] );
					$q['errors'][] = $path . ': ' . wp_html_excerpt( wp_strip_all_tags( $e->getMessage() ), 160, '…' );
					continue;
				}
				array_shift( $q['todo'] );
				$st      = $resp->getInspectionResult() ? $resp->getInspectionResult()->getIndexStatusResult() : null;
				$verdict = $st ? (string) $st->getVerdict() : '';
				$state   = $st ? (string) $st->getCoverageState() : '';
				$indexed = 'PASS' === $verdict;
				aw_si_obs_put(
					0,
					$path,
					'inspect',
					'index',
					'state',
					AW_SI_INSPECT_D,
					array(
						'value' => $indexed ? 1.0 : 0.0,
						'txt'   => '' !== $state ? $state : $verdict,
					)
				);
				++$q['done'];
				$q['indexed'] += $indexed ? 1 : 0;
			}
			return true;
		}
	);
	if ( is_wp_error( $res ) ) {
		$q['refused'] = $res->get_error_message();
	}
	$q['errors'] = array_slice( (array) $q['errors'], -20 );
	update_option( AW_SI_INSPECT_QUEUE, $q, false );
	if ( $q['todo'] && '' === $q['refused'] ) {
		aw_si_run_beat( 'inspect' );
		return $q;
	}
	wp_clear_scheduled_hook( AW_SI_INSPECT_MORE );
	if ( $q['done'] ) {
		aw_si_run_ok(
			'inspect',
			array(
				'rows'     => $q['done'],
				'up_to'    => $q['d'],
				'indexed'  => $q['indexed'],
				'warnings' => array_values( array_filter( array_merge( array( $q['refused'] ), (array) $q['errors'] ) ) ),
			)
		);
	} else {
		/* translators: %s: the problem. */
		aw_si_run_fail( 'inspect', sprintf( __( 'No URL Inspection call worked: %s', 'aw-seo-extras' ), '' !== $q['refused'] ? $q['refused'] : (string) ( $q['errors'][0] ?? '' ) ) );
	}
	return $q;
}
add_action( AW_SI_INSPECT_MORE, 'aw_si_inspect_step' );

/**
 * The stored index state of each page: path => [indexed (bool), Google's reason, when checked].
 *
 * @return array<string, array{0: bool, 1: string, 2: string}>
 */
function aw_si_index_states(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	global $wpdb;
	$cache = array();
	if ( ! aw_si_ready() ) {
		return $cache;
	}
	$o = aw_si_table( 'obs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, value, txt, fetched FROM {$o} WHERE src = 'inspect' AND metric = 'index' AND period = 'state' AND d = %s", AW_SI_INSPECT_D ) ) as $r ) {
		$cache[ $r->page ] = array( (float) $r->value > 0, (string) $r->txt, (string) $r->fetched );
	}
	return $cache;
}

/** Overview box: pages not indexed, by Google's reason. */
function aw_si_box_index(): void {
	$states = aw_si_index_states();
	if ( ! $states ) {
		echo '<p>' . esc_html__( 'No pages inspected yet (URL Inspection, daily from 07:40 IST, up to 2,000 pages a day).', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$by  = array();
	$yes = 0;
	foreach ( $states as $s ) {
		if ( $s[0] ) {
			++$yes;
		} else {
			$by[ '' !== $s[1] ? $s[1] : __( 'No reason given', 'aw-seo-extras' ) ] = ( $by[ '' !== $s[1] ? $s[1] : __( 'No reason given', 'aw-seo-extras' ) ] ?? 0 ) + 1;
		}
	}
	arsort( $by );
	/* translators: 1: pages inspected, 2: indexed, 3: not indexed. */
	echo '<p>' . esc_html( sprintf( __( '%1$s pages inspected: %2$s indexed, %3$s not indexed.', 'aw-seo-extras' ), number_format_i18n( count( $states ) ), number_format_i18n( $yes ), number_format_i18n( count( $states ) - $yes ) ) ) . '</p>';
	if ( $by ) {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Not indexed: Google\'s reason', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Pages', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
		foreach ( $by as $reason => $n ) {
			echo '<tr><td>' . esc_html( $reason ) . '</td><td>' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( isset( aw_si_extra_tabs()['pages'] ) ) {
		echo '<p class="description">' . esc_html__( 'Each page\'s state is on the Pages tab (Index column).', 'aw-seo-extras' ) . '</p>';
	}
}
