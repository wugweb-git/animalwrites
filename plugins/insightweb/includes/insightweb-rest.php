<?php
/**
 * InsightWeb's routes for its new screens, under insightweb/v1:
 *
 *   GET  dashboard           aw_si_cap()       the tiles and lists, from the stored counts (insightweb-dashboard.php)
 *   POST dashboard/count     aw_si_cap()       ask for a count now (one background event)
 *   GET  settings            aw_si_cap()       the groups with today's values
 *   POST settings/numbers    manage_options    the seven numbers (aw_si_settings_save(), the same as the form)
 *   POST settings/rising     aw_si_cap()       News (GDELT) and Seeds for keyword tools (the same saves as the forms)
 *   GET  services            aw_si_cap()       the connected services and their last runs
 *
 * Every route checks the capability and, for a signed-in browser, the wp_rest nonce (kits_shell_rest_can()). The
 * aw/v1 routes the workers use are not touched.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const INSIGHTWEB_REST = 'insightweb/v1';

add_action(
	'rest_api_init',
	function () {
		if ( ! function_exists( 'kits_shell_rest_can' ) ) {
			return;
		}
		$cap = aw_si_cap();
		register_rest_route(
			INSIGHTWEB_REST,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'permission_callback' => kits_shell_rest_can( $cap ),
				'callback'            => fn() => rest_ensure_response( insightweb_dashboard_payload() ),
			)
		);
		register_rest_route(
			INSIGHTWEB_REST,
			'/dashboard/count',
			array(
				'methods'             => 'POST',
				'permission_callback' => kits_shell_rest_can( $cap ),
				'callback'            => fn() => rest_ensure_response( array( 'waiting' => insightweb_dash_request() ) ),
			)
		);
		register_rest_route(
			INSIGHTWEB_REST,
			'/settings',
			array(
				'methods'             => 'GET',
				'permission_callback' => kits_shell_rest_can( $cap ),
				'callback'            => fn() => rest_ensure_response( insightweb_settings_payload( current_user_can( 'manage_options' ) ) ),
			)
		);
		register_rest_route(
			INSIGHTWEB_REST,
			'/settings/numbers',
			array(
				'methods'             => 'POST',
				'permission_callback' => kits_shell_rest_can( 'manage_options' ),
				'args'                => array(
					'fields' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => array( 'string', 'integer' ) ),
						'default'              => array(),
					),
					'reset'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => 'insightweb_rest_save_numbers',
			)
		);
		register_rest_route(
			INSIGHTWEB_REST,
			'/settings/rising',
			array(
				'methods'             => 'POST',
				'permission_callback' => kits_shell_rest_can( $cap ),
				'args'                => array(
					'gdelt' => array( 'type' => 'boolean' ),
					'seeds' => array( 'type' => 'string' ),
				),
				'callback'            => 'insightweb_rest_save_rising',
			)
		);
		register_rest_route(
			INSIGHTWEB_REST,
			'/services',
			array(
				'methods'             => 'GET',
				'permission_callback' => kits_shell_rest_can( $cap ),
				'callback'            => fn() => rest_ensure_response( array( 'services' => insightweb_services() ) ),
			)
		);
	}
);

/* ---------- Dashboard ---------- */

/**
 * A change in plain words: "+12% on the 7 days before".
 *
 * @param float|int|null $now    This window.
 * @param float|int|null $before The window before.
 * @param int            $days   Window length.
 * @param string         $kind   'pct' (percent change), 'points' (a rate's change in points) or 'diff' (the plain difference).
 */
function insightweb_delta( $now, $before, int $days, string $kind = 'pct' ): string {
	if ( null === $now || null === $before ) {
		return '';
	}
	if ( 'pct' === $kind ) {
		if ( ! $before ) {
			return '';
		}
		$d    = round( ( $now - $before ) / $before * 100 );
		$text = ( $d > 0 ? '+' : '' ) . number_format_i18n( $d ) . '%';
	} else {
		$d    = round( $now - $before, 1 );
		$text = ( $d > 0 ? '+' : '' ) . number_format_i18n( $d, floor( $d ) === $d ? 0 : 1 ) . ( 'points' === $kind ? ' ' . __( 'points', 'aw-seo-extras' ) : '' );
	}
	/* translators: 1: change (+12%), 2: number of days. */
	return sprintf( __( '%1$s on the %2$d days before', 'aw-seo-extras' ), $text, $days );
}

