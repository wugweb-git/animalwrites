<?php
/**
 * Search Insights (owner, 24 Oct 2026; plan in docs/seo/search-insights-plan.md). One record per keyword, with its
 * roles, owning pages, Search Console figures by day, priority and a suggested action. Read-only data and suggestions:
 * nothing here edits Rank Math keyphrases or the Sourcing lists.
 *
 * Phase 1: tables, the one-time seed from the keyword map, Search Console through Site Kit (each module as its own
 * owner, one call per calendar day), keyword roles, priority, the 90-day rollup, Overview and Keywords tabs, a Site
 * Health test and the `wp aw-si` commands. The Search demand screen (search-demand.php) stays as the Searches tab.
 *
 * @package aw-seo-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( array( 'schema', 'runs', 'sitekit', 'keywords', 'gsc', 'compute', 'rollup', 'cron', 'health', 'admin', 'cli' ) as $aw_si_file ) {
	require_once __DIR__ . '/search-insights/' . $aw_si_file . '.php';
}
unset( $aw_si_file );
