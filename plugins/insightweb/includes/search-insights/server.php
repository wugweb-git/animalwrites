<?php
/**
 * Server tab (owner, 3 Nov 2026): what the server itself sees, aggregate counts only, kept for good (nothing is deleted).
 *
 * - The host gives this account no access log (only PHP error logs), so bandwidth and requests the CDN answers are
 *   not available here; hPanel shows them. What is counted:
 *   - AI and search crawler requests per bot, by user agent, for requests that reach WordPress. Pages the CDN or the
 *     server's LiteSpeed page cache answer from a stored copy never reach WordPress, so these are lower bounds until
 *     those caches let crawlers through (docs/cdn.md, step C).
 *   - 4xx and 5xx answers WordPress gave to front-end requests (not wp-admin, cron or the REST API), with the top
 *     addresses (path only, no query string).
 *   - PHP errors per day by kind from this domain's PHP error logs (the same files as the daily error count,
 *     error-count.php), with the most frequent messages.
 * - Visitors by country and shortened IP address: visitors.php.
 * - No full IP address, full user agent or query string is stored; a crawler is kept only as its name.
 * - Counts are kept in one option per day (aw_si_srv_YYYYMMDD, site time zone, not autoloaded); a request is written
 *   only when it is a listed crawler or a 4xx/5xx answer. The daily run (aw_si_daily) adds yesterday's PHP errors.
 *   Every day is kept (owner, 3 Nov 2026: nothing is dropped).
 * - Speed and AI visibility are checked automatically every morning: selfcheck.php.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

/**
 * A PHP error message as HTML, with its file:line. A file of this site's own plugins or themes links to that line in
 * the public repository (the live branch); any other file is shown as text.
 *
 * @param string $msg Message (paths relative to the site root).
 */
function aw_si_srv_msg_html( string $msg ): string {
	if ( ! preg_match( '#(wp-content/(plugins|themes)/([^/\s]+)/\S+?\.php)(?: on line |:)(\d+)#', $msg, $m ) ) {
		return esc_html( $msg );
	}
	$own  = (array) apply_filters( 'aw_si_srv_repo_dirs', array( 'animal-writes-core', 'aw-seo-extras', 'aw-sourcing', 'kits-core', 'mailweb', 'animal-writes', 'kits-by-wugweb' ) );
	$file = $m[1] . ':' . $m[4];
	$link = in_array( $m[3], $own, true ) ? 'https://github.com/wugweb-git/animalwrites/blob/wordpress/' . substr( $m[1], strlen( 'wp-content/' ) ) . '#L' . $m[4] : '';
	return esc_html( $msg ) . ' (' . ( '' !== $link ? '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $file ) . '</a>' : esc_html( $file ) ) . ')';
}

/** The daily step: yesterday's PHP errors into its day. Every day is kept. */
function aw_si_srv_daily(): void {
	$ymd        = wp_date( 'Ymd', time() - DAY_IN_SECONDS );
	$key        = aw_si_srv_key( $ymd );
	$day        = get_option( $key, array() );
	$day        = is_array( $day ) ? $day : array();
	$day['php'] = aw_si_srv_php_errors( $ymd );
	update_option( $key, $day, false );
}
add_action( AW_SI_CRON_DAILY, 'aw_si_srv_daily', 20 );

/**
 * The stored days, newest first.
 *
 * @return array<string,array> Ymd => day.
 */
