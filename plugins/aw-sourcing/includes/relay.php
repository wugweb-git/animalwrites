<?php
/**
 * Relay for the workers (owner, 20 Oct 2026). Some publishers refuse GitHub's servers (PFA's Substack, Zoom TV: 403)
 * but answer this server. The workers ask GET aw/v1/relay?url=... and get the page or feed as this server sees it.
 *
 * Limits: the workers' login only (an application password), hosts on the allow list (since phase 1 of the sourcing
 * restructure, 25 Oct 2026: the "Fetch through this site (relay)" switch on each source in Sourcing → Sources),
 * http/https, GET only. The host is resolved first and refused when any address is private, loopback, reserved or this
 * site's own; the checked address is the one connected to, and every redirect (at most 3) is checked the same way.
 * At most 5 MB, 20 seconds, 30 requests per host per 10 minutes. Every request is logged (last 200, Sourcing → Relay log).
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_RELAY_MAX_BYTES = 5 * MB_IN_BYTES;
const AW_RELAY_TIMEOUT   = 20;
const AW_RELAY_RATE      = 30; // requests per host per 10 minutes
const AW_RELAY_DEFAULT   = array( 'substack.com', 'zoomtventertainment.com', 'awbi.gov.in', 'ccsea.gov.in', 'egazette.gov.in' );

/**
 * Allowed hosts (a host or any of its subdomains): once phase 1 is migrated, the hosts whose relay switch is on
 * (aw_publishers[host].relay); before that, and after a rollback, the list of before (aw_relay_hosts_legacy).
 */
function aw_relay_hosts() {
	if ( aw_sourcing_phase() >= 1 ) {
		return aw_src_relay_hosts_from_pubs( function_exists( 'aw_publishers' ) ? aw_publishers() : array() );
	}
	return aw_relay_hosts_legacy();
}

/** The allow list of before phase 1: aw_sources.relay_hosts when it was saved, else the built-in list. */
function aw_relay_hosts_legacy() {
	return aw_src_relay_legacy( function_exists( 'aw_sources_config' ) ? aw_sources_config() : array(), AW_RELAY_DEFAULT );
}

/** True when the host is on the allow list. */
function aw_relay_host_allowed( $host ) {
	return aw_src_host_in( (string) $host, aw_relay_hosts() );
}

