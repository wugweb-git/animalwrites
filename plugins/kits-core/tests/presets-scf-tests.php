<?php
/**
 * The presets' SCF field groups match the presets (no WordPress needed): for each built-in preset, its Local JSON file
 * exists under acf-json/{id}/, the group key is group_{id with underscores}, it shows on the preset's post type only,
 * and it has exactly the preset's fields, in order, each with the field type its format calls for. A preset that gains,
 * loses or renames a field fails here until its group is changed too.
 *
 * Usage: php plugins/kits-core/tests/presets-scf-tests.php
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' !== PHP_SAPI ) {
		exit;
	}
	define( 'ABSPATH', __DIR__ . '/' );
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- a command-line test runner with WordPress stand-ins.


define( 'KITS_CORE_DIR', dirname( __DIR__ ) . '/' );

/**
 * Stand-in for __().
 *
 * @param string $s Text.
 */
function __( $s ) {
	return $s;
}

/**
 * Stand-in for sanitize_key().
 *
 * @param string $k Key.
 */
function sanitize_key( $k ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}

/**
 * Stand-in for add_action() / add_filter(): this test only reads the files.
 *
 * @param mixed ...$args Arguments.
 */
function add_action( ...$args ) {
	return true;
}

require KITS_CORE_DIR . 'includes/presets-scf.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $name What it checks.
 */
function kits_t( bool $ok, string $name ) {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL $name\n";
	}
}

// The built-in preset files, as includes/presets.php lists them (read from the file, so the list cannot drift).
preg_match_all( "/'(kits-[a-z-]+)'\s*=>\s*'([a-z-]+\.php)'/", (string) file_get_contents( KITS_CORE_DIR . 'includes/presets.php' ), $m );
$files = array_combine( $m[1], $m[2] );
kits_t( 7 === count( $files ), 'seven built-in presets' );

$want_type = array(
	'text'       => 'text',
	'textarea'   => 'textarea',
	'url'        => 'url',
	'urls'       => 'textarea',
	'date'       => 'date_picker',
	'datetime'   => 'date_time_picker',
	'html'       => 'wysiwyg',
	'attachment' => 'image',
);
$keys      = array();
foreach ( $files as $id => $file ) {
	$preset = include KITS_CORE_DIR . 'includes/presets/' . $file;
	$key    = kits_preset_scf_group_key( $id );
	$path   = KITS_CORE_DIR . 'acf-json/' . $id . '/' . $key . '.json';
	kits_t( is_readable( $path ), "$id: $path exists" );
	$group = json_decode( (string) @file_get_contents( $path ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- reported on the line above.
	if ( ! is_array( $group ) ) {
		kits_t( false, "$id: the file is JSON" );
		continue;
	}
	$keys[] = $group['key'];
	kits_t( $key === $group['key'], "$id: group key $key" );
	kits_t( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => $preset['post_type'] ) ) ) === $group['location'], "$id: shows on {$preset['post_type']} only" );
	kits_t( array_keys( $preset['fields'] ) === array_column( $group['fields'], 'name' ), "$id: the preset's fields, in order" );
	foreach ( $group['fields'] as $f ) {
		$format = $preset['fields'][ $f['name'] ]['format'] ?? '';
		kits_t( 'field_' . $f['name'] === $f['key'], "$id: field key of {$f['name']}" );
		kits_t( ( $want_type[ $format ] ?? '?' ) === $f['type'], "$id: {$f['name']} ($format) is an SCF {$f['type']} field" );
		if ( 'image' === $f['type'] ) {
			kits_t( 'id' === $f['return_format'], "$id: {$f['name']} stores the Media Library ID" );
		}
		if ( 'date_picker' === $f['type'] || 'date_time_picker' === $f['type'] ) {
			kits_t( 0 === strpos( $f['return_format'], 'Y-m-d' ), "$id: {$f['name']} returns Y-m-d" );
		}
	}
}
kits_t( count( $keys ) === count( array_unique( $keys ) ), 'every group key once' );
kits_t( 'group_kits_case_study' === kits_preset_scf_group_key( 'kits-case-study' ), 'group key from a preset id with dashes' );

echo "$count checks, $fails failed\n";
exit( $fails ? 1 : 0 );
