<?php
/**
 * Plugin Name: Animal Writes Core
 * Description: The Animal Writes site: content types and fields, the Directory with its map and places, campaigns and letters, forms and people (Fluent Forms, FluentCRM), card pictures, and the jobs that keep it running (backups, site check, scheduled tasks, deploy purge, CDN cache). Its screens are in the Kits menu (Home, Settings, Theme Options, Help). Theme-independent.
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
require_once AW_CORE_DIR . 'includes/admin-table.php';
require_once AW_CORE_DIR . 'includes/admin-home.php';
require_once AW_CORE_DIR . 'includes/help-tab.php';
require_once AW_CORE_DIR . 'includes/keys.php'; // Kits → Keys: service keys in WP admin (owner, 31 Oct 2026)
require_once AW_CORE_DIR . 'includes/edit-screens.php'; // Kits → Edit screens: every edit-screen box, its fields and where they are used (owner, 1 Nov 2026)
require_once AW_CORE_DIR . 'includes/admin-tidy.php';
require_once AW_CORE_DIR . 'includes/cdn-cache.php';
require_once AW_CORE_DIR . 'includes/deploy-purge.php';
require_once AW_CORE_DIR . 'includes/deploy-status.php'; // /aw/v1/deploy-status: the deploy gate waits while a campaign is sending (3 Nov 2026)
require_once AW_CORE_DIR . 'includes/keep-modified.php'; // technical saves keep the modified date (1 Nov 2026)
require_once AW_CORE_DIR . 'includes/content-types.php';
require_once AW_CORE_DIR . 'includes/category-moves.php';
require_once AW_CORE_DIR . 'includes/tags.php';
require_once AW_CORE_DIR . 'includes/taxonomy-rename.php'; // aw_campaign_link / aw_media_group → kits_ names, renamed only by a click on Tools → Taxonomy rename (owner, 18 Nov 2026)
require_once AW_CORE_DIR . 'includes/campaign-links.php';
require_once AW_CORE_DIR . 'includes/meta.php';
require_once AW_CORE_DIR . 'includes/scf.php';
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
require_once AW_CORE_DIR . 'includes/issuers.php';
require_once AW_CORE_DIR . 'includes/summary-display.php'; // an item's own page shows its whole summary (the rules are SourceWeb's)
require_once AW_CORE_DIR . 'includes/news-article.php'; // News article: read-more fold and the ad-slot hook
require_once AW_CORE_DIR . 'includes/bindings.php';
require_once AW_CORE_DIR . 'includes/also-reported.php';
require_once AW_CORE_DIR . 'includes/category-images.php';
require_once AW_CORE_DIR . 'includes/cron.php';
require_once AW_CORE_DIR . 'includes/display.php';
require_once AW_CORE_DIR . 'includes/media.php';
require_once AW_CORE_DIR . 'includes/media-autofill.php'; // New image uploads: fill empty name, alt, caption, description and image context
require_once AW_CORE_DIR . 'includes/media-columns.php'; // Media Library columns: slug, also used in, SEO score, optimisation; no Author (owner, 31 Oct 2026)
require_once AW_CORE_DIR . 'includes/media-groups.php'; // Media group label for every attachment: core taxonomy, column, REST
require_once AW_CORE_DIR . 'includes/media-webp.php'; // Resized copies of JPEG and PNG uploads as WebP; Media → Make WebP converts existing images in batches with Undo
require_once AW_CORE_DIR . 'includes/media-merge.php'; // Media → Duplicate files: merge duplicate copies with Undo
require_once AW_CORE_DIR . 'includes/media-reuse.php'; // Media → Reuse pictures: recycle Media Library pictures as page covers, one at a time, with Undo
require_once AW_CORE_DIR . 'includes/campaigns.php';
require_once AW_CORE_DIR . 'includes/campaign-tools.php';
require_once AW_CORE_DIR . 'includes/campaign-short.php'; // /lab, /diwali...: a campaign's short link, 301 to its page
require_once AW_CORE_DIR . 'includes/campaign-letters.php';
require_once AW_CORE_DIR . 'includes/campaign-record.php';
require_once AW_CORE_DIR . 'includes/sections.php';
require_once AW_CORE_DIR . 'includes/hubs.php';
require_once AW_CORE_DIR . 'includes/hub-blocks.php';
require_once AW_CORE_DIR . 'includes/term-page.php';
require_once AW_CORE_DIR . 'includes/faq-data.php'; // the Library item fields (the FAQ questions are RankWeb's faq-data.php since 18 Nov 2026)
require_once AW_CORE_DIR . 'includes/sponsored-blocks.php';
require_once AW_CORE_DIR . 'includes/site-check.php';
require_once AW_CORE_DIR . 'includes/backups.php';
require_once AW_CORE_DIR . 'includes/staging.php';
require_once AW_CORE_DIR . 'includes/systems.php';
require_once AW_CORE_DIR . 'includes/retention.php';
require_once AW_CORE_DIR . 'includes/model.php';
require_once AW_CORE_DIR . 'includes/helpers-public.php'; // [aw_helpers] on the Volunteer page: approved helpers (owner, 13 Nov 2026)
require_once AW_CORE_DIR . 'includes/breadcrumbs.php'; // fallback: RankWeb's breadcrumb renderer when RankWeb is not active
require_once AW_CORE_DIR . 'includes/item-head.php';
require_once AW_CORE_DIR . 'includes/research-citation.php';
require_once AW_CORE_DIR . 'includes/pages.php';
require_once AW_CORE_DIR . 'includes/not-found.php';
require_once AW_CORE_DIR . 'includes/press.php';
require_once AW_CORE_DIR . 'includes/social-posts.php'; // Social posts (aw_social, under News): off by default, Kits → Modules (owner, 2 Nov 2026)
require_once AW_CORE_DIR . 'includes/site-options.php'; // Kits → Modules → Forms and sample listings (12 Nov 2026)
require_once AW_CORE_DIR . 'includes/edit-fields.php'; // edit fields for values that were WP-CLI only (12 Nov 2026)
require_once AW_CORE_DIR . 'includes/block-states.php';
require_once AW_CORE_DIR . 'includes/related.php';
require_once AW_CORE_DIR . 'includes/law-links.php';
require_once AW_CORE_DIR . 'includes/home.php'; // Home: sections in the theme template, hero, FAQ section
require_once AW_CORE_DIR . 'includes/near-opps.php';
require_once AW_CORE_DIR . 'includes/entity-links.php';
require_once AW_CORE_DIR . 'includes/thumbs.php';
require_once AW_CORE_DIR . 'includes/cc0-images.php';
require_once AW_CORE_DIR . 'includes/admin-ia.php';
require_once AW_CORE_DIR . 'includes/team-dates.php';
require_once AW_CORE_DIR . 'includes/dashboard-activity.php';
require_once AW_CORE_DIR . 'includes/cron-health.php';
require_once AW_CORE_DIR . 'includes/error-count.php';
require_once AW_CORE_DIR . 'includes/error-pages.php';
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
		aw_register_content_types();
		if ( function_exists( 'directoryweb_register_listing_type' ) ) {
			directoryweb_register_listing_type();
		}
		if ( function_exists( 'aw_directory_rewrites' ) ) {
			aw_directory_rewrites();
		}
		aw_register_meta();
		if ( function_exists( 'directoryweb_caps_grant' ) ) {
			directoryweb_caps_grant();
		}
		flush_rewrite_rules();
		aw_cron_schedule();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		aw_cron_unschedule();
		flush_rewrite_rules();
	}
);
