<?php
/**
 * Relay for the workers (owner, 20 Oct 2026). Some publishers refuse GitHub's servers (PFA's Substack, Zoom TV: 403)
 * but answer this server. The workers ask GET aw/v1/relay?url=... and get the page or feed as this server sees it.
 *
 * Limits: the workers' login only (an application password), hosts on the allow list in Settings → Sourcing → Relay,
 * http/https, GET only. The host is resolved first and refused when any address is private, loopback, reserved or this
 * site's own; the checked address is the one connected to, and every redirect (at most 3) is checked the same way.
 * At most 5 MB, 20 seconds, 30 requests per host per 10 minutes. Every request is logged (last 200, on the Relay tab).
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_RELAY_MAX_BYTES = 5 * MB_IN_BYTES;
const AW_RELAY_TIMEOUT   = 20;
const AW_RELAY_RATE      = 30; // requests per host per 10 minutes
const AW_RELAY_DEFAULT   = array( 'substack.com', 'zoomtventertainment.com', 'awbi.gov.in', 'ccsea.gov.in', 'egazette.gov.in' );

/** Allowed hosts (a host or any of its subdomains). */
function aw_relay_hosts() {
	$c = function_exists( 'aw_sources_config' ) ? aw_sources_config() : array();
	return isset( $c['relay_hosts'] ) ? array_values( (array) $c['relay_hosts'] ) : AW_RELAY_DEFAULT;
}

/** True when the host is on the allow list. */
function aw_relay_host_allowed( $host ) {
	$host = strtolower( (string) $host );
	foreach ( aw_relay_hosts() as $h ) {
		if ( $host === $h || str_ends_with( $host, '.' . $h ) ) {
			return true;
		}
	}
	return false;
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

// Settings → Sourcing → Relay: the allow list and the log.
add_filter(
	'kits_sourcing_tabs',
	function ( $tabs ) {
		$tabs['relay'] = array( 'Relay', 'aw_relay_tab' );
		return $tabs;
	}
);

/** Relay tab. */
function aw_relay_tab() {
	echo '<p>Publishers that refuse GitHub\'s servers but answer this site. When one answers the workers with 403, they ask this site to fetch the feed or page for them. One host per line; its subdomains are included.</p>';
	echo '<textarea name="relay_hosts" class="large-text code" rows="6">' . esc_textarea( implode( "\n", aw_relay_hosts() ) ) . '</textarea>';
	submit_button( 'Save' );
	$log = array_reverse( (array) get_option( 'aw_relay_log', array() ) );
	echo '<h3>Requests (last 200)</h3>';
	if ( ! $log ) {
		echo '<p>None yet.</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Host</th><th>Path</th><th>Result</th><th>Size</th><th>Time taken</th><th>Note</th></tr></thead><tbody>';
	foreach ( array_slice( $log, 0, 200 ) as $r ) {
		echo '<tr><td>' . esc_html( wp_date( 'j M Y, g:i a', (int) $r['at'] ) ) . '</td><td>' . esc_html( $r['host'] ) . '</td><td>' . esc_html( $r['path'] ) . '</td><td>' . esc_html( (string) $r['status'] ) . '</td><td>' . esc_html( size_format( (int) $r['bytes'] ) ) . '</td><td>' . (int) $r['ms'] . ' ms</td><td>' . esc_html( $r['note'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'kits_sourcing_save_relay',
	function () {
		$c   = aw_sources_config();
		$old = aw_relay_hosts();
		$new = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( sanitize_textarea_field( wp_unslash( $_POST['relay_hosts'] ?? '' ) ) ) ) as $h ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked by the Kits save handler
			$h = preg_replace( '#^https?://#', '', trim( $h ) );
			$h = trim( explode( '/', $h )[0], '.' );
			if ( preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $h ) ) {
				$new[] = $h;
			}
		}
		$c['relay_hosts'] = array_values( array_unique( $new ) );
		update_option( 'aw_sources', $c, false );
		kits_sourcing_log( 'relay', kits_sourcing_diff( 'relay hosts', $old, $c['relay_hosts'] ) );
	}
);
