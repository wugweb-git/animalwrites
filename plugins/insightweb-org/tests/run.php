<?php
/**
 * InsightWeb (public edition) I1 smoke test — no WordPress. Confirms the skeleton loads, defines its constants,
 * registers a standalone menu, and renders the empty-module screen with the Site Kit prompt. Runs in the Deploy check
 * and locally: php plugins/insightweb-org/tests/run.php
 *
 * @package InsightWeb
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, Squiz.Commenting.FunctionComment.MissingParamTag, Universal.Files.SeparateFunctionsFromOO, WordPress.NamingConventions.PrefixAllGlobals, Generic.CodeAnalysis.UnusedFunctionParameter -- a command-line test runner with WordPress stubs (stub params are used via compact()).

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['iw_menu'] = array();

/** Stub. */
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
/** Stub. */
function add_action() {}
/** Stub: default passthrough. */
function apply_filters( $hook, $value ) {
	return $value;
}
/** Stub. */
function current_user_can() {
	return true;
}
/** Stub: record the registered menu. */
function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb, $icon = '' ) {
	$GLOBALS['iw_menu'] = compact( 'page_title', 'menu_title', 'cap', 'slug', 'cb', 'icon' );
}
/** Stub. */
function __( $s ) {
	return $s;
}
/** Stub. */
function esc_html__( $s ) {
	return $s;
}
/** Stub. */
function esc_html( $s ) {
	return $s;
}

require dirname( __DIR__ ) . '/insightweb.php';

$GLOBALS['iw_fail'] = 0;

/** One check. */
function iw_check( bool $ok, string $what ): void {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	if ( ! $ok ) {
		$GLOBALS['iw_fail'] = 1;
	}
}

iw_check( defined( 'INSIGHTWEB_VERSION' ) && '0.1.0' === INSIGHTWEB_VERSION, 'version constant is 0.1.0' );
iw_check( defined( 'INSIGHTWEB_FILE' ) && defined( 'INSIGHTWEB_DIR' ), 'file and dir constants defined' );
iw_check( 'manage_options' === insightweb_cap(), 'capability maps to manage_options' );
iw_check( array() === insightweb_tabs(), 'tab set is empty in the skeleton' );
iw_check( is_bool( insightweb_site_kit_active() ) && false === insightweb_site_kit_active(), 'Site Kit absent in the test → false' );

insightweb_admin_menu();
iw_check( 'insightweb' === ( $GLOBALS['iw_menu']['slug'] ?? '' ) && 'insightweb_render_screen' === ( $GLOBALS['iw_menu']['cb'] ?? '' ), 'standalone top-level menu registered on slug insightweb' );

ob_start();
insightweb_render_screen();
$html = (string) ob_get_clean();
iw_check( false !== strpos( $html, 'No insights modules are active yet.' ), 'empty-module screen renders' );
iw_check( false !== strpos( $html, 'Site Kit by Google' ), 'Site Kit connection prompt shown when absent' );

exit( $GLOBALS['iw_fail'] );
