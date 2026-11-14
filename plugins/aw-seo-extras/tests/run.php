<?php
/**
 * RankWeb tests that need no WordPress (owner rule: no local WordPress or MySQL), for the settings the new Settings
 * screen edits (includes/settings.php):
 *
 * 1. Every field's default, shown as text and read back, is the default again (so a Save with nothing changed stores
 *    nothing: only values that differ from the default are stored).
 * 2. A Save of every group with nothing changed writes nothing: the option stays absent when it was absent (as on live,
 *    11 Nov 2026), and stays byte for byte the same when it holds values, including InsightWeb's 'search-insights'.
 * 3. A changed field is stored, and Restore defaults takes it out again without touching other groups.
 * 4. Modules: an unchanged Save writes nothing; switching one off stores only { on: false }.
 * 5. An 'on' => true from an older save is kept: an unchanged modules Save writes nothing.
 * 6. The register_setting() sanitize callback returns stored values unchanged.
 * 7. Reset this section: Modules and a field group go back to their registered defaults.
 *
 * Usage: php plugins/aw-seo-extras/tests/run.php
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) { // web requests stop here; the Deploy check runs this from the command line
	exit;
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.GlobalVariablesOverride, Squiz.Commenting.FunctionComment.MissingParamTag -- a command-line test runner with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['rw_options'] = array();
$GLOBALS['rw_writes']  = 0;

/** Stub: options in memory; false when absent, as WordPress. */
function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['rw_options'] ) ? $GLOBALS['rw_options'][ $name ] : $fallback;
}

/** Stub: counts writes. */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	++$GLOBALS['rw_writes'];
	$GLOBALS['rw_options'][ $name ] = $value;
	return true;
}

/** Stub: hooks are not run here. */
function add_action() {}

/** WordPress's sanitize_key. */
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

/** WordPress's wp_unslash for strings and arrays. */
function wp_unslash( $v ) {
	return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v );
}

/** WordPress's sanitize_text_field (_sanitize_text_fields without keeping line breaks), as in wp-includes/formatting.php. */
function sanitize_text_field( $str ) {
	$str = (string) $str;
	if ( ! preg_match( '//u', $str ) ) {
		return '';
	}
	if ( false !== strpos( $str, '<' ) ) {
		$str = preg_replace_callback( '%<[^>]*?((?=<)|>|$)%', fn( $m ) => false === strpos( $m[0], '>' ) ? htmlspecialchars( $m[0] ) : $m[0], $str );
		$str = trim( strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str ) ) );
		$str = str_replace( "<\n", "&lt;\n", $str );
	}
	$str   = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	$str   = trim( $str );
	$found = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $str, $match ) ) {
		$str   = str_replace( $match[0], '', $str );
		$found = true;
	}
	if ( $found ) {
		$str = trim( preg_replace( '/ +/', ' ', $str ) );
	}
	return $str;
}

require dirname( __DIR__ ) . '/includes/settings.php';

$fails = 0;
$count = 0;

/**
 * One check.
 *
 * @param bool   $ok   Passed.
 * @param string $what What was checked.
 */
function rw_check( bool $ok, string $what ): void {
	global $fails, $count;
	++$count;
	if ( ! $ok ) {
		++$fails;
		echo "FAIL {$what}\n";
	}
}

/** The texts of a tab's fields as the screen shows them (stored value, else the default). */
function rw_texts( string $tab ): array {
	$saved = aw_seo_saved();
	$out   = array();
	foreach ( aw_seo_tab_fields( $tab ) as $key => $list ) {
		foreach ( $list as $const => $f ) {
			$v             = array_key_exists( $const, $saved[ $key ] ?? array() ) ? $saved[ $key ][ $const ] : $f['default'];
			$out[ $const ] = aw_seo_field_text( $f['type'], $v );
		}
	}
	return $out;
}

/** Switchable modules as the screen shows them. */
function rw_on(): array {
	$on = array();
	foreach ( aw_seo_modules() as $key => $mod ) {
		if ( ! empty( $mod['switch'] ) ) {
			$on[ $key ] = aw_seo_module_on( $key );
		}
	}
	return $on;
}

// 1. Defaults survive the text round trip.
$fields = 0;
foreach ( aw_seo_modules() as $key => $mod ) {
	foreach ( $mod['fields'] as $const => $f ) {
		++$fields;
		$back = aw_seo_field_value( $f['type'], aw_seo_field_text( $f['type'], $f['default'] ) );
		rw_check( $back === $f['default'], "default round trip {$key}.{$const}" );
	}
}
rw_check( 38 === $fields, "38 fields found ({$fields})" ); // 31, and 7 for Internal links (14 Nov 2026)

