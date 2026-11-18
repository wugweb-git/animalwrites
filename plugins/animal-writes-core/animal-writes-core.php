<?php
/**
 * Plugin Name: Animal Writes Core
 * Description: The Animal Writes site: content types and fields, the Directory with its map and places, campaigns and letters, forms and people (Fluent Forms, FluentCRM), card pictures, and the jobs that keep it running (site check, scheduled tasks, deploy purge, CDN cache). Its screens are in the Kits menu (Home, Settings, Theme Options, Help). Theme-independent.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Wugweb
 * Author URI: https://wugweb.com
 * License: GPL-2.0-or-later
 * Update URI: https://wugweb.com/plugins/animal-writes-core/
 * Text Domain: animal-writes-core
 * Domain Path: /languages
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AW_CORE_VERSION', '1.0.0' );
define( 'AW_CORE_DIR', plugin_dir_path( __FILE__ ) );

// No theme or plugin file editor in WP Admin: all code changes go through GitHub (S0.2).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/** Days after which a verified listing is flagged for re-verification. */
if ( ! defined( 'AW_REVERIFY_DAYS' ) ) {
	define( 'AW_REVERIFY_DAYS', 180 );
}

// Cron: WordPress runs its scheduled tasks on page requests that reach PHP. The "WordPress cron" workflow in
// animal-writes-workers also calls wp-cron.php as a backup; GitHub throttles that schedule, so it can't be the only
// trigger (on 2026-10-07 it fired once in 6 hours).

