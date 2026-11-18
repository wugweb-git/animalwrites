<?php
/**
 * Load smoke: with no WordPress and no Animal Writes Core, the plugin file loads and its pure helpers and the
 * external-services gate behave. Run: php tests/load-smoke.php
 */
define( 'ABSPATH', __DIR__ . '/' );
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400, 'WEEK_IN_SECONDS' => 604800, 'MONTH_IN_SECONDS' => 2592000, 'YEAR_IN_SECONDS' => 31536000, 'MB_IN_BYTES' => 1048576, 'KB_IN_BYTES' => 1024 ) as $k => $v ) { define( $k, $v ); }
$GLOBALS['dw_hooks'] = array();
$GLOBALS['dw_opts']  = array();
class WP_Error { public $code; public $msg; public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function add_action( $h, $cb = null, $p = 10, $a = 1 ) { $GLOBALS['dw_hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb = null, $p = 10, $a = 1 ) { $GLOBALS['dw_hooks'][ $h ][] = $cb; }
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function apply_filters( $h, $v = null ) { return $v; }
function do_action() {}
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s ) { return $s; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://x.test/wp-content/plugins/directoryweb/'; }
function plugin_basename( $f ) { return 'directoryweb/directoryweb.php'; }
function home_url( $p = '' ) { return 'http://x.test' . $p; }
function get_option( $k, $d = false ) { return $GLOBALS['dw_opts'][ $k ] ?? $d; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_safe_remote_get( $u ) { return array( 'ok' => $u ); }
function wp_rand() { return 1; }
function wp_date( $f, $t ) { return date( $f, $t ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) ); }
function wp_json_encode( $v ) { return json_encode( $v ); }

$fail = 0;
$ok   = function ( $c, $m ) use ( &$fail ) { echo ( $c ? 'ok   ' : 'FAIL ' ) . $m . "\n"; $fail += $c ? 0 : 1; };

require dirname( __DIR__ ) . '/directoryweb.php';
$ok( defined( 'DIRECTORYWEB_LOADED' ), 'plugin loads with no Animal Writes Core' );
$ok( ! defined( 'AW_CORE_VERSION' ), 'AW_CORE_VERSION not needed' );
$ok( function_exists( 'directoryweb_register_listing_type' ) && function_exists( 'directoryweb_content_type_args' ), 'owns its post type functions' );
$ok( 'tel:+919876543210' === directoryweb_tel_url( '098765 43210' ), 'tel_url normalises a 10-digit number' );
$ok( '1962' === directoryweb_normalise_phone( '1962' ), 'short code kept' );
$ok( in_array( 'animal-shelter', directoryweb_listing_type_slugs(), true ), 'listing types come from the plugin' );

// The gate: unregistered refused, registered-but-off refused, on allowed.
$r = directoryweb_remote_get( 'https://evil.example/x' );
$ok( is_wp_error( $r ) && 'directoryweb_unregistered_service' === $r->code, 'unregistered host is blocked' );
$r = directoryweb_remote_get( 'https://nominatim.openstreetmap.org/search?q=x' );
$ok( is_wp_error( $r ) && 'directoryweb_service_off' === $r->code, 'address lookup is off by default' );
$GLOBALS['dw_opts']['directoryweb_services'] = array( 'nominatim' => true );
$r = directoryweb_remote_get( 'https://nominatim.openstreetmap.org/search?q=x' );
$ok( ! is_wp_error( $r ), 'address lookup allowed once switched on' );
$ok( false !== strpos( directoryweb_services_readme(), 'tiles.openfreemap.org' ), 'readme section lists the tile host' );

// No code left that names the old prefix or Core.
$bad = array();
foreach ( glob( dirname( __DIR__ ) . '/{includes,assets/js}/*.{php,js}', GLOB_BRACE ) as $f ) {
	$t = file_get_contents( $f );
	if ( preg_match( '/\b(aw_[a-z]|AW_[A-Z]|AW_CORE_VERSION)/', $t ) ) { $bad[] = basename( $f ); }
	if ( preg_match( '/wp_remote_(get|post)\(/', $t ) && 'external-services.php' !== basename( $f ) ) { $bad[] = 'raw http in ' . basename( $f ); }
}
$ok( ! $bad, 'no aw_ names and no direct HTTP outside the gate' . ( $bad ? ': ' . implode( ', ', $bad ) : '' ) );
exit( $fail ? 1 : 0 );