$tabs = array_keys( array_filter( aw_seo_setting_tabs(), fn( $t ) => empty( $t['hidden'] ) ) );
rw_check( 8 === count( $tabs ), 'eight groups (Internal links added 14 Nov 2026): ' . implode( ', ', $tabs ) );

// 2a. Option absent (live, 11 Nov 2026): every group saved unchanged writes nothing.
$GLOBALS['rw_options'] = array();
$GLOBALS['rw_writes']  = 0;
foreach ( $tabs as $tab ) {
	if ( 'modules' === $tab ) {
		aw_seo_settings_save( 'modules', array(), false, rw_on() );
	} else {
		aw_seo_settings_save( $tab, rw_texts( $tab ), false );
	}
}
rw_check( 0 === $GLOBALS['rw_writes'], 'absent option: no write after an unchanged Save of every group (' . $GLOBALS['rw_writes'] . ' writes)' );
rw_check( ! array_key_exists( AW_SEO_SETTINGS, $GLOBALS['rw_options'] ), 'absent option stays absent' );

// 2b. Option with values (a changed field, a module off, InsightWeb's numbers): unchanged Saves keep it byte for byte.
$stored                = array(
	'search-insights' => array(
		'AW_SI_DAYS' => 90,
		'on'         => 1,
	),
	'general'         => array( 'AW_SEO_DESC_MAX' => 150 ),
	'titles'          => array( 'AW_SEO_ITEM_TYPES' => array( 'aw_listing', 'aw_news', 'page' ) ),
	'llms'            => array( 'on' => false ),
	'faq'             => array(
		'AW_FAQ_HUBS' => array(
			'news'    => 'News questions | more',
			'library' => 'Library',
		),
	),
);
$GLOBALS['rw_options'] = array( AW_SEO_SETTINGS => $stored );
$GLOBALS['rw_writes']  = 0;
$bytes                 = serialize( $stored );
foreach ( $tabs as $tab ) {
	if ( 'modules' === $tab ) {
		aw_seo_settings_save( 'modules', array(), false, rw_on() );
	} else {
		aw_seo_settings_save( $tab, rw_texts( $tab ), false );
	}
}
rw_check( 0 === $GLOBALS['rw_writes'], 'stored option: no write after an unchanged Save of every group (' . $GLOBALS['rw_writes'] . ' writes)' );
rw_check( serialize( get_option( AW_SEO_SETTINGS ) ) === $bytes, 'stored option byte for byte the same' );

// 3. A change is stored; Restore defaults takes it out; other groups and InsightWeb's numbers stay.
$t                    = rw_texts( 'ai' );
$t['AW_MD_VARY_HTML'] = '0';
aw_seo_settings_save( 'ai', $t, false );
$now = get_option( AW_SEO_SETTINGS );
rw_check( 0 === ( $now['markdown']['AW_MD_VARY_HTML'] ?? null ), 'changed field stored' );
rw_check( $now['search-insights'] === $stored['search-insights'], 'search-insights kept after a save' );
rw_check( 150 === ( $now['general']['AW_SEO_DESC_MAX'] ?? null ), 'another group kept after a save' );
aw_seo_settings_save( 'ai', array(), true );
$now = get_option( AW_SEO_SETTINGS );
rw_check( ! isset( $now['markdown']['AW_MD_VARY_HTML'] ), 'Restore defaults takes the field out' );
rw_check( false === ( $now['llms']['on'] ?? null ), 'Restore defaults keeps a module switch' );
rw_check( serialize( $now ) === $bytes, 'after change and Restore defaults the option is as before' );

// 4. Modules: switching one off stores only { on: false }; on again removes it.
$GLOBALS['rw_options'] = array();
$on                    = rw_on();
$on['redirects']       = false;
aw_seo_settings_save( 'modules', array(), false, $on );
rw_check( array( 'redirects' => array( 'on' => false ) ) === get_option( AW_SEO_SETTINGS ), 'module off stored as { on: false } only' );
$on['redirects'] = true;
aw_seo_settings_save( 'modules', array(), false, $on );
rw_check( array() === get_option( AW_SEO_SETTINGS ), 'module on again: nothing stored' );

// 5. An 'on' => true left by an older save: an unchanged modules Save keeps it and writes nothing.
$GLOBALS['rw_options'] = array( AW_SEO_SETTINGS => array( 'faq' => array( 'on' => true ) ) );
$GLOBALS['rw_writes']  = 0;
aw_seo_settings_save( 'modules', array(), false, rw_on() );
rw_check( 0 === $GLOBALS['rw_writes'], "older 'on' => true: no write after an unchanged modules Save" );