// Kits Core (shared site features). Loaded from the sibling plugin folder when it is not active on its own.
if ( ! defined( 'KITS_CORE_LOADED' ) && file_exists( dirname( AW_CORE_DIR ) . '/kits-core/kits-core.php' ) ) {
	require_once dirname( AW_CORE_DIR ) . '/kits-core/kits-core.php';
}
require_once AW_CORE_DIR . 'includes/plugin-info.php'; // icon, banner and View details on the Plugins screen
require_once AW_CORE_DIR . 'includes/kits-config.php';
require_once AW_CORE_DIR . 'includes/admin-config.php'; // the site's values for Kits Core's admin layer (menus, labels, Overview)
require_once AW_CORE_DIR . 'includes/admin-table.php';
require_once AW_CORE_DIR . 'includes/help-tab.php';
require_once AW_CORE_DIR . 'includes/keys.php'; // Kits → Keys: service keys in WP admin (owner, 31 Oct 2026)
require_once AW_CORE_DIR . 'includes/cdn-cache.php';
require_once AW_CORE_DIR . 'includes/deploy-purge.php';
require_once AW_CORE_DIR . 'includes/deploy-status.php'; // /aw/v1/deploy-status: the deploy gate waits while a campaign is sending (3 Nov 2026)
require_once AW_CORE_DIR . 'includes/keep-modified.php'; // technical saves keep the modified date (1 Nov 2026)
// The content types, hubs, blocks, tags, meta, SCF, bindings, related and the rest of the content layer load from the theme's site
// layer (themes/kits-by-wugweb/assets/site/content/, loaded by assets/site/site.php); the taxonomy rename is Kits Core's.
// DirectoryWeb (the Directory: listings, places, claims, map, Find help, and its admin). Loaded from the sibling plugin
// folder when it is not active on its own, here, where the Directory files were loaded before (the Directory is used by
// the files below). If the folder is missing, one notice says so; the Directory pieces that follow need it.
if ( ! defined( 'DIRECTORYWEB_LOADED' ) ) {
	if ( file_exists( dirname( AW_CORE_DIR ) . '/directoryweb/directoryweb.php' ) ) {
		require_once dirname( AW_CORE_DIR ) . '/directoryweb/directoryweb.php';
	} else {
		add_action(
			'admin_notices',
			function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'DirectoryWeb is missing: Animal Writes Core needs the directoryweb plugin folder next to it.', 'animal-writes-core' ) . '</p></div>';
				}
			}
		);
	}
}
require_once AW_CORE_DIR . 'includes/cron.php';
require_once AW_CORE_DIR . 'includes/media-compat.php'; // old aw_ names of the media functions that moved to Kits Core, RankWeb and SourceWeb (18 Nov 2026)
require_once AW_CORE_DIR . 'includes/site-check.php';
require_once AW_CORE_DIR . 'includes/staging.php';
require_once AW_CORE_DIR . 'includes/systems.php';
require_once AW_CORE_DIR . 'includes/retention.php';
require_once AW_CORE_DIR . 'includes/research-citation.php';
require_once AW_CORE_DIR . 'includes/not-found.php';
require_once AW_CORE_DIR . 'includes/uninstall-setting.php'; // Kits → Modules → Animal Writes Core data (the rest of Modules is Kits Core's)
require_once AW_CORE_DIR . 'includes/dashboard-activity.php';
require_once AW_CORE_DIR . 'includes/cron-health.php';
require_once AW_CORE_DIR . 'includes/error-count.php';
// Forms, FluentCRM and the newsletter moved out (18 Nov 2026): the form engine, form pages, Report a mistake, volunteers,
// the Campaign form and the Dashboard box to Kits Core (includes/forms/); the claim page to DirectoryWeb (claim-page.php);
// FluentCRM, the newsletter and Mail → Website forms to MailWeb. When MailWeb is not active, its three site files are
// loaded from the sibling folder, so the forms keep their FluentCRM steps as before.
add_action(
	'plugins_loaded',
	function () {
		if ( defined( 'MAILWEB_VERSION' ) ) {
			return;
		}
		foreach ( array( 'website-forms', 'fluentcrm', 'newsletter', 'fluentcrm-compat' ) as $f ) {
			$file = dirname( AW_CORE_DIR ) . '/mailweb/includes/' . $f . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	},
	0
);
require_once AW_CORE_DIR . 'includes/theme-parts.php'; // theme files, templates and patterns when Kits runs with no child theme
require_once AW_CORE_DIR . 'includes/theme-parts-screen.php'; // Kits → Theme parts: the child theme's parts, where they live now, where to change them.

/**
 * A sibling plugin Core loads (SourceWeb, InsightWeb, PlanWeb) is loaded from its real folder, so WordPress does not
 * know its link in the plugins directory and plugins_url() would build an address from the real path (a 404). Map the
 * real folder back to the link, as WordPress does for active plugins and as Kits Core does for itself. Without a link
 * in the plugins directory nothing changes.
 *
 * @param string $main The plugin's main file, relative to the plugins directory (e.g. 'planweb/planweb.php').
 */
function aw_core_map_sibling( string $main ): void {
	if ( ! defined( 'WP_PLUGIN_DIR' ) || ! function_exists( 'wp_register_plugin_realpath' ) ) {
		return;
	}
	$link = WP_PLUGIN_DIR . '/' . $main;
	$real = dirname( AW_CORE_DIR ) . '/' . $main;
	if ( file_exists( $link ) && file_exists( $real ) && realpath( $link ) === realpath( $real ) ) {
		wp_register_plugin_realpath( $link );
	}
}

/**
 * Create the plugins-directory link for a sibling that Core loads from the deploy folder, when it is missing, so its
 * own assets resolve and it lists on the Plugins screen. The deploy clones the repo into aw_deploy and the links in
 * wp-content/plugins were made by hand, so a new split (e.g. DirectoryWeb) would otherwise have no link and its assets
 * would 404. WordPress owns wp-content/plugins (plugin uploads write there), so a symlink from PHP works; if the host
 * blocks symlink() or the directory is not writable, nothing changes and Core still loads the plugin from the deploy
 * folder. Creating the link does not activate the plugin; Core keeps loading it with require_once.
 *
 * @param string $main The plugin's main file, relative to the plugins directory (e.g. 'directoryweb/directoryweb.php').
 */
function aw_core_ensure_sibling_link( string $main ): void {
	if ( ! defined( 'WP_PLUGIN_DIR' ) || ! function_exists( 'symlink' ) ) {
		return;
	}
	$slug = dirname( $main );
	$link = WP_PLUGIN_DIR . '/' . $slug;
	$real = dirname( AW_CORE_DIR ) . '/' . $slug;
	if ( file_exists( $link ) || ! is_dir( $real ) || ! is_writable( WP_PLUGIN_DIR ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- pre-flight capability check before an optional symlink; WP_Filesystem is not loaded this early and nothing is written here.
		return;
	}
	@symlink( $real, $link ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a host that blocks symlink() must not warn; Core still loads the plugin from the deploy folder.
}
foreach ( array( 'aw-sourcing/aw-sourcing.php', 'insightweb/insightweb.php', 'planweb/planweb.php', 'directoryweb/directoryweb.php' ) as $aw_sibling ) {
	aw_core_ensure_sibling_link( $aw_sibling );
	aw_core_map_sibling( $aw_sibling );
}

// AW Sourcing (feeds, keywords, publishers, worker endpoints, AI keys, enrichment, review). Loaded from the sibling
// plugin folder when it is not active on its own; after Core's includes, which it uses.
if ( ! defined( 'AW_SOURCING_LOADED' ) && file_exists( dirname( AW_CORE_DIR ) . '/aw-sourcing/aw-sourcing.php' ) ) {
	require_once dirname( AW_CORE_DIR ) . '/aw-sourcing/aw-sourcing.php';
}

// InsightWeb (Search Insights; owner, 11 Nov 2026: never off while the theme runs). Core loads its bootstrap whether or
// not the InsightWeb plugin is active, at the priority InsightWeb uses; the bootstrap loads once, whoever calls it first.
add_action(
	'plugins_loaded',
	function () {
		$file = dirname( AW_CORE_DIR ) . '/insightweb/includes/bootstrap.php';
		if ( PHP_VERSION_ID >= 80000 && ! defined( 'INSIGHTWEB_LOADED' ) && file_exists( $file ) ) { // InsightWeb needs PHP 8.0
			require_once $file;
		}
	},
	5
);

// PlanWeb (the team's calendar; owner, 11 Nov 2026). Loaded from the sibling plugin folder when it is not active on its
// own. It does nothing until it is switched on in Kits → Modules; until then Kits → Dates works as before. It needs
// PHP 8.0 (Core still runs on 7.4), so on older PHP it is not loaded and Dates stays.
if ( PHP_VERSION_ID >= 80000 && ! defined( 'PLANWEB_VERSION' ) && file_exists( dirname( AW_CORE_DIR ) . '/planweb/planweb.php' ) ) {
	require_once dirname( AW_CORE_DIR ) . '/planweb/planweb.php';
}

register_activation_hook(
	__FILE__,
	function () {
		// The content types, their meta and the daily schedule moved out of this plugin (the theme's site layer and Kits Core,
		// 19 Nov 2026). Each is called only when it is loaded: on a first activation the site layer may not be (it loads once
		// Kits Core is), and a missing function must not stop the activation.
		if ( function_exists( 'aw_register_content_types' ) ) {
			aw_register_content_types();
		}
		if ( function_exists( 'directoryweb_register_listing_type' ) ) {
			directoryweb_register_listing_type();
		}
		if ( function_exists( 'aw_directory_rewrites' ) ) {
			aw_directory_rewrites();
		}
		if ( function_exists( 'aw_register_meta' ) ) {
			aw_register_meta();
		}
		if ( function_exists( 'directoryweb_caps_grant' ) ) {
			directoryweb_caps_grant();
		}
		flush_rewrite_rules();
		if ( function_exists( 'aw_cron_schedule' ) ) {
			aw_cron_schedule();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		if ( function_exists( 'aw_cron_unschedule' ) ) {
			aw_cron_unschedule();
		}
		flush_rewrite_rules();
	}
);
