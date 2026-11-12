<?php
/**
 * Plugin Name:       RankWeb
 * Plugin URI:        https://animalwrites.in/
 * Description:       Everything Rank Math doesn't do or keeps for Premium, for Kits sites.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPL-2.0-or-later
 * Update URI:        https://wugweb.com/plugins/aw-seo-extras/
 * Text Domain:       aw-seo-extras
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AW_SEO_EXTRAS_DIR', plugin_dir_path( __FILE__ ) );

require_once AW_SEO_EXTRAS_DIR . 'includes/kits-hub.php'; // a tab and a status row in the Kits admin, when Kits Core is loaded.

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
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'RankWeb does nothing until Rank Math SEO is active.', 'aw-seo-extras' ) . '</p></div>';
					}
				}
			);
			return;
		}
		require_once AW_SEO_EXTRAS_DIR . 'includes/settings.php'; // first: the modules, their settings and switches
		$off = aw_seo_files_off();
		foreach ( array( 'helpers', 'overview', 'faq', 'programmatic', 'titles', 'canonical', 'filtered-views', 'robots', 'schema', 'rank-math-schema', 'opengraph', 'sitemap', 'redirects', 'breadcrumbs', 'focus-keywords', 'health', 'llms', 'markdown', 'analysis', 'content-advice', 'rankweb-dashboard', 'rankweb-rest', 'rankweb-ui' ) as $file ) {
			if ( ! in_array( $file, $off, true ) ) { // a module switched off in RankWeb → Settings is not loaded
				require_once AW_SEO_EXTRAS_DIR . 'includes/' . $file . '.php';
			}
		}
		aw_seo_extras_insightweb_bridge();
		// A switched-off module's scheduled job is taken off (its file, which runs and reschedules it, is not loaded).
		// Checked in admin and cron requests only, so a page view never writes.
		$jobs = array(
			'health'  => 'aw_seo_endpoints_watchdog',
			'sitemap' => 'aw_seo_sitemap_daily',
		);
		$idle = array_intersect_key( $jobs, array_flip( $off ) );
		if ( $idle ) {
			add_action(
				'init',
				function () use ( $idle ) {
					if ( ! is_admin() && ! wp_doing_cron() ) {
						return;
					}
					foreach ( $idle as $hook ) {
						if ( wp_next_scheduled( $hook ) ) {
							wp_clear_scheduled_hook( $hook );
						}
					}
				}
			);
		}
	},
	5
);

/**
 * Bridge while InsightWeb is not active yet (docs/insightweb-plan.md 3.2). Search Insights moved to its own plugin on
 * 11 Nov 2026; a new plugin is inactive until someone activates it, and in that gap its scheduled jobs would run with no
 * handler. So, while InsightWeb is not active and Search Insights was not switched off here, RankWeb loads InsightWeb's
 * bootstrap, which loads once whoever calls it first. Removed once InsightWeb is active on live and a daily run is OK.
 */
function aw_seo_extras_insightweb_bridge(): void {
	if ( defined( 'INSIGHTWEB_FILE' ) ) {
		return; // InsightWeb is active: it loads Search Insights itself.
	}
	// Never off while the theme runs (owner, 11 Nov 2026): an old saved off switch is not read.
	$file = WP_PLUGIN_DIR . '/insightweb/includes/bootstrap.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

// Search Insights' scheduled jobs are InsightWeb's now: switching RankWeb off never stops them.
register_deactivation_hook(
	__FILE__,
	function () {
		// The SEO jobs: titles.php, health.php, sitemap.php; the Dashboard counts: rankweb-dashboard.php.
		foreach ( array( 'aw_seo_fill_daily', 'aw_seo_endpoints_watchdog', 'aw_seo_sitemap_daily', 'rankweb_health_build' ) as $hook ) {
			wp_clear_scheduled_hook( $hook ); // repeating (no-argument) events only, as Core
		}
		wp_clear_scheduled_hook( 'rankweb_health_build', array( 'now' ) ); // A Count now that has not run yet.
	}
);
