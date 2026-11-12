<?php
/**
 * DirectoryWeb's screens on the Kits admin shell (docs/directory-plugin-plan.md §2), the default since 12 Nov 2026.
 *
 * Addresses:
 *   admin.php?page=directoryweb                              Dashboard   edit_aw_volunteers
 *   edit.php?post_type=aw_listing&page=aw-claims              Claims      edit_aw_volunteers (menu: Fluent Forms → Claims)
 *   admin.php?page=directoryweb-settings                      Settings    manage_options
 *
 * &ui=classic (or one claim, or the notice after a review) shows the earlier Claims screen. The Dashboard and Settings
 * addresses are new and not in the menu; on &ui=classic they show a short page with the way in. The screens are one
 * script (directoryweb/build/index.js, from directoryweb/src/), drawn with Kits Core's shell parts and fed by the
 * directoryweb/v1 routes (directoryweb-rest.php). When Kits Core's shell or the build file is missing, the earlier
 * screen shows.
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_PAGE          = 'directoryweb';
const DIRECTORYWEB_SETTINGS_PAGE = 'directoryweb-settings';

/** Which screen each page is, with its capability. */
function directoryweb_ui_screens(): array {
	return array(
		DIRECTORYWEB_PAGE          => array( 'dashboard', 'edit_aw_volunteers' ),
		'aw-claims'                => array( 'claims', 'edit_aw_volunteers' ),
		DIRECTORYWEB_SETTINGS_PAGE => array( 'settings', 'manage_options' ),
		DIRECTORYWEB_LISTINGS_PAGE => array( 'listings', DIRECTORYWEB_LISTINGS_CAP ),
	);
}

/**
 * Which new screen this request asks for: 'dashboard', 'claims', 'listings', 'settings' or ''.
 */
function directoryweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	$ui   = sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
	if ( 'classic' === $ui ) {
		return '';
	}
	// One claim to review, and the notice after a review, are only on the earlier Claims screen.
	if ( 'aw-claims' === $page && ( isset( $_GET['entry'] ) || isset( $_GET['done'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
		return '';
	}
	return directoryweb_ui_screens()[ $page ][0] ?? '';
}

/**
 * The new screen is on this request (its script enqueued). Set on admin_enqueue_scripts.
 *
 * @param bool|null $set Set the flag.
 */
function directoryweb_ui_ready( ?bool $set = null ): bool {
	static $ready = false;
	if ( null !== $set ) {
		$ready = $set;
	}
	return $ready;
}

/**
 * The address of a screen.
 *
 * @param string $screen 'dashboard', 'claims', 'listings' or 'settings'.
 */
function directoryweb_ui_url( string $screen ): string {
	$pages = array(
		'settings' => DIRECTORYWEB_SETTINGS_PAGE,
		'listings' => DIRECTORYWEB_LISTINGS_PAGE,
	);
	if ( 'claims' === $screen ) {
		return add_query_arg(
			array(
				'post_type' => 'aw_listing',
				'page'      => 'aw-claims',
				'ui'        => 'next',
			),
			admin_url( 'edit.php' )
		);
	}
	return add_query_arg(
		array(
			'page' => $pages[ $screen ] ?? DIRECTORYWEB_PAGE,
			'ui'   => 'next',
		),
		admin_url( 'admin.php' )
	);
}

// Plugins → DirectoryWeb opens the Dashboard (owner, 12 Nov 2026: "Yes, add it", as the other Kits plugins); Settings
// opens from the Dashboard and lights the same item (kits-core admin-fold.php). Claims: Fluent Forms → Claims (claims-review.php).
add_action(
	'admin_menu',
	function () {
		if ( function_exists( 'kits_admin_fold_menu' ) ) {
			kits_admin_fold_menu( DIRECTORYWEB_PAGE, 'plugins.php', __( 'DirectoryWeb', 'directoryweb' ), 'edit_aw_volunteers', 'directoryweb', dirname( __DIR__ ) . '/directoryweb/menu-icon.svg' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- the Directory's own capability (volunteers.php)
		} else {
			add_menu_page( __( 'DirectoryWeb', 'directoryweb' ), __( 'DirectoryWeb', 'directoryweb' ), 'edit_aw_volunteers', DIRECTORYWEB_PAGE, 'directoryweb_dashboard_page', 'dashicons-location-alt', '58.2' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- as above
		}
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'DirectoryWeb', 'directoryweb' ), __( 'Dashboard', 'directoryweb' ), 'edit_aw_volunteers', DIRECTORYWEB_PAGE, 'directoryweb_dashboard_page' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- as above, as Directory → Claims
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'Directory listings', 'directoryweb' ), __( 'Listings', 'directoryweb' ), DIRECTORYWEB_LISTINGS_CAP, DIRECTORYWEB_LISTINGS_PAGE, 'directoryweb_listings_page' );
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'DirectoryWeb settings', 'directoryweb' ), __( 'Settings', 'directoryweb' ), 'manage_options', DIRECTORYWEB_SETTINGS_PAGE, 'directoryweb_settings_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = directoryweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'kits_shell_app' ) ) {
			return;
		}
		$caps = array_column( directoryweb_ui_screens(), 1, 0 );
		if ( ! current_user_can( $caps[ $screen ] ) ) {
			return;
		}
		$tabs = array(
			array(
				'slug'    => 'dashboard',
				'label'   => __( 'Dashboard', 'directoryweb' ),
				'url'     => directoryweb_ui_url( 'dashboard' ),
				'current' => 'dashboard' === $screen,
			),
			array(
				'slug'    => 'claims',
				'label'   => __( 'Claims', 'directoryweb' ),
				'url'     => directoryweb_ui_url( 'claims' ),
				'current' => 'claims' === $screen,
			),
			array(
				'slug'    => 'listings',
				'label'   => __( 'Listings', 'directoryweb' ),
				'url'     => current_user_can( DIRECTORYWEB_LISTINGS_CAP ) ? directoryweb_ui_url( 'listings' ) : add_query_arg( 'post_type', 'aw_listing', admin_url( 'edit.php' ) ),
				'current' => 'listings' === $screen,
			),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$tabs[] = array(
				'slug'    => 'settings',
				'label'   => __( 'Settings', 'directoryweb' ),
				'url'     => directoryweb_ui_url( 'settings' ),
				'current' => 'settings' === $screen,
			);
			$tabs[] = array(
				'slug'    => 'place-intros',
				'label'   => __( 'Place intros', 'directoryweb' ),
				'url'     => aw_place_intros_url(), // a core screen (place-intros.php)
				'current' => false,
			);
		}
		$ok = kits_shell_app(
			array(
				'handle'      => 'directoryweb-admin',
				'src'         => plugins_url( 'directoryweb/build/index.js', AW_CORE_DIR . 'animal-writes-core.php' ),
				'asset'       => AW_CORE_DIR . 'directoryweb/build/index.asset.php',
				'object'      => 'directorywebApp',
				'text_domain' => 'directoryweb',
				'data'        => array(
					'screen'   => $screen,
					'name'     => 'DirectoryWeb',
					'version'  => AW_CORE_VERSION,
					'icon'     => plugins_url( 'directoryweb/icon.svg', AW_CORE_DIR . 'animal-writes-core.php' ),
					'tabs'     => $tabs,
					'listings' => add_query_arg( 'post_type', 'aw_listing', admin_url( 'edit.php' ) ),
					'rest'     => DIRECTORYWEB_REST,
				),
			)
		);
		directoryweb_ui_ready( $ok );
	}
);

