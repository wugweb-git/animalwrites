<?php
/**
 * Stub tests for includes/taxonomy-rename.php (no WordPress): the name map, dry run, the rename, its backup, undo,
 * and the first-load hook with its lock. An in-memory term_taxonomy table stands in for the database.
 *
 * Run: php plugins/animal-writes-core/tests/taxonomy-rename-tests.php
 *
 * @package animal-writes-core
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['opts']    = array();
$GLOBALS['actions'] = array();
$GLOBALS['cleaned'] = array();

/** Options. */
function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d;
}
/** Options. */
function update_option( $k, $v ) {
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
/** Options. */
function add_option( $k, $v = '' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) {
		return false;
	}
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
/** Options. */
function delete_option( $k ) {
	unset( $GLOBALS['opts'][ $k ] );
	return true;
}
/** Hooks. */
function add_action( $hook, $cb, $prio = 10 ) {
	$GLOBALS['actions'][ $hook ][] = $cb;
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

/** In-memory term_taxonomy with just the queries taxonomy-rename.php sends. */
class Fake_Wpdb {
	public $term_taxonomy = 'wp_term_taxonomy';
	public $rows          = array();
	public function prepare( $q, ...$a ) {
		foreach ( $a as $v ) {
			$q = preg_replace( '/%s/', "'" . addslashes( $v ) . "'", $q, 1 );
		}
		return $q;
	}
	private function match( $q ) {
		preg_match( "/WHERE taxonomy = '([^']+)'/", $q, $m );
		$ids = preg_match( '/IN \(([0-9,]+)\)/', $q, $n ) ? array_map( 'intval', explode( ',', $n[1] ) ) : null;
		return array_filter( $this->rows, fn( $r ) => $r['taxonomy'] === $m[1] && ( null === $ids || in_array( $r['term_taxonomy_id'], $ids, true ) ) );
	}
	public function get_var( $q ) {
		return count( $this->match( $q ) );
	}
	public function get_results( $q ) {
		return array_values( array_map( fn( $r ) => (object) $r, $this->match( $q ) ) );
	}
	public function get_col( $q ) {
		return array_values( wp_list_pluck( $this->match( $q ), 'term_id' ) );
	}
	public function update( $t, $data, $where ) {
		$n = 0;
		foreach ( $this->rows as &$r ) {
			if ( $r['taxonomy'] === $where['taxonomy'] ) {
				$r['taxonomy'] = $data['taxonomy'];
				++$n;
			}
		}
		return $n;
	}
	public function query( $q ) {
		preg_match( "/SET taxonomy = '([^']+)'/", $q, $s );
		$keys = array_keys( $this->match( $q ) );
		foreach ( $keys as $k ) {
			$this->rows[ $k ]['taxonomy'] = $s[1];
		}
		return count( $keys );
	}
	public function names() {
		return array_column( $this->rows, 'taxonomy', 'term_taxonomy_id' );
	}
}

/** Fresh table and options. */
function reset_db() {
	$GLOBALS['wpdb']    = new Fake_Wpdb();
	$GLOBALS['opts']    = array();
	$GLOBALS['cleaned'] = array();
	$GLOBALS['wpdb']->rows = array(
		array( 'term_taxonomy_id' => 11, 'term_id' => 101, 'taxonomy' => 'aw_campaign_link' ),
		array( 'term_taxonomy_id' => 12, 'term_id' => 102, 'taxonomy' => 'aw_campaign_link' ),
		array( 'term_taxonomy_id' => 21, 'term_id' => 201, 'taxonomy' => 'aw_media_group' ),
		array( 'term_taxonomy_id' => 31, 'term_id' => 301, 'taxonomy' => 'post_tag' ),
	);
}

require dirname( __DIR__ ) . '/includes/taxonomy-rename.php';

$fails = 0;
/** Assert. */
function check( $name, $ok ) {
	global $fails;
	fwrite( STDOUT, ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n" ); // CLI test output.
	$fails += $ok ? 0 : 1;
}

check( 'old campaign name maps to new', 'kits_campaign_link' === kits_taxonomy_current( 'aw_campaign_link' ) );
check( 'old media name maps to new', 'kits_media_group' === kits_taxonomy_current( 'aw_media_group' ) );
check( 'new name is unchanged', 'kits_campaign_link' === kits_taxonomy_current( 'kits_campaign_link' ) );
check( 'other taxonomy is unchanged', 'post_tag' === kits_taxonomy_current( 'post_tag' ) );

reset_db();
$r = kits_taxonomy_rename_run( true );
check( 'dry run counts rows', array( 'aw_campaign_link' => 2, 'aw_media_group' => 1 ) === $r );
check( 'dry run changes nothing', 'aw_campaign_link' === $wpdb->names()[11] && 'aw_media_group' === $wpdb->names()[21] );
check( 'dry run keeps no backup and no marker', false === get_option( 'kits_taxonomy_rename_backup' ) && false === get_option( 'kits_taxonomy_rename' ) );

$r = kits_taxonomy_rename_run();
$n = $wpdb->names();
check( 'run renames campaign rows', 'kits_campaign_link' === $n[11] && 'kits_campaign_link' === $n[12] );
check( 'run renames media rows', 'kits_media_group' === $n[21] );
check( 'run leaves other taxonomies', 'post_tag' === $n[31] );
check( 'run keeps term ids', array( 101, 102, 201, 301 ) === array_column( $wpdb->rows, 'term_id' ) );
check( 'run saves backup ids', array( 'aw_campaign_link' => array( 11, 12 ), 'aw_media_group' => array( 21 ) ) === get_option( 'kits_taxonomy_rename_backup' ) );
check( 'run sets marker', '1' === get_option( 'kits_taxonomy_rename' ) );
check( 'run cleans the changed terms', array( 101, 102, 201 ) === $GLOBALS['cleaned'] );
check( 'counts after run', array( 'aw_campaign_link' => 0, 'kits_campaign_link' => 2, 'aw_media_group' => 0, 'kits_media_group' => 1 ) === kits_taxonomy_rename_counts() );

$r = kits_taxonomy_rename_run();
check( 'second run renames nothing', array( 'aw_campaign_link' => 0, 'aw_media_group' => 0 ) === $r );
check( 'second run keeps backup', array( 'aw_campaign_link' => array( 11, 12 ), 'aw_media_group' => array( 21 ) ) === get_option( 'kits_taxonomy_rename_backup' ) );

$wpdb->rows[] = array( 'term_taxonomy_id' => 13, 'term_id' => 103, 'taxonomy' => 'kits_campaign_link' ); // made after the rename
$r            = kits_taxonomy_rename_undo();
$n            = $wpdb->names();
check( 'undo puts rows back', array( 'aw_campaign_link' => 2, 'aw_media_group' => 1 ) === $r && 'aw_campaign_link' === $n[11] && 'aw_media_group' === $n[21] );
check( 'undo leaves a term made after the rename', 'kits_campaign_link' === $n[13] );
check( 'undo marks undone', 'undone' === get_option( 'kits_taxonomy_rename' ) );

$hook = $GLOBALS['actions']['init'][0];
reset_db();
$hook();
check( 'first load renames', 'kits_campaign_link' === $wpdb->names()[11] && '1' === get_option( 'kits_taxonomy_rename' ) );
check( 'first load drops its lock', false === get_option( 'kits_taxonomy_rename_lock' ) );

reset_db();
update_option( 'kits_taxonomy_rename', 'undone' );
$hook();
check( 'after undo the first-load rename does not run', 'aw_campaign_link' === $wpdb->names()[11] );

reset_db();
update_option( 'kits_taxonomy_rename_lock', time() );
$hook();
check( 'a fresh lock skips the run', 'aw_campaign_link' === $wpdb->names()[11] );

reset_db();
update_option( 'kits_taxonomy_rename_lock', time() - 600 );
$hook();
check( 'a stale lock runs it', 'kits_campaign_link' === $wpdb->names()[11] && false === get_option( 'kits_taxonomy_rename_lock' ) );

fwrite( STDOUT, $fails ? "\n$fails failed\n" : "\nall passed\n" ); // CLI test output.
exit( $fails ? 1 : 0 );
