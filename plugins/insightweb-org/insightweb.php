<?php
/**
 * InsightWeb
 *
 * The public WordPress.org edition of InsightWeb: a separate, standalone codebase extracted from the Animal Writes
 * site plugin (plugins/insightweb), which is untouched. This is the I1 skeleton — it activates on its own next to
 * Site Kit by Google with an empty tab set; the insights engine ports in later phases (docs/insightweb-plan.md,
 * "WordPress.org edition"). One prefix: insightweb_ / InsightWeb_ / INSIGHTWEB_. No `aw_`/`kits_`.
 *
 * Credentials: model (A), companion to Site Kit — InsightWeb reads Search Console and GA4 only through the Site Kit
 * by Google plugin and stores no Google OAuth client or tokens of its own.
 *
 * @package           InsightWeb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       InsightWeb
 * Description:       Per-keyword insights from Search Console and GA4 via Site Kit: roles, owning pages, a next action, rising terms and quick wins.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       insightweb
 * Domain Path:       /languages
 * Requires Plugins:  google-site-kit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHTWEB_VERSION', '0.1.0' ); // Equal to the header's Version.
define( 'INSIGHTWEB_FILE', __FILE__ );
define( 'INSIGHTWEB_DIR', plugin_dir_path( __FILE__ ) );

require_once INSIGHTWEB_DIR . 'includes/keyword-table.php';
require_once INSIGHTWEB_DIR . 'includes/schema.php';
require_once INSIGHTWEB_DIR . 'includes/compute.php';
require_once INSIGHTWEB_DIR . 'includes/recompute.php';
require_once INSIGHTWEB_DIR . 'includes/admin.php';
