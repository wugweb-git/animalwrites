<?php
/**
 * The admin shell, registered from the bundled planweb-shell UI package (packages/planweb-shell, built into
 * build/shell/). This is the Kits admin shell extracted verbatim; the plugin ships it, so there is no runtime
 * dependency on Kits Core. One script and one stylesheet, both with the namespaced handle `planweb-shell`, which sets
 * window.planweb.shell.
 *
 * A screen is put in place with two calls: planweb_shell_app() enqueues the shell and the screen's own script, and
 * planweb_shell_mount() prints the element the script renders into.
 *
 * @package planweb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The shell's build asset (dependencies and version), or an empty array when the build is missing. */
function planweb_shell_asset(): array {
	$file = PLANWEB_DIR . 'build/shell/index.asset.php';
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
		$a = planweb_shell_asset();
		if ( ! $a ) {
			return;
		}
		$base = plugins_url( 'build/shell/', PLANWEB_FILE );
		wp_register_script( 'planweb-shell', $base . 'index.js', $a['dependencies'] ?? array(), $a['version'] ?? false, true );
		wp_register_style( 'planweb-shell', $base . 'index.css', wp_style_is( 'wp-theme', 'registered' ) ? array( 'wp-components', 'wp-theme' ) : array( 'wp-components' ), $a['version'] ?? false );
		wp_set_script_translations( 'planweb-shell', 'planweb' );
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
function planweb_shell_app( array $args ): bool {
	if ( ! wp_script_is( 'planweb-shell', 'registered' ) || empty( $args['handle'] ) || empty( $args['src'] ) || empty( $args['asset'] ) || ! is_readable( $args['asset'] ) ) {
		return false;
	}
	$a     = include $args['asset'];
	$deps  = array_values( array_unique( array_merge( (array) ( $a['dependencies'] ?? array() ), array( 'planweb-shell' ) ) ) );
	$shell = wp_scripts()->registered['planweb-shell']->deps ?? array();
	// A script WordPress does not have (react-jsx-runtime is WordPress 6.6+) would leave the screen empty: show a notice.
	foreach ( array_merge( $deps, (array) $shell ) as $dep ) {
		if ( ! wp_script_is( $dep, 'registered' ) ) {
			return false;
		}
	}
	wp_enqueue_style( 'planweb-shell' );
	wp_enqueue_script( $args['handle'], $args['src'], $deps, $a['version'] ?? false, true );
	if ( ! empty( $args['text_domain'] ) ) {
		wp_set_script_translations( $args['handle'], $args['text_domain'] );
	}
	if ( ! empty( $args['object'] ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $args['object'] ) ) {
		wp_add_inline_script( $args['handle'], 'window.' . $args['object'] . ' = ' . wp_json_encode( (array) ( $args['data'] ?? array() ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}
	add_filter( 'admin_body_class', 'planweb_shell_body_class' );
	planweb_shell_screen_plain();
	return true;
}

/**
 * A shell screen has no Screen Options or Help tab: they belong to list tables and do nothing here.
 */
function planweb_shell_screen_plain(): void {
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
 * The body class that lets the shell run edge to edge (matches the shell stylesheet, which keeps the Kits class name).
 *
 * @param string $classes Admin body classes.
 * @return string
 */
function planweb_shell_body_class( $classes ) {
	return trim( (string) $classes . ' kits-shell-screen' );
}

/**
 * The mount point of a shell screen, with a note when JavaScript does not run.
 *
 * @param string $id    Element id the script renders into.
 * @param string $title Screen title (the no-script heading).
 */
function planweb_shell_mount( string $id, string $title ): void {
	echo '<div class="wrap"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><hr class="wp-header-end"><div id="' . esc_attr( $id ) . '">';
	echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'planweb' ) . '</p></noscript>';
	echo '</div></div>';
}
