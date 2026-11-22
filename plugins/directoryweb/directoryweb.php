<?php
/**
 * DirectoryWeb
 *
 * @package           directoryweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       DirectoryWeb
 * Plugin URI:        https://wugweb.com/plugins/directoryweb/
 * Description:       The Directory in WordPress: listings with their places and types, a map and Near me, claims from the organisations, Claimed and Verified marks, and "Find help near you". Needs Kits Core.
 * Version:           1.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wugweb.com/plugins/directoryweb/
 * Text Domain:       directoryweb
 * Domain Path:       /languages
 * Requires Plugins:  kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The hook names are fixed, not built from __FILE__: Animal Writes Core loads this file from the real folder, before
// WordPress maps a symlinked plugin folder, so __FILE__ can give a name WordPress never fires.
if ( defined( 'DIRECTORYWEB_LOADED' ) ) {
	return; // Loaded already (active on its own and also loaded by Animal Writes Core).
}

// DirectoryWeb needs Kits Core (its admin shell, list table, icons and dates). Plugins load in name order, so an active
// Kits Core loads after this file: load it now, once, as Animal Writes Core does (kits-core.php stops a second load).
// Animal Writes Core loads Kits Core before this file, so while Core is active this changes nothing.
if ( ! defined( 'KITS_CORE_LOADED' ) && defined( 'WP_PLUGIN_DIR' ) && is_readable( WP_PLUGIN_DIR . '/kits-core/kits-core.php' )
	&& ( in_array( 'kits-core/kits-core.php', (array) get_option( 'active_plugins', array() ), true )
		|| ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )['kits-core/kits-core.php'] ) ) ) ) {
	wp_register_plugin_realpath( WP_PLUGIN_DIR . '/kits-core/kits-core.php' ); // as WordPress does before it loads a plugin: a linked folder keeps its plugins address
	require_once WP_PLUGIN_DIR . '/kits-core/kits-core.php';
}
// Without Kits Core, say so and do nothing else.
if ( ! defined( 'KITS_CORE_LOADED' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'DirectoryWeb needs Kits Core. Activate Kits Core and DirectoryWeb will start.', 'directoryweb' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'DIRECTORYWEB_LOADED', '1.1.0' );
define( 'DIRECTORYWEB_VERSION', '1.1.0' );
define( 'DIRECTORYWEB_FILE', __FILE__ );
define( 'DIRECTORYWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORYWEB_URL', plugin_dir_url( __FILE__ ) );

// Kept in the order Animal Writes Core loaded these files in, so hooks added at the same priority keep their order.
$directoryweb_includes = array(
	'listing-meta', // the listing's meta, phones, hours and never-publish list (from Core's meta.php, 18 Nov 2026)
	'listing-terms', // a listing's types and services, and tel: links (from Core's tags.php and bindings.php, 18 Nov 2026)
	'data-sources', // where a listing's details come from (from Core, 18 Nov 2026)
	'directoryweb-help-tab',
	'places',
	'places-admin',
	'directory',
	'gov-verified',
	'geocode',
	'directory-map',
	'claims',
	'claim-page',
	'claims-review',
	'directoryweb-shape',
	'directoryweb-dashboard',
	'directoryweb-rest',
	'directoryweb-listings',
	'directoryweb-caps',
	'directoryweb-ui',
	'place-intros',
	'unverified',
	'featured',
	'org-people',
	'listing-template',
	'listing-profile',
	'map-picker',
	'local-cards',
	'district-helplines',
	'find-help',
	'plugin-info',
	'bootstrap',
	'directoryweb-fields',
	'directoryweb-status',
	'directoryweb-people',
	'directoryweb-featured-in',
	'directoryweb-local-card',
	'directoryweb-helplines',
);
foreach ( $directoryweb_includes as $directoryweb_include ) {
	require_once DIRECTORYWEB_DIR . 'includes/' . $directoryweb_include . '.php';
}
unset( $directoryweb_includes, $directoryweb_include );

add_action( 'activate_directoryweb/directoryweb.php', 'directoryweb_activate' );
add_action( 'deactivate_directoryweb/directoryweb.php', 'directoryweb_deactivate' );

add_action( 'init', 'directoryweb_register_listing_type' );

/** The listing field groups: this plugin's acf-json/, read and saved here (the keys are the same as ever). */
const DIRECTORYWEB_SCF_GROUPS = array( 'group_aw_listing', 'group_aw_listing_address', 'group_aw_listing_hours', 'group_aw_listing_registrations', 'group_aw_listing_about', 'group_aw_listing_online', 'group_aw_listing_photos', 'group_aw_listing_claimed' );

add_filter(
	'acf/settings/load_json',
	function ( $paths ) {
		$paths[] = DIRECTORYWEB_DIR . 'acf-json';
		return $paths;
	}
);
foreach ( DIRECTORYWEB_SCF_GROUPS as $directoryweb_group ) {
	add_filter( 'acf/settings/save_json/key=' . $directoryweb_group, fn() => DIRECTORYWEB_DIR . 'acf-json' );
}
unset( $directoryweb_group );
add_filter( 'aw_scf_groups', fn( $groups ) => array_merge( (array) $groups, DIRECTORYWEB_SCF_GROUPS ) );

/** Plugins → DirectoryWeb → Settings. */
add_filter(
	'plugin_action_links_directoryweb/directoryweb.php',
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=directoryweb-settings' ) ) . '">' . esc_html__( 'Settings', 'directoryweb' ) . '</a>' );
		return $links;
	}
);

