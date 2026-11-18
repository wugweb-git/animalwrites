<?php
/**
 * DirectoryWeb's screens on the bundled admin shell.
 *
 * Addresses:
 *   admin.php?page=directoryweb                              Dashboard   manage_directoryweb (directoryweb-caps.php)
 *   edit.php?post_type=directoryweb_listing&page=directoryweb-claims              Claims      manage_directoryweb (menu: Fluent Forms → Claims)
 *   admin.php?page=directoryweb-settings                      Settings    manage_options
 *
 * One claim (&entry=) and the notice after a review (&done=) are server-rendered on the Claims address
 * (claims-review.php). The screens are one script (directoryweb/build/index.js, from directoryweb/src/), drawn with
 * the bundled shell's parts and fed by the directoryweb/v1 routes (directoryweb-rest.php). When the shell or the
 * build file is missing, each screen shows one notice saying so.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIRECTORYWEB_PAGE          = 'directoryweb';
const DIRECTORYWEB_SETTINGS_PAGE = 'directoryweb-settings';

/** Which screen each page is, with its capability. */
function directoryweb_ui_screens(): array {
	return array(
		DIRECTORYWEB_PAGE          => array( 'dashboard', DIRECTORYWEB_CAP ),
		'directoryweb-claims'                => array( 'claims', DIRECTORYWEB_CAP ),
		DIRECTORYWEB_SETTINGS_PAGE => array( 'settings', 'manage_options' ),
		DIRECTORYWEB_LISTINGS_PAGE => array( 'listings', DIRECTORYWEB_LISTINGS_CAP ),
	);
}

/**
 * Which new screen this request asks for: 'dashboard', 'claims', 'listings', 'settings' or ''.
 */
function directoryweb_ui_wanted(): string {
	$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here
	// One claim to review, and the notice after a review, are server-rendered (claims-review.php).
	if ( 'directoryweb-claims' === $page && ( isset( $_GET['entry'] ) || isset( $_GET['done'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same
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
				'post_type' => 'directoryweb_listing',
				'page'      => 'directoryweb-claims',
			),
			admin_url( 'edit.php' )
		);
	}
	return add_query_arg( 'page', $pages[ $screen ] ?? DIRECTORYWEB_PAGE, admin_url( 'admin.php' ) );
}

// Plugins → DirectoryWeb opens the Dashboard; Settings
// opens from the Dashboard and lights the same item. Claims: Fluent Forms → Claims (claims-review.php).
add_action(
	'admin_menu',
	function () {
				// The icon is the plugin's one-colour SVG as a data URI, which WordPress paints in the admin colour scheme.
		$icon = DIRECTORYWEB_DIR . 'assets/menu-icon.svg';
		$icon = is_readable( $icon ) ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $icon ) ) : 'dashicons-location-alt'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own small SVG file.
		add_menu_page( __( 'DirectoryWeb', 'directoryweb' ), __( 'DirectoryWeb', 'directoryweb' ), DIRECTORYWEB_CAP, DIRECTORYWEB_PAGE, 'directoryweb_dashboard_page', $icon, 26 ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- DirectoryWeb's own capability (directoryweb-caps.php)
		$GLOBALS['admin_page_hooks'][ DIRECTORYWEB_PAGE ] = 'directoryweb'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the screens' hook prefix (directoryweb_page_…), whatever the title.
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'DirectoryWeb', 'directoryweb' ), __( 'Dashboard', 'directoryweb' ), DIRECTORYWEB_CAP, DIRECTORYWEB_PAGE, 'directoryweb_dashboard_page' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- as above
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'Directory listings', 'directoryweb' ), __( 'Listings', 'directoryweb' ), DIRECTORYWEB_LISTINGS_CAP, DIRECTORYWEB_LISTINGS_PAGE, 'directoryweb_listings_page' );
		add_submenu_page( DIRECTORYWEB_PAGE, __( 'DirectoryWeb settings', 'directoryweb' ), __( 'Settings', 'directoryweb' ), 'manage_options', DIRECTORYWEB_SETTINGS_PAGE, 'directoryweb_settings_page' );
	}
);

