<?php
/**
 * Crawler access (owner, 31 Oct 2026; Search Insights step 3). On 26 Oct an SEO tool's crawler got no answer on 17 of 20
 * pages, while the same pages answered 200 from the owner's Mac and from the server. Two weekly checks:
 * - inside: this server asks the home page and five sample pages (a Directory listing, a News item, a Library item, a
 *   Resources guide, an Opportunity) with each search and AI crawler's user agent (Monday 08:45 IST). A request from the
 *   server to itself does not show blocks that depend on the caller's address, so it only catches user-agent rules;
 * - outside: the workers job crawl-check.yml asks the same pages from a GitHub runner (an outside data-centre address),
 *   plus a 20-page burst, and posts here (aw/v1/search-insights/crawl, the ingest user's application password).
 * A page that is not a 200, or a 200 that is not one of our pages (a challenge page), is a problem: Site Health shows
 * it and the owner gets one email per new set of problems. Results are kept 12 weeks.
 *
 * obs rows: src crawl, period day, metric inside / outside, txt = agent, value = problems in that run.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_CRAWL_CRON  = 'aw_si_crawl';
const AW_SI_CRAWL_IN    = 'aw_si_crawl_inside'; // Option: the last inside run.
const AW_SI_CRAWL_OUT   = 'aw_si_crawl_outside'; // Option: the last outside run.
const AW_SI_CRAWL_WEEKS = 12;
const AW_SI_CRAWL_TIME  = 240; // Seconds the inside run may take before it stops.

/** User agents: the same as the workers job (aw_workers/crawl_check.py AGENTS). */
function aw_si_crawl_agents(): array {
	return (array) apply_filters(
		'aw_si_crawl_agents',
		array(
			'Browser'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
			'Googlebot'       => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
			'Bingbot'         => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
			'GPTBot'          => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
			'OAI-SearchBot'   => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot',
			'ChatGPT-User'    => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot',
			'PerplexityBot'   => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
			'ClaudeBot'       => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
			'Google-Extended' => 'Mozilla/5.0 (compatible; Google-Extended)',
			'Applebot'        => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)',
		)
	);
}

/**
 * The sample pages (newest published of each kind) and up to 20 pages for the outside burst.
 *
 * @return array{pages: array<int, array{kind: string, url: string}>, burst: string[]}
 */
function aw_si_crawl_pages(): array {
	$pages = array(
		array(
			'kind' => 'home',
			'url'  => home_url( '/' ),
		),
	);
	$extra = array();
	$kinds = array(
		'listing'     => 'aw_listing',
		'news'        => 'aw_news',
		'library'     => 'aw_legal',
		'resource'    => 'post',
		'opportunity' => 'aw_opportunity',
	);
	foreach ( $kinds as $kind => $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}
		$urls = array();
		foreach ( get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 'resource' === $kind ? 30 : 4,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		) as $id ) {
			$url = (string) get_permalink( $id );
			if ( 'resource' === $kind && false === strpos( $url, '/resources/' ) ) {
				continue; // Resources guides are posts under /resources/.
			}
			$urls[] = $url;
		}
		if ( $urls ) {
			$pages[] = array(
				'kind' => $kind,
				'url'  => array_shift( $urls ),
			);
			$extra   = array_merge( $extra, array_slice( $urls, 0, 3 ) );
		}
	}
	foreach ( array( '/about/', '/contact/', '/directory/', '/library/', '/news/', '/campaigns/' ) as $path ) {
		$extra[] = home_url( $path );
	}
	$burst = array_values( array_unique( array_merge( wp_list_pluck( $pages, 'url' ), $extra ) ) );
	return array(
		'pages' => $pages,
		'burst' => array_slice( $burst, 0, 20 ),
	);
}

/**
 * The verdict on one answer: ok, refused (not a 200) or challenge (a 200 that is not one of our pages).
 *
 * @param int    $code HTTP status (0: no answer).
 * @param string $body Response body.
 */
