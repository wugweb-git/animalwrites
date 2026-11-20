<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Stub tests for includes/taxonomy-rename.php (no WordPress): the active name follows the data, nothing moves on
 * load, Rename moves terms, menu items and Rank Math keys after saving a backup, Undo puts them back, and the counts.
 * An in-memory term_taxonomy and postmeta stand in for the database.
 *
 * Run: php plugins/kits-core/tests/taxonomy-rename-tests.php
 *
 * @package kits-core
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']    = array();
$GLOBALS['actions'] = array();
$GLOBALS['cleaned'] = array();
$GLOBALS['purged']  = 0;

/** Options. */
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
/** Options. */
function update_option( $k, $v ) {
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
/** Filters (the screen joins the Kits frame with one). */
function add_filter( $hook, $cb ) {
	$GLOBALS['filters'][ $hook ][] = $cb;
}
/** Hooks. */
function add_action( $hook, $cb ) {
	$GLOBALS['actions'][ $hook ][] = $cb;
}
/** Hooks. */
function do_action( $hook ) {
	if ( 'litespeed_purge_all' === $hook ) {
		++$GLOBALS['purged'];
	}
}
/** Caches. */
function clean_term_cache( $ids ) {
	$GLOBALS['cleaned'] = array_merge( $GLOBALS['cleaned'], (array) $ids );
}
/** Caches. */
function clean_taxonomy_cache( $t ) {}
/** Caches. */
function wp_cache_set_terms_last_changed() {}
/** Core helper. */
function wp_list_pluck( $list, $field ) {
	return array_map( fn( $r ) => is_object( $r ) ? $r->$field : $r[ $field ], $list );
}

/** In-memory term_taxonomy and postmeta, with just the queries taxonomy-rename.php sends. */
class Fake_Wpdb {
	public $term_taxonomy = 'wp_term_taxonomy';
	public $postmeta      = 'wp_postmeta';
	public $posts         = 'wp_posts';
	public $options       = 'wp_options';
	public $usermeta      = 'wp_usermeta';
	public $prefix        = 'wp_';
	public $tt            = array();
	public $pm            = array();
	public function esc_like( $s ) {
		return $s;
	}
	public function prepare( $q, ...$a ) {
		foreach ( $a as $v ) {
			$q = preg_replace( '/%[sd]/', "'" . addslashes( (string) $v ) . "'", $q, 1 );
		}
		return $q;
	}
	private function &table( $q ) {
		if ( false !== strpos( $q, 'wp_term_taxonomy' ) ) {
			return $this->tt;
		}
		if ( false !== strpos( $q, 'wp_postmeta' ) ) {
			return $this->pm;
		}
		$none = null;
		return $none;
	}
	private function match( $q, $col ) {
		$t = $this->table( $q );
		if ( null === $t || ! preg_match( "/WHERE .*?{$col} = '([^']+)'/", $q, $m ) ) {
			return array();
		}
		$ids = preg_match( '/IN \(([0-9,]+)\)/', $q, $n ) ? array_map( 'intval', explode( ',', $n[1] ) ) : null;
		$key = false !== strpos( $q, 'wp_term_taxonomy' ) ? 'term_taxonomy_id' : 'meta_id';
		return array_filter( $t, fn( $r ) => $r[ $col ] === $m[1] && ( null === $ids || in_array( $r[ $key ], $ids, true ) ) );
	}
	private function col( $q ) {
		return false !== strpos( $q, 'wp_term_taxonomy' ) ? 'taxonomy' : 'meta_value';
	}
	public function get_var( $q ) {
		return null === $this->table( $q ) ? 0 : count( $this->match( $q, $this->col( $q ) ) );
	}
	public function get_results( $q ) {
		return array_values( array_map( fn( $r ) => (object) $r, $this->match( $q, $this->col( $q ) ) ) );
	}
	public function get_col( $q ) {
		preg_match( '/SELECT (\w+)/', $q, $m );
		return array_values( wp_list_pluck( $this->match( $q, $this->col( $q ) ), $m[1] ) );
	}
	public function update( $table, $data, $where ) {
		$t   = &$this->table( $table );
		$col = 'wp_term_taxonomy' === $table ? 'taxonomy' : 'meta_value';
		foreach ( $t as &$r ) {
			if ( $r[ $col ] === $where[ $col ] && ( ! isset( $where['meta_key'] ) || $r['meta_key'] === $where['meta_key'] ) ) {
				$r[ $col ] = $data[ $col ];
			}
		}
	}
	public function query( $q ) {
		preg_match( "/SET (\w+) = '([^']+)'/", $q, $s );
		$t    = &$this->table( $q );
		$keys = array_keys( $this->match( $q, $s[1] ) );
		foreach ( $keys as $k ) {
			$t[ $k ][ $s[1] ] = $s[2];
		}
		return count( $keys );
	}
}

/** Fresh tables and options. */
function reset_db() {
	global $wpdb;
	$wpdb               = new Fake_Wpdb();
	$GLOBALS['opts']    = array(
		'rank-math-options-titles'  => array( 'tax_aw_campaign_link_robots' => array( 'noindex' ), 'pt_post_title' => 'x' ),
		'rank-math-options-sitemap' => array( 'tax_aw_media_group_sitemap' => 'off' ),
	);
	$GLOBALS['cleaned'] = array();
	$GLOBALS['purged']  = 0;
	$wpdb->tt           = array(
		array( 'term_taxonomy_id' => 11, 'term_id' => 101, 'taxonomy' => 'aw_campaign_link' ),
		array( 'term_taxonomy_id' => 12, 'term_id' => 102, 'taxonomy' => 'aw_campaign_link' ),
		array( 'term_taxonomy_id' => 21, 'term_id' => 201, 'taxonomy' => 'aw_media_group' ),
		array( 'term_taxonomy_id' => 31, 'term_id' => 301, 'taxonomy' => 'post_tag' ),
	);
	$wpdb->pm           = array(
		array( 'meta_id' => 7, 'meta_key' => '_menu_item_object', 'meta_value' => 'aw_campaign_link' ),
		array( 'meta_id' => 8, 'meta_key' => '_menu_item_object', 'meta_value' => 'page' ),
	);
}

reset_db();
require dirname( __DIR__ ) . '/includes/taxonomy-rename.php';

$fails = 0;
/** Assert. */
function check( $name, $ok ) {
	global $fails;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test output.
	fwrite( STDOUT, ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n" );
	$fails += $ok ? 0 : 1;
}

check( 'before the rename the constants are the old names', 'aw_campaign_link' === KITS_CAMPAIGN_LINK && 'aw_media_group' === KITS_MEDIA_GROUP );
check( 'before: old and new names both resolve to the old one', 'aw_campaign_link' === kits_taxonomy_current( 'kits_campaign_link' ) && 'aw_campaign_link' === kits_taxonomy_current( 'aw_campaign_link' ) );
check( 'other taxonomy is unchanged', 'post_tag' === kits_taxonomy_current( 'post_tag' ) );
check( 'nothing runs on load (no init hook)', empty( $GLOBALS['actions']['init'] ) );
check( 'admin screen and its post handler are registered', ! empty( $GLOBALS['actions']['admin_menu'] ) && ! empty( $GLOBALS['actions']['admin_post_kits_taxonomy_rename'] ) );

$c = kits_taxonomy_rename_counts()['move'];
check( 'counts: terms, menu items, Rank Math keys', 2 === $c['Terms under aw_campaign_link'] && 1 === $c['Terms under aw_media_group'] && 1 === $c['Menu items for aw_campaign_link'] && 1 === $c['Rank Math settings for aw_campaign_link'] && 1 === $c['Rank Math settings for aw_media_group'] );
check( 'counts change nothing', 'aw_campaign_link' === $wpdb->tt[0]['taxonomy'] && false === get_option( 'kits_taxonomy_rename' ) );

$r = kits_taxonomy_rename_run();
check( 'run renames terms', 'kits_campaign_link' === $wpdb->tt[0]['taxonomy'] && 'kits_campaign_link' === $wpdb->tt[1]['taxonomy'] && 'kits_media_group' === $wpdb->tt[2]['taxonomy'] );
check( 'run leaves other taxonomies and term ids', 'post_tag' === $wpdb->tt[3]['taxonomy'] && array( 101, 102, 201, 301 ) === array_column( $wpdb->tt, 'term_id' ) );
check( 'run renames menu items only for the taxonomy', 'kits_campaign_link' === $wpdb->pm[0]['meta_value'] && 'page' === $wpdb->pm[1]['meta_value'] );
check( 'run renames Rank Math keys and keeps others', isset( get_option( 'rank-math-options-titles' )['tax_kits_campaign_link_robots'] ) && 'x' === get_option( 'rank-math-options-titles' )['pt_post_title'] && isset( get_option( 'rank-math-options-sitemap' )['tax_kits_media_group_sitemap'] ) );
$b = get_option( 'kits_taxonomy_rename_backup' );
check( 'backup has terms, menu items and the Rank Math values as they were', array( 11, 12 ) === $b['terms']['aw_campaign_link'] && array( 21 ) === $b['terms']['aw_media_group'] && array( 7 ) === $b['menus']['aw_campaign_link'] && isset( $b['options']['rank-math-options-titles']['tax_aw_campaign_link_robots'] ) );
check( 'run sets the marker, so new names are active', '1' === get_option( 'kits_taxonomy_rename' ) && 'kits_campaign_link' === kits_taxonomy_current( 'aw_campaign_link' ) );
check( 'run cleans caches and purges pages', array( 101, 102, 201 ) === $GLOBALS['cleaned'] && 1 === $GLOBALS['purged'] );
check( 'run reports what moved', 2 === $r['Terms aw_campaign_link'] && 1 === $r['Menu items aw_campaign_link'] && 1 === $r['Rank Math rank-math-options-titles'] );

$wpdb->tt[] = array( 'term_taxonomy_id' => 13, 'term_id' => 103, 'taxonomy' => 'kits_campaign_link' ); // made after the rename
$titles     = get_option( 'rank-math-options-titles' );
$titles['tax_kits_campaign_link_title'] = 'changed after';
update_option( 'rank-math-options-titles', $titles );
$r = kits_taxonomy_rename_undo();
check( 'undo puts terms back', 'aw_campaign_link' === $wpdb->tt[0]['taxonomy'] && 'aw_media_group' === $wpdb->tt[2]['taxonomy'] && 2 === $r['Terms aw_campaign_link'] );
check( 'undo leaves a term made after the rename', 'kits_campaign_link' === $wpdb->tt[4]['taxonomy'] );
check( 'undo puts menu items back', 'aw_campaign_link' === $wpdb->pm[0]['meta_value'] );
check( 'undo gives Rank Math keys their old names, keeping later values', isset( get_option( 'rank-math-options-titles' )['tax_aw_campaign_link_robots'] ) && 'changed after' === get_option( 'rank-math-options-titles' )['tax_aw_campaign_link_title'] );
check( 'undo marks undone, so old names are active again', 'undone' === get_option( 'kits_taxonomy_rename' ) && 'aw_campaign_link' === kits_taxonomy_current( 'kits_campaign_link' ) );
check( 'undo keeps the backup', isset( get_option( 'kits_taxonomy_rename_backup' )['terms'] ) );

check( 'key rename touches only the names', array( 'tax_kits_media_group_x' => 1, 'pt_post' => 2 ) === kits_taxonomy_rename_keys( kits_taxonomy_renames(), array( 'tax_aw_media_group_x' => 1, 'pt_post' => 2 ) ) );

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test output.
fwrite( STDOUT, $fails ? "\n$fails failed\n" : "\nall passed\n" ); // CLI test output.
exit( $fails ? 1 : 0 );
