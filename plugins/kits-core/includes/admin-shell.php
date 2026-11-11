<?php
/**
 * Kits admin shell: the shared parts plugin screens are built from (docs/kits-plugin-ui.md).
 *
 * Kits Core registers one script and one stylesheet, both with the handle `kits-shell` (built from src/shell/ into
 * build/shell/, npm run build:shell). The script sets window.kits.shell: app header, tile, chip row, section card,
 * settings group, service row, notice, and the empty, loading and error states. A plugin builds its own screen script
 * against it and puts the screen in place with two calls:
 *
 *     kits_shell_app( array( 'handle' => …, 'src' => …, 'asset' => …, 'object' => …, 'data' => … ) ); // enqueue
 *     kits_shell_mount( 'my-app', __( 'My plugin', 'my-domain' ) );
 *
 * Nothing here adds a menu or a screen of its own.
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The shell's version, from its build (changes whenever the built file changes). */
function kits_shell_asset(): array {
	$file = KITS_CORE_DIR . 'build/shell/index.asset.php';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$a = include $file;
	return is_array( $a ) ? $a : array();
}

// Registered on every admin screen (cheap: nothing is printed until a screen enqueues it).
add_action(
	'admin_enqueue_scripts',
	function () {
		$a = kits_shell_asset();
		if ( ! $a ) {
			return;
		}
		$base = plugins_url( 'build/shell/', KITS_CORE_DIR . 'kits-core.php' );
		wp_register_script( 'kits-shell', $base . 'index.js', $a['dependencies'] ?? array(), $a['version'] ?? false, true );
		wp_register_style( 'kits-shell', $base . 'index.css', array(), $a['version'] ?? false );
		wp_set_script_translations( 'kits-shell', 'kits-core' );
	},
	1
);

/**
 * Enqueue a plugin screen built on the shell. Call it from admin_enqueue_scripts, on the plugin's own screen only.
 *
 * @param array $args {
 *     The screen.
 *
 *     @type string $handle      The screen script's handle.
 *     @type string $src         Its URL.
 *     @type string $asset       Path of its .asset.php (dependencies and version from @wordpress/scripts).
 *     @type string $object      Global the data goes in (window.<object>).
 *     @type array  $data        The screen's data.
 *     @type string $text_domain Translations of the script.
 *     @type string $lang_path   Folder of the script's JSON translations (optional).
 * }
 * @return bool False when the shell, the screen script or a script they need is missing (the caller shows its current
 *              screen).
 */
function kits_shell_app( array $args ): bool {
	if ( ! wp_script_is( 'kits-shell', 'registered' ) || empty( $args['handle'] ) || empty( $args['src'] ) || empty( $args['asset'] ) || ! is_readable( $args['asset'] ) ) {
		return false;
	}
	$a     = include $args['asset'];
	$deps  = array_values( array_unique( array_merge( (array) ( $a['dependencies'] ?? array() ), array( 'kits-shell' ) ) ) );
	$shell = wp_scripts()->registered['kits-shell']->deps ?? array();
	// A script WordPress does not have (react-jsx-runtime is WordPress 6.6+) would leave the screen empty: show the current one.
	foreach ( array_merge( $deps, (array) $shell ) as $dep ) {
		if ( ! wp_script_is( $dep, 'registered' ) ) {
			return false;
		}
	}
	wp_enqueue_style( 'kits-shell' );
	wp_enqueue_script( $args['handle'], $args['src'], $deps, $a['version'] ?? false, true );
	if ( ! empty( $args['text_domain'] ) ) {
		wp_set_script_translations( $args['handle'], $args['text_domain'], $args['lang_path'] ?? '' );
	}
	if ( ! empty( $args['object'] ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $args['object'] ) ) {
		wp_add_inline_script( $args['handle'], 'window.' . $args['object'] . ' = ' . wp_json_encode( (array) ( $args['data'] ?? array() ) ) . ';', 'before' );
	}
	add_filter( 'admin_body_class', 'kits_shell_body_class' );
	kits_shell_screen_plain();
	return true;
}

/**
 * A shell screen has no Screen Options or Help tab: they belong to the earlier screen's list tables and do nothing here.
 * Core's own switches: the screen_options_show_screen filter and WP_Screen::remove_help_tabs().
 */
function kits_shell_screen_plain(): void {
	add_filter( 'screen_options_show_screen', '__return_false', 99 );
	add_action(
		'admin_head',
		function () {
			$screen = get_current_screen();
			if ( $screen ) {
				$screen->remove_help_tabs();
			}
		},
		99
	);
}

/**
 * The body class that lets the shell run edge to edge.
 *
 * @param string $classes Admin body classes.
 */
function kits_shell_body_class( $classes ) {
	return trim( (string) $classes . ' kits-shell-screen' );
}

/**
 * The mount point of a shell screen, with a note when JavaScript does not run.
 *
 * @param string $id    Element id the script renders into.
 * @param string $title Screen title (the no-script heading).
 */
function kits_shell_mount( string $id, string $title ): void {
	// Notices from other plugins go after .wp-header-end, so they stay out of the app and its pinned header.
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><hr class="wp-header-end"><div id="' . esc_attr( $id ) . '">';
	echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'kits-core' ) . '</p></noscript>';
	echo '</div></div>';
}

/**
 * Every Kits plugin screen shows its new look by default (owner, 12 Nov 2026: "Yes, switch"); &ui=classic on the
 * same address shows the earlier screen (nothing links to it: owner, 12 Nov 2026).
 */
function kits_shell_ui_next(): bool {
	return 'classic' !== sanitize_key( wp_unslash( $_GET['ui'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen to show; nothing is saved here.
}

/**
 * The permission check of a shell screen's REST route (docs/kits-plugin-ui.md, Data):
 * 401 when signed out; 403 when a browser request has no valid wp_rest nonce (an Application Password request has
 * none); 403 without the capability.
 *
 * @param string $cap Capability the route needs.
 * @return callable A permission_callback.
 */
function kits_shell_rest_can( string $cap ): callable {
	return function ( $request ) use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to use this.', 'kits-core' ), array( 'status' => 401 ) );
		}
		$app = function_exists( 'rest_get_authenticated_app_password' ) && null !== rest_get_authenticated_app_password();
		if ( ! $app ) {
			$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?? $request->get_param( '_wpnonce' ) ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error( 'rest_cookie_invalid_nonce', __( 'The page has expired. Reload it and try again.', 'kits-core' ), array( 'status' => 403 ) );
			}
		}
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do this.', 'kits-core' ), array( 'status' => 403 ) );
		}
		return true;
	};
}
