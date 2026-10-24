<?php
/**
 * Tools → Search Insights (owner addendum, 24 Oct: core WordPress only, no custom CSS). Tabs: Overview (postboxes),
 * Keywords (list table), Searches (the Search demand screen) and Settings (form-table: run now, delete data).
 * Each tab starts with a notice per source: last updated, or the error with the date of the data still shown.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AW_SI_SLUG = 'aw-search-insights';

/** Same people as Sourcing and Search demand. */
function aw_si_cap(): string {
	return function_exists( 'aw_sd_cap' ) ? aw_sd_cap() : 'manage_options';
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
		admin_url( 'tools.php' )
	);
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
 * Tabs other plugins add with the aw_si_tabs filter (key => label), e.g. AW Sourcing's per-keyword view. Each renders
 * on the action aw_si_tab_{key}; the action aw_si_load_tab_{key} runs before output (screen options, redirects).
 */
function aw_si_extra_tabs(): array {
	$out = array();
	foreach ( (array) apply_filters( 'aw_si_tabs', array() ) as $key => $label ) {
		$key = sanitize_key( (string) $key );
		if ( '' !== $key && ! in_array( $key, array( 'overview', 'keywords', 'searches', 'settings' ), true ) ) {
			$out[ $key ] = (string) $label;
		}
	}
	return $out;
}

/** The current tab. */
function aw_si_tab(): string {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
	return in_array( $tab, array_merge( array( 'overview', 'keywords', 'searches', 'settings' ), array_keys( aw_si_extra_tabs() ) ), true ) ? $tab : 'overview';
}

add_action(
	'admin_menu',
	function () {
		$hook = add_management_page( __( 'Search Insights', 'aw-seo-extras' ), __( 'Search Insights', 'aw-seo-extras' ), aw_si_cap(), AW_SI_SLUG, 'aw_si_screen' );
		if ( $hook ) {
			add_action( 'load-' . $hook, 'aw_si_load' );
		}
		// The Search demand screen is the Searches tab now: one menu item, and the old address opens that tab.
		remove_submenu_page( 'tools.php', 'aw-search-demand' );
		add_action(
			'load-tools_page_aw-search-demand',
			function () {
				$keep = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'refreshed', 'refresh_failed' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification -- message flags only.
				wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $keep ) ), aw_si_url( 'searches' ) ), 301 );
				exit;
			}
		);
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

