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
const AW_SI_INSPECT_BUDGET = 15;   // Seconds per cron step (12 Nov 2026: 25 left too little room under the host's 30-second PHP limit; the 11 Nov run died mid-step).
const AW_SI_INSPECT_GAP    = 300;  // Seconds between cron steps (WP-Cron runs them only when the site has visits).
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
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(IF(metric = 'discover', clicks, 0)) dc, SUM(IF(metric = 'discover', impr, 0)) di, SUM(IF(metric = 'gnews', clicks, 0)) nc, SUM(IF(metric = 'gnews', impr, 0)) ni FROM {$o} WHERE src = 'gsc-types' AND metric IN ('discover', 'gnews') AND period = 'day' AND d >= %s GROUP BY page ORDER BY SUM(impr) DESC LIMIT %d", $since, $limit ) );
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
 * @param int      $cap   How many.
 * @param int|null $never Set to the number of published pages never inspected (all of them, not only today's).
 * @return string[] Paths.
 */
function aw_si_inspect_todo( int $cap, ?int &$never = null ): array {
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
	$fresh = array_keys( array_diff_key( $paths, $known ) );
	$never = count( $fresh );
	$again = array();
	foreach ( $seen as $r ) {
		if ( isset( $paths[ $r->page ] ) ) {
			$again[] = $r->page; // Already ordered: not indexed first, then the oldest checks.
		}
	}
	return array_slice( array_merge( $fresh, $again ), 0, $cap );
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
		aw_si_run_ok( 'inspect', aw_si_inspect_info( $q ) );
	}
	$never = 0;
	$todo  = aw_si_inspect_todo( aw_si_inspect_cap(), $never );
	update_option(
		AW_SI_INSPECT_QUEUE,
		array(
			'd'       => wp_date( 'Y-m-d' ),
			'todo'    => $todo,
			'never'   => $never, // published pages never inspected when the day's queue started
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
		wp_schedule_single_event( time() + AW_SI_INSPECT_GAP, AW_SI_INSPECT_MORE ); // Booked first, as in psi.php.
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
						$q['quota']   = true; // only these two codes are Google's quota; other errors say so differently (aw_si_inspect_info())
						break;
					}
					array_shift( $q['todo'] );
					$q['errors'][] = $path . ': ' . wp_html_excerpt( wp_strip_all_tags( $e->getMessage() ), 160, '…' );
					$q['errors']   = array_slice( (array) $q['errors'], -20 );
					update_option( AW_SI_INSPECT_QUEUE, $q, false ); // Saved after every page, so a step cut short loses nothing.
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
				update_option( AW_SI_INSPECT_QUEUE, $q, false ); // Saved after every page, so a step cut short loses nothing.
				aw_si_run_beat( 'inspect' );
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
		aw_si_run_ok( 'inspect', aw_si_inspect_info( $q ) );
	} else {
		/* translators: %s: the problem. */
		aw_si_run_fail( 'inspect', sprintf( __( 'No URL Inspection call worked: %s', 'aw-seo-extras' ), '' !== $q['refused'] ? $q['refused'] : (string) ( $q['errors'][0] ?? '' ) ) );
	}
	return $q;
}
add_action( AW_SI_INSPECT_MORE, 'aw_si_inspect_step' );

/**
 * A day's URL Inspection queue as the run log's info: pages done and indexed and, when pages were left, why they were
 * not reached (Google's refusal or quota, else the time budget of the day's steps) and how many pages were never
 * inspected, for the estimate of when every page has been (aw_si_inspect_eta()). 12 Nov 2026: the bare "N pages were
 * not reached" line gave no reason and no end date.
 *
 * @param array $q The queue (AW_SI_INSPECT_QUEUE).
 */