/**
 * One range of the Dashboard, from the stored counts: the tiles, the clicks per day and the two lists. Formatting only.
 *
 * @param array $r     A range from insightweb_dash_count().
 * @param array $links pages, keywords: addresses of the current tabs.
 */
function insightweb_dash_shape( array $r, array $links ): array {
	$n     = (int) $r['days'];
	$full  = (int) $r['prevStored'] >= $n; // Compare only with a fully stored window before.
	$num   = fn( $v ) => number_format_i18n( (int) $v );
	$ctr   = fn( $c, $i ) => $i > 0 ? round( $c / $i * 100, 1 ) : null;
	$now_r = $ctr( (int) $r['clicks'], (int) $r['impr'] );
	$was_r = $ctr( (int) $r['prevClicks'], (int) $r['prevImpr'] );
	$pos   = null === $r['pos'] ? null : (float) $r['pos'];
	$ppos  = null === $r['prevPos'] ? null : (float) $r['prevPos'];
	$sub   = (int) $r['stored'] < $n
		/* translators: 1: days stored, 2: days in the range. */
		? sprintf( __( '%1$d of %2$d days stored so far', 'aw-seo-extras' ), (int) $r['stored'], $n )
		/* translators: %d: number of days. */
		: sprintf( __( 'Search Console, %d days', 'aw-seo-extras' ), $n );
	$tiles = array(
		array(
			'key'   => 'clicks',
			'label' => __( 'Clicks', 'aw-seo-extras' ),
			'value' => $num( $r['clicks'] ),
			'delta' => $full ? insightweb_delta( (int) $r['clicks'], (int) $r['prevClicks'], $n ) : '',
			'sub'   => $sub,
			'href'  => $links['pages'] ?? '',
		),
		array(
			'key'   => 'impr',
			'label' => __( 'Impressions', 'aw-seo-extras' ),
			'value' => $num( $r['impr'] ),
			'delta' => $full ? insightweb_delta( (int) $r['impr'], (int) $r['prevImpr'], $n ) : '',
			'sub'   => __( 'Times a page of ours showed in Google', 'aw-seo-extras' ),
			'href'  => $links['pages'] ?? '',
		),
		array(
			'key'   => 'ctr',
			'label' => __( 'Click rate', 'aw-seo-extras' ),
			'value' => null === $now_r ? '–' : number_format_i18n( $now_r, 1 ) . '%',
			'delta' => $full ? insightweb_delta( $now_r, $was_r, $n, 'points' ) : '',
			'sub'   => __( 'Clicks for every 100 impressions', 'aw-seo-extras' ),
			'href'  => '',
		),
		array(
			'key'   => 'pos',
			'label' => __( 'Average position', 'aw-seo-extras' ),
			'value' => null === $pos ? '–' : number_format_i18n( $pos, 1 ),
			'delta' => $full ? insightweb_delta( $pos, $ppos, $n, 'diff' ) : '',
			'sub'   => __( '1 is the top of Google; lower is better', 'aw-seo-extras' ),
			'href'  => $links['keywords'] ?? '',
		),
		array(
			'key'   => 'pages',
			'label' => __( 'Pages in search', 'aw-seo-extras' ),
			'value' => $num( $r['pages'] ),
			'delta' => $full ? insightweb_delta( (int) $r['pages'], (int) $r['prevPages'], $n ) : '',
			'sub'   => __( 'Pages with at least one impression', 'aw-seo-extras' ),
			'href'  => $links['pages'] ?? '',
		),
		array(
			'key'   => 'queries',
			'label' => __( 'Searches', 'aw-seo-extras' ),
			'value' => $num( $r['queries'] ),
			'delta' => $full ? insightweb_delta( (int) $r['queries'], (int) $r['prevQuery'], $n ) : '',
			'sub'   => __( 'Different searches we showed for', 'aw-seo-extras' ),
			'href'  => $links['keywords'] ?? '',
		),
	);
	$chart = array();
	foreach ( (array) $r['chart'] as $d => $v ) {
		$chart[] = array(
			'label' => wp_date( 'j M', strtotime( (string) $d . ' 12:00:00' ) ),
			'value' => (int) $v,
		);
	}
	$row   = fn( array $x, int $i ) => array(
		'id'     => $i,
		'title'  => (string) $x[0],
		'clicks' => $num( $x[1] ),
		'impr'   => $num( $x[2] ),
		'pos'    => null === $x[3] ? '–' : number_format_i18n( (float) $x[3], 1 ),
	);
	$pages = array();
	foreach ( (array) $r['topPages'] as $i => $x ) {
		$p        = $row( $x, (int) $i );
		$p['url'] = ( '' !== $p['title'] && '/' === $p['title'][0] && false === strpos( $p['title'], '{' ) ) ? home_url( $p['title'] ) : '';
		$pages[]  = $p;
	}
	$queries = array();
	foreach ( (array) $r['topQueries'] as $i => $x ) {
		$queries[] = $row( $x, (int) $i );
	}
	return array(
		'tiles'      => $tiles,
		'chart'      => $chart,
		'topPages'   => $pages,
		'topQueries' => $queries,
		'compared'   => $full,
	);
}

