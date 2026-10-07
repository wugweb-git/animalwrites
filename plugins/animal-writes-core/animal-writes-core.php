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

/** Days after which a verified listing is flagged for re-verification. */
if ( ! defined( 'AW_REVERIFY_DAYS' ) ) {
	define( 'AW_REVERIFY_DAYS', 180 );
}

// Real cron: WordPress's scheduled tasks are run every 15 minutes by the "WordPress cron" workflow in
// animal-writes-workers (calls wp-cron.php), so page views no longer trigger them.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

require_once AW_CORE_DIR . 'includes/content-types.php';
require_once AW_CORE_DIR . 'includes/meta.php';
require_once AW_CORE_DIR . 'includes/directory.php';
require_once AW_CORE_DIR . 'includes/bindings.php';
require_once AW_CORE_DIR . 'includes/ingest.php';
require_once AW_CORE_DIR . 'includes/cron.php';
require_once AW_CORE_DIR . 'includes/analytics.php';
require_once AW_CORE_DIR . 'includes/display.php';
require_once AW_CORE_DIR . 'includes/seo.php';
require_once AW_CORE_DIR . 'includes/media.php';
require_once AW_CORE_DIR . 'includes/share.php';
require_once AW_CORE_DIR . 'includes/forms.php';
require_once AW_CORE_DIR . 'includes/volunteers-db.php';
require_once AW_CORE_DIR . 'includes/volunteers.php';
require_once AW_CORE_DIR . 'includes/campaigns.php';
require_once AW_CORE_DIR . 'includes/visits.php';
require_once AW_CORE_DIR . 'includes/ads.php';
require_once AW_CORE_DIR . 'includes/testimonials.php';
require_once AW_CORE_DIR . 'includes/sections.php';
require_once AW_CORE_DIR . 'includes/claims.php';
require_once AW_CORE_DIR . 'includes/launch.php';
require_once AW_CORE_DIR . 'includes/systems.php';
require_once AW_CORE_DIR . 'includes/sourcing.php';
require_once AW_CORE_DIR . 'includes/attribution.php';
require_once AW_CORE_DIR . 'includes/reports.php';
require_once AW_CORE_DIR . 'includes/schema.php';

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
