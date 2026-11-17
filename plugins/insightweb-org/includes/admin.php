<?php
/**
 * InsightWeb admin: a standalone top-level menu with a tab set (Overview, Keywords, …). Each tab is rendered by a
 * function that RETURNS its HTML, so the same markup serves two ways:
 *   - today, the classic path — insightweb_render_screen() echoes it inside a core `<div class="wrap">`;
 *   - once the shared admin shell package (insightweb-shell) is bundled, the shell draws the same HTML as a "part",
 *     keeping the admin shell look (components, palette, typography, chrome) with no redesign.
 *
 * No custom CSS of its own beyond layout; colours and chrome come from WordPress admin / the shell. The Keywords grid
 * is a core WP_List_Table (class-insightweb-keywords-table.php); the Overview is core scorecards and widefat tables
 * (overview.php), and the same figures feed the shell's chart component over REST (rest.php).
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The capability InsightWeb's screens require. Maps to manage_options until a dedicated capability is granted in a
 * later phase.
 *
 * @return string
 */
function insightweb_cap(): string {
	/**
	 * Filter the capability for InsightWeb's admin screens.
	 *
	 * @param string $cap The capability.
	 */
	return (string) apply_filters( 'insightweb_cap', 'manage_options' );
}

/**
 * The tab set, id => title, in order. Modules add their own through the `insightweb_tabs` filter.
 *
 * @return array<string, string>
 */
function insightweb_tabs(): array {
	$tabs = array(
		'overview' => __( 'Overview', 'insightweb' ),
		'keywords' => __( 'Keywords', 'insightweb' ),
		'rising'   => __( 'Rising', 'insightweb' ),
		'links'    => __( 'Links', 'insightweb' ),
	);
	/**
	 * Filter the InsightWeb admin tabs (id => title).
	 *
	 * @param array<string, string> $tabs The tabs.
	 */
	return (array) apply_filters( 'insightweb_tabs', $tabs );
}

/**
 * The tab this request asks for (defaults to the first).
 *
 * @return string
 */
function insightweb_current_tab(): string {
	$tabs = insightweb_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.
	return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
}

/**
 * Whether the Site Kit by Google plugin is available (model A: InsightWeb reads Google data only through Site Kit).
 *
 * @return bool
 */
function insightweb_site_kit_active(): bool {
	return class_exists( '\Google\Site_Kit\Plugin' );
}

add_action( 'admin_menu', 'insightweb_admin_menu' );

/**
 * Register the top-level InsightWeb menu and remember its load hook (screen options, action handling).
 *
 * @return void
 */
function insightweb_admin_menu(): void {
	$hook = add_menu_page(
		__( 'InsightWeb', 'insightweb' ),
		__( 'InsightWeb', 'insightweb' ),
		insightweb_cap(),
		'insightweb',
		'insightweb_render_screen',
		'dashicons-chart-line'
	);
	if ( $hook ) {
		add_action( 'load-' . $hook, 'insightweb_screen_load' );
	}
}

/**
 * On the InsightWeb screen load: handle a priority override (before any output, so it can redirect) and add the
 * per-page screen option for the Keywords list.
 *
 * @return void
 */
function insightweb_screen_load(): void {
	insightweb_handle_kw_action();
	insightweb_handle_rising_action();
	$per = array(
		'keywords' => array( __( 'Keywords per page', 'insightweb' ), 'insightweb_keywords_per_page' ),
		'rising'   => array( __( 'Rising terms per page', 'insightweb' ), 'insightweb_rising_per_page' ),
		'links'    => array( __( 'Links per page', 'insightweb' ), 'insightweb_links_per_page' ),
	);
	$tab = insightweb_current_tab();
	if ( isset( $per[ $tab ] ) ) {
		add_screen_option(
			'per_page',
			array(
				'label'   => $per[ $tab ][0],
				'default' => 50,
				'option'  => $per[ $tab ][1],
			)
		);
	}
}

add_filter( 'set-screen-option', 'insightweb_set_screen_option', 10, 3 );

/**
 * Save the Keywords per-page screen option.
 *
 * @param mixed  $status Default (false).
 * @param string $option Option name.
 * @param int    $value  Submitted value.
 * @return mixed
 */
function insightweb_set_screen_option( $status, $option, $value ) {
	$ours = array( 'insightweb_keywords_per_page', 'insightweb_rising_per_page', 'insightweb_links_per_page' );
	return in_array( $option, $ours, true ) ? max( 1, (int) $value ) : $status;
}

/**
 * Render the InsightWeb admin screen: the tab bar and the current tab's HTML, inside a core wrap. When the shared
 * admin shell is present it would draw the same tab HTML instead; until then this classic render is the working path.
 *
 * @return void
 */
