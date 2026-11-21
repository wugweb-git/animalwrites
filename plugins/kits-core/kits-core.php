<?php
/**
 * Plugin Name: Kits Core
 * Description: Shared layer for Kits by Wugweb sites: form engine with spam guard (ALTCHA), share bar, consent-mode analytics, attribution and Kits admin.
 * Version: 1.0.0
 * Requires at least: 7.1
 * Requires PHP: 8.0
 * Author: Wugweb
 * Author URI: https://wugweb.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://wugweb.com/plugins/kits-core/
 * Text Domain: kits-core
 * Domain Path: /languages
 *
 * @package kits-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// A site plugin may load Kits Core itself; never load twice.
if ( defined( 'KITS_CORE_LOADED' ) ) {
	return;
}
define( 'KITS_CORE_LOADED', '1.0.0' );
define( 'KITS_CORE_DIR', __DIR__ . '/' );

// When a site plugin loads Kits Core, WordPress does not know Kits Core's folder. If that folder is a symlink in
// the plugins directory, __DIR__ is the link's target, and plugins_url() (block.json assets, scripts) would build
// an address from the target's absolute path. Map the target back to the plugins directory, as WordPress does for
// active plugins; if Kits Core is active itself, this sets the same mapping again. Without a kits-core folder or
// link in the plugins directory, its files have no public address and nothing here changes.
if ( defined( 'WP_PLUGIN_DIR' ) && function_exists( 'wp_register_plugin_realpath' ) ) {
	$kits_core_link = WP_PLUGIN_DIR . '/' . basename( __DIR__ ) . '/kits-core.php';
	if ( file_exists( $kits_core_link ) && realpath( $kits_core_link ) === __FILE__ ) {
		wp_register_plugin_realpath( $kits_core_link );
	}
	unset( $kits_core_link );
}

require_once KITS_CORE_DIR . 'includes/kits-caps.php'; // Kits' own capability, manage_kits (granted from manage_options).
require_once KITS_CORE_DIR . 'includes/admin-table.php';
require_once KITS_CORE_DIR . 'includes/dashboard-cards.php';
require_once KITS_CORE_DIR . 'includes/admin-home.php';
require_once KITS_CORE_DIR . 'includes/plugin-info.php';
require_once KITS_CORE_DIR . 'includes/altcha.php';
require_once KITS_CORE_DIR . 'includes/forms.php';
require_once KITS_CORE_DIR . 'includes/form-kit.php';
require_once KITS_CORE_DIR . 'includes/private-dir.php'; // kits_private_dir(): the private upload folder for claim logos (moved from Animal Writes Core's campaigns.php)
// The site's forms on Fluent Forms: the custom fields, checks and look, the form and thank-you pages, Report a mistake,
// the volunteer form, the Campaign form with the raw copy of every send, and the Dashboard box (moved from Animal Writes
// Core, 18 Nov 2026). compat.php keeps the old function names working.
foreach ( array( 'fluentforms', 'form-pages', 'reports', 'volunteers', 'campaign-form', 'dashboard-forms', 'compat' ) as $kits_forms_file ) {
	require_once KITS_CORE_DIR . 'includes/forms/' . $kits_forms_file . '.php';
}
unset( $kits_forms_file );
require_once KITS_CORE_DIR . 'includes/primitives.php';
require_once KITS_CORE_DIR . 'includes/scf.php'; // SCF/ACF bridge (admin): Local JSON folders and the field group registry
require_once KITS_CORE_DIR . 'includes/bindings.php'; // Block Bindings source kits/post-field; sites register keys on kits_post_fields
require_once KITS_CORE_DIR . 'includes/presets.php'; // Content-type presets (kits-portfolio … kits-testimonial), each off until switched on in Kits → Settings
require_once KITS_CORE_DIR . 'includes/presets-scf.php'; // SCF field groups (Local JSON, acf-json/) for the switched-on presets
require_once KITS_CORE_DIR . 'includes/blocks.php';
require_once KITS_CORE_DIR . 'includes/demo.php';
require_once KITS_CORE_DIR . 'includes/system-screen.php';
require_once KITS_CORE_DIR . 'includes/landmarks.php';
require_once KITS_CORE_DIR . 'includes/sections.php';
require_once KITS_CORE_DIR . 'includes/share.php';
require_once KITS_CORE_DIR . 'includes/frontend-scripts.php'; // share, forms (ALTCHA) and analytics scripts, before the theme's kit.js
require_once KITS_CORE_DIR . 'includes/image-context.php';
require_once KITS_CORE_DIR . 'includes/taxonomy-rename.php'; // aw_campaign_link / aw_media_group → kits_ names (moved from Animal Writes Core, 19 Nov 2026): KITS_CAMPAIGN_LINK, KITS_MEDIA_GROUP, kits_taxonomy_current(), Tools → Taxonomy rename
require_once KITS_CORE_DIR . 'includes/media-groups.php'; // Media group label for every attachment: core taxonomy, column, REST (moved from Animal Writes Core, 18 Nov 2026)
require_once KITS_CORE_DIR . 'includes/category-images.php'; // category and tag photos (term meta aw_category_image) and the category banner
require_once KITS_CORE_DIR . 'includes/thumbs.php'; // card pictures never repeat: option aw_thumb_map, GET /aw/v1/thumb-dupes (the deploy gate)
require_once KITS_CORE_DIR . 'includes/card-images.php'; // card images: the item's own picture, the category photo, the placard fallback
require_once KITS_CORE_DIR . 'includes/media-webp.php'; // resized copies of JPEG and PNG uploads as WebP; Media → Make WebP with Undo
require_once KITS_CORE_DIR . 'includes/tools/media-merge.php'; // Kits → Tools: Media → Duplicate files (one-off clean-up, Undo, never deletes)
require_once KITS_CORE_DIR . 'includes/tools/media-reuse.php'; // Kits → Tools: Media → Reuse pictures (recycle, never delete)
require_once KITS_CORE_DIR . 'includes/slugs.php';
require_once KITS_CORE_DIR . 'includes/analytics.php';
require_once KITS_CORE_DIR . 'includes/performance.php';
require_once KITS_CORE_DIR . 'includes/joined-css.php'; // Performance: joined stylesheets (the Kits theme's core files, the component files per kind of page)
require_once KITS_CORE_DIR . 'includes/preview.php';
// The sourcing lists, source rows, publisher icons and the favicon run are SourceWeb's, and the keyword table is
// InsightWeb's (moved out on 18 Nov 2026 with the same names, option, tables, shortcode and scheduled event).
require_once KITS_CORE_DIR . 'includes/theme-updates.php';
require_once KITS_CORE_DIR . 'includes/page-options.php';
require_once KITS_CORE_DIR . 'includes/settings-transfer.php';
require_once KITS_CORE_DIR . 'includes/theme-options.php';
require_once KITS_CORE_DIR . 'includes/brand-customizer.php'; // Brand Customizer: tenant brand tokens UI (admin.php?page=kits-brand) and live CSS
require_once KITS_CORE_DIR . 'includes/onboarding-wizard.php'; // 1-Click Theme Setup & Onboarding Wizard (Astra/Kadence benchmark).
require_once KITS_CORE_DIR . 'includes/header-footer-scripts.php'; // Header and Footer Custom Scripts & Pixels (WPCode/Insert Headers benchmark).
require_once KITS_CORE_DIR . 'includes/admin-screens.php';
require_once KITS_CORE_DIR . 'includes/admin-menu.php';
require_once KITS_CORE_DIR . 'includes/admin-bar-products.php'; // The Wugweb menu in the admin bar.
require_once KITS_CORE_DIR . 'includes/admin-app.php';
require_once KITS_CORE_DIR . 'includes/admin-shell.php'; // Shared parts plugin screens are built from (docs/kits-plugin-ui.md).
require_once KITS_CORE_DIR . 'includes/admin-shell-parts.php'; // A classic screen's output as page parts for the shell.
require_once KITS_CORE_DIR . 'includes/admin-kits-frame.php'; // Kits → Home, Settings, Tools and Help on the plugin screens' look.
require_once KITS_CORE_DIR . 'includes/admin-app-global-styles.php';
require_once KITS_CORE_DIR . 'includes/button-hover.php';
require_once KITS_CORE_DIR . 'includes/admin-app-cards.php';
// The admin layer (moved from Animal Writes Core, 18 Nov 2026): menu arrangement and tidy, the site's rows on the Kits
// screens, the edit-screens map, edit fields and the Modules settings. The site's own values come through filters.
require_once KITS_CORE_DIR . 'includes/admin-layer/arrange.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/tidy.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/site-screens.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/cli-commands.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/edit-screens.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/edit-fields.php';
require_once KITS_CORE_DIR . 'includes/admin-layer/site-options.php';
require_once KITS_CORE_DIR . 'includes/site-parts.php';
require_once KITS_CORE_DIR . 'includes/fonts.php';
require_once KITS_CORE_DIR . 'includes/backups.php'; // the daily database and code backup into ~/backups, its REST download and the UpdraftPlus alert (moved from Animal Writes Core, 18 Nov 2026)
require_once KITS_CORE_DIR . 'includes/error-pages.php'; // the branded 500 and 503 drop-ins copied from dropins/ into wp-content (moved from Animal Writes Core, 18 Nov 2026)
// Campaign engines (moved from Animal Writes Core, 19 Nov 2026; names and data unchanged): the campaign link taxonomy and
// the letter tool's statistics (campaign-letters.php is a site module, see its header). The short links, press coverage,
// social posts, sponsored blocks and the Library document viewer are the theme's site layer (assets/site/includes/), as
// are the campaign pages and Home.
foreach ( array( 'campaign-links', 'campaign-letters' ) as $kits_engine_file ) {
	require_once KITS_CORE_DIR . 'includes/' . $kits_engine_file . '.php';
}
unset( $kits_engine_file );
// Site operations (moved from Animal Writes Core, 19 Nov 2026): scheduled-task health and Run now, the daily event, deploy purge
// and status, technical saves keeping the modified date, CDN cache times, Check site, the PHP error count, the 404 page and
// retention. The site's own values (page lists, sections, repository) come through filters from the theme's site layer.
require_once KITS_CORE_DIR . 'includes/dashboard-activity.php'; // the Dashboard Activity box, off unless the site switches it on (moved from Animal Writes Core, 19 Nov 2026)
foreach ( array( 'cron-health', 'daily', 'deploy-purge', 'deploy-status', 'keep-modified', 'cdn-cache', 'site-check', 'error-count', 'not-found', 'retention', 'compat' ) as $kits_ops_file ) {
	require_once KITS_CORE_DIR . 'includes/ops/' . $kits_ops_file . '.php';
}
unset( $kits_ops_file );

// Switched off: its scheduled jobs come off too (they are booked again on the next request after it is switched back on).
register_deactivation_hook(
	__FILE__,
	function () {
		wp_unschedule_hook( 'kits_spot_map_daily' ); // aw_fetch_favicons is SourceWeb's since 18 Nov 2026
		wp_unschedule_hook( 'aw_thumb_rebuild' ); // a queued thumbnail map rebuild (thumbs.php)
		// While it is off the rules may be rebuilt without the preset types and consent items may be published:
		// rebuild the rules and recheck consent when it is back on (includes/presets.php).
		delete_option( 'kits_presets_rewrite' );
		delete_option( 'kits_presets_consent' );
	}
);
