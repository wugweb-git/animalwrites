<?php
/**
 * AW SEO Plus → Search Insights (owner addendum, 24 Oct: core WordPress only, no custom CSS). Tabs: Overview (postboxes),
 * Keywords (list table), tabs added by other files (Pages, Check list, Rising, Sourcing keywords) and Settings
 * (form-table: keys, run now, imports, snapshot, delete data). The old Search demand screen was removed on 25 Oct 2026;
 * its content gaps are an Overview box.
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
		// AW SEO Plus → Search Insights, after Overview and FAQ.
		$hook = add_submenu_page( AW_SEO_MENU, __( 'Search Insights', 'aw-seo-extras' ), __( 'Search Insights', 'aw-seo-extras' ), aw_si_cap(), AW_SI_SLUG, 'aw_si_screen' );
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
		add_meta_box( 'aw-si-referrers', __( 'Top referrers (GA4)', 'aw-seo-extras' ), 'aw_si_box_referrers', $screen, 'normal' );
		add_meta_box( 'aw-si-nf', __( 'Not found (404s, GA4, 28 days)', 'aw-seo-extras' ), 'aw_si_box_nf', $screen, 'normal' );
		add_meta_box( 'aw-si-clarity', __( 'Clarity (28 days)', 'aw-seo-extras' ), 'aw_si_box_clarity', $screen, 'normal' );
		add_meta_box( 'aw-si-site-search', __( 'Searches on our site (28 days, minimum)', 'aw-seo-extras' ), 'aw_si_box_site_search', $screen, 'normal' );
		add_meta_box( 'aw-si-gaps', __( 'Content gaps (keyword map gaps and P2)', 'aw-seo-extras' ), 'aw_si_box_gaps', $screen, 'normal' );
		add_meta_box( 'aw-si-crawl', __( 'Crawler access (weekly)', 'aw-seo-extras' ), 'aw_si_box_crawl', $screen, 'normal' );
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
		} elseif ( $ok && in_array( $r['info']['status'] ?? '', array( 'baseline', 'no rows' ), true ) ) {
			$msg = 'baseline' === $r['info']['status']
				/* translators: 1: source, 2: date and time. */
				? __( '%1$s ran on %2$s and recorded the usual level of each term (a baseline): rising terms show from its next run.', 'aw-seo-extras' )
				/* translators: 1: source, 2: date and time. */
				: __( '%1$s ran on %2$s and kept nothing: the source answered, and no term passed the filters.', 'aw-seo-extras' );
			echo '<div class="notice notice-info inline"><p>' . esc_html( sprintf( $msg, $s['label'], wp_date( 'j M, H:i', $ok ) ) ) . '</p></div>';
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
	if ( function_exists( 'aw_si_gdelt_on' ) && ! aw_si_gdelt_on() ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'News (GDELT) is switched off (Settings): GDELT refuses our requests (HTTP 429), so Rising uses the other sources.', 'aw-seo-extras' ) . '</p></div>';
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
		),
		aw_si_extra_tabs(),
		array( 'settings' => __( 'Settings', 'aw-seo-extras' ) )
	);
	foreach ( $tabs as $key => $label ) {
		echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( aw_si_url( $key ) ) . '"' . ( $key === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
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
		if ( ! empty( $info['via'] ) ) {
			$det[] = (string) $info['via']; // The source used instead, e.g. Rank Math Analytics when Site Kit failed.
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
		'quick'     => array( __( 'Quick wins', 'aw-seo-extras' ), aw_si_quick_sql() ),
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
	// Sitelinks: brand searches where the live check found two or more of our links in the top 20.
	global $wpdb;
	$o     = aw_si_table( 'obs' );
	$k     = aw_si_table( 'keywords' );
	$links = aw_si_ready() ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT k.label, o.value, o.d FROM {$o} o JOIN {$k} k ON k.id = o.kw_id WHERE o.src = 'live' AND o.metric = 'ours' AND o.value >= 2 AND k.roles & %d AND o.d = (SELECT MAX(d) FROM {$o} WHERE src = 'live' AND metric = 'ours' AND kw_id = o.kw_id) ORDER BY o.value DESC LIMIT 10", AW_SI_ROLE_BRAND ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names only.
	if ( $links ) {
		$parts = array();
		foreach ( $links as $l ) {
			/* translators: 1: brand search, 2: number of our links. */
			$parts[] = sprintf( __( '%1$s (%2$d links)', 'aw-seo-extras' ), $l->label, (int) $l->value );
		}
		/* translators: %s: brand searches with their link counts. */
		echo '<p>' . esc_html( sprintf( __( 'Sitelinks in the last live check: %s.', 'aw-seo-extras' ), implode( ', ', $parts ) ) ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'AI referrals have their own box. Sitelinks come from the monthly live check (Check list tab); unlinked mentions come in a later phase.', 'aw-seo-extras' ) . '</p>';
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

/** Overview box: every referring site and social source, 28 days and 3 months (owner, 31 Oct 2026). */
function aw_si_box_referrers(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$o     = aw_si_table( 'obs' );
	$today = new DateTimeImmutable( 'today', wp_timezone() );
	$d28   = $today->modify( '-28 days' )->format( 'Y-m-d' );
	$d90   = $today->modify( '-90 days' )->format( 'Y-m-d' ); // Daily rows are kept 90 days.
	$skip  = aw_si_ga4_skip_sql();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed dates only.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT txt, SUM(IF(metric = 'ref' AND d >= %s, value, 0)) s28, SUM(IF(metric = 'ref_eng' AND d >= %s, value, 0)) e28, SUM(IF(metric = 'ref_key' AND d >= %s, value, 0)) k28, SUM(IF(metric = 'ref', value, 0)) s90, MIN(d) since FROM {$o} WHERE src = 'ga4' AND metric IN ('ref', 'ref_eng', 'ref_key') AND period = 'day' AND d >= %s{$skip} GROUP BY txt ORDER BY s28 DESC, s90 DESC LIMIT 20", $d28, $d28, $d28, $d90 ) );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No referrals recorded yet (GA4 runs daily at 07:20 IST).', 'aw-seo-extras' ) . '</p>';
	} else {
		$since = min( wp_list_pluck( $rows, 'since' ) );
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Source', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Sessions (28 days)', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Engaged', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Key events', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Sessions (90 days)', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r->txt ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->s28 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->e28 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->k28 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->s90 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		/* translators: %s: first date with referral figures. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Figures from %s.', 'aw-seo-extras' ), wp_date( get_option( 'date_format' ), strtotime( $since ) ) ) ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'Referral and social sessions by source (GA4 channels Referral, Organic Social and Paid Social, plus Instagram, Facebook, X, LinkedIn, Reddit, WhatsApp, Telegram and Linktree). 22 Oct 2026 is left out (GA4 test events). GA4 page views changed on 25 Oct 2026, when the site moved to one GA4 tag (#80).', 'aw-seo-extras' ) . '</p>';
}

/** Overview box: the 20 paths with the most 404_view events in the stored 28-day window, and where each goes now. */
function aw_si_box_nf(): void {
	global $wpdb;
	if ( ! aw_si_ready() ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	$o = aw_si_table( 'obs' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
	$rows = (array) $wpdb->get_results( "SELECT txt, value, d FROM {$o} WHERE src = 'ga4' AND metric = 'nf' AND period = '28d' ORDER BY value DESC, txt LIMIT 20" );
	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No 404s recorded yet (GA4 runs daily at 07:20 IST).', 'aw-seo-extras' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Path', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Views', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Status', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$to = aw_si_final_path( (string) $r->txt );
		if ( untrailingslashit( $to ) !== untrailingslashit( (string) $r->txt ) ) {
			/* translators: %s: path the redirect ends at. */
			$status = sprintf( esc_html__( 'Redirects to %s', 'aw-seo-extras' ), '<a href="' . esc_url( home_url( $to ) ) . '" target="_blank" rel="noopener">' . esc_html( $to ) . '</a>' );
		} else {
			$status = esc_html__( 'Still 404', 'aw-seo-extras' );
		}
		echo '<tr><td>' . esc_html( $r->txt ) . '</td><td>' . esc_html( number_format_i18n( (int) $r->value ) ) . '</td><td>' . $status . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '</tbody></table>';
	$info = aw_si_runs()['ga4']['info']['nf'] ?? array();
	$dim  = 'pagePath' === ( $info['dim'] ?? '' ) ? __( 'page path (the custom dimension "path" is not registered in GA4 yet)', 'aw-seo-extras' ) : __( 'the event\'s path parameter', 'aw-seo-extras' );
	/* translators: 1: last day of the window, 2: what the path comes from. */
	echo '<p class="description">' . esc_html( sprintf( __( '404_view events in the 28 days to %1$s, by %2$s. 22 Oct 2026 is left out (GA4 test events). Status follows Rank Math redirects (exact and regex) and old slugs; "Still 404" means no redirect, not that the address was checked again.', 'aw-seo-extras' ), wp_date( get_option( 'date_format' ), strtotime( (string) $rows[0]->d ) ), $dim ) ) . '</p>';
}

/** Overview box: content gaps (the keyword map's demand gaps and every P2 keyword), with the suggested page type. */
function aw_si_box_gaps(): void {
	$gaps = aw_si_searches_gaps();
	if ( ! $gaps ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'aw-seo-extras' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Gap', 'aw-seo-extras' ) . '</th><th scope="col">' . esc_html__( 'Searches', 'aw-seo-extras' ) . '</th></tr></thead><tbody>';
	foreach ( $gaps as $g ) {
		echo '<tr><td>' . esc_html( $g[0] ) . '</td><td>' . esc_html( $g[1] ) . '</td></tr>';
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
	echo '<p class="description">' . esc_html__( 'Minimum counts: a search answered from the page cache never reaches WordPress, so it is not counted (mostly Directory searches; WordPress search results are normally not cached). Daily counts per term only: no IP address, user or session is stored, and terms that look like emails or phone numbers are dropped.', 'aw-seo-extras' ) . '</p>';
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
	return is_numeric( $v ) ? sprintf( __( '%s s', 'aw-seo-extras' ), number_format_i18n( round( (float) $v ) ) ) : '–';
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
		return __( 'No Clarity token: add it under Animal Writes → Keys.', 'aw-seo-extras' );
	}
	return __( 'No Clarity data yet; it imports daily at 06:30.', 'aw-seo-extras' );
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
		__( 'Sessions', 'aw-seo-extras' )                      => esc_html( number_format_i18n( (int) ( $s['sessions'] ?? 0 ) ) ),
		__( 'Pages per session', 'aw-seo-extras' )             => esc_html( aw_si_clarity_num( $s['pps'] ?? null ) ),
		__( 'Scroll depth', 'aw-seo-extras' )                  => esc_html( aw_si_clarity_pct( $s['scroll'] ?? null ) ),
		__( 'Active time', 'aw-seo-extras' )                   => esc_html( aw_si_clarity_secs( $s['active'] ?? null ) ),
		__( 'Dead clicks', 'aw-seo-extras' )                   => esc_html( aw_si_clarity_pct( $s['dead'] ?? null ) ),
		__( 'Rage clicks', 'aw-seo-extras' )                   => esc_html( aw_si_clarity_pct( $s['rage'] ?? null ) ),
		__( 'Quick backs', 'aw-seo-extras' )                   => esc_html( aw_si_clarity_pct( $s['quickback'] ?? null ) ),
		__( 'Excessive scrolling', 'aw-seo-extras' )           => esc_html( aw_si_clarity_pct( $s['xscroll'] ?? null ) ),
		__( 'Script errors', 'aw-seo-extras' )                 => esc_html( aw_si_clarity_pct( $s['jserror'] ?? null ) ),
		__( 'Pages flagged (3+ days)', 'aw-seo-extras' )       => $flagged,
		__( 'Mobile / PC / Tablet sessions', 'aw-seo-extras' ) => esc_html( aw_si_clarity_devices_txt( $s['devices'] ?? array() ) ),
	);
	echo '<table class="widefat striped"><tbody>';
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}
	echo '</tbody></table>';
	$days = (int) $s['days'];
	/* translators: %s: number of days. */
	$line = sprintf( _n( '%s day of data', '%s days of data', $days, 'aw-seo-extras' ), number_format_i18n( $days ) );
	if ( ! empty( $s['last'] ) ) {
		/* translators: %s: date. */
		$line .= ' · ' . sprintf( __( 'last day %s', 'aw-seo-extras' ), mysql2date( 'j M Y', (string) $s['last'] ) );
	}
	echo '<p class="description">' . esc_html( $line ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( aw_si_url( 'clarity' ) ) . '">' . esc_html__( 'Clarity', 'aw-seo-extras' ) . '</a></p>';
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
				$out['clarity'] = __( 'Clarity', 'aw-seo-extras' );
			}
		}
		if ( ! isset( $out['clarity'] ) ) {
			$out['clarity'] = __( 'Clarity', 'aw-seo-extras' );
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
				'label'   => __( 'Pages per page', 'aw-seo-extras' ),
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
		$table->search_box( __( 'Search pages', 'aw-seo-extras' ), 'aw-si-clarity' );
		$table->display();
		echo '</form>';
	}
);

/** Settings tab: schedule, seed, run now, delete data. */
function aw_si_settings_tab(): void {
	$seeded = (int) get_option( 'aw_si_seeded' );
	do_action( 'aw_si_settings_notices' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Schedule', 'aw-seo-extras' ) . '</th><td><p>' . esc_html__( 'Daily at 06:30 IST: Clarity (once the token is set). Daily at 07:10 IST: Search Console (the last 4 days again, plus up to 15 older days until 90 days are stored), keyword roles from Rank Math and Sourcing, then priorities. Daily at 07:20 IST: GA4 (site search, AI referrals, all referral and social sources, organic landing pages; the last 3 days; 404s for the last 28 days). Sundays at 06:00 IST: PageSpeed for the 16 key pages, a few at a time. Mondays at 08:45 IST: crawler access from the server; at 09:15 IST from outside (workers). Daily at 23:30 IST: rows older than 90 days become monthly totals. The 2nd of each month at 07:40 IST: Bing Webmaster (links to us, and clicks and impressions by search and by page; once the key is set). Searches on the site are counted as they happen.', 'aw-seo-extras' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Keys', 'aw-seo-extras' ) . '</th><td><ul>';
	foreach ( array( 'AW_CLARITY_TOKEN' => __( 'Clarity', 'aw-seo-extras' ), 'AW_BING_WEBMASTER_KEY' => __( 'Bing Webmaster', 'aw-seo-extras' ), 'AW_PSI_KEY' => __( 'PageSpeed (optional)', 'aw-seo-extras' ) ) as $const => $label ) {
		$set = '' !== aw_si_key( $const );
		echo '<li><span class="dashicons dashicons-' . ( $set ? 'yes-alt' : 'minus' ) . '" aria-hidden="true"></span> ' . esc_html( $label . ': ' . ( $set ? __( 'set', 'aw-seo-extras' ) : __( 'not set', 'aw-seo-extras' ) ) ) . '</li>';
	}
	echo '</ul>';
	if ( function_exists( 'aw_key' ) && current_user_can( 'manage_options' ) ) {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=aw-keys' ) ) . '">' . esc_html__( 'Animal Writes → Keys', 'aw-seo-extras' ) . '</a></p>';
	}
	echo '<p class="description">' . esc_html__( 'Keys are set under Animal Writes → Keys (or as wp-config.php constants, which win); their values are never shown.', 'aw-seo-extras' ) . '</p></td></tr>';
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
	if ( aw_si_bing_on() ) {
		echo ' <button type="submit" name="what" value="bing" class="button">' . esc_html__( 'Run Bing Webmaster', 'aw-seo-extras' ) . '</button>';
	}
	if ( aw_si_clarity_on() ) {
		echo ' <button type="submit" name="what" value="clarity" class="button">' . esc_html__( 'Run Clarity (uses 2 of its 10 daily calls)', 'aw-seo-extras' ) . '</button>';
	}
	echo '</p>';
	echo '</form>';
	echo '<p class="description">' . esc_html__( 'The daily update can take a minute on the first run (it fetches up to 19 days of Search Console).', 'aw-seo-extras' ) . '</p></td></tr>';
	do_action( 'aw_si_settings_rows' ); // More rows: imports and paste forms (imports.php).
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
		} elseif ( 'bing' === $what ) {
			aw_si_bing_run();
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