function aw_si_inspect_info( array $q ): array {
	$done    = (int) ( $q['done'] ?? 0 );
	$left    = count( (array) ( $q['todo'] ?? array() ) );
	$refused = (string) ( $q['refused'] ?? '' );
	$warn    = array_values( array_filter( array_merge( array( $refused ), (array) ( $q['errors'] ?? array() ) ) ) );
	if ( $left ) {
		$pages = number_format_i18n( $left );
		if ( '' !== $refused && ! empty( $q['quota'] ) ) {
			/* translators: %s: pages. */
			$why = sprintf( __( '%s pages not reached that day: Google refused URL Inspection (HTTP 403 or 429, its quota), so the queue stopped.', 'aw-seo-extras' ), $pages );
		} elseif ( '' !== $refused ) {
			/* translators: %s: pages. */
			$why = sprintf( __( '%s pages not reached that day: Search Console could not be reached (the reason follows), so the queue stopped.', 'aw-seo-extras' ), $pages );
		} else {
			/* translators: 1: pages, 2: seconds of calls per step, 3: minutes between steps, 4: Google's daily limit. */
			$why = sprintf( __( '%1$s pages not reached that day: the time budget ran out (%2$s s of calls per step, a step every %3$s min when the site has visits), not Google\'s %4$s-a-day quota.', 'aw-seo-extras' ), $pages, number_format_i18n( AW_SI_INSPECT_BUDGET ), number_format_i18n( AW_SI_INSPECT_GAP / MINUTE_IN_SECONDS ), number_format_i18n( AW_SI_INSPECT_CAP ) );
		}
		array_unshift( $warn, $why );
	}
	$info = array(
		'rows'     => $done,
		'up_to'    => (string) ( $q['d'] ?? '' ),
		'indexed'  => (int) ( $q['indexed'] ?? 0 ),
		'left'     => $left,
		'warnings' => $warn,
	);
	if ( isset( $q['never'] ) ) {
		$info['never_left'] = max( 0, (int) $q['never'] - $done ); // never-checked pages go first in the queue
	}
	return $info;
}

/**
 * When every published page will have been inspected once, at the pages a day the queue got through.
 *
 * @param int    $never_left Pages never inspected.
 * @param int    $per_day    Pages inspected on the last day.
 * @param string $day        That day (Y-m-d).
 * @return string A short phrase, or '' when there is nothing to estimate.
 */
function aw_si_inspect_eta( int $never_left, int $per_day, string $day ): string {
	if ( $never_left <= 0 ) {
		return __( 'every page has been inspected at least once; the queue now checks not-indexed pages first, then the oldest checks', 'aw-seo-extras' );
	}
	if ( $per_day <= 0 || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
		return '';
	}
	$days = (int) ceil( $never_left / $per_day );
	$end  = (int) strtotime( $day . ' 12:00:00 +' . $days . ' days' );
	/* translators: 1: pages, 2: pages a day, 3: date. */
	return sprintf( __( '%1$s pages never inspected yet; at %2$s a day the queue reaches them all by about %3$s', 'aw-seo-extras' ), number_format_i18n( $never_left ), number_format_i18n( $per_day ), wp_date( 'j M Y', $end ) );
}

/**
 * URL Inspection's figures over every page it has checked, each by its latest result: [inspected, indexed, not
 * indexed, latest check (Y-m-d H:i:s or '')]. The Overview's "pages we inspected" line and the Pages tab's Not indexed
 * view use this one rule (12 Nov 2026).
 */
function aw_si_inspect_summary(): array {
	$states = aw_si_index_states();
	$yes    = count( array_filter( $states, fn( $s ) => $s[0] ) );
	$latest = $states ? (string) max( array_map( fn( $s ) => (string) $s[2], $states ) ) : '';
	return array( count( $states ), $yes, count( $states ) - $yes, $latest );
}

/**
 * Search Console's Page indexing totals as last entered: [indexed, not indexed, report day, whether "not indexed" is
 * the sum of the reasons entered (no total typed)], or null when no indexed total is entered.
 *
 * @param array $c aw_si_coverage().
 */
function aw_si_coverage_totals( array $c ): ?array {
	if ( ! isset( $c['indexed'] ) ) {
		return null;
	}
	if ( isset( $c['not_indexed'] ) ) {
		return array( (int) $c['indexed'], (int) $c['not_indexed'], (string) ( $c['day'] ?? '' ), false );
	}
	$sum = 0;
	foreach ( array_keys( aw_si_coverage_fields() ) as $k ) {
		$sum += in_array( $k, array( 'indexed', 'not_indexed' ), true ) ? 0 : (int) ( $c[ $k ] ?? 0 );
	}
	return array( (int) $c['indexed'], $sum, (string) ( $c['day'] ?? '' ), true );
}

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

/* ---------- Search Console coverage totals (entered by hand) and the Request indexing list (owner, 12 Nov 2026) ---------- */

const AW_SI_COVERAGE_OPTION = 'aw_si_gsc_coverage';

