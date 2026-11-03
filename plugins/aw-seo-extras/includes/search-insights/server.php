<?php
/**
 * Server tab (owner, 3 Nov 2026): what the server itself sees, kept 90 days, aggregate counts only.
 *
 * - The host gives this account no access log (only PHP error logs), so bandwidth and requests the CDN answers are
 *   not available here; hPanel shows them. What is counted:
 *   - AI and search crawler requests per bot, by user agent, for requests that reach WordPress. Pages the CDN serves
 *     from its cache never reach WordPress, so these are lower bounds.
 *   - 4xx and 5xx answers WordPress gave to front-end requests (not wp-admin, cron or the REST API), with the top
 *     addresses (path only, no query string).
 *   - PHP errors per day by kind from this domain's PHP error logs (the same files as the daily error count,
 *     error-count.php), with the most frequent messages.
 * - Visitors by country and shortened IP address: visitors.php.
 * - No full IP address, full user agent or query string is stored; a crawler is kept only as its name.
 * - Counts are kept in one option per day (aw_si_srv_YYYYMMDD, site time zone, not autoloaded); a request is written
 *   only when it is a listed crawler or a 4xx/5xx answer. The daily run (aw_si_daily) adds yesterday's PHP errors and
 *   deletes days older than 90.
 * - Speed and AI visibility are checked automatically every morning: selfcheck.php.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_SRV_DAYS = 90;
const AW_SI_SRV_URLS = 100; // distinct addresses kept per day and kind; more are counted as "(other)".

/**
 * The crawler watch list (owner, 3 Nov 2026), as name => user-agent token, matched case-insensitively; the first match
 * wins, so the more specific tokens come first. Every name always has a row on the tab, with 0 when it made no
 * requests. An empty token is a robots.txt name only (Google-Extended, Applebot-Extended: Google and Apple crawl with
 * Googlebot and Applebot), which never visits and so is never counted.
 *
 * @return array<string,string>
 */
function aw_si_srv_bots(): array {
	return (array) apply_filters(
		'aw_si_srv_bots',
		array(
			'OAI-SearchBot'         => 'OAI-SearchBot',
			'ChatGPT-User'          => 'ChatGPT-User',
			'GPTBot'                => 'GPTBot',
			'Claude-User'           => 'Claude-User',
			'Claude-SearchBot'      => 'Claude-SearchBot',
			'ClaudeBot'             => 'ClaudeBot',
			'Perplexity-User'       => 'Perplexity-User',
			'PerplexityBot'         => 'PerplexityBot',
			'MistralAI-User'        => 'MistralAI-User',
			'cohere-ai'             => 'cohere-ai',
			'YouBot'                => 'YouBot',
			'Google-InspectionTool' => 'Google-InspectionTool',
			'GoogleOther'           => 'GoogleOther',
			'Googlebot'             => 'Googlebot',
			'Google-Extended'       => '',
			'Bingbot'               => 'bingbot',
			'Applebot'              => 'Applebot',
			'Applebot-Extended'     => '',
			'Amazonbot'             => 'Amazonbot',
			'DuckDuckBot'           => 'DuckDuckBot',
			'YandexBot'             => 'YandexBot',
			'Baiduspider'           => 'Baiduspider',
			'meta-externalagent'    => 'meta-externalagent',
			'meta-webindexer'       => 'meta-webindexer',
			'Bytespider'            => 'Bytespider',
			'CCBot'                 => 'CCBot',
			'PetalBot'              => 'PetalBot',
			'Diffbot'               => 'Diffbot',
		)
	);
}

/**
 * The crawler a user agent belongs to, or ''.
 *
 * @param string $ua User agent.
 */
function aw_si_srv_bot( string $ua ): string {
	if ( '' === $ua ) {
		return '';
	}
	foreach ( aw_si_srv_bots() as $name => $token ) {
		if ( '' !== (string) $token && false !== stripos( $ua, (string) $token ) ) {
			return (string) $name;
		}
	}
	return '';
}

/**
 * Option name for a day.
 *
 * @param string $ymd Day as Ymd (site time zone).
 */
function aw_si_srv_key( string $ymd ): string {
	return 'aw_si_srv_' . preg_replace( '/\D/', '', $ymd );
}

/**
 * Add one to a counted address, keeping at most AW_SI_SRV_URLS per kind.
 *
 * @param array  $urls Address => count (changed in place).
 * @param string $path Path.
 */
function aw_si_srv_tally( array &$urls, string $path ): void {
	if ( ! isset( $urls[ $path ] ) && count( $urls ) >= AW_SI_SRV_URLS ) {
		$path = '(other)';
	}
	$urls[ $path ] = (int) ( $urls[ $path ] ?? 0 ) + 1;
}

