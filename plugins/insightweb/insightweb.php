<?php
/**
 * Plugin Name:       InsightWeb
 * Plugin URI:        https://animalwrites.in/
 * Description:       Search Console, GA4, Clarity, PageSpeed, Bing, keywords and rising terms for Kits sites.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * Update URI:        https://wugweb.com/plugins/insightweb/
 * Text Domain:       aw-seo-extras
 *
 * Search Insights, moved out of RankWeb on 11 Nov 2026 with no other change: the same screen, names, tables, options,
 * scheduled jobs and routes (docs/insightweb-plan.md). It needs Kits Core, Animal Writes Core and Rank Math, checked in
 * includes/bootstrap.php (not with a Requires Plugins header: on live Kits Core is loaded by Animal Writes Core).
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHTWEB_FILE', __FILE__ ); // RankWeb's bridge loads Search Insights only while this is not defined.
if ( ! defined( 'INSIGHTWEB_DIR' ) ) { // already set by the bootstrap on the request that activates InsightWeb
	define( 'INSIGHTWEB_DIR', plugin_dir_path( __FILE__ ) );
}

require_once INSIGHTWEB_DIR . 'includes/kits-hub.php'; // a row on Kits → Plugins and in System Status, when Kits Core is loaded.

// Priority 5, as Search Insights loaded inside RankWeb. The bootstrap loads once, whoever calls it first.
add_action(
	'plugins_loaded',
	function () {
		require_once INSIGHTWEB_DIR . 'includes/bootstrap.php';
	},
	5
);

// Tables and data stay when the plugin is switched off (owner, 24 Oct 2026); only its scheduled jobs stop, the same
// list RankWeb's deactivation cleared before the move.
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( function_exists( 'aw_si_cron_hooks' ) ? aw_si_cron_hooks() : array( 'aw_si_daily', 'aw_si_rollup' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
);
