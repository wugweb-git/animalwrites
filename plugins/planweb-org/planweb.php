<?php
/**
 * PlanWeb
 *
 * @package           planweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PlanWeb
 * Description:       A private editorial calendar for WordPress teams: plan content, social, email and campaign entries, with a read-only iCalendar feed. It posts and sends nothing.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       planweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Standalone plugin: activating switches PlanWeb on, deactivating switches it off.
register_activation_hook( __FILE__, 'planweb_activate' );
register_deactivation_hook( __FILE__, 'planweb_deactivate' );

if ( defined( 'PLANWEB_VERSION' ) ) {
	return; // Loaded already.
}

define( 'PLANWEB_VERSION', '0.2.0' );
define( 'PLANWEB_FILE', __FILE__ );
define( 'PLANWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLANWEB_URL', plugin_dir_url( __FILE__ ) );

require_once PLANWEB_DIR . 'includes/model.php';
require_once PLANWEB_DIR . 'includes/ui-model.php';
require_once PLANWEB_DIR . 'includes/switch.php';
require_once PLANWEB_DIR . 'includes/store.php';
require_once PLANWEB_DIR . 'includes/import.php';
require_once PLANWEB_DIR . 'includes/admin-table.php';
require_once PLANWEB_DIR . 'includes/admin.php';
require_once PLANWEB_DIR . 'includes/help-tab.php';
require_once PLANWEB_DIR . 'includes/dashboard.php';
require_once PLANWEB_DIR . 'includes/planweb-dashboard.php';
require_once PLANWEB_DIR . 'includes/planweb-rest.php';
require_once PLANWEB_DIR . 'includes/site-schedule.php';
require_once PLANWEB_DIR . 'includes/feed-model.php';
require_once PLANWEB_DIR . 'includes/feed.php';
require_once PLANWEB_DIR . 'includes/migrate.php';
require_once PLANWEB_DIR . 'includes/planweb-shell.php';
require_once PLANWEB_DIR . 'includes/planweb-ui.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PLANWEB_DIR . 'includes/class-planweb-cli.php';
}