/** This site's own addresses (never fetched through the relay). */
function aw_relay_own_ips() {
	$own = array_filter( array( (string) ( $_SERVER['SERVER_ADDR'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$own = array_merge( $own, (array) gethostbynamel( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	return array_values( array_unique( array_filter( $own ) ) );
}

/**
 * The address to connect to, or why the host is refused.
 *
 * @param string $host Host name.
 * @return string|WP_Error
 */
function aw_relay_resolve( $host ) {
	$host = strtolower( $host );
	if ( $host === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) || filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return new WP_Error( 'aw_relay_host', 'host refused: ' . $host, array( 'status' => 403 ) );
	}
	$ips = array();
	foreach ( (array) @dns_get_record( $host, DNS_A | DNS_AAAA ) as $r ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$ips[] = $r['ip'] ?? ( $r['ipv6'] ?? '' );
	}
	$ips = array_values( array_unique( array_filter( array_merge( $ips, (array) gethostbynamel( $host ) ) ) ) );
	if ( ! $ips ) {
		return new WP_Error( 'aw_relay_dns', 'host does not resolve: ' . $host, array( 'status' => 502 ) );
	}
	$own = aw_relay_own_ips();
	foreach ( $ips as $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || in_array( $ip, $own, true ) ) {
			return new WP_Error( 'aw_relay_ip', 'address refused for ' . $host . ': ' . $ip, array( 'status' => 403 ) );
		}
	}
	$v4 = array_values( array_filter( $ips, fn( $ip ) => filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) );
	return $v4 ? $v4[0] : $ips[0];
}

/**
 * WordPress's CA bundle plus the intermediates some government sites do not send (data/certs: eGazette's Let's Encrypt
 * YR1/YR2 and Root YR, AWBI's eMudhra chain), the same as the workers use. Cached in the temp folder by content.
 */
function aw_relay_ca_bundle() {
	$text = (string) file_get_contents( ABSPATH . WPINC . '/certificates/ca-bundle.crt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( (array) glob( AW_CORE_DIR . 'data/certs/*.pem' ) as $pem ) {
		$text .= "\n" . file_get_contents( $pem ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$path = trailingslashit( get_temp_dir() ) . 'aw-relay-ca-' . substr( md5( $text ), 0, 12 ) . '.pem';
	if ( ! file_exists( $path ) ) {
		file_put_contents( $path, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $path;
}

/** Append to the relay log (last 200). */
function aw_relay_log( $row ) {
	$log   = (array) get_option( 'aw_relay_log', array() );
	$log[] = $row;
	update_option( 'aw_relay_log', array_slice( $log, -200 ), false );
}

/**
 * Fetch a URL for the workers.
 *
 * @param string $url URL.
 * @return array|WP_Error {status, final_url, content_type, body}
 */
function aw_relay_fetch( $url ) {
	$start = microtime( true );
	$first = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$done  = function ( $res, $note = '' ) use ( $url, $first, $start ) {
		aw_relay_log(
			array(
				'at'     => time(),
				'host'   => $first,
				'path'   => mb_substr( (string) wp_parse_url( $url, PHP_URL_PATH ), 0, 120 ),
				'status' => is_wp_error( $res ) ? $res->get_error_code() : (int) $res['status'],
				'bytes'  => is_wp_error( $res ) ? 0 : strlen( $res['body'] ),
				'ms'     => (int) round( ( microtime( true ) - $start ) * 1000 ),
				'note'   => is_wp_error( $res ) ? $res->get_error_message() : $note,
			)
		);
		return $res;
	};
	if ( ! aw_relay_host_allowed( $first ) ) {
		return $done( new WP_Error( 'aw_relay_not_allowed', 'host not on the relay list: ' . $first, array( 'status' => 403 ) ) );
	}
	$key = 'aw_relay_rl_' . md5( $first );
	$n   = (int) get_transient( $key );
	if ( $n >= AW_RELAY_RATE ) {
		return $done( new WP_Error( 'aw_relay_rate', 'too many requests for ' . $first, array( 'status' => 429 ) ) );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

	for ( $hop = 0; $hop <= 3; $hop++ ) {
		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || ! $host ) {
			return $done( new WP_Error( 'aw_relay_url', 'only http and https addresses', array( 'status' => 400 ) ) );
		}
		if ( ! aw_relay_host_allowed( $host ) ) {
			return $done( new WP_Error( 'aw_relay_not_allowed', 'redirect to a host not on the relay list: ' . $host, array( 'status' => 403 ) ) );
		}
		$ip = aw_relay_resolve( $host );
		if ( is_wp_error( $ip ) ) {
			return $done( $ip );
		}
		$port = (int) ( $parts['port'] ?? ( 'https' === $parts['scheme'] ? 443 : 80 ) );
		$pin  = function ( $handle ) use ( $host, $port, $ip ) {
			curl_setopt( $handle, CURLOPT_RESOLVE, array( $host . ':' . $port . ':' . ( str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		};
		add_action( 'http_api_curl', $pin );
		$resp = wp_remote_get(
			$url,
			array(
				'timeout'             => AW_RELAY_TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => AW_RELAY_MAX_BYTES,
				'user-agent'          => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
				'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml,application/xml,application/rss+xml;q=0.9,*/*;q=0.8' ),
				'reject_unsafe_urls'  => true,
				'sslcertificates'     => aw_relay_ca_bundle(),
			)
		);
		remove_action( 'http_api_curl', $pin );
		if ( is_wp_error( $resp ) ) {
			return $done( new WP_Error( 'aw_relay_fetch', $resp->get_error_message(), array( 'status' => 502 ) ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$loc  = (string) wp_remote_retrieve_header( $resp, 'location' );
		if ( $code >= 300 && $code < 400 && $loc ) {
			$url = WP_Http::make_absolute_url( $loc, $url );
			continue;
		}
		return $done(
			array(
				'status'       => $code,
				'final_url'    => $url,
				'content_type' => (string) wp_remote_retrieve_header( $resp, 'content-type' ),
				'body'         => (string) wp_remote_retrieve_body( $resp ),
			),
			$hop ? $hop . ' redirect(s)' : ''
		);
	}
	return $done( new WP_Error( 'aw_relay_redirects', 'more than 3 redirects', array( 'status' => 502 ) ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/relay',
			array(
				'methods'             => 'GET',
				// The workers' login only: an application password (never a browser session).
				'permission_callback' => fn() => current_user_can( 'edit_posts' ) && did_action( 'application_password_did_authenticate' ),
				'args'                => array( 'url' => array( 'required' => true, 'type' => 'string' ) ),
				'callback'            => function ( WP_REST_Request $req ) {
					$res = aw_relay_fetch( esc_url_raw( (string) $req->get_param( 'url' ), array( 'http', 'https' ) ) );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					$res['body'] = base64_encode( $res['body'] ); // any encoding, any bytes
					return new WP_REST_Response( $res );
				},
			)
		);
	}
);

// The workers learn the allow list from the sourcing policy (whether or not they use the site's lists).
add_filter(
	'kits_sourcing_policy',
	function ( $data ) {
		$data['relay_hosts'] = aw_relay_hosts();
		return $data;
	}
);

/*
 * The Relay tab moved into Sources (25 Oct 2026): each source has its relay switch. The request log is a report of its
 * own, Sourcing → Relay log (owner, 25 Oct 2026: reports apart from settings).
 */
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs['relay-log'] = array( 'Relay log', 'aw_relay_log_view', true ); // nothing to save: its search is a GET form
		return $tabs;
	}
);

/*
 * The old Relay form posted to options.php (aw_sources[_aw_tab] = relay). A copy still open in another browser tab must
 * not replace the option: aw_sources_sanitize() hands such a post here, and it changes nothing (as for the old
 * Categories and Sections forms, sourcing-terms-admin.php).
 */
add_filter( 'aw_sources_save_relay', fn( $c ) => $c );

/** Sourcing → Relay log: every request the workers made through the site (last 200). */
function aw_relay_log_view() {
	$log  = array_reverse( (array) get_option( 'aw_relay_log', array() ) );
	$rows = array();
	foreach ( array_slice( $log, 0, 200 ) as $r ) {
		$rows[] = array(
			'at'     => esc_html( wp_date( 'j M Y, g:i a', (int) $r['at'] ) ),
			'host'   => esc_html( $r['host'] ),
			'path'   => esc_html( $r['path'] ),
			'status' => esc_html( (string) $r['status'] ),
			'bytes'  => esc_html( size_format( (int) $r['bytes'] ) ),
			'ms'     => (int) $r['ms'] . ' ms',
			'note'   => esc_html( $r['note'] ),
			'_sort'  => array( 'at' => (int) $r['at'], 'bytes' => (int) $r['bytes'], 'ms' => (int) $r['ms'] ),
			'_find'  => $r['host'] . ' ' . $r['path'] . ' ' . $r['status'] . ' ' . $r['note'],
		);
	}
	aw_sourcing_simple_table(
		'requests',
		array( 'at' => 'Time', 'host' => 'Host', 'path' => 'Path', 'status' => 'Result', 'bytes' => 'Size', 'ms' => 'Time taken', 'note' => 'Note' ),
		$rows,
		array( 'per_page' => 50, 'sortable' => array( 'at', 'host', 'status', 'bytes', 'ms' ), 'empty' => 'None yet.', 'search' => 'Search requests', 'hidden' => array( 'page' => 'aw-sourcing', 'tab' => 'relay-log' ) )
	);
}

/** The last relay result of a host in words ("200 · 25 Oct 2026, 10:15 am"), or ''. */
function aw_relay_last_html( $host ) {
	$r = aw_src_relay_last( (array) get_option( 'aw_relay_log', array() ), (string) $host );
	return $r ? aw_relay_result_html( $r ) : '';
}

/** One relay log row's result in words, with a core dashicon. */
function aw_relay_result_html( array $r ) {
	$ok = is_int( $r['status'] ?? null ) && $r['status'] < 400;
	return '<span class="dashicons dashicons-' . ( $ok ? 'yes' : 'warning' ) . '" aria-hidden="true"></span> ' . esc_html( (string) $r['status'] . ' · ' . wp_date( 'j M Y, g:i a', (int) $r['at'] ) );
}

/**
 * Turn the relay switch on or off for hosts (Sources list and edit form). On for a host without a record adds one
 * with kind=relay (not a publisher); off removes such a record again. Each change is logged with old → new.
 *
 * @param array    $pubs  aw_publishers (read just before).
 * @param string[] $hosts Hosts.
 * @param bool     $on    On or off.
 * @return array The records.
 */
function aw_relay_switch( array $pubs, array $hosts, $on ) {
	$names = array();
	foreach ( (array) ( aw_sources_config()['sources'] ?? array() ) as $s ) {
		if ( is_array( $s ) ) {
			$names[ aw_src_source_host( $s ) ] = $names[ aw_src_source_host( $s ) ] ?? (string) ( $s['name'] ?? '' );
		}
	}
	$log = array();
	foreach ( $hosts as $h ) {
		$h = aw_sourcing_norm_host( $h );
		if ( '' === $h ) {
			continue;
		}
		$was = ! empty( $pubs[ $h ]['relay'] );
		if ( $on && ! $was ) {
			if ( isset( $pubs[ $h ] ) ) {
				$pubs[ $h ]['relay'] = true;
				$log[]               = array( 'relay', $h . ': fetch through this site', 'off', 'on' );
			} else {
				$pubs[ $h ] = array( 'status' => 'neutral', 'name' => (string) ( $names[ $h ] ?? '' ), 'count' => 0, 'first' => current_time( 'Y-m-d' ), 'last' => '', 'kind' => 'relay', 'relay' => true );
				$log[]      = array( 'relay', $h . ': fetch through this site (added as relay only, not a publisher)', '', $pubs[ $h ] );
			}
		} elseif ( ! $on && $was ) {
			if ( 'relay' === ( $pubs[ $h ]['kind'] ?? '' ) ) {
				$log[] = array( 'relay', $h . ': relay off (relay-only record removed)', $pubs[ $h ], '' );
				unset( $pubs[ $h ] );
			} else {
				unset( $pubs[ $h ]['relay'] );
				$log[] = array( 'relay', $h . ': fetch through this site', 'on', 'off' );
			}
		}
	}
	kits_sourcing_log_many( $log );
	if ( $log && function_exists( 'aw_sources_sync_soon' ) ) {
		aw_sources_sync_soon(); // the relay allow list and sources_v2 are copied into the workers' sources.yaml (aw_publishers is not hooked: ingest writes it all the time)
	}
	return $pubs;
}
