<?php
/**
 * Plugin Name: SourceWeb
 * Plugin URI: https://wordpress.org/plugins/sourceweb/
 * Description: Decide what news and content enters your site and review it: source hosts with an allow/neutral/block status, keyword relevance (include/animal/context/exclude), and a review gate over the post types you choose.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Wugweb
 * Author URI: https://wugweb.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sourceweb
 * Domain Path: /languages
 *
 * Standalone, self-contained plugin: one `sourceweb_` prefix, its own option store, its own Settings-API admin,
 * no companion-plugin requirement and no self-hosted updater. It operates only on the public post types the
 * administrator chooses — never on hardcoded types.
 *
 * @package   sourceweb
 * @author    Wugweb <admin@wugweb.com>
 * @copyright 2026 Wugweb
 * @license   GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @wordpress-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOURCEWEB_VERSION', '0.1.0' );
define( 'SOURCEWEB_FILE', __FILE__ );
define( 'SOURCEWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOURCEWEB_URL', plugin_dir_url( __FILE__ ) );
define( 'SOURCEWEB_CAP', 'manage_sourceweb' );

// The curation engine is pure PHP (no WordPress), so it is unit-testable without a WordPress install.
require_once SOURCEWEB_DIR . 'includes/matcher.php';
require_once SOURCEWEB_DIR . 'includes/relevance.php';
require_once SOURCEWEB_DIR . 'includes/sources.php';
require_once SOURCEWEB_DIR . 'includes/external-services.php';
require_once SOURCEWEB_DIR . 'includes/parse.php';
require_once SOURCEWEB_DIR . 'includes/rules.php';
require_once SOURCEWEB_DIR . 'includes/same-story.php';
require_once SOURCEWEB_DIR . 'includes/stats.php';
require_once SOURCEWEB_DIR . 'includes/queue.php';
require_once SOURCEWEB_DIR . 'includes/feeds.php';
// The WordPress layer: settings, admin screens, REST and the capability.
require_once SOURCEWEB_DIR . 'includes/settings.php';
require_once SOURCEWEB_DIR . 'includes/minichart.php';
require_once SOURCEWEB_DIR . 'includes/admin.php';
require_once SOURCEWEB_DIR . 'includes/external-services-admin.php';
require_once SOURCEWEB_DIR . 'includes/queue-admin.php';
require_once SOURCEWEB_DIR . 'includes/rest.php';

/**
 * On activation: grant the plugin's capability to administrators and schedule the twice-daily ingest.
 */
function sourceweb_activate() {
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( SOURCEWEB_CAP ) ) {
		$role->add_cap( SOURCEWEB_CAP );
	}
	if ( ! wp_next_scheduled( 'sourceweb_ingest_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'sourceweb_ingest_event' );
	}
}
register_activation_hook( __FILE__, 'sourceweb_activate' );

/**
 * On deactivation: clear the scheduled ingest (data and settings are left intact).
 */
function sourceweb_deactivate() {
	wp_clear_scheduled_hook( 'sourceweb_ingest_event' );
}
register_deactivation_hook( __FILE__, 'sourceweb_deactivate' );

/**
 * Load translations.
 */
function sourceweb_load_textdomain() {
	load_plugin_textdomain( 'sourceweb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'sourceweb_load_textdomain' );
