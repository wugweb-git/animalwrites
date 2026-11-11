<?php
/**
 * PlanWeb's new screens on the Kits admin shell (trial), all on the Calendar's own address with &ui=next.
 *
 * Address, screen and capability:
 *   admin.php?page=planweb&ui=next               Dashboard  edit_planweb_entries
 *   admin.php?page=planweb&ui=next&tab=entries   Entries    edit_planweb_entries
 *   admin.php?page=planweb&ui=next&tab=settings  Settings   manage_options
 *
 * Without &ui=next the Calendar shows, as before; it stays the default until the owner switches. One script
 * (build/index.js, from src/) draws the three screens with Kits Core's shell parts, fed by the planweb/v1 routes
 * (planweb-rest.php). When Kits Core or the build file is missing, &ui=next shows the Calendar.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which new screen this request asks for: 'dashboard', 'entries', 'settings' or ''.
 */
function planweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
	$ui   = sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same.
	if ( 'classic' === $ui || PLANWEB_MENU !== $page ) {
		return '';
	}
	// The month calendar, its edit form and the notice after a save are only on the earlier screen.
	foreach ( array( 'm', 'edit', 'done', 'error' ) as $k ) {
		if ( isset( $_GET[ $k ] ) && '' === $tab ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show.
			return '';
		}
	}
	return in_array( $tab, array( 'entries', 'settings' ), true ) ? $tab : 'dashboard';
}

/**
 * The capability a new screen needs.
 *
 * @param string $screen 'dashboard', 'entries' or 'settings'.
 */
function planweb_ui_cap( string $screen ): string {
	return 'settings' === $screen ? 'manage_options' : 'edit_planweb_entries';
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function planweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a new screen.
 *
 * @param string $screen 'dashboard', 'entries' or 'settings'.
 * @param array  $args   More query arguments (the list's filters).
 */
function planweb_ui_url( string $screen, array $args = array() ): string {
	$base = array( 'ui' => 'next' );
	if ( 'dashboard' !== $screen ) {
		$base['tab'] = $screen;
	}
	return planweb_url( array_merge( $base, $args ) );
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = planweb_ui_wanted();
		if ( '' === $screen || 'toplevel_page_' . PLANWEB_MENU !== $hook || ! planweb_on() || ! function_exists( 'kits_shell_app' ) || ! current_user_can( planweb_ui_cap( $screen ) ) ) {
			return;
		}
		$tabs = array();
		foreach ( array(
			'dashboard' => __( 'Dashboard', 'planweb' ),
			'entries'   => __( 'Entries', 'planweb' ),
			'settings'  => __( 'Settings', 'planweb' ),
		) as $slug => $label ) {
			if ( current_user_can( planweb_ui_cap( $slug ) ) ) {
				$tabs[] = array(
					'slug'    => $slug,
					'label'   => $label,
					'url'     => planweb_ui_url( $slug ),
					'current' => $slug === $screen,
				);
			}
		}
		$list = fn( array $values ) => array_map(
			fn( $slug, $label ) => array(
				'value' => (string) $slug,
				'label' => $label,
			),
			array_keys( $values ),
			array_values( $values )
		);
		$ok   = kits_shell_app(
			array(
				'handle'      => 'planweb-admin',
				'src'         => PLANWEB_URL . 'build/index.js',
				'asset'       => PLANWEB_DIR . 'build/index.asset.php',
				'object'      => 'planwebApp',
				'text_domain' => 'planweb',
				'data'        => array(
					'screen'      => $screen,
					'name'        => 'PlanWeb',
					'version'     => PLANWEB_VERSION,
					'icon'        => PLANWEB_URL . 'assets/icon.svg',
					'tabs'        => $tabs,
					'oldUrl'      => add_query_arg( 'ui', 'classic', remove_query_arg( 'tab' ) ),
					'rest'        => PLANWEB_REST,
					'entriesUrl'  => planweb_ui_url( 'entries' ),
					'today'       => wp_date( 'Y-m-d' ),
					'canApprove'  => current_user_can( 'planweb_approve' ),
					'lists'       => array(
						'types'    => $list( PLANWEB_TYPES ),
						'channels' => $list( PLANWEB_CHANNELS ),
						'owners'   => $list( PLANWEB_OWNERS ),
						'statuses' => $list( PLANWEB_STATUSES ),
					),
					'approveOnly' => PLANWEB_APPROVE_STATUSES,
				),
			)
		);
		planweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of the Calendar, when it is ready. planweb_screen() calls this first.
 *
 * @return bool True when the new screen was drawn.
 */
function planweb_ui_page(): bool {
	$screen = planweb_ui_wanted();
	if ( '' === $screen || ! planweb_ui_ready() ) {
		return false;
	}
	$titles = array(
		'dashboard' => __( 'PlanWeb', 'planweb' ),
		'entries'   => __( 'PlanWeb entries', 'planweb' ),
		'settings'  => __( 'PlanWeb settings', 'planweb' ),
	);
	kits_shell_mount( 'planweb-app', $titles[ $screen ], add_query_arg( 'ui', 'classic', remove_query_arg( 'tab' ) ), __( 'Open the current screen', 'planweb' ) );
	return true;
}
