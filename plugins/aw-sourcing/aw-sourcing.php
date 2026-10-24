<?php
/**
 * Plugin Name: AW Sourcing
 * Description: What the workers fetch and how it arrives: Settings → Sourcing (publishers, keywords, feeds, Library, opportunity and press sources, categories, summary rules, rejected items, enrichment, relay), Settings → AI models, the worker endpoints (ingest, relay, sourcing policy, relevance, same story, summaries, enrichment), auto-review and Inbox → To review. Needs Animal Writes Core, which loads it from this folder; it can also be activated on its own.
 * Version: 0.1.0
 * Author: Wugweb
 * License: GPL-2.0-or-later
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
// Everything here runs on Core's functions; without Core, load nothing and say so.
if ( ! defined( 'AW_CORE_DIR' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>AW Sourcing needs Animal Writes Core. Sourcing is off until Core is active.</p></div>';
		}
	);
	return;
}
define( 'AW_SOURCING_LOADED', '0.1.0' );
define( 'AW_SOURCING_DIR', __DIR__ . '/' );

// Order matters for the Settings → Sourcing tabs (Publishers, Keywords, then these in this order).
require_once AW_SOURCING_DIR . 'includes/sourcing-policy.php';
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
require_once AW_SOURCING_DIR . 'includes/enrichment-admin.php';
require_once AW_SOURCING_DIR . 'includes/enrichment-front.php';
require_once AW_SOURCING_DIR . 'includes/review-queue.php';
require_once AW_SOURCING_DIR . 'includes/keyword-suggestions.php';
require_once AW_SOURCING_DIR . 'includes/ai-keys.php';
require_once AW_SOURCING_DIR . 'includes/site-health.php';
require_once AW_SOURCING_DIR . 'includes/relay.php';
require_once AW_SOURCING_DIR . 'includes/keyword-stats.php';
require_once AW_SOURCING_DIR . 'includes/workers.php';
