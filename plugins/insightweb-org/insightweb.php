<?php
/**
 * InsightWeb
 *
 * Per-keyword insights from Google Search Console and GA4: each keyword's roles and owning pages, a priority, a status,
 * one suggested next action, rising terms and quick wins. It makes suggestions only and never changes the site's SEO
 * settings, content or keyphrases.
 *
 * InsightWeb is a companion to Site Kit by Google: it reads Search Console and GA4 through the Site Kit plugin and
 * stores no Google OAuth client or tokens of its own.
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
define( 'INSIGHTWEB_REST', 'insightweb/v1' ); // REST namespace for the admin Overview chart component.

require_once INSIGHTWEB_DIR . 'includes/class-wugweb-external-services.php'; // Shared suite registry (identical copy per plugin).
require_once INSIGHTWEB_DIR . 'includes/services.php';
require_once INSIGHTWEB_DIR . 'includes/keyword-table.php';
require_once INSIGHTWEB_DIR . 'includes/schema.php';
require_once INSIGHTWEB_DIR . 'includes/compute.php';
require_once INSIGHTWEB_DIR . 'includes/recompute.php';
require_once INSIGHTWEB_DIR . 'includes/query.php';
require_once INSIGHTWEB_DIR . 'includes/overview.php';
require_once INSIGHTWEB_DIR . 'includes/admin.php';
require_once INSIGHTWEB_DIR . 'includes/rest.php';

// The list tables pull in WordPress's WP_List_Table; load them only in the admin.
if ( is_admin() ) {
	require_once INSIGHTWEB_DIR . 'includes/class-insightweb-keywords-table.php';
	require_once INSIGHTWEB_DIR . 'includes/class-insightweb-rising-table.php';
	require_once INSIGHTWEB_DIR . 'includes/class-insightweb-links-table.php';
}
