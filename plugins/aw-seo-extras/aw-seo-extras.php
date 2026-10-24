<?php
/**
 * Plugin Name:       Animal Writes – SEO extras for Rank Math
 * Plugin URI:        https://animalwrites.in/
 * Description:       What Rank Math (free) cannot do for animalwrites.in: titles, descriptions and canonicals of list pages (hubs, Directory places, filtered lists), the site's own JSON-LD nodes in Rank Math's graph, the Directory sitemap, dynamic noindex rules, share images, URL clean-up redirects, the focus-keyword API, llms.txt ordering and Directory section, and Tools → Search Insights (keywords, Search Console by day through Site Kit, priorities; read-only). Rank Math holds everything else. Does nothing unless Rank Math is active.
 * Version:           1.4.0
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
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'Animal Writes – SEO extras for Rank Math does nothing until Rank Math SEO is active.', 'aw-seo-extras' ) . '</p></div>';
					}
				}
			);
			return;
		}
		foreach ( array( 'helpers', 'faq', 'programmatic', 'titles', 'canonical', 'filtered-views', 'robots', 'schema', 'rank-math-schema', 'opengraph', 'sitemap', 'redirects', 'breadcrumbs', 'focus-keywords', 'health', 'search-demand', 'search-insights', 'llms', 'analysis' ) as $file ) {
			require_once AW_SEO_EXTRAS_DIR . 'includes/' . $file . '.php';
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
	}
);
