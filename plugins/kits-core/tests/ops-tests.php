<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Tests that need no WordPress (owner rule: no local WordPress or MySQL): the ops pieces moved from Animal Writes Core
 * on 18 Nov 2026. The error pages (includes/error-pages.php) install a missing drop-in, update ours, leave a current
 * one and never replace a file without the marker; the site's drop-ins carry the marker and sit in the site folder
 * (assets/site/dropins/, left out of Kits builds), and nothing installs without them. The daily backup
 * (includes/backups.php) is off unless switched on, is switched on once by itself where it already ran, keeps its cron
 * hook, option and REST route names, and the UpdraftPlus alert reads the newest remote backup. The old aw_ names still
 * answer.
 *
 * Usage: php plugins/kits-core/tests/ops-tests.php
 *
 * @package kits-core
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', sys_get_temp_dir() . '/kits-ops-tests/a/b/c/' );
define( 'KITS_CORE_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['t_hooks']   = array();
$GLOBALS['t_options'] = array();

$GLOBALS['t_filters'] = array();
$GLOBALS['t_cron']    = array();

function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['t_hooks'][ $hook ][] = $cb;
	return true;
}
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	return add_action( $hook, $cb, $prio, $args );
}
function apply_filters( $hook, $value, ...$rest ) {
	return array_key_exists( $hook, $GLOBALS['t_filters'] ) ? $GLOBALS['t_filters'][ $hook ] : $value;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['t_options'] ) ? $GLOBALS['t_options'][ $name ] : $default;
}
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $name, $GLOBALS['t_options'] ) ) {
		return false;
	}
	$GLOBALS['t_options'][ $name ] = $value;
	return true;
}
function wp_next_scheduled( $hook ) {
	return $GLOBALS['t_cron'][ $hook ] ?? false;
}
function wp_schedule_event( $at, $recurrence, $hook ) {
	$GLOBALS['t_cron'][ $hook ] = $at;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['t_cron'][ $hook ] );
	return 1;
}
function register_setting( ...$args ) {
	return true;
}
function rest_sanitize_boolean( $v ) {
	return in_array( $v, array( true, 1, '1', 'true', 'on' ), true );
}
/** Run every init callback (the scheduler and the setting), as WordPress does on each request. */
function t_init(): void {
	foreach ( $GLOBALS['t_hooks']['init'] ?? array() as $cb ) {
		$cb();
	}
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['t_options'][ $name ] = $value;
	return true;
}
function wp_generate_password( $len = 12, $special = true ) {
	return substr( md5( (string) mt_rand() ), 0, $len );
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function wp_date( $format, $ts = null ) {
	return gmdate( $format, $ts ?? time() );
}
function wp_strip_all_tags( $s ) {
	return strip_tags( (string) $s );
}
class UpdraftPlus {}

$fails = 0;
function check( $label, $ok ) {
	global $fails;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		++$fails;
	}
}

require KITS_CORE_DIR . 'includes/error-pages.php';
require KITS_CORE_DIR . 'includes/backups.php';

// The site's drop-ins carry the marker (or the sync would report no-source and never install them).
check( 'the error pages live in the site folder Kits builds leave out', 'assets/site/dropins/' === substr( KITS_ERROR_PAGE_DIR, strlen( KITS_CORE_DIR ) ) && ! is_dir( KITS_CORE_DIR . 'dropins' ) );
check( 'this site has its error pages', kits_error_pages_here() );
foreach ( KITS_ERROR_PAGE_FILES as $name ) {
	$src = (string) file_get_contents( KITS_ERROR_PAGE_DIR . $name );
	check( "assets/site/dropins/$name carries the marker", false !== strpos( $src, KITS_ERROR_PAGE_MARKER ) );
	check( "assets/site/dropins/$name looks for its pictures in the site folder", false !== strpos( $src, "'/plugins/kits-core/assets/site/dropins/'" ) && false === strpos( $src, '/plugins/animal-writes-core/assets' ) && false === strpos( $src, "'/plugins/kits-core/assets/error/'" ) );
}
$pages_src = (string) file_get_contents( KITS_CORE_DIR . 'includes/error-pages.php' );
check( 'without the site folder: no copy, no option, no hourly check, no notice', false !== strpos( $pages_src, "if ( ! kits_error_pages_here() ) {\n\t\treturn array();" ) && false !== strpos( $pages_src, "wp_doing_ajax() || ! kits_error_pages_here()" ) && false !== strpos( $pages_src, "current_user_can( 'manage_options' ) || ! kits_error_pages_here()" ) );
check( 'the marker is unchanged, so the drop-ins already installed stay ours', 'Animal Writes error page' === KITS_ERROR_PAGE_MARKER );