/**
 * The two health tiles and the sources list, from the run log (stored state; nothing is counted here).
 *
 * @param array  $sources Rows from aw_seo_dash_sources(): name, last, status, problem.
 * @param array  $gsc     The run log's gsc entry (ok, error, info).
 * @param string $state   The Search Console source's status (failed, late, refused, notrun, ok).
 * @param array  $links   overview, settings: addresses of the current tabs.
 */
function insightweb_health_shape( array $sources, array $gsc, string $state, array $links ): array {
	$words = array(
		'failed'  => __( 'Failed', 'aw-seo-extras' ),
		'late'    => __( 'Late', 'aw-seo-extras' ),
		'refused' => __( 'Refused by the service', 'aw-seo-extras' ),
		'notrun'  => __( 'Not run yet', 'aw-seo-extras' ),
		'ok'      => __( 'OK', 'aw-seo-extras' ),
	);
	$tones = array(
		'failed' => 'error',
		'late'   => 'warn',
	);
	$need  = array_values( array_filter( $sources, fn( $s ) => ! empty( $s['problem'] ) ) );
	$ok    = (int) ( $gsc['ok'] ?? 0 );
	$upto  = (string) ( $gsc['info']['up_to'] ?? '' );
	$sub   = '' !== $upto
		/* translators: %s: date. */
		? sprintf( __( 'Figures up to %s', 'aw-seo-extras' ), wp_date( 'j M Y', strtotime( $upto . ' 12:00:00' ) ) )
		: __( 'Daily at 07:10', 'aw-seo-extras' );
	if ( ! empty( $gsc['error']['message'] ) && 'failed' === $state ) {
		$sub = (string) $gsc['error']['message'];
	}
	$tiles = array(
		array(
			'key'   => 'import',
			'label' => __( 'Last Search Console import', 'aw-seo-extras' ),
			'value' => $ok ? wp_date( 'j M, H:i', $ok ) : __( 'Never', 'aw-seo-extras' ),
			'delta' => '',
			'sub'   => $sub,
			'href'  => $links['settings'] ?? '',
			'tone'  => $tones[ $state ] ?? '',
		),
		array(
			'key'   => 'jobs',
			'label' => __( 'Daily jobs', 'aw-seo-extras' ),
			'value' => $need
				/* translators: %s: number of sources. */
				? sprintf( _n( '%s needs attention', '%s need attention', count( $need ), 'aw-seo-extras' ), number_format_i18n( count( $need ) ) )
				: __( 'All working', 'aw-seo-extras' ),
			'delta' => '',
			'sub'   => $need
				? implode( ', ', wp_list_pluck( $need, 'name' ) )
				/* translators: %s: number of sources. */
				: sprintf( _n( '%s source checked', '%s sources checked', count( $sources ), 'aw-seo-extras' ), number_format_i18n( count( $sources ) ) ),
			'href'  => $links['overview'] ?? '',
			'tone'  => $need ? ( in_array( 'failed', wp_list_pluck( $need, 'status' ), true ) ? 'error' : 'warn' ) : '',
		),
	);
	$rows  = array();
	foreach ( $sources as $i => $s ) {
		$rows[] = array(
			'id'     => (int) $i,
			'title'  => (string) $s['name'],
			'last'   => ! empty( $s['last'] ) ? wp_date( 'j M Y, H:i', (int) $s['last'] ) : __( 'Never', 'aw-seo-extras' ),
			'status' => $words[ $s['status'] ] ?? (string) $s['status'],
			'tone'   => $tones[ $s['status'] ] ?? '',
		);
	}
	return array(
		'tiles'    => $tiles,
		'sources'  => $rows,
		'problems' => count( $need ),
	);
}

