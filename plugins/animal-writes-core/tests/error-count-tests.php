<?php
/**
 * Daily PHP error count (includes/error-count.php): lines read by kind, without WordPress. Run by the Deploy check.
 *
 * Usage: php plugins/animal-writes-core/tests/error-count-tests.php
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) { // web requests stop here; the Deploy check runs this from the command line
	exit;
}

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- a command-line test runner with a temporary log file.

define( 'ABSPATH', sys_get_temp_dir() . '/aw-error-count-root/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

// The few WordPress functions the file and the count use.
function add_action() {}
function apply_filters( $hook, $value ) {
	return 'aw_error_count_php_paths' === $hook ? array( $GLOBALS['aw_test_log'] ) : $value;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function trailingslashit( $s ) {
	return untrailingslashit( $s ) . '/';
}
function wp_parse_url( $url, $part = -1 ) {
	return parse_url( $url, $part ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- the stub of the WordPress wrapper
}
function home_url() {
	return 'https://example.org';
}

require dirname( __DIR__ ) . '/includes/error-count.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $name What was checked.
 */
function check( $ok, $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo 'FAIL: ' . $name . "\n";
	}
}

// One line at a time.
$p = aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Warning:  Undefined array key "x" in /a/b.php on line 3' );
check( 'warning' === $p['kind'] && 'Undefined array key "x" in /a/b.php on line 3' === $p['msg'], 'warning line' );
check( 'deprecated' === aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Deprecated:  strlen(): Passing null' )['kind'], 'deprecated line' );
check( 'notice' === aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Notice:  Function x was called incorrectly' )['kind'], 'notice line' );
check( 'fatal' === aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Fatal error:  Uncaught Error' )['kind'], 'fatal line' );
$p = aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Parse error:  syntax error' );
check( 'fatal' === $p['kind'] && 'Parse error: syntax error' === $p['msg'], 'parse error counts as fatal, labelled' );
check( '' === aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] WordPress database error Table x' )['kind'], 'database error is not a PHP kind' );
check( null === aw_error_count_parse_line( 'Stack trace:' ), 'continuation line skipped' );
check( strtotime( '13-Nov-2026 10:00:00 UTC' ) === aw_error_count_parse_line( '[13-Nov-2026 10:00:00 UTC] PHP Notice:  x' )['t'], 'time read' );

// A whole log: four kinds counted apart; old lines and other lines left out.
$now  = time();
$old  = gmdate( 'd-M-Y H:i:s', $now - 2 * DAY_IN_SECONDS ) . ' UTC';
$new  = gmdate( 'd-M-Y H:i:s', $now - 60 ) . ' UTC';
$log  = sys_get_temp_dir() . '/aw-error-count-test-' . getmypid() . '.log';
$body = array(
	"[$old] PHP Fatal error:  Old one",
	"[$new] PHP Fatal error:  Uncaught Error: x",
	'Stack trace:',
	"[$new] PHP Warning:  W1",
	"[$new] PHP Warning:  W1",
	"[$new] PHP Warning:  W2",
	"[$new] PHP Notice:  N1",
	"[$new] PHP Deprecated:  D1",
	"[$new] PHP Deprecated:  D1",
	"[$new] WordPress database error Table missing",
);
file_put_contents( $log, implode( "\n", $body ) . "\n" );
$GLOBALS['aw_test_log'] = $log;
$r = aw_error_count_php( $now - DAY_IN_SECONDS );
unlink( $log );
check( 'ok' === $r['status'], 'log read' );
check( 1 === $r['kinds']['fatal']['count'] && 1 === $r['count'], 'one fatal in the window (count kept for the email)' );
check( 3 === $r['kinds']['warning']['count'] && array( 'W1' => 2, 'W2' => 1 ) === $r['kinds']['warning']['samples'], 'warnings counted with samples' );
check( 1 === $r['kinds']['notice']['count'], 'notice counted' );
check( 2 === $r['kinds']['deprecated']['count'], 'deprecated counted' );
check( '3' === aw_error_count_label( $r, 'warning' ) && '1' === aw_error_count_label( $r ), 'labels per kind' );
check( 1 === aw_error_count_total( array( 'php' => $r ) ), 'total is fatal only' );

// A result saved before kinds were counted.
$saved = array( 'status' => 'ok', 'count' => 4, 'samples' => array( 'x' => 4 ) );
check( '4' === aw_error_count_label( $saved ) && '0' === aw_error_count_label( $saved, 'warning' ), 'older saved result reads' );

echo $count . ' checks, ' . $fails . " failed\n";
exit( $fails ? 1 : 0 );