// One drop-in through every state.
$dir = sys_get_temp_dir() . '/kits-ops-tests-' . getmypid();
@mkdir( $dir );
$src = $dir . '/src.php';
$dst = $dir . '/php-error.php';
file_put_contents( $src, "<?php // Animal Writes error page v1\n" );
check( 'missing drop-in is installed', 'installed' === kits_error_page_sync_one( $src, $dst ) && file_get_contents( $dst ) === file_get_contents( $src ) );
check( 'same drop-in is current', 'current' === kits_error_page_sync_one( $src, $dst ) );
file_put_contents( $src, "<?php // Animal Writes error page v2\n" );
check( 'our older drop-in is updated', 'updated' === kits_error_page_sync_one( $src, $dst ) && false !== strpos( file_get_contents( $dst ), 'v2' ) );
file_put_contents( $dst, "<?php // someone else's page\n" );
check( 'a drop-in without the marker is never replaced', 'foreign' === kits_error_page_sync_one( $src, $dst ) && false !== strpos( file_get_contents( $dst ), 'someone else' ) );
file_put_contents( $src, "<?php // no marker\n" );
check( 'a source without the marker is not copied', 'no-source' === kits_error_page_sync_one( $src, $dst ) );
check( 'no temporary files are left behind', array() === glob( $dir . '/.*.tmp' ) );
check( 'old name aw_error_page_sync_one() still answers', 'no-source' === aw_error_page_sync_one( $src, $dst ) );
array_map( 'unlink', glob( $dir . '/*' ) );
@rmdir( $dir );

// The switch: off by default, on once by itself where the backup already ran (live keeps it with no manual step).
check( 'a new site: off, stored once as off', false === kits_daily_backup_on() && '0' === get_option( 'kits_daily_backup' ) );
t_init();
check( 'off: nothing is booked', false === wp_next_scheduled( 'aw_daily_backup' ) );
check( 'off: a booked run writes nothing', array( 'error' => 'off' ) === kits_daily_backup_run() );
$GLOBALS['t_options'] = array( 'aw_backup_last' => array( 'at' => '2026-11-18 03:30:18' ) );
check( 'where the backup already ran (aw_backup_last): switched on once', true === kits_daily_backup_on() && '1' === get_option( 'kits_daily_backup' ) );
t_init();
check( 'on: the daily run is booked', false !== wp_next_scheduled( 'aw_daily_backup' ) );
$GLOBALS['t_options'] = array();
check( 'where a run is booked but none ran yet: switched on once', true === kits_daily_backup_on() && '1' === get_option( 'kits_daily_backup' ) );
$GLOBALS['t_options'] = array( 'kits_daily_backup' => '0', 'aw_backup_last' => array( 'at' => 'x' ) );
check( 'a stored off wins over the old evidence (asked once only)', false === kits_daily_backup_on() );
t_init();
check( 'switched off: the booked run is taken off', false === wp_next_scheduled( 'aw_daily_backup' ) );
$GLOBALS['t_filters']['kits_daily_backup'] = true;
check( 'the filter kits_daily_backup can force it on', true === kits_daily_backup_on() );
unset( $GLOBALS['t_filters']['kits_daily_backup'] );
$GLOBALS['t_options'] = array();
$GLOBALS['t_cron']    = array();

// Names the server, the workers repository and the dashboards rely on.
check( 'cron hook aw_daily_backup runs the backup', in_array( 'kits_daily_backup_run', $GLOBALS['t_hooks']['aw_daily_backup'] ?? array(), true ) );
check( 'Check site still shows the backup', in_array( 'kits_daily_backup_site_check', $GLOBALS['t_hooks']['aw_site_check_after'] ?? array(), true ) );
check( 'the error pages sync after a deploy', in_array( 'kits_error_pages_sync', $GLOBALS['t_hooks']['update_option_aw_deployed_head'] ?? array(), true ) && in_array( 'kits_error_pages_sync', $GLOBALS['t_hooks']['add_option_aw_deployed_head'] ?? array(), true ) );
$backups_src = (string) file_get_contents( KITS_CORE_DIR . 'includes/backups.php' );
check( 'REST route aw/v1/backup/(db|code) kept', false !== strpos( $backups_src, "'aw/v1'," ) && false !== strpos( $backups_src, "'/backup/(?P<kind>db|code)'" ) );
check( 'option aw_backup_last kept', false !== strpos( $backups_src, "update_option( 'aw_backup_last'" ) );
check( 'backups go to ~/backups, three levels above the web root', dirname( untrailingslashit( ABSPATH ), 3 ) . '/backups' === kits_daily_backup_dir() && kits_daily_backup_dir() === aw_backup_dir() );
check( 'kept 14 days', 14 === KITS_BACKUP_KEEP_DAYS );

// UpdraftPlus alert.
$now = 1_800_000_000;
$GLOBALS['t_options']['updraft_backup_history'] = array();
check( 'no remote backup is a problem', '' !== kits_backup_problem( $now ) );
$GLOBALS['t_options']['updraft_backup_history'] = array(
	$now - 3 * HOUR_IN_SECONDS  => array( 'service' => array( 'none' ), 'db' => 'x' ),
	$now - 10 * HOUR_IN_SECONDS => array( 'service' => array( 'googledrive' ), 'db' => 'x' ),
);
check( 'the newest remote backup counts, a local-only one does not', $now - 10 * HOUR_IN_SECONDS === kits_backup_remote_last() );
check( 'a 10-hour-old remote backup is fine', '' === kits_backup_problem( $now ) && '' === aw_backup_problem( $now ) );
check( 'a 40-hour-old remote backup is a problem', false !== strpos( kits_backup_problem( $now + 30 * HOUR_IN_SECONDS ), '40 hours' ) );
$GLOBALS['t_options']['updraft_last_backup'] = array( 'success' => 0, 'errors' => array( '<b>Drive</b> quota' ), 'backup_time' => $now );
check( 'a run with errors is a problem', false !== strpos( kits_backup_problem( $now ), 'Drive quota' ) );

echo $fails ? "\n$fails failed\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