/**
 * Draw the new screen in place of the current one, when it is ready. The page callbacks call this first.
 *
 * @param string $screen 'dashboard', 'claims', 'listings' or 'settings'.
 * @return bool True when the new screen was drawn.
 */
function directoryweb_ui_page( string $screen ): bool {
	if ( directoryweb_ui_wanted() !== $screen || ! directoryweb_ui_ready() ) {
		return false;
	}
	$titles = array(
		'dashboard' => __( 'DirectoryWeb', 'directoryweb' ),
		'claims'    => __( 'Claims', 'directoryweb' ),
		'listings'  => __( 'Directory listings', 'directoryweb' ),
		'settings'  => __( 'DirectoryWeb settings', 'directoryweb' ),
	);
	kits_shell_mount( 'directoryweb-app', $titles[ $screen ] );
	return true;
}

/**
 * The page for a new address on &ui=classic (or without the shell): its name and the way in.
 *
 * @param string $title  Heading.
 * @param string $screen 'dashboard', 'listings' or 'settings'.
 */
function directoryweb_plain_page( string $title, string $screen ): void {
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	if ( function_exists( 'kits_shell_app' ) ) {
		echo '<p><a class="button button-primary" href="' . esc_url( directoryweb_ui_url( $screen ) ) . '">' . esc_html__( 'Open the new screen', 'directoryweb' ) . '</a></p>';
	} else {
		wp_admin_notice( esc_html__( 'This screen needs Kits Core.', 'directoryweb' ), array( 'type' => 'warning', 'additional_classes' => array( 'inline' ) ) );
	}
	echo '<p><a href="' . esc_url( add_query_arg( 'post_type', 'aw_listing', admin_url( 'edit.php' ) ) ) . '">' . esc_html__( 'Directory listings', 'directoryweb' ) . '</a></p></div>';
}

// Kits → Tools export: only the claim form's ID from the site's form map (owner, 12 Nov 2026), the one value the new
// Settings saves. On import only that key changes; every other form ID stays as stored.
add_filter(
	'kits_settings_transfer_options',
	function ( $names ) {
		return array_values( array_unique( array_merge( (array) $names, array( directoryweb_forms_option() ) ) ) );
	}
);
add_filter(
	'kits_settings_transfer_value',
	function ( $value, $name ) {
		if ( directoryweb_forms_option() !== $name ) {
			return $value;
		}
		return array( 'claim' => absint( ( (array) $value )['claim'] ?? 0 ) );
	},
	10,
	2
);
add_filter(
	'kits_settings_transfer_import_value',
	function ( $value, $name, $stored ) {
		if ( directoryweb_forms_option() !== $name ) {
			return $value;
		}
		$stored = (array) $stored;
		$claim  = absint( ( (array) $value )['claim'] ?? 0 );
		if ( $claim ) {
			$stored['claim'] = $claim;
		}
		return $stored;
	},
	10,
	3
);

/** The option that maps form keys to Fluent Forms IDs. */
function directoryweb_forms_option(): string {
	return defined( 'AW_FF_OPTION' ) ? AW_FF_OPTION : 'aw_ff_forms';
}

/** Dashboard page callback. */
function directoryweb_dashboard_page(): void {
	if ( directoryweb_ui_page( 'dashboard' ) ) {
		return;
	}
	directoryweb_plain_page( __( 'DirectoryWeb', 'directoryweb' ), 'dashboard' );
}

/** Settings page callback. */
function directoryweb_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'directoryweb' ), 403 );
	}
	if ( directoryweb_ui_page( 'settings' ) ) {
		return;
	}
	directoryweb_plain_page( __( 'DirectoryWeb settings', 'directoryweb' ), 'settings' );
}
