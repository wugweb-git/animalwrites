<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Tests that need no WordPress: the site operations moved from Animal Writes Core on 19 Nov 2026 (includes/ops/).
 * The scheduled-task statistics and the trigger it names, the 404 page's section and search words, the deployed
 * commit read from a git folder, retention's closed opportunities, the modified date kept by a technical save, the
 * old aw_ names that still answer, and that every function and constant defined in ops/ is a kits_ name (the old
 * names are only in compat.php and daily.php has none).
 *
 * Usage: php plugins/kits-core/tests/site-ops-tests.php
 *
 * @package kits-core
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable -- a command-line test runner with WordPress stubs.

$tmp = sys_get_temp_dir() . '/kits-site-ops-tests-' . getmypid();
define( 'ABSPATH', $tmp . '/a/b/c/' );
define( 'KITS_CORE_DIR', $tmp . '/repo/plugins/kits-core/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['t_filters'] = array();
$GLOBALS['t_meta']    = array();
$GLOBALS['t_posts']   = array();
$GLOBALS['t_request'] = '';

$GLOBALS['t_actions'] = array();
$GLOBALS['t_options'] = array();
function do_action( $hook ) {
	$GLOBALS['t_actions'][] = $hook;
}
function get_option( $n, $d = false ) {
	return $GLOBALS['t_options'][ $n ] ?? $d;
}
function update_option( $n, $v ) {
	$GLOBALS['t_options'][ $n ] = $v;
	return true;
}
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function register_rest_route() {}
function apply_filters( $hook, $value, ...$rest ) {
	return array_key_exists( $hook, $GLOBALS['t_filters'] ) ? $GLOBALS['t_filters'][ $hook ] : $value;
}
function get_post( $id ) {
	return $GLOBALS['t_posts'][ is_object( $id ) ? $id->ID : $id ] ?? null;
}
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['t_meta'][ $id ][ $key ] ?? '';
}
function wp_date( $f ) {
	return '2026-11-19';
}
function wp_parse_url( $u, $c = -1 ) {
	return parse_url( $u, $c );
}
function wp_unslash( $v ) {
	return $v;
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function is_404() {
	return true;
}
function wp_doing_cron() {
	return false;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}

$fails = 0;
$n     = 0;
function check( $what, $ok ) {
	global $fails, $n;
	++$n;
	if ( ! $ok ) {
		++$fails;
	}
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
}

$ops = dirname( __DIR__ ) . '/includes/ops/';

// Every function and constant defined in ops/ is a kits_ name; only compat.php carries the old aw_ ones.
$bad = array();
foreach ( glob( $ops . '*.php' ) as $f ) {
	if ( 'compat.php' === basename( $f ) ) {
		continue;
	}
	preg_match_all( '/^\s*function\s+&?([A-Za-z0-9_]+)|^\s*const\s+([A-Za-z0-9_]+)/m', file_get_contents( $f ), $m );
	foreach ( array_filter( array_merge( $m[1], $m[2] ) ) as $name ) {
		if ( 0 !== strpos( $name, 'kits_' ) && 0 !== strpos( $name, 'KITS_' ) ) {
			$bad[] = basename( $f ) . ':' . $name;
		}
	}
}
check( 'every function and constant in ops/ has a kits_ name (found: ' . implode( ', ', $bad ) . ')', ! $bad );

require $ops . 'cron-health.php';
require $ops . 'not-found.php';
require $ops . 'retention.php';
require $ops . 'keep-modified.php';
require $ops . 'deploy-purge.php';
require $ops . 'compat.php';

/* ---------- Scheduled tasks ---------- */

check( 'span: minutes and hours', '12 min' === kits_cron_span( 720 ) && '2 h 05 min' === kits_cron_span( 7500 ) );
$now = 1000000;
$st  = kits_cron_stats( array( $now - 3600, $now - 1800, $now - 60 ), $now - 7200, $now );
check( 'stats: three runs, the longest gap is the first one (7200 - 3600 = 3600 s)', 3 === $st['runs'] && 3600 === $st['gap'] );
$st = kits_cron_stats( array(), $now - 7200, $now );
check( 'stats: no run is one gap as long as the window', 0 === $st['runs'] && 7200 === $st['gap'] );
check( 'a command-line call is the server cron', 'hostinger' === kits_cron_trigger() );
check( 'the stale limit is 30 minutes', 1800 === KITS_CRON_STALE );

/* ---------- 404 page ---------- */

$GLOBALS['t_filters']['kits_not_found_sections'] = array(
	'aw_news'  => array( 'label' => 'News', 'slug' => 'news', 'gone' => true ),
	'aw_legal' => array( 'label' => 'Library', 'slug' => 'library' ),
);
$_SERVER['REQUEST_URI'] = '/news/stray-dog-protest/';
check( 'a missing address is split into its parts', array( 'news', 'stray-dog-protest' ) === kits_not_found_parts() );
check( 'the section is found by its first address part', array( 'aw_news', 'News' ) === kits_not_found_section() );
check( 'the search words are the last part, hyphens as spaces', 'stray dog protest' === kits_not_found_query() );
$_SERVER['REQUEST_URI'] = '/wp-content/x/old-file.php';
check( 'a file path gives no search words', '' === kits_not_found_query() );
$_SERVER['REQUEST_URI'] = '/blog/some-story__trashed/';
check( 'a trashed slug loses its suffix', 'some story' === kits_not_found_query() );
check( 'an address under no section has none', null === kits_not_found_section() );

/* ---------- Deployed commit ---------- */

$git = $tmp . '/repo/.git';
mkdir( $git . '/refs/heads', 0777, true );
mkdir( KITS_CORE_DIR, 0777, true );
$sha = str_repeat( 'ab12', 10 );
file_put_contents( $git . '/HEAD', "ref: refs/heads/main\n" );
file_put_contents( $git . '/refs/heads/main', $sha . "\n" );
check( 'the deployed commit is read from the branch the deploy folder is on', $sha === kits_deployed_head() );
file_put_contents( $git . '/HEAD', $sha . "\n" );
check( 'a detached head is the commit itself', $sha === kits_deployed_head() );
file_put_contents( $git . '/HEAD', "ref: refs/heads/packed\n" );
file_put_contents( $git . '/packed-refs', "# pack-refs\n" . $sha . " refs/heads/packed\n" );
check( 'a packed ref is read too', $sha === kits_deployed_head() );

// The purge after a deploy: once per commit, remembered, and LiteSpeed is asked to purge everything.
file_put_contents( $git . '/HEAD', $sha . "\n" );
$GLOBALS['t_actions'] = array();
check( 'a new commit purges once and is remembered', true === kits_deploy_purge_if_new() && $sha === $GLOBALS['t_options']['aw_deployed_head'] && array( 'litespeed_purge_all' ) === $GLOBALS['t_actions'] );
$GLOBALS['t_actions'] = array();
check( 'the same commit does not purge again', false === kits_deploy_purge_if_new() && array() === $GLOBALS['t_actions'] );
$sha2 = str_repeat( 'cd34', 10 );
file_put_contents( $git . '/HEAD', $sha2 . "\n" );
check( 'the next commit purges again', true === kits_deploy_purge_if_new() && $sha2 === $GLOBALS['t_options']['aw_deployed_head'] );
file_put_contents( $git . '/HEAD', "junk\n" );
$GLOBALS['t_actions'] = array();
check( 'an unreadable head purges nothing and keeps the stored commit', false === kits_deploy_purge_if_new() && array() === $GLOBALS['t_actions'] && $sha2 === $GLOBALS['t_options']['aw_deployed_head'] );

/* ---------- Retention ---------- */

$GLOBALS['t_posts'] = array( 1 => (object) array( 'ID' => 1, 'post_type' => 'aw_opportunity' ), 2 => (object) array( 'ID' => 2, 'post_type' => 'aw_news' ) );
$GLOBALS['t_meta'][1]['aw_deadline'] = '2026-11-01';
check( 'an opportunity past its deadline is closed', 'closed' === kits_retention_state( 1 ) );
$GLOBALS['t_meta'][1]['aw_deadline'] = '2026-12-01';
check( 'an open opportunity is normal', '' === kits_retention_state( 1 ) );
$GLOBALS['t_meta'][1] = array( 'aw_review_until' => '2026-11-18' );
check( 'with no deadline the review date decides', 'closed' === kits_retention_state( 1 ) );
check( 'news is never closed', '' === kits_retention_state( 2 ) );

/* ---------- Modified date kept ---------- */

check( 'no flag, no request: the date moves', ! kits_keep_modified_requested() );
$seen = null;
$ret  = kits_keep_modified( function () use ( &$seen ) {
	$seen = kits_keep_modified_requested();
	return 'saved';
} );
check( 'a save inside kits_keep_modified keeps the date, returns its value, and clears the flag after', true === $seen && 'saved' === $ret && ! kits_keep_modified_requested() );

/* ---------- The old names still answer ---------- */

check( 'old names: aw_retention_state and aw_keep_modified forward', 'closed' === aw_retention_state( 1 ) && 'x' === aw_keep_modified( fn() => 'x' ) );
check( 'old names: aw_cron_span forwards', '5 min' === aw_cron_span( 300 ) );
check( 'old name: aw_dispatch_workflow exists for the server\'s cron line', function_exists( 'aw_dispatch_workflow' ) && function_exists( 'aw_cron_last' ) && function_exists( 'aw_core_simple_table' ) );

foreach ( array( $git . '/refs/heads/main', $git . '/refs/heads/packed', $git . '/HEAD', $git . '/packed-refs' ) as $f ) {
	@unlink( $f );
}
foreach ( array( $git . '/refs/heads', $git . '/refs', $git, $tmp . '/repo/plugins/kits-core', $tmp . '/repo/plugins', $tmp . '/repo', $tmp ) as $d ) {
	@rmdir( $d );
}

echo "\n" . $n . ' checks, ' . $fails . " failed\n";
exit( $fails ? 1 : 0 );
