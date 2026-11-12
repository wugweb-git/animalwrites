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
 * Description:       The team's calendar in WordPress: dates and content, social, email and campaign plans. Nothing is posted or sent from it. Needs Kits Core.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wugweb.com/plugins/planweb/
 * Text Domain:       planweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Activating PlanWeb switches it on; deactivating switches it off. The hook names are fixed, not built from __FILE__:
// Animal Writes Core loads this file first, before WordPress maps a symlinked plugin folder, so __FILE__ can give a
// name WordPress never fires (the Deploy check's WordPress links plugins in this way).
add_action( 'activate_planweb/planweb.php', 'planweb_activate' );
add_action( 'deactivate_planweb/planweb.php', 'planweb_deactivate' );

if ( defined( 'PLANWEB_VERSION' ) ) {
	return; // Loaded already (active on its own and also loaded by Animal Writes Core).
}

define( 'PLANWEB_VERSION', '0.1.0' );
define( 'PLANWEB_FILE', __FILE__ );
define( 'PLANWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLANWEB_URL', plugin_dir_url( __FILE__ ) );

require_once PLANWEB_DIR . 'includes/model.php';
require_once PLANWEB_DIR . 'includes/ui-model.php';
require_once PLANWEB_DIR . 'includes/switch.php';
require_once PLANWEB_DIR . 'includes/store.php';
require_once PLANWEB_DIR . 'includes/import.php';
require_once PLANWEB_DIR . 'includes/admin.php';
require_once PLANWEB_DIR . 'includes/help-tab.php';
require_once PLANWEB_DIR . 'includes/plugin-info.php';
require_once PLANWEB_DIR . 'includes/dashboard.php';
require_once PLANWEB_DIR . 'includes/kits-hub.php';
require_once PLANWEB_DIR . 'includes/planweb-dashboard.php';
require_once PLANWEB_DIR . 'includes/planweb-rest.php';
require_once PLANWEB_DIR . 'includes/planweb-ui.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PLANWEB_DIR . 'includes/class-planweb-cli.php';
}