/** The Dashboard: every range from the stored counts, the health tiles and the sources. */
function insightweb_dashboard_payload(): array {
	$c        = get_transient( INSIGHTWEB_DASH );
	$counting = ! is_array( $c );
	if ( $counting ) {
		insightweb_dash_request(); // The first view after a deploy: count in the background.
		$c = array();
	}
	$links  = array(
		'overview' => aw_si_url( 'overview' ),
		'keywords' => aw_si_url( 'keywords' ),
		'pages'    => isset( aw_si_extra_tabs()['pages'] ) ? aw_si_url( 'pages' ) : aw_si_url( 'keywords' ),
		'settings' => insightweb_ui_url( 'settings' ),
	);
	$ranges = array();
	foreach ( (array) ( $c['ranges'] ?? array() ) as $n => $r ) {
		$ranges[ (string) $n ] = insightweb_dash_shape( (array) $r, $links );
	}
	$runs  = aw_si_runs();
	$end   = (string) ( $c['end'] ?? '' );
	$state = 'ok';
	if ( '' !== aw_si_run_problem( 'gsc' ) ) {
		$state = 'failed';
	} elseif ( empty( $runs['gsc']['ok'] ) ) {
		$state = 'notrun';
	} elseif ( aw_si_stale( 'gsc' ) ) {
		$state = 'late';
	}
	return array(
		'counting' => $counting,
		'off'      => (bool) get_option( 'aw_si_off' ),
		'at'       => (int) ( $c['at'] ?? 0 ),
		'atText'   => ! empty( $c['at'] ) ? wp_date( 'j M Y, H:i', (int) $c['at'] ) : '',
		'endText'  => '' !== $end ? wp_date( 'j M Y', strtotime( $end . ' 12:00:00' ) ) : '',
		'ranges'   => (object) $ranges,
		'health'   => insightweb_health_shape( aw_seo_dash_sources(), (array) ( $runs['gsc'] ?? array() ), $state, $links ),
		'links'    => $links,
	);
}

/* ---------- Settings ---------- */

/**
 * The numbers as the screen shows them, in three blocks.
 *
 * @param array $saved Stored numbers (aw_si_settings_saved()).
 */
function insightweb_numbers_group( array $saved ): array {
	$fields = aw_si_setting_fields();
	$blocks = array(
		'priorities' => array( __( 'Priorities', 'aw-seo-extras' ), __( 'When a search counts as high demand or a quick win, and how listing names are shown.', 'aw-seo-extras' ), array( 'AW_SI_HIGH_TRENDS', 'AW_SI_HIGH_IMPR', 'AW_SI_QUICK_MIN', 'AW_SI_MOVERS' ) ),
		'flags'      => array( __( 'Page flags', 'aw-seo-extras' ), __( 'Scores under these mark a page on the Pages tab.', 'aw-seo-extras' ), array( 'AW_SI_PSI_FLAG', 'AW_SI_RM_LOW' ) ),
		'keep'       => array( __( 'Keeping data', 'aw-seo-extras' ), '', array( 'AW_SI_KEEP_DAYS' ) ),
	);
	$out    = array();
	foreach ( $blocks as $key => $b ) {
		$list = array();
		foreach ( $b[2] as $const ) {
			$f      = $fields[ $const ];
			$has    = array_key_exists( $const, $saved );
			$value  = $has ? (int) $saved[ $const ] : (int) $f['default'];
			$list[] = array(
				'const'       => $const,
				'type'        => 'int',
				'label'       => $f['label'],
				'help'        => $f['help'],
				'text'        => (string) $value,
				'defaultText' => (string) $f['default'],
				'changed'     => $value !== (int) $f['default'],
			);
		}
		$out[] = array(
			'key'    => $key,
			'label'  => $b[0],
			'desc'   => $b[1],
			'fields' => $list,
		);
	}
	return array(
		'slug'   => 'numbers',
		'label'  => __( 'Numbers', 'aw-seo-extras' ),
		'blocks' => $out,
	);
}