// 6. The registered sanitize callback (register_setting(), runs on every update_option()) returns a stored value
//    unchanged: every field at its default, module switches, InsightWeb's numbers and a key no field knows.
$full = array(
	'search-insights' => array(
		'AW_SI_HIGH_TRENDS' => 25,
		'on'                => true,
	),
	'llms'            => array( 'on' => false ),
	'faq'             => array( 'AW_FAQ_OFF_TYPES' => array( 'aw_listing' ) ),
);
foreach ( aw_seo_modules() as $key => $mod ) {
	foreach ( $mod['fields'] as $const => $f ) {
		$full[ $key ][ $const ] = $f['default'];
	}
}
rw_check( serialize( aw_seo_settings_sanitize( $full ) ) === serialize( $full ), 'sanitize callback: a stored value comes back byte for byte' );
rw_check( serialize( aw_seo_settings_sanitize( $stored ) ) === serialize( $stored ), 'sanitize callback: the test option comes back byte for byte' );
rw_check( array( 'titles' => array( 'AW_SEO_ITEM_TYPES' => array( 'a', 'b' ) ) ) === aw_seo_settings_sanitize( array( 'titles' => array( 'AW_SEO_ITEM_TYPES' => array( '<b>a</b>', 'b' ) ) ) ), 'sanitize callback: list texts cleaned' );

// 7. Reset this section on Modules: every module on again, "Delete this plugin's data" off; other groups kept.
$GLOBALS['rw_options'] = array(
	AW_SEO_SETTINGS    => array(
		'llms'    => array( 'on' => false ),
		'general' => array( 'AW_SEO_DESC_MAX' => 150 ),
	),
	AW_SEO_DELETE_DATA => 1,
);
aw_seo_settings_reset( 'modules' );
rw_check( array( 'general' => array( 'AW_SEO_DESC_MAX' => 150 ) ) === get_option( AW_SEO_SETTINGS ), 'Reset Modules: switches back on, fields kept' );
rw_check( 0 === get_option( AW_SEO_DELETE_DATA ), 'Reset Modules: delete data back to off' );
aw_seo_settings_reset( 'titles' );
rw_check( array() === get_option( AW_SEO_SETTINGS ), 'Reset a field group: its fields back to defaults' );

