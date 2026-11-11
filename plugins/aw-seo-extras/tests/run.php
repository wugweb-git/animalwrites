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
 *
 * Usage: php plugins/aw-seo-extras/tests/run.php
 *
 * @package aw-seo-extras
 */

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
rw_check( 30 === $fields, "30 fields found ({$fields})" );

$tabs = array_keys( array_filter( aw_seo_setting_tabs(), fn( $t ) => empty( $t['hidden'] ) ) );
rw_check( 7 === count( $tabs ), 'seven groups: ' . implode( ', ', $tabs ) );

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

echo $fails ? "{$fails} of {$count} checks failed\n" : "all {$count} RankWeb settings checks passed\n";
exit( $fails ? 1 : 0 );