/** The Rising group: News (GDELT) and the seeds for keyword tools. */
function insightweb_rising_group(): array {
	return array(
		'slug'   => 'rising',
		'label'  => __( 'Rising', 'aw-seo-extras' ),
		'blocks' => array(),
		'gdelt'  => array(
			'on'   => aw_si_gdelt_on(),
			'help' => __( 'Off by default: GDELT refuses requests from the workers\' and the site\'s addresses. While it is off its results are not stored, and it is not shown as late or failing.', 'aw-seo-extras' ),
		),
		'seeds'  => array(
			'text' => implode( "\n", aw_si_tool_seeds() ),
			'help' => __( 'One search per line. The Ubersuggest, vidIQ and OpenSEO reports start from these, in turn.', 'aw-seo-extras' ),
		),
	);
}

/**
 * Every group the user may change: the numbers for administrators (as the current form), then Rising.
 *
 * @param bool $admin The user can manage options.
 */
function insightweb_settings_payload( bool $admin ): array {
	$groups = array();
	if ( $admin ) {
		$groups[] = insightweb_numbers_group( aw_si_settings_saved() );
	}
	$groups[] = insightweb_rising_group();
	return array(
		'groups'  => $groups,
		'moreUrl' => aw_si_url( 'settings' ),
	);
}

/**
 * Save the numbers. Fields not sent keep their value.
 *
 * @param WP_REST_Request $request Request.
 */
function insightweb_rest_save_numbers( WP_REST_Request $request ) {
	$reset = (bool) $request['reset'];
	$known = array_intersect_key( (array) $request['fields'], aw_si_setting_fields() );
	foreach ( $known as $const => $v ) {
		if ( ! is_numeric( $v ) ) {
			/* translators: %s: field name. */
			return new WP_Error( 'rest_invalid_param', sprintf( __( '%s must be a whole number.', 'aw-seo-extras' ), aw_si_setting_fields()[ $const ]['label'] ), array( 'status' => 400 ) );
		}
	}
	$saved = aw_si_settings_save( $known, $reset );
	return rest_ensure_response(
		array(
			'group'   => insightweb_numbers_group( aw_si_settings_saved() ),
			'saved'   => $saved,
			'message' => $reset ? __( 'Defaults restored.', 'aw-seo-extras' ) : ( $saved ? __( 'Saved. The new numbers are used from the next daily run.', 'aw-seo-extras' ) : __( 'Nothing changed.', 'aw-seo-extras' ) ),
		)
	);
}

/**
 * Save the Rising group. Each part only when sent.
 *
 * @param WP_REST_Request $request Request.
 */
function insightweb_rest_save_rising( WP_REST_Request $request ) {
	$saved = false;
	if ( null !== $request['gdelt'] ) {
		$saved = aw_si_gdelt_save( (bool) $request['gdelt'] ) || $saved;
	}
	if ( null !== $request['seeds'] ) {
		$saved = aw_si_tool_seeds_save( sanitize_textarea_field( (string) $request['seeds'] ) ) || $saved;
	}
	return rest_ensure_response(
		array(
			'group'   => insightweb_rising_group(),
			'saved'   => $saved,
			'message' => $saved ? __( 'Saved.', 'aw-seo-extras' ) : __( 'Nothing changed.', 'aw-seo-extras' ),
		)
	);
}

/* ---------- Connected services ---------- */

/**
 * A source's last run, in plain words.
 *
 * @param string $src Source key.
 * @return array [ status, tone, lines ].
 */
function insightweb_run_state( string $src ): array {
	$r    = aw_si_runs()[ $src ] ?? array();
	$ok   = (int) ( $r['ok'] ?? 0 );
	$err  = aw_si_run_problem( $src );
	$line = array(
		/* translators: %s: date and time. */
		$ok ? sprintf( __( 'Last good run: %s', 'aw-seo-extras' ), wp_date( 'j M Y, H:i', $ok ) ) : __( 'Not run yet', 'aw-seo-extras' ),
	);
	if ( function_exists( 'aw_si_run_detail' ) ) {
		$line = array_merge( $line, aw_si_run_detail( $src, (array) ( $r['info'] ?? array() ) ) );
	}
	if ( $err ) {
		$line[] = $err;
		return array( __( 'Last run failed', 'aw-seo-extras' ), 'error', $line );
	}
	if ( $ok && aw_si_stale( $src ) ) {
		return array( __( 'Late', 'aw-seo-extras' ), 'warn', $line );
	}
	return array( $ok ? __( 'Working', 'aw-seo-extras' ) : __( 'Not run yet', 'aw-seo-extras' ), '', $line );
}

