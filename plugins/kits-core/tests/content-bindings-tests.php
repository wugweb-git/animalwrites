<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Tests that need no WordPress (owner rule: no local WordPress or MySQL): the icon, the date component and the Block
 * Bindings source moved to Kits Core (kits_icon, kits_date, kits/post-field) print exactly what the site printed before
 * through aw_i(), aw_date() and "animal-writes/field": same markup, same escaping, empty bound blocks left out, the
 * Directory's greyed contact buttons and data-act kept.
 *
 * Usage: php plugins/kits-core/tests/content-bindings-tests.php
 *
 * @package kits-core
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:disable -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['t_hooks']   = array();
$GLOBALS['t_sources'] = array();
$GLOBALS['t_meta']    = array();
$GLOBALS['t_types']   = array();

function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['t_hooks'][ $hook ][ $prio ][] = array( $cb, $args );
	return true;
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	return add_filter( $hook, $cb, $prio, $args );
}
function apply_filters( $hook, $value, ...$rest ) {
	$all = $GLOBALS['t_hooks'][ $hook ] ?? array();
	ksort( $all );
	foreach ( $all as $cbs ) {
		foreach ( $cbs as $c ) {
			$value = call_user_func_array( $c[0], array_slice( array_merge( array( $value ), $rest ), 0, $c[1] ) );
		}
	}
	return $value;
}
function do_action( $hook ) {
	$GLOBALS['t_did'][ $hook ] = 1;
	apply_filters( $hook, null );
}
function did_action( $hook ) {
	return $GLOBALS['t_did'][ $hook ] ?? 0;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return str_replace( array( '"', '<', '>' ), '', (string) $s );
}
function wp_kses_post( $s ) {
	return preg_replace( '#<script\b.*?</script>#is', '', (string) $s );
}
function __( $s ) {
	return $s;
}
function esc_html__( $s ) {
	return esc_html( $s );
}
function wp_date( $fmt, $ts ) {
	return gmdate( $fmt, $ts );
}
function date_i18n( $fmt, $ts ) {
	return gmdate( $fmt, $ts );
}
function get_option( $k ) {
	return 'date_format' === $k ? 'j M Y' : false;
}
function get_post_meta( $id, $k ) {
	return $GLOBALS['t_meta'][ $id ][ $k ] ?? '';
}
function get_post_type( $id ) {
	return $GLOBALS['t_types'][ $id ] ?? 'post';
}
function get_the_ID() {
	return 1;
}
function is_singular() {
	return false;
}
function number_format_i18n( $n ) {
	return number_format( $n );
}
function get_template_directory() {
	return __DIR__;
}
function get_template_directory_uri() {
	return 'https://example.test';
}
function plugins_url( $path = '', $plugin = '' ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( (string) $plugin ) ) . '/' . ltrim( (string) $path, '/' );
}
function wp_register_script_module() {
}
function untrailingslashit( $s ) {
	return rtrim( (string) $s, '/\\' );
}
function register_block_bindings_source( $name, $args ) {
	$GLOBALS['t_sources'][ $name ] = $args;
}
class WP_Block {
	public $context = array();
	public function __construct( $ctx ) {
		$this->context = $ctx;
	}
}

$root = dirname( __DIR__, 2 );
require $root . '/kits-core/includes/primitives.php';
require $root . '/kits-core/includes/bindings.php';
require $root . '/kits-core/includes/taxonomy-rename.php'; // KITS_CAMPAIGN_LINK / KITS_MEDIA_GROUP, which systems.php reads (Kits Core loads it first)
$site = dirname( $root ) . '/themes/kits-by-wugweb/assets/site/'; // the site layer: the content files moved here from animal-writes-core on 19 Nov 2026
require $site . 'content/systems.php';
function kits_site_path( $f ) {
	return dirname( __DIR__, 3 ) . '/themes/kits-by-wugweb/assets/site/' . ltrim( $f, '/' );
}
require $site . 'content/bindings.php';
require $root . '/kits-core/includes/scf.php';
require $site . 'content/scf.php';
do_action( 'init' );

