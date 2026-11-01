<?php
/**
 * Plugin Name:       AW SEO Plus
 * Plugin URI:        https://animalwrites.in/
 * Description:       What Rank Math (free) cannot do for animalwrites.in: titles, descriptions and canonicals of list pages (hubs, Directory places, filtered lists), the site's own JSON-LD nodes in Rank Math's graph, the Directory sitemap, dynamic noindex rules, share images, URL clean-up redirects, the focus-keyword API, llms.txt ordering and Directory section, the FAQ system (an FAQ box on every public post type and taxonomy, automatic questions filled in where the site provides them) and Search Insights (keywords, Search Console by day through Site Kit, priorities; read-only), under its own AW SEO Plus menu (Overview, FAQ, Search Insights, Settings: every module's rules, and an on/off switch for each feature module). Rank Math holds everything else. Does nothing unless Rank Math is active.
 * Version:           1.9.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       aw-seo-extras
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AW_SEO_EXTRAS_DIR', plugin_dir_path( __FILE__ ) );

/** Whether Rank Math is active (it loads after this plugin, so this is asked on plugins_loaded). */
function aw_seo_extras_rank_math() {
	return defined( 'RANK_MATH_VERSION' ) && class_exists( '\RankMath\Helper' );
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! aw_seo_extras_rank_math() ) {
			add_action(
				'admin_notices',
				function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'AW SEO Plus does nothing until Rank Math SEO is active.', 'aw-seo-extras' ) . '</p></div>';
					}
				}
			);
			return;
		}
		require_once AW_SEO_EXTRAS_DIR . 'includes/settings.php'; // first: the modules, their settings and switches
		$off = aw_seo_files_off();
		foreach ( array( 'helpers', 'overview', 'faq', 'programmatic', 'titles', 'canonical', 'filtered-views', 'robots', 'schema', 'rank-math-schema', 'opengraph', 'sitemap', 'redirects', 'breadcrumbs', 'focus-keywords', 'health', 'search-insights', 'llms', 'analysis' ) as $file ) {
			if ( ! in_array( $file, $off, true ) ) { // a module switched off in AW SEO Plus → Settings is not loaded
				require_once AW_SEO_EXTRAS_DIR . 'includes/' . $file . '.php';
			}
		}
	},
	5
);

// Search Insights keeps its tables and data when the plugin is switched off (owner, 24 Oct 2026); only its cron stops.
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( function_exists( 'aw_si_cron_hooks' ) ? aw_si_cron_hooks() : array( 'aw_si_daily', 'aw_si_rollup' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		// The SEO jobs too (plugin audit 23): titles.php, health.php.
		foreach ( array( 'aw_seo_fill_daily', 'aw_seo_endpoints_watchdog' ) as $hook ) {
			wp_clear_scheduled_hook( $hook ); // repeating (no-argument) events only, as Core
		}
	}
);
