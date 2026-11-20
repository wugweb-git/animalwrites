<?php
/**
 * RankWeb
 *
 * @package           rankweb
 * @author            Wugweb
 * @copyright         2026 Wugweb
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       RankWeb
 * Plugin URI:        https://animalwrites.in/
 * Description:       SEO extras that run alongside Rank Math: FAQs, canonicals, breadcrumbs, internal links, schema, video and bulk tools.
 * Version:           2.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.0
 * Requires Plugins:  seo-by-rank-math
 * Author:            Wugweb
 * Author URI:        https://wugweb.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wugweb.com/plugins/rankweb/
 * Text Domain:       rankweb
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RANKWEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'RANKWEB_FILE', __FILE__ );
if ( ! defined( 'AW_SEO_EXTRAS_DIR' ) ) {
	define( 'AW_SEO_EXTRAS_DIR', RANKWEB_DIR );
}
if ( ! defined( 'AW_SEO_EXTRAS_FILE' ) ) {
	define( 'AW_SEO_EXTRAS_FILE', RANKWEB_FILE );
}

require_once AW_SEO_EXTRAS_DIR . 'includes/text.php'; // rankweb_plain_text(), used by every file.
require_once AW_SEO_EXTRAS_DIR . 'includes/rankweb-caps.php'; // RankWeb's own capability, manage_rankweb (granted from manage_categories).
require_once AW_SEO_EXTRAS_DIR . 'includes/plugin-info.php'; // icon, banner and View details on the Plugins screen, with or without Rank Math.
require_once AW_SEO_EXTRAS_DIR . 'includes/kits-hub.php'; // a tab and a status row in the Kits admin, when Kits Core is loaded.
require_once AW_SEO_EXTRAS_DIR . 'includes/breadcrumbs-render.php'; // the visible trail above the page title, with or without Rank Math and the Breadcrumbs module.
require_once AW_SEO_EXTRAS_DIR . 'includes/media-autofill.php'; // new image uploads: empty name, alt, caption, description, image context and keyphrase filled, with or without Rank Math.
require_once AW_SEO_EXTRAS_DIR . 'includes/media-columns.php'; // Media Library columns: slug, SEO fields, where each file is used, optimisation; with or without Rank Math.
require_once AW_SEO_EXTRAS_DIR . 'includes/reprint.php'; // Reprint, Content Syndication & Canonical Attribution engine (Reprint benchmark).
require_once AW_SEO_EXTRAS_DIR . 'includes/editor-sidebar.php'; // Gutenberg Block Editor Sidebar & Live 0-100 Score Engine (Rank Math/Yoast benchmark).

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
						wp_admin_notice( esc_html__( 'RankWeb does nothing until Rank Math SEO is active.', 'aw-seo-extras' ), array( 'type' => 'warning' ) );
					}
				}
			);
			return;
		}
		require_once AW_SEO_EXTRAS_DIR . 'includes/settings.php'; // first: the modules, their settings and switches
		$off = aw_seo_files_off();
		foreach ( array( 'helpers', 'overview', 'faq', 'faq-data', 'programmatic', 'titles', 'canonical', 'filtered-views', 'robots', 'schema', 'rank-math-schema', 'opengraph', 'sitemap', 'redirects', 'breadcrumbs', 'focus-keywords', 'health', 'llms', 'markdown', 'analysis', 'content-advice', 'internal-links', 'link-audit', 'link-anchors', 'silos', 'video', 'schema-custom', 'bulk-edit', 'bulk-csv', 'media-seo', 'redirect-tools', 'agent-tools', 'rankweb-dashboard', 'rankweb-rest', 'rankweb-ui', 'rankweb-modules', 'help-tab' ) as $file ) {
			if ( ! in_array( $file, $off, true ) ) { // a module switched off in RankWeb → Settings is not loaded
				require_once AW_SEO_EXTRAS_DIR . 'includes/' . $file . '.php';
			}
		}
		aw_seo_extras_insightweb_bridge();
		// A switched-off module's scheduled job is taken off (its file, which runs and reschedules it, is not loaded).
		// Checked in admin and cron requests only, so a page view never writes.
		$jobs = array(
			'health'         => 'aw_seo_endpoints_watchdog',
			'sitemap'        => 'aw_seo_sitemap_daily',
			'internal-links' => array( 'aw_link_index_build', 'aw_link_audit' ),
			'video'          => 'aw_video_sync',
		);
		$idle = array_intersect_key( $jobs, array_flip( $off ) );
		if ( $idle ) {
			add_action(
				'init',
				function () use ( $idle ) {
					if ( ! is_admin() && ! wp_doing_cron() ) {
						return;
					}
					foreach ( $idle as $hooks ) {
						foreach ( (array) $hooks as $hook ) {
							if ( wp_next_scheduled( $hook ) ) {
								wp_clear_scheduled_hook( $hook );
							}
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
		foreach ( array( 'aw_seo_fill_daily', 'aw_seo_endpoints_watchdog', 'aw_seo_sitemap_daily', 'rankweb_health_build', 'aw_link_index_build', 'aw_link_audit', 'aw_video_sync' ) as $hook ) {
			wp_clear_scheduled_hook( $hook ); // repeating (no-argument) events only, as Core
		}
		wp_clear_scheduled_hook( 'rankweb_health_build', array( 'now' ) ); // A Count now that has not run yet.
		wp_clear_scheduled_hook( 'aw_link_index_build', array( 'now' ) ); // A rebuild after a Settings save.
		wp_clear_scheduled_hook( 'aw_link_audit', array( 'now' ) ); // An audit asked for and not yet run.
		wp_clear_scheduled_hook( 'aw_link_audit', array( 'more' ) ); // The next slice of a running audit.
	}
);
