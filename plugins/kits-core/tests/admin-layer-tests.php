<?php
/**
 * Admin layer tests that need no WordPress (owner rule: no local WordPress or MySQL). The admin layer moved from
 * Animal Writes Core to Kits Core on 18 Nov 2026 (includes/admin-layer/); these check that, with Animal Writes' values
 * (the site layer's assets/site/admin.php), what it gives is what Animal Writes Core gave: the menu order, the
 * site's screens and their menu positions, the hidden upsell links, comments off, and the value parsers (campaign
 * metrics, district helplines, issuer short names). The Deploy check also loads every screen in a real WordPress
 * (tools/ci/smoke.sh).
 *
 * Usage: php plugins/kits-core/tests/admin-layer-tests.php
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' !== PHP_SAPI ) {
		exit;
	}
	define( 'ABSPATH', __DIR__ . '/' );
}

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, Universal.Files.SeparateFunctionsFromOO -- a command-line test runner with WordPress stand-ins.


define( 'KITS_MENU', 'kits' );

/* ---------- WordPress stand-ins: hooks run, everything else is the plain value ---------- */

$GLOBALS['kits_test_hooks'] = array();

/**
 * Stand-in for add_filter().
 *
 * @param string   $hook     Hook.
 * @param callable $cb       Callback.
 * @param int      $priority Priority.
 */
function add_filter( $hook, $cb, $priority = 10 ) {
	$GLOBALS['kits_test_hooks'][ $hook ][ $priority ][] = $cb;
	return true;
}

/**
 * Stand-in for add_action().
 *
 * @param string   $hook     Hook.
 * @param callable $cb       Callback.
 * @param int      $priority Priority.
 */
function add_action( $hook, $cb, $priority = 10 ) {
	return add_filter( $hook, $cb, $priority );
}

/**
 * Stand-in for apply_filters().
 *
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @param mixed  ...$args More arguments.
 */
function apply_filters( $hook, $value, ...$args ) {
	$by = $GLOBALS['kits_test_hooks'][ $hook ] ?? array();
	ksort( $by );
	foreach ( $by as $list ) {
		foreach ( $list as $cb ) {
			$value = $cb( $value, ...$args );
		}
	}
	return $value;
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $v ) { return (string) $v; }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $v ) { return (string) $v; }
}

/**
 * Stand-in for absint().
 *
 * @param mixed $v Value.
 */
function absint( $v ) {
	return abs( (int) $v );
}