// One top-level menu for the plugin (WordPress guideline): the listing post type has no menu of its own (its own menu
// is off — directoryweb_content_type_args). Its native screens — All listings, Add listing and the editor (the
// one-pager meta box) — still open at their URLs; light DirectoryWeb → Listings while they are open, so they read as
// part of this one menu instead of orphaning the highlight.
add_filter(
	'parent_file',
	function ( $parent ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'directoryweb_listing' === $screen->post_type ? DIRECTORYWEB_PAGE : $parent;
	}
);
add_filter(
	'submenu_file',
	function ( $submenu ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'directoryweb_listing' === $screen->post_type ? DIRECTORYWEB_LISTINGS_PAGE : $submenu;
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = directoryweb_ui_wanted();
		if ( '' === $screen || ! function_exists( 'directoryweb_shell_app' ) ) {
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
				'url'     => current_user_can( DIRECTORYWEB_LISTINGS_CAP ) ? directoryweb_ui_url( 'listings' ) : add_query_arg( 'post_type', 'directoryweb_listing', admin_url( 'edit.php' ) ),
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
			$intros_url = directoryweb_place_intros_url(); // an optional screen a host site may add; empty in the standalone plugin.
			if ( '' !== $intros_url ) {
				$tabs[] = array(
					'slug'    => 'place-intros',
					'label'   => __( 'Place intros', 'directoryweb' ),
					'url'     => $intros_url,
					'current' => false,
				);
			}
		}
		$ok = directoryweb_shell_app(
			array(
				'handle'      => 'directoryweb-admin',
				'src'         => plugins_url( 'build/index.js', DIRECTORYWEB_FILE ),
				'asset'       => DIRECTORYWEB_DIR . 'build/index.asset.php',
				'object'      => 'directorywebApp',
				'text_domain' => 'directoryweb',
				'data'        => array(
					'screen'   => $screen,
					'name'     => 'DirectoryWeb',
					'version'  => DIRECTORYWEB_VERSION,
					'icon'     => plugins_url( 'assets/icon.svg', DIRECTORYWEB_FILE ),
					'tabs'     => $tabs,
					'listings' => add_query_arg( 'post_type', 'directoryweb_listing', admin_url( 'edit.php' ) ),
					'rest'     => DIRECTORYWEB_REST,
				),
			)
		);
		if ( $ok ) {
			// The plugin's own screen layout (layout only; the bundled shell is untouched), loaded after the shell.
			wp_enqueue_style( 'directoryweb-admin', plugins_url( 'assets/css/directoryweb-admin.css', DIRECTORYWEB_FILE ), array( 'directoryweb-shell' ), DIRECTORYWEB_VERSION );
		}
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
	if ( directoryweb_ui_wanted() !== $screen || ! directoryweb_ui_ready() || ! function_exists( 'directoryweb_shell_mount' ) ) {
		return false;
	}
	$titles = array(
		'dashboard' => __( 'DirectoryWeb', 'directoryweb' ),
		'claims'    => __( 'Claims', 'directoryweb' ),
		'listings'  => __( 'Directory listings', 'directoryweb' ),
		'settings'  => __( 'DirectoryWeb settings', 'directoryweb' ),
	);
	directoryweb_shell_mount( 'directoryweb-app', $titles[ $screen ] );
	return true;
}

/**
 * The page when the screen cannot be drawn (the shell or the build missing): its name and one notice.
 *
 * @param string $title Heading.
 */
function directoryweb_missing_page( string $title ): void {
	echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	wp_admin_notice( esc_html__( 'The DirectoryWeb admin screens need their build files (build/shell and build/index.js). Reinstall the plugin.', 'directoryweb' ), array( 'type' => 'error' ) );
	echo '</div>';
}


/** The option that maps form keys to Fluent Forms IDs. */
function directoryweb_forms_option(): string {
	return defined( 'DIRECTORYWEB_FF_OPTION' ) ? DIRECTORYWEB_FF_OPTION : 'directoryweb_ff_forms';
}

/** Dashboard page callback. */
function directoryweb_dashboard_page(): void {
	if ( directoryweb_ui_page( 'dashboard' ) ) {
		return;
	}
	directoryweb_missing_page( __( 'DirectoryWeb', 'directoryweb' ) );
}

/** Settings page callback. */
function directoryweb_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'directoryweb' ), 403 );
	}
	if ( directoryweb_ui_page( 'settings' ) ) {
		return;
	}
	directoryweb_missing_page( __( 'DirectoryWeb settings', 'directoryweb' ) );
}
