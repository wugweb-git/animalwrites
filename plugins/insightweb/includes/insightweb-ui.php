<?php
/**
 * InsightWeb's screens on the Kits admin shell, the default since 12 Nov 2026:
 * - admin.php?page=aw-search-insights: the Dashboard;
 * - admin.php?page=aw-search-insights&tab=settings: Settings;
 * - admin.php?page=aw-search-insights&tab=<keywords|pages|checklist|rising|clarity|links|server|sourcing|…>: the other
 *   tabs, each from its own output (insightweb-tabs.php), since 12 Nov 2026.
 *
 * &ui=classic shows the earlier tabs. The screens are one script (build/index.js, from src/), drawn with Kits Core's
 * shell parts (kits-core/includes/admin-shell.php) and fed by the insightweb/v1 routes (insightweb-rest.php). When Kits
 * Core or a build file is missing, the earlier tab shows.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kits → Tools export: the options the Settings screen saves. The seven numbers live in RankWeb's aw_seo_settings
 * (key search-insights), which RankWeb's export carries too; it is listed here as well so the file keeps it either way.
 *
 * @param array $names Option names.
 */
function insightweb_kits_export( $names ) {
	return array_merge( (array) $names, array( 'aw_seo_settings', 'aw_si_gdelt_on', 'aw_si_tool_seeds' ) );
}
add_filter( 'kits_settings_transfer_options', 'insightweb_kits_export' );

/**
 * Which new screen this request asks for: 'dashboard', 'settings', a tab of insightweb_tab_keys() or ''.
 */
function insightweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	if ( AW_SI_SLUG !== $page || ! function_exists( 'kits_shell_ui_next' ) || ! kits_shell_ui_next() ) {
		return '';
	}
	$tab = aw_si_tab();
	if ( function_exists( 'insightweb_tab_keys' ) && in_array( $tab, insightweb_tab_keys(), true ) ) {
		return $tab; // Keywords and the other tabs (insightweb-tabs.php)
	}
	return array(
		'overview' => 'dashboard',
		'settings' => 'settings',
	)[ $tab ] ?? '';
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function insightweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a new screen.
 *
 * @param string $screen 'dashboard' or 'settings'.
 */
function insightweb_ui_url( string $screen ): string {
	return add_query_arg( 'ui', 'next', aw_si_url( 'settings' === $screen ? 'settings' : 'overview' ) );
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = insightweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) || ! current_user_can( aw_si_cap() ) ) {
			return;
		}
		$main = INSIGHTWEB_DIR . 'insightweb.php';
		$head = get_file_data( $main, array( 'Version' => 'Version' ) );
		$tabs = array(
			array(
				'slug'    => 'dashboard',
				'label'   => __( 'Dashboard', 'aw-seo-extras' ),
				'url'     => insightweb_ui_url( 'dashboard' ),
				'current' => 'dashboard' === $screen,
			),
		);
		// Keywords and the other tabs, each on its own address (insightweb-tabs.php).
		foreach ( insightweb_tab_labels() as $key => $label ) {
			$tabs[] = array(
				'slug'    => $key,
				'label'   => $label,
				'url'     => aw_si_url( $key ),
				'current' => $key === $screen,
			);
		}
		$tabs[] = array(
			'slug'    => 'settings',
			'label'   => __( 'Settings', 'aw-seo-extras' ),
			'url'     => insightweb_ui_url( 'settings' ),
			'current' => 'settings' === $screen,
		);
		$ok     = kits_shell_app(
			array(
				'handle'      => 'insightweb-admin',
				'src'         => plugins_url( 'build/index.js', $main ),
				'asset'       => INSIGHTWEB_DIR . 'build/index.asset.php',
				'object'      => 'insightwebApp',
				'text_domain' => 'aw-seo-extras',
				'data'        => array(
					'screen'  => $screen,
					'name'    => 'InsightWeb',
					'version' => (string) $head['Version'],
					'icon'    => plugins_url( 'assets/icon.svg', $main ),
					'tabs'    => $tabs,
					'rest'    => INSIGHTWEB_REST,
					'admin'   => current_user_can( 'manage_options' ),
					'boxes'   => 'dashboard' === $screen ? insightweb_box_list() : array(), // the Overview boxes (insightweb-boxes.php)
					'title'   => insightweb_tab_labels()[ $screen ] ?? '',
					// The tab's own output as parts, drawn after its load hook has run (insightweb-tabs.php).
					'parts'   => insightweb_tab_parts( $screen ),
					// Settings: schedule, keys, seed, Run now, snapshots, imports and Delete data, from the Settings tab's own output.
					'more'    => 'settings' === $screen ? insightweb_settings_more_parts() : array(),
				),
			)
		);
		insightweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of the current tab, when it is ready. aw_si_screen() calls this first.
 *
 * @param string $tab The current tab.
 * @return bool True when the new screen was drawn.
 */
function insightweb_ui_page( string $tab ): bool {
	$screen = insightweb_ui_wanted();
	$want = array(
		'overview' => 'dashboard',
		'settings' => 'settings',
	)[ $tab ] ?? $tab;
	if ( '' === $screen || ! insightweb_ui_ready() || $want !== $screen ) {
		return false;
	}
	$title = array(
		'dashboard' => __( 'Search Insights', 'aw-seo-extras' ),
		'settings'  => __( 'Search Insights settings', 'aw-seo-extras' ),
	)[ $screen ] ?? ( insightweb_tab_labels()[ $screen ] ?? __( 'Search Insights', 'aw-seo-extras' ) );
	kits_shell_mount( 'insightweb-app', $title );
	return true;
}