/** At the end of a front-end request: count a listed crawler, and a 4xx or 5xx answer. */
function aw_si_srv_count(): void {
	if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$bot  = aw_si_srv_bot( $ua );
	$code = (int) http_response_code();
	$rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$bad  = ! $rest && $code >= 400 && $code < 600;
	if ( '' === $bot && ! $bad ) {
		return; // most requests: nothing written.
	}
	$key = aw_si_srv_key( wp_date( 'Ymd' ) );
	$day = get_option( $key, array() );
	$day = is_array( $day ) ? $day : array();
	if ( '' !== $bot ) {
		$day['bots'][ $bot ] = (int) ( $day['bots'][ $bot ] ?? 0 ) + 1;
	}
	if ( $bad ) {
		$kind                  = $code >= 500 ? '5xx' : '4xx';
		$day['codes'][ $kind ] = (int) ( $day['codes'][ $kind ] ?? 0 ) + 1;
		$uri                   = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path                  = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path                  = '' !== $path ? mb_substr( $path, 0, 200 ) : '/';
		$day['urls'][ $kind ]  = (array) ( $day['urls'][ $kind ] ?? array() );
		aw_si_srv_tally( $day['urls'][ $kind ], ( '4xx' === $kind && 404 !== $code ? $code . ' ' : '' ) . $path );
	}
	update_option( $key, $day, false );
}
add_action( 'shutdown', 'aw_si_srv_count', 0 );

/**
 * PHP errors on one day (site time zone) by kind, with the most frequent messages, from this domain's PHP error logs.
 *
 * @param string $ymd Day as Ymd.
 * @return array{status:string,kinds:array<string,int>,top:array<string,int>}
 */
function aw_si_srv_php_errors( string $ymd ): array {
	$out = array(
		'status' => 'not_found',
		'kinds'  => array(),
		'top'    => array(),
	);
	if ( ! function_exists( 'aw_error_count_resolve' ) ) {
		return $out;
	}
	$tz    = wp_timezone();
	$start = ( new DateTimeImmutable( $ymd . ' 00:00:00', $tz ) )->getTimestamp();
	$end   = $start + DAY_IN_SECONDS;
	$res   = aw_error_count_resolve( aw_error_count_php_candidates() );
	if ( ! $res['files'] ) {
		$out['status'] = $res['unreadable'] ? 'unreadable' : 'not_found';
		return $out;
	}
	$kinds = array();
	$top   = array();
	foreach ( $res['files'] as $f ) {
		if ( (int) filemtime( $f ) < $start ) {
			continue;
		}
		aw_error_count_each_line(
			$f,
			function ( $line ) use ( $start, $end, &$kinds, &$top ) {
				if ( '[' !== substr( $line, 0, 1 ) || ! preg_match( '/^\[([^\]]+)\]\s*(.*)$/', $line, $m ) ) {
					return;
				}
				$t = (int) strtotime( $m[1] );
				if ( $t < $start || $t >= $end ) {
					return;
				}
				$msg = $m[2];
				if ( preg_match( '/^PHP (Fatal error|Parse error|Warning|Notice|Deprecated):\s*(.*)$/', $msg, $k ) ) {
					$kind = $k[1];
					$msg  = $k[2];
				} elseif ( 0 === strpos( $msg, 'WordPress database error' ) ) {
					$kind = 'Database error';
					$msg  = preg_replace( '/^WordPress database error\s*| made by .*$/', '', $msg ); // drop the label and the call stack.
				} else {
					$kind = 'Other';
				}
				$kinds[ $kind ] = (int) ( $kinds[ $kind ] ?? 0 ) + 1;
				aw_error_count_tally( $top, aw_error_count_clean_message( $kind . ': ' . $msg ) );
			}
		);
	}
	$out['status'] = 'ok';
	$out['kinds']  = $kinds;
	$out['top']    = aw_error_count_top( $top );
	return $out;
}

/** The daily step: yesterday's PHP errors into its day, then days older than 90 deleted. */
function aw_si_srv_daily(): void {
	$ymd        = wp_date( 'Ymd', time() - DAY_IN_SECONDS );
	$key        = aw_si_srv_key( $ymd );
	$day        = get_option( $key, array() );
	$day        = is_array( $day ) ? $day : array();
	$day['php'] = aw_si_srv_php_errors( $ymd );
	update_option( $key, $day, false );
	global $wpdb;
	$cut = aw_si_srv_key( wp_date( 'Ymd', time() - AW_SI_SRV_DAYS * DAY_IN_SECONDS ) );
	$old = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s", $wpdb->esc_like( 'aw_si_srv_' ) . '%', $cut ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a prefix scan of our own options
	foreach ( $old as $name ) {
		delete_option( $name );
	}
}
add_action( AW_SI_CRON_DAILY, 'aw_si_srv_daily', 20 );

