<?php
/**
 * Plugins → InsightWeb, opening Search Insights. The screen is drawn by the Kits admin shell (insightweb-ui.php): the
 * Dashboard (the Overview boxes, through REST), Keywords, the tabs added by other files (Pages, Check list, Rising,
 * Sourcing keywords and more) and Settings. This file holds the menu, the tab addresses, the source notices, each
 * tab's output the shell draws, the Overview boxes and the admin-post handlers.
 *
 * @package insightweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_SLUG = 'aw-search-insights';

/** Same people as Sourcing and Search demand. */
function aw_si_cap(): string {
	return defined( 'AW_SRC_CAP' ) ? AW_SRC_CAP : 'manage_options'; // The same people as the Sourcing screens.
}

/**
 * Address of a Search Insights tab.
 *
 * @param string $tab overview, keywords or settings.
 */
function aw_si_url( string $tab = 'overview' ): string {
	return add_query_arg(
		array(
			'page' => AW_SI_SLUG,
			'tab'  => $tab,
		),
		admin_url( 'admin.php' )
	);
}

/**
 * Where a form action (admin-post.php) goes back to: the tab's screen.
 *
 * @param string $tab The tab.
 */
function aw_si_return_url( string $tab = 'overview' ): string {
	return aw_si_url( $tab );
}

/**
 * Nonce'd link for a priority row action.
 *
 * @param string $action override_p1 … override_clear.
 * @param int    $id     Keyword record.
 */
function aw_si_kw_action_url( string $action, int $id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'aw_si_kw' => $action,
				'kw[]'     => $id,
			),
			aw_si_url( 'keywords' )
		),
		'bulk-keywords'
	);
}

/**
 * Tabs other plugins add with the aw_si_tabs filter (key => label), e.g. SourceWeb's per-keyword view. Each renders
 * on the action aw_si_tab_{key}; the action aw_si_load_tab_{key} runs before output (screen options, redirects).
 */
function aw_si_extra_tabs(): array {
	$out = array();
	foreach ( (array) apply_filters( 'aw_si_tabs', array() ) as $key => $label ) {
		$key = sanitize_key( (string) $key );
		if ( '' !== $key && ! in_array( $key, array( 'overview', 'keywords', 'settings' ), true ) ) {
			$out[ $key ] = (string) $label;
		}
	}
	return $out;
}

/** The current tab. */
function aw_si_tab(): string {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	return in_array( $tab, array_merge( array( 'overview', 'keywords', 'settings' ), array_keys( aw_si_extra_tabs() ) ), true ) ? $tab : 'overview';
}

add_action(
	'admin_menu',
	function () {
		// InsightWeb's own item, opening Search Insights (owner, 11 Nov 2026: Search Insights is InsightWeb's, not RankWeb's;
		// our plugins sit under Plugins). Same address as before (admin.php?page=aw-search-insights) and the same hook name.
		// Without Kits Core it is a menu of its own.
		// The Wugweb block (owner, 12 Nov 2026): Kits, DirectoryWeb, SourceWeb, RankWeb, InsightWeb, MailWeb, PlanWeb at 58.1-58.7,
		// after one separator (58.05), before WordPress's second separator (59); the same with or without Kits Core.
		// The icon is the plugin's one-colour SVG as a data URI, which WordPress paints in the admin colour scheme.
		$icon = dirname( __DIR__, 2 ) . '/assets/menu-icon.svg';
		$icon = is_readable( $icon ) ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon ) ) : 'dashicons-chart-area'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own small SVG file.
		add_menu_page( __( 'Search Insights', 'insightweb' ), 'InsightWeb', aw_si_cap(), AW_SI_SLUG, 'aw_si_screen', $icon, '58.5' );
		$GLOBALS['admin_page_hooks'][ AW_SI_SLUG ] = 'insightweb'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screens' hook prefix (insightweb_page_…), whatever the title.
		if ( ! isset( $GLOBALS['menu']['58.05'] ) ) {
			$GLOBALS['menu']['58.05'] = array( '', 'read', 'separator-wugweb', '', 'wp-menu-separator', 'separator-wugweb' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- one separator above the block, as core adds its own.
		}
		$hook = add_submenu_page( AW_SI_SLUG, __( 'Search Insights', 'insightweb' ), __( 'Search Insights', 'insightweb' ), aw_si_cap(), AW_SI_SLUG, 'aw_si_screen' );
		if ( $hook ) {
			add_action( 'load-' . $hook, 'aw_si_load' );
		}
	},
	100
);

add_filter(
	'set_screen_option_aw_si_keywords_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

/** Before output: screen options, the tab's load hook and priority overrides (then redirect). */
function aw_si_load(): void {
	aw_si_install();
	$tab = aw_si_tab();
	if ( isset( aw_si_extra_tabs()[ $tab ] ) ) {
		do_action( 'aw_si_load_tab_' . $tab );
	}
	if ( 'keywords' === $tab ) {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Keywords per page', 'insightweb' ),
				'default' => 50,
				'option'  => 'aw_si_keywords_per_page',
			)
		);
		aw_si_handle_overrides();
	}
}

/**
 * The Overview boxes in order: key => [ title, callback ]. The Dashboard reads each through GET
 * insightweb/v1/dashboard/box (insightweb-boxes.php); it draws the sources itself.
 *
 * @return array[]
 */
function aw_si_overview_boxes(): array {
	// A setup step first, only while it is not done (12 Nov 2026: the Google Alerts feeds were never set).
	$setup = function_exists( 'aw_si_alerts_feeds' ) && ! aw_si_alerts_feeds() ? array( 'setup' => array( __( 'Setup step', 'insightweb' ), 'aw_si_box_setup' ) ) : array();
	return $setup + array(
		'priorities'  => array( __( 'Priorities', 'insightweb' ), 'aw_si_box_priorities' ),
		/* translators: %d: minimum impressions. */
		'quick'       => array( sprintf( __( 'Quick wins (position 8–20, at least %d impressions)', 'insightweb' ), AW_SI_QUICK_MIN ), 'aw_si_box_quick' ),
		'rising-week' => array( __( 'Rising this week', 'insightweb' ), 'aw_si_box_rising_week' ),
		'movers'      => array( __( 'Movers (28 days against the 28 before)', 'insightweb' ), 'aw_si_box_movers' ),
		'brand'       => array( __( 'Brand', 'insightweb' ), 'aw_si_box_brand' ),
		'ai'          => array( __( 'AI referrals (GA4, 28 days)', 'insightweb' ), 'aw_si_box_ai' ),
		'referrers'   => array( __( 'Top referrers (GA4)', 'insightweb' ), 'aw_si_box_referrers' ),
		'campaigns'   => array( __( 'Campaigns by source (GA4, 28 days)', 'insightweb' ), 'aw_si_box_campaigns' ),
		'nf'          => array( __( 'Not found (404s, GA4, 28 days)', 'insightweb' ), 'aw_si_box_nf' ),
		'index'       => array( __( 'Index status (Search Console totals, and the pages we inspected)', 'insightweb' ), 'aw_si_box_index' ),
		'request'     => array( __( 'Request indexing (10 a day)', 'insightweb' ), 'aw_si_box_request' ),
		'gsc-types'   => array( __( 'Discover and Google News (Search Console, 28 days)', 'insightweb' ), 'aw_si_box_gsc_types' ),
		'clarity'     => array( __( 'Clarity (28 days)', 'insightweb' ), 'aw_si_box_clarity' ),
		'site-search' => array( __( 'Searches on our site (28 days, minimum)', 'insightweb' ), 'aw_si_box_site_search' ),
		'gaps'        => array( __( 'Content gaps (keyword map gaps and P2)', 'insightweb' ), 'aw_si_box_gaps' ),
		'crawl'       => array( __( 'Crawler access (weekly)', 'insightweb' ), 'aw_si_box_crawl' ),
		'bing-crawl'  => array( __( 'Bing crawl (12 weeks)', 'insightweb' ), 'aw_si_box_bing_crawl' ),
	);
}

