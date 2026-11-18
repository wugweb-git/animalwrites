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
 * Description:       A directory of organisations: listings with places and types, a free map with Near me, and listings the organisations can claim, which you review field by field.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       directoryweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DIRECTORYWEB_LOADED' ) ) {
	return; // Loaded already (for example by a host plugin).
}

define( 'DIRECTORYWEB_LOADED', '1.0.0' );
define( 'DIRECTORYWEB_VERSION', '1.0.0' );
define( 'DIRECTORYWEB_FILE', __FILE__ );
define( 'DIRECTORYWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORYWEB_URL', plugin_dir_url( __FILE__ ) );

// Standalone: no other plugin is needed. core-lite.php and standalone.php hold what Animal Writes Core used to supply;
// external-services.php is the only gate for outbound requests.
$directoryweb_includes = array(
	'external-services',
	'core-lite',
	'standalone',
	'claims-fluentforms-compat',
	'directoryweb-shell',
	'directoryweb-help-tab',
	'places',
	'places-admin',
	'directory',
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
	'unverified',
	'listing-template',
	'listing-profile',
	'map-picker',
	'bootstrap',
);
foreach ( $directoryweb_includes as $directoryweb_include ) {
	require_once DIRECTORYWEB_DIR . 'includes/' . $directoryweb_include . '.php';
}
unset( $directoryweb_includes, $directoryweb_include );

register_activation_hook( __FILE__, 'directoryweb_activate' );
register_deactivation_hook( __FILE__, 'directoryweb_deactivate' );

add_action( 'init', 'directoryweb_register_listing_type' );

/** Plugins → DirectoryWeb → Settings. */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=directoryweb-settings' ) ) . '">' . esc_html__( 'Settings', 'directoryweb' ) . '</a>' );
		return $links;
	}
);