// Backup before uninstall deletes (includes/uninstall-backup.php): the lines, the 500-row batches, the folder guard.
require_once dirname( __DIR__ ) . '/includes/uninstall-backup.php';
$ub_t = static function ( bool $ok, string $what ): void {
	rw_check( $ok, $what );
};
$ub_h = json_decode( aw_seo_backup_header( 'aw-seo-extras', '1.2.3', 'https://example.org/', 1763000000, array( 'a_opt', 'b_opt' ), array( 'wp_x' ) ), true );
$ub_t( 'header' === $ub_h['type'] && 'aw-seo-extras' === $ub_h['plugin'] && '1.2.3' === $ub_h['version'] && 'https://example.org/' === $ub_h['site'], 'backup: header names the plugin, version and site' );
$ub_t( '2025-11-13T02:13:20Z' === $ub_h['time'] && array( 'a_opt', 'b_opt' ) === $ub_h['options'] && array( 'wp_x' ) === $ub_h['tables'], 'backup: header has the time, the options and the tables' );
$ub_l = aw_seo_backup_option_line( 'a_opt', 'a:1:{s:1:"k";s:3:"é/";}', 'yes' );
$ub_o = json_decode( $ub_l, true );
$ub_t( "\n" === substr( $ub_l, -1 ) && 1 === substr_count( $ub_l, "\n" ), 'backup: one line per option' );
$ub_t( 'option' === $ub_o['type'] && 'a:1:{s:1:"k";s:3:"é/";}' === $ub_o['value'] && 'yes' === $ub_o['autoload'] && ! isset( $ub_o['b64'] ), 'backup: option value kept exactly as stored' );
$ub_o = json_decode( aw_seo_backup_option_line( 'bin', "\xff\x00\xfe", 'no' ), true );
$ub_t( array( 'value' ) === $ub_o['b64'] && "\xff\x00\xfe" === base64_decode( $ub_o['value'] ), 'backup: a value that is not UTF-8 is kept base64-encoded' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- the test reads the value back
$ub_r = json_decode( aw_seo_backup_row_line( 'wp_x', array( 'id' => '7', 'note' => null, 'blob' => "\x80" ) ), true );
$ub_t( 'row' === $ub_r['type'] && 'wp_x' === $ub_r['table'] && '7' === $ub_r['row']['id'] && null === $ub_r['row']['note'] && array( 'blob' ) === $ub_r['b64'], 'backup: row line keeps every column, null and binary' );
$ub_t( '{}' === substr( trim( aw_seo_backup_row_line( 'wp_x', array() ) ), -3, 2 ), 'backup: an empty row is an object' );
$ub_t( 500 === AW_SEO_BACKUP_BATCH, 'backup: tables are read 500 rows at a time' );
$ub_calls = array();
$ub_seen  = 0;
$ub_fetch = static function ( int $offset, int $limit ) use ( &$ub_calls ) {
	$ub_calls[] = array( $offset, $limit );
	return array_fill( 0, max( 0, min( $limit, 1203 - $offset ) ), array( 'id' => '1' ) );
};
$ub_n     = aw_seo_backup_rows(
	$ub_fetch,
	static function ( array $row ) use ( &$ub_seen ): bool {
		++$ub_seen;
		return true;
	}
);
$ub_t( 1203 === $ub_n && 1203 === $ub_seen && array( array( 0, 500 ), array( 500, 500 ), array( 1000, 500 ) ) === $ub_calls, 'backup: 1203 rows in batches of 500' );
$ub_calls = array();
$ub_n     = aw_seo_backup_rows( static fn( int $o, int $l ) => $o < 1000 ? array_fill( 0, $l, array() ) : array(), static fn( array $r ): bool => true );
$ub_t( 1000 === $ub_n, 'backup: an exact multiple of 500 ends on the empty batch' );
$ub_t( false === aw_seo_backup_rows( static fn( int $o, int $l ) => null, static fn( array $r ): bool => true ), 'backup: a read error stops the backup' );
$ub_w = 0;
$ub_n = aw_seo_backup_rows(
	static fn( int $o, int $l ) => array_fill( 0, $l, array() ),
	static function ( array $r ) use ( &$ub_w ): bool {
		return ++$ub_w < 3;
	}
);
$ub_t( false === $ub_n && 3 === $ub_w, 'backup: a write error stops the backup at once' );
$ub_t( 1 === preg_match( '/^aw-seo-extras-uninstall-\d{8}-\d{6}\.jsonl\.gz$/', aw_seo_backup_name( 'aw-seo-extras', 1763000000 ) ) && 'aw-seo-extras-uninstall-20251113-021320.jsonl.gz' === aw_seo_backup_name( 'aw-seo-extras', 1763000000 ), 'backup: file name' );
$ub_dir = sys_get_temp_dir() . '/aw-seo-extras-ub-' . getmypid();
@mkdir( $ub_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a temporary folder for the test
file_put_contents( $ub_dir . '/index.php', 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
$ub_t( aw_seo_backup_protect( $ub_dir ) && false !== strpos( (string) file_get_contents( $ub_dir . '/.htaccess' ), 'Require all denied' ) && 'kept' === file_get_contents( $ub_dir . '/index.php' ), 'backup: folder guarded by .htaccess and index.php, an existing file kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
$ub_gz = gzopen( $ub_dir . '/t.jsonl.gz', 'wb6' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own file
gzwrite( $ub_gz, aw_seo_backup_header( 'aw-seo-extras', '1', 's', 0, array( 'a' ), array() ) . aw_seo_backup_option_line( 'a', 'v', 'yes' ) );
gzclose( $ub_gz );
$ub_lines = gzfile( $ub_dir . '/t.jsonl.gz' );
$ub_t( 2 === count( $ub_lines ) && 'header' === json_decode( $ub_lines[0], true )['type'] && 'v' === json_decode( $ub_lines[1], true )['value'], 'backup: gzip JSON lines read back' );
foreach ( array( 'index.php', '.htaccess', 't.jsonl.gz' ) as $ub_f ) {
	unlink( $ub_dir . '/' . $ub_f ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own files
}
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder
$ub_dir = sys_get_temp_dir() . '/aw-seo-extras-ub-ro-' . getmypid();
@mkdir( $ub_dir, 0555 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions -- a read-only folder for the test
$ub_t( 0 === getmyuid() || ! @aw_seo_backup_protect( $ub_dir ), 'backup: a folder that cannot be written is refused' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the warning is the expected failure
rmdir( $ub_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- the test's own folder

echo $fails ? "{$fails} of {$count} checks failed\n" : "all {$count} RankWeb settings checks passed\n";
exit( $fails ? 1 : 0 );