function aw_si_srv_days(): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC", $wpdb->esc_like( 'aw_si_srv_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a prefix scan of our own options
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$v   = maybe_unserialize( $r->option_value );
		$ymd = substr( (string) $r->option_name, strlen( 'aw_si_srv_' ) );
		if ( is_array( $v ) && preg_match( '/^\d{8}$/', $ymd ) ) { // day options only, not aw_si_srv_checks
			$out[ $ymd ] = $v;
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

/**
 * Crawler requests by week (Monday to Sunday), the last 12 weeks: all crawlers, the five busiest by name, the rest.
 *
 * @param array $days What aw_si_srv_days() returned.
 */
function aw_si_srv_weeks_table( array $days ): void {
	$monday = new DateTimeImmutable( 'monday this week', wp_timezone() );
	$weeks  = array();
	for ( $i = 0; $i < 12; $i++ ) {
		$weeks[ $monday->modify( '-' . ( 7 * $i ) . ' days' )->format( 'Ymd' ) ] = array();
	}
	$first = (string) array_key_last( $weeks );
	$tot   = array();
	foreach ( $days as $ymd => $d ) {
		if ( (string) $ymd < $first ) {
			continue;
		}
		$wk = ( new DateTimeImmutable( (string) $ymd, wp_timezone() ) )->modify( 'monday this week' )->format( 'Ymd' );
		foreach ( (array) ( $d['bots'] ?? array() ) as $b => $n ) {
			$weeks[ $wk ][ $b ] = (int) ( $weeks[ $wk ][ $b ] ?? 0 ) + (int) $n;
			$tot[ $b ]          = (int) ( $tot[ $b ] ?? 0 ) + (int) $n;
		}
	}
	if ( ! $tot ) {
		return;
	}
	arsort( $tot );
	$top = array_slice( array_keys( $tot ), 0, 5 );
	echo '<h2>' . esc_html__( 'Crawler requests by week (12 weeks)', 'aw-seo-extras' ) . '</h2>';
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Week from', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'All crawlers', 'aw-seo-extras' ) . '</th>';
	foreach ( $top as $b ) {
		echo '<th scope="col">' . esc_html( $b ) . '</th>';
	}
	echo '<th scope="col">' . esc_html__( 'Others', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $weeks as $wk => $by ) {
		$all  = array_sum( $by );
		$rest = $all;
		echo '<tr><td>' . esc_html( wp_date( 'j M', ( new DateTimeImmutable( (string) $wk, wp_timezone() ) )->getTimestamp() ) ) . '</td><td>' . esc_html( number_format_i18n( $all ) ) . '</td>';
		foreach ( $top as $b ) {
			$rest -= (int) ( $by[ $b ] ?? 0 );
			echo '<td>' . esc_html( number_format_i18n( (int) ( $by[ $b ] ?? 0 ) ) ) . '</td>';
		}
		echo '<td>' . esc_html( number_format_i18n( $rest ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Weeks before counting started (3 Nov 2026) show 0.', 'aw-seo-extras' ) . '</p>';
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
		echo '<p>' . esc_html__( 'What the server sees, per day, kept for good. Everything here is counted when a request reaches WordPress: a page the CDN or the server\'s page cache (LiteSpeed) answers from a stored copy is not counted, unless that cache lets the request through (docs/cdn.md, step C). Bandwidth is only in hPanel. No full IP address or user agent is stored.', 'aw-seo-extras' ) . '</p>';

		// Every crawler on the watch list, with 0 when it made no requests.
		$today   = wp_date( 'Ymd' );
		$periods = array(
			'today' => array( __( 'Today', 'aw-seo-extras' ), 1 ),
			'd7'    => array( __( 'Last 7 days', 'aw-seo-extras' ), 7 ),
			'd30'   => array( __( 'Last 30 days', 'aw-seo-extras' ), 30 ),
			'all'   => array( __( 'All days', 'aw-seo-extras' ), PHP_INT_MAX ),
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
		// 12 Nov 2026: Googlebot showed 2,099 requests in 30 days because cached hits never reach PHP. There is no access
		// log on this host to read instead (only PHP error logs), so the table says what it leaves out.
		echo '<p class="description">' . esc_html__( 'These are lower bounds. Most crawler hits are answered from a stored copy by the server\'s page cache (LiteSpeed) or the Hostinger CDN; those requests never reach PHP, so they are not counted here. hPanel shows the full traffic. Letting crawlers past the caches is step C of docs/cdn.md:', 'aw-seo-extras' ) . ' <a href="' . esc_url( 'https://github.com/wugweb-git/animalwrites/blob/wordpress/docs/cdn.md#c-let-ai-agents-and-crawlers-past-the-caches-3-nov-2026' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'docs/cdn.md, C. Let AI agents and crawlers past the caches', 'aw-seo-extras' ) . '</a>.</p>';
		aw_si_srv_weeks_table( $days );

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