/** The coverage rows Search Console's Page indexing report shows: key => label. */
function aw_si_coverage_fields(): array {
	return array(
		'indexed'     => __( 'Indexed', 'aw-seo-extras' ),
		'not_indexed' => __( 'Not indexed (the total at the top of the report)', 'aw-seo-extras' ),
		'discovered'  => __( 'Discovered, currently not indexed', 'aw-seo-extras' ),
		'crawled'     => __( 'Crawled, currently not indexed', 'aw-seo-extras' ),
		'noindex'     => __( 'Excluded by noindex', 'aw-seo-extras' ),
		'canonical'   => __( 'Alternate page with proper canonical tag', 'aw-seo-extras' ),
	);
}

/** The totals last entered: field => count, plus 'day' (Y-m-d, the report's date). */
function aw_si_coverage(): array {
	return (array) get_option( AW_SI_COVERAGE_OPTION, array() );
}

add_action(
	'admin_post_aw_si_coverage',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_coverage' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$out = array();
		foreach ( array_keys( aw_si_coverage_fields() ) as $k ) {
			$v = isset( $_POST[ $k ] ) ? str_replace( array( ',', ' ' ), '', sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : '';
			if ( '' !== $v && ctype_digit( $v ) ) {
				$out[ $k ] = (int) $v;
			}
		}
		$day        = isset( $_POST['day'] ) ? sanitize_text_field( wp_unslash( $_POST['day'] ) ) : '';
		$out['day'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : wp_date( 'Y-m-d' );
		update_option( AW_SI_COVERAGE_OPTION, $out, false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : aw_si_url( '' ) );
		exit;
	}
);

/** The coverage totals as a table, with the form to enter them. */
function aw_si_coverage_html(): string {
	$c   = aw_si_coverage();
	$out = '<h3>' . esc_html__( 'Search Console Page indexing (the whole site, entered by hand)', 'aw-seo-extras' ) . '</h3>';
	$out .= '<p class="description">' . ( ! empty( $c['day'] ) ? esc_html( sprintf( /* translators: %s: date. */ __( 'From Search Console → Pages, %s.', 'aw-seo-extras' ), wp_date( 'j M Y', strtotime( $c['day'] . ' 12:00' ) ) ) ) : esc_html__( 'Not entered yet: copy the figures from Search Console → Pages.', 'aw-seo-extras' ) ) . '</p>';
	$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_coverage">' . wp_nonce_field( 'aw_si_coverage', '_wpnonce', true, false );
	$out .= '<table class="widefat striped"><tbody>';
	foreach ( aw_si_coverage_fields() as $k => $label ) {
		$out .= '<tr><th scope="row"><label for="aw-si-cov-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" inputmode="numeric" class="small-text" id="aw-si-cov-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( isset( $c[ $k ] ) ? (string) $c[ $k ] : '' ) . '"></td></tr>';
	}
	$out .= '<tr><th scope="row"><label for="aw-si-cov-day">' . esc_html__( 'Report date', 'aw-seo-extras' ) . '</label></th><td><input type="date" id="aw-si-cov-day" name="day" value="' . esc_attr( (string) ( $c['day'] ?? '' ) ) . '"></td></tr>';
	$out .= '</tbody></table>' . '<p class="submit"><button type="submit" class="button button-small">' . esc_html__( 'Save the totals', 'aw-seo-extras' ) . '</button></p>' . '</form>';
	return $out;
}

// The same form on the Settings tab (12 Nov 2026), where the new screen keeps forms; the new Dashboard's boxes show text only.
add_action(
	'aw_si_settings_rows',
	function () {
		echo '<tr><th scope="row">' . esc_html__( 'Search Console Page indexing', 'aw-seo-extras' ) . '</th><td>' . aw_si_coverage_html() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in aw_si_coverage_html().
	},
	5
);

/** Type order for the Request indexing list: guides, Library, campaigns, then the rest, listings last. */
function aw_si_request_type_rank( int $id ): int {
	$rank = array( 'post' => 0, 'aw_legal' => 1, 'aw_campaign' => 2, 'page' => 3, 'aw_news' => 4, 'aw_opportunity' => 5, 'aw_listing' => 9 );
	return $id ? ( $rank[ (string) get_post_type( $id ) ] ?? 6 ) : 7;
}

/**
 * The highest-value pages URL Inspection found not indexed, for asking Google by hand (Search Console → URL
 * Inspection → Request indexing): no internal link in first (Rank Math's link counter, links in the text of other
 * items), then the most Search Console impressions in 28 days, then the type (aw_si_request_type_rank()). Read only.
 *
 * @param int $n How many.
 * @return array<int, array{path: string, id: int, links_in: int|null, impr: int, reason: string}>
 */
function aw_si_request_list( int $n = 10 ): array {
	$key    = 'aw_si_request_' . $n . '_' . gmdate( 'Ymd' ); // one list a day: each row looks up its page (url_to_postid)
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$list = aw_si_request_build( $n );
	set_transient( $key, $list, DAY_IN_SECONDS );
	return $list;
}

/**
 * Works out the Request indexing list (aw_si_request_list(), which keeps it for the day).
 *
 * @param int $n How many.
 * @return array[]
 */
function aw_si_request_build( int $n ): array {
	global $wpdb;
	$rows = array();
	foreach ( aw_si_index_states() as $path => $st ) {
		if ( ! $st[0] && '/' === substr( (string) $path, 0, 1 ) ) {
			$rows[ $path ] = array( 'path' => (string) $path, 'id' => (int) url_to_postid( home_url( $path ) ), 'links_in' => null, 'impr' => 0, 'reason' => (string) $st[1] );
		}
	}
	if ( ! $rows ) {
		return array();
	}
	$ids = array_filter( wp_list_pluck( $rows, 'id' ) );
	if ( $ids ) {
		$t = $wpdb->prefix . 'rank_math_internal_meta';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders -- table name only; one %d per ID.
		$in = (array) $wpdb->get_results( $wpdb->prepare( "SELECT object_id, incoming_link_count FROM {$t} WHERE object_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_values( $ids ) ) );
		$by = array();
		foreach ( $in as $r ) {
			$by[ (int) $r->object_id ] = (int) $r->incoming_link_count;
		}
		foreach ( $rows as $p => $r ) {
			$rows[ $p ]['links_in'] = $r['id'] ? ( $by[ $r['id'] ] ?? 0 ) : null;
		}
	}
	foreach ( aw_si_page_metrics( array(), 28 ) as $page => $m ) {
		if ( isset( $rows[ $page ] ) ) {
			$rows[ $page ]['impr'] = (int) ( $m['impr'] ?? 0 );
		}
	}
	$rows = array_values( $rows );
	usort(
		$rows,
		fn( $a, $b ) => array( 0 === $a['links_in'] ? 0 : 1, -$a['impr'], aw_si_request_type_rank( $a['id'] ), $a['path'] ) <=> array( 0 === $b['links_in'] ? 0 : 1, -$b['impr'], aw_si_request_type_rank( $b['id'] ), $b['path'] )
	);
	return array_slice( $rows, 0, $n );
}

/** Overview box: today's Request indexing list. */
function aw_si_box_request(): void {
	$rows = aw_si_request_list( 10 );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing to ask for: URL Inspection has found no page that is not indexed.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	echo '<p class="description">' . esc_html__( 'Ten pages a day to ask Google for by hand: Search Console → URL Inspection → Request indexing. Not indexed (URL Inspection); no link in from another item first, then the most impressions, then guides, Library and campaigns before listings.', 'aw-seo-extras' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Page', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Links in', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Impressions (28 days)', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Google\'s reason', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><a href="' . esc_url( home_url( $r['path'] ) ) . '">' . esc_html( $r['path'] ) . '</a></td><td>' . esc_html( null === $r['links_in'] ? '—' : number_format_i18n( $r['links_in'] ) ) . '</td><td>' . esc_html( number_format_i18n( $r['impr'] ) ) . '</td><td>' . esc_html( $r['reason'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * What the last URL Inspection run says about its queue: pages not reached and why (from its warnings), and when every
 * page will have been inspected once.
 *
 * @param array $info The inspect run's info.
 * @return string[]
 */
function aw_si_inspect_queue_lines( array $info ): array {
	$out = array();
	if ( ! empty( $info['left'] ) ) {
		$out[] = (string) ( (array) ( $info['warnings'] ?? array() ) )[0]; // aw_si_inspect_info() puts the not-reached line first
	}
	$out[] = aw_si_inspect_info_eta( $info );
	return array_values( array_filter( $out ) );
}

/**
 * The estimate of aw_si_inspect_eta() from an inspect run's info, or '' when the run kept no count of pages never
 * inspected (runs before 12 Nov 2026).
 *
 * @param array $info The inspect run's info (aw_si_inspect_info()).
 */
function aw_si_inspect_info_eta( array $info ): string {
	if ( ! isset( $info['never_left'] ) ) {
		return '';
	}
	return aw_si_inspect_eta( (int) $info['never_left'], (int) ( $info['rows'] ?? 0 ), (string) ( $info['up_to'] ?? '' ) );
}

/**
 * Overview box (12 Nov 2026: three different "not indexed" figures). The headline is Search Console's Page indexing
 * totals with the day they were read; then URL Inspection's figures, labelled as the pages we inspected, each by its
 * latest result (the Pages tab's Not indexed view counts the same pages); the queue's state; Google's reasons.
 */
function aw_si_box_index(): void {
	$tot = aw_si_coverage_totals( aw_si_coverage() );
	if ( $tot ) {
		$day = '' !== $tot[2] ? wp_date( 'j M Y', (int) strtotime( $tot[2] . ' 12:00:00' ) ) : __( 'date not entered', 'aw-seo-extras' );
		$msg = $tot[3]
			/* translators: 1: date read, 2: indexed, 3: not indexed. */
			? __( 'Search Console Page indexing, read %1$s: %2$s indexed, %3$s not indexed (the reasons entered below added up; enter the report\'s Not indexed total for the exact figure).', 'aw-seo-extras' )
			/* translators: 1: date read, 2: indexed, 3: not indexed. */
			: __( 'Search Console Page indexing, read %1$s: %2$s indexed, %3$s not indexed.', 'aw-seo-extras' );
		echo '<p><strong>' . esc_html( sprintf( $msg, $day, number_format_i18n( $tot[0] ), number_format_i18n( $tot[1] ) ) ) . '</strong></p>';
		echo '<p class="description">' . esc_html__( 'The whole site as Google counts it. Copied by hand from Search Console → Pages (Settings tab, or the form below).', 'aw-seo-extras' ) . '</p>';
	} else {
		echo '<p><strong>' . esc_html__( 'Search Console Page indexing totals are not entered yet, so there is no whole-site figure: copy them from Search Console → Pages (Settings tab, or the form below). The figures here are only the pages we inspected.', 'aw-seo-extras' ) . '</strong></p>';
	}
	$states = aw_si_index_states();
	if ( ! $states ) {
		echo '<p>' . esc_html__( 'No pages inspected yet (URL Inspection, daily from 07:40 IST, up to 2,000 pages a day).', 'aw-seo-extras' ) . '</p>';
		echo aw_si_coverage_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in aw_si_coverage_html().
		return;
	}
	list( $all, $yes, $not, $latest ) = aw_si_inspect_summary();
	echo '<h3>' . esc_html__( 'URL Inspection: pages we inspected, latest result', 'aw-seo-extras' ) . '</h3>';
	/* translators: 1: pages inspected, 2: indexed, 3: not indexed, 4: date of the latest check. */
	echo '<p>' . esc_html( sprintf( __( 'Pages we inspected, latest result: %1$s pages, %2$s indexed, %3$s not indexed (latest check %4$s).', 'aw-seo-extras' ), number_format_i18n( $all ), number_format_i18n( $yes ), number_format_i18n( $not ), '' !== $latest ? mysql2date( 'j M Y', $latest ) : '–' ) ) . '</p>';
	/* translators: %s: pages inspected a day at most. */
	echo '<p class="description">' . esc_html( sprintf( __( 'Each page counts once, by its latest check (up to %s a day, never-checked and not-indexed pages first), so these figures lean to pages that are not indexed and are not the whole site.', 'aw-seo-extras' ), number_format_i18n( AW_SI_INSPECT_CAP ) ) ) . '</p>';
	$queue = aw_si_inspect_queue_lines( (array) ( aw_si_runs()['inspect']['info'] ?? array() ) );
	if ( $queue ) {
		echo '<p class="description">' . esc_html( implode( ' ', array_map( fn( $l ) => rtrim( ucfirst( $l ), '.' ) . '.', $queue ) ) ) . '</p>';
	}
	$by = array();
	foreach ( $states as $s ) {
		if ( ! $s[0] ) {
			$reason        = '' !== $s[1] ? $s[1] : __( 'No reason given', 'aw-seo-extras' );
			$by[ $reason ] = ( $by[ $reason ] ?? 0 ) + 1;
		}
	}
	arsort( $by );
	if ( $by ) {
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Not indexed: Google\'s reason', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Pages', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
		foreach ( $by as $reason => $n ) {
			echo '<tr><td>' . esc_html( $reason ) . '</td><td>' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( isset( aw_si_extra_tabs()['pages'] ) ) {
		echo '<p class="description">' . esc_html__( 'Each page\'s state is on the Pages tab (Google index column); its "Not indexed (URL Inspection)" view counts the same pages as the not-indexed figure above.', 'aw-seo-extras' ) . '</p>';
	}
	echo aw_si_coverage_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in aw_si_coverage_html().
}
