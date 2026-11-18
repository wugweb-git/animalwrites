<?php
/**
 * Feeds: the list of feed URLs the administrator configures as content sources, and the gated fetch for them.
 *
 * Each configured feed's host is auto-registered in the external-services registry (see external-services.php),
 * so (a) the outbound gate permits fetching it and (b) it is disclosed in the generated "== External services =="
 * section and the admin screen. Fetching always goes through sourceweb_remote_request(), so a feed whose host is
 * not registered-and-enabled cannot be contacted.
 *
 * @package sourceweb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * The configured feed URLs (option `sourceweb_feeds`), sanitised to valid http(s) URLs.
 *
 * @return string[]
 */
function sourceweb_feeds() {
	$raw = function_exists( 'get_option' ) ? get_option( 'sourceweb_feeds' ) : array();
	return sourceweb_clean_feed_list( $raw );
}

/**
 * Clean an arbitrary value (array or newline list) into a de-duplicated list of valid http(s) feed URLs.
 *
 * @param mixed $value Raw feeds value.
 * @return string[]
 */
function sourceweb_clean_feed_list( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/\r\n|\r|\n/', $value );
	}
	$out = array();
	foreach ( (array) $value as $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			continue;
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}
		$clean = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
		if ( '' !== $clean ) {
			$out[] = $clean;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * The distinct hosts of the configured feeds (normalised: lower case, no "www.").
 *
 * @param string[]|null $feeds Optional feed list (for testing); defaults to the stored feeds.
 * @return string[]
 */
function sourceweb_feed_hosts( $feeds = null ) {
	$feeds = null === $feeds ? sourceweb_feeds() : sourceweb_clean_feed_list( $feeds );
	$hosts = array();
	foreach ( $feeds as $url ) {
		$host = sourceweb_service_host( $url );
		if ( '' !== $host ) {
			$hosts[] = $host;
		}
	}
	return array_values( array_unique( $hosts ) );
}

/**
 * Register the configured feed hosts as external services, so fetching is permitted and the hosts are disclosed.
 * Hooked on `sourceweb_register_services`.
 *
 * @return void
 */
function sourceweb_register_feed_services() {
	$hosts = sourceweb_feed_hosts();
	if ( empty( $hosts ) ) {
		return;
	}
	sourceweb_register_external_service(
		'feeds',
		array(
			'hosts'     => $hosts,
			'purpose'   => 'Fetch the content feeds you configured under SourceWeb.',
			'data_sent' => 'An HTTP GET request for each feed URL (no personal data).',
			'enabled'   => true,
		)
	);
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'sourceweb_register_services', 'sourceweb_register_feed_services' );
}

/**
 * Fetch one feed through the gated outbound wrapper. Returns WP_Error when the feed host is not a
 * registered-and-enabled external service, or on transport failure.
 *
 * @param string $url Feed URL.
 * @return array|WP_Error The HTTP response, or WP_Error.
 */
function sourceweb_fetch_feed( $url ) {
	return sourceweb_remote_request(
		$url,
		array(
			'timeout'     => 15,
			'redirection' => 3,
			'user-agent'  => 'SourceWeb/' . ( defined( 'SOURCEWEB_VERSION' ) ? SOURCEWEB_VERSION : '0' ) . ' (WordPress plugin)',
		),
		'GET'
	);
}

/**
 * Fetch, parse and queue one feed. Each item runs through the review-queue decision + dedupe
 * (sourceweb_queue_add).
 *
 * @param string $url Feed URL.
 * @return array{fetched:int,queued:int,skipped:int,error:string}
 */
function sourceweb_ingest_feed( $url ) {
	$out      = array(
		'fetched' => 0,
		'queued'  => 0,
		'skipped' => 0,
		'error'   => '',
	);
	$response = sourceweb_fetch_feed( $url );
	if ( is_wp_error( $response ) ) {
		$out['error'] = $response->get_error_message();
		return $out;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		$out['error'] = 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
		return $out;
	}
	$items          = sourceweb_parse_feed( wp_remote_retrieve_body( $response ) );
	$out['fetched'] = count( $items );
	$bump           = array(
		'fetched'        => $out['fetched'],
		'queued'         => 0,
		'skipped_dedupe' => 0,
		'skipped_gate'   => 0,
		'skipped_other'  => 0,
	);
	foreach ( $items as $item ) {
		$result = sourceweb_queue_add(
			array(
				'title'       => $item['title'],
				'source_url'  => $item['url'],
				'summary'     => $item['summary'],
				'source_name' => sourceweb_service_host( $item['url'] ),
			)
		);
		if ( ! empty( $result['queued'] ) ) {
			++$out['queued'];
			++$bump['queued'];
		} else {
			++$out['skipped'];
			$metric = sourceweb_stats_skip_metric( isset( $result['reason'] ) ? (string) $result['reason'] : '' );
			++$bump[ $metric ];
		}
	}
	if ( function_exists( 'sourceweb_stats_bump' ) ) {
		sourceweb_stats_bump( $bump );
	}
	sourceweb_feed_run_record( $url, $out );
	return $out;
}

/**
 * The stored per-feed last-run results (option `sourceweb_feed_runs`), keyed by feed URL.
 *
 * @return array<string,array{time:int,fetched:int,queued:int,skipped:int,error:string}>
 */
function sourceweb_feed_runs() {
	$runs = function_exists( 'get_option' ) ? get_option( 'sourceweb_feed_runs' ) : array();
	return is_array( $runs ) ? $runs : array();
}

/**
 * Record one feed's last-run result, so the Feeds screen can show when it last ran and how it went.
 *
 * @param string $url Feed URL.
 * @param array  $out The ingest result ({fetched, queued, skipped, error}).
 * @return void
 */
function sourceweb_feed_run_record( $url, array $out ) {
	if ( ! function_exists( 'update_option' ) ) {
		return;
	}
	$runs         = sourceweb_feed_runs();
	$runs[ $url ] = array(
		'time'    => time(),
		'fetched' => (int) $out['fetched'],
		'queued'  => (int) $out['queued'],
		'skipped' => (int) $out['skipped'],
		'error'   => (string) $out['error'],
	);
	// Keep only runs for feeds that still exist.
	$keep = array_fill_keys( sourceweb_feeds(), true );
	foreach ( array_keys( $runs ) as $key ) {
		if ( ! isset( $keep[ $key ] ) ) {
			unset( $runs[ $key ] );
		}
	}
	update_option( 'sourceweb_feed_runs', $runs, false );
}

/**
 * Fetch, parse and queue every configured feed. Returns the summed counts.
 *
 * @return array{feeds:int,fetched:int,queued:int,skipped:int}
 */
function sourceweb_ingest_all() {
	$totals = array(
		'feeds'   => 0,
		'fetched' => 0,
		'queued'  => 0,
		'skipped' => 0,
	);
	foreach ( sourceweb_feeds() as $url ) {
		$one = sourceweb_ingest_feed( $url );
		++$totals['feeds'];
		$totals['fetched'] += $one['fetched'];
		$totals['queued']  += $one['queued'];
		$totals['skipped'] += $one['skipped'];
	}
	// Snapshot the review-queue size for the Overview dashboard's queue-size chart.
	if ( function_exists( 'sourceweb_stats_snapshot_queue' ) && function_exists( 'sourceweb_queue_pending_count' ) ) {
		sourceweb_stats_snapshot_queue( sourceweb_queue_pending_count() );
	}
	return $totals;
}
if ( function_exists( 'add_action' ) ) {
	add_action( 'sourceweb_ingest_event', 'sourceweb_ingest_all' );
}
