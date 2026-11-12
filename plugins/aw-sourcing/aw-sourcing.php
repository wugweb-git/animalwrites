<?php
/**
 * Plugin Name: SourceWeb
 * Description: What the workers fetch and how it arrives, in its own SourceWeb menu: Overview, To review, Enrichment review, the settings screens (sources with their feeds and relay, keywords, rules, press queries, categories, AI models), the reports and jobs (workers, rejected items, relay log, change log) and Settings; the worker endpoints (ingest, relay, sourcing policy, relevance, same story, summaries, enrichment) and auto-review. Needs Animal Writes Core, which loads it from this folder; it can also be activated on its own.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Wugweb
 * Author URI: https://wugweb.com
 * License: GPL-2.0-or-later
 * Update URI: https://wugweb.com/plugins/aw-sourcing/
 * Text Domain: aw-sourcing
 * Domain Path: /languages
 *
 * Moved out of Animal Writes Core and Kits Core on 24 Oct 2026 with no change in behaviour: the same option keys,
 * REST routes, cron hooks, admin pages and actions. Core keeps the content types and templates.
 *
 * This file only defines and includes (no functions): it may be reached twice, from Core and as an active plugin.
 *
 * @package aw-sourcing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Core loads this file; if it is also active on its own, the second load stops here.
if ( defined( 'AW_SOURCING_LOADED' ) ) {
	return;
}
// SourceWeb fills Animal Writes Core's content types (News, Library, Directory listings: content-types.php) with its
// place, tag and listing helpers, so it needs Core; Core also loads Kits Core, which holds the shared keyword table and
// sourcing lists (keyword-table.php, sources-config.php). Without Core, load nothing and say so.
if ( ! defined( 'AW_CORE_DIR' ) ) {
	add_action(
		'admin_notices',
		function () {
			wp_admin_notice( 'SourceWeb needs Animal Writes Core. Sourcing is off until Core is active.', array( 'type' => 'error' ) );
		}
	);
	return;
}
define( 'AW_SOURCING_LOADED', '0.1.0' );
define( 'AW_SOURCING_DIR', __DIR__ . '/' );

// The menu's shape and the Sources model (plain functions, tested without WordPress by tests/run.php) first.
require_once AW_SOURCING_DIR . 'includes/sourcing-basics.php'; // own capability, option names and host helpers (also in Kits Core)
require_once AW_SOURCING_DIR . 'includes/summary-rules.php'; // the summary rules (option aw_summary_rules); moved from Core 12 Nov 2026
require_once AW_SOURCING_DIR . 'includes/help-tab.php';
require_once AW_SOURCING_DIR . 'includes/plugin-info.php';
require_once AW_SOURCING_DIR . 'includes/menu-model.php'; // the Sourcing menu: slugs, screen id, order (admin-menu.php registers it)
require_once AW_SOURCING_DIR . 'includes/sources-model.php';
require_once AW_SOURCING_DIR . 'includes/keywords-model.php'; // phase 2: the lists in the keyword table (Core's)
require_once AW_SOURCING_DIR . 'includes/retire-model.php'; // phase 4: what stops being used, the policy without v1 keys
// Order matters for the Sourcing screens (Sources, Keywords, then these in this order; reports and Settings go last, menu-model.php).
require_once AW_SOURCING_DIR . 'includes/sourcing-policy.php';
require_once AW_SOURCING_DIR . 'includes/source-sites.php';
require_once AW_SOURCING_DIR . 'includes/source-icons.php';
require_once AW_SOURCING_DIR . 'includes/sourcing-lists.php';
require_once AW_SOURCING_DIR . 'includes/summary-rules-admin.php';
require_once AW_SOURCING_DIR . 'includes/relevance.php';
require_once AW_SOURCING_DIR . 'includes/ingest.php';
require_once AW_SOURCING_DIR . 'includes/opportunities-site.php';
require_once AW_SOURCING_DIR . 'includes/auto-review.php';
require_once AW_SOURCING_DIR . 'includes/same-story.php';
require_once AW_SOURCING_DIR . 'includes/summary-rewrite.php';
require_once AW_SOURCING_DIR . 'includes/news-titles.php';
require_once AW_SOURCING_DIR . 'includes/enrichment.php';
require_once AW_SOURCING_DIR . 'includes/enrichment-cleanup.php';
require_once AW_SOURCING_DIR . 'includes/enrichment-admin.php';
require_once AW_SOURCING_DIR . 'includes/rules-model.php'; // sourcing restructure phase 3: Rules
require_once AW_SOURCING_DIR . 'includes/rules.php';
require_once AW_SOURCING_DIR . 'includes/rules-admin.php';
require_once AW_SOURCING_DIR . 'includes/enrichment-front.php';
require_once AW_SOURCING_DIR . 'includes/review-queue.php';
require_once AW_SOURCING_DIR . 'includes/keyword-suggestions.php';
require_once AW_SOURCING_DIR . 'includes/ai-keys.php';
require_once AW_SOURCING_DIR . 'includes/site-health.php';
require_once AW_SOURCING_DIR . 'includes/relay.php';
require_once AW_SOURCING_DIR . 'includes/sourcing-migrate.php'; // WP-CLI only: backup, migrate, verify, rollback
require_once AW_SOURCING_DIR . 'includes/sourcing-migrate-phase2.php'; // WP-CLI only: the same for phase 2 (Keywords)
require_once AW_SOURCING_DIR . 'includes/sourcing-migrate-rules.php'; // WP-CLI only: phase 3 (Rules)
require_once AW_SOURCING_DIR . 'includes/sourcing-migrate-retire.php'; // WP-CLI only: phase 4 (Retire: archive, stop using the old lists)
require_once AW_SOURCING_DIR . 'includes/keyword-stats.php';
require_once AW_SOURCING_DIR . 'includes/search-insights-tab.php';
require_once AW_SOURCING_DIR . 'includes/workers.php';
require_once AW_SOURCING_DIR . 'includes/workers-tick.php'; // WP-CLI: wp aw workers-tick (hPanel cron every 15 minutes)
require_once AW_SOURCING_DIR . 'includes/sourcing-terms-admin.php';
require_once AW_SOURCING_DIR . 'includes/keywords-admin.php'; // phase 2: Keywords on the keyword table
// Workers you can organise and AI keys attached to workers (owner, 24 Oct 2026).
require_once AW_SOURCING_DIR . 'includes/workers-meta.php';
require_once AW_SOURCING_DIR . 'includes/workers-admin.php';
require_once AW_SOURCING_DIR . 'includes/ai-provider-icons.php';
// The Sourcing menu, its Overview and the Plugins screen links (owner, 25 Oct 2026).
require_once AW_SOURCING_DIR . 'includes/admin-menu.php';
require_once AW_SOURCING_DIR . 'includes/dashboard.php'; // the Dashboard box "Sourcing" (owner, 3 Nov 2026)
require_once AW_SOURCING_DIR . 'includes/kits-hub.php'; // a tab and a status row in the Kits admin, when Kits Core is loaded.
// The new screens on the Kits admin shell, the default (Dashboard, To review, Enrichment review, Settings).
require_once AW_SOURCING_DIR . 'includes/sourceweb-model.php';
require_once AW_SOURCING_DIR . 'includes/sourceweb-dashboard.php';
require_once AW_SOURCING_DIR . 'includes/sourceweb-rest.php';
require_once AW_SOURCING_DIR . 'includes/sourceweb-rest-pages.php'; // the tabs rebuilt after 12 Nov 2026 (Workers …)
require_once AW_SOURCING_DIR . 'includes/sourceweb-rest-ai.php'; // AI models on the new screen (12 Nov 2026)
require_once AW_SOURCING_DIR . 'includes/sourceweb-rest-logs.php'; // Rejected, Relay log, Change log on the new screen (12 Nov 2026)
require_once AW_SOURCING_DIR . 'includes/sourceweb-ui.php';
