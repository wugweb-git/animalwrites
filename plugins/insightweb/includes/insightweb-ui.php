<?php
/**
 * InsightWeb's new screens on the Kits admin shell (trial):
 * - admin.php?page=aw-search-insights&ui=next: the Dashboard;
 * - admin.php?page=aw-search-insights&tab=settings&ui=next: Settings.
 *
 * Without &ui=next the current tabs show, as before; they stay the default until the owner switches. The screens are one
 * script (build/index.js, from src/), drawn with Kits Core's shell parts (kits-core/includes/admin-shell.php) and fed
 * by the insightweb/v1 routes (insightweb-rest.php). When Kits Core or a build file is missing, &ui=next shows the
 * current tab.
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
 * Which new screen this request asks for: 'dashboard', 'settings' or ''.
 */
function insightweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	if ( AW_SI_SLUG !== $page || ! function_exists( 'kits_shell_ui_next' ) || ! kits_shell_ui_next() ) {
		return '';
	}
	return array(
		'overview' => 'dashboard',
		'settings' => 'settings',
	)[ aw_si_tab() ] ?? '';
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
			array(
				'slug'    => 'keywords',
				'label'   => __( 'Keywords', 'aw-seo-extras' ),
				'url'     => aw_si_url( 'keywords' ),
				'current' => false,
			),
		);
		// The other tabs open the current screens, as on today's tab row.
		foreach ( aw_si_extra_tabs() as $key => $label ) {
			$tabs[] = array(
				'slug'    => $key,
				'label'   => $label,
				'url'     => aw_si_url( $key ),
				'current' => false,
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
					'oldUrl'  => add_query_arg( 'ui', 'classic' ),
					'rest'    => INSIGHTWEB_REST,
					'admin'   => current_user_can( 'manage_options' ),
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
	if ( '' === $screen || ! insightweb_ui_ready() || ( 'settings' === $screen ) !== ( 'settings' === $tab ) ) {
		return false;
	}
	kits_shell_mount( 'insightweb-app', 'settings' === $screen ? __( 'Search Insights settings', 'aw-seo-extras' ) : __( 'Search Insights', 'aw-seo-extras' ), add_query_arg( 'ui', 'classic' ), __( 'Open the current screen', 'aw-seo-extras' ) );
	return true;
}