/**
 * The stored days, newest first.
 *
 * @return array<string,array> Ymd => day.
 */
function aw_si_srv_days(): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC LIMIT %d", $wpdb->esc_like( 'aw_si_srv_' ) . '%', AW_SI_SRV_DAYS + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a prefix scan of our own options
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$v = maybe_unserialize( $r->option_value );
		if ( is_array( $v ) ) {
			$out[ substr( (string) $r->option_name, strlen( 'aw_si_srv_' ) ) ] = $v;
		}
	}
	return $out;
}

/**
 * Text for a count map: "a 3, b 2", the largest first.
 *
 * @param array $map   Name => count.
 * @param int   $limit How many.
 */
function aw_si_srv_list( array $map, int $limit = 5 ): string {
	arsort( $map );
	$parts = array();
	foreach ( array_slice( $map, 0, $limit, true ) as $k => $n ) {
		$parts[] = $k . ' ' . number_format_i18n( (int) $n );
	}
	return implode( ', ', $parts );
}

/* ---------- The tab ---------- */

add_filter(
	'aw_si_tabs',
	function ( $tabs ) {
		$tabs           = (array) $tabs;
		$tabs['server'] = __( 'Server', 'aw-seo-extras' );
		return $tabs;
	},
	30
);

add_action(
	'aw_si_load_tab_server',
	function () {
		require_once __DIR__ . '/class-aw-si-server-table.php';
	}
);

add_action(
	'aw_si_tab_server',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! class_exists( 'AW_SI_Server_Table' ) ) {
			return;
		}
		$days = aw_si_srv_days();
		echo '<p>' . esc_html__( 'What the server sees, per day. The host gives no access log, so bandwidth and the pages the CDN serves from its cache are only in hPanel. Everything here is counted when a request reaches WordPress. No full IP address or user agent is stored.', 'aw-seo-extras' ) . '</p>';

		// Every crawler on the watch list, with 0 when it made no requests.
		$today   = wp_date( 'Ymd' );
		$periods = array(
			'today' => array( __( 'Today', 'aw-seo-extras' ), 1 ),
			'd7'    => array( __( 'Last 7 days', 'aw-seo-extras' ), 7 ),
			'd30'   => array( __( 'Last 30 days', 'aw-seo-extras' ), 30 ),
			'all'   => array( __( 'All stored days', 'aw-seo-extras' ), AW_SI_SRV_DAYS ),
		);
		$sums    = array();
		foreach ( $days as $ymd => $d ) {
			$age = (int) round( ( strtotime( $today ) - strtotime( (string) $ymd ) ) / DAY_IN_SECONDS );
			foreach ( (array) ( $d['bots'] ?? array() ) as $b => $n ) {
				foreach ( $periods as $pk => $per ) {
					if ( $age < $per[1] ) {
						$sums[ $b ][ $pk ] = (int) ( $sums[ $b ][ $pk ] ?? 0 ) + (int) $n;
					}
				}
			}
		}
		echo '<h2>' . esc_html__( 'Crawler requests', 'aw-seo-extras' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Crawler', 'aw-seo-extras' ) . '</th>';
		foreach ( $periods as $per ) {
			echo '<th scope="col">' . esc_html( $per[0] ) . '</th>';
		}
		echo '<th scope="col">' . esc_html__( 'Note', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
		$names = array_keys( aw_si_srv_bots() );
		foreach ( array_keys( $sums ) as $b ) {
			if ( ! in_array( $b, $names, true ) ) {
				$names[] = $b; // a crawler counted under a name since taken off the list.
			}
		}
		foreach ( $names as $b ) {
			$only = '' === (string) ( aw_si_srv_bots()[ $b ] ?? 'x' );
			echo '<tr><td>' . esc_html( $b ) . '</td>';
			foreach ( array_keys( $periods ) as $pk ) {
				echo '<td>' . esc_html( number_format_i18n( (int) ( $sums[ $b ][ $pk ] ?? 0 ) ) ) . '</td>';
			}
			echo '<td>' . ( $only ? esc_html__( 'robots.txt name only, never visits', 'aw-seo-extras' ) : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( function_exists( 'aw_si_vis_section' ) ) {
			aw_si_vis_section();
		}

		echo '<h2>' . esc_html__( 'Per day', 'aw-seo-extras' ) . '</h2>';
		$table = new AW_SI_Server_Table( $days );
		$table->prepare_items();
		$table->display();

		if ( function_exists( 'aw_si_chk_section' ) ) {
			aw_si_chk_section();
		}
	}
);
