<?php
/**
 * InsightWeb admin: a standalone top-level menu and a generic, filterable tab set. In this I1 skeleton the tab set is
 * empty; insights modules register tabs through the `insightweb_tabs` filter as the engine ports. No Kits Core and no
 * admin shell here — the shell-backed screens are staged behind the shared `insightweb-shell` package.
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The capability InsightWeb's screens require. A dedicated capability is granted from manage_options in a later phase;
 * for now it maps to manage_options so the skeleton is self-contained.
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
 * The generic tab set. Empty in the skeleton; each insights module adds `array( 'id' => ..., 'title' => ... )`.
 *
 * @return array<int, array<string, string>>
 */
function insightweb_tabs(): array {
	/**
	 * Filter the InsightWeb admin tabs.
	 *
	 * @param array<int, array<string, string>> $tabs The tabs.
	 */
	return (array) apply_filters( 'insightweb_tabs', array() );
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
 * Register the top-level InsightWeb menu.
 *
 * @return void
 */
function insightweb_admin_menu(): void {
	add_menu_page(
		__( 'InsightWeb', 'insightweb' ),
		__( 'InsightWeb', 'insightweb' ),
		insightweb_cap(),
		'insightweb',
		'insightweb_render_screen',
		'dashicons-chart-line'
	);
}

/**
 * Render the InsightWeb admin screen: the Site Kit connection prompt and the (currently empty) tab set.
 *
 * @return void
 */
function insightweb_render_screen(): void {
	if ( ! current_user_can( insightweb_cap() ) ) {
		return;
	}
	$tabs = insightweb_tabs();
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'InsightWeb', 'insightweb' ) . '</h1>';
	if ( ! insightweb_site_kit_active() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'InsightWeb reads Search Console and GA4 through Site Kit by Google. Activate and connect Site Kit to see your data.', 'insightweb' ) . '</p></div>';
	}
	if ( ! $tabs ) {
		echo '<p>' . esc_html__( 'No insights modules are active yet.', 'insightweb' ) . '</p>';
	} else {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab ) {
			echo '<span class="nav-tab">' . esc_html( (string) ( $tab['title'] ?? '' ) ) . '</span>';
		}
		echo '</h2>';
	}
	echo '</div>';
}