/** Connected services: Site Kit's Search Console, GA4 and PageSpeed, then the services with a key in Kits → Keys. */
function insightweb_services(): array {
	$sitekit = class_exists( '\Google\Site_Kit\Plugin' );
	$sk_url  = add_query_arg( 'page', 'googlesitekit-settings', admin_url( 'admin.php' ) );
	$keys    = current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=aw-keys' ) : '';
	$run     = aw_si_url( 'settings' );
	$out     = array();
	$module  = function ( string $slug, string $src, string $name, string $prop ) use ( $sitekit, $sk_url, $run ) {
		$s                             = get_option( 'googlesitekit_' . $slug . '_settings' );
		$s                             = is_array( $s ) ? $s : array();
		$connected                     = $sitekit && ( '' === $prop || ! empty( $s[ $prop ] ) );
		list( $status, $tone, $lines ) = insightweb_run_state( $src );
		if ( ! $sitekit ) {
			$status = __( 'Site Kit not active', 'aw-seo-extras' );
			$tone   = 'error';
		} elseif ( ! $connected ) {
			$status = __( 'Not connected', 'aw-seo-extras' );
			$tone   = 'error';
		}
		return array(
			'slug'    => $src,
			'name'    => $name,
			'status'  => $status,
			'tone'    => $tone,
			'summary' => $connected && '' !== $prop
				/* translators: %s: Google property. */
				? sprintf( __( 'Through Site Kit by Google, property %s. Read only.', 'aw-seo-extras' ), (string) $s[ $prop ] )
				: __( 'Through Site Kit by Google. Read only.', 'aw-seo-extras' ),
			'lines'   => $lines,
			'links'   => array(
				array(
					'label' => __( 'Site Kit settings', 'aw-seo-extras' ),
					'url'   => $sitekit ? $sk_url : admin_url( 'plugins.php' ),
				),
				array(
					'label' => __( 'Run now (current Settings)', 'aw-seo-extras' ),
					'url'   => $run,
				),
			),
		);
	};
	$out[]   = $module( 'search-console', 'gsc', __( 'Search Console', 'aw-seo-extras' ), 'propertyID' );
	$out[]   = $module( 'analytics-4', 'ga4', __( 'Google Analytics 4', 'aw-seo-extras' ), 'propertyID' );
	$psi     = $module( 'pagespeed-insights', 'psi', __( 'PageSpeed Insights', 'aw-seo-extras' ), '' );
	if ( '' !== aw_si_key( 'AW_PSI_KEY' ) ) {
		$psi['summary'] = __( 'Through Site Kit by Google, with its own key as a fallback. Weekly.', 'aw-seo-extras' );
	}
	$out[] = $psi;
	$keyed = array(
		array( 'clarity', __( 'Microsoft Clarity', 'aw-seo-extras' ), function_exists( 'aw_si_clarity_on' ) && aw_si_clarity_on(), __( 'Daily, 2 of its 10 free calls.', 'aw-seo-extras' ) ),
		array( 'bing', __( 'Bing Webmaster', 'aw-seo-extras' ), function_exists( 'aw_si_bing_on' ) && aw_si_bing_on(), __( 'Monthly: links to us, and clicks and impressions by search and by page.', 'aw-seo-extras' ) ),
		array( 'alerts', __( 'Google Alerts', 'aw-seo-extras' ), function_exists( 'aw_si_alerts_feeds' ) && (bool) aw_si_alerts_feeds(), __( 'Daily: up to two alert feeds, as mentions on the Links tab.', 'aw-seo-extras' ) ),
	);
	foreach ( $keyed as $k ) {
		list( $status, $tone, $lines ) = insightweb_run_state( $k[0] );
		if ( ! $k[2] ) {
			$status = __( 'Not set up', 'aw-seo-extras' );
			$tone   = '';
			$lines  = array( __( 'Its key is not set, so it does not run.', 'aw-seo-extras' ) );
		}
		$links = array();
		if ( $keys ) {
			$links[] = array(
				'label' => __( 'Kits → Keys', 'aw-seo-extras' ),
				'url'   => $keys,
			);
		}
		$out[] = array(
			'slug'    => $k[0],
			'name'    => $k[1],
			'status'  => $status,
			'tone'    => $tone,
			'summary' => $k[3],
			'lines'   => $lines,
			'links'   => $links,
		);
	}
	return $out;
}
