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
 * Description:       The Directory in WordPress: listings with their places and types, a map and Near me, claims from the organisations, Claimed and Verified marks, and "Find help near you". Needs Animal Writes Core.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wugweb.com/plugins/directoryweb/
 * Text Domain:       directoryweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The hook names are fixed, not built from __FILE__: Animal Writes Core loads this file from the real folder, before
// WordPress maps a symlinked plugin folder, so __FILE__ can give a name WordPress never fires.
if ( defined( 'DIRECTORYWEB_LOADED' ) ) {
	return; // Loaded already (active on its own and also loaded by Animal Writes Core).
}

// The Directory is built on Animal Writes Core's content types, meta and helpers. On its own, without Core, it says so
// and does nothing else (Core loads this file after its own files, so the constant is there then).
if ( ! defined( 'AW_CORE_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'DirectoryWeb needs Animal Writes Core. Activate Animal Writes Core and DirectoryWeb will start.', 'directoryweb' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'DIRECTORYWEB_LOADED', '1.0.0' );
define( 'DIRECTORYWEB_VERSION', '1.0.0' );
define( 'DIRECTORYWEB_FILE', __FILE__ );
define( 'DIRECTORYWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORYWEB_URL', plugin_dir_url( __FILE__ ) );

// Kept in the order Animal Writes Core loaded these files in, so hooks added at the same priority keep their order.
$directoryweb_includes = array(
	'directoryweb-help-tab',
	'places',
	'places-admin',
	'directory',
	'gov-verified',
	'geocode',
	'directory-map',
	'claims',
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

