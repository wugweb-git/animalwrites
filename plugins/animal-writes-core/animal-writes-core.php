<?php
/**
 * Plugin Name: Animal Writes Core
 * Description: Content types, directory and ingest endpoint for Animal Writes 2.0. Theme-independent.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Wugweb
 * Author URI: https://wugweb.com
 * License: GPL-2.0-or-later
 * Text Domain: animal-writes-core
 *
 * @package animal-writes-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AW_CORE_VERSION', '0.1.0' );
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
require_once AW_CORE_DIR . 'includes/kits-config.php';
require_once AW_CORE_DIR . 'includes/cdn-cache.php';
require_once AW_CORE_DIR . 'includes/deploy-purge.php';
require_once AW_CORE_DIR . 'includes/content-types.php';
require_once AW_CORE_DIR . 'includes/category-moves.php';
require_once AW_CORE_DIR . 'includes/tags.php';
require_once AW_CORE_DIR . 'includes/campaign-links.php';
require_once AW_CORE_DIR . 'includes/meta.php';
require_once AW_CORE_DIR . 'includes/scf.php';
require_once AW_CORE_DIR . 'includes/places.php';
require_once AW_CORE_DIR . 'includes/issuers.php';
require_once AW_CORE_DIR . 'includes/sources-config.php';
require_once AW_CORE_DIR . 'includes/summary-rules.php';
require_once AW_CORE_DIR . 'includes/places-admin.php';
require_once AW_CORE_DIR . 'includes/directory.php';
require_once AW_CORE_DIR . 'includes/data-sources.php';
require_once AW_CORE_DIR . 'includes/bindings.php';
require_once AW_CORE_DIR . 'includes/also-reported.php';
require_once AW_CORE_DIR . 'includes/category-images.php';
require_once AW_CORE_DIR . 'includes/cron.php';
require_once AW_CORE_DIR . 'includes/display.php';
require_once AW_CORE_DIR . 'includes/media.php';
require_once AW_CORE_DIR . 'includes/volunteers-db.php';
require_once AW_CORE_DIR . 'includes/volunteers.php';
require_once AW_CORE_DIR . 'includes/campaigns.php';
require_once AW_CORE_DIR . 'includes/campaign-tools.php';
require_once AW_CORE_DIR . 'includes/campaign-letters.php';
require_once AW_CORE_DIR . 'includes/campaign-record.php';
require_once AW_CORE_DIR . 'includes/sections.php';
require_once AW_CORE_DIR . 'includes/hubs.php';
require_once AW_CORE_DIR . 'includes/hub-blocks.php';
require_once AW_CORE_DIR . 'includes/term-page.php';
require_once AW_CORE_DIR . 'includes/sponsored-blocks.php';
require_once AW_CORE_DIR . 'includes/form-blocks.php';
require_once AW_CORE_DIR . 'includes/site-check.php';
require_once AW_CORE_DIR . 'includes/mailweb-aw.php';
require_once AW_CORE_DIR . 'includes/geocode.php';
require_once AW_CORE_DIR . 'includes/directory-map.php';
require_once AW_CORE_DIR . 'includes/backups.php';
require_once AW_CORE_DIR . 'includes/staging.php';
require_once AW_CORE_DIR . 'includes/claims.php';
require_once AW_CORE_DIR . 'includes/unverified.php';
require_once AW_CORE_DIR . 'includes/systems.php';
require_once AW_CORE_DIR . 'includes/reports.php';
require_once AW_CORE_DIR . 'includes/retention.php';
require_once AW_CORE_DIR . 'includes/newsletter.php';
require_once AW_CORE_DIR . 'includes/model.php';
require_once AW_CORE_DIR . 'includes/featured.php';
require_once AW_CORE_DIR . 'includes/org-people.php';
require_once AW_CORE_DIR . 'includes/breadcrumbs.php';
require_once AW_CORE_DIR . 'includes/item-head.php';
require_once AW_CORE_DIR . 'includes/research-citation.php';
require_once AW_CORE_DIR . 'includes/listing-template.php';
require_once AW_CORE_DIR . 'includes/pages.php';
require_once AW_CORE_DIR . 'includes/press.php';
require_once AW_CORE_DIR . 'includes/block-states.php';
require_once AW_CORE_DIR . 'includes/listing-profile.php';
require_once AW_CORE_DIR . 'includes/map-picker.php';
require_once AW_CORE_DIR . 'includes/privacy-requests.php';
require_once AW_CORE_DIR . 'includes/local-cards.php';
require_once AW_CORE_DIR . 'includes/district-helplines.php';
require_once AW_CORE_DIR . 'includes/related.php';
require_once AW_CORE_DIR . 'includes/find-help.php';
require_once AW_CORE_DIR . 'includes/near-opps.php';
require_once AW_CORE_DIR . 'includes/entity-links.php';
require_once AW_CORE_DIR . 'includes/thumbs.php';
require_once AW_CORE_DIR . 'includes/cc0-images.php';
require_once AW_CORE_DIR . 'includes/newsletter-optin.php';
require_once AW_CORE_DIR . 'includes/newsletter-fluentforms.php';
require_once AW_CORE_DIR . 'includes/forms-fluentforms.php';
require_once AW_CORE_DIR . 'includes/campaign-form.php'; // "Sign it" as the Fluent Forms Campaign form; the raw copy of every form send
require_once AW_CORE_DIR . 'includes/fluentcrm.php';
require_once AW_CORE_DIR . 'includes/admin-ia.php';
require_once AW_CORE_DIR . 'includes/team-dates.php';
require_once AW_CORE_DIR . 'includes/cron-health.php';

// AW Sourcing (feeds, keywords, publishers, worker endpoints, AI keys, enrichment, review). Loaded from the sibling
// plugin folder when it is not active on its own; after Core's includes, which it uses.
if ( ! defined( 'AW_SOURCING_LOADED' ) && file_exists( dirname( AW_CORE_DIR ) . '/aw-sourcing/aw-sourcing.php' ) ) {
	require_once dirname( AW_CORE_DIR ) . '/aw-sourcing/aw-sourcing.php';
}

register_activation_hook(
	__FILE__,
	function () {
		aw_register_content_types();
		aw_directory_rewrites();
		aw_register_meta();
		aw_backfill_featured_flag();
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