$GLOBALS['t_fail'] = 0;
function t_check( $ok, $what ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	if ( ! $ok ) {
		$GLOBALS['t_fail'] = 1;
	}
}

// Icon and date: the markup aw_i() and aw_date() printed before the move (systems.php at 73bbc87c).
t_check( '<span class="kit-ico kit-ico--calendar" aria-hidden="true"></span>' === aw_i( 'calendar' ), 'aw_i(): same icon span' );
t_check( '<span class="kit-ico kit-ico--type-ngo kit-ico--type" aria-hidden="true"></span>' === aw_i( 'type-ngo', 'kit-ico--type' ), 'aw_i(): extra class kept' );
$old_date = '<span class="kit-date aw-date"><span class="kit-ico kit-ico--calendar" aria-hidden="true"></span><time datetime="2026-11-18">18 Nov 2026</time></span>';
t_check( $old_date === aw_date( '2026-11-18 12:00:00' ), 'aw_date(): same date component' );
t_check( $old_date === aw_date( '20261118' ), 'aw_date(): Ymd from the date picker' );
t_check( false !== strpos( aw_date( '2026-11-18 12:00:00', 'Y' ), '>2026</time>' ), 'aw_date(): own format' );
t_check( '' === aw_date( 'not a date' ) && '' === aw_date( '' ), 'aw_date(): unreadable date prints nothing' );
t_check( '<span class="kit-date"><span class="kit-ico kit-ico--calendar" aria-hidden="true"></span><time datetime="2026-11-18">18 Nov 2026</time></span>' === kits_date( '2026-11-18 12:00:00' ), 'kits_date(): site format, no tenant class' );

// The sources.
t_check( isset( $GLOBALS['t_sources']['kits/post-field'], $GLOBALS['t_sources']['animal-writes/field'] ), 'both binding sources registered' );
$GLOBALS['t_meta'][5] = array(
	'aw_source_url'  => 'https://example.test/a?b=1"x',
	'aw_source_name' => 'The <Times>',
	'aw_source_date' => '2026-11-01',
	'aw_deadline'    => '',
	'aw_website'     => '',
);
$block = new WP_Block( array( 'postId' => 5 ) );
foreach ( array( 'kits/post-field', 'animal-writes/field' ) as $src ) {
	$get = $GLOBALS['t_sources'][ $src ]['get_value_callback'];
	t_check( 'https://example.test/a?b=1x' === $get( array( 'key' => 'source_url' ), $block ), "$src: url key escaped as a URL" );
	t_check( 'The &lt;Times&gt;, 1 Nov 2026' === $get( array( 'key' => 'source_meta' ), $block ), "$src: text key escaped as text" );
	t_check( 'Read at The &lt;Times&gt;' === $get( array( 'key' => 'source_link_label' ), $block ), "$src: label key" );
	t_check( '' === $get( array( 'key' => 'no_such_key' ), $block ) && '' === $get( array(), $block ), "$src: unknown key is empty" );
}

// Empty bound blocks.
$render = function ( $html, $parsed, $inst ) {
	return apply_filters( 'render_block', $html, $parsed, $inst );
};
$bound  = fn( $src, $key, $name = 'core/paragraph' ) => array(
	'blockName' => $name,
	'attrs'     => array( 'metadata' => array( 'bindings' => array( 'content' => array( 'source' => $src, 'args' => array( 'key' => $key ) ) ) ) ),
);
foreach ( array( 'kits/post-field', 'animal-writes/field' ) as $src ) {
	t_check( '' === $render( '<p>x</p>', $bound( $src, 'deadline_label' ), $block ), "$src: block bound to an empty value is left out" );
	t_check( '<p>x</p>' === $render( '<p>x</p>', $bound( $src, 'source_meta' ), $block ), "$src: filled block kept" );
}
t_check( '<p>x</p>' === $render( '<p>x</p>', $bound( 'core/post-meta', 'anything' ), $block ), 'other sources untouched' );
t_check( '<p>x</p>' === $render( '<p>x</p>', array( 'blockName' => 'core/paragraph', 'attrs' => array() ), $block ), 'unbound block untouched' );