function insightweb_render_screen(): void {
	if ( ! current_user_can( insightweb_cap() ) ) {
		return;
	}
	$tabs = insightweb_tabs();
	$cur  = insightweb_current_tab();

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'InsightWeb', 'insightweb' ) . '</h1>';

	// Site Kit connection prompt (model A: Google data arrives only through Site Kit by Google).
	if ( ! insightweb_site_kit_active() ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'InsightWeb reads Search Console and GA4 through Site Kit by Google. Activate and connect Site Kit to see your data.', 'insightweb' ) . '</p></div>';
	}

	echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'InsightWeb sections', 'insightweb' ) . '">';
	foreach ( $tabs as $id => $title ) {
		$url = insightweb_url( 'overview' === $id ? '' : $id );
		echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $id === $cur ? ' nav-tab-active' : '' ) . '"' . ( $id === $cur ? ' aria-current="page"' : '' ) . '>' . esc_html( $title ) . '</a>';
	}
	echo '</nav>';

	// The current tab's HTML (the shell draws this same markup as a "part").
	echo insightweb_tab_html( $cur ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in the tab renderers.

	echo '</div>';
}

/**
 * One tab as a block of escaped HTML. Keywords renders the core list table (captured so it embeds as a shell part).
 *
 * @param string $tab Tab id.
 * @return string
 */
function insightweb_tab_html( string $tab ): string {
	if ( 'keywords' === $tab ) {
		return insightweb_keywords_html();
	}
	if ( 'rising' === $tab ) {
		return insightweb_rising_html();
	}
	if ( 'links' === $tab ) {
		return insightweb_links_html();
	}
	if ( 'overview' === $tab ) {
		return insightweb_overview_html();
	}
	/**
	 * Filter the HTML of a module-registered tab.
	 *
	 * @param string $html The tab HTML (empty by default).
	 * @param string $tab  The tab id.
	 */
	return (string) apply_filters( 'insightweb_tab_html', '', $tab );
}

/**
 * The Keywords tab: the search box, the view links, the filters and the core list table, inside the list form.
 *
 * @return string
 */
function insightweb_keywords_html(): string {
	$table = new InsightWeb_Keywords_Table();
	$table->prepare_items();
	ob_start();
	$table->views();
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="insightweb">';
	echo '<input type="hidden" name="tab" value="keywords">';
	$table->search_box( __( 'Search keywords', 'insightweb' ), 'insightweb-kw' );
	$table->display();
	echo '</form>';
	return (string) ob_get_clean();
}

/**
 * The Rising tab: the view links, the search box and the core list table, inside the list form.
 *
 * @return string
 */
function insightweb_rising_html(): string {
	$table = new InsightWeb_Rising_Table();
	$table->prepare_items();
	ob_start();
	$table->views();
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="insightweb">';
	echo '<input type="hidden" name="tab" value="rising">';
	$table->search_box( __( 'Search terms', 'insightweb' ), 'insightweb-rising' );
	$table->display();
	echo '</form>';
	return (string) ob_get_clean();
}

/**
 * The Links tab: the search box, the type filter and the core list table, inside the list form.
 *
 * @return string
 */
function insightweb_links_html(): string {
	$table = new InsightWeb_Links_Table();
	$table->prepare_items();
	ob_start();
	echo '<form method="get">';
	echo '<input type="hidden" name="page" value="insightweb">';
	echo '<input type="hidden" name="tab" value="links">';
	$table->search_box( __( 'Search links', 'insightweb' ), 'insightweb-links' );
	$table->display();
	echo '</form>';
	return (string) ob_get_clean();
}

/**
 * A nonce'd link for a priority row action on one keyword record.
 *
 * @param string $action override_p1 … override_clear.
 * @param int    $id     Keyword record id.
 * @return string
 */
function insightweb_kw_action_url( string $action, int $id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'insightweb_kw' => $action,
				'kw'            => $id,
			),
			insightweb_url( 'keywords' )
		),
		'insightweb_kw_' . $id,
		'_insightweb_nonce'
	);
}

/**
 * Apply a priority override to one or more keyword records (row action or bulk action). Capability and nonce checked
 * here; then redirect back to the list without the action in the URL (post/redirect/get).
 *
 * @return void
 */