/** Before output: screen options, meta boxes, and priority overrides (then redirect). */
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
				'label'   => __( 'Keywords per page', 'aw-seo-extras' ),
				'default' => 50,
				'option'  => 'aw_si_keywords_per_page',
			)
		);
		aw_si_handle_overrides();
	}
	if ( 'overview' === $tab ) {
		$screen = get_current_screen();
		add_meta_box( 'aw-si-sources', __( 'Sources', 'aw-seo-extras' ), 'aw_si_box_sources', $screen, 'normal' );
		add_meta_box( 'aw-si-priorities', __( 'Priorities', 'aw-seo-extras' ), 'aw_si_box_priorities', $screen, 'normal' );
		add_meta_box( 'aw-si-quick', __( 'Quick wins (position 8–20, at least 10 impressions)', 'aw-seo-extras' ), 'aw_si_box_quick', $screen, 'normal' );
		add_meta_box( 'aw-si-movers', __( 'Movers (28 days against the 28 before)', 'aw-seo-extras' ), 'aw_si_box_movers', $screen, 'normal' );
		add_meta_box( 'aw-si-brand', __( 'Brand', 'aw-seo-extras' ), 'aw_si_box_brand', $screen, 'normal' );
		add_meta_box( 'aw-si-ai', __( 'AI referrals (GA4, 28 days)', 'aw-seo-extras' ), 'aw_si_box_ai', $screen, 'normal' );
		add_meta_box( 'aw-si-site-search', __( 'Searches on our site (28 days)', 'aw-seo-extras' ), 'aw_si_box_site_search', $screen, 'normal' );
		wp_enqueue_script( 'postbox' );
		wp_add_inline_script( 'postbox', 'jQuery( function () { postboxes.add_postbox_toggles( pagenow ); } );' );
	}
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
		wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
	}
	$ids = array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST['kw'] ?? array() ) ) );
	if ( ! $ids ) {
		wp_safe_redirect( add_query_arg( 'none', 1, aw_si_url( 'keywords' ) ) );
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
		echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Search Insights is off: its data was deleted on %s and nothing runs on schedule. Settings → Run the daily update starts it again.', 'aw-seo-extras' ), wp_date( 'j M Y, H:i', $off ) ) ) . '</p></div>';
		return;
	}
	$runs  = aw_si_runs();
	$parts = array();
	foreach ( aw_si_sources() as $src => $s ) {
		$r   = $runs[ $src ] ?? array();
		$ok  = (int) ( $r['ok'] ?? 0 );
		$err = aw_si_run_problem( $src );
		/* translators: 1: source, 2: date and time. */
		$parts[] = $ok ? sprintf( __( '%1$s: %2$s', 'aw-seo-extras' ), $s['label'], wp_date( 'j M, H:i', $ok ) ) : sprintf( __( '%s: not run yet', 'aw-seo-extras' ), $s['label'] );
		if ( $err ) {
			/* translators: 1: source, 2: the problem, 3: date of the data still shown. */
			$msg = sprintf( __( '%1$s: %2$s The figures shown are from %3$s.', 'aw-seo-extras' ), $s['label'], $err, $ok ? wp_date( 'j M, H:i', $ok ) : __( 'no earlier run', 'aw-seo-extras' ) );
			echo '<div class="notice notice-error inline"><p><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $msg ) . '</p></div>';
		} elseif ( $ok && aw_si_stale( $src ) ) {
			/* translators: %s: source. */
			echo '<div class="notice notice-warning inline"><p><span class="dashicons dashicons-clock" aria-hidden="true"></span> ' . esc_html( sprintf( __( '%s has not updated on schedule.', 'aw-seo-extras' ), $s['label'] ) ) . '</p></div>';
		}
		if ( ! empty( $r['info']['truncated'] ) ) {
			$days = implode( ', ', array_keys( (array) $r['info']['truncated'] ) );
			$msg  = 'gsc' === $src
				/* translators: %s: dates. */
				? __( 'Search Console hit the 5,000-row maximum on %s even after fetching the busiest pages one by one, so the rarest searches of those days may be missing.', 'aw-seo-extras' )
				/* translators: %s: dates. */
				: __( 'GA4 returned its row maximum on %s, so the smallest pages or terms of those days may be missing.', 'aw-seo-extras' );
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( $msg, $days ) ) . '</p></div>';
		}
	}
	echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Last updated', 'aw-seo-extras' ) . ' — ' . esc_html( implode( ' · ', $parts ) ) . '</p></div>';
}