// Directory contact buttons: greyed when empty, data-act when filled (bindings.php before the move).
$GLOBALS['t_types'][7] = 'aw_listing';
$GLOBALS['t_meta'][7]  = array( 'aw_email' => 'a@example.test', 'aw_website' => '' );
$lb  = new WP_Block( array( 'postId' => 7 ) );
$btn = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Website</a></div>';
t_check(
	'<div class="wp-block-button"><span class="wp-block-button__link wp-element-button" role="link" aria-disabled="true" aria-label="Website: not available yet" title="Not available yet" data-act="website">Website</span></div>' === $render( $btn, $bound( 'animal-writes/field', 'website', 'core/button' ), $lb ),
	'listing: empty Website button greyed, not a link'
);
t_check(
	'<div class="wp-block-button"><a data-act="email" class="wp-block-button__link wp-element-button" href="#">Website</a></div>' === $render( $btn, $bound( 'animal-writes/field', 'email_url', 'core/button' ), $lb ),
	'listing: filled Email button gets data-act'
);
t_check( '' === $render( $btn, $bound( 'animal-writes/field', 'website', 'core/button' ), $block ), 'not a listing: empty button left out' );
t_check(
	'<div class="wp-block-button"><span class="wp-block-button__link wp-element-button" role="link" aria-disabled="true" aria-label="Website: not available yet" title="Not available yet" data-act="website">Website</span></div>' === $render( $btn, $bound( 'kits/post-field', 'website', 'core/button' ), $lb )
		&& '<div class="wp-block-button"><a data-act="email" class="wp-block-button__link wp-element-button" href="#">Website</a></div>' === $render( $btn, $bound( 'kits/post-field', 'email_url', 'core/button' ), $lb ),
	'listing through kits/post-field: greyed when empty, data-act when filled'
);

// The html key (the deadline's date component) keeps its markup and loses scripts.
function aw_retention_state() {
	return 'open';
}
$GLOBALS['t_meta'][9] = array( 'aw_deadline' => '2026-12-01' );
$dl                   = $GLOBALS['t_sources']['kits/post-field']['get_value_callback']( array( 'key' => 'deadline' ), new WP_Block( array( 'postId' => 9 ) ) );
t_check( 'Apply by <span class="kit-date aw-date"><span class="kit-ico kit-ico--calendar" aria-hidden="true"></span><time datetime="2026-12-01">1 Dec 2026</time></span>' === $dl, 'deadline: html key keeps the date component' );

// SCF bridge (admin): the same folder, save paths and group order as before the move (scf.php at 73bbc87c).
add_filter( 'aw_scf_groups', fn( $g ) => array_merge( (array) $g, array( 'group_aw_listing' ) ) ); // as DirectoryWeb does
add_filter( 'kits_scf_groups', fn( $g ) => array_merge( (array) $g, array( 'group_other', 'group_aw_news' ) ) );
t_check( in_array( kits_site_path( 'acf-json' ), apply_filters( 'acf/settings/load_json', array() ), true ), 'SCF: our acf-json folder is loaded' );
t_check( kits_site_path( 'acf-json' ) === apply_filters( 'acf/settings/save_json/key=group_aw_news', '' ), 'SCF: our groups save back to it' );
t_check( '' === apply_filters( 'acf/settings/save_json/key=group_aw_listing', '' ), 'SCF: other plugins\' groups are not saved here' );
t_check( array_merge( AW_SCF_GROUPS, array( 'group_aw_listing', 'group_other' ) ) === aw_scf_group_keys(), 'SCF: group keys in the same order, plugins after ours, no duplicates' );
t_check( false === aw_scf_active() && false === kits_scf_active(), 'SCF: not active without SCF' );

exit( $GLOBALS['t_fail'] );