function insightweb_handle_kw_action(): void {
	if ( ! current_user_can( insightweb_cap() ) ) {
		return;
	}
	$map = array(
		'override_p1'    => 'P1',
		'override_p2'    => 'P2',
		'override_p3'    => 'P3',
		'override_clear' => '',
	);

	// Row action, e.g. insightweb_kw=override_p1 with kw and the nonce.
	$row = isset( $_GET['insightweb_kw'] ) ? sanitize_key( wp_unslash( $_GET['insightweb_kw'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
	if ( '' !== $row && isset( $map[ $row ] ) ) {
		$id = isset( $_GET['kw'] ) ? (int) $_GET['kw'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		check_admin_referer( 'insightweb_kw_' . $id, '_insightweb_nonce' );
		if ( $id ) {
			insightweb_kw_set_override( $id, $map[ $row ] );
		}
		wp_safe_redirect( insightweb_list_return_url() );
		exit;
	}

	// Bulk action: the list table posts action/action2 + kw[] + the bulk nonce.
	$bulk = '';
	foreach ( array( 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		if ( '' !== $v && '-1' !== $v && isset( $map[ $v ] ) ) {
			$bulk = $v;
			break;
		}
	}
	if ( '' === $bulk ) {
		return;
	}
	check_admin_referer( 'bulk-insightweb_keywords' );
	$ids = isset( $_REQUEST['kw'] ) ? array_map( 'intval', (array) wp_unslash( $_REQUEST['kw'] ) ) : array();
	foreach ( array_filter( $ids ) as $id ) {
		insightweb_kw_set_override( $id, $map[ $bulk ] );
	}
	wp_safe_redirect( insightweb_list_return_url() );
	exit;
}

/**
 * The Keywords list URL to return to after an override, keeping the view, search, filters, sort and page so the owner
 * lands back where they were. Read-only request values, rebuilt onto the canonical tab URL.
 *
 * @return string
 */
function insightweb_list_return_url(): string {
	$f    = insightweb_kw_filters();
	$args = array();
	if ( 'all' !== $f['view'] ) {
		$args['view'] = $f['view'];
	}
	if ( $f['role'] ) {
		$args['role'] = $f['role'];
	}
	if ( '' !== $f['intent'] ) {
		$args['intent'] = $f['intent'];
	}
	if ( '' !== $f['topic'] ) {
		$args['topic'] = $f['topic'];
	}
	if ( '' !== $f['search'] ) {
		$args['s'] = $f['search'];
	}
	if ( 'impr28' !== $f['orderby'] ) {
		$args['orderby'] = $f['orderby'];
	}
	if ( 'DESC' !== $f['order'] ) {
		$args['order'] = strtolower( $f['order'] );
	}
	if ( $f['paged'] > 1 ) {
		$args['paged'] = $f['paged'];
	}
	return add_query_arg( $args, insightweb_url( 'keywords' ) );
}

/**
 * A nonce'd link for a rising status row action.
 *
 * @param string $action rising_noted, rising_dismissed or rising_new.
 * @param int    $id     Rising-row id.
 * @return string
 */
function insightweb_rising_action_url( string $action, int $id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'insightweb_rising' => $action,
				'rising'            => $id,
			),
			insightweb_url( 'rising' )
		),
		'insightweb_rising_' . $id,
		'_insightweb_nonce'
	);
}

/**
 * Apply a rising status change (row or bulk action). Capability and nonce checked here; then redirect back to the
 * Rising list without the action in the URL (post/redirect/get).
 *
 * @return void
 */
function insightweb_handle_rising_action(): void {
	if ( ! current_user_can( insightweb_cap() ) ) {
		return;
	}
	$map = array(
		'rising_noted'     => 'noted',
		'rising_dismissed' => 'dismissed',
		'rising_new'       => 'new',
	);

	// Row action.
	$row = isset( $_GET['insightweb_rising'] ) ? sanitize_key( wp_unslash( $_GET['insightweb_rising'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
	if ( '' !== $row && isset( $map[ $row ] ) ) {
		$id = isset( $_GET['rising'] ) ? (int) $_GET['rising'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		check_admin_referer( 'insightweb_rising_' . $id, '_insightweb_nonce' );
		if ( $id ) {
			insightweb_rising_set_status( $id, $map[ $row ] );
		}
		wp_safe_redirect( insightweb_rising_return_url() );
		exit;
	}

	// Bulk action: the list table posts action/action2 + rising[] + the bulk nonce.
	$bulk = '';
	foreach ( array( 'action', 'action2' ) as $field ) {
		$v = isset( $_REQUEST[ $field ] ) ? sanitize_key( wp_unslash( $_REQUEST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		if ( '' !== $v && isset( $map[ $v ] ) ) {
			$bulk = $v;
			break;
		}
	}
	if ( '' === $bulk ) {
		return;
	}
	check_admin_referer( 'bulk-insightweb_rising' );
	$ids = isset( $_REQUEST['rising'] ) ? array_map( 'intval', (array) wp_unslash( $_REQUEST['rising'] ) ) : array();
	foreach ( array_filter( $ids ) as $id ) {
		insightweb_rising_set_status( $id, $map[ $bulk ] );
	}
	wp_safe_redirect( insightweb_rising_return_url() );
	exit;
}

/**
 * The Rising list URL to return to after a status change, keeping the view and page.
 *
 * @return string
 */
function insightweb_rising_return_url(): string {
	$f    = insightweb_list_filters( 'rising' );
	$args = array();
	if ( 'new' !== $f['view'] ) {
		$args['view'] = $f['view'];
	}
	if ( '' !== $f['search'] ) {
		$args['s'] = $f['search'];
	}
	if ( $f['paged'] > 1 ) {
		$args['paged'] = $f['paged'];
	}
	return add_query_arg( $args, insightweb_url( 'rising' ) );
}