function aw_si_crawl_verdict( int $code, string $body ): string {
	if ( 200 !== $code ) {
		return 'refused';
	}
	return ( false === strpos( $body, 'rel="canonical"' ) && false === strpos( $body, "rel='canonical'" ) ) ? 'challenge' : 'ok';
}

/**
 * The inside run: every agent on every sample page, from this server. Stored unless $dry.
 *
 * @param bool $dry Check only.
 * @return array{at: int, runner: string, checks: array, cut: bool}
 */
function aw_si_crawl_run( bool $dry = false ): array {
	if ( ! $dry ) {
		aw_si_run_start( 'crawl-in' );
	}
	$plan   = aw_si_crawl_pages();
	$start  = time();
	$checks = array();
	$cut    = false;
	foreach ( aw_si_crawl_agents() as $agent => $ua ) {
		foreach ( $plan['pages'] as $p ) {
			if ( time() - $start > AW_SI_CRAWL_TIME ) {
				$cut = true;
				break 2;
			}
			$t    = microtime( true );
			$resp = wp_remote_get(
				$p['url'],
				array(
					'user-agent'  => $ua,
					'timeout'     => 20,
					'redirection' => 3,
					'headers'     => array( 'Cache-Control' => 'no-cache' ),
				)
			);
			$code     = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );
			$checks[] = array(
				'agent'  => $agent,
				'kind'   => $p['kind'],
				'url'    => $p['url'],
				'code'   => $code,
				'ms'     => (int) round( ( microtime( true ) - $t ) * 1000 ),
				'result' => is_wp_error( $resp ) ? 'refused' : aw_si_crawl_verdict( $code, (string) wp_remote_retrieve_body( $resp ) ),
				'error'  => is_wp_error( $resp ) ? $resp->get_error_message() : '',
			);
		}
	}
	$run = array(
		'at'     => time(),
		'runner' => 'server',
		'checks' => $checks,
		'burst'  => array(),
		'cut'    => $cut,
	);
	if ( ! $dry ) {
		aw_si_crawl_store( 'inside', $run );
		if ( $cut ) {
			/* translators: %d: seconds. */
			aw_si_run_fail( 'crawl-in', sprintf( __( 'The check stopped after %d seconds; some agents were not asked.', 'aw-seo-extras' ), AW_SI_CRAWL_TIME ) );
		}
	}
	return $run;
}

/**
 * Problems in one run, in plain words.
 *
 * @param array $run A stored run.
 * @return string[]
 */
function aw_si_crawl_problems( array $run ): array {
	$out = array();
	foreach ( (array) ( $run['checks'] ?? array() ) as $c ) {
		if ( 'ok' !== ( $c['result'] ?? '' ) ) {
			$what  = 'challenge' === $c['result'] ? __( 'challenge page', 'aw-seo-extras' ) : ( $c['code'] ? 'HTTP ' . $c['code'] : __( 'no answer', 'aw-seo-extras' ) );
			$out[] = sprintf( '%s, %s: %s', $c['agent'], $c['kind'], $what );
		}
	}
	foreach ( (array) ( $run['burst'] ?? array() ) as $b ) {
		if ( (int) ( $b['ok'] ?? 0 ) < (int) ( $b['n'] ?? 0 ) ) {
			/* translators: 1: agent, 2: pages that answered, 3: pages asked. */
			$out[] = sprintf( __( 'Burst as %1$s: %2$d of %3$d pages answered', 'aw-seo-extras' ), $b['agent'], (int) $b['ok'], (int) $b['n'] );
		}
	}
	return $out;
}

/**
 * Store a run: the latest in its option, problems per agent in obs (kept 12 weeks), the run log, and the alert.
 *
 * @param string $side inside or outside.
 * @param array  $run  The run.
 */
