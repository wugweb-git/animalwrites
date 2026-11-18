<?php
/**
 * The admin shell, registered from the bundled directoryweb-shell UI package (packages/directoryweb-shell, built into
 * build/shell/). This is the Kits admin shell extracted verbatim; the plugin ships it, so there is no runtime
 * dependency on any companion plugin. One script and one stylesheet, both with the namespaced handle `directoryweb-shell`, which sets
 * window.directoryweb.shell.
 *
 * A screen is put in place with two calls: directoryweb_shell_app() enqueues the shell and the screen's own script, and
 * directoryweb_shell_mount() prints the element the script renders into.
 *
 * @package directoryweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The shell's build asset (dependencies and version), or an empty array when the build is missing. */
function directoryweb_shell_asset(): array {
	$file = DIRECTORYWEB_DIR . 'build/shell/index.asset.php';
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
		$a = directoryweb_shell_asset();
		if ( ! $a ) {
			return;
		}
		$base = plugins_url( 'build/shell/', DIRECTORYWEB_FILE );
		wp_register_script( 'directoryweb-shell', $base . 'index.js', $a['dependencies'] ?? array(), $a['version'] ?? false, true );
		wp_register_style( 'directoryweb-shell', $base . 'index.css', wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-components', 'wp-theme' ) : array( 'wp-components' ), $a['version'] ?? false );
		wp_set_script_translations( 'directoryweb-shell', 'directoryweb' );
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
 * }
 * @return bool False when the shell, the screen script or a script they need is missing (the caller shows a notice).
 */
function directoryweb_shell_app( array $args ): bool {
	if ( ! wp_script_is( 'directoryweb-shell', 'registered' ) || empty( $args['handle'] ) || empty( $args['src'] ) || empty( $args['asset'] ) || ! is_readable( $args['asset'] ) ) {
		return false;
	}
	$a     = include $args['asset'];
	$deps  = array_values( array_unique( array_merge( (array) ( $a['dependencies'] ?? array() ), array( 'directoryweb-shell' ) ) ) );
	$shell = wp_scripts()->registered['directoryweb-shell']->deps ?? array();
	// A script WordPress does not have (react-jsx-runtime is WordPress 6.6+) would leave the screen empty: show a notice.
	foreach ( array_merge( $deps, (array) $shell ) as $dep ) {
		if ( ! wp_script_is( $dep, 'registered' ) ) {
			return false;
		}
	}
	wp_enqueue_style( 'directoryweb-shell' );
	wp_enqueue_script( $args['handle'], $args['src'], $deps, $a['version'] ?? false, true );
	if ( ! empty( $args['text_domain'] ) ) {
		wp_set_script_translations( $args['handle'], $args['text_domain'] );
	}
	if ( ! empty( $args['object'] ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $args['object'] ) ) {
		wp_add_inline_script( $args['handle'], 'window.' . $args['object'] . ' = ' . wp_json_encode( (array) ( $args['data'] ?? array() ) ) . ';', 'before' );
	}
	add_filter( 'admin_body_class', 'directoryweb_shell_body_class' );
	directoryweb_shell_screen_plain();
	return true;
}

/**
 * A shell screen has no Screen Options or Help tab: they belong to list tables and do nothing here.
 */
function directoryweb_shell_screen_plain(): void {
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
 * The body class that lets the shell run edge to edge (matches the shell stylesheet).
 *
 * @param string $classes Admin body classes.
 * @return string
 */
function directoryweb_shell_body_class( $classes ) {
	return trim( (string) $classes . ' kits-shell-screen' );
}

/**
 * The mount point of a shell screen, with a note when JavaScript does not run.
 *
 * @param string $id    Element id the script renders into.
 * @param string $title Screen title (the no-script heading).
 */
function directoryweb_shell_mount( string $id, string $title ): void {
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><hr class="wp-header-end"><div id="' . esc_attr( $id ) . '">';
	echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'directoryweb' ) . '</p></noscript>';
	echo '</div></div>';
}

/**
 * The permission callback for the screens' REST routes: signed in (401), then the capability (403). Replaces Kits
 * Core's kits_shell_rest_can(). Browser requests carry the wp_rest nonce, which WordPress's REST layer checks.
 *
 * @param string $cap Capability.
 * @return callable
 */
function directoryweb_shell_rest_can( string $cap ): callable {
	return static function () use ( $cap ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'directoryweb_unauthorised', __( 'Sign in to continue.', 'directoryweb' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'directoryweb_forbidden', __( 'You are not allowed to do this.', 'directoryweb' ), array( 'status' => 403 ) );
		}
		return true;
	};
}