/** Stand-in for __return_true(). */
function __return_true() {
	return true;
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
 * Stand-in for wp_http_validate_url().
 *
 * @param string $u URL.
 */
function wp_http_validate_url( $u ) {
	return filter_var( $u, FILTER_VALIDATE_URL ) ? $u : false;
}

/**
 * Stand-in for get_option().
 *
 * @param string $name    Option.
 * @param mixed  $default Default.
 */
function get_option( $name, $default = false ) {
	return $GLOBALS['kits_test_options'][ $name ] ?? $default;
}

/**
 * Stand-in for add_settings_error().
 *
 * @param string ...$args Arguments.
 */
function add_settings_error( ...$args ) {
	$GLOBALS['kits_test_errors'][] = $args;
}

/**
 * Stand-in for plugin_basename().
 *
 * @param string $f File.
 */
function plugin_basename( $f ) {
	return basename( dirname( $f ) ) . '/' . basename( $f );
}

$dir = dirname( __DIR__ ) . '/includes/admin-layer/';
require $dir . 'arrange.php';
require $dir . 'tidy.php';
require $dir . 'site-screens.php';
require $dir . 'cli-commands.php';
require $dir . 'edit-fields.php';
require $dir . 'site-options.php';

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

/* ---------- Standalone Kits Core: nothing of the site's ---------- */

kits_t( false === kits_comments_off(), 'standalone: comments stay on' );
kits_t( array( 'category', 'post_tag' ) === kits_admin_shared_taxonomies(), 'standalone: Categories and Tags under Classification' );
$order = kits_admin_menu_order();
kits_t( 'index.php' === $order[0] && 'edit.php' === $order[1] && 'edit.php?post_type=page' === $order[2], 'standalone: Dashboard, Posts, Pages first' );
kits_t( array() === apply_filters( 'kits_retired_shortcodes', array() ), 'standalone: no retired shortcodes' );
kits_t( array() === kits_site_overview(), 'standalone: no Overview' );
kits_t( '' === kits_edit_screen_map_file_for_test(), 'standalone: no edit-screen map' );

/**
 * The map file the filter gives (edit-screens.php is not loaded here: it reads WordPress's meta boxes).
 */
function kits_edit_screen_map_file_for_test(): string {
	return (string) apply_filters( 'kits_edit_screen_map_file', '' );
}

/* ---------- With Animal Writes' values: what Animal Writes Core gave ---------- */

/** The theme's site folder (assets/site/), as the theme's kits_site_path() gives it. */
function kits_site_path( string $file ): string {
	return dirname( __DIR__, 3 ) . '/themes/kits-by-wugweb/assets/site/' . ltrim( $file, '/' );
}
require kits_site_path( 'admin.php' ); // Animal Writes' values, moved from Animal Writes Core's admin-config.php (19 Nov 2026)
define( 'KITS_CAMPAIGN_LINK', 'kits_campaign_link' );

kits_t( true === kits_comments_off(), 'Animal Writes: comments off' );
kits_t( array( 'category', 'post_tag', 'kits_campaign_link' ) === kits_admin_shared_taxonomies(), 'Animal Writes: Categories, Tags and Linked campaigns under Classification' );
$was = array(
	'index.php',
	'edit.php?post_type=aw_listing',
	'edit.php?post_type=aw_news',
	'edit.php',
	'edit.php?post_type=aw_legal',
	'edit.php?post_type=aw_opportunity',
	'edit.php?post_type=aw_campaign',
	'edit.php?post_type=aw_press',
	'edit.php?post_type=page',
	'upload.php',
	'aw-classification',
	'separator-wugweb',
	'kits',
	'directoryweb',
	'aw-sourcing-menu',
	'aw-seo-plus',
	'aw-search-insights',
	'mailweb',
	'planweb',
	'separator2',
	'themes.php',
	'plugins.php',
	'users.php',
	'tools.php',
	'options-general.php',
	'googlesitekit-dashboard',
	'litespeed',
);
kits_t( kits_admin_menu_order() === $was, 'Animal Writes: menu order exactly as in admin-ia.php' );
kits_t( array( 'aw_testimonials', 'aw_banner' ) === apply_filters( 'kits_retired_shortcodes', array() ), 'Animal Writes: [aw_testimonials] and [aw_banner] print nothing' );
kits_t( array( 'aw_message', 'aw_claim' ) === apply_filters( 'kits_admin_hidden_post_types', array() ), 'Animal Writes: messages and claims out of the menu' );
kits_t( array( 'aw_message' => 'aw_read' ) === apply_filters( 'kits_admin_read_marks', array() ), 'Animal Writes: opening a message marks it read' );
$labels = apply_filters( 'kits_admin_post_type_labels', array() );
kits_t( 'Campaigns' === $labels['aw_campaign']['name'] && 'Contact messages' === $labels['aw_message']['all_items'], 'Animal Writes: labels' );
kits_t( kits_site_path( 'data/edit-screen-map.json' ) === kits_edit_screen_map_file_for_test() && is_readable( kits_edit_screen_map_file_for_test() ), 'Animal Writes: the edit-screen map is the site\'s file' );
kits_t( 'aw_legal' === kits_library_post_type(), 'Animal Writes: Library type' );

/* ---------- The site's screens and their menu positions ---------- */

$screens = kits_site_screens( true );
kits_t( array( 'animal-writes', 'aw-dates', 'aw-site-check', 'aw-elsewhere', 'aw-modules', 'aw-edit-screens' ) === array_keys( $screens ), 'site screens in the same order, without the retired Keys' );
kits_t( array( 20, 21, 22, 23, 24, 25, 29 ) === array_map( 'kits_site_menu_priority', array( 'animal-writes', 'aw-dates', 'aw-site-check', 'aw-elsewhere', 'aw-modules', 'aw-edit-screens', 'unknown' ) ), 'menu positions 20 to 25, unknown 29' );
kits_t( 'manage_options' === kits_site_screen_cap( 'aw-modules' ) && 'edit_pages' === kits_site_screen_cap( 'aw-elsewhere' ) && 'edit_others_posts' === kits_site_screen_cap( 'aw-dates' ), 'capabilities as before' );
kits_t( 'kits' === kits_site_menu_parent(), 'the menu parent is Kits' );
kits_t( 24 === kits_site_menu_priority( 'aw-modules' ), 'the Modules screen position' );

/* ---------- Tidy: labels and the hidden links ---------- */

kits_t( 'To review' === kits_admin_label( 'To review <span class="awaiting-mod count-3"><span class="pending-count">3</span></span>' ), 'label without its count bubble' );
kits_t( kits_admin_label_in( 'Unlock PRO now', array( 'Unlock PRO' ) ) && ! kits_admin_label_in( 'Dashboard', array( 'Unlock PRO' ) ), 'label starts with a hidden one' );
kits_t( array( 'Available Tools', 'Server-Timing', 'Scheduled Actions' ) === apply_filters( 'kits_admin_hide', KITS_ADMIN_HIDE )['Tools'], 'Tools: the same three links hidden' );
kits_t( array( 'SCF' ) === KITS_ADMIN_HIDE_TOP && array( 'Rank Math SEO', 'UpdraftPlus' ) === KITS_ADMIN_BAR_HIDE, 'SCF menu and two admin-bar shortcuts hidden' );
kits_t( ' <span class="awaiting-mod count-2"><span class="pending-count">2</span></span>' === kits_menu_count( 2 ) && '' === kits_menu_count( 0 ), 'menu count bubble' );

/* ---------- Edit fields: campaign metrics ---------- */

$lines = "visits | Visits | 1,200 | 1200 | 100 | 2026-11-01 | 30 | team | GA4 | https://example.org/\nletters | Letters";
$list  = kits_edit_fields_metric_list( $lines );
kits_t( is_array( $list ) && 2 === count( $list ), 'two metric lines read' );
kits_t( 1200.0 === $list[0]['count'] && 100 === $list[0]['threshold'] && 30 === $list[0]['stale_days'] && '2026-11-01' === $list[0]['verified'], 'numbers and the date kept with their types' );
kits_t( array( 'key' => 'letters', 'label' => 'Letters' ) === $list[1], 'empty fields are not stored' );
kits_t( null === kits_edit_fields_metric_list( 'visits | Visits | 1 | 1 | 1 | 2026-13-40' ), 'a bad date keeps every metric as it was' );
kits_t( null === kits_edit_fields_metric_list( 'a|b|c|d|e|f|g|h|i|j|k' ), 'more than ten fields is refused' );
kits_t( kits_edit_fields_is_day( '2026-02-28' ) && ! kits_edit_fields_is_day( '2026-02-30' ), 'a real day only' );

/* ---------- Modules: district helplines and issuer short names ---------- */

$GLOBALS['kits_test_options']['aw_district_helplines'] = array();
$h = kits_site_options_helplines_clean( "123 | District office | 0100 | Animal welfare | Collectorate | https://example.org/ | 2026-11-01\n123 | Vet | 0200" );
kits_t( 2 === count( $h[123] ) && 'Vet' === $h[123][1]['name'] && '' === $h[123][1]['checked'], 'helplines: two numbers for one district' );
$GLOBALS['kits_test_options']['aw_district_helplines'] = $h;
$GLOBALS['kits_test_errors']                           = array();
kits_t( kits_site_options_helplines_clean( "123 | | 0100\n" ) === $h && 1 === count( $GLOBALS['kits_test_errors'] ), 'helplines: a bad line keeps the saved list and says which line' );
kits_t( kits_site_options_helplines_clean( '' ) === $h, 'helplines: an empty box keeps the saved list' );
kits_t( array( 'awbi' => 'AWBI' ) === kits_site_options_short_clean( "awbi = AWBI\nbad line\n = x" ), 'issuer short names: slug = short' );
kits_t( array( 3, 12 ) === kits_site_options_ids( '3, 12, 3, x' ), 'sample listing IDs' );
kits_t( isset( KITS_FOLLOW_EXTRA['aw_follow_reddit'] ), 'Follow us accounts' );
kits_t( array( 'aw_cdn', 'aw_modules_social', 'aw_modules_forms' ) === array_keys( kits_site_reset_sections() ), 'Reset this section: the Kits sections' );

/* ---------- Help → Docs → Commands, read from WP-CLI's registry ---------- */

$tree = array(
	'post'          => array( 'what' => 'Core.' ),
	'aw'            => array(
		'what' => 'Group.',
		'subs' => array(
			'sort-dates' => array( 'what' => 'News and Library sort dates.' ),
			'crm-setup'  => array( 'what' => 'FluentCRM sender.' ),
		),
	),
	'aw-edit-screens' => array( 'what' => 'Edit-screen boxes.' ),
	'awful'           => array( 'what' => 'Not ours: aw is a whole word.' ),
	'kits'            => array(
		'subs' => array(
			'preset' => array(
				'subs' => array( 'list' => array( 'what' => 'List the presets.' ) ),
			),
			'export' => array( 'what' => ' Export. ' ),
		),
	),
);
$names = array_column( kits_cli_command_rows( $tree, array( 'kits', 'aw' ) ), 'name' );
kits_t( array( 'aw crm-setup', 'aw sort-dates', 'aw-edit-screens', 'kits export', 'kits preset list' ) === $names, 'commands: the site\'s, every runnable one, sorted; core and look-alikes left out' );
kits_t( 'Export.' === kits_cli_command_rows( $tree, array( 'kits' ) )[0]['what'], 'commands: one-line description trimmed' );
kits_t( array() === kits_cli_command_rows( $tree, array() ), 'commands: no prefixes, nothing listed' );
kits_t( in_array( 'kits', kits_cli_command_prefixes(), true ) && in_array( 'aw', kits_cli_command_prefixes(), true ), 'commands: the Kits prefixes, and aw from admin-config.php' );
$GLOBALS['kits_test_options']['kits_cli_commands'] = array( array( 'name' => 'kits export', 'what' => 'x' ) );
kits_t( 1 === count( kits_cli_commands() ), 'commands: the list kept at the last wp run' );
$GLOBALS['kits_test_options']['kits_cli_commands'] = 'broken';
kits_t( array() === kits_cli_commands(), 'commands: a broken option lists nothing' );

/* ---------- Classic screens in the Kits frame (includes/admin-kits-frame.php) ---------- */

if ( ! defined( 'KITS_CAP' ) ) {
	define( 'KITS_CAP', 'edit_posts' );
}
require dirname( __DIR__ ) . '/includes/admin-kits-frame.php';
// The joins are read once per request, so they are all registered before the first read.
$joined = array(
	'includes/media-webp.php'          => array( 'aw-media-webp', 'manage_options', 'kits-tools' ),
	'includes/tools/media-merge.php'   => array( 'aw-media-duplicates', 'manage_options', 'kits-tools' ),
	'includes/tools/media-reuse.php'   => array( 'aw-media-reuse', 'manage_options', 'kits-tools' ),
	'includes/campaign-letters.php'    => array( 'aw-letter-report', 'edit_posts', '' ),
	'../../themes/kits-by-wugweb/assets/site/includes/social-posts.php' => array( 'aw-modules', 'manage_options', 'kits-settings' ), // moved to the site layer, 19 Nov 2026
	'includes/taxonomy-rename.php'     => array( 'kits-taxonomy-rename', 'manage_options', 'kits-tools' ),
);
foreach ( $joined as $file => $def ) {
	$src = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );
	kits_t( false !== strpos( $src, "'kits_frame_extra_screens'" ) && false !== strpos( $src, "\$screens['{$def[0]}'] = array( '{$def[1]}', '{$def[2]}' )" ), "frame: {$def[0]} joins with its own capability ({$def[1]})" );
	kits_t( false === strpos( $src, '<div class="wrap"><h1>' ), "frame: {$file} prints no classic wrap and heading" );
	kits_t( false !== strpos( $src, "kits_frame_screen( '{$def[0]}'" ), "frame: {$def[0]} prints through kits_frame_screen()" );
	add_filter(
		'kits_frame_extra_screens',
		function ( $screens ) use ( $def ) {
			$screens[ $def[0] ] = array( $def[1], $def[2] );
			return $screens;
		}
	);
}
add_filter(
	'kits_frame_extra_screens',
	function ( $screens ) {
		$screens['kits-tools'] = array( 'read', '' ); // A join cannot replace a Kits screen.
		return $screens;
	}
);
$frame_after = kits_frame_screens();
kits_t( array( 'kits', 'kits-settings', 'kits-tools', 'kits-help' ) === array_slice( array_keys( $frame_after ), 0, 4 ), 'frame: the four Kits screens come first' );
kits_t( 'manage_options' === $frame_after['kits-tools'] && 'manage_options' === $frame_after['aw-media-webp'] && 'edit_posts' === $frame_after['aw-letter-report'], 'frame: joined screens keep their capability; the Kits four cannot be replaced' );
kits_t( 'kits-tools' === kits_frame_extra_tab( 'aw-media-reuse' ) && 'kits-settings' === kits_frame_extra_tab( 'aw-modules' ) && '' === kits_frame_extra_tab( 'nope' ), 'frame: the media screens sit under the Tools tab' );

echo "$count checks, $fails failed\n";
exit( $fails ? 1 : 0 );