function aw_si_crawl_store( string $side, array $run ): void {
	global $wpdb;
	update_option( 'inside' === $side ? AW_SI_CRAWL_IN : AW_SI_CRAWL_OUT, $run, false );
	$day = wp_date( 'Y-m-d', (int) $run['at'] );
	$per = array();
	foreach ( (array) $run['checks'] as $c ) {
		$per[ $c['agent'] ] = ( $per[ $c['agent'] ] ?? 0 ) + ( 'ok' === $c['result'] ? 0 : 1 );
	}
	$o = aw_si_table( 'obs' );
	foreach ( $per as $agent => $n ) {
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$o,
			array(
				'kw_id'    => 0,
				'page'     => '',
				'page_key' => md5( 'agent:' . $agent ), // One row per agent and day (aw_si_obs_put keys rows by page).
				'src'      => 'crawl',
				'metric'   => $side,
				'period'   => 'day',
				'd'        => $day,
				'value'    => (float) $n,
				'txt'      => mb_substr( (string) $agent, 0, 255 ),
				'fetched'  => aw_si_now(),
			)
		);
	}
	$keep = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . AW_SI_CRAWL_WEEKS . ' weeks' )->format( 'Y-m-d' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$o} WHERE src = 'crawl' AND d < %s", $keep ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$problems = aw_si_crawl_problems( $run );
	aw_si_run_ok(
		'inside' === $side ? 'crawl-in' : 'crawl-out',
		array(
			'checks'   => count( (array) $run['checks'] ),
			'problems' => count( $problems ),
		)
	);
	aw_si_crawl_alert();
}

/** Email the owner once per new set of problems (inside and outside together). */
function aw_si_crawl_alert(): void {
	$all = array();
	foreach ( array(
		__( 'From the server', 'aw-seo-extras' )  => AW_SI_CRAWL_IN,
		__( 'From outside', 'aw-seo-extras' )     => AW_SI_CRAWL_OUT,
	) as $label => $opt ) {
		foreach ( aw_si_crawl_problems( (array) get_option( $opt, array() ) ) as $p ) {
			$all[] = $label . ': ' . $p;
		}
	}
	if ( ! $all ) {
		delete_option( 'aw_si_crawl_mailed' );
		return;
	}
	$key = md5( implode( "\n", $all ) );
	if ( get_option( 'aw_si_crawl_mailed' ) === $key ) {
		return; // The same problems were already emailed.
	}
	$body = "Some search or AI crawlers could not read animalwrites.in pages:\n\n- " . implode( "\n- ", $all ) . "\n\nSite Health: " . admin_url( 'site-health.php' ) . "\nSearch Insights: " . aw_si_url() . "\n";
	if ( wp_mail( get_option( 'admin_email' ), 'Animal Writes: crawler access problem — ' . $all[0], $body ) ) {
		update_option( 'aw_si_crawl_mailed', $key, false );
	}
}

/**
 * Take the outside results from the workers job.
 *
 * @param array $body JSON body: runner, started, checks [{agent, kind, url, code, ms, bytes, result, error}], burst.
 * @return array|WP_Error What was stored.
 */