/** Bulk and row priority overrides. The only write this screen makes, and only to Search Insights' own table. */
function aw_si_handle_overrides(): void {
	$action = '';
	foreach ( array( 'aw_si_kw', 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked below before any change.
		if ( 0 === strpos( $v, 'override_' ) ) {
			$action = $v;
			break;
		}
	}
	if ( ! $action ) {
		return;
	}
	check_admin_referer( 'bulk-keywords' );
	if ( ! current_user_can( aw_si_cap() ) ) {
		wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
	}
	$ids = array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST['kw'] ?? array() ) ) );
	if ( ! $ids ) {
		wp_safe_redirect( add_query_arg( 'none', 1, aw_si_return_url( 'keywords' ) ) );
		exit;
	}
	$set = array(
		'override_p1'    => 'P1',
		'override_p2'    => 'P2',
		'override_p3'    => 'P3',
		'override_clear' => '',
	);
	if ( $ids && isset( $set[ $action ] ) ) {
		global $wpdb;
		$t  = aw_si_table( 'keywords' );
		$in = implode( ',', $ids );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET priority_override = %s WHERE id IN ({$in})", $set[ $action ] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers.
	}
	$back = remove_query_arg( array( 'aw_si_kw', 'kw', '_wpnonce', '_wp_http_referer', 'action', 'action2' ), wp_get_referer() ? wp_get_referer() : aw_si_url( 'keywords' ) );
	wp_safe_redirect( add_query_arg( 'updated', count( $ids ), $back ) );
	exit;
}