/** The Search Insights screen: tabs, source notices, then the tab. */
function aw_si_screen(): void {
	if ( ! current_user_can( aw_si_cap() ) ) {
		return;
	}
	$tab = aw_si_tab();
	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Search Insights', 'aw-seo-extras' ) . '</h1><hr class="wp-header-end">';
	echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Search Insights sections', 'aw-seo-extras' ) . '">';
	$tabs = array_merge(
		array(
			'overview' => __( 'Overview', 'aw-seo-extras' ),
			'keywords' => __( 'Keywords', 'aw-seo-extras' ),
			'searches' => __( 'Searches', 'aw-seo-extras' ),
		),
		aw_si_extra_tabs(),
		array( 'settings' => __( 'Settings', 'aw-seo-extras' ) )
	);
	foreach ( $tabs as $key => $label ) {
		echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( aw_si_url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	if ( 'searches' === $tab ) {
		aw_sd_screen( true ); // The Search demand screen, inside Search Insights; it shows its own refresh notices.
		echo '</div>';
		return;
	}
	aw_si_source_notices();
	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Priority saved.', 'aw-seo-extras' ) . '</p></div>';
	}
	if ( isset( $_GET['none'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No keyword was selected, so nothing changed.', 'aw-seo-extras' ) . '</p></div>';
	}
	if ( isset( $_GET['ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- message only.
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Run finished. See the notices above for each source.', 'aw-seo-extras' ) . '</p></div>';
	}
	if ( 'keywords' === $tab ) {
		$table = new AW_SI_Keywords_Table();
		$table->prepare_items();
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( AW_SI_SLUG ) . '"><input type="hidden" name="tab" value="keywords">';
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- navigation only.
		if ( $view ) {
			echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		}
		$table->search_box( __( 'Search keywords', 'aw-seo-extras' ), 'aw-si-kw' );
		$table->display();
		echo '</form>';
	} elseif ( 'settings' === $tab ) {
		aw_si_settings_tab();
	} elseif ( isset( aw_si_extra_tabs()[ $tab ] ) ) {
		do_action( 'aw_si_tab_' . $tab );
	} else {
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		echo '<div id="dashboard-widgets-wrap"><div id="dashboard-widgets" class="metabox-holder"><div class="postbox-container">';
		do_meta_boxes( get_current_screen(), 'normal', null );
		echo '</div></div></div>';
	}
	echo '</div>';
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
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Keyword', 'aw-seo-extras' ) . '</th>';
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

/** Overview box: each source's last update and state. */
function aw_si_box_sources(): void {
	$runs = aw_si_runs();
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Last updated', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'State', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Detail', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( aw_si_sources() as $src => $s ) {
		$r    = $runs[ $src ] ?? array();
		$ok   = (int) ( $r['ok'] ?? 0 );
		$err  = aw_si_run_problem( $src );
		$icon = $err ? 'dismiss' : ( ! $ok ? 'minus' : ( aw_si_stale( $src ) ? 'clock' : 'yes-alt' ) );
		$word = $err ? __( 'Failed', 'aw-seo-extras' ) : ( ! $ok ? __( 'Not run yet', 'aw-seo-extras' ) : ( aw_si_stale( $src ) ? __( 'Late', 'aw-seo-extras' ) : __( 'OK', 'aw-seo-extras' ) ) );
		$info = (array) ( $r['info'] ?? array() );
		$det  = array();
		if ( ! empty( $info['up_to'] ) ) {
			/* translators: %s: date. */
			$det[] = sprintf( __( 'figures up to %s', 'aw-seo-extras' ), mysql2date( 'j M Y', $info['up_to'] ) );
		}
		if ( isset( $info['rows'] ) ) {
			/* translators: %s: number of rows. */
			$det[] = sprintf( __( '%s rows', 'aw-seo-extras' ), number_format_i18n( (int) $info['rows'] ) );
		}
		if ( $err ) {
			$det[] = $err;
		}
		echo '<tr><td>' . esc_html( $s['label'] ) . '</td><td>' . esc_html( $ok ? wp_date( 'j M Y, H:i', $ok ) : __( 'Never', 'aw-seo-extras' ) ) . '</td><td><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ' . esc_html( $word ) . '</td><td>' . esc_html( implode( '; ', $det ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Overview box: counts per priority and view, linked to the Keywords tab. */
function aw_si_box_priorities(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$t   = aw_si_table( 'keywords' );
	$eff = "IF(priority_override <> '', priority_override, priority)";
	$sql = array(
		'p1'        => array( 'P1', "{$eff} = 'P1'" ),
		'p2'        => array( __( 'P2 (gaps)', 'aw-seo-extras' ), "{$eff} = 'P2'" ),
		'conflict'  => array( __( 'Conflicts', 'aw-seo-extras' ), "status = 'conflict'" ),
		'quick'     => array( __( 'Quick wins', 'aw-seo-extras' ), 'pos28 BETWEEN 8 AND 20 AND impr28 >= ' . (int) AW_SI_QUICK_MIN ),
		'notranked' => array( __( 'Tracked, not ranking', 'aw-seo-extras' ), 'roles & ' . (int) AW_SI_ROLE_TRACKED . ' AND impr28 = 0' ),
		'all'       => array( __( 'All keywords', 'aw-seo-extras' ), '1=1' ),
	);
	$ent = aw_si_entity_sql(); // Listing names count as in the Keywords views: the 20 that moved most, not all.
	echo '<ul>';
	foreach ( $sql as $view => $v ) {
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$v[1]} AND {$ent}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- fixed conditions.
		echo '<li><a href="' . esc_url( add_query_arg( 'view', $view, aw_si_url( 'keywords' ) ) ) . '">' . esc_html( $v[0] ) . '</a>: ' . esc_html( number_format_i18n( $n ) ) . '</li>';
	}
	echo '</ul>';
}

/** Overview box: quick wins. */
function aw_si_box_quick(): void {
	aw_si_mini_table(
		aw_si_rows( 'pos28 BETWEEN 8 AND 20 AND impr28 >= ' . (int) AW_SI_QUICK_MIN, 'impr28 DESC' ),
		array(
			'pos28'  => __( 'Position', 'aw-seo-extras' ),
			'impr28' => __( 'Impressions', 'aw-seo-extras' ),
			'page'   => __( 'Page', 'aw-seo-extras' ),
			'action' => __( 'Suggested action', 'aw-seo-extras' ),
		)
	);
}

/** Overview box: biggest position changes. */
function aw_si_box_movers(): void {
	aw_si_mini_table(
		aw_si_rows( 'pos28 IS NOT NULL AND pos_prev IS NOT NULL AND impr28 >= 20', 'ABS(pos_prev - pos28) DESC' ),
		array(
			'pos_prev' => __( 'Before', 'aw-seo-extras' ),
			'pos28'    => __( 'Now', 'aw-seo-extras' ),
			'impr28'   => __( 'Impressions', 'aw-seo-extras' ),
			'page'     => __( 'Page', 'aw-seo-extras' ),
		)
	);
}

/** Overview box: brand searches. */
function aw_si_box_brand(): void {
	aw_si_mini_table(
		aw_si_rows( 'roles & ' . (int) AW_SI_ROLE_BRAND, 'impr28 DESC' ),
		array(
			'pos28'    => __( 'Position', 'aw-seo-extras' ),
			'impr28'   => __( 'Impressions', 'aw-seo-extras' ),
			'clicks28' => __( 'Clicks', 'aw-seo-extras' ),
			'live_txt' => __( 'Live check', 'aw-seo-extras' ),
		)
	);
	echo '<p class="description">' . esc_html__( 'AI referrals have their own box. Sitelinks (live check) and unlinked mentions come in later phases.', 'aw-seo-extras' ) . '</p>';
}

/** Overview box: sessions from AI assistants, by source and by landing page. */
function aw_si_box_ai(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$o     = aw_si_table( 'obs' );
	$since = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-28 days' )->format( 'Y-m-d' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$by_src  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, SUM(value) n FROM {$o} WHERE src = 'ga4' AND metric = 'ai' AND period = 'day' AND d >= %s GROUP BY txt ORDER BY n DESC", $since ) );
	$by_page = (array) $wpdb->get_results( $wpdb->prepare( "SELECT page, SUM(value) n FROM {$o} WHERE src = 'ga4' AND metric = 'ai' AND period = 'day' AND d >= %s GROUP BY page ORDER BY n DESC LIMIT 10", $since ) );
	// phpcs:enable
	if ( ! $by_src ) {
		echo '<p>' . esc_html__( 'No AI referrals recorded yet (GA4 runs daily at 07:20 IST).', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$parts = array();
	foreach ( $by_src as $r ) {
		$parts[] = $r->txt . ': ' . number_format_i18n( (int) $r->n );
	}
	echo '<p>' . esc_html( implode( ' · ', $parts ) ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Landing page', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Sessions', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $by_page as $r ) {
		echo '<tr><td><a href="' . esc_url( home_url( $r->page ) ) . '" target="_blank" rel="noopener">' . esc_html( $r->page ) . '</a></td><td>' . esc_html( number_format_i18n( (int) $r->n ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Overview box: what people search on the site (WordPress search, Directory, GA4 site search), counts only. */
function aw_si_box_site_search(): void {
	$rows = aw_si_site_searches( 28, 15 );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Nothing yet: searches are counted from today.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$where = array(
		'wp'       => __( 'Site search', 'aw-seo-extras' ),
		'dir-q'    => __( 'Directory search', 'aw-seo-extras' ),
		'dir-pin'  => __( 'Directory PIN code', 'aw-seo-extras' ),
		'dir-near' => __( 'Near me (district found)', 'aw-seo-extras' ),
		'search'   => __( 'GA4 site search', 'aw-seo-extras' ),
	);
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Term', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Where', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Searches', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->txt ) . '</td><td>' . esc_html( $where[ $r->metric ] ?? $r->metric ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->n ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Daily counts per term only: no IP address, user or session is stored, and terms that look like emails or phone numbers are dropped.', 'aw-seo-extras' ) . '</p>';
}

/** Settings tab: schedule, seed, run now, delete data. */
function aw_si_settings_tab(): void {
	$seeded = (int) get_option( 'aw_si_seeded' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Schedule', 'aw-seo-extras' ) . '</th><td><p>' . esc_html__( 'Daily at 06:30 IST: Clarity (once the token is set). Daily at 07:10 IST: Search Console (the last 4 days again, plus up to 15 older days until 90 days are stored), keyword roles from Rank Math and Sourcing, then priorities. Daily at 07:20 IST: GA4 (site search, AI referrals, organic landing pages; the last 3 days). Sundays at 06:00 IST: PageSpeed for the 16 key pages, a few at a time. Daily at 23:30 IST: rows older than 90 days become monthly totals. Searches on the site are counted as they happen.', 'aw-seo-extras' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Keys', 'aw-seo-extras' ) . '</th><td><ul>';
	$keys = array(
		'AW_CLARITY_TOKEN' => __( 'Clarity Data Export token (Clarity → Settings → Data Export). Needed for Clarity figures and Clarity page-quality flags.', 'aw-seo-extras' ),
		'AW_PSI_KEY'       => __( 'Free PageSpeed API key. Only needed if Site Kit\'s PageSpeed module can\'t be used.', 'aw-seo-extras' ),
	);
	foreach ( $keys as $const => $what ) {
		$set = defined( $const ) && '' !== (string) constant( $const );
		echo '<li><span class="dashicons dashicons-' . ( $set ? 'yes-alt' : 'minus' ) . '" aria-hidden="true"></span> <code>' . esc_html( $const ) . '</code> ' . esc_html( $set ? __( 'is set in wp-config.php.', 'aw-seo-extras' ) : __( 'is not set.', 'aw-seo-extras' ) ) . ' <span class="description">' . esc_html( $what ) . '</span></li>';
	}
	echo '</ul><p class="description">' . esc_html__( 'Keys live in wp-config.php only; their values are never shown or stored here.', 'aw-seo-extras' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Keyword map seed', 'aw-seo-extras' ) . '</th><td><p>';
	echo $seeded
		/* translators: %s: date. */
		? esc_html( sprintf( __( 'Seeded once on %s from docs/seo/keyword-map.md and the 21 Oct rankings. The table here is the source of truth now.', 'aw-seo-extras' ), wp_date( 'j M Y, H:i', $seeded ) ) )
		: esc_html__( 'Not seeded yet: the first run seeds it.', 'aw-seo-extras' );
	echo '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Run now', 'aw-seo-extras' ) . '</th><td>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_run">';
	wp_nonce_field( 'aw_si_run' );
	echo '<p><button type="submit" name="what" value="daily" class="button">' . esc_html__( 'Run the daily update', 'aw-seo-extras' ) . '</button> ';
	echo '<button type="submit" name="what" value="rollup" class="button">' . esc_html__( 'Run the 90-day rollup', 'aw-seo-extras' ) . '</button> ';
	echo '<button type="submit" name="what" value="ga4" class="button">' . esc_html__( 'Run GA4', 'aw-seo-extras' ) . '</button> ';
	echo '<button type="submit" name="what" value="psi" class="button">' . esc_html__( 'Start PageSpeed', 'aw-seo-extras' ) . '</button>';
	if ( aw_si_clarity_on() ) {
		echo ' <button type="submit" name="what" value="clarity" class="button">' . esc_html__( 'Run Clarity (uses 2 of its 10 daily calls)', 'aw-seo-extras' ) . '</button>';
	}
	echo '</p>';
	echo '</form>';
	echo '<p class="description">' . esc_html__( 'The daily update can take a minute on the first run (it fetches up to 19 days of Search Console).', 'aw-seo-extras' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="aw-si-confirm">' . esc_html__( 'Delete Search Insights data', 'aw-seo-extras' ) . '</label></th><td>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="aw_si_delete">';
	wp_nonce_field( 'aw_si_delete' );
	echo '<p>' . esc_html__( 'Drops the Search Insights tables and settings. Deactivating or deleting the plugin never does this. Type DELETE to confirm.', 'aw-seo-extras' ) . '</p>';
	echo '<input type="text" id="aw-si-confirm" name="confirm" class="regular-text" autocomplete="off"> ';
	submit_button( __( 'Delete data', 'aw-seo-extras' ), 'delete', 'submit', false );
	echo '</form></td></tr>';
	echo '</tbody></table>';
}

add_action(
	'admin_post_aw_si_run',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_run' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
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
		} else {
			aw_si_daily( true );
		}
		wp_safe_redirect( add_query_arg( 'ran', 1, aw_si_url( 'settings' ) ) );
		exit;
	}
);

add_action(
	'admin_post_aw_si_delete',
	function () {
		if ( ! current_user_can( aw_si_cap() ) || ! check_admin_referer( 'aw_si_delete' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'aw-seo-extras' ), 403 );
		}
		$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		if ( 'DELETE' !== $confirm ) {
			wp_die(
				esc_html__( 'Not deleted: type DELETE to confirm.', 'aw-seo-extras' ),
				'',
				array(
					'back_link' => true,
					'response'  => 400,
				)
			);
		}
		aw_si_drop_all();
		wp_safe_redirect( aw_si_url( 'settings' ) );
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