function aw_si_crawl_receive( array $body ) {
	$checks = array();
	foreach ( array_slice( (array) ( $body['checks'] ?? array() ), 0, 500 ) as $c ) {
		if ( ! is_array( $c ) || empty( $c['agent'] ) ) {
			continue;
		}
		$checks[] = array(
			'agent'  => sanitize_text_field( (string) $c['agent'] ),
			'kind'   => sanitize_key( (string) ( $c['kind'] ?? '' ) ),
			'url'    => esc_url_raw( (string) ( $c['url'] ?? '' ) ),
			'code'   => (int) ( $c['code'] ?? 0 ),
			'ms'     => (int) ( $c['ms'] ?? 0 ),
			'result' => in_array( $c['result'] ?? '', array( 'ok', 'refused', 'challenge' ), true ) ? $c['result'] : 'refused',
			'error'  => sanitize_text_field( (string) ( $c['error'] ?? '' ) ),
		);
	}
	if ( ! $checks ) {
		return new WP_Error( 'aw_si_crawl', __( 'No checks in the request.', 'aw-seo-extras' ), array( 'status' => 400 ) );
	}
	$burst = array();
	foreach ( array_slice( (array) ( $body['burst'] ?? array() ), 0, 20 ) as $b ) {
		if ( is_array( $b ) && ! empty( $b['agent'] ) ) {
			$burst[] = array(
				'agent'  => sanitize_text_field( (string) $b['agent'] ),
				'n'      => (int) ( $b['n'] ?? 0 ),
				'ok'     => (int) ( $b['ok'] ?? 0 ),
				'codes'  => array_map( 'intval', array_filter( (array) ( $b['codes'] ?? array() ), 'is_scalar' ) ),
				'max_ms' => (int) ( $b['max_ms'] ?? 0 ),
			);
		}
	}
	$run = array(
		'at'     => time(),
		'runner' => sanitize_key( (string) ( $body['runner'] ?? 'github' ) ),
		'checks' => $checks,
		'burst'  => $burst,
		'cut'    => false,
	);
	aw_si_crawl_store( 'outside', $run );
	return array(
		'checks'   => count( $checks ),
		'problems' => count( aw_si_crawl_problems( $run ) ),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'aw/v1',
			'/search-insights/crawl',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'aw_seo_api_allowed',
					'callback'            => fn() => rest_ensure_response( aw_si_crawl_pages() + array( 'agents' => aw_si_crawl_agents() ) ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'aw_seo_api_allowed',
					'callback'            => function ( WP_REST_Request $req ) {
						$body = $req->get_json_params();
						$out  = aw_si_crawl_receive( is_array( $body ) ? $body : array() );
						return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
					},
				),
			)
		);
	}
);

add_action( AW_SI_CRAWL_CRON, fn() => get_option( 'aw_si_off' ) ? null : aw_si_crawl_run() );

add_action(
	'init',
	function () {
		if ( ! get_option( 'aw_si_off' ) && ! wp_next_scheduled( AW_SI_CRAWL_CRON ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'next monday 08:45', wp_timezone() ) )->getTimestamp(), 'weekly', AW_SI_CRAWL_CRON );
		}
	}
);

add_filter(
	'aw_si_cron_hooks',
	function ( $hooks ) {
		$hooks[] = AW_SI_CRAWL_CRON;
		return $hooks;
	}
);

add_filter(
	'aw_si_sources',
	function ( $s ) {
		$s['crawl-in'] = array(
			'label' => __( 'Crawler access, from the server', 'aw-seo-extras' ),
			'every' => WEEK_IN_SECONDS,
		);
		if ( get_option( AW_SI_CRAWL_OUT ) ) { // Listed once the workers job has reported, so it never shows as late before.
			$s['crawl-out'] = array(
				'label' => __( 'Crawler access, from outside (workers)', 'aw-seo-extras' ),
				'every' => WEEK_IN_SECONDS,
			);
		}
		return $s;
	}
);

