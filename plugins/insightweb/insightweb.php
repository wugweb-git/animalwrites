<?php
/**
 * InsightWeb
 *
 * Search Insights, moved out of RankWeb on 11 Nov 2026 with no other change: the same screen, names, tables, options,
 * scheduled jobs and routes (docs/insightweb-plan.md). It needs Kits Core, Animal Writes Core and Rank Math, checked in
 * includes/bootstrap.php (not with a Requires Plugins header: on live Kits Core is loaded by Animal Writes Core).
 *
 * @package           insightweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       InsightWeb
 * Description:       Search Console, GA4, Clarity, PageSpeed, Bing, keywords and rising terms for Kits sites.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wugweb.com/plugins/insightweb/
 * Text Domain:       insightweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHTWEB_VERSION', '1.0.0' ); // equal to the header's Version.
define( 'INSIGHTWEB_FILE', __FILE__ ); // RankWeb's bridge loads Search Insights only while this is not defined.
if ( ! defined( 'INSIGHTWEB_DIR' ) ) { // already set by the bootstrap on the request that activates InsightWeb
	define( 'INSIGHTWEB_DIR', plugin_dir_path( __FILE__ ) );
}

require_once INSIGHTWEB_DIR . 'includes/kits-hub.php'; // a row in Kits System Status, when Kits Core is loaded.

// Priority 5, as Search Insights loaded inside RankWeb. The bootstrap loads once, whoever calls it first.
add_action(
	'plugins_loaded',
	function () {
		require_once INSIGHTWEB_DIR . 'includes/bootstrap.php';
	},
	5
);

// No deactivation hook: Search Insights is never off while the theme runs (owner, 11 Nov 2026). Animal Writes Core
// loads it when this plugin is not active, so its scheduled jobs stay booked.