/** Notices per source: an info line with every source's last update, then a warning or error per problem. */
function aw_si_source_notices(): void {
	$off = (int) get_option( 'aw_si_off' );
	if ( $off ) {
		/* translators: %s: date and time. */
		wp_admin_notice( esc_html( sprintf( __( 'Search Insights is off: its data was deleted on %s and nothing runs on schedule. Settings → Run the daily update starts it again.', 'insightweb' ), wp_date( 'j M Y, H:i', $off ) ) ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
		return;
	}
	$runs  = aw_si_runs();
	$parts = array();
	foreach ( aw_si_sources() as $src => $s ) {
		$r   = $runs[ $src ] ?? array();
		$ok  = (int) ( $r['ok'] ?? 0 );
		$err = aw_si_run_problem( $src );
		/* translators: 1: source, 2: date and time. */
		$parts[] = $ok ? sprintf( __( '%1$s: %2$s', 'insightweb' ), $s['label'], wp_date( 'j M, H:i', $ok ) ) : sprintf( __( '%s: not run yet', 'insightweb' ), $s['label'] );
		if ( $err && aw_si_rising_blocked( $src ) ) {
			$paste = in_array( $src, array( 'rising_trends_interest', 'rising_trends_related' ), true ) ? ' ' . __( 'Paste the figures on the Rising tab.', 'insightweb' ) : '';
			$msg   = $ok
				/* translators: 1: source, 2: date and time. */
				? sprintf( __( '%1$s: the service refuses automated requests from our servers (HTTP 429), so it is not fetched; figures from %2$s.', 'insightweb' ), $s['label'], wp_date( 'j M, H:i', $ok ) )
				/* translators: %s: source. */
				: sprintf( __( '%s: the service refuses automated requests from our servers (HTTP 429), so it is not fetched; no figures yet.', 'insightweb' ), $s['label'] );
			wp_admin_notice( esc_html( $msg . $paste ), array( 'type' => 'info', 'additional_classes' => array( 'inline' ) ) );
		} elseif ( $err ) {
			/* translators: 1: source, 2: the problem, 3: date of the data still shown. */
			$msg = sprintf( __( '%1$s: %2$s The figures shown are from %3$s.', 'insightweb' ), $s['label'], $err, $ok ? wp_date( 'j M, H:i', $ok ) : __( 'no earlier run', 'insightweb' ) );
			wp_admin_notice( '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $msg ), array( 'type' => 'error', 'additional_classes' => array( 'inline' ) ) );
		} elseif ( $ok && in_array( $r['info']['status'] ?? '', array( 'baseline', 'no rows' ), true ) ) {
			$msg = 'baseline' === $r['info']['status']
				/* translators: 1: source, 2: date and time. */
				? __( '%1$s ran on %2$s and recorded the usual level of each term (a baseline): rising terms show from its next run.', 'insightweb' )
				/* translators: 1: source, 2: date and time. */
				: __( '%1$s ran on %2$s and kept nothing: the source answered, and no term passed the filters (the Rising tab shows what was dropped and why).', 'insightweb' );
			wp_admin_notice( esc_html( sprintf( $msg, $s['label'], wp_date( 'j M, H:i', $ok ) ) ), array( 'type' => 'info', 'additional_classes' => array( 'inline' ) ) );
		} elseif ( $ok && aw_si_stale( $src ) ) {
			/* translators: %s: source. */
			wp_admin_notice( '<span class="dashicons dashicons-clock" aria-hidden="true"></span> ' . esc_html( sprintf( __( '%s has not updated on schedule.', 'insightweb' ), $s['label'] ) ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
		}
		if ( ! empty( $r['info']['truncated'] ) ) {
			$days = implode( ', ', array_keys( (array) $r['info']['truncated'] ) );
			$msg  = 'gsc' === $src
				/* translators: %s: dates. */
				? __( 'Search Console hit the 5,000-row maximum on %s even after fetching the busiest pages one by one, so the rarest searches of those days may be missing.', 'insightweb' )
				/* translators: %s: dates. */
				: __( 'GA4 returned its row maximum on %s, so the smallest pages or terms of those days may be missing.', 'insightweb' );
			wp_admin_notice( esc_html( sprintf( $msg, $days ) ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
		}
		// The 28-day 404 and campaign-event reports are one window each, not days: they have their own flag.
		$cut = array_filter(
			array(
				__( '404s', 'insightweb' )            => ! empty( $r['info']['nf']['truncated'] ),
				__( 'campaign events', 'insightweb' ) => ! empty( $r['info']['camp']['truncated'] ),
			)
		);
		if ( 'ga4' === $src && $cut ) {
			/* translators: %s: 404s and/or campaign events. */
			wp_admin_notice( esc_html( sprintf( __( 'GA4 returned its row maximum for the 28-day %s report, so the rarest rows of that window may be missing.', 'insightweb' ), implode( ', ', array_keys( $cut ) ) ) ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
		}
	}
	if ( function_exists( 'aw_si_gdelt_on' ) && ! aw_si_gdelt_on() ) {
		wp_admin_notice( esc_html__( 'News (GDELT) is switched off (Settings): GDELT refuses our requests (HTTP 429), so Rising uses the other sources.', 'insightweb' ), array( 'type' => 'info', 'additional_classes' => array( 'inline' ) ) );
	}
	wp_admin_notice( esc_html__( 'Last updated', 'insightweb' ) . ' — ' . esc_html( implode( ' · ', $parts ) ), array( 'type' => 'info', 'additional_classes' => array( 'inline' ) ) );
}

/** The Search Insights screen, drawn by the Kits admin shell; without Kits Core or the build, a notice. */
function aw_si_screen(): void {
	if ( ! current_user_can( aw_si_cap() ) ) {
		return;
	}
	if ( function_exists( 'insightweb_ui_page' ) && insightweb_ui_page( aw_si_tab() ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Search Insights', 'insightweb' ) . '</h1>';
	wp_admin_notice( esc_html__( 'InsightWeb needs Kits Core (active, with its build) to show this screen.', 'insightweb' ), array( 'type' => 'error' ) );
	echo '</div>';
}

/** The notices above every tab: each source's state, then the result of the last action. */
function aw_si_screen_notices(): void {
	aw_si_source_notices();
	aw_si_action_notices();
}

/** The result of the last action (a priority saved, a run finished). */
function aw_si_action_notices(): void {
	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		wp_admin_notice( esc_html__( 'Priority saved.', 'insightweb' ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
	}
	if ( isset( $_GET['none'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		wp_admin_notice( esc_html__( 'No keyword was selected, so nothing changed.', 'insightweb' ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
	}
	if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		wp_admin_notice( esc_html__( 'Run finished. See the notices above for each source.', 'insightweb' ), array( 'type' => 'success', 'additional_classes' => array( 'inline' ) ) );
	}
}

/** The Keywords tab: views, search and the list (bulk and row priority overrides). */
function aw_si_keywords_tab(): void {
	$table = new AW_SI_Keywords_Table();
	$table->prepare_items();
	$table->views();
	echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="keywords">';
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	if ( $view ) {
		echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
	}
	$table->search_box( __( 'Search keywords', 'insightweb' ), 'aw-si-kw' );
	$table->display();
	echo '</form>';
}

/**
 * Keyword rows for an Overview box.
 *
 * @param string $where Fixed condition from this file.
 * @param string $order Fixed order from this file.
 * @param int    $limit Rows.
 */
function aw_si_rows( string $where, string $order, int $limit = 10 ): array {
	global $wpdb;
	$t = aw_si_table( 'keywords' );
	if ( ! aw_si_ready() ) {
		return array();
	}
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$where} ORDER BY {$order} LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed conditions from this file.
}

/**
 * A small table of keyword rows: keyword, then the given columns.
 *
 * @param array $rows Keyword records.
 * @param array $cols column => label.
 */
function aw_si_mini_table( array $rows, array $cols ): void {
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Keyword', 'insightweb' ) . '</th>';
	foreach ( $cols as $label ) {
		echo '<th scope="col">' . esc_html( $label ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->label ) . '</td>';
		foreach ( array_keys( $cols ) as $c ) {
			$v = $r->$c ?? '';
			if ( 'page' === $c && '' !== $v && 0 === strpos( $v, '/' ) && false === strpos( $v, '{' ) ) {
				echo '<td><a href="' . esc_url( home_url( $v ) ) . '" target="_blank" rel="noopener">' . esc_html( $v ) . '</a></td>';
			} elseif ( 'action' === $c ) {
				echo '<td>' . esc_html( aw_si_actions()[ $v ] ?? '' ) . '</td>';
			} elseif ( is_numeric( $v ) ) {
				echo '<td>' . esc_html( number_format_i18n( (float) $v, floor( (float) $v ) === (float) $v ? 0 : 1 ) ) . '</td>';
			} else {
				echo '<td>' . esc_html( null === $v || '' === $v ? '–' : (string) $v ) . '</td>';
			}
		}
		echo '</tr>';
	}
	echo '</tbody></table>';
}

/** Overview box: what is not set up yet (the Google Alerts feeds), and where to set it. */
function aw_si_box_setup(): void {
	echo '<p>' . esc_html__( 'Google Alerts feeds are not set, so mentions of Animal Writes on the web are not read. Create an alert at google.com/alerts, choose Deliver to: RSS feed, and paste the feed address under Animal Writes → Keys. This box goes once a feed is set.', 'insightweb' ) . '</p>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=aw-keys' ) ) . '">' . esc_html__( 'Animal Writes → Keys', 'insightweb' ) . '</a></p>';
	}
}

/**
 * What a source's last run recorded, as short phrases for the Dashboard's sources (insightweb_run_state()): the date of the figures and the rows, plus
 * each source's own counts (keyword roles, the rollup, crawler checks, PageSpeed pages not measured, GA4 warnings).
 *
 * @param string $src  Source key.
 * @param array  $info The run's info.
 * @return string[]
 */
function aw_si_run_detail( string $src, array $info ): array {
	$n   = fn( $k ) => number_format_i18n( (int) ( $info[ $k ] ?? 0 ) );
	$det = array();
	if ( ! empty( $info['up_to'] ) ) {
		/* translators: %s: date. */
		$det[] = sprintf( __( 'figures up to %s', 'insightweb' ), mysql2date( 'j M Y', $info['up_to'] ) );
	}
	if ( isset( $info['rows'] ) ) {
		/* translators: %s: number of rows. */
		$det[] = sprintf( __( '%s rows', 'insightweb' ), $n( 'rows' ) );
	}
	if ( ! empty( $info['via'] ) ) {
		$det[] = (string) $info['via']; // The source used instead, e.g. Rank Math Analytics when Site Kit failed.
	}
	if ( 'roles' === $src && isset( $info['focus_kw'] ) ) {
		/* translators: 1: focus keyphrases, 2: posts, 3: terms, 4: listing names, 5: Sourcing keywords, 6: brand searches. */
		$det[] = sprintf( __( '%1$s focus keyphrases on %2$s posts and %3$s terms; %4$s listing names; %5$s Sourcing keywords; %6$s brand searches', 'insightweb' ), $n( 'focus_kw' ), $n( 'focus_posts' ), $n( 'focus_terms' ), $n( 'entities' ), $n( 'sourcing' ), $n( 'brand' ) );
		if ( ! empty( $info['prio'] ) ) {
			/* translators: 1–4: keywords at P1, P2, P3, P4. */
			$det[] = sprintf( __( 'priorities worked out over every record: P1 %1$s, P2 %2$s, P3 %3$s, P4 %4$s', 'insightweb' ), number_format_i18n( (int) ( $info['prio']['P1'] ?? 0 ) ), number_format_i18n( (int) ( $info['prio']['P2'] ?? 0 ) ), number_format_i18n( (int) ( $info['prio']['P3'] ?? 0 ) ), number_format_i18n( (int) ( $info['prio']['P4'] ?? 0 ) ) );
			// The Keywords tab and the Priorities box fold listing names (12 Nov 2026: P1 224 here, 203 there).
			$movers = aw_si_entity_movers();
			/* translators: 1–3: keywords at P1, P2, P3 with listing names folded, 4: listing names counted one by one. */
			$det[] = sprintf( __( 'with listing names folded as on the Keywords tab: P1 %1$s, P2 %2$s, P3 %3$s (only the %4$s listing names that moved most count one by one)', 'insightweb' ), number_format_i18n( aw_si_view_count( 'p1', $movers ) ), number_format_i18n( aw_si_view_count( 'p2', $movers ) ), number_format_i18n( aw_si_view_count( 'p3', $movers ) ), number_format_i18n( count( $movers ) ) );
		}
	}
	if ( 'rollup' === $src && isset( $info['daily'] ) ) {
		/* translators: 1: daily rows, 2: days, 3: monthly rows. */
		$det[] = sprintf( __( '%1$s daily rows older than %2$d days rolled into %3$s monthly rows', 'insightweb' ), $n( 'daily' ), aw_si_keep_days(), $n( 'monthly' ) );
		if ( ! empty( $info['cutoff'] ) ) {
			/* translators: %s: date. */
			$det[] = sprintf( __( 'daily rows kept from %s', 'insightweb' ), mysql2date( 'j M Y', (string) $info['cutoff'] ) );
		}
	}
	if ( isset( $info['checks'] ) && is_numeric( $info['checks'] ) ) {
		/* translators: 1: checks, 2: problems. */
		$det[] = sprintf( __( '%1$s checks, %2$s problems (Crawler access box)', 'insightweb' ), $n( 'checks' ), $n( 'problems' ) );
	}
	if ( 'bing' === $src && isset( $info['queries'] ) ) {
		/* translators: 1: searches, 2: pages. */
		$det[] = sprintf( __( '%1$s searches, %2$s pages', 'insightweb' ), $n( 'queries' ), $n( 'pages' ) );
	}
	if ( 'clarity' === $src && isset( $info['pages'] ) ) {
		/* translators: 1: pages, 2: pages flagged that day. */
		$det[] = sprintf( __( '%1$s pages, %2$s flagged that day', 'insightweb' ), $n( 'pages' ), $n( 'flagged' ) );
	}
	if ( 'ga4' === $src ) {
		if ( isset( $info['nf']['paths'] ) ) {
			/* translators: %s: number of paths. */
			$det[] = sprintf( __( '%s 404 paths', 'insightweb' ), number_format_i18n( (int) $info['nf']['paths'] ) );
		}
		if ( isset( $info['camp']['rows'] ) ) {
			/* translators: %s: number of rows. */
			$det[] = sprintf( __( '%s campaign rows', 'insightweb' ), number_format_i18n( (int) $info['camp']['rows'] ) );
		}
	}
	if ( isset( $info['rising'] ) && 0 === strpos( $src, 'rising_' ) ) {
		/* translators: %s: number of rising terms. */
		$det[] = sprintf( __( '%s rising', 'insightweb' ), $n( 'rising' ) );
	}
	if ( 'inspect' === $src && isset( $info['indexed'] ) ) {
		/* translators: %s: pages indexed. */
		$det[] = sprintf( __( '%s indexed', 'insightweb' ), $n( 'indexed' ) );
		$eta   = function_exists( 'aw_si_inspect_info_eta' ) ? aw_si_inspect_info_eta( $info ) : '';
		if ( '' !== $eta ) {
			$det[] = $eta; // why pages were not reached is the first warning (aw_si_inspect_info())
		}
	}
	if ( ! empty( $info['pasted'] ) ) {
		$det[] = __( 'pasted on the Rising tab', 'insightweb' );
	}
	if ( 'live' === $src ) {
		$det[] = __( 'monthly, by hand (Check list tab)', 'insightweb' );
	}
	foreach ( array( 'errors', 'warnings' ) as $key ) {
		$list = array_filter( array_map( 'strval', (array) ( $info[ $key ] ?? array() ) ) );
		if ( $list ) {
			/* translators: 1: number of problems, 2: the first one. */
			$det[] = sprintf( _n( '%1$s problem: %2$s', '%1$s problems, the first: %2$s', count( $list ), 'insightweb' ), number_format_i18n( count( $list ) ), wp_html_excerpt( (string) reset( $list ), 180, '…' ) );
		}
	}
	return $det;
}

/** Overview box: counts per priority and view, linked to the Keywords tab. */
function aw_si_box_priorities(): void {
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$labels = array(
		'p1'        => 'P1',
		'p2'        => __( 'P2 (gaps)', 'insightweb' ),
		'conflict'  => __( 'Conflicts', 'insightweb' ),
		'quick'     => __( 'Quick wins', 'insightweb' ),
		'notranked' => __( 'Tracked, not ranking', 'insightweb' ),
		'all'       => __( 'All keywords', 'insightweb' ),
	);
	$movers = aw_si_entity_movers(); // Listing names count as in the Keywords views: the ones that moved most, not all.
	echo '<ul>';
	foreach ( $labels as $view => $label ) {
		echo '<li><a href="' . esc_url( add_query_arg( 'view', $view, aw_si_url( 'keywords' ) ) ) . '">' . esc_html( $label ) . '</a>: ' . esc_html( number_format_i18n( aw_si_view_count( $view, $movers ) ) ) . '</li>';
	}
	echo '</ul>';
	$all = aw_si_prio_all_line();
	if ( '' !== $all ) {
		echo '<p class="description">' . esc_html( $all ) . '</p>';
	}
}

/**
 * Why the Sources row's priority counts are higher than the Priorities box and the Keywords tab (12 Nov 2026: P1 224
 * against 203): the daily calculation counts every keyword record, the views fold listing names into one row and
 * count only the AW_SI_MOVERS that moved most. Empty when no calculation is logged.
 */
function aw_si_prio_all_line(): string {
	$prio = (array) ( aw_si_runs()['roles']['info']['prio'] ?? array() );
	if ( ! $prio ) {
		return '';
	}
	/* translators: 1: listing names counted one by one, 2–4: P1, P2, P3 over every record. */
	return sprintf( __( 'Listing names are folded here, as on the Keywords tab (only the %1$s that moved most count one by one). The daily calculation in the Sources box counts every record, listing names too: P1 %2$s, P2 %3$s, P3 %4$s.', 'insightweb' ), number_format_i18n( (int) AW_SI_MOVERS ), number_format_i18n( (int) ( $prio['P1'] ?? 0 ) ), number_format_i18n( (int) ( $prio['P2'] ?? 0 ) ), number_format_i18n( (int) ( $prio['P3'] ?? 0 ) ) );
}

/** Overview box: quick wins. */
function aw_si_box_quick(): void {
	aw_si_mini_table(
		aw_si_rows( aw_si_view_sql( 'quick' ) . ' AND ' . aw_si_entity_sql(), 'impr28 DESC' ), // The same rows as the Quick wins view.
		array(
			'pos28'  => __( 'Position', 'insightweb' ),
			'impr28' => __( 'Impressions', 'insightweb' ),
			'page'   => __( 'Page', 'insightweb' ),
			'action' => __( 'Suggested action', 'insightweb' ),
		)
	);
}

/** Overview box: biggest position changes. */
function aw_si_box_movers(): void {
	aw_si_mini_table(
		aw_si_rows( 'pos28 IS NOT NULL AND pos_prev IS NOT NULL AND impr28 >= 20', 'ABS(pos_prev - pos28) DESC' ),
		array(
			'pos_prev' => __( 'Before', 'insightweb' ),
			'pos28'    => __( 'Now', 'insightweb' ),
			'impr28'   => __( 'Impressions', 'insightweb' ),
			'page'     => __( 'Page', 'insightweb' ),
		)
	);
}

/** Overview box: brand searches. */
function aw_si_box_brand(): void {
	aw_si_mini_table(
		aw_si_rows( 'roles & ' . (int) AW_SI_ROLE_BRAND, 'impr28 DESC' ),
		array(
			'pos28'    => __( 'Position', 'insightweb' ),
			'impr28'   => __( 'Impressions', 'insightweb' ),
			'clicks28' => __( 'Clicks', 'insightweb' ),
			'live_txt' => __( 'Live check', 'insightweb' ),
		)
	);
	// Sitelinks: brand searches where the live check found two or more of our links in the top 20.
	global $wpdb;
	$o     = aw_si_table( 'obs' );
	$k     = aw_si_table( 'keywords' );
	$links = aw_si_ready() ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT k.label, o.value, o.d FROM {$o} o JOIN {$k} k ON k.id = o.kw_id WHERE o.src = 'live' AND o.metric = 'ours' AND o.value >= 2 AND k.roles & %d AND o.d = (SELECT MAX(d) FROM {$o} WHERE src = 'live' AND metric = 'ours' AND kw_id = o.kw_id) ORDER BY o.value DESC LIMIT 10", AW_SI_ROLE_BRAND ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	if ( $links ) {
		$parts = array();
		foreach ( $links as $l ) {
			/* translators: 1: brand search, 2: number of our links. */
			$parts[] = sprintf( __( '%1$s (%2$d links)', 'insightweb' ), $l->label, (int) $l->value );
		}
		/* translators: %s: brand searches with their link counts. */
		echo '<p>' . esc_html( sprintf( __( 'Sitelinks in the last live check: %s.', 'insightweb' ), implode( ', ', $parts ) ) ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'AI referrals have their own box. Sitelinks come from the monthly live check (Check list tab); unlinked mentions come in a later phase.', 'insightweb' ) . '</p>';
}

/** Overview box: sessions from AI assistants, by source and by landing page. */
function aw_si_box_ai(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-28 days' )->format( 'Y-m-d' );
	$skip  = aw_si_ga4_skip_sql(); // The GA4 test day is left out here too, as in the other GA4 boxes.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed dates only.
	$by_src  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, SUM(value) n FROM {$o} WHERE src = 'ga4' AND metric = 'ai' AND period = 'day' AND d >= %s{$skip} GROUP BY txt ORDER BY n DESC", $since ) );
	$by_page = (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(value) n FROM {$o} WHERE src = 'ga4' AND metric = 'ai' AND period = 'day' AND d >= %s{$skip} GROUP BY page ORDER BY n DESC LIMIT 10", $since ) );
	// phpcs:enable
	if ( ! $by_src ) {
		echo '<p>' . esc_html__( 'No AI referrals recorded yet (GA4 runs daily at 07:20 IST).', 'insightweb' ) . '</p>';
		return;
	}
	$by_src = aw_si_ref_merge( $by_src, array( 'n' ) ); // One row per site: "ig" and l.instagram.com are both Instagram.
	usort( $by_src, fn( $a, $b ) => (float) $b->n <=> (float) $a->n );
	$parts = array();
	foreach ( $by_src as $r ) {
		$parts[] = aw_si_ref_html( (string) $r->txt ) . ': ' . esc_html( number_format_i18n( (int) $r->n ) );
	}
	echo '<p>' . implode( ' · ', $parts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Landing page', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sessions', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $by_page as $r ) {
		echo '<tr><td><a href="' . esc_url( home_url( $r->page ) ) . '" target="_blank" rel="noopener">' . esc_html( $r->page ) . '</a></td><td>' . esc_html( number_format_i18n( (int) $r->n ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Overview box: every referring site and social source, 28 days and 3 months (owner, 31 Oct 2026). */
function aw_si_box_referrers(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$o     = aw_si_table( 'obs' );
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$d28   = $today->modify( '-28 days' )->format( 'Y-m-d' );
	$keep  = aw_si_keep_days();
	$d90   = $today->modify( "-{$keep} days" )->format( 'Y-m-d' ); // Daily rows are kept this long.
	$skip  = aw_si_ga4_skip_sql();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed dates only.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, SUM(IF(metric = 'ref' AND d >= %s, value, 0)) s28, SUM(IF(metric = 'ref_eng' AND d >= %s, value, 0)) e28, SUM(IF(metric = 'ref_key' AND d >= %s, value, 0)) k28, SUM(IF(metric = 'ref', value, 0)) s90, MIN(d) since FROM {$o} WHERE src = 'ga4' AND metric IN ('ref', 'ref_eng', 'ref_key') AND period = 'day' AND d >= %s{$skip} GROUP BY txt", $d28, $d28, $d28, $d90 ) );
	// One row per site ("ig" and l.instagram.com are both Instagram), then the top 20.
	$rows = aw_si_ref_merge( $rows, array( 's28', 'e28', 'k28', 's90' ) );
	usort( $rows, fn( $a, $b ) => array( (float) $b->s28, (float) $b->s90 ) <=> array( (float) $a->s28, (float) $a->s90 ) );
	$rows = array_slice( $rows, 0, 20 );
	// Objections sent and confirmed per site, from the 28-day campaign rows (every campaign), merged the same way.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$obj = (array) $wpdb->get_results( "SELECT txt, SUM(IF(metric = 'camp_sent', value, 0)) sent, SUM(IF(metric = 'camp_conf', value, 0)) conf FROM {$o} WHERE src = 'ga4' AND period = '28d' AND metric IN ('camp_sent', 'camp_conf') GROUP BY txt" );
	$objs = array();
	foreach ( aw_si_ref_merge( $obj, array( 'sent', 'conf' ) ) as $m ) {
		$objs[ strtolower( (string) $m->label ) ] = $m;
	}
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No referrals recorded yet (GA4 runs daily at 07:20 IST).', 'insightweb' ) . '</p>';
	} else {
		$since = min( wp_list_pluck( $rows, 'since' ) );
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sessions (28 days)', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Engaged', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'All key events', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Objections sent', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirmed', 'insightweb' ) . '</th><th scope="col">' . esc_html( sprintf( /* translators: %d: days. */ __( 'Sessions (%d days)', 'insightweb' ), $keep ) ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$m = $objs[ strtolower( aw_si_referrer_label( (string) $r->txt ) ) ] ?? null;
			echo '<tr><td>' . aw_si_ref_html( (string) $r->txt ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->s28 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->e28 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->k28 ) ) . '</td><td>' . esc_html( $m ? number_format_i18n( (int) $m->sent ) : '–' ) . '</td><td>' . esc_html( $m ? number_format_i18n( (int) $m->conf ) : '–' ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->s90 ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aw_si_ref_html() escapes.
		}
		echo '</tbody></table>';
		/* translators: %s: first date with referral figures. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Figures from %s.', 'insightweb' ), wp_date( get_option( 'date_format' ), strtotime( $since ) ) ) ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'Referral and social sessions by source (GA4 channels Referral, Organic Social and Paid Social, plus Instagram, Facebook, X, LinkedIn, Reddit, WhatsApp, Telegram and Linktree). 22 Oct 2026 is left out (GA4 test events). GA4 page views changed on 25 Oct 2026, when the site moved to one GA4 tag.', 'insightweb' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'All key events counts every GA4 key event in those sessions, so one letter can count twice (objection_sent and newsletter_optin). Objections sent and confirmed are the campaign events on campaign pages, 28 days, every campaign: judge a source by these.', 'insightweb' ) . '</p>';
}

/**
 * Slug of the published campaign a site path leads to (the campaign page, an address under it, or a short link or old
 * address that redirects there), or ''.
 *
 * @param string $path  Site path.
 * @param array  $slugs Published campaign slug => title.
 */
function aw_si_campaign_of( string $path, array $slugs ): string {
	static $seen = array();
	if ( ! isset( $seen[ $path ] ) ) {
		$seen[ $path ] = '';
		foreach ( array( $path, aw_si_final_path( $path ) ) as $p ) {
			if ( preg_match( '#^/campaigns/([^/]+)#', $p, $m ) && isset( $slugs[ $m[1] ] ) ) {
				$seen[ $path ] = $m[1];
				break;
			}
		}
	}
	return $seen[ $path ];
}

/**
 * Confirm rate (confirmed ÷ sent) per source and per day, for each campaign with objections sent in the window
 * (12 Nov 2026). "Sent" is objection_sent (the letter opened in Gmail or the mail app, or copied); "confirmed" is
 * objection_confirmed (the person came back and said "Yes, I sent it"). Sources: the 10 with the most sent.
 *
 * @param array    $camp  The 28-day campaign rows (page, txt = source, metric, value).
 * @param array    $slugs Campaign slug => title.
 * @param callable $rate  fn( sent, conf ): string.
 */
function aw_si_box_campaigns_rates( array $camp, array $slugs, callable $rate ): void {
	$by = array();
	foreach ( $camp as $r ) {
		$slug = aw_si_campaign_of( (string) $r->page, $slugs );
		if ( '' !== $slug && in_array( $r->metric, array( 'camp_sent', 'camp_conf' ), true ) ) {
			$by[ $slug ][ (string) $r->txt ][ $r->metric ] = ( $by[ $slug ][ (string) $r->txt ][ $r->metric ] ?? 0 ) + (float) $r->value;
		}
	}
	$days = (array) ( aw_si_runs()['ga4']['info']['camp']['days'] ?? array() );
	$num  = fn( $v ) => number_format_i18n( (int) round( (float) $v ) );
	foreach ( $by as $slug => $srcs ) {
		uasort( $srcs, fn( $a, $b ) => ( $b['camp_sent'] ?? 0 ) <=> ( $a['camp_sent'] ?? 0 ) );
		$srcs = array_filter( $srcs, fn( $v ) => ( $v['camp_sent'] ?? 0 ) > 0 );
		if ( ! $srcs ) {
			continue;
		}
		/* translators: %s: campaign title. */
		echo '<h3>' . esc_html( sprintf( __( 'Confirm rate by source: %s', 'insightweb' ), $slugs[ $slug ] ) ) . '</h3>';
		echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Objections sent', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirmed', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirm rate', 'insightweb' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $srcs, 0, 10, true ) as $src => $v ) {
			echo '<tr><td>' . esc_html( $src ) . '</td><td>' . esc_html( $num( $v['camp_sent'] ?? 0 ) ) . '</td><td>' . esc_html( $num( $v['camp_conf'] ?? 0 ) ) . '</td><td>' . esc_html( $rate( (float) ( $v['camp_sent'] ?? 0 ), (float) ( $v['camp_conf'] ?? 0 ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$daily = array();
		foreach ( $days as $path => $rows ) {
			if ( aw_si_campaign_of( (string) $path, $slugs ) !== $slug ) {
				continue;
			}
			foreach ( (array) $rows as $d => $m ) {
				$daily[ $d ]['s'] = ( $daily[ $d ]['s'] ?? 0 ) + (int) ( $m['camp_sent'] ?? 0 );
				$daily[ $d ]['c'] = ( $daily[ $d ]['c'] ?? 0 ) + (int) ( $m['camp_conf'] ?? 0 );
			}
		}
		$daily = array_filter( $daily, fn( $v ) => $v['s'] > 0 );
		if ( ! $daily ) {
			continue; // Daily figures arrive with the next GA4 run.
		}
		krsort( $daily );
		/* translators: %s: campaign title. */
		echo '<h3>' . esc_html( sprintf( __( 'Confirm rate by day: %s', 'insightweb' ), $slugs[ $slug ] ) ) . '</h3>';
		echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Day', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Objections sent', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirmed', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirm rate', 'insightweb' ) . '</th></tr></thead><tbody>';
		foreach ( $daily as $d => $v ) {
			$ts = strtotime( (string) $d );
			echo '<tr><td>' . esc_html( $ts ? wp_date( get_option( 'date_format' ), $ts ) : (string) $d ) . '</td><td>' . esc_html( $num( $v['s'] ) ) . '</td><td>' . esc_html( $num( $v['c'] ) ) . '</td><td>' . esc_html( $rate( (float) $v['s'], (float) $v['c'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	if ( $by ) {
		echo '<p class="description">' . esc_html__( 'Sent: the letter was opened in Gmail or the mail app, or copied (objection_sent). Confirmed: the person came back to the page and said "Yes, I sent it" (objection_confirmed). Someone who sends from the mail app and never comes back is sent but not confirmed, so the true number of letters lies between the two.', 'insightweb' ) . '</p>';
	}
}

/**
 * Overview box: what X, all social sources and all sources bring to each campaign (owner, 2 Nov 2026). Landed sessions
 * and key events come from the daily referral rows (ref, ref_key); viewed sessions, objections sent and confirmed from
 * the 28-day campaign rows (camp_view, camp_sent, camp_conf).
 */
function aw_si_box_campaigns(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$slugs = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'aw_campaign',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	) as $post ) {
		$slugs[ $post->post_name ] = get_the_title( $post );
	}
	if ( ! $slugs ) {
		echo '<p>' . esc_html__( 'No published campaigns.', 'insightweb' ) . '</p>';
		return;
	}
	$o    = aw_si_table( 'obs' );
	$skip = aw_si_ga4_skip_sql();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed conditions only.
	$camp = (array) $wpdb->get_results( "SELECT page, txt, metric, value, d FROM {$o} WHERE src = 'ga4' AND period = '28d' AND metric IN ('camp_view', 'camp_sent', 'camp_conf')" );
	// The same 28 days as the campaign rows (to yesterday until they arrive).
	$end   = $camp ? (string) $camp[0]->d : ( new DateTimeImmutable( 'yesterday', wp_timezone() ) )->format( 'Y-m-d' );
	$start = ( new DateTimeImmutable( $end, wp_timezone() ) )->modify( '-27 days' )->format( 'Y-m-d' );
	$ref   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, txt, metric, SUM(value) value, MIN(d) since FROM {$o} WHERE src = 'ga4' AND period = 'day' AND metric IN ('ref', 'ref_key') AND d >= %s AND d <= %s{$skip} GROUP BY page, txt, metric", $start, $end ) );
	// phpcs:enable
	$x      = array_map( 'strtolower', aw_si_x_sources() );
	$social = array_map( 'strtolower', aw_si_social_sources() );
	$cols   = array(
		'ref'       => 'landed',
		'ref_key'   => 'key',
		'camp_view' => 'viewed',
		'camp_sent' => 'sent',
		'camp_conf' => 'conf',
	);
	$t      = array();
	$since  = '';
	foreach ( array_merge( $ref, $camp ) as $r ) {
		$slug = aw_si_campaign_of( (string) $r->page, $slugs );
		if ( '' === $slug ) {
			continue;
		}
		$col     = $cols[ $r->metric ];
		$buckets = array( 'all' );
		if ( in_array( $r->txt, $social, true ) ) {
			$buckets[] = 'social';
		}
		if ( in_array( $r->txt, $x, true ) ) {
			$buckets[] = 'x';
		}
		foreach ( $buckets as $b ) {
			$t[ $slug ][ $b ][ $col ] = ( $t[ $slug ][ $b ][ $col ] ?? 0 ) + (float) $r->value;
		}
		if ( isset( $r->since ) && ( '' === $since || $r->since < $since ) ) {
			$since = (string) $r->since;
		}
	}
	if ( ! $t ) {
		echo '<p>' . esc_html__( 'No campaign visits recorded yet (GA4 runs daily at 07:20 IST).', 'insightweb' ) . '</p>';
		return;
	}
	uasort( $t, fn( $a, $b ) => ( ( $b['all']['viewed'] ?? 0 ) <=> ( $a['all']['viewed'] ?? 0 ) ) ?: ( ( $b['all']['landed'] ?? 0 ) <=> ( $a['all']['landed'] ?? 0 ) ) );
	$rows = array(
		'x'      => __( 'X', 'insightweb' ),
		'social' => __( 'All social', 'insightweb' ),
		'all'    => __( 'All sources', 'insightweb' ),
	);
	$num  = fn( $v ) => null === $v ? '–' : number_format_i18n( (int) round( (float) $v ) );
	echo '<table class="widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Campaign', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'From', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sessions (landed)', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Key events', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Sessions (viewed)', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Objections sent', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirmed', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Confirm rate', 'insightweb' ) . '</th></tr></thead><tbody>';
	$rate = fn( $sent, $conf ) => $sent > 0 ? number_format_i18n( 100 * $conf / $sent, 0 ) . '%' : '–';
	foreach ( $t as $slug => $b ) {
		$first = true;
		foreach ( $rows as $key => $label ) {
			$v    = $b[ $key ] ?? array();
			$cell = $first ? '<a href="' . esc_url( home_url( '/campaigns/' . $slug . '/' ) ) . '" target="_blank" rel="noopener">' . esc_html( $slugs[ $slug ] ) . '</a>' : '';
			$all  = 'all' === $key; // Landed sessions and key events are stored for referral and social sources only.
			echo '<tr><td>' . $cell . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $all ? '–' : $num( $v['landed'] ?? 0 ) ) . '</td><td>' . esc_html( $all ? '–' : $num( $v['key'] ?? 0 ) ) . '</td><td>' . esc_html( $camp ? $num( $v['viewed'] ?? 0 ) : '–' ) . '</td><td>' . esc_html( $camp ? $num( $v['sent'] ?? 0 ) : '–' ) . '</td><td>' . esc_html( $camp ? $num( $v['conf'] ?? 0 ) : '–' ) . '</td><td>' . esc_html( $camp ? $rate( (float) ( $v['sent'] ?? 0 ), (float) ( $v['conf'] ?? 0 ) ) : '–' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $cell is escaped above.
			$first = false;
		}
	}
	echo '</tbody></table>';
	aw_si_box_campaigns_rates( $camp, $slugs, $rate );
	$from = $since && $since > $start ? $since : $start;
	/* translators: 1: first day, 2: last day. */
	echo '<p class="description">' . esc_html( sprintf( __( '%1$s to %2$s. X: t.co, x.com and twitter.com. All social includes X.', 'insightweb' ), wp_date( get_option( 'date_format' ), strtotime( $from ) ), wp_date( get_option( 'date_format' ), strtotime( $end ) ) ) ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Sessions (landed) and key events: sessions that started on the campaign page or a short link to it, from referral and social sources (direct, search and email visits are not in these rows). Key events count every GA4 key event in those sessions, not only objections.', 'insightweb' ) . '</p>';
	if ( $camp ) {
		echo '<p class="description">' . esc_html__( 'Sessions (viewed), objections sent and confirmed: sessions with a campaign_view, and objection_sent and objection_confirmed events, on the campaign page, by the session\'s source, from every source. 22 Oct 2026 is left out (GA4 test events).', 'insightweb' ) . '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'Sessions (viewed), objections sent and confirmed arrive with the next GA4 run (daily at 07:20 IST).', 'insightweb' ) . '</p>';
	}
}

/**
 * Whether a site path opens a published post, page or listing now (no request is made: WordPress's own URL lookup).
 *
 * @param string $path Site path.
 */
function aw_si_path_loads( string $path ): bool {
	if ( '' === $path || '/' !== $path[0] || preg_match( '/[^\x21-\x7e]|%[0-9a-f]{2}/i', $path ) ) {
		return false; // Spaces, invisible characters and encoded fragments (%23) never match a page.
	}
	$id = url_to_postid( home_url( $path ) );
	return $id > 0 && 'publish' === get_post_status( $id );
}

/** Overview box: the 20 paths with the most 404_view events in the stored 28-day window, and where each goes now. */
function aw_si_box_nf(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	$o = aw_si_table( 'obs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results( "SELECT txt, value, d FROM {$o} WHERE src = 'ga4' AND metric = 'nf' AND period = '28d' ORDER BY value DESC, txt LIMIT 22" );
	$rows = array_slice( array_values( array_filter( $rows, fn( $r ) => ! aw_si_nf_is_test( (string) $r->txt ) ) ), 0, 20 ); // test addresses stored before 12 Nov
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No 404s recorded yet (GA4 runs daily at 07:20 IST).', 'insightweb' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Path', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Views', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Status', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$to = aw_si_final_path( (string) $r->txt );
		if ( untrailingslashit( $to ) !== untrailingslashit( (string) $r->txt ) ) {
			/* translators: %s: path the redirect ends at. */
			$status = sprintf( esc_html__( 'Redirects to %s', 'insightweb' ), '<a href="' . esc_url( home_url( $to ) ) . '" target="_blank" rel="noopener">' . esc_html( $to ) . '</a>' );
		} elseif ( aw_si_path_loads( (string) $r->txt ) ) {
			$status = esc_html__( 'Loads now (the 404s were earlier)', 'insightweb' );
		} else {
			$status = esc_html__( 'Still 404', 'insightweb' );
		}
		echo '<tr><td>' . esc_html( $r->txt ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->value ) ) . '</td><td>' . $status . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '</tbody></table>';
	$info = aw_si_runs()['ga4']['info']['nf'] ?? array();
	$dim  = 'pagePath' === ( $info['dim'] ?? '' ) ? __( 'page path (some events in the window were sent before the "path" dimension was registered; on a 404 page it is the same address)', 'insightweb' ) : __( 'the event\'s path parameter', 'insightweb' );
	/* translators: 1: last day of the window, 2: what the path comes from. */
	echo '<p class="description">' . esc_html( sprintf( __( '404_view events in the 28 days to %1$s, by %2$s. 22 Oct 2026 (GA4 test events) and the test addresses /no-such-page/ and /no-such-page-xyz/ are left out. Status follows Rank Math redirects (exact and regex) and old slugs; "Loads now" means the address opens a published page today (the 404s came before it existed or during an update); "Still 404" means neither, not that the address was checked again.', 'insightweb' ), wp_date( get_option( 'date_format' ), strtotime( (string) $rows[0]->d ) ), $dim ) ) . '</p>';
}

/** Overview box: content gaps (the keyword map's demand gaps and every P2 keyword), with the suggested page type. */
function aw_si_box_gaps(): void {
	$gaps = aw_si_searches_gaps();
	if ( ! $gaps ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'insightweb' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Gap', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Searches', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $gaps as $g ) {
		echo '<tr><td>' . esc_html( $g[0] ) . '</td><td>' . esc_html( $g[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Overview box: what people search on the site (WordPress search, Directory, GA4 site search), counts only. */
function aw_si_box_site_search(): void {
	$rows = aw_si_site_searches( 28, 15 );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing yet: searches are counted from today.', 'insightweb' ) . '</p>';
		return;
	}
	$where = array(
		'wp'       => __( 'Site search (WordPress or GA4, the higher count)', 'insightweb' ),
		'dir-q'    => __( 'Directory search', 'insightweb' ),
		'dir-pin'  => __( 'Directory PIN code', 'insightweb' ),
		'dir-near' => __( 'Near me (district found)', 'insightweb' ),
	);
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Term', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Where', 'insightweb' ) . '</th><th scope="col">' . esc_html__( 'Searches', 'insightweb' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->txt ) . '</td><td>' . esc_html( $where[ $r->metric ] ?? $r->metric ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->n ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Minimum counts: a search answered from the page cache never reaches WordPress, so it is not counted (mostly Directory searches; WordPress search results are normally not cached). Daily counts per term only: no IP address, user or session is stored, and terms that look like emails or phone numbers are dropped.', 'insightweb' ) . '</p>';
}

/**
 * A Clarity share as "12.5%", or a dash when missing.
 *
 * @param mixed $v Per cent.
 */
function aw_si_clarity_pct( $v ): string {
	return is_numeric( $v ) ? number_format_i18n( (float) $v, 1 ) . '%' : '–';
}

/**
 * A Clarity time as "34 s", or a dash when missing.
 *
 * @param mixed $v Seconds.
 */
function aw_si_clarity_secs( $v ): string {
	/* translators: %s: seconds. */
	return is_numeric( $v ) ? sprintf( __( '%s s', 'insightweb' ), number_format_i18n( round( (float) $v ) ) ) : '–';
}

/**
 * Pages per session with one decimal, or a dash when missing.
 *
 * @param mixed $v Pages per session.
 */
function aw_si_clarity_num( $v ): string {
	return is_numeric( $v ) ? number_format_i18n( (float) $v, 1 ) : '–';
}

/**
 * Sessions by device as "Mobile / PC / Tablet" figures, e.g. "120 / 80 / 3".
 *
 * @param mixed $devices device => sessions.
 */
function aw_si_clarity_devices_txt( $devices ): string {
	$by = array_change_key_case( (array) $devices, CASE_LOWER );
	$n  = array();
	foreach ( array( 'mobile', 'pc', 'tablet' ) as $d ) {
		$n[] = number_format_i18n( (int) ( $by[ $d ] ?? 0 ) );
	}
	return implode( ' / ', $n );
}

/** The line shown when Clarity has nothing to show: no token, or no data yet. */
function aw_si_clarity_empty_line(): string {
	if ( ! function_exists( 'aw_si_clarity_on' ) || ! aw_si_clarity_on() ) {
		return __( 'No Clarity token: add it under Animal Writes → Keys.', 'insightweb' );
	}
	return __( 'No Clarity data yet; it imports daily at 06:30.', 'insightweb' );
}

/** Overview box: Clarity totals for 28 days (clarity.php), linked to the Clarity tab. */
function aw_si_box_clarity(): void {
	$s = function_exists( 'aw_si_clarity_summary' ) && function_exists( 'aw_si_clarity_on' ) && aw_si_clarity_on() ? (array) aw_si_clarity_summary( 28 ) : array();
	if ( empty( $s['days'] ) ) {
		echo '<p>' . esc_html( aw_si_clarity_empty_line() ) . '</p>';
		return;
	}
	$flagged = '<a href="' . esc_url( add_query_arg( 'view', 'flagged', aw_si_url( 'clarity' ) ) ) . '">' . esc_html( number_format_i18n( (int) ( $s['flagged'] ?? 0 ) ) ) . '</a>';
	$rows    = array(
		__( 'Sessions', 'insightweb' )                      => esc_html( number_format_i18n( (int) ( $s['sessions'] ?? 0 ) ) ),
		__( 'Pages per session', 'insightweb' )             => esc_html( aw_si_clarity_num( $s['pps'] ?? null ) ),
		__( 'Scroll depth', 'insightweb' )                  => esc_html( aw_si_clarity_pct( $s['scroll'] ?? null ) ),
		__( 'Active time', 'insightweb' )                   => esc_html( aw_si_clarity_secs( $s['active'] ?? null ) ),
		__( 'Dead clicks', 'insightweb' )                   => esc_html( aw_si_clarity_pct( $s['dead'] ?? null ) ),
		__( 'Rage clicks', 'insightweb' )                   => esc_html( aw_si_clarity_pct( $s['rage'] ?? null ) ),
		__( 'Quick backs', 'insightweb' )                   => esc_html( aw_si_clarity_pct( $s['quickback'] ?? null ) ),
		__( 'Excessive scrolling', 'insightweb' )           => esc_html( aw_si_clarity_pct( $s['xscroll'] ?? null ) ),
		__( 'Script errors', 'insightweb' )                 => esc_html( aw_si_clarity_pct( $s['jserror'] ?? null ) ),
		__( 'Pages flagged (3+ days)', 'insightweb' )       => $flagged,
		__( 'Mobile / PC / Tablet sessions', 'insightweb' ) => esc_html( aw_si_clarity_devices_txt( $s['devices'] ?? array() ) ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '</tbody></table>';
	$days = (int) $s['days'];
	/* translators: %s: number of days. */
	$line = sprintf( _n( '%s day of data', '%s days of data', $days, 'insightweb' ), number_format_i18n( $days ) );
	if ( ! empty( $s['last'] ) ) {
		/* translators: %s: date. */
		$line .= ' · ' . sprintf( __( 'last day %s', 'insightweb' ), mysql2date( 'j M Y', (string) $s['last'] ) );
	}
	echo '<p class="description">' . esc_html( $line ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( aw_si_url( 'clarity' ) ) . '">' . esc_html__( 'Clarity', 'insightweb' ) . '</a></p>';
}

// Clarity tab: after Pages when Pages is there, else at the end.
add_filter(
	'aw_si_tabs',
	function ( $tabs ) {
		$tabs = (array) $tabs;
		$out  = array();
		foreach ( $tabs as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'pages' === $key ) {
				$out['clarity'] = __( 'Clarity', 'insightweb' );
			}
		}
		if ( ! isset( $out['clarity'] ) ) {
			$out['clarity'] = __( 'Clarity', 'insightweb' );
		}
		return $out;
	},
	20
);

add_filter(
	'set_screen_option_aw_si_clarity_per_page',
	function ( $keep, $option, $value ) {
		return max( 10, min( 500, (int) $value ) );
	},
	10,
	3
);

add_action(
	'aw_si_load_tab_clarity',
	function () {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Pages per page', 'insightweb' ),
				'default' => 50,
				'option'  => 'aw_si_clarity_per_page',
			)
		);
		require_once __DIR__ . '/class-aw-si-clarity-table.php';
	}
);

add_action(
	'aw_si_tab_clarity',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! class_exists( 'AW_SI_Clarity_Table' ) ) {
			return;
		}
		$table = new AW_SI_Clarity_Table();
		$table->prepare_items();
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="clarity">';
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		if ( $view ) {
			echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		}
		$table->search_box( __( 'Search pages', 'insightweb' ), 'aw-si-clarity' );
		$table->display();
		echo '</form>';
	}
);

/**
 * Settings tab rows: schedule, keys, seed, run now, imports, delete data. The Settings screen saves the numbers
 * itself and draws these rows from this output (insightweb_settings_more_parts()).
 */
function aw_si_settings_tab(): void {
	$seeded = (int) get_option( 'aw_si_seeded' );
	do_action( 'aw_si_settings_notices' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Schedule', 'insightweb' ) . '</th><td><p>' . esc_html( sprintf( /* translators: 1: days of daily figures stored, 2: the same. */ __( 'Daily at 06:30 IST: Clarity (once the token is set). Daily at 07:10 IST: Search Console (the last 4 days again, plus up to 15 older days until %1$d days are stored), keyword roles from Rank Math and Sourcing, then priorities; at 07:15 IST Discover and Google News; from 07:40 IST URL Inspection (up to 2,000 pages a day, not indexed and never checked first). Daily at 07:20 IST: GA4 (site search, AI referrals, all referral and social sources, organic landing pages; the last 3 days; 404s and campaign events for the last 28 days). Daily at 08:10 IST: Google Alerts feeds (once set on Keys). Sundays at 06:00 IST: PageSpeed (lab scores and Chrome users\' figures) for the key pages and every campaign page, a few at a time. Mondays at 06:45 IST: Bing crawl figures; at 08:45 IST crawler access from the server; at 09:15 IST from outside (workers). Daily at 23:30 IST: rows older than %2$d days become monthly totals. The 2nd of each month at 07:40 IST: Bing Webmaster (links to us, and clicks and impressions by search and by page; once the key is set); once a month Bing search volumes for the top keywords. Searches on the site are counted as they happen.', 'insightweb' ), aw_si_keep_days(), aw_si_keep_days() ) ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Keys', 'insightweb' ) . '</th><td><ul>';
	foreach ( array( 'AW_CLARITY_TOKEN' => __( 'Clarity', 'insightweb' ), 'AW_BING_WEBMASTER_KEY' => __( 'Bing Webmaster', 'insightweb' ), 'AW_PSI_KEY' => __( 'PageSpeed (optional)', 'insightweb' ) ) as $const => $label ) {
		$set = '' !== aw_si_key( $const );
		echo '<li><span class="dashicons dashicons-' . ( $set ? 'yes-alt' : 'minus' ) . '" aria-hidden="true"></span> ' . esc_html( $label . ': ' . ( $set ? __( 'set', 'insightweb' ) : __( 'not set', 'insightweb' ) ) ) . '</li>';
	}
	echo '</ul>';
	if ( function_exists( 'aw_key' ) && current_user_can( 'manage_options' ) ) {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=aw-keys' ) ) . '">' . esc_html__( 'Animal Writes → Keys', 'insightweb' ) . '</a></p>';
	}
	echo '<p class="description">' . esc_html__( 'Keys are set under Animal Writes → Keys (or as wp-config.php constants, which win); their values are never shown.', 'insightweb' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Keyword map seed', 'insightweb' ) . '</th><td><p>';
	echo $seeded
		/* translators: %s: date. */
		? esc_html( sprintf( __( 'Seeded once on %s from docs/seo/keyword-map.md and the 21 Oct rankings. The table here is the source of truth now.', 'insightweb' ), wp_date( 'j M Y, H:i', $seeded ) ) )
		: esc_html__( 'Not seeded yet: the first run seeds it.', 'insightweb' );
	echo '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Run now', 'insightweb' ) . '</th><td>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_run">';
	wp_nonce_field( 'aw_si_run' );
	echo '<p><button type="submit" name="what" value="daily" class="button">' . esc_html__( 'Run the daily update', 'insightweb' ) . '</button> ';
	echo '<button type="submit" name="what" value="rollup" class="button">' . esc_html( sprintf( /* translators: %d: days. */ __( 'Run the %d-day rollup', 'insightweb' ), aw_si_keep_days() ) ) . '</button> ';
	echo '<button type="submit" name="what" value="ga4" class="button">' . esc_html__( 'Run GA4', 'insightweb' ) . '</button> ';
	echo '<button type="submit" name="what" value="psi" class="button">' . esc_html__( 'Start PageSpeed', 'insightweb' ) . '</button>';
	if ( aw_si_bing_on() ) {
		echo ' <button type="submit" name="what" value="bing" class="button">' . esc_html__( 'Run Bing Webmaster', 'insightweb' ) . '</button>';
		if ( function_exists( 'aw_si_bing_kw_run' ) ) {
			echo ' <button type="submit" name="what" value="bing_kw" class="button">' . esc_html__( 'Run Bing keyword volumes', 'insightweb' ) . '</button>';
		}
	}
	if ( aw_si_clarity_on() ) {
		echo ' <button type="submit" name="what" value="clarity" class="button">' . esc_html__( 'Run Clarity (uses 2 of its 10 daily calls)', 'insightweb' ) . '</button>';
	}
	echo '</p>';
	echo '</form>';
	echo '<p class="description">' . esc_html__( 'The daily update can take a minute on the first run (it fetches up to 19 days of Search Console).', 'insightweb' ) . '</p></td></tr>';
	do_action( 'aw_si_settings_rows' ); // More rows: imports and paste forms (imports.php).
	echo '<tr><th scope="row"><label for="aw-si-confirm">' . esc_html__( 'Delete Search Insights data', 'insightweb' ) . '</label></th><td>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_delete">';
	wp_nonce_field( 'aw_si_delete' );
	echo '<p>' . esc_html__( 'Drops the Search Insights tables and settings. Deactivating or deleting the plugin never does this. Type DELETE to confirm.', 'insightweb' ) . '</p>';
	echo '<input type="text" id="aw-si-confirm" name="confirm" class="regular-text" autocomplete="off"> ';
	submit_button( __( 'Delete data', 'insightweb' ), 'delete', 'submit', false );
	echo '</form></td></tr>';
	echo '</tbody></table>';
}

add_action(
	'admin_post_aw_si_run',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_run' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : '';
		if ( 'rollup' === $what ) {
			aw_si_rollup_cron();
		} elseif ( 'ga4' === $what ) {
			aw_si_ga4_run();
		} elseif ( 'psi' === $what ) {
			aw_si_psi_start(); // The first calls now; the rest every five minutes.
		} elseif ( 'clarity' === $what ) {
			aw_si_clarity_run();
		} elseif ( 'bing' === $what ) {
			aw_si_bing_run();
		} elseif ( 'bing_kw' === $what && function_exists( 'aw_si_bing_kw_run' ) ) {
			aw_si_bing_kw_run(); // Monthly job; by hand before its first scheduled run (12 Nov 2026).
		} else {
			aw_si_daily( true );
		}
		wp_safe_redirect( add_query_arg( 'ran', 1, aw_si_return_url( 'settings' ) ) );
		exit;
	}
);

add_action(
	'admin_post_aw_si_delete',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_delete' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'insightweb' ), 403 );
		}
		$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'DELETE' !== $confirm ) {
			wp_die(
				esc_html__( 'Not deleted: type DELETE to confirm.', 'insightweb' ),
				'',
				array(
					'back_link' => true,
					'response'  => 400,
				)
			);
		}
		aw_si_drop_all();
		wp_safe_redirect( aw_si_return_url( 'settings' ) );
		exit;
	}
);

/** The lazy-loaded list table class. */
add_action(
	'admin_init',
	function () {
		require_once __DIR__ . '/class-aw-si-keywords-table.php';
	}
);