/** Site Health test: the last inside and outside runs. */
function aw_si_crawl_health_test(): array {
	$items = '';
	$any   = false;
	$bad   = 0;
	foreach ( array(
		__( 'From the server (user-agent rules only)', 'aw-seo-extras' ) => AW_SI_CRAWL_IN,
		__( 'From outside (GitHub runner, weekly)', 'aw-seo-extras' )    => AW_SI_CRAWL_OUT,
	) as $label => $opt ) {
		$run = (array) get_option( $opt, array() );
		if ( empty( $run['at'] ) ) {
			$items .= '<li>' . esc_html( $label . ': ' . __( 'not run yet.', 'aw-seo-extras' ) ) . '</li>';
			continue;
		}
		$any      = true;
		$problems = aw_si_crawl_problems( $run );
		$bad     += count( $problems );
		/* translators: 1: date, 2: checks, 3: problems. */
		$line   = sprintf( __( '%1$s: %2$d checks, %3$d problems', 'aw-seo-extras' ), wp_date( 'j M, H:i', (int) $run['at'] ), count( (array) $run['checks'] ), count( $problems ) );
		$items .= '<li>' . esc_html( $label . ' — ' . $line ) . ( $problems ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', array_slice( $problems, 0, 20 ) ) ) . '</li></ul>' : '' ) . '</li>';
	}
	$status = $bad ? 'critical' : ( $any ? 'good' : 'recommended' );
	return array(
		'label'       => $bad ? __( 'Some search or AI crawlers cannot read the site', 'aw-seo-extras' ) : ( $any ? __( 'Search and AI crawlers can read the site', 'aw-seo-extras' ) : __( 'Crawler access has not been checked yet', 'aw-seo-extras' ) ),
		'status'      => $status,
		'badge'       => array(
			'label' => __( 'Search Insights', 'aw-seo-extras' ),
			'color' => $bad ? 'red' : 'blue',
		),
		'description' => '<p>' . esc_html__( 'Each week the home page and five sample pages are asked with the user agents of a browser, Googlebot, Bingbot, GPTBot, OAI-SearchBot, ChatGPT-User, PerplexityBot, ClaudeBot, Google-Extended and Applebot: from this server, and from outside by the workers. A page that is not a 200, or a challenge page, is a problem.', 'aw-seo-extras' ) . '</p><ul>' . $items . '</ul>',
		'actions'     => '<p><a href="' . esc_url( aw_si_url() ) . '">' . esc_html__( 'Open Search Insights', 'aw-seo-extras' ) . '</a></p>',
		'test'        => 'aw_search_insights_crawl',
	);
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['aw_search_insights_crawl'] = array(
			'label' => __( 'Search and AI crawlers can read the site', 'aw-seo-extras' ),
			'test'  => 'aw_si_crawl_health_test',
		);
		return $tests;
	}
);

/** Overview box: the last inside and outside runs, problems first. */
function aw_si_box_crawl(): void {
	foreach ( array(
		__( 'From the server', 'aw-seo-extras' )         => AW_SI_CRAWL_IN,
		__( 'From outside (workers)', 'aw-seo-extras' ) => AW_SI_CRAWL_OUT,
	) as $label => $opt ) {
		$run = (array) get_option( $opt, array() );
		if ( empty( $run['at'] ) ) {
			echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html__( 'not run yet (weekly, Monday morning).', 'aw-seo-extras' ) . '</p>';
			continue;
		}
		$problems = aw_si_crawl_problems( $run );
		/* translators: 1: date, 2: checks. */
		$line = sprintf( __( '%1$s, %2$d checks', 'aw-seo-extras' ), wp_date( 'j M, H:i', (int) $run['at'] ), count( (array) $run['checks'] ) );
		echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $line ) . ' — ' . ( $problems ? '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( implode( '; ', array_slice( $problems, 0, 8 ) ) ) : '<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html__( 'every agent got every page.', 'aw-seo-extras' ) ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'The server check only shows user-agent rules: blocks that depend on the caller\'s address show only in the outside check.', 'aw-seo-extras' ) . '</p>';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Crawler access from this server: every agent on every sample page.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Check and print only; store nothing, send no email.
	 *
	 * ## EXAMPLES
	 *
	 *     wp aw-si-crawl --dry-run
	 */
	WP_CLI::add_command(
		'aw-si-crawl',
		function ( $args, $assoc ) {
			$run = aw_si_crawl_run( isset( $assoc['dry-run'] ) );
			WP_CLI\Utils\format_items( 'table', $run['checks'], array( 'agent', 'kind', 'code', 'ms', 'result' ) );
			$problems = aw_si_crawl_problems( $run );
			WP_CLI::log( $problems ? 'Problems: ' . implode( '; ', $problems ) : 'Every agent got every page.' );
		}
	);
}
